<?php
// views/shared/notifications.php - ALL NOTIFICATIONS PAGE (all roles)
// Lists every in-app notification for the logged-in user, with
// "Mark all as read", "Clear all", and per-item read-on-click.

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/helpers/SecurityHelper.php';

if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "views/auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'citizen';

$database = new Database();
$db = $database->getConnection();
$notifModel = new Notification($db);

$notifications = $notifModel->getForUser($user_id, 100);
$unread_count  = $notifModel->getUnreadCount($user_id);
$csrf_token    = InputSanitizer::generateCsrfToken();

$reports_count     = 0;
$announcements_count = 0;
foreach ($notifications as $n) {
    if (($n['type'] ?? '') === 'report') { $reports_count++; }
    else if (($n['type'] ?? '') === 'announcement') { $announcements_count++; }
}
$has_notifications = count($notifications) > 0;
$notification_groups = ['Today' => [], 'This Week' => [], 'Earlier' => []];
$today_start = strtotime('today');
$week_start = strtotime('monday this week');
foreach ($notifications as $notification) {
    $created = strtotime($notification['created_at']);
    $group = $created >= $today_start ? 'Today' : ($created >= $week_start ? 'This Week' : 'Earlier');
    $notification_groups[$group][] = $notification;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Notifications - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/material-symbols.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/material-symbols.css'); ?>">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: #f0f5f3; overflow-x: hidden; }

        @media (max-width: 768px) {
            .ml-72 { margin-left: 0 !important; width: 100%; padding: 0; }
            .sidebar-mobile { position: fixed; left: -280px; transition: left 0.3s ease; z-index: 1000; }
            .sidebar-mobile.open { left: 0; }
        }

        .main-container {
            padding: 1rem;
            max-width: 1440px;
            margin: 0 auto;
        }
        @media (min-width: 640px) { .main-container { padding: 1.5rem; } }
        @media (min-width: 768px) { .main-container { padding: 2rem; } }

        .notif-card { min-width: 0; }
        .notif-group { margin-top: 1.4rem; }
        .notif-group-title { margin: 0 0 .65rem .15rem; color: #7b8a86; font-size: .66rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .notif-group-list { overflow: hidden; background: #fff; border: 1px solid #e8eeeb; border-radius: 1rem; }

        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 1.1rem 1.2rem;
            border-bottom: 1px solid #f3f5f4;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .notif-item:last-child { border-bottom: 0; }
        .notif-item:hover, .notif-item:focus-within { background: #f7fbf9; }
        .notif-item:focus-visible { outline: 2px solid #10a37f; outline-offset: -3px; }
        .notif-item.unread .notif-title { color: #1e293b; }
        .notif-item:not(.unread) .notif-title { color: #5a6675; }

        .notif-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .notif-content { flex: 1; min-width: 0; }
        .notif-title { font-weight: 700; color: #1e293b; font-size: 0.85rem; }
        .notif-message { color: #85919e; font-size: 0.78rem; line-height: 1.6; margin-top: 3px; overflow-wrap: anywhere; }
        .notif-time { color: #9CA3AF; font-size: 0.7rem; display: flex; align-items: center; gap: 4px; margin-top: 6px; }
        .notif-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #10A37F; flex-shrink: 0; margin-top: 14px; box-shadow: 0 0 0 3px #e6faf1;
        }
        .notif-delete { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; flex-shrink: 0; margin-left: auto; border: 1px solid transparent; border-radius: 10px; background: transparent; color: #94a3a0; opacity: 0; cursor: pointer; transition: opacity .18s ease, background .18s ease, color .18s ease; }
        .notif-item:hover .notif-delete, .notif-item:focus-within .notif-delete { opacity: 1; }
        .notif-delete:hover { color: #dc2626; background: #fff1f2; border-color: #fecdd3; }
        .notif-delete:focus-visible { opacity: 1; outline: 2px solid #10a37f; outline-offset: 2px; }
        .notif-delete:disabled { cursor: wait; opacity: .5; }
        .notif-text-action { display: inline-flex; align-items: center; gap: .4rem; padding: .4rem .55rem; border: 0; background: transparent; color: #6e8079; font-size: .72rem; font-weight: 600; cursor: pointer; border-radius: 8px; }
        .notif-text-action:hover { background: #e5f3ed; color: #0d8568; }
        .notif-text-action.danger:hover { background: #fff1f2; color: #dc2626; }
        .notif-text-action:disabled { opacity: .4; cursor: default; }
        .notif-text-action:focus-visible { outline: 2px solid #10a37f; outline-offset: 2px; }
        .notif-caught-up { display: flex; align-items: center; justify-content: center; gap: .5rem; margin-top: 1.6rem; color: #8b9992; font-size: .72rem; }
        .notif-caught-up i { color: #10a37f; }
        .notif-group[hidden], .notif-text-action[hidden], .nt-chip-count[hidden] { display: none; }

        .empty-state { padding: 3.5rem 1rem; text-align: center; }
        .empty-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: #F3F4F6; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
        }

        /* ---- Filter chips toolbar ---- */
        .notif-toolbar {
            padding: 0;
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
        }
        .nt-chips { display: flex; flex-wrap: wrap; gap: 3px; background: white; border-radius: 999px; padding: 4px; }
        .nt-toolbar-clear { margin-left: auto; flex-shrink: 0; }
        .nt-chip {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 14px; border-radius: 9999px;
            font-size: 0.72rem; font-weight: 600; line-height: 1;
            border: 1px solid transparent; background: transparent; color: #85919e;
            cursor: pointer; transition: all 0.2s ease; white-space: nowrap;
        }
        .nt-chip:hover { border-color: #10A37F; color: #10A37F; background: #F0FDF4; }
        .nt-chip.active {
            background: #0d8568; border-color: #0d8568; color: #FFFFFF;
        }
        .nt-chip-count {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px;
            background: rgba(255, 255, 255, 0.25); font-size: 0.58rem; font-weight: 700;
        }
        .nt-chip:not(.active) .nt-chip-count { background: #e1f7ec; color: #0d8568; }
        @media (hover: none) { .notif-delete { opacity: 1; } }
        @media (max-width: 640px) {
            .notif-item { padding: 1rem .75rem; gap: .5rem; }
            .notif-dot { width: 6px; height: 6px; }
            .notif-icon { width: 30px; height: 30px; }
            .notif-title { font-size: .8rem; }
            .notif-message { font-size: .73rem; }
            .nt-chips { width: 100%; justify-content: space-between; }
            .nt-chip { padding: 6px 9px; font-size: .67rem; }
        }
    </style>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
</head>
<body>

<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<div id="main-content" tabindex="-1" class="lg:ml-72 min-h-screen" role="main">
    <div class="main-container mx-auto">

        <div class="notif-card" id="notifCard">
            <div class="notif-toolbar">
                <div class="nt-chips" id="ntChips">
                    <button type="button" class="nt-chip active" data-filter="all">All</button>
                    <button type="button" class="nt-chip" data-filter="unread">Unread<?php if ($unread_count > 0): ?> <span class="nt-chip-count"><?php echo $unread_count; ?></span><?php endif; ?></button>
                    <button type="button" class="nt-chip" data-filter="reports">Reports<?php if ($reports_count > 0): ?> <span class="nt-chip-count"><?php echo $reports_count; ?></span><?php endif; ?></button>
                    <button type="button" class="nt-chip" data-filter="announcements">Announcements<?php if ($announcements_count > 0): ?> <span class="nt-chip-count"><?php echo $announcements_count; ?></span><?php endif; ?></button>
                </div>
                <button type="button" class="notif-text-action nt-toolbar-clear" id="markAllBtn" onclick="markAllAsRead()" <?php echo $unread_count === 0 ? 'disabled' : ''; ?>>
                    <i class="fas fa-check-double"></i> Mark all as read
                </button>
            </div>
            <div id="notifList">
                <?php foreach ($notification_groups as $group_title => $group_notifications): ?>
                <?php if (!$group_notifications) continue; ?>
                <section class="notif-group" aria-label="<?php echo $group_title; ?>">
                    <h2 class="notif-group-title"><?php echo $group_title; ?></h2>
                    <div class="notif-group-list">
                    <?php foreach ($group_notifications as $notif): ?>
                    <div class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>"
                         tabindex="0"
                         data-link="<?php echo htmlspecialchars($notif['link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                         data-id="<?php echo (int)$notif['id']; ?>"
                         data-type="<?php echo htmlspecialchars($notif['type'] ?? 'info', ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if (!$notif['is_read']): ?><span class="notif-dot" aria-label="Unread"></span><?php endif; ?>
                        <div class="notif-icon" style="background: <?php echo $notif['color'] ?? '#10A37F'; ?>20;">
                            <i class="fas <?php echo $notif['icon'] ?? 'fa-bell'; ?>" style="color: <?php echo $notif['color'] ?? '#10A37F'; ?>; font-size: 1rem;"></i>
                        </div>
                        <div class="notif-content">
                            <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                            <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                            <div class="notif-time">
                                <i class="far fa-clock"></i>
                                <?php
                                    $time_diff = time() - strtotime($notif['created_at']);
                                    if ($time_diff < 60) echo "Just now";
                                    elseif ($time_diff < 3600) echo floor($time_diff / 60) . " min ago";
                                    elseif ($time_diff < 86400) echo floor($time_diff / 3600) . " hrs ago";
                                    elseif ($time_diff < 604800) echo floor($time_diff / 86400) . " days ago";
                                    else echo date('M d, Y', strtotime($notif['created_at']));
                                ?>
                            </div>
                        </div>
                        <button type="button" class="notif-delete" aria-label="Delete notification: <?php echo htmlspecialchars($notif['title'], ENT_QUOTES, 'UTF-8'); ?>" title="Delete notification"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </section>
                <?php endforeach; ?>
                <div class="empty-state" id="notifEmpty" style="<?php echo $has_notifications ? 'display:none' : ''; ?>">
                    <div class="empty-icon">
                        <i class="fas fa-bell-slash text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 font-medium">No notifications</p>
                    <p class="text-sm text-gray-400 mt-1">Notifications for your reports, status updates, and announcements will appear here.</p>
                </div>
            </div>
        </div>
        <p class="notif-caught-up" id="notifSummary" aria-live="polite"><i class="far fa-check-circle" aria-hidden="true"></i><span><?php echo $unread_count > 0 ? $unread_count . ' unread notifications' : 'You are all caught up with your notifications'; ?></span></p>

    </div>
</div>

<script>
(function () {
    'use strict';

    var BASE_URL = '<?php echo BASE_URL; ?>';

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function showToast(message, type) {
        var color = type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#3B82F6';
        var toast = document.createElement('div');
        toast.className = 'fixed top-4 right-4 z-[9999] text-white px-5 py-3 rounded-xl shadow-lg flex items-center gap-3 max-w-sm';
        toast.style.background = color;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(function () { if (toast.parentNode) toast.remove(); }, 300);
        }, 3000);
    }

    function post(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('csrf_token', getCsrfToken());
        if (data) Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        return fetch(BASE_URL + 'controllers/NotificationController.php', { method: 'POST', body: fd })
            .then(function (res) {
                return res.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (err) {
                        return { success: false, error: 'Unexpected server response. Please refresh the page and try again.' };
                    }
                });
            });
    }

    window.markAllAsRead = function () {
        var btn = document.getElementById('markAllBtn');
        if (!btn || btn.disabled) return;
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Working...'; }
        post('mark_all_read').then(function (data) {
            if (data && data.success) {
                document.querySelectorAll('.notif-item').forEach(function (el) {
                    el.classList.remove('unread');
                    var dot = el.querySelector('.notif-dot');
                    if (dot) dot.remove();
                });
                btn.innerHTML = '<i class="fas fa-check-double"></i> Mark all as read';
                updateSummary(0, document.querySelectorAll('.notif-item').length);
                ntApply();
                showToast('All notifications marked as read.', 'success');
            } else if (data && data.error) {
                showToast(data.error, 'error');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-double"></i> Mark all as read'; }
            } else {
                showToast('Failed to mark notifications as read.', 'error');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-double"></i> Mark all as read'; }
            }
        }).catch(function () {
            showToast('Failed to mark notifications as read.', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-double"></i> Mark all as read'; }
        });
    };

    function updateSummary(unread, total) {
        var markBtn = document.getElementById('markAllBtn');
        if (markBtn) markBtn.disabled = unread === 0;
        var summary = document.querySelector('#notifSummary span');
        if (summary) summary.textContent = unread > 0 ? unread + ' unread notifications' : 'You are all caught up with your notifications';
    }

    // ===== Pill filter chips =====
    var ntList = document.getElementById('notifList');
    var ntChips = Array.prototype.slice.call(document.querySelectorAll('.nt-chip'));
    var activeFilter = 'all';

    function deleteNotifications(ids, button) {
        if (!ids.length) return;
        window.GB.confirm({message: 'Delete this notification?', onConfirm: function() {
            button.disabled = true;
            post('delete_selected', {ids: JSON.stringify(ids)}).then(function(data) {
                if (!data || !data.success) throw new Error((data && data.error) || 'Unable to delete notifications.');
                document.querySelectorAll('.notif-item').forEach(function(row) { if (ids.includes(row.dataset.id)) row.remove(); });
                updateSummary(data.unread_count, document.querySelectorAll('.notif-item').length);
                ntApply();
                showToast('Notification deleted.', 'success');
            }).catch(function(error) { showToast(error.message, 'error'); }).finally(function() { button.disabled = false; });
        }});
    }
    document.querySelectorAll('.notif-delete').forEach(function(button) {
        button.addEventListener('click', function(event) {
            event.stopPropagation();
            deleteNotifications([button.closest('.notif-item').dataset.id], button);
        });
    });

    function ntApply() {
        var items = Array.prototype.slice.call(ntList ? ntList.querySelectorAll('.notif-item') : []);
        var visible = 0;
        items.forEach(function (item) {
            var show = true;
            if (activeFilter === 'unread' && !item.classList.contains('unread')) show = false;
            else if (activeFilter === 'reports' && item.getAttribute('data-type') !== 'report') show = false;
            else if (activeFilter === 'announcements' && item.getAttribute('data-type') !== 'announcement') show = false;
            item.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        var emptyEl = document.getElementById('notifEmpty');
        if (emptyEl) emptyEl.style.display = visible === 0 ? '' : 'none';
        document.querySelectorAll('.notif-group').forEach(function(group) {
            group.hidden = !Array.from(group.querySelectorAll('.notif-item')).some(function(row) { return row.style.display !== 'none'; });
        });
        ntChips.forEach(function (chip) {
            var kind = chip.getAttribute('data-filter');
            if (kind === 'all') return;
            var count = items.filter(function (row) {
                return kind === 'unread' ? row.classList.contains('unread') : row.getAttribute('data-type') === (kind === 'reports' ? 'report' : 'announcement');
            }).length;
            var badge = chip.querySelector('.nt-chip-count');
            if (badge) { badge.textContent = count; badge.hidden = count === 0; }
        });
        updateSummary(document.querySelectorAll('.notif-item.unread').length, items.length);
    }

    ntChips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            ntChips.forEach(function (c) { c.classList.remove('active'); });
            chip.classList.add('active');
            activeFilter = chip.getAttribute('data-filter');
            ntApply();
        });
    });
    window.addEventListener('load', ntApply);

    // Click a notification -> mark read + follow link
    document.querySelectorAll('.notif-item').forEach(function (item) {
        item.addEventListener('keydown', function(event) {
            if (event.target === item && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); item.click(); }
        });
        item.addEventListener('click', function (e) {
            if (e.target.closest('input,button,label')) return;
            e.stopPropagation();
            var id = item.getAttribute('data-id');
            var link = item.getAttribute('data-link');
            if (id) {
                post('mark_read', { id: id }).then(function (data) {
                    if (data && data.success) {
                        item.classList.remove('unread');
                        var dot = item.querySelector('.notif-dot');
                        if (dot) dot.remove();
                        ntApply();
                    }
                }).catch(function () {});
            }
            if (link && link !== '') {
                window.location.href = link;
            }
        });
    });
})();
</script>

</body>
</html>
