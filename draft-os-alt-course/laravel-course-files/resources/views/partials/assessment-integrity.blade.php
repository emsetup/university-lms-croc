{{-- Ограничение копирования и съёмки экрана на стороне браузера (не абсолютная защита). Подключать рядом с блоком data-integrity-protect. --}}
@once
    @push('scripts')
        <script>
            (function () {
                function bind(el) {
                    if (!el || el.dataset.integrityBound === '1') {
                        return;
                    }
                    el.dataset.integrityBound = '1';
                    el.addEventListener('copy', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }, true);
                    el.addEventListener('cut', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }, true);
                    el.addEventListener('contextmenu', function (e) {
                        e.preventDefault();
                    }, true);
                    el.addEventListener('dragstart', function (e) {
                        // Сопоставление (match_drag): карточки нужно перетаскивать.
                        if (e.target && e.target.closest && e.target.closest('.js-match-drag-list, .module-exam-match__card')) {
                            return;
                        }
                        e.preventDefault();
                    }, true);
                }

                function inTerminalDock(el) {
                    return el && el.closest && el.closest('.terminal-dock-panel');
                }

                function keyBlock(e) {
                    var t = e.target;
                    if (!t || !t.closest || !t.closest('[data-integrity-protect]')) {
                        return;
                    }
                    if (inTerminalDock(t)) {
                        return;
                    }
                    if (e.ctrlKey || e.metaKey) {
                        var k = (e.key || '').toLowerCase();
                        if (k === 'c' || k === 'x' || k === 's' || k === 'p' || k === 'u') {
                            e.preventDefault();
                        }
                    }
                }

                function syncHidden() {
                    document.body.classList.toggle(
                        'integrity-tab-hidden',
                        document.visibilityState === 'hidden'
                    );
                }

                function init() {
                    document.querySelectorAll('[data-integrity-protect]').forEach(bind);
                    document.addEventListener('keydown', keyBlock, true);
                    document.addEventListener('visibilitychange', syncHidden);
                    syncHidden();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }
            })();
        </script>
    @endpush
@endonce
