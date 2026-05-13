@extends('layouts.course')

@section('title', 'Образовательный портал')

@section('content')
    <style>
        .portal-course-card {
            cursor: pointer;
            display: flex;
            flex-direction: column;
        }
        .portal-course-title { min-height: 2.7rem; }
        .portal-course-grow { flex: 1; min-height: 0; }
        .portal-course-actions {
            margin-top: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .portal-course-audience-row {
            margin-top: 0.65rem;
            display: flex;
            justify-content: center;
            width: 100%;
        }

        /* Каталог курсов: единая сетка (реальные карточки + заглушки + контакт) */
        .portal-catalog-grid.module-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.85rem 1rem;
        }
        @media (max-width: 820px) {
            .portal-catalog-grid.module-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 520px) {
            .portal-catalog-grid.module-grid { grid-template-columns: minmax(0, 1fr); }
        }

        .portal-slot-card {
            position: relative;
            overflow: hidden;
            height: 100%;
            min-height: 17.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: default;
            background: #f5f5f5 !important;
            border: 1px solid #e0e0e0 !important;
            box-shadow: none !important;
        }
        .portal-slot-card::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background-image: radial-gradient(circle at 1px 1px, #dcdcdc 0.65px, transparent 0.7px);
            background-size: 14px 14px;
            opacity: 0.45;
            pointer-events: none;
        }
        .portal-slot-card__hover-msg {
            position: relative;
            z-index: 1;
            text-align: center;
            font-size: 0.9rem;
            line-height: 1.5;
            color: #737373;
            max-width: 12rem;
            opacity: 0;
            transform: translateY(4px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .portal-slot-card:hover .portal-slot-card__hover-msg {
            opacity: 1;
            transform: translateY(0);
        }

        .portal-contact-card {
            position: relative;
            height: 100%;
            min-height: 17.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 1.15rem 1rem;
            cursor: default;
            background: #f5f5f5 !important;
            border: 1px solid #e0e0e0 !important;
            box-shadow: none !important;
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
        }
        .portal-contact-card:hover {
            background: #ececec !important;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
        }
        .portal-contact-card__text {
            font-size: 0.9rem;
            line-height: 1.55;
            color: #888;
            max-width: 14rem;
        }
        .portal-contact-card__text a {
            color: #4b5563;
            font-weight: 500;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: color 0.15s ease, border-color 0.15s ease;
        }
        .portal-contact-card__text a:hover {
            color: #374151;
            border-bottom-color: #9ca3af;
        }

        .portal-catalog-empty {
            grid-column: 1 / -1;
        }

        .portal-course-search-wrap {
            margin-top: 1.1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line, #e5e7eb);
        }
        .portal-course-search-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #64748b;
            margin: 0 0 0.45rem;
        }
        .portal-course-search-row {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            width: 100%;
            box-sizing: border-box;
            padding: 0.45rem 0.65rem 0.45rem 0.75rem;
            border-radius: 999px;
            border: 1px solid #d5e3da;
            background: linear-gradient(180deg, #fbfffc 0%, #f4faf6 100%);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .portal-course-search-row:focus-within {
            border-color: #00b956;
            box-shadow: 0 0 0 3px rgba(0, 185, 86, 0.18), 0 2px 8px rgba(0, 185, 86, 0.08);
            background: #fff;
        }
        .portal-course-search-icon {
            flex: 0 0 auto;
            width: 1.15rem;
            height: 1.15rem;
            color: #00b956;
            opacity: 0.85;
        }
        .portal-course-search-input {
            flex: 1;
            min-width: 0;
            border: none;
            background: transparent;
            font: inherit;
            font-size: 0.95rem;
            line-height: 1.35;
            color: var(--text, #0f172a);
            outline: none;
        }
        .portal-course-search-input::placeholder {
            color: #94a3b8;
        }
        .portal-course-search-status {
            margin: 0.45rem 0 0;
            font-size: 0.82rem;
            min-height: 1.25em;
        }
        .portal-catalog-card--hidden {
            display: none !important;
        }
        .portal-search-no-hits {
            display: none;
            padding: 1.25rem 0.5rem;
            text-align: center;
        }
        .portal-search-no-hits.is-visible {
            display: block;
        }

        .course-info-modal {
            position: fixed;
            inset: 0;
            z-index: 2200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.22s ease, visibility 0.22s ease;
            pointer-events: none;
        }
        .course-info-modal.is-open { visibility: visible; opacity: 1; pointer-events: auto; }
        .course-info-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(3px);
        }
        .course-info-modal__panel {
            position: relative;
            z-index: 1;
            max-width: min(860px, 98vw);
            width: 100%;
            max-height: 92vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 16px;
            padding: 1rem 1.1rem 0.85rem;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.2);
        }
        .course-info-modal__close {
            position: absolute;
            top: 0.55rem;
            right: 0.55rem;
            z-index: 2;
            border: none;
            background: transparent;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
            color: var(--muted, #64748b);
            padding: 0.25rem 0.45rem;
            border-radius: 8px;
        }
        .course-info-modal__close:hover { background: rgba(15, 23, 42, 0.06); color: var(--text, #0f172a); }
        .course-info-modal__body { overflow: auto; padding-right: 0.25rem; }
        .course-info-modal__footer { margin-top: 0.85rem; padding-top: 0.65rem; border-top: 1px solid var(--line, #e5e7eb); display:flex; justify-content:flex-end; gap:0.5rem; flex-wrap:wrap; }
        .course-info-modal__title { margin: 0 0 0.25rem; font-size: 1.15rem; padding-right: 2rem; font-weight: 900; }
        .course-info-modal__slug { margin: 0 0 0.65rem; }
        .course-info-modal__summary { line-height: 1.6; color: #334155; white-space: pre-wrap; }
    </style>

    <div class="card" style="max-width:1100px;margin:0 auto 1rem">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <h1 style="margin:0 0 0.35rem">Образовательный портал</h1>
                @if (! empty($portalWelcomeName))
                    <p class="muted" style="margin:0 0 0.5rem;max-width:60rem;line-height:1.5">
                        Добро пожаловать, <strong>{{ $portalWelcomeName }}</strong>!
                    </p>
                @elseif (! empty($learnerEmail))
                    <p class="muted" style="margin:0 0 0.5rem;max-width:60rem;line-height:1.5">
                        Добро пожаловать! Вы вошли как <strong>{{ $learnerEmail }}</strong>.
                    </p>
                @endif
                <p class="muted" style="margin:0;max-width:60rem;line-height:1.5">
                    Выберите курс и начните обучение. Прогресс и попытки привязаны к вашей корпоративной почте.
                </p>
            </div>
            @if (! session('learner_id'))
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                    <button type="button" class="btn btn-primary" id="portal-login-open">Войти</button>
                </div>
            @else
                @if (! empty($portalStaffAccess))
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                        <a class="btn btn-ghost" href="{{ route('admin.panel') }}">Управление</a>
                    </div>
                @endif
            @endif
        </div>
        <div class="portal-course-search-wrap" role="search" aria-label="Поиск по каталогу курсов">
            <label class="portal-course-search-label" for="portal-course-search">Поиск курса</label>
            <div class="portal-course-search-row">
                <svg class="portal-course-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="M20 20l-3.5-3.5"/>
                </svg>
                <input type="search"
                       id="portal-course-search"
                       class="portal-course-search-input"
                       placeholder="Название, описание или идентификатор курса…"
                       autocomplete="off"
                       spellcheck="false"
                       enterkeyhint="search">
            </div>
            <p class="portal-course-search-status muted" id="portal-course-search-status" aria-live="polite"></p>
        </div>
    </div>

    @if (! empty($identityDebugRows))
        <div class="card" style="max-width:1100px;margin:0 auto 1rem;border:2px dashed #94a3b8;background:#f8fafc">
            <h2 style="margin:0 0 0.5rem;font-size:1.05rem">Отладка личности (только при <code>?identity_debug=1</code>)</h2>
            <p class="muted" style="margin:0 0 0.75rem;font-size:0.9rem">
                Сообщите, какие строки заполнены и какое значение подходит под ФИО. Панель только для диагностики.
            </p>
            <div style="overflow:auto;max-height:70vh">
                <table style="width:100%;border-collapse:collapse;font-size:0.82rem">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid #e2e8f0">
                            <th style="padding:0.35rem 0.5rem;vertical-align:top;width:38%">Подпись поля</th>
                            <th style="padding:0.35rem 0.5rem;vertical-align:top">Значение</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($identityDebugRows as $row)
                            <tr style="border-bottom:1px solid #f1f5f9">
                                <td style="padding:0.35rem 0.5rem;vertical-align:top;color:#334155;font-family:ui-monospace,monospace">{{ $row['label'] }}</td>
                                <td style="padding:0.35rem 0.5rem;vertical-align:top;word-break:break-word;white-space:pre-wrap">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card" style="max-width:1100px;margin:0 auto">
        <h2 style="margin-top:0">Доступные курсы</h2>
        <div class="module-grid portal-catalog-grid courses-catalog-grid" id="portal-courses-catalog-grid" style="margin-top:0.75rem">
            @foreach ($courses as $c)
                @php
                    $enroll = $enrollmentsByCourseId[$c->id] ?? null;
                    $pct = (int) ($progressByCourseId[$c->id] ?? 0);
                    $started = $pct > 0 || ($enroll && ! empty($enroll->started_at));
                @endphp
                <div class="module-card portal-course-card js-course-card"
                     role="button"
                     tabindex="0"
                     data-course-id="{{ (int) $c->id }}"
                     data-course-title="{{ e($c->title) }}"
                     data-course-slug="{{ e($c->slug) }}"
                     data-course-summary="{{ e($c->summary) }}"
                     data-course-started="{{ $started ? '1' : '0' }}"
                     data-course-pct="{{ (int) $pct }}">
                    <div class="tag">Курс</div>
                    <div class="portal-course-title" style="font-weight:800;font-size:1.05rem;line-height:1.25">{{ $c->title }}</div>
                    <div class="portal-course-grow">
                        <div class="muted course-card__description" style="font-size:0.92rem;line-height:1.45;margin-top:0.35rem">{{ $c->summary }}</div>
                    </div>
                    @if ($c->slug === 'alt-os-features')
                        <div class="portal-course-audience-row">
                            <button type="button" class="btn btn-ghost" id="portal-course-audience-open">Подробнее: для кого этот курс</button>
                        </div>
                    @endif

                    @if (session('learner_id') && ($pct > 0 || ($enroll && ! empty($enroll->started_at))))
                        <div style="margin-top:0.85rem">
                            <div class="muted small" style="margin:0 0 0.35rem">Прогресс по курсу: <strong>{{ $pct }}%</strong></div>
                            <div class="learner-track-summary__bar" aria-hidden="true" style="height:10px">
                                <div class="learner-track-summary__bar-fill" style="width: {{ min(100, max(0, $pct)) }}%"></div>
                            </div>
                            @if ($enroll && $enroll->started_at)
                                <div class="muted small" style="margin-top:0.35rem">
                                    Начато: <strong>{{ $enroll->started_at->format('d.m.Y H:i') }}</strong>
                                </div>
                            @endif
                        </div>
                    @endif
                    <div class="portal-course-actions" style="margin-top:0.85rem">
                        @if (session('learner_id'))
                            <form method="post" action="{{ route('portal.enroll', ['course' => $c->id]) }}" style="margin:0">
                                @csrf
                                <button type="submit" class="btn btn-primary">{{ $started ? 'Продолжить' : 'Начать обучение' }}</button>
                            </form>
                        @else
                            <button type="button" class="btn btn-primary portal-start-needs-login" data-course="{{ (int) $c->id }}">Войти и начать</button>
                        @endif
                    </div>
                </div>
            @endforeach
            @if (count($courses) === 0)
                <p class="muted portal-catalog-empty" style="margin:0">Курсы пока не добавлены.</p>
            @endif
            <p class="muted portal-catalog-empty portal-search-no-hits" id="portal-search-no-hits" role="status">
                По запросу ничего не найдено — попробуйте другие слова или сбросьте поиск.
            </p>
            @for ($i = 0; $i < 8; $i++)
                <div class="module-card portal-slot-card js-catalog-extra" aria-hidden="true">
                    <div class="portal-slot-card__hover-msg">
                        🚀 Скоро здесь появится<br>новый курс
                    </div>
                </div>
            @endfor
            <div class="module-card portal-contact-card js-catalog-extra">
                <div class="portal-contact-card__text">
                    Хотите разместить свой курс?<br>
                    Напишите нам:<br>
                    <a href="mailto:emednikov@croc.ru">emednikov@croc.ru</a>
                </div>
            </div>
        </div>
    </div>

    @include('portal.partials.login-modal', ['domain' => config('course.email_domain')])
    @include('partials.course-audience-modal')

    <div class="course-info-modal" id="portal-course-info-modal" aria-hidden="true">
        <div class="course-info-modal__backdrop" data-course-info-close tabindex="-1"></div>
        <div class="course-info-modal__panel" role="dialog" aria-modal="true" aria-labelledby="portal-course-info-title">
            <button type="button" class="course-info-modal__close" data-course-info-close aria-label="Закрыть">&times;</button>
            <div class="tag" style="margin-bottom:0.65rem">Курс</div>
            <div id="portal-course-info-title" class="course-info-modal__title">Курс</div>
            <div class="muted small course-info-modal__slug" id="portal-course-info-slug"></div>
            <div class="course-info-modal__body">
                <div class="course-info-modal__summary" id="portal-course-info-summary"></div>
            </div>
            <div class="course-info-modal__footer" id="portal-course-info-footer">
                <button type="button" class="btn btn-ghost" data-course-info-close>Закрыть</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var input = document.getElementById('portal-course-search');
            var status = document.getElementById('portal-course-search-status');
            var noHits = document.getElementById('portal-search-no-hits');
            var grid = document.getElementById('portal-courses-catalog-grid');
            if (input && grid) {
                var cards = grid.querySelectorAll('.js-course-card');
                var extras = grid.querySelectorAll('.js-catalog-extra');
                var total = cards.length;
                function norm(s) {
                    return String(s || '').toLowerCase();
                }
                function cardHaystack(card) {
                    return norm(
                        (card.getAttribute('data-course-title') || '') + ' ' +
                        (card.getAttribute('data-course-slug') || '') + ' ' +
                        (card.getAttribute('data-course-summary') || '')
                    );
                }
                function matches(hay, q) {
                    if (!q) return true;
                    var parts = q.split(/\s+/).filter(Boolean);
                    var i;
                    for (i = 0; i < parts.length; i++) {
                        if (hay.indexOf(parts[i]) === -1) return false;
                    }
                    return true;
                }
                function apply() {
                    var q = norm(input.value).trim();
                    var n = 0;
                    var j;
                    for (j = 0; j < cards.length; j++) {
                        var card = cards[j];
                        var ok = matches(cardHaystack(card), q);
                        if (ok) n++;
                        card.classList.toggle('portal-catalog-card--hidden', !ok);
                    }
                    var showExtras = q === '';
                    extras.forEach(function (el) {
                        el.classList.toggle('portal-catalog-card--hidden', !showExtras);
                    });
                    if (noHits) {
                        noHits.classList.toggle('is-visible', q !== '' && n === 0 && total > 0);
                    }
                    if (status) {
                        if (total === 0) {
                            status.textContent = '';
                        } else if (q === '') {
                            status.textContent = total === 1 ? 'В каталоге 1 курс.' : ('В каталоге курсов: ' + total + '.');
                        } else {
                            status.textContent = n === 0
                                ? 'Совпадений нет.'
                                : (n === 1 ? 'Найден 1 курс.' : ('Найдено курсов: ' + n + ' из ' + total + '.'));
                        }
                    }
                }
                input.addEventListener('input', apply);
                input.addEventListener('search', apply);
                apply();
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && document.activeElement === input && input.value) {
                        input.value = '';
                        apply();
                    }
                });
            }
        })();

        (function () {
            var dlg = document.getElementById('portal-login-dialog');
            var openBtn = document.getElementById('portal-login-open');
            function open() {
                if (!dlg) return;
                if (typeof dlg.showModal === 'function') dlg.showModal();
                var email = document.getElementById('portal-login-email');
                if (email) setTimeout(function () { try { email.focus(); } catch (e) {} }, 0);
            }
            if (openBtn) openBtn.addEventListener('click', open);
            var needs = document.querySelectorAll('.portal-start-needs-login');
            needs.forEach(function (b) { b.addEventListener('click', open); });
            @if (!empty($showLogin))
            open();
            @endif
        })();

        (function () {
            var modal = document.getElementById('course-audience-modal-root');
            var openBtn = document.getElementById('portal-course-audience-open');
            if (!modal || !openBtn) return;

            var lastFocus = null;
            function closeEls() {
                return modal.querySelectorAll('[data-modal-close]');
            }
            function openModal() {
                lastFocus = document.activeElement;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('course-modal-open');
                var closeBtn = modal.querySelector('.course-modal__close');
                if (closeBtn) closeBtn.focus();
            }
            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('course-modal-open');
                if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
            }
            openBtn.addEventListener('click', openModal);
            closeEls().forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('course-modal__backdrop')) {
                        e.preventDefault();
                    }
                    closeModal();
                });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });
        })();

        (function () {
            var modal = document.getElementById('portal-course-info-modal');
            var titleEl = document.getElementById('portal-course-info-title');
            var slugEl = document.getElementById('portal-course-info-slug');
            var summaryEl = document.getElementById('portal-course-info-summary');
            var footerEl = document.getElementById('portal-course-info-footer');
            if (!modal || !titleEl || !slugEl || !summaryEl || !footerEl) return;

            var lastFocus = null;
            function openModal(card) {
                lastFocus = document.activeElement;
                var title = card.getAttribute('data-course-title') || 'Курс';
                var slug = card.getAttribute('data-course-slug') || '';
                var summary = card.getAttribute('data-course-summary') || '';
                var courseId = card.getAttribute('data-course-id') || '';
                var started = card.getAttribute('data-course-started') === '1';

                titleEl.textContent = title;
                slugEl.innerHTML = slug ? ('slug: <code>' + slug + '</code>') : '';
                summaryEl.textContent = summary;

                footerEl.querySelectorAll('[data-dynamic]').forEach(function (n) { n.remove(); });

                @if (session('learner_id'))
                if (courseId) {
                    var form = document.createElement('form');
                    form.method = 'post';
                    form.action = '{{ route('portal.enroll', ['course' => 0]) }}'.replace(/\/0$/, '/' + courseId);
                    form.setAttribute('data-dynamic', '1');
                    form.style.margin = '0';
                    form.innerHTML = '{!! csrf_field() !!}';
                    var btn = document.createElement('button');
                    btn.type = 'submit';
                    btn.className = 'btn btn-primary';
                    btn.textContent = started ? 'Продолжить' : 'Начать обучение';
                    form.appendChild(btn);
                    footerEl.insertBefore(form, footerEl.firstChild);
                }
                @endif

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('course-modal-open');
                var closeBtn = modal.querySelector('.course-info-modal__close');
                if (closeBtn) closeBtn.focus();
            }
            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('course-modal-open');
                if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
            }
            function shouldIgnore(e) {
                if (!e || !e.target) return false;
                return !!e.target.closest('button, a, form, input, select, textarea, .portal-start-needs-login');
            }
            document.querySelectorAll('.js-course-card').forEach(function (card) {
                card.addEventListener('click', function (e) {
                    if (shouldIgnore(e)) return;
                    openModal(card);
                });
                card.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openModal(card);
                    }
                });
            });
            modal.querySelectorAll('[data-course-info-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('course-info-modal__backdrop')) e.preventDefault();
                    closeModal();
                });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
            });
        })();
    </script>
@endsection

