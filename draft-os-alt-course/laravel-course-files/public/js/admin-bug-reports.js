(function () {
    'use strict';

    var POLL_MS = 30000;
    var DEBOUNCE_MS = 450;

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatDateInput(d) {
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function pluralRecords(n) {
        var m10 = n % 10;
        var m100 = n % 100;
        if (m100 >= 11 && m100 <= 14) return 'записей';
        if (m10 === 1) return 'запись';
        if (m10 >= 2 && m10 <= 4) return 'записи';
        return 'записей';
    }

    function initPanel(panel) {
        var feedUrl = panel.getAttribute('data-ap-bug-feed-url') || '';
        var detailBase = panel.getAttribute('data-ap-bug-detail-url') || '';
        var csrf = panel.getAttribute('data-ap-bug-csrf') || '';
        var form = panel.querySelector('[data-ap-bug-filters]');
        var viewport = panel.querySelector('[data-ap-bug-viewport]');
        var mount = panel.querySelector('[data-ap-bug-mount]');
        var loadingEl = panel.querySelector('[data-ap-bug-loading]');
        var emptyEl = panel.querySelector('[data-ap-bug-empty]');
        var statusEl = panel.querySelector('[data-ap-bug-status]');
        var footerEl = panel.querySelector('[data-ap-bug-footer]');
        var moreBtn = panel.querySelector('[data-ap-bug-more]');
        var liveInput = panel.querySelector('[data-ap-bug-live]');
        var resetBtn = panel.querySelector('[data-ap-bug-reset]');
        var periodGroup = panel.querySelector('[data-ap-period-group]');
        var detailDlg = document.getElementById('ap-bug-detail');
        var detailBody = detailDlg && detailDlg.querySelector('[data-ap-bug-detail-body]');
        var detailTitle = document.getElementById('ap-bug-detail-title');
        var page = document.querySelector('[data-ap-bug-page]');
        var openPref = page ? parseInt(page.getAttribute('data-ap-bug-open') || '0', 10) : 0;

        var debounceTimer = null;
        var pollTimer = null;
        var abortCtrl = null;
        var busy = false;
        var reqSeq = 0;
        var beforeId = 0;

        if (!feedUrl || !mount || !viewport) return;

        function setViewState(state) {
            viewport.setAttribute('data-state', state);
            if (loadingEl) loadingEl.hidden = state !== 'loading';
            if (emptyEl) emptyEl.hidden = state !== 'empty';
            mount.hidden = state !== 'list';
        }

        function setStatus(text, isError) {
            if (!statusEl) return;
            statusEl.textContent = text || '';
            statusEl.classList.toggle('ap-act-status--error', !!isError);
        }

        function setMoreVisible(on) {
            if (footerEl) footerEl.hidden = !on;
        }

        function readFilters() {
            if (!form) return {};
            var fd = new FormData(form);
            var params = {
                date_from: (fd.get('date_from') || '').toString(),
                date_to: (fd.get('date_to') || '').toString(),
                user: (fd.get('user') || '').toString(),
                q: (fd.get('q') || '').toString(),
                limit: '80',
            };
            fd.getAll('types[]').filter(Boolean).forEach(function (s, i) {
                params['types[' + i + ']'] = s;
            });
            fd.getAll('scopes[]').filter(Boolean).forEach(function (s, i) {
                params['scopes[' + i + ']'] = s;
            });
            fd.getAll('statuses[]').filter(Boolean).forEach(function (s, i) {
                params['statuses[' + i + ']'] = s;
            });
            return params;
        }

        function buildQuery(extra) {
            var params = readFilters();
            Object.keys(extra || {}).forEach(function (k) { params[k] = extra[k]; });
            var parts = [];
            Object.keys(params).forEach(function (key) {
                var val = params[key];
                if (val !== '' && val !== null && val !== undefined) {
                    parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(val)));
                }
            });
            return parts.join('&');
        }

        function renderItem(item) {
            var chips =
                '<span class="ap-bug-type ap-bug-type--' + escHtml(item.type) + '">' + escHtml(item.type_label || '') + '</span>' +
                '<span class="ap-bug-type ap-bug-scope--' + escHtml(item.scope || 'portal') + '">' + escHtml(item.scope_label || '') + '</span>' +
                (item.course_title ? '<span class="ap-logs-chip">' + escHtml(item.course_title) + '</span>' : '') +
                '<span class="ap-bug-status ap-bug-status--' + escHtml(item.status) + '">' + escHtml(item.status_label || '') + '</span>' +
                '<span class="ap-logs-chip ap-logs-chip--muted">' + escHtml(item.created_at || '') + '</span>' +
                (item.user_email ? '<span class="ap-logs-chip ap-logs-chip--muted">' + escHtml(item.user_email) + '</span>' : '') +
                (item.screenshot_count ? '<span class="ap-logs-chip">' + item.screenshot_count + ' скр.</span>' : '');
            return (
                '<article class="ap-logs-entry" role="listitem">' +
                '<button type="button" class="ap-logs-entry__toggle" data-ap-bug-open="' + item.id + '">' +
                '<span class="ap-logs-entry__code">#' + item.id + '</span>' +
                '<span class="ap-logs-entry__body">' +
                '<p class="ap-logs-entry__title">' + escHtml(item.preview || '') + '</p>' +
                '<div class="ap-logs-entry__chips">' + chips + '</div>' +
                '</span>' +
                '<span class="ap-logs-entry__expand" aria-hidden="true">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>' +
                '</span>' +
                '</button>' +
                '</article>'
            );
        }

        function closeDetail() {
            if (!detailDlg) return;
            detailDlg.classList.remove('is-open');
            detailDlg.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('ap-modal-open');
            detailDlg.hidden = true;
        }

        function openDetail(id) {
            if (!detailDlg || !detailBody || !detailBase) return;
            detailDlg.hidden = false;
            void detailDlg.offsetWidth;
            detailDlg.classList.add('is-open');
            detailDlg.setAttribute('aria-hidden', 'false');
            document.body.classList.add('ap-modal-open');
            detailBody.innerHTML = '<p class="ap-logs-detail-loading">Загрузка…</p>';
            if (detailTitle) detailTitle.textContent = '#' + id;

            fetch(detailBase + '/' + id, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function (d) {
                    if (detailTitle) detailTitle.textContent = d.ticket || ('#' + id);
                    var shots = (d.screenshots || []).map(function (s) {
                        return '<a href="' + escHtml(s.url) + '" target="_blank" rel="noopener"><img src="' + escHtml(s.url) + '" alt="' + escHtml(s.name || '') + '"></a>';
                    }).join('');
                    var statusOpts = ['new', 'in_progress', 'done', 'dismissed'].map(function (st) {
                        var labels = { new: 'Новое', in_progress: 'В работе', done: 'Готово', dismissed: 'Отклонено' };
                        return '<option value="' + st + '"' + (d.status === st ? ' selected' : '') + '>' + labels[st] + '</option>';
                    }).join('');
                    detailBody.innerHTML =
                        '<dl class="ap-logs-detail-grid">' +
                        '<dt>Тикет</dt><dd><strong>' + escHtml(d.ticket || ('#' + id)) + '</strong></dd>' +
                        '<dt>Тип</dt><dd><span class="ap-bug-type ap-bug-type--' + escHtml(d.type) + '">' + escHtml(d.type_label) + '</span></dd>' +
                        '<dt>Область</dt><dd><span class="ap-bug-type ap-bug-scope--' + escHtml(d.scope || 'portal') + '">' + escHtml(d.scope_label || '') + '</span>' +
                        (d.course_title ? ' · ' + escHtml(d.course_title) : '') + '</dd>' +
                        '<dt>Статус</dt><dd><span class="ap-bug-status ap-bug-status--' + escHtml(d.status) + '">' + escHtml(d.status_label) + '</span></dd>' +
                        '<dt>От кого</dt><dd>' + escHtml(d.user_email || '—') + (d.learner_id ? ' · id ' + d.learner_id : '') + '</dd>' +
                        '<dt>Время</dt><dd>' + escHtml(d.created_at_label || d.created_at || '—') + '</dd>' +
                        '<dt>Страница</dt><dd class="ap-logs-url">' +
                        (d.page_url ? '<a href="' + escHtml(d.page_url) + '" target="_blank" rel="noopener">' + escHtml(d.page_url) + '</a>' : '—') +
                        '</dd>' +
                        '<dt>IP</dt><dd>' + escHtml(d.ip || '—') + '</dd>' +
                        '</dl>' +
                        (d.title ? '<p style="margin:0.75rem 0 0.35rem;font-weight:700;">' + escHtml(d.title) + '</p>' : '') +
                        '<pre class="ap-logs-detail-pre">' + escHtml(d.message || '') + '</pre>' +
                        (shots ? '<div class="ap-bug-shots">' + shots + '</div>' : '') +
                        '<form data-ap-bug-status-form style="margin-top:1rem;display:grid;gap:0.65rem;">' +
                        '<label style="display:grid;gap:0.3rem;font-size:0.85rem;font-weight:650;">Статус' +
                        '<select name="status" class="ap-logs-input ap-logs-input--select">' + statusOpts + '</select></label>' +
                        '<label style="display:grid;gap:0.3rem;font-size:0.85rem;font-weight:650;">Заметка' +
                        '<textarea name="admin_note" rows="3" class="ap-logs-input" style="resize:vertical;">' + escHtml(d.admin_note || '') + '</textarea></label>' +
                        '<div style="display:flex;gap:0.5rem;justify-content:flex-end;">' +
                        '<button type="button" class="btn btn-ghost" data-ap-bug-detail-close>Закрыть</button>' +
                        '<button type="submit" class="btn btn-primary">Сохранить</button></div>' +
                        '<p data-ap-bug-save-msg hidden style="margin:0;font-size:0.9rem;"></p>' +
                        '</form>';

                    detailBody.querySelectorAll('[data-ap-bug-detail-close]').forEach(function (b) {
                        b.addEventListener('click', closeDetail);
                    });

                    var sf = detailBody.querySelector('[data-ap-bug-status-form]');
                    if (sf) {
                        sf.addEventListener('submit', function (e) {
                            e.preventDefault();
                            var msg = detailBody.querySelector('[data-ap-bug-save-msg]');
                            var body = new FormData(sf);
                            fetch(detailBase + '/' + id + '/status', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: body,
                                credentials: 'same-origin',
                            })
                                .then(function (r) { return r.json().then(function (data) { return { r: r, data: data }; }); })
                                .then(function (pack) {
                                    if (!pack.r.ok || !pack.data.ok) {
                                        if (msg) {
                                            msg.hidden = false;
                                            msg.style.color = '#b91c1c';
                                            msg.textContent = (pack.data && pack.data.message) || 'Не удалось сохранить.';
                                        }
                                        return;
                                    }
                                    if (msg) {
                                        msg.hidden = false;
                                        msg.style.color = '#047857';
                                        msg.textContent = 'Сохранено.';
                                    }
                                    fetchFeed(false);
                                })
                                .catch(function () {
                                    if (msg) {
                                        msg.hidden = false;
                                        msg.style.color = '#b91c1c';
                                        msg.textContent = 'Сеть недоступна.';
                                    }
                                });
                        });
                    }
                })
                .catch(function () {
                    detailBody.innerHTML = '<p class="ap-logs-detail-loading">Не удалось загрузить.</p>';
                });
        }

        function bindOpens() {
            mount.querySelectorAll('[data-ap-bug-open]').forEach(function (btn) {
                if (btn.dataset.apBound) return;
                btn.dataset.apBound = '1';
                btn.addEventListener('click', function () {
                    var id = parseInt(btn.getAttribute('data-ap-bug-open'), 10);
                    if (id) openDetail(id);
                });
            });
        }

        function fetchFeed(append) {
            if (busy && !append) return;
            var mySeq = ++reqSeq;
            busy = true;
            if (!append) {
                setViewState('loading');
                setMoreVisible(false);
                beforeId = 0;
                mount.innerHTML = '';
            }
            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();
            var q = buildQuery(append && beforeId > 0 ? { before_id: String(beforeId) } : {});
            fetch(feedUrl + (q ? '?' + q : ''), {
                signal: abortCtrl.signal,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    if (mySeq !== reqSeq) return;
                    busy = false;
                    var items = data.items || [];
                    if (!append) mount.innerHTML = '';
                    if (items.length === 0 && mount.querySelectorAll('.ap-logs-entry').length === 0) {
                        setViewState('empty');
                        setStatus('');
                    } else {
                        setViewState('list');
                        mount.hidden = false;
                        mount.insertAdjacentHTML('beforeend', items.map(renderItem).join(''));
                        bindOpens();
                        var total = mount.querySelectorAll('.ap-logs-entry').length;
                        setStatus(total + ' ' + pluralRecords(total));
                    }
                    if (items.length > 0) beforeId = items[items.length - 1].id;
                    setMoreVisible(!!data.has_more);
                    if (!append && openPref > 0) {
                        var id = openPref;
                        openPref = 0;
                        openDetail(id);
                    }
                })
                .catch(function (err) {
                    if (mySeq !== reqSeq) return;
                    busy = false;
                    if (err && err.name === 'AbortError') return;
                    setViewState(mount.querySelectorAll('.ap-logs-entry').length ? 'list' : 'empty');
                    setStatus('Ошибка загрузки', true);
                });
        }

        function scheduleFetch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () { fetchFeed(false); }, DEBOUNCE_MS);
        }

        if (form) {
            form.addEventListener('input', scheduleFetch);
            form.addEventListener('change', scheduleFetch);
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                fetchFeed(false);
            });
        }
        if (moreBtn) moreBtn.addEventListener('click', function () { fetchFeed(true); });
        if (resetBtn && form) {
            resetBtn.addEventListener('click', function () {
                form.reset();
                form.querySelectorAll('input[name="types[]"]').forEach(function (c) { c.checked = true; });
                form.querySelectorAll('input[name="scopes[]"]').forEach(function (c) { c.checked = true; });
                form.querySelectorAll('input[name="statuses[]"]').forEach(function (c) {
                    c.checked = c.value === 'new' || c.value === 'in_progress';
                });
                fetchFeed(false);
            });
        }
        if (periodGroup && form) {
            periodGroup.querySelectorAll('[data-ap-period]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var kind = btn.getAttribute('data-ap-period');
                    var from = form.querySelector('[name="date_from"]');
                    var to = form.querySelector('[name="date_to"]');
                    var now = new Date();
                    if (kind === 'all') {
                        if (from) from.value = '';
                        if (to) to.value = '';
                    } else if (kind === 'today') {
                        var t = formatDateInput(now);
                        if (from) from.value = t;
                        if (to) to.value = t;
                    } else {
                        var days = kind === '7d' ? 7 : 30;
                        var start = new Date(now.getTime() - (days - 1) * 86400000);
                        if (from) from.value = formatDateInput(start);
                        if (to) to.value = formatDateInput(now);
                    }
                    fetchFeed(false);
                });
            });
        }

        if (detailDlg) {
            detailDlg.querySelectorAll('[data-ap-bug-detail-close]').forEach(function (b) {
                b.addEventListener('click', closeDetail);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && detailDlg.classList.contains('is-open')) {
                    closeDetail();
                }
            });
        }

        function armPoll() {
            clearInterval(pollTimer);
            if (liveInput && liveInput.checked) {
                pollTimer = setInterval(function () {
                    if (!document.hidden) fetchFeed(false);
                }, POLL_MS);
            }
        }
        if (liveInput) liveInput.addEventListener('change', armPoll);
        armPoll();
        fetchFeed(false);
    }

    function boot() {
        document.querySelectorAll('[data-ap-bug-panel]').forEach(initPanel);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
