<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Uses ONLY a disposable local MySQL instance and a copied app with test settings.
// The real config/env.php is never included.
function check($value, string $message): void {
    if (!$value) throw new RuntimeException($message);
    echo "PASS $message\n";
}
$db = new PDO('mysql:host=127.0.0.1;port=13308;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$schema = 'sierra_perf_test_' . bin2hex(random_bytes(6));
$db->exec("CREATE DATABASE `$schema`");
$db->exec("USE `$schema`");
$sandbox = sys_get_temp_dir() . '/' . $schema;
$files = ['config/database.php', 'helpers/SchemaMigration.php', 'helpers/SettingsHelper.php', 'helpers/EmailQueue.php', 'models/Report.php', 'models/Notification.php', 'models/ActivityLog.php', 'models/UserDevice.php'];
mkdir($sandbox);
foreach (['config', 'helpers', 'models'] as $folder) mkdir($sandbox . '/' . $folder);
foreach ($files as $file) copy(dirname(__DIR__) . '/' . $file, $sandbox . '/' . $file);
file_put_contents($sandbox . '/config/env.php', "<?php define('DB_HOST', '127.0.0.1'); define('DB_PORT', '13308'); define('DB_NAME', '$schema'); define('DB_USER', 'root'); define('DB_PASSWORD', '');");
try {
    $db->exec(require __DIR__ . '/schema-fixture.php');
    require_once $sandbox . '/config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $migration = new ReflectionMethod(Database::class, 'ensureColumns');
    $migration->setAccessible(true);
    // Avoid unrelated permission bootstrap; fixtures contain no role tables.
    $db->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES ('system_name', 'QA'), ('permissions_v9_migrated', '1')");
    foreach (['Report', 'Notification', 'ActivityLog', 'UserDevice'] as $model) require_once $sandbox . '/models/' . $model . '.php';
    require_once $sandbox . '/helpers/EmailQueue.php';
    check($db->query("SELECT setting_value FROM system_settings WHERE setting_key = '_app_schema_version'")->fetchColumn() === Database::SCHEMA_VERSION, 'legacy schema migrates and stores completion in the database');
    check(count($db->query("SHOW COLUMNS FROM activity_logs WHERE Field IN ('user_agent', 'status')")->fetchAll()) === 2, 'legacy activity metadata columns migrate');
    check(count($db->query("SHOW COLUMNS FROM reports WHERE Field IN ('rejected_at', 'rejection_reason', 'cancelled_at', 'cancellation_remarks')")->fetchAll()) === 4, 'report reasons migrate before page and OTP requests');
    check((int)$db->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn() === 5
        && (int)$db->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn() === 5, 'database lock waits have a five-second ceiling');
    check((int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'remember_tokens'")->fetchColumn() === 1, 'remember-me storage is prepared before login');
    check((int)$db->query("SELECT COUNT(DISTINCT CONCAT(table_name, '/', index_name)) FROM information_schema.statistics WHERE table_schema = DATABASE() AND index_name IN ('idx_notif_recent','idx_verification_active','idx_report_images_report','idx_reports_status_created')")->fetchColumn() === 4, 'notification, OTP, image, and status lookups are indexed');
    check((int)$db->query("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'reports' AND index_name IN ('idx_reports_created_status','idx_reports_barangay_created','idx_reports_user_created')")->fetchColumn() === 3, 'dashboard and per-user report scopes have indexes');
    $ran = false;
    SchemaMigration::run($db, Database::SCHEMA_VERSION, function () use (&$ran) { $ran = true; return true; });
    check(!$ran, 'completed version skips schema work without a filesystem marker');
    $other = new PDO('mysql:host=127.0.0.1;port=13308;dbname=' . $schema, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $lockName = 'sierra-schema-' . substr(hash('sha256', $schema), 0, 40);
    $hold = $other->prepare('SELECT GET_LOCK(?, 0)'); $hold->execute([$lockName]);
    $started = microtime(true); $contended = false;
    try { SchemaMigration::run($db, 'concurrent-test', static function () { throw new RuntimeException('A competing migration must not start'); }); }
    catch (PDOException $e) { $contended = true; }
    $release = $other->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lockName]);
    check($contended && microtime(true) - $started < 1, 'a competing schema update returns immediately instead of blocking a worker');
    try { SchemaMigration::run($db, 'failed-test', static function () { return false; }); }
    catch (PDOException $e) { /* Expected: never mark failure as complete. */ }
    check($db->query("SELECT setting_value FROM system_settings WHERE setting_key = '_app_schema_version'")->fetchColumn() === Database::SCHEMA_VERSION, 'failed migration preserves the last successful version');
    $db->exec("INSERT INTO announcements (barangay_id, is_public, broadcast_type) VALUES (1, 1, 'localized_public')");
    SchemaMigration::run($db, 'next-test', function () use ($migration, $database) { return $migration->invoke($database); });
    check($db->query('SELECT broadcast_type FROM announcements LIMIT 1')->fetchColumn() === 'localized_public', 'repeat migration preserves existing announcement targeting');

    $db->exec("INSERT INTO users VALUES (1, 'Test', 'Citizen', 'test@example.invalid', '0', 0, NULL, NULL, 1), (2, 'Other', 'Citizen', 'other@example.invalid', '0', 0, NULL, NULL, 1)");
    $db->exec("INSERT INTO categories (id, name, icon_class) VALUES (1, 'Waste', 'fa-trash')");
    $db->exec("INSERT INTO barangays (id, name) VALUES (1, 'Test'), (2, 'Other')");
    $insert = $db->prepare('INSERT INTO reports (user_id, category_id, barangay_id, title, description, status, risk_level, created_at) VALUES (?, 1, ?, ?, ?, ?, ?, ?)');
    foreach (['pending', 'under_review', 'verified', 'in_progress', 'escalated_pending', 'escalated', 'resolved', 'closed', 'rejected', 'cancelled'] as $status) {
        for ($i = 0; $i < 8; $i++) $insert->execute([1, 1, 'Match waste', 'Test report', $status, 'high', '2026-10-01 10:00:00']);
    }
    $insert->execute([2, 2, 'Match waste', 'Other jurisdiction', 'pending', 'high', '2026-10-01 10:00:00']);
    $report = new Report($db);
    check(count($report->getReportsByUser(1, 5)->fetchAll()) === 5, 'citizen recent reports are limited to five');
    check(count($report->getReportsByUser(1)->fetchAll()) === 72, 'unlimited report callers remain compatible and cancelled reports stay excluded');
    $counts = $report->getUserStatusCounts(1);
    check($counts['in_progress'] === 8 && array_sum($counts) - $counts['cancelled'] === 72, 'full totals remain accurate beyond the recent-report limit and respect user scope');
    $recent = $report->getReportsByUser(1, 5)->fetchAll();
    check(array_column($recent, 'id') === [72, 71, 70, 69, 68], 'equal timestamps have deterministic recent-report ordering');

    $view = file_get_contents(dirname(__DIR__) . '/views/barangay/dashboard.php');
    $barangay_id = 1; $fC_sql = ''; $fC_params = [1];
    $f_date_from = '2026-10-01'; $f_date_to = '2026-10-01'; $f_risk = 'high';
    $f_search_sql = ' AND (r.title LIKE ? OR r.description LIKE ?)'; $f_search_params = ['%Match%', '%Match%'];
    $start = strpos($view, '$fLegend_sql ='); $end = strpos($view, '$escalated_to_menro_params =', $start);
    eval(substr($view, $start, $end - $start));
    check($pending_count === 8 && $resolved_count === 8 && $total_reports === 64, 'barangay date/risk/search filters and jurisdiction apply to full status totals');
    $start = strpos($view, '$recent_reports = $db->prepare('); $end = strpos($view, '// ============================================================', $start);
    eval(substr($view, $start, $end - $start));
    check(count($recent_reports_rows) === 10, 'barangay recent reports are limited to ten');

    $notification = new Notification($db);
    $notification->create(1, 'Test', 'Test');
    $notification->create(1, 'Second', 'Test');
    $notification->create(2, 'Other', 'Test');
    $sync = $notification->getSyncSummary(1);
    check($sync['unread'] === 2 && $sync['notif_seq'] === 2, 'notification summary excludes other users');
    $notification->markRead(1, 1);
    check($notification->getSyncSummary(1)['unread'] === 1, 'notification summary reflects read actions');
    $sync = $notification->getSyncSummary(999);
    check($sync['unread'] === 0 && $sync['notif_seq'] === 0, 'empty notification account returns zero counts');
    (new ActivityLog($db))->log(1, 'Test', 'Isolated QA');
    check((new UserDevice($db))->registerLogin(1, 'QA browser', '127.0.0.1')['is_new'] === true, 'activity logs and recognized devices work after centralized migration');

    $queue = new EmailQueue($db); $queue->install();
    check(!$queue->enqueue('invalid', '', 'Test', 'Test'), 'queue rejects invalid recipients');
    $queue->enqueue('first@example.invalid', 'Test', 'Test', '<p>Test</p>');
    $queue->enqueue('second@example.invalid', 'Test', 'Test', '<p>Test</p>');
    $sent = [];
    $queue->process(function ($email) use ($queue, &$sent) {
        $sent[] = $email;
        // Simulate an overlapping worker while this sender holds a live lease.
        $queue->process(function ($next) use (&$sent) { $sent[] = $next; return true; }, 1);
        return true;
    }, 1);
    check(count($sent) === 2 && count(array_unique($sent)) === 2, 'overlapping workers cannot send the same leased message');
    check((int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'sent' AND html_body = '' AND recipient = ''")->fetchColumn() === 2, 'successful deliveries remove stored personal message content');
    $queue->enqueue('retry@example.invalid', 'Test', 'Retry', 'Test');
    $queue->process(static function () { return false; }, 1);
    check($queue->process(static function () { throw new RuntimeException('Should not run before due'); }, 1) === ['sent' => 0, 'failed' => 0], 'failed send waits for persisted retry backoff');
    for ($i = 0; $i < 4; $i++) {
        $db->exec("UPDATE email_queue SET available_at = 0 WHERE status = 'pending'");
        $queue->process(static function () { throw new RuntimeException('Simulated network failure'); }, 1);
    }
    check((int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'failed' AND attempts = 5")->fetchColumn() === 1, 'retries stop after five failures and retain the message');
    check($queue->retryFailed() === 1, 'failed messages can be explicitly retried');
    $db->exec("UPDATE email_queue SET status = 'sending', lease_until = 0, lease_token = 'interrupted' WHERE status = 'pending'");
    check($queue->process(static function () { return true; }, 1)['sent'] === 1, 'expired worker leases recover after interruption');
    echo "All backend checks passed; no real email sent.\n";
} finally {
    // Name is generated locally above and never accepts an external database.
    $db->exec("DROP DATABASE `$schema`");
    foreach ($files as $file) unlink($sandbox . '/' . $file);
    unlink($sandbox . '/config/env.php');
    foreach (['config', 'helpers', 'models'] as $folder) rmdir($sandbox . '/' . $folder);
    rmdir($sandbox);
}
