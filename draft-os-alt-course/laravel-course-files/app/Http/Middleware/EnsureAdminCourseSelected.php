<?php

namespace App\Http\Middleware;

use App\Services\PortalStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminCourseSelected
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('admin_course_id')) {
            return redirect()
                ->route('admin.courses.index')
                ->with('err', 'Сначала выберите курс.');
        }

        $courseId = (int) session('admin_course_id', 0);
        app(PortalStaffAccess::class)->assertCanAccessCourseInAdmin($courseId);

        $routeCourse = $request->route('adminCourse');
        if ($routeCourse instanceof \App\Models\Course && (int) $routeCourse->id !== $courseId) {
            abort(403);
        }

        if (! session('admin_course_slug')) {
            $c = \App\Models\Course::query()->find($courseId);
            if ($c) {
                session(['admin_course_slug' => (string) $c->slug]);
            }
        }

        return $next($request);
    }
}

