<?php

namespace App\Services;

use App\Models\CourseModule;
use App\Models\FinalLabResult;
use App\Models\Learner;
use App\Models\ModuleProgress;
use App\Support\CourseModuleMeta;
use Illuminate\Support\Facades\Schema;

/**
 * Баллы и прогресс по модулям. Число модулей — из БД (course_modules) при наличии, иначе из config('course.modules').
 */
final class CourseScoringService
{
    public function __construct(
        private CourseSectionService $courseSections,
        private CourseModuleService $courseModules,
    ) {}

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

    public static function moduleCount(?int $courseId = null): int
    {
        $courseId = $courseId ?? (int) session('course_id', 0);
        if ($courseId > 0 && Schema::hasTable('course_modules')) {
            $n = (int) CourseModule::query()->where('course_id', $courseId)->count();
            if ($n > 0) {
                return $n;
            }
        }
        $m = config('course.modules');

        return is_array($m) ? max(1, count($m)) : 9;
    }

    /** @deprecated Используйте moduleCount() — останется для совместимости со старыми шаблонами. */
    public const MODULE_COUNT = 9;

    public function maxTotalModulePoints(?int $courseId = null): int
    {
        return self::moduleCount($courseId) * self::MAX_POINTS_PER_MODULE;
    }

    public function moduleProgressPercent(ModuleProgress $p): int
    {
        $cmId = (int) $p->course_module_id;
        $courseId = (int) $p->course_id;
        $cm = $this->courseModules->findForCourse($courseId, $cmId);
        $contentIdx = $cm?->effectiveContentIndex() ?? 1;

        if ($cmId > 0 && $this->courseSections->useDbSectionsForModule($cmId)) {
            $legacyAlt = $cm ? ($cm->relationLoaded('course') ? ($cm->course?->isLegacyAltCourse() ?? false) : ($cm->loadMissing('course:id,slug')->course?->isLegacyAltCourse() ?? false)) : false;

            return $this->courseSections->moduleProgressPercent($p, $cmId, $contentIdx, (bool) $legacyAlt);
        }

        $skipPractice = CourseModuleMeta::shouldSkipPractice($contentIdx);
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

    public function allModulesComplete(Learner $learner, ?int $courseIdOverride = null): bool
    {
        $courseId = $courseIdOverride ?? (int) session('course_id', 0);
        if ($courseId < 1 || ! Schema::hasTable('course_modules')) {
            return false;
        }
        $ids = $this->courseModules->orderedModuleIdsForCourse($courseId);
        if ($ids === []) {
            return false;
        }
        foreach ($ids as $courseModuleId) {
            $p = $learner->progressExisting($courseModuleId, $courseId);
            if ($p === null || ! $p->module_exam_passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function moduleReport(Learner $learner, ?int $courseIdOverride = null): array
    {
        $courseId = $courseIdOverride ?? (int) session('course_id', 0);
        $out = [];
        if ($courseId < 1 || ! Schema::hasTable('course_modules')) {
            return $out;
        }
        foreach ($this->courseModules->orderedModulesForCourse($courseId) as $mod) {
            $p = $learner->progressExisting((int) $mod->id, $courseId);
            $idx = $mod->effectiveContentIndex();
            $skipPractice = CourseModuleMeta::shouldSkipPractice($idx);
            $out[] = [
                'module_id' => (int) $mod->id,
                'course_module_id' => (int) $mod->id,
                'title' => (string) $mod->title,
                'letter' => (string) ($mod->letter ?? ''),
                'content_source_index' => $idx,
                'points' => $this->modulePointsRow($idx, $p),
                'theory_quiz_pct' => $p ? (int) $p->theory_quiz_best_score : 0,
                'practice_pct' => $skipPractice ? null : ($p ? (int) ($p->practice_lab_percent ?? 0) : 0),
                'exam_pct' => $p ? (int) $p->module_exam_best_score : 0,
                'difficulties' => $p ? ($p->difficulty_flags ?? []) : [],
                'skip_practice' => $skipPractice,
            ];
        }

        return $out;
    }

    public function modulePointsForProgress(ModuleProgress $p): int
    {
        $cmId = (int) $p->course_module_id;
        $courseId = (int) $p->course_id;
        $cm = $this->courseModules->findForCourse($courseId, $cmId);
        $contentIdx = $cm?->effectiveContentIndex() ?? 1;

        return $this->modulePointsRow($contentIdx, $p);
    }

    private function modulePointsRow(int $contentSourceIndex, ?ModuleProgress $p): int
    {
        if ($p === null) {
            return 0;
        }
        $skipPractice = CourseModuleMeta::shouldSkipPractice($contentSourceIndex);
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

    public function totalModulePoints(Learner $learner, ?int $courseIdOverride = null): int
    {
        $sum = 0;
        foreach ($this->moduleReport($learner, $courseIdOverride) as $row) {
            $sum += (int) ($row['points'] ?? 0);
        }

        return $sum;
    }

    public function totalModulePointsSafe(Learner $learner, ?int $courseIdOverride = null): int
    {
        return min($this->maxTotalModulePoints($courseIdOverride), $this->totalModulePoints($learner, $courseIdOverride));
    }

    public function grandTotal(Learner $learner, ?int $courseIdOverride = null, ?FinalLabResult $finalForPoints = null): int
    {
        $final = $finalForPoints ?? $learner->finalLabResult;

        return $this->totalModulePoints($learner, $courseIdOverride) + $this->finalLabPoints($final);
    }

    public function grandTotalSafe(Learner $learner, ?int $courseIdOverride = null, ?FinalLabResult $finalForPoints = null): int
    {
        return min(
            $this->maxTotalModulePoints($courseIdOverride) + self::MAX_FINAL_LAB_POINTS,
            max(0, $this->grandTotal($learner, $courseIdOverride, $finalForPoints))
        );
    }

    public function certificateCoursePercent(Learner $learner, ?int $courseIdOverride = null, ?FinalLabResult $finalForPoints = null): int
    {
        $max = $this->maxTotalModulePoints($courseIdOverride) + self::MAX_FINAL_LAB_POINTS;
        if ($max <= 0) {
            return 0;
        }
        $g = $this->grandTotalSafe($learner, $courseIdOverride, $finalForPoints);

        return (int) max(0, min(100, (int) round(100 * $g / $max)));
    }

    /**
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
        $courseId = (int) session('course_id', 0);
        $rows = [];
        $sumTq = 0;
        $sumEx = 0;
        $sumPr = 0;
        $prN = 0;

        if ($courseId > 0 && Schema::hasTable('course_modules') && $this->courseModules->moduleCountForCourse($courseId) > 0) {
            $mods = $this->courseModules->orderedModulesForCourse($courseId);
            $n = max(1, $mods->count());
            foreach ($mods as $mod) {
                $id = (int) $mod->id;
                $meta = $this->courseModules->displayMeta($mod);
                $p = $learner->progressExisting($id, $courseId);
                $idx = $mod->effectiveContentIndex();
                $skipPractice = CourseModuleMeta::shouldSkipPractice($idx);
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
                    'points' => $this->modulePointsRow($idx, $p),
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
                'total_points' => $this->totalModulePoints($learner, $courseId),
                'max_points' => $this->maxTotalModulePoints($courseId),
                'pass_threshold' => self::PASS_THRESHOLD,
                'weight_tq_pct' => (int) round(self::MODULE_SCORE_WEIGHT_THEORY_QUIZ * 100),
                'weight_pr_pct' => (int) round(self::MODULE_SCORE_WEIGHT_PRACTICE * 100),
                'weight_ex_pct' => (int) round(self::MODULE_SCORE_WEIGHT_EXAM * 100),
            ];
        }

        return [
            'rows' => [],
            'averages' => [
                'tq' => 0,
                'pr' => null,
                'ex' => 0,
            ],
            'total_points' => $this->totalModulePoints($learner, $courseId),
            'max_points' => $this->maxTotalModulePoints($courseId),
            'pass_threshold' => self::PASS_THRESHOLD,
            'weight_tq_pct' => (int) round(self::MODULE_SCORE_WEIGHT_THEORY_QUIZ * 100),
            'weight_pr_pct' => (int) round(self::MODULE_SCORE_WEIGHT_PRACTICE * 100),
            'weight_ex_pct' => (int) round(self::MODULE_SCORE_WEIGHT_EXAM * 100),
        ];
    }
}
