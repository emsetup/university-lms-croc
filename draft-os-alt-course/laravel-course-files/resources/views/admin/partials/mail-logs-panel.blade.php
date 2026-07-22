@php
    $feedUrl = $mailFeedUrl ?? route('admin.mail.feed');
    $typeLabels = $typeLabels ?? \App\Services\Mail\PortalMailFeedService::TYPE_LABELS;
    $statusLabels = $statusLabels ?? \App\Services\Mail\PortalMailFeedService::STATUS_LABELS;
    $emailSuggestions = $emailSuggestions ?? [];
@endphp

<div class="ap-logs-layout ap-mail-panel"
     data-ap-mail-panel
     data-ap-mail-feed-url="{{ $feedUrl }}"
     data-ap-mail-detail-base="{{ rtrim(route('admin.mail.index'), '/') }}"
     data-ap-mail-resend-base="{{ rtrim(route('admin.mail.index'), '/') }}">
    <aside class="ap-logs-filters">
        <div class="ap-logs-filters__head">
            <h2 class="ap-logs-filters__title">Фильтры</h2>
            <button type="button" class="ap-logs-filters__reset" data-ap-mail-reset>Сброс</button>
        </div>

        <form class="ap-logs-filters__form" data-ap-mail-filters autocomplete="off">
            <fieldset class="ap-logs-field">
                <legend class="ap-logs-field__label">Период</legend>
                <div class="ap-logs-pills" role="group" aria-label="Быстрый период" data-ap-mail-period-group>
                    <button type="button" class="ap-logs-pill" data-ap-mail-period="today">Сегодня</button>
                    <button type="button" class="ap-logs-pill" data-ap-mail-period="7d">7 дн.</button>
                    <button type="button" class="ap-logs-pill" data-ap-mail-period="30d">30 дн.</button>
                    <button type="button" class="ap-logs-pill" data-ap-mail-period="all">Всё</button>
                </div>
                <div class="ap-logs-dates">
                    <input type="date" name="date_from" class="ap-logs-input ap-logs-input--date" aria-label="С даты">
                    <input type="date" name="date_to" class="ap-logs-input ap-logs-input--date" aria-label="По дату">
                </div>
            </fieldset>

            <fieldset class="ap-logs-field">
                <legend class="ap-logs-field__label">Получатель</legend>
                <div class="ap-logs-search">
                    @include('partials.ap-icon', ['name' => 'search', 'size' => 'sm'])
                    <input type="search" name="user" list="ap-mail-emails" class="ap-logs-input" placeholder="email…">
                </div>
            </fieldset>

            <fieldset class="ap-logs-field">
                <legend class="ap-logs-field__label">Тип</legend>
                <div class="ap-logs-sources">
                    @foreach ($typeLabels as $typeKey => $typeLabel)
                        <label class="ap-logs-source">
                            <input type="checkbox" name="types[]" value="{{ $typeKey }}" checked>
                            <span>{{ $typeLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="ap-logs-field">
                <legend class="ap-logs-field__label">Статус</legend>
                <select name="status" class="ap-logs-input ap-logs-input--select">
                    <option value="">Любой</option>
                    @foreach ($statusLabels as $sk => $sl)
                        <option value="{{ $sk }}">{{ $sl }}</option>
                    @endforeach
                </select>
            </fieldset>

            <datalist id="ap-mail-emails">
                @foreach ($emailSuggestions as $email)
                    <option value="{{ $email }}"></option>
                @endforeach
            </datalist>
        </form>
    </aside>

    <section class="ap-logs-feed">
        <div class="ap-logs-feed__bar">
            <span class="ap-logs-feed__status" data-ap-mail-status aria-live="polite"></span>
        </div>

        <div class="ap-logs-feed__viewport" data-ap-mail-viewport data-state="loading">
            <div class="ap-logs-state ap-logs-state--loading" data-ap-mail-loading>
                <span class="ap-logs-spinner" aria-hidden="true"></span>
                <span>Загрузка журнала…</span>
            </div>
            <div class="ap-logs-state ap-logs-state--empty" data-ap-mail-empty hidden>
                <p class="ap-logs-empty__title">Писем пока нет</p>
                <p class="ap-logs-empty__text">Когда администратор или редактор выдаст доступ или отправит приглашение — запись появится здесь.</p>
            </div>
            <div class="ap-logs-list" data-ap-mail-mount role="list" hidden></div>
        </div>

        <div class="ap-logs-feed__footer" data-ap-mail-footer hidden>
            <button type="button" class="ap-logs-more" data-ap-mail-more>Ещё</button>
        </div>
    </section>
</div>

<div class="ap-modal ap-mail-detail-modal" id="ap-mail-detail-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-mail-detail-title" hidden>
    <div class="ap-modal__backdrop" data-ap-mail-detail-close tabindex="-1"></div>
    <div class="ap-modal__panel ap-modal__panel--wide">
        <button type="button" class="ap-modal__close" data-ap-mail-detail-close aria-label="Закрыть">&times;</button>
        <h2 id="ap-mail-detail-title" class="ap-modal__title">Письмо</h2>
        <div data-ap-mail-detail-body>
            <p class="ap-muted">Загрузка…</p>
        </div>
        <div class="ap-modal__actions" style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
            <button type="button" class="btn btn-ghost" data-ap-mail-detail-close>Закрыть</button>
            <button type="button" class="btn btn-primary" data-ap-mail-resend>Отправить снова</button>
        </div>
    </div>
</div>
