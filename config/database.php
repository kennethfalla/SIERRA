<?php
// config/database.php – COMPLETE DATABASE CONNECTION
// Includes automatic column migration for force_password_reset

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Read from environment variables (set in Render/Aiven)
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->port = getenv('DB_PORT') ?: '3306';
        $this->db_name = getenv('DB_NAME') ?: 'env_reporting_system';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';
    }

    /**
     * Get database connection
     * @return PDO
     */
    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Auto-ensure required columns exist
            $this->ensureColumns();
            
        } catch(PDOException $exception) {
            die("Database Connection Error: " . $exception->getMessage());
        }
        return $this->conn;
    }

    /**
     * Ensure required columns exist in the database
     * This auto-migrates the database on connection
     */
    private function ensureColumns() {
        try {
            // ============================================
            // 1. CHECK: force_password_reset column in users table
            // ============================================
            $check = $this->conn->query("SHOW COLUMNS FROM users LIKE 'force_password_reset'");
            if ($check->rowCount() == 0) {
                $this->conn->exec("
                    ALTER TABLE users 
                    ADD COLUMN force_password_reset TINYINT(1) NOT NULL DEFAULT 0 
                    COMMENT 'Set to 1 to force password reset on next login (staff accounts)'
                ");
                error_log("[Database] Added 'force_password_reset' column to users table.");
            }

            // ============================================
            // 2. CHECK: system_settings table (if not exists)
            // ============================================
            $check = $this->conn->query("SHOW TABLES LIKE 'system_settings'");
            if ($check->rowCount() == 0) {
                $this->conn->exec("
                    CREATE TABLE IF NOT EXISTS system_settings (
                        id INT(11) NOT NULL AUTO_INCREMENT,
                        setting_key VARCHAR(100) NOT NULL,
                        setting_value TEXT DEFAULT NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY setting_key (setting_key)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ");
                error_log("[Database] Created 'system_settings' table.");

                // Insert default SMS templates
                $this->conn->exec("
                    INSERT INTO system_settings (setting_key, setting_value) VALUES
                    ('template_staff_account_created', 'Hello {first_name}, an official {role} account has been created for you. Username: {email}. Temporary Password: {temp_password}. Login: {login_url}'),
                    ('enable_sms_notifications', '0'),
                    ('sms_sender_name', 'SierraLGU'),
                    ('semaphore_api_key', ''),
                    ('twilio_account_sid', ''),
                    ('twilio_auth_token', ''),
                    ('twilio_from_number', ''),
                    ('chikka_api_key', ''),
                    ('chikka_secret_key', ''),
                    ('chikka_shortcode', '')
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                error_log("[Database] Inserted default SMS settings into system_settings.");
            }

            // ============================================
            // 3. CHECK: Missing SMS settings in system_settings
            // ============================================
            $sms_settings = [
                'template_staff_account_created',
                'enable_sms_notifications',
                'sms_sender_name',
                'semaphore_api_key',
                'twilio_account_sid',
                'twilio_auth_token',
                'twilio_from_number',
                'chikka_api_key',
                'chikka_secret_key',
                'chikka_shortcode'
            ];

            foreach ($sms_settings as $key) {
                $check = $this->conn->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
                $check->execute([$key]);
                if ($check->fetchColumn() == 0) {
                    $default_value = match($key) {
                        'template_staff_account_created' => 'Hello {first_name}, an official {role} account has been created for you. Username: {email}. Temporary Password: {temp_password}. Login: {login_url}',
                        'enable_sms_notifications' => '0',
                        'sms_sender_name' => 'SierraLGU',
                        default => ''
                    };
                    $insert = $this->conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
                    $insert->execute([$key, $default_value]);
                    error_log("[Database] Added missing SMS setting: $key");
                }
            }

            // ============================================
            // 4. CHECK: Other missing columns (safety net)
            // ============================================
            
            // Check if profile_picture column exists
            $check = $this->conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
            if ($check->rowCount() == 0) {
                $this->conn->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
                error_log("[Database] Added 'profile_picture' column to users table.");
            }

            // Check if job_title column exists
            $check = $this->conn->query("SHOW COLUMNS FROM users LIKE 'job_title'");
            if ($check->rowCount() == 0) {
                $this->conn->exec("ALTER TABLE users ADD COLUMN job_title VARCHAR(100) DEFAULT NULL");
                error_log("[Database] Added 'job_title' column to users table.");
            }

            // ============================================
            // 5. CHECK: Primary keys are auto-increment
            // ============================================
            // Barangays: a non-AUTO_INCREMENT PK lets INSERT statements that omit
            // `id` silently store 0, breaking edit/delete lookups by ID.
            $check = $this->conn->query("
                SELECT EXTRA AS extra FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'barangays'
                  AND COLUMN_NAME = 'id'
                  AND COLUMN_KEY = 'PRI'
            ");
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if ($row && stripos((string)($row['extra'] ?? ''), 'auto_increment') === false) {
                // Remove any garbage rows that were stored with id = 0 before the fix
                // (they are unreachable by ID-based edit/delete and are not referenced).
                $this->conn->exec("DELETE FROM barangays WHERE id = 0");
                $this->conn->exec("ALTER TABLE barangays MODIFY id int(11) NOT NULL AUTO_INCREMENT");
                error_log("[Database] Fixed barangays.id to be AUTO_INCREMENT.");
            }

            // Categories: same latent bug, fixed for consistency.
            $check = $this->conn->query("
                SELECT EXTRA AS extra FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'categories'
                  AND COLUMN_NAME = 'id'
                  AND COLUMN_KEY = 'PRI'
            ");
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if ($row && stripos((string)($row['extra'] ?? ''), 'auto_increment') === false) {
                $this->conn->exec("DELETE FROM categories WHERE id = 0");
                $this->conn->exec("ALTER TABLE categories MODIFY id int(11) NOT NULL AUTO_INCREMENT");
                error_log("[Database] Fixed categories.id to be AUTO_INCREMENT.");
            }

            return true;

        } catch (PDOException $e) {
            error_log("[Database] Column check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a table exists
     * @param string $table Table name
     * @return bool
     */
    public function tableExists($table) {
        try {
            $stmt = $this->conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Check if a column exists in a table
     * @param string $table Table name
     * @param string $column Column name
     * @return bool
     */
    public function columnExists($table, $column) {
        try {
            $stmt = $this->conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get the last inserted ID
     * @return string
     */
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->conn->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollBack() {
        return $this->conn->rollBack();
    }

    /**
     * Prepare a statement
     * @param string $query SQL query
     * @return PDOStatement
     */
    public function prepare($query) {
        return $this->conn->prepare($query);
    }

    /**
     * Execute a query
     * @param string $query SQL query
     * @return PDOStatement
     */
    public function query($query) {
        return $this->conn->query($query);
    }

    /**
     * Execute a statement
     * @param string $query SQL query
     * @return int Number of affected rows
     */
    public function exec($query) {
        return $this->conn->exec($query);
    }

    /**
     * Get the PDO connection object
     * @return PDO
     */
    public function getPdo() {
        return $this->conn;
    }

    /**
     * Quote a string for use in SQL
     * @param string $string
     * @return string
     */
    public function quote($string) {
        return $this->conn->quote($string);
    }

    /**
     * Get error info
     * @return array
     */
    public function errorInfo() {
        return $this->conn->errorInfo();
    }
}