(function () {
    'use strict';

    function init() {
        var modal = document.getElementById('ap-create-course-modal');
        if (!modal) {
            return;
        }

        var titleIn = document.getElementById('ap-create-title');
        var slugIn = document.getElementById('ap-create-slug');
        var slugTouched = false;

        var RU = {
            а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y',
            к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f',
            х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
        };

        function translitSlug(str) {
            var out = '';
            var lower = (str || '').toLowerCase();
            for (var i = 0; i < lower.length; i++) {
                var ch = lower.charAt(i);
                if (RU[ch] !== undefined) {
                    out += RU[ch];
                } else if (/[a-z0-9]/.test(ch)) {
                    out += ch;
                } else if (/\s/.test(ch) || ch === '-' || ch === '_' || ch === '.') {
                    out += '-';
                }
            }
            out = out.replace(/-+/g, '-').replace(/^-|-$/g, '');
            return out.slice(0, 80);
        }

        function openModal() {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('ap-modal-open');
            slugTouched = !!(slugIn && slugIn.value && slugIn.value.trim());
            var t = modal.querySelector('#ap-create-title');
            if (t) {
                setTimeout(function () {
                    t.focus();
                }, 10);
            }
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('ap-modal-open');
        }

        function resetForm() {
            if (titleIn) {
                titleIn.value = '';
            }
            if (slugIn) {
                slugIn.value = '';
            }
            slugTouched = false;
        }

        var openBtn = document.getElementById('ap-open-create-course');
        if (openBtn) {
            openBtn.addEventListener('click', function () {
                resetForm();
                openModal();
            });
        }

        modal.querySelectorAll('[data-ap-modal-close]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (el.classList.contains('ap-modal__backdrop')) {
                    e.preventDefault();
                }
                closeModal();
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });

        window.addEventListener('ap-open-create-course', function (ev) {
            var preserve = ev.detail && ev.detail.preserveForm;
            if (!preserve) {
                resetForm();
            } else {
                slugTouched = true;
            }
            openModal();
        });

        if (titleIn && slugIn) {
            titleIn.addEventListener('input', function () {
                if (!slugTouched) {
                    slugIn.value = translitSlug(titleIn.value);
                }
            });
            slugIn.addEventListener('input', function () {
                slugTouched = slugIn.value.trim().length > 0;
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
