(function () {
    'use strict';

    var root = document.querySelector('.docs-prose');
    if (!root) {
        return;
    }

    var triggers = root.querySelectorAll('[data-docs-lightbox]');
    if (!triggers.length) {
        return;
    }

    var lb = document.getElementById('docs-lightbox');
    if (!lb) {
        lb = document.createElement('div');
        lb.id = 'docs-lightbox';
        lb.className = 'docs-lightbox';
        lb.setAttribute('hidden', '');
        lb.setAttribute('role', 'dialog');
        lb.setAttribute('aria-modal', 'true');
        lb.setAttribute('aria-label', 'Просмотр скриншота');
        lb.innerHTML =
            '<div class="docs-lightbox__backdrop" data-docs-lightbox-close tabindex="-1"></div>'
            + '<div class="docs-lightbox__panel">'
            + '<button type="button" class="docs-lightbox__close" data-docs-lightbox-close aria-label="Закрыть">×</button>'
            + '<img class="docs-lightbox__img" src="" alt="">'
            + '<p class="docs-lightbox__caption"></p>'
            + '</div>';
        document.body.appendChild(lb);
    }

    var imgEl = lb.querySelector('.docs-lightbox__img');
    var capEl = lb.querySelector('.docs-lightbox__caption');
    var lastFocus = null;

    function open(src, alt) {
        if (!src) {
            return;
        }
        lastFocus = document.activeElement;
        imgEl.src = src;
        imgEl.alt = alt || '';
        capEl.textContent = alt || '';
        requestAnimationFrame(function () {
            lb.classList.add('is-open');
        });
        document.body.classList.add('docs-lightbox-open');
        lb.setAttribute('aria-hidden', 'false');
        lb.querySelector('.docs-lightbox__close').focus();
    }

    function close() {
        lb.classList.remove('is-open');
        lb.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('docs-lightbox-open');
        window.setTimeout(function () {
            if (!lb.classList.contains('is-open')) {
                imgEl.removeAttribute('src');
            }
        }, 220);
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    triggers.forEach(function (btn) {
        btn.addEventListener('click', function () {
            open(btn.getAttribute('data-docs-lightbox-src'), btn.getAttribute('data-docs-lightbox-alt'));
        });
    });

    lb.querySelectorAll('[data-docs-lightbox-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
        if (!lb.classList.contains('is-open')) {
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
        }
    });
})();
