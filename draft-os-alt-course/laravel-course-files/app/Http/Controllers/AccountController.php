<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Learner;
use App\Services\CourseScoringService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function __construct(private CourseScoringService $scoring) {}

    public function __invoke(Request $request): View
    {
        /** @var Learner $learner */
        $learner = Learner::query()
            ->with(['moduleProgresses', 'finalLabResult'])
            ->findOrFail((int) session('learner_id'));

        $courses = Course::query()->orderBy('sort')->orderBy('id')->get();
        $enrollments = CourseEnrollment::query()
            ->where('learner_id', $learner->id)
            ->get()
            ->keyBy(fn (CourseEnrollment $e) => (int) $e->course_id);

        $rows = [];
        foreach ($courses as $c) {
            $e = $enrollments->get((int) $c->id);
            $started = (bool) ($e && $e->started_at);
            $pct = ($c->slug === 'alt-os-features') ? $this->scoring->certificateCoursePercent($learner) : 0;
            $certAvailable = ($c->slug === 'alt-os-features') ? (bool) optional($learner->finalLabResult)->passed : false;

            $rows[] = [
                'course' => $c,
                'started' => $started,
                'started_at' => $e?->started_at,
                'progress_pct' => (int) $pct,
                'certificate_available' => $certAvailable,
            ];
        }

        return view('account', [
            'learner' => $learner,
            'rows' => $rows,
        ]);
    }
}

