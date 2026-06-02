<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\Learner;
use App\Services\LearnerCourseAvailability;
use App\Support\OidcSignInRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PortalEnrollController extends Controller
{
    public function store(Request $request, int $course): RedirectResponse
    {
        if (! session('learner_id')) {
            if (config('oidc.enabled') && config('oidc.required')) {
                return OidcSignInRedirect::toOidcLogin($request);
            }

            return redirect()->route('portal', ['login' => 1])->with('err', 'Сначала войдите по корпоративной почте.');
        }

        $c = Course::query()->findOrFail($course);
        $learner = Learner::query()->findOrFail((int) session('learner_id'));
        $next = (string) $request->input('next', '');
        $certificateOnly = $next === 'certificate';

        if (! LearnerCourseAvailability::isOpenForLearning($c)) {
            if ($certificateOnly && LearnerCourseAvailability::learnerHasIssuedCertificate($learner, (int) $c->id)) {
                session([
                    'course_id' => $c->id,
                    'course_title' => $c->title,
                ]);

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

        session([
            'course_id' => $c->id,
            'course_title' => $c->title,
        ]);

        if ($certificateOnly) {
            return redirect()->route('certificate')->with('ok', 'Курс выбран.');
        }
        if ($next === 'account') {
            return redirect()->route('account')->with('ok', 'Курс выбран.');
        }
        if ($next === 'module') {
            $moduleId = (int) $request->input('module', 0);
            if ($moduleId > 0 && CourseModule::query()->where('course_id', $c->id)->where('id', $moduleId)->exists()) {
                return redirect()->route('modules.hub', ['module' => $moduleId])
                    ->with('ok', 'Продолжаем обучение.');
            }

            return redirect()->route('course.dashboard', ['course' => $c->id])->with('ok', 'Курс выбран.');
        }
        if ($next === 'final_lab') {
            return redirect()->route('final-lab')->with('ok', 'Курс выбран.');
        }

        return redirect()->route('course.dashboard', ['course' => $c->id])->with('ok', 'Курс начат. Удачного обучения!');
    }
}

