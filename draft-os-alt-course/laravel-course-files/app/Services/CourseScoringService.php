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
        $skipPractice = CourseModuleMeta::shouldSkipPractice($mid);
        // Всегда считаем по обязательным этапам модуля, а не по уже "появившимся":
        // иначе можно получить 100% до сдачи экзамена.
        $parts = $skipPractice ? 3 : 4;
        $done = 0;
        if ($p->theory_read_at) {
            $done++;
        }
        if ($p->theory_quiz_passed) {
            $done++;
        }
        if (! $skipPractice && $p->practice_done_at) {
            $done++;
        }
        if ($p->module_exam_passed) {
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
            $skipPractice = CourseModuleMeta::shouldSkipPractice($id);
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
        $skipPractice = CourseModuleMeta::shouldSkipPractice($moduleId);
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

    /**
     * Доля набранных баллов от максимума курса (модули + финальная), 0–100 — для уровня на сертификате.
     */
    public function certificateCoursePercent(Learner $learner): int
    {
        $max = $this->maxTotalModulePoints() + self::MAX_FINAL_LAB_POINTS;
        if ($max <= 0) {
            return 0;
        }
        $g = $this->grandTotalSafe($learner);

        return (int) max(0, min(100, (int) round(100 * $g / $max)));
    }

    /**
     * Уровень сертификата по сводному проценту курса.
     *
     * @return array{key: string, label: string}
     */
    public function certificateTier(int $coursePercent): array
    {
        if ($coursePercent >= 90) {
            return ['key' => 'expert', 'label' => 'ALT Linux Administrator — Expert'];
        }
        if ($coursePercent >= 70) {
            return ['key' => 'administrator', 'label' => 'ALT Linux Administrator'];
        }

        return ['key' => 'retake', 'label' => 'Пересдача'];
    }

    public function finalLabPoints(?FinalLabResult $final): int
    {
        if ($final === null) {
            return 0;
        }

        return (int) min(self::MAX_FINAL_LAB_POINTS, max(0, (int) $final->best_score));
    }

    /**
     * Данные для модального окна и страницы «оценка по модулям»: проценты по этапам, баллы, слабое место в модуле.
     *
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   averages: array{tq: int, pr: int|null, ex: int},
     *   total_points: int,
     *   max_points: int,
     *   pass_threshold: int,
     *   weight_tq_pct: int,
     *   weight_pr_pct: int,
     *   weight_ex_pct: int
     * }
     */
    public function learnerAssessmentSnapshot(Learner $learner): array
    {
        $ids = $this->moduleIdRange();
        $n = max(1, count($ids));
        $rows = [];
        $sumTq = 0;
        $sumEx = 0;
        $sumPr = 0;
        $prN = 0;

        foreach ($ids as $id) {
            $meta = CourseModuleMeta::resolved($id);
            $p = $learner->progressExisting($id);
            $skipPractice = CourseModuleMeta::shouldSkipPractice($id);
            $tq = $p ? (int) $p->theory_quiz_best_score : 0;
            $pr = $skipPractice ? null : ($p ? (int) ($p->practice_lab_percent ?? 0) : 0);
            $ex = $p ? (int) $p->module_exam_best_score : 0;
            $sumTq += $tq;
            $sumEx += $ex;
            if ($pr !== null) {
                $sumPr += $pr;
                $prN++;
            }

            $parts = [['key' => 'tq', 'label' => 'Тест по теории', 'pct' => $tq]];
            if ($pr !== null) {
                $parts[] = ['key' => 'pr', 'label' => 'Практика', 'pct' => $pr];
            }
            $parts[] = ['key' => 'ex', 'label' => 'Итоговый тест', 'pct' => $ex];

            $weakKey = 'tq';
            $minPct = 101;
            foreach ($parts as $part) {
                if ($part['pct'] < $minPct) {
                    $minPct = $part['pct'];
                    $weakKey = $part['key'];
                }
            }
            $anyBelowPass = false;
            foreach ($parts as $part) {
                if ($part['pct'] < self::PASS_THRESHOLD) {
                    $anyBelowPass = true;
                    break;
                }
            }

            $rows[] = [
                'module_id' => $id,
                'title' => (string) ($meta['title'] ?? ('Модуль '.$id)),
                'letter' => (string) ($meta['letter'] ?? (string) $id),
                'skip_practice' => $skipPractice,
                'theory_quiz_pct' => $tq,
                'practice_pct' => $pr,
                'exam_pct' => $ex,
                'points' => $this->modulePointsRow($id, $p),
                'weak_key' => $weakKey,
                'any_below_pass' => $anyBelowPass,
                'tq_attempts' => $p ? (int) $p->theory_quiz_attempts : 0,
                'ex_attempts' => $p ? (int) $p->module_exam_attempts : 0,
                'difficulties' => $p ? (array) ($p->difficulty_flags ?? []) : [],
                'parts' => $parts,
            ];
        }

        return [
            'rows' => $rows,
            'averages' => [
                'tq' => (int) round($sumTq / $n),
                'pr' => $prN > 0 ? (int) round($sumPr / $prN) : null,
                'ex' => (int) round($sumEx / $n),
            ],
            'total_points' => $this->totalModulePoints($learner),
            'max_points' => $this->maxTotalModulePoints(),
            'pass_threshold' => self::PASS_THRESHOLD,
            'weight_tq_pct' => (int) round(self::MODULE_SCORE_WEIGHT_THEORY_QUIZ * 100),
            'weight_pr_pct' => (int) round(self::MODULE_SCORE_WEIGHT_PRACTICE * 100),
            'weight_ex_pct' => (int) round(self::MODULE_SCORE_WEIGHT_EXAM * 100),
        ];
    }
}
