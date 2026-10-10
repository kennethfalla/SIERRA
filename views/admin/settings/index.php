<?php
// views/admin/settings/index.php - Unified Settings Dashboard
// Complete with tab navigation, responsive design, and all setting types

require_once dirname(__DIR__, 3) . '/config/config.php';
require_once BASE_PATH . 'helpers/SecurityHelper.php';
require_once BASE_PATH . 'helpers/SettingsHelper.php';
require_once BASE_PATH . 'helpers/Lang.php';
requireRole('admin');

// Get active tab from URL
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
$system_name = SettingsHelper::get('system_name', 'Sierra');

// Define all setting tabs organized into categories
$navigation_groups = [
    'Website Look & Info' => [
        'general' => [
            'label' => t('General'),
            'icon' => 'fa-cog',
            'description' => t('System name, logo, and contact information'),
            'file' => 'general.php'
        ],
        'landing' => [
            'label' => t('Landing Page'),
            'icon' => 'fa-home',
            'description' => t('Edit all content shown on the public homepage'),
            'file' => 'landing.php'
        ],
        'barangays' => [
            'label' => t('Barangays'),
            'icon' => 'fa-building',
            'description' => t('Manage barangay information'),
            'file' => 'barangays.php'
        ]
    ],
    'User Access & Safety' => [
        'permissions' => [
            'label' => t('Permissions'),
            'icon' => 'fa-user-lock',
            'description' => t('Role-based access control'),
            'file' => 'permissions.php'
        ],
        'security' => [
            'label' => t('Security'),
            'icon' => 'fa-shield-alt',
            'description' => t('Password policies and login security'),
            'file' => 'security.php'
        ]
    ],
    'Report Rules & Automation' => [
        'categories' => [
            'label' => t('Categories'),
            'icon' => 'fa-tags',
            'description' => t('Manage report categories and severity weights'),
            'file' => 'categories.php'
        ],
        'category_keywords' => [
            'label' => t('Category Keywords'),
            'icon' => 'fa-key',
            'description' => t('Auto-correction dictionary — trigger words that auto-suggest a category while residents type'),
            'file' => 'category_keywords.php'
        ],
        'quick_notes' => [
            'label' => t('Quick Note Templates'),
            'icon' => 'fa-bolt',
            'description' => t('Smart suggestion templates for investigation & resolution notes'),
            'file' => 'quick_notes.php'
        ],
        'reporting' => [
            'label' => t('Report Settings'),
            'icon' => 'fa-gauge-high',
            'description' => t('Submission limits and report follow-up reminders'),
            'file' => 'reporting.php'
        ],
        'algorithm' => [
            'label' => t('Algorithm'),
            'icon' => 'fa-calculator',
            'description' => t('Severity scoring configuration'),
            'file' => 'algorithm.php'
        ],
        'features' => [
            'label' => t('Features & Kill Switches'),
            'icon' => 'fa-exclamation-triangle',
            'description' => t('Master kill switches — turn features on/off instantly without touching code'),
            'file' => 'features.php'
        ]
    ],
    'Maps & Alerts' => [
        'map' => [
            'label' => t('Map'),
            'icon' => 'fa-map',
            'description' => t('Clustering radius and map settings'),
            'file' => 'map.php'
        ],
        'kpi' => [
            'label' => t('KPI & Insights'),
            'icon' => 'fa-chart-pie',
            'description' => t('Key performance indicator targets for the Insight Engine'),
            'file' => 'kpi.php'
        ],
        'notifications' => [
            'label' => t('Notifications'),
            'icon' => 'fa-envelope',
            'description' => t('Email and SMS templates'),
            'file' => 'notifications.php'
        ]
    ],
    'Data & Downloads' => [
        'archiving' => [
            'label' => t('Data Archiving & Retention'),
            'icon' => 'fa-archive',
            'description' => t('Manually archive old reports, retain rejected/spam, and manage the archive'),
            'file' => 'archiving.php'
        ],
        'pdf_export' => [
            'label' => t('PDF Export'),
            'icon' => 'fa-file-pdf',
            'description' => t('MENRO PDF Analytics Export — official LGU header, logos, and signatory block'),
            'file' => 'pdf_export.php'
        ]
    ]
];

// Flatten tabs for lookup
$tabs = [];
foreach ($navigation_groups as $category_tabs) {
    $tabs = array_merge($tabs, $category_tabs);
}

// Ensure the active tab exists
if (!isset($tabs[$active_tab])) {
    $active_tab = 'general';
}

// Generate CSRF token for forms
$csrf_token = InputSanitizer::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="<?php echo BASE_URL; ?>assets/js/theme.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/theme.js'); ?>"></script>
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo t('Settings'); ?> - <?php echo htmlspecialchars($system_name); ?></title>
    <link href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/material-symbols.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/material-symbols.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/export-print.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/buttons.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/buttons.css'); ?>">
<script src="<?php echo BASE_URL; ?>assets/js/app-ui.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/app-ui.js'); ?>" defer></script>
    <!-- Leaflet Map (required by the Map settings tab preview) -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/leaflet/leaflet.css" />
    <script src="<?php echo BASE_URL; ?>assets/vendor/leaflet/leaflet.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/leaflet-stub.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/map-layers.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/map-layers.js'); ?>"></script>
    <!-- Network hints for slow connections (map tiles / reverse geocoding) -->
    <link rel="dns-prefetch" href="https://tile.openstreetmap.org">
    <link rel="preconnect" href="https://tile.openstreetmap.org" crossorigin>
    <link rel="preconnect" href="https://tile.openstreetmap.appspot.com" crossorigin>
    <link rel="dns-prefetch" href="https://nominatim.openstreetmap.org">
    <link rel="dns-prefetch" href="https://photon.komoot.io">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: #F5FBF6; }
        
        /* ===== RESPONSIVE SIDEBAR ===== */
        @media (max-width: 768px) {
            .ml-72 { margin-left: 0 !important; }
        }

        /* ===== HIDE SETTINGS NAV TOGGLE ===== */
        body.settings-nav-hidden .settings-layout {
            grid-template-columns: 1fr;
        }
        body.settings-nav-hidden .settings-sidebar {
            display: none;
        }
        /* Compact page header button on small phones */
        @media (max-width: 480px) {
            #navToggleLabel { display: none; }
            #navToggleBtn { padding: 0.5rem 0.65rem; }
        }
        
        /* ===== CONTAINER ===== */
        .main-container {
            padding: 1rem;
            max-width: 1280px;
            margin: 0 auto;
        }
        @media (min-width: 640px) {
            .main-container { padding: 1.5rem; }
        }
        @media (min-width: 768px) {
            .main-container { padding: 2rem; }
        }
        
        /* ===== SETTINGS LAYOUT ===== */
        .settings-layout {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            gap: 1.5rem;
        }
        @media (max-width: 768px) {
            .settings-layout {
                grid-template-columns: 1fr;
            }
        }
        
        /* ===== SIDEBAR ===== */
        .settings-sidebar {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(16, 163, 127, 0.08);
            padding: 0.75rem;
            height: fit-content;
        }

        /* ===== APP SHELL (desktop): the tab list and the content pane each
           scroll on their own; the page itself does not scroll. ===== */
        @media (min-width: 1024px) {
            #main-content { height: 100vh; min-height: 0; overflow: hidden; }
            #main-content .main-container { height: 100%; display: flex; flex-direction: column; }
            #main-content .settings-layout { flex: 1 1 auto; min-height: 0; }
            #main-content .settings-sidebar {
                height: 100%;
                max-height: 100%;
                overflow-y: auto;
                overscroll-behavior: contain;
                scrollbar-width: thin;
                padding-right: 0.5rem;
            }
            #main-content .settings-content {
                height: 100%;
                max-height: 100%;
                overflow-y: auto;
                overscroll-behavior: contain;
            }
        }
        @media (max-width: 768px) {
            .settings-sidebar {
                position: static;
                overflow-x: auto;
                overflow-y: visible;
                max-height: none;
                display: flex;
                flex-wrap: nowrap;
                gap: 0.25rem;
                padding: 0.5rem;
                scrollbar-width: none;
            }
            .settings-sidebar::-webkit-scrollbar {
                display: none;
            }
            .category-header {
                display: none;
            }
            .category-spacer {
                display: none;
            }
        }
        
        /* ===== CATEGORY HEADERS ===== */
        .category-header {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--sierra-type-muted, #63746b);
            padding: 0.75rem 0.9rem 0.4rem;
            margin-top: 0.5rem;
        }
        .category-header:first-child {
            margin-top: 0;
        }
        .category-spacer {
            height: 0.75rem;
        }
        
        /* ===== TAB ITEMS ===== */
        .settings-tab {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 0.9rem;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--sierra-type-muted, #63746b);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            width: 100%;
            border: none;
            background: transparent;
            text-align: left;
        }
        .settings-tab:hover {
            background: #f0fdf4;
            color: #10A37F;
        }
        .settings-tab.active {
            background: #10A37F;
            color: white;
            box-shadow: 0 2px 8px rgba(16, 163, 127, 0.2);
        }
        .settings-tab .tab-icon {
            width: 1.5rem;
            text-align: center;
            flex-shrink: 0;
        }
        .settings-tab .tab-label {
            flex: 1;
        }
        .settings-tab .tab-badge {
            background: rgba(255,255,255,0.2);
            padding: 0.05rem 0.5rem;
            border-radius: 0.5rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .settings-tab.active .tab-badge {
            background: rgba(255,255,255,0.25);
        }
        
        @media (max-width: 768px) {
            .settings-tab {
                white-space: nowrap;
                padding: 0.5rem 0.75rem;
                width: auto;
                flex-shrink: 0;
            }
            .settings-tab .tab-label {
                display: inline;
            }
            .settings-tab .tab-description {
                display: none;
            }
        }
        
        /* ===== CONTENT AREA ===== */
        .settings-content {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(16, 163, 127, 0.08);
            padding: 1.5rem;
        }
        .settings-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .settings-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--sierra-type-primary, #203b31);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .settings-header p {
            color: var(--sierra-type-muted, #63746b);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        /* ===== FORM ELEMENTS ===== */
        .form-input {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1.5px solid #e5ece8;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            transition: all 0.2s;
            background: white;
            color: var(--sierra-type-primary, #203b31);
        }
        .form-input:focus {
            border-color: #10A37F;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08);
        }
        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #4d6b4a;
            margin-bottom: 0.25rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        /* ===== BUTTONS ===== */
        .btn-primary {
            background: linear-gradient(135deg, #10A37F, #0D8568);
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
        }
        .btn-secondary {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 0.6rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 500;
            color: var(--sierra-type-muted, #63746b);
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-secondary:hover {
            background: #f8fafc;
        }
        
        /* ===== TOGGLE SWITCH ===== */
        .toggle-switch {
            position: relative;
            width: 48px;
            height: 28px;
            flex-shrink: 0;
            cursor: pointer;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            inset: 0;
            background: #d1d5db;
            border-radius: 9999px;
            transition: all 0.3s;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .toggle-switch input:checked + .toggle-slider {
            background: #10A37F;
        }
        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(20px);
        }
        
        /* ===== UPLOAD AREA ===== */
        .upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .upload-area:hover {
            border-color: #10A37F;
            background: #f0fdf4;
        }
        .upload-area.dragover {
            border-color: #10A37F;
            background: #d1fae5;
        }
        .logo-preview {
            width: 120px;
            height: 120px;
            object-fit: contain;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            background: white;
        }
        
        /* ===== TABLE ===== */
        .table-container {
            overflow-x: auto;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
        }
        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-container th {
            background: #f9fafb;
            padding: 0.6rem 1rem;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--sierra-type-muted, #63746b);
            border-bottom: 1px solid #e5e7eb;
        }
        .table-container td {
            padding: 0.6rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.85rem;
        }
        .table-container tr:hover td {
            background: #fafafa;
        }
        
        /* ===== TAG ITEMS ===== */
        .tag-item {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid #e5e7eb;
            background: white;
        }
        .tag-item .tag-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .tag-item .tag-remove {
            cursor: pointer;
            color: var(--sierra-type-muted, #63746b);
            transition: color 0.2s;
        }
        .tag-item .tag-remove:hover {
            color: #ef4444;
        }
        
        /* ===== FLASH MESSAGES ===== */
        .flash-success {
            background: #f0fdf4;
            border-left: 4px solid #10A37F;
            color: #065f46;
        }
        .flash-error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }

        /* ===== MAP CONTAINER (matches header.php) ===== */
        .map-container {
            border-radius: 1rem;
            overflow: hidden;
            border: 1px solid rgba(16, 163, 127, 0.2);
        }

        /* ===== FADE-IN ANIMATION ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.3s ease-out; }

        /* ===== TOAST NOTIFICATION ===== */
        .settings-toast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.85rem;
            font-weight: 500;
            color: white;
            z-index: 9999;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            animation: fadeIn 0.25s ease-out;
            max-width: 320px;
        }
        .settings-toast.info    { background: #10A37F; }
        .settings-toast.success { background: #059669; }
        .settings-toast.error   { background: #ef4444; }

        /* ===== UNSAVED CHANGES MODAL ===== */
        .settings-unsaved-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 10001;
            background: rgba(17, 24, 39, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .settings-unsaved-modal-overlay.show { display: flex; }
        #unsavedModal { align-items:flex-end; background:rgba(17,24,39,.18); }
        #unsavedModal .settings-unsaved-modal { max-width:680px; padding:1.1rem 1.25rem; border:1px solid #cde5d7; box-shadow:0 12px 40px rgba(21,65,43,.2); }
        #unsavedModal .sum-actions { flex-wrap:wrap; }
        @media(max-width:600px) { #unsavedModal .sum-actions button { flex:1 1 130px; } }
        .settings-unsaved-modal {
            background: #ffffff;
            border-radius: 1rem;
            max-width: 440px;
            width: 100%;
            padding: 1.5rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
            animation: fadeIn 0.2s ease-out;
        }
        .settings-unsaved-modal h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--sierra-type-primary, #203b31);
            margin-bottom: 0.4rem;
        }
        .settings-unsaved-modal p {
            font-size: 0.85rem;
            color: var(--sierra-type-muted, #63746b);
            line-height: 1.5;
            margin-bottom: 1.25rem;
        }
        .settings-unsaved-modal .sum-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
        }
        .settings-unsaved-modal .sum-actions .btn-primary,
        .settings-unsaved-modal .sum-actions .btn-secondary {
            flex: 1 1 auto;
            min-width: 130px;
            justify-content: center;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* ===== SAVE VALIDATION ===== */
        .settings-content .is-invalid {
            border-color: #ef4444 !important;
            border-width: 1.5px !important;
            background-color: #fff5f5 !important;
        }
        .settings-content .is-invalid:focus {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
            outline: none;
        }
        .settings-content .field-error {
            color: #b91c1c;
            font-size: 0.78rem;
            font-weight: 500;
            margin-top: 0.35rem;
        }
        .settings-content .field-error i {
            margin-right: 0.3rem;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 640px) {
            .settings-content { padding: 1rem; }
            .settings-header { margin-bottom: 1rem; padding-bottom: 0.75rem; }
            .settings-toast { left: 1rem; right: 1rem; max-width: none; }
        }
        @media (max-width: 480px) {
            .main-container { padding: 0.75rem; }
        }

        /* ===== SETTINGS REDESIGN (screenshot-style, on-brand) ===== */
        #main-content { background: #f3f6f4; }
        .settings-layout { grid-template-columns: 300px minmax(0, 1fr); gap: 1.25rem; }

        .settings-sidebar {
            background: #fff;
            border: 1px solid #e6efe9;
            border-radius: 1.1rem;
            padding: 0.75rem 0.6rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
        }
        .category-header {
            font-size: .62rem;
            letter-spacing: .12em;
            color: #93a59c;
            padding: 1rem 0.85rem .45rem;
            margin-top: 0;
        }
        .category-spacer { height: .35rem; }
        .settings-tab {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            padding: .6rem .65rem;
            border-radius: .8rem;
            color: #3f5349;
            font-weight: 600;
            font-size: .82rem;
            transition: background .18s ease, color .18s ease;
        }
        .settings-tab .tab-icon {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            border-radius: 10px;
            background: #f1f6f3;
            color: #5b7a6c;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
            transition: background .18s ease, color .18s ease;
            text-align: center;
        }
        .settings-tab .tab-copy { display: flex; flex-direction: column; min-width: 0; gap: .1rem; flex: 1; }
        .settings-tab .tab-label { font-weight: 700; color: inherit; }
        .settings-tab .tab-description {
            display: block;
            font-size: .68rem;
            font-weight: 500;
            color: #93a59c;
            line-height: 1.35;
            white-space: normal;
        }
        .settings-tab .tab-badge { align-self: center; background: #e6f6ee; color: #0d8568; font-weight: 700; }
        .settings-tab:hover { background: #f2f8f5; color: #0d8568; }
        .settings-tab:hover .tab-icon { background: #e2f3ea; color: #0d8568; }
        .settings-tab.active { background: #e7f6ee; color: #0d8568; box-shadow: none; }
        .settings-tab.active .tab-icon { background: #0d8568; color: #fff; }
        .settings-tab.active .tab-description { color: #4e8f77; }
        .settings-tab.active .tab-badge { background: #d8f0e6; color: #0d8568; }

        .settings-content { background: transparent; border: 0; padding: 0; border-radius: 0; }
        #main-content .settings-content { padding: 0 0 5rem; }

        .settings-header {
            display: flex;
            align-items: center;
            gap: .9rem;
            background: #fff;
            border: 1px solid #e6efe9;
            border-radius: 1.1rem;
            padding: 1.1rem 1.25rem;
            margin-bottom: 1.1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
        }
        .settings-header .settings-header-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: #e7f6ee;
            color: #0d8568;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .settings-header .settings-header-copy { min-width: 0; flex: 1; }
        .settings-header h2 { font-size: 1.15rem; font-weight: 800; color: #16271f; border: 0; padding: 0; }
        .settings-header p { margin: .15rem 0 0; color: #7c8f87; font-size: .82rem; }
        .settings-sync-pill {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .5rem .85rem;
            border-radius: 999px;
            background: #f2f8f5;
            color: #0d8568;
            font-size: .72rem;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .settings-sync-pill .dot { width: 8px; height: 8px; border-radius: 50%; background: #10A37F; box-shadow: 0 0 0 3px #d8f0e6; }

        .settings-card {
            background: #fff;
            border: 1px solid #e6efe9;
            border-radius: 1.1rem;
            padding: 1.25rem 1.35rem;
            margin-bottom: 1.1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
        }
        .settings-card > h3,
        .settings-card .settings-card-title { margin: 0; font-size: 1rem; font-weight: 800; color: #16271f; }
        .settings-card .settings-card-sub { margin: .25rem 0 1.1rem; color: #7c8f87; font-size: .82rem; line-height: 1.5; }

        .settings-content .form-group { margin-bottom: 1.1rem; }
        .settings-content .form-label { display: block; font-size: .82rem; font-weight: 700; color: #35473e; margin-bottom: .35rem; }
        .settings-content .form-input {
            width: 100%;
            padding: .68rem .85rem;
            border: 1.5px solid #e2ece6;
            border-radius: .8rem;
            font-size: .88rem;
            background: #fbfdfc;
            color: var(--sierra-type-primary, #203b31);
        }
        .settings-content .form-input:focus { border-color: #10A37F; background: #fff; box-shadow: 0 0 0 3px rgba(16, 163, 127, .1); }
        .settings-content .form-group > p { color: #93a59c; }

        .settings-callout { border: 1.5px solid #cdeadd; background: #f2fbf6; border-radius: .9rem; padding: 1rem 1.1rem; }
        .settings-callout .settings-callout-badge {
            display: inline-flex; align-items: center; gap: .35rem;
            background: #fde9e7; color: #c2410c;
            font-size: .62rem; font-weight: 800; letter-spacing: .04em;
            padding: .2rem .5rem; border-radius: 999px; text-transform: uppercase;
        }
        .settings-verified {
            display: inline-flex; align-items: center; gap: .3rem;
            background: #e7f6ee; color: #0d8568;
            font-size: .64rem; font-weight: 800;
            padding: .18rem .5rem; border-radius: 999px;
        }

        .settings-footer {
            position: sticky;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1.2rem;
            padding: .85rem 1.1rem;
            background: #fff;
            border: 1px solid #e6efe9;
            border-radius: 1rem;
            box-shadow: 0 -8px 30px -22px rgba(15, 23, 42, .5);
            flex-wrap: wrap;
            z-index: 20;
        }
        .settings-footer[hidden] { display: none; }
        .settings-footer-msg { display: inline-flex; align-items: center; gap: .5rem; color: #7c8f87; font-size: .8rem; font-weight: 600; }
        .settings-footer-msg i { color: #f59e0b; }
        .settings-footer-actions { display: flex; align-items: center; gap: .6rem; margin-left: auto; }
        .settings-btn-ghost {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .6rem 1rem; border-radius: .8rem;
            border: 1px solid #dbe8e0; background: #fff; color: #4b5f57;
            font-weight: 700; font-size: .82rem; cursor: pointer;
        }
        .settings-btn-ghost:hover { background: #f3f7f5; }
        .settings-btn-primary {
            display: inline-flex; align-items: center; gap: .45rem;
            padding: .6rem 1.15rem; border-radius: .8rem;
            border: 0; background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            color: #fff; font-weight: 700; font-size: .82rem; cursor: pointer;
            box-shadow: 0 10px 20px -12px rgba(16, 163, 127, .8);
        }
        .settings-btn-primary:hover { transform: translateY(-1px); }

        /* ===== Card look for EVERY partial's content sections ===== */
        .settings-content :is(
            .card-info, .barangay-section, .ks-card, .template-card, .perm-card,
            .rl-card, .map-card, .stat-card, .ckw-stats, .qnt-stats, .settings-section,
            .settings-card, .seg-card, .security-card, .sec-card
        ) {
            background: #fff;
            border: 1px solid #e6efe9;
            border-radius: 1.1rem;
            padding: 1.25rem 1.35rem;
            margin-bottom: 1.1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
        }
        .settings-content :is(.stat-cards, .ckw-stats, .qnt-stats) { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .85rem; }

        /* ===== RESPONSIVE: tablets & phones ===== */
        @media (max-width: 1023px) {
            .settings-layout { grid-template-columns: 1fr; gap: .9rem; }
            #main-content { height: auto; min-height: 100vh; overflow: visible; }
            #main-content .main-container { height: auto; display: block; }
            .settings-sidebar {
                position: sticky;
                top: 60px;
                z-index: 30;
                display: flex;
                flex-wrap: nowrap;
                gap: .4rem;
                padding: .5rem;
                overflow-x: auto;
                overflow-y: hidden;
                scrollbar-width: none;
                -webkit-overflow-scrolling: touch;
            }
            .settings-sidebar::-webkit-scrollbar { display: none; }
            .category-header, .category-spacer { display: none; }
            .settings-tab {
                width: auto;
                flex: 0 0 auto;
                padding: .45rem .65rem;
                align-items: center;
                gap: .45rem;
                white-space: nowrap;
            }
            .settings-tab .tab-copy { flex-direction: row; align-items: center; }
            .settings-tab .tab-description { display: none; }
            .settings-tab .tab-icon { width: 28px; height: 28px; }
            #main-content .settings-content { height: auto; max-height: none; overflow: visible; padding: 0 0 4.5rem; }
        }

        @media (max-width: 768px) {
            .main-container { padding: .85rem !important; }
            .settings-header { padding: .9rem 1rem; gap: .7rem; }
            .settings-header .settings-header-icon { width: 40px; height: 40px; }
            .settings-sync-pill { display: none; }
            .settings-card, .settings-content :is(.card-info, .barangay-section, .ks-card, .template-card, .perm-card, .rl-card, .map-card, .stat-card, .ckw-stats, .qnt-stats, .settings-section) {
                padding: 1rem;
                border-radius: .9rem;
            }
            .settings-footer { position: sticky; bottom: .5rem; padding: .7rem .85rem; }
            .settings-footer-actions { width: 100%; margin-left: 0; }
            .settings-footer-actions .settings-btn-ghost,
            .settings-footer-actions .settings-btn-primary { flex: 1 1 auto; justify-content: center; }
            /* Let wide tables/sections scroll instead of overflowing the phone */
            .settings-content table { width: 100%; }
            .settings-content :is(.table-wrap, .table-responsive, .overflow-x-auto) { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .settings-content img { max-width: 100%; height: auto; }
            .settings-content input,
            .settings-content select,
            .settings-content textarea { max-width: 100%; }
            .settings-content :is(.grid, .flex) { min-width: 0; }
        }

        @media (max-width: 480px) {
            .main-container { padding: .6rem !important; }
            .settings-header { flex-wrap: wrap; }
            .settings-footer { flex-direction: column; align-items: stretch; }
            .settings-footer-msg { justify-content: center; text-align: center; }
            .settings-footer-actions { flex-direction: column; align-items: stretch; gap: .5rem; }
            .settings-footer-actions button { width: 100%; }
            .settings-content :is(.stat-cards, .ckw-stats, .qnt-stats) { grid-template-columns: 1fr; }
        }

        /* Shared partial sizing: use the space available beside both sidebars. */
        .settings-layout, .settings-sidebar, .settings-content { min-width: 0; max-width: 100%; }
        .settings-content :is(form, section, .grid, .flex, [class*="-row"]) { min-width: 0; }
        .settings-content :is(.grid, .flex, [class*="-row"]) > * { min-width: 0; }
        .settings-content :is(p, li, label, a, h1, h2, h3, h4, .file-label) { overflow-wrap: anywhere; }
        .settings-content :is(input, select, textarea, img, canvas) { max-width: 100%; }
        .settings-content :is(.form-input, .rl-input, .barangay-input) { min-width: 0; box-sizing: border-box; }
        .settings-content :is(.toggle-switch, .perm-switch, .ks-toggle-icon, .role-item-icon, .create-role-icon) { flex-shrink: 0; }
        .settings-content :is(.btn-primary, .btn-secondary, .settings-btn-primary, .settings-btn-ghost) { max-width: 100%; white-space: normal; }
        .settings-content :is(.create-role-fields, .perm-option-grid) {
            grid-template-columns: repeat(auto-fit, minmax(min(100%, max(220px, calc((100% - 1rem) / 2))), 1fr));
        }
        @media (min-width: 640px) {
            .settings-content .grid:is(.sm\:grid-cols-2, .sm\:grid-cols-3, .sm\:grid-cols-4, .md\:grid-cols-2) {
                grid-template-columns: repeat(auto-fit, minmax(min(100%, max(200px, calc((100% - (var(--settings-columns) - 1) * 1rem) / var(--settings-columns)))), 1fr));
            }
            .settings-content .grid.sm\:grid-cols-2 { --settings-columns: 2; }
            .settings-content .grid.sm\:grid-cols-3 { --settings-columns: 3; }
            .settings-content .grid.sm\:grid-cols-4 { --settings-columns: 4; }
            .settings-content .grid.md\:grid-cols-2 { --settings-columns: 2; }
        }
        .settings-content :is(
            .table-container, .barangay-table-wrap, .archive-table-wrap,
            [class*="table-wrap"], [class*="table-container"]
        ) { min-width: 0; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .settings-content :is(.ckw-toolbar, .qnt-toolbar, .create-role-actions, .boundary-actions) { flex-wrap: wrap; }
        .settings-content :is(.ckw-toolbar, .qnt-toolbar) .stat-cards { flex: 1 1 260px; }
        .settings-content :is(.ckw-toolbar, .qnt-toolbar) ~ .table-container table { min-width: 560px; }
        .settings-content .table-container:has(.qnt-chip) table { min-width: 680px; }
        .settings-content :is(.ckw-modal-header, .qnt-modal-header) h3 { min-width: 0; overflow-wrap: anywhere; }
        .settings-content :is(.ckw-modal-close, .qnt-modal-close, .modal-close) { flex-shrink: 0; }
        .settings-content :is(.modal-overlay, [class*="modal-overlay"]) { padding: 12px; }
        .settings-content :is(.modal-content, .ckw-modal, .qnt-modal) {
            box-sizing: border-box; width: min(100%, calc(100vw - 24px)); max-height: calc(100dvh - 24px); overflow-y: auto; overscroll-behavior: contain;
        }
        @media (min-width: 1024px) and (max-width: 1399px) {
            .settings-layout { grid-template-columns: 230px minmax(0, 1fr); gap: 1rem; }
            .settings-content .settings-sync-pill { display: none; }
            .settings-content .setting-row { flex-wrap: wrap; gap: .75rem; }
            .settings-content .setting-row > form { flex-wrap: wrap; max-width: 100%; }
            .settings-content .role-item-head { flex-wrap: wrap; }
            .settings-content .role-item-info { flex-basis: calc(100% - 60px); }
            .settings-content .role-item-actions { margin-left: auto; }
        }

        @media (max-width: 640px) {
            .settings-content :is(.grid, .create-role-fields, .perm-option-grid) { grid-template-columns: minmax(0, 1fr); }
            .settings-content :is(.ckw-toolbar, .qnt-toolbar, .create-role-actions, .boundary-actions, .setting-row) { gap: .75rem; }
            .settings-content :is(.ckw-toolbar, .qnt-toolbar) > .btn-primary { width: 100%; justify-content: center; }
            .settings-content :is(.setting-row > form, .boundary-actions) { flex-wrap: wrap; max-width: 100%; }
            .settings-content :is(.setting-row > form, .boundary-actions) > :is(button, label, select) { flex: 1 1 140px; }
            .settings-content :is(.ks-toggle-body, .perm-option-text) { overflow-wrap: anywhere; }
            .settings-content .ks-toggle-body h4 { flex-wrap: wrap; }
            .settings-content .sms-gateway-card .flex-1 { min-width: 0 !important; flex-basis: 100%; }
            .settings-content .pdf-preview-head { flex-wrap: wrap; gap: .75rem; }
            .settings-content :is(#mapSettingsPreview, #barangayBoundaryMap) { height: clamp(240px, 55dvh, 360px) !important; }
            .settings-content :is(.archive-table, .barangay-table) { white-space: normal; }
            .settings-content :is(.archive-table, .barangay-table) td::before { max-width: 40%; white-space: normal; overflow-wrap: normal; }
            .settings-content :is(.archive-table, .barangay-table) td { min-width: 0; overflow-wrap: anywhere; }
            .settings-content :is(.btn-primary, .btn-secondary, .barangay-btn, .barangay-btn-delete, .btn-add-barangay) { min-height: 44px; }
            .settings-content :is(.ckw-modal-close, .qnt-modal-close, .role-action-btn, .modal-close) { width: 44px; height: 44px; }
            .settings-content :is(.form-input, .rl-input, .barangay-input) { font-size: 16px; }
            .settings-content :is(.modal-overlay, [class*="modal-overlay"]) { align-items: center; }
            .settings-content :is(.modal-content, .ckw-modal, .qnt-modal) { border-radius: 16px; }
        }
        @media (max-width: 576px) {
            .settings-content .table-container:has(.cat-actions),
            .settings-content .table-container:has(.cat-actions) .overflow-x-auto { overflow: visible; }
            .settings-content .table-container:has(.cat-actions) table { min-width: 0; white-space: normal; }
            .settings-content .cat-actions { width: 100%; }
        }
    </style>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
</head>
<body>

<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<div id="main-content" tabindex="-1" class="lg:ml-72 min-h-screen" role="main">
    <div class="main-container max-w-7xl mx-auto">
        
        <!-- ===== PAGE HEADER ===== -->
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            
            <button id="navToggleBtn" onclick="toggleSettingsNav()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-emerald-200 bg-white text-sm font-semibold text-gray-600 hover:bg-emerald-50 hover:text-[#10A37F] hover:border-emerald-300 transition shadow-sm flex-shrink-0"
                    title="<?php echo t('Hide/Show the settings navigation menu to give the content more room'); ?>">
                <i id="navToggleIcon" class="fas fa-compress-arrows-alt"></i>
                <span id="navToggleLabel"><?php echo t('Hide Menu'); ?></span>
            </button>
        </div>

        <!-- ===== FLASH MESSAGES ===== -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 flash-success rounded-xl text-sm flex items-center gap-2">
                <i class="fas fa-check-circle text-[#10A37F]"></i>
                <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 flash-error rounded-xl text-sm flex items-center gap-2">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
        <?php endif; ?>

        <!-- ===== UNSAVED CHANGES MODAL (shown before leaving) ===== -->
        <div class="settings-unsaved-modal-overlay" id="unsavedModal" role="dialog" aria-modal="true" aria-labelledby="unsavedModalTitle" onclick="if(event.target===this) unsavedStay()">
            <div class="settings-unsaved-modal">
                <h3 id="unsavedModalTitle"><?php echo t('Unsaved changes'); ?></h3>
                <p><?php echo t("You edited values in this tab but haven't saved them yet. Save the changes before leaving, discard them, or stay here."); ?></p>
                <div class="sum-actions">
                    <button type="button" class="btn-primary" onclick="unsavedSaveNow()"><i class="fas fa-save mr-1" aria-hidden="true"></i><?php echo t('Save Changes'); ?></button>
                    <button type="button" class="btn-secondary" onclick="unsavedDiscard()"><i class="fas fa-undo mr-1" aria-hidden="true"></i><?php echo t('Discard Changes'); ?></button>
                    <button type="button" class="btn-secondary" onclick="unsavedStay()"><i class="fas fa-times mr-1" aria-hidden="true"></i><?php echo t('Stay Here'); ?></button>
                </div>
            </div>
        </div>

        <!-- ===== CONFIRM SAVE MODAL (shown after validation passes) ===== -->
        <div class="settings-unsaved-modal-overlay" id="confirmSaveModal" role="dialog" aria-modal="true" aria-labelledby="confirmSaveModalTitle" onclick="if(event.target===this) confirmSaveCancel()">
            <div class="settings-unsaved-modal">
                <h3 id="confirmSaveModalTitle"><?php echo t('Confirm save'); ?></h3>
                <p><?php echo t('Are you sure you want to save your changes? Saved settings apply immediately to the whole system.'); ?></p>
                <div class="sum-actions">
                    <button type="button" class="btn-primary" onclick="confirmSaveProceed()"><i class="fas fa-check mr-1" aria-hidden="true"></i><?php echo t('Yes, Save Changes'); ?></button>
                    <button type="button" class="btn-secondary" onclick="confirmSaveCancel()"><i class="fas fa-times mr-1" aria-hidden="true"></i><?php echo t('Cancel'); ?></button>
                </div>
            </div>
        </div>

        <!-- ===== SETTINGS LAYOUT ===== -->
        <div class="settings-layout">
            
            <!-- ===== SIDEBAR NAVIGATION ===== -->
            <nav class="settings-sidebar" aria-label="<?php echo t('Settings navigation'); ?>">
                <?php foreach ($navigation_groups as $category_name => $category_tabs): ?>
                    <!-- Category Header -->
                    <div class="category-header">
                        <?php echo htmlspecialchars(t($category_name)); ?>
                    </div>
                    
                    <!-- Category Links -->
                    <?php foreach ($category_tabs as $tab_key => $tab): ?>
                        <?php if (!empty($tab['hidden'])) continue; ?>
                        <a href="<?php echo BASE_URL; ?>index.php?page=settings&tab=<?php echo $tab_key; ?>" 
                           class="settings-tab <?php echo $active_tab === $tab_key ? 'active' : ''; ?>"
                           title="<?php echo htmlspecialchars($tab['description']); ?>">
                            <span class="tab-icon"><i class="fas <?php echo $tab['icon']; ?>"></i></span>
                            <span class="tab-copy">
                                <span class="tab-label"><?php echo htmlspecialchars($tab['label']); ?></span>
                                <span class="tab-description"><?php echo htmlspecialchars($tab['description']); ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                    
                    <!-- Spacing between categories -->
                    <div class="category-spacer"></div>
                <?php endforeach; ?>
            </nav>

            <!-- ===== CONTENT AREA ===== -->
            <div class="settings-content">
                <div class="settings-header">
                    <span class="settings-header-icon"><i class="fas <?php echo $tabs[$active_tab]['icon']; ?>"></i></span>
                    <div class="settings-header-copy">
                        <h2><?php echo $tabs[$active_tab]['label']; ?></h2>
                        <p><?php echo $tabs[$active_tab]['description']; ?></p>
                    </div>
                    <span class="settings-sync-pill"><span class="dot"></span> <?php echo t('Synced with database'); ?></span>
                </div>

                <?php 
                // Load the selected tab's content
                $tab_file = __DIR__ . '/partials/' . $tabs[$active_tab]['file'];
                if (file_exists($tab_file)) {
                    include $tab_file;
                } else {
                    // Fallback: show a "coming soon" message
                    echo '
                    <div class="text-center py-12">
                        <i class="fas ' . $tabs[$active_tab]['icon'] . ' text-6xl text-gray-300 mb-4 block"></i>
                        <h3 class="text-xl font-semibold text-gray-700">' . $tabs[$active_tab]['label'] . ' Settings</h3>
                        <p class="text-gray-400 mt-2">Coming soon. This settings panel is under development.</p>
                    </div>
                    ';
                }
                ?>

                <!-- ===== STICKY SAVE BAR ===== -->
                <div class="settings-footer" id="settingsFooter" hidden>
                    <span class="settings-footer-msg"><i class="fas fa-circle-exclamation"></i> <span id="settingsFooterText"><?php echo t('You have unsaved changes.'); ?></span></span>
                    <div class="settings-footer-actions">
                        <button type="button" class="settings-btn-ghost" onclick="settingsDiscard()"><i class="fas fa-rotate-left"></i> <?php echo t('Discard Changes'); ?></button>
                        <button type="button" class="settings-btn-primary" onclick="settingsSave()"><i class="fas fa-check"></i> <?php echo t('Save Configuration'); ?></button>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- ===== STICKY SAVE BAR LOGIC ===== -->
<script>
(function () {
    'use strict';
    var footer = document.getElementById('settingsFooter');
    var content = document.querySelector('.settings-content');
    if (!footer || !content) return;
    window.settingsSave = function () {
        window.unsavedSaveNow();
    };
    window.settingsDiscard = function () {
        if (window.GB && window.GB.confirm) {
            window.GB.confirm({ message: 'Discard unsaved changes?', onConfirm: function () { window.unsavedDiscard(); } });
        } else if (confirm('Discard unsaved changes?')) {
            window.unsavedDiscard();
        }
    };
})();
</script>

<!-- ===== SCRIPTS ===== -->
<script>
// ===== HIDE SETTINGS NAV TOGGLE =====
function toggleSettingsNav() {
    const hidden = document.body.classList.toggle('settings-nav-hidden');
    try { localStorage.setItem('settingsNavHidden', hidden ? '1' : '0'); } catch (e) {}
    updateNavToggleBtn();
}

function updateNavToggleBtn() {
    const icon = document.getElementById('navToggleIcon');
    const label = document.getElementById('navToggleLabel');
    if (!icon || !label) return;
    const hidden = document.body.classList.contains('settings-nav-hidden');
    if (hidden) {
        icon.className = 'fas fa-expand-arrows-alt';
        label.textContent = 'Show Menu';
    } else {
        icon.className = 'fas fa-compress-arrows-alt';
        label.textContent = 'Hide Menu';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        if (localStorage.getItem('settingsNavHidden') === '1') {
            document.body.classList.add('settings-nav-hidden');
        }
    } catch (e) {}
    updateNavToggleBtn();
});

// ===== TOAST NOTIFICATION HELPER =====
// Used by partials (e.g. map.php) that call showNotification(msg, type)
function showNotification(message, type) {
    type = type || 'info';
    const toast = document.createElement('div');
    toast.className = 'settings-toast ' + type;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.style.transition = 'opacity 0.3s';
        toast.style.opacity = '0';
        setTimeout(function() { toast.remove(); }, 320);
    }, 3500);
}

// Upload area drag & drop
document.querySelectorAll('.upload-area').forEach(area => {
    const input = area.querySelector('input[type="file"]');
    if (!input) return;
    
    area.addEventListener('click', () => input.click());
    
    area.addEventListener('dragover', (e) => {
        e.preventDefault();
        area.classList.add('dragover');
    });
    
    area.addEventListener('dragleave', () => {
        area.classList.remove('dragover');
    });
    
    area.addEventListener('drop', (e) => {
        e.preventDefault();
        area.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            const label = area.querySelector('.file-label');
            if (label) label.textContent = e.dataTransfer.files[0].name;
        }
    });
    
    input.addEventListener('change', function() {
        const label = area.querySelector('.file-label');
        if (label && this.files.length) {
            label.textContent = this.files[0].name;
        }
    });
});

// ===== PRESERVE SCROLL POSITION ACROSS TAB SWITCHES =====
// Desktop uses a vertically scrolling menu; smaller screens use a
// horizontal menu and page scroll. Keep both when opening another tab.
(function() {
    var key = 'settingsNavigationScroll';
    var destination = null;
    var saved = null;
    function pageKey(url) { return url.pathname + url.search; }
    try {
        saved = JSON.parse(sessionStorage.getItem(key) || 'null');
        sessionStorage.removeItem(key);
        sessionStorage.removeItem('settingsScrollPos');
        sessionStorage.removeItem('settingsSidebarScroll');
    } catch (e) {}

    function restore() {
        if (!saved || saved.destination !== pageKey(new URL(window.location.href)) ||
            Date.now() - saved.time > 60000) return;
        var bar = document.querySelector('.settings-sidebar');
        if (bar) {
            bar.scrollTo({ top: saved.menuTop || 0, left: saved.menuLeft || 0, behavior: 'instant' });
        }
        window.scrollTo({ top: saved.pageTop || 0, left: saved.pageLeft || 0, behavior: 'instant' });
    }
    document.addEventListener('DOMContentLoaded', function() {
        restore();
        requestAnimationFrame(restore);
    });
    window.addEventListener('load', restore, { once: true });

    // Capture the intended tab even when the unsaved-changes prompt
    // defers navigation. Write only when the user actually leaves.
    document.addEventListener('click', function(event) {
        var link = event.target.closest('a[href]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey ||
            event.shiftKey || event.altKey || link.target === '_blank') return;
        destination = link.classList.contains('settings-tab') ? pageKey(new URL(link.href)) : null;
    }, true);
    document.addEventListener('submit', function() { destination = null; }, true);
    window.addEventListener('pagehide', function() {
        if (!destination) return;
        var bar = document.querySelector('.settings-sidebar');
        try {
            sessionStorage.setItem(key, JSON.stringify({
                destination: destination, time: Date.now(),
                pageTop: window.scrollY, pageLeft: window.scrollX,
                menuTop: bar ? bar.scrollTop : 0, menuLeft: bar ? bar.scrollLeft : 0
            }));
        } catch (e) {}
    });
})();

// ===== UNSAVED CHANGES PROTECTION =====
// 1. Tracks edits silently until the user tries to leave.
// 2. Intercepts clicks on navigation links while edits are pending and opens
//    a modal offering: Save Changes / Discard Changes / Stay Here.
// 3. Blocks the browser's hidden implicit submit (pressing Enter inside a
//    field) so values are only ever saved by clicking an explicit Save button.
(function() {
    'use strict';

    var dirty = false;
    var pendingHref = null;
    var dirtyForms = new Set();
    var initialValues = new Map();
    var promptFocus = null;
    function formValue(form) {
        return JSON.stringify(Array.from(new FormData(form).entries()).map(function(entry) {
            var value = entry[1];
            return [entry[0], typeof value === 'string' ? value : (value.name ? [value.name, value.size, value.lastModified] : null)];
        }));
    }

    function setDirty(v) {
        dirty = v;
        window.settingsHasUnsavedChanges = v;
        var footer = document.getElementById('settingsFooter');
        if (footer) footer.hidden = !v;
    }

    // Pick the "main" save form of the active tab: prefer a form whose submit
    // button text includes "Save", then fall back to the first form with one.
    function findSavableForm() {
        if (dirtyForms.size) return dirtyForms.values().next().value;
        var forms = document.querySelectorAll('.settings-content form');
        var fallback = null;
        for (var i = 0; i < forms.length; i++) {
            var btn = forms[i].querySelector('button[type="submit"], input[type="submit"]');
            if (!btn) continue;
            if (!fallback) fallback = forms[i];
            if ((btn.textContent || '').indexOf('Save') !== -1) return forms[i];
        }
        return fallback;
    }

    window.addEventListener('beforeunload', function(event) {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
    document.addEventListener('keydown', function(event) {
        var modal = document.getElementById('unsavedModal');
        if (!modal || !modal.classList.contains('show')) return;
        if (event.key === 'Escape') window.unsavedStay();
        if (event.key === 'Tab') {
            var buttons = modal.querySelectorAll('button');
            var first = buttons[0], last = buttons[buttons.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });

    // ---- Track edits ----
    document.querySelectorAll('.settings-content form').forEach(function(form) {
        initialValues.set(form, formValue(form));
        var mark = function(e) {
            if (formValue(form) !== initialValues.get(form)) dirtyForms.add(form);
            else dirtyForms.delete(form);
            setDirty(dirtyForms.size > 0);
        };
        form.addEventListener('input', mark, true);
        form.addEventListener('change', mark, true);
        form.addEventListener('reset', function() { setTimeout(mark, 0); });
        form.addEventListener('submit', function(event) {
            queueMicrotask(function() { if (!event.defaultPrevented) setDirty(false); });
        });

        // Explicit-save only: pressing Enter inside a text field must not
        // silently submit the form (this was auto-saving edits in the past).
        form.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            var t = e.target;
            if (!t || t.tagName !== 'INPUT') return;
            var type = (t.type || '').toLowerCase();
            if (['text', 'number', 'email', 'tel', 'url', 'search', 'password'].indexOf(type) === -1) return;
            e.preventDefault();
            if (typeof showNotification === 'function') {
                showNotification('Changes are not saved until you press the Save button.', 'info');
            }
        });
    });

    // ---- Intercept navigation clicks while edits are pending ----
    document.addEventListener('click', function(e) {
        if (!dirty) return;
        var target = e.target;
        var a = target && target.closest ? target.closest('a[href]') : null;
        if (!a) return;

        var href = a.getAttribute('href') || '';
        if (href.indexOf('#') === 0) return;                        // in-page anchors
        if (!/^https?:/i.test(a.href) || e.ctrlKey || e.metaKey || e.shiftKey) return;
        if (a.getAttribute('target') === '_blank') return;          // open-new-tab links

        e.preventDefault();
        promptFocus = document.activeElement;
        pendingHref = a.href;
        var modal = document.getElementById('unsavedModal');
        if (modal) { modal.classList.add('show'); modal.querySelector('button').focus(); }
    });

    // ---- Modal actions (exposed globally for the inline onclick handlers) ----
    window.unsavedStay = function() {
        var modal = document.getElementById('unsavedModal');
        if (modal) modal.classList.remove('show');
        if (promptFocus && promptFocus.isConnected) promptFocus.focus();
        pendingHref = null;
    };

    window.unsavedSaveNow = function() {
        var form = findSavableForm();
        window.unsavedStay();
        if (!form) return;
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    };

    window.unsavedDiscard = function() {
        var goto = pendingHref || window.location.href.split('#')[0];
        window.unsavedStay();
        setDirty(false);
        window.location.href = goto;
    };
})();

// ===== SAVE VALIDATION =====
// Runs in the capture phase so it checks every settings form before the
// browser submits. Invalid fields get a red ring + an inline message, the
// page scrolls to the first problem field, and the submit is cancelled with
// a toast explaining what to fix. Fields are validated when the tab's
// "Save Changes" button is clicked (which also covers the unsaved-changes
// bar/modal Save buttons, since they submit the same form).
(function() {
    'use strict';

    var FIELD_TAGS = ['INPUT', 'SELECT', 'TEXTAREA'];

    function clearValidation(form) {
        form.querySelectorAll('.is-invalid').forEach(function(el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('.field-error').forEach(function(el) { el.remove(); });
    }

    function findLabel(el) {
        var id = el.id;
        if (id) {
            var lbl = document.querySelector('label[for="' + id + '"]');
            if (lbl) return lbl.textContent.trim();
        }
        var wrap = el.closest('.form-group, .form-label, .form-field, .field');
        if (wrap) {
            var firstLbl = wrap.querySelector('label');
            if (firstLbl) return firstLbl.textContent.trim();
        }
        return '';
    }

    function validationMessage(el) {
        var rawLabel = findLabel(el).replace(/\*.*$/g, '').trim();
        var label = rawLabel || el.name || el.id || 'this field';

        if (el.validity.valueMissing) {
            return label + ' is required.';
        }
        if (el.validity.typeMismatch) {
            return el.type === 'email'
                ? 'Please enter a valid email address for "' + label + '".'
                : 'Please enter a valid value for "' + label + '".';
        }
        if (el.validity.rangeUnderflow) {
            return '"' + label + '" is too small (minimum ' + (el.min || '') + ').';
        }
        if (el.validity.rangeOverflow) {
            return '"' + label + '" is too large (maximum ' + (el.max || '') + ').';
        }
        if (el.validity.tooShort) {
            return '"' + label + '" is too short (minimum ' + (el.minLength || '') + ' characters).';
        }
        if (el.validity.tooLong) {
            return '"' + label + '" is too long (maximum ' + (el.maxLength || '') + ' characters).';
        }
        if (el.tagName === 'SELECT') {
            return 'Please choose an option for "' + label + '".';
        }
        return 'Please fix "' + label + '" before saving.';
    }

    function showFieldError(el, msg) {
        el.classList.add('is-invalid');
        var err = document.createElement('p');
        err.className = 'field-error';
        err.innerHTML = '<i class="fas fa-exclamation-circle" aria-hidden="true"></i>' + (msg || '');
        var anchor = el.closest('.form-group, .form-label, .form-field, .field') || el.parentNode;
        if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(err, anchor.nextSibling);
        } else {
            el.parentNode.insertBefore(err, el.nextSibling);
        }
        el.setAttribute('aria-invalid', 'true');
    }

    // Intercept submits of settings forms that carry a Save button.
        document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        var saveBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (!saveBtn) return; // delete/activate sub-forms keep their own confirm()

        clearValidation(form);

        // Forced submit after the user confirmed the "Are you sure?" dialog.
        if (forcedSubmitForm === form) {
            forcedSubmitForm = null;
            return; // valid -> let the form submit normally
        }

        // Force validation: a hidden/noValidate form can't clear "willValidate"
        // states from a previous run, so let native validity decide.
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation(); // keep per-tab custom validators from double-firing
            showFieldErrors(form);
            return;
        }

        // Validation passed -> ask for confirmation before saving.
        e.preventDefault();
        e.stopPropagation();
        pendingSaveForm = form;
        var modal = document.getElementById('confirmSaveModal');
        if (modal) modal.classList.add('show');
    }, true);

    var forcedSubmitForm = null;
    var pendingSaveForm = null;

    window.confirmSaveProceed = function() {
        var form = pendingSaveForm;
        pendingSaveForm = null;
        var modal = document.getElementById('confirmSaveModal');
        if (modal) modal.classList.remove('show');
        if (!form || form.tagName !== 'FORM') return;
        forcedSubmitForm = form;
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    };

    window.confirmSaveCancel = function() {
        pendingSaveForm = null;
        var modal = document.getElementById('confirmSaveModal');
        if (modal) modal.classList.remove('show');
    };

    function showFieldErrors(form) {
        var firstBad = null;
        FIELD_TAGS.forEach(function(tag) {
            Array.prototype.forEach.call(form.querySelectorAll(tag + ':invalid'), function(el) {
                showFieldError(el, validationMessage(el));
                if (!firstBad) firstBad = el;
            });
        });

        if (firstBad) {
            try { firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (err) {}
            try { firstBad.focus({ preventScroll: true }); } catch (err) {}
        }

        if (typeof showNotification === 'function') {
            showNotification('Please fix the highlighted fields before saving.', 'error');
        }
    }

    // Clear a field's error as soon as the admin types/selects into it again.
    document.addEventListener('input', function(e) {
        var el = e.target;
        if (!el || !el.classList || !el.classList.contains('is-invalid')) return;
        el.classList.remove('is-invalid');
        el.removeAttribute('aria-invalid');
        var anchor = el.closest('.form-group, .form-label, .form-field, .field') || el.parentNode;
        if (anchor && anchor.nextSibling && anchor.nextSibling.classList && anchor.nextSibling.classList.contains('field-error')) {
            anchor.nextSibling.remove();
        }
    }, true);
})();
</script>

<script src="<?php echo BASE_URL; ?>assets/js/fetch-timeout.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/modal-a11y.js"></script>
</body>
</html>
