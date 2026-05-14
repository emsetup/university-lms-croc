<?php

namespace App\Services;

use App\Models\Course;
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

        $panels = [];
        if ($courseId > 0 && Schema::hasTable('course_modules')) {
            foreach ($this->courseModules->orderedModulesForCourse($courseId) as $mod) {
                $mid = (int) $mod->id;
                $idx = $mod->effectiveContentIndex();
                $p = $learner->progressExisting($mid, $courseId);
                $meta = $this->courseModules->displayMeta($mod);
                $panels[] = [
                    'module_id' => $mid,
                    'content_source_index' => $idx,
                    'title' => (string) ($meta['title'] ?? ''),
                    'letter' => (string) ($meta['letter'] ?? ''),
                    'report' => $reportById->get($mid),
                    'progress' => $p,
                    'theory_questions' => config('course.module_quizzes.'.$idx.'.theory_quiz', []) ?: [],
                    'exam_questions' => config('course.module_quizzes.'.$idx.'.module_exam', []) ?: [],
                    'practice_session' => $sessions->get($mid),
                    'theory_quiz_history' => $p ? ($p->theory_quiz_history ?? []) : [],
                    'module_exam_history' => $p ? ($p->module_exam_history ?? []) : [],
                    'instructor_resets' => $p ? ($p->instructor_resets ?? []) : [],
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
}
