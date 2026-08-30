// assets/js/map-layers.js
// Single OpenStreetMap base layer (Leaflet + OSM only). Also provides shared
// helpers for the dashed green/white administrative boundary styling and the
// "spotlight" mask that desaturates everything outside a selected polygon.

(function () {
    'use strict';

    function osmLayer() {
        return L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 20,
            maxNativeZoom: 19
        });
    }

    function addMapLayerControl(map) {
        var layer = osmLayer();
        layer.addTo(map);
        return layer;
    }

    // Green dashed stroke (transparent fill). Pairs with whiteCasingStyle() so
    // the boundary reads as alternating green/white dashes.
    function dashedBoundaryStyle(weight, color) {
        return {
            color: color || '#10A37F',
            weight: weight || 1.5,
            opacity: 0.9,
            dashArray: '6 6',
            fillColor: color || '#10A37F',
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
    // Pass a Leaflet layer or raw latlngs.
    function spotlight(map, layerOrLatLngs, options) {
        options = options || {};
        var latlngs = (layerOrLatLngs && layerOrLatLngs.getLatLngs) ? layerOrLatLngs.getLatLngs() : layerOrLatLngs;
        var ring = normalizeRings(latlngs);
        if (!ring) return null;

        var world = [[-85, -180], [-85, 180], [85, 180], [85, -180]];
        var mask = L.polygon([world, ring], {
            fillColor: options.fillColor || '#ffffff',
            fillOpacity: (options.fillOpacity != null) ? options.fillOpacity : 0.6,
            stroke: false,
            interactive: false
        }).addTo(map);
        return mask;
    }

    window.MapLayers = {
        addControl: addMapLayerControl,
        getLayers: function () { return { 'OpenStreetMap': osmLayer() }; },
        dashedBoundaryStyle: dashedBoundaryStyle,
        whiteCasingStyle: whiteCasingStyle,
        spotlight: spotlight
    };
})();
