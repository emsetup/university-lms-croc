(function () {
    'use strict';

    function $(id) {
        return document.getElementById(id);
    }

    function init() {
        var modal = $('ap-audience-modal');
        if (!modal || typeof window.ContentAudiencePicker !== 'function') {
            return;
        }

        var titleEl = $('ap-audience-modal-title');
        var saveBtn = $('ap-audience-modal-save');
        var pickerRoot = $('ap-audience-picker-root');
        var picker = null;
        var current = { url: '', method: 'GET' };

        function openModal(opts) {
            current = opts;
            if (titleEl) titleEl.textContent = opts.title || 'Доступ к материалу';
            modal.hidden = false;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('ap-modal-open');
            fetch(opts.loadUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('load failed');
                    }
                    return r.json();
                })
                .then(function (data) {
                    if (!picker) {
                        picker = new window.ContentAudiencePicker(pickerRoot, {
                            searchUrl: opts.searchUrl || '',
                            resolveUrl: opts.resolveUrl || '',
                            readOnly: !!opts.readOnly,
                        });
                    }
                    picker.searchUrl = opts.searchUrl || '';
                    picker.resolveUrl = opts.resolveUrl || '';
                    picker.readOnly = !!opts.readOnly;
                    picker.setData(data);
                })
                .catch(function () {
                    alert('Не удалось загрузить настройки доступа. Проверьте, что выполнена миграция БД (php artisan migrate).');
                    closeModal();
                });
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            modal.hidden = true;
            document.body.classList.remove('ap-modal-open');
        }

        function save() {
            if (!picker || !current.saveUrl) return;
            var err = picker.validate();
            if (err) {
                alert(err);
                return;
            }
            var csrf = document.querySelector('meta[name="csrf-token"]');
            var token = csrf ? csrf.getAttribute('content') : '';
            if (!token) {
                var wb = document.querySelector('[data-ap-csrf]');
                token = wb ? (wb.getAttribute('data-ap-csrf') || '') : '';
            }
            if (!token) {
                var inp = document.querySelector('input[name="_token"]');
                token = inp ? inp.value : '';
            }
            if (!token) {
                alert('Нет CSRF-токена. Обновите страницу.');
                return;
            }
            saveBtn.disabled = true;
            fetch(current.saveUrl, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(picker.getPayload()),
            })
                .then(function (r) {
                    return r.json().then(function (d) {
                        return { ok: r.ok, data: d };
                    });
                })
                .then(function (res) {
                    saveBtn.disabled = false;
                    if (!res.ok) {
                        var msg = res.data && (res.data.message || (res.data.errors && Object.values(res.data.errors)[0]));
                        alert(msg || 'Ошибка сохранения');
                        return;
                    }
                    if (current.onSaved) current.onSaved(res.data);
                    closeModal();
                })
                .catch(function () {
                    saveBtn.disabled = false;
                    alert('Ошибка сети');
                });
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ap-open-audience]');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            openModal({
                title: btn.getAttribute('data-audience-title') || 'Доступ',
                loadUrl: btn.getAttribute('data-audience-load-url'),
                saveUrl: btn.getAttribute('data-audience-save-url'),
                searchUrl: btn.getAttribute('data-audience-search-url'),
                resolveUrl: btn.getAttribute('data-audience-resolve-url'),
                readOnly: btn.getAttribute('data-audience-readonly') === '1',
                onSaved: function (data) {
                    var badge = document.querySelector(
                        '[data-audience-badge-for="' + btn.getAttribute('data-audience-target') + '"]'
                    );
                    if (badge) {
                        if (data.summary) {
                            badge.textContent = data.summary;
                            badge.hidden = false;
                        } else {
                            badge.hidden = true;
                        }
                    }
                },
            });
        });

        var closeBtn = $('ap-audience-modal-close');
        var cancelBtn = $('ap-audience-modal-cancel');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (saveBtn) saveBtn.addEventListener('click', save);
        var backdrop = modal.querySelector('.ap-modal__backdrop');
        if (backdrop) backdrop.addEventListener('click', closeModal);

        window.ApContentAudience = {
            open: openModal,
            close: closeModal,
            getPicker: function () {
                return picker;
            },
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
