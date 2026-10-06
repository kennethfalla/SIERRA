<?php
// views/barangay/reports/reporters_directory_print.php - BARANGAY REPORTERS DIRECTORY EXPORT
// Printable/CSV directory of reporters (residents, non-residents/other barangay,
// or all reporters). Opened via:
//   index.php?page=barangay-reporters-print&type=residents|non_residents|all[&search=..][&format=csv][&autoprint=1]

require_once dirname(__DIR__, 3) . '/config/config.php';
require_once BASE_PATH . '/helpers/SettingsHelper.php';
requireRole('barangay_official');

$database = new Database();
$db = $database->getConnection();

$barangay_id = (int)($_SESSION['barangay_id'] ?? 0);
$type     = in_array($_GET['type'] ?? 'all', ['residents', 'non_residents', 'all'], true) ? $_GET['type'] : 'all';
$search   = trim($_GET['search'] ?? '');
$format   = isset($_GET['format']) && $_GET['format'] === 'csv' ? 'csv' : 'html';
$autoprint = !empty($_GET['autoprint']);

$brgyStmt = $db->prepare("SELECT name FROM barangays WHERE id = ?");
$brgyStmt->execute([$barangay_id]);
$barangay_name = $brgyStmt->fetchColumn() ?: 'Your Barangay';

// ------------------------------------------------------------
// QUERY
// ------------------------------------------------------------
$searchSql = '';
$searchParams = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $searchSql = " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR u.contact_number LIKE ?)";
    $searchParams = [$like, $like, $like];
}

if ($type === 'residents') {
    $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.contact_number,
                   u.purok_street, u.created_at,
                   (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id AND r.barangay_id = ?) AS report_count
            FROM users u
            WHERE u.user_type IS NULL AND u.barangay_id = ? AND u.is_resident = 1 AND u.is_active = 1
            $searchSql
            ORDER BY report_count DESC, u.first_name ASC";
    $params = [$barangay_id, $barangay_id];
} elseif ($type === 'non_residents') {
    $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.contact_number,
                   u.is_resident, u.province, u.municipality, u.non_resident_address,
                   b.name AS home_barangay, u.created_at,
                   (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id AND r.barangay_id = ?) AS report_count
            FROM users u
            LEFT JOIN barangays b ON u.barangay_id = b.id
            WHERE u.user_type IS NULL
              AND u.id IN (SELECT DISTINCT r2.user_id FROM reports r2 WHERE r2.barangay_id = ?)
              AND (u.is_resident = 0 OR u.barangay_id IS NULL OR u.barangay_id != ?)
              AND u.is_active = 1
            $searchSql
            ORDER BY report_count DESC, u.first_name ASC";
    $params = [$barangay_id, $barangay_id, $barangay_id];
} else {
    $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.contact_number,
                   u.is_resident, u.province, u.municipality, u.non_resident_address, u.purok_street,
                   b.name AS home_barangay, u.created_at,
                   (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id AND r.barangay_id = ?) AS report_count
            FROM users u
            LEFT JOIN barangays b ON u.barangay_id = b.id
            WHERE u.user_type IS NULL AND u.is_active = 1
              AND ( (u.barangay_id = ? AND u.is_resident = 1)
                    OR u.id IN (SELECT DISTINCT r2.user_id FROM reports r2 WHERE r2.barangay_id = ?) )
            $searchSql
            ORDER BY report_count DESC, u.first_name ASC";
    $params = [$barangay_id, $barangay_id, $barangay_id];
}

$stmt = $db->prepare($sql);
$stmt->execute(array_merge($params, $searchParams));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);

function rdir_residency($r) {
    return ((int)($r['is_resident'] ?? 0) === 1) ? 'Resident' : 'Non-Resident';
}
function rdir_location($r) {
    $loc = trim(implode(', ', array_filter([$r['municipality'] ?? '', $r['province'] ?? ''])));
    if ($loc !== '') return $loc;
    if (!empty($r['non_resident_address'])) return $r['non_resident_address'];
    if (!empty($r['purok_street'])) return $r['purok_street'];
    return '—';
}

$typeLabel = $type === 'residents' ? 'Residents' : ($type === 'non_residents' ? 'Non-Residents & Other Barangay' : 'All Reporters');
$reportTitle = $type === 'residents' ? 'RESIDENT REPORTERS DIRECTORY'
             : ($type === 'non_residents' ? 'NON-RESIDENT REPORTERS DIRECTORY'
             : 'REPORTERS DIRECTORY');

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
    header('Content-Disposition: attachment; filename="barangay_reporters_' . $type . '_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    if ($type === 'residents') {
        fputcsv($out, ['ID', 'First Name', 'Last Name', 'Email', 'Contact Number', 'Purok / Street', 'Reports Submitted', 'Registered']);
    } else {
        fputcsv($out, ['ID', 'First Name', 'Last Name', 'Email', 'Contact Number', 'Residency', 'Location', 'Home Barangay', 'Reports Submitted', 'Registered']);
    }

    foreach ($rows as $r) {
        if ($type === 'residents') {
            fputcsv($out, [
                str_pad($r['id'], 5, '0', STR_PAD_LEFT),
                $r['first_name'], $r['last_name'], $r['email'] ?? '',
                $r['contact_number'] ?? '', $r['purok_street'] ?? '',
                $r['report_count'] ?? 0, date('M d, Y', strtotime($r['created_at']))
            ]);
        } else {
            fputcsv($out, [
                str_pad($r['id'], 5, '0', STR_PAD_LEFT),
                $r['first_name'], $r['last_name'], $r['email'] ?? '',
                $r['contact_number'] ?? '', rdir_residency($r), rdir_location($r),
                $r['home_barangay'] ?? '-', $r['report_count'] ?? 0,
                date('M d, Y', strtotime($r['created_at']))
            ]);
        }
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
    <title>Reporters Directory - Printable</title>
    <link href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/material-symbols.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/material-symbols.css'); ?>">
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
        table { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 8px; }
        thead th { background: #f0fbf6; padding: 7px 8px; text-align: left; font-weight: 700; color: #374151; border-bottom: 2px solid #d1fae5; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; }
        tbody td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; color: #4b5563; vertical-align: top; }
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
        .sidebar-head .head-icon { width: 38px; height: 38px; border-radius: 8px; background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 15px; box-shadow: 0 4px 10px rgba(16,163,127,0.3); flex-shrink: 0; }
        .sidebar-head .head-title { font-size: 14px; font-weight: 800; color: #111827; }
        .sidebar-head .head-sub { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 1px; }
        .sidebar-form { flex: 1; display: flex; flex-direction: column; min-height: 0; }
        .sidebar-body { flex: 1; overflow-y: auto; padding: 14px 16px; display: flex; flex-direction: column; gap: 16px; }
        .sidebar-group { display: flex; flex-direction: column; gap: 10px; }
        .sidebar-group-label { font-size: 10px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.06em; }
        .filter-field { display: flex; flex-direction: column; gap: 4px; }
        .filter-field label { font-size: 11px; font-weight: 600; color: #374151; }
        .filter-field select, .filter-field input { width: 100%; padding: 9px 10px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 12.5px; font-family: inherit; background: #fff; color: #1f2937; transition: all 0.15s ease; }
        .filter-field select:focus, .filter-field input:focus { border-color: #10A37F; outline: none; box-shadow: 0 0 0 3px rgba(16,163,127,0.12); }
        .sidebar-footer { padding: 14px 16px; border-top: 1px solid #f3f4f6; background: #fff; display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }
        .btn-apply { width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; }
        .btn-apply:hover { box-shadow: 0 6px 16px rgba(16,163,127,0.35); transform: translateY(-1px); }
        .btn-reset { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 12px; border: 1.5px solid #e5e7eb; border-radius: 8px; background: #fff; color: #6b7280; font-size: 12px; font-weight: 600; text-decoration: none; }
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
</head>
<body>
    <div class="page-wrap">
        <aside class="filter-sidebar">
            <div class="sidebar-head">
                <div class="head-icon"><i class="fas fa-sliders-h"></i></div>
                <div>
                    <div class="head-title">Filters</div>
                    <div class="head-sub">Refine directory</div>
                </div>
            </div>
            <form class="sidebar-form" method="get" action="<?php echo BASE_URL; ?>index.php">
                <input type="hidden" name="page" value="barangay-reporters-print">
                <div class="sidebar-body">
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Directory</div>
                        <div class="filter-field">
                            <label for="sideType">Reporter Group</label>
                            <select name="type" id="sideType">
                                <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Reporters</option>
                                <option value="residents" <?php echo $type === 'residents' ? 'selected' : ''; ?>>Residents</option>
                                <option value="non_residents" <?php echo $type === 'non_residents' ? 'selected' : ''; ?>>Non-Residents &amp; Other Barangay</option>
                            </select>
                        </div>
                    </div>
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Search</div>
                        <div class="filter-field">
                            <input type="text" name="search" id="sideSearch" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, email, contact...">
                        </div>
                    </div>
                </div>
                <div class="sidebar-footer">
                    <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="<?php echo BASE_URL; ?>index.php?page=barangay-reporters-print" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </aside>

        <div class="page-main">
            <div class="toolbar">
                <a href="<?php echo BASE_URL; ?>index.php?page=reporters-directory"><i class="fas fa-arrow-left" style="margin-right:6px;"></i>Back</a>
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
                    <div class="report-subtitle"><?php echo htmlspecialchars($typeLabel); ?> &middot; Barangay <?php echo htmlspecialchars($barangay_name); ?></div>
                    <div class="report-meta">
                        <span><strong>Generated On:</strong> <?php echo htmlspecialchars($generatedOn); ?></span>
                        <span><strong>Generated By:</strong> <?php echo htmlspecialchars($generatedBy); ?></span>
                        <span><strong>Total Reporters:</strong> <?php echo number_format($total); ?></span>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width:6%;">ID</th>
                            <th style="width:18%;">Name</th>
                            <th style="width:18%;">Email</th>
                            <th style="width:12%;">Contact</th>
                            <?php if ($type === 'residents'): ?>
                            <th style="width:14%;">Purok / Street</th>
                            <?php else: ?>
                            <th style="width:10%;">Residency</th>
                            <th style="width:16%;">Location</th>
                            <th style="width:12%;">Home Barangay</th>
                            <?php endif; ?>
                            <th style="width:9%;">Reports</th>
                            <th style="width:11%;">Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total > 0): ?>
                            <?php foreach ($rows as $r): ?>
                            <tr>
                                <td style="color:#6b7280;">#<?php echo str_pad($r['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td style="font-weight:600;color:#111827;"><?php echo htmlspecialchars(trim($r['first_name'] . ' ' . $r['last_name'])); ?></td>
                                <td><?php echo htmlspecialchars($r['email'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($r['contact_number'] ?: '—'); ?></td>
                                <?php if ($type === 'residents'): ?>
                                <td><?php echo htmlspecialchars($r['purok_street'] ?: '—'); ?></td>
                                <?php else: ?>
                                <td><?php echo htmlspecialchars(rdir_residency($r)); ?></td>
                                <td><?php echo htmlspecialchars(rdir_location($r)); ?></td>
                                <td><?php echo htmlspecialchars($r['home_barangay'] ?: '—'); ?></td>
                                <?php endif; ?>
                                <td style="text-align:center;"><?php echo (int)($r['report_count'] ?? 0); ?></td>
                                <td><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center;padding:20px;color:#9ca3af;">No reporters found.</td></tr>
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
<script src="<?php echo BASE_URL; ?>assets/js/export-print.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/export-print.js'); ?>"></script>
</body>
</html>
