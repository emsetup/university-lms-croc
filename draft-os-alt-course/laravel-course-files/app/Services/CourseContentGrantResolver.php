<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\CourseContentGrant;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\PortalStaff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Иерархическое разрешение грантов: course → module → section.
 */
final class CourseContentGrantResolver
{
    /** @var array<int, Collection<int, CourseContentGrant>> */
    private array $grantsByCourse = [];

    /** @var array<int, int|null> */
    private array $courseOwnerCache = [];

    public function __construct(private PortalStaff $staff) {}

    public function hasGrantsTable(): bool
    {
        return Schema::hasTable('course_content_grants');
    }

    /** @return Collection<int, CourseContentGrant> */
    public function grantsForCourse(int $courseId): Collection
    {
        if (! $this->hasGrantsTable() || $courseId <= 0) {
            return collect();
        }
        if (isset($this->grantsByCourse[$courseId])) {
            return $this->grantsByCourse[$courseId];
        }

        return $this->grantsByCourse[$courseId] = CourseContentGrant::query()
            ->where('course_id', $courseId)
            ->where('portal_staff_id', (int) $this->staff->id)
            ->get();
    }

    /** @return Collection<int, int> */
    public function grantedCourseIds(): Collection
    {
        if (! $this->hasGrantsTable()) {
            return collect();
        }

        return CourseContentGrant::query()
            ->where('portal_staff_id', (int) $this->staff->id)
            ->distinct()
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    public function isCourseOwner(int $courseId): bool
    {
        if ($courseId <= 0 || ! Schema::hasColumn('courses', 'created_by_portal_staff_id')) {
            return false;
        }
        if (! array_key_exists($courseId, $this->courseOwnerCache)) {
            $ownerId = Course::query()->whereKey($courseId)->value('created_by_portal_staff_id');
            $this->courseOwnerCache[$courseId] = $ownerId !== null ? (int) $ownerId : null;
        }

        return $this->courseOwnerCache[$courseId] === (int) $this->staff->id;
    }

    public function courseUsesStrictGrants(int $courseId): bool
    {
        if ($courseId <= 0 || ! Schema::hasColumn('courses', 'strict_grants')) {
            return false;
        }

        return (bool) Course::query()->whereKey($courseId)->value('strict_grants');
    }

    public function effectivePermissionForCourse(int $courseId): int
    {
        if ($this->isCourseOwner($courseId)) {
            return CourseContentGrant::permissionRank(CourseContentGrant::PERMISSION_MANAGE);
        }

        $rank = 0;
        foreach ($this->grantsForCourse($courseId) as $grant) {
            if ($grant->resource_type === CourseContentGrant::RESOURCE_COURSE) {
                $rank = max($rank, CourseContentGrant::permissionRank($grant->permission));
            }
        }

        return $rank;
    }

    public function effectivePermissionForModule(int $moduleId): int
    {
        $module = CourseModule::query()->find($moduleId);
        if ($module === null) {
            return 0;
        }
        $courseId = (int) $module->course_id;

        $rank = $this->effectivePermissionForCourse($courseId);
        foreach ($this->grantsForCourse($courseId) as $grant) {
            if ($grant->resource_type === CourseContentGrant::RESOURCE_MODULE
                && (int) $grant->resource_id === $moduleId) {
                $rank = max($rank, CourseContentGrant::permissionRank($grant->permission));
            }
        }

        return $rank;
    }

    public function effectivePermissionForSection(int $sectionId): int
    {
        $section = CourseSection::query()->find($sectionId);
        if ($section === null) {
            return 0;
        }
        $moduleId = (int) $section->course_module_id;
        $courseId = (int) $section->course_id;

        $rank = $this->effectivePermissionForModule($moduleId);
        foreach ($this->grantsForCourse($courseId) as $grant) {
            if ($grant->resource_type === CourseContentGrant::RESOURCE_SECTION
                && (int) $grant->resource_id === $sectionId) {
                $rank = max($rank, CourseContentGrant::permissionRank($grant->permission));
            }
        }

        return $rank;
    }

    public function canViewCourse(int $courseId): bool
    {
        return $this->effectivePermissionForCourse($courseId) >= CourseContentGrant::permissionRank(CourseContentGrant::PERMISSION_VIEW)
            || $this->hasAnyGrantOnCourse($courseId);
    }

    public function canEditCourseContent(int $courseId): bool
    {
        return $this->effectivePermissionForCourse($courseId) >= CourseContentGrant::permissionRank(CourseContentGrant::PERMISSION_EDIT);
    }

    public function canManageCourse(int $courseId): bool
    {
        return $this->effectivePermissionForCourse($courseId) >= CourseContentGrant::permissionRank(CourseContentGrant::PERMISSION_MANAGE);
    }

    /** Редактирование на каждом существующем модуле (без гранта на «Весь курс»). */
    public function canEditAllModulesInCourse(int $courseId): bool
    {
        $moduleIds = CourseModule::query()
            ->where('course_id', $courseId)
            ->orderBy('sort')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($moduleIds->isEmpty()) {
            return false;
        }

        foreach ($moduleIds as $moduleId) {
            if (! $this->canEditModule($moduleId)) {
                return false;
            }
        }

        return true;
    }

    public function canViewModule(int $moduleId): bool
    {
        return $this->effectivePermissionForModule($moduleId) >= CourseContentGrant::permissionRank(CourseContentGrant::PERMISSION_VIEW);
    }

    public function canEditModule(int $moduleId): bool
    {
        return $this->effectivePermissionForModule($moduleId) >= CourseContentGrant::permissionRank(CourseContentGrant::PERMISSION_EDIT);
    }

    public function canViewSection(int $sectionId): bool
    {
        return $this->effectivePermissionForSection($sectionId) >= CourseContentGrant::permissionRank(CourseContentGrant::PERMISSION_VIEW);
    }

    public function canEditSection(int $sectionId): bool
    {
        return $this->effectivePermissionForSection($sectionId) >= CourseContentGrant::permissionRank(CourseContentGrant::PERMISSION_EDIT);
    }

    public function hasAnyGrantOnCourse(int $courseId): bool
    {
        return $this->grantsForCourse($courseId)->isNotEmpty();
    }

    /** @return Collection<int, int> */
    public function accessibleModuleIdsForCourse(int $courseId): Collection
    {
        if ($this->canEditCourseContent($courseId) || $this->isCourseOwner($courseId)) {
            return CourseModule::query()
                ->where('course_id', $courseId)
                ->orderBy('sort')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id);
        }

        $moduleRanks = [];
        foreach ($this->grantsForCourse($courseId) as $grant) {
            if ($grant->resource_type === CourseContentGrant::RESOURCE_MODULE && $grant->resource_id !== null) {
                $mid = (int) $grant->resource_id;
                $moduleRanks[$mid] = max(
                    $moduleRanks[$mid] ?? 0,
                    CourseContentGrant::permissionRank($grant->permission)
                );
            }
        }

        $sectionModuleIds = CourseSection::query()
            ->where('course_id', $courseId)
            ->whereIn('id', $this->grantsForCourse($courseId)
                ->filter(fn (CourseContentGrant $g) => $g->resource_type === CourseContentGrant::RESOURCE_SECTION && $g->resource_id !== null)
                ->pluck('resource_id')
                ->map(fn ($id) => (int) $id)
                ->all())
            ->pluck('course_module_id')
            ->map(fn ($id) => (int) $id);

        $ids = collect(array_keys($moduleRanks))
            ->merge($sectionModuleIds)
            ->unique()
            ->filter(fn (int $moduleId) => $this->canViewModule($moduleId))
            ->values();

        return $ids;
    }

    /** @return Collection<int, int> */
    public function accessibleSectionIdsForModule(int $moduleId): Collection
    {
        $module = CourseModule::query()->find($moduleId);
        if ($module === null) {
            return collect();
        }
        $courseId = (int) $module->course_id;

        if ($this->canEditModule($moduleId)) {
            return CourseSection::query()
                ->where('course_module_id', $moduleId)
                ->orderBy('sort')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id);
        }

        return CourseSection::query()
            ->where('course_module_id', $moduleId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->filter(fn (CourseSection $s) => $this->canViewSection((int) $s->id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /** @return Collection<int, int> */
    public function editableSectionIdsForModule(int $moduleId): Collection
    {
        if ($this->canEditModule($moduleId)) {
            return $this->accessibleSectionIdsForModule($moduleId);
        }

        return CourseSection::query()
            ->where('course_module_id', $moduleId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->filter(fn (CourseSection $s) => $this->canEditSection((int) $s->id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }
}
