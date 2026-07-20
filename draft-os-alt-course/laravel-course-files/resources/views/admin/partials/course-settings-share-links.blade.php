{{-- Вкладка «Ссылки»: реестр быстрых ссылок курса / модулей / разделов / опросов --}}
@php
    /** @var list<array<string,mixed>> $shareLinkItems */
    $items = $shareLinkItems ?? [];
    $activeCount = collect($items)->where('active', true)->count();
    $totalCount = count($items);
    $kindLabels = [
        'course' => 'Курс',
        'module' => 'Модуль',
        'section' => 'Раздел',
        'survey' => 'Опрос',
    ];
    $kindIcons = [
        'course' => 'book-open',
        'module' => 'panel',
        'section' => 'file-text',
        'survey' => 'pencil',
    ];
    $moduliUrl = route('admin.course.settings', $tp ?? ['adminCourse' => $course->slug]);
    $byKind = collect($items)->groupBy(fn ($r) => (string) ($r['kind'] ?? 'section'));
@endphp

<div class="ap-share-links-page">
    <header class="ap-share-links-hero">
        <div class="ap-share-links-hero__main">
            <div class="ap-share-links-hero__icon" aria-hidden="true">
                @include('partials.ap-icon', ['name' => 'link', 'size' => 'lg'])
            </div>
            <div class="ap-share-links-hero__text">
                <h2 class="ap-share-links-hero__title">Быстрые ссылки</h2>
                <p class="ap-share-links-hero__lead">
                    Постоянные ссылки на курс, модуль или раздел. Слушателю нужен вход; доступ и видимость сохраняются.
                </p>
            </div>
        </div>
        <div class="ap-share-links-stats" role="group" aria-label="Сводка по ссылкам">
            <div class="ap-share-links-stat @if ($activeCount > 0) is-live @endif">
                <span class="ap-share-links-stat__value">{{ $activeCount }}</span>
                <span class="ap-share-links-stat__label">активных</span>
            </div>
            <div class="ap-share-links-stat">
                <span class="ap-share-links-stat__value">{{ $totalCount }}</span>
                <span class="ap-share-links-stat__label">целей</span>
            </div>
        </div>
    </header>

    @if ($items === [])
        <div class="ap-share-links-empty">
            <p class="ap-share-links-empty__title">Пока нечего показывать</p>
            <p class="ap-muted">Добавьте модули и разделы — здесь появятся цели для ссылок.</p>
        </div>
    @else
        <div class="ap-share-links-toolbar">
            <div class="ap-share-links-filters" role="tablist" aria-label="Фильтр по типу">
                <button type="button" class="ap-share-links-filter is-active" data-share-filter="all" role="tab" aria-selected="true">
                    Все <span class="ap-share-links-filter__n">{{ $totalCount }}</span>
                </button>
                @foreach (['course' => 'Курс', 'module' => 'Модуль', 'section' => 'Раздел', 'survey' => 'Опрос'] as $fk => $fl)
                    @php $n = $byKind->get($fk, collect())->count(); @endphp
                    @if ($n > 0)
                        <button type="button" class="ap-share-links-filter" data-share-filter="{{ $fk }}" role="tab" aria-selected="false">
                            {{ $fl }} <span class="ap-share-links-filter__n">{{ $n }}</span>
                        </button>
                    @endif
                @endforeach
            </div>
            <label class="ap-share-links-search">
                <span class="ap-share-links-search__icon" aria-hidden="true">@include('partials.ap-icon', ['name' => 'search', 'size' => 'sm'])</span>
                <input type="search" id="ap-share-links-q" class="ap-share-links-search__input" placeholder="Найти по названию…" autocomplete="off">
            </label>
        </div>

        <ul class="ap-share-links-list" id="ap-share-links-list">
            @foreach ($items as $row)
                @php
                    $kind = (string) ($row['kind'] ?? 'section');
                    $active = ! empty($row['active']);
                    $url = $row['url'] ?? null;
                    $title = (string) ($row['title'] ?? '—');
                    $moduleTitle = (string) ($row['module_title'] ?? '');
                    $meta = [
                        'active' => $active,
                        'url' => $url,
                        'generate_url' => $row['generate_url'] ?? '',
                        'revoke_url' => $row['revoke_url'] ?? '',
                        'kind' => $kind,
                        'title' => $title,
                    ];
                    $searchBlob = mb_strtolower($title.' '.$moduleTitle.' '.($kindLabels[$kind] ?? $kind));
                @endphp
                <li class="ap-share-links-card @if ($active) is-active @endif"
                    data-share-row
                    data-kind="{{ $kind }}"
                    data-search="{{ e($searchBlob) }}">
                    <div class="ap-share-links-card__kind ap-share-links-card__kind--{{ $kind }}" title="{{ $kindLabels[$kind] ?? $kind }}">
                        @include('partials.ap-icon', ['name' => $kindIcons[$kind] ?? 'link', 'size' => 'sm'])
                        <span>{{ $kindLabels[$kind] ?? $kind }}</span>
                    </div>
                    <div class="ap-share-links-card__body">
                        <div class="ap-share-links-card__title">{{ $title }}</div>
                        @if ($moduleTitle !== '')
                            <div class="ap-share-links-card__meta">{{ $moduleTitle }}</div>
                        @elseif ($kind === 'course')
                            <div class="ap-share-links-card__meta">Велый курс · дашборд</div>
                        @endif
                        @if ($url)
                            <div class="ap-share-links-card__url" title="{{ $url }}">
                                <code>{{ $url }}</code>
                            </div>
                        @endif
                    </div>
                    <div class="ap-share-links-card__status">
                        @if ($active)
                            <span class="ap-share-links-pill ap-share-links-pill--on">
                                <span class="ap-share-links-pill__dot" aria-hidden="true"></span>
                                Включена
                            </span>
                        @else
                            <span class="ap-share-links-pill ap-share-links-pill--off">Выключена</span>
                        @endif
                    </div>
                    <div class="ap-share-links-card__actions">
                        <button type="button"
                                class="btn btn-primary btn-sm ap-share-links-card__share"
                                data-ap-share-link
                                data-ap-share-meta='@json($meta)'>
                            @include('partials.ap-icon', ['name' => 'share', 'size' => 'sm'])
                            {{ $active ? 'Управление' : 'Поделиться' }}
                        </button>
                        @if (! empty($row['edit_anchor']))
                            <a class="btn btn-ghost btn-sm" href="{{ $moduliUrl }}{{ $row['edit_anchor'] }}">К модулю</a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="ap-share-links-none ap-muted" id="ap-share-links-none" hidden>Ничего не найдено по фильтру.</p>
    @endif
</div>

<script>
(function () {
    var list = document.getElementById('ap-share-links-list');
    if (!list) return;

    var filter = 'all';
    var qEl = document.getElementById('ap-share-links-q');
    var noneEl = document.getElementById('ap-share-links-none');
    var filterBtns = document.querySelectorAll('[data-share-filter]');

    function apply() {
        var q = (qEl && qEl.value ? qEl.value : '').trim().toLowerCase();
        var visible = 0;
        list.querySelectorAll('[data-share-row]').forEach(function (row) {
            var kindOk = filter === 'all' || row.getAttribute('data-kind') === filter;
            var search = row.getAttribute('data-search') || '';
            var qOk = !q || search.indexOf(q) !== -1;
            var show = kindOk && qOk;
            row.hidden = !show;
            if (show) visible++;
        });
        if (noneEl) noneEl.hidden = visible > 0;
    }

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filter = btn.getAttribute('data-share-filter') || 'all';
            filterBtns.forEach(function (b) {
                var on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            apply();
        });
    });
    if (qEl) qEl.addEventListener('input', apply);

    document.addEventListener('ap-share-link-changed', function () {
        window.location.reload();
    });
})();
</script>
