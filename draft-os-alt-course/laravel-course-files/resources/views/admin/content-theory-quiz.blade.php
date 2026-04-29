@extends('layouts.course')

@php
    $mTitle = is_array($meta) ? ($meta['title'] ?? ('Модуль '.$module)) : ('Модуль '.$module);
@endphp

@section('title', 'Админ: тест по теории — модуль '.$module)

@section('content')
    <div class="card" style="max-width: 920px; margin: 0 auto">
        <p class="muted"><a href="{{ route('admin.theory.index', ['key' => $adminKey]) }}">← К сводке курса</a></p>
        <h1 style="margin-top: 0">Модуль {{ $module }}: {{ config('course.step_titles.theory_quiz') }}</h1>
        <p class="muted small" style="margin-top:0">
            Просмотр для администратора: {{ count($questions) }} вопр.
            · лимит времени для студентов: <strong>{{ \App\Services\CourseScoringService::THEORY_QUIZ_TIME_LIMIT_MINUTES }}</strong> мин.
            · порог: <strong>{{ \App\Services\CourseScoringService::PASS_THRESHOLD }}%</strong>
        </p>
        <p class="muted small" style="margin:0 0 1rem">Ниже отмечены верные варианты так, как они заданы в <code>config/course.php</code> / сниппетах.</p>
        <div class="admin-readonly-quiz">
            @include('admin.partials.quiz-questions-readonly', ['questions' => $questions])
        </div>
    </div>
@endsection
