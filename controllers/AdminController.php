<?php
// controllers/AdminController.php - COMPLETE ADMIN CONTROLLER
// Features: User Management (CRUD), Category Management, Role Management,
// Staff Account Creation with Temporary Password + SMS Notification (iProg),
// iProg SMS Gateway Support

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/helpers/SecurityHelper.php';
require_once dirname(__DIR__) . '/helpers/SettingsHelper.php';

// ============================================
// INITIALIZE DATABASE AND MODELS
// ============================================
$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$category = new Category($db);
$activityLog = new ActivityLog($db);

// ============================================
// REQUIRE ADMIN ROLE FOR ALL ADMIN ACTIONS
// ============================================
requireRole('admin');

// ============================================
// HANDLE POST REQUESTS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ============================================================
    // 1. USER MANAGEMENT ACTIONS
    // ============================================================

    // --------------------------------------------
    // 1a. CREATE USER (Staff Account with Temp Password)
    // --------------------------------------------
    if ($action === 'create_user') {
        // CSRF Protection
        if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
            $_SESSION['error'] = "Invalid security token. Please refresh and try again.";
            header("Location: " . BASE_URL . "index.php?page=manage-users");
            exit();
        }

        // Sanitize inputs
        $first_name = InputSanitizer::sanitizeName($_POST['first_name'] ?? '');
        $last_name = InputSanitizer::sanitizeName($_POST['last_name'] ?? '');
        $email = InputSanitizer::sanitizeEmail($_POST['email'] ?? '');
        $contact_number = InputSanitizer::sanitizePhone($_POST['contact_number'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['citizen', 'barangay_official', 'admin']) ? $_POST['role'] : 'citizen';
        $barangay_id = !empty($_POST['barangay_id']) ? (int)$_POST['barangay_id'] : null;
        $job_title = InputSanitizer::sanitizeString($_POST['job_title'] ?? '');

        // Validation
        $errors = [];
        if (empty($first_name)) $errors[] = "First name is required";
        if (empty($last_name)) $errors[] = "Last name is required";
        if (!$email) $errors[] = "Valid email is required";
        if (!$contact_number) $errors[] = "Valid contact number is required";
        if ($role === 'barangay_official' && !$barangay_id) {
            $errors[] = "Please select a barangay for the official";
        }

        // Check if email already exists
        $check = $db->prepare("SELECT id FROM users WHERE email = :email");
        $check->execute([':email' => $email]);
        if ($check->rowCount() > 0) {
            $errors[] = "Email already exists in the system";
        }

        // Check if contact number already exists
        $check = $db->prepare("SELECT id FROM users WHERE contact_number = :contact_number");
        $check->execute([':contact_number' => $contact_number]);
        if ($check->rowCount() > 0) {
            $errors[] = "Contact number already exists in the system";
        }

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $redirect_tab = ($role === 'barangay_official') ? 'barangay' : 'menro';
            header("Location: " . BASE_URL . "index.php?page=manage-users&tab=" . $redirect_tab);
            exit();
        }

        // Generate a temporary password with a stable prefix and random numeric suffix.
        // Format: Sierra2026-123456
        $temp_password = 'Sierra2026-' . random_int(100000, 999999);

        // Create staff account with force_password_reset = 1
        $user_data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'contact_number' => $contact_number,
            'role' => $role,
            'barangay_id' => $barangay_id,
            'job_title' => $job_title
        ];

        $new_user_id = $user->createStaffAccount($user_data, $temp_password);

        if ($new_user_id) {
            $role_display = ($role === 'barangay_official') ? 'Barangay Official' : 'MENRO Staff';

            // ============================================
            // SEND SMS WITH TEMPORARY PASSWORD (via SettingsHelper / iProg)
            // Email notification has been removed - credentials are now
            // delivered via SMS only.
            // ============================================
            $sms_sent = sendWelcomeSMS($contact_number, $first_name, $last_name, $email, $temp_password, $role_display);

            // Log the action
            $activityLog->log($_SESSION['user_id'], 'Create Staff Account', "Created $role account for $first_name $last_name");
            $_SESSION['success'] = $sms_sent
                ? "$role_display account created successfully! An SMS with the temporary password has been sent."
                : "$role_display account created successfully! However, the SMS with the temporary password could not be sent - please check the SMS gateway settings or share the credentials manually.";

        } else {
            $_SESSION['error'] = "Failed to create account. Please try again.";
        }

        $redirect_tab = ($role === 'barangay_official') ? 'barangay' : 'menro';
        header("Location: " . BASE_URL . "index.php?page=manage-users&tab=" . $redirect_tab);
        exit();
    }

    // --------------------------------------------
    // 1b. UPDATE USER ROLE
    // --------------------------------------------
    if ($action === 'update_user_role') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? '';

        if ($user_id > 0 && !empty($role)) {
            if ($user->updateRole($user_id, $role)) {
                $activityLog->log($_SESSION['user_id'], 'Update User Role', "Updated user #$user_id role to $role");
                $_SESSION['success'] = "User role updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update user role";
            }
        } else {
            $_SESSION['error'] = "Invalid user or role";
        }

        header("Location: " . BASE_URL . "index.php?page=manage-users");
        exit();
    }

    // --------------------------------------------
    // 1c. TOGGLE USER STATUS (Activate/Deactivate)
    // --------------------------------------------
    if ($action === 'toggle_user_status') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $is_active = (int)($_POST['is_active'] ?? 0);

        if ($user_id > 0) {
            // Prevent deactivating own account
            if ($user_id == $_SESSION['user_id']) {
                $_SESSION['error'] = "You cannot deactivate your own account.";
                header("Location: " . BASE_URL . "index.php?page=manage-users");
                exit();
            }

            if ($user->updateStatus($user_id, $is_active)) {
                $status_text = $is_active ? 'activated' : 'deactivated';
                $activityLog->log($_SESSION['user_id'], 'Toggle User Status', "$status_text user #$user_id");
                $_SESSION['success'] = "User $status_text successfully!";
            } else {
                $_SESSION['error'] = "Failed to update user status";
            }
        } else {
            $_SESSION['error'] = "Invalid user ID";
        }

        header("Location: " . BASE_URL . "index.php?page=manage-users");
        exit();
    }

    // --------------------------------------------
    // 1d. DELETE USER
    // --------------------------------------------
    if ($action === 'delete_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);

        if ($user_id > 0) {
            // Prevent deleting own account
            if ($user_id == $_SESSION['user_id']) {
                $_SESSION['error'] = "You cannot delete your own account.";
                header("Location: " . BASE_URL . "index.php?page=manage-users");
                exit();
            }

            if ($user->deleteUser($user_id)) {
                $activityLog->log($_SESSION['user_id'], 'Delete User', "Deleted user #$user_id");
                $_SESSION['success'] = "User deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete user";
            }
        } else {
            $_SESSION['error'] = "Invalid user ID";
        }

        header("Location: " . BASE_URL . "index.php?page=manage-users");
        exit();
    }

    // ============================================================
    // 2. CATEGORY MANAGEMENT ACTIONS
    // ============================================================

    // --------------------------------------------
    // 2a. CREATE CATEGORY
    // --------------------------------------------
    if ($action === 'create_category') {
        $name = InputSanitizer::sanitizeString($_POST['name'] ?? '');
        $description = InputSanitizer::sanitizeString($_POST['description'] ?? '');
        $icon_class = InputSanitizer::sanitizeString($_POST['icon_class'] ?? 'fa-tag');
        $base_weight = isset($_POST['base_weight']) ? (int)$_POST['base_weight'] : 1;

        if (empty($name)) {
            $_SESSION['error'] = "Category name is required.";
            header("Location: " . BASE_URL . "index.php?page=manage-categories");
            exit();
        }

        if ($base_weight < 1 || $base_weight > 10) {
            $base_weight = 1;
        }

        if ($category->create($name, $description, $icon_class, $base_weight)) {
            $activityLog->log($_SESSION['user_id'], 'Create Category', "Created category: $name");
            $_SESSION['success'] = "Category created successfully!";
        } else {
            $_SESSION['error'] = "Failed to create category";
        }

        header("Location: " . BASE_URL . "index.php?page=manage-categories");
        exit();
    }

    // --------------------------------------------
    // 2b. UPDATE CATEGORY
    // --------------------------------------------
    if ($action === 'update_category') {
        $id = (int)($_POST['category_id'] ?? 0);
        $name = InputSanitizer::sanitizeString($_POST['name'] ?? '');
        $description = InputSanitizer::sanitizeString($_POST['description'] ?? '');
        $icon_class = InputSanitizer::sanitizeString($_POST['icon_class'] ?? 'fa-tag');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $base_weight = isset($_POST['base_weight']) ? (int)$_POST['base_weight'] : 1;

        if ($id <= 0 || empty($name)) {
            $_SESSION['error'] = "Invalid category data.";
            header("Location: " . BASE_URL . "index.php?page=manage-categories");
            exit();
        }

        if ($base_weight < 1 || $base_weight > 10) {
            $base_weight = 1;
        }

        if ($category->update($id, $name, $description, $icon_class, $is_active, $base_weight)) {
            $activityLog->log($_SESSION['user_id'], 'Update Category', "Updated category: $name");
            $_SESSION['success'] = "Category updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update category";
        }

        header("Location: " . BASE_URL . "index.php?page=manage-categories");
        exit();
    }

    // --------------------------------------------
    // 2c. DELETE CATEGORY
    // --------------------------------------------
    if ($action === 'delete_category') {
        $id = (int)($_POST['category_id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['error'] = "Invalid category ID.";
            header("Location: " . BASE_URL . "index.php?page=manage-categories");
            exit();
        }

        // Check if category is in use
        if ($category->isUsed($id)) {
            $_SESSION['error'] = "Cannot delete category that is already used in reports. Deactivate it instead.";
        } else {
            if ($category->delete($id)) {
                $activityLog->log($_SESSION['user_id'], 'Delete Category', "Deleted category #$id");
                $_SESSION['success'] = "Category deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete category";
            }
        }

        header("Location: " . BASE_URL . "index.php?page=manage-categories");
        exit();
    }

    // --------------------------------------------
    // 2d. TOGGLE CATEGORY STATUS
    // --------------------------------------------
    if ($action === 'toggle_category_status') {
        $id = (int)($_POST['category_id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['error'] = "Invalid category ID.";
        } else {
            if ($category->toggleStatus($id)) {
                $activityLog->log($_SESSION['user_id'], 'Toggle Category Status', "Toggled category #$id status");
                $_SESSION['success'] = "Category status updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update category status";
            }
        }

        header("Location: " . BASE_URL . "index.php?page=manage-categories");
        exit();
    }

    // ============================================================
    // 3. SEND TEST SMS (AJAX)
    // ============================================================
    if ($action === 'send_test_sms') {
        // CSRF Protection
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

        // Use SettingsHelper to send SMS
        $result = SettingsHelper::sendSms($phone, $message);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Test SMS sent successfully!']);
        } else {
            $gateway = SettingsHelper::getActiveSmsGateway();
            $gateway_name = $gateway ? ucfirst($gateway['gateway']) : 'No gateway configured';
            echo json_encode(['success' => false, 'message' => "Failed to send SMS via $gateway_name. Check your gateway configuration."]);
        }
        exit();
    }
}

// ============================================================
// HELPER METHODS
// ============================================================

/**
 * Send welcome SMS with temporary password
 * Uses SettingsHelper to handle all gateways (iProg, Semaphore, Twilio, Chikka)
 */
function sendWelcomeSMS($phone_number, $first_name, $last_name, $email, $temp_password, $role_display) {
    // Check if SMS is enabled
    if (!SettingsHelper::isSmsEnabled()) {
        return false;
    }

    if (empty($phone_number)) {
        return false;
    }

    $system_name = SettingsHelper::get('system_name', 'Sierra');
    $login_url = BASE_URL . 'index.php?page=login';

    // Get template from settings or use default
    $template = SettingsHelper::getTemplate('template_staff_account_created');

    // Replace placeholders
    $replacements = [
        '{first_name}' => $first_name,
        '{last_name}' => $last_name,
        '{full_name}' => $first_name . ' ' . $last_name,
        '{email}' => $email,
        '{temp_password}' => $temp_password,
        '{role}' => $role_display,
        '{login_url}' => $login_url,
        '{system_name}' => $system_name
    ];

    $message = SettingsHelper::parseTemplate($template, $replacements);

    // Truncate message if needed (modern SMS gateways handle long messages automatically)
    // But keep it reasonable - most gateways charge per 160 chars
    if (strlen($message) > 320) {
        $message = substr($message, 0, 317) . '...';
    }

    // Send SMS using SettingsHelper (supports iProg)
    return SettingsHelper::sendSms($phone_number, $message);
}

/**
 * Helper to get user email by ID
 */
function getUserEmail($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['email'] : '';
}

/**
 * Helper to get user name by ID
 */
function getUserName($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['full_name'] : '';
}

// ============================================================
// IF NO VALID ACTION MATCHED
// ============================================================
$_SESSION['error'] = "Invalid action.";
header("Location: " . BASE_URL . "index.php?page=manage-users");
exit();
?>