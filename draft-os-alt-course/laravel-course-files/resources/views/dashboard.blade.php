@extends('layouts.course')

@section('title', 'Модули курса')

@section('content')
    <div class="dashboard-plaques">
        <button type="button" class="dashboard-plaque dashboard-plaque--audience" id="course-audience-open" aria-haspopup="dialog" aria-controls="course-audience-modal">
            <span class="dashboard-plaque__accent" aria-hidden="true"></span>
            <span class="dashboard-plaque__main">
                <span class="dashboard-plaque__kicker">О курсе</span>
                <span class="dashboard-plaque__title">Для кого этот материал</span>
                <span class="dashboard-plaque__hint muted">Практикум по ОС «Альт» для администраторов с опытом Linux (ориентир&nbsp;— уровень <abbr title="Red Hat Certified System Administrator">RHCSA</abbr>). Нажмите, чтобы прочитать полное описание.</span>
            </span>
            <span class="dashboard-plaque__action" aria-hidden="true">Подробнее</span>
        </button>

        <div class="dashboard-plaques__split">
            <section class="dashboard-plaque dashboard-plaque--track card">
                <h1 class="dashboard-plaque__h1">Трек модулей</h1>
                <p class="muted dashboard-plaque__text">Пройдите теорию, тест по теории, практику и итоговый тест в каждом модуле. Ползунок на карточке модуля показывает долю завершённых этапов (по 25% за шаг).</p>
            </section>

            <section class="dashboard-plaque dashboard-plaque--account card" aria-labelledby="dashboard-account-label">
                <div id="dashboard-account-label" class="dashboard-plaque__account-label">Текущий аккаунт</div>
                <div class="dashboard-plaque__email" title="{{ $currentLearner->email }}">{{ $currentLearner->email }}</div>
                <p class="muted dashboard-plaque__account-hint">Прогресс и попытки привязаны к этой почте.</p>
            </section>
        </div>
    </div>

    @include('partials.course-audience-modal')
    @include('partials.dashboard-assessment-modal')

    <section class="learner-track-summary card" aria-label="Общий прогресс по курсу">
        <h2 class="learner-track-summary__title">Ваш прогресс по модулям</h2>
        <p class="muted small learner-track-summary__lead">Модули открываются по очереди: следующий доступен после <strong>зачёта</strong> итогового теста предыдущего или после <strong>попытки сдачи с результатом выше 0%</strong> (ноль процентов — как будто экзамен не сдавали). Ползунок на карточке — четыре шага внутри модуля (по 25%).</p>
        <div class="learner-track-summary__stats">
            <div class="learner-track-stat">
                <span class="learner-track-stat__label">Модулей закрыто</span>
                <span class="learner-track-stat__value">{{ $trackModulesPassed }}<span class="learner-track-stat__of">/{{ $courseModuleCount }}</span></span>
            </div>
            <div class="learner-track-stat">
                <span class="learner-track-stat__label">Средний прогресс по шагам</span>
                <span class="learner-track-stat__value">{{ $trackAvgPercent }}<span class="learner-track-stat__pct">%</span></span>
            </div>
            <div class="learner-track-stat learner-track-stat--wide">
                <span class="learner-track-stat__label">Баллы по модулям (с учётом тестов)</span>
                <span class="learner-track-stat__value">{{ $modulePointsTotal }}<span class="learner-track-stat__of">/{{ $modulePointsMax }}</span></span>
            </div>
        </div>
        <div class="learner-track-summary__bar-wrap" role="presentation">
            <div class="learner-track-summary__bar-label muted small">Средняя заполненность этапов по всем модулям</div>
            <div class="learner-track-summary__bar">
                <div class="learner-track-summary__bar-fill" style="width: {{ min(100, max(0, (int) $trackAvgPercent)) }}%"></div>
            </div>
        </div>
        <div class="learner-track-mini" role="list" aria-label="Кратко по модулям A–{{ $modules[count($modules) - 1]['letter'] ?? '' }}">
            @foreach ($modules as $m)
                @php
                    $cellClass = 'learner-track-mini__cell';
                    if (! empty($m['exam_passed'])) {
                        $cellClass .= ' learner-track-mini__cell--done';
                    } elseif (empty($m['unlocked'])) {
                        $cellClass .= ' learner-track-mini__cell--locked';
                    } elseif ((int) ($m['percent'] ?? 0) > 0) {
                        $cellClass .= ' learner-track-mini__cell--active';
                    } else {
                        $cellClass .= ' learner-track-mini__cell--avail';
                    }
                @endphp
                @if (!empty($m['unlocked']))
                    <a href="{{ route('modules.hub', $m['id']) }}" class="{{ $cellClass }}" role="listitem" title="Модуль {{ $m['id'] }}: {{ $m['title'] }}">
                        <span class="learner-track-mini__letter">{{ $m['letter'] }}</span>
                        <span class="learner-track-mini__n">{{ $m['id'] }}</span>
                        @if (!empty($m['exam_passed']))
                            <span class="learner-track-mini__hint">сдан</span>
                        @elseif ((int) ($m['percent'] ?? 0) >= 100)
                            <span class="learner-track-mini__hint">100%</span>
                        @elseif ((int) ($m['percent'] ?? 0) > 0)
                            <span class="learner-track-mini__hint">{{ (int) $m['percent'] }}%</span>
                        @else
                            <span class="learner-track-mini__hint">открыть</span>
                        @endif
                    </a>
                @else
                    <span class="{{ $cellClass }} learner-track-mini__cell--static" role="listitem" title="Сначала зачтите итоговый тест предыдущего модуля (№{{ max(1, (int) ($m['sequence'] ?? 1) - 1) }}) или сдайте попытку с результатом выше 0%">
                        <span class="learner-track-mini__letter">{{ $m['letter'] }}</span>
                        <span class="learner-track-mini__n">{{ $m['id'] }}</span>
                        <span class="learner-track-mini__hint">закрыт</span>
                    </span>
                @endif
            @endforeach
        </div>
    </section>

    <div class="module-grid">
        @foreach ($modules as $m)
            <div class="module-card {{ empty($m['unlocked']) ? 'module-card--locked' : '' }}">
                <div class="tag">Модуль {{ $m['letter'] }} — {{ (int) ($m['sequence'] ?? $m['id']) }}/{{ $courseModuleCount }}</div>
                <div style="font-weight:700">Модуль {{ (int) ($m['sequence'] ?? $m['id']) }}: {{ $m['title'] }}</div>
                <div class="muted" style="font-size:0.9rem">{{ $m['summary'] }}</div>
                <div class="range-wrap">
                    <label for="rng-{{ $m['id'] }}">Прогресс</label>
                    <input id="rng-{{ $m['id'] }}" type="range" min="0" max="100" value="{{ $m['percent'] }}" class="course-range-readonly" tabindex="-1" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $m['percent'] }}" aria-label="Прогресс модуля {{ $m['id'] }}: {{ (int) $m['percent'] }}%" @if(empty($m['unlocked'])) disabled @endif>
                </div>
                @if (!empty($m['unlocked']))
                    <a class="btn btn-primary" href="{{ route('modules.hub', $m['id']) }}">Открыть модуль</a>
                @else
                    <p class="module-card-lock-note muted small" style="margin:0">Откроется после зачёта итогового теста предыдущего модуля (№{{ max(1, (int) ($m['sequence'] ?? 1) - 1) }}) или после попытки сдачи с ненулевым результатом.</p>
                    <span class="btn btn-primary" style="opacity:0.5;pointer-events:none;cursor:not-allowed" aria-disabled="true">Недоступно</span>
                @endif
            </div>
        @endforeach
    </div>

    <div class="card" style="margin-top:1.25rem">
        <h2 style="margin-top:0">Дальше по курсу</h2>
        <div class="module-grid">
            <div class="module-card">
                <div class="tag">Оценка</div>
                <div style="font-weight:700">Оценка по модулям</div>
                <div class="muted" style="font-size:0.9rem">Сводная аналитика по всем модулям: проценты, баллы и слабые места. Полезно перед финальной лабораторной.</div>
                <div class="range-wrap">
                    <label>Готовность трека</label>
                    <input type="range" min="0" max="100" value="{{ min(100, max(0, (int) $trackAvgPercent)) }}" class="course-range-readonly" tabindex="-1" aria-label="Средний прогресс трека {{ (int) $trackAvgPercent }}%" disabled>
                </div>
                <button type="button" class="btn btn-primary" id="dash-assessment-open" aria-haspopup="dialog" aria-controls="dash-assessment-modal-dialog">Перейти к оценке</button>
            </div>

            <div class="module-card {{ ! $allDone ? 'module-card--locked' : '' }}">
                <div class="tag">Финальная лаба</div>
                <div style="font-weight:700">Практический экзамен</div>
                <div class="muted" style="font-size:0.9rem">Итоговый кейс по всем темам курса: реальная настройка системы по ТЗ. Можно возвращаться и улучшать результат.</div>
                <div class="range-wrap">
                    <label for="rng-final-lab">Прогресс</label>
                    <input id="rng-final-lab" type="range" min="0" max="100" value="{{ min(100, max(0, (int) ($finalLabBestScore ?? 0))) }}" class="course-range-readonly" tabindex="-1" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) ($finalLabBestScore ?? 0) }}" aria-label="Прогресс финальной лабы: {{ (int) ($finalLabBestScore ?? 0) }}%" @if(! $allDone) disabled @endif>
                </div>
                @if (($finalLabAttempts ?? 0) > 0)
                    <p class="module-card-lock-note muted small" style="margin:0">Попыток: {{ (int) $finalLabAttempts }} · лучший результат: {{ (int) ($finalLabBestScore ?? 0) }}%.</p>
                @endif
                @if ($allDone)
                    <a class="btn btn-primary" href="{{ route('final-lab') }}">Открыть финальную лабу</a>
                @else
                    <p class="module-card-lock-note muted small" style="margin:0">Станет доступна после завершения всех модулей.</p>
                    <span class="btn btn-primary" style="opacity:0.5;pointer-events:none;cursor:not-allowed" aria-disabled="true">Недоступно</span>
                @endif
            </div>

            <div class="module-card {{ ! $finalDone ? 'module-card--locked' : '' }}">
                <div class="tag">Итог</div>
                <div style="font-weight:700">Итоговая страница и сертификат</div>
                <div class="muted" style="font-size:0.9rem">Финальная страница завершения обучения. После полного прохождения курса здесь доступен сертификат.</div>
                @if ($finalDone)
                    <a class="btn btn-primary" href="{{ route('certificate') }}">Открыть итоговую страницу</a>
                @else
                    <p class="module-card-lock-note muted small" style="margin:0">Откроется после успешной сдачи финальной лабораторной.</p>
                    <span class="btn btn-primary" style="opacity:0.5;pointer-events:none;cursor:not-allowed" aria-disabled="true">Недоступно</span>
                @endif
            </div>
        </div>
    </div>

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
@endsection
