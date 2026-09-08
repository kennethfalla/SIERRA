<?php
// models/Notification.php - In-app notification bell (DB-backed, per user)
class Notification {
    private $conn;
    private $table = "notifications";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Self-heal the notifications schema before writing.
     * The committed database.sql historically omitted icon/color/link; those
     * columns are required by every INSERT below. If a deployment imported the
     * old dump and the runtime migrator never ran, inserts would throw and
     * notifications would silently never be created. This guard makes writes
     * work regardless of how the table was created. Runs once per request.
     */
    private function ensureColumns() {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $check = $this->conn->query("SHOW TABLES LIKE 'notifications'");
            if ($check->rowCount() === 0) {
                $this->conn->exec("
                    CREATE TABLE IF NOT EXISTS notifications (
                        id INT(11) NOT NULL AUTO_INCREMENT,
                        user_id INT(11) NOT NULL,
                        report_id INT(11) DEFAULT NULL,
                        title VARCHAR(200) NOT NULL,
                        message TEXT NOT NULL,
                        type VARCHAR(50) DEFAULT 'info',
                        icon VARCHAR(50) DEFAULT 'fa-bell',
                        color VARCHAR(20) DEFAULT '#10A37F',
                        link VARCHAR(255) DEFAULT '',
                        is_read TINYINT(1) NOT NULL DEFAULT 0,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY idx_notif_user (user_id, is_read, created_at),
                        KEY idx_notif_report (report_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ");
                return;
            }
            if (!$this->columnExists('icon')) {
                $this->conn->exec("ALTER TABLE notifications ADD COLUMN icon VARCHAR(50) DEFAULT 'fa-bell' AFTER type");
            }
            if (!$this->columnExists('color')) {
                $this->conn->exec("ALTER TABLE notifications ADD COLUMN color VARCHAR(20) DEFAULT '#10A37F' AFTER icon");
            }
            if (!$this->columnExists('link')) {
                $this->conn->exec("ALTER TABLE notifications ADD COLUMN link VARCHAR(255) DEFAULT '' AFTER color");
            }
        } catch (Exception $e) {
            error_log("Notification schema check failed: " . $e->getMessage());
        }
    }

    private function columnExists($column) {
        try {
            $stmt = $this->conn->prepare("SHOW COLUMNS FROM notifications LIKE ?");
            $stmt->execute([$column]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create a single notification for one user.
     */
    public function create($user_id, $title, $message, $type = 'info', $icon = 'fa-bell', $color = '#10A37F', $link = '', $report_id = null) {
        $this->ensureColumns();
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
        $this->ensureColumns();

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
