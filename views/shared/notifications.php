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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: #F5FBF6; overflow-x: hidden; }

        .main-container { padding: 1.25rem; }
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
    </style>
</head>
<body>

<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<div class="lg:ml-72 min-h-screen">
    <div class="main-container max-w-4xl mx-auto">

        <div class="page-header flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-7 h-7 md:w-8 md:h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bell text-[#10A37F] text-xs md:text-sm"></i>
                    </div>
                    <span class="text-[10px] md:text-xs uppercase tracking-wider text-[#10A37F] font-semibold">Notifications</span>
                </div>
                <h1 class="page-title font-bold text-gray-800">All Notifications</h1>
                <p class="text-sm text-gray-500 mt-1">
                    <?php echo $unread_count > 0 ? $unread_count . ' unread' : 'You are all caught up'; ?>
                    &middot; <?php echo count($notifications); ?> total
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($unread_count > 0): ?>
                <button type="button" class="btn-action" id="markAllBtn" onclick="markAllAsRead()">
                    <i class="fas fa-check-double"></i> Mark all as read
                </button>
                <?php endif; ?>
                <?php if (count($notifications) > 0): ?>
                <button type="button" class="btn-action danger" id="clearAllBtn" onclick="clearAllNotifications()">
                    <i class="fas fa-trash-alt"></i> Clear all
                </button>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" class="btn-action">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div class="notif-card" id="notifCard">
            <div id="notifList">
                <?php if (count($notifications) > 0): ?>
                    <?php foreach ($notifications as $notif): ?>
                    <div class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>"
                         data-link="<?php echo htmlspecialchars($notif['link'] ?? ''); ?>"
                         data-id="<?php echo (int)$notif['id']; ?>">
                        <div class="notif-icon" style="background: <?php echo $notif['color']; ?>20;">
                            <i class="fas <?php echo $notif['icon']; ?>" style="color: <?php echo $notif['color']; ?>; font-size: 1rem;"></i>
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
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-bell-slash text-2xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-500 font-medium">No notifications</p>
                        <p class="text-sm text-gray-400 mt-1">Notifications for your reports, status updates, and announcements will appear here.</p>
                    </div>
                <?php endif; ?>
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
        if (!confirm('Clear all notifications? This cannot be undone.')) return;
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
                showToast('All notifications cleared.', 'success');
            } else if (data && data.error) {
                showToast(data.error, 'error');
                if (btn) btn.disabled = false;
            }
        }).catch(function () {
            showToast('Failed to clear notifications.', 'error');
            if (btn) btn.disabled = false;
        });
    };

    function updateSummary(unread, total) {
        var p = document.querySelector('.page-header p');
        if (p) {
            p.innerHTML = (unread > 0 ? unread + ' unread' : 'You are all caught up')
                + ' &middot; ' + total + ' total';
        }
    }

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
