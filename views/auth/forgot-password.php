<?php
// views/auth/forgot-password.php - Complete SMS OTP Forgot Password
// Uses iProg SMS gateway to send 6-digit OTP

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/helpers/SecurityHelper.php';
require_once BASE_PATH . 'helpers/SettingsHelper.php';

if (isLoggedIn()) {
    header("Location: " . BASE_URL . "index.php?page=dashboard");
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$csrf_token = InputSanitizer::generateCsrfToken();
$database = new Database();
$db = $database->getConnection();

// Dynamic system settings
$system_name = SettingsHelper::get('system_name', 'Sierra');
$lgu_logo = SettingsHelper::get('lgu_logo', '');
$logo_url = $lgu_logo ? BASE_URL . $lgu_logo : '';

// Load dynamic password rules for the reset step
$pwd_min = (int) SettingsHelper::get('password_min_length', 8);
$pwd_require_upper   = (int) SettingsHelper::get('password_require_upper', 1);
$pwd_require_lower   = (int) SettingsHelper::get('password_require_lower', 1);
$pwd_require_number  = (int) SettingsHelper::get('password_require_number', 1);
$pwd_require_special = (int) SettingsHelper::get('password_require_special', 1);

$reqList = [];
if ($pwd_require_upper)   $reqList[] = 'At least 1 uppercase letter (A-Z)';
if ($pwd_require_lower)   $reqList[] = 'At least 1 lowercase letter (a-z)';
if ($pwd_require_number)  $reqList[] = 'At least 1 number (0-9)';
if ($pwd_require_special) $reqList[] = 'At least 1 special character (!@#$%^&*)';
$reqList[] = "Between $pwd_min and 16 characters";
$reqList[] = 'No spaces allowed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Forgot Password - <?php echo htmlspecialchars($system_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: linear-gradient(135deg, #f0f7f4 0%, #e6f0ec 100%); min-height: 100vh; }
        
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
        
        .step-container { display: none; animation: fadeIn 0.3s ease-out; }
        .step-container.active { display: block; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .input-group { position: relative; margin-bottom: 1rem; }
        .input-field {
            width: 100%;
            padding: 0.75rem 1rem 0.5rem 2.5rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            transition: all 0.2s;
            background: #ffffff;
            color: #1e293b;
            height: 48px;
        }
        .input-field:focus {
            border-color: #10A37F;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.1);
            outline: none;
        }
        .input-field.error { border-color: #ef4444; }
        .input-field.valid { border-color: #10A37F; }
        
        .input-icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.875rem;
            pointer-events: none;
            z-index: 2;
        }
        .floating-label {
            position: absolute;
            left: 2.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.875rem;
            pointer-events: none;
            transition: all 0.2s ease;
            background: transparent;
            padding: 0 0.25rem;
        }
        .input-field:focus ~ .floating-label,
        .input-field:not(:placeholder-shown) ~ .floating-label {
            top: 0;
            transform: translateY(-50%);
            font-size: 0.65rem;
            color: #10A37F;
            background: white;
            padding: 0 0.25rem;
        }
        
        .password-toggle {
            position: absolute;
            right: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 0.875rem;
            z-index: 3;
        }
        .password-toggle:hover { color: #10A37F; }
        
        .otp-container {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            margin: 1.5rem 0;
        }
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            transition: all 0.2s;
            background: white;
            color: #1e293b;
        }
        .otp-input:focus {
            border-color: #10A37F;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.2);
            outline: none;
        }
        .otp-input.filled { border-color: #10A37F; background: #ecfdf5; }
        .otp-input.error { border-color: #ef4444; background: #fef2f2; }
        
        .btn-primary {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16,163,127,0.3); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-secondary {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 0.6rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 500;
            color: #4b5563;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        .btn-secondary:hover { background: #f8fafc; }
        
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e2e8f0;
            transition: all 0.3s ease;
        }
        .step-dot.active { background: #10A37F; transform: scale(1.2); box-shadow: 0 0 0 4px rgba(16,163,127,0.2); }
        .step-dot.completed { background: #10A37F; }
        .step-line {
            width: 40px;
            height: 2px;
            background: #e2e8f0;
            transition: all 0.3s ease;
        }
        .step-line.completed { background: #10A37F; }
        .step-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: #94a3b8;
            text-align: center;
            margin-top: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .step-label.active { color: #10A37F; }
        
        .strength-meter {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 4px;
            overflow: hidden;
        }
        .strength-meter-fill {
            height: 100%;
            border-radius: 2px;
            transition: all 0.3s ease;
            width: 0%;
        }
        .strength-text {
            font-size: 0.6875rem;
            margin-top: 2px;
            font-weight: 500;
        }
        .requirement {
            transition: all 0.2s ease;
            font-size: 0.6875rem;
        }
        .requirement.met { color: #10A37F; }
        .requirement.unmet { color: #94a3b8; }
        
        .flash-message {
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        .flash-message.success { background: #f0fdf4; border-left: 4px solid #10A37F; color: #065f46; }
        .flash-message.error { background: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; }
        .flash-message.info { background: #eff6ff; border-left: 4px solid #3b82f6; color: #1e40af; }
        
        .brand-logo { max-height: 60px; width: auto; object-fit: contain; }
        @media (max-width: 640px) {
            .brand-logo { max-height: 50px; }
            .otp-container { gap: 0.5rem; }
            .otp-input { width: 40px; height: 50px; font-size: 1.2rem; }
            .step-line { width: 20px; }
        }
        .register-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f8;
            border-top: 2px solid #10A37F;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="floating-shape top-[-100px] right-[-100px] w-[300px] h-[300px] opacity-15" style="background: #10A37F;"></div>
    <div class="floating-shape bottom-[-100px] left-[-100px] w-[350px] h-[350px] opacity-10" style="background: #0D8568; animation-delay: -5s;"></div>
    
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-lg">
            <div class="bg-white rounded-2xl shadow-xl p-6 md:p-8" style="background: rgba(255, 255, 255, 0.98); border: 1px solid rgba(0, 0, 0, 0.06);">
                
                <!-- Back Button -->
                <a href="<?php echo BASE_URL; ?>index.php?page=login" class="inline-flex items-center gap-2 text-gray-500 hover:text-[#10A37F] transition-all group mb-4">
                    <i class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform"></i>
                    <span class="text-sm font-medium">Back to Login</span>
                </a>
                
                <!-- Header -->
                <div class="text-center mb-6">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($system_name); ?> Logo" class="brand-logo mx-auto mb-4">
                    <?php else: ?>
                        <div class="w-14 h-14 rounded-xl flex items-center justify-center shadow-lg mb-4 mx-auto" style="background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);">
                            <i class="fas fa-key text-white text-2xl"></i>
                        </div>
                    <?php endif; ?>
                    <h2 class="text-2xl font-bold text-gray-800">Reset Password</h2>
                    <p class="text-gray-500 text-sm mt-1">We'll send a verification code to your mobile number</p>
                </div>
                
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="flash-message success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="flash-message error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="flex flex-col items-center">
                        <div class="flex items-center">
                            <div class="step-dot active" id="dot1"></div>
                            <div class="step-line" id="line1"></div>
                        </div>
                        <span class="step-label active" id="label1">Mobile</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <div class="flex items-center">
                            <div class="step-dot" id="dot2"></div>
                            <div class="step-line" id="line2"></div>
                        </div>
                        <span class="step-label" id="label2">OTP</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <div class="flex items-center">
                            <div class="step-dot" id="dot3"></div>
                        </div>
                        <span class="step-label" id="label3">New Password</span>
                    </div>
                </div>
                
                <!-- ============================================ -->
                <!-- STEP 1: ENTER MOBILE NUMBER -->
                <!-- ============================================ -->
                <div class="step-container active" id="step1">
                    <form id="step1Form" onsubmit="return false;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="input-group">
                            <i class="fas fa-phone input-icon"></i>
                            <input type="tel" name="mobile" id="mobileInput" required class="input-field" placeholder=" " maxlength="11" inputmode="numeric" pattern="09[0-9]{9}">
                            <label for="mobileInput" class="floating-label">Registered Mobile Number <span class="text-red-400">*</span></label>
                            <span class="text-red-500 text-xs mt-1 hidden" id="mobileError"></span>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-[-8px] ml-2 mb-2">Enter 11-digit number starting with 09 (e.g., 09123456789)</p>
                        
                        <button type="button" onclick="requestOTP()" class="btn-primary" id="requestBtn">
                            <span id="requestText">Send Verification Code</span>
                            <span id="requestSpinner" class="hidden"><i class="fas fa-spinner fa-spin mr-2"></i>Sending...</span>
                        </button>
                    </form>
                </div>
                
                <!-- ============================================ -->
                <!-- STEP 2: OTP VERIFICATION -->
                <!-- ============================================ -->
                <div class="step-container" id="step2">
                    <div class="text-center mb-4">
                        <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-shield-alt text-blue-500 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800">Enter Verification Code</h3>
                        <p class="text-gray-500 text-sm mt-1">We sent a 6-digit code to <strong id="otpPhoneDisplay"></strong></p>
                    </div>
                    
                    <div class="otp-container" id="otpContainer">
                        <input type="text" class="otp-input" id="otp1" maxlength="1" inputmode="numeric" pattern="[0-9]" autofocus>
                        <input type="text" class="otp-input" id="otp2" maxlength="1" inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="otp-input" id="otp3" maxlength="1" inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="otp-input" id="otp4" maxlength="1" inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="otp-input" id="otp5" maxlength="1" inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="otp-input" id="otp6" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    </div>
                    
                    <div id="otpError" class="text-red-500 text-sm text-center hidden">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <span>Invalid or expired code. Please try again.</span>
                    </div>
                    <div id="otpSuccess" class="text-green-500 text-sm text-center hidden">
                        <i class="fas fa-check-circle mr-1"></i>
                        <span>Code verified! Setting up password reset...</span>
                    </div>
                    
                    <div class="text-center mt-3">
                        <p class="text-sm text-gray-500">
                            Didn't receive the code?
                            <button type="button" onclick="resendOTP()" class="text-[#10A37F] font-semibold hover:underline" id="resendBtn">Resend</button>
                        </p>
                        <p class="text-xs text-gray-400 mt-1" id="resendTimer"></p>
                    </div>
                    
                    <div class="flex gap-3 mt-4">
                        <button onclick="goToStep(1)" class="btn-secondary">Back</button>
                        <button onclick="verifyOTP()" class="btn-primary" id="verifyBtn">
                            <span id="verifyText">Verify</span>
                            <span id="verifySpinner" class="hidden"><i class="fas fa-spinner fa-spin mr-1"></i> Verifying...</span>
                        </button>
                    </div>
                </div>
                
                <!-- ============================================ -->
                <!-- STEP 3: SET NEW PASSWORD -->
                <!-- ============================================ -->
                <div class="step-container" id="step3">
                    <div class="text-center mb-4">
                        <div class="w-16 h-16 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-lock-open text-green-500 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800">Create New Password</h3>
                        <p class="text-gray-500 text-sm mt-1">Enter your new password below</p>
                    </div>
                    
                    <form id="resetForm" method="POST" action="<?php echo BASE_URL; ?>index.php?page=forgot-password" onsubmit="return false;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="reset_password_with_otp">
                        
                        <div class="input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" id="newPassword" required class="input-field" placeholder=" " autocomplete="new-password" minlength="8" maxlength="16">
                            <label for="newPassword" class="floating-label">New Password <span class="text-red-400">*</span></label>
                            <button type="button" id="togglePassword" class="password-toggle">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                        
                        <div class="strength-meter">
                            <div class="strength-meter-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                        
                        <div class="password-requirements text-[11px] text-gray-400 mt-2 ml-2 mb-3 space-y-0.5">
                            <?php foreach ($reqList as $req): ?>
                                <div class="requirement flex items-center gap-1" id="req_<?php echo md5($req); ?>">
                                    <i class="far fa-circle text-[8px]"></i>
                                    <span><?php echo htmlspecialchars($req); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="input-group">
                            <i class="fas fa-check-circle input-icon"></i>
                            <input type="password" name="confirm_password" id="confirmPassword" required class="input-field" placeholder=" " autocomplete="new-password">
                            <label for="confirmPassword" class="floating-label">Confirm Password <span class="text-red-400">*</span></label>
                        </div>
                        <p id="matchMsg" class="text-[11px] text-gray-400 ml-2 mt-1"></p>
                        
                        <button type="submit" class="btn-primary mt-4" id="resetBtn">
                            <span id="resetText">Reset Password</span>
                            <span id="resetSpinner" class="hidden"><i class="fas fa-spinner fa-spin mr-1"></i> Updating...</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // ============================================
    // STATE VARIABLES
    // ============================================
    let currentStep = 1;
    let mobileNumber = '';
    let resendTimer = null;
    let timerSeconds = 60;
    let otpVerified = false;
    
    const csrfToken = '<?php echo $csrf_token; ?>';
    const passwordSettings = {
        minLength: <?php echo $pwd_min; ?>,
        requireUpper: <?php echo $pwd_require_upper ? 'true' : 'false'; ?>,
        requireLower: <?php echo $pwd_require_lower ? 'true' : 'false'; ?>,
        requireNumber: <?php echo $pwd_require_number ? 'true' : 'false'; ?>,
        requireSpecial: <?php echo $pwd_require_special ? 'true' : 'false'; ?>
    };
    
    // ============================================
    // STEP NAVIGATION
    // ============================================
    function goToStep(step) {
        if (step === 2 && !mobileNumber) return;
        if (step === 3 && !otpVerified) return;
        
        currentStep = step;
        document.querySelectorAll('.step-container').forEach(el => el.classList.remove('active'));
        document.getElementById('step' + step).classList.add('active');
        
        // Update indicators
        for (let i = 1; i <= 3; i++) {
            const dot = document.getElementById('dot' + i);
            const label = document.getElementById('label' + i);
            dot.classList.remove('active', 'completed');
            label.classList.remove('active');
            if (i < step) { dot.classList.add('completed'); }
            else if (i === step) { dot.classList.add('active'); label.classList.add('active'); }
        }
        for (let i = 1; i < step; i++) {
            const line = document.getElementById('line' + i);
            if (line) line.classList.add('completed');
        }
    }
    
    // ============================================
    // STEP 1: REQUEST OTP
    // ============================================
    function requestOTP() {
        const input = document.getElementById('mobileInput');
        const phone = input.value.trim();
        const errorEl = document.getElementById('mobileError');
        
        // Validate phone
        if (!/^09[0-9]{9}$/.test(phone)) {
            errorEl.textContent = 'Please enter a valid 11-digit number starting with 09.';
            errorEl.classList.remove('hidden');
            input.classList.add('error');
            return;
        }
        errorEl.classList.add('hidden');
        input.classList.remove('error');
        
        mobileNumber = phone;
        
        // Show loading
        const btn = document.getElementById('requestBtn');
        btn.disabled = true;
        document.getElementById('requestText').classList.add('hidden');
        document.getElementById('requestSpinner').classList.remove('hidden');
        
        // Send AJAX to request OTP
        const formData = new FormData();
        formData.append('action', 'forgot_password');
        formData.append('mobile', phone);
        formData.append('csrf_token', csrfToken);
        
        fetch('<?php echo BASE_URL; ?>controllers/AuthController.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            document.getElementById('requestText').classList.remove('hidden');
            document.getElementById('requestSpinner').classList.add('hidden');
            
            if (data.success) {
                // Move to step 2
                document.getElementById('otpPhoneDisplay').textContent = formatPhone(phone);
                goToStep(2);
                startResendTimer();
                document.getElementById('otp1').focus();
            } else {
                // Show error (generic)
                alert(data.message || 'Unable to send OTP. Please try again.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            document.getElementById('requestText').classList.remove('hidden');
            document.getElementById('requestSpinner').classList.add('hidden');
            alert('Network error. Please try again.');
        });
    }
    
    function formatPhone(phone) {
        if (phone.length === 11) {
            return '+63 ' + phone.substring(1,4) + ' ' + phone.substring(4,7) + ' ' + phone.substring(7,11);
        }
        return phone;
    }
    
    // ============================================
    // STEP 2: OTP VERIFICATION
    // ============================================
    document.querySelectorAll('.otp-input').forEach((input, index, arr) => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 1) {
                this.classList.add('filled');
                if (index < arr.length - 1) arr[index + 1].focus();
            } else {
                this.classList.remove('filled');
            }
            document.getElementById('otpError').classList.add('hidden');
        });
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value && index > 0) {
                arr[index - 1].focus();
            }
        });
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const digits = paste.replace(/[^0-9]/g, '');
            const otpInputs = document.querySelectorAll('.otp-input');
            for (let i = 0; i < Math.min(digits.length, otpInputs.length); i++) {
                otpInputs[i].value = digits[i];
                otpInputs[i].classList.add('filled');
            }
        });
    });
    
    function verifyOTP() {
        const inputs = document.querySelectorAll('.otp-input');
        let code = '';
        let allFilled = true;
        inputs.forEach(inp => {
            code += inp.value;
            if (!inp.value) allFilled = false;
        });
        if (!allFilled) {
            document.getElementById('otpError').textContent = 'Please enter all 6 digits.';
            document.getElementById('otpError').classList.remove('hidden');
            return;
        }
        
        const btn = document.getElementById('verifyBtn');
        btn.disabled = true;
        document.getElementById('verifyText').classList.add('hidden');
        document.getElementById('verifySpinner').classList.remove('hidden');
        document.getElementById('otpError').classList.add('hidden');
        
        const formData = new FormData();
        formData.append('action', 'verify_forgot_otp');
        formData.append('mobile', mobileNumber);
        formData.append('otp', code);
        formData.append('csrf_token', csrfToken);
        
        fetch('<?php echo BASE_URL; ?>controllers/AuthController.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            document.getElementById('verifyText').classList.remove('hidden');
            document.getElementById('verifySpinner').classList.add('hidden');
            
            if (data.success) {
                otpVerified = true;
                document.getElementById('otpSuccess').classList.remove('hidden');
                document.getElementById('otpSuccess').querySelector('span').textContent = 'Code verified!';
                // Move to step 3 after short delay
                setTimeout(() => {
                    goToStep(3);
                    document.getElementById('newPassword').focus();
                }, 800);
            } else {
                document.getElementById('otpError').textContent = data.message || 'Invalid code. Please try again.';
                document.getElementById('otpError').classList.remove('hidden');
                // Clear OTP inputs
                document.querySelectorAll('.otp-input').forEach(inp => { inp.value = ''; inp.classList.remove('filled'); });
                document.getElementById('otp1').focus();
            }
        })
        .catch(err => {
            btn.disabled = false;
            document.getElementById('verifyText').classList.remove('hidden');
            document.getElementById('verifySpinner').classList.add('hidden');
            alert('Network error. Please try again.');
        });
    }
    
    function resendOTP() {
        const btn = document.getElementById('resendBtn');
        btn.disabled = true;
        btn.textContent = 'Sending...';
        
        const formData = new FormData();
        formData.append('action', 'forgot_password');
        formData.append('mobile', mobileNumber);
        formData.append('csrf_token', csrfToken);
        
        fetch('<?php echo BASE_URL; ?>controllers/AuthController.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            btn.textContent = 'Resend';
            if (data.success) {
                startResendTimer();
                document.getElementById('otpError').classList.add('hidden');
                document.querySelectorAll('.otp-input').forEach(inp => { inp.value = ''; inp.classList.remove('filled'); });
                document.getElementById('otp1').focus();
            } else {
                alert(data.message || 'Failed to resend OTP. Please try again.');
                btn.disabled = false;
            }
        })
        .catch(err => {
            btn.textContent = 'Resend';
            btn.disabled = false;
            alert('Network error. Please try again.');
        });
    }
    
    function startResendTimer() {
        timerSeconds = 60;
        const btn = document.getElementById('resendBtn');
        const timerEl = document.getElementById('resendTimer');
        btn.disabled = true;
        timerEl.textContent = 'Resend available in ' + timerSeconds + 's';
        clearInterval(resendTimer);
        resendTimer = setInterval(() => {
            timerSeconds--;
            timerEl.textContent = 'Resend available in ' + timerSeconds + 's';
            if (timerSeconds <= 0) {
                clearInterval(resendTimer);
                timerEl.textContent = '';
                btn.disabled = false;
            }
        }, 1000);
    }
    
    // ============================================
    // STEP 3: SET NEW PASSWORD
    // ============================================
    // Password strength and requirements (same as register)
    function checkStrength(password) {
        const min = passwordSettings.minLength;
        if (password.length < min || password.length > 16) return 0;
        let score = 0;
        if (password.length >= 8) score += 1;
        if (password.length >= 10) score += 1;
        if (password.length >= 12) score += 1;
        if (password.length >= 14) score += 1;
        if (/[A-Z]/.test(password) && passwordSettings.requireUpper) score += 1;
        if (/[a-z]/.test(password) && passwordSettings.requireLower) score += 1;
        if (/[0-9]/.test(password) && passwordSettings.requireNumber) score += 1;
        if (/[!@#$%^&*()\-_=+{};:,<.>]/.test(password) && passwordSettings.requireSpecial) score += 1;
        if (!/\s/.test(password)) score += 1;
        // Penalize common patterns
        const common = ['password', '123456', 'qwerty', 'abc123', 'letmein'];
        if (common.some(p => password.toLowerCase().includes(p))) score = Math.max(0, score - 2);
        if (/(.)\1{2,}/.test(password)) score = Math.max(0, score - 1);
        return Math.min(10, Math.max(0, score));
    }
    
    function getStrengthLabel(score) {
        if (score <= 1) return { text: 'Very Weak', class: 'very-weak' };
        if (score <= 3) return { text: 'Weak', class: 'weak' };
        if (score <= 5) return { text: 'Fair', class: 'fair' };
        if (score <= 7) return { text: 'Good', class: 'good' };
        return { text: 'Strong!', class: 'strong' };
    }
    
    function updateStrengthMeter(password) {
        const score = checkStrength(password);
        const fill = document.getElementById('strengthFill');
        const text = document.getElementById('strengthText');
        fill.className = 'strength-meter-fill';
        if (password.length === 0) {
            fill.style.width = '0%';
            text.textContent = '';
            return;
        }
        const label = getStrengthLabel(score);
        fill.classList.add('strength-' + label.class);
        text.textContent = label.text;
        text.className = 'strength-text text-' + label.class;
    }
    
    function updateRequirements(password) {
        const min = passwordSettings.minLength;
        const checks = [
            { id: 'reqLen', test: password.length >= min && password.length <= 16, text: 'Between ' + min + ' and 16 characters' },
            { id: 'reqUpper', test: passwordSettings.requireUpper ? /[A-Z]/.test(password) : true, text: 'At least 1 uppercase letter' },
            { id: 'reqLower', test: passwordSettings.requireLower ? /[a-z]/.test(password) : true, text: 'At least 1 lowercase letter' },
            { id: 'reqNumber', test: passwordSettings.requireNumber ? /[0-9]/.test(password) : true, text: 'At least 1 number' },
            { id: 'reqSpecial', test: passwordSettings.requireSpecial ? /[!@#$%^&*()\-_=+{};:,<.>]/.test(password) : true, text: 'At least 1 special character' },
            { id: 'reqNoSpace', test: !/\s/.test(password), text: 'No spaces allowed' }
        ];
        // Update requirement elements
        const container = document.querySelector('.password-requirements');
        const items = container.querySelectorAll('.requirement');
        const texts = [
            'Between ' + min + ' and 16 characters',
            'At least 1 uppercase letter (A-Z)',
            'At least 1 lowercase letter (a-z)',
            'At least 1 number (0-9)',
            'At least 1 special character (!@#$%^&*)',
            'No spaces allowed'
        ];
        items.forEach((el, idx) => {
            const met = checks[idx].test;
            el.className = 'requirement flex items-center gap-1 ' + (password.length === 0 ? 'unmet' : (met ? 'met' : 'unmet'));
            if (password.length === 0) {
                el.innerHTML = '<i class="far fa-circle text-[8px]"></i> <span>' + texts[idx] + '</span>';
            } else if (met) {
                el.innerHTML = '<i class="fas fa-check-circle text-[8px]"></i> <span>' + texts[idx] + '</span>';
            } else {
                el.innerHTML = '<i class="far fa-circle text-[8px]"></i> <span>' + texts[idx] + '</span>';
            }
        });
    }
    
    // Password toggle
    document.getElementById('togglePassword').addEventListener('click', function() {
        const pwd = document.getElementById('newPassword');
        const type = pwd.type === 'password' ? 'text' : 'password';
        pwd.type = type;
        this.querySelector('i').classList.toggle('fa-eye');
        this.querySelector('i').classList.toggle('fa-eye-slash');
    });
    
    // Live password validation
    document.getElementById('newPassword').addEventListener('input', function() {
        const pwd = this.value;
        updateStrengthMeter(pwd);
        updateRequirements(pwd);
        checkMatch();
    });
    
    document.getElementById('confirmPassword').addEventListener('input', checkMatch);
    
    function checkMatch() {
        const pwd = document.getElementById('newPassword').value;
        const confirm = document.getElementById('confirmPassword').value;
        const msg = document.getElementById('matchMsg');
        if (confirm.length === 0) { msg.innerHTML = ''; return; }
        if (pwd === confirm) {
            msg.innerHTML = '<span class="text-[#10A37F]"><i class="fas fa-check-circle mr-0.5"></i>Passwords match</span>';
        } else {
            msg.innerHTML = '<span class="text-red-500"><i class="fas fa-times-circle mr-0.5"></i>Passwords do not match</span>';
        }
    }
    
    // Handle form submission for password reset
    document.getElementById('resetForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const pwd = document.getElementById('newPassword').value;
        const confirm = document.getElementById('confirmPassword').value;
        const errors = [];
        
        // Validate using dynamic rules
        const min = passwordSettings.minLength;
        if (pwd.length < min) errors.push('Password must be at least ' + min + ' characters');
        if (pwd.length > 16) errors.push('Password cannot exceed 16 characters');
        if (passwordSettings.requireUpper && !/[A-Z]/.test(pwd)) errors.push('Password must contain at least 1 uppercase letter');
        if (passwordSettings.requireLower && !/[a-z]/.test(pwd)) errors.push('Password must contain at least 1 lowercase letter');
        if (passwordSettings.requireNumber && !/[0-9]/.test(pwd)) errors.push('Password must contain at least 1 number');
        if (passwordSettings.requireSpecial && !/[!@#$%^&*()\-_=+{};:,<.>]/.test(pwd)) errors.push('Password must contain at least 1 special character');
        if (/\s/.test(pwd)) errors.push('Password cannot contain spaces');
        if (pwd !== confirm) errors.push('Passwords do not match');
        
        if (errors.length > 0) {
            alert(errors.join('\n'));
            return;
        }
        
        // Submit
        const btn = document.getElementById('resetBtn');
        btn.disabled = true;
        document.getElementById('resetText').classList.add('hidden');
        document.getElementById('resetSpinner').classList.remove('hidden');
        
        const formData = new FormData(this);
        formData.append('mobile', mobileNumber);
        formData.append('csrf_token', csrfToken);
        
        fetch('<?php echo BASE_URL; ?>controllers/AuthController.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            document.getElementById('resetText').classList.remove('hidden');
            document.getElementById('resetSpinner').classList.add('hidden');
            
            if (data.success) {
                alert('Password reset successful! You can now login with your new password.');
                window.location.href = '<?php echo BASE_URL; ?>index.php?page=login';
            } else {
                alert(data.error || 'Failed to reset password. Please try again.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            document.getElementById('resetText').classList.remove('hidden');
            document.getElementById('resetSpinner').classList.add('hidden');
            alert('Network error. Please try again.');
        });
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            if (currentStep === 1) requestOTP();
            else if (currentStep === 2) verifyOTP();
            else if (currentStep === 3) document.getElementById('resetForm').dispatchEvent(new Event('submit'));
        }
    });
    </script>
</body>
</html>