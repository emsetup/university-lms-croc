@extends('layouts.course')

@section('title', 'Админ: просмотр теории — модуль '.$module)

@section('content')
    @php($isReadOnly = (bool) ($isReadOnly ?? false))
    <div style="max-width: 1000px; margin: 0 auto">
        @if (! $isReadOnly)
            @include('partials.admin-instructor-nav', ['navKey' => $adminKey, 'active' => 'theory'])
        @endif
        <div class="card">
            <p class="muted" style="margin:0 0 0.75rem">
                <a href="{{ route('admin.theory.index', ['key' => $adminKey]) }}">← К содержимому курса</a>
            </p>
            <p class="muted small" style="margin:0 0 1rem">Так же отображается теория у обучающегося (без кнопки «отмечено просмотрено»). Диаграммы Mermaid подгружаются из CDN.</p>
            @php
                $theoryRaw = (string) ($meta['theory'] ?? '');
            @endphp
            <article class="theory-article prose-course practice-block">
                {!! \Illuminate\Support\Str::markdown($theoryRaw) !!}
            </article>

            <style>
                .theory-mermaid-wrap { margin: 1rem 0 1.25rem; overflow-x: auto; text-align: center; }
                .theory-mermaid-wrap svg { max-width: 100%; height: auto; }
            </style>
            <script type="module">
                (async function () {
                    var codes = document.querySelectorAll('.theory-article pre code.language-mermaid');
                    if (!codes.length) {
                        return;
                    }
                    var mer = (await import('https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs')).default;
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
                        var id = 'mermaid-admin-theory-' + i + '-' + Math.random().toString(36).slice(2, 9);
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
        </div>
    </div>
@endsection
