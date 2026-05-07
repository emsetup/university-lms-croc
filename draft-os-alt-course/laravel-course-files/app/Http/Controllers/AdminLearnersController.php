<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Learner;
use Illuminate\Support\Facades\Schema;
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
                $courseId = (int) $c->id;

                // Участников: enrollments ∪ progress ∪ final_lab (по этому курсу, если есть поля course_id).
                $enrolled = (int) CourseEnrollment::query()->where('course_id', $courseId)->count();
                if (Schema::hasTable('module_progress') || Schema::hasTable('final_lab_results')) {
                    $enrollmentIds = DB::table('course_enrollments')
                        ->where('course_id', $courseId)
                        ->select('learner_id');

                    $progressIds = Schema::hasTable('module_progress')
                        ? DB::table('module_progress')->where('course_id', $courseId)->select('learner_id')
                        : null;
                    $finalIds = Schema::hasTable('final_lab_results')
                        ? DB::table('final_lab_results')->where('course_id', $courseId)->select('learner_id')
                        : null;

                    $union = $enrollmentIds;
                    if ($progressIds) {
                        $union = $union->union($progressIds);
                    }
                    if ($finalIds) {
                        $union = $union->union($finalIds);
                    }

                    $enrolled = (int) DB::query()
                        ->fromSub($union, 'u')
                        ->distinct()
                        ->count('learner_id');
                }

                // Завершили: есть сертификат по этому курсу.
                $completed = 0;
                if (Schema::hasTable('final_lab_results')) {
                    $completed = (int) DB::table('final_lab_results')
                        ->where('course_id', $courseId)
                        ->whereNotNull('certificate_full_name')
                        ->whereNotNull('certificate_serial')
                        ->distinct()
                        ->count('learner_id');
                }

                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'slug' => $c->slug,
                    'enrolled' => (int) $enrolled,
                    'completed' => (int) $completed,
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
                'courseTitle' => $course->title,
                'courseCounters' => $this->analytics->courseCounters($courseId),
                'learnerRows' => $this->analytics->learnerRows($courseId),
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

