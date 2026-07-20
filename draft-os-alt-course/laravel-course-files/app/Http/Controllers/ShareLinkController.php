<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\CourseShareLink;
use App\Models\Learner;
use App\Services\CourseModuleService;
use App\Services\LearnerContentVisibilityService;
use App\Services\LearnerCourseAvailability;
use App\Services\ShareLinkService;
use App\Support\LearnerPreviewContext;
use App\Support\LearnerRoute;
use App\Support\ShareLinkEntryContext;
use Illuminate\Http\RedirectResponse;

final class ShareLinkController extends Controller
{
    public function __construct(
        private ShareLinkService $shareLinks,
        private CourseModuleService $modules,
        private LearnerContentVisibilityService $visibility,
    ) {}

    public function show(string $token): RedirectResponse
    {
        $link = $this->shareLinks->resolve($token);
        if ($link === null) {
            return redirect()->route('portal')->with('err', 'Ссылка недействительна или отключена.');
        }

        $course = Course::query()->find((int) $link->course_id);
        if ($course === null || ! LearnerCourseAvailability::isOpenForLearning($course)) {
            return redirect()->route('portal')->with('err', 'Этот курс снят с обучения. Ссылка недоступна.');
        }

        $learner = $this->learner();

        if (! $this->visibility->isCourseVisibleToLearner((int) $course->id, (int) $learner->id)) {
            return redirect()->route('portal')->with('err', 'У вас нет доступа к этому курсу.');
        }

        $module = null;
        $section = null;

        if ($link->target_type === CourseShareLink::TARGET_MODULE) {
            $module = CourseModule::query()
                ->whereKey((int) $link->target_id)
                ->where('course_id', (int) $course->id)
                ->first();
            if ($module === null) {
                return redirect()->route('portal')->with('err', 'Модуль по ссылке не найден.');
            }
            if (! $this->visibility->isModuleVisibleToLearner((int) $module->id, (int) $learner->id, (int) $course->id)) {
                return redirect()->route('portal')->with('err', 'У вас нет доступа к этому модулю.');
            }
        } elseif ($link->target_type === CourseShareLink::TARGET_SECTION) {
            $section = CourseSection::query()
                ->whereKey((int) $link->target_id)
                ->where('course_id', (int) $course->id)
                ->where('is_enabled', true)
                ->first();
            if ($section === null) {
                return redirect()->route('portal')->with('err', 'Раздел по ссылке не найден или выключен.');
            }
            $module = $section->loadMissing('courseModule')->courseModule;
            if ($module === null) {
                return redirect()->route('portal')->with('err', 'Модуль раздела не найден.');
            }
            if (! $this->visibility->isSectionVisibleToLearner((int) $section->id, (int) $learner->id, (int) $module->id)) {
                return redirect()->route('portal')->with('err', 'У вас нет доступа к этому разделу.');
            }
        }

        $this->shareLinks->ensureEnrollment($learner, $course);

        ShareLinkEntryContext::activate(
            (int) $course->id,
            $module ? (int) $module->id : null,
            $section ? (int) $section->id : null,
        );

        if ($section !== null && $module !== null) {
            $routeName = $section->learnerRouteName();
            if ($routeName === null) {
                return redirect()->route('course.dashboard', ['course' => $course->id])
                    ->with('err', 'Не удалось открыть раздел по ссылке.');
            }
            $modSeq = $this->modules->sequenceForModule($module);
            $params = $section->learnerRouteParams((int) $course->id, $modSeq);

            return redirect()->route($routeName, $params);
        }

        if ($module !== null) {
            $modSeq = $this->modules->sequenceForModule($module);

            return redirect()->route('course.module.hub', LearnerRoute::hub((int) $course->id, $modSeq));
        }

        return redirect()->route('course.dashboard', ['course' => $course->id]);
    }

    private function learner(): Learner
    {
        return Learner::findOrFail(LearnerPreviewContext::learnerId());
    }
}
