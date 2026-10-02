<?php
// views/index.php - PUBLIC HOMEPAGE
// UPDATED: Clean, no leftover test code; uses dynamic system settings

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . 'helpers/SettingsHelper.php';
require_once BASE_PATH . 'helpers/Lang.php';

// Get statistics for homepage
$database = new Database();
$db = $database->getConnection();

// Get total reports count
$stmt = $db->query("SELECT COUNT(*) as total FROM reports");
$total_reports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$stmt = $db->query("SELECT COUNT(*) as total FROM reports WHERE status = 'resolved'");
$resolved_reports = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

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

$landing_barangays = [];
try {
    $landing_barangays = $db->query("SELECT name FROM barangays ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $landing_barangays = [];
}

$landing_categories = [];
try {
    $landing_categories = $db->query("SELECT name FROM categories WHERE is_active = 1 ORDER BY name ASC LIMIT 8")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $landing_categories = [];
}

$landing_response_time = $lp('lp_response_timeline', 'Barangay or MENRO staff usually review new reports within 1 to 3 working days, depending on urgency and available details.');

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
    $hero_subtitle = $lp('lp_hero_subtitle_guest', "See something wrong in your neighborhood? Drainage blockage, illegal dumping, or uncollected garbage?\nReport it here, and your barangay will take action. It's free, fast, and easy.");
}
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
    <link href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/material-symbols.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/material-symbols.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/leaflet/leaflet.css" />
    <script src="<?php echo BASE_URL; ?>assets/vendor/leaflet/leaflet.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/leaflet-stub.js"></script>
    <!-- Network hints for slow connections (map tiles / reverse geocoding) -->
    <link rel="dns-prefetch" href="https://tile.openstreetmap.org">
    <link rel="preconnect" href="https://tile.openstreetmap.org" crossorigin>
    <link rel="preconnect" href="https://tile.openstreetmap.appspot.com" crossorigin>
    <link rel="dns-prefetch" href="https://nominatim.openstreetmap.org">
    <link rel="dns-prefetch" href="https://photon.komoot.io">
    <script src="<?php echo BASE_URL; ?>assets/js/map-layers.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/vendor/gsap/gsap.min.js"></script>
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
            position: relative;
            overflow: hidden;
            animation: statFloatIn .72s cubic-bezier(.2,.7,.2,1) both;
            transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(5, 150, 105, 0.08);
        }
        .stat-card:nth-child(1) { animation-delay: .04s; }
        .stat-card:nth-child(2) { animation-delay: .12s; }
        .stat-card:nth-child(3) { animation-delay: .20s; }
        .stat-card:nth-child(4) { animation-delay: .28s; }
        .stat-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 0%, rgba(255,255,255,.55) 48%, transparent 72%);
            transform: translateX(-120%);
            transition: transform .65s ease;
            pointer-events: none;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            border-color: #059669;
            box-shadow: 0 20px 30px -15px rgba(5, 150, 105, 0.15);
        }
        .stat-card:hover::after { transform: translateX(120%); }
        
        .feature-card {
            transition: all 0.3s ease;
            border: 1px solid #eef2f0;
        }
        .feature-card:hover {
            transform: translateY(-6px);
            border-color: #059669;
            box-shadow: 0 25px 40px -20px rgba(5, 150, 105, 0.2);
        }

        main section[id] {
            transition: transform .32s ease, filter .32s ease;
        }
        main section[id] > .max-w-7xl,
        main section[id] > .container {
            transition: transform .32s ease;
        }
        main section[id]:hover > .max-w-7xl,
        main section[id]:hover > .container {
            transform: translateY(-2px);
        }
        #stats:hover .stat-card {
            box-shadow: 0 22px 40px -26px rgba(5, 150, 105, .28);
        }
        @keyframes statFloatIn {
            from { opacity: 0; transform: translateY(22px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
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
            border-radius: 8px;
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
            animation: landingHeroBgDrift 28s ease-in-out infinite alternate;
        }

        .hero-media-video {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
            transform: scale(1.02);
            animation: heroMediaDrift 18s ease-in-out infinite alternate;
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

        #home::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 38%;
            background: linear-gradient(0deg, rgba(1, 28, 19, .42), transparent);
            pointer-events: none;
            z-index: 1;
        }

        .hero-content-wrap {
            transition: opacity .72s ease, transform .72s cubic-bezier(.22,1,.36,1);
        }
        body:not(.landing-ready) .hero-content-wrap,
        body:not(.landing-ready) .hero-media-video {
            opacity: 0;
        }
        body:not(.landing-ready) .hero-content-wrap {
            transform: translateY(28px);
        }
        body.landing-ready .hero-content-wrap {
            opacity: 1;
            transform: translateY(0);
        }

        @keyframes heroMediaDrift {
            from { transform: scale(1.02) translate3d(-.5%, -.5%, 0); }
            to { transform: scale(1.08) translate3d(.7%, .7%, 0); }
        }
        @keyframes landingHeroBgDrift {
            from { background-position: 50% 50%; }
            to { background-position: 56% 48%; }
        }

        /* Hero bottom corners: square on load, rounded once the page is scrolled */
        #home {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            transition: border-radius 0.5s ease;
            will-change: border-radius;
        }
        body.is-scrolled-landing #home {
            border-bottom-left-radius: 2rem;
            border-bottom-right-radius: 2rem;
        }
        @media (min-width: 640px) {
            body.is-scrolled-landing #home {
                border-bottom-left-radius: 2.5rem;
                border-bottom-right-radius: 2.5rem;
            }
        }

        /* FAQ accordion */
        .faq-item {
            background: #ffffff;
            border: 1px solid #e7efe9;
            border-radius: 1rem;
            overflow: hidden;
        }
        .faq-item[open] {
            border-color: #a7f3d0;
            box-shadow: 0 10px 28px -14px rgba(5, 150, 105, 0.22);
        }
        .faq-q {
            list-style: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.1rem 1.25rem;
            font-weight: 600;
            font-family: 'Manrope', sans-serif;
            font-size: 0.95rem;
            line-height: 1.6;
            color: #1f2937;
        }
        .faq-q::-webkit-details-marker { display: none; }
        .faq-q i {
            color: #059669;
            transition: transform 0.25s ease;
            flex-shrink: 0;
        }
        .faq-item[open] .faq-q i { transform: rotate(180deg); }
        .faq-a {
            padding: 0 1.25rem 1.25rem;
            color: #6b7280;
            font-family: 'Manrope', sans-serif;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .faq-a strong { color: #047857; }
        .faq-item::details-content {
            block-size: 0;
            opacity: 0;
            overflow: hidden;
            transition: block-size .32s ease, opacity .24s ease;
        }
        .faq-item[open]::details-content {
            block-size: auto;
            opacity: 1;
        }

        /* ============================================ */
        /* LANDING NAVBAR (the compiled Tailwind build  */
        /* lacks responsive/hover/opacity utilities)    */
        /* ============================================ */
        .nav-landing {
            background: rgba(255, 255, 255, 0.95);
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid #f3f4f6;
        }
        .nav-landing.shadow-md { box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08); }
        main section[id] { scroll-margin-top: 4.5rem; }
        @media (prefers-reduced-motion: reduce) {
            .stat-card,
            .hero-media-video,
            .hero-bg,
            .hero-content-wrap,
            main section[id],
            main section[id] > .max-w-7xl,
            main section[id] > .container {
                animation: none !important;
                transition: none !important;
            }
            main section[id]:hover > .max-w-7xl,
            main section[id]:hover > .container {
                transform: none !important;
            }
        }

        .nav-link {
            color: #4b5563;
            font-weight: 500;
            white-space: nowrap;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .nav-link:hover { color: #059669; }

.nav-links { display: none; align-items: center; justify-content: center; gap: 1rem; flex: 1 1 auto; min-width: 0; }
@media (min-width: 1280px) { .nav-links { display: flex; gap: 1.35rem; } }

.landing-brand { min-width: 0; flex-shrink: 0; flex-wrap: nowrap; }
.landing-brand-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.landing-brand-badge { white-space: nowrap; }
        .nav-actions { display: flex; align-items: center; gap: 0.6rem; flex-shrink: 0; }
        @media (min-width: 640px) { .nav-actions { gap: 0.75rem; } }

        /* Sign In / Register buttons (navbar) */
        .nav-login-btn,
        .nav-register {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
            font-weight: 700; white-space: nowrap; text-decoration: none; line-height: 1;
            border-radius: 0.7rem; min-height: 2.5rem; padding: 0.5rem 1.05rem; font-size: 0.84rem;
            transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }
        .nav-login-btn {
            color: #047857; background: #ffffff; border: 1.5px solid #cde3d7;
        }
        .nav-login-btn:hover { border-color: #10a37f; background: #f0fdf7; color: #065f46; }
        .nav-login-btn i { font-size: 0.78rem; }
        .nav-register { border: 1.5px solid transparent; box-shadow: 0 6px 16px -8px rgba(5,150,105,0.6); }
        .nav-register i { font-size: 0.78rem; }
        .nav-register:hover { transform: translateY(-1px); box-shadow: 0 10px 22px -8px rgba(5,150,105,0.8); }

        .nav-mobile-auth { display: flex; align-items: center; gap: 0.35rem; }
        @media (min-width: 768px) { .nav-mobile-auth { display: none; } }
        @media (max-width: 1279px) { .nav-desktop-lang { display: none; } }
        @media (max-width: 767px) {
            .landing-brand-name { max-width: clamp(64px, 23vw, 125px); }
            .nav-landing .brand-logo { max-width: 40px; object-fit: contain; }
            .nav-landing .nav-actions { gap: 0.35rem; }
            .nav-hamburger { width: 40px; height: 40px; flex-shrink: 0; }
            .nav-mobile-auth .nav-login-btn { padding: 0.42rem 0.6rem; font-size: 0.74rem; }
            .nav-mobile-auth .nav-register { padding: 0.42rem 0.72rem; font-size: 0.74rem; }
        }
        @media (max-width: 360px) {
            .nav-landing .max-w-7xl { padding-left: 10px; padding-right: 10px; }
            .landing-brand { gap: 5px; }
            .landing-brand-name { max-width: 72px; font-size: 0.85rem; }
            .nav-mobile-auth .nav-login-btn,
            .nav-mobile-auth .nav-register { padding: 0.4rem 0.5rem; font-size: 0.7rem; }
        }

        .nav-auth { display: none; align-items: center; gap: 0.6rem; }
        @media (min-width: 768px) { .nav-auth { display: flex; } }

        .nav-hamburger {
            display: inline-flex; align-items: center; justify-content: center;
            color: #374151; background: #ffffff; transition: all 0.2s ease;
        }
        .nav-hamburger:hover { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .nav-hamburger i { font-size: 1rem; }
        @media (min-width: 1280px) { .nav-hamburger { display: none !important; } }

        .nav-mobile-menu {
            max-height: calc(100vh - 4rem); overflow-y: auto;
            border-top: 1px solid #eef2f1;
            box-shadow: 0 24px 48px -20px rgba(2, 44, 34, 0.35);
        }
        @media (min-width: 1280px) { .nav-mobile-menu { display: none !important; } }

        .nav-mobile-link {
            display: flex; align-items: center; gap: 0.7rem;
            padding: 0.7rem 0.85rem; border-radius: 0.75rem;
            color: #374151; font-weight: 600; font-size: 0.9rem;
            transition: background 0.18s ease, color 0.18s ease;
        }
        .nav-mobile-link i { width: 1.1rem; text-align: center; color: #10a37f; font-size: 0.85rem; }
        .nav-mobile-link:hover, .nav-mobile-link:active { background: #ecfdf5; color: #047857; }

        .nav-mobile-lang {
            display: flex; flex-direction: column; gap: 0.55rem;
            padding: 0.9rem 0.55rem 0.4rem; margin-top: 0.35rem; border-top: 1px solid #eef2f1;
        }
        .nav-mobile-lang-label {
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.09em; text-transform: uppercase; color: #9aa7a1;
            white-space: nowrap;
        }
        @media (min-width: 1280px) { .nav-mobile-lang { display: none; } }

        .nav-menu-cta { display: flex; flex-direction: column; gap: 0.5rem; padding: 0.85rem 0.55rem; border-top: 1px solid #eef2f1; margin-top: 0.35rem; }
        .nav-menu-cta a { justify-content: center; min-height: 46px; padding: 0.7rem 1rem; font-size: 0.9rem; border-radius: 0.8rem; }
        @media (min-width: 640px) { .nav-menu-cta { flex-direction: row; } .nav-menu-cta a { flex: 1 1 0; } }

        <?php echo lang_toggle_css(); ?>

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
            .nav-landing .landing-brand-name { font-size: 0.95rem; }

            /* ── Hero ──
               Bottom corners stay square while the hero is at rest (matching
               desktop) and round once the page is scrolled. Position/layout is
               unchanged — only the visuals are enhanced. */
            #home {
                min-height: 100svh;
                border-radius: 0;
                padding: 0 !important;
                background-position: 54% center;
            }
            body.is-scrolled-landing #home {
                border-bottom-left-radius: 1.5rem;
                border-bottom-right-radius: 1.5rem;
            }
            /* Stronger bottom shading so the hero copy stays readable over the
               busy photo/video on small screens. */
            #home .hero-bg-overlay {
                background: linear-gradient(180deg, rgba(4,40,31,0.30) 0%, rgba(6,78,59,0.30) 38%, rgba(3,22,15,0.78) 100%);
            }
            #home > .relative.z-10 {
                padding-top: 6rem;
                padding-bottom: max(2.5rem, env(safe-area-inset-bottom));
                gap: 1.5rem;
            }
            .hero-eyebrow {
                max-width: 100%;
                font-size: 0.73rem !important;
                padding: 0.55rem 0.8rem;
                margin-bottom: 1.1rem !important;
                line-height: 1.5;
            }
            .hero-heading {
                font-size: clamp(2.1rem, 8.8vw, 3rem) !important;
                line-height: 1.08;
                letter-spacing: -0.025em;
                text-transform: uppercase;
                text-wrap: balance;
                margin-bottom: 1rem !important;
            }
            .hero-headline-second br { display: none; }
            #home .hero-subtitle {
                max-width: 35rem;
                font-size: 0.95rem !important;
                line-height: 1.65;
                margin-bottom: 1.5rem !important;
            }
            .hero-actions { display: grid; grid-template-columns: minmax(0, 1fr); }
            #home .hero-actions a {
                min-height: 44px;
                justify-content: center;
                padding: 0.75rem 1rem;
                font-size: 0.88rem;
                border-radius: 0.75rem;
            }
            /* Stats glass card — compact */
            .glass-card {
                padding: 1rem 1.1rem !important;
                border-radius: 1rem!important;
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
            #about .group.bg-white { padding: 0.85rem 0.6rem !important; border-radius: 0.75rem!important; }
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
            .hero-heading { font-size: clamp(2.5rem, 7vw, 3rem) !important; }
            .feature-card { padding: 1.5rem !important; }
            .feature-card h3.text-xl { font-size: 1.05rem !important; }
            .stat-card .text-3xl { font-size: 1.5rem !important; }
            #about img.w-full.h-\[320px\],
            #about .relative.w-full.h-\[320px\] { height: 220px !important; }
        }
    </style>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/landing-interactions.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/landing-interactions.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/custom-cursor.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/custom-cursor.css'); ?>">
    <script defer src="<?php echo BASE_URL; ?>assets/js/landing-reveal.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/landing-reveal.js'); ?>"></script>
    <script defer src="<?php echo BASE_URL; ?>assets/js/custom-cursor.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/custom-cursor.js'); ?>"></script>
</head>
<body class="bg-[#F5FBF6] splash-lock">

<div class="brand-cursor" aria-hidden="true">
    <div class="brand-cursor-dot"></div>
    <div class="brand-cursor-ringwrap">
        <div class="brand-cursor-ring">
            <span class="brand-cursor-leaf"></span>
        </div>
    </div>
</div>

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[9999] focus:px-4 focus:py-2 focus:bg-emerald-600 focus:text-white focus:rounded-lg">Skip to main content</a>

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
<nav class="fixed w-full z-50 nav-landing">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="landing-brand flex items-center gap-2">
                <?php if ($logo_url): ?>
                    <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($system_name); ?> Logo" class="brand-logo">
                <?php else: ?>
                    <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-leaf text-white text-sm"></i>
                    </div>
                <?php endif; ?>
                <span class="landing-brand-name text-xl font-bold text-gray-800"><?php echo htmlspecialchars($system_name); ?></span>
                <span class="landing-brand-badge text-xs text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full hidden sm:inline-block">San Isidro</span>
            </div>
            
            <div class="nav-links">
                <a href="#home" class="nav-link"><?php echo t('Home'); ?></a>
                <a href="#features" class="nav-link"><?php echo t('How It Works'); ?></a>
                <a href="#map-section" class="nav-link"><?php echo t('Map'); ?></a>
                <a href="#stats" class="nav-link"><?php echo t('Stats'); ?></a>
                <a href="#about" class="nav-link"><?php echo t('About LGU'); ?></a>
                <a href="#faq" class="nav-link"><?php echo t('FAQ'); ?></a>
            </div>
            
            <div class="nav-actions">
                <div class="nav-desktop-lang"><?php echo lang_icon_widget(); ?></div>
                <div class="nav-mobile-auth">
                    <?php if($isLoggedIn): ?>
                        <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" class="nav-dashboard-btn"><i class="fas fa-tachometer-alt" aria-hidden="true"></i><?php echo t('Dashboard'); ?></a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>index.php?page=login" class="nav-login-btn"><i class="fas fa-sign-in-alt" aria-hidden="true"></i><?php echo t('Sign In'); ?></a>
                        <a href="<?php echo BASE_URL; ?>index.php?page=register" class="nav-register btn-primary text-white"><i class="fas fa-user-plus" aria-hidden="true"></i><?php echo t('Register'); ?></a>
                    <?php endif; ?>
                </div>
                <div class="nav-auth">
                <?php if($isLoggedIn): ?>
                    <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" class="nav-dashboard-btn">
                        <i class="fas fa-tachometer-alt" aria-hidden="true"></i><?php echo t('Dashboard'); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>index.php?page=login" class="nav-login-btn"><i class="fas fa-sign-in-alt"></i><?php echo t('Sign In'); ?></a>
                    <a href="<?php echo BASE_URL; ?>index.php?page=register" class="nav-register btn-primary text-white">
                        <i class="fas fa-user-plus"></i><?php echo t('Register'); ?>
                    </a>
                <?php endif; ?>
                </div>
                <button type="button" id="navToggle" class="nav-hamburger w-11 h-11 rounded-xl border border-gray-200 text-gray-600 items-center justify-center transition" aria-label="Toggle navigation menu" aria-controls="mobileNavMenu" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile navigation menu (below lg) -->
    <div id="mobileNavMenu" class="nav-mobile-menu hidden bg-white border-t border-gray-100 shadow-xl">
        <div class="px-3 sm:px-5 py-3">
            <div class="space-y-1">
                <a href="#home" class="nav-mobile-link"><i class="fas fa-home"></i><?php echo t('Home'); ?></a>
                <a href="#features" class="nav-mobile-link"><i class="fas fa-cogs"></i><?php echo t('How It Works'); ?></a>
                <a href="#map-section" class="nav-mobile-link"><i class="fas fa-map-marked-alt"></i><?php echo t('Map'); ?></a>
                <a href="#stats" class="nav-mobile-link"><i class="fas fa-chart-bar"></i><?php echo t('Stats'); ?></a>
                <a href="#about" class="nav-mobile-link"><i class="fas fa-landmark"></i><?php echo t('About LGU'); ?></a>
                <a href="#faq" class="nav-mobile-link"><i class="fas fa-question-circle"></i><?php echo t('FAQ'); ?></a>
            </div>

            <div class="nav-mobile-lang">
                <span class="nav-mobile-lang-label"><?php echo t('Language'); ?></span>
                <?php echo lang_segmented_widget(); ?>
            </div>

            <div class="nav-menu-cta">
                <?php if($isLoggedIn): ?>
                    <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" class="nav-dashboard-btn">
                        <i class="fas fa-tachometer-alt" aria-hidden="true"></i><?php echo t('Dashboard'); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>index.php?page=login" class="nav-login-btn"><i class="fas fa-sign-in-alt"></i><?php echo t('Sign In'); ?></a>
                    <a href="<?php echo BASE_URL; ?>index.php?page=register" class="nav-register btn-primary text-white"><i class="fas fa-user-plus"></i><?php echo t('Register'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main id="main-content" tabindex="-1" role="main">
<!-- ============================================ -->
<!-- SECTION 1: HOME (HERO) -->
<!-- ============================================ -->
<section id="home" class="relative min-h-screen overflow-hidden hero-bg flex flex-col justify-end" style="<?php echo $hero_bg_style; ?>">
    <?php if ($show_hero_video): ?>
        <video class="hero-media-video" id="heroVideo" autoplay muted loop playsinline src="<?php echo htmlspecialchars($hero_bg_video); ?>"></video>
    <?php endif; ?>
    <?php if ($show_hero_overlay): ?>
        <div class="absolute inset-0 hero-bg-overlay"></div>
    <?php endif; ?>
        <div class="hero-content-wrap relative z-10 flex flex-col gap-10 max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pb-24 sm:pb-28 pt-24">

            <!-- Left: eyebrow + heading + subtitle + CTAs (left aligned) -->
            <div class="max-w-3xl animate-fade-up">
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
                        <span class="hero-headline-second"><?php
    $hero_line2 = nl2br(htmlspecialchars($lp('lp_hero_headline_2', "pangalagaan ang\nSan Isidro.")));
    $hero_line2 = str_ireplace('san isidro', 'San Isidro', $hero_line2);
    echo str_replace(['<br />', 'San Isidro'], ['<br /> ', 'San&nbsp;Isidro'], $hero_line2);
?></span>
                    <?php endif; ?>
                </h1>

                <p class="hero-subtitle text-white/85 text-base sm:text-lg leading-relaxed mb-8">
                    <?php echo str_replace('<br />', '<br /> ', nl2br(htmlspecialchars($hero_subtitle))); ?>
                </p>

                <div class="hero-actions flex flex-wrap gap-3 animate-fade-up delay-2">
                    <?php if($isLoggedIn && ($user_role === 'barangay_official' || $user_role === 'admin')): ?>
                        <a href="<?php echo BASE_URL; ?>index.php?page=verify-reports" class="btn-light px-6 py-3 rounded-xl font-semibold flex items-center gap-2">
                            <i class="fas fa-check-double"></i> <?php echo t('Manage Reports'); ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>index.php?page=announcements" class="btn-outline-light px-6 py-3 rounded-xl font-semibold flex items-center gap-2">
                            <i class="fas fa-bullhorn"></i> <?php echo t('Post Announcement'); ?>
                        </a>
                    <?php elseif($isLoggedIn): ?>
                        <a href="<?php echo BASE_URL; ?>index.php?page=submit-report" class="btn-light px-6 py-3 rounded-xl font-semibold flex items-center gap-2">
                            <i class="fas fa-plus-circle"></i> <?php echo t('Report an Issue'); ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>index.php?page=my-reports" class="btn-outline-light px-6 py-3 rounded-xl font-semibold flex items-center gap-2">
                            <i class="fas fa-list"></i> <?php echo t('My Reports'); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>index.php?page=register" class="btn-light px-6 py-3 rounded-xl font-semibold flex items-center gap-2">
                            <i class="fas fa-user-plus"></i> <?php echo t('Create Free Account'); ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>index.php?page=login" class="btn-outline-light px-6 py-3 rounded-xl font-semibold flex items-center gap-2">
                            <i class="fas fa-sign-in-alt"></i> <?php echo t('Sign In'); ?>
                        </a>
                    <?php endif; ?>
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
        <div class="text-center lp-steps-head-wrap">
            <span class="text-emerald-600 text-sm font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($lp('lp_how_kicker', 'How It Works')); ?></span>
            <div class="section-divider"></div>
            <h2 class="lp-steps-head">
                <?php echo htmlspecialchars($lp('lp_how_heading', 'Three simple steps')); ?>
                <em><?php echo htmlspecialchars($lp('lp_how_heading_accent', 'that need no introduction')); ?></em>
            </h2>
            <div class="lp-steps-pill">
                <i class="fas fa-seedling"></i>
                <?php echo htmlspecialchars($lp('lp_how_pill', 'See how a simple report turns into real action for your community')); ?>
            </div>
        </div>
        
        <div class="lp-steps-track">
            <div class="lp-step">
                <span class="lp-step-num">01</span>
                <span class="lp-step-icon"><i class="fas fa-user-plus"></i></span>
                <h3 class="lp-step-title"><?php echo htmlspecialchars($lp('lp_how_step1_title', 'Join the Community')); ?></h3>
                <p class="lp-step-desc"><?php echo nl2br(htmlspecialchars($lp('lp_how_step1_desc'))); ?></p>
                <div class="lp-step-media">
                    <div class="lp-mock">
                        <div class="lp-mock-top"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
                        <div class="lp-mock-row">
                            <span class="lp-mock-ava"></span>
                            <div class="lp-mock-col"><span class="lp-mock-line w80 solid"></span><span class="lp-mock-line w45"></span></div>
                        </div>
                        <div class="lp-mock-row">
                            <span class="lp-mock-ava alt"></span>
                            <div class="lp-mock-col"><span class="lp-mock-line w70 solid"></span><span class="lp-mock-line w50"></span></div>
                        </div>
                        <div class="lp-mock-cta">Create account</div>
                    </div>
                </div>
                <?php if(!$isLoggedIn): ?>
                <a href="<?php echo BASE_URL; ?>index.php?page=register" class="lp-step-link">Sign up now <i class="fas fa-arrow-right"></i></a>
                <?php endif; ?>
            </div>
            
            <div class="lp-step">
                <span class="lp-step-num">02</span>
                <span class="lp-step-icon"><i class="fas fa-map-location-dot"></i></span>
                <h3 class="lp-step-title"><?php echo htmlspecialchars($lp('lp_how_step2_title', 'Report the Problem')); ?></h3>
                <p class="lp-step-desc"><?php echo nl2br(htmlspecialchars($lp('lp_how_step2_desc'))); ?></p>
                <div class="lp-step-media">
                    <div class="lp-mock">
                        <div class="lp-mock-map"><span class="lp-mock-pin"><i class="fas fa-map-marker-alt"></i></span></div>
                        <div class="lp-mock-field">Garbage pile near the creek</div>
                        <div class="lp-mock-cta">Submit report</div>
                    </div>
                </div>
                <?php if(!$isLoggedIn): ?>
                <a href="<?php echo BASE_URL; ?>index.php?page=login" class="lp-step-link">Login to report <i class="fas fa-arrow-right"></i></a>
                <?php endif; ?>
            </div>
            
            <div class="lp-step">
                <span class="lp-step-num">03</span>
                <span class="lp-step-icon"><i class="fas fa-road"></i></span>
                <h3 class="lp-step-title"><?php echo htmlspecialchars($lp('lp_how_step3_title', 'Track the Action')); ?></h3>
                <p class="lp-step-desc"><?php echo nl2br(htmlspecialchars($lp('lp_how_step3_desc'))); ?></p>
                <div class="lp-step-media">
                    <div class="lp-mock">
                        <div class="lp-mock-row"><div class="lp-mock-col"><span class="lp-mock-line w60 solid"></span></div></div>
                        <div class="lp-mock-track"><span style="width:66%"></span></div>
                        <ul class="lp-mock-timeline">
                            <li class="done">Submitted</li>
                            <li class="done">Verified by barangay</li>
                            <li>Resolved</li>
                        </ul>
                    </div>
                </div>
                <?php if(!$isLoggedIn): ?>
                <a href="<?php echo BASE_URL; ?>index.php?page=login" class="lp-step-link">Login to track <i class="fas fa-arrow-right"></i></a>
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
        <div class="text-center lp-section-head-wrap">
            <span class="text-emerald-600 text-sm font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($lp('lp_map_kicker', 'Live Map')); ?></span>
            <div class="section-divider"></div>
            <h2 class="lp-section-head">
                <?php echo htmlspecialchars($lp('lp_map_heading', 'Environmental Reports Map')); ?>
                <em><?php echo htmlspecialchars($lp('lp_map_heading_accent', 'see every concern in one place')); ?></em>
            </h2>
            <p class="text-gray-500 mt-2 max-w-2xl mx-auto"><?php echo htmlspecialchars($lp('lp_map_intro', 'See where environmental issues are being reported across San Isidro.')); ?></p>
            <?php if(!$isLoggedIn): ?>
            <p class="text-xs text-gray-400 mt-2">
                <i class="fas fa-info-circle mr-1"></i>
                Login to view report details and submit your own reports.
            </p>
            <?php endif; ?>
        </div>
        
        <div class="lp-live-map-card bg-white rounded-2xl shadow-sm border border-emerald-50 p-4">
            <div class="lp-live-map-top">
                <span><i class="fas fa-location-crosshairs"></i> San Isidro live view</span>
                <strong><?php echo count($reports_for_map); ?> active pins</strong>
            </div>
            <div class="lp-map-frame">
                <div id="map"></div>
            </div>
            <div class="lp-map-legend flex flex-wrap gap-3 mt-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full" style="background:#16A34A;"></span> Low Risk</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full" style="background:#F59E0B;"></span> Medium Risk</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full" style="background:#EA580C;"></span> High Risk</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full" style="background:#DC2626;"></span> Critical Risk</span>
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
        <div class="text-center lp-section-head-wrap">
            <span class="text-emerald-600 text-sm font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($lp('lp_stats_kicker', 'Community Impact')); ?></span>
            <div class="section-divider"></div>
            <h2 class="lp-section-head">
                <?php echo htmlspecialchars($lp('lp_stats_heading', 'San Isidro Statistics')); ?>
                <em><?php echo htmlspecialchars($lp('lp_stats_heading_accent', 'one community, one mission')); ?></em>
            </h2>
            <p class="text-gray-500 mt-2 max-w-2xl mx-auto"><?php echo htmlspecialchars($lp('lp_stats_intro', "Together, we're making a difference in our community.")); ?></p>
        </div>
        
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 stat-cards">
            <div class="stat-card bg-gradient-to-br from-emerald-50 to-teal-50 rounded-2xl p-6 text-center">
                <div class="text-3xl font-bold text-emerald-600 lp-stat-number" data-count="<?php echo (int)$san_isidro_stats['barangays']; ?>"><?php echo number_format($san_isidro_stats['barangays']); ?></div>
                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($lp('lp_stat_barangays_label', 'Barangays')); ?></p>
                <p class="text-xs text-gray-400 mt-2"><?php echo htmlspecialchars($lp('lp_stat_barangays_sub')); ?></p>
            </div>
            <div class="stat-card bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-2xl p-6 text-center">
                <div class="text-3xl font-bold text-emerald-700 lp-stat-number" data-count="<?php echo (int)$san_isidro_stats['population']; ?>"><?php echo number_format($san_isidro_stats['population']); ?></div>
                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($lp('lp_stat_population_label', 'Population')); ?></p>
                <p class="text-xs text-gray-400 mt-2"><?php echo htmlspecialchars($lp('lp_stat_population_sub')); ?></p>
            </div>
            <div class="stat-card bg-gradient-to-br from-teal-50 to-emerald-100 rounded-2xl p-6 text-center">
                <div class="text-3xl font-bold text-teal-700 lp-stat-number" data-count="<?php echo (int)$san_isidro_stats['households']; ?>"><?php echo number_format($san_isidro_stats['households']); ?></div>
                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($lp('lp_stat_households_label', 'Households')); ?></p>
                <p class="text-xs text-gray-400 mt-2"><?php echo htmlspecialchars($lp('lp_stat_households_sub')); ?></p>
            </div>
            <div class="stat-card bg-gradient-to-br from-emerald-100 to-white rounded-2xl p-6 text-center">
                <div class="text-3xl font-bold text-emerald-700 lp-stat-number" data-count="<?php echo (int)$total_reports; ?>"><?php echo number_format($total_reports); ?></div>
                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($lp('lp_stat_reports_label', 'Reports Submitted')); ?></p>
                <p class="text-xs text-gray-400 mt-2"><?php echo htmlspecialchars($lp('lp_stat_reports_sub')); ?></p>
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
        
        <div class="text-center mb-14 lp-section-head-wrap">
            <div class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-100 px-4 py-1.5 rounded-full mb-4">
                <i class="fas fa-building text-emerald-600 text-xs"></i>
                <span class="text-emerald-700 text-xs font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($lp('lp_about_kicker', 'About LGU')); ?></span>
            </div>
            <h2 class="lp-section-head">
                <?php echo htmlspecialchars($lp('lp_about_heading', 'Municipal Environment & Natural Resources Office')); ?>
                <em><?php echo htmlspecialchars($lp('lp_about_heading_accent', 'working for a greener San Isidro')); ?></em>
            </h2>
            <p class="text-gray-500 max-w-2xl mx-auto">
                <?php echo htmlspecialchars($lp('lp_about_subtitle', "Committed to protecting and preserving San Isidro's environment for future generations.")); ?>
            </p>
            <div class="w-20 h-1 bg-gradient-to-r from-emerald-500 to-teal-600 mx-auto mt-4 rounded-full"></div>
        </div>
        
        <!-- Our Mission -->
        <div class="grid md:grid-cols-2 gap-10 lg:gap-16 items-center mb-24 lp-mission-split">

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
<!-- SECTION 6: BEFORE YOU REPORT -->
<!-- ============================================ -->
<section id="report-guide" class="py-20 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center lp-section-head-wrap">
            <span class="text-emerald-600 text-sm font-semibold uppercase tracking-wider">Before You Report</span>
            <div class="section-divider"></div>
            <h2 class="lp-section-head">
                What You Need To Know
                <em>clear steps before sending a concern</em>
            </h2>
            <p class="text-gray-500 mt-2 max-w-2xl mx-auto">A short guide to coverage, privacy, urgency, and what happens after you submit.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-4 mb-8">
            <div class="lp-info-card">
                <span class="lp-info-icon"><i class="fas fa-map-marked-alt"></i></span>
                <h3>Barangay Coverage</h3>
                <p>Reports are routed to the barangay connected to the pinned location, with MENRO support for escalated or municipal-level concerns.</p>
                <div class="lp-chip-list">
                    <?php foreach (array_slice($landing_barangays, 0, 9) as $barangay_name): ?>
                        <span><?php echo htmlspecialchars($barangay_name); ?></span>
                    <?php endforeach; ?>
                    <?php if (count($landing_barangays) > 9): ?>
                        <span>+<?php echo count($landing_barangays) - 9; ?> more</span>
                    <?php elseif (!$landing_barangays): ?>
                        <span>San Isidro barangays</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lp-info-card">
                <span class="lp-info-icon"><i class="fas fa-list-check"></i></span>
                <h3>Report Categories</h3>
                <p>Choose the closest category so staff can assess the issue faster and assign the right response.</p>
                <div class="lp-chip-list">
                    <?php foreach ($landing_categories ?: ['Illegal dumping', 'Flooding', 'Drainage blockage', 'Air or water pollution'] as $category_name): ?>
                        <span><?php echo htmlspecialchars($category_name); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="lp-info-card">
                <span class="lp-info-icon"><i class="fas fa-clock"></i></span>
                <h3>Review Timeline</h3>
                <p><?php echo htmlspecialchars($landing_response_time); ?></p>
                <div class="lp-mini-flow">
                    <span>Submitted</span>
                    <i class="fas fa-arrow-right"></i>
                    <span>Reviewed</span>
                    <i class="fas fa-arrow-right"></i>
                    <span>Resolved</span>
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-4 gap-4">
            <div class="lp-detail-card">
                <i class="fas fa-user-shield"></i>
                <h3>Privacy</h3>
                <p>Your identity is not shown publicly. Barangay and MENRO staff can view reporter details only for verification and coordination.</p>
            </div>
            <div class="lp-detail-card">
                <i class="fas fa-triangle-exclamation"></i>
                <h3>Urgent Issues</h3>
                <p>For immediate danger, call local emergency services first. You can still file a report after the urgent situation is safe.</p>
            </div>
            <div class="lp-detail-card">
                <i class="fas fa-location-dot"></i>
                <h3>Nearby Reports</h3>
                <p>If a similar report already exists near your pinned location, the system may ask you to support the existing report instead.</p>
            </div>
            <div class="lp-detail-card">
                <i class="fas fa-headset"></i>
                <h3>Need Help?</h3>
                <p>Email <?php echo htmlspecialchars($contact_email); ?> or call <?php echo htmlspecialchars($emergency_hotline); ?> if you cannot register, log in, or submit.</p>
            </div>
        </div>

        <div class="lp-impact-strip">
            <span><strong><?php echo number_format($resolved_reports); ?></strong> resolved reports recorded</span>
            <span><strong><?php echo number_format($total_reports); ?></strong> total community reports submitted</span>
            <span><strong><?php echo count($reports_for_map); ?></strong> recent map pins visible</span>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- SECTION 7: FAQ -->
<!-- ============================================ -->
<section id="faq" class="py-20 bg-[#F5FBF6]">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center lp-section-head-wrap">
            <span class="text-emerald-600 text-sm font-semibold uppercase tracking-wider">FAQ</span>
            <div class="section-divider"></div>
            <h2 class="lp-section-head">
                Frequently Asked Questions
                <em><?php echo htmlspecialchars($lp('lp_faq_heading_accent', 'answers before you report')); ?></em>
            </h2>
            <p class="text-gray-500 mt-2 max-w-2xl mx-auto">Everything you need to know about reporting environmental issues in San Isidro.</p>
        </div>

        <div class="max-w-3xl mx-auto space-y-3">
            <details class="faq-item" open>
                <summary class="faq-q">
                    <span>How do I report an environmental issue?</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">Create a free account, then click <strong>Submit Report</strong> in your dashboard. Choose the category, take a photo, describe the issue, and pin the exact location on the map. Your barangay will review it and take action.</div>
            </details>

            <details class="faq-item">
                <summary class="faq-q">
                    <span>Who can report environmental issues?</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">Any citizen, whether a resident of San Isidro or not. You only need a free account. Your report is anonymous to the public but visible to your barangay and MENRO so they can act on it.</div>
            </details>

            <details class="faq-item">
                <summary class="faq-q">
                    <span>What types of issues can I report?</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">Common reports include illegal dumping, uncollected garbage, drainage blockage, flooding, burning, air or water pollution, and other environmental concerns affecting your neighborhood.</div>
            </details>

            <details class="faq-item">
                <summary class="faq-q">
                    <span>How do I track the status of my report?</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">Open <strong>My Reports</strong> from your sidebar. Every report shows its current status &#8212; Pending, Verified, In Progress, Resolved, or Rejected. You also receive in-app notifications when your report's status changes.</div>
            </details>

            <details class="faq-item">
                <summary class="faq-q">
                    <span>Do I need to include a photo?</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">A photo is highly recommended because it helps your barangay assess the issue faster. You can take a photo with your camera or choose one from your gallery when submitting the report.</div>
            </details>

            <details class="faq-item">
                <summary class="faq-q">
                    <span>What happens after I submit a report?</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">Your report goes to the appropriate barangay or MENRO staff. They verify the report, assign it, and work to resolve it. You will be notified at every step until the issue is marked resolved.</div>
            </details>

            <details class="faq-item">
                <summary class="faq-q">
                    <span>How long does review usually take?</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a"><?php echo htmlspecialchars($landing_response_time); ?> Reports with clear photos, descriptions, and pinned locations are easier to review.</div>
            </details>

            <details class="faq-item">
                <summary class="faq-q">
                    <span>What if another person already reported the same issue?</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">If the system detects a nearby similar report, you may support the existing report instead of creating a duplicate. This helps staff count how many people are affected by the same concern.</div>
            </details>

            <details class="faq-item">
                <summary class="faq-q">
                    <span>Is my personal information public?</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">No. Public viewers do not see your personal details. Authorized barangay and MENRO staff can view reporter information only when they need to verify, coordinate, or resolve a report.</div>
            </details>

            <details class="faq-item">
                <summary class="faq-q">
                    <span>What should I do for emergencies?</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">For immediate danger, contact local emergency responders first. You can call <?php echo htmlspecialchars($emergency_hotline); ?> or email <?php echo htmlspecialchars($contact_email); ?> if you need help with the reporting system.</div>
            </details>
        </div>
    </div>
</section>

</main>

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
                    <li><a href="<?php echo BASE_URL; ?>index.php#home" class="hover:text-emerald-400 transition">Home</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php#features" class="hover:text-emerald-400 transition">How It Works</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php#map-section" class="hover:text-emerald-400 transition">Live Map</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php#stats" class="hover:text-emerald-400 transition">Stats</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php#about" class="hover:text-emerald-400 transition">About LGU</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Support</h4>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="<?php echo BASE_URL; ?>index.php#faq" class="hover:text-emerald-400 transition">FAQ</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php?page=privacy-policy" class="hover:text-emerald-400 transition">Privacy Policy</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php?page=terms-of-service" class="hover:text-emerald-400 transition">Terms of Service</a></li>
                    <li><a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="hover:text-emerald-400 transition">Contact Us</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Connect</h4>
                <div class="flex flex-wrap gap-3">
                    <a href="#" class="px-3 h-10 gap-2 text-sm bg-gray-800 rounded-lg flex items-center justify-center hover:bg-emerald-600 transition">
                        <i class="material-symbols ms-public" aria-hidden="true"></i><span>Facebook</span>
                    </a>
                    <a href="#" class="px-3 h-10 gap-2 text-sm bg-gray-800 rounded-lg flex items-center justify-center hover:bg-emerald-600 transition">
                        <i class="material-symbols ms-chat" aria-hidden="true"></i><span>Twitter</span>
                    </a>
                    <a href="#" class="px-3 h-10 gap-2 text-sm bg-gray-800 rounded-lg flex items-center justify-center hover:bg-emerald-600 transition">
                        <i class="material-symbols ms-photo_camera" aria-hidden="true"></i><span>Instagram</span>
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
// MOBILE NAV TOGGLE
// ============================================
(function () {
    var btn = document.getElementById('navToggle');
    var menu = document.getElementById('mobileNavMenu');
    if (!btn || !menu) return;
    function close() {
        menu.classList.add('hidden');
        btn.setAttribute('aria-expanded', 'false');
        btn.innerHTML = '<i class="fas fa-bars"></i>';
    }
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = !menu.classList.toggle('hidden');
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        btn.innerHTML = '<i class="fas ' + (isOpen ? 'fa-times' : 'fa-bars') + '"></i>';
    });
    menu.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', close);
    });
    document.addEventListener('click', function (e) {
        if (!btn.contains(e.target) && !menu.contains(e.target)) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
})();

// ============================================
// HERO SCROLL CORNERS
// ============================================
function heroScrollState() {
    if (window.scrollY > 40) {
        document.body.classList.add('is-scrolled-landing');
    } else {
        document.body.classList.remove('is-scrolled-landing');
    }
}
window.addEventListener('scroll', heroScrollState, { passive: true });
heroScrollState();

// ============================================
// LANGUAGE DROPDOWNS
// ============================================
<?php echo lang_toggle_js(); ?>

// ============================================
// MAP
// ============================================
const mapReports = <?php echo json_encode($reports_for_map); ?>;

function initMap() {
    const map = L.map('map').setView([15.3092, 120.9033], 13);
    
    MapLayers.addControl(map);
    
    // Add San Isidro boundary
    <?php 
    $geojson_file = BASE_PATH . 'geojson/sanisidro.geojson';
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
                    L.polygon(coords, MapLayers.whiteCasingStyle(1.5)).addTo(map);
                    L.polygon(coords, MapLayers.dashedBoundaryStyle(1.5)).bindTooltip('San Isidro, Nueva Ecija', { sticky: true }).addTo(map);
                }
            }
        } catch(e) {
            console.log('Boundary not loaded');
        }
    }
    
    // Add markers
    const riskColors = {
        'low': '#16A34A',
        'medium': '#F59E0B',
        'high': '#EA580C',
        'critical': '#DC2626'
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
            const color = riskColors[risk] || '#16A34A';
            
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
                       style="display: inline-block; margin-top: 8px; padding: 4px 12px; background: #059669; color: white; border-radius: 8px; font-size: 12px; text-decoration: none; font-weight: 500;">
                        View Details
                    </a>
                </div>
            `;
            <?php else: ?>
            const popupContent = `
                <div style="font-family: Manrope, sans-serif; min-width: 180px; padding: 4px; text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 8px; color: #059669;"><i class="fas fa-lock"></i></div>
                    <p style="font-size: 14px; font-weight: 600; color: #1e293b;">Login to view details</p>
                    <p style="font-size: 12px; color: #64748b; margin: 4px 0 8px;">Sign in to see full report information</p>
                    <a href="<?php echo BASE_URL; ?>index.php?page=login" 
                       style="display: inline-block; padding: 6px 16px; background: #059669; color: white; border-radius: 8px; font-size: 12px; text-decoration: none; font-weight: 500;">
                        Login
                    </a>
                    <a href="<?php echo BASE_URL; ?>index.php?page=register" 
                       style="display: inline-block; padding: 6px 16px; margin-left: 4px; background: transparent; color: #059669; border: 1px solid #059669; border-radius: 8px; font-size: 12px; text-decoration: none; font-weight: 500;">
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
// LANDING STATS COUNT-UP
// ============================================
(function () {
    var numbers = Array.prototype.slice.call(document.querySelectorAll('.lp-stat-number[data-count]'));
    if (!numbers.length) return;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function formatNumber(value) {
        return Math.round(value).toLocaleString();
    }
    function animateNumber(el) {
        if (el.dataset.counted === '1') return;
        el.dataset.counted = '1';
        var target = parseInt(el.getAttribute('data-count'), 10) || 0;
        if (reduceMotion || target === 0) {
            el.textContent = formatNumber(target);
            return;
        }
        var startTime = null;
        var duration = Math.min(1800, Math.max(850, String(target).length * 190));
        function tick(timestamp) {
            if (startTime === null) startTime = timestamp;
            var progress = Math.min(1, (timestamp - startTime) / duration);
            var eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = formatNumber(target * eased);
            if (progress < 1) window.requestAnimationFrame(tick);
            else el.textContent = formatNumber(target);
        }
        el.textContent = '0';
        window.requestAnimationFrame(tick);
    }
    if (!window.IntersectionObserver) {
        numbers.forEach(animateNumber);
        return;
    }
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            numbers.forEach(animateNumber);
            observer.disconnect();
        });
    }, { threshold: .35 });
    var stats = document.getElementById('stats');
    observer.observe(stats || numbers[0]);
}());

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
// RESOLUTION RATE ANIMATION
// ============================================
const resolutionBar = document.querySelector('.h-full.bg-gradient-to-r');
if (resolutionBar && window.IntersectionObserver && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                observer.unobserve(entry.target);
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
    function revealLanding() {
        document.body.classList.add('landing-ready');
    }
    function cleanup() {
        if (cleaned) return;
        cleaned = true;
        if (splash.parentNode) splash.parentNode.removeChild(splash);
        document.body.classList.remove('splash-lock');
        revealLanding();
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

<!-- FAQ: exclusive accordion — only one item open at a time -->
<script>
(function () {
    'use strict';
    var faqs = document.querySelectorAll('#faq details.faq-item');
    if (!faqs.length) return;
    Array.prototype.forEach.call(faqs, function (item) {
        item.addEventListener('toggle', function () {
            if (!item.open) return;
            Array.prototype.forEach.call(faqs, function (other) {
                if (other !== item && other.open) other.open = false;
            });
        });
    });
})();
</script>

<script src="<?php echo BASE_URL; ?>assets/js/fetch-timeout.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/modal-a11y.js"></script>
<script>
/* Stats rows: 3 or fewer cards fit the screen; more than 3 scroll horizontally.
   Self-contained (injects its own CSS) so a cached stylesheet can't break it. */
(function () {
    'use strict';
    var CSS = '@media(max-width:767px){' +
        '.stat-cards{display:flex !important;flex-wrap:nowrap !important;gap:10px;min-width:0;max-width:100%;overflow-x:auto !important;overflow-y:hidden;-webkit-overflow-scrolling:touch;scroll-snap-type:x proximity;padding-bottom:6px;overscroll-behavior-x:contain;}' +
        '.stat-cards>*{box-sizing:border-box;flex:0 0 165px !important;width:165px !important;min-width:0;scroll-snap-align:start;}' +
        '.stat-cards.sf-fit{display:grid !important;overflow:visible !important;padding-bottom:0;scroll-snap-type:none;}' +
        '.stat-cards.sf-fit>*{flex:1 1 auto !important;width:auto !important;min-width:0;}' +
        '.stat-cards.sf-1{grid-template-columns:minmax(0,1fr) !important;}' +
        '.stat-cards.sf-2{grid-template-columns:repeat(2,minmax(0,1fr)) !important;}' +
        '.stat-cards.sf-3{grid-template-columns:repeat(3,minmax(0,1fr)) !important;}' +
        '}';
    function inject() {
        if (document.getElementById('stat-cards-css')) return;
        var s = document.createElement('style');
        s.id = 'stat-cards-css';
        s.appendChild(document.createTextNode(CSS));
        (document.head || document.documentElement).appendChild(s);
    }
    function applyStatFit() {
        inject();
        var rows = document.querySelectorAll('.stat-cards');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var n = row.children.length;
            row.classList.remove('sf-fit', 'sf-1', 'sf-2', 'sf-3');
            if (n >= 1 && n <= 3) { row.classList.add('sf-fit', 'sf-' + n); }
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', applyStatFit);
    else applyStatFit();
    window.addEventListener('resize', applyStatFit);
})();
</script>
<?php echo lang_apply_js(); ?>
</body>
</html>
