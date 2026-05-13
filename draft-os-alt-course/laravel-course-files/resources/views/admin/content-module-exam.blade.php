@extends('layouts.course')

@php
    $mTitle = is_array($meta) ? ($meta['title'] ?? ('Модуль '.$module)) : ('Модуль '.$module);
@endphp

@section('title', 'Админ: итоговый тест — модуль '.$module)

@section('content')
    <div style="max-width: 920px; margin: 0 auto">
        @include('partials.admin-instructor-nav', ['active' => 'theory'])
        <div class="card">
        <p class="muted"><a href="{{ route('admin.theory.index') }}">← К сводке курса</a></p>
        <h1 style="margin-top: 0">Модуль {{ $module }}: {{ config('course.step_titles.module_exam') }}</h1>
        <p class="muted small" style="margin-top:0">
            Просмотр для администратора: {{ count($questions) }} вопр.
            · лимит времени для обучающихся: <strong>{{ $timeLimitMinutes }}</strong> мин.
            · порог: <strong>{{ \App\Services\CourseScoringService::PASS_THRESHOLD }}%</strong>
        </p>
        <p class="muted small" style="margin:0 0 1rem">{{ $mTitle }} — верные ответы подсвечены.</p>
        <div class="admin-readonly-quiz">
            @include('admin.partials.quiz-questions-readonly', ['questions' => $questions])
        </div>
        </div>
    </div>
@endsection
