<dialog class="quiz-modal" id="portal-login-dialog" aria-labelledby="portal-login-title">
    <div class="quiz-modal-inner" style="max-width:560px">
        <p class="quiz-modal-badge">Вход</p>
        <h2 id="portal-login-title" class="quiz-modal-heading">Вход по корпоративной почте</h2>
        <p class="muted" style="margin:0 0 0.75rem">Укажите адрес в домене <strong>{{ '@'.$domain }}</strong>. Пароль не требуется.</p>

        @if ($errors->any())
            <div class="flash err" style="margin:0 0 0.75rem">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('login.store') }}" class="quiz-modal-form">
            @csrf
            <div class="field" style="margin:0 0 0.85rem">
                <label for="portal-login-email">Электронная почта</label>
                <input id="portal-login-email" name="email" type="email" value="{{ old('email') }}" placeholder="example@croc.ru" required autocomplete="username">
            </div>
            <div class="quiz-modal-actions" style="justify-content:flex-start;flex-wrap:wrap;gap:0.5rem">
                <button type="submit" class="btn btn-primary">Продолжить</button>
                @if (config('oidc.enabled') && ! config('oidc.required'))
                    <a class="btn" href="{{ route('oidc.login') }}">Войти через SSO</a>
                @endif
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('portal-login-dialog').close()">Отмена</button>
            </div>
        </form>
        <p class="footer-note" style="margin-top:0.85rem">Данные учебного стенда. Не используйте пароли от рабочих систем.</p>
    </div>
</dialog>

