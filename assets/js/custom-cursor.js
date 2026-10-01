/* Sierra Eco — branded interactive cursor.
   Vanilla, dependency-free. Requires .brand-cursor markup in the page
   (see views/index.php). Disables itself on touch / reduced-motion.

   Motion model (conflict-free):
   - JS animates .brand-cursor-ringwrap (translate3d, size 0x0 anchor)
   - CSS scales .brand-cursor-ring inside the wrapper (transform scale)
   - both dot and ring are driven from ONE rAF loop, so if the loop
     runs, the ring is guaranteed to move too. */
(function () {
    'use strict';
    if (window.matchMedia('(pointer: coarse)').matches) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var cursorEl = document.querySelector('.brand-cursor');
    if (!cursorEl) return;
    var dot = cursorEl.querySelector('.brand-cursor-dot');
    var ringwrap = cursorEl.querySelector('.brand-cursor-ringwrap');
    var ring = cursorEl.querySelector('.brand-cursor-ring');
    var leaf = cursorEl.querySelector('.brand-cursor-leaf');
    if (!dot || !ringwrap || !ring || !leaf) return;

    var INTERACTIVE = [
        'a', 'button', 'input', 'select', 'textarea', 'label', 'summary',
        '[role="button"]', '[onclick]', '[data-cursor]', '.cursor-pointer',
        'details > summary', '.swiper-slide', '.landing-leaf'
    ].join(',');

    document.documentElement.classList.add('brand-cursor-active');

    var started = false;
    var mx = window.innerWidth / 2, my = window.innerHeight / 2;
    var rx = mx, ry = my;
    var lastX = mx, lastY = my;
    var leafAngle = 45; /* base tilt so the leaf points up-right */
    var blend = 0;

    function show() {
        if (!started) return;
        cursorEl.classList.add('is-visible');
    }

    function place(e) {
        mx = e.clientX;
        my = e.clientY;
        if (!started) started = true;
        show();
    }

    function interactiveOf(n) {
        return (n && n.closest) ? n.closest(INTERACTIVE) : null;
    }

    if (window.PointerEvent) document.addEventListener('pointermove', place);
    else document.addEventListener('mousemove', place);

    document.addEventListener('mouseover', function (e) {
        var node = interactiveOf(e.target);
        var isInput = !!node && (node.matches('input,select,textarea') ||
                                 !!node.closest('input,select,textarea'));
        var isMap = !!(e.target.closest && e.target.closest('.leaflet-container'));
        cursorEl.classList.toggle('is-hover', !!node && !isInput && !isMap);
        cursorEl.classList.toggle('is-input', isInput && !isMap);
        cursorEl.classList.toggle('is-map', isMap);
    });

    document.addEventListener('mouseout', function (e) {
        /* leaving the window -> hide so the cursor doesn't sit frozen at the edge */
        if (!e.relatedTarget) cursorEl.classList.remove('is-visible');
    });
    document.addEventListener('mouseover', function (e) {
        if (!e.relatedTarget) { place(e); show(); }
    });

    document.addEventListener('mousedown', function () { cursorEl.classList.add('is-down'); });
    document.addEventListener('mouseup', function () { cursorEl.classList.remove('is-down'); });

    function frame() {
        /* ring chases the pointer with easing */
        rx += (mx - rx) * 0.16;
        ry += (my - ry) * 0.16;

        /* leaf leans toward the direction of travel */
        var dx = mx - lastX, dy = my - lastY;
        var speed = Math.sqrt(dx * dx + dy * dy);
        if (speed > 0.5) leafAngle = Math.atan2(dy, dx) * 180 / Math.PI;
        lastX = mx; lastY = my;
        blend += (speed * 0.05 - blend) * 0.18;
        var flip = (Math.abs(leafAngle) > 90) ? -1 : 1;

        try {
            dot.style.transform = 'translate3d(' + mx + 'px,' + my + 'px,0)';
            ringwrap.style.transform = 'translate3d(' + rx.toFixed(1) + 'px,' + ry.toFixed(1) + 'px,0)';
            leaf.style.transform = 'rotate(' + leafAngle.toFixed(1) + 'deg) scaleY(' + flip + ')';
            leaf.style.opacity = String(0.55 + Math.min(1, blend) * 0.4);
        } catch (e) { /* keep the loop alive no matter what */ }

        requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
})();