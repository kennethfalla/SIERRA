<?php
// views/admin/reports/manage_report_print.php - PRINTABLE SINGLE REPORT
// Formal one-report printout. Included by ReportController when
// index.php?page=manage-report&id=ID&print=1 is requested.
// Expects $view_data (report, images, notes, resolution_evidence, escalation, user_role).

if (!isset($view_data) || empty($view_data)) {
    header('Location: ' . BASE_URL . 'index.php?page=dashboard');
    exit();
}

$report = $view_data['report'];
$images = $view_data['images'];
$notes  = $view_data['notes'];
$resolution_evidence = $view_data['resolution_evidence'];
$escalation = $view_data['escalation'];
$user_role = $view_data['user_role'];

$statusLabels = [
    'pending'           => 'Pending',
    'under_review'      => 'Under Review',
    'verified'          => 'Verified',
    'in_progress'       => 'In Progress',
    'escalated_pending' => 'Escalation Pending',
    'escalated'         => 'Escalated',
    'resolved'          => 'Resolved',
    'rejected'          => 'Rejected',
    'cancelled'         => 'Cancelled',
];
$statusText = $statusLabels[$report['status']] ?? ucfirst($report['status']);

$lguLogo       = SettingsHelper::getLogoUrl();
$menroLogoPath = SettingsHelper::get('menro_logo', '');
$menroLogo     = $menroLogoPath ? BASE_URL . $menroLogoPath : '';
$officeName    = SettingsHelper::get('pdf_office_name', 'Municipal Environment and Natural Resources Office');
$municipality  = SettingsHelper::get('pdf_municipality_name', 'Municipality of San Isidro');
$systemName    = SettingsHelper::get('system_name', 'SIERRA');
$generatedBy   = $_SESSION['user_name'] ?? 'System User';
$generatedOn   = date('F j, Y \a\t h:i A');

// PDF Export signatory block + footer (Settings > PDF Export)
$preparedBy    = SettingsHelper::get('pdf_prepared_by_name', '');
$preparedTitle = SettingsHelper::get('pdf_prepared_by_title', 'MENRO Data Analyst / Administrator');
$approvedBy    = SettingsHelper::get('pdf_approved_by_name', '');
$approvedTitle = SettingsHelper::get('pdf_approved_by_title', 'Municipal Environment and Natural Resources Officer');
$footerNote    = SettingsHelper::get('pdf_footer_note', 'System Generated via SIERRA (Web-Based Environmental Reporting Application) | Page 1 of 1');

$reportNo = str_pad($report['id'], 6, '0', STR_PAD_LEFT);
$hasCoords = !empty($report['latitude']) && !empty($report['longitude']) && (float)$report['latitude'] != 0 && (float)$report['longitude'] != 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ($lguLogo): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($lguLogo); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report #<?php echo $reportNo; ?> - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css">
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
        .toolbar a:hover { border-color: #10A37F; color: #10A37F; }
        .toolbar .hint { color: #6b7280; font-size: 11px; }

        /* ===== Report page (paper-agnostic) ===== */
        .report {
            width: 100%;
            margin: 0 auto;
            background: #ffffff;
            padding: 10mm 12mm;
        }

        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding-bottom: 10px;
            border-bottom: 3px solid #10A37F;
        }
        .logo-box { width: 22mm; height: 22mm; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .logo-box img { max-width: 22mm; max-height: 22mm; object-fit: contain; }
        .logo-placeholder {
            width: 22mm; height: 22mm; border: 1px dashed #d1d5db; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            color: #9ca3af; font-size: 8px; text-align: center;
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

        /* ===== Sections ===== */
        .section { margin-top: 12px; }
        .section-head {
            font-size: 11px;
            font-weight: 800;
            color: #0D8568;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 5px 10px;
            background: #f4faf7;
            border: 1px solid #dff0e9;
            border-left: 5px solid #10A37F;
            border-radius: 6px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .section-body { padding: 2px 2px; }

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
            width: 34%;
        }
        table.data td {
            padding: 5px 7px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
            vertical-align: top;
        }
        table.data tr:nth-child(even) td { background: #fafcfb; }

        .desc-text { white-space: pre-line; line-height: 1.55; font-size: 10.5px; color: #374151; }

        /* ===== Photo grids ===== */
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 8px;
        }
        .photo-grid .photo-cell {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
            background: #fafcfb;
        }
        .photo-grid img, .photo-grid video {
            width: 100%;
            height: 90px;
            object-fit: cover;
            display: block;
        }
        .photo-cell .cap { font-size: 8px; color: #6b7280; text-align: center; padding: 3px 4px; }

        /* ===== Notes / escalation ===== */
        .note-item {
            border: 1px solid #dff0e9;
            border-left: 3px solid #10A37F;
            border-radius: 6px;
            background: #fafcfb;
            padding: 6px 9px;
            margin-bottom: 6px;
        }
        .note-item .n-text { font-size: 10px; color: #374151; }
        .note-item .n-meta { font-size: 8.5px; color: #9ca3af; margin-top: 2px; }

        /* ===== Signature ===== */
        .signature-block {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 30px;
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

        @page {
            margin: 10mm 12mm;
        }
        @media print {
            body { background: #ffffff !important; }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .toolbar { display: none !important; }
            .report { width: 100%; margin: 0; padding: 0; }
            .section, .report-title-block, .signature-block, .report-header { break-inside: avoid; }
            table.data tr { break-inside: avoid; }
            .photo-grid { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <!-- Screen-only toolbar (hidden on print) -->
    <div class="toolbar">
        <button type="button" onclick="window.print()"><i class="fas fa-print" style="margin-right:6px;"></i>Print</button>
        <button type="button" onclick="window.print()"><i class="fas fa-file-pdf" style="margin-right:6px;"></i>Save as PDF</button>
        <a href="<?php echo BASE_URL; ?>index.php?page=manage-report&id=<?php echo (int)$report['id']; ?>">&larr; Back to Report</a>
        <span class="hint">Tip: choose "Save as PDF" as the printer destination for a PDF export.</span>
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
            <div class="report-title">ENVIRONMENTAL REPORT DETAILS</div>
            <div class="report-subtitle">Report #<?php echo $reportNo; ?> &middot; <?php echo htmlspecialchars($report['category_name'] ?? 'Environmental Report'); ?></div>
            <div class="report-meta">
                <span><strong>Status:</strong> <?php echo htmlspecialchars($statusText); ?></span>
                <span><strong>Risk:</strong> <?php echo htmlspecialchars(ucfirst($report['risk_level'] ?? 'low')); ?></span>
                <span><strong>Barangay:</strong> <?php echo htmlspecialchars($report['barangay_name'] ?? '—'); ?></span>
                <span><strong>Submitted:</strong> <?php echo date('M d, Y \a\t h:i A', strtotime($report['created_at'])); ?></span>
                <span><strong>Generated:</strong> <?php echo htmlspecialchars($generatedOn); ?></span>
            </div>
        </div>

        <!-- ===== Reporter Details ===== -->
        <div class="section">
            <div class="section-head"><i class="fas fa-user"></i> Reporter Details</div>
            <div class="section-body">
                <table class="data">
                    <tbody>
                        <tr><th>Full Name</th><td><?php echo htmlspecialchars($report['user_name'] ?? 'Unknown'); ?></td></tr>
                        <tr><th>Contact Number</th><td><?php echo htmlspecialchars($report['contact_number'] ?? '—'); ?></td></tr>
                        <tr><th>Account Type</th><td><?php echo !empty($report['is_resident']) ? 'Resident' : 'Non-Resident'; ?></td></tr>
                        <tr><th>Barangay</th><td><?php echo htmlspecialchars($report['barangay_name'] ?? '—'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== Report Details ===== -->
        <div class="section">
            <div class="section-head"><i class="fas fa-tags"></i> Report Details</div>
            <div class="section-body">
                <table class="data">
                    <tbody>
                        <tr><th>Title</th><td><strong><?php echo htmlspecialchars($report['title']); ?></strong></td></tr>
                        <tr><th>Category</th><td><?php echo htmlspecialchars($report['category_name'] ?? '—'); ?></td></tr>
                        <tr><th>Status</th><td><?php echo htmlspecialchars($statusText); ?></td></tr>
                        <tr><th>Risk Level</th><td><?php echo htmlspecialchars(ucfirst($report['risk_level'] ?? 'low')); ?></td></tr>
                        <tr><th>Impact Modifier</th><td><?php
                            $imp = (int)($report['impact_modifier'] ?? 0);
                            echo $imp >= 4 ? 'Severe (+4)' : ($imp >= 2 ? 'Moderate (+2)' : 'Localized (+0)');
                        ?></td></tr>
                        <tr><th>Severity Score</th><td><?php echo htmlspecialchars((string)($report['severity_score'] ?? 0)); ?></td></tr>
                        <tr><th>Classification</th><td><?php echo htmlspecialchars($report['decision_classification'] ?? 'Pending'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== Description ===== -->
        <div class="section">
            <div class="section-head"><i class="fas fa-align-left"></i> Resident's Description</div>
            <div class="section-body">
                <p class="desc-text"><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
            </div>
        </div>

        <!-- ===== Evidentiary Photos ===== -->
        <div class="section">
            <div class="section-head"><i class="fas fa-image"></i> Evidentiary Photos</div>
            <div class="section-body">
                <?php if (!empty($images)): ?>
                    <div class="photo-grid">
                        <?php foreach ($images as $i => $img): ?>
                            <div class="photo-cell">
                                <?php if (!empty($img['is_video'])): ?>
                                    <video src="<?php echo BASE_URL . htmlspecialchars($img['image_path']); ?>" muted playsinline preload="metadata"></video>
                                <?php else: ?>
                                    <img src="<?php echo BASE_URL . htmlspecialchars($img['image_path']); ?>" alt="Evidentiary photo <?php echo $i + 1; ?>">
                                <?php endif; ?>
                                <div class="cap">Photo <?php echo $i + 1; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:#9ca3af; font-size:10px;">No photos submitted with this report.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Geographic Location ===== -->
        <div class="section">
            <div class="section-head"><i class="fas fa-map-pin"></i> Geographic Location</div>
            <div class="section-body">
                <?php if ($hasCoords): ?>
                    <table class="data">
                        <tbody>
                            <tr><th>GPS Coordinates</th><td><?php echo number_format((float)$report['latitude'], 6); ?>, <?php echo number_format((float)$report['longitude'], 6); ?></td></tr>
                            <?php if (!empty($report['location_address'])): ?>
                            <tr><th>Location Address</th><td><?php echo htmlspecialchars($report['location_address']); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color:#9ca3af; font-size:10px;">No location data available.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Investigation Notes ===== -->
        <?php if (!empty($notes)): ?>
        <div class="section">
            <div class="section-head"><i class="fas fa-sticky-note"></i> Investigation Notes</div>
            <div class="section-body">
                <?php foreach ($notes as $note): ?>
                    <div class="note-item">
                        <p class="n-text"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                        <p class="n-meta"><?php echo htmlspecialchars($note['user_name'] ?? 'System'); ?> &middot; <?php echo date('M d, Y h:i A', strtotime($note['created_at'])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== Resolution Evidence ===== -->
        <?php if (!empty($resolution_evidence)): ?>
        <div class="section">
            <div class="section-head"><i class="fas fa-check-circle"></i> Resolution Evidence</div>
            <div class="section-body">
                <div class="photo-grid">
                    <?php foreach ($resolution_evidence as $i => $ev): ?>
                        <div class="photo-cell">
                            <?php if (!empty($ev['is_video'])): ?>
                                <video src="<?php echo BASE_URL . htmlspecialchars($ev['image_path']); ?>" muted playsinline preload="metadata"></video>
                            <?php else: ?>
                                <img src="<?php echo BASE_URL . htmlspecialchars($ev['image_path']); ?>" alt="Resolution evidence <?php echo $i + 1; ?>">
                            <?php endif; ?>
                            <div class="cap">Evidence <?php echo $i + 1; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p style="font-size:9px; color:#6b7280; margin-top:6px;">
                    <?php echo htmlspecialchars($resolution_evidence[0]['uploaded_by_name'] ?? 'MENRO'); ?> &middot; uploaded on <?php echo date('M d, Y', strtotime($resolution_evidence[0]['created_at'] ?? 'now')); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== Escalation ===== -->
        <?php if (!empty($escalation)): ?>
        <div class="section">
            <div class="section-head"><i class="fas fa-arrow-up"></i> Escalation Record</div>
            <div class="section-body">
                <table class="data">
                    <tbody>
                        <tr><th>Escalated By</th><td><?php echo htmlspecialchars($escalation['escalated_by_name'] ?? '—'); ?></td></tr>
                        <tr><th>Escalated On</th><td><?php echo date('M d, Y h:i A', strtotime($escalation['escalated_at'])); ?></td></tr>
                        <tr><th>Status</th><td><?php echo htmlspecialchars(ucfirst($escalation['status'] ?? 'pending')); ?></td></tr>
                        <tr><th>Reason</th><td><?php echo nl2br(htmlspecialchars($escalation['reason'] ?? '—')); ?></td></tr>
                        <?php if (!empty($escalation['remarks'])): ?>
                        <tr><th>Remarks</th><td><?php echo nl2br(htmlspecialchars($escalation['remarks'])); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
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
            <span><?php echo htmlspecialchars($systemName); ?> &middot; Web-Based Environmental Reporting System</span>
        </footer>
        <div class="report-footer-note"><?php echo htmlspecialchars($footerNote); ?></div>
    </div>

    <?php if (!empty($_GET['autoprint'])): ?>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 700);
        });
    </script>
    <?php endif; ?>
</body>
</html>