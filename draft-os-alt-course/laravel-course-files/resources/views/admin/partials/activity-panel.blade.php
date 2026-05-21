@php
    $panelMode = $panelMode ?? 'full';
    $feedUrl = $activityFeedUrl ?? route('admin.activity.feed');
    $filters = $activityFilters ?? ['date_from' => '', 'date_to' => '', 'user' => '', 'kinds' => []];
    $kindsCatalog = $activityKinds ?? \App\Services\PortalActivityFeedService::KIND_LABELS;
    $emailSuggestions = $activityEmails ?? [];
    $defaultLimit = $panelMode === 'compact' ? 14 : 120;
    $showFilters = $panelMode === 'full';
@endphp

<div class="ap-activity-panel @if($showFilters) ap-activity-panel--full @endif"
     data-ap-activity-panel
     data-ap-activity-feed-url="{{ $feedUrl }}"
     data-ap-activity-limit="{{ $defaultLimit }}"
     data-ap-activity-mode="{{ $panelMode }}">
    @if ($showFilters)
        <form class="ap-act-toolbar" data-ap-activity-filters autocomplete="off">
            <div class="ap-act-toolbar__grid">
                <div class="ap-act-toolbar__block ap-act-toolbar__block--period">
                    <span class="ap-act-toolbar__heading">Период</span>
                    <div class="ap-act-seg" role="group" aria-label="Быстрый выбор периода" data-ap-period-group>
                        <button type="button" class="ap-act-seg__btn" data-ap-period="today">Сегодня</button>
                        <button type="button" class="ap-act-seg__btn" data-ap-period="7d">7 дней</button>
                        <button type="button" class="ap-act-seg__btn" data-ap-period="30d">30 дней</button>
                        <button type="button" class="ap-act-seg__btn" data-ap-period="all">Всё время</button>
                    </div>
                    <div class="ap-act-dates">
                        <label class="ap-act-date">
                            <span>с</span>
                            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                        </label>
                        <span class="ap-act-dates__sep" aria-hidden="true">—</span>
                        <label class="ap-act-date">
                            <span>по</span>
                            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                        </label>
                    </div>
                </div>

                <div class="ap-act-toolbar__block ap-act-toolbar__block--user">
                    <span class="ap-act-toolbar__heading">Пользователь</span>
                    <div class="ap-act-search">
                        @include('partials.ap-icon', ['name' => 'search', 'size' => 'sm'])
                        <input type="search" name="user" list="ap-activity-emails"
                               placeholder="Поиск по email…"
                               value="{{ $filters['user'] ?? '' }}">
                    </div>
                </div>

                <div class="ap-act-toolbar__block ap-act-toolbar__block--types">
                    <span class="ap-act-toolbar__heading">Тип события</span>
                    <div class="ap-act-type-pills">
                        @foreach ($kindsCatalog as $kindKey => $kindLabel)
                            <label class="ap-act-type-pill">
                                <input type="checkbox" name="kinds[]" value="{{ $kindKey }}"
                                       @checked(in_array($kindKey, $filters['kinds'] ?? [], true))>
                                <span>{{ $kindLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <datalist id="ap-activity-emails">
                @foreach ($emailSuggestions as $email)
                    <option value="{{ $email }}"></option>
                @endforeach
            </datalist>

            <div class="ap-act-toolbar__footer">
                <button type="button" class="ap-btn ap-btn--ghost ap-btn--sm" data-ap-activity-reset>
                    Сбросить фильтры
                </button>
                <div class="ap-act-toolbar__meta">
                    <span class="ap-act-status" data-ap-activity-status aria-live="polite"></span>
                    <label class="ap-act-live">
                        <input type="checkbox" data-ap-activity-live>
                        <span class="ap-act-live__track" aria-hidden="true"><span class="ap-act-live__thumb"></span></span>
                        <span class="ap-act-live__label">Live</span>
                    </label>
                </div>
            </div>
        </form>
    @else
        <div class="ap-activity-panel__compact-bar">
            <span class="ap-act-status" data-ap-activity-status aria-live="polite"></span>
            <label class="ap-act-live ap-act-live--sm">
                <input type="checkbox" data-ap-activity-live>
                <span class="ap-act-live__track" aria-hidden="true"><span class="ap-act-live__thumb"></span></span>
                <span class="ap-act-live__label">Live</span>
            </label>
        </div>
    @endif

    <div class="ap-activity-panel__body" data-ap-activity-body>
        <p class="ap-muted ap-activity-panel__loading" data-ap-activity-loading hidden>Загрузка…</p>
        <p class="ap-muted ap-activity-panel__empty" data-ap-activity-empty hidden>По выбранным фильтрам событий нет.</p>
        <div data-ap-activity-mount></div>
    </div>
</div>
