<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Доступ к редактору теории с ?key=… — совпадение с COURSE_ADMIN_TOKEN или (если задан) с TEACHER_REPORT_TOKEN,
 * чтобы преподаватель мог открыть ту же ссылку, что и для /instruktor/kurs-progress.
 */
class EnsureCourseAdminToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = trim((string) $request->query('key', ''));
        if ($provided === '') {
            abort(404);
        }

        $admin = trim((string) config('course_admin.token', ''));
        $moderator = trim((string) config('course_admin.content_moderator_token', ''));
        $teacher = trim((string) config('course.teacher_report_token', ''));

        if ($admin === '' && $teacher === '' && $moderator === '') {
            abort(404);
        }

        $okAdmin = $admin !== '' && hash_equals($admin, $provided);
        $okModerator = $moderator !== '' && hash_equals($moderator, $provided);
        $okTeacher = $teacher !== '' && hash_equals($teacher, $provided);
        if (! $okAdmin && ! $okTeacher && ! $okModerator) {
            abort(404);
        }

        if ($okModerator && ! $okAdmin && ! $okTeacher) {
            if (! $request->routeIs(
                'admin.theory.index',
                'admin.theory.preview-theory',
                'admin.theory.preview-theory-quiz',
                'admin.theory.preview-practice',
                'admin.theory.preview-module-exam',
                'admin.theory.preview-final-lab'
            )) {
                abort(404);
            }
            $request->attributes->set('course_admin_readonly', true);
        }

        return $next($request);
    }
}
