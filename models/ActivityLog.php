<?php
// models/ActivityLog.php - FIXED VERSION
class ActivityLog {
    private $conn;
    private $table = "activity_logs";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function log($user_id, $action, $description, $ip_address = null, $target_module = null) {
        if(!$ip_address) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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
                  (user_id, actor_name, actor_role, target_module, action, description, ip_address) 
                  VALUES (:user_id, :actor_name, :actor_role, :target_module, :action, :description, :ip_address)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":actor_name", $actor_name);
        $stmt->bindParam(":actor_role", $actor_role);
        $stmt->bindParam(":target_module", $target_module);
        $stmt->bindParam(":action", $action);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":ip_address", $ip_address);
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