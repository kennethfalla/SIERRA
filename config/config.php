<?php
// config/config.php - COMPLETE FIXED VERSION
// NO output before this line - NO spaces, NO HTML, NO echo

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// BASE URL & PATH DEFINITIONS
// ============================================
// Build BASE_URL dynamically so the app works regardless of hostname
// (localhost, 127.0.0.1, LAN IP, or a real domain).
if (!defined('BASE_URL')) {
    if (isset($_SERVER['HTTP_HOST'])) {
        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST']; // includes port when non-standard
        // Derive the app's sub-path from __DIR__ vs DOCUMENT_ROOT
        $docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
        $appDir   = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
        $subPath  = str_replace($docRoot, '', $appDir);
        define('BASE_URL', $scheme . '://' . $host . $subPath . '/');
    } else {
        // CLI or missing server vars — fall back to original hardcoded value
        define('BASE_URL', 'http://localhost/environmental-reporting-app/');
    }
}
define('BASE_PATH', dirname(__DIR__) . '/');


// ============================================
// UPLOAD DIRECTORIES
// ============================================
define('UPLOAD_DIR', BASE_PATH . 'uploads/reports/');
define('PROFILE_UPLOAD_DIR', BASE_PATH . 'uploads/profile/');
define('ANNOUNCEMENT_UPLOAD_DIR', BASE_PATH . 'uploads/announcements/');

// ============================================
// FILE UPLOAD CONSTANTS
// ============================================
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// ============================================
// ERROR REPORTING (Development Mode)
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ============================================
// REQUIRE CORE FILES
// ============================================
require_once BASE_PATH . 'config/database.php';
require_once BASE_PATH . 'includes/functions.php';
require_once BASE_PATH . 'helpers/SecurityHelper.php';

// ============================================
// AUTO-LOAD MODEL FILES
// ============================================
spl_autoload_register(function($class_name) {
    $model_file = BASE_PATH . 'models/' . $class_name . '.php';
    if(file_exists($model_file)) {
        require_once $model_file;
    }
});

// ============================================
// AUTHENTICATION HELPER FUNCTIONS
// ============================================

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && validateAuthenticatedSession();
}

/**
 * Clear the current authentication session and optionally preserve a flash message.
 *
 * @param string|null $message
 * @return void
 */
function forceLogout($message = 'Your account is no longer available. Please log in again.') {
    $_SESSION = array();

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    session_start();

    if (!empty($message)) {
        $_SESSION['error'] = $message;
    }
}

/**
 * Ensure the logged-in user still exists in the database.
 * Returns false and clears the session if the account was deleted.
 *
 * @return bool
 */
function validateAuthenticatedSession() {
    static $validated = false;

    if ($validated) {
        return empty($_SESSION['user_id']) ? false : true;
    }

    $validated = true;

    if (empty($_SESSION['user_id'])) {
        return false;
    }

    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare('SELECT id FROM users WHERE id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => (int) $_SESSION['user_id']]);

        if (!$stmt->fetchColumn()) {
            forceLogout('Your account was removed, so you have been logged out.');
            return false;
        }
    } catch (PDOException $e) {
        error_log('[Auth] Session validation failed: ' . $e->getMessage());
    }

    return true;
}

/**
 * Check if user has a specific role
 * @param string $role The role to check (citizen, barangay_official, admin)
 * @return bool
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Require user to be logged in
 * Redirects to login page if not
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = "Please login to access this page.";
        header('Location: ' . BASE_URL . 'views/auth/login.php');
        exit();
    }
}

/**
 * Require a specific role
 * Redirects to login if not logged in, shows 403 if wrong role
 * @param string $role Required role
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('HTTP/1.0 403 Forbidden');
        echo "<div class='min-h-screen flex items-center justify-center'><div class='text-center'><h1 class='text-4xl font-bold text-red-600'>403</h1><p class='text-xl mt-2'>Access Denied</p><a href='" . BASE_URL . "' class='mt-4 inline-block px-4 py-2 bg-emerald-500 text-white rounded-lg'>Go Home</a></div></div>";
        exit();
    }
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user name
 * @return string|null
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}

// Validate any existing session immediately so deleted accounts are logged out
// before page-specific code runs.
validateAuthenticatedSession();

/**
 * Get current user barangay ID
 * @return int|null
 */
function getCurrentBarangayId() {
    return $_SESSION['barangay_id'] ?? null;
}

// ============================================
// CREATE UPLOAD DIRECTORIES IF NOT EXIST
// ============================================
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
if (!is_dir(PROFILE_UPLOAD_DIR)) {
    mkdir(PROFILE_UPLOAD_DIR, 0777, true);
}
if (!is_dir(ANNOUNCEMENT_UPLOAD_DIR)) {
    mkdir(ANNOUNCEMENT_UPLOAD_DIR, 0777, true);
}

// ============================================
// TIMEZONE SETTING
// ============================================
date_default_timezone_set('Asia/Manila');

// ============================================
// CSRF PROTECTION HELPERS (Legacy support)
// ============================================
// Note: Use InputSanitizer for new code
// These are kept for backward compatibility

if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

// ============================================
// ERROR HANDLING FOR SESSIONS
// ============================================
// Clear old session messages after they've been displayed
// (This is handled per-page, but we keep it here for consistency)

// ============================================
// DEBUG MODE
// ============================================
// Set to true to enable debug logging
define('DEBUG_MODE', true);

function debug_log($message) {
    if (DEBUG_MODE) {
        error_log('[DEBUG] ' . $message);
    }
}