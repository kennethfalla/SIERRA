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
                u.role,
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
            $user_role = $user_data['role'] ?? 'citizen';
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
switch($user_role) {
    case 'admin':
        $role_display_name = 'MENRO Administrator';
        $role_badge_color = 'bg-purple-100 text-purple-700';
        $role_icon = 'fa-building';
        break;
    case 'barangay_official':
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
            
            <a href="<?php echo BASE_URL; ?>index.php?page=manage-users" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'manage-users' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'manage-users' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-users text-sm <?php echo $current_page == 'manage-users' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium">Manage Users</span>
                <?php if($current_page == 'manage-users'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo BASE_URL; ?>index.php?page=manage-categories" 
               class="flex items-center px-3 py-2.5 rounded-xl mb-1.5 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 <?php echo $current_page == 'manage-categories' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center <?php echo $current_page == 'manage-categories' ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                    <i class="fas fa-tags text-sm <?php echo $current_page == 'manage-categories' ? 'text-emerald-600' : 'text-gray-500'; ?>"></i>
                </div>
                <span class="ml-3 text-sm font-medium">Categories</span>
                <?php if($current_page == 'manage-categories'): ?>
                <span class="ml-auto w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
            
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

            <!-- UPDATED: System Settings (tabbed interface) -->
            <div class="mt-4 pt-2 border-t border-emerald-50">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider px-3 mb-3">Settings</p>
                <a href="<?php echo BASE_URL; ?>index.php?page=settings&tab=general" 
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