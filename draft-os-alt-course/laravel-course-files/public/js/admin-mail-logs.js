(function () {
    'use strict';

    var DEBOUNCE_MS = 400;

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

    function csrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m && m.content) return m.content;
        var inp = document.querySelector('input[name="_token"]');
        return inp ? inp.value : '';
    }

    function statusClass(status) {
        if (status === 'sent') return 'ap-mail-status--sent';
        if (status === 'failed') return 'ap-mail-status--failed';
        if (status === 'skipped') return 'ap-mail-status--skipped';
        return 'ap-mail-status--pending';
    }

    function initPanel(panel) {
        var feedUrl = panel.getAttribute('data-ap-mail-feed-url') || '';
        var detailBase = panel.getAttribute('data-ap-mail-detail-base') || '';
        var form = panel.querySelector('[data-ap-mail-filters]');
        var viewport = panel.querySelector('[data-ap-mail-viewport]');
        var mount = panel.querySelector('[data-ap-mail-mount]');
        var loadingEl = panel.querySelector('[data-ap-mail-loading]');
        var emptyEl = panel.querySelector('[data-ap-mail-empty]');
        var statusEl = panel.querySelector('[data-ap-mail-status]');
        var footerEl = panel.querySelector('[data-ap-mail-footer]');
        var moreBtn = panel.querySelector('[data-ap-mail-more]');
        var resetBtn = panel.querySelector('[data-ap-mail-reset]');
        var periodGroup = panel.querySelector('[data-ap-mail-period-group]');
        var modal = document.getElementById('ap-mail-detail-modal');
        var detailBody = modal ? modal.querySelector('[data-ap-mail-detail-body]') : null;
        var resendBtn = modal ? modal.querySelector('[data-ap-mail-resend]') : null;
        var debounceTimer = null;
        var closeTimer = null;
        var abortCtrl = null;
        var busy = false;
        var beforeId = 0;
        var openId = 0;
        var detailCache = {};

        if (!feedUrl || !mount || !viewport) return;

        function setViewState(state) {
            viewport.setAttribute('data-state', state);
            if (loadingEl) loadingEl.hidden = state !== 'loading';
            if (emptyEl) emptyEl.hidden = state !== 'empty';
            mount.hidden = state !== 'list';
            if (footerEl) footerEl.hidden = state !== 'list';
        }

        function queryParams(append) {
            var fd = new FormData(form);
            var params = new URLSearchParams();
            fd.forEach(function (v, k) {
                if (v === '' || v == null) return;
                params.append(k, String(v));
            });
            params.set('limit', '80');
            if (append && beforeId > 0) params.set('before_id', String(beforeId));
            return params;
        }

        function renderItems(items, append) {
            if (!append) mount.innerHTML = '';
            items.forEach(function (it) {
                var el = document.createElement('article');
                el.className = 'ap-logs-entry ap-mail-entry-wrap';
                el.setAttribute('role', 'listitem');
                el.dataset.id = String(it.id);
                el.innerHTML =
                    '<div class="ap-mail-entry">' +
                    '<div class="ap-mail-entry__main">' +
                    '<div class="ap-logs-entry__meta">' +
                    '<time>' + escHtml(it.created_at || '') + '</time>' +
                    '<span class="ap-mail-status ' + statusClass(it.status) + '">' + escHtml(it.status_label || it.status) + '</span>' +
                    '<span class="ap-logs-entry__code">' + escHtml(it.type_label || it.type) + '</span>' +
                    '</div>' +
                    '<div class="ap-logs-entry__summary">' + escHtml(it.subject || '') + '</div>' +
                    '<div class="ap-logs-entry__sub">' + escHtml(it.to_email || '') +
                    (it.sent_by_email ? ' · от ' + escHtml(it.sent_by_email) : '') +
                    (it.error ? ' · ' + escHtml(it.error) : '') +
                    '</div>' +
                    '</div>' +
                    '<div class="ap-mail-entry__actions">' +
                    '<button type="button" class="btn btn-ghost btn-sm" data-ap-mail-view>Просмотреть</button>' +
                    '<button type="button" class="btn btn-primary btn-sm" data-ap-mail-resend-row>Отправить снова</button>' +
                    '</div>' +
                    '</div>';
                var viewBtn = el.querySelector('[data-ap-mail-view]');
                var resendRowBtn = el.querySelector('[data-ap-mail-resend-row]');
                if (viewBtn) {
                    viewBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        openDetail(it.id);
                    });
                }
                if (resendRowBtn) {
                    resendRowBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        resendById(it.id, resendRowBtn);
                    });
                }
                mount.appendChild(el);
            });
        }

        function load(append) {
            if (busy && !append) return;
            busy = true;
            if (!append) {
                setViewState('loading');
                beforeId = 0;
            }
            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();
            var url = feedUrl + '?' + queryParams(!!append).toString();
            fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: abortCtrl.signal
            }).then(function (r) {
                return r.json();
            }).then(function (data) {
                var items = (data && data.items) || [];
                if (!append && items.length === 0) {
                    setViewState('empty');
                    if (statusEl) statusEl.textContent = 'Нет записей';
                    return;
                }
                if (items.length) {
                    beforeId = items[items.length - 1].id;
                }
                renderItems(items, !!append);
                setViewState('list');
                if (footerEl) footerEl.hidden = !data.has_more;
                if (statusEl) {
                    statusEl.textContent = (append ? 'Догружено' : 'Показано') + ': ' + mount.children.length;
                }
            }).catch(function (err) {
                if (err && err.name === 'AbortError') return;
                setViewState(mount.children.length ? 'list' : 'empty');
                if (statusEl) statusEl.textContent = 'Ошибка загрузки';
            }).finally(function () {
                busy = false;
            });
        }

        function openDetail(id) {
            openId = id;
            if (!modal || !detailBody) return;
            if (closeTimer) {
                clearTimeout(closeTimer);
                closeTimer = null;
            }
            modal.hidden = false;
            void modal.offsetWidth;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('ap-modal-open');
            detailBody.innerHTML = '<p class="ap-muted">Загрузка…</p>';
            if (resendBtn) resendBtn.disabled = true;

            var apply = function (d) {
                detailCache[id] = d;
                var title = document.getElementById('ap-mail-detail-title');
                if (title) title.textContent = d.subject || 'Письмо #' + d.id;
                var html = '';
                html += '<p><strong>Кому:</strong> ' + escHtml(d.to_email || '') +
                    (d.to_name ? ' (' + escHtml(d.to_name) + ')' : '') + '</p>';
                html += '<p><strong>Статус:</strong> ' + escHtml(d.status_label || d.status) +
                    ' · <strong>Тип:</strong> ' + escHtml(d.type_label || d.type) + '</p>';
                html += '<p><strong>Когда:</strong> ' + escHtml(d.created_at || '—') +
                    (d.sent_at ? ' · отправлено ' + escHtml(d.sent_at) : '') + '</p>';
                if (d.sent_by_email) {
                    html += '<p><strong>Инициатор:</strong> ' + escHtml(d.sent_by_email) + '</p>';
                }
                if (d.resend_of_id) {
                    html += '<p><strong>Повтор</strong> письма #' + escHtml(d.resend_of_id) + '</p>';
                }
                if (d.error) {
                    html += '<p style="color:#b42318;"><strong>Ошибка:</strong> ' + escHtml(d.error) + '</p>';
                }
                html += '<div class="ap-mail-preview" style="margin-top:12px;border:1px solid #e6ebf0;border-radius:8px;overflow:hidden;background:#fff;">' +
                    '<iframe title="preview" style="width:100%;min-height:420px;border:0;" sandbox=""></iframe></div>';
                detailBody.innerHTML = html;
                var iframe = detailBody.querySelector('iframe');
                if (iframe) {
                    var doc = iframe.contentDocument || iframe.contentWindow.document;
                    doc.open();
                    doc.write(d.body_html || '<p></p>');
                    doc.close();
                }
                if (resendBtn) resendBtn.disabled = false;
            };

            if (detailCache[id]) {
                apply(detailCache[id]);
                return;
            }
            fetch(detailBase + '/' + id, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(apply).catch(function () {
                detailBody.innerHTML = '<p class="ap-muted">Не удалось загрузить письмо.</p>';
            });
        }

        function closeDetail() {
            if (!modal) return;
            if (!modal.classList.contains('is-open')) {
                modal.hidden = true;
                return;
            }
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('ap-modal-open');
            openId = 0;
            closeTimer = setTimeout(function () {
                modal.hidden = true;
                closeTimer = null;
            }, 200);
        }

        function resendById(id, btn) {
            if (!id) return;
            if (!window.confirm('Отправить это письмо повторно на тот же адрес?')) return;
            if (btn) btn.disabled = true;
            fetch(detailBase + '/' + id + '/resend', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }).then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok || !data.ok) {
                        throw new Error((data && data.message) || 'Ошибка повторной отправки');
                    }
                    return data;
                });
            }).then(function (data) {
                alert(data.message || 'Отправлено');
                closeDetail();
                load(false);
            }).catch(function (err) {
                alert(err.message || 'Ошибка');
                if (btn) btn.disabled = false;
            });
        }

        function resend() {
            if (!openId || !resendBtn) return;
            resendById(openId, resendBtn);
        }

        function scheduleReload() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () { load(false); }, DEBOUNCE_MS);
        }

        if (form) {
            form.addEventListener('input', scheduleReload);
            form.addEventListener('change', scheduleReload);
        }
        if (moreBtn) {
            moreBtn.addEventListener('click', function () { load(true); });
        }
        if (resetBtn && form) {
            resetBtn.addEventListener('click', function () {
                form.reset();
                form.querySelectorAll('input[type="checkbox"]').forEach(function (c) { c.checked = true; });
                load(false);
            });
        }
        if (periodGroup) {
            periodGroup.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-ap-mail-period]');
                if (!btn || !form) return;
                var kind = btn.getAttribute('data-ap-mail-period');
                var from = form.querySelector('[name="date_from"]');
                var to = form.querySelector('[name="date_to"]');
                var today = new Date();
                if (kind === 'all') {
                    if (from) from.value = '';
                    if (to) to.value = '';
                } else if (kind === 'today') {
                    var t = formatDateInput(today);
                    if (from) from.value = t;
                    if (to) to.value = t;
                } else {
                    var days = kind === '30d' ? 30 : 7;
                    var start = new Date(today.getTime() - (days - 1) * 86400000);
                    if (from) from.value = formatDateInput(start);
                    if (to) to.value = formatDateInput(today);
                }
                load(false);
            });
        }
        if (modal) {
            modal.querySelectorAll('[data-ap-mail-detail-close]').forEach(function (el) {
                el.addEventListener('click', closeDetail);
            });
        }
        if (resendBtn) resendBtn.addEventListener('click', resend);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
                closeDetail();
            }
        });

        load(false);
    }

    document.querySelectorAll('[data-ap-mail-panel]').forEach(initPanel);
})();
