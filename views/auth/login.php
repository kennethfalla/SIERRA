<?php
// views/auth/login.php - Complete Login Page
// UPDATED: Works with AuthController (login → dashboard)
// Displays success messages from registration, logout, etc.
// Dynamic system name and logo from SettingsHelper

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

// Generate CSRF token
$csrf_token = InputSanitizer::generateCsrfToken();
$csrf_expiry = time() + 1800;

$database = new Database();
$db = $database->getConnection();

// Load dynamic settings
$system_name = SettingsHelper::get('system_name', 'Sierra');
$lgu_logo = SettingsHelper::get('lgu_logo', '');
$logo_url = $lgu_logo ? BASE_URL . $lgu_logo : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - <?php echo htmlspecialchars($system_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        
        .input-group {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .input-field {
            width: 100%;
            padding: 0.75rem 1rem 0.5rem 2.5rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: #ffffff;
            color: #1e293b;
            height: 48px;
        }
        
        .input-field:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
            outline: none;
        }
        
        .input-field.error {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }
        
        .input-icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.875rem;
            pointer-events: none;
            z-index: 2;
            transition: all 0.2s ease;
        }
        
        .input-field:focus ~ .input-icon {
            color: #059669;
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
            color: #059669;
            background: white;
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
            transition: color 0.2s ease;
        }
        
        .password-toggle:hover {
            color: #059669;
        }
        
        .field-error {
            display: none;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: #dc2626;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0.75rem;
            background: #fef2f2;
            border-radius: 0.5rem;
            border: 1px solid #fecaca;
            animation: slideDown 0.25s ease;
        }
        
        .field-error.show {
            display: flex;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        .demo-card {
            transition: all 0.2s ease;
        }
        
        .demo-card:hover {
            transform: translateY(-1px);
        }
        
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }
        
        @media (max-width: 480px) {
            .demo-accounts-grid {
                grid-template-columns: 1fr !important;
            }
        }
        
        /* Brand logo */
        .brand-logo {
            max-height: 60px;
            width: auto;
            object-fit: contain;
        }
        .brand-logo-sm {
            max-height: 40px;
            width: auto;
            object-fit: contain;
        }
        @media (max-width: 640px) {
            .brand-logo {
                max-height: 50px;
            }
        }
        
        /* Flash message animation */
        .flash-message {
            animation: slideDown 0.3s ease;
        }
        .flash-message.success {
            background: #f0fdf4;
            border-left: 4px solid #059669;
            color: #065f46;
        }
        .flash-message.error {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            color: #991b1b;
        }
        .flash-message.info {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }
    </style>
</head>
<body class="relative min-h-screen" style="background: linear-gradient(135deg, #f0f7f4 0%, #e6f0ec 100%);">
    <div class="floating-shape top-[-100px] right-[-100px] w-[300px] h-[300px] opacity-15" style="background: #059669;"></div>
    <div class="floating-shape bottom-[-100px] left-[-100px] w-[350px] h-[350px] opacity-10" style="background: #047857; animation-delay: -5s;"></div>
    
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-6xl mx-auto">
            <div class="flex flex-col lg:flex-row gap-6">
                
                <!-- Left: Brand Section -->
                <div class="flex-1 flex items-center justify-center px-4 py-6">
                    <div class="max-w-md">
                        <a href="<?php echo BASE_URL; ?>index.php" class="inline-flex items-center gap-2 text-gray-500 hover:text-[#059669] transition-all mb-6 group">
                            <i class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform"></i>
                            <span class="text-sm font-medium">Back to Home</span>
                        </a>
                        
                        <?php if ($logo_url): ?>
                            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($system_name); ?> Logo" class="brand-logo mb-6">
                        <?php else: ?>
                            <div class="w-14 h-14 rounded-xl flex items-center justify-center shadow-lg mb-6" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
                                <i class="fas fa-leaf text-white text-2xl"></i>
                            </div>
                        <?php endif; ?>
                        
                        <h1 class="text-4xl font-bold text-gray-800 tracking-tight leading-tight"><?php echo htmlspecialchars($system_name); ?></h1>
                        <p class="text-gray-500 text-sm mt-3 leading-relaxed">San Isidro Environmental Hub — Report concerns, track resolutions, and contribute to a cleaner, greener community.</p>
                        
                        <div class="mt-4 flex flex-wrap gap-2">
                            <div class="flex items-center gap-1.5 px-2.5 py-1 bg-white/60 rounded-full">
                                <i class="fas fa-shield-alt text-[#059669] text-xs"></i>
                                <span class="text-xs text-gray-600">Secure Login</span>
                            </div>
                            <div class="flex items-center gap-1.5 px-2.5 py-1 bg-white/60 rounded-full">
                                <i class="fas fa-map-marker-alt text-[#059669] text-xs"></i>
                                <span class="text-xs text-gray-600">San Isidro, Nueva Ecija</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right: Login Form -->
                <div class="flex-1 flex justify-center">
                    <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl" style="background: rgba(255, 255, 255, 0.98); border: 1px solid rgba(0, 0, 0, 0.06);">
                        
                        <div class="text-center mb-6 mt-14">
                            <h2 class="text-2xl font-bold text-gray-800">Welcome back</h2>
                            <p class="text-gray-500 text-xs mt-1">Sign in with email or mobile number</p>
                        </div>
                        
                        <!-- ============================================ -->
                        <!-- FLASH MESSAGES -->
                        <!-- ============================================ -->
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="flash-message success rounded-lg p-3 text-sm mb-4" role="alert">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-check-circle text-green-600" aria-hidden="true"></i>
                                    <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="flash-message error rounded-lg p-3 text-sm mb-4" role="alert">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-exclamation-circle text-red-600" aria-hidden="true"></i>
                                    <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['info'])): ?>
                            <div class="flash-message info rounded-lg p-3 text-sm mb-4" role="alert">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-info-circle text-blue-600" aria-hidden="true"></i>
                                    <span><?php echo htmlspecialchars($_SESSION['info']); unset($_SESSION['info']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- ============================================ -->
                        <!-- LOGIN FORM -->
                        <!-- ============================================ -->
                        <form action="/environmental-reporting-app/controllers/AuthController.php" method="POST" id="loginForm" novalidate>
                            <input type="hidden" name="action" value="login">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="csrf_expiry" value="<?php echo $csrf_expiry; ?>" id="csrfExpiry">
                        
                            <div class="field-error" id="fieldError" role="alert" aria-live="assertive">
                                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                                <span id="fieldErrorMessage">Invalid email or password.</span>
                            </div>
                            
                            <div class="input-group">
                                <i class="fas fa-envelope input-icon" id="loginIcon" aria-hidden="true"></i>
                                <input type="text" name="login" id="login" required class="input-field" placeholder=" " autocomplete="username" aria-label="Email or mobile number" aria-describedby="fieldError">
                                <label for="login" class="floating-label">Email or Mobile Number</label>
                            </div>
            
                            <div class="input-group mt-4">
                                <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                                <input type="password" name="password" id="password" required class="input-field" placeholder=" " autocomplete="current-password" aria-label="Password">
                                <label for="password" class="floating-label">Password</label>
                                <button type="button" id="togglePassword" class="password-toggle" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye-slash" aria-hidden="true"></i>
                                </button>
                            </div>
                            
                            <div class="flex items-center justify-between mb-5 mt-4">
                                <label class="flex items-center gap-1.5 cursor-pointer group">
                                    <input type="checkbox" name="remember" class="w-3.5 h-3.5 rounded border-gray-300 text-[#059669] focus:ring-[#059669]">
                                    <span class="text-xs text-gray-500 group-hover:text-gray-700">Remember me</span>
                                </label>
                                <a href="<?php echo BASE_URL; ?>index.php?page=forgot-password" class="text-xs text-[#059669] hover:text-[#047857]">Forgot password?</a>
                            </div>
                            
                            <button type="submit" id="submitBtn" class="w-full text-white font-semibold py-3 rounded-xl transition-all hover:scale-[0.98] hover:shadow-lg text-sm" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);" aria-label="Sign in button">
                                <span id="submitText">Sign in →</span>
                                <span id="submitSpinner" class="hidden" role="status">
                                    <svg class="animate-spin h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Signing in...
                                </span>
                            </button>
                        </form>
                        
                        <!-- ============================================ -->
                        <!-- REGISTER LINK -->
                        <!-- ============================================ -->
                        <div class="relative flex items-center justify-center my-5">
                            <div class="flex-1 h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent"></div>
                            <div class="flex items-center gap-2 px-3 text-gray-400 text-[10px]">
                                <i class="fas fa-leaf text-[9px]" aria-hidden="true"></i>
                                <span>New here?</span>
                                <i class="fas fa-leaf text-[9px]" aria-hidden="true"></i>
                            </div>
                            <div class="flex-1 h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent"></div>
                        </div>
                        
                        <div class="text-center">
                            <a href="<?php echo BASE_URL; ?>index.php?page=register" class="inline-flex items-center gap-2 text-[#059669] font-semibold text-sm hover:gap-3 transition-all">
                                <span>Create an account</span>
                                <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i>
                            </a>
                        </div>
                        
                        <!-- ============================================ -->
                        <!-- DEMO ACCOUNTS -->
                        <!-- ============================================ -->
                        <div class="mt-5 pt-3 border-t border-gray-100">
                            <button type="button" class="flex items-center justify-between w-full cursor-pointer py-1 group" onclick="toggleDemoAccounts()" aria-expanded="false" aria-controls="demoContent">
                                <div class="flex items-center gap-1.5">
                                    <i class="fas fa-flask text-gray-400 text-[10px] group-hover:text-[#059669] transition-colors" aria-hidden="true"></i>
                                    <span class="text-[10px] font-medium text-gray-400 uppercase tracking-wider group-hover:text-[#059669] transition-colors">Demo Access</span>
                                </div>
                                <i id="demoChevron" class="fas fa-chevron-down text-gray-400 text-[10px] transition-transform duration-300" aria-hidden="true"></i>
                            </button>
                            
                            <div id="demoContent" class="mt-3 space-y-3" style="display: none;" role="region" aria-label="Demo accounts">
                                
                                <!-- MENRO Role -->
                                <div>
                                    <div class="flex items-center gap-1.5 mb-2 px-0.5">
                                        <div class="w-5 h-5 bg-purple-100 rounded-lg flex items-center justify-center" aria-hidden="true">
                                            <i class="fas fa-building text-purple-600 text-[9px]"></i>
                                        </div>
                                        <p class="text-[10px] font-semibold text-purple-600 uppercase tracking-wide">MENRO Administrator</p>
                                    </div>
                                    <div class="relative border border-gray-100 rounded-lg bg-white hover:border-[#059669] hover:bg-emerald-50 transition-all cursor-pointer p-2 demo-card" onclick="fillCredentials('menro@envreport.com', 'password')" tabindex="0" role="button" aria-label="Login as MENRO Administrator">
                                        <span class="absolute -top-2 right-2 text-[8px] font-semibold px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-600">MENRO</span>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 bg-purple-100 rounded-lg flex items-center justify-center" aria-hidden="true">
                                                    <i class="fas fa-leaf text-purple-600 text-xs"></i>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-medium text-gray-800">menro@envreport.com</p>
                                                    <p class="text-[9px] text-gray-400">Mobile: 09170000001</p>
                                                </div>
                                            </div>
                                            <i class="fas fa-chevron-right text-gray-300 text-[9px]" aria-hidden="true"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Barangay Officials -->
                                <div>
                                    <div class="flex items-center gap-1.5 mb-2 px-0.5">
                                        <div class="w-5 h-5 bg-blue-100 rounded-lg flex items-center justify-center" aria-hidden="true">
                                            <i class="fas fa-map-marker-alt text-blue-600 text-[9px]"></i>
                                        </div>
                                        <p class="text-[10px] font-semibold text-blue-600 uppercase tracking-wide">Barangay Officials (9)</p>
                                    </div>
                                    <div class="demo-accounts-grid grid grid-cols-3 gap-1.5 max-h-48 overflow-y-auto p-0.5" role="list">
                                        <?php
                                        $barangay_emails = [
                                            'Alua' => 'alua@barangay.gov',
                                            'Calaba' => 'calaba@barangay.gov',
                                            'Malapit' => 'malapit@barangay.gov',
                                            'Mangga' => 'mangga@barangay.gov',
                                            'Poblacion' => 'poblacion@barangay.gov',
                                            'Pulo' => 'pulo@barangay.gov',
                                            'San Roque' => 'sanroque@barangay.gov',
                                            'Santo Cristo' => 'santocristo@barangay.gov',
                                            'Tabon' => 'tabon@barangay.gov'
                                        ];
                                        foreach($barangay_emails as $name => $email):
                                        ?>
                                        <div class="relative border border-gray-100 rounded-lg bg-white hover:border-[#059669] hover:bg-emerald-50 transition-all cursor-pointer p-1.5 demo-card" onclick="fillCredentials('<?php echo $email; ?>', 'password')" tabindex="0" role="listitem" aria-label="Login as Barangay <?php echo $name; ?> official">
                                            <span class="absolute -top-1.5 right-1.5 text-[7px] font-semibold px-1 py-0.5 rounded-full bg-blue-100 text-blue-600"><?php echo $name; ?></span>
                                            <div class="flex items-center gap-1">
                                                <i class="fas fa-envelope text-blue-400 text-[8px]" aria-hidden="true"></i>
                                                <span class="text-[9px] font-medium truncate"><?php echo $email; ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Citizens -->
                                <div>
                                    <div class="flex items-center gap-1.5 mb-2 px-0.5">
                                        <div class="w-5 h-5 bg-emerald-100 rounded-lg flex items-center justify-center" aria-hidden="true">
                                            <i class="fas fa-users text-emerald-600 text-[9px]"></i>
                                        </div>
                                        <p class="text-[10px] font-semibold text-emerald-600 uppercase tracking-wide">Citizens (9)</p>
                                    </div>
                                    <div class="demo-accounts-grid grid grid-cols-2 gap-1.5 max-h-48 overflow-y-auto p-0.5" role="list">
                                        <?php
                                        $citizens = [
                                            ['Alua', 'Ana Cruz', 'ana.cruz@email.com'],
                                            ['Calaba', 'Benigno Ramos', 'benigno.ramos@email.com'],
                                            ['Malapit', 'Corazon Aquino', 'corazon.aquino@email.com'],
                                            ['Mangga', 'Diosdado Macapagal', 'diosdado.macapagal@email.com'],
                                            ['Poblacion', 'Ferdinand Marcos', 'ferdinand.marcos@email.com'],
                                            ['Pulo', 'Gloria Macapagal', 'gloria.macapagal@email.com'],
                                            ['San Roque', 'Joseph Estrada', 'joseph.estrada@email.com'],
                                            ['Santo Cristo', 'Rodrigo Duterte', 'rodrigo.duterte@email.com'],
                                            ['Tabon', 'Leni Robredo', 'leni.robredo@email.com']
                                        ];
                                        foreach($citizens as $c):
                                        ?>
                                        <div class="relative border border-gray-100 rounded-lg bg-white hover:border-[#059669] hover:bg-emerald-50 transition-all cursor-pointer p-1.5 demo-card" onclick="fillCredentials('<?php echo $c[2]; ?>', 'password')" tabindex="0" role="listitem" aria-label="Login as <?php echo $c[1]; ?> from <?php echo $c[0]; ?>">
                                            <span class="absolute -top-1.5 right-1.5 text-[7px] font-semibold px-1 py-0.5 rounded-full bg-emerald-100 text-emerald-600"><?php echo $c[0]; ?></span>
                                            <div class="flex items-center gap-1">
                                                <i class="fas fa-user text-emerald-500 text-[8px]" aria-hidden="true"></i>
                                                <span class="text-[9px] font-medium truncate"><?php echo $c[1]; ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <p class="text-center text-[9px] text-gray-400 pt-1">Password for all accounts: <span class="font-mono font-medium text-gray-600">password</span></p>
                                <p class="text-center text-[8px] text-gray-400">Click any card to auto-fill credentials</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // ============================================
    // DOM ELEMENTS
    // ============================================
    const loginForm = document.getElementById('loginForm');
    const loginInput = document.getElementById('login');
    const passwordInput = document.getElementById('password');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const submitSpinner = document.getElementById('submitSpinner');
    const fieldError = document.getElementById('fieldError');
    const fieldErrorMessage = document.getElementById('fieldErrorMessage');
    const loginIcon = document.getElementById('loginIcon');
    const toggleBtn = document.getElementById('togglePassword');
    
    // ============================================
    // FIELD ERROR HANDLING
    // ============================================
    function showFieldError(message) {
        fieldErrorMessage.textContent = message || 'Invalid email or password.';
        fieldError.classList.add('show');
        loginInput.classList.add('error');
        passwordInput.classList.add('error');
    }
    
    function hideFieldError() {
        fieldError.classList.remove('show');
        loginInput.classList.remove('error');
        passwordInput.classList.remove('error');
    }
    
    // ============================================
    // LOGIN ICON TOGGLE (email vs mobile)
    // ============================================
    function updateLoginIcon(value) {
        const cleaned = value.replace(/\s/g, '');
        if (/^09[0-9]{9}$/.test(cleaned) || /^[0-9]+$/.test(cleaned)) {
            loginIcon.className = 'fas fa-mobile-alt input-icon';
        } else {
            loginIcon.className = 'fas fa-envelope input-icon';
        }
    }
    
    // ============================================
    // FORM SUBMISSION
    // ============================================
    loginForm.addEventListener('submit', function(e) {
        const loginValue = loginInput.value.trim();
        const passwordValue = passwordInput.value.trim();
        
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;
        if (!csrfToken) {
            e.preventDefault();
            alert('Your session has expired. Please refresh the page and try again.');
            return false;
        }
        
        if (!loginValue || !passwordValue) {
            e.preventDefault();
            showFieldError('Please enter your email and password.');
            return false;
        }
        
        hideFieldError();
        submitBtn.disabled = true;
        submitText.classList.add('hidden');
        submitSpinner.classList.remove('hidden');
        
        return true;
    });
    
    // ============================================
    // LIVE INPUT HANDLING
    // ============================================
    loginInput.addEventListener('input', function(e) {
        let value = e.target.value;
        // If it looks like a phone number, allow only digits
        if (/^[0-9]+$/.test(value.replace(/\s/g, ''))) {
            let digits = value.replace(/[^0-9]/g, '');
            if (digits.length > 11) digits = digits.substring(0, 11);
            e.target.value = digits;
        }
        updateLoginIcon(e.target.value);
        hideFieldError();
    });
    
    passwordInput.addEventListener('input', function() {
        hideFieldError();
    });
    
    // ============================================
    // PASSWORD TOGGLE
    // ============================================
    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            const icon = toggleBtn.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
            toggleBtn.setAttribute('aria-label', 
                type === 'password' ? 'Show password' : 'Hide password'
            );
        });
    }
    
    // ============================================
    // FLOATING LABEL FIX
    // ============================================
    document.querySelectorAll('.input-field').forEach(input => {
        if (input.value) input.dispatchEvent(new Event('input'));
    });
    
    // ============================================
    // DEMO ACCOUNTS TOGGLE
    // ============================================
    const demoContent = document.getElementById('demoContent');
    const demoChevron = document.getElementById('demoChevron');
    let demoVisible = false;
    
    function toggleDemoAccounts() {
        demoVisible = !demoVisible;
        if (demoContent) {
            demoContent.style.display = demoVisible ? 'block' : 'none';
        }
        if (demoChevron) {
            demoChevron.classList.toggle('fa-chevron-down', !demoVisible);
            demoChevron.classList.toggle('fa-chevron-up', demoVisible);
        }
        const toggleBtn = document.querySelector('[onclick="toggleDemoAccounts()"]');
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', demoVisible);
        }
    }
    
    // ============================================
    // FILL DEMO CREDENTIALS
    // ============================================
    function fillCredentials(login, password) {
        loginInput.value = login;
        passwordInput.value = password;
        loginInput.dispatchEvent(new Event('input'));
        passwordInput.dispatchEvent(new Event('input'));
        updateLoginIcon(login);
        hideFieldError();
        // Auto-submit? No, let user click submit.
    }
    
    // ============================================
    // KEYBOARD SUPPORT FOR DEMO CARDS
    // ============================================
    document.querySelectorAll('.demo-card').forEach(card => {
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
    
    // ============================================
    // KEYBOARD SHORTCUT: Ctrl+Enter to submit
    // ============================================
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            loginForm.dispatchEvent(new Event('submit'));
        }
    });
    </script>
</body>
</html>