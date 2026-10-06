(function (root) {
    'use strict';
    root.SierraReportSubmission = function (options) {
        let state = options.initial, deadline = 0, pending = false, busy = false;
        let nextCheck = Date.now() + 30000, previousRemaining = 0;
        const original = options.button.innerHTML;
        function apply(next) {
            state = next;
            deadline = Date.now() + Math.max(0, Number(next.retry_after) || 0) * 1000;
            previousRemaining = Math.ceil((deadline - Date.now()) / 1000);
            render();
        }
        function render() {
            const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
            options.button.disabled = busy || !state.allowed;
            if (!busy) options.button.innerHTML = original;
            options.node.hidden = !!state.allowed;
            options.node.textContent = state.message || 'Checking when you can submit again…';
            if (remaining) {
                const hours = Math.floor(remaining / 3600);
                const minutes = Math.floor((remaining % 3600) / 60);
                options.node.textContent += ' ' + (hours ? hours + ':' : '') + String(minutes).padStart(2, '0') + ':' + String(remaining % 60).padStart(2, '0');
            }
            if (previousRemaining > 0 && remaining === 0 && !state.allowed) nextCheck = 0;
            previousRemaining = remaining;
        }
        async function refresh() {
            if (pending) return;
            pending = true;
            nextCheck = Date.now() + 30000;
            try {
                const response = await fetch(options.url, {credentials:'same-origin', cache:'no-store'});
                if (!response.ok) throw new Error('Availability request failed');
                const next = await response.json();
                if (typeof next.allowed !== 'boolean') throw new Error('Invalid availability response');
                apply(next);
            } catch (error) {
                if (!state.allowed && deadline <= Date.now()) options.node.textContent = 'Checking availability. Please wait…';
            } finally { pending = false; }
        }
        apply(state);
        const timer = setInterval(function () { render(); if (Date.now() >= nextCheck && !busy) refresh(); }, 1000);
        const visible = function () { if (!document.hidden) refresh(); };
        document.addEventListener('visibilitychange', visible);
        root.addEventListener('pagehide', function () { clearInterval(timer); document.removeEventListener('visibilitychange', visible); }, {once:true});
        return { canSubmit: () => state.allowed && !busy,
            setBusy: function (value) { busy = value; options.button.setAttribute('aria-busy', String(value)); render(); }, refresh: refresh };
    };
}(window));
