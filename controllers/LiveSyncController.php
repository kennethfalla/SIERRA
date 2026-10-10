<?php
// controllers/LiveSyncController.php - Live "Messenger-style" sync endpoint.
// Returns unread count, the newest notification, and a data-version
// stamp so pages can detect new content and update in place without a manual
// refresh. Staff polling also runs the throttled automatic reminder check.

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/helpers/SecurityHelper.php';
require_once dirname(__DIR__) . '/helpers/SettingsHelper.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit();
}

// Capture authentication before releasing this user's session lock.
$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'citizen';
$barangay_id = $_SESSION['barangay_id'] ?? null;
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
header('Cache-Control: no-store');

$database = new Database();
$db = $database->getConnection();
$notif = new Notification($db);
$summary = $notif->getSyncSummary($user_id, $user_role, $barangay_id);
$unread = $summary['unread'];
$notifSeq = $summary['notif_seq'];
$latest = $notif->getForUser($user_id, 1);
$latest = $latest[0] ?? null;
// Notification updates stay separate from own-report and verification counts.
$dataVersion = $latest['created_at'] ?? null;
$reportReminders = null;
if (($_GET['dashboard_reminders'] ?? '') === '1' && in_array($user_role, ['admin', 'barangay_official'], true)) {
    try { $reportReminders = (new ReportReminder($db))->summaryForUser($user_id); }
    catch (Throwable $error) { error_log('[Report reminders dashboard] ' . $error->getMessage()); }
}

echo json_encode([
    'success'      => true,
    'unread'       => $unread,
    'notif_seq'    => $notifSeq,
    'sidebar_counts' => $summary['sidebar_counts'],
    'latest'       => $latest ? [
        'id'         => (int)$latest['id'],
        'title'      => $latest['title'],
        'message'    => $latest['message'],
        'is_read'    => (int)$latest['is_read'],
        'icon'       => (string)($latest['icon'] ?? 'fa-bell'),
        'color'      => (string)($latest['color'] ?? '#10A37F'),
        'link'       => (string)($latest['link'] ?? ''),
        'created_at' => $latest['created_at'],
    ] : null,
    'data_version' => $dataVersion,
    'report_reminders' => $reportReminders,
    'server_time'  => date('Y-m-d H:i:s'),
]);
exit();
