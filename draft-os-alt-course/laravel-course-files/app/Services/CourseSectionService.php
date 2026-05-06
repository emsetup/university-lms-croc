<?php

namespace App\Services;

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
    public function orderedBackendKeys(int $courseModuleId, int $contentSourceIndex): array
    {
        $out = [];
        foreach ($this->enabledSectionsForCourseModule($courseModuleId) as $sec) {
            $bk = $sec->backendStepKey();
            if ($bk === 'practice' && $this->isPracticeWaived($courseModuleId, $contentSourceIndex)) {
                continue;
            }
            $out[] = $bk;
        }

        return $out;
    }

    public function isPracticeWaived(int $courseModuleId, int $contentSourceIndex): bool
    {
        if (! $this->hasBackendStep($courseModuleId, 'practice')) {
            return true;
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

    public function passPercentForQuiz(int $courseModuleId): int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'theory_quiz');
        $v = $sec ? ($this->mergedSettings($sec)['pass_percent'] ?? null) : null;

        return is_numeric($v) && (int) $v > 0 ? (int) $v : CourseScoringService::PASS_THRESHOLD;
    }

    public function passPercentForExam(int $courseModuleId): int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'module_exam');
        $v = $sec ? ($this->mergedSettings($sec)['pass_percent'] ?? null) : null;

        return is_numeric($v) && (int) $v > 0 ? (int) $v : CourseScoringService::PASS_THRESHOLD;
    }

    public function theoryQuizTimeLimitMinutes(int $courseModuleId): int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'theory_quiz');
        $v = $sec ? ($this->mergedSettings($sec)['time_limit_minutes'] ?? null) : null;

        return is_numeric($v) && (int) $v > 0 ? (int) $v : CourseScoringService::THEORY_QUIZ_TIME_LIMIT_MINUTES;
    }

    public function theoryQuizAttemptLimit(int $courseModuleId): ?int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'theory_quiz');
        $v = $sec ? ($this->mergedSettings($sec)['attempt_limit'] ?? null) : null;
        if ($v === null || $v === '') {
            return null;
        }

        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
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

    public function examTimeLimitMinutes(int $courseModuleId, int $contentSourceIndex): int
    {
        $fromConfig = config('course.modules.'.$contentSourceIndex.'.module_exam_time_limit_minutes');
        if (is_numeric($fromConfig) && (int) $fromConfig > 0) {
            return (int) $fromConfig;
        }
        $sec = $this->findSectionByBackendKey($courseModuleId, 'module_exam');
        $v = $sec ? ($this->mergedSettings($sec)['time_limit_minutes'] ?? null) : null;

        return is_numeric($v) && (int) $v > 0 ? (int) $v : CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;
    }

    public function examMaxAttempts(int $courseModuleId): int
    {
        $sec = $this->findSectionByBackendKey($courseModuleId, 'module_exam');
        $v = $sec ? ($this->mergedSettings($sec)['attempt_limit'] ?? null) : null;

        return is_numeric($v) && (int) $v > 0 ? (int) $v : CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS;
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

    public function isStepComplete(ModuleProgress $p, string $backendKey, int $courseModuleId, int $contentSourceIndex): bool
    {
        if ($backendKey === 'practice' && $this->isPracticeWaived($courseModuleId, $contentSourceIndex)) {
            return true;
        }

        return match ($backendKey) {
            'theory' => (bool) $p->theory_read_at,
            'theory_quiz' => (bool) $p->theory_quiz_passed,
            'practice' => (bool) $p->practice_done_at,
            'module_exam' => (bool) $p->module_exam_passed,
            default => false,
        };
    }

    public function firstBlockedPrerequisite(ModuleProgress $p, int $courseModuleId, int $contentSourceIndex, string $targetBackendKey): ?string
    {
        $order = $this->orderedBackendKeys($courseModuleId, $contentSourceIndex);
        $idx = array_search($targetBackendKey, $order, true);
        if ($idx === false) {
            return null;
        }
        for ($i = 0; $i < (int) $idx; $i++) {
            $step = $order[$i];
            if (! $this->isStepComplete($p, $step, $courseModuleId, $contentSourceIndex)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function progressBackendKeys(int $courseModuleId, int $contentSourceIndex): array
    {
        return $this->orderedBackendKeys($courseModuleId, $contentSourceIndex);
    }

    public function moduleProgressPercent(ModuleProgress $p, int $courseModuleId, int $contentSourceIndex): int
    {
        $keys = $this->progressBackendKeys($courseModuleId, $contentSourceIndex);
        if ($keys === []) {
            return 0;
        }
        $done = 0;
        foreach ($keys as $bk) {
            if ($this->isStepComplete($p, $bk, $courseModuleId, $contentSourceIndex)) {
                $done++;
            }
        }

        return (int) round(100 * $done / count($keys));
    }
}
