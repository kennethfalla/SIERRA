<?php
// views/layouts/sidebar.php - UPDATED WITH TABBED SETTINGS
// Bugs fixed:
// 1. Removed duplicate logout modal (now only in sidebar)
// 2. Fixed JavaScript function conflicts
// 3. Added proper profile picture handling
// 4. Fixed avatar update selectors
// 5. Updated System Settings link to point to tabbed interface

require_once BASE_PATH . 'helpers/SettingsHelper.php';
require_once BASE_PATH . 'helpers/PermissionHelper.php';
require_once BASE_PATH . 'helpers/Lang.php';

$current_page = $_GET['page'] ?? 'dashboard';
$app_page_titles = [
    'dashboard' => 'Dashboard',
    'submit-report' => 'Submit Environmental Report',
    'my-reports' => ($_GET['tab'] ?? '') === 'supported' ? 'Reports I Supported' : 'My Reports',
    'track-status' => !empty($is_supporter) ? 'Track Supported Report' : 'Track Report',
    'all-reports' => 'All Reports',
    'verify-reports' => 'Manage Reports',
    'manage-report' => 'Manage Report',
    'manage-users' => 'User Management',
    'reporters-directory' => 'Reporters Directory',
    'audit-logs' => 'Audit Logs',
    'settings' => 'System Settings',
    'notifications' => 'All Notifications',
    'announcements' => 'Announcements',
    'profile' => 'Settings',
    'edit-profile' => 'Settings',
];
$app_page_title = t($app_page_titles[$current_page] ?? ucwords(str_replace('-', ' ', $current_page)));
$user_role = $_SESSION['user_role'] ?? 'citizen';
$user_email = $_SESSION['user_email'] ?? 'user@example.com';
$barangay_id = $_SESSION['barangay_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

// Initialize all user variables
$user_first_name = '';
$user_last_name = '';
$user_fullname = '';
$user_contact_number = '';
$user_email_address = '';
$user_role_display = '';
$barangay_name = '';
$user_created_at = '';
$user_is_active = 1;
$user_is_resident = 1;
$user_is_verified = 1;
$user_province = '';
$user_municipality = '';
$user_non_resident_address = '';
$user_purok_street = '';
$user_profile_picture = '';

// Fetch complete user data from database if user is logged in
if ($user_id) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                CONCAT(u.first_name, ' ', u.last_name) AS full_name,
                u.email,
                u.contact_number,
                u.role_id,
                u.user_type,
                u.barangay_id,
                u.is_active,
                u.created_at,
                u.updated_at,
                u.is_resident,
                u.province,
                u.municipality,
                u.non_resident_address,
                u.purok_street,
                u.is_verified,
                u.profile_picture,
                b.name as barangay_name
            FROM users u
            LEFT JOIN barangays b ON u.barangay_id = b.id
            WHERE u.id = :user_id
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            $user_first_name = $user_data['first_name'] ?? '';
            $user_last_name = $user_data['last_name'] ?? '';
            $user_fullname = $user_data['full_name'] ?? '';
            $user_email_address = $user_data['email'] ?? '';
            $user_contact_number = $user_data['contact_number'] ?? '';
            $user_type = $user_data['user_type'] ?? null;
            $user_role = roleFromUserType($user_type);
            $barangay_id = $user_data['barangay_id'] ?? null;
            $user_is_active = $user_data['is_active'] ?? 1;
            $user_created_at = $user_data['created_at'] ?? date('Y-m-d H:i:s');
            $barangay_name = $user_data['barangay_name'] ?? '';
            $user_is_resident = $user_data['is_resident'] ?? 1;
            $user_is_verified = $user_data['is_verified'] ?? 1;
            $user_province = $user_data['province'] ?? '';
            $user_municipality = $user_data['municipality'] ?? '';
            $user_non_resident_address = $user_data['non_resident_address'] ?? '';
            $user_purok_street = $user_data['purok_street'] ?? '';
            $user_profile_picture = $user_data['profile_picture'] ?? '';
            
            $_SESSION['user_name'] = $user_fullname;
            $_SESSION['user_email'] = $user_email_address;
            $_SESSION['user_contact'] = $user_contact_number;
            $_SESSION['user_role'] = $user_role;
            $_SESSION['role_id'] = $user_data['role_id'] ?? null;
            $_SESSION['user_type'] = $user_data['user_type'] ?? null;
            $_SESSION['barangay_id'] = $barangay_id;
            $_SESSION['profile_picture'] = $user_profile_picture;
        }
    } catch (Exception $e) {
        $user_fullname = $_SESSION['user_name'] ?? 'User';
        $user_email_address = $_SESSION['user_email'] ?? 'user@example.com';
        $user_contact_number = $_SESSION['user_contact'] ?? '';
        $user_created_at = date('Y-m-d H:i:s');
    }
} else {
    $user_fullname = $_SESSION['user_name'] ?? 'User';
    $user_email_address = $_SESSION['user_email'] ?? 'user@example.com';
    $user_contact_number = $_SESSION['user_contact'] ?? '';
    $user_created_at = date('Y-m-d H:i:s');
}

// If barangay name is still empty but we have barangay_id, try to fetch it
if (empty($barangay_name) && $barangay_id) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("SELECT name FROM barangays WHERE id = ?");
        $stmt->execute([$barangay_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $barangay_name = $row['name'] ?? '';
    } catch (Exception $e) {
        $barangay_name = '';
    }
}

// Format dates
$join_date = date('F Y', strtotime($user_created_at));
$member_since = date('M d, Y', strtotime($user_created_at));

// Get user display name
$display_name = $user_fullname ?: 'User';

// Get user initials for avatar
$name_parts = explode(' ', $display_name);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($display_name, 0, 2));
}

// Role display name
$role_display_name = '';
$role_badge_color = '';
$role_icon = '';
switch($user_type) {
    case 'admin':
        $role_display_name = t('Admin');
        $role_badge_color = 'bg-purple-100 text-purple-700';
        $role_icon = 'fa-building';
        break;
    case 'menro_staff':
        $role_display_name = t('MENRO Staff');
        $role_badge_color = 'bg-purple-100 text-purple-700';
        $role_icon = 'fa-building';
        break;
    case 'barangay_personnel':
        $role_display_name = t('Barangay Official');
        $role_badge_color = 'bg-emerald-100 text-emerald-700';
        $role_icon = 'fa-map-marker-alt';
        break;
    default:
        $role_display_name = isset($user_is_resident) && $user_is_resident == 0 ? t('Non-Resident') : t('Resident');
        $role_badge_color = 'bg-blue-100 text-blue-700';
        $role_icon = 'fa-user';
}

// Profile picture URL (from session or database)
$profile_pic = $_SESSION['profile_picture'] ?? $user_profile_picture ?? '';
$profile_pic_url = !empty($profile_pic) ? BASE_URL . $profile_pic : '';
?>
<!-- UPDATED: Include SettingsHelper for dynamic system name -->
<?php require_once BASE_PATH . 'helpers/SettingsHelper.php'; ?>
<?php $system_name = SettingsHelper::get('system_name', 'Sierra'); ?>

<?php
// ============================================
// NOTIFICATION BELL DATA (admin / MENRO only)
// Feeds the bell + dropdown in the sidebar header.
// ============================================
$menu_notifs = [];
$menu_unread = 0;
if ($user_id && isset($db)) {
    try {
        $sidebar_notif_model = new Notification($db);
        $menu_notifs  = $sidebar_notif_model->getForUser((int)$user_id, 8);
        $menu_unread  = $sidebar_notif_model->getUnreadCount((int)$user_id);
    } catch (Exception $e) {
        $menu_notifs = [];
        $menu_unread = 0;
    }
}
?>

<!-- Skip to main content link -->
<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[9999] focus:px-4 focus:py-2 focus:bg-emerald-600 focus:text-white focus:rounded-lg">
    <?php echo t('Skip to main content'); ?>
</a>

<style>
    /* ============================================ */
    /* SIDEBAR OVERLAY STYLES                       */
    /* ============================================ */
    body { overflow-x: hidden; }
    
    /* Sidebar hidden by default on mobile */
    #sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease-in-out;
        z-index: 50;
        box-shadow: 4px 0 20px rgba(0,0,0,0.1);
    }
    
    body.sidebar-open #sidebar {
        transform: translateX(0) !important;
    }
    
    /* ---- Top app bar (shown on every screen size; holds the bell) ---- */
    .app-mobile-header {
        display: flex;
        align-items: center;
        gap: 8px;
        position: fixed;
        top: 0; left: 0; right: 0;
        height: 56px;
        padding: 0 14px;
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        z-index: 45;
    }
    .app-mobile-brand-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        margin-right: auto;
    }
    .app-mobile-logo {
        width: 34px;
        height: 34px;
        flex-shrink: 0;
        object-fit: contain;
        border-radius: 8px;
    }
    .app-mobile-logo-fallback {
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #10A37F, #0D8568);
        color: #fff;
        font-size: 1rem;
    }
    .app-mobile-titles {
        display: flex;
        flex-direction: column;
        min-width: 0;
        line-height: 1.15;
    }
    .app-mobile-brand {
        font-weight: 800;
        font-size: 0.95rem;
        letter-spacing: .01em;
        color: #0d8568;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }
    .app-mobile-sub {
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }
    /* Hero notification bell, relocated into the mobile header */
    .app-mobile-header .sierra-hero-bell {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 42px;
        height: 42px;
        border: none;
        border-radius: 12px;
        background: transparent;
        color: #374151;
        cursor: pointer;
        transition: background-color .15s ease, color .15s ease;
    }
    .app-mobile-header .sierra-hero-bell:hover { background: #ecfdf5; color: #10A37F; }
    .app-mobile-header .sierra-hero-bell .fa-bell { font-size: 1.05rem; }
    .app-mobile-header .sierra-hero-bell:focus-visible { outline: 3px solid #0D8568; outline-offset: 2px; }
    .app-mobile-header .notification-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        background: #ef4444;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        border: 2px solid #fff;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    #showSidebarBtn.app-mobile-menu-btn {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 42px;
        height: 42px;
        border: none;
        border-radius: 12px;
        background: transparent;
        color: #374151;
        cursor: pointer;
        transition: background-color .15s ease, color .15s ease;
    }
    #showSidebarBtn.app-mobile-menu-btn svg {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 22px;
        height: 22px;
        margin: -11px 0 0 -11px;
        transition: opacity .25s ease, transform .25s ease;
    }
    #showSidebarBtn.app-mobile-menu-btn .icon-close { opacity: 0; transform: rotate(-90deg) scale(.6); }
    body.sidebar-open #showSidebarBtn.app-mobile-menu-btn .icon-menu { opacity: 0; transform: rotate(90deg) scale(.6); }
    body.sidebar-open #showSidebarBtn.app-mobile-menu-btn .icon-close { opacity: 1; transform: rotate(0) scale(1); }
    #showSidebarBtn.app-mobile-menu-btn:hover { background: #ecfdf5; color: #10A37F; }
    #showSidebarBtn.app-mobile-menu-btn:focus-visible { outline: 3px solid #0D8568; outline-offset: 2px; }
    
    /* Unified top app header: shown on every screen size. */
    body { padding-top: 56px; }
    @media (max-width: 1023px) {
        /* Mobile: the header's menu button (X) closes the sidebar, so hide the sidebar's own close button. */
        #hideSidebarBtn { display: none !important; }
    }

    /* Desktop: sidebar always visible, header sits beside it, no burger. */
    @media (min-width: 1024px) {
        body:not(.sidebar-open) #sidebar {
            transform: translateX(0) !important;
        }
        .app-mobile-header { left: 280px; }
        #showSidebarBtn { display: none !important; }
    }
    
    #sidebar .flex-1 {
        overflow-y: auto;
        scrollbar-width: thin;
    }
    
    #sidebar .flex-1::-webkit-scrollbar {
        width: 4px;
    }
    
    #sidebar .flex-1::-webkit-scrollbar-track {
        background: #f1f5f9;
    }
    
    #sidebar .flex-1::-webkit-scrollbar-thumb {
        background: #10a37f;
        border-radius: 8px;
    }
    
    /* Ensure sidebar avatar is round */
    #sidebar .w-10.h-10 img,
    #sidebar .w-10.h-10 span {
        border-radius: 50% !important;
    }
    
    #sidebar .w-10.h-10 {
        overflow: hidden;
    }

    /* ============================================ */
    /* MENRO NOTIFICATION BELL + DROPDOWN           */
    /* ============================================ */
    #menroNotifBell.notification-bell { position: relative; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all .2s; }
    #menroNotifBell.notification-bell:hover { background: #E8FAF0 !important; color: #10A37F !important; transform: scale(1.05); }

    .notification-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #EF4444;
        color: #fff;
        font-size: 9px;
        font-weight: 700;
        padding: 2px 5px;
        border-radius: 16px;
        min-width: 16px;
        text-align: center;
        line-height: 1.3;
        z-index: 10;
    }

    .menro-notif-dropdown {
        position: fixed;
        top: 82px;
        left: 12px;
        width: min(400px, calc(100vw - 24px));
        max-height: 500px;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 20px 35px -10px rgba(0,0,0,.25);
        z-index: 999990;
        display: none;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #E5E7EB;
        animation: menroSlideDown .2s ease;
    }
    .menro-notif-dropdown.show { display: flex; }
    @keyframes menroSlideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

    .menro-notif-header { padding: 14px 18px; border-bottom: 1px solid #F3F4F6; background: #FAFAFA; flex-shrink: 0; }
    .menro-notif-list { overflow-y: auto; max-height: 340px; }
    .menro-notif-item { display: flex; gap: 12px; padding: 12px 16px; border-bottom: 1px solid #F3F4F6; cursor: pointer; transition: background .2s; }
    .menro-notif-item.unread { background: #F0FDF4; }
    .menro-notif-item.unread:hover { background: #E8FAF0; }
    .menro-notif-item:last-child { border-bottom: none; }
    .menro-notif-icon { width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .menro-notif-content { flex: 1; min-width: 0; }
    .menro-notif-title { font-weight: 600; font-size: .8rem; color: #1F2937; margin-bottom: 2px; }
    .menro-notif-msg { font-size: .72rem; color: #6B7280; line-height: 1.4; margin-bottom: 4px; word-wrap: break-word; }
    .menro-notif-time { font-size: .62rem; color: #9CA3AF; display: flex; align-items: center; gap: 4px; }
    .menro-notif-dot { width: 6px; height: 6px; background: #10A37F; border-radius: 50%; flex-shrink: 0; margin-top: 6px; }
    .menro-notif-actions { display: flex; border-top: 1px solid #F3F4F6; background: #FAFAFA; }
    .menro-notif-actions button { flex: 1; text-align: center; padding: 11px 8px; font-size: .72rem; font-weight: 600; cursor: pointer; transition: all .2s; border: none; background: transparent; color: inherit; }
    .menro-notif-actions .menro-mark-all { color: #10A37F; border-right: 1px solid #F3F4F6; }
    .menro-notif-actions .menro-mark-all:hover { background: #F0FDF4; color: #0D8568; }
    .menro-notif-actions .menro-clear-all { color: #EF4444; }
    .menro-notif-actions .menro-clear-all:hover { background: #FEF2F2; color: #B91C1C; }
    .menro-notif-view-all { text-align: center; padding: 11px 16px; border-top: 1px solid #F3F4F6; background: #fff; font-size: .72rem; font-weight: 600; cursor: pointer; color: #10A37F; }
    .menro-notif-view-all:hover { background: #F0FDF4; color: #0D8568; }
    .menro-notif-empty { padding: 2.5rem 1rem; text-align: center; }

    /* ---- Filter toolbars lifted into the top app header (same as dashboards) ---- */
    body:not(.dashboard-page) #dashHeaderExtras { display: none; }
    .app-mobile-header #dashHeaderExtras {
        display: flex !important;
        align-items: center;
        min-width: 0;
        margin-left: auto;
    }
    .app-mobile-header #dashHeaderExtras .dashboard-toolbar-row,
    .app-mobile-header #dashHeaderExtras .dashboard-toolbar-filters,
    .app-mobile-header #dashHeaderExtras .ft-toolbar {
        flex: 0 0 auto;
        width: auto;
        min-width: 0;
        margin: 0;
    }
    .app-mobile-header #dashHeaderExtras .ft-toolbar .reports-toolbar,
    .app-mobile-header #dashHeaderExtras .reports-toolbar {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        min-height: 0 !important;
        height: auto !important;
        gap: .4rem;
        flex-wrap: nowrap;
        overflow-x: auto;
        scrollbar-width: none;
    }
    .app-mobile-header #dashHeaderExtras .reports-toolbar::-webkit-scrollbar { display: none; }
    .app-mobile-header #dashHeaderExtras .toolbar-select {
        min-height: 34px;
        background-color: #f4f7f5;
        border-color: transparent;
    }
    .app-mobile-header #dashHeaderExtras .toolbar-select:hover,
    .app-mobile-header #dashHeaderExtras .toolbar-select:focus {
        border-color: #cde3d7;
        background-color: #ffffff;
    }
    .app-mobile-header #dashHeaderExtras .toolbar-divider { display: none; }
    .app-mobile-header #dashHeaderExtras .toolbar-results { display: none; }
    .app-mobile-header #dashHeaderExtras .active-filters-row { display: none; }
    .app-mobile-header #dashHeaderExtras .ft-more-btn {
        width: 40px;
        height: 40px;
        min-width: 40px;
        min-height: 40px;
        background: #f4f7f5;
        border-color: transparent;
    }
    .app-mobile-header #dashHeaderExtras .toolbar-search { width: 170px; }
    .app-mobile-header #dashHeaderExtras .toolbar-search input { height: 34px; }
    .app-mobile-header #dashHeaderExtras form input[type="text"] { min-height: 34px; }
</style>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app-shell.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/app-shell.css'); ?>">

<!-- Page title, filters, notifications and account actions. -->
<header class="app-mobile-header" role="banner">
    <div class="app-page-title-wrap">
        <?php $header_logo = SettingsHelper::get('lgu_logo'); ?>
        <span class="app-page-logo" aria-hidden="true">
            <?php if ($header_logo): ?>
                <img src="<?php echo BASE_URL . $header_logo; ?>" alt="">
            <?php else: ?>
                <i class="fas fa-leaf"></i>
            <?php endif; ?>
        </span>
        <h1 class="app-page-title"><?php echo htmlspecialchars($app_page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
    <div class="app-header-actions">
        <?php if (!empty($_SESSION['user_id'])): ?>
        <button type="button" id="<?php echo ($user_role === 'admin') ? 'menroNotifBell' : 'notifBellBtn'; ?>"
                class="notification-bell sierra-hero-bell radius-12"
                onclick="<?php echo ($user_role === 'admin') ? 'menroToggleNotifs(event)' : "if (typeof toggleNotifications === 'function') toggleNotifications(); else window.location.href='" . BASE_URL . "index.php?page=notifications';"; ?>"
                aria-label="<?php echo t('Notifications'); ?>" aria-haspopup="true" aria-expanded="false"
                aria-controls="<?php echo ($user_role === 'admin') ? 'menroNotifDropdown' : 'notificationDropdown'; ?>">
            <i class="fas fa-bell" aria-hidden="true"></i>
            <?php if ($menu_unread > 0): ?>
            <span class="notification-badge" id="notificationBadge"><?php echo $menu_unread > 9 ? '9+' : $menu_unread; ?></span>
            <?php endif; ?>
        </button>
        <?php endif; ?>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="<?php echo BASE_URL; ?>index.php?page=profile" class="app-header-user">
            <span class="app-header-avatar">
                <?php if (!empty($profile_pic_url)): ?>
                    <img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo htmlspecialchars($initials); ?>
                <?php endif; ?>
            </span>
            <span class="app-header-user-txt">
                <strong><?php echo htmlspecialchars($display_name); ?></strong>
                <em><?php echo htmlspecialchars($role_display_name); ?><?php if (!empty($barangay_name)): ?> • <?php echo htmlspecialchars($barangay_name); ?><?php endif; ?></em>
            </span>
            <i class="fas fa-chevron-down app-header-chev" aria-hidden="true"></i>
        </a>
        <?php endif; ?>
        <button id="showSidebarBtn"
                type="button"
                class="app-mobile-menu-btn"
                aria-label="Toggle navigation menu"
                aria-expanded="false"
                aria-controls="sidebar"
                title="Toggle Menu">
            <svg class="icon-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
            <svg class="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <span class="sr-only">Toggle menu</span>
        </button>
    </div>
</header>

<aside id="sidebar" 
       class="fixed left-0 top-0 h-full bg-white shadow-2xl z-40 transition-all duration-300 flex flex-col"
       style="width: 280px; transform: translateX(-100%);"
       aria-label="Main navigation sidebar"
       role="navigation">
    
    <!-- Sidebar Header -->
    <div class="p-5 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center space-x-3">
            <?php 
            $logo = SettingsHelper::get('lgu_logo');
            if ($logo): ?>
                <img src="<?php echo BASE_URL . $logo; ?>" alt="LGU Logo" class="w-10 h-10 object-contain rounded-xl">
            <?php else: ?>
                <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl flex items-center justify-center shadow-md">
                    <i class="fas fa-leaf text-white text-lg"></i>
                </div>
            <?php endif; ?>
            <div>
                <!-- UPDATED: Dynamic system name -->
                <p class="text-xl font-bold bg-gradient-to-r from-emerald-700 to-teal-600 bg-clip-text text-transparent">
                    <?php echo htmlspecialchars($system_name); ?>
                </p>
                <p class="text-[10px] text-gray-500 uppercase tracking-wider"><?php echo t('Environmental Reporting'); ?></p>
            </div>
        </div>
        <div class="flex items-center gap-1.5 flex-shrink-0">
            <button id="hideSidebarBtn" 
                    class="w-10 h-10 rounded-lg bg-transparent hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-300 transition-all duration-200 flex items-center justify-center group"
                    aria-label="Close sidebar menu"
                    title="Hide Sidebar">
            <i class="material-symbols ms-close text-gray-500 group-hover:text-red-500 transition" aria-hidden="true"></i>
            <span class="sr-only">Close sidebar</span>
            </button>
            </div>
    </div>
    
    <!-- Navigation - Scrollable Area -->
    <nav class="flex-1 overflow-y-auto px-4 py-5" aria-label="Main navigation">
        
        <p class="sb-label"><?php echo t('Menu'); ?></p>

        <?php if($user_role == 'citizen'): ?>
        <!-- Citizen Section -->
        <div class="mb-6">

            <!-- Home -->
            <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'dashboard' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'dashboard' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-home text-sm <?php echo $current_page == 'dashboard' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Home'); ?></span>
                <?php if($current_page == 'dashboard'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>

            <!-- Submit Report -->
            <a href="<?php echo BASE_URL; ?>index.php?page=submit-report" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'submit-report' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'submit-report' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-plus-circle text-sm <?php echo $current_page == 'submit-report' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Submit Report'); ?></span>
                <?php if($current_page == 'submit-report'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
            <!-- My Reports -->
            <a href="<?php echo BASE_URL; ?>index.php?page=my-reports" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'my-reports' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'my-reports' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-list text-sm <?php echo $current_page == 'my-reports' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('My Reports'); ?></span>
                <?php if($current_page == 'my-reports'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
            <!-- Announcements -->
            <a href="<?php echo BASE_URL; ?>index.php?page=announcements" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'announcements' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'announcements' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-bullhorn text-sm <?php echo $current_page == 'announcements' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Announcements'); ?></span>
                <?php if($current_page == 'announcements'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
            <!-- Notifications -->
            <a href="<?php echo BASE_URL; ?>index.php?page=notifications" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'notifications' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'notifications' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-bell text-sm <?php echo $current_page == 'notifications' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Notifications'); ?></span>
                <?php if($current_page == 'notifications'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
        </div>
        
        <?php elseif($user_role == 'barangay_official'): ?>
        <!-- Barangay Official Section -->
        <div class="mb-6">

            <?php if (PermissionHelper::userHasAnyPermission(['can_view_analytics', 'can_view_map'])): ?>
            
            <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'dashboard' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'dashboard' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-home text-sm <?php echo $current_page == 'dashboard' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Dashboard'); ?></span>
                <?php if($current_page == 'dashboard'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <?php if (PermissionHelper::userHasPermission('can_manage_reports')): ?>
            <a href="<?php echo BASE_URL; ?>index.php?page=verify-reports" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'verify-reports' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'verify-reports' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-check-double text-sm <?php echo $current_page == 'verify-reports' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Manage Reports'); ?></span>
                <?php if($current_page == 'verify-reports'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <a href="<?php echo BASE_URL; ?>index.php?page=announcements" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'announcements' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'announcements' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-bullhorn text-sm <?php echo $current_page == 'announcements' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Announcements'); ?></span>
                <?php if($current_page == 'announcements'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
            <?php if (PermissionHelper::userHasPermission('can_view_reports')): ?>
            <a href="<?php echo BASE_URL; ?>index.php?page=reporters-directory" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'reporters-directory' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'reporters-directory' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-address-book text-sm <?php echo $current_page == 'reporters-directory' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Reporters Directory'); ?></span>
                <?php if($current_page == 'reporters-directory'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <!-- Notifications -->
            <a href="<?php echo BASE_URL; ?>index.php?page=notifications" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'notifications' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'notifications' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-bell text-sm <?php echo $current_page == 'notifications' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Notifications'); ?></span>
                <?php if($current_page == 'notifications'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
        </div>
        
        <?php elseif($user_role == 'admin'): ?>
        <!-- Admin/MENRO Section -->
        <div class="mb-6">

            <?php if (PermissionHelper::userHasAnyPermission(['can_view_analytics', 'can_view_map'])): ?>
            
            <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'dashboard' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'dashboard' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-home text-sm <?php echo $current_page == 'dashboard' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Dashboard'); ?></span>
                <?php if($current_page == 'dashboard'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <?php if (PermissionHelper::userHasPermission('can_view_reports')): ?>
            <a href="<?php echo BASE_URL; ?>index.php?page=all-reports" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'all-reports' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'all-reports' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-flag text-sm <?php echo $current_page == 'all-reports' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('All Reports'); ?></span>
                <?php if($current_page == 'all-reports'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <a href="<?php echo BASE_URL; ?>index.php?page=announcements" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'announcements' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'announcements' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-bullhorn text-sm <?php echo $current_page == 'announcements' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Announcements'); ?></span>
                <?php if($current_page == 'announcements'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
            <!-- Notifications -->
            <a href="<?php echo BASE_URL; ?>index.php?page=notifications" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'notifications' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'notifications' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-bell text-sm <?php echo $current_page == 'notifications' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Notifications'); ?></span>
                <?php if($current_page == 'notifications'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
            <?php if (PermissionHelper::userHasAnyPermission(['can_manage_users', 'can_manage_staff'])): ?>
            <a href="<?php echo BASE_URL; ?>index.php?page=manage-users" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'manage-users' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'manage-users' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-users-cog text-sm <?php echo $current_page == 'manage-users' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('User Management'); ?></span>
                <?php if($current_page == 'manage-users'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <?php if (($_SESSION['user_type'] ?? null) === 'admin'): ?>
            <a href="<?php echo BASE_URL; ?>index.php?page=audit-logs" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'audit-logs' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'audit-logs' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-history text-sm <?php echo $current_page == 'audit-logs' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Audit Logs'); ?></span>
                <?php if($current_page == 'audit-logs'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

        </div>
        <?php endif; ?>

        <!-- GENERAL -->
        <div class="sb-general pt-3" style="border-top:1px solid rgba(255,255,255,.09);">
            <p class="sb-label"><?php echo t('General'); ?></p>
            <a href="<?php echo BASE_URL; ?>index.php?page=<?php echo ($user_role === 'admin') ? 'settings&tab=general' : 'profile'; ?>" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center"><i class="fas fa-cog text-sm"></i></div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Settings'); ?></span>
            </a>
            <a href="#" onclick="openLogoutModal();return false;" 
               class="sb-logout flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center"><i class="fas fa-sign-out-alt text-sm"></i></div>
                <span class="ml-3 text-sm font-medium"><?php echo t('Log Out'); ?></span>
            </a>
        </div>
    </nav>
    
    <!-- User Profile Section - Fixed at Bottom (Links to Profile Page) -->
    <div class="p-4 border-t border-gray-100 bg-white flex-shrink-0">
        <a href="<?php echo BASE_URL; ?>index.php?page=profile" 
           class="flex items-center hover:bg-gray-50 rounded-xl p-1.5 transition-all duration-200 text-left group">
            <div class="relative">
                <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-full flex items-center justify-center shadow-sm overflow-hidden">
                    <?php if (!empty($profile_pic_url)): ?>
                        <img src="<?php echo $profile_pic_url; ?>" alt="Profile" class="w-full h-full object-cover rounded-full">
                    <?php else: ?>
                        <span class="text-white font-bold text-sm"><?php echo $initials; ?></span>
                    <?php endif; ?>
                    <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 rounded-full border-2 border-white"></div>
                </div>
            </div>
            <div class="ml-3 flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate group-hover:text-emerald-600 transition"><?php echo htmlspecialchars($display_name); ?></p>
                <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-medium <?php echo $role_badge_color; ?>">
                        <?php echo $role_display_name; ?>
                    </span>
                    <?php if($barangay_name): ?>
                    <span class="text-[9px] text-gray-400 truncate flex items-center gap-0.5">
                        <i class="fas fa-map-marker-alt text-[8px]"></i><?php echo htmlspecialchars(substr($barangay_name, 0, 12)); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <i class="fas fa-chevron-right text-gray-300 text-xs group-hover:text-emerald-500 transition"></i>
        </a>
    </div>
</aside>

<?php if ($user_role === 'admin'): ?>
<!-- ===== MENRO NOTIFICATION DROPDOWN (sidebar bell) ===== -->
<div id="menroNotifDropdown" class="menro-notif-dropdown" style="display:none;">
    <div class="menro-notif-header">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="font-semibold text-gray-800 text-sm"><?php echo t('Notifications'); ?></h3>
                <p class="text-xs text-gray-400 mt-0.5"><?php echo t('MENRO alerts &amp; report updates'); ?></p>
            </div>
            <span class="text-xs bg-emerald-50 text-emerald-600 px-2.5 py-1 rounded-full font-medium" id="menroNotifCount"><?php echo count($menu_notifs); ?></span>
        </div>
    </div>
    <div class="menro-notif-list" id="menroNotifList">
        <?php if (count($menu_notifs) > 0): ?>
            <?php foreach ($menu_notifs as $mnotif): ?>
            <div class="menro-notif-item <?php echo $mnotif['is_read'] ? '' : 'unread'; ?>"
                 data-link="<?php echo htmlspecialchars($mnotif['link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                 data-id="<?php echo (int)$mnotif['id']; ?>">
                <div class="menro-notif-icon" style="background: <?php echo $mnotif['color'] ?? '#10A37F'; ?>20;">
                    <i class="fas <?php echo $mnotif['icon'] ?? 'fa-bell'; ?>" style="color: <?php echo $mnotif['color'] ?? '#10A37F'; ?>; font-size: 1rem;"></i>
                </div>
                <div class="menro-notif-content">
                    <div class="menro-notif-title"><?php echo htmlspecialchars($mnotif['title']); ?></div>
                    <div class="menro-notif-msg"><?php echo htmlspecialchars($mnotif['message']); ?></div>
                    <div class="menro-notif-time">
                        <i class="far fa-clock"></i>
                        <?php
                        $mtime = time() - strtotime($mnotif['created_at']);
                        if ($mtime < 60) echo t('Just now');
                        elseif ($mtime < 3600) echo floor($mtime / 60) . t(' min ago');
                        elseif ($mtime < 86400) echo floor($mtime / 3600) . t(' hrs ago');
                        else echo date('M d', strtotime($mnotif['created_at']));
                        ?>
                    </div>
                </div>
                <?php if (!$mnotif['is_read']): ?>
                <div class="menro-notif-dot"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="menro-notif-empty">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-bell-slash text-xl text-gray-400"></i>
                </div>
                <p class="text-gray-400 text-sm"><?php echo t('No notifications yet'); ?></p>
                <p class="text-xs text-gray-300 mt-1"><?php echo t('New reports and escalations will appear here'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php if (count($menu_notifs) > 0): ?>
    <div class="menro-notif-actions">
        <button type="button" class="menro-mark-all" onclick="menroMarkAllRead()"><i class="fas fa-check-double mr-1"></i><?php echo t('Mark all as read'); ?></button>
        <button type="button" class="menro-clear-all" onclick="menroClearAll()"><i class="fas fa-trash-alt mr-1"></i><?php echo t('Clear all'); ?></button>
    </div>
    <?php endif; ?>
    <div class="menro-notif-view-all" role="button" tabindex="0" onclick="menroViewAll()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();menroViewAll();}"><i class="fas fa-list-alt mr-2"></i><?php echo t('View all notifications'); ?></div>
</div>
<?php endif; ?>

<!-- LOGOUT MODAL - SINGLE SOURCE OF TRUTH -->
<div id="logoutModal" 
     class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center"
     style="z-index: 99999;"
     role="dialog"
     aria-modal="true"
     aria-labelledby="logout-modal-title">
    <div class="bg-white rounded-2xl max-w-sm w-full mx-4 overflow-hidden shadow-2xl" onclick="event.stopPropagation()">
        <div class="p-6 text-center">
            <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-sign-out-alt text-red-500 text-2xl"></i>
            </div>
            <h3 id="logout-modal-title" class="text-xl font-semibold text-gray-800 mb-2"><?php echo t('Confirm Logout'); ?></h3>
            <p class="text-gray-500 text-sm mb-6"><?php echo t('Are you sure you want to logout from your account?'); ?></p>
            <div class="flex gap-3">
                <button type="button" onclick="closeLogoutModal()" 
                        class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-gray-600 font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 transition">
                    <?php echo t('Cancel'); ?>
                </button>
                <a href="<?php echo BASE_URL; ?>index.php?page=logout" 
                   class="flex-1 px-4 py-2.5 bg-red-500 text-white rounded-xl font-medium hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-300 transition text-center">
                    <?php echo t('Logout'); ?>
                </a>
            </div>
        </div>
    </div>
</div>



<script>
    let sidebarOpen = false;
    
    function toggleSidebar() {
        sidebarOpen = !sidebarOpen;
        document.body.classList.toggle('sidebar-open', sidebarOpen);
        document.getElementById('showSidebarBtn').setAttribute('aria-expanded', sidebarOpen);
    }
    
    function closeSidebar() {
        sidebarOpen = false;
        document.body.classList.remove('sidebar-open');
        document.getElementById('showSidebarBtn').setAttribute('aria-expanded', 'false');
    }
    
    // ===== GLOBAL LOGOUT FUNCTIONS (accessible from any page) =====
    window.openLogoutModal = function() {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    };
    
    window.closeLogoutModal = function() {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    };
    
    document.addEventListener('DOMContentLoaded', function() {
        const body = document.body;
        const showBtn = document.getElementById('showSidebarBtn');
        const hideBtn = document.getElementById('hideSidebarBtn');
        const sidebar = document.getElementById('sidebar');
        const isDesktop = window.innerWidth >= 1024;
        
        // Initial state
        if (isDesktop) {
            body.classList.add('sidebar-open');
            sidebarOpen = true;
            if (showBtn) showBtn.setAttribute('aria-expanded', 'true');
        } else {
            body.classList.remove('sidebar-open');
            sidebarOpen = false;
            if (showBtn) showBtn.setAttribute('aria-expanded', 'false');
        }
        
        // Toggle buttons
        if (showBtn) {
            showBtn.addEventListener('click', toggleSidebar);
        }
        
        if (hideBtn) {
            hideBtn.addEventListener('click', closeSidebar);
        }
        
        // Close sidebar on outside click (mobile only)
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 1024 && sidebarOpen) {
                if (sidebar && showBtn) {
                    if (!sidebar.contains(e.target) && !showBtn.contains(e.target)) {
                        closeSidebar();
                    }
                }
            }
        });
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (sidebarOpen) {
                    closeSidebar();
                }
                // Also close logout modal if open
                const modal = document.getElementById('logoutModal');
                if (modal && modal.style.display === 'flex') {
                    window.closeLogoutModal();
                }
            }
        });
        
        // Window resize handler
        window.addEventListener('resize', function() {
            const desktop = window.innerWidth >= 1024;
            if (desktop) {
                body.classList.add('sidebar-open');
                sidebarOpen = true;
                if (showBtn) showBtn.setAttribute('aria-expanded', 'true');
            } else {
                body.classList.remove('sidebar-open');
                sidebarOpen = false;
                if (showBtn) showBtn.setAttribute('aria-expanded', 'false');
            }
        });
        
        // Close logout modal on overlay click
        const logoutModal = document.getElementById('logoutModal');
        if (logoutModal) {
            logoutModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    window.closeLogoutModal();
                }
            });
        }
    });
</script>

<!-- ===== REALTIME NOTIFICATIONS (polling + top-center toast, all pages) ===== -->
<script src="<?php echo BASE_URL; ?>assets/js/notification-polling.js?v=20261001" data-live-url="<?php echo BASE_URL; ?>controllers/LiveSyncController.php"></script>
<?php if ($user_role === 'admin'): ?>
<!-- ===== MENRO NOTIFICATION BELL LOGIC (toggle / mark read / clear) ===== -->
<script>
(function () {
    'use strict';
    var MENRO_NOTIF_URL = '<?php echo BASE_URL; ?>';
    var menroOpen = false;

    window.menroGetCsrf = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    window.menroCloseDropdown = function () {
        var d = document.getElementById('menroNotifDropdown');
        if (d) { d.style.display = 'none'; d.classList.remove('show'); }
        menroOpen = false;
        var bell = document.getElementById('menroNotifBell');
        if (bell) bell.setAttribute('aria-expanded', 'false');
    };

    window.menroToggleNotifs = function (e) {
        if (e) e.stopPropagation();
        var d = document.getElementById('menroNotifDropdown');
        if (!d) return;
        if (menroOpen) { window.menroCloseDropdown(); return; }
        d.style.display = 'flex';
        d.classList.add('show');
        menroOpen = true;
        var bell = document.getElementById('menroNotifBell');
        if (bell) bell.setAttribute('aria-expanded', 'true');
        window.menroPositionDropdown();
    };

    window.menroPositionDropdown = function () {
        var bell = document.getElementById('menroNotifBell');
        var d = document.getElementById('menroNotifDropdown');
        if (!bell || !d || d.style.display !== 'flex') return;
        var vb = bell.getBoundingClientRect();
        var vh = window.innerHeight;
        var vw = window.innerWidth;
        var top = vb.bottom + 10;
        if (top + d.offsetHeight > vh - 10) top = Math.max(10, vh - d.offsetHeight - 10);
        d.style.top = top + 'px';
        d.style.left = Math.max(12, Math.min(vb.right - 400, vw - 412)) + 'px';
        if (vw <= 480) {
            d.style.left = '12px';
            d.style.right = '12px';
            d.style.width = 'auto';
        } else {
            d.style.right = 'auto';
            d.style.width = '400px';
        }
    };

    function menroPost(action, data, cb) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('csrf_token', window.menroGetCsrf());
        if (data) Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        fetch(MENRO_NOTIF_URL + 'controllers/NotificationController.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (cb) cb(d); })
            .catch(function () { if (cb) cb(null); });
    }

    window.__menroRefreshBadge = function (unread) {
        var badge = document.getElementById('notificationBadge');
        if (unread > 0) {
            if (!badge) {
                var bell = document.getElementById('menroNotifBell');
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
    };

    window.menroHandleClick = function (id, link) {
        if (id) menroPost('mark_read', { id: id }, function () {});
        if (link && link !== '') { window.location.href = link; }
    };

    window.menroMarkAllRead = function () {
        menroPost('mark_all_read', null, function (data) {
            if (data && data.success) {
                document.querySelectorAll('#menroNotifList .menro-notif-item').forEach(function (el) {
                    el.classList.remove('unread');
                    var dot = el.querySelector('.menro-notif-dot');
                    if (dot) dot.remove();
                });
                var cnt = document.getElementById('menroNotifCount');
                if (cnt) cnt.textContent = String(document.querySelectorAll('#menroNotifList .menro-notif-item').length);
                window.__menroRefreshBadge(0);
            }
        });
    };

    window.menroClearAll = function () {
        window.GB.confirm({
            message: 'Clear all notifications? This cannot be undone.',
            onConfirm: function () {
                menroPost('clear_all', null, function (data) {
                    if (data && data.success) {
                        var list = document.getElementById('menroNotifList');
                        if (list) {
                            list.innerHTML = '<div class="menro-notif-empty">'
                                + '<div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">'
                                + '<i class="fas fa-bell-slash text-xl text-gray-400"></i></div>'
                                + '<p class="text-gray-400 text-sm">No notifications yet</p>'
                                + '<p class="text-xs text-gray-300 mt-1">You have cleared your notifications.</p></div>';
                        }
                        var cnt = document.getElementById('menroNotifCount');
                        if (cnt) cnt.textContent = '0';
                        var actions = document.querySelector('.menro-notif-actions');
                        if (actions) actions.style.display = 'none';
                        window.__menroRefreshBadge(0);
                    }
                });
            }
        });
    };

    window.menroViewAll = function () {
        window.location.href = MENRO_NOTIF_URL + 'index.php?page=notifications';
    };

    document.addEventListener('click', function (e) {
        if (menroOpen) {
            var d = document.getElementById('menroNotifDropdown');
            var bell = document.getElementById('menroNotifBell');
            if (d && bell && !d.contains(e.target) && !bell.contains(e.target)) {
                window.menroCloseDropdown();
            }
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && menroOpen) window.menroCloseDropdown();
    });

    window.addEventListener('resize', function () { if (menroOpen) window.menroPositionDropdown(); });

    document.querySelectorAll('#menroNotifList .menro-notif-item').forEach(function (item) {
        item.addEventListener('click', function (e) {
            e.stopPropagation();
            window.menroHandleClick(item.getAttribute('data-id'), item.getAttribute('data-link'));
        });
    });
})();
</script>
<?php endif; ?>
<style>
@keyframes rtToastIn {
    from { opacity: 0; transform: translateY(-16px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<script>
/* Lift page filter toolbars (#dashHeaderExtras) into the top app header,
   before the notification bell. The bell itself is always in the header. */
(function () {
    'use strict';
    var header = document.querySelector('.app-mobile-header');
    var burger = document.getElementById('showSidebarBtn');
    if (!header || !burger) return;

    function liftExtras() {
        var extras = document.getElementById('dashHeaderExtras');
        if (!extras || extras.parentNode === header) return;
        var actions = header.querySelector('.app-header-actions');
        header.insertBefore(extras, actions || burger);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', liftExtras);
    else liftExtras();
})();
</script>

<script>
/* Stats/KPI rows on mobile:
   - 3 or fewer cards -> fit the screen (equal columns)
   - more than 3      -> horizontal, swipeable row
   Self-contained: this script injects its own CSS (so it works even if the
   external stylesheet is cached/stale) and works on every mobile browser. */
(function () {
    'use strict';
    var CSS = '@media(max-width:767px){' +
        '.stat-cards{display:flex !important;flex-wrap:nowrap !important;gap:10px;min-width:0;max-width:100%;overflow-x:auto !important;overflow-y:hidden;-webkit-overflow-scrolling:touch;scroll-snap-type:x proximity;padding-bottom:6px;overscroll-behavior-x:contain;}' +
        '.stat-cards>*{box-sizing:border-box;flex:0 0 165px !important;width:165px !important;min-width:0;scroll-snap-align:start;}' +
        '.stat-cards.sf-fit{display:grid !important;overflow:visible !important;padding-bottom:0;scroll-snap-type:none;}' +
        '.stat-cards.sf-fit>*{flex:1 1 auto !important;width:auto !important;min-width:0;}' +
        '.stat-cards.sf-1{grid-template-columns:minmax(0,1fr) !important;}' +
        '.stat-cards.sf-2{grid-template-columns:repeat(2,minmax(0,1fr)) !important;}' +
        '.stat-cards.sf-3{grid-template-columns:repeat(3,minmax(0,1fr)) !important;}' +
        '}';
    function inject() {
        if (document.getElementById('stat-cards-css')) return;
        var s = document.createElement('style');
        s.id = 'stat-cards-css';
        s.appendChild(document.createTextNode(CSS));
        (document.head || document.documentElement).appendChild(s);
    }
    function applyStatFit() {
        inject();
        var rows = document.querySelectorAll('.stat-cards');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var n = row.children.length;
            row.classList.remove('sf-fit', 'sf-1', 'sf-2', 'sf-3');
            if (n >= 1 && n <= 3) { row.classList.add('sf-fit', 'sf-' + n); }
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', applyStatFit);
    else applyStatFit();
    window.addEventListener('resize', applyStatFit);
})();
</script>

<?php include BASE_PATH . 'views/shared/global_modals.php'; ?>

<?php echo lang_apply_js(); ?>
