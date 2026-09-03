@extends('layouts.course')

@section('title', 'Результат теста по теории')

@section('content')
    <div class="page-container">
    @php
        $modNum = (int) ($moduleSequence ?? $module);
        $lr = \App\Support\LearnerRoute::hub((int) ($courseId ?? session('course_id')), $modNum);
        $sectionTitle = isset($section) && (string) $section->title !== ''
            ? (string) $section->title
            : 'тест по теории';
        $showScorePercents = $showScorePercents ?? true;
    @endphp
    <a class="back-link" href="{{ route('course.module.hub', $lr) }}">
        @include('partials.ap-icon', ['name' => 'arrow-left'])
        <span>К шагам модуля</span>
    </a>
    <div class="card">
        <h1 style="margin-top:0">Модуль {{ $modNum }}: {{ $sectionTitle }}</h1>
        @if ($showScorePercents)
            <p>Итог: <strong>{{ $result['final_percent'] ?? '—' }}%</strong>
                (порог {{ $result['threshold'] ?? \App\Services\CourseScoringService::PASS_THRESHOLD }}%)
                @if (!empty($result['passed']))
                    <span class="tag" style="margin-left:0.5rem">зачтено</span>
                @endif
            </p>
        @else
            <p>Итог:
                @if (!empty($result['passed']))
                    <span class="tag">зачтено</span>
                @else
                    <span class="tag">не зачтено</span>
                @endif
            </p>
        @endif
        <p class="muted">Верно: {{ $result['correct_count'] ?? '—' }} из {{ $result['total'] ?? '—' }}.
            @if ($showScorePercents && ($result['penalty_points'] ?? 0) > 0)
                Штраф за пересдачу: −{{ $result['penalty_points'] }} п.п.
            @endif
        </p>
        @if (!empty($breakdownExpired))
            <p class="muted">Окно просмотра разбора по вопросам истекло — вернуться к списку вопросов нельзя.</p>
        @endif
    </div>

    @include('partials.learner-quiz-breakdown-wrong', [
        'showBreakdown' => $showBreakdown ?? false,
        'wrongItems' => $wrongItems ?? [],
        'breakdownUntilTs' => $breakdownUntilTs ?? null,
        'breakdownMode' => $breakdownMode ?? 'all',
        'breakdownTitle' => 'Разбор ответов',
    ])
    </div>
@endsection
