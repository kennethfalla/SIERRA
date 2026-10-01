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
        body { background: #F5FBF6; overflow-x: hidden; }

        @media (max-width: 768px) {
            .ml-72 { margin-left: 0 !important; width: 100%; padding: 0; }
            .sidebar-mobile { position: fixed; left: -280px; transition: left 0.3s ease; z-index: 1000; }
            .sidebar-mobile.open { left: 0; }
        }

        .main-container {
            padding: 1rem;
            max-width: 1280px;
            margin: 0 auto;
        }
        @media (min-width: 640px) { .main-container { padding: 1.5rem; } }
        @media (min-width: 768px) { .main-container { padding: 2rem; } }

        .page-title { font-size: 1.5rem; }

        .notif-card {
            background: white;
            border: 1px solid rgba(16, 163, 127, 0.1);
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            overflow: hidden;
        }

        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 0.875rem;
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .notif-item:hover { background: #F8FDFA; }
        .notif-item.unread { background: #F0FDF4; }
        .notif-item.unread:hover { background: #E8FAF0; }

        .notif-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .notif-content { flex: 1; min-width: 0; }
        .notif-title { font-weight: 600; color: #1a2e1a; font-size: 0.85rem; }
        .notif-message { color: #6B7280; font-size: 0.78rem; line-height: 1.4; margin-top: 2px; }
        .notif-time { color: #9CA3AF; font-size: 0.7rem; display: flex; align-items: center; gap: 4px; margin-top: 6px; }
        .notif-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #10A37F; flex-shrink: 0; margin-top: 4px;
        }

        .empty-state { padding: 3.5rem 1rem; text-align: center; }
        .empty-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: #F3F4F6; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
        }

        .btn-action {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.5rem 0.9rem; border-radius: 0.75rem;
            font-size: 0.75rem; font-weight: 600; cursor: pointer;
            transition: all 0.2s; border: 1px solid #E5E7EB; background: white; color: #4B5563;
        }
        .btn-action:hover { border-color: #10A37F; color: #10A37F; }
        .btn-action.danger:hover { border-color: #EF4444; color: #EF4444; }
        .btn-action:disabled { opacity: 0.6; cursor: not-allowed; }

        /* ---- Filter chips toolbar ---- */
        .notif-toolbar {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
        }
        .nt-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .nt-toolbar-clear { margin-left: auto; flex-shrink: 0; }
        .nt-chip {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 14px; border-radius: 9999px;
            font-size: 0.72rem; font-weight: 600; line-height: 1;
            border: 1px solid #E5E7EB; background: #F3F4F6; color: #6B7280;
            cursor: pointer; transition: all 0.2s ease; white-space: nowrap;
        }
        .nt-chip:hover { border-color: #10A37F; color: #10A37F; background: #F0FDF4; }
        .nt-chip.active {
            background: #10A37F; border-color: #10A37F; color: #FFFFFF;
            box-shadow: 0 2px 8px rgba(16, 163, 127, 0.25);
        }
        .nt-chip-count {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px;
            background: rgba(255, 255, 255, 0.25); font-size: 0.58rem; font-weight: 700;
        }
        .nt-chip:not(.active) .nt-chip-count { background: #E5E7EB; color: #6B7280; }
    </style>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
</head>
<body>

<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<div id="main-content" tabindex="-1" class="lg:ml-72 min-h-screen" role="main">
    <div class="main-container max-w-4xl mx-auto">

        <div class="page-header flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
            
            <div class="flex flex-wrap gap-2">
                <?php if ($unread_count > 0): ?>
                <button type="button" class="btn-action" id="markAllBtn" onclick="markAllAsRead()">
                    <i class="fas fa-check-double"></i> Mark all as read
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="notif-card" id="notifCard">
            <div class="notif-toolbar">
                <div class="nt-chips" id="ntChips">
                    <button type="button" class="nt-chip active" data-filter="all">All</button>
                    <button type="button" class="nt-chip" data-filter="unread">Unread<?php if ($unread_count > 0): ?> <span class="nt-chip-count"><?php echo $unread_count; ?></span><?php endif; ?></button>
                    <button type="button" class="nt-chip" data-filter="reports">Reports<?php if ($reports_count > 0): ?> <span class="nt-chip-count"><?php echo $reports_count; ?></span><?php endif; ?></button>
                    <button type="button" class="nt-chip" data-filter="announcements">Announcements<?php if ($announcements_count > 0): ?> <span class="nt-chip-count"><?php echo $announcements_count; ?></span><?php endif; ?></button>
                </div>
                <?php if (count($notifications) > 0): ?>
                <button type="button" class="btn-action danger nt-toolbar-clear" id="clearAllBtn" onclick="clearAllNotifications()">
                    <i class="fas fa-trash-alt"></i> Clear all
                </button>
                <?php endif; ?>
            </div>
            <div id="notifList">
                <?php if ($has_notifications): ?>
                    <?php foreach ($notifications as $notif): ?>
                    <div class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>"
                         data-link="<?php echo htmlspecialchars($notif['link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                         data-id="<?php echo (int)$notif['id']; ?>"
                         data-type="<?php echo htmlspecialchars($notif['type'] ?? 'info', ENT_QUOTES, 'UTF-8'); ?>">
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
                        <?php if (!$notif['is_read']): ?>
                        <div class="notif-dot"></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="empty-state" id="notifEmpty" style="<?php echo $has_notifications ? 'display:none' : ''; ?>">
                    <div class="empty-icon">
                        <i class="fas fa-bell-slash text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 font-medium">No notifications</p>
                    <p class="text-sm text-gray-400 mt-1">Notifications for your reports, status updates, and announcements will appear here.</p>
                </div>
            </div>
        </div>

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
            .then(function (res) { return res.json(); });
    }

    window.markAllAsRead = function () {
        var btn = document.getElementById('markAllBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Working...'; }
        post('mark_all_read').then(function (data) {
            if (data && data.success) {
                document.querySelectorAll('.notif-item').forEach(function (el) {
                    el.classList.remove('unread');
                    var dot = el.querySelector('.notif-dot');
                    if (dot) dot.remove();
                });
                var markBtn = document.getElementById('markAllBtn');
                if (markBtn) { markBtn.remove(); }
                var clearBtn = document.getElementById('clearAllBtn');
                if (clearBtn) clearBtn.remove();
                updateSummary(0, document.querySelectorAll('.notif-item').length);
                ntApply();
                showToast('All notifications marked as read.', 'success');
            } else if (data && data.error) {
                showToast(data.error, 'error');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-double"></i> Mark all as read'; }
            }
        }).catch(function () {
            showToast('Failed to mark notifications as read.', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-double"></i> Mark all as read'; }
        });
    };

    window.clearAllNotifications = function () {
        window.GB.confirm({
            message: 'Clear all notifications? This cannot be undone.',
            onConfirm: function () {
                var btn = document.getElementById('clearAllBtn');
                if (btn) { btn.disabled = true; }
                post('clear_all').then(function (data) {
                    if (data && data.success) {
                        var list = document.getElementById('notifList');
                        list.innerHTML = '<div class="empty-state">'
                            + '<div class="empty-icon"><i class="fas fa-bell-slash text-2xl text-gray-400"></i></div>'
                            + '<p class="text-gray-500 font-medium">No notifications</p>'
                            + '<p class="text-sm text-gray-400 mt-1">Notifications for your reports, status updates, and announcements will appear here.</p>'
                            + '</div>';
                        var markBtn = document.getElementById('markAllBtn');
                        if (markBtn) markBtn.remove();
                        var clearBtn = document.getElementById('clearAllBtn');
                        if (clearBtn) clearBtn.remove();
                        updateSummary(0, 0);
                        ntApply();
                        showToast('All notifications cleared.', 'success');
                    } else if (data && data.error) {
                        showToast(data.error, 'error');
                        if (btn) btn.disabled = false;
                    }
                }).catch(function () {
                    showToast('Failed to clear notifications.', 'error');
                    if (btn) btn.disabled = false;
                });
            }
        });
    };

    function updateSummary(unread, total) {
        var p = document.querySelector('.page-header p');
        if (p) {
            p.innerHTML = (unread > 0 ? unread + ' unread' : 'You are all caught up')
                + ' &middot; ' + total + ' total';
        }
    }

    // ===== Pill filter chips =====
    var ntList = document.getElementById('notifList');
    var ntChips = Array.prototype.slice.call(document.querySelectorAll('.nt-chip'));
    var activeFilter = 'all';

    function ntApply() {
        var items = ntList ? ntList.querySelectorAll('.notif-item') : [];
        var visible = 0;
        Array.prototype.forEach.call(items, function (item) {
            var show = true;
            if (activeFilter === 'unread' && !item.classList.contains('unread')) show = false;
            else if (activeFilter === 'reports' && item.getAttribute('data-type') !== 'report') show = false;
            else if (activeFilter === 'announcements' && item.getAttribute('data-type') !== 'announcement') show = false;
            item.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        var emptyEl = document.getElementById('notifEmpty');
        if (emptyEl) emptyEl.style.display = visible === 0 ? '' : 'none';
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
        item.addEventListener('click', function (e) {
            e.stopPropagation();
            var id = item.getAttribute('data-id');
            var link = item.getAttribute('data-link');
            if (id) {
                post('mark_read', { id: id }).then(function (data) {
                    if (data && data.success) {
                        item.classList.remove('unread');
                        var dot = item.querySelector('.notif-dot');
                        if (dot) dot.remove();
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
