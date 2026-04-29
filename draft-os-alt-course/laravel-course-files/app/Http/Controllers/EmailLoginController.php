<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class EmailLoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (session('learner_id')) {
            return redirect()->route('dashboard');
        }

        return view('auth.email', [
            'domain' => config('course.email_domain'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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
        session(['learner_id' => $learner->id]);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        session()->forget('learner_id');

        return redirect()->route('login');
    }
}
