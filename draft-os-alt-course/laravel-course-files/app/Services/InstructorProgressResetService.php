<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\ModuleProgress;
use App\Models\PracticeSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сброс шага модуля для обучающегося: снимок в instructor_resets, очистка «текущего» состояния.
 * История снимков не удаляется — преподаватель видит архив в админке.
 */
final class InstructorProgressResetService
{
    public const STEP_THEORY_QUIZ = 'theory_quiz';

    public const STEP_MODULE_EXAM = 'module_exam';

    public const STEP_PRACTICE = 'practice';

    /**
     * @return list<string>
     */
    public static function allowedSteps(): array
    {
        return [self::STEP_THEORY_QUIZ, self::STEP_MODULE_EXAM, self::STEP_PRACTICE];
    }

    public function reset(Learner $learner, int $moduleId, string $step, ?string $note = null): void
    {
        abort_unless($moduleId >= 1 && $moduleId <= 9, 404);
        abort_unless(in_array($step, self::allowedSteps(), true), 400);

        DB::transaction(function () use ($learner, $moduleId, $step, $note): void {
            $p = $learner->progressFor($moduleId);

            $snap = $this->snapshotProgress($p, $learner->id, $moduleId);
            $entry = [
                'at' => now()->toIso8601String(),
                'step' => $step,
                'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null,
                'snapshot' => $snap,
            ];
            $resets = $p->instructor_resets ?? [];
            $resets[] = $entry;
            $p->instructor_resets = $resets;

            match ($step) {
                self::STEP_THEORY_QUIZ => $this->applyTheoryQuizReset($p),
                self::STEP_MODULE_EXAM => $this->applyModuleExamReset($p),
                self::STEP_PRACTICE => $this->applyPracticeReset($p, $learner->id, $moduleId),
            };

            $p->save();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotProgress(ModuleProgress $p, int $learnerId, int $moduleId): array
    {
        $session = PracticeSession::query()
            ->where('learner_id', $learnerId)
            ->where('module_id', $moduleId)
            ->first();

        return [
            'theory_quiz_attempts' => (int) $p->theory_quiz_attempts,
            'theory_quiz_passed' => (bool) $p->theory_quiz_passed,
            'theory_quiz_best_score' => (int) $p->theory_quiz_best_score,
            'theory_quiz_last_result' => $p->theory_quiz_last_result,
            'theory_quiz_history' => $p->theory_quiz_history ?? [],
            'module_exam_attempts' => (int) $p->module_exam_attempts,
            'module_exam_passed' => (bool) $p->module_exam_passed,
            'module_exam_best_score' => (int) $p->module_exam_best_score,
            'module_exam_last_result' => $p->module_exam_last_result,
            'module_exam_history' => $p->module_exam_history ?? [],
            'module_exam_deadline_at' => $p->module_exam_deadline_at?->toIso8601String(),
            'module_exam_deadline_for_attempt' => $p->module_exam_deadline_for_attempt,
            'practice_done_at' => $p->practice_done_at?->toIso8601String(),
            'practice_lab_percent' => $p->practice_lab_percent,
            'practice_m1_quest' => Schema::hasColumn('module_progress', 'practice_m1_quest') ? $p->practice_m1_quest : null,
            'seconds_practice' => (int) ($p->seconds_practice ?? 0),
            'practice_session' => $session ? $session->toArray() : null,
        ];
    }

    private function applyTheoryQuizReset(ModuleProgress $p): void
    {
        $p->theory_quiz_last_result = null;
        $p->theory_quiz_history = [];
        $p->theory_quiz_passed = false;
        $p->theory_quiz_best_score = 0;
        $before = (int) $p->theory_quiz_attempts;
        $p->theory_quiz_attempts = max(0, $before - 1);
    }

    private function applyModuleExamReset(ModuleProgress $p): void
    {
        $p->module_exam_last_result = null;
        $p->module_exam_history = [];
        $p->module_exam_passed = false;
        $p->module_exam_best_score = 0;
        $p->module_exam_deadline_at = null;
        $p->module_exam_deadline_for_attempt = null;
        $before = (int) $p->module_exam_attempts;
        $p->module_exam_attempts = max(0, $before - 1);
    }

    private function applyPracticeReset(ModuleProgress $p, int $learnerId, int $moduleId): void
    {
        $p->practice_done_at = null;
        $p->practice_lab_percent = null;
        if (Schema::hasColumn('module_progress', 'practice_m1_quest')) {
            $p->practice_m1_quest = null;
        }

        PracticeSession::query()
            ->where('learner_id', $learnerId)
            ->where('module_id', $moduleId)
            ->delete();
    }
}
