(function () {
    var copyBtn = document.getElementById('ap-surveys-quick-link-copy');
    var urlInput = document.getElementById('ap-surveys-quick-link-url');
    if (copyBtn && urlInput) {
        copyBtn.addEventListener('click', function () {
            if (!urlInput.value) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(urlInput.value).catch(function () {
                    urlInput.select();
                    document.execCommand('copy');
                });
            } else {
                urlInput.select();
                document.execCommand('copy');
            }
        });
    }

    document.querySelectorAll('[data-ap-survey-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var key = btn.getAttribute('data-ap-survey-tab');
            if (!key) {
                return;
            }
            document.querySelectorAll('[data-ap-survey-tab]').forEach(function (b) {
                var on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            document.querySelectorAll('[data-ap-survey-pane]').forEach(function (pane) {
                var on = pane.getAttribute('data-ap-survey-pane') === key;
                pane.classList.toggle('is-active', on);
                if (on) {
                    pane.removeAttribute('hidden');
                } else {
                    pane.setAttribute('hidden', '');
                }
            });
        });
    });
})();
