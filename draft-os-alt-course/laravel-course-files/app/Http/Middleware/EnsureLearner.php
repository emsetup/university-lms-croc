<?php

namespace App\Http\Middleware;

use App\Models\Learner;
use App\Support\OidcSignInRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureLearner
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = session('learner_id');
        if (! $id) {
            if (config('oidc.enabled') && config('oidc.required')) {
                return OidcSignInRedirect::toOidcLogin($request);
            }

            return redirect()->route('portal', ['login' => 1]);
        }

        $learner = Learner::find($id);
        if (! $learner) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if (config('oidc.enabled') && config('oidc.required')) {
                return OidcSignInRedirect::toOidcLogin($request);
            }

            return redirect()->route('portal', ['login' => 1]);
        }

        View::share('currentLearner', $learner);

        return $next($request);
    }
}
