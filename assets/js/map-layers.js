// assets/js/map-layers.js
// Base map presets shared by every map in the system (Leaflet + free no-key
// tile providers only — no CARTO). Users can switch between three views:
// Satellite (Esri World Imagery), Street (OpenStreetMap), Light (Esri gray canvas).
// Satellite is the starting view. Also provides shared helpers for the dashed
// green/white boundary styling and the selected-polygon "spotlight" mask.

(function () {
    'use strict';

    function satelliteLayer() {
        return L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community',
            maxZoom: 20,
            maxNativeZoom: 19
        });
    }

    function streetLayer() {
        return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            subdomains: 'abc',
            maxZoom: 20,
            maxNativeZoom: 19
        });
    }

    function lightLayer() {
        return L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ',
            maxZoom: 20,
            maxNativeZoom: 19
        });
    }

    /**
     * Attach the base-layer switcher to a Leaflet map.
     * @param {L.Map} map Target map instance
     * @param {Object} opts Optional { default: 'Satellite'|'Street'|'Light', position }
     * @returns {L.TileLayer} The default layer that was added to the map
     */
    function addMapLayerControl(map, opts) {
        opts = opts || {};
        // Authenticated maps keep the same usable zoom range on every device.
        if (document.getElementById('sidebar')) configureMap(map);

        var layers = {
            'Satellite': satelliteLayer(),
            'Street': streetLayer(),
            'Light': lightLayer()
        };

        var defaultName = layers[opts.default] ? opts.default : 'Satellite';
        var active = layers[defaultName];
        active.addTo(map);

        L.control.layers(layers, null, {
            position: opts.position || 'topright',
            collapsed: opts.collapsed !== false
        }).addTo(map);

        return active;
    }

    function configureMap(map) {
        if (map._sierraConfigured) return;
        map._sierraConfigured = true;
        map.setMinZoom(3);
        map.setMaxZoom(20);
        map.options.zoomSnap = 1;
        map.options.zoomDelta = 1;
        map.options.wheelPxPerZoomLevel = 90;
        map.touchZoom && map.touchZoom.enable();
        map.doubleClickZoom && map.doubleClickZoom.enable();
        map.boxZoom && map.boxZoom.enable();
        // Resize without recentering or changing the zoom selected by the user.
        var queued = false;
        function resize() {
            if (queued) return;
            queued = true;
            window.requestAnimationFrame(function () {
                queued = false;
                if (map._loaded) map.invalidateSize({pan:false});
            });
        }
        var observer = window.ResizeObserver ? new ResizeObserver(resize) : null;
        if (observer) observer.observe(map.getContainer());
        window.addEventListener('resize', resize);
        map.on('unload', function () {
            if (observer) observer.disconnect();
            window.removeEventListener('resize', resize);
        });
        resize();
    }

    function getSettings() {
        var values = window.SierraMapSettings || {};
        return {
            default_lat: Number.isFinite(Number(values.default_lat)) && values.default_lat != null ? Number(values.default_lat) : 15.3092,
            default_lng: Number.isFinite(Number(values.default_lng)) && values.default_lng != null ? Number(values.default_lng) : 120.9033,
            default_zoom: Math.max(3, Math.min(20, Number(values.default_zoom) || 14)),
            clustering_radius_meters: Math.max(0, Number(values.clustering_radius_meters ?? 50) || 0)
        };
    }

    // The Analytics outline: white casing below a green dashed boundary.
    function addBoundary(map, geojson, options) {
        options = options || {};
        L.geoJSON(geojson, {style:whiteCasingStyle(1.5),interactive:false}).addTo(map);
        return L.geoJSON(geojson, {
            style:dashedBoundaryStyle(1.5),
            interactive:options.interactive !== false,
            onEachFeature:options.onEachFeature
        }).addTo(map);
    }

    // Green dashed stroke (transparent fill). Pairs with whiteCasingStyle() so
    // the boundary reads as alternating green/white dashes.
    function dashedBoundaryStyle(weight, color) {
        return {
            color: color || '#4ADE80',
            weight: weight || 1.5,
            opacity: 0.9,
            dashArray: '6 6',
            fillColor: color || '#4ADE80',
            fillOpacity: 0,
            smoothFactor: 1
        };
    }

    // Solid white line drawn UNDER the dashed green stroke (the "white" part of
    // the alternating dashes, plus a clean white edge).
    function whiteCasingStyle(weight) {
        return {
            color: '#ffffff',
            weight: (weight || 1.5) + 2,
            opacity: 1,
            fill: false,
            fillOpacity: 0,
            smoothFactor: 1
        };
    }

    // Normalize a Leaflet layer's latlngs (single Polygon ring) so it can be
    // used as the hole for the spotlight mask.
    function normalizeRings(latlngs) {
        if (!latlngs || !latlngs.length) return null;
        var first = latlngs[0];
        if (first && typeof first[0] === 'number') return latlngs;      // single ring
        if (first && Array.isArray(first[0])) return first;             // first ring of multi
        return latlngs;
    }

    // Inverted-polygon mask: heavy desaturation (white veil) over everything
    // OUTSIDE the selected polygon, leaving the inside at full saturation.
    function spotlight(map, layerOrLatLngs, options) {
        options = options || {};
        var latlngs = (layerOrLatLngs && layerOrLatLngs.getLatLngs) ? layerOrLatLngs.getLatLngs() : layerOrLatLngs;
        var ring = normalizeRings(latlngs);
        if (!ring) return null;

        var world = [[-85, -180], [-85, 180], [85, 180], [85, -180]];
        var mask = L.polygon([world, ring], {
            fillColor: options.fillColor || '#ffffff',
            fillOpacity: (options.fillOpacity != null) ? options.fillOpacity : 0.3,
            stroke: false,
            interactive: false
        }).addTo(map);
        return mask;
    }

    window.MapLayers = {
        addControl: addMapLayerControl,
        getLayers: function () { return { 'Satellite': satelliteLayer(), 'Street': streetLayer(), 'Light': lightLayer() }; },
        dashedBoundaryStyle: dashedBoundaryStyle,
        whiteCasingStyle: whiteCasingStyle,
        spotlight: spotlight,
        addBoundary: addBoundary,
        configure: configureMap,
        getSettings: getSettings
    };
})();
