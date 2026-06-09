<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Services\LearnerCourseAvailability;
use App\Support\LearnerPreviewContext;
use App\Support\StaffImpersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Блокирует доступ к треку обучения для черновиков и архивных курсов.
 */
final class EnsureLearnerCourseActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $courseId = (int) $request->route('course', 0);
        if ($courseId < 1) {
            $courseId = LearnerPreviewContext::courseId($request);
        }

        if ($courseId < 1) {
            return redirect()->route('portal')->with('err', 'Выберите курс и нажмите «Начать обучение».');
        }

        $course = Course::query()->find($courseId);
        if ($course === null) {
            if (LearnerPreviewContext::isActive($request)) {
                session()->forget([
                    StaffImpersonation::SESSION_COURSE_ID,
                    StaffImpersonation::SESSION_COURSE_TITLE,
                ]);
            } else {
                session()->forget(['course_id', 'course_title']);
            }

            return redirect()->route('portal')->with('err', 'Курс не найден.');
        }

        if (! LearnerCourseAvailability::isOpenForLearning($course)) {
            if (LearnerPreviewContext::isActive($request)) {
                session()->forget([
                    StaffImpersonation::SESSION_COURSE_ID,
                    StaffImpersonation::SESSION_COURSE_TITLE,
                ]);
            } else {
                session()->forget(['course_id', 'course_title']);
            }

            return redirect()
                ->route('account')
                ->with('err', 'Этот курс снят с обучения. Прогресс и сертификаты сохранены в личном кабинете.');
        }

        if (LearnerPreviewContext::courseId($request) !== (int) $course->id) {
            LearnerPreviewContext::selectCourse((int) $course->id, (string) $course->title);
        }

        return $next($request);
    }
}
