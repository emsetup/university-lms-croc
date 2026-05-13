<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Когда в ADFS зарегистрирован только один redirect_uri (например https://practice.croc.ru/oidc/callback),
 * вход по SSO нужно начинать на том же хосте, иначе state в сессии не совпадёт с callback.
 */
final class OidcSignInRedirect
{
    public static function toOidcLogin(Request $request): RedirectResponse
    {
        $url = self::oidcLoginUrl($request);
        if (self::isAbsoluteHttp($url)) {
            return redirect()->away($url);
        }

        return redirect()->to($url);
    }

    /**
     * URL страницы /oidc/login (абсолютный при переносе на канонический хост).
     */
    public static function oidcLoginUrl(?Request $request = null): string
    {
        $request ??= request();
        $origin = self::canonicalOrigin();
        if ($origin === null) {
            return '/oidc/login';
        }

        $current = self::requestOrigin($request);
        if (strcasecmp(rtrim($current, '/'), rtrim($origin, '/')) !== 0) {
            return rtrim($origin, '/').'/oidc/login';
        }

        return '/oidc/login';
    }

    public static function canonicalOrigin(): ?string
    {
        $fixed = trim((string) config('oidc.redirect_uri', ''));
        if ($fixed !== '') {
            $p = parse_url($fixed);
            if (! empty($p['scheme']) && ! empty($p['host'])) {
                $origin = $p['scheme'].'://'.$p['host'];
                if (! empty($p['port'])) {
                    $origin .= ':'.$p['port'];
                }

                return rtrim($origin, '/');
            }
        }

        $po = trim((string) config('oidc.public_origin', ''));
        if ($po !== '') {
            return rtrim($po, '/');
        }

        return null;
    }

    /** Абсолютный URL для ссылок «Войти через SSO» (в т.ч. с IP на practice.croc.ru). */
    public static function oidcLoginUrlAbsolute(?Request $request = null): string
    {
        $request ??= request();
        $u = self::oidcLoginUrl($request);
        if (self::isAbsoluteHttp($u)) {
            return $u;
        }

        return rtrim($request->getScheme().'://'.$request->getHttpHost(), '/').$u;
    }

    public static function portalHomeUrl(?Request $request = null): string
    {
        $o = self::canonicalOrigin();
        if ($o !== null) {
            return rtrim($o, '/').'/';
        }
        $request ??= request();

        return rtrim($request->getScheme().'://'.$request->getHttpHost(), '/').'/';
    }

    private static function requestOrigin(Request $request): string
    {
        return rtrim(strtolower($request->getScheme().'://'.$request->getHttpHost()), '/');
    }

    private static function isAbsoluteHttp(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }
}
