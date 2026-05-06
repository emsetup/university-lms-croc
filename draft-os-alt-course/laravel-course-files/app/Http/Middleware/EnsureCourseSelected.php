<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCourseSelected
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('course_id')) {
            return redirect()->route('portal')->with('err', 'Выберите курс и нажмите «Начать обучение».');
        }

        return $next($request);
    }
}

