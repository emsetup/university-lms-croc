@extends('layouts.course')

@section('title', 'Контент модуля')

@section('content')
    <div style="max-width: 1100px; margin: 0 auto">
        @include('partials.admin-instructor-nav', ['navKey' => $adminKey, 'active' => 'theory'])
        <div class="card">
            <div class="muted small" style="margin:0 0 0.35rem">
                <a href="{{ route('admin.course.settings', ['key' => $adminKey]) }}">Настройки</a>
                <span class="muted">/</span>
                <a href="{{ route('admin.course.module.sections', ['courseModule' => $courseModule->id, 'key' => $adminKey]) }}">{{ $courseModule->title }}</a>
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
                        <span class="icon-btn__icon">👁</span>
                    </button>
                </div>

                <form method="post" action="{{ route('admin.course.module.content.update', ['courseModule' => $courseModule->id, 'key' => $adminKey]) }}">
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
                        <p class="muted small" style="margin:0.5rem 0 0">Подсказка: Mermaid-блоки рендерятся после обновления предпросмотра.</p>
                    </section>

                    <div style="display:flex;gap:0.5rem;align-items:center;justify-content:space-between;margin-top:1rem">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a class="btn btn-ghost" href="{{ route('admin.theory.index', ['key' => $adminKey]) }}">Назад к содержимому</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .cmce-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        @media (max-width: 900px) {
            .cmce-split { grid-template-columns: 1fr; }
        }
        .cmce-editor {
            border: 1px solid var(--line,#e5e7eb);
            border-radius: 10px;
            overflow: hidden;
            background: #0b1220;
            color: #e5e7eb;
        }
        .cmce-editor .cm-editor { font-size: 14px; }
        .cmce-editor .cm-scroller { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .cmce-editor .cm-gutters { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.6); border-right: 1px solid rgba(255,255,255,0.08); }
        .cmce-editor .cm-activeLineGutter, .cmce-editor .cm-activeLine { background: rgba(255,255,255,0.06); }
    </style>

    <script type="module">
        import { EditorView, basicSetup } from 'https://esm.sh/codemirror@6';
        import { EditorState } from 'https://esm.sh/@codemirror/state@6';
        import { markdown } from 'https://esm.sh/@codemirror/lang-markdown@6';
        import { oneDark } from 'https://esm.sh/@codemirror/theme-one-dark@6';
        import { marked } from 'https://esm.sh/marked@12';
        import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs';

        (function () {
            const panels = Array.from(document.querySelectorAll('.js-cmce-panel'));
            const tabs = Array.from(document.querySelectorAll('.js-cmce-tab'));
            const previewEl = document.getElementById('cmce-preview');
            const textareas = Array.from(document.querySelectorAll('textarea.js-cmce-textarea'));
            if (!panels.length || !tabs.length || !previewEl || !textareas.length) return;

            const views = new Map();
            function mountEditors() {
                textareas.forEach((ta) => {
                    const wrap = document.createElement('div');
                    wrap.className = 'cmce-editor';
                    ta.parentNode.insertBefore(wrap, ta);
                    ta.style.display = 'none';

                    const state = EditorState.create({
                        doc: ta.value || '',
                        extensions: [
                            basicSetup,
                            markdown(),
                            oneDark,
                            EditorView.updateListener.of((v) => {
                                if (v.docChanged) ta.value = v.state.doc.toString();
                            }),
                        ],
                    });
                    const view = new EditorView({ state, parent: wrap });
                    views.set(ta, view);
                });
            }

            function setActive(tab) {
                tabs.forEach((t) => t.classList.toggle('is-active', t.dataset.tab === tab));
                panels.forEach((p) => {
                    p.style.display = (p.dataset.panel === tab) ? '' : 'none';
                });
                if (tab === 'preview') {
                    renderPreview();
                }
            }

            function activeTextareaByTab() {
                const activePanel = panels.find((p) => p.style.display !== 'none');
                if (!activePanel) return textareas[0];
                const ta = activePanel.querySelector('textarea.js-cmce-textarea');
                return ta || textareas[0];
            }

            function renderPreview() {
                const ta = activeTextareaByTab();
                const md = ta ? (ta.value || '') : '';
                previewEl.innerHTML = marked.parse(md);

                const codes = previewEl.querySelectorAll('pre code.language-mermaid');
                if (!codes.length) return;
                mermaid.initialize({
                    startOnLoad: false,
                    theme: 'base',
                    securityLevel: 'strict',
                    fontFamily: 'Manrope, system-ui, sans-serif',
                    flowchart: { curve: 'basis', padding: 12 },
                });
                let i = 0;
                codes.forEach(async (code) => {
                    const pre = code.parentElement;
                    if (!pre || pre.tagName !== 'PRE') return;
                    const graph = (code.textContent || '').trim();
                    if (!graph) return;
                    const id = 'cmce-mermaid-' + (i++) + '-' + Math.random().toString(36).slice(2, 8);
                    try {
                        const out = await mermaid.render(id, graph);
                        const wrap = document.createElement('div');
                        wrap.className = 'theory-mermaid-wrap';
                        wrap.innerHTML = out.svg;
                        pre.replaceWith(wrap);
                    } catch (e) {
                        console.warn('mermaid', e);
                    }
                });
            }

            tabs.forEach((t) => t.addEventListener('click', () => setActive(t.dataset.tab || 'theory')));

            mountEditors();
            setActive('theory');
        })();
    </script>
@endsection

