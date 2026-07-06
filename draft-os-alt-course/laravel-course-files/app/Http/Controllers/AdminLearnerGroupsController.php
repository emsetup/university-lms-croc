<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseLearnerGroup;
use App\Models\Learner;
use App\Models\PortalLearnerGroup;
use App\Services\PortalStaffAccess;
use App\Support\LearnerDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminLearnerGroupsController extends Controller
{
    public function portalIndex(Request $request): View
    {
        app(PortalStaffAccess::class)->assertCanViewPortalLearners();

        $groups = PortalLearnerGroup::query()
            ->with('members:id,email,sso_display_name')
            ->withCount('members')
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        $learners = Learner::query()
            ->orderBy('email')
            ->limit(500)
            ->get(['id', 'email', 'sso_display_name']);

        return view('admin.learner-groups-portal', [
            'groups' => $groups,
            'learners' => $learners,
            'tab' => 'portal',
        ]);
    }

    public function portalStore(Request $request): RedirectResponse
    {
        app(PortalStaffAccess::class)->assertCanViewPortalLearners();
        $data = $this->validateGroupPayload($request);
        $group = PortalLearnerGroup::query()->create([
            'name' => $data['name'],
            'description' => $data['description'],
            'color' => $data['color'],
            'sort' => $data['sort'],
        ]);
        $group->members()->sync($data['member_ids']);

        return redirect()->route('admin.learner-groups.portal')->with('ok', 'Группа создана.');
    }

    public function portalUpdate(Request $request, PortalLearnerGroup $group): RedirectResponse
    {
        app(PortalStaffAccess::class)->assertCanViewPortalLearners();
        $data = $this->validateGroupPayload($request);
        $group->update([
            'name' => $data['name'],
            'description' => $data['description'],
            'color' => $data['color'],
            'sort' => $data['sort'],
        ]);
        $group->members()->sync($data['member_ids']);

        return redirect()->route('admin.learner-groups.portal')->with('ok', 'Группа обновлена.');
    }

    public function portalDestroy(PortalLearnerGroup $group): RedirectResponse
    {
        app(PortalStaffAccess::class)->assertCanViewPortalLearners();
        $group->delete();

        return redirect()->route('admin.learner-groups.portal')->with('ok', 'Группа удалена.');
    }

    public function courseIndex(Request $request, Course $adminCourse): View
    {
        $course = $adminCourse;
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $course->id);

        $groups = CourseLearnerGroup::query()
            ->where('course_id', (int) $course->id)
            ->withCount('members')
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        $enrolledLearnerIds = $course->enrollments()->pluck('learner_id')->map(fn ($id) => (int) $id)->all();
        $learners = $enrolledLearnerIds !== []
            ? Learner::query()->whereIn('id', $enrolledLearnerIds)->orderBy('email')->get(['id', 'email', 'sso_display_name'])
            : collect();

        $tp = ['adminCourse' => $course->slug];

        return view('admin.learner-groups-course', [
            'course' => $course,
            'groups' => $groups,
            'learners' => $learners,
            'ap' => $tp,
            'settingsTab' => 'gruppy',
        ]);
    }

    public function courseStore(Request $request, Course $adminCourse): RedirectResponse
    {
        $course = $adminCourse;
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $course->id);
        $data = $this->validateGroupPayload($request, (int) $course->id);
        $group = CourseLearnerGroup::query()->create([
            'course_id' => (int) $course->id,
            'name' => $data['name'],
            'description' => $data['description'],
            'color' => $data['color'],
            'sort' => $data['sort'],
        ]);
        $group->members()->sync($data['member_ids']);

        return redirect()->route('admin.course.settings', ['adminCourse' => $course->slug, 'tab' => 'gruppy'])
            ->with('ok', 'Группа курса создана.');
    }

    public function courseUpdate(Request $request, Course $adminCourse, CourseLearnerGroup $group): RedirectResponse
    {
        $course = $adminCourse;
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $course->id);
        abort_unless((int) $group->course_id === (int) $course->id, 404);
        $data = $this->validateGroupPayload($request, (int) $course->id);
        $group->update([
            'name' => $data['name'],
            'description' => $data['description'],
            'color' => $data['color'],
            'sort' => $data['sort'],
        ]);
        $group->members()->sync($data['member_ids']);

        return redirect()->route('admin.course.settings', ['adminCourse' => $course->slug, 'tab' => 'gruppy'])
            ->with('ok', 'Группа курса обновлена.');
    }

    public function courseDestroy(Course $adminCourse, CourseLearnerGroup $group): RedirectResponse
    {
        $course = $adminCourse;
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $course->id);
        abort_unless((int) $group->course_id === (int) $course->id, 404);
        $group->delete();

        return redirect()->route('admin.course.settings', ['adminCourse' => $course->slug, 'tab' => 'gruppy'])
            ->with('ok', 'Группа курса удалена.');
    }

    public function portalSearchLearners(Request $request): JsonResponse
    {
        app(PortalStaffAccess::class)->assertCanViewPortalLearners();
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }
        $like = '%'.addcslashes($q, '%_\\').'%';
        $rows = Learner::query()
            ->where(function ($w) use ($like): void {
                $w->where('email', 'like', $like)->orWhere('sso_display_name', 'like', $like);
            })
            ->orderBy('email')
            ->limit(20)
            ->get(['id', 'email', 'sso_display_name']);

        return response()->json([
            'items' => $rows->map(fn (Learner $l) => [
                'id' => (int) $l->id,
                'label' => LearnerDisplay::portalDisplayName($l) ?: (string) $l->email,
                'email' => (string) $l->email,
            ])->values()->all(),
        ]);
    }

    /**
     * @return array{name: string, description: ?string, color: string, sort: int, member_ids: list<int>}
     */
    private function validateGroupPayload(Request $request, ?int $courseId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:learners,id'],
        ]);

        $memberIds = array_values(array_unique(array_map('intval', $data['member_ids'] ?? [])));
        if ($courseId !== null && $memberIds !== []) {
            $enrolled = Course::query()->findOrFail($courseId)->enrollments()->pluck('learner_id')->all();
            $memberIds = array_values(array_intersect($memberIds, array_map('intval', $enrolled)));
        }

        return [
            'name' => trim((string) $data['name']),
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'color' => (string) ($data['color'] ?? '#6366f1'),
            'sort' => (int) ($data['sort'] ?? 0),
            'member_ids' => $memberIds,
        ];
    }
}
