<?php
// Run the actual note action blocks against in-memory SQLite with the real role guard.
if (!isset($argv[1])) {
    foreach (['resolved_admin'=>false,'resolved_barangay'=>false,'active_barangay'=>true,'active_menro'=>true,'delete_own'=>true,'delete_other'=>false,'delete_resolved'=>false,'delete_other_barangay'=>false,'delete_admin'=>false,'permission_denied'=>false] as $case=>$expected) {
        $process = proc_open([PHP_BINARY, __FILE__, $case], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
        $result = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
        $data = json_decode($result,true);
        if ($exit || ($data['success'] ?? null) !== $expected || $errors) throw new RuntimeException("$case failed: $result $errors");
    }
    echo "Investigation note role, ownership and closed-status checks passed.\n";
    exit;
}
$case = $argv[1];
class SettingsHelper { static function hasPermission($id,$key) { return $GLOBALS['case'] !== 'permission_denied'; } }
class InputSanitizer { static function sanitizeString($value,$limit) { return trim($value); } }
require_once __DIR__ . '/../helpers/PermissionHelper.php';
preg_match_all('/const STATUS_\w+\s*=\s*[^;]+;/', file_get_contents(__DIR__ . '/../models/Report.php'), $constants);
eval('class Report {' . implode(' ', $constants[0]) . '}');
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW',fn()=>'2026-10-05 12:00:00');
$db->exec('CREATE TABLE reports (id INTEGER PRIMARY KEY,barangay_id INTEGER,status TEXT)');
$db->exec('CREATE TABLE report_notes (id INTEGER PRIMARY KEY,report_id INTEGER,user_id INTEGER,note TEXT,created_at TEXT)');
$status = str_contains($case,'resolved') ? 'resolved' : ($case === 'active_menro' ? 'escalated' : 'in_progress');
$db->prepare('INSERT INTO reports VALUES (1,1,?)')->execute([$status]);
$db->exec("INSERT INTO report_notes VALUES (1,1,10,'Own note','2026-10-05'),(2,1,20,'Another author','2026-10-05')");
$user_id=10;
$user_role=str_contains($case,'admin') || $case==='active_menro' ? 'admin' : 'barangay_official';
$_SESSION=['user_id'=>10,'user_role'=>$user_role,'user_type'=>$case==='active_menro'?'menro_staff':($user_role==='admin'?'admin':'barangay_personnel'),'role_id'=>1,'barangay_id'=>$case==='delete_other_barangay'?2:1];
$action=str_starts_with($case,'delete')?'delete_note':'add_note';
$_POST=['action'=>$action,'report_id'=>1,'note_id'=>$case==='delete_other'?2:1,'note'=>'Test investigation note'];
$is_ajax=true;
$activityLog=new class { function log(...$args) {} };
$source=file_get_contents(__DIR__.'/../controllers/ReportController.php');
$start=strpos($source,"    if (\$action === 'add_note'");
$end=strpos($source,"    // DELETE REPORT (AJAX or Non-AJAX)",$start);
eval(substr($source,$start,$end-$start));
