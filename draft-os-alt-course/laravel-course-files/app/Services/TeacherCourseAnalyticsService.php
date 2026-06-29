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
    /** @var array<string, bool>|null */
    private ?array $schemaTables = null;

    public function __construct(
        private CourseScoringService $scoring,
        private CourseModuleService $courseModules,
        private LearnerLastActivityService $lastActivity,
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
        $activityByCourse = $this->lastActivity->timestampsForLearners($ids, [$courseId])['by_course'];

        $rows = [];
        foreach ($learners as $learner) {
            $lid = (int) $learner->id;
            $row = $this->rowForLearner($learner, $courseId);
            $row['full_name'] = $nameByLearner[$lid] ?? '';
            $row['last_active_ts'] = (int) ($activityByCourse[$lid][$courseId] ?? 0);
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
        $finalMax = $this->scoring->maxFinalLabPoints($courseId);
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
            'has_scoring' => $grandMax > 0,
            'final_lab_enabled' => $finalMax > 0,
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
        $batch = $this->batchCatalogStats([$courseId]);

        return (int) ($batch[$courseId]['avg_progress_pct'] ?? 0);
    }

    /**
     * Сводная статистика для каталога курсов (без тяжёлого расчёта среднего прогресса).
     *
     * @param  list<int>  $courseIds
     * @return array<int, array{enrolled:int, completed:int, completed_rate_pct:int}>
     */
    public function batchCatalogStatsFast(array $courseIds): array
    {
        $courseIds = array_values(array_unique(array_filter(array_map('intval', $courseIds), fn (int $id) => $id > 0)));
        if ($courseIds === []) {
            return [];
        }

        $learnersByCourse = $this->batchLearnerIdsByCourse($courseIds);
        $completedByCourse = $this->batchCompletedCounts($courseIds);

        $out = [];
        foreach ($courseIds as $courseId) {
            $enrolled = count($learnersByCourse[$courseId] ?? []);
            $completed = (int) ($completedByCourse[$courseId] ?? 0);
            $out[$courseId] = [
                'enrolled' => $enrolled,
                'completed' => $completed,
                'completed_rate_pct' => $enrolled > 0 ? (int) round(100 * $completed / $enrolled) : 0,
            ];
        }

        return $out;
    }

    /**
     * Средний процент прохождения по баллам для списка курсов (тяжёлый расчёт).
     *
     * @param  list<int>  $courseIds
     * @return array<int, int>
     */
    public function batchCatalogAvgProgress(array $courseIds): array
    {
        $courseIds = array_values(array_unique(array_filter(array_map('intval', $courseIds), fn (int $id) => $id > 0)));
        if ($courseIds === []) {
            return [];
        }

        $learnersByCourse = $this->batchLearnerIdsByCourse($courseIds);

        return $this->batchAverageGrandTotalPercents($courseIds, $learnersByCourse);
    }

    /**
     * Сводная статистика для каталога курсов одним пакетом (без N+1 запросов).
     *
     * @param  list<int>  $courseIds
     * @return array<int, array{enrolled:int, completed:int, completed_rate_pct:int, avg_progress_pct:int}>
     */
    public function batchCatalogStats(array $courseIds): array
    {
        $courseIds = array_values(array_unique(array_filter(array_map('intval', $courseIds), fn (int $id) => $id > 0)));
        if ($courseIds === []) {
            return [];
        }

        $fast = $this->batchCatalogStatsFast($courseIds);
        $avgByCourse = $this->batchCatalogAvgProgress($courseIds);

        $out = [];
        foreach ($courseIds as $courseId) {
            $row = $fast[$courseId] ?? ['enrolled' => 0, 'completed' => 0, 'completed_rate_pct' => 0];
            $out[$courseId] = [
                'enrolled' => (int) $row['enrolled'],
                'completed' => (int) $row['completed'],
                'completed_rate_pct' => (int) $row['completed_rate_pct'],
                'avg_progress_pct' => (int) ($avgByCourse[$courseId] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $courseIds
     * @return array<int, list<int>>
     */
    private function batchLearnerIdsByCourse(array $courseIds): array
    {
        $sets = [];
        foreach ($courseIds as $courseId) {
            $sets[$courseId] = [];
        }

        if ($this->hasTable('course_enrollments')) {
            foreach (DB::table('course_enrollments')->whereIn('course_id', $courseIds)->get(['course_id', 'learner_id']) as $row) {
                $sets[(int) $row->course_id][(int) $row->learner_id] = true;
            }
        }
        if ($this->hasTable('module_progress')) {
            foreach (DB::table('module_progress')->whereIn('course_id', $courseIds)->get(['course_id', 'learner_id']) as $row) {
                $sets[(int) $row->course_id][(int) $row->learner_id] = true;
            }
        }
        if ($this->hasTable('final_lab_results')) {
            foreach (DB::table('final_lab_results')->whereIn('course_id', $courseIds)->get(['course_id', 'learner_id']) as $row) {
                $sets[(int) $row->course_id][(int) $row->learner_id] = true;
            }
        }

        $out = [];
        foreach ($sets as $courseId => $learnerSet) {
            $out[(int) $courseId] = array_map('intval', array_keys($learnerSet));
        }

        return $out;
    }

    /**
     * @param  list<int>  $courseIds
     * @return array<int, int>
     */
    private function batchCompletedCounts(array $courseIds): array
    {
        if (! $this->hasTable('final_lab_results')) {
            return [];
        }

        return DB::table('final_lab_results')
            ->whereIn('course_id', $courseIds)
            ->whereNotNull('certificate_full_name')
            ->whereNotNull('certificate_serial')
            ->groupBy('course_id')
            ->selectRaw('course_id, COUNT(DISTINCT learner_id) as completed')
            ->pluck('completed', 'course_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @param  list<int>  $courseIds
     * @param  array<int, list<int>>  $learnersByCourse
     * @return array<int, int>
     */
    private function batchAverageGrandTotalPercents(array $courseIds, array $learnersByCourse): array
    {
        $allLearnerIds = [];
        foreach ($learnersByCourse as $learnerIds) {
            foreach ($learnerIds as $learnerId) {
                $allLearnerIds[$learnerId] = true;
            }
        }
        $allLearnerIds = array_map('intval', array_keys($allLearnerIds));
        if ($allLearnerIds === []) {
            return array_fill_keys($courseIds, 0);
        }

        $progressRows = $this->hasTable('module_progress')
            ? ModuleProgress::query()
                ->whereIn('course_id', $courseIds)
                ->whereIn('learner_id', $allLearnerIds)
                ->get()
            : collect();

        $finalRows = $this->hasTable('final_lab_results')
            ? FinalLabResult::query()
                ->whereIn('course_id', $courseIds)
                ->whereIn('learner_id', $allLearnerIds)
                ->get()
            : collect();

        $progressByKey = [];
        foreach ($progressRows as $progress) {
            $progressByKey[(int) $progress->course_id.'-'.(int) $progress->learner_id][] = $progress;
        }

        $finalByKey = [];
        foreach ($finalRows as $final) {
            $finalByKey[(int) $final->course_id.'-'.(int) $final->learner_id] = $final;
        }

        $maxGrandByCourse = [];
        foreach ($courseIds as $courseId) {
            $moduleMax = $this->scoring->maxTotalModulePoints($courseId);
            $maxGrandByCourse[$courseId] = $this->scoring->maxTotalModulePoints($courseId) + $this->scoring->maxFinalLabPoints($courseId);
        }

        $out = [];
        foreach ($courseIds as $courseId) {
            $learnerIds = $learnersByCourse[$courseId] ?? [];
            if ($learnerIds === []) {
                $out[$courseId] = 0;
                continue;
            }

            $maxGrand = (int) ($maxGrandByCourse[$courseId] ?? 0);
            if ($maxGrand <= 0) {
                $out[$courseId] = 0;
                continue;
            }

            $sum = 0;
            foreach ($learnerIds as $learnerId) {
                $key = $courseId.'-'.$learnerId;
                $progresses = collect($progressByKey[$key] ?? []);
                $final = $finalByKey[$key] ?? null;

                $learner = new Learner(['id' => $learnerId]);
                $learner->setRelation('moduleProgresses', $progresses);
                $learner->setRelation('finalLabResults', $final !== null ? collect([$final]) : collect());

                $modulePoints = $this->scoring->totalModulePointsSafe($learner, $courseId);
                $finalPoints = $this->scoring->finalLabPoints($final);
                $grandTotal = min($maxGrand, max(0, $modulePoints + $finalPoints));
                $sum += (int) round(100 * $grandTotal / $maxGrand);
            }

            $out[$courseId] = (int) round($sum / count($learnerIds));
        }

        return $out;
    }

    private function trackedSeconds(ModuleProgress $progress): int
    {
        return max(0, (int) ($progress->seconds_theory ?? 0))
            + max(0, (int) ($progress->seconds_theory_quiz ?? 0))
            + max(0, (int) ($progress->seconds_practice ?? 0))
            + max(0, (int) ($progress->seconds_module_exam ?? 0));
    }

    private function hasTable(string $table): bool
    {
        if ($this->schemaTables === null) {
            $this->schemaTables = [];
        }
        if (! array_key_exists($table, $this->schemaTables)) {
            $this->schemaTables[$table] = Schema::hasTable($table);
        }

        return $this->schemaTables[$table];
    }
}
