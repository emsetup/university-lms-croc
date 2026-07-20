<?php

return [
    /** Максимум соавторов с правом edit/manage на один курс (без владельца). */
    'course_collaborator_limit' => (int) env('PORTAL_COURSE_COLLABORATOR_LIMIT', 5),

    /**
     * Часовой пояс для отображения дат в админке (лента активности и т.п.).
     * Хранение в БД остаётся в app.timezone (обычно UTC).
     */
    'display_timezone' => env('PORTAL_DISPLAY_TIMEZONE', 'Europe/Moscow'),
];
