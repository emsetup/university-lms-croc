<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Services\CourseChangeLogService;
use App\Services\PortalStaffAccess;
use App\Services\TeacherCourseAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class AdminCoursesController extends Controller
{
    public function __construct(private CourseChangeLogService $changeLog) {}

    public function index(Request $request): View
    {
        session()->forget(['admin_course_id', 'admin_course_title', 'admin_course_slug']);

        $gate = app(PortalStaffAccess::class);
        $analytics = app(TeacherCourseAnalyticsService::class);

        $courseModels = Course::query()
            ->with(['createdByPortalStaff.learner:id,email,sso_display_name'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $courseModels = $this->filterCourseModelsForCatalog($courseModels, $gate);

        $courseIds = $courseModels->pluck('id')->map(fn ($id) => (int) $id)->all();
        $statsByCourse = $analytics->batchCatalogStatsFast($courseIds);

        $courses = $courseModels->map(function (Course $c) use ($statsByCourse, $gate) {
            $courseId = (int) $c->id;
            $stats = $statsByCourse[$courseId] ?? [
                'enrolled' => 0,
                'completed' => 0,
                'completed_rate_pct' => 0,
            ];
            $creator = $this->changeLog->creatorForCourse($c);

            return [
                'id' => $c->id,
                'slug' => $c->slug,
                'title' => $c->title,
                'summary' => $c->summary,
                'is_published' => (bool) $c->is_published,
                'is_archived' => (bool) $c->is_archived,
                'is_collaborator' => $gate->isCollaboratorOnCourse($courseId),
                'enrolled' => (int) $stats['enrolled'],
                'completed' => (int) $stats['completed'],
                'completed_rate_pct' => (int) $stats['completed_rate_pct'],
                'creator_name' => $creator['name'],
                'creator_email' => $creator['email'],
                'creator_staff_id' => $creator['staff_id'],
                'creator_initials' => $creator['initials'],
            ];
        });

        $editableCourseIds = match (true) {
            $gate->isPortalAdmin(), $gate->isCourseModerator() => null,
            $gate->isCourseCreator() => $gate->ownedCourseIds()->merge($gate->grantedCourseIds())->unique()->flip()->all(),
            $gate->isCourseEditor() => $gate->editableCourseIds()->merge($gate->grantedCourseIds())->unique()->flip()->all(),
            $gate->isCourseContributor() => $gate->grantedCourseIds()->flip()->all(),
            $gate->isInstructor() => [],
            default => $gate->assignedCourseIds()->merge($gate->grantedCourseIds())->unique()->flip()->all(),
        };

        return view('admin.courses-index', [
            'courses' => $courses,
            'canCreateCourse' => $gate->canCreateCourses(),
            'editableCourseIds' => $editableCourseIds,
            'openCreateModal' => $request->boolean('create'),
            'canViewStaffProfiles' => $gate->canManageStaff(),
        ]);
    }

    public function catalogStats(Request $request): JsonResponse
    {
        $gate = app(PortalStaffAccess::class);
        $analytics = app(TeacherCourseAnalyticsService::class);

        $rawIds = $request->query('ids', '');
        if (is_array($rawIds)) {
            $requestedIds = array_map('intval', $rawIds);
        } else {
            $requestedIds = array_map('intval', array_filter(array_map('trim', explode(',', (string) $rawIds))));
        }
        $requestedIds = array_values(array_unique(array_filter($requestedIds, fn (int $id) => $id > 0)));
        if ($requestedIds === []) {
            return response()->json([]);
        }

        $allowedIds = array_values(array_filter(
            $requestedIds,
            fn (int $id) => $gate->canAccessCourseInAdmin($id)
        ));
        if ($allowedIds === []) {
            return response()->json([]);
        }

        sort($allowedIds);
        $cacheKey = 'admin.catalog.avg.v1.'.md5(implode(',', $allowedIds));
        $avgByCourse = Cache::remember($cacheKey, 600, fn () => $analytics->batchCatalogAvgProgress($allowedIds));

        $out = [];
        foreach ($allowedIds as $courseId) {
            $out[(string) $courseId] = [
                'avg_progress_pct' => (int) ($avgByCourse[$courseId] ?? 0),
            ];
        }

        return response()->json($out);
    }

    public function select(Request $request, int $course): RedirectResponse
    {
        $gate = app(PortalStaffAccess::class);
        $gate->assertCanAccessCourseInAdmin($course);
        $next = (string) $request->input('next', $gate->isInstructor() ? 'learners' : 'content');
        $gate->assertTesterSelectNext($next);

        $c = Course::query()->findOrFail($course);
        session([
            'admin_course_id' => $c->id,
            'admin_course_title' => $c->title,
            'admin_course_slug' => $c->slug,
        ]);

        if ($next === 'quiz') {
            return redirect()->route('admin.quiz.index', ['adminCourse' => $c->slug])->with('ok', 'Курс выбран: '.$c->title);
        }
        if ($next === 'certificates') {
            return redirect()->route('admin.certificates', ['adminCourse' => $c->slug])->with('ok', 'Курс выбран: '.$c->title);
        }
        if ($next === 'learners') {
            return redirect()->route('admin.learners.course', ['adminCourse' => $c->slug])->with('ok', 'Курс выбран: '.$c->title);
        }

        return redirect()->route('admin.theory.index', ['adminCourse' => $c->slug])->with('ok', 'Курс выбран: '.$c->title);
    }

    public function enter(Request $request, int $course): RedirectResponse
    {
        $gate = app(PortalStaffAccess::class);
        $gate->assertCanAccessCourseInAdmin($course);

        $c = Course::query()->findOrFail($course);
        session([
            'admin_course_id' => $c->id,
            'admin_course_title' => $c->title,
            'admin_course_slug' => $c->slug,
        ]);

        $next = (string) $request->query('next', $gate->isInstructor() ? 'learners' : 'content');
        $gate->assertTesterSelectNext($next);

        if ($next === 'quiz') {
            return redirect()->route('admin.quiz.index', ['adminCourse' => $c->slug]);
        }
        if ($next === 'certificates') {
            return redirect()->route('admin.certificates', ['adminCourse' => $c->slug]);
        }
        if ($next === 'learners') {
            return redirect()->route('admin.learners.course', ['adminCourse' => $c->slug]);
        }

        return redirect()->route('admin.theory.index', ['adminCourse' => $c->slug]);
    }

    public function create(Request $request): RedirectResponse
    {
        session()->forget(['admin_course_id', 'admin_course_title', 'admin_course_slug']);

        return redirect()->route('admin.courses.index', ['create' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:courses,slug'],
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_published' => ['nullable', 'in:0,1'],
            'is_archived' => ['nullable', 'in:0,1'],
        ], [
            'slug.regex' => 'Slug: только латиница/цифры и дефис (например: alt-os-features).',
        ]);

        $gate = app(PortalStaffAccess::class);
        $staffId = (int) $gate->staff()->id;

        $course = Course::query()->create([
            'created_by_portal_staff_id' => $staffId > 0 ? $staffId : null,
            'slug' => strtolower((string) $data['slug']),
            'title' => (string) $data['title'],
            'summary' => (string) ($data['summary'] ?? ''),
            'sort' => isset($data['sort']) ? (int) $data['sort'] : 100,
            'is_published' => isset($data['is_published']) ? ((string) $data['is_published'] === '1') : false,
            'is_archived' => isset($data['is_archived']) ? ((string) $data['is_archived'] === '1') : false,
            'strict_grants' => true,
            'final_lab_enabled' => false,
        ]);

        $this->changeLog->logCourseCreated($course);

        session([
            'admin_course_id' => $course->id,
            'admin_course_title' => $course->title,
            'admin_course_slug' => $course->slug,
        ]);

        return redirect()
            ->route('admin.course.settings', ['adminCourse' => $course->slug])
            ->with('ok', 'Курс создан. Добавьте модули в разделе «Модули» или заполните карточку в «Настройках курса».');
    }

    public function edit(Request $request, int $course): View
    {
        app(PortalStaffAccess::class)->assertCanEditCourseMeta($course);

        $c = Course::query()->findOrFail($course);
        $courseId = (int) $c->id;
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

        $completed = 0;
        if (Schema::hasTable('final_lab_results')) {
            $completed = (int) DB::table('final_lab_results')
                ->where('course_id', $courseId)
                ->whereNotNull('certificate_full_name')
                ->whereNotNull('certificate_serial')
                ->distinct()
                ->count('learner_id');
        }

        return view('admin.course-edit', [
            'mode' => 'edit',
            'course' => $c,
            'stats' => [
                'enrolled' => $enrolled,
                'completed' => $completed,
            ],
        ]);
    }

    public function update(Request $request, int $course): RedirectResponse
    {
        app(PortalStaffAccess::class)->assertCanEditCourseMeta($course);

        $c = Course::query()->findOrFail($course);
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:courses,slug,'.$c->id],
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_published' => ['nullable', 'in:0,1'],
            'is_archived' => ['nullable', 'in:0,1'],
        ], [
            'slug.regex' => 'Slug: только латиница/цифры и дефис (например: alt-os-features).',
        ]);

        $c->slug = strtolower((string) $data['slug']);
        $c->title = (string) $data['title'];
        $c->summary = (string) ($data['summary'] ?? '');
        $c->sort = isset($data['sort']) ? (int) $data['sort'] : 100;
        $c->is_published = isset($data['is_published']) && (string) $data['is_published'] === '1';
        $c->is_archived = isset($data['is_archived']) && (string) $data['is_archived'] === '1';
        $this->changeLog->logCourseDirty($c, 'Обновлена карточка курса');
        $c->save();

        if ((int) session('admin_course_id', 0) === (int) $c->id) {
            session(['admin_course_title' => $c->title]);
        }

        return redirect()
            ->route('admin.courses.edit', ['course' => $c->id])
            ->with('ok', 'Курс обновлён.');
    }

    public function archive(Request $request, int $course): RedirectResponse
    {
        app(PortalStaffAccess::class)->assertCanEditCourseMeta($course);

        $c = Course::query()->findOrFail($course);
        $c->is_archived = true;
        $c->is_published = false;
        $c->save();
        $this->changeLog->logCourseArchived($c);

        return redirect()
            ->route('admin.courses.edit', ['course' => $c->id])
            ->with('ok', 'Курс перенесён в архив.');
    }

    public function unarchive(Request $request, int $course): RedirectResponse
    {
        app(PortalStaffAccess::class)->assertCanEditCourseMeta($course);

        $c = Course::query()->findOrFail($course);
        $c->is_archived = false;
        $c->save();
        $this->changeLog->logCourseUnarchived($c);

        return redirect()
            ->route('admin.courses.edit', ['course' => $c->id])
            ->with('ok', 'Курс восстановлен из архива.');
    }

    public function publish(Request $request, int $course): RedirectResponse
    {
        app(PortalStaffAccess::class)->assertCanEditCourseMeta($course);

        $c = Course::query()->findOrFail($course);
        if ($c->is_archived) {
            return redirect()
                ->route('admin.courses.index')
                ->with('err', 'Нельзя опубликовать курс из архива.');
        }
        $c->is_published = true;
        $c->save();
        $this->changeLog->logCoursePublished($c);

        if ((int) session('admin_course_id', 0) === (int) $c->id) {
            session(['admin_course_title' => $c->title]);
        }

        return redirect()
            ->route('admin.courses.index')
            ->with('ok', 'Курс опубликован.');
    }

    /** @param  Collection<int, Course>  $courseModels */
    private function filterCourseModelsForCatalog(Collection $courseModels, PortalStaffAccess $gate): Collection
    {
        if ($gate->isPortalAdmin() || $gate->isCourseModerator()) {
            return $courseModels;
        }
        if ($gate->isCourseContributor()) {
            $allowed = $gate->grantedCourseIds()->flip()->all();

            return $courseModels
                ->filter(fn (Course $c) => isset($allowed[(int) $c->id]))
                ->values();
        }
        if ($gate->isInstructor() || $gate->isCourseTester()) {
            $allowed = $gate->assignedCourseIds()->flip()->all();

            return $courseModels
                ->filter(fn (Course $c) => isset($allowed[(int) $c->id]))
                ->values();
        }
        if ($gate->isCourseCreator()) {
            $owned = $gate->ownedCourseIds()->flip()->all();
            $granted = $gate->grantedCourseIds()->flip()->all();

            return $courseModels
                ->filter(fn (Course $c) => isset($owned[(int) $c->id]) || isset($granted[(int) $c->id]))
                ->values();
        }
        if ($gate->isCourseEditor()) {
            $allowed = $gate->editableCourseIds()
                ->merge($gate->grantedCourseIds())
                ->unique()
                ->flip()
                ->all();

            return $courseModels
                ->filter(fn (Course $c) => isset($allowed[(int) $c->id]))
                ->values();
        }

        $granted = $gate->grantedCourseIds()->flip()->all();
        if ($granted !== []) {
            return $courseModels
                ->filter(fn (Course $c) => isset($granted[(int) $c->id]))
                ->values();
        }

        return $courseModels;
    }
}
