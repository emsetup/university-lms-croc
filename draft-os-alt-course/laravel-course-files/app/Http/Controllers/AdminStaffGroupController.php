<?php

namespace App\Http\Controllers;

use App\Models\PortalStaff;
use App\Models\PortalStaffGroup;
use App\Support\PortalStaffFromEmail;
use App\Support\PortalStaffPermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AdminStaffGroupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $group = PortalStaffGroup::query()->create([
            'name' => $data['name'],
            'description' => $data['description'],
            'role' => $data['role'],
            'sort' => $data['sort'],
        ]);
        $created = $this->syncRelations($group, $data);

        return $this->redirectBack($request, $this->flashMessage('Группа создана.', $created, $data['role']));
    }

    public function update(Request $request, PortalStaffGroup $group): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $group->name = $data['name'];
        $group->description = $data['description'];
        $group->role = $data['role'];
        $group->sort = $data['sort'];
        $group->save();
        $created = $this->syncRelations($group, $data);

        return $this->redirectBack($request, $this->flashMessage('Группа обновлена.', $created, $data['role']));
    }

    public function destroy(Request $request, PortalStaffGroup $group): RedirectResponse
    {
        $group->delete();

        return $this->redirectBack($request, 'Группа удалена.');
    }

    /**
     * @return array{
     *     name: string,
     *     description: string|null,
     *     sort: int,
     *     role: string,
     *     permissions: list<string>,
     *     member_ids: list<int>,
     *     invite_emails: string,
     *     course_ids: list<int>
     * }
     */
    private function validatePayload(Request $request): array
    {
        $allowed = PortalStaffPermissionCatalog::allKeys();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'role' => ['required', 'string', Rule::in(PortalStaff::ROLES)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($allowed)],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:portal_staff,id'],
            'invite_emails' => ['nullable', 'string', 'max:8000'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ], [], [
            'name' => 'название',
            'role' => 'роль группы',
            'permissions' => 'права',
            'member_ids' => 'участники',
            'invite_emails' => 'почта участников',
            'course_ids' => 'курсы',
        ]);

        $role = (string) $data['role'];
        $permissions = array_values(array_unique(array_map('strval', $data['permissions'] ?? [])));
        [$memberIds, $staffCreated, $createdStaff] = $this->resolveMemberIds(
            array_values(array_unique(array_map('intval', $data['member_ids'] ?? []))),
            trim((string) ($data['invite_emails'] ?? '')),
            $role,
        );
        $courseIds = array_values(array_unique(array_map('intval', $data['course_ids'] ?? [])));

        $needsCourses = $this->groupNeedsCourses($role, $permissions);
        if ($needsCourses && $courseIds === []) {
            throw ValidationException::withMessages([
                'course_ids' => 'Для этой роли или выбранных прав укажите хотя бы один курс группы.',
            ]);
        }
        if (! $needsCourses) {
            $courseIds = [];
        }

        return [
            'name' => trim((string) $data['name']),
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'role' => $role,
            'sort' => (int) ($data['sort'] ?? 0),
            'permissions' => $permissions,
            'member_ids' => $memberIds,
            'invite_emails' => trim((string) ($data['invite_emails'] ?? '')),
            'course_ids' => $courseIds,
            'staff_created' => $staffCreated,
            'created_staff' => $createdStaff,
        ];
    }

    /**
     * @param  list<int>  $memberIds
     * @return array{0: list<int>, 1: int}
     */
    /**
     * @param  list<string>  $permissions
     */
    private function groupNeedsCourses(string $role, array $permissions): bool
    {
        if (in_array($role, [
            PortalStaff::ROLE_INSTRUCTOR,
            PortalStaff::ROLE_COURSE_TESTER,
            PortalStaff::ROLE_COURSE_EDITOR,
        ], true)) {
            return true;
        }

        return array_intersect($permissions, PortalStaffPermissionCatalog::ASSIGNED_SCOPE_KEYS) !== [];
    }

    private function resolveMemberIds(array $memberIds, string $inviteEmailsRaw, string $groupRole): array
    {
        $ids = $memberIds;
        $created = 0;
        $createdStaff = [];
        $domain = (string) config('course.email_domain', '');
        $bad = [];
        foreach (PortalStaffFromEmail::parseLines($inviteEmailsRaw) as $email) {
            if ($domain !== '' && ! PortalStaffFromEmail::isCorporateEmail($email, $domain)) {
                $bad[] = $email;
                continue;
            }
            $pack = PortalStaffFromEmail::findOrCreateStaff($email, $groupRole);
            if ($pack['created']) {
                $created++;
                $createdStaff[] = $pack['staff'];
            }
            $ids[] = (int) $pack['staff']->id;
        }
        if ($bad !== []) {
            throw ValidationException::withMessages([
                'invite_emails' => 'Укажите корпоративную почту @'.$domain.': '.implode(', ', $bad),
            ]);
        }

        return [
            array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0))),
            $created,
            $createdStaff,
        ];
    }

    /**
     * @param  array{
     *     permissions: list<string>,
     *     member_ids: list<int>,
     *     invite_emails: string,
     *     course_ids: list<int>,
     *     staff_created: int,
     *     created_staff: list<\App\Models\PortalStaff>,
     *     role: string
     * }  $data
     */
    private function syncRelations(PortalStaffGroup $group, array $data): int
    {
        $group->permissions()->delete();
        foreach ($data['permissions'] as $perm) {
            $group->permissions()->create(['permission' => $perm]);
        }
        $group->members()->sync($data['member_ids']);
        $group->courses()->sync($data['course_ids']);

        $notifier = app(\App\Services\Mail\PortalMailNotifier::class);
        $roleLabel = \App\Services\Mail\PortalMailNotifier::roleLabel((string) ($data['role'] ?? $group->role));
        foreach ($data['created_staff'] ?? [] as $staff) {
            if ($staff instanceof PortalStaff) {
                $staff->loadMissing('learner');
                $notifier->notifyStaffAdded($staff, $roleLabel);
            }
        }

        return (int) ($data['staff_created'] ?? 0);
    }

    private function flashMessage(string $base, int $newStaffCount, string $groupRole): string
    {
        if ($newStaffCount <= 0) {
            return $base;
        }

        return $base.' Добавлено новых сотрудников: '.$newStaffCount
            .' (роль «'.$this->roleLabel($groupRole).'»). В нескольких группах права складываются.';
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            PortalStaff::ROLE_PORTAL_ADMIN => 'Администратор портала',
            PortalStaff::ROLE_COURSE_MODERATOR => 'Модератор',
            PortalStaff::ROLE_PORTAL_AUDITOR => 'Аудитор портала',
            PortalStaff::ROLE_COURSE_CREATOR => 'Создатель курсов',
            PortalStaff::ROLE_COURSE_EDITOR => 'Редактор курсов',
            PortalStaff::ROLE_INSTRUCTOR => 'Преподаватель курса',
            PortalStaff::ROLE_COURSE_TESTER => 'Тестировщик курса',
            default => $role,
        };
    }

    private function redirectBack(Request $request, string $message): RedirectResponse
    {
        $params = ['tab' => 'groups'];
        $q = trim((string) $request->input('_return_q', ''));
        if ($q !== '') {
            $params['q'] = $q;
        }

        return redirect()
            ->route('admin.staff.index', $params)
            ->with('ok', $message);
    }
}
