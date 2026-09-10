@php
    $feedUrl = $bugFeedUrl ?? route('admin.bugs.feed');
    $typeLabels = $typeLabels ?? \App\Services\PortalBugReportFeedService::TYPE_LABELS;
    $scopeLabels = $scopeLabels ?? \App\Services\PortalBugReportFeedService::SCOPE_LABELS;
    $statusLabels = $statusLabels ?? \App\Services\PortalBugReportFeedService::STATUS_LABELS;
    $emailSuggestions = $emailSuggestions ?? [];
@endphp

<div class="ap-logs-layout ap-bug-panel"
     data-ap-bug-panel
     data-ap-bug-feed-url="{{ $feedUrl }}"
     data-ap-bug-detail-url="{{ rtrim(route('admin.bugs.index'), '/') }}"
     data-ap-bug-csrf="{{ csrf_token() }}">
    <aside class="ap-logs-filters">
        <div class="ap-logs-filters__head">
            <h2 class="ap-logs-filters__title">Фильтры</h2>
            <button type="button" class="ap-logs-filters__reset" data-ap-bug-reset>Сброс</button>
        </div>

        <form class="ap-logs-filters__form" data-ap-bug-filters autocomplete="off">
            <fieldset class="ap-logs-field">
                <legend class="ap-logs-field__label">Период</legend>
                <div class="ap-logs-pills" role="group" aria-label="Быстрый период" data-ap-period-group>
                    <button type="button" class="ap-logs-pill" data-ap-period="today">Сегодня</button>
                    <button type="button" class="ap-logs-pill" data-ap-period="7d">7 дн.</button>
                    <button type="button" class="ap-logs-pill" data-ap-period="30d">30 дн.</button>
                    <button type="button" class="ap-logs-pill" data-ap-period="all">Всё</button>
                </div>
                <div class="ap-logs-dates">
                    <input type="date" name="date_from" class="ap-logs-input ap-logs-input--date" aria-label="С даты">
                    <span class="ap-logs-dates__sep" aria-hidden="true">—</span>
                    <input type="date" name="date_to" class="ap-logs-input ap-logs-input--date" aria-label="По дату">
                </div>
            </fieldset>

            <fieldset class="ap-logs-field">
                <legend class="ap-logs-field__label">Пользователь</legend>
                <div class="ap-logs-search">
                    @include('partials.ap-icon', ['name' => 'search', 'size' => 'sm'])
                    <input type="search" name="user" list="ap-bug-emails" class="ap-logs-input" placeholder="email…">
                </div>
            </fieldset>

            <fieldset class="ap-logs-field">
                <legend class="ap-logs-field__label">Поиск</legend>
                <input type="search" name="q" class="ap-logs-input" placeholder="текст сообщения…">
            </fieldset>

            <fieldset class="ap-logs-field">
                <legend class="ap-logs-field__label">Область</legend>
                <div class="ap-logs-sources">
                    @foreach ($scopeLabels as $scopeKey => $scopeLabel)
                        <label class="ap-logs-source">
                            <input type="checkbox" name="scopes[]" value="{{ $scopeKey }}" checked>
                            <span>{{ $scopeLabel }}</span>
                        </label>
                    @endforeach
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
                <div class="ap-logs-sources">
                    @foreach ($statusLabels as $statusKey => $statusLabel)
                        <label class="ap-logs-source">
                            <input type="checkbox" name="statuses[]" value="{{ $statusKey }}"
                                   @if (in_array($statusKey, ['new', 'in_progress'], true)) checked @endif>
                            <span>{{ $statusLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <datalist id="ap-bug-emails">
                @foreach ($emailSuggestions as $email)
                    <option value="{{ $email }}"></option>
                @endforeach
            </datalist>
        </form>
    </aside>

    <section class="ap-logs-feed">
        <div class="ap-logs-feed__bar">
            <span class="ap-logs-feed__status" data-ap-bug-status aria-live="polite"></span>
            <label class="ap-logs-live">
                <input type="checkbox" data-ap-bug-live checked>
                <span class="ap-logs-live__dot" aria-hidden="true"></span>
                <span>Live</span>
            </label>
        </div>

        <div class="ap-logs-feed__viewport" data-ap-bug-viewport data-state="loading">
            <div class="ap-logs-state ap-logs-state--loading" data-ap-bug-loading>
                <span class="ap-logs-spinner" aria-hidden="true"></span>
                <span>Загрузка…</span>
            </div>
            <div class="ap-logs-state ap-logs-state--empty" data-ap-bug-empty hidden>
                <div class="ap-logs-empty__icon" aria-hidden="true">✓</div>
                <p class="ap-logs-empty__title">Сообщений нет</p>
                <p class="ap-logs-empty__text">Пока нет багов и предложений по выбранным фильтрам.</p>
            </div>
            <div class="ap-logs-list" data-ap-bug-mount role="list" hidden></div>
        </div>

        <div class="ap-logs-feed__footer" data-ap-bug-footer hidden>
            <button type="button" class="ap-logs-more" data-ap-bug-more>Показать ещё</button>
        </div>
    </section>
</div>

<div class="ap-modal ap-mail-detail-modal" id="ap-bug-detail" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-bug-detail-title" hidden>
    <div class="ap-modal__backdrop" data-ap-bug-detail-close></div>
    <div class="ap-modal__panel" style="max-width:760px;width:min(760px,96vw);">
        <button type="button" class="ap-modal__close" data-ap-bug-detail-close aria-label="Закрыть">&times;</button>
        <p class="ap-logs-hero__eyebrow" style="margin:0 0 0.25rem">Тикет</p>
        <h2 id="ap-bug-detail-title" class="ap-modal__title" style="margin:0 0 0.5rem;font-size:1.25rem">#—</h2>
        <div data-ap-bug-detail-body></div>
    </div>
</div>
