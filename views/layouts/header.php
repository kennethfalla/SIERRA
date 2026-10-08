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
    <script src="<?php echo BASE_URL; ?>assets/js/theme.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/theme.js'); ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($logo_url): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($logo_url); ?>">
    <?php endif; ?>
    <title><?php echo htmlspecialchars($system_name); ?> - Environmental Reporting System</title>
    
    <!-- Fonts -->
    <link href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    
    <!-- Material Symbols -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/material-symbols.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/material-symbols.css'); ?>">
    
    <!-- Leaflet Map -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/leaflet/leaflet.css" />
    <script src="<?php echo BASE_URL; ?>assets/vendor/leaflet/leaflet.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/leaflet-stub.js"></script>
    <!-- Network hints for slow connections (map tiles / reverse geocoding) -->
    <link rel="dns-prefetch" href="https://tile.openstreetmap.org">
    <link rel="preconnect" href="https://tile.openstreetmap.org" crossorigin>
    <link rel="preconnect" href="https://tile.openstreetmap.appspot.com" crossorigin>
    <link rel="dns-prefetch" href="https://nominatim.openstreetmap.org">
    <link rel="dns-prefetch" href="https://photon.komoot.io">
    
    <!-- Chart.js -->
    <script src="<?php echo BASE_URL; ?>assets/vendor/chart/chart.umd.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/chart-stub.js"></script>
    
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
            border-radius: 8px;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--veridian-green);
            border-radius: 8px;
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

        @keyframes spin-cw { to { transform: rotate(360deg); } }
        
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/buttons.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/buttons.css'); ?>">
<script src="<?php echo BASE_URL; ?>assets/js/app-ui.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/app-ui.js'); ?>" defer></script>
</head>
<body class="bg-[#F5FBF6]">
