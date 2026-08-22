<?php
// views/admin/settings/index.php - Unified Settings Dashboard
// Complete with tab navigation, responsive design, and all setting types

require_once dirname(__DIR__, 3) . '/config/config.php';
require_once BASE_PATH . 'helpers/SecurityHelper.php';
require_once BASE_PATH . 'helpers/SettingsHelper.php';
requireRole('admin');

// Get active tab from URL
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
$system_name = SettingsHelper::get('system_name', 'Sierra');

// ============================================================
// USERS CSV EXPORT HANDLER
// Runs BEFORE any HTML output so the CSV headers/download work
// correctly (same pattern as views/admin/all_reports.php).
// ============================================================
if (isset($_GET['export_users']) && $_GET['export_users'] !== '') {
    if (!PermissionHelper::userHasPermission('can_export_reports')) {
        $_SESSION['error'] = "You do not have permission to export users.";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=users");
        exit();
    }

    $export_type = $_GET['export_users'];
    $export_from = $_GET['export_from'] ?? '';
    $export_to   = $_GET['export_to'] ?? '';

    $db2 = (new Database())->getConnection();
    $q = "SELECT u.id, u.first_name, u.last_name, u.email, u.contact_number,
                 u.user_type, u.is_active, u.created_at, u.job_title,
                 u.is_resident, u.province, u.municipality, u.non_resident_address,
                 b.name AS barangay_name
          FROM users u
          LEFT JOIN barangays b ON u.barangay_id = b.id";

    $where = [];
    $params = [];

    switch ($export_type) {
        case 'menro':
            $where[] = "u.user_type IN ('admin','menro_staff')";
            break;
        case 'barangay':
            $where[] = "u.user_type = 'barangay_personnel'";
            break;
        case 'reporters':
            // Reporters = citizen accounts (both resident and non-resident).
            $where[] = "u.user_type IS NULL";
            break;
        case 'residents':
            $where[] = "u.user_type IS NULL AND u.is_resident = 1";
            break;
        case 'non_residents':
            $where[] = "u.user_type IS NULL AND u.is_resident = 0";
            break;
        case 'new_accounts':
            if ($export_from !== '') { $where[] = "DATE(u.created_at) >= :efrom"; $params[':efrom'] = $export_from; }
            if ($export_to !== '')   { $where[] = "DATE(u.created_at) <= :eto";   $params[':eto']   = $export_to; }
            break;
        case 'status':
            // Exports all users with their account status column.
            break;
        case 'all':
        default:
            break;
    }

    $sql = $q . (count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY u.created_at DESC';
    $stmt = $db2->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [
        'ID', 'First Name', 'Last Name', 'Email', 'Contact Number', 'Role', 'Status',
        'Barangay', 'Residency', 'Province/Municipality', 'Registered Date'
    ];

    $roleMap = [
        'admin'              => 'Admin',
        'menro_staff'        => 'MENRO Staff',
        'barangay_personnel' => 'Barangay Personnel',
    ];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users_' . $export_type . '_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($out, $labels);

    foreach ($rows as $r) {
        $is_citizen  = empty($r['user_type']);
        $role        = $is_citizen ? 'Citizen' : ($roleMap[$r['user_type']] ?? 'Citizen');
        $status      = !empty($r['is_active']) ? 'Active' : 'Suspended';
        $is_resident = (int)($r['is_resident'] ?? 1);
        $residency   = $is_citizen ? ($is_resident ? 'Resident' : 'Non-Resident') : '—';

        $loc = '';
        if ($is_citizen && !$is_resident) {
            $loc = trim(implode(', ', array_filter([$r['province'] ?? '', $r['municipality'] ?? ''])));
        } else {
            $loc = $r['barangay_name'] ?? '';
        }

        fputcsv($out, [
            str_pad($r['id'], 5, '0', STR_PAD_LEFT),
            $r['first_name'], $r['last_name'], $r['email'],
            $r['contact_number'], $role, $status,
            $r['barangay_name'] ?? '', $residency, $loc,
            date('M d, Y', strtotime($r['created_at']))
        ]);
    }
    fclose($out);

    $actLog = new ActivityLog($db2);
    $actLog->log($_SESSION['user_id'], 'Export Users', "Exported $export_type users list from Manage Users", $_SERVER['REMOTE_ADDR'] ?? 'unknown', null, 'SUCCESS');
    exit();
}

// Define all setting tabs organized into categories
$navigation_groups = [
    'General & Branding' => [
        'general' => [
            'label' => 'General',
            'icon' => 'fa-cog',
            'description' => 'System name, logo, and contact information',
            'file' => 'general.php'
        ],
        'landing' => [
            'label' => 'Landing Page',
            'icon' => 'fa-home',
            'description' => 'Edit all content shown on the public homepage',
            'file' => 'landing.php'
        ],
        'barangays' => [
            'label' => 'Barangays',
            'icon' => 'fa-building',
            'description' => 'Manage barangay information',
            'file' => 'barangays.php'
        ]
    ],
    'Administration & Access Control' => [
        'users' => [
            'label' => 'Users',
            'icon' => 'fa-users',
            'description' => 'Manage citizens, barangay personnel, and MENRO staff accounts',
            'file' => 'users.php'
        ],
        'permissions' => [
            'label' => 'Permissions',
            'icon' => 'fa-user-lock',
            'description' => 'Role-based access control',
            'file' => 'permissions.php'
        ],
        'security' => [
            'label' => 'Security',
            'icon' => 'fa-shield-alt',
            'description' => 'Password policies and login security',
            'file' => 'security.php'
        ]
    ],
    'Application & Workflow Management' => [
        'categories' => [
            'label' => 'Categories',
            'icon' => 'fa-tags',
            'description' => 'Manage report categories and severity weights',
            'file' => 'categories.php'
        ],
        'reporting' => [
            'label' => 'Reporting Limits',
            'icon' => 'fa-gauge-high',
            'description' => 'Per-citizen report rate limits to prevent spam',
            'file' => 'reporting.php'
        ],
        'algorithm' => [
            'label' => 'Algorithm',
            'icon' => 'fa-calculator',
            'description' => 'Severity scoring configuration',
            'file' => 'algorithm.php'
        ],
        'features' => [
            'label' => 'Features & Kill Switches',
            'icon' => 'fa-exclamation-triangle',
            'description' => 'Master kill switches — turn features on/off instantly without touching code',
            'file' => 'features.php'
        ]
    ],
    'Data & Operations' => [
        'map' => [
            'label' => 'Map',
            'icon' => 'fa-map',
            'description' => 'Clustering radius and map settings',
            'file' => 'map.php'
        ],
        'kpi' => [
            'label' => 'KPI & Insights',
            'icon' => 'fa-chart-pie',
            'description' => 'Key performance indicator targets for the Insight Engine',
            'file' => 'kpi.php'
        ],
        'notifications' => [
            'label' => 'Notifications',
            'icon' => 'fa-envelope',
            'description' => 'Email and SMS templates',
            'file' => 'notifications.php'
        ],
        'archiving' => [
            'label' => 'Data Archiving & Retention',
            'icon' => 'fa-archive',
            'description' => 'Manually archive old reports, retain rejected/spam, and manage the archive',
            'file' => 'archiving.php'
        ],
        'pdf_export' => [
            'label' => 'PDF Export',
            'icon' => 'fa-file-pdf',
            'description' => 'MENRO PDF Analytics Export — official LGU header, logos, and signatory block',
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
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Settings - <?php echo htmlspecialchars($system_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css">
    <!-- Leaflet Map (required by the Map settings tab preview) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
            grid-template-columns: 280px 1fr;
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
            position: sticky;
            top: 1.5rem;
            max-height: calc(100vh - 3rem);
            overflow-y: auto;
            scrollbar-width: none;
        }
        .settings-sidebar::-webkit-scrollbar { display: none; }
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
            color: #9ca3af;
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
            color: #6b7280;
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
                font-size: 0.75rem;
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
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .settings-header p {
            color: #6b7280;
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
            color: #1a2e1a;
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
            color: #4b5563;
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
            color: #6b7280;
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
            color: #9ca3af;
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
    </style>
</head>
<body>

<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<div class="lg:ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">
        
        <!-- ===== PAGE HEADER ===== -->
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sliders-h text-[#10A37F] text-sm"></i>
                    </div>
                    <span class="text-xs uppercase tracking-wider text-[#10A37F] font-semibold">Administration</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">System Settings</h1>
                <p class="text-gray-500 text-sm mt-1">Configure and manage all system settings</p>
            </div>
            <button id="navToggleBtn" onclick="toggleSettingsNav()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-emerald-200 bg-white text-sm font-semibold text-gray-600 hover:bg-emerald-50 hover:text-[#10A37F] hover:border-emerald-300 transition shadow-sm flex-shrink-0"
                    title="Hide/Show the settings navigation menu to give the content more room">
                <i id="navToggleIcon" class="fas fa-compress-arrows-alt"></i>
                <span id="navToggleLabel">Hide Menu</span>
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

        <!-- ===== SETTINGS LAYOUT ===== -->
        <div class="settings-layout">
            
            <!-- ===== SIDEBAR NAVIGATION ===== -->
            <nav class="settings-sidebar" aria-label="Settings navigation">
                <?php foreach ($navigation_groups as $category_name => $category_tabs): ?>
                    <!-- Category Header -->
                    <div class="category-header">
                        <?php echo htmlspecialchars($category_name); ?>
                    </div>
                    
                    <!-- Category Links -->
                    <?php foreach ($category_tabs as $tab_key => $tab): ?>
                        <a href="<?php echo BASE_URL; ?>index.php?page=settings&tab=<?php echo $tab_key; ?>" 
                           class="settings-tab <?php echo $active_tab === $tab_key ? 'active' : ''; ?>"
                           title="<?php echo htmlspecialchars($tab['description']); ?>">
                            <span class="tab-icon"><i class="fas <?php echo $tab['icon']; ?>"></i></span>
                            <span class="tab-label"><?php echo htmlspecialchars($tab['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                    
                    <!-- Spacing between categories -->
                    <div class="category-spacer"></div>
                <?php endforeach; ?>
            </nav>

            <!-- ===== CONTENT AREA ===== -->
            <div class="settings-content">
                <div class="settings-header">
                    <h2>
                        <i class="fas <?php echo $tabs[$active_tab]['icon']; ?> text-[#10A37F]"></i>
                        <?php echo $tabs[$active_tab]['label']; ?>
                    </h2>
                    <p><?php echo $tabs[$active_tab]['description']; ?></p>
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
            </div>
            
        </div>
    </div>
</div>

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

// Prevent accidental navigation with unsaved changes
document.addEventListener('DOMContentLoaded', function() {
    let formChanged = false;
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('input', () => { formChanged = true; });
        form.addEventListener('submit', () => { formChanged = false; });
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
});
</script>

</body>
</html>