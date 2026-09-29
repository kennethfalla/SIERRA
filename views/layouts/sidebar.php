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
        
        // Check if profile_picture column exists
        $columns = $db->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
        $hasProfilePicture = $columns->rowCount() > 0;
        
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
                " . ($hasProfilePicture ? "u.profile_picture," : "'' as profile_picture,") . "
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
if ($user_role === 'admin' && $user_id && isset($db)) {
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

<!-- Minimal Non-Intrusive Burger Menu Button -->
<button id="showSidebarBtn" 
        class="fixed top-4 left-4 z-50 bg-white/80 backdrop-blur-sm border border-gray-200 rounded-lg shadow-sm p-1.5 hover:bg-emerald-50 hover:border-emerald-200 focus:outline-none focus:ring-2 focus:ring-emerald-300 transition-all duration-300 group hidden"
        style="display: none;"
        aria-label="Open navigation menu"
        aria-expanded="false"
        title="Show Menu">
    <svg class="w-4 h-4 text-gray-500 group-hover:text-emerald-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
    <span class="sr-only">Open menu</span>
</button>

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
            <?php if ($user_role === 'admin'): ?>
            <button type="button" id="menroNotifBell"
                    class="notification-bell"
                    style="width:40px;height:40px;border-radius:10px;background:#F3F4F6;color:#4B5563;position:relative;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;"
                    onclick="menroToggleNotifs(event)"
                    aria-label="Notifications"
                    title="Notifications">
                <i class="fas fa-bell" style="font-size:15px;"></i>
                <?php if ($menu_unread > 0): ?>
                <span class="notification-badge" id="notificationBadge"><?php echo $menu_unread > 9 ? '9+' : (int)$menu_unread; ?></span>
                <?php endif; ?>
            </button>
            <?php endif; ?>
            <button id="hideSidebarBtn" 
                    class="w-10 h-10 rounded-lg bg-gray-100 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-300 transition-all duration-200 flex items-center justify-center group"
                    aria-label="Close sidebar menu"
                    title="Hide Sidebar">
            <svg class="w-4 h-4 text-gray-500 group-hover:text-red-500 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <span class="sr-only">Close sidebar</span>
            </button>
            </div>
    </div>
    
    <!-- Navigation - Scrollable Area -->
    <nav class="flex-1 overflow-y-auto px-4 py-5" aria-label="Main navigation">
        
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

            <!-- UPDATED: System Settings (tabbed interface) -->
            <?php if (PermissionHelper::userHasPermission('can_manage_system')): ?>
            <div class="mt-4 pt-2 border-t border-emerald-50">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider px-3 mb-3"><?php echo t('Settings'); ?></p>
                <a href="<?php echo BASE_URL; ?>index.php?page=settings&tab=general" 
                   class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'settings' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'settings' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-cog text-sm <?php echo $current_page == 'settings' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                    </div>
                    <span class="ml-3 text-sm font-medium"><?php echo t('System Settings'); ?></span>
                    <?php if($current_page == 'settings'): ?>
                    <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                    <span class="sr-only">(current)</span>
                    <?php endif; ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
    
    #showSidebarBtn {
        display: flex !important;
        z-index: 51;
    }
    
    /* Hide the burger while the sidebar overlay is open (mobile only) */
    @media (max-width: 1023px) {
        body.sidebar-open #showSidebarBtn {
            display: none !important;
        }
    }
    
    /* Desktop: sidebar always visible */
    @media (min-width: 1024px) {
        body:not(.sidebar-open) #sidebar {
            transform: translateX(0) !important;
        }
        #showSidebarBtn {
            display: none !important;
        }
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
        border-radius: 4px;
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
        border-radius: 20px;
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
</style>

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
<script>
(function () {
    'use strict';
    var LIVE_URL = '<?php echo BASE_URL; ?>controllers/LiveSyncController.php';
    var POLL_MS = 30000;   // check for new notifications every 30 seconds (was 10s — free hosts throttle on hit volume)
    var TOAST_MS = 6000;   // how long each toast stays on screen

    var baselineVersion = null;
    var baselineSeq = null;
    var lastUnread = -1;
    var pageVisible = true;
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
        toast.style.cssText = 'pointer-events:auto;display:flex;align-items:flex-start;gap:12px;background:#ffffff;border:1px solid #e2e8f0;border-left:4px solid ' + (latest.color || '#10A37F') + ';border-radius:14px;box-shadow:0 14px 40px rgba(0,0,0,.18);padding:14px 16px;font-family:inherit;cursor:pointer;opacity:0;transform:translateY(-16px);animation:rtToastIn .35s cubic-bezier(.16,1,.3,1) forwards;';

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

    function tick() {
        if (!pageVisible || polling) return;
        polling = true;
        fetch(LIVE_URL, { method: 'GET', credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || data.success !== true) return;
                var unread = parseInt(data.unread, 10) || 0;
                updateBadge(unread);
                if (baselineVersion === null) {
                    baselineVersion = data.data_version;
                    baselineSeq = data.notif_seq;
                    lastUnread = unread;
                    return;
                }
                if (unread > lastUnread && data.latest) {
                    showToast(data.latest);
                }
                lastUnread = unread;
                if (data.data_version && data.data_version !== baselineVersion) baselineVersion = data.data_version;
                if (typeof data.notif_seq === 'number') baselineSeq = data.notif_seq;
            })
            .catch(function () {})
            .then(function () { polling = false; });
    }

    document.addEventListener('visibilitychange', function () {
        pageVisible = !document.hidden;
        if (pageVisible) tick();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(tick, 2500); });
    } else {
        setTimeout(tick, 2500);
    }
    setInterval(tick, POLL_MS);
})();
</script>
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
    };

    window.menroToggleNotifs = function (e) {
        if (e) e.stopPropagation();
        var d = document.getElementById('menroNotifDropdown');
        if (!d) return;
        if (menroOpen) { window.menroCloseDropdown(); return; }
        d.style.display = 'flex';
        d.classList.add('show');
        menroOpen = true;
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
        d.style.left = vb.left + 'px';
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

<?php include BASE_PATH . 'views/shared/global_modals.php'; ?>

<?php echo lang_apply_js(); ?>
