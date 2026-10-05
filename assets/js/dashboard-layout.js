(function () {
    'use strict';
    // Pack cards at their content height while retaining the existing column widths.
    if (!window.ResizeObserver || !document.body.classList.contains('dashboard-page')) return;
    const selector = '.analytics-grid,.menro-dash-middle,body.barangay-dashboard-page .bento';
    document.querySelectorAll(selector).forEach(grid => {
        const cards = Array.from(grid.children);
        if (!cards.length) return;
        let frame = 0;
        function layout() {
            frame = 0;
            const style = getComputedStyle(grid);
            if (style.display !== 'grid' || !grid.clientWidth) return;
            const gap = parseFloat(style.columnGap) || 14;
            cards.forEach(card => {
                if (getComputedStyle(card).display === 'none') return;
                const cardStyle = getComputedStyle(card);
                const height = Math.ceil(card.offsetHeight + parseFloat(cardStyle.marginTop || 0) + gap);
                const row = 'span ' + Math.max(1, height);
                if (card.style.gridRow !== row) card.style.gridRow = row;
            });
        }
        function schedule() { if (!frame) frame = requestAnimationFrame(layout); }
        grid.classList.add('dashboard-packed-grid');
        const observer = new ResizeObserver(schedule);
        observer.observe(grid);
        cards.forEach(card => observer.observe(card));
        window.addEventListener('resize', schedule, {passive:true});
        schedule();
    });
}());
