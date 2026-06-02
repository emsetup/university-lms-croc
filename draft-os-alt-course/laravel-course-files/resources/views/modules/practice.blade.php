@extends('layouts.course')

@php
    $mid = (int) ($moduleSequence ?? $module);
@endphp

@section('title', 'Модуль '.$mid.': '.config('course.step_titles.practice'))

@section('content')
<div class="page-container practice-page-anchor" id="practice-page-anchor">
        <a class="back-link" href="{{ route('modules.hub', $module) }}">
            @include('partials.ap-icon', ['name' => 'arrow-left'])
            <span>К шагам модуля</span>
        </a>
    <div class="card">
        <h1 style="margin-top:0">Модуль {{ $mid }}: {{ config('course.step_titles.practice') }}</h1>
        @if ($mid === 1)
            <div class="card" style="margin:0 0 1rem;padding:0.75rem 1rem;border-left:4px solid #2d6a9f;background:#f3f8fc">
                <p style="margin:0;font-size:0.95rem"><strong>Модуль 1 (Docker).</strong> В контейнере — типичный <strong>серверный</strong> профиль ALT. Результаты — в одном файле в домашнем каталоге пользователя <code>student</code> (имя и формат — в тексте задания). Проверка: кнопка <strong>«Проверить результат»</strong> (балл 0–100), зачёт в курсе — <strong>«Принять результат»</strong>.</p>
            </div>
        @endif
        @if ($mid === 8)
            <div class="card" style="margin:0 0 1rem;padding:0.75rem 1rem;border-left:4px solid #2d6a9f;background:#f3f8fc">
                <p style="margin:0;font-size:0.95rem"><strong>Модуль 8 (Docker).</strong> Засчитывается <strong>одно</strong> задание: правки в <strong>трёх файлах</strong> конфигурации <strong>audit</strong> — ровно то, что проверяет <code>sudo /opt/lab-check/check.sh</code> (строка <code>TASK1:PASS</code> или <code>TASK1:FAIL</code>, балл 0 или 100).</p>
                <p class="muted small" style="margin:0.5rem 0 0">Условия перечислены в тексте ниже и в таблице «Что именно проверяется». Запуск <code>auditd</code> в контейнере для кнопки «Проверить результат» <strong>не</strong> требуется.</p>
            </div>
        @endif
        @php
            $practiceRaw = (string) ($meta['practice'] ?? '');
            $practiceHintsVisible = \App\Support\PracticeHintMarkdown::shouldShowBlockquoteHints($practiceSession ?? null);
            $practiceMarkdown = \App\Support\PracticeHintMarkdown::stripBlockquoteHintsUnlessVisible($practiceRaw, $practiceHintsVisible);
            $practiceHintsGated = ! $practiceHintsVisible && \App\Support\PracticeHintMarkdown::containsBlockquoteHints($practiceRaw);
        @endphp
        @if ($practiceHintsGated)
            <p class="muted small" style="margin:0 0 0.75rem">Блоки <strong>подсказок</strong> появятся после первой автопроверки на стенде, в которой <strong>не набран полный балл</strong> (кнопка «Проверить результат» ниже). Если с первого раза всё верно — подсказки не понадобятся.</p>
        @endif
        <article class="theory-article prose-course practice-block theory-content content-protect" data-integrity-protect>
            {!! \Illuminate\Support\Str::markdown($practiceMarkdown) !!}
        </article>
        @include('partials.assessment-integrity')

        @php
            $hasSessionsTable = \Illuminate\Support\Facades\Schema::hasTable('practice_sessions');
            $canUseLab = ($labConfigured ?? false) && ($labImage ?? null);
            $m3HintsAfterCheck =
                (int) $module === 3
                && ($practiceSession ?? null)
                && $practiceSession->last_check_at
                && (int) ($practiceSession->last_check_score ?? 0) < 100;
            $m5Max = (int) (($practiceSession ?? null)?->last_check_max_score ?? 100);
            $m5HintsAfterCheck =
                (int) $module === 5
                && ($practiceSession ?? null)
                && $practiceSession->last_check_at
                && (int) ($practiceSession->last_check_score ?? 0) < $m5Max;
        @endphp

        @if ($m3HintsAfterCheck)
            @include('partials.module_03_timed_hints')
        @endif

        @if ($m5HintsAfterCheck)
            @include('partials.module_05_lab_hints')
        @endif

        <div class="practice-lab-panel card-inner" style="margin-top:1.25rem">
            <h2 class="practice-lab-title">Лабораторный стенд</h2>

            @if (! $hasSessionsTable)
                <p class="muted">Выполните на сервере <code>php artisan migrate</code>, чтобы появились кнопки лаборатории.</p>
            @elseif (! ($labEnabled ?? false))
                <div class="flash err" style="margin-bottom:1rem">Автоматическая лаборатория выключена. Администратору: задайте в <code>.env</code> приложения <code>PRACTICE_LAB_ENABLED=true</code>, <code>PRACTICE_LAB_DAEMON_URL</code> и <code>PRACTICE_LAB_DAEMON_SECRET</code>, поднимите lab-daemon и образ лабы.</div>
            @elseif (! ($labConfigured ?? false))
                <div class="flash err" style="margin-bottom:1rem">Лаборатория включена в конфиге, но не настроена: проверьте URL и секрет daemon (те же значения, что у сервиса lab-daemon).</div>
            @elseif (! ($labImage ?? null))
                <div class="flash err" style="margin-bottom:1rem">Для этого модуля не задан Docker-образ в <code>config/practice_lab.php</code>.</div>
            @endif

            @if ($hasSessionsTable)
                <ol class="practice-flow-steps muted" style="margin:0 0 1rem 1.1rem;line-height:1.55;font-size:0.92rem">
                    <li><strong>«Запустить контейнер»</strong> — изолированная среда для заданий.</li>
                    <li><strong>«Открыть веб-терминал»</strong> — панель справа на этой странице.</li>
                    <li><strong>«Проверить результат»</strong> — автоматическая проверка скриптом в контейнере.</li>
                    <li><strong>«Принять результат»</strong> — появляется **после проверки** под блоком с баллом (зачёт практики в курсе с зафиксированным баллом, в том числе неполным или 0 при подтверждении).</li>
                    <li><strong>«Завершить работу со стендом»</strong> — удаление контейнера.</li>
                </ol>

                @php
                    $ps = $practiceSession ?? null;
                    $hasLab = $ps && $ps->daemon_lab_id && ($ps->expires_at === null || $ps->expires_at->isFuture());
                    $canAccept = false;
                    if ($ps) {
                        $canAccept = $ps->last_check_score !== null
                            ? true
                            : (bool) ($ps->last_check_passed ?? false);
                    }
                @endphp

                <div class="practice-lab-actions">
                    @if (! $hasLab)
                        <form method="post" action="{{ route('modules.practice.lab.start', $module) }}" class="inline-form">
                            @csrf
                            <button type="submit" class="btn btn-primary">Запустить контейнер</button>
                        </form>
                    @else
                        @if ($ps->terminal_url)
                            <p class="practice-terminal-actions">
                                <button type="button" class="btn btn-primary js-practice-terminal-toggle" id="practice-terminal-toggle" data-terminal-url="{{ $ps->terminal_url }}" aria-expanded="false" aria-controls="practice-terminal-dock">
                                    <span class="js-terminal-label-open">Открыть веб-терминал</span>
                                    <span class="js-terminal-label-close" hidden>Скрыть терминал</span>
                                </button>
                            </p>
                        @else
                            <p class="muted">Веб-терминал не выдан (на стенде: <code>LAB_ENABLE_TTY=1</code>, <code>LAB_PUBLIC_HOST</code> — IP или DNS стенда <strong>для браузера обучающегося</strong>, не <code>127.0.0.1</code>; в <code>.env</code> Laravel — <code>PRACTICE_LAB_PUBLIC_HOST</code>; скрипт <code>start-lab-daemon-stand.sh</code> подставляет хост из <code>STAND_SSH</code>). Или SSH / <code>docker exec</code>.</p>
                        @endif
                        <p class="muted small">Стенд активен до {{ $ps->expires_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</p>
                        <form method="post" action="{{ route('modules.practice.lab.check', $module) }}" class="inline-form">
                            @csrf
                            <button type="submit" class="btn btn-primary">Проверить результат</button>
                        </form>
                    @endif
                </div>

                @if ($progress->practice_done_at)
                    <div class="flash ok" style="margin-top:1rem;line-height:1.5">
                        Практика по этому модулю <strong>уже зачтена</strong> в курсе — кнопка «Принять результат» не показывается (повторный зачёт не требуется).
                        @if ($ps && $ps->last_check_score !== null)
                            Последняя проверка на стенде: <strong>{{ $ps->last_check_score }}</strong> из {{ $ps->last_check_max_score ?? 100 }} баллов (на зачтённый в курсе балл это не влияет, пока прогресс не сброшен).
                        @endif
                        Чтобы заново пройти путь «проверка → принять» с новым баллом, нужен <strong>сброс практики</strong> по модулю (преподаватель или администратор курса).
                    </div>
                @endif

                @if ($ps && $ps->last_check_log)
                    @if ($ps->last_check_score !== null)
                        <div class="practice-score-banner" style="margin-top:1rem;padding:0.75rem 1rem;border-radius:8px;background:var(--card-border, #e8e8e8);border:1px solid #ccc">
                            <strong>Оценка по проверке:</strong>
                            {{ $ps->last_check_score }} / {{ $ps->last_check_max_score ?? 100 }} баллов
                            @if (($ps->last_check_max_score ?? 100) <= ($ps->last_check_score ?? 0))
                                <span class="muted">— полный балл</span>
                            @elseif (($ps->last_check_score ?? 0) > 0)
                                <span class="muted">— можно принять или доработать задание</span>
                            @else
                                <span class="muted">— при желании результат всё равно можно принять (0 баллов) или попробовать снова</span>
                            @endif
                        </div>
                    @endif

                    @if ($hasLab && $canAccept && ! $progress->practice_done_at)
                        <form method="post" action="{{ route('modules.practice.lab.accept', $module) }}" style="margin-top:1rem" class="inline-form" onsubmit="return confirm(@json(
                            ($ps->last_check_score !== null
                                ? 'Зачесть практику с баллом '.$ps->last_check_score.' из '.($ps->last_check_max_score ?? 100).'? Несохранённые правки в контейнере можно доделать до «Завершить».'
                                : 'Зафиксировать этот результат и зачесть практику?')
                        ));">
                            @csrf
                            <button type="submit" class="btn btn-primary">Принять результат</button>
                        </form>
                    @endif

                    @if ($ps->last_check_hints && count($ps->last_check_hints) > 0)
                        <div class="practice-hints" style="margin-top:0.75rem">
                            <div class="muted" style="font-size:0.85rem;margin-bottom:0.35rem">Подсказки</div>
                            <ul style="margin:0;padding-left:1.2rem;line-height:1.5">
                                @foreach ($ps->last_check_hints as $h)
                                    <li>{{ $h }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="practice-check-log">
                        <div class="muted" style="font-size:0.8rem;margin-bottom:0.35rem">Журнал последней проверки</div>
                        <pre class="check-log-pre">{{ $ps->last_check_log }}</pre>
                    </div>
                @endif

                @if ($hasLab)
                    <form method="post" action="{{ route('modules.practice.lab.finish', $module) }}" style="margin-top:0.75rem" class="inline-form" onsubmit="return confirm('Удалить контейнер? Несохранённая работа внутри стенда будет потеряна.');">
                        @csrf
                        <button type="submit" class="btn btn-ghost">Завершить работу со стендом</button>
                    </form>
                @endif

                @if ($progress->practice_done_at && optional($ps)->accepted_at)
                    <p class="muted" style="margin-top:1rem">
                        Результат принят {{ $ps->accepted_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}.
                        @if ($ps->accepted_practice_score !== null)
                            Зафиксирован балл по проверке: {{ $ps->accepted_practice_score }}.
                        @endif
                    </p>
                @endif
            @endif
        </div>

        @if (! $progress->practice_done_at)
            @if (!($labEnabled ?? false) || ($allowManualDone ?? false))
                <form method="post" action="{{ route('modules.practice.done', $module) }}" style="margin-top:1rem">
                    @csrf
                    <button type="submit" class="btn btn-ghost">{{ ($labEnabled ?? false) ? 'Отметить без контейнера (аварийно)' : 'Отметить практику выполненной' }}</button>
                </form>
            @endif
        @else
            <p class="muted" style="margin-top:1rem">
                Практика зачтена в курсе.
                @if ($progress->practice_lab_percent !== null)
                    Оценка по автопроверке стенда (в процентах от максимума чек-листа): <strong>{{ (int) $progress->practice_lab_percent }}%</strong>.
                @endif
            </p>
        @endif
    </div>

    @if ($hasSessionsTable ?? false)
        @php
            $dockPs = $practiceSession ?? null;
            $dockHasLab = $dockPs && $dockPs->daemon_lab_id && ($dockPs->expires_at === null || $dockPs->expires_at->isFuture());
            $dockUrl = ($dockHasLab && $dockPs->terminal_url) ? $dockPs->terminal_url : null;
        @endphp
        @if ($dockUrl)
            <div class="terminal-dock-backdrop" id="practice-terminal-backdrop" aria-hidden="true"></div>
            <aside class="terminal-dock-panel" id="practice-terminal-dock" role="dialog" aria-label="Веб-терминал лаборатории" aria-hidden="true" data-terminal-url="{{ $dockUrl }}">
                <div class="terminal-dock-toolbar">
                    <span class="terminal-dock-title">Терминал</span>
                    <div class="terminal-dock-toolbar-actions">
                        <a class="btn btn-ghost terminal-dock-link" href="{{ $dockUrl }}" target="_blank" rel="noopener">Новая вкладка</a>
                        <button type="button" class="btn btn-ghost terminal-dock-link js-practice-terminal-close" aria-label="Скрыть терминал">Закрыть</button>
                    </div>
                </div>
                <iframe class="terminal-dock-frame" id="practice-terminal-iframe" title="Веб-терминал" loading="lazy" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals" allow="clipboard-read; clipboard-write"></iframe>
            </aside>
            <script>
                (function () {
                    function dockWidthPx() {
                        if (window.matchMedia('(max-width: 768px)').matches) {
                            return window.innerWidth + 'px';
                        }
                        return Math.min(window.innerWidth * 0.5, 880) + 'px';
                    }

                    function shellPadPx() {
                        if (window.matchMedia('(max-width: 768px)').matches) {
                            return '';
                        }
                        return dockWidthPx();
                    }

                    function mountToBody(el) {
                        if (el && el.parentNode !== document.body) {
                            document.body.appendChild(el);
                        }
                    }

                    function applyDockLayout(dock, backdrop) {
                        if (backdrop) {
                            backdrop.style.setProperty('position', 'fixed', 'important');
                            backdrop.style.setProperty('top', '0', 'important');
                            backdrop.style.setProperty('left', '0', 'important');
                            backdrop.style.setProperty('right', '0', 'important');
                            backdrop.style.setProperty('bottom', '0', 'important');
                            backdrop.style.setProperty('z-index', '10040', 'important');
                            backdrop.style.setProperty('margin', '0', 'important');
                            backdrop.style.setProperty('background', 'rgba(26, 35, 50, 0.38)', 'important');
                        }
                        dock.style.setProperty('position', 'fixed', 'important');
                        dock.style.setProperty('top', '0', 'important');
                        dock.style.setProperty('bottom', '0', 'important');
                        dock.style.setProperty('height', '100vh', 'important');
                        dock.style.setProperty('max-height', 'none', 'important');
                        dock.style.setProperty('margin', '0', 'important');
                        dock.style.setProperty('z-index', '10050', 'important');
                        dock.style.setProperty('display', 'flex', 'important');
                        dock.style.setProperty('flex-direction', 'column', 'important');
                        dock.style.setProperty('box-sizing', 'border-box', 'important');
                        dock.style.setProperty('transition', 'transform 0.28s ease', 'important');
                        dock.style.setProperty('background', '#0b1220', 'important');

                        if (window.matchMedia('(max-width: 768px)').matches) {
                            dock.style.setProperty('left', '0', 'important');
                            dock.style.setProperty('right', '0', 'important');
                            dock.style.setProperty('width', '100vw', 'important');
                            dock.style.setProperty('max-width', 'none', 'important');
                            dock.style.setProperty('min-width', '0', 'important');
                        } else {
                            dock.style.setProperty('left', 'auto', 'important');
                            dock.style.setProperty('right', '0', 'important');
                            dock.style.setProperty('width', dockWidthPx(), 'important');
                            dock.style.setProperty('max-width', '880px', 'important');
                            dock.style.setProperty('min-width', '280px', 'important');
                        }

                        var frame = dock.querySelector('.terminal-dock-frame');
                        if (frame) {
                            frame.style.setProperty('flex', '1 1 auto', 'important');
                            frame.style.setProperty('min-height', '0', 'important');
                            frame.style.setProperty('width', '100%', 'important');
                            frame.style.setProperty('border', '0', 'important');
                        }
                    }

                    function boot() {
                        var dock = document.getElementById('practice-terminal-dock');
                        var backdrop = document.getElementById('practice-terminal-backdrop');
                        if (!dock) {
                            return;
                        }

                        mountToBody(backdrop);
                        mountToBody(dock);
                        applyDockLayout(dock, backdrop);
                        if (backdrop) {
                            backdrop.style.setProperty('opacity', '0', 'important');
                            backdrop.style.setProperty('pointer-events', 'none', 'important');
                        }

                        var iframe = dock.querySelector('.terminal-dock-frame');
                        var url = (dock.getAttribute('data-terminal-url') || '').trim();
                        var btn = document.getElementById('practice-terminal-toggle');
                        var anchor = document.getElementById('practice-page-anchor');
                        var shell = document.querySelector('.course-shell');
                        var openLabel = document.querySelector('.js-terminal-label-open');
                        var closeLabel = document.querySelector('.js-terminal-label-close');

                        if (!iframe || !url) {
                            return;
                        }

                        var open = false;

                        function unloadTerminalFrame() {
                            // Внутри ttyd может быть beforeunload; убираем src перед уходом со страницы.
                            iframe.removeAttribute('src');
                        }

                        function setDockTransform(visible) {
                            dock.style.setProperty('transform', visible ? 'translateX(0)' : 'translateX(100%)', 'important');
                        }

                        function setOpen(v) {
                            open = v;
                            applyDockLayout(dock, backdrop);
                            if (shell) {
                                shell.style.paddingRight = v ? shellPadPx() : '';
                            }
                            document.body.classList.toggle('practice-terminal-open', v);
                            if (anchor) {
                                anchor.classList.toggle('practice-terminal-open', v);
                            }
                            dock.classList.toggle('is-visible', v);
                            setDockTransform(v);
                            dock.setAttribute('aria-hidden', v ? 'false' : 'true');
                            if (backdrop) {
                                backdrop.classList.toggle('is-visible', v);
                                backdrop.setAttribute('aria-hidden', v ? 'false' : 'true');
                                backdrop.style.setProperty('opacity', v ? '1' : '0', 'important');
                                backdrop.style.setProperty('pointer-events', v ? 'auto' : 'none', 'important');
                            }
                            if (btn) {
                                btn.setAttribute('aria-expanded', v ? 'true' : 'false');
                            }
                            if (openLabel) {
                                openLabel.hidden = v;
                            }
                            if (closeLabel) {
                                closeLabel.hidden = !v;
                            }
                            if (v && !iframe.getAttribute('src')) {
                                iframe.setAttribute('src', url);
                            } else if (!v) {
                                unloadTerminalFrame();
                            }
                        }

                        setDockTransform(false);

                        if (btn) {
                            btn.addEventListener('click', function () {
                                setOpen(!open);
                            });
                        }
                        document.querySelectorAll('.js-practice-terminal-close').forEach(function (el) {
                            el.addEventListener('click', function () {
                                setOpen(false);
                            });
                        });
                        if (backdrop) {
                            backdrop.addEventListener('click', function () {
                                setOpen(false);
                            });
                        }
                        document.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape' && open) {
                                setOpen(false);
                            }
                        });
                        document.querySelectorAll('form[action*="/practice/lab/"]').forEach(function (form) {
                            form.addEventListener('submit', function () {
                                setOpen(false);
                                unloadTerminalFrame();
                            });
                        });
                        window.addEventListener('pagehide', function () {
                            unloadTerminalFrame();
                        });
                        window.addEventListener('resize', function () {
                            if (!dock.parentNode) {
                                return;
                            }
                            applyDockLayout(dock, backdrop);
                            if (open) {
                                if (shell) {
                                    shell.style.paddingRight = shellPadPx();
                                }
                                setDockTransform(true);
                            }
                        });
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', boot);
                    } else {
                        boot();
                    }
                })();
            </script>
        @endif
    @endif
</div>
@endsection
