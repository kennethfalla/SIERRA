<?php
// config/database.php – COMPLETE DATABASE CONNECTION
// Includes automatic column migration for force_password_reset

require_once dirname(__DIR__) . '/helpers/SchemaMigration.php';

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Deployable config, resolved in this order:
        //   1. config/env.php   (per-deployment file, gitignored — fill in your
        //      InfinityFree MySQL credentials there; see config/env.php.example)
        //   2. Environment variables (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD)
        //   3. Local XAMPP defaults (localhost / root / empty password)
        $envFile = dirname(__DIR__) . '/config/env.php';
        if (file_exists($envFile)) {
            require_once $envFile;
        }

        $this->host     = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
        $this->port     = defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: '3306');
        $this->db_name  = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'env_reporting_system');
        $this->username = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
        $this->password = defined('DB_PASSWORD') ? DB_PASSWORD : (getenv('DB_PASSWORD') ?: '');
    }

    /**
     * Get database connection
     * @return PDO
     */
    // Bump this whenever new auto-migrations are added further below. The
    // database stores completion, so normal requests run one version lookup
    // and no schema inspection (important on shared hosting / high-traffic polling).
    const SCHEMA_VERSION = '2026-10-10-report-reminders-1';

    // Reuse a single PDO connection per request (see getConnection()).
    private static $sharedConn = null;
    // Run the schema/column migration block at most once per request.
    private static $columnsEnsured = false;

    public function getConnection() {
        // Reuse the connection created earlier in this request. Creating a new
        // PDO for every `new Database()` (there are ~50 call sites) exhausts
        // shared-hosting connection limits and re-runs the migration checks
        // dozens of times, which is a common cause of 502/limit errors.
        if (self::$sharedConn instanceof PDO) {
            $this->conn = self::$sharedConn;
            return $this->conn;
        }

        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [PDO::ATTR_TIMEOUT => 5]);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Bound metadata/row-lock waits instead of keeping a worker blocked
            // until the reverse proxy gives up. Applies to every page/controller.
            foreach (['SET SESSION lock_wait_timeout = 5', 'SET SESSION innodb_lock_wait_timeout = 5'] as $setting) {
                try { $this->conn->exec($setting); }
                catch (PDOException $e) { error_log('[Database] Lock timeout setting unavailable.'); }
            }

            // Force the MySQL session to Manila time (+08:00) so NOW(),
            // CURRENT_TIMESTAMP and TIMESTAMPDIFF all match the app's
            // date_default_timezone_set('Asia/Manila'). Without this, a UTC
            // MySQL server (e.g. InfinityFree) stores report timestamps 8h in
            // the past and "X days ago" calculations look wrong.
            try {
                $this->conn->exec("SET time_zone = '+08:00'");
            } catch(PDOException $tzException) {
                error_log("[Database] Could not set session time_zone to +08:00: " . $tzException->getMessage());
            }

            // Auto-ensure required columns exist (once per request only).
            if (!self::$columnsEnsured) {
                SchemaMigration::run($this->conn, self::SCHEMA_VERSION, function () {
                    return $this->ensureColumns();
                });
                self::$columnsEnsured = true;
            }

            // MySQL bounds SELECTs; MariaDB uses max_statement_time. Install
            // this after migrations so first-run schema work is not interrupted.
            if (PHP_SAPI !== 'cli') {
                try { $this->conn->exec('SET SESSION max_execution_time = 10000'); }
                catch (PDOException $e) {
                    try { $this->conn->exec('SET SESSION max_statement_time = 10'); }
                    catch (PDOException $unsupported) { error_log('[Database] Statement timeout unavailable on this server.'); }
                }
            }

            self::$sharedConn = $this->conn;

        } catch(PDOException $exception) {
            if (PHP_SAPI === 'cli') throw $exception;
            error_log('[Database] Connection/update failed: ' . $exception->getMessage());
            http_response_code(503);
            header('Retry-After: 30');
            if (class_exists('RequestRuntime')) RequestRuntime::unavailable(503);
            else echo 'The system is temporarily unavailable. Please try again shortly.';
            exit;
        }
        return $this->conn;
    }

    /**
     * Ensure required columns exist in the database
     * This auto-migrates the database on connection
     */
    private function ensureColumns() {
        try {
            $this->conn->exec("CREATE TABLE IF NOT EXISTS report_reminder_deliveries (
                report_id INT(11) NOT NULL,
                user_id INT(11) NOT NULL,
                stage VARCHAR(20) NOT NULL,
                started_at DATETIME NOT NULL,
                delivered_at DATETIME NOT NULL,
                PRIMARY KEY (user_id, report_id, stage, started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $this->conn->exec("CREATE TABLE IF NOT EXISTS report_reminder_checks (
                user_id INT(11) NOT NULL PRIMARY KEY,
                checked_at DATETIME NOT NULL,
                settings_key VARCHAR(80) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            // Report reasons are migrated once, rather than on each list load.
            foreach (['rejected_at' => 'DATETIME NULL', 'rejection_reason' => 'TEXT NULL',
                      'cancelled_at' => 'DATETIME NULL', 'cancellation_remarks' => 'TEXT NULL'] as $column => $definition) {
                if (!$this->columnExists('reports', $column)) {
                    $this->conn->exec("ALTER TABLE reports ADD COLUMN `{$column}` {$definition}");
                }
            }
            // Index the report scopes used by dashboards, maps and rate limits.
            $reportIndexes = [
                'idx_reports_created_status' => 'created_at, status',
                'idx_reports_barangay_created' => 'barangay_id, created_at, status',
                'idx_reports_user_created' => 'user_id, created_at',
                'idx_reports_resolved' => 'resolved_at',
            ];
            $indexCheck = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'reports' AND index_name = ?");
            foreach ($reportIndexes as $name => $columns) {
                foreach (explode(', ', $columns) as $column) {
                    if (!$this->columnExists('reports', $column)) continue 2;
                }
                $indexCheck->execute([$name]);
                if (!(int)$indexCheck->fetchColumn()) $this->conn->exec("ALTER TABLE reports ADD INDEX `{$name}` ({$columns})");
            }
            // Prepare OTP storage once per schema version, never during sends/resends.
            $this->conn->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), KEY user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            $this->conn->exec("CREATE TABLE IF NOT EXISTS verification_codes (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) NOT NULL,
                code VARCHAR(10) NOT NULL,
                expires_at DATETIME NOT NULL,
                used TINYINT(1) DEFAULT 0,
                type VARCHAR(20) DEFAULT 'forgot',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $typeCol = $this->conn->query("SHOW COLUMNS FROM verification_codes LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
            if (!$typeCol) {
                $this->conn->exec("ALTER TABLE verification_codes ADD COLUMN type VARCHAR(20) DEFAULT 'forgot'");
            } elseif (stripos($typeCol['Type'], 'enum') === 0) {
                $this->conn->exec("ALTER TABLE verification_codes MODIFY COLUMN type VARCHAR(20) DEFAULT 'forgot'");
            }

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
            // 3a. CHECK: quick_note_templates table (if not exists)
            // Smart suggestion (canned response) templates the MENRO admin
            // manages in Settings > Quick Note Templates. They appear as
            // clickable chips on the Barangay/MENRO Manage Report page above
            // the Investigation Note box and inside the Resolve Report modal.
            // ============================================
            $check = $this->conn->query("SHOW TABLES LIKE 'quick_note_templates'");
            if ($check->rowCount() == 0) {
                $this->conn->exec("
                    CREATE TABLE IF NOT EXISTS quick_note_templates (
                        id INT(11) NOT NULL AUTO_INCREMENT,
                        template_text TEXT NOT NULL,
                        target_category VARCHAR(191) NOT NULL DEFAULT '',
                        target_status VARCHAR(32) NOT NULL DEFAULT '',
                        is_active TINYINT(1) NOT NULL DEFAULT 1,
                        created_by INT(11) DEFAULT NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY idx_category (target_category),
                        KEY idx_status (target_status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ");
                error_log("[Database] Created 'quick_note_templates' table.");
            }

            // ============================================
            // 3b. CHECK: category_keywords table (if not exists)
            // Auto-correction dictionary — trigger words linked to report
            // categories. Managed by the MENRO admin in Settings >
            // Category Keywords. Used on the Submit Report page to sniff the
            // resident's description and auto-correct a mismatched category.
            // ============================================
            $check = $this->conn->query("SHOW TABLES LIKE 'category_keywords'");
            if ($check->rowCount() == 0) {
                $this->conn->exec("
                    CREATE TABLE IF NOT EXISTS category_keywords (
                        id INT(11) NOT NULL AUTO_INCREMENT,
                        keyword VARCHAR(100) NOT NULL,
                        category_id INT(11) NOT NULL,
                        is_active TINYINT(1) NOT NULL DEFAULT 1,
                        created_by INT(11) DEFAULT NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY idx_keyword (keyword),
                        KEY idx_category (category_id),
                        KEY idx_active (is_active)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ");
                error_log("[Database] Created 'category_keywords' table.");
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

            // ============================================
            // 6. CHECK: reports archiving columns + index
            // ============================================
            if (!$this->columnExists('reports', 'is_archived')) {
                $this->conn->exec("ALTER TABLE reports ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Hidden from active views by the archiving cron' AFTER verification_count");
                error_log("[Database] Added 'is_archived' column to reports table.");
            }
            if (!$this->columnExists('reports', 'archived_at')) {
                $this->conn->exec("ALTER TABLE reports ADD COLUMN archived_at DATETIME DEFAULT NULL COMMENT 'When the archiving job moved this report' AFTER is_archived");
                error_log("[Database] Added 'archived_at' column to reports table.");
            }
            $idx = $this->conn->query("SHOW INDEX FROM reports WHERE Key_name = 'idx_reports_archive'");
            if ($idx->rowCount() == 0) {
                $this->conn->exec("ALTER TABLE reports ADD INDEX idx_reports_archive (is_archived, status, created_at)");
                error_log("[Database] Added 'idx_reports_archive' index to reports table.");
            }

            // ============================================
            // 7. CHECK: archiving retention columns
            // ============================================
            if (!$this->columnExists('reports', 'archived_reason')) {
                $this->conn->exec("ALTER TABLE reports ADD COLUMN archived_reason VARCHAR(50) DEFAULT NULL COMMENT 'Why this report was archived (resolved / rejected)' AFTER archived_at");
                error_log("[Database] Added 'archived_reason' column to reports table.");
            }
            if (!$this->columnExists('announcements', 'is_archived')) {
                $this->conn->exec("ALTER TABLE announcements ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Hidden from active views by the archiving job' AFTER is_active");
                error_log("[Database] Added 'is_archived' column to announcements table.");
            }
            if (!$this->columnExists('announcements', 'archived_at')) {
                $this->conn->exec("ALTER TABLE announcements ADD COLUMN archived_at DATETIME DEFAULT NULL COMMENT 'When the archiving job moved this announcement' AFTER is_archived");
                error_log("[Database] Added 'archived_at' column to announcements table.");
            }

            // ============================================
            // 8. CHECK: announcement broadcast targeting
            // ============================================
            // broadcast_type: global_public | localized_public | internal_global | internal_direct
            $needsBroadcastBackfill = !$this->columnExists('announcements', 'broadcast_type');
            if ($needsBroadcastBackfill) {
                $this->conn->exec("ALTER TABLE announcements ADD COLUMN broadcast_type VARCHAR(30) NOT NULL DEFAULT 'localized_public' COMMENT 'Broadcast routing: global_public | localized_public | internal_global | internal_direct' AFTER is_active");
                error_log("[Database] Added 'broadcast_type' column to announcements table.");
            }
            if (!$this->columnExists('announcements', 'target_admin_id')) {
                $this->conn->exec("ALTER TABLE announcements ADD COLUMN target_admin_id INT(11) DEFAULT NULL COMMENT 'Specific barangay admin targeted by internal_direct' AFTER barangay_id");
                error_log("[Database] Added 'target_admin_id' column to announcements table.");
            }
            if (!$this->columnExists('announcements', 'expires_at')) {
                $this->conn->exec("ALTER TABLE announcements ADD COLUMN expires_at DATETIME DEFAULT NULL COMMENT 'When this announcement stops showing (auto-archived by the archiving job)' AFTER archived_at");
                error_log("[Database] Added 'expires_at' column to announcements table.");
            }
            // Backfill legacy rows: old "Public" announcements map to global_public;
            // barangay-scoped ones keep localized_public.
            if ($needsBroadcastBackfill) {
                $this->conn->exec("UPDATE announcements SET broadcast_type = 'global_public' WHERE is_public = 1 AND broadcast_type = 'localized_public'");
            }

            // ============================================
            // 9. CHECK: login rate-limit tables (brute-force lockout)
            // ============================================
            if (!$this->tableExists('rate_limits')) {
                $this->conn->exec("
                    CREATE TABLE IF NOT EXISTS rate_limits (
                        id INT(11) NOT NULL AUTO_INCREMENT,
                        identifier VARCHAR(191) NOT NULL,
                        action VARCHAR(50) NOT NULL DEFAULT 'login',
                        attempted_at DATETIME NOT NULL,
                        PRIMARY KEY (id),
                        KEY idx_rate_identifier_time (identifier, attempted_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ");
                error_log("[Database] Created 'rate_limits' table.");
            }
            if (!$this->tableExists('rate_lockouts')) {
                $this->conn->exec("
                    CREATE TABLE IF NOT EXISTS rate_lockouts (
                        id INT(11) NOT NULL AUTO_INCREMENT,
                        identifier VARCHAR(191) NOT NULL,
                        action VARCHAR(50) NOT NULL DEFAULT 'login',
                        locked_until DATETIME NOT NULL,
                        PRIMARY KEY (id),
                        UNIQUE KEY uq_rate_identifier_action (identifier, action)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ");
                error_log("[Database] Created 'rate_lockouts' table.");
            }

            // ============================================
            // 10. CHECK: notifications table (in-app notification bell)
            // ============================================
            if (!$this->tableExists('notifications')) {
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
                error_log("[Database] Created 'notifications' table.");
            } else {
                // Legacy table from database.sql may be missing the richer columns.
                if (!$this->columnExists('notifications', 'icon')) {
                    $this->conn->exec("ALTER TABLE notifications ADD COLUMN icon VARCHAR(50) DEFAULT 'fa-bell' AFTER type");
                }
                if (!$this->columnExists('notifications', 'color')) {
                    $this->conn->exec("ALTER TABLE notifications ADD COLUMN color VARCHAR(20) DEFAULT '#10A37F' AFTER icon");
                }
                if (!$this->columnExists('notifications', 'link')) {
                    $this->conn->exec("ALTER TABLE notifications ADD COLUMN link VARCHAR(255) DEFAULT '' AFTER color");
                }
            }

            // ============================================
            // 11. CHECK: user_devices table (new-device login detection)
            // ============================================
            if (!$this->tableExists('user_devices')) {
                $this->conn->exec("
                    CREATE TABLE IF NOT EXISTS user_devices (
                        id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                        user_id INT(11) NOT NULL,
                        device_key VARCHAR(64) NOT NULL,
                        device_name VARCHAR(191) DEFAULT NULL,
                        user_agent VARCHAR(255) DEFAULT NULL,
                        ip_address VARCHAR(64) DEFAULT NULL,
                        first_seen_at DATETIME DEFAULT NULL,
                        last_login_at DATETIME DEFAULT NULL,
                        PRIMARY KEY (id),
                        UNIQUE KEY uq_user_device (user_id, device_key),
                        KEY idx_user_ip (user_id, ip_address),
                        KEY idx_user (user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ");
                error_log("[Database] Created 'user_devices' table.");
            }

            // Centralized here instead of DDL in model constructors/login requests.
            if (!$this->columnExists('users', 'is_verified')) {
                $this->conn->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 1");
            }
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `activity_logs` (
                `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT(11) DEFAULT NULL,
                `actor_name` VARCHAR(191) DEFAULT NULL,
                `actor_role` VARCHAR(50) DEFAULT NULL,
                `target_module` VARCHAR(50) DEFAULT NULL,
                `action` VARCHAR(100) NOT NULL,
                `description` VARCHAR(500) DEFAULT NULL,
                `ip_address` VARCHAR(64) DEFAULT NULL,
                `user_agent` VARCHAR(255) DEFAULT NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'SUCCESS',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_user` (`user_id`),
                INDEX `idx_action` (`action`),
                INDEX `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $activityColumns = $this->conn->query("SHOW COLUMNS FROM activity_logs")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('user_agent', $activityColumns, true)) {
                $this->conn->exec("ALTER TABLE activity_logs ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL AFTER ip_address");
            }
            if (!in_array('status', $activityColumns, true)) {
                $this->conn->exec("ALTER TABLE activity_logs ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'SUCCESS' AFTER user_agent");
            }

            // Cover frequent list/poll/OTP lookups, including older imported
            // schemas. Skip any equivalent index even if it has another name.
            foreach ([
                'notifications' => ['idx_notif_recent' => ['user_id', 'created_at', 'id']],
                'verification_codes' => ['idx_verification_active' => ['user_id', 'type', 'used', 'expires_at']],
                'report_images' => ['idx_report_images_report' => ['report_id']],
                'reports' => ['idx_reports_status_created' => ['status', 'created_at']],
            ] as $table => $indexes) {
                if (!$this->tableExists($table)) continue;
                $existing = [];
                foreach ($this->conn->query("SHOW INDEX FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC) as $index) {
                    $existing[$index['Key_name']][(int)$index['Seq_in_index']] = $index['Column_name'];
                }
                foreach ($indexes as $name => $columns) {
                    $covered = false;
                    foreach ($existing as $indexedColumns) {
                        ksort($indexedColumns);
                        if (array_slice(array_values($indexedColumns), 0, count($columns)) === $columns) { $covered = true; break; }
                    }
                    if (!$covered) {
                        $columnSql = implode(', ', array_map(static function ($column) { return "`{$column}`"; }, $columns));
                        $this->conn->exec("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$columnSql})");
                    }
                }
            }

            // Native search indexes cover complete bodies. Restricted/older
            // databases retain literal-safe search if an index cannot be added.
            foreach ([
                'reports' => ['ft_search_reports', ['title','description','location_address']],
                'announcements' => ['ft_search_announcements', ['title','content']],
                'notifications' => ['ft_search_notifications', ['title','message']],
                'users' => ['ft_search_users', ['first_name','last_name','email']],
                'activity_logs' => ['ft_search_activity', ['action','description','actor_name']],
            ] as $searchTable => [$searchIndex, $searchColumns]) {
                try {
                    foreach ($searchColumns as $searchColumn) {
                        if (!$this->columnExists($searchTable,$searchColumn)) continue 2;
                    }
                    $checkSearchIndex = $this->conn->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?');
                    $checkSearchIndex->execute([$searchTable,$searchIndex]);
                    if (!(int)$checkSearchIndex->fetchColumn()) {
                        $this->conn->exec("ALTER TABLE `$searchTable` ADD FULLTEXT INDEX `$searchIndex` (".implode(',',array_map(fn($column)=>"`$column`",$searchColumns)).')');
                    }
                } catch (PDOException $searchIndexError) {
                    error_log('[Search] Native index unavailable for '.$searchTable.'. Using live text matching.');
                }
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
        $stmt = $this->conn->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Check if a column exists in a table
     * @param string $table Table name
     * @param string $column Column name
     * @return bool
     */
    public function columnExists($table, $column) {
        $stmt = $this->conn->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
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
