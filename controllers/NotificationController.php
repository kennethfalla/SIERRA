<?php
// controllers/NotificationController.php - AJAX endpoints for the in-app notification bell
// Actions: get_unread_count, mark_all_read, clear_all, mark_read
// All actions require login; mutating actions require a valid CSRF token.

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/helpers/SecurityHelper.php';
require_once dirname(__DIR__) . '/helpers/SettingsHelper.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated.']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$notif = new Notification($db);
$user_id = (int)$_SESSION['user_id'];

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// ============================================
// GET UNREAD COUNT (read-only, also used on page load polling)
// ============================================
if ($action === 'get_unread_count') {
    echo json_encode(['success' => true, 'unread_count' => $notif->getUnreadCount($user_id)]);
    exit();
}

// Mutating actions require CSRF
if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
    echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
    exit();
}

// ============================================
// MARK ALL AS READ
// ============================================
if ($action === 'mark_all_read') {
    $updated = $notif->markAllRead($user_id);
    echo json_encode(['success' => true, 'updated' => $updated]);
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
    echo json_encode(['success' => true, 'unread_count' => $notif->getUnreadCount($user_id)]);
    exit();
}

// ============================================
// CLEAR ALL (permanently delete)
// ============================================
if ($action === 'clear_all') {
    $deleted = $notif->clearAll($user_id);
    echo json_encode(['success' => true, 'deleted' => $deleted]);
    exit();
}

echo json_encode(['error' => 'Invalid action.']);
exit();
