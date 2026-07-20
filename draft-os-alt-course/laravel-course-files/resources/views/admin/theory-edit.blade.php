@extends('layouts.admin')

@php
    $mTitle = is_array($meta) ? ($meta['title'] ?? ('Модуль '.$module)) : ('Модуль '.$module);
@endphp

@section('title', 'Админ: теория модуля '.$module)

@section('content')
    <div style="max-width: 1100px; margin: 0 auto">
        <div class="card">
        <p class="muted"><a href="{{ route('admin.theory.index', $ap ?? []) }}">← К списку модулей</a></p>
        <h1 style="margin-top: 0">Модуль {{ $module }}: {{ $mTitle }}</h1>
        <p class="muted small">Файл: <code>config/snippets/{{ $filename }}</code>. Плашки, таблицы и картинки — кнопки панели. Предпросмотр — как у обучающегося (вкладка Preview / Side-by-side).</p>

        <form method="post" action="{{ route('admin.theory.update', array_merge($ap ?? [], ['module' => $module])) }}" id="theory-admin-form">
            @csrf
            <label for="theory-md" class="muted small" style="display: block; margin-bottom: 0.35rem">Содержимое (Markdown)</label>
            <p class="cmde-editor-hint">Плашки: <strong>Важно</strong>, <strong>Подсказка</strong>, <strong>Примечание</strong>, <strong>Зачем</strong> — или цитата <code>&gt; **Важно:** …</code></p>
            <textarea id="theory-md" name="markdown" rows="24" spellcheck="false">{{ old('markdown', $markdown) }}</textarea>
            <div style="margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a class="btn btn-ghost" href="{{ route('admin.theory.index', $ap ?? []) }}">Отмена</a>
            </div>
        </form>
        </div>
    </div>

    @include('partials.course-markdown-editor-assets', [
        'cmdeCourseId' => (int) session('admin_course_id'),
        'ap' => $ap ?? [],
    ])
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('theory-md');
            var cfg = window.CourseMarkdownEditorPage || {};
            if (!el || !window.CourseMarkdownEditor) return;
            var handle = window.CourseMarkdownEditor.create(el, {
                courseId: cfg.courseId,
                previewUrl: cfg.previewUrl,
                csrf: cfg.csrf,
                minHeight: '420px',
            });
            var form = document.getElementById('theory-admin-form');
            if (form && handle) {
                form.addEventListener('submit', function () {
                    handle.syncToTextarea();
                });
            }
        });
    </script>
@endsection
