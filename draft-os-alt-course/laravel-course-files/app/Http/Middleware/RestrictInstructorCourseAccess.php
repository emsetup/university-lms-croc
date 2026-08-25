<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Services\PortalStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Преподаватель: только статистика по назначенному курсу.
 * Аудитор: полный доступ к своим курсам; к чужим — только статистика обучающихся.
 */
final class RestrictInstructorCourseAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = app(PortalStaffAccess::class);
        $name = (string) ($request->route()?->getName() ?? '');

        if ($gate->isInstructor()) {
            if (str_starts_with($name, 'admin.learners.course')) {
                return $next($request);
            }

            abort(403, 'Преподаватель курса может только просматривать статистику обучающихся по назначенному курсу.');
        }

        if ($gate->isPortalAuditor()) {
            $course = $request->route('adminCourse');
            $courseId = $course instanceof Course ? (int) $course->id : 0;
            if ($courseId > 0 && $gate->ownedCourseIds()->containsStrict($courseId)) {
                return $next($request);
            }
            if (str_starts_with($name, 'admin.learners.course')
                || str_starts_with($name, 'admin.course.surveys')
                || str_starts_with($name, 'admin.course.preview')) {
                return $next($request);
            }

            abort(403, 'Аудитор портала может редактировать только свои курсы; чужие — только статистика обучающихся.');
        }

        return $next($request);
    }
}
