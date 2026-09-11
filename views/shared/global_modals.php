<?php
// views/shared/global_modals.php
// Single, on-brand replacement for the native browser confirm()/alert() dialogs.
// Provides:
//   GB.confirm({title, message, confirmText, cancelText, confirmType, onConfirm, onCancel})
//   GB.alert({title, message, type})               type: 'info' | 'success' | 'error' | 'warning'
//   GB.closeError()                                 alias for closing any open modal
// Plus a delegated auto-upgrader that converts every inline
//   onsubmit="return confirm('...')"  /  onclick="return confirm('...')"
// into a branded modal with zero per-file changes.
?>
<!-- ===== GLOBAL BRANDED CONFIRM MODAL ===== -->
<div id="gbConfirmOverlay" class="gb-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="gbConfirmTitle">
    <div class="gb-modal-card" role="document">
        <div class="gb-modal-icon gb-icon-warning" id="gbConfirmIcon"><i class="fas fa-triangle-exclamation"></i></div>
        <p class="gb-modal-title" id="gbConfirmTitle">Please confirm</p>
        <p class="gb-modal-msg" id="gbConfirmMsg"></p>
        <div class="gb-modal-actions">
            <button type="button" class="gb-btn gb-btn-danger" id="gbConfirmOk">Yes, continue</button>
            <button type="button" class="gb-btn gb-btn-cancel" id="gbConfirmCancel">Cancel</button>
        </div>
    </div>
</div>

<!-- ===== GLOBAL BRANDED ALERT MODAL ===== -->
<div id="gbAlertOverlay" class="gb-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="gbAlertTitle">
    <div class="gb-modal-card" role="document">
        <div class="gb-modal-icon gb-icon-info" id="gbAlertIcon"><i class="fas fa-circle-info"></i></div>
        <p class="gb-modal-title" id="gbAlertTitle">Notice</p>
        <p class="gb-modal-msg" id="gbAlertMsg"></p>
        <div class="gb-modal-actions">
            <button type="button" class="gb-btn gb-btn-primary" id="gbAlertOk">OK</button>
        </div>
    </div>
</div>

<style>
    /* ===== GLOBAL MODALS ===== */
    .gb-modal-overlay {
        position: fixed; inset: 0; z-index: 100500;
        background: rgba(15, 23, 20, 0.55);
        backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
        display: none; align-items: center; justify-content: center;
        padding: 1rem; animation: gbFadeIn 0.15s ease;
    }
    .gb-modal-overlay.open { display: flex; }
    .gb-modal-card {
        background: #fff; border-radius: 1rem; padding: 1.6rem;
        max-width: 400px; width: 100%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.28);
        animation: gbFadeUp 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        text-align: center;
    }
    .gb-modal-icon {
        width: 48px; height: 48px; border-radius: 9999px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; margin: 0 auto 14px;
        transition: background 0.3s ease, color 0.3s ease;
    }
    .gb-icon-warning { background: #FEE2E2; color: #DC2626; }
    .gb-icon-danger  { background: #FEE2E2; color: #DC2626; }
    .gb-icon-info    { background: #E0F2E9; color: #10A37F; }
    .gb-icon-success { background: #D1FAE5; color: #059669; }
    .gb-icon-error   { background: #FEE2E2; color: #DC2626; }
    .gb-modal-title {
        font-weight: 700; color: #1F2937; font-size: 1.05rem; margin-bottom: 6px;
    }
    .gb-modal-msg {
        color: #6B7280; font-size: 0.875rem; line-height: 1.55; margin-bottom: 1.4rem;
        word-wrap: break-word; overflow-wrap: anywhere; white-space: pre-line;
    }
    .gb-modal-actions { display: flex; gap: 0.75rem; }
    .gb-modal-actions .gb-btn { flex: 1; }
    .gb-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        border: none; cursor: pointer; border-radius: 0.7rem;
        padding: 0.6rem 1rem; font-size: 0.85rem; font-weight: 600;
        transition: all 0.2s ease; font-family: inherit;
    }
    .gb-btn-danger  { background: linear-gradient(135deg, #DC2626, #B91C1C); color: #fff; box-shadow: 0 2px 8px rgba(220,38,38,0.2); }
    .gb-btn-danger:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(220,38,38,0.32); }
    .gb-btn-primary { background: linear-gradient(135deg, #10A37F, #0D8568); color: #fff; box-shadow: 0 2px 8px rgba(16,163,127,0.24); }
    .gb-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(16,163,127,0.34); }
    .gb-btn-cancel  { background: #fff; color: #4B5563; border: 1px solid #E5E7EB; }
    .gb-btn-cancel:hover { background: #F9FAFB; }
    @keyframes gbFadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes gbFadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    @media (max-width: 640px) {
        .gb-modal-card { padding: 1.25rem; }
        .gb-modal-actions { flex-direction: column-reverse; }
    }
</style>

<script>
(function () {
    'use strict';
    if (window.GB && window.GB._installed) return;

    var overlayC = null, overlayA = null;

    function $(id) { return document.getElementById(id); }

    function ensureDom() {
        if (overlayC) return;
        overlayC = $('gbConfirmOverlay');
        overlayA = $('gbAlertOverlay');
    }

    // ---------- PUBLIC API ----------
    function confirm(opts) {
        opts = opts || {};
        ensureDom();
        if (!overlayC) return;
        var danger = opts.confirmType === 'danger' || opts.confirmType === 'error' ||
                     (opts.confirmType == null && /delete|cancel|deactivate|suspend|remove|clear|undo|overwrite|permanently|irreversibl|cannot be undone|destroy/i.test(opts.message || ''));
        var icon = $('gbConfirmIcon');
        icon.className = 'gb-modal-icon ' + (danger ? 'gb-icon-danger' : 'gb-icon-warning');
        icon.innerHTML = '<i class="fas ' + (danger ? 'fa-triangle-exclamation' : 'fa-circle-question') + '"></i>';
        $('gbConfirmTitle').textContent = opts.title || 'Please confirm';
        $('gbConfirmMsg').textContent = opts.message || '';
        $('gbConfirmOk').textContent = opts.confirmText || (danger ? 'Yes, continue' : 'Confirm');
        $('gbConfirmOk').className = 'gb-btn ' + (danger ? 'gb-btn-danger' : 'gb-btn-primary');
        $('gbConfirmCancel').textContent = opts.cancelText || 'Cancel';
        overlayC._onConfirm = opts.onConfirm || null;
        overlayC._onCancel  = opts.onCancel  || null;
        overlayC.classList.add('open');
        document.body.style.overflow = 'hidden';
        $('gbConfirmOk').focus();
    }

    function alert(opts) {
        opts = opts || {};
        ensureDom();
        if (!overlayA) return;
        var type = opts.type || 'info';
        var map = {
            info:    ['gb-icon-info',    'fa-circle-info'],
            success: ['gb-icon-success', 'fa-circle-check'],
            error:   ['gb-icon-error',   'fa-circle-exclamation'],
            warning: ['gb-icon-warning', 'fa-triangle-exclamation']
        };
        var conf = map[type] || map.info;
        var icon = $('gbAlertIcon');
        icon.className = 'gb-modal-icon ' + conf[0];
        icon.innerHTML = '<i class="fas ' + conf[1] + '"></i>';
        $('gbAlertTitle').textContent = opts.title || 'Notice';
        $('gbAlertMsg').textContent = opts.message || '';
        overlayA._onClose = opts.onClose || opts.onOk || null;
        overlayA.classList.add('open');
        document.body.style.overflow = 'hidden';
        $('gbAlertOk').focus();
    }

    function closeAll() {
        ensureDom();
        if (overlayC && overlayC.classList.contains('open')) {
            overlayC.classList.remove('open');
            if (overlayC._onCancel) { var cb = overlayC._onCancel; overlayC._onCancel = null; cb(); }
        }
        if (overlayA && overlayA.classList.contains('open')) {
            overlayA.classList.remove('open');
            if (overlayA._onClose) { var c2 = overlayA._onClose; overlayA._onClose = null; c2(); }
        }
        if (document.body) document.body.style.overflow = '';
    }

    function proceedConfirm() {
        ensureDom();
        if (!overlayC || !overlayC.classList.contains('open')) return;
        overlayC.classList.remove('open');
        var cb = overlayC._onConfirm; overlayC._onConfirm = null; overlayC._onCancel = null;
        if (document.body) document.body.style.overflow = '';
        if (cb) cb();
    }

    function confirmCancel() {
        ensureDom();
        if (!overlayC || !overlayC.classList.contains('open')) return;
        overlayC.classList.remove('open');
        var cb = overlayC._onCancel; overlayC._onCancel = null; overlayC._onConfirm = null;
        if (document.body) document.body.style.overflow = '';
        if (cb) cb();
    }

    function alertOk() {
        ensureDom();
        if (!overlayA || !overlayA.classList.contains('open')) return;
        overlayA.classList.remove('open');
        var cb = overlayA._onClose; overlayA._onClose = null;
        if (document.body) document.body.style.overflow = '';
        if (cb) cb();
    }

    // ---------- HELPERS FOR CALLERS ----------
    // Ask the user to confirm, then run `action()` if they agree.
    function ask(message, action, opts) {
        var o = opts || {};
        o.message = message;
        o.onConfirm = action;
        confirm(o);
    }

    // ---------- AUTO-UPGRADE INLINE confirm() HANDLERS ----------
    function parseConfirmAttr(attr) {
        var m = /confirm\(\s*(['"])((?:\\.|(?!\1)[\s\S])*)\1\s*\)/.exec(attr || '');
        if (!m) return null;
        return m[2].replace(/\\(['"\\])/g, '$1');
    }
    function isPureConfirmHandler(attr) {
        return /^\s*return\s+confirm\([\s\S]*\)\s*;?\s*$/.test(attr || '');
    }

    // Forms with onsubmit="return confirm('...')"
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.nodeName !== 'FORM') return;
        if (form.getAttribute('data-gb-approved') === '1') { form.removeAttribute('data-gb-approved'); return; }
        var attr = form.getAttribute('onsubmit');
        if (!attr || attr.indexOf('confirm(') === -1) return;
        if (!isPureConfirmHandler(attr)) return;
        var msg = parseConfirmAttr(attr);
        if (msg === null) return;
        e.preventDefault();
        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
        confirm({
            message: msg,
            onConfirm: function () {
                form.removeAttribute('onsubmit');
                form.setAttribute('data-gb-approved', '1');
                try { form.requestSubmit(); } catch (err) { form.submit(); }
            }
        });
    }, true);

    // Buttons/links with onclick="return confirm('...')"
    document.addEventListener('click', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('[onclick*="confirm("]') : null;
        if (!el) return;
        if (el.getAttribute('data-gb-approved') === '1') { el.removeAttribute('data-gb-approved'); return; }
        var attr = el.getAttribute('onclick');
        if (!isPureConfirmHandler(attr)) return;
        var msg = parseConfirmAttr(attr);
        if (msg === null) return;
        e.preventDefault();
        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
        confirm({
            message: msg,
            onConfirm: function () {
                el.removeAttribute('onclick');
                el.setAttribute('data-gb-approved', '1');
                el.click();
            }
        });
    }, true);

    // Bind buttons
    document.addEventListener('DOMContentLoaded', function () {
        var ok = $('gbConfirmOk');    if (ok) ok.addEventListener('click', proceedConfirm);
        var cc = $('gbConfirmCancel'); if (cc) cc.addEventListener('click', confirmCancel);
        var ao = $('gbAlertOk');      if (ao) ao.addEventListener('click', alertOk);
        if (overlayC) overlayC.addEventListener('click', function (e) { if (e.target === overlayC) confirmCancel(); });
        if (overlayA) overlayA.addEventListener('click', function (e) { if (e.target === overlayA) alertOk(); });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            ensureDom();
            if (overlayC && overlayC.classList.contains('open')) confirmCancel();
            else if (overlayA && overlayA.classList.contains('open')) alertOk();
        });
    });

    window.GB = window.GB || {};
    window.GB.confirm = confirm;
    window.GB.alert = alert;
    window.GB.ask = ask;
    window.GB.closeModals = closeAll;
    window.GB._installed = true;
})();
</script>

<?php
// ============================================================
// INACTIVITY AUTO-LOGOUT WATCHDOG (Settings > Security)
// The admin sets 'session_timeout_hours'; users are signed out
// automatically after that many hours of real inactivity.
// We track real browser interaction (mouse/keyboard/touch/scroll)
// because the notification poll keeps the server-side last_activity
// fresh in an open tab - so this client watchdog is what catches
// an *open but idle* tab. Server-side config.php handles the
// closed-browser case. Both read the same setting.
// ============================================================
$__idle_hours   = (float) SettingsHelper::get('session_timeout_hours', 2);
$__idle_seconds = $__idle_hours > 0 ? (int) round($__idle_hours * 3600) : 0;
if (isLoggedIn() && $__idle_seconds > 0):
?>
<script>
(function () {
    'use strict';
    var IDLE_MS    = <?php echo (int)$__idle_seconds; ?> * 1000;
    var IDLE_URL   = '<?php echo BASE_URL; ?>controllers/AuthController.php?action=logout&reason=inactivity';
    var lastActive = Date.now();

    function poke() { lastActive = Date.now(); }

    ['mousemove', 'mousedown', 'keydown', 'touchstart', 'touchmove', 'click', 'scroll', 'wheel']
        .forEach(function (ev) {
            document.addEventListener(ev, poke, { passive: true, capture: true });
        });

    // Switching back to this tab counts as activity (user is clearly here).
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) poke();
    });

    setInterval(function () {
        if (document.hidden) return;               // don't count time in background tabs
        if (Date.now() - lastActive >= IDLE_MS) {  // idle long enough -> automatic logout
            window.location.href = IDLE_URL;
        }
    }, 30000);
})();
</script>
<?php endif; ?>