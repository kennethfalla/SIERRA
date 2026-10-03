(function () {
    'use strict';
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (motion.matches || !window.IntersectionObserver) return;

    const candidates = Array.from(document.querySelectorAll(
        '#features > div > *, #features .feature-card, ' +
        '#map-section > div > *, #stats > div > *, #stats .stat-card, ' +
        '#about > .relative > *, #about .grid > *, #report-guide > div > *, ' +
        '#report-guide .lp-info-card, #report-guide .lp-detail-card, #faq > div > *, #faq .faq-item, footer .grid > *'
    ));
    // Animate cards individually; their grid wrapper stays in normal layout.
    const targets = candidates.filter(el => !el.classList.contains('grid') && !el.classList.contains('lp-steps-track') && !el.querySelector('.faq-item'));
    const roots = targets.filter(el => !targets.some(parent => parent !== el && parent.contains(el)));
    let observer;
    function reveal(el, immediately) {
        if (immediately) el.style.setProperty('--reveal-delay', '0ms');
        el.classList.remove('is-pending');
        el.classList.add('is-visible');
        if (observer) observer.unobserve(el);
    }
    function revealAll() {
        roots.forEach(el => reveal(el, true));
        if (observer) observer.disconnect();
    }
    try {
        observer = new IntersectionObserver(entries => {
            entries.forEach(entry => { if (entry.isIntersecting) reveal(entry.target, false); });
        }, { threshold: 0, rootMargin: '0px 0px -24px 0px' });
        roots.forEach(el => {
            el.classList.add('landing-reveal');
            const siblings = Array.from(el.parentElement.children);
            el.style.setProperty('--reveal-delay', (Math.min(siblings.indexOf(el), 3) * 70) + 'ms');
            // Keep the hero and content already in view readable on first load.
            if (el.getBoundingClientRect().top < window.innerHeight - 24) reveal(el, true);
            else { el.classList.add('is-pending'); observer.observe(el); }
        });
    } catch (error) { revealAll(); }

    document.addEventListener('focusin', event => {
        const target = event.target.closest('.landing-reveal');
        if (target) reveal(target, true);
    });
    document.addEventListener('beforematch', revealAll);
    window.addEventListener('beforeprint', revealAll);
    window.addEventListener('pageshow', () => {
        roots.forEach(el => { if (el.getBoundingClientRect().top < window.innerHeight) reveal(el, true); });
    });
    function revealAnchor() {
        let id;
        try { id = decodeURIComponent(window.location.hash.slice(1)); } catch (error) { return; }
        const anchor = document.getElementById(id);
        if (anchor) roots.filter(el => anchor.contains(el) || el.contains(anchor)).forEach(el => reveal(el, true));
    }
    window.addEventListener('hashchange', revealAnchor);
    revealAnchor();
    if (motion.addEventListener) motion.addEventListener('change', event => { if (event.matches) revealAll(); });
})();
