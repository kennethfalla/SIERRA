<?php
// views/barangay/reports/dashboard_print.php - BARANGAY DASHBOARD PRINT/EXPORT
// Printable (A4 portrait) and CSV summary of EVERYTHING shown on the Barangay
// Dashboard, strictly scoped to the official's own barangay.
// Opened via:
//   index.php?page=barangay-dashboard-print[&format=csv][&autoprint=1]

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
// STATUS BREAKDOWN
// ============================================================
$total    = (int)$db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id")->fetchColumn();
$pending  = (int)$db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id AND status = 'pending'")->fetchColumn();
$in_progress = (int)$db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id AND status = 'in_progress'")->fetchColumn();
$resolved = (int)$db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id AND status = 'resolved'")->fetchColumn();
$rejected = (int)$db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id AND status = 'rejected'")->fetchColumn();
$escalated = (int)$db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id AND status IN ('escalated','escalated_pending')")->fetchColumn();
$resolution_rate = $total > 0 ? round(($resolved / $total) * 100) : 0;

// ============================================================
// KPI WIDGETS
// ============================================================
$criticalHotspots = (int)$db->query("
    SELECT COUNT(DISTINCT CONCAT(FLOOR(latitude / 0.00045), ',', FLOOR(longitude / 0.00045)))
    FROM reports
    WHERE barangay_id = $barangay_id AND risk_level = 'critical'
      AND status NOT IN ('resolved','rejected','cancelled')
      AND latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0
")->fetchColumn();

$avgResAllTime = (float)$db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM reports WHERE barangay_id = $barangay_id AND status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL")->fetchColumn();
$avgResThisMonth = (float)$db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM reports WHERE barangay_id = $barangay_id AND status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetchColumn();
$avgResLastMonth = (float)$db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM reports WHERE barangay_id = $barangay_id AND status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')")->fetchColumn();
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

$assignedThisMonth = (int)$db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id AND (DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') OR DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m'))")->fetchColumn();
$resolvedThisMonth = (int)$db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id AND status = 'resolved' AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetchColumn();
$resolutionRateThisMonth = $assignedThisMonth > 0 ? round(($resolvedThisMonth / $assignedThisMonth) * 100) : 0;

// ============================================================
// DEMOGRAPHICS
// ============================================================
$demographics = ['resident' => 0, 'non_resident' => 0];
try {
    $stmt = $db->prepare("SELECT u.is_resident, COUNT(*) AS total FROM reports r JOIN users u ON u.id = r.user_id WHERE r.barangay_id = ? GROUP BY u.is_resident");
    $stmt->execute([$barangay_id]);
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
$stmt = $db->prepare("SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS total FROM reports WHERE barangay_id = ? AND created_at IS NOT NULL GROUP BY DAYOFWEEK(created_at)");
$stmt->execute([$barangay_id]);
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
$stmt = $db->prepare("SELECT risk_level, COUNT(*) AS cnt FROM reports WHERE barangay_id = ? GROUP BY risk_level");
$stmt->execute([$barangay_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($riskData[$row['risk_level']])) $riskData[$row['risk_level']] = (int)$row['cnt'];
}
$riskTotal = array_sum($riskData);

// ============================================================
// CATEGORY DISTRIBUTION
// ============================================================
$categoryData = [];
$stmt = $db->prepare("SELECT c.name, COUNT(r.id) AS cnt FROM categories c LEFT JOIN reports r ON c.id = r.category_id AND r.barangay_id = ? GROUP BY c.id HAVING COUNT(r.id) > 0 ORDER BY cnt DESC");
$stmt->execute([$barangay_id]);
$categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categoryTotal = array_sum(array_column($categoryData, 'cnt'));

// ============================================================
// RECENT REPORTS
// ============================================================
$statusLabels = ['pending'=>'Pending','under_review'=>'Under Review','verified'=>'Verified','in_progress'=>'In Progress','escalated_pending'=>'Escalated Pending','escalated'=>'Escalated','resolved'=>'Resolved','rejected'=>'Rejected','cancelled'=>'Cancelled'];
$riskLabels = ['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'];

$recent = $db->prepare("
    SELECT r.id, r.title, r.risk_level, r.status, r.created_at, c.name AS category_name,
           CONCAT(u.first_name, ' ', u.last_name) AS reporter
    FROM reports r
    JOIN categories c ON r.category_id = c.id
    JOIN users u ON r.user_id = u.id
    WHERE r.barangay_id = ?
    ORDER BY r.created_at DESC
    LIMIT 20
");
$recent->execute([$barangay_id]);
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
// CSV EXPORT
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
    fputcsv($out, ['KPI SUMMARY']);
    fputcsv($out, ['Pending Acknowledgment', $pending]);
    fputcsv($out, ['Critical Local Hotspots', $criticalHotspots]);
    fputcsv($out, ['Avg Resolution Speed (All Time, Days)', $avgResAllDays]);
    fputcsv($out, ['Avg Resolution Speed (This Month, Days)', $avgResThisMonthDays]);
    fputcsv($out, ['Avg Resolution Speed (Last Month, Days)', $avgResLastMonthDays]);
    fputcsv($out, ['Resolution Rate (This Month, %)', $resolutionRateThisMonth]);
    fputcsv($out, []);
    fputcsv($out, ['STATUS BREAKDOWN']);
    fputcsv($out, ['Total', $total]);
    fputcsv($out, ['Pending', $pending]);
    fputcsv($out, ['In Progress', $in_progress]);
    fputcsv($out, ['Resolved', $resolved]);
    fputcsv($out, ['Rejected', $rejected]);
    fputcsv($out, ['Escalated', $escalated]);
    fputcsv($out, ['Resolution Rate (%)', $resolution_rate]);
    fputcsv($out, []);
    fputcsv($out, ['DEMOGRAPHICS']);
    fputcsv($out, ['Resident', $demographics['resident'], $residentPct . '%']);
    fputcsv($out, ['Non-Resident', $demographics['non_resident'], $nonResidentPct . '%']);
    fputcsv($out, []);
    fputcsv($out, ['PEAK REPORTING']);
    fputcsv($out, ['Peak Day', $peakDayLabel]);
    fputcsv($out, ['Peak Day Share (%)', $peakDayShare]);
    fputcsv($out, []);
    fputcsv($out, ['RISK DISTRIBUTION']);
    foreach ($riskData as $level => $cnt) fputcsv($out, [ucfirst($level), $cnt]);
    fputcsv($out, []);
    fputcsv($out, ['ISSUES BY CATEGORY']);
    foreach ($categoryData as $c) fputcsv($out, [$c['name'], $c['cnt']]);
    fputcsv($out, []);
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
    fclose($out);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ($lguLogo): ?><link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($lguLogo); ?>"><?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Dashboard Report - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Manrope', Arial, sans-serif; background: #eef2f1; color: #1f2937; font-size: 11px; }
        .toolbar { max-width: 100%; margin: 16px auto 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 0 12px; }
        .toolbar button { background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); color: #fff; border: none; border-radius: 8px; padding: 8px 16px; font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
        .toolbar a { color: #374151; font-size: 12px; font-weight: 600; text-decoration: none; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; }
        .report { width: 210mm; min-height: 297mm; margin: 0 auto; background: #ffffff; padding: 12mm 14mm; }
        .report-header { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding-bottom: 10px; border-bottom: 3px solid #10A37F; }
        .logo-box { width: 24mm; height: 24mm; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .logo-box img { max-width: 24mm; max-height: 24mm; object-fit: contain; }
        .logo-placeholder { width: 24mm; height: 24mm; border: 1px dashed #d1d5db; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 8px; text-align: center; }
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
        .section-heading { font-size: 12px; font-weight: 800; color: #0D8568; text-transform: uppercase; letter-spacing: 0.03em; padding: 6px 10px; background: #f4faf7; border: 1px solid #dff0e9; border-left: 5px solid #10A37F; border-radius: 6px; margin-bottom: 8px; }
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
        .signature-block { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 34px; padding-top: 12px; border-top: 1px solid #e5e7eb; }
        .sig-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .sig-line { border-bottom: 1px solid #374151; margin-top: 30px; }
        .sig-name { font-size: 12px; font-weight: 700; color: #111827; margin-top: 4px; text-align: center; }
        .sig-title { font-size: 10px; color: #6b7280; text-align: center; }
        .report-footer { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px; margin-top: 22px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #6b7280; }
        .report-footer .brand { font-weight: 700; color: #0D8568; }
        .report-footer-note { margin-top: 6px; text-align: center; font-size: 8px; color: #9ca3af; }
        @page { size: A4 portrait; margin: 14mm 14mm 20mm; }
        @media print {
            body { background: #ffffff !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .toolbar { display: none !important; }
            .report { width: 100%; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="<?php echo BASE_URL; ?>index.php?page=dashboard"><i class="fas fa-arrow-left" style="margin-right:6px;"></i>Back</a>
        <button type="button" onclick="window.print()"><i class="fas fa-print" style="margin-right:6px;"></i>Print</button>
        <button type="button" onclick="window.print()"><i class="fas fa-file-pdf" style="margin-right:6px;"></i>Save as PDF</button>
    </div>

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
            <div class="report-title">BARANGAY DASHBOARD REPORT</div>
            <div class="report-subtitle"><?php echo htmlspecialchars($barangay_name); ?> &middot; <?php echo htmlspecialchars($municipality); ?></div>
            <div class="report-meta">
                <span><strong>Generated On:</strong> <?php echo htmlspecialchars($generatedOn); ?></span>
                <span><strong>Generated By:</strong> <?php echo htmlspecialchars($generatedBy); ?></span>
            </div>
        </div>

        <!-- KPI -->
        <div class="section">
            <div class="section-heading">Local Health KPIs</div>
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

        <!-- Status Breakdown -->
        <div class="section">
            <div class="section-heading">Status Breakdown</div>
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

        <!-- Demographics + Peak -->
        <div class="section">
            <div class="section-heading">Reporter Insights</div>
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

        <!-- Risk + Category -->
        <div class="section">
            <div class="section-heading">Risk &amp; Category Distribution</div>
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

        <!-- Recent Reports -->
        <div class="section">
            <div class="section-heading">Recent Reports</div>
            <?php if (empty($recentReports)): ?>
            <p style="color:#9ca3af; font-size:10px; text-align:center; padding:12px 0;">No reports in this barangay yet.</p>
            <?php else: ?>
            <table class="data">
                <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Reporter</th><th>Risk</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($recentReports as $r): ?>
                    <tr>
                        <td style="font-family:monospace;">#<?php echo str_pad($r['id'], 5, '0', STR_PAD_LEFT); ?></td>
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

    <?php if ($autoprint): ?>
    <script>window.addEventListener('load', function() { setTimeout(function() { window.print(); }, 700); });</script>
    <?php endif; ?>
</body>
</html>
