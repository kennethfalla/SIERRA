(function () {
    'use strict';
    const panel = document.getElementById('drillPanel');
    const content = document.getElementById('drillContent');
    const backdrop = document.getElementById('mapReportBackdrop');
    const closeButton = document.getElementById('mapReportClose');
    if (!panel || !content || !backdrop || !closeButton) return;
    const cache = new Map();
    let request = null, selected = null, previousFocus = null;
    function element(tag, value, className) {
        const node = document.createElement(tag);
        if (value != null) node.textContent = value;
        if (className) node.className = className;
        return node;
    }
    function message(value) { content.replaceChildren(element('p', value, 'map-report-message')); }
    function level(report) {
        if (report.severity_score == null) return report.risk_level || 'low';
        const score = Number(report.severity_score);
        const bands = (window.SierraMapSettings || {}).severityBands || {yellow:5, orange:9, critical:15};
        return score < bands.yellow ? 'low' : score < bands.orange ? 'medium' : score < bands.critical ? 'high' : 'critical';
    }
    function section(title, value) {
        const box = element('section', null, 'map-report-section');
        box.append(element('h3', title));
        if (value) box.append(element('p', value));
        content.append(box);
        return box;
    }
    function media(title, paths) {
        const box = section(title), grid = element('div', null, 'map-report-photos');
        String(paths || '').split(',').filter(Boolean).slice(0, 3).forEach(path => {
            let url;
            try { url = new URL(path.trim(), panel.dataset.assetUrl); } catch (_) { return; }
            if (url.origin !== new URL(panel.dataset.detailUrl).origin) return;
            const video = /\.(mp4|webm|mov|m4v|avi)$/i.test(url.pathname);
            const item = element(video ? 'video' : 'img');
            item.src = url.href;
            if (video) { item.controls = true; item.preload = 'none'; item.playsInline = true; }
            else { item.alt = 'Report evidence'; item.loading = 'lazy'; }
            const link = element('a'); link.href = url.href; link.target = '_blank'; link.rel = 'noopener';
            if (video) grid.append(item);
            else { link.append(item); grid.append(link); }
        });
        if (grid.children.length) box.append(grid);
        else box.append(element('p', 'No evidence available.', 'map-report-muted'));
    }
    function render(report) {
        content.replaceChildren();
        const risk = level(report), labels = {low:'Low', medium:'Medium', high:'High', critical:'Critical'};
        const statuses = {pending:'Pending',under_review:'Under Review',verified:'Verified',in_progress:'In Progress',escalated_pending:'Escalated Pending',escalated:'Escalated',resolved:'Resolved',rejected:'Rejected',cancelled:'Cancelled'};
        content.append(element('h3', report.title || 'Report', 'map-report-title'));
        const badges = element('div', null, 'map-report-badges');
        badges.append(element('span', statuses[report.status] || report.status, 'status-badge status-' + report.status));
        badges.append(element('span', labels[risk] || 'Low', 'risk-badge risk-' + risk));
        content.append(badges);
        section('Category', report.category_name || 'Uncategorized');
        section('Description', report.description || 'No description available.');
        section('Location', report.location_address || [report.latitude, report.longitude].filter(value => value != null).join(', ') || 'No location available.');
        media('Photo Evidence', report.image_paths);
        if (report.status === 'resolved' || report.resolution_evidence_paths) media('Resolution Evidence', report.resolution_evidence_paths);
        const score = section('Severity Score', (Number(report.severity_score) || 0) + ' / 20 · ' + (labels[risk] || 'Low'));
        score.classList.add('map-report-score');
        if (report.decision_classification) score.append(element('p', report.decision_classification, 'map-report-muted'));
        const recommendations = {
            low:'Keep as is. Barangay can handle this with regular cleanup.',
            medium:'Barangay should act soon. MENRO should keep an eye on this.',
            high:'Send to MENRO. Clear the hazard now before it spreads or causes flooding.',
            critical:'Act now. Send MENRO crews and equipment to this location right away.'
        };
        const recommendation = section('Recommendation', recommendations[risk]);
        recommendation.classList.add('map-report-recommendation', 'map-report-recommendation-' + risk);
        const link = element('a', 'Open Full Report', 'map-report-open');
        link.href = panel.dataset.reportUrl + encodeURIComponent(report.token || report.id);
        content.append(link);
        const date = new Date(String(report.created_at || '').replace(' ', 'T'));
        if (!isNaN(date)) content.append(element('p', 'Reported: ' + date.toLocaleString(), 'map-report-muted'));
    }
    function close() {
        selected = null;
        if (request) request.abort();
        request = null;
        panel.classList.remove('open');
        panel.setAttribute('aria-hidden', 'true');
        panel.inert = true;
        backdrop.hidden = true;
        document.body.classList.remove('map-report-opened');
        document.documentElement.classList.remove('map-report-opened');
        if (previousFocus && previousFocus.isConnected) previousFocus.focus({preventScroll:true});
    }
    async function open(id) {
        const key = String(id);
        if (key === selected && request) return;
        if (!panel.classList.contains('open')) previousFocus = document.activeElement;
        selected = key;
        if (request) request.abort();
        request = null;
        panel.inert = false;
        panel.setAttribute('aria-hidden', 'false');
        panel.classList.add('open');
        backdrop.hidden = false;
        document.body.classList.add('map-report-opened');
        document.documentElement.classList.add('map-report-opened');
        requestAnimationFrame(() => {
            if (selected === key && panel.classList.contains('open')) closeButton.focus({preventScroll:true});
        });
        const saved = cache.get(key);
        if (saved && Date.now() - saved.at < 30000) { render(saved.report); return; }
        message('Loading report details…');
        const controller = new AbortController();
        request = controller;
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(panel.dataset.detailUrl + encodeURIComponent(key), {credentials:'same-origin',signal:controller.signal});
            if (!response.ok) throw new Error('Request failed');
            const report = await response.json();
            if (!report || report.error || !report.id) throw new Error('Report unavailable');
            if (selected !== key || request !== controller) return;
            if (cache.size >= 50) cache.delete(cache.keys().next().value);
            cache.set(key, {report,at:Date.now()});
            render(report);
        } catch (_) {
            if (selected === key && request === controller) message('Unable to load this report. Close the panel and select it again to retry.');
        } finally {
            clearTimeout(timeout);
            if (request === controller) request = null;
        }
    }
    closeButton.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    panel.addEventListener('transitionend', event => {
        if (event.propertyName === 'transform' && panel.classList.contains('open') && !panel.contains(document.activeElement)) closeButton.focus({preventScroll:true});
    });
    document.addEventListener('keydown', event => {
        if (!panel.classList.contains('open')) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key === 'Tab') {
            const controls = Array.from(panel.querySelectorAll('button,a[href],video[controls]')).filter(node => node.getClientRects().length);
            const first = controls[0], last = controls[controls.length - 1];
            if (!panel.contains(document.activeElement)) { event.preventDefault(); first.focus(); return; }
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    window.SierraMapReportPanel = {open,close};
})();
