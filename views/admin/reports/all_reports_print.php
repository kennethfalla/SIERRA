<?php
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once BASE_PATH . '/helpers/SettingsHelper.php';
require_once BASE_PATH . '/helpers/PermissionHelper.php';
requireRole('admin');

$database = new Database();
$db = $database->getConnection();

$status_filter = $_GET['status'] ?? '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$barangay_filter = isset($_GET['barangay']) ? (int)$_GET['barangay'] : 0;
$risk_filter = $_GET['risk'] ?? '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$autoprint = !empty($_GET['autoprint']);

$where = "1=1";
$params = [];
if ($status_filter != '') {
    if ($status_filter == 'escalated') {
        $where .= " AND r.status IN ('escalated_pending', 'escalated')";
    } else {
        $where .= " AND r.status = :status";
        $params[':status'] = $status_filter;
    }
}
if ($category_filter > 0) { $where .= " AND r.category_id = :category"; $params[':category'] = $category_filter; }
if ($barangay_filter > 0) { $where .= " AND r.barangay_id = :barangay"; $params[':barangay'] = $barangay_filter; }
if ($risk_filter != '') { $where .= " AND r.risk_level = :risk"; $params[':risk'] = $risk_filter; }
if ($search != '') { $search_like = "%$search%"; $where .= " AND (r.title LIKE :search OR r.description LIKE :search OR CONCAT(u.first_name,' ',u.last_name) LIKE :search)"; $params[':search'] = $search_like; }
if ($date_from != '') { $where .= " AND DATE(r.created_at) >= :date_from"; $params[':date_from'] = $date_from; }
if ($date_to != '') { $where .= " AND DATE(r.created_at) <= :date_to"; $params[':date_to'] = $date_to; }

$sql = "SELECT r.id, r.title, r.description, r.risk_level, r.severity_score, r.status,
        r.impact_modifier, r.verification_count, r.location_address,
        c.name AS category_name, b.name AS barangay_name,
        CONCAT(u.first_name, ' ', u.last_name) AS reporter_name,
        r.created_at, r.resolved_at
        FROM reports r
        JOIN categories c ON r.category_id = c.id
        JOIN barangays b ON r.barangay_id = b.id
        JOIN users u ON r.user_id = u.id
        WHERE $where
        ORDER BY r.created_at DESC";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($reports);

$statusLabels = ['pending'=>'Pending','under_review'=>'Under Review','verified'=>'Verified','in_progress'=>'In Progress','escalated_pending'=>'Escalated (Pending)','escalated'=>'Escalated','resolved'=>'Resolved','rejected'=>'Rejected','cancelled'=>'Cancelled'];
$riskLabels = ['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'];

$lguLogo = SettingsHelper::getLogoUrl();
$menroLogoPath = SettingsHelper::get('menro_logo', '');
$menroLogo = $menroLogoPath ? BASE_URL . $menroLogoPath : '';
$officeName = SettingsHelper::get('pdf_office_name', 'Municipal Environment and Natural Resources Office');
$municipality = SettingsHelper::get('pdf_municipality_name', 'Municipality of San Isidro');
$systemName = SettingsHelper::get('system_name', 'SIERRA');
$generatedBy = $_SESSION['user_name'] ?? 'System User';
$generatedOn = date('F j, Y \a\t h:i A');

$filterSummary = [];
if ($status_filter) $filterSummary[] = 'Status: ' . ($statusLabels[$status_filter] ?? $status_filter);
if ($category_filter > 0) { $catQ = $db->prepare("SELECT name FROM categories WHERE id = ?"); $catQ->execute([$category_filter]); $catRow = $catQ->fetch(); $filterSummary[] = 'Category: ' . ($catRow['name'] ?? ''); }
if ($barangay_filter > 0) { $brgQ = $db->prepare("SELECT name FROM barangays WHERE id = ?"); $brgQ->execute([$barangay_filter]); $brgRow = $brgQ->fetch(); $filterSummary[] = 'Barangay: ' . ($brgRow['name'] ?? ''); }
if ($risk_filter) $filterSummary[] = 'Risk: ' . ($riskLabels[$risk_filter] ?? $risk_filter);
if ($search) $filterSummary[] = 'Search: "' . htmlspecialchars($search) . '"';
if ($date_from) $filterSummary[] = 'From: ' . date('M j, Y', strtotime($date_from));
if ($date_to) $filterSummary[] = 'To: ' . date('M j, Y', strtotime($date_to));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ($lguLogo): ?><link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($lguLogo); ?>"><?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Reports - Printable</title>
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
        .toolbar .hint { color: #6b7280; font-size: 11px; }
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
        tbody tr:hover td { background: #f9fafb; }
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
        @page { size: A4 landscape; margin: 12mm 10mm 18mm; }
        @media print {
            body { background: #ffffff !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .toolbar { display: none !important; }
            .report { width: 100%; min-height: 0; margin: 0; padding: 0; box-shadow: none; border: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()"><i class="fas fa-print" style="margin-right:6px;"></i>Print / Save as PDF</button>
        <a href="<?php echo BASE_URL; ?>index.php?page=all-reports">&larr; Back to All Reports</a>
        <span class="hint">Tip: choose "Save as PDF" as the printer destination for a PDF export.</span>
    </div>

    <div class="report">
        <header class="report-header">
            <div class="logo-box">
                <?php if ($lguLogo): ?><img src="<?php echo htmlspecialchars($lguLogo); ?>" alt="LGU Logo"><?php else: ?><div class="logo-placeholder">LGU<br>Logo</div><?php endif; ?>
            </div>
            <div class="org-block">
                <div class="org-line1">Republic of the Philippines</div>
                <div class="org-name"><?php echo htmlspecialchars($officeName); ?></div>
                <div class="org-muni"><?php echo htmlspecialchars($municipality); ?></div>
            </div>
            <div class="logo-box">
                <?php if ($menroLogo): ?><img src="<?php echo htmlspecialchars($menroLogo); ?>" alt="MENRO Logo"><?php else: ?><div class="logo-placeholder">MENRO<br>Logo</div><?php endif; ?>
            </div>
        </header>

        <div class="report-title-block">
            <div class="report-title">ALL REPORTS</div>
            <div class="report-subtitle">Environmental Incident Report &middot; <?php echo htmlspecialchars($municipality); ?></div>
            <div class="report-meta">
                <span><strong>Generated On:</strong> <?php echo htmlspecialchars($generatedOn); ?></span>
                <span><strong>Generated By:</strong> <?php echo htmlspecialchars($generatedBy); ?></span>
                <span><strong>Total Records:</strong> <?php echo number_format($total); ?></span>
            </div>
        </div>

        <?php if (!empty($filterSummary)): ?>
        <div class="filter-bar">
            <span class="filter-label"><i class="fas fa-filter" style="margin-right:4px;"></i>Active Filters:</span>
            <?php foreach ($filterSummary as $f): ?>
                <span class="filter-chip"><?php echo htmlspecialchars($f); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php
        $totalCount = $total;
        $pendingCount = 0; $activeCount = 0; $resolvedCount = 0; $highRiskCount = 0;
        foreach ($reports as $r) {
            if ($r['status'] === 'pending') $pendingCount++;
            if (in_array($r['status'], ['in_progress','under_review','verified','escalated_pending','escalated'])) $activeCount++;
            if ($r['status'] === 'resolved') $resolvedCount++;
            if (in_array($r['risk_level'], ['high','critical'])) $highRiskCount++;
        }
        ?>
        <div class="summary-row">
            <div class="summary-card sc-green"><div class="sc-value"><?php echo $totalCount; ?></div><div class="sc-label">Total Reports</div></div>
            <div class="summary-card sc-yellow"><div class="sc-value"><?php echo $pendingCount; ?></div><div class="sc-label">Pending</div></div>
            <div class="summary-card sc-blue"><div class="sc-value"><?php echo $activeCount; ?></div><div class="sc-label">Active</div></div>
            <div class="summary-card sc-red"><div class="sc-value"><?php echo $highRiskCount; ?></div><div class="sc-label">High Risk</div></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:6%;">ID</th>
                    <th style="width:18%;">Title</th>
                    <th style="width:10%;">Reporter</th>
                    <th style="width:10%;">Category</th>
                    <th style="width:10%;">Barangay</th>
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
                        <td style="font-family:monospace;color:#6b7280;">#<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td style="font-weight:600;color:#111827;"><?php echo htmlspecialchars(substr($row['title'], 0, 35)); ?><?php echo strlen($row['title']) > 35 ? '...' : ''; ?></td>
                        <td><?php echo htmlspecialchars($row['reporter_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['barangay_name']); ?></td>
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
                <div class="sig-name"><?php echo htmlspecialchars($generatedBy); ?></div>
                <div class="sig-title">MENRO Staff</div>
            </div>
            <div>
                <div class="sig-label">Noted and Approved by:</div>
                <div class="sig-line"></div>
                <div class="sig-name">____________________</div>
                <div class="sig-title">MENRO Head</div>
            </div>
        </div>

        <footer class="report-footer">
            <span>Date Printed: <?php echo date('F j, Y'); ?></span>
            <span>Time Printed: <?php echo date('h:i A'); ?></span>
            <span class="brand"><?php echo htmlspecialchars($systemName); ?> &middot; Web-Based Environmental Reporting System</span>
        </footer>
    </div>

    <?php if ($autoprint): ?>
    <script>window.addEventListener('load', function() { setTimeout(function() { window.print(); }, 700); });</script>
    <?php endif; ?>
</body>
</html>
