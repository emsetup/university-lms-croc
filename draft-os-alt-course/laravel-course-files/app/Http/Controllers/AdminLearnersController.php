<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Learner;
use App\Services\CourseScoringService;
use App\Services\TeacherCourseAnalyticsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminLearnersController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring,
        private TeacherCourseAnalyticsService $analytics
    ) {}

    public function indexPortal(Request $request): View
    {
        $adminKey = (string) $request->query('key', '');

        $courses = Course::query()
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(function (Course $c) {
                if ($c->slug === 'alt-os-features') {
                    $enrollmentIds = DB::table('course_enrollments')
                        ->where('course_id', $c->id)
                        ->select('learner_id');
                    $progressIds = DB::table('module_progress')->select('learner_id');
                    $finalIds = DB::table('final_lab_results')->select('learner_id');

                    $enrolled = DB::query()
                        ->fromSub($enrollmentIds->union($progressIds)->union($finalIds), 'u')
                        ->distinct()
                        ->count('learner_id');

                    $startedEnrollmentIds = DB::table('course_enrollments')
                        ->where('course_id', $c->id)
                        ->whereNotNull('started_at')
                        ->select('learner_id');

                    $started = DB::query()
                        ->fromSub($startedEnrollmentIds->union($progressIds)->union($finalIds), 'u')
                        ->distinct()
                        ->count('learner_id');
                } else {
                    $enrolled = CourseEnrollment::query()->where('course_id', $c->id)->count();
                    $started = CourseEnrollment::query()->where('course_id', $c->id)->whereNotNull('started_at')->count();
                }

                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'slug' => $c->slug,
                    'enrolled' => (int) $enrolled,
                    'started' => (int) $started,
                ];
            });

        return view('admin.learners-portal', [
            'adminKey' => $adminKey,
            'courses' => $courses,
        ]);
    }

    public function indexCourse(Request $request): View
    {
        $adminKey = (string) $request->query('key', '');
        $courseId = (int) session('admin_course_id');
        $course = Course::query()->findOrFail($courseId);

        if ($course->slug === 'alt-os-features') {
            // Для курса "Альт" уже есть полноценная сводка (прогресс/баллы/карточки).
            return view('teacher-course-report', [
                'learnerRows' => $this->analytics->learnerRows(),
            ]);
        }

        // Новая модель: enrollments. Исторически учившиеся в "Альт" могли не иметь enrollment-строк,
        // поэтому для alt-os-features дополнительно подтягиваем learners с прогрессом/итоговой лабой.
        $learners = Learner::query()
            ->select(['id', 'email'])
            ->with([
                'courseEnrollments:id,course_id,learner_id,started_at,last_seen_at',
                'moduleProgresses:id,learner_id,updated_at',
                'finalLabResult:id,learner_id,updated_at',
            ])
            ->when($course->slug === 'alt-os-features', function ($q) use ($courseId) {
                $q->where(function ($qq) use ($courseId) {
                    $qq->whereHas('courseEnrollments', fn ($e) => $e->where('course_id', $courseId))
                        ->orWhereHas('moduleProgresses')
                        ->orWhereHas('finalLabResult');
                });
            }, function ($q) use ($courseId) {
                $q->whereHas('courseEnrollments', fn ($e) => $e->where('course_id', $courseId));
            })
            ->orderBy('email')
            ->get();

        $rows = [];
        foreach ($learners as $learner) {
            $enrollment = $learner->courseEnrollments->firstWhere('course_id', $courseId);

            $startedAt = $enrollment?->started_at;
            $lastSeenAt = $enrollment?->last_seen_at;

            // Фоллбек для старых УЗ: если нет enrollment, берём примерные даты из активности.
            if ($course->slug === 'alt-os-features' && $startedAt === null) {
                $activity = $learner->moduleProgresses
                    ->map(fn ($p) => $p->updated_at)
                    ->filter()
                    ->sort()
                    ->values();

                $startedAt = $activity->first();
                $lastSeenAt = $activity->last() ?: $learner->finalLabResult?->updated_at;
            }

            $pct = 0;
            if ($course->slug === 'alt-os-features') {
                $learner->loadMissing(['moduleProgresses', 'finalLabResult']);
                $pct = $this->scoring->certificateCoursePercent($learner);
            }

            $rows[] = [
                'email' => $learner->email ?: '—',
                'started_at' => $startedAt,
                'last_seen_at' => $lastSeenAt,
                'progress_pct' => (int) $pct,
            ];
        }

        return view('admin.learners-course', [
            'adminKey' => $adminKey,
            'course' => $course,
            'rows' => $rows,
        ]);
    }
}

