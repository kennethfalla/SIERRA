/* Chart.js failure stub.
   If Chart.js failed to load, provide inert no-op stubs so page scripts never
   die with "Chart is not defined". Charts simply render nothing. */
(function () {
    'use strict';
    if (typeof window.Chart !== 'undefined') {
        // Canvas labels do not inherit the page's CSS typography.
        if (window.Chart.defaults) {
            if (window.Chart.defaults.font) window.Chart.defaults.font.family = 'Manrope, sans-serif';
            window.Chart.defaults.color = document.documentElement.dataset.theme === 'dark' ? '#a3b9ac' : '#63746b';
        }
        return;
    }

    var inert = {
        destroy: function () {},
        update: function () {},
        resize: function () {},
        render: function () {}
    };

    function StubChart() {
        return inert;
    }
    StubChart.getChart = function () { return null; };
    StubChart.instances = {};

    window.Chart = StubChart;
})();
