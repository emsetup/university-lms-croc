<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\CourseChangeLog;
use App\Models\PortalActivityEvent;
use App\Models\PortalStaff;
use App\Models\PracticeImage;
use App\Support\LearnerDisplay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class PortalStaffProfileService
{
    public function __construct(private CourseChangeLogService $changeLog) {}

    /**
     * @return array{
     *   staff: PortalStaff,
     *   display_name: string,
     *   email: string,
     *   initials: string,
     *   role_label: string,
     *   badge_class: string,
     *   last_login: ?\DateTimeInterface,
     *   access_comment: ?string,
     *   groups: list<string>,
     *   assigned_courses: list<array{id:int,title:string,slug:string}>,
     *   created_courses: list<array{id:int,title:string,slug:string,is_published:bool,is_archived:bool}>,
     *   created_images: list<array{id:int,title:string,docker_tag:string,description:?string,is_built:bool,last_build_status:?string}>,
     *   change_logs: Collection<int, CourseChangeLog>,
     *   admin_activity: Collection<int, PortalActivityEvent>,
     * }
     */
    public function build(PortalStaff $staff): array
    {
        $staff->loadMissing([
            'learner:id,email,sso_display_name,last_login_at',
            'groups:id,name',
            'courses:id,title,slug',
        ]);

        $email = trim((string) ($staff->learner?->email ?? ''));
        $displayName = $staff->learner
            ? LearnerDisplay::portalDisplayName($staff->learner)
            : '';
        if ($displayName === '') {
            $displayName = $email !== '' ? $email : 'Сотрудник #'.(int) $staff->id;
        }

        [$roleLabel, $badgeClass] = $this->roleMeta((string) $staff->role);

        return [
            'staff' => $staff,
            'display_name' => $displayName,
            'email' => $email,
            'initials' => LearnerDisplay::initials($email, $displayName),
            'role_label' => $roleLabel,
            'badge_class' => $badgeClass,
            'last_login' => $staff->learner?->last_login_at,
            'access_comment' => $this->normalizeComment($staff->access_comment),
            'groups' => $staff->groups->pluck('name')->map(fn ($n) => (string) $n)->values()->all(),
            'assigned_courses' => $staff->courses->map(fn (Course $c) => [
                'id' => (int) $c->id,
                'title' => (string) $c->title,
                'slug' => (string) $c->slug,
            ])->values()->all(),
            'created_courses' => $this->createdCourses((int) $staff->id),
            'created_images' => $this->createdImages((int) $staff->id),
            'change_logs' => $this->changeLogsForStaff((int) $staff->id),
            'admin_activity' => $this->adminActivityForStaff((int) $staff->learner_id),
            'content_grants' => $this->contentGrantsForStaff((int) $staff->id),
        ];
    }

    /**
     * @return list<array{id:int,title:string,slug:string,is_published:bool,is_archived:bool}>
     */
    private function createdCourses(int $staffId): array
    {
        if (! Schema::hasColumn('courses', 'created_by_portal_staff_id')) {
            return [];
        }

        return Course::query()
            ->where('created_by_portal_staff_id', $staffId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'title', 'slug', 'is_published', 'is_archived'])
            ->map(fn (Course $c) => [
                'id' => (int) $c->id,
                'title' => (string) $c->title,
                'slug' => (string) $c->slug,
                'is_published' => (bool) $c->is_published,
                'is_archived' => (bool) $c->is_archived,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,title:string,docker_tag:string,description:?string,is_built:bool,last_build_status:?string}>
     */
    private function createdImages(int $staffId): array
    {
        if (! Schema::hasTable('practice_images')
            || ! Schema::hasColumn('practice_images', 'created_by_portal_staff_id')) {
            return [];
        }

        $columns = ['id', 'title', 'docker_tag', 'is_built', 'last_build_status'];
        if (Schema::hasColumn('practice_images', 'description')) {
            $columns[] = 'description';
        }

        return PracticeImage::query()
            ->where('created_by_portal_staff_id', $staffId)
            ->orderBy('title')
            ->orderBy('id')
            ->get($columns)
            ->map(function (PracticeImage $img) {
                $description = trim((string) ($img->description ?? ''));

                return [
                    'id' => (int) $img->id,
                    'title' => (string) $img->title,
                    'docker_tag' => (string) $img->docker_tag,
                    'description' => $description !== '' ? $description : null,
                    'is_built' => (bool) $img->is_built,
                    'last_build_status' => $img->last_build_status !== null
                        ? (string) $img->last_build_status
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, CourseChangeLog>
     */
    private function changeLogsForStaff(int $staffId): Collection
    {
        if (! Schema::hasTable('course_change_logs')) {
            return collect();
        }

        return CourseChangeLog::query()
            ->where('portal_staff_id', $staffId)
            ->with(['course:id,title,slug'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    /**
     * @return Collection<int, PortalActivityEvent>
     */
    private function adminActivityForStaff(int $learnerId): Collection
    {
        if ($learnerId <= 0 || ! Schema::hasTable('portal_activity_events')) {
            return collect();
        }

        return PortalActivityEvent::query()
            ->where('learner_id', $learnerId)
            ->where('type', 'admin_panel')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function roleMeta(string $role): array
    {
        return match ($role) {
            PortalStaff::ROLE_PORTAL_ADMIN => ['Администратор портала', 'ap-staff-badge ap-staff-badge--admin'],
            PortalStaff::ROLE_COURSE_MODERATOR => ['Модератор', 'ap-staff-badge ap-staff-badge--moderator'],
            PortalStaff::ROLE_COURSE_CREATOR => ['Создатель курсов', 'ap-staff-badge ap-staff-badge--creator'],
            PortalStaff::ROLE_COURSE_EDITOR => ['Редактор курсов', 'ap-staff-badge ap-staff-badge--editor'],
            PortalStaff::ROLE_INSTRUCTOR => ['Преподаватель курса', 'ap-staff-badge ap-staff-badge--instructor'],
            PortalStaff::ROLE_COURSE_TESTER => ['Тестировщик курса', 'ap-staff-badge ap-staff-badge--tester'],
            PortalStaff::ROLE_COURSE_CONTRIBUTOR => ['Соавтор курса', 'ap-staff-badge ap-staff-badge--editor'],
            default => [$role, 'ap-staff-badge ap-staff-badge--muted'],
        };
    }

    private function normalizeComment(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || in_array($text, ['&quot;&quot;', '""'], true)) {
            return null;
        }

        return $text;
    }

    /**
     * @return list<array{course_id:int,course_title:string,course_slug:string,resource_type:string,resource_id:?int,permission:string,settings_url:string}>
     */
    private function contentGrantsForStaff(int $staffId): array
    {
        if (! Schema::hasTable('course_content_grants')) {
            return [];
        }

        return \App\Models\CourseContentGrant::query()
            ->where('portal_staff_id', $staffId)
            ->with('course:id,title,slug')
            ->orderBy('course_id')
            ->orderBy('resource_type')
            ->get()
            ->map(function (\App\Models\CourseContentGrant $grant): array {
                $course = $grant->course;

                return [
                    'course_id' => (int) $grant->course_id,
                    'course_title' => $course ? (string) $course->title : '#'.$grant->course_id,
                    'course_slug' => $course ? (string) $course->slug : '',
                    'resource_type' => (string) $grant->resource_type,
                    'resource_id' => $grant->resource_id !== null ? (int) $grant->resource_id : null,
                    'permission' => (string) $grant->permission,
                    'settings_url' => $course
                        ? route('admin.course.settings', ['adminCourse' => $course->slug, 'tab' => 'soavtory'])
                        : '#',
                ];
            })
            ->values()
            ->all();
    }
}
