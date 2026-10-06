(function () {
    'use strict';
    const sidebar = document.querySelector('.filter-sidebar');
    if (!sidebar) return;
    sidebar.id = 'exportFilterSidebar';
    const bar = document.createElement('div');
    bar.className = 'export-mobile-controls';
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'btn-apply';
    trigger.textContent = 'Filter report';
    trigger.setAttribute('aria-controls', sidebar.id);
    trigger.setAttribute('aria-expanded', 'false');
    const overlay = document.createElement('button');
    overlay.type = 'button'; overlay.className = 'export-filter-backdrop'; overlay.setAttribute('aria-label', 'Close report filters'); overlay.hidden = true;
    const close = document.createElement('button');
    close.type = 'button'; close.className = 'export-filter-close'; close.textContent = '×'; close.setAttribute('aria-label', 'Close report filters');
    const media = window.matchMedia('(max-width:900px)');
    let previousOverflow = '';
    function toggle(open) {
        if (open) previousOverflow = document.body.style.overflow;
        sidebar.classList.toggle('export-filter-open', open);
        overlay.hidden = !open;
        trigger.setAttribute('aria-expanded', String(open));
        document.body.style.overflow = open ? 'hidden' : previousOverflow;
        if (open) { sidebar.setAttribute('role', 'dialog'); sidebar.setAttribute('aria-modal', 'true'); close.focus(); }
        else { sidebar.removeAttribute('role'); sidebar.removeAttribute('aria-modal'); trigger.focus(); }
    }
    trigger.addEventListener('click', () => toggle(true));
    close.addEventListener('click', () => toggle(false));
    overlay.addEventListener('click', () => toggle(false));
    document.addEventListener('keydown', function (event) {
        if (!sidebar.classList.contains('export-filter-open')) return;
        if (event.key === 'Escape') toggle(false);
        if (event.key === 'Tab') {
            const controls = Array.from(sidebar.querySelectorAll('button,a,input,select,textarea')).filter(el => !el.disabled && el.getClientRects().length);
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    media.addEventListener('change', () => { if (!media.matches && sidebar.classList.contains('export-filter-open')) toggle(false); });
    bar.appendChild(trigger); document.body.insertBefore(bar, document.body.firstChild);
    document.body.appendChild(overlay); sidebar.querySelector('.sidebar-head').appendChild(close);
    sidebar.setAttribute('aria-label', 'Report filters');
}());
