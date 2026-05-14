<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Services\PortalStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Маршруты /adm/kurs/{adminCourse:slug}/… — синхронизируем сессию выбранного курса с URL.
 */
final class SyncAdminCourseFromSlug
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $course = $request->route('adminCourse');
        if (! $course instanceof Course) {
            return $next($request);
        }

        app(PortalStaffAccess::class)->assertCanAccessCourseInAdmin((int) $course->id);

        session([
            'admin_course_id' => (int) $course->id,
            'admin_course_title' => (string) $course->title,
            'admin_course_slug' => (string) $course->slug,
        ]);

        return $next($request);
    }
}
