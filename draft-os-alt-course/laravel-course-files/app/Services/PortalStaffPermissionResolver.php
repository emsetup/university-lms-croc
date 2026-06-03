<?php

namespace App\Services;

use App\Models\PortalStaff;
use App\Support\PortalStaffPermissionCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Эффективные права: личная роль ∪ роли всех групп ∪ доп. права групп (объединение).
 */
final class PortalStaffPermissionResolver
{
    /** @var array<string, true> */
    private array $granted;

    private Collection $groupCourseIds;

    public function __construct(private PortalStaff $staff)
    {
        $this->granted = [];
        foreach (PortalStaffPermissionCatalog::keysForRole((string) $staff->role) as $key) {
            $this->granted[$key] = true;
        }

        if (Schema::hasTable('portal_staff_groups')) {
            $staff->loadMissing([
                'groups.permissions',
                'groups.courses',
            ]);
            foreach ($staff->groups as $group) {
                $groupRole = (string) ($group->role ?? '');
                if ($groupRole !== '' && in_array($groupRole, PortalStaff::ROLES, true)) {
                    foreach (PortalStaffPermissionCatalog::keysForRole($groupRole) as $key) {
                        $this->granted[$key] = true;
                    }
                }
                $keys = $group->relationLoaded('permissions')
                    ? $group->permissions->pluck('permission')->map(fn ($p) => (string) $p)->all()
                    : $group->permissionKeys();
                foreach ($keys as $key) {
                    if (in_array($key, PortalStaffPermissionCatalog::allKeys(), true)) {
                        $this->granted[$key] = true;
                    }
                }
            }
        }

        $this->groupCourseIds = $this->loadGroupCourseIds();
    }

    public function has(string $permission): bool
    {
        return isset($this->granted[$permission]);
    }

    public function hasAny(string ...$permissions): bool
    {
        foreach ($permissions as $p) {
            if ($this->has($p)) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, int> */
    public function groupCourseIds(): Collection
    {
        return $this->groupCourseIds;
    }

    /** @return list<string> */
    public function grantedKeys(): array
    {
        return array_keys($this->granted);
    }

    /** Роль в карточке сотрудника или роль любой из групп. */
    public function hasRole(string $role): bool
    {
        if ($this->staff->role === $role) {
            return true;
        }
        if (! Schema::hasTable('portal_staff_groups')) {
            return false;
        }
        foreach ($this->staff->groups as $group) {
            if ((string) ($group->role ?? '') === $role) {
                return true;
            }
        }

        return false;
    }

    private function loadGroupCourseIds(): Collection
    {
        if (! Schema::hasTable('portal_staff_group_courses')) {
            return collect();
        }
        $ids = collect();
        foreach ($this->staff->groups as $group) {
            foreach ($group->courses as $course) {
                $ids->push((int) $course->id);
            }
        }

        return $ids->unique()->values();
    }
}
