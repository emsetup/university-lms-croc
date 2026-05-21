/**
 * Конструктор check.sh для мастера образа (practice-image-edit).
 */
(function (global) {
    'use strict';

    function initCheckWizard(deps) {
        var checkEditor = deps.checkEditor;
        var taskBody = deps.taskBody;
        var checkTaskTypes = deps.checkTaskTypes || [];
        var checkExampleGrids = deps.checkExampleGrids || [];
        var checkById = deps.checkById || {};
        var commonServices = deps.checkCommonServices || [];
        var serviceStates = deps.checkServiceStates || [
            { id: 'active', label: 'Запущена (is-active)' },
            { id: 'enabled', label: 'В автозагрузке (is-enabled)' },
            { id: 'inactive', label: 'Не запущена' },
        ];
        var getHelpersSelected = deps.getHelpersSelected || function () { return []; };

        var checkTypeById = {};
        checkTaskTypes.forEach(function (t) { checkTypeById[t.id] = t; });

        var checkModal = document.getElementById('piwiz-check-modal');
        var checkModalCtx = { tr: null, mode: 'type', picked: null };

        function fillTemplate(str, n) {
            return String(str || '').replace(/\{n\}/g, String(n));
        }

        function defaultTask(n, points, typeId) {
            typeId = typeId || 'file_exists';
            var tt = checkTypeById[typeId] || checkTypeById.file_exists;
            var nn = String(n);
            return {
                num: n,
                points: points || 25,
                type: typeId,
                file: fillTemplate(tt.default_file, nn),
                pattern: fillTemplate(tt.default_pattern, nn),
                hint: fillTemplate(tt.default_hint, nn),
            };
        }

        function taskFromRow(tr) {
            if (!tr || !tr.classList.contains('piwiz-task-main')) return null;
            var g = function (f) {
                var el = tr.querySelector('[data-f="' + f + '"]');
                return el ? el.value.trim() : '';
            };
            return {
                num: parseInt(tr.querySelector('.piwiz-task-num')?.textContent, 10) || 1,
                points: parseInt(g('points'), 10) || 0,
                type: g('type') || 'file_exists',
                file: g('file'),
                pattern: g('pattern'),
                hint: g('hint'),
            };
        }

        function taskPrimary(t) {
            var f = (t.file || '').trim();
            var p = (t.pattern || '').trim();
            if (t.type === 'command' || t.type === 'service_active' || t.type === 'package_installed') {
                return f || p;
            }
            return f;
        }

        function describeCheckHuman(t) {
            var n = t.num || '?';
            var primary = taskPrimary(t);
            var secondary = (t.pattern || '').trim();
            if (t.type === 'file_exists') {
                return 'Задание ' + n + ': в контейнере должен существовать файл «' + primary + '».';
            }
            if (t.type === 'file_readable') {
                return 'Задание ' + n + ': файл «' + primary + '» существует и читается.';
            }
            if (t.type === 'file_contains') {
                return 'Задание ' + n + ': в файле «' + primary + '» есть строка «' + secondary + '» (grep).';
            }
            if (t.type === 'file_regex') {
                return 'Задание ' + n + ': в «' + primary + '» совпадение с regex «' + secondary + '».';
            }
            if (t.type === 'command') {
                return 'Задание ' + n + ': команда «' + primary + '» завершается с кодом 0.';
            }
            if (t.type === 'service_active') {
                var st = serviceStates.find(function (s) { return s.id === (secondary || 'active'); });
                var stLabel = st ? st.label : (secondary || 'active');
                return 'Задание ' + n + ': служба «' + primary + '» — ' + stLabel + '.';
            }
            if (t.type === 'package_installed') {
                return 'Задание ' + n + ': установлен пакет «' + primary + '».';
            }
            return 'Задание ' + n;
        }

        function describeCheckBash(t) {
            return emitTaskCheck(t, t.num || 1).split('\n').slice(1).join('\n');
        }

        function updateRowPreview(tr) {
            var how = tr && tr.nextElementSibling;
            if (!how || !how.classList.contains('piwiz-task-how')) return;
            var t = taskFromRow(tr);
            if (!t) return;
            var human = how.querySelector('.piwiz-task-how__text');
            var code = how.querySelector('.piwiz-task-how__code');
            if (human) human.textContent = describeCheckHuman(t);
            if (code) code.textContent = describeCheckBash(t);
        }

        function syncServiceUi(tr) {
            var pick = tr.querySelector('[data-role="svc-pick"]');
            var cell = tr.querySelector('.piwiz-task-param-cell');
            var fileInp = tr.querySelector('[data-f="file"]');
            if (!cell || !pick) return;
            var custom = pick.value === '__custom__' || (pick.value === '' && fileInp && fileInp.value.trim() !== '');
            cell.classList.toggle('is-svc-custom', custom);
        }

        function serviceSelectHtml(selected) {
            var opts = '<option value="">— выберите —</option>';
            commonServices.forEach(function (s) {
                var sel = s === selected ? ' selected' : '';
                opts += '<option value="' + deps.escAttr(s) + '"' + sel + '>' + deps.escAttr(s) + '</option>';
            });
            opts += '<option value="__custom__"' + (selected && commonServices.indexOf(selected) < 0 ? ' selected' : '') + '>Другое…</option>';
            return '<select class="piwiz-task-inp piwiz-task-svc-select" data-role="svc-pick">' + opts + '</select>';
        }

        function stateSelectHtml(selected) {
            var opts = '';
            serviceStates.forEach(function (s) {
                var sel = (selected || 'active') === s.id ? ' selected' : '';
                opts += '<option value="' + deps.escAttr(s.id) + '"' + sel + '>' + deps.escAttr(s.label) + '</option>';
            });
            return '<select class="piwiz-task-inp" data-f="pattern">' + opts + '</select>';
        }

        function buildParamCellHtml(tt, task) {
            var val = deps.escAttr(task.file || '');
            var label = tt.param_label || 'Параметр';
            if (tt.param_widget === 'service') {
                return (
                    '<div class="piwiz-task-field">' +
                    '<span class="piwiz-task-fields__lbl">' + deps.escAttr(label) + '</span>' +
                    '<div class="piwiz-task-field__row">' + serviceSelectHtml(task.file) +
                    '<input type="text" class="piwiz-task-inp" data-f="file" value="' + val + '" placeholder="имя unit">' +
                    '</div></div>'
                );
            }
            return (
                '<div class="piwiz-task-field">' +
                '<span class="piwiz-task-fields__lbl">' + deps.escAttr(label) + '</span>' +
                '<input type="text" class="piwiz-task-inp piwiz-task-inp--wide" data-f="file" value="' + val + '" placeholder="' + deps.escAttr(tt.param_placeholder || '') + '">' +
                '</div>'
            );
        }

        function buildExtraCellHtml(tt, task) {
            if (!tt.has_extra) {
                return '<span class="piwiz-task-extra-off">—</span>';
            }
            var label = tt.param2_label || 'Доп.';
            if (tt.param2_widget === 'service_state') {
                return (
                    '<div class="piwiz-task-field">' +
                    '<span class="piwiz-task-fields__lbl">' + deps.escAttr(label) + '</span>' +
                    stateSelectHtml(task.pattern || 'active') +
                    '</div>'
                );
            }
            return (
                '<div class="piwiz-task-field">' +
                '<span class="piwiz-task-fields__lbl">' + deps.escAttr(label) + '</span>' +
                '<input type="text" class="piwiz-task-inp piwiz-task-inp--wide" data-f="pattern" placeholder="' + deps.escAttr(tt.param2_placeholder || '') + '" value="' + deps.escAttr(task.pattern || '') + '">' +
                '</div>'
            );
        }

        function wireServicePick(tr) {
            var pick = tr.querySelector('[data-role="svc-pick"]');
            var fileInp = tr.querySelector('[data-f="file"]');
            if (!pick || !fileInp) return;
            function syncFromPick() {
                if (pick.value && pick.value !== '__custom__') {
                    fileInp.value = pick.value;
                }
                syncServiceUi(tr);
                updateRowPreview(tr);
            }
            pick.addEventListener('change', syncFromPick);
            fileInp.addEventListener('input', function () {
                var v = fileInp.value.trim();
                var match = commonServices.indexOf(v) >= 0;
                if (match) pick.value = v;
                else if (v) pick.value = '__custom__';
                syncServiceUi(tr);
                updateRowPreview(tr);
            });
            syncServiceUi(tr);
        }

        function applyTypeToRow(tr, typeId, resetValues) {
            var tt = checkTypeById[typeId];
            if (!tt || !tr) return;
            var task = resetValues ? defaultTask(tr.querySelector('.piwiz-task-num')?.textContent || '1', 25, typeId) : taskFromRow(tr);
            if (!task) task = defaultTask(1, 25, typeId);

            var paramCell = tr.querySelector('.piwiz-task-param-cell');
            var extraCell = tr.querySelector('.piwiz-task-extra-cell');
            if (paramCell) {
                paramCell.innerHTML = buildParamCellHtml(tt, task);
                wireServicePick(tr);
            }
            if (extraCell) {
                extraCell.innerHTML = buildExtraCellHtml(tt, task);
                extraCell.classList.toggle('piwiz-task-extra--off', !tt.has_extra);
            }
            updateRowPreview(tr);
        }

        function renderTaskRow(task, idx) {
            var tr = document.createElement('tr');
            tr.className = 'piwiz-task-main';
            tr.dataset.idx = String(idx);
            var typeOpts = checkTaskTypes.map(function (tt) {
                var sel = tt.id === task.type ? ' selected' : '';
                return '<option value="' + tt.id + '"' + sel + '>' + tt.label + '</option>';
            }).join('');
            var tt = checkTypeById[task.type] || checkTypeById.file_exists;
            tr.innerHTML =
                '<td class="piwiz-task-num">' + (task.num || (idx + 1)) + '</td>' +
                '<td class="piwiz-task-pts"><input type="number" class="piwiz-task-inp" data-f="points" min="0" max="1000" value="' + (task.points || 25) + '"></td>' +
                '<td colspan="4">' +
                '<div class="piwiz-task-fields">' +
                '<div class="piwiz-task-fields__item piwiz-task-type-cell">' +
                '<span class="piwiz-task-fields__lbl">Тип</span>' +
                '<select class="piwiz-task-inp" data-f="type">' + typeOpts + '</select>' +
                '<button type="button" class="piwiz-task-link js-pi-check-guide">Примеры</button>' +
                '</div>' +
                '<div class="piwiz-task-fields__item piwiz-task-param-cell"></div>' +
                '<div class="piwiz-task-fields__item piwiz-task-extra-cell"></div>' +
                '<div class="piwiz-task-fields__item piwiz-task-hint-cell">' +
                '<span class="piwiz-task-fields__lbl">Подсказка студенту</span>' +
                '<input type="text" class="piwiz-task-inp piwiz-task-inp--hint" data-f="hint" value="' + deps.escAttr(task.hint || '') + '">' +
                '</div></div></td>' +
                '<td class="piwiz-task-rm-cell"><button type="button" class="piwiz-task-rm js-pi-task-rm" title="Удалить">×</button></td>';

            var how = document.createElement('tr');
            how.className = 'piwiz-task-how';
            how.innerHTML =
                '<td colspan="7">' +
                '<div class="piwiz-task-how__strip">' +
                '<button type="button" class="piwiz-task-how__toggle js-pi-task-how-toggle" aria-expanded="false">' +
                '<span class="piwiz-task-how__chevron" aria-hidden="true"></span>' +
                '<span class="piwiz-task-how__text"></span>' +
                '<span class="piwiz-task-how__hint">показать bash</span></button>' +
                '<pre class="piwiz-task-how__code" hidden></pre>' +
                '</div></td>';

            applyTypeToRow(tr, task.type, false);
            var typeSel = tr.querySelector('[data-f="type"]');
            if (typeSel) {
                typeSel.addEventListener('change', function () {
                    applyTypeToRow(tr, typeSel.value, true);
                });
            }
            tr.querySelectorAll('input, select').forEach(function (el) {
                el.addEventListener('input', function () { updateRowPreview(tr); });
                el.addEventListener('change', function () { updateRowPreview(tr); });
            });
            updateRowPreview(tr);
            return { main: tr, how: how };
        }

        function readTasksFromTable() {
            var tasks = [];
            if (!taskBody) return tasks;
            taskBody.querySelectorAll('tr.piwiz-task-main').forEach(function (tr, i) {
                var t = taskFromRow(tr);
                if (t) {
                    t.num = i + 1;
                    tasks.push(t);
                }
            });
            return tasks;
        }

        function writeTasksToTable(tasks) {
            if (!taskBody) return;
            taskBody.innerHTML = '';
            tasks.forEach(function (t, i) {
                var rows = renderTaskRow(t, i);
                taskBody.appendChild(rows.main);
                taskBody.appendChild(rows.how);
            });
        }

        function emitTaskCheck(t, n) {
            var pts = parseInt(t.points, 10) || 0;
            var lines = [];
            var hint = deps.escBash(t.hint);
            var primary = taskPrimary(t);
            var secondary = (t.pattern || '').trim();
            lines.push('# Задание ' + n);
            if (t.type === 'file_exists') {
                lines.push('F="' + deps.escBash(primary || '$STUDENT_HOME/answer.txt') + '"');
                lines.push('if [[ -f "$F" ]]; then score=$((score+' + pts + ')); ok "задание ' + n + ': файл есть"; else fail_vis "задание ' + n + ': нет $F"; hint "' + hint + '"; fi');
            } else if (t.type === 'file_readable') {
                lines.push('F="' + deps.escBash(primary) + '"');
                lines.push('if [[ -f "$F" && -r "$F" ]]; then score=$((score+' + pts + ')); ok "задание ' + n + '"; else fail_vis "задание ' + n + ': нет/не читается $F"; hint "' + hint + '"; fi');
            } else if (t.type === 'file_contains') {
                lines.push('F="' + deps.escBash(primary) + '"');
                lines.push('P="' + deps.escBash(secondary) + '"');
                lines.push('if [[ -f "$F" ]] && grep -q "$P" "$F" 2>/dev/null; then score=$((score+' + pts + ')); ok "задание ' + n + '"; else fail_vis "задание ' + n + ': нет подстроки"; hint "' + hint + '"; fi');
            } else if (t.type === 'file_regex') {
                lines.push('F="' + deps.escBash(primary) + '"');
                lines.push('if [[ -f "$F" ]] && grep -qiE "' + deps.escBash(secondary) + '" "$F" 2>/dev/null; then score=$((score+' + pts + ')); ok "задание ' + n + '"; else fail_vis "задание ' + n + '"; hint "' + hint + '"; fi');
            } else if (t.type === 'command') {
                lines.push('CMD="' + deps.escBash(primary || 'id') + '"');
                lines.push('if eval "$CMD" >/dev/null 2>&1; then score=$((score+' + pts + ')); ok "задание ' + n + '"; else fail_vis "задание ' + n + ': команда"; hint "' + hint + '"; fi');
            } else if (t.type === 'service_active') {
                var unit = primary || 'sshd';
                var state = (secondary || 'active').toLowerCase();
                lines.push('U="' + deps.escBash(unit) + '"');
                if (state === 'enabled') {
                    lines.push('if command -v systemctl >/dev/null 2>&1 && systemctl is-enabled --quiet "$U" 2>/dev/null; then score=$((score+' + pts + ')); ok "задание ' + n + ': enabled"; else fail_vis "задание ' + n + ': служба не enabled"; hint "' + hint + '"; fi');
                } else if (state === 'inactive') {
                    lines.push('if command -v systemctl >/dev/null 2>&1 && ! systemctl is-active --quiet "$U" 2>/dev/null; then score=$((score+' + pts + ')); ok "задание ' + n + ': не active"; else fail_vis "задание ' + n + ': служба ещё active"; hint "' + hint + '"; fi');
                } else {
                    lines.push('if command -v systemctl >/dev/null 2>&1 && systemctl is-active --quiet "$U" 2>/dev/null; then score=$((score+' + pts + ')); ok "задание ' + n + ': active"; else fail_vis "задание ' + n + ': служба не active"; hint "' + hint + '"; fi');
                }
            } else if (t.type === 'package_installed') {
                lines.push('PKG="' + deps.escBash(primary) + '"');
                lines.push('if rpm -q "$PKG" >/dev/null 2>&1 || dpkg -l "$PKG" 2>/dev/null | grep -q ^ii; then score=$((score+' + pts + ')); ok "задание ' + n + '"; else fail_vis "задание ' + n + ': пакет"; hint "' + hint + '"; fi');
            }
            return lines.join('\n');
        }

        function showGenStatus(msg, ok) {
            var el = document.getElementById('pi-check-gen-status');
            if (!el) return;
            el.hidden = false;
            el.textContent = msg;
            el.className = 'piwiz-check__gen-status' + (ok ? ' is-ok' : ' is-err');
        }

        function syncTasksFromCountInput() {
            var n = parseInt((document.getElementById('pi-check-task-num') || {}).value, 10) || 4;
            var max = parseInt((document.getElementById('pi-check-max') || {}).value, 10) || 100;
            var each = Math.floor(max / n) || 25;
            var tasks = readTasksFromTable();
            if (tasks.length === n) return tasks;
            var next = [];
            for (var i = 0; i < n; i++) {
                if (tasks[i]) {
                    tasks[i].num = i + 1;
                    next.push(tasks[i]);
                } else {
                    next.push(defaultTask(i + 1, each));
                }
            }
            if (tasks.length > n) next = tasks.slice(0, n);
            writeTasksToTable(next);
            return next;
        }

        function generateCheckFromTasks() {
            var tasks = syncTasksFromCountInput();
            if (!tasks.length) {
                showGenStatus('Нет заданий в таблице', false);
                return;
            }
            var maxInp = document.getElementById('pi-check-max');
            var max = parseInt(maxInp && maxInp.value, 10) || 0;
            var sum = tasks.reduce(function (a, t) { return a + (parseInt(t.points, 10) || 0); }, 0);
            if (max <= 0) max = sum > 0 ? sum : 100;
            var useHelpers = getHelpersSelected().length > 0;
            var lines = ['#!/bin/bash', 'set -uo pipefail', '', 'STUDENT_HOME="${STUDENT_HOME:-/home/student}"', 'MAX=' + max, 'score=0', ''];
            if (useHelpers) {
                getHelpersSelected().forEach(function (id) {
                    var p = checkById[id];
                    if (p && p.body) lines.push(String(p.body).trim());
                });
                lines.push('');
            }
            tasks.forEach(function (t, i) {
                lines.push(emitTaskCheck(t, i + 1));
                lines.push('');
            });
            lines.push('echo ""');
            lines.push('echo "ИТОГО: ${score} из ${MAX}"');
            lines.push('echo "===PRACTICE_RESULT_JSON==="');
            lines.push('echo "{\\"score\\":${score},\\"max\\":${MAX}}"');
            lines.push('[[ "$score" -ge 50 ]]');
            if (checkEditor) {
                checkEditor.value = lines.join('\n') + '\n';
                checkEditor.classList.add('piwiz-code--pulse');
                setTimeout(function () { checkEditor.classList.remove('piwiz-code--pulse'); }, 1400);
                checkEditor.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            showGenStatus('Скрипт check.sh сгенерирован: ' + tasks.length + ' заданий, MAX=' + max, true);
        }

        function splitPointsEvenly() {
            var tasks = readTasksFromTable();
            if (!tasks.length) return;
            var max = parseInt((document.getElementById('pi-check-max') || {}).value, 10) || 100;
            var each = Math.floor(max / tasks.length) || 1;
            var rest = max - each * tasks.length;
            tasks.forEach(function (t, i) {
                t.points = each + (i < rest ? 1 : 0);
            });
            writeTasksToTable(tasks);
        }

        function buildGridTasks() {
            var n = parseInt((document.getElementById('pi-check-task-num') || {}).value, 10) || 4;
            var max = parseInt((document.getElementById('pi-check-max') || {}).value, 10) || 100;
            var each = Math.floor(max / n) || 25;
            var tasks = [];
            for (var i = 1; i <= n; i++) tasks.push(defaultTask(i, each, 'file_exists'));
            writeTasksToTable(tasks);
            showGenStatus('Сетка из ' + n + ' файлов ~/N.txt', true);
        }

        function addQuickTask(typeId) {
            var tasks = readTasksFromTable();
            var n = tasks.length + 1;
            tasks.push(defaultTask(n, 25, typeId));
            writeTasksToTable(tasks);
            var numInp = document.getElementById('pi-check-task-num');
            if (numInp) numInp.value = String(tasks.length);
            var tt = checkTypeById[typeId];
            showGenStatus('Добавлено задание: ' + (tt && tt.label ? tt.label : typeId), true);
        }

        function closeCheckModal() {
            if (!checkModal) return;
            checkModal.hidden = true;
            checkModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('piwiz-modal-open');
        }

        function openCheckModal(mode, tr) {
            if (!checkModal || !tr) return;
            checkModalCtx = { tr: tr, mode: mode, picked: null };
            var typeId = tr.querySelector('[data-f="type"]').value;
            var tt = checkTypeById[typeId] || {};
            var titleEl = document.getElementById('piwiz-check-modal-title');
            var descEl = document.getElementById('piwiz-check-modal-desc');
            var exWrap = document.getElementById('piwiz-check-modal-examples-wrap');
            var exBox = document.getElementById('piwiz-check-modal-examples');
            var hintWrap = document.getElementById('piwiz-check-modal-hints-wrap');
            var hintBox = document.getElementById('piwiz-check-modal-hints');
            var tipEl = document.getElementById('piwiz-check-modal-tip');
            var previewEl = document.getElementById('piwiz-check-modal-preview');
            if (titleEl) titleEl.textContent = (mode === 'type' ? 'Тип проверки: ' : '') + (tt.label || 'Справка');
            if (descEl) descEl.textContent = tt.description || '';
            if (exBox) exBox.innerHTML = '';
            if (hintBox) hintBox.innerHTML = '';
            var examples = tt.examples || [];
            if (mode === 'param' || mode === 'type' || mode === 'extra') {
                if (exWrap) exWrap.hidden = !examples.length;
                examples.forEach(function (ex) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'piwiz-modal__chip';
                    btn.textContent = ex.title || 'Пример';
                    btn.addEventListener('click', function () {
                        checkModalCtx.picked = ex;
                        document.querySelectorAll('#piwiz-check-modal-examples .piwiz-modal__chip').forEach(function (c) {
                            c.classList.toggle('is-picked', c === btn);
                        });
                        if (previewEl) {
                            var tmp = {
                                type: typeId,
                                file: ex.file || '',
                                pattern: ex.pattern || '',
                                hint: ex.hint || '',
                                points: 25,
                                num: 1,
                            };
                            previewEl.textContent = emitTaskCheck(tmp, 1);
                        }
                    });
                    exBox.appendChild(btn);
                });
            } else if (mode === 'extra' && !tt.has_extra) {
                if (exWrap) exWrap.hidden = true;
            }
            if (mode === 'hint' || mode === 'type') {
                var hints = [];
                examples.forEach(function (ex) {
                    if (ex.hint) hints.push({ title: ex.title, hint: ex.hint });
                });
                if (hintWrap) hintWrap.hidden = !hints.length;
                hints.forEach(function (h) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'piwiz-modal__chip';
                    btn.textContent = h.title || 'Подсказка';
                    btn.addEventListener('click', function () {
                        checkModalCtx.picked = { hint: h.hint };
                    });
                    hintBox.appendChild(btn);
                });
            }
            if (tipEl) {
                if (mode === 'param') tipEl.textContent = 'Выберите готовый пример — поля строки заполнятся автоматически. Ниже виден фрагмент bash.';
                else if (mode === 'extra') tipEl.textContent = tt.has_extra ? ('Поле «' + (tt.param2_label || 'Доп.') + '».') : 'Для этого типа доп. поле не нужно.';
                else if (mode === 'hint') tipEl.textContent = 'Текст подсказки студенту (HINT) при ошибке проверки.';
                else tipEl.textContent = 'Смена типа подставит значения по умолчанию. Кнопка «?» у типа — описание и примеры.';
            }
            if (previewEl) previewEl.textContent = describeCheckBash(taskFromRow(tr) || { type: typeId, file: '', pattern: '', hint: '', points: 25, num: 1 });
            checkModal.hidden = false;
            checkModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('piwiz-modal-open');
        }

        function applyCheckModalPick() {
            var tr = checkModalCtx.tr;
            var pick = checkModalCtx.picked;
            if (!tr || !pick) {
                closeCheckModal();
                return;
            }
            if (pick.file !== undefined) tr.querySelector('[data-f="file"]').value = pick.file;
            if (pick.pattern !== undefined) {
                var pat = tr.querySelector('[data-f="pattern"]');
                if (pat) pat.value = pick.pattern;
            }
            if (pick.hint !== undefined) tr.querySelector('[data-f="hint"]').value = pick.hint;
            var svcPick = tr.querySelector('[data-role="svc-pick"]');
            if (svcPick && pick.file !== undefined) {
                svcPick.value = commonServices.indexOf(pick.file) >= 0 ? pick.file : '__custom__';
            }
            if (checkModalCtx.mode === 'type' && pick.file) {
                applyTypeToRow(tr, tr.querySelector('[data-f="type"]').value, false);
            }
            updateRowPreview(tr);
            closeCheckModal();
        }

        function bindEvents() {
            document.getElementById('pi-check-generate')?.addEventListener('click', generateCheckFromTasks);
            document.getElementById('pi-check-split-points')?.addEventListener('click', splitPointsEvenly);
            document.getElementById('pi-check-build-grid')?.addEventListener('click', buildGridTasks);
            document.getElementById('pi-check-add-row')?.addEventListener('click', function () {
                addQuickTask('file_exists');
            });
            document.getElementById('pi-check-reset-editor')?.addEventListener('click', function () {
                if (checkEditor) checkEditor.value = '#!/bin/bash\nset -uo pipefail\n';
            });
            document.querySelectorAll('.js-pi-check-quick').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    addQuickTask(btn.getAttribute('data-type') || 'file_exists');
                });
            });
            document.querySelectorAll('.js-pi-check-example').forEach(function (btn, idx) {
                btn.addEventListener('click', function () {
                    var grid = checkExampleGrids[idx];
                    if (!grid || !grid.tasks) return;
                    writeTasksToTable(grid.tasks.map(function (t, i) {
                        return {
                            num: i + 1,
                            points: t.points || 25,
                            type: t.type || 'file_exists',
                            file: t.file || '',
                            pattern: t.pattern || '',
                            hint: t.hint || '',
                        };
                    }));
                    document.getElementById('pi-check-task-num').value = String(grid.tasks.length);
                    showGenStatus('Загружен пример: ' + (grid.title || ''), true);
                    document.querySelector('.js-piwiz-check-tab[data-check-tab="tasks"]')?.click();
                });
            });
            if (taskBody) {
                taskBody.addEventListener('click', function (e) {
                    if (e.target.classList.contains('js-pi-task-rm')) {
                        var tr = e.target.closest('tr.piwiz-task-main');
                        if (tr) {
                            tr.nextElementSibling?.remove();
                            tr.remove();
                        }
                        var tasks = readTasksFromTable();
                        writeTasksToTable(tasks);
                        var numInp = document.getElementById('pi-check-task-num');
                        if (numInp) numInp.value = String(tasks.length);
                        return;
                    }
                    if (e.target.classList.contains('js-pi-task-how-toggle')) {
                        var howTr = e.target.closest('tr.piwiz-task-how');
                        var box = howTr && howTr.querySelector('.piwiz-task-how__strip');
                        var code = howTr && howTr.querySelector('.piwiz-task-how__code');
                        var hint = howTr && howTr.querySelector('.piwiz-task-how__hint');
                        if (box && code) {
                            var open = box.classList.toggle('is-open');
                            code.hidden = !open;
                            e.target.setAttribute('aria-expanded', open ? 'true' : 'false');
                            if (hint) hint.textContent = open ? 'скрыть bash' : 'показать bash';
                        }
                        return;
                    }
                    var tr = e.target.closest('tr.piwiz-task-main');
                    if (!tr) return;
                    if (e.target.classList.contains('js-pi-check-guide')) openCheckModal('type', tr);
                });
            }
            document.querySelectorAll('.js-piwiz-check-modal-close').forEach(function (el) {
                el.addEventListener('click', closeCheckModal);
            });
            document.getElementById('piwiz-check-modal-apply')?.addEventListener('click', applyCheckModalPick);
        }

        writeTasksToTable((function () {
            var n = parseInt((document.getElementById('pi-check-task-num') || {}).value, 10) || 4;
            var max = parseInt((document.getElementById('pi-check-max') || {}).value, 10) || 100;
            var each = Math.floor(max / n) || 25;
            var tasks = [];
            for (var i = 1; i <= n; i++) tasks.push(defaultTask(i, each));
            return tasks;
        })());
        bindEvents();

        return {
            generateCheckFromTasks: generateCheckFromTasks,
            readTasksFromTable: readTasksFromTable,
            writeTasksToTable: writeTasksToTable,
            addQuickTask: addQuickTask,
        };
    }

    global.initPracticeImageCheckWizard = initCheckWizard;
})(window);
