<?php

namespace App\Http\Middleware;

use App\Services\PortalStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Только администратор портала (не модератор, не преподаватель).
 */
final class EnsurePortalAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $access = app()->bound(PortalStaffAccess::class)
            ? app(PortalStaffAccess::class)
            : PortalStaffAccess::fromLearnerId((int) session('learner_id', 0));

        abort_unless($access !== null && $access->isPortalAdmin(), 404);

        return $next($request);
    }
}
