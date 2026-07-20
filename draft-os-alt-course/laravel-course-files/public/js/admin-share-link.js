(function () {
    'use strict';

    var modal = document.getElementById('ap-modal-share-link');
    if (!modal) return;

    var panel = modal.querySelector('.ap-share-modal__panel');
    var targetEl = document.getElementById('ap-share-link-target');
    var offEl = document.getElementById('ap-share-link-off');
    var onEl = document.getElementById('ap-share-link-on');
    var urlEl = document.getElementById('ap-share-link-url');
    var urlBox = document.getElementById('ap-share-link-url-box');
    var msgEl = document.getElementById('ap-share-link-msg');
    var enableBtn = document.getElementById('ap-share-link-enable');
    var copyBtn = document.getElementById('ap-share-link-copy');
    var regenBtn = document.getElementById('ap-share-link-regen');
    var disableBtn = document.getElementById('ap-share-link-disable');
    var closeTimer = null;
    var toastTimer = null;
    var busy = false;

    var state = {
        meta: null,
        csrf: '',
        onChange: null
    };

    function csrfToken() {
        if (state.csrf) return state.csrf;
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m && m.content) return m.content;
        var inp = document.querySelector('input[name="_token"]');
        return inp ? inp.value : '';
    }

    function kindLabel(kind) {
        if (kind === 'course') return 'Курс';
        if (kind === 'module') return 'Модуль';
        if (kind === 'survey') return 'Опрос';
        if (kind === 'section') return 'Раздел';
        return 'Цель';
    }

    function setBusy(on) {
        busy = !!on;
        modal.classList.toggle('is-busy', busy);
        [enableBtn, copyBtn, regenBtn, disableBtn].forEach(function (b) {
            if (b) b.disabled = busy;
        });
    }

    function setMsg(text, isErr) {
        if (!msgEl) return;
        if (toastTimer) {
            clearTimeout(toastTimer);
            toastTimer = null;
        }
        if (!text) {
            msgEl.hidden = true;
            msgEl.textContent = '';
            msgEl.classList.remove('is-err', 'is-show');
            return;
        }
        msgEl.hidden = false;
        msgEl.textContent = text;
        msgEl.classList.toggle('is-err', !!isErr);
        // restart animation
        msgEl.classList.remove('is-show');
        void msgEl.offsetWidth;
        msgEl.classList.add('is-show');
        if (!isErr) {
            toastTimer = setTimeout(function () {
                msgEl.classList.remove('is-show');
                setTimeout(function () {
                    if (msgEl.classList.contains('is-show')) return;
                    msgEl.hidden = true;
                }, 220);
            }, 2200);
        }
    }

    function render() {
        var meta = state.meta || {};
        var kind = meta.kind || 'course';
        var title = meta.title || '';
        if (targetEl) {
            targetEl.textContent = kindLabel(kind) + (title ? ' · ' + title : '');
        }
        var active = !!meta.active && !!meta.url;
        if (offEl) {
            offEl.hidden = active;
            if (!active) {
                offEl.classList.remove('is-enter');
                void offEl.offsetWidth;
                offEl.classList.add('is-enter');
            }
        }
        if (onEl) {
            onEl.hidden = !active;
            if (active) {
                onEl.classList.remove('is-enter');
                void onEl.offsetWidth;
                onEl.classList.add('is-enter');
            }
        }
        if (urlEl) urlEl.value = active ? meta.url : '';
        if (copyBtn) copyBtn.classList.remove('is-copied');
        if (urlBox) urlBox.classList.remove('is-flash');
    }

    function openModal() {
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
        modal.hidden = false;
        // force reflow before anim class
        void modal.offsetWidth;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ap-modal-open');
        setMsg('');
        render();
        if (panel) {
            panel.classList.remove('is-pop');
            void panel.offsetWidth;
            panel.classList.add('is-pop');
        }
    }

    function closeModal() {
        if (!modal.classList.contains('is-open')) {
            modal.hidden = true;
            return;
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ap-modal-open');
        setMsg('');
        closeTimer = setTimeout(function () {
            modal.hidden = true;
            closeTimer = null;
        }, 280);
    }

    function notifyChange() {
        if (typeof state.onChange === 'function') {
            try { state.onChange(state.meta); } catch (e) {}
        }
        document.dispatchEvent(new CustomEvent('ap-share-link-changed', { detail: state.meta }));
    }

    function post(url) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok || !data || data.ok === false) {
                    throw new Error((data && data.message) || 'Не удалось выполнить действие.');
                }
                return data;
            });
        });
    }

    function enableOrRegen(confirmRegen) {
        if (busy || !state.meta || !state.meta.generate_url) return;
        if (confirmRegen && state.meta.active) {
            if (!window.confirm('Создать новую ссылку? Старая перестанет работать.')) return;
        }
        setBusy(true);
        setMsg('Готовим ссылку…');
        post(state.meta.generate_url).then(function (data) {
            state.meta.active = true;
            state.meta.url = data.url || null;
            render();
            setMsg('Ссылка готова');
            if (urlBox) {
                urlBox.classList.remove('is-flash');
                void urlBox.offsetWidth;
                urlBox.classList.add('is-flash');
            }
            notifyChange();
        }).catch(function (err) {
            setMsg(err.message || 'Ошибка', true);
        }).finally(function () {
            setBusy(false);
        });
    }

    function disable() {
        if (busy || !state.meta || !state.meta.revoke_url) return;
        if (!window.confirm('Выключить ссылку? Она перестанет открываться.')) return;
        setBusy(true);
        setMsg('Отключаем…');
        post(state.meta.revoke_url).then(function () {
            state.meta.active = false;
            state.meta.url = null;
            render();
            setMsg('Ссылка выключена');
            notifyChange();
        }).catch(function (err) {
            setMsg(err.message || 'Ошибка', true);
        }).finally(function () {
            setBusy(false);
        });
    }

    function copyUrl() {
        if (busy || !urlEl || !urlEl.value) return;
        var done = function () {
            setMsg('Скопировано в буфер');
            if (copyBtn) {
                var textEl = copyBtn.querySelector('.ap-share-modal__copy-text');
                var prev = textEl ? textEl.textContent : '';
                copyBtn.classList.remove('is-copied');
                void copyBtn.offsetWidth;
                copyBtn.classList.add('is-copied');
                if (textEl) textEl.textContent = 'Готово';
                setTimeout(function () {
                    copyBtn.classList.remove('is-copied');
                    if (textEl) textEl.textContent = prev || 'Копировать';
                }, 1600);
            }
            if (urlBox) {
                urlBox.classList.remove('is-flash');
                void urlBox.offsetWidth;
                urlBox.classList.add('is-flash');
            }
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(urlEl.value).then(done).catch(function () {
                urlEl.select();
                document.execCommand('copy');
                done();
            });
        } else {
            urlEl.select();
            document.execCommand('copy');
            done();
        }
    }

    function open(opts) {
        opts = opts || {};
        state.csrf = opts.csrf || csrfToken();
        state.onChange = opts.onChange || null;

        if (opts.meta) {
            state.meta = Object.assign({}, opts.meta);
            openModal();
            return;
        }

        if (opts.metaUrl) {
            openModal();
            if (targetEl) targetEl.textContent = 'Загрузка…';
            if (offEl) offEl.hidden = true;
            if (onEl) onEl.hidden = true;
            setBusy(true);
            fetch(opts.metaUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data || !data.ok || !data.meta) throw new Error((data && data.message) || 'Не удалось загрузить.');
                state.meta = data.meta;
                render();
            }).catch(function (err) {
                setMsg(err.message || 'Ошибка загрузки', true);
            }).finally(function () {
                setBusy(false);
            });
            return;
        }

        console.warn('ApShareLink.open: нужен meta или metaUrl');
    }

    if (enableBtn) enableBtn.addEventListener('click', function () { enableOrRegen(false); });
    if (regenBtn) regenBtn.addEventListener('click', function () { enableOrRegen(true); });
    if (disableBtn) disableBtn.addEventListener('click', disable);
    if (copyBtn) copyBtn.addEventListener('click', copyUrl);

    modal.querySelectorAll('[data-ap-modal-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ap-share-link]');
        if (!btn) return;
        e.preventDefault();
        var metaRaw = btn.getAttribute('data-ap-share-meta');
        var meta = null;
        if (metaRaw) {
            try { meta = JSON.parse(metaRaw); } catch (err) { meta = null; }
        }
        open({
            meta: meta,
            metaUrl: btn.getAttribute('data-ap-share-meta-url') || null,
            csrf: btn.getAttribute('data-ap-share-csrf') || csrfToken()
        });
    });

    window.ApShareLink = {
        open: open,
        close: closeModal
    };
})();
