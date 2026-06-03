<?php

namespace App\Services;

use App\Models\Course;
use App\Models\PortalStaff;
use App\Models\PracticeImage;
use App\Support\PortalStaffPermissionCatalog as Perm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Права сотрудника портала (OIDC + запись в portal_staff).
 */
final class PortalStaffAccess
{
    private ?Collection $assignedCourseIds = null;

    private ?Collection $ownedCourseIds = null;

    private ?Collection $editableCourseIds = null;

    private ?Collection $practiceImageIdsForEditableCourses = null;

    private ?PortalStaffPermissionResolver $permissionResolver = null;

    public function __construct(private PortalStaff $staff) {}

    private function perms(): PortalStaffPermissionResolver
    {
        return $this->permissionResolver ??= new PortalStaffPermissionResolver($this->staff);
    }

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
        return $this->staff->isPortalAdmin() || $this->perms()->hasRole(PortalStaff::ROLE_PORTAL_ADMIN);
    }

    public function isCourseModerator(): bool
    {
        return $this->staff->isCourseModerator() || $this->perms()->hasRole(PortalStaff::ROLE_COURSE_MODERATOR);
    }

    public function isCourseCreator(): bool
    {
        return $this->staff->isCourseCreator() || $this->perms()->hasRole(PortalStaff::ROLE_COURSE_CREATOR);
    }

    public function isCourseEditor(): bool
    {
        return $this->staff->isCourseEditor() || $this->perms()->hasRole(PortalStaff::ROLE_COURSE_EDITOR);
    }

    public function isInstructor(): bool
    {
        return $this->staff->isInstructor() || $this->perms()->hasRole(PortalStaff::ROLE_INSTRUCTOR);
    }

    public function isCourseTester(): bool
    {
        return $this->staff->isCourseTester() || $this->perms()->hasRole(PortalStaff::ROLE_COURSE_TESTER);
    }

    /** Редактирование курса: модули, практики, сертификаты, метаданные (не преподаватель и не тестировщик). */
    public function canUseCourseAdminTools(): bool
    {
        return $this->isPortalAdmin()
            || $this->isCourseModerator()
            || $this->isCourseCreator()
            || $this->isCourseEditor()
            || $this->perms()->hasAny(
                Perm::COURSES_MANAGE_ALL,
                Perm::COURSES_MANAGE_ASSIGNED,
                Perm::CONTENT_EDIT_ALL,
                Perm::CONTENT_EDIT_ASSIGNED,
            );
    }

    /** Просмотр статистики обучающихся по курсу в админке. */
    public function canViewCourseLearnerStats(int $courseId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator() || $this->perms()->has(Perm::LEARNERS_VIEW_ALL)) {
            return true;
        }
        if ($this->perms()->has(Perm::LEARNERS_VIEW_ASSIGNED)
            && $this->assignedCourseIds()->containsStrict($courseId)) {
            return true;
        }
        if ($this->isCourseCreator()) {
            return $this->ownedCourseIds()->containsStrict($courseId);
        }
        if ($this->isCourseEditor()) {
            return $this->editableCourseIds()->containsStrict($courseId);
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
        return $this->isPortalAdmin() || $this->isCourseModerator() || $this->perms()->has(Perm::LEARNERS_RESET);
    }

    /** Сброс попыток по конкретному курсу. */
    public function canResetLearnerProgressForCourse(int $courseId): bool
    {
        if ($this->canResetLearnerProgress()) {
            return true;
        }

        if ($this->isCourseCreator()) {
            return $this->ownedCourseIds()->containsStrict($courseId);
        }

        if ($this->isCourseEditor()) {
            return $this->editableCourseIds()->containsStrict($courseId);
        }

        return false;
    }

    public function isReadOnlyCourseContent(): bool
    {
        if ($this->perms()->hasAny(Perm::CONTENT_EDIT_ALL, Perm::CONTENT_EDIT_ASSIGNED)) {
            return false;
        }

        return $this->isCourseTester() || $this->isInstructor();
    }

    public function canManageStaff(): bool
    {
        return $this->isPortalAdmin() || $this->perms()->has(Perm::STAFF_MANAGE);
    }

    public function canViewPortalLearners(): bool
    {
        return $this->isPortalAdmin() || $this->isCourseModerator() || $this->perms()->has(Perm::PEOPLE_VIEW);
    }

    /** Заглушка портала, сброс переопределения из .env. */
    public function canManagePortalSettings(): bool
    {
        return $this->isPortalAdmin() || $this->perms()->has(Perm::SETTINGS_MANAGE);
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
            || $this->isCourseCreator()
            || $this->isCourseEditor()
            || $this->perms()->has(Perm::COURSES_CREATE);
    }

    public function assignedCourseIds(): Collection
    {
        if ($this->assignedCourseIds !== null) {
            return $this->assignedCourseIds;
        }
        $base = collect();
        if ($this->isInstructor() || $this->isCourseTester() || $this->isCourseEditor()) {
            $this->staff->loadMissing('courses');
            $base = $this->staff->courses->pluck('id');
        }

        return $this->assignedCourseIds = $base
            ->merge($this->perms()->groupCourseIds())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Курсы, созданные этим сотрудником (владение).
     */
    public function ownedCourseIds(): Collection
    {
        if ($this->ownedCourseIds !== null) {
            return $this->ownedCourseIds;
        }
        if (! $this->isCourseCreator() && ! $this->isCourseEditor()) {
            return $this->ownedCourseIds = collect();
        }
        if (! Schema::hasColumn('courses', 'created_by_portal_staff_id')) {
            return $this->ownedCourseIds = collect();
        }

        return $this->ownedCourseIds = Course::query()
            ->where('created_by_portal_staff_id', (int) $this->staff->id)
            ->pluck('id');
    }

    /** Назначенные + свои (для роли «Редактор курсов»). */
    public function editableCourseIds(): Collection
    {
        if ($this->editableCourseIds !== null) {
            return $this->editableCourseIds;
        }
        if ($this->isCourseEditor()) {
            return $this->editableCourseIds = $this->assignedCourseIds()
                ->merge($this->ownedCourseIds())
                ->unique()
                ->values();
        }

        return $this->editableCourseIds = collect();
    }

    public function canAccessCourseInAdmin(int $courseId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator() || $this->perms()->has(Perm::COURSES_MANAGE_ALL)) {
            return true;
        }
        if ($this->perms()->has(Perm::COURSES_MANAGE_ASSIGNED)
            && $this->assignedCourseIds()->containsStrict($courseId)) {
            return true;
        }
        if ($this->isCourseCreator()) {
            return $this->ownedCourseIds()->containsStrict($courseId);
        }
        if ($this->isCourseEditor()) {
            return $this->editableCourseIds()->containsStrict($courseId);
        }

        return $this->assignedCourseIds()->containsStrict($courseId);
    }

    public function assertCanAccessCourseInAdmin(int $courseId): void
    {
        abort_unless($this->canAccessCourseInAdmin($courseId), 403);
    }

    public function canEditCourseMeta(int $courseId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator() || $this->perms()->has(Perm::COURSES_MANAGE_ALL)) {
            return true;
        }
        if ($this->perms()->has(Perm::COURSES_MANAGE_ASSIGNED)
            && $this->assignedCourseIds()->containsStrict($courseId)) {
            return true;
        }
        if ($this->isCourseCreator()) {
            return $this->ownedCourseIds()->containsStrict($courseId);
        }
        if ($this->isCourseEditor()) {
            return $this->editableCourseIds()->containsStrict($courseId);
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
        if ($this->isPortalAdmin() || $this->isCourseModerator() || $this->perms()->has(Perm::DOCKER_MANAGE_ALL)) {
            return true;
        }
        if (! $this->isCourseCreator() && ! $this->isCourseEditor()) {
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

    /** Создать копию образа (редактор может клонировать образы, уже привязанные к его курсам). */
    public function canDuplicatePracticeImageFrom(int $practiceImageId): bool
    {
        if ($this->canEditPracticeImage($practiceImageId)) {
            return true;
        }

        return $this->isCourseEditor()
            && $this->practiceImageIdsLinkedToEditableCourses()->containsStrict($practiceImageId);
    }

    public function assertCanDuplicatePracticeImageFrom(PracticeImage|int $image): void
    {
        $id = $image instanceof PracticeImage ? (int) $image->id : $image;
        abort_unless($this->canDuplicatePracticeImageFrom($id), 403);
    }

    /** Привязать собранный образ к курсу, который сотрудник может редактировать. */
    public function canAssignPracticeImageToCourse(int $practiceImageId, int $courseId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator() || $this->perms()->has(Perm::DOCKER_MANAGE_ALL)) {
            return true;
        }
        if ($this->isCourseCreator()) {
            if (! $this->ownedCourseIds()->containsStrict($courseId)) {
                return false;
            }

            return $this->canEditPracticeImage($practiceImageId);
        }
        if ($this->isCourseEditor()) {
            if (! $this->editableCourseIds()->containsStrict($courseId)) {
                return false;
            }
            if ($this->canEditPracticeImage($practiceImageId)) {
                return true;
            }

            return $this->practiceImageIdsLinkedToEditableCourses()->containsStrict($practiceImageId);
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
        if ($this->isPortalAdmin() || $this->isCourseModerator() || $this->perms()->has(Perm::DOCKER_MANAGE_ALL)) {
            return $query;
        }
        if (($this->isCourseCreator() || $this->isCourseEditor())
            && Schema::hasColumn('practice_images', 'created_by_portal_staff_id')) {
            $staffId = (int) $this->staff->id;

            return $query->where(function (Builder $q) use ($staffId): void {
                $q->where('created_by_portal_staff_id', $staffId);
                if ($this->isCourseEditor()) {
                    $linked = $this->practiceImageIdsLinkedToEditableCourses();
                    if ($linked->isNotEmpty()) {
                        $q->orWhereIn('id', $linked->all());
                    }
                }
            });
        }

        return $query;
    }

    /** Образы, уже используемые в назначенных/своих курсах редактора (для выбора в практике). */
    public function practiceImageIdsLinkedToEditableCourses(): Collection
    {
        if ($this->practiceImageIdsForEditableCourses !== null) {
            return $this->practiceImageIdsForEditableCourses;
        }
        if (! $this->isCourseEditor()) {
            return $this->practiceImageIdsForEditableCourses = collect();
        }

        $courseIds = $this->editableCourseIds()->map(fn ($id) => (int) $id)->all();
        if ($courseIds === []) {
            return $this->practiceImageIdsForEditableCourses = collect();
        }

        $ids = collect();

        if (Schema::hasTable('course_module_practice_settings') && Schema::hasTable('course_modules')) {
            $moduleIds = DB::table('course_modules')->whereIn('course_id', $courseIds)->pluck('id');
            if ($moduleIds->isNotEmpty()) {
                $ids = $ids->merge(
                    DB::table('course_module_practice_settings')
                        ->whereIn('course_module_id', $moduleIds)
                        ->whereNotNull('practice_image_id')
                        ->pluck('practice_image_id')
                );
            }
        }

        if (Schema::hasColumn('courses', 'final_lab_practice_image_id')) {
            $ids = $ids->merge(
                DB::table('courses')
                    ->whereIn('id', $courseIds)
                    ->whereNotNull('final_lab_practice_image_id')
                    ->pluck('final_lab_practice_image_id')
            );
        }

        return $this->practiceImageIdsForEditableCourses = $ids
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
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
            PortalStaff::ROLE_COURSE_EDITOR => 'Редактор курсов',
            PortalStaff::ROLE_INSTRUCTOR => 'Инструктор',
            PortalStaff::ROLE_COURSE_TESTER => 'Тестировщик курса',
            default => $this->staff->role,
        };
    }
}
