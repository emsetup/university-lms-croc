@extends('layouts.admin')

@section('title', 'Админ: редактор вопросов')

@section('content')
    <div class="card" style="max-width:1200px;margin:0 auto 1rem">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <p class="muted" style="margin:0 0 0.35rem">
                    <a href="{{ route('admin.course.settings', $ap ?? []) }}">← К модулям</a>
                </p>
                <h1 style="margin:0 0 0.35rem">{{ $title }}</h1>
                @if ($scope === 'module')
                    <p class="muted" style="margin:0">Модуль <strong>{{ $module }}</strong>. Режим: <strong>{{ $kind }}</strong>.</p>
                @else
                    <p class="muted" style="margin:0">Вопросы финальной страницы (без модулей).</p>
                @endif
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                @if ($scope === 'module' && in_array($kind ?? '', ['theory_quiz', 'module_exam'], true))
                    <a class="btn btn-ghost" href="{{ route('admin.quiz.export.module', array_merge($ap ?? [], ['module' => $module, 'kind' => $kind])) }}">Скачать Excel</a>
                    <a class="btn btn-ghost" href="{{ route('admin.quiz.export.module.word', array_merge($ap ?? [], ['module' => $module, 'kind' => $kind])) }}">Скачать Word</a>
                @elseif ($scope === 'final')
                    <a class="btn btn-ghost" href="{{ route('admin.quiz.export.final', $ap ?? []) }}">Скачать Excel</a>
                    <a class="btn btn-ghost" href="{{ route('admin.quiz.export.final.word', $ap ?? []) }}">Скачать Word</a>
                @endif
                @if(empty($isReadOnly))
                    <button type="button" class="btn btn-primary js-qb-save">Сохранить</button>
                    <button type="button" class="btn btn-ghost js-qb-add">Добавить вопрос</button>
                @else
                    <p class="muted" style="margin:0">Режим просмотра (тестировщик курса): сохранение недоступно.</p>
                @endif
            </div>
        </div>
        <p class="muted small" style="margin:0.75rem 0 0;line-height:1.5">
            Типы: <strong>один ответ</strong> (c — число), <strong>несколько</strong> (c — список индексов), <strong>сопоставление</strong> (match_drag + left/right).
            Для экзамена можно задавать <strong>points</strong>. Для сопоставления редактируйте пары слева/справа в одной строке.
        </p>
    </div>

    <div class="card" style="max-width:1200px;margin:0 auto">
        <div class="qb-layout">
            <aside class="qb-side">
                <div class="qb-side__head">
                    <div class="qb-side__title">Вопросы</div>
                    <div class="muted small" id="qb-count"></div>
                </div>
                <ul class="qb-list" id="qb-list"></ul>
            </aside>
            <main class="qb-main">
                <div class="qb-toolbar">
                    <div class="qb-toolbar__left">
                        <span class="muted small">Вопрос</span>
                        <strong id="qb-active-title">—</strong>
                    </div>
                    <div class="qb-toolbar__right">
                        @if(empty($isReadOnly))
                            <button type="button" class="btn btn-ghost js-qb-dup">Дублировать</button>
                            <button type="button" class="btn btn-ghost js-qb-del">Удалить</button>
                        @endif
                    </div>
                </div>

                <div id="qb-editor" class="qb-editor" hidden>
                    <label class="qb-field">
                        <span class="qb-label">Тип вопроса</span>
                        <select id="qb-type" class="qb-input">
                            <option value="single">Один ответ</option>
                            <option value="multi">Несколько ответов</option>
                            <option value="match_drag">Сопоставление (drag)</option>
                        </select>
                    </label>

                    <label class="qb-field" id="qb-points-wrap" hidden>
                        <span class="qb-label">Баллы (points)</span>
                        <input id="qb-points" type="number" min="0" step="1" class="qb-input" placeholder="например, 5">
                    </label>

                    <label class="qb-field">
                        <span class="qb-label">Текст вопроса (Markdown)</span>
                        <textarea id="qb-q" class="qb-textarea" rows="7"></textarea>
                    </label>

                    <div id="qb-answers-wrap">
                        <div class="qb-section-head">
                            <div class="qb-section-title">Варианты ответов</div>
                            @if(empty($isReadOnly))
                                <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                                    <button type="button" class="btn btn-ghost js-a-add">Добавить вариант</button>
                                </div>
                            @endif
                        </div>
                        <div class="muted small" style="margin:0.25rem 0 0.5rem" id="qb-c-hint"></div>
                        <ul class="qb-answers" id="qb-answers"></ul>
                    </div>

                    <div id="qb-match-wrap" hidden>
                        <div class="qb-section-head">
                            <div class="qb-section-title">Пары для сопоставления</div>
                            @if(empty($isReadOnly))
                                <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                                    <button type="button" class="btn btn-ghost js-m-add">Добавить пару</button>
                                </div>
                            @endif
                        </div>
                        <div class="muted small" style="margin:0.25rem 0 0.5rem">
                            Важно: элемент слева в строке должен соответствовать описанию справа в той же строке (индексы совпадают).
                        </div>
                        <div class="qb-match" id="qb-match"></div>
                    </div>
                </div>

                <div class="muted" id="qb-empty" style="padding:1rem 0">Выберите вопрос слева@if(empty($isReadOnly)) или нажмите «Добавить вопрос»@endif.</div>
            </main>
        </div>
    </div>

    <div class="ap-modal" id="ap-qb-del-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-qb-del-title">
        <div class="ap-modal__backdrop" data-ap-qb-del-close tabindex="-1"></div>
        <div class="ap-modal__panel">
            <button type="button" class="ap-modal__close" data-ap-qb-del-close aria-label="Закрыть">&times;</button>
            <h2 id="ap-qb-del-title" class="ap-modal__title">Удалить вопрос?</h2>
            <p class="ap-muted">Вопрос будет удалён из черновика. Чтобы отменить после сохранения, используйте историю в репозитории.</p>
            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-qb-del-close>Отмена</button>
                <button type="button" class="btn btn-primary" id="ap-qb-del-confirm" style="background:#b91c1c;border-color:#b91c1c">Удалить</button>
            </div>
        </div>
    </div>

    <style>
        .qb-layout { display:grid; grid-template-columns: 340px minmax(0,1fr); gap: 1rem; }
        @media (max-width: 980px) { .qb-layout { grid-template-columns: 1fr; } }
        .qb-side { border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; overflow:hidden; }
        .qb-side__head { padding: 0.75rem 0.85rem; border-bottom: 1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:baseline; gap:0.75rem; }
        .qb-side__title { font-weight: 800; }
        .qb-list { list-style:none; margin:0; padding:0; max-height: 70vh; overflow:auto; }
        .qb-item { padding: 0.55rem 0.7rem; border-bottom: 1px solid #f1f5f9; cursor:pointer; display:flex; gap:0.55rem; align-items:flex-start; }
        .qb-item:hover { background: #f8fafc; }
        .qb-item.is-active { background: #ecfeff; }
        .qb-item__n { font-weight: 800; color:#0f766e; min-width: 2.1rem; text-align:right; }
        .qb-item__text { font-size: 0.9rem; line-height: 1.25; color:#0f172a; }
        .qb-item__meta { font-size: 0.75rem; color:#64748b; margin-top: 0.1rem; }
        .qb-drag { width: 14px; height: 14px; margin-top: 0.15rem; opacity: 0.45; }
        .qb-main { border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; padding: 0.9rem 1rem; }
        .qb-toolbar { display:flex; justify-content:space-between; gap:0.75rem; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem; margin-bottom:0.75rem; }
        .qb-toolbar__left { display:flex; flex-direction:column; gap:0.1rem; }
        .qb-editor { display:grid; gap: 0.75rem; }
        .qb-field { display:grid; gap: 0.35rem; }
        .qb-label { font-weight: 700; font-size: 0.92rem; }
        .qb-input { width:100%; padding:0.55rem 0.6rem; border:1px solid #cbd5e1; border-radius:10px; font-size:0.95rem; background:#fff; }
        .qb-textarea { width:100%; padding:0.6rem 0.7rem; border:1px solid #cbd5e1; border-radius:10px; font-size:0.95rem; background:#fff; line-height:1.45; }
        .qb-section-head { display:flex; justify-content:space-between; align-items:center; gap:0.75rem; flex-wrap:wrap; margin-top: 0.25rem; }
        .qb-section-title { font-weight: 800; }
        .qb-answers { list-style:none; margin:0; padding:0; display:grid; gap:0.5rem; }
        .qb-a { display:grid; grid-template-columns: 1.2rem 1fr auto; gap:0.5rem; align-items:center; padding:0.45rem 0.5rem; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; }
        .qb-a input[type=\"text\"] { width:100%; padding:0.45rem 0.55rem; border:1px solid #cbd5e1; border-radius:8px; background:#fff; }
        .qb-a__move { cursor: grab; opacity:0.55; user-select:none; }
        .qb-a__del { font-size:0.82rem; padding:0.25rem 0.55rem; }
        .qb-match { display:grid; gap:0.5rem; }
        .qb-mrow { display:grid; grid-template-columns: 1fr 1fr auto; gap:0.5rem; align-items:start; padding:0.5rem; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; }
        .qb-mrow input, .qb-mrow textarea { width:100%; padding:0.45rem 0.55rem; border:1px solid #cbd5e1; border-radius:8px; background:#fff; font-size:0.92rem; line-height:1.35; }
        .qb-mrow textarea { min-height: 3.2rem; resize: vertical; }
    </style>

    <script>
        (function () {
            var scope = @json($scope);
            var module = @json($module);
            var kind = @json($kind);
            var initial = @json(array_values($questions));

            var saveBtn = document.querySelector('.js-qb-save');
            var addBtn = document.querySelector('.js-qb-add');
            var dupBtn = document.querySelector('.js-qb-dup');
            var delBtn = document.querySelector('.js-qb-del');
            var list = document.getElementById('qb-list');
            var countEl = document.getElementById('qb-count');
            var editor = document.getElementById('qb-editor');
            var empty = document.getElementById('qb-empty');
            var activeTitle = document.getElementById('qb-active-title');

            var typeSel = document.getElementById('qb-type');
            var qText = document.getElementById('qb-q');
            var pointsWrap = document.getElementById('qb-points-wrap');
            var pointsInput = document.getElementById('qb-points');

            var answersWrap = document.getElementById('qb-answers-wrap');
            var answersList = document.getElementById('qb-answers');
            var cHint = document.getElementById('qb-c-hint');

            var matchWrap = document.getElementById('qb-match-wrap');
            var matchBox = document.getElementById('qb-match');

            var aAdd = document.querySelector('.js-a-add');
            var mAdd = document.querySelector('.js-m-add');

            var state = {
                questions: Array.isArray(initial) ? initial : [],
                active: -1,
                dirty: false,
            };

            function setDirty(v) {
                state.dirty = v;
                if (saveBtn) saveBtn.textContent = v ? 'Сохранить *' : 'Сохранить';
            }

            function normalizeQuestion(q) {
                if (!q || typeof q !== 'object') q = {};
                if (typeof q.q !== 'string') q.q = '';
                if (!Array.isArray(q.a)) q.a = [];
                return q;
            }

            function guessType(q) {
                if (q && q.match_drag) return 'match_drag';
                if (q && Array.isArray(q.c)) return 'multi';
                return 'single';
            }

            function short(s) {
                s = (s || '').replace(/\\s+/g, ' ').trim();
                if (s.length > 80) return s.slice(0, 77) + '…';
                return s || '(без текста)';
            }

            function renderList() {
                if (!list) return;
                list.innerHTML = '';
                var n = state.questions.length;
                if (countEl) countEl.textContent = n + ' шт.';
                state.questions.forEach(function (q, i) {
                    q = normalizeQuestion(q);
                    var li = document.createElement('li');
                    li.className = 'qb-item' + (i === state.active ? ' is-active' : '');
                    li.draggable = true;
                    li.dataset.idx = String(i);
                    li.innerHTML =
                        '<div class=\"qb-item__n\">' + (i + 1) + '</div>' +
                        '<div style=\"min-width:0\">' +
                        '<div class=\"qb-item__text\">' + escapeHtml(short(q.q)) + '</div>' +
                        '<div class=\"qb-item__meta\">' + escapeHtml(guessType(q)) + '</div>' +
                        '</div>';
                    li.addEventListener('click', function () { setActive(i); });
                    li.addEventListener('dragstart', onDragStart);
                    li.addEventListener('dragover', onDragOver);
                    li.addEventListener('drop', onDrop);
                    list.appendChild(li);
                });
            }

            var dragFrom = null;
            function onDragStart(e) {
                dragFrom = parseInt(this.dataset.idx || '-1', 10);
                e.dataTransfer.effectAllowed = 'move';
            }
            function onDragOver(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            }
            function onDrop(e) {
                e.preventDefault();
                var to = parseInt(this.dataset.idx || '-1', 10);
                if (!Number.isFinite(dragFrom) || dragFrom < 0 || to < 0 || dragFrom === to) return;
                var item = state.questions.splice(dragFrom, 1)[0];
                state.questions.splice(to, 0, item);
                if (state.active === dragFrom) state.active = to;
                setDirty(true);
                renderList();
            }

            function setActive(i) {
                state.active = i;
                renderList();
                renderEditor();
            }

            function renderEditor() {
                if (state.active < 0 || state.active >= state.questions.length) {
                    if (editor) editor.hidden = true;
                    if (empty) empty.hidden = false;
                    if (activeTitle) activeTitle.textContent = '—';
                    return;
                }
                if (editor) editor.hidden = false;
                if (empty) empty.hidden = true;
                var q = normalizeQuestion(state.questions[state.active]);
                state.questions[state.active] = q;

                if (activeTitle) activeTitle.textContent = '#' + (state.active + 1);
                var t = guessType(q);
                if (typeSel) typeSel.value = t;
                if (qText) qText.value = q.q || '';

                // points only for module_exam
                var allowPoints = (scope === 'module' && kind === 'module_exam');
                if (pointsWrap) pointsWrap.hidden = !allowPoints;
                if (allowPoints && pointsInput) pointsInput.value = (q.points != null) ? String(q.points) : '';

                if (t === 'match_drag') {
                    answersWrap.hidden = true;
                    matchWrap.hidden = false;
                    renderMatch(q);
                } else {
                    answersWrap.hidden = false;
                    matchWrap.hidden = true;
                    renderAnswers(q, t);
                }
            }

            function renderAnswers(q, t) {
                if (!answersList) return;
                answersList.innerHTML = '';
                if (!Array.isArray(q.a)) q.a = [];
                var c = q.c;
                if (t === 'multi') {
                    if (!Array.isArray(c)) c = [];
                    if (cHint) cHint.textContent = 'Отметьте все верные варианты. Обучающемуся автоматически покажется подсказка «Выберите все правильные варианты ответа».';
                } else {
                    if (Array.isArray(c)) c = (c[0] != null) ? c[0] : null;
                    if (cHint) cHint.textContent = 'Выберите один верный вариант.';
                }

                q.a.forEach(function (opt, idx) {
                    var li = document.createElement('li');
                    li.className = 'qb-a';
                    var mark = document.createElement('input');
                    mark.type = (t === 'multi') ? 'checkbox' : 'radio';
                    mark.name = 'qb-c';
                    mark.checked = (t === 'multi') ? (Array.isArray(c) && c.indexOf(idx) !== -1) : (c === idx);
                    mark.addEventListener('change', function () {
                        var qq = state.questions[state.active];
                        if (t === 'multi') {
                            var cur = Array.isArray(qq.c) ? qq.c.slice() : [];
                            if (mark.checked) {
                                if (cur.indexOf(idx) === -1) cur.push(idx);
                            } else {
                                cur = cur.filter(function (x) { return x !== idx; });
                            }
                            cur.sort(function(a,b){return a-b;});
                            qq.c = cur;
                        } else {
                            qq.c = idx;
                        }
                        setDirty(true);
                    });

                    var inp = document.createElement('input');
                    inp.type = 'text';
                    inp.value = String(opt || '');
                    inp.addEventListener('input', function () {
                        state.questions[state.active].a[idx] = inp.value;
                        setDirty(true);
                        renderList();
                    });

                    var del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'btn btn-ghost qb-a__del';
                    del.textContent = 'Удалить';
                    del.addEventListener('click', function () {
                        var qq = state.questions[state.active];
                        qq.a.splice(idx, 1);
                        // поправим c
                        if (Array.isArray(qq.c)) {
                            qq.c = qq.c.filter(function (x) { return x !== idx; }).map(function (x) { return x > idx ? x - 1 : x; });
                        } else if (typeof qq.c === 'number') {
                            if (qq.c === idx) qq.c = 0;
                            else if (qq.c > idx) qq.c = qq.c - 1;
                        }
                        setDirty(true);
                        renderEditor();
                        renderList();
                    });

                    li.appendChild(mark);
                    li.appendChild(inp);
                    li.appendChild(del);
                    answersList.appendChild(li);
                });
            }

            function renderMatch(q) {
                if (!matchBox) return;
                matchBox.innerHTML = '';
                if (!Array.isArray(q.left)) q.left = [];
                if (!Array.isArray(q.right)) q.right = [];
                var n = Math.max(q.left.length, q.right.length);
                for (var i = 0; i < n; i++) {
                    if (q.left[i] == null) q.left[i] = '';
                    if (q.right[i] == null) q.right[i] = '';
                }
                q.match_drag = true;
                q.c = 0;
                q.a = [];

                for (var i2 = 0; i2 < n; i2++) {
                    (function (idx) {
                        var row = document.createElement('div');
                        row.className = 'qb-mrow';
                        var left = document.createElement('input');
                        left.type = 'text';
                        left.value = String(q.left[idx] || '');
                        left.addEventListener('input', function () {
                            state.questions[state.active].left[idx] = left.value;
                            setDirty(true);
                        });
                        var right = document.createElement('textarea');
                        right.value = String(q.right[idx] || '');
                        right.addEventListener('input', function () {
                            state.questions[state.active].right[idx] = right.value;
                            setDirty(true);
                        });
                        var del = document.createElement('button');
                        del.type = 'button';
                        del.className = 'btn btn-ghost qb-a__del';
                        del.textContent = 'Удалить';
                        del.addEventListener('click', function () {
                            var qq = state.questions[state.active];
                            qq.left.splice(idx, 1);
                            qq.right.splice(idx, 1);
                            setDirty(true);
                            renderEditor();
                        });
                        row.appendChild(left);
                        row.appendChild(right);
                        row.appendChild(del);
                        matchBox.appendChild(row);
                    })(i2);
                }
            }

            function escapeHtml(s) {
                return String(s).replace(/[&<>\"']/g, function (c) {
                    return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]) || c;
                });
            }

            function addQuestion() {
                var q = { q: '', a: [''], c: 0 };
                if (scope === 'module' && kind === 'module_exam') {
                    q.points = 5;
                }
                state.questions.push(q);
                setDirty(true);
                setActive(state.questions.length - 1);
            }

            function dupQuestion() {
                if (state.active < 0) return;
                var src = state.questions[state.active] || {};
                var copy = JSON.parse(JSON.stringify(src));
                state.questions.splice(state.active + 1, 0, copy);
                setDirty(true);
                setActive(state.active + 1);
            }

            function delQuestion() {
                if (state.active < 0) return;
                state.questions.splice(state.active, 1);
                setDirty(true);
                state.active = Math.min(state.active, state.questions.length - 1);
                renderList();
                renderEditor();
            }

            function save() {
                if (!saveBtn) return;
                saveBtn.disabled = true;
                var url;
                if (scope === 'final') {
                    url = '{{ route('admin.quiz.save.final', $ap ?? []) }}';
                } else {
                    url = '{{ route('admin.quiz.save.module', array_merge($ap ?? [], ['module' => $module ?: 1, 'kind' => $kind])) }}';
                }
                var payload = { questions: state.questions };
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin'
                }).then(function (r) {
                    if (r.ok) {
                        return r.json();
                    }
                    return r.json().then(function (j) { throw j; });
                }).then(function () {
                    setDirty(false);
                    window.location.reload();
                }).catch(function (err) {
                    saveBtn.disabled = false;
                    alert((err && err.message) ? err.message : 'Не удалось сохранить.');
                });
            }

            function syncFromInputs() {
                if (state.active < 0) return;
                var q = state.questions[state.active];
                q.q = qText ? qText.value : q.q;
                var t = typeSel ? typeSel.value : guessType(q);
                if (t === 'match_drag') {
                    q.match_drag = true;
                    delete q.a;
                    delete q.c;
                    if (!Array.isArray(q.left)) q.left = [''];
                    if (!Array.isArray(q.right)) q.right = [''];
                } else {
                    delete q.match_drag;
                    delete q.left;
                    delete q.right;
                    if (!Array.isArray(q.a) || q.a.length === 0) q.a = [''];
                    if (t === 'multi') {
                        if (!Array.isArray(q.c)) q.c = [];
                    } else {
                        if (Array.isArray(q.c)) q.c = 0;
                        if (typeof q.c !== 'number') q.c = 0;
                    }
                }
                if (scope === 'module' && kind === 'module_exam') {
                    var pts = pointsInput ? parseInt(pointsInput.value || '0', 10) : 0;
                    if (Number.isFinite(pts) && pts > 0) q.points = pts;
                    else delete q.points;
                } else {
                    delete q.points;
                }
                setDirty(true);
                renderList();
                renderEditor();
            }
            if (addBtn) addBtn.addEventListener('click', addQuestion);
            if (dupBtn) dupBtn.addEventListener('click', dupQuestion);
            var delModal = document.getElementById('ap-qb-del-modal');
            var delConfirm = document.getElementById('ap-qb-del-confirm');
            function openQbDelModal() {
                if (!delModal) return;
                delModal.classList.add('is-open');
                delModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('ap-modal-open');
            }
            function closeQbDelModal() {
                if (!delModal) return;
                delModal.classList.remove('is-open');
                delModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('ap-modal-open');
            }
            document.querySelectorAll('[data-ap-qb-del-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
                    closeQbDelModal();
                });
            });
            if (delBtn) delBtn.addEventListener('click', function () { openQbDelModal(); });
            if (delConfirm) delConfirm.addEventListener('click', function () {
                delQuestion();
                closeQbDelModal();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape' || !delModal || !delModal.classList.contains('is-open')) return;
                closeQbDelModal();
                e.preventDefault();
            });
            if (saveBtn) saveBtn.addEventListener('click', save);
            if (typeSel) typeSel.addEventListener('change', syncFromInputs);
            if (qText) qText.addEventListener('input', function () {
                if (state.active >= 0) {
                    state.questions[state.active].q = qText.value;
                    setDirty(true);
                    renderList();
                }
            });
            if (pointsInput) pointsInput.addEventListener('input', function () { if (state.active >= 0) setDirty(true); });
            if (aAdd) aAdd.addEventListener('click', function () {
                if (state.active < 0) return;
                var q = state.questions[state.active];
                if (!Array.isArray(q.a)) q.a = [];
                q.a.push('');
                setDirty(true);
                renderEditor();
                renderList();
            });
            if (mAdd) mAdd.addEventListener('click', function () {
                if (state.active < 0) return;
                var q = state.questions[state.active];
                if (!Array.isArray(q.left)) q.left = [];
                if (!Array.isArray(q.right)) q.right = [];
                q.left.push('');
                q.right.push('');
                q.match_drag = true;
                setDirty(true);
                renderEditor();
            });

            window.addEventListener('beforeunload', function (e) {
                if (!state.dirty) return;
                e.preventDefault();
                e.returnValue = '';
            });

            renderList();
            renderEditor();
        })();
    </script>
@endsection

