<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\CourseContentGrant;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\Learner;
use App\Models\PortalStaff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CourseCollaboratorService
{
    public function __construct(private CourseChangeLogService $changeLog) {}

    public function collaboratorLimit(): int
    {
        return max(1, (int) config('portal.course_collaborator_limit', 5));
    }

    /** @return Collection<int, PortalStaff> */
    public function collaboratorsForCourse(Course $course): Collection
    {
        if (! Schema::hasTable('course_content_grants')) {
            return collect();
        }

        $staffIds = CourseContentGrant::query()
            ->where('course_id', (int) $course->id)
            ->distinct()
            ->pluck('portal_staff_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($staffIds === []) {
            return collect();
        }

        return PortalStaff::query()
            ->with('learner:id,email,sso_display_name')
            ->whereIn('id', $staffIds)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, list<array{resource_type: string, resource_id: int|null, permission: string}>>
     */
    public function grantsGroupedByStaff(Course $course): array
    {
        if (! Schema::hasTable('course_content_grants')) {
            return [];
        }

        $out = [];
        $rows = CourseContentGrant::query()
            ->where('course_id', (int) $course->id)
            ->orderBy('portal_staff_id')
            ->orderBy('resource_type')
            ->orderBy('resource_id')
            ->get();

        foreach ($rows as $row) {
            $sid = (int) $row->portal_staff_id;
            $out[$sid] ??= [];
            $out[$sid][] = [
                'resource_type' => (string) $row->resource_type,
                'resource_id' => $row->resource_id !== null ? (int) $row->resource_id : null,
                'permission' => (string) $row->permission,
            ];
        }

        return $out;
    }

    public function countCollaboratorsWithEdit(Course $course, ?int $excludeStaffId = null): int
    {
        if (! Schema::hasTable('course_content_grants')) {
            return 0;
        }

        $ownerId = (int) ($course->created_by_portal_staff_id ?? 0);

        $query = CourseContentGrant::query()
            ->where('course_id', (int) $course->id)
            ->whereIn('permission', [
                CourseContentGrant::PERMISSION_EDIT,
                CourseContentGrant::PERMISSION_MANAGE,
            ])
            ->distinct()
            ->pluck('portal_staff_id');

        return $query
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== $ownerId && ($excludeStaffId === null || $id !== $excludeStaffId))
            ->unique()
            ->count();
    }

    /**
     * @param  list<array{resource_type: string, resource_id: int|null, permission: string}>  $grants
     */
    public function syncStaffGrants(
        Course $course,
        PortalStaff $staff,
        array $grants,
        int $grantedByStaffId,
    ): void {
        if (! Schema::hasTable('course_content_grants')) {
            return;
        }

        DB::transaction(function () use ($course, $staff, $grants, $grantedByStaffId): void {
            CourseContentGrant::query()
                ->where('course_id', (int) $course->id)
                ->where('portal_staff_id', (int) $staff->id)
                ->delete();

            foreach ($grants as $grant) {
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
                }

                CourseContentGrant::query()->create([
                    'course_id' => (int) $course->id,
                    'portal_staff_id' => (int) $staff->id,
                    'resource_type' => $type,
                    'resource_id' => $resourceId !== null ? (int) $resourceId : null,
                    'permission' => $permission,
                    'granted_by_portal_staff_id' => $grantedByStaffId > 0 ? $grantedByStaffId : null,
                ]);
            }
        });

        $this->changeLog->logCollaboratorGrantsSynced($course, $staff);
    }

    public function removeStaffFromCourse(Course $course, PortalStaff $staff): void
    {
        if (! Schema::hasTable('course_content_grants')) {
            return;
        }

        $deleted = CourseContentGrant::query()
            ->where('course_id', (int) $course->id)
            ->where('portal_staff_id', (int) $staff->id)
            ->delete();

        if ($deleted > 0) {
            $this->changeLog->logCollaboratorRemoved($course, $staff);
        }
    }

    public function findStaffByEmail(string $email): ?PortalStaff
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $learner = Learner::query()->where('email', $email)->first();
        if ($learner === null) {
            return null;
        }

        return PortalStaff::query()->where('learner_id', (int) $learner->id)->first();
    }

    /**
     * @return array{course: Course, modules: list<array{id: int, title: string, sections: list<array{id: int, title: string, type: string, type_label: string}>}>}
     */
    public function courseGrantTree(Course $course): array
    {
        $modules = CourseModule::query()
            ->where('course_id', (int) $course->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->with(['sections' => fn ($q) => $q->orderBy('sort')->orderBy('id')])
            ->get();

        $moduleNodes = [];
        foreach ($modules as $module) {
            $sections = [];
            foreach ($module->sections as $section) {
                $sections[] = [
                    'id' => (int) $section->id,
                    'title' => (string) $section->title,
                    'type' => (string) $section->type,
                    'type_label' => CourseSection::typesList()[$section->type] ?? $section->type,
                ];
            }
            $moduleNodes[] = [
                'id' => (int) $module->id,
                'title' => (string) $module->title,
                'sections' => $sections,
            ];
        }

        return [
            'course' => $course,
            'modules' => $moduleNodes,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{resource_type: string, resource_id: int|null, permission: string}>
     */
    public function normalizeGrantsPayload(Course $course, array $payload): array
    {
        $out = [];
        $courseId = (int) $course->id;

        $push = static function (string $type, ?int $resourceId, string $permission) use (&$out, $courseId): void {
            if (! in_array($permission, CourseContentGrant::PERMISSIONS, true)) {
                return;
            }
            $key = $type.':'.($resourceId ?? 'course');
            $rank = CourseContentGrant::permissionRank($permission);
            if (isset($out[$key]) && CourseContentGrant::permissionRank($out[$key]['permission']) >= $rank) {
                return;
            }
            $out[$key] = [
                'resource_type' => $type,
                'resource_id' => $resourceId,
                'permission' => $permission,
            ];
        };

        foreach ((array) ($payload['course_permission'] ?? []) as $staffId => $perm) {
            if ((string) $perm !== '') {
                $push(CourseContentGrant::RESOURCE_COURSE, null, (string) $perm);
            }
        }

        foreach ((array) ($payload['module'] ?? []) as $moduleId => $byStaff) {
            $module = CourseModule::query()
                ->whereKey((int) $moduleId)
                ->where('course_id', $courseId)
                ->first();
            if ($module === null) {
                continue;
            }
            foreach ((array) $byStaff as $staffId => $perm) {
                if ((string) $perm !== '') {
                    $push(CourseContentGrant::RESOURCE_MODULE, (int) $module->id, (string) $perm);
                }
            }
        }

        foreach ((array) ($payload['section'] ?? []) as $sectionId => $byStaff) {
            $section = CourseSection::query()
                ->whereKey((int) $sectionId)
                ->where('course_id', $courseId)
                ->first();
            if ($section === null) {
                continue;
            }
            foreach ((array) $byStaff as $staffId => $perm) {
                if ((string) $perm !== '') {
                    $push(CourseContentGrant::RESOURCE_SECTION, (int) $section->id, (string) $perm);
                }
            }
        }

        return array_values($out);
    }
}
