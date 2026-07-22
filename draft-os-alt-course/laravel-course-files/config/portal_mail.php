<?php

return [
    /** Выключить отправку (журнал при этом можно писать как skipped). */
    'enabled' => filter_var(env('PORTAL_MAIL_ENABLED', true), FILTER_VALIDATE_BOOL),

    /** Exchange Web Services (HTTPS). */
    'ews_url' => env('PORTAL_MAIL_EWS_URL', 'https://owa.croc.ru/EWS/Exchange.asmx'),

    'username' => env('PORTAL_MAIL_USERNAME', 'practice@croc.ru'),

    'password' => env('PORTAL_MAIL_PASSWORD', ''),

    'from_address' => env('PORTAL_MAIL_FROM_ADDRESS', env('PORTAL_MAIL_USERNAME', 'practice@croc.ru')),

    'from_name' => env('PORTAL_MAIL_FROM_NAME', 'Учебный портал practice.croc.ru'),

    /**
     * Проверка TLS-сертификата. На стенде часто корпоративный CA —
     * по умолчанию выключено (как при ручном EWS-пробе).
     */
    'verify_ssl' => filter_var(env('PORTAL_MAIL_VERIFY_SSL', false), FILTER_VALIDATE_BOOL),

    /** Таймаут HTTP к EWS, секунды. */
    'timeout' => max(5, (int) env('PORTAL_MAIL_TIMEOUT', 25)),
];
