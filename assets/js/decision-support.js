(function decisionSupportPopover() {
    const popover = document.getElementById('decisionSupportPopover');
    const content = document.getElementById('decisionSupportContent');
    const closeButton = document.getElementById('decisionSupportClose');
    const buttons = Array.from(document.querySelectorAll('.rec-info-btn'));
    const stacks = Array.from(document.querySelectorAll('.rec-stack'));
    if (!popover || !content || !closeButton) return;

    const emptyMessages = {
        'rec-demo': 'No demographic outreach threshold is currently flagged. Keep monitoring local participation.',
        'rec-perf': 'No category performance issue is currently flagged. Review resolution rates as new reports arrive.',
        'rec-demographics': 'No demographic outreach threshold is currently flagged. Keep monitoring participation from both groups.',
        'rec-leaderboard': 'No barangay performance issue is currently flagged. Review the resolution rates as new reports arrive.',
        'rec-seasonal': 'No unusual seasonal surge is currently flagged. Continue monitoring monthly report trends.',
        'rec-repeat': 'No repeat-offender location is currently identified. Continue monitoring recurring incident areas.'
    };

    let activeButton = null;
    let pinned = false;
    let closeTimer = null;
    let discoveryTimer = null;
    let discoveryIndex = -1;
    const supportsHover = window.matchMedia('(hover: hover) and (pointer: fine)');

    function cancelClose() {
        if (closeTimer) clearTimeout(closeTimer);
        closeTimer = null;
    }

    function close(returnFocus) {
        cancelClose();
        const previousButton = activeButton;
        popover.hidden = true;
        activeButton = null;
        pinned = false;
        buttons.forEach(function (button) {
            button.classList.remove('active');
            button.setAttribute('aria-expanded', 'false');
        });
        if (returnFocus && previousButton) previousButton.focus();
    }

    function rotateDiscovery() {
        buttons.forEach(function (button) { button.classList.remove('rec-discovery'); });
        if (document.hidden || activeButton || buttons.length === 0) return;
        discoveryIndex = (discoveryIndex + 1) % buttons.length;
        buttons[discoveryIndex].classList.add('rec-discovery');
    }

    function startDiscovery() {
        if (discoveryTimer || buttons.length === 0) return;
        rotateDiscovery();
        discoveryTimer = setInterval(rotateDiscovery, 4200);
    }

    function position() {
        if (!activeButton || popover.hidden) return;
        const rect = activeButton.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        if (rect.bottom < 0 || rect.top > viewportHeight) { close(false); return; }
        const width = popover.offsetWidth;
        const height = popover.offsetHeight;
        const left = Math.max(12, Math.min(rect.left, viewportWidth - width - 12));
        let top = rect.bottom + 10;
        if (top + height > viewportHeight - 12) top = rect.top - height - 10;
        top = Math.max(12, Math.min(top, viewportHeight - height - 12));
        popover.style.left = left + 'px';
        popover.style.top = top + 'px';
    }

    function show(button, pin) {
        cancelClose();
        const source = stacks.find(function (stack) { return stack.dataset.rec === button.dataset.rec; });
        if (source && source.querySelector('.rec-box')) {
            content.innerHTML = source.innerHTML;
        } else {
            const empty = document.createElement('div');
            empty.className = 'rec-box rec-low';
            empty.textContent = emptyMessages[button.dataset.rec] || 'No specific recommendation is available for this chart yet. Check again as reports are updated.';
            content.replaceChildren(empty);
        }
        activeButton = button;
        pinned = pin;
        buttons.forEach(function (item) { item.classList.remove('rec-discovery'); });
        popover.hidden = false;
        buttons.forEach(function (item) {
            const active = item === button;
            item.classList.toggle('active', active);
            item.setAttribute('aria-expanded', active ? 'true' : 'false');
        });
        position();
    }

    function scheduleClose() {
        if (pinned) return;
        cancelClose();
        closeTimer = setTimeout(function () { close(false); }, 180);
    }

    buttons.forEach(function (button) {
        button.setAttribute('aria-controls', 'decisionSupportPopover');
        button.setAttribute('aria-expanded', 'false');
        button.removeAttribute('title');
        button.setAttribute('aria-label', 'Decision support');
        button.addEventListener('mouseenter', function () {
            if (supportsHover.matches && !pinned) show(button, false);
        });
        button.addEventListener('mouseleave', scheduleClose);
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            if (activeButton === button && pinned) close(false);
            else show(button, true);
        });
    });

    popover.addEventListener('mouseenter', cancelClose);
    popover.addEventListener('mouseleave', scheduleClose);
    closeButton.addEventListener('click', function () { close(true); });
    document.addEventListener('pointerdown', function (event) {
        if (activeButton && !popover.contains(event.target) && !event.target.closest('.rec-info-btn')) close(false);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && activeButton) close(true);
    });
    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, true);
    document.addEventListener('visibilitychange', function () {
        buttons.forEach(function (button) { button.classList.remove('rec-discovery'); });
        if (!document.hidden && !activeButton) rotateDiscovery();
    });
    startDiscovery();
})();
