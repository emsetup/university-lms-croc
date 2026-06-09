@php
    $oidcOnly = (bool) config('oidc.enabled') && (bool) config('oidc.required');
    $ssoUrl = \App\Support\OidcSignInRedirect::oidcLoginUrlAbsolute().'?reauth=1';
@endphp
<dialog class="quiz-modal" id="portal-login-dialog" aria-labelledby="portal-login-title">
    <div class="quiz-modal-inner" style="max-width:560px">
        <p class="quiz-modal-badge">Вход</p>
        @if ($oidcOnly)
            <h2 id="portal-login-title" class="quiz-modal-heading">Вход через корпоративный аккаунт</h2>
            <p class="muted" style="margin:0 0 0.75rem;line-height:1.5">
                Для доступа к порталу и курсам необходимо авторизоваться через корпоративный SSO (OpenID).
            </p>
        @else
            <h2 id="portal-login-title" class="quiz-modal-heading">Вход по корпоративной почте</h2>
            <p class="muted" style="margin:0 0 0.75rem">Укажите адрес в домене <strong>{{ '@'.$domain }}</strong>. Пароль не требуется.</p>
        @endif

        @if ($errors->any())
            <div class="flash err" style="margin:0 0 0.75rem">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        @if ($oidcOnly)
            <div class="quiz-modal-actions" style="justify-content:flex-start;flex-wrap:wrap;gap:0.5rem">
                <a class="btn btn-primary" href="{{ $ssoUrl }}">Войти через SSO</a>
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('portal-login-dialog').close()">Отмена</button>
            </div>
        @else
            <form method="post" action="{{ route('login.store') }}" class="quiz-modal-form">
                @csrf
                <div class="field" style="margin:0 0 0.85rem">
                    <label for="portal-login-email">Электронная почта</label>
                    <input id="portal-login-email" name="email" type="email" value="{{ old('email') }}" placeholder="example@croc.ru" required autocomplete="username">
                </div>
                <div class="quiz-modal-actions" style="justify-content:flex-start;flex-wrap:wrap;gap:0.5rem">
                    <button type="submit" class="btn btn-primary">Продолжить</button>
                    @if (config('oidc.enabled'))
                        <a class="btn" href="{{ \App\Support\OidcSignInRedirect::oidcLoginUrlAbsolute() }}">Войти через SSO</a>
                    @endif
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('portal-login-dialog').close()">Отмена</button>
                </div>
            </form>
        @endif
        <p class="footer-note" style="margin-top:0.85rem">Данные учебного стенда. Не используйте пароли от рабочих систем.</p>
    </div>
</dialog>
