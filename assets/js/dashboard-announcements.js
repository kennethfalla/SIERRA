(function () {
    'use strict';
    document.querySelectorAll('.announce-carousel').forEach(function (card) {
        var slides = Array.from(card.querySelectorAll('.announce-slide'));
        if (slides.length < 2) return;
        var index = 0;
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var touching = false;
        var startX = 0;
        var count = card.querySelector('[data-ann-count]');
        var timer = null;
        var transitions = new Map();
        function show(next, manual) {
            next = (next + slides.length) % slides.length;
            if (next === index) return;
            if (transitions.has(slides[next])) {
                window.clearTimeout(transitions.get(slides[next]));
                transitions.delete(slides[next]);
                slides[next].classList.remove('is-leaving');
            }
            var current = slides[index];
            current.classList.remove('is-active');
            current.classList.add('is-leaving');
            transitions.set(current, window.setTimeout(function () {
                current.hidden = true;
                current.classList.remove('is-leaving');
                transitions.delete(current);
            }, reduceMotion ? 0 : 420));
            index = next;
            slides[index].hidden = false;
            slides[index].classList.add('is-active');
            if (count) count.setAttribute('aria-live', manual ? 'polite' : 'off');
            restart();
        }
        function restart() {
            if (timer) window.clearInterval(timer);
            if (reduceMotion) return;
            timer = window.setInterval(function () {
                if (!document.hidden && !touching) show(index + 1, false);
            }, 5200);
        }
        slides[0].classList.add('is-active');
        card.querySelector('[data-ann-prev]').addEventListener('click', function () { show(index - 1, true); restart(); });
        card.querySelector('[data-ann-next]').addEventListener('click', function () { show(index + 1, true); restart(); });
        card.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault(); show(index + (event.key === 'ArrowLeft' ? -1 : 1), true);
            }
        });
        card.addEventListener('touchstart', function (event) { touching = true; startX = event.touches[0].clientX; }, { passive: true });
        card.addEventListener('touchend', function (event) {
            touching = false;
            var delta = event.changedTouches[0].clientX - startX;
            if (Math.abs(delta) > 45) show(index + (delta < 0 ? 1 : -1), true);
        }, { passive: true });
        card.addEventListener('touchcancel', function () { touching = false; }, { passive: true });
        document.addEventListener('visibilitychange', restart);
        restart();
    });
}());
