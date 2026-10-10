(function () {
    'use strict';
    const panel = document.getElementById('reportFollowups');
    if (!panel) return;
    document.addEventListener('sierra:report-reminders', function (event) {
        const data = event.detail;
        panel.hidden = !data.enabled;
        ['total', 'days', 'pending', 'escalated'].forEach(function (key) {
            panel.querySelector('[data-followup-' + key + ']').textContent = data[key];
        });
        const list = panel.querySelector('[data-followup-list]');
        list.replaceChildren();
        (data.reports || []).forEach(function (report) {
            const row = document.createElement('a');
            row.className = 'report-followups-row';
            row.href = report.url;
            const copy = document.createElement('span');
            const title = document.createElement('strong');
            title.textContent = report.title;
            const detail = document.createElement('small');
            detail.textContent = (report.barangay_name || 'San Isidro') + ' · ' + (report.status === 'pending' ? 'Pending review' : 'MENRO follow-up');
            copy.append(title, detail);
            const age = document.createElement('span');
            age.className = 'report-followups-age';
            age.textContent = report.age_days + 'd waiting ';
            const arrow = document.createElement('i');
            arrow.className = 'fas fa-arrow-right';
            arrow.setAttribute('aria-hidden', 'true');
            age.append(arrow);
            row.append(copy, age);
            list.append(row);
        });
        panel.querySelector('[data-followup-empty]').hidden = data.total > 0;
        panel.querySelector('[data-followup-more]').hidden = data.total <= 5;
    });
})();
