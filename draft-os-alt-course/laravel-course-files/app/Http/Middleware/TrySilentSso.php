<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\OidcSignInRedirect;
use App\Support\SilentSsoProbe;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Один тихий заход на ADFS (prompt=none) для гостя, чтобы не жать «Войти через SSO»
 * при живой корпоративной сессии. Публичный каталог курсов при этом остаётся доступен:
 * если у IdP сессии нет, пользователь возвращается на ту же страницу без входа.
 */
class TrySilentSso
{
    /** Служебные пути: тихий вход на них не нужен и мешал бы. */
    private const SKIP_PATHS = [
        'oidc/*',
        'login',
        'logout',
        'up',
        'portal/incident',
        'portal/bug-report',
        'build/*',
        'storage/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldProbe($request)) {
            return $next($request);
        }

        SilentSsoProbe::rememberReturn($request);

        $url = OidcSignInRedirect::oidcLoginUrl($request);
        $url .= (str_contains($url, '?') ? '&' : '?').'silent=1';

        // Cookie ставим до ухода на IdP: даже если callback не вернётся, цикла не будет.
        return redirect()->to($url)->withCookie(SilentSsoProbe::cookie());
    }

    private function shouldProbe(Request $request): bool
    {
        if (! SilentSsoProbe::enabled()) {
            return false;
        }
        if ($request->method() !== 'GET') {
            return false;
        }
        if (session('learner_id')) {
            return false;
        }
        if ((int) $request->attributes->get('preview_learner_id', 0) > 0) {
            return false;
        }
        if (SilentSsoProbe::attempted($request)) {
            return false;
        }
        if ($request->ajax() || $request->pjax() || $request->wantsJson() || $request->hasHeader('X-Requested-With')) {
            return false;
        }
        if (! $request->acceptsHtml()) {
            return false;
        }
        if ($request->is(...self::SKIP_PATHS)) {
            return false;
        }
        if ($this->hasSsoError($request)) {
            return false;
        }

        return $this->onCanonicalOrigin($request);
    }

    /** После ошибки SSO повторять тихую попытку нельзя — пользователь не увидит причину. */
    private function hasSsoError(Request $request): bool
    {
        $errors = $request->session()->get('errors');

        return $errors instanceof ViewErrorBag && $errors->has('oidc');
    }

    /**
     * redirect_uri в ADFS зарегистрирован на одном хосте: с IP тихий вход увёл бы
     * пользователя на practice.croc.ru без его ведома, поэтому пробуем только на нём.
     */
    private function onCanonicalOrigin(Request $request): bool
    {
        $origin = OidcSignInRedirect::canonicalOrigin();
        if ($origin === null) {
            return true;
        }

        $current = $request->getScheme().'://'.$request->getHttpHost();

        return strcasecmp(rtrim($current, '/'), rtrim($origin, '/')) === 0;
    }
}
