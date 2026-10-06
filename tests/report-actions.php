<?php
// Run controller cancellation branches against a disposable in-memory database.
if (!isset($argv[1])) {
    $photos = new PDO('sqlite::memory:');
    $photos->exec('CREATE TABLE reports (id INTEGER); CREATE TABLE report_images (id INTEGER,report_id INTEGER,image_path TEXT,is_primary INTEGER)');
    $photos->exec("INSERT INTO reports VALUES (22); INSERT INTO report_images VALUES (1,22,'uploads/reports/video.mp4',1),(2,22,'uploads/reports/new.JPG',1),(3,22,'uploads/reports/other.png',0)");
    preg_match('/\(SELECT ri.image_path.*?LIMIT 1\)/s', file_get_contents(__DIR__.'/../views/citizen/my_reports.php'), $coverQuery);
    if ($photos->query('SELECT '.$coverQuery[0].' AS cover_image FROM reports r')->fetchColumn() !== 'uploads/reports/new.JPG') throw new RuntimeException('New report photo must be selected ahead of video/secondary evidence');
    foreach (['valid', 'blank', 'not_owner', 'in_progress', 'disabled', 'staff'] as $case) {
        $child = proc_open([PHP_BINARY, __FILE__, $case], [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']], $pipes);
        fclose($pipes[0]);
        $result = json_decode(stream_get_contents($pipes[1]), true);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($child) !== 0 || !$result) throw new RuntimeException('Cancellation fixture failed: ' . $error);
        if ($case === 'valid') {
            if (!$result['response']['success'] || $result['report']['status'] !== 'cancelled' || $result['report']['cancellation_remarks'] !== 'Submitted by mistake' || !$result['report']['cancelled_at']) throw new RuntimeException('Cancellation reason was not persisted');
        } elseif ($result['response']['success'] || $result['report']['status'] === 'cancelled') throw new RuntimeException('Invalid cancellation changed the report: ' . $case);
    }
    echo "New report photo, cancellation reason, and permission tests passed.\n";
    exit;
}
$case = $argv[1];
define('BASE_URL','/fixture/');
class SettingsHelper { static function get($key,$default) { return $GLOBALS['case'] === 'disabled' ? '0' : $default; } }
class InputSanitizer { static function sanitizeString($text,$limit) { return trim(substr(strip_tags($text),0,$limit)); } }
class Report { const STATUS_PENDING='pending'; const STATUS_CANCELLED='cancelled'; }
function trackStatusUrl($id) { return '/fixture/track/' . $id; }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW',fn()=>'2026-10-07 10:00:00');
$db->exec('CREATE TABLE reports (id INTEGER PRIMARY KEY,user_id INTEGER,status TEXT,latitude REAL,longitude REAL,cancellation_remarks TEXT,cancelled_at TEXT)');
$db->prepare('INSERT INTO reports (id,user_id,status) VALUES (1,17,?)')->execute([$case === 'in_progress' ? 'in_progress' : 'pending']);
$user_id = $case === 'not_owner' ? 18 : 17;
$user_role = $case === 'staff' ? 'barangay_official' : 'citizen';
$is_ajax = true;
$action = 'cancel_report';
$_POST = ['report_id'=>1,'cancellation_remarks'=>$case === 'blank' ? '' : 'Submitted by mistake'];
$activityLog = new class { function log(...$args) {} };
ob_start();
register_shutdown_function(function () use ($db) {
    $response = json_decode(ob_get_clean(),true);
    echo json_encode(['response'=>$response,'report'=>$db->query('SELECT * FROM reports')->fetch(PDO::FETCH_ASSOC)]);
});
$source=file_get_contents(__DIR__.'/../controllers/ReportController.php');
$start=strpos($source,"    if (\$action === 'cancel_report')");
$end=strpos($source,"    if (\$action === 'verify_report')",$start);
eval(substr($source,$start,$end-$start));
