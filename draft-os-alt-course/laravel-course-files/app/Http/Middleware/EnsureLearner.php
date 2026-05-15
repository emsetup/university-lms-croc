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
        $previewId = (int) $request->attributes->get('preview_learner_id', 0);
        $id = $previewId > 0 ? $previewId : session('learner_id');

        if (! $id) {
            if (config('oidc.enabled') && config('oidc.required')) {
                return OidcSignInRedirect::toOidcLogin($request);
            }

            return redirect()->route('portal', ['login' => 1]);
        }

        $learner = Learner::find($id);
        if (! $learner) {
            if ($previewId > 0) {
                return redirect()
                    ->route('portal')
                    ->with('err', 'Обучающийся не найден.');
            }

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if (config('oidc.enabled') && config('oidc.required')) {
                return OidcSignInRedirect::toOidcLogin($request);
            }

            return redirect()->route('portal', ['login' => 1]);
        }

        View::share('currentLearner', $learner);
        if ($previewId > 0) {
            View::share('learnerPreviewTarget', $learner);
        }

        return $next($request);
    }
}
