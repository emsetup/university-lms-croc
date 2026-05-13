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
];
