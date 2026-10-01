<?php
// Run through the host's PHP CLI / scheduler; never expose a browser sender.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers/EmailQueue.php';

$action = $argv[1] ?? '--run';
if (!in_array($action, ['--install', '--status', '--retry-failed', '--run'], true)) {
    fwrite(STDERR, "Usage: php scripts/email-worker.php [--install|--status|--retry-failed|--run]\n");
    exit(2);
}
try {
    $db = (new Database())->getConnection();
    require_once dirname(__DIR__) . '/helpers/SettingsHelper.php';
    $queue = new EmailQueue($db);
    if ($action === '--install') {
        $queue->install();
        echo "Email queue installed. Sending is still controlled by EMAIL_QUEUE_ENABLED.\n";
    } elseif ($action === '--status') {
        echo json_encode($queue->status(), JSON_PRETTY_PRINT) . "\n";
    } elseif ($action === '--retry-failed') {
        echo 'Requeued: ' . $queue->retryFailed() . "\n";
    } else {
        // Keep draining existing mail even when new enqueueing is switched off.
        $result = $queue->process([SettingsHelper::class, 'sendEmail']);
        echo json_encode($result) . "\n";
        $hasFailed = false;
        foreach ($queue->status() as $row) {
            if ($row['status'] === 'failed' && (int)$row['total'] > 0) $hasFailed = true;
        }
        exit($result['failed'] > 0 || $hasFailed ? 1 : 0);
    }
} catch (Throwable $e) {
    error_log('Email worker failed: ' . $e->getMessage());
    fwrite(STDERR, "Email worker failed. Check the server error log.\n");
    exit(1);
}
