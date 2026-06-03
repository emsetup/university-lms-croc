(function () {
    'use strict';

    var POLL_MS = 25000;
    var DEBOUNCE_MS = 450;

    function escHtml(s) {
        return String(s)
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

    function codeClass(code) {
        if (!code) {
            return '';
        }
        if (code >= 500) {
            return 'ap-logs-entry__code--5xx';
        }
        if (code >= 400) {
            return 'ap-logs-entry__code--4xx';
        }
        return '';
    }

    function pluralRecords(n) {
        var m10 = n % 10;
        var m100 = n % 100;
        if (m100 >= 11 && m100 <= 14) {
            return 'записей';
        }
        if (m10 === 1) {
            return 'запись';
        }
        if (m10 >= 2 && m10 <= 4) {
            return 'записи';
        }
        return 'записей';
    }

    function initPanel(panel) {
        var feedUrl = panel.getAttribute('data-ap-incident-feed-url') || '';
        var detailBase = panel.getAttribute('data-ap-incident-detail-url') || '';
        var form = panel.querySelector('[data-ap-incident-filters]');
        var viewport = panel.querySelector('[data-ap-incident-viewport]');
        var mount = panel.querySelector('[data-ap-incident-mount]');
        var loadingEl = panel.querySelector('[data-ap-incident-loading]');
        var emptyEl = panel.querySelector('[data-ap-incident-empty]');
        var statusEl = panel.querySelector('[data-ap-incident-status]');
        var footerEl = panel.querySelector('[data-ap-incident-footer]');
        var moreBtn = panel.querySelector('[data-ap-incident-more]');
        var liveInput = panel.querySelector('[data-ap-incident-live]');
        var resetBtn = panel.querySelector('[data-ap-incident-reset]');
        var periodGroup = panel.querySelector('[data-ap-period-group]');
        var debounceTimer = null;
        var pollTimer = null;
        var abortCtrl = null;
        var busy = false;
        var reqSeq = 0;
        var beforeId = 0;
        var openId = 0;
        var detailCache = {};

        if (!feedUrl || !mount || !viewport) {
            return;
        }

        /** @param {'loading'|'empty'|'list'|'error'} state */
        function setViewState(state) {
            viewport.setAttribute('data-state', state);
            if (loadingEl) {
                loadingEl.hidden = state !== 'loading';
            }
            if (emptyEl) {
                emptyEl.hidden = state !== 'empty';
            }
            mount.hidden = state !== 'list';
            panel.classList.toggle('ap-incident-panel--loading', state === 'loading');
        }

        function setStatus(text, isError) {
            if (!statusEl) {
                return;
            }
            statusEl.textContent = text || '';
            statusEl.classList.toggle('ap-act-status--error', !!isError);
        }

        function setMoreVisible(on) {
            if (footerEl) {
                footerEl.hidden = !on;
            }
        }

        function readFilters() {
            if (!form) {
                return {};
            }
            var fd = new FormData(form);
            var sources = fd.getAll('sources[]').filter(Boolean);
            var params = {
                date_from: (fd.get('date_from') || '').toString(),
                date_to: (fd.get('date_to') || '').toString(),
                user: (fd.get('user') || '').toString(),
                status: (fd.get('status') || '').toString(),
                limit: '80',
            };
            sources.forEach(function (s, i) {
                params['sources[' + i + ']'] = s;
            });
            return params;
        }

        function buildQuery(extra) {
            var params = readFilters();
            Object.keys(extra || {}).forEach(function (k) {
                params[k] = extra[k];
            });
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
            var code = item.status_code;
            var codeLabel = code ? String(code) : 'JS';
            var open = openId === item.id;
            var chips =
                '<span class="ap-logs-chip">' + escHtml(item.source_label || '') + '</span>' +
                '<span class="ap-logs-chip ap-logs-chip--muted">' + escHtml(item.occurred_at || '') + '</span>' +
                (item.user_email
                    ? '<span class="ap-logs-chip ap-logs-chip--muted">' + escHtml(item.user_email) + '</span>'
                    : '');
            return (
                '<article class="ap-logs-entry' + (open ? ' is-open' : '') + '" role="listitem">' +
                '<button type="button" class="ap-logs-entry__toggle" data-ap-incident-toggle="' + item.id + '" aria-expanded="' + (open ? 'true' : 'false') + '">' +
                '<span class="ap-logs-entry__code ' + codeClass(code) + '">' + escHtml(codeLabel) + '</span>' +
                '<span class="ap-logs-entry__body">' +
                '<p class="ap-logs-entry__title">' + escHtml(item.summary || '') + '</p>' +
                '<div class="ap-logs-entry__chips">' + chips + '</div>' +
                '</span>' +
                '<span class="ap-logs-entry__expand" aria-hidden="true">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>' +
                '</span>' +
                '</button>' +
                '<div class="ap-logs-entry__drawer" data-ap-incident-detail="' + item.id + '" hidden></div>' +
                '</article>'
            );
        }

        function loadDetail(id) {
            var slot = mount.querySelector('[data-ap-incident-detail="' + id + '"]');
            if (!slot) {
                return;
            }
            if (detailCache[id]) {
                slot.innerHTML = detailCache[id];
                slot.hidden = false;
                return;
            }
            slot.innerHTML = '<p class="ap-logs-detail-loading">Загрузка…</p>';
            slot.hidden = false;
            fetch(detailBase + '/' + id, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status);
                    }
                    return r.json();
                })
                .then(function (d) {
                    var html =
                        '<dl class="ap-logs-detail-grid">' +
                        '<dt>Пользователь</dt><dd>' + escHtml(d.user_email || '—') + (d.learner_id ? ' · id ' + d.learner_id : '') + '</dd>' +
                        '<dt>Время</dt><dd>' + escHtml(d.occurred_at || '—') + '</dd>' +
                        '<dt>Метод</dt><dd>' + escHtml(d.http_method || '—') + '</dd>' +
                        '<dt>Код</dt><dd>' + escHtml(d.status_code != null ? String(d.status_code) : '—') + '</dd>' +
                        '<dt>IP</dt><dd>' + escHtml(d.ip || '—') + '</dd>' +
                        '<dt>Страница</dt><dd class="ap-logs-url">' + escHtml(d.url || '—') + '</dd>' +
                        '</dl>' +
                        '<pre class="ap-logs-detail-pre">' + escHtml(d.detail || d.summary || '') + '</pre>';
                    detailCache[id] = html;
                    slot.innerHTML = html;
                })
                .catch(function () {
                    slot.innerHTML = '<p class="ap-logs-detail-loading">Не удалось загрузить подробности.</p>';
                });
        }

        function bindToggles() {
            mount.querySelectorAll('[data-ap-incident-toggle]').forEach(function (btn) {
                if (btn.dataset.apBound) {
                    return;
                }
                btn.dataset.apBound = '1';
                btn.addEventListener('click', function () {
                    var id = parseInt(btn.getAttribute('data-ap-incident-toggle'), 10);
                    if (!id) {
                        return;
                    }
                    var item = btn.closest('.ap-logs-entry');
                    if (openId === id) {
                        openId = 0;
                        item.classList.remove('is-open');
                        btn.setAttribute('aria-expanded', 'false');
                        var slot = mount.querySelector('[data-ap-incident-detail="' + id + '"]');
                        if (slot) {
                            slot.hidden = true;
                        }
                        return;
                    }
                    mount.querySelectorAll('.ap-logs-entry.is-open').forEach(function (el) {
                        el.classList.remove('is-open');
                    });
                    openId = id;
                    item.classList.add('is-open');
                    btn.setAttribute('aria-expanded', 'true');
                    loadDetail(id);
                });
            });
        }

        function fetchFeed(append) {
            if (busy && !append) {
                return;
            }
            var mySeq = ++reqSeq;
            busy = true;

            if (!append) {
                setViewState('loading');
                setMoreVisible(false);
                beforeId = 0;
                openId = 0;
                detailCache = {};
                mount.innerHTML = '';
            }

            if (abortCtrl) {
                abortCtrl.abort();
            }
            abortCtrl = new AbortController();

            var q = buildQuery(append && beforeId > 0 ? { before_id: String(beforeId) } : {});
            fetch(feedUrl + (q ? '?' + q : ''), {
                signal: abortCtrl.signal,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status);
                    }
                    return r.json();
                })
                .then(function (data) {
                    if (mySeq !== reqSeq) {
                        return;
                    }
                    var items = data.items || [];
                    if (!append) {
                        mount.innerHTML = '';
                    }
                    if (items.length === 0 && mount.querySelectorAll('.ap-logs-entry').length === 0) {
                        setViewState('empty');
                        setStatus('');
                    } else {
                        setViewState('list');
                        mount.hidden = false;
                        mount.insertAdjacentHTML('beforeend', items.map(renderItem).join(''));
                        bindToggles();
                        var total = mount.querySelectorAll('.ap-logs-entry').length;
                        setStatus(total + ' ' + pluralRecords(total));
                    }
                    if (items.length > 0) {
                        beforeId = items[items.length - 1].id;
                    }
                    setMoreVisible(!!data.has_more);
                })
                .catch(function (err) {
                    if (mySeq !== reqSeq) {
                        return;
                    }
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    setViewState('empty');
                    setStatus('Ошибка загрузки журнала', true);
                    setMoreVisible(false);
                })
                .finally(function () {
                    if (mySeq !== reqSeq) {
                        return;
                    }
                    busy = false;
                });
        }

        function scheduleFetch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                fetchFeed(false);
            }, DEBOUNCE_MS);
        }

        function markPeriodPill(mode) {
            if (!periodGroup) {
                return;
            }
            periodGroup.querySelectorAll('[data-ap-period]').forEach(function (b) {
                b.classList.toggle('is-active', b.getAttribute('data-ap-period') === mode);
            });
        }

        if (form) {
            form.addEventListener('change', scheduleFetch);
            var userInput = form.querySelector('[name="user"]');
            if (userInput) {
                userInput.addEventListener('input', scheduleFetch);
            }
        }
        if (resetBtn && form) {
            resetBtn.addEventListener('click', function () {
                form.reset();
                form.querySelectorAll('input[name="sources[]"]').forEach(function (cb) {
                    cb.checked = true;
                });
                markPeriodPill('');
                fetchFeed(false);
            });
        }
        if (moreBtn) {
            moreBtn.addEventListener('click', function () {
                fetchFeed(true);
            });
        }
        if (periodGroup) {
            periodGroup.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-ap-period]');
                if (!btn || !form) {
                    return;
                }
                var mode = btn.getAttribute('data-ap-period');
                markPeriodPill(mode);
                var today = new Date();
                var from = form.querySelector('[name="date_from"]');
                var to = form.querySelector('[name="date_to"]');
                if (!from || !to) {
                    return;
                }
                to.value = formatDateInput(today);
                if (mode === 'today') {
                    from.value = formatDateInput(today);
                } else if (mode === '7d') {
                    var d7 = new Date(today);
                    d7.setDate(d7.getDate() - 6);
                    from.value = formatDateInput(d7);
                } else if (mode === '30d') {
                    var d30 = new Date(today);
                    d30.setDate(d30.getDate() - 29);
                    from.value = formatDateInput(d30);
                } else {
                    from.value = '';
                    to.value = '';
                }
                fetchFeed(false);
            });
        }
        if (liveInput) {
            liveInput.addEventListener('change', function () {
                clearInterval(pollTimer);
                if (liveInput.checked) {
                    pollTimer = setInterval(function () {
                        fetchFeed(false);
                    }, POLL_MS);
                }
            });
        }

        fetchFeed(false);
    }

    document.querySelectorAll('[data-ap-incident-panel]').forEach(initPanel);
})();
