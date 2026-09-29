/* Global fetch timeout guard.
   Aborts requests that stall too long on weak/slow connections so buttons,
   spinners and toasts recover instead of hanging indefinitely. */
(function () {
    'use strict';
    if (window.__FETCH_TIMEOUT_INSTALLED || !window.fetch) return;
    window.__FETCH_TIMEOUT_INSTALLED = true;

    var TIMEOUT_MS = 45000;
    window.FETCH_TIMEOUT_MS = TIMEOUT_MS;

    var _fetch = window.fetch;

    window.fetch = function (input, init) {
        init = init || {};
        var userSignal = init.signal || null;
        var controller = new AbortController();
        var signal = controller.signal;

        if (userSignal) {
            if (userSignal.aborted) {
                controller.abort();
            } else {
                userSignal.addEventListener('abort', function () { controller.abort(); }, { once: true });
            }
        }

        var timer = setTimeout(function () { controller.abort(); }, TIMEOUT_MS);

        return _fetch.call(window, input, Object.assign({}, init, { signal: signal })).then(
            function (res) { clearTimeout(timer); return res; },
            function (err) { clearTimeout(timer); throw err; }
        );
    };
})();