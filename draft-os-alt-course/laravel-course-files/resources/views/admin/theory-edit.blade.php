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
        <p class="muted small">Файл: <code>config/snippets/{{ $filename }}</code>. Панель вносит разметку Markdown (**жирный**, *курсив*, списки, код). Предпросмотр — вкладка справа в редакторе.</p>

        <form method="post" action="{{ route('admin.theory.update', ['module' => $module]) }}" id="theory-admin-form">
            @csrf
            <label for="theory-md" class="muted small" style="display: block; margin-bottom: 0.35rem">Содержимое (Markdown)</label>
            <textarea id="theory-md" name="markdown" rows="24" spellcheck="false">{{ old('markdown', $markdown) }}</textarea>
            <div style="margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a class="btn btn-ghost" href="{{ route('admin.theory.index', $ap ?? []) }}">Отмена</a>
            </div>
        </form>
        </div>
    </div>

    <link rel="stylesheet" href="{{ asset('vendor/easymde/2.18.0/easymde.min.css') }}">
    <script src="{{ asset('vendor/easymde/2.18.0/easymde.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('theory-md');
            if (!el || typeof EasyMDE === 'undefined') return;
            var mde = new EasyMDE({
                element: el,
                spellChecker: false,
                autosave: { enabled: false },
                status: ['lines', 'words', 'cursor'],
                minHeight: '420px',
                toolbar: [
                    'bold', 'italic', 'heading', '|', 'quote', 'unordered-list', 'ordered-list', '|',
                    'link', 'image', '|', 'code', 'table', '|', 'preview', 'side-by-side', 'fullscreen', '|', 'guide'
                ]
            });
            var form = document.getElementById('theory-admin-form');
            if (form) {
                form.addEventListener('submit', function () {
                    if (mde && typeof mde.value === 'function') {
                        el.value = mde.value();
                    }
                });
            }
        });
    </script>
@endsection
