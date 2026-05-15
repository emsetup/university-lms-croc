<?php

namespace App\Services;

use App\Models\Learner;
use App\Support\CourseModuleMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;

/**
 * Доступ к шагам модуля: порядок и предпосылки из настроек курса (разделы в БД).
 */
final class ModuleAccessGate
{
    public function __construct(
        private CourseSectionService $sections,
        private CourseModuleService $modules,
    ) {}

    private function courseId(Learner $learner): int
    {
        $id = (int) session('course_id', 0);

        return $id > 0 ? $id : 0;
    }

    public function isModuleUnlocked(Learner $learner, int $courseModuleId, ?int $courseId = null): bool
    {
        $courseId = $courseId ?? $this->courseId($learner);
        if ($courseId < 1) {
            return false;
        }
        $ordered = $this->modules->orderedModuleIdsForCourse($courseId);
        if ($ordered === []) {
            return $courseModuleId >= 1;
        }
        $idx = array_search($courseModuleId, $ordered, true);
        if ($idx === false) {
            return false;
        }
        if ($idx === 0) {
            return true;
        }
        $prevId = $ordered[$idx - 1];
        $prev = $learner->progressExisting($prevId, $courseId);
        if ($prev === null) {
            return false;
        }

        if ((bool) $prev->module_exam_passed) {
            return true;
        }

        if ((int) ($prev->module_exam_attempts ?? 0) >= 1) {
            return true;
        }
        $last = $prev->module_exam_last_result ?? null;

        return is_array($last) && $last !== [];
    }

    public function redirectIfModuleLocked(Learner $learner, int $courseModuleId): ?RedirectResponse
    {
        if (! $this->isModuleUnlocked($learner, $courseModuleId)) {
            $cid = $this->courseId($learner);

            return redirect()->route('course.dashboard', ['course' => $cid > 0 ? $cid : 1])
                ->with('err', 'Модуль ещё недоступен: завершите хотя бы одну попытку итогового теста предыдущего модуля.');
        }

        return null;
    }

    public function redirectIfStepBlocked(Learner $learner, int $courseModuleId, string $targetBackendKey): ?RedirectResponse
    {
        $courseId = $this->courseId($learner);
        $cm = $this->modules->findForCourse($courseId, $courseModuleId);
        $contentIdx = $cm?->effectiveContentIndex() ?? 1;

        if (! $this->sections->useDbSectionsForModule($courseModuleId)) {
            return $this->legacyRedirectForStep($learner, $courseModuleId, $targetBackendKey, $contentIdx);
        }
        if (! $this->sections->hasBackendStep($courseModuleId, $targetBackendKey)) {
            return redirect()->route('modules.hub', $courseModuleId)
                ->with('err', 'Этот этап отключён в настройках курса.');
        }
        $p = $learner->progressFor($courseModuleId);
        $blocked = $this->sections->firstBlockedPrerequisite($p, $courseModuleId, $contentIdx, $targetBackendKey);
        if ($blocked === null) {
            return null;
        }

        return $this->redirectForBackendKey($courseModuleId, $blocked);
    }

    public function redirectIfTheoryNotRead(Learner $learner, int $courseModuleId): ?RedirectResponse
    {
        return $this->redirectIfStepBlocked($learner, $courseModuleId, 'theory_quiz');
    }

    public function redirectIfTheoryQuizNotPassed(Learner $learner, int $courseModuleId): ?RedirectResponse
    {
        return $this->redirectIfStepBlocked($learner, $courseModuleId, 'practice');
    }

    public function redirectIfExamPrerequisitesMissing(Learner $learner, int $courseModuleId): ?RedirectResponse
    {
        return $this->redirectIfStepBlocked($learner, $courseModuleId, 'module_exam');
    }

    private function legacyRedirectForStep(Learner $learner, int $courseModuleId, string $targetBackendKey, int $contentSourceIndex): ?RedirectResponse
    {
        if ($targetBackendKey === 'theory') {
            return null;
        }
        if ($targetBackendKey === 'theory_quiz') {
            $p = $learner->progressFor($courseModuleId);
            if (! $p->theory_read_at) {
                return redirect()->route('modules.theory', $courseModuleId)
                    ->with('err', 'Сначала отметьте просмотр теории.');
            }

            return null;
        }
        if ($targetBackendKey === 'practice') {
            $p = $learner->progressFor($courseModuleId);
            if (! $p->theory_read_at) {
                return redirect()->route('modules.theory', $courseModuleId)
                    ->with('err', 'Сначала отметьте просмотр теории.');
            }
            if (! $this->sections->isTheoryQuizEffectivelyPassed($p, $courseModuleId)) {
                return redirect()->route('modules.theory-quiz', $courseModuleId)
                    ->with('err', 'Сначала успешно сдайте тест по теории.');
            }

            return null;
        }
        if ($targetBackendKey === 'module_exam') {
            $p = $learner->progressFor($courseModuleId);
            if (! $p->theory_read_at) {
                return redirect()->route('modules.theory', $courseModuleId)
                    ->with('err', 'Сначала отметьте просмотр теории.');
            }
            if (! $this->sections->isTheoryQuizEffectivelyPassed($p, $courseModuleId)) {
                return redirect()->route('modules.theory-quiz', $courseModuleId)
                    ->with('err', 'Сначала успешно сдайте тест по теории.');
            }
            if (! CourseModuleMeta::shouldSkipPractice($contentSourceIndex) && ! $p->practice_done_at) {
                return redirect()->route('modules.hub', $courseModuleId)
                    ->with('err', 'Сначала зачтите практическое занятие.');
            }

            return null;
        }

        return null;
    }

    private function redirectForBackendKey(int $courseModuleId, string $blockedKey): RedirectResponse
    {
        $msg = 'Сначала завершите предыдущий этап модуля.';

        return match ($blockedKey) {
            'theory' => redirect()->route('modules.theory', $courseModuleId)->with('err', $msg),
            'theory_quiz' => redirect()->route('modules.theory-quiz', $courseModuleId)->with('err', $msg),
            'practice' => redirect()->route('modules.practice', $courseModuleId)->with('err', $msg),
            'module_exam' => redirect()->route('modules.exam', $courseModuleId)->with('err', $msg),
            default => redirect()->route('modules.hub', $courseModuleId)->with('err', $msg),
        };
    }
}
