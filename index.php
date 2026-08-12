<?php
// index.php - COMPLETE ROUTER
// Features: Authentication, Role-Based Routing, Settings Tabs, Password Reset Route
// Updated: Login → Dashboard, Register → Login, Reset Password → Dashboard

require_once 'config/config.php';
require_once BASE_PATH . 'helpers/SettingsHelper.php';

// ============================================
// CHECK FOR LOGOUT ACTION - MUST BE FIRST
// ============================================
if(isset($_GET['page']) && $_GET['page'] === 'logout') {
    header("Location: " . BASE_URL . "controllers/AuthController.php?action=logout");
    exit();
}

// ============================================
// GET THE PAGE PARAMETER - DEFAULT TO HOME
// ============================================
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// ============================================
// MAINTENANCE MODE - MASTER KILL SWITCH
// ============================================
// When maintenance_mode is ON, everyone except logged-in admins sees the
// maintenance splash. The login page stays reachable so an admin can always
// get back in and toggle the switch off.
//
// Failsafe: any non-admin session is force-logged-out immediately. Without
// this, a logged-in citizen gets trapped in a loop (login -> dashboard ->
// maintenance splash -> login...) and can never reach the login form.
if (SettingsHelper::get('maintenance_mode', 0) == 1) {
    $is_admin_session = isset($_SESSION['user_id'])
        && ($_SESSION['user_role'] ?? '') === 'admin';

    if (!$is_admin_session && isset($_SESSION['user_id'])) {
        forceLogout('Maintenance mode is active. You have been signed out; staff may log in below.');
    }

    $allowed_during_maintenance = in_array($page, ['login', 'reset-password', 'forgot-password']);
    if (!$is_admin_session && !$allowed_during_maintenance) {
        require_once 'views/maintenance.php';
        exit();
    }
}

// ============================================
// CSRF TOKEN GENERATION FOR FORMS
// ============================================
$csrf_token = InputSanitizer::generateCsrfToken();

// ============================================
// HOME PAGE - Always accessible
// ============================================
if($page === 'home') {
    require_once 'views/index.php';
    exit();
}

// ============================================
// AUTH PAGES - Allow access to login/register even if logged in
// ============================================
if($page === 'login' || $page === 'register') {
    if(isLoggedIn()) {
        // If already logged in, redirect to dashboard (not home)
        header("Location: " . BASE_URL . "index.php?page=dashboard");
        exit();
    }
    // KILL SWITCH: public registration disabled -> hide the register form
    if ($page === 'register' && SettingsHelper::get('enable_public_registration', '1') != '1') {
        $_SESSION['error'] = "Public registration is currently disabled. Please contact the MENRO office.";
        header("Location: " . BASE_URL . "index.php?page=login");
        exit();
    }
    require_once $page === 'login' ? 'views/auth/login.php' : 'views/auth/register.php';
    exit();
}

// ============================================
// FORGOT PASSWORD PAGE - Accessible without login
// ============================================
if($page === 'forgot-password') {
    if(isLoggedIn()) {
        header("Location: " . BASE_URL . "index.php?page=dashboard");
        exit();
    }
    require_once 'views/auth/forgot-password.php';
    exit();
}

// ============================================
// PROTECTED PAGES - Login required
// ============================================
if(!isLoggedIn()) {
    $_SESSION['error'] = "Please login to access this page.";
    header("Location: " . BASE_URL . "index.php?page=login");
    exit();
}

// ============================================
// RESET PASSWORD PAGE - Step 2 of 2-Step Login
// Only accessible when force_password_reset flag is set
// ============================================
if($page === 'reset-password') {
    // Only allow access if force_password_reset is set in session
    if (!isset($_SESSION['force_password_reset']) || $_SESSION['force_password_reset'] !== true) {
        $_SESSION['error'] = "Access denied. Please login first.";
        header("Location: " . BASE_URL . "index.php?page=login");
        exit();
    }
    
    // Ensure user is logged in
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        $_SESSION['error'] = "Session expired. Please login again.";
        unset($_SESSION['force_password_reset']);
        header("Location: " . BASE_URL . "index.php?page=login");
        exit();
    }
    
    require_once 'views/auth/reset_password.php';
    exit();
}

// ============================================
// PROFILE PAGE - Accessible to all logged-in users
// ============================================
if($page === 'profile') {
    require_once 'views/profile.php';
    exit();
}

// ============================================
// MANAGE REPORT PAGE - Accessible to all logged-in users
// (Controller handles permission checks)
// ============================================
if($page === 'manage-report') {
    require_once 'controllers/ReportController.php';
    exit();
}

// ============================================
// ANNOUNCEMENTS - SHARED PAGE FOR ALL ROLES
// ============================================
if($page === 'announcements') {
    require_once 'views/shared/announcements.php';
    exit();
}

// ============================================
// SETTINGS PAGE - Admin only (Tabbed interface)
// ============================================
if($page === 'settings') {
    requireLogin();
    requireRole('admin');

    require_once BASE_PATH . 'helpers/SettingsHelper.php';
    require_once BASE_PATH . 'helpers/PermissionHelper.php';

    // "System Management" permission gates this page (super-admin bypasses).
    if (!PermissionHelper::userHasPermission('can_manage_system')) {
        $_SESSION['error'] = "You are not permitted to edit system settings.";
        header("Location: " . BASE_URL . "index.php?page=dashboard");
        exit();
    }

    // Handle POST requests for settings updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $settings_tab = $_GET['tab'] ?? 'general';
        if ($settings_tab === 'users' || $settings_tab === 'categories') {
            // User & category management POSTs are handled by their own
            // partials (they validate CSRF, process the action, and redirect).
            require_once 'views/admin/settings/partials/' . ($settings_tab === 'users' ? 'users.php' : 'categories.php');
            exit();
        }
        require_once 'controllers/SettingsController.php';
        // The controller handles the request and redirects
        exit();
    }
    
    // GET request - show settings page
    require_once 'views/admin/settings/index.php';
    exit();
}

// ============================================
// LOGGED IN USERS - Role-based routing
// ============================================
$role = $_SESSION['user_role'] ?? 'citizen';

// ============================================
// CITIZEN ROUTES
// ============================================
if($role === 'citizen') {
    switch($page) {
        case 'dashboard':
            require_once 'views/citizen/dashboard.php';
            break;
        case 'submit-report':
            require_once 'views/citizen/submit_report.php';
            break;
        case 'my-reports':
            require_once 'views/citizen/my_reports.php';
            break;
        case 'track-status':
            require_once 'views/citizen/track_status.php';
            break;
        case 'edit-profile':
            require_once 'views/edit_profile.php';
            break;
        default:
            // If citizen tries to access unknown page, redirect to dashboard
            header("Location: " . BASE_URL . "index.php?page=dashboard");
            exit();
    }
}

// ============================================
// BARANGAY OFFICIAL ROUTES
// ============================================
elseif($role === 'barangay_official') {
    switch($page) {
        case 'dashboard':
            require_once 'views/barangay/dashboard.php';
            break;
        case 'verify-reports':
            require_once 'views/barangay/verify_reports.php';
            break;
        case 'edit-profile':
            require_once 'views/edit_profile.php';
            break;
        default:
            // If barangay official tries to access unknown page, redirect to dashboard
            header("Location: " . BASE_URL . "index.php?page=dashboard");
            exit();
    }
}

// ============================================
// ADMIN (MENRO) ROUTES
// ============================================
elseif($role === 'admin') {
    switch($page) {
        case 'dashboard':
            require_once 'views/admin/dashboard.php';
            break;
        case 'all-reports':
            require_once 'views/admin/all_reports.php';
            break;
        case 'manage-users':
            header("Location: " . BASE_URL . "index.php?page=settings&tab=users");
            exit();
        case 'manage-categories':
            header("Location: " . BASE_URL . "index.php?page=settings&tab=categories");
            exit();
        case 'audit-logs':
            require_once 'views/admin/audit_logs.php';
            break;
        case 'edit-profile':
            require_once 'views/edit_profile.php';
            break;
        default:
            // If admin tries to access unknown page, redirect to dashboard
            header("Location: " . BASE_URL . "index.php?page=dashboard");
            exit();
    }
}

// ============================================
// FALLBACK - If role is not recognized or page not found
// ============================================
else {
    // Logout or redirect to login if role is unknown
    $_SESSION['error'] = "Invalid user role. Please login again.";
    header("Location: " . BASE_URL . "index.php?page=logout");
    exit();
}
?>