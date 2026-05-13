<?php

namespace App\Http\Middleware;

use App\Services\PortalStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Доступ к /adm: только вошедший обучающийся с записью в portal_staff.
 */
final class EnsurePortalStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $learnerId = (int) session('learner_id', 0);
        if ($learnerId <= 0) {
            abort(404);
        }

        $access = PortalStaffAccess::fromLearnerId($learnerId);
        if ($access === null) {
            abort(404);
        }

        app()->instance(PortalStaffAccess::class, $access);
        $request->attributes->set('portal_staff_access', $access);
        if ($access->isReadOnlyCourseContent()) {
            $request->attributes->set('course_admin_readonly', true);
        }

        return $next($request);
    }
}
