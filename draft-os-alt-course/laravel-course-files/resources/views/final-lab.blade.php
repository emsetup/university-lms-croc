@extends('layouts.course')

@section('title', 'Финальная лабораторная')

@section('content')
    <div class="card">
        <h1 style="margin-top:0">Массовая лабораторная</h1>
        <p class="muted">10 вопросов по всему треку. Порог: {{ \App\Services\CourseScoringService::PASS_THRESHOLD }}%. Попытки учитываются в итоговых баллах.</p>
        @if ($result)
            <p class="muted">Попыток: <strong>{{ $result->attempts }}</strong>, лучший результат: <strong>{{ $result->best_score }}%</strong>
                @if ($result->passed)
                    <span class="badge">принято</span>
                @endif
            </p>
        @endif
    </div>

    <div class="card" style="margin-top:1rem">
        <form method="post" action="{{ route('final-lab.submit') }}">
            @csrf
            @foreach ($questions as $i => $q)
                <div class="quiz-q">
                    <div style="font-weight:600;margin-bottom:0.5rem">{{ $i + 1 }}. {{ $q['q'] }}</div>
                    @foreach ($q['a'] as $j => $opt)
                        <label class="choice">
                            <input type="radio" name="f{{ $i }}" value="{{ $j }}" required>
                            <span>{{ $opt }}</span>
                        </label>
                    @endforeach
                </div>
            @endforeach
            <button type="submit" class="btn btn-primary">Сдать работу</button>
        </form>
    </div>
@endsection
