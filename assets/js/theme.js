/* Shared appearance preference, applied before the page is painted. */
(function () {
    'use strict';
    if (window.SierraTheme) return;
    var key = 'sierra-theme';
    var media = window.matchMedia('(prefers-color-scheme: dark)');
    var preference = 'system';
    var printing = false;
    try { preference = localStorage.getItem(key) || 'system'; } catch (ignore) {}
    if (!['light', 'dark', 'system'].includes(preference)) preference = 'system';
    if (window.frameElement && window.frameElement.hasAttribute('data-landing-auth')) {
        var page = new URL(window.location.href).searchParams.get('page');
        if (!['login','register','forgot-password','reset-password'].includes(page)) {
            window.top.location.replace(window.location.href);
            return;
        }
        document.documentElement.classList.add('auth-embedded');
    }
    function apply() {
        var dark = !printing && (preference === 'dark' || (preference === 'system' && media.matches));
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.setAttribute('aria-pressed', String(dark));
            button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
            button.title = dark ? 'Switch to light mode' : 'Switch to dark mode';
            button.innerHTML = dark ? '<i class="fas fa-sun" aria-hidden="true"></i>' : '<i class="fas fa-moon" aria-hidden="true"></i>';
        });
        document.querySelectorAll('[data-theme-preference]').forEach(function (select) { select.value = preference; });
        if (window.Chart && Chart.defaults) {
            if (Chart.defaults.font) Chart.defaults.font.family = 'Manrope, sans-serif';
            Chart.defaults.color = dark ? '#a3b9ac' : '#63746b';
            Chart.defaults.borderColor = dark ? '#30493d' : '#e1e9e4';
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
        document.dispatchEvent(new CustomEvent('sierra:themechange', { detail: { theme: dark ? 'dark' : 'light', preference: preference } }));
    }
    function set(value) {
        if (!['light', 'dark', 'system'].includes(value)) return;
        preference = value;
        try { localStorage.setItem(key, value); } catch (ignore) {}
        apply();
    }
    window.SierraTheme = { set: set, get: function () { return preference; } };
    apply();
    var onSystemChange = function () { if (preference === 'system') apply(); };
    if (media.addEventListener) media.addEventListener('change', onSystemChange);
    else if (media.addListener) media.addListener(onSystemChange);
    window.addEventListener('storage', function (event) {
        if (event.key === key) { preference = ['light','dark','system'].includes(event.newValue) ? event.newValue : 'system'; apply(); }
    });
    window.addEventListener('beforeprint', function () { printing = true; apply(); });
    window.addEventListener('afterprint', function () { printing = false; apply(); });
    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-theme-toggle]')) set(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
    });
    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-theme-preference]')) set(event.target.value);
    });
    function init() {
        var actions = document.querySelector('.app-header-actions, .nav-actions');
        if (!actions && !document.querySelector('.app-mobile-header') && !document.querySelector('[data-theme-toggle]') && !window.frameElement) {
            var floating = document.createElement('button');
            floating.type = 'button'; floating.className = 'theme-toggle theme-toggle-floating'; floating.dataset.themeToggle = '';
            document.body.appendChild(floating);
        }
        apply();
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(apply);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
