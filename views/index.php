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

// San Isidro Statistics (editable in Settings > Landing Page)
$lp = function($key, $default = '') {
    $value = SettingsHelper::get($key, $default);
    return ($value === null || $value === '') ? $default : $value;
};

$san_isidro_stats = [
    'barangays'  => (int)$lp('lp_stat_barangays', 9),
    'population' => (int)$lp('lp_stat_population', 55108),
    'households' => (int)$lp('lp_stat_households', 12828),
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

// Attach opaque tokens so the public map links never expose raw report IDs
foreach ($reports_for_map as &$map_row) {
    $map_row['token'] = IdGuard::enc((int)$map_row['id']);
}
unset($map_row);

// MENRO Vision, Mission, and About (editable in Settings > Landing Page)
$menro_vision = $lp('lp_vision_body', 'A clean, green, and sustainable San Isidro where every citizen is an active steward of the environment, and environmental resources are protected and preserved for future generations.');
$menro_mission = $lp('lp_mission_body', 'The Municipal Environment and Natural Resources Office (MENRO) of San Isidro is dedicated to the protection, conservation, and sustainable management of the municipality\'s natural resources and environment. MENRO works closely with barangay officials, community organizations, and citizens to address environmental concerns, enforce environmental laws, and promote ecological awareness. Through the Sierra Environmental Reporting System, MENRO aims to empower every citizen to participate in environmental governance and contribute to a cleaner, healthier community.');
$menro_about = $lp('lp_about_body', 'The Municipal Environment and Natural Resources Office (MENRO) of San Isidro is dedicated to the protection, conservation, and sustainable management of the municipality\'s natural resources and environment. MENRO works closely with barangay officials, community organizations, and citizens to address environmental concerns, enforce environmental laws, and promote ecological awareness. Through the Sierra Environmental Reporting System, MENRO aims to empower every citizen to participate in environmental governance and contribute to a cleaner, healthier community.');

// Mission & Vision imagery (editable in Settings > Landing Page > Media gallery).
// When no image has been uploaded yet, the section falls back to on-brand
// gradient + icon artwork instead of a broken image or an off-brand stock photo.
$mission_image_main = $lp('lp_mission_image_main', '');
if ($mission_image_main !== '' && preg_match('#^uploads/#i', $mission_image_main)) {
    $mission_image_main = BASE_URL . $mission_image_main;
}
$mission_image_inset = $lp('lp_mission_image_inset', '');
if ($mission_image_inset !== '' && preg_match('#^uploads/#i', $mission_image_inset)) {
    $mission_image_inset = BASE_URL . $mission_image_inset;
}
$vision_image_main = $lp('lp_vision_image', '');
if ($vision_image_main !== '' && preg_match('#^uploads/#i', $vision_image_main)) {
    $vision_image_main = BASE_URL . $vision_image_main;
}

// ===== DYNAMIC SETTINGS =====
$system_name = SettingsHelper::get('system_name', 'Sierra');
$contact_email = SettingsHelper::get('contact_email', 'menro@sanisidro.gov.ph');
$emergency_hotline = SettingsHelper::get('emergency_hotline', '0917-123-4567');
$lgu_logo = SettingsHelper::get('lgu_logo', '');
$logo_url = $lgu_logo ? BASE_URL . $lgu_logo : '';

// ===== HERO BACKGROUND MEDIA (editable in Settings > Landing Page) =====
$hero_bg_type  = $lp('lp_hero_bg_type', 'image');
$hero_bg_image = $lp('lp_hero_bg_image', 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=2069&q=80');
$hero_bg_video = $lp('lp_hero_bg_video', '');

// File picked from the Settings > Landing media gallery is stored as a relative
// path (e.g. uploads/settings/hero/hero_....webp). Make it absolute so the hero
// background loads no matter what URL the homepage is reached through.
if ($hero_bg_image !== '' && preg_match('#^uploads/#i', $hero_bg_image)) {
    $hero_bg_image = BASE_URL . $hero_bg_image;
}
if ($hero_bg_video !== '' && preg_match('#^uploads/#i', $hero_bg_video)) {
    $hero_bg_video = BASE_URL . $hero_bg_video;
}

$hero_bg_style = '';
$show_hero_video = false;
$show_hero_overlay = false;
if ($hero_bg_type === 'video' && $hero_bg_video) {
    $show_hero_video = true;
    // Green brand gradient behind the video while it loads; the overlay div
    // above the content adds a complementary brand-green tint on the video.
    $show_hero_overlay = true;
    $hero_bg_style = "background: linear-gradient(135deg, #064e3b 0%, #047857 50%, #065f46 100%);";
} elseif ($hero_bg_type === 'image' && $hero_bg_image) {
    // Brand-green gradient layered over the hero image for a green tint while
    // keeping the white hero text readable.
    $hero_bg_style = "background-image: linear-gradient(180deg, rgba(6,78,59,0.42) 0%, rgba(4,120,87,0.20) 40%, rgba(6,78,59,0.45) 70%, rgba(6,20,14,0.78) 100%), url('" . htmlspecialchars($hero_bg_image) . "'); background-size: cover; background-position: center;";
} else {
    // 'none' or a video type with no video URL: rich brand-green gradient
    $hero_bg_style = "background: linear-gradient(135deg, #064e3b 0%, #047857 45%, #0f766e 100%);";
}

// Role-aware hero subtitle (editable in Settings > Landing Page)
if ($isLoggedIn && $is_staff) {
    $hero_subtitle = str_replace('{role}', $staff_label, $lp('lp_hero_subtitle_staff', "As a {role}, you can review, verify, and manage environmental reports from your community.\nTake action on pending reports and help resolve issues faster."));
} elseif ($isLoggedIn) {
    $hero_subtitle = $lp('lp_hero_subtitle_user', "Your voice matters. Report environmental issues like illegal dumping, flooding, or pollution —\nand we'll help track them until they're resolved.");
} else {
    $hero_subtitle = $lp('lp_hero_subtitle_guest', "See something wrong in your neighborhood? Illegal dumping, clogged canals, or air pollution?\nReport it here, and your barangay will take action. It's free, fast, and easy.");
}

// Resolution rate for the hero stat panel
$resolution_rate = $total_reports > 0 ? round(($resolved_reports / $total_reports) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($system_name); ?> - San Isidro Environmental Reporting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/map-layers.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
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

        /* ============================================ */
        /* HERO REDESIGN */
        /* ============================================ */
        .hero-bg {
            background-image:
                linear-gradient(180deg, rgba(6,78,59,0.42) 0%, rgba(4,120,87,0.20) 40%, rgba(6,78,59,0.45) 70%, rgba(6,20,14,0.78) 100%),
                url('https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=2069&q=80');
            background-size: cover;
            background-position: center;
        }

        .hero-media-video {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
        }

        .hero-bg-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(4,40,31,0.58) 0%, rgba(6,78,59,0.40) 35%, rgba(4,120,87,0.32) 60%, rgba(3,22,15,0.62) 100%);
            z-index: 0;
        }

        .hero-heading {
            text-transform: uppercase;
            letter-spacing: -0.01em;
            line-height: 1.02;
            text-shadow: 0 2px 12px rgba(0,0,0,0.45), 0 4px 30px rgba(0,0,0,0.35);
        }

        .hero-eyebrow {
            background: rgba(255,255,255,0.14);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.3);
            color: rgba(255,255,255,0.95);
            box-shadow: 0 8px 24px -8px rgba(0,0,0,0.25);
        }

        .hero-scroll-cue {
            text-shadow: 0 1px 6px rgba(0,0,0,0.25);
        }

        .glass-card {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(10px);
            box-shadow: 0 15px 35px -10px rgba(0,0,0,0.25);
        }

        .btn-light {
            background: white;
            color: #047857;
            transition: all 0.3s ease;
        }
        .btn-light:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(255,255,255,0.4);
        }

        .btn-outline-light {
            border: 2px solid rgba(255,255,255,0.75);
            color: white;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(6px);
            transition: all 0.3s ease;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
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

        /* ============================================ */
        /* INTRO SPLASH — logo drops from top, zooms,   */
        /* then the landing page fades in               */
        /* ============================================ */
        body.splash-lock { overflow: hidden; }

        #intro-splash {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            contain: layout style paint;
        }
        #intro-splash .intro-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            opacity: 0;
            will-change: transform, opacity;
        }
        #intro-splash .intro-logo {
            max-height: 120px;
            width: auto;
            object-fit: contain;
        }
        #intro-splash .intro-logo-fallback {
            width: 112px;
            height: 112px;
            border-radius: 1.5rem;
            background: linear-gradient(135deg, #065f46 0%, #10A37F 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 2.8rem;
            box-shadow: 0 20px 40px -12px rgba(16,163,127,.45);
        }
        #intro-splash .tw-droplet {
            position: absolute;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 30%, rgba(20,184,166,.95) 0%, #0d5c46 70%);
            filter: blur(24px);
            opacity: 0;
            will-change: transform, opacity;
        }
        #intro-splash .intro-title {
            font-family: 'Manrope', sans-serif;
            font-size: clamp(2.6rem, 7vw, 4rem);
            font-weight: 800;
            letter-spacing: .12em;
            text-indent: .12em;
            text-align: center;
            position: relative;
        }
        #intro-splash .tw-char {
            font-family: inherit;
            font-size: inherit;
            letter-spacing: inherit;
            background: linear-gradient(135deg, #064e3b 0%, #10A37F 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: #065f46;
            opacity: 0;
            filter: blur(18px);
        }
        @media (prefers-reduced-motion: reduce) {
            #intro-splash { display: none; }
        }

        /* ============================================ */
        /* MOBILE OPTIMISATIONS  (≤ 639px)              */
        /* ============================================ */
        @media (max-width: 639px) {

            /* ── Nav ── */
            nav .text-xl { font-size: 1rem; }

            /* ── Hero ── */
            #home {
                min-height: 100svh;
                border-radius: 0 0 1.5rem 1.5rem;
            }
            #home > .relative.z-10 {
                padding-top: 4.5rem;
                padding-bottom: 3rem;
                gap: 1.5rem;
            }
            .hero-eyebrow {
                font-size: 0.68rem !important;
                padding: 0.35rem 0.75rem;
                margin-bottom: 0.9rem !important;
                line-height: 1.4;
            }
            .hero-heading {
                font-size: 2rem !important;
                line-height: 1.1;
                margin-bottom: 0.75rem !important;
            }
            #home p.text-white\/85 {
                font-size: 0.82rem !important;
                line-height: 1.55;
                margin-bottom: 1.1rem !important;
            }
            /* CTA buttons — stack nicely */
            #home .flex.flex-wrap.gap-3 a {
                padding: 0.55rem 1.1rem;
                font-size: 0.8rem;
                border-radius: 0.65rem;
            }
            /* Stats glass card — compact */
            .glass-card {
                padding: 1rem 1.1rem !important;
                border-radius: 1.25rem !important;
            }
            .glass-card .text-2xl { font-size: 1.35rem; }
            .glass-card > .flex.items-center.gap-3 { margin-bottom: 0.85rem !important; }
            .glass-card > .flex.items-center.gap-3 .text-sm { font-size: 0.72rem; }
            .glass-card > .flex.items-center.gap-3 p  { font-size: 0.68rem; }
            .glass-card .mt-6 { margin-top: 0.85rem !important; }
            .glass-card .w-11 { width: 2.1rem; height: 2.1rem; }

            /* ── Section shared ── */
            section { padding-top: 2.5rem !important; padding-bottom: 2.5rem !important; }
            .text-center.mb-12,
            .text-center.mb-8  { margin-bottom: 1.5rem !important; }
            .text-center.mb-14 { margin-bottom: 1.75rem !important; }
            section h2.text-3xl,
            section h2.text-3xl.md\:text-4xl { font-size: 1.35rem !important; }
            section p.text-gray-500 { font-size: 0.82rem !important; }
            .section-divider { margin-bottom: 0.6rem; }

            /* ── How It Works cards ── */
            #features .grid.md\:grid-cols-3 { gap: 0.75rem; }
            .feature-card { padding: 1.25rem !important; border-radius: 1rem !important; }
            .feature-card .w-20 { width: 3rem; height: 3rem; }
            .feature-card .text-3xl { font-size: 1.25rem; }
            .feature-card h3.text-xl { font-size: 0.95rem !important; margin-bottom: 0.4rem !important; }
            .feature-card p.text-sm { font-size: 0.78rem !important; }
            /* Login prompt */
            .login-prompt { padding: 1rem !important; }
            .login-prompt p.text-lg  { font-size: 0.95rem !important; }
            .login-prompt p.text-sm  { font-size: 0.78rem !important; }

            /* ── Map section ── */
            #map { height: 230px !important; border-radius: 0.75rem; }
            #map-section .bg-white.rounded-2xl { padding: 0.65rem !important; }
            #map-section .flex.flex-wrap.gap-3 { gap: 0.5rem; font-size: 0.7rem; }

            /* ── Stats ── */
            #stats .grid.grid-cols-2 { gap: 0.6rem; }
            .stat-card { padding: 0.85rem 0.6rem !important; border-radius: 1rem !important; }
            .stat-card .text-3xl { font-size: 1.35rem !important; }
            .stat-card p.text-sm { font-size: 0.7rem !important; }
            /* Resolution bar card */
            #stats .mt-8 { margin-top: 0.85rem !important; padding: 0.9rem !important; border-radius: 1rem !important; }
            #stats .mt-8 .text-3xl { font-size: 1.4rem !important; }
            #stats .mt-8 .text-sm { font-size: 0.78rem !important; }
            #stats .mt-8 .text-xs { font-size: 0.68rem !important; }
            #stats .w-full.md\:w-2\/3 { width: 100% !important; }

            /* ── About LGU ── */
            #about .grid.md\:grid-cols-2 { gap: 1.25rem; margin-bottom: 2rem !important; }
            #about h3.text-3xl,
            #about h3.text-3xl.md\:text-4xl { font-size: 1.25rem !important; margin-bottom: 0.6rem !important; }
            #about p.text-gray-600 { font-size: 0.8rem !important; line-height: 1.55; margin-bottom: 1rem !important; }
            /* Mission / Vision images — shorter on mobile */
            #about .relative.w-full.h-\[320px\],
            #about img.w-full.h-\[320px\] { height: 190px !important; }
            /* Inset image */
            #about .absolute.-bottom-8 { display: none; }
            /* Core Values grid */
            #about .grid.grid-cols-2.md\:grid-cols-4 { gap: 0.6rem; }
            #about .group.bg-white { padding: 0.85rem 0.6rem !important; border-radius: 0.85rem !important; }
            #about .group .w-16 { width: 2.5rem; height: 2.5rem; margin-bottom: 0.6rem !important; }
            #about .group .text-2xl { font-size: 1rem; }
            #about .group h4 { font-size: 0.72rem !important; }
            #about .group p.text-xs { font-size: 0.65rem !important; }
            /* Bullet points */
            #about .flex.items-start.gap-3 span { font-size: 0.78rem; }

            /* ── Footer ── */
            footer { padding-top: 2rem !important; padding-bottom: 2rem !important; }
            footer .grid.md\:grid-cols-4 { grid-template-columns: 1fr 1fr; gap: 1.5rem 1rem; }
            footer h4 { font-size: 0.8rem; margin-bottom: 0.6rem !important; }
            footer ul.space-y-2 { gap: 0.3rem; }
            footer .text-sm { font-size: 0.75rem; }
            footer .text-gray-400.text-sm { font-size: 0.73rem; }
            footer .border-t { margin-top: 1.25rem !important; padding-top: 1rem !important; }
            footer .border-t p { font-size: 0.68rem; }
        }

        /* ── Slightly above mobile (480–639) — loosen up slightly ── */
        @media (min-width: 480px) and (max-width: 639px) {
            .hero-heading { font-size: 2.35rem !important; }
            .feature-card { padding: 1.5rem !important; }
            .feature-card h3.text-xl { font-size: 1.05rem !important; }
            .stat-card .text-3xl { font-size: 1.5rem !important; }
            #about img.w-full.h-\[320px\],
            #about .relative.w-full.h-\[320px\] { height: 220px !important; }
        }
    </style>
</head>
<body class="bg-[#F5FBF6] splash-lock">

<!-- ============================================ -->
<!-- INTRO SPLASH — logo drops from top, zooms,   -->
<!-- then the landing page fades in               -->
<!-- ============================================ -->
<div id="intro-splash" aria-hidden="true">
    <div class="intro-brand">
        <?php if ($logo_url): ?>
            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="" class="intro-logo">
        <?php else: ?>
            <div class="intro-logo-fallback"><i class="fas fa-leaf"></i></div>
        <?php endif; ?>
        <span class="tw-droplet" aria-hidden="true"></span>
        <span class="tw-droplet" aria-hidden="true"></span>
        <span class="tw-droplet" aria-hidden="true"></span>
        <span class="tw-droplet" aria-hidden="true"></span>
        <span class="tw-droplet" aria-hidden="true"></span>
        <span class="tw-droplet" aria-hidden="true"></span>
        <span class="intro-title" aria-label="SIERRA">
            <span class="tw-char">S</span><span class="tw-char">I</span><span class="tw-char">E</span><span class="tw-char">R</span><span class="tw-char">R</span><span class="tw-char">A</span>
        </span>
    </div>
</div>
<!-- /INTRO SPLASH -->

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
<section id="home" class="relative min-h-screen overflow-hidden hero-bg rounded-b-[2rem] sm:rounded-b-[2.5rem] flex flex-col justify-end" style="<?php echo $hero_bg_style; ?>">
    <?php if ($show_hero_video): ?>
        <video class="hero-media-video" autoplay muted loop playsinline src="<?php echo htmlspecialchars($hero_bg_video); ?>"></video>
    <?php endif; ?>
    <?php if ($show_hero_overlay): ?>
        <div class="absolute inset-0 hero-bg-overlay"></div>
    <?php endif; ?>
    <div class="relative z-10 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-10 max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pb-24 sm:pb-28 pt-24">

            <!-- Left: eyebrow + heading + subtitle + CTAs -->
            <div class="max-w-2xl animate-fade-up">
                <p class="hero-eyebrow inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs sm:text-sm font-semibold mb-6">
                    <span class="relative flex h-2 w-2 flex-shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-60"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
                    </span>
                    <?php echo htmlspecialchars($lp('lp_hero_badge', "Environmental care is more than a policy. It protects your barangay and keeps San Isidro clean today.")); ?>
                </p>

                <h1 class="hero-heading text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white mb-6">
                    <?php if($isLoggedIn): ?>
                        Good to see you,<br>
                        <span class="text-white"><?php echo htmlspecialchars($user_name); ?>!</span>
                    <?php else: ?>
                        <?php echo htmlspecialchars($lp('lp_hero_headline_1', 'Sama-sama nating')); ?><br>
                        <?php echo nl2br(htmlspecialchars($lp('lp_hero_headline_2', "pangalagaan ang\nSan Isidro."))); ?>
                    <?php endif; ?>
                </h1>

                <p class="text-white/85 text-base sm:text-lg leading-relaxed mb-8 max-w-xl">
                    <?php echo nl2br(htmlspecialchars($hero_subtitle)); ?>
                </p>

                <div class="flex flex-wrap gap-3 animate-fade-up delay-2">
                    <?php if($isLoggedIn && ($user_role === 'barangay_official' || $user_role === 'admin')): ?>
                        <a href="<?php echo BASE_URL; ?>index.php?page=verify-reports" class="btn-light px-6 py-3 rounded-xl font-semibold flex items-center gap-2">
                            <i class="fas fa-check-double"></i> Manage Reports
                        </a>
                        <a href="<?php echo BASE_URL; ?>index.php?page=announcements" class="btn-outline-light px-6 py-3 rounded-xl font-semibold flex items-center gap-2">
                            <i class="fas fa-bullhorn"></i> Post Announcement
                        </a>
                    <?php elseif($isLoggedIn): ?>
                        <a href="<?php echo BASE_URL; ?>index.php?page=submit-report" class="btn-light px-6 py-3 rounded-xl font-semibold flex items-center gap-2">
                            <i class="fas fa-plus-circle"></i> Report an Issue
                        </a>
                        <a href="<?php echo BASE_URL; ?>index.php?page=my-reports" class="btn-outline-light px-6 py-3 rounded-xl font-semibold flex items-center gap-2">
                            <i class="fas fa-list"></i> My Reports
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>index.php?page=register" class="btn-light px-6 py-3 rounded-xl font-semibold flex items-center gap-2">
                            <i class="fas fa-user-plus"></i> Create Free Account
                        </a>
                        <a href="<?php echo BASE_URL; ?>index.php?page=login" class="btn-outline-light px-6 py-3 rounded-xl font-semibold flex items-center gap-2">
                            <i class="fas fa-sign-in-alt"></i> Sign In
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: unified community stats panel -->
            <div class="w-full lg:w-96 flex-shrink-0 animate-fade-up delay-3">
                <div class="glass-card rounded-3xl p-7">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-11 h-11 rounded-xl bg-emerald-600 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-seedling text-white text-base"></i>
                        </div>
                        <div>
                            <div class="text-sm font-extrabold text-gray-800 leading-tight uppercase">San Isidro at a Glance</div>
                            <p class="text-[11px] text-gray-500 mt-1 text-base"><?php echo htmlspecialchars($lp('lp_hero_stats_caption', 'Community impact in real time.')); ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 divide-x divide-gray-200">
                        <div class="pr-3">
                            <div class="text-2xl font-extrabold text-emerald-700"><?php echo number_format($total_reports); ?></div>
                            <div class="text-[10px] font-bold text-gray-600 uppercase tracking-wide mt-1">Reports</div>
                        </div>
                        <div class="px-3">
                            <div class="text-2xl font-extrabold text-emerald-700"><?php echo number_format($total_users); ?></div>
                            <div class="text-[10px] font-bold text-gray-600 uppercase tracking-wide mt-1">Citizens</div>
                        </div>
                        <div class="pl-3">
                            <div class="text-2xl font-extrabold text-emerald-700"><?php echo $resolution_rate; ?>%</div>
                            <div class="text-[10px] font-bold text-gray-600 uppercase tracking-wide mt-1">Resolved</div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="flex justify-between text-[11px] font-semibold text-gray-600 mb-1.5">
                            <span>Resolution rate</span>
                            <span><?php echo $resolution_rate; ?>%</span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-200 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-emerald-600 to-green-500" style="width: <?php echo $resolution_rate; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    <!-- Scroll cue -->
    <a href="#features" class="hero-scroll-cue hidden sm:flex flex-col items-center gap-1.5 absolute bottom-4 left-1/2 -translate-x-1/2 text-white/80 hover:text-white transition" aria-label="Scroll to explore">
        <span class="text-[10px] uppercase tracking-widest">Scroll</span>
        <i class="fas fa-chevron-down text-sm animate-bounce"></i>
    </a>
</section>

<!-- ============================================ -->
<!-- SECTION 2: HOW IT WORKS -->
<!-- ============================================ -->
<section id="features" class="py-20 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="text-emerald-600 text-sm font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($lp('lp_how_kicker', 'How It Works')); ?></span>
            <div class="section-divider"></div>
            <h2 class="text-3xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($lp('lp_how_heading', 'Three simple steps')); ?></h2>
            <p class="text-gray-500 mt-2 max-w-2xl mx-auto"><?php echo htmlspecialchars($lp('lp_how_intro', "You don't need to be an expert. Anyone can report an environmental issue in their neighborhood.")); ?></p>
        </div>
        
        <div class="grid md:grid-cols-3 gap-8">
            <div class="feature-card bg-white rounded-2xl p-8 text-center">
                <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-5">
                    <span class="text-3xl font-bold text-emerald-600">1</span>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-3"><?php echo htmlspecialchars($lp('lp_how_step1_title', 'Join the Community')); ?></h3>
                <p class="text-gray-500 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($lp('lp_how_step1_desc'))); ?></p>
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
                <h3 class="text-xl font-bold text-gray-800 mb-3"><?php echo htmlspecialchars($lp('lp_how_step2_title', 'Report the Problem')); ?></h3>
                <p class="text-gray-500 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($lp('lp_how_step2_desc'))); ?></p>
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
                <h3 class="text-xl font-bold text-gray-800 mb-3"><?php echo htmlspecialchars($lp('lp_how_step3_title', 'Track the Action')); ?></h3>
                <p class="text-gray-500 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($lp('lp_how_step3_desc'))); ?></p>
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
            <span class="text-emerald-600 text-sm font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($lp('lp_map_kicker', 'Live Map')); ?></span>
            <div class="section-divider"></div>
            <h2 class="text-3xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($lp('lp_map_heading', 'Environmental Reports Map')); ?></h2>
            <p class="text-gray-500 mt-2 max-w-2xl mx-auto"><?php echo htmlspecialchars($lp('lp_map_intro', 'See where environmental issues are being reported across San Isidro.')); ?></p>
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
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-red-600"></span> Critical Risk</span>
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
            <span class="text-emerald-600 text-sm font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($lp('lp_stats_kicker', 'Community Impact')); ?></span>
            <div class="section-divider"></div>
            <h2 class="text-3xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($lp('lp_stats_heading', 'San Isidro Statistics')); ?></h2>
            <p class="text-gray-500 mt-2 max-w-2xl mx-auto"><?php echo htmlspecialchars($lp('lp_stats_intro', "Together, we're making a difference in our community.")); ?></p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div class="stat-card bg-gradient-to-br from-emerald-50 to-teal-50 rounded-2xl p-6 text-center">
                <div class="text-3xl font-bold text-emerald-600"><?php echo number_format($san_isidro_stats['barangays']); ?></div>
                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($lp('lp_stat_barangays_label', 'Barangays')); ?></p>
                <p class="text-xs text-gray-400 mt-2"><?php echo htmlspecialchars($lp('lp_stat_barangays_sub')); ?></p>
            </div>
            <div class="stat-card bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-2xl p-6 text-center">
                <div class="text-3xl font-bold text-emerald-700"><?php echo number_format($san_isidro_stats['population']); ?></div>
                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($lp('lp_stat_population_label', 'Population')); ?></p>
                <p class="text-xs text-gray-400 mt-2"><?php echo htmlspecialchars($lp('lp_stat_population_sub')); ?></p>
            </div>
            <div class="stat-card bg-gradient-to-br from-teal-50 to-emerald-100 rounded-2xl p-6 text-center">
                <div class="text-3xl font-bold text-teal-700"><?php echo number_format($san_isidro_stats['households']); ?></div>
                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($lp('lp_stat_households_label', 'Households')); ?></p>
                <p class="text-xs text-gray-400 mt-2"><?php echo htmlspecialchars($lp('lp_stat_households_sub')); ?></p>
            </div>
            <div class="stat-card bg-gradient-to-br from-emerald-100 to-white rounded-2xl p-6 text-center">
                <div class="text-3xl font-bold text-emerald-700"><?php echo number_format($total_reports); ?></div>
                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($lp('lp_stat_reports_label', 'Reports Submitted')); ?></p>
                <p class="text-xs text-gray-400 mt-2"><?php echo htmlspecialchars($lp('lp_stat_reports_sub')); ?></p>
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
    <div class="absolute inset-0 bg-gradient-to-br from-white via-emerald-50/30 to-emerald-100/30"></div>
    <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-100/20 rounded-full blur-3xl"></div>
    <div class="absolute bottom-0 left-0 w-80 h-80 bg-emerald-100/25 rounded-full blur-3xl"></div>
    
    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center mb-14">
            <div class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-100 px-4 py-1.5 rounded-full mb-4">
                <i class="fas fa-building text-emerald-600 text-xs"></i>
                <span class="text-emerald-700 text-xs font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($lp('lp_about_kicker', 'About LGU')); ?></span>
            </div>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-3">
                <?php echo htmlspecialchars($lp('lp_about_heading', 'Municipal Environment & Natural Resources Office')); ?>
            </h2>
            <p class="text-gray-500 max-w-2xl mx-auto">
                <?php echo htmlspecialchars($lp('lp_about_subtitle', "Committed to protecting and preserving San Isidro's environment for future generations.")); ?>
            </p>
            <div class="w-20 h-1 bg-gradient-to-r from-emerald-500 to-teal-600 mx-auto mt-4 rounded-full"></div>
        </div>
        
        <!-- Our Mission -->
        <div class="grid md:grid-cols-2 gap-10 lg:gap-16 items-center mb-24">

            <!-- Mission Imagery -->
            <div class="relative order-2 md:order-1">
                <div class="absolute -top-6 -left-6 w-40 h-40 bg-emerald-100 rounded-3xl -z-10 hidden md:block"></div>

                <?php if ($mission_image_main): ?>
                    <img src="<?php echo htmlspecialchars($mission_image_main); ?>" alt="<?php echo htmlspecialchars($lp('lp_mission_title', 'Our Mission')); ?>" class="w-full h-[320px] md:h-[380px] object-cover rounded-2xl shadow-xl">
                <?php else: ?>
                    <div class="relative w-full h-[320px] md:h-[380px] rounded-2xl shadow-xl overflow-hidden bg-gradient-to-br from-emerald-500 via-emerald-600 to-teal-700 flex items-center justify-center">
                        <div class="absolute inset-0 opacity-20" style="background-image:radial-gradient(circle at 25% 25%, white 0, transparent 45%), radial-gradient(circle at 80% 80%, white 0, transparent 40%);"></div>
                        <i class="fas fa-bullseye text-white/90 text-7xl relative"></i>
                    </div>
                <?php endif; ?>

                <div class="absolute -bottom-8 -right-6 md:-right-8 w-2/5 aspect-square rounded-2xl shadow-xl border-4 border-white overflow-hidden">
                    <?php if ($mission_image_inset): ?>
                        <img src="<?php echo htmlspecialchars($mission_image_inset); ?>" alt="MENRO field team" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-br from-emerald-600 to-teal-700 flex items-center justify-center">
                            <i class="fas fa-users text-white/90 text-3xl"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mission Text -->
            <div class="order-1 md:order-2">
                <div class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-100 px-4 py-1.5 rounded-full mb-4">
                    <i class="fas fa-bullseye text-emerald-600 text-xs"></i>
                    <span class="text-emerald-700 text-xs font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($lp('lp_mission_tagline', 'Our Purpose')); ?></span>
                </div>
                <h3 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">
                    <?php echo htmlspecialchars($lp('lp_mission_title', 'Our Mission')); ?>
                </h3>
<p class="text-gray-600 leading-relaxed mb-7">
                        <?php echo nl2br(htmlspecialchars($menro_mission)); ?>
                    </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-emerald-500 mt-1"></i>
                        <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($lp('lp_mission_point1', 'Fostering Sustainable Growth and Green Development')); ?></span>
                    </div>
                    <div class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-emerald-500 mt-1"></i>
                        <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($lp('lp_mission_point2', 'Innovating for a Sustainable Future')); ?></span>
                    </div>
                    <div class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-emerald-500 mt-1"></i>
                        <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($lp('lp_mission_point3', 'Community-Centered Public Service')); ?></span>
                    </div>
                    <div class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-emerald-500 mt-1"></i>
                        <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($lp('lp_mission_point4', 'Building Stronger, Resilient Barangays')); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Our Vision -->
        <div class="grid md:grid-cols-2 gap-10 lg:gap-16 items-center">

            <!-- Vision Text -->
            <div>
                <div class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-100 px-4 py-1.5 rounded-full mb-4">
                    <i class="fas fa-eye text-emerald-600 text-xs"></i>
                    <span class="text-emerald-700 text-xs font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($lp('lp_vision_tagline', 'Looking Ahead')); ?></span>
                </div>
                <h3 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">
                    <?php echo htmlspecialchars($lp('lp_vision_title', 'Our Vision')); ?>
                </h3>
                <p class="text-gray-600 leading-relaxed mb-7">
                    <?php echo nl2br(htmlspecialchars($menro_vision)); ?>
                </p>

                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-emerald-500 mt-1"></i>
                        <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($lp('lp_vision_point1', 'Inspiring Environmental Stewardship')); ?></span>
                    </div>
                    <div class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-emerald-500 mt-1"></i>
                        <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($lp('lp_vision_point2', 'Pioneering Sustainable Community Development')); ?></span>
                    </div>
                </div>
            </div>

            <!-- Vision Imagery -->
            <div class="relative">
                <div class="absolute -top-6 -right-6 w-40 h-40 bg-emerald-100 rounded-3xl -z-10 hidden md:block"></div>

                <?php if ($vision_image_main): ?>
                    <img src="<?php echo htmlspecialchars($vision_image_main); ?>" alt="<?php echo htmlspecialchars($lp('lp_vision_title', 'Our Vision')); ?>" class="w-full h-[320px] md:h-[380px] object-cover rounded-2xl shadow-xl">
                <?php else: ?>
                    <div class="relative w-full h-[320px] md:h-[380px] rounded-2xl shadow-xl overflow-hidden bg-gradient-to-br from-emerald-500 via-emerald-600 to-teal-700 flex items-center justify-center">
                        <div class="absolute inset-0 opacity-20" style="background-image:radial-gradient(circle at 25% 25%, white 0, transparent 45%), radial-gradient(circle at 80% 80%, white 0, transparent 40%);"></div>
                        <i class="fas fa-binoculars text-white/90 text-7xl relative"></i>
                    </div>
                <?php endif; ?>
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
                    <h4 class="font-bold text-gray-800 text-sm mb-1"><?php echo htmlspecialchars($lp('lp_core_protection_title', 'Protection')); ?></h4>
                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($lp('lp_core_protection_desc')); ?></p>
                    <div class="mt-3 w-8 h-0.5 bg-emerald-400 mx-auto rounded-full group-hover:w-12 transition-all duration-300"></div>
                </div>
                
                <div class="group bg-white rounded-xl p-6 text-center border border-gray-100 hover:border-emerald-200 transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                    <div class="w-16 h-16 bg-gradient-to-br from-teal-50 to-teal-100 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-hand-holding-heart text-teal-600 text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm mb-1"><?php echo htmlspecialchars($lp('lp_core_service_title', 'Service')); ?></h4>
                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($lp('lp_core_service_desc')); ?></p>
                    <div class="mt-3 w-8 h-0.5 bg-teal-400 mx-auto rounded-full group-hover:w-12 transition-all duration-300"></div>
                </div>
                
                <div class="group bg-white rounded-xl p-6 text-center border border-gray-100 hover:border-emerald-200 transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                    <div class="w-16 h-16 bg-gradient-to-br from-emerald-100 to-teal-100 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-recycle text-emerald-700 text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm mb-1"><?php echo htmlspecialchars($lp('lp_core_sustainability_title', 'Sustainability')); ?></h4>
                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($lp('lp_core_sustainability_desc')); ?></p>
                    <div class="mt-3 w-8 h-0.5 bg-emerald-500 mx-auto rounded-full group-hover:w-12 transition-all duration-300"></div>
                </div>
                
                <div class="group bg-white rounded-xl p-6 text-center border border-gray-100 hover:border-emerald-200 transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                    <div class="w-16 h-16 bg-gradient-to-br from-teal-50 to-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-users text-teal-700 text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm mb-1"><?php echo htmlspecialchars($lp('lp_core_partnership_title', 'Partnership')); ?></h4>
                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($lp('lp_core_partnership_desc')); ?></p>
                    <div class="mt-3 w-8 h-0.5 bg-teal-500 mx-auto rounded-full group-hover:w-12 transition-all duration-300"></div>
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
                <p class="text-gray-400 text-sm"><?php echo nl2br(htmlspecialchars($lp('lp_footer_about', 'Environmental reporting system for San Isidro, Nueva Ecija. Working together for a cleaner, greener community.'))); ?></p>
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
                    <?php echo htmlspecialchars($lp('lp_footer_address', 'San Isidro, Nueva Ecija')); ?>
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
    
    MapLayers.addControl(map);
    
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
        'high': '#F97316',
        'critical': '#EF4444'
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
                    <a href="<?php echo BASE_URL; ?>index.php?page=track-status&id=${report.token}" 
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

// ============================================
// INTRO SPLASH — GSAP timeline (drop → SIERRA → zoom)
// ============================================
(function () {
    var splash = document.getElementById('intro-splash');
    if (!splash) return;

    var seenKey = 'sierra_intro_seen';

    var cleaned = false;
    function cleanup() {
        if (cleaned) return;
        cleaned = true;
        if (splash.parentNode) splash.parentNode.removeChild(splash);
        document.body.classList.remove('splash-lock');
    }

    // Show the intro only once per browser session
    try {
        if (sessionStorage.getItem(seenKey)) {
            cleanup();
            return;
        }
    } catch (e) {}

    function run() {
        try { sessionStorage.setItem(seenKey, '1'); } catch (e) {}
        var brand = splash.querySelector('.intro-brand');
        var logo = splash.querySelector('.intro-logo, .intro-logo-fallback');
        var chars = splash.querySelectorAll('.tw-char');
        var droplets = splash.querySelectorAll('.tw-droplet');
        if (!brand || !logo || chars.length === 0 || droplets.length === 0) { cleanup(); return; }

        if (!window.gsap) {
            splash.style.transition = 'opacity .5s ease';
            splash.style.opacity = '0';
            setTimeout(cleanup, 550);
            return;
        }

        gsap.set(brand, { opacity: 0, y: -window.innerHeight, scale: .82 });

        var br = brand.getBoundingClientRect();
        var logoRect = logo.getBoundingClientRect();
        var startX = logoRect.left + logoRect.width / 2 - br.left;
        var startY = logoRect.top + logoRect.height / 2 - br.top;

        var targets = [];
        chars.forEach(function (c) {
            var r = c.getBoundingClientRect();
            targets.push({
                x: r.left + r.width / 2 - br.left - startX,
                y: r.top + r.height / 2 - br.top - startY
            });
        });

        droplets.forEach(function (d, i) {
            gsap.set(d, { left: startX, top: startY, xPercent: -50, yPercent: -50 });
        });

        var tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
        tl.to(brand, { opacity: 1, y: 0, scale: 1, duration: .55, ease: 'expo.out' }, 0)
          .fromTo(droplets, { opacity: 0, scale: .8 }, { opacity: .95, scale: 1.15, duration: .14, stagger: { each: .12 } }, .5)
          .to(droplets, { x: function (i) { return targets[i].x; }, y: function (i) { return targets[i].y; }, duration: .8, ease: 'elastic.out(1, .45)', stagger: { each: .12 } }, .64)
          .to(droplets, { scale: 0, opacity: 0, duration: .24, ease: 'power2.in', stagger: { each: .12 } }, 1.28)
          .fromTo(chars, { opacity: 0, filter: 'blur(18px)' }, { opacity: 1, filter: 'blur(0px)', duration: .3, stagger: { each: .12, from: 'start' }, ease: 'power2.inOut' }, .64)
          .to(brand, { scale: 6.8, opacity: 0, duration: 1.05, ease: 'power4.inOut' }, 2.85)
          .to(splash, { opacity: 0, duration: .5, ease: 'power2.inOut' }, 3.35);
        tl.eventCallback('onComplete', cleanup);
    }

    var img = splash.querySelector('img');
    if (img) {
        if (img.complete && img.naturalWidth > 0) {
            run();
        } else {
            img.addEventListener('load', run, { once: true });
            img.addEventListener('error', run, { once: true });
        }
    } else {
        run();
    }

    setTimeout(cleanup, 4400);
})();
</script>

</body>
</html>