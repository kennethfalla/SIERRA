<?php
// In-memory regression tests: never connects to the application database.
function expect($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE system_settings (setting_key TEXT, setting_value TEXT)');
$db->exec("INSERT INTO system_settings VALUES ('system_name','Test'),('permissions_v9_migrated','1')");
class Database { public function getConnection() { return $GLOBALS['db']; } }
require_once __DIR__ . '/../models/Report.php';
require_once __DIR__ . '/../helpers/ReporterDemographics.php';
$db->sqliteCreateFunction('CONCAT', static function (...$parts) { return in_array(null,$parts,true) ? null : implode('',$parts); });
$db->exec('CREATE TABLE users (id INTEGER, first_name TEXT,last_name TEXT,email TEXT,contact_number TEXT,is_resident INTEGER,purok_street TEXT,non_resident_address TEXT,province TEXT,municipality TEXT)');
$db->exec('CREATE TABLE categories (id INTEGER,name TEXT,icon_class TEXT,description TEXT)');
$db->exec('CREATE TABLE barangays (id INTEGER,name TEXT,zone TEXT)');
$db->exec('CREATE TABLE reports (id INTEGER,user_id INTEGER,category_id INTEGER,barangay_id INTEGER,title TEXT,created_at TEXT,status TEXT)');
$db->exec('CREATE TABLE report_images (id INTEGER,report_id INTEGER,image_path TEXT,is_primary INTEGER)');
$db->exec("INSERT INTO users (id,first_name,last_name,is_resident) VALUES (1,'Ana','Reyes',1),(2,'Ben','Cruz',0)");
$db->exec("INSERT INTO categories (id,name) VALUES (1,'Flooding')");
$db->exec("INSERT INTO barangays (id,name) VALUES (1,'Poblacion')");
$db->exec("INSERT INTO reports VALUES (1,1,1,1,'Urgent flooding','2026-10-01','pending'),(2,1,NULL,1,'Missing category','2026-10-02','pending'),(3,2,1,NULL,'Missing barangay','2026-10-02','pending'),(4,999,1,1,'Missing account','2026-10-03','pending'),(5,2,1,2,'Other barangay','2026-10-03','resolved')");
$model = new Report($db);
foreach (['getReportById','getReportWithDetails'] as $method) {
    foreach ([1,2,3,4] as $id) expect((int)$model->$method($id)['id']===$id,"$method lost report $id");
    expect((int)$model->$method(4)['user_id']===999,'Owner identity must survive a missing linked account');
    expect($model->$method(2)['category_name']==='Uncategorized','Missing category needs a fallback');
    expect($model->$method(99)===false,'A nonexistent report must still be rejected');
}
expect(ReporterDemographics::summarize($db,'1=1',[])===['resident'=>1,'non_resident'=>1,'unknown'=>1],'Count distinct reporters, including unrecorded residency');
expect(ReporterDemographics::summarize($db,'r.barangay_id = ?',[1])===['resident'=>1,'non_resident'=>0,'unknown'=>1],'Keep barangay scope');
expect(ReporterDemographics::summarize($db,'r.created_at >= ? AND r.status = ?',['2026-10-03','resolved'])===['resident'=>0,'non_resident'=>1,'unknown'=>0],'Respect date and status filters');
$db->exec('UPDATE users SET is_resident = 0 WHERE id=1');
expect(ReporterDemographics::summarize($db,'1=1',[])['non_resident']===2,'Use the current profile');
// Exercise the map details endpoint's actual query without connecting to a live database.
$db->exec('CREATE TABLE resolution_evidence (report_id INTEGER,image_path TEXT)');
$db->exec("INSERT INTO report_images (report_id,image_path) VALUES (1,'uploads/reports/photo.jpg')");
$db->exec("INSERT INTO resolution_evidence VALUES (1,'uploads/reports/resolved.jpg')");
$controllerSource = file_get_contents(__DIR__ . '/../controllers/ReportController.php');
$detailsBlock = substr($controllerSource, strpos($controllerSource, "if (\$action === 'get_full' && \$report_id > 0)"));
expect((bool)preg_match('/\$db->prepare\("(.*?)"\)/s', $detailsBlock, $queryMatch),'Map details query is available');
$detailsStmt = $db->prepare($queryMatch[1]);
foreach ([1,2,3,4] as $id) {
    $detailsStmt->execute([$id]);
    $details = $detailsStmt->fetch(PDO::FETCH_ASSOC);
    expect((int)$details['id'] === $id,"Map panel lost report $id with a missing linked record");
    if ($id === 1) expect($details['image_paths'] === 'uploads/reports/photo.jpg' && $details['resolution_evidence_paths'] === 'uploads/reports/resolved.jpg','Map details retain both evidence types');
    if ($id === 2) expect($details['category_name'] === 'Uncategorized','Map details keep uncategorized reports');
}
echo "Report lookup, map details, and reporter demographics checks passed.\n";
