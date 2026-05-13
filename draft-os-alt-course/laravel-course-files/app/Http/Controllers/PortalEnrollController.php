<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PortalEnrollController extends Controller
{
    public function store(Request $request, int $course): RedirectResponse
    {
        if (! session('learner_id')) {
            if (config('oidc.enabled') && config('oidc.required')) {
                return redirect()->route('oidc.login');
            }

            return redirect()->route('portal', ['login' => 1])->with('err', 'Сначала войдите по корпоративной почте.');
        }

        $c = Course::query()->findOrFail($course);
        if (! $c->is_published || $c->is_archived) {
            return redirect()->route('portal')->with('err', 'Этот курс недоступен для обучения.');
        }

        $enroll = CourseEnrollment::query()->firstOrCreate(
            ['course_id' => $c->id, 'learner_id' => (int) session('learner_id')],
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

        $next = (string) $request->input('next', '');
        if ($next === 'certificate') {
            return redirect()->route('certificate')->with('ok', 'Курс выбран.');
        }
        if ($next === 'account') {
            return redirect()->route('account')->with('ok', 'Курс выбран.');
        }

        return redirect()->route('course.dashboard', ['course' => $c->id])->with('ok', 'Курс начат. Удачного обучения!');
    }
}

