<?php
// controllers/SettingsController.php - COMPLETE SETTINGS CONTROLLER
// Features: General Settings, Security, Features, Tags, Algorithm, 
// Notifications (iProg SMS), Map, Archiving, Barangays, Permissions

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/helpers/SecurityHelper.php';
require_once dirname(__DIR__) . '/helpers/SettingsHelper.php';

// ============================================
// ENSURE USER IS LOGGED IN AND IS ADMIN
// ============================================
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];
$database = new Database();
$db = $database->getConnection();

// ============================================
// SETTINGS CONTROLLER CLASS
// ============================================
class SettingsController {
    private $db;
    private $user_id;

    public function __construct($db, $user_id) {
        $this->db = $db;
        $this->user_id = $user_id;
    }

    /**
     * Main entry point for POST requests
     */
    public function update($tab) {
        // CSRF validation
        if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
            $_SESSION['error'] = "Invalid security token. Please try again.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=" . $tab);
            exit();
        }

        // Route to the appropriate handler based on tab
        $method = 'update' . ucfirst($tab);
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            $_SESSION['error'] = "Invalid settings tab.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=general");
            exit();
        }
    }

    // ================================================================
    // 1. GENERAL SETTINGS (System Name, Email, Hotline, Logo)
    // ================================================================
    private function updateGeneral() {
        $system_name = InputSanitizer::sanitizeString($_POST['system_name'] ?? 'Sierra');
        $contact_email = InputSanitizer::sanitizeEmail($_POST['contact_email'] ?? '');
        $emergency_hotline = InputSanitizer::sanitizeString($_POST['emergency_hotline'] ?? '');

        if (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Please enter a valid email address.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=general");
            exit();
        }

        SettingsHelper::set('system_name', $system_name);
        SettingsHelper::set('contact_email', $contact_email);
        SettingsHelper::set('emergency_hotline', $emergency_hotline);

        // Handle logo upload
        if (isset($_FILES['lgu_logo']) && $_FILES['lgu_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['lgu_logo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed) && $file['size'] <= 5242880) {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/uploads/settings/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $old_logo = SettingsHelper::get('lgu_logo');
                if ($old_logo && file_exists($_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/' . $old_logo)) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/' . $old_logo);
                }
                $new_filename = 'logo_' . time() . '.' . $ext;
                $target_path = $upload_dir . $new_filename;
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    SettingsHelper::set('lgu_logo', 'uploads/settings/' . $new_filename);
                } else {
                    $_SESSION['error'] = "Logo upload failed.";
                    header("Location: " . BASE_URL . "index.php?page=settings&tab=general");
                    exit();
                }
            } else {
                $_SESSION['error'] = "Invalid logo file. Allowed: JPG, PNG, GIF, WebP (max 5MB).";
                header("Location: " . BASE_URL . "index.php?page=settings&tab=general");
                exit();
            }
        }

        SettingsHelper::clearCache();
        $_SESSION['success'] = "General settings saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=general");
        exit();
    }

    // ================================================================
    // 2. SECURITY SETTINGS (Password Policies, Lockout)
    // ================================================================
    private function updateSecurity() {
        $min_length = (int)($_POST['password_min_length'] ?? 8);
        $max_attempts = (int)($_POST['max_login_attempts'] ?? 5);
        $lockout_duration = (int)($_POST['lockout_duration_minutes'] ?? 30);

        // Clamp values
        $min_length = max(6, min(20, $min_length));
        $max_attempts = max(3, min(10, $max_attempts));
        $lockout_duration = max(5, min(1440, $lockout_duration));

        SettingsHelper::set('password_min_length', $min_length);
        SettingsHelper::set('max_login_attempts', $max_attempts);
        SettingsHelper::set('lockout_duration_minutes', $lockout_duration);

        // Password requirements (checkboxes)
        $requirements = ['require_upper', 'require_lower', 'require_number', 'require_special'];
        foreach ($requirements as $req) {
            $value = isset($_POST['password_' . $req]) ? 1 : 0;
            SettingsHelper::set('password_' . $req, $value);
        }

        SettingsHelper::clearCache();
        $_SESSION['success'] = "Security settings saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=security");
        exit();
    }

    // ================================================================
    // 3. FEATURE TOGGLES
    // ================================================================
    private function updateFeatures() {
        $features = [
            'enable_public_registration',
            'show_heatmap',
            'allow_citizen_cancellations',
            'allow_edit_pending_reports',
            'enable_escalation',
            'enable_notifications'
        ];
        foreach ($features as $feature) {
            $value = isset($_POST[$feature]) ? 1 : 0;
            SettingsHelper::set($feature, $value);
        }
        SettingsHelper::clearCache();
        $_SESSION['success'] = "Feature toggles saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=features");
        exit();
    }

    // ================================================================
    // 4. CUSTOM TAGS (Create, Edit, Delete)
    // ================================================================
    private function updateTags() {
        $sub_action = $_POST['sub_action'] ?? '';
        if ($sub_action === 'add') {
            $name = InputSanitizer::sanitizeString($_POST['name'] ?? '');
            $color = InputSanitizer::sanitizeString($_POST['color'] ?? '#6B7280');
            if (empty($name)) {
                $_SESSION['error'] = "Tag name cannot be empty.";
                header("Location: " . BASE_URL . "index.php?page=settings&tab=tags");
                exit();
            }
            $stmt = $this->db->prepare("INSERT INTO custom_tags (name, color) VALUES (?, ?)");
            $stmt->execute([$name, $color]);
            $_SESSION['success'] = "Tag added successfully!";
        } elseif ($sub_action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $this->db->prepare("DELETE FROM custom_tags WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = "Tag deleted successfully!";
            } else {
                $_SESSION['error'] = "Invalid tag ID.";
            }
        } elseif ($sub_action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = InputSanitizer::sanitizeString($_POST['name'] ?? '');
            $color = InputSanitizer::sanitizeString($_POST['color'] ?? '#6B7280');
            if ($id > 0 && !empty($name)) {
                $stmt = $this->db->prepare("UPDATE custom_tags SET name = ?, color = ? WHERE id = ?");
                $stmt->execute([$name, $color, $id]);
                $_SESSION['success'] = "Tag updated successfully!";
            } else {
                $_SESSION['error'] = "Invalid tag data.";
            }
        } else {
            $_SESSION['error'] = "Invalid tag action.";
        }
        header("Location: " . BASE_URL . "index.php?page=settings&tab=tags");
        exit();
    }

    // ================================================================
    // 5. SEVERITY ALGORITHM TUNER
    // ================================================================
    private function updateAlgorithm() {
        // Impact modifier points
        $impact_0 = (int)($_POST['impact_modifier_0'] ?? 0);
        $impact_2 = (int)($_POST['impact_modifier_2'] ?? 2);
        $impact_4 = (int)($_POST['impact_modifier_4'] ?? 4);
        SettingsHelper::set('impact_modifier_0', $impact_0);
        SettingsHelper::set('impact_modifier_2', $impact_2);
        SettingsHelper::set('impact_modifier_4', $impact_4);

        // Density points
        $density_0 = (int)($_POST['density_points_0'] ?? 0);
        $density_2 = (int)($_POST['density_points_2'] ?? 2);
        $density_4 = (int)($_POST['density_points_4'] ?? 4);
        $density_6 = (int)($_POST['density_points_6'] ?? 6);
        SettingsHelper::set('density_points_0', $density_0);
        SettingsHelper::set('density_points_2', $density_2);
        SettingsHelper::set('density_points_4', $density_4);
        SettingsHelper::set('density_points_6', $density_6);

        // Clustering radius (also used in map settings)
        $radius = (int)($_POST['clustering_radius_meters'] ?? 50);
        SettingsHelper::set('clustering_radius_meters', max(10, min(200, $radius)));

        // Critical threshold (score at/above which a report is flagged CRITICAL / Red)
        $critical_threshold = (int)($_POST['critical_threshold_score'] ?? 15);
        SettingsHelper::set('critical_threshold_score', max(1, min(100, $critical_threshold)));

        // Verification / upvote bonus
        $points_per_upvote = (int)($_POST['verification_points_per_upvote'] ?? 1);
        $max_verification_points = (int)($_POST['verification_max_points'] ?? 5);
        SettingsHelper::set('verification_points_per_upvote', max(0, min(10, $points_per_upvote)));
        SettingsHelper::set('verification_max_points', max(0, min(20, $max_verification_points)));

        SettingsHelper::clearCache();
        $_SESSION['success'] = "Algorithm settings saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=algorithm");
        exit();
    }

    // ================================================================
    // 6. NOTIFICATION TEMPLATES + SMS GATEWAY SETTINGS (iProg Only)
    // ================================================================
    private function updateNotifications() {
        // ============================================
        // 6a. EMAIL/SMS TEMPLATES
        // ============================================
        $templates = [
            'template_submitted',
            'template_status_update',
            'template_resolved',
            'template_escalated',
            'template_staff_account_created'
        ];

        foreach ($templates as $key) {
            $value = $_POST[$key] ?? '';
            SettingsHelper::set($key, $value);
        }

        // ============================================
        // 6b. SMS GATEWAY SETTINGS (iProg Only)
        // ============================================
        $sms_settings = [
            'enable_sms_notifications' => isset($_POST['enable_sms_notifications']) ? 1 : 0,
            'sms_sender_name' => InputSanitizer::sanitizeString($_POST['sms_sender_name'] ?? 'SierraLGU', 11),
            'iprog_api_key' => trim($_POST['iprog_api_key'] ?? ''),
            'iprog_sender_id' => trim($_POST['iprog_sender_id'] ?? ''),
            'iprog_base_url' => trim($_POST['iprog_base_url'] ?? 'https://sms.iprogtech.com/api/v1/sms_messages'),
        ];

        foreach ($sms_settings as $key => $value) {
            SettingsHelper::set($key, $value);
        }

        SettingsHelper::clearCache();

        $_SESSION['success'] = "Notification templates and SMS settings saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=notifications");
        exit();
    }

    // ================================================================
    // 7. MAP SETTINGS (Clustering Radius)
    // ================================================================
    private function updateMap() {
        $radius = (int)($_POST['clustering_radius_meters'] ?? 50);
        SettingsHelper::set('clustering_radius_meters', max(10, min(200, $radius)));
        SettingsHelper::clearCache();
        $_SESSION['success'] = "Map settings saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=map");
        exit();
    }

    // ================================================================
    // 8. AUTO-ARCHIVING RULES
    // ================================================================
    private function updateArchiving() {
        $days = (int)($_POST['archive_after_days'] ?? 30);
        SettingsHelper::set('archive_after_days', max(0, min(365, $days)));
        SettingsHelper::clearCache();
        $_SESSION['success'] = "Archiving rules saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=archiving");
        exit();
    }

    // ================================================================
    // 9. BARANGAY MANAGER
    // ================================================================
    private function updateBarangays() {
        $sub_action = $_POST['sub_action'] ?? '';
        if ($sub_action === 'edit') {
            $id = (int)($_POST['barangay_id'] ?? 0);
            $captain_name = InputSanitizer::sanitizeName($_POST['captain_name'] ?? '');
            $captain_contact = InputSanitizer::sanitizeString($_POST['captain_contact'] ?? '');
            if ($id > 0) {
                $stmt = $this->db->prepare("UPDATE barangays SET captain_name = ?, captain_contact = ? WHERE id = ?");
                $stmt->execute([$captain_name, $captain_contact, $id]);
                $_SESSION['success'] = "Barangay updated successfully!";
            } else {
                $_SESSION['error'] = "Invalid barangay ID.";
            }
        } else {
            $_SESSION['error'] = "Invalid action.";
        }
        header("Location: " . BASE_URL . "index.php?page=settings&tab=barangays");
        exit();
    }

    // ================================================================
    // 10. PERMISSIONS (RBAC) – Simple JSON storage
    // ================================================================
    private function updatePermissions() {
        $permissions_data = $_POST['permissions'] ?? [];
        foreach ($permissions_data as $user_id => $perms) {
            $user_id = (int)$user_id;
            if ($user_id > 0) {
                $json = json_encode($perms);
                $stmt = $this->db->prepare("UPDATE users SET permissions = ? WHERE id = ?");
                $stmt->execute([$json, $user_id]);
            }
        }
        $_SESSION['success'] = "Permissions updated successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=permissions");
        exit();
    }

    // ================================================================
    // 11. SEND TEST SMS (AJAX endpoint - iProg Only)
    // ================================================================
    public function sendTestSMS() {
        // Ensure JSON response
        header('Content-Type: application/json; charset=utf-8');
        // CSRF validation
        if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
            exit();
        }

        $phone = InputSanitizer::sanitizePhone($_POST['phone'] ?? '');
        $message = InputSanitizer::sanitizeString($_POST['message'] ?? '');

        if (!$phone) {
            echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
            exit();
        }

        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty.']);
            exit();
        }

        // If the admin has entered an API key in the form, save it and test with it.
        $gatewayOverride = null;
        if (isset($_POST['iprog_api_key'])) {
            $apiKey = trim($_POST['iprog_api_key']);

            if ($apiKey === '') {
                echo json_encode(['success' => false, 'message' => 'Please enter an iProg API Token before testing.']);
                exit();
            }

            $senderId = trim($_POST['iprog_sender_id'] ?? '');
            $baseUrl = trim($_POST['iprog_base_url'] ?? '') ?: 'https://sms.iprogtech.com/api/v1/sms_messages';
            $senderName = InputSanitizer::sanitizeString($_POST['sms_sender_name'] ?? 'SierraLGU', 11);

            // Persist immediately
            SettingsHelper::set('iprog_api_key', $apiKey);
            SettingsHelper::set('iprog_sender_id', $senderId);
            SettingsHelper::set('iprog_base_url', $baseUrl);
            SettingsHelper::set('sms_sender_name', $senderName);
            SettingsHelper::clearCache();

            $gatewayOverride = [
                'gateway' => 'iprog',
                'api_key' => $apiKey,
                'sender_id' => $senderId,
                'base_url' => $baseUrl,
            ];
        }

        // Use SettingsHelper to send SMS
        $result = SettingsHelper::sendSms($phone, $message, $gatewayOverride);

        $savedNote = $gatewayOverride ? ' Your API key was saved.' : '';

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Test SMS sent successfully!' . $savedNote]);
        } else {
            $gateway = $gatewayOverride ?? SettingsHelper::getActiveSmsGateway();
            if (!$gateway) {
                $status = SettingsHelper::getGatewayStatus();
                $sms_enabled = (int)SettingsHelper::get('enable_sms_notifications');
                echo json_encode([
                    'success' => false,
                    'message' => 'No gateway configured. Check your gateway configuration.' . $savedNote,
                    'diagnostic' => [
                        'sms_enabled' => $sms_enabled,
                        'gateways' => $status
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Your API key was saved, but the test SMS failed. Double-check the API token, sender ID, and endpoint URL are correct for your iProg account.']);
            }
        }
        exit();
    }
}

// ============================================================
// HANDLE THE REQUEST
// ============================================================

// Check if this is a test SMS AJAX request
if (isset($_POST['action']) && $_POST['action'] === 'send_test_sms') {
    $controller = new SettingsController($db, $user_id);
    $controller->sendTestSMS();
    exit();
}

// Persist a single setting via AJAX (used by toggle controls)
if (isset($_POST['action']) && $_POST['action'] === 'save_setting') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
        exit();
    }

    $key = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['key'] ?? ''));
    $value = (isset($_POST['value']) && $_POST['value'] == '1') ? 1 : 0;

    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Invalid key']);
        exit();
    }

    SettingsHelper::set($key, $value);
    SettingsHelper::clearCache();
    echo json_encode(['success' => true, 'message' => 'Saved']);
    exit();
}

// Validate iProg credentials (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'validate_iprog') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
        exit();
    }

    $apiKey = trim($_POST['iprog_api_key'] ?? '');

    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'message' => 'API key is required']);
        exit();
    }

    $testUrl = 'https://sms.iprogtech.com/api/v1/account/sms_credits?' . http_build_query(['api_token' => $apiKey]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $json = json_decode($response, true);

    if ($response !== false && $http_code === 200 && $json && ($json['status'] ?? '') === 'success') {
        $balance = $json['data']['load_balance'] ?? 'unknown';
        echo json_encode(['success' => true, 'message' => 'API key is valid. SMS credit balance: ' . $balance]);
    } else {
        $body = $response !== false ? substr($response, 0, 1000) : '';
        echo json_encode(['success' => false, 'message' => 'Request failed (HTTP ' . $http_code . ')', 'response' => $body]);
    }
    exit();
}

// Get the active tab from URL
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// Handle the settings update
$controller = new SettingsController($db, $user_id);
$controller->update($tab);

// If we get here, something went wrong
$_SESSION['error'] = "Invalid request.";
header("Location: " . BASE_URL . "index.php?page=settings&tab=general");
exit();
?>