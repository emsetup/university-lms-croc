(function () {
    'use strict';

    var POLL_MS = 20000;
    var DEBOUNCE_MS = 400;

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

    function initPanel(panel) {
        var feedUrl = panel.getAttribute('data-ap-activity-feed-url') || '';
        var limit = parseInt(panel.getAttribute('data-ap-activity-limit') || '50', 10);
        var mode = panel.getAttribute('data-ap-activity-mode') || 'full';
        var form = panel.querySelector('[data-ap-activity-filters]');
        var mount = panel.querySelector('[data-ap-activity-mount]');
        var loadingEl = panel.querySelector('[data-ap-activity-loading]');
        var emptyEl = panel.querySelector('[data-ap-activity-empty]');
        var statusEl = panel.querySelector('[data-ap-activity-status]');
        var liveInput = panel.querySelector('[data-ap-activity-live]');
        var resetBtn = panel.querySelector('[data-ap-activity-reset]');
        var periodGroup = panel.querySelector('[data-ap-period-group]');
        var debounceTimer = null;
        var pollTimer = null;
        var abortCtrl = null;
        var busy = false;

        if (!feedUrl || !mount) {
            return;
        }

        function setLoading(on) {
            if (loadingEl) {
                loadingEl.hidden = !on;
            }
            panel.classList.toggle('ap-activity-panel--loading', !!on);
        }

        function setEmpty(on) {
            if (emptyEl) {
                emptyEl.hidden = !on;
            }
        }

        function setStatus(text, isError) {
            if (!statusEl) {
                return;
            }
            statusEl.textContent = text || '';
            statusEl.classList.toggle('ap-act-status--error', !!isError);
        }

        function readFilters() {
            if (!form) {
                return { limit: limit };
            }
            var fd = new FormData(form);
            var kinds = fd.getAll('kinds[]').filter(Boolean);
            var params = {
                date_from: (fd.get('date_from') || '').toString(),
                date_to: (fd.get('date_to') || '').toString(),
                user: (fd.get('user') || '').toString(),
                limit: String(limit),
            };
            kinds.forEach(function (k, i) {
                params['kinds[' + i + ']'] = k;
            });
            return params;
        }

        function syncUrl() {
            if (mode !== 'full' || !form || !window.history || !window.history.replaceState) {
                return;
            }
            var params = new URLSearchParams();
            var fd = new FormData(form);
            ['date_from', 'date_to', 'user'].forEach(function (name) {
                var v = (fd.get(name) || '').toString();
                if (v) {
                    params.set(name, v);
                }
            });
            fd.getAll('kinds[]').forEach(function (k) {
                if (k) {
                    params.append('kinds[]', k);
                }
            });
            var qs = params.toString();
            window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
        }

        function syncPeriodHighlight() {
            if (!periodGroup || !form) {
                return;
            }
            var from = (form.querySelector('[name="date_from"]') || {}).value || '';
            var to = (form.querySelector('[name="date_to"]') || {}).value || '';
            var today = formatDateInput(new Date());
            var active = 'custom';
            if (!from && !to) {
                active = 'all';
            } else if (from === today && to === today) {
                active = 'today';
            } else {
                var dFrom = from ? new Date(from + 'T00:00:00') : null;
                var dTo = to ? new Date(to + 'T00:00:00') : null;
                if (dFrom && dTo && to === today) {
                    var diff = Math.round((dTo - dFrom) / 86400000);
                    if (diff === 6) {
                        active = '7d';
                    } else if (diff === 29 || diff === 30) {
                        active = '30d';
                    }
                }
            }
            periodGroup.querySelectorAll('[data-ap-period]').forEach(function (btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-ap-period') === active);
            });
        }

        function applyPeriod(preset) {
            if (!form) {
                return;
            }
            var fromInput = form.querySelector('[name="date_from"]');
            var toInput = form.querySelector('[name="date_to"]');
            var today = new Date();
            var to = formatDateInput(today);
            var from = '';
            if (preset === 'today') {
                from = to;
            } else if (preset === '7d') {
                var d7 = new Date(today);
                d7.setDate(d7.getDate() - 6);
                from = formatDateInput(d7);
            } else if (preset === '30d') {
                var d30 = new Date(today);
                d30.setDate(d30.getDate() - 29);
                from = formatDateInput(d30);
            } else if (preset === 'all') {
                to = '';
            }
            if (fromInput) {
                fromInput.value = from;
            }
            if (toInput) {
                toInput.value = to;
            }
            syncPeriodHighlight();
            scheduleFetch();
        }

        function renderSteps(steps) {
            if (!steps || !steps.length) {
                return '';
            }
            var html = '<ol class="ap-activity-route">';
            for (var i = 0; i < steps.length; i++) {
                var step = steps[i];
                html +=
                    '<li class="ap-activity-route__step">' +
                    '<span class="ap-activity-route__dot" aria-hidden="true"></span>' +
                    '<span class="ap-activity-route__label">' + escHtml(step.label || '') + '</span>' +
                    '<time class="ap-activity-route__time">' + escHtml(step.at_display || '') + '</time>' +
                    '</li>';
            }
            html += '</ol>';
            return html;
        }

        function renderItem(item) {
            var dotClass = item.active_today ? ' ap-activity-feed__dot--live' : '';
            var kind = item.kind || '';
            var kindLabel = item.kind_label || '';
            var badge = kindLabel
                ? '<span class="ap-activity-kind ap-activity-kind--' + escHtml(kind) + '">' + escHtml(kindLabel) + '</span>'
                : '';
            var timeLabel = item.at_range || item.at_display || '';
            var grouped = item.grouped && item.steps && item.steps.length > 0;

            if (grouped) {
                var count = item.step_count || item.steps.length;
                return (
                    '<li class="ap-activity-feed__item ap-activity-feed__item--grouped" data-ap-activity-id="' + escHtml(item.id || '') + '">' +
                    '<button type="button" class="ap-activity-group-toggle" aria-expanded="false" aria-label="Показать маршрут">' +
                    '<svg class="ap-activity-group-toggle__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>' +
                    '</button>' +
                    '<span class="ap-activity-feed__dot' + dotClass + '" aria-hidden="true"></span>' +
                    '<div class="ap-activity-feed__body">' +
                    '<div class="ap-activity-feed__top">' + badge +
                    '<p class="ap-activity-feed__text">' +
                    '<span class="ap-activity-feed__email">' + escHtml(item.email || '—') + '</span>' +
                    ' — ' + escHtml(item.text || '') +
                    '</p></div>' +
                    '<p class="ap-activity-group-hint">' +
                    (mode === 'compact'
                        ? count + ' ' + (count === 1 ? 'раздел' : 'раздела')
                        : count + ' ' + (count === 1 ? 'переход' : count < 5 ? 'перехода' : 'переходов') + ' · нажмите, чтобы раскрыть маршрут') +
                    '</p>' +
                    renderSteps(item.steps) +
                    '<time class="ap-activity-feed__time" datetime="' + escHtml(item.at_iso || '') + '">' + escHtml(timeLabel) + '</time>' +
                    '</div></li>'
                );
            }

            return (
                '<li class="ap-activity-feed__item" data-ap-activity-id="' + escHtml(item.id || '') + '">' +
                '<span class="ap-activity-feed__spacer" aria-hidden="true"></span>' +
                '<span class="ap-activity-feed__dot' + dotClass + '" aria-hidden="true"></span>' +
                '<div class="ap-activity-feed__body">' +
                '<div class="ap-activity-feed__top">' + badge +
                '<p class="ap-activity-feed__text">' +
                '<span class="ap-activity-feed__email">' + escHtml(item.email || '—') + '</span>' +
                ' — ' + escHtml(item.text || '') +
                '</p></div>' +
                '<time class="ap-activity-feed__time" datetime="' + escHtml(item.at_iso || '') + '">' +
                escHtml(item.at_display || '') +
                '</time></div></li>'
            );
        }

        function bindGroupToggles() {
            mount.querySelectorAll('.ap-activity-feed__item--grouped').forEach(function (row) {
                var btn = row.querySelector('.ap-activity-group-toggle');
                if (!btn || btn.dataset.apBound) {
                    return;
                }
                btn.dataset.apBound = '1';
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var open = row.classList.toggle('is-open');
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
            });
        }

        function renderList(items) {
            var wide = mode === 'full' ? ' ap-activity-feed--wide' : '';
            var id = mode === 'full' ? ' id="ap-activity-feed"' : ' id="ap-dash-activity-feed"';
            var label = mode === 'full' ? 'События' : 'Последние события';
            var html =
                '<ul class="ap-activity-feed' + wide + '"' + id + ' data-ap-activity-list aria-label="' + escHtml(label) + '">';
            for (var i = 0; i < items.length; i++) {
                html += renderItem(items[i]);
            }
            html += '</ul>';
            mount.innerHTML = html;
            bindGroupToggles();
            mount.classList.add('ap-activity-panel__mount--flash');
            window.setTimeout(function () {
                mount.classList.remove('ap-activity-panel__mount--flash');
            }, 400);
        }

        function fetchFeed() {
            if (busy) {
                return;
            }
            busy = true;
            if (abortCtrl) {
                abortCtrl.abort();
            }
            abortCtrl = typeof AbortController !== 'undefined' ? new AbortController() : null;

            var params = readFilters();
            var url = feedUrl + '?' + new URLSearchParams(params).toString();

            setLoading(true);
            setStatus('Обновление…');

            var opts = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
            if (abortCtrl) {
                opts.signal = abortCtrl.signal;
            }

            fetch(url, opts)
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }
                    return res.json();
                })
                .then(function (data) {
                    var items = data.items || [];
                    if (items.length === 0) {
                        mount.innerHTML = '';
                        setEmpty(true);
                    } else {
                        renderList(items);
                        setEmpty(false);
                    }
                    var gen = data.generated_at ? new Date(data.generated_at) : new Date();
                    var timeStr = gen.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    var noun = items.length === 1 ? 'событие' : items.length < 5 ? 'события' : 'событий';
                    setStatus(items.length + ' ' + noun + ' · ' + timeStr, false);
                    syncUrl();
                    syncPeriodHighlight();
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    setStatus('Не удалось загрузить ленту', true);
                })
                .finally(function () {
                    busy = false;
                    setLoading(false);
                    abortCtrl = null;
                });
        }

        function scheduleFetch() {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = window.setTimeout(function () {
                debounceTimer = null;
                fetchFeed();
            }, DEBOUNCE_MS);
        }

        function startPoll() {
            stopPoll();
            if (!liveInput || !liveInput.checked) {
                return;
            }
            pollTimer = window.setInterval(fetchFeed, POLL_MS);
        }

        function stopPoll() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        if (periodGroup) {
            periodGroup.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-ap-period]');
                if (!btn) {
                    return;
                }
                applyPeriod(btn.getAttribute('data-ap-period'));
            });
        }

        if (form) {
            form.addEventListener('change', function (e) {
                if (e.target && (e.target.name === 'date_from' || e.target.name === 'date_to')) {
                    syncPeriodHighlight();
                }
                scheduleFetch();
            });
            form.addEventListener('input', function (e) {
                if (e.target && e.target.name === 'user') {
                    scheduleFetch();
                }
            });
        }

        if (resetBtn && form) {
            resetBtn.addEventListener('click', function () {
                form.reset();
                form.querySelectorAll('input[name="kinds[]"]').forEach(function (cb) {
                    cb.checked = false;
                });
                syncPeriodHighlight();
                scheduleFetch();
            });
        }

        if (liveInput) {
            liveInput.addEventListener('change', function () {
                panel.classList.toggle('ap-activity-panel--live-off', !liveInput.checked);
                if (liveInput.checked) {
                    startPoll();
                    fetchFeed();
                } else {
                    stopPoll();
                    setStatus('Автообновление выключено');
                }
            });
            panel.classList.toggle('ap-activity-panel--live-off', !liveInput.checked);
        }

        syncPeriodHighlight();
        fetchFeed();
        if (liveInput && liveInput.checked) {
            startPoll();
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPoll();
            } else if (liveInput && liveInput.checked) {
                fetchFeed();
                startPoll();
            }
        });
    }

    function boot() {
        document.querySelectorAll('[data-ap-activity-panel]').forEach(initPanel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
