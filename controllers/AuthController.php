<?php
// controllers/AuthController.php - COMPLETE AUTHENTICATION CONTROLLER
// Features: Login (2-Step for staff), Registration, Logout, Duplicate Check, Session Management

require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SecurityHelper.php';

// ============================================
// INITIALIZE DATABASE CONNECTION
// ============================================
$database = new Database();
$db = $database->getConnection();

// Initialize User Model
$user = new User($db);

// Initialize ActivityLog if class exists
$activityLog = null;
if (class_exists('ActivityLog')) {
    $activityLog = new ActivityLog($db);
}

// ============================================
// ENSURE 'is_verified' AND 'force_password_reset' COLUMNS EXIST
// ============================================
try {
    $checkColumn = $db->query("SHOW COLUMNS FROM users LIKE 'is_verified'");
    if ($checkColumn->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 1");
        error_log("Added missing 'is_verified' column to users table.");
    }

    $checkColumn = $db->query("SHOW COLUMNS FROM users LIKE 'force_password_reset'");
    if ($checkColumn->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN force_password_reset TINYINT(1) DEFAULT 0 COMMENT 'Set to 1 to force password reset on next login (staff accounts)'");
        error_log("Added missing 'force_password_reset' column to users table.");
    }

    $checkColumn = $db->query("SHOW COLUMNS FROM users LIKE 'job_title'");
    if ($checkColumn->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN job_title VARCHAR(100) DEFAULT NULL");
        error_log("Added missing 'job_title' column to users table.");
    }

    $checkColumn = $db->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if ($checkColumn->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
        error_log("Added missing 'profile_picture' column to users table.");
    }
} catch (PDOException $e) {
    error_log("Column check failed: " . $e->getMessage());
}

// ============================================
// HANDLE LOGOUT (GET request)
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_SESSION['user_id']) && $activityLog) {
        $activityLog->log($_SESSION['user_id'], 'Logout', 'User logged out successfully');
    }

    // Clear all session variables
    $_SESSION = array();

    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();

    // Start new session for flash message
    session_start();
    $_SESSION['success'] = "You have been logged out successfully.";

    header("Location: " . BASE_URL . "index.php?page=login");
    exit();
}

// ============================================
// HANDLE POST REQUESTS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ========================================
    // CHECK DUPLICATE EMAIL/NUMBER (AJAX)
    // ========================================
    if ($action === 'check_duplicate') {
        $email = $_POST['email'] ?? '';
        $contact_number = $_POST['contact_number'] ?? '';
        $errors = [];

        if (!empty($email)) {
            $checkEmail = $db->prepare("SELECT id FROM users WHERE email = :email AND is_active = 1");
            $checkEmail->execute([':email' => $email]);
            if ($checkEmail->rowCount() > 0) {
                $errors[] = "This email address is already registered. Please use a different email or login.";
            }
        }

        if (!empty($contact_number)) {
            $checkPhone = $db->prepare("SELECT id FROM users WHERE contact_number = :contact_number AND is_active = 1");
            $checkPhone->execute([':contact_number' => $contact_number]);
            if ($checkPhone->rowCount() > 0) {
                $errors[] = "This mobile number is already registered. Please use a different number or login.";
            }
        }

        if (!empty($errors)) {
            echo json_encode(['error' => implode(' ', $errors)]);
        } else {
            echo json_encode(['success' => true]);
        }
        exit();
    }

    // ========================================
    // REGISTRATION HANDLER
    // ========================================
    if ($action === 'register') {
        // CSRF Protection
        if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
                exit();
            }
            $_SESSION['error'] = "Invalid security token. Please try again.";
            header("Location: " . BASE_URL . "index.php?page=register");
            exit();
        }

        // Sanitize and prepare user data
        $first_name = InputSanitizer::sanitizeName($_POST['first_name'] ?? '');
        $last_name = InputSanitizer::sanitizeName($_POST['last_name'] ?? '');
        $email = InputSanitizer::sanitizeEmail($_POST['email'] ?? '');
        $contact_number = InputSanitizer::sanitizePhone($_POST['contact_number'] ?? '');
        $password = $_POST['password'] ?? '';

        $is_resident = isset($_POST['is_resident']) ? $_POST['is_resident'] : 'yes';
        $is_resident_int = ($is_resident === 'yes') ? 1 : 0;

        $errors = [];

        if (strlen($first_name) < 2) $errors[] = "First name must be at least 2 characters";
        if (strlen($last_name) < 2) $errors[] = "Last name must be at least 2 characters";
        if (!$contact_number) $errors[] = "Please enter a valid 11-digit mobile number starting with 09";
        if (!$email) $errors[] = "Please enter a valid email address";

        $passwordErrors = InputSanitizer::validatePassword($password);
        $errors = array_merge($errors, $passwordErrors);

        if (($password) !== ($_POST['confirm_password'] ?? '')) {
            $errors[] = "Passwords do not match";
        }

        // Validate address based on resident status
        if ($is_resident === 'yes') {
            $barangay_id = filter_var($_POST['barangay_id'] ?? null, FILTER_VALIDATE_INT);
            if (empty($barangay_id) || $barangay_id <= 0) {
                $errors[] = "Please select your barangay";
            }
        } else {
            $province = InputSanitizer::sanitizeString($_POST['province'] ?? '');
            $municipality = InputSanitizer::sanitizeString($_POST['municipality'] ?? '');
            if (empty($province)) $errors[] = "Please select your province";
            if (empty($municipality)) $errors[] = "Please select your municipality";
        }

        if (empty($errors)) {
            $checkEmail = $db->prepare("SELECT id FROM users WHERE email = :email AND is_active = 1");
            $checkEmail->execute([':email' => $email]);
            if ($checkEmail->rowCount() > 0) {
                $errors[] = "This email address is already registered. Please use a different email or login.";
            }

            $checkPhone = $db->prepare("SELECT id FROM users WHERE contact_number = :contact_number AND is_active = 1");
            $checkPhone->execute([':contact_number' => $contact_number]);
            if ($checkPhone->rowCount() > 0) {
                $errors[] = "This mobile number is already registered. Please use a different number or login.";
            }
        }

        if (!empty($errors)) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['error' => implode('. ', $errors)]);
                exit();
            }
            $_SESSION['errors'] = $errors;
            header("Location: " . BASE_URL . "index.php?page=register");
            exit();
        }

        // Prepare fields - convert empty strings to NULL
        $barangay_id = null;
        $purok_street = null;
        $province = null;
        $municipality = null;
        $non_resident_address = null;

        if ($is_resident === 'yes') {
            $barangay_id = filter_var($_POST['barangay_id'] ?? null, FILTER_VALIDATE_INT);
            if ($barangay_id === false || $barangay_id <= 0) $barangay_id = null;
            $purok_street = InputSanitizer::sanitizeString($_POST['purok_street'] ?? '');
            if (empty($purok_street)) $purok_street = null;
        } else {
            $province = InputSanitizer::sanitizeString($_POST['province'] ?? '');
            $municipality = InputSanitizer::sanitizeString($_POST['municipality'] ?? '');
            $non_resident_address = InputSanitizer::sanitizeString($_POST['non_resident_address'] ?? '');
            if (empty($province)) $province = null;
            if (empty($municipality)) $municipality = null;
            if (empty($non_resident_address)) $non_resident_address = null;
        }

        // Email: if empty, set NULL
        if (empty($email)) $email = null;

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO users (
            first_name, last_name, email, contact_number, password_hash,
            role, barangay_id, is_resident, province, municipality,
            non_resident_address, purok_street, is_verified, is_active
        ) VALUES (
            :first_name, :last_name, :email, :contact_number, :password_hash,
            'citizen', :barangay_id, :is_resident, :province, :municipality,
            :non_resident_address, :purok_street, 1, 1
        )";

        $stmt = $db->prepare($query);

        try {
            $result = $stmt->execute([
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':contact_number' => $contact_number,
                ':password_hash' => $hashed_password,
                ':barangay_id' => $barangay_id,
                ':is_resident' => $is_resident_int,
                ':province' => $province,
                ':municipality' => $municipality,
                ':non_resident_address' => $non_resident_address,
                ':purok_street' => $purok_street
            ]);
        } catch (PDOException $e) {
            error_log("Registration PDOException: " . $e->getMessage());
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
                exit();
            }
            $_SESSION['error'] = "Registration failed. Please try again.";
            header("Location: " . BASE_URL . "index.php?page=register");
            exit();
        }

        if ($result) {
            $user_id = $db->lastInsertId();
            if ($activityLog) {
                $activityLog->log($user_id, 'User Registration', "New user registered: $first_name $last_name");
            }

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => true, 'message' => 'Registration successful! You can now login.']);
                exit();
            }

            $_SESSION['success'] = "Registration successful! Please login with your mobile number or email.";
            header("Location: " . BASE_URL . "index.php?page=login");
            exit();
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Registration failed: " . print_r($errorInfo, true));
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['error' => 'Registration failed. Please try again. (SQL error: ' . $errorInfo[2] . ')']);
                exit();
            }
            $_SESSION['error'] = "Registration failed. Please try again.";
            header("Location: " . BASE_URL . "index.php?page=register");
            exit();
        }
    }

    // ========================================
    // LOGIN HANDLER - WITH 2-STEP FOR STAFF
    // ========================================
    elseif ($action === 'login') {
        $loginInput = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;

        // CSRF Protection
        if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
            $_SESSION['error'] = "Invalid security token. Please try again.";
            header("Location: " . BASE_URL . "index.php?page=login");
            exit();
        }

        // Validate input
        if (empty($loginInput) || empty($password)) {
            $_SESSION['error'] = "Please enter your email/mobile and password.";
            header("Location: " . BASE_URL . "index.php?page=login");
            exit();
        }

        // Normalize login input (detect if mobile or email)
        $login = $loginInput;
        $is_mobile = false;

        if (preg_match('/^[0-9]+$/', $login)) {
            $login = preg_replace('/[^0-9]/', '', $login);
            if (strlen($login) === 10) $login = '0' . $login;

            if (!(strlen($login) === 11 && preg_match('/^09/', $login))) {
                $_SESSION['error'] = "Invalid email or password.";
                header("Location: " . BASE_URL . "index.php?page=login");
                exit();
            }
            $is_mobile = true;
        } else {
            $login = filter_var(trim($login), FILTER_SANITIZE_EMAIL);
            if (!filter_var($login, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = "Invalid email or password.";
                header("Location: " . BASE_URL . "index.php?page=login");
                exit();
            }
        }

        // Query user
        if ($is_mobile) {
            $query = "SELECT * FROM users WHERE contact_number = :login AND is_active = 1";
        } else {
            $query = "SELECT * FROM users WHERE email = :login AND is_active = 1";
        }

        $stmt = $db->prepare($query);
        $stmt->bindParam(':login', $login);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify password
            if (password_verify($password, $row['password_hash'])) {

                // ========================================
                // AUTO-VERIFY FOR DEMO ACCOUNTS
                // If password is 'password', auto-verify
                // ========================================
                if ($row['is_verified'] == 0 && $password === 'password') {
                    $verifyStmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                    $verifyStmt->execute([$row['id']]);
                    $row['is_verified'] = 1;
                }

                // ========================================
                // CHECK IF USER NEEDS PASSWORD RESET
                // ========================================
                // This applies to staff accounts (barangay_official and admin)
                // that were created by MENRO with a temporary password.
                // IMPORTANT: this check runs BEFORE the is_verified check.
                // Staff/admin accounts created by an admin are trusted
                // accounts, not self-registrations pending e-mail
                // verification, so a valid temp-password login must be let
                // through to the step-2 force-reset page instead of being
                // blocked by "not yet verified" below.
                if ($user->needsPasswordReset($row['id'])) {
                    // Set basic session data so we know who they are
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_role'] = $row['role'];
                    $_SESSION['user_name'] = $row['first_name'] . ' ' . $row['last_name'];
                    $_SESSION['user_email'] = $row['email'];
                    $_SESSION['user_contact'] = $row['contact_number'];

                    // Set reset flags for the force password reset page
                    $_SESSION['force_password_reset'] = true;
                    $_SESSION['reset_user_id'] = $row['id'];
                    $_SESSION['reset_user_name'] = $row['first_name'] . ' ' . $row['last_name'];
                    $_SESSION['reset_user_email'] = $row['email'];

                    // Log the login attempt
                    if ($activityLog) {
                        $activityLog->log(
                            $row['id'],
                            'Login (First Time)',
                            "First login - redirected to password reset"
                        );
                    }

                    // Regenerate CSRF token
                    InputSanitizer::regenerateCsrfToken();

                    $_SESSION['info'] = "Welcome! This is your first login. Please set your permanent password to continue.";
                    header("Location: " . BASE_URL . "index.php?page=reset-password");
                    exit();
                }

                // Check if user is verified (only self-registered citizens
                // reach this point - staff/admin accounts needing a reset
                // were already redirected above)
                if ($row['is_verified'] == 0) {
                    $_SESSION['error'] = "Your account is not yet verified. Please check your email or contact support.";
                    header("Location: " . BASE_URL . "index.php?page=login");
                    exit();
                }

                // ========================================
                // STANDARD LOGIN (No password reset needed)
                // ========================================
                session_regenerate_id(true);

                $_SESSION['user_id'] = $row['id'];
                $_SESSION['first_name'] = $row['first_name'];
                $_SESSION['last_name'] = $row['last_name'];
                $_SESSION['user_name'] = $row['first_name'] . ' ' . $row['last_name'];
                $_SESSION['user_role'] = $row['role'];
                $_SESSION['barangay_id'] = $row['barangay_id'];
                $_SESSION['user_email'] = $row['email'];
                $_SESSION['user_contact'] = $row['contact_number'];
                $_SESSION['is_resident'] = $row['is_resident'];
                $_SESSION['is_verified'] = $row['is_verified'];
                $_SESSION['profile_picture'] = $row['profile_picture'] ?? '';

                InputSanitizer::regenerateCsrfToken();

                // ========================================
                // REMEMBER ME TOKEN
                // ========================================
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);

                    try {
                        // Check if remember_tokens table exists
                        $stmt = $db->prepare("SHOW TABLES LIKE 'remember_tokens'");
                        $stmt->execute();
                        if ($stmt->rowCount() == 0) {
                            $db->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
                                id INT(11) NOT NULL AUTO_INCREMENT,
                                user_id INT(11) NOT NULL,
                                token_hash VARCHAR(255) NOT NULL,
                                expires_at DATETIME NOT NULL,
                                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                PRIMARY KEY (id),
                                KEY user_id (user_id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
                        }

                        $stmt = $db->prepare(
                            "INSERT INTO remember_tokens (user_id, token_hash, expires_at) 
                             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))"
                        );
                        $stmt->execute([$row['id'], $tokenHash]);

                        setcookie('remember_token', $row['id'] . ':' . $token, [
                            'expires' => time() + (86400 * 30),
                            'path' => '/',
                            'secure' => isset($_SERVER['HTTPS']),
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    } catch (Exception $e) {
                        error_log("Remember token failed: " . $e->getMessage());
                    }
                }

                // ========================================
                // LOG SUCCESSFUL LOGIN
                // ========================================
                $role_display = 'Citizen';
                if ($row['role'] === 'barangay_official') {
                    $role_display = 'Barangay Official';
                } elseif ($row['role'] === 'admin') {
                    $role_display = 'MENRO Administrator';
                }

                if ($activityLog) {
                    $activityLog->log(
                        $row['id'],
                        'Login',
                        "User logged into the system as {$role_display}"
                    );
                }

                $_SESSION['success'] = "Welcome back, " . htmlspecialchars($row['first_name']) . "!";
                header("Location: " . BASE_URL . "index.php?page=dashboard");
                exit();

            } else {
                // Password incorrect
                $_SESSION['error'] = "Invalid email or password.";
                header("Location: " . BASE_URL . "index.php?page=login");
                exit();
            }
        } else {
            // User not found - check if exists but inactive
            if ($is_mobile) {
                $query2 = "SELECT id, is_active FROM users WHERE contact_number = :login";
            } else {
                $query2 = "SELECT id, is_active FROM users WHERE email = :login";
            }
            $stmt2 = $db->prepare($query2);
            $stmt2->bindParam(':login', $login);
            $stmt2->execute();

            if ($stmt2->rowCount() > 0) {
                $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($row2['is_active'] == 0) {
                    $_SESSION['error'] = "Your account has been deactivated. Please contact support.";
                } else {
                    $_SESSION['error'] = "Invalid email or password.";
                }
            } else {
                $_SESSION['error'] = "Invalid email or password.";
            }
            header("Location: " . BASE_URL . "index.php?page=login");
            exit();
        }
    }

    // ========================================
    // HANDLE PASSWORD RESET FROM RESET PAGE
    // ========================================
    elseif ($action === 'reset_password') {
        // This handles the form submission from reset_password.php
        // Note: The actual processing is in reset_password.php view
        // This is a fallback for AJAX or direct POST

        if (!isset($_SESSION['force_password_reset']) || $_SESSION['force_password_reset'] !== true) {
            $_SESSION['error'] = "Access denied. Please login first.";
            header("Location: " . BASE_URL . "index.php?page=login");
            exit();
        }

        $user_id = $_SESSION['user_id'] ?? 0;
        $new_password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $errors = [];
        $passwordErrors = InputSanitizer::validatePassword($new_password);
        $errors = array_merge($errors, $passwordErrors);

        if ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }

        if (empty($errors)) {
            if ($user->updatePassword($user_id, $new_password)) {
                $user->setForcePasswordReset($user_id, 0);

                // Re-fetch the full user row so the session gets populated
                // exactly like a normal login. Previously only a partial
                // session (id/role/name/email/contact) was set at the
                // "needs reset" step, so fields like barangay_id, is_resident,
                // and profile_picture were left undefined for this first
                // session - breaking things like a barangay official's
                // dashboard until they logged out and back in again.
                $freshStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $freshStmt->execute([$user_id]);
                $freshUser = $freshStmt->fetch(PDO::FETCH_ASSOC);

                // Regenerate the session ID now that the account's
                // permanent password has been set (privilege-relevant change).
                session_regenerate_id(true);

                $_SESSION['user_id'] = $freshUser['id'];
                $_SESSION['first_name'] = $freshUser['first_name'];
                $_SESSION['last_name'] = $freshUser['last_name'];
                $_SESSION['user_name'] = $freshUser['first_name'] . ' ' . $freshUser['last_name'];
                $_SESSION['user_role'] = $freshUser['role'];
                $_SESSION['barangay_id'] = $freshUser['barangay_id'];
                $_SESSION['user_email'] = $freshUser['email'];
                $_SESSION['user_contact'] = $freshUser['contact_number'];
                $_SESSION['is_resident'] = $freshUser['is_resident'];
                $_SESSION['is_verified'] = $freshUser['is_verified'];
                $_SESSION['profile_picture'] = $freshUser['profile_picture'] ?? '';

                unset($_SESSION['force_password_reset']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_user_name']);
                unset($_SESSION['reset_user_email']);

                InputSanitizer::regenerateCsrfToken();

                if ($activityLog) {
                    $activityLog->log($user_id, 'Password Reset', 'User reset password on first login');
                }

                $_SESSION['success'] = "Password updated successfully! Welcome, " . htmlspecialchars($freshUser['first_name']) . ".";
                echo json_encode(['success' => true, 'message' => 'Password updated successfully!', 'redirect' => BASE_URL . 'index.php?page=dashboard']);
                exit();
            } else {
                echo json_encode(['error' => 'Failed to update password.']);
                exit();
            }
        } else {
            echo json_encode(['error' => implode('. ', $errors)]);
            exit();
        }
    }

    // ========================================
    // FORGOT PASSWORD - Step 1: Request Reset
    // ========================================
    elseif ($action === 'forgot_password') {
        $email = InputSanitizer::sanitizeEmail($_POST['email'] ?? '');

        if (!$email) {
            $_SESSION['error'] = "Please enter a valid email address.";
            header("Location: " . BASE_URL . "index.php?page=forgot-password");
            exit();
        }

        // Check if user exists
        $check = $db->prepare("SELECT id, first_name, last_name FROM users WHERE email = ? AND is_active = 1");
        $check->execute([$email]);

        if ($check->rowCount() > 0) {
            $user_data = $check->fetch(PDO::FETCH_ASSOC);

            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            // Save token to database
            $stmt = $db->prepare("
                INSERT INTO password_resets (user_id, token_hash, expires_at) 
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
                ON DUPLICATE KEY UPDATE token_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$user_data['id'], $tokenHash, $tokenHash]);

            // Send reset email
            $reset_link = BASE_URL . "index.php?page=reset-password&token=" . $token;
            $subject = "Password Reset Request - " . SettingsHelper::get('system_name', 'Sierra');

            $message = "
            <html>
            <head>
                <style>
                    body { font-family: 'Manrope', Arial, sans-serif; color: #1a2e1a; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #10A37F; color: white; padding: 20px; text-align: center; border-radius: 12px 12px 0 0; }
                    .content { background: #f9fbfa; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 12px 12px; }
                    .btn { display: inline-block; background: #10A37F; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; }
                    .footer { text-align: center; color: #6b7280; font-size: 12px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2 style='margin:0;'>" . SettingsHelper::get('system_name', 'Sierra') . "</h2>
                        <p style='margin:5px 0 0; opacity:0.9;'>Password Reset Request</p>
                    </div>
                    <div class='content'>
                        <h3 style='margin-top:0;'>Hello " . htmlspecialchars($user_data['first_name']) . ",</h3>
                        <p>We received a request to reset your password.</p>
                        <p>Click the button below to set a new password:</p>
                        <div style='text-align:center; margin:25px 0;'>
                            <a href='$reset_link' class='btn'>Reset Password</a>
                        </div>
                        <p style='font-size:13px; color:#6b7280;'>This link will expire in 1 hour.</p>
                        <p style='font-size:13px; color:#6b7280;'>If you didn't request this, please ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>© " . date('Y') . " " . SettingsHelper::get('system_name', 'Sierra') . " - LGU San Isidro</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: " . SettingsHelper::get('system_name', 'Sierra') . " System <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";

            @mail($email, $subject, $message, $headers);

            $_SESSION['success'] = "Password reset link sent to your email!";
        } else {
            // Don't reveal if email exists or not for security
            $_SESSION['success'] = "If an account exists with this email, a password reset link has been sent.";
        }

        header("Location: " . BASE_URL . "index.php?page=login");
        exit();
    }

    // ========================================
    // FORGOT PASSWORD - Step 2: Reset with Token
    // ========================================
    elseif ($action === 'reset_password_with_token') {
        $token = $_POST['token'] ?? '';
        $new_password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($token)) {
            $_SESSION['error'] = "Invalid reset token.";
            header("Location: " . BASE_URL . "index.php?page=login");
            exit();
        }

        $tokenHash = hash('sha256', $token);

        // Validate token
        $stmt = $db->prepare("
            SELECT user_id FROM password_resets 
            WHERE token_hash = ? AND expires_at > NOW() AND used = 0
        ");
        $stmt->execute([$tokenHash]);

        if ($stmt->rowCount() == 0) {
            $_SESSION['error'] = "Invalid or expired reset token. Please request a new one.";
            header("Location: " . BASE_URL . "index.php?page=forgot-password");
            exit();
        }

        $user_id = $stmt->fetch(PDO::FETCH_ASSOC)['user_id'];

        $errors = [];
        $passwordErrors = InputSanitizer::validatePassword($new_password);
        $errors = array_merge($errors, $passwordErrors);

        if ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }

        if (empty($errors)) {
            if ($user->updatePassword($user_id, $new_password)) {
                // Mark token as used
                $db->prepare("UPDATE password_resets SET used = 1 WHERE token_hash = ?")->execute([$tokenHash]);

                if ($activityLog) {
                    $activityLog->log($user_id, 'Password Reset', 'User reset password via forgot password link');
                }

                $_SESSION['success'] = "Password has been reset successfully! Please login with your new password.";
                header("Location: " . BASE_URL . "index.php?page=login");
                exit();
            } else {
                $_SESSION['error'] = "Failed to reset password. Please try again.";
                header("Location: " . BASE_URL . "index.php?page=forgot-password");
                exit();
            }
        } else {
            $_SESSION['errors'] = $errors;
            header("Location: " . BASE_URL . "index.php?page=reset-password&token=" . $token);
            exit();
        }
    }
}

// ============================================
// IF NO VALID ACTION MATCHED
// ============================================
// If we get here via POST with no valid action, redirect to login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['error'] = "Invalid request.";
    header("Location: " . BASE_URL . "index.php?page=login");
    exit();
}

// If GET request with no action, redirect to home
header("Location: " . BASE_URL . "index.php");
exit();
?>