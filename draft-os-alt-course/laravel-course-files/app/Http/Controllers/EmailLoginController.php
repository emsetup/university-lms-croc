<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Support\LearnerPortalLoginPersistence;
use App\Support\LoginReturnUrl;
use App\Support\OidcSignInRedirect;
use App\Support\StaffAdminPreview;
use App\Support\StaffImpersonation;
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

        if ($return = LoginReturnUrl::pull()) {
            return redirect()->to($return);
        }

        return redirect()->route('portal');
    }

    public function logout(Request $request): RedirectResponse
    {
        StaffImpersonation::clearSession();
        StaffAdminPreview::clearSession();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('portal', ['login' => 1])
            ->with('ok', 'Вы вышли из учётной записи. Для доступа к курсам войдите снова.');
    }
}
