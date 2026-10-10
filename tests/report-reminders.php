<?php
// Isolated database; never connects to the application database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('UTC');
function checkReminder($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('current_timestamp', function () { return '2026-10-10 12:00:00'; });
$db->sqliteCreateFunction('NOW', function () { return '2026-10-10 12:00:00'; });
$db->exec('CREATE TABLE system_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
$db->exec("INSERT INTO system_settings VALUES ('system_name','Fixture'), ('permissions_v9_migrated','1'), ('enable_report_reminders','1'), ('report_reminder_days','3')");
class Database { public function getConnection() { return $GLOBALS['db']; } }
require_once __DIR__ . '/../helpers/SettingsHelper.php';
require_once __DIR__ . '/../helpers/IdGuard.php';
require_once __DIR__ . '/../models/Notification.php';
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, user_type TEXT, barangay_id INTEGER, is_active INTEGER)');
$db->exec("INSERT INTO users VALUES (1,'barangay_personnel',1,1),(2,'barangay_personnel',2,1),(3,'menro_staff',NULL,1),(4,'admin',NULL,1),(5,NULL,1,1),(6,'barangay_personnel',1,0),(7,'barangay_personnel',NULL,1)");
$db->exec('CREATE TABLE barangays (id INTEGER PRIMARY KEY, name TEXT)');
$db->exec("INSERT INTO barangays VALUES (1,'Calaba'), (2,'San Roque'), (3,'Alua')");
$db->exec('CREATE TABLE reports (id INTEGER PRIMARY KEY, title TEXT, status TEXT, barangay_id INTEGER, created_at TEXT, escalated_at TEXT, is_archived INTEGER DEFAULT 0)');
$db->exec('CREATE TABLE escalations (id INTEGER PRIMARY KEY, report_id INTEGER, escalated_at TEXT)');
$db->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER, report_id INTEGER, title TEXT, message TEXT, type TEXT, icon TEXT, color TEXT, link TEXT, is_read INTEGER, created_at TEXT)');
$db->exec('CREATE TABLE report_reminder_checks (user_id INTEGER PRIMARY KEY, checked_at TEXT, settings_key TEXT)');
$db->exec('CREATE TABLE report_reminder_deliveries (report_id INTEGER, user_id INTEGER, stage TEXT, started_at TEXT, delivered_at TEXT, PRIMARY KEY(user_id,report_id,stage,started_at))');
$now = new DateTimeImmutable($db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn());
$at = function ($offset) use ($now) { return $now->modify($offset)->format('Y-m-d H:i:s'); };
$insert = $db->prepare('INSERT INTO reports (id,title,status,barangay_id,created_at,escalated_at,is_archived) VALUES (?,?,?,?,?,?,?)');
foreach ([
    [1,'pending',1,'-3 days',null,0], [2,'pending',1,'-3 days +1 second',null,0],
    [3,'under_review',1,'-10 days',null,0], [4,'escalated',1,'-9 days','-2 days',0],
    [5,'escalated_pending',1,'-9 days','-3 days',0], [6,'escalated',1,'-9 days','-4 days',0],
    [7,'pending',2,'-7 days',null,0], [8,'resolved',1,'-10 days','-5 days',0],
    [9,'escalated',1,'-9 days',null,0], [10,'pending',1,'-9 days',null,1],
    [11,'escalated',1,'-9 days',null,0], [12,'escalated',2,'-9 days','-5 days',0],
] as [$id,$status,$barangay,$created,$escalated,$archived]) {
    $insert->execute([$id,"Report $id",$status,$barangay,$at($created),$escalated ? $at($escalated) : null,$archived]);
}
$db->prepare('INSERT INTO escalations VALUES (1,9,?)')->execute([$at('-4 days')]);
$model = new ReportReminder($db);
$queue = $model->summaryForUser(1);
checkReminder($queue['pending'] === 1 && $queue['escalated'] === 3, 'Exact 3-day threshold, fresh escalation, terminal states, archive and fallback timestamp');
checkReminder($model->summaryForUser(3)['total'] === 4, 'MENRO sees overdue escalations across Barangays, not pending citizen reports');
checkReminder($model->summaryForUser(2)['total'] === 2, 'Barangay queue is jurisdiction scoped');
foreach ([5,6,7,999] as $user) {
    checkReminder($model->summaryForUser($user)['total'] === 0 && $model->processForUser($user) === 0, 'Citizens, inactive, missing and unassigned accounts cannot receive staff reminders');
}
checkReminder($model->processForUser(1) === 4, 'Barangay reminder delivery');
checkReminder($model->processForUser(1) === 0 && $model->processForUser(1, true) === 0, 'Polling and forced scans never duplicate delivery');
checkReminder($model->processForUser(3) === 4 && $model->processForUser(4) === 4 && $model->processForUser(2) === 2, 'Both MENRO/admin and originating Barangay receive escalations');
$notice = $db->query('SELECT * FROM notifications WHERE user_id=1 AND report_id=1')->fetch(PDO::FETCH_ASSOC);
parse_str(parse_url($notice['link'], PHP_URL_QUERY), $query);
checkReminder(IdGuard::dec($query['id']) === 1 && $query['page'] === 'manage-report', 'Reminder opens the signed report link');
$db->exec('DELETE FROM notifications WHERE user_id=1');
checkReminder($model->processForUser(1, true) === 0 && $model->summaryForUser(1)['total'] === 4, 'Deleting notifications does not resend them or remove dashboard follow-ups');
$db->exec("UPDATE reports SET status='escalated' WHERE id=5");
checkReminder($model->processForUser(1, true) === 0, 'Escalation acceptance does not reset age or resend the stage reminder');
$db->prepare('UPDATE reports SET escalated_at=? WHERE id=6')->execute([$at('-6 days')]);
checkReminder($model->processForUser(1, true) === 1, 'A new escalation timestamp starts a new delivery cycle');
$db->exec("UPDATE system_settings SET setting_value='1' WHERE setting_key='report_reminder_days'");
SettingsHelper::clearCache();
checkReminder($model->processForUser(1) === 2, 'A shorter saved threshold takes effect immediately, without waiting for the polling throttle');
$db->exec("UPDATE system_settings SET setting_value='0' WHERE setting_key='enable_report_reminders'");
SettingsHelper::clearCache();
checkReminder($model->processForUser(1, true) === 0 && !$model->summaryForUser(1)['enabled'], 'Independent reminder switch disables notifications and dashboard queue');
$db->exec("UPDATE system_settings SET setting_value='1' WHERE setting_key='enable_report_reminders'");
$db->exec("UPDATE system_settings SET setting_value='3' WHERE setting_key='report_reminder_days'");
SettingsHelper::clearCache();
$db->exec("UPDATE reports SET status='resolved' WHERE id=6");
checkReminder(!in_array('Report 6', array_column($model->summaryForUser(1)['reports'], 'title'), true), 'Resolving a report immediately removes its dashboard reminder');
$db->exec("INSERT INTO users VALUES (99,'barangay_personnel',3,1)");
for ($i=100; $i<201; $i++) $insert->execute([$i,"Backlog $i",'pending',3,$at('-7 days'),null,0]);
checkReminder($model->processForUser(99) === 50 && $model->processForUser(99) === 0, 'Bounded scan and throttle protect web requests');
checkReminder($model->processForUser(99, true) === 50 && $model->processForUser(99, true) === 1, 'Large backlogs drain across bounded batches without starvation');
checkReminder($model->summaryForUser(99)['total'] === 101 && count($model->summaryForUser(99)['reports']) === 5, 'Dashboard count covers the full backlog and shows 5 oldest reports');
$db->exec("INSERT INTO users VALUES (88,'barangay_personnel',1,1)");
$db->exec("CREATE TRIGGER fail_notice BEFORE INSERT ON notifications WHEN NEW.user_id=88 BEGIN SELECT RAISE(ABORT,'Fixture failure'); END");
$failed = false;
try { $model->processForUser(88); } catch (Throwable $error) { $failed = true; }
checkReminder($failed && !$db->inTransaction() && (int)$db->query('SELECT COUNT(*) FROM report_reminder_deliveries WHERE user_id=88')->fetchColumn() === 0, 'Notification failure rolls back the delivery ledger');
$db->exec('DROP TRIGGER fail_notice');
checkReminder($model->processForUser(88) > 0, 'Failed delivery retries immediately instead of being lost');
echo "Report reminder timing, roles, Barangay scope, deduplication, stage changes, settings, batching and recovery passed.\n";
