(function () {
    'use strict';

    var openBtn = document.querySelector('[data-ap-ad-mail-open]');
    var modal = document.getElementById('ap-ad-mail-modal');
    if (!openBtn || !modal) return;

    var searchUrl = openBtn.getAttribute('data-search-url') || '';
    var notifyUrl = openBtn.getAttribute('data-notify-url') || '';
    var csrf = openBtn.getAttribute('data-csrf') || '';
    var searchEl = document.getElementById('ap-ad-mail-search');
    var resultsEl = document.getElementById('ap-ad-mail-results');
    var chipsEl = document.getElementById('ap-ad-mail-chips');
    var msgEl = document.getElementById('ap-ad-mail-msg');
    var sendBtn = document.getElementById('ap-ad-mail-send');
    var selected = new Map();
    var searchTimer = null;
    var abortCtrl = null;

    function setMsg(text, isError) {
        if (!msgEl) return;
        if (!text) {
            msgEl.hidden = true;
            msgEl.textContent = '';
            return;
        }
        msgEl.hidden = false;
        msgEl.textContent = text;
        msgEl.style.color = isError ? '#b91c1c' : '';
    }

    function openModal() {
        modal.hidden = false;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ap-modal-open');
        setMsg('');
        if (searchEl) {
            searchEl.value = '';
            setTimeout(function () {
                searchEl.focus();
            }, 30);
        }
        renderChips();
        loadBrowse();
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ap-modal-open');
        modal.hidden = true;
        if (abortCtrl) {
            abortCtrl.abort();
            abortCtrl = null;
        }
    }

    function loadBrowse() {
        doSearch('', true);
    }

    function renderChips() {
        if (!chipsEl) return;
        chipsEl.innerHTML = '';
        selected.forEach(function (item, id) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'ap-audience-chip';
            chip.setAttribute('data-id', String(id));
            chip.title = 'Убрать';
            chip.innerHTML =
                '<span class="ap-audience-chip__label">' +
                escapeHtml(item.name) +
                '</span><span class="ap-muted" style="margin-left:0.35rem">' +
                escapeHtml(item.mail) +
                '</span>';
            chip.addEventListener('click', function () {
                selected.delete(id);
                renderChips();
                updateSend();
            });
            chipsEl.appendChild(chip);
        });
        updateSend();
    }

    function updateSend() {
        if (sendBtn) sendBtn.disabled = selected.size === 0 || sendBtn.dataset.busy === '1';
    }

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderResults(items) {
        if (!resultsEl) return;
        resultsEl.innerHTML = '';
        if (!items || !items.length) {
            resultsEl.innerHTML = '<p class="ap-muted small" style="margin:0.35rem 0">Ничего не найдено</p>';
            return;
        }
        items.forEach(function (item) {
            var id = Number(item.id);
            if (!id) return;
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'btn btn-ghost btn-sm';
            row.style.cssText = 'display:block;width:100%;text-align:left;margin:0.15rem 0';
            row.disabled = selected.has(id);
            row.innerHTML =
                '<strong>' +
                escapeHtml(item.name) +
                '</strong><br><span class="ap-muted small">' +
                escapeHtml(item.mail) +
                '</span>';
            row.addEventListener('click', function () {
                selected.set(id, { id: id, mail: item.mail, name: item.name });
                renderChips();
                renderResults(items);
            });
            resultsEl.appendChild(row);
        });
    }

    function doSearch(q, isBrowse) {
        if (abortCtrl) abortCtrl.abort();
        var term = (q || '').trim();
        if (!isBrowse && term.length < 2) {
            loadBrowse();
            return;
        }
        abortCtrl = new AbortController();
        var url = searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(term);
        if (isBrowse || term === '') {
            url += (url.indexOf('?') >= 0 ? '&' : '?') + 'browse=1';
        }
        if (resultsEl) {
            resultsEl.innerHTML = '<p class="ap-muted small" style="margin:0.35rem 0">Загрузка…</p>';
        }
        fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: abortCtrl.signal,
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                renderResults((data && data.items) || []);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                setMsg('Ошибка поиска', true);
            });
    }

    openBtn.addEventListener('click', openModal);
    modal.querySelectorAll('[data-ap-ad-mail-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            var q = (searchEl.value || '').trim();
            searchTimer = setTimeout(function () {
                if (q.length === 0) {
                    loadBrowse();
                    return;
                }
                doSearch(q, false);
            }, 220);
        });
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', function () {
            if (selected.size === 0 || sendBtn.dataset.busy === '1') return;
            var ids = Array.from(selected.keys());
            sendBtn.dataset.busy = '1';
            updateSend();
            setMsg('Отправка…');
            fetch(notifyUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ group_ids: ids }),
            })
                .then(function (r) {
                    return r.json().then(function (data) {
                        return { ok: r.ok, data: data };
                    });
                })
                .then(function (res) {
                    sendBtn.dataset.busy = '0';
                    updateSend();
                    if (!res.ok || !res.data || !res.data.ok) {
                        setMsg((res.data && res.data.message) || 'Не удалось отправить', true);
                        return;
                    }
                    setMsg(res.data.message || 'Отправлено');
                    selected.clear();
                    renderChips();
                    if (resultsEl) resultsEl.innerHTML = '';
                    if (searchEl) searchEl.value = '';
                })
                .catch(function () {
                    sendBtn.dataset.busy = '0';
                    updateSend();
                    setMsg('Сеть или сервер недоступны', true);
                });
        });
    }
})();
