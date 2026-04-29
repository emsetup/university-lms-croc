@extends('layouts.course')

@section('title', 'Результат теста по теории')

@section('content')
    <p class="muted"><a href="{{ route('modules.hub', $module) }}">Назад к модулю</a></p>
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
    </div>

    @if (!empty($result['items']) && is_array($result['items']))
        <div class="card" style="margin-top:1rem">
            <h2 style="margin-top:0">Разбор ответов</h2>
            <ul class="muted" style="padding-left:1.1rem">
                @foreach ($result['items'] as $it)
                    <li style="margin-bottom:0.75rem">
                        <strong>Вопрос {{ $it['n'] ?? '' }}.</strong>
                        <div class="module-exam-q--md" style="font-weight:600;margin-top:0.2rem">{!! \Illuminate\Support\Str::markdown($it['question'] ?? '') !!}</div>
                        <br>
                        @if (!empty($it['correct']))
                            <span style="color:var(--croc-600)">Верно</span>
                        @elseif (!empty($it['skipped']))
                            Пропуск
                        @else
                            Ошибка
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
