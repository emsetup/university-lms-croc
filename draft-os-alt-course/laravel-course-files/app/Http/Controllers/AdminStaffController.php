<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Learner;
use App\Models\PortalStaff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminStaffController extends Controller
{
    public function index(): View
    {
        $items = PortalStaff::query()
            ->with(['learner:id,email', 'courses:id,title'])
            ->orderBy('id')
            ->get();

        return view('admin.staff-index', ['items' => $items]);
    }

    public function create(): View
    {
        return view('admin.staff-edit', [
            'mode' => 'create',
            'staff' => null,
            'courses' => Course::query()->orderBy('sort')->orderBy('id')->get(['id', 'title']),
        ]);
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

    public function edit(PortalStaff $staff): View
    {
        $staff->load(['learner:id,email', 'courses:id']);

        return view('admin.staff-edit', [
            'mode' => 'edit',
            'staff' => $staff,
            'courses' => Course::query()->orderBy('sort')->orderBy('id')->get(['id', 'title']),
        ]);
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
        } else {
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
        if (in_array($role, [PortalStaff::ROLE_INSTRUCTOR, PortalStaff::ROLE_COURSE_TESTER], true)) {
            $staff->courses()->sync($courseIds);
        } else {
            $staff->courses()->sync([]);
        }
    }
}
