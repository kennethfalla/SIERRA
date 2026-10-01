(function () {
    var overlay = document.getElementById('dashboardLoading');
    if (!overlay) return;
    var storageKey = 'sierraDashboardLoadingStartedAt';
    var minDashboardTime = 700;

    function rememberStart() {
        try {
            window.sessionStorage.setItem(storageKey, String(Date.now()));
        } catch (error) {}
    }

    function readStart() {
        try {
            return parseInt(window.sessionStorage.getItem(storageKey) || '', 10) || 0;
        } catch (error) {
            return 0;
        }
    }

    function clearStart() {
        try {
            window.sessionStorage.removeItem(storageKey);
        } catch (error) {}
    }

    function show() {
        overlay.classList.remove('is-leaving');
        overlay.hidden = false;
        rememberStart();
    }

    function hide() {
        if (overlay.hidden) return;
        overlay.classList.add('is-leaving');
        window.setTimeout(function () {
            overlay.hidden = true;
            clearStart();
        }, 200);
    }

    function hideAfterMinimum() {
        var startedAt = readStart() || Date.now();
        var elapsed = Date.now() - startedAt;
        var wait = Math.max(0, minDashboardTime - elapsed);
        window.setTimeout(hide, wait);
    }

    window.SierraDashboardLoading = { show: show, hide: hide };
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) hide();
    });
    if (document.body.classList.contains('dashboard-page')) {
        if (!readStart()) rememberStart();
        if (document.readyState === 'complete') hideAfterMinimum();
        else window.addEventListener('load', hideAfterMinimum, { once: true });
        window.setTimeout(hide, 5000);
    }
}());
