<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Support\LearnerPortalLoginPersistence;
use App\Support\OidcSignInRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class EmailLoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (session('learner_id')) {
            return redirect()->route('portal');
        }

        $oidcGate = (bool) config('oidc.enabled') && (bool) config('oidc.required');
        if ($oidcGate) {
            $bag = $request->session()->get('errors');
            if ($bag instanceof \Illuminate\Support\ViewErrorBag && $bag->isNotEmpty()) {
                return view('auth.oidc-required');
            }

            return OidcSignInRedirect::toOidcLogin($request);
        }

        return view('auth.email', [
            'domain' => config('course.email_domain'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (config('oidc.enabled') && config('oidc.required')) {
            return OidcSignInRedirect::toOidcLogin($request);
        }

        $domain = preg_quote(config('course.email_domain'), '/');
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email:rfc',
                'regex:/^[^@\s]+@'.$domain.'$/i',
            ],
        ], [
            'email.regex' => 'Укажите корпоративную почту в домене @'.config('course.email_domain'),
        ]);

        if ($validator->fails()) {
            return redirect()->route('login')->withErrors($validator)->withInput();
        }

        $email = strtolower((string) $request->input('email'));
        $learner = Learner::firstOrCreate(['email' => $email]);
        $currentLearnerId = (int) session('learner_id', 0);
        if ($currentLearnerId > 0 && $currentLearnerId !== (int) $learner->id) {
            // В одном браузере могла остаться сессия прошлого обучающегося (активные дедлайны и т.п.).
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        session(['learner_id' => $learner->id]);
        LearnerPortalLoginPersistence::recordLogin($learner);
        LearnerPortalLoginPersistence::markSessionRecorded($request);

        return redirect()->route('portal');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal');
    }
}
