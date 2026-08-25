@extends('layouts.admin')

@section('title', 'Статистика портала — Панель администратора')

@php
    /** @var array<string, mixed> $stats */
    $users = $stats['users'] ?? [];
    $authors = $stats['authors'] ?? [];
    $content = $stats['content'] ?? [];
    $participants = $stats['participants'] ?? [];
    $progress = $stats['progress'] ?? [];
    $changelog = $stats['changelog'] ?? [];
    $courses = $stats['courses'] ?? [];
    $funnel = $stats['funnel'] ?? [];
    $engagementPct = (int) ($stats['engagement_pct'] ?? 0);

    $authorActivePct = (int) ($authors['active_pct'] ?? 0);
    $testsLabs = (int) ($content['tests_total'] ?? 0) + (int) ($content['labs_total'] ?? 0);
    $funnelMax = max(1, ...array_map(static fn ($r) => (int) ($r['value'] ?? 0), $funnel ?: [['value' => 1]]));
    $mixMax = max(1, ...array_map(static fn ($r) => (int) ($r['value'] ?? 0), $content['mix'] ?? [['value' => 1]]));
    $timeline = $changelog['timeline'] ?? [];
    $timelineMax = max(1, ...array_map(static fn ($r) => (int) ($r['value'] ?? 0), $timeline ?: [['value' => 1]]));
    $courseMaxAssigned = max(1, ...array_map(static fn ($r) => (int) ($r['assigned'] ?? 0), $courses ?: [['assigned' => 1]]));
    $tagTotal = max(1, (int) ($changelog['total'] ?? 1));
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/platform-stats.css') }}?v={{ @filemtime(public_path('css/platform-stats.css')) ?: 1 }}">
@endpush

@section('content')
    <div class="ap-page ap-fade ps-page">
        <header class="ps-hero">
            <p class="ps-hero__kicker">Администратор и аудитор портала</p>
            <h1 class="ps-hero__title">Статистика активности</h1>
            <p class="ps-hero__lead">
                Живая картина портала: кто реально наполняет курсы, кого назначили на лабы
                и у кого есть прогресс — без «мёртвых» ролей и пустых заходов.
            </p>
            <div class="ps-hero__meta">
                <span class="ps-pill">Обновлено {{ $stats['generated_at'] ?? '—' }}</span>
                <span class="ps-pill">Вовлечённость {{ $engagementPct }}%</span>
                <span class="ps-pill">Доработок {{ (int) ($changelog['total'] ?? 0) }}</span>
            </div>
        </header>

        <div class="ps-kpi-grid">
            <article class="ps-kpi ps-kpi--users">
                <p class="ps-kpi__label">
                    @include('partials.ap-icon', ['name' => 'users', 'size' => 'sm'])
                    Пользователей всего
                </p>
                <p class="ps-kpi__value"><span class="js-ps-num" data-ps-target="{{ (int) ($users['total'] ?? 0) }}">0</span></p>
                <p class="ps-kpi__hint">
                    обучающихся: <strong>{{ (int) ($users['learners_only'] ?? 0) }}</strong>
                    · staff: {{ (int) ($users['staff'] ?? 0) }}
                </p>
            </article>

            <article class="ps-kpi ps-kpi--authors">
                <p class="ps-kpi__label">
                    @include('partials.ap-icon', ['name' => 'pencil', 'size' => 'sm'])
                    Авторов с правами
                </p>
                <p class="ps-kpi__value"><span class="js-ps-num" data-ps-target="{{ (int) ($authors['granted'] ?? 0) }}">0</span></p>
                <p class="ps-kpi__hint">
                    активных (вносили правки): <strong>{{ (int) ($authors['active'] ?? 0) }}</strong>
                    · без правок: {{ (int) ($authors['dormant'] ?? 0) }}
                </p>
            </article>

            <article class="ps-kpi ps-kpi--content">
                <p class="ps-kpi__label">
                    @include('partials.ap-icon', ['name' => 'clipboard-check', 'size' => 'sm'])
                    Тестов и лабораторных
                </p>
                <p class="ps-kpi__value"><span class="js-ps-num" data-ps-target="{{ $testsLabs }}">0</span></p>
                <p class="ps-kpi__hint">
                    тесты/экзамены/опросы: <strong>{{ (int) ($content['tests_total'] ?? 0) }}</strong>
                    · практики+Docker: <strong>{{ (int) ($content['labs_total'] ?? 0) }}</strong>
                </p>
            </article>

            <article class="ps-kpi ps-kpi--assigned">
                <p class="ps-kpi__label">
                    @include('partials.ap-icon', ['name' => 'academic', 'size' => 'sm'])
                    Назначено участников
                </p>
                <p class="ps-kpi__value"><span class="js-ps-num" data-ps-target="{{ (int) ($participants['assigned_excl_staff'] ?? 0) }}">0</span></p>
                <p class="ps-kpi__hint">
                    enrollment + гранулярный доступ · без staff
                    @if (($participants['assigned_staff'] ?? 0) > 0)
                        (ещё {{ (int) $participants['assigned_staff'] }} staff)
                    @endif
                </p>
            </article>

            <article class="ps-kpi ps-kpi--progress">
                <p class="ps-kpi__label">
                    @include('partials.ap-icon', ['name' => 'check', 'size' => 'sm'])
                    С прогрессом
                </p>
                <p class="ps-kpi__value"><span class="js-ps-num" data-ps-target="{{ (int) ($progress['with_progress_excl_staff'] ?? 0) }}">0</span></p>
                <p class="ps-kpi__hint">
                    попытки/практика/опрос: <strong>{{ (int) ($progress['deep_progress_excl_staff'] ?? 0) }}</strong>
                    · завершили курс: <strong>{{ (int) ($progress['completed_excl_staff'] ?? 0) }}</strong>
                </p>
            </article>

            <article class="ps-kpi ps-kpi--changelog">
                <p class="ps-kpi__label">
                    @include('partials.ap-icon', ['name' => 'book-open', 'size' => 'sm'])
                    Доработок за всё время
                </p>
                <p class="ps-kpi__value"><span class="js-ps-num" data-ps-target="{{ (int) ($changelog['total'] ?? 0) }}">0</span></p>
                <p class="ps-kpi__hint">
                    новинок: <strong>{{ (int) (($changelog['by_tag']['feature'] ?? 0)) }}</strong>
                    · улучшений: {{ (int) (($changelog['by_tag']['improvement'] ?? 0)) }}
                </p>
            </article>
        </div>

        <div class="ps-layout">
            <section class="ps-card">
                <div class="ps-card__head">
                    <h2 class="ps-card__title">Воронка активности</h2>
                    <p class="ps-card__sub">от базы пользователей к реальному прохождению</p>
                </div>
                <div class="ps-funnel" id="ps-funnel">
                    @foreach ($funnel as $step)
                        @php
                            $val = (int) ($step['value'] ?? 0);
                            $w = (int) round(100 * $val / $funnelMax);
                        @endphp
                        <div class="ps-funnel__row">
                            <div class="ps-funnel__label">{{ $step['label'] ?? '' }}</div>
                            <div class="ps-funnel__track">
                                <div class="ps-funnel__fill js-ps-bar" data-ps-width="{{ $w }}" style="width:0"></div>
                            </div>
                            <div class="ps-funnel__value">{{ $val }}</div>
                        </div>
                    @endforeach
                </div>
                <p class="ps-note">
                    «Назначены» — запись на курс или выдача доступа к модулю/разделу.
                    «С прогрессом» — теория, попытка теста, практика, экзамен или сданный опрос (без staff).
                </p>
            </section>

            <section class="ps-card">
                <div class="ps-card__head">
                    <h2 class="ps-card__title">Авторы: права vs активность</h2>
                    <p class="ps-card__sub">{{ $authorActivePct }}% реально наполняли</p>
                </div>
                <div class="ps-donut-wrap">
                    <div class="ps-donut" style="--ps-pct: {{ $authorActivePct }}%">
                        <div class="ps-donut__hole">
                            <div>
                                <div class="ps-donut__num">{{ $authorActivePct }}%</div>
                                <div class="ps-donut__cap">активны</div>
                            </div>
                        </div>
                    </div>
                    <ul class="ps-legend">
                        <li class="ps-legend__row">
                            <span class="ps-legend__swatch" style="background:#00b956"></span>
                            <span class="ps-legend__label">Вносили правки</span>
                            <span class="ps-legend__value">{{ (int) ($authors['active'] ?? 0) }}</span>
                        </li>
                        <li class="ps-legend__row">
                            <span class="ps-legend__swatch" style="background:#e5efe9"></span>
                            <span class="ps-legend__label">Только права</span>
                            <span class="ps-legend__value">{{ (int) ($authors['dormant'] ?? 0) }}</span>
                        </li>
                        <li class="ps-legend__row">
                            <span class="ps-legend__swatch" style="background:#0d9488"></span>
                            <span class="ps-legend__label">Всего выдано</span>
                            <span class="ps-legend__value">{{ (int) ($authors['granted'] ?? 0) }}</span>
                        </li>
                    </ul>
                </div>

                @if (! empty($authors['top_active']))
                    <p class="ps-card__sub" style="margin:1rem 0 0.55rem">Топ по правкам контента</p>
                    <div class="ps-top-authors">
                        @foreach ($authors['top_active'] as $row)
                            <div class="ps-top-authors__row">
                                <span class="ps-top-authors__email" title="{{ $row['email'] }}">{{ $row['email'] }}</span>
                                <span class="ps-top-authors__edits">{{ (int) $row['edits'] }} правок</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <div class="ps-layout">
            <section class="ps-card">
                <div class="ps-card__head">
                    <h2 class="ps-card__title">Состав тестов и лабораторных</h2>
                    <p class="ps-card__sub">
                        курсов опубл.: {{ (int) ($content['courses_published'] ?? 0) }}
                        · черновиков: {{ (int) ($content['courses_draft'] ?? 0) }}
                    </p>
                </div>
                <div class="ps-mix" style="margin-bottom:1rem">
                    @foreach (($content['mix'] ?? []) as $item)
                        <div class="ps-mix__item">
                            <p class="ps-mix__val">{{ (int) ($item['value'] ?? 0) }}</p>
                            <p class="ps-mix__lab">
                                <span class="ps-mix__dot" style="background:{{ $item['color'] ?? '#00b956' }}"></span>
                                {{ $item['label'] ?? '' }}
                            </p>
                        </div>
                    @endforeach
                </div>
                <div class="ps-bars">
                    @foreach (($content['mix'] ?? []) as $item)
                        @php $w = (int) round(100 * ((int) ($item['value'] ?? 0)) / $mixMax); @endphp
                        <div class="ps-bar">
                            <div class="ps-bar__meta">
                                <span class="ps-bar__label">{{ $item['label'] ?? '' }}</span>
                                <span class="ps-bar__value">{{ (int) ($item['value'] ?? 0) }}</span>
                            </div>
                            <div class="ps-bar__track">
                                <div class="ps-bar__fill js-ps-bar" data-ps-width="{{ $w }}"
                                     style="width:0;background:linear-gradient(90deg, {{ $item['color'] ?? '#00b956' }}, {{ $item['color'] ?? '#0d9488' }})"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="ps-note">
                    Банков вопросов: {{ (int) ($content['quiz_banks'] ?? 0) }}.
                    Практики считаются по разделам + Docker-образам.
                </p>
            </section>

            <section class="ps-card">
                <div class="ps-card__head">
                    <h2 class="ps-card__title">Участники → прогресс</h2>
                    <p class="ps-card__sub">доля назначенных с хоть каким-то прогрессом</p>
                </div>
                <div class="ps-donut-wrap">
                    <div class="ps-donut" style="--ps-pct: {{ $engagementPct }}%; background: conic-gradient(#65a30d 0 {{ $engagementPct }}%, #e5efe9 {{ $engagementPct }}% 100%)">
                        <div class="ps-donut__hole">
                            <div>
                                <div class="ps-donut__num">{{ $engagementPct }}%</div>
                                <div class="ps-donut__cap">вовлечены</div>
                            </div>
                        </div>
                    </div>
                    <ul class="ps-legend">
                        <li class="ps-legend__row">
                            <span class="ps-legend__swatch" style="background:#65a30d"></span>
                            <span class="ps-legend__label">С прогрессом</span>
                            <span class="ps-legend__value">{{ (int) ($progress['with_progress_excl_staff'] ?? 0) }}</span>
                        </li>
                        <li class="ps-legend__row">
                            <span class="ps-legend__swatch" style="background:#0284c7"></span>
                            <span class="ps-legend__label">Глубокий прогресс</span>
                            <span class="ps-legend__value">{{ (int) ($progress['deep_progress_excl_staff'] ?? 0) }}</span>
                        </li>
                        <li class="ps-legend__row">
                            <span class="ps-legend__swatch" style="background:#e5efe9"></span>
                            <span class="ps-legend__label">Только теория</span>
                            <span class="ps-legend__value">{{ (int) ($progress['theory_only_excl_staff'] ?? 0) }}</span>
                        </li>
                        <li class="ps-legend__row">
                            <span class="ps-legend__swatch" style="background:#00b956"></span>
                            <span class="ps-legend__label">Завершили (сертификат)</span>
                            <span class="ps-legend__value">{{ (int) ($progress['completed_excl_staff'] ?? 0) }}</span>
                        </li>
                    </ul>
                </div>
            </section>
        </div>

        <div class="ps-layout">
            <section class="ps-card">
                <div class="ps-card__head">
                    <h2 class="ps-card__title">Активность по курсам</h2>
                    <p class="ps-card__sub">топ по назначенным и прогрессу (без staff)</p>
                </div>
                @if ($courses === [])
                    <p class="ap-muted">Нет данных по курсам.</p>
                @else
                    <div style="overflow-x:auto">
                        <table class="ps-course-table">
                            <thead>
                                <tr>
                                    <th>Курс</th>
                                    <th>Статус</th>
                                    <th>Назначено</th>
                                    <th>Прогресс</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($courses as $c)
                                    <tr>
                                        <td>
                                            <div class="ps-course-title" title="{{ $c['title'] }}">{{ $c['title'] }}</div>
                                            <div class="ps-bar" style="margin-top:0.35rem;max-width:220px">
                                                <div class="ps-bar__track">
                                                    @php $cw = (int) round(100 * ((int) $c['assigned']) / $courseMaxAssigned); @endphp
                                                    <div class="ps-bar__fill js-ps-bar" data-ps-width="{{ $cw }}" style="width:0"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if ($c['archived'])
                                                <span class="ps-badge ps-badge--arch">архив</span>
                                            @elseif ($c['published'])
                                                <span class="ps-badge ps-badge--pub">опубликован</span>
                                            @else
                                                <span class="ps-badge ps-badge--draft">черновик</span>
                                            @endif
                                        </td>
                                        <td>{{ (int) $c['assigned'] }}</td>
                                        <td>{{ (int) $c['with_progress'] }}</td>
                                        <td><strong>{{ (int) $c['rate_pct'] }}%</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <div class="ps-stack">
                <section class="ps-card">
                    <div class="ps-card__head">
                        <h2 class="ps-card__title">Доработки по месяцам</h2>
                        <p class="ps-card__sub">лента «Что нового»</p>
                    </div>
                    @if ($timeline === [])
                        <p class="ap-muted">Записей пока нет.</p>
                    @else
                        <div class="ps-spark">
                            @foreach ($timeline as $col)
                                @php $h = max(6, (int) round(78 * ((int) $col['value']) / $timelineMax)); @endphp
                                <div class="ps-spark__col" title="{{ $col['label'] }}: {{ (int) $col['value'] }}">
                                    <div class="ps-spark__bar js-ps-spark" data-ps-height="{{ $h }}" style="height:4px"></div>
                                    <div class="ps-spark__lab">{{ $col['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="ps-mix" style="margin-top:1rem">
                        @foreach ([
                            'feature' => ['Новинки', '#00b956'],
                            'improvement' => ['Улучшения', '#0284c7'],
                            'fix' => ['Исправления', '#b45309'],
                            'docs' => ['Документация', '#64748b'],
                        ] as $tag => [$lab, $color])
                            @php $n = (int) (($changelog['by_tag'][$tag] ?? 0)); @endphp
                            <div class="ps-mix__item">
                                <p class="ps-mix__val">{{ $n }}</p>
                                <p class="ps-mix__lab">
                                    <span class="ps-mix__dot" style="background:{{ $color }}"></span>
                                    {{ $lab }} · {{ (int) round(100 * $n / $tagTotal) }}%
                                </p>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>

        <section class="ps-card" style="margin-bottom:1.5rem">
            <div class="ps-card__head">
                <h2 class="ps-card__title">Список доработок функций</h2>
                <p class="ps-card__sub">всего {{ (int) ($changelog['total'] ?? 0) }} записей</p>
            </div>
            <div class="ps-changelog">
                @forelse (($changelog['entries'] ?? []) as $entry)
                    <article class="ps-changelog__item">
                        <div class="ps-changelog__date">{{ $entry['date_short'] ?? $entry['date'] }}</div>
                        <div>
                            <span class="ps-changelog__tag ps-changelog__tag--{{ $entry['tag'] ?? 'feature' }}">
                                {{ $entry['tag_label'] ?? 'Новинка' }}
                            </span>
                            <h3 class="ps-changelog__title">{{ $entry['title'] ?? '' }}</h3>
                            @if (! empty($entry['items']))
                                <ul class="ps-changelog__items">
                                    @foreach (array_slice($entry['items'], 0, 2) as $item)
                                        <li>{!! $entry['items_html'][$loop->index] ?? e($item) !!}</li>
                                    @endforeach
                                    @if (count($entry['items']) > 2)
                                        <li>… ещё {{ count($entry['items']) - 2 }}</li>
                                    @endif
                                </ul>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="ap-muted">Лента доработок пуста.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    function animateNum(el) {
        var target = parseInt(el.getAttribute('data-ps-target') || '0', 10) || 0;
        var start = 0;
        var dur = 700;
        var t0 = null;
        function frame(ts) {
            if (!t0) t0 = ts;
            var p = Math.min(1, (ts - t0) / dur);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = String(Math.round(start + (target - start) * eased));
            if (p < 1) requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }
    document.querySelectorAll('.js-ps-num').forEach(animateNum);
    requestAnimationFrame(function () {
        document.querySelectorAll('.js-ps-bar').forEach(function (el) {
            el.style.width = (el.getAttribute('data-ps-width') || '0') + '%';
        });
        document.querySelectorAll('.js-ps-spark').forEach(function (el) {
            el.style.height = (el.getAttribute('data-ps-height') || '4') + 'px';
        });
    });
})();
</script>
@endpush
