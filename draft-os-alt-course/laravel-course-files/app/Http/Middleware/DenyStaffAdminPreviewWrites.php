<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** В режиме просмотра админки от лица сотрудника запрещены изменения. */
final class DenyStaffAdminPreviewWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('staff_admin_preview_active') && ! $request->isMethodSafe()) {
            abort(403, 'В режиме просмотра админки изменения запрещены. Закройте вкладку просмотра и выполните действие от своей учётной записи.');
        }

        return $next($request);
    }
}
