(function () {
    'use strict';
    if (!document.body.classList.contains('dashboard-page')) return;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var items = Array.from(document.querySelectorAll(
        '.dashboard-page .analytics-kpi-box, .dashboard-page .chart-card, .dashboard-page .table-card, ' +
        '.dashboard-page .announce-carousel, .dashboard-page #map-container, .dashboard-page .report-card-item, ' +
        '.dashboard-page .mobile-report-card, .dashboard-page .mob-card, .dashboard-page .citizen-track-card'
    ));
    if (!items.length || reduceMotion.matches || !window.IntersectionObserver) {
        items.forEach(function (item) { item.classList.add('dash-reveal-visible'); });
        return;
    }
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
}());
