<?php
// views/shared/manage_report.php - COMPLETE FULL-PAGE MANAGEMENT VIEW
// WITH UNDER REVIEW STATUS SUPPORT
// Action buttons shown based on $view_data flags from controller

if (!isset($view_data) || empty($view_data)) {
    header('Location: ' . BASE_URL . 'index.php?page=dashboard');
    exit();
}

// Extract data
$report = $view_data['report'];
$images = $view_data['images'];
$notes = $view_data['notes'];
$resolution_evidence = $view_data['resolution_evidence'];
$escalation = $view_data['escalation'];
$user_role = $view_data['user_role'];
$can_verify = $view_data['can_verify'];
$can_reject = $view_data['can_reject'];
$can_resolve = $view_data['can_resolve'];
$can_escalate = $view_data['can_escalate'];
$can_approve_escalation = $view_data['can_approve_escalation'];
$can_reject_escalation = $view_data['can_reject_escalation'];
$can_reclassify = $view_data['can_reclassify'];
$show_notes = $view_data['show_notes'];

// Generate CSRF token
$csrf_token = InputSanitizer::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Report - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: #F5FBF6; overflow-x: hidden; }

        /* ===== RESPONSIVE SIDEBAR ===== */
        @media (max-width: 768px) {
            .ml-72 { margin-left: 0 !important; width: 100%; padding: 0; }
        }

        /* ===== CONTAINER ===== */
        .main-container { max-width: 1280px; margin: 0 auto; padding: 1rem; }
        @media (min-width: 640px) { .main-container { padding: 1.5rem; } }
        @media (min-width: 768px) { .main-container { padding: 2rem; } }

        /* ===== CARDS ===== */
        .card { background: white; border-radius: 1rem; border: 1px solid rgba(16,163,127,0.08); padding: 1.25rem; margin-bottom: 1.25rem; transition: all 0.25s ease; }
        .card:hover { border-color: rgba(16,163,127,0.15); box-shadow: 0 4px 16px -4px rgba(16,163,127,0.08); }
        @media (min-width: 640px) { .card { padding: 1.5rem; } }
        .card-header { font-weight: 700; font-size: 0.85rem; color: #4b5563; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .card-header i { color: #10A37F; }

        /* ===== BUTTONS ===== */
        .btn-primary { background: linear-gradient(135deg, #10A37F, #0D8568); color: white; border: none; padding: 0.5rem 1.25rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.25s ease; box-shadow: 0 2px 8px rgba(16,163,127,0.18); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(16,163,127,0.3); }
        .btn-primary:active { transform: translateY(0); }
        .btn-secondary { background: white; border: 1.5px solid #10A37F; color: #10A37F; padding: 0.5rem 1.25rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.25s ease; }
        .btn-secondary:hover { background: #E8F5F0; border-color: #0D8568; color: #0D8568; }
        .btn-danger { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; border: none; padding: 0.5rem 1.25rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.25s ease; box-shadow: 0 2px 8px rgba(220,38,38,0.18); }
        .btn-danger:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(220,38,38,0.3); }
        .btn-danger:active { transform: translateY(0); }
        .btn-warning { background: linear-gradient(135deg, #D97706, #B45309); color: white; border: none; padding: 0.5rem 1.25rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.25s ease; box-shadow: 0 2px 8px rgba(217,119,6,0.18); }
        .btn-warning:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(217,119,6,0.3); }
        .btn-warning:active { transform: translateY(0); }
        .btn-success { background: linear-gradient(135deg, #10A37F, #0D8568); color: white; border: none; padding: 0.5rem 1.25rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.25s ease; box-shadow: 0 2px 8px rgba(16,163,127,0.18); }
        .btn-success:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(16,163,127,0.3); }
        .btn-success:active { transform: translateY(0); }
        .btn-indigo { background: linear-gradient(135deg, #10A37F, #0D8568); color: white; border: none; padding: 0.5rem 1.25rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.25s ease; box-shadow: 0 2px 8px rgba(16,163,127,0.18); }
        .btn-indigo:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(16,163,127,0.3); }
        .btn-indigo:active { transform: translateY(0); }

        /* ===== LAYOUT ===== */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media (max-width: 768px) { .two-col { grid-template-columns: 1fr; } }

        /* ===== PHOTO GRID ===== */
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
        .photo-grid img { width: 100%; height: 120px; object-fit: cover; border-radius: 0.75rem; cursor: pointer; border: 1px solid rgba(16,163,127,0.08); transition: transform 0.2s; }
        .photo-grid img:hover { transform: scale(1.02); }

        /* ===== MAP ===== */
        #map { height: 250px; border-radius: 0.75rem; border: 1px solid rgba(16,163,127,0.08); }

        /* ===== NOTES ===== */
        .note-item { background: #F5FBF6; padding: 12px; border-radius: 0.75rem; margin-bottom: 8px; border-left: 3px solid #10A37F; }

        /* ===== ACTION PANEL ===== */
        .action-panel { background: white; border-radius: 1rem; border: 1px solid rgba(16,163,127,0.08); padding: 1.25rem; margin-top: 1.25rem; }
        @media (min-width: 640px) { .action-panel { padding: 1.5rem; } }

        /* ===== INFO ROWS ===== */
        .info-label { color: #6b7280; font-size: 0.8rem; font-weight: 500; }
        .info-value { font-weight: 600; color: #1f2937; }
        .info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f3f4f6; }
        .info-row:last-child { border-bottom: none; }

        /* ===== FILE UPLOAD ===== */
        .file-upload-area { border: 2px dashed #d1d5db; border-radius: 0.75rem; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s; }
        .file-upload-area:hover { border-color: #10A37F; background: #E8F5F0; }

        /* ===== BADGES ===== */
        .badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .badge-verified { background: #dbeafe; color: #1e40af; }
        .badge-resident { background: #d1fae5; color: #065f46; }
        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .status-pending { background: #FEF3C7; color: #D97706; }
        .status-under_review { background: #DBEAFE; color: #1E40AF; }
        .status-verified { background: #DBEAFE; color: #1E40AF; }
        .status-in_progress { background: #D1FAE5; color: #065F46; }
        .status-escalated_pending { background: #FDE68A; color: #92400E; }
        .status-escalated { background: #FED7AA; color: #9A3412; }
        .status-resolved { background: #D1FAE5; color: #10A37F; }
        .status-rejected { background: #FEE2E2; color: #991B1B; }
        .status-closed { background: #F3F4F6; color: #6B7280; }
        .risk-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .risk-low { background: #D1FAE5; color: #065F46; }
        .risk-medium { background: #FEF3C7; color: #92400E; }
        .risk-high { background: #FEE2E2; color: #991B1B; }
        .risk-critical { background: #EDE9FE; color: #6B21A8; }

        /* ===== PRINT DROPDOWN ===== */
        .print-dropdown { position: relative; display: inline-block; }
        .print-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            box-shadow: 0 12px 36px -8px rgba(0,0,0,0.12), 0 4px 12px -4px rgba(0,0,0,0.06);
            min-width: 180px;
            z-index: 100;
            overflow: hidden;
            animation: dropdownIn 0.15s ease;
        }
        .print-dropdown-menu.open { display: block; }
        @keyframes dropdownIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .print-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            font-size: 0.82rem;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.15s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        .print-dropdown-item:hover {
            background: #E8F5F0;
            color: #10A37F;
        }
        .print-dropdown-item i {
            width: 16px;
            text-align: center;
            font-size: 0.85rem;
        }
        .print-dropdown-divider {
            height: 1px;
            background: #f3f4f6;
            margin: 0;
        }

        /* ===== PRINT STYLES ===== */
        @media print {
            /* Reset page */
            @page {
                size: A4;
                margin: 10mm 12mm;
            }

            /* Hide non-printable elements */
            #sidebar,
            #showSidebarBtn,
            .print-dropdown,
            .action-panel,
            .no-print,
            .print-dropdown-menu,
            button,
            form,
            a[href*="arrow-left"],
            .file-upload-area,
            [onclick],
            nav,
            .flash-message {
                display: none !important;
            }

            /* Reset layout */
            html, body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 9pt !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            .ml-72 {
                margin-left: 0 !important;
                width: 100% !important;
            }

            .main-container {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Compact cards */
            .card {
                border: 1px solid #e5e7eb !important;
                border-radius: 6px !important;
                padding: 8px 10px !important;
                margin-bottom: 6px !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }

            .card-header {
                font-size: 8pt !important;
                padding-bottom: 4px !important;
                margin-bottom: 6px !important;
            }

            /* Gradient header card - print-friendly */
            .bg-gradient-to-r {
                background: #10A37F !important;
                border-radius: 6px !important;
                padding: 8px 12px !important;
                margin-bottom: 6px !important;
            }
            .bg-gradient-to-r h2 {
                font-size: 12pt !important;
            }
            .bg-gradient-to-r .text-white\/80,
            .bg-gradient-to-r .bg-white\/20 {
                font-size: 7pt !important;
            }

            /* Branded header */
            .mb-6, .md\:mb-8 {
                margin-bottom: 6px !important;
            }
            .page-header, h1 {
                font-size: 13pt !important;
            }
            .text-gray-500 {
                font-size: 7pt !important;
            }

            /* Two column layout stays side by side */
            .two-col {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 6px !important;
            }

            /* Info rows */
            .info-row {
                padding: 2px 0 !important;
                font-size: 8pt !important;
            }
            .info-label { font-size: 7pt !important; }
            .info-value { font-size: 8pt !important; }

            /* Photos - smaller for print */
            .photo-grid {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)) !important;
                gap: 4px !important;
            }
            .photo-grid img {
                height: 60px !important;
                border-radius: 4px !important;
            }

            /* Map */
            #map {
                height: 120px !important;
                border-radius: 4px !important;
            }

            /* Notes */
            .note-item {
                padding: 4px 6px !important;
                margin-bottom: 3px !important;
                font-size: 7pt !important;
                border-radius: 3px !important;
            }

            /* Badges */
            .badge, .status-badge, .risk-badge, .severity-badge {
                font-size: 6pt !important;
                padding: 2px 6px !important;
            }

            /* Prevent page breaks inside cards */
            .card, .two-col, .note-item {
                page-break-inside: avoid;
            }

            /* Force single page by scaling if needed */
            .main-container > * {
                page-break-inside: avoid;
            }

            /* Print watermark footer */
            .print-footer {
                display: block !important;
                text-align: center;
                font-size: 7pt;
                color: #9CA3AF;
                border-top: 1px solid #e5e7eb;
                padding-top: 4px;
                margin-top: 8px;
            }
        }

        /* Hide print footer on screen */
        .print-footer { display: none; }

        /* PDF generating overlay */
        .pdf-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .pdf-overlay-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            max-width: 320px;
            width: 90%;
        }
        .pdf-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e5e7eb;
            border-top-color: #10A37F;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<div class="ml-72 min-h-screen">
    <div class="main-container">

        <!-- Flash Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 text-sm flex items-center gap-2">
                <i class="fas fa-check-circle text-green-500"></i>
                <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 text-sm flex items-center gap-2">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
        <?php endif; ?>

        <!-- ===== BRANDED HEADER ===== -->
        <div class="mb-6 md:mb-8">
            <div class="flex items-center space-x-2 mb-2">
                <div class="w-7 h-7 md:w-8 md:h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clipboard-check text-[#10A37F] text-xs md:text-sm"></i>
                </div>
                <span class="text-[10px] md:text-xs uppercase tracking-wider text-[#10A37F] font-semibold">Report Management</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                <div>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-bold text-gray-800">Manage Report</h1>
                    <p class="text-gray-500 text-xs md:text-sm mt-0.5 md:mt-1">Review, verify, and manage environmental report details</p>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <div class="print-dropdown">
                        <button onclick="togglePrintMenu()" class="bg-white border border-gray-200 hover:border-[#10A37F] hover:bg-[#E8F5F0] text-gray-600 hover:text-[#10A37F] px-3 md:px-4 py-2 rounded-xl transition-all flex items-center gap-2 text-xs md:text-sm font-medium">
                            <i class="fas fa-print"></i>
                            <span>Print</span>
                            <i class="fas fa-chevron-down text-[10px] ml-0.5"></i>
                        </button>
                        <div id="printDropdownMenu" class="print-dropdown-menu">
                            <button class="print-dropdown-item" onclick="handlePrint()">
                                <i class="fas fa-print"></i>
                                <span>Print Report</span>
                            </button>
                            <div class="print-dropdown-divider"></div>
                            <button class="print-dropdown-item" onclick="handleDownloadPDF()">
                                <i class="fas fa-file-pdf"></i>
                                <span>Download PDF</span>
                            </button>
                        </div>
                    </div>
                    <a href="<?php echo ($user_role == 'admin') ? BASE_URL . 'index.php?page=all-reports' : BASE_URL . 'index.php?page=verify-reports'; ?>"
                       class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 md:px-4 py-2 rounded-xl transition-all flex items-center gap-2 text-xs md:text-sm font-medium">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- ===== GRADIENT HEADER CARD ===== -->
        <div class="bg-gradient-to-r from-[#10A37F] to-[#0D8568] rounded-2xl shadow-xl overflow-hidden mb-6 md:mb-8">
            <div class="px-4 md:px-6 py-4 md:py-6">
                <div class="flex flex-wrap justify-between items-start gap-4">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 md:w-6 md:h-6 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-file-alt text-white/80 text-[10px] md:text-xs"></i>
                            </div>
                            <span class="text-white/80 text-[10px] md:text-xs uppercase tracking-wider font-semibold">Report #<?php echo str_pad($report['id'], 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <h2 class="text-xl md:text-2xl font-bold text-white"><?php echo htmlspecialchars($report['title']); ?></h2>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-white/20 rounded-lg text-white text-[10px] md:text-xs">
                                <i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y \a\t h:i A', strtotime($report['created_at'])); ?>
                            </span>
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-white/20 rounded-lg text-white text-[10px] md:text-xs">
                                <i class="far fa-clock"></i> <?php echo timeAgo($report['created_at']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <?php echo getStatusBadge($report['status']); ?>
                        <?php echo getRiskBadge($report['risk_level']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Two Columns: Reporter Details + Metadata -->
        <div class="two-col">
            <div class="card">
                <div class="card-header"><i class="fas fa-user"></i> Reporter Details</div>
                <div class="space-y-2 text-sm">
                    <div class="info-row"><span class="info-label">Full Name</span><span class="info-value"><?php echo htmlspecialchars($report['user_name'] ?? 'Unknown'); ?></span></div>
                    <div class="info-row"><span class="info-label">Account</span><span class="info-value">
                        <?php if ($report['is_resident'] ?? 0): ?>
                            <span class="badge badge-resident"><i class="fas fa-check-circle mr-1"></i> Verified Resident</span>
                        <?php else: ?>
                            <span class="badge badge-verified"><i class="fas fa-user mr-1"></i> Non-Resident</span>
                        <?php endif; ?>
                    </span></div>
                    <div class="info-row"><span class="info-label">Contact</span><span class="info-value"><?php echo htmlspecialchars($report['contact_number'] ?? ''); ?></span></div>
                    <div class="info-row"><span class="info-label">Address</span><span class="info-value"><?php echo htmlspecialchars($report['barangay_name']); ?></span></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-tags"></i> Report Metadata</div>
                <div class="space-y-2 text-sm">
                    <div class="info-row"><span class="info-label">Category</span><span class="info-value"><?php echo htmlspecialchars($report['category_name']); ?></span></div>
                    <div class="info-row"><span class="info-label">Barangay</span><span class="info-value"><?php echo htmlspecialchars($report['barangay_name']); ?></span></div>
                    <div class="info-row"><span class="info-label">Risk Level</span><span class="info-value"><?php echo ucfirst($report['risk_level']); ?></span></div>
                    <div class="info-row"><span class="info-label">Impact Modifier</span><span class="info-value">
                        <?php
                        $imp = $report['impact_modifier'] ?? 0;
                        if ($imp == 4) echo '<span class="text-red-600 font-bold">Severe (+4)</span>';
                        elseif ($imp == 2) echo '<span class="text-amber-600 font-bold">Moderate (+2)</span>';
                        else echo '<span class="text-emerald-600 font-bold">Localized (+0)</span>';
                        ?>
                    </span></div>
                    <div class="info-row"><span class="info-label">Severity Score</span><span class="info-value font-bold <?php echo ($report['severity_score'] ?? 0) >= 16 ? 'text-red-600' : (($report['severity_score'] ?? 0) >= 11 ? 'text-orange-600' : (($report['severity_score'] ?? 0) >= 6 ? 'text-amber-600' : 'text-emerald-600')); ?>"><?php echo $report['severity_score'] ?? 0; ?></span></div>
                    <div class="info-row"><span class="info-label">Classification</span><span class="info-value"><?php echo $report['decision_classification'] ?? 'Pending'; ?></span></div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="card">
            <div class="card-header"><i class="fas fa-align-left"></i> Resident's Description</div>
            <div class="text-gray-700 leading-relaxed whitespace-pre-line"><?php echo nl2br(htmlspecialchars($report['description'])); ?></div>
        </div>

        <!-- Two Columns: Photo + Map -->
        <div class="two-col">
            <div class="card">
                <div class="card-header"><i class="fas fa-image"></i> Evidentiary Photo</div>
                <?php if (!empty($images)): ?>
                    <div class="photo-grid">
                        <?php foreach ($images as $img): ?>
                            <img src="<?php echo BASE_URL . $img['image_path']; ?>" onclick="window.open('<?php echo BASE_URL . $img['image_path']; ?>','_blank')" alt="Report photo">
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($images) > 1): ?>
                        <p class="text-xs text-gray-400 mt-2">Click any photo to view full size.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-gray-400 text-sm">No photos submitted.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-map-pin"></i> Geographic Location</div>
                <?php if ($report['latitude'] && $report['longitude'] && $report['latitude'] != 0 && $report['longitude'] != 0): ?>
                    <div id="map"></div>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-location-dot mr-1 text-emerald-600"></i>
                        GPS: <?php echo number_format($report['latitude'], 6); ?>, <?php echo number_format($report['longitude'], 6); ?>
                        &nbsp; <a href="https://www.google.com/maps?q=<?php echo $report['latitude']; ?>,<?php echo $report['longitude']; ?>" target="_blank" class="text-emerald-600 hover:underline">Open in Google Maps</a>
                    </p>
                    <?php if (!empty($report['location_address'])): ?>
                        <p class="text-sm text-gray-600 mt-1"><i class="fas fa-address-card mr-1 text-gray-400"></i> <?php echo htmlspecialchars($report['location_address']); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-gray-400 text-sm">No location data available.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notes -->
        <?php if ($show_notes): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-sticky-note"></i> Investigation Notes</div>
            <div class="max-h-60 overflow-y-auto mb-4 space-y-2">
                <?php if (!empty($notes)): ?>
                    <?php foreach ($notes as $note): ?>
                        <div class="note-item">
                            <p class="text-sm"><?php echo htmlspecialchars($note['note']); ?></p>
                            <p class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($note['user_name']); ?> • <?php echo date('M d, h:i A', strtotime($note['created_at'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-400 text-sm text-center py-2">No notes yet.</p>
                <?php endif; ?>
            </div>
            <!-- Quick note form -->
            <?php if ($user_role == 'barangay_official' || $user_role == 'admin'): ?>
            <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="flex gap-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                <input type="text" name="note" placeholder="Add investigation note..." class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none">
                <button type="submit" class="btn-primary">Add Note</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 🛠️ ACTION & MANAGEMENT PANEL -->
        <div class="action-panel">
            <div class="card-header"><i class="fas fa-tools"></i> ACTION & MANAGEMENT PANEL</div>

            <?php if ($user_role == 'barangay_official'): ?>
                <!-- Step 1: Reclassify Risk Level & Take Action (only when in progress) -->
                <?php if ($can_reclassify): ?>
                <div class="mb-6">
                    <h3 class="text-sm font-bold text-gray-700 mb-3 pb-2 border-b-2 border-gray-200">
                        <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded">1</span> Reclassify Risk Level (In Progress only):
                    </h3>
                    <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="reclassify_impact">
                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                        
                        <div class="flex items-center gap-3 flex-wrap">
                            <label class="text-sm font-semibold text-gray-700">Reclassify Risk:</label>
                            <select name="new_impact" class="border-2 border-gray-300 rounded-lg px-4 py-2 text-sm font-semibold focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none">
                                <option value="0" <?php echo ($report['impact_modifier'] == 0) ? 'selected' : ''; ?>>🟢 Low Risk (Localized)</option>
                                <option value="2" <?php echo ($report['impact_modifier'] == 2) ? 'selected' : ''; ?>>🟡 Medium Risk (Moderate)</option>
                                <option value="4" <?php echo ($report['impact_modifier'] == 4) ? 'selected' : ''; ?>>🔴 High Risk (Severe)</option>
                            </select>
                            
                            <div class="flex-1 min-w-[300px]">
                                <input type="text" name="reclassify_reason" placeholder="Reason for risk level change..." class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none" required>
                            </div>
                            
                            <button type="submit" class="btn-indigo px-6">
                                <i class="fas fa-save mr-2"></i> Update Risk
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Step 2: PRIMARY RESOLUTION ACTIONS (only when under_review) -->
                <div class="mb-4">
                    <h3 class="text-sm font-bold text-gray-700 mb-3 pb-2 border-b-2 border-[#10A37F]">
                        PRIMARY RESOLUTION ACTIONS:
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3" style="direction: ltr;">
                        <?php if ($can_verify): ?>
                            <!-- Verify Report -->
                            <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="verify_report">
                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                <button type="submit" class="w-full btn-primary py-3 md:py-4 text-sm md:text-base font-bold shadow-lg hover:shadow-xl">
                                    <i class="fas fa-check mr-2"></i> Verify Report
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($can_escalate): ?>
                            <!-- Escalate to MENRO -->
                            <button onclick="document.getElementById('escalateFormSection').style.display='block';this.style.display='none'" class="w-full btn-warning py-3 md:py-4 text-sm md:text-base font-bold shadow-lg hover:shadow-xl">
                                <i class="fas fa-share mr-2"></i> Escalate to MENRO
                            </button>
                            
                            <div id="escalateFormSection" style="display:none;" class="col-span-full p-4 bg-amber-50 border-2 border-amber-200 rounded-xl mt-2">
                                <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="space-y-3">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="escalate_report">
                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                    
                                    <label class="block text-sm font-semibold text-gray-700">Justification for Escalation:</label>
                                    <textarea name="escalation_reason" rows="3" class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 text-sm focus:border-[#10A37F] focus:ring-2 focus:ring-[#10A37F]/20 outline-none" placeholder="Explain why this report needs to be escalated to MENRO..." required></textarea>
                                    
                                    <div class="flex gap-3">
                                        <button type="submit" class="btn-warning px-6 py-2">
                                            <i class="fas fa-paper-plane mr-2"></i> Confirm Escalation
                                        </button>
                                        <button type="button" onclick="document.getElementById('escalateFormSection').style.display='none';document.querySelector('[onclick*=escalateFormSection]').style.display=''" class="btn-secondary px-6 py-2">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>

                        <?php if ($can_reject): ?>
                            <!-- Reject Report -->
                            <button onclick="document.getElementById('rejectFormSection').style.display='block';this.style.display='none'" class="w-full btn-danger py-3 md:py-4 text-sm md:text-base font-bold shadow-lg hover:shadow-xl">
                                <i class="fas fa-times-circle mr-2"></i> Reject Report
                            </button>
                            
                            <div id="rejectFormSection" style="display:none;" class="col-span-full p-4 bg-red-50 border-2 border-red-200 rounded-xl mt-2">
                                <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="space-y-3" onsubmit="return confirm('Are you sure you want to reject this report?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="reject_report">
                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                    
                                    <label class="block text-sm font-semibold text-gray-700">Reason for Rejection:</label>
                                    <textarea name="rejection_reason" rows="3" class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 text-sm focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none" placeholder="Provide a clear reason for rejecting this report..." required></textarea>
                                    
                                    <div class="flex gap-3">
                                        <button type="submit" class="btn-danger px-6 py-2">
                                            <i class="fas fa-ban mr-2"></i> Confirm Rejection
                                        </button>
                                        <button type="button" onclick="document.getElementById('rejectFormSection').style.display='none';document.querySelector('[onclick*=rejectFormSection]').style.display=''" class="btn-secondary px-6 py-2">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>

                        <?php if ($can_resolve): ?>
                            <!-- Mark as Resolved (moved to right) -->
                            <button onclick="document.getElementById('resolveFormSection').style.display='block';this.style.display='none'" class="w-full btn-success py-3 md:py-4 text-sm md:text-base font-bold shadow-lg hover:shadow-xl md:col-start-3">
                                <i class="fas fa-check-double mr-2"></i> Mark as Resolved
                            </button>
                            
                            <div id="resolveFormSection" style="display:none;" class="col-span-full p-4 bg-green-50 border-2 border-green-200 rounded-xl mt-2">
                                <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" enctype="multipart/form-data" class="space-y-3">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="resolve_report">
                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                    
                                    <div class="grid md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Resolution Photo (Required):</label>
                                            <div class="file-upload-area" onclick="document.getElementById('resImage').click()">
                                                <i class="fas fa-camera text-3xl text-gray-400 mb-2 block"></i>
                                                <span class="text-sm text-gray-600 font-medium">Click to upload photo proof</span>
                                                <input type="file" name="resolution_image" id="resImage" accept="image/*" style="display:none;" required onchange="document.getElementById('resPreview').textContent=this.files[0]?this.files[0].name:'No file selected'">
                                                <span id="resPreview" class="text-xs text-gray-500 block mt-2">JPG, PNG, GIF (Max 5MB)</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Resolution Notes (Optional):</label>
                                            <textarea name="resolution_note" rows="5" class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 text-sm focus:border-[#10A37F] focus:ring-2 focus:ring-[#10A37F]/20 outline-none" placeholder="Describe the actions taken to resolve this issue..."></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="flex gap-3">
                                        <button type="submit" class="btn-success px-6 py-2">
                                            <i class="fas fa-check mr-2"></i> Confirm Resolution
                                        </button>
                                        <button type="button" onclick="document.getElementById('resolveFormSection').style.display='none';document.querySelector('[onclick*=resolveFormSection]').style.display=''" class="btn-secondary px-6 py-2">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($user_role == 'admin'): ?>
                <!-- Admin Actions -->
                <?php if ($can_approve_escalation): ?>
                    <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="inline">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="approve_escalation">
                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                        <button type="submit" class="btn-success mr-2">✅ Approve Escalation</button>
                    </form>
                    <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="inline" onsubmit="return confirm('Reject this escalation? Provide a reason below.')">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="reject_escalation">
                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                        <div class="flex items-center gap-2 mt-2">
                            <input type="text" name="rejection_reason" placeholder="Reason for rejection..." class="border border-gray-300 rounded-lg px-4 py-2 text-sm flex-1" required>
                            <button type="submit" class="btn-danger">❌ Reject Escalation</button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($can_resolve): ?>
                    <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" enctype="multipart/form-data" class="mt-2">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="resolve_report">
                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="flex-1">
                                <div class="file-upload-area" onclick="document.getElementById('resImageAdmin').click()">
                                    <i class="fas fa-camera text-2xl text-gray-400 mb-1 block"></i>
                                    <span class="text-sm text-gray-500">Upload resolution photo</span>
                                    <input type="file" name="resolution_image" id="resImageAdmin" accept="image/*" style="display:none;" onchange="document.getElementById('resPreviewAdmin').textContent=this.files[0]?this.files[0].name:'No file selected'">
                                    <span id="resPreviewAdmin" class="text-xs text-gray-400 block">JPG, PNG, GIF (Max 5MB)</span>
                                </div>
                            </div>
                            <div class="flex-1">
                                <input type="text" name="resolution_note" placeholder="Optional note..." class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm">
                            </div>
                            <button type="submit" class="btn-success">✅ Mark Resolved</button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($can_reclassify): ?>
                    <div class="mt-2">
                        <button onclick="document.getElementById('reclassifyAdminForm').style.display='block'" class="btn-indigo">🔄 Reclassify Impact (Admin Override)</button>
                        <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" id="reclassifyAdminForm" style="display:none;" class="mt-2 p-4 border rounded-lg bg-gray-50">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="reclassify_impact">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <p class="text-sm text-gray-600 mb-2">Select new impact level:</p>
                            <div class="flex gap-4 mb-2">
                                <label><input type="radio" name="new_impact" value="0" checked> Localized (+0)</label>
                                <label><input type="radio" name="new_impact" value="2"> Moderate (+2)</label>
                                <label><input type="radio" name="new_impact" value="4"> Severe (+4)</label>
                            </div>
                            <input type="text" name="reclassify_reason" placeholder="Reason for reclassification..." class="border border-gray-300 rounded-lg px-4 py-2 text-sm w-full mb-2" required>
                            <button type="submit" class="btn-indigo">Confirm Reclassification</button>
                            <button type="button" onclick="this.parentElement.style.display='none'" class="btn-secondary ml-2">Cancel</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Escalation status display -->
            <?php if ($report['status'] == 'escalated_pending'): ?>
                <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                    <p class="text-sm text-amber-800"><i class="fas fa-hourglass-half mr-1"></i> This report is pending MENRO approval.</p>
                    <?php if (!empty($escalation['escalation_reason'])): ?>
                        <p class="text-xs text-amber-700 mt-1"><strong>Justification:</strong> <?php echo htmlspecialchars($escalation['escalation_reason']); ?></p>
                    <?php endif; ?>
                </div>
            <?php elseif ($report['status'] == 'escalated'): ?>
                <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-800"><i class="fas fa-check-circle mr-1"></i> This report is under MENRO supervision.</p>
                </div>
            <?php elseif ($report['status'] == 'resolved'): ?>
                <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-800"><i class="fas fa-check-double mr-1"></i> This report has been resolved.</p>
                    <?php if (!empty($report['resolved_at'])): ?>
                        <p class="text-xs text-green-700">Resolved on: <?php echo date('F d, Y h:i A', strtotime($report['resolved_at'])); ?></p>
                    <?php endif; ?>
                </div>
            <?php elseif ($report['status'] == 'rejected'): ?>
                <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-800"><i class="fas fa-times-circle mr-1"></i> This report was rejected.</p>
                    <?php if (!empty($report['rejection_reason'])): ?>
                        <p class="text-xs text-red-700 mt-1"><strong>Reason:</strong> <?php echo htmlspecialchars($report['rejection_reason']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Print footer (only visible when printing) -->
    <div class="print-footer">
        <strong>Sierra Environmental Reporting System</strong> — Report #<?php echo str_pad($report['id'], 6, '0', STR_PAD_LEFT); ?>
        &nbsp;|&nbsp; Generated: <?php echo date('F d, Y \a\t h:i A'); ?>
        &nbsp;|&nbsp; This document is for official use only.
    </div>
</div>

<!-- html2canvas + jsPDF for PDF download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
// Initialize map
<?php if ($report['latitude'] && $report['longitude'] && $report['latitude'] != 0 && $report['longitude'] != 0): ?>
    var map = L.map('map').setView([<?php echo $report['latitude']; ?>, <?php echo $report['longitude']; ?>], 16);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '© OpenStreetMap',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);
    L.marker([<?php echo $report['latitude']; ?>, <?php echo $report['longitude']; ?>], {
        icon: L.divIcon({
            html: '<div style="background:#10A37F;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.3);"><i class="fas fa-map-pin" style="color:white;font-size:14px;"></i></div>',
            iconSize: [32, 32]
        })
    }).addTo(map);
<?php endif; ?>

// Helper to display selected file name
document.querySelectorAll('.file-upload-area input[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
        const preview = this.parentElement.querySelector('span:last-child');
        if (preview) preview.textContent = this.files[0] ? this.files[0].name : 'No file selected';
    });
});

// ===== PRINT DROPDOWN =====
function togglePrintMenu() {
    const menu = document.getElementById('printDropdownMenu');
    menu.classList.toggle('open');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.querySelector('.print-dropdown');
    const menu = document.getElementById('printDropdownMenu');
    if (dropdown && menu && !dropdown.contains(e.target)) {
        menu.classList.remove('open');
    }
});

// ===== PRINT HANDLER =====
function handlePrint() {
    document.getElementById('printDropdownMenu').classList.remove('open');
    window.print();
}

// ===== PDF DOWNLOAD (FIXED: removes script tags from clone) =====
function handleDownloadPDF() {
    document.getElementById('printDropdownMenu').classList.remove('open');

    // Show loading overlay
    const overlay = document.createElement('div');
    overlay.className = 'pdf-overlay';
    overlay.id = 'pdfOverlay';
    overlay.innerHTML = `
        <div class="pdf-overlay-card">
            <div class="pdf-spinner"></div>
            <p style="font-weight:700;color:#1f2937;font-size:0.95rem;margin-bottom:4px;">Generating PDF</p>
            <p style="color:#6b7280;font-size:0.8rem;">Please wait a moment...</p>
        </div>
    `;
    document.body.appendChild(overlay);

    // 1. Clone the main container
    const source = document.querySelector('.main-container');
    const clone = source.cloneNode(true);

    // 2. REMOVE ALL SCRIPT TAGS FROM THE CLONE (prevents raw code from appearing)
    clone.querySelectorAll('script').forEach(el => el.remove());

    // 3. Remove non-printable elements from clone
    const removeSelectors = [
        '.print-dropdown', '.action-panel', 'form', 'button',
        '[onclick]', '.flash-message', '.file-upload-area',
        'a.bg-gray-100', 'select', 'input', 'textarea'
    ];
    removeSelectors.forEach(sel => {
        clone.querySelectorAll(sel).forEach(el => el.remove());
    });

    // 4. Show print footer in clone
    const footer = clone.querySelector('.print-footer');
    if (footer) footer.style.cssText = 'display:block; text-align:center; font-size:6.5pt; color:#9CA3AF; border-top:1px solid #e5e7eb; padding-top:4px; margin-top:6px;';

    // 5. Create off-screen render container with compact width
    const wrapper = document.createElement('div');
    wrapper.id = 'pdf-render-container';
    wrapper.style.cssText = 'position:fixed; left:-9999px; top:0; width:680px; background:white; z-index:-1; font-family:Manrope,sans-serif; font-size:8.5pt; color:#1f2937; padding:12px;';

    // 6. Apply compact styles to cloned elements
    // Cards
    clone.querySelectorAll('.card').forEach(c => {
        c.style.cssText = 'background:white; border:1px solid #e5e7eb; border-radius:6px; padding:7px 9px; margin-bottom:5px; box-shadow:none;';
    });
    clone.querySelectorAll('.card-header').forEach(h => {
        h.style.cssText = 'font-weight:700; font-size:8pt; color:#4b5563; border-bottom:1px solid #e5e7eb; padding-bottom:3px; margin-bottom:5px; display:flex; align-items:center; gap:5px;';
    });
    // Two-col layout
    clone.querySelectorAll('.two-col').forEach(g => {
        g.style.cssText = 'display:grid; grid-template-columns:1fr 1fr; gap:5px;';
    });
    // Info rows
    clone.querySelectorAll('.info-row').forEach(r => {
        r.style.cssText = 'display:flex; justify-content:space-between; padding:1px 0; border-bottom:1px solid #f3f4f6; font-size:7.5pt;';
    });
    clone.querySelectorAll('.info-label').forEach(l => { l.style.fontSize = '7pt'; });
    clone.querySelectorAll('.info-value').forEach(v => { v.style.fontSize = '7.5pt'; });
    // Photo grid
    clone.querySelectorAll('.photo-grid').forEach(pg => {
        pg.style.cssText = 'display:grid; grid-template-columns:repeat(auto-fill,minmax(65px,1fr)); gap:3px;';
    });
    clone.querySelectorAll('.photo-grid img').forEach(img => {
        img.style.cssText = 'width:100%; height:50px; object-fit:cover; border-radius:3px; border:1px solid #e5e7eb;';
    });
    // Map - replace with static text
    const mapEl = clone.querySelector('#map');
    if (mapEl) {
        mapEl.id = 'map-pdf-placeholder';
        mapEl.style.cssText = 'height:80px; border-radius:4px; border:1px solid #e5e7eb; background:#f0f4f0; display:flex; align-items:center; justify-content:center; color:#6b7280; font-size:7pt;';
        mapEl.innerHTML = `<div style="text-align:center;"><i class="fas fa-map-marker-alt" style="color:#10A37F; font-size:14px; display:block; margin-bottom:2px;"></i>GPS: <?php echo number_format($report['latitude'], 6); ?>, <?php echo number_format($report['longitude'], 6); ?><?php if (!empty($report['location_address'])): ?><br><span style="font-size:6pt;"><?php echo htmlspecialchars($report['location_address']); ?></span><?php endif; ?></div>`;
    }
    // Notes
    clone.querySelectorAll('.note-item').forEach(n => {
        n.style.cssText = 'background:#F5FBF6; padding:3px 5px; border-radius:3px; margin-bottom:2px; border-left:2px solid #10A37F; font-size:7pt;';
    });
    clone.querySelectorAll('.max-h-60').forEach(el => { el.style.maxHeight = 'none'; el.style.overflow = 'visible'; });
    // Badges
    clone.querySelectorAll('.badge, .status-badge, .risk-badge').forEach(b => {
        b.style.fontSize = '6pt'; b.style.padding = '1px 5px';
    });
    // Gradient header card
    const gradientCard = clone.querySelector('.bg-gradient-to-r');
    if (gradientCard) {
        gradientCard.style.cssText = 'background:linear-gradient(135deg,#10A37F,#0D8568); border-radius:6px; padding:7px 10px; margin-bottom:5px; color:white; overflow:hidden;';
        const h2 = gradientCard.querySelector('h2');
        if (h2) h2.style.fontSize = '11pt';
        gradientCard.querySelectorAll('.bg-white\\/20').forEach(el => {
            el.style.cssText = 'display:inline-flex; align-items:center; gap:3px; padding:1px 5px; background:rgba(255,255,255,0.2); border-radius:4px; color:white; font-size:6.5pt;';
        });
    }
    // Page header area
    const pageHeader = clone.querySelector('.mb-6');
    if (pageHeader) pageHeader.style.marginBottom = '5px';
    const h1 = clone.querySelector('h1');
    if (h1) h1.style.fontSize = '12pt';
    const subtitle = clone.querySelector('p.text-gray-500');
    if (subtitle) subtitle.style.fontSize = '7pt';
    // Description text
    clone.querySelectorAll('.leading-relaxed, .whitespace-pre-line').forEach(d => {
        d.style.cssText = 'font-size:7.5pt; line-height:1.3;';
    });

    // 7. Append clone to wrapper and add to body
    wrapper.appendChild(clone);
    document.body.appendChild(wrapper);

    // 8. Capture with html2canvas then scale to fit A4
    setTimeout(() => {
        html2canvas(clone, {
            scale: 2,
            useCORS: true,
            letterRendering: true,
            backgroundColor: '#ffffff',
            width: 680,
            windowWidth: 680,
            scrollY: 0,
            scrollX: 0
        }).then(canvas => {
            // A4 in mm: 210 x 297
            const marginX = 8, marginY = 6;
            const usableW = 210 - (marginX * 2); // 194mm
            const usableH = 297 - (marginY * 2); // 285mm

            const imgRatio = canvas.width / canvas.height;

            let pdfW = usableW;
            let pdfH = pdfW / imgRatio;

            // Scale down if taller than page
            if (pdfH > usableH) {
                pdfH = usableH;
                pdfW = pdfH * imgRatio;
            }

            // Center on page
            const offsetX = marginX + (usableW - pdfW) / 2;
            const offsetY = marginY;

            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('portrait', 'mm', 'a4');
            const imgData = canvas.toDataURL('image/jpeg', 0.92);
            pdf.addImage(imgData, 'JPEG', offsetX, offsetY, pdfW, pdfH);

            const reportId = '<?php echo str_pad($report['id'], 6, "0", STR_PAD_LEFT); ?>';
            const reportTitle = <?php echo json_encode($report['title']); ?>;
            const filename = `Report_${reportId}_${reportTitle.replace(/[^a-zA-Z0-9]/g, '_').substring(0, 30)}.pdf`;

            pdf.save(filename);
            cleanup();
        }).catch(err => {
            console.error('PDF capture error:', err);
            cleanup();
            alert('Failed to generate PDF. Please try the Print option instead.');
        });
    }, 300);

    function cleanup() {
        const c = document.getElementById('pdf-render-container');
        if (c) c.remove();
        const o = document.getElementById('pdfOverlay');
        if (o) o.remove();
    }
}
</script>

</body>
</html>