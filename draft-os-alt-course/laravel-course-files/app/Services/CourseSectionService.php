<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\ModuleProgress;
use App\Support\CourseModuleMeta;
use App\Support\SectionProgress;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    public function visibleSectionsForLearner(int $courseModuleId, int $learnerId): Collection
    {
        return app(LearnerContentVisibilityService::class)
            ->visibleSectionsForLearner($courseModuleId, $learnerId);
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

    /**
     * Порядковый номер раздела среди включённых в модуле (1..N), по sort/id.
     */
    public function sequenceForSection(CourseSection $section): int
    {
        $courseModuleId = (int) $section->course_module_id;
        if ($courseModuleId < 1) {
            return 1;
        }

        $sort = (int) ($section->sort ?? 0);
        $id = (int) ($section->id ?? 0);
        if ($id < 1) {
            return 1;
        }

        $before = 0;
        foreach ($this->enabledSectionsForCourseModule($courseModuleId) as $sec) {
            $secSort = (int) ($sec->sort ?? 0);
            $secId = (int) ($sec->id ?? 0);
            if ($secSort < $sort || ($secSort === $sort && $secId < $id)) {
                $before++;
            }
        }

        return max(1, $before + 1);
    }

    public function findBySequenceForModule(int $courseModuleId, int $sequence): ?CourseSection
    {
        if ($courseModuleId < 1 || $sequence < 1) {
            return null;
        }

        return $this->enabledSectionsForCourseModule($courseModuleId)->get($sequence - 1);
    }

    /**
     * Раздел по порядковому номеру (1..N) или по legacy id в URL (старые закладки).
     */
    public function findOrFailBySequenceForModuleRoute(int $courseModuleId, int $sectionRoute): CourseSection
    {
        $sec = $this->findBySequenceForModule($courseModuleId, $sectionRoute);
        if ($sec === null) {
            $sec = CourseSection::query()->find($sectionRoute);
            if ($sec
                && (int) $sec->course_module_id === $courseModuleId
                && $sec->is_enabled) {
                return $sec;
            }
            throw (new ModelNotFoundException)->setModel(CourseSection::class, [$sectionRoute]);
        }

        return $sec;
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

    public function hasBackendStep(int $courseModuleId, string $stepKey): bool
    {
        return $this->findSectionByStepKey($courseModuleId, $stepKey) !== null;
    }

    public function findSectionByStepKey(int $courseModuleId, string $stepKey): ?CourseSection
    {
        $sectionId = CourseSection::idFromStepKey($stepKey);
        if ($sectionId !== null) {
            $sec = CourseSection::query()->find($sectionId);
            if ($sec
                && (int) $sec->course_module_id === $courseModuleId
                && $sec->is_enabled) {
                return $sec;
            }

            return null;
        }

        foreach ($this->enabledSectionsForCourseModule($courseModuleId) as $sec) {
            if ($sec->legacyTypeKey() === $stepKey) {
                return $sec;
            }
        }

        return null;
    }

    /** @deprecated use findSectionByStepKey */
    public function findSectionByBackendKey(int $courseModuleId, string $backendKey): ?CourseSection
    {
        return $this->findSectionByStepKey($courseModuleId, $backendKey);
    }

    public function countEnabledSectionsOfType(int $courseModuleId, string $type): int
    {
        return $this->enabledSectionsForCourseModule($courseModuleId)
            ->where('type', $type)
            ->count();
    }

    public function isSoleSectionOfType(CourseSection $section): bool
    {
        return $this->countEnabledSectionsOfType((int) $section->course_module_id, (string) $section->type) === 1;
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

    public function passPercentForSection(CourseSection $section): int
    {
        $m = $this->mergedSettings($section);
        if (($m['pass_from_course'] ?? false) === true) {
            $course = Course::query()->find((int) $section->course_id);
            $def = $course?->default_pass_percent;
            if (is_numeric($def) && (int) $def > 0) {
                return (int) $def;
            }

            return CourseScoringService::PASS_THRESHOLD;
        }
        $v = $m['pass_percent'] ?? null;
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }
        $course = Course::query()->find((int) $section->course_id);
        $def = $course?->default_pass_percent;
        if (is_numeric($def) && (int) $def > 0) {
            return (int) $def;
        }

        return CourseScoringService::PASS_THRESHOLD;
    }

    /** null = без ограничения по времени */
    public function theoryQuizTimeLimitMinutesForSection(CourseSection $section): ?int
    {
        $m = $this->mergedSettings($section);
        if (($m['time_from_course'] ?? false) === true) {
            $course = Course::query()->find((int) $section->course_id);
            $def = $course?->default_quiz_time_minutes;
            if (is_numeric($def) && (int) $def > 0) {
                return (int) $def;
            }

            return null;
        }
        $v = $m['time_limit_minutes'] ?? null;
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }

        return null;
    }

    public function theoryQuizAttemptLimitForSection(CourseSection $section): ?int
    {
        $m = $this->mergedSettings($section);
        if (($m['attempts_from_course'] ?? false) === true) {
            $course = Course::query()->find((int) $section->course_id);
            $def = $course?->default_attempt_limit;
            if ($def !== null && is_numeric($def) && (int) $def > 0) {
                return (int) $def;
            }

            return null;
        }
        $v = $m['attempt_limit'] ?? null;
        if ($v !== null && $v !== '' && is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }

        return null;
    }

    public function theoryQuizPenaltyForAttemptForSection(CourseSection $section, int $attemptNo): int
    {
        if ($attemptNo <= 1) {
            return 0;
        }
        $pen = $this->mergedSettings($section)['penalties'] ?? [];
        if (! is_array($pen)) {
            return CourseScoringService::THEORY_QUIZ_RETAKE_PENALTY_POINTS;
        }
        $key = (string) $attemptNo;

        return isset($pen[$key]) && is_numeric($pen[$key]) ? max(0, (int) $pen[$key]) : CourseScoringService::THEORY_QUIZ_RETAKE_PENALTY_POINTS;
    }

    public function theoryQuizShuffleForSection(CourseSection $section): bool
    {
        return (bool) ($this->mergedSettings($section)['shuffle'] ?? false);
    }

    /** null = без ограничения по времени; 0 = не показывать разбор; >0 = минуты. */
    public function theoryQuizBreakdownVisibleMinutesForSection(CourseSection $section): ?int
    {
        $v = $this->mergedSettings($section)['breakdown_visible_minutes'] ?? null;

        return CourseScoringService::normalizeBreakdownVisibleMinutes(
            $v,
            CourseScoringService::THEORY_QUIZ_BREAKDOWN_VISIBLE_MINUTES
        );
    }

    /** null = без ограничения по времени */
    public function examTimeLimitMinutesForSection(CourseSection $section, int $contentSourceIndex, bool $legacyAlt = true): ?int
    {
        if ($legacyAlt) {
            $fromConfig = config('course.modules.'.$contentSourceIndex.'.module_exam_time_limit_minutes');
            if (is_numeric($fromConfig) && (int) $fromConfig > 0) {
                return (int) $fromConfig;
            }
        }
        $m = $this->mergedSettings($section);
        if (($m['time_from_course'] ?? false) === true) {
            $course = Course::query()->find((int) $section->course_id);
            $def = $course?->default_quiz_time_minutes;
            if (is_numeric($def) && (int) $def > 0) {
                return (int) $def;
            }

            return null;
        }
        $v = $m['time_limit_minutes'] ?? null;
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }

        return null;
    }

    public function examMaxAttemptsForSection(CourseSection $section): int
    {
        $m = $this->mergedSettings($section);
        if (($m['attempts_from_course'] ?? false) === true) {
            $course = Course::query()->find((int) $section->course_id);
            $def = $course?->default_attempt_limit;
            if (is_numeric($def) && (int) $def > 0) {
                return (int) $def;
            }

            return CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS;
        }
        $v = $m['attempt_limit'] ?? null;
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }
        $course = Course::query()->find((int) $section->course_id);
        $def = $course?->default_attempt_limit;
        if (is_numeric($def) && (int) $def > 0) {
            return (int) $def;
        }

        return CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS;
    }

    public function examPenaltyForAttemptForSection(CourseSection $section, int $attemptNo): int
    {
        if ($attemptNo <= 1) {
            return 0;
        }
        $pen = $this->mergedSettings($section)['penalties'] ?? [];
        if (! is_array($pen)) {
            return CourseScoringService::MODULE_EXAM_RETAKE_PENALTY_POINTS;
        }
        $key = (string) $attemptNo;

        return isset($pen[$key]) && is_numeric($pen[$key]) ? max(0, (int) $pen[$key]) : CourseScoringService::MODULE_EXAM_RETAKE_PENALTY_POINTS;
    }

    /** null = без ограничения по времени; 0 = не показывать разбор; >0 = минуты. */
    public function examBreakdownVisibleMinutesForSection(CourseSection $section): ?int
    {
        $v = $this->mergedSettings($section)['breakdown_visible_minutes'] ?? null;

        return CourseScoringService::normalizeBreakdownVisibleMinutes(
            $v,
            CourseScoringService::MODULE_EXAM_BREAKDOWN_VISIBLE_MINUTES
        );
    }

    public function examOneByOneForSection(CourseSection $section): bool
    {
        return (bool) ($this->mergedSettings($section)['one_by_one'] ?? true);
    }

    public function passPercentForQuiz(int $courseModuleId): int
    {
        $sec = $this->findSectionByStepKey($courseModuleId, 'theory_quiz');
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
        $sec = $this->findSectionByStepKey($courseModuleId, 'module_exam');
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

    /** null = без ограничения по времени */
    public function theoryQuizTimeLimitMinutes(int $courseModuleId): ?int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'theory_quiz');
        if ($sec === null) {
            return CourseScoringService::THEORY_QUIZ_TIME_LIMIT_MINUTES;
        }

        return $this->theoryQuizTimeLimitMinutesForSection($sec);
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

    /** null = без ограничения по времени; 0 = не показывать разбор; >0 = минуты. */
    public function theoryQuizBreakdownVisibleMinutes(int $courseModuleId): ?int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'theory_quiz');
        $v = $sec ? ($this->mergedSettings($sec)['breakdown_visible_minutes'] ?? null) : null;

        return CourseScoringService::normalizeBreakdownVisibleMinutes(
            $v,
            CourseScoringService::THEORY_QUIZ_BREAKDOWN_VISIBLE_MINUTES
        );
    }

    /** null = без ограничения по времени */
    public function examTimeLimitMinutes(int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): ?int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'module_exam');
        if ($sec === null) {
            if ($legacyAlt) {
                $fromConfig = config('course.modules.'.$contentSourceIndex.'.module_exam_time_limit_minutes');
                if (is_numeric($fromConfig) && (int) $fromConfig > 0) {
                    return (int) $fromConfig;
                }
            }

            return CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;
        }

        return $this->examTimeLimitMinutesForSection($sec, $contentSourceIndex, $legacyAlt);
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

    /** null = без ограничения по времени; 0 = не показывать разбор; >0 = минуты. */
    public function examBreakdownVisibleMinutes(int $courseModuleId): ?int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'module_exam');
        $v = $sec ? ($this->mergedSettings($sec)['breakdown_visible_minutes'] ?? null) : null;

        return CourseScoringService::normalizeBreakdownVisibleMinutes(
            $v,
            CourseScoringService::MODULE_EXAM_BREAKDOWN_VISIBLE_MINUTES
        );
    }

    public function examOneByOne(int $courseModuleId): bool
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'module_exam');
        if ($sec === null) {
            return true;
        }

        return (bool) ($this->mergedSettings($sec)['one_by_one'] ?? true);
    }

    /**
     * Нужно ли проходить этапы модуля строго по цепочке (есть разделы, блокирующие следующие).
     */
    public function moduleEnforcesSectionOrder(int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): bool
    {
        if (! $this->useDbSectionsForModule($courseModuleId)) {
            return true;
        }
        $order = $this->orderedBackendKeys($courseModuleId, $contentSourceIndex, $legacyAlt);
        if (count($order) <= 1) {
            return false;
        }
        for ($i = 1, $n = count($order); $i < $n; $i++) {
            for ($j = 0; $j < $i; $j++) {
                if ($this->stepBlocksProgress($courseModuleId, $order[$j])) {
                    return true;
                }
            }
        }

        return false;
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
            if (! $this->stepBlocksProgress($courseModuleId, $step)) {
                continue;
            }
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
        $keys = array_values(array_filter(
            $this->progressBackendKeys($courseModuleId, $contentSourceIndex, $legacyAlt),
            fn (string $bk) => $this->stepBlocksProgress($courseModuleId, $bk)
        ));
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

    public function isStepComplete(ModuleProgress $p, string $stepKey, int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): bool
    {
        $sec = $this->findSectionByStepKey($courseModuleId, $stepKey);
        if ($sec !== null) {
            if ($sec->legacyTypeKey() === 'practice' && $this->isPracticeWaived($courseModuleId, $contentSourceIndex, $legacyAlt)) {
                return true;
            }

            return $this->isSectionComplete($p, $sec, $courseModuleId, $contentSourceIndex, $legacyAlt);
        }

        if ($stepKey === 'practice' && $this->isPracticeWaived($courseModuleId, $contentSourceIndex, $legacyAlt)) {
            return true;
        }

        return match ($stepKey) {
            'theory' => (bool) $p->theory_read_at,
            'theory_quiz' => $this->isTheoryQuizEffectivelyPassed($p, $courseModuleId),
            'practice' => (bool) $p->practice_done_at,
            'module_exam' => $this->isModuleExamEffectivelyPassed($p, $courseModuleId),
            'survey' => $this->isSurveyComplete($p, $courseModuleId),
            default => false,
        };
    }

    public function isSectionComplete(
        ModuleProgress $p,
        CourseSection $section,
        int $courseModuleId,
        int $contentSourceIndex,
        bool $legacyAlt = true,
    ): bool {
        $sole = $this->isSoleSectionOfType($section);

        return match ($section->type) {
            CourseSection::TYPE_TEXT => SectionProgress::isTextRead($p, $section, $sole),
            CourseSection::TYPE_QUIZ => $this->isSectionQuizPassed($p, $section, $sole),
            CourseSection::TYPE_PRACTICE => $this->isPracticeWaived($courseModuleId, $contentSourceIndex, $legacyAlt)
                || SectionProgress::isPracticeDone($p, $section, $sole),
            CourseSection::TYPE_EXAM => $this->isSectionExamPassed($p, $section, $sole),
            CourseSection::TYPE_SURVEY => $this->isSurveyCompleteForSection($p, (int) $section->id),
            default => false,
        };
    }

    public function isSectionQuizPassed(ModuleProgress $p, CourseSection $section, ?bool $sole = null): bool
    {
        $sole ??= $this->isSoleSectionOfType($section);
        $st = SectionProgress::quizState($p, $section, $sole);
        if (! empty($st['passed'])) {
            return true;
        }
        $threshold = $this->passPercentForSection($section);

        return $this->quizStateIndicatesPass($st, $threshold);
    }

    public function isSectionExamPassed(ModuleProgress $p, CourseSection $section, ?bool $sole = null): bool
    {
        $sole ??= $this->isSoleSectionOfType($section);
        $st = SectionProgress::quizState($p, $section, $sole);
        if (! empty($st['passed'])) {
            return true;
        }
        $threshold = $this->passPercentForSection($section);

        return $this->quizStateIndicatesPass($st, $threshold);
    }

    /**
     * @param  array{passed:bool,attempts:int,best_score:int,last_result:?array,history:array}  $st
     */
    private function quizStateIndicatesPass(array $st, int $threshold): bool
    {
        if ((int) ($st['best_score'] ?? 0) >= $threshold) {
            return true;
        }
        if ($this->quizAttemptIndicatesPass($st['last_result'] ?? null, $threshold)) {
            return true;
        }
        foreach ($st['history'] ?? [] as $entry) {
            if ($this->quizAttemptIndicatesPass($entry, $threshold)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Зачёт по тесту теории: флаг в БД или сохранённые результаты (best_score, history).
     * Нужно при пересдаче: theory_quiz_passed сбрасывается, лучший % остаётся.
     */


    public function stepBlocksProgress(int $courseModuleId, string $stepKey): bool
    {
        $sec = $this->findSectionByStepKey($courseModuleId, $stepKey);
        if ($sec === null) {
            return $stepKey !== 'survey';
        }
        $settings = $this->mergedSettings($sec);
        if (array_key_exists('blocks_progress', $settings)) {
            return (bool) $settings['blocks_progress'];
        }

        return true;
    }

    public function isSurveyComplete(ModuleProgress $p, int $courseModuleId): bool
    {
        foreach ($this->enabledSectionsForCourseModule($courseModuleId) as $sec) {
            if ($sec->type !== CourseSection::TYPE_SURVEY) {
                continue;
            }
            if (! $this->isSurveyCompleteForSection($p, (int) $sec->id)) {
                return false;
            }
        }

        return $this->enabledSectionsForCourseModule($courseModuleId)
            ->where('type', CourseSection::TYPE_SURVEY)
            ->isNotEmpty();
    }

    public function isSurveyCompleteForSection(ModuleProgress $p, int $sectionId): bool
    {
        if (! Schema::hasTable('course_survey_submissions')) {
            return false;
        }
        $learnerId = (int) ($p->learner_id ?? 0);
        if ($learnerId < 1 || $sectionId < 1) {
            return false;
        }

        return \App\Models\CourseSurveySubmission::query()
            ->where('course_section_id', $sectionId)
            ->where('learner_id', $learnerId)
            ->whereHas('answers')
            ->exists();
    }

    public function isSurveyAnonymous(int $courseModuleId, ?int $sectionId = null): bool
    {
        if ($sectionId !== null) {
            $sec = CourseSection::query()->find($sectionId);
        } else {
            $sec = $this->findSectionByStepKey($courseModuleId, 'survey');
        }
        if ($sec === null) {
            return false;
        }
        $settings = $this->mergedSettings($sec);

        return (bool) ($settings['anonymous'] ?? false);
    }

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
            foreach ($this->enabledSectionsForCourseModule($courseModuleId) as $sec) {
                $lt = $sec->legacyTypeKey();
                if ($lt === 'practice' && $this->isPracticeWaived($courseModuleId, $contentSourceIndex, $legacyAlt)) {
                    continue;
                }
                if (isset(self::DEFAULT_SCORE_WEIGHTS[$lt])) {
                    $out[] = $sec->backendStepKey();
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
        $legacyCounts = [];
        foreach ($keys as $k) {
            $sec = $this->findSectionByStepKey($courseModuleId, $k);
            $lt = $sec?->legacyTypeKey() ?? $k;
            $legacyCounts[$lt] = ($legacyCounts[$lt] ?? 0) + 1;
        }
        foreach ($keys as $k) {
            $sec = $this->findSectionByStepKey($courseModuleId, $k);
            $lt = $sec?->legacyTypeKey() ?? $k;
            $base = self::DEFAULT_SCORE_WEIGHTS[$lt] ?? 0.0;
            $cnt = max(1, $legacyCounts[$lt] ?? 1);
            $w = $base / $cnt;
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
            $sec = $this->findSectionByStepKey($courseModuleId, $bk);
            $lt = $sec?->legacyTypeKey() ?? $bk;
            $label = $labels[$lt] ?? $lt;
            if ($sec !== null) {
                $label = (string) $sec->title;
            }
            $out[] = [
                'label' => $label,
                'pct' => (int) round($w * 100),
            ];
        }

        return $out;
    }

    public function scorePercentForBackendKey(ModuleProgress $p, string $stepKey, int $courseModuleId, int $contentSourceIndex, bool $legacyAlt = true): int
    {
        $sec = $this->findSectionByStepKey($courseModuleId, $stepKey);
        if ($sec !== null) {
            $sole = $this->isSoleSectionOfType($sec);

            return match ($sec->type) {
                CourseSection::TYPE_QUIZ => min(100, max(0, (int) (SectionProgress::quizState($p, $sec, $sole)['best_score'] ?? 0))),
                CourseSection::TYPE_PRACTICE => $this->isPracticeWaived($courseModuleId, $contentSourceIndex, $legacyAlt)
                    ? 100
                    : min(100, max(0, SectionProgress::practicePercent($p, $sec, $sole))),
                CourseSection::TYPE_EXAM => min(100, max(0, (int) (SectionProgress::quizState($p, $sec, $sole)['best_score'] ?? 0))),
                default => 0,
            };
        }

        if ($stepKey === 'practice' && $this->isPracticeWaived($courseModuleId, $contentSourceIndex, $legacyAlt)) {
            return 100;
        }

        return match ($stepKey) {
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
        string $stepKey,
        int $courseModuleId,
        int $contentSourceIndex,
        bool $legacyAlt = true
    ): int {
        $sec = $this->findSectionByStepKey($courseModuleId, $stepKey);
        if ($sec !== null) {
            $sole = $this->isSoleSectionOfType($sec);

            return match ($sec->type) {
                CourseSection::TYPE_TEXT => SectionProgress::isTextRead($p, $sec, $sole) ? 100 : 0,
                CourseSection::TYPE_SURVEY => $this->isSurveyCompleteForSection($p, (int) $sec->id) ? 100 : 0,
                CourseSection::TYPE_PRACTICE => $this->isPracticeWaived($courseModuleId, $contentSourceIndex, $legacyAlt)
                    ? 0
                    : $this->scorePercentForBackendKey($p, $stepKey, $courseModuleId, $contentSourceIndex, $legacyAlt),
                default => $this->scorePercentForBackendKey($p, $stepKey, $courseModuleId, $contentSourceIndex, $legacyAlt),
            };
        }

        if ($stepKey === 'practice' && $this->isPracticeWaived($courseModuleId, $contentSourceIndex, $legacyAlt)) {
            return 0;
        }

        return match ($stepKey) {
            'theory' => $p->theory_read_at ? 100 : 0,
            'theory_quiz', 'practice', 'module_exam' => $this->scorePercentForBackendKey($p, $stepKey, $courseModuleId, $contentSourceIndex, $legacyAlt),
            'survey' => $this->isSurveyComplete($p, $courseModuleId) ? 100 : 0,
            default => 0,
        };
    }

    public static function legacyTypeColorKey(string $legacyType): string
    {
        return match ($legacyType) {
            'theory_quiz' => 'tq',
            'practice' => 'pr',
            'module_exam' => 'ex',
            default => preg_replace('/[^a-z0-9_]/', '', $legacyType) ?: 'part',
        };
    }

    /**
     * Оцениваемые этапы модуля для итоговой статистики (только quiz / practice / exam).
     *
     * @return list<array{key: string, color_key: string, label: string, pct: int, attempts: int|null, weight_pct: int, legacy_key: string}>
     */
    public function assessmentPartsForModule(
        ?ModuleProgress $p,
        int $courseModuleId,
        int $contentSourceIndex,
        bool $legacyAlt = true
    ): array {
        $titles = config('course.step_titles', []);
        $defaultLabels = [
            'theory_quiz' => (string) ($titles['theory_quiz'] ?? 'Тест по теории'),
            'practice' => (string) ($titles['practice'] ?? 'Практика'),
            'module_exam' => (string) ($titles['module_exam'] ?? 'Итоговый тест'),
        ];
        $weights = $this->moduleScoreWeights($courseModuleId, $contentSourceIndex, $legacyAlt);
        $parts = [];

        foreach ($this->scorableBackendKeys($courseModuleId, $contentSourceIndex, $legacyAlt) as $bk) {
            $sec = $this->findSectionByStepKey($courseModuleId, $bk);
            $legacyKey = $sec?->legacyTypeKey() ?? $bk;
            $label = $sec !== null && (string) $sec->title !== ''
                ? (string) $sec->title
                : ($defaultLabels[$legacyKey] ?? $legacyKey);
            $pct = $p !== null
                ? $this->scorePercentForBackendKey($p, $bk, $courseModuleId, $contentSourceIndex, $legacyAlt)
                : 0;
            $attempts = null;
            if ($p !== null && $sec !== null && in_array($sec->type, [CourseSection::TYPE_QUIZ, CourseSection::TYPE_EXAM], true)) {
                $sole = $this->isSoleSectionOfType($sec);
                $attempts = (int) (SectionProgress::quizState($p, $sec, $sole)['attempts'] ?? 0);
            } elseif ($p !== null) {
                $attempts = match ($legacyKey) {
                    'theory_quiz' => (int) ($p->theory_quiz_attempts ?? 0),
                    'module_exam' => (int) ($p->module_exam_attempts ?? 0),
                    default => null,
                };
            }

            $parts[] = [
                'key' => $bk,
                'color_key' => self::legacyTypeColorKey($legacyKey),
                'label' => $label,
                'pct' => $pct,
                'attempts' => $attempts,
                'weight_pct' => isset($weights[$bk]) ? (int) round($weights[$bk] * 100) : 0,
                'legacy_key' => $legacyKey,
            ];
        }

        return $parts;
    }
}
