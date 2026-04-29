@extends('layouts.course')

@section('title', 'Оценка по модулям')

@section('content')
    <div class="card" style="max-width: 960px; margin: 0 auto">
        <p class="muted" style="margin:0 0 1rem"><a href="{{ route('dashboard') }}">← К модулям</a></p>
        <h1 style="margin-top:0">Итоговая оценка по модулям</h1>
        <p class="muted" style="margin:0 0 1.25rem;font-size:0.95rem">Учитываются лучшие результаты теста по теории, практики (если есть) и итогового теста; балл за модуль — до {{ \App\Services\CourseScoringService::MAX_POINTS_PER_MODULE }} с весами из правил курса.</p>
        @include('partials.assessment-snapshot-inner', ['assessmentSnapshot' => $assessmentSnapshot, 'allDone' => true])
        <p style="margin:1.25rem 0 0;display:flex;flex-wrap:wrap;gap:0.5rem">
            <a class="btn btn-primary" href="{{ route('final-lab') }}">Финальная лабораторная</a>
            <a class="btn btn-ghost" href="{{ route('dashboard') }}">На дашборд</a>
        </p>
    </div>
@endsection
