@extends('layouts.course')

@php
    $mTitle = is_array($meta) ? ($meta['title'] ?? ('Модуль '.$module)) : ('Модуль '.$module);
@endphp

@section('title', 'Админ: практика — модуль '.$module)

@section('content')
    <div style="max-width: 920px; margin: 0 auto">
        @include('partials.admin-instructor-nav', ['navKey' => $adminKey, 'active' => 'theory'])
        <div class="card">
        <p class="muted"><a href="{{ route('admin.theory.index', ['key' => $adminKey]) }}">← К сводке курса</a></p>
        <h1 style="margin-top: 0">Модуль {{ $module }}: {{ config('course.step_titles.practice') }}</h1>
        <p class="muted small" style="margin-top:0">{{ $mTitle }}</p>
        <p class="muted small" style="margin:0 0 1rem">Текст из объединённого конфига (в т.ч. из <code>require</code> сниппетов). Подсказки в цитатах показаны полностью.</p>
        <article class="theory-article prose-course">
            {!! \Illuminate\Support\Str::markdown($practiceMarkdown) !!}
        </article>
        </div>
    </div>
@endsection
