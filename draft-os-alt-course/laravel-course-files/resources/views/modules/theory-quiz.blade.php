@extends('layouts.course')

@php
    $modNum = (int) ($moduleSequence ?? $module);
    $lr = \App\Support\LearnerRoute::hub((int) ($courseId ?? session('course_id')), $modNum);
    $sr = isset($sectionSequence)
        ? \App\Support\LearnerRoute::section((int) ($courseId ?? session('course_id')), $modNum, (int) $sectionSequence)
        : $lr;
    $sectionTitle = isset($section) && (string) $section->title !== ''
        ? (string) $section->title
        : config('course.step_titles.theory_quiz');
    $quizSt = $quizState ?? [];
    $quizAttempts = (int) ($quizSt['attempts'] ?? ($progress->theory_quiz_attempts ?? 0));
    $showScorePercents = $showScorePercents ?? true;
@endphp

@section('title')
Модуль {{ $modNum }}: {{ $sectionTitle }}
@endsection

@section('content')
    <div class="page-container">
    <a class="back-link" href="{{ route('course.module.hub', $lr) }}">
        @include('partials.ap-icon', ['name' => 'arrow-left'])
        <span>К шагам модуля</span>
    </a>

    @if (! empty($previewWalkthrough))
        <div class="impersonation-banner impersonation-banner--course-preview" role="status" style="margin-bottom:1rem;border-radius:8px">
            <span class="impersonation-banner__text">
                <strong>Режим просмотра теста.</strong>
                <span class="muted">Вопросы можно пролистать и посмотреть оформление; ответы и попытки не сохраняются.</span>
            </span>
        </div>

        <div class="card content-protect">
            <h1 class="theory-quiz-page-title" style="margin-top:0">Модуль {{ $modNum }}: {{ $sectionTitle }}</h1>
            <p class="muted">
                @if ($showScorePercents)Порог успеха у обучающихся: {{ $passThreshold ?? \App\Services\CourseScoringService::PASS_THRESHOLD }}%. @endif
                @if ($timeLimitMinutes)Лимит времени: {{ $timeLimitMinutes }} мин.@else Без ограничения по времени.@endif
            </p>

            @foreach ($questions as $i => $q)
                @include('modules.partials.theory-quiz-question', ['i' => $i, 'q' => $q, 'preview' => true, 'inputPrefix' => 'q'])
            @endforeach
            <a class="btn btn-primary" href="{{ route('course.module.hub', $lr) }}">К шагам модуля</a>
        </div>
    @elseif (! $quizActive)
        <dialog class="quiz-modal" id="theory-quiz-intro" open aria-labelledby="theory-quiz-intro-title">
            <div class="quiz-modal-inner">
                <p class="quiz-modal-badge">Модуль {{ $modNum }}@if (! empty($meta['letter'])) · {{ $meta['letter'] }}@endif</p>
                <h2 id="theory-quiz-intro-title" class="quiz-modal-heading">Перед началом проверки</h2>
                <ul class="quiz-modal-list">
                    @if ($timeLimitMinutes)
                        <li>На прохождение отводится <strong>{{ $timeLimitMinutes }} минут</strong> с момента нажатия «Начать тестирование».</li>
                        <li>Таймер отображается сверху; по истечении времени ответы отправятся автоматически (в том числе незаполненные варианты учитываются как ошибки).</li>
                    @else
                        <li>Ограничения по времени <strong>нет</strong> — можете проходить тест в своём темпе.</li>
                    @endif
                    @if ($showScorePercents)
                        <li>Порог успешной сдачи: <strong>{{ $passThreshold ?? \App\Services\CourseScoringService::PASS_THRESHOLD }}%</strong>. Каждая попытка учитывается в итоговой оценке.</li>
                    @else
                        <li>Каждая попытка учитывается в итоговой оценке.</li>
                    @endif
                    @if ($timeLimitMinutes)
                        <li>Закройте посторонние вкладки: возврат к теории в середине попытки укорачивает оставшееся время.</li>
                    @endif
                    @if ($quizAttempts >= 1)
                        @if ($showScorePercents)
                            <li class="quiz-modal-warn"><strong>Повторная попытка:</strong> к сырому проценту прибавляется штраф <strong>-{{ $theoryQuizRetakePenalty ?? \App\Services\CourseScoringService::THEORY_QUIZ_RETAKE_PENALTY_POINTS }}</strong> п.п. Зачёт по модулю и отображаемый лучший результат сброшены до завершения этой попытки.</li>
                        @else
                            <li class="quiz-modal-warn"><strong>Повторная попытка:</strong> зачёт и отображаемый результат обновятся после завершения этой попытки.</li>
                        @endif
                    @endif
                </ul>
                <div class="quiz-modal-actions">
                    <form method="post" action="{{ route('course.module.section.theory-quiz.start', $sr) }}" class="quiz-modal-form">
                        @csrf
                        <button type="submit" class="btn btn-primary">Начать тестирование</button>
                    </form>
                    <a class="quiz-modal-cancel" href="{{ route('course.module.hub', $lr) }}">Вернуться к модулю без начала</a>
                </div>
            </div>
        </dialog>

        <div class="card theory-quiz-idle-card">
            <h1 class="theory-quiz-page-title">Модуль {{ $modNum }}: {{ $sectionTitle }}</h1>
            <p class="muted" style="margin:0">Ознакомьтесь с условиями в окне выше и нажмите «Начать тестирование», когда будете готовы.</p>
        </div>
    @else
        @if ($timeLimitMinutes)
        <div class="quiz-timer-bar" id="quiz-timer-bar" role="status" aria-live="polite">
            <span class="quiz-timer-label">Осталось времени</span>
            <span class="quiz-timer-value" id="quiz-timer-display">--:--</span>
        </div>
        @endif

        <div class="card content-protect" data-integrity-protect>
            <h1 class="theory-quiz-page-title" style="margin-top:0">Модуль {{ $modNum }}: {{ $sectionTitle }}</h1>
            <p class="muted">@if ($showScorePercents)Порог успеха: {{ $passThreshold ?? \App\Services\CourseScoringService::PASS_THRESHOLD }}%. @endifОтветьте на все вопросы и при необходимости проверьте формулировки в теории модуля.</p>

            <form method="post" action="{{ route('course.module.section.theory-quiz.submit', $sr) }}" id="theory-quiz-form">
                @csrf
                @foreach ($questions as $i => $q)
                    @include('modules.partials.theory-quiz-question', ['i' => $i, 'q' => $q, 'preview' => false, 'inputPrefix' => 'q'])
                @endforeach
                <button type="submit" class="btn btn-primary">Отправить ответы</button>
            </form>
        </div>
        @include('partials.assessment-integrity')
        @include('partials.match-reorder-script')

        @if ($expiresAtMs)
            <script>
                (function () {
                    const end = {{ (int) $expiresAtMs }};
                    const form = document.getElementById('theory-quiz-form');
                    const el = document.getElementById('quiz-timer-display');
                    const bar = document.getElementById('quiz-timer-bar');
                    function pad(n) { return n < 10 ? '0' + n : '' + n; }
                    function tick() {
                        var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
                        var m = Math.floor(left / 60), s = left % 60;
                        el.textContent = pad(m) + ':' + pad(s);
                        if (left <= 60 && bar) bar.classList.add('quiz-timer-urgent');
                        if (left <= 0 && form) form.requestSubmit();
                    }
                    tick();
                    setInterval(tick, 250);
                })();
            </script>
        @endif
    @endif
    </div>
@endsection
