<?php
ob_start(); // Prevent "headers already sent" errors

// views/auth/reset_password.php - Step 2: Force Password Reset
// This page is shown to newly created staff accounts (Barangay Officials and MENRO Staff)
// after they successfully log in with their temporary password.
// COMPLETE: Password strength meter, live validation, CSRF protection, redirect to dashboard

require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SecurityHelper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SettingsHelper.php';

// ============================================
// SECURITY CHECKS
// ============================================

// Only allow access if force_password_reset is set in session
if (!isset($_SESSION['force_password_reset']) || $_SESSION['force_password_reset'] !== true) {
    $_SESSION['error'] = "Access denied. Please login first.";
    header("Location: " . BASE_URL . "index.php?page=login");
    exit();
}

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $_SESSION['error'] = "Session expired. Please login again.";
    unset($_SESSION['force_password_reset']);
    header("Location: " . BASE_URL . "index.php?page=login");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['reset_user_email'] ?? $_SESSION['user_email'] ?? '';
$user_role = $_SESSION['user_role'] ?? 'staff';

// Verify the user actually exists and is a staff account
$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Check if user exists and is a staff account
$check_stmt = $db->prepare("SELECT id, role, first_name, last_name, email FROM users WHERE id = ? AND is_active = 1");
$check_stmt->execute([$user_id]);
$user_data = $check_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    $_SESSION['error'] = "User account not found or inactive.";
    unset($_SESSION['force_password_reset']);
    header("Location: " . BASE_URL . "index.php?page=login");
    exit();
}

// Only staff accounts (barangay_official or admin) should go through this flow
if (!in_array($user_data['role'], ['barangay_official', 'admin'])) {
    $_SESSION['error'] = "This account does not require password reset.";
    unset($_SESSION['force_password_reset']);
    header("Location: " . BASE_URL . "index.php?page=dashboard");
    exit();
}

$role_display = ($user_data['role'] === 'barangay_official') ? 'Barangay Official' : 'MENRO Staff';
$full_name = $user_data['first_name'] . ' ' . $user_data['last_name'];

// ============================================
// HANDLE PASSWORD UPDATE (POST via AJAX or direct)
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (
    isset($_POST['update_password']) ||
    (($_POST['action'] ?? '') === 'reset_password')
)) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
            exit();
        }
        $_SESSION['error'] = "Invalid security token. Please refresh and try again.";
        header("Location: " . BASE_URL . "index.php?page=reset-password");
        exit();
    }

    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $errors = [];

    // Validate password strength using existing InputSanitizer method
    $passwordErrors = InputSanitizer::validatePassword($new_password);
    $errors = array_merge($errors, $passwordErrors);

    // Check if passwords match
    if ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        // Update the password
        if ($user->updatePassword($user_id, $new_password)) {
            // Clear the force reset flag
            $user->setForcePasswordReset($user_id, 0);

            // Re-fetch the full user row so the session gets populated
            // exactly like a normal login. Previously only a partial
            // session (id/role/name/email/contact) was set at the
            // "needs reset" step, so fields like barangay_id, is_resident,
            // and profile_picture were left undefined for this first
            // session - breaking things like a barangay official's
            // dashboard until they logged out and back in again.
            $fresh_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $fresh_stmt->execute([$user_id]);
            $fresh_user = $fresh_stmt->fetch(PDO::FETCH_ASSOC);

            // Regenerate the session ID now that the account's
            // permanent password has been set (privilege-relevant change).
            session_regenerate_id(true);

            $_SESSION['user_id'] = $fresh_user['id'];
            $_SESSION['first_name'] = $fresh_user['first_name'];
            $_SESSION['last_name'] = $fresh_user['last_name'];
            $_SESSION['user_name'] = $fresh_user['first_name'] . ' ' . $fresh_user['last_name'];
            $_SESSION['user_role'] = $fresh_user['role'];
            $_SESSION['role_id'] = $fresh_user['role_id'];
            $_SESSION['user_type'] = $fresh_user['user_type'];
            $_SESSION['barangay_id'] = $fresh_user['barangay_id'];
            $_SESSION['user_email'] = $fresh_user['email'];
            $_SESSION['user_contact'] = $fresh_user['contact_number'];
            $_SESSION['is_resident'] = $fresh_user['is_resident'];
            $_SESSION['is_verified'] = $fresh_user['is_verified'];
            $_SESSION['profile_picture'] = $fresh_user['profile_picture'] ?? '';

            unset($_SESSION['force_password_reset']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_user_name']);
            unset($_SESSION['reset_user_email']);

            InputSanitizer::regenerateCsrfToken();

            // Log the password change
            try {
                $activityLog = new ActivityLog($db);
                $activityLog->log($user_id, 'Password Reset', 'User reset password on first login');
            } catch (Exception $e) {
                // ActivityLog might not be available, continue anyway
                error_log("Reset Password: Activity log failed: " . $e->getMessage());
            }

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode([
                    'success' => true,
                    'message' => 'Password updated successfully!',
                    'redirect' => BASE_URL . 'index.php?page=dashboard'
                ]);
                exit();
            }

            $_SESSION['success'] = "Your permanent password has been set successfully! Welcome to Sierra.";
            header("Location: " . BASE_URL . "index.php?page=dashboard");
            exit();
        } else {
            $errors[] = "Failed to update password. Please try again.";
        }
    }

    // If we have errors, return them (AJAX) or store in session
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['error' => implode('. ', $errors)]);
        exit();
    }

    $_SESSION['errors'] = $errors;
    header("Location: " . BASE_URL . "index.php?page=reset-password");
    exit();
}

// ============================================
// GENERATE CSRF TOKEN
// ============================================
$csrf_token = InputSanitizer::generateCsrfToken();

// Get system name for branding
$system_name = 'Sierra';
$logo_url = '';
try {
    $system_name = SettingsHelper::get('system_name', 'Sierra');
    $lgu_logo = SettingsHelper::get('lgu_logo', '');
    $logo_url = $lgu_logo ? BASE_URL . $lgu_logo : '';
} catch (Exception $e) {
    error_log("SettingsHelper error in reset_password: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Set Your Password - <?php echo htmlspecialchars($system_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { 
            background: #F5FBF6;
            min-height: 100vh;
        }
        
        .floating-shape {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            pointer-events: none;
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -30px); }
        }
        
        .input-group {
            margin-bottom: 1.25rem;
        }
        
        .input-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
            font-size: 0.875rem;
        }
        
        .input-group .relative {
            position: relative;
        }
        
        .input-group input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: #ffffff;
            color: #1e293b;
        }
        
        .input-group input:focus {
            border-color: #10A37F;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.1);
        }
        
        .input-group input.error {
            border-color: #EF4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }
        
        .input-group .icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.95rem;
        }
        
        .input-group .toggle-password {
            position: absolute;
            right: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 0.95rem;
            transition: color 0.2s;
        }
        
        .input-group .toggle-password:hover {
            color: #10A37F;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            padding: 0.15rem 0;
            transition: all 0.2s ease;
        }
        
        .requirement.met {
            color: #10A37F;
        }
        
        .requirement.unmet {
            color: #94a3b8;
        }
        
        .requirement i {
            width: 1rem;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            color: white;
            border: none;
            padding: 0.85rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(16, 163, 127, 0.3);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Brand logo */
        .brand-logo {
            max-height: 60px;
            width: auto;
            object-fit: contain;
        }
        @media (max-width: 640px) {
            .brand-logo {
                max-height: 50px;
            }
        }
        
        /* Error/Success flash messages */
        .flash-error {
            background: #FEF2F2;
            border-left: 4px solid #EF4444;
            color: #991B1B;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        
        .flash-success {
            background: #F0FDF4;
            border-left: 4px solid #10A37F;
            color: #065F46;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        
        .flash-info {
            background: #EFF6FF;
            border-left: 4px solid #3B82F6;
            color: #1E40AF;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        
        .strength-meter {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .strength-meter-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease, background 0.3s ease;
            width: 0%;
        }
        
        .strength-text {
            font-size: 0.75rem;
            margin-top: 0.25rem;
            font-weight: 500;
        }
        
        .strength-very-weak { background: #EF4444; width: 20%; }
        .strength-weak { background: #F59E0B; width: 40%; }
        .strength-fair { background: #FCD34D; width: 60%; }
        .strength-good { background: #34D399; width: 80%; }
        .strength-strong { background: #10A37F; width: 100%; }
        
        .text-very-weak { color: #EF4444; }
        .text-weak { color: #F59E0B; }
        .text-fair { color: #D97706; }
        .text-good { color: #10B981; }
        .text-strong { color: #10A37F; }
        
        @media (max-width: 480px) {
            .input-group input {
                font-size: 0.85rem;
                padding: 0.65rem 0.85rem 0.65rem 2.25rem;
            }
            .input-group .icon {
                font-size: 0.8rem;
                left: 0.7rem;
            }
            .requirement {
                font-size: 0.65rem;
            }
        }
    </style>
</head>
<body>
    <!-- Background Shapes -->
    <div class="floating-shape top-[-100px] right-[-100px] w-[300px] h-[300px] opacity-15" style="background: #10A37F;"></div>
    <div class="floating-shape bottom-[-100px] left-[-100px] w-[350px] h-[350px] opacity-10" style="background: #0D8568; animation-delay: -5s;"></div>
    
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-lg">
            <div class="bg-white rounded-2xl shadow-xl p-6 md:p-8" style="background: rgba(255, 255, 255, 0.98); border: 1px solid rgba(0, 0, 0, 0.06);">
                
                <!-- Header -->
                <div class="text-center mb-6">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($system_name); ?> Logo" class="brand-logo mx-auto mb-4">
                    <?php else: ?>
                        <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <i class="fas fa-shield-alt text-white text-2xl"></i>
                        </div>
                    <?php endif; ?>
                    
                    <h2 class="text-2xl font-bold text-gray-800">Set Your Permanent Password</h2>
                    <p class="text-gray-500 text-sm mt-1">Welcome, <strong><?php echo htmlspecialchars($full_name); ?></strong>!</p>
                    <p class="text-gray-500 text-sm">This is your first login. Please create your permanent password.</p>
                    
                    <div class="mt-3 inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-medium">
                        <i class="fas fa-info-circle"></i>
                        <span><?php echo $role_display; ?> Account</span>
                    </div>
                </div>
                
                <!-- Flash Messages -->
                <?php if(isset($_SESSION['errors'])): ?>
                    <div class="flash-error">
                        <?php foreach($_SESSION['errors'] as $err): ?>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                                <span><?php echo htmlspecialchars($err); ?></span>
                            </div>
                        <?php endforeach; unset($_SESSION['errors']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="flash-error">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                            <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['info'])): ?>
                    <div class="flash-info">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-info-circle text-blue-500"></i>
                            <span><?php echo htmlspecialchars($_SESSION['info']); unset($_SESSION['info']); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Reset Password Form -->
                <form method="POST" action="index.php?page=reset-password" id="resetPasswordForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="reset_password">
                    
                    <!-- New Password -->
                    <div class="input-group">
                        <label for="password">New Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <i class="fas fa-lock icon"></i>
                            <input type="password" name="password" id="password" required 
                                   placeholder="Enter new password" 
                                   autocomplete="new-password"
                                   minlength="8" maxlength="16">
                            <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                        
                        <!-- Strength Meter -->
                        <div class="strength-meter">
                            <div class="strength-meter-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                    
                    <!-- Confirm Password -->
                    <div class="input-group">
                        <label for="confirm_password">Confirm Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <i class="fas fa-check-circle icon"></i>
                            <input type="password" name="confirm_password" id="confirm_password" required 
                                   placeholder="Re-enter new password" 
                                   autocomplete="new-password"
                                   minlength="8" maxlength="16">
                            <span id="matchStatus" style="position: absolute; right: 0.875rem; top: 50%; transform: translateY(-50%);"></span>
                        </div>
                        <p id="matchMessage" class="text-xs mt-1"></p>
                    </div>
                    
                    <!-- Password Requirements -->
                    <div class="mt-4 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <p class="text-sm font-semibold text-gray-700 mb-2">Password must contain:</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-0.5">
                            <div class="requirement unmet" id="reqLength">
                                <i class="far fa-circle"></i>
                                <span>Between 8 and 16 characters</span>
                            </div>
                            <div class="requirement unmet" id="reqUpper">
                                <i class="far fa-circle"></i>
                                <span>At least 1 uppercase letter (A-Z)</span>
                            </div>
                            <div class="requirement unmet" id="reqLower">
                                <i class="far fa-circle"></i>
                                <span>At least 1 lowercase letter (a-z)</span>
                            </div>
                            <div class="requirement unmet" id="reqNumber">
                                <i class="far fa-circle"></i>
                                <span>At least 1 number (0-9)</span>
                            </div>
                            <div class="requirement unmet" id="reqSpecial">
                                <i class="far fa-circle"></i>
                                <span>At least 1 special character (!@#$%^&*)</span>
                            </div>
                            <div class="requirement unmet" id="reqNoSpace">
                                <i class="far fa-circle"></i>
                                <span>No spaces allowed</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" name="update_password" class="btn-primary mt-6" id="submitBtn">
                        <i class="fas fa-save mr-2"></i>Set Password &amp; Continue
                    </button>
                </form>
                
                <!-- Security Notice -->
                <div class="text-center mt-4">
                    <p class="text-xs text-gray-400 flex items-center justify-center gap-1">
                        <i class="fas fa-lock text-gray-300"></i>
                        Your password is encrypted and stored securely.
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                        <i class="fas fa-clock text-gray-300"></i>
                        This session is protected and will expire in 30 minutes.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        'use strict';
        
        // ============================================
        // DOM REFERENCES
        // ============================================
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const form = document.getElementById('resetPasswordForm');
        const submitBtn = document.getElementById('submitBtn');
        const toggleBtn = document.getElementById('togglePassword');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        const matchMessage = document.getElementById('matchMessage');
        const matchStatus = document.getElementById('matchStatus');
        
        // ============================================
        // PASSWORD STRENGTH CHECK
        // ============================================
        function checkStrength(password) {
            if (!password || password.length === 0) {
                return { score: 0, label: '', class: '' };
            }
            
            let score = 0;
            
            // Length
            if (password.length >= 8) score += 1;
            if (password.length >= 10) score += 1;
            if (password.length >= 12) score += 1;
            if (password.length >= 14) score += 1;
            
            // Character types
            if (/[A-Z]/.test(password)) score += 1;
            if (/[a-z]/.test(password)) score += 1;
            if (/[0-9]/.test(password)) score += 1;
            if (/[!@#$%^&*()\-_=+{};:,<.>]/.test(password)) score += 1;
            
            // Penalty for common patterns
            const commonPatterns = ['123456', 'password', 'qwerty', 'abcdef', '111111', 'letmein', 'abc123'];
            const lowerPwd = password.toLowerCase();
            if (commonPatterns.some(pattern => lowerPwd.includes(pattern))) {
                score = Math.max(0, score - 2);
            }
            
            // Penalty for repeating characters
            if (/(.)\1{2,}/.test(password)) {
                score = Math.max(0, score - 1);
            }
            
            // Clamp score
            score = Math.max(0, Math.min(10, score));
            
            let label, classname;
            if (score <= 1) { label = 'Very Weak'; classname = 'very-weak'; }
            else if (score <= 3) { label = 'Weak'; classname = 'weak'; }
            else if (score <= 5) { label = 'Fair'; classname = 'fair'; }
            else if (score <= 7) { label = 'Good'; classname = 'good'; }
            else { label = 'Strong!'; classname = 'strong'; }
            
            return { score, label, class: classname };
        }
        
        // ============================================
        // UPDATE PASSWORD REQUIREMENTS
        // ============================================
        function updateRequirements(password) {
            const requirements = [
                { id: 'reqLength', regex: /^.{8,16}$/, text: 'Between 8 and 16 characters' },
                { id: 'reqUpper', regex: /[A-Z]/, text: 'At least 1 uppercase letter (A-Z)' },
                { id: 'reqLower', regex: /[a-z]/, text: 'At least 1 lowercase letter (a-z)' },
                { id: 'reqNumber', regex: /[0-9]/, text: 'At least 1 number (0-9)' },
                { id: 'reqSpecial', regex: /[!@#$%^&*()\-_=+{};:,<.>]/, text: 'At least 1 special character (!@#$%^&*)' },
                { id: 'reqNoSpace', regex: /^\S*$/, text: 'No spaces allowed' }
            ];
            
            let allMet = true;
            
            requirements.forEach(function(req) {
                const el = document.getElementById(req.id);
                if (!el) return;
                
                const met = password.length > 0 ? req.regex.test(password) : false;
                el.className = 'requirement ' + (met ? 'met' : 'unmet');
                
                if (met) {
                    el.innerHTML = '<i class="fas fa-check-circle"></i> <span>' + req.text + '</span>';
                } else {
                    el.innerHTML = '<i class="far fa-circle"></i> <span>' + req.text + '</span>';
                }
                
                if (!met && password.length > 0) {
                    allMet = false;
                }
            });
            
            return allMet;
        }
        
        // ============================================
        // UPDATE STRENGTH METER
        // ============================================
        function updateStrengthMeter(password) {
            const result = checkStrength(password);
            
            // Reset classes
            strengthFill.className = 'strength-meter-fill';
            
            if (password.length === 0) {
                strengthFill.style.width = '0%';
                strengthText.textContent = '';
                strengthText.className = 'strength-text';
                return;
            }
            
            strengthFill.classList.add('strength-' + result.class);
            strengthText.textContent = result.label;
            strengthText.className = 'strength-text text-' + result.class;
        }
        
        // ============================================
        // CHECK PASSWORD MATCH
        // ============================================
        function checkMatch() {
            const pwd = passwordInput.value;
            const confirm = confirmInput.value;
            
            if (confirm.length === 0) {
                matchMessage.textContent = '';
                matchStatus.innerHTML = '';
                return;
            }
            
            if (pwd === confirm) {
                matchMessage.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Passwords match</span>';
                matchStatus.innerHTML = '<i class="fas fa-check-circle text-green-500"></i>';
                confirmInput.classList.remove('error');
            } else {
                matchMessage.innerHTML = '<span class="text-red-500"><i class="fas fa-times-circle mr-1"></i>Passwords do not match</span>';
                matchStatus.innerHTML = '<i class="fas fa-times-circle text-red-500"></i>';
                confirmInput.classList.add('error');
            }
        }
        
        // ============================================
        // VALIDATE FORM
        // ============================================
        function validateForm() {
            const pwd = passwordInput.value;
            const confirm = confirmInput.value;
            
            // Check length
            if (pwd.length < 8 || pwd.length > 16) {
                return false;
            }
            
            // Check all requirements
            const allMet = updateRequirements(pwd);
            
            // Check match
            if (pwd !== confirm || confirm.length === 0) {
                return false;
            }
            
            return allMet;
        }
        
        // ============================================
        // EVENT LISTENERS
        // ============================================
        passwordInput.addEventListener('input', function() {
            const pwd = this.value;
            updateStrengthMeter(pwd);
            updateRequirements(pwd);
            checkMatch();
            submitBtn.disabled = !validateForm();
        });
        
        confirmInput.addEventListener('input', function() {
            checkMatch();
            submitBtn.disabled = !validateForm();
        });
        
        // Password toggle
        toggleBtn.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
            this.setAttribute('aria-label', type === 'password' ? 'Show password' : 'Hide password');
        });
        
        // ============================================
        // FORM SUBMISSION (AJAX + fallback)
        // ============================================
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                // Find the first unmet requirement and scroll to it
                const firstUnmet = document.querySelector('.requirement.unmet');
                if (firstUnmet) {
                    firstUnmet.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstUnmet.style.color = '#EF4444';
                    setTimeout(function() {
                        firstUnmet.style.color = '';
                    }, 2000);
                }
                return false;
            }
            
            // Disable submit button to prevent double submission
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Setting password...';
            
            // Use AJAX if possible, but allow normal POST fallback
            const isAjax = window.fetch && window.FormData;
            if (isAjax) {
                e.preventDefault();

                // Always use a relative URL so the request goes to the same
                // origin the page was served from. Using BASE_URL (hardcoded
                // to http://localhost/…) breaks when the admin opens the app
                // via a LAN IP, 127.0.0.1, or any other hostname.
                const postUrl = 'index.php?page=reset-password';

                const formData = new FormData(form);
                fetch(postUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Server returned HTTP ' + response.status);
                    }
                    return response.text();
                })
                .then(function(text) {
                    var data;
                    try {
                        data = JSON.parse(text);
                    } catch (parseErr) {
                        // Server returned HTML (PHP error page) — fall back
                        console.error('Non-JSON response:', text.substring(0, 500));
                        throw new Error('Server returned an unexpected response. Check PHP error logs.');
                    }
                    if (data.success) {
                        // Use relative redirect so it works on any hostname
                        window.location.href = 'index.php?page=dashboard';
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Set Password &amp; Continue';
                        alert(data.error || 'Failed to update password. Please try again.');
                    }
                })
                .catch(function(err) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Set Password &amp; Continue';
                    alert('Error: ' + err.message + '\n\nIf this keeps happening, try refreshing the page.');
                });
            }
            // If not AJAX, the form will submit normally (fallback)
            return true;
        });
        
        // ============================================
        // INITIAL STATE
        // ============================================
        // Trigger initial validation
        passwordInput.dispatchEvent(new Event('input'));
        
        // Auto-focus the password field
        passwordInput.focus();
        
        // ============================================
        // KEYBOARD SHORTCUTS
        // ============================================
        document.addEventListener('keydown', function(e) {
            // Ctrl+Enter to submit
            if (e.ctrlKey && e.key === 'Enter') {
                form.dispatchEvent(new Event('submit'));
            }
            
            // Escape to go back to login
            if (e.key === 'Escape') {
                if (confirm('Are you sure you want to cancel? You will need to login again.')) {
                    window.location.href = '<?php echo BASE_URL; ?>index.php?page=logout';
                }
            }
        });
        
    })();
    </script>
</body>
</html>