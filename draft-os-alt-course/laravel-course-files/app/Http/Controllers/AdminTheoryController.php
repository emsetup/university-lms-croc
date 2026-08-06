<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Services\CourseModuleService;
use App\Services\CourseScoringService;
use App\Services\CourseSectionService;
use App\Services\PortalStaffAccess;
use App\Services\PracticeLabDaemonClient;
use App\Services\PracticeLabService;
use App\Services\TheoryWordExportService;
use App\Support\AdminContentMarkdown;
use App\Support\AdminCourseContentInspector;
use App\Support\AdminNavigation;
use App\Support\CourseModuleMeta;
use App\Support\CourseTheoryPaths;
use App\Support\PracticeHintMarkdown;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
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
        $contentColumns = AdminCourseContentInspector::contentColumnsForCourse($courseId, (bool) $isAltCourse);

        $client = PracticeLabDaemonClient::fromConfig();
        $lab = PracticeLabService::make();
        $moduleSvc = app(CourseModuleService::class);
        $gate = app(PortalStaffAccess::class);

        if ($useDbModules) {
            foreach (CourseModule::query()->where('course_id', $courseId)->orderBy('sort')->orderBy('id')->get() as $ent) {
                if ($gate->usesGrantBasedAccess($courseId) && ! $gate->canViewModuleInAdmin((int) $ent->id)) {
                    continue;
                }
                $m = $ent->effectiveContentIndex();
                $title = (string) ($ent->title !== '' ? $ent->title : 'Модуль '.$moduleSvc->sequenceForModule($ent));
                $rows[] = $this->theoryIndexRow($m, $title, $isAltCourse, $ent, $lab, $contentColumns);
                $labStateMap[$m] = $this->adminLabState($key, $m);
            }
        } elseif ($isAltCourse) {
            for ($m = 1; $m <= 9; $m++) {
                $rows[] = $this->theoryIndexRow($m, '', true, null, $lab, $contentColumns);
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
            'contentColumns' => $contentColumns,
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

    /**
     * Live-предпросмотр Markdown как у обучающегося (callouts, media, GFM).
     */
    public function previewMarkdown(Request $request, Course $adminCourse): JsonResponse
    {
        $data = $request->validate([
            'markdown' => ['nullable', 'string', 'max:'.self::MAX_BYTES],
        ]);

        return response()->json([
            'html' => AdminContentMarkdown::toHtml((string) ($data['markdown'] ?? '')),
        ]);
    }

    public function edit(Request $request, Course $adminCourse, int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $this->assertCanEditTheoryModule($module);
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
        $this->assertCanEditTheoryModule($module);
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
     * @param  list<array{key: string, label: string}>  $contentColumns
     * @return array<string, mixed>
     */
    private function theoryIndexRow(int $m, string $titleOverride = '', bool $legacyAlt = true, ?CourseModule $cm = null, ?PracticeLabService $lab = null, array $contentColumns = []): array
    {
        $meta = $legacyAlt ? config('course.modules.'.$m) : null;
        $moduleSequence = $legacyAlt ? $m : ($cm instanceof CourseModule ? app(CourseModuleService::class)->sequenceForModule($cm) : $m);
        $title = $titleOverride !== ''
            ? $titleOverride
            : (is_array($meta) ? (string) ($meta['title'] ?? 'Модуль '.$m) : '—');
        $ref = $legacyAlt ? CourseTheoryPaths::rawTheoryReference($m) : '';
        $snippet = $legacyAlt ? CourseTheoryPaths::snippetBasenameFromReference($ref) : null;
        $editable = $legacyAlt && $snippet !== null && CourseTheoryPaths::snippetBasenameTargetsModule($snippet, $m);
        $tq = $legacyAlt ? AdminCourseContentInspector::theoryQuizQuestions($m) : [];
        $ex = $legacyAlt ? AdminCourseContentInspector::moduleExamQuestions($m) : [];
        $survey = [];
        $practiceMd = $legacyAlt ? AdminCourseContentInspector::practiceMarkdown($m) : '';
        $theoryChars = $legacyAlt ? AdminCourseContentInspector::theoryCharacterCount($m) : 0;
        $examTimeMin = $legacyAlt
            ? $this->legacyModuleExamTimeLimitMinutes($m)
            : CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;
        $hasPracticeSection = $legacyAlt || $practiceMd !== '';
        $course = null;

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
                $hasPracticeSection = (bool) $db['has_practice_section'];
                $survey = AdminCourseContentInspector::questionsForModuleSections($cm, CourseSection::TYPE_SURVEY);
            }
        }

        $dockerImage = ($cm && $lab) ? $lab->imageForCourseModule($cm, $m) : ($legacyAlt ? AdminCourseContentInspector::practiceLabDockerImageForModule($m) : null);

        if ($contentColumns === []) {
            $contentColumns = AdminCourseContentInspector::contentColumnsForCourse(
                $cm instanceof CourseModule ? (int) $cm->course_id : 0,
                $legacyAlt
            );
        }

        $cells = $this->buildTheoryContentCells(
            $m,
            $contentColumns,
            $legacyAlt,
            $course ?? null,
            $cm,
            $theoryChars,
            $tq,
            $practiceMd,
            $hasPracticeSection,
            $ex,
            $examTimeMin,
            $survey,
            $dockerImage
        );

        return [
            'module' => $m,
            'module_sequence' => $moduleSequence,
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
            'cells' => $cells,
        ];
    }

    /**
     * @param  list<array{key: string, label: string, type?: string, section_id?: int, course_module_id?: int}>  $contentColumns
     * @param  list<array<string, mixed>>  $tq
     * @param  list<array<string, mixed>>  $ex
     * @param  list<array<string, mixed>>  $survey
     * @return array<string, array<string, mixed>>
     */
    private function buildTheoryContentCells(
        int $m,
        array $contentColumns,
        bool $legacyAlt,
        ?Course $course,
        ?CourseModule $cm,
        int $theoryChars,
        array $tq,
        string $practiceMd,
        bool $hasPracticeSection,
        array $ex,
        int $examTimeMin,
        array $survey,
        ?string $dockerImage
    ): array {
        $rp = AdminNavigation::adminCourseRouteParams();
        $cells = [];

        foreach ($contentColumns as $col) {
            $key = (string) $col['key'];
            if (! $legacyAlt && str_starts_with($key, 'slot') && $course instanceof Course && $cm instanceof CourseModule) {
                $cells[$key] = AdminCourseContentInspector::cellForModuleColumn(
                    $course,
                    $cm,
                    $m,
                    $col,
                    $dockerImage
                );

                continue;
            }

            $cells[$key] = match ($key) {
                'text' => [
                    'has_section' => $legacyAlt || ($cm && AdminCourseContentInspector::moduleHasSectionType((int) $cm->id, CourseSection::TYPE_TEXT)),
                    'filled' => $theoryChars > 0,
                    'meta' => $theoryChars > 0
                        ? number_format($theoryChars, 0, ',', ' ').' симв.'
                        : '0 симв.',
                    'preview_url' => route('admin.theory.preview-theory', array_merge($rp, ['module' => $m])),
                    'preview_title' => 'Просмотр теории',
                    'stats_url' => null,
                    'stats_label' => null,
                    'col_type' => 'text',
                    'docker_image' => null,
                ],
                'quiz' => [
                    'has_section' => $legacyAlt || ($cm && AdminCourseContentInspector::moduleHasSectionType((int) $cm->id, CourseSection::TYPE_QUIZ)),
                    'filled' => count($tq) > 0,
                    'meta' => $this->quizCellMeta(count($tq), AdminCourseContentInspector::countMatchDrag($tq)),
                    'preview_url' => route('admin.theory.preview-theory-quiz', array_merge($rp, ['module' => $m])),
                    'preview_title' => 'Просмотр теста по теории',
                    'stats_url' => null,
                    'stats_label' => null,
                    'col_type' => 'quiz',
                    'docker_image' => null,
                ],
                'practice' => [
                    'has_section' => $legacyAlt ? true : $hasPracticeSection,
                    'filled' => $practiceMd !== '',
                    'meta' => AdminCourseContentInspector::practiceSummaryLine($practiceMd),
                    'preview_url' => route('admin.theory.preview-practice', array_merge($rp, ['module' => $m])),
                    'preview_title' => 'Просмотр практики',
                    'stats_url' => null,
                    'stats_label' => null,
                    'col_type' => 'practice',
                    'docker_image' => $dockerImage,
                ],
                'exam' => [
                    'has_section' => $legacyAlt || ($cm && AdminCourseContentInspector::moduleHasSectionType((int) $cm->id, CourseSection::TYPE_EXAM)),
                    'filled' => count($ex) > 0,
                    'meta' => $this->examCellMeta(count($ex), $examTimeMin, AdminCourseContentInspector::countMatchDrag($ex)),
                    'preview_url' => route('admin.theory.preview-module-exam', array_merge($rp, ['module' => $m])),
                    'preview_title' => 'Просмотр итогового теста',
                    'stats_url' => null,
                    'stats_label' => null,
                    'col_type' => 'exam',
                    'docker_image' => null,
                ],
                'survey' => [
                    'has_section' => $legacyAlt || ($cm && AdminCourseContentInspector::moduleHasSectionType((int) $cm->id, CourseSection::TYPE_SURVEY)),
                    'filled' => count($survey) > 0,
                    'meta' => $this->quizCellMeta(count($survey), AdminCourseContentInspector::countMatchDrag($survey)),
                    'preview_url' => null,
                    'preview_title' => null,
                    'stats_url' => null,
                    'stats_label' => null,
                    'col_type' => 'survey',
                    'docker_image' => null,
                ],
                'docker' => [
                    'has_section' => $legacyAlt ? true : $hasPracticeSection,
                    'filled' => $dockerImage !== null && $dockerImage !== '',
                    'meta' => $dockerImage ?? '',
                    'preview_url' => null,
                    'preview_title' => null,
                    'stats_url' => null,
                    'stats_label' => null,
                    'col_type' => 'docker',
                    'docker_image' => $dockerImage,
                ],
                default => [
                    'has_section' => false,
                    'filled' => false,
                    'meta' => '',
                    'preview_url' => null,
                    'preview_title' => null,
                    'stats_url' => null,
                    'stats_label' => null,
                    'col_type' => '',
                    'docker_image' => null,
                ],
            };
        }

        return $cells;
    }

    /**
     * Предпросмотр содержимого конкретного раздела модуля (теория, тест, экзамен, опрос, практика).
     */
    public function previewSection(Request $request, Course $adminCourse, int $module, CourseSection $section): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $cm = $this->courseModuleForContentIndex($adminCourse, $module);
        abort_unless(
            $cm !== null
            && (int) $section->course_module_id === (int) $cm->id
            && (int) $section->course_id === (int) $adminCourse->id
            && $section->is_enabled,
            404
        );

        $meta = $this->previewModuleMeta($adminCourse, $module);
        $meta['section_title'] = (string) $section->title;
        if (in_array((string) $section->type, [CourseSection::TYPE_TEXT, CourseSection::TYPE_PRACTICE], true)
            && \Illuminate\Support\Facades\Schema::hasTable('course_section_contents')) {
            $md = app(\App\Services\CourseContentService::class)->markdownForSection($section);
            if ((string) $section->type === CourseSection::TYPE_TEXT) {
                $meta['theory'] = $md;
            } else {
                $meta['practice'] = $md;
            }
        }

        return match ((string) $section->type) {
            CourseSection::TYPE_TEXT => $this->previewSectionTheory($request, $module, $meta),
            CourseSection::TYPE_PRACTICE => $this->previewSectionPractice($request, $module, $meta),
            CourseSection::TYPE_QUIZ => $this->previewSectionQuizBank($request, $module, $section, $meta, 'admin.content-theory-quiz', 'Тест'),
            CourseSection::TYPE_EXAM => $this->previewSectionExam($request, $adminCourse, $module, $section, $meta),
            CourseSection::TYPE_SURVEY => $this->previewSectionQuizBank($request, $module, $section, $meta, 'admin.content-survey', 'Опрос'),
            default => redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Неподдерживаемый тип раздела.'),
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function previewSectionTheory(Request $request, int $module, array $meta): View|RedirectResponse
    {
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

    /**
     * @param  array<string, mixed>  $meta
     */
    private function previewSectionPractice(Request $request, int $module, array $meta): View|RedirectResponse
    {
        $markdown = PracticeHintMarkdown::stripBlockquoteHintsUnlessVisible(
            (string) ($meta['practice'] ?? ''),
            true
        );
        if ($markdown === '') {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Модуль '.$module.': нет текста практики.');
        }

        return view('admin.content-practice', [
            'module' => $module,
            'meta' => $meta,
            'practiceMarkdown' => $markdown,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function previewSectionQuizBank(
        Request $request,
        int $module,
        CourseSection $section,
        array $meta,
        string $view,
        string $label
    ): View|RedirectResponse {
        $questions = AdminCourseContentInspector::questionsForSection($section);
        if ($questions === []) {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Раздел «'.$section->title.'»: нет вопросов.');
        }

        return view($view, [
            'module' => $module,
            'meta' => $meta,
            'section' => $section,
            'questions' => $questions,
            'sectionLabel' => $label,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function previewSectionExam(Request $request, Course $course, int $module, CourseSection $section, array $meta): View|RedirectResponse
    {
        $questions = AdminCourseContentInspector::questionsForSection($section);
        if ($questions === []) {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', 'Раздел «'.$section->title.'»: нет вопросов итогового теста.');
        }

        $cm = $this->courseModuleForContentIndex($course, $module);
        $timeLimit = $cm
            ? app(CourseSectionService::class)->examTimeLimitMinutes((int) $cm->id, $module, false)
            : CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;

        return view('admin.content-module-exam', [
            'module' => $module,
            'meta' => $meta,
            'section' => $section,
            'questions' => $questions,
            'timeLimitMinutes' => $timeLimit,
        ]);
    }

    private function quizCellMeta(int $count, int $matchCount): string
    {
        if ($count < 1) {
            return '0 вопр.';
        }
        $meta = $count.' вопр.';
        if ($matchCount > 0) {
            $meta .= ' · '.$matchCount.' сопост.';
        }

        return $meta;
    }

    private function examCellMeta(int $count, int $timeMin, int $matchCount): string
    {
        if ($count < 1) {
            return '0 вопр.';
        }
        $meta = $count.' вопр. · '.$timeMin.' мин';
        if ($matchCount > 0) {
            $meta .= ' · '.$matchCount.' сопост.';
        }

        return $meta;
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

    public function downloadModuleDoc(Request $request, Course $adminCourse, int $module): Response
    {
        abort_if($module < 1 || $module > 99, 404);
        $cm = $this->courseModuleForContentIndex($adminCourse, $module);
        abort_unless($cm !== null, 404);
        app(PortalStaffAccess::class)->assertCanEditModuleContent((int) $cm->id);

        $svc = app(TheoryWordExportService::class);
        $md = $svc->markdownForModule($adminCourse, $cm);
        $html = $svc->docHtml((string) $cm->title, $md, (string) $adminCourse->title);
        $filename = 'theory-'.$adminCourse->slug.'-m'.$module.'.doc';

        return response($html, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function downloadSectionDoc(Course $adminCourse, CourseModule $courseModule, CourseSection $section): Response
    {
        abort_unless((int) $courseModule->course_id === (int) $adminCourse->id, 404);
        abort_unless((int) $section->course_module_id === (int) $courseModule->id, 404);
        abort_unless($section->type === CourseSection::TYPE_TEXT, 404);
        app(PortalStaffAccess::class)->assertCanViewSectionInAdmin((int) $section->id);

        $svc = app(TheoryWordExportService::class);
        $md = $svc->markdownForSection($adminCourse, $section);
        $html = $svc->docHtml((string) $section->title, $md, (string) $adminCourse->title.' · '.$courseModule->title);
        $filename = 'theory-'.$adminCourse->slug.'-s'.$section->id.'.doc';

        return response($html, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function downloadWordZip(Request $request, Course $adminCourse): Response|RedirectResponse
    {
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $adminCourse->id);
        $result = app(TheoryWordExportService::class)->zipForCourse($adminCourse);
        if (! ($result['ok'] ?? false)) {
            return redirect()
                ->route('admin.theory.index', $this->theoryRouteQuery($request))
                ->with('err', (string) ($result['error'] ?? 'Не удалось сформировать ZIP.'));
        }

        $name = 'course-theory-word-'.date('Y-m-d').'.zip';

        return response((string) ($result['binary'] ?? ''), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    private function assertCanEditTheoryModule(int $contentModuleIndex): void
    {
        $courseId = (int) session('admin_course_id', 0);
        if ($courseId < 1) {
            abort(403);
        }
        $cm = CourseModule::query()
            ->where('course_id', $courseId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->first(fn (CourseModule $m) => $m->effectiveContentIndex() === $contentModuleIndex);
        if ($cm !== null) {
            app(PortalStaffAccess::class)->assertCanEditModuleContent((int) $cm->id);
        }
    }
}
