(function () {
    'use strict';

    var MAX = 5;
    var MAX_BYTES = 5 * 1024 * 1024;

    function qs(root, sel) {
        return root.querySelector(sel);
    }

    function openDialog(dlg) {
        if (!dlg) return;
        if (typeof dlg.showModal === 'function') {
            if (!dlg.open) dlg.showModal();
        } else {
            dlg.setAttribute('open', 'open');
        }
    }

    function closeDialog(dlg) {
        if (!dlg) return;
        if (typeof dlg.close === 'function') {
            dlg.close();
        } else {
            dlg.removeAttribute('open');
        }
    }

    function revokeAll(files) {
        files.forEach(function (f) {
            if (f.url) URL.revokeObjectURL(f.url);
        });
    }

    function init() {
        var fab = document.getElementById('portal-bug-fab');
        var dlg = document.getElementById('portal-bug-dialog');
        var root = dlg && qs(dlg, '[data-pbr-root]');
        if (!fab || !dlg || !root) return;

        var authed = root.getAttribute('data-authenticated') === '1';
        var storeUrl = root.getAttribute('data-store-url') || '';
        var loginUrl = root.getAttribute('data-login-url') || '';
        var csrf = root.getAttribute('data-csrf') || '';
        var form = qs(root, '[data-pbr-form]');
        var guest = qs(root, '[data-pbr-guest]');
        var drop = qs(root, '[data-pbr-drop]');
        var fileInput = qs(root, '[data-pbr-file]');
        var thumbs = qs(root, '[data-pbr-thumbs]');
        var errEl = qs(root, '[data-pbr-error]');
        var okEl = qs(root, '[data-pbr-ok]');
        var pageUrlInput = qs(root, '[data-pbr-page-url]');
        var pageTitleInput = qs(root, '[data-pbr-page-title]');
        var courseWrap = qs(root, '[data-pbr-course-wrap]');
        var courseSelect = qs(root, '[data-pbr-course]');
        var defaultCourseId = root.getAttribute('data-default-course-id') || '';
        var files = [];

        function syncScope() {
            var scope = 'portal';
            var checked = root.querySelector('input[name="scope"]:checked');
            if (checked) scope = checked.value;
            if (courseWrap) {
                courseWrap.hidden = scope !== 'course';
            }
            if (courseSelect) {
                if (scope === 'course') {
                    courseSelect.required = true;
                    if (!courseSelect.value && defaultCourseId) {
                        courseSelect.value = String(defaultCourseId);
                    }
                } else {
                    courseSelect.required = false;
                }
            }
            syncTypePills();
        }


        function setError(msg) {
            if (!errEl) return;
            if (msg) {
                errEl.textContent = msg;
                errEl.hidden = false;
            } else {
                errEl.textContent = '';
                errEl.hidden = true;
            }
        }

        function setOk(msg) {
            if (!okEl) return;
            if (msg) {
                okEl.textContent = msg;
                okEl.hidden = false;
            } else {
                okEl.textContent = '';
                okEl.hidden = true;
            }
        }

        function syncTypePills() {
            root.querySelectorAll('.pbr-type').forEach(function (lab) {
                var inp = lab.querySelector('input');
                lab.classList.toggle('is-active', !!(inp && inp.checked));
            });
        }

        function renderThumbs() {
            if (!thumbs) return;
            thumbs.innerHTML = '';
            files.forEach(function (item, idx) {
                var wrap = document.createElement('div');
                wrap.className = 'pbr-thumb';
                var img = document.createElement('img');
                img.src = item.url;
                img.alt = item.file.name || 'screenshot';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pbr-thumb__remove';
                btn.setAttribute('aria-label', 'Удалить');
                btn.textContent = '×';
                btn.addEventListener('click', function () {
                    if (files[idx] && files[idx].url) URL.revokeObjectURL(files[idx].url);
                    files.splice(idx, 1);
                    renderThumbs();
                });
                wrap.appendChild(img);
                wrap.appendChild(btn);
                thumbs.appendChild(wrap);
            });
        }

        function addFiles(list) {
            Array.prototype.forEach.call(list || [], function (file) {
                if (!file || !file.type || file.type.indexOf('image/') !== 0) return;
                if (file.size > MAX_BYTES) {
                    setError('Файл «' + (file.name || 'image') + '» больше 5 МБ.');
                    return;
                }
                if (files.length >= MAX) {
                    setError('Можно приложить не больше ' + MAX + ' скриншотов.');
                    return;
                }
                files.push({ file: file, url: URL.createObjectURL(file) });
            });
            renderThumbs();
        }

        function resetForm() {
            if (form) form.reset();
            revokeAll(files);
            files = [];
            renderThumbs();
            setError('');
            setOk('');
            if (form) form.classList.remove('is-busy');
            syncTypePills();
            var portalScope = root.querySelector('input[name="scope"][value="portal"]');
            if (portalScope) portalScope.checked = true;
            syncScope();
            if (pageUrlInput) pageUrlInput.value = window.location.href;
            if (pageTitleInput) pageTitleInput.value = document.title || '';
        }

        function showGuestOrForm() {
            if (authed) {
                if (guest) guest.hidden = true;
                if (form) form.hidden = false;
            } else {
                if (form) form.hidden = true;
                if (guest) guest.hidden = false;
            }
        }

        fab.addEventListener('click', function () {
            resetForm();
            showGuestOrForm();
            openDialog(dlg);
            if (authed) {
                var ta = form && form.querySelector('textarea[name="message"]');
                if (ta) setTimeout(function () { try { ta.focus(); } catch (e) {} }, 0);
            }
        });

        root.querySelectorAll('[data-pbr-close]').forEach(function (btn) {
            btn.addEventListener('click', function () { closeDialog(dlg); });
        });

        dlg.addEventListener('click', function (e) {
            if (e.target === dlg) closeDialog(dlg);
        });

        root.querySelectorAll('.pbr-type input').forEach(function (inp) {
            inp.addEventListener('change', function () {
                syncTypePills();
                if (inp.name === 'scope') syncScope();
            });
        });
        syncScope();

        var pick = qs(root, '[data-pbr-pick]');
        if (pick && fileInput) {
            pick.addEventListener('click', function () { fileInput.click(); });
        }
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                addFiles(fileInput.files);
                fileInput.value = '';
            });
        }

        if (drop) {
            ['dragenter', 'dragover'].forEach(function (ev) {
                drop.addEventListener(ev, function (e) {
                    e.preventDefault();
                    drop.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (ev) {
                drop.addEventListener(ev, function (e) {
                    e.preventDefault();
                    drop.classList.remove('is-dragover');
                });
            });
            drop.addEventListener('drop', function (e) {
                if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files);
            });
        }

        dlg.addEventListener('paste', function (e) {
            if (!authed || !dlg.open) return;
            var items = e.clipboardData && e.clipboardData.items;
            if (!items) return;
            var pasted = [];
            for (var i = 0; i < items.length; i++) {
                if (items[i].type && items[i].type.indexOf('image/') === 0) {
                    var f = items[i].getAsFile();
                    if (f) pasted.push(f);
                }
            }
            if (pasted.length) {
                e.preventDefault();
                addFiles(pasted);
            }
        });

        var loginBtn = qs(root, '[data-pbr-login]');
        if (loginBtn) {
            loginBtn.addEventListener('click', function (e) {
                var portalLogin = document.getElementById('portal-login-dialog');
                if (portalLogin && typeof portalLogin.showModal === 'function') {
                    e.preventDefault();
                    closeDialog(dlg);
                    portalLogin.showModal();
                    var email = document.getElementById('portal-login-email');
                    if (email) setTimeout(function () { try { email.focus(); } catch (err) {} }, 0);
                    return;
                }
                if (!loginUrl) return;
                // allow default navigation
            });
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                setError('');
                setOk('');
                if (!authed || !storeUrl) {
                    setError('Нужна авторизация.');
                    return;
                }
                var scopeEl = form.querySelector('input[name="scope"]:checked');
                if (scopeEl && scopeEl.value === 'course') {
                    var cid = courseSelect ? String(courseSelect.value || '') : '';
                    if (!cid) {
                        setError('Выберите курс, к которому относится сообщение.');
                        return;
                    }
                }
                var fd = new FormData(form);
                files.forEach(function (item) {
                    fd.append('screenshots[]', item.file, item.file.name || 'screenshot.png');
                });

                form.classList.add('is-busy');
                fetch(storeUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: fd,
                    credentials: 'same-origin'
                }).then(function (res) {
                    return res.json().then(function (data) {
                        return { res: res, data: data || {} };
                    }).catch(function () {
                        return { res: res, data: {} };
                    });
                }).then(function (pack) {
                    form.classList.remove('is-busy');
                    if (pack.res.status === 401 || pack.data.auth_required) {
                        authed = false;
                        showGuestOrForm();
                        setError(pack.data.message || 'Войдите, чтобы отправить сообщение.');
                        return;
                    }
                    if (!pack.res.ok || !pack.data.ok) {
                        var msg = pack.data.message || 'Не удалось отправить.';
                        if (pack.data.errors) {
                            var first = Object.keys(pack.data.errors)[0];
                            if (first && pack.data.errors[first] && pack.data.errors[first][0]) {
                                msg = pack.data.errors[first][0];
                            }
                        }
                        setError(msg);
                        return;
                    }
                    setOk(pack.data.message || 'Отправлено.');
                    revokeAll(files);
                    files = [];
                    renderThumbs();
                    setTimeout(function () { closeDialog(dlg); }, 900);
                }).catch(function () {
                    form.classList.remove('is-busy');
                    setError('Сеть недоступна. Попробуйте ещё раз.');
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
