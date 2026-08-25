<?php

namespace App\Http\Middleware;

use App\Services\PortalStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Сводная статистика портала: администратор или аудитор (stats.view).
 */
final class EnsurePlatformStatsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $access = app()->bound(PortalStaffAccess::class)
            ? app(PortalStaffAccess::class)
            : PortalStaffAccess::fromLearnerId((int) session('learner_id', 0));

        abort_unless($access !== null && $access->canViewPlatformStats(), 404);

        return $next($request);
    }
}
