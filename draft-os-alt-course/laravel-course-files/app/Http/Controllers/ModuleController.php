<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\Learner;
use App\Models\ModuleProgress;
use App\Models\PracticeSession;
use App\Services\CourseModuleService;
use App\Services\CourseScoringService;
use App\Services\CourseContentService;
use App\Services\CourseSectionService;
use App\Services\LearnerContentVisibilityService;
use App\Services\ModuleAccessGate;
use App\Services\PracticeLabService;
use App\Support\CourseModuleMeta;
use App\Support\CourseStaffPreview;
use App\Support\LearnerPreviewContext;
use App\Support\LearnerRoute;
use App\Support\LearnerScoreDisplay;
use App\Support\SectionProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring,
        private ModuleAccessGate $accessGate,
        private CourseSectionService $sectionService,
        private CourseModuleService $courseModules,
        private CourseContentService $courseContent,
        private LearnerContentVisibilityService $visibility,
    ) {}

    private function theoryQuizDeadlineKey(int $learnerId, int $courseModuleId, int $sectionId): string
    {
        return 'theory_quiz_deadline_l'.$learnerId.'_m'.$courseModuleId.'_s'.$sectionId;
    }

    private function theoryQuizWallStartKey(int $learnerId, int $courseModuleId, int $sectionId): string
    {
        return 'theory_quiz_wall_start_l'.$learnerId.'_m'.$courseModuleId.'_s'.$sectionId;
    }

    private function theoryQuizResultSessionKey(int $sectionId): string
    {
        return 'theory_quiz_result_s'.$sectionId;
    }

    private function moduleExamResultSessionKey(int $sectionId): string
    {
        return 'module_exam_result_s'.$sectionId;
    }

    private function staffPreviewWalkthrough(): bool
    {
        return CourseStaffPreview::isActive();
    }

    /** В предпросмотре курса тесты и экзамены — только просмотр вопросов, без записи попыток. */
    private function redirectIfStaffPreviewAssessmentWrite(Request $request, string $showRoute): ?RedirectResponse
    {
        if (! $this->staffPreviewWalkthrough()) {
            return null;
        }
        $ctx = $this->assessmentSectionContext($request);
        if ($ctx instanceof RedirectResponse) {
            return $ctx;
        }

        return redirect()->route($showRoute, $this->sectionRouteParams($ctx));
    }

    /**
     * @param  array{courseId: int, moduleSequence: int, sectionSequence: int}  $ctx
     * @return array{course: int, module: int, section: int}
     */
    private function sectionRouteParams(array $ctx): array
    {
        return LearnerRoute::section($ctx['courseId'], $ctx['moduleSequence'], $ctx['sectionSequence']);
    }

    public function theoryQuizLegacyRedirect(Request $request): RedirectResponse
    {
        return $this->legacyAssessmentRedirect($request, CourseSection::TYPE_QUIZ, 'course.module.section.theory-quiz');
    }

    public function theoryLegacyRedirect(Request $request): RedirectResponse
    {
        return $this->legacyAssessmentRedirect($request, CourseSection::TYPE_TEXT, 'course.module.section.theory');
    }

    public function theoryQuizLegacyResultRedirect(Request $request): RedirectResponse
    {
        return $this->legacyAssessmentRedirect($request, CourseSection::TYPE_QUIZ, 'course.module.section.theory-quiz.result');
    }

    public function examLegacyRedirect(Request $request): RedirectResponse
    {
        return $this->legacyAssessmentRedirect($request, CourseSection::TYPE_EXAM, 'course.module.section.exam');
    }

    public function examLegacyResultRedirect(Request $request): RedirectResponse
    {
        return $this->legacyAssessmentRedirect($request, CourseSection::TYPE_EXAM, 'course.module.section.exam.result');
    }

    private function legacyAssessmentRedirect(Request $request, string $type, string $targetRoute): RedirectResponse
    {
        $ctx = $this->routeContext($request);
        $sec = $this->resolveSoleSectionOfType($ctx['mid'], $type);
        if ($sec === null) {
            if ($this->sectionService->countEnabledSectionsOfType($ctx['mid'], $type) === 0) {
                abort(404);
            }

            return redirect()->to($this->hubUrl($ctx))
                ->with('err', 'Выберите конкретный раздел в списке шагов модуля.');
        }

        return redirect()->route(
            $targetRoute,
            LearnerRoute::section(
                $ctx['courseId'],
                $ctx['moduleSequence'],
                $this->sectionService->sequenceForSection($sec),
            ),
        );
    }

    private function resolveSoleSectionOfType(int $courseModuleId, string $type): ?CourseSection
    {
        $secs = $this->sectionService->enabledSectionsForCourseModule($courseModuleId)
            ->where('type', $type);
        if ($secs->count() !== 1) {
            return null;
        }

        return $secs->first();
    }

    /**
     * @return array{
     *     cm: CourseModule,
     *     mid: int,
     *     moduleSequence: int,
     *     courseId: int,
     *     section: CourseSection,
     *     sectionSequence: int,
     *     sole: bool
     * }|RedirectResponse
     */
    private function assessmentSectionContext(Request $request, ?string $expectedType = null): array|RedirectResponse
    {
        $ctx = $this->routeContext($request);
        $sectionRoute = $request->route('section');
        if ($sectionRoute !== null) {
            $sec = $this->sectionService->findOrFailBySequenceForModuleRoute($ctx['mid'], (int) $sectionRoute);
            if ($expectedType !== null) {
                abort_unless($sec->type === $expectedType && $sec->is_enabled, 404);
            }
        } else {
            if ($expectedType === null) {
                abort(404);
            }
            $sec = $this->resolveSoleSectionOfType($ctx['mid'], $expectedType);
            if ($sec === null) {
                return redirect()->to($this->hubUrl($ctx))
                    ->with('err', 'Выберите конкретный раздел в списке шагов модуля.');
            }
        }

        return array_merge($ctx, [
            'section' => $sec,
            'sectionSequence' => $this->sectionService->sequenceForSection($sec),
            'sole' => $this->sectionService->isSoleSectionOfType($sec),
        ]);
    }

    /**
     * @return array<int, array{q:string,a:array,c:int|array<int>}>
     */
    private function questionsForSection(CourseSection $section, int $contentSourceIndex): array
    {
        $kind = $section->quizBankKind();
        if ($kind === null) {
            return [];
        }

        if ($this->courseModules->selectedCourseIsLegacyAlt()) {
            $cm = $this->courseModuleOrAbort((int) request()->route('module'));
            $course = $cm->loadMissing('course:id,slug')->course;
            if ($course instanceof Course && Schema::hasTable('course_quiz_banks')) {
                $bank = $this->courseContent->quizBankForSection($section);
                if ($bank !== null) {
                    $fromDb = $this->courseContent->questionsForBank($bank);
                    if ($fromDb !== []) {
                        return $fromDb;
                    }
                }
            }

            return config('course.module_quizzes.'.$contentSourceIndex.'.'.$kind, []);
        }

        $bank = $this->courseContent->quizBankForSection($section);
        if ($bank === null) {
            return [];
        }

        return $this->courseContent->questionsForBank($bank);
    }

    protected function moduleExamTimeLimitMinutesForSection(CourseSection $section, CourseModule $cm): ?int
    {
        $courseModuleId = (int) $cm->id;
        $idx = $cm->effectiveContentIndex();
        $legacyAlt = $this->courseModules->selectedCourseIsLegacyAlt();
        if (LearnerPreviewContext::courseId() > 0 && Schema::hasTable('course_sections')
            && $this->sectionService->useDbSectionsForModule($courseModuleId)) {
            return $this->sectionService->examTimeLimitMinutesForSection($section, $idx, $legacyAlt);
        }
        $v = $legacyAlt ? config('course.modules.'.$idx.'.module_exam_time_limit_minutes') : null;

        return (is_numeric($v) && (int) $v > 0)
            ? (int) $v
            : CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;
    }

    /**
     * @return array{cm: CourseModule, mid: int, moduleSequence: int, courseId: int}
     */
    private function routeContext(?Request $request = null): array
    {
        $request ??= request();
        $courseRoute = (int) $request->route('course', 0);
        $moduleRoute = (int) $request->route('module');
        $courseId = $courseRoute > 0 ? $courseRoute : LearnerPreviewContext::courseId();
        $cm = $this->courseModules->findOrFailForCourseRoute($courseId, $moduleRoute);
        abort_unless((int) $cm->course_id === $courseId, 404);

        return [
            'cm' => $cm,
            'mid' => (int) $cm->id,
            'moduleSequence' => $this->moduleSequenceFor($cm),
            'courseId' => $courseId,
        ];
    }

    /** @param array{courseId: int, moduleSequence: int} $ctx */
    private function hubUrl(array $ctx): string
    {
        return route('course.module.hub', LearnerRoute::hub($ctx['courseId'], $ctx['moduleSequence']));
    }

    private function courseModuleOrAbort(int $moduleRoute): CourseModule
    {
        $courseRoute = (int) request()->route('course', 0);
        $courseId = $courseRoute > 0 ? $courseRoute : LearnerPreviewContext::courseId();

        return $this->courseModules->findOrFailForCourseRoute($courseId, $moduleRoute);
    }

    private function moduleSequenceFor(CourseModule $cm): int
    {
        return $this->courseModules->sequenceForModule($cm);
    }

    protected function learner(): Learner
    {
        return Learner::findOrFail(LearnerPreviewContext::learnerId());
    }

    /**
     * @return array<int, array{q:string,a:array,c:int|array<int>}>
     */
    protected function questions(int $contentSourceIndex, string $kind): array
    {
        // Банки вопросов в legacy-конфиге есть только у ALT-курса.
        if ($this->courseModules->selectedCourseIsLegacyAlt()) {
            $cm = $this->courseModuleOrAbort((int) request()->route('module'));
            $course = $cm->loadMissing('course:id,slug')->course;
            if ($course instanceof \App\Models\Course && Schema::hasTable('course_quiz_banks')) {
                $bank = $this->courseContent->quizBankFor($course, $cm, $kind);
                if ($bank !== null) {
                    $fromDb = $this->courseContent->questionsForBank($bank);
                    if ($fromDb !== []) {
                        return $fromDb;
                    }
                }
            }

            return config('course.module_quizzes.'.$contentSourceIndex.'.'.$kind, []);
        }

        $courseId = LearnerPreviewContext::courseId();
        if ($courseId < 1) {
            return [];
        }
        $cm = $this->courseModuleOrAbort((int) request()->route('module'));
        $course = $cm->relationLoaded('course') ? $cm->course : $cm->loadMissing('course:id,slug')->course;
        if (! ($course instanceof \App\Models\Course)) {
            return [];
        }
        $bank = $this->courseContent->quizBankFor($course, $cm, $kind);
        if (! $bank) {
            return [];
        }

        return $this->courseContent->questionsForBank($bank);
    }

    /**
     * Лимит времени на итоговый тест модуля (мин.): из course.modules[N].module_exam_time_limit_minutes или константа.
     * null = без ограничения.
     */
    protected function moduleExamTimeLimitMinutes(CourseModule $cm): ?int
    {
        $courseId = LearnerPreviewContext::courseId();
        $courseModuleId = (int) $cm->id;
        $idx = $cm->effectiveContentIndex();
        $legacyAlt = $this->courseModules->selectedCourseIsLegacyAlt();
        if ($courseId > 0 && Schema::hasTable('course_sections')
            && $this->sectionService->useDbSectionsForModule($courseModuleId)) {
            return $this->sectionService->examTimeLimitMinutes($courseModuleId, $idx, $legacyAlt);
        }
        $v = $legacyAlt ? config('course.modules.'.$idx.'.module_exam_time_limit_minutes') : null;

        return (is_numeric($v) && (int) $v > 0)
            ? (int) $v
            : CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;
    }

    protected function learnerCourseId(): int
    {
        return LearnerPreviewContext::courseId();
    }

    /**
     * Разбор для обучающегося: только ошибки/пропуски; окно по времени или без ограничения.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     result: array<string, mixed>,
     *     showBreakdown: bool,
     *     breakdownExpired: bool,
     *     wrongItems: list<array<string, mixed>>,
     *     breakdownUntilTs: ?int
     * }
     */
    protected function prepareLearnerQuizBreakdownView(array $data): array
    {
        $unlimited = ! empty($data['breakdown_unlimited']);
        $until = array_key_exists('breakdown_visible_until', $data) && $data['breakdown_visible_until'] !== null
            ? (int) $data['breakdown_visible_until']
            : 0;
        // Старые попытки без флага: null until не встречался; until=0 — разбор скрыт.
        $withinWindow = $unlimited || ($until > 0 && $until > now()->getTimestamp());
        $breakdownExpired = ! $unlimited && $until > 0 && ! $withinWindow;

        $result = $data;
        if (! $withinWindow) {
            unset($result['items'], $result['breakdown_visible_until'], $result['breakdown_unlimited']);
        }

        $wrongItems = [];
        if ($withinWindow && ! empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $it) {
                if (! is_array($it)) {
                    continue;
                }
                if (empty($it['correct']) || ! empty($it['skipped'])) {
                    $wrongItems[] = $it;
                }
            }
        }

        return [
            'result' => $result,
            'showBreakdown' => $withinWindow && $wrongItems !== [],
            'breakdownExpired' => $breakdownExpired,
            'wrongItems' => $wrongItems,
            'breakdownUntilTs' => ($withinWindow && ! $unlimited && $until > 0) ? $until : null,
        ];
    }

    protected function examQuestionIsMulti(array $q): bool
    {
        return isset($q['c']) && is_array($q['c']);
    }

    /**
     * Итоговый тест: одиночный выбор (c — int) или несколько верных (c — список индексов).
     * Если у вопросов задано поле points (>0), сырой процент считается по сумме баллов, иначе — по числу верных вопросов.
     *
     * @param  array<int, array{q:string,a:array,c:int|array<int>,points?:int}>  $questions
     * @return array{correct_count:int, wrong_count:int, total:int, raw_percent:int, items: array<int, array<string, mixed>>, max_points:?int, earned_points:?int}
     */
    protected function scoreModuleExamBreakdown(array $questions, Request $request): array
    {
        $items = [];
        $correctCount = 0;
        $weighted = false;
        foreach ($questions as $q) {
            if (isset($q['points']) && (int) $q['points'] > 0) {
                $weighted = true;
                break;
            }
        }
        $maxPoints = 0;
        $earnedPoints = 0;
        foreach (array_keys($questions) as $i) {
            $q = $questions[$i];
            $pts = 0;
            if ($weighted) {
                $pts = max(0, (int) ($q['points'] ?? 0));
                if ($pts <= 0) {
                    $pts = 1;
                }
                $maxPoints += $pts;
            }
            if (! empty($q['match_drag'])) {
                $left = is_array($q['left'] ?? null) ? $q['left'] : [];
                $right = is_array($q['right'] ?? null) ? $q['right'] : [];
                $n = count($left);
                $malformed = $n < 1 || count($right) !== $n;
                $rawOrder = (string) $request->input('e'.$i.'_order', '');
                $parts = array_values(array_map('intval', array_filter(explode(',', $rawOrder), static function ($s) {
                    return $s !== '';
                })));
                $skipped = $malformed || $rawOrder === '' || count($parts) !== $n;
                $expectedOrder = range(0, max(0, $n - 1));
                $ok = ! $skipped && ! $malformed && $parts === $expectedOrder;
                $row = [
                    'n' => $i + 1,
                    'question' => $q['q'],
                    'match_drag' => true,
                    'left' => $left,
                    'right' => $right,
                    'chosen_order' => $skipped ? [] : $parts,
                    'expected_order' => $expectedOrder,
                    'multi' => false,
                    'correct' => $ok,
                    'skipped' => $skipped,
                ];
                if ($weighted) {
                    $row['points'] = $pts;
                    $row['earned_points'] = $ok ? $pts : 0;
                }
                $items[] = $row;
                if ($ok) {
                    $correctCount++;
                    if ($weighted) {
                        $earnedPoints += $pts;
                    }
                }

                continue;
            }
            $expectedRaw = $q['c'] ?? null;
            $isMulti = is_array($expectedRaw);
            if ($isMulti) {
                $expSet = array_values(array_unique(array_map('intval', $expectedRaw)));
                sort($expSet);
                $chosen = $request->input('e'.$i, []);
                if (! is_array($chosen)) {
                    $chosen = [];
                }
                $chosenSet = array_values(array_unique(array_map('intval', $chosen)));
                sort($chosenSet);
                $ok = $expSet === $chosenSet;
                $skipped = $chosenSet === [];
                $row = [
                    'n' => $i + 1,
                    'question' => $q['q'],
                    'options' => $q['a'],
                    'chosen' => $chosenSet,
                    'expected' => $expSet,
                    'multi' => true,
                    'correct' => $ok,
                    'skipped' => $skipped,
                ];
                if ($weighted) {
                    $row['points'] = $pts;
                    $row['earned_points'] = $ok ? $pts : 0;
                }
                $items[] = $row;
            } else {
                $exp = (int) $expectedRaw;
                $has = $request->has('e'.$i);
                $chosenOne = $has ? (int) $request->input('e'.$i) : null;
                $ok = $has && $chosenOne === $exp;
                $row = [
                    'n' => $i + 1,
                    'question' => $q['q'],
                    'options' => $q['a'],
                    'chosen' => $chosenOne,
                    'expected' => $exp,
                    'multi' => false,
                    'correct' => $ok,
                    'skipped' => ! $has,
                ];
                if ($weighted) {
                    $row['points'] = $pts;
                    $row['earned_points'] = $ok ? $pts : 0;
                }
                $items[] = $row;
            }
            if ($ok) {
                $correctCount++;
                if ($weighted) {
                    $earnedPoints += $pts;
                }
            }
        }
        $total = count($questions);
        if ($weighted && $maxPoints > 0) {
            $rawPercent = (int) round(100 * $earnedPoints / $maxPoints);
        } else {
            $rawPercent = $total > 0 ? (int) round(100 * $correctCount / $total) : 0;
        }

        return [
            'correct_count' => $correctCount,
            'wrong_count' => $total - $correctCount,
            'total' => $total,
            'raw_percent' => $rawPercent,
            'items' => $items,
            'max_points' => $weighted ? $maxPoints : null,
            'earned_points' => $weighted ? $earnedPoints : null,
        ];
    }

    /**
     * @param  array<int, array{q:string,a:array,c:int}>  $questions
     */
    protected function scorePercent(array $questions, Request $request, string $prefix): int
    {
        if (count($questions) === 0) {
            return 0;
        }
        $correct = 0;
        foreach (array_keys($questions) as $i) {
            if (! $request->has($prefix.$i)) {
                continue;
            }
            $v = (int) $request->input($prefix.$i);
            if (isset($questions[$i]['c']) && ! is_array($questions[$i]['c']) && $v === (int) $questions[$i]['c']) {
                $correct++;
            }
        }

        return (int) round(100 * $correct / count($questions));
    }

    /**
     * Детальный разбор теста по теории (для страницы результата).
     * Одиночный выбор: c — int; несколько верных: c — int[] (чекбоксы в форме).
     *
     * @param  array<int, array{q:string,a:array,c:int|array<int>}>  $questions
     * @return array{correct_count:int, wrong_count:int, total:int, raw_percent:int, items: array<int, array<string, mixed>>}
     */
    protected function scoreTheoryQuizBreakdown(array $questions, Request $request, string $prefix): array
    {
        $items = [];
        $correctCount = 0;
        foreach (array_keys($questions) as $i) {
            $q = $questions[$i];
            $key = $prefix.$i;
            $expectedRaw = $q['c'] ?? null;

            if (is_array($expectedRaw)) {
                $expSet = array_values(array_unique(array_map('intval', $expectedRaw)));
                sort($expSet);

                $rawIn = $request->input($key);
                $chosenSet = [];
                if (is_array($rawIn)) {
                    $chosenSet = array_values(array_unique(array_map('intval', $rawIn)));
                } elseif ($rawIn !== null && $rawIn !== '') {
                    $chosenSet = [(int) $rawIn];
                }
                sort($chosenSet);

                $has = $request->has($key);
                $skipped = ! $has;
                $ok = $expSet === $chosenSet;
                if ($ok) {
                    $correctCount++;
                }
                $items[] = [
                    'n' => $i + 1,
                    'question' => $q['q'],
                    'options' => $q['a'],
                    'chosen' => $chosenSet,
                    'expected' => $expSet,
                    'multi' => true,
                    'correct' => $ok,
                    'skipped' => $skipped,
                ];
            } else {
                $expected = (int) $expectedRaw;
                $has = $request->has($key);
                $chosen = $has ? (int) $request->input($key) : null;
                $ok = $has && $chosen === $expected;
                if ($ok) {
                    $correctCount++;
                }
                $items[] = [
                    'n' => $i + 1,
                    'question' => $q['q'],
                    'options' => $q['a'],
                    'chosen' => $chosen,
                    'expected' => $expected,
                    'multi' => false,
                    'correct' => $ok,
                    'skipped' => ! $has,
                ];
            }
        }
        $total = count($questions);
        $rawPercent = $total > 0 ? (int) round(100 * $correctCount / $total) : 0;

        return [
            'correct_count' => $correctCount,
            'wrong_count' => $total - $correctCount,
            'total' => $total,
            'raw_percent' => $rawPercent,
            'items' => $items,
        ];
    }

    public function hub(Request $request): View|RedirectResponse
    {
        $ctx = $this->routeContext($request);
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $moduleSequence = $ctx['moduleSequence'];
        $courseId = $ctx['courseId'];
        $contentIdx = $cm->effectiveContentIndex();
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        $meta = $this->courseModules->displayMeta($cm);
        $p = $learner->progressFor($mid);
        if (Schema::hasColumn('module_progress', 'module_access_started_at') && $p->module_access_started_at === null) {
            $p->module_access_started_at = now();
            $p->save();
        }
        // Итог % на хабе = из последнего результата; колонка могла отставать у старых записей — выравниваем.
        $p->syncModuleExamBestScoreFromLastResult();

        if ($courseId > 0 && $this->sectionService->reconcilePassFlagsFromResults($p, $mid)) {
            $p->save();
        }

        $legacyAltForBriefing = $courseId > 0 ? $this->courseModules->selectedCourseIsLegacyAlt() : true;
        $sequentialSections = $this->sectionService->moduleEnforcesSectionOrder($mid, $contentIdx, $legacyAltForBriefing);
        $sequentialCourse = $courseId < 1 || ! $this->accessGate->courseUnlocksAllModules($courseId);
        $showBriefing = Schema::hasColumn('module_progress', 'hub_briefing_acknowledged_at')
            && $p->hub_briefing_acknowledged_at === null
            && ! CourseStaffPreview::isActive($request)
            && $sequentialCourse
            && $sequentialSections;

        $hubPresent = null;
        if ($courseId > 0 && Schema::hasTable('course_sections')
            && $this->sectionService->useDbSectionsForModule($mid)) {
            $legacyAlt = $this->courseModules->selectedCourseIsLegacyAlt();
            $freeSectionOrder = ! $sequentialCourse;
            $hubPresent = [];
            foreach ($this->visibility->visibleSectionsForLearner($mid, (int) $learner->id) as $sec) {
                $bk = $sec->backendStepKey();
                $waived = $sec->legacyTypeKey() === 'practice' && $this->sectionService->isPracticeWaived($mid, $contentIdx, $legacyAlt);
                $blocked = ($waived || $freeSectionOrder)
                    ? null
                    : $this->sectionService->firstBlockedPrerequisite($p, $mid, $contentIdx, $bk, $legacyAlt);
                $previewOpen = \App\Support\CourseStaffPreview::isActive($request);
                $hubPresent[] = [
                    'section' => $sec,
                    'waived' => $waived,
                    'accessible' => $previewOpen ? ! $waived : ($blocked === null && ! $waived),
                ];
            }
        }

        $difficultyEnabled = true;
        if ($courseId > 0 && Schema::hasTable('courses') && Schema::hasColumn('courses', 'difficulty_flags_enabled')) {
            $difficultyEnabled = (bool) \App\Models\Course::query()
                ->whereKey($courseId)
                ->value('difficulty_flags_enabled');
        }
        $difficultyOptions = [];
        $labels = [
            'theory' => $tTheory ?? (config('course.step_titles.theory') ?? 'Теория'),
            'theory_quiz' => $tTq ?? (config('course.step_titles.theory_quiz') ?? 'Тест по теории'),
            'practice' => $tPr ?? (config('course.step_titles.practice') ?? 'Практика'),
            'module_exam' => $tEx ?? (config('course.step_titles.module_exam') ?? 'Итоговый тест'),
        ];
        if (is_array($hubPresent)) {
            foreach ($hubPresent as $row) {
                $sec = $row['section'] ?? null;
                if (! ($sec instanceof \App\Models\CourseSection)) {
                    continue;
                }
                $bk = (string) $sec->backendStepKey();
                if ($sec->legacyTypeKey() === 'practice' && ! empty($row['waived'])) {
                    continue;
                }
                $difficultyOptions[] = ['key' => $bk, 'title' => (string) $sec->title];
            }
        } else {
            $difficultyOptions[] = ['key' => 'theory', 'title' => $labels['theory']];
            $difficultyOptions[] = ['key' => 'theory_quiz', 'title' => $labels['theory_quiz']];
            if (! \App\Support\CourseModuleMeta::shouldSkipPractice($contentIdx)) {
                $difficultyOptions[] = ['key' => 'practice', 'title' => $labels['practice']];
            }
            $difficultyOptions[] = ['key' => 'module_exam', 'title' => $labels['module_exam']];
        }

        $legacyAlt = $courseId > 0 ? $this->courseModules->selectedCourseIsLegacyAlt() : true;
        $scoreWeightLegend = ($courseId > 0 && $this->sectionService->useDbSectionsForModule($mid))
            ? $this->sectionService->moduleScoreWeightLegend($mid, $contentIdx, $legacyAlt)
            : null;
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        $scoreDisplay = LearnerScoreDisplay::flags($course, $cm);
        $showModuleScoring = $scoreDisplay['showScorePoints'];
        $showModuleProgress = LearnerScoreDisplay::showModuleProgress($course);

        return view('modules.hub', [
            'courseId' => $courseId,
            'module' => $moduleSequence,
            'moduleDbId' => $mid,
            'moduleSequence' => $moduleSequence,
            'contentIdx' => $contentIdx,
            'meta' => $meta,
            'progress' => $p,
            'percent' => $this->scoring->moduleProgressPercent($p),
            'modulePoints' => $this->scoring->modulePointsForProgress($p),
            'scoreWeightLegend' => $scoreWeightLegend,
            'passThreshold' => $courseId > 0
                ? $this->sectionService->passPercentForQuiz($mid)
                : CourseScoringService::PASS_THRESHOLD,
            'passThresholdExam' => $courseId > 0
                ? $this->sectionService->passPercentForExam($mid)
                : CourseScoringService::PASS_THRESHOLD,
            'examMaxAttemptsDisplay' => $courseId > 0
                ? $this->sectionService->examMaxAttempts($mid)
                : CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS,
            'hubPresent' => $hubPresent,
            'showHubBriefing' => $showBriefing,
            'sectionService' => $this->sectionService,
            'difficultyEnabled' => $difficultyEnabled,
            'difficultyOptions' => $difficultyOptions,
            'showModuleScoring' => $showModuleScoring,
            'showModuleProgress' => $showModuleProgress,
            'showScorePercents' => $scoreDisplay['showScorePercents'],
            'showScorePoints' => $scoreDisplay['showScorePoints'],
        ]);
    }

    public function ackHubBriefing(Request $request): RedirectResponse
    {
        $ctx = $this->routeContext($request);
        $mid = $ctx['mid'];
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        $p = $learner->progressFor($mid);
        if (Schema::hasColumn('module_progress', 'hub_briefing_acknowledged_at')) {
            $p->hub_briefing_acknowledged_at = now();
            $p->save();
        }

        return redirect()->to($this->hubUrl($ctx));
    }

    public function theory(Request $request): View|RedirectResponse
    {
        $ctx = $this->assessmentSectionContext($request, CourseSection::TYPE_TEXT);
        if ($ctx instanceof RedirectResponse) {
            return $ctx;
        }
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $moduleSequence = $ctx['moduleSequence'];
        $courseId = $ctx['courseId'];
        $sec = $ctx['section'];
        $sole = $ctx['sole'];
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfSectionHidden($learner, (int) $sec->id, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfStepBlocked($learner, $mid, $sec->backendStepKey())) {
            return $r;
        }
        $meta = $this->courseModules->displayMeta($cm);
        $meta['theory'] = $this->courseContent->markdownForSection($sec);
        if (! empty($sec->title)) {
            $meta['section_title'] = (string) $sec->title;
        }
        $p = $learner->progressFor($mid);
        if (! SectionProgress::isTextRead($p, $sec, $sole)) {
            $sk = 'theory_time_start_'.$mid.'_s'.$sec->id;
            if (! session()->has($sk)) {
                session([$sk => now()->getTimestamp()]);
            }
        }

        return view('modules.theory', [
            'courseId' => $courseId,
            'module' => $moduleSequence,
            'moduleSequence' => $moduleSequence,
            'sectionSequence' => $ctx['sectionSequence'],
            'section' => $sec,
            'meta' => $meta,
            'progress' => $p,
        ]);
    }

    public function markTheoryRead(Request $request): RedirectResponse
    {
        $ctx = $this->assessmentSectionContext($request, CourseSection::TYPE_TEXT);
        if ($ctx instanceof RedirectResponse) {
            return $ctx;
        }
        $mid = $ctx['mid'];
        $sec = $ctx['section'];
        $sole = $ctx['sole'];
        if ($r = $this->accessGate->redirectIfModuleLocked($this->learner(), $mid)) {
            return $r;
        }
        $p = $this->learner()->progressFor($mid);
        $sk = 'theory_time_start_'.$mid.'_s'.$sec->id;
        $ts = session()->pull($sk);
        if ($ts === null) {
            $ts = session()->pull('theory_time_start_'.$mid);
        }
        if ($ts !== null && is_numeric($ts)) {
            $elapsed = max(0, min(86400 * 7, now()->getTimestamp() - (int) $ts));
            if ($elapsed > 0) {
                $p->seconds_theory = (int) ($p->seconds_theory ?? 0) + $elapsed;
            }
        }
        SectionProgress::markTextRead($p, $sec, $sole);
        $p->save();

        return redirect()->to($this->hubUrl($ctx))->with('ok', 'Теория отмечена как просмотренная.');
    }

    public function theoryQuizShow(Request $request): View|RedirectResponse
    {
        $ctx = $this->assessmentSectionContext($request, CourseSection::TYPE_QUIZ);
        if ($ctx instanceof RedirectResponse) {
            return $ctx;
        }
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $moduleSequence = $ctx['moduleSequence'];
        $courseId = $ctx['courseId'];
        $sec = $ctx['section'];
        $sole = $ctx['sole'];
        $sectionId = (int) $sec->id;
        $contentIdx = $cm->effectiveContentIndex();
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfSectionHidden($learner, $sectionId, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfTheoryNotRead($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfStepBlocked($learner, $mid, $sec->backendStepKey())) {
            return $r;
        }
        $qs = $this->questionsForSection($sec, $contentIdx);
        if ($courseId > 0 && $this->sectionService->theoryQuizShuffleForSection($sec)) {
            $qs = collect($qs)->shuffle()->values()->all();
        }

        $deadlineKey = $this->theoryQuizDeadlineKey((int) $learner->id, $mid, $sectionId);
        $wallKey = $this->theoryQuizWallStartKey((int) $learner->id, $mid, $sectionId);
        $ts = session($deadlineKey);
        $wallStart = session($wallKey);
        if ($ts !== null && is_numeric($ts) && (int) $ts <= now()->getTimestamp()) {
            session()->forget([$deadlineKey, $wallKey]);
            $ts = null;
            $wallStart = null;
        }

        $tl = $courseId > 0
            ? $this->sectionService->theoryQuizTimeLimitMinutesForSection($sec)
            : CourseScoringService::THEORY_QUIZ_TIME_LIMIT_MINUTES;
        $hasTimeLimit = $tl !== null && (int) $tl > 0;

        $previewWalkthrough = $this->staffPreviewWalkthrough();
        $deadlineActive = $ts !== null && is_numeric($ts) && (int) $ts > now()->getTimestamp();
        $unlimitedActive = ! $hasTimeLimit && $wallStart !== null && is_numeric($wallStart);
        $quizActive = $previewWalkthrough || $deadlineActive || $unlimitedActive;
        $expiresAtMs = $previewWalkthrough || ! $quizActive || ! $hasTimeLimit || ! $deadlineActive
            ? null
            : ((int) $ts) * 1000;

        $passTh = $courseId > 0
            ? $this->sectionService->passPercentForSection($sec)
            : CourseScoringService::PASS_THRESHOLD;
        $quizSt = SectionProgress::quizState($this->learner()->progressFor($mid), $sec, $sole);
        $scoreDisplay = LearnerScoreDisplay::flags(
            $courseId > 0 ? Course::query()->find($courseId) : null,
            $cm
        );

        return view('modules.theory-quiz', [
            'courseId' => $courseId,
            'module' => $moduleSequence,
            'moduleSequence' => $moduleSequence,
            'section' => $sec,
            'sectionSequence' => $ctx['sectionSequence'],
            'meta' => $this->courseModules->displayMeta($cm),
            'questions' => $qs,
            'progress' => $this->learner()->progressFor($mid),
            'quizState' => $quizSt,
            'quizActive' => $quizActive,
            'expiresAtMs' => $expiresAtMs,
            'timeLimitMinutes' => $tl,
            'passThreshold' => $passTh,
            'theoryQuizRetakePenalty' => $courseId > 0
                ? $this->sectionService->theoryQuizPenaltyForAttemptForSection($sec, 2)
                : CourseScoringService::THEORY_QUIZ_RETAKE_PENALTY_POINTS,
            'previewWalkthrough' => $previewWalkthrough,
            'showScorePercents' => $scoreDisplay['showScorePercents'],
            'showScorePoints' => $scoreDisplay['showScorePoints'],
        ]);
    }

    public function theoryQuizStart(Request $request): RedirectResponse
    {
        if ($r = $this->redirectIfStaffPreviewAssessmentWrite($request, 'course.module.section.theory-quiz')) {
            return $r;
        }
        $ctx = $this->assessmentSectionContext($request, CourseSection::TYPE_QUIZ);
        if ($ctx instanceof RedirectResponse) {
            return $ctx;
        }
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $sec = $ctx['section'];
        $sole = $ctx['sole'];
        $sectionId = (int) $sec->id;
        $contentIdx = $cm->effectiveContentIndex();
        $learner = $this->learner();
        $courseId = $ctx['courseId'];
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfSectionHidden($learner, $sectionId, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfTheoryNotRead($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfStepBlocked($learner, $mid, $sec->backendStepKey())) {
            return $r;
        }
        $qs = $this->questionsForSection($sec, $contentIdx);
        if (count($qs) === 0) {
            return redirect()->to($this->hubUrl($ctx))->with('err', 'Тест по теории для этого модуля не настроен.');
        }
        $p = $this->learner()->progressFor($mid);
        $quizSt = SectionProgress::quizState($p, $sec, $sole);
        $attemptLimit = $courseId > 0 ? $this->sectionService->theoryQuizAttemptLimitForSection($sec) : null;
        if ($attemptLimit !== null && (int) ($quizSt['attempts'] ?? 0) >= $attemptLimit) {
            return redirect()->to($this->hubUrl($ctx))->with(
                'err',
                'Исчерпан лимит попыток теста по теории ('.$attemptLimit.').'
            );
        }

        $deadlineKey = $this->theoryQuizDeadlineKey((int) $learner->id, $mid, $sectionId);
        $wallKey = $this->theoryQuizWallStartKey((int) $learner->id, $mid, $sectionId);
        $tlMin = $courseId > 0
            ? $this->sectionService->theoryQuizTimeLimitMinutesForSection($sec)
            : CourseScoringService::THEORY_QUIZ_TIME_LIMIT_MINUTES;
        $sessionPayload = [
            $wallKey => now()->getTimestamp(),
        ];
        if ($tlMin !== null && (int) $tlMin > 0) {
            $sessionPayload[$deadlineKey] = now()->addMinutes((int) $tlMin)->getTimestamp();
        } else {
            session()->forget($deadlineKey);
        }
        session($sessionPayload);

        if (($quizSt['attempts'] ?? 0) >= 1 || ($quizSt['best_score'] ?? 0) > 0 || ($quizSt['passed'] ?? false)) {
            SectionProgress::saveQuizState($p, $sec, $sole, ['passed' => false]);
            $p->save();
        }

        return redirect()->route('course.module.section.theory-quiz', $this->sectionRouteParams($ctx));
    }

    public function theoryQuizResult(Request $request): View|RedirectResponse
    {
        if ($r = $this->redirectIfStaffPreviewAssessmentWrite($request, 'course.module.section.theory-quiz')) {
            return $r;
        }
        $ctx = $this->assessmentSectionContext($request, CourseSection::TYPE_QUIZ);
        if ($ctx instanceof RedirectResponse) {
            return $ctx;
        }
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $moduleSequence = $ctx['moduleSequence'];
        $courseId = $ctx['courseId'];
        $sec = $ctx['section'];
        $sole = $ctx['sole'];
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfSectionHidden($learner, (int) $sec->id, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfTheoryNotRead($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfStepBlocked($learner, $mid, $sec->backendStepKey())) {
            return $r;
        }
        $resultKey = $this->theoryQuizResultSessionKey((int) $sec->id);
        $data = session($resultKey);
        if (! is_array($data) || (int) ($data['section_id'] ?? 0) !== (int) $sec->id) {
            $p = $this->learner()->progressFor($mid);
            $quizSt = SectionProgress::quizState($p, $sec, $sole);
            $data = $quizSt['last_result'] ?? null;
        }
        if (! is_array($data) || (int) ($data['section_id'] ?? 0) !== (int) $sec->id) {
            return redirect()->to($this->hubUrl($ctx))->with('err', 'Нет сохранённого разбора. Сначала завершите тест с отправкой ответов.');
        }

        $breakdownView = $this->prepareLearnerQuizBreakdownView($data);
        $scoreDisplay = LearnerScoreDisplay::flags(
            $courseId > 0 ? Course::query()->find($courseId) : null,
            $cm
        );

        return view('modules.theory-quiz-result', [
            'courseId' => $courseId,
            'module' => $moduleSequence,
            'moduleSequence' => $moduleSequence,
            'section' => $sec,
            'sectionSequence' => $ctx['sectionSequence'],
            'meta' => $this->courseModules->displayMeta($cm),
            'result' => $breakdownView['result'],
            'showBreakdown' => $breakdownView['showBreakdown'],
            'breakdownExpired' => $breakdownView['breakdownExpired'],
            'wrongItems' => $breakdownView['wrongItems'],
            'breakdownUntilTs' => $breakdownView['breakdownUntilTs'],
            'showScorePercents' => $scoreDisplay['showScorePercents'],
            'showScorePoints' => $scoreDisplay['showScorePoints'],
        ]);
    }

    public function theoryQuizSubmit(Request $request): RedirectResponse
    {
        if ($r = $this->redirectIfStaffPreviewAssessmentWrite($request, 'course.module.section.theory-quiz')) {
            return $r;
        }
        $ctx = $this->assessmentSectionContext($request, CourseSection::TYPE_QUIZ);
        if ($ctx instanceof RedirectResponse) {
            return $ctx;
        }
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $sec = $ctx['section'];
        $sole = $ctx['sole'];
        $sectionId = (int) $sec->id;
        $contentIdx = $cm->effectiveContentIndex();
        $learner = $this->learner();
        $courseId = $ctx['courseId'];
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfSectionHidden($learner, $sectionId, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfTheoryNotRead($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfStepBlocked($learner, $mid, $sec->backendStepKey())) {
            return $r;
        }
        $deadlineKey = $this->theoryQuizDeadlineKey((int) $learner->id, $mid, $sectionId);
        $wallKey = $this->theoryQuizWallStartKey((int) $learner->id, $mid, $sectionId);
        $tlMin = $courseId > 0
            ? $this->sectionService->theoryQuizTimeLimitMinutesForSection($sec)
            : CourseScoringService::THEORY_QUIZ_TIME_LIMIT_MINUTES;
        $hasTimeLimit = $tlMin !== null && (int) $tlMin > 0;
        $ts = session($deadlineKey);
        $wallStartSess = session($wallKey);
        if ($hasTimeLimit) {
            if ($ts === null || ! is_numeric($ts) || now()->getTimestamp() > (int) $ts) {
                session()->forget([$deadlineKey, $wallKey]);

                return redirect()->route('course.module.section.theory-quiz', $this->sectionRouteParams($ctx))
                    ->with('err', 'Время на прохождение теста истекло. Начните попытку снова.');
            }
        } elseif ($wallStartSess === null || ! is_numeric($wallStartSess)) {
            session()->forget([$deadlineKey, $wallKey]);

            return redirect()->route('course.module.section.theory-quiz', $this->sectionRouteParams($ctx))
                ->with('err', 'Активная попытка не найдена. Начните тестирование снова.');
        }

        $qs = $this->questionsForSection($sec, $contentIdx);
        $p = $this->learner()->progressFor($mid);
        $quizSt = SectionProgress::quizState($p, $sec, $sole);
        $attemptsBefore = (int) ($quizSt['attempts'] ?? 0);
        $attemptNo = $attemptsBefore + 1;
        $penalty = $courseId > 0
            ? $this->sectionService->theoryQuizPenaltyForAttemptForSection($sec, $attemptNo)
            : ($attemptsBefore >= 1 ? CourseScoringService::THEORY_QUIZ_RETAKE_PENALTY_POINTS : 0);
        $threshold = $courseId > 0
            ? $this->sectionService->passPercentForSection($sec)
            : CourseScoringService::PASS_THRESHOLD;

        $breakdown = $this->scoreTheoryQuizBreakdown($qs, $request, 'q');
        $rawPercent = $breakdown['raw_percent'];
        $finalPercent = max(0, $rawPercent - $penalty);

        $wallStart = session()->pull($wallKey);
        if ($wallStart !== null && is_numeric($wallStart)) {
            $elapsedRaw = max(0, now()->getTimestamp() - (int) $wallStart);
            $elapsed = $hasTimeLimit
                ? min((int) $tlMin * 60, $elapsedRaw)
                : $elapsedRaw;
            if ($elapsed > 0) {
                $p->seconds_theory_quiz = (int) ($p->seconds_theory_quiz ?? 0) + $elapsed;
            }
        }

        $breakdownMinutes = $courseId > 0
            ? $this->sectionService->theoryQuizBreakdownVisibleMinutesForSection($sec)
            : CourseScoringService::THEORY_QUIZ_BREAKDOWN_VISIBLE_MINUTES;
        $breakdownUntil = CourseScoringService::breakdownVisibleUntilTimestamp($breakdownMinutes);
        $breakdownUnlimited = $breakdownUntil === null;

        $payload = [
            'module' => $mid,
            'section_id' => $sectionId,
            'raw_percent' => $rawPercent,
            'penalty_points' => $penalty,
            'final_percent' => $finalPercent,
            'passed' => $finalPercent >= $threshold,
            'threshold' => $threshold,
            'correct_count' => $breakdown['correct_count'],
            'wrong_count' => $breakdown['wrong_count'],
            'total' => $breakdown['total'],
            'items' => $breakdown['items'],
            'breakdown_visible_until' => $breakdownUnlimited ? null : (int) $breakdownUntil,
            'breakdown_unlimited' => $breakdownUnlimited,
            'recorded_at' => now()->toIso8601String(),
            'attempt_no' => $attemptNo,
        ];

        $hist = $quizSt['history'] ?? [];
        $hist[] = $payload;
        SectionProgress::saveQuizState($p, $sec, $sole, [
            'last_result' => $payload,
            'history' => $hist,
            'attempts' => $attemptNo,
            'best_score' => max((int) ($quizSt['best_score'] ?? 0), $finalPercent),
            'passed' => ($quizSt['passed'] ?? false) || $finalPercent >= $threshold,
        ]);
        $p->save();
        session()->forget($deadlineKey);

        $resultKey = $this->theoryQuizResultSessionKey($sectionId);

        return redirect()->route('course.module.section.theory-quiz.result', $this->sectionRouteParams($ctx))
            ->with($resultKey, $payload);
    }

    public function practiceShow(Request $request): View|RedirectResponse
    {
        $ctx = $this->routeContext($request);
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $moduleSequence = $ctx['moduleSequence'];
        $courseId = $ctx['courseId'];
        $contentIdx = $cm->effectiveContentIndex();
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfTheoryQuizNotPassed($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfStepBlocked($learner, $mid, 'practice')) {
            return $r;
        }
        $meta = $this->courseModules->displayMeta($cm);
        if ($this->courseModules->selectedCourseIsLegacyAlt() && CourseModuleMeta::shouldSkipPractice($contentIdx)) {
            return redirect()->to($this->hubUrl($ctx))->with(
                'ok',
                'В этом модуле нет практического занятия — перейдите к итоговому тесту (шаг «Экзамен» на странице модуля).'
            );
        }
        $practiceSession = null;
        if (Schema::hasTable('practice_sessions')) {
            $practiceSession = PracticeSession::query()
                ->where('learner_id', $learner->id)
                ->where('course_id', $courseId)
                ->where('module_id', $mid)
                ->first();
        }
        $lab = PracticeLabService::make();
        $this->ensurePracticeSegmentStarted($mid);

        return view('modules.practice', [
            'courseId' => $courseId,
            'module' => $moduleSequence,
            'moduleSequence' => $moduleSequence,
            'meta' => $meta,
            'progress' => $learner->progressFor($mid),
            'practiceSession' => $practiceSession,
            'labConfigured' => $lab->isConfigured(),
            'labImage' => $lab->imageForCourseModule($cm, $contentIdx),
            'labEnabled' => (bool) config('practice_lab.enabled'),
            'allowManualDone' => (bool) config('practice_lab.allow_manual_done'),
        ]);
    }

    public function practiceDone(Request $request): RedirectResponse
    {
        $ctx = $this->routeContext($request);
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $contentIdx = $cm->effectiveContentIndex();
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfTheoryQuizNotPassed($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfStepBlocked($learner, $mid, 'practice')) {
            return $r;
        }
        if ($this->courseModules->selectedCourseIsLegacyAlt() && CourseModuleMeta::shouldSkipPractice($contentIdx)) {
            return redirect()->to($this->hubUrl($ctx))->with(
                'ok',
                'В этом модуле нет практического занятия — перейдите к итоговому тесту.'
            );
        }
        if (config('practice_lab.enabled') && ! config('practice_lab.allow_manual_done')) {
            return redirect()->route('course.module.practice', LearnerRoute::hub($ctx['courseId'], $ctx['moduleSequence']))->with(
                'err',
                'Отметка вручную отключена. Завершите практику через кнопку после проверки или включите PRACTICE_LAB_ALLOW_MANUAL_DONE для методиста.'
            );
        }
        $p = $this->learner()->progressFor($mid);
        $this->accumulatePracticeSegmentSeconds($p, $mid);
        $p->practice_done_at = now();
        $p->save();

        return redirect()->to($this->hubUrl($ctx))->with('ok', 'Практика отмечена как выполненная.');
    }

    public function examShow(Request $request): View|RedirectResponse
    {
        $ctx = $this->assessmentSectionContext($request, CourseSection::TYPE_EXAM);
        if ($ctx instanceof RedirectResponse) {
            return $ctx;
        }
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $moduleSequence = $ctx['moduleSequence'];
        $courseId = $ctx['courseId'];
        $sec = $ctx['section'];
        $sole = $ctx['sole'];
        $contentIdx = $cm->effectiveContentIndex();
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfSectionHidden($learner, (int) $sec->id, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfExamPrerequisitesMissing($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfStepBlocked($learner, $mid, $sec->backendStepKey())) {
            return $r;
        }
        $p = $learner->progressFor($mid);
        $quizSt = SectionProgress::quizState($p, $sec, $sole);
        $maxAttempts = $courseId > 0
            ? $this->sectionService->examMaxAttemptsForSection($sec)
            : CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS;
        $passTh = $courseId > 0
            ? $this->sectionService->passPercentForSection($sec)
            : CourseScoringService::PASS_THRESHOLD;
        $retakePen = $courseId > 0
            ? $this->sectionService->examPenaltyForAttemptForSection($sec, 2)
            : CourseScoringService::MODULE_EXAM_RETAKE_PENALTY_POINTS;
        $qs = $this->questionsForSection($sec, $contentIdx);
        if (count($qs) === 0) {
            return redirect()->to($this->hubUrl($ctx))->with('err', 'Итоговый тест для этого модуля не настроен.');
        }
        $previewWalkthrough = $this->staffPreviewWalkthrough();
        if (! $previewWalkthrough && (int) ($quizSt['attempts'] ?? 0) >= $maxAttempts) {
            return redirect()->route('course.module.section.exam.result', $this->sectionRouteParams($ctx))->with(
                'err',
                'Исчерпаны все попытки итогового теста ('.$maxAttempts.'). Ниже сохранённый результат.'
            );
        }

        $attemptNo = (int) ($quizSt['attempts'] ?? 0) + 1;
        $deadlineInfo = SectionProgress::examDeadline($p, $sec, $sole);
        $deadline = $deadlineInfo['deadline'];
        $deadlineFor = $deadlineInfo['for_attempt'];
        $unlimitedAttempt = (bool) ($deadlineInfo['unlimited'] ?? false);
        $timeLimitMin = $this->moduleExamTimeLimitMinutesForSection($sec, $cm);
        $hasTimeLimit = $timeLimitMin !== null && (int) $timeLimitMin > 0;

        $deadlineValidForAttempt = ! $previewWalkthrough
            && $deadlineFor > 0
            && $deadlineFor === $attemptNo
            && (
                ($hasTimeLimit && $deadline !== null && $deadline->isFuture())
                || (! $hasTimeLimit && ($unlimitedAttempt || ($deadline !== null && $deadline->isFuture())))
            );

        if (! $previewWalkthrough && $hasTimeLimit && $deadline !== null && $deadlineFor === $attemptNo && $deadline->isPast()) {
            SectionProgress::clearExamDeadline($p, $sec, $sole);
            $p->save();

            return redirect()->to($this->hubUrl($ctx))->with(
                'err',
                'Отведённое на итоговый тест время ('.$timeLimitMin.' мин.) истекло. Начните попытку снова.'
            );
        }

        $examActive = $previewWalkthrough || $deadlineValidForAttempt;
        $expiresAtMs = $previewWalkthrough || ! $examActive || ! $hasTimeLimit || $deadline === null
            ? null
            : ($deadline->getTimestamp() * 1000);
        $scoreDisplay = LearnerScoreDisplay::flags(
            $courseId > 0 ? Course::query()->find($courseId) : null,
            $cm
        );

        return view('modules.exam', [
            'courseId' => $courseId,
            'module' => $moduleSequence,
            'moduleSequence' => $moduleSequence,
            'section' => $sec,
            'sectionSequence' => $ctx['sectionSequence'],
            'meta' => $this->courseModules->displayMeta($cm),
            'questions' => $qs,
            'progress' => $p,
            'quizState' => $quizSt,
            'attemptNumber' => $attemptNo,
            'maxAttempts' => $maxAttempts,
            'retakePenalty' => $retakePen,
            'passThreshold' => $passTh,
            'timeLimitMinutes' => $timeLimitMin,
            'examActive' => $examActive,
            'expiresAtMs' => $expiresAtMs,
            'needsRetakeAck' => ! $previewWalkthrough && (int) ($quizSt['attempts'] ?? 0) >= 1,
            'examOneByOne' => $courseId > 0 ? $this->sectionService->examOneByOneForSection($sec) : true,
            'previewWalkthrough' => $previewWalkthrough,
            'showScorePercents' => $scoreDisplay['showScorePercents'],
            'showScorePoints' => $scoreDisplay['showScorePoints'],
        ]);
    }

    public function examStart(Request $request): RedirectResponse
    {
        if ($r = $this->redirectIfStaffPreviewAssessmentWrite($request, 'course.module.section.exam')) {
            return $r;
        }
        $ctx = $this->assessmentSectionContext($request, CourseSection::TYPE_EXAM);
        if ($ctx instanceof RedirectResponse) {
            return $ctx;
        }
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $courseId = $ctx['courseId'];
        $sec = $ctx['section'];
        $sole = $ctx['sole'];
        $contentIdx = $cm->effectiveContentIndex();
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfSectionHidden($learner, (int) $sec->id, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfExamPrerequisitesMissing($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfStepBlocked($learner, $mid, $sec->backendStepKey())) {
            return $r;
        }
        $p = $learner->progressFor($mid);
        $quizSt = SectionProgress::quizState($p, $sec, $sole);
        $maxAttempts = $courseId > 0
            ? $this->sectionService->examMaxAttemptsForSection($sec)
            : CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS;
        $qs = $this->questionsForSection($sec, $contentIdx);
        if (count($qs) === 0) {
            return redirect()->to($this->hubUrl($ctx))->with('err', 'Итоговый тест для этого модуля не настроен.');
        }
        if ((int) ($quizSt['attempts'] ?? 0) >= $maxAttempts) {
            return redirect()->route('course.module.section.exam.result', $this->sectionRouteParams($ctx))->with(
                'err',
                'Исчерпаны все попытки итогового теста ('.$maxAttempts.').'
            );
        }

        $attemptNo = (int) ($quizSt['attempts'] ?? 0) + 1;
        $deadlineInfo = SectionProgress::examDeadline($p, $sec, $sole);
        $deadline = $deadlineInfo['deadline'];
        $deadlineFor = $deadlineInfo['for_attempt'];
        $unlimitedAttempt = (bool) ($deadlineInfo['unlimited'] ?? false);
        $timeLimitMin = $this->moduleExamTimeLimitMinutesForSection($sec, $cm);
        $hasTimeLimit = $timeLimitMin !== null && (int) $timeLimitMin > 0;
        $alreadyActive = $deadlineFor === $attemptNo && (
            ($hasTimeLimit && $deadline !== null && $deadline->isFuture())
            || (! $hasTimeLimit && ($unlimitedAttempt || ($deadline !== null && $deadline->isFuture())))
        );

        if ($alreadyActive) {
            return redirect()->route('course.module.section.exam', $this->sectionRouteParams($ctx));
        }

        SectionProgress::setExamDeadline(
            $p,
            $sec,
            $sole,
            $hasTimeLimit ? now()->addMinutes((int) $timeLimitMin) : null,
            $attemptNo,
        );
        $p->save();

        return redirect()->route('course.module.section.exam', $this->sectionRouteParams($ctx));
    }

    public function examResult(Request $request): View|RedirectResponse
    {
        if ($r = $this->redirectIfStaffPreviewAssessmentWrite($request, 'course.module.section.exam')) {
            return $r;
        }
        $ctx = $this->assessmentSectionContext($request, CourseSection::TYPE_EXAM);
        if ($ctx instanceof RedirectResponse) {
            return $ctx;
        }
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $moduleSequence = $ctx['moduleSequence'];
        $courseId = $ctx['courseId'];
        $sec = $ctx['section'];
        $sole = $ctx['sole'];
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfSectionHidden($learner, (int) $sec->id, $mid)) {
            return $r;
        }
        $p = $learner->progressFor($mid);
        $resultKey = $this->moduleExamResultSessionKey((int) $sec->id);
        $data = session($resultKey);
        if (! is_array($data) || (int) ($data['section_id'] ?? 0) !== (int) $sec->id) {
            $quizSt = SectionProgress::quizState($p, $sec, $sole);
            $data = $quizSt['last_result'] ?? null;
        }
        if (empty($data) || ! is_array($data)) {
            return redirect()->to($this->hubUrl($ctx))->with('err', 'Пока нет результата итогового теста. Пройдите тест с шага модуля.');
        }

        $breakdownView = $this->prepareLearnerQuizBreakdownView($data);
        $scoreDisplay = LearnerScoreDisplay::flags(
            $courseId > 0 ? Course::query()->find($courseId) : null,
            $cm
        );

        return view('modules.exam-result', [
            'courseId' => $courseId,
            'module' => $moduleSequence,
            'moduleSequence' => $moduleSequence,
            'section' => $sec,
            'sectionSequence' => $ctx['sectionSequence'],
            'meta' => $this->courseModules->displayMeta($cm),
            'r' => $breakdownView['result'],
            'progress' => $p,
            'showExamBreakdown' => $breakdownView['showBreakdown'],
            'breakdownUntilTs' => $breakdownView['breakdownUntilTs'],
            'breakdownExpired' => $breakdownView['breakdownExpired'],
            'wrongItems' => $breakdownView['wrongItems'],
            'showScorePercents' => $scoreDisplay['showScorePercents'],
            'showScorePoints' => $scoreDisplay['showScorePoints'],
        ]);
    }

    public function examSubmit(Request $request): RedirectResponse
    {
        if ($r = $this->redirectIfStaffPreviewAssessmentWrite($request, 'course.module.section.exam')) {
            return $r;
        }
        $ctx = $this->assessmentSectionContext($request, CourseSection::TYPE_EXAM);
        if ($ctx instanceof RedirectResponse) {
            return $ctx;
        }
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $courseId = $ctx['courseId'];
        $sec = $ctx['section'];
        $sole = $ctx['sole'];
        $contentIdx = $cm->effectiveContentIndex();
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfSectionHidden($learner, (int) $sec->id, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfExamPrerequisitesMissing($learner, $mid)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfStepBlocked($learner, $mid, $sec->backendStepKey())) {
            return $r;
        }
        $maxAttempts = $courseId > 0
            ? $this->sectionService->examMaxAttemptsForSection($sec)
            : CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS;
        $passTh = $courseId > 0
            ? $this->sectionService->passPercentForSection($sec)
            : CourseScoringService::PASS_THRESHOLD;
        $qs = $this->questionsForSection($sec, $contentIdx);
        $p = $learner->progressFor($mid);
        $quizSt = SectionProgress::quizState($p, $sec, $sole);
        if (count($qs) === 0) {
            return redirect()->to($this->hubUrl($ctx))->with('err', 'Тест не настроен.');
        }
        if ((int) ($quizSt['attempts'] ?? 0) >= $maxAttempts) {
            return redirect()->route('course.module.section.exam.result', $this->sectionRouteParams($ctx))->with('err', 'Попытки исчерпаны.');
        }

        $attemptNo = (int) ($quizSt['attempts'] ?? 0) + 1;
        $deadlineInfo = SectionProgress::examDeadline($p, $sec, $sole);
        $deadline = $deadlineInfo['deadline'];
        $deadlineFor = $deadlineInfo['for_attempt'];
        $unlimitedAttempt = (bool) ($deadlineInfo['unlimited'] ?? false);
        $timeLimitMin = $this->moduleExamTimeLimitMinutesForSection($sec, $cm);
        $hasTimeLimit = $timeLimitMin !== null && (int) $timeLimitMin > 0;
        $attemptActive = $deadlineFor === $attemptNo && (
            ($hasTimeLimit && $deadline !== null && $deadline->isFuture())
            || (! $hasTimeLimit && ($unlimitedAttempt || ($deadline !== null && $deadline->isFuture())))
        );
        if (! $attemptActive) {
            SectionProgress::clearExamDeadline($p, $sec, $sole);
            $p->save();

            return redirect()->to($this->hubUrl($ctx))->with(
                'err',
                $hasTimeLimit
                    ? 'Время на прохождение итогового теста истекло. Начните попытку снова.'
                    : 'Активная попытка не найдена. Начните итоговый тест снова.'
            );
        }

        $breakdown = $this->scoreModuleExamBreakdown($qs, $request);
        $attemptAfter = $attemptNo;
        $examDeadline = $deadline;
        $raw = $breakdown['raw_percent'];
        $penaltyPts = $courseId > 0
            ? $this->sectionService->examPenaltyForAttemptForSection($sec, $attemptAfter)
            : ($attemptAfter >= 2 ? CourseScoringService::MODULE_EXAM_RETAKE_PENALTY_POINTS : 0);
        $penaltyApplied = $attemptAfter >= 2 && $penaltyPts > 0;
        $final = $penaltyPts > 0
            ? max(0, $raw - $penaltyPts)
            : $raw;
        $wasPassed = (bool) ($quizSt['passed'] ?? false);
        $passedThisAttempt = $final >= $passTh;
        $modulePassed = $wasPassed || $passedThisAttempt;

        $examBreakdownMinutes = $courseId > 0
            ? $this->sectionService->examBreakdownVisibleMinutesForSection($sec)
            : CourseScoringService::MODULE_EXAM_BREAKDOWN_VISIBLE_MINUTES;
        $examBreakdownUntil = CourseScoringService::breakdownVisibleUntilTimestamp($examBreakdownMinutes);
        $examBreakdownUnlimited = $examBreakdownUntil === null;

        $payload = [
            'module' => $mid,
            'section_id' => (int) $sec->id,
            'raw_percent' => $raw,
            'final_percent' => $final,
            'passed' => $modulePassed,
            'passed_this_attempt' => $passedThisAttempt,
            'threshold' => $passTh,
            'attempt' => $attemptAfter,
            'penalty_applied' => $penaltyApplied,
            'penalty_points' => $penaltyApplied ? $penaltyPts : 0,
            'items' => $breakdown['items'],
            'correct_count' => $breakdown['correct_count'],
            'wrong_count' => $breakdown['wrong_count'],
            'total' => $breakdown['total'],
            'max_points' => $breakdown['max_points'] ?? null,
            'earned_points' => $breakdown['earned_points'] ?? null,
            'breakdown_visible_until' => $examBreakdownUnlimited ? null : (int) $examBreakdownUntil,
            'breakdown_unlimited' => $examBreakdownUnlimited,
            'recorded_at' => now()->toIso8601String(),
        ];

        $examHist = $quizSt['history'] ?? [];
        $examHist[] = $payload;
        SectionProgress::saveQuizState($p, $sec, $sole, [
            'attempts' => $attemptAfter,
            'best_score' => (int) $final,
            'passed' => $modulePassed,
            'last_result' => $payload,
            'history' => $examHist,
        ]);
        if ($examDeadline !== null && $hasTimeLimit) {
            $lim = (int) $timeLimitMin;
            $start = $examDeadline->copy()->subMinutes($lim);
            $cap = $lim * 60;
            $elapsed = min($cap, max(0, now()->diffInSeconds($start)));
            if ($elapsed > 0) {
                $p->seconds_module_exam = (int) ($p->seconds_module_exam ?? 0) + $elapsed;
            }
        }
        if ($modulePassed && $p->module_cleared_at === null && Schema::hasColumn('module_progress', 'module_cleared_at')) {
            $p->module_cleared_at = now();
        }
        SectionProgress::clearExamDeadline($p, $sec, $sole);
        $p->save();

        $resultKey = $this->moduleExamResultSessionKey((int) $sec->id);

        return redirect()->route('course.module.section.exam.result', $this->sectionRouteParams($ctx))
            ->with($resultKey, $payload);
    }

    private function practiceSegmentSessionKey(int $courseModuleId): string
    {
        return 'practice_segment_start_'.$courseModuleId;
    }

    private function ensurePracticeSegmentStarted(int $courseModuleId): void
    {
        $k = $this->practiceSegmentSessionKey($courseModuleId);
        if (! session()->has($k)) {
            session([$k => now()->getTimestamp()]);
        }
    }

    private function accumulatePracticeSegmentSeconds(ModuleProgress $p, int $courseModuleId): void
    {
        $k = $this->practiceSegmentSessionKey($courseModuleId);
        $ts = session()->pull($k);
        if ($ts === null || ! is_numeric($ts)) {
            return;
        }
        $elapsed = max(0, min(86400 * 14, now()->getTimestamp() - (int) $ts));
        if ($elapsed > 0) {
            $p->seconds_practice = (int) ($p->seconds_practice ?? 0) + $elapsed;
        }
    }

    public function saveDifficulties(Request $request): RedirectResponse
    {
        $ctx = $this->routeContext($request);
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $courseId = $ctx['courseId'];
        if ($r = $this->accessGate->redirectIfModuleLocked($this->learner(), $mid)) {
            return $r;
        }
        if ($courseId > 0 && Schema::hasTable('courses') && Schema::hasColumn('courses', 'difficulty_flags_enabled')) {
            $enabled = (bool) \App\Models\Course::query()
                ->whereKey($courseId)
                ->value('difficulty_flags_enabled');
            if (! $enabled) {
                return redirect()->to($this->hubUrl($ctx));
            }
        }
        $p = $this->learner()->progressFor($mid);
        $flags = [
            'theory' => false,
            'theory_quiz' => false,
            'practice' => false,
            'module_exam' => false,
        ];

        $contentIdx = $cm->effectiveContentIndex();
        if ($courseId > 0 && Schema::hasTable('course_sections') && $this->sectionService->useDbSectionsForModule($mid)) {
            $legacyAlt = $this->courseModules->selectedCourseIsLegacyAlt();
            $allowed = [];
            foreach ($this->visibility->visibleSectionsForLearner($mid, (int) $this->learner()->id) as $sec) {
                $bk = (string) $sec->backendStepKey();
                if ($bk === 'practice' && $this->sectionService->isPracticeWaived($mid, $contentIdx, $legacyAlt)) {
                    continue;
                }
                $allowed[$bk] = true;
            }
            foreach (array_keys($flags) as $k) {
                if (! empty($allowed[$k])) {
                    $flags[$k] = $request->boolean('d_'.$k);
                }
            }
        } else {
            $flags['theory'] = $request->boolean('d_theory');
            $flags['theory_quiz'] = $request->boolean('d_theory_quiz');
            if (! \App\Support\CourseModuleMeta::shouldSkipPractice($contentIdx)) {
                $flags['practice'] = $request->boolean('d_practice');
            }
            $flags['module_exam'] = $request->boolean('d_module_exam');
        }

        $p->difficulty_flags = $flags;
        $p->save();

        return redirect()->to($this->hubUrl($ctx))->with('ok', 'Отметки о сложностях сохранены.');
    }
}
