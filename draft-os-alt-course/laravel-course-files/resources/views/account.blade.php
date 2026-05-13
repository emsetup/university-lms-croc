@extends('layouts.course')

@section('title', 'Личный кабинет')

@section('content')
    @php
        $brand = '#00b956';
        $stats = $stats ?? ['in_progress' => 0, 'completed' => 0, 'total_time_label' => '0 мин', 'certificates_count' => 0];
        $courseRows = $courseRows ?? [];
        $certificates = $certificates ?? [];
        $portalWelcomeName = $portalWelcomeName ?? null;
    @endphp
    <style>
        .acc-page { max-width: 1120px; margin: 0 auto; padding: 0 0 2rem; --acc-brand: {{ $brand }}; }
        .acc-hero {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 1.25rem 1.35rem 1.1rem;
            margin-bottom: 1rem;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }
        .acc-hero__title { margin: 0 0 0.35rem; font-size: 1.45rem; font-weight: 800; letter-spacing: -0.02em; }
        .acc-hero__sub { margin: 0; color: #64748b; line-height: 1.55; font-size: 0.95rem; }
        .acc-hero__sub strong { color: #0f172a; }
        .acc-hero__fio {
            margin: 0.35rem 0 0;
            font-size: 0.98rem;
            line-height: 1.45;
            color: #334155;
        }
        .acc-hero__fio strong {
            font-weight: 700;
            color: #0f172a;
        }
        .acc-metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.85rem;
            width: 100%;
            margin-top: 1.15rem;
            padding-top: 1.15rem;
            border-top: 1px solid rgba(15, 23, 42, 0.06);
        }
        @media (max-width: 900px) {
            .acc-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 420px) {
            .acc-metrics { grid-template-columns: 1fr; }
        }
        .acc-metric {
            position: relative;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 185, 86, 0.14);
            padding: 0;
            text-align: center;
            overflow: hidden;
            transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.2s ease;
        }
        .acc-metric:hover {
            transform: translateY(-3px);
            border-color: rgba(0, 185, 86, 0.35);
            box-shadow:
                0 14px 32px rgba(0, 185, 86, 0.12),
                0 4px 12px rgba(15, 23, 42, 0.06);
        }
        .acc-metric__stripe {
            height: 4px;
            background: linear-gradient(90deg, #00b956 0%, #2dd48a 55%, #5ee9b0 100%);
        }
        .acc-metric__body {
            padding: 0.95rem 0.75rem 0.9rem;
            background: linear-gradient(180deg, rgba(240, 253, 249, 0.65) 0%, #fff 38%);
        }
        .acc-metric__v {
            font-size: clamp(1.65rem, 4.2vw, 2.15rem);
            font-weight: 800;
            color: #00994a;
            line-height: 1.08;
            letter-spacing: -0.03em;
            font-variant-numeric: tabular-nums;
        }
        .acc-metric--time .acc-metric__v {
            font-size: clamp(1.2rem, 3.1vw, 1.55rem);
            line-height: 1.2;
        }
        .acc-metric__k {
            margin-top: 0.4rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #64748b;
            letter-spacing: 0.01em;
            line-height: 1.35;
        }
        .acc-tabs {
            display: flex;
            gap: 0.25rem;
            border-bottom: 2px solid #e8eeea;
            margin-bottom: 1.1rem;
        }
        .acc-tab {
            appearance: none;
            border: none;
            background: transparent;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            font-size: 0.95rem;
            color: #64748b;
            padding: 0.65rem 1rem 0.75rem;
            margin-bottom: -2px;
            border-bottom: 3px solid transparent;
            border-radius: 8px 8px 0 0;
            transition: color 0.18s ease, background 0.18s ease, border-color 0.18s ease;
        }
        .acc-tab:hover { color: #0f172a; background: rgba(0,185,86,0.06); }
        .acc-tab[aria-selected="true"] {
            color: #0f172a;
            border-bottom-color: var(--acc-brand);
            background: rgba(0,185,86,0.08);
        }
        .acc-panel { display: none; }
        .acc-panel.is-active { display: block; }
        .acc-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }
        @media (max-width: 960px) {
            .acc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 560px) {
            .acc-grid { grid-template-columns: 1fr; }
        }
        .acc-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 1.05rem 1.1rem 1rem;
            display: flex;
            flex-direction: column;
            min-height: 100%;
            opacity: 0;
            animation: accCardIn 0.42s ease forwards;
        }
        @keyframes accCardIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .acc-card--done {
            background: linear-gradient(180deg, #f4fdf8 0%, #fff 52%);
            border: 1px solid rgba(0,185,86,0.22);
        }
        .acc-card__badge {
            display: inline-flex;
            align-self: flex-start;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #047857;
            background: rgba(0,185,86,0.14);
            border-radius: 999px;
            padding: 0.2rem 0.55rem;
            margin-bottom: 0.45rem;
        }
        .acc-card__title { font-weight: 800; font-size: 1.05rem; line-height: 1.28; margin: 0; color: #0f172a; }
        .acc-card__desc {
            margin: 0.45rem 0 0;
            font-size: 0.9rem;
            line-height: 1.45;
            color: #64748b;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
        }
        .acc-bar-wrap { margin-top: 0.85rem; }
        .acc-bar-label { font-size: 0.8rem; color: #64748b; margin: 0 0 0.35rem; }
        .acc-bar {
            height: 10px;
            border-radius: 999px;
            background: #e8eeea;
            overflow: hidden;
        }
        .acc-bar__fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #00b956, #34d399);
            width: 0%;
            transition: width 0.55s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .acc-pin {
            margin-top: 0.65rem;
            font-size: 0.86rem;
            line-height: 1.45;
            color: #334155;
        }
        .acc-pin strong { color: #0f172a; }
        .acc-time {
            margin-top: 0.45rem;
            font-size: 0.86rem;
            color: #64748b;
        }
        .acc-card__actions {
            margin-top: auto;
            padding-top: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .acc-btn-brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 10px;
            padding: 0.55rem 1rem;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            background: linear-gradient(180deg, #00c961 0%, #00b956 45%, #00994a 100%);
            color: #fff;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0, 185, 86, 0.35);
            transition: filter 0.15s ease, transform 0.12s ease, box-shadow 0.15s ease;
        }
        .acc-btn-brand:hover {
            filter: brightness(1.04);
            box-shadow: 0 4px 14px rgba(0, 185, 86, 0.45);
        }
        .acc-btn-brand:active { transform: scale(0.98); }
        .acc-btn-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            padding: 0.55rem 0.95rem;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            background: #fff;
            color: #0f172a;
            border: 1px solid rgba(0, 185, 86, 0.35);
            text-decoration: none;
            transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
        }
        .acc-btn-ghost:hover {
            background: rgba(0, 185, 86, 0.08);
            border-color: #00b956;
            color: #047857;
            box-shadow: 0 2px 10px rgba(0, 185, 86, 0.12);
        }
        .acc-done-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
        .acc-done-txt { font-weight: 800; color: #047857; font-size: 0.92rem; }
        .acc-empty {
            text-align: center;
            padding: 2.5rem 1.25rem;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .acc-empty__icon { font-size: 2.25rem; line-height: 1; margin-bottom: 0.5rem; }
        .acc-empty__title { margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a; }
        .acc-empty__text { margin: 0.45rem auto 0; max-width: 22rem; color: #64748b; font-size: 0.95rem; line-height: 1.5; }
        .acc-empty__actions { margin-top: 1.1rem; }
        .acc-cert-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }
        @media (max-width: 900px) {
            .acc-cert-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 520px) {
            .acc-cert-grid { grid-template-columns: 1fr; }
        }
        .acc-cert-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid rgba(0, 185, 86, 0.1);
            padding: 1rem 1.05rem;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            opacity: 0;
            animation: accCardIn 0.42s ease forwards;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .acc-cert-card:hover {
            border-color: rgba(0, 185, 86, 0.22);
            box-shadow: 0 8px 24px rgba(0, 185, 86, 0.1), 0 2px 8px rgba(0,0,0,0.06);
        }
        .acc-cert-actions {
            margin-top: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
            border-radius: 10px;
            background: linear-gradient(135deg, #ecfdf5, #f0fdf4);
            border: 1px dashed rgba(0,185,86,0.35);
            min-height: 6.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
        }
        .acc-toast {
            position: fixed;
            bottom: 1.25rem;
            left: 50%;
            transform: translateX(-50%) translateY(120%);
            background: #0f172a;
            color: #fff;
            padding: 0.55rem 1rem;
            border-radius: 999px;
            font-size: 0.88rem;
            font-weight: 600;
            z-index: 3000;
            opacity: 0;
            transition: opacity 0.2s ease, transform 0.28s ease;
            pointer-events: none;
        }
        .acc-toast.is-on { opacity: 1; transform: translateX(-50%) translateY(0); }
    </style>

    <div class="acc-page">
        <div class="acc-hero">
            <div>
                <h1 class="acc-hero__title">Личный кабинет</h1>
                <p class="acc-hero__sub">
                    Аккаунт: <strong>{{ $learner->email }}</strong>
                </p>
                @if (! empty($portalWelcomeName))
                    <p class="acc-hero__fio">
                        <span class="muted">ФИО:</span> <strong>{{ $portalWelcomeName }}</strong>
                    </p>
                @endif
            </div>
            <a class="btn btn-ghost" href="{{ route('portal') }}">Каталог курсов</a>

            <div class="acc-metrics" role="group" aria-label="Сводная статистика">
                <div class="acc-metric">
                    <div class="acc-metric__stripe" aria-hidden="true"></div>
                    <div class="acc-metric__body">
                        <div class="acc-metric__v">{{ (int) $stats['in_progress'] }}</div>
                        <div class="acc-metric__k">Курсов в процессе</div>
                    </div>
                </div>
                <div class="acc-metric">
                    <div class="acc-metric__stripe" aria-hidden="true"></div>
                    <div class="acc-metric__body">
                        <div class="acc-metric__v">{{ (int) $stats['completed'] }}</div>
                        <div class="acc-metric__k">Курсов завершено</div>
                    </div>
                </div>
                <div class="acc-metric acc-metric--time">
                    <div class="acc-metric__stripe" aria-hidden="true"></div>
                    <div class="acc-metric__body">
                        <div class="acc-metric__v">{{ $stats['total_time_label'] }}</div>
                        <div class="acc-metric__k">Общее время обучения</div>
                    </div>
                </div>
                <div class="acc-metric">
                    <div class="acc-metric__stripe" aria-hidden="true"></div>
                    <div class="acc-metric__body">
                        <div class="acc-metric__v">{{ (int) $stats['certificates_count'] }}</div>
                        <div class="acc-metric__k">Сертификатов получено</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="acc-tabs" role="tablist" aria-label="Разделы личного кабинета">
            <button type="button" class="acc-tab" role="tab" id="acc-tab-courses" aria-controls="acc-panel-courses" aria-selected="true">Мои курсы</button>
            <button type="button" class="acc-tab" role="tab" id="acc-tab-certs" aria-controls="acc-panel-certs" aria-selected="false">Мои сертификаты</button>
        </div>

        <div id="acc-panel-courses" class="acc-panel is-active" role="tabpanel" aria-labelledby="acc-tab-courses">
            @if (count($courseRows) === 0)
                <div class="acc-empty">
                    <div class="acc-empty__icon" aria-hidden="true">📚</div>
                    <h2 class="acc-empty__title">Вы ещё не начали ни одного курса</h2>
                    <p class="acc-empty__text">Перейдите в каталог и выберите подходящий курс — после старта обучения он появится здесь.</p>
                    <div class="acc-empty__actions">
                        <a class="acc-btn-brand" href="{{ route('portal') }}">Смотреть курсы</a>
                    </div>
                </div>
            @else
                <div class="acc-grid">
                    @foreach ($courseRows as $idx => $r)
                        @php
                            /** @var \App\Models\Course $c */
                            $c = $r['course'];
                            $done = ! empty($r['course_completed']);
                            $pct = (int) ($r['progress_bar_percent'] ?? 0);
                            $fromDb = ! empty($r['modules_from_db']);
                            $mp = (int) ($r['modules_passed'] ?? 0);
                            $mt = (int) ($r['modules_total'] ?? 0);
                            $next = (string) ($r['continue_next'] ?? 'default');
                            $mid = (int) ($r['continue_module_id'] ?? 0);
                            $curTitle = $r['current_module_title'] ?? null;
                        @endphp
                        <article class="acc-card {{ $done ? 'acc-card--done' : '' }}" style="animation-delay: {{ min(20, (int) $idx) * 50 }}ms" data-acc-card>
                            @if ($done)
                                <span class="acc-card__badge">Завершён</span>
                            @endif
                            <h2 class="acc-card__title">{{ $c->title }}</h2>
                            <p class="acc-card__desc">{{ $c->summary }}</p>

                            <div class="acc-bar-wrap">
                                @if ($fromDb && $mt > 0)
                                    <p class="acc-bar-label">{{ $mp }} из {{ $mt }} модулей пройдено</p>
                                @else
                                    <p class="acc-bar-label">Прогресс по курсу: {{ min(100, max(0, $pct)) }}%</p>
                                @endif
                                <div class="acc-bar" aria-hidden="true">
                                    <div class="acc-bar__fill" style="width: {{ min(100, max(0, $pct)) }}%"></div>
                                </div>
                            </div>

                            @if ($done)
                                {{-- completed: optional current line --}}
                            @elseif ($curTitle)
                                <div class="acc-pin">📍 Остановились на: <strong>{{ $curTitle }}</strong></div>
                            @endif

                            <div class="acc-time">⏱ На курсе: {{ $r['time_label'] ?? '0 мин' }}</div>

                            <div class="acc-card__actions">
                                @if ($done)
                                    <div class="acc-done-row">
                                        <span class="acc-done-txt">Пройдено ✓</span>
                                        @if (! empty($r['certificate_available']))
                                            <form method="post" action="{{ route('portal.enroll', ['course' => $c->id]) }}" style="margin:0">
                                                @csrf
                                                <input type="hidden" name="next" value="certificate">
                                                <button type="submit" class="acc-btn-brand">Смотреть сертификат</button>
                                            </form>
                                        @endif
                                    </div>
                                @else
                                    <form method="post" action="{{ route('portal.enroll', ['course' => $c->id]) }}" style="margin:0;display:contents">
                                        @csrf
                                        @if ($next === 'module' && $mid > 0)
                                            <input type="hidden" name="next" value="module">
                                            <input type="hidden" name="module" value="{{ $mid }}">
                                        @elseif ($next === 'final_lab')
                                            <input type="hidden" name="next" value="final_lab">
                                        @endif
                                        <button type="submit" class="acc-btn-brand">Продолжить</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div id="acc-panel-certs" class="acc-panel" role="tabpanel" aria-labelledby="acc-tab-certs" hidden>
            @if (count($certificates) === 0)
                <div class="acc-empty">
                    <div class="acc-empty__icon" aria-hidden="true">🏆</div>
                    <h2 class="acc-empty__title">Сертификатов пока нет</h2>
                    <p class="acc-empty__text">Завершите курс, чтобы получить первый сертификат.</p>
                    <div class="acc-empty__actions">
                        <button type="button" class="acc-btn-brand acc-js-goto-courses">Перейти к курсам</button>
                    </div>
                </div>
            @else
                <div class="acc-cert-grid">
                    @foreach ($certificates as $idx => $cert)
                        <article class="acc-cert-card" style="animation-delay: {{ min(20, (int) $idx) * 50 }}ms" id="{{ $cert['share_anchor'] }}">
                            <div>
                                <div style="font-weight:800;font-size:1rem;line-height:1.25;color:#0f172a">{{ $cert['course_title'] }}</div>
                                <div class="muted" style="margin-top:0.35rem;font-size:0.88rem">Выдан: <strong>{{ $cert['issued_label'] }}</strong></div>
                            </div>
                            <div class="acc-cert-thumb" aria-hidden="true">🏆</div>
                            <div class="acc-cert-actions">
                                <form method="post" action="{{ route('portal.enroll', ['course' => $cert['course_id']]) }}" style="margin:0">
                                    @csrf
                                    <input type="hidden" name="next" value="certificate">
                                    <button type="submit" class="acc-btn-brand" title="Откроется итоговая страница: скачивание только в PNG">Сертификат (PNG)</button>
                                </form>
                                <button
                                    type="button"
                                    class="acc-btn-ghost acc-js-share"
                                    data-share-url="{{ url(route('account', [], false)) }}?tab=certificates#{{ $cert['share_anchor'] }}"
                                >Поделиться</button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="acc-toast" id="acc-toast" role="status" aria-live="polite"></div>

    <script>
        (function () {
            var tabs = document.querySelectorAll('.acc-tab');
            var pCourses = document.getElementById('acc-panel-courses');
            var pCerts = document.getElementById('acc-panel-certs');
            var toast = document.getElementById('acc-toast');
            function showToast(msg) {
                if (!toast) return;
                toast.textContent = msg;
                toast.classList.add('is-on');
                clearTimeout(showToast._t);
                showToast._t = setTimeout(function () { toast.classList.remove('is-on'); }, 2200);
            }
            function setTab(which) {
                var isCourses = which === 'courses';
                tabs.forEach(function (t) {
                    var sel = (isCourses && t.id === 'acc-tab-courses') || (!isCourses && t.id === 'acc-tab-certs');
                    t.setAttribute('aria-selected', sel ? 'true' : 'false');
                });
                if (pCourses) {
                    pCourses.classList.toggle('is-active', isCourses);
                    pCourses.toggleAttribute('hidden', !isCourses);
                }
                if (pCerts) {
                    pCerts.classList.toggle('is-active', !isCourses);
                    pCerts.toggleAttribute('hidden', isCourses);
                }
                try {
                    var u = new URL(window.location.href);
                    if (!isCourses) u.searchParams.set('tab', 'certificates'); else u.searchParams.delete('tab');
                    u.hash = '';
                    history.replaceState({}, '', u.toString());
                } catch (e) {}
            }
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    setTab(tab.id === 'acc-tab-courses' ? 'courses' : 'certs');
                });
            });
            document.querySelectorAll('.acc-js-goto-courses').forEach(function (btn) {
                btn.addEventListener('click', function () { setTab('courses'); });
            });
            document.querySelectorAll('.acc-js-share').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var url = btn.getAttribute('data-share-url') || '';
                    if (!url) return;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(function () {
                            showToast('Ссылка скопирована');
                        }).catch(function () {
                            window.prompt('Скопируйте ссылку', url);
                        });
                    } else {
                        window.prompt('Скопируйте ссылку', url);
                    }
                });
            });
            try {
                var q = new URLSearchParams(window.location.search).get('tab');
                if (q === 'certificates') setTab('certs');
                var h = window.location.hash.replace(/^#/, '');
                if (h && document.getElementById(h)) {
                    setTab('certs');
                    setTimeout(function () {
                        var el = document.getElementById(h);
                        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 80);
                }
            } catch (e) {}
        })();
    </script>
@endsection
