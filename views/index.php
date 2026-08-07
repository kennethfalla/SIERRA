<?php
// views/index.php - PUBLIC HOMEPAGE
// UPDATED: Clean, no leftover test code; uses dynamic system settings

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . 'helpers/SettingsHelper.php';

// Get statistics for homepage
$database = new Database();
$db = $database->getConnection();

// Get total reports count
$stmt = $db->query("SELECT COUNT(*) as total FROM reports");
$total_reports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get resolved reports count
$stmt = $db->query("SELECT COUNT(*) as total FROM reports WHERE status = 'resolved'");
$resolved_reports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total users count
$stmt = $db->query("SELECT COUNT(*) as total FROM users");
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// San Isidro Statistics
$san_isidro_stats = [
    'barangays' => 9,
    'population' => 55108,
    'households' => 12828
];

// Get logged in user's info for filtering
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
$isLoggedIn = isLoggedIn();
$user_name = $isLoggedIn ? $_SESSION['user_name'] : '';
$is_staff = in_array($user_type, ['admin', 'menro_staff'], true) || $user_role === 'admin';
$staff_label = $user_type === 'admin' ? 'Admin' : 'MENRO Staff';

// Get recent reports for the map (only show reports with location data)
$reports_for_map = $db->query("
    SELECT id, title, latitude, longitude, status, risk_level, category_id 
    FROM reports 
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL 
    AND latitude != 0 AND longitude != 0
    ORDER BY created_at DESC 
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// MENRO Vision and About
$menro_vision = "A clean, green, and sustainable San Isidro where every citizen is an active steward of the environment, and environmental resources are protected and preserved for future generations.";
$menro_about = "The Municipal Environment and Natural Resources Office (MENRO) of San Isidro is dedicated to the protection, conservation, and sustainable management of the municipality's natural resources and environment. MENRO works closely with barangay officials, community organizations, and citizens to address environmental concerns, enforce environmental laws, and promote ecological awareness. Through the Sierra Environmental Reporting System, MENRO aims to empower every citizen to participate in environmental governance and contribute to a cleaner, healthier community.";

// ===== DYNAMIC SETTINGS =====
$system_name = SettingsHelper::get('system_name', 'Sierra');
$contact_email = SettingsHelper::get('contact_email', 'menro@sanisidro.gov.ph');
$emergency_hotline = SettingsHelper::get('emergency_hotline', '0917-123-4567');
$lgu_logo = SettingsHelper::get('lgu_logo', '');
$logo_url = $lgu_logo ? BASE_URL . $lgu_logo : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($system_name); ?> - San Isidro Environmental Reporting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { font-family: 'Manrope', sans-serif; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-up {
            animation: fadeInUp 0.6s ease forwards;
        }
        
        .delay-1 { animation-delay: 0.1s; opacity: 0; }
        .delay-2 { animation-delay: 0.2s; opacity: 0; }
        .delay-3 { animation-delay: 0.3s; opacity: 0; }
        .delay-4 { animation-delay: 0.4s; opacity: 0; }
        .delay-5 { animation-delay: 0.5s; opacity: 0; }
        
        .btn-primary {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.3);
        }
        
        .btn-outline {
            border: 2px solid #059669;
            color: #059669;
            transition: all 0.3s ease;
            background: transparent;
        }
        .btn-outline:hover {
            background: #059669;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.3);
        }
        
        .stat-card {
            transition: all 0.3s ease;
            border: 1px solid rgba(5, 150, 105, 0.08);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            border-color: #059669;
            box-shadow: 0 20px 30px -15px rgba(5, 150, 105, 0.15);
        }
        
        .feature-card {
            transition: all 0.3s ease;
            border: 1px solid #eef2f0;
        }
        .feature-card:hover {
            transform: translateY(-6px);
            border-color: #059669;
            box-shadow: 0 25px 40px -20px rgba(5, 150, 105, 0.2);
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
        
        .section-divider {
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, #059669, #047857);
            border-radius: 2px;
            margin: 0 auto 1rem;
        }
        
        #map {
            height: 400px;
            width: 100%;
            border-radius: 1rem;
            z-index: 1;
            border: 1px solid rgba(5, 150, 105, 0.1);
        }
        
        .custom-marker {
            background: transparent;
        }
        
        .login-prompt {
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            border: 2px dashed #059669;
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            #map { height: 300px; }
            .login-prompt .btn-primary {
                padding: 0.5rem 1.5rem;
                font-size: 0.9rem;
                margin: 0.25rem;
                display: block;
            }
        }
        
        /* Logo styling */
        .brand-logo {
            max-height: 40px;
            width: auto;
        }
        .brand-logo-lg {
            max-height: 80px;
            width: auto;
        }
        @media (max-width: 640px) {
            .brand-logo-lg {
                max-height: 60px;
            }
        }
    </style>
</head>
<body class="bg-[#F5FBF6]">

<!-- ============================================ -->
<!-- NAVIGATION -->
<!-- ============================================ -->
<nav class="fixed w-full z-50 bg-white/95 backdrop-blur-sm border-b border-gray-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-2">
                <?php if ($logo_url): ?>
                    <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($system_name); ?> Logo" class="brand-logo">
                <?php else: ?>
                    <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-leaf text-white text-sm"></i>
                    </div>
                <?php endif; ?>
                <span class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($system_name); ?></span>
                <span class="text-xs text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full hidden sm:inline-block">San Isidro</span>
            </div>
            
            <div class="hidden md:flex items-center gap-6">
                <a href="#home" class="text-gray-600 hover:text-emerald-600 transition font-medium">Home</a>
                <a href="#features" class="text-gray-600 hover:text-emerald-600 transition font-medium">How It Works</a>
                <a href="#map-section" class="text-gray-600 hover:text-emerald-600 transition font-medium">Map</a>
                <a href="#stats" class="text-gray-600 hover:text-emerald-600 transition font-medium">Stats</a>
                <a href="#about" class="text-gray-600 hover:text-emerald-600 transition font-medium">About LGU</a>
            </div>
            
            <div class="flex items-center gap-3">
                <?php if($isLoggedIn): ?>
                    <div class="text-right hidden sm:block">
                        <p class="text-xs text-gray-500">Welcome back,</p>
                        <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($user_name); ?></p>
                    </div>
                    <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" class="btn-primary px-4 py-2 text-white rounded-lg text-sm font-medium">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>index.php?page=login" class="text-gray-600 hover:text-emerald-600 transition font-medium">Sign In</a>
                    <a href="<?php echo BASE_URL; ?>index.php?page=register" class="btn-primary px-4 py-2 text-white rounded-lg text-sm font-medium">
                        <i class="fas fa-user-plus mr-2"></i>Join Now
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- ============================================ -->
<!-- SECTION 1: HOME (HERO) -->
<!-- ============================================ -->
<section id="home" class="relative min-h-screen pt-20 overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 via-white to-teal-50"></div>
    <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-100 rounded-full blur-3xl opacity-30"></div>
    <div class="absolute bottom-0 left-0 w-80 h-80 bg-teal-100 rounded-full blur-3xl opacity-30"></div>
    
    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 min-h-screen flex items-center">
        <div class="grid lg:grid-cols-2 gap-12 items-center py-12">
            <div>
                <div class="inline-flex items-center gap-2 bg-white/60 backdrop-blur-sm px-4 py-2 rounded-full border border-emerald-100 mb-6 animate-fade-up">
                    <i class="fas fa-hand-peace text-emerald-600 text-sm"></i>
                    <span class="text-gray-700 text-sm">Working together for a cleaner community</span>
                </div>
                
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-gray-800 leading-tight mb-5 animate-fade-up delay-1">
                    <?php if($isLoggedIn): ?>
                        Good to see you,<br>
                        <span class="text-emerald-600"><?php echo htmlspecialchars($user_name); ?>!</span>
                    <?php else: ?>
                        Sama-sama nating<br>
                        <span class="text-emerald-600">pangalagaan ang<br>San Isidro.</span>
                    <?php endif; ?>
                </h1>
                
                <p class="text-lg text-gray-600 mb-8 leading-relaxed animate-fade-up delay-2">
                    <?php if($isLoggedIn && ($user_role === 'barangay_official' || $user_role === 'admin')): ?>
                        As a <?php echo ($user_type === 'barangay_personnel' || $user_role === 'barangay_official') ? 'Barangay Official' : $staff_label; ?>, you can review, verify, and manage environmental reports from your community. 
                        Take action on pending reports and help resolve issues faster.
                    <?php elseif($isLoggedIn): ?>
                        Your voice matters. Report environmental issues like illegal dumping, flooding, or pollution — 
                        and we'll help track them until they're resolved.
                    <?php else: ?>
                        See something wrong in your neighborhood? Illegal dumping, clogged canals, or air pollution? 
                        Report it here, and your barangay will take action. It's free, fast, and easy.
                    <?php endif; ?>
                </p>
                
                <div class="flex flex-wrap gap-4 animate-fade-up delay-2">
                    <?php if($isLoggedIn && ($user_role === 'barangay_official' || $user_role === 'admin')): ?>
                        <a href="<?php echo BASE_URL; ?>index.php?page=verify-reports" class="btn-primary px-6 py-3 text-white rounded-xl font-medium flex items-center gap-2">
                            <i class="fas fa-check-double"></i> Manage Reports
                        </a>
                        <a href="<?php echo BASE_URL; ?>index.php?page=announcements" class="btn-outline px-6 py-3 rounded-xl font-medium flex items-center gap-2">
                            <i class="fas fa-bullhorn"></i> Post Announcement
                        </a>
                    <?php elseif($isLoggedIn): ?>
                        <a href="<?php echo BASE_URL; ?>index.php?page=submit-report" class="btn-primary px-6 py-3 text-white rounded-xl font-medium flex items-center gap-2">
                            <i class="fas fa-plus-circle"></i> Report an Issue
                        </a>
                        <a href="<?php echo BASE_URL; ?>index.php?page=my-reports" class="btn-outline px-6 py-3 rounded-xl font-medium flex items-center gap-2">
                            <i class="fas fa-list"></i> My Reports
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-3 gap-4 mt-8 animate-fade-up delay-3">
                    <div class="stat-card bg-white rounded-xl p-4 text-center shadow-sm">
                        <div class="text-2xl font-bold text-emerald-600"><?php echo number_format($total_reports); ?></div>
                        <div class="text-xs text-gray-500 mt-1">Total Reports</div>
                    </div>
                    <div class="stat-card bg-white rounded-xl p-4 text-center shadow-sm">
                        <div class="text-2xl font-bold text-emerald-600"><?php echo number_format($resolved_reports); ?></div>
                        <div class="text-xs text-gray-500 mt-1">Resolved</div>
                    </div>
                    <div class="stat-card bg-white rounded-xl p-4 text-center shadow-sm">
                        <div class="text-2xl font-bold text-emerald-600"><?php echo number_format($total_users); ?></div>
                        <div class="text-xs text-gray-500 mt-1">Active Citizens</div>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Login/Register CTA -->
            <div class="animate-fade-up delay-2">
                <?php if($isLoggedIn): ?>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-lg overflow-hidden">
                        <div class="p-8 text-center">
                            <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-5">
                                <i class="fas fa-user-check text-emerald-600 text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-2">Welcome Back!</h3>
                            <p class="text-gray-500 text-sm mb-6">You're logged in as <?php echo htmlspecialchars($is_staff ? $staff_label : ($user_type === 'barangay_personnel' || $user_role === 'barangay_official' ? 'Barangay Official' : 'Citizen')); ?></p>
                            <div class="space-y-3">
                                <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" class="btn-primary w-full py-3 text-white rounded-xl font-medium block text-center">
                                    <i class="fas fa-tachometer-alt mr-2"></i>Go to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-lg overflow-hidden">
                        <div class="p-8">
                            <div class="text-center">
                                <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-5">
                                    <i class="fas fa-leaf text-emerald-600 text-3xl"></i>
                                </div>
                                <h3 class="text-xl font-bold text-gray-800 mb-2">Join the Community</h3>
                                <p class="text-gray-500 text-sm mb-6">Start reporting environmental issues today and help keep San Isidro clean.</p>
                            </div>
                            
                            <a href="<?php echo BASE_URL; ?>index.php?page=register" class="btn-primary w-full py-3 text-white rounded-xl font-medium block text-center">
                                <i class="fas fa-user-plus mr-2"></i>Create Free Account
                            </a>
                            
                            <div class="relative my-5">
                                <div class="absolute inset-0 flex items-center">
                                    <div class="w-full border-t border-gray-200"></div>
                                </div>
                                <div class="relative flex justify-center text-xs">
                                    <span class="px-3 bg-white text-gray-400">or</span>
                                </div>
                            </div>
                            
                            <a href="<?php echo BASE_URL; ?>index.php?page=login" class="w-full py-3 text-emerald-600 rounded-xl font-medium block text-center border-2 border-emerald-200 hover:bg-emerald-50 transition">
                                <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                            </a>
                            
                            <div class="mt-6 text-center">
                                <p class="text-xs text-gray-400">
                                    <i class="fas fa-lock text-gray-300 mr-1"></i>
                                    Your information is safe and private
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- SECTION 2: HOW IT WORKS -->
<!-- ============================================ -->
<section id="features" class="py-20 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="text-emerald-600 text-sm font-semibold uppercase tracking-wider">How It Works</span>
            <div class="section-divider"></div>
            <h2 class="text-3xl font-bold text-gray-800 mt-2">Three simple steps</h2>
            <p class="text-gray-500 mt-2 max-w-2xl mx-auto">You don't need to be an expert. Anyone can report an environmental issue in their neighborhood.</p>
        </div>
        
        <div class="grid md:grid-cols-3 gap-8">
            <div class="feature-card bg-white rounded-2xl p-8 text-center">
                <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-5">
                    <span class="text-3xl font-bold text-emerald-600">1</span>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-3">Join the Community</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Create a free account. No fees, no hidden charges. Just a commitment to a cleaner community.</p>
                <?php if(!$isLoggedIn): ?>
                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>index.php?page=register" class="text-emerald-600 font-medium hover:underline text-sm">Sign up now →</a>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="feature-card bg-white rounded-2xl p-8 text-center">
                <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-5">
                    <span class="text-3xl font-bold text-emerald-600">2</span>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-3">Report the Problem</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Take a photo, tag the location on the map, and describe the issue. It takes just a few minutes.</p>
                <?php if(!$isLoggedIn): ?>
                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>index.php?page=login" class="text-emerald-600 font-medium hover:underline text-sm">Login to report →</a>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="feature-card bg-white rounded-2xl p-8 text-center">
                <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-5">
                    <span class="text-3xl font-bold text-emerald-600">3</span>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-3">Track the Action</h3>
                <p class="text-gray-500 text-sm leading-relaxed">See when your barangay takes action until the issue is resolved. You'll get updates every step of the way.</p>
                <?php if(!$isLoggedIn): ?>
                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>index.php?page=login" class="text-emerald-600 font-medium hover:underline text-sm">Login to track →</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if(!$isLoggedIn): ?>
        <div class="login-prompt mt-12">
            <p class="text-gray-700 text-lg font-semibold mb-3">
                <i class="fas fa-lock text-emerald-600 mr-2"></i>
                Want to report an issue?
            </p>
            <p class="text-gray-500 text-sm mb-4">Login or create an account to start reporting environmental concerns in your community.</p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="<?php echo BASE_URL; ?>index.php?page=login" class="btn-primary px-6 py-2.5 text-white rounded-lg font-medium">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </a>
                <a href="<?php echo BASE_URL; ?>index.php?page=register" class="btn-outline px-6 py-2.5 rounded-lg font-medium">
                    <i class="fas fa-user-plus mr-2"></i>Register
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================================ -->
<!-- SECTION 3: MAP (LIVE ENVIRONMENTAL REPORTS) -->
<!-- ============================================ -->
<section id="map-section" class="py-20 bg-[#F5FBF6]">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="text-emerald-600 text-sm font-semibold uppercase tracking-wider">Live Map</span>
            <div class="section-divider"></div>
            <h2 class="text-3xl font-bold text-gray-800 mt-2">Environmental Reports Map</h2>
            <p class="text-gray-500 mt-2 max-w-2xl mx-auto">See where environmental issues are being reported across San Isidro.</p>
            <?php if(!$isLoggedIn): ?>
            <p class="text-xs text-gray-400 mt-2">
                <i class="fas fa-info-circle mr-1"></i>
                Login to view report details and submit your own reports.
            </p>
            <?php endif; ?>
        </div>
        
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 p-4">
            <div id="map"></div>
            <div class="flex flex-wrap gap-3 mt-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-green-500"></span> Low Risk</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-yellow-500"></span> Medium Risk</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-red-500"></span> High Risk</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-purple-500"></span> Critical Risk</span>
                <span class="flex items-center gap-1.5 ml-auto text-emerald-600 font-medium">
                    <i class="fas fa-map-pin"></i> <?php echo count($reports_for_map); ?> reports shown
                </span>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- SECTION 4: STATS (COMMUNITY IMPACT) -->
<!-- ============================================ -->
<section id="stats" class="py-20 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="text-emerald-600 text-sm font-semibold uppercase tracking-wider">Community Impact</span>
            <div class="section-divider"></div>
            <h2 class="text-3xl font-bold text-gray-800 mt-2">San Isidro Statistics</h2>
            <p class="text-gray-500 mt-2 max-w-2xl mx-auto">Together, we're making a difference in our community.</p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div class="stat-card bg-gradient-to-br from-emerald-50 to-teal-50 rounded-2xl p-6 text-center">
                <div class="text-3xl font-bold text-emerald-600"><?php echo number_format($san_isidro_stats['barangays']); ?></div>
                <p class="text-sm text-gray-600 mt-1">Barangays</p>
                <p class="text-xs text-gray-400 mt-2">All working together</p>
            </div>
            <div class="stat-card bg-gradient-to-br from-blue-50 to-cyan-50 rounded-2xl p-6 text-center">
                <div class="text-3xl font-bold text-blue-600"><?php echo number_format($san_isidro_stats['population']); ?></div>
                <p class="text-sm text-gray-600 mt-1">Population</p>
                <p class="text-xs text-gray-400 mt-2">Caring for each other</p>
            </div>
            <div class="stat-card bg-gradient-to-br from-purple-50 to-pink-50 rounded-2xl p-6 text-center">
                <div class="text-3xl font-bold text-purple-600"><?php echo number_format($san_isidro_stats['households']); ?></div>
                <p class="text-sm text-gray-600 mt-1">Households</p>
                <p class="text-xs text-gray-400 mt-2">Building a better future</p>
            </div>
            <div class="stat-card bg-gradient-to-br from-amber-50 to-orange-50 rounded-2xl p-6 text-center">
                <div class="text-3xl font-bold text-amber-600"><?php echo number_format($total_reports); ?></div>
                <p class="text-sm text-gray-600 mt-1">Reports Submitted</p>
                <p class="text-xs text-gray-400 mt-2">Voices heard</p>
            </div>
        </div>
        
        <!-- Resolution Rate -->
        <?php 
        $resolution_rate = $total_reports > 0 ? round(($resolved_reports / $total_reports) * 100) : 0;
        ?>
        <div class="mt-8 bg-gradient-to-r from-emerald-50 to-teal-50 rounded-2xl p-6 border border-emerald-100">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <p class="text-sm font-semibold text-gray-700">Resolution Rate</p>
                    <p class="text-3xl font-bold text-emerald-600"><?php echo $resolution_rate; ?>%</p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo number_format($resolved_reports); ?> of <?php echo number_format($total_reports); ?> reports resolved</p>
                </div>
                <div class="w-full md:w-2/3">
                    <div class="h-3 bg-white rounded-full overflow-hidden border border-emerald-100">
                        <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-1000" style="width: <?php echo $resolution_rate; ?>%;"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- SECTION 5: ABOUT LGU -->
<!-- ============================================ -->
<section id="about" class="py-20 relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-white via-emerald-50/30 to-purple-50/30"></div>
    <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-100/20 rounded-full blur-3xl"></div>
    <div class="absolute bottom-0 left-0 w-80 h-80 bg-purple-100/20 rounded-full blur-3xl"></div>
    
    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center mb-14">
            <div class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-100 px-4 py-1.5 rounded-full mb-4">
                <i class="fas fa-building text-emerald-600 text-xs"></i>
                <span class="text-emerald-700 text-xs font-semibold uppercase tracking-wider">About LGU</span>
            </div>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-3">
                Municipal Environment &amp; Natural Resources Office
            </h2>
            <p class="text-gray-500 max-w-2xl mx-auto">
                Committed to protecting and preserving San Isidro's environment for future generations.
            </p>
            <div class="w-20 h-1 bg-gradient-to-r from-emerald-500 to-purple-500 mx-auto mt-4 rounded-full"></div>
        </div>
        
        <div class="grid md:grid-cols-2 gap-8">
            
            <!-- Vision Card -->
            <div class="group relative bg-white rounded-2xl shadow-sm hover:shadow-xl transition-all duration-500 border border-emerald-100/50 overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-full -translate-y-1/2 translate-x-1/2 opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-emerald-100/20 rounded-full translate-y-1/2 -translate-x-1/2"></div>
                
                <div class="relative p-8">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-emerald-100 group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-eye text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Our Vision</h3>
                            <p class="text-sm text-emerald-600 font-medium">A greener, cleaner San Isidro</p>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100/30 rounded-xl p-6 border border-emerald-100/50">
                        <p class="text-gray-700 leading-relaxed text-lg italic">
                            "<?php echo $menro_vision; ?>"
                        </p>
                        <div class="mt-4 flex items-center gap-2 text-emerald-600">
                            <span class="w-8 h-0.5 bg-emerald-400"></span>
                            <span class="text-sm font-medium">Vision 2030</span>
                        </div>
                    </div>
                    
                    <div class="mt-6 grid grid-cols-3 gap-3">
                        <div class="text-center p-3 bg-emerald-50/50 rounded-xl border border-emerald-100/30">
                            <i class="fas fa-tree text-emerald-500 text-lg mb-1 block"></i>
                            <span class="text-xs font-medium text-gray-600">Green</span>
                        </div>
                        <div class="text-center p-3 bg-emerald-50/50 rounded-xl border border-emerald-100/30">
                            <i class="fas fa-hand-holding-heart text-emerald-500 text-lg mb-1 block"></i>
                            <span class="text-xs font-medium text-gray-600">Sustainable</span>
                        </div>
                        <div class="text-center p-3 bg-emerald-50/50 rounded-xl border border-emerald-100/30">
                            <i class="fas fa-users text-emerald-500 text-lg mb-1 block"></i>
                            <span class="text-xs font-medium text-gray-600">Inclusive</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- About MENRO Card -->
            <div class="group relative bg-white rounded-2xl shadow-sm hover:shadow-xl transition-all duration-500 border border-purple-100/50 overflow-hidden">
                <div class="absolute top-0 left-0 w-32 h-32 bg-purple-50 rounded-full -translate-y-1/2 -translate-x-1/2 opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
                <div class="absolute bottom-0 right-0 w-24 h-24 bg-purple-100/20 rounded-full translate-y-1/2 translate-x-1/2"></div>
                
                <div class="relative p-8">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-100 group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-building text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">About MENRO</h3>
                            <p class="text-sm text-purple-600 font-medium">Municipal Environment Office</p>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100/30 rounded-xl p-6 border border-purple-100/50">
                        <p class="text-gray-700 leading-relaxed">
                            <?php echo $menro_about; ?>
                        </p>
                    </div>
                    
                    <div class="mt-6 grid grid-cols-2 gap-2">
                        <div class="flex items-center gap-2 p-2.5 bg-purple-50/50 rounded-lg border border-purple-100/30">
                            <i class="fas fa-check-circle text-purple-500 text-xs"></i>
                            <span class="text-xs font-medium text-gray-600">Protection</span>
                        </div>
                        <div class="flex items-center gap-2 p-2.5 bg-purple-50/50 rounded-lg border border-purple-100/30">
                            <i class="fas fa-check-circle text-purple-500 text-xs"></i>
                            <span class="text-xs font-medium text-gray-600">Community</span>
                        </div>
                        <div class="flex items-center gap-2 p-2.5 bg-purple-50/50 rounded-lg border border-purple-100/30">
                            <i class="fas fa-check-circle text-purple-500 text-xs"></i>
                            <span class="text-xs font-medium text-gray-600">Sustainability</span>
                        </div>
                        <div class="flex items-center gap-2 p-2.5 bg-purple-50/50 rounded-lg border border-purple-100/30">
                            <i class="fas fa-check-circle text-purple-500 text-xs"></i>
                            <span class="text-xs font-medium text-gray-600">Partnership</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Core Values -->
        <div class="mt-12">
            <div class="text-center mb-8">
                <h3 class="text-xl font-bold text-gray-800">Our Core Values</h3>
                <p class="text-sm text-gray-400">The principles that guide our work every day</p>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="group bg-white rounded-xl p-6 text-center border border-gray-100 hover:border-emerald-200 transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                    <div class="w-16 h-16 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-shield-alt text-emerald-600 text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm mb-1">Protection</h4>
                    <p class="text-xs text-gray-400">Safeguarding natural resources</p>
                    <div class="mt-3 w-8 h-0.5 bg-emerald-400 mx-auto rounded-full group-hover:w-12 transition-all duration-300"></div>
                </div>
                
                <div class="group bg-white rounded-xl p-6 text-center border border-gray-100 hover:border-emerald-200 transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-50 to-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-hand-holding-heart text-blue-600 text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm mb-1">Service</h4>
                    <p class="text-xs text-gray-400">Dedicated to the community</p>
                    <div class="mt-3 w-8 h-0.5 bg-blue-400 mx-auto rounded-full group-hover:w-12 transition-all duration-300"></div>
                </div>
                
                <div class="group bg-white rounded-xl p-6 text-center border border-gray-100 hover:border-emerald-200 transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                    <div class="w-16 h-16 bg-gradient-to-br from-teal-50 to-teal-100 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-recycle text-teal-600 text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm mb-1">Sustainability</h4>
                    <p class="text-xs text-gray-400">For future generations</p>
                    <div class="mt-3 w-8 h-0.5 bg-teal-400 mx-auto rounded-full group-hover:w-12 transition-all duration-300"></div>
                </div>
                
                <div class="group bg-white rounded-xl p-6 text-center border border-gray-100 hover:border-emerald-200 transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-50 to-purple-100 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-users text-purple-600 text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm mb-1">Partnership</h4>
                    <p class="text-xs text-gray-400">Working together for change</p>
                    <div class="mt-3 w-8 h-0.5 bg-purple-400 mx-auto rounded-full group-hover:w-12 transition-all duration-300"></div>
                </div>
            </div>
        </div>
        
        <div class="mt-12 text-center">
            <a href="#features" class="inline-flex items-center gap-2 text-emerald-600 font-medium hover:text-emerald-700 transition group">
                <span>Learn how to get involved</span>
                <i class="fas fa-arrow-right text-sm group-hover:translate-x-1 transition-transform"></i>
            </a>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- FOOTER (UPDATED WITH DYNAMIC CONTACT INFO) -->
<!-- ============================================ -->
<footer class="bg-gray-900 text-white py-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-4 gap-8">
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($system_name); ?> Logo" class="brand-logo" style="max-height:32px;">
                    <?php else: ?>
                        <div class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-leaf text-white text-sm"></i>
                        </div>
                    <?php endif; ?>
                    <span class="text-lg font-bold"><?php echo htmlspecialchars($system_name); ?></span>
                </div>
                <p class="text-gray-400 text-sm">Environmental reporting system for San Isidro, Nueva Ecija. Working together for a cleaner, greener community.</p>
                <div class="mt-4 text-sm text-gray-400">
                    <p><i class="fas fa-envelope mr-2"></i> <?php echo htmlspecialchars($contact_email); ?></p>
                    <p><i class="fas fa-phone mr-2"></i> <?php echo htmlspecialchars($emergency_hotline); ?></p>
                </div>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Quick Links</h4>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="#home" class="hover:text-emerald-400 transition">Home</a></li>
                    <li><a href="#features" class="hover:text-emerald-400 transition">How It Works</a></li>
                    <li><a href="#map-section" class="hover:text-emerald-400 transition">Live Map</a></li>
                    <li><a href="#stats" class="hover:text-emerald-400 transition">Stats</a></li>
                    <li><a href="#about" class="hover:text-emerald-400 transition">About LGU</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Support</h4>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="#" class="hover:text-emerald-400 transition">FAQ</a></li>
                    <li><a href="#" class="hover:text-emerald-400 transition">Privacy Policy</a></li>
                    <li><a href="#" class="hover:text-emerald-400 transition">Terms of Service</a></li>
                    <li><a href="#" class="hover:text-emerald-400 transition">Contact Us</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Connect</h4>
                <div class="flex gap-3">
                    <a href="#" class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-emerald-600 transition">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-emerald-600 transition">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-emerald-600 transition">
                        <i class="fab fa-instagram"></i>
                    </a>
                </div>
                <p class="text-sm text-gray-400 mt-4">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    San Isidro, Nueva Ecija
                </p>
            </div>
        </div>
        <div class="border-t border-gray-800 mt-8 pt-6 text-center text-sm text-gray-500">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($system_name); ?> - San Isidro Environmental Reporting System. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
// ============================================
// MAP
// ============================================
const mapReports = <?php echo json_encode($reports_for_map); ?>;

function initMap() {
    const map = L.map('map').setView([15.3092, 120.9033], 13);
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);
    
    // Add San Isidro boundary
    <?php 
    $geojson_file = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/geojson/sanisidro.geojson';
    if (file_exists($geojson_file)) {
        $geojson_content = file_get_contents($geojson_file);
        $boundary_data = json_decode($geojson_content, true);
        if ($boundary_data && isset($boundary_data->features)) {
            echo "const boundaryData = " . json_encode($boundary_data) . ";";
        }
    }
    ?>
    
    if (typeof boundaryData !== 'undefined' && boundaryData && boundaryData.features) {
        try {
            for (const feature of boundaryData.features) {
                if (feature.geometry && feature.geometry.type === 'MultiPolygon') {
                    const coords = feature.geometry.coordinates[0][0].map(coord => [coord[1], coord[0]]);
                    L.polygon(coords, {
                        color: "#059669",
                        weight: 2,
                        fillColor: "#059669",
                        fillOpacity: 0.08,
                        smoothFactor: 1
                    }).addTo(map);
                }
            }
        } catch(e) {
            console.log('Boundary not loaded');
        }
    }
    
    // Add markers
    const riskColors = {
        'low': '#10B981',
        'medium': '#F59E0B',
        'high': '#EF4444',
        'critical': '#7C3AED'
    };
    
    const riskIcons = {
        'low': 'fa-seedling',
        'medium': 'fa-exclamation-triangle',
        'high': 'fa-fire',
        'critical': 'fa-skull-crossbones'
    };
    
    mapReports.forEach(function(report) {
        if (report.latitude && report.longitude) {
            const lat = parseFloat(report.latitude);
            const lng = parseFloat(report.longitude);
            const risk = report.risk_level || 'low';
            const color = riskColors[risk] || '#059669';
            
            const customIcon = L.divIcon({
                html: `<div style="background-color: ${color}; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.2); border: 2px solid white;">
                        <i class="fas ${riskIcons[risk]}" style="color: white; font-size: 14px;"></i>
                       </div>`,
                iconSize: [32, 32],
                className: 'custom-marker'
            });
            
            const statusDisplay = report.status === 'in_progress' ? 'In Progress' : report.status.charAt(0).toUpperCase() + report.status.slice(1);
            
            <?php if($isLoggedIn): ?>
            const popupContent = `
                <div style="font-family: Manrope, sans-serif; min-width: 200px; padding: 4px;">
                    <strong style="font-size: 14px; color: #1e293b;">${escapeHtml(report.title)}</strong><br>
                    <span style="font-size: 12px; color: #64748b;">Risk: ${risk.charAt(0).toUpperCase() + risk.slice(1)}</span><br>
                    <span style="font-size: 12px; color: #64748b;">Status: ${statusDisplay}</span><br>
                    <a href="<?php echo BASE_URL; ?>index.php?page=track-status&id=${report.id}" 
                       style="display: inline-block; margin-top: 8px; padding: 4px 12px; background: #059669; color: white; border-radius: 6px; font-size: 12px; text-decoration: none; font-weight: 500;">
                        View Details
                    </a>
                </div>
            `;
            <?php else: ?>
            const popupContent = `
                <div style="font-family: Manrope, sans-serif; min-width: 180px; padding: 4px; text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 8px;">🔒</div>
                    <p style="font-size: 14px; font-weight: 600; color: #1e293b;">Login to view details</p>
                    <p style="font-size: 12px; color: #64748b; margin: 4px 0 8px;">Sign in to see full report information</p>
                    <a href="<?php echo BASE_URL; ?>index.php?page=login" 
                       style="display: inline-block; padding: 6px 16px; background: #059669; color: white; border-radius: 6px; font-size: 12px; text-decoration: none; font-weight: 500;">
                        Login
                    </a>
                    <a href="<?php echo BASE_URL; ?>index.php?page=register" 
                       style="display: inline-block; padding: 6px 16px; margin-left: 4px; background: transparent; color: #059669; border: 1px solid #059669; border-radius: 6px; font-size: 12px; text-decoration: none; font-weight: 500;">
                        Register
                    </a>
                </div>
            `;
            <?php endif; ?>
            
            L.marker([lat, lng], { icon: customIcon })
                .bindPopup(popupContent)
                .addTo(map);
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', initMap);

// ============================================
// SMOOTH SCROLL
// ============================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        const target = document.querySelector(targetId);
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// ============================================
// NAVBAR SHADOW
// ============================================
window.addEventListener('scroll', function() {
    const nav = document.querySelector('nav');
    if (window.scrollY > 50) {
        nav.classList.add('shadow-md');
    } else {
        nav.classList.remove('shadow-md');
    }
});

// ============================================
// ANIMATION ON SCROLL
// ============================================
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate-fade-up');
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.stat-card, .feature-card').forEach(el => {
    el.style.opacity = '0';
    observer.observe(el);
});

// ============================================
// RESOLUTION RATE ANIMATION
// ============================================
const resolutionBar = document.querySelector('.h-full.bg-gradient-to-r');
if (resolutionBar) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const width = resolutionBar.style.width;
                resolutionBar.style.width = '0%';
                setTimeout(() => {
                    resolutionBar.style.width = width;
                }, 100);
            }
        });
    }, { threshold: 0.5 });
    observer.observe(resolutionBar);
}
</script>

</body>
</html>