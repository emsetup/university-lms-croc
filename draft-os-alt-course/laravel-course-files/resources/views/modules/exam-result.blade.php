@extends('layouts.course')

@section('title', 'Результат итогового теста')

@section('content')
    <div class="page-container">
    <a class="back-link" href="{{ route('modules.hub', $module) }}">
        @include('partials.ap-icon', ['name' => 'arrow-left'])
        <span>К шагам модуля</span>
    </a>
    <div class="card">
        <h1 style="margin-top:0">Модуль {{ $module }}: итоговый тест</h1>
        <p>Результат попытки: <strong>{{ $r['final_percent'] ?? '—' }}%</strong>
            (порог {{ $r['threshold'] ?? \App\Services\CourseScoringService::PASS_THRESHOLD }}%)
            @if (!empty($r['passed']))
                <span class="tag" style="margin-left:0.5rem">модуль зачтён по правилам курса</span>
            @endif
        </p>
        <p class="muted">Верно: {{ $r['correct_count'] ?? '—' }} из {{ $r['total'] ?? '—' }}.
            @if (!empty($r['max_points']))
                Баллы: {{ $r['earned_points'] ?? '—' }} / {{ $r['max_points'] }}.
            @endif
        </p>
        @if (!empty($breakdownExpired))
            <p class="muted">Окно просмотра разбора по вопросам истекло — вернуться к списку вопросов нельзя.</p>
        @endif
    </div>

    @include('partials.learner-quiz-breakdown-wrong', [
        'showBreakdown' => $showExamBreakdown ?? false,
        'wrongItems' => $wrongItems ?? [],
        'breakdownUntilTs' => $breakdownUntilTs ?? null,
        'breakdownTitle' => 'Где ошиблись — кратко',
    ])
    </div>
@endsection
