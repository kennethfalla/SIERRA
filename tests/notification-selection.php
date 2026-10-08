<?php
// Isolated ownership regression: no application database is accessed.
require_once __DIR__ . '/../models/Notification.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER, is_read INTEGER, type TEXT DEFAULT 'info', title TEXT DEFAULT '')");
$db->exec('INSERT INTO notifications (id,user_id,is_read) VALUES (1,10,0),(2,10,1),(3,20,0),(4,10,0)');
$model = new Notification($db);
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
check($model->deleteSelected(10, [1,1,3]) === 1, 'Delete only the selected notifications owned by this user');
check((int)$db->query('SELECT COUNT(*) FROM notifications WHERE id=3')->fetchColumn() === 1, 'Other users retain their notification');
check($model->deleteSelected(10, []) === 0, 'An empty selection cannot delete anything');
check($model->getUnreadCount(10) === 1, 'Unread count updates after deletion');
check($model->markAllRead(10) === 1, 'Mark all read still works');
check($model->clearAll(10) === 2, 'Delete all includes both read and unread notifications');
check((int)$db->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === 1, 'Delete all remains scoped to the current user');

$db->exec("INSERT INTO notifications VALUES (5,10,0,'report','New Report Submitted'),(6,10,0,'announcement','New Announcement'),(7,10,1,'report','New Report Submitted'),(8,20,0,'announcement','New Announcement')");
$summary = $model->getSyncSummary(10);
check($summary['sidebar_counts'] === ['notifications' => 2, 'reports' => 1, 'announcements' => 1], 'Sidebar counts exclude read updates and other users');
$model->markRead(10, 5);
check($model->getSyncSummary(10)['sidebar_counts']['reports'] === 0, 'Reading a report update clears its sidebar count');
$model->deleteSelected(10, [6]);
check($model->getSyncSummary(10)['sidebar_counts']['announcements'] === 0, 'Deleting an announcement update clears its sidebar count');
$db->exec("CREATE TABLE reports (id INTEGER PRIMARY KEY, user_id INTEGER, barangay_id INTEGER, status TEXT)");
$db->exec("INSERT INTO reports VALUES (1,10,7,'pending'),(2,10,7,'in_progress'),(3,20,8,'pending'),(4,20,7,'resolved')");
check($model->getSyncSummary(10, 'citizen')['sidebar_counts']['reports'] === 2, 'My Reports counts owned reports independently of notification reads');
check($model->getSyncSummary(10, 'barangay_official', 7)['sidebar_counts']['reports'] === 1, 'Verification badge includes pending reports in this barangay only');
$db->exec("UPDATE reports SET status='under_review' WHERE id=1");
check($model->getSyncSummary(10, 'barangay_official', 7)['sidebar_counts']['reports'] === 0, 'Report updates do not count as new reports awaiting verification');
check($model->getSyncSummary(10, 'barangay_official', null)['sidebar_counts']['reports'] === 0, 'Missing barangay cannot count another jurisdiction');
echo "Notification ownership and selection checks passed.\n";
