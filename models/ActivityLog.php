<?php
// models/ActivityLog.php - EXTENDED: records device/user-agent and status/result metadata
class ActivityLog {
    private $conn;
    private $table = "activity_logs";

    public function __construct($db) {
        $this->conn = $db;
        $this->ensureColumns();
    }

    // Auto-migrate: add metadata columns that may not exist in older installs.
    private function ensureColumns() {
        try {
            $this->conn->exec("ALTER TABLE `activity_logs` ADD COLUMN IF NOT EXISTS `user_agent` VARCHAR(255) DEFAULT NULL AFTER `ip_address`");
            $this->conn->exec("ALTER TABLE `activity_logs` ADD COLUMN IF NOT EXISTS `status` VARCHAR(30) NOT NULL DEFAULT 'SUCCESS' AFTER `user_agent`");
        } catch (Exception $e) {
            // Some MySQL 5.x versions don't support "ADD COLUMN IF NOT EXISTS"; fall back to a check.
            try {
                $cols = $this->conn->query("SHOW COLUMNS FROM `activity_logs`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('user_agent', $cols)) {
                    $this->conn->exec("ALTER TABLE `activity_logs` ADD COLUMN `user_agent` VARCHAR(255) DEFAULT NULL AFTER `ip_address`");
                }
                if (!in_array('status', $cols)) {
                    $this->conn->exec("ALTER TABLE `activity_logs` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'SUCCESS' AFTER `user_agent`");
                }
            } catch (Exception $e2) {
                // Table may not exist yet; ignore.
            }
        }
    }

    // Logs an activity entry.
    //   $status: SUCCESS, FAILED, or UNAUTHORIZED_ATTEMPT
    //   $user_agent: defaults to the browser/client User-Agent.
    public function log($user_id, $action, $description, $ip_address = null, $target_module = null, $status = 'SUCCESS', $user_agent = null) {
        if(!$ip_address) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        if(!$user_agent) {
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
        }
        if(!in_array($status, ['SUCCESS', 'FAILED', 'UNAUTHORIZED_ATTEMPT'], true)) {
            $status = 'SUCCESS';
        }
        
        // Check if user exists before logging
        if($user_id) {
            $checkUser = $this->conn->prepare("SELECT id FROM users WHERE id = :user_id");
            $checkUser->execute([':user_id' => $user_id]);
            if(!$checkUser->fetch()) {
                // User doesn't exist, log without user_id
                $user_id = null;
            }
        }
        
        // Capture the actor details from the session so the log stays
        // self-contained even if the user's account is later deleted.
        $actor_name = $_SESSION['user_name'] ?? null;
        $actor_role = $_SESSION['user_type'] ?? null;
        if(empty($actor_role)) {
            $actor_role = $_SESSION['user_type'] ?? null;
        }
        
        $query = "INSERT INTO " . $this->table . "
                  (user_id, actor_name, actor_role, target_module, action, description, ip_address, user_agent, status, created_at) 
                  VALUES (:user_id, :actor_name, :actor_role, :target_module, :action, :description, :ip_address, :user_agent, :status, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":actor_name", $actor_name);
        $stmt->bindParam(":actor_role", $actor_role);
        $stmt->bindParam(":target_module", $target_module);
        $stmt->bindParam(":action", $action);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":ip_address", $ip_address);
        $stmt->bindParam(":user_agent", $user_agent);
        $stmt->bindParam(":status", $status);
        return $stmt->execute();
    }

    public function getAll($limit = 100) {
        $query = "SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM " . $this->table . " l
                  LEFT JOIN users u ON l.user_id = u.id
                  ORDER BY l.created_at DESC 
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    public function getByUser($user_id, $limit = 50) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }
}
?>