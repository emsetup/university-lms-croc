<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Тихая проверка сессии SSO (OIDC prompt=none).
 *
 * Гость один раз уводится на ADFS с prompt=none: есть живая сессия у IdP —
 * возвращаемся уже авторизованными, нет — IdP отдаёт login_required и портал
 * молча рисует обычную страницу. Cookie-отметка не даёт зациклить редиректы,
 * поэтому её ставим до ухода на IdP и снимаем только после удачного входа.
 */
final class SilentSsoProbe
{
    public const COOKIE = 'portal_sso_probe';

    /** Режим текущего запроса к IdP: probe | button | forms | reauth (см. OidcLoginController). */
    public const SESSION_FLAG = 'oidc_silent_probe';

    /** Куда вернуть пользователя после тихой попытки. */
    public const SESSION_RETURN = 'oidc_silent_return';

    /** Query-параметры входа, которые не нужны на странице возврата. */
    private const STRIP_QUERY = ['login', 'silent', 'forms', 'reauth', 'login_hint'];

    public static function enabled(): bool
    {
        return (bool) config('oidc.enabled', false) && (bool) config('oidc.silent_login', false);
    }

    public static function cookieMinutes(): int
    {
        $minutes = (int) config('oidc.silent_probe_minutes', 720);

        return $minutes > 0 ? $minutes : 720;
    }

    public static function attempted(Request $request): bool
    {
        return (string) $request->cookie(self::COOKIE, '') !== '';
    }

    /** Отметка «тихую попытку уже делали» — защита от цикла редиректов. */
    public static function cookie(): Cookie
    {
        return cookie(self::COOKIE, '1', self::cookieMinutes(), '/', null, null, true, false, 'lax');
    }

    /**
     * Отметка после выхода: короткая, только чтобы редирект не втянул обратно.
     * Через несколько минут пользователь снова сможет войти тихо.
     */
    public static function logoutCookie(): Cookie
    {
        $minutes = (int) config('oidc.silent_logout_minutes', 5);

        return cookie(self::COOKIE, '1', $minutes > 0 ? $minutes : 5, '/', null, null, true, false, 'lax');
    }

    /** После удачного входа отметку снимаем: следующая сессия снова сможет подняться тихо. */
    public static function forgetCookie(): Cookie
    {
        return cookie()->forget(self::COOKIE);
    }

    public static function rememberReturn(Request $request): void
    {
        session([self::SESSION_RETURN => self::returnPathFor($request)]);
    }

    public static function pullReturn(): ?string
    {
        return self::sanitizeReturn(session()->pull(self::SESSION_RETURN));
    }

    /** Возврат разрешён только внутрь портала: чужие хосты и protocol-relative URL отбрасываем. */
    public static function sanitizeReturn(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }
        if (! str_starts_with($url, '/')) {
            return null;
        }
        // "//host" и "/\host" браузер трактует как абсолютный адрес чужого сайта.
        if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return null;
        }

        return $url;
    }

    /** Текущий адрес без параметров входа — на него возвращаемся после тихой попытки. */
    public static function returnPathFor(Request $request): string
    {
        $path = '/'.ltrim($request->path(), '/');
        if ($path === '/.') {
            $path = '/';
        }

        $query = $request->query();
        foreach (self::STRIP_QUERY as $key) {
            unset($query[$key]);
        }

        if ($query === []) {
            return $path;
        }

        return $path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
