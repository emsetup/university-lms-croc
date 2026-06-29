<?php

namespace App\Support;

use App\Models\PortalStaff;

/**
 * Описание ролей сотрудника для UI «Сотрудники» (модалка добавления/редактирования).
 *
 * @phpstan-type AccessCell array{level: string, label: string}
 */
final class StaffRoleGuide
{
    /** @return list<array{key: string, title: string}> */
    public static function capabilityColumns(): array
    {
        return [
            ['key' => 'admin', 'title' => 'Админка /adm'],
            ['key' => 'courses', 'title' => 'Курсы'],
            ['key' => 'content', 'title' => 'Контент курса'],
            ['key' => 'learners', 'title' => 'Обучающиеся'],
            ['key' => 'people', 'title' => 'Люди (портал)'],
            ['key' => 'docker', 'title' => 'Docker'],
            ['key' => 'staff', 'title' => 'Сотрудники'],
            ['key' => 'settings', 'title' => 'Настройки'],
        ];
    }

    /**
     * @return array<string, array{
     *     label: string,
     *     badge: string,
     *     summary: string,
     *     admin_note: string,
     *     capabilities: array<string, AccessCell>
     * }>
     */
    public static function roles(): array
    {
        return [
            PortalStaff::ROLE_PORTAL_ADMIN => [
                'label' => 'Администратор портала',
                'badge' => 'admin',
                'summary' => 'Полное управление порталом: курсы, сотрудники, глобальные настройки и обучающиеся.',
                'admin_note' => 'Полный доступ к /adm',
                'capabilities' => self::cells(
                    admin: ['yes', 'Да'],
                    courses: ['yes', 'Все'],
                    content: ['yes', 'Все'],
                    learners: ['yes', 'Все курсы'],
                    people: ['yes', 'Да'],
                    docker: ['yes', 'Да'],
                    staff: ['yes', 'Да'],
                    settings: ['yes', 'Все'],
                ),
            ],
            PortalStaff::ROLE_COURSE_MODERATOR => [
                'label' => 'Модератор',
                'badge' => 'moderator',
                'summary' => 'Ведёт любые курсы: контент, тесты, Docker, статистика и раздел «Люди».',
                'admin_note' => 'Доступ к /adm без управления сотрудниками',
                'capabilities' => self::cells(
                    admin: ['yes', 'Да'],
                    courses: ['yes', 'Все'],
                    content: ['yes', 'Все'],
                    learners: ['yes', 'Все курсы'],
                    people: ['yes', 'Да'],
                    docker: ['yes', 'Да'],
                    staff: ['no', '—'],
                    settings: ['partial', 'Частично'],
                ),
            ],
            PortalStaff::ROLE_COURSE_CREATOR => [
                'label' => 'Создатель курсов',
                'badge' => 'creator',
                'summary' => 'Создаёт и редактирует только свои курсы. Чужие курсы не видит в каталоге.',
                'admin_note' => 'Доступ к /adm, только свои курсы',
                'capabilities' => self::cells(
                    admin: ['yes', 'Да'],
                    courses: ['own', 'Только свои'],
                    content: ['own', 'Только свои'],
                    learners: ['own', 'Свои курсы'],
                    people: ['no', '—'],
                    docker: ['own', 'Только свои'],
                    staff: ['no', '—'],
                    settings: ['no', '—'],
                ),
            ],
            PortalStaff::ROLE_COURSE_EDITOR => [
                'label' => 'Редактор курсов',
                'badge' => 'editor',
                'summary' => 'Редактирует назначенные курсы и создаёт свои. В списке «Курсы» отметьте, к каким курсам дать доступ.',
                'admin_note' => 'Доступ к /adm: назначенные + свои курсы',
                'capabilities' => self::cells(
                    admin: ['yes', 'Да'],
                    courses: ['assigned', 'Назнач. + свои'],
                    content: ['assigned', 'Назнач. + свои'],
                    learners: ['assigned', 'Назнач. + свои'],
                    people: ['no', '—'],
                    docker: ['partial', 'Свои + в курсах'],
                    staff: ['no', '—'],
                    settings: ['no', '—'],
                ),
            ],
            PortalStaff::ROLE_INSTRUCTOR => [
                'label' => 'Преподаватель курса',
                'badge' => 'instructor',
                'summary' => 'Просмотр прогресса обучающихся по назначенным курсам. Контент и Docker не редактирует.',
                'admin_note' => 'Урезанная /adm по назначенным курсам',
                'capabilities' => self::cells(
                    admin: ['partial', 'Ограничен'],
                    courses: ['assigned', 'Назначенные'],
                    content: ['no', '—'],
                    learners: ['assigned', 'Назначенные'],
                    people: ['no', '—'],
                    docker: ['no', '—'],
                    staff: ['no', '—'],
                    settings: ['no', '—'],
                ),
            ],
            PortalStaff::ROLE_COURSE_TESTER => [
                'label' => 'Тестировщик курса',
                'badge' => 'tester',
                'summary' => 'Проверка материалов до публикации: просмотр и правка тестов по назначенным курсам.',
                'admin_note' => 'Доступ к /adm, без публикации и Docker',
                'capabilities' => self::cells(
                    admin: ['partial', 'Ограничен'],
                    courses: ['assigned', 'Назначенные'],
                    content: ['partial', 'Просмотр + тесты'],
                    learners: ['no', '—'],
                    people: ['no', '—'],
                    docker: ['no', '—'],
                    staff: ['no', '—'],
                    settings: ['no', '—'],
                ),
            ],
            PortalStaff::ROLE_COURSE_CONTRIBUTOR => [
                'label' => 'Соавтор курса',
                'badge' => 'editor',
                'summary' => 'Совместная работа над курсом: только назначенные модули и разделы.',
                'admin_note' => 'Доступ к /adm по грантам, без публикации',
                'capabilities' => self::cells(
                    admin: ['partial', 'По грантам'],
                    courses: ['assigned', 'С грантами'],
                    content: ['partial', 'Назначенные разделы'],
                    learners: ['no', '—'],
                    people: ['no', '—'],
                    docker: ['no', '—'],
                    staff: ['no', '—'],
                    settings: ['no', '—'],
                ),
            ],
        ];
    }

    /** @return list<string> */
    public static function roleOrder(): array
    {
        return PortalStaff::ROLES;
    }

    /**
     * @return array<string, AccessCell>
     */
    private static function cells(
        array $admin,
        array $courses,
        array $content,
        array $learners,
        array $people,
        array $docker,
        array $staff,
        array $settings,
    ): array {
        return [
            'admin' => ['level' => $admin[0], 'label' => $admin[1]],
            'courses' => ['level' => $courses[0], 'label' => $courses[1]],
            'content' => ['level' => $content[0], 'label' => $content[1]],
            'learners' => ['level' => $learners[0], 'label' => $learners[1]],
            'people' => ['level' => $people[0], 'label' => $people[1]],
            'docker' => ['level' => $docker[0], 'label' => $docker[1]],
            'staff' => ['level' => $staff[0], 'label' => $staff[1]],
            'settings' => ['level' => $settings[0], 'label' => $settings[1]],
        ];
    }
}
