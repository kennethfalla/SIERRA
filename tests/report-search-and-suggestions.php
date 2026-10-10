<?php
// Offline tests: action isolation and real My Reports SQL against a disposable database.
require_once __DIR__ . '/../helpers/QuickNoteTemplates.php';
function expect($actual, $expected, $message) {
    if ($actual !== $expected) throw new RuntimeException($message . ': ' . json_encode($actual));
}
$templates = [
    ['template_text'=>'General investigation', 'target_category'=>'', 'target_status'=>''],
    ['template_text'=>'Inspection started', 'target_category'=>'Air Pollution', 'target_status'=>'in_progress'],
    ['template_text'=>'Need MENRO equipment', 'target_category'=>'Air Pollution', 'target_status'=>'escalated_pending'],
    ['template_text'=>'Specialist response', 'target_category'=>'', 'target_status'=>'escalated'],
    ['template_text'=>'Source removed', 'target_category'=>'Air Pollution', 'target_status'=>'resolved'],
    ['template_text'=>'Drain cleared', 'target_category'=>'Drainage Blockage', 'target_status'=>'resolved'],
];
expect(QuickNoteTemplates::forAction($templates, 'Air Pollution', 'in_progress', 'investigation'), ['General investigation', 'Inspection started'], 'Investigation targeting');
expect(QuickNoteTemplates::forAction($templates, 'air pollution', 'in_progress', 'escalation'), ['Need MENRO equipment', 'Specialist response'], 'Escalation must not inherit resolution or general notes');
expect(QuickNoteTemplates::forAction($templates, 'Air Pollution', 'in_progress', 'resolution'), ['Source removed'], 'Resolution must not inherit escalation');
expect(QuickNoteTemplates::forAction($templates, 'Unknown category', 'in_progress', 'resolution'), [], 'Do not fill an unmatched action with other actions');
expect(QuickNoteTemplates::forAction($templates, 'Air Pollution', 'resolved', 'investigation'), [], 'Closed reports have no investigation suggestions');

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE reports (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, description TEXT, location_address TEXT, status TEXT, risk_level TEXT, category_id INTEGER)');
$db->exec('CREATE TABLE report_verifications (user_id INTEGER, report_id INTEGER)');
$insert = $db->prepare('INSERT INTO reports VALUES (?,?,?,?,?,?,?,?)');
$insert->execute([1,10,"Resident's concern",'Smoke near school','Calaba','in_progress','high',1]);
$insert->execute([2,10,'Drain blocked','Flooding by market','Alua','pending','low',2]);
$insert->execute([3,20,"Resident's concern",'Outside own reports','Calaba','pending','low',1]);
$db->exec('INSERT INTO report_verifications VALUES (10,3)');
$source = file_get_contents(__DIR__.'/../views/citizen/my_reports.php');
$start = strpos($source, '// Both tabs search the complete dataset');
$end = strpos($source, '// Set pagination based on active tab', $start);
$queryBlock = substr($source, $start, $end-$start);
$user_id=10; $filter_date=0;
foreach ([
    ["Resident's", '', [], [], 1, 1],
    ['Alua', '', [], [], 1, 0],
    ['#2', '', [], [], 1, 0],
    ['#000002', '', [], [], 1, 0],
    ["' OR 1=1 --", '', [], [], 0, 0],
    ['', 'pending', ['low'], [2], 1, 0],
] as [$search_keyword,$filter_status,$filter_risks,$filter_categories,$own,$supported]) {
    eval($queryBlock);
    expect($total_reports, $own, 'Own report search and filters');
    expect($total_supported, $supported, 'Supported search keeps ownership scope');
}
// All Reports and Verify Reports must use matching joins for counts and data.
$db->sqliteCreateFunction('CONCAT', fn(...$values)=>implode('', $values));
$db->exec('ALTER TABLE reports ADD COLUMN barangay_id INTEGER DEFAULT 1');
$db->exec('UPDATE reports SET barangay_id=2 WHERE id=2');
$db->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT)');
$db->exec("INSERT INTO categories VALUES (1,'Uncollected Waste'),(2,'Drainage Blockage')");
$db->exec('CREATE TABLE barangays (id INTEGER PRIMARY KEY, name TEXT)');
$db->exec("INSERT INTO barangays VALUES (1,'Calaba'),(2,'Alua')");
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)');
$db->exec("INSERT INTO users VALUES (10,'Sample','Resident'),(20,'Other','Reporter')");
foreach (['views/admin/all_reports.php','views/barangay/verify_reports.php'] as $file) {
    $source=file_get_contents(__DIR__.'/../'.$file);
    $isVerify=str_contains($file,'verify_reports');
    $searchStart=strpos($source,$isVerify?"if (\$search_keyword != '') {":"if (\$search != '') {");
    $searchEnd=strpos($source,"\n}",$searchStart)+2;
    $countStart=strpos($source,'$count_sql =');$countEnd=strpos($source,';',$countStart)+1;
    foreach ([["Resident's",2,2],['#000002',1,0],['Alua',1,0],['Uncollected Waste',2,2],['No match',0,0]] as [$keyword,$allCount,$verifyCount]) {
        $search=$search_keyword=$keyword;$params=[];$where=$isVerify?'r.barangay_id=1':'1=1';
        eval(substr($source,$searchStart,$searchEnd-$searchStart));
        eval(substr($source,$countStart,$countEnd-$countStart));
        $statement=$db->prepare($count_sql);$statement->execute($params);
        expect((int)$statement->fetchColumn(),$isVerify?$verifyCount:$allCount,'Report counts search IDs, category and barangay without losing scope');
    }
}
// Run the real announcement search clause with an apostrophe-containing title.
$db->exec('CREATE TABLE announcements (title TEXT, content TEXT)');
$db->prepare('INSERT INTO announcements VALUES (?,?)')->execute(["Resident's clean-up day",'Everyone is welcome.']);
$announcementSource=file_get_contents(__DIR__.'/../views/shared/announcements.php');
$start=strpos($announcementSource,"if(\$search_query != '') {");
$end=strpos($announcementSource,"if(\$category_filter",$start);
$search_query="Resident's";$where='1=1';$params=[];
eval(substr($announcementSource,$start,$end-$start));
$statement=$db->prepare("SELECT COUNT(*) FROM announcements a WHERE $where");$statement->execute($params);
expect((int)$statement->fetchColumn(),1,'Announcement apostrophes are not double-escaped');
echo "PASS action-specific suggestions, category scope, closed reports, literal search, IDs, locations, ownership and filters\n";
