<?php
// Run the real page query builders against synthetic data, without app bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function checkFilter($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function fragment($file, $start, $end) {
    $source = file_get_contents(__DIR__ . '/../' . $file);
    $a = strpos($source, $start); $b = strpos($source, $end, $a);
    checkFilter($a !== false && $b !== false, 'Query builder markers: ' . $file);
    return substr($source, $a, $b-$a);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', fn() => '2026-10-10 12:00:00');
$db->sqliteCreateFunction('CONCAT', fn(...$parts) => implode('', $parts));
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, email TEXT, is_resident INTEGER, barangay_id INTEGER, is_active INTEGER, created_at TEXT)');
$db->exec("INSERT INTO users VALUES (10,'Ada','Santos','ada@example.test',1,3,1,'2026-10-09 10:00:00'),(11,'Ben','Cruz','ben@example.test',0,9,0,'2026-09-01 09:00:00')");
$db->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT)');
$db->exec("INSERT INTO categories VALUES (2,'Water'),(7,'Waste')");
$db->exec('CREATE TABLE barangays (id INTEGER PRIMARY KEY, name TEXT)');
$db->exec("INSERT INTO barangays VALUES (3,'Calaba'),(9,'Alua')");
$db->exec('CREATE TABLE reports (id INTEGER PRIMARY KEY,user_id INTEGER,category_id INTEGER,barangay_id INTEGER,title TEXT,description TEXT,location_address TEXT,risk_level TEXT,status TEXT,created_at TEXT,resolved_at TEXT)');
$db->exec("INSERT INTO reports VALUES
 (1,10,2,3,'Drainage','Canal blocked','Calaba','high','pending','2026-10-09 10:00:00',NULL),
 (2,11,7,9,'Waste','Collected','Alua','low','resolved','2026-09-01 09:00:00','2026-10-08 12:00:00'),
 (3,11,2,3,'River','River pollution','Calaba','critical','escalated_pending','2026-10-02 11:00:00',NULL),
 (4,10,7,3,'Garbage','Waste outside','Calaba','medium','under_review','2026-10-10 08:00:00',NULL)");
$join = 'FROM reports r JOIN users u ON u.id=r.user_id JOIN categories c ON c.id=r.category_id JOIN barangays b ON b.id=r.barangay_id';
$all = fragment('views/admin/all_reports.php', '$where = "1=1";', '// EXPORT CSV HANDLER');
$verify = fragment('views/barangay/verify_reports.php', '$where = "r.barangay_id = "', '// CSV EXPORT HANDLER');
foreach (['All Reports'=>$all, 'Verify Reports'=>$verify] as $pageName=>$builder) {
    foreach ([
        [['risk_list'=>['high','critical'],'category_list'=>[2]], [1,3]],
        [['status_filter'=>'escalated'], [3]],
        [['search'=>'#3','search_keyword'=>'#3'], [3]],
        [['date_from'=>'2026-10-09','date_to'=>'2026-10-09','date_range'=>7,'residency_filter'=>'resident'], [1]],
        [['barangay_filter'=>9,'residency_filter'=>'non_resident'], $pageName==='All Reports' ? [2] : [3]],
    ] as [$filters,$expected]) {
        $status_filter=$search=$search_keyword=$date_from=$date_to=$residency_filter='';
        $risk_list=$category_list=[]; $barangay_filter=0; $barangay_id=3; $date_range=0;
        extract($filters);
        eval($builder);
        // SQLite equivalent of MySQL's relative-day expression, same bound value.
        $where = str_replace('DATE_SUB(NOW(), INTERVAL :date_range DAY)', "datetime(NOW(), '-' || :date_range || ' days')", $where);
        if ($pageName==='Verify Reports' && $date_from) $expected=[1,4]; // This page exposes relative dates, not custom dates.
        $stmt=$db->prepare("SELECT r.id $join WHERE $where ORDER BY r.id"); $stmt->execute($params);
        checkFilter(array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN))===$expected, $pageName.' filter results: '.json_encode($filters));
    }
}
// Analytics shares its builder across map, KPI, demographic and trend queries.
eval(fragment('views/admin/analytics.php','function _build_af(', '// created_at-based clauses'));
$analytics_date_from='2026-10-01'; $analytics_date_to='2026-10-09';
[$clause,$params]=_build_af('r','created_at',true,'pending','high','Calaba','Canal',2);
$stmt=$db->prepare("SELECT r.id $join WHERE 1=1 $clause"); $stmt->execute($params);
checkFilter($stmt->fetchAll(PDO::FETCH_COLUMN)===[1], 'Analytics combines date, status, severity, Barangay, body search and category');
$f_date_from='2026-10-09'; $f_date_to='2026-10-10'; $f_status='pending'; $f_risk='high'; $f_search_sql=''; $f_search_params=[]; $barangay_id=3;
eval(fragment('views/barangay/dashboard.php', "$".'fC_sql = ' . "'';", "$".'fR_sql = ' . "'';"));
$stmt=$db->prepare("SELECT r.id FROM reports r WHERE r.barangay_id=? $fC_sql"); $stmt->execute($fC_params);
checkFilter($stmt->fetchAll(PDO::FETCH_COLUMN)===[1], 'Barangay dashboard combines status, severity and dates without crossing jurisdiction');
// Audit exact user, action, status, dates and text.
$db->exec('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY,user_id INTEGER,action TEXT,status TEXT,description TEXT,created_at TEXT,user_agent TEXT)');
$db->exec("INSERT INTO activity_logs VALUES (1,10,'Login','SUCCESS','Signed in','2026-10-09 08:00:00','Browser'),(2,11,'Login','FAILED','Failed login','2026-10-09 09:00:00','Browser')");
$_GET=[]; $action_filter='Login'; $status_filter='SUCCESS'; $user_filter=10; $date_from=$date_to='2026-10-09'; $search='Signed';
eval(fragment('views/admin/audit_logs.php','$where = ["1=1"];','$where_clause = implode'));
$stmt=$db->prepare('SELECT a.id FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id WHERE '.implode(' AND ',$where)); $stmt->execute($params);
checkFilter($stmt->fetchAll(PDO::FETCH_COLUMN)===[1], 'Audit filters match the real selected account and values');
// User filters operate on loaded account data.
eval(fragment('views/admin/users.php','function applyFilters($users,','// CATEGORIZE USERS BY ROLE'));
$users=$db->query("SELECT *,first_name||' '||last_name AS full_name FROM users")->fetchAll(PDO::FETCH_ASSOC);
$filtered=applyFilters($users,'Ada',3,'active','resident','2026-10-01','2026-10-10');
checkFilter(array_column(array_values($filtered),'id')===[10], 'User status, residency, Barangay, search and registered-date filters combine');
// Announcement visibility remains in effect when any filter is applied.
$db->exec('CREATE TABLE announcements (id INTEGER PRIMARY KEY,title TEXT,content TEXT,category TEXT,barangay_id INTEGER,target_admin_id INTEGER,broadcast_type TEXT,is_archived INTEGER,expires_at TEXT,created_at TEXT)');
$db->exec("INSERT INTO announcements VALUES
 (1,'Planting','Trees','Environment',3,NULL,'localized_public',0,NULL,'2026-10-09 09:00:00'),
 (2,'Planting','Trees','Environment',9,NULL,'localized_public',0,NULL,'2026-10-09 09:00:00'),
 (3,'Private','Internal','Environment',3,NULL,'internal_direct',0,NULL,'2026-10-09 09:00:00')");
$announcementBuilder=fragment('views/shared/announcements.php','$where = "1=1 AND a.is_archived','// Get total count for pagination');
$is_admin=false; $is_barangay=false; $is_citizen=true; $is_resident=1; $barangay_id=3; $user_id=10; $broadcast_barangay=0;
$search_query='Trees'; $category_filter='Environment'; $date_from=$date_to='2026-10-09';
eval($announcementBuilder);
$stmt=$db->prepare("SELECT a.id FROM announcements a WHERE $where"); $stmt->execute($params);
checkFilter($stmt->fetchAll(PDO::FETCH_COLUMN)===[1], 'Announcement filters cannot expose other Barangays or internal broadcasts to citizens');
echo "Report, analytics, Barangay dashboard, audit, user and announcement query filters passed.\n";
