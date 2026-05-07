@extends('layouts.course')

@section('title', 'Образовательный портал')

@section('content')
    <style>
        .portal-course-card {
            cursor: pointer;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .portal-course-title { min-height: 2.7rem; }
        .portal-course-summary {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 5;
            overflow: hidden;
        }
        .portal-course-grow { flex: 1; min-height: 0; }
        .portal-course-actions { margin-top: auto; }

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
                <p class="muted" style="margin:0;max-width:60rem;line-height:1.5">
                    Выберите курс и начните обучение. Прогресс и попытки привязаны к вашей корпоративной почте.
                </p>
            </div>
            @if (! session('learner_id'))
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                    <button type="button" class="btn btn-primary" id="portal-login-open">Войти</button>
                </div>
            @endif
        </div>
    </div>

    <div class="card" style="max-width:1100px;margin:0 auto">
        <h2 style="margin-top:0">Доступные курсы</h2>
        <div class="module-grid" style="margin-top:0.75rem">
            @foreach ($courses as $c)
                @php
                    $enroll = $enrollmentsByCourseId[$c->id] ?? null;
                    $started = $enroll && !empty($enroll->started_at);
                    $pct = (int) ($progressByCourseId[$c->id] ?? 0);
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
                        <div class="muted portal-course-summary" style="font-size:0.92rem;line-height:1.45;margin-top:0.35rem">{{ $c->summary }}</div>
                    </div>
                    @if ($c->slug === 'alt-os-features')
                        <div style="margin-top:0.65rem">
                            <button type="button" class="btn btn-ghost" id="portal-course-audience-open">Подробнее: для кого этот курс</button>
                        </div>
                    @endif

                    @if (session('learner_id') && $started)
                        <div style="margin-top:0.85rem">
                            <div class="muted small" style="margin:0 0 0.35rem">Прогресс по курсу: <strong>{{ $pct }}%</strong></div>
                            <div class="learner-track-summary__bar" aria-hidden="true" style="height:10px">
                                <div class="learner-track-summary__bar-fill" style="width: {{ min(100, max(0, $pct)) }}%"></div>
                            </div>
                            <div class="muted small" style="margin-top:0.35rem">
                                Начато: <strong>{{ optional($enroll->started_at)->format('d.m.Y H:i') }}</strong>
                            </div>
                        </div>
                    @endif
                    <div class="portal-course-actions" style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.85rem;align-items:center">
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
                <p class="muted" style="margin:0">Курсы пока не добавлены.</p>
            @endif
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

