<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseEnrollment;
use App\Models\Learner;
use App\Services\CourseScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class PortalController extends Controller
{
    public function __construct(private CourseScoringService $scoring) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! session('learner_id') && config('oidc.enabled') && config('oidc.required')) {
            return redirect()->route('oidc.login');
        }

        $courses = Course::query()
            ->where('is_published', true)
            ->where('is_archived', false)
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

            foreach ($courses as $c) {
                $courseId = (int) $c->id;
                $hasModules = Schema::hasTable('course_modules')
                    && CourseModule::query()->where('course_id', $courseId)->exists();
                $progressByCourseId[$courseId] = $hasModules
                    ? $this->scoring->certificateCoursePercent($learner, $courseId)
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

