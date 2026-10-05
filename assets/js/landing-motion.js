// Landing scroll motion (Alethia-inspired, original implementation):
// progress bar, parallax, hero fade/lift, fly-in step cards, a pinned
// independent map zoom journey, a stats number-storm that
// resolves into the real stat cards, and a scroll-scrubbed word reveal.
(function () {
    'use strict';
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    // Cards move along a shallow arc, always facing the viewer. Their position
    // comes from the duplicated track, so looping does not reset card angles.
    var orbit = document.querySelector('.hero-orbit');
    var ring = orbit && orbit.querySelector('.hero-orbit-ring');
    if (ring) {
        var orbitCards = Array.prototype.slice.call(ring.querySelectorAll('.hero-orbit-card'));
        var orbitWidth = 0;
        var cardPositions = [];
        var orbitFrame = null;
        var orbitVisible = true;
        function measureOrbit() {
            orbitWidth = orbit.clientWidth;
            cardPositions = orbitCards.map(function (card) {
                return card.offsetLeft + card.parentElement.offsetLeft + card.offsetWidth / 2;
            });
        }
        function curveOrbit() {
            orbitFrame = null;
            var transform = getComputedStyle(ring).transform;
            var offset = 0;
            if (transform !== 'none') {
                var values = transform.slice(transform.indexOf('(') + 1, -1).split(',');
                offset = parseFloat(values[values.length === 16 ? 12 : 4]) || 0;
            }
            orbitCards.forEach(function (card, index) {
                var position = Math.max(-1, Math.min(1, (cardPositions[index] + offset - orbitWidth / 2) / (orbitWidth * .55)));
                var curve = position * position;
                card.style.transform = 'translate3d(0,' + (curve * 28).toFixed(2) + 'px,0) perspective(900px) rotateY(' + (-position * 42).toFixed(2) + 'deg) scale(' + (1 - curve * .12).toFixed(4) + ')';
            });
            if (orbitVisible && !document.hidden && !reducedMotion.matches) orbitFrame = requestAnimationFrame(curveOrbit);
        }
        function refreshOrbit() {
            measureOrbit();
            if (orbitFrame === null) curveOrbit();
        }
        if (window.IntersectionObserver) {
            new IntersectionObserver(function (entries) {
                orbitVisible = entries[0].isIntersecting;
                if (orbitVisible) refreshOrbit();
            }).observe(orbit);
        }
        window.addEventListener('resize', refreshOrbit, { passive: true });
        document.addEventListener('visibilitychange', function () { if (!document.hidden && orbitVisible) refreshOrbit(); });
        reducedMotion.addEventListener('change', refreshOrbit);
        refreshOrbit();
    }
    if (reducedMotion.matches) return;

    var clamp = function (v, min, max) { return Math.min(max, Math.max(min, v)); };

    document.body.classList.add('lp-motion');

    // Top scroll-progress bar
    var bar = document.createElement('div');
    bar.className = 'lp-scroll-progress';
    bar.setAttribute('aria-hidden', 'true');
    document.body.appendChild(bar);

    var parallaxEls = Array.prototype.slice.call(document.querySelectorAll('[data-parallax]'));
    var hero = document.getElementById('home');
    var heroContent = document.querySelector('.hero-content-wrap');
    var mapSection = document.getElementById('map-section');
    var statsSection = document.getElementById('stats');
    var storm = document.querySelector('.lp-stats-storm');

    // --- How It Works: arm the cards, reveal them when scrolled into view ---
    var stepsTrack = document.querySelector('.lp-steps-track');
    var stepsFill = stepsTrack ? stepsTrack.querySelector('.lp-steps-fill') : null;
    var stepCards = stepsTrack ? Array.prototype.slice.call(stepsTrack.querySelectorAll('.lp-step')) : [];
    var activeStep = null;
    if (stepsTrack) {
        stepsTrack.classList.add('lp-steps-armed');
        var armObs = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) { stepsTrack.classList.add('lp-steps-in'); armObs.disconnect(); }
            });
        }, { threshold: 0.12 });
        if (stepsTrack.getBoundingClientRect().top < window.innerHeight * 0.85) stepsTrack.classList.add('lp-steps-in');
        else armObs.observe(stepsTrack);
    }

    // --- Stats storm: count the big numbers up once it scrolls in ---
    var stormNums = Array.prototype.slice.call(document.querySelectorAll('.lp-storm-num[data-storm-to]'));
    var stormDone = false;
    function runStorm() {
        if (stormDone || !stormNums.length) return;
        stormDone = true;
        var start = null;
        function tick(ts) {
            if (start === null) start = ts;
            var p = Math.min(1, (ts - start) / 950);
            var eased = 1 - Math.pow(1 - p, 3);
            stormNums.forEach(function (el) {
                var to = parseInt(el.getAttribute('data-storm-to'), 10) || 0;
                el.textContent = Math.round(to * eased).toLocaleString();
            });
            if (p < 1) window.requestAnimationFrame(tick);
        }
        window.requestAnimationFrame(tick);
    }
    if (statsSection && stormNums.length) {
        if (!window.IntersectionObserver) { runStorm(); }
        else {
            var stormObs = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) { if (entry.isIntersecting) { runStorm(); stormObs.disconnect(); } });
            }, { threshold: 0.1 });
            stormObs.observe(statsSection);
        }
    }

    // --- Headings: split into words for the scroll-scrub reveal ---
    function splitWords(root) {
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
        var textNodes = [];
        var node;
        while ((node = walker.nextNode())) {
            if (node.nodeValue && node.nodeValue.trim()) textNodes.push(node);
        }
        textNodes.forEach(function (textNode) {
            var frag = document.createDocumentFragment();
            textNode.nodeValue.split(/(\s+)/).forEach(function (part) {
                if (part === '') return;
                if (/^\s+$/.test(part)) {
                    frag.appendChild(document.createTextNode(part));
                } else {
                    var span = document.createElement('span');
                    span.className = 'lp-word';
                    span.textContent = part;
                    frag.appendChild(span);
                }
            });
            textNode.parentNode.replaceChild(frag, textNode);
        });
    }
    var wordGroups = [];
    Array.prototype.slice.call(document.querySelectorAll('.lp-steps-head, .lp-section-head')).forEach(function (h) {
        splitWords(h);
        var words = Array.prototype.slice.call(h.querySelectorAll('.lp-word'));
        if (words.length) wordGroups.push({ words: words });
    });

    var ticking = false;
    var lastMapProgress = -1;

    // Transform-independent document position (offsetTop ignores CSS transforms),
    // so parallaxed elements never feed their own movement back into the math.
    function docTop(el) {
        var top = 0;
        while (el) { top += el.offsetTop || 0; el = el.offsetParent; }
        return top;
    }
    function measure() {
        parallaxEls.forEach(function (el) {
            el._baseTop = docTop(el);
            el._baseH = el.offsetHeight;
        });
        if (mapSection) mapSection._baseTop = docTop(mapSection);
    }

    function update() {
        ticking = false;
        var vh = window.innerHeight || document.documentElement.clientHeight;
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
        var maxScroll = document.documentElement.scrollHeight - vh;

        if (bar) {
            var p = maxScroll > 0 ? scrollTop / maxScroll : 0;
            bar.style.transform = 'scaleX(' + clamp(p, 0, 1).toFixed(4) + ')';
        }

        for (var i = 0; i < parallaxEls.length; i++) {
            var el = parallaxEls[i];
            var speed = parseFloat(el.getAttribute('data-parallax')) || 0.12;
            var center = (el._baseTop - scrollTop) + el._baseH / 2;
            var offset = (center - vh / 2) * speed;
            el.style.transform = 'translate3d(0,' + offset.toFixed(2) + 'px,0)';
        }

        if (hero && heroContent) {
            var hRect = hero.getBoundingClientRect();
            var progress = clamp(-hRect.top / (hRect.height || 1), 0, 1);
            heroContent.style.transform = 'translate3d(0,' + (-progress * 64).toFixed(2) + 'px,0)';
            heroContent.style.opacity = (1 - progress * 0.85).toFixed(3);
        }

        // The map starts after How It Works. Scroll within its own sticky stage
        // zooms toward San Isidro, reveals details, then releases into Statistics.
        if (mapSection) {
            var mapRect = mapSection.getBoundingClientRect();
            var travel = Math.max(1, mapSection.offsetHeight - (vh - 64));
            var mapProgress = clamp((64 - mapRect.top) / travel, 0, 1);
            var reveal = clamp((mapProgress - 0.1) / 0.18, 0, 1);
            mapSection.style.setProperty('--map-reveal', reveal.toFixed(4));
            mapSection.classList.toggle('map-details-visible', reveal > 0);
            var reportPanel = mapSection.querySelector('.lp-map-reports');
            if (reportPanel) reportPanel.inert = reveal === 0;
            mapSection.dataset.journeyProgress = mapProgress.toFixed(4);
            if (Math.abs(mapProgress - lastMapProgress) > 0.0005) {
                mapSection.dispatchEvent(new CustomEvent('sierra:map-progress', { detail: { progress: mapProgress } }));
                lastMapProgress = mapProgress;
            }
        }

        // How It Works: connector fill + nearest-step highlight
        if (stepsTrack && stepCards.length) {
            var sRect = stepsTrack.getBoundingClientRect();
            if (stepsFill) {
                var fillP = clamp((vh * 0.8 - sRect.top) / (sRect.height + vh * 0.2), 0, 1);
                stepsFill.style.transform = 'scaleX(' + fillP.toFixed(4) + ')';
            }
            var focusY = vh * 0.42;
            var best = null;
            var bestDist = Infinity;
            for (var s = 0; s < stepCards.length; s++) {
                var cRect = stepCards[s].getBoundingClientRect();
                var cCenter = cRect.top + cRect.height / 2;
                var dist = Math.abs(cCenter - focusY);
                if (dist < bestDist) { bestDist = dist; best = stepCards[s]; }
            }
            if (best !== activeStep) {
                if (activeStep) activeStep.classList.remove('is-active');
                if (best && bestDist < vh * 0.5) best.classList.add('is-active');
                activeStep = best;
            }
        }

        // Stats storm: visible as the section enters, then scales/blurs/fades away.
        if (statsSection && storm) {
            var stRect = statsSection.getBoundingClientRect();
            var sp = clamp((vh - stRect.top) / (vh * 0.85), 0, 1);
            var vis = clamp(1 - (sp - 0.3) / 0.55, 0, 1);
            storm.style.opacity = vis.toFixed(3);
            storm.style.transform = 'scale(' + (1 + sp * 0.6).toFixed(3) + ')';
            storm.style.filter = 'blur(' + (sp * 6).toFixed(2) + 'px)';
        }

        // Scroll-scrubbed headings: light words as they pass the reading line
        for (var g = 0; g < wordGroups.length; g++) {
            var words = wordGroups[g].words;
            var wRect = words[0].getBoundingClientRect();
            var wp = clamp((vh * 0.82 - wRect.top) / (vh * 0.42), 0, 1);
            var lit = Math.round(wp * words.length);
            for (var w = 0; w < words.length; w++) {
                var on = w < lit;
                if (on !== words[w].classList.contains('is-lit')) {
                    words[w].classList.toggle('is-lit', on);
                }
            }
        }
    }

    function request() {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(update);
        }
    }

    function reset() { measure(); request(); }

    window.addEventListener('scroll', request, { passive: true });
    window.addEventListener('resize', reset, { passive: true });
    window.addEventListener('pageshow', reset);
    window.addEventListener('load', reset);
    measure();
    update();
}());
