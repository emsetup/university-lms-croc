<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\ModuleProgress;
use App\Support\CourseModuleMeta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Разделы модуля курса (порядок, настройки) из БД. При отсутствии строк — пустой набор (fallback в вызывающем коде).
 */
final class CourseSectionService
{
    /** @var array<int, Collection<int, CourseSection>> */
    private array $enabledCache = [];

    public function clearCache(): void
    {
        $this->enabledCache = [];
    }

    /**
     * @return Collection<int, CourseSection>
     */
    public function enabledSectionsForCourseModule(int $courseModuleId): Collection
    {
        if ($courseModuleId < 1 || ! Schema::hasTable('course_sections')) {
            return collect();
        }
        if (! isset($this->enabledCache[$courseModuleId])) {
            $q = CourseSection::query()
                ->where('course_module_id', $courseModuleId)
                ->where('is_enabled', true)
                ->orderBy('sort')
                ->orderBy('id')
                ->with('sectionSettings');
            if (Schema::hasColumn('course_sections', 'course_module_id')) {
                $this->enabledCache[$courseModuleId] = $q->get();
            } else {
                $this->enabledCache[$courseModuleId] = collect();
            }
        }

        return $this->enabledCache[$courseModuleId];
    }

    public function useDbSectionsForModule(int $courseModuleId): bool
    {
        return $courseModuleId > 0
            && Schema::hasTable('course_sections')
            && Schema::hasColumn('course_sections', 'course_module_id')
            && $this->enabledSectionsForCourseModule($courseModuleId)->isNotEmpty();
    }

    /**
     * @return list<string>
     */
    public function orderedBackendKeys(int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): array
    {
        $out = [];
        foreach ($this->enabledSectionsForCourseModule($courseModuleId) as $sec) {
            $bk = $sec->backendStepKey();
            if ($bk === 'practice' && $this->isPracticeWaived($courseModuleId, $contentSourceIndex, $legacyAlt)) {
                continue;
            }
            $out[] = $bk;
        }

        return $out;
    }

    public function isPracticeWaived(int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): bool
    {
        if (! $this->hasBackendStep($courseModuleId, 'practice')) {
            return true;
        }
        if (! $legacyAlt) {
            return false;
        }

        return CourseModuleMeta::shouldSkipPractice($contentSourceIndex);
    }

    public function hasBackendStep(int $courseModuleId, string $backendKey): bool
    {
        foreach ($this->enabledSectionsForCourseModule($courseModuleId) as $sec) {
            if ($sec->backendStepKey() === $backendKey) {
                return true;
            }
        }

        return false;
    }

    public function findSectionByBackendKey(int $courseModuleId, string $backendKey): ?CourseSection
    {
        foreach ($this->enabledSectionsForCourseModule($courseModuleId) as $sec) {
            if ($sec->backendStepKey() === $backendKey) {
                return $sec;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function mergedSettings(CourseSection $section): array
    {
        $raw = $section->sectionSettings?->settings;

        return is_array($raw) ? $raw : [];
    }

    private function courseForModule(int $courseModuleId): ?Course
    {
        $cid = (int) CourseModule::query()->whereKey($courseModuleId)->value('course_id');

        return $cid > 0 ? Course::query()->find($cid) : null;
    }

    public function passPercentForQuiz(int $courseModuleId): int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'theory_quiz');
        $m = $sec ? $this->mergedSettings($sec) : [];
        if (($m['pass_from_course'] ?? false) === true) {
            $course = $this->courseForModule($courseModuleId);
            $def = $course?->default_pass_percent;
            if (is_numeric($def) && (int) $def > 0) {
                return (int) $def;
            }

            return CourseScoringService::PASS_THRESHOLD;
        }
        $v = $sec ? ($m['pass_percent'] ?? null) : null;
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }
        $course = $this->courseForModule($courseModuleId);
        $def = $course?->default_pass_percent;
        if (is_numeric($def) && (int) $def > 0) {
            return (int) $def;
        }

        return CourseScoringService::PASS_THRESHOLD;
    }

    public function passPercentForExam(int $courseModuleId): int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'module_exam');
        $m = $sec ? $this->mergedSettings($sec) : [];
        if (($m['pass_from_course'] ?? false) === true) {
            $course = $this->courseForModule($courseModuleId);
            $def = $course?->default_pass_percent;
            if (is_numeric($def) && (int) $def > 0) {
                return (int) $def;
            }

            return CourseScoringService::PASS_THRESHOLD;
        }
        $v = $sec ? ($m['pass_percent'] ?? null) : null;
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }
        $course = $this->courseForModule($courseModuleId);
        $def = $course?->default_pass_percent;
        if (is_numeric($def) && (int) $def > 0) {
            return (int) $def;
        }

        return CourseScoringService::PASS_THRESHOLD;
    }

    public function theoryQuizTimeLimitMinutes(int $courseModuleId): int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'theory_quiz');
        $m = $sec ? $this->mergedSettings($sec) : [];
        if (($m['time_from_course'] ?? false) === true) {
            $course = $this->courseForModule($courseModuleId);
            $def = $course?->default_quiz_time_minutes;
            if (is_numeric($def) && (int) $def > 0) {
                return (int) $def;
            }

            return CourseScoringService::THEORY_QUIZ_TIME_LIMIT_MINUTES;
        }
        $v = $sec ? ($m['time_limit_minutes'] ?? null) : null;
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }
        $course = $this->courseForModule($courseModuleId);
        $def = $course?->default_quiz_time_minutes;
        if (is_numeric($def) && (int) $def > 0) {
            return (int) $def;
        }

        return CourseScoringService::THEORY_QUIZ_TIME_LIMIT_MINUTES;
    }

    public function theoryQuizAttemptLimit(int $courseModuleId): ?int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'theory_quiz');
        $m = $sec ? $this->mergedSettings($sec) : [];
        if (($m['attempts_from_course'] ?? false) === true) {
            $course = $this->courseForModule($courseModuleId);
            $def = $course?->default_attempt_limit;
            if ($def !== null && is_numeric($def) && (int) $def > 0) {
                return (int) $def;
            }

            return null;
        }
        $v = $sec ? ($m['attempt_limit'] ?? null) : null;
        if ($v !== null && $v !== '' && is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }

        return null;
    }

    public function theoryQuizPenaltyForAttempt(int $courseModuleId, int $attemptNo): int
    {
        if ($attemptNo <= 1) {
            return 0;
        }
        $sec = $this->findSectionByBackendKey($courseModuleId, 'theory_quiz');
        $pen = $sec ? ($this->mergedSettings($sec)['penalties'] ?? []) : [];
        if (! is_array($pen)) {
            return $attemptNo >= 2 ? CourseScoringService::THEORY_QUIZ_RETAKE_PENALTY_POINTS : 0;
        }
        $key = (string) $attemptNo;

        return isset($pen[$key]) && is_numeric($pen[$key]) ? max(0, (int) $pen[$key]) : CourseScoringService::THEORY_QUIZ_RETAKE_PENALTY_POINTS;
    }

    public function theoryQuizShuffle(int $courseModuleId): bool
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'theory_quiz');

        return (bool) ($sec ? ($this->mergedSettings($sec)['shuffle'] ?? false) : false);
    }

    public function theoryQuizBreakdownVisibleMinutes(int $courseModuleId): int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'theory_quiz');
        $v = $sec ? ($this->mergedSettings($sec)['breakdown_visible_minutes'] ?? null) : null;

        return is_numeric($v) && (int) $v >= 0 ? (int) $v : CourseScoringService::THEORY_QUIZ_BREAKDOWN_VISIBLE_MINUTES;
    }

    public function examTimeLimitMinutes(int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): int
    {
        if ($legacyAlt) {
            $fromConfig = config('course.modules.'.$contentSourceIndex.'.module_exam_time_limit_minutes');
            if (is_numeric($fromConfig) && (int) $fromConfig > 0) {
                return (int) $fromConfig;
            }
        }
        $sec = $this->findSectionByBackendKey($courseModuleId, 'module_exam');
        $m = $sec ? $this->mergedSettings($sec) : [];
        if (($m['time_from_course'] ?? false) === true) {
            $course = $this->courseForModule($courseModuleId);
            $def = $course?->default_quiz_time_minutes;
            if (is_numeric($def) && (int) $def > 0) {
                return (int) $def;
            }

            return CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;
        }
        $v = $sec ? ($m['time_limit_minutes'] ?? null) : null;
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }
        $course = $this->courseForModule($courseModuleId);
        $def = $course?->default_quiz_time_minutes;
        if (is_numeric($def) && (int) $def > 0) {
            return (int) $def;
        }

        return CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;
    }

    public function examMaxAttempts(int $courseModuleId): int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'module_exam');
        $m = $sec ? $this->mergedSettings($sec) : [];
        if (($m['attempts_from_course'] ?? false) === true) {
            $course = $this->courseForModule($courseModuleId);
            $def = $course?->default_attempt_limit;
            if (is_numeric($def) && (int) $def > 0) {
                return (int) $def;
            }

            return CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS;
        }
        $v = $sec ? ($m['attempt_limit'] ?? null) : null;
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }
        $course = $this->courseForModule($courseModuleId);
        $def = $course?->default_attempt_limit;
        if (is_numeric($def) && (int) $def > 0) {
            return (int) $def;
        }

        return CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS;
    }

    public function examPenaltyForAttempt(int $courseModuleId, int $attemptNo): int
    {
        if ($attemptNo <= 1) {
            return 0;
        }
        $sec = $this->findSectionByBackendKey($courseModuleId, 'module_exam');
        $pen = $sec ? ($this->mergedSettings($sec)['penalties'] ?? []) : [];
        if (! is_array($pen)) {
            return CourseScoringService::MODULE_EXAM_RETAKE_PENALTY_POINTS;
        }
        $key = (string) $attemptNo;

        return isset($pen[$key]) && is_numeric($pen[$key]) ? max(0, (int) $pen[$key]) : CourseScoringService::MODULE_EXAM_RETAKE_PENALTY_POINTS;
    }

    public function examBreakdownVisibleMinutes(int $courseModuleId): int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'module_exam');
        $v = $sec ? ($this->mergedSettings($sec)['breakdown_visible_minutes'] ?? null) : null;

        return is_numeric($v) && (int) $v >= 0 ? (int) $v : CourseScoringService::MODULE_EXAM_BREAKDOWN_VISIBLE_MINUTES;
    }

    public function examOneByOne(int $courseModuleId): bool
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'module_exam');
        if ($sec === null) {
            return true;
        }

        return (bool) ($this->mergedSettings($sec)['one_by_one'] ?? true);
    }

    public function firstBlockedPrerequisite(ModuleProgress $p, int $courseModuleId, int $contentSourceIndex, string $targetBackendKey, bool $legacyAlt = true): ?string
    {
        $order = $this->orderedBackendKeys($courseModuleId, $contentSourceIndex, $legacyAlt);
        $idx = array_search($targetBackendKey, $order, true);
        if ($idx === false) {
            return null;
        }
        for ($i = 0; $i < (int) $idx; $i++) {
            $step = $order[$i];
            if (! $this->isStepComplete($p, $step, $courseModuleId, $contentSourceIndex, $legacyAlt)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function progressBackendKeys(int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): array
    {
        return $this->orderedBackendKeys($courseModuleId, $contentSourceIndex, $legacyAlt);
    }

    public function moduleProgressPercent(ModuleProgress $p, int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): int
    {
        $keys = $this->progressBackendKeys($courseModuleId, $contentSourceIndex, $legacyAlt);
        if ($keys === []) {
            return 0;
        }
        $done = 0;
        foreach ($keys as $bk) {
            if ($this->isStepComplete($p, $bk, $courseModuleId, $contentSourceIndex, $legacyAlt)) {
                $done++;
            }
        }

        return (int) round(100 * $done / count($keys));
    }

    public function isStepComplete(ModuleProgress $p, string $backendKey, int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): bool
    {
        if ($backendKey === 'practice' && $this->isPracticeWaived($courseModuleId, $contentSourceIndex, $legacyAlt)) {
            return true;
        }

        return match ($backendKey) {
            'theory' => (bool) $p->theory_read_at,
            'theory_quiz' => $this->isTheoryQuizEffectivelyPassed($p, $courseModuleId),
            'practice' => (bool) $p->practice_done_at,
            'module_exam' => $this->isModuleExamEffectivelyPassed($p, $courseModuleId),
            default => false,
        };
    }

    /**
     * Зачёт по тесту теории: флаг в БД или сохранённые результаты (best_score, history).
     * Нужно при пересдаче: theory_quiz_passed сбрасывается, лучший % остаётся.
     */
    public function isTheoryQuizEffectivelyPassed(ModuleProgress $p, int $courseModuleId): bool
    {
        if ($p->theory_quiz_passed) {
            return true;
        }

        $threshold = $this->passPercentForQuiz($courseModuleId);
        if ((int) $p->theory_quiz_best_score >= $threshold) {
            return true;
        }

        if ($this->quizAttemptIndicatesPass($p->theory_quiz_last_result, $threshold)) {
            return true;
        }

        foreach ($p->theory_quiz_history ?? [] as $entry) {
            if ($this->quizAttemptIndicatesPass($entry, $threshold)) {
                return true;
            }
        }

        return false;
    }

    public function isModuleExamEffectivelyPassed(ModuleProgress $p, int $courseModuleId): bool
    {
        if ($p->module_exam_passed) {
            return true;
        }

        $threshold = $this->passPercentForExam($courseModuleId);
        if ((int) $p->module_exam_best_score >= $threshold) {
            return true;
        }

        if ($this->quizAttemptIndicatesPass($p->module_exam_last_result, $threshold)) {
            return true;
        }

        foreach ($p->module_exam_history ?? [] as $entry) {
            if ($this->quizAttemptIndicatesPass($entry, $threshold)) {
                return true;
            }
        }

        return false;
    }

    /** Выровнять флаги зачёта с сохранёнными баллами (старые записи, пересдача). */
    public function reconcilePassFlagsFromResults(ModuleProgress $p, int $courseModuleId): bool
    {
        $dirty = false;
        if (! $p->theory_quiz_passed && $this->isTheoryQuizEffectivelyPassed($p, $courseModuleId)) {
            $p->theory_quiz_passed = true;
            $dirty = true;
        }
        if (! $p->module_exam_passed && $this->isModuleExamEffectivelyPassed($p, $courseModuleId)) {
            $p->module_exam_passed = true;
            $dirty = true;
        }

        return $dirty;
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function quizAttemptIndicatesPass(?array $result, int $threshold): bool
    {
        if (! is_array($result) || $result === []) {
            return false;
        }
        if (! empty($result['passed'])) {
            return true;
        }
        if (array_key_exists('final_percent', $result)) {
            return (int) $result['final_percent'] >= $threshold;
        }
        if (array_key_exists('raw_percent', $result)) {
            return (int) $result['raw_percent'] >= $threshold;
        }

        return false;
    }

    /** @var array<string, float> */
    private const DEFAULT_SCORE_WEIGHTS = [
        'theory_quiz' => CourseScoringService::MODULE_SCORE_WEIGHT_THEORY_QUIZ,
        'practice' => CourseScoringService::MODULE_SCORE_WEIGHT_PRACTICE,
        'module_exam' => CourseScoringService::MODULE_SCORE_WEIGHT_EXAM,
    ];

    /**
     * Ключи этапов, участвующих в баллах модуля (теория без % не входит).
     *
     * @return list<string>
     */
    public function scorableBackendKeys(int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): array
    {
        if ($courseModuleId > 0 && $this->useDbSectionsForModule($courseModuleId)) {
            $out = [];
            foreach ($this->orderedBackendKeys($courseModuleId, $contentSourceIndex, $legacyAlt) as $bk) {
                if (isset(self::DEFAULT_SCORE_WEIGHTS[$bk])) {
                    $out[] = $bk;
                }
            }

            return $out;
        }

        $keys = ['theory_quiz', 'module_exam'];
        if (! CourseModuleMeta::shouldSkipPractice($contentSourceIndex)) {
            array_splice($keys, 1, 0, ['practice']);
        }

        return $keys;
    }

    /**
     * Нормализованные доли веса (сумма = 1) только по включённым этапам с баллами.
     *
     * @return array<string, float>
     */
    public function moduleScoreWeights(int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): array
    {
        $keys = $this->scorableBackendKeys($courseModuleId, $contentSourceIndex, $legacyAlt);
        if ($keys === []) {
            return [];
        }

        $sum = 0.0;
        $raw = [];
        foreach ($keys as $k) {
            $w = self::DEFAULT_SCORE_WEIGHTS[$k];
            $raw[$k] = $w;
            $sum += $w;
        }

        $out = [];
        foreach ($raw as $k => $w) {
            $out[$k] = $sum > 0 ? $w / $sum : 0.0;
        }

        return $out;
    }

    /**
     * Подписи весов для легенды на хабе: «Тест 50% · Экзамен 50%».
     *
     * @return list<array{label: string, pct: int}>
     */
    public function moduleScoreWeightLegend(int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): array
    {
        $titles = config('course.step_titles', []);
        $labels = [
            'theory_quiz' => (string) ($titles['theory_quiz'] ?? 'Тест по теории'),
            'practice' => (string) ($titles['practice'] ?? 'Практика'),
            'module_exam' => (string) ($titles['module_exam'] ?? 'Итоговый тест'),
        ];

        $out = [];
        foreach ($this->moduleScoreWeights($courseModuleId, $contentSourceIndex, $legacyAlt) as $bk => $w) {
            $out[] = [
                'label' => $labels[$bk] ?? $bk,
                'pct' => (int) round($w * 100),
            ];
        }

        return $out;
    }

    public function scorePercentForBackendKey(ModuleProgress $p, string $backendKey, int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): int
    {
        if ($backendKey === 'practice' && $this->isPracticeWaived($courseModuleId, $contentSourceIndex, $legacyAlt)) {
            return 100;
        }

        return match ($backendKey) {
            'theory_quiz' => min(100, max(0, (int) $p->theory_quiz_best_score)),
            'practice' => min(100, max(0, (int) ($p->practice_lab_percent ?? ($p->practice_done_at ? 100 : 0)))),
            'module_exam' => min(100, max(0, (int) $p->module_exam_best_score)),
            default => 0,
        };
    }

    public function modulePointsFromProgress(ModuleProgress $p, int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): int
    {
        $weights = $this->moduleScoreWeights($courseModuleId, $contentSourceIndex, $legacyAlt);
        if ($weights === []) {
            return 0;
        }

        $raw = 0.0;
        foreach ($weights as $bk => $w) {
            $raw += $w * $this->scorePercentForBackendKey($p, $bk, $courseModuleId, $contentSourceIndex, $legacyAlt);
        }

        return (int) round(CourseScoringService::MAX_POINTS_PER_MODULE * $raw / 100);
    }

    /**
     * % для строки прогресса в отчёте преподавателя (теория — по просмотру, тесты — по best_score).
     */
    public function displayProgressPercentForBackendKey(
        ModuleProgress $p,
        string $backendKey,
        int $courseModuleId,
        int $contentSourceIndex,
        bool $legacyAlt = true
    ): int {
        if ($backendKey === 'practice' && $this->isPracticeWaived($courseModuleId, $contentSourceIndex, $legacyAlt)) {
            return 0;
        }

        return match ($backendKey) {
            'theory' => $p->theory_read_at ? 100 : 0,
            'theory_quiz', 'practice', 'module_exam' => $this->scorePercentForBackendKey($p, $backendKey, $courseModuleId, $contentSourceIndex, $legacyAlt),
            default => 0,
        };
    }
}
