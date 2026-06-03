<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModule;
use App\Services\CourseScoringService;
use App\Services\CourseSectionService;
use App\Services\PracticeLabDaemonClient;
use App\Services\PracticeLabService;
use App\Support\AdminCourseContentInspector;
use App\Support\AdminNavigation;
use App\Support\CourseModuleMeta;
use App\Support\CourseTheoryPaths;
use App\Support\PracticeHintMarkdown;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;
use ZipArchive;

class AdminTheoryController extends Controller
{
    private function adminLabCacheKeyPrefix(): string
    {
        return 'lid:'.(int) session('learner_id', 0);
    }

    private const MAX_BYTES = 2_500_000;
    private const ADMIN_LAB_TTL_MINUTES = 120;
    private const IMAGE_STATS_TTL_MINUTES = 720;

    public function index(Request $request): View
    {
        $key = $this->adminLabCacheKeyPrefix();
        $rows = [];
        $labStateMap = [];
        $imageStatsByImage = [];
        $courseId = (int) session('admin_course_id');
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        $isAltCourse = $course && $course->isLegacyAltCourse();
        $useDbModules = $courseId > 0 && Schema::hasTable('course_modules')
            && CourseModule::query()->where('course_id', $courseId)->exists();

        $client = PracticeLabDaemonClient::fromConfig();
        $lab = PracticeLabService::make();

        if ($useDbModules) {
            foreach (CourseModule::query()->where('course_id', $courseId)->orderBy('sort')->orderBy('id')->get() as $ent) {
                $m = $ent->effectiveContentIndex();
                $title = $ent->title.' · пакет №'.$m;
                $rows[] = $this->theoryIndexRow($m, $title, $isAltCourse, $ent, $lab);
                $labStateMap[$m] = $this->adminLabState($key, $m);
            }
        } elseif ($isAltCourse) {
            for ($m = 1; $m <= 9; $m++) {
                $rows[] = $this->theoryIndexRow($m, '', true, null, $lab);
                $labStateMap[$m] = $this->adminLabState($key, $m);
            }
        }

        $images = [];
        foreach ($rows as $r) {
            if (! empty($r['practice_lab_docker_image'])) {
                $images[] = (string) $r['practice_lab_docker_image'];
            }
        }
        $finalImg = $isAltCourse ? AdminCourseContentInspector::practiceLabDockerImageForModule(10) : null;
        if ($finalImg) {
            $images[] = $finalImg;
        }
        $images = array_values(array_unique(array_filter($images)));
        if ($client && $images !== []) {
            foreach ($images as $img) {
                $imageStatsByImage[$img] = $this->cachedImageStats($client, $img);
            }
        }

        return view('admin.theory-index', [
            'rows' => $rows,
            'selectedCourse' => $course,
            'adminLabStates' => $labStateMap,
            'finalLabState' => $this->adminLabState($key, 10),
            'finalLabDockerImage' => $finalImg,
            'imageStatsByImage' => $imageStatsByImage,
            'isReadOnly' => $this->isReadOnlyAccess($request),
            'adminKey' => (string) $request->query('key', ''),
        ]);
    }

    public function startPracticeLabProbe(Request $request, Course $adminCourse, int $module): RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 10, 404);
        if ($this->isReadOnlyAccess($request)) {
            return $this->redirectToTheoryIndex($request)->with('err', 'Режим модератора: запуск контейнера недоступен.');
        }
        $key = $this->adminLabCacheKeyPrefix();
        $image = AdminCourseContentInspector::practiceLabDockerImageForModule($module);
        if (! $image) {
            return $this->redirectToTheoryIndex($request)->with('err', 'Для модуля '.$module.' не задан Docker-образ.');
        }
        $state = $this->adminLabState($key, $module);
        if (is_array($state) && ! empty($state['lab_id'])) {
            return $this->redirectToTheoryIndex($request)->with('ok', 'Проверочный стенд уже запущен для модуля '.$module.'.');
        }
        $client = PracticeLabDaemonClient::fromConfig();
        if (! $client) {
            return $this->redirectToTheoryIndex($request)->with('err', 'Lab-daemon не настроен (PRACTICE_LAB_DAEMON_URL / SECRET).');
        }
        try {
            $resp = $client->createLab($this->adminPseudoLearnerId($key), $module, $image);
        } catch (Throwable $e) {
            return $this->redirectToTheoryIndex($request)->with('err', 'Не удалось запустить стенд: '.$e->getMessage());
        }

        $payload = [
            'lab_id' => (string) ($resp['lab_id'] ?? ''),
            'terminal_url' => (string) ($resp['terminal_url'] ?? ''),
            'image' => $image,
            'started_at' => now()->toIso8601String(),
        ];
        Cache::put($this->adminLabStateCacheKey($key, $module), $payload, now()->addMinutes(self::ADMIN_LAB_TTL_MINUTES));

        return $this->redirectToTheoryIndex($request)->with('ok', 'Проверочный стенд модуля '.$module.' запущен.');
    }

    public function finishPracticeLabProbe(Request $request, Course $adminCourse, int $module): RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 10, 404);
        if ($this->isReadOnlyAccess($request)) {
            return $this->redirectToTheoryIndex($request)->with('err', 'Режим модератора: завершение контейнера недоступно.');
        }
        $key = $this->adminLabCacheKeyPrefix();
        $state = $this->adminLabState($key, $module);
        $client = PracticeLabDaemonClient::fromConfig();
        if ($client && is_array($state) && ! empty($state['lab_id'])) {
            try {
                $client->destroyLab((string) $state['lab_id']);
            } catch (Throwable) {
                // best effort
            }
        }
        Cache::forget($this->adminLabStateCacheKey($key, $module));

        return $this->redirectToTheoryIndex($request)->with('ok', 'Проверочный стенд модуля '.$module.' завершён.');
    }

    /**
     * Предпросмотр теории как у студента (Markdown + Mermaid), для iframe в модалке админки.
     */
    public function previewTheory(Request $request, Course $adminCourse, int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $meta = $this->previewModuleMeta($adminCourse, $module);
        $theoryRaw = (string) ($meta['theory'] ?? '');
        if (trim($theoryRaw) === '') {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Модуль '.$module.': нет текста теории для предпросмотра.');
        }

        return view('admin.theory-preview', [
            'module' => $module,
            'meta' => $meta,
            'isReadOnly' => $this->isReadOnlyAccess($request),
        ]);
    }

    public function edit(Request $request, Course $adminCourse, int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $ref = CourseTheoryPaths::rawTheoryReference($module);
        $snippet = CourseTheoryPaths::snippetBasenameFromReference($ref);
        if ($snippet === null || ! CourseTheoryPaths::snippetBasenameTargetsModule($snippet, $module)) {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Модуль '.$module.': в course.php должна быть ссылка вида @snippet:module_<номер>_theory.md для этого модуля.');
        }
        $path = CourseTheoryPaths::absolutePathForSnippetBasename($snippet);
        $markdown = is_file($path) ? (string) file_get_contents($path) : '';

        return view('admin.theory-edit', [
            'module' => $module,
            'meta' => is_array(config('course.modules.'.$module)) ? config('course.modules.'.$module) : [],
            'markdown' => $markdown,
            'filename' => $snippet,
        ]);
    }

    public function update(Request $request, Course $adminCourse, int $module): RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $ref = CourseTheoryPaths::rawTheoryReference($module);
        $snippet = CourseTheoryPaths::snippetBasenameFromReference($ref);
        if ($snippet === null || ! CourseTheoryPaths::snippetBasenameTargetsModule($snippet, $module)) {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Сохранение отклонено: для модуля нет допустимой ссылки @snippet:module_*_theory.md.');
        }

        $validated = $request->validate([
            'markdown' => ['nullable', 'string', 'max:'.self::MAX_BYTES],
        ]);

        $path = CourseTheoryPaths::absolutePathForSnippetBasename($snippet);
        $dir = CourseTheoryPaths::snippetsDirectory();
        if (! is_dir($dir)) {
            return redirect()
                ->route('admin.theory.edit', array_merge($this->theoryRouteQuery($request), ['module' => $module]))
                ->with('err', 'Каталог сниппетов недоступен: '.$dir);
        }

        $body = str_replace("\r\n", "\n", (string) ($validated['markdown'] ?? ''));
        if (strlen($body) > self::MAX_BYTES) {
            return redirect()
                ->route('admin.theory.edit', array_merge($this->theoryRouteQuery($request), ['module' => $module]))
                ->with('err', 'Слишком большой объём текста.');
        }

        if (file_put_contents($path, $body) === false) {
            return redirect()
                ->route('admin.theory.edit', array_merge($this->theoryRouteQuery($request), ['module' => $module]))
                ->with('err', 'Не удалось записать файл (права на каталог config/snippets?).');
        }

        return redirect()
            ->route('admin.theory.edit', array_merge($this->theoryRouteQuery($request), ['module' => $module]))
            ->with('ok', 'Теория модуля '.$module.' сохранена в '.$snippet);
    }

    public function previewTheoryQuiz(Request $request, Course $adminCourse, int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $questions = $this->previewTheoryQuizQuestions($adminCourse, $module);
        if ($questions === []) {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Модуль '.$module.': нет вопросов теста по теории.');
        }

        return view('admin.content-theory-quiz', [
            'module' => $module,
            'meta' => $this->previewModuleMeta($adminCourse, $module),
            'questions' => $questions,
        ]);
    }

    public function previewPractice(Request $request, Course $adminCourse, int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $markdown = PracticeHintMarkdown::stripBlockquoteHintsUnlessVisible(
            $this->previewPracticeMarkdown($adminCourse, $module),
            true
        );
        if ($markdown === '') {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Модуль '.$module.': нет текста практики.');
        }

        return view('admin.content-practice', [
            'module' => $module,
            'meta' => $this->previewModuleMeta($adminCourse, $module),
            'practiceMarkdown' => $markdown,
        ]);
    }

    public function previewModuleExam(Request $request, Course $adminCourse, int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $questions = $this->previewModuleExamQuestions($adminCourse, $module);
        if ($questions === []) {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Модуль '.$module.': нет вопросов итогового теста.');
        }

        return view('admin.content-module-exam', [
            'module' => $module,
            'meta' => $this->previewModuleMeta($adminCourse, $module),
            'questions' => $questions,
            'timeLimitMinutes' => $this->moduleExamTimeLimitMinutes($adminCourse, $module),
        ]);
    }

    public function previewFinalLab(Request $request, Course $adminCourse): View
    {
        $courseId = (int) session('admin_course_id', 0);
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        if ($course && Schema::hasColumn('courses', 'final_lab_enabled') && ! $course->final_lab_enabled) {
            abort(404);
        }

        return view('admin.content-final-lab', [
            'isReadOnly' => $this->isReadOnlyAccess($request),
        ]);
    }

    private function moduleExamTimeLimitMinutes(Course $course, int $module): int
    {
        if ($course->isLegacyAltCourse()) {
            $v = config('course.modules.'.$module.'.module_exam_time_limit_minutes');

            return (is_numeric($v) && (int) $v > 0)
                ? (int) $v
                : CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;
        }
        $cm = $this->courseModuleForContentIndex($course, $module);
        if ($cm === null) {
            return CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;
        }

        return app(CourseSectionService::class)->examTimeLimitMinutes((int) $cm->id, $module, false);
    }

    /**
     * @return array<string, mixed>
     */
    private function theoryIndexRow(int $m, string $titleOverride = '', bool $legacyAlt = true, ?CourseModule $cm = null, ?PracticeLabService $lab = null): array
    {
        $meta = $legacyAlt ? config('course.modules.'.$m) : null;
        $title = $titleOverride !== ''
            ? $titleOverride
            : (is_array($meta) ? (string) ($meta['title'] ?? 'Модуль '.$m) : '—');
        $ref = $legacyAlt ? CourseTheoryPaths::rawTheoryReference($m) : '';
        $snippet = $legacyAlt ? CourseTheoryPaths::snippetBasenameFromReference($ref) : null;
        $editable = $legacyAlt && $snippet !== null && CourseTheoryPaths::snippetBasenameTargetsModule($snippet, $m);
        $tq = $legacyAlt ? AdminCourseContentInspector::theoryQuizQuestions($m) : [];
        $ex = $legacyAlt ? AdminCourseContentInspector::moduleExamQuestions($m) : [];
        $practiceMd = $legacyAlt ? AdminCourseContentInspector::practiceMarkdown($m) : '';
        $theoryChars = $legacyAlt ? AdminCourseContentInspector::theoryCharacterCount($m) : 0;
        $examTimeMin = $legacyAlt
            ? $this->legacyModuleExamTimeLimitMinutes($m)
            : CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;

        if (! $legacyAlt && $cm instanceof CourseModule) {
            $course = $cm->relationLoaded('course')
                ? $cm->course
                : Course::query()->find((int) $cm->course_id);
            if ($course instanceof Course) {
                $db = AdminCourseContentInspector::databaseModuleContentSummary($course, $cm);
                $tq = $db['theory_quiz'];
                $ex = $db['exam'];
                $practiceMd = (string) $db['practice_markdown'];
                $theoryChars = (int) $db['theory_chars'];
                $examTimeMin = (int) $db['exam_time_min'];
            }
        }

        $dockerImage = ($cm && $lab) ? $lab->imageForCourseModule($cm, $m) : ($legacyAlt ? AdminCourseContentInspector::practiceLabDockerImageForModule($m) : null);

        return [
            'module' => $m,
            'title' => $title,
            'editable' => $editable,
            'ref' => $ref !== '' ? $ref : '—',
            'theory_chars' => $theoryChars,
            'theory_quiz_count' => count($tq),
            'theory_quiz_match' => AdminCourseContentInspector::countMatchDrag($tq),
            'exam_count' => count($ex),
            'exam_match' => AdminCourseContentInspector::countMatchDrag($ex),
            'exam_time_min' => $examTimeMin,
            'practice_summary' => AdminCourseContentInspector::practiceSummaryLine($practiceMd),
            'has_practice' => $practiceMd !== '',
            'practice_lab_docker_image' => $dockerImage,
        ];
    }

    private function legacyModuleExamTimeLimitMinutes(int $module): int
    {
        $v = config('course.modules.'.$module.'.module_exam_time_limit_minutes');

        return (is_numeric($v) && (int) $v > 0)
            ? (int) $v
            : CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;
    }

    private function courseModuleForContentIndex(Course $course, int $contentIndex): ?CourseModule
    {
        foreach ($course->courseModules()->orderBy('sort')->orderBy('id')->get() as $cm) {
            if ($cm->effectiveContentIndex() === $contentIndex) {
                return $cm;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function previewModuleMeta(Course $course, int $module): array
    {
        if (! $course->isLegacyAltCourse()) {
            $cm = $this->courseModuleForContentIndex($course, $module);
            if ($cm !== null) {
                $db = AdminCourseContentInspector::databaseModuleContentSummary($course, $cm);

                return [
                    'title' => (string) $cm->title,
                    'summary' => (string) ($cm->summary ?? ''),
                    'theory' => (string) $db['theory_markdown'],
                    'practice' => (string) $db['practice_markdown'],
                ];
            }
        }

        return CourseModuleMeta::resolved($module);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function previewTheoryQuizQuestions(Course $course, int $module): array
    {
        if (! $course->isLegacyAltCourse()) {
            $cm = $this->courseModuleForContentIndex($course, $module);
            if ($cm !== null) {
                return AdminCourseContentInspector::databaseModuleContentSummary($course, $cm)['theory_quiz'];
            }

            return [];
        }

        return AdminCourseContentInspector::theoryQuizQuestions($module);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function previewModuleExamQuestions(Course $course, int $module): array
    {
        if (! $course->isLegacyAltCourse()) {
            $cm = $this->courseModuleForContentIndex($course, $module);
            if ($cm !== null) {
                return AdminCourseContentInspector::databaseModuleContentSummary($course, $cm)['exam'];
            }

            return [];
        }

        return AdminCourseContentInspector::moduleExamQuestions($module);
    }

    private function previewPracticeMarkdown(Course $course, int $module): string
    {
        if (! $course->isLegacyAltCourse()) {
            $cm = $this->courseModuleForContentIndex($course, $module);
            if ($cm !== null) {
                return (string) AdminCourseContentInspector::databaseModuleContentSummary($course, $cm)['practice_markdown'];
            }

            return '';
        }

        return AdminCourseContentInspector::practiceMarkdown($module);
    }

    private function isReadOnlyAccess(Request $request): bool
    {
        return (bool) $request->attributes->get('course_admin_readonly', false);
    }

    private function adminLabState(string $key, int $module): ?array
    {
        $state = Cache::get($this->adminLabStateCacheKey($key, $module));

        return is_array($state) ? $state : null;
    }

    private function adminLabStateCacheKey(string $key, int $module): string
    {
        return 'admin_lab_probe:'.sha1($key).':'.$module;
    }

    private function imageStatsCacheKey(string $image): string
    {
        return 'admin_docker_image_stats:'.sha1($image);
    }

    private function cachedImageStats(PracticeLabDaemonClient $client, string $image): ?array
    {
        $key = $this->imageStatsCacheKey($image);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $data = $client->imageStats($image);
            $ok = is_array($data) ? $data : null;
        } catch (Throwable) {
            $ok = null;
        }
        Cache::put($key, $ok, now()->addMinutes(self::IMAGE_STATS_TTL_MINUTES));

        return $ok;
    }

    private function adminPseudoLearnerId(string $key): int
    {
        $v = abs((int) crc32('admin:'.$key));

        return 900000 + ($v % 99999);
    }

    private function theoryRouteQuery(Request $request): array
    {
        $rp = AdminNavigation::adminCourseRouteParams();
        $key = (string) $request->query('key', '');
        if ($key !== '') {
            $rp['key'] = $key;
        }

        return $rp;
    }

    private function redirectToTheoryIndex(Request $request): RedirectResponse
    {
        return redirect()->route('admin.theory.index', $this->theoryRouteQuery($request));
    }

    public function downloadZip(Request $request): Response|RedirectResponse
    {
        $files = CourseTheoryPaths::existingTheoryMarkdownFiles();
        if ($files === []) {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Нет файлов module_*_theory.md в config/snippets.');
        }

        if (! class_exists(ZipArchive::class)) {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Расширение PHP zip (ZipArchive) недоступно на сервере.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'theory-md-');
        if ($tmp === false) {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Не удалось создать временный файл.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);

            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
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
