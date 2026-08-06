@extends('layouts.course')

@section('title', 'Модули курса')

@section('content')
    <div class="page-container @if ($showInformativeCourseNotice ?? false) page-container--dashboard @endif">
    <div class="dashboard-page__main">
    <div class="dashboard-plaques @if (empty($audiencePlaque)) dashboard-plaques--no-audience @endif">
        @if (! empty($audiencePlaque))
            @if (! empty($audiencePlaque['hasModal']))
                <button type="button" class="dashboard-plaque dashboard-plaque--audience" id="course-audience-open" aria-haspopup="dialog" aria-controls="course-audience-modal">
            @else
                <div class="dashboard-plaque dashboard-plaque--audience dashboard-plaque--static">
            @endif
                <span class="dashboard-plaque__accent" aria-hidden="true"></span>
                <span class="dashboard-plaque__main">
                    <span class="dashboard-plaque__kicker">{{ $audiencePlaque['kicker'] }}</span>
                    <span class="dashboard-plaque__title">{{ $audiencePlaque['title'] }}</span>
                    <span class="dashboard-plaque__hint muted">{!! nl2br(e($audiencePlaque['teaser'])) !!}</span>
                </span>
                @if (! empty($audiencePlaque['hasModal']))
                    <span class="dashboard-plaque__action" aria-hidden="true">Подробнее</span>
                @endif
            @if (! empty($audiencePlaque['hasModal']))
                </button>
            @else
                </div>
            @endif
        @endif

        <div class="dashboard-plaques__split">
            <section class="dashboard-plaque dashboard-plaque--track card">
                <h1 class="dashboard-plaque__h1">Трек модулей</h1>
                @if ($showModuleProgress ?? true)
                    <p class="muted dashboard-plaque__text">Пройдите теорию, тест по теории, практику и итоговый тест в каждом модуле. Ползунок на карточке модуля показывает долю завершённых этапов (по 25% за шаг).</p>
                @else
                    <p class="muted dashboard-plaque__text">Выберите модуль и проходите этапы в удобном порядке. Все модули курса доступны сразу.</p>
                @endif
            </section>

            <section class="dashboard-plaque dashboard-plaque--account card" aria-labelledby="dashboard-account-label">
                <div id="dashboard-account-label" class="dashboard-plaque__account-label">Текущий аккаунт</div>
                <div class="dashboard-plaque__email" title="{{ $currentLearner->email }}">{{ $currentLearner->email }}</div>
                <p class="muted dashboard-plaque__account-hint">Прогресс и попытки привязаны к этой почте.</p>
            </section>
        </div>
    </div>

    @if (! empty($audiencePlaque) && ! empty($audiencePlaque['hasModal']))
        @include('partials.course-audience-modal', ['audiencePlaque' => $audiencePlaque])
    @endif
    @include('partials.dashboard-assessment-modal')

    @if ($showModuleProgress ?? true)
    <section class="learner-track-summary card" aria-label="Общий прогресс по курсу">
        <h2 class="learner-track-summary__title">Ваш прогресс по модулям</h2>
        <p class="muted small learner-track-summary__lead">Модули открываются по очереди: следующий доступен после <strong>зачёта</strong> итогового теста предыдущего или после <strong>попытки сдачи с результатом выше 0%</strong> (ноль процентов — как будто экзамен не сдавали). Ползунок на карточке — четыре шага внутри модуля (по 25%).</p>
        <div class="stats-row portal-welcome-metrics" role="group" aria-label="Сводные показатели">
            <div class="stat-card portal-metric">
                <div class="portal-metric__iconWrap" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                </div>
                <div class="portal-metric__text">
                    <span class="portal-metric__accent" aria-hidden="true"></span>
                    <div class="portal-metric__value stat-value">
                        <span class="stat-value__num">{{ $trackModulesPassed }}</span><span class="stat-value__suffix">/{{ $courseModuleCount }}</span>
                    </div>
                    <div class="portal-metric__label stat-label">Модулей закрыто</div>
                </div>
            </div>
            @if ($showScorePercents ?? true)
            <div class="stat-card portal-metric">
                <div class="portal-metric__iconWrap" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7v5l3 2"/>
                    </svg>
                </div>
                <div class="portal-metric__text">
                    <span class="portal-metric__accent" aria-hidden="true"></span>
                    <div class="portal-metric__value stat-value">{{ $trackAvgPercent }}%</div>
                    <div class="portal-metric__label stat-label">Средний прогресс по шагам</div>
                </div>
            </div>
            @endif
            @if ($showScorePoints ?? true)
            <div class="stat-card portal-metric">
                <div class="portal-metric__iconWrap" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 3h12a2 2 0 0 1 2 2v14l-4-2-4 2-4-2-4 2V5a2 2 0 0 1 2-2z"/>
                        <path d="M8 8h8M8 12h8M8 16h5"/>
                    </svg>
                </div>
                <div class="portal-metric__text">
                    <span class="portal-metric__accent" aria-hidden="true"></span>
                    <div class="portal-metric__value stat-value">
                        <span class="stat-value__num">{{ $modulePointsTotal }}</span><span class="stat-value__suffix">/{{ $modulePointsMax }}</span>
                    </div>
                    <div class="portal-metric__label stat-label">Баллы по модулям</div>
                </div>
            </div>
            @endif
        </div>
        @if ($showScorePercents ?? true)
        <div class="learner-track-summary__bar-wrap" role="presentation">
            <div class="learner-track-summary__bar-label muted small">Средняя заполненность этапов по всем модулям</div>
            <div class="learner-track-summary__bar">
                <div class="learner-track-summary__bar-fill" style="width: {{ min(100, max(0, (int) $trackAvgPercent)) }}%"></div>
            </div>
        </div>
        @endif
        <div class="module-nav" role="list" aria-label="Кратко по модулям A–{{ $modules[count($modules) - 1]['letter'] ?? '' }}">
            @foreach ($modules as $m)
                @php
                    $cellClass = 'module-nav-item';
                    if (! empty($m['exam_passed'])) {
                        $cellClass .= ' done';
                    } elseif (empty($m['unlocked'])) {
                        $cellClass .= ' locked';
                    } elseif ((int) ($m['percent'] ?? 0) > 0) {
                        $cellClass .= ' current';
                    }
                @endphp
                @if (!empty($m['unlocked']))
                    <a href="{{ route('course.module.hub', ['course' => $courseId, 'module' => $m['sequence']]) }}" class="{{ $cellClass }}" role="listitem" title="Модуль {{ (int) ($m['sequence'] ?? 1) }}: {{ $m['title'] }}">
                        {{ (int) ($m['sequence'] ?? 1) }}
                    </a>
                @else
                    <span class="{{ $cellClass }}" role="listitem" title="Сначала зачтите итоговый тест предыдущего модуля (№{{ max(1, (int) ($m['sequence'] ?? 1) - 1) }}) или сдайте попытку с результатом выше 0%">{{ (int) ($m['sequence'] ?? 1) }}</span>
                @endif
            @endforeach
        </div>
    </section>
    @endif

    <div class="module-grid dashboard-modules-grid">
        @foreach ($modules as $m)
            <div class="module-card {{ empty($m['unlocked']) ? 'module-card--locked locked' : '' }}">
                <div class="tag">Модуль {{ (int) ($m['sequence'] ?? $m['id']) }}/{{ $courseModuleCount }}@if (! empty($m['letter'])) · {{ $m['letter'] }}@endif</div>
                <div style="font-weight:700">Модуль {{ (int) ($m['sequence'] ?? $m['id']) }}: {{ $m['title'] }}</div>
                <div class="muted" style="font-size:0.9rem">{{ $m['summary'] }}</div>
                @if ($showModuleProgress ?? true)
                <div>
                    <span class="muted small" style="display:block;margin-bottom:4px">Прогресс</span>
                    <div class="progress-track" aria-hidden="true">
                        <div class="progress-fill" style="width: {{ min(100, max(0, (int) $m['percent'])) }}%"></div>
                    </div>
                    <span class="visually-hidden">Прогресс модуля {{ $m['id'] }}: {{ (int) $m['percent'] }}%</span>
                </div>
                @endif
                @if (!empty($m['unlocked']))
                    <a class="btn btn-primary" href="{{ route('course.module.hub', ['course' => $courseId, 'module' => $m['sequence']]) }}">Открыть модуль</a>
                @else
                    <p class="module-card-lock-note muted small" style="margin:0">Откроется после зачёта итогового теста предыдущего модуля (№{{ max(1, (int) ($m['sequence'] ?? 1) - 1) }}) или после попытки сдачи с ненулевым результатом.</p>
                    <span class="btn btn-module-locked" aria-disabled="true">Недоступно</span>
                @endif
            </div>
        @endforeach
    </div>

    @if ($showFurtherCourseSection ?? true)
    <div class="card" style="margin-top:1.25rem">
        <h2 style="margin-top:0">Дальше по курсу</h2>
        <div class="module-grid">
            @if ($assessmentEnabled ?? true)
            <div class="module-card">
                <div class="tag">Оценка</div>
                <div style="font-weight:700">Оценка по модулям</div>
                <div class="muted" style="font-size:0.9rem">Сводная аналитика по всем модулям: проценты, баллы и слабые места. Полезно перед финальной лабораторной.</div>
                @if ($showModuleProgress ?? true)
                <div>
                    <span class="muted small" style="display:block;margin-bottom:4px">Готовность трека</span>
                    <div class="progress-track" aria-hidden="true">
                        <div class="progress-fill" style="width: {{ min(100, max(0, (int) $trackAvgPercent)) }}%"></div>
                    </div>
                </div>
                @endif
                <button type="button" class="btn btn-primary" id="dash-assessment-open" aria-haspopup="dialog" aria-controls="dash-assessment-modal-dialog">Перейти к оценке</button>
            </div>
            @endif

            @if (($finalLabEnabled ?? true) === true)
                <div class="module-card {{ ! $allDone ? 'module-card--locked locked' : '' }}">
                    <div class="tag">Финальная лаба</div>
                    <div style="font-weight:700">Практический экзамен</div>
                    <div class="muted" style="font-size:0.9rem">Итоговый кейс по всем темам курса: реальная настройка системы по ТЗ. Можно возвращаться и улучшать результат.</div>
                    <div>
                        <span class="muted small" style="display:block;margin-bottom:4px">Прогресс</span>
                        <div class="progress-track" aria-hidden="true">
                            <div class="progress-fill" style="width: {{ min(100, max(0, (int) ($finalLabBestScore ?? 0))) }}%"></div>
                        </div>
                    </div>
                    @if (($finalLabAttempts ?? 0) > 0)
                        <p class="module-card-lock-note muted small" style="margin:0">Попыток: {{ (int) $finalLabAttempts }} · лучший результат: {{ (int) ($finalLabBestScore ?? 0) }}%.</p>
                    @endif
                    @if ($allDone)
                        <a class="btn btn-primary" href="{{ route('final-lab') }}">Открыть финальную лабу</a>
                    @else
                        <p class="module-card-lock-note muted small" style="margin:0">Станет доступна после завершения всех модулей.</p>
                        <span class="btn btn-module-locked" aria-disabled="true">Недоступно</span>
                    @endif
                </div>
            @endif

            @if (($certificateEnabled ?? true) === true)
                <div class="module-card {{ ! $finalDone ? 'module-card--locked locked' : '' }}">
                    <div class="tag">Итог</div>
                    <div style="font-weight:700">Итоговая страница и сертификат</div>
                    <div class="muted" style="font-size:0.9rem">Финальная страница завершения обучения. После полного прохождения курса здесь доступен сертификат.</div>
                    @if ($finalDone)
                        <a class="btn btn-primary" href="{{ route('certificate') }}">Открыть итоговую страницу</a>
                    @else
                        <p class="module-card-lock-note muted small" style="margin:0">Откроется после успешной сдачи финальной лабораторной.</p>
                        <span class="btn btn-module-locked" aria-disabled="true">Недоступно</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
    @endif

    </div>{{-- .dashboard-page__main --}}

    @if ($showInformativeCourseNotice ?? false)
        <footer class="dashboard-page__footer">
            @include('partials.dashboard-informative-notice')
        </footer>
    @endif

    <script>
        (function () {
            var modal = document.getElementById('course-audience-modal-root');
            var openBtn = document.getElementById('course-audience-open');
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
            var modal = document.getElementById('dash-assessment-modal-root');
            var openBtn = document.getElementById('dash-assessment-open');
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
    </div>
@endsection
