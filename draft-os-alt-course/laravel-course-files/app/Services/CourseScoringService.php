<?php

namespace App\Services;

use App\Models\CourseModule;
use App\Models\Course;
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
        private LearnerContentVisibilityService $visibility,
    ) {}

    public const PASS_THRESHOLD = 70;

    public const THEORY_QUIZ_TIME_LIMIT_MINUTES = 30;

    public const THEORY_QUIZ_RETAKE_PENALTY_POINTS = 10;

    public const MODULE_EXAM_TIME_LIMIT_MINUTES = 60;

    public const MODULE_EXAM_MAX_ATTEMPTS = 2;

    public const MODULE_EXAM_RETAKE_PENALTY_POINTS = 10;

    public const THEORY_QUIZ_BREAKDOWN_VISIBLE_MINUTES = 5;

    public const MODULE_EXAM_BREAKDOWN_VISIBLE_MINUTES = 5;

    /** Sentinel in section/bank settings: разбор без ограничения по времени. */
    public const BREAKDOWN_VISIBLE_UNLIMITED = -1;

    /**
     * Нормализация минут видимости разбора.
     * null = без ограничения; 0 = не показывать; >0 = минуты.
     */
    public static function normalizeBreakdownVisibleMinutes(mixed $value, int $fallback): ?int
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        if (! is_numeric($value)) {
            return $fallback;
        }
        $n = (int) $value;
        if ($n < 0) {
            return null;
        }

        return $n;
    }

    /**
     * Метка окончания окна разбора (unix ts) или null при безлимите; 0 — разбор скрыт.
     */
    public static function breakdownVisibleUntilTimestamp(?int $minutes): ?int
    {
        if ($minutes === null) {
            return null;
        }
        if ($minutes <= 0) {
            return 0;
        }

        return now()->addMinutes($minutes)->getTimestamp();
    }

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
        $courseId = $courseId ?? (int) session('course_id', 0);
        if ($courseId > 0 && Schema::hasTable('course_modules')) {
            $legacyAlt = (bool) Course::query()->find($courseId)?->isLegacyAltCourse();
            $total = 0;
            $learnerId = (int) session('learner_id', 0);
            $modules = $learnerId > 0
                ? $this->visibility->visibleModulesForLearner($courseId, $learnerId)
                : $this->courseModules->orderedModulesForCourse($courseId);
            if ($modules->isNotEmpty()) {
                foreach ($modules as $mod) {
                    $mid = (int) $mod->id;
                    $idx = $mod->effectiveContentIndex();
                    if ($this->courseSections->useDbSectionsForModule($mid)) {
                        if ($this->courseSections->moduleScoreWeights($mid, $idx, $legacyAlt) !== []) {
                            $total += self::MAX_POINTS_PER_MODULE;
                        }
                    } else {
                        $total += self::MAX_POINTS_PER_MODULE;
                    }
                }

                return $total;
            }
        }

        return self::moduleCount($courseId) * self::MAX_POINTS_PER_MODULE;
    }

    /**
     * @return array{
     *     max_module_points: int,
     *     max_final_lab_points: int,
     *     max_course_points: int,
     *     final_lab_enabled: bool,
     *     has_scoring: bool,
     *     module_count: int,
     *     scoring_hint: string
     * }
     */
    public function courseScoringReportMeta(int $courseId): array
    {
        $maxModule = $this->maxTotalModulePoints($courseId);
        $maxFinal = $this->maxFinalLabPoints($courseId);
        $maxGrand = $maxModule + $maxFinal;

        return [
            'max_module_points' => $maxModule,
            'max_final_lab_points' => $maxFinal,
            'max_course_points' => $maxGrand,
            'final_lab_enabled' => $maxFinal > 0,
            'has_scoring' => $maxGrand > 0,
            'module_count' => self::moduleCount($courseId),
            'scoring_hint' => $this->courseScoringHint($courseId, $maxModule, $maxFinal),
        ];
    }

    private function courseScoringHint(int $courseId, int $maxModule, int $maxFinal): string
    {
        if ($maxModule <= 0 && $maxFinal <= 0) {
            return 'В курсе нет оцениваемых этапов — баллы не начисляются (например, только опросы или теория без тестов).';
        }

        $legendParts = [];
        $legacyAlt = (bool) Course::query()->find($courseId)?->isLegacyAltCourse();
        foreach ($this->courseModules->orderedModulesForCourse($courseId) as $mod) {
            $legend = $this->courseSections->moduleScoreWeightLegend(
                (int) $mod->id,
                $mod->effectiveContentIndex(),
                $legacyAlt
            );
            if ($legend !== []) {
                foreach ($legend as $item) {
                    $legendParts[] = (string) $item['label'].' '.(int) $item['pct'].'%';
                }
                break;
            }
        }

        $parts = [];
        if ($maxModule > 0) {
            $weightLine = $legendParts !== [] ? implode(' · ', $legendParts) : 'оцениваемые этапы модуля';
            $parts[] = sprintf(
                'Баллы за модуль: взвешенное среднее (%s), максимум %d за модуль; сумма по модулям — до %d',
                $weightLine,
                self::MAX_POINTS_PER_MODULE,
                $maxModule
            );
        }
        if ($maxFinal > 0) {
            $parts[] = sprintf('финальная лаборатория — до %d', $maxFinal);
        }
        $parts[] = sprintf('«Итого курс» — максимум %d', $maxModule + $maxFinal);

        return implode('. ', $parts).'.';
    }

    private function finalLabEnabledForCourseId(?int $courseId): bool
    {
        if ($courseId === null || $courseId < 1 || ! Schema::hasTable('courses')) {
            return true;
        }
        if (! Schema::hasColumn('courses', 'final_lab_enabled')) {
            return true;
        }

        $v = Course::query()->whereKey($courseId)->value('final_lab_enabled');

        return (bool) $v;
    }

    public function maxFinalLabPoints(?int $courseId = null): int
    {
        return $this->finalLabEnabledForCourseId($courseId) ? self::MAX_FINAL_LAB_POINTS : 0;
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
        if ($p->theory_quiz_passed
            || ($cmId > 0 && $this->courseSections->isTheoryQuizEffectivelyPassed($p, $cmId))
            || ($cmId < 1 && (int) $p->theory_quiz_best_score >= self::PASS_THRESHOLD)) {
            $done++;
        }
        if (! $skipPractice && $p->practice_done_at) {
            $done++;
        }
        if ($p->module_exam_passed
            || ($cmId > 0 && $this->courseSections->isModuleExamEffectivelyPassed($p, $cmId))
            || ($cmId < 1 && (int) $p->module_exam_best_score >= self::PASS_THRESHOLD)) {
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
        $ids = $this->visibility->visibleModuleIdsForLearner($courseId, (int) $learner->id);
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
        $visibleIds = $this->visibility->visibleModuleIdsForLearner($courseId, (int) $learner->id);
        foreach ($this->courseModules->orderedModulesForCourse($courseId) as $mod) {
            if (! in_array((int) $mod->id, $visibleIds, true)) {
                continue;
            }
            $p = $learner->progressExisting((int) $mod->id, $courseId);
            $idx = $mod->effectiveContentIndex();
            $mid = (int) $mod->id;
            $legacyAlt = $mod->loadMissing('course:id,slug')->course?->isLegacyAltCourse() ?? false;
            $skipPractice = $this->courseSections->isPracticeWaived($mid, $idx, $legacyAlt);
            $parts = $this->courseSections->assessmentPartsForModule($p, $mid, $idx, $legacyAlt);
            $out[] = [
                'module_id' => (int) $mod->id,
                'module_sequence' => $this->courseModules->sequenceForModule($mod),
                'course_module_id' => (int) $mod->id,
                'title' => (string) $mod->title,
                'letter' => (string) ($mod->letter ?? ''),
                'content_source_index' => $idx,
                'points' => $this->modulePointsRow($idx, $p, $mid, $legacyAlt),
                'parts' => $parts,
                'weight_legend' => $this->courseSections->moduleScoreWeightLegend($mid, $idx, $legacyAlt),
                'theory_quiz_pct' => self::partPctByLegacyKey($parts, 'theory_quiz'),
                'practice_pct' => self::partPctByLegacyKey($parts, 'practice'),
                'exam_pct' => self::partPctByLegacyKey($parts, 'module_exam'),
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
        $legacyAlt = $cm
            ? ($cm->relationLoaded('course') ? ($cm->course?->isLegacyAltCourse() ?? false) : ($cm->loadMissing('course:id,slug')->course?->isLegacyAltCourse() ?? false))
            : false;

        return $this->modulePointsRow($contentIdx, $p, $cmId > 0 ? $cmId : null, $legacyAlt);
    }

    private function modulePointsRow(int $contentSourceIndex, ?ModuleProgress $p, ?int $courseModuleId = null, bool $legacyAlt = true): int
    {
        if ($p === null) {
            return 0;
        }
        $cmId = $courseModuleId ?? (int) $p->course_module_id;
        if ($cmId > 0 && $this->courseSections->useDbSectionsForModule($cmId)) {
            return $this->courseSections->modulePointsFromProgress($p, $cmId, $contentSourceIndex, $legacyAlt);
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

        $finalPts = $this->finalLabEnabledForCourseId($courseIdOverride) ? $this->finalLabPoints($final) : 0;

        return $this->totalModulePoints($learner, $courseIdOverride) + $finalPts;
    }

    public function grandTotalSafe(Learner $learner, ?int $courseIdOverride = null, ?FinalLabResult $finalForPoints = null): int
    {
        return min(
            $this->maxTotalModulePoints($courseIdOverride) + $this->maxFinalLabPoints($courseIdOverride),
            max(0, $this->grandTotal($learner, $courseIdOverride, $finalForPoints))
        );
    }

    public function certificateCoursePercent(Learner $learner, ?int $courseIdOverride = null, ?FinalLabResult $finalForPoints = null): int
    {
        $max = $this->maxTotalModulePoints($courseIdOverride) + $this->maxFinalLabPoints($courseIdOverride);
        if ($max <= 0) {
            return 0;
        }
        $g = $this->grandTotalSafe($learner, $courseIdOverride, $finalForPoints);

        return (int) max(0, min(100, (int) round(100 * $g / $max)));
    }

    /**
     * Возвращает уровень сертификата. Если уровень не найден (результат ниже минимального),
     * сертификат не выдаётся и возвращается null.
     *
     * @return array{key: string, label: string}|null
     */
    public function certificateTier(int $coursePercent, ?int $courseIdOverride = null): ?array
    {
        // Персональная градация по курсу (если задана)
        if ($courseIdOverride !== null && $courseIdOverride > 0 && \Illuminate\Support\Facades\Schema::hasTable('courses')) {
            $course = \App\Models\Course::query()->find($courseIdOverride);
            if ($course && $course->certificate_enabled && is_array($course->certificate_tiers) && $course->certificate_tiers !== []) {
                $tiers = array_values(array_filter($course->certificate_tiers, static function ($row) {
                    return is_array($row)
                        && isset($row['min_percent'], $row['label'])
                        && is_numeric($row['min_percent'])
                        && trim((string) $row['label']) !== '';
                }));
                usort($tiers, static fn ($a, $b) => ((int) $b['min_percent']) <=> ((int) $a['min_percent']));
                foreach ($tiers as $t) {
                    if ($coursePercent >= (int) $t['min_percent']) {
                        $k = strtolower(preg_replace('/[^a-z0-9\-_]/', '', (string) ($t['key'] ?? '')));
                        if ($k === '') {
                            $k = 'tier';
                        }
                        return ['key' => $k, 'label' => trim((string) $t['label'])];
                    }
                }
                // Ни один уровень не подошёл — сертификат не выдаётся.
                return null;
            }
        }

        // Fallback (как было раньше)
        if ($coursePercent >= 90) {
            return ['key' => 'expert', 'label' => 'ALT Linux Administrator — Expert'];
        }
        if ($coursePercent >= 70) {
            return ['key' => 'administrator', 'label' => 'ALT Linux Administrator'];
        }

        return null;
    }

    public function finalLabPoints(?FinalLabResult $final): int
    {
        if ($final === null) {
            return 0;
        }

        return (int) min(self::MAX_FINAL_LAB_POINTS, max(0, (int) $final->best_score));
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     */
    public static function partPctByLegacyKey(array $parts, string $legacyKey): ?int
    {
        foreach ($parts as $part) {
            if (($part['legacy_key'] ?? '') === $legacyKey) {
                return (int) ($part['pct'] ?? 0);
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{color_key: string, label: string, pct: int}>
     */
    public static function buildSummaryParts(array $rows): array
    {
        /** @var array<string, array{color_key: string, label: string, sum: int, count: int}> $acc */
        $acc = [];
        foreach ($rows as $row) {
            foreach ((array) ($row['parts'] ?? []) as $part) {
                $colorKey = (string) ($part['color_key'] ?? '');
                if ($colorKey === '') {
                    continue;
                }
                if (! isset($acc[$colorKey])) {
                    $acc[$colorKey] = [
                        'color_key' => $colorKey,
                        'label' => (string) ($part['label'] ?? $colorKey),
                        'sum' => 0,
                        'count' => 0,
                    ];
                }
                $acc[$colorKey]['sum'] += (int) ($part['pct'] ?? 0);
                $acc[$colorKey]['count']++;
            }
        }

        $out = [];
        foreach ($acc as $item) {
            $out[] = [
                'color_key' => $item['color_key'],
                'label' => $item['label'],
                'pct' => $item['count'] > 0 ? (int) round($item['sum'] / $item['count']) : 0,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @return array{weak_key: string, any_below_pass: bool}
     */
    public static function assessPartsRisk(array $parts, int $passThreshold = self::PASS_THRESHOLD): array
    {
        $weakKey = '';
        $minPct = 101;
        $anyBelowPass = false;
        foreach ($parts as $part) {
            $pct = (int) ($part['pct'] ?? 0);
            $colorKey = (string) ($part['color_key'] ?? '');
            if ($pct < $minPct && $colorKey !== '') {
                $minPct = $pct;
                $weakKey = $colorKey;
            }
            if ($pct < $passThreshold) {
                $anyBelowPass = true;
            }
        }

        return [
            'weak_key' => $weakKey,
            'any_below_pass' => $anyBelowPass,
        ];
    }

    /**
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   summary_parts: list<array{color_key: string, label: string, pct: int}>,
     *   total_points: int,
     *   max_points: int,
     *   pass_threshold: int
     * }
     */
    public function learnerAssessmentSnapshot(Learner $learner): array
    {
        $courseId = (int) session('course_id', 0);
        $rows = [];

        if ($courseId > 0 && Schema::hasTable('course_modules') && $this->courseModules->moduleCountForCourse($courseId) > 0) {
            foreach ($this->courseModules->orderedModulesForCourse($courseId) as $mod) {
                $id = (int) $mod->id;
                $meta = $this->courseModules->displayMeta($mod);
                $p = $learner->progressExisting($id, $courseId);
                $idx = $mod->effectiveContentIndex();
                $legacyAlt = $mod->loadMissing('course:id,slug')->course?->isLegacyAltCourse() ?? false;
                $skipPractice = $this->courseSections->isPracticeWaived($id, $idx, $legacyAlt);
                $parts = $this->courseSections->assessmentPartsForModule($p, $id, $idx, $legacyAlt);
                $risk = self::assessPartsRisk($parts);

                $rows[] = [
                    'module_id' => $id,
                    'module_sequence' => $this->courseModules->sequenceForModule($mod),
                    'title' => (string) ($meta['title'] ?? ('Модуль '.$id)),
                    'letter' => (string) ($meta['letter'] ?? (string) $id),
                    'skip_practice' => $skipPractice,
                    'theory_quiz_pct' => self::partPctByLegacyKey($parts, 'theory_quiz'),
                    'practice_pct' => self::partPctByLegacyKey($parts, 'practice'),
                    'exam_pct' => self::partPctByLegacyKey($parts, 'module_exam'),
                    'points' => $this->modulePointsRow($idx, $p, $id, $legacyAlt),
                    'weak_key' => $risk['weak_key'],
                    'any_below_pass' => $risk['any_below_pass'],
                    'difficulties' => $p ? (array) ($p->difficulty_flags ?? []) : [],
                    'parts' => $parts,
                    'weight_legend' => $this->courseSections->moduleScoreWeightLegend($id, $idx, $legacyAlt),
                ];
            }

            return [
                'rows' => $rows,
                'summary_parts' => self::buildSummaryParts($rows),
                'total_points' => $this->totalModulePoints($learner, $courseId),
                'max_points' => $this->maxTotalModulePoints($courseId),
                'pass_threshold' => self::PASS_THRESHOLD,
            ];
        }

        return [
            'rows' => [],
            'summary_parts' => [],
            'total_points' => $this->totalModulePoints($learner, $courseId),
            'max_points' => $this->maxTotalModulePoints($courseId),
            'pass_threshold' => self::PASS_THRESHOLD,
        ];
    }
}
