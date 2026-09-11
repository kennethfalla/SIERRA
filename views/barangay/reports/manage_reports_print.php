<?php
// views/barangay/reports/manage_reports_print.php - BARANGAY MANAGE REPORTS EXPORT
// Printable/CSV list of this barangay's reports with filters (status, category,
// risk, date range, reporter residency, search). Opened via:
//   index.php?page=barangay-manage-reports-print[&status=..&category=..&risk=..]
//             [&date_from=..&date_to=..&residency=..&search=..][&format=csv][&autoprint=1]

require_once dirname(__DIR__, 3) . '/config/config.php';
require_once BASE_PATH . '/helpers/SettingsHelper.php';
requireRole('barangay_official');

$database = new Database();
$db = $database->getConnection();

$barangay_id = (int)($_SESSION['barangay_id'] ?? 0);

// ------------------------------------------------------------
// INPUTS
// ------------------------------------------------------------
$status_filter   = $_GET['status'] ?? '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$risk_filter     = $_GET['risk'] ?? '';
$residency       = in_array($_GET['residency'] ?? '', ['resident', 'non_resident'], true) ? $_GET['residency'] : '';
$search          = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from       = $_GET['date_from'] ?? '';
$date_to         = $_GET['date_to'] ?? '';
$date_range      = isset($_GET['date_range']) ? (int)$_GET['date_range'] : 0;
$format          = isset($_GET['format']) && $_GET['format'] === 'csv' ? 'csv' : 'html';
$autoprint       = !empty($_GET['autoprint']);

$dateRangeLabels = [7 => 'Last 7 Days', 30 => 'Last 30 Days', 90 => 'Last 90 Days'];

$statusLabels = [
    'pending' => 'Pending', 'under_review' => 'Under Review', 'verified' => 'Verified',
    'in_progress' => 'In Progress', 'escalated_pending' => 'Escalated (Pending)',
    'escalated' => 'Escalated', 'resolved' => 'Resolved', 'rejected' => 'Rejected',
    'cancelled' => 'Cancelled'
];
$riskLabels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];

// ------------------------------------------------------------
// WHERE CLAUSE
// ------------------------------------------------------------
$where = "r.barangay_id = " . (int)$barangay_id;
$params = [];

if ($status_filter !== '') {
    if ($status_filter === 'escalated') {
        $where .= " AND r.status IN ('escalated_pending', 'escalated')";
    } else {
        $where .= " AND r.status = :status";
        $params[':status'] = $status_filter;
    }
}
if ($category_filter > 0) { $where .= " AND r.category_id = :category"; $params[':category'] = $category_filter; }
if ($risk_filter !== '') { $where .= " AND r.risk_level = :risk"; $params[':risk'] = $risk_filter; }
if ($residency === 'resident') { $where .= " AND u.is_resident = 1"; }
elseif ($residency === 'non_resident') { $where .= " AND u.is_resident = 0"; }
if ($search !== '') { $like = "%$search%"; $where .= " AND (r.title LIKE :search OR r.description LIKE :search OR CONCAT(u.first_name,' ',u.last_name) LIKE :search)"; $params[':search'] = $like; }
if ($date_range > 0) { $where .= " AND r.created_at >= DATE_SUB(NOW(), INTERVAL :date_range DAY)"; $params[':date_range'] = $date_range; }
if ($date_from !== '') { $where .= " AND DATE(r.created_at) >= :date_from"; $params[':date_from'] = $date_from; }
if ($date_to !== '') { $where .= " AND DATE(r.created_at) <= :date_to"; $params[':date_to'] = $date_to; }

$sql = "SELECT r.id, r.title, r.description, r.risk_level, r.severity_score, r.status,
               r.location_address, r.created_at, r.resolved_at,
               c.name AS category_name,
               u.first_name, u.last_name, u.is_resident
        FROM reports r
        JOIN categories c ON r.category_id = c.id
        JOIN users u ON r.user_id = u.id
        WHERE $where
        ORDER BY r.created_at DESC";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($reports);

// ------------------------------------------------------------
// FILTER LOOKUPS + TITLE
// ------------------------------------------------------------
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$titleCategory = '';
$titleStatus   = '';
$titleRisk     = '';
$filterSummary = [];
if ($category_filter > 0) {
    $tq = $db->prepare("SELECT name FROM categories WHERE id = ?");
    $tq->execute([$category_filter]);
    $titleCategory = (string)($tq->fetchColumn() ?: '');
    $filterSummary[] = 'Category: ' . $titleCategory;
}
if ($status_filter !== '') {
    $titleStatus = $statusLabels[$status_filter] ?? str_replace('_', ' ', $status_filter);
    $filterSummary[] = 'Status: ' . $titleStatus;
}
if ($risk_filter !== '') { $filterSummary[] = 'Risk: ' . ($riskLabels[$risk_filter] ?? $risk_filter); }
if ($residency === 'resident') $filterSummary[] = 'Reported by: Resident';
elseif ($residency === 'non_resident') $filterSummary[] = 'Reported by: Non-Resident';
if ($search !== '') $filterSummary[] = 'Search: "' . htmlspecialchars($search) . '"';
if ($date_range > 0) $filterSummary[] = 'Date Range: ' . ($dateRangeLabels[$date_range] ?? $date_range . ' days');
if ($date_from !== '') $filterSummary[] = 'From: ' . date('M j, Y', strtotime($date_from));
if ($date_to !== '') $filterSummary[] = 'To: ' . date('M j, Y', strtotime($date_to));

// Period descriptor matching the quick presets (Today / This Week / This Month / This Year).
$manageRangeMap = [
    'today' => [date('Y-m-d'), date('Y-m-d')],
    'week'  => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
    'month' => [date('Y-m-01'), date('Y-m-d')],
    'year'  => [date('Y-01-01'), date('Y-m-d')],
];
$managePeriodDesc = '';
if ($date_from !== '' || $date_to !== '') {
    foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $k => $n) {
        if ($date_from === $manageRangeMap[$k][0] && $date_to === $manageRangeMap[$k][1]) { $managePeriodDesc = $n; break; }
    }
    if ($managePeriodDesc === '') {
        $mfs = $date_from ? date('M j, Y', strtotime($date_from)) : '&hellip;';
        $mts = $date_to   ? date('M j, Y', strtotime($date_to))   : '&hellip;';
        $managePeriodDesc = $mfs . ' &ndash; ' . $mts;
    }
}

$reportTitleParts = [];
if ($residency === 'resident')      $reportTitleParts[] = 'Resident';
elseif ($residency === 'non_resident') $reportTitleParts[] = 'Non-Resident';
if ($titleStatus !== '')            $reportTitleParts[] = $titleStatus;
if ($risk_filter !== '')            $reportTitleParts[] = ($riskLabels[$risk_filter] ?? $risk_filter) . ' Risk';
$reportTitle = !empty($reportTitleParts) ? implode(' ', $reportTitleParts) . ' Reports' : 'All Reports';
if ($titleCategory !== '')          $reportTitle .= ' for ' . $titleCategory;
if ($managePeriodDesc !== '')       $reportTitle .= ' - ' . $managePeriodDesc;

// ------------------------------------------------------------
// PDF EXPORT CONFIG
// ------------------------------------------------------------
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

// ------------------------------------------------------------
// CSV EXPORT
// ------------------------------------------------------------
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="barangay_manage_reports_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Report ID', 'Title', 'Description', 'Category', 'Reporter', 'Residency', 'Risk Level', 'Severity Score', 'Status', 'Date Submitted', 'Resolved At']);
    foreach ($reports as $r) {
        fputcsv($out, [
            '#' . str_pad($r['id'], 5, '0', STR_PAD_LEFT),
            $r['title'],
            $r['description'] ?? '',
            $r['category_name'],
            trim($r['first_name'] . ' ' . $r['last_name']),
            ((int)($r['is_resident'] ?? 0) === 1) ? 'Resident' : 'Non-Resident',
            $riskLabels[$r['risk_level']] ?? $r['risk_level'],
            $r['severity_score'] ?? 0,
            $statusLabels[$r['status']] ?? $r['status'],
            $r['created_at'],
            $r['resolved_at'] ?? ''
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
    <title><?php echo htmlspecialchars($reportTitle); ?> - Printable</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Manrope', Arial, sans-serif; background: #eef2f1; color: #1f2937; font-size: 11px; }
        .toolbar { max-width: 100%; margin: 16px auto 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 0 12px; }
        .toolbar button { background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); color: #fff; border: none; border-radius: 8px; padding: 8px 16px; font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
        .toolbar button:hover { box-shadow: 0 4px 12px rgba(16,163,127,0.3); }
        .toolbar a { color: #374151; font-size: 12px; font-weight: 600; text-decoration: none; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; }
        .toolbar a:hover { border-color: #10A37F; color: #10A37F; }
        .report { width: 210mm; min-height: 297mm; margin: 0 auto; background: #ffffff; padding: 12mm 14mm; display: flex; flex-direction: column; }
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
        .filter-bar { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 14px; margin-bottom: 14px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .filter-bar .filter-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .filter-bar .filter-chip { background: #d1fae5; color: #065f46; border-radius: 999px; padding: 2px 10px; font-size: 10px; font-weight: 600; }
        .summary-row { display: flex; gap: 10px; margin-bottom: 14px; }
        .summary-card { flex: 1; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; background: #fafcfb; text-align: center; }
        .summary-card .sc-value { font-size: 22px; font-weight: 800; color: #111827; }
        .summary-card .sc-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 2px; }
        .sc-green { border-top: 3px solid #10A37F; }
        .sc-yellow { border-top: 3px solid #F59E0B; }
        .sc-blue { border-top: 3px solid #3B82F6; }
        .sc-red { border-top: 3px solid #EF4444; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        thead th { background: #f0fbf6; padding: 7px 8px; text-align: left; font-weight: 700; color: #374151; border-bottom: 2px solid #d1fae5; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; }
        tbody td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; color: #4b5563; vertical-align: top; }
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
        .badge-cancelled { background: #f3f4f6; color: #4b5563; }
        .signature-block { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: auto; padding-top: 12px; border-top: 1px solid #e5e7eb; }
        .sig-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .sig-line { border-bottom: 1px solid #374151; margin-top: 30px; }
        .sig-name { font-size: 12px; font-weight: 700; color: #111827; margin-top: 4px; text-align: center; }
        .sig-title { font-size: 10px; color: #6b7280; text-align: center; }
        .report-footer { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px; margin-top: 22px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #6b7280; }
        .report-footer .brand { font-weight: 700; color: #0D8568; }
        .report-footer-note { margin-top: 6px; text-align: center; font-size: 8px; color: #9ca3af; }
        .page-wrap { display: flex; align-items: flex-start; min-height: 100vh; }
        .filter-sidebar { width: 300px; flex-shrink: 0; background: #fff; border-right: 1px solid rgba(16,163,127,0.12); box-shadow: 2px 0 20px -8px rgba(16,163,127,0.18); position: sticky; top: 0; height: 100vh; display: flex; flex-direction: column; }
        .sidebar-head { padding: 16px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .sidebar-head .head-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 15px; box-shadow: 0 4px 10px rgba(16,163,127,0.3); flex-shrink: 0; }
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
        .filter-field select, .filter-field input { width: 100%; padding: 9px 10px; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 12.5px; font-family: inherit; background: #fff; color: #1f2937; transition: all 0.15s ease; }
        .filter-field select:focus, .filter-field input:focus { border-color: #10A37F; outline: none; box-shadow: 0 0 0 3px rgba(16,163,127,0.12); }
        .sidebar-footer { padding: 14px 16px; border-top: 1px solid #f3f4f6; background: #fff; display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }
        .btn-apply { width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; }
        .btn-apply:hover { box-shadow: 0 6px 16px rgba(16,163,127,0.35); transform: translateY(-1px); }
        .btn-reset { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 12px; border: 1.5px solid #e5e7eb; border-radius: 10px; background: #fff; color: #6b7280; font-size: 12px; font-weight: 600; text-decoration: none; }
        .btn-reset:hover { color: #EF4444; border-color: #EF4444; background: #FEF2F2; }
        .page-main { flex: 1; min-width: 0; padding: 16px; }
        @media (max-width: 900px) { .page-wrap { flex-direction: column; } .filter-sidebar { width: 100%; position: static; height: auto; border-right: none; border-bottom: 1px solid rgba(16,163,127,0.12); } }
        @page { size: A4 landscape; margin: 12mm 10mm 18mm; }
        @media print {
            body { background: #ffffff !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .filter-sidebar { display: none !important; }
            .page-wrap { display: block; padding: 0; }
            .page-main { padding: 0; }
            .toolbar { display: none !important; }
            .report { width: 100%; min-height: 0; margin: 0; padding: 0; box-shadow: none; border: none; }
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
            <form class="sidebar-form" method="get" action="<?php echo BASE_URL; ?>index.php">
                <input type="hidden" name="page" value="barangay-manage-reports-print">
                <div class="sidebar-body">
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Scope</div>
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
                            <label for="sideCategory">Category</label>
                            <select name="category" id="sideCategory">
                                <option value="0" <?php echo $category_filter === 0 ? 'selected' : ''; ?>>All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>" <?php echo $category_filter === (int)$cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sideRisk">Risk Level</label>
                            <select name="risk" id="sideRisk">
                                <option value="">All Levels</option>
                                <?php foreach ($riskLabels as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $risk_filter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sideResidency">Reported By</label>
                            <select name="residency" id="sideResidency">
                                <option value="">All Reporters</option>
                                <option value="resident" <?php echo $residency === 'resident' ? 'selected' : ''; ?>>Resident</option>
                                <option value="non_resident" <?php echo $residency === 'non_resident' ? 'selected' : ''; ?>>Non-Resident</option>
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
                        </div>
                        <div class="filter-field">
                            <label for="sideDateRange">Date Range</label>
                            <select name="date_range" id="sideDateRange">
                                <option value="0">All Time</option>
                                <option value="7" <?php echo $date_range === 7 ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="30" <?php echo $date_range === 30 ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="90" <?php echo $date_range === 90 ? 'selected' : ''; ?>>Last 90 Days</option>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sideDateFrom">Date From</label>
                            <input type="date" name="date_from" id="sideDateFrom" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="sideDateTo">Date To</label>
                            <input type="date" name="date_to" id="sideDateTo" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                    </div>
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Search</div>
                        <div class="filter-field">
                            <input type="text" name="search" id="sideSearch" value="<?php echo htmlspecialchars($search); ?>" placeholder="Title, description, reporter...">
                        </div>
                    </div>
                </div>
                <div class="sidebar-footer">
                    <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="<?php echo BASE_URL; ?>index.php?page=barangay-manage-reports-print" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </aside>

        <div class="page-main">
            <div class="toolbar">
                <a href="<?php echo BASE_URL; ?>index.php?page=verify-reports"><i class="fas fa-arrow-left" style="margin-right:6px;"></i>Back</a>
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
                    <div class="report-title"><?php echo htmlspecialchars($reportTitle); ?></div>
                    <div class="report-subtitle">Environmental Incident Report &middot; <?php echo htmlspecialchars($municipality); ?></div>
                    <div class="report-meta">
                        <span><strong>Generated On:</strong> <?php echo htmlspecialchars($generatedOn); ?></span>
                        <span><strong>Generated By:</strong> <?php echo htmlspecialchars($generatedBy); ?></span>
                        <span><strong>Total Records:</strong> <?php echo number_format($total); ?></span>
                    </div>
                </div>

                <?php if (!empty($filterSummary)): ?>
                <div class="filter-bar">
                    <span class="filter-label"><i class="fas fa-filter" style="margin-right:4px;"></i>Filters:</span>
                    <?php foreach ($filterSummary as $f): ?><span class="filter-chip"><?php echo htmlspecialchars($f); ?></span><?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php
                $pendingCount = 0; $activeCount = 0; $resolvedCount = 0; $highRiskCount = 0;
                foreach ($reports as $r) {
                    if ($r['status'] === 'pending') $pendingCount++;
                    if (in_array($r['status'], ['in_progress','under_review','verified','escalated_pending','escalated'])) $activeCount++;
                    if ($r['status'] === 'resolved') $resolvedCount++;
                    if (in_array($r['risk_level'], ['high','critical'])) $highRiskCount++;
                }
                ?>
                <div class="summary-row">
                    <div class="summary-card sc-green"><div class="sc-value"><?php echo $total; ?></div><div class="sc-label">Total Reports</div></div>
                    <div class="summary-card sc-yellow"><div class="sc-value"><?php echo $pendingCount; ?></div><div class="sc-label">Pending</div></div>
                    <div class="summary-card sc-blue"><div class="sc-value"><?php echo $activeCount; ?></div><div class="sc-label">Active</div></div>
                    <div class="summary-card sc-red"><div class="sc-value"><?php echo $highRiskCount; ?></div><div class="sc-label">High Risk</div></div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width:6%;">ID</th>
                            <th style="width:18%;">Title</th>
                            <th style="width:12%;">Reporter</th>
                            <th style="width:8%;">Residency</th>
                            <th style="width:10%;">Category</th>
                            <th style="width:7%;">Risk</th>
                            <th style="width:8%;">Severity</th>
                            <th style="width:9%;">Status</th>
                            <th style="width:9%;">Date</th>
                            <th style="width:9%;">Resolved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total > 0): ?>
                            <?php foreach ($reports as $row): ?>
                            <tr>
                                <td style="color:#6b7280;">#<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td style="font-weight:600;color:#111827;"><?php echo htmlspecialchars(substr($row['title'], 0, 35)); ?><?php echo strlen($row['title']) > 35 ? '...' : ''; ?></td>
                                <td><?php echo htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])); ?></td>
                                <td><?php echo ((int)($row['is_resident'] ?? 0) === 1) ? 'Resident' : 'Non-Resident'; ?></td>
                                <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td><span class="badge badge-<?php echo $row['risk_level']; ?>"><?php echo $riskLabels[$row['risk_level']] ?? ucfirst($row['risk_level']); ?></span></td>
                                <td style="text-align:center;"><?php echo $row['severity_score'] ?? 0; ?></td>
                                <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo $statusLabels[$row['status']] ?? ucfirst(str_replace('_', ' ', $row['status'])); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td><?php echo $row['resolved_at'] ? date('M d, Y', strtotime($row['resolved_at'])) : '&mdash;'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10" style="text-align:center;padding:20px;color:#9ca3af;">No reports match the selected filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

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

    <?php if ($autoprint): ?>
    <script>window.addEventListener('load', function() { setTimeout(function() { window.print(); }, 700); });</script>
    <?php endif; ?>
    <script>
        function setQuickReportRange(range) {
            var f = document.getElementById('sideDateFrom') || document.getElementById('sideFrom');
            var t = document.getElementById('sideDateTo') || document.getElementById('sideTo');
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
</body>
</html>
