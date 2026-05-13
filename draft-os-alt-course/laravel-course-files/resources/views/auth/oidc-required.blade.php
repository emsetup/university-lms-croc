@extends('layouts.course')

@section('title', 'Вход через корпоративный аккаунт')

@section('content')
    <div class="card" style="max-width:520px;margin:2rem auto 0">
        <h1 style="margin-top:0">Вход через SSO</h1>
        <p class="muted">Для портала включена только корпоративная аутентификация (OpenID). Вход по почте на этой странице отключён.</p>

        @if ($errors->any())
            <div class="flash err">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        <div style="margin-top:1rem;display:flex;gap:0.5rem;flex-wrap:wrap">
            <a class="btn btn-primary" href="{{ route('oidc.login') }}">Повторить вход через SSO</a>
            <a class="btn btn-ghost" href="{{ route('portal') }}">На главную</a>
        </div>
    </div>
@endsection
