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

        return $next($request);
    }
}

