// assets/js/map-layers.js
// Base map presets shared by every map in the system.
// Every user can switch between three views: Default (original), Satellite, Street.
// The Default layer (original Carto light tiles) is always the starting view.

(function () {
    'use strict';

    function defaultLayer() {
        return L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 20,
            maxNativeZoom: 19
        });
    }

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

    /**
     * Attach the base-layer switcher to a Leaflet map.
     * @param {L.Map} map Target map instance
     * @param {Object} opts Optional { default: 'Default'|'Satellite'|'Street', position }
     * @returns {L.TileLayer} The default layer that was added to the map
     */
    function addMapLayerControl(map, opts) {
        opts = opts || {};

        var layers = {
            'Default': defaultLayer(),
            'Satellite': satelliteLayer(),
            'Street': streetLayer()
        };

        var defaultName = layers[opts.default] ? opts.default : 'Default';
        var active = layers[defaultName];
        active.addTo(map);

        L.control.layers(layers, null, {
            position: opts.position || 'topright',
            collapsed: opts.collapsed !== false
        }).addTo(map);

        return active;
    }

    window.MapLayers = {
        getLayers: function () {
            return { 'Default': defaultLayer(), 'Satellite': satelliteLayer(), 'Street': streetLayer() };
        },
        addControl: addMapLayerControl
    };
})();
