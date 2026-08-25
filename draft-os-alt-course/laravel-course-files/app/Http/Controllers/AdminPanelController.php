<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\FinalLabResult;
use App\Models\Learner;
use App\Services\CourseScoringService;
use App\Services\PortalActivityFeedService;
use App\Services\PortalStaffAccess;
use App\Support\PortalChangelog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AdminPanelController extends Controller
{
    public function show(Request $request): View
    {
        session()->forget(['admin_course_id', 'admin_course_title', 'admin_course_slug']);

        $gate = app(PortalStaffAccess::class);
        $scopedIds = $this->scopedCourseIds($gate);

        $data = $this->buildDashboardPayload($gate, $scopedIds, 14);

        return view('admin.panel', $data);
    }

    public function activity(Request $request, PortalActivityFeedService $feedService): View
    {
        session()->forget(['admin_course_id', 'admin_course_title', 'admin_course_slug']);

        $gate = app(PortalStaffAccess::class);
        $scopedIds = $this->scopedCourseIds($gate);
        $filters = $this->activityFiltersFromRequest($request);

        return view('admin.activity', [
            'activityFilters' => $filters,
            'activityKinds' => PortalActivityFeedService::KIND_LABELS,
            'activityEmails' => $feedService->suggestEmails($scopedIds),
            'activityFeedUrl' => route('admin.activity.feed'),
        ]);
    }

    public function activityFeed(Request $request, PortalActivityFeedService $feedService): JsonResponse
    {
        $gate = app(PortalStaffAccess::class);
        $scopedIds = $this->scopedCourseIds($gate);
        $filters = $this->activityFiltersFromRequest($request);
        $limit = min(500, max(1, (int) $request->query('limit', 120)));

        $items = $feedService->feed($scopedIds, $filters, $limit);

        return response()->json([
            'items' => $feedService->serializeForJson($items),
            'generated_at' => now()->toIso8601String(),
            'total' => $items->count(),
        ]);
    }

    public function certificates(Request $request): ViewContract
    {
        $courseId = (int) session('admin_course_id', 0);

        $courseCompletedCount = 0;
        if ($courseId > 0 && Schema::hasTable('final_lab_results')) {
            $courseCompletedCount = (int) FinalLabResult::query()
                ->where('course_id', $courseId)
                ->whereNotNull('completed_at')
                ->count();
        }

        $items = FinalLabResult::query()
            ->with('learner:id,email')
            ->whereNotNull('certificate_full_name')
            ->whereNotNull('certificate_serial')
            ->when($courseId > 0, fn ($q) => $q->where('course_id', $courseId))
            ->orderByDesc('certificate_issued_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        foreach ($items as $row) {
            if ($row->certificate_issued_at === null) {
                $resolved = $this->resolveIssuedAtFromSerial((string) $row->certificate_serial);
                if ($resolved !== null) {
                    $row->certificate_issued_at = $resolved;
                    $row->save();
                }
            }
        }

        return view('admin.certificates', [
            'items' => $items,
            'courseCompletedCount' => $courseCompletedCount,
        ]);
    }

    /**
     * Поиск для палитры команд (курсы, модули, обучающиеся по правам сотрудника).
     */
    public function commandPaletteSearch(Request $request): JsonResponse
    {
        $gate = app(PortalStaffAccess::class);
        $qRaw = trim((string) $request->query('q', ''));
        if (mb_strlen($qRaw) > 120) {
            $qRaw = mb_substr($qRaw, 0, 120);
        }

        $scopedIds = $this->scopedCourseIds($gate);
        $like = '%'.addcslashes($qRaw, '%_\\').'%';

        $learners = [];
        if ($gate->canViewPortalLearners() && $qRaw !== '' && Schema::hasTable('learners')) {
            $learners = Learner::query()
                ->where('email', 'like', $like)
                ->orderBy('email')
                ->limit(12)
                ->get(['id', 'email'])
                ->map(static fn (Learner $l) => [
                    'id' => (int) $l->id,
                    'email' => (string) $l->email,
                    'url' => route('admin.learners.people.detail', ['learner' => $l->id]),
                ])
                ->all();
        }

        $coursesPayload = [];
        if (Schema::hasTable('courses')) {
            $cq = Course::query()->where('is_archived', false);
            if (is_array($scopedIds)) {
                $cq->whereIn('id', $scopedIds);
            }
            if ($qRaw !== '') {
                $cq->where('title', 'like', $like);
            }
            $limit = $qRaw === '' ? 6 : 12;
            $coursesPayload = $cq
                ->orderBy('sort')
                ->orderBy('id')
                ->limit($limit)
                ->get(['id', 'title', 'slug'])
                ->map(static fn (Course $c) => [
                    'id' => (int) $c->id,
                    'title' => (string) $c->title,
                    'slug' => (string) $c->slug,
                    'url' => route('admin.courses.enter', ['course' => $c->id]),
                ])
                ->all();
        }

        $modulesPayload = [];
        if ($qRaw !== '' && Schema::hasTable('course_modules')) {
            $mq = CourseModule::query()->with(['course:id,title,slug']);
            if (is_array($scopedIds)) {
                $mq->whereIn('course_id', $scopedIds);
            }
            $mq->where('title', 'like', $like);
            $modulesPayload = $mq
                ->orderBy('course_id')
                ->orderBy('sort')
                ->orderBy('id')
                ->limit(12)
                ->get()
                ->map(static function (CourseModule $m) {
                    $slug = (string) ($m->course?->slug ?? '');
                    if ($slug === '') {
                        return null;
                    }

                    return [
                        'id' => (int) $m->id,
                        'title' => (string) $m->title,
                        'course_title' => (string) ($m->course?->title ?? ''),
                        'course_slug' => $slug,
                        'url' => route('admin.theory.edit', [
                            'adminCourse' => $slug,
                            'module' => $m->effectiveContentIndex(),
                        ]),
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        return response()->json([
            'query' => $qRaw,
            'learners' => $learners,
            'courses' => $coursesPayload,
            'modules' => $modulesPayload,
        ]);
    }

    public function certificateShow(Request $request, Course $adminCourse, FinalLabResult $result, CourseScoringService $scoring): ViewContract
    {
        $resultCourseId = (int) ($result->course_id ?? 0);
        if ($resultCourseId > 0 && $resultCourseId !== (int) $adminCourse->id) {
            abort(404);
        }

        $result->loadMissing('learner');
        abort_unless($result->learner !== null, 404);
        $result->learner->loadMissing('moduleProgresses');

        $courseIdOverride = $resultCourseId > 0 ? $resultCourseId : null;
        $certCoursePercent = $scoring->certificateCoursePercent($result->learner, $courseIdOverride, $result);
        $certTier = $scoring->certificateTier($certCoursePercent, $courseIdOverride);

        return view('admin.certificate-preview', [
            'row' => $result,
            'certCoursePercent' => $certCoursePercent,
            'certTier' => $certTier,
        ]);
    }

    /**
     * @param  array<int, int>|null  $scopedIds  null — все курсы; [] — нет доступных курсов
     * @return array<string, mixed>
     */
    private function buildDashboardPayload(PortalStaffAccess $gate, ?array $scopedIds, int $activityLimit): array
    {
        $metrics = [
            'courses_total' => 0,
            'courses_published' => 0,
            'learners_enrolled' => 0,
            'learners_active' => 0,
            'completed_cert_learners' => 0,
            'completed_pct' => null,
            'certificates' => 0,
        ];

        $coursesQuick = collect();
        $activity = collect();

        $emptyScope = is_array($scopedIds) && $scopedIds === [];

        if (! $emptyScope && Schema::hasTable('courses')) {
            $courseQuery = Course::query()->where('is_archived', false);
            if (is_array($scopedIds)) {
                $courseQuery->whereIn('id', $scopedIds);
            }
            $metrics['courses_total'] = (int) (clone $courseQuery)->count();
            $metrics['courses_published'] = (int) (clone $courseQuery)->where('is_published', true)->count();

            $coursesQuick = $courseQuery
                ->orderBy('sort')
                ->orderBy('id')
                ->get()
                ->map(function (Course $c) {
                    $cid = (int) $c->id;
                    $enrolled = $this->courseParticipantCount($cid);
                    $completed = 0;
                    if (Schema::hasTable('final_lab_results')) {
                        $completed = (int) DB::table('final_lab_results')
                            ->where('course_id', $cid)
                            ->whereNotNull('certificate_full_name')
                            ->whereNotNull('certificate_serial')
                            ->distinct()
                            ->count('learner_id');
                    }
                    $pct = $enrolled > 0 ? (int) round(100 * $completed / $enrolled) : 0;

                    return [
                        'course' => $c,
                        'enrolled' => $enrolled,
                        'completed' => $completed,
                        'progress_pct' => $pct,
                    ];
                });
        }

        if (! $emptyScope && Schema::hasTable('course_enrollments')) {
            $enQ = CourseEnrollment::query();
            if (is_array($scopedIds)) {
                $enQ->whereIn('course_id', $scopedIds);
            }
            $metrics['learners_enrolled'] = (int) (clone $enQ)->distinct()->count('learner_id');
            $metrics['learners_active'] = (int) (clone $enQ)
                ->whereNotNull('started_at')
                ->distinct()
                ->count('learner_id');
        }

        if (! $emptyScope && Schema::hasTable('final_lab_results')) {
            $flQ = FinalLabResult::query();
            if (is_array($scopedIds)) {
                $flQ->whereIn('course_id', $scopedIds);
            }
            $metrics['completed_cert_learners'] = (int) (clone $flQ)
                ->whereNotNull('certificate_full_name')
                ->whereNotNull('certificate_serial')
                ->distinct()
                ->count('learner_id');
            $metrics['certificates'] = (int) (clone $flQ)
                ->whereNotNull('certificate_full_name')
                ->whereNotNull('certificate_serial')
                ->count();
        }

        if ($metrics['learners_enrolled'] > 0) {
            $metrics['completed_pct'] = (int) round(
                100 * $metrics['completed_cert_learners'] / $metrics['learners_enrolled']
            );
        }

        if (! $emptyScope) {
            $activity = app(PortalActivityFeedService::class)->feed($scopedIds, [], $activityLimit);
        }

        $editableCourseIds = match (true) {
            $gate->isPortalAdmin(), $gate->isCourseModerator() => null,
            $gate->isPortalAuditor(), $gate->isCourseCreator() => $gate->ownedCourseIds()->flip()->all(),
            $gate->isCourseEditor() => $gate->editableCourseIds()->flip()->all(),
            default => $gate->assignedCourseIds()->flip()->all(),
        };

        return [
            'dashMetrics' => $metrics,
            'dashActivity' => $activity,
            'dashCoursesQuick' => $coursesQuick,
            'dashEditableCourseIds' => $editableCourseIds,
            'dashCanCreateCourse' => $gate->canCreateCourses(),
            'changelogEntries' => PortalChangelog::forDashboard(),
        ];
    }

    /** @return array{date_from: string, date_to: string, user: string, kinds: list<string>} */
    private function activityFiltersFromRequest(Request $request): array
    {
        $kinds = $request->query('kinds', $request->query('kind', []));
        if (is_string($kinds)) {
            $kinds = array_filter(array_map('trim', explode(',', $kinds)));
        }

        return [
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'user' => trim((string) $request->query('user', '')),
            'kinds' => array_values(array_intersect(
                array_keys(PortalActivityFeedService::KIND_LABELS),
                (array) $kinds
            )),
        ];
    }

    private function courseParticipantCount(int $courseId): int
    {
        if (! Schema::hasTable('course_enrollments')) {
            return 0;
        }

        $enrollmentIds = DB::table('course_enrollments')
            ->where('course_id', $courseId)
            ->select('learner_id');

        if (! Schema::hasTable('module_progress') && ! Schema::hasTable('final_lab_results')) {
            return (int) DB::table('course_enrollments')->where('course_id', $courseId)->count();
        }

        $union = $enrollmentIds;
        if (Schema::hasTable('module_progress')) {
            $union = $union->union(
                DB::table('module_progress')->where('course_id', $courseId)->select('learner_id')
            );
        }
        if (Schema::hasTable('final_lab_results')) {
            $union = $union->union(
                DB::table('final_lab_results')->where('course_id', $courseId)->select('learner_id')
            );
        }

        return (int) DB::query()
            ->fromSub($union, 'u')
            ->distinct()
            ->count('learner_id');
    }

    /** @return array<int, int>|null */
    private function scopedCourseIds(PortalStaffAccess $gate): ?array
    {
        if ($gate->isInstructor() || $gate->isCourseTester()) {
            return $gate->assignedCourseIds()->map(fn ($id) => (int) $id)->values()->all();
        }
        if ($gate->isCourseCreator()) {
            return $gate->ownedCourseIds()->map(fn ($id) => (int) $id)->values()->all();
        }
        if ($gate->isCourseEditor()) {
            return $gate->editableCourseIds()->map(fn ($id) => (int) $id)->values()->all();
        }

        return null;
    }

    private function resolveIssuedAtFromSerial(string $serial): ?Carbon
    {
        if (preg_match('/CROC-ALT-(\d{8})-/', $serial, $m) !== 1) {
            return null;
        }
        if (! Carbon::hasFormat($m[1], 'Ymd')) {
            return null;
        }
        $date = Carbon::createFromFormat('Ymd', $m[1]);

        return $date->startOfDay();
    }
}
