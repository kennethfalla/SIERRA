// assets/js/map-layers.js
// Base map presets shared by every map in the system.
// Every user can switch between three views: Satellite, Street, Light.
// The Satellite layer (Esri World Imagery) is the starting view.
//
// CARTO basemaps require a free API key. Request one (no CARTO account needed,
// free up to 5M tile requests/month) at https://carto.com/basemaps/apikey
// then paste it into CARTO_API_KEY below.
var CARTO_API_KEY = 'YOUR_KEY';

(function () {
    'use strict';

    function lightLayer() {
        return L.tileLayer('https://basemaps.cartocdn.com/rastertiles/light_all/{z}/{x}/{y}.png?key=' + CARTO_API_KEY, {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
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
        return L.tileLayer('https://basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png?key=' + CARTO_API_KEY, {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
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

    window.MapLayers = {
        getLayers: function () {
            return { 'Satellite': satelliteLayer(), 'Street': streetLayer(), 'Light': lightLayer() };
        },
        addControl: addMapLayerControl
    };
})();
