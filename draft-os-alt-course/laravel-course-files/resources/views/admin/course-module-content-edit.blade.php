@extends('layouts.admin')

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
                Теория и практика хранятся в БД и изолированы по курсу. Markdown поддерживает Mermaid-блоки как у обучающегося.
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
                    <button type="button" class="icon-btn js-cmce-tab" data-tab="preview" title="Предпросмотр" aria-label="Предпросмотр">
                        <span class="icon-btn__icon" aria-hidden="true">@include('partials.ap-icon', ['name' => 'eye', 'size' => 'md'])</span>
                    </button>
                </div>

                <form method="post" action="{{ route('admin.course.module.content.update', array_merge($apNav, ['courseModule' => $courseModule->id])) }}">
                    @csrf

                    <section class="js-cmce-panel" data-panel="theory">
                        <div class="muted small" style="margin:0 0 0.35rem">Теория (Markdown)</div>
                        <textarea class="input js-cmce-textarea" name="theory_markdown" rows="18" spellcheck="false">{{ old('theory_markdown', $theory ?? '') }}</textarea>
                    </section>

                    <section class="js-cmce-panel" data-panel="practice" style="display:none">
                        <div class="muted small" style="margin:0 0 0.35rem">Практика (Markdown)</div>
                        <textarea class="input js-cmce-textarea" name="practice_markdown" rows="18" spellcheck="false">{{ old('practice_markdown', $practice ?? '') }}</textarea>
                    </section>

                    <section class="js-cmce-panel" data-panel="preview" style="display:none">
                        <div class="muted small" style="margin:0 0 0.5rem">Предпросмотр</div>
                        <div class="card" style="margin:0;padding:0.9rem;border:1px solid var(--line,#e5e7eb);background:var(--surface,#fff)">
                            <article class="prose-course practice-block" id="cmce-preview" style="max-width:none"></article>
                        </div>
                        <p class="muted small" style="margin:0.5rem 0 0">Подсказка: Mermaid-блоки рендерятся после открытия вкладки предпросмотра.</p>
                    </section>

                    <div style="display:flex;gap:0.5rem;align-items:center;justify-content:space-between;margin-top:1rem">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a class="btn btn-ghost" href="{{ route('admin.theory.index', $apNav) }}">Назад к содержимому</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="{{ asset('vendor/easymde/2.18.0/easymde.min.css') }}">
    <style>
        .theory-mermaid-wrap { margin: 1rem 0 1.25rem; overflow-x: auto; text-align: center; }
        .theory-mermaid-wrap svg { max-width: 100%; height: auto; }
    </style>
    <script src="{{ asset('vendor/easymde/2.18.0/easymde.min.js') }}"></script>
    <script src="{{ asset('vendor/marked/12.0.2/marked.min.js') }}"></script>
    @include('partials.vendor-mermaid-importmap')
    <script type="module">
        (async function () {
            var panels = Array.from(document.querySelectorAll('.js-cmce-panel'));
            var tabs = Array.from(document.querySelectorAll('.js-cmce-tab'));
            var previewEl = document.getElementById('cmce-preview');
            var theoryTa = document.querySelector('textarea[name="theory_markdown"]');
            var practiceTa = document.querySelector('textarea[name="practice_markdown"]');
            if (!panels.length || !tabs.length || !previewEl || !theoryTa || !practiceTa || typeof EasyMDE === 'undefined' || typeof marked === 'undefined') {
                return;
            }

            var lastEditTab = 'theory';
            var mdeOpts = {
                spellChecker: false,
                autosave: { enabled: false },
                status: ['lines', 'words'],
                minHeight: '360px',
            };
            var mdeTheory = new EasyMDE(Object.assign({}, mdeOpts, { element: theoryTa }));
            var mdePractice = new EasyMDE(Object.assign({}, mdeOpts, { element: practiceTa }));

            var mermaidMod = null;

            function markdownForPreview() {
                var ta = lastEditTab === 'practice' ? practiceTa : theoryTa;
                return ta ? (ta.value || '') : '';
            }

            function setActive(tab) {
                if (tab === 'theory' || tab === 'practice') {
                    lastEditTab = tab;
                }
                tabs.forEach(function (t) {
                    t.classList.toggle('is-active', t.dataset.tab === tab);
                });
                panels.forEach(function (p) {
                    p.style.display = (p.dataset.panel === tab) ? '' : 'none';
                });
                if (tab === 'theory' && mdeTheory.codemirror) {
                    mdeTheory.codemirror.refresh();
                }
                if (tab === 'practice' && mdePractice.codemirror) {
                    mdePractice.codemirror.refresh();
                }
                if (tab === 'preview') {
                    renderPreview();
                }
            }

            async function renderPreview() {
                previewEl.innerHTML = marked.parse(markdownForPreview());
                var codes = previewEl.querySelectorAll('pre code.language-mermaid');
                if (!codes.length) {
                    return;
                }
                if (!mermaidMod) {
                    mermaidMod = (await import('mermaid')).default;
                    mermaidMod.initialize({
                        startOnLoad: false,
                        theme: 'base',
                        securityLevel: 'strict',
                        fontFamily: 'Manrope, system-ui, sans-serif',
                        flowchart: { curve: 'basis', padding: 12 },
                    });
                }
                var i = 0;
                codes.forEach(async function (code) {
                    var pre = code.parentElement;
                    if (!pre || pre.tagName !== 'PRE') {
                        return;
                    }
                    var graph = (code.textContent || '').trim();
                    if (!graph) {
                        return;
                    }
                    var id = 'cmce-mermaid-' + (i++) + '-' + Math.random().toString(36).slice(2, 8);
                    try {
                        var out = await mermaidMod.render(id, graph);
                        var wrap = document.createElement('div');
                        wrap.className = 'theory-mermaid-wrap';
                        wrap.innerHTML = out.svg;
                        pre.replaceWith(wrap);
                    } catch (e) {
                        console.warn('mermaid', e);
                    }
                });
            }

            tabs.forEach(function (t) {
                t.addEventListener('click', function () {
                    setActive(t.dataset.tab || 'theory');
                });
            });

            setActive('theory');
        })();
    </script>
@endsection
