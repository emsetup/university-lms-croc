<?php

namespace App\Services;

use App\Models\FinalLabResult;
use App\Models\Learner;
use App\Models\ModuleProgress;
use App\Support\CourseModuleMeta;

/**
 * Баллы и прогресс по модулям. Число модулей берётся из ключей config('course.modules').
 */
final class CourseScoringService
{
    public const PASS_THRESHOLD = 70;

    public const THEORY_QUIZ_TIME_LIMIT_MINUTES = 30;

    public const THEORY_QUIZ_RETAKE_PENALTY_POINTS = 10;

    public const MODULE_EXAM_TIME_LIMIT_MINUTES = 60;

    public const MODULE_EXAM_MAX_ATTEMPTS = 2;

    public const MODULE_EXAM_RETAKE_PENALTY_POINTS = 10;

    public const MODULE_EXAM_BREAKDOWN_VISIBLE_MINUTES = 30;

    public const MODULE_SCORE_WEIGHT_THEORY_QUIZ = 0.25;

    public const MODULE_SCORE_WEIGHT_PRACTICE = 0.25;

    public const MODULE_SCORE_WEIGHT_EXAM = 0.50;

    public const MAX_POINTS_PER_MODULE = 100;

    public const MAX_FINAL_LAB_POINTS = 100;

    public static function moduleCount(): int
    {
        $m = config('course.modules');

        return is_array($m) ? max(1, count($m)) : 9;
    }

    /** @deprecated Используйте moduleCount() — останется для совместимости со старыми шаблонами. */
    public const MODULE_COUNT = 9;

    public function maxTotalModulePoints(): int
    {
        return self::moduleCount() * self::MAX_POINTS_PER_MODULE;
    }

    public function moduleProgressPercent(ModuleProgress $p): int
    {
        $mid = (int) $p->module_id;
        $skipPractice = ! empty(CourseModuleMeta::resolved($mid)['skip_practice']);
        $parts = 0;
        $done = 0;
        if ($p->theory_read_at) {
            $parts++;
            $done++;
        }
        if ($p->theory_quiz_passed) {
            $parts++;
            $done++;
        }
        if (! $skipPractice) {
            $parts++;
            if ($p->practice_done_at) {
                $done++;
            }
        }
        if ($p->module_exam_passed) {
            $parts++;
            $done++;
        }
        if ($parts === 0) {
            return 0;
        }

        return (int) round(100 * $done / $parts);
    }

    public function allModulesComplete(Learner $learner): bool
    {
        foreach ($this->moduleIdRange() as $id) {
            $p = $learner->progressExisting($id);
            if ($p === null || ! $p->module_exam_passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<int>
     */
    private function moduleIdRange(): array
    {
        $m = config('course.modules');
        if (! is_array($m) || $m === []) {
            return range(1, 9);
        }
        $ids = array_map('intval', array_keys($m));
        sort($ids);

        return array_values($ids);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function moduleReport(Learner $learner): array
    {
        $out = [];
        foreach ($this->moduleIdRange() as $id) {
            $p = $learner->progressExisting($id);
            $skipPractice = ! empty(CourseModuleMeta::resolved($id)['skip_practice']);
            $out[] = [
                'module_id' => $id,
                'points' => $this->modulePointsRow($id, $p),
                'theory_quiz_pct' => $p ? (int) $p->theory_quiz_best_score : 0,
                'practice_pct' => $skipPractice ? null : ($p ? (int) ($p->practice_lab_percent ?? 0) : 0),
                'exam_pct' => $p ? (int) $p->module_exam_best_score : 0,
                'difficulties' => $p ? ($p->difficulty_flags ?? []) : [],
                'skip_practice' => $skipPractice,
            ];
        }

        return $out;
    }

    /** Баллы за модуль (0…100) по текущему прогрессу — для хаба и отчётов. */
    public function modulePointsForProgress(ModuleProgress $p): int
    {
        return $this->modulePointsRow((int) $p->module_id, $p);
    }

    private function modulePointsRow(int $moduleId, ?ModuleProgress $p): int
    {
        if ($p === null) {
            return 0;
        }
        $skipPractice = ! empty(CourseModuleMeta::resolved($moduleId)['skip_practice']);
        $tq = (int) $p->theory_quiz_best_score;
        $pr = $skipPractice ? 100 : (int) ($p->practice_lab_percent ?? 0);
        $ex = (int) $p->module_exam_best_score;

        if ($skipPractice) {
            $raw = self::MODULE_SCORE_WEIGHT_THEORY_QUIZ * $tq
                + self::MODULE_SCORE_WEIGHT_EXAM * $ex;
            $norm = self::MODULE_SCORE_WEIGHT_THEORY_QUIZ + self::MODULE_SCORE_WEIGHT_EXAM;

            return (int) round(self::MAX_POINTS_PER_MODULE * ($norm > 0 ? $raw / $norm : 0) / 100);
        }

        $raw = self::MODULE_SCORE_WEIGHT_THEORY_QUIZ * $tq
            + self::MODULE_SCORE_WEIGHT_PRACTICE * $pr
            + self::MODULE_SCORE_WEIGHT_EXAM * $ex;

        return (int) round(self::MAX_POINTS_PER_MODULE * $raw / 100);
    }

    public function totalModulePoints(Learner $learner): int
    {
        $sum = 0;
        foreach ($this->moduleReport($learner) as $row) {
            $sum += (int) ($row['points'] ?? 0);
        }

        return $sum;
    }

    public function totalModulePointsSafe(Learner $learner): int
    {
        return min($this->maxTotalModulePoints(), $this->totalModulePoints($learner));
    }

    public function grandTotal(Learner $learner): int
    {
        $final = $learner->finalLabResult;

        return $this->totalModulePoints($learner) + $this->finalLabPoints($final);
    }

    public function grandTotalSafe(Learner $learner): int
    {
        return min(
            $this->maxTotalModulePoints() + self::MAX_FINAL_LAB_POINTS,
            max(0, $this->grandTotal($learner))
        );
    }

    public function finalLabPoints(?FinalLabResult $final): int
    {
        if ($final === null) {
            return 0;
        }

        return (int) min(self::MAX_FINAL_LAB_POINTS, max(0, (int) $final->best_score));
    }
}
