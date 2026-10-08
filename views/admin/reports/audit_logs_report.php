<?php
// views/admin/reports/audit_logs_report.php - PRINTABLE AUDIT LOG REPORT
// A4/paper-agnostic printable report of the system's audit trail, grouped by
// role (MENRO, Barangay Officials, Reporters) or by individual user.
// Opened via:
//   index.php?page=audit-logs-report&from=YYYY-MM-DD&to=YYYY-MM-DD&group_by=role|user[&autoprint=1]
require_once dirname(__DIR__, 3) . '/config/config.php';
requireLogin();

// Audit logs are reserved for the System Administrator.
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "You are not permitted to view audit logs.";
    header("Location: " . BASE_URL . "index.php?page=dashboard");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// ------------------------------------------------------------
// INPUTS
// ------------------------------------------------------------
$from = isset($_GET['from']) ? preg_replace('/[^0-9-]', '', $_GET['from']) : '';
$to   = isset($_GET['to'])   ? preg_replace('/[^0-9-]/', '', $_GET['to'])   : '';
$groupBy = ($_GET['group_by'] ?? 'role') === 'user' ? 'user' : 'role';
$autoprint = !empty($_GET['autoprint']);
$roleFilter   = in_array($_GET['role'] ?? 'all', ['all', 'menro', 'barangay', 'citizen'], true) ? $_GET['role'] : 'all';
$statusFilter = in_array($_GET['status'] ?? 'all', ['SUCCESS', 'FAILED', 'UNAUTHORIZED_ATTEMPT'], true) ? $_GET['status'] : 'all';
$actionFilter = isset($_GET['action']) ? trim($_GET['action']) : 'all';
$residencyFilter = in_array($_GET['residency'] ?? 'all', ['all', 'resident', 'non_resident'], true) ? $_GET['residency'] : 'all';
$barangayFilter  = isset($_GET['barangay']) ? (int)$_GET['barangay'] : 0;
$search       = isset($_GET['search']) ? trim($_GET['search']) : '';

function isValidDateStr($s) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
    [$y, $m, $d] = array_map('intval', explode('-', $s));
    return checkdate($m, $d, $y);
}
if ($from && !isValidDateStr($from)) $from = '';
if ($to   && !isValidDateStr($to))   $to   = '';

// ------------------------------------------------------------
// QUERY LOGS
// ------------------------------------------------------------
$where = ["1=1"];
$params = [];
if ($from) { $where[] = "DATE(a.created_at) >= :from"; $params[':from'] = $from; }
if ($to)   { $where[] = "DATE(a.created_at) <= :to";   $params[':to']   = $to;   }
if ($actionFilter !== '' && $actionFilter !== 'all') {
    $where[] = "a.action = :action";
    $params[':action'] = $actionFilter;
}
if ($residencyFilter === 'resident') {
    $where[] = "u.is_resident = 1";
} elseif ($residencyFilter === 'non_resident') {
    $where[] = "u.is_resident = 0";
}
if ($barangayFilter > 0) {
    $where[] = "u.barangay_id = :barangay";
    $params[':barangay'] = $barangayFilter;
}
if ($search !== '') {
    $where[] = "(a.description LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR a.action LIKE :search OR a.status LIKE :search)";
    $params[':search'] = "%$search%";
}

$sql = "SELECT a.*,
               CONCAT(u.first_name, ' ', u.last_name) AS user_name,
               u.email AS user_email,
               u.user_type AS user_role
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.created_at ASC
        LIMIT 2000";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct actions + barangays for the on-page filter controls.
$actionList = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$barangayList = $db->query("SELECT id, name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------------------------
// HELPERS
// ------------------------------------------------------------
function friendlyDeviceName($ua) {
    if (empty(trim($ua))) return null;
    $ua = ' ' . $ua . ' ';
    if (stripos($ua, 'iPhone') !== false) {
        $device = 'iPhone' . iosVersion($ua);
    } elseif (stripos($ua, 'iPad') !== false) {
        $device = 'iPad' . iosVersion($ua);
    } elseif (stripos($ua, 'Android') !== false) {
        $androidVer = '';
        if (preg_match('/Android\s([0-9.]+)/i', $ua, $m)) $androidVer = ' ' . $m[1];
        $device = (stripos($ua, 'Mobile') !== false ? 'Android Phone' : 'Android Tablet') . $androidVer;
    } elseif (stripos($ua, 'CrOS') !== false) {
        $device = 'Chromebook';
    } elseif (preg_match('/Windows NT 11/i', $ua)) {
        $device = 'Windows 11';
    } elseif (preg_match('/Windows NT 10\.0/i', $ua)) {
        $device = 'Windows 10/11';
    } elseif (preg_match('/Windows NT 6\.3/i', $ua)) {
        $device = 'Windows 8.1';
    } elseif (preg_match('/Windows NT 6\.1/i', $ua)) {
        $device = 'Windows 7';
    } elseif (stripos($ua, 'Windows') !== false) {
        $device = 'Windows PC';
    } elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) {
        $device = 'Mac';
    } elseif (stripos($ua, 'Linux') !== false) {
        $device = 'Linux Device';
    } else {
        $device = 'Unknown Device';
    }
    if (stripos($ua, 'SamsungBrowser') !== false) {
        $browser = 'Samsung Internet';
    } elseif (stripos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) {
        $browser = 'Opera';
    } elseif (stripos($ua, 'Chrome/') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (preg_match('/Version\/[\d.]+.*Safari/i', $ua)) {
        $browser = 'Safari';
    } elseif (stripos($ua, 'Trident/') !== false || stripos($ua, 'MSIE') !== false) {
        $browser = 'Internet Explorer';
    } else {
        $browser = null;
    }
    return $browser ? $device . ' · ' . $browser : $device;
}

function iosVersion($ua) {
    if (preg_match('/OS (\d+)[_\.](\d+)/', $ua, $m)) {
        return ' ' . $m[1] . '.' . $m[2];
    }
    return '';
}

function roleGroupOf($role) {
    if (in_array($role, ['admin', 'menro_staff'], true)) return 'menro';
    if ($role === 'barangay_personnel') return 'barangay';
    return 'citizen';
}

function roleGroupLabel($group) {
    switch ($group) {
        case 'menro':     return 'MENRO';
        case 'barangay':  return 'Barangay Officials';
        case 'citizen':   return 'Reporters (Citizens)';
        default:          return 'System';
    }
}

$actionModuleMap = [
    'Login' => 'Auth', 'Logout' => 'Auth', 'User Registration' => 'Auth',
    'Password Reset' => 'Auth',
    'Create Report' => 'Reports', 'Update Report' => 'Reports',
    'Delete Report' => 'Reports', 'Cancel Report' => 'Reports',
    'Support Report' => 'Reports', 'Verify Report' => 'Reports',
    'Reject Report' => 'Reports', 'Resolve Report' => 'Reports',
    'Escalate Report' => 'Reports', 'Approve Escalation' => 'Reports',
    'Reject Escalation' => 'Reports', 'Status Change' => 'Reports',
    'Update Status' => 'Reports', 'Add Note' => 'Reports',
    'Evidence Upload' => 'Reports', 'Reclassify Impact' => 'Reports',
    'Create Staff Account' => 'Staff', 'Toggle User Status' => 'Users',
    'Update User Role' => 'Users', 'Delete User' => 'Users',
    'Create Category' => 'Categories', 'Update Category' => 'Categories',
    'Delete Category' => 'Categories', 'Toggle Category Status' => 'Categories',
    'Create Announcement' => 'Announcements',
    'Create Role' => 'Permissions', 'Update Role' => 'Permissions',
    'Delete Role' => 'Permissions', 'Update Permissions' => 'Permissions',
    'Update System Settings' => 'Settings',
    'Create Barangay' => 'Barangays', 'Update Barangay' => 'Barangays',
    'Delete Barangay' => 'Barangays',
    'Create Tag' => 'Tags', 'Update Tag' => 'Tags', 'Delete Tag' => 'Tags',
];

// Decorate each log with resolved name/role/module.
foreach ($logs as &$log) {
    $log['_name']  = $log['user_name'] ?: ($log['actor_name'] ?? null);
    $log['_email'] = $log['user_email'] ?: '';
    $log['_role']  = $log['user_role'] ?: ($log['actor_role'] ?? null);
    if (empty($log['_role']) && ($log['user_id'] || $log['_name'])) {
        $log['_role'] = 'citizen';
    }
    $log['_module'] = $log['target_module'] ?? ($actionModuleMap[$log['action']] ?? 'General');
    $log['_group']  = $log['_name'] ? roleGroupOf($log['_role']) : 'system';
    $log['_device'] = friendlyDeviceName($log['user_agent'] ?? '');
}
unset($log);

// ------------------------------------------------------------
// APPLY ROLE & STATUS FILTERS
// ------------------------------------------------------------
if ($roleFilter !== 'all') {
    $logs = array_values(array_filter($logs, function ($log) use ($roleFilter) {
        return $log['_group'] === $roleFilter;
    }));
}
if ($statusFilter !== 'all') {
    $logs = array_values(array_filter($logs, function ($log) use ($statusFilter) {
        return ($log['status'] ?? 'SUCCESS') === $statusFilter;
    }));
}

// ------------------------------------------------------------
// BUILD GROUPS
// ------------------------------------------------------------
$groups = [];
if ($groupBy === 'user') {
    $byUser = [];
    foreach ($logs as $log) {
        $key = $log['_name'] ?: 'System';
        if (!isset($byUser[$key])) {
            $byUser[$key] = ['label' => $key, 'email' => $log['_email'], 'role' => $log['_role'], 'rows' => []];
        }
        $byUser[$key]['rows'][] = $log;
    }
    ksort($byUser);
    foreach ($byUser as $g) {
        $groups[] = $g;
    }
} else {
    $order = ['menro', 'barangay', 'citizen', 'system'];
    $byRole = [];
    foreach ($order as $g) $byRole[$g] = ['label' => roleGroupLabel($g), 'role' => $g, 'rows' => []];
    foreach ($logs as $log) {
        $byRole[$log['_group']]['rows'][] = $log;
    }
    foreach ($order as $g) {
        if (!empty($byRole[$g]['rows'])) $groups[] = $byRole[$g];
    }
}

$total = count($logs);
$truncated = $total >= 2000;

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

$rangeText = 'All Time';
if ($from && $to && $from === $to) $rangeText = date('M j, Y', strtotime($from));
elseif ($from && $to) $rangeText = date('M j, Y', strtotime($from)) . ' to ' . date('M j, Y', strtotime($to));
elseif ($from)        $rangeText = 'From ' . date('M j, Y', strtotime($from));
elseif ($to)          $rangeText = 'Up to ' . date('M j, Y', strtotime($to));

$groupLabel = ($groupBy === 'user') ? 'Grouped by User' : 'Grouped by Role';

$statusLabels = ['SUCCESS' => 'Success', 'FAILED' => 'Failed', 'UNAUTHORIZED_ATTEMPT' => 'Unauthorized'];
$roleFilterText   = $roleFilter === 'all' ? 'All Roles' : roleGroupLabel($roleFilter);
$statusFilterText = $statusFilter === 'all' ? 'All Statuses' : ($statusLabels[$statusFilter] ?? $statusFilter);
$actionFilterText = ($actionFilter === '' || $actionFilter === 'all') ? 'All Actions' : $actionFilter;
$residencyText = $residencyFilter === 'resident' ? 'Residents' : ($residencyFilter === 'non_resident' ? 'Non-Residents' : 'All Users');
$barangayText = 'All Barangays';
if ($barangayFilter > 0) {
    foreach ($barangayList as $b) {
        if ((int)$b['id'] === $barangayFilter) { $barangayText = $b['name']; break; }
    }
}
$searchText = $search !== '' ? $search : '';

// Report title reflects the applied filters — period + role/status/action/residency/barangay.
$auditPeriodDesc = '';
if ($from !== '' || $to !== '') {
    if ($from === date('Y-m-d') && $to === date('Y-m-d'))                        $auditPeriodDesc = 'Today';
    elseif ($from === date('Y-m-d', strtotime('-6 days')) && $to === date('Y-m-d')) $auditPeriodDesc = 'This Week';
    elseif ($from === date('Y-m-01') && $to === date('Y-m-d'))                   $auditPeriodDesc = 'This Month';
    elseif ($from === date('Y-01-01') && $to === date('Y-m-d'))                  $auditPeriodDesc = 'This Year';
    else                                                                         $auditPeriodDesc = $rangeText;
}

$auditScopeParts = [];
if ($roleFilter !== 'all')            $auditScopeParts[] = $roleFilterText;
if ($statusFilter !== 'all')          $auditScopeParts[] = strtoupper($statusFilterText);
if ($actionFilter !== '' && $actionFilter !== 'all') $auditScopeParts[] = 'Action: ' . $actionFilterText;
if ($residencyFilter !== 'all')       $auditScopeParts[] = $residencyText;
if ($barangayFilter > 0)              $auditScopeParts[] = 'Barangay: ' . $barangayText;

$reportTitle = 'AUDIT LOG REPORT';
if (!empty($auditScopeParts)) $reportTitle .= ' - ' . implode(' - ', $auditScopeParts);
if ($auditPeriodDesc !== '')  $reportTitle .= ' - ' . $auditPeriodDesc;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="<?php echo BASE_URL; ?>assets/js/theme.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/theme.js'); ?>"></script>
    <?php if ($lguLogo): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($lguLogo); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log Report - Sierra</title>
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
        .controls input[type="date"], .controls input[type="text"], .controls select {
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

        /* ===== Group sections ===== */
        .group-section { margin-top: 16px; break-inside: avoid; }
        .group-heading {
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
        .group-heading .count { float: right; font-weight: 700; }

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
        .mono { font-family: 'Manrope', sans-serif; font-size: 9.5px; }
        .status-ok      { color: #047857; font-weight: 700; }
        .status-fail    { color: #b91c1c; font-weight: 700; }
        .status-unauth  { color: #c2410c; font-weight: 700; }

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
            .group-section { break-inside: auto; }
            .group-heading { break-after: avoid; }
            table.data tr { break-inside: avoid; }
        }
    </style>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/export-print.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/buttons.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/buttons.css'); ?>">
<script src="<?php echo BASE_URL; ?>assets/js/app-ui.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/app-ui.js'); ?>" defer></script>
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
                <input type="hidden" name="page" value="audit-logs-report">
                <div class="sidebar-body">
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Filters</div>
                        <div class="filter-field">
                            <input type="text" name="search" id="sideSearch" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search activity…">
                        </div>
                        <div class="filter-field">
                            <label for="sideRole">Role</label>
                            <select name="role" id="sideRole">
                                <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                <option value="menro" <?php echo $roleFilter === 'menro' ? 'selected' : ''; ?>>MENRO</option>
                                <option value="barangay" <?php echo $roleFilter === 'barangay' ? 'selected' : ''; ?>>Barangay Officials</option>
                                <option value="citizen" <?php echo $roleFilter === 'citizen' ? 'selected' : ''; ?>>Reporters (Citizens)</option>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sideStatus">Status</label>
                            <select name="status" id="sideStatus">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="SUCCESS" <?php echo $statusFilter === 'SUCCESS' ? 'selected' : ''; ?>>Success</option>
                                <option value="FAILED" <?php echo $statusFilter === 'FAILED' ? 'selected' : ''; ?>>Failed</option>
                                <option value="UNAUTHORIZED_ATTEMPT" <?php echo $statusFilter === 'UNAUTHORIZED_ATTEMPT' ? 'selected' : ''; ?>>Unauthorized</option>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sideAction">Action</label>
                            <select name="action" id="sideAction">
                                <option value="all" <?php echo $actionFilter === 'all' ? 'selected' : ''; ?>>All Actions</option>
                                <?php foreach ($actionList as $act): ?>
                                <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $actionFilter === $act ? 'selected' : ''; ?>><?php echo htmlspecialchars($act); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sideUser">User</label>
                            <select name="residency" id="sideUser">
                                <option value="all" <?php echo $residencyFilter === 'all' ? 'selected' : ''; ?>>All Users</option>
                                <option value="resident" <?php echo $residencyFilter === 'resident' ? 'selected' : ''; ?>>Resident</option>
                                <option value="non_resident" <?php echo $residencyFilter === 'non_resident' ? 'selected' : ''; ?>>Non-Resident</option>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sideBarangay">Barangay</label>
                            <select name="barangay" id="sideBarangay">
                                <option value="0" <?php echo $barangayFilter === 0 ? 'selected' : ''; ?>>All Barangays</option>
                                <?php foreach ($barangayList as $b): ?>
                                <option value="<?php echo (int)$b['id']; ?>" <?php echo $barangayFilter === (int)$b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sideGroupBy">Group By</label>
                            <select name="group_by" id="sideGroupBy">
                                <option value="role" <?php echo $groupBy === 'role' ? 'selected' : ''; ?>>Role</option>
                                <option value="user" <?php echo $groupBy === 'user' ? 'selected' : ''; ?>>User</option>
                            </select>
                        </div>
                    </div>
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Date</div>
                        <div class="quick-range">
                            <a href="#" onclick="setQuickReportRange('today'); return false;">Today</a>
                            <a href="#" onclick="setQuickReportRange('week'); return false;">This Week</a>
                            <a href="#" onclick="setQuickReportRange('month'); return false;">This Month</a>
                            <a href="#" onclick="setQuickReportRange('year'); return false;">This Year</a>
                            <a href="#" onclick="setQuickReportRange('all'); return false;">All Records</a>
                        </div>
                    </div>
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Date Range</div>
                        <div class="filter-field">
                            <label for="sideFrom">Date From</label>
                            <input type="date" name="from" id="sideFrom" value="<?php echo htmlspecialchars($from); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="sideTo">Date To</label>
                            <input type="date" name="to" id="sideTo" value="<?php echo htmlspecialchars($to); ?>">
                        </div>
                    </div>
                </div>
                <div class="sidebar-footer">
                    <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="<?php echo BASE_URL; ?>index.php?page=audit-logs-report" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </aside>

        <div class="page-main">
            <div class="toolbar">
                <a href="<?php echo BASE_URL; ?>index.php?page=audit-logs"><i class="fas fa-arrow-left" style="margin-right:6px;"></i>Back</a>
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
            <div class="report-subtitle">System Activity Trail &middot; <?php echo htmlspecialchars($groupLabel); ?></div>
            <div class="report-meta">
                <span><strong>Date Range:</strong> <?php echo htmlspecialchars($rangeText); ?></span>
                <span><strong>Total Entries:</strong> <?php echo number_format($total); ?></span>
                <span><strong>Groupings:</strong> <?php echo count($groups); ?></span>
                <span><strong>Generated On:</strong> <?php echo htmlspecialchars($generatedOn); ?></span>
            </div>
            <?php if ($truncated): ?>
            <div style="margin-top:6px; font-size:10px; color:#b45309;">Showing the most recent 2,000 entries within the selected range.</div>
            <?php endif; ?>
        </div>

        <!-- ===== Grouped Log Tables ===== -->
        <?php if (empty($logs)): ?>
        <div style="text-align:center; padding:40px 0; color:#9ca3af; font-size:12px;">No audit log entries found for the selected date range.</div>
        <?php else: ?>
            <?php foreach ($groups as $gi => $group): ?>
            <div class="group-section">
                <div class="group-heading">
                    <?php echo htmlspecialchars($group['label']); ?>
                    <?php if (!empty($group['email'])): ?><span style="font-weight:600; text-transform:none;"> &middot; <?php echo htmlspecialchars($group['email']); ?></span><?php endif; ?>
                    <?php if (!empty($group['role'])): ?><span style="font-weight:600; text-transform:none;"> &middot; <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $group['role']))); ?></span><?php endif; ?>
                    <span class="count"><?php echo count($group['rows']); ?></span>
                </div>
                <table class="data">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Timestamp</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Status</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Device</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group['rows'] as $i => $log): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['_module']); ?></td>
                            <td>
                                <?php $st = $log['status'] ?? 'SUCCESS';
                                    $cls = $st === 'FAILED' ? 'status-fail' : ($st === 'UNAUTHORIZED_ATTEMPT' ? 'status-unauth' : 'status-ok'); ?>
                                <span class="<?php echo $cls; ?>"><?php echo htmlspecialchars($st); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($log['description'] ?: '—'); ?></td>
                            <td class="mono"><?php echo htmlspecialchars($log['ip_address'] ?: '—'); ?></td>
                            <td><?php echo $log['_device'] ? htmlspecialchars($log['_device']) : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

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
            <span><?php echo htmlspecialchars($systemName); ?> &middot; Audit Trail</span>
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
        function setQuickReportRange(range) {
            var f = document.getElementById('sideFrom');
            var t = document.getElementById('sideTo');
            if (!f || !t) return;
            var today = new Date();
            var ymd = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
            var from = today, to = today;
            if (range === 'all') { f.value = ''; t.value = ''; }
            else if (range === 'week') { from = new Date(today); from.setDate(today.getDate() - 6); }
            else if (range === 'month') { from = new Date(today.getFullYear(), today.getMonth(), 1); }
            else if (range === 'year') { from = new Date(today.getFullYear(), 0, 1); }
            if (range !== 'all') { f.value = ymd(from); t.value = ymd(to); }
            var form = f.closest('form');
            if (form) { if (form.requestSubmit) form.requestSubmit(); else form.submit(); }
        }
    </script>
<script src="<?php echo BASE_URL; ?>assets/js/export-print.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/export-print.js'); ?>"></script>
</body>
</html>
