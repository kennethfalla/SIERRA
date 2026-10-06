<?php
// views/admin/reports/users_report.php - PRINTABLE USER / ACCOUNT REPORT
// Paper-agnostic printable report of all registered users with filters
// for Status (Active/Inactive) and Barangay. Role filter included so the
// report can focus on Reporters/Residents, Barangay Officials, or MENRO staff.
// Opened via:
//   index.php?page=users-report&status=all|active|inactive&barangay=ID&role=all|citizen|barangay|menro[&autoprint=1]
require_once dirname(__DIR__, 3) . '/config/config.php';
requireLogin();

// Users reports are reserved for the System Administrator.
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "You are not permitted to view user reports.";
    header("Location: " . BASE_URL . "index.php?page=dashboard");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// ------------------------------------------------------------
// INPUTS
// ------------------------------------------------------------
$status   = in_array($_GET['status'] ?? 'all', ['all', 'active', 'inactive'], true) ? $_GET['status'] : 'all';
$barangay = isset($_GET['barangay']) ? (int)$_GET['barangay'] : 0;
$role     = in_array($_GET['role'] ?? 'all', ['all', 'citizen', 'barangay', 'menro'], true) ? $_GET['role'] : 'all';
$residency = in_array($_GET['residency'] ?? 'all', ['all', 'resident', 'non_resident'], true) ? $_GET['residency'] : 'all';
$created_from = isset($_GET['created_from']) ? preg_replace('/[^0-9-]/', '', $_GET['created_from']) : '';
$created_to   = isset($_GET['created_to'])   ? preg_replace('/[^0-9-]/', '', $_GET['created_to'])   : '';
$autoprint = !empty($_GET['autoprint']);

// Non-residents do not belong to any barangay, so a barangay filter must not
// be applied to them.
if ($residency === 'non_resident') $barangay = 0;

// Residency only applies to Reporters, so it is only effective (and shown)
// when the User Group filter is set to "Reporters".
if ($role !== 'citizen') $residency = 'all';

function isValidUserDate($s) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
    [$y, $m, $d] = array_map('intval', explode('-', $s));
    return checkdate($m, $d, $y);
}
if ($created_from && !isValidUserDate($created_from)) $created_from = '';
if ($created_to   && !isValidUserDate($created_to))   $created_to   = '';

// ------------------------------------------------------------
// QUERY USERS
// ------------------------------------------------------------
$where = ["1=1"];
$params = [];
if ($status === 'active')   { $where[] = "u.is_active = 1"; }
elseif ($status === 'inactive') { $where[] = "u.is_active = 0"; }
if ($barangay > 0) {
    $where[] = "u.barangay_id = :barangay";
    $params[':barangay'] = $barangay;
}
if ($role === 'citizen')  { $where[] = "(u.user_type IS NULL OR u.user_type = '')"; }
elseif ($role === 'barangay') { $where[] = "u.user_type = 'barangay_personnel'"; }
elseif ($role === 'menro')    { $where[] = "u.user_type IN ('menro_staff', 'admin')"; }
if ($residency === 'resident') {
    $where[] = "u.is_resident = 1";
} elseif ($residency === 'non_resident') {
    $where[] = "u.is_resident = 0";
}
if ($created_from !== '') { $where[] = "DATE(u.created_at) >= :created_from"; $params[':created_from'] = $created_from; }
if ($created_to !== '')   { $where[] = "DATE(u.created_at) <= :created_to";   $params[':created_to']   = $created_to;   }

$sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.contact_number,
               u.user_type, u.is_active, u.is_resident, u.non_resident_address,
               u.job_title, u.created_at,
               b.name AS barangay_name
        FROM users u
        LEFT JOIN barangays b ON u.barangay_id = b.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY u.is_active DESC, u.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Barangay list for the filter dropdown.
$barangays = $db->query("SELECT id, name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$barangayName = '';
foreach ($barangays as $b) {
    if ((int)$b['id'] === $barangay) { $barangayName = $b['name']; break; }
}

// ------------------------------------------------------------
// DERIVED
// ------------------------------------------------------------
function roleLabelOf($user_type) {
    if ($user_type === 'admin') return 'System Admin';
    if ($user_type === 'menro_staff') return 'MENRO Staff';
    if ($user_type === 'barangay_personnel') return 'Barangay Official';
    return 'Reporter / Resident';
}

$totalUsers  = count($users);
$activeUsers = count(array_filter($users, fn($u) => (int)$u['is_active'] === 1));
$inactiveUsers = $totalUsers - $activeUsers;
$residents = count(array_filter($users, fn($u) => isset($u['is_resident']) && (int)$u['is_resident'] === 1));
$nonResidents = count(array_filter($users, fn($u) => isset($u['is_resident']) && (int)$u['is_resident'] === 0));
$activePct = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0;

$statusText = $status === 'active' ? 'Active Only' : ($status === 'inactive' ? 'Inactive Only' : 'All Statuses');
$roleText = $role === 'citizen' ? 'Reporters (Resident & Non-Resident)' : ($role === 'barangay' ? 'Barangay Users' : ($role === 'menro' ? 'MENRO Users' : 'All Users'));
$barangayText = $barangay > 0 ? $barangayName : 'All Barangays';
$residencyText = $residency === 'resident' ? 'Residents Only' : ($residency === 'non_resident' ? 'Non-Residents Only' : 'All');
$createdText = 'All Time';
if ($created_from && $created_to && $created_from === $created_to) $createdText = date('M j, Y', strtotime($created_from));
elseif ($created_from && $created_to) $createdText = date('M j, Y', strtotime($created_from)) . ' to ' . date('M j, Y', strtotime($created_to));
elseif ($created_from) $createdText = 'From ' . date('M j, Y', strtotime($created_from));
elseif ($created_to)   $createdText = 'Up to ' . date('M j, Y', strtotime($created_to));

// Quick preset ranges ("All date filters apply Today / This Week / This Month / This Year").
$quickRanges = [
    'today' => ['name' => 'Today',      'from' => date('Y-m-d'),                          'to' => date('Y-m-d')],
    'week'  => ['name' => 'This Week',  'from' => date('Y-m-d', strtotime('monday this week')), 'to' => date('Y-m-d')],
    'month' => ['name' => 'This Month', 'from' => date('Y-m-01'),                         'to' => date('Y-m-d')],
    'year'  => ['name' => 'This Year',  'from' => date('Y-01-01'),                        'to' => date('Y-m-d')],
];

// Report title reflects the applied filters — user group + residency + barangay.
// When a date filter is set the title becomes "NEWLY REGISTERED <PERIOD> [- <SCOPE>]".
$dateActive = ($created_from !== '' || $created_to !== '');

$periodDesc = '';
if ($dateActive) {
    foreach ($quickRanges as $k => $qr) {
        if ($created_from === $qr['from'] && $created_to === $qr['to']) { $periodDesc = $qr['name']; break; }
    }
    if ($periodDesc === '') $periodDesc = $createdText; // custom range
}

$roleSet   = $role !== 'all';
$resSet    = $residency !== 'all';
$brgySet   = ($barangay > 0 && $barangayName !== '');
$scopeCount = (int)$roleSet + (int)$resSet + (int)$brgySet;

if ($scopeCount === 0) {
    $scopeLabel = 'ALL ACCOUNTS';
} elseif ($scopeCount === 1) {
    if ($resSet && $residency === 'non_resident') $scopeLabel = 'NON-RESIDENT ACCOUNTS';
    elseif ($resSet && $residency === 'resident') $scopeLabel = 'RESIDENT ACCOUNTS';
    elseif ($roleSet && $role === 'menro')        $scopeLabel = 'MENRO ACCOUNTS';
    elseif ($roleSet && $role === 'barangay')     $scopeLabel = 'BARANGAY ACCOUNTS';
    elseif ($roleSet && $role === 'citizen')      $scopeLabel = 'REPORTERS ACCOUNTS';
    else                                          $scopeLabel = 'ACCOUNTS IN ' . strtoupper($barangayName);
} else {
    $parts = [];
    if ($resSet) $parts[] = $residency === 'resident' ? 'RESIDENT' : 'NON-RESIDENT';
    if ($roleSet) $parts[] = $role === 'menro' ? 'MENRO USERS' : ($role === 'barangay' ? 'BARANGAY USERS' : 'REPORTERS');
    else          $parts[] = 'ACCOUNTS';
    if ($brgySet) $parts[] = 'IN ' . strtoupper($barangayName);
    $scopeLabel = implode(' ', $parts);
}

if ($dateActive) {
    $reportTitle = 'NEWLY REGISTERED ' . $periodDesc . ($scopeCount > 0 ? ' - ' . $scopeLabel : '');
} else {
    $reportTitle = $scopeLabel;
    if ($scopeCount === 0 || ($scopeCount === 1 && !$brgySet)) $reportTitle .= ' REPORT';
}
$reportTitle = strtoupper($reportTitle);
$baseQuery = '?page=users-report&role=' . urlencode($role)
             . ($status !== 'all' ? '&status=' . urlencode($status) : '')
             . ($barangay > 0 ? '&barangay=' . (int)$barangay : '')
             . ($residency !== 'all' ? '&residency=' . urlencode($residency) : '');

// ------------------------------------------------------------
// ORGANIZATION / REPORT SETTINGS
// ------------------------------------------------------------
$lguLogo       = SettingsHelper::getLogoUrl();
$menroLogoPath = SettingsHelper::get('menro_logo', '');
$menroLogo     = $menroLogoPath ? BASE_URL . $menroLogoPath : '';
$officeName    = SettingsHelper::get('pdf_office_name', 'Municipal Environment and Natural Resources Office');
$municipality  = SettingsHelper::get('pdf_municipality_name', 'Municipality of San Isidro');
$systemName    = SettingsHelper::get('system_name', 'SIERRA');
$generatedBy   = $_SESSION['user_name'] ?? 'System Admin';
$generatedOn   = date('F j, Y \a\t h:i A');

// PDF Export signatory block + footer (Settings > PDF Export)
$_pdfCfg       = getPdfExportConfig();
$preparedBy    = $_pdfCfg['prepared_by'];
$preparedTitle = $_pdfCfg['prepared_title'];
$approvedBy    = SettingsHelper::get('pdf_approved_by_name', '');
$approvedTitle = SettingsHelper::get('pdf_approved_by_title', 'Municipal Environment and Natural Resources Officer');
$footerNote    = SettingsHelper::get('pdf_footer_note', 'System Generated via SIERRA (Web-Based Environmental Reporting Application) | Page 1 of 1');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ($lguLogo): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($lguLogo); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User / Account Report - Sierra</title>
    <link href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/material-symbols.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/material-symbols.css'); ?>">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Manrope', Arial, sans-serif;
            background: #eef2f1;
            color: #1f2937;
            font-size: 11px;
        }

        /* ===== Screen-only toolbar ===== */
        .toolbar {
            max-width: 100%;
            margin: 16px auto 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 0 12px;
        }
        .toolbar button {
            background: #10A37F;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .toolbar button:hover { background: #0D8568; }
        .toolbar a {
            color: #374151;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
        }
        .controls {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 14px;
        }
        .controls label { font-size: 12px; font-weight: 600; color: #374151; }
        .controls input, .controls select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 12px;
            font-family: inherit;
            background: #fff;
            color: #1f2937;
        }

        /* ===== Report page (fixed A4 portrait paper) ===== */
        .report {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 12mm 14mm;
            display: flex;
            flex-direction: column;
        }

        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding-bottom: 10px;
            border-bottom: 3px solid #10A37F;
        }
        .logo-box {
            width: 22mm;
            height: 22mm;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .logo-box img { max-width: 22mm; max-height: 22mm; object-fit: contain; }
        .logo-placeholder {
            width: 22mm;
            height: 22mm;
            border: 1px dashed #d1d5db;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 8px;
            text-align: center;
        }
        .org-block { flex: 1; text-align: center; padding: 0 6px; }
        .org-line1 { font-size: 10px; letter-spacing: 0.12em; color: #4b5563; text-transform: uppercase; }
        .org-name { font-size: 15px; font-weight: 800; color: #111827; margin-top: 2px; line-height: 1.25; }
        .org-muni { font-size: 11px; color: #374151; margin-top: 2px; font-weight: 600; }

        .report-title-block { text-align: center; margin: 12px 0 12px; }
        .report-title { font-size: 19px; font-weight: 800; letter-spacing: 0.02em; color: #0D8568; }
        .report-subtitle { font-size: 11px; color: #4b5563; margin-top: 3px; font-weight: 600; }
        .report-meta {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
            font-size: 10px;
            color: #6b7280;
        }
        .report-meta span {
            background: #f4faf7;
            border: 1px solid #dff0e9;
            border-radius: 999px;
            padding: 3px 10px;
        }

        /* ===== KPI stat cards ===== */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }
        .kpi-card {
            border: 1px solid #e5e7eb;
            border-top: 4px solid #10A37F;
            border-radius: 8px;
            padding: 10px 12px;
            background: #fafcfb;
            text-align: center;
        }
        .kpi-card .kpi-label { font-size: 8.5px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi-card .kpi-value { font-size: 22px; font-weight: 800; color: #111827; margin-top: 2px; line-height: 1.1; }
        .kpi-card .kpi-sub { font-size: 9px; color: #9ca3af; margin-top: 3px; }
        .kpi-green { border-top-color: #10B981; }
        .kpi-red   { border-top-color: #EF4444; }
        .kpi-blue  { border-top-color: #3B82F6; }
        .kpi-amber { border-top-color: #F59E0B; }

        /* ===== Table ===== */
        .user-table-wrap { margin-top: 14px; }
        .table-caption {
            font-size: 12px;
            font-weight: 800;
            color: #0D8568;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 6px 10px;
            background: #f4faf7;
            border: 1px solid #dff0e9;
            border-left: 5px solid #10A37F;
            border-radius: 8px;
            margin-bottom: 6px;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        table.data th {
            background: #f4faf7;
            color: #374151;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            text-align: left;
            padding: 5px 7px;
            border: 1px solid #dff0e9;
        }
        table.data td {
            padding: 4px 7px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
            vertical-align: top;
        }
        table.data tr:nth-child(even) td { background: #fafcfb; }
        .badge-active { color: #047857; font-weight: 700; }
        .badge-inactive { color: #b91c1c; font-weight: 700; }

        .signature-block {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
        }
        .sig-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .sig-line { border-bottom: 1px solid #374151; margin-top: 28px; }
        .sig-name { font-size: 12px; font-weight: 700; color: #111827; margin-top: 4px; text-align: center; }
        .sig-title { font-size: 10px; color: #6b7280; text-align: center; }

        .report-footer {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #6b7280;
        }
        .report-footer .brand { font-weight: 700; color: #0D8568; }
        .report-footer-note { margin-top: 6px; text-align: center; font-size: 8px; color: #9ca3af; }

        /* ===== Screen-only filter sidebar (main-sidebar style) ===== */
        .page-wrap { display: flex; align-items: flex-start; min-height: 100vh; }
        .filter-sidebar { width: 300px; flex-shrink: 0; background: #fff; border-right: 1px solid rgba(16,163,127,0.12); box-shadow: 2px 0 20px -8px rgba(16,163,127,0.18); position: sticky; top: 0; height: 100vh; display: flex; flex-direction: column; }
        .sidebar-head { padding: 16px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .sidebar-head .head-icon { width: 38px; height: 38px; border-radius: 8px; background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 15px; box-shadow: 0 4px 10px rgba(16,163,127,0.3); flex-shrink: 0; }
        .sidebar-head .head-title { font-size: 14px; font-weight: 800; color: #111827; }
        .sidebar-head .head-sub { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 1px; }
        .sidebar-form { flex: 1; display: flex; flex-direction: column; min-height: 0; }
        .sidebar-body { flex: 1; overflow-y: auto; padding: 14px 16px; display: flex; flex-direction: column; gap: 16px; }
        .sidebar-group { display: flex; flex-direction: column; gap: 10px; }
        .quick-range { display: flex; flex-wrap: wrap; gap: 5px; }
        .quick-range a {
            padding: 4px 10px;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            background: #fff;
            font-size: 11px;
            font-weight: 600;
            color: #374151;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .quick-range a:hover { border-color: #10A37F; color: #10A37F; background: #F0FBF6; }
        .sidebar-group-label { font-size: 10px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.06em; }
        .filter-field { display: flex; flex-direction: column; gap: 4px; }
        .filter-field label { font-size: 11px; font-weight: 600; color: #374151; }
        .filter-field select, .filter-field input { width: 100%; padding: 9px 10px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 12.5px; font-family: inherit; background: #fff; color: #1f2937; transition: all 0.15s ease; }
        .filter-field select:hover, .filter-field input:hover { border-color: #d1d5db; }
        .filter-field select:focus, .filter-field input:focus { border-color: #10A37F; outline: none; box-shadow: 0 0 0 3px rgba(16,163,127,0.12); }
        .section-check-list { display: flex; flex-direction: column; gap: 2px; }
        .section-check { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; cursor: pointer; transition: background 0.12s ease; font-size: 12px; color: #374151; font-weight: 500; }
        .section-check:hover { background: #F0FBF6; }
        .section-check input { accent-color: #10A37F; width: 15px; height: 15px; cursor: pointer; }
        .sidebar-footer { padding: 14px 16px; border-top: 1px solid #f3f4f6; background: #fff; display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }
        .btn-apply { width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.2s ease; }
        .btn-apply:hover { box-shadow: 0 6px 16px rgba(16,163,127,0.35); transform: translateY(-1px); }
        .btn-reset { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 12px; border: 1.5px solid #e5e7eb; border-radius: 8px; background: #fff; color: #6b7280; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.15s ease; }
        .btn-reset:hover { color: #EF4444; border-color: #EF4444; background: #FEF2F2; }
        .page-main { flex: 1; min-width: 0; padding: 16px; }
        @media (max-width: 900px) { .page-wrap { flex-direction: column; } .filter-sidebar { width: 100%; position: static; height: auto; border-right: none; border-bottom: 1px solid rgba(16,163,127,0.12); } }

        @page {
            size: A4 portrait;
            margin: 12mm 14mm 16mm;
        }
        @media print {
            body { background: #ffffff !important; }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .filter-sidebar { display: none !important; }
            .page-wrap { display: block; padding: 0; }
            .page-main { padding: 0; }
            .toolbar { display: none !important; }
            .report {
                width: 100%;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            table.data tr { break-inside: avoid; }
            .report-title-block, .signature-block, .kpi-row { break-inside: avoid; }
        }
    </style>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/export-print.css'); ?>">
</head>
<body>
    <div class="page-wrap">
        <aside class="filter-sidebar">
            <div class="sidebar-head">
                <div class="head-icon"><i class="fas fa-sliders-h"></i></div>
                <div>
                    <div class="head-title">Filters</div>
                    <div class="head-sub">Refine report</div>
                </div>
            </div>
            <form class="sidebar-form" method="get" action="<?php echo BASE_URL; ?>index.php">
                <input type="hidden" name="page" value="users-report">
                <div class="sidebar-body">
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Account</div>
                        <div class="filter-field">
                            <label for="sideRole">User Group</label>
                            <select name="role" id="sideRole">
                                <option value="all" <?php echo $role === 'all' ? 'selected' : ''; ?>>All Users</option>
                                <option value="menro" <?php echo $role === 'menro' ? 'selected' : ''; ?>>MENRO Users</option>
                                <option value="barangay" <?php echo $role === 'barangay' ? 'selected' : ''; ?>>Barangay Users</option>
                                <option value="citizen" <?php echo $role === 'citizen' ? 'selected' : ''; ?>>Reporters (Resident &amp; Non-Resident)</option>
                            </select>
                        </div>
                        <div class="filter-field" id="residencyField" style="<?php echo $role === 'citizen' ? '' : 'display:none;'; ?>">
                            <label for="sideResidency">Residency</label>
                            <select name="residency" id="sideResidency">
                                <option value="all" <?php echo $residency === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="resident" <?php echo $residency === 'resident' ? 'selected' : ''; ?>>Residents Only</option>
                                <option value="non_resident" <?php echo $residency === 'non_resident' ? 'selected' : ''; ?>>Non-Residents Only</option>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sideBarangay">Barangay</label>
                            <select name="barangay" id="sideBarangay">
                                <option value="0" <?php echo $barangay === 0 ? 'selected' : ''; ?>>All Barangays</option>
                                <?php foreach ($barangays as $b): ?>
                                <option value="<?php echo (int)$b['id']; ?>" <?php echo $barangay === (int)$b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sideStatus">Account Status</label>
                            <select name="status" id="sideStatus">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Date</div>
                        <div class="quick-range">
                            <?php foreach ($quickRanges as $qr): ?>
                            <a href="<?php echo BASE_URL; ?>index.php<?php echo $baseQuery . '&created_from=' . $qr['from'] . '&created_to=' . $qr['to']; ?>"><?php echo htmlspecialchars($qr['name']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Date Range</div>
                        <div class="filter-field">
                            <label for="sideCreatedFrom">Created From</label>
                            <input type="date" name="created_from" id="sideCreatedFrom" value="<?php echo htmlspecialchars($created_from); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="sideCreatedTo">Created To</label>
                            <input type="date" name="created_to" id="sideCreatedTo" value="<?php echo htmlspecialchars($created_to); ?>">
                        </div>
                    </div>
                </div>
                <div class="sidebar-footer">
                    <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="<?php echo BASE_URL; ?>index.php?page=users-report" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </aside>

        <div class="page-main">
            <div class="toolbar">
                <a href="<?php echo BASE_URL; ?>index.php?page=manage-users"><i class="fas fa-arrow-left" style="margin-right:6px;"></i>Back</a>
                <button type="button" onclick="window.print()"><i class="fas fa-print" style="margin-right:6px;"></i>Print</button>
                <button type="button" onclick="window.print()"><i class="fas fa-file-pdf" style="margin-right:6px;"></i>Save as PDF</button>
                <span class="hint" style="color:#6b7280; font-size:11px;">Tip: choose "Save as PDF" as the printer destination for a PDF export.</span>
            </div>

            <div class="report">
        <!-- ===== Official LGU Header ===== -->
        <header class="report-header">
            <div class="logo-box">
                <?php if ($lguLogo): ?>
                    <img src="<?php echo htmlspecialchars($lguLogo); ?>" alt="LGU Logo">
                <?php else: ?>
                    <div class="logo-placeholder">LGU<br>Logo</div>
                <?php endif; ?>
            </div>
            <div class="org-block">
                <div class="org-line1">Republic of the Philippines</div>
                <div class="org-name"><?php echo htmlspecialchars($officeName); ?></div>
                <div class="org-muni"><?php echo htmlspecialchars($municipality); ?></div>
            </div>
            <div class="logo-box">
                <?php if ($menroLogo): ?>
                    <img src="<?php echo htmlspecialchars($menroLogo); ?>" alt="MENRO Logo">
                <?php else: ?>
                    <div class="logo-placeholder">MENRO<br>Logo</div>
                <?php endif; ?>
            </div>
        </header>

        <!-- ===== Report Title & Metadata ===== -->
        <div class="report-title-block">
            <div class="report-title"><?php echo htmlspecialchars($reportTitle); ?></div>
            <div class="report-subtitle">Registered Users in the System &middot; <?php echo htmlspecialchars($municipality); ?></div>
            <div class="report-meta">
                <span><strong>Date Range:</strong> <?php echo htmlspecialchars($createdText); ?></span>
                <span><strong>Generated On:</strong> <?php echo htmlspecialchars($generatedOn); ?></span>
                <span><strong>Total Users:</strong> <?php echo number_format($totalUsers); ?></span>
            </div>
        </div>

        <!-- ===== Users Table ===== -->
        <div class="user-table-wrap">
            <div class="table-caption">Registered Users</div>
            <?php if (empty($users)): ?>
            <div style="text-align:center; padding:30px 0; color:#9ca3af; font-size:12px;">No users found for the selected filters.</div>
            <?php else: ?>
            <table class="data">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Barangay</th>
                        <th>Residency</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><strong><?php echo htmlspecialchars(trim($u['first_name'] . ' ' . $u['last_name'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($u['email'] ?: '—'); ?></td>
                        <td><?php echo !empty($u['contact_number']) ? htmlspecialchars(formatPhoneNumber($u['contact_number'])) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($u['barangay_name'] ?: '—'); ?></td>
                        <td><?php
                            if (isset($u['is_resident']) && (int)$u['is_resident'] === 1) { echo 'Resident'; }
                            elseif (isset($u['is_resident']) && (int)$u['is_resident'] === 0) { echo 'Non-Resident'; }
                            else { echo '—'; }
                        ?></td>
                        <td><?php echo htmlspecialchars(roleLabelOf($u['user_type'])); ?></td>
                        <td>
                            <?php if ((int)$u['is_active'] === 1): ?>
                                <span class="badge-active">Active</span>
                            <?php else: ?>
                                <span class="badge-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ===== Formal Sign-off ===== -->
        <div class="signature-block">
            <div>
                <div class="sig-label">Prepared by:</div>
                <div class="sig-line"></div>
                <div class="sig-name"><?php echo htmlspecialchars($preparedBy ?: $generatedBy); ?></div>
                <div class="sig-title"><?php echo htmlspecialchars($preparedTitle); ?></div>
            </div>
            <div>
                <div class="sig-label">Noted and Approved by:</div>
                <div class="sig-line"></div>
                <div class="sig-name"><?php echo htmlspecialchars($approvedBy ?: '____________________'); ?></div>
                <div class="sig-title"><?php echo htmlspecialchars($approvedTitle); ?></div>
            </div>
        </div>

        <!-- ===== Audit Trail Footer ===== -->
        <footer class="report-footer">
            <span>Date Printed: <?php echo date('F j, Y'); ?></span>
            <span>Time Printed: <?php echo date('h:i A'); ?></span>
            <span><?php echo htmlspecialchars($systemName); ?> &middot; Web-Based Environmental Reporting System</span>
        </footer>
        <div class="report-footer-note"><?php echo htmlspecialchars($footerNote); ?></div>
            </div>
        </div>
    </div>

    <?php if ($autoprint): ?>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 700);
        });
    </script>
    <?php endif; ?>

    <script>
        // Residency only applies to Reporters — toggle the field live with the User Group.
        (function () {
            var role = document.getElementById('sideRole');
            var field = document.getElementById('residencyField');
            var residency = document.getElementById('sideResidency');
            if (!role || !field || !residency) return;
            role.addEventListener('change', function () {
                var show = role.value === 'citizen';
                field.style.display = show ? '' : 'none';
                if (!show) residency.value = 'all';
            });
        })();
    </script>
<script src="<?php echo BASE_URL; ?>assets/js/export-print.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/export-print.js'); ?>"></script>
</body>
</html>
