<?php

namespace App\Http\Middleware;

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
                ->route('admin.courses.index', ['key' => (string) $request->query('key', '')])
                ->with('err', 'Сначала выберите курс.');
        }

        return $next($request);
    }
}

