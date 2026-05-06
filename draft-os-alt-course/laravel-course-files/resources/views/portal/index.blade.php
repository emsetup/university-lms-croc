@extends('layouts.course')

@section('title', 'Образовательный портал')

@section('content')
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
                <div class="module-card">
                    <div class="tag">Курс</div>
                    <div style="font-weight:800;font-size:1.05rem;line-height:1.25">{{ $c->title }}</div>
                    <div class="muted" style="font-size:0.92rem;line-height:1.45;margin-top:0.35rem">{{ $c->summary }}</div>
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
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.85rem;align-items:center">
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
    </script>
@endsection

