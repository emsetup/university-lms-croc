@extends('layouts.course')

@section('title')
Модуль {{ $module }}: {{ config('course.step_titles.theory_quiz') }}
@endsection

@section('content')
    <div class="page-container">
    <a class="back-link" href="{{ route('modules.hub', $module) }}">
        @include('partials.ap-icon', ['name' => 'arrow-left'])
        <span>К шагам модуля</span>
    </a>

    @if (! $quizActive)
        <dialog class="quiz-modal" id="theory-quiz-intro" open aria-labelledby="theory-quiz-intro-title">
            <div class="quiz-modal-inner">
                <p class="quiz-modal-badge">Модуль {{ $meta['letter'] }}</p>
                <h2 id="theory-quiz-intro-title" class="quiz-modal-heading">Перед началом проверки</h2>
                <ul class="quiz-modal-list">
                    <li>На прохождение отводится <strong>{{ $timeLimitMinutes }} минут</strong> с момента нажатия «Начать тестирование».</li>
                    <li>Таймер отображается сверху; по истечении времени ответы отправятся автоматически (в том числе незаполненные варианты учитываются как ошибки).</li>
                    <li>Порог успешной сдачи: <strong>{{ $passThreshold ?? \App\Services\CourseScoringService::PASS_THRESHOLD }}%</strong>. Каждая попытка учитывается в итоговой оценке.</li>
                    <li>Закройте посторонние вкладки: возврат к теории в середине попытки укорачивает оставшееся время.</li>
                    @if (($progress->theory_quiz_attempts ?? 0) >= 1)
                        <li class="quiz-modal-warn"><strong>Повторная попытка:</strong> к сырому проценту прибавляется штраф <strong>-{{ $theoryQuizRetakePenalty ?? \App\Services\CourseScoringService::THEORY_QUIZ_RETAKE_PENALTY_POINTS }}</strong> п.п. Зачёт по модулю и отображаемый лучший результат сброшены до завершения этой попытки.</li>
                    @endif
                </ul>
                <div class="quiz-modal-actions">
                    <form method="post" action="{{ route('modules.theory-quiz.start', $module) }}" class="quiz-modal-form">
                        @csrf
                        <button type="submit" class="btn btn-primary">Начать тестирование</button>
                    </form>
                    <a class="quiz-modal-cancel" href="{{ route('modules.hub', $module) }}">Вернуться к модулю без начала</a>
                </div>
            </div>
        </dialog>

        <div class="card theory-quiz-idle-card">
            <h1 class="theory-quiz-page-title">Модуль {{ $module }}: {{ config('course.step_titles.theory_quiz') }}</h1>
            <p class="muted" style="margin:0">Ознакомьтесь с условиями в окне выше и нажмите «Начать тестирование», когда будете готовы.</p>
        </div>
    @else
        <div class="quiz-timer-bar" id="quiz-timer-bar" role="status" aria-live="polite">
            <span class="quiz-timer-label">Осталось времени</span>
            <span class="quiz-timer-value" id="quiz-timer-display">--:--</span>
        </div>

        <div class="card content-protect" data-integrity-protect>
            <h1 class="theory-quiz-page-title" style="margin-top:0">Модуль {{ $module }}: {{ config('course.step_titles.theory_quiz') }}</h1>
            <p class="muted">Порог успеха: {{ $passThreshold ?? \App\Services\CourseScoringService::PASS_THRESHOLD }}%. Ответьте на все вопросы и при необходимости проверьте формулировки в теории модуля.</p>
            <p class="muted small content-protect-hint">Текст заданий нельзя копировать; при переключении на другую вкладку формулировки скрываются. Скриншот средствами ОС технически не блокируется.</p>

            <form method="post" action="{{ route('modules.theory-quiz.submit', $module) }}" id="theory-quiz-form">
                @csrf
                @foreach ($questions as $i => $q)
                    @php $multi = isset($q['c']) && is_array($q['c']); @endphp
                    <div class="quiz-q">
                        <div class="quiz-q-text quiz-q-text--md"><span class="quiz-q-num">{{ $i + 1 }}.</span> {!! \Illuminate\Support\Str::markdown($q['q']) !!}</div>
                        @if ($multi)
                            <p class="muted small" style="margin:0.35rem 0 0.5rem">Отметьте все верные варианты.</p>
                            @foreach ($q['a'] as $j => $opt)
                                <label class="choice">
                                    <input type="checkbox" name="q{{ $i }}[]" value="{{ $j }}">
                                    <span>{{ $opt }}</span>
                                </label>
                            @endforeach
                        @else
                            @foreach ($q['a'] as $j => $opt)
                                <label class="choice">
                                    <input type="radio" name="q{{ $i }}" value="{{ $j }}" @if ($loop->first) required @endif>
                                    <span>{{ $opt }}</span>
                                </label>
                            @endforeach
                        @endif
                    </div>
                @endforeach
                <button type="submit" class="btn btn-primary">Отправить ответы</button>
            </form>
        </div>
        @include('partials.assessment-integrity')

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
