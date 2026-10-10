/* Shared light appearance and viewport initialization. */
(function () {
    'use strict';
    if (window.SierraAppearanceReady) return;
    window.SierraAppearanceReady = true;
    // A phone starts at 80%; desktop/tablet viewport settings remain unchanged.
    // Account iframes inherit the parent's scale instead of scaling twice.
    if (!window.frameElement && window.screen && Math.min(window.screen.width, window.screen.height) < 768 && window.matchMedia('(pointer: coarse)').matches) {
        function setPhoneScale() {
            var viewport = document.querySelector('meta[name="viewport"]');
            if (!viewport) return false;
            var parts = (viewport.getAttribute('content') || 'width=device-width').split(',').filter(function (part) {
                return !/^\s*(initial-scale|minimum-scale|maximum-scale|user-scalable)\s*=/i.test(part);
            });
            parts.push('initial-scale=0.8', 'user-scalable=yes');
            viewport.setAttribute('content', parts.join(', '));
            return true;
        }
        if (!setPhoneScale() && window.MutationObserver) {
            var viewportObserver = new MutationObserver(function () {
                if (setPhoneScale()) viewportObserver.disconnect();
            });
            viewportObserver.observe(document.head || document.documentElement, { childList: true, subtree: true });
        }
    }
    try { localStorage.removeItem('sierra-theme'); localStorage.removeItem('sierra_landing_dark'); localStorage.removeItem('theme'); } catch (ignore) {}
    document.documentElement.dataset.theme = 'light';
    document.documentElement.style.colorScheme = 'light';
    if (window.frameElement && window.frameElement.hasAttribute('data-landing-auth')) {
        var page = new URL(window.location.href).searchParams.get('page');
        if (!['login','register','forgot-password','reset-password'].includes(page)) {
            window.top.location.replace(window.location.href);
            return;
        }
        document.documentElement.classList.add('auth-embedded');
    }
    function apply() {
        if (window.Chart && Chart.defaults) {
            if (Chart.defaults.font) Chart.defaults.font.family = 'Manrope, sans-serif';
            Chart.defaults.color = '#63746b';
            Chart.defaults.borderColor = '#e1e9e4';
            Object.values(Chart.instances || {}).forEach(function (chart) {
                var plugins = chart.options.plugins || {};
                if (plugins.legend && plugins.legend.labels) plugins.legend.labels.color = Chart.defaults.color;
                Object.values(chart.options.scales || {}).forEach(function (axis) {
                    if (axis.ticks) axis.ticks.color = Chart.defaults.color;
                    if (axis.grid) axis.grid.color = Chart.defaults.borderColor;
                });
                chart.update('none');
            });
        }
    }
    function init() {
        apply();
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(apply);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
