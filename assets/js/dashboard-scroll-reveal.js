(function () {
    'use strict';
    if (!document.body.classList.contains('dashboard-page')) return;

    function initialize() {
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        var items = Array.from(document.querySelectorAll(
            '.dashboard-page .analytics-kpi-box, .dashboard-page .menro-stat-card, .dashboard-page .menro-dash-card, ' +
            '.dashboard-page .sierra-map-panel, .dashboard-page .chart-card, .dashboard-page .table-card, ' +
            '.dashboard-page .announce-carousel, .dashboard-page #map-container, .dashboard-page .report-card-item, ' +
            '.dashboard-page .mobile-report-card, .dashboard-page .mob-card, .dashboard-page .citizen-track-card'
        ));
        if (!items.length || reduceMotion.matches || !window.IntersectionObserver) {
            items.forEach(function (item) { item.classList.add('dash-reveal-visible'); });
            return;
        }
        // Nested cards inherit their parent's reveal.
        items = items.filter(function (item) {
            return !items.some(function (parent) { return parent !== item && parent.contains(item); });
        });
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('dash-reveal-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
        items.forEach(function (item, index) {
            item.classList.add('dash-reveal');
            item.style.setProperty('--dash-reveal-delay', (Math.min(index % 6, 5) * 45) + 'ms');
            observer.observe(item);
        });
    }

    // Let the login loading screen clear before starting visible animations.
    function ready() {
        var overlay = document.getElementById('dashboardLoading');
        if (overlay && !overlay.hidden) {
            var loadingObserver = new MutationObserver(function () {
                if (!overlay.hidden) return;
                loadingObserver.disconnect();
                window.requestAnimationFrame(initialize);
            });
            loadingObserver.observe(overlay, {attributes:true,attributeFilter:['hidden']});
        } else window.requestAnimationFrame(initialize);
    }
    if (document.readyState === 'complete') ready();
    else window.addEventListener('load', ready, {once:true});
}());
