<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\Learner;
use App\Services\CourseModuleService;
use App\Services\LearnerCourseAvailability;
use App\Support\LearnerPreviewContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PortalEnrollController extends Controller
{
    public function store(Request $request, int $course): RedirectResponse
    {
        $learnerId = LearnerPreviewContext::learnerId($request);
        if ($learnerId <= 0) {
            return redirect()
                ->route('portal', ['login' => 1])
                ->with('err', 'Сначала войдите в учётную запись.');
        }

        $c = Course::query()->findOrFail($course);
        $learner = Learner::query()->findOrFail($learnerId);
        $next = (string) $request->input('next', '');
        $certificateOnly = $next === 'certificate';

        if (! LearnerCourseAvailability::isOpenForLearning($c)) {
            if ($certificateOnly && LearnerCourseAvailability::learnerHasIssuedCertificate($learner, (int) $c->id)) {
                LearnerPreviewContext::selectCourse((int) $c->id, (string) $c->title);

                return redirect()->route('certificate')->with('ok', 'Сертификат по архивному курсу.');
            }

            return redirect()
                ->route('account')
                ->with('err', 'Этот курс снят с обучения. Прогресс и сертификаты сохранены в личном кабинете.');
        }

        $enroll = CourseEnrollment::query()->firstOrCreate(
            ['course_id' => $c->id, 'learner_id' => $learner->id],
            []
        );
        if ($enroll->started_at === null) {
            $enroll->started_at = now();
        }
        $enroll->last_seen_at = now();
        $enroll->save();

        LearnerPreviewContext::selectCourse((int) $c->id, (string) $c->title);

        if ($certificateOnly) {
            return redirect()->route('certificate')->with('ok', 'Курс выбран.');
        }
        if ($next === 'account') {
            return redirect()->route('account')->with('ok', 'Курс выбран.');
        }
        if ($next === 'module') {
            $moduleId = (int) $request->input('module', 0);
            $cm = $moduleId > 0
                ? app(CourseModuleService::class)->findForCourse((int) $c->id, $moduleId)
                : null;
            if ($cm !== null) {
                $seq = app(CourseModuleService::class)->sequenceForModule($cm);

                return redirect()->route('course.module.hub', [
                    'course' => (int) $c->id,
                    'module' => $seq,
                ])->with('ok', 'Продолжаем обучение.');
            }

            return redirect()->route('course.dashboard', ['course' => $c->id])->with('ok', 'Курс выбран.');
        }
        if ($next === 'final_lab') {
            return redirect()->route('final-lab')->with('ok', 'Курс выбран.');
        }

        return redirect()->route('course.dashboard', ['course' => $c->id])->with('ok', 'Курс начат. Удачного обучения!');
    }
}
