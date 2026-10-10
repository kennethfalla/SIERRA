(function () {
    'use strict';
    var LIVE_URL = document.currentScript.getAttribute('data-live-url');
    var POLL_MS = 60000;
    var TOAST_MS = 6000;   // how long each toast stays on screen

    var baselineSeq = null;
    var lastUnread = -1;
    var polling = false;
    var seenIds = {};

    function timeAgo(ts) {
        var d = new Date(String(ts).replace(/-/g, '/').replace(/\.\d+/, ''));
        if (isNaN(d.getTime())) return '';
        var s = Math.floor((Date.now() - d.getTime()) / 1000);
        if (s < 60) return 'just now';
        var m = Math.floor(s / 60); if (m < 60) return m + 'm ago';
        var h = Math.floor(m / 60); if (h < 24) return h + 'h ago';
        var dd = Math.floor(h / 24); return dd + 'd ago';
    }

    function updateBadge(unread) {
        var badge = document.getElementById('notificationBadge');
        if (unread > 0) {
            if (!badge) {
                var bell = document.querySelector('.notification-bell, .rt-bell');
                if (!bell) return;
                badge = document.createElement('span');
                badge.id = 'notificationBadge';
                badge.className = 'notification-badge';
                bell.appendChild(badge);
            }
            badge.textContent = unread > 9 ? '9+' : unread;
            badge.style.display = '';
        } else if (badge) {
            badge.style.display = 'none';
        }
    }

    function showToast(latest) {
        if (!latest || !latest.title) return;
        var key = latest.id || (latest.title + latest.created_at);
        if (seenIds[key]) return;
        seenIds[key] = true;

        // stack toasts, newest on top, centered at the top of the page
        var wrapper = document.getElementById('rtToastWrapper');
        if (!wrapper) {
            wrapper = document.createElement('div');
            wrapper.id = 'rtToastWrapper';
            wrapper.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:1000000;display:flex;flex-direction:column;gap:10px;width:min(92vw,420px);pointer-events:none;';
            document.body.appendChild(wrapper);
        }

        var toast = document.createElement('div');
        toast.setAttribute('role', 'alert');
        toast.style.cssText = 'pointer-events:auto;display:flex;align-items:flex-start;gap:12px;background:#ffffff;border:1px solid #e2e8f0;border-left:4px solid ' + (latest.color || '#10A37F') + ';border-radius:12px;box-shadow:0 14px 40px rgba(0,0,0,.18);padding:14px 16px;font-family:inherit;cursor:pointer;opacity:0;transform:translateY(-16px);animation:rtToastIn .35s cubic-bezier(.16,1,.3,1) forwards;';

        var icon = document.createElement('div');
        icon.style.cssText = 'width:38px;height:38px;border-radius:12px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:' + (latest.color || '#10A37F') + '1f;';
        var i = document.createElement('i');
        i.className = 'fas ' + (latest.icon || 'fa-bell');
        i.style.cssText = 'color:' + (latest.color || '#10A37F') + ';font-size:1.05rem;';
        icon.appendChild(i);

        var body = document.createElement('div');
        body.style.cssText = 'flex:1;min-width:0;';

        var t = document.createElement('div');
        t.style.cssText = 'font-weight:700;font-size:0.85rem;color:#111827;line-height:1.3;margin-bottom:2px;';
        t.textContent = latest.title;

        var m = document.createElement('div');
        m.style.cssText = 'font-size:0.78rem;color:#6B7280;line-height:1.4;margin-bottom:4px;word-wrap:break-word;';
        m.textContent = latest.message;

        var meta = document.createElement('div');
        meta.style.cssText = 'font-size:0.68rem;color:#6B7280;display:flex;align-items:center;gap:4px;';
        var ci = document.createElement('i');
        ci.className = 'far fa-clock';
        ci.style.fontSize = '0.68rem';
        meta.appendChild(ci);
        meta.appendChild(document.createTextNode(' ' + timeAgo(latest.created_at)));

        body.appendChild(t);
        body.appendChild(m);
        body.appendChild(meta);

        var close = document.createElement('button');
        close.innerHTML = '&times;';
        close.style.cssText = 'flex-shrink:0;border:none;background:transparent;color:#6B7280;font-size:1.1rem;line-height:1;cursor:pointer;padding:0 2px;';
        close.addEventListener('click', function (e) { e.stopPropagation(); dismiss(toast); });

        toast.appendChild(icon);
        toast.appendChild(body);
        toast.appendChild(close);

        if (latest.link) {
            toast.addEventListener('click', function () { window.location.href = latest.link; });
        }
        wrapper.appendChild(toast);

        setTimeout(function () { dismiss(toast); }, TOAST_MS);
    }

    function dismiss(toast) {
        if (!toast || !toast.parentNode) return;
        toast.style.transition = 'opacity .28s ease, transform .28s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-16px)';
        setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 280);
    }

    var timer = null;
    var nextAt = 0;
    var failures = 0;
    var stopped = false;
    var activeRequest = null;

    function available() { return !document.hidden && navigator.onLine !== false; }

    function schedule(delay) {
        clearTimeout(timer);
        if (stopped || !available() || polling) return;
        timer = setTimeout(tick, Math.max(delay || 0, nextAt - Date.now()));
    }

    function tick() {
        if (stopped || !available() || polling) return;
        if (Date.now() < nextAt) { schedule(0); return; }
        polling = true;
        activeRequest = new AbortController();
        var timeout = setTimeout(function () { activeRequest.abort(); }, 15000);
        var liveUrl = LIVE_URL;
        if (document.getElementById('reportFollowups')) liveUrl += (liveUrl.indexOf('?') === -1 ? '?' : '&') + 'dashboard_reminders=1';
        fetch(liveUrl, { method: 'GET', credentials: 'same-origin', cache: 'no-store', signal: activeRequest.signal })
            .then(function (r) {
                if (r.status === 401 || r.status === 403) { stopped = true; }
                if (!r.ok) throw new Error('Notification request failed');
                return r.json();
            })
            .then(function (data) {
                if (!data || data.success !== true) throw new Error('Invalid notification response');
                failures = 0;
                var unread = parseInt(data.unread, 10) || 0;
                updateBadge(unread);
                if (window.SierraUI && data.sidebar_counts) window.SierraUI.updateSidebarCounts(data.sidebar_counts);
                if (data.report_reminders) document.dispatchEvent(new CustomEvent('sierra:report-reminders', {detail: data.report_reminders}));
                if (baselineSeq !== null && unread > lastUnread && data.latest) showToast(data.latest);
                baselineSeq = data.notif_seq;
                lastUnread = unread;
            })
            .catch(function () { failures = Math.min(failures + 1, 3); })
            .then(function () {
                clearTimeout(timeout);
                activeRequest = null;
                polling = false;
                // 60 seconds normally; 2, 4, then 5 minutes during an outage.
                nextAt = Date.now() + Math.min(POLL_MS * Math.pow(2, failures), 300000);
                schedule(0);
            });
    }

    function availabilityChanged() {
        clearTimeout(timer);
        if (available()) schedule(0);
    }
    document.addEventListener('visibilitychange', availabilityChanged);
    window.addEventListener('online', availabilityChanged);
    window.addEventListener('offline', availabilityChanged);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { schedule(2500); });
    } else {
        schedule(2500);
    }
})();
