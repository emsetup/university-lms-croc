/**
 * Split-панель «Обучающиеся курса» (админка).
 */
(function () {
    'use strict';

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var root = document.querySelector('[data-ap-learners]');
    if (!root) return;

    var csrf = root.getAttribute('data-ap-csrf') || '';
    var preEmail = (root.getAttribute('data-ap-preselect-email') || '').trim();
    var jsonNode = document.getElementById('ap-learners-json-data');
    try {
        learners = JSON.parse(jsonNode ? jsonNode.textContent : '[]');
    } catch (e) {
        learners = [];
    }

    var listEl = $('ap-learners-list');
    var searchEl = $('ap-learners-search');
    var emptyEl = $('ap-learners-empty');
    var detailEl = $('ap-learners-detail');
    var innerEl = $('ap-learners-detail-inner');
    var jumpEl = $('ap-learners-jump');
    var modulesEl = $('ap-learners-modules');

    var selectedId = null;
    var pendingReset = null;

    function setUrlUser(email) {
        var u = new URL(window.location.href);
        if (email) {
            u.searchParams.set('user', email);
        } else {
            u.searchParams.delete('user');
        }
        window.history.replaceState({}, '', u.toString());
    }

    function renderList(filter) {
        if (!listEl) return;
        var q = (filter || '').trim().toLowerCase();
        listEl.innerHTML = '';
        learners.forEach(function (L) {
            var fnLower = ((L.full_name || '') + '').toLowerCase();
            if (q && L.email.toLowerCase().indexOf(q) === -1 && fnLower.indexOf(q) === -1) {
                return;
            }
            var li = document.createElement('li');
            li.className = 'ap-learners-list__item';
            li.setAttribute('role', 'option');
            li.setAttribute('data-learner-id', String(L.id));
            li.setAttribute('data-email', L.email);
            if (selectedId === L.id) {
                li.classList.add('is-active');
            }
            var fn = ((L.full_name || '') + '').trim();
            var mainBlock =
                fn !== ''
                    ? '<div class="ap-learners-list__title">' +
                      esc(fn) +
                      '</div>' +
                      '<div class="ap-learners-list__email-sub">' +
                      esc(L.email) +
                      '</div>'
                    : '<div class="ap-learners-list__email">' + esc(L.email) + '</div>';
            li.innerHTML =
                '<div class="ap-learners-list__avatar" aria-hidden="true">' +
                esc(L.initials) +
                '</div>' +
                '<div class="ap-learners-list__main">' +
                mainBlock +
                '<div class="ap-learners-list__meta ap-muted">' +
                esc(L.meta) +
                '</div></div>';
            li.addEventListener('click', function () {
                selectLearner(L);
            });
            listEl.appendChild(li);
        });
        if (listEl.children.length === 0) {
            var empty = document.createElement('li');
            empty.className = 'ap-muted ap-learners-list__empty';
            empty.textContent = 'Никого не найдено.';
            listEl.appendChild(empty);
        }
    }

    function animateDetailIn() {
        if (!innerEl) return;
        innerEl.classList.remove('is-entering');
        void innerEl.offsetWidth;
        innerEl.classList.add('is-entering');
    }

    function selectLearner(L) {
        selectedId = L.id;
        setUrlUser(L.email);
        if (modulesEl) modulesEl.innerHTML = '';
        if (jumpEl) jumpEl.innerHTML = '';
        document.querySelectorAll('.ap-learners-list__item').forEach(function (li) {
            li.classList.toggle('is-active', li.getAttribute('data-learner-id') === String(L.id));
        });
        if (emptyEl) emptyEl.hidden = true;
        if (detailEl) {
            detailEl.hidden = false;
            detailEl.setAttribute('aria-busy', 'true');
        }
        animateDetailIn();
        fetch(L.detail_url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) {
                return r.json();
            })
            .then(function (j) {
                if (!j || !j.ok || !j.data) {
                    window.alert((j && j.message) || 'Не удалось загрузить данные.');
                    return;
                }
                fillDetail(j.data);
            })
            .catch(function () {
                window.alert('Ошибка сети при загрузке профиля.');
            })
            .finally(function () {
                if (detailEl) detailEl.removeAttribute('aria-busy');
            });
    }

    function fillDetail(d) {
        var lr = d.learner || {};
        var sm = d.summary || {};
        $('ap-learners-av').textContent = lr.initials || '—';
        var p1 = $('ap-learners-primary');
        var p2 = $('ap-learners-secondary');
        var name = ((lr.full_name || '') + '').trim();
        if (p1) {
            if (name) {
                p1.textContent = name;
                if (p2) {
                    p2.textContent = lr.email || '';
                    p2.hidden = false;
                }
            } else {
                p1.textContent = lr.email || '';
                if (p2) {
                    p2.textContent = '';
                    p2.hidden = true;
                }
            }
        }
        var days = sm.span_days != null && sm.span_days !== '' ? ' · ' + sm.span_days + ' дн.' : '';
        $('ap-learners-summary').textContent =
            (sm.modules_passed || 0) +
            '/' +
            (sm.module_total || 0) +
            ' сдано · ' +
            (sm.points || 0) +
            '/' +
            (sm.points_max || 0) +
            ' баллов · ' +
            (sm.time_tracked_label || '—') +
            days +
            ' · ' +
            (sm.percent || 0) +
            '%';
        var bar = $('ap-learners-bar');
        if (bar) bar.style.width = Math.max(0, Math.min(100, parseInt(sm.percent, 10) || 0)) + '%';

        if (jumpEl) {
            jumpEl.innerHTML = '';
            (d.modules || []).forEach(function (m) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'ap-learners-jump__pill';
                b.textContent = 'M' + m.ordinal;
                b.setAttribute('data-scroll-mod', String(m.id));
                b.addEventListener('click', function () {
                    var el = document.getElementById('ap-learner-mod-' + m.id);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                jumpEl.appendChild(b);
            });
        }

        if (modulesEl) {
            modulesEl.innerHTML = '';
            (d.modules || []).forEach(function (m) {
                var card = document.createElement('article');
                card.className = 'ap-learners-mod';
                card.id = 'ap-learner-mod-' + m.id;
                var head =
                    '<header class="ap-learners-mod__head">' +
                    '<h2 class="ap-learners-mod__title">Модуль ' +
                    esc(m.ordinal) +
                    ' · ' +
                    esc(m.title) +
                    '</h2>' +
                    '<span class="ap-muted ap-learners-mod__time">' +
                    esc(m.time_label) +
                    '</span></header>';
                var rows = '<ul class="ap-learners-sec-list">';
                (m.sections || []).forEach(function (s) {
                    var pct = parseInt(s.progress_percent, 10) || 0;
                    rows +=
                        '<li class="ap-learners-sec-row">' +
                        '<span class="ap-sec-chip ap-sec-chip--' +
                        esc(s.type) +
                        '">' +
                        esc(s.type_label) +
                        '</span>' +
                        '<span class="ap-learners-sec-row__title">' +
                        esc(s.title) +
                        '</span>' +
                        '<div class="ap-learners-sec-row__bar-wrap"><div class="ap-learners-sec-row__bar" style="width:' +
                        pct +
                        '%"></div></div>' +
                        '<span class="ap-learners-sec-row__pct">' +
                        pct +
                        '%</span>' +
                        '<div class="ap-learners-sec-row__actions">' +
                        '<a class="btn btn-ghost btn-sm" href="' + esc(s.view_url) + '" target="_blank" rel="noopener">Посмотреть</a>';
                    if (s.show_reset && s.reset_step) {
                        rows +=
                            '<button type="button" class="btn btn-ghost btn-sm ap-learners-reset-btn" data-reset-step="' +
                            esc(s.reset_step) +
                            '" data-reset-url="' +
                            esc(m.reset_post_url) +
                            '" data-reset-type="' +
                            esc(s.type_label) +
                            '" data-reset-title="' +
                            esc(s.title) +
                            '" data-mod-ordinal="' +
                            esc(String(m.ordinal)) +
                            '" data-mod-title="' +
                            esc(m.title) +
                            '">Сброс</button>';
                    }
                    rows += '</div></li>';
                });
                rows += '</ul>';
                var foot =
                    '<footer class="ap-learners-mod__foot">Итог модуля: <strong>' +
                    esc(String(m.points)) +
                    '</strong> / 100</footer>';
                card.innerHTML = head + rows + foot;
                modulesEl.appendChild(card);
            });
        }

        modulesEl.querySelectorAll('.ap-learners-reset-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openResetModal({
                    url: btn.getAttribute('data-reset-url'),
                    step: btn.getAttribute('data-reset-step'),
                    typeLabel: btn.getAttribute('data-reset-type'),
                    secTitle: btn.getAttribute('data-reset-title'),
                    modOrdinal: btn.getAttribute('data-mod-ordinal'),
                    modTitle: btn.getAttribute('data-mod-title'),
                    email: lr.email,
                });
            });
        });
    }

    function openResetModal(ctx) {
        pendingReset = ctx;
        var modal = $('ap-learners-reset-modal');
        var txt = $('ap-learners-reset-text');
        if (!modal || !txt) return;
        txt.innerHTML =
            'Сбросить попытки? <strong>' +
            esc(ctx.typeLabel) +
            '</strong> · Модуль ' +
            esc(ctx.modOrdinal) +
            '<br><span class="ap-muted">' +
            esc(ctx.modTitle) +
            '</span><br><br><strong>' +
            esc(ctx.email) +
            '</strong> сможет пересдать этот раздел.';
        modal.hidden = false;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeResetModal() {
        var modal = $('ap-learners-reset-modal');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;
        pendingReset = null;
    }

    function confirmReset() {
        if (!pendingReset) return;
        fetch(pendingReset.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ step: pendingReset.step, confirm: true }),
        })
            .then(function (r) {
                return r.json().then(function (j) {
                    return { ok: r.ok, j: j };
                });
            })
            .then(function (x) {
                if (!x.j || !x.j.ok) {
                    window.alert((x.j && x.j.message) || 'Не удалось выполнить сброс.');
                    return;
                }
                closeResetModal();
                var cur = learners.find(function (L) {
                    return L.id === selectedId;
                });
                if (cur) selectLearner(cur);
            })
            .catch(function () {
                window.alert('Ошибка сети.');
            });
    }

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            renderList(searchEl.value);
        });
    }

    document.querySelectorAll('[data-ap-learners-modal-close]').forEach(function (b) {
        b.addEventListener('click', closeResetModal);
    });
    var cbtn = $('ap-learners-reset-confirm');
    if (cbtn) cbtn.addEventListener('click', confirmReset);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeResetModal();
    });

    renderList('');
    if (preEmail) {
        var dec = preEmail;
        try {
            dec = decodeURIComponent(preEmail);
        } catch (err) {}
        var found = learners.find(function (L) {
            return L.email.toLowerCase() === dec.toLowerCase();
        });
        if (found) selectLearner(found);
    }
})();
