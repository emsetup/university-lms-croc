<?php

namespace App\Services;

use App\Models\Course;
use App\Models\PortalStaff;
use App\Models\PracticeImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Права сотрудника портала (OIDC + запись в portal_staff).
 */
final class PortalStaffAccess
{
    private ?Collection $assignedCourseIds = null;

    private ?Collection $ownedCourseIds = null;

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

    public function isCourseCreator(): bool
    {
        return $this->staff->isCourseCreator();
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
        return $this->isPortalAdmin()
            || $this->isCourseModerator()
            || $this->isCourseCreator();
    }

    /** Просмотр статистики обучающихся по курсу в админке. */
    public function canViewCourseLearnerStats(int $courseId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator()) {
            return true;
        }
        if ($this->isCourseCreator()) {
            return $this->ownedCourseIds()->containsStrict($courseId);
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

    /** Сброс попыток по конкретному курсу (создатель — только свои курсы). */
    public function canResetLearnerProgressForCourse(int $courseId): bool
    {
        if ($this->canResetLearnerProgress()) {
            return true;
        }

        return $this->isCourseCreator() && $this->ownedCourseIds()->containsStrict($courseId);
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
        return $this->isPortalAdmin()
            || $this->isCourseModerator()
            || $this->isCourseCreator();
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

    /**
     * Курсы, созданные этим сотрудником (владение).
     * Для роли «Создатель курсов» — единственный источник доступа к редактированию.
     */
    public function ownedCourseIds(): Collection
    {
        if ($this->ownedCourseIds !== null) {
            return $this->ownedCourseIds;
        }
        if (! $this->isCourseCreator()) {
            return $this->ownedCourseIds = collect();
        }
        if (! Schema::hasColumn('courses', 'created_by_portal_staff_id')) {
            return $this->ownedCourseIds = collect();
        }

        return $this->ownedCourseIds = Course::query()
            ->where('created_by_portal_staff_id', (int) $this->staff->id)
            ->pluck('id');
    }

    public function canAccessCourseInAdmin(int $courseId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator()) {
            return true;
        }
        if ($this->isCourseCreator()) {
            return $this->ownedCourseIds()->containsStrict($courseId);
        }

        return $this->assignedCourseIds()->containsStrict($courseId);
    }

    public function assertCanAccessCourseInAdmin(int $courseId): void
    {
        abort_unless($this->canAccessCourseInAdmin($courseId), 403);
    }

    public function canEditCourseMeta(int $courseId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator()) {
            return true;
        }
        if ($this->isCourseCreator()) {
            return $this->ownedCourseIds()->containsStrict($courseId);
        }

        return false;
    }

    public function assertCanEditCourseMeta(int $courseId): void
    {
        abort_unless($this->canEditCourseMeta($courseId), 403);
    }

    public function practiceImageOwnerStaffId(): ?int
    {
        $id = (int) $this->staff->id;

        return $id > 0 ? $id : null;
    }

    public function canEditPracticeImage(int $practiceImageId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator()) {
            return true;
        }
        if (! $this->isCourseCreator()) {
            return false;
        }
        if (! Schema::hasColumn('practice_images', 'created_by_portal_staff_id')) {
            return false;
        }

        return PracticeImage::query()
            ->whereKey($practiceImageId)
            ->where('created_by_portal_staff_id', (int) $this->staff->id)
            ->exists();
    }

    public function assertCanEditPracticeImage(PracticeImage|int $image): void
    {
        $id = $image instanceof PracticeImage ? (int) $image->id : $image;
        abort_unless($this->canEditPracticeImage($id), 403);
    }

    /** Использовать собранный образ в настройках своего курса (только свои образы). */
    public function canAssignPracticeImageToCourse(int $practiceImageId, int $courseId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator()) {
            return true;
        }
        if ($this->isCourseCreator()) {
            if (! $this->ownedCourseIds()->containsStrict($courseId)) {
                return false;
            }

            return $this->canEditPracticeImage($practiceImageId);
        }

        return false;
    }

    public function assertCanAssignPracticeImageToCourse(int $practiceImageId, int $courseId): void
    {
        abort_unless($this->canAssignPracticeImageToCourse($practiceImageId, $courseId), 403);
    }

    /**
     * @param  Builder<PracticeImage>  $query
     * @return Builder<PracticeImage>
     */
    public function scopePracticeImagesForStaff(Builder $query): Builder
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator()) {
            return $query;
        }
        if ($this->isCourseCreator() && Schema::hasColumn('practice_images', 'created_by_portal_staff_id')) {
            return $query->where('created_by_portal_staff_id', (int) $this->staff->id);
        }

        return $query;
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
            PortalStaff::ROLE_COURSE_CREATOR => 'Создатель курсов',
            PortalStaff::ROLE_INSTRUCTOR => 'Инструктор',
            PortalStaff::ROLE_COURSE_TESTER => 'Тестировщик курса',
            default => $this->staff->role,
        };
    }
}
