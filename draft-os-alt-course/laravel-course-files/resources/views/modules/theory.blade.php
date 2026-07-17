@extends('layouts.course')

@php
    $st = config('course.step_titles', []);
    $tTheory = $st['theory'] ?? 'Теория';
    $modNum = (int) ($moduleSequence ?? $module);
    $sectionTitle = (string) ($meta['section_title'] ?? $tTheory);
    $lr = \App\Support\LearnerRoute::hub((int) ($courseId ?? session('course_id')), $modNum);
    $sr = isset($sectionSequence)
        ? \App\Support\LearnerRoute::section((int) ($courseId ?? session('course_id')), $modNum, (int) $sectionSequence)
        : $lr;
@endphp

@section('title', 'Модуль '.$modNum.': '.($meta['title'] ?? $tTheory))

@section('content')
    <div class="page-container">
        <a class="back-link" href="{{ route('course.module.hub', $lr) }}">
            @include('partials.ap-icon', ['name' => 'arrow-left'])
            <span>К шагам модуля</span>
        </a>

        <header class="card module-step-header">
            <div class="tag module-step-header__badge">Модуль {{ $modNum }}@if (! empty($meta['letter'])) · {{ $meta['letter'] }}@endif</div>
            <h1 class="module-step-page-title">Модуль {{ $modNum }}: {{ $meta['title'] ?? 'Без названия' }}</h1>
            <p class="muted module-step-header__step">{{ $sectionTitle }}</p>
        </header>

        @php
            $theoryRaw = (string) ($meta['theory'] ?? '');
        @endphp
        <article class="theory-article prose-course practice-block theory-content">
            {!! \App\Support\CourseContentMarkdown::toHtml($theoryRaw) !!}
        </article>

        <style>
            .theory-mermaid-wrap { margin: 1rem 0 1.25rem; overflow-x: auto; text-align: center; }
            .theory-mermaid-wrap svg { max-width: 100%; height: auto; }
        </style>
        @include('partials.vendor-mermaid-importmap')
        <script type="module">
            (async function () {
                var codes = document.querySelectorAll('.theory-article pre code.language-mermaid');
                if (!codes.length) {
                    return;
                }
                var mer = (await import('mermaid')).default;
                mer.initialize({
                    startOnLoad: false,
                    theme: 'base',
                    securityLevel: 'strict',
                    fontFamily: 'Manrope, system-ui, sans-serif',
                    flowchart: { curve: 'basis', padding: 12 },
                });
                for (var i = 0; i < codes.length; i++) {
                    var code = codes[i];
                    var pre = code.parentElement;
                    if (!pre || pre.tagName !== 'PRE') {
                        continue;
                    }
                    var graph = (code.textContent || '').trim();
                    if (!graph) {
                        continue;
                    }
                    var id = 'mermaid-theory-' + i + '-' + Math.random().toString(36).slice(2, 9);
                    try {
                        var out = await mer.render(id, graph);
                        var wrap = document.createElement('div');
                        wrap.className = 'theory-mermaid-wrap';
                        wrap.innerHTML = out.svg;
                        pre.replaceWith(wrap);
                    } catch (e) {
                        console.warn('mermaid', e);
                    }
                }
            })();
        </script>

        <form method="post" action="{{ route('course.module.section.theory.read', $sr) }}" style="margin-top: 1.5rem">
            @csrf
            <button type="submit" class="btn btn-primary">Отметить теорию как просмотренную</button>
        </form>
    </div>
@endsection
