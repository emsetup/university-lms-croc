<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Models\Learner;
use App\Support\LearnerPreviewContext;
use App\Support\StaffImpersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Страница сертификата: активный курс или уже выданный сертификат по архивному.
 */
final class EnsureLearnerCertificateCourse
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $courseId = LearnerPreviewContext::courseId($request);
        if ($courseId < 1) {
            return redirect()->route('account')->with('err', 'Сначала выберите курс в личном кабинете.');
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

            return redirect()->route('account')->with('err', 'Курс не найден.');
        }

        if (LearnerCourseAvailability::isOpenForLearning($course)) {
            return $next($request);
        }

        $learner = Learner::query()->find(LearnerPreviewContext::learnerId($request));
        if ($learner !== null && LearnerCourseAvailability::learnerHasIssuedCertificate($learner, $courseId)) {
            return $next($request);
        }

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
            ->with('err', 'Курс снят с обучения. Просмотр сертификата доступен только для уже выданных документов.');
    }
}
