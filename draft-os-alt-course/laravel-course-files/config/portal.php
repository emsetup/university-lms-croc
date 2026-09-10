<?php

return [
    /** Максимум соавторов с правом edit/manage на один курс (без владельца). */
    'course_collaborator_limit' => (int) env('PORTAL_COURSE_COLLABORATOR_LIMIT', 5),

    /**
     * Часовой пояс для отображения дат в админке (лента активности и т.п.).
     * Хранение в БД остаётся в app.timezone (обычно UTC).
     */
    'display_timezone' => env('PORTAL_DISPLAY_TIMEZONE', 'Europe/Moscow'),

    /** Куда слать письмо о новом баге / предложении (плюс автор курса при scope=course). */
    'bug_notify_email' => env('PORTAL_BUG_NOTIFY_EMAIL', 'emednikov@croc.ru'),

    /**
     * Глобальный inbox /adm/bagi (список через запятую).
     * Авторы курсов видят /adm/bagi сами, но только тикеты своих курсов.
     */
    'bug_inbox_emails' => env('PORTAL_BUG_INBOX_EMAILS', 'emednikov@croc.ru'),
];
