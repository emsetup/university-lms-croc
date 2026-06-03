<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Последняя активность обучающегося на портале (заход в курс, события, прогресс).
 */
final class LearnerLastActivityService
{
    /**
     * @param  list<int>  $learnerIds
     * @param  list<int>|null  $courseIds  ограничение по курсам; null — все курсы
     * @return array{
     *     portal: array<int, int>,
     *     by_course: array<int, array<int, int>>
     * }
     */
    public function timestampsForLearners(array $learnerIds, ?array $courseIds = null): array
    {
        $learnerIds = array_values(array_unique(array_filter(array_map('intval', $learnerIds))));
        if ($learnerIds === []) {
            return ['portal' => [], 'by_course' => []];
        }

        $byCourse = [];
        foreach ($learnerIds as $lid) {
            $byCourse[$lid] = [];
        }

        if (Schema::hasTable('course_enrollments')) {
            $q = DB::table('course_enrollments')
                ->select(['learner_id', 'course_id', 'last_seen_at', 'started_at', 'updated_at'])
                ->whereIn('learner_id', $learnerIds);
            if (is_array($courseIds)) {
                $q->whereIn('course_id', $courseIds);
            }
            foreach ($q->get() as $row) {
                $this->mergeCourseTs(
                    $byCourse,
                    (int) $row->learner_id,
                    (int) $row->course_id,
                    self::maxTsFromColumns($row, ['last_seen_at', 'started_at', 'updated_at']),
                );
            }
        }

        if (Schema::hasTable('module_progress')) {
            $q = DB::table('module_progress')
                ->select(['learner_id', 'course_id', 'updated_at'])
                ->whereIn('learner_id', $learnerIds)
                ->whereNotNull('updated_at');
            if (is_array($courseIds)) {
                $q->whereIn('course_id', $courseIds);
            }
            foreach ($q->get() as $row) {
                $this->mergeCourseTs(
                    $byCourse,
                    (int) $row->learner_id,
                    (int) $row->course_id,
                    self::maxTsFromColumns($row, ['updated_at']),
                );
            }
        }

        if (Schema::hasTable('final_lab_results')) {
            $q = DB::table('final_lab_results')
                ->select(['learner_id', 'course_id', 'certificate_issued_at', 'completed_at', 'updated_at'])
                ->whereIn('learner_id', $learnerIds);
            if (is_array($courseIds)) {
                $q->whereIn('course_id', $courseIds);
            }
            foreach ($q->get() as $row) {
                $this->mergeCourseTs(
                    $byCourse,
                    (int) $row->learner_id,
                    (int) $row->course_id,
                    self::maxTsFromColumns($row, ['certificate_issued_at', 'completed_at', 'updated_at']),
                );
            }
        }

        $portalExtras = [];
        if (Schema::hasTable('portal_activity_events')) {
            $q = DB::table('portal_activity_events')
                ->select(['learner_id', 'occurred_at'])
                ->whereIn('learner_id', $learnerIds)
                ->whereNotNull('occurred_at');
            foreach ($q->get() as $row) {
                $ts = self::maxTsFromColumns($row, ['occurred_at']);
                if ($ts > 0) {
                    $lid = (int) $row->learner_id;
                    $portalExtras[$lid] = max($portalExtras[$lid] ?? 0, $ts);
                }
            }
        }

        $portal = [];
        foreach ($byCourse as $lid => $perCourse) {
            $portal[$lid] = max(
                $perCourse === [] ? 0 : max($perCourse),
                $portalExtras[$lid] ?? 0,
            );
        }

        return ['portal' => $portal, 'by_course' => $byCourse];
    }

    public function timestampForLearnerOnCourse(int $learnerId, int $courseId): int
    {
        $pack = $this->timestampsForLearners([$learnerId], [$courseId]);

        return (int) ($pack['by_course'][$learnerId][$courseId] ?? $pack['portal'][$learnerId] ?? 0);
    }

    /**
     * @param  array<int, array<int, int>>  $byCourse
     */
    private function mergeCourseTs(array &$byCourse, int $learnerId, int $courseId, int $ts): void
    {
        if ($ts <= 0 || $courseId < 1) {
            return;
        }
        if (! isset($byCourse[$learnerId])) {
            $byCourse[$learnerId] = [];
        }
        $byCourse[$learnerId][$courseId] = max($byCourse[$learnerId][$courseId] ?? 0, $ts);
    }

    /**
     * @param  object|array<string, mixed>  $row
     * @param  list<string>  $columns
     */
    private static function maxTsFromColumns(object|array $row, array $columns): int
    {
        $max = 0;
        foreach ($columns as $col) {
            $v = is_array($row) ? ($row[$col] ?? null) : ($row->{$col} ?? null);
            if ($v === null || $v === '') {
                continue;
            }
            if ($v instanceof DateTimeInterface) {
                $t = $v->getTimestamp();
            } else {
                $t = strtotime((string) $v) ?: 0;
            }
            $max = max($max, $t);
        }

        return $max;
    }
}
