(function (global) {
    'use strict';

    var pickerModal = null;
    var pageRoot = null;
    var state = {
        courseId: null,
        scope: 'mine',
        onInsert: null,
        selected: null,
        insertOpts: { alt: '', size: 'full', align: 'center' },
        apiUrl: '',
        uploadUrl: '',
        pinUrlTpl: '',
        csrf: ''
    };

    var SIZE_WIDTH = { full: null, large: '75', medium: '50', small: '33', thumb: '150px' };

    function defaultAltFromFilename(name) {
        var base = String(name || 'image').replace(/\.[^.]+$/, '');
        return base.replace(/[_-]+/g, ' ').trim() || 'image';
    }

    function publicMediaUrl(item) {
        if (item && item.public_url) return item.public_url;
        if (item && item.uuid) return '/media/' + item.uuid;
        return '';
    }

    function buildInsertMarkdown(item, opts) {
        if (!item) return '';
        opts = opts || state.insertOpts || {};
        var alt = String(opts.alt || '').trim();
        if (!alt) alt = defaultAltFromFilename(item.original_filename);
        alt = alt.replace(/[\[\]]/g, '');
        var parts = [];
        var size = opts.size || 'full';
        var align = opts.align || 'none';
        if (size !== 'full' && SIZE_WIDTH[size]) {
            parts.push('w=' + SIZE_WIDTH[size]);
        }
        if (align && align !== 'none') {
            parts.push('align=' + align);
        }
        var title = parts.length ? (' "' + parts.join(';') + '"') : '';
        return '![' + alt + '](' + publicMediaUrl(item) + title + ')';
    }

    function defaultInsertOpts(item) {
        return {
            alt: defaultAltFromFilename(item && item.original_filename),
            size: 'full',
            align: 'center'
        };
    }

    function qs(sel, root) { return (root || document).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function readConfig(shell) {
        if (!shell) return;
        state.apiUrl = shell.getAttribute('data-ap-media-api') || state.apiUrl;
        state.uploadUrl = shell.getAttribute('data-ap-media-upload') || state.uploadUrl;
        state.pinUrlTpl = shell.getAttribute('data-ap-media-pin-tpl') || state.pinUrlTpl;
        state.csrf = shell.getAttribute('data-ap-media-csrf') || state.csrf;
    }

    function allowDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        if (e.dataTransfer) {
            e.dataTransfer.dropEffect = 'copy';
        }
    }

  /** @param {HTMLElement} zone */
    function bindDropZone(zone, fileInput, onFiles) {
        if (!zone || zone._apMediaDropBound) return;
        zone._apMediaDropBound = true;

        var dragDepth = 0;

        zone.addEventListener('dragenter', function (e) {
            allowDrop(e);
            dragDepth += 1;
            zone.classList.add('is-dragover');
        });

        zone.addEventListener('dragover', function (e) {
            allowDrop(e);
            zone.classList.add('is-dragover');
        });

        zone.addEventListener('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dragDepth -= 1;
            if (dragDepth <= 0) {
                dragDepth = 0;
                zone.classList.remove('is-dragover');
            }
        });

        zone.addEventListener('drop', function (e) {
            allowDrop(e);
            dragDepth = 0;
            zone.classList.remove('is-dragover');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                onFiles(e.dataTransfer.files);
            }
        });

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files.length) {
                    onFiles(fileInput.files);
                }
                fileInput.value = '';
            });
        }
    }

    function ensurePickerModal() {
        if (pickerModal && document.body.contains(pickerModal)) {
            return pickerModal;
        }
        pickerModal = document.getElementById('ap-media-lib-modal');
        if (!pickerModal) return null;

        readConfig(pickerModal);

        if (!pickerModal._apMediaUiBound) {
            pickerModal._apMediaUiBound = true;

            var closeBtn = qs('[data-ap-media-close]', pickerModal);
            var cancelBtn = qs('[data-ap-media-cancel]', pickerModal);
            var insertBtn = qs('[data-ap-media-insert]', pickerModal);
            if (closeBtn) closeBtn.addEventListener('click', close);
            if (cancelBtn) cancelBtn.addEventListener('click', close);
            if (insertBtn) {
                insertBtn.addEventListener('click', function () {
                    if (state.selected && state.onInsert) {
                        state.onInsert(buildInsertMarkdown(state.selected, state.insertOpts));
                    }
                    close();
                });
            }

            bindDetailsPanel(pickerModal);

            qsa('[data-ap-media-tab]', pickerModal).forEach(function (tab) {
                tab.addEventListener('click', function () {
                    state.scope = tab.getAttribute('data-ap-media-tab');
                    qsa('[data-ap-media-tab]', pickerModal).forEach(function (t) {
                        t.classList.toggle('is-active', t === tab);
                    });
                    loadGrid();
                });
            });
        }

        var drop = qs('[data-ap-media-drop]', pickerModal);
        var fileInput = qs('[data-ap-media-file]', pickerModal);
        var panel = qs('.ap-media-lib-panel', pickerModal);
        bindDropZone(panel || drop, fileInput, function (files) { uploadFiles(files); });
        if (drop && fileInput) {
            drop.addEventListener('click', function () { fileInput.click(); });
        }

        return pickerModal;
    }

    function bindDetailsPanel(modal) {
        if (!modal || modal._apMediaDetailsBound) return;
        modal._apMediaDetailsBound = true;

        var altInput = qs('[data-ap-media-opt-alt]', modal);
        if (altInput) {
            altInput.addEventListener('input', function () {
                state.insertOpts.alt = altInput.value;
                updateDetailsPreview();
                updateInsertButton();
            });
        }

        qsa('[data-ap-media-size]', modal).forEach(function (btn) {
            btn.addEventListener('click', function () {
                state.insertOpts.size = btn.getAttribute('data-ap-media-size') || 'full';
                qsa('[data-ap-media-size]', modal).forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                updateDetailsPreview();
            });
        });

        qsa('[data-ap-media-align]', modal).forEach(function (btn) {
            btn.addEventListener('click', function () {
                state.insertOpts.align = btn.getAttribute('data-ap-media-align') || 'none';
                qsa('[data-ap-media-align]', modal).forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                updateDetailsPreview();
            });
        });
    }

    function updateDetailsPanel() {
        if (!pickerModal) return;
        var details = qs('[data-ap-media-details]', pickerModal);
        var item = state.selected;
        if (!details) return;

        if (!item) {
            details.hidden = true;
            return;
        }

        details.hidden = false;
        var altInput = qs('[data-ap-media-opt-alt]', pickerModal);
        if (altInput) altInput.value = state.insertOpts.alt || '';

        qsa('[data-ap-media-size]', pickerModal).forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-ap-media-size') === state.insertOpts.size);
        });
        qsa('[data-ap-media-align]', pickerModal).forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-ap-media-align') === state.insertOpts.align);
        });

        var dims = qs('[data-ap-media-dims]', pickerModal);
        if (dims) {
            var w = parseInt(item.width, 10) || 0;
            var h = parseInt(item.height, 10) || 0;
            dims.textContent = w > 0 && h > 0 ? (w + ' × ' + h + ' px') : '';
        }

        updateDetailsPreview();
    }

    function updateDetailsPreview() {
        if (!pickerModal || !state.selected) return;
        var wrap = qs('[data-ap-media-preview]', pickerModal);
        var img = qs('[data-ap-media-preview-img]', pickerModal);
        if (!wrap || !img) return;

        img.src = state.selected.url || state.selected.thumb_url || '';
        img.alt = state.insertOpts.alt || '';

        wrap.className = 'ap-media-lib-details__preview';
        wrap.classList.add('ap-media-lib-details__preview--size-' + (state.insertOpts.size || 'full'));
        var align = state.insertOpts.align || 'none';
        if (align !== 'none') {
            wrap.classList.add('ap-media-lib-details__preview--align-' + align);
        }
    }

    function hideDetailsPanel() {
        if (!pickerModal) return;
        var details = qs('[data-ap-media-details]', pickerModal);
        if (details) details.hidden = true;
    }

    function open(opts) {
        var modal = ensurePickerModal();
        if (!modal) return;

        state.courseId = opts && opts.courseId ? parseInt(opts.courseId, 10) : null;
        state.onInsert = opts && opts.onInsert ? opts.onInsert : null;
        state.selected = null;
        state.insertOpts = defaultInsertOpts(null);
        state.scope = 'mine';

        hideDetailsPanel();

        var courseTab = qs('[data-ap-media-tab="course"]', modal);
        if (courseTab) courseTab.hidden = !state.courseId;
        qsa('[data-ap-media-tab]', modal).forEach(function (t) {
            var active = t.getAttribute('data-ap-media-tab') === state.scope;
            t.classList.toggle('is-active', active);
        });

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ap-media-lib-open');
        updateInsertButton();
        loadGrid();
    }

    function close() {
        if (!pickerModal) return;
        pickerModal.classList.remove('is-open');
        pickerModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ap-media-lib-open');
        qsa('.is-dragover', pickerModal).forEach(function (el) {
            el.classList.remove('is-dragover');
        });
        state.onInsert = null;
        state.selected = null;
        state.insertOpts = defaultInsertOpts(null);
        hideDetailsPanel();
    }

    function loadGrid() {
        var modal = pickerModal;
        var grid = modal ? qs('[data-ap-media-grid]', modal) : null;
        if (!grid || !state.apiUrl) return;
        grid.innerHTML = '<p class="ap-muted small">Загрузка…</p>';

        var url = state.apiUrl + '?scope=' + encodeURIComponent(state.scope) + '&per_page=48';
        if (state.courseId && state.scope === 'course') {
            url += '&course_id=' + state.courseId;
        }

        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) throw new Error(data.message || 'Ошибка');
                renderGrid(data.items || []);
            })
            .catch(function (err) {
                grid.innerHTML = '<p class="ap-muted small">' + (err.message || 'Не удалось загрузить') + '</p>';
            });
    }

    function updateInsertButton() {
        var btn = pickerModal ? qs('[data-ap-media-insert]', pickerModal) : null;
        var hint = pickerModal ? qs('#ap-media-lib-hint', pickerModal) : null;
        if (!btn) return;
        var has = !!state.selected;
        btn.disabled = !has;
        if (hint) {
            hint.textContent = has
                ? ('Вставка: ' + (state.insertOpts.alt || state.selected.original_filename || 'изображение'))
                : 'Выберите картинку в сетке слева';
        }
    }

    function selectAsset(item, cardEl) {
        state.selected = item;
        state.insertOpts = defaultInsertOpts(item);
        var grid = pickerModal ? qs('[data-ap-media-grid]', pickerModal) : null;
        if (grid) {
            qsa('.ap-media-lib-card', grid).forEach(function (c) { c.classList.remove('is-selected'); });
        }
        if (cardEl) cardEl.classList.add('is-selected');
        updateDetailsPanel();
        updateInsertButton();
    }

    function createGridCard(item, grid) {
        var card = document.createElement('button');
        card.type = 'button';
        card.className = 'ap-media-lib-card';
        card.dataset.uuid = item.uuid || '';
        card.innerHTML =
            '<img src="' + esc(item.thumb_url || item.url) + '" alt="" loading="lazy" draggable="false">'
            + '<span class="ap-media-lib-card__name">' + esc(item.original_filename || '') + '</span>';
        card.addEventListener('click', function () { selectAsset(item, card); });
        card.addEventListener('dblclick', function () {
            selectAsset(item, card);
            if (state.onInsert) {
                state.onInsert(buildInsertMarkdown(item, state.insertOpts));
            }
            close();
        });
        if (state.selected && state.selected.uuid === item.uuid) {
            card.classList.add('is-selected');
        }
        grid.appendChild(card);
        return card;
    }

    function prependAsset(item) {
        var grid = pickerModal ? qs('[data-ap-media-grid]', pickerModal) : null;
        if (!grid) return;
        var empty = grid.querySelector('.ap-muted');
        if (empty && !grid.querySelector('.ap-media-lib-card')) {
            grid.innerHTML = '';
        }
        var existing = item.uuid ? grid.querySelector('[data-uuid="' + item.uuid + '"]') : null;
        if (existing) {
            selectAsset(item, existing);
            return;
        }
        var card = createGridCard(item, grid);
        grid.insertBefore(card, grid.firstChild);
        selectAsset(item, card);
    }

    function renderGrid(items) {
        var grid = pickerModal ? qs('[data-ap-media-grid]', pickerModal) : null;
        if (!grid) return;
        grid.innerHTML = '';
        if (!items.length) {
            grid.innerHTML = '<p class="ap-muted small ap-media-lib-empty">Пока нет картинок. Перетащите файлы в зону выше или нажмите на неё.</p>';
            updateInsertButton();
            return;
        }

        items.forEach(function (item) {
            createGridCard(item, grid);
        });
        updateInsertButton();
    }

    function uploadFiles(fileList) {
        var uploadsBox = null;
        if (pickerModal && document.body.contains(pickerModal)) {
            uploadsBox = qs('[data-ap-media-uploads]', pickerModal);
        }
        if (!uploadsBox && pageRoot) {
            uploadsBox = qs('[data-ap-media-uploads]', pageRoot);
        }
        if (!fileList || !fileList.length) return;

        var queued = 0;
        Array.prototype.forEach.call(fileList, function (file) {
            var isImage = file.type && file.type.indexOf('image/') === 0;
            if (!isImage && (!file.name || !/\.(jpe?g|png|gif|webp)$/i.test(file.name))) {
                return;
            }
            queued += 1;

            var row = document.createElement('div');
            row.className = 'ap-media-lib-upload-row';
            row.innerHTML =
                '<span class="ap-media-lib-upload-thumb" hidden></span>'
                + '<span class="ap-media-lib-upload-name"></span>'
                + '<div class="ap-media-lib-upload-bar"><span></span></div>'
                + '<span class="ap-media-lib-upload-pct">…</span>';
            if (uploadsBox) uploadsBox.appendChild(row);
            var thumbEl = qs('.ap-media-lib-upload-thumb', row);
            var nameEl = qs('.ap-media-lib-upload-name', row);
            var barEl = qs('.ap-media-lib-upload-bar > span', row);
            var pctEl = qs('.ap-media-lib-upload-pct', row);
            if (nameEl) nameEl.textContent = file.name;

            var previewUrl = null;
            try {
                previewUrl = URL.createObjectURL(file);
                if (thumbEl) {
                    thumbEl.hidden = false;
                    thumbEl.innerHTML = '<img src="' + previewUrl + '" alt="">';
                }
            } catch (e) { /* ignore */ }

            var fd = new FormData();
            fd.append('file', file);
            if (state.courseId) fd.append('course_id', String(state.courseId));

            var xhr = new XMLHttpRequest();
            xhr.open('POST', state.uploadUrl);
            xhr.setRequestHeader('X-CSRF-TOKEN', state.csrf);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.addEventListener('progress', function (e) {
                if (!e.lengthComputable) return;
                var pct = Math.round((e.loaded / e.total) * 100);
                if (barEl) barEl.style.width = pct + '%';
                if (pctEl) pctEl.textContent = pct < 100 ? pct + '%' : '…';
            });
            xhr.addEventListener('load', function () {
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                var data = {};
                try {
                    data = JSON.parse(xhr.responseText || '{}');
                } catch (e) {
                    data = { ok: false, message: 'Неверный ответ сервера' };
                }
                if (xhr.status >= 200 && xhr.status < 300 && data.ok && data.asset) {
                    row.classList.add('is-ok');
                    if (pctEl) pctEl.textContent = '✓';
                    if (thumbEl && data.asset.thumb_url) {
                        thumbEl.innerHTML = '<img src="' + esc(data.asset.thumb_url) + '" alt="">';
                        thumbEl.hidden = false;
                    }
                    prependAsset(data.asset);
                    window.setTimeout(function () { row.remove(); }, 2500);
                    return;
                }
                row.classList.add('is-err');
                if (pctEl) pctEl.textContent = '✗';
                var msg = data.message || ('Ошибка ' + xhr.status);
                if (nameEl) {
                    nameEl.textContent = file.name + ' — ' + msg;
                    nameEl.title = msg;
                }
            });
            xhr.addEventListener('error', function () {
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                row.classList.add('is-err');
                if (pctEl) pctEl.textContent = '✗';
                if (nameEl) nameEl.textContent = file.name + ' — сеть';
            });
            xhr.send(fd);
        });

        if (!queued && uploadsBox) {
            var hint = document.createElement('p');
            hint.className = 'ap-muted small';
            hint.style.margin = '0 1rem';
            hint.textContent = 'Перетащите файл изображения (JPEG, PNG, GIF, WebP) с компьютера.';
            uploadsBox.appendChild(hint);
            window.setTimeout(function () { hint.remove(); }, 3500);
        }
    }

    function insertAtCursor(textarea, text) {
        if (!textarea) return;
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var val = textarea.value || '';
        var before = val.slice(0, start);
        var after = val.slice(end);
        var needsNlBefore = before.length > 0 && !before.endsWith('\n');
        var needsNlAfter = after.length > 0 && !after.startsWith('\n');
        var snippet = (needsNlBefore ? '\n' : '') + text + (needsNlAfter ? '\n' : '');
        textarea.value = before + snippet + after;
        var pos = before.length + snippet.length;
        textarea.selectionStart = textarea.selectionEnd = pos;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.focus();
    }

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function initPage(root) {
        if (!root) return;
        pageRoot = root;
        readConfig(root);
        state.courseId = parseInt(root.getAttribute('data-ap-media-course-id') || '0', 10) || null;

        var drop = qs('[data-ap-media-drop]', root);
        var fileInput = qs('[data-ap-media-file]', root);
        var grid = qs('[data-ap-media-grid]', root);

        function pageLoad() {
            fetch(state.apiUrl + '?scope=mine&per_page=60', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!grid) return;
                    grid.innerHTML = '';
                    (data.items || []).forEach(function (item) {
                        var card = document.createElement('div');
                        card.className = 'ap-media-lib-card';
                        card.style.cursor = 'pointer';
                        card.innerHTML =
                            '<img src="' + esc(item.thumb_url || item.url) + '" alt="" loading="lazy" draggable="false">'
                            + '<span class="ap-media-lib-card__name">' + esc(item.original_filename) + '</span>';
                        card.addEventListener('click', function () {
                            if (global.CourseLightbox) global.CourseLightbox.open(item.url, item.original_filename);
                        });
                        grid.appendChild(card);
                    });
                });
            }

        bindDropZone(drop || root, fileInput, function (files) {
            uploadFiles(files);
            window.setTimeout(pageLoad, 1500);
        });
        if (drop && fileInput) {
            drop.addEventListener('click', function () { fileInput.click(); });
        }

        pageLoad();
    }

    global.MediaLibrary = {
        open: open,
        close: close,
        insertAtCursor: insertAtCursor,
        buildInsertMarkdown: buildInsertMarkdown,
        initPage: initPage
    };

    document.addEventListener('DOMContentLoaded', function () {
        var page = document.getElementById('ap-media-page');
        if (page) initPage(page);
    });
})(window);
