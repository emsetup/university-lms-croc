@extends('layouts.admin')

@section('title', 'Ответы опроса — '.$section->title)

@section('content')
    @php
        $tp = ['adminCourse' => $course->slug];
    @endphp
    <div class="admin-content">
        <p><a href="{{ route('admin.course.settings', $tp) }}#ap-mod-{{ $module->id }}">← К модулю</a></p>
        <h1 class="ap-page-title">Ответы: {{ $section->title }}</h1>
        <p class="ap-muted">Модуль {{ $module->title }} · {{ $anonymous ? 'анонимный режим' : 'с привязкой к обучающимся' }}</p>
        <p>
            <a class="btn btn-primary" href="{{ route('admin.course.module.section.survey-responses.export', array_merge($tp, ['courseModule' => $module->id, 'section' => $section->id])) }}">Скачать CSV</a>
            <a class="btn btn-ghost" href="{{ route('admin.course.module.section.participants', array_merge($tp, ['courseModule' => $module->id, 'section' => $section->id])) }}">Участники</a>
        </p>
        @if (count($rows) === 0)
            <p class="ap-muted">Пока нет отправленных ответов.</p>
        @else
            <div class="ap-table-wrap" style="overflow:auto;margin-top:1rem">
                <table class="ap-table">
                    <thead>
                        <tr>
                            @foreach ($columns as $col)
                                <th>{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($columns as $col)
                                    <td>{{ $row[$col] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
