@extends('layouts.course')

@php
    $quickLinkMode = (bool) ($quickLinkMode ?? false);
    $previewWalkthrough = ! empty($previewWalkthrough);
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
    @unless ($previewWalkthrough)
        <script src="{{ asset('js/survey.js') }}" defer></script>
    @endunless
@endpush

@section('content')
<div class="page-container survey-page @if($quickLinkMode) survey-page--quick @endif @if($previewWalkthrough) survey-page--preview @endif">
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
            @if (session('ok') && ! $previewWalkthrough)
                <div class="survey-thanks">
                    <div class="survey-thanks__icon" aria-hidden="true">✓</div>
                    <h2>Спасибо!</h2>
                    <p class="muted">{{ session('ok') }}</p>
                    @unless ($quickLinkMode)
                    <p class="survey-thanks__link"><a class="btn btn-primary" href="{{ route('course.module.hub', $lr) }}">Вернуться к модулю</a></p>
                    @endunless
                </div>
            @elseif ($previewWalkthrough)
                <div class="impersonation-banner impersonation-banner--course-preview" role="status" style="margin-bottom:1rem;border-radius:8px">
                    <span class="impersonation-banner__text">
                        <strong>Режим просмотра опроса.</strong>
                        <span class="muted">Все вопросы на одной странице; ответы не сохраняются.</span>
                    </span>
                </div>

                @if ($anonymous)
                    <div class="survey-anon-banner" role="note">
                        <span class="survey-anon-banner__icon" aria-hidden="true">◇</span>
                        <span>Анонимный опрос — у обучающихся ответы не привязываются к имени в отчётах.</span>
                    </div>
                @endif

                <div class="survey-shell survey-shell--preview">
                    <div class="survey-flow">
                        <header class="survey-flow__header">
                            <h1 class="survey-flow__title">{{ $section->title }}</h1>
                            <p class="muted" style="margin:0.35rem 0 0">Вопросов: {{ $total }}</p>
                        </header>

                        <div class="survey-flow__steps survey-flow__steps--preview">
                            @foreach ($questions as $i => $q)
                                @include('modules.partials.survey-question', [
                                    'i' => $i,
                                    'q' => $q,
                                    'preview' => true,
                                ])
                            @endforeach
                        </div>

                        @unless ($quickLinkMode)
                        <p style="margin:1.5rem 0 0">
                            <a class="btn btn-primary" href="{{ route('course.module.hub', $lr) }}">К шагам модуля</a>
                        </p>
                        @endunless
                    </div>
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
                                    @include('modules.partials.survey-question', [
                                        'i' => $i,
                                        'q' => $q,
                                        'preview' => false,
                                        'stepHidden' => $i !== 0,
                                    ])
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
