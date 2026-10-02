(function () {
    'use strict';
    var overlay = document.getElementById('dashboardLoading');
    if (!overlay) return;
    var delay = 650;
    var timer;
    var finished = false;
    function reveal() {
        if (finished) return;
        overlay.classList.remove('is-leaving');
        overlay.hidden = false;
    }
    function show() {
        finished = false;
        clearTimeout(timer);
        timer = window.setTimeout(reveal, delay);
    }
    function hide() {
        finished = true;
        clearTimeout(timer);
        overlay.hidden = true;
    }
    window.SierraDashboardLoading = { show: show, hide: hide };
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) hide();
    });
    // The server grants this only to the first authenticated page after login.
    if (overlay.dataset.initialLoad !== '1') return;
    timer = window.setTimeout(reveal, Math.max(0, delay - performance.now()));
    function rendered() {
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(hide);
        });
    }
    if (document.readyState === 'complete') rendered();
    else window.addEventListener('load', rendered, { once: true });
    // Failed external resources must never trap the workspace.
    window.setTimeout(hide, 12000);
}());
