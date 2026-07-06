<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseSection;
use App\Services\CourseModuleService;
use App\Services\CourseSectionService;
use App\Support\CourseStaffPreview;
use App\Support\LearnerRoute;
use App\Support\StaffAdminPreview;
use App\Support\StaffImpersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CourseStaffPreviewController extends Controller
{
    public function __construct(
        private CourseModuleService $modules,
        private CourseSectionService $sections,
    ) {}

    public function startCourse(Request $request, Course $adminCourse): RedirectResponse
    {
        return $this->redirectWithToken($request, $adminCourse, 'course.dashboard', [
            'course' => (int) $adminCourse->id,
        ]);
    }

    public function startModule(Request $request, Course $adminCourse, int $module): RedirectResponse
    {
        abort_unless($module >= 1, 404);
        $cm = $this->modules->findByContentIndexForCourse($adminCourse, $module);
        abort_if($cm === null, 404);
        $sequence = $this->modules->sequenceForModule($cm);

        return $this->redirectWithToken($request, $adminCourse, 'course.module.hub', LearnerRoute::hub(
            (int) $adminCourse->id,
            $sequence,
        ));
    }

    public function startSection(Request $request, Course $adminCourse, int $module, CourseSection $section): RedirectResponse
    {
        abort_unless($module >= 1, 404);
        $cm = $this->modules->findByContentIndexForCourse($adminCourse, $module);
        abort_if(
            $cm === null
            || (int) $section->course_module_id !== (int) $cm->id
            || (int) $section->course_id !== (int) $adminCourse->id,
            404
        );

        $routeName = $section->learnerRouteName();
        abort_if($routeName === null, 404, 'Неподдерживаемый тип раздела.');

        $sequence = $this->modules->sequenceForModule($cm);
        $params = $section->learnerRouteParams((int) $adminCourse->id, $sequence);

        return $this->redirectWithToken($request, $adminCourse, $routeName, $params);
    }

    public function end(Request $request): RedirectResponse
    {
        $courseId = CourseStaffPreview::courseIdFromSession();
        $slug = $courseId > 0
            ? (string) Course::query()->whereKey($courseId)->value('slug')
            : '';

        CourseStaffPreview::clearSession();

        if ($slug !== '') {
            return redirect()
                ->route('admin.theory.index', ['adminCourse' => $slug])
                ->with('ok', 'Предпросмотр курса завершён.');
        }

        return redirect()
            ->route('admin.courses.index')
            ->with('ok', 'Предпросмотр курса завершён.');
    }

    /**
     * @param  array<string, int|string>  $routeParams
     */
    private function redirectWithToken(
        Request $request,
        Course $course,
        string $routeName,
        array $routeParams,
    ): RedirectResponse {
        $staffId = (int) session('learner_id', 0);
        abort_if($staffId <= 0, 403);

        CourseStaffPreview::assertCanPreview($course, $staffId);

        StaffImpersonation::clearSession();
        StaffAdminPreview::clearSession();
        CourseStaffPreview::clearSession();

        $token = CourseStaffPreview::createPreviewToken($staffId, (int) $course->id);
        CourseStaffPreview::persistToken($token);
        CourseStaffPreview::selectCourse((int) $course->id, (string) $course->title);

        return redirect()->route($routeName, array_merge(
            $routeParams,
            [CourseStaffPreview::QUERY_PARAM => $token],
        ));
    }
}
