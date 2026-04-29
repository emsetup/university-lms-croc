<?php

/**
 * Доступ к веб-редактору теории: GET/POST с ?key=... равным COURSE_ADMIN_TOKEN в .env.
 * Если токен пустой — маршруты отдают 404.
 */
return [
    'token' => env('COURSE_ADMIN_TOKEN', ''),
    'content_moderator_token' => env('COURSE_CONTENT_MODERATOR_TOKEN', ''),
];
