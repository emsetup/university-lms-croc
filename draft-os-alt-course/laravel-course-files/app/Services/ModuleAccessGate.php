<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Learner;
use App\Models\CourseSection;
use App\Support\CourseModuleMeta;
use App\Support\CourseStaffPreview;
use App\Support\LearnerPreviewContext;
use App\Support\LearnerRoute;
use App\Support\ShareLinkEntryContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;

/**
 * Доступ к шагам модуля: порядок и предпосылки из настроек курса (разделы в БД).
 */
final class ModuleAccessGate
{
    /** @var array<int, bool> */
    private array $unlockAllModulesCache = [];

    public function __construct(
        private CourseSectionService $sections,
        private CourseModuleService $modules,
        private LearnerContentVisibilityService $visibility,
    ) {}

    private function courseId(Learner $learner): int
    {
        $id = LearnerPreviewContext::courseId();

        return $id > 0 ? $id : 0;
    }

    public function isModuleUnlocked(Learner $learner, int $courseModuleId, ?int $courseId = null): bool
    {
        $courseId = $courseId ?? $this->courseId($learner);
        if (CourseStaffPreview::isActive()) {
            if ($courseId < 1) {
                return false;
            }
            $ordered = $this->modules->orderedModuleIdsForCourse($courseId);
            if ($ordered === []) {
                return $courseModuleId >= 1;
            }

            return in_array($courseModuleId, $ordered, true);
        }
        if ($courseId < 1) {
            return false;
        }
        if (! $this->visibility->isModuleVisibleToLearner($courseModuleId, (int) $learner->id, $courseId)) {
            return false;
        }
        if (ShareLinkEntryContext::allowsModule($courseModuleId, $courseId)) {
            return true;
        }
        if ($this->courseUnlocksAllModules($courseId)) {
            $ordered = $this->visibleOrderedModuleIdsForLearner($learner, $courseId);
            if ($ordered === []) {
                return $courseModuleId >= 1;
            }

            return in_array($courseModuleId, $ordered, true);
        }
        $ordered = $this->visibleOrderedModuleIdsForLearner($learner, $courseId);
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

    public function courseUnlocksAllModules(int $courseId): bool
    {
        if (isset($this->unlockAllModulesCache[$courseId])) {
            return $this->unlockAllModulesCache[$courseId];
        }
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'unlock_all_modules')) {
            return $this->unlockAllModulesCache[$courseId] = false;
        }

        return $this->unlockAllModulesCache[$courseId] = (bool) Course::query()
            ->whereKey($courseId)
            ->value('unlock_all_modules');
    }

    public function redirectIfModuleLocked(Learner $learner, int $courseModuleId): ?RedirectResponse
    {
        if (CourseStaffPreview::isActive()) {
            return null;
        }

        if ($r = $this->redirectIfModuleHidden($learner, $courseModuleId)) {
            return $r;
        }

        if (! $this->isModuleUnlocked($learner, $courseModuleId)) {
            $cid = $this->courseId($learner);

            return redirect()->route('course.dashboard', ['course' => $cid > 0 ? $cid : 1])
                ->with('err', 'Модуль ещё недоступен: завершите хотя бы одну попытку итогового теста предыдущего модуля.');
        }

        return null;
    }

    public function redirectIfModuleHidden(Learner $learner, int $courseModuleId): ?RedirectResponse
    {
        if (CourseStaffPreview::isActive()) {
            return null;
        }

        $courseId = $this->courseId($learner);
        if ($courseId < 1 || $this->visibility->isModuleVisibleToLearner($courseModuleId, (int) $learner->id, $courseId)) {
            return null;
        }

        return redirect()->route('course.dashboard', ['course' => $courseId])
            ->with('err', 'Этот модуль недоступен для вашей учётной записи.');
    }

    public function redirectIfSectionHidden(Learner $learner, int $sectionId, int $courseModuleId): ?RedirectResponse
    {
        if (CourseStaffPreview::isActive()) {
            return null;
        }

        if ($this->visibility->isSectionVisibleToLearner($sectionId, (int) $learner->id, $courseModuleId)) {
            return null;
        }

        $courseId = $this->courseId($learner);
        $seq = $this->moduleSequenceForId($courseModuleId);

        return redirect()->route('course.module.hub', LearnerRoute::hub($courseId, $seq))
            ->with('err', 'Этот раздел недоступен для вашей учётной записи.');
    }

    /**
     * @return list<int>
     */
    private function visibleOrderedModuleIdsForLearner(Learner $learner, int $courseId): array
    {
        return $this->visibility->visibleModuleIdsForLearner($courseId, (int) $learner->id);
    }

    public function redirectIfStepBlocked(Learner $learner, int $courseModuleId, string $targetBackendKey): ?RedirectResponse
    {
        if (CourseStaffPreview::isActive()) {
            return null;
        }

        $courseId = $this->courseId($learner);
        if (ShareLinkEntryContext::bypassesStepGates($courseModuleId, $courseId > 0 ? $courseId : null)) {
            return null;
        }

        $cm = $this->modules->findForCourse($courseId, $courseModuleId);
        $contentIdx = $cm?->effectiveContentIndex() ?? 1;

        if (! $this->sections->useDbSectionsForModule($courseModuleId)) {
            return $this->legacyRedirectForStep($learner, $courseModuleId, $targetBackendKey, $contentIdx);
        }
        if (! $this->sections->hasBackendStep($courseModuleId, $targetBackendKey)) {
            $seq = $cm ? $this->modules->sequenceForModule($cm) : $courseModuleId;

            return redirect()->route('course.module.hub', LearnerRoute::hub($courseId, $seq))
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
        $courseId = $this->courseIdFromModule($courseModuleId);
        $seq = $this->moduleSequenceForId($courseModuleId);
        $hub = LearnerRoute::hub($courseId, $seq);
        if ($targetBackendKey === 'theory') {
            return null;
        }
        if ($targetBackendKey === 'theory_quiz') {
            $p = $learner->progressFor($courseModuleId);
            if (! $p->theory_read_at) {
                return redirect()->route('course.module.theory', $hub)
                    ->with('err', 'Сначала отметьте просмотр теории.');
            }

            return null;
        }
        if ($targetBackendKey === 'practice') {
            $p = $learner->progressFor($courseModuleId);
            if (! $p->theory_read_at) {
                return redirect()->route('course.module.theory', $hub)
                    ->with('err', 'Сначала отметьте просмотр теории.');
            }
            if (! $this->sections->isTheoryQuizEffectivelyPassed($p, $courseModuleId)) {
                return redirect()->route('course.module.theory-quiz', $hub)
                    ->with('err', 'Сначала успешно сдайте тест по теории.');
            }

            return null;
        }
        if ($targetBackendKey === 'module_exam') {
            $p = $learner->progressFor($courseModuleId);
            if (! $p->theory_read_at) {
                return redirect()->route('course.module.theory', $hub)
                    ->with('err', 'Сначала отметьте просмотр теории.');
            }
            if (! $this->sections->isTheoryQuizEffectivelyPassed($p, $courseModuleId)) {
                return redirect()->route('course.module.theory-quiz', $hub)
                    ->with('err', 'Сначала успешно сдайте тест по теории.');
            }
            if (! CourseModuleMeta::shouldSkipPractice($contentSourceIndex) && ! $p->practice_done_at) {
                return redirect()->route('course.module.hub', $hub)
                    ->with('err', 'Сначала зачтите практическое занятие.');
            }

            return null;
        }

        return null;
    }

    private function redirectForBackendKey(int $courseModuleId, string $blockedKey): RedirectResponse
    {
        $msg = 'Сначала завершите предыдущий этап модуля.';
        $courseId = $this->courseIdFromModule($courseModuleId);
        $sec = $this->sections->findSectionByStepKey($courseModuleId, $blockedKey);
        if ($sec !== null) {
            $routeName = $sec->learnerRouteName();
            if ($routeName !== null) {
                $cm = $this->modules->findForCourse($courseId, $courseModuleId);
                $seq = $cm ? $this->modules->sequenceForModule($cm) : $courseModuleId;
                $surveyMsg = $sec->type === CourseSection::TYPE_SURVEY
                    ? 'Сначала заполните и отправьте опрос «'.$sec->title.'».': $msg;

                return redirect()->route($routeName, $sec->learnerRouteParams($courseId, $seq))
                    ->with('err', $sec->type === CourseSection::TYPE_SURVEY ? $surveyMsg : $msg);
            }
        }

        $seq = $this->moduleSequenceForId($courseModuleId);
        $hub = LearnerRoute::hub($courseId, $seq);

        return match ($blockedKey) {
            'theory' => redirect()->route('course.module.theory', $hub)->with('err', $msg),
            'theory_quiz' => redirect()->route('course.module.theory-quiz', $hub)->with('err', $msg),
            'practice' => redirect()->route('course.module.practice', $hub)->with('err', $msg),
            'module_exam' => redirect()->route('course.module.exam', $hub)->with('err', $msg),
            'survey' => redirect()->route('course.module.hub', $hub)->with('err', 'Сначала заполните и отправьте опрос.'),
            default => redirect()->route('course.module.hub', $hub)->with('err', $msg),
        };
    }

    private function moduleSequenceForId(int $courseModuleId): int
    {
        $courseId = $this->courseIdFromModule($courseModuleId);
        $cm = $this->modules->findForCourse($courseId, $courseModuleId);

        return $cm ? $this->modules->sequenceForModule($cm) : $courseModuleId;
    }

    private function courseIdFromModule(int $courseModuleId): int
    {
        $cid = (int) \App\Models\CourseModule::query()->whereKey($courseModuleId)->value('course_id');

        return $cid > 0 ? $cid : (int) LearnerPreviewContext::courseId();
    }
}
