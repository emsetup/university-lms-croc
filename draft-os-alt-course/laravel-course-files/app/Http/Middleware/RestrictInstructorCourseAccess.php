<?php

namespace App\Http\Middleware;

use App\Services\PortalStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Преподаватель курса: только просмотр статистики обучающихся по назначенному курсу.
 */
final class RestrictInstructorCourseAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = app(PortalStaffAccess::class);
        if (! $gate->isInstructor()) {
            return $next($request);
        }

        $name = (string) ($request->route()?->getName() ?? '');
        if (str_starts_with($name, 'admin.learners.course')) {
            return $next($request);
        }

        abort(403, 'Преподаватель курса может только просматривать статистику обучающихся по назначенному курсу.');
    }
}
