(function () {
    'use strict';
    const data = window.sierraHazardMapData;
    const node = document.getElementById('sierraHazardMap');
    if (!data || !node || !window.L) return;
    const settings = data.defaults || {};
    const latitude = Number(settings.default_lat) || 15.3092;
    const longitude = Number(settings.default_lng) || 120.9033;
    const zoom = Number(settings.default_zoom) || 12;
    const map = L.map(node, { scrollWheelZoom:true, minZoom:3, maxZoom:20 }).setView([latitude, longitude], zoom);
    if (window.MapLayers && MapLayers.addControl) MapLayers.addControl(map, { position: 'bottomright' });
    else L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(map);
    if (data.boundary && data.boundary.features) {
        const boundary = MapLayers.addBoundary(map, data.boundary, {interactive:false});
        if (boundary.getBounds().isValid()) map.fitBounds(boundary.getBounds(), { padding:[24,24], maxZoom:15 });
    }
    if (data.barangayBoundaries && data.barangayBoundaries.features && !data.focusBoundary) {
        MapLayers.addBoundary(map, data.barangayBoundaries, {onEachFeature:function(feature, polygon) {
            const name = (feature.properties || {}).barangay_name || (feature.properties || {}).name || 'Barangay';
            polygon.bindTooltip(name, {sticky:true});
        }});
    }
    const bands = data.severityBands || { yellow: 5, orange: 10, critical: 15 };
    const colors = { low: '#10B981', medium: '#F59E0B', high: '#F97316', critical: '#EF4444' };
    function risk(report) {
        if (report.severity_score == null) return report.risk_level || 'low';
        const score = Number(report.severity_score);
        return score < bands.yellow ? 'low' : score < bands.orange ? 'medium' : score < bands.critical ? 'high' : 'critical';
    }
    const layer = SierraMapClusters.layer({ radiusMeters: settings.clustering_radius_meters,
        iconCreateFunction: function (cluster) {
            const members = cluster.getAllChildMarkers();
            const score = members.reduce((sum, marker) => sum + marker.options.severityScore, 0) / members.length;
            const size = Math.min(64, 38 + Math.log2(members.length) * 6);
            return L.divIcon({className:'sierra-distance-cluster', html:'<span style="background:' + colors[risk({severity_score:score})] + ';width:100%;height:100%;border-radius:50%;display:grid;place-items:center">' + members.length + '</span>',iconSize:[size,size]});
        }
    });
    layer.addTo(map);
    const controls = {
        period: document.getElementById('sierraMapPeriod'),
        category: document.getElementById('sierraMapCategory'),
        risk: document.getElementById('sierraMapRisk'),
        barangay: document.getElementById('sierraMapBarangay'),
        from: document.getElementById('sierraMapFrom'),
        to: document.getElementById('sierraMapTo')
    };
    let mode = 'active';
    function dateMatch(report) {
        const date = String((mode === 'historical' ? report.resolved_at : report.created_at) || '').slice(0, 10);
        const period = controls.period ? controls.period.value : 'all';
        if (period === 'all') return true;
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        if (period === 'custom') return (!controls.from.value || date >= controls.from.value) && (!controls.to.value || date <= controls.to.value);
        if (period === 'week') start.setDate(start.getDate() - 6);
        if (period === 'month') start.setDate(1);
        if (period === 'year') start.setMonth(0, 1);
        const localDate = start.getFullYear() + '-' + String(start.getMonth() + 1).padStart(2, '0') + '-' + String(start.getDate()).padStart(2, '0');
        return date >= localDate;
    }
    function text(tag, value, className) {
        const item = document.createElement(tag);
        if (className) item.className = className;
        item.textContent = value || '';
        return item;
    }
    function popup(report) {
        const box = document.createElement('div');
        box.className = 'sierra-map-popup';
        box.appendChild(text('strong', report.title));
        box.appendChild(text('span', report.barangay_name || report.location_address || 'San Isidro'));
        box.appendChild(text('span', (report.category_name || 'Uncategorized') + ' · ' + risk(report)));
        const link = text('a', 'View report →');
        link.href = report.url;
        box.appendChild(link);
        return box;
    }
    function refresh() {
        layer.clearLayers();
        const markers = [];
        (data.reports || []).forEach(function (report) {
            const active = !['resolved', 'rejected', 'cancelled'].includes(report.status);
            if (mode === 'historical' && report.status !== 'resolved') return;
            if ((mode === 'active') !== active || !dateMatch(report)) return;
            if (controls.category && controls.category.value && String(report.category_id) !== controls.category.value) return;
            if (controls.risk && controls.risk.value && risk(report) !== controls.risk.value) return;
            if (controls.barangay && controls.barangay.value && String(report.barangay_id) !== controls.barangay.value) return;
            const lat = Number(report.latitude), lng = Number(report.longitude);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
            const color = colors[risk(report)];
            const pin = document.createElement('div'); pin.className = 'sev-marker-wrap';
            const dot = text('div', '', 'sev-marker-dot'); dot.style.background = color; pin.appendChild(dot);
            pin.appendChild(text('div', report.category_name || 'Uncategorized', 'sev-marker-label'));
            const icon = L.divIcon({ className:'severity-marker',html:pin,iconSize:[140,44],iconAnchor:[70,11] });
            const marker = L.marker([lat, lng], {icon, reportTitle:report.title, severityScore:Number(report.severity_score) || 0}).bindPopup(popup(report));
            if (window.SierraMapReportPanel) marker.on('click', () => SierraMapReportPanel.open(report.token || report.id));
            markers.push(marker);
        });
        layer.addLayers(markers);
        const count = markers.length;
        document.getElementById('sierraMapCount').textContent = count + (count === 1 ? ' report shown' : ' reports shown');
    }
    document.querySelectorAll('[data-map-mode]').forEach(function (button) {
        button.addEventListener('click', function () {
            mode = button.dataset.mapMode;
            document.querySelectorAll('[data-map-mode]').forEach(function (item) { item.classList.toggle('active', item === button); });
            refresh();
        });
    });
    document.querySelectorAll('[data-map-period]').forEach(function (button) {
        button.addEventListener('click', function () {
            controls.period.value = button.dataset.mapPeriod;
            controls.period.dispatchEvent(new Event('change'));
            document.querySelectorAll('[data-map-period]').forEach(item => item.classList.toggle('active', item === button));
        });
    });
    Object.values(controls).forEach(function (input) { if (input) input.addEventListener('change', function () {
        const dates = document.getElementById('sierraMapDates');
        if (dates) dates.hidden = controls.period.value !== 'custom';
        refresh();
    }); });
    refresh();
})();
