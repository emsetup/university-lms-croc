<?php

namespace App\Services;

use App\Models\PortalStaff;
use Illuminate\Support\Collection;

/**
 * Права сотрудника портала (OIDC + запись в portal_staff).
 */
final class PortalStaffAccess
{
    private ?Collection $assignedCourseIds = null;

    public function __construct(private PortalStaff $staff) {}

    public static function fromLearnerId(int $learnerId): ?self
    {
        $row = PortalStaff::query()->where('learner_id', $learnerId)->first();
        if ($row === null) {
            return null;
        }

        return new self($row);
    }

    public function staff(): PortalStaff
    {
        return $this->staff;
    }

    public function role(): string
    {
        return (string) $this->staff->role;
    }

    public function isPortalAdmin(): bool
    {
        return $this->staff->isPortalAdmin();
    }

    public function isCourseModerator(): bool
    {
        return $this->staff->isCourseModerator();
    }

    public function isInstructor(): bool
    {
        return $this->staff->isInstructor();
    }

    public function isCourseTester(): bool
    {
        return $this->staff->isCourseTester();
    }

    /** Редактирование курса: модули, практики, сертификаты, метаданные (не преподаватель и не тестировщик). */
    public function canUseCourseAdminTools(): bool
    {
        return $this->isPortalAdmin() || $this->isCourseModerator();
    }

    /** Просмотр статистики обучающихся по курсу в админке. */
    public function canViewCourseLearnerStats(int $courseId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator()) {
            return true;
        }
        if ($this->isInstructor()) {
            return $this->assignedCourseIds()->containsStrict($courseId);
        }

        return false;
    }

    public function assertCanViewCourseLearnerStats(int $courseId): void
    {
        abort_unless($this->canViewCourseLearnerStats($courseId), 403);
    }

    /** Сброс попыток обучающего (не преподаватель). */
    public function canResetLearnerProgress(): bool
    {
        return $this->isPortalAdmin() || $this->isCourseModerator();
    }

    public function isReadOnlyCourseContent(): bool
    {
        return $this->isCourseTester() || $this->isInstructor();
    }

    public function canManageStaff(): bool
    {
        return $this->isPortalAdmin();
    }

    public function canViewPortalLearners(): bool
    {
        return $this->isPortalAdmin() || $this->isCourseModerator();
    }

    /** Заглушка портала, сброс переопределения из .env. */
    public function canManagePortalSettings(): bool
    {
        return $this->isPortalAdmin();
    }

    /** Просмотр портала от лица обучающегося. */
    public function canImpersonateLearners(): bool
    {
        return $this->canViewPortalLearners();
    }

    /** Просмотр админки с правами другого сотрудника (только чтение). */
    public function canPreviewStaffAdmin(): bool
    {
        return $this->canManageStaff();
    }

    public function canCreateCourses(): bool
    {
        return $this->isPortalAdmin() || $this->isCourseModerator();
    }

    public function assignedCourseIds(): Collection
    {
        if ($this->assignedCourseIds !== null) {
            return $this->assignedCourseIds;
        }
        if ($this->isInstructor() || $this->isCourseTester()) {
            $this->staff->loadMissing('courses');

            return $this->assignedCourseIds = $this->staff->courses->pluck('id');
        }

        return $this->assignedCourseIds = collect();
    }

    public function canAccessCourseInAdmin(int $courseId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator()) {
            return true;
        }

        return $this->assignedCourseIds()->containsStrict($courseId);
    }

    public function assertCanAccessCourseInAdmin(int $courseId): void
    {
        abort_unless($this->canAccessCourseInAdmin($courseId), 403);
    }

    public function canEditCourseMeta(int $courseId): bool
    {
        return $this->isPortalAdmin() || $this->isCourseModerator();
    }

    public function assertCanEditCourseMeta(int $courseId): void
    {
        abort_unless($this->canEditCourseMeta($courseId), 403);
    }

    public function assertCanCreateCourses(): void
    {
        abort_unless($this->canCreateCourses(), 403);
    }

    public function assertCanViewPortalLearners(): void
    {
        abort_unless($this->canViewPortalLearners(), 403);
    }

    public function assertCanManageStaff(): void
    {
        abort_unless($this->canManageStaff(), 403);
    }

    public function assertNotCourseTester(): void
    {
        abort_if($this->isCourseTester(), 403);
    }

    public function assertTesterSelectNext(string $next): void
    {
        if (! $this->isCourseTester()) {
            return;
        }
        abort_unless(in_array($next, ['content', 'quiz'], true), 403);
    }

    public function roleLabel(): string
    {
        return match ($this->staff->role) {
            PortalStaff::ROLE_PORTAL_ADMIN => 'Администратор портала',
            PortalStaff::ROLE_COURSE_MODERATOR => 'Модератор курсов',
            PortalStaff::ROLE_INSTRUCTOR => 'Инструктор',
            PortalStaff::ROLE_COURSE_TESTER => 'Тестировщик курса',
            default => $this->staff->role,
        };
    }
}
