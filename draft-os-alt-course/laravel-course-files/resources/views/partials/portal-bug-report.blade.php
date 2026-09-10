@php
    $bugAuthed = (bool) session('learner_id');
    $bugStoreUrl = $bugAuthed ? route('portal.bug-report.store') : '';
    $bugLoginUrl = route('portal', ['login' => 1]);
    $bugCsrf = csrf_token();
    $bugCourses = \App\Services\PortalBugReportService::courseOptions();
    $bugDefaultCourseId = 0;
    try {
        $bugDefaultCourseId = (int) (\App\Support\LearnerPreviewContext::courseId() ?: session('admin_course_id') ?: session('course_id') ?: 0);
    } catch (\Throwable) {
        $bugDefaultCourseId = (int) (session('admin_course_id') ?: session('course_id') ?: 0);
    }
@endphp
<link rel="stylesheet" href="{{ asset('css/portal-bug-report.css') }}?v={{ @filemtime(public_path('css/portal-bug-report.css')) ?: 1 }}">

<button type="button"
        class="pbr-fab"
        id="portal-bug-fab"
        aria-haspopup="dialog"
        aria-controls="portal-bug-dialog"
        title="Сообщить об ошибке">
    <span class="pbr-fab__pulse" aria-hidden="true"></span>
    <span class="pbr-fab__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2a4 4 0 0 1 4 4v1h1a3 3 0 0 1 3 3v1"/>
            <path d="M8 7V6a4 4 0 0 1 4-4"/>
            <path d="M4 11v1a3 3 0 0 0 3 3h1"/>
            <rect x="8" y="7" width="8" height="12" rx="3"/>
            <path d="M8 12h8M12 7v12"/>
            <path d="m5 8 2 2M19 8l-2 2M5 16l2-2M19 16l-2-2"/>
        </svg>
    </span>
    <span class="pbr-fab__label">Сообщить об ошибке</span>
</button>

<dialog class="pbr-dialog" id="portal-bug-dialog" aria-labelledby="portal-bug-title">
    <div class="pbr-dialog__panel"
         data-pbr-root
         data-authenticated="{{ $bugAuthed ? '1' : '0' }}"
         data-store-url="{{ $bugStoreUrl }}"
         data-login-url="{{ $bugLoginUrl }}"
         data-csrf="{{ $bugCsrf }}"
         data-default-course-id="{{ $bugDefaultCourseId }}">
        <header class="pbr-dialog__head">
            <div>
                <p class="pbr-dialog__eyebrow">Обратная связь</p>
                <h2 id="portal-bug-title" class="pbr-dialog__title">Сообщить об ошибке</h2>
                <p class="pbr-dialog__lead">Опишите проблему или идею — мы увидим страницу и скриншоты. После отправки придёт письмо с номером тикета.</p>
            </div>
            <button type="button" class="pbr-dialog__close" data-pbr-close aria-label="Закрыть">&times;</button>
        </header>

        <div class="pbr-dialog__guest" data-pbr-guest hidden>
            <div class="pbr-guest-card">
                <p class="pbr-guest-card__title">Нужна авторизация</p>
                <p class="pbr-guest-card__text">Отправлять сообщения могут только вошедшие пользователи. Войдите корпоративной почтой — и форма откроется снова.</p>
                <div class="pbr-guest-card__actions">
                    <a class="btn btn-primary" data-pbr-login href="{{ $bugLoginUrl }}">Войти</a>
                    <button type="button" class="btn btn-ghost" data-pbr-close>Отмена</button>
                </div>
            </div>
        </div>

        <form class="pbr-form" data-pbr-form @if (! $bugAuthed) hidden @endif>
            <div class="pbr-field">
                <span class="pbr-field__label">К чему относится</span>
                <div class="pbr-type-row" role="radiogroup" aria-label="Область">
                    <label class="pbr-type is-active">
                        <input type="radio" name="scope" value="portal" checked data-pbr-scope>
                        <span>Работа портала</span>
                    </label>
                    <label class="pbr-type">
                        <input type="radio" name="scope" value="course" data-pbr-scope>
                        <span>Ошибка в курсе</span>
                    </label>
                </div>
            </div>

            <label class="pbr-field" data-pbr-course-wrap hidden>
                <span class="pbr-field__label">Курс</span>
                <select name="course_id" data-pbr-course class="pbr-select">
                    <option value="">Выберите курс…</option>
                    @foreach ($bugCourses as $c)
                        <option value="{{ $c['id'] }}" @selected((int) $c['id'] === $bugDefaultCourseId)>{{ $c['title'] }}</option>
                    @endforeach
                </select>
            </label>

            <div class="pbr-type-row" role="radiogroup" aria-label="Тип сообщения">
                <label class="pbr-type is-active">
                    <input type="radio" name="type" value="bug" checked>
                    <span>Ошибка</span>
                </label>
                <label class="pbr-type">
                    <input type="radio" name="type" value="suggestion">
                    <span>Предложение</span>
                </label>
                <label class="pbr-type">
                    <input type="radio" name="type" value="other">
                    <span>Другое</span>
                </label>
            </div>

            <label class="pbr-field">
                <span class="pbr-field__label">Кратко <em>необязательно</em></span>
                <input type="text" name="title" maxlength="255" placeholder="Например: не открывается практика модуля 3" autocomplete="off">
            </label>

            <label class="pbr-field">
                <span class="pbr-field__label">Описание</span>
                <textarea name="message" rows="5" maxlength="8000" required placeholder="Что произошло? Что ожидали увидеть? Шаги для воспроизведения…"></textarea>
            </label>

            <label class="pbr-field">
                <span class="pbr-field__label">Страница</span>
                <input type="url" name="page_url" data-pbr-page-url maxlength="1000" readonly>
                <input type="hidden" name="page_title" data-pbr-page-title value="">
            </label>

            <div class="pbr-field">
                <span class="pbr-field__label">Скриншоты <em>до 5 · Ctrl+V / перетащите</em></span>
                <div class="pbr-drop" data-pbr-drop tabindex="0">
                    <input type="file" data-pbr-file accept="image/png,image/jpeg,image/webp,image/gif" multiple hidden>
                    <p class="pbr-drop__hint">Вставьте из буфера, перетащите файлы или <button type="button" class="pbr-drop__pick" data-pbr-pick>выберите</button></p>
                    <div class="pbr-thumbs" data-pbr-thumbs></div>
                </div>
            </div>

            <p class="pbr-form__error" data-pbr-error hidden></p>
            <p class="pbr-form__ok" data-pbr-ok hidden></p>

            <div class="pbr-form__actions">
                <button type="button" class="btn btn-ghost" data-pbr-close>Отмена</button>
                <button type="submit" class="btn btn-primary" data-pbr-submit>Отправить</button>
            </div>
        </form>
    </div>
</dialog>

<script src="{{ asset('js/portal-bug-report.js') }}?v={{ @filemtime(public_path('js/portal-bug-report.js')) ?: 1 }}" defer></script>
