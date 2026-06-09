<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\Learner;
use App\Models\PracticeSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Сборка данных для страницы профиля обучающегося (преподаватель): история попыток, время по разделам, практика.
 */
final class TeacherLearnerProfileDetailService
{
    public function __construct(
        private CourseScoringService $scoring,
        private CourseModuleService $courseModules,
        private CourseSectionService $courseSections,
    ) {}

    private function resolveCourseId(Learner $learner): int
    {
        $id = (int) $learner->courseEnrollments()->orderBy('id')->value('course_id');
        if ($id > 0) {
            return $id;
        }

        return (int) Course::query()->where('slug', 'alt-os-features')->value('id');
    }

    /**
     * @return list<array{
     *   module_id:int,
     *   title:string,
     *   letter:string,
     *   report:array|null,
     *   progress:\App\Models\ModuleProgress|null,
     *   theory_questions:array,
     *   exam_questions:array,
     *   practice_session:\App\Models\PracticeSession|null,
     *   theory_quiz_history:array,
     *   module_exam_history:array,
     *   instructor_resets:array
     * }>
     */
    public function modulePanels(Learner $learner, ?int $forceCourseId = null): array
    {
        $courseId = $forceCourseId ?? $this->resolveCourseId($learner);
        $learner->loadMissing([
            'moduleProgresses' => fn ($q) => $q->where('course_id', $courseId),
        ]);

        /** @var Collection<int, PracticeSession> $sessions */
        $sessions = Schema::hasColumn('practice_sessions', 'course_id')
            ? PracticeSession::query()
                ->where('learner_id', $learner->id)
                ->where('course_id', $courseId)
                ->get()
                ->keyBy('module_id')
            : PracticeSession::query()
                ->where('learner_id', $learner->id)
                ->get()
                ->keyBy('module_id');

        $reportById = collect($this->scoring->moduleReport($learner, $courseId))->keyBy('module_id');

        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        $legacyAlt = $course?->isLegacyAltCourse() ?? false;
        $stepTitles = config('course.step_titles', []);

        $panels = [];
        if ($courseId > 0 && Schema::hasTable('course_modules')) {
            $sequence = 0;
            foreach ($this->courseModules->orderedModulesForCourse($courseId) as $mod) {
                $sequence++;
                $mid = (int) $mod->id;
                $idx = $mod->effectiveContentIndex();
                $p = $learner->progressExisting($mid, $courseId);
                $meta = $this->courseModules->displayMeta($mod);
                $panels[] = [
                    'module_id' => $mid,
                    'sequence' => $sequence,
                    'content_source_index' => $idx,
                    'title' => (string) ($meta['title'] ?? ''),
                    'letter' => (string) ($meta['letter'] ?? ''),
                    'section_rows' => $this->sectionRowsForPanel($p, $mid, $idx, $legacyAlt, $stepTitles),
                    'report' => $reportById->get($mid),
                    'progress' => $p,
                    'theory_questions' => config('course.module_quizzes.'.$idx.'.theory_quiz', []) ?: [],
                    'exam_questions' => config('course.module_quizzes.'.$idx.'.module_exam', []) ?: [],
                    'practice_session' => $sessions->get($mid),
                    'theory_quiz_history' => $p ? ($p->theory_quiz_history ?? []) : [],
                    'module_exam_history' => $p ? ($p->module_exam_history ?? []) : [],
                    'instructor_resets' => $p ? ($p->instructor_resets ?? []) : [],
                    'surveys' => $this->surveyPanelsForModule($learner, $mod, $courseId),
                ];
            }
        }

        return $panels;
    }

    /**
     * @return array|null  тот же формат элемента, что и в modulePanels()
     */
    public function modulePanel(Learner $learner, int $moduleId, ?int $forceCourseId = null): ?array
    {
        foreach ($this->modulePanels($learner, $forceCourseId) as $panel) {
            if ((int) $panel['module_id'] === $moduleId) {
                return $panel;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $stepTitles
     * @return list<array{label: string, percent: int, waived: bool, backend_key: string}>
     */
    private function sectionRowsForPanel(?\App\Models\ModuleProgress $p, int $mid, int $idx, bool $legacyAlt, array $stepTitles): array
    {
        $defaultLabels = [
            'theory' => (string) ($stepTitles['theory'] ?? 'Теория'),
            'theory_quiz' => (string) ($stepTitles['theory_quiz'] ?? 'Тест по теории'),
            'practice' => (string) ($stepTitles['practice'] ?? 'Практика'),
            'module_exam' => (string) ($stepTitles['module_exam'] ?? 'Экзамен'),
            'survey' => 'Опрос',
        ];

        $rows = [];
        if ($this->courseSections->useDbSectionsForModule($mid)) {
            foreach ($this->courseSections->enabledSectionsForCourseModule($mid) as $sec) {
                $bk = $sec->backendStepKey();
                $waived = $sec->legacyTypeKey() === 'practice' && $this->courseSections->isPracticeWaived($mid, $idx, $legacyAlt);
                if ($waived) {
                    continue;
                }
                $pct = $p !== null
                    ? $this->courseSections->displayProgressPercentForBackendKey($p, $bk, $mid, $idx, $legacyAlt)
                    : 0;
                $rows[] = [
                    'label' => (string) ($sec->title !== '' ? $sec->title : ($defaultLabels[$sec->legacyTypeKey()] ?? $bk)),
                    'percent' => $pct,
                    'waived' => false,
                    'backend_key' => $bk,
                    'section_id' => (int) $sec->id,
                    'section_type' => (string) $sec->type,
                ];
            }

            return $rows;
        }

        foreach (['theory', 'theory_quiz', 'practice', 'module_exam'] as $bk) {
            if ($bk === 'practice' && $this->courseSections->isPracticeWaived($mid, $idx, $legacyAlt)) {
                continue;
            }
            $pct = $p !== null
                ? $this->courseSections->displayProgressPercentForBackendKey($p, $bk, $mid, $idx, $legacyAlt)
                : 0;
            $rows[] = [
                'label' => $defaultLabels[$bk] ?? $bk,
                'percent' => $pct,
                'waived' => false,
                'backend_key' => $bk,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function surveyPanelsForModule(Learner $learner, CourseModule $mod, int $courseId): array
    {
        if (! $this->courseSections->useDbSectionsForModule((int) $mod->id)) {
            return [];
        }
        $course = Course::query()->find($courseId);
        $out = [];
        foreach ($this->courseSections->enabledSectionsForCourseModule((int) $mod->id) as $sec) {
            if ($sec->type !== CourseSection::TYPE_SURVEY) {
                continue;
            }
            $card = app(\App\Services\SurveyResponseExportService::class)->cardForLearner($sec, (int) $learner->id);
            $responsesUrl = $course
                ? route('admin.course.module.section.survey-responses', ['adminCourse' => $course->slug, 'courseModule' => $mod->id, 'section' => $sec->id])
                : null;
            $out[(int) $sec->id] = [
                'section_id' => (int) $sec->id,
                'title' => (string) $sec->title,
                'card' => $card,
                'responses_url' => $responsesUrl,
            ];
        }

        return $out;
    }

}
