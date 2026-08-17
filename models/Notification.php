<?php
// models/Notification.php - In-app notification bell (DB-backed, per user)
class Notification {
    private $conn;
    private $table = "notifications";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create a single notification for one user.
     */
    public function create($user_id, $title, $message, $type = 'info', $icon = 'fa-bell', $color = '#10A37F', $link = '', $report_id = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table} (user_id, report_id, title, message, type, icon, color, link, is_read, created_at)
            VALUES (:user_id, :report_id, :title, :message, :type, :icon, :color, :link, 0, NOW())
        ");
        return $stmt->execute([
            ':user_id'    => (int)$user_id,
            ':report_id'  => $report_id ? (int)$report_id : null,
            ':title'      => $title,
            ':message'    => $message,
            ':type'       => $type,
            ':icon'       => $icon,
            ':color'      => $color,
            ':link'       => $link,
        ]);
    }

    /**
     * Bulk-create the same notification for many users (e.g. an announcement).
     */
    public function createForMany(array $user_ids, $title, $message, $type = 'info', $icon = 'fa-bell', $color = '#10A37F', $link = '') {
        if (empty($user_ids)) return 0;

        $values = [];
        $params = [];
        foreach (array_values(array_unique($user_ids)) as $i => $uid) {
            $u = ':u' . $i;
            $values[] = "($u, :t, :m, :ty, :i, :c, :l, 0, NOW())";
            $params[$u] = (int)$uid;
        }
        $params[':t']  = $title;
        $params[':m']  = $message;
        $params[':ty'] = $type;
        $params[':i']  = $icon;
        $params[':c']  = $color;
        $params[':l']  = $link;

        $sql = "INSERT INTO {$this->table} (user_id, title, message, type, icon, color, link, is_read, created_at)
                VALUES " . implode(', ', $values);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Get a user's notifications, newest first.
     * @param int $user_id
     * @param int|null $limit Limit rows (null = all)
     * @return array
     */
    public function getForUser($user_id, $limit = null) {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = :user_id ORDER BY created_at DESC, id DESC";
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':user_id' => (int)$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count unread notifications for a user.
     */
    public function getUnreadCount($user_id) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute([':user_id' => (int)$user_id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Mark a single notification as read (must belong to the user).
     */
    public function markRead($user_id, $id) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET is_read = 1 WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => (int)$id, ':user_id' => (int)$user_id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllRead($user_id) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute([':user_id' => (int)$user_id]);
        return $stmt->rowCount();
    }

    /**
     * Permanently clear all notifications for a user.
     */
    public function clearAll($user_id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE user_id = :user_id");
        $stmt->execute([':user_id' => (int)$user_id]);
        return $stmt->rowCount();
    }
}
