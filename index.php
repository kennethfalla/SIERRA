<?php
// index.php - COMPLETE ROUTER
// Features: Authentication, Role-Based Routing, Settings Tabs, Password Reset Route
// Updated: Login → Dashboard, Register → Login, Reset Password → Dashboard

require_once 'config/config.php';
require_once BASE_PATH . 'helpers/SettingsHelper.php';
require_once BASE_PATH . 'helpers/PermissionHelper.php';

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

    $allowed_during_maintenance = in_array($page, ['login', 'reset-password', 'forgot-password', 'privacy-policy', 'terms-of-service'], true);
    if (!$is_admin_session && !$allowed_during_maintenance) {
        require_once 'views/maintenance.php';
        exit();
    }
}

// ============================================
// CSRF TOKEN GENERATION FOR FORMS
// ============================================
// Authentication may start in the landing popup. Hand off before rendering
// dashboard queries or consuming its first-login loading flag.
if ($page === 'dashboard' && isLoggedIn() && ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'iframe') {
    $dashboard_url = BASE_URL . 'index.php?page=dashboard';
    header('Cache-Control: no-store');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Opening dashboard</title></head><body><script>if(!window.frameElement || !window.frameElement.hasAttribute("data-landing-auth")) window.top.location.replace(' . json_encode($dashboard_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');</script><a target="_top" href="' . htmlspecialchars($dashboard_url, ENT_QUOTES, 'UTF-8') . '">Open dashboard</a></body></html>';
    exit();
}
$csrf_token = InputSanitizer::generateCsrfToken();

// ============================================
// HOME PAGE - Always accessible
// ============================================
if($page === 'home') {
    require_once 'views/index.php';
    exit();
}

// Public policy pages use the same content shown during registration.
if (in_array($page, ['privacy-policy', 'terms-of-service'], true)) {
    require_once 'views/public/legal.php';
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
    // All profile logic (AJAX, form POSTs, rendering) lives in the controller.
    require_once 'controllers/ProfileController.php';
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
// NOTIFICATIONS - SHARED PAGE FOR ALL ROLES
// ============================================
if($page === 'notifications') {
    require_once 'views/shared/notifications.php';
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
        if ($settings_tab === 'categories') {
            // Category management POSTs are handled by their own partial
            // (they validate CSRF, process the action, and redirect).
            require_once 'views/admin/settings/partials/categories.php';
            exit();
        }
        if ($settings_tab === 'quick_notes') {
            // Quick Note Template POSTs (create / update / delete / toggle)
            // are handled by their own partial.
            require_once 'views/admin/settings/partials/quick_notes.php';
            exit();
        }
        if ($settings_tab === 'category_keywords') {
            // Category Keyword POSTs (create / update / delete / toggle)
            // are handled by their own partial.
            require_once 'views/admin/settings/partials/category_keywords.php';
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
// PERMISSION GATE - role-based pages
// A user's menu and page access follow the permissions of the ROLE they
// are assigned (role_permissions), not their legacy user_type alone.
// Super-admin (users.user_type = 'admin') bypasses via PermissionHelper.
// Citizen/shared pages are not in the map and pass through untouched.
// ============================================
if ($role === 'admin' || $role === 'barangay_official') {
    PermissionHelper::requirePagePermission($page);
}

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
        case 'map':
            require_once 'views/shared/map_page.php';
            break;
        case 'verify-reports':
            require_once 'views/barangay/verify_reports.php';
            break;
        case 'reporters-directory':
            require_once 'views/barangay/reporters_directory.php';
            break;
        case 'barangay-dashboard-report':
            require_once 'views/admin/reports/dashboard_report.php';
            break;
        case 'barangay-dashboard-print':
            require_once 'views/barangay/reports/dashboard_print.php';
            break;
        case 'barangay-manage-reports-print':
            require_once 'views/barangay/reports/manage_reports_print.php';
            break;
        case 'barangay-reporters-print':
            require_once 'views/barangay/reports/reporters_directory_print.php';
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
        case 'analytics':
            require_once 'views/admin/analytics.php';
            break;
        case 'map':
            require_once 'views/shared/map_page.php';
            break;
        case 'all-reports':
            require_once 'views/admin/all_reports.php';
            break;
        case 'manage-users':
            require_once 'views/admin/users.php';
            break;
        case 'manage-categories':
            header("Location: " . BASE_URL . "index.php?page=settings&tab=categories");
            exit();
        case 'all-reports-print':
            require_once 'views/admin/reports/all_reports_print.php';
            break;
        case 'audit-logs':
            require_once 'views/admin/audit_logs.php';
            break;
        case 'audit-logs-report':
            require_once 'views/admin/reports/audit_logs_report.php';
            break;
        case 'users-report':
            require_once 'views/admin/reports/users_report.php';
            break;
        case 'dashboard-report':
            require_once 'views/admin/reports/dashboard_report.php';
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
