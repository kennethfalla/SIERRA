<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../models/Search.php';
function searchCheck($condition,string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}
$searchMysql = in_array('--mysql',$argv,true);
if ($searchMysql) {
    // Only a disposable database on the local test server; never config/env.php.
    $db = new PDO('mysql:host=127.0.0.1;port=13308;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $searchSchema = 'sierra_search_test_'.bin2hex(random_bytes(6));
    $db->exec("CREATE DATABASE `$searchSchema` CHARACTER SET utf8mb4");
    $db->exec("USE `$searchSchema`");
    register_shutdown_function(function() use ($db,$searchSchema) { $db->exec("DROP DATABASE IF EXISTS `$searchSchema`"); });
} else {
    $db = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->sqliteCreateFunction('CONCAT',fn(...$parts)=>implode('',$parts));
}
$db->exec('CREATE TABLE reports (id INTEGER PRIMARY KEY,user_id INTEGER,barangay_id INTEGER,category_id INTEGER,title TEXT,description TEXT,location_address TEXT,status TEXT,risk_level TEXT,created_at TEXT)');
$db->exec('CREATE TABLE report_verifications (report_id INTEGER,user_id INTEGER)');
$db->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY,name TEXT)');
$db->exec('CREATE TABLE barangays (id INTEGER PRIMARY KEY,name TEXT)');
$db->exec('CREATE TABLE announcements (id INTEGER PRIMARY KEY,title TEXT,content TEXT,barangay_id INTEGER,target_admin_id INTEGER,broadcast_type TEXT,is_archived INTEGER,expires_at TEXT,created_at TEXT)');
$db->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY,title TEXT,message TEXT,type TEXT,user_id INTEGER,created_at TEXT)');
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,email TEXT,contact_number TEXT,purok_street TEXT,non_resident_address TEXT,job_title TEXT,barangay_id INTEGER,user_type TEXT,is_resident INTEGER,is_active INTEGER,created_at TEXT)');
$db->exec('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY,action TEXT,description TEXT,actor_name TEXT,actor_role TEXT,target_module TEXT,status TEXT,created_at TEXT)');
if ($searchMysql) {
    $db->exec('ALTER TABLE reports ADD FULLTEXT ft_search_reports (title,description,location_address)');
    $db->exec('ALTER TABLE announcements ADD FULLTEXT ft_search_announcements (title,content)');
    $db->exec('ALTER TABLE notifications ADD FULLTEXT ft_search_notifications (title,message)');
    $db->exec('ALTER TABLE users ADD FULLTEXT ft_search_users (first_name,last_name,email)');
    $db->exec('ALTER TABLE activity_logs ADD FULLTEXT ft_search_activity (action,description,actor_name)');
}
$db->exec("INSERT INTO categories VALUES (1,'Drainage'),(2,'Waste'); INSERT INTO barangays VALUES (1,'Calaba'),(2,'Alua')");
$report = $db->prepare('INSERT INTO reports VALUES (?,?,?,?,?,?,?,?,?,?)');
foreach ([
    [1,10,1,1,'Drainage blockage','Water is running across the road','Market','pending','high','2026-10-01 09:00:00'],
    [2,10,1,2,'Waste concern',str_repeat('Details. ',100).'Garbage in the stream','Riverside','in_progress','medium','2026-10-02 09:00:00'],
    [3,20,2,1,'Private drainage','Other household running water','Alua','pending','low','2026-10-03 09:00:00'],
    [4,20,2,1,'Supported drainage','Reporter ran to check the source','Alua','resolved','low','2026-10-04 09:00:00'],
] as $row) $report->execute($row);
$db->exec('INSERT INTO report_verifications VALUES (4,10)');
$announcement = $db->prepare('INSERT INTO announcements VALUES (?,?,?,?,?,?,?,NULL,?)');
foreach ([
    [1,'Public advisory','Garbage collection notice',null,null,'global_public',0,'2026-10-01 09:00:00'],
    [2,'Calaba notice','Garbage clean-up',1,null,'localized_public',0,'2026-10-01 09:00:00'],
    [3,'Alua notice','Garbage clean-up',2,null,'localized_public',0,'2026-10-01 09:00:00'],
    [4,'Staff memo','Garbage response',null,null,'internal_global',0,'2026-10-01 09:00:00'],
    [5,'Private memo','Garbage dispatch',2,99,'internal_direct',0,'2026-10-01 09:00:00'],
    [6,'Archived','Garbage archive',null,null,'global_public',1,'2026-10-01 09:00:00'],
    [7,"Resident's concern",'Flooding after rainfall',null,null,'global_public',0,'2026-10-01 09:00:00'],
] as $row) $announcement->execute($row);
$db->exec("INSERT INTO announcements VALUES (8,'Expired garbage notice','Garbage',NULL,NULL,'global_public',0,'2020-01-01','2019-01-01')");
$db->exec("INSERT INTO notifications VALUES (1,'Update','Garbage response received','report',10,'2026-10-01'),(2,'Private update','Garbage response received','report',20,'2026-10-01')");
$citizen = new Search($db,['id'=>10,'role'=>'citizen','barangay_id'=>1,'is_resident'=>1]);
$ids = fn($data)=>array_column($data['results'],'id');
searchCheck($ids($citizen->find('garbage','reports')) === [2],'Full body match finds text beyond the first 800 characters');
searchCheck(str_contains($citizen->find('garbage','reports')['results'][0]['excerpt'],'Garbage'),'Excerpt shows the matching body passage');
searchCheck($ids($citizen->find('run','reports')) === [4,1],'Common roots match running and ran without exposing an unsupported report');
searchCheck($ids($citizen->find('running','reports')) === [4,1],'Inflected query matches its root and irregular forms');
searchCheck($ids($citizen->find('dr?in*','reports')) === [4,1],'Both wildcard characters match safely');
searchCheck($ids($citizen->find('#000002','reports')) === [2],'Padded report IDs can be searched');
searchCheck($citizen->find("' OR 1=1 --",'reports')['total'] === 0,'SQL injection is treated as search text');
searchCheck($ids($citizen->find("Resident's",'announcements')) === [7],'Apostrophe searches work');
searchCheck($citizen->find('garbage','announcements')['total'] === 2,'Citizen announcements exclude other barangays, internal, archived and expired posts');
searchCheck($ids($citizen->find('garbage','notifications')) === [1],'Only the current user notifications are searchable');
searchCheck($citizen->find('garbage stream','reports')['total'] === 1,'Multiple terms must match the same document');
searchCheck($citizen->find('* ?','all')['total'] === 0,'Wildcard-only queries cannot enumerate data');
searchCheck($citizen->find('unmatchablequery')['total'] === 0,'No matches returns a clean empty result');
$visitor = new Search($db,['id'=>10,'role'=>'citizen','barangay_id'=>1,'is_resident'=>0]);
searchCheck($visitor->find('garbage','announcements')['total'] === 1,'Non-residents see only municipality-wide broadcasts');
$barangay = new Search($db,['id'=>50,'role'=>'barangay_official','barangay_id'=>1],['reports'=>true]);
searchCheck($barangay->find('drain','reports')['total'] === 1,'Barangay report results stay within their jurisdiction');
searchCheck($barangay->find('garbage','announcements')['total'] === 3,'Barangay can find public and internal-global but not another recipient private notices');
$noReports = new Search($db,['id'=>50,'role'=>'admin'],['reports'=>false]);
searchCheck($noReports->find('drain','reports')['total'] === 0,'Staff without report permissions cannot search reports');
$admin = new Search($db,['id'=>99,'role'=>'admin'],['reports'=>true]);
searchCheck($admin->find('drain','reports')['total'] === 3,'Authorized admin can search all barangays');
$report->execute([5,10,1,2,'Garbage','Only title matches','Calaba','pending','low','2026-09-01']);
searchCheck($ids($citizen->find('garbage','reports'))[0] === 5,'Exact title ranks above a newer body-only match');
for ($id=20;$id<42;$id++) $report->execute([$id,10,1,2,'Repeated concern','Garbage in water','Calaba','pending','low','2026-10-01']);
$pageOne = $citizen->find('garbage','reports'); $pageTwo = $citizen->find('garbage','reports',2);
searchCheck($pageOne['total'] === 24 && count($pageOne['results']) === 15 && count($pageTwo['results']) === 9,'Ranked pagination includes the entire dataset');
searchCheck(!array_intersect($ids($pageOne),$ids($pageTwo)),'Stable pagination does not repeat results');
searchCheck(count($citizen->find('garb','reports',1,true)['results']) === 6,'Predictive results are capped at six');
searchCheck(count($citizen->find('garb','reports',999999,true)['results']) === 6,'Suggestion requests always use the first ranked results');
$db->exec('DELETE FROM reports WHERE id=5');
searchCheck(!in_array(5,$ids($citizen->find('garbage','reports')),true),'Deleted content immediately disappears from search');
searchCheck((new Search($db,['id'=>0,'role'=>'citizen']))->find('garbage')['total'] === 0,'Missing user cannot access indexed content');
$db->exec("INSERT INTO users VALUES (10,'Maria','Santos','maria@example.invalid','09123456789','Market Street','','',1,NULL,1,1,'2026-10-01'),(20,'Jose','Reyes','jose@example.invalid','09987654321','Alua Street','','',2,NULL,1,1,'2026-10-01'),(30,'Visitor','Cruz','visitor@example.invalid','09876543210','','Other town','',NULL,NULL,0,1,'2026-10-01'),(50,'Barangay','Staff','staff@example.invalid','09000000000','','','Official',1,'barangay_personnel',1,1,'2026-10-01')");
$db->exec("INSERT INTO activity_logs VALUES (1,'Update Status','Set report to resolved','MENRO Admin','Admin','Reports','SUCCESS','2026-10-01')");
$directorySearch = new Search($db,['id'=>50,'role'=>'barangay_official','barangay_id'=>1],['reporters'=>true]);
searchCheck($ids($directorySearch->find('Maria','reporters')) === [10],'Directory search includes own barangay residents');
searchCheck($directorySearch->find('Jose','reporters')['total'] === 0,'Directory search excludes another barangay resident without a local report');
searchCheck($directorySearch->find('Staff','reporters')['total'] === 0,'Directory never exposes staff accounts');
$report->execute([99,30,1,2,'Visitor report','Smoke noticed','Market','pending','low','2026-10-01']);
searchCheck($ids($directorySearch->find('Visitor','reporters')) === [30],'Non-resident reporter search requires a report in the staff barangay');
$staffSearch = new Search($db,['id'=>99,'role'=>'admin','user_type'=>'menro_staff'],['users'=>true,'audit'=>true]);
searchCheck($ids($staffSearch->find('Market','users')) === [10],'Authorized user search includes complete account contact and address fields');
searchCheck($staffSearch->find('resolved','audit')['total'] === 0,'MENRO staff cannot search audit logs even with a system permission');
$superSearch = new Search($db,['id'=>99,'role'=>'admin','user_type'=>'admin'],['users'=>true,'audit'=>true]);
searchCheck($ids($superSearch->find('resolved','audit')) === [1],'Only the authorized system administrator can search audit bodies');
searchCheck($citizen->find('Maria','users')['total'] === 0 && $citizen->find('resolved','audit')['total'] === 0,'Citizen search never exposes user-management or audit records');
$pageUser = ['id'=>10,'role'=>'citizen','barangay_id'=>1,'is_resident'=>1];
$ownSearch = new Search($db,$pageUser,[],['page'=>'my-reports']);
$supportedSearch = new Search($db,$pageUser,[],['page'=>'my-reports','tab'=>'supported']);
searchCheck($ids($ownSearch->find('drain')) === [1], 'My Reports search excludes supported and other users reports');
searchCheck($ids($supportedSearch->find('drain')) === [4], 'My Support search only includes supported reports');
searchCheck($ownSearch->find('garbage','announcements')['total'] === 0, 'Changing the type parameter cannot escape the page scope');
$noticeSearch = new Search($db,$pageUser,[],['page'=>'announcements']);
searchCheck($noticeSearch->allowedTypes() === ['announcements'] && $noticeSearch->find('garbage')['total'] === 2, 'Announcement page search retains visibility rules and excludes reports and notifications');
searchCheck(array_unique(array_column($noticeSearch->find('garbage','all',1,true)['results'],'type')) === ['announcements'], 'Live suggestions stay inside the page scope');
searchCheck((new Search($db,$pageUser,[],['page'=>'manage-users']))->find('Maria')['total'] === 0, 'A forged page scope does not grant user-management access');
$userTab = new Search($db,['id'=>99,'role'=>'admin'],['users'=>true],['page'=>'manage-users','tab'=>'barangay']);
searchCheck($ids($userTab->find('Staff')) === [50] && $userTab->find('Maria')['total'] === 0, 'Users search stays within the selected account tab');
$reportPage = new Search($db,['id'=>50,'role'=>'barangay_official','barangay_id'=>1],['reports'=>true],['page'=>'verify-reports']);
searchCheck($ids($reportPage->find('drain')) === [1], 'Verify Reports search retains barangay jurisdiction');
searchCheck(count(array_unique(array_column($citizen->find('garbage')['results'],'type'))) > 1, 'Dashboard search remains system-wide');
