<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PortalStaff;

/**
 * Ключи прав для групп сотрудников (дополняют роль в {@see PortalStaffPermissionResolver}).
 */
final class PortalStaffPermissionCatalog
{
    public const STAFF_MANAGE = 'staff.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const PEOPLE_VIEW = 'people.view';

    public const COURSES_CREATE = 'courses.create';

    public const COURSES_MANAGE_ALL = 'courses.manage_all';

    public const COURSES_MANAGE_ASSIGNED = 'courses.manage_assigned';

    public const CONTENT_EDIT_ALL = 'content.edit_all';

    public const CONTENT_EDIT_ASSIGNED = 'content.edit_assigned';

    public const LEARNERS_VIEW_ALL = 'learners.view_all';

    public const LEARNERS_VIEW_ASSIGNED = 'learners.view_assigned';

    public const LEARNERS_RESET = 'learners.reset_progress';

    public const DOCKER_MANAGE_ALL = 'docker.manage_all';

    public const DOCKER_MANAGE_OWN = 'docker.manage_own';

    /** Права, для которых в группе можно указать привязку к курсам. */
    public const ASSIGNED_SCOPE_KEYS = [
        self::COURSES_MANAGE_ASSIGNED,
        self::CONTENT_EDIT_ASSIGNED,
        self::LEARNERS_VIEW_ASSIGNED,
    ];

    /**
     * @return list<array{key: string, title: string, hint: string}>
     */
    public static function sections(): array
    {
        return [
            [
                'title' => 'Админка и портал',
                'items' => [
                    ['key' => self::STAFF_MANAGE, 'title' => 'Сотрудники', 'hint' => 'Раздел «Сотрудники», группы и учётные записи'],
                    ['key' => self::SETTINGS_MANAGE, 'title' => 'Настройки портала', 'hint' => 'Заглушка, просмотр от лица других'],
                    ['key' => self::PEOPLE_VIEW, 'title' => 'Люди (портал)', 'hint' => 'Список обучающихся по всем курсам'],
                ],
            ],
            [
                'title' => 'Курсы',
                'items' => [
                    ['key' => self::COURSES_CREATE, 'title' => 'Создание курсов', 'hint' => 'Кнопка «Создать курс»'],
                    ['key' => self::COURSES_MANAGE_ALL, 'title' => 'Все курсы', 'hint' => 'Любой курс в каталоге админки'],
                    ['key' => self::COURSES_MANAGE_ASSIGNED, 'title' => 'Назначенные курсы', 'hint' => 'Только курсы группы и личная привязка сотрудника'],
                ],
            ],
            [
                'title' => 'Контент и обучающиеся',
                'items' => [
                    ['key' => self::CONTENT_EDIT_ALL, 'title' => 'Контент — все курсы', 'hint' => 'Теория, тесты, практики'],
                    ['key' => self::CONTENT_EDIT_ASSIGNED, 'title' => 'Контент — назначенные', 'hint' => 'В рамках доступных курсов'],
                    ['key' => self::LEARNERS_VIEW_ALL, 'title' => 'Статистика — все курсы', 'hint' => 'Отчёты по обучающимся'],
                    ['key' => self::LEARNERS_VIEW_ASSIGNED, 'title' => 'Статистика — назначенные', 'hint' => 'По курсам группы'],
                    ['key' => self::LEARNERS_RESET, 'title' => 'Сброс попыток', 'hint' => 'Сброс прогресса обучающихся'],
                ],
            ],
            [
                'title' => 'Docker',
                'items' => [
                    ['key' => self::DOCKER_MANAGE_ALL, 'title' => 'Все образы', 'hint' => 'Библиотека Docker целиком'],
                    ['key' => self::DOCKER_MANAGE_OWN, 'title' => 'Свои образы', 'hint' => 'Созданные сотрудником и в его курсах'],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function allKeys(): array
    {
        $keys = [];
        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    public static function label(string $key): string
    {
        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                if ($item['key'] === $key) {
                    return $item['title'];
                }
            }
        }

        return $key;
    }

    /** @return list<string> */
    public static function keysForRole(string $role): array
    {
        return match ($role) {
            PortalStaff::ROLE_PORTAL_ADMIN => self::allKeys(),
            PortalStaff::ROLE_COURSE_MODERATOR => array_values(array_diff(self::allKeys(), [self::STAFF_MANAGE])),
            PortalStaff::ROLE_COURSE_CREATOR => [
                self::COURSES_CREATE,
                self::COURSES_MANAGE_ASSIGNED,
                self::CONTENT_EDIT_ASSIGNED,
                self::LEARNERS_VIEW_ASSIGNED,
                self::DOCKER_MANAGE_OWN,
            ],
            PortalStaff::ROLE_COURSE_EDITOR => [
                self::COURSES_CREATE,
                self::COURSES_MANAGE_ASSIGNED,
                self::CONTENT_EDIT_ASSIGNED,
                self::LEARNERS_VIEW_ASSIGNED,
                self::DOCKER_MANAGE_OWN,
            ],
            PortalStaff::ROLE_INSTRUCTOR => [
                self::COURSES_MANAGE_ASSIGNED,
                self::LEARNERS_VIEW_ASSIGNED,
            ],
            PortalStaff::ROLE_COURSE_TESTER => [
                self::COURSES_MANAGE_ASSIGNED,
            ],
            default => [],
        };
    }
}
