<?php

/**
 * Практикум в Docker (lab-daemon). Копируйте в config/practice_lab.php на стенде и задайте .env.
 *
 * Веб-терминал (ttyd): на стенде в .env задайте PRACTICE_LAB_PUBLIC_HOST — IP или DNS стенда,
 * который открывается в браузере студента (не 127.0.0.1). Скрипт start-lab-daemon-stand.sh
 * подставляет хост из STAND_SSH, если переменная не задана.
 *
 * Модуль 1: контейнер os-alt-lab-m1 — изучение дистрибутива изнутри (см. docker/lab-m1/README.md).
 * Модуль 3: os-alt-lab-m3-systemd — ЦУС/Alterator (systemd в контейнере; см. docker/lab-m3/README.md).
 * @see docker/lab-m1/README.md docker/lab-m3/README.md docker/lab-m5/README.md docker/lab-m6/README.md docker/lab-m7/README.md docker/lab-m8/README.md docker/lab-m9/README.md docker/lab-m8-systemd/README.md
 *
 * Редактор теории (Markdown) в веб-интерфейсе: задайте COURSE_ADMIN_TOKEN в .env, скопируйте config/course_admin.php,
 * затем откройте /adm?key=ВАШ_ТОКЕН или /adm/kurs-teoriya?key=… (см. scripts/deploy-laravel-stand.sh).
 */
return [
    'enabled' => (bool) env('PRACTICE_LAB_ENABLED', false),
    'daemon_url' => env('PRACTICE_LAB_DAEMON_URL', 'http://127.0.0.1:8090'),
    'daemon_secret' => env('PRACTICE_LAB_DAEMON_SECRET', ''),
    'session_ttl_minutes' => (int) env('PRACTICE_LAB_SESSION_TTL', 480),
    'min_accept_score' => (int) env('PRACTICE_LAB_MIN_ACCEPT', 50),

    'images' => [
        '1' => env('PRACTICE_LAB_IMAGE_1', 'os-alt-lab-m1:latest'),
        '2' => env('PRACTICE_LAB_IMAGE_2', 'os-alt-lab-m2:latest'),
        '3' => env('PRACTICE_LAB_IMAGE_3', 'os-alt-lab-m3-systemd:latest'),
        '5' => env('PRACTICE_LAB_IMAGE_5', 'os-alt-lab-m5-systemd:latest'),
        '6' => env('PRACTICE_LAB_IMAGE_6', 'os-alt-lab-m6:latest'),
        '7' => env('PRACTICE_LAB_IMAGE_7', 'os-alt-lab-m7:latest'),
        '8' => env('PRACTICE_LAB_IMAGE_M8', 'os-alt-lab-m8:latest'),
        '9' => env('PRACTICE_LAB_IMAGE_9', 'os-alt-lab-m9:latest'),
    ],
];
