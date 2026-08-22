<?php
// views/admin/reports/dashboard_report.php - DEDICATED PRINT/EXPORT REPORT PAGE
// A4-portrait printable "Dashboard & Analytics Report" generated from the
// MENRO Decision Dashboard. Opened in a new tab via:
//   index.php?page=dashboard-report&range=week|month|year|all|custom&cats=1,2,3[&autoprint=1]
//   For custom ranges also pass from=YYYY-MM-DD&to=YYYY-MM-DD (inclusive).
// Uses a dedicated @media print stylesheet so charts and KPI cards print cleanly.

require_once dirname(__DIR__, 3) . '/config/config.php';
requireRole('admin');

$database = new Database();
$db = $database->getConnection();

// ------------------------------------------------------------
// INPUTS
// ------------------------------------------------------------
$range = isset($_GET['range']) ? $_GET['range'] : 'all';
if (!in_array($range, ['week', 'month', 'year', 'all', 'custom'], true)) $range = 'all';

$from = isset($_GET['from']) ? preg_replace('/[^0-9-]/', '', $_GET['from']) : '';
$to   = isset($_GET['to'])   ? preg_replace('/[^0-9-]/', '', $_GET['to'])   : '';

function isValidDateStr($s) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
    [$y, $m, $d] = array_map('intval', explode('-', $s));
    return checkdate($m, $d, $y);
}

$cats = [];
if (!empty($_GET['cats'])) {
    foreach (explode(',', $_GET['cats']) as $cid) {
        if (ctype_digit((string)$cid)) $cats[] = (int)$cid;
    }
}
$autoprint = !empty($_GET['autoprint']);

// ------------------------------------------------------------
// DATE WINDOW + PERIOD LABELS
// ------------------------------------------------------------
switch ($range) {
    case 'week':
        $startDate   = date('Y-m-d H:i:s', strtotime('-7 days'));
        $periodStart = date('Y-m-d H:i:s', strtotime('-7 days'));
        $periodLabel = 'This Week';
        $rangeLabel  = 'Weekly';
        $endDate     = date('Y-m-d H:i:s');
        break;
    case 'month':
        $startDate   = date('Y-m-d H:i:s', strtotime('-1 month'));
        $periodStart = date('Y-m-d H:i:s', strtotime('-1 month'));
        $periodLabel = 'This Month';
        $rangeLabel  = 'Monthly';
        $endDate     = date('Y-m-d H:i:s');
        break;
    case 'year':
        $startDate   = date('Y-m-d H:i:s', strtotime('-1 year'));
        $periodStart = date('Y-m-d H:i:s', strtotime(date('Y-01-01')));
        $periodLabel = 'This Year';
        $rangeLabel  = 'Annual';
        $endDate     = date('Y-m-d H:i:s');
        break;
    case 'custom':
        if (isValidDateStr($from) && isValidDateStr($to)) {
            $startDate   = $from . ' 00:00:00';
            $endDate     = $to . ' 23:59:59';
            $periodStart = $startDate;
            $periodLabel = 'Custom Range';
            $rangeLabel  = 'Custom';
        } else {
            $range       = 'all';
            $startDate   = '1970-01-01 00:00:00';
            $periodStart = date('Y-m-d H:i:s', strtotime(date('Y-m-01')));
            $periodLabel = 'This Month';
            $rangeLabel  = 'All-Time';
            $endDate     = date('Y-m-d H:i:s');
        }
        break;
    default:
        $startDate   = '1970-01-01 00:00:00';
        $periodStart = date('Y-m-d H:i:s', strtotime(date('Y-m-01')));
        $periodLabel = 'This Month';
        $rangeLabel  = 'All-Time';
        $endDate     = date('Y-m-d H:i:s');
        break;
}

// Upper-bound clause only for explicit custom ranges; presets end "now".
$endSql = ($range === 'custom') ? ' AND r.created_at <= :end' : '';

// Category filter clause — ids are int-cast, safe to inline.
$catSql = '';
if (!empty($cats)) {
    $catSql = ' AND r.category_id IN (' . implode(',', array_map('intval', $cats)) . ')';
}

// ------------------------------------------------------------
// 1. TRANSACTIONAL SERIES (grouped by Month)
// ------------------------------------------------------------
$monthlyCounts = [];
$monthStmt = $db->prepare("
    SELECT DATE_FORMAT(r.created_at, '%Y-%m') AS ym, COUNT(*) AS total
    FROM reports r
    WHERE r.created_at >= :start $catSql$endSql
    GROUP BY ym
    ORDER BY ym ASC
");
$monthStmt->execute([':start' => $startDate] + ($endSql ? [':end' => $endDate] : []));
foreach ($monthStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $monthlyCounts[$row['ym']] = (int)$row['total'];
}

// Chart window: 12 months for year/all, 3 months for month/week,
// and for custom ranges scale with the span (capped at 12 months).
if ($range === 'custom') {
    $spanMonths  = max(0, (strtotime($to) - strtotime($from)) / (30.4 * 86400));
    $monthWindow = max(3, min(12, (int)ceil($spanMonths)));
} else {
    $monthWindow = ($range === 'year' || $range === 'all') ? 12 : 3;
}
$monthKeys   = [];
$monthLabels = [];
for ($i = $monthWindow - 1; $i >= 0; $i--) {
    $ts            = strtotime("first day of -$i month");
    $monthKeys[]   = date('Y-m', $ts);
    $monthLabels[] = date('M y', $ts);
}
$seriesData = [];
foreach ($monthKeys as $k) {
    $seriesData[] = $monthlyCounts[$k] ?? 0;
}

// ------------------------------------------------------------
// 2. KPI METRICS
// ------------------------------------------------------------
// Total Count (within the selected date window + category filter)
$totalStmt = $db->prepare("SELECT COUNT(*) FROM reports r WHERE r.created_at >= :start $catSql$endSql");
$totalStmt->execute([':start' => $startDate] + ($endSql ? [':end' => $endDate] : []));
$totalCount = (int)$totalStmt->fetchColumn();

// New Period Count
$newStmt = $db->prepare("SELECT COUNT(*) FROM reports r WHERE r.created_at >= :start $catSql$endSql");
$newStmt->execute([':start' => $periodStart] + ($endSql ? [':end' => $endDate] : []));
$newCount = (int)$newStmt->fetchColumn();

// Category Breakdown (top category)
$topCategory = null;
$topStmt = $db->prepare("
    SELECT COALESCE(c.name, 'Uncategorized') AS category_name, COUNT(*) AS total
    FROM reports r
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE r.created_at >= :start $catSql$endSql
    GROUP BY c.id, c.name
    ORDER BY total DESC
    LIMIT 1
");
$topStmt->execute([':start' => $startDate] + ($endSql ? [':end' => $endDate] : []));
$topRow = $topStmt->fetch(PDO::FETCH_ASSOC);
if ($topRow) {
    $topCategory = $topRow;
    $topCategory['share'] = $totalCount > 0 ? round(($topRow['total'] / $totalCount) * 100, 1) : 0;
}

// ------------------------------------------------------------
// 3. REPORTER DEMOGRAPHICS (Resident vs Non-Resident)
// ------------------------------------------------------------
$demographics = ['resident' => 0, 'non_resident' => 0];
$demographicsAvailable = true;
try {
    $demStmt = $db->prepare("
        SELECT u.residency_status AS status_type, COUNT(*) AS total
        FROM reports r
        JOIN users u ON u.id = r.user_id
        WHERE r.created_at >= :start $catSql$endSql
        GROUP BY u.residency_status
    ");
    $demStmt->execute([':start' => $startDate] + ($endSql ? [':end' => $endDate] : []));
    foreach ($demStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (strtolower($row['status_type']) === 'resident') ? 'resident' : 'non_resident';
        $demographics[$key] += (int)$row['total'];
    }
} catch (Exception $e) {
    try {
        $demStmt = $db->prepare("
            SELECT u.is_resident AS status_type, COUNT(*) AS total
            FROM reports r
            JOIN users u ON u.id = r.user_id
            WHERE r.created_at >= :start $catSql$endSql
            GROUP BY u.is_resident
        ");
        $demStmt->execute([':start' => $startDate] + ($endSql ? [':end' => $endDate] : []));
        foreach ($demStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = ((int)$row['status_type'] === 1) ? 'resident' : 'non_resident';
            $demographics[$key] += (int)$row['total'];
        }
    } catch (Exception $e2) {
        $demographicsAvailable = false;
    }
}
$demographicsTotal = $demographics['resident'] + $demographics['non_resident'];
$residentPct    = $demographicsTotal > 0 ? round(($demographics['resident'] / $demographicsTotal) * 100, 1) : 0;
$nonResidentPct = $demographicsTotal > 0 ? round(($demographics['non_resident'] / $demographicsTotal) * 100, 1) : 0;

// ------------------------------------------------------------
// 4. DYNAMIC INSIGHTS (trend highlights)
// ------------------------------------------------------------
function reportCountForMonth($db, $ym, $catSql) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM reports r WHERE DATE_FORMAT(r.created_at, '%Y-%m') = :ym $catSql");
    $stmt->execute([':ym' => $ym]);
    return (int)$stmt->fetchColumn();
}
$curCount  = reportCountForMonth($db, date('Y-m'), $catSql);
$prevCount = reportCountForMonth($db, date('Y-m', strtotime('first day of last month')), $catSql);

$insights = [];
if ($totalCount === 0) {
    $insights[] = 'No reports were recorded within the selected period and category scope.';
} else {
    if ($prevCount > 0) {
        $momPct = round((($curCount - $prevCount) / $prevCount) * 100, 1);
        $dir = $momPct >= 0 ? 'increased' : 'decreased';
        $insights[] = "Report activity {$dir} by " . number_format(abs($momPct), 1) . "% this month compared to the previous month ({$prevCount} \u{2192} {$curCount} reports).";
    } else {
        $insights[] = "This month recorded {$curCount} report(s) \u{2014} the first recorded activity within the selected period.";
    }
    if ($topCategory) {
        $insights[] = "{$topCategory['category_name']} is the leading hazard category with {$topCategory['total']} report(s) ({$topCategory['share']}% of the period total).";
    }
    if ($demographicsAvailable && $demographicsTotal > 0) {
        $insights[] = "Reporter demographics are split {$demographics['resident']} resident(s) ({$residentPct}%) and {$demographics['non_resident']} non-resident(s) ({$nonResidentPct}%).";
    }
}

// ------------------------------------------------------------
// 5. ORGANIZATION / PDF EXPORT SETTINGS
// ------------------------------------------------------------
$lguLogo       = SettingsHelper::getLogoUrl();
$menroLogoPath = SettingsHelper::get('menro_logo', '');
$menroLogo     = $menroLogoPath ? BASE_URL . $menroLogoPath : '';
$systemName    = SettingsHelper::get('system_name', 'SIERRA');
$generatedBy   = $_SESSION['user_name'] ?? 'System Admin';
$generatedOn   = date('F j, Y \a\t h:i A');

// PDF Export settings (Settings > PDF Export)
$officeName    = SettingsHelper::get('pdf_office_name', 'Municipal Environment and Natural Resources Office');
$municipality  = SettingsHelper::get('pdf_municipality_name', 'Municipality of San Isidro');
$preparedBy    = SettingsHelper::get('pdf_prepared_by_name', '');
$preparedTitle = SettingsHelper::get('pdf_prepared_by_title', 'MENRO Data Analyst / Administrator');
$approvedBy    = SettingsHelper::get('pdf_approved_by_name', '');
$approvedTitle = SettingsHelper::get('pdf_approved_by_title', 'Municipal Environment and Natural Resources Officer');
$footerNote    = SettingsHelper::get('pdf_footer_note', 'System Generated via SIERRA (Web-Based Environmental Reporting Application) | Page 1 of 1');

// Determine if this is a barangay user
$isBarangay = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'barangay_official');
$barangayName = '';
if ($isBarangay && isset($_SESSION['barangay_id'])) {
    $barangayModel = new Barangay($db);
    $brgyInfo = $barangayModel->getById($_SESSION['barangay_id']);
    $barangayName = $brgyInfo['name'] ?? '';
}

// Header text based on role
if ($isBarangay) {
    $headerLine1 = 'Republic of the Philippines';
    $headerLine2 = 'Province of Nueva Ecija';
    $headerLine3 = $municipality;
    $headerLine4 = 'Barangay ' . htmlspecialchars($barangayName);
} else {
    $headerLine1 = 'Republic of the Philippines';
    $headerLine2 = $officeName;
    $headerLine3 = $municipality;
    $headerLine4 = 'San Isidro, Nueva Ecija';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ($lguLogo): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($lguLogo); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MENRO Environmental Hazard Report - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Manrope', Arial, sans-serif;
            background: #eef2f1;
            color: #1f2937;
            font-size: 12px;
        }

        /* ===== Screen-only toolbar ===== */
        .toolbar {
            max-width: 210mm;
            margin: 16px auto 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toolbar button {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .toolbar button:hover { box-shadow: 0 4px 12px rgba(16,163,127,0.3); }
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
        .toolbar .hint { color: #6b7280; font-size: 11px; }

        /* ===== Report page (A4 portrait) ===== */
        .report {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 12mm 14mm;
        }

        /* Official LGU header */
        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding-bottom: 10px;
            border-bottom: 3px solid #10A37F;
        }
        .logo-box {
            width: 24mm;
            height: 24mm;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .logo-box img { max-width: 24mm; max-height: 24mm; object-fit: contain; }
        .logo-placeholder {
            width: 24mm;
            height: 24mm;
            border: 1px dashed #d1d5db;
            border-radius: 6px;
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
        .org-contact { font-size: 10px; color: #6b7280; margin-top: 2px; }

        /* Title & metadata */
        .report-title-block { text-align: center; margin: 12px 0 14px; }
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

        /* KPI stat cards */
        .kpi-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .kpi-card {
            border: 1px solid #e5e7eb;
            border-top: 4px solid #10A37F;
            border-radius: 8px;
            padding: 10px 12px;
            background: #fafcfb;
        }
        .kpi-card .kpi-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi-card .kpi-value { font-size: 24px; font-weight: 800; color: #111827; margin-top: 2px; line-height: 1.1; }
        .kpi-card .kpi-sub { font-size: 10px; color: #6b7280; margin-top: 2px; }
        .kpi-blue   { border-top-color: #3B82F6; }
        .kpi-amber  { border-top-color: #F59E0B; }

        /* Charts */
        .charts-row { display: grid; grid-template-columns: 1.25fr 1fr; gap: 10px; margin-top: 10px; }
        .chart-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; }
        .chart-heading { font-size: 11px; font-weight: 700; color: #1f2937; margin-bottom: 6px; }
        .chart-wrap { height: 210px; position: relative; }

        /* Insights callout */
        .insight-box {
            margin-top: 10px;
            border: 1px solid #fde68a;
            border-left: 5px solid #F59E0B;
            background: #fffbeb;
            border-radius: 8px;
            padding: 10px 14px;
        }
        .insight-label { font-size: 10px; font-weight: 800; letter-spacing: 0.08em; color: #92400E; text-transform: uppercase; margin-bottom: 4px; }
        .insight-box ul { margin: 0; padding-left: 16px; }
        .insight-box li { font-size: 11px; color: #78350F; line-height: 1.55; }

        /* Signature block */
        .signature-block {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 34px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
        }
        .sig-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .sig-line { border-bottom: 1px solid #374151; margin-top: 30px; }
        .sig-name { font-size: 12px; font-weight: 700; color: #111827; margin-top: 4px; text-align: center; }
        .sig-title { font-size: 10px; color: #6b7280; text-align: center; }

        /* Content footer (screen + fallback for print engines without @page margin boxes) */
        .report-footer {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 22px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #6b7280;
        }
        .report-footer .brand { font-weight: 700; color: #0D8568; }
        .report-footer-note {
            margin-top: 6px;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
        }

        /* ===== DEDICATED A4 PRINT / EXPORT LAYOUT ===== */
        @page {
            size: A4 portrait;
            margin: 14mm 14mm 20mm;
        }
        @media print {
            body { background: #ffffff !important; }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .toolbar { display: none !important; }
            .report {
                width: 100%;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .kpi-card, .chart-card, .insight-box, .signature-block, .report-header, .report-title-block { break-inside: avoid; }
            .chart-wrap { height: 200px; }
        }
    </style>
    </head>
<body>
    <!-- Screen-only toolbar (hidden on print) -->
    <div class="toolbar">
        <button type="button" onclick="window.print()"><i class="fas fa-print" style="margin-right:6px;"></i>Print</button>
        <button type="button" onclick="window.print()"><i class="fas fa-file-pdf" style="margin-right:6px;"></i>Save as PDF</button>
        <a href="<?php echo BASE_URL; ?>index.php?page=dashboard">&larr; Back to Dashboard</a>
        <span class="hint">Tip: choose "Save as PDF" as the printer destination for an A4 PDF export.</span>
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
                <div class="org-line1"><?php echo $headerLine1; ?></div>
                <div class="org-name"><?php echo $headerLine2; ?></div>
                <div class="org-muni"><?php echo $headerLine3; ?></div>
                <div class="org-muni"><?php echo $headerLine4; ?></div>
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
            <div class="report-title">MENRO ENVIRONMENTAL HAZARD REPORT</div>
            <div class="report-subtitle"><?php echo htmlspecialchars($rangeLabel); ?> Decision Support Report &middot; <?php echo htmlspecialchars($municipality); ?></div>
            <div class="report-meta">
                <span><strong>Date Range:</strong> <?php echo date('M j, Y', strtotime($startDate)); ?> &ndash; <?php echo date('M j, Y', strtotime($endDate)); ?></span>
                <span><strong>Period:</strong> <?php echo htmlspecialchars($periodLabel); ?></span>
                <span><strong>Generated On:</strong> <?php echo htmlspecialchars($generatedOn); ?></span>
                <span><strong>Generated By:</strong> <?php echo htmlspecialchars($generatedBy); ?></span>
            </div>
        </div>

        <!-- ===== KPI Stat Cards ===== -->
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-label">Total Count</div>
                <div class="kpi-value"><?php echo number_format($totalCount); ?></div>
                <div class="kpi-sub">Reports in selected period</div>
            </div>
            <div class="kpi-card kpi-blue">
                <div class="kpi-label">New <?php echo htmlspecialchars($periodLabel); ?></div>
                <div class="kpi-value"><?php echo number_format($newCount); ?></div>
                <div class="kpi-sub"><?php echo htmlspecialchars($periodLabel); ?> new reports</div>
            </div>
            <div class="kpi-card kpi-amber">
                <div class="kpi-label">Category Breakdown</div>
                <div class="kpi-value" style="font-size:16px; padding-top:6px;"><?php echo $topCategory ? htmlspecialchars($topCategory['category_name']) : '&mdash;'; ?></div>
                <div class="kpi-sub"><?php echo $topCategory ? 'Top category &middot; ' . number_format($topCategory['total']) . ' report(s) &middot; ' . $topCategory['share'] . '% of total' : 'No category data'; ?></div>
            </div>
        </div>

        <!-- ===== Charts: Monthly Transactional + Demographics ===== -->
        <div class="charts-row">
            <div class="chart-card">
                <div class="chart-heading"><i class="fas fa-chart-bar" style="color:#10A37F; margin-right:6px;"></i>Transactional Report &mdash; Grouped by Month</div>
                <div class="chart-wrap"><canvas id="monthlyChart"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-heading"><i class="fas fa-chart-pie" style="color:#10A37F; margin-right:6px;"></i>Reporter Demographics</div>
                <div class="chart-wrap">
                    <?php if ($demographicsAvailable && $demographicsTotal > 0): ?>
                        <canvas id="demographicsChart"></canvas>
                    <?php else: ?>
                        <div style="height:100%; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:11px; text-align:center;">Demographic data not available.</div>
                    <?php endif; ?>
                </div>
                <?php if ($demographicsAvailable && $demographicsTotal > 0): ?>
                <div style="display:flex; justify-content:center; gap:16px; margin-top:6px; font-size:10px;">
                    <span><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#10A37F; margin-right:4px;"></span>Resident (<?php echo $residentPct; ?>%)</span>
                    <span><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#F59E0B; margin-right:4px;"></span>Non-Resident (<?php echo $nonResidentPct; ?>%)</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Dynamic Insights Block ===== -->
        <div class="insight-box">
            <div class="insight-label"><i class="fas fa-lightbulb" style="margin-right:6px;"></i>Insight</div>
            <ul>
                <?php foreach ($insights as $insight): ?>
                    <li><?php echo htmlspecialchars($insight); ?></li>
                <?php endforeach; ?>
            </ul>
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
            <span class="brand"><?php echo htmlspecialchars($systemName); ?> &middot; Web-Based Environmental Reporting System</span>
        </footer>
        <div class="report-footer-note"><?php echo htmlspecialchars($footerNote); ?></div>
    </div>

    <script>
        // ------------------------------------------------------------
        // CHARTS
        // ------------------------------------------------------------
        const monthlyLabels = <?php echo json_encode($monthLabels); ?>;
        const monthlyData = <?php echo json_encode($seriesData); ?>;

        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Reports',
                    data: monthlyData,
                    backgroundColor: '#10A37F',
                    borderRadius: 3,
                    maxBarThickness: 34
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e5e7eb' }, ticks: { precision: 0, font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });

        <?php if ($demographicsAvailable && $demographicsTotal > 0): ?>
        const demographicsData = <?php echo json_encode([$demographics['resident'], $demographics['non_resident']]); ?>;
        new Chart(document.getElementById('demographicsChart'), {
            type: 'doughnut',
            data: {
                labels: ['Resident', 'Non-Resident'],
                datasets: [{
                    data: demographicsData,
                    backgroundColor: ['#10A37F', '#F59E0B'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                cutout: '62%',
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
                responsive: true,
                maintainAspectRatio: false
            }
        });
        <?php endif; ?>

        // ------------------------------------------------------------
        // AUTO-PRINT (when launched via "Export as PDF")
        // ------------------------------------------------------------
        <?php if ($autoprint): ?>
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 700);
        });
        <?php endif; ?>
    </script>
</body>
</html>