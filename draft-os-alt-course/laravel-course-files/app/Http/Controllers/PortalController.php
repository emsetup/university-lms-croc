<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Learner;
use App\Services\CourseScoringService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PortalController extends Controller
{
    public function __construct(private CourseScoringService $scoring) {}

    public function index(Request $request): View
    {
        $courses = Course::query()
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $enrollmentsByCourseId = [];
        $progressByCourseId = [];
        if (session('learner_id')) {
            /** @var Learner $learner */
            $learner = Learner::query()
                ->with(['moduleProgresses', 'finalLabResult'])
                ->findOrFail(session('learner_id'));

            $enrollments = CourseEnrollment::query()
                ->where('learner_id', $learner->id)
                ->get();
            foreach ($enrollments as $e) {
                $enrollmentsByCourseId[(int) $e->course_id] = $e;
            }

            // Пока у нас один “контентный” курс (совместимость с текущей архитектурой модулей).
            foreach ($courses as $c) {
                $progressByCourseId[(int) $c->id] = ($c->slug === 'alt-os-features')
                    ? $this->scoring->certificateCoursePercent($learner)
                    : 0;
            }
        }

        return view('portal.index', [
            'courses' => $courses,
            'showLogin' => (bool) $request->query('login', false),
            'enrollmentsByCourseId' => $enrollmentsByCourseId,
            'progressByCourseId' => $progressByCourseId,
        ]);
    }
}

