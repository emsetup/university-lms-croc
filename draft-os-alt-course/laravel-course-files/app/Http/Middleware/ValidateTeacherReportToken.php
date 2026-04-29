<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Доступ к отчёту преподавателя с ?key=... — как у редактора теории:
 * TEACHER_REPORT_TOKEN или COURSE_ADMIN_TOKEN (единая навигация /adm).
 */
class ValidateTeacherReportToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = trim((string) $request->query('key', ''));
        if ($provided === '') {
            abort(404);
        }

        $admin = trim((string) config('course_admin.token', ''));
        $teacher = trim((string) config('course.teacher_report_token', ''));

        if ($admin === '' && $teacher === '') {
            abort(404);
        }

        $okAdmin = $admin !== '' && hash_equals($admin, $provided);
        $okTeacher = $teacher !== '' && hash_equals($teacher, $provided);
        if (! $okAdmin && ! $okTeacher) {
            abort(404);
        }

        return $next($request);
    }
}
