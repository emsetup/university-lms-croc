<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated Ранее открывало отчёт преподавателя по ?key=; маршруты перенесены на сессию портала (EnsureLearner + EnsurePortalStaff).
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
