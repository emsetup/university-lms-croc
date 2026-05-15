@extends('layouts.course')

@section('title', 'Результат теста по теории')

@section('content')
    <div class="page-container">
    <a class="back-link" href="{{ route('modules.hub', $module) }}">
        @include('partials.ap-icon', ['name' => 'arrow-left'])
        <span>К шагам модуля</span>
    </a>
    <div class="card">
        <h1 style="margin-top:0">Модуль {{ $module }}: тест по теории</h1>
        <p>Итог: <strong>{{ $result['final_percent'] ?? '—' }}%</strong>
            (порог {{ $result['threshold'] ?? \App\Services\CourseScoringService::PASS_THRESHOLD }}%)
            @if (!empty($result['passed']))
                <span class="tag" style="margin-left:0.5rem">зачтено</span>
            @endif
        </p>
        <p class="muted">Верно: {{ $result['correct_count'] ?? '—' }} из {{ $result['total'] ?? '—' }}.
            @if (($result['penalty_points'] ?? 0) > 0)
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
        'breakdownTitle' => 'Разбор ответов',
    ])
    </div>
@endsection
