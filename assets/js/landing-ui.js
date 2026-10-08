/* Landing interactions share the existing authentication routes and forms. */
(function () {
    'use strict';
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    var nav = document.querySelector('.nav-landing');
    var toggle = document.getElementById('navToggle');
    var menu = document.getElementById('mobileNavMenu');
    function closeMenu() {
        if (!nav || !menu) return;
        nav.classList.remove('menu-open'); menu.classList.add('hidden');
        document.documentElement.classList.remove('landing-menu-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<i class="fas fa-bars" aria-hidden="true"></i>';
    }
    if (nav && toggle && menu) {
        toggle.addEventListener('click', function () {
            if (nav.classList.contains('menu-open')) { closeMenu(); return; }
            menu.classList.remove('hidden'); nav.classList.add('menu-open');
            document.documentElement.classList.add('landing-menu-open');
            toggle.setAttribute('aria-expanded', 'true');
            toggle.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
        });
        menu.querySelectorAll('a').forEach(function (link) { link.addEventListener('click', closeMenu); });
        window.addEventListener('resize', function () { if (innerWidth >= 1280) closeMenu(); });
        document.addEventListener('keydown', function (event) {
            if (!nav.classList.contains('menu-open')) return;
            if (event.key === 'Escape') { closeMenu(); toggle.focus(); }
            if (event.key !== 'Tab') return;
            var controls = Array.from(nav.querySelectorAll('a,button,input')).filter(function (el) { return el.getClientRects().length && !el.disabled; });
            var first = controls[0], last = controls[controls.length-1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
    }

    var dialog = document.getElementById('landingAuthDialog');
    var frame = dialog && dialog.querySelector('iframe');
    var opener = null;
    if (dialog && frame && typeof dialog.showModal === 'function') {
        window.addEventListener('message', function (event) {
            if (event.origin !== location.origin || event.source !== frame.contentWindow || !event.data) return;
            if (event.data.type === 'sierra:auth-overlay' && typeof event.data.open === 'boolean') {
                dialog.querySelector('[data-auth-close]').style.visibility = event.data.open ? 'hidden' : '';
                return;
            }
            if (event.data.type !== 'sierra:auth-submit') return;
            if (window.SierraDashboardLoading) window.SierraDashboardLoading.show(true);
            document.documentElement.classList.add('auth-transitioning');
        });
        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[href]');
            if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target === '_blank') return;
            var url = new URL(link.href, location.href);
            var page = url.searchParams.get('page');
            if (url.origin !== location.origin || !['login','register'].includes(page)) return;
            event.preventDefault(); closeMenu(); opener = link;
            frame.title = page === 'login' ? 'Sign in to SIERRA' : 'Create a SIERRA account';
            dialog.dataset.loading = 'true';
            frame.src = url.href;
            dialog.showModal();
            document.documentElement.classList.add('auth-dialog-open');
        });
        dialog.querySelector('[data-auth-close]').addEventListener('click', function () { dialog.close(); });
        dialog.addEventListener('click', function (event) {
            if (event.target !== dialog) return;
            var rect = dialog.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
        });
        dialog.addEventListener('close', function () {
            dialog.querySelector('[data-auth-close]').style.visibility = '';
            document.documentElement.classList.remove('auth-dialog-open');
            frame.src = 'about:blank';
            document.documentElement.classList.remove('auth-transitioning');
            if (opener && opener.isConnected) opener.focus();
        });
        frame.addEventListener('load', function () {
            if (!dialog.open) return;
            dialog.querySelector('[data-auth-close]').style.visibility = '';
            dialog.dataset.loading = 'false';
            try {
                var url = new URL(frame.contentWindow.location.href);
                if (url.protocol === 'about:') return;
                frame.title = url.searchParams.get('page') === 'register' ? 'Create a SIERRA account' : 'Sign in to SIERRA';
                // Successful sign-in opens the dashboard in the main window.
                if (url.searchParams.get('page') === 'dashboard') {
                    if (window.SierraDashboardLoading) window.SierraDashboardLoading.show(true);
                    document.documentElement.classList.add('auth-transitioning');
                    location.replace(url.href); return;
                }
                if (window.SierraDashboardLoading) window.SierraDashboardLoading.hide();
                document.documentElement.classList.remove('auth-transitioning');
                var doc = frame.contentDocument;
                if (doc.querySelector('#home')) { dialog.close(); return; }
                doc.documentElement.classList.add('auth-embedded');
                doc.querySelectorAll('a[href]').forEach(function (link) {
                    var href = new URL(link.href, url.href);
                    if (!['http:','https:'].includes(href.protocol)) return;
                    if (href.origin !== location.origin || ['terms','privacy','legal','terms-of-service','privacy-policy'].includes(href.searchParams.get('page'))) link.target = '_blank';
                });
            } catch (ignore) { /* Normal links remain available if a host blocks embedding. */ }
        });
    }

    // Animate a measured answer height; keep `open` until closing has finished.
    var faqs = Array.from(document.querySelectorAll('#faq details.faq-item'));
    var animations = new WeakMap();
    function animate(item, opening) {
        var answer = item.querySelector('.faq-a');
        var current = animations.get(item);
        var from = item.open ? answer.getBoundingClientRect().height : 0;
        if (current) current.cancel();
        item.dataset.expanded = String(opening);
        item.querySelector('summary').setAttribute('aria-expanded', String(opening));
        item.open = true;
        var target = opening ? answer.scrollHeight : 0;
        if (reduced.matches || !answer.animate) { item.open = opening; return; }
        var motion = answer.animate([{height:from+'px',opacity:from ? 1 : 0},{height:target+'px',opacity:opening ? 1 : 0}], {duration:300,easing:'cubic-bezier(.22,1,.36,1)'});
        animations.set(item, motion);
        motion.onfinish = function () { item.open = opening; animations.delete(item); };
    }
    faqs.forEach(function (item) {
        item.open = false; item.dataset.expanded = 'false';
        var summary = item.querySelector('summary'); summary.setAttribute('aria-expanded','false');
        summary.addEventListener('click', function (event) {
            event.preventDefault();
            var opening = item.dataset.expanded !== 'true';
            if (opening) faqs.forEach(function (other) { if (other !== item && other.dataset.expanded === 'true') animate(other,false); });
            animate(item,opening);
        });
    });
}());
