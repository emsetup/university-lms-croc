<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\PracticeSession;
use Illuminate\Support\Collection;

/**
 * Сборка данных для страницы профиля обучающегося (преподаватель): история попыток, время по разделам, практика.
 */
final class TeacherLearnerProfileDetailService
{
    public function __construct(
        private CourseScoringService $scoring
    ) {}

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
    public function modulePanels(Learner $learner): array
    {
        $learner->loadMissing(['moduleProgresses']);
        /** @var Collection<int, PracticeSession> $sessions */
        $sessions = PracticeSession::query()
            ->where('learner_id', $learner->id)
            ->get()
            ->keyBy('module_id');

        $reportById = collect($this->scoring->moduleReport($learner))->keyBy('module_id');

        $panels = [];
        for ($mid = 1; $mid <= 9; $mid++) {
            $p = $learner->progressExisting($mid);
            $meta = config('course.modules.'.$mid);
            $panels[] = [
                'module_id' => $mid,
                'title' => is_array($meta) ? (string) ($meta['title'] ?? '') : '',
                'letter' => is_array($meta) ? (string) ($meta['letter'] ?? '') : '',
                'report' => $reportById->get($mid),
                'progress' => $p,
                'theory_questions' => config('course.module_quizzes.'.$mid.'.theory_quiz', []) ?: [],
                'exam_questions' => config('course.module_quizzes.'.$mid.'.module_exam', []) ?: [],
                'practice_session' => $sessions->get($mid),
                'theory_quiz_history' => $p ? ($p->theory_quiz_history ?? []) : [],
                'module_exam_history' => $p ? ($p->module_exam_history ?? []) : [],
                'instructor_resets' => $p ? ($p->instructor_resets ?? []) : [],
            ];
        }

        return $panels;
    }

    /**
     * Одна панель модуля для страницы преподавателя (детализация шага).
     *
     * @return array|null  тот же формат элемента, что и в modulePanels()
     */
    public function modulePanel(Learner $learner, int $moduleId): ?array
    {
        foreach ($this->modulePanels($learner) as $panel) {
            if ((int) $panel['module_id'] === $moduleId) {
                return $panel;
            }
        }

        return null;
    }
}
