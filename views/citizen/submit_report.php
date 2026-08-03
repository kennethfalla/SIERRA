<?php
/**
 * views/citizen/submit_report.php
 * Environmental Report Submission Form - WITH DUPLICATE DETECTION & UPVOTE
 * UPDATED: Category filter, re‑check on category change, simplified details modal, redirect to My Reports
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SecurityHelper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SettingsHelper.php';

if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "views/auth/login.php");
    exit();
}

$csrf_token = InputSanitizer::generateCsrfToken();

$database = new Database();
$db = $database->getConnection();

$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");

// Load San Isidro boundary from GeoJSON
$geojson_file = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/geojson/sanisidro.geojson';
$boundary_data = null;
if (file_exists($geojson_file)) {
    $geojson_content = file_get_contents($geojson_file);
    $boundary_data = json_decode($geojson_content, true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Submit Report - EnviroTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { font-family: 'Manrope', sans-serif; }
        
        /* ===== RESPONSIVE SIDEBAR FIX ===== */
        @media (max-width: 768px) {
            .ml-72 {
                margin-left: 0 !important;
                width: 100%;
                padding: 0;
            }
            .sidebar-mobile {
                position: fixed;
                left: -280px;
                transition: left 0.3s ease;
                z-index: 1000;
            }
            .sidebar-mobile.open {
                left: 0;
            }
        }
        
        /* ===== CONTAINER & PADDING ===== */
        .main-container {
            padding: 1rem;
            max-width: 1280px;
            margin: 0 auto;
        }
        @media (min-width: 640px) {
            .main-container {
                padding: 1.5rem;
            }
        }
        @media (min-width: 768px) {
            .main-container {
                padding: 2rem;
            }
        }

        /* ===== FORM CARD ===== */
        .form-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #eef2f0;
            overflow: hidden;
        }
        .form-card-header {
            padding: 1rem;
            background: #F5FBF6;
            border-bottom: 1px solid #eef2f0;
        }
        @media (min-width: 640px) {
            .form-card-header {
                padding: 1.25rem 1.5rem;
            }
        }
        .form-card-body {
            padding: 1rem;
        }
        @media (min-width: 640px) {
            .form-card-body {
                padding: 1.5rem;
            }
        }

        /* ===== INPUTS ===== */
        .form-input {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1.5px solid #e5ece8;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            transition: all 0.2s;
            background: white;
            color: #1a2e1a;
            min-height: 44px;
            touch-action: manipulation;
        }
        .form-input:focus {
            border-color: #10A37F;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08);
        }
        .form-input.error {
            border-color: #EF4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
        }
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #4d6b4a;
            margin-bottom: 0.25rem;
        }
        @media (min-width: 640px) {
            .form-label {
                font-size: 0.875rem;
            }
        }

        /* ===== IMPACT CARDS ===== */
        .impact-option {
            cursor: pointer;
        }
        .impact-option .card {
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
            border-radius: 0.9rem;
            padding: 0.8rem 0.5rem;
            text-align: center;
            height: 100%;
            background: white;
            min-height: 100px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        @media (min-width: 640px) {
            .impact-option .card {
                padding: 0.8rem 0.75rem;
                min-height: 110px;
            }
        }
        .impact-option .card:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        .impact-option.selected .card {
            border-color: #10A37F;
            background: #f0fdf4;
            box-shadow: 0 3px 8px rgba(16,163,127,0.1);
        }
        .impact-option.selected-localized .card {
            border-color: #10A37F;
            background: #f0fdf4;
        }
        .impact-option.selected-moderate .card {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        .impact-option.selected-severe .card {
            border-color: #ef4444;
            background: #fef2f2;
        }
        .impact-option .icon { font-size: 1.25rem; margin-bottom: 0.35rem; }
        .impact-option .title { font-weight: 700; font-size: 0.85rem; color: #1f2937; line-height: 1.2; }
        .impact-option .desc { font-size: 0.7rem; color: #6b7280; margin-top: 0.2rem; line-height: 1.35; }
        .impact-option .badge-severe { font-size: 0.65rem; display: block; margin-top: 0.25rem; color: #ef4444; font-weight: 600; }
        @media (max-width: 480px) {
            .impact-option .title { font-size: 0.75rem; }
            .impact-option .desc { font-size: 0.6rem; }
        }

        /* ===== MAP ===== */
        .custom-map-container {
            border-radius: 1rem;
            overflow: hidden;
            border: 1px solid rgba(16, 163, 127, 0.2);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 1;
        }
        #map {
            height: 300px;
            width: 100%;
            z-index: 0;
        }
        @media (min-width: 640px) {
            #map {
                height: 380px;
            }
        }
        @media (min-width: 1024px) {
            #map {
                height: 400px;
            }
        }
        .map-control-btn {
            z-index: 10 !important;
        }
        .map-tip-tooltip {
            z-index: 20 !important;
        }

        /* ===== UPLOAD AREA ===== */
        .upload-area {
            transition: all 0.2s ease;
            cursor: pointer;
            border: 2px dashed #E5E7EB;
            border-radius: 1rem;
            padding: 1.5rem 1rem;
            text-align: center;
        }
        @media (min-width: 640px) {
            .upload-area {
                padding: 2rem;
            }
        }
        .upload-area:hover {
            border-color: #10A37F;
            background: #F0FDF4;
        }
        .upload-area.drag-over {
            border-color: #10A37F;
            background: #D1FAE5;
        }

        /* ===== BUTTONS ===== */
        .btn-primary {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            transition: all 0.2s ease;
            border: none;
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            min-height: 44px;
            touch-action: manipulation;
        }
        .btn-primary:hover {
            background-color: #0D8568;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 0.6rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 500;
            color: #4b5563;
            cursor: pointer;
            transition: all 0.2s;
            min-height: 44px;
            touch-action: manipulation;
        }
        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        /* ===== PHOTO PREVIEW ===== */
        .photo-preview {
            position: relative;
            transition: all 0.2s ease;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        .photo-preview:hover .remove-photo { opacity: 1; }
        .remove-photo {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 28px;
            height: 28px;
            background: #EF4444;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s ease;
            font-size: 12px;
            border: 2px solid white;
            z-index: 10;
        }
        @media (max-width: 480px) {
            .remove-photo {
                opacity: 1;
                width: 24px;
                height: 24px;
                font-size: 10px;
            }
        }

        /* ===== TOAST ===== */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideInRight 0.3s ease-out;
            max-width: 90vw;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @media (max-width: 480px) {
            .toast-notification {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }

        /* ===== MAP TIP ===== */
        .map-tip-tooltip {
            position: absolute;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(6px);
            color: white;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 0.8rem;
            display: none;
            max-width: 90%;
            text-align: center;
            pointer-events: none;
            border: 1px solid rgba(255,255,255,0.1);
            z-index: 20;
        }
        .map-tip-tooltip.show {
            display: block;
            animation: fadeSlideDown 0.4s ease;
        }
        @media (max-width: 480px) {
            .map-tip-tooltip {
                font-size: 0.7rem;
                padding: 8px 12px;
                bottom: 70px;
            }
        }

        /* ===== DUPLICATE MODAL ===== */
        #duplicateModal {
            z-index: 1000;
        }
        #duplicateModal .modal-card {
            max-height: 90vh;
            overflow-y: auto;
        }
        #duplicateReportList .report-item {
            transition: all 0.15s ease;
            cursor: pointer;
        }
        #duplicateReportList .report-item:hover {
            background: #f9fafb;
        }
        #duplicateReportList .report-item.selected {
            border-color: #10A37F !important;
            background: #f0fdf4 !important;
        }
        .report-item .distance-badge {
            background: #e5e7eb;
            color: #4b5563;
            font-size: 0.65rem;
            padding: 0.1rem 0.5rem;
            border-radius: 9999px;
            white-space: nowrap;
        }

        /* ===== DETAILS SUB-MODAL ===== */
        #detailsModal {
            z-index: 1001;
        }
        #detailsModal .modal-card {
            max-height: 90vh;
            overflow-y: auto;
        }
        .detail-row {
            display: flex;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .detail-row .label {
            width: 35%;
            font-weight: 600;
            color: #4b5563;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        .detail-row .value {
            width: 65%;
            color: #1f2937;
            font-size: 0.85rem;
            word-break: break-word;
        }
        @media (max-width: 480px) {
            .detail-row {
                flex-direction: column;
                padding: 0.3rem 0;
            }
            .detail-row .label {
                width: 100%;
                font-size: 0.7rem;
            }
            .detail-row .value {
                width: 100%;
                font-size: 0.8rem;
            }
        }

        /* Photo gallery in details modal */
        .details-photos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .details-photos img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .details-photos img:hover {
            transform: scale(1.05);
        }

        /* ===== CAMERA MODAL ===== */
        .camera-modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            flex-direction: column;
        }
        .camera-modal.active {
            display: flex;
        }
        .camera-modal .camera-wrapper {
            position: relative;
            width: 100%;
            max-width: 500px;
            border-radius: 20px;
            overflow: hidden;
            background: #000;
            aspect-ratio: 4/3;
            box-shadow: 0 25px 60px rgba(0,0,0,0.8);
        }
        .camera-modal .camera-wrapper video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .camera-modal .viewfinder {
            position: absolute;
            inset: 0;
            pointer-events: none;
            border: 2px solid rgba(255,255,255,0.15);
            border-radius: 20px;
            box-shadow: inset 0 0 0 2px rgba(255,255,255,0.05);
        }
        .camera-modal .viewfinder::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            height: 80%;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
        }
        .camera-modal .viewfinder .crosshair {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .camera-modal .viewfinder .crosshair::before,
        .camera-modal .viewfinder .crosshair::after {
            content: '';
            position: absolute;
            background: rgba(255,255,255,0.3);
        }
        .camera-modal .viewfinder .crosshair::before {
            width: 2px;
            height: 100%;
        }
        .camera-modal .viewfinder .crosshair::after {
            width: 100%;
            height: 2px;
        }
        .camera-modal .viewfinder .corner {
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.2);
        }
        .camera-modal .viewfinder .corner.tl { top: 12px; left: 12px; border-right: none; border-bottom: none; }
        .camera-modal .viewfinder .corner.tr { top: 12px; right: 12px; border-left: none; border-bottom: none; }
        .camera-modal .viewfinder .corner.bl { bottom: 12px; left: 12px; border-right: none; border-top: none; }
        .camera-modal .viewfinder .corner.br { bottom: 12px; right: 12px; border-left: none; border-top: none; }

        .camera-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-top: 24px;
            width: 100%;
            max-width: 500px;
            padding: 0 8px;
        }
        .camera-controls .ctrl-btn {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: none;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(4px);
            color: white;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            touch-action: manipulation;
        }
        .camera-controls .ctrl-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.05);
        }
        .camera-controls .ctrl-btn:active {
            transform: scale(0.92);
        }
        .camera-controls .ctrl-btn.capture {
            width: 72px;
            height: 72px;
            background: white;
            color: #10A37F;
            font-size: 1.8rem;
            box-shadow: 0 0 0 4px rgba(255,255,255,0.2);
        }
        .camera-controls .ctrl-btn.capture:hover {
            background: #f0fdf4;
            box-shadow: 0 0 0 6px rgba(255,255,255,0.3);
        }
        .camera-controls .ctrl-btn.close-cam {
            background: rgba(239,68,68,0.3);
            color: #fca5a5;
        }
        .camera-controls .ctrl-btn.close-cam:hover {
            background: rgba(239,68,68,0.5);
        }
        .camera-controls .ctrl-btn.flash {
            background: rgba(255,255,255,0.08);
            color: #fbbf24;
        }
        .camera-controls .ctrl-btn.flash.active {
            background: #fbbf24;
            color: #1f2937;
        }
        .camera-controls .ctrl-btn.switch-cam {
            background: rgba(255,255,255,0.08);
            color: #93c5fd;
        }
        @media (max-width: 480px) {
            .camera-controls .ctrl-btn {
                width: 48px;
                height: 48px;
                font-size: 1rem;
            }
            .camera-controls .ctrl-btn.capture {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            .camera-controls {
                gap: 14px;
            }
        }

        .camera-tips-overlay {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: white;
            padding: 10px 18px;
            border-radius: 30px;
            font-size: 0.75rem;
            text-align: center;
            max-width: 90%;
            z-index: 10;
            border: 1px solid rgba(255,255,255,0.08);
            pointer-events: none;
            transition: opacity 0.3s ease;
            white-space: nowrap;
        }
        .camera-tips-overlay .tip-emoji {
            margin-right: 6px;
        }
        .camera-tips-overlay .tip-text strong {
            color: #10A37F;
        }
        .camera-tips-overlay .tip-dismiss {
            position: absolute;
            top: -8px;
            right: -6px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            pointer-events: auto;
            font-size: 10px;
            color: #aaa;
            transition: all 0.2s;
        }
        .camera-tips-overlay .tip-dismiss:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        @media (max-width: 480px) {
            .camera-tips-overlay {
                font-size: 0.65rem;
                padding: 8px 14px;
                bottom: 12px;
                white-space: normal;
            }
        }

        /* ===== RESPONSIVE FINE-TUNING ===== */
        @media (max-width: 480px) {
            .form-card-body {
                padding: 0.75rem;
            }
            .form-input {
                font-size: 0.8rem;
                padding: 0.5rem 0.6rem;
                min-height: 40px;
            }
            .btn-primary, .btn-secondary {
                font-size: 0.8rem;
                padding: 0.5rem 1rem;
                min-height: 40px;
            }
            .upload-area {
                padding: 1rem;
            }
        }
        @media (max-width: 380px) {
            .form-card-body {
                padding: 0.5rem;
            }
        }

        /* ===== LOADING SPINNER ===== */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #10A37F;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-[#F5FBF6]">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<!-- ===== CONTAINER ===== -->
<div class="ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span><?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['errors']) && is_array($_SESSION['errors'])): ?>
            <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl">
                <p class="font-medium text-red-800 mb-2 text-sm">Please fix the following errors:</p>
                <ul class="list-disc list-inside text-red-600 text-sm space-y-1">
                    <?php foreach ($_SESSION['errors'] as $err): ?>
                        <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['errors']); ?>
        <?php endif; ?>

        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center space-x-2 mb-2">
                <div class="w-8 h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-plus-circle text-[#10A37F] text-sm"></i>
                </div>
                <span class="text-xs uppercase tracking-wider text-[#10A37F] font-semibold">New Report</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Submit Environmental Report</h1>
            <p class="text-gray-500 text-sm mt-1">Document and report environmental concerns in your community</p>
            <div class="mt-3 inline-flex items-center px-3 py-1 bg-emerald-100 rounded-full text-xs text-emerald-800">
                <i class="fas fa-map-marker-alt mr-1"></i> San Isidro, Nueva Ecija
            </div>
        </div>

        <!-- Form Card -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-shield-alt text-[#10A37F]"></i>
                    <span class="text-sm font-medium text-gray-700">Report Details - All data is validated and sanitized</span>
                </div>
            </div>

            <div class="form-card-body">
                <form id="reportForm" 
                      action="<?php echo htmlspecialchars(BASE_URL . 'controllers/ReportController.php', ENT_QUOTES, 'UTF-8'); ?>" 
                      method="POST" 
                      enctype="multipart/form-data" 
                      novalidate>

                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="store">

                    <!-- ===== CATEGORY ===== -->
                    <div class="mb-5">
                        <label for="category_id" class="form-label">
                            Category <span class="text-red-500">*</span>
                            <span class="text-xs font-normal text-gray-400 ml-1">(Select the issue type)</span>
                        </label>
                        <select id="category_id" 
                                name="category_id" 
                                required 
                                class="form-input">
                            <option value="">Select a category</option>
                            <?php 
                            $categories->execute();
                            while ($cat = $categories->fetch(PDO::FETCH_ASSOC)): 
                            ?>
                                <option value="<?php echo (int)$cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <p id="category-error" class="error-message"></p>
                        <!-- Category Suggestion -->
                        <div id="categorySuggestion" style="display:none; background:#f8fafc; border:1px solid #e2e8f0; border-radius:0.75rem; padding:0.5rem 0.75rem; margin-top:0.5rem; font-size:0.8rem; color:#475569;">
                            <i class="fas fa-lightbulb"></i>
                            <span class="font-semibold">Not sure which category?</span>
                            <div style="margin-top:6px;">
                                <span class="text-xs text-gray-500">Select from these common categories:</span>
                                <div style="margin-top:4px; display:flex; flex-wrap:wrap; gap:4px;">
                                    <span class="cat-tag" data-cat="Drainage Blockage" style="display:inline-block; background:#e2e8f0; padding:0.1rem 0.5rem; border-radius:9999px; font-size:0.7rem; font-weight:600; color:#475569; cursor:pointer;">🌊 Drainage Blockage</span>
                                    <span class="cat-tag" data-cat="Illegal Dumping" style="display:inline-block; background:#e2e8f0; padding:0.1rem 0.5rem; border-radius:9999px; font-size:0.7rem; font-weight:600; color:#475569; cursor:pointer;">🚮 Illegal Dumping</span>
                                    <span class="cat-tag" data-cat="Uncollected Garbage" style="display:inline-block; background:#e2e8f0; padding:0.1rem 0.5rem; border-radius:9999px; font-size:0.7rem; font-weight:600; color:#475569; cursor:pointer;">🗑️ Uncollected Garbage</span>
                                    <span class="cat-tag" data-cat="Water Pollution" style="display:inline-block; background:#e2e8f0; padding:0.1rem 0.5rem; border-radius:9999px; font-size:0.7rem; font-weight:600; color:#475569; cursor:pointer;">💧 Water Pollution</span>
                                </div>
                            </div>
                        </div>
                        <div class="suggestion-box" id="categoryTipBox" style="background:#f0fdf4; border-left:4px solid #10A37F; padding:0.75rem 1rem; border-radius:0.75rem; margin-top:0.5rem; font-size:0.85rem; color:#065f46; display:none;">
                            <i class="fas fa-info-circle"></i>
                            <span class="suggestion-title font-bold">Tip: Choose the right category</span>
                            <span class="suggestion-desc font-normal text-sm">Selecting the correct category helps barangay officials respond faster to your report.</span>
                        </div>
                    </div>

                    <!-- ===== IMPACT MODIFIER ===== -->
                    <div class="mb-5">
                        <label class="form-label">
                            What is the current impact of this issue? <span class="text-red-500">*</span>
                            <span class="text-xs font-normal text-gray-400 ml-1">(Select the most accurate description)</span>
                        </label>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" id="impactContainer">
                            <!-- Localized / Minor -->
                            <div class="impact-option selected selected-localized" data-value="0" role="button" tabindex="0">
                                <div class="card">
                                    <div class="icon"><i class="fas fa-circle text-emerald-500"></i></div>
                                    <div class="title">Localized / Minor</div>
                                    <div class="desc">Contained in one small area, no immediate danger.</div>
                                </div>
                            </div>
                            
                            <!-- Moderate -->
                            <div class="impact-option" data-value="2" role="button" tabindex="0">
                                <div class="card">
                                    <div class="icon"><i class="fas fa-exclamation-triangle text-amber-500"></i></div>
                                    <div class="title">Moderate</div>
                                    <div class="desc">Affecting sidewalks or causing strong, widespread odor.</div>
                                </div>
                            </div>
                            
                            <!-- Severe -->
                            <div class="impact-option" data-value="4" role="button" tabindex="0">
                                <div class="card">
                                    <div class="icon"><i class="fas fa-fire text-red-500"></i></div>
                                    <div class="title">Severe</div>
                                    <div class="desc">Blocking roads, entering homes, active safety hazard.</div>
                                    <span class="badge-severe">Auto-escalates</span>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="impact_modifier" id="impact_modifier" value="0">
                        <p id="impact-error" class="error-message"></p>
                        
                        <div class="mt-3 text-xs text-gray-400 flex items-center gap-2">
                            <i class="fas fa-info-circle text-emerald-500"></i>
                            <span>This helps us prioritize urgent reports. <strong class="text-red-500">Severe</strong> issues automatically trigger a High Priority alert to MENRO.</span>
                        </div>
                    </div>

                    <!-- ===== MAP ===== -->
                    <div class="mb-5">
                        <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                            <label class="form-label mb-0">
                                Geotag Location <span class="text-red-500">*</span>
                                <span class="text-xs font-normal text-gray-400 ml-1">(Click map to pin)</span>
                            </label>
                            <span class="text-xs text-gray-400" id="coordDisplay">
                                <i class="fas fa-map-marker-alt mr-1"></i>No location selected
                            </span>
                        </div>

                        <div class="custom-map-container relative">
                            <div id="map"></div>

                            <!-- Map Smart Tip Tooltip -->
                            <div id="mapTipTooltip" class="map-tip-tooltip">
                                <div class="tip-close" onclick="closeMapTip()" style="position:absolute; top:-6px; right:-6px; background:rgba(255,255,255,0.15); border-radius:50%; width:20px; height:20px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:10px; color:#aaa;">✕</div>
                                <i class="fas fa-hand-pointer"></i>
                                <span><strong>Tip:</strong> Click anywhere on the map to pin the exact location of the environmental issue.</span>
                                <span style="display:block;font-size:0.7rem;color:#94a3b8;margin-top:4px;">You can also use "My Location" to auto-detect.</span>
                            </div>

                            <div class="absolute bottom-4 right-4 z-[10] flex flex-col space-y-2">
                                <button type="button" id="getLocationBtn" 
                                        class="map-control-btn bg-white shadow-lg rounded-xl px-3 py-1.5 text-xs font-medium text-[#10A37F] hover:bg-[#10A37F] hover:text-white transition-all flex items-center space-x-2">
                                    <i class="fas fa-location-dot"></i>
                                    <span>My Location</span>
                                </button>
                                <button type="button" id="clearLocationBtn" 
                                        class="map-control-btn bg-white shadow-lg rounded-xl px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 transition-all flex items-center space-x-2">
                                    <i class="fas fa-eraser"></i>
                                    <span>Clear</span>
                                </button>
                            </div>

                            <div class="absolute top-4 left-4 z-[10] bg-white/90 backdrop-blur-sm rounded-lg px-2 py-1 shadow-sm">
                                <div class="flex items-center space-x-2 text-xs text-gray-600">
                                    <i class="fas fa-hand-pointer text-[#10A37F]"></i>
                                    <span>Click on map to pin location</span>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="latitude" id="latitude">
                        <input type="hidden" name="longitude" id="longitude">
                        <input type="hidden" name="barangay_id" id="barangay_id">
                        <input type="hidden" name="location_address" id="location_address">

                        <p id="map-error" class="error-message"></p>
                        <div id="locationStatus" class="mt-2"></div>
                    </div>

                    <!-- ===== PHOTO EVIDENCE ===== -->
                    <div class="mb-5">
                        <label class="form-label">
                            Photo Evidence (Max 3) <span class="text-red-500">*</span>
                            <span class="text-xs font-normal text-gray-400 ml-1">(Take photo or upload)</span>
                        </label>
                        <div class="upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-500">Click or drag & drop to upload photos</p>
                            <p class="text-xs text-gray-400 mt-1">Up to 3 photos (JPG, PNG, GIF, WebP - Max 5MB each)</p>
                            <input type="file" id="photoInput" name="report_images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display: none;" data-max-files="3" data-max-size="5242880">
                        </div>

                        <div class="mt-3 flex flex-col sm:flex-row gap-3">
                            <button type="button" id="cameraBtn" class="flex-1 px-4 py-2 bg-gray-100 rounded-xl hover:bg-gray-200 transition-all flex items-center justify-center gap-2 text-sm">
                                <i class="fas fa-camera"></i>
                                <span>Take Photo</span>
                            </button>
                            <button type="button" id="galleryBtn" class="flex-1 px-4 py-2 bg-gray-100 rounded-xl hover:bg-gray-200 transition-all flex items-center justify-center gap-2 text-sm">
                                <i class="fas fa-images"></i>
                                <span>Choose from Gallery</span>
                            </button>
                        </div>

                        <div id="photoPreviews" class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4"></div>
                        <p id="photoCount" class="text-xs text-gray-400 mt-2">0 / 3 photos selected</p>
                        <p id="file-error" class="error-message"></p>
                    </div>

                    <!-- ===== DESCRIPTION ===== -->
                    <div class="mb-5">
                        <label for="description" class="form-label">
                            Description <span class="text-red-500">*</span>
                        </label>
                        <textarea id="description" 
                                  name="description" 
                                  rows="5" 
                                  required 
                                  maxlength="5000"
                                  placeholder="Describe the issue, location, and impact (e.g., clogged drainage at Purok 3 causing road flooding since Monday)."
                                  class="form-input" style="resize:vertical; min-height:120px;"></textarea>
                        
                        <p id="description-error" class="error-message"></p>
                        <p id="description-count" class="text-xs text-gray-400 mt-1">0/5000 characters</p>
                    </div>

                    <!-- FORM ACTIONS -->
                    <div class="form-actions pt-3 border-t border-emerald-50 flex justify-end gap-3">
                        <button type="button" id="resetBtn" class="btn-secondary">
                            <i class="fas fa-redo mr-2"></i>Reset
                        </button>
                        <button type="submit" id="submitBtn" class="btn-primary">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ===== DUPLICATE REPORT MODAL ===== -->
<div id="duplicateModal" class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/60 backdrop-blur-sm" style="display:none;">
    <div class="modal-card bg-white rounded-2xl max-w-md w-full mx-4 shadow-2xl overflow-hidden transform transition-all scale-95">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center text-yellow-600 flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">It looks like this was already reported</h3>
            </div>
            <p class="text-sm text-gray-600 mb-4">
                Someone nearby reported a similar issue. Are you reporting the same incident?
            </p>
            <div id="duplicateReportList" class="space-y-3 mb-5 max-h-60 overflow-y-auto">
                <!-- Will be populated by JavaScript -->
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <button id="dupYesBtn" class="flex-1 bg-[#10A37F] hover:bg-[#0D8568] text-white font-semibold py-2.5 rounded-xl transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-check mr-2"></i>Yes, it's the same
                </button>
                <button id="dupNoBtn" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2.5 rounded-xl transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-times mr-2"></i>No, different issue
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-4 text-center">
                <i class="fas fa-shield-alt mr-1"></i>Your verification helps prioritize real issues.
            </p>
        </div>
    </div>
</div>

<!-- ===== DETAILS SUB-MODAL (UPDATED) ===== -->
<div id="detailsModal" class="fixed inset-0 z-[1001] flex items-center justify-center bg-black/70 backdrop-blur-sm" style="display:none;">
    <div class="modal-card bg-white rounded-2xl max-w-lg w-full mx-4 shadow-2xl overflow-hidden">
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-file-alt text-[#10A37F]"></i>
                    Report Details
                </h3>
                <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="detailsContent" class="space-y-3 max-h-[70vh] overflow-y-auto pr-2">
                <div class="text-center py-8">
                    <div class="loading-spinner"></div>
                    <p class="text-gray-400 text-sm mt-2">Loading report details...</p>
                </div>
            </div>
            
            <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end gap-3">
                <button onclick="closeDetailsModal()" class="btn-secondary px-4 py-2 text-sm">Close</button>
                <button id="supportFromDetailsBtn" class="btn-primary px-4 py-2 text-sm flex items-center gap-2">
                    <i class="fas fa-thumbs-up"></i> Support This Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== ENHANCED CAMERA MODAL ===== -->
<div id="cameraModal" class="camera-modal">
    <div class="camera-wrapper">
        <video id="video" autoplay playsinline></video>
        <canvas id="canvas" style="display: none;"></canvas>
        
        <!-- Viewfinder overlay -->
        <div class="viewfinder">
            <div class="corner tl"></div>
            <div class="corner tr"></div>
            <div class="corner bl"></div>
            <div class="corner br"></div>
            <div class="crosshair"></div>
        </div>
        
        <!-- Camera Tips -->
        <div id="cameraTips" class="camera-tips-overlay">
            <div class="tip-dismiss" onclick="dismissCameraTips()">✕</div>
            <div id="tipContent">
                <span class="tip-emoji">📸</span>
                <span class="tip-text"><strong>Hold steady</strong> and ensure good lighting for clear photos.</span>
            </div>
        </div>
    </div>
    
    <!-- Camera Controls -->
    <div class="camera-controls">
        <button id="switchCameraBtn" class="ctrl-btn switch-cam" title="Switch Camera">
            <i class="fas fa-sync-alt"></i>
        </button>
        <button id="flashToggleBtn" class="ctrl-btn flash" title="Toggle Flash">
            <i class="fas fa-bolt"></i>
        </button>
        <button id="captureBtn" class="ctrl-btn capture" title="Capture Photo">
            <i class="fas fa-camera"></i>
        </button>
        <button id="closeCameraBtn" class="ctrl-btn close-cam" title="Close Camera">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<!-- ============================================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================================ -->
<script>
(function() {
    'use strict';

    // DOM REFERENCES
    const reportForm = document.getElementById('reportForm');
    const descriptionInput = document.getElementById('description');
    const categorySelect = document.getElementById('category_id');
    const photoInput = reportForm.querySelector('#photoInput');
    const photoPreviews = reportForm.querySelector('#photoPreviews');
    const photoCount = reportForm.querySelector('#photoCount');
    const uploadArea = reportForm.querySelector('#uploadArea');
    const cameraBtn = reportForm.querySelector('#cameraBtn');
    const galleryBtn = reportForm.querySelector('#galleryBtn');
    const cameraModal = document.getElementById('cameraModal');
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const closeCameraBtn = document.getElementById('closeCameraBtn');
    const captureBtn = document.getElementById('captureBtn');
    const submitBtn = document.getElementById('submitBtn');
    const resetBtn = document.getElementById('resetBtn');
    const getLocationBtn = document.getElementById('getLocationBtn');
    const clearLocationBtn = document.getElementById('clearLocationBtn');
    const switchCameraBtn = document.getElementById('switchCameraBtn');
    const flashToggleBtn = document.getElementById('flashToggleBtn');

    // Duplicate Modal DOM
    const duplicateModal = document.getElementById('duplicateModal');
    const duplicateReportList = document.getElementById('duplicateReportList');
    const dupYesBtn = document.getElementById('dupYesBtn');
    const dupNoBtn = document.getElementById('dupNoBtn');

    // Details Modal DOM
    const detailsModal = document.getElementById('detailsModal');
    const detailsContent = document.getElementById('detailsContent');
    const supportFromDetailsBtn = document.getElementById('supportFromDetailsBtn');

    // STATE
    let selectedPhotos = [];
    let selectedFiles = [];
    let mediaStream = null;
    let map = null;
    let currentMarker = null;
    let boundaryLayer = null;
    let sanIsidroPolygon = null;
    let mapTipTimer = null;
    let cameraTipTimer = null;
    let facingMode = 'environment';
    let flashMode = false;
    let duplicateReportId = null;
    let isDuplicateCheckDone = false;
    let viewingReportId = null;

    const MAX_PHOTOS = 3;
    const MAX_FILE_SIZE = 5 * 1024 * 1024;
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // ============================================================
    // CSRF TOKEN HELPER
    // ============================================================
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // ============================================================
    // TOAST NOTIFICATION
    // ============================================================
    function showToast(message, type) {
        type = type || 'error';
        const colors = {
            error: { bg: 'bg-red-500', icon: 'fa-exclamation-circle' },
            success: { bg: 'bg-green-500', icon: 'fa-check-circle' },
            warning: { bg: 'bg-yellow-500', icon: 'fa-exclamation-triangle' },
            info: { bg: 'bg-blue-500', icon: 'fa-info-circle' }
        };
        const color = colors[type] || colors.error;
        const toast = document.createElement('div');
        toast.className = 'toast-notification ' + color.bg + ' text-white px-5 py-3 rounded-xl shadow-lg flex items-center gap-3';
        toast.style.minWidth = '300px';
        toast.style.maxWidth = '450px';
        toast.innerHTML = '<i class="fas ' + color.icon + '"></i><span style="flex:1;">' + escapeHtml(String(message)) + '</span><button class="text-white/80 hover:text-white" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
        document.body.appendChild(toast);
        setTimeout(function() {
            if (toast.parentElement) {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(function() { toast.remove(); }, 300);
            }
        }, 4000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function timeSince(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + ' years ago';
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + ' months ago';
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + ' days ago';
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + ' hours ago';
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + ' minutes ago';
        return 'just now';
    }

    // ============================================================
    // IMPACT MODIFIER SELECTION
    // ============================================================
    function selectImpact(value) {
        document.querySelectorAll('.impact-option').forEach(el => {
            el.classList.remove('selected', 'selected-localized', 'selected-moderate', 'selected-severe');
        });
        
        const selected = document.querySelector(`.impact-option[data-value="${value}"]`);
        if (selected) {
            selected.classList.add('selected');
            if (value === 0) selected.classList.add('selected-localized');
            else if (value === 2) selected.classList.add('selected-moderate');
            else if (value === 4) selected.classList.add('selected-severe');
        }
        
        document.getElementById('impact_modifier').value = value;
        hideFieldError('impact');
    }

    function bindImpactOptions() {
        document.querySelectorAll('.impact-option').forEach(function(option) {
            option.addEventListener('click', function() {
                selectImpact(parseInt(this.getAttribute('data-value'), 10));
            });
            option.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectImpact(parseInt(this.getAttribute('data-value'), 10));
                }
            });
        });
    }

    // ============================================================
    // CAMERA TIPS
    // ============================================================
    const cameraTips = [
        { emoji: '📸', text: '<strong>Hold steady</strong> and ensure good lighting for clear photos.' },
        { emoji: '🎯', text: '<strong>Frame the issue</strong> clearly — show the problem in context.' },
        { emoji: '🔍', text: '<strong>Get close</strong> to capture details, then step back for the bigger picture.' },
        { emoji: '📐', text: '<strong>Use landscape</strong> orientation for wider shots of the area.' },
        { emoji: '☀️', text: '<strong>Avoid glare</strong> — shoot with the sun behind you if possible.' }
    ];
    let cameraTipIndex = 0;

    function rotateCameraTip() {
        const tipContent = document.getElementById('tipContent');
        if (!tipContent) return;
        cameraTipIndex = (cameraTipIndex + 1) % cameraTips.length;
        const tip = cameraTips[cameraTipIndex];
        tipContent.innerHTML = `
            <span class="tip-emoji">${tip.emoji}</span>
            <span class="tip-text">${tip.text}</span>
        `;
    }

    function startCameraTips() {
        cameraTipIndex = 0;
        const tipContent = document.getElementById('tipContent');
        if (tipContent) {
            const tip = cameraTips[0];
            tipContent.innerHTML = `
                <span class="tip-emoji">${tip.emoji}</span>
                <span class="tip-text">${tip.text}</span>
            `;
        }
        clearInterval(cameraTipTimer);
        cameraTipTimer = setInterval(rotateCameraTip, 5000);
    }

    function stopCameraTips() {
        clearInterval(cameraTipTimer);
    }

    function dismissCameraTips() {
        const overlay = document.getElementById('cameraTips');
        if (overlay) {
            overlay.style.opacity = '0';
            setTimeout(function() {
                overlay.style.display = 'none';
            }, 300);
        }
        stopCameraTips();
    }

    // ============================================================
    // MAP TOOLTIP
    // ============================================================
    function showMapTip() {
        const tip = document.getElementById('mapTipTooltip');
        tip.classList.add('show');
        clearTimeout(mapTipTimer);
        mapTipTimer = setTimeout(function() {
            tip.classList.remove('show');
        }, 8000);
    }

    function closeMapTip() {
        document.getElementById('mapTipTooltip').classList.remove('show');
        clearTimeout(mapTipTimer);
    }

    // ============================================================
    // CATEGORY SUGGESTIONS
    // ============================================================
    categorySelect.addEventListener('click', function() {
        const tipBox = document.getElementById('categoryTipBox');
        const suggestionBox = document.getElementById('categorySuggestion');
        tipBox.style.display = 'block';
        suggestionBox.style.display = 'block';
        clearTimeout(categoryTipTimer);
        categoryTipTimer = setTimeout(function() {
            tipBox.style.display = 'none';
            suggestionBox.style.display = 'none';
        }, 8000);
    });

    document.querySelectorAll('.cat-tag').forEach(function(tag) {
        tag.addEventListener('click', function() {
            const catName = this.getAttribute('data-cat');
            const options = categorySelect.options;
            for (let i = 0; i < options.length; i++) {
                if (options[i].text.trim() === catName) {
                    categorySelect.selectedIndex = i;
                    categorySelect.dispatchEvent(new Event('change'));
                    break;
                }
            }
            document.getElementById('categorySuggestion').style.display = 'none';
            showToast('Category selected: ' + catName, 'success');
        });
    });

    let categoryTipTimer = null;

    // ============================================================
    // SANITIZATION & VALIDATION
    // ============================================================
    function stripHtmlTags(input) {
        if (!input) return '';
        let cleaned = input.replace(/<[^>]*>/g, '');
        const txt = document.createElement('textarea');
        txt.innerHTML = cleaned;
        cleaned = txt.value;
        cleaned = cleaned.replace(/&[a-zA-Z]+;/g, '');
        cleaned = cleaned.replace(/&#[0-9]+;/g, '');
        cleaned = cleaned.replace(/&#x[0-9a-fA-F]+;/g, '');
        return cleaned.trim();
    }

    function sanitizeRichText(input) {
        if (!input) return '';
        const allowedTags = ['p', 'br', 'b', 'i', 'u', 'em', 'strong', 'ul', 'ol', 'li', 'h3', 'h4', 'h5', 'h6'];
        const div = document.createElement('div');
        div.innerHTML = input;
        const allElements = div.getElementsByTagName('*');
        for (let i = allElements.length - 1; i >= 0; i--) {
            const el = allElements[i];
            if (!allowedTags.includes(el.tagName.toLowerCase())) {
                const textNode = document.createTextNode(el.textContent);
                el.parentNode.replaceChild(textNode, el);
                continue;
            }
            while (el.attributes.length > 0) {
                el.removeAttribute(el.attributes[0].name);
            }
        }
        let result = div.innerHTML;
        result = result.replace(/on\w+\s*=\s*"[^"]*"/gi, '');
        result = result.replace(/on\w+\s*=\s*'[^']*'/gi, '');
        result = result.replace(/javascript\s*:/gi, '');
        return result.trim();
    }

    function showFieldError(fieldId, message) {
        const errorEl = document.getElementById(fieldId + '-error');
        const inputEl = document.getElementById(fieldId);
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.add('visible');
        }
        if (inputEl) inputEl.classList.add('error');
    }

    function hideFieldError(fieldId) {
        const errorEl = document.getElementById(fieldId + '-error');
        const inputEl = document.getElementById(fieldId);
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.remove('visible');
        }
        if (inputEl) inputEl.classList.remove('error');
    }

    function updateCharCount(inputId, countId, maxLength) {
        const input = document.getElementById(inputId);
        const count = document.getElementById(countId);
        if (!input || !count) return;
        const len = input.value.length;
        count.textContent = len + '/' + maxLength + ' characters';
        count.classList.remove('text-red-500', 'text-yellow-500', 'text-green-500');
        if (len >= maxLength) count.classList.add('text-red-500');
        else if (len > maxLength * 0.85) count.classList.add('text-yellow-500');
        else if (len > 0) count.classList.add('text-green-500');
    }

    function validateDescription() {
        const raw = descriptionInput.value;
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = raw;
        const plainText = tempDiv.textContent || tempDiv.innerText || '';
        const trimmed = plainText.trim();
        
        if (!trimmed) {
            showFieldError('description', 'Description is required');
            return false;
        }
        if (trimmed.length < 10) {
            showFieldError('description', 'Description must contain at least 10 characters');
            return false;
        }
        if (trimmed.length > 5000) {
            showFieldError('description', 'Description must not exceed 5000 characters');
            return false;
        }
        hideFieldError('description');
        return true;
    }

    function validateCategory() {
        if (!categorySelect.value) {
            showFieldError('category', 'Please select a category');
            return false;
        }
        hideFieldError('category');
        return true;
    }

    function validateImpact() {
        const val = parseInt(document.getElementById('impact_modifier').value);
        if (![0, 2, 4].includes(val)) {
            showFieldError('impact', 'Please select an impact level');
            return false;
        }
        hideFieldError('impact');
        return true;
    }

    function validateFiles() {
        const previewImages = photoPreviews.querySelectorAll('.photo-preview');
        if (previewImages.length === 0) {
            showFieldError('file', 'Please upload at least one photo as evidence');
            return false;
        }
        hideFieldError('file');
        return true;
    }

    function validateLocation() {
        const lat = document.getElementById('latitude').value;
        const lng = document.getElementById('longitude').value;
        if (!lat || !lng) {
            showFieldError('map', 'Please click on the map to pin the exact location');
            const mapEl = document.getElementById('map');
            if (mapEl) mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
        hideFieldError('map');
        return true;
    }

    // ============================================================
    // PHOTO MANAGEMENT
    // ============================================================
    function validateFile(file) {
        if (!ALLOWED_TYPES.includes(file.type)) {
            showToast('Invalid file type: ' + file.name + '. Allowed: JPG, PNG, GIF, WebP', 'error');
            return false;
        }
        if (file.size > MAX_FILE_SIZE) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            showToast('File too large: ' + file.name + ' (' + sizeMB + 'MB). Max 5MB', 'error');
            return false;
        }
        return true;
    }

    function updatePhotoPreviews() {
        photoPreviews.innerHTML = '';
        selectedPhotos.forEach(function(photo, index) {
            const div = document.createElement('div');
            div.className = 'photo-preview relative';
            div.innerHTML = `
                <img src="${photo.data}" class="w-full h-20 md:h-24 object-cover rounded-lg border border-gray-200" alt="Photo ${index + 1}">
                <div class="remove-photo" data-index="${index}" title="Remove photo"><i class="fas fa-times"></i></div>
            `;
            photoPreviews.appendChild(div);
        });
        photoPreviews.querySelectorAll('.remove-photo').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.getAttribute('data-index'));
                removePhoto(idx);
            });
        });
        photoCount.textContent = selectedPhotos.length + ' / ' + MAX_PHOTOS + ' photos selected';
        validateFiles();
    }

    function updateFileInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(function(file) {
            if (file) dt.items.add(file);
        });
        photoInput.files = dt.files;
    }

    function removePhoto(index) {
        selectedPhotos.splice(index, 1);
        selectedFiles.splice(index, 1);
        updatePhotoPreviews();
        updateFileInput();
    }

    function addPhotoFromFile(file) {
        if (!validateFile(file)) return false;
        if (selectedPhotos.length >= MAX_PHOTOS) {
            showToast('Maximum ' + MAX_PHOTOS + ' photos allowed', 'warning');
            return false;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            selectedPhotos.push({ data: e.target.result, file: file });
            selectedFiles.push(file);
            updatePhotoPreviews();
            updateFileInput();
        };
        reader.readAsDataURL(file);
        return true;
    }

    function addPhotos(files) {
        for (let i = 0; i < files.length; i++) {
            if (selectedPhotos.length >= MAX_PHOTOS) {
                showToast('Maximum ' + MAX_PHOTOS + ' photos reached', 'warning');
                break;
            }
            addPhotoFromFile(files[i]);
        }
    }

    // ============================================================
    // ENHANCED CAMERA FUNCTIONS
    // ============================================================
    async function openCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showToast('Camera not supported on this device/browser', 'error');
            return;
        }
        try {
            const constraints = {
                video: { 
                    facingMode: facingMode,
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                },
                audio: false
            };
            mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = mediaStream;
            await video.play();
            
            cameraModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            const tipsOverlay = document.getElementById('cameraTips');
            tipsOverlay.style.display = 'block';
            tipsOverlay.style.opacity = '1';
            startCameraTips();
            
            flashMode = false;
            flashToggleBtn.classList.remove('active');
            
        } catch (error) {
            console.error('Camera error:', error);
            showToast('Unable to access camera. Please grant camera permission.', 'error');
        }
    }

    function closeCamera() {
        if (mediaStream) {
            mediaStream.getTracks().forEach(function(track) { track.stop(); });
            mediaStream = null;
        }
        video.srcObject = null;
        cameraModal.classList.remove('active');
        document.body.style.overflow = '';
        stopCameraTips();
    }

    function capturePhoto() {
        const context = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function(blob) {
            if (blob) {
                const file = new File([blob], 'camera_' + Date.now() + '.jpg', { type: 'image/jpeg' });
                if (addPhotoFromFile(file)) {
                    showToast('Photo captured successfully!', 'success');
                }
            }
            closeCamera();
        }, 'image/jpeg', 0.92);
    }

    async function switchCamera() {
        facingMode = (facingMode === 'environment') ? 'user' : 'environment';
        if (mediaStream) {
            closeCamera();
            await new Promise(resolve => setTimeout(resolve, 300));
            await openCamera();
        }
    }

    function toggleFlash() {
        flashMode = !flashMode;
        flashToggleBtn.classList.toggle('active', flashMode);
        if (mediaStream) {
            const track = mediaStream.getVideoTracks()[0];
            if (track) {
                const capabilities = track.getCapabilities();
                if (capabilities.torch) {
                    track.applyConstraints({ advanced: [{ torch: flashMode }] }).catch(() => {});
                } else {
                    showToast('Flash not supported on this device', 'info');
                    flashToggleBtn.classList.remove('active');
                    flashMode = false;
                }
            }
        }
    }

    // ============================================================
    // DUPLICATE DETECTION (UPDATED with category filter)
    // ============================================================
    async function checkNearbyReports(lat, lng) {
        if (isDuplicateCheckDone) return;
        const categoryId = document.getElementById('category_id').value || 0;
        try {
            const url = '<?php echo BASE_URL; ?>controllers/ReportController.php?action=check_nearby_reports&lat=' + lat + '&lng=' + lng + '&category_id=' + categoryId;
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            
            if (data.success && data.reports && data.reports.length > 0) {
                showDuplicateModal(data.reports);
                isDuplicateCheckDone = true;
            }
        } catch (error) {
            console.error('Duplicate check error:', error);
        }
    }

    function showDuplicateModal(reports) {
        duplicateReportList.innerHTML = '';
        duplicateReportId = null;
        
        reports.forEach(function(r, index) {
            const timeAgo = timeSince(new Date(r.created_at));
            const distance = r.distance_km ? (r.distance_km * 1000).toFixed(0) + 'm' : 'nearby';
            
            const div = document.createElement('div');
            div.className = 'report-item border border-gray-200 rounded-xl p-3 hover:bg-gray-50 transition cursor-pointer' + (index === 0 ? ' selected' : '');
            div.dataset.reportId = r.id;
            div.innerHTML = `
                <div class="flex justify-between items-start">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm text-gray-800 truncate">${escapeHtml(r.title)}</p>
                        <p class="text-xs text-gray-500">${escapeHtml(r.category_name)} • ${timeAgo}</p>
                        <p class="text-xs text-gray-400 mt-1 line-clamp-2">${escapeHtml(r.description || 'No description provided')}</p>
                    </div>
                    <span class="distance-badge flex-shrink-0 ml-2">${distance}</span>
                </div>
                <div class="mt-2 flex justify-end">
                    <button class="text-xs text-[#10A37F] font-medium hover:underline view-details-btn" data-report-id="${r.id}">
                        <i class="fas fa-eye mr-1"></i>View Details
                    </button>
                </div>
            `;
            
            // Click on the card itself selects it for support
            div.addEventListener('click', function(e) {
                if (e.target.closest('.view-details-btn')) return;
                duplicateReportList.querySelectorAll('.report-item').forEach(el => el.classList.remove('selected'));
                this.classList.add('selected');
                duplicateReportId = parseInt(this.dataset.reportId, 10);
            });
            
            // View Details button
            const detailsBtn = div.querySelector('.view-details-btn');
            detailsBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const reportId = parseInt(this.dataset.reportId, 10);
                openDetailsModal(reportId);
            });
            
            duplicateReportList.appendChild(div);
        });
        
        const first = duplicateReportList.querySelector('.report-item');
        if (first) {
            duplicateReportId = parseInt(first.dataset.reportId, 10);
        }
        
        duplicateModal.style.display = 'flex';
    }

    function closeDuplicateModal() {
        duplicateModal.style.display = 'none';
        isDuplicateCheckDone = false;
        dupYesBtn.disabled = false;
        dupNoBtn.disabled = false;
    }

    // ============================================================
    // DETAILS MODAL FUNCTIONS (UPDATED)
    // ============================================================
    function openDetailsModal(reportId) {
        viewingReportId = reportId;
        detailsModal.style.display = 'flex';
        detailsContent.innerHTML = `
            <div class="text-center py-8">
                <div class="loading-spinner"></div>
                <p class="text-gray-400 text-sm mt-2">Loading report details...</p>
            </div>
        `;
        supportFromDetailsBtn.disabled = false;
        supportFromDetailsBtn.innerHTML = '<i class="fas fa-thumbs-up"></i> Support This Report';
        supportFromDetailsBtn.style.opacity = '1';
        
        // Fetch report details and images
        Promise.all([
            fetchReportDetails(reportId),
            fetchReportImages(reportId)
        ]).then(([reportData, images]) => {
            renderReportDetails(reportData, images);
        }).catch(() => {
            detailsContent.innerHTML = `
                <div class="text-center py-8 text-red-500">
                    <i class="fas fa-exclamation-circle text-3xl"></i>
                    <p class="mt-2">Failed to load report details.</p>
                </div>
            `;
        });
    }

    function closeDetailsModal() {
        detailsModal.style.display = 'none';
        viewingReportId = null;
    }

    async function fetchReportDetails(reportId) {
        const url = '<?php echo BASE_URL; ?>controllers/ReportController.php?action=get_full&id=' + reportId;
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        if (!data || !data.id) throw new Error('No data');
        return data;
    }

    async function fetchReportImages(reportId) {
        const url = '<?php echo BASE_URL; ?>controllers/ReportController.php?action=get_images&id=' + reportId;
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        return data || [];
    }

    function renderReportDetails(data, images) {
        const statusColors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'under_review': 'bg-blue-100 text-blue-800',
            'verified': 'bg-blue-100 text-blue-800',
            'in_progress': 'bg-purple-100 text-purple-800',
            'escalated_pending': 'bg-orange-100 text-orange-800',
            'escalated': 'bg-orange-100 text-orange-800',
            'resolved': 'bg-green-100 text-green-800',
            'rejected': 'bg-red-100 text-red-800',
            'cancelled': 'bg-gray-100 text-gray-800'
        };
        const statusLabel = data.status ? data.status.replace('_', ' ') : 'Unknown';
        const statusClass = statusColors[data.status] || 'bg-gray-100 text-gray-800';
        
        const impactLabels = {
            0: 'Minor (Localized)',
            2: 'Moderate',
            4: 'Severe'
        };
        const impactValue = data.impact_modifier !== undefined ? data.impact_modifier : 0;
        const impactLabel = impactLabels[impactValue] || 'Unknown';
        
        const createdDate = data.created_at ? new Date(data.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A';
        const verifications = data.verification_count || 0;
        
        let html = `
            <!-- Title with support count and date -->
            <div class="border-b border-gray-200 pb-3 mb-3">
                <h4 class="text-xl font-bold text-gray-800">${escapeHtml(data.title)}</h4>
                <div class="flex flex-wrap items-center gap-3 mt-1 text-sm text-gray-500">
                    <span><i class="fas fa-thumbs-up text-[#10A37F]"></i> ${verifications} support${verifications != 1 ? 's' : ''}</span>
                    <span><i class="far fa-calendar-alt"></i> ${escapeHtml(createdDate)}</span>
                </div>
            </div>
            
            <!-- Category -->
            <div class="detail-row">
                <span class="label">Category</span>
                <span class="value">${escapeHtml(data.category_name)}</span>
            </div>
            
            <!-- Status -->
            <div class="detail-row">
                <span class="label">Status</span>
                <span class="value"><span class="px-2 py-0.5 rounded-full text-xs font-medium ${statusClass}">${escapeHtml(statusLabel)}</span></span>
            </div>
            
            <!-- Impact Modifier -->
            <div class="detail-row">
                <span class="label">Impact Modifier</span>
                <span class="value">${escapeHtml(impactLabel)}</span>
            </div>
        `;
        
        // Photos
        if (images && images.length > 0) {
            html += `
                <div class="detail-row flex-col">
                    <span class="label">Photos</span>
                    <div class="details-photos">
            `;
            images.forEach(function(img) {
                html += `<img src="${img.image_path}" alt="Report photo" onclick="window.open('${img.image_path}', '_blank')">`;
            });
            html += `
                    </div>
                </div>
            `;
        }
        
        // Description
        if (data.description) {
            html += `
                <div class="detail-row flex-col">
                    <span class="label">Description</span>
                    <span class="value text-sm leading-relaxed">${escapeHtml(data.description)}</span>
                </div>
            `;
        }
        
        detailsContent.innerHTML = html;
        supportFromDetailsBtn.dataset.reportId = data.id;
    }

    // ============================================================
    // SUPPORT FROM DETAILS MODAL
    // ============================================================
    supportFromDetailsBtn.addEventListener('click', function() {
        const reportId = parseInt(this.dataset.reportId, 10);
        if (!reportId) {
            showToast('No report selected.', 'error');
            return;
        }
        
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Supporting...';
        this.style.opacity = '0.6';
        
        const formData = new FormData();
        formData.append('action', 'upvote_report');
        formData.append('report_id', reportId);
        formData.append('csrf_token', getCsrfToken());
        
        fetch('<?php echo BASE_URL; ?>controllers/ReportController.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Thank you for supporting this report!', 'success');
                closeDetailsModal();
                closeDuplicateModal();
                window.location.href = '<?php echo BASE_URL; ?>index.php?page=my-reports';
            } else {
                showToast(data.message || 'Failed to support report.', 'error');
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-thumbs-up"></i> Support This Report';
                this.style.opacity = '1';
            }
        })
        .catch(error => {
            showToast('Error: ' + error.message, 'error');
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-thumbs-up"></i> Support This Report';
            this.style.opacity = '1';
        });
    });

    // ============================================================
    // UPVOTE FROM MAIN MODAL ("Yes, it's the same")
    // ============================================================
    function handleUpvote() {
        if (!duplicateReportId) {
            showToast('Please select a report from the list.', 'warning');
            return;
        }
        
        dupYesBtn.disabled = true;
        dupNoBtn.disabled = true;
        dupYesBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verifying...';
        
        const formData = new FormData();
        formData.append('action', 'upvote_report');
        formData.append('report_id', duplicateReportId);
        formData.append('csrf_token', getCsrfToken());
        
        fetch('<?php echo BASE_URL; ?>controllers/ReportController.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                window.location.href = '<?php echo BASE_URL; ?>index.php?page=my-reports';
            } else {
                showToast(data.message || 'Failed to verify report.', 'error');
                dupYesBtn.disabled = false;
                dupNoBtn.disabled = false;
                dupYesBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Yes, it\'s the same';
            }
        })
        .catch(function() {
            showToast('An error occurred. Please try again.', 'error');
            dupYesBtn.disabled = false;
            dupNoBtn.disabled = false;
            dupYesBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Yes, it\'s the same';
        });
    }

    // ============================================================
    // FORM RESET
    // ============================================================
    function resetForm() {
        if (selectedPhotos.length > 0 || descriptionInput.value.trim()) {
            if (!confirm('Are you sure? All entered data will be cleared.')) return;
        }
        reportForm.reset();
        selectedPhotos = [];
        selectedFiles = [];
        updatePhotoPreviews();
        updateFileInput();
        if (currentMarker && map) map.removeLayer(currentMarker);
        currentMarker = null;
        document.getElementById('latitude').value = '';
        document.getElementById('longitude').value = '';
        document.getElementById('barangay_id').value = '';
        document.getElementById('location_address').value = '';
        document.getElementById('coordDisplay').innerHTML = '<i class="fas fa-map-marker-alt mr-1"></i>No location selected';
        document.getElementById('locationStatus').innerHTML = '';
        photoCount.textContent = '0 / ' + MAX_PHOTOS + ' photos selected';
        document.querySelectorAll('.error-message').forEach(function(el) { el.classList.remove('visible'); });
        document.querySelectorAll('.form-input').forEach(function(el) { el.classList.remove('error'); });
        updateCharCount('description', 'description-count', 5000);
        if (map) map.setView([15.3092, 120.9033], 13);
        selectImpact(0);
        isDuplicateCheckDone = false;
        closeDuplicateModal();
        closeDetailsModal();
    }

    // ============================================================
    // FORM SUBMISSION
    // ============================================================
    reportForm.addEventListener('submit', function(e) {
        let isValid = true;
        descriptionInput.value = sanitizeRichText(descriptionInput.value);
        if (!validateCategory()) isValid = false;
        if (!validateImpact()) isValid = false;
        if (!validateLocation()) isValid = false;
        if (!validateFiles()) isValid = false;
        if (!validateDescription()) isValid = false;

        if (!isValid) {
            e.preventDefault();
            showToast('Please fix the errors before submitting', 'error');
            const firstError = document.querySelector('.error-message.visible');
            if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
        return true;
    });

    // ============================================================
    // EVENT LISTENERS
    // ============================================================
    descriptionInput.addEventListener('input', function() {
        updateCharCount('description', 'description-count', 5000);
    });

    descriptionInput.addEventListener('blur', function() {
        this.value = sanitizeRichText(this.value);
        validateDescription();
        updateCharCount('description', 'description-count', 5000);
    });

    descriptionInput.addEventListener('paste', function(e) {
        e.preventDefault();
        const pastedText = (e.clipboardData || window.clipboardData).getData('text/plain');
        const start = this.selectionStart;
        const end = this.selectionEnd;
        const currentValue = this.value;
        this.value = currentValue.substring(0, start) + pastedText + currentValue.substring(end);
        const newCursorPos = start + pastedText.length;
        this.selectionStart = newCursorPos;
        this.selectionEnd = newCursorPos;
        updateCharCount('description', 'description-count', 5000);
    });

    // CATEGORY CHANGE: re-check nearby reports
    categorySelect.addEventListener('change', function() {
        validateCategory();
        const lat = document.getElementById('latitude').value;
        const lng = document.getElementById('longitude').value;
        if (lat && lng) {
            isDuplicateCheckDone = false;
            closeDuplicateModal();
            checkNearbyReports(parseFloat(lat), parseFloat(lng));
        }
    });

    cameraBtn.addEventListener('click', openCamera);
    galleryBtn.addEventListener('click', function() { photoInput.click(); });
    uploadArea.addEventListener('click', function() { photoInput.click(); });
    closeCameraBtn.addEventListener('click', closeCamera);
    captureBtn.addEventListener('click', capturePhoto);
    resetBtn.addEventListener('click', resetForm);
    switchCameraBtn.addEventListener('click', switchCamera);
    flashToggleBtn.addEventListener('click', toggleFlash);
    
    // Map button event listeners
    getLocationBtn.addEventListener('click', getCurrentLocation);
    clearLocationBtn.addEventListener('click', function() {
        if (userMarker) {
            map.removeLayer(userMarker);
            userMarker = null;
        }
        latitudeInput.value = '';
        longitudeInput.value = '';
        document.getElementById('locationStatus').innerHTML = '';
        showToast('Location cleared', 'info');
    });

    // Duplicate Modal events
    dupYesBtn.addEventListener('click', handleUpvote);
    dupNoBtn.addEventListener('click', function() {
        closeDuplicateModal();
        showToast('You can now submit a new report for a different issue.', 'info');
    });

    // Close modal on background click
    duplicateModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDuplicateModal();
        }
    });

    // Close details modal on background click
    detailsModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailsModal();
        }
    });

    photoInput.addEventListener('change', function(e) {
        if (e.target.files && e.target.files.length > 0) {
            addPhotos(Array.from(e.target.files));
            this.value = '';
        }
    });

    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });
    uploadArea.addEventListener('dragleave', function(e) {
        this.classList.remove('drag-over');
    });
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            addPhotos(Array.from(e.dataTransfer.files));
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            reportForm.dispatchEvent(new Event('submit', { cancelable: true }));
        }
        if (e.key === 'Escape') {
            if (cameraModal.classList.contains('active')) closeCamera();
            if (detailsModal.style.display === 'flex') closeDetailsModal();
            if (duplicateModal.style.display === 'flex') closeDuplicateModal();
        }
    });

    // ============================================================
    // MAP FUNCTIONS
    // ============================================================
    const barangayMapping = {
        'alua': 1, 'Alua': 1, 'barangay alua': 1, 'brgy alua': 1, 'brgy. alua': 1,
        'calaba': 2, 'Calaba': 2, 'barangay calaba': 2, 'brgy calaba': 2, 'brgy. calaba': 2,
        'malapit': 3, 'Malapit': 3, 'barangay malapit': 3, 'brgy malapit': 3, 'brgy. malapit': 3,
        'mangga': 4, 'Mangga': 4, 'barangay mangga': 4, 'brgy mangga': 4, 'brgy. mangga': 4,
        'poblacion': 5, 'Poblacion': 5, 'barangay poblacion': 5, 'brgy poblacion': 5, 'brgy. poblacion': 5,
        'pulo': 6, 'Pulo': 6, 'barangay pulo': 6, 'brgy pulo': 6, 'brgy. pulo': 6,
        'san roque': 7, 'San Roque': 7, 'barangay san roque': 7, 'brgy san roque': 7, 'brgy. san roque': 7,
        'santo cristo': 8, 'Santo Cristo': 8, 'barangay santo cristo': 8, 'brgy santo cristo': 8, 'brgy. santo cristo': 8,
        'tabon': 9, 'Tabon': 9, 'barangay tabon': 9, 'brgy tabon': 9, 'brgy. tabon': 9
    };

    const sanIsidroBoundary = <?php echo json_encode($boundary_data); ?>;

    function isPointInSanIsidro(lat, lng) {
        if (!sanIsidroPolygon) return true;
        let inside = false;
        const x = lng, y = lat;
        for (let i = 0, j = sanIsidroPolygon.length - 1; i < sanIsidroPolygon.length; j = i++) {
            const xi = sanIsidroPolygon[i][0], yi = sanIsidroPolygon[i][1];
            const xj = sanIsidroPolygon[j][0], yj = sanIsidroPolygon[j][1];
            const intersect = ((yi > y) != (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
            if (intersect) inside = !inside;
        }
        return inside;
    }

    const customIcon = L.divIcon({
        html: '<div style="background-color: #10A37F; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(16, 163, 127, 0.4); border: 3px solid white;"><i class="fas fa-map-pin" style="color: white; font-size: 18px;"></i></div>',
        iconSize: [40, 40],
        className: 'custom-div-icon'
    });

    function extractPolygonCoordinates(geojson) {
        if (!geojson || !geojson.features) return null;
        for (const feature of geojson.features) {
            if (feature.geometry && feature.geometry.type === 'MultiPolygon') {
                return feature.geometry.coordinates[0][0].map(function(coord) { return [coord[1], coord[0]]; });
            }
        }
        return null;
    }

    function addBoundaryToMap() {
        if (sanIsidroBoundary && sanIsidroBoundary.features) {
            const polygonCoords = extractPolygonCoordinates(sanIsidroBoundary);
            if (polygonCoords) {
                sanIsidroPolygon = polygonCoords.map(function(coord) { return [coord[1], coord[0]]; });
                boundaryLayer = L.polygon(polygonCoords, {
                    color: "#10A37F", weight: 3, fillColor: "#10A37F", fillOpacity: 0.15, smoothFactor: 1
                }).addTo(map);
                map.fitBounds(boundaryLayer.getBounds());
            }
        }
    }

    function findBarangayId(barangayName) {
        if (!barangayName) return null;
        const cleanName = barangayName.toLowerCase().trim().replace(/^barangay\s+/, '').replace(/^brgy\.?\s+/, '');
        for (const [key, id] of Object.entries(barangayMapping)) {
            if (cleanName === key.toLowerCase()) return id;
        }
        for (const [key, id] of Object.entries(barangayMapping)) {
            const cleanKey = key.toLowerCase().replace(/^barangay\s+/, '').replace(/^brgy\.?\s+/, '');
            if (cleanName.includes(cleanKey) || cleanKey.includes(cleanName)) return id;
        }
        return null;
    }

    function getBarangayNameFromId(barangayId) {
        const mapping = { 1: 'Alua', 2: 'Calaba', 3: 'Malapit', 4: 'Mangga', 5: 'Poblacion', 6: 'Pulo', 7: 'San Roque', 8: 'Santo Cristo', 9: 'Tabon' };
        return mapping[barangayId] || '';
    }

    async function getDetailedAddress(lat, lng) {
        try {
            const response = await fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&zoom=18&addressdetails=1&accept-language=en,fil&countrycodes=ph', {
                headers: { 'User-Agent': 'EnviroTrack/1.0' }
            });
            if (!response.ok) throw new Error('Nominatim request failed');
            const data = await response.json();
            if (data && data.address) {
                const address = data.address;
                let barangayName = (address.village || address.suburb || address.neighbourhood || address.hamlet || address.city_district || address.district || '').replace(/^Barangay\s+/i, '').trim();
                let barangayId = findBarangayId(barangayName);
                if (!barangayId && data.display_name) {
                    const parts = data.display_name.split(',');
                    for (const part of parts) {
                        const foundId = findBarangayId(part.trim());
                        if (foundId) { barangayId = foundId; barangayName = getBarangayNameFromId(foundId); break; }
                    }
                }
                let street = address.road || address.pedestrian || address.path || address.street || address.footway || address.residential || '';
                const addressParts = [];
                if (street) addressParts.push(street);
                if (barangayName) addressParts.push(barangayName);
                addressParts.push('San Isidro', 'Nueva Ecija');
                return { street: street, barangay: barangayName, barangay_id: barangayId, municipality: 'San Isidro', province: 'Nueva Ecija', country: 'Philippines', postcode: '3106', fullAddress: addressParts.filter(function(p) { return p; }).join(', '), displayName: data.display_name || '', source: 'nominatim' };
            }
            return await getAddressFromPhoton(lat, lng);
        } catch (error) {
            console.error('Nominatim error:', error);
            return await getAddressFromPhoton(lat, lng);
        }
    }

    async function getAddressFromPhoton(lat, lng) {
        try {
            const response = await fetch('https://photon.komoot.io/reverse?lat=' + lat + '&lon=' + lng + '&lang=en');
            if (!response.ok) throw new Error('Photon request failed');
            const data = await response.json();
            if (data && data.features && data.features.length > 0) {
                const props = data.features[0].properties;
                let barangayName = (props.suburb || props.neighbourhood || props.district || props.village || '').replace(/^Barangay\s+/i, '').trim();
                let barangayId = findBarangayId(barangayName);
                let street = props.street || props.name || '';
                const addressParts = [];
                if (street) addressParts.push(street);
                if (barangayName) addressParts.push(barangayName);
                addressParts.push('San Isidro', 'Nueva Ecija');
                return { street: street, barangay: barangayName, barangay_id: barangayId, municipality: 'San Isidro', province: 'Nueva Ecija', country: 'Philippines', postcode: '3106', fullAddress: addressParts.filter(function(p) { return p; }).join(', '), displayName: props.name || '', source: 'photon' };
            }
            return { street: '', barangay: '', barangay_id: null, municipality: 'San Isidro', province: 'Nueva Ecija', country: 'Philippines', postcode: '3106', fullAddress: 'Location at ' + lat.toFixed(6) + ', ' + lng.toFixed(6), displayName: lat.toFixed(6) + ', ' + lng.toFixed(6), source: 'fallback' };
        } catch (error) {
            console.error('Photon error:', error);
            return { street: '', barangay: '', barangay_id: null, municipality: 'San Isidro', province: 'Nueva Ecija', country: 'Philippines', postcode: '3106', fullAddress: 'Location at ' + lat.toFixed(6) + ', ' + lng.toFixed(6), displayName: lat.toFixed(6) + ', ' + lng.toFixed(6), source: 'fallback' };
        }
    }

    function displayLocationDetails(addressComponents, lat, lng) {
        const statusDiv = document.getElementById('locationStatus');
        const isInSanIsidro = isPointInSanIsidro(lat, lng);
        if (!isInSanIsidro) {
            statusDiv.innerHTML = '<div class="bg-red-50 border border-red-200 rounded-xl p-3 mt-2"><div class="flex items-start gap-2"><i class="fas fa-exclamation-triangle text-red-600 mt-0.5"></i><div class="flex-1"><p class="text-sm font-semibold text-red-800">Location Outside San Isidro</p><p class="text-xs text-red-600 mt-1">Please select a location within San Isidro, Nueva Ecija.</p></div></div></div>';
            return false;
        }
        if (addressComponents) {
            const barangayDisplay = addressComponents.barangay || 'Not detected';
            const streetDisplay = addressComponents.street || '';
            let locationText = '';
            if (streetDisplay && barangayDisplay !== 'Not detected') locationText = streetDisplay + ', ' + barangayDisplay;
            else if (streetDisplay) locationText = streetDisplay;
            else if (barangayDisplay !== 'Not detected') locationText = barangayDisplay;
            else locationText = 'Location selected';
            statusDiv.innerHTML = '<div class="bg-green-50 border border-green-200 rounded-xl p-3 mt-2"><div class="flex items-start gap-2"><i class="fas fa-check-circle text-green-600 mt-0.5"></i><div class="flex-1"><p class="text-sm font-semibold text-green-800"><i class="fas fa-map-pin mr-1"></i>' + locationText + '</p><p class="text-xs text-gray-500 mt-1">' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</p></div></div></div>';
            document.getElementById('location_address').value = addressComponents.fullAddress;
            document.getElementById('barangay_id').value = addressComponents.barangay_id || '';
            return true;
        } else {
            statusDiv.innerHTML = '<div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 mt-2"><div class="flex items-start gap-2"><i class="fas fa-map-marker-alt text-yellow-600 mt-0.5"></i><div class="flex-1"><p class="text-sm font-semibold text-yellow-800">Location Selected</p><p class="text-xs text-yellow-600 mt-1">Coordinates: ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</p></div></div></div>';
            document.getElementById('location_address').value = lat + ', ' + lng;
            return true;
        }
    }

    async function setLocation(lat, lng, showPopup) {
        if (showPopup === undefined) showPopup = true;
        if (!isPointInSanIsidro(lat, lng)) {
            document.getElementById('locationStatus').innerHTML = '<div class="bg-red-50 border border-red-200 rounded-xl p-3 mt-2"><p class="text-sm text-red-800"><i class="fas fa-exclamation-circle mr-1"></i>Outside San Isidro boundary</p></div>';
            return false;
        }
        document.getElementById('locationStatus').innerHTML = '<div class="bg-blue-50 border border-blue-200 rounded-xl p-3 mt-2"><div class="flex items-center gap-2"><div class="loading-spinner"></div><span class="text-sm text-blue-700">Fetching location details...</span></div></div>';
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
        document.getElementById('coordDisplay').innerHTML = '<i class="fas fa-map-marker-alt mr-1"></i>' + lat.toFixed(6) + ', ' + lng.toFixed(6);
        if (currentMarker) map.removeLayer(currentMarker);
        currentMarker = L.marker([lat, lng], { icon: customIcon }).addTo(map);
        map.setView([lat, lng], 18);
        const addressComponents = await getDetailedAddress(lat, lng);
        const isValid = displayLocationDetails(addressComponents, lat, lng);
        hideFieldError('map');
        if (showPopup && currentMarker && isValid) {
            const locationText = addressComponents && addressComponents.street ? addressComponents.street + (addressComponents.barangay ? ', ' + addressComponents.barangay : '') : lat.toFixed(6) + ', ' + lng.toFixed(6);
            currentMarker.bindPopup('<div style="text-align:center"><strong><i class="fas fa-map-pin mr-1"></i>Selected Location</strong><br><span style="font-size:11px;">' + locationText + '</span></div>').openPopup();
        }
        closeMapTip();

        // ===== Check for nearby reports after location is set =====
        if (isValid) {
            setTimeout(function() {
                checkNearbyReports(lat, lng);
            }, 800);
        }

        return isValid;
    }

    function getCurrentLocation() {
        if (!navigator.geolocation) { showToast('Geolocation not supported by your browser', 'error'); return; }
        document.getElementById('locationStatus').innerHTML = '<div class="bg-blue-50 border border-blue-200 rounded-xl p-3 mt-2"><div class="flex items-center gap-2"><div class="loading-spinner"></div><span class="text-sm text-blue-700">Getting your location...</span></div></div>';
        navigator.geolocation.getCurrentPosition(
            async function(position) {
                const lat = position.coords.latitude, lng = position.coords.longitude;
                if (!isPointInSanIsidro(lat, lng)) {
                    document.getElementById('locationStatus').innerHTML = '<div class="bg-red-50 border border-red-200 rounded-xl p-3 mt-2"><p class="text-sm text-red-800"><i class="fas fa-exclamation-circle mr-1"></i>You are outside San Isidro</p></div>';
                    return;
                }
                await setLocation(lat, lng, true);
            },
            function(error) {
                let msg = 'Unable to get location';
                if (error.code === error.PERMISSION_DENIED) msg = 'Location permission denied';
                else if (error.code === error.TIMEOUT) msg = 'Location request timed out';
                document.getElementById('locationStatus').innerHTML = '<div class="bg-red-50 border border-red-200 rounded-xl p-3 mt-2"><p class="text-sm text-red-700"><i class="fas fa-exclamation-triangle mr-1"></i>' + msg + '</p></div>';
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    function clearLocationData() {
        if (currentMarker) map.removeLayer(currentMarker);
        currentMarker = null;
        document.getElementById('latitude').value = '';
        document.getElementById('longitude').value = '';
        document.getElementById('barangay_id').value = '';
        document.getElementById('location_address').value = '';
        document.getElementById('coordDisplay').innerHTML = '<i class="fas fa-map-marker-alt mr-1"></i>No location selected';
        document.getElementById('locationStatus').innerHTML = '';
        showFieldError('map', 'Please select a location on the map');
        isDuplicateCheckDone = false;
        closeDuplicateModal();
        closeDetailsModal();
    }

    function initMap() {
        map = L.map('map').setView([15.3092, 120.9033], 14);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, &copy; CartoDB',
            subdomains: 'abcd', maxZoom: 20
        }).addTo(map);
        addBoundaryToMap();
        map.on('click', function(e) { 
            setLocation(e.latlng.lat, e.latlng.lng, true); 
            showMapTip();
        });
        L.control.scale({ imperial: false, metric: true }).addTo(map);
        setTimeout(showMapTip, 1500);
    }

    // ============================================================
    // INITIALIZATION
    // ============================================================
    initMap();
    updateCharCount('description', 'description-count', 5000);
    bindImpactOptions();
    selectImpact(0);

    window.closeDetailsModal = closeDetailsModal;
    window.closeDuplicateModal = closeDuplicateModal;

    // Clean up duplicate modal if user refreshes
    window.addEventListener('beforeunload', function() {
        closeDuplicateModal();
        closeDetailsModal();
    });

    setTimeout(function() {
        document.getElementById('categoryTipBox').style.display = 'block';
        setTimeout(function() {
            document.getElementById('categoryTipBox').style.display = 'none';
        }, 6000);
    }, 2000);

})();
</script>

</body>
</html>