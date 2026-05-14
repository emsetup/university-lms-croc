/**
 * Боковая панель редактирования раздела курса (workbench «Модули»).
 */
(function () {
    'use strict';

    var TYPE_LABELS = { text: 'Теория', quiz: 'Тест', practice: 'Практика', exam: 'Экзамен' };

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
        practiceImageId: null,
        saveUrl: '',
        open: false,
    };

    function clamp(n, a, b) {
        return Math.max(a, Math.min(b, n));
    }

    function fromServerQuestion(q) {
        if (q.match_drag || (Array.isArray(q.left) && Array.isArray(q.right))) {
            return {
                type: 'match',
                q: q.q || '',
                left: Array.isArray(q.left) ? q.left.slice() : [],
                right: Array.isArray(q.right) ? q.right.slice() : [],
            };
        }
        if (Array.isArray(q.c)) {
            return { type: 'multi', q: q.q || '', options: (q.a || []).slice(), correct: q.c.slice() };
        }
        return { type: 'single', q: q.q || '', options: (q.a || []).slice(), correct: typeof q.c === 'number' ? q.c : 0 };
    }

    function toBankQuestion(item) {
        if (item.type === 'match') {
            return { q: item.q, match_drag: true, left: item.left, right: item.right };
        }
        if (item.type === 'multi') {
            return { q: item.q, a: item.options, c: item.correct };
        }
        return { q: item.q, a: item.options, c: item.correct };
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

    function renderQuizList() {
        var ul = $('ap-sec-quiz-list');
        if (!ul) return;
        ul.innerHTML = '';
        state.questions.forEach(function (item, idx) {
            var li = document.createElement('li');
            li.className = 'ap-sec-quiz-item';
            var typeL = item.type === 'match' ? 'Сопоставление' : item.type === 'multi' ? 'Несколько' : 'Один ответ';
            var optsHtml = '';
            var correctNote = '';
            if (item.type === 'match') {
                optsHtml = '<span class="ap-muted small">' + esc(String(item.left.length)) + ' пар</span>';
                correctNote = '<span class="ap-sec-quiz-correct">пары по строкам</span>';
            } else {
                item.options.forEach(function (o, oi) {
                    var isC = item.type === 'multi' ? item.correct.indexOf(oi) >= 0 : item.correct === oi;
                    optsHtml += '<div class="ap-sec-quiz-opt' + (isC ? ' ap-sec-quiz-opt--ok' : '') + '">' + esc(o) + '</div>';
                });
                if (item.type === 'multi') {
                    correctNote = '<span class="ap-sec-quiz-correct">ответы: ' + esc(item.correct.join(', ')) + '</span>';
                } else {
                    correctNote = '<span class="ap-sec-quiz-correct">верный: #' + esc(String(item.correct)) + '</span>';
                }
            }
            li.innerHTML =
                '<div class="ap-sec-quiz-item__main">' +
                '<div class="ap-sec-quiz-item__q">' +
                esc(item.q) +
                '</div>' +
                '<div class="ap-muted small">' +
                esc(typeL) +
                '</div>' +
                '<div class="ap-sec-quiz-item__opts">' +
                optsHtml +
                '</div>' +
                correctNote +
                '</div>' +
                '<div class="ap-sec-quiz-item__actions">' +
                '<button type="button" class="btn btn-ghost btn-sm" data-ap-q-edit="' +
                idx +
                '" title="Редактировать">✏</button>' +
                '<button type="button" class="btn btn-ghost btn-sm ap-mod-icon-btn--danger" data-ap-q-del="' +
                idx +
                '" title="Удалить">🗑</button>' +
                '</div>';
            ul.appendChild(li);
        });
    }

    function fillNewFormFromItem(item) {
        $('ap-new-q-text').value = item.q;
        $('ap-new-q-type').value = item.type;
        if (item.type === 'match') {
            $('ap-new-q-left').value = item.left.join('\n');
            $('ap-new-q-right').value = item.right.join('\n');
        } else {
            $('ap-new-q-opts').value = item.options.join('\n');
            $('ap-new-q-correct').value = item.type === 'multi' ? item.correct.join(',') : String(item.correct);
        }
        toggleNewQBlocks();
    }

    function toggleNewQBlocks() {
        var t = $('ap-new-q-type').value;
        $('ap-new-q-block-opts').hidden = t === 'match';
        $('ap-new-q-block-match').hidden = t !== 'match';
    }

    function readNewQuestionFromForm() {
        var t = $('ap-new-q-type').value;
        var q = $('ap-new-q-text').value.trim();
        if (!q) {
            window.alert('Введите текст вопроса.');
            return null;
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
            return { type: 'match', q: q, left: left, right: right };
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
            return { type: 'multi', q: q, options: opts, correct: idxs };
        }
        var c = parseInt(cRaw, 10);
        if (isNaN(c) || c < 0 || c >= opts.length) {
            window.alert('Индекс правильного ответа вне диапазона.');
            return null;
        }
        return { type: 'single', q: q, options: opts, correct: c };
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
        var el = $('ap-sec-quiz-summary');
        if (!el || !state.data) return;
        var n = state.questions.length;
        var parts = ['Вопросов: ' + n];
        if (!readInherit('ap-sec-inherit-att')) {
            var a = $('ap-sec-own-att').value;
            parts.push('Попытки: ' + (a ? a : '—'));
        } else parts.push('Попытки: из курса');
        if (!readInherit('ap-sec-inherit-time')) {
            parts.push('Время: ' + ($('ap-sec-own-time').value || '—') + ' мин');
        } else parts.push('Время: из курса');
        if (!readInherit('ap-sec-inherit-pass')) {
            parts.push('Проходной: ' + ($('ap-sec-own-pass').value || '—') + '%');
        } else parts.push('Проходной: из курса');
        el.textContent = parts.join(' · ');
    }

    function showContentForType(t) {
        var th = $('ap-sec-edit-content-theory');
        var qz = $('ap-sec-edit-content-quiz');
        var pr = $('ap-sec-edit-content-practice');
        if (th) th.hidden = t !== 'text';
        if (qz) qz.hidden = t !== 'quiz' && t !== 'exam';
        if (pr) pr.hidden = t !== 'practice';
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
        renderQuizList();
        updateQuizSummary();

        var chip = $('ap-sec-edit-panel-chip');
        var typ = d.section.type || 'text';
        chip.textContent = TYPE_LABELS[typ] || typ;
        chip.className = 'ap-sec-edit-panel__chip ap-sec-chip ap-sec-chip--' + typ;
        $('ap-sec-edit-panel-heading').textContent = d.section.title || 'Раздел';
        $('ap-sec-edit-panel-sub').textContent =
            'Модуль ' + (d.module.ordinal || '—') + ' · ' + (d.course.title || '');

        showContentForType($('ap-sec-set-type').value);

        $('ap-sec-edit-legacy').hidden = !d.is_legacy;
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
                switchMainTab('content');
            })
            .catch(function () {
                window.alert('Ошибка сети при загрузке панели.');
            });
    }

    function closePanel() {
        var panel = $('ap-sec-edit-panel');
        if (!panel) return;
        panel.classList.remove('is-open');
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
        var tabs = document.querySelectorAll('[data-ap-sec-tab]');
        var panS = $('ap-sec-edit-panel-pane-settings');
        var panC = $('ap-sec-edit-panel-pane-content');
        tabs.forEach(function (t) {
            var on = t.getAttribute('data-ap-sec-tab') === which;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        if (which === 'settings') {
            panS.hidden = false;
            panC.hidden = true;
        } else {
            panS.hidden = true;
            panC.hidden = false;
        }
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
        if (typ === 'quiz' || typ === 'exam') {
            p.questions = state.questions.map(toBankQuestion);
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
            var minW = 480;
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
        });
    }

    function init(root) {
        var csrf = root.getAttribute('data-ap-csrf') || '';

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

        $('ap-sec-quiz-list').addEventListener('click', function (e) {
            var del = e.target.closest('[data-ap-q-del]');
            if (del) {
                var i = parseInt(del.getAttribute('data-ap-q-del'), 10);
                state.questions.splice(i, 1);
                renderQuizList();
                updateQuizSummary();
                return;
            }
            var ed = e.target.closest('[data-ap-q-edit]');
            if (ed) {
                var j = parseInt(ed.getAttribute('data-ap-q-edit'), 10);
                var item = state.questions[j];
                if (!item) return;
                state.questions.splice(j, 1);
                fillNewFormFromItem(item);
                renderQuizList();
                updateQuizSummary();
                $('ap-new-q-text').focus();
            }
        });

        $('ap-new-q-type').addEventListener('change', toggleNewQBlocks);
        $('ap-new-q-submit').addEventListener('click', function () {
            var q = readNewQuestionFromForm();
            if (!q) return;
            state.questions.push(q);
            clearNewForm();
            renderQuizList();
            updateQuizSummary();
        });
        $('ap-sec-quiz-add').addEventListener('click', function () {
            var block = $('ap-sec-quiz-new');
            if (block) block.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            $('ap-new-q-text').focus();
        });

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
