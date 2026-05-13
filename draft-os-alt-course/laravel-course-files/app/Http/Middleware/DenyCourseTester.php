<?php

namespace App\Http\Middleware;

use App\Services\PortalStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Запрет для роли «тестировщик курса» (редактирование и служебные разделы). */
final class DenyCourseTester
{
    public function handle(Request $request, Closure $next): Response
    {
        app(PortalStaffAccess::class)->assertNotCourseTester();

        return $next($request);
    }
}
