<!-- views/layouts/header.php - REDESIGNED WITH DYNAMIC SYSTEM SETTINGS -->
<?php
// Include SettingsHelper for dynamic branding
require_once BASE_PATH . 'helpers/SettingsHelper.php';

$system_name = SettingsHelper::get('system_name', 'EnviroTrack');
$lgu_logo = SettingsHelper::get('lgu_logo', '');
$logo_url = $lgu_logo ? BASE_URL . $lgu_logo : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($logo_url): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($logo_url); ?>">
    <?php endif; ?>
    <title><?php echo htmlspecialchars($system_name); ?> - Environmental Reporting System</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * {
            font-family: 'Manrope', sans-serif;
        }
        
        /* Custom Veridian Horizon Theme */
        :root {
            --veridian-green: #10A37F;
            --veridian-dark: #0D8568;
            --veridian-light: #D1FAE5;
            --soft-mint: #F5FBF6;
            --amber: #FF8A00;
            --cool-gray: #D1D5DB;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--soft-mint);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--veridian-green);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--veridian-dark);
        }
        
        /* Glassmorphism Base */
        .glass-nav {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(16, 163, 127, 0.1);
        }
        
        /* Card Hover Effects */
        .stat-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(16, 163, 127, 0.1);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            border-color: var(--veridian-green);
            box-shadow: 0 20px 25px -12px rgba(16, 163, 127, 0.15);
        }
        
        /* Button Styles */
        .btn-primary {
            background-color: var(--veridian-green);
            transition: all 0.2s ease;
            transform: scale(1);
        }
        .btn-primary:hover {
            background-color: var(--veridian-dark);
            transform: scale(0.98);
        }
        .btn-primary:active {
            transform: scale(0.95);
        }
        
        /* Form Input Focus */
        .form-input:focus {
            border-color: var(--veridian-green);
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.2);
            outline: none;
        }
        
        /* Status Badges */
        .badge-low {
            background-color: #D1FAE5;
            color: var(--veridian-green);
        }
        .badge-high {
            background-color: #FEF3C7;
            color: var(--amber);
        }
        .badge-pending {
            background-color: var(--cool-gray);
            color: #4B5563;
        }
        
        /* Table Row Hover */
        .table-row-hover:hover {
            background-color: rgba(16, 163, 127, 0.04);
        }
        
        /* Animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up { animation: fadeInUp 0.5s ease-out; }

        /* ===== GLOBAL PAGE TRANSITION LOADER ===== */
        #page-loader {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(245, 251, 246, 0.88);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        #page-loader.visible {
            opacity: 1;
            pointer-events: all;
        }
        /* Top progress bar */
        #page-loader-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            width: 0%;
            background: linear-gradient(90deg, #10A37F, #34d399, #10A37F);
            background-size: 200% 100%;
            border-radius: 0 2px 2px 0;
            transition: width 0.4s ease;
            animation: shimmer-bar 1.6s linear infinite;
            z-index: 100000;
        }
        @keyframes shimmer-bar {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        /* Spinner card */
        .loader-card {
            background: white;
            border-radius: 20px;
            padding: 2rem 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(16,163,127,0.18),
                        0 0 0 1px rgba(16,163,127,0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            animation: loader-card-pop 0.35s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        @keyframes loader-card-pop {
            from { transform: scale(0.85); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
        /* Dual-ring spinner */
        .loader-ring {
            width: 52px;
            height: 52px;
            position: relative;
        }
        .loader-ring::before,
        .loader-ring::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 3px solid transparent;
        }
        .loader-ring::before {
            border-top-color: #10A37F;
            border-right-color: #10A37F;
            animation: spin-cw 0.9s cubic-bezier(0.4,0,0.2,1) infinite;
        }
        .loader-ring::after {
            border-bottom-color: #34d399;
            border-left-color: #34d399;
            animation: spin-ccw 1.2s cubic-bezier(0.4,0,0.2,1) infinite;
        }
        @keyframes spin-cw  { to { transform: rotate(360deg);  } }
        @keyframes spin-ccw { to { transform: rotate(-360deg); } }
        .loader-dots {
            display: flex;
            gap: 5px;
        }
        .loader-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10A37F;
            animation: dot-bounce 1.2s ease-in-out infinite;
        }
        .loader-dots span:nth-child(2) { animation-delay: 0.2s; background: #34d399; }
        .loader-dots span:nth-child(3) { animation-delay: 0.4s; background: #6ee7b7; }
        @keyframes dot-bounce {
            0%, 80%, 100% { transform: scale(0.7); opacity: 0.5; }
            40%            { transform: scale(1.2); opacity: 1;   }
        }
        
        /* Map Container */
        .map-container {
            border-radius: 1rem;
            overflow: hidden;
            border: 1px solid rgba(16, 163, 127, 0.2);
        }
        
        /* Brand Logo Styles */
        .brand-logo {
            max-height: 40px;
            width: auto;
        }
        .brand-logo-sm {
            max-height: 32px;
            width: auto;
        }
    </style>
</head>
<body class="bg-[#F5FBF6]">

<!-- ===== GLOBAL PAGE TRANSITION LOADER ===== -->
<div id="page-loader-bar"></div>
<div id="page-loader" role="status" aria-label="Loading">
    <div class="loader-card">
        <div class="loader-ring"></div>
        <div class="loader-dots">
            <span></span><span></span><span></span>
        </div>
        <p class="text-xs font-semibold text-gray-400 tracking-widest uppercase">Loading&hellip;</p>
    </div>
</div>

<script>
(function () {
    var loader = document.getElementById('page-loader');
    var bar    = document.getElementById('page-loader-bar');
    var barW   = 10;

    function showLoader() {
        loader.classList.add('visible');
        barW = 10;
        bar.style.width = barW + '%';
        var t = setInterval(function () {
            if (barW < 85) {
                barW += (85 - barW) * 0.08;
                bar.style.width = barW + '%';
            } else {
                clearInterval(t);
            }
        }, 120);
    }

    function hideLoader() {
        bar.style.width = '100%';
        setTimeout(function () {
            loader.classList.remove('visible');
            setTimeout(function () {
                bar.style.transition = 'none';
                bar.style.width = '0%';
                setTimeout(function () {
                    bar.style.transition = 'width 0.4s ease';
                }, 50);
            }, 350);
        }, 250);
    }

    /* Hide on full load */
    window.addEventListener('load', hideLoader);

    /* Show on internal link navigation */
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (!a) return;
        var href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript') ||
            href.startsWith('mailto') || href.startsWith('tel') ||
            a.getAttribute('target') === '_blank' ||
            e.ctrlKey || e.metaKey || e.shiftKey) return;
        showLoader();
    });

    /* Show on form submissions */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        /* Skip AJAX-driven forms that manage their own spinner */
        if (form.dataset.noLoader === 'true') return;
        showLoader();
    });
})();
</script>