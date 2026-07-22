<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseContentGrant;
use App\Models\Learner;
use App\Models\PortalStaff;
use App\Services\CourseCollaboratorService;
use App\Services\PortalStaffAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AdminCourseCollaboratorsController extends Controller
{
    public function __construct(private CourseCollaboratorService $collaborators) {}

    public function invite(Request $request, Course $adminCourse): RedirectResponse
    {
        $course = $adminCourse;
        $gate = app(PortalStaffAccess::class);
        $gate->assertCanManageCollaborators((int) $course->id);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'grants' => ['required', 'array', 'min:1'],
            'grants.*.resource_type' => ['required', 'string', 'in:'.implode(',', CourseContentGrant::RESOURCE_TYPES)],
            'grants.*.resource_id' => ['nullable', 'integer', 'min:1'],
            'grants.*.permission' => ['required', 'string', 'in:'.implode(',', CourseContentGrant::PERMISSIONS)],
        ]);

        $email = strtolower((string) $data['email']);
        $staff = $this->collaborators->findStaffByEmail($email);

        if ($staff === null) {
            abort_unless($gate->canManageStaff(), 422, 'Сотрудник с таким email не найден. Попросите администратора портала добавить его в «Сотрудники» с ролью «Соавтор курса».');

            $learner = Learner::query()->firstOrCreate(['email' => $email]);
            $staff = PortalStaff::query()->create([
                'learner_id' => (int) $learner->id,
                'role' => PortalStaff::ROLE_COURSE_CONTRIBUTOR,
            ]);
            app(\App\Services\CourseChangeLogService::class)->logCollaboratorAdded($course, $staff);
        }

        abort_if((int) $staff->id === (int) ($course->created_by_portal_staff_id ?? 0), 422, 'Владелец курса уже имеет полный доступ.');

        $grants = $this->normalizeGrantsForCourse($course, $data['grants']);
        abort_if($grants === [], 422, 'Выберите хотя бы один раздел или модуль.');

        $hasEdit = collect($grants)->contains(fn (array $g) => in_array($g['permission'], [
            CourseContentGrant::PERMISSION_EDIT,
            CourseContentGrant::PERMISSION_MANAGE,
        ], true));

        if ($hasEdit) {
            $limit = $this->collaborators->collaboratorLimit();
            $count = $this->collaborators->countCollaboratorsWithEdit($course, (int) $staff->id);
            $existing = CourseContentGrant::query()
                ->where('course_id', (int) $course->id)
                ->where('portal_staff_id', (int) $staff->id)
                ->whereIn('permission', [CourseContentGrant::PERMISSION_EDIT, CourseContentGrant::PERMISSION_MANAGE])
                ->exists();
            if (! $existing && $count >= $limit) {
                abort(422, 'Достигнут лимит соавторов с правом редактирования ('.$limit.').');
            }
        }

        $this->collaborators->syncStaffGrants(
            $course,
            $staff,
            $grants,
            (int) $gate->staff()->id,
        );

        $staff->loadMissing('learner');
        app(\App\Services\Mail\PortalMailNotifier::class)->notifyCollaborator($staff, $course, $grants);

        return redirect()
            ->route('admin.course.settings', ['adminCourse' => $course->slug, 'tab' => 'soavtory'])
            ->with('ok', 'Права соавтора сохранены.');
    }

    public function remove(Request $request, Course $adminCourse, PortalStaff $portalStaff): RedirectResponse
    {
        $course = $adminCourse;
        app(PortalStaffAccess::class)->assertCanManageCollaborators((int) $course->id);

        abort_if((int) $portalStaff->id === (int) ($course->created_by_portal_staff_id ?? 0), 422, 'Нельзя удалить владельца курса.');

        $this->collaborators->removeStaffFromCourse($course, $portalStaff);

        return redirect()
            ->route('admin.course.settings', ['adminCourse' => $course->slug, 'tab' => 'soavtory'])
            ->with('ok', 'Соавтор удалён из курса.');
    }

    public function searchStaff(Request $request, Course $adminCourse): JsonResponse
    {
        app(PortalStaffAccess::class)->assertCanManageCollaborators((int) $adminCourse->id);

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $rows = PortalStaff::query()
            ->with('learner:id,email,sso_display_name')
            ->whereHas('learner', function ($lq) use ($like): void {
                $lq->where('email', 'like', $like)
                    ->orWhere('sso_display_name', 'like', $like);
            })
            ->orderBy('id')
            ->limit(15)
            ->get();

        $items = $rows->map(function (PortalStaff $staff): array {
            $learner = $staff->learner;
            $access = new PortalStaffAccess($staff);

            return [
                'id' => (int) $staff->id,
                'email' => $learner ? (string) $learner->email : '',
                'name' => $learner ? (string) ($learner->sso_display_name ?: $learner->email) : ('#'.$staff->id),
                'role' => $access->roleLabel(),
            ];
        })->values()->all();

        return response()->json(['items' => $items]);
    }

    /**
     * @param  list<array{resource_type: string, resource_id?: int|null, permission: string}>  $raw
     * @return list<array{resource_type: string, resource_id: int|null, permission: string}>
     */
    private function normalizeGrantsForCourse(Course $course, array $raw): array
    {
        $out = [];
        foreach ($raw as $grant) {
            $type = (string) ($grant['resource_type'] ?? '');
            $permission = (string) ($grant['permission'] ?? '');
            if (! in_array($type, CourseContentGrant::RESOURCE_TYPES, true)) {
                continue;
            }
            if (! in_array($permission, CourseContentGrant::PERMISSIONS, true)) {
                continue;
            }
            $resourceId = $grant['resource_id'] ?? null;
            if ($type === CourseContentGrant::RESOURCE_COURSE) {
                $resourceId = null;
            } elseif ($resourceId === null || (int) $resourceId <= 0) {
                continue;
            } else {
                $resourceId = (int) $resourceId;
                if ($type === CourseContentGrant::RESOURCE_MODULE) {
                    abort_unless(
                        \App\Models\CourseModule::query()->whereKey($resourceId)->where('course_id', (int) $course->id)->exists(),
                        422,
                        'Модуль не принадлежит курсу.'
                    );
                }
                if ($type === CourseContentGrant::RESOURCE_SECTION) {
                    abort_unless(
                        \App\Models\CourseSection::query()->whereKey($resourceId)->where('course_id', (int) $course->id)->exists(),
                        422,
                        'Раздел не принадлежит курсу.'
                    );
                }
            }
            $key = $type.':'.($resourceId ?? 'all');
            $out[$key] = [
                'resource_type' => $type,
                'resource_id' => $resourceId,
                'permission' => $permission,
            ];
        }

        return array_values($out);
    }
}
