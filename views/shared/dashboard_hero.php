<?php
// One welcome banner for citizen, barangay, and MENRO dashboards.
$hero_admin = ($_SESSION['user_role'] ?? '') === 'admin';
$hero_hour = (int)(new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('G');
$hero_greeting = $hero_hour < 12 ? t('Good Morning') : ($hero_hour < 18 ? t('Good Afternoon') : t('Good Evening'));
$hero_icon = $hero_hour < 12 ? 'fa-sun' : ($hero_hour < 18 ? 'fa-cloud' : 'fa-moon');
$hero_unread = (int)($hero_admin ? $menu_unread : $unread_count);
?>
<div class="sierra-hero">
    <div class="sierra-hero-row">
        <div>
            <div class="sierra-hero-greeting">
                <i class="fas <?php echo $hero_icon; ?>" aria-hidden="true"></i>
                <span><?php echo $hero_greeting; ?></span>
            </div>
            <h1 class="sierra-hero-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="sierra-hero-subtitle"><?php echo $hero_admin ? t('Municipal Environment & Natural Resources Office') : htmlspecialchars(($_SESSION['user_role'] ?? '') === 'barangay_official' ? 'Barangay ' . ($barangay_info['name'] ?? '') . ' · Environmental response' : ($hero_subtitle ?? $current_date), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <?php if (!empty($hero_extra)): ?>
        <div class="sierra-hero-extra"><?php echo $hero_extra; ?></div>
        <?php endif; ?>
        <button type="button" id="<?php echo $hero_admin ? 'menroNotifBell' : 'notifBellBtn'; ?>"
                class="notification-bell sierra-hero-bell radius-12"
                onclick="<?php echo $hero_admin ? 'menroToggleNotifs(event)' : 'toggleNotifications()'; ?>"
                aria-label="<?php echo t('Toggle notifications'); ?>" aria-haspopup="true" aria-expanded="false"
                aria-controls="<?php echo $hero_admin ? 'menroNotifDropdown' : 'notificationDropdown'; ?>">
            <i class="fas fa-bell" aria-hidden="true"></i>
            <?php if ($hero_unread > 0): ?>
            <span class="notification-badge" id="notificationBadge"><?php echo $hero_unread > 9 ? '9+' : $hero_unread; ?></span>
            <?php endif; ?>
        </button>
    </div>
</div>
