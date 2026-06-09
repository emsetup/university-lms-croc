<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/** Сохраняет URL быстрой ссылки на опрос до входа и возвращает после логина. */
final class LoginReturnUrl
{
    public const SESSION_KEY = 'login_return_url';

    public static function rememberIfSurveyQuick(Request $request): void
    {
        if (! $request->is('opros/*')) {
            return;
        }

        $path = '/'.ltrim($request->path(), '/');
        if (! preg_match('#^/opros/[A-Za-z0-9_-]+$#', $path)) {
            return;
        }

        session([self::SESSION_KEY => $request->fullUrl()]);
    }

    public static function pull(): ?string
    {
        $url = session()->pull(self::SESSION_KEY);
        if (! is_string($url) || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! preg_match('#^/opros/[A-Za-z0-9_-]+$#', $path)) {
            return null;
        }

        return $url;
    }
}
