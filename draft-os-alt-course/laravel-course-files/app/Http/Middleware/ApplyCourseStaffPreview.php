<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Support\CourseStaffPreview;
use App\Support\StaffAdminPreview;
use App\Support\StaffImpersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Предпросмотр курса сотрудником: ?course_preview=token (токен затем в сессии).
 */
final class ApplyCourseStaffPreview
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('portal.course-preview.end')) {
            CourseStaffPreview::clearSession();

            return $next($request);
        }

        $token = CourseStaffPreview::activeToken($request);
        if ($token === null) {
            return $next($request);
        }

        if (StaffImpersonation::isPreviewRequest($request) || StaffAdminPreview::isPreviewRequest($request)) {
            CourseStaffPreview::clearSession();

            return $next($request);
        }

        $ctx = CourseStaffPreview::resolvePreview($token);
        if ($ctx === null) {
            CourseStaffPreview::clearSession();

            return redirect()
                ->route('portal')
                ->with('err', 'Ссылка предпросмотра курса недействительна или истекла. Откройте курс снова из админки.');
        }

        $sessionLearnerId = (int) session('learner_id', 0);
        if ($sessionLearnerId !== $ctx['staff_learner_id']) {
            CourseStaffPreview::clearSession();

            return redirect()
                ->route('portal')
                ->with('err', 'Предпросмотр курса доступен только под учётной записью сотрудника, который его открыл.');
        }

        $courseId = (int) $ctx['course_id'];
        $routeCourseId = (int) $request->route('course', 0);
        if ($routeCourseId > 0 && $routeCourseId !== $courseId) {
            abort(403, 'Предпросмотр привязан к другому курсу.');
        }

        CourseStaffPreview::persistToken($token);

        $course = Course::query()->find($courseId);
        if ($course !== null) {
            CourseStaffPreview::selectCourse($courseId, (string) $course->title);
        }

        $request->attributes->set('course_staff_preview_active', true);
        $request->attributes->set('course_staff_preview_course_id', $courseId);
        $request->attributes->set('course_staff_preview_token', $token);

        URL::defaults(CourseStaffPreview::routeQueryParams($request));
        View::share('courseStaffPreviewActive', true);
        View::share('courseStaffPreviewCourse', $course);
        View::share('courseStaffPreviewToken', $token);

        return $next($request);
    }
}
