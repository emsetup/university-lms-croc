<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Services\LearnerCourseAvailability;
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
            $courseId = (int) session('course_id', 0);
        }

        if ($courseId < 1) {
            return redirect()->route('portal')->with('err', 'Выберите курс и нажмите «Начать обучение».');
        }

        $course = Course::query()->find($courseId);
        if ($course === null) {
            session()->forget(['course_id', 'course_title']);

            return redirect()->route('portal')->with('err', 'Курс не найден.');
        }

        if (! LearnerCourseAvailability::isOpenForLearning($course)) {
            session()->forget(['course_id', 'course_title']);

            return redirect()
                ->route('account')
                ->with('err', 'Этот курс снят с обучения. Прогресс и сертификаты сохранены в личном кабинете.');
        }

        if ((int) session('course_id', 0) !== (int) $course->id) {
            session([
                'course_id' => $course->id,
                'course_title' => $course->title,
            ]);
        }

        return $next($request);
    }
}
