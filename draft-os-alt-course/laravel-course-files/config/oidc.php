<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenID Connect (ADFS) — опциональный вход
    |--------------------------------------------------------------------------
    |
    | Секрет клиента задаётся только в .env на сервере, не в репозитории.
    | Redirect URI в ADFS: https://practice.croc.ru/oidc/callback и при необходимости
    | http://172.26.76.216/oidc/callback
    |
    */

    'enabled' => filter_var(env('OIDC_ENABLED', false), FILTER_VALIDATE_BOOL),

    /*
     * Если true: без сессии обучающегося портал и защищённые страницы сразу ведут на ADFS,
     * вход по почте отключён (кроме показа ошибок SSO на /login).
     */
    'required' => filter_var(env('OIDC_REQUIRED', false), FILTER_VALIDATE_BOOL),

    /*
     * Тихая проверка сессии IdP (prompt=none) для гостя: при живой корпоративной сессии
     * портал открывается сразу под учётной записью, без кнопки «Войти через SSO».
     * Публичный каталог курсов сохраняется: если сессии у IdP нет, страница рисуется как обычно.
     */
    'silent_login' => filter_var(env('OIDC_SILENT_LOGIN', false), FILTER_VALIDATE_BOOL),

    /*
     * На сколько минут запоминается неудачная тихая попытка (cookie-отметка против цикла
     * редиректов). После удачного входа отметка снимается.
     */
    'silent_probe_minutes' => (int) env('OIDC_SILENT_PROBE_MINUTES', 720),

    /*
     * На сколько минут тихий вход отключается после явного выхода из портала.
     * Нужно только чтобы редирект не вернул пользователя обратно сразу же.
     */
    'silent_logout_minutes' => (int) env('OIDC_SILENT_LOGOUT_MINUTES', 5),

    /*
     * Вход под другой учётной записью (prompt=login). По умолчанию запрещён: портал
     * пускает только под текущей доменной УЗ, и ?reauth=1 в адресе ничего не меняет.
     */
    'allow_reauth' => filter_var(env('OIDC_ALLOW_REAUTH', false), FILTER_VALIDATE_BOOL),

    /*
     * Что делает кнопка «Войти через SSO»:
     *  forms  (по умолчанию) — сразу фирменная веб-форма ADFS (prompt=login);
     *  silent — сначала тихая попытка prompt=none, форма только при login_required.
     * Режим silent на ADFS Croc уводит интранет-браузеры на WIA-эндпоинт (системный
     * попап Sign in без Kerberos-тикета), поэтому включать только после починки WIA.
     */
    'button_mode' => env('OIDC_BUTTON_MODE', 'forms'),

    'discovery_url' => env(
        'OIDC_DISCOVERY_URL',
        'https://fs.croc.ru/adfs/.well-known/openid-configuration'
    ),

    'issuer' => env('OIDC_ISSUER', 'https://fs.croc.ru/adfs'),

    'client_id' => env('OIDC_CLIENT_ID', ''),

    'client_secret' => env('OIDC_CLIENT_SECRET', ''),

    'scope' => env('OIDC_SCOPE', 'openid profile email'),

    'redirect_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OIDC_REDIRECT_HOSTS', '172.26.76.216,practice.croc.ru'))
    ))),

    /*
     * Полный redirect_uri, как в ADFS (например https://practice.croc.ru/oidc/callback).
     * Если задан — используется всегда; заход с IP перенаправляется на канонический хост (см. OidcSignInRedirect).
     */
    'redirect_uri' => env('OIDC_REDIRECT_URI', ''),

    /*
     * Канонический origin портала для SSO (https://practice.croc.ru), если не выводится из OIDC_REDIRECT_URI.
     */
    'public_origin' => rtrim((string) env('OIDC_PUBLIC_ORIGIN', ''), '/'),
];
