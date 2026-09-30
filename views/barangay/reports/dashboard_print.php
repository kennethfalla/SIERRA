<?php
// views/barangay/reports/dashboard_print.php - BARANGAY DASHBOARD PRINT/EXPORT
// Printable (A4 portrait) and CSV summary of EVERYTHING shown on the Barangay
// Dashboard, strictly scoped to the official's own barangay. Includes a filter
// sidebar (sections + date range + status + risk) like the MENRO report.
// Opened via:
//   index.php?page=barangay-dashboard-print[&sections=..&date_from=..&date_to=..]
//             [&status=..&risk=..][&format=csv][&autoprint=1]

require_once dirname(__DIR__, 3) . '/config/config.php';
require_once BASE_PATH . '/helpers/SettingsHelper.php';
requireRole('barangay_official');

$database = new Database();
$db = $database->getConnection();

$barangay_id = (int)($_SESSION['barangay_id'] ?? 0);

$brgyStmt = $db->prepare("SELECT name FROM barangays WHERE id = ?");
$brgyStmt->execute([$barangay_id]);
$barangay_name = $brgyStmt->fetchColumn() ?: 'Your Barangay';

$format   = isset($_GET['format']) && $_GET['format'] === 'csv' ? 'csv' : 'html';
$autoprint = !empty($_GET['autoprint']);

// ============================================================
// FILTERS
// ============================================================
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$statusLabels = ['pending'=>'Pending','under_review'=>'Under Review','verified'=>'Verified','in_progress'=>'In Progress','escalated_pending'=>'Escalated Pending','escalated'=>'Escalated','resolved'=>'Resolved','rejected'=>'Rejected','cancelled'=>'Cancelled'];
$riskLabels = ['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'];
$status_filter = isset($_GET['status']) && isset($statusLabels[$_GET['status']]) ? $_GET['status'] : '';
$risk_filter   = isset($_GET['risk']) && isset($riskLabels[$_GET['risk']]) ? $_GET['risk'] : '';

// Section selection (which dashboard blocks to include)
$sectionLabels = [
    'kpi'          => 'Local Health KPIs',
    'status'       => 'Status Breakdown',
    'insights'     => 'Reporter Insights',
    'distribution' => 'Risk & Category Distribution',
    'recent'       => 'Recent Reports',
];
$sections = [];
$rawSections = $_GET['sections'] ?? null;
if (is_array($rawSections)) {
    foreach ($rawSections as $sec) { $sec = trim((string)$sec); if (isset($sectionLabels[$sec])) $sections[] = $sec; }
} elseif (is_string($rawSections) && $rawSections !== '') {
    foreach (explode(',', $rawSections) as $sec) { $sec = trim($sec); if (isset($sectionLabels[$sec])) $sections[] = $sec; }
}
if (empty($sections)) $sections = array_keys($sectionLabels);

// Shared WHERE fragments
$filterFrag  = '';
$filterFragR = '';
$filterParams = [];
if ($date_from !== '') { $filterFrag .= " AND DATE(created_at) >= :date_from"; $filterFragR .= " AND DATE(r.created_at) >= :date_from"; $filterParams[':date_from'] = $date_from; }
if ($date_to !== '')   { $filterFrag .= " AND DATE(created_at) <= :date_to";   $filterFragR .= " AND DATE(r.created_at) <= :date_to";   $filterParams[':date_to']   = $date_to; }
if ($status_filter !== '') { $filterFrag .= " AND status = :status"; $filterFragR .= " AND r.status = :status"; $filterParams[':status'] = $status_filter; }
if ($risk_filter !== '')   { $filterFrag .= " AND risk_level = :risk"; $filterFragR .= " AND r.risk_level = :risk"; $filterParams[':risk'] = $risk_filter; }

function execCount($db, $sql, $params) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}
function execValue($db, $sql, $params) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}
$baseParams = [':barangay_id' => $barangay_id];
$p = array_merge($baseParams, $filterParams);

// ============================================================
// STATUS BREAKDOWN
// ============================================================
$total       = execCount($db, "SELECT COUNT(*) FROM reports WHERE barangay_id = :barangay_id $filterFrag", $p);
$pending     = execCount($db, "SELECT COUNT(*) FROM reports WHERE barangay_id = :barangay_id $filterFrag AND status = 'pending'", $p);
$in_progress = execCount($db, "SELECT COUNT(*) FROM reports WHERE barangay_id = :barangay_id $filterFrag AND status = 'in_progress'", $p);
$resolved    = execCount($db, "SELECT COUNT(*) FROM reports WHERE barangay_id = :barangay_id $filterFrag AND status = 'resolved'", $p);
$rejected    = execCount($db, "SELECT COUNT(*) FROM reports WHERE barangay_id = :barangay_id $filterFrag AND status = 'rejected'", $p);
$escalated   = execCount($db, "SELECT COUNT(*) FROM reports WHERE barangay_id = :barangay_id $filterFrag AND status IN ('escalated','escalated_pending')", $p);
$resolution_rate = $total > 0 ? round(($resolved / $total) * 100) : 0;

// ============================================================
// KPI WIDGETS
// ============================================================
$criticalHotspots = execCount($db, "
    SELECT COUNT(DISTINCT CONCAT(FLOOR(latitude / 0.00045), ',', FLOOR(longitude / 0.00045)))
    FROM reports
    WHERE barangay_id = :barangay_id $filterFrag AND risk_level = 'critical'
      AND status NOT IN ('resolved','rejected','cancelled')
      AND latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0
", $p);

$avgResAllTime   = (float)execValue($db, "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM reports WHERE barangay_id = :barangay_id $filterFrag AND status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL", $p);
$avgResThisMonth = (float)execValue($db, "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM reports WHERE barangay_id = :barangay_id $filterFrag AND status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')", $p);
$avgResLastMonth = (float)execValue($db, "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM reports WHERE barangay_id = :barangay_id $filterFrag AND status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')", $p);
$avgResAllDays = round($avgResAllTime / 24, 1);
$avgResThisMonthDays = round($avgResThisMonth / 24, 1);
$avgResLastMonthDays = round($avgResLastMonth / 24, 1);
$speedTrend = 'Stable';
$speedDelta = 0;
if ($avgResLastMonthDays > 0) {
    $speedDelta = round($avgResThisMonthDays - $avgResLastMonthDays, 1);
    if ($speedDelta > 0.5) $speedTrend = 'Slower vs last month';
    elseif ($speedDelta < -0.5) $speedTrend = 'Faster vs last month';
}

$assignedThisMonth = execCount($db, "SELECT COUNT(*) FROM reports WHERE barangay_id = :barangay_id $filterFrag AND (DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') OR DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m'))", $p);
$resolvedThisMonth = execCount($db, "SELECT COUNT(*) FROM reports WHERE barangay_id = :barangay_id $filterFrag AND status = 'resolved' AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')", $p);
$resolutionRateThisMonth = $assignedThisMonth > 0 ? round(($resolvedThisMonth / $assignedThisMonth) * 100) : 0;

// ============================================================
// DEMOGRAPHICS
// ============================================================
$demographics = ['resident' => 0, 'non_resident' => 0];
try {
    $stmt = $db->prepare("SELECT u.is_resident, COUNT(*) AS total FROM reports r JOIN users u ON u.id = r.user_id WHERE r.barangay_id = :barangay_id $filterFragR GROUP BY u.is_resident");
    $stmt->execute($p);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((int)$row['is_resident'] === 1) $demographics['resident'] = (int)$row['total'];
        else $demographics['non_resident'] = (int)$row['total'];
    }
} catch (Exception $e) {}
$demographicsTotal = $demographics['resident'] + $demographics['non_resident'];
$residentPct = $demographicsTotal > 0 ? round(($demographics['resident'] / $demographicsTotal) * 100, 1) : 0;
$nonResidentPct = $demographicsTotal > 0 ? round(($demographics['non_resident'] / $demographicsTotal) * 100, 1) : 0;

// ============================================================
// PEAK REPORTING DAY
// ============================================================
$dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$dayCounts = array_fill(0, 7, 0);
$stmt = $db->prepare("SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS total FROM reports WHERE barangay_id = :barangay_id $filterFrag AND created_at IS NOT NULL GROUP BY DAYOFWEEK(created_at)");
$stmt->execute($p);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $idx = (int)$row['dow'] - 1;
    if ($idx >= 0 && $idx < 7) $dayCounts[$idx] = (int)$row['total'];
}
$dayGrandTotal = array_sum($dayCounts);
$peakDayTotal = max($dayCounts);
$peakDayIndex = $peakDayTotal > 0 ? array_search($peakDayTotal, $dayCounts) : false;
$peakDayLabel = ($peakDayTotal > 0 && $peakDayIndex !== false) ? $dayLabels[$peakDayIndex] : 'N/A';
$peakDayShare = $dayGrandTotal > 0 ? round(($peakDayTotal / $dayGrandTotal) * 100, 1) : 0;

// ============================================================
// RISK DISTRIBUTION
// ============================================================
$riskData = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
$stmt = $db->prepare("SELECT risk_level, COUNT(*) AS cnt FROM reports WHERE barangay_id = :barangay_id $filterFrag GROUP BY risk_level");
$stmt->execute($p);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($riskData[$row['risk_level']])) $riskData[$row['risk_level']] = (int)$row['cnt'];
}
$riskTotal = array_sum($riskData);

// ============================================================
// CATEGORY DISTRIBUTION
// ============================================================
$categoryData = [];
$stmt = $db->prepare("SELECT c.name, COUNT(r.id) AS cnt FROM categories c LEFT JOIN reports r ON c.id = r.category_id AND r.barangay_id = :barangay_id $filterFragR GROUP BY c.id HAVING COUNT(r.id) > 0 ORDER BY cnt DESC");
$stmt->execute($p);
$categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categoryTotal = array_sum(array_column($categoryData, 'cnt'));

// ============================================================
// RECENT REPORTS
// ============================================================
$recent = $db->prepare("
    SELECT r.id, r.title, r.risk_level, r.status, r.created_at, c.name AS category_name,
           CONCAT(u.first_name, ' ', u.last_name) AS reporter
    FROM reports r
    JOIN categories c ON r.category_id = c.id
    JOIN users u ON r.user_id = u.id
    WHERE r.barangay_id = :barangay_id $filterFragR
    ORDER BY r.created_at DESC
    LIMIT 20
");
$recent->execute($p);
$recentReports = $recent->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// PDF EXPORT CONFIG
// ============================================================
$pdf = getPdfExportConfig($db);
$lguLogo       = $pdf['lgu_logo'];
$rightLogo     = $pdf['right_logo'];
$systemName    = $pdf['system_name'];
$municipality  = $pdf['municipality'];
$preparedBy    = $pdf['prepared_by'];
$preparedTitle = $pdf['prepared_title'];
$approvedBy    = $pdf['approved_by'];
$approvedTitle = $pdf['approved_title'];
$footerNote    = $pdf['footer_note'];
$generatedBy   = $pdf['generated_by'];
$generatedOn   = $pdf['generated_on'];
$headerLines   = $pdf['header_lines'];

// ============================================================
// CSV EXPORT (respects sections + filters)
// ============================================================
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="barangay_dashboard_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['BARANGAY DASHBOARD REPORT']);
    fputcsv($out, ['Barangay', $barangay_name]);
    fputcsv($out, ['Generated On', $generatedOn]);
    fputcsv($out, ['Generated By', $generatedBy]);
    fputcsv($out, []);

    if (in_array('kpi', $sections, true)) {
        fputcsv($out, ['LOCAL HEALTH KPIs']);
        fputcsv($out, ['Pending Acknowledgment', $pending]);
        fputcsv($out, ['Critical Local Hotspots', $criticalHotspots]);
        fputcsv($out, ['Avg Resolution Speed (All Time, Days)', $avgResAllDays]);
        fputcsv($out, ['Avg Resolution Speed (This Month, Days)', $avgResThisMonthDays]);
        fputcsv($out, ['Avg Resolution Speed (Last Month, Days)', $avgResLastMonthDays]);
        fputcsv($out, ['Resolution Rate (This Month, %)', $resolutionRateThisMonth]);
        fputcsv($out, []);
    }
    if (in_array('status', $sections, true)) {
        fputcsv($out, ['STATUS BREAKDOWN']);
        fputcsv($out, ['Total', $total]);
        fputcsv($out, ['Pending', $pending]);
        fputcsv($out, ['In Progress', $in_progress]);
        fputcsv($out, ['Resolved', $resolved]);
        fputcsv($out, ['Rejected', $rejected]);
        fputcsv($out, ['Escalated', $escalated]);
        fputcsv($out, ['Resolution Rate (%)', $resolution_rate]);
        fputcsv($out, []);
    }
    if (in_array('insights', $sections, true)) {
        fputcsv($out, ['REPORTER INSIGHTS']);
        fputcsv($out, ['Resident', $demographics['resident'], $residentPct . '%']);
        fputcsv($out, ['Non-Resident', $demographics['non_resident'], $nonResidentPct . '%']);
        fputcsv($out, ['Peak Day', $peakDayLabel]);
        fputcsv($out, ['Peak Day Share (%)', $peakDayShare]);
        fputcsv($out, []);
    }
    if (in_array('distribution', $sections, true)) {
        fputcsv($out, ['RISK DISTRIBUTION']);
        foreach ($riskData as $level => $cnt) fputcsv($out, [ucfirst($level), $cnt]);
        fputcsv($out, []);
        fputcsv($out, ['ISSUES BY CATEGORY']);
        foreach ($categoryData as $c) fputcsv($out, [$c['name'], $c['cnt']]);
        fputcsv($out, []);
    }
    if (in_array('recent', $sections, true)) {
        fputcsv($out, ['RECENT REPORTS']);
        fputcsv($out, ['ID', 'Title', 'Category', 'Reporter', 'Risk', 'Status', 'Date']);
        foreach ($recentReports as $r) {
            fputcsv($out, [
                '#' . str_pad($r['id'], 5, '0', STR_PAD_LEFT),
                $r['title'], $r['category_name'], $r['reporter'],
                $riskLabels[$r['risk_level']] ?? $r['risk_level'],
                $statusLabels[$r['status']] ?? $r['status'],
                date('M d, Y', strtotime($r['created_at']))
            ]);
        }
    }
    fclose($out);
    exit();
}

// Filter summary chips
$filterChips = [];
if ($date_from || $date_to) {
    $fr = $date_from ? date('M j, Y', strtotime($date_from)) : '&hellip;';
    $t  = $date_to ? date('M j, Y', strtotime($date_to)) : '&hellip;';
    $filterChips[] = 'Date: ' . $fr . ' &ndash; ' . $t;
}
if ($status_filter !== '') $filterChips[] = 'Status: ' . $statusLabels[$status_filter];
if ($risk_filter !== '') $filterChips[] = 'Risk: ' . $riskLabels[$risk_filter];

// Report title reflects the applied filters — period + status/risk scope.
$dashPeriodDesc = '';
if ($date_from !== '' || $date_to !== '') {
    if ($date_from === date('Y-m-d') && $date_to === date('Y-m-d'))                          $dashPeriodDesc = 'Today';
    elseif ($date_from === date('Y-m-d', strtotime('-6 days')) && $date_to === date('Y-m-d')) $dashPeriodDesc = 'This Week';
    elseif ($date_from === date('Y-m-01') && $date_to === date('Y-m-d'))                     $dashPeriodDesc = 'This Month';
    elseif ($date_from === date('Y-01-01') && $date_to === date('Y-m-d'))                    $dashPeriodDesc = 'This Year';
    else {
        $dfs = $date_from ? date('M j, Y', strtotime($date_from)) : '&hellip;';
        $dts = $date_to   ? date('M j, Y', strtotime($date_to))   : '&hellip;';
        $dashPeriodDesc = $dfs . ' &ndash; ' . $dts;
    }
}
$dashScopeParts = [];
if ($status_filter !== '') $dashScopeParts[] = $statusLabels[$status_filter];
if ($risk_filter !== '')   $dashScopeParts[] = $riskLabels[$risk_filter] . ' Risk';
$reportTitle = 'BARANGAY DASHBOARD REPORT';
if (!empty($dashScopeParts)) $reportTitle .= ' - ' . implode(' - ', $dashScopeParts);
if ($dashPeriodDesc !== '')  $reportTitle .= ' - ' . $dashPeriodDesc;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ($lguLogo): ?><link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($lguLogo); ?>"><?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Dashboard Report - Sierra</title>
    <link href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/material-symbols.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/material-symbols.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Manrope', Arial, sans-serif; background: #eef2f1; color: #1f2937; font-size: 11px; }
        .toolbar { max-width: 100%; margin: 16px auto 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 0 12px; }
        .toolbar button { background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); color: #fff; border: none; border-radius: 8px; padding: 8px 16px; font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
        .toolbar a { color: #374151; font-size: 12px; font-weight: 600; text-decoration: none; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; }
        .report { width: 210mm; min-height: 297mm; margin: 0 auto; background: #ffffff; padding: 12mm 14mm; display: flex; flex-direction: column; }
        .report-header { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding-bottom: 10px; border-bottom: 3px solid #10A37F; }
        .logo-box { width: 24mm; height: 24mm; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .logo-box img { max-width: 24mm; max-height: 24mm; object-fit: contain; }
        .logo-placeholder { width: 24mm; height: 24mm; border: 1px dashed #d1d5db; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 8px; text-align: center; }
        .org-block { flex: 1; text-align: center; padding: 0 6px; }
        .org-line1 { font-size: 10px; letter-spacing: 0.12em; color: #4b5563; text-transform: uppercase; }
        .org-name { font-size: 15px; font-weight: 800; color: #111827; margin-top: 2px; line-height: 1.25; }
        .org-muni { font-size: 11px; color: #374151; margin-top: 2px; font-weight: 600; }
        .report-title-block { text-align: center; margin: 12px 0 14px; }
        .report-title { font-size: 19px; font-weight: 800; letter-spacing: 0.02em; color: #0D8568; }
        .report-subtitle { font-size: 11px; color: #4b5563; margin-top: 3px; font-weight: 600; }
        .report-meta { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-top: 8px; font-size: 10px; color: #6b7280; }
        .report-meta span { background: #f4faf7; border: 1px solid #dff0e9; border-radius: 999px; padding: 3px 10px; }
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 12px; }
        .kpi-card { border: 1px solid #e5e7eb; border-top: 4px solid #10A37F; border-radius: 8px; padding: 10px 12px; background: #fafcfb; text-align: center; }
        .kpi-card .kpi-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpi-card .kpi-value { font-size: 22px; font-weight: 800; color: #111827; margin-top: 2px; line-height: 1.1; }
        .kpi-card .kpi-sub { font-size: 8.5px; color: #9ca3af; margin-top: 3px; }
        .kpi-red { border-top-color: #EF4444; }
        .kpi-amber { border-top-color: #F59E0B; }
        .kpi-blue { border-top-color: #3B82F6; }
        .kpi-green { border-top-color: #10B981; }
        .section { margin-top: 12px; break-inside: avoid; }
        .section-heading { font-size: 12px; font-weight: 800; color: #0D8568; text-transform: uppercase; letter-spacing: 0.03em; padding: 6px 10px; background: #f4faf7; border: 1px solid #dff0e9; border-left: 5px solid #10A37F; border-radius: 8px; margin-bottom: 8px; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #f4faf7; color: #374151; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; text-align: left; padding: 5px 7px; border: 1px solid #dff0e9; }
        table.data td { padding: 4px 7px; border: 1px solid #e5e7eb; font-size: 10px; vertical-align: top; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: 8px; font-weight: 700; }
        .badge-low { background: #d1fae5; color: #065f46; }
        .badge-medium { background: #fef3c7; color: #92400e; }
        .badge-high { background: #ffedd5; color: #9a3412; }
        .badge-critical { background: #fee2e2; color: #991b1b; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-under_review { background: #dbeafe; color: #1e40af; }
        .badge-in_progress { background: #fce7f3; color: #db2777; }
        .badge-escalated_pending, .badge-escalated { background: #fed7aa; color: #9a3412; }
        .badge-resolved { background: #d1fae5; color: #10a37f; }
        .badge-rejected { background: #fee2e2; color: #dc2626; }
        .signature-block { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: auto; padding-top: 12px; border-top: 1px solid #e5e7eb; }
        .sig-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .sig-line { border-bottom: 1px solid #374151; margin-top: 30px; }
        .sig-name { font-size: 12px; font-weight: 700; color: #111827; margin-top: 4px; text-align: center; }
        .sig-title { font-size: 10px; color: #6b7280; text-align: center; }
        .report-footer { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px; margin-top: 22px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #6b7280; }
        .report-footer .brand { font-weight: 700; color: #0D8568; }
        .report-footer-note { margin-top: 6px; text-align: center; font-size: 8px; color: #9ca3af; }
        /* ===== Screen-only filter sidebar ===== */
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
        .filter-field select, .filter-field input { width: 100%; padding: 9px 10px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 12.5px; font-family: inherit; background: #fff; color: #1f2937; }
        .filter-field select:focus, .filter-field input:focus { border-color: #10A37F; outline: none; box-shadow: 0 0 0 3px rgba(16,163,127,0.12); }
        .section-check-list { display: flex; flex-direction: column; gap: 2px; }
        .section-check { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; cursor: pointer; font-size: 12px; color: #374151; font-weight: 500; }
        .section-check:hover { background: #F0FBF6; }
        .section-check input { accent-color: #10A37F; width: 15px; height: 15px; cursor: pointer; }
        .sidebar-footer { padding: 14px 16px; border-top: 1px solid #f3f4f6; background: #fff; display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }
        .btn-apply { width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; }
        .btn-reset { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 12px; border: 1.5px solid #e5e7eb; border-radius: 8px; background: #fff; color: #6b7280; font-size: 12px; font-weight: 600; text-decoration: none; }
        .btn-reset:hover { color: #EF4444; border-color: #EF4444; background: #FEF2F2; }
        .page-main { flex: 1; min-width: 0; padding: 16px; }
        .filter-summary-bar { max-width: 210mm; margin: 0 auto 10px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; background: #f4faf7; border: 1px solid #dff0e9; border-radius: 8px; padding: 6px 12px; }
        .filter-summary-bar .fs-label { font-size: 10px; font-weight: 700; color: #0D8568; text-transform: uppercase; letter-spacing: 0.05em; }
        .filter-summary-bar .fs-chip { background: #d1fae5; color: #065f46; border-radius: 999px; padding: 2px 10px; font-size: 10px; font-weight: 600; }
        @media (max-width: 900px) { .page-wrap { flex-direction: column; } .filter-sidebar { width: 100%; position: static; height: auto; border-right: none; border-bottom: 1px solid rgba(16,163,127,0.12); } }
        @page { size: A4 portrait; margin: 14mm 14mm 20mm; }
        @media print {
            body { background: #ffffff !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .filter-sidebar { display: none !important; }
            .page-wrap { display: block; padding: 0; }
            .page-main { padding: 0; }
            .toolbar { display: none !important; }
            .filter-summary-bar { display: none !important; }
            .report { width: 100%; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
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
            <form class="sidebar-form" method="get" action="<?php echo BASE_URL; ?>index.php" onsubmit="return validateSections()">
                <input type="hidden" name="page" value="barangay-dashboard-print">
                <div class="sidebar-body">
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Sections to include</div>
                        <label class="section-check">
                            <input type="checkbox" id="sectionAll" <?php echo count($sections) === count($sectionLabels) ? 'checked' : ''; ?>>
                            <strong>Select All</strong>
                        </label>
                        <div class="section-check-list">
                            <?php foreach ($sectionLabels as $secId => $secLabel): ?>
                            <label class="section-check">
                                <input type="checkbox" class="section-cb" name="sections[]" value="<?php echo htmlspecialchars($secId); ?>" <?php echo in_array($secId, $sections, true) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($secLabel); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Date</div>
                        <div class="quick-range">
                            <a href="#" onclick="setQuickReportRange('today'); return false;">Today</a>
                            <a href="#" onclick="setQuickReportRange('week'); return false;">This Week</a>
                            <a href="#" onclick="setQuickReportRange('month'); return false;">This Month</a>
                            <a href="#" onclick="setQuickReportRange('year'); return false;">This Year</a>
                        </div>
                        <div class="filter-field">
                            <label for="sideFrom">Date From</label>
                            <input type="date" name="date_from" id="sideFrom" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="sideTo">Date To</label>
                            <input type="date" name="date_to" id="sideTo" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                    </div>
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Filters</div>
                        <div class="filter-field">
                            <label for="sideStatus">Status</label>
                            <select name="status" id="sideStatus">
                                <option value="">All Statuses</option>
                                <?php foreach ($statusLabels as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $status_filter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sideRisk">Risk</label>
                            <select name="risk" id="sideRisk">
                                <option value="">All Levels</option>
                                <?php foreach ($riskLabels as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $risk_filter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="sidebar-footer">
                    <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="<?php echo BASE_URL; ?>index.php?page=barangay-dashboard-print" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </aside>

        <div class="page-main">
            <div class="toolbar">
                <a href="<?php echo BASE_URL; ?>index.php?page=dashboard"><i class="fas fa-arrow-left" style="margin-right:6px;"></i>Back</a>
                <button type="button" onclick="window.print()"><i class="fas fa-print" style="margin-right:6px;"></i>Print</button>
                <button type="button" onclick="window.print()"><i class="fas fa-file-pdf" style="margin-right:6px;"></i>Save as PDF</button>
            </div>

            <?php if (!empty($filterChips)): ?>
            <div class="filter-summary-bar">
                <span class="fs-label"><i class="fas fa-filter" style="margin-right:4px;"></i>Active Filters:</span>
                <?php foreach ($filterChips as $chip): ?><span class="fs-chip"><?php echo $chip; ?></span><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="report">
                <header class="report-header">
                    <div class="logo-box">
                        <?php if ($lguLogo): ?><img src="<?php echo htmlspecialchars($lguLogo); ?>" alt="LGU Logo"><?php else: ?><div class="logo-placeholder">LGU<br>Logo</div><?php endif; ?>
                    </div>
                    <div class="org-block">
                        <div class="org-line1"><?php echo htmlspecialchars($headerLines[0]); ?></div>
                        <div class="org-name"><?php echo htmlspecialchars($headerLines[1]); ?></div>
                        <div class="org-muni"><?php echo htmlspecialchars($headerLines[2]); ?></div>
                        <div class="org-muni"><?php echo htmlspecialchars($headerLines[3]); ?></div>
                    </div>
                    <div class="logo-box">
                        <?php if ($rightLogo): ?><img src="<?php echo htmlspecialchars($rightLogo); ?>" alt="Logo"><?php else: ?><div class="logo-placeholder">Barangay<br>Logo</div><?php endif; ?>
                    </div>
                </header>

                <div class="report-title-block">
                    <div class="report-title"><?php echo htmlspecialchars($reportTitle); ?></div>
                    <div class="report-subtitle"><?php echo htmlspecialchars($barangay_name); ?> &middot; <?php echo htmlspecialchars($municipality); ?></div>
                    <div class="report-meta">
                        <span><strong>Generated On:</strong> <?php echo htmlspecialchars($generatedOn); ?></span>
                        <span><strong>Generated By:</strong> <?php echo htmlspecialchars($generatedBy); ?></span>
                    </div>
                </div>

                <?php if (in_array('kpi', $sections, true)): ?>
                <div class="section">
                    <div class="section-heading"><?php echo htmlspecialchars($sectionLabels['kpi']); ?></div>
                    <div class="kpi-row">
                        <div class="kpi-card kpi-red">
                            <div class="kpi-label">Pending Acknowledgment</div>
                            <div class="kpi-value" style="color:#dc2626;"><?php echo $pending; ?></div>
                            <div class="kpi-sub">Awaiting review</div>
                        </div>
                        <div class="kpi-card kpi-amber">
                            <div class="kpi-label">Critical Local Hotspots</div>
                            <div class="kpi-value" style="color:#d97706;"><?php echo $criticalHotspots; ?></div>
                            <div class="kpi-sub">Clusters scoring 16+</div>
                        </div>
                        <div class="kpi-card kpi-blue">
                            <div class="kpi-label">Avg Resolution Speed</div>
                            <div class="kpi-value" style="color:#2563eb;"><?php echo $avgResAllDays; ?><span style="font-size:13px;">d</span></div>
                            <div class="kpi-sub"><?php echo htmlspecialchars($speedTrend); ?></div>
                        </div>
                        <div class="kpi-card kpi-green">
                            <div class="kpi-label">Resolution Rate (This Month)</div>
                            <div class="kpi-value" style="color:#059669;"><?php echo $resolutionRateThisMonth; ?>%</div>
                            <div class="kpi-sub"><?php echo $resolvedThisMonth; ?> of <?php echo $assignedThisMonth; ?> assigned</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('status', $sections, true)): ?>
                <div class="section">
                    <div class="section-heading"><?php echo htmlspecialchars($sectionLabels['status']); ?></div>
                    <table class="data">
                        <thead><tr><th>Total</th><th>Pending</th><th>In Progress</th><th>Resolved</th><th>Rejected</th><th>Escalated</th><th>Resolution Rate</th></tr></thead>
                        <tbody><tr>
                            <td><?php echo $total; ?></td>
                            <td><?php echo $pending; ?></td>
                            <td><?php echo $in_progress; ?></td>
                            <td><?php echo $resolved; ?></td>
                            <td><?php echo $rejected; ?></td>
                            <td><?php echo $escalated; ?></td>
                            <td><?php echo $resolution_rate; ?>%</td>
                        </tr></tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if (in_array('insights', $sections, true)): ?>
                <div class="section">
                    <div class="section-heading"><?php echo htmlspecialchars($sectionLabels['insights']); ?></div>
                    <div class="two-col">
                        <table class="data">
                            <thead><tr><th colspan="2">Demographics</th></tr></thead>
                            <tbody>
                                <tr><td>Resident</td><td><?php echo $demographics['resident']; ?> (<?php echo $residentPct; ?>%)</td></tr>
                                <tr><td>Non-Resident</td><td><?php echo $demographics['non_resident']; ?> (<?php echo $nonResidentPct; ?>%)</td></tr>
                            </tbody>
                        </table>
                        <table class="data">
                            <thead><tr><th colspan="2">Peak Reporting</th></tr></thead>
                            <tbody>
                                <tr><td>Peak Day</td><td><?php echo htmlspecialchars($peakDayLabel); ?></td></tr>
                                <tr><td>Share of Reports</td><td><?php echo $peakDayShare; ?>%</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('distribution', $sections, true)): ?>
                <div class="section">
                    <div class="section-heading"><?php echo htmlspecialchars($sectionLabels['distribution']); ?></div>
                    <div class="two-col">
                        <table class="data">
                            <thead><tr><th>Risk Level</th><th>Count</th><th>Share</th></tr></thead>
                            <tbody>
                                <?php foreach (['critical'=>'Critical','high'=>'High','medium'=>'Medium','low'=>'Low'] as $level => $label): ?>
                                <tr>
                                    <td><?php echo $label; ?></td>
                                    <td><?php echo $riskData[$level]; ?></td>
                                    <td><?php echo $riskTotal > 0 ? round(($riskData[$level] / $riskTotal) * 100, 1) . '%' : '0%'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <table class="data">
                            <thead><tr><th>Category</th><th>Count</th></tr></thead>
                            <tbody>
                                <?php if (empty($categoryData)): ?>
                                <tr><td colspan="2">No category data</td></tr>
                                <?php else: foreach ($categoryData as $c): ?>
                                <tr><td><?php echo htmlspecialchars($c['name']); ?></td><td><?php echo (int)$c['cnt']; ?></td></tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array('recent', $sections, true)): ?>
                <div class="section">
                    <div class="section-heading"><?php echo htmlspecialchars($sectionLabels['recent']); ?></div>
                    <?php if (empty($recentReports)): ?>
                    <p style="color:#9ca3af; font-size:10px; text-align:center; padding:12px 0;">No reports match the selected filters.</p>
                    <?php else: ?>
                    <table class="data">
                        <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Reporter</th><th>Risk</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentReports as $r): ?>
                            <tr>
                                <td>#<?php echo str_pad($r['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td style="font-weight:600;color:#111827;"><?php echo htmlspecialchars($r['title']); ?></td>
                                <td><?php echo htmlspecialchars($r['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($r['reporter']); ?></td>
                                <td><span class="badge badge-<?php echo $r['risk_level']; ?>"><?php echo $riskLabels[$r['risk_level']] ?? ucfirst($r['risk_level']); ?></span></td>
                                <td><span class="badge badge-<?php echo $r['status']; ?>"><?php echo $statusLabels[$r['status']] ?? ucfirst(str_replace('_', ' ', $r['status'])); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="signature-block">
                    <div>
                        <div class="sig-label">Prepared by:</div>
                        <div class="sig-line"></div>
                        <div class="sig-name"><?php echo htmlspecialchars($preparedBy); ?></div>
                        <div class="sig-title"><?php echo htmlspecialchars($preparedTitle); ?></div>
                    </div>
                    <div>
                        <div class="sig-label">Noted and Approved by:</div>
                        <div class="sig-line"></div>
                        <div class="sig-name"><?php echo htmlspecialchars($approvedBy ?: '____________________'); ?></div>
                        <div class="sig-title"><?php echo htmlspecialchars($approvedTitle); ?></div>
                    </div>
                </div>

                <footer class="report-footer">
                    <span>Date Printed: <?php echo date('F j, Y'); ?></span>
                    <span>Time Printed: <?php echo date('h:i A'); ?></span>
                    <span class="brand"><?php echo htmlspecialchars($systemName); ?> &middot; Web-Based Environmental Reporting System</span>
                </footer>
                <div class="report-footer-note"><?php echo htmlspecialchars($footerNote); ?></div>
            </div>
        </div>
    </div>

    <script>
        const sectionAll = document.getElementById('sectionAll');
        const sectionCbs = document.querySelectorAll('.section-cb');
        function syncSelectAll() { if (sectionAll) sectionAll.checked = Array.from(sectionCbs).every(cb => cb.checked); }
        if (sectionAll) {
            sectionAll.addEventListener('change', function() { sectionCbs.forEach(cb => { cb.checked = this.checked; }); });
            sectionCbs.forEach(cb => cb.addEventListener('change', syncSelectAll));
        }
        window.validateSections = function() {
            const any = Array.from(sectionCbs).some(cb => cb.checked);
            if (!any) { window.GB.alert({ type: 'error', title: 'Nothing selected', message: 'Please select at least one section to include.' }); return false; }
            return true;
        };
    </script>

    <script>
        function setQuickReportRange(range) {
            var f = document.getElementById('sideFrom');
            var t = document.getElementById('sideTo');
            if (!f || !t) return;
            var r = document.getElementById('sideDateRange');
            if (r) r.value = '0';
            var today = new Date();
            var ymd = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
            var from = today, to = today;
            if (range === 'week') { from = new Date(today); from.setDate(today.getDate() - 6); }
            else if (range === 'month') { from = new Date(today.getFullYear(), today.getMonth(), 1); }
            else if (range === 'year') { from = new Date(today.getFullYear(), 0, 1); }
            f.value = ymd(from);
            t.value = ymd(to);
            var form = f.closest('form');
            if (form) { if (form.requestSubmit) form.requestSubmit(); else form.submit(); }
        }
    </script>

    <?php if ($autoprint): ?>
    <script>window.addEventListener('load', function() { setTimeout(function() { window.print(); }, 700); });</script>
    <?php endif; ?>
    <?php include BASE_PATH . 'views/shared/global_modals.php'; ?>
<script src="<?php echo BASE_URL; ?>assets/js/fetch-timeout.js"></script>
</body>
</html>
