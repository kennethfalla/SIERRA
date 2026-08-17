<?php
// views/layouts/sidebar.php - UPDATED WITH TABBED SETTINGS
// Bugs fixed:
// 1. Removed duplicate logout modal (now only in sidebar)
// 2. Fixed JavaScript function conflicts
// 3. Added proper profile picture handling
// 4. Fixed avatar update selectors
// 5. Updated System Settings link to point to tabbed interface

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
        $role_display_name = 'Admin';
        $role_badge_color = 'bg-purple-100 text-purple-700';
        $role_icon = 'fa-building';
        break;
    case 'menro_staff':
        $role_display_name = 'MENRO Staff';
        $role_badge_color = 'bg-purple-100 text-purple-700';
        $role_icon = 'fa-building';
        break;
    case 'barangay_personnel':
        $role_display_name = 'Barangay Official';
        $role_badge_color = 'bg-emerald-100 text-emerald-700';
        $role_icon = 'fa-map-marker-alt';
        break;
    default:
        $role_display_name = 'Citizen';
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

<!-- Skip to main content link -->
<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[9999] focus:px-4 focus:py-2 focus:bg-emerald-600 focus:text-white focus:rounded-lg">
    Skip to main content
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
                <h2 class="text-xl font-bold bg-gradient-to-r from-emerald-700 to-teal-600 bg-clip-text text-transparent">
                    <?php echo htmlspecialchars($system_name); ?>
                </h2>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Environmental Reporting</p>
            </div>
        </div>
        <button id="hideSidebarBtn" 
                class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-300 transition-all duration-200 flex items-center justify-center group"
                aria-label="Close sidebar menu"
                title="Hide Sidebar">
            <svg class="w-4 h-4 text-gray-500 group-hover:text-red-500 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <span class="sr-only">Close sidebar</span>
        </button>
    </div>
    
    <!-- Navigation - Scrollable Area -->
    <nav class="flex-1 overflow-y-auto px-4 py-5" aria-label="Main navigation">
        
        <?php if($user_role == 'citizen'): ?>
        <!-- Citizen Section -->
        <div class="mb-6">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider px-3 mb-3">Main</p>
            
            <!-- Home -->
            <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'dashboard' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'dashboard' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-home text-sm <?php echo $current_page == 'dashboard' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium">Home</span>
                <?php if($current_page == 'dashboard'): ?>
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
                <span class="ml-3 text-sm font-medium">Announcements</span>
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
                <span class="ml-3 text-sm font-medium">Notifications</span>
                <?php if($current_page == 'notifications'): ?>
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
                <span class="ml-3 text-sm font-medium">Submit Report</span>
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
                <span class="ml-3 text-sm font-medium">My Reports</span>
                <?php if($current_page == 'my-reports'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
        </div>
        
        <?php elseif($user_role == 'barangay_official'): ?>
        <!-- Barangay Official Section -->
        <div class="mb-6">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider px-3 mb-3">Main</p>
            
            <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'dashboard' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'dashboard' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-home text-sm <?php echo $current_page == 'dashboard' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium">Dashboard</span>
                <?php if($current_page == 'dashboard'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo BASE_URL; ?>index.php?page=announcements" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'announcements' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'announcements' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-bullhorn text-sm <?php echo $current_page == 'announcements' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium">Announcements</span>
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
                <span class="ml-3 text-sm font-medium">Notifications</span>
                <?php if($current_page == 'notifications'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo BASE_URL; ?>index.php?page=verify-reports" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'verify-reports' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'verify-reports' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-check-double text-sm <?php echo $current_page == 'verify-reports' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium">Manage Reports</span>
                <?php if($current_page == 'verify-reports'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
        </div>
        
        <?php elseif($user_role == 'admin'): ?>
        <!-- Admin/MENRO Section -->
        <div class="mb-6">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider px-3 mb-3">Main</p>
            
            <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'dashboard' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'dashboard' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-home text-sm <?php echo $current_page == 'dashboard' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium">Dashboard</span>
                <?php if($current_page == 'dashboard'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo BASE_URL; ?>index.php?page=announcements" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'announcements' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'announcements' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-bullhorn text-sm <?php echo $current_page == 'announcements' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium">Announcements</span>
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
                <span class="ml-3 text-sm font-medium">Notifications</span>
                <?php if($current_page == 'notifications'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo BASE_URL; ?>index.php?page=all-reports" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'all-reports' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'all-reports' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-flag text-sm <?php echo $current_page == 'all-reports' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium">All Reports</span>
                <?php if($current_page == 'all-reports'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
            <?php if (($_SESSION['user_type'] ?? null) === 'admin'): ?>
            <a href="<?php echo BASE_URL; ?>index.php?page=audit-logs" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'audit-logs' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'audit-logs' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-history text-sm <?php echo $current_page == 'audit-logs' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium">Audit Logs</span>
                <?php if($current_page == 'audit-logs'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <!-- UPDATED: System Settings (tabbed interface) -->
            <div class="mt-4 pt-2 border-t border-emerald-50">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider px-3 mb-3">Settings</p>
                <a href="<?php echo BASE_URL; ?>settings?tab=general" 
                   class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'settings' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'settings' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-cog text-sm <?php echo $current_page == 'settings' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                    </div>
                    <span class="ml-3 text-sm font-medium">System Settings</span>
                    <?php if($current_page == 'settings'): ?>
                    <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                    <span class="sr-only">(current)</span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </nav>
    
    <!-- User Profile Section - Fixed at Bottom (Links to Profile Page) -->
    <div class="p-4 border-t border-gray-100 bg-white flex-shrink-0">
        <a href="<?php echo BASE_URL; ?>index.php?page=profile" 
           class="flex items-center hover:bg-gray-50 rounded-xl p-1.5 transition-all duration-200 text-left group">
            <div class="relative">
                <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl flex items-center justify-center shadow-sm overflow-hidden">
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
            <h3 id="logout-modal-title" class="text-xl font-semibold text-gray-800 mb-2">Confirm Logout</h3>
            <p class="text-gray-500 text-sm mb-6">Are you sure you want to logout from your account?</p>
            <div class="flex gap-3">
                <button type="button" onclick="closeLogoutModal()" 
                        class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-gray-600 font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 transition">
                    Cancel
                </button>
                <a href="<?php echo BASE_URL; ?>index.php?page=logout" 
                   class="flex-1 px-4 py-2.5 bg-red-500 text-white rounded-xl font-medium hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-300 transition text-center">
                    Logout
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

<!-- ===== GLOBAL LIVE REFRESH (Messenger-style silent sync) ===== -->
<script>
(function () {
    'use strict';
    var LIVE_URL = '<?php echo BASE_URL; ?>controllers/LiveSyncController.php';
    var POLL_MS = 10000;          // check for updates every 10 seconds
    var AUTO_RELOAD_MS = 6000;    // auto-refresh delay once a new update is found
    var IDLE_WINDOW_MS = 4000;    // cancel auto-refresh if the user is active

    var baselineVersion = null;
    var baselineSeq = null;
    var lastUnread = -1;
    var pillActive = false;
    var pillDismissed = false;
    var autoReloadTimer = null;
    var countdownTimer = null;
    var pageVisible = true;
    var lastActivity = Date.now();
    var polling = false;

    function markActivity() { lastActivity = Date.now(); }

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
                var bell = document.querySelector('.notification-bell');
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

    function hidePill() {
        clearTimeout(autoReloadTimer);
        clearInterval(countdownTimer);
        var p = document.getElementById('liveSyncPill');
        if (p) p.remove();
        pillActive = false;
    }

    function showPill() {
        if (pillActive || pillDismissed) return;
        pillActive = true;

        var pill = document.createElement('div');
        pill.id = 'liveSyncPill';
        pill.style.cssText = 'position:fixed;bottom:18px;right:18px;z-index:9999;display:flex;align-items:center;gap:10px;background:#ffffff;border:1px solid #10A37F;box-shadow:0 8px 24px rgba(16,163,127,.25);border-radius:12px;padding:10px 14px;font-family:inherit;font-size:13px;color:#111827;';

        var icon = document.createElement('i');
        icon.className = 'fas fa-bolt';
        icon.style.color = '#10A37F';

        var label = document.createElement('span');
        label.id = 'liveSyncPillLabel';
        label.textContent = 'New update';

        var refreshBtn = document.createElement('button');
        refreshBtn.textContent = 'Refresh now';
        refreshBtn.style.cssText = 'border:none;background:#10A37F;color:#fff;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;';
        refreshBtn.addEventListener('click', function () { window.location.reload(); });

        var closeBtn = document.createElement('button');
        closeBtn.textContent = '\u00d7';
        closeBtn.style.cssText = 'border:none;background:transparent;color:#9CA3AF;font-size:18px;cursor:pointer;padding:0 2px;';
        closeBtn.addEventListener('click', function () { pillDismissed = true; hidePill(); });

        pill.appendChild(icon);
        pill.appendChild(label);
        pill.appendChild(refreshBtn);
        pill.appendChild(closeBtn);
        document.body.appendChild(pill);

        var remaining = Math.ceil(AUTO_RELOAD_MS / 1000);
        label.textContent = 'New update \u00b7 refreshing in ' + remaining + 's';
        countdownTimer = setInterval(function () {
            remaining -= 1;
            if (remaining < 0) remaining = 0;
            label.textContent = 'New update \u00b7 refreshing in ' + remaining + 's';
        }, 1000);

        autoReloadTimer = setTimeout(function () {
            if (!pageVisible) { hidePill(); return; }
            if (Date.now() - lastActivity < IDLE_WINDOW_MS) { hidePill(); showPill(); return; }
            var active = document.activeElement;
            if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable)) { hidePill(); return; }
            window.location.reload();
        }, AUTO_RELOAD_MS);
    }

    function showToast(latest) {
        if (!latest || !latest.title) return;
        var toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;bottom:18px;left:18px;z-index:9999;max-width:300px;background:#ffffff;border-left:4px solid #10A37F;box-shadow:0 8px 24px rgba(0,0,0,.15);border-radius:10px;padding:12px 14px;font-family:inherit;cursor:pointer;';
        var t = document.createElement('div');
        t.style.cssText = 'font-weight:600;font-size:13px;color:#111827;margin-bottom:2px;';
        t.textContent = latest.title;
        var m = document.createElement('div');
        m.style.cssText = 'font-size:12px;color:#6B7280;line-height:1.35;';
        m.textContent = latest.message;
        var meta = document.createElement('div');
        meta.style.cssText = 'font-size:11px;color:#9CA3AF;margin-top:6px;';
        meta.textContent = timeAgo(latest.created_at);
        toast.appendChild(t);
        toast.appendChild(m);
        toast.appendChild(meta);
        if (latest.link) {
            toast.addEventListener('click', function () { window.location.href = latest.link; });
        }
        document.body.appendChild(toast);
        setTimeout(function () { if (toast.parentNode) toast.remove(); }, 5000);
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
                var changed = (data.data_version && baselineVersion && data.data_version !== baselineVersion) ||
                              (typeof data.notif_seq === 'number' && typeof baselineSeq === 'number' && data.notif_seq !== baselineSeq);
                if (changed) {
                    baselineVersion = data.data_version;
                    baselineSeq = data.notif_seq;
                    showPill();
                }
            })
            .catch(function () {})
            .then(function () { polling = false; });
    }

    ['click', 'keydown', 'scroll', 'wheel', 'touchstart'].forEach(function (ev) {
        window.addEventListener(ev, markActivity, { passive: true });
    });

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