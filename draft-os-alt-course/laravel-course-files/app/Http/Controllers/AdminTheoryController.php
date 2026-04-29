<?php

namespace App\Http\Controllers;

use App\Services\CourseScoringService;
use App\Support\AdminCourseContentInspector;
use App\Support\CourseModuleMeta;
use App\Support\CourseTheoryPaths;
use App\Support\PracticeHintMarkdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use ZipArchive;

class AdminTheoryController extends Controller
{
    private const MAX_BYTES = 2_500_000;

    public function index(Request $request): View
    {
        $key = (string) $request->query('key', '');
        $rows = [];
        for ($m = 1; $m <= 9; $m++) {
            $meta = config('course.modules.'.$m);
            $title = is_array($meta) ? (string) ($meta['title'] ?? 'Модуль '.$m) : '—';
            $ref = CourseTheoryPaths::rawTheoryReference($m);
            $snippet = CourseTheoryPaths::snippetBasenameFromReference($ref);
            $editable = $snippet !== null && CourseTheoryPaths::snippetBasenameTargetsModule($snippet, $m);
            $tq = AdminCourseContentInspector::theoryQuizQuestions($m);
            $ex = AdminCourseContentInspector::moduleExamQuestions($m);
            $practiceMd = AdminCourseContentInspector::practiceMarkdown($m);
            $dockerImage = AdminCourseContentInspector::practiceLabDockerImageForModule($m);
            $theoryChars = AdminCourseContentInspector::theoryCharacterCount($m);
            $rows[] = [
                'module' => $m,
                'title' => $title,
                'editable' => $editable,
                'ref' => $ref !== '' ? $ref : '—',
                'theory_chars' => $theoryChars,
                'theory_quiz_count' => count($tq),
                'theory_quiz_match' => AdminCourseContentInspector::countMatchDrag($tq),
                'exam_count' => count($ex),
                'exam_match' => AdminCourseContentInspector::countMatchDrag($ex),
                'exam_time_min' => $this->moduleExamTimeLimitMinutes($m),
                'practice_summary' => AdminCourseContentInspector::practiceSummaryLine($practiceMd),
                'has_practice' => $practiceMd !== '',
                'practice_lab_docker_image' => $dockerImage,
            ];
        }

        return view('admin.theory-index', [
            'adminKey' => $key,
            'rows' => $rows,
            'isReadOnly' => $this->isReadOnlyAccess($request),
        ]);
    }

    /**
     * Предпросмотр теории как у студента (Markdown + Mermaid), для iframe в модалке админки.
     */
    public function previewTheory(Request $request, int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $key = (string) $request->query('key', '');
        $meta = CourseModuleMeta::resolved($module);
        $theoryRaw = (string) ($meta['theory'] ?? '');
        if (trim($theoryRaw) === '') {
            return redirect()
                ->route('admin.theory.index', ['key' => $key])
                ->with('err', 'Модуль '.$module.': нет текста теории для предпросмотра.');
        }

        return view('admin.theory-preview', [
            'adminKey' => $key,
            'module' => $module,
            'meta' => $meta,
            'isReadOnly' => $this->isReadOnlyAccess($request),
        ]);
    }

    public function edit(Request $request, int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $key = (string) $request->query('key', '');
        $ref = CourseTheoryPaths::rawTheoryReference($module);
        $snippet = CourseTheoryPaths::snippetBasenameFromReference($ref);
        if ($snippet === null || ! CourseTheoryPaths::snippetBasenameTargetsModule($snippet, $module)) {
            return redirect()
                ->route('admin.theory.index', ['key' => $key])
                ->with('err', 'Модуль '.$module.': в course.php должна быть ссылка вида @snippet:module_<номер>_theory.md для этого модуля.');
        }
        $path = CourseTheoryPaths::absolutePathForSnippetBasename($snippet);
        $markdown = is_file($path) ? (string) file_get_contents($path) : '';

        return view('admin.theory-edit', [
            'adminKey' => $key,
            'module' => $module,
            'meta' => is_array(config('course.modules.'.$module)) ? config('course.modules.'.$module) : [],
            'markdown' => $markdown,
            'filename' => $snippet,
        ]);
    }

    public function update(Request $request, int $module): RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $key = (string) $request->query('key', '');
        $ref = CourseTheoryPaths::rawTheoryReference($module);
        $snippet = CourseTheoryPaths::snippetBasenameFromReference($ref);
        if ($snippet === null || ! CourseTheoryPaths::snippetBasenameTargetsModule($snippet, $module)) {
            return redirect()
                ->route('admin.theory.index', ['key' => $key])
                ->with('err', 'Сохранение отклонено: для модуля нет допустимой ссылки @snippet:module_*_theory.md.');
        }

        $validated = $request->validate([
            'markdown' => ['nullable', 'string', 'max:'.self::MAX_BYTES],
        ]);

        $path = CourseTheoryPaths::absolutePathForSnippetBasename($snippet);
        $dir = CourseTheoryPaths::snippetsDirectory();
        if (! is_dir($dir)) {
            return redirect()
                ->route('admin.theory.edit', ['module' => $module, 'key' => $key])
                ->with('err', 'Каталог сниппетов недоступен: '.$dir);
        }

        $body = str_replace("\r\n", "\n", (string) ($validated['markdown'] ?? ''));
        if (strlen($body) > self::MAX_BYTES) {
            return redirect()
                ->route('admin.theory.edit', ['module' => $module, 'key' => $key])
                ->with('err', 'Слишком большой объём текста.');
        }

        if (file_put_contents($path, $body) === false) {
            return redirect()
                ->route('admin.theory.edit', ['module' => $module, 'key' => $key])
                ->with('err', 'Не удалось записать файл (права на каталог config/snippets?).');
        }

        return redirect()
            ->route('admin.theory.edit', ['module' => $module, 'key' => $key])
            ->with('ok', 'Теория модуля '.$module.' сохранена в '.$snippet);
    }

    public function previewTheoryQuiz(Request $request, int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $key = (string) $request->query('key', '');
        $questions = AdminCourseContentInspector::theoryQuizQuestions($module);
        if ($questions === []) {
            return redirect()
                ->route('admin.theory.index', ['key' => $key])
                ->with('err', 'Модуль '.$module.': в конфиге нет вопросов теста по теории (theory_quiz).');
        }

        return view('admin.content-theory-quiz', [
            'adminKey' => $key,
            'module' => $module,
            'meta' => is_array(config('course.modules.'.$module)) ? config('course.modules.'.$module) : [],
            'questions' => $questions,
        ]);
    }

    public function previewPractice(Request $request, int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $key = (string) $request->query('key', '');
        $markdown = PracticeHintMarkdown::stripBlockquoteHintsUnlessVisible(
            AdminCourseContentInspector::practiceMarkdown($module),
            true
        );
        if ($markdown === '') {
            return redirect()
                ->route('admin.theory.index', ['key' => $key])
                ->with('err', 'Модуль '.$module.': в конфиге нет текста практики.');
        }

        return view('admin.content-practice', [
            'adminKey' => $key,
            'module' => $module,
            'meta' => is_array(config('course.modules.'.$module)) ? config('course.modules.'.$module) : [],
            'practiceMarkdown' => $markdown,
        ]);
    }

    public function previewModuleExam(Request $request, int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $key = (string) $request->query('key', '');
        $questions = AdminCourseContentInspector::moduleExamQuestions($module);
        if ($questions === []) {
            return redirect()
                ->route('admin.theory.index', ['key' => $key])
                ->with('err', 'Модуль '.$module.': в конфиге нет вопросов итогового теста (module_exam).');
        }

        return view('admin.content-module-exam', [
            'adminKey' => $key,
            'module' => $module,
            'meta' => is_array(config('course.modules.'.$module)) ? config('course.modules.'.$module) : [],
            'questions' => $questions,
            'timeLimitMinutes' => $this->moduleExamTimeLimitMinutes($module),
        ]);
    }

    private function moduleExamTimeLimitMinutes(int $module): int
    {
        $v = config('course.modules.'.$module.'.module_exam_time_limit_minutes');

        return (is_numeric($v) && (int) $v > 0)
            ? (int) $v
            : CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;
    }

    private function isReadOnlyAccess(Request $request): bool
    {
        return (bool) $request->attributes->get('course_admin_readonly', false);
    }

    public function downloadZip(Request $request): Response|RedirectResponse
    {
        $key = (string) $request->query('key', '');
        $files = CourseTheoryPaths::existingTheoryMarkdownFiles();
        if ($files === []) {
            return redirect()
                ->route('admin.theory.index', ['key' => $key])
                ->with('err', 'Нет файлов module_*_theory.md в config/snippets.');
        }

        if (! class_exists(ZipArchive::class)) {
            return redirect()
                ->route('admin.theory.index', ['key' => $key])
                ->with('err', 'Расширение PHP zip (ZipArchive) недоступно на сервере.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'theory-md-');
        if ($tmp === false) {
            return redirect()
                ->route('admin.theory.index', ['key' => $key])
                ->with('err', 'Не удалось создать временный файл.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);

            return redirect()
                ->route('admin.theory.index', ['key' => $key])
                ->with('err', 'Не удалось создать ZIP.');
        }

        foreach ($files as $path) {
            $zip->addFile($path, basename($path));
        }
        $zip->close();

        $binary = file_get_contents($tmp) ?: '';
        @unlink($tmp);

        $name = 'course-theory-md-'.date('Y-m-d').'.zip';

        return response($binary, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }
}
