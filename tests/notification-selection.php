<?php
// Isolated ownership regression: no application database is accessed.
require_once __DIR__ . '/../models/Notification.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER, is_read INTEGER)');
$db->exec('INSERT INTO notifications VALUES (1,10,0),(2,10,1),(3,20,0),(4,10,0)');
$model = new Notification($db);
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
check($model->deleteSelected(10, [1,1,3]) === 1, 'Delete only the selected notifications owned by this user');
check((int)$db->query('SELECT COUNT(*) FROM notifications WHERE id=3')->fetchColumn() === 1, 'Other users retain their notification');
check($model->deleteSelected(10, []) === 0, 'An empty selection cannot delete anything');
check($model->getUnreadCount(10) === 1, 'Unread count updates after deletion');
check($model->markAllRead(10) === 1, 'Mark all read still works');
check($model->clearAll(10) === 2, 'Delete all includes both read and unread notifications');
check((int)$db->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === 1, 'Delete all remains scoped to the current user');
echo "Notification ownership and selection checks passed.\n";
