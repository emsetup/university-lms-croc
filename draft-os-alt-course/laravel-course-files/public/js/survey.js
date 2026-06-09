(function () {
    var form = document.getElementById('survey-form');
    if (!form) return;

    var total = parseInt(form.getAttribute('data-total') || '0', 10);
    if (total < 1) return;

    var steps = form.querySelectorAll('.survey-step');
    var btnPrev = document.getElementById('survey-prev');
    var btnNext = document.getElementById('survey-next');
    var btnSubmit = document.getElementById('survey-submit');
    var fill = document.getElementById('survey-progress-fill');
    var counter = document.getElementById('survey-progress-counter');
    var arrowUp = document.getElementById('survey-arrow-up');
    var arrowDown = document.getElementById('survey-arrow-down');
    var hero = document.getElementById('survey-hero');
    var anonBanner = document.getElementById('survey-anon-banner');
    var cur = 0;
    var autoAdvanceTimer = null;

    function stepEl(idx) {
        return steps[idx] || null;
    }

    function answered(idx) {
        var step = stepEl(idx);
        if (!step) return false;

        var textarea = step.querySelector('textarea.js-survey-input');
        if (textarea && textarea.value.trim().length > 0) return true;

        var selects = step.querySelectorAll('.survey-match-select');
        if (selects.length) {
            var allFilled = true;
            selects.forEach(function (sel) {
                if (!sel.value) allFilled = false;
            });
            if (allFilled) return true;
        }

        var cbs = step.querySelectorAll('input[type="checkbox"]:checked');
        if (cbs.length) return true;

        var radio = step.querySelector('input[type="radio"]:checked');
        return !!radio;
    }

    function syncPlaceholder(wrap) {
        if (!wrap) return;
        var ta = wrap.querySelector('.survey-input-line');
        if (!ta) return;
        wrap.classList.toggle('has-value', ta.value.trim().length > 0);
    }

    function autoResizeTextarea(ta) {
        if (!ta) return;
        ta.style.height = 'auto';
        ta.style.height = Math.max(ta.scrollHeight, 32) + 'px';
    }

    function syncChrome() {
        if (fill) {
            fill.style.width = (100 * (cur + 1) / total) + '%';
        }
        if (counter) {
            counter.textContent = (cur + 1) + ' / ' + total;
        }
        if (hero) {
            hero.classList.toggle('is-hidden', cur > 0);
        }
        if (anonBanner) {
            anonBanner.classList.toggle('is-hidden', cur > 0);
        }
        if (btnPrev) btnPrev.disabled = cur === 0;
        if (arrowUp) arrowUp.disabled = cur === 0;
        if (arrowDown) arrowDown.disabled = cur >= total - 1;
    }

    function showStep(idx) {
        if (idx < 0 || idx >= total) return;
        cur = idx;
        steps.forEach(function (el, i) {
            el.hidden = i !== cur;
        });

        if (cur === total - 1) {
            if (btnNext) btnNext.hidden = true;
            if (btnSubmit) btnSubmit.hidden = false;
        } else {
            if (btnNext) btnNext.hidden = false;
            if (btnSubmit) btnSubmit.hidden = true;
        }

        syncChrome();

        var step = stepEl(cur);
        if (!step) return;
        var focusable = step.querySelector('textarea, select, input[type="radio"], input[type="checkbox"]');
        if (focusable && typeof focusable.focus === 'function') {
            try { focusable.focus({ preventScroll: true }); } catch (e) { focusable.focus(); }
        }
    }

    function validateCurrentStep() {
        if (answered(cur)) return true;
        var step = stepEl(cur);
        if (!step) return false;

        var textarea = step.querySelector('textarea.js-survey-input');
        if (textarea) {
            textarea.focus();
            return false;
        }

        var selects = step.querySelectorAll('.survey-match-select');
        if (selects.length) {
            for (var i = 0; i < selects.length; i++) {
                if (!selects[i].value) {
                    selects[i].focus();
                    return false;
                }
            }
            return false;
        }

        var firstInput = step.querySelector('input[type="radio"], input[type="checkbox"]');
        if (firstInput) firstInput.focus();
        return false;
    }

    function goNext() {
        if (!validateCurrentStep()) return;
        if (cur < total - 1) showStep(cur + 1);
    }

    function goPrev() {
        if (cur > 0) showStep(cur - 1);
    }

    function syncMatchOrders() {
        form.querySelectorAll('[id^="survey-order-"]').forEach(function (hid) {
            var q = hid.id.replace('survey-order-', '');
            var parts = [];
            form.querySelectorAll('.survey-match-select[data-q="' + q + '"]').forEach(function (sel) {
                parts.push(sel.value);
            });
            hid.value = parts.join(',');
        });
    }

    function firstUnanswered() {
        for (var i = 0; i < total; i++) {
            if (!answered(i)) return i;
        }
        return -1;
    }

    if (btnPrev) btnPrev.addEventListener('click', goPrev);
    if (btnNext) btnNext.addEventListener('click', goNext);
    if (arrowUp) arrowUp.addEventListener('click', goPrev);
    if (arrowDown) arrowDown.addEventListener('click', goNext);

    form.querySelectorAll('.survey-input-line-wrap').forEach(function (wrap) {
        var ta = wrap.querySelector('.survey-input-line');
        if (!ta) return;
        syncPlaceholder(wrap);
        autoResizeTextarea(ta);
        ta.addEventListener('input', function () {
            syncPlaceholder(wrap);
            autoResizeTextarea(ta);
        });
    });

    form.querySelectorAll('.js-survey-input').forEach(function (inp) {
        inp.addEventListener('change', function () {
            var step = inp.closest('.survey-step');
            if (!step) return;
            var idx = parseInt(step.getAttribute('data-step'), 10);
            if (isNaN(idx) || idx !== cur) return;

            if (inp.type === 'radio' && cur < total - 1) {
                if (autoAdvanceTimer) clearTimeout(autoAdvanceTimer);
                autoAdvanceTimer = setTimeout(function () {
                    if (answered(cur)) showStep(cur + 1);
                }, 300);
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (!form || form.hidden) return;
        var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
        var isTextarea = tag === 'textarea';

        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            if (cur === total - 1) {
                if (validateCurrentStep()) form.requestSubmit();
            } else {
                goNext();
            }
            return;
        }

        if (isTextarea && e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            goNext();
        }
    });

    form.addEventListener('submit', function (e) {
        syncMatchOrders();
        var miss = [];
        for (var i = 0; i < total; i++) {
            if (!answered(i)) miss.push(i + 1);
        }
        if (miss.length) {
            e.preventDefault();
            var first = firstUnanswered();
            if (first >= 0) showStep(first);
            window.alert('Ответьте на вопросы: ' + miss.join(', ') + '.');
        }
    });

    showStep(0);
})();
