(function () {
    document.querySelectorAll('[data-admin-settings-menu]').forEach(function (root) {
        var trigger = root.querySelector('[data-admin-settings-trigger]');
        var panel = root.querySelector('[data-admin-settings-panel]');
        if (!trigger || !panel) {
            return;
        }

        function close() {
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            root.classList.remove('is-open');
        }

        function open() {
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            root.classList.add('is-open');
        }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            if (panel.hidden) {
                open();
            } else {
                close();
            }
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) {
                close();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                close();
            }
        });
    });
})();
