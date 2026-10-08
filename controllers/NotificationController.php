<?php
// controllers/NotificationController.php - AJAX endpoints for the in-app notification bell
// Actions: get_unread_count, mark_all_read, clear_all, mark_read
// All actions require login; mutating actions require a valid CSRF token.

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/helpers/SecurityHelper.php';
require_once dirname(__DIR__) . '/helpers/SettingsHelper.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated.']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$notif = new Notification($db);
$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'citizen';
$barangay_id = $_SESSION['barangay_id'] ?? null;

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// ============================================
// GET UNREAD COUNT (read-only, also used on page load polling)
// ============================================
if ($action === 'get_unread_count') {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $summary = $notif->getSyncSummary($user_id, $user_role, $barangay_id);
    echo json_encode(['success' => true, 'unread_count' => $summary['unread'], 'sidebar_counts' => $summary['sidebar_counts']]);
    exit();
}

// Mutating actions require CSRF
if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
    echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
    exit();
}

// CSRF verification is complete; these actions do not write session data.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

// ============================================
// MARK ALL AS READ
// ============================================
if ($action === 'mark_all_read') {
    $updated = $notif->markAllRead($user_id);
    $summary = $notif->getSyncSummary($user_id, $user_role, $barangay_id);
    echo json_encode(['success' => true, 'updated' => $updated, 'unread_count' => $summary['unread'], 'sidebar_counts' => $summary['sidebar_counts']]);
    exit();
}

// ============================================
// MARK SINGLE AS READ
// ============================================
if ($action === 'mark_read') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid notification.']);
        exit();
    }
    $notif->markRead($user_id, $id);
    $summary = $notif->getSyncSummary($user_id, $user_role, $barangay_id);
    echo json_encode(['success' => true, 'unread_count' => $summary['unread'], 'sidebar_counts' => $summary['sidebar_counts']]);
    exit();
}

// ============================================
// CLEAR ALL (permanently delete)
// ============================================
if ($action === 'delete_selected') {
    $ids = json_decode($_POST['ids'] ?? '[]', true);
    if (!is_array($ids) || !$ids || count($ids) > 100 || array_filter($ids, fn($id) => !is_scalar($id) || !ctype_digit((string)$id) || (int)$id <= 0)) {
        echo json_encode(['error' => 'Select valid notifications to delete.']);
        exit();
    }
    $deleted = $notif->deleteSelected($user_id, $ids);
    $summary = $notif->getSyncSummary($user_id, $user_role, $barangay_id);
    echo json_encode(['success' => true, 'deleted' => $deleted, 'unread_count' => $summary['unread'], 'sidebar_counts' => $summary['sidebar_counts']]);
    exit();
}

if ($action === 'clear_all') {
    $deleted = $notif->clearAll($user_id);
    $summary = $notif->getSyncSummary($user_id, $user_role, $barangay_id);
    echo json_encode(['success' => true, 'deleted' => $deleted, 'unread_count' => $summary['unread'], 'sidebar_counts' => $summary['sidebar_counts']]);
    exit();
}

echo json_encode(['error' => 'Invalid action.']);
exit();
