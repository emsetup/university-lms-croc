<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\FinalLabResult;
use App\Models\Learner;
use App\Models\ModuleProgress;

final class TeacherCourseAnalyticsService
{
    public function __construct(
        private CourseScoringService $scoring
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function learnerRows(int $courseId): array
    {
        $rows = [];
        $learners = Learner::query()
            ->with([
                'moduleProgresses' => fn ($q) => $q->where('course_id', $courseId),
                'finalLabResults' => fn ($q) => $q->where('course_id', $courseId),
            ])
            ->orderBy('email')
            ->get();

        foreach ($learners as $learner) {
            $rows[] = $this->rowForLearner($learner, $courseId);
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

        foreach ($learner->moduleProgresses as $progress) {
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

    private function trackedSeconds(ModuleProgress $progress): int
    {
        return max(0, (int) ($progress->seconds_theory ?? 0))
            + max(0, (int) ($progress->seconds_theory_quiz ?? 0))
            + max(0, (int) ($progress->seconds_practice ?? 0))
            + max(0, (int) ($progress->seconds_module_exam ?? 0));
    }
}
