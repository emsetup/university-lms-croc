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

    private ?CourseContentGrantResolver $grantResolver = null;

    public function __construct(private PortalStaff $staff) {}

    private function grants(): CourseContentGrantResolver
    {
        return $this->grantResolver ??= new CourseContentGrantResolver($this->staff);
    }

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

    public function isCourseContributor(): bool
    {
        return $this->staff->isCourseContributor() || $this->perms()->hasRole(PortalStaff::ROLE_COURSE_CONTRIBUTOR);
    }

    /** Редактирование курса: модули, практики, сертификаты, метаданные (не преподаватель и не тестировщик). */
    public function canUseCourseAdminTools(): bool
    {
        if ($this->isCourseContributor()) {
            return $this->grants()->grantedCourseIds()->isNotEmpty();
        }

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

        if ($this->isCourseContributor()) {
            return false;
        }

        return $this->isCourseTester() || $this->isInstructor();
    }

    /** Полный доступ к курсу по старой модели (без сужения грантами). */
    public function hasLegacyFullCourseAccess(int $courseId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator() || $this->perms()->has(Perm::COURSES_MANAGE_ALL)) {
            return true;
        }
        if ($this->perms()->has(Perm::COURSES_MANAGE_ASSIGNED)
            && $this->assignedCourseIds()->containsStrict($courseId)) {
            return true;
        }
        if ($this->isCourseCreator() && $this->ownedCourseIds()->containsStrict($courseId)) {
            return true;
        }
        if ($this->isCourseEditor() && $this->editableCourseIds()->containsStrict($courseId)) {
            return true;
        }

        return false;
    }

    /** Гранты сужают доступ редактора на курсе с strict_grants. */
    public function grantsNarrowCourseAccess(int $courseId): bool
    {
        if (! $this->grants()->hasGrantsTable()) {
            return false;
        }
        if (! $this->grants()->courseUsesStrictGrants($courseId)) {
            return false;
        }
        if ($this->grants()->isCourseOwner($courseId)) {
            return false;
        }
        if ($this->isPortalAdmin() || $this->isCourseModerator()) {
            return false;
        }

        return $this->grants()->hasAnyGrantOnCourse($courseId);
    }

    public function usesGrantBasedAccess(int $courseId): bool
    {
        if ($this->isCourseContributor()) {
            return true;
        }
        if ($this->isPortalAdmin() || $this->isCourseModerator()) {
            return false;
        }
        if ($this->grants()->isCourseOwner($courseId)) {
            return false;
        }
        if ($this->grants()->hasGrantsTable() && $this->grants()->hasAnyGrantOnCourse($courseId)) {
            return true;
        }

        return $this->grantsNarrowCourseAccess($courseId);
    }

    public function canViewCourseInAdmin(int $courseId): bool
    {
        return $this->canAccessCourseInAdmin($courseId);
    }

    /** Вкладка «Модули» в настройках: структура курса или гранты на отдельные модули. */
    public function canAccessCourseModulesTab(int $courseId): bool
    {
        if ($this->canEditCourseMeta($courseId)) {
            return true;
        }

        return $this->usesGrantBasedAccess($courseId)
            && $this->accessibleModulesForCourse($courseId)->isNotEmpty();
    }

    public function assertCanAccessCourseModulesTab(int $courseId): void
    {
        abort_unless($this->canAccessCourseModulesTab($courseId), 403);
    }

    /** Добавление модулей в курс (удаление и порядок — через canEditCourseMeta). */
    public function canEditCourseStructure(int $courseId): bool
    {
        if ($this->canEditCourseMeta($courseId)) {
            return true;
        }

        if (! $this->grants()->hasGrantsTable() || ! $this->grants()->hasAnyGrantOnCourse($courseId)) {
            return false;
        }

        if ($this->grants()->canEditCourseContent($courseId)) {
            return true;
        }

        return $this->grants()->canEditAllModulesInCourse($courseId);
    }

    public function assertCanEditCourseStructure(int $courseId): void
    {
        abort_unless($this->canEditCourseStructure($courseId), 403);
    }

    public function canViewModuleInAdmin(int $moduleId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator()) {
            return true;
        }

        $module = \App\Models\CourseModule::query()->find($moduleId);
        if ($module === null) {
            return false;
        }
        $courseId = (int) $module->course_id;

        if ($this->usesGrantBasedAccess($courseId)) {
            return $this->grants()->canViewModule($moduleId);
        }

        return $this->canAccessCourseInAdmin($courseId);
    }

    public function canEditModuleContent(int $moduleId): bool
    {
        if ($this->isReadOnlyCourseContent()) {
            return false;
        }
        if ($this->isPortalAdmin() || $this->isCourseModerator() || $this->perms()->has(Perm::CONTENT_EDIT_ALL)) {
            return true;
        }

        $module = \App\Models\CourseModule::query()->find($moduleId);
        if ($module === null) {
            return false;
        }
        $courseId = (int) $module->course_id;

        if ($this->usesGrantBasedAccess($courseId)) {
            return $this->grants()->canEditModule($moduleId);
        }

        return $this->canEditCourseMeta($courseId);
    }

    public function canViewSectionInAdmin(int $sectionId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator()) {
            return true;
        }

        $section = \App\Models\CourseSection::query()->find($sectionId);
        if ($section === null) {
            return false;
        }
        $courseId = (int) $section->course_id;

        if ($this->usesGrantBasedAccess($courseId)) {
            return $this->grants()->canViewSection($sectionId);
        }

        return $this->canAccessCourseInAdmin($courseId);
    }

    public function canEditSection(int $sectionId): bool
    {
        if ($this->isReadOnlyCourseContent()) {
            return false;
        }
        if ($this->isPortalAdmin() || $this->isCourseModerator() || $this->perms()->has(Perm::CONTENT_EDIT_ALL)) {
            return true;
        }

        $section = \App\Models\CourseSection::query()->find($sectionId);
        if ($section === null) {
            return false;
        }
        $courseId = (int) $section->course_id;

        if ($this->usesGrantBasedAccess($courseId)) {
            return $this->grants()->canEditSection($sectionId);
        }

        return $this->canEditCourseMeta($courseId);
    }

    public function canManageCollaborators(int $courseId): bool
    {
        if ($this->isPortalAdmin() || $this->isCourseModerator() || $this->canManageStaff()) {
            return true;
        }
        if ($this->grants()->isCourseOwner($courseId)) {
            return true;
        }
        if ($this->grants()->canManageCourse($courseId)) {
            return true;
        }

        return $this->hasLegacyFullCourseAccess($courseId) && ! $this->grantsNarrowCourseAccess($courseId);
    }

    public function assertCanManageCollaborators(int $courseId): void
    {
        abort_unless($this->canManageCollaborators($courseId), 403);
    }

    public function assertCanEditModuleContent(int $moduleId): void
    {
        abort_unless($this->canEditModuleContent($moduleId), 403);
    }

    public function assertCanViewSectionInAdmin(int $sectionId): void
    {
        abort_unless($this->canViewSectionInAdmin($sectionId), 403);
    }

    public function assertCanEditSection(int $sectionId): void
    {
        abort_unless($this->canEditSection($sectionId), 403);
    }

    /** @return Collection<int, int> */
    public function accessibleModulesForCourse(int $courseId): Collection
    {
        if (! $this->usesGrantBasedAccess($courseId)) {
            if (! $this->canAccessCourseInAdmin($courseId)) {
                return collect();
            }

            return \App\Models\CourseModule::query()
                ->where('course_id', $courseId)
                ->orderBy('sort')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id);
        }

        return $this->grants()->accessibleModuleIdsForCourse($courseId);
    }

    /** @return Collection<int, int> */
    public function accessibleSectionsForModule(int $moduleId): Collection
    {
        $module = \App\Models\CourseModule::query()->find($moduleId);
        if ($module === null) {
            return collect();
        }
        $courseId = (int) $module->course_id;

        if (! $this->usesGrantBasedAccess($courseId)) {
            if (! $this->canAccessCourseInAdmin($courseId)) {
                return collect();
            }

            return \App\Models\CourseSection::query()
                ->where('course_module_id', $moduleId)
                ->orderBy('sort')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id);
        }

        return $this->grants()->accessibleSectionIdsForModule($moduleId);
    }

    /** @return Collection<int, int> */
    public function grantedCourseIds(): Collection
    {
        return $this->grants()->grantedCourseIds();
    }

    public function isCollaboratorOnCourse(int $courseId): bool
    {
        return $this->grants()->hasAnyGrantOnCourse($courseId) && ! $this->grants()->isCourseOwner($courseId);
    }

    public function canViewCourseSettings(int $courseId): bool
    {
        return $this->canEditCourseMeta($courseId) || $this->canManageCollaborators($courseId);
    }

    public function canViewCourseDocker(int $courseId): bool
    {
        if ($this->usesGrantBasedAccess($courseId)) {
            return $this->grants()->canEditCourseContent($courseId);
        }

        return $this->canEditCourseMeta($courseId);
    }

    public function canViewCourseLearnersTab(int $courseId): bool
    {
        if ($this->usesGrantBasedAccess($courseId)) {
            return $this->grants()->canManageCourse($courseId)
                || ($this->hasLegacyFullCourseAccess($courseId) && $this->canViewCourseLearnerStats($courseId));
        }

        return $this->canViewCourseLearnerStats($courseId);
    }

    public function canEditModuleQuiz(int $moduleId, string $kind): bool
    {
        if ($kind === 'final_lab') {
            $module = \App\Models\CourseModule::query()->find($moduleId);

            return $module !== null && $this->canEditCourseMeta((int) $module->course_id);
        }

        $sectionType = match ($kind) {
            'theory_quiz' => \App\Models\CourseSection::TYPE_QUIZ,
            'module_exam' => \App\Models\CourseSection::TYPE_EXAM,
            default => null,
        };
        if ($sectionType === null) {
            return $this->canEditModuleContent($moduleId);
        }

        $section = \App\Models\CourseSection::query()
            ->where('course_module_id', $moduleId)
            ->where('type', $sectionType)
            ->orderBy('sort')
            ->orderBy('id')
            ->first();

        if ($section !== null) {
            return $this->canEditSection((int) $section->id);
        }

        return $this->canEditModuleContent($moduleId);
    }

    public function assertCanEditModuleQuiz(int $moduleId, string $kind): void
    {
        abort_unless($this->canEditModuleQuiz($moduleId, $kind), 403);
    }

    public function canViewAnyCourseContent(int $courseId): bool
    {
        if ($this->canAccessCourseInAdmin($courseId) && ! $this->usesGrantBasedAccess($courseId)) {
            return true;
        }

        return $this->accessibleModulesForCourse($courseId)->isNotEmpty();
    }

    /** Предпросмотр курса на learner-треке (черновики, архив, свободная навигация). */
    public function canPreviewCourse(int $courseId): bool
    {
        return $this->canViewAnyCourseContent($courseId);
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
        if ($this->isCourseCreator() && $this->ownedCourseIds()->containsStrict($courseId)) {
            return true;
        }
        if ($this->isCourseEditor() && $this->editableCourseIds()->containsStrict($courseId)) {
            return true;
        }
        if ($this->isCourseContributor() && $this->grants()->canViewCourse($courseId)) {
            return true;
        }
        if ($this->grants()->hasAnyGrantOnCourse($courseId)) {
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
        if ($this->isPortalAdmin() || $this->isCourseModerator() || $this->perms()->has(Perm::COURSES_MANAGE_ALL)) {
            return true;
        }
        if ($this->usesGrantBasedAccess($courseId)) {
            return $this->grants()->canManageCourse($courseId);
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
        abort_unless($next === 'content', 403);
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
            PortalStaff::ROLE_COURSE_CONTRIBUTOR => 'Соавтор курса',
            default => $this->staff->role,
        };
    }
}
