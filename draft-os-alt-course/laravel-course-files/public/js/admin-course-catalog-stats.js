(function () {
    'use strict';

    function applyAvgProgress(cards, data) {
        cards.forEach(function (card) {
            var id = card.getAttribute('data-course-id');
            if (!id) {
                return;
            }

            var stat = data[id] || data[String(id)];
            var pct = stat && stat.avg_progress_pct !== undefined
                ? parseInt(String(stat.avg_progress_pct), 10)
                : 0;
            if (isNaN(pct)) {
                pct = 0;
            }
            pct = Math.max(0, Math.min(100, pct));

            var label = card.querySelector('.js-ap-catalog-avg-label');
            var bar = card.querySelector('.ap-catalog-card__progress');
            var barInner = card.querySelector('.ap-mini-progress__bar');

            if (label) {
                label.textContent = String(pct);
            }
            if (bar) {
                bar.classList.remove('is-loading');
                bar.setAttribute('aria-valuenow', String(pct));
            }
            if (barInner) {
                barInner.style.width = pct + '%';
            }
        });
    }

    function markAvgProgressFailed(cards) {
        cards.forEach(function (card) {
            var label = card.querySelector('.js-ap-catalog-avg-label');
            var bar = card.querySelector('.ap-catalog-card__progress');
            var barInner = card.querySelector('.ap-mini-progress__bar');

            if (label) {
                label.textContent = '—';
            }
            if (bar) {
                bar.classList.remove('is-loading');
                bar.setAttribute('aria-valuenow', '0');
            }
            if (barInner) {
                barInner.style.width = '0';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var grid = document.getElementById('ap-course-catalog-grid');
        if (!grid) {
            return;
        }

        var statsUrl = grid.getAttribute('data-ap-stats-url');
        if (!statsUrl) {
            return;
        }

        var cards = grid.querySelectorAll('.ap-catalog-card[data-course-id]');
        if (!cards.length) {
            return;
        }

        var ids = [];
        cards.forEach(function (card) {
            ids.push(card.getAttribute('data-course-id'));
        });

        var url = statsUrl + (statsUrl.indexOf('?') === -1 ? '?' : '&') + 'ids=' + encodeURIComponent(ids.join(','));

        fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('stats request failed');
                }
                return response.json();
            })
            .then(function (data) {
                applyAvgProgress(cards, data || {});
            })
            .catch(function () {
                markAvgProgressFailed(cards);
            });
    });
})();
