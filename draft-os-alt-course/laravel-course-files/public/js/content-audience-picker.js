(function (global) {
    'use strict';

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function ruleKey(r) {
        return (r.subject_type || '') + ':' + (r.subject_id || 0);
    }

    function parseEmails(text) {
        return String(text || '')
            .split(/[\s,;]+/)
            .map(function (s) { return s.trim(); })
            .filter(Boolean);
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            var t = meta.getAttribute('content');
            if (t) return t;
        }
        var wb = document.querySelector('[data-ap-csrf]');
        if (wb) {
            var w = wb.getAttribute('data-ap-csrf');
            if (w) return w;
        }
        var input = document.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    /**
     * @param {HTMLElement} root
     * @param {object} opts
     */
    function ContentAudiencePicker(root, opts) {
        this.root = root;
        this.searchUrl = opts.searchUrl || '';
        this.resolveUrl = opts.resolveUrl || '';
        this.readOnly = !!opts.readOnly;
        this.rules = [];
        this.groups = { portal: [], course: [] };
        this.onChange = typeof opts.onChange === 'function' ? opts.onChange : null;
        this.render();
        this.bind();
    }

    ContentAudiencePicker.prototype.render = function () {
        var ro = this.readOnly ? ' disabled' : '';
        this.root.innerHTML =
            '<div class="ap-audience-picker">' +
            '  <div class="ap-audience-picker__mode ap-toggle-row">' +
            '    <label class="ap-toggle">' +
            '      <input type="checkbox" class="ap-toggle__input ap-audience-restricted"' + ro + '>' +
            '      <span class="ap-toggle__track"></span>' +
            '      <span class="ap-toggle__label">Ограниченный доступ</span>' +
            '    </label>' +
            '    <p class="ap-muted small ap-audience-picker__hint">По умолчанию материал видят все записанные на курс.</p>' +
            '  </div>' +
            '  <div class="ap-audience-picker__restricted" hidden>' +
            '    <p class="ap-muted small ap-audience-picker__lead">Назначьте <strong>конкретных обучающихся</strong> и/или группы. Email можно добавить заранее — даже если человек ещё не заходил в портал.</p>' +
            '    <div class="ap-audience-picker__actions">' +
            '      <div class="ap-audience-picker__dropdown-wrap">' +
            '        <button type="button" class="btn btn-secondary btn-sm ap-audience-add-learner"' + ro + '>+ Обучающийся</button>' +
            '        <div class="ap-audience-picker__menu ap-audience-learners-menu" hidden></div>' +
            '      </div>' +
            '      <div class="ap-audience-picker__dropdown-wrap">' +
            '        <button type="button" class="btn btn-ghost btn-sm ap-audience-add-course-group"' + ro + '>+ Группа курса</button>' +
            '        <div class="ap-audience-picker__menu ap-audience-course-groups-menu" hidden></div>' +
            '      </div>' +
            '      <div class="ap-audience-picker__dropdown-wrap">' +
            '        <button type="button" class="btn btn-ghost btn-sm ap-audience-add-portal-group"' + ro + '>+ Глобальная</button>' +
            '        <div class="ap-audience-picker__menu ap-audience-portal-groups-menu" hidden></div>' +
            '      </div>' +
            '    </div>' +
            '    <label class="ap-settings-label" for="ap-audience-search">Найти обучающегося (email или ФИО)</label>' +
            '    <input type="search" id="ap-audience-search" class="ap-modal__input ap-audience-search" placeholder="Email или ФИО…" autocomplete="off"' + ro + '>' +
            '    <div class="ap-audience-picker__results" hidden></div>' +
            '    <div class="ap-audience-picker__bulk">' +
            '      <label class="ap-settings-label" for="ap-audience-bulk-emails">Добавить списком (email)</label>' +
            '      <textarea id="ap-audience-bulk-emails" class="ap-modal__input ap-audience-bulk-emails" rows="3" placeholder="По одному email на строку или через запятую"' + ro + '></textarea>' +
            '      <div class="ap-audience-picker__bulk-actions">' +
            '        <button type="button" class="btn btn-secondary btn-sm ap-audience-bulk-add"' + ro + '>Добавить в список</button>' +
            '        <p class="ap-muted small ap-audience-bulk-msg ap-m0"></p>' +
            '      </div>' +
            '    </div>' +
            '    <div class="ap-audience-picker__chips-label ap-settings-label">Кому видно (люди и группы)</div>' +
            '    <div class="ap-audience-picker__chips"></div>' +
            '    <p class="ap-muted small ap-audience-picker__empty">Никто кроме выбранных не увидит этот материал.</p>' +
            '  </div>' +
            '</div>';
    };

    ContentAudiencePicker.prototype.bind = function () {
        var self = this;
        var wrap = this.root.querySelector('.ap-audience-picker__restricted');
        var toggle = this.root.querySelector('.ap-audience-restricted');
        if (toggle) {
            toggle.addEventListener('change', function () {
                wrap.hidden = !toggle.checked;
                self.emitChange();
            });
        }
        var search = this.root.querySelector('.ap-audience-search');
        if (search) {
            var timer = null;
            search.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    self.runSearch(search.value);
                }, 280);
            });
            search.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var q = String(search.value || '').trim();
                    if (q.indexOf('@') !== -1) {
                        self.resolveEmails([q], function () {
                            search.value = '';
                        });
                    } else {
                        self.runSearch(q);
                    }
                }
            });
        }
        this.root.querySelector('.ap-audience-add-learner')?.addEventListener('click', function (e) {
            e.stopPropagation();
            self.toggleLearnersMenu();
        });
        this.root.querySelector('.ap-audience-add-course-group')?.addEventListener('click', function (e) {
            e.stopPropagation();
            self.toggleMenu('course');
        });
        this.root.querySelector('.ap-audience-add-portal-group')?.addEventListener('click', function (e) {
            e.stopPropagation();
            self.toggleMenu('portal');
        });
        this.root.querySelector('.ap-audience-bulk-add')?.addEventListener('click', function () {
            self.runBulkAdd();
        });
        document.addEventListener('click', function () {
            self.closeMenus();
        });
    };

    ContentAudiencePicker.prototype.fetchJson = function (url, options) {
        return fetch(url, Object.assign({
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }, options || {})).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) {
                if (!r.ok) {
                    var msg = data && (data.message || (data.errors && Object.values(data.errors).flat().join(' ')));
                    var err = new Error(msg || ('HTTP ' + r.status));
                    err.status = r.status;
                    throw err;
                }
                return data;
            });
        });
    };

    ContentAudiencePicker.prototype.renderLearnerResult = function (it) {
        var enrolledBadge = it.enrolled
            ? '<span class="ap-audience-chip__scope">на курсе</span>'
            : '<span class="ap-audience-chip__scope ap-audience-chip__scope--muted">не на курсе</span>';
        return '<button type="button" class="ap-audience-picker__result" data-subject-type="' + escapeHtml(it.subject_type) + '" data-subject-id="' + it.subject_id + '" data-label="' + escapeHtml(it.label) + '">' +
            escapeHtml(it.label) + ' <span class="ap-muted">' + escapeHtml(it.email || '') + '</span> ' + enrolledBadge + '</button>';
    };

    ContentAudiencePicker.prototype.bindResultClicks = function (results) {
        var self = this;
        results.querySelectorAll('.ap-audience-picker__result').forEach(function (btn) {
            btn.addEventListener('click', function () {
                self.addRule({
                    subject_type: btn.getAttribute('data-subject-type'),
                    subject_id: parseInt(btn.getAttribute('data-subject-id'), 10),
                    label: btn.getAttribute('data-label'),
                    color: null,
                    scope: null,
                });
                results.hidden = true;
                var search = self.root.querySelector('.ap-audience-search');
                if (search) search.value = '';
                self.closeMenus();
            });
        });
    };

    ContentAudiencePicker.prototype.showResults = function (items, emptyText) {
        var results = this.root.querySelector('.ap-audience-picker__results');
        if (!results) return;
        if (!items || items.length === 0) {
            results.innerHTML = '<p class="ap-muted small" style="margin:0.35rem 0">' + escapeHtml(emptyText || 'Никого не найдено') + '</p>';
        } else {
            results.innerHTML = items.map(this.renderLearnerResult.bind(this)).join('');
            this.bindResultClicks(results);
        }
        results.hidden = false;
    };

    ContentAudiencePicker.prototype.toggleLearnersMenu = function () {
        var menu = this.root.querySelector('.ap-audience-learners-menu');
        if (!menu || !this.searchUrl) return;
        var self = this;
        this.closeMenus();
        menu.innerHTML = '<p class="ap-muted small" style="padding:0.5rem 0.75rem;margin:0">Загрузка…</p>';
        menu.hidden = false;
        this.fetchJson(this.searchUrl + '?q=')
            .then(function (data) {
                var items = data.items || [];
                if (items.length === 0) {
                    menu.innerHTML = '<p class="ap-muted small" style="padding:0.5rem 0.75rem;margin:0">На курсе пока никого нет. Введите email в поиске или добавьте списком.</p>';
                } else {
                    menu.innerHTML = items.map(function (it) {
                        return '<button type="button" class="ap-audience-picker__menu-item" data-subject-type="' + escapeHtml(it.subject_type) + '" data-subject-id="' + it.subject_id + '" data-label="' + escapeHtml(it.label) + '">' +
                            escapeHtml(it.label) + ' <span class="ap-muted">' + escapeHtml(it.email || '') + '</span></button>';
                    }).join('');
                    menu.querySelectorAll('.ap-audience-picker__menu-item').forEach(function (btn) {
                        btn.addEventListener('click', function (e) {
                            e.stopPropagation();
                            self.addRule({
                                subject_type: btn.getAttribute('data-subject-type'),
                                subject_id: parseInt(btn.getAttribute('data-subject-id'), 10),
                                label: btn.getAttribute('data-label'),
                                color: null,
                                scope: null,
                            });
                            self.closeMenus();
                        });
                    });
                }
            })
            .catch(function () {
                menu.innerHTML = '<p class="ap-muted small" style="padding:0.5rem 0.75rem;margin:0">Не удалось загрузить список</p>';
            });
    };

    ContentAudiencePicker.prototype.toggleMenu = function (kind) {
        var sel = kind === 'course' ? '.ap-audience-course-groups-menu' : '.ap-audience-portal-groups-menu';
        var menu = this.root.querySelector(sel);
        if (!menu) return;
        var list = kind === 'course' ? (this.groups.course || []) : (this.groups.portal || []);
        this.closeMenus();
        if (list.length === 0) {
            menu.innerHTML = '<p class="ap-muted small" style="padding:0.5rem 0.75rem;margin:0">Нет групп</p>';
        } else {
            menu.innerHTML = list.map(function (g) {
                return '<button type="button" class="ap-audience-picker__menu-item" data-subject-type="' + escapeHtml(g.subject_type) + '" data-subject-id="' + g.subject_id + '">' +
                    '<span class="ap-audience-chip__dot" style="background:' + escapeHtml(g.color || '#6366f1') + '"></span>' +
                    escapeHtml(g.label) +
                    ' <span class="ap-muted">(' + (g.member_count || 0) + ')</span></button>';
            }).join('');
            var self = this;
            menu.querySelectorAll('.ap-audience-picker__menu-item').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    self.addRule({
                        subject_type: btn.getAttribute('data-subject-type'),
                        subject_id: parseInt(btn.getAttribute('data-subject-id'), 10),
                        label: btn.textContent.trim(),
                        color: null,
                        scope: kind === 'course' ? 'course' : 'global',
                    });
                    self.closeMenus();
                });
            });
        }
        menu.hidden = false;
    };

    ContentAudiencePicker.prototype.closeMenus = function () {
        this.root.querySelectorAll('.ap-audience-picker__menu').forEach(function (m) {
            m.hidden = true;
        });
    };

    ContentAudiencePicker.prototype.runSearch = function (q) {
        var self = this;
        var results = this.root.querySelector('.ap-audience-picker__results');
        if (!results || !this.searchUrl) {
            if (results) results.hidden = true;
            return;
        }
        q = String(q || '').trim();
        var minLen = q.indexOf('@') !== -1 ? 1 : 2;
        if (q.length < minLen) {
            results.hidden = true;
            return;
        }
        this.fetchJson(this.searchUrl + '?q=' + encodeURIComponent(q))
            .then(function (data) {
                var items = data.items || [];
                self.showResults(items, 'Никого не найдено. Вставьте email в поле «Добавить списком» и нажмите «Добавить в список».');
            })
            .catch(function () {
                results.hidden = true;
            });
    };

    ContentAudiencePicker.prototype.resolveEmails = function (emails, onDone) {
        var self = this;
        var msg = this.root.querySelector('.ap-audience-bulk-msg');
        if (!this.resolveUrl || !emails || emails.length === 0) {
            if (msg) msg.textContent = 'Введите корректный email.';
            return;
        }
        var token = getCsrfToken();
        if (!token) {
            if (msg) msg.textContent = 'Нет CSRF-токена. Обновите страницу.';
            return;
        }
        if (msg) msg.textContent = 'Добавляем…';
        this.fetchJson(this.resolveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ emails: emails }),
        })
            .then(function (data) {
                self.applyResolveResult(data, msg);
                if (onDone) onDone(data);
            })
            .catch(function (err) {
                if (msg) {
                    msg.textContent = err && err.message
                        ? err.message
                        : 'Ошибка при добавлении. Обновите страницу и попробуйте снова.';
                }
            });
    };

    ContentAudiencePicker.prototype.applyResolveResult = function (data, msgEl) {
        var items = data.items || [];
        var added = 0;
        var self = this;
        items.forEach(function (it) {
            var before = self.rules.length;
            self.addRule({
                subject_type: it.subject_type,
                subject_id: it.subject_id,
                label: it.label,
                color: null,
                scope: null,
            });
            if (self.rules.length > before) added++;
        });
        var parts = [];
        if (added > 0) parts.push('Добавлено: ' + added);
        if ((data.created || []).length > 0) {
            parts.push('Новые в портале: ' + data.created.length);
        }
        if ((data.enrolled || []).length > 0) {
            parts.push('Записаны на курс: ' + data.enrolled.length);
        }
        if ((data.invalid || []).length > 0) {
            parts.push('Некорректный email: ' + data.invalid.join(', '));
        }
        if (parts.length === 0) parts.push('Никого не удалось добавить.');
        if (msgEl) msgEl.textContent = parts.join('. ');
        var ta = this.root.querySelector('.ap-audience-bulk-emails');
        if (added > 0 && ta) ta.value = '';
    };

    ContentAudiencePicker.prototype.runBulkAdd = function () {
        var ta = this.root.querySelector('.ap-audience-bulk-emails');
        var msg = this.root.querySelector('.ap-audience-bulk-msg');
        if (!ta) return;
        var emails = parseEmails(ta.value);
        if (emails.length === 0) {
            if (msg) msg.textContent = 'Введите хотя бы один email.';
            return;
        }
        if (!this.resolveUrl) {
            if (msg) msg.textContent = 'Добавление недоступно. Обновите страницу.';
            return;
        }
        this.resolveEmails(emails);
    };

    ContentAudiencePicker.prototype.addRule = function (rule) {
        if (!rule || !rule.subject_type || !rule.subject_id) return;
        var key = ruleKey(rule);
        if (this.rules.some(function (r) { return ruleKey(r) === key; })) return;
        this.rules.push({
            subject_type: rule.subject_type,
            subject_id: rule.subject_id,
            label: rule.label || ('#' + rule.subject_id),
            color: rule.color || null,
            scope: rule.scope || null,
        });
        this.renderChips();
        this.emitChange();
    };

    ContentAudiencePicker.prototype.removeRule = function (key) {
        this.rules = this.rules.filter(function (r) { return ruleKey(r) !== key; });
        this.renderChips();
        this.emitChange();
    };

    ContentAudiencePicker.prototype.renderChips = function () {
        var box = this.root.querySelector('.ap-audience-picker__chips');
        var empty = this.root.querySelector('.ap-audience-picker__empty');
        if (!box) return;
        if (this.rules.length === 0) {
            box.innerHTML = '';
            if (empty) empty.hidden = false;
            return;
        }
        if (empty) empty.hidden = true;
        var self = this;
        box.innerHTML = this.rules.map(function (r) {
            var scopeBadge = r.scope === 'course' ? '<span class="ap-audience-chip__scope">курс</span>'
                : (r.scope === 'global' ? '<span class="ap-audience-chip__scope">глоб.</span>' : '');
            var dot = r.color ? '<span class="ap-audience-chip__dot" style="background:' + escapeHtml(r.color) + '"></span>' : '';
            return '<span class="ap-audience-chip ap-audience-chip--' + (r.scope || 'learner') + '">' +
                dot + escapeHtml(r.label) + scopeBadge +
                (self.readOnly ? '' : '<button type="button" class="ap-audience-chip__remove" data-key="' + escapeHtml(ruleKey(r)) + '" aria-label="Убрать">×</button>') +
                '</span>';
        }).join('');
        box.querySelectorAll('.ap-audience-chip__remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                self.removeRule(btn.getAttribute('data-key'));
            });
        });
    };

    ContentAudiencePicker.prototype.setData = function (data) {
        data = data || {};
        this.groups = data.groups || { portal: [], course: [] };
        this.rules = (data.rules || []).map(function (r) {
            return {
                subject_type: r.subject_type,
                subject_id: r.subject_id,
                label: r.label,
                color: r.color || null,
                scope: r.scope || null,
            };
        });
        var toggle = this.root.querySelector('.ap-audience-restricted');
        var wrap = this.root.querySelector('.ap-audience-picker__restricted');
        var restricted = data.view_audience === 'restricted';
        if (toggle) toggle.checked = restricted;
        if (wrap) wrap.hidden = !restricted;
        var msg = this.root.querySelector('.ap-audience-bulk-msg');
        if (msg) msg.textContent = '';
        this.renderChips();
    };

    ContentAudiencePicker.prototype.getPayload = function () {
        var toggle = this.root.querySelector('.ap-audience-restricted');
        var restricted = toggle && toggle.checked;
        return {
            view_audience: restricted ? 'restricted' : 'all',
            rules: restricted ? this.rules.map(function (r) {
                return { subject_type: r.subject_type, subject_id: r.subject_id };
            }) : [],
        };
    };

    ContentAudiencePicker.prototype.validate = function () {
        var p = this.getPayload();
        if (p.view_audience === 'restricted' && p.rules.length === 0) {
            return 'При ограниченном доступе выберите хотя бы одного обучающегося или группу.';
        }
        return null;
    };

    ContentAudiencePicker.prototype.emitChange = function () {
        if (this.onChange) this.onChange(this.getPayload());
    };

    global.ContentAudiencePicker = ContentAudiencePicker;
})(window);
