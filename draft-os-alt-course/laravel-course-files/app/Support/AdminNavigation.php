<?php

namespace App\Support;

use App\Models\Course;
use App\Services\PortalStaffAccess;
use Illuminate\Support\Facades\Route;

/**
 * Хлебные крошки и активные пункты админ-оболочки по имени маршрута.
 */
final class AdminNavigation
{
    /**
     * Параметры для маршрутов /adm/kurs/{adminCourse}/… (slug из сессии или из id курса).
     *
     * @return array<string, string>
     */
    public static function adminCourseRouteParams(): array
    {
        $slug = (string) session('admin_course_slug', '');
        if ($slug === '') {
            $courseId = (int) session('admin_course_id', 0);
            if ($courseId > 0) {
                $slug = (string) (Course::query()->whereKey($courseId)->value('slug') ?? '');
            }
        }

        return $slug !== '' ? ['adminCourse' => $slug] : [];
    }

    /**
     * @return list<array{label: string, url: ?string}>
     */
    public static function breadcrumbs(): array
    {
        $name = Route::currentRouteName();
        $course = Course::query()->find((int) session('admin_course_id', 0));
        $ac = self::adminCourseRouteParams();

        $root = [['label' => 'Панель администратора', 'url' => route('admin.panel')]];

        return match ($name) {
            'admin.panel' => $root,
            'admin.activity' => [
                ...$root,
                ['label' => 'События', 'url' => null],
            ],
            'admin.settings',
            'admin.settings.maintenance',
            'admin.settings.maintenance.reset',
            'admin.settings.impersonate',
            'admin.settings.learner-search' => [
                ...$root,
                ['label' => 'Настройки', 'url' => null],
            ],
            'admin.staff.index', 'admin.staff.create', 'admin.staff.store' => [
                ...$root,
                ['label' => 'Сотрудники', 'url' => null],
            ],
            'admin.staff.edit', 'admin.staff.update', 'admin.staff.destroy' => [
                ...$root,
                ['label' => 'Сотрудники', 'url' => route('admin.staff.index')],
                ['label' => 'Редактирование', 'url' => null],
            ],
            'admin.docker.library',
            'admin.docker.library.store',
            'admin.docker.library.stats.refresh',
            'admin.docker.library.build',
            'admin.docker.library.destroy' => [
                ...$root,
                ['label' => 'Библиотека Docker', 'url' => null],
            ],
            'admin.courses.index', 'admin.courses.create', 'admin.courses.store' => [
                ...$root,
                ['label' => 'Курсы', 'url' => null],
            ],
            'admin.courses.edit', 'admin.courses.update', 'admin.courses.archive', 'admin.courses.unarchive' => [
                ...$root,
                ['label' => 'Курсы', 'url' => route('admin.courses.index')],
                ['label' => 'Редактирование курса', 'url' => null],
            ],
            'admin.learners.portal' => [
                ...$root,
                ['label' => 'Обучающиеся', 'url' => null],
            ],
            'admin.learners.people.detail' => [
                ...$root,
                ['label' => 'Обучающиеся', 'url' => route('admin.learners.portal')],
                ['label' => 'Карточка', 'url' => null],
            ],
            default => self::courseScopedBreadcrumbs($name, $course, $ac),
        };
    }

    /**
     * @param  array<string, string>  $ac
     * @return list<array{label: string, url: ?string}>
     */
    private static function courseScopedBreadcrumbs(?string $name, ?Course $course, array $ac): array
    {
        $root = [['label' => 'Панель', 'url' => route('admin.panel')]];
        $courses = ['label' => 'Курсы', 'url' => route('admin.courses.index')];
        $courseTitle = $course ? (string) $course->title : (string) (session('admin_course_title') ?: 'Курс');
        $courseCrumb = ['label' => $courseTitle, 'url' => $ac !== [] ? route('admin.theory.index', $ac) : null];

        $tabKey = self::courseTab();
        $tab = match ($tabKey) {
            'course_modules' => 'Модули',
            'course_content' => 'Содержимое',
            'learners' => 'Обучающиеся',
            'certificates' => 'Сертификаты',
            default => null,
        };

        if ($tab === null) {
            return [...$root, $courses, $courseCrumb];
        }

        return [...$root, $courses, $courseCrumb, ['label' => $tab, 'url' => null]];
    }

    public static function sidebarActive(): string
    {
        $name = Route::currentRouteName();

        if (str_starts_with((string) $name, 'admin.docker')) {
            return 'docker';
        }
        if (str_starts_with((string) $name, 'admin.staff')) {
            return 'staff';
        }
        if ($name === 'admin.learners.portal') {
            return 'learners';
        }
        if ($name === 'admin.learners.people.detail') {
            return 'learners';
        }
        if (
            str_starts_with((string) $name, 'admin.courses')
            || $name === 'admin.courses.index'
        ) {
            return 'courses';
        }
        if (self::isCourseAdminRoute($name)) {
            return 'courses';
        }

        return 'panel';
    }

    public static function courseTab(): ?string
    {
        $name = Route::currentRouteName();

        return match ($name) {
            'admin.course.settings',
            'admin.course.settings.save',
            'admin.course.modules',
            'admin.course.settings.module.store',
            'admin.course.settings.module.update',
            'admin.course.settings.module.destroy',
            'admin.course.settings.modules.reorder',
            'admin.course.module.sections',
            'admin.course.module.practice',
            'admin.course.module.practice.save',
            'admin.course.module.sections.store',
            'admin.course.module.sections.update',
            'admin.course.module.sections.destroy',
            'admin.course.module.sections.reorder',
            'admin.course.module.section.settings',
            'admin.course.module.section.settings.save',
            'admin.course.section.panel.data',
            'admin.course.section.panel.save' => 'course_modules',
            'admin.theory.index',
            'admin.theory.zip',
            'admin.theory.preview-theory',
            'admin.theory.preview-theory-quiz',
            'admin.theory.preview-practice',
            'admin.theory.preview-module-exam',
            'admin.theory.preview-final-lab',
            'admin.theory.edit',
            'admin.theory.update',
            'admin.theory.container.start',
            'admin.theory.container.finish',
            'admin.course.module.content.edit',
            'admin.course.module.content.update',
            'admin.quiz.index',
            'admin.quiz.edit.module',
            'admin.quiz.edit.final',
            'admin.quiz.save.module',
            'admin.quiz.save.final',
            'admin.practice.images.index',
            'admin.practice.images.create',
            'admin.practice.images.store',
            'admin.practice.images.system.copy',
            'admin.practice.images.stats.refresh',
            'admin.practice.images.pkg.search',
            'admin.practice.images.edit',
            'admin.practice.images.update',
            'admin.practice.images.destroy',
            'admin.practice.images.build',
            'admin.practice.images.export' => 'course_content',
            'admin.learners.course',
            'admin.learners.course.detail',
            'admin.learners.course.learner.module',
            'admin.learners.course.learner.reset' => 'learners',
            'admin.certificates',
            'admin.certificates.show' => 'certificates',
            default => null,
        };
    }

    public static function showCourseChrome(): bool
    {
        return self::courseTab() !== null;
    }

    public static function currentCourse(): ?Course
    {
        return Course::query()->find((int) session('admin_course_id', 0));
    }

    private static function isCourseAdminRoute(?string $name): bool
    {
        if ($name === null) {
            return false;
        }

        return str_starts_with($name, 'admin.theory.')
            || str_starts_with($name, 'admin.quiz.')
            || str_starts_with($name, 'admin.course.')
            || str_starts_with($name, 'admin.practice.')
            || str_starts_with($name, 'admin.learners.course')
            || str_starts_with($name, 'admin.certificates');
    }

    public static function canSeeStaff(): bool
    {
        $access = PortalStaffAccess::fromLearnerId((int) session('learner_id', 0));

        return $access?->canManageStaff() ?? false;
    }

    public static function canSeePortalLearners(): bool
    {
        $access = PortalStaffAccess::fromLearnerId((int) session('learner_id', 0));

        return $access?->canViewPortalLearners() ?? false;
    }
}
