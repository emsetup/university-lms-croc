(function () {
    'use strict';

    var root;
    var overlay;
    var input;
    var resultsEl;
    var searchUrl;
    var debounceTimer;
    var flat = [];
    var selected = 0;
    var canPortalLearners;
    var canCreateCourse;
    var canStaff;
    var canDocker;
    var dockerUrl;
    var staffUrl;

    function readFlags() {
        var b = document.querySelector('[data-ap-palette-search]') || document.body;
        searchUrl = b.getAttribute('data-ap-palette-search') || '';
        canPortalLearners = b.getAttribute('data-ap-can-portal-learners') === '1';
        canCreateCourse = b.getAttribute('data-ap-can-create-course') === '1';
        canStaff = b.getAttribute('data-ap-can-staff') === '1';
        canDocker = b.getAttribute('data-ap-can-docker') === '1';
        dockerUrl = b.getAttribute('data-ap-docker-url') || '';
        staffUrl = b.getAttribute('data-ap-staff-url') || '';
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function highlightAll(text, needle) {
        if (!needle) {
            return escHtml(text);
        }
        var esc = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var parts = String(text).split(new RegExp('(' + esc + ')', 'ig'));
        return parts.map(function (p, i) {
            return i % 2 === 1 ? '<b>' + escHtml(p) + '</b>' : escHtml(p);
        }).join('');
    }

    function isOpen() {
        return root && root.classList.contains('is-open');
    }

    function open() {
        if (!root) {
            return;
        }
        root.hidden = false;
        root.classList.add('is-open');
        root.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ap-cmd-palette-open');
        selected = 0;
        if (input) {
            input.value = '';
            input.focus();
        }
        fetchResults('');
    }

    function close() {
        if (!root) {
            return;
        }
        root.classList.remove('is-open');
        root.setAttribute('aria-hidden', 'true');
        root.hidden = true;
        document.body.classList.remove('ap-cmd-palette-open');
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
    }

    function toggle() {
        if (isOpen()) {
            close();
        } else {
            open();
        }
    }

    function buildActions() {
        var rows = [];
        if (canCreateCourse) {
            rows.push({
                kind: 'action',
                action: 'create-course',
                primary: 'Создать курс',
                secondary: 'Новый курс в каталоге',
            });
        }
        if (canDocker && dockerUrl) {
            rows.push({ kind: 'url', url: dockerUrl, primary: 'Перейти в Docker библиотеку', secondary: '' });
        }
        if (canStaff && staffUrl) {
            rows.push({ kind: 'url', url: staffUrl, primary: 'Перейти к сотрудникам', secondary: '' });
        }
        return rows;
    }

    function render(data, q) {
        flat = [];
        var needle = (data && data.query !== undefined) ? String(data.query) : String(q || '');
        var html = '';

        if (!needle.trim()) {
            html += '<p class="ap-cmd-palette__hint ap-muted">Введите запрос, чтобы искать курсы, модули и email.</p>';
        }

        function group(title, body) {
            if (!body) {
                return;
            }
            html += '<div class="ap-cmd-palette__group" role="group" aria-label="' + escHtml(title) + '">';
            html += '<div class="ap-cmd-palette__group-title">' + escHtml(title) + '</div>';
            html += body;
            html += '</div>';
        }

        var learners = (data && data.learners) || [];
        if (canPortalLearners && learners.length) {
            var lb = '';
            learners.forEach(function (L) {
                var idx = flat.length;
                flat.push({ kind: 'url', url: L.url });
                lb += '<a class="ap-cmd-palette__item" role="option" data-ap-cmd-idx="' + idx + '" href="' + escHtml(L.url) + '">';
                lb += '<span class="ap-cmd-palette__item-primary">' + highlightAll(L.email, needle) + '</span>';
                lb += '</a>';
            });
            group('ОБУЧАЮЩИЕСЯ', lb);
        }

        var courses = (data && data.courses) || [];
        if (courses.length) {
            var cb = '';
            courses.forEach(function (C) {
                var idx = flat.length;
                flat.push({ kind: 'url', url: C.url });
                cb += '<a class="ap-cmd-palette__item" role="option" data-ap-cmd-idx="' + idx + '" href="' + escHtml(C.url) + '">';
                cb += '<span class="ap-cmd-palette__item-primary">' + highlightAll(C.title, needle) + '</span>';
                if (C.slug) {
                    cb += '<span class="ap-cmd-palette__item-secondary"><code>' + escHtml(C.slug) + '</code></span>';
                }
                cb += '</a>';
            });
            group('КУРСЫ', cb);
        }

        var modules = (data && data.modules) || [];
        if (modules.length) {
            var mb = '';
            modules.forEach(function (M) {
                var idx = flat.length;
                flat.push({ kind: 'url', url: M.url });
                mb += '<a class="ap-cmd-palette__item" role="option" data-ap-cmd-idx="' + idx + '" href="' + escHtml(M.url) + '">';
                mb += '<span class="ap-cmd-palette__item-primary">' + highlightAll(M.title, needle) + '</span>';
                if (M.course_title) {
                    mb += '<span class="ap-cmd-palette__item-secondary">' + escHtml(M.course_title) + '</span>';
                }
                mb += '</a>';
            });
            group('МОДУЛИ', mb);
        }

        var actions = buildActions();
        if (actions.length) {
            var ab = '';
            actions.forEach(function (A) {
                var idx = flat.length;
                flat.push(A);
                var href = A.kind === 'url' ? escHtml(A.url) : '#';
                ab += '<a class="ap-cmd-palette__item" role="option" data-ap-cmd-idx="' + idx + '" href="' + href + '"';
                if (A.kind === 'action') {
                    ab += ' data-ap-cmd-action="' + escHtml(A.action) + '"';
                }
                ab += '><span class="ap-cmd-palette__item-primary">' + escHtml(A.primary) + '</span>';
                if (A.secondary) {
                    ab += '<span class="ap-cmd-palette__item-secondary">' + escHtml(A.secondary) + '</span>';
                }
                ab += '</a>';
            });
            group('ДЕЙСТВИЯ', ab);
        }

        if (!html.trim()) {
            html = '<p class="ap-cmd-palette__empty ap-muted">Нет доступных действий или данных.</p>';
        } else if (needle.trim() && flat.length === 0) {
            html = '<p class="ap-cmd-palette__empty ap-muted">Ничего не найдено.</p>' + html;
        }

        resultsEl.innerHTML = html;
        bindResultHover();
        if (flat.length) {
            setSelected(0);
        }
    }

    function bindResultHover() {
        resultsEl.querySelectorAll('[data-ap-cmd-idx]').forEach(function (el) {
            el.addEventListener('mouseenter', function () {
                var i = parseInt(el.getAttribute('data-ap-cmd-idx'), 10);
                if (!isNaN(i)) {
                    setSelected(i);
                }
            });
        });
    }

    function setSelected(i) {
        if (!flat.length) {
            return;
        }
        if (i < 0) {
            i = 0;
        }
        if (i >= flat.length) {
            i = flat.length - 1;
        }
        selected = i;
        resultsEl.querySelectorAll('.ap-cmd-palette__item.is-active').forEach(function (n) {
            n.classList.remove('is-active');
        });
        var cur = resultsEl.querySelector('[data-ap-cmd-idx="' + i + '"]');
        if (cur) {
            cur.classList.add('is-active');
            if (typeof cur.scrollIntoView === 'function') {
                cur.scrollIntoView({ block: 'nearest' });
            }
        }
    }

    function activateSelected() {
        if (!flat.length) {
            return;
        }
        var item = flat[selected];
        if (!item) {
            return;
        }
        if (item.kind === 'action' && item.action === 'create-course') {
            close();
            window.dispatchEvent(new CustomEvent('ap-open-create-course'));
            return;
        }
        if (item.kind === 'url' && item.url) {
            window.location.href = item.url;
        }
    }

    function fetchResults(q) {
        if (!searchUrl) {
            render({ query: q, learners: [], courses: [], modules: [] }, q);
            return;
        }
        var url = searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q);
        fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            })
            .then(function (data) {
                render(data, q);
            })
            .catch(function () {
                resultsEl.innerHTML = '<p class="ap-cmd-palette__empty ap-muted">Не удалось загрузить результаты.</p>';
                flat = [];
            });
    }

    function scheduleFetch() {
        var q = (input && input.value.trim()) || '';
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(function () {
            debounceTimer = null;
            fetchResults(q);
        }, 220);
    }

    function onGlobalKeydown(e) {
        var meta = e.metaKey || e.ctrlKey;
        if (meta && (e.key === 'k' || e.key === 'K')) {
            var t = e.target;
            if (t && t.closest && t.closest('[data-ap-palette-ignore-shortcut]')) {
                return;
            }
            e.preventDefault();
            toggle();
            return;
        }
        if (!isOpen()) {
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
        }
    }

    function init() {
        readFlags();
        root = document.getElementById('ap-command-palette');
        if (!root) {
            return;
        }
        overlay = root.querySelector('[data-ap-cmd-close]');
        input = document.getElementById('ap-cmd-palette-q');
        resultsEl = document.getElementById('ap-cmd-palette-results');

        document.addEventListener('keydown', onGlobalKeydown, true);

        if (overlay) {
            overlay.addEventListener('click', function () {
                close();
            });
        }

        if (input) {
            input.addEventListener('input', function () {
                selected = 0;
                scheduleFetch();
            });
            input.addEventListener('keydown', function (e) {
                if (!isOpen()) {
                    return;
                }
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (flat.length) {
                        setSelected(selected + 1);
                    }
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (flat.length) {
                        setSelected(selected - 1);
                    }
                    return;
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    activateSelected();
                }
            });
        }

        resultsEl.addEventListener('click', function (e) {
            var a = e.target.closest('a.ap-cmd-palette__item');
            if (!a) {
                return;
            }
            var act = a.getAttribute('data-ap-cmd-action');
            if (act === 'create-course') {
                e.preventDefault();
                close();
                window.dispatchEvent(new CustomEvent('ap-open-create-course'));
            }
        });

        var trig = document.getElementById('ap-cmd-palette-trigger');
        if (trig) {
            trig.addEventListener('click', function () {
                open();
            });
        }

        var kbd = document.querySelector('[data-ap-kbd-palette]');
        if (kbd) {
            var mac = /Mac|iPhone|iPod|iPad/i.test(navigator.platform || '') || (navigator.userAgentData && navigator.userAgentData.platform === 'macOS');
            kbd.textContent = mac ? '⌘K' : 'Ctrl+K';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
