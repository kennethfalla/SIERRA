<?php
// Optional scheduler entry point. Never callable through the public website.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers/IdGuard.php';
require_once dirname(__DIR__) . '/models/Notification.php';
require_once dirname(__DIR__) . '/models/ReportReminder.php';
try {
    $db = (new Database())->getConnection();
    require_once dirname(__DIR__) . '/helpers/SettingsHelper.php';
    $model = new ReportReminder($db);
    $users = $db->query("SELECT id FROM users WHERE is_active = 1 AND user_type IN ('admin', 'menro_staff', 'barangay_personnel')")->fetchAll(PDO::FETCH_COLUMN);
    $sent = 0;
    foreach ($users as $userId) $sent += $model->processForUser((int)$userId);
    echo "Report reminders sent: $sent\n";
} catch (Throwable $error) {
    error_log('[Report reminder worker] ' . $error->getMessage());
    fwrite(STDERR, "Report reminder check failed. Check the server error log.\n");
    exit(1);
}
