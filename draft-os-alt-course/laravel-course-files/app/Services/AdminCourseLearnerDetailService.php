<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\Learner;
use App\Models\ModuleProgress;
use App\Models\PracticeSession;
use App\Support\DurationFormat;
use App\Support\LearnerDisplay;
use Illuminate\Support\Facades\Schema;

/**
 * Данные для split-панели «Обучающиеся» в админке курса (прогресс по модулям и разделам).
 */
final class AdminCourseLearnerDetailService
{
    private const SECTION_TYPE_LABEL = [
        CourseSection::TYPE_TEXT => 'Теория',
        CourseSection::TYPE_QUIZ => 'Тест',
        CourseSection::TYPE_PRACTICE => 'Практика',
        CourseSection::TYPE_EXAM => 'Экзамен',
        CourseSection::TYPE_SURVEY => 'Опрос',
    ];

    public function __construct(
        private CourseScoringService $scoring,
        private CourseModuleService $courseModules,
        private CourseSectionService $courseSections,
        private TeacherCourseAnalyticsService $analytics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildDetail(Learner $learner, Course $course): array
    {
        $courseId = (int) $course->id;
        $learner->loadMissing([
            'moduleProgresses' => fn ($q) => $q->where('course_id', $courseId),
            'finalLabResults' => fn ($q) => $q->where('course_id', $courseId),
        ]);

        $row = $this->analytics->rowForLearner($learner, $courseId);
        $legacyAlt = $course->isLegacyAltCourse();
        $tracked = (int) ($row['time_tracked']['total'] ?? 0);
        $spanDays = $row['time']['span_days'] ?? null;

        $modulesOut = [];
        $mods = $this->courseModules->orderedModulesForCourse($courseId);

        $sessions = collect();
        if (Schema::hasTable('practice_sessions')) {
            $q = PracticeSession::query()->where('learner_id', $learner->id);
            if (Schema::hasColumn('practice_sessions', 'course_id')) {
                $q->where('course_id', $courseId);
            }
            $sessions = $q->get()->keyBy('module_id');
        }

        $allowReset = app(PortalStaffAccess::class)->canResetLearnerProgressForCourse($courseId);

        $ordinal = 0;
        foreach ($mods as $mod) {
            $ordinal++;
            $mid = (int) $mod->id;
            $idx = $mod->effectiveContentIndex();
            $p = $learner->progressExisting($mid, $courseId);
            $points = $p ? $this->scoring->modulePointsForProgress($p) : 0;

            $secTheory = $p ? (int) ($p->seconds_theory ?? 0) : 0;
            $secTq = $p ? (int) ($p->seconds_theory_quiz ?? 0) : 0;
            $secPr = $p ? (int) ($p->seconds_practice ?? 0) : 0;
            $secEx = $p ? (int) ($p->seconds_module_exam ?? 0) : 0;
            $secMod = $secTheory + $secTq + $secPr + $secEx;

            $modPct = $p !== null
                ? $this->scoring->moduleProgressPercent($p)
                : 0;
            if ($this->courseSections->useDbSectionsForModule($mid)) {
                $modPct = $p !== null
                    ? $this->courseSections->moduleProgressPercent($p, $mid, $idx, $legacyAlt)
                    : 0;
            }

            $sections = $this->buildSectionsForModule($learner, $course, $courseId, $mod, $p, $idx, $legacyAlt, $sessions, $allowReset);

            $modulesOut[] = [
                'id' => $mid,
                'ordinal' => $ordinal,
                'title' => (string) $mod->title,
                'letter' => (string) ($mod->letter ?? ''),
                'points' => $points,
                'progress_percent' => $modPct,
                'seconds_tracked' => $secMod,
                'time_label' => DurationFormat::fromSeconds($secMod),
                'sections' => $sections,
                'reset_post_url' => route('admin.learners.course.learner.reset', [
                    'adminCourse' => $course->slug,
                    'learner' => $learner->id,
                    'courseModule' => $mid,
                ]),
            ];
        }

        $email = (string) ($learner->email ?? '');
        $fullName = LearnerDisplay::portalDisplayName($learner);

        return [
            'learner' => [
                'id' => (int) $learner->id,
                'email' => $email,
                'initials' => LearnerDisplay::initials($email, $fullName),
                'full_name' => $fullName,
            ],
            'summary' => [
                'modules_passed' => (int) ($row['modules_passed_count'] ?? 0),
                'module_total' => (int) ($row['module_count'] ?? max(1, count($modulesOut))),
                'points' => (int) ($row['grand_total'] ?? 0),
                'points_max' => (int) ($row['max_grand_total'] ?? 0),
                'percent' => (int) ($row['grand_total_percent'] ?? 0),
                'time_tracked_label' => DurationFormat::fromSeconds($tracked),
                'span_days' => $spanDays,
            ],
            'modules' => $modulesOut,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\PracticeSession>  $sessionsByModuleId
     * @return list<array<string, mixed>>
     */
    private function buildSectionsForModule(
        Learner $learner,
        Course $course,
        int $courseId,
        CourseModule $mod,
        ?ModuleProgress $p,
        int $contentIdx,
        bool $legacyAlt,
        $sessionsByModuleId,
        bool $allowReset = true
    ): array {
        $mid = (int) $mod->id;
        $out = [];

        if ($this->courseSections->useDbSectionsForModule($mid)) {
            $secs = CourseSection::query()
                ->where('course_module_id', $mid)
                ->where('is_enabled', true)
                ->orderBy('sort')
                ->orderBy('id')
                ->get();

            foreach ($secs as $sec) {
                $bk = $sec->backendStepKey();
                $complete = $p !== null && $this->courseSections->isStepComplete($p, $bk, $mid, $contentIdx, $legacyAlt);
                $pct = $complete ? 100 : 0;
                $resetStep = $this->resetStepForBackendKey($bk);
                $started = $this->sectionStarted($p, $bk, $mid, $courseId, $contentIdx, $legacyAlt, $sessionsByModuleId);
                $showReset = $allowReset && $resetStep !== null && $started;

                $hash = $this->anchorForBackendKey($bk);
                $viewUrl = route('admin.learners.course.learner.module', [
                    'adminCourse' => $course->slug,
                    'learner' => $learner->id,
                    'courseModule' => $mid,
                ]).$hash;

                $surveyCard = null;
                if ($sec->type === CourseSection::TYPE_SURVEY) {
                    $surveyCard = app(\App\Services\SurveyResponseExportService::class)
                        ->cardForLearner($sec, (int) $learner->id);
                }

                $out[] = [
                    'title' => (string) $sec->title,
                    'type' => (string) $sec->type,
                    'type_label' => self::SECTION_TYPE_LABEL[$sec->type] ?? $sec->type,
                    'progress_percent' => $pct,
                    'view_url' => $viewUrl,
                    'view_hash' => $hash,
                    'reset_step' => $resetStep,
                    'show_reset' => $showReset,
                    'survey_card' => $surveyCard,
                ];
            }

            return $out;
        }

        $rows = [
            ['bk' => 'theory', 'title' => 'Теория', 'type' => 'text', 'type_label' => 'Теория'],
            ['bk' => 'theory_quiz', 'title' => 'Тест по теории', 'type' => 'quiz', 'type_label' => 'Тест'],
            ['bk' => 'practice', 'title' => 'Практика', 'type' => 'practice', 'type_label' => 'Практика'],
            ['bk' => 'module_exam', 'title' => 'Итоговый тест', 'type' => 'exam', 'type_label' => 'Экзамен'],
        ];

        foreach ($rows as $r) {
            $bk = (string) $r['bk'];
            if ($bk === 'practice' && $this->courseSections->isPracticeWaived($mid, $contentIdx, $legacyAlt)) {
                continue;
            }
            $complete = $p !== null && $this->courseSections->isStepComplete($p, $bk, $mid, $contentIdx, $legacyAlt);
            $pct = $complete ? 100 : 0;
            $resetStep = $this->resetStepForBackendKey($bk);
            $started = $this->sectionStarted($p, $bk, $mid, $courseId, $contentIdx, $legacyAlt, $sessionsByModuleId);
            $showReset = $allowReset && $resetStep !== null && $started;

            $hash = $this->anchorForBackendKey($bk);
            $viewUrl = route('admin.learners.course.learner.module', [
                'adminCourse' => $course->slug,
                'learner' => $learner->id,
                'courseModule' => $mid,
            ]).$hash;

            $out[] = [
                'title' => $r['title'],
                'type' => $r['type'],
                'type_label' => $r['type_label'],
                'progress_percent' => $pct,
                'view_url' => $viewUrl,
                'view_hash' => $hash,
                'reset_step' => $resetStep,
                'show_reset' => $showReset,
            ];
        }

        return $out;
    }

    private function anchorForBackendKey(string $bk): string
    {
        $sid = CourseSection::idFromStepKey($bk);
        if ($sid !== null) {
            return '#section-'.$sid;
        }

        return match ($bk) {
            'theory' => '#theory',
            'theory_quiz' => '#test',
            'practice' => '#practice',
            'module_exam' => '#exam',
            'survey' => '#survey',
            default => '#theory',
        };
    }

    private function resetStepForBackendKey(string $bk): ?string
    {
        return match ($bk) {
            'theory_quiz' => InstructorProgressResetService::STEP_THEORY_QUIZ,
            'module_exam' => InstructorProgressResetService::STEP_MODULE_EXAM,
            'practice' => InstructorProgressResetService::STEP_PRACTICE,
            default => null,
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\PracticeSession>  $sessionsByModuleId
     */
    private function sectionStarted(
        ?ModuleProgress $p,
        string $bk,
        int $courseModuleId,
        int $courseId,
        int $contentIdx,
        bool $legacyAlt,
        $sessionsByModuleId
    ): bool {
        if ($p === null) {
            return false;
        }

        $sec = $this->courseSections->findSectionByStepKey($courseModuleId, $bk);
        if ($sec !== null) {
            if ($sec->type === CourseSection::TYPE_SURVEY) {
                return app(\App\Services\SurveyResponseService::class)->hasSubmission((int) $sec->id, (int) $p->learner_id);
            }

            return $this->courseSections->isSectionComplete($p, $sec, $courseModuleId, $contentIdx, $legacyAlt)
                || match ($sec->type) {
                    CourseSection::TYPE_QUIZ => $this->theoryQuizStarted($p),
                    CourseSection::TYPE_EXAM => $this->moduleExamStarted($p),
                    CourseSection::TYPE_PRACTICE => $this->practiceStarted($p, $courseModuleId, $courseId, $contentIdx, $legacyAlt, $sessionsByModuleId),
                    default => false,
                };
        }

        return match ($bk) {
            'theory' => (bool) $p->theory_read_at,
            'theory_quiz' => $this->theoryQuizStarted($p),
            'practice' => $this->practiceStarted($p, $courseModuleId, $courseId, $contentIdx, $legacyAlt, $sessionsByModuleId),
            'module_exam' => $this->moduleExamStarted($p),
            'survey' => false,
            default => false,
        };
    }

    private function theoryQuizStarted(ModuleProgress $p): bool
    {
        if ($p->theory_quiz_passed) {
            return true;
        }
        $hist = $p->theory_quiz_history ?? [];

        return (int) $p->theory_quiz_attempts >= 1
            || is_array($p->theory_quiz_last_result)
            || (is_array($hist) && count($hist) > 0);
    }

    private function moduleExamStarted(ModuleProgress $p): bool
    {
        if ($p->module_exam_passed) {
            return true;
        }
        $hist = $p->module_exam_history ?? [];

        return (int) $p->module_exam_attempts >= 1
            || is_array($p->module_exam_last_result)
            || (is_array($hist) && count($hist) > 0);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\PracticeSession>  $sessionsByModuleId
     */
    private function practiceStarted(
        ModuleProgress $p,
        int $courseModuleId,
        int $courseId,
        int $contentIdx,
        bool $legacyAlt,
        $sessionsByModuleId
    ): bool {
        if ($p->practice_done_at) {
            return true;
        }
        if ($this->courseSections->isPracticeWaived($courseModuleId, $contentIdx, $legacyAlt)) {
            return false;
        }

        return $sessionsByModuleId->has($courseModuleId);
    }

}
