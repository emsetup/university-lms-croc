<?php

namespace App\Http\Middleware;

use App\Services\PortalActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Фиксирует заходы сотрудников в /adm (GET, после успешной авторизации).
 */
final class LogAdminActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->isMethod('GET')
            && str_starts_with(trim($request->path(), '/'), 'adm')
            && ! $request->attributes->get('staff_admin_preview_active')
        ) {
            PortalActivityLogger::recordAdminAccess(
                (int) session('learner_id', 0),
                '/'.trim($request->path(), '/')
            );
        }

        return $response;
    }
}
