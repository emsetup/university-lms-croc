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
    };

    var quizAutoSaveTimer = null;

    var ICON_X =
        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>';

    function clamp(n, a, b) {
        return Math.max(a, Math.min(b, n));
    }

    function fromServerQuestion(q) {
        var item;
        if (q.open_text) {
            item = { type: 'open_text', q: q.q || '', placeholder: q.placeholder || '', max_length: q.max_length || null };
            if (q.points != null && Number(q.points) > 0) item.points = Number(q.points);
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
        return item;
    }

    function toBankQuestion(item) {
        var out;
        if (item.type === 'open_text') {
            out = { q: item.q, open_text: true };
            if (item.placeholder) out.placeholder = item.placeholder;
            if (item.max_length) out.max_length = item.max_length;
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
        return out;
    }

    function shortQuestionText(s) {
        s = (s || '').replace(/\s+/g, ' ').trim();
        if (s.length > 80) return s.slice(0, 77) + '…';
        return s || '(без текста)';
    }

    function typeMetaLabel(t) {
        if (t === 'open_text') return 'open';
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

    function theoryCmd(cmd) {
        var ta = $('ap-sec-theory-md');
        if (!ta) return;
        if (cmd === 'bold') wrapTextareaSelection(ta, '**', '**');
        else if (cmd === 'italic') wrapTextareaSelection(ta, '*', '*');
        else if (cmd === 'h2') wrapTextareaSelection(ta, '## ', '');
        else if (cmd === 'h3') wrapTextareaSelection(ta, '### ', '');
        else if (cmd === 'code') wrapTextareaSelection(ta, '`', '`');
        else if (cmd === 'link') {
            var url = window.prompt('URL ссылки', 'https://');
            if (url) wrapTextareaSelection(ta, '[', '](' + url + ')');
        }
        ta.dispatchEvent(new Event('input'));
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
        var ta = $('ap-sec-theory-md');
        var el = $('ap-sec-theory-chars');
        if (!ta || !el) return;
        var n = ta.value.length;
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
        if (typeOpenOpt) typeOpenOpt.hidden = !isSurveySection();
        if (item.type === 'open_text') {
            if (answersWrap) answersWrap.hidden = true;
            if (matchWrap) matchWrap.hidden = true;
            if (openWrap) openWrap.hidden = false;
            var ph = $('ap-sec-q-placeholder');
            var ml = $('ap-sec-q-maxlen');
            if (ph) ph.value = item.placeholder || '';
            if (ml) ml.value = item.max_length != null ? String(item.max_length) : '';
        } else if (item.type === 'match') {
            if (answersWrap) answersWrap.hidden = true;
            if (matchWrap) matchWrap.hidden = false;
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
                hint.textContent =
                    item.type === 'multi'
                        ? 'Опрос: можно выбрать несколько вариантов, правильных ответов нет.'
                        : 'Опрос: правильный ответ не задаётся — только варианты для респондента.';
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
                row.appendChild(left);
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
            } else {
                item.type = newType;
                item.options = item.options && item.options.length ? item.options : ['', ''];
                if (isSurveySection()) {
                    if (newType === 'multi') item.correct = [];
                    else delete item.correct;
                } else if (newType === 'multi') {
                    item.correct = Array.isArray(item.correct) ? item.correct : [];
                } else {
                    item.correct = typeof item.correct === 'number' ? item.correct : 0;
                }
                delete item.left;
                delete item.right;
            }
        }
        item.q = qText ? qText.value : item.q;
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

    function toggleNewQBlocks() {
        var t = $('ap-new-q-type').value;
        var openOpt = $('ap-new-q-type-open');
        var survey = isSurveySection();
        if (openOpt) openOpt.hidden = !survey;
        $('ap-new-q-block-opts').hidden = t === 'match' || t === 'open_text';
        $('ap-new-q-block-match').hidden = t !== 'match';
        var correctWrap = $('ap-new-q-correct-wrap');
        var surveyNote = $('ap-new-q-survey-note');
        if (correctWrap) correctWrap.hidden = survey;
        if (surveyNote) surveyNote.hidden = !survey || t === 'match' || t === 'open_text';
    }

    function readNewQuestionFromForm() {
        var t = $('ap-new-q-type').value;
        var q = $('ap-new-q-text').value.trim();
        if (!q) {
            window.alert('Введите текст вопроса.');
            return null;
        }
        if (t === 'open_text') {
            if (!isSurveySection()) {
                window.alert('Открытый ответ доступен только в опросах.');
                return null;
            }
            return { type: 'open_text', q: q, placeholder: '', max_length: null };
        }
        if (t === 'match') {
            var left = $('ap-new-q-left')
                .value.split('\n')
                .map(function (s) {
                    return s.trim();
                })
                .filter(Boolean);
            var right = $('ap-new-q-right')
                .value.split('\n')
                .map(function (s) {
                    return s.trim();
                })
                .filter(Boolean);
            if (left.length < 1 || left.length !== right.length) {
                window.alert('Сопоставление: одинаковое ненулевое число непустых строк слева и справа.');
                return null;
            }
            var m = { type: 'match', q: q, left: left, right: right };
            if (isExamSection()) m.points = 5;
            return m;
        }
        var opts = $('ap-new-q-opts')
            .value.split('\n')
            .map(function (s) {
                return s.trim();
            })
            .filter(Boolean);
        if (opts.length < 2) {
            window.alert('Нужно минимум два варианта ответа.');
            return null;
        }
        if (isSurveySection()) {
            if (t === 'multi') {
                return { type: 'multi', q: q, options: opts, correct: [] };
            }
            return { type: 'single', q: q, options: opts };
        }
        var cRaw = $('ap-new-q-correct').value.trim();
        if (t === 'multi') {
            var parts = cRaw.split(/[,;\s]+/).filter(Boolean);
            var idxs = [];
            for (var i = 0; i < parts.length; i++) {
                idxs.push(parseInt(parts[i], 10));
            }
            idxs = idxs.filter(function (x) {
                return !isNaN(x) && x >= 0 && x < opts.length;
            });
            if (idxs.length < 1) {
                window.alert('Укажите индексы правильных ответов (0 … n−1).');
                return null;
            }
            var mq = { type: 'multi', q: q, options: opts, correct: idxs };
            if (isExamSection()) mq.points = 5;
            return mq;
        }
        var c = parseInt(cRaw, 10);
        if (isNaN(c) || c < 0 || c >= opts.length) {
            window.alert('Индекс правильного ответа вне диапазона.');
            return null;
        }
        var sq = { type: 'single', q: q, options: opts, correct: c };
        if (isExamSection()) sq.points = 5;
        return sq;
    }

    function clearNewForm() {
        $('ap-new-q-text').value = '';
        $('ap-new-q-type').value = 'single';
        $('ap-new-q-opts').value = '';
        $('ap-new-q-correct').value = '0';
        $('ap-new-q-left').value = '';
        $('ap-new-q-right').value = '';
        toggleNewQBlocks();
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
            toggleNewQBlocks();
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
        renderQuickLinkUi(d.quick_link);
        var svLinkWrap = $('ap-sec-survey-responses-link-wrap');
        var svLink = $('ap-sec-survey-responses-link');
        if (svLinkWrap && svLink && d.survey_responses_url) {
            svLink.href = d.survey_responses_url;
            svLinkWrap.hidden = d.section.type !== 'survey';
        } else if (svLinkWrap) svLinkWrap.hidden = true;

        var st = state.rawSettings;
        setInheritRadios('ap-sec-inherit-att', !!st.attempts_from_course);
        setInheritRadios('ap-sec-inherit-time', !!st.time_from_course);
        setInheritRadios('ap-sec-inherit-pass', !!st.pass_from_course);
        $('ap-sec-own-att').value = st.attempt_limit != null ? String(st.attempt_limit) : '';
        $('ap-sec-own-time').value = st.time_limit_minutes != null ? String(st.time_limit_minutes) : '';
        $('ap-sec-own-pass').value = st.pass_percent != null ? String(st.pass_percent) : '';
        syncOwnInputs();

        $('ap-sec-theory-md').value = d.theory_markdown || '';
        $('ap-sec-practice-md').value = d.practice_markdown || '';
        updateTheoryChars();
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
    }

    function renderQuickLinkUi(meta) {
        var wrap = $('ap-sec-quick-link-wrap');
        var active = $('ap-sec-quick-link-active');
        var genBtn = $('ap-sec-quick-link-gen');
        var urlEl = $('ap-sec-quick-link-url');
        if (!wrap) return;
        var isSurvey = state.data && state.data.section && state.data.section.type === 'survey';
        wrap.hidden = !isSurvey;
        if (!isSurvey || !meta) {
            if (active) active.hidden = true;
            if (genBtn) genBtn.hidden = true;
            return;
        }
        state.quickLink = meta;
        var hasUrl = !!(meta.active && meta.url);
        if (active) active.hidden = !hasUrl;
        if (genBtn) genBtn.hidden = hasUrl;
        if (urlEl && hasUrl) urlEl.value = meta.url;
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
                    renderQuickLinkUi(state.quickLink);
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
                applyLoadedData(d);
                panel.hidden = false;
                panel.setAttribute('aria-hidden', 'false');
                state.open = true;
                requestAnimationFrame(function () {
                    panel.classList.add('is-open');
                });
                switchMainTab(defaultTabForType(d.section.type || 'text'));
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
        tabs.forEach(function (t) {
            if (t.hidden) return;
            var on = t.getAttribute('data-ap-sec-tab') === which;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        if (which === 'settings') {
            if (panS) panS.hidden = false;
            if (panC) panC.hidden = true;
            if (panQ) panQ.hidden = true;
        } else if (isQuizExamType(typ) && which === 'questions') {
            if (panS) panS.hidden = true;
            if (panC) panC.hidden = true;
            if (panQ) panQ.hidden = false;
        } else {
            if (panS) panS.hidden = true;
            if (panC) panC.hidden = false;
            if (panQ) panQ.hidden = true;
        }
        if (!isQuizExamType(typ)) hideQuizEditorChrome();
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
            p.theory_markdown = $('ap-sec-theory-md').value;
        }
        if (typ === 'practice') {
            p.practice_markdown = $('ap-sec-practice-md').value;
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
        fetch(state.saveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
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

        document.querySelectorAll('[data-ap-theory-cmd]').forEach(function (b) {
            b.addEventListener('click', function () {
                theoryCmd(b.getAttribute('data-ap-theory-cmd'));
            });
        });

        var th = $('ap-sec-theory-md');
        if (th) th.addEventListener('input', updateTheoryChars);

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

        var qlCopy = $('ap-sec-quick-link-copy');
        if (qlCopy) {
            qlCopy.addEventListener('click', function () {
                var urlEl = $('ap-sec-quick-link-url');
                if (!urlEl || !urlEl.value) return;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(urlEl.value).catch(function () {
                        urlEl.select();
                        document.execCommand('copy');
                    });
                } else {
                    urlEl.select();
                    document.execCommand('copy');
                }
            });
        }
        var qlGen = $('ap-sec-quick-link-gen');
        if (qlGen) {
            qlGen.addEventListener('click', function () {
                if (!state.quickLink || !state.quickLink.generate_url) return;
                postQuickLink(state.quickLink.generate_url, csrf);
            });
        }
        var qlRegen = $('ap-sec-quick-link-regen');
        if (qlRegen) {
            qlRegen.addEventListener('click', function () {
                if (!state.quickLink || !state.quickLink.generate_url) return;
                if (!window.confirm('Создать новую ссылку? Старая перестанет работать.')) return;
                postQuickLink(state.quickLink.generate_url, csrf);
            });
        }
        var qlOff = $('ap-sec-quick-link-off');
        if (qlOff) {
            qlOff.addEventListener('click', function () {
                if (!state.quickLink || !state.quickLink.revoke_url) return;
                if (!window.confirm('Отключить быструю ссылку?')) return;
                postQuickLink(state.quickLink.revoke_url, csrf);
            });
        }

        var qType = $('ap-sec-q-type');
        if (qType) {
            qType.addEventListener('change', function () {
                syncActiveQuestionFromEditor();
                var item = state.questions[state.quizActive];
                if (!item) return;
                var t = qType.value;
                if (t === 'match') {
                    item.type = 'match';
                    item.left = [''];
                    item.right = [''];
                    delete item.options;
                    delete item.correct;
                } else {
                    item.type = t;
                    item.options = ['', ''];
                    if (isSurveySection()) {
                        if (t === 'multi') item.correct = [];
                        else delete item.correct;
                    } else {
                        item.correct = t === 'multi' ? [] : 0;
                    }
                    delete item.left;
                    delete item.right;
                }
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
                if (!item || item.type === 'match') return;
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

        $('ap-new-q-type').addEventListener('change', toggleNewQBlocks);
        $('ap-new-q-submit').addEventListener('click', function () {
            if (state.quizActive >= 0) syncActiveQuestionFromEditor();
            var q = readNewQuestionFromForm();
            if (!q) return;
            if (isExamSection() && !q.points) q.points = 5;
            state.questions.push(q);
            clearNewForm();
            setQuizActive(state.questions.length - 1);
            updateQuizSummary();
        });
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
