@php
    $feedUrl = $incidentFeedUrl ?? route('admin.incidents.feed');
    $sourceLabels = $sourceLabels ?? \App\Services\PortalIncidentFeedService::SOURCE_LABELS;
    $emailSuggestions = $emailSuggestions ?? [];
@endphp

<div class="ap-logs-layout ap-incident-panel"
     data-ap-incident-panel
     data-ap-incident-feed-url="{{ $feedUrl }}"
     data-ap-incident-detail-url="{{ rtrim(route('admin.incidents.index'), '/') }}">
    <aside class="ap-logs-filters">
        <div class="ap-logs-filters__head">
            <h2 class="ap-logs-filters__title">Фильтры</h2>
            <button type="button" class="ap-logs-filters__reset" data-ap-incident-reset>Сброс</button>
        </div>

        <form class="ap-logs-filters__form" data-ap-incident-filters autocomplete="off">
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
                    <input type="search" name="user" list="ap-incident-emails" class="ap-logs-input" placeholder="email…">
                </div>
            </fieldset>

            <fieldset class="ap-logs-field">
                <legend class="ap-logs-field__label">Источник</legend>
                <div class="ap-logs-sources">
                    @foreach ($sourceLabels as $sourceKey => $sourceLabel)
                        <label class="ap-logs-source">
                            <input type="checkbox" name="sources[]" value="{{ $sourceKey }}" checked>
                            <span>{{ $sourceLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="ap-logs-field">
                <legend class="ap-logs-field__label">Код HTTP</legend>
                <select name="status" class="ap-logs-input ap-logs-input--select">
                    <option value="">Любой</option>
                    <option value="403">403</option>
                    <option value="404">404</option>
                    <option value="419">419</option>
                    <option value="422">422</option>
                    <option value="500">500</option>
                    <option value="503">503</option>
                </select>
            </fieldset>

            <datalist id="ap-incident-emails">
                @foreach ($emailSuggestions as $email)
                    <option value="{{ $email }}"></option>
                @endforeach
            </datalist>
        </form>
    </aside>

    <section class="ap-logs-feed">
        <div class="ap-logs-feed__bar">
            <span class="ap-logs-feed__status" data-ap-incident-status aria-live="polite"></span>
            <label class="ap-logs-live">
                <input type="checkbox" data-ap-incident-live>
                <span class="ap-logs-live__dot" aria-hidden="true"></span>
                <span>Live</span>
            </label>
        </div>

        <div class="ap-logs-feed__viewport" data-ap-incident-viewport data-state="loading">
            <div class="ap-logs-state ap-logs-state--loading" data-ap-incident-loading>
                <span class="ap-logs-spinner" aria-hidden="true"></span>
                <span>Загрузка журнала…</span>
            </div>

            <div class="ap-logs-state ap-logs-state--empty" data-ap-incident-empty hidden>
                <div class="ap-logs-empty__icon" aria-hidden="true">✓</div>
                <p class="ap-logs-empty__title">Инцидентов нет</p>
                <p class="ap-logs-empty__text">Записей по фильтрам нет — система работает штатно.</p>
            </div>

            <div class="ap-logs-list" data-ap-incident-mount role="list" hidden></div>
        </div>

        <div class="ap-logs-feed__footer" data-ap-incident-footer hidden>
            <button type="button" class="ap-logs-more" data-ap-incident-more>
                Показать ещё
                @include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])
            </button>
        </div>
    </section>
</div>
