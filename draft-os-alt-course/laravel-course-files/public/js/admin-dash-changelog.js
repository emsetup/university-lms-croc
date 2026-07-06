(function () {
    'use strict';

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatChangelogHtml(s) {
        return escHtml(s)
            .replace(/«([^»]+)»/g, '<strong>«$1»</strong>')
            .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
            .replace(/`([^`]+)`/g, '<code>$1</code>');
    }

    var BODY_SEP = '\x1e';

    function parseChangelogItems(trigger) {
        var body = trigger.getAttribute('data-ap-changelog-body');
        if (body) {
            return body.split(BODY_SEP).map(function (s) {
                return s.trim();
            }).filter(Boolean);
        }
        return [];
    }

    var modal = null;
    var modalDate = null;
    var modalTag = null;
    var modalTitle = null;
    var modalBody = null;
    var modalDoc = null;
    var lastFocus = null;

    function getModal() {
        if (modal) {
            return modal;
        }
        modal = document.getElementById('ap-changelog-modal');
        if (!modal) {
            return null;
        }
        modalDate = document.getElementById('ap-changelog-modal-date');
        modalTag = document.getElementById('ap-changelog-modal-tag');
        modalTitle = document.getElementById('ap-changelog-modal-title');
        modalBody = document.getElementById('ap-changelog-modal-body');
        modalDoc = document.getElementById('ap-changelog-modal-doc');
        return modal;
    }

    function openModal(trigger) {
        var m = getModal();
        if (!m || !modalTitle || !modalBody) {
            return;
        }

        var dateLabel = trigger.getAttribute('data-ap-changelog-date') || '';
        var dateIso = trigger.querySelector('time')?.getAttribute('datetime') || '';
        var tag = trigger.getAttribute('data-ap-changelog-tag') || 'feature';
        var tagLabel = trigger.getAttribute('data-ap-changelog-tag-label') || '';
        var title = trigger.getAttribute('data-ap-changelog-title') || '';
        var items = parseChangelogItems(trigger);

        if (modalDate) {
            modalDate.textContent = dateLabel;
            if (dateIso) {
                modalDate.setAttribute('datetime', dateIso);
            }
        }
        if (modalTag) {
            modalTag.textContent = tagLabel;
            modalTag.className = 'ap-changelog-tag ap-changelog-modal__tag ap-changelog-tag--' + tag;
        }
        modalTitle.textContent = title;

        var html = '';
        if (items.length === 1) {
            html = '<p class="ap-changelog-modal__text">' + formatChangelogHtml(items[0]) + '</p>';
        } else if (items.length > 1) {
            html = '<ul class="ap-changelog-modal__list">';
            for (var i = 0; i < items.length; i++) {
                html += '<li>' + formatChangelogHtml(items[i]) + '</li>';
            }
            html += '</ul>';
        } else {
            html = '<p class="ap-muted">Текст обновления недоступен.</p>';
        }
        modalBody.innerHTML = html;

        var docUrl = trigger.getAttribute('data-ap-changelog-doc-url') || '';
        var docLabel = trigger.getAttribute('data-ap-changelog-doc-label') || 'Документация';
        if (modalDoc) {
            if (docUrl) {
                modalDoc.hidden = false;
                modalDoc.innerHTML =
                    '<a class="ap-changelog-modal__doc-link" href="' +
                    escHtml(docUrl) +
                    '">' +
                    escHtml(docLabel) +
                    '</a>';
            } else {
                modalDoc.hidden = true;
                modalDoc.innerHTML = '';
            }
        }

        lastFocus = document.activeElement;
        m.hidden = false;
        m.classList.add('is-open');
        m.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ap-modal-open');

        var closeBtn = m.querySelector('[data-ap-changelog-modal-close].ap-modal__close');
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function closeModal() {
        var m = getModal();
        if (!m) {
            return;
        }
        m.classList.remove('is-open');
        m.setAttribute('aria-hidden', 'true');
        m.hidden = true;
        document.body.classList.remove('ap-modal-open');
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
        lastFocus = null;
    }

    function initModalHandlers() {
        var m = getModal();
        if (!m) {
            return;
        }

        m.addEventListener('click', function (ev) {
            var close = ev.target.closest('[data-ap-changelog-modal-close]');
            if (close) {
                ev.preventDefault();
                closeModal();
            }
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && m.classList.contains('is-open')) {
                closeModal();
            }
        });
    }

    function initPanel(panel) {
        var storageKey = panel.getAttribute('data-ap-changelog-storage-key') || 'ap-dash-changelog-collapsed:0';
        var toggle = panel.querySelector('[data-ap-changelog-toggle]');
        var label = panel.querySelector('[data-ap-changelog-toggle-label]');
        var body = panel.querySelector('[data-ap-changelog-body]');
        if (!toggle || !body) {
            return;
        }

        function setCollapsed(collapsed) {
            panel.classList.toggle('is-collapsed', collapsed);
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (label) {
                label.textContent = collapsed ? 'Развернуть' : 'Свернуть';
            }
            try {
                localStorage.setItem(storageKey, collapsed ? '1' : '0');
            } catch (e) {
                /* ignore */
            }
        }

        var stored = null;
        try {
            stored = localStorage.getItem(storageKey);
        } catch (e) {
            stored = null;
        }
        if (stored === '1') {
            setCollapsed(true);
        }

        toggle.addEventListener('click', function (ev) {
            ev.stopPropagation();
            setCollapsed(!panel.classList.contains('is-collapsed'));
        });

        panel.addEventListener('click', function (ev) {
            var openBtn = ev.target.closest('[data-ap-changelog-open]');
            if (!openBtn) {
                return;
            }
            ev.preventDefault();
            openModal(openBtn);
        });
    }

    function boot() {
        initModalHandlers();
        var panels = document.querySelectorAll('[data-ap-changelog-panel]');
        for (var i = 0; i < panels.length; i++) {
            initPanel(panels[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
