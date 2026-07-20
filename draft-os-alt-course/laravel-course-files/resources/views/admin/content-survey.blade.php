@extends('layouts.admin-preview')

@php
    $mTitle = is_array($meta) ? ($meta['title'] ?? ('Модуль '.$module)) : ('Модуль '.$module);
    $secTitle = (string) (($section->title ?? null) ?: ($meta['section_title'] ?? 'Опрос'));
@endphp

@section('title', 'Админ: опрос — модуль '.$module)

@section('content')
    <div style="max-width: 920px; margin: 0 auto">
        <div class="card">
        <p class="muted"><a href="{{ route('admin.theory.index', $ap ?? []) }}">← К сводке курса</a></p>
        <h1 style="margin-top: 0">Модуль {{ $module }}: {{ $secTitle }}</h1>
        <p class="muted small" style="margin-top:0">
            Просмотр для администратора: {{ count($questions) }} вопр.
            · тип раздела: <strong>опрос</strong> (без оценки и порога прохождения)
        </p>
        <p class="muted small" style="margin:0 0 1rem">{{ $mTitle }} — список вопросов как у обучающегося. Правильные ответы не отмечаются: в опросе их нет.</p>
        <div class="admin-readonly-quiz">
            @include('admin.partials.quiz-questions-readonly', ['questions' => $questions, 'surveyMode' => true])
        </div>
        </div>
    </div>
@endsection
