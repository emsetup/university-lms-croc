<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceActivityLogger;
use App\Services\PortalStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Заглушка обновления для обычных обучающихся; сотрудники портала (portal_staff) проходят без ограничений.
 */
final class MaintenanceForUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('course.portal_user_maintenance', false)) {
            return $next($request);
        }

        if ($this->isPortalStaff($request)) {
            return $next($request);
        }

        if ($request->routeIs('login', 'login.store', 'logout', 'oidc.*')) {
            return $next($request);
        }

        $learnerId = (int) session('learner_id', 0);
        // Гостей не блокируем: PortalController сам отправит на OIDC / покажет витрину.
        if ($learnerId <= 0) {
            return $next($request);
        }

        MaintenanceActivityLogger::recordBlockedAccess($learnerId, $request->path());

        return response()->view('maintenance', [], 503);
    }

    private function isPortalStaff(Request $request): bool
    {
        $learnerId = (int) session('learner_id', 0);
        if ($learnerId <= 0) {
            return false;
        }

        return PortalStaffAccess::fromLearnerId($learnerId) !== null;
    }
}
