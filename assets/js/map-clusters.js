/* Geographic grouping stays the same at every zoom level. */
(function (root, factory) {
    const api = factory(root);
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.SierraMapClusters = api;
}(typeof window !== 'undefined' ? window : globalThis, function (root) {
    'use strict';
    const earth = 6371000;
    function color(score) {
        const bands = (root.SierraMapSettings || {}).severityBands || { yellow:5, orange:9, critical:15 };
        return score < bands.yellow ? '#10B981' : score < bands.orange ? '#F59E0B' : score < bands.critical ? '#F97316' : '#EF4444';
    }
    function icon(score, label, owned) {
        const wrap = document.createElement('div'); wrap.className = 'sev-marker-wrap';
        const dot = document.createElement('div'); dot.className = 'sev-marker-dot'; dot.style.background = color(Number(score) || 0); wrap.appendChild(dot);
        if (owned) { const badge = document.createElement('span'); badge.className = 'sierra-own-marker'; badge.textContent = '★'; badge.title = 'Your report'; wrap.appendChild(badge); }
        if (label) { const caption = document.createElement('div'); caption.className = 'sev-marker-label'; caption.textContent = label; wrap.appendChild(caption); }
        return root.L.divIcon({ className:'severity-marker', html:wrap, iconSize:[120,40], iconAnchor:[60,11] });
    }
    function distance(a, b) {
        const rad = Math.PI / 180, lat = (b.lat - a.lat) * rad, lng = (b.lng - a.lng) * rad;
        const h = Math.sin(lat / 2) ** 2 + Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.sin(lng / 2) ** 2;
        return earth * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(Math.max(0, 1 - h)));
    }
    function group(items, radius, position) {
        const locate = position || (item => ({ lat: Number(item.latitude), lng: Number(item.longitude) }));
        const points = items.map(item => ({ item, point: locate(item) })).filter(({ point }) =>
            Number.isFinite(point.lat) && Number.isFinite(point.lng) && Math.abs(point.lat) <= 90 && Math.abs(point.lng) <= 180
        ).sort((a, b) => a.point.lat - b.point.lat || a.point.lng - b.point.lng);
        if (!(radius > 0)) return points.map(p => [p.item]);
        const buckets = new Map(), groups = [];
        function cell(p) {
            const lat = p.lat * Math.PI / 180, lng = p.lng * Math.PI / 180;
            return [Math.floor(earth * Math.cos(lat) * Math.cos(lng) / radius),
                Math.floor(earth * Math.cos(lat) * Math.sin(lng) / radius), Math.floor(earth * Math.sin(lat) / radius)];
        }
        points.forEach(entry => {
            const xyz = cell(entry.point), candidates = [];
            for (let x = -1; x <= 1; x++) for (let y = -1; y <= 1; y++) for (let z = -1; z <= 1; z++) {
                candidates.push(...(buckets.get([xyz[0] + x, xyz[1] + y, xyz[2] + z].join(',')) || []));
            }
            const existing = candidates.sort((a, b) => a - b).find(index =>
                groups[index].every(member => distance(member.point, entry.point) <= radius)
            );
            if (existing !== undefined) groups[existing].push(entry);
            else {
                const key = xyz.join(','), index = groups.push([entry]) - 1;
                if (!buckets.has(key)) buckets.set(key, []);
                buckets.get(key).push(index);
            }
        });
        return groups.map(members => members.map(member => member.item));
    }
    function layer(options) {
        const L = root.L, settings = options || {}, result = L.featureGroup();
        const add = result.addLayer, clear = result.clearLayers, remove = result.removeLayer;
        let markers = [];
        let expanded = null, attachedMap = null;
        function collapse() {
            if (!expanded) return;
            const previous = expanded;
            expanded = null;
            remove.call(result, previous.layer);
            if (result._map) add.call(result, previous.cluster);
        }
        function expand(clusterMarker, members, center) {
            const map = result._map;
            if (!map) return;
            collapse();
            map.closePopup();
            map.setView(center, Math.min(map.getMaxZoom(), Math.max(map.getZoom(), 18)), {animate:false});
            const spread = L.featureGroup(), origin = map.latLngToLayerPoint(center);
            const size = map.getSize();
            const radius = Math.min(Math.max(35, Math.min(size.x, size.y) / 2 - 32), Math.max(45, members.length * 12));
            members.forEach((member, index) => {
                const angle = index * Math.PI * 2 / members.length - Math.PI / 2;
                const point = L.point(origin.x + Math.cos(angle) * radius, origin.y + Math.sin(angle) * radius);
                const position = map.layerPointToLatLng(point);
                spread.addLayer(L.polyline([member.getLatLng(), position], {
                    color:'#0d8568', weight:1.5, opacity:.65, interactive:false
                }));
                const pin = L.marker(position, Object.assign({}, member.options, {title:member.options.reportTitle || 'Report'}));
                const popup = member.getPopup();
                if (popup) pin.bindPopup(popup.getContent(), popup.options);
                pin.on('click', () => member.fire('click'));
                spread.addLayer(pin);
            });
            remove.call(result, clusterMarker);
            expanded = {cluster:clusterMarker, layer:spread};
            add.call(result, spread);
        }
        function render() {
            if (!result._map) return;
            collapse();
            clear.call(result);
            group(markers, Number(settings.radiusMeters ?? 50), marker => marker.getLatLng()).forEach(members => {
                if (members.length === 1) { add.call(result, members[0]); return; }
                const center = L.latLngBounds(members.map(marker => marker.getLatLng())).getCenter();
                const cluster = { getAllChildMarkers: () => members, getChildCount: () => members.length };
                const average = members.reduce((sum, marker) => sum + (Number(marker.options.severityScore) || 0), 0) / members.length;
                const icon = settings.iconCreateFunction ? settings.iconCreateFunction(cluster) : L.divIcon({
                    className: 'sierra-distance-cluster', html: '<span style="background:' + color(average) + ';border-radius:50%;width:100%;height:100%;display:grid;place-items:center">' + members.length + '</span>', iconSize: [42, 42]
                });
                const clusterMarker = L.marker(center, {icon, title:members.length + ' nearby reports — click to separate'});
                clusterMarker.on('click', () => expand(clusterMarker, members, center));
                add.call(result, clusterMarker);
            });
        }
        result.addLayer = function (marker) { markers.push(marker); if (result._map) render(); return result; };
        result.addLayers = function (items) { markers.push(...items); render(); return result; };
        result.clearLayers = function () { collapse(); markers = []; clear.call(result); return result; };
        result.on('add', function () {
            attachedMap = result._map;
            attachedMap.on('click zoomstart', collapse);
            render();
        });
        result.on('remove', function () {
            if (attachedMap) attachedMap.off('click zoomstart', collapse);
            attachedMap = null;
            collapse();
        });
        return result;
    }
    return { distance, group, layer, color, icon };
}));
