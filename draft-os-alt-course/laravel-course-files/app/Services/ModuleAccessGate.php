<?php

namespace App\Services;

use App\Models\Learner;
use App\Support\CourseModuleMeta;
use Illuminate\Http\RedirectResponse;

/**
 * Доступ к шагам модуля: порядок модулей, теория, тест, практика (если не отключена), экзамен.
 */
final class ModuleAccessGate
{
    public function isModuleUnlocked(Learner $learner, int $moduleId): bool
    {
        if ($moduleId <= 1) {
            return true;
        }
        $prev = $learner->progressExisting($moduleId - 1);
        if ($prev === null) {
            return false;
        }

        if ((bool) $prev->module_exam_passed) {
            return true;
        }

        // Разрешаем переход дальше после любой завершённой попытки итогового теста:
        // даже если порог не взят, обучающийся может продолжать курс и вернуться на пересдачу.
        if ((int) ($prev->module_exam_attempts ?? 0) >= 1) {
            return true;
        }
        $last = $prev->module_exam_last_result ?? null;

        return is_array($last) && $last !== [];
    }

    public function redirectIfModuleLocked(Learner $learner, int $moduleId): ?RedirectResponse
    {
        if (! $this->isModuleUnlocked($learner, $moduleId)) {
            return redirect()->route('dashboard')
                ->with('err', 'Модуль ещё недоступен: завершите хотя бы одну попытку итогового теста предыдущего модуля.');
        }

        return null;
    }

    public function redirectIfTheoryNotRead(Learner $learner, int $moduleId): ?RedirectResponse
    {
        $p = $learner->progressFor($moduleId);
        if (! $p->theory_read_at) {
            return redirect()->route('modules.theory', $moduleId)
                ->with('err', 'Сначала отметьте просмотр теории.');
        }

        return null;
    }

    public function redirectIfTheoryQuizNotPassed(Learner $learner, int $moduleId): ?RedirectResponse
    {
        $p = $learner->progressFor($moduleId);
        if (! $p->theory_quiz_passed) {
            return redirect()->route('modules.theory-quiz', $moduleId)
                ->with('err', 'Сначала успешно сдайте тест по теории.');
        }

        return null;
    }

    public function redirectIfExamPrerequisitesMissing(Learner $learner, int $moduleId): ?RedirectResponse
    {
        if ($r = $this->redirectIfTheoryNotRead($learner, $moduleId)) {
            return $r;
        }
        if ($r = $this->redirectIfTheoryQuizNotPassed($learner, $moduleId)) {
            return $r;
        }
        if (CourseModuleMeta::shouldSkipPractice($moduleId)) {
            return null;
        }
        $p = $learner->progressFor($moduleId);
        if (! $p->practice_done_at) {
            return redirect()->route('modules.hub', $moduleId)
                ->with('err', 'Сначала зачтите практическое занятие.');
        }

        return null;
    }
}
