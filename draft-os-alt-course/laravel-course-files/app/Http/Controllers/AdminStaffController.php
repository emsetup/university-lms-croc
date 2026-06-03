<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\PortalStaff;
use App\Models\PortalStaffGroup;
use App\Support\PortalStaffPermissionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminStaffController extends Controller
{
    public function index(Request $request): View
    {
        $staffTab = (string) $request->query('tab', 'users');
        if (! in_array($staffTab, ['users', 'groups'], true)) {
            $staffTab = 'users';
        }

        $totalStaff = PortalStaff::query()->count();
        $q = trim((string) $request->query('q', ''));
        [$staffSort, $staffDir] = $this->staffListSortParams($request);

        $items = collect();
        if ($staffTab === 'users') {
            $query = PortalStaff::query()
                ->with([
                    'learner:id,email,sso_display_name,last_login_at',
                    'courses:id,title',
                    'groups:id,name',
                ]);
            if ($q !== '') {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->whereHas('learner', function ($lq) use ($like) {
                    $lq->where('email', 'like', $like);
                });
            }
            $this->applyStaffListSort($query, $staffSort, $staffDir);
            $items = $query->get();
        }

        $groups = collect();
        $allStaffForGroups = collect();
        if ($staffTab === 'groups') {
            $groups = PortalStaffGroup::query()
                ->withCount('members')
                ->with(['permissions', 'courses:id,title', 'members.learner:id,email,sso_display_name'])
                ->orderBy('sort')
                ->orderBy('name')
                ->get();
            $allStaffForGroups = PortalStaff::query()
                ->with('learner:id,email,sso_display_name')
                ->orderBy('id')
                ->get();
        }

        return view('admin.staff-index', [
            'items' => $items,
            'staffSearch' => $q,
            'staffSearchEnabled' => $totalStaff > 5,
            'staffSort' => $staffSort,
            'staffDir' => $staffDir,
            'staffTab' => $staffTab,
            'groups' => $groups,
            'allStaffForGroups' => $allStaffForGroups,
            'permissionSections' => PortalStaffPermissionCatalog::sections(),
            'assignedPermissionKeys' => PortalStaffPermissionCatalog::ASSIGNED_SCOPE_KEYS,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.staff.index', ['add' => '1']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request, null);
        $email = strtolower((string) $data['email']);
        $learner = Learner::query()->firstOrCreate(['email' => $email]);

        abort_if(
            PortalStaff::query()->where('learner_id', $learner->id)->exists(),
            422,
            'Этот пользователь уже в списке сотрудников.'
        );

        $staff = PortalStaff::query()->create([
            'learner_id' => $learner->id,
            'role' => $data['role'],
        ]);
        $this->syncCourses($staff, $data['role'], $data['course_ids']);

        return redirect()
            ->route('admin.staff.index')
            ->with('ok', 'Сотрудник добавлен.');
    }

    public function edit(PortalStaff $staff): RedirectResponse
    {
        return redirect()->route('admin.staff.index', ['edit' => (string) $staff->id]);
    }

    public function update(Request $request, PortalStaff $staff): RedirectResponse
    {
        $data = $this->validatePayload($request, $staff);
        $staff->role = $data['role'];
        $staff->save();

        $learner = $staff->learner;
        if ($learner !== null) {
            $learner->email = strtolower((string) $data['email']);
            $learner->save();
        }

        $this->syncCourses($staff, $data['role'], $data['course_ids']);

        return redirect()
            ->route('admin.staff.index')
            ->with('ok', 'Запись обновлена.');
    }

    public function destroy(PortalStaff $staff): RedirectResponse
    {
        if ($staff->isPortalAdmin()) {
            $admins = PortalStaff::query()->where('role', PortalStaff::ROLE_PORTAL_ADMIN)->count();
            abort_if($admins <= 1, 422, 'Нельзя удалить последнего администратора портала.');
        }

        $currentId = (int) session('learner_id', 0);
        abort_if($currentId > 0 && $staff->learner_id === $currentId, 422, 'Нельзя удалить собственную учётную запись сотрудника.');

        $staff->delete();

        return redirect()
            ->route('admin.staff.index')
            ->with('ok', 'Сотрудник удалён.');
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function staffListSortParams(Request $request): array
    {
        $sort = (string) $request->query('sort', 'id');
        if (! in_array($sort, ['id', 'email', 'name', 'role', 'login'], true)) {
            $sort = 'id';
        }
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        return [$sort, $dir];
    }

    private function applyStaffListSort(Builder $query, string $sort, string $dir): void
    {
        $joined = false;
        $joinLearners = function () use ($query, &$joined): void {
            if ($joined) {
                return;
            }
            $query->join('learners', 'learners.id', '=', 'portal_staff.learner_id')
                ->select('portal_staff.*');
            $joined = true;
        };

        if ($sort === 'email') {
            $joinLearners();
            $query->orderBy('learners.email', $dir);
        } elseif ($sort === 'name') {
            $joinLearners();
            $query->orderByRaw(
                "(learners.sso_display_name IS NULL OR TRIM(learners.sso_display_name) = '') ASC"
            )
                ->orderBy('learners.sso_display_name', $dir)
                ->orderBy('learners.email', 'asc');
        } elseif ($sort === 'login') {
            $joinLearners();
            $query->orderByRaw('learners.last_login_at IS NULL ASC')
                ->orderBy('learners.last_login_at', $dir);
        } elseif ($sort === 'role') {
            $roles = PortalStaff::ROLES;
            $placeholders = implode(',', array_fill(0, count($roles), '?'));
            $query->orderByRaw(
                'FIELD(portal_staff.role, '.$placeholders.') '.($dir === 'desc' ? 'DESC' : 'ASC'),
                $roles
            );
            $joinLearners();
            $query->orderBy('learners.email', 'asc');
        } else {
            $query->orderBy('portal_staff.id', $dir);
        }
    }

    /**
     * @return array{email: string, role: string, course_ids: list<int>}
     */
    private function validatePayload(Request $request, ?PortalStaff $existing): array
    {
        $roleRule = Rule::in(PortalStaff::ROLES);

        $emailRules = ['required', 'email', 'max:190'];
        if ($existing !== null && $existing->learner_id > 0) {
            $emailRules[] = Rule::unique('learners', 'email')->ignore($existing->learner_id);
        }

        $data = $request->validate([
            'email' => $emailRules,
            'role' => ['required', 'string', $roleRule],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ], [], [
            'email' => 'email',
            'course_ids' => 'курсы',
        ]);

        $role = (string) $data['role'];
        $courseIds = array_values(array_unique(array_map('intval', $data['course_ids'] ?? [])));

        if (in_array($role, [PortalStaff::ROLE_INSTRUCTOR, PortalStaff::ROLE_COURSE_TESTER], true)) {
            abort_if($courseIds === [], 422, 'Для роли «инструктор» или «тестировщик» выберите хотя бы один курс.');
        } elseif ($role !== PortalStaff::ROLE_COURSE_EDITOR) {
            $courseIds = [];
        }

        $email = strtolower((string) $data['email']);
        if ($existing === null) {
            $learner = Learner::query()->where('email', $email)->first();
            if ($learner !== null) {
                abort_if(
                    PortalStaff::query()->where('learner_id', $learner->id)->exists(),
                    422,
                    'Этот пользователь уже в списке сотрудников.'
                );
            }
        }

        return [
            'email' => $email,
            'role' => $role,
            'course_ids' => $courseIds,
        ];
    }

    /**
     * @param  list<int>  $courseIds
     */
    private function syncCourses(PortalStaff $staff, string $role, array $courseIds): void
    {
        if (in_array($role, [PortalStaff::ROLE_INSTRUCTOR, PortalStaff::ROLE_COURSE_TESTER, PortalStaff::ROLE_COURSE_EDITOR], true)) {
            $staff->courses()->sync($courseIds);
        } else {
            $staff->courses()->sync([]);
        }
    }
}
