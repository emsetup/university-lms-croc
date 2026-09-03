{{-- Общий reorder для .js-match-drag-list: кнопки ↑/↓ + HTML5 drag + pointer (мышь/тач). --}}
<script>
(function () {
    function elFromEvent(t) {
        return t && t.nodeType === 3 ? t.parentElement : t;
    }

    function syncMatchOrderHidden(ul) {
        var wrap = ul.closest('.module-exam-match__right') || ul.parentElement;
        var hid = wrap ? wrap.querySelector('.js-match-order') : null;
        if (!hid) return;
        var ids = [];
        ul.querySelectorAll(':scope > li[data-desc-idx]').forEach(function (li) {
            ids.push(li.getAttribute('data-desc-idx'));
        });
        hid.value = ids.join(',');
        hid.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function moveCard(ul, li, dir) {
        if (!ul || !li) return;
        if (dir < 0) {
            var prev = li.previousElementSibling;
            if (prev) ul.insertBefore(li, prev);
        } else {
            var next = li.nextElementSibling;
            if (next) ul.insertBefore(next, li);
        }
        syncMatchOrderHidden(ul);
    }

    document.querySelectorAll('.js-match-drag-list').forEach(function (ul) {
        if (ul.dataset.matchBound === '1') return;
        ul.dataset.matchBound = '1';

        ul.addEventListener('click', function (e) {
            var t = elFromEvent(e.target);
            if (!t || !t.closest) return;
            var btn = t.closest('[data-match-move]');
            if (!btn || !ul.contains(btn)) return;
            e.preventDefault();
            var li = btn.closest('li[data-desc-idx]');
            if (!li) return;
            moveCard(ul, li, btn.getAttribute('data-match-move') === 'up' ? -1 : 1);
        });

        var dragEl = null;
        ul.addEventListener('dragstart', function (e) {
            var t = elFromEvent(e.target);
            if (!t || !t.closest) return;
            if (t.closest('[data-match-move]')) {
                e.preventDefault();
                return;
            }
            var li = t.closest('li[data-desc-idx]');
            if (!li || !ul.contains(li)) return;
            dragEl = li;
            li.classList.add('module-exam-match__card--drag');
            try { e.dataTransfer.setData('text/plain', li.getAttribute('data-desc-idx') || ''); } catch (err) {}
            e.dataTransfer.effectAllowed = 'move';
        });
        ul.addEventListener('dragend', function () {
            if (dragEl) dragEl.classList.remove('module-exam-match__card--drag');
            dragEl = null;
        });
        ul.addEventListener('dragover', function (e) {
            e.preventDefault();
            try { e.dataTransfer.dropEffect = 'move'; } catch (err) {}
        });
        ul.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!dragEl) return;
            var t = elFromEvent(e.target);
            var target = t && t.closest ? t.closest('li[data-desc-idx]') : null;
            if (!target || !ul.contains(target) || target === dragEl) return;
            var rect = target.getBoundingClientRect();
            var before = e.clientY < rect.top + rect.height / 2;
            ul.insertBefore(dragEl, before ? target : target.nextSibling);
            syncMatchOrderHidden(ul);
        });

        // Pointer fallback (тачпад/тач, когда HTML5 DnD не срабатывает)
        var ptrLi = null;
        var ptrY0 = 0;
        var ptrMoved = false;
        ul.addEventListener('pointerdown', function (e) {
            if (e.button != null && e.button !== 0) return;
            var t = elFromEvent(e.target);
            if (!t || !t.closest) return;
            if (t.closest('[data-match-move]')) return;
            var li = t.closest('li[data-desc-idx]');
            if (!li || !ul.contains(li)) return;
            // На устройствах с нормальным drag оставляем HTML5; pointer — запасной для тача
            if (e.pointerType === 'mouse' && typeof li.draggable === 'boolean') return;
            ptrLi = li;
            ptrY0 = e.clientY;
            ptrMoved = false;
            try { li.setPointerCapture(e.pointerId); } catch (err) {}
        });
        ul.addEventListener('pointermove', function (e) {
            if (!ptrLi) return;
            var dy = e.clientY - ptrY0;
            if (Math.abs(dy) < 18) return;
            ptrMoved = true;
            var over = document.elementFromPoint(e.clientX, e.clientY);
            var target = over && over.closest ? over.closest('li[data-desc-idx]') : null;
            if (!target || !ul.contains(target) || target === ptrLi) return;
            var rect = target.getBoundingClientRect();
            var before = e.clientY < rect.top + rect.height / 2;
            ul.insertBefore(ptrLi, before ? target : target.nextSibling);
            ptrY0 = e.clientY;
            syncMatchOrderHidden(ul);
        });
        function endPtr() {
            if (ptrLi) ptrLi.classList.remove('module-exam-match__card--drag');
            ptrLi = null;
            ptrMoved = false;
        }
        ul.addEventListener('pointerup', endPtr);
        ul.addEventListener('pointercancel', endPtr);
    });
})();
</script>
