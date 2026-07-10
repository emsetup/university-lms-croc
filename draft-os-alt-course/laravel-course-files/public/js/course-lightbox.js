(function () {
    'use strict';

    var lb = document.getElementById('course-lightbox');
    if (!lb) return;

    var imgEl = lb.querySelector('.course-lightbox__img');
    var capEl = lb.querySelector('.course-lightbox__caption');
    var lastFocus = null;

    function open(src, alt) {
        if (!src) return;
        lastFocus = document.activeElement;
        imgEl.src = src;
        imgEl.alt = alt || '';
        capEl.textContent = alt || '';
        requestAnimationFrame(function () { lb.classList.add('is-open'); });
        document.body.classList.add('course-lightbox-open');
        lb.setAttribute('aria-hidden', 'false');
        var closeBtn = lb.querySelector('.course-lightbox__close');
        if (closeBtn) closeBtn.focus();
    }

    function close() {
        lb.classList.remove('is-open');
        lb.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('course-lightbox-open');
        window.setTimeout(function () {
            if (!lb.classList.contains('is-open')) imgEl.removeAttribute('src');
        }, 220);
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-course-lightbox]');
        if (!btn) return;
        e.preventDefault();
        open(btn.getAttribute('data-course-lightbox-src'), btn.getAttribute('data-course-lightbox-alt'));
    });

    lb.querySelectorAll('[data-course-lightbox-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
        if (!lb.classList.contains('is-open')) return;
        if (e.key === 'Escape') { e.preventDefault(); close(); }
    });

    window.CourseLightbox = { open: open, close: close };
})();
