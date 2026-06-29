(function () {
    'use strict';

    var root = document.querySelector('[data-ap-collab-page]');
    if (!root) return;

    var form = document.getElementById('ap-collaborators-form');
    var hidden = document.getElementById('ap-collab-grants-hidden');
    var emailInput = form ? form.querySelector('input[name="email"]') : null;
    var datalist = document.getElementById('ap-staff-suggest');
    var searchUrl = root.getAttribute('data-search-url') || '';
    var formTitle = document.getElementById('ap-collab-form-title');
    var formHint = document.getElementById('ap-collab-form-hint');
    var grantSummary = document.getElementById('ap-collab-grant-summary');
    var debounce = null;

    function updateGrantSummary() {
        if (!grantSummary || !form) return;
        var count = 0;
        form.querySelectorAll('.ap-collab-grant-select').forEach(function (sel) {
            if (sel.value) count++;
        });
        grantSummary.textContent = count > 0
            ? 'Выбрано прав: ' + count + (count === 1 ? ' область' : count < 5 ? ' области' : ' областей')
            : 'Права не выбраны — выберите хотя бы один модуль или раздел';
        grantSummary.classList.toggle('is-empty', count === 0);
    }

    function setPermValue(select, value) {
        select.value = value || '';
        var row = select.closest('.ap-collab-perm-row');
        if (!row) return;
        row.querySelectorAll('.ap-collab-perm-btn').forEach(function (btn) {
            var active = btn.getAttribute('data-value') === (value || '');
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        updateGrantSummary();
    }

    function bindPermButtons() {
        if (!form) return;
        form.querySelectorAll('.ap-collab-perm-row').forEach(function (row) {
            var select = row.querySelector('.ap-collab-grant-select');
            if (!select) return;
            row.querySelectorAll('.ap-collab-perm-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setPermValue(select, btn.getAttribute('data-value') || '');
                });
            });
            setPermValue(select, select.value);
        });
    }

    function fillFormFromCard(card) {
        if (!form || !emailInput) return;
        var email = card.getAttribute('data-email') || '';
        var name = card.getAttribute('data-name') || '';
        var grantsRaw = card.getAttribute('data-grants') || '[]';
        var grants = [];
        try {
            grants = JSON.parse(grantsRaw);
        } catch (e) {
            grants = [];
        }

        emailInput.value = email;
        form.querySelectorAll('.ap-collab-grant-select').forEach(function (sel) {
            setPermValue(sel, '');
        });

        grants.forEach(function (g) {
            var type = g.resource_type || '';
            var rid = g.resource_id != null ? String(g.resource_id) : '';
            var perm = g.permission || '';
            var sel = form.querySelector(
                '.ap-collab-grant-select[data-resource-type="' + type + '"][data-resource-id="' + rid + '"]'
            );
            if (sel) setPermValue(sel, perm);
        });

        if (formTitle) {
            formTitle.textContent = name ? 'Права: ' + name : 'Изменить права соавтора';
        }
        if (formHint) {
            formHint.textContent = 'Обновите доступ по модулям и разделам, затем нажмите «Сохранить».';
        }

        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        emailInput.focus();
    }

    root.querySelectorAll('[data-ap-collab-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('[data-ap-collab-card]');
            if (card) fillFormFromCard(card);
        });
    });

    var resetBtn = document.getElementById('ap-collab-form-reset');
    if (resetBtn && form) {
        resetBtn.addEventListener('click', function () {
            form.reset();
            form.querySelectorAll('.ap-collab-grant-select').forEach(function (sel) {
                setPermValue(sel, '');
            });
            if (formTitle) formTitle.textContent = 'Добавить соавтора';
            if (formHint) {
                formHint.textContent = 'Укажите email коллеги и выберите, какие части курса он может просматривать или редактировать.';
            }
        });
    }

    if (emailInput && datalist && searchUrl) {
        emailInput.addEventListener('input', function () {
            clearTimeout(debounce);
            var q = emailInput.value.trim();
            if (q.length < 2) return;
            debounce = setTimeout(function () {
                fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        datalist.innerHTML = '';
                        (data.items || []).forEach(function (item) {
                            var opt = document.createElement('option');
                            opt.value = item.email;
                            opt.label = item.name + ' (' + item.role + ')';
                            datalist.appendChild(opt);
                        });
                    })
                    .catch(function () {});
            }, 250);
        });
    }

    if (form && hidden) {
        form.addEventListener('submit', function () {
            hidden.innerHTML = '';
            var idx = 0;
            form.querySelectorAll('.ap-collab-grant-select').forEach(function (sel) {
                var perm = sel.value;
                if (!perm) return;
                var type = sel.getAttribute('data-resource-type');
                var rid = sel.getAttribute('data-resource-id');
                ['resource_type', 'resource_id', 'permission'].forEach(function (field) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'grants[' + idx + '][' + field + ']';
                    if (field === 'resource_type') input.value = type;
                    else if (field === 'resource_id') input.value = rid || '';
                    else input.value = perm;
                    hidden.appendChild(input);
                });
                idx++;
            });
        });
    }

    root.querySelectorAll('.ap-collab-module-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var panel = btn.closest('.ap-collab-module');
            if (!panel) return;
            var body = panel.querySelector('.ap-collab-module__body');
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            if (body) body.hidden = expanded;
        });
    });

    bindPermButtons();
    updateGrantSummary();
})();
