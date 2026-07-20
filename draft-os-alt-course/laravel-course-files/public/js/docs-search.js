/**
 * Быстрый поиск по справке портала (/docs).
 * Индекс: window.DocsSearchIndex (title, summary, section, haystack с текстом статей).
 */
(function () {
    'use strict';

    function norm(s) {
        return String(s || '')
            .toLowerCase()
            .replace(/ё/g, 'е')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function tokenize(q) {
        return norm(q).split(' ').filter(function (t) {
            return t.length > 0;
        });
    }

    function scoreItem(item, tokens) {
        if (!tokens.length) return 0;
        var hay = item.haystack || '';
        var title = norm(item.title);
        var summary = norm(item.summary);
        var score = 0;
        for (var i = 0; i < tokens.length; i++) {
            var t = tokens[i];
            if (title.indexOf(t) !== -1) score += 12;
            else if (summary.indexOf(t) !== -1) score += 6;
            else if (hay.indexOf(t) !== -1) score += 2;
            else return -1;
        }
        if (tokens.length === 1 && title.indexOf(tokens[0]) === 0) score += 8;
        return score;
    }

    function search(index, query) {
        var tokens = tokenize(query);
        if (!tokens.length) return [];
        var hits = [];
        for (var i = 0; i < index.length; i++) {
            var s = scoreItem(index[i], tokens);
            if (s >= 0) {
                hits.push({ item: index[i], score: s });
            }
        }
        hits.sort(function (a, b) {
            return b.score - a.score || a.item.title.localeCompare(b.item.title, 'ru');
        });
        return hits.slice(0, 40);
    }

    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function highlight(text, tokens) {
        var out = esc(text);
        tokens.forEach(function (t) {
            if (t.length < 2) return;
            var re = new RegExp('(' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            out = out.replace(re, '<mark>$1</mark>');
        });
        return out;
    }

    function filterSidebar(slugs) {
        var allow = null;
        if (slugs) {
            allow = {};
            slugs.forEach(function (s) {
                allow[s] = true;
            });
        }
        document.querySelectorAll('.docs-sidebar__section').forEach(function (sec) {
            var any = false;
            sec.querySelectorAll('.docs-nav-link').forEach(function (a) {
                var slug = a.getAttribute('data-docs-slug') || '';
                var show = !allow || !!allow[slug];
                a.hidden = !show;
                if (show) any = true;
            });
            sec.hidden = allow ? !any : false;
        });
    }

    function filterIndexCards(slugs) {
        var allow = null;
        if (slugs) {
            allow = {};
            slugs.forEach(function (s) {
                allow[s] = true;
            });
        }
        document.querySelectorAll('.docs-index-section-card').forEach(function (sec) {
            var any = false;
            sec.querySelectorAll('.docs-article-card').forEach(function (card) {
                var slug = card.getAttribute('data-docs-slug') || '';
                var show = !allow || !!allow[slug];
                card.hidden = !show;
                if (show) any = true;
            });
            sec.hidden = allow ? !any : false;
        });
    }

    function renderResults(root, hits, tokens, query) {
        var box = root.querySelector('[data-docs-search-results]');
        var empty = root.querySelector('[data-docs-search-empty]');
        var status = root.querySelector('[data-docs-search-status]');
        if (!box) return;

        if (!query) {
            box.hidden = true;
            box.innerHTML = '';
            if (empty) empty.hidden = true;
            if (status) status.textContent = '';
            filterSidebar(null);
            filterIndexCards(null);
            return;
        }

        if (!hits.length) {
            box.hidden = true;
            box.innerHTML = '';
            if (empty) empty.hidden = false;
            if (status) status.textContent = 'Ничего не найдено';
            filterSidebar([]);
            filterIndexCards([]);
            return;
        }

        if (empty) empty.hidden = true;
        var slugs = hits.map(function (h) {
            return h.item.slug;
        });
        filterSidebar(slugs);
        filterIndexCards(slugs);

        var html = '<ul class="docs-search__list" role="listbox">';
        hits.forEach(function (h, idx) {
            var it = h.item;
            html +=
                '<li role="option">' +
                '<a class="docs-search__hit" href="' +
                esc(it.url) +
                '" data-docs-search-hit data-idx="' +
                idx +
                '">' +
                '<span class="docs-search__hit-section">' +
                esc(it.section) +
                '</span>' +
                '<span class="docs-search__hit-title">' +
                highlight(it.title, tokens) +
                '</span>' +
                (it.summary
                    ? '<span class="docs-search__hit-summary">' + highlight(it.summary, tokens) + '</span>'
                    : '') +
                '</a></li>';
        });
        html += '</ul>';
        box.innerHTML = html;
        box.hidden = false;
        if (status) {
            status.textContent = hits.length + ' ' + (hits.length === 1 ? 'статья' : hits.length < 5 ? 'статьи' : 'статей');
        }
    }

    function bindRoot(root, index) {
        var input = root.querySelector('[data-docs-search-input]');
        if (!input) return;

        var activeIdx = -1;

        var clearBtn = root.querySelector('[data-docs-search-clear]');

        function syncClear() {
            if (!clearBtn) return;
            clearBtn.hidden = !input.value;
        }

        function run() {
            var q = input.value;
            var tokens = tokenize(q);
            var hits = search(index, q);
            activeIdx = -1;
            syncClear();
            renderResults(root, hits, tokens, norm(q));
        }

        var timer = null;
        input.addEventListener('input', function () {
            if (timer) clearTimeout(timer);
            timer = setTimeout(run, 80);
        });

        input.addEventListener('keydown', function (ev) {
            var hits = root.querySelectorAll('[data-docs-search-hit]');
            if (ev.key === 'Escape') {
                if (input.value) {
                    input.value = '';
                    run();
                    ev.preventDefault();
                }
                return;
            }
            if (ev.key === 'ArrowDown' && hits.length) {
                ev.preventDefault();
                activeIdx = Math.min(activeIdx + 1, hits.length - 1);
                hits[activeIdx].focus();
            }
            if (ev.key === 'ArrowUp' && hits.length) {
                ev.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                hits[activeIdx].focus();
            }
            if (ev.key === 'Enter' && activeIdx < 0 && hits.length === 1) {
                ev.preventDefault();
                window.location.href = hits[0].getAttribute('href');
            }
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                input.focus();
                run();
            });
        }
        syncClear();
    }

    function boot() {
        var index = window.DocsSearchIndex;
        if (!Array.isArray(index) || !index.length) return;

        document.querySelectorAll('[data-docs-search]').forEach(function (root) {
            bindRoot(root, index);
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key !== '/' && !(ev.key === 'k' && (ev.ctrlKey || ev.metaKey))) return;
            var tag = (ev.target && ev.target.tagName) || '';
            if (tag === 'INPUT' || tag === 'TEXTAREA' || (ev.target && ev.target.isContentEditable)) return;
            var input = document.querySelector('[data-docs-search-input]');
            if (!input) return;
            ev.preventDefault();
            input.focus();
            input.select();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
