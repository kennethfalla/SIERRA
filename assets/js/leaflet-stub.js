/* Leaflet failure stub.
   If Leaflet failed to load (CDN/network issue), provide inert no-op stubs so
   page scripts never die with "L is not defined". Maps simply render empty. */
(function () {
    'use strict';
    if (typeof window.L !== 'undefined') return;

    var stub;
    var handler = {
        apply: function () { return stub; },
        construct: function () { return stub; },
        get: function () { return stub; },
        set: function () { return true; }
    };
    stub = new Proxy(function () {}, handler);
    window.L = stub;
})();