@extends('layouts.course')

@php
    $quickLinkMode = (bool) ($quickLinkMode ?? false);
    $modNum = (int) ($moduleSequence ?? 1);
    $secNum = (int) ($sectionSequence ?? 1);
    $lr = \App\Support\LearnerRoute::hub((int) ($courseId ?? session('course_id')), $modNum);
    $surveyParams = \App\Support\LearnerRoute::section((int) ($courseId ?? session('course_id')), $modNum, $secNum);
    $total = count($questions);
    $formAction = $quickLinkMode
        ? route('survey.quick.submit', ['token' => $quickLinkToken ?? ''])
        : route('course.module.section.survey.submit', $surveyParams);
@endphp

@section('title')
@if ($quickLinkMode)
{{ $section->title }}
@else
Модуль {{ $modNum }} · Раздел {{ $secNum }}: {{ $section->title }}
@endif
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('css/survey.css') }}">
    <script src="{{ asset('js/survey.js') }}" defer></script>
@endpush

@section('content')
<div class="page-container survey-page @if($quickLinkMode) survey-page--quick @endif">
    <div class="survey-stage">
        @unless ($quickLinkMode)
        <div class="survey-stage__chrome">
            <a class="back-link survey-stage__back" href="{{ route('course.module.hub', $lr) }}">
                @include('partials.ap-icon', ['name' => 'arrow-left'])
                <span>К шагам модуля</span>
            </a>
        </div>
        @endunless

        <div class="survey-stage__body">
            @if (session('ok'))
                <div class="survey-thanks">
                    <div class="survey-thanks__icon" aria-hidden="true">✓</div>
                    <h2>Спасибо!</h2>
                    <p class="muted">{{ session('ok') }}</p>
                    @unless ($quickLinkMode)
                    <p class="survey-thanks__link"><a class="btn btn-primary" href="{{ route('course.module.hub', $lr) }}">Вернуться к модулю</a></p>
                    @endunless
                </div>
            @elseif ($submitted)
                <div class="survey-thanks">
                    <div class="survey-thanks__icon survey-thanks__icon--muted" aria-hidden="true">✓</div>
                    <h2>Ответы уже отправлены</h2>
                    <p class="muted">Повторная отправка для этого опроса недоступна.</p>
                    @unless ($quickLinkMode)
                    <p class="survey-thanks__link"><a class="btn btn-primary" href="{{ route('course.module.hub', $lr) }}">Вернуться к модулю</a></p>
                    @endunless
                </div>
            @else
                @if ($anonymous)
                    <div class="survey-anon-banner" id="survey-anon-banner" role="note">
                        <span class="survey-anon-banner__icon" aria-hidden="true">◇</span>
                        <span>Анонимный опрос — ответы не привязываются к имени в отчётах.</span>
                    </div>
                @endif

                @if (session('err'))
                    <p class="survey-err">{{ session('err') }}</p>
                @endif

                <form
                    method="post"
                    action="{{ $formAction }}"
                    class="survey-form"
                    id="survey-form"
                    data-total="{{ $total }}"
                    novalidate
                >
                    @csrf

                    <div class="survey-shell">
                        <div class="survey-flow" id="survey-flow">
                            <header class="survey-flow__header" id="survey-hero">
                                <h1 class="survey-flow__title">{{ $section->title }}</h1>
                            </header>

                            <div class="survey-flow__progress" aria-live="polite">
                                <div class="survey-flow__progress-bar" aria-hidden="true">
                                    <div class="survey-flow__progress-bar__fill" id="survey-progress-fill" style="width: {{ $total > 0 ? round(100 / $total) : 0 }}%"></div>
                                </div>
                                <span class="survey-flow__counter" id="survey-progress-counter">{{ $total > 0 ? '1' : '0' }} / {{ $total }}</span>
                            </div>

                            <div class="survey-flow__steps">
                                @foreach ($questions as $i => $q)
                                    <div class="survey-step" data-step="{{ $i }}" @if ($i !== 0) hidden @endif role="group" aria-label="Вопрос {{ $i + 1 }}">
                                        <div class="survey-step__head">
                                            <span class="survey-step__badge">{{ $i + 1 }}</span>
                                            <div class="survey-step__text">{!! \Illuminate\Support\Str::markdown($q['q']) !!}</div>
                                        </div>

                                        <div class="survey-step__body">
                                            @if (!empty($q['open_text']))
                                                <label class="survey-input-line-wrap" for="survey-q{{ $i }}-input">
                                                    <textarea
                                                        id="survey-q{{ $i }}-input"
                                                        class="survey-input-line js-survey-input"
                                                        name="q{{ $i }}"
                                                        rows="1"
                                                        data-q="{{ $i }}"
                                                        @if(!empty($q['max_length'])) maxlength="{{ (int) $q['max_length'] }}" @endif
                                                        required
                                                    >{{ old('q'.$i) }}</textarea>
                                                    <span class="survey-input-line-placeholder">{{ $q['placeholder'] ?? 'Напишите ответ здесь…' }}</span>
                                                </label>
                                                <p class="survey-step__hint">Чтобы добавить абзац, нажмите <kbd>Shift</kbd> + <kbd>Enter</kbd></p>
                                            @elseif (!empty($q['match_drag']))
                                                @php $left = $q['left'] ?? []; $right = $q['right'] ?? []; @endphp
                                                <input type="hidden" name="q{{ $i }}_order" id="survey-order-{{ $i }}" value="">
                                                @foreach ($left as $li => $ltxt)
                                                    <div class="survey-match-row">
                                                        <span class="survey-match-row__label">{{ $ltxt }}</span>
                                                        <select class="survey-match-select js-survey-input" data-q="{{ $i }}" required>
                                                            <option value="">— выберите —</option>
                                                            @foreach ($right as $ri => $rtxt)
                                                                <option value="{{ $ri }}">{{ $rtxt }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @endforeach
                                            @else
                                                @php $multi = isset($q['c']) && is_array($q['c']); @endphp
                                                @if ($multi)
                                                    <p class="survey-step__hint">Можно выбрать несколько вариантов</p>
                                                @endif
                                                <div class="survey-options" role="{{ $multi ? 'group' : 'radiogroup' }}">
                                                    @foreach ($q['a'] ?? [] as $j => $opt)
                                                        <label class="survey-option">
                                                            @if ($multi)
                                                                <input type="checkbox" class="js-survey-input" name="q{{ $i }}[]" value="{{ $j }}" data-q="{{ $i }}">
                                                            @else
                                                                <input type="radio" class="js-survey-input" name="q{{ $i }}" value="{{ $j }}" data-q="{{ $i }}" @if($loop->first) required @endif>
                                                            @endif
                                                            <span class="survey-option__letter">{{ chr(65 + $j) }}</span>
                                                            <span class="survey-option__text">{{ $opt }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="survey-flow__nav">
                                <div class="survey-flow__nav-main">
                                    <button type="button" class="survey-flow__back" id="survey-prev" disabled>Назад</button>
                                    <button type="button" class="survey-flow__cta btn btn-primary" id="survey-next">
                                        <span>Далее</span>
                                        <span class="survey-flow__cta-arrow" aria-hidden="true">→</span>
                                    </button>
                                    <button type="submit" class="survey-flow__cta btn btn-primary" id="survey-submit" hidden>
                                        <span>Отправить</span>
                                        <span class="survey-flow__cta-arrow" aria-hidden="true">✓</span>
                                    </button>
                                </div>
                                <span class="survey-flow__kbd-hint">нажмите <kbd>Ctrl</kbd> + <kbd>Enter</kbd></span>
                            </div>
                        </div>

                        <div class="survey-flow__arrows" aria-label="Навигация по вопросам">
                            <button type="button" class="survey-flow__arrow" id="survey-arrow-up" title="Предыдущий вопрос" aria-label="Предыдущий вопрос" disabled>↑</button>
                            <button type="button" class="survey-flow__arrow" id="survey-arrow-down" title="Следующий вопрос" aria-label="Следующий вопрос">↓</button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
