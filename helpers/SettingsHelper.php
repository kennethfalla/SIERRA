<?php
// helpers/SettingsHelper.php - COMPLETE SETTINGS MANAGEMENT (iProg Only)
// Features: Centralized settings management with caching, all settings types,
// SMS/Email templates, iProg SMS gateway configuration (GET method)

class SettingsHelper {
    private static $settings = null;
    private static $db = null;

    /**
     * Get a setting value by key
     * @param string $key Setting key
     * @param mixed $default Default value if key not found
     * @return mixed Setting value or default
     */
    public static function get($key, $default = null) {
        // Lazy load settings from database
        if (self::$settings === null) {
            self::loadAll();
        }

        // Check if setting exists
        if (isset(self::$settings[$key])) {
            return self::$settings[$key];
        }

        // Return default if provided
        if ($default !== null) {
            return $default;
        }

        // Check if we have a default for this specific key
        $defaults = self::getDefaultValues();
        return $defaults[$key] ?? null;
    }

    /**
     * Set a setting value
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool True on success
     */
    public static function set($key, $value) {
        if (self::$db === null) {
            $database = new Database();
            self::$db = $database->getConnection();
        }

        // Prepare value (convert arrays to JSON, etc.)
        if (is_array($value)) {
            $value = json_encode($value);
        }

        // Use distinct placeholders for the INSERT and UPDATE value (safer across
        // PDO emulation modes than reusing :value twice in the same statement).
        $stmt = self::$db->prepare(
            "INSERT INTO system_settings (setting_key, setting_value) 
             VALUES (:key, :value_insert) 
             ON DUPLICATE KEY UPDATE setting_value = :value_update"
        );
        $result = $stmt->execute([
            ':key' => $key,
            ':value_insert' => $value,
            ':value_update' => $value,
        ]);

        if (!$result) {
            error_log("SettingsHelper: Failed to save setting '$key' - " . implode(' | ', $stmt->errorInfo()));
        }

        // Update cache
        if ($result && self::$settings !== null) {
            self::$settings[$key] = $value;
        }

        return $result;
    }

    /**
     * Load all settings from database into cache
     */
    private static function loadAll() {
        if (self::$db === null) {
            $database = new Database();
            self::$db = $database->getConnection();
        }

        try {
            $stmt = self::$db->query("SELECT setting_key, setting_value FROM system_settings");
            self::$settings = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Try to decode JSON values
                $decoded = json_decode($row['setting_value'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    self::$settings[$row['setting_key']] = $decoded;
                } else {
                    self::$settings[$row['setting_key']] = $row['setting_value'];
                }
            }

            // Merge with defaults for missing settings
            $defaults = self::getDefaultValues();
            foreach ($defaults as $key => $value) {
                if (!isset(self::$settings[$key])) {
                    self::$settings[$key] = $value;
                }
            }
        } catch (PDOException $e) {
            // Table might not exist yet - initialize empty
            self::$settings = [];
            error_log("SettingsHelper: Failed to load settings - " . $e->getMessage());
        }
    }

    /**
     * Clear the settings cache
     */
    public static function clearCache() {
        self::$settings = null;
        error_log("SettingsHelper: Cache cleared.");
    }

    /**
     * Get default values for all settings
     * @return array Default settings
     */
    private static function getDefaultValues() {
        return [
            // ========================================
            // GENERAL SETTINGS
            // ========================================
            'system_name' => 'Sierra',
            'contact_email' => 'menro@sanisidro.gov.ph',
            'emergency_hotline' => '0917-123-4567',
            'lgu_logo' => '',

            // ========================================
            // SECURITY SETTINGS
            // ========================================
            'password_min_length' => 8,
            'max_login_attempts' => 5,
            'lockout_duration_minutes' => 30,
            'password_require_upper' => 1,
            'password_require_lower' => 1,
            'password_require_number' => 1,
            'password_require_special' => 1,

            // ========================================
            // FEATURE TOGGLES
            // ========================================
            'enable_public_registration' => 1,
            'show_heatmap' => 1,
            'allow_citizen_cancellations' => 1,
            'allow_edit_pending_reports' => 1,
            'enable_escalation' => 1,
            'enable_notifications' => 1,

            // ========================================
            // SEVERITY ALGORITHM
            // ========================================
            'impact_modifier_0' => 0,
            'impact_modifier_2' => 2,
            'impact_modifier_4' => 4,
            'density_points_0' => 0,
            'density_points_2' => 2,
            'density_points_4' => 4,
            'density_points_6' => 6,
            'clustering_radius_meters' => 50,

            // ========================================
            // KPI & INSIGHTS (Insight Engine targets)
            // ========================================
            'kpi_resolution_rate_target' => 60,
            'kpi_sla_response_hours' => 48,
            'kpi_surge_alert_threshold' => 25,
            'kpi_hotspot_radius_meters' => 10,
            'kpi_critical_reports_pct' => 30,
            'kpi_demographic_threshold' => 10,
            'kpi_repeat_min_reports' => 3,
            'kpi_repeat_window_days' => 30,

            // ========================================
            // MAP SETTINGS
            // ========================================
            'map_default_lat' => 15.3092,
            'map_default_lng' => 120.9033,
            'map_default_zoom' => 14,

            // ========================================
            // NOTIFICATION TEMPLATES
            // ========================================
            'template_submitted' => 'Thank you for submitting your environmental report. Your report #{report_id} has been received and is being reviewed by your barangay officials.',
            'template_status_update' => 'Your report #{report_id} status has been updated to {report_status}. Please login to view the details.',
            'template_resolved' => 'Your report #{report_id} has been resolved. Thank you for helping keep San Isidro clean!',
            'template_escalated' => 'Your report #{report_id} has been escalated to MENRO. They are now handling your concern.',

            // ========================================
            // STAFF ACCOUNT CREATION TEMPLATE
            // ========================================
            'template_staff_account_created' => 'Sierra LGU: Your {role} account is ready! Login: {login_url} Username: {email} Password: {temp_password} (Change on first login)',

            // ========================================
            // SMS GATEWAY SETTINGS (iProg Only)
            // ========================================
            'enable_sms_notifications' => 0,
            'sms_sender_name' => 'SierraLGU',

            // iProg SMS (Philippines)
            'iprog_api_key' => '',
            'iprog_sender_id' => '',
            'iprog_base_url' => 'https://sms.iprogtech.com/api/v1/sms_messages',

            // ========================================
            // ARCHIVING SETTINGS
            // ========================================
            'archive_after_days' => 30,
            'archive_rejected_days' => 60,

            // ========================================
            // BARANGAY SETTINGS
            // ========================================
            'barangay_captains' => '{}',

            // ========================================
            // PERMISSIONS (RBAC)
            // ========================================
            'permissions' => '{}',
        ];
    }

    /**
     * Check if a setting exists
     * @param string $key Setting key
     * @return bool
     */
    public static function exists($key) {
        if (self::$settings === null) {
            self::loadAll();
        }
        return isset(self::$settings[$key]);
    }

    /**
     * Get all settings as an array
     * @return array All settings
     */
    public static function getAll() {
        if (self::$settings === null) {
            self::loadAll();
        }
        return self::$settings;
    }

    /**
     * Get settings by prefix (e.g., 'template_' returns all template settings)
     * @param string $prefix Prefix to filter by
     * @return array Filtered settings
     */
    public static function getByPrefix($prefix) {
        if (self::$settings === null) {
            self::loadAll();
        }

        $result = [];
        foreach (self::$settings as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Get all SMS gateway settings (iProg only)
     * @return array SMS gateway settings
     */
    public static function getSmsSettings() {
        if (self::$settings === null) {
            self::loadAll();
        }

        return [
            'enable_sms_notifications' => self::$settings['enable_sms_notifications'] ?? 0,
            'sms_sender_name' => self::$settings['sms_sender_name'] ?? 'SierraLGU',
            
            // iProg
            'iprog_api_key' => self::$settings['iprog_api_key'] ?? '',
            'iprog_sender_id' => self::$settings['iprog_sender_id'] ?? '',
            'iprog_base_url' => self::$settings['iprog_base_url'] ?? 'https://sms.iprogtech.com/api/v1/sms_messages',
        ];
    }

    /**
     * Get all Map settings (clustering radius, default center, default zoom)
     * @return array Map settings
     */
    public static function getMapSettings() {
        if (self::$settings === null) {
            self::loadAll();
        }

        return [
            'clustering_radius_meters' => (int)(self::$settings['clustering_radius_meters'] ?? 50),
            'default_lat' => (float)(self::$settings['map_default_lat'] ?? 15.3092),
            'default_lng' => (float)(self::$settings['map_default_lng'] ?? 120.9033),
            'default_zoom' => (int)(self::$settings['map_default_zoom'] ?? 14),
        ];
    }

    /**
     * Get all notification templates
     * @return array Notification templates
     */
    public static function getTemplates() {
        if (self::$settings === null) {
            self::loadAll();
        }

        return [
            'template_submitted' => self::$settings['template_submitted'] ?? '',
            'template_status_update' => self::$settings['template_status_update'] ?? '',
            'template_resolved' => self::$settings['template_resolved'] ?? '',
            'template_escalated' => self::$settings['template_escalated'] ?? '',
            'template_staff_account_created' => self::$settings['template_staff_account_created'] ?? '',
        ];
    }

    /**
     * Get a notification template with default fallback
     * @param string $key Template key
     * @return string Template content
     */
    public static function getTemplate($key) {
        $defaults = [
            'template_submitted' => 'Thank you for submitting your environmental report. Your report #{report_id} has been received and is being reviewed by your barangay officials.',
            'template_status_update' => 'Your report #{report_id} status has been updated to {report_status}. Please login to view the details.',
            'template_resolved' => 'Your report #{report_id} has been resolved. Thank you for helping keep San Isidro clean!',
            'template_escalated' => 'Your report #{report_id} has been escalated to MENRO. They are now handling your concern.',
            'template_staff_account_created' => 'Sierra LGU: Your {role} account is ready! Login: {login_url} Username: {email} Password: {temp_password} (Change on first login)',
        ];

        $value = self::get($key);
        if (empty($value)) {
            return $defaults[$key] ?? '';
        }
        return $value;
    }

    /**
     * Parse a template with placeholders
     * @param string $template Template with placeholders
     * @param array $data Data to replace placeholders with
     * @return string Parsed template
     */
    public static function parseTemplate($template, $data = []) {
        if (empty($template)) {
            return '';
        }

        // Add system-wide placeholders
        $data['{system_name}'] = self::get('system_name', 'Sierra');
        $data['{login_url}'] = BASE_URL . 'index.php?page=login';
        $data['{site_url}'] = BASE_URL;

        // Replace all placeholders
        foreach ($data as $key => $value) {
            if (!is_string($value) && !is_numeric($value)) {
                $value = '';
            }
            $template = str_replace($key, $value, $template);
        }

        return $template;
    }

    /**
     * Initialize default settings in database
     * @return bool True on success
     */
    public static function initializeDefaults() {
        if (self::$db === null) {
            $database = new Database();
            self::$db = $database->getConnection();
        }

        try {
            // Check if system_settings table exists
            $check = self::$db->query("SHOW TABLES LIKE 'system_settings'");
            if ($check->rowCount() == 0) {
                // Table doesn't exist - create it
                self::$db->exec("
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
                error_log("SettingsHelper: Created system_settings table");
            }

            // Insert default settings if they don't exist
            $defaults = self::getDefaultValues();
            foreach ($defaults as $key => $value) {
                $stmt = self::$db->prepare(
                    "INSERT INTO system_settings (setting_key, setting_value) 
                     VALUES (:key, :value) 
                     ON DUPLICATE KEY UPDATE setting_value = setting_value"
                );
                $stmt->execute([':key' => $key, ':value' => $value]);
            }

            self::clearCache();
            return true;
        } catch (PDOException $e) {
            error_log("SettingsHelper: Failed to initialize defaults - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a setting
     * @param string $key Setting key
     * @return bool True on success
     */
    public static function delete($key) {
        if (self::$db === null) {
            $database = new Database();
            self::$db = $database->getConnection();
        }

        $stmt = self::$db->prepare("DELETE FROM system_settings WHERE setting_key = :key");
        $result = $stmt->execute([':key' => $key]);

        // Update cache
        if ($result && self::$settings !== null) {
            unset(self::$settings[$key]);
        }

        return $result;
    }

    /**
     * Get multiple settings at once
     * @param array $keys Array of setting keys
     * @return array Key-value pairs
     */
    public static function getMultiple($keys) {
        if (self::$settings === null) {
            self::loadAll();
        }

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::$settings[$key] ?? null;
        }
        return $result;
    }

    /**
     * Set multiple settings at once
     * @param array $settings Key-value pairs
     * @return bool True on success
     */
    public static function setMultiple($settings) {
        $success = true;
        foreach ($settings as $key => $value) {
            if (!self::set($key, $value)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Get active SMS gateway configuration (iProg only)
     * @return array|null Active gateway config or null if not configured
     */
    public static function getActiveSmsGateway() {
        $settings = self::getSmsSettings();

        // SMS must be enabled
        if ($settings['enable_sms_notifications'] != 1) {
            error_log("SettingsHelper: SMS is disabled (enable_sms_notifications = " . $settings['enable_sms_notifications'] . ")");
            return null;
        }

        // Check iProg
        if (!empty($settings['iprog_api_key'])) {
            error_log("SettingsHelper: iProg gateway found. API key: " . substr($settings['iprog_api_key'], 0, 10) . "...");
            return [
                'gateway' => 'iprog',
                'api_key' => $settings['iprog_api_key'],
                'sender_id' => $settings['iprog_sender_id'] ?? '',
                'base_url' => $settings['iprog_base_url'] ?? 'https://sms.iprogtech.com/api/v1/sms_messages',
            ];
        }

        error_log("SettingsHelper: No active gateway found (iprog_api_key is empty).");
        return null;
    }

    /**
     * Check if SMS is configured and enabled
     * @return bool
     */
    public static function isSmsEnabled() {
        $settings = self::getSmsSettings();
        if ($settings['enable_sms_notifications'] != 1) {
            return false;
        }

        return self::getActiveSmsGateway() !== null;
    }

    /**
     * Send SMS using iProg gateway (GET method with query parameters)
     * 
     * @param string $phone_number Recipient phone number
     * @param string $message SMS message
     * @return bool True on success
     */
    public static function sendSms($phone_number, $message, $gatewayOverride = null) {
        // Clear cache to ensure we're using the latest settings
        self::clearCache();

        $gateway = $gatewayOverride;

        if ($gateway === null) {
            if (!self::isSmsEnabled()) {
                error_log("SMS not sent: SMS is not enabled or configured.");
                return false;
            }
            $gateway = self::getActiveSmsGateway();
        }

        if (!$gateway || empty($gateway['api_key'])) {
            error_log("SMS not sent: No active gateway found.");
            return false;
        }

        // Format phone number to international format (for iProg)
        // iProg expects: 63XXXXXXXXXX (no leading 0)
        $phone = preg_replace('/[^0-9]/', '', $phone_number);
        if (strlen($phone) === 10) {
            $phone = '63' . $phone;
        } elseif (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
            $phone = '63' . substr($phone, 1);
        } elseif (strlen($phone) === 12 && substr($phone, 0, 2) === '63') {
            // Already in international format
            $phone = $phone;
        }

        $sender_name = self::get('sms_sender_name', 'SierraLGU');
        $sender_name = substr($sender_name, 0, 11);

        // ============================================
        // iProg SMS Gateway
        // NOTE: iProg's API requires POST (not GET), and its Send SMS
        // endpoint only accepts api_token, phone_number, message, and
        // an optional sms_provider - there is no per-request "sender"
        // field. Sender identity is configured on the iProg dashboard.
        // Docs: https://sms.iprogtech.com/api/v1/documentation
        // ============================================
        if ($gateway['gateway'] === 'iprog') {
            $params = [
                'api_token' => $gateway['api_key'],
                'phone_number' => $phone,
                'message' => $message,
            ];

            $url = $gateway['base_url'];

            // Log the request (mask API key for security)
            error_log("iProg SMS Request: POST " . $url . " (phone=" . $phone . ")");

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // Log response
            error_log("iProg SMS Response: HTTP " . $http_code . " - " . substr($response, 0, 500));
            if ($error) {
                error_log("iProg SMS cURL Error: " . $error);
            }

            // Check if response contains success indicators
            $success = false;
            
            // Check HTTP status code
            if ($http_code === 200 || $http_code === 201 || $http_code === 202) {
                // Try to parse JSON response
                $json = json_decode($response, true);
                if ($json) {
                    // iProg's actual success response looks like:
                    // {"status": 200, "message": "...", "message_id": "iSms-XHYBk"}
                    if (isset($json['status']) && ((int)$json['status'] === 200)) {
                        $success = true;
                    } elseif (isset($json['status']) && in_array(strtolower((string)$json['status']), ['success', 'ok', 'sent'])) {
                        $success = true;
                    } elseif (isset($json['success']) && $json['success'] === true) {
                        $success = true;
                    } elseif (isset($json['message_id']) || isset($json['id'])) {
                        $success = true;
                    }
                } else {
                    // If not JSON, check if response is a string success indicator
                    $response_lower = strtolower($response);
                    if (strpos($response_lower, 'success') !== false || 
                        strpos($response_lower, 'sent') !== false ||
                        strpos($response_lower, 'ok') !== false ||
                        strpos($response_lower, '"status":"ok"') !== false) {
                        $success = true;
                    }
                }
            }

            if ($success) {
                error_log("iProg SMS sent successfully!");
            } else {
                error_log("iProg SMS failed. HTTP: " . $http_code . ", Response: " . $response);
            }

            return $success;
        }

        error_log("SMS not sent: Unknown gateway configured.");
        return false;
    }

    /**
     * Get the system name
     * @return string System name
     */
    public static function getSystemName() {
        return self::get('system_name', 'Sierra');
    }

    /**
     * Get the LGU logo URL
     * @return string Logo URL or empty string
     */
    public static function getLogoUrl() {
        $logo = self::get('lgu_logo', '');
        if (empty($logo)) {
            return '';
        }
        return BASE_URL . $logo;
    }

    /**
     * Get contact email
     * @return string Contact email
     */
    public static function getContactEmail() {
        return self::get('contact_email', 'menro@sanisidro.gov.ph');
    }

    /**
     * Get emergency hotline
     * @return string Emergency hotline
     */
    public static function getEmergencyHotline() {
        return self::get('emergency_hotline', '0917-123-4567');
    }

    /**
     * Get iProg gateway status
     * @return array
     */
    public static function getGatewayStatus() {
        $settings = self::getSmsSettings();
        
        return [
            'iprog' => [
                'name' => 'iProg',
                'configured' => !empty($settings['iprog_api_key']),
                'enabled' => $settings['enable_sms_notifications'] == 1,
                'fields' => ['iprog_api_key', 'iprog_sender_id'],
                'endpoint' => $settings['iprog_base_url'] ?? 'https://sms.iprogtech.com/api/v1/sms_messages',
                'method' => 'GET',
                'api_key_set' => !empty($settings['iprog_api_key'])
            ]
        ];
    }

    /**
     * Get iProg specific settings
     * @return array
     */
    public static function getIprogSettings() {
        if (self::$settings === null) {
            self::loadAll();
        }

        return [
            'api_key' => self::$settings['iprog_api_key'] ?? '',
            'sender_id' => self::$settings['iprog_sender_id'] ?? '',
            'base_url' => self::$settings['iprog_base_url'] ?? 'https://sms.iprogtech.com/api/v1/sms_messages',
            'enabled' => (self::$settings['enable_sms_notifications'] ?? 0) == 1,
            'configured' => !empty(self::$settings['iprog_api_key']),
        ];
    }

    // ========================================================
    // PERMISSIONS (RBAC) — dynamic, admin-created roles
    // ========================================================
    // Roles now live in the `roles` table (Create Role feature) instead
    // of being hardcoded to the users.role enum. `users.role` is still
    // used for the hard citizen/barangay_official/admin boundary
    // (requireRole()); `users.role_id` points at the custom role that
    // drives these 9 feature-level permission toggles; `users.user_type`
    // (barangay_personnel/menro_staff/admin) drives the report-scoping
    // runtime logic in PermissionHelper::canManageReport().
    // ========================================================

    private static function getDb() {
        if (self::$db === null) {
            $database = new Database();
            self::$db = $database->getConnection();
        }
        return self::$db;
    }

    /**
     * The permission toggles available on the Permissions tab / Create
     * Role modal. Nine feature-level permissions. Each maps onto the
     * finer-grained capabilities that previously lived in the six legacy
     * keys (see migratePermissionKeys() for the old -> new mapping).
     * @return array<string,string> permission_key => label
     */
    public static function getPermissionKeys() {
        return [
            'can_view_reports'    => 'View Reports',
            'can_manage_reports'  => 'Manage Reports',
            'can_view_map'        => 'Map & Geotagging',
            'can_view_analytics'  => 'Analytics & Dashboard',
            'can_manage_evidence' => 'Evidence Management',
            'can_manage_users'    => 'User Management',
            'can_manage_staff'    => 'Staff Management',
            'can_export_reports'  => 'Reports & Export',
            'can_manage_system'   => 'System Management',
        ];
    }

    /**
     * Legacy permission-key aliases. The six original keys were folded
     * into the nine feature-level keys, so any code that still checks an
     * old key resolves it to its new equivalent. This keeps every existing
     * gate working without removing any functionality.
     * @return array<string,string> legacy_key => new_key
     */
    public static function getPermissionAliases() {
        return [
            'can_edit_settings'           => 'can_manage_system',
            'can_manage_categories'       => 'can_manage_system',
            'can_broadcast_announcements' => 'can_manage_system',
            'can_delete_users'            => 'can_manage_users',
        ];
    }

    /**
     * Resolve a (possibly legacy) permission key to its canonical key.
     * @param string $key
     * @return string
     */
    public static function resolvePermissionKey($key) {
        $aliases = self::getPermissionAliases();
        return $aliases[$key] ?? $key;
    }

    /**
     * One-time migration from the six legacy permission keys to the nine
     * feature-level keys. Existing grants are preserved as closely as
     * possible:
     *   - can_manage_reports  -> can_manage_reports (unchanged)
     *   - can_export_reports  -> can_export_reports (unchanged)
     *   - can_edit_settings | can_manage_categories | can_broadcast_announcements
     *                         -> can_manage_system
     *   - can_delete_users    -> can_manage_users + can_manage_staff
     * New read-only keys (can_view_map / can_view_analytics) inherit the
     * View Reports grant; can_manage_evidence inherits Manage Reports.
     * "Manage Reports" always implies "View Reports".
     * @return bool
     */
    public static function migratePermissionKeys() {
        $db = self::getDb();
        try {
            $roles = self::getAllRoles();
            if (empty($roles)) {
                return true;
            }

            $stmt = $db->query("SELECT role_id, permission_key, is_granted FROM role_permissions");
            $stored = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $stored[$row['role_id']][$row['permission_key']] = (bool)$row['is_granted'];
            }

            foreach ($roles as $role) {
                $roleId   = (int)$role['id'];
                $old      = $stored[$roleId] ?? [];

                $manage   = !empty($old['can_manage_reports']);
                $view     = !empty($old['can_view_reports']) || $manage;

                $new = [
                    'can_view_reports'    => $view,
                    'can_manage_reports'  => $manage,
                    'can_view_map'        => !empty($old['can_view_map']) ? true : $view,
                    'can_view_analytics'  => !empty($old['can_view_analytics']) ? true : $view,
                    'can_manage_evidence' => !empty($old['can_manage_evidence']) ? true : $manage,
                    'can_manage_users'    => !empty($old['can_manage_users']) ? true : !empty($old['can_delete_users']),
                    'can_manage_staff'    => !empty($old['can_manage_staff']) ? true : !empty($old['can_delete_users']),
                    'can_export_reports'  => !empty($old['can_export_reports']),
                    'can_manage_system'   => !empty($old['can_manage_system'])
                        ? true
                        : (!empty($old['can_edit_settings']) || !empty($old['can_manage_categories']) || !empty($old['can_broadcast_announcements'])),
                ];

                // Always persist the full nine-key set so the page renders
                // every toggle (delete + re-insert keeps it idempotent).
                self::savePermissionsForRole($roleId, $new);
            }

            return true;
        } catch (Exception $e) {
            error_log("migratePermissionKeys failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * All roles (built-in + admin-created), id => title, in creation order.
     * @return array<int,string>
     */
    public static function getManageableRoles() {
        $roles = [];
        foreach (self::getAllRoles() as $role) {
            $roles[$role['id']] = $role['title'];
        }
        return $roles;
    }

    /**
     * Full role rows (id, title, description, is_system, ...).
     * @return array<int,array>
     */
    public static function getAllRoles() {
        $stmt = self::getDb()->query("SELECT * FROM roles ORDER BY is_system DESC, id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRoleById($roleId) {
        $stmt = self::getDb()->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create a new custom role with its permission set.
     * @param string $title
     * @param string $description
     * @param array<string,bool> $permissions keyed by permission_key
     * @param int|null $createdBy
     * @return int|false new role id, or false on failure
     */
    public static function createRole($title, $description, array $permissions, $createdBy = null) {
        $title = trim($title);
        if ($title === '') {
            return false;
        }

        $db = self::getDb();
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO roles (title, description, is_system, created_by) VALUES (?, ?, 0, ?)");
            $stmt->execute([$title, $description !== '' ? $description : null, $createdBy]);
            $roleId = (int)$db->lastInsertId();

            self::savePermissionsForRole($roleId, $permissions);

            $db->commit();
            return $roleId;
        } catch (Exception $e) {
            $db->rollBack();
            error_log("createRole failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing role's title/description/permissions.
     * @return bool
     */
    public static function updateRole($roleId, $title, $description, array $permissions) {
        $title = trim($title);
        if ($title === '' || $roleId <= 0) {
            return false;
        }

        $db = self::getDb();
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("UPDATE roles SET title = ?, description = ? WHERE id = ?");
            $stmt->execute([$title, $description !== '' ? $description : null, $roleId]);

            self::savePermissionsForRole($roleId, $permissions);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            error_log("updateRole failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a custom role. Built-in (is_system) roles and roles still
     * assigned to at least one user cannot be deleted.
     * @return true|string true on success, or an error message string
     */
    public static function deleteRole($roleId) {
        $db = self::getDb();

        $role = self::getRoleById($roleId);
        if (!$role) {
            return "Role not found.";
        }
        if ((int)$role['is_system'] === 1) {
            return "Built-in roles cannot be deleted.";
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
        $stmt->execute([$roleId]);
        if ((int)$stmt->fetchColumn() > 0) {
            return "Cannot delete a role that is still assigned to one or more users. Reassign those users first.";
        }

        $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
        return $stmt->execute([$roleId]) ? true : "Failed to delete role.";
    }

    /**
     * Whitelist-save a role's permission rows (delete + re-insert keeps
     * this simple and avoids partial/mismatched rows).
     * Enforces the dependency rule: granting "Manage Reports" always
     * implies "View Reports", and revoking "View Reports" always revokes
     * "Manage Reports".
     */
    private static function savePermissionsForRole($roleId, array $permissions) {
        $db = self::getDb();
        $allowedKeys = array_keys(self::getPermissionKeys());

        // Manage Reports => View Reports dependency.
        $manage = !empty($permissions['can_manage_reports']);
        $permissions['can_view_reports'] = $manage;

        $del = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $del->execute([$roleId]);

        $ins = $db->prepare("INSERT INTO role_permissions (role_id, permission_key, is_granted) VALUES (?, ?, ?)");
        foreach ($allowedKeys as $key) {
            $ins->execute([$roleId, $key, !empty($permissions[$key]) ? 1 : 0]);
        }
    }

    /**
     * Get the full role_id => permissions matrix, merged with false-
     * defaults so every role and every permission key is always present.
     * @return array<int,array<string,bool>>
     */
    public static function getAllRolePermissions() {
        $permissionKeys = array_keys(self::getPermissionKeys());
        $roles = self::getAllRoles();

        $stmt = self::getDb()->query("SELECT role_id, permission_key, is_granted FROM role_permissions");
        $stored = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stored[$row['role_id']][$row['permission_key']] = (bool)$row['is_granted'];
        }

        $result = [];
        foreach ($roles as $role) {
            $roleId = $role['id'];
            $result[$roleId] = [];
            foreach ($permissionKeys as $key) {
                $result[$roleId][$key] = $stored[$roleId][$key] ?? false;
            }
        }
        return $result;
    }

    /**
     * Get the permissions for a single role id.
     * @return array<string,bool>
     */
    public static function getRolePermissions($roleId) {
        $all = self::getAllRolePermissions();
        return $all[$roleId] ?? array_fill_keys(array_keys(self::getPermissionKeys()), false);
    }

    /**
     * Check whether a given role id has a given permission.
     * Note: the primary super-admin (users.user_type = 'admin') bypasses
     * all of these — see PermissionHelper::userHasPermission() for the
     * user-level check that applies that bypass. This function checks
     * the role's stored permission only.
     * @param int $roleId
     * @param string $permissionKey e.g. 'can_manage_system'
     * @return bool
     */
    public static function hasPermission($roleId, $permissionKey) {
        $perms = self::getRolePermissions($roleId);
        return !empty($perms[self::resolvePermissionKey($permissionKey)]);
    }
}

// ============================================================
// INITIALIZE DEFAULTS ON FIRST RUN
// ============================================================
// This will run automatically when the file is included
// but only if the settings haven't been initialized yet
if (SettingsHelper::get('system_name') === null) {
    SettingsHelper::initializeDefaults();
}

// ============================================================
// ONE-TIME PERMISSION KEY MIGRATION (6 legacy -> 9 features)
// ============================================================
// Runs once, gated by a system_settings flag. Converts existing
// role_permissions rows from the old keys to the new nine so that
// every role keeps the grants it had before the redesign.
if (SettingsHelper::get('permissions_v9_migrated') !== '1') {
    if (SettingsHelper::migratePermissionKeys()) {
        SettingsHelper::set('permissions_v9_migrated', '1');
    }
    SettingsHelper::clearCache();
}
?>