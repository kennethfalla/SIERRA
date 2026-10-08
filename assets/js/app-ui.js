/* Shared feedback and field presentation. Page handlers still own each action. */
(function () {
    'use strict';
    if (window.SierraUI) return;
    var pending = new Map();
    var action = null;

    function begin(button) {
        if (!button || !button.isConnected) return;
        var state = pending.get(button);
        if (!state) {
            state = { count: 0, busy: button.getAttribute('aria-busy') };
            pending.set(button, state);
            button.classList.add('app-is-loading');
            button.setAttribute('aria-busy', 'true');
        }
        state.count++;
    }
    function end(button) {
        var state = pending.get(button);
        if (!state || --state.count > 0) return;
        button.classList.remove('app-is-loading');
        if (state.busy === null) button.removeAttribute('aria-busy');
        else button.setAttribute('aria-busy', state.busy);
        pending.delete(button);
    }
    function updateSidebarCounts(counts) {
        if (!counts) return;
        document.querySelectorAll('[data-sidebar-count]').forEach(function (badge) {
            var count = Math.max(0, parseInt(counts[badge.dataset.sidebarCount], 10) || 0);
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count === 0;
            badge.setAttribute('aria-label', count + ' ' + (badge.dataset.countLabel || 'unread updates'));
        });
    }

    // Capture the initiator without changing its content, name, value or disabled
    // state: existing forms and confirmation handlers continue to work normally.
    document.addEventListener('click', function (event) {
        var target = event.target.closest && event.target.closest('button, a, input[type="submit"], input[type="button"]');
        if (!target) return;
        if (pending.has(target)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        action = target;
        setTimeout(function () { if (action === target) action = null; }, 0);
        if (target.tagName === 'A') {
            setTimeout(function () {
                if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0 || target.hasAttribute('download') || (target.target && target.target !== '_self')) return;
                var href = target.getAttribute('href') || '';
                if (!href || href[0] === '#' || /^(javascript:|mailto:|tel:)/i.test(href)) return;
                var url = new URL(target.href, location.href);
                if (url.origin === location.origin && (url.pathname !== location.pathname || url.search !== location.search)) begin(target);
            }, 0);
        }
    }, true);
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var button = event.submitter || form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
        action = button;
        setTimeout(function () { if (action === button) action = null; }, 0);
        setTimeout(function () {
            if (!event.defaultPrevented && (!form.target || form.target === '_self')) begin(button);
        }, 0);
    }, true);
    // Validated page workflows sometimes finish with form.submit(), which does
    // not emit a submit event. Preserve native submission and its form data.
    var originalSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        var button = (!this.target || this.target === '_self') ? action || this.querySelector('button[type="submit"], input[type="submit"]') : null;
        begin(button);
        try { return originalSubmit.apply(this, arguments); }
        catch (error) { if (button) end(button); throw error; }
    };

    var originalFetch = window.fetch;
    if (originalFetch) window.fetch = function () {
        var button = action;
        var result;
        if (button) begin(button);
        try { result = originalFetch.apply(this, arguments); }
        catch (error) { if (button) end(button); throw error; }
        var released = false;
        var bodyStarted = false;
        function release() {
            if (!released) { released = true; setTimeout(function () { if (button) end(button); }, 0); }
        }
        return result.then(function (response) {
            // JSON responses also carry updated sidebar counts after mark/read/delete.
            ['json', 'text', 'blob', 'arrayBuffer', 'formData'].forEach(function (method) {
                var original = response[method];
                if (!original) return;
                response[method] = function () {
                    bodyStarted = true;
                    return original.apply(this, arguments).then(function (value) {
                        if (method === 'json' && value && value.sidebar_counts) updateSidebarCounts(value.sidebar_counts);
                        if (method === 'text' && typeof value === 'string' && value[0] === '{') {
                            try { var data = JSON.parse(value); if (data.sidebar_counts) updateSidebarCounts(data.sidebar_counts); } catch (ignore) {}
                        }
                        release();
                        return value;
                    }, function (error) { release(); throw error; });
                };
            });
            // A caller need not consume the body (e.g. fire-and-forget actions).
            setTimeout(function () { if (!bodyStarted) release(); }, 0);
            return response;
        }, function (error) { release(); throw error; });
    };
    if (window.XMLHttpRequest) {
        var originalSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function () {
            var button = action;
            if (button) {
                begin(button);
                this.addEventListener('loadend', function () { end(button); }, { once: true });
            }
            try { return originalSend.apply(this, arguments); }
            catch (error) { if (button) end(button); throw error; }
        };
    }
    window.addEventListener('pageshow', function () {
        pending.forEach(function (state, button) { state.count = 1; end(button); });
    });

    function enhanceFields(root) {
        root.querySelectorAll('input, textarea').forEach(function (field) {
            // Select2 owns its search input and moves it when its menu opens.
            if (field.classList.contains('select2-search__field')) return;
            if (field.dataset.uiEnhanced) return;
            field.dataset.uiEnhanced = '1';
            var search = field.type === 'search' || field.name === 'search' || /search/i.test(field.id) || field.classList.contains('table-search') || field.closest('.toolbar-search,.search-box,.search-bar,.search-wrap');
            if (search) {
                field.classList.add('app-search-input');
                // A real SVG stays visible when a page overrides input backgrounds
                // or the host disallows data URLs. Keep the existing input intact.
                var wrap = document.createElement('span');
                wrap.className = 'app-search-field';
                field.parentNode.insertBefore(wrap, field);
                wrap.appendChild(field);
                var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                icon.setAttribute('viewBox', '0 0 24 24');
                icon.setAttribute('aria-hidden', 'true');
                icon.setAttribute('focusable', 'false');
                icon.classList.add('app-search-icon');
                var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                circle.setAttribute('cx', '10.5'); circle.setAttribute('cy', '10.5'); circle.setAttribute('r', '7');
                var handle = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                handle.setAttribute('d', 'm16 16 5 5');
                icon.appendChild(circle); icon.appendChild(handle); wrap.appendChild(icon);
                if (!field.getAttribute('aria-label')) field.setAttribute('aria-label', field.placeholder || 'Search');
                if (!field.placeholder || /^Search\.\.\.$/.test(field.placeholder)) field.placeholder = 'Search records…';
            }
            if (field.placeholder.trim() || !/^(text|email|tel|password|url|number|textarea)$/.test(field.type)) return;
            var label = field.labels && field.labels[0];
            var text = label ? label.textContent.replace(/\*/g, '').trim().replace(/\s+/g, ' ') : '';
            var hints = {
                first_name: 'Enter first name', last_name: 'Enter last name', email: 'name@example.com',
                contact_number: '09XXXXXXXXX', mobile: '09XXXXXXXXX', mobileInput: '09XXXXXXXXX',
                password: 'Enter your password', confirm_password: 'Confirm your password', confirmPwd: 'Confirm your password',
                purok_street: 'Enter purok or street', non_resident_address: 'Enter your complete address',
                title: 'Enter a short title', description: 'Describe the issue and its impact',
                content: 'Share a community update', phoneOtpInput: 'Enter 6-digit code', emailConfirmTokenInput: 'Enter verification code'
            };
            var hint = hints[field.name] || hints[field.id] || (text ? (field.tagName === 'TEXTAREA' ? 'Add ' : 'Enter ') + text.toLowerCase() : '');
            if (hint) field.placeholder = hint;
        });
    }
    function responsiveHeader() {
        var header = document.querySelector('.app-mobile-header');
        if (!header) return;
        var menu = header.querySelector('#showSidebarBtn');
        var title = header.querySelector('.app-page-title-wrap');
        var actions = header.querySelector('.app-header-actions');
        if (!menu || !title || !actions) return;
        function placeMenu() {
            if (window.innerWidth < 1024) { if (menu.parentNode !== title) title.insertBefore(menu, title.firstChild); }
            else if (menu.parentNode !== actions) actions.appendChild(menu);
        }
        placeMenu();
        window.addEventListener('resize', placeMenu);
    }
    function mobileSearch() {
        var header = document.querySelector('.app-mobile-header');
        if (!header || header.querySelector('.app-header-search')) return;
        var field = Array.from(document.querySelectorAll('.app-search-field')).find(function (wrap) {
            return !wrap.closest('dialog,[role="dialog"],.modal,.modal-overlay,.filter-popover,.cs-list,.cs-options,.dropdown-menu,.date-range-popover') && wrap.getClientRects().length;
        });
        var actions = header.querySelector('.app-header-actions');
        if (!field || !actions) return;
        var input = field.querySelector('input');
        if (!input) return;
        field.classList.add('app-mobile-search-field');
        if (!field.id) field.id = 'appMobileSearchField';
        var source = field.closest('.toolbar-search,.search-box,.search-bar,.search-wrap');
        if (source) source.classList.add('app-mobile-search-source');
        var button = document.createElement('button');
        button.type = 'button'; button.className = 'app-header-search';
        button.setAttribute('aria-label','Search this page'); button.setAttribute('aria-expanded','false'); button.setAttribute('aria-controls',field.id);
        button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7"></circle><path d="m16 16 5 5"></path></svg>';
        var bell = actions.querySelector('.notification-bell');
        actions.insertBefore(button, bell || actions.firstChild);
        function closeSearch() { field.classList.remove('is-open'); button.setAttribute('aria-expanded','false'); }
        button.addEventListener('click', function () {
            var open = field.classList.toggle('is-open'); button.setAttribute('aria-expanded',String(open));
            if (open) input.focus();
        });
        document.addEventListener('click', function(event) { if (!field.contains(event.target) && !button.contains(event.target)) closeSearch(); });
        input.addEventListener('keydown', function(event) { if (event.key === 'Escape') { closeSearch(); button.focus(); } });
        window.addEventListener('resize', function() { if (window.innerWidth >= 768) closeSearch(); });
        // Keep the actual field in its form: native Enter submission, page
        // event delegation and hidden filter values continue to work.
    }
    function init() {
        enhanceFields(document);
        responsiveHeader();
        mobileSearch();
        var observer = new MutationObserver(function (records) {
            records.forEach(function (record) {
                record.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1 || !node.isConnected) return;
                    if (node.matches('input,textarea')) enhanceFields(node.parentElement);
                    else enhanceFields(node);
                });
            });
            mobileSearch();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
    window.SierraUI = { begin: begin, end: end, updateSidebarCounts: updateSidebarCounts };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
