/* assets/js/design-select.js
   Progressive enhancement: turns <select data-design-select> into a styled
   button + listbox (a native <select> popup cannot be styled). The native
   <select> is hidden and kept in the DOM so the submitted value, 'change'
   events, validation and page JS that reads select.value / selectedIndex
   keep working unchanged.

   Self-contained: this file injects its own CSS and hides the native select
   itself, so it does NOT depend on tailwind.css being fresh (avoids the
   "native select still shows / default styling" problem when CSS is cached).

   Mirrors the native select's "error" and "category-auto-flash" classes onto
   the wrapper so existing validation / auto-correction feedback still shows.
*/
(function () {
    'use strict';

    var STYLE_ID = 'design-select-styles';
    var CSS = [
        '.cs{position:relative;width:100%;}',
        '.cs>select{position:absolute;inset:0;width:100%;height:100%;margin:0;opacity:0;pointer-events:none;}',
        '.cs-btn{display:flex;align-items:center;justify-content:space-between;gap:.5rem;width:100%;min-height:44px;',
        'padding:.6rem .9rem;font-family:inherit;font-size:.9rem;line-height:1.4;color:#1a2e1a;background:#fff;',
        'border:1.5px solid #e5ece8;border-radius:.75rem;cursor:pointer;text-align:left;',
        'transition:border-color .15s ease,box-shadow .15s ease,background-color .15s ease;}',
        '.cs-btn:hover{border-color:#10A37F;background:#f5fbf8;}',
        '.cs-btn:focus{outline:none;border-color:#10A37F;box-shadow:0 0 0 3px rgba(16,163,127,.18);}',
        '.cs-label{flex:1 1 auto;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}',
        '.cs-label.is-placeholder{color:#9ca3af;}',
        '.cs-caret{flex:0 0 auto;width:1rem;height:1rem;background-repeat:no-repeat;background-position:center;',
        'background-size:1rem 1rem;transition:transform .18s ease;',
        'background-image:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2310A37F\' stroke-width=\'2.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpolyline points=\'6 9 12 15 18 9\'/%3E%3C/svg%3E");}',
        '.cs.is-open .cs-caret{transform:rotate(180deg);}',
        '.cs.is-error .cs-btn{border-color:#DC2626;background:#FEF2F2;}',
        '.cs.is-flash .cs-btn{animation:csFlash .9s ease 2;}',
        '@keyframes csFlash{0%,100%{border-color:#e5ece8;box-shadow:none;background:#fff;}50%{border-color:#10A37F;box-shadow:0 0 0 4px rgba(16,163,127,.35);background:#D1FAE5;}}',
        '.cs-list{position:absolute;z-index:80;top:calc(100% + 6px);left:0;right:0;max-height:280px;overflow-y:auto;',
        'margin:0;padding:6px;list-style:none;background:#fff;border:1px solid #e5ece8;border-radius:0.75rem;',
        'box-shadow:0 14px 34px rgba(15,23,42,.16),0 2px 8px rgba(15,23,42,.07);animation:csIn .14s ease;}',
        '@keyframes csIn{from{opacity:0;transform:translateY(-4px);}to{opacity:1;transform:none;}}',
        '.cs-option{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.55rem .7rem;',
        'border-radius:0.5rem;font-size:.9rem;color:#1f2937;cursor:pointer;}',
        '.cs-option:hover,.cs-option.is-active{background:#eef8f4;color:#0f766e;}',
        '.cs-option.is-selected{background:#10A37F;color:#fff;}',
        '.cs-option.is-selected::after{content:"";flex:0 0 auto;width:.95rem;height:.95rem;background-repeat:no-repeat;',
        'background-position:center;background-size:.95rem .95rem;',
        'background-image:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23ffffff\' stroke-width=\'3\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpolyline points=\'20 6 9 17 4 12\'/%3E%3C/svg%3E");}'
    ].join('');

    function injectStyles() {
        if (document.getElementById(STYLE_ID)) return;
        var s = document.createElement('style');
        s.id = STYLE_ID;
        s.appendChild(document.createTextNode(CSS));
        (document.head || document.documentElement).appendChild(s);
    }

    function hideNative(native) {
        // Inline hide so the native control never shows even if the external
        // stylesheet is missing/cached. display:none still submits the value.
        native.style.position = 'absolute';
        native.style.opacity = '0';
        native.style.pointerEvents = 'none';
        native.style.width = '1px';
        native.style.height = '1px';
        native.style.margin = '0';
        native.style.padding = '0';
        native.style.border = '0';
        native.setAttribute('aria-hidden', 'true');
        native.setAttribute('tabindex', '-1');
    }

    function enhance(native) {
        if (!native || native.dataset.csEnhanced === '1') return;
        native.dataset.csEnhanced = '1';

        injectStyles();
        hideNative(native);

        var wrap = document.createElement('div');
        wrap.className = 'cs';
        native.parentNode.insertBefore(wrap, native);
        wrap.appendChild(native);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cs-btn';
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('aria-expanded', 'false');
        btn.id = (native.id || 'design-select-' + document.querySelectorAll('.cs').length) + '-button';
        var nativeLabel = native.labels && native.labels[0];
        if (nativeLabel) {
            if (!nativeLabel.id) nativeLabel.id = btn.id + '-label';
            btn.setAttribute('aria-labelledby', nativeLabel.id + ' ' + btn.id + '-value');
            nativeLabel.addEventListener('click', function (event) { event.preventDefault(); btn.focus(); });
        }

        var label = document.createElement('span');
        label.className = 'cs-label';
        label.id = btn.id + '-value';
        btn.appendChild(label);

        var caret = document.createElement('span');
        caret.className = 'cs-caret';
        caret.setAttribute('aria-hidden', 'true');
        btn.appendChild(caret);

        var list = document.createElement('ul');
        list.className = 'cs-list';
        list.setAttribute('role', 'listbox');
        list.id = btn.id + '-options';
        btn.setAttribute('aria-controls', list.id);
        list.hidden = true;

        wrap.appendChild(btn);
        wrap.appendChild(list);

        var active = 0;

        function build() {
            list.innerHTML = '';
            Array.prototype.forEach.call(native.options, function (opt, i) {
                var li = document.createElement('li');
                li.className = 'cs-option';
                li.setAttribute('role', 'option');
                li.dataset.index = String(i);
                li.id = list.id + '-' + i;
                li.setAttribute('aria-disabled', opt.disabled ? 'true' : 'false');
                li.textContent = (opt.textContent || '').replace(/\s+/g, ' ').trim();
                li.addEventListener('click', function () { choose(i); });
                list.appendChild(li);
            });
        }

        function refresh() {
            btn.disabled = native.disabled;
            var sel = native.options[native.selectedIndex];
            label.textContent = sel ? (sel.textContent || '').replace(/\s+/g, ' ').trim() : '';
            label.classList.toggle('is-placeholder', !native.value);
            Array.prototype.forEach.call(list.children, function (li, i) {
                var on = i === native.selectedIndex;
                li.classList.toggle('is-selected', on);
                li.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }

        function setActive(i) {
            var items = list.children;
            if (!items.length) return;
            active = Math.max(0, Math.min(i, items.length - 1));
            btn.setAttribute('aria-activedescendant', items[active].id);
            for (var k = 0; k < items.length; k++) items[k].classList.toggle('is-active', k === active);
            if (items[active] && items[active].scrollIntoView) items[active].scrollIntoView({ block: 'nearest' });
        }

        function open() {
            if (native.disabled) return;
            closeAllExcept(wrap);
            list.hidden = false;
            wrap.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
            setActive(native.selectedIndex >= 0 ? native.selectedIndex : 0);
        }
        function close() {
            list.hidden = true;
            wrap.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            btn.removeAttribute('aria-activedescendant');
        }
        function choose(i) {
            if (!native.options[i] || native.options[i].disabled) return;
            native.selectedIndex = i;
            native.dispatchEvent(new Event('change', { bubbles: true }));
            refresh();
            close();
            btn.focus();
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (list.hidden) open(); else close();
        });
        btn.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (list.hidden) open(); else setActive(active + 1);
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (list.hidden) open(); else choose(active);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (list.hidden) open(); else setActive(active - 1);
            } else if (e.key === 'Escape') {
                if (!list.hidden) { e.preventDefault(); close(); }
            } else if (e.key === 'Tab') {
                close();
            }
        });

        native.addEventListener('change', refresh);
        native.addEventListener('invalid', function () { wrap.classList.add('is-error'); btn.setAttribute('aria-invalid', 'true'); btn.focus(); });

        // Mirror feedback classes from the native select onto the wrapper.
        var mirror = function () {
            wrap.classList.toggle('is-error', native.classList.contains('error'));
            btn.setAttribute('aria-invalid', native.classList.contains('error') ? 'true' : 'false');
            if (native.classList.contains('category-auto-flash')) {
                wrap.classList.remove('is-flash');
                void wrap.offsetWidth; // restart the animation
                wrap.classList.add('is-flash');
            } else {
                wrap.classList.remove('is-flash');
            }
        };
        if (window.MutationObserver) {
            new MutationObserver(mirror).observe(native, { attributes: true, attributeFilter: ['class'] });
        }

        build();
        refresh();
        mirror();
    }

    function closeAllExcept(target) {
        Array.prototype.forEach.call(document.querySelectorAll('.cs.is-open'), function (w) {
            if (w === target) return;
            w.classList.remove('is-open');
            var l = w.querySelector('.cs-list'); if (l) l.hidden = true;
            var b = w.querySelector('.cs-btn'); if (b) b.setAttribute('aria-expanded', 'false');
        });
    }

    document.addEventListener('click', function (e) {
        Array.prototype.forEach.call(document.querySelectorAll('.cs.is-open'), function (w) {
            if (!w.contains(e.target)) {
                w.classList.remove('is-open');
                var l = w.querySelector('.cs-list'); if (l) l.hidden = true;
                var b = w.querySelector('.cs-btn'); if (b) b.setAttribute('aria-expanded', 'false');
            }
        });
    });

    function init(root) {
        Array.prototype.forEach.call(
            (root || document).querySelectorAll('select[data-design-select]'),
            enhance
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(document); });
    } else {
        init(document);
    }

    window.DesignSelect = { enhance: enhance, init: init, closeAll: closeAllExcept };
})();
