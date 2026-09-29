<?php
// views/shared/profile/activity_logs.php - Activity Logs section partial.
// Shows the signed-in user's own activity across the website (reports,
// supports, profile changes, login/logout, etc.). Read-only.
// Expected variables available in the parent view: $db, $user_id.

// Make sure the activity_logs table exists (auto-created on first use).
if (class_exists('ActivityLog')) {
    new ActivityLog($db);
}

$al_user_id = (int)$user_id;
$al_limit = 12;
$al_page = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$al_offset = ($al_page - 1) * $al_limit;

// Category (chip) filter: 'all' shows every action.
$al_cat_raw = $_GET['cat'] ?? 'all';
if (!in_array($al_cat_raw, ['all', 'reports', 'account', 'auth'], true)) $al_cat_raw = 'all';

$al_cat_labels = ['all' => 'All', 'reports' => 'Reports', 'account' => 'Account', 'auth' => 'Sign In / Out'];
$al_cat_icons = ['all' => 'fa-th-large', 'reports' => 'fa-file-alt', 'account' => 'fa-user-cog', 'auth' => 'fa-sign-in-alt'];
$al_cat_actions = [
    'reports' => ['Create Report', 'Support Report', 'Verify Report', 'Cancel Report', 'Delete Report', 'Add Note', 'Evidence Upload', 'Email Receipt', 'Export Report'],
    'account' => ['Change Password', 'Update Email', 'Update Phone', 'Update Profile Photo'],
    'auth'    => ['Login', 'Logout', 'User Registration', 'Password Reset'],
];

$al_activities = [];
$al_total_rows = 0;
$al_cat_counts = ['all' => 0, 'reports' => 0, 'account' => 0, 'auth' => 0];

try {
    $al_where = "user_id = ?";
    $al_args = [$al_user_id];
    if ($al_cat_raw !== 'all') {
        $al_actions = $al_cat_actions[$al_cat_raw];
        $al_where .= " AND action IN (" . implode(',', array_fill(0, count($al_actions), '?')) . ")";
        $al_args = array_merge($al_args, $al_actions);
    }

    $al_total_stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE $al_where");
    $al_total_stmt->execute($al_args);
    $al_total_rows = (int)$al_total_stmt->fetchColumn();

    // Clamp page numbers so stale ?pg= URLs don't produce an empty list.
    $al_pages = max(1, ceil($al_total_rows / $al_limit));
    if ($al_page > $al_pages) $al_page = $al_pages;
    $al_offset = ($al_page - 1) * $al_limit;

    // The LIMIT/OFFSET are cast integers, so it is safe to inline them.
    $al_stmt = $db->prepare(
        "SELECT action, description, ip_address, status, created_at
         FROM activity_logs
         WHERE $al_where
         ORDER BY created_at DESC
         LIMIT $al_limit OFFSET $al_offset"
    );
    $al_stmt->execute($al_args);
    $al_activities = $al_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Per-category counts (across all of this user's activity).
    $al_cat_counts['all'] = (int)$al_total_rows;
    $al_grp = $db->prepare("SELECT action, COUNT(*) AS c FROM activity_logs WHERE user_id = ? GROUP BY action");
    $al_grp->execute([$al_user_id]);
    while ($al_g = $al_grp->fetch(PDO::FETCH_ASSOC)) {
        foreach ($al_cat_actions as $al_ck => $al_acts) {
            if (in_array($al_g['action'], $al_acts, true)) {
                $al_cat_counts[$al_ck] += (int)$al_g['c'];
            }
        }
    }

    // Small stats for this user.
    $al_month_stmt = $db->prepare(
        "SELECT COUNT(*) FROM activity_logs
         WHERE user_id = ? AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
    );
    $al_month_stmt->execute([$al_user_id]);
    $al_this_month = (int)$al_month_stmt->fetchColumn();

    $al_reports_stmt = $db->prepare(
        "SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND action = 'Create Report'"
    );
    $al_reports_stmt->execute([$al_user_id]);
    $al_reports_filed = (int)$al_reports_stmt->fetchColumn();
} catch (Exception $e) {
    $al_pages = 1;
    $al_this_month = 0;
    $al_reports_filed = 0;
}

// Relative time helper (prefixed to avoid collisions).
function profileActivityTimeAgo($datetime) {
    $now = time();
    $ts = strtotime($datetime);
    if (!$ts) return htmlspecialchars($datetime);
    $diff = $now - $ts;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr' . (floor($diff / 3600) > 1 ? 's' : '') . ' ago';
    if ($diff < 604800) return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' wk ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' mo ago';
    return floor($diff / 31536000) . ' yr ago';
}

// User-friendly label + icon for each known action.
$al_action_meta = [
    'Login'                 => ['icon' => 'fa-sign-in-alt',    'bg' => '#E8F0FE', 'color' => '#1D4ED8'],
    'Logout'                => ['icon' => 'fa-sign-out-alt',   'bg' => '#F1F5F9', 'color' => '#475569'],
    'User Registration'     => ['icon' => 'fa-user-plus',      'bg' => '#EDE9FE', 'color' => '#6D28D9'],
    'Password Reset'        => ['icon' => 'fa-unlock-alt',     'bg' => '#FEF3C7', 'color' => '#B45309'],
    'Create Report'         => ['icon' => 'fa-file-alt',       'bg' => '#D1FAE5', 'color' => '#047857'],
    'Support Report'        => ['icon' => 'fa-heart',          'bg' => '#FCE7F3', 'color' => '#BE185D'],
    'Verify Report'         => ['icon' => 'fa-check-double',   'bg' => '#DBEAFE', 'color' => '#1D4ED8'],
    'Cancel Report'         => ['icon' => 'fa-ban',            'bg' => '#F3F4F6', 'color' => '#4B5563'],
    'Delete Report'         => ['icon' => 'fa-trash-alt',      'bg' => '#FEE2E2', 'color' => '#B91C1C'],
    'Email Receipt'         => ['icon' => 'fa-envelope-open-text', 'bg' => '#E0E7FF', 'color' => '#4338CA'],
    'Add Note'              => ['icon' => 'fa-sticky-note',    'bg' => '#FEF3C7', 'color' => '#B45309'],
    'Change Password'       => ['icon' => 'fa-key',            'bg' => '#FEF3C7', 'color' => '#B45309'],
    'Update Email'          => ['icon' => 'fa-envelope',       'bg' => '#DBEAFE', 'color' => '#1D4ED8'],
    'Update Phone'          => ['icon' => 'fa-phone-alt',      'bg' => '#CCFBF1', 'color' => '#0F766E'],
    'Update Profile Photo'  => ['icon' => 'fa-camera',         'bg' => '#FCE7F3', 'color' => '#BE185D'],
    'Export Report'         => ['icon' => 'fa-file-export',    'bg' => '#EDE9FE', 'color' => '#6D28D9'],
    'Evidence Upload'       => ['icon' => 'fa-cloud-upload-alt', 'bg' => '#DBEAFE', 'color' => '#1D4ED8'],
];
$al_action_default = ['icon' => 'fa-circle', 'bg' => '#E8F5F0', 'color' => '#10A37F'];
?>
<style>
    .al-timeline { position: relative; }
    .al-timeline .al-connector {
        position: absolute;
        left: 17px;
        top: 36px;
        bottom: 0;
        width: 2px;
        background: linear-gradient(to bottom, #D9F0E6, #EEF5F0);
    }
    @media (min-width: 640px) {
        .al-timeline .al-connector { left: 19px; }
    }
    .al-item .al-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
    }
    @media (min-width: 640px) {
        .al-item .al-icon { width: 40px; height: 40px; font-size: 0.9rem; }
    }
    .al-item.al-last .al-connector { display: none; }
    .al-time-ago { font-weight: 600; color: #10A37F; }

    /* Activity filter chips (notification-style pills) */
    .al-chip-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        background: #fff;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        margin-bottom: 16px;
    }
    .al-chip-label {
        font-size: 0.7rem;
        font-weight: 700;
        color: #9CA3AF;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-right: 2px;
    }
    .al-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 9999px;
        font-size: 0.74rem;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
        border: 1px solid #E5E7EB;
        background: #F3F4F6;
        color: #6B7280;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    .al-chip i { font-size: 0.62rem; opacity: 0.85; }
    .al-chip:hover {
        border-color: #10A37F;
        color: #10A37F;
        background: #E8F5F0;
    }
    .al-chip.active {
        background: #10A37F;
        border-color: #10A37F;
        color: #fff;
        box-shadow: 0 2px 8px rgba(16, 163, 127, 0.25);
    }
    .al-chip-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.25);
        font-size: 0.58rem;
        font-weight: 700;
    }
    .al-chip:not(.active) .al-chip-count {
        background: #E5E7EB;
        color: #6B7280;
    }
    @media (max-width: 640px) {
        .al-chip-bar {
            flex-wrap: nowrap;
            justify-content: flex-start;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .al-chip-bar::-webkit-scrollbar { display: none; }
        .al-chip-label { flex-shrink: 0; }
        .al-chip { flex: 0 0 auto; }
    }
</style>

<div id="section-activity-log">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <i class="fas fa-history text-[#10A37F]"></i>
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider"><?php echo t('Activity Logs'); ?></h3>
        </div>
        <span class="text-xs text-gray-400">Only your own actions are shown here</span>
    </div>

    <!-- Activity filter chips -->
    <div class="al-chip-bar" id="alChipBar">
        <span class="al-chip-label">Filter</span>
        <?php foreach ($al_cat_labels as $al_ck => $al_cl):
            $al_c_active = ($al_ck === $al_cat_raw);
            $al_c_count = (int)($al_cat_counts[$al_ck] ?? 0);
            if (!$al_c_active && $al_c_count <= 0) continue;
        ?>
        <a href="<?php echo BASE_URL; ?>index.php?page=profile&section=activity-log&cat=<?php echo urlencode($al_ck); ?>"
           class="al-chip<?php echo $al_c_active ? ' active' : ''; ?>" data-cat="<?php echo htmlspecialchars($al_ck, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas <?php echo $al_cat_icons[$al_ck]; ?>"></i>
            <?php echo $al_cl; ?>
            <span class="al-chip-count"><?php echo (int)$al_c_count; ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-white rounded-lg border border-gray-100 p-3 sm:p-4">
            <p class="text-[10px] sm:text-xs text-gray-400 uppercase tracking-wider font-semibold mb-1"><?php echo t('Total Activities'); ?></p>
            <p class="text-lg sm:text-xl font-extrabold text-gray-800 tracking-tight"><?php echo number_format($al_total_rows); ?></p>
        </div>
        <div class="bg-white rounded-lg border border-gray-100 p-3 sm:p-4">
            <p class="text-[10px] sm:text-xs text-gray-400 uppercase tracking-wider font-semibold mb-1"><?php echo t('This Month'); ?></p>
            <p class="text-lg sm:text-xl font-extrabold text-[#10A37F] tracking-tight"><?php echo number_format($al_this_month ?? 0); ?></p>
        </div>
        <div class="bg-white rounded-lg border border-gray-100 p-3 sm:p-4">
            <p class="text-[10px] sm:text-xs text-gray-400 uppercase tracking-wider font-semibold mb-1"><?php echo t('Reports Filed'); ?></p>
            <p class="text-lg sm:text-xl font-extrabold text-blue-600 tracking-tight"><?php echo number_format($al_reports_filed ?? 0); ?></p>
        </div>
    </div>

    <?php if (!empty($al_activities)): ?>
        <!-- Timeline -->
        <div class="al-timeline">
            <div class="al-connector"></div>
            <?php $al_count = count($al_activities); foreach ($al_activities as $al_i => $al_act):
                $al_meta = $al_action_meta[$al_act['action']] ?? $al_action_default;
                $al_bad = ($al_act['status'] ?? 'SUCCESS') !== 'SUCCESS';
            ?>
            <div class="al-item flex gap-3 sm:gap-4 relative <?php echo $al_i === $al_count - 1 ? 'al-last' : ''; ?>">
                <div class="al-icon" style="background: <?php echo $al_meta['bg']; ?>; color: <?php echo $al_meta['color']; ?>;">
                    <i class="fas <?php echo $al_meta['icon']; ?>"></i>
                </div>
                <div class="flex-1 pb-5">
                    <div class="flex flex-wrap items-center justify-between gap-1">
                        <p class="text-sm font-semibold text-gray-800">
                            <?php echo htmlspecialchars($al_act['action']); ?>
                            <?php if ($al_bad): ?>
                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-md text-[9px] font-bold text-red-600 bg-red-100 uppercase tracking-wide"><?php echo htmlspecialchars($al_act['status']); ?></span>
                            <?php endif; ?>
                        </p>
                        <span class="text-xs text-gray-400" title="<?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($al_act['created_at']))); ?>">
                            <i class="fas fa-clock mr-1 opacity-60"></i><span class="al-time-ago"><?php echo profileActivityTimeAgo($al_act['created_at']); ?></span>
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mt-0.5 leading-relaxed"><?php echo htmlspecialchars($al_act['description'] ?: 'No additional details'); ?></p>
                    <?php if (!empty($al_act['ip_address']) && $al_act['ip_address'] !== 'unknown'): ?>
                    <p class="text-[11px] text-gray-400 mt-1"><i class="fas fa-map-marker-alt mr-1 opacity-60"></i>IP <?php echo htmlspecialchars($al_act['ip_address']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($al_pages > 1): ?>
        <div class="flex flex-wrap justify-center gap-2 mt-4">
            <?php for ($al_p = 1; $al_p <= $al_pages; $al_p++): ?>
            <a href="<?php echo BASE_URL; ?>index.php?page=profile&section=activity-log&cat=<?php echo urlencode($al_cat_raw); ?>&pg=<?php echo $al_p; ?>"
               class="inline-flex items-center justify-center min-w-[2rem] h-8 px-2 text-sm rounded-lg border transition
               <?php echo $al_p === $al_page ? 'bg-[#10A37F] text-white border-[#10A37F] font-bold' : 'bg-white text-gray-700 border-gray-200 hover:border-[#10A37F] hover:text-[#10A37F]'; ?>">
                <?php echo $al_p; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Empty state -->
        <div class="text-center py-10 sm:py-14 bg-white rounded-xl border border-gray-100">
            <div class="w-14 h-14 sm:w-16 sm:h-16 bg-[#E8F5F0] rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-history text-xl text-[#10A37F]"></i>
            </div>
            <h4 class="font-semibold text-gray-700 mb-1">No activity yet</h4>
            <p class="text-xs sm:text-sm text-gray-400 max-w-xs mx-auto">Your actions across the site — like filing a report or updating your profile — will appear here.</p>
        </div>
    <?php endif; ?>
</div>