(function () {
    'use strict';
    window.SierraCharts = {
        create: function (id, config) {
            const canvas = document.getElementById(id);
            if (!canvas) return null;
            try {
                if (!window.Chart) throw new Error('Chart library unavailable');
                Chart.defaults.font.family = 'Manrope, sans-serif';
                const previous = Chart.getChart(canvas);
                if (previous) previous.destroy();
                return new Chart(canvas, config);
            } catch (error) {
                console.error('Unable to render ' + id, error);
                const notice = document.createElement('p');
                notice.className = 'chart-unavailable';
                notice.textContent = 'Chart unavailable. Please reload to try again.';
                canvas.hidden = true;
                canvas.after(notice);
                return null;
            }
        }
    };
}());
