(function () {
    'use strict';

    var cfg = document.getElementById('portal-incident-reporter');
    if (!cfg) {
        return;
    }
    var endpoint = cfg.getAttribute('data-endpoint') || '';
    var token = cfg.getAttribute('data-csrf') || '';
    if (!endpoint || !token) {
        return;
    }

    var sent = {};

    function send(payload) {
        var key = (payload.message || '') + '|' + (payload.url || '');
        if (sent[key]) {
            return;
        }
        sent[key] = true;
        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify(payload),
        }).catch(function () {});
    }

    window.addEventListener('error', function (ev) {
        send({
            message: ev.message || 'Script error',
            filename: ev.filename || '',
            line: ev.lineno || 0,
            column: ev.colno || 0,
            stack: ev.error && ev.error.stack ? String(ev.error.stack) : '',
            url: window.location.href,
        });
    });

    window.addEventListener('unhandledrejection', function (ev) {
        var reason = ev.reason;
        var message = reason && reason.message ? String(reason.message) : String(reason || 'Unhandled rejection');
        var stack = reason && reason.stack ? String(reason.stack) : '';
        send({
            message: message,
            stack: stack,
            url: window.location.href,
        });
    });
})();
