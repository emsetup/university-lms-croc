<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\FinalLabResult;
use App\Models\Learner;
use App\Models\ModuleProgress;
use App\Support\LearnerDisplay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TeacherCourseAnalyticsService
{
    public function __construct(
        private CourseScoringService $scoring,
        private CourseModuleService $courseModules,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function learnerRows(int $courseId): array
    {
        return $this->learnerRowsForCourse($courseId);
    }

    /**
     * Строки отчёта только по обучающимся, связанным с курсом (зачисление, прогресс или ИЛР).
     *
     * @return list<array<string, mixed>>
     */
    public function learnerRowsForCourse(int $courseId): array
    {
        $ids = $this->learnerIdsTouchingCourse($courseId);
        if ($ids === []) {
            return [];
        }

        $learners = Learner::query()
            ->whereIn('id', $ids)
            ->with([
                'moduleProgresses' => fn ($q) => $q->where('course_id', $courseId),
                'finalLabResults' => fn ($q) => $q->where('course_id', $courseId),
            ])
            ->orderBy('email')
            ->get();

        $nameByLearner = LearnerDisplay::portalDisplayNamesByLearnerIds($ids);

        $rows = [];
        foreach ($learners as $learner) {
            $row = $this->rowForLearner($learner, $courseId);
            $row['full_name'] = $nameByLearner[(int) $learner->id] ?? '';
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function rowForLearner(Learner $learner, int $courseId): array
    {
        $learner->loadMissing([
            'moduleProgresses' => fn ($q) => $q->where('course_id', $courseId),
            'finalLabResults' => fn ($q) => $q->where('course_id', $courseId),
        ]);

        $moduleCount = CourseScoringService::moduleCount($courseId);
        $moduleMax = $this->scoring->maxTotalModulePoints($courseId);
        $finalMax = CourseScoringService::MAX_FINAL_LAB_POINTS;
        $grandMax = $moduleMax + $finalMax;

        $modulePoints = $this->scoring->totalModulePointsSafe($learner, $courseId);
        $final = $learner->finalLabResults->first();
        $finalPoints = $this->scoring->finalLabPoints($final);
        $grandTotal = min($grandMax, max(0, $modulePoints + $finalPoints));

        $modulesPassed = 0;
        $estimatedTestMinutes = 0;
        $trackedTotalSeconds = 0;
        $firstStarted = null;
        $lastTouched = null;

        $activeModuleIds = $courseId > 0 && Schema::hasTable('course_modules')
            ? $this->courseModules->orderedModuleIdsForCourse($courseId)
            : [];

        foreach ($learner->moduleProgresses as $progress) {
            if ($activeModuleIds !== [] && ! in_array((int) $progress->course_module_id, $activeModuleIds, true)) {
                continue;
            }
            if ($progress->module_exam_passed) {
                $modulesPassed++;
            }

            $estimatedTestMinutes += (int) ($progress->theory_quiz_attempts ?? 0) * CourseScoringService::THEORY_QUIZ_TIME_LIMIT_MINUTES;
            $estimatedTestMinutes += (int) ($progress->module_exam_attempts ?? 0) * CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;

            $trackedTotalSeconds += $this->trackedSeconds($progress);

            $started = $progress->module_access_started_at;
            if ($started !== null && ($firstStarted === null || $started->lt($firstStarted))) {
                $firstStarted = $started;
            }

            foreach ([
                $progress->updated_at,
                $progress->module_cleared_at,
                $progress->module_exam_deadline_at,
                $progress->practice_done_at,
                $progress->theory_read_at,
            ] as $stamp) {
                if ($stamp !== null && ($lastTouched === null || $stamp->gt($lastTouched))) {
                    $lastTouched = $stamp;
                }
            }
        }

        $spanDays = null;
        if ($firstStarted !== null && $lastTouched !== null) {
            $spanDays = max(1, (int) $firstStarted->diffInDays($lastTouched) + 1);
        }

        return [
            'id' => (int) $learner->id,
            'email' => (string) $learner->email,
            'modules_passed_count' => $modulesPassed,
            'total_module_points' => $modulePoints,
            'max_module_points' => $moduleMax,
            'module_points_percent' => $moduleMax > 0 ? (int) round(100 * $modulePoints / $moduleMax) : 0,
            'grand_total' => $grandTotal,
            'max_grand_total' => $grandMax,
            'grand_total_percent' => $grandMax > 0 ? (int) round(100 * $grandTotal / $grandMax) : 0,
            'final_lab_points' => $finalPoints,
            'max_final_lab_points' => $finalMax,
            'final_lab' => $final
                ? [
                    'passed' => (bool) $final->passed,
                    'best_score' => (int) ($final->best_score ?? 0),
                    'attempts' => (int) ($final->attempts ?? 0),
                ]
                : null,
            'time' => [
                'span_days' => $spanDays,
                'estimated_test_minutes' => $estimatedTestMinutes,
            ],
            'time_tracked' => [
                'total' => $trackedTotalSeconds,
            ],
            'module_count' => $moduleCount,
        ];
    }

    /**
     * @return array{enrolled:int, started:int, completed:int}
     */
    public function courseCounters(int $courseId): array
    {
        $enrolled = (int) CourseEnrollment::query()->where('course_id', $courseId)->count();
        $started = (int) CourseEnrollment::query()->where('course_id', $courseId)->whereNotNull('started_at')->count();
        $completed = (int) FinalLabResult::query()->where('course_id', $courseId)->where('passed', true)->count();

        return ['enrolled' => $enrolled, 'started' => $started, 'completed' => $completed];
    }

    /**
     * Обучающиеся, у которых есть запись на курс, прогресс по модулям или результат ИЛР.
     *
     * @return list<int>
     */
    public function learnerIdsTouchingCourse(int $courseId): array
    {
        if ($courseId < 1) {
            return [];
        }

        $ids = collect();

        if (Schema::hasTable('course_enrollments')) {
            $ids = $ids->merge(
                DB::table('course_enrollments')->where('course_id', $courseId)->pluck('learner_id')
            );
        }
        if (Schema::hasTable('module_progress')) {
            $ids = $ids->merge(
                DB::table('module_progress')->where('course_id', $courseId)->pluck('learner_id')
            );
        }
        if (Schema::hasTable('final_lab_results')) {
            $ids = $ids->merge(
                DB::table('final_lab_results')->where('course_id', $courseId)->pluck('learner_id')
            );
        }

        return $ids->unique()->filter()->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Средний процент прохождения курса по баллам (как в отчёте по обучающимся).
     */
    public function averageGrandTotalPercentForCourse(int $courseId): int
    {
        $learnerIds = $this->learnerIdsTouchingCourse($courseId);
        if ($learnerIds === []) {
            return 0;
        }

        $learners = Learner::query()
            ->whereIn('id', $learnerIds)
            ->with([
                'moduleProgresses' => fn ($q) => $q->where('course_id', $courseId),
                'finalLabResults' => fn ($q) => $q->where('course_id', $courseId),
            ])
            ->get();

        if ($learners->isEmpty()) {
            return 0;
        }

        $sum = 0;
        foreach ($learners as $learner) {
            $row = $this->rowForLearner($learner, $courseId);
            $sum += (int) ($row['grand_total_percent'] ?? 0);
        }

        return (int) round($sum / $learners->count());
    }

    private function trackedSeconds(ModuleProgress $progress): int
    {
        return max(0, (int) ($progress->seconds_theory ?? 0))
            + max(0, (int) ($progress->seconds_theory_quiz ?? 0))
            + max(0, (int) ($progress->seconds_practice ?? 0))
            + max(0, (int) ($progress->seconds_module_exam ?? 0));
    }
}
