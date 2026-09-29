/**
 * modal-a11y.js — lightweight WCAG-focused dialog behaviour for any
 * element marked role="dialog" or data-modal="1".
 *
 * - Gives every dialog an accessible name (aria-labelledby its first heading)
 * - Moves focus into the dialog when it opens and back to the trigger on close
 * - Traps Tab/Shift+Tab inside the dialog while it is open
 *
 * It observes DOM/attribute changes, so it works with existing inline
 * onclick open/close functions without modifying them.
 */
(function () {
    if (window.__MODAL_A11Y_INSTALLED) return;
    window.__MODAL_A11Y_INSTALLED = true;

    var FOCUSABLE = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function candidates() {
        return Array.prototype.slice.call(document.querySelectorAll('[role="dialog"], [data-modal]'));
    }

    function isVisible(m) {
        if (m.hidden) return false;
        if (m.getAttribute('aria-hidden') === 'true') return false;
        var s = window.getComputedStyle(m);
        if (s.display === 'none') return false;
        if (s.visibility === 'hidden') return false;
        return true;
    }

    function firstFocusable(m) {
        var els = m.querySelectorAll(FOCUSABLE);
        return els.length ? els[0] : null;
    }

    function nameDialog(m) {
        if (m.getAttribute('aria-label')) return;
        if (m.getAttribute('aria-labelledby')) return;
        var h = m.querySelector('h1, h2, h3, h4, [class*="modal-title"], [class*="ModalTitle"]');
        if (h) {
            if (!h.id) h.id = 'm-a11y-title-' + Math.random().toString(36).slice(2, 9);
            m.setAttribute('aria-labelledby', h.id);
        }
    }

    function trapTab(m) {
        var last = null, reported = false, era = m.__m11y__ || 0;
        if (m.__m11yTrap && m.__m11yEra === era) return;
        m.__m11yEra = era;
        if (m.__m11yTrap) m.removeEventListener('keydown', m.__m11yTrap);
        last = m.querySelectorAll(FOCUSABLE);
        m.__m11yTrap = function (e) {
            if (e.key !== 'Tab') return;
            if (!last || !last.length) return;
            var first = last[0], end = last[last.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === m)) {
                e.preventDefault();
                end.focus();
            } else if (!e.shiftKey && (document.activeElement === end)) {
                e.preventDefault();
                first.focus();
            }
        };
        reported = true;
        m.addEventListener('keydown', m.__m11yTrap);
    }

    function onOpen(m) {
        nameDialog(m);
        if (!m.hasAttribute('role')) m.setAttribute('role', 'dialog');
        if (m.getAttribute('aria-modal') !== 'true') m.setAttribute('aria-modal', 'true');
        if (!m._m11yTrigger) m._m11yTrigger = document.activeElement;
        trapTab(m);
        var first = firstFocusable(m);
        if (first) {
            try { first.focus(); } catch (e) {}
        } else if (!m.contains(document.activeElement)) {
            m.setAttribute('tabindex', '-1');
            try { m.focus(); } catch (e) {}
        }
    }

    function onClose(m) {
        var t = m._m11yTrigger;
        m._m11yTrigger = null;
        if (t && t.isConnected && typeof t.focus === 'function') {
            try { t.focus(); } catch (e) {}
        }
    }

    function sync(initial) {
        candidates().forEach(function (m) {
            var visible = isVisible(m);
            if (visible && !m._m11yVisible) {
                m._m11yVisible = true;
                onOpen(m);
            } else if (!visible && m._m11yVisible) {
                m._m11yVisible = false;
                onClose(m);
            }
        });
    }

    // Seed the initial state on load (dialogs start hidden).
    try {
        candidates().forEach(function (m) { m._m11yVisible = isVisible(m); });
    } catch (e) {}

    var raf = null;
    var mo = new MutationObserver(function () {
        if (raf) return;
        raf = window.requestAnimationFrame(function () {
            raf = null;
            sync(false);
        });
    });
    mo.observe(document.body, {
        attributes: true,
        childList: true,
        subtree: true,
        attributeFilter: ['class', 'style', 'hidden', 'aria-hidden']
    });
})();