@extends('layouts.admin')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/docker-sandbox.css') }}?v=3">
@endpush

@section('title', 'Библиотека Docker-образов')

@php
    $apNav = \App\Support\AdminNavigation::adminCourseRouteParams();
@endphp

@section('content')
    <div class="ap-page ap-fade ap-docker">
        <div class="ap-docker__head">
            <div>
                <h1 class="ap-page-title">Библиотека Docker-образов</h1>
                <p class="ap-page-lead ap-docker__lead">
                    Образы из таблицы <code>practice_images</code>. Сборка, тестовый контейнер и запуск <code>check.sh</code> — через lab-daemon.
                </p>
            </div>
            <a href="{{ route('admin.docker.library.create') }}" class="btn btn-primary ap-docker__create">
                @include('partials.ap-icon', ['name' => 'plus', 'size' => 'sm'])
                Мастер: новый образ
            </a>
        </div>

        <form method="get" action="{{ route('admin.docker.library') }}" class="ap-docker__search" role="search">
            <label class="ap-docker__search-label" for="ap-docker-q">Поиск</label>
            <input id="ap-docker-q" name="q" type="search" value="{{ $q }}" placeholder="Название или тег образа…" class="ap-modal__input ap-docker__search-input" autocomplete="off">
            <button type="submit" class="btn btn-primary">Найти</button>
            @if ($q !== '')
                <a href="{{ route('admin.docker.library') }}" class="btn btn-ghost">Сбросить</a>
            @endif
        </form>

        @if ($errors->any())
            <div class="admin-flash admin-flash--err ap-docker__flash" role="alert">
                <strong>Проверьте поля формы.</strong>
                <ul style="margin:0.35rem 0 0;padding-left:1.1rem">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('ok'))
            <div class="admin-flash admin-flash--ok ap-docker__flash" role="status">{{ session('ok') }}</div>
        @endif
        @if (session('err'))
            <div class="admin-flash admin-flash--err ap-docker__flash" role="alert">{{ session('err') }}</div>
        @endif

        @if (! $daemonConfigured)
            <div class="admin-flash admin-flash--err ap-docker__warn" role="status">
                Lab-daemon не настроен: в <code>.env</code> задайте <code>PRACTICE_LAB_DAEMON_URL</code> и <code>PRACTICE_LAB_DAEMON_SECRET</code> — тестовый стенд и размеры образов будут недоступны.
            </div>
        @elseif (! ($practiceLabEnabled ?? false))
            <div class="admin-flash ap-docker__warn" role="status" style="background:#fffbeb;border-color:#fde68a;color:#854d0e">
                <code>PRACTICE_LAB_ENABLED=false</code> — для обучающихся лаба выключена, но отладка образов в библиотеке доступна при настроенном daemon.
            </div>
        @endif

        <div class="ap-docker__grid">
            @foreach ($items as $row)
                @php
                    $tag = (string) $row->docker_tag;
                    $st = is_array($statsByTag[$tag] ?? null) ? $statsByTag[$tag] : null;
                    $bytes = (int) ($st['size_bytes'] ?? 0);
                    $mb = $bytes > 0 ? round($bytes / 1048576, 1) : null;
                    $layers = isset($st['layers_count']) ? (int) $st['layers_count'] : null;
                    $lbs = (string) ($row->last_build_status ?? '');
                    if ($lbs === 'running') {
                        $badgeClass = 'ap-docker-badge ap-docker-badge--build';
                        $badgeLabel = 'Сборка…';
                    } elseif ($lbs === 'fail') {
                        $badgeClass = 'ap-docker-badge ap-docker-badge--err';
                        $badgeLabel = 'Ошибка';
                    } elseif ($row->is_built) {
                        $badgeClass = 'ap-docker-badge ap-docker-badge--ok';
                        $badgeLabel = 'Собран';
                    } else {
                        $badgeClass = 'ap-docker-badge ap-docker-badge--muted';
                        $badgeLabel = 'Не собран';
                    }
                    $settings = $row->modulePracticeSettings->sortBy(function ($s) {
                        $c = $s->courseModule?->course?->title ?? '';
                        $m = $s->courseModule?->title ?? '';

                        return $c.'|'.$m;
                    });
                    $used = $settings->count();
                    $finalLabCourses = \Illuminate\Support\Facades\Schema::hasColumn('courses', 'final_lab_practice_image_id')
                        ? (int) \App\Models\Course::query()->where('final_lab_practice_image_id', $row->id)->count()
                        : 0;
                    $blocked = $used + $finalLabCourses;
                    $editUrl = route('admin.docker.library.edit', ['id' => $row->id]);
                    $sandboxState = is_array($sandboxById[$row->id] ?? null) ? $sandboxById[$row->id] : null;
                    $sandboxRunning = is_array($sandboxState) && ! empty($sandboxState['lab_id']);
                @endphp
                <article class="ap-docker-card">
                    <div class="ap-docker-card__top">
                        <code class="ap-docker-card__tag">{{ trim((string) $row->title) !== '' ? $row->title.':' : '' }}{{ $tag !== '' ? $tag : '—' }}</code>
                        <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>
                    </div>
                    @if (trim((string) ($row->description ?? '')) !== '')
                        <p class="ap-docker-card__desc">{{ $row->description }}</p>
                    @endif
                    <div class="ap-docker-card__meta">
                        @if ($mb !== null)
                            <span>{{ $mb }} МБ</span>
                        @else
                            <span class="ap-muted">— МБ</span>
                        @endif
                        <span class="ap-docker-card__dot" aria-hidden="true">·</span>
                        @if ($layers !== null && $layers >= 0)
                            <span>Слоёв {{ $layers }}</span>
                        @else
                            <span class="ap-muted">Слоёв —</span>
                        @endif
                    </div>

                    <div class="ap-docker-card__use">
                        @if ($blocked === 0)
                            <span class="ap-muted">Не используется в практиках</span>
                        @else
                            <details class="ap-docker-use">
                                <summary class="ap-docker-use__summary">
                                    Используется: {{ $blocked }} {{ $blocked === 1 ? 'привязка' : 'привязок' }}
                                </summary>
                                <ul class="ap-docker-use__list">
                                    @if ($finalLabCourses > 0)
                                        <li>
                                            <span class="ap-docker-use__course">Финальная лабораторная</span>
                                            <span class="ap-docker-use__sep" aria-hidden="true"><span class="ap-docker-use__chev">@include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])</span></span>
                                            <span class="ap-docker-use__module">{{ $finalLabCourses }} {{ $finalLabCourses === 1 ? 'курс' : 'курса' }}</span>
                                        </li>
                                    @endif
                                    @foreach ($settings as $ps)
                                        @php
                                            $cour = $ps->courseModule?->course;
                                            $mod = $ps->courseModule;
                                        @endphp
                                        <li>
                                            <span class="ap-docker-use__course">{{ $cour?->title ?? '—' }}</span>
                                            <span class="ap-docker-use__sep" aria-hidden="true"><span class="ap-docker-use__chev">@include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])</span></span>
                                            <span class="ap-docker-use__module">{{ $mod?->title ?? 'Модуль' }}</span>
                                            <span class="ap-docker-use__sep" aria-hidden="true"><span class="ap-docker-use__chev">@include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])</span></span>
                                            <span class="ap-docker-use__lab">Практика</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>

                    <div class="ap-docker-card__actions">
                        @if ($daemonConfigured && $tag !== '')
                            <button type="button"
                                    class="btn btn-ghost btn-sm ap-docker-sandbox-open @if ($sandboxRunning) ap-docker-sandbox-open--active @endif"
                                    data-image-id="{{ $row->id }}"
                                    data-image-tag="{{ e($tag) }}"
                                    data-image-built="{{ $row->is_built ? '1' : '0' }}"
                                    data-sandbox-start="{{ route('admin.docker.library.sandbox.start', ['id' => $row->id]) }}"
                                    data-sandbox-check="{{ route('admin.docker.library.sandbox.check', ['id' => $row->id]) }}"
                                    data-sandbox-stop="{{ route('admin.docker.library.sandbox.stop', ['id' => $row->id]) }}"
                                    data-sandbox-status="{{ route('admin.docker.library.sandbox.status', ['id' => $row->id]) }}"
                                    title="Запустить контейнер, веб-терминал и check.sh">
                                @if ($sandboxRunning)
                                    Стенд · отладка
                                @else
                                    Тестовый стенд
                                @endif
                            </button>
                            <form method="post" action="{{ route('admin.docker.library.stats.refresh') }}" class="ap-docker-card__form">
                                @csrf
                                <input type="hidden" name="tag" value="{{ $tag }}">
                                <button type="submit" class="btn btn-ghost btn-sm" title="Обновить размер и слои в Docker">Статус образа</button>
                            </form>
                        @else
                            <button type="button" class="btn btn-ghost btn-sm" disabled title="Нет lab-daemon">Тестовый стенд</button>
                        @endif

                        @if ($daemonConfigured)
                            <form method="post" action="{{ route('admin.docker.library.build', ['id' => $row->id]) }}" class="ap-docker-card__form">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm">Пересобрать</button>
                            </form>
                        @else
                            <button type="button" class="btn btn-ghost btn-sm" disabled title="Нет lab-daemon">Пересобрать</button>
                        @endif

                        <a class="btn btn-ghost btn-sm" href="{{ $editUrl }}">Настроить</a>

                        @if ($blocked > 0)
                            <button type="button" class="ap-icon-btn ap-icon-btn--danger" disabled title="Образ используется в практиках или в финальной лабораторной">
                                @include('partials.ap-icon', ['name' => 'trash', 'size' => 'md'])
                            </button>
                        @else
                            <button type="button" class="ap-icon-btn ap-icon-btn--danger ap-docker-del-open" title="Удалить образ" aria-label="Удалить"
                                    data-ap-docker-del-tag="{{ e($tag) }}"
                                    data-ap-docker-del-action="{{ route('admin.docker.library.destroy', ['id' => $row->id]) }}">
                                @include('partials.ap-icon', ['name' => 'trash', 'size' => 'md'])
                            </button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if ($items->isEmpty())
            <p class="ap-muted ap-docker__empty">Образов пока нет. Создайте первый — шаблон по умолчанию: модуль 1 (Alt).</p>
        @endif

        @if ($items->hasPages())
            <div class="ap-docker__pager">
                {{ $items->links() }}
            </div>
        @endif
    </div>

    <form method="post" id="ap-docker-del-form" action="#" class="ap-docker-card__form ap-docker-card__form--trash" hidden>
        @csrf
    </form>

    <div class="ap-modal" id="ap-docker-delete-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-docker-delete-title">
        <div class="ap-modal__backdrop" data-ap-docker-del-close tabindex="-1"></div>
        <div class="ap-modal__panel">
            <button type="button" class="ap-modal__close" data-ap-docker-del-close aria-label="Закрыть">&times;</button>
            <h2 id="ap-docker-delete-title" class="ap-modal__title">Удалить образ?</h2>
            <p class="ap-muted" id="ap-docker-delete-text"></p>
            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-docker-del-close>Отмена</button>
                <button type="button" class="btn btn-primary" id="ap-docker-delete-confirm" style="background:#b91c1c;border-color:#b91c1c">Удалить</button>
            </div>
        </div>
    </div>

    <div class="ap-modal ap-docker-sandbox-modal" id="ap-docker-sandbox-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-docker-sandbox-title">
        <div class="ap-modal__backdrop" data-ap-docker-sandbox-close tabindex="-1"></div>
        <div class="ap-modal__panel ap-docker-sandbox-panel">
            <button type="button" class="ap-modal__close" data-ap-docker-sandbox-close aria-label="Закрыть">&times;</button>

            <header class="ap-docker-sandbox__head">
                <div>
                    <h2 id="ap-docker-sandbox-title" class="ap-docker-sandbox__title">Тестовый стенд</h2>
                    <p class="ap-docker-sandbox__lead">Запуск контейнера → работа в терминале → проверка <code>check.sh</code></p>
                </div>
            </header>

            <div class="ap-docker-sandbox__image-row">
                <span class="ap-docker-sandbox__image-label">Образ</span>
                <code class="ap-docker-sandbox__tag" id="ap-docker-sandbox-tagline"></code>
            </div>

            <p class="ap-docker-sandbox__warn" id="ap-docker-sandbox-warn" hidden></p>

            <div class="ap-docker-sandbox__layout">
                <aside class="ap-docker-sandbox__rail">
                    <ol class="ap-docker-sandbox__steps">
                        <li class="ap-docker-sandbox__step is-current" data-step="start">
                            <span class="ap-docker-sandbox__step-text">Запустите контейнер с этим образом</span>
                        </li>
                        <li class="ap-docker-sandbox__step" data-step="term">
                            <span class="ap-docker-sandbox__step-text">Выполните задания в терминале справа</span>
                        </li>
                        <li class="ap-docker-sandbox__step" data-step="check">
                            <span class="ap-docker-sandbox__step-text">Нажмите «Проверить» — баллы и подсказки ниже</span>
                        </li>
                    </ol>

                    <div class="ap-docker-sandbox__meta" id="ap-docker-sandbox-meta" aria-live="polite"></div>
                    <p class="ap-docker-sandbox__statusline" id="ap-docker-sandbox-status"></p>

                    <div class="ap-docker-sandbox__actions">
                        <button type="button" class="btn btn-primary ap-docker-sandbox__btn-start" id="ap-docker-sandbox-start">
                            @include('partials.ap-icon', ['name' => 'terminal', 'size' => 'sm'])
                            Запустить контейнер
                        </button>
                        <button type="button" class="btn btn-secondary ap-docker-sandbox__btn-check" id="ap-docker-sandbox-check" disabled>
                            @include('partials.ap-icon', ['name' => 'clipboard-check', 'size' => 'sm'])
                            Проверить check.sh
                        </button>
                        <button type="button" class="btn btn-ghost ap-docker-sandbox__btn-stop" id="ap-docker-sandbox-stop" disabled>Остановить стенд</button>
                    </div>

                    <section class="ap-docker-sandbox__log-panel" id="ap-docker-sandbox-log-wrap" hidden>
                        <div class="ap-docker-sandbox__log-head">
                            <span>Результат проверки</span>
                            <span class="ap-docker-sandbox__score" id="ap-docker-sandbox-score" hidden></span>
                        </div>
                        <pre class="ap-docker-sandbox__log" id="ap-docker-sandbox-log"></pre>
                    </section>

                    <p class="ap-docker-sandbox__tip">После правок в мастере: <strong>Пересобрать</strong> образ, затем снова <strong>Запустить</strong>.</p>
                </aside>

                <main class="ap-docker-sandbox__stage">
                    <div class="ap-docker-sandbox__stage-head">
                        <span class="ap-docker-sandbox__stage-title">Веб-терминал</span>
                        <a class="ap-docker-sandbox__stage-link" id="ap-docker-sandbox-terminal" href="#" target="_blank" rel="noopener" hidden>Открыть в новой вкладке ↗</a>
                    </div>
                    <div class="ap-docker-sandbox__stage-body" id="ap-docker-sandbox-terminal-wrap">
                        <div class="ap-docker-sandbox__placeholder" id="ap-docker-sandbox-placeholder">
                            <p class="ap-docker-sandbox__placeholder-title">Терминал появится после запуска</p>
                            <p class="ap-docker-sandbox__placeholder-sub">Нажмите «Запустить контейнер» слева — справа откроется shell пользователя <code>student</code>.</p>
                        </div>
                        <iframe class="ap-docker-sandbox__iframe" id="ap-docker-sandbox-iframe" title="Веб-терминал отладки" hidden sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals" allow="clipboard-read; clipboard-write"></iframe>
                    </div>
                </main>
            </div>

            <footer class="ap-docker-sandbox__foot">
                <button type="button" class="btn btn-ghost" data-ap-docker-sandbox-close>Закрыть</button>
            </footer>
        </div>
    </div>

    <div class="ap-modal" id="ap-docker-create-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-docker-create-title">
        <div class="ap-modal__backdrop" data-ap-docker-modal-close tabindex="-1"></div>
        <div class="ap-modal__panel ap-modal__panel--wide">
            <button type="button" class="ap-modal__close" data-ap-docker-modal-close aria-label="Закрыть">&times;</button>
            <h2 id="ap-docker-create-title" class="ap-modal__title">Создать образ</h2>
            <form method="post" action="{{ route('admin.docker.library.store') }}" class="ap-modal__form">
                @csrf
                <input type="hidden" name="base_template" value="lab-m1">
                <input type="hidden" name="base_os" value="alt">
                <input type="hidden" name="init_from_template" value="1">
                <div class="ap-modal__field">
                    <label class="ap-modal__label" for="ap-docker-title">Название</label>
                    <input id="ap-docker-title" name="title" type="text" required maxlength="200" class="ap-modal__input" value="{{ old('title') }}" placeholder="Например, Лаборатория модуля 2">
                </div>
                <div class="ap-modal__field">
                    <label class="ap-modal__label" for="ap-docker-tag">Тег Docker</label>
                    <input id="ap-docker-tag" name="docker_tag" type="text" required maxlength="200" class="ap-modal__input ap-modal__input--mono" value="{{ old('docker_tag') }}" placeholder="registry/namespace/name:tag" autocomplete="off">
                </div>
                <div class="ap-modal__field">
                    <label class="ap-modal__label" for="ap-docker-desc">Описание</label>
                    <textarea id="ap-docker-desc" name="description" rows="3" maxlength="5000" class="ap-modal__input" placeholder="Необязательно">{{ old('description') }}</textarea>
                </div>
                <div class="ap-modal__footer">
                    <button type="button" class="btn btn-ghost" data-ap-docker-modal-close>Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var csrf = @json(csrf_token());

            function postJson(url) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: '{}',
                }).then(function (r) {
                    return r.json().then(function (j) {
                        return { status: r.status, body: j };
                    });
                });
            }

            function getJson(url) {
                return fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                }).then(function (r) {
                    return r.json().then(function (j) {
                        return { status: r.status, body: j };
                    });
                });
            }

            var sandboxModal = document.getElementById('ap-docker-sandbox-modal');
            var sandboxTagline = document.getElementById('ap-docker-sandbox-tagline');
            var sandboxWarn = document.getElementById('ap-docker-sandbox-warn');
            var sandboxMeta = document.getElementById('ap-docker-sandbox-meta');
            var sandboxStatus = document.getElementById('ap-docker-sandbox-status');
            var sandboxStart = document.getElementById('ap-docker-sandbox-start');
            var sandboxCheck = document.getElementById('ap-docker-sandbox-check');
            var sandboxStop = document.getElementById('ap-docker-sandbox-stop');
            var sandboxTerminal = document.getElementById('ap-docker-sandbox-terminal');
            var sandboxPlaceholder = document.getElementById('ap-docker-sandbox-placeholder');
            var sandboxIframe = document.getElementById('ap-docker-sandbox-iframe');
            var sandboxSteps = document.querySelectorAll('.ap-docker-sandbox__step');
            var sandboxLogWrap = document.getElementById('ap-docker-sandbox-log-wrap');
            var sandboxLog = document.getElementById('ap-docker-sandbox-log');
            var sandboxScore = document.getElementById('ap-docker-sandbox-score');

            function escHtml(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function formatIsoShort(iso) {
                if (!iso) return '';
                try {
                    return new Date(iso).toLocaleString('ru-RU', {
                        day: '2-digit',
                        month: 'short',
                        hour: '2-digit',
                        minute: '2-digit',
                    });
                } catch (e) {
                    return iso;
                }
            }

            function formatCheckLogHtml(text) {
                return String(text).split('\n').map(function (line) {
                    var raw = escHtml(line);
                    if (/^FAIL:/i.test(line)) return '<span class="log-fail">' + raw + '</span>';
                    if (/^HINT:/i.test(line)) return '<span class="log-hint">' + raw + '</span>';
                    if (/^ИТОГО:/i.test(line) || /^RESULT:/i.test(line)) return '<span class="log-result">' + raw + '</span>';
                    if (/^\s*\{/.test(line)) return '<span class="log-json">' + raw + '</span>';
                    return raw;
                }).join('\n');
            }

            function statusRow(label, value, valueClass) {
                return '<div class="ap-docker-sandbox__status-row"><dt>' + escHtml(label) +
                    '</dt><dd' + (valueClass ? ' class="' + valueClass + '"' : '') + '>' + escHtml(value) + '</dd></div>';
            }

            function renderSandboxMeta(st, running) {
                if (!sandboxMeta) return;
                if (!running) {
                    sandboxMeta.innerHTML = '<div class="ap-docker-sandbox__status-card">' +
                        statusRow('Состояние', 'Не запущен') + '</div>';
                    return;
                }
                var rows = statusRow('Контейнер', 'Запущен', 'is-ok');
                if (st.module_key) {
                    rows += statusRow('Режим daemon', 'Модуль ' + st.module_key);
                }
                if (st.started_at) {
                    rows += statusRow('С', formatIsoShort(st.started_at));
                }
                if (st.last_check_at) {
                    var passed = !!st.last_check_passed;
                    var score = st.last_check_score;
                    var max = st.last_check_max_score;
                    if (score != null && max != null) {
                        rows += statusRow('Проверка', score + ' из ' + max + ' баллов', passed ? 'is-ok' : 'is-fail');
                    } else {
                        rows += statusRow('Проверка', passed ? 'Успешно' : 'Есть ошибки', passed ? 'is-ok' : 'is-fail');
                    }
                }
                sandboxMeta.innerHTML = '<div class="ap-docker-sandbox__status-card ap-docker-sandbox__status-card--run">' + rows + '</div>';
            }

            function updateSandboxSteps(running, st) {
                sandboxSteps.forEach(function (el) {
                    el.classList.remove('is-current', 'is-done');
                });
                var sStart = document.querySelector('.ap-docker-sandbox__step[data-step="start"]');
                var sTerm = document.querySelector('.ap-docker-sandbox__step[data-step="term"]');
                var sCheck = document.querySelector('.ap-docker-sandbox__step[data-step="check"]');
                if (!running) {
                    if (sStart) sStart.classList.add('is-current');
                    return;
                }
                if (sStart) sStart.classList.add('is-done');
                if (st && st.last_check_at) {
                    if (sTerm) sTerm.classList.add('is-done');
                    if (sCheck) sCheck.classList.add('is-current');
                } else if (sTerm) {
                    sTerm.classList.add('is-current');
                }
            }

            var sandboxCtx = {
                id: 0,
                tag: '',
                built: false,
                startUrl: '',
                checkUrl: '',
                stopUrl: '',
                statusUrl: '',
            };

            function setSandboxBusy(on) {
                if (!on) return;
                [sandboxStart, sandboxCheck, sandboxStop].forEach(function (b) {
                    if (b) b.disabled = true;
                });
            }

            function renderSandboxState(data) {
                var st = data && data.state ? data.state : null;
                var running = !!(st && st.lab_id);
                renderSandboxMeta(st || {}, running);
                updateSandboxSteps(running, st);
                if (sandboxStatus) {
                    sandboxStatus.textContent = running
                        ? ''
                        : 'Сначала запустите контейнер — терминал откроется справа.';
                }
                if (sandboxStart) {
                    sandboxStart.disabled = running;
                    sandboxStart.classList.toggle('is-running', running);
                }
                if (sandboxCheck) {
                    sandboxCheck.disabled = !running;
                }
                if (sandboxStop) {
                    sandboxStop.disabled = !running;
                }
                var term = running && st && st.terminal_url ? String(st.terminal_url) : '';
                if (sandboxTerminal) {
                    if (term) {
                        sandboxTerminal.href = term;
                    } else {
                        sandboxTerminal.removeAttribute('href');
                    }
                }
                if (sandboxIframe && sandboxPlaceholder) {
                    if (term) {
                        sandboxIframe.hidden = false;
                        sandboxPlaceholder.hidden = true;
                        if (sandboxIframe.getAttribute('src') !== term) {
                            sandboxIframe.src = term;
                        }
                    } else {
                        sandboxIframe.hidden = true;
                        sandboxIframe.removeAttribute('src');
                        sandboxPlaceholder.hidden = false;
                    }
                }
                var log = st && st.last_check_log ? String(st.last_check_log) : '';
                if (sandboxLogWrap && sandboxLog) {
                    if (log) {
                        sandboxLogWrap.hidden = false;
                        sandboxLog.innerHTML = formatCheckLogHtml(log);
                        if (sandboxScore) {
                            var sc = st.last_check_score;
                            var mx = st.last_check_max_score;
                            if (sc != null && mx != null) {
                                sandboxScore.hidden = false;
                                sandboxScore.textContent = sc + ' / ' + mx;
                                sandboxScore.classList.remove('is-ok', 'is-warn', 'is-fail');
                                if (st.last_check_passed) {
                                    sandboxScore.classList.add('is-ok');
                                } else if (mx > 0 && sc / mx >= 0.5) {
                                    sandboxScore.classList.add('is-warn');
                                } else {
                                    sandboxScore.classList.add('is-fail');
                                }
                            } else {
                                sandboxScore.hidden = true;
                            }
                        }
                    } else {
                        sandboxLogWrap.hidden = true;
                        sandboxLog.innerHTML = '';
                        if (sandboxScore) sandboxScore.hidden = true;
                    }
                }
                document.querySelectorAll('.ap-docker-sandbox-open[data-image-id="' + sandboxCtx.id + '"]').forEach(function (btn) {
                    btn.classList.toggle('ap-docker-sandbox-open--active', running);
                    btn.textContent = running ? 'Стенд · отладка' : 'Тестовый стенд';
                });
            }

            function openSandbox(btn) {
                if (!sandboxModal || !btn) return;
                sandboxCtx.id = parseInt(btn.getAttribute('data-image-id'), 10) || 0;
                sandboxCtx.tag = btn.getAttribute('data-image-tag') || '';
                sandboxCtx.built = btn.getAttribute('data-image-built') === '1';
                sandboxCtx.startUrl = btn.getAttribute('data-sandbox-start') || '';
                sandboxCtx.checkUrl = btn.getAttribute('data-sandbox-check') || '';
                sandboxCtx.stopUrl = btn.getAttribute('data-sandbox-stop') || '';
                sandboxCtx.statusUrl = btn.getAttribute('data-sandbox-status') || '';
                if (sandboxTagline) sandboxTagline.textContent = sandboxCtx.tag || '—';
                if (sandboxWarn) {
                    if (!sandboxCtx.built) {
                        sandboxWarn.hidden = false;
                        sandboxWarn.textContent = 'Образ ещё не собран на стенде — контейнер может не стартовать. Сначала «Пересобрать» или соберите в мастере.';
                    } else {
                        sandboxWarn.hidden = true;
                        sandboxWarn.textContent = '';
                    }
                }
                sandboxModal.classList.add('is-open');
                sandboxModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('ap-modal-open');
                if (sandboxCtx.statusUrl) {
                    getJson(sandboxCtx.statusUrl).then(function (res) {
                        renderSandboxState(res.body || {});
                    });
                }
            }

            function closeSandbox() {
                if (!sandboxModal) return;
                sandboxModal.classList.remove('is-open');
                sandboxModal.setAttribute('aria-hidden', 'true');
                if (!document.getElementById('ap-docker-create-modal')?.classList.contains('is-open')
                    && !document.getElementById('ap-docker-delete-modal')?.classList.contains('is-open')) {
                    document.body.classList.remove('ap-modal-open');
                }
                if (sandboxIframe) {
                    sandboxIframe.hidden = true;
                    sandboxIframe.removeAttribute('src');
                }
                if (sandboxPlaceholder) sandboxPlaceholder.hidden = false;
            }

            document.querySelectorAll('.ap-docker-sandbox-open').forEach(function (btn) {
                btn.addEventListener('click', function () { openSandbox(btn); });
            });
            document.querySelectorAll('[data-ap-docker-sandbox-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
                    closeSandbox();
                });
            });
            if (sandboxStart) {
                sandboxStart.addEventListener('click', function () {
                    if (!sandboxCtx.startUrl) return;
                    setSandboxBusy(true);
                    if (sandboxStatus) {
                        sandboxStatus.hidden = false;
                        sandboxStatus.textContent = 'Запуск контейнера…';
                    }
                    postJson(sandboxCtx.startUrl).then(function (res) {
                        var j = res.body || {};
                        if (!j.ok) {
                            if (sandboxStatus) {
                                sandboxStatus.hidden = false;
                                sandboxStatus.textContent = j.error || 'Не удалось запустить стенд';
                            }
                            renderSandboxState(j);
                            return;
                        }
                        renderSandboxState(j);
                    }).catch(function (e) {
                        sandboxStatus.textContent = String(e);
                    }).finally(refreshSandboxFromServer);
                });
            }
            if (sandboxCheck) {
                sandboxCheck.addEventListener('click', function () {
                    if (!sandboxCtx.checkUrl) return;
                    setSandboxBusy(true);
                    if (sandboxStatus) {
                        sandboxStatus.hidden = false;
                        sandboxStatus.textContent = 'Выполняется check.sh…';
                    }
                    postJson(sandboxCtx.checkUrl).then(function (res) {
                        var j = res.body || {};
                        if (!j.ok) {
                            if (sandboxStatus) {
                                sandboxStatus.hidden = false;
                                sandboxStatus.textContent = j.error || 'Ошибка проверки';
                            }
                            return;
                        }
                        renderSandboxState(j);
                    }).catch(function (e) {
                        sandboxStatus.textContent = String(e);
                    }).finally(refreshSandboxFromServer);
                });
            }
            if (sandboxStop) {
                sandboxStop.addEventListener('click', function () {
                    if (!sandboxCtx.stopUrl) return;
                    setSandboxBusy(true);
                    postJson(sandboxCtx.stopUrl).then(function (res) {
                        var j = res.body || {};
                        if (!j.ok) {
                            sandboxStatus.textContent = j.error || 'Не удалось остановить';
                            return;
                        }
                        renderSandboxState({ state: null });
                    }).finally(refreshSandboxFromServer);
                });
            }

            function refreshSandboxFromServer() {
                if (!sandboxCtx.statusUrl) return;
                getJson(sandboxCtx.statusUrl).then(function (res) {
                    renderSandboxState(res.body || {});
                });
            }

            var modal = document.getElementById('ap-docker-create-modal');
            var openBtn = document.getElementById('ap-docker-open-create');
            var delModal = document.getElementById('ap-docker-delete-modal');
            var delForm = document.getElementById('ap-docker-del-form');
            var delText = document.getElementById('ap-docker-delete-text');
            var delConfirm = document.getElementById('ap-docker-delete-confirm');

            function openM() {
                if (!modal) return;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('ap-modal-open');
            }
            function closeM() {
                if (!modal) return;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                if (!delModal || !delModal.classList.contains('is-open')) {
                    document.body.classList.remove('ap-modal-open');
                }
            }
            function openDel() {
                if (!delModal) return;
                delModal.classList.add('is-open');
                delModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('ap-modal-open');
            }
            function closeDel() {
                if (!delModal) return;
                delModal.classList.remove('is-open');
                delModal.setAttribute('aria-hidden', 'true');
                if (!modal || !modal.classList.contains('is-open')) {
                    document.body.classList.remove('ap-modal-open');
                }
            }
            if (openBtn) openBtn.addEventListener('click', openM);
            document.querySelectorAll('[data-ap-docker-modal-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
                    closeM();
                });
            });
            document.querySelectorAll('[data-ap-docker-del-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
                    closeDel();
                });
            });
            document.querySelectorAll('.ap-docker-del-open').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var url = btn.getAttribute('data-ap-docker-del-action') || '';
                    var tag = btn.getAttribute('data-ap-docker-del-tag') || '';
                    if (!delForm || !url) return;
                    delForm.setAttribute('action', url);
                    if (delText) delText.textContent = 'Будет удалена запись образа «' + tag + '». Рецепт на диске останется, если он уже создан.';
                    openDel();
                });
            });
            if (delConfirm && delForm) {
                delConfirm.addEventListener('click', function () {
                    delForm.submit();
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if (sandboxModal && sandboxModal.classList.contains('is-open')) {
                    closeSandbox();
                    e.preventDefault();
                    return;
                }
                if (delModal && delModal.classList.contains('is-open')) {
                    closeDel();
                    e.preventDefault();
                    return;
                }
                if (modal && modal.classList.contains('is-open')) {
                    closeM();
                    e.preventDefault();
                }
            });
        })();
    </script>
@endsection
