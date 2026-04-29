<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Доступ к отчёту преподавателя только с ?key=... совпадающим с TEACHER_REPORT_TOKEN.
 * При неверном или пустом токене — 404 (без признаков существования страницы).
 */
class ValidateTeacherReportToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('course.teacher_report_token', ''));
        if ($expected === '') {
            abort(404);
        }

        $provided = trim((string) $request->query('key', ''));
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(404);
        }

        return $next($request);
    }
}
