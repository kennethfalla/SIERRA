<?php
// views/auth/login.php - Complete Login Page
// UPDATED: Works with AuthController (login → dashboard)
// Displays success messages from registration, logout, etc.
// Dynamic system name and logo from SettingsHelper
// LAYOUT: Split-screen design (image panel w/ wave cut + form panel)

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

// Lockout countdown target (unix seconds). Set by AuthController when a login
// is rejected for too many failed attempts; drives the live MM:SS countdown.
$login_lockout_until = (int)($_SESSION['login_lockout_until'] ?? 0);

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
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - <?php echo htmlspecialchars($system_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        html, body { height: 100%; }

        /* ============================================ */
        /* PAGE SHELL                                    */
        /* ============================================ */
        .auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr;
        }
        @media (min-width: 1024px) {
            .auth-shell { grid-template-columns: 1.05fr 1fr; }
        }

        /* ============================================ */
        /* LEFT: IMAGE / HERO PANEL                      */
        /* ============================================ */
        .hero-panel {
            position: relative;
            overflow: hidden;
            min-height: 280px;
            background:
                radial-gradient(circle at 15% 20%, rgba(255,255,255,0.12), transparent 40%),
                radial-gradient(circle at 80% 10%, rgba(255,255,255,0.06), transparent 35%),
                radial-gradient(circle at 30% 75%, rgba(3,40,30,0.35), transparent 45%),
                linear-gradient(135deg, #064e3b 0%, #047857 45%, #065f46 100%);
        }
        .hero-panel::before {
            /* subtle organic texture — kept on-brand, low opacity */
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 20% 30%, rgba(3,40,30,0.5) 0, transparent 12%),
                radial-gradient(circle at 45% 15%, rgba(3,40,30,0.45) 0, transparent 10%),
                radial-gradient(circle at 65% 40%, rgba(3,40,30,0.5) 0, transparent 14%),
                radial-gradient(circle at 25% 55%, rgba(3,40,30,0.4) 0, transparent 9%),
                radial-gradient(circle at 80% 65%, rgba(3,40,30,0.35) 0, transparent 11%),
                radial-gradient(circle at 50% 80%, rgba(3,40,30,0.45) 0, transparent 13%);
            mix-blend-mode: multiply;
            opacity: 0.4;
        }
        .hero-panel::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(0deg, rgba(3,30,22,0.60) 0%, rgba(6,78,59,0.05) 45%, rgba(3,40,30,0.18) 100%);
        }

        /* wavy white cut separating the two panels (desktop only) */
        .wave-divider {
            position: absolute;
            top: -2%;
            right: -1px;
            height: 104%;
            width: 130px;
            z-index: 3;
            display: none;
        }
        @media (min-width: 1024px) {
            .wave-divider { display: block; }
        }

        .hero-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2rem 2rem 3rem;
        }
        @media (min-width: 1024px) {
            .hero-content { padding: 2.5rem 3rem 4rem; }
        }

        .hero-headline {
            color: #ffffff;
            font-weight: 800;
            line-height: 1.15;
            font-size: 1.5rem;
            max-width: 30rem;
        }
        @media (min-width: 640px) {
            .hero-headline { font-size: 1.85rem; }
        }

        /* decorative concentric ring accents (bottom-left + bottom-right, like reference) */
        .ring-decor {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.35);
            pointer-events: none;
        }
        .ring-decor.r2 { border-color: rgba(255,255,255,0.22); }
        .ring-decor.r3 { border-color: rgba(255,255,255,0.12); }

        .corner-blob {
            position: absolute;
            bottom: -90px;
            right: -90px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: #05493a;
            z-index: 0;
        }
        .corner-blob .ring-decor { border-color: rgba(255,255,255,0.15); }

        /* ============================================ */
        /* RIGHT: FORM PANEL                             */
        /* ============================================ */
        .form-panel {
            position: relative;
            overflow: hidden;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1.5rem;
        }
        .form-card { position: relative; z-index: 1; width: 100%; max-width: 26rem; }

        .field-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #111111;
            margin-bottom: 0.4rem;
        }

        .input-field {
            width: 100%;
            padding: 0.85rem 2.75rem 0.85rem 1rem;
            border: 1.5px solid #e2e2e2;
            border-radius: 0.65rem;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: #ffffff;
            color: #111111;
            height: 48px;
        }
        .input-field:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5,150,105,0.14);
            outline: none;
        }
        .input-field.error {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08);
        }

        .input-group { position: relative; margin-bottom: 1.1rem; }

        .password-toggle {
            position: absolute;
            right: 0.9rem;
            top: 2.55rem;
            color: #9a9a9a;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 0.9rem;
            z-index: 3;
            transition: color 0.2s ease;
        }
        .password-toggle:hover { color: #047857; }

        .field-error {
            display: none;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: #dc2626;
            margin-bottom: 0.9rem;
            padding: 0.6rem 0.75rem;
            background: #fef2f2;
            border-radius: 0.5rem;
            border: 1px solid #fecaca;
            animation: slideDown 0.25s ease;
        }
        .field-error.show { display: flex; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .btn-primary {
            width: 100%;
            background: #047857;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.85rem 1rem;
            border-radius: 0.7rem;
            transition: all 0.2s ease;
        }
        .btn-primary:hover { background: #065f46; box-shadow: 0 8px 20px rgba(4,120,87,0.32); }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }

        .demo-card { transition: all 0.2s ease; }
        .demo-card:hover { transform: translateY(-1px); }

        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }
        @media (max-width: 480px) {
            .demo-accounts-grid { grid-template-columns: 1fr !important; }
        }

        .brand-logo-sm { max-height: 34px; width: auto; object-fit: contain; }
        .brand-logo-form {
            display: block;
            max-height: 70px;
            width: auto;
            object-fit: contain;
            margin: 0 auto 1.25rem;
        }
        @media (max-width: 640px) {
            .brand-logo-form { max-height: 56px; }
        }

        .flash-message { animation: slideDown 0.3s ease; }
        .flash-message.success { background: #f0fdf4; border-left: 4px solid #059669; color: #065f46; }
        .flash-message.error { background: #fef2f2; border-left: 4px solid #dc2626; color: #991b1b; }
        .flash-message.info { background: #ecfdf5; border-left: 4px solid #0f766e; color: #115e59; }
    </style>
</head>
<body>
    <div class="auth-shell">

        <!-- ============================================ -->
        <!-- LEFT: HERO / IMAGE PANEL                      -->
        <!-- ============================================ -->
        <div class="hero-panel">

            <!-- wavy white cut into the right edge (matches reference) -->
            <svg class="wave-divider" viewBox="0 0 200 900" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M130,0 C40,90 190,170 95,260 C0,350 175,430 85,520 C-5,610 165,690 100,780 C60,830 140,870 200,900 L200,0 Z" fill="#ffffff"/>
            </svg>

            <!-- subtle concentric rings, lower-left (echoes reference) -->
            <div class="ring-decor" style="width:170px;height:170px;left:-60px;bottom:40px;"></div>
            <div class="ring-decor r2" style="width:230px;height:230px;left:-90px;bottom:10px;"></div>
            <div class="ring-decor r3" style="width:290px;height:290px;left:-120px;bottom:-20px;"></div>

            <div class="hero-content">
                <a href="<?php echo BASE_URL; ?>index.php" class="inline-flex items-center gap-2 text-white/80 hover:text-white transition-all w-fit group">
                    <i class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform"></i>
                    <span class="text-sm font-medium">Back to Home</span>
                </a>

                <div>
                    <?php if ($logo_url): ?>
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($system_name); ?> Logo" class="brand-logo-sm mb-4">
                    <?php endif; ?>
                    <h1 class="hero-headline"><?php echo htmlspecialchars($system_name); ?> Collaboration
                        <br class="hidden sm:block">to keep our community clean, reported, and resolved.</h1>
                    <p class="text-white/70 text-sm mt-4 max-w-sm">San Isidro Environmental Hub — report concerns, track resolutions, and contribute to a cleaner, greener community.</p>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- RIGHT: LOGIN FORM PANEL                       -->
        <!-- ============================================ -->
        <div class="form-panel">
            <div class="form-card">

                <?php if ($logo_url): ?>
                    <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($system_name); ?> Logo" class="brand-logo-form">
                <?php endif; ?>

                <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 leading-tight">Welcome<br>Back</h2>
                <p class="text-gray-500 text-sm mt-2 mb-6">Sign in with your email or mobile number</p>

                <!-- ============================================ -->
                <!-- FLASH MESSAGES -->
                <!-- ============================================ -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="flash-message success rounded-lg p-3 text-sm mb-4" role="alert">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                            <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="flash-message error rounded-lg p-3 text-sm mb-4" role="alert">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                            <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($login_lockout_until > 0): ?>
                    <div id="lockoutBanner" class="flash-message error rounded-lg p-3 text-sm mb-4" style="display: none;" role="alert" aria-live="polite">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-clock" aria-hidden="true"></i>
                            <span>Too many failed attempts. Please try again in <strong id="lockoutCountdown" class="tabular-nums">--:--</strong>.</span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['info'])): ?>
                    <div class="flash-message info rounded-lg p-3 text-sm mb-4" role="alert">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            <span><?php echo htmlspecialchars($_SESSION['info']); unset($_SESSION['info']); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ============================================ -->
                <!-- LOGIN FORM -->
                <!-- ============================================ -->
                <form action="<?php echo BASE_URL; ?>controllers/AuthController.php" method="POST" id="loginForm" novalidate>
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="csrf_expiry" value="<?php echo $csrf_expiry; ?>" id="csrfExpiry">

                    <div class="field-error" id="fieldError" role="alert" aria-live="assertive">
                        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                        <span id="fieldErrorMessage">Invalid email or password.</span>
                    </div>

                    <div class="input-group">
                        <label for="login" class="field-label">Email or Mobile Number</label>
                        <input type="text" name="login" id="login" required class="input-field" placeholder="Enter your email or mobile number" autocomplete="username" aria-label="Email or mobile number" aria-describedby="fieldError">
                    </div>

                    <div class="input-group">
                        <label for="password" class="field-label">Password</label>
                        <input type="password" name="password" id="password" required class="input-field" placeholder="Enter your password" autocomplete="current-password" aria-label="Password">
                        <button type="button" id="togglePassword" class="password-toggle" aria-label="Toggle password visibility">
                            <i class="fas fa-eye-slash" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="flex items-center justify-between mb-5">
                        <label class="flex items-center gap-1.5 cursor-pointer group">
                            <input type="checkbox" name="remember" class="w-3.5 h-3.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-400">
                            <span class="text-xs text-gray-500 group-hover:text-gray-700">Remember me</span>
                        </label>
                        <a href="<?php echo BASE_URL; ?>index.php?page=forgot-password" class="text-xs font-medium text-emerald-700 hover:underline">Forgot Password?</a>
                    </div>

                    <button type="submit" id="submitBtn" class="btn-primary" aria-label="Sign in button">
                        <span id="submitText">Sign in</span>
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
                    <div class="flex-1 h-px bg-gray-100"></div>
                    <span class="px-3 text-gray-400 text-[10px] uppercase tracking-wide">New here?</span>
                    <div class="flex-1 h-px bg-gray-100"></div>
                </div>

                <div class="text-center">
                    <a href="<?php echo BASE_URL; ?>index.php?page=register" class="inline-flex items-center gap-2 text-emerald-700 font-semibold text-sm hover:gap-3 transition-all">
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
                            <i class="fas fa-flask text-gray-400 text-[10px] group-hover:text-emerald-600 transition-colors" aria-hidden="true"></i>
                            <span class="text-[10px] font-medium text-gray-400 uppercase tracking-wider group-hover:text-emerald-600 transition-colors">Demo Access</span>
                        </div>
                        <i id="demoChevron" class="fas fa-chevron-down text-gray-400 text-[10px] transition-transform duration-300" aria-hidden="true"></i>
                    </button>

                    <div id="demoContent" class="mt-3 space-y-3" style="display: none;" role="region" aria-label="Demo accounts">

                        <!-- MENRO Role -->
                        <div>
                            <div class="flex items-center gap-1.5 mb-2 px-0.5">
                                <div class="w-5 h-5 bg-gray-100 rounded-lg flex items-center justify-center" aria-hidden="true">
                                    <i class="fas fa-building text-gray-700 text-[9px]"></i>
                                </div>
                                <p class="text-[10px] font-semibold text-gray-700 uppercase tracking-wide">MENRO Administrator</p>
                            </div>
                            <div class="relative border border-gray-100 rounded-lg bg-white hover:border-emerald-300 hover:bg-emerald-50 transition-all cursor-pointer p-2 demo-card" onclick="fillCredentials('menro@envreport.com', 'password')" tabindex="0" role="button" aria-label="Login as MENRO Administrator">
                                <span class="absolute -top-2 right-2 text-[8px] font-semibold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-700">MENRO</span>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center" aria-hidden="true">
                                            <i class="fas fa-leaf text-gray-700 text-xs"></i>
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
                                <div class="w-5 h-5 bg-gray-100 rounded-lg flex items-center justify-center" aria-hidden="true">
                                    <i class="fas fa-map-marker-alt text-gray-700 text-[9px]"></i>
                                </div>
                                <p class="text-[10px] font-semibold text-gray-700 uppercase tracking-wide">Barangay Officials (9)</p>
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
                                <div class="relative border border-gray-100 rounded-lg bg-white hover:border-emerald-300 hover:bg-emerald-50 transition-all cursor-pointer p-1.5 demo-card" onclick="fillCredentials('<?php echo $email; ?>', 'password')" tabindex="0" role="listitem" aria-label="Login as Barangay <?php echo $name; ?> official">
                                    <span class="absolute -top-1.5 right-1.5 text-[7px] font-semibold px-1 py-0.5 rounded-full bg-gray-100 text-gray-700"><?php echo $name; ?></span>
                                    <div class="flex items-center gap-1">
                                        <i class="fas fa-envelope text-gray-400 text-[8px]" aria-hidden="true"></i>
                                        <span class="text-[9px] font-medium truncate"><?php echo $email; ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Citizens -->
                        <div>
                            <div class="flex items-center gap-1.5 mb-2 px-0.5">
                                <div class="w-5 h-5 bg-gray-100 rounded-lg flex items-center justify-center" aria-hidden="true">
                                    <i class="fas fa-users text-gray-700 text-[9px]"></i>
                                </div>
                                <p class="text-[10px] font-semibold text-gray-700 uppercase tracking-wide">Citizens (9)</p>
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
                                <div class="relative border border-gray-100 rounded-lg bg-white hover:border-emerald-300 hover:bg-emerald-50 transition-all cursor-pointer p-1.5 demo-card" onclick="fillCredentials('<?php echo $c[2]; ?>', 'password')" tabindex="0" role="listitem" aria-label="Login as <?php echo $c[1]; ?> from <?php echo $c[0]; ?>">
                                    <span class="absolute -top-1.5 right-1.5 text-[7px] font-semibold px-1 py-0.5 rounded-full bg-gray-100 text-gray-700"><?php echo $c[0]; ?></span>
                                    <div class="flex items-center gap-1">
                                        <i class="fas fa-user text-gray-400 text-[8px]" aria-hidden="true"></i>
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

            <!-- bottom-right decorative circle alongside the login form -->
            <div class="corner-blob">
                <div class="ring-decor" style="width:280px;height:280px;left:-30px;top:-30px;"></div>
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
    const toggleBtn = document.getElementById('togglePassword');

    // ============================================
    // LOCKOUT COUNTDOWN
    // ============================================
    const lockoutBanner = document.getElementById('lockoutBanner');
    const lockoutCountdown = document.getElementById('lockoutCountdown');
    const lockoutUntil = <?php echo (int)$login_lockout_until; ?> * 1000;

    if (lockoutBanner && lockoutCountdown && lockoutUntil > 0) {
        function renderLockout() {
            const remaining = Math.floor((lockoutUntil - Date.now()) / 1000);
            if (remaining <= 0) {
                lockoutBanner.style.display = 'none';
                submitBtn.disabled = false;
                return;
            }
            const mins = String(Math.floor(remaining / 60)).padStart(2, '0');
            const secs = String(remaining % 60).padStart(2, '0');
            lockoutCountdown.textContent = mins + ':' + secs;
            lockoutBanner.style.display = 'block';
            submitBtn.disabled = true;
        }
        renderLockout();
        setInterval(renderLockout, 1000);
    }

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
    // LIVE INPUT HANDLING (phone numbers: digits only)
    // ============================================
    loginInput.addEventListener('input', function(e) {
        let value = e.target.value;
        if (/^[0-9]+$/.test(value.replace(/\s/g, ''))) {
            let digits = value.replace(/[^0-9]/g, '');
            if (digits.length > 11) digits = digits.substring(0, 11);
            e.target.value = digits;
        }
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
        const btn = document.querySelector('[onclick="toggleDemoAccounts()"]');
        if (btn) {
            btn.setAttribute('aria-expanded', demoVisible);
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
        hideFieldError();
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