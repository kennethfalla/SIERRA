<?php
// controllers/LiveSyncController.php - Live "Messenger-style" sync endpoint.
// Read-only: returns unread count, the newest notification, and a data-version
// stamp so pages can detect new content and update in place without a manual
// refresh. No CSRF needed because nothing is mutated here.

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/helpers/SecurityHelper.php';
require_once dirname(__DIR__) . '/helpers/SettingsHelper.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$notif = new Notification($db);
$user_id = (int)$_SESSION['user_id'];
$role = (string)($_SESSION['user_role'] ?? '');

// Unread count + newest notification + highest id (reliable change detector)
$unread = $notif->getUnreadCount($user_id);
$latest = $notif->getForUser($user_id, 1);
$latest = $latest[0] ?? null;

$stmt = $db->prepare("SELECT MAX(id) FROM notifications WHERE user_id = ?");
$stmt->execute([$user_id]);
$notifSeq = (int)$stmt->fetchColumn();

// ---- Data version: latest relevant change for this role ----
$dataVersion = null;

if ($latest && !empty($latest['created_at'])) {
    $dataVersion = $latest['created_at'];
}

// Role-scoped report activity
$reportTs = null;
if ($role === 'admin' || $role === 'menro') {
    $stmt = $db->query("SELECT MAX(created_at) FROM reports WHERE is_archived = 0");
    $reportTs = $stmt->fetchColumn();
} elseif ($role === 'barangay_personnel') {
    $barangay_id = (int)($_SESSION['barangay_id'] ?? 0);
    if ($barangay_id > 0) {
        $stmt = $db->prepare("SELECT MAX(created_at) FROM reports WHERE is_archived = 0 AND barangay_id = ?");
        $stmt->execute([$barangay_id]);
        $reportTs = $stmt->fetchColumn();
    }
} else {
    $stmt = $db->prepare("SELECT MAX(created_at) FROM reports WHERE is_archived = 0 AND user_id = ?");
    $stmt->execute([$user_id]);
    $reportTs = $stmt->fetchColumn();
}
if ($reportTs && (!$dataVersion || strtotime($reportTs) > strtotime($dataVersion))) {
    $dataVersion = $reportTs;
}

// Latest active announcement (reaches citizens and barangay personnel)
if ($role !== 'admin' && $role !== 'menro') {
    $stmt = $db->query("SELECT MAX(created_at) FROM announcements WHERE is_active = 1 AND is_archived = 0 AND (expires_at IS NULL OR expires_at > NOW())");
    $annTs = $stmt->fetchColumn();
    if ($annTs && (!$dataVersion || strtotime($annTs) > strtotime($dataVersion))) {
        $dataVersion = $annTs;
    }
}

echo json_encode([
    'success'      => true,
    'unread'       => $unread,
    'notif_seq'    => $notifSeq,
    'latest'       => $latest ? [
        'id'         => (int)$latest['id'],
        'title'      => $latest['title'],
        'message'    => $latest['message'],
        'is_read'    => (int)$latest['is_read'],
        'link'       => (string)($latest['link'] ?? ''),
        'created_at' => $latest['created_at'],
    ] : null,
    'data_version' => $dataVersion,
    'server_time'  => date('Y-m-d H:i:s'),
]);
exit();