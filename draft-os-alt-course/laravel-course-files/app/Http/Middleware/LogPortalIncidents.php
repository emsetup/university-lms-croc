<?php

namespace App\Http\Middleware;

use App\Services\PortalIncidentLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Записывает HTTP-ошибки и исключения в журнал инцидентов портала.
 */
final class LogPortalIncidents
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $throwable = $request->attributes->get('portal_incident_throwable');
        if (! $throwable instanceof Throwable) {
            $throwable = null;
        }

        PortalIncidentLogger::recordHttp($request, $response, $throwable);

        return $response;
    }
}
