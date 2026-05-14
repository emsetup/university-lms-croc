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
            <p class="muted">Окно просмотра разбора по вопросам истекло.</p>
        @endif
    </div>

    @if (!empty($showExamBreakdown) && !empty($r['items']) && is_array($r['items']))
        <div class="card" style="margin-top:1rem">
            <h2 style="margin-top:0">Где ошиблись — кратко</h2>
            <p class="muted small">Показаны только вопросы с ошибкой или без ответа.</p>
            @php $any = false; @endphp
            <ul class="muted" style="padding-left:1.1rem">
                @foreach ($r['items'] as $it)
                    @if (empty($it['correct']) || !empty($it['skipped']))
                        @php $any = true; @endphp
                        <li style="margin-bottom:0.75rem">
                            <strong>Вопрос {{ $it['n'] ?? '' }}.</strong>
                            <div class="module-exam-q--md" style="font-weight:600;margin-top:0.2rem">{!! \Illuminate\Support\Str::markdown($it['question'] ?? '') !!}</div>
                        </li>
                    @endif
                @endforeach
            </ul>
            @if (!$any)
                <p class="muted">В этой попытке ошибок для разбора нет — все ответы зачтены.</p>
            @endif
        </div>
    @endif
    </div>
@endsection
