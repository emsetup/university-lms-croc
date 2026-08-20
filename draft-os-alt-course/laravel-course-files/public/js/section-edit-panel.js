/**
 * Боковая панель редактирования раздела курса (workbench «Модули»).
 */
(function () {
    'use strict';

    var TYPE_LABELS = { text: 'Теория', quiz: 'Тест', practice: 'Практика', exam: 'Экзамен', survey: 'Опрос' };

    function $(id) {
        return document.getElementById(id);
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    var state = {
        data: null,
        rawSettings: {},
        questions: [],
        quizActive: -1,
        practiceImageId: null,
        saveUrl: '',
        open: false,
        secAccessPicker: null,
        visibilitySaveUrl: '',
        learnerSearchUrl: '',
        learnerResolveUrl: '',
        participantsUrl: '',
        participantsJsonUrl: '',
        participantsDetailTpl: '',
    };

    var quizAutoSaveTimer = null;

    var ICON_X =
        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>';

    function getCourseId() {
        return state.data && state.data.course && state.data.course.id
            ? parseInt(state.data.course.id, 10)
            : null;
    }

    function openMediaPicker(targetId) {
        if (!window.MediaLibrary) return;
        var handle = window.CourseMarkdownEditor && window.CourseMarkdownEditor.get(targetId);
        if (handle) {
            window.MediaLibrary.open({
                courseId: getCourseId(),
                onInsert: function (md) {
                    var cm = handle.mde && handle.mde.codemirror;
                    if (cm) {
                        var doc = cm.getDoc();
                        var cursor = doc.getCursor();
                        var prefix = cursor.ch > 0 ? '\n' : '';
                        doc.replaceRange(prefix + md + '\n', cursor);
                        cm.focus();
                    }
                    if (targetId === 'ap-sec-theory-md') updateTheoryChars();
                },
            });
            return;
        }
        var el = $(targetId);
        window.MediaLibrary.open({
            courseId: getCourseId(),
            onInsert: function (md) {
                if (el) window.MediaLibrary.insertAtCursor(el, md);
                if (targetId === 'ap-sec-theory-md') updateTheoryChars();
                if (targetId === 'ap-sec-q-text') scheduleQuizDraftSave();
            },
        });
    }

    function cmdeConfig() {
        var page = window.CourseMarkdownEditorPage || {};
        return {
            courseId: getCourseId() || page.courseId || null,
            previewUrl: page.previewUrl || '',
            csrf: page.csrf || '',
            compact: true,
        };
    }

    function isEditorHostVisible(el) {
        if (!el) return false;
        var node = el;
        while (node && node !== document.body) {
            if (node.hidden) return false;
            node = node.parentElement;
        }
        return true;
    }

    function ensureMarkdownEditors(force) {
        if (typeof window.EasyMDE === 'undefined' || !window.CourseMarkdownEditor) {
            return false;
        }
        var cfg = cmdeConfig();
        var th = $('ap-sec-theory-md');
        var pr = $('ap-sec-practice-md');
        var ok = true;

        function mount(el, extra) {
            if (!el) return;
            var existing = window.CourseMarkdownEditor.get(el);
            if (existing && !force) {
                return;
            }
            // Не монтируем в полностью скрытый контейнер — CodeMirror часто ломается.
            if (!isEditorHostVisible(el) && !force) {
                return;
            }
            try {
                if (existing) {
                    existing.destroy();
                }
                var handle = window.CourseMarkdownEditor.create(el, Object.assign({}, cfg, extra || {}));
                if (!handle) {
                    ok = false;
                    if (typeof console !== 'undefined' && console.warn) {
                        console.warn('CourseMarkdownEditor.create returned null', el.id);
                    }
                }
            } catch (err) {
                ok = false;
                if (typeof console !== 'undefined' && console.warn) {
                    console.warn('CourseMarkdownEditor.create failed', err);
                }
            }
        }

        mount(th, {
            minHeight: '280px',
            status: ['lines', 'words'],
            onChange: function () {
                updateTheoryChars();
            },
        });
        mount(pr, {
            minHeight: '220px',
            status: ['lines', 'words'],
        });
        return ok;
    }

    function refreshMarkdownEditors(force) {
        ensureMarkdownEditors(!!force);
        var th = window.CourseMarkdownEditor && window.CourseMarkdownEditor.get('ap-sec-theory-md');
        var pr = window.CourseMarkdownEditor && window.CourseMarkdownEditor.get('ap-sec-practice-md');
        window.setTimeout(function () {
            if (th) th.refresh();
            if (pr) pr.refresh();
        }, 40);
        window.setTimeout(function () {
            if (th) th.refresh();
            if (pr) pr.refresh();
        }, 200);
    }

    function scheduleMarkdownEditors() {
        // После открытия панели / снятия hidden — несколько попыток, пока EasyMDE не готов.
        var tries = 0;
        function tick() {
            tries += 1;
            var ready = typeof window.EasyMDE !== 'undefined' && !!window.CourseMarkdownEditor;
            if (!ready) {
                if (tries < 40) window.setTimeout(tick, 50);
                return;
            }
            refreshMarkdownEditors(tries === 1);
            if (tries < 6) window.setTimeout(tick, 120);
        }
        tick();
    }

    function theoryMarkdownValue() {
        if (window.CourseMarkdownEditor) {
            return window.CourseMarkdownEditor.valueOf('ap-sec-theory-md');
        }
        var ta = $('ap-sec-theory-md');
        return ta ? ta.value : '';
    }

    function practiceMarkdownValue() {
        if (window.CourseMarkdownEditor) {
            return window.CourseMarkdownEditor.valueOf('ap-sec-practice-md');
        }
        var ta = $('ap-sec-practice-md');
        return ta ? ta.value : '';
    }

    function setTheoryMarkdown(v) {
        if (window.CourseMarkdownEditor) {
            ensureMarkdownEditors();
            window.CourseMarkdownEditor.setValueOf('ap-sec-theory-md', v || '');
        } else {
            var ta = $('ap-sec-theory-md');
            if (ta) ta.value = v || '';
        }
        updateTheoryChars();
    }

    function setPracticeMarkdown(v) {
        if (window.CourseMarkdownEditor) {
            ensureMarkdownEditors();
            window.CourseMarkdownEditor.setValueOf('ap-sec-practice-md', v || '');
        } else {
            var ta = $('ap-sec-practice-md');
            if (ta) ta.value = v || '';
        }
    }

    function insertMediaAtInput(inp, md) {
        if (!inp || !window.MediaLibrary) return;
        window.MediaLibrary.insertAtCursor(inp, md);
        scheduleQuizDraftSave();
    }

    var ICON_MEDIA_IMAGE =
        '<svg class="ap-icon ap-icon--media" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>';

    function mediaBtnForInput(inp) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ap-media-insert-btn';
        btn.title = 'Вставить картинку';
        btn.setAttribute('aria-label', 'Вставить картинку');
        btn.innerHTML = ICON_MEDIA_IMAGE;
        btn.addEventListener('click', function () {
            if (!window.MediaLibrary) return;
            window.MediaLibrary.open({
                courseId: getCourseId(),
                onInsert: function (md) {
                    insertMediaAtInput(inp, md);
                },
            });
        });
        return btn;
    }

    function fromServerQuestion(q) {
        var item;
        if (q.open_text) {
            item = { type: 'open_text', q: q.q || '', placeholder: q.placeholder || '', max_length: q.max_length || null };
            if (q.points != null && Number(q.points) > 0) item.points = Number(q.points);
            if (q.id) item.id = Number(q.id);
            return item;
        }
        if (q.multi_other) {
            item = {
                type: 'multi_other',
                q: q.q || '',
                options: (q.a || []).slice(),
                correct: [],
                placeholder: q.placeholder || '',
                max_length: q.max_length || null,
            };
            if (q.points != null && Number(q.points) > 0) item.points = Number(q.points);
            if (q.id) item.id = Number(q.id);
            return item;
        }
        if (q.match_drag || (Array.isArray(q.left) && Array.isArray(q.right))) {
            item = {
                type: 'match',
                q: q.q || '',
                left: Array.isArray(q.left) ? q.left.slice() : [],
                right: Array.isArray(q.right) ? q.right.slice() : [],
            };
        } else if (Array.isArray(q.c)) {
            item = { type: 'multi', q: q.q || '', options: (q.a || []).slice(), correct: q.c.slice() };
        } else if (q.c === undefined || q.c === null) {
            item = { type: 'single', q: q.q || '', options: (q.a || []).slice() };
        } else {
            item = {
                type: 'single',
                q: q.q || '',
                options: (q.a || []).slice(),
                correct: typeof q.c === 'number' ? q.c : 0,
            };
        }
        if (q.points != null && Number(q.points) > 0) {
            item.points = Number(q.points);
        }
        if (q.id) item.id = Number(q.id);
        return item;
    }

    function toBankQuestion(item) {
        var out;
        if (item.type === 'open_text') {
            out = { q: item.q, open_text: true };
            if (item.placeholder) out.placeholder = item.placeholder;
            if (item.max_length) out.max_length = item.max_length;
            if (item.id) out.id = item.id;
            return out;
        }
        if (item.type === 'multi_other') {
            out = { q: item.q, a: item.options, c: [], multi_other: true };
            if (item.placeholder) out.placeholder = item.placeholder;
            if (item.max_length) out.max_length = item.max_length;
            if (item.id) out.id = item.id;
            return out;
        }
        if (item.type === 'match') {
            out = { q: item.q, match_drag: true, left: item.left, right: item.right };
        } else if (item.type === 'multi') {
            out = { q: item.q, a: item.options };
            if (isSurveySection()) {
                out.c = [];
            } else {
                out.c = item.correct;
            }
        } else {
            out = { q: item.q, a: item.options };
            if (! isSurveySection()) {
                out.c = item.correct;
            }
        }
        if (item.points != null && Number(item.points) > 0) {
            out.points = Number(item.points);
        }
        if (item.id) out.id = item.id;
        return out;
    }

    function shortQuestionText(s) {
        s = (s || '').replace(/\s+/g, ' ').trim();
        if (s.length > 80) return s.slice(0, 77) + '…';
        return s || '(без текста)';
    }

    function typeMetaLabel(t) {
        if (t === 'open_text') return 'open';
        if (t === 'multi_other') return 'mixed';
        if (t === 'match') return 'match';
        if (t === 'multi') return 'multi';
        return 'single';
    }

    function isExamSection() {
        return state.data && state.data.section && state.data.section.type === 'exam';
    }

    function isQuizExamType(t) {
        return t === 'quiz' || t === 'exam' || t === 'survey';
    }

    function isSurveySection() {
        return currentSectionType() === 'survey';
    }

    /** Практика: текст задания и Docker, без банка вопросов. */
    function hideQuizEditorChrome() {
        var panQ = $('ap-sec-edit-panel-pane-questions');
        var meta = $('ap-sec-panel-meta');
        if (panQ) panQ.hidden = true;
        if (meta) meta.hidden = true;
        state.quizActive = -1;
        var list = $('ap-sec-quiz-list');
        if (list) list.innerHTML = '';
        var editor = $('ap-sec-q-editor');
        var empty = $('ap-sec-q-empty');
        if (editor) editor.hidden = true;
        if (empty) empty.hidden = false;
    }

    function currentSectionType() {
        var sel = $('ap-sec-set-type');
        if (sel && sel.value) return sel.value;
        return state.data && state.data.section ? state.data.section.type : 'text';
    }

    function applyPanelLayout(sectionType) {
        var panel = $('ap-sec-edit-panel');
        if (!panel) return;
        var wide = isQuizExamType(sectionType);
        panel.classList.toggle('panel-wide', wide);
        if (!panel.dataset.userResized) {
            panel.style.width = wide ? '80vw' : '';
        }
        var tabQ = $('ap-sec-tab-questions');
        var tabC = $('ap-sec-tab-content');
        if (tabQ) tabQ.hidden = !wide;
        if (tabC) tabC.hidden = wide;
        var meta = $('ap-sec-panel-meta');
        if (meta) meta.hidden = !wide;
        document.querySelectorAll('.ap-sec-settings-quiz-only').forEach(function (el) {
            el.hidden = !wide;
        });
    }

    function defaultTabForType(t) {
        return isQuizExamType(t) ? 'questions' : 'content';
    }

    function showQuizSavedIndicator() {
        var el = $('ap-sec-quiz-save-indicator');
        if (!el) return;
        el.hidden = false;
        el.classList.add('is-visible');
        el.innerHTML = 'Сохранено <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="vertical-align:-2px"><path d="M20 6L9 17l-5-5"/></svg>';
        window.clearTimeout(showQuizSavedIndicator._t);
        showQuizSavedIndicator._t = window.setTimeout(function () {
            el.textContent = '';
            el.classList.remove('is-visible');
            el.hidden = true;
        }, 2000);
    }

    function scheduleQuizDraftSave() {
        if (!isQuizExamType(currentSectionType())) return;
        window.clearTimeout(quizAutoSaveTimer);
        quizAutoSaveTimer = window.setTimeout(function () {
            syncActiveQuestionFromEditor();
            renderQuizSidebar();
            updatePanelMetaInfo();
            showQuizSavedIndicator();
        }, 1500);
    }

    function wrapTextareaSelection(ta, before, after) {
        var s = ta.selectionStart;
        var e = ta.selectionEnd;
        var v = ta.value;
        var sel = v.slice(s, e);
        ta.value = v.slice(0, s) + before + sel + after + v.slice(e);
        var pos = s + before.length + sel.length + after.length;
        ta.selectionStart = ta.selectionEnd = pos;
        ta.focus();
    }

    function setInheritRadios(name, inherit) {
        var v = inherit ? 'inherit' : 'own';
        var nodes = document.querySelectorAll('input[name="' + name + '"]');
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].value === v) nodes[i].checked = true;
        }
    }

    function readInherit(name) {
        var nodes = document.querySelectorAll('input[name="' + name + '"]:checked');
        return nodes.length && nodes[0].value === 'inherit';
    }

    function syncOwnInputs() {
        $('ap-sec-own-att').hidden = readInherit('ap-sec-inherit-att');
        $('ap-sec-own-time').hidden = readInherit('ap-sec-inherit-time');
        $('ap-sec-own-pass').hidden = readInherit('ap-sec-inherit-pass');
    }

    function updateTheoryChars() {
        var el = $('ap-sec-theory-chars');
        if (!el) return;
        var n = (theoryMarkdownValue() || '').length;
        el.textContent = n + ' ' + (n === 1 ? 'символ' : n > 1 && n < 5 ? 'символа' : 'символов');
    }

    function syncQuestionsOrderFromDom() {
        var list = $('ap-sec-quiz-list');
        if (!list || state.questions.length < 2) return;
        syncActiveQuestionFromEditor();
        var activeItem = state.quizActive >= 0 ? state.questions[state.quizActive] : null;
        var newOrder = [];
        list.querySelectorAll('.question-list-item').forEach(function (row) {
            var idx = parseInt(row.getAttribute('data-q-idx'), 10);
            if (!isNaN(idx) && state.questions[idx] !== undefined) {
                newOrder.push(state.questions[idx]);
            }
        });
        if (newOrder.length !== state.questions.length) return;
        var changed = false;
        for (var i = 0; i < newOrder.length; i++) {
            if (newOrder[i] !== state.questions[i]) {
                changed = true;
                break;
            }
        }
        if (!changed) return;
        state.questions = newOrder;
        if (activeItem) {
            state.quizActive = state.questions.indexOf(activeItem);
        }
        renderQuizSidebar();
        scheduleQuizDraftSave();
    }

    function initQuestionListReorder() {
        var list = $('ap-sec-quiz-list');
        if (!list || list.dataset.reorderReady) return;
        list.dataset.reorderReady = '1';
        var dragEl = null;
        var dropMark = null;

        function clearDropMark() {
            if (dropMark) dropMark.classList.remove('is-drop-target');
            dropMark = null;
        }

        function finishDrag() {
            clearDropMark();
            if (dragEl) {
                dragEl.classList.remove('is-dragging');
                syncQuestionsOrderFromDom();
            }
            dragEl = null;
        }

        function moveDragEl(beforeNode) {
            if (!dragEl || !beforeNode || !list.contains(beforeNode) || beforeNode === dragEl) return;
            list.insertBefore(dragEl, beforeNode);
        }

        list.addEventListener('dragstart', function (e) {
            var handle = e.target.closest('.q-item-drag-handle');
            if (!handle) return;
            dragEl = handle.closest('.question-list-item');
            if (!dragEl) return;
            e.dataTransfer.effectAllowed = 'move';
            try {
                e.dataTransfer.setData('text/plain', dragEl.getAttribute('data-q-idx') || '');
            } catch (err) {
                /* IE11 */
            }
            if (e.dataTransfer.setDragImage) {
                try {
                    e.dataTransfer.setDragImage(dragEl, 28, 22);
                } catch (err2) {
                    /* ignore */
                }
            }
            dragEl.classList.add('is-dragging');
        });

        list.addEventListener('dragover', function (e) {
            if (!dragEl) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var row = e.target.closest('.question-list-item');
            clearDropMark();
            if (!row || row === dragEl || !list.contains(row)) return;
            dropMark = row;
            row.classList.add('is-drop-target');
            var rect = row.getBoundingClientRect();
            var after = e.clientY - rect.top > rect.height / 2;
            moveDragEl(after ? row.nextSibling : row);
        });

        list.addEventListener('drop', function (e) {
            e.preventDefault();
            finishDrag();
        });

        list.addEventListener('dragend', finishDrag);
    }

    function renderQuizSidebar() {
        if (!isQuizExamType(currentSectionType())) return;
        var list = $('ap-sec-quiz-list');
        var countEl = $('ap-sec-quiz-count');
        if (!list) return;
        var n = state.questions.length;
        if (countEl) countEl.textContent = String(n);
        list.innerHTML = '';
        state.questions.forEach(function (item, idx) {
            var row = document.createElement('div');
            row.className = 'question-list-item' + (idx === state.quizActive ? ' active' : '');
            row.setAttribute('role', 'listitem');
            row.setAttribute('data-q-idx', String(idx));
            row.innerHTML =
                '<span class="q-item-drag-handle" title="Перетащите для изменения порядка" aria-label="Перетащите для изменения порядка" draggable="true">≡</span>' +
                '<div class="q-item-main">' +
                '<div class="q-item-num">' +
                (idx + 1) +
                '</div>' +
                '<div class="q-item-text">' +
                esc(shortQuestionText(item.q)) +
                '</div>' +
                '<div class="q-item-type">' +
                esc(typeMetaLabel(item.type)) +
                '</div>' +
                '</div>';
            row.addEventListener('click', function (e) {
                if (e.target.closest('.q-item-drag-handle')) return;
                var i = parseInt(row.getAttribute('data-q-idx'), 10);
                if (!isNaN(i)) setQuizActive(i);
            });
            list.appendChild(row);
        });
    }

    function setQuizActive(i) {
        if (state.quizActive >= 0 && state.quizActive < state.questions.length) {
            syncActiveQuestionFromEditor();
        }
        state.quizActive = i;
        renderQuizSidebar();
        renderQuizEditor();
    }

    function renderQuizEditor() {
        var editor = $('ap-sec-q-editor');
        var empty = $('ap-sec-q-empty');
        var titleEl = $('ap-sec-q-editor-title');
        if (state.quizActive < 0 || state.quizActive >= state.questions.length) {
            if (editor) editor.hidden = true;
            if (empty) empty.hidden = false;
            if (titleEl) titleEl.textContent = 'Вопрос';
            return;
        }
        if (editor) editor.hidden = false;
        if (empty) empty.hidden = true;
        var item = state.questions[state.quizActive];
        if (titleEl) titleEl.textContent = 'Вопрос #' + (state.quizActive + 1);
        var typeSel = $('ap-sec-q-type');
        var qText = $('ap-sec-q-text');
        var pointsWrap = $('ap-sec-q-points-label');
        var pointsInp = $('ap-sec-q-points');
        var allowPoints = isExamSection();
        if (pointsWrap) pointsWrap.hidden = !allowPoints;
        if (pointsInp) pointsInp.hidden = !allowPoints;
        if (typeSel) typeSel.value = item.type;
        if (qText) qText.value = item.q || '';
        if (allowPoints && pointsInp) {
            pointsInp.value = item.points != null ? String(item.points) : '';
        }
        var answersWrap = $('ap-sec-q-answers-wrap');
        var matchWrap = $('ap-sec-q-match-wrap');
        var openWrap = $('ap-sec-q-open-wrap');
        var typeOpenOpt = $('ap-sec-q-type-open');
        var typeMixedOpt = $('ap-sec-q-type-mixed');
        if (typeOpenOpt) typeOpenOpt.hidden = !isSurveySection();
        if (typeMixedOpt) typeMixedOpt.hidden = !isSurveySection();
        if (item.type === 'open_text') {
            if (answersWrap) answersWrap.hidden = true;
            if (matchWrap) matchWrap.hidden = true;
            if (openWrap) openWrap.hidden = false;
            var ph = $('ap-sec-q-placeholder');
            var ml = $('ap-sec-q-maxlen');
            if (ph) ph.value = item.placeholder || '';
            if (ml) ml.value = item.max_length != null ? String(item.max_length) : '';
        } else if (item.type === 'multi_other') {
            if (answersWrap) answersWrap.hidden = false;
            if (matchWrap) matchWrap.hidden = true;
            if (openWrap) openWrap.hidden = false;
            var ph2 = $('ap-sec-q-placeholder');
            var ml2 = $('ap-sec-q-maxlen');
            if (ph2) ph2.value = item.placeholder || '';
            if (ml2) ml2.value = item.max_length != null ? String(item.max_length) : '';
            renderAnswerOptions(item);
        } else if (item.type === 'match') {
            if (answersWrap) answersWrap.hidden = true;
            if (matchWrap) matchWrap.hidden = false;
            if (openWrap) openWrap.hidden = true;
            renderMatchEditor(item);
        } else {
            if (answersWrap) answersWrap.hidden = false;
            if (matchWrap) matchWrap.hidden = true;
            if (openWrap) openWrap.hidden = true;
            renderAnswerOptions(item);
        }
    }

    function renderAnswerOptions(item) {
        var box = $('ap-sec-q-answers');
        var hint = $('ap-sec-q-c-hint');
        var survey = isSurveySection();
        if (!box) return;
        if (!Array.isArray(item.options)) item.options = [''];
        if (survey) {
            if (hint) {
                if (item.type === 'multi_other') {
                    hint.textContent =
                        'Опрос: несколько вариантов и поле «Свой вариант». Правильных ответов нет.';
                } else if (item.type === 'multi') {
                    hint.textContent =
                        'Опрос: можно выбрать несколько вариантов, правильных ответов нет.';
                } else {
                    hint.textContent =
                        'Опрос: правильный ответ не задаётся — только варианты для респондента.';
                }
            }
        } else if (item.type === 'multi') {
            if (!Array.isArray(item.correct)) item.correct = [];
            if (hint) hint.textContent = 'Отметьте все верные варианты.';
        } else {
            if (typeof item.correct !== 'number') item.correct = 0;
            if (hint) hint.textContent = 'Выберите один верный вариант.';
        }
        box.innerHTML = '';
        item.options.forEach(function (opt, idx) {
            var row = document.createElement('div');
            row.className = 'answer-row';
            var isCorrect =
                !survey &&
                (item.type === 'multi' ? item.correct.indexOf(idx) >= 0 : item.correct === idx);
            if (isCorrect) row.classList.add('correct');
            var mark = null;
            if (!survey) {
                mark = document.createElement('div');
                mark.className = item.type === 'multi' ? 'answer-marker multi' : 'answer-marker';
                if (isCorrect) mark.classList.add('checked');
                mark.addEventListener('click', function () {
                    selectCorrectAnswer(row, idx, item.type);
                });
            }
            var inp = document.createElement('input');
            inp.className = 'answer-text-input';
            inp.type = 'text';
            inp.value = String(opt || '');
            inp.placeholder = 'Вариант ответа…';
            inp.addEventListener('input', function () {
                item.options[idx] = inp.value;
                scheduleQuizDraftSave();
            });
            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'answer-delete-btn';
            del.setAttribute('aria-label', 'Удалить вариант');
            del.innerHTML = ICON_X;
            del.addEventListener('click', function () {
                item.options.splice(idx, 1);
                if (!survey && item.correct != null) {
                    if (item.type === 'multi') {
                        item.correct = item.correct
                            .filter(function (x) {
                                return x !== idx;
                            })
                            .map(function (x) {
                                return x > idx ? x - 1 : x;
                            });
                    } else {
                        if (item.correct === idx) item.correct = 0;
                        else if (item.correct > idx) item.correct -= 1;
                    }
                }
                renderQuizEditor();
                scheduleQuizDraftSave();
            });
            if (mark) row.appendChild(mark);
            row.appendChild(mediaBtnForInput(inp));
            row.appendChild(inp);
            row.appendChild(del);
            box.appendChild(row);
        });
    }

    function selectCorrectAnswer(optionEl, idx, type) {
        var item = state.questions[state.quizActive];
        if (!item) return;
        if (type === 'multi') {
            var pos = item.correct.indexOf(idx);
            if (pos >= 0) item.correct.splice(pos, 1);
            else item.correct.push(idx);
            item.correct.sort(function (a, b) {
                return a - b;
            });
        } else {
            item.correct = idx;
        }
        renderAnswerOptions(item);
        scheduleQuizDraftSave();
    }

    function renderMatchEditor(item) {
        var box = $('ap-sec-q-match');
        if (!box) return;
        if (!Array.isArray(item.left)) item.left = [''];
        if (!Array.isArray(item.right)) item.right = [''];
        var n = Math.max(item.left.length, item.right.length);
        box.innerHTML = '';
        for (var i = 0; i < n; i++) {
            (function (idx) {
                var row = document.createElement('div');
                row.className = 'ap-sec-q-match-row';
                var left = document.createElement('input');
                left.type = 'text';
                left.value = String(item.left[idx] || '');
                left.addEventListener('input', function () {
                    item.left[idx] = left.value;
                    scheduleQuizDraftSave();
                });
                var right = document.createElement('textarea');
                right.value = String(item.right[idx] || '');
                right.addEventListener('input', function () {
                    item.right[idx] = right.value;
                    scheduleQuizDraftSave();
                });
                var del = document.createElement('button');
                del.type = 'button';
                del.className = 'btn btn-ghost btn-sm answer-delete';
                del.setAttribute('aria-label', 'Удалить пару');
                del.innerHTML = ICON_X;
                del.addEventListener('click', function () {
                    item.left.splice(idx, 1);
                    item.right.splice(idx, 1);
                    renderMatchEditor(item);
                    scheduleQuizDraftSave();
                });
                row.appendChild(mediaBtnForInput(left));
                row.appendChild(left);
                row.appendChild(mediaBtnForInput(right));
                row.appendChild(right);
                row.appendChild(del);
                box.appendChild(row);
            })(i);
        }
    }

    function syncActiveQuestionFromEditor() {
        if (state.quizActive < 0 || state.quizActive >= state.questions.length) return;
        var item = state.questions[state.quizActive];
        var typeSel = $('ap-sec-q-type');
        var qText = $('ap-sec-q-text');
        var pointsInp = $('ap-sec-q-points');
        var newType = typeSel ? typeSel.value : item.type;
        if (newType !== item.type) {
            if (newType === 'match') {
                item.type = 'match';
                item.left = item.left || [''];
                item.right = item.right || [''];
                delete item.options;
                delete item.correct;
                delete item.placeholder;
                delete item.max_length;
            } else if (newType === 'open_text') {
                item.type = 'open_text';
                delete item.options;
                delete item.correct;
                delete item.left;
                delete item.right;
                if (item.placeholder == null) item.placeholder = '';
                if (item.max_length == null) item.max_length = null;
            } else {
                item.type = newType;
                item.options = item.options && item.options.length ? item.options : ['', ''];
                if (isSurveySection()) {
                    if (newType === 'multi' || newType === 'multi_other') item.correct = [];
                    else delete item.correct;
                } else if (newType === 'multi') {
                    if (Array.isArray(item.correct)) {
                        /* keep */
                    } else if (typeof item.correct === 'number') {
                        item.correct = [item.correct];
                    } else {
                        item.correct = [];
                    }
                } else if (newType === 'multi_other') {
                    item.correct = [];
                } else {
                    if (typeof item.correct === 'number') {
                        /* keep */
                    } else if (Array.isArray(item.correct) && item.correct.length) {
                        item.correct = item.correct[0];
                    } else {
                        item.correct = 0;
                    }
                }
                delete item.left;
                delete item.right;
                if (newType === 'multi_other') {
                    if (item.placeholder == null) item.placeholder = '';
                    if (item.max_length == null) item.max_length = null;
                } else {
                    delete item.placeholder;
                    delete item.max_length;
                }
            }
        }
        item.q = qText ? qText.value : item.q;
        if (item.type === 'open_text' || item.type === 'multi_other') {
            var ph = $('ap-sec-q-placeholder');
            var ml = $('ap-sec-q-maxlen');
            item.placeholder = ph ? ph.value.trim() : item.placeholder || '';
            if (ml && ml.value !== '') {
                var mlNum = parseInt(ml.value, 10);
                item.max_length = Number.isFinite(mlNum) && mlNum > 0 ? mlNum : null;
            } else {
                item.max_length = null;
            }
        }
        if (isExamSection() && pointsInp) {
            var pts = parseInt(pointsInp.value || '0', 10);
            if (Number.isFinite(pts) && pts > 0) item.points = pts;
            else delete item.points;
        } else {
            delete item.points;
        }
    }

    function addEmptyQuestion() {
        var q = { type: 'single', q: '', options: [''] };
        if (! isSurveySection()) q.correct = 0;
        if (isExamSection()) q.points = 5;
        state.questions.push(q);
        setQuizActive(state.questions.length - 1);
        var scroll = $('ap-sec-q-editor-scroll');
        if (scroll) scroll.scrollTop = 0;
        if ($('ap-sec-q-text')) $('ap-sec-q-text').focus();
    }

    function dupActiveQuestion() {
        if (state.quizActive < 0) return;
        syncActiveQuestionFromEditor();
        var copy = JSON.parse(JSON.stringify(state.questions[state.quizActive]));
        state.questions.splice(state.quizActive + 1, 0, copy);
        setQuizActive(state.quizActive + 1);
    }

    function delActiveQuestion() {
        if (state.quizActive < 0) return;
        syncActiveQuestionFromEditor();
        state.questions.splice(state.quizActive, 1);
        state.quizActive = state.questions.length ? Math.min(state.quizActive, state.questions.length - 1) : -1;
        renderQuizSidebar();
        renderQuizEditor();
        updateQuizSummary();
    }

    function renderQuizList() {
        state.quizActive = -1;
        renderQuizSidebar();
        if (state.questions.length > 0) {
            setQuizActive(0);
        } else {
            renderQuizEditor();
        }
    }

    function updateQuizSummary() {
        updatePanelMetaInfo();
    }

    function updatePanelMetaInfo() {
        var el = $('ap-sec-panel-meta');
        if (!el || !state.data) return;
        if (!isQuizExamType(currentSectionType())) {
            el.hidden = true;
            return;
        }
        el.hidden = false;
        var n = state.questions.length;
        var att = !readInherit('ap-sec-inherit-att')
            ? ($('ap-sec-own-att').value || '—')
            : 'из курса';
        var time = !readInherit('ap-sec-inherit-time')
            ? ($('ap-sec-own-time').value || '—') + ' мин'
            : 'из курса';
        if (isSurveySection()) {
            el.innerHTML =
                '<span class="panel-meta-item">Вопросов: <strong>' + esc(String(n)) + '</strong></span>' +
                '<span class="panel-meta-item">Попытки: <strong>' + esc(String(att)) + '</strong></span>' +
                '<span class="panel-meta-item">Время: <strong>' + (time === '— мин' ? 'не задано' : esc(String(time))) + '</strong></span>' +
                '<span class="panel-meta-item">Режим: <strong>без оценки</strong></span>';
        } else {
            var pass = !readInherit('ap-sec-inherit-pass')
                ? ($('ap-sec-own-pass').value || '—') + '%'
                : 'из курса';
            el.innerHTML =
                '<span class="panel-meta-item">Вопросов: <strong>' + esc(String(n)) + '</strong></span>' +
                '<span class="panel-meta-item">Попытки: <strong>' + esc(String(att)) + '</strong></span>' +
                '<span class="panel-meta-item">Время: <strong>' + esc(String(time)) + '</strong></span>' +
                '<span class="panel-meta-item">Проходной: <strong>' + esc(String(pass)) + '</strong></span>';
        }
        var countEl = $('ap-sec-quiz-count');
        if (countEl) countEl.textContent = String(n);
    }

    function showContentForType(t) {
        var svFields = $('ap-sec-survey-fields');
        if (svFields) svFields.hidden = t !== 'survey';
        var quizMode = isQuizExamType(t);
        document.querySelectorAll('.ap-sec-settings-quiz-only').forEach(function (el) {
            el.hidden = !quizMode || t === 'survey';
        });
        var th = $('ap-sec-edit-content-theory');
        var pr = $('ap-sec-edit-content-practice');
        if (th) th.hidden = t !== 'text';
        if (pr) pr.hidden = t !== 'practice';
        var body = document.querySelector('.ap-sec-edit-panel__body');
        if (body) body.classList.toggle('ap-sec-edit-panel__body--quiz', quizMode);
        applyPanelLayout(t);
        if (quizMode) {
            renderQuizList();
            updateQuizSummary();
        } else {
            hideQuizEditorChrome();
        }
        if (state.open) {
            switchMainTab(defaultTabForType(t));
        }
    }

    function applyLoadedData(d) {
        state.data = d;
        state.rawSettings = d.settings && typeof d.settings === 'object' ? JSON.parse(JSON.stringify(d.settings)) : {};
        state.saveUrl = d.save_url || '';
        state.questions = (d.questions || []).map(fromServerQuestion);
        state.practiceImageId = d.practice_image ? d.practice_image.id : null;

        var hints = (d.course && d.course.inherit_hints) || { attempts: '—', time: '—', pass: '—' };
        $('ap-sec-hint-att').textContent = hints.attempts;
        $('ap-sec-hint-time').textContent = hints.time;
        $('ap-sec-hint-pass').textContent = hints.pass;

        $('ap-sec-set-title').value = d.section.title || '';
        $('ap-sec-set-type').value = d.section.type || 'text';
        $('ap-sec-set-enabled').checked = !!d.section.is_enabled;
        var anonEl = $('ap-sec-set-anonymous');
        if (anonEl) anonEl.checked = !!(state.rawSettings.anonymous);
        var blocksEl = $('ap-sec-set-blocks-progress');
        if (blocksEl) blocksEl.checked = state.rawSettings.blocks_progress !== false;
        renderShareLinkUi(d.share_link || d.quick_link);
        var svLinkWrap = $('ap-sec-survey-responses-link-wrap');
        var svLink = $('ap-sec-survey-responses-link');
        if (svLinkWrap && svLink && d.survey_responses_url) {
            svLink.href = d.survey_responses_url;
            svLinkWrap.hidden = d.section.type !== 'survey';
        } else if (svLinkWrap) svLinkWrap.hidden = true;

        var quizExportWrap = $('ap-sec-quiz-export-wrap');
        var quizExport = $('ap-sec-quiz-export');
        var quizExportWord = $('ap-sec-quiz-export-word');
        if (quizExport && d.questions_export_url) {
            quizExport.href = d.questions_export_url;
            quizExport.hidden = false;
        } else if (quizExport) {
            quizExport.href = '#';
            quizExport.hidden = true;
        }
        if (quizExportWord && d.questions_word_export_url) {
            quizExportWord.href = d.questions_word_export_url;
            quizExportWord.hidden = false;
        } else if (quizExportWord) {
            quizExportWord.href = '#';
            quizExportWord.hidden = true;
        }
        if (quizExportWrap) {
            quizExportWrap.hidden = !(d.questions_export_url || d.questions_word_export_url);
        }

        var theoryExport = $('ap-sec-theory-export');
        if (theoryExport) {
            if (d.theory_export_url) {
                theoryExport.href = d.theory_export_url;
                theoryExport.hidden = false;
            } else {
                theoryExport.hidden = true;
            }
        }

        // Шапка: Word для теории и вопросов; Excel — только для теста/экзамена/опроса.
        var setHeadExport = function (id, url) {
            var el = $(id);
            if (!el) return;
            if (url) {
                el.href = url;
                el.hidden = false;
            } else {
                el.href = '#';
                el.hidden = true;
            }
        };
        setHeadExport('ap-sec-export-excel-btn', d.questions_export_url || '');
        setHeadExport('ap-sec-export-word-btn', d.theory_export_url || d.questions_word_export_url || '');

        state.participantsUrl = d.participants_url || '';
        state.participantsJsonUrl = d.participants_json_url || '';
        state.participantsDetailTpl = d.participants_detail_url_tpl || '';
        var partPage = $('ap-sec-participants-page-link');
        if (partPage) {
            partPage.href = state.participantsUrl || '#';
            partPage.hidden = !state.participantsUrl;
        }
        var partCsv = $('ap-sec-participants-csv-link');
        if (partCsv) {
            if (d.survey_responses_url) {
                partCsv.href = d.survey_responses_url;
                partCsv.hidden = false;
            } else {
                partCsv.hidden = true;
            }
        }
        var partSetWrap = $('ap-sec-participants-settings-link-wrap');
        var partSetLink = $('ap-sec-participants-settings-link');
        if (partSetWrap && partSetLink && state.participantsUrl) {
            partSetLink.href = state.participantsUrl;
            partSetWrap.hidden = false;
        } else if (partSetWrap) partSetWrap.hidden = true;

        var partList = $('ap-sec-participants-list');
        if (partList) partList.innerHTML = '<p class="ap-muted">Откройте вкладку, чтобы загрузить список.</p>';
        var partDetail = $('ap-sec-participants-detail');
        if (partDetail) { partDetail.hidden = true; partDetail.innerHTML = ''; }
        var partCounters = $('ap-sec-participants-counters');
        if (partCounters) { partCounters.hidden = true; partCounters.innerHTML = ''; }

        var st = state.rawSettings;
        setInheritRadios('ap-sec-inherit-att', !!st.attempts_from_course);
        setInheritRadios('ap-sec-inherit-time', !!st.time_from_course);
        setInheritRadios('ap-sec-inherit-pass', !!st.pass_from_course);
        $('ap-sec-own-att').value = st.attempt_limit != null ? String(st.attempt_limit) : '';
        $('ap-sec-own-time').value = st.time_limit_minutes != null ? String(st.time_limit_minutes) : '';
        $('ap-sec-own-pass').value = st.pass_percent != null ? String(st.pass_percent) : '';
        syncOwnInputs();

        setTheoryMarkdown(d.theory_markdown || '');
        setPracticeMarkdown(d.practice_markdown || '');
        $('ap-sec-theory-saved').hidden = true;

        renderDockerCard(d);

        var chip = $('ap-sec-edit-panel-chip');
        var typ = d.section.type || 'text';
        if (isQuizExamType(typ)) {
            renderQuizList();
            updateQuizSummary();
        } else {
            hideQuizEditorChrome();
            updatePanelMetaInfo();
        }
        chip.textContent = TYPE_LABELS[typ] || typ;
        chip.className = 'ap-sec-edit-panel__chip ap-sec-chip ap-sec-chip--' + typ;
        $('ap-sec-edit-panel-heading').textContent = d.section.title || 'Раздел';
        $('ap-sec-edit-panel-sub').textContent =
            'Модуль ' + (d.module.ordinal || '—') + ' · ' + (d.course.title || '');

        showContentForType($('ap-sec-set-type').value);

        $('ap-sec-edit-legacy').hidden = !d.is_legacy;

        scheduleMarkdownEditors();

        state.visibilitySaveUrl = d.visibility_save_url || '';
        state.learnerSearchUrl = d.learner_search_url || '';
        state.learnerResolveUrl = d.learner_resolve_url || '';
        var accessRoot = $('ap-sec-access-picker-root');
        if (accessRoot && typeof window.ContentAudiencePicker === 'function') {
            if (!state.secAccessPicker) {
                state.secAccessPicker = new window.ContentAudiencePicker(accessRoot, {
                    searchUrl: state.learnerSearchUrl,
                    resolveUrl: state.learnerResolveUrl,
                });
            } else {
                state.secAccessPicker.searchUrl = state.learnerSearchUrl;
                state.secAccessPicker.resolveUrl = state.learnerResolveUrl;
            }
            state.secAccessPicker.setData(d.visibility || { view_audience: 'all', rules: [], groups: { portal: [], course: [] } });
        }
    }

    function renderShareLinkUi(meta) {
        var shareBtn = $('ap-sec-share-btn');
        var hint = $('ap-sec-share-hint');
        state.quickLink = meta || null;
        state.shareLink = meta || null;
        var hasMeta = !!(meta && meta.generate_url);
        if (shareBtn) shareBtn.hidden = !hasMeta;
        if (hint) hint.hidden = !hasMeta;
    }

    function postQuickLink(url, csrf, onOk) {
        if (!url) return;
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (d) {
                if (!d || !d.ok) {
                    window.alert((d && d.message) || 'Не удалось обновить быструю ссылку.');
                    return;
                }
                if (state.quickLink) {
                    if (d.url) {
                        state.quickLink.active = true;
                        state.quickLink.url = d.url;
                    } else {
                        state.quickLink.active = false;
                        state.quickLink.url = null;
                    }
                    state.shareLink = state.quickLink;
                    renderShareLinkUi(state.quickLink);
                }
                if (typeof onOk === 'function') onOk(d);
            })
            .catch(function () {
                window.alert('Ошибка сети при работе с быстрой ссылкой.');
            });
    }

    function renderDockerCard(d) {
        var bound = $('ap-sec-docker-bound');
        var unb = $('ap-sec-docker-unbound');
        if (!bound || !unb) return;
        var pi = d.practice_image;
        if (pi) {
            bound.hidden = false;
            unb.hidden = true;
            $('ap-sec-docker-title').textContent = pi.title || '';
            $('ap-sec-docker-tag').textContent = pi.docker_tag || '';
            $('ap-sec-docker-layers').textContent = pi.layers_note || '';
        } else {
            bound.hidden = true;
            unb.hidden = false;
        }
    }

    function openPanel(dataUrl) {
        var panel = $('ap-sec-edit-panel');
        if (!panel || !dataUrl) return;
        fetch(dataUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) {
                return r.json();
            })
            .then(function (d) {
                if (!d || !d.ok) {
                    window.alert((d && d.message) || 'Не удалось загрузить раздел.');
                    return;
                }
                // Сначала показать панель — иначе EasyMDE/CodeMirror не монтируется в [hidden].
                panel.hidden = false;
                panel.setAttribute('aria-hidden', 'false');
                state.open = true;
                applyLoadedData(d);
                requestAnimationFrame(function () {
                    panel.classList.add('is-open');
                    scheduleMarkdownEditors();
                });
                switchMainTab(defaultTabForType(d.section.type || 'text'));
                window.setTimeout(function () {
                    scheduleMarkdownEditors();
                }, 250);
            })
            .catch(function (err) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('section panel load failed', err);
                }
                window.alert('Ошибка сети при загрузке панели.');
            });
    }

    function closePanel() {
        var panel = $('ap-sec-edit-panel');
        if (!panel) return;
        panel.classList.remove('is-open');
        panel.classList.remove('panel-wide');
        delete panel.dataset.userResized;
        panel.style.width = '';
        state.open = false;
        window.setTimeout(function () {
            panel.hidden = true;
            panel.setAttribute('aria-hidden', 'true');
            var m = $('ap-sec-docker-modal');
            if (m) {
                m.hidden = true;
                m.classList.remove('is-open');
            }
        }, 230);
    }

    function switchMainTab(which) {
        var typ = currentSectionType();
        if (isQuizExamType(typ) && which === 'content') which = 'questions';
        if (!isQuizExamType(typ) && which === 'questions') which = 'content';
        var tabs = document.querySelectorAll('[data-ap-sec-tab]');
        var panS = $('ap-sec-edit-panel-pane-settings');
        var panC = $('ap-sec-edit-panel-pane-content');
        var panQ = $('ap-sec-edit-panel-pane-questions');
        var panA = $('ap-sec-edit-panel-pane-access');
        var panP = $('ap-sec-edit-panel-pane-participants');
        tabs.forEach(function (t) {
            if (t.hidden) return;
            var on = t.getAttribute('data-ap-sec-tab') === which;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        if (which === 'access') {
            if (panS) panS.hidden = true;
            if (panC) panC.hidden = true;
            if (panQ) panQ.hidden = true;
            if (panA) panA.hidden = false;
            if (panP) panP.hidden = true;
        } else if (which === 'participants') {
            if (panS) panS.hidden = true;
            if (panC) panC.hidden = true;
            if (panQ) panQ.hidden = true;
            if (panA) panA.hidden = true;
            if (panP) panP.hidden = false;
            loadParticipantsTab();
        } else if (which === 'settings') {
            if (panS) panS.hidden = false;
            if (panC) panC.hidden = true;
            if (panQ) panQ.hidden = true;
            if (panA) panA.hidden = true;
            if (panP) panP.hidden = true;
        } else if (isQuizExamType(typ) && which === 'questions') {
            if (panS) panS.hidden = true;
            if (panC) panC.hidden = true;
            if (panQ) panQ.hidden = false;
            if (panA) panA.hidden = true;
            if (panP) panP.hidden = true;
        } else {
            if (panS) panS.hidden = true;
            if (panC) panC.hidden = false;
            if (panQ) panQ.hidden = true;
            if (panA) panA.hidden = true;
            if (panP) panP.hidden = true;
            scheduleMarkdownEditors();
        }
        if (!isQuizExamType(typ)) hideQuizEditorChrome();
    }

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function participantsDetailUrl(learnerId) {
        var tpl = state.participantsDetailTpl || '';
        if (!tpl) return '';
        return tpl.replace(/\/0(\/?$)/, '/' + learnerId);
    }

    function loadParticipantsTab() {
        var list = $('ap-sec-participants-list');
        var counters = $('ap-sec-participants-counters');
        var detail = $('ap-sec-participants-detail');
        if (!list) return;
        if (!state.participantsJsonUrl) {
            list.innerHTML = '<p class="ap-muted">Сохраните раздел, чтобы увидеть участников.</p>';
            return;
        }
        list.innerHTML = '<p class="ap-muted">Загрузка…</p>';
        if (detail) { detail.hidden = true; detail.innerHTML = ''; }
        fetch(state.participantsJsonUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d || !d.ok) {
                list.innerHTML = '<p class="ap-muted">Не удалось загрузить участников.</p>';
                return;
            }
            renderParticipantsPayload(d);
        }).catch(function () {
            list.innerHTML = '<p class="ap-muted">Ошибка загрузки участников.</p>';
        });
    }

    function renderParticipantsPayload(d) {
        var list = $('ap-sec-participants-list');
        var counters = $('ap-sec-participants-counters');
        if (!list) return;
        var c = d.counters || {};
        if (counters) {
            var htmlC = '';
            if (d.audience === 'restricted') {
                htmlC += '<span class="ap-sec-participants__chip"><b>' + escHtml(c.eligible || 0) + '</b> доступно</span>';
                htmlC += '<span class="ap-sec-participants__chip"><b>' + escHtml(c.completed || 0) + '</b> прошли</span>';
                htmlC += '<span class="ap-sec-participants__chip"><b>' + escHtml(c.pending || 0) + '</b> не прошли</span>';
                if (c.attempted != null) {
                    htmlC += '<span class="ap-sec-participants__chip"><b>' + escHtml(c.attempted) + '</b> с попытками</span>';
                }
            } else {
                htmlC += '<span class="ap-sec-participants__chip"><b>' + escHtml(c.completed || 0) + '</b> прошли</span>';
            }
            counters.innerHTML = htmlC;
            counters.hidden = false;
        }
        var rows = d.rows || [];
        if (!rows.length) {
            list.innerHTML = '<p class="ap-muted">' +
                (d.audience === 'restricted'
                    ? 'Нет допущенных участников. Назначьте доступ во вкладке «Доступ».'
                    : 'Пока никто не прошёл этот раздел.') +
                '</p>';
            return;
        }
        var html = '<ul class="ap-sec-participants__ul" role="list">';
        rows.forEach(function (row) {
            var stClass = row.status === 'completed' ? 'is-done' : (row.status === 'attempted' ? 'is-attempted' : 'is-pending');
            var clickable = row.can_open_detail && row.learner_id;
            html += '<li class="ap-sec-participants__row' + (clickable ? ' is-clickable' : '') + '"' +
                (clickable ? ' data-learner-id="' + escHtml(row.learner_id) + '"' : '') + '>';
            html += '<div class="ap-sec-participants__row-main">';
            html += '<div class="ap-sec-participants__row-name">' + escHtml(row.display_name || '—') + '</div>';
            if (row.email) html += '<div class="ap-muted small">' + escHtml(row.email) + '</div>';
            html += '</div>';
            html += '<div class="ap-sec-participants__row-meta">';
            html += '<span class="ap-section-participants__status ' + stClass + '">' + escHtml(row.status_label || '') + '</span>';
            if (row.completed_at) html += '<span class="ap-muted small">' + escHtml(row.completed_at) + '</span>';
            if (row.meta) html += '<span class="ap-muted small">' + escHtml(row.meta) + '</span>';
            html += '</div></li>';
        });
        html += '</ul>';
        list.innerHTML = html;
        list.querySelectorAll('.ap-sec-participants__row[data-learner-id]').forEach(function (li) {
            li.addEventListener('click', function () {
                openParticipantDetail(li.getAttribute('data-learner-id'));
            });
        });
    }

    function openParticipantDetail(learnerId) {
        var detail = $('ap-sec-participants-detail');
        var url = participantsDetailUrl(learnerId);
        if (!detail || !url) return;
        detail.hidden = false;
        detail.innerHTML = '<p class="ap-muted">Загрузка…</p>';
        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d || !d.ok) {
                detail.innerHTML = '<p class="ap-muted">' + escHtml((d && d.message) || 'Не удалось загрузить') + '</p>';
                return;
            }
            detail.innerHTML = renderParticipantDetailHtml(d);
        }).catch(function () {
            detail.innerHTML = '<p class="ap-muted">Ошибка загрузки</p>';
        });
    }

    function renderParticipantDetailHtml(d) {
        var html = '<div class="ap-sp-detail-card">';
        if (d.learner) {
            html += '<h3 class="ap-sp-detail-card__title">' + escHtml(d.learner.display_name) + '</h3>';
            if (d.learner.email) html += '<p class="ap-muted small">' + escHtml(d.learner.email) + '</p>';
        } else {
            html += '<h3 class="ap-sp-detail-card__title">Результат</h3>';
        }
        html += '<p><span class="ap-section-participants__status">' + escHtml(d.status_label || '') + '</span>';
        if (d.completed_at) html += ' · ' + escHtml(d.completed_at);
        html += '</p>';
        if (d.survey) {
            if (d.survey.anonymous) {
                html += '<p class="ap-muted">Анонимный опрос — текст ответов скрыт.</p>';
            } else if (!d.survey.submitted) {
                html += '<p class="ap-muted">Ещё не отправил ответы.</p>';
            } else if (!d.survey.items || !d.survey.items.length) {
                html += '<p class="ap-muted">Ответов нет.</p>';
            } else {
                html += '<ul class="ap-report-survey-answers">';
                d.survey.items.forEach(function (it) {
                    html += '<li class="ap-report-survey-answers__item">';
                    html += '<p class="ap-report-survey-answers__q">' + escHtml(it.question || '') + '</p>';
                    html += '<p class="ap-report-survey-answers__a">' + escHtml(it.answer || '—') + '</p>';
                    html += '</li>';
                });
                html += '</ul>';
            }
        } else if (d.quiz) {
            html += '<p class="ap-muted small">Лучший результат: ' + escHtml(d.quiz.best_score) + '% · попыток: ' + escHtml(d.quiz.attempts) + '</p>';
            if (d.quiz.items && d.quiz.items.length) {
                html += '<ul class="ap-report-survey-answers">';
                d.quiz.items.forEach(function (it, i) {
                    var q = it.question_text || it.question || ('Вопрос ' + (i + 1));
                    var a = it.display || it.answer || it.chosen_label || '—';
                    if (typeof a === 'object') a = JSON.stringify(a);
                    html += '<li class="ap-report-survey-answers__item">';
                    html += '<p class="ap-report-survey-answers__q">' + escHtml(q) + '</p>';
                    html += '<p class="ap-report-survey-answers__a">' + escHtml(a) + '</p>';
                    html += '</li>';
                });
                html += '</ul>';
            }
        } else if (d.simple) {
            html += '<p>' + escHtml(d.simple.message || '') + '</p>';
        }
        html += '</div>';
        return html;
    }

    function buildPayload() {
        var typ = $('ap-sec-set-type').value;
        var af = readInherit('ap-sec-inherit-att');
        var tf = readInherit('ap-sec-inherit-time');
        var pf = readInherit('ap-sec-inherit-pass');
        function parseOptInt(elId) {
            var el = $(elId);
            if (!el) return null;
            var v = el.value.trim();
            if (v === '') return null;
            var n = parseInt(v, 10);
            return isNaN(n) ? null : n;
        }
        var p = {
            title: $('ap-sec-set-title').value.trim(),
            type: typ,
            is_enabled: $('ap-sec-set-enabled').checked,
            attempts_from_course: af,
            time_from_course: tf,
            pass_from_course: pf,
            attempt_limit: af ? null : parseOptInt('ap-sec-own-att'),
            time_limit_minutes: tf ? null : parseOptInt('ap-sec-own-time'),
            pass_percent: pf ? null : parseOptInt('ap-sec-own-pass'),
            min_read_seconds: state.rawSettings.min_read_seconds != null ? state.rawSettings.min_read_seconds : 0,
            shuffle: !!state.rawSettings.shuffle,
            one_by_one: state.rawSettings.one_by_one !== undefined ? !!state.rawSettings.one_by_one : true,
            breakdown_visible_minutes:
                state.rawSettings.breakdown_visible_minutes != null ? state.rawSettings.breakdown_visible_minutes : 30,
        };
        if (typ === 'text') {
            p.theory_markdown = theoryMarkdownValue();
        }
        if (typ === 'practice') {
            p.practice_markdown = practiceMarkdownValue();
            p.practice_image_id = state.practiceImageId != null ? state.practiceImageId : null;
        }
        if (typ === 'quiz' || typ === 'exam' || typ === 'survey') {
            syncActiveQuestionFromEditor();
            p.questions = state.questions.map(toBankQuestion);
        }
        if (typ === 'survey') {
            p.anonymous = $('ap-sec-set-anonymous') && $('ap-sec-set-anonymous').checked;
            p.blocks_progress = $('ap-sec-set-blocks-progress') && $('ap-sec-set-blocks-progress').checked;
            p.pass_percent = null;
            p.pass_from_course = false;
            p.one_by_one = true;
            if (!p.attempt_limit) p.attempt_limit = 1;
        }
        return p;
    }

    function savePanel(csrf) {
        var panel = $('ap-sec-edit-panel');
        if (!state.saveUrl || !panel) return;
        if (state.data && state.data.is_legacy) {
            window.alert('Legacy-курс: сохранение из панели недоступно.');
            return;
        }
        var body = buildPayload();
        var visPromise = Promise.resolve({ ok: true });
        if (state.secAccessPicker && state.visibilitySaveUrl) {
            var visErr = state.secAccessPicker.validate();
            if (visErr) {
                window.alert(visErr);
                return;
            }
            visPromise = fetch(state.visibilitySaveUrl, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(state.secAccessPicker.getPayload()),
            }).then(function (r) {
                return r.json().then(function (j) {
                    return { ok: r.ok, j: j };
                });
            });
        }
        visPromise
            .then(function (visRes) {
                if (!visRes.ok) {
                    window.alert((visRes.j && visRes.j.message) || 'Ошибка сохранения доступа.');
                    return Promise.reject();
                }
                return fetch(state.saveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
            })
            .then(function (r) {
                return r.json().then(function (j) {
                    return { ok: r.ok, j: j };
                });
            })
            .then(function (x) {
                if (!x.j || !x.j.ok) {
                    window.alert((x.j && x.j.message) || 'Ошибка сохранения.');
                    return;
                }
                if ($('ap-sec-set-type').value === 'text') {
                    var s = $('ap-sec-theory-saved');
                    if (s) {
                        s.hidden = false;
                        window.setTimeout(function () {
                            s.hidden = true;
                        }, 2500);
                    }
                }
                if ($('ap-sec-set-type').value === 'quiz' || $('ap-sec-set-type').value === 'exam') {
                    showQuizSavedIndicator();
                }
                state.data.section.title = body.title;
                state.data.section.type = body.type;
                state.data.section.is_enabled = body.is_enabled;
                $('ap-sec-edit-panel-heading').textContent = body.title;
                var chip = $('ap-sec-edit-panel-chip');
                chip.textContent = TYPE_LABELS[body.type] || body.type;
                chip.className = 'ap-sec-edit-panel__chip ap-sec-chip ap-sec-chip--' + body.type;
                var sid = state.data.section && state.data.section.id;
                if (sid) {
                    var row = document.querySelector('.ap-sec-row[data-section-id="' + sid + '"]');
                    if (row) {
                        var tEl = row.querySelector('.ap-sec-row__title');
                        if (tEl) tEl.textContent = body.title;
                        var mEl = row.querySelector('.ap-sec-row__meta');
                        if (mEl) mEl.textContent = body.is_enabled ? 'Включён' : 'Выключен';
                    }
                }
            })
            .catch(function () {
                window.alert('Ошибка сети при сохранении.');
            });
    }

    function openDockerModal() {
        var m = $('ap-sec-docker-modal');
        var list = $('ap-sec-docker-modal-list');
        if (!m || !list || !state.data) return;
        list.innerHTML = '';
        (state.data.docker_images || []).forEach(function (im) {
            var li = document.createElement('li');
            li.className = 'ap-sec-docker-modal-list__item';
            li.innerHTML =
                '<button type="button" class="ap-sec-docker-pick-row">' +
                '<strong>' +
                esc(im.title) +
                '</strong> <code class="ap-muted">' +
                esc(im.docker_tag) +
                '</code></button>';
            li.querySelector('button').addEventListener('click', function () {
                state.practiceImageId = im.id;
                state.data.practice_image = {
                    id: im.id,
                    title: im.title,
                    docker_tag: im.docker_tag,
                    layers_note: 'Слои задаются в Dockerfile образа (редактор Docker-образов).',
                };
                renderDockerCard(state.data);
                m.hidden = true;
                m.classList.remove('is-open');
            });
            list.appendChild(li);
        });
        m.hidden = false;
        requestAnimationFrame(function () {
            m.classList.add('is-open');
        });
    }

    function bindResize() {
        var handle = $('ap-sec-edit-panel-resize');
        var panel = $('ap-sec-edit-panel');
        if (!handle || !panel) return;
        var dragging = false;
        function pxClamp(w) {
            var iw = window.innerWidth;
            var minW = panel.classList.contains('panel-wide') ? 800 : 480;
            var maxW = Math.min(Math.floor(iw * 0.9), iw - 8);
            return clamp(w, minW, maxW);
        }
        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            dragging = true;
            document.body.style.userSelect = 'none';
        });
        window.addEventListener('mousemove', function (e) {
            if (!dragging) return;
            var w = pxClamp(window.innerWidth - e.clientX);
            panel.style.width = w + 'px';
        });
        window.addEventListener('mouseup', function () {
            if (!dragging) return;
            dragging = false;
            document.body.style.userSelect = '';
            panel.dataset.userResized = '1';
        });
    }

    function init(root) {
        var csrf = root.getAttribute('data-ap-csrf') || '';
        initQuestionListReorder();

        root.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ap-open-section-panel]');
            if (btn) {
                e.preventDefault();
                openPanel(btn.getAttribute('data-ap-panel-data-url'));
            }
        });

        document.querySelectorAll('[data-ap-media-insert-target]').forEach(function (b) {
            b.addEventListener('click', function () {
                openMediaPicker(b.getAttribute('data-ap-media-insert-target'));
            });
        });

        document.addEventListener('cmde:ready', function () {
            if (state.open) scheduleMarkdownEditors();
        });

        ['ap-sec-inherit-att', 'ap-sec-inherit-time', 'ap-sec-inherit-pass'].forEach(function (name) {
            document.querySelectorAll('input[name="' + name + '"]').forEach(function (r) {
                r.addEventListener('change', function () {
                    syncOwnInputs();
                    updateQuizSummary();
                });
            });
        });
        ['ap-sec-own-att', 'ap-sec-own-time', 'ap-sec-own-pass'].forEach(function (id) {
            var el = $(id);
            if (el) el.addEventListener('input', updateQuizSummary);
        });

        document.querySelectorAll('[data-ap-sec-tab]').forEach(function (t) {
            t.addEventListener('click', function () {
                switchMainTab(t.getAttribute('data-ap-sec-tab'));
            });
        });

        $('ap-sec-edit-panel-close').addEventListener('click', closePanel);
        $('ap-sec-edit-cancel').addEventListener('click', closePanel);
        $('ap-sec-edit-save').addEventListener('click', function () {
            savePanel(csrf);
        });

        $('ap-sec-set-type').addEventListener('change', function () {
            showContentForType($('ap-sec-set-type').value);
            updateQuizSummary();
        });

        var shareBtn = $('ap-sec-share-btn');
        if (shareBtn) {
            shareBtn.addEventListener('click', function () {
                var meta = state.shareLink || state.quickLink;
                if (!meta || !window.ApShareLink) return;
                window.ApShareLink.open({
                    meta: meta,
                    csrf: csrf,
                    onChange: function (updated) {
                        state.shareLink = updated;
                        state.quickLink = updated;
                        renderShareLinkUi(updated);
                    }
                });
            });
        }

        var qType = $('ap-sec-q-type');
        if (qType) {
            qType.addEventListener('change', function () {
                // syncActiveQuestionFromEditor already remaps type and keeps
                // filled options / correct marks when switching single ↔ multi.
                syncActiveQuestionFromEditor();
                renderQuizEditor();
                scheduleQuizDraftSave();
            });
        }
        var qText = $('ap-sec-q-text');
        if (qText) {
            qText.addEventListener('input', function () {
                scheduleQuizDraftSave();
            });
        }
        var qPh = $('ap-sec-q-placeholder');
        if (qPh) {
            qPh.addEventListener('input', function () {
                scheduleQuizDraftSave();
            });
        }
        var qMl = $('ap-sec-q-maxlen');
        if (qMl) {
            qMl.addEventListener('input', function () {
                scheduleQuizDraftSave();
            });
        }
        var qPoints = $('ap-sec-q-points');
        if (qPoints) {
            qPoints.addEventListener('input', function () {
                scheduleQuizDraftSave();
            });
        }
        var qDup = $('ap-sec-q-dup');
        if (qDup) qDup.addEventListener('click', dupActiveQuestion);
        var qDel = $('ap-sec-q-del');
        if (qDel) qDel.addEventListener('click', delActiveQuestion);
        var qAddOpt = $('ap-sec-q-add-option');
        if (qAddOpt) {
            qAddOpt.addEventListener('click', function () {
                var item = state.questions[state.quizActive];
                if (!item || item.type === 'match' || item.type === 'open_text') return;
                if (!Array.isArray(item.options)) item.options = [];
                item.options.push('');
                renderQuizEditor();
                scheduleQuizDraftSave();
            });
        }
        var qAddPair = $('ap-sec-q-add-pair');
        if (qAddPair) {
            qAddPair.addEventListener('click', function () {
                var item = state.questions[state.quizActive];
                if (!item || item.type !== 'match') return;
                if (!Array.isArray(item.left)) item.left = [];
                if (!Array.isArray(item.right)) item.right = [];
                item.left.push('');
                item.right.push('');
                renderMatchEditor(item);
                scheduleQuizDraftSave();
            });
        }

        $('ap-sec-quiz-add').addEventListener('click', addEmptyQuestion);

        $('ap-sec-docker-unbind').addEventListener('click', function () {
            state.practiceImageId = null;
            if (state.data) state.data.practice_image = null;
            renderDockerCard(state.data);
        });
        $('ap-sec-docker-replace').addEventListener('click', openDockerModal);
        $('ap-sec-docker-pick').addEventListener('click', openDockerModal);
        document.querySelectorAll('[data-ap-sec-docker-modal-close]').forEach(function (b) {
            b.addEventListener('click', function () {
                var m = $('ap-sec-docker-modal');
                if (m) {
                    m.classList.remove('is-open');
                    m.hidden = true;
                }
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && state.open) {
                var m = $('ap-sec-docker-modal');
                if (m && !m.hidden) {
                    m.classList.remove('is-open');
                    m.hidden = true;
                } else closePanel();
            }
        });

        bindResize();
    }

    window.ApSectionEditPanel = { init: init };

    function boot() {
        var r = document.querySelector('[data-ap-workbench]');
        if (r) init(r);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
