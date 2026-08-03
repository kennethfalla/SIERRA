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
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-up {
            animation: fadeInUp 0.5s ease-out;
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