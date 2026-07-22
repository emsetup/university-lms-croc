@extends('layouts.admin')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/media-library.css') }}">
@endpush

@section('title', 'Контент модуля')

@section('content')
    @php($apNav = \App\Support\AdminNavigation::adminCourseRouteParams())
    <div class="ap-wide-page">
        <div class="admin-card">
            <div class="muted small" style="margin:0 0 0.35rem">
                <a href="{{ route('admin.course.settings', $apNav) }}">Модули</a>
                <span class="muted">/</span>
                <a href="{{ route('admin.course.module.sections', array_merge($apNav, ['courseModule' => $courseModule->id])) }}">{{ $courseModule->title }}</a>
                <span class="muted">/</span>
                Контент (БД)
            </div>
            <h1 style="margin:0">Контент модуля: {{ $courseModule->title }}</h1>
            <p class="muted small" style="margin-top:0.35rem;line-height:1.5">
                Теория и практика в Markdown. Плашки (Важно / Подсказка / …), таблицы, картинки и Mermaid — кнопки панели; предпросмотр совпадает с видом у обучающегося.
            </p>

            @if (session('err'))
                <div class="flash err" style="margin-top:0.75rem">{{ session('err') }}</div>
            @endif
            @if (session('ok'))
                <div class="flash ok" style="margin-top:0.75rem">{{ session('ok') }}</div>
            @endif

            <div class="card-inner" style="margin-top:1rem">
                <div class="icon-strip" style="margin:0 0 0.75rem">
                    <button type="button" class="icon-btn is-active js-cmce-tab" data-tab="theory" title="Теория" aria-label="Теория">
                        <span class="icon-btn__icon">T</span>
                    </button>
                    <button type="button" class="icon-btn js-cmce-tab" data-tab="practice" title="Практика" aria-label="Практика">
                        <span class="icon-btn__icon">P</span>
                    </button>
                </div>

                <form method="post" action="{{ route('admin.course.module.content.update', array_merge($apNav, ['courseModule' => $courseModule->id])) }}" id="cmce-content-form">
                    @csrf

                    <section class="js-cmce-panel" data-panel="theory">
                        <p class="cmde-editor-hint">Заголовки: <strong>Заголовок</strong> / <strong>Подзаголовок</strong>, <strong>≡ Центр</strong> / <strong>≡ | Центр</strong>. Плашки: <strong>Важно</strong> / <strong>Подсказка</strong> / <strong>Примечание</strong> / <strong>Зачем</strong>. Preview — как у студента.</p>
                        <textarea class="input js-cmce-textarea" id="cmce-theory-md" name="theory_markdown" rows="18" spellcheck="false">{{ old('theory_markdown', $theory ?? '') }}</textarea>
                    </section>

                    <section class="js-cmce-panel" data-panel="practice" style="display:none">
                        <p class="cmde-editor-hint">То же форматирование, что у теории (плашки, таблицы, картинки, Mermaid).</p>
                        <textarea class="input js-cmce-textarea" id="cmce-practice-md" name="practice_markdown" rows="18" spellcheck="false">{{ old('practice_markdown', $practice ?? '') }}</textarea>
                    </section>

                    <div style="display:flex;gap:0.5rem;align-items:center;justify-content:space-between;margin-top:1rem">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a class="btn btn-ghost" href="{{ route('admin.theory.index', $apNav) }}">Назад к содержимому</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('partials.course-markdown-editor-assets', [
        'cmdeCourseId' => (int) ($courseModule->course_id ?? session('admin_course_id')),
        'ap' => $apNav,
    ])
    <style>
        .theory-mermaid-wrap { margin: 1rem 0 1.25rem; overflow-x: auto; text-align: center; }
        .theory-mermaid-wrap svg { max-width: 100%; height: auto; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var panels = Array.from(document.querySelectorAll('.js-cmce-panel'));
            var tabs = Array.from(document.querySelectorAll('.js-cmce-tab'));
            var theoryTa = document.getElementById('cmce-theory-md');
            var practiceTa = document.getElementById('cmce-practice-md');
            var cfg = window.CourseMarkdownEditorPage || {};
            if (!panels.length || !tabs.length || !theoryTa || !practiceTa || !window.CourseMarkdownEditor) {
                return;
            }

            var shared = {
                courseId: cfg.courseId,
                previewUrl: cfg.previewUrl,
                csrf: cfg.csrf,
                minHeight: '360px',
            };
            var mdeTheory = window.CourseMarkdownEditor.create(theoryTa, shared);
            var mdePractice = window.CourseMarkdownEditor.create(practiceTa, shared);

            function setActive(tab) {
                tabs.forEach(function (t) {
                    t.classList.toggle('is-active', t.dataset.tab === tab);
                });
                panels.forEach(function (p) {
                    p.style.display = (p.dataset.panel === tab) ? '' : 'none';
                });
                if (tab === 'theory' && mdeTheory) mdeTheory.refresh();
                if (tab === 'practice' && mdePractice) mdePractice.refresh();
            }

            tabs.forEach(function (t) {
                t.addEventListener('click', function () {
                    setActive(t.dataset.tab || 'theory');
                });
            });

            setActive('theory');

            var form = document.getElementById('cmce-content-form');
            if (form) {
                form.addEventListener('submit', function () {
                    if (mdeTheory) mdeTheory.syncToTextarea();
                    if (mdePractice) mdePractice.syncToTextarea();
                });
            }
        });
    </script>
@endsection
