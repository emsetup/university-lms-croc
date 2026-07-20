/**
 * Единый Markdown-редактор теории/практики (EasyMDE + плашки callout + серверный preview).
 * window.CourseMarkdownEditor.create(textarea, options) → handle
 */
(function (global) {
    'use strict';

    var CALLOUTS = [
        { name: 'callout-warn', label: 'Важно', title: 'Плашка «Важно»', className: 'cmde-callout cmde-callout--warn', placeholder: 'критичное ограничение или риск…' },
        { name: 'callout-tip', label: 'Подсказка', title: 'Плашка «Подсказка»', className: 'cmde-callout cmde-callout--tip', placeholder: 'практический совет…' },
        { name: 'callout-note', label: 'Примечание', title: 'Плашка «Примечание»', className: 'cmde-callout cmde-callout--note', placeholder: 'дополнительный контекст…' },
        { name: 'callout-goal', label: 'Зачем', title: 'Плашка «Зачем» / идея', className: 'cmde-callout cmde-callout--goal', placeholder: 'зачем это нужно…' },
    ];

    function insertAroundCursor(cm, text, preferBlankLines) {
        var doc = cm.getDoc();
        var cursor = doc.getCursor();
        var prefix = '';
        var suffix = '';
        if (preferBlankLines) {
            var line = doc.getLine(cursor.line) || '';
            if (cursor.ch > 0 || (line && line.trim() !== '')) {
                prefix = '\n\n';
            } else if (cursor.line > 0) {
                var prev = doc.getLine(cursor.line - 1) || '';
                if (prev.trim() !== '') {
                    prefix = '\n';
                }
            }
            suffix = '\n\n';
        }
        var from = { line: cursor.line, ch: cursor.ch };
        doc.replaceRange(prefix + text + suffix, from);
        cm.focus();
    }

    function insertCallout(editor, label, placeholder) {
        var cm = editor.codemirror;
        var selected = cm.getSelection();
        var body = (selected && selected.trim() !== '') ? selected.trim() : (placeholder || 'текст…');
        var lines = body.split(/\r?\n/);
        var block = '> **' + label + ':** ' + lines[0];
        for (var i = 1; i < lines.length; i++) {
            block += '\n> ' + lines[i];
        }
        if (selected && selected.trim() !== '') {
            cm.replaceSelection(block);
            cm.focus();
        } else {
            insertAroundCursor(cm, block, true);
        }
    }

    function insertMermaid(editor) {
        var tpl = '```mermaid\nflowchart TD\n  A[Шаг 1] --> B[Шаг 2]\n```';
        insertAroundCursor(editor.codemirror, tpl, true);
    }

    function insertMedia(editor, courseId) {
        if (!global.MediaLibrary) {
            return;
        }
        global.MediaLibrary.open({
            courseId: courseId || null,
            onInsert: function (md) {
                insertAroundCursor(editor.codemirror, md, true);
            },
        });
    }

    function debounce(fn, ms) {
        var t = null;
        return function () {
            var args = arguments;
            var self = this;
            if (t) clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(self, args);
            }, ms);
        };
    }

    function enhanceMermaid(root) {
        if (!root) return;
        var codes = root.querySelectorAll('pre code.language-mermaid');
        if (!codes.length) return;
        if (!(global.mermaid && typeof global.mermaid.render === 'function')) {
            return;
        }
        var i = 0;
        codes.forEach(function (code) {
            var pre = code.parentElement;
            if (!pre || pre.tagName !== 'PRE') return;
            var graph = (code.textContent || '').trim();
            if (!graph) return;
            var id = 'cmde-mermaid-' + (i++) + '-' + Math.random().toString(36).slice(2, 8);
            try {
                var out = global.mermaid.render(id, graph);
                if (out && typeof out.then === 'function') {
                    out.then(function (res) {
                        var wrap = document.createElement('div');
                        wrap.className = 'theory-mermaid-wrap';
                        wrap.innerHTML = res.svg || res;
                        pre.replaceWith(wrap);
                    }).catch(function () { /* keep code fence */ });
                } else if (out && out.svg) {
                    var wrap = document.createElement('div');
                    wrap.className = 'theory-mermaid-wrap';
                    wrap.innerHTML = out.svg;
                    pre.replaceWith(wrap);
                }
            } catch (e) { /* keep fence */ }
        });
    }

    function makePreviewRender(opts) {
        var seq = 0;
        var previewUrl = opts.previewUrl || '';
        var csrf = opts.csrf || '';
        var run = debounce(function (plainText, previewEl, token) {
            if (!previewUrl) {
                previewEl.innerHTML = '<p class="muted">Нет URL предпросмотра.</p><pre class="cmde-preview-fallback">' +
                    escapeHtml(plainText) + '</pre>';
                return;
            }
            fetch(previewUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ markdown: plainText || '' }),
            })
                .then(function (r) {
                    if (!r.ok) throw new Error('preview ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    if (token !== seq) return;
                    previewEl.innerHTML = data && data.html ? data.html : '';
                    previewEl.classList.add('theory-article', 'prose-course', 'practice-block', 'theory-content', 'cmde-preview-body');
                    enhanceMermaid(previewEl);
                })
                .catch(function () {
                    if (token !== seq) return;
                    previewEl.innerHTML = '<p class="muted">Не удалось получить предпросмотр. Сохраните и откройте «Просмотр» в Содержимом.</p>';
                });
        }, 280);

        return function (plainText, preview) {
            seq += 1;
            var token = seq;
            if (preview && preview.innerHTML !== undefined) {
                preview.innerHTML = '<p class="muted cmde-preview-loading">Предпросмотр…</p>';
                run(plainText, preview, token);
                return 'Предпросмотр…';
            }
            return 'Предпросмотр…';
        };
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function buildToolbar(opts) {
        var courseId = opts.courseId;
        var compact = !!opts.compact;
        var base = [
            'bold', 'italic', 'heading', '|',
            'quote', 'unordered-list', 'ordered-list', '|',
            'link',
            {
                name: 'media-lib',
                action: function (editor) {
                    insertMedia(editor, courseId);
                },
                className: 'fa fa-picture-o',
                title: 'Библиотека картинок',
            },
            'code', 'table', '|',
        ];

        CALLOUTS.forEach(function (c) {
            base.push({
                name: c.name,
                action: function (editor) {
                    insertCallout(editor, c.label, c.placeholder);
                },
                className: c.className,
                title: c.title,
            });
        });

        base.push('|');
        base.push({
            name: 'mermaid',
            action: function (editor) {
                insertMermaid(editor);
            },
            className: 'cmde-callout cmde-callout--mermaid',
            title: 'Блок диаграммы Mermaid',
        });

        if (!compact) {
            base.push('|', 'preview', 'side-by-side', 'fullscreen', '|', 'guide');
        } else {
            base.push('|', 'preview', 'side-by-side');
        }

        return base;
    }

    /**
     * @param {HTMLTextAreaElement} element
     * @param {object} options
     * @returns {object|null}
     */
    function create(element, options) {
        if (!element || typeof global.EasyMDE === 'undefined') {
            return null;
        }
        var opts = options || {};
        if (element._cmdeHandle && typeof element._cmdeHandle.destroy === 'function') {
            element._cmdeHandle.destroy();
        }

        var mde = new global.EasyMDE({
            element: element,
            spellChecker: false,
            autosave: { enabled: false },
            status: opts.status !== undefined ? opts.status : ['lines', 'words', 'cursor'],
            minHeight: opts.minHeight || '360px',
            toolbar: opts.toolbar || buildToolbar(opts),
            previewRender: makePreviewRender(opts),
            placeholder: opts.placeholder || '',
            renderingConfig: {
                singleLineBreaks: false,
                codeSyntaxHighlighting: false,
            },
        });

        var changeTimer = null;
        if (typeof opts.onChange === 'function' && mde.codemirror) {
            mde.codemirror.on('change', function () {
                if (changeTimer) clearTimeout(changeTimer);
                changeTimer = setTimeout(function () {
                    opts.onChange(mde.value());
                }, 80);
            });
        }

        var handle = {
            mde: mde,
            element: element,
            value: function () {
                return mde.value();
            },
            setValue: function (v) {
                mde.value(v == null ? '' : String(v));
            },
            refresh: function () {
                if (mde.codemirror) {
                    mde.codemirror.refresh();
                }
            },
            focus: function () {
                if (mde.codemirror) mde.codemirror.focus();
            },
            destroy: function () {
                try {
                    mde.toTextArea();
                } catch (e) { /* ignore */ }
                element._cmdeHandle = null;
            },
            syncToTextarea: function () {
                element.value = mde.value();
                return element.value;
            },
        };

        element._cmdeHandle = handle;
        return handle;
    }

    function get(elementOrId) {
        var el = typeof elementOrId === 'string' ? document.getElementById(elementOrId) : elementOrId;
        return el && el._cmdeHandle ? el._cmdeHandle : null;
    }

    function valueOf(elementOrId) {
        var h = get(elementOrId);
        if (h) return h.value();
        var el = typeof elementOrId === 'string' ? document.getElementById(elementOrId) : elementOrId;
        return el ? el.value : '';
    }

    function setValueOf(elementOrId, v) {
        var h = get(elementOrId);
        if (h) {
            h.setValue(v);
            return;
        }
        var el = typeof elementOrId === 'string' ? document.getElementById(elementOrId) : elementOrId;
        if (el) el.value = v == null ? '' : String(v);
    }

    global.CourseMarkdownEditor = {
        create: create,
        get: get,
        valueOf: valueOf,
        setValueOf: setValueOf,
        buildToolbar: buildToolbar,
        insertCallout: insertCallout,
        CALLOUTS: CALLOUTS,
    };
})(typeof window !== 'undefined' ? window : this);
