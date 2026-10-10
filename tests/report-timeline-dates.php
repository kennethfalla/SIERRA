<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function t($text) { return $text; }
function checkTimeline($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE system_settings (setting_key TEXT, setting_value TEXT)');
$db->exec("INSERT INTO system_settings VALUES ('system_name','Fixture'),('permissions_v9_migrated','1')");
class Database { public function getConnection() { return $GLOBALS['db']; } }
require_once __DIR__ . '/../models/Report.php';
$db->exec('CREATE TABLE escalations (id INTEGER PRIMARY KEY, report_id INTEGER, escalated_at TEXT, approved_at TEXT)');
$db->exec('CREATE TABLE activity_logs (action TEXT, description TEXT, status TEXT, created_at TEXT)');
$db->exec("INSERT INTO escalations VALUES (1, 7, '2026-10-04 10:15:00', '2026-10-05 11:30:00')");
$insert = $db->prepare('INSERT INTO activity_logs VALUES (?, ?, ?, ?)');
$insert->execute(['Verify Report', 'Verified report #70 and moved to In Progress', 'SUCCESS', '2026-10-01 01:00:00']);
$insert->execute(['Verify Report', 'Verified report #7 and moved to In Progress', 'FAILED', '2026-10-02 01:00:00']);
$insert->execute(['Verify Report', 'Verified report #7 and moved to In Progress', 'SUCCESS', '2026-10-03 09:10:00']);
$insert->execute(['Resolve Report', 'Resolved report #7', 'SUCCESS', '2026-10-06 12:45:00']);
$model = new Report($db);
$stored = ['id'=>7,'status'=>'resolved','created_at'=>'2026-10-01 07:00:00','viewed_at'=>'2026-10-02 08:05:00'];
$report = $model->getTimelineDates($stored);
checkTimeline($report['verified_at'] === '2026-10-03 09:10:00', 'Only successful exact-report events fill missing dates');
checkTimeline($report['approved_at'] === '2026-10-05 11:30:00', 'MENRO milestone uses actual approval time');
checkTimeline($report['resolved_at'] === '2026-10-06 12:45:00', 'Legacy resolution date comes from audit history');
$stored['verified_at'] = '2026-10-03 08:00:00';
checkTimeline($model->getTimelineDates($stored)['verified_at'] === $stored['verified_at'], 'Stored dates take precedence');
foreach (['manage','citizen'] as $context) {
    $reportProgressContext = $context;
    $reportProgressMode = 'history';
    ob_start(); include __DIR__ . '/../views/shared/report_progress.php'; $html = ob_get_clean();
    $doc = new DOMDocument(); @$doc->loadHTML($html); $xpath = new DOMXPath($doc);
    checkTimeline($xpath->query('//li/time')->length === 6, 'Every reached milestone has date and time on both pages');
    checkTimeline($xpath->query('//li[.//h4[text()="With MENRO"]]/time')->length === 1, 'MENRO approval appears in the timeline');
}
$report = $model->getTimelineDates(['id'=>9,'status'=>'pending','created_at'=>'2026-10-10 08:00:00']);
ob_start(); include __DIR__ . '/../views/shared/report_progress.php'; $html = ob_get_clean();
checkTimeline(substr_count($html, '<time ') === 1 && str_contains($html, 'Not yet reached'), 'Future stages never invent timestamps');
$report = ['status'=>'rejected','created_at'=>'2026-10-01 07:00:00','viewed_at'=>'2026-10-02 08:00:00','rejected_at'=>'2026-10-03 09:00:00'];
ob_start(); include __DIR__ . '/../views/shared/report_progress.php'; $html = ob_get_clean();
checkTimeline(substr_count($html, '<time ') === 3 && str_contains($html, 'Rejected') && !str_contains($html, '<h4>Resolved'), 'Rejection retains prior review events without future resolution');
echo "Timeline dates, legacy events, exact report matching, future stages and terminal history passed.\n";
