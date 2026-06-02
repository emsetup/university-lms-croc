<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\FinalLabResult;
use App\Models\Learner;
use App\Models\ModuleProgress;
use App\Services\CourseModuleService;
use App\Services\CourseScoringService;
use App\Services\ModuleAccessGate;
use App\Support\DurationFormat;
use App\Support\LearnerSsoDisplayNamePersistence;
use App\Support\PortalWelcomeInitials;
use App\Support\PortalWelcomeName;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring,
        private CourseModuleService $courseModules,
        private ModuleAccessGate $accessGate,
    ) {}

    public function __invoke(): View
    {
        /** @var Learner $learner */
        $learner = Learner::query()
            ->with(['moduleProgresses', 'finalLabResults'])
            ->findOrFail((int) session('learner_id'));

        $enrolledIds = CourseEnrollment::query()
            ->where('learner_id', $learner->id)
            ->pluck('course_id');

        $progressCourseIds = ModuleProgress::query()
            ->where('learner_id', $learner->id)
            ->distinct()
            ->pluck('course_id');

        $visibleCourseIds = $enrolledIds
            ->merge($progressCourseIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn (int $id) => $id > 0)
            ->values();

        $coursesQuery = Course::query()
            ->where(function ($q): void {
                $q->where(function ($q2): void {
                    $q2->where('is_published', true)->where('is_archived', false);
                })->orWhere('is_archived', true);
            })
            ->orderBy('is_archived')
            ->orderBy('sort')
            ->orderBy('id');
        if ($visibleCourseIds->isEmpty()) {
            $coursesQuery->whereRaw('0 = 1');
        } else {
            $coursesQuery->whereIn('id', $visibleCourseIds);
        }
        $courses = $coursesQuery->get();

        $finalByCourseId = $learner->finalLabResults
            ->filter(fn (FinalLabResult $r) => (int) ($r->course_id ?? 0) > 0)
            ->keyBy(fn (FinalLabResult $r) => (int) $r->course_id);

        $courseRows = [];
        foreach ($courses as $c) {
            $courseId = (int) $c->id;
            $final = $finalByCourseId->get($courseId);
            $courseRows[] = $this->buildCourseRow($learner, $c, $final);
        }

        $completed = 0;
        $inProgress = 0;
        $totalSeconds = 0;
        foreach ($courseRows as $row) {
            $totalSeconds += (int) ($row['tracked_seconds'] ?? 0);
            if (! empty($row['course_completed'])) {
                $completed++;
            } else {
                $inProgress++;
            }
        }

        $certificates = $this->buildCertificateRows($learner);

        $portalWelcomeName = PortalWelcomeName::forLearner($learner);
        LearnerSsoDisplayNamePersistence::syncIfPossible($learner);
        $emailNorm = strtolower(trim((string) $learner->email));
        $nameNorm = strtolower(trim((string) ($portalWelcomeName ?? '')));
        if ($portalWelcomeName !== null && $emailNorm !== '' && $nameNorm === $emailNorm) {
            $portalWelcomeName = null;
        }

        $learnerInitials = PortalWelcomeInitials::from($portalWelcomeName, (string) $learner->email);

        return view('account', [
            'learner' => $learner,
            'portalWelcomeName' => $portalWelcomeName,
            'learnerInitials' => $learnerInitials,
            'courseRows' => $courseRows,
            'stats' => [
                'in_progress' => $inProgress,
                'completed' => $completed,
                'total_time_label' => DurationFormat::fromSeconds($totalSeconds),
                'certificates_count' => count($certificates),
            ],
            'certificates' => $certificates,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCourseRow(Learner $learner, Course $course, ?FinalLabResult $final): array
    {
        $courseId = (int) $course->id;
        $mods = Schema::hasTable('course_modules')
            ? $this->courseModules->orderedModulesForCourse($courseId)
            : collect();

        $modulesPassed = 0;
        $modulesTotal = $mods->count();
        $currentModuleTitle = null;
        $continueModuleId = null;
        $continueNext = 'default';

        if ($modulesTotal > 0) {
            foreach ($mods as $mod) {
                $mid = (int) $mod->id;
                $p = $learner->progressExisting($mid, $courseId);
                $examPassed = (bool) ($p?->module_exam_passed);
                if ($examPassed) {
                    $modulesPassed++;
                }
            }

            $allModulesDone = $modulesPassed >= $modulesTotal && $modulesTotal > 0;
            if ($allModulesDone && (bool) ($final?->passed)) {
                $currentModuleTitle = null;
                $continueModuleId = null;
                $continueNext = 'default';
            } else {
                foreach ($mods as $mod) {
                    $mid = (int) $mod->id;
                    $unlocked = $this->accessGate->isModuleUnlocked($learner, $mid, $courseId);
                    if (! $unlocked) {
                        continue;
                    }
                    $p = $learner->progressExisting($mid, $courseId);
                    $examPassed = (bool) ($p?->module_exam_passed);
                    $meta = $this->courseModules->displayMeta($mod);
                    $title = (string) ($meta['title'] ?? $mod->title);
                    if (! $examPassed) {
                        $currentModuleTitle = $title;
                        $continueModuleId = $mid;
                        $continueNext = 'module';

                        break;
                    }
                }

                if ($currentModuleTitle === null) {
                    $allModulesDoneInner = $modulesPassed >= $modulesTotal && $modulesTotal > 0;
                    if ($allModulesDoneInner && ! (bool) ($final?->passed)) {
                        $currentModuleTitle = 'Итоговая лабораторная работа';
                        $continueNext = 'final_lab';
                    }
                }
            }
        } else {
            $modulesTotal = 0;
            $modulesPassed = 0;
            $currentModuleTitle = 'Трек курса';
            $continueNext = 'default';
        }

        $modulesFromDb = $mods->count() > 0;

        $trackedSeconds = $this->trackedSecondsForCourse($learner, $courseId);
        $courseCompleted = $this->isCourseCompleted($learner, $courseId, $final, $mods->count());

        // Тот же расчёт, что на главной портала (баллы / максимум), а не «сдано модулей / всего».
        $progressBarPercent = (int) min(100, max(0, $this->scoring->certificateCoursePercent($learner, $courseId)));

        $certAvailable = $courseCompleted && (bool) ($final?->passed);

        return [
            'course' => $course,
            'is_archived' => (bool) $course->is_archived,
            'modules_passed' => $modulesPassed,
            'modules_total' => $modulesTotal,
            'modules_from_db' => $modulesFromDb,
            'progress_bar_percent' => $progressBarPercent,
            'current_module_title' => $currentModuleTitle,
            'continue_module_id' => $continueModuleId,
            'continue_next' => $continueNext,
            'tracked_seconds' => $trackedSeconds,
            'time_label' => DurationFormat::fromSeconds($trackedSeconds),
            'course_completed' => $courseCompleted,
            'certificate_available' => $certAvailable,
        ];
    }

    private function trackedSecondsForCourse(Learner $learner, int $courseId): int
    {
        $sum = 0;
        foreach ($learner->moduleProgresses->where('course_id', $courseId) as $p) {
            $sum += max(0, (int) ($p->seconds_theory ?? 0));
            $sum += max(0, (int) ($p->seconds_theory_quiz ?? 0));
            $sum += max(0, (int) ($p->seconds_practice ?? 0));
            $sum += max(0, (int) ($p->seconds_module_exam ?? 0));
        }

        return $sum;
    }

    private function isCourseCompleted(Learner $learner, int $courseId, ?FinalLabResult $final, int $dbModuleCount): bool
    {
        if ($dbModuleCount > 0) {
            return $this->scoring->allModulesComplete($learner, $courseId)
                && (bool) ($final?->passed);
        }

        return (bool) ($final?->passed);
    }

    /**
     * @return list<array{course_id: int, course_title: string, issued_label: string, issued_sort: int, share_anchor: string}>
     */
    private function buildCertificateRows(Learner $learner): array
    {
        $out = [];
        foreach ($learner->finalLabResults as $r) {
            if (empty($r->certificate_full_name) || empty($r->certificate_serial)) {
                continue;
            }
            $cid = (int) $r->course_id;
            if ($cid < 1) {
                continue;
            }
            $course = Course::query()->where('id', $cid)->first();
            if ($course === null) {
                continue;
            }
            $issued = $r->certificateDisplayIssueDate();
            $out[] = [
                'course_id' => $cid,
                'course_title' => (string) $course->title,
                'issued_label' => $issued->format('d.m.Y'),
                'issued_sort' => $issued->timestamp,
                'share_anchor' => 'certificate-'.$cid,
            ];
        }

        usort($out, fn (array $a, array $b) => ($b['issued_sort'] ?? 0) <=> ($a['issued_sort'] ?? 0));

        return $out;
    }
}
