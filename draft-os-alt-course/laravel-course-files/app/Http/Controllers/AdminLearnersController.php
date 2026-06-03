<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Learner;
use App\Models\PracticeSession;
use App\Services\AdminCourseLearnerDetailService;
use App\Services\CourseModuleService;
use App\Services\CourseScoringService;
use App\Services\InstructorProgressResetService;
use App\Services\LearnerLastActivityService;
use App\Services\ModuleAccessGate;
use App\Services\PortalStaffAccess;
use App\Services\TeacherCourseAnalyticsService;
use App\Services\TeacherLearnerProfileDetailService;
use App\Support\LearnerDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class AdminLearnersController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring,
        private TeacherCourseAnalyticsService $analytics,
        private CourseModuleService $courseModules,
        private ModuleAccessGate $accessGate,
        private AdminCourseLearnerDetailService $courseLearnerDetail,
        private TeacherLearnerProfileDetailService $learnerDetail,
        private InstructorProgressResetService $instructorReset,
    ) {}

    public function indexPeople(Request $request): View
    {
        session()->forget(['admin_course_id', 'admin_course_title', 'admin_course_slug']);

        $gate = app(PortalStaffAccess::class);
        $allowedCourseIds = $this->allowedCourseIdsForGate($gate);
        $learnerCourseMap = $this->collectLearnerCourseMap($allowedCourseIds);
        $learnerIds = array_keys($learnerCourseMap);
        sort($learnerIds);

        $learners = $learnerIds === []
            ? collect()
            : Learner::query()->whereIn('id', $learnerIds)->orderBy('email')->get();

        $nameByLearner = LearnerDisplay::portalDisplayNamesByLearnerIds($learnerIds);
        $activity = app(LearnerLastActivityService::class)
            ->timestampsForLearners($learnerIds, $allowedCourseIds);

        $leftList = [];
        foreach ($learners as $learner) {
            $lid = (int) $learner->id;
            $cids = $learnerCourseMap[$lid] ?? [];
            $email = (string) ($learner->email ?? '');
            $fullName = $nameByLearner[$lid] ?? '';
            $perCourse = $activity['by_course'][$lid] ?? [];
            $leftList[] = [
                'id' => $lid,
                'email' => $email,
                'full_name' => $fullName,
                'initials' => LearnerDisplay::initials($email, $fullName),
                'course_count' => count($cids),
                'course_ids' => array_values($cids),
                'last_active_ts' => (int) ($activity['portal'][$lid] ?? 0),
                'last_active_by_course' => $perCourse,
            ];
        }

        $courseFilterOptions = Course::query()
            ->when($allowedCourseIds !== null, fn ($q) => $q->whereIn('id', $allowedCourseIds))
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'title', 'slug'])
            ->map(fn (Course $c) => [
                'id' => (int) $c->id,
                'title' => (string) $c->title,
                'slug' => (string) $c->slug,
            ])
            ->values()
            ->all();

        return view('admin.learners-people', [
            'leftList' => $leftList,
            'courseFilterOptions' => $courseFilterOptions,
        ]);
    }

    public function peopleShowJson(Request $request, Learner $learner): JsonResponse
    {
        $gate = app(PortalStaffAccess::class);
        $allowedCourseIds = $this->allowedCourseIdsForGate($gate);
        $map = $this->collectLearnerCourseMap($allowedCourseIds);
        if (! isset($map[(int) $learner->id])) {
            abort(404);
        }

        $courseIds = $map[(int) $learner->id];
        $courses = Course::query()
            ->whereIn('id', $courseIds)
            ->orderBy('title')
            ->get();

        $email = (string) ($learner->email ?? '');
        $fullName = LearnerDisplay::portalDisplayName($learner);
        $coursesOut = [];
        foreach ($courses as $course) {
            $cid = (int) $course->id;
            $track = $this->trackAndStopForCourse($learner, $course, $cid);
            $coursesOut[] = [
                'slug' => (string) $course->slug,
                'title' => (string) $course->title,
                'module_total' => $track['module_total'],
                'modules_passed' => $track['modules_passed'],
                'track_avg_percent' => $track['track_avg_percent'],
                'stopped_label' => $track['stopped_label'],
                'open_url' => route('admin.learners.course', ['adminCourse' => $course->slug]).'?user='.rawurlencode($email),
            ];
        }

        return response()->json([
            'id' => (int) $learner->id,
            'email' => $email,
            'full_name' => $fullName,
            'initials' => LearnerDisplay::initials($email, $fullName),
            'courses' => $coursesOut,
        ]);
    }

    public function indexCourse(Request $request): View
    {
        $courseId = (int) session('admin_course_id');
        app(PortalStaffAccess::class)->assertCanViewCourseLearnerStats($courseId);
        $course = Course::query()->findOrFail($courseId);
        $moduleCount = CourseScoringService::moduleCount($courseId);
        $maxCoursePoints = $moduleCount * CourseScoringService::MAX_POINTS_PER_MODULE + CourseScoringService::MAX_FINAL_LAB_POINTS;

        return view('teacher-course-report', [
            'layout' => 'layouts.admin',
            'courseTitle' => $course->title,
            'courseCounters' => $this->analytics->courseCounters($courseId),
            'learnerRows' => $this->analytics->learnerRowsForCourse($courseId),
            'adminCourseSlug' => $course->slug,
            'courseModuleCount' => $moduleCount,
            'maxCoursePoints' => $maxCoursePoints,
        ]);
    }

    public function courseLearnerShow(Request $request, Course $adminCourse, Learner $learner): View
    {
        $this->assertAdminCourseMatches($adminCourse);
        app(PortalStaffAccess::class)->assertCanViewCourseLearnerStats((int) $adminCourse->id);
        $this->assertLearnerTouchesCourse($learner, (int) $adminCourse->id);
        $courseId = (int) $adminCourse->id;

        $learner->loadMissing(['moduleProgresses', 'finalLabResults', 'courseEnrollments']);

        return view('teacher-learner-profile', [
            'learner' => $learner,
            'forcedCourse' => $adminCourse,
            'summaryRow' => $this->analytics->rowForLearner($learner, $courseId),
            'moduleReport' => $this->scoring->moduleReport($learner, $courseId),
            'modulePanels' => $this->learnerDetail->modulePanels($learner, $courseId),
        ]);
    }

    public function courseLearnerDetailJson(Request $request, Course $adminCourse, Learner $learner): JsonResponse
    {
        $this->assertAdminCourseMatches($adminCourse);
        app(PortalStaffAccess::class)->assertCanViewCourseLearnerStats((int) $adminCourse->id);
        $this->assertLearnerTouchesCourse($learner, (int) $adminCourse->id);

        $data = $this->courseLearnerDetail->buildDetail($learner, $adminCourse);

        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function courseLearnerModuleShow(Request $request, Course $adminCourse, Learner $learner, CourseModule $courseModule): View
    {
        $this->assertAdminCourseMatches($adminCourse);
        app(PortalStaffAccess::class)->assertCanViewCourseLearnerStats((int) $adminCourse->id);
        $this->assertLearnerTouchesCourse($learner, (int) $adminCourse->id);
        abort_unless((int) $courseModule->course_id === (int) $adminCourse->id, 404);

        $courseId = (int) $adminCourse->id;
        $panel = $this->learnerDetail->modulePanel($learner, (int) $courseModule->id, $courseId);
        abort_if($panel === null, 404);

        return view('teacher-learner-module', [
            'layout' => 'layouts.admin',
            'learner' => $learner,
            'forcedCourse' => $adminCourse,
            'module' => (int) $courseModule->id,
            'panel' => $panel,
            'summaryRow' => $this->analytics->rowForLearner($learner, $courseId),
        ]);
    }

    public function courseLearnerReset(Request $request, Course $adminCourse, Learner $learner, CourseModule $courseModule): JsonResponse|RedirectResponse
    {
        $this->assertAdminCourseMatches($adminCourse);
        $this->assertLearnerTouchesCourse($learner, (int) $adminCourse->id);
        abort_unless((int) $courseModule->course_id === (int) $adminCourse->id, 404);
        abort_unless(app(PortalStaffAccess::class)->canResetLearnerProgressForCourse((int) $adminCourse->id), 403);

        $request->validate([
            'step' => 'required|in:theory_quiz,module_exam,practice',
            'confirm' => 'accepted',
            'note' => 'nullable|string|max:500',
        ]);

        $courseId = (int) $adminCourse->id;
        $panel = $this->learnerDetail->modulePanel($learner, (int) $courseModule->id, $courseId);
        abort_if($panel === null, 404);

        $p = $learner->progressFor((int) $courseModule->id, $courseId);
        $step = (string) $request->input('step');

        if ($step === InstructorProgressResetService::STEP_THEORY_QUIZ) {
            $hist = $p->theory_quiz_history ?? [];
            if ((int) $p->theory_quiz_attempts < 1 && ! is_array($p->theory_quiz_last_result) && (! is_array($hist) || count($hist) === 0)) {
                return $this->resetResponse($request, false, 'Нет зафиксированных попыток теста по теории — сброс не требуется.');
            }
        }
        if ($step === InstructorProgressResetService::STEP_MODULE_EXAM) {
            $hist = $p->module_exam_history ?? [];
            if ((int) $p->module_exam_attempts < 1 && ! is_array($p->module_exam_last_result) && (! is_array($hist) || count($hist) === 0)) {
                return $this->resetResponse($request, false, 'Нет зафиксированных попыток экзамена — сброс не требуется.');
            }
        }
        if ($step === InstructorProgressResetService::STEP_PRACTICE) {
            $idx = (int) ($panel['content_source_index'] ?? 1);
            if (\App\Support\CourseModuleMeta::shouldSkipPractice($idx)) {
                return $this->resetResponse($request, false, 'В этом модуле практика не предусмотрена.');
            }
            $hasSession = PracticeSession::query()
                ->where('learner_id', $learner->id)
                ->where('course_id', $courseId)
                ->where('module_id', (int) $courseModule->id)
                ->exists();
            if (! $p->practice_done_at && ! $hasSession) {
                return $this->resetResponse($request, false, 'Практика не начиналась и не отмечена — сброс не требуется.');
            }
        }

        $this->instructorReset->reset(
            $learner,
            $courseId,
            (int) $courseModule->id,
            $step,
            $request->filled('note') ? (string) $request->input('note') : null
        );

        return $this->resetResponse($request, true, 'Сброс выполнен: у обучающегося освобождена ещё одна попытка по выбранному шагу.');
    }

    /**
     * @return JsonResponse|RedirectResponse
     */
    private function resetResponse(Request $request, bool $ok, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }

        $slug = (string) $request->route('adminCourse')?->slug;
        $params = array_merge(
            ['adminCourse' => $slug],
            (string) $request->query('key', '') !== '' ? ['key' => (string) $request->query('key')] : []
        );

        return redirect()
            ->route('admin.learners.course', $params)
            ->with($ok ? 'ok' : 'err', $message);
    }

    private function assertAdminCourseMatches(Course $adminCourse): void
    {
        abort_unless((int) session('admin_course_id') === (int) $adminCourse->id, 403);
    }

    private function assertLearnerTouchesCourse(Learner $learner, int $courseId): void
    {
        $ids = $this->analytics->learnerIdsTouchingCourse($courseId);
        abort_unless(in_array((int) $learner->id, $ids, true), 404);
    }

    /**
     * @return array<int, int>|null null — все курсы портала
     */
    private function allowedCourseIdsForGate(PortalStaffAccess $gate): ?array
    {
        if ($gate->isInstructor() || $gate->isCourseTester()) {
            return $gate->assignedCourseIds()->map(fn ($id) => (int) $id)->values()->all();
        }

        return null;
    }

    /**
     * @param  array<int, int>|null  $allowedCourseIds
     * @return array<int, list<int>> learner_id => sorted unique course ids
     */
    private function collectLearnerCourseMap(?array $allowedCourseIds): array
    {
        if (is_array($allowedCourseIds) && $allowedCourseIds === []) {
            return [];
        }

        $map = [];

        $add = function (int $learnerId, int $courseId) use (&$map, $allowedCourseIds): void {
            if ($courseId < 1 || $learnerId < 1) {
                return;
            }
            if ($allowedCourseIds !== null && ! in_array($courseId, $allowedCourseIds, true)) {
                return;
            }
            if (! isset($map[$learnerId])) {
                $map[$learnerId] = [];
            }
            $map[$learnerId][$courseId] = true;
        };

        if (Schema::hasTable('course_enrollments')) {
            $q = DB::table('course_enrollments')->select(['learner_id', 'course_id']);
            if ($allowedCourseIds !== null) {
                $q->whereIn('course_id', $allowedCourseIds);
            }
            foreach ($q->cursor() as $row) {
                $add((int) $row->learner_id, (int) $row->course_id);
            }
        }
        if (Schema::hasTable('module_progress')) {
            $q = DB::table('module_progress')->select(['learner_id', 'course_id']);
            if ($allowedCourseIds !== null) {
                $q->whereIn('course_id', $allowedCourseIds);
            }
            foreach ($q->cursor() as $row) {
                $add((int) $row->learner_id, (int) $row->course_id);
            }
        }
        if (Schema::hasTable('final_lab_results')) {
            $q = DB::table('final_lab_results')->select(['learner_id', 'course_id'])->whereNotNull('course_id');
            if ($allowedCourseIds !== null) {
                $q->whereIn('course_id', $allowedCourseIds);
            }
            foreach ($q->cursor() as $row) {
                $add((int) $row->learner_id, (int) $row->course_id);
            }
        }

        $out = [];
        foreach ($map as $learnerId => $set) {
            $ids = array_map('intval', array_keys($set));
            sort($ids);
            $out[(int) $learnerId] = $ids;
        }

        return $out;
    }

    /**
     * @return array{module_total: int, modules_passed: int, track_avg_percent: int, stopped_label: string}
     */
    private function trackAndStopForCourse(Learner $learner, Course $course, int $courseId): array
    {
        $learner->loadMissing([
            'moduleProgresses' => fn ($q) => $q->where('course_id', $courseId),
            'finalLabResults' => fn ($q) => $q->where('course_id', $courseId),
        ]);

        $mods = $this->courseModules->orderedModulesForCourse($courseId);

        if ($mods->isEmpty()) {
            $row = $this->analytics->rowForLearner($learner, $courseId);
            $moduleTotal = max(1, (int) ($row['module_count'] ?? 1));
            $modulesPassed = (int) ($row['modules_passed_count'] ?? 0);
            $trackAvg = (int) ($row['grand_total_percent'] ?? 0);
            $final = $learner->finalLabResults->firstWhere('course_id', $courseId);
            $allModulesDone = $modulesPassed >= $moduleTotal;
            $stopped = $allModulesDone
                ? (($final && $final->passed) ? 'Все этапы пройдены' : 'Итоговая лабораторная')
                : 'Продолжение обучения';

            return [
                'module_total' => $moduleTotal,
                'modules_passed' => $modulesPassed,
                'track_avg_percent' => $trackAvg,
                'stopped_label' => $stopped,
            ];
        }

        $modulesPassed = 0;
        $sumPercent = 0;
        $lines = [];
        foreach ($mods as $idx => $mod) {
            $mid = (int) $mod->id;
            $meta = $this->courseModules->displayMeta($mod);
            $sequence = $idx + 1;
            $existing = $learner->progressExisting($mid, $courseId);
            $unlocked = $this->accessGate->isModuleUnlocked($learner, $mid, $courseId);

            if ($existing === null && ! $unlocked) {
                $pct = 0;
                $examPassed = false;
            } elseif ($existing === null) {
                $pct = 0;
                $examPassed = false;
            } else {
                $pct = $this->scoring->moduleProgressPercent($existing);
                $examPassed = (bool) $existing->module_exam_passed;
            }

            if ($examPassed) {
                $modulesPassed++;
            }
            $sumPercent += $pct;
            $lines[] = [
                'sequence' => $sequence,
                'title' => (string) ($meta['title'] ?? 'Модуль'),
                'exam_passed' => $examPassed,
            ];
        }

        $moduleCount = max(1, $mods->count());
        $trackAvg = (int) round($sumPercent / $moduleCount);

        $stopped = 'Все этапы пройдены';
        foreach ($lines as $m) {
            if (! $m['exam_passed']) {
                $stopped = 'Модуль '.$m['sequence'].' · '.$m['title'];
                break;
            }
        }

        $final = $learner->finalLabResults->firstWhere('course_id', $courseId);
        if ($stopped === 'Все этапы пройдены' && $final && ! $final->passed) {
            $stopped = 'Итоговая лабораторная';
        }

        return [
            'module_total' => $moduleCount,
            'modules_passed' => $modulesPassed,
            'track_avg_percent' => $trackAvg,
            'stopped_label' => $stopped,
        ];
    }
}
