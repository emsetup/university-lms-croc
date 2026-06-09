<?php

namespace App\Http\Middleware;

use App\Support\LearnerPreviewContext;
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
        if (LearnerPreviewContext::courseId($request) <= 0) {
            return redirect()->route('portal')->with('err', 'Выберите курс и нажмите «Начать обучение».');
        }

        return $next($request);
    }
}

