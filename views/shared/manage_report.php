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
$can_manage = $view_data['can_manage'];
$show_notes = $view_data['show_notes'];
$report_verified = $view_data['report_verified'] ?? false;
$note_templates = $view_data['note_templates'] ?? [];
$resolve_templates = $view_data['resolve_templates'] ?? [];
$escalate_templates = $view_data['escalate_templates'] ?? [];

// Generate CSRF token
$csrf_token = InputSanitizer::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="<?php echo BASE_URL; ?>assets/js/theme.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/theme.js'); ?>"></script>
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Manage Report - Sierra</title>
    <link href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/material-symbols.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/material-symbols.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/export-print.css'); ?>">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/map-layers.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/map-layers.js'); ?>"></script>
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: #F5FBF6; overflow-x: hidden; }

        /* ===== RESPONSIVE SIDEBAR ===== */
        @media (max-width: 768px) {
            .ml-72 { margin-left: 0 !important; width: 100%; padding: 0; }
        }

        /* ===== CONTAINER ===== */
        .main-container { max-width: 1280px; margin: 0 auto; padding: 1rem 1rem 13rem; }
        @media (min-width: 640px) { .main-container { padding: 1.5rem 1.5rem 13rem; } }
        @media (min-width: 768px) { .main-container { padding: 2rem 2rem 13rem; } }

        /* ===== CARDS ===== */
        .card { background: white; border-radius: 1rem; border: 1px solid #cbd5cf; padding: 1.25rem; margin-bottom: 1.25rem; transition: all 0.25s ease; }
        .card:hover { border-color: rgba(16,163,127,0.15); box-shadow: 0 4px 16px -4px rgba(16,163,127,0.08); }
        @media (min-width: 640px) { .card { padding: 1.5rem; } }
        .card-header { font-weight: 700; font-size: 0.85rem; color: var(--sierra-type-muted, #63746b); border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .card-header i { color: #10A37F; }

        .report-top-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .85rem;
            flex-wrap: wrap;
        }
        .report-back-btn {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            min-height: 42px;
            padding: .62rem 1rem;
            border-radius: 14px;
            background: #fff;
            border: 1px solid rgba(16, 163, 127, .12);
            color: var(--sierra-type-primary, #203b31);
            font-size: .82rem;
            font-weight: 800;
            box-shadow: 0 10px 24px -20px rgba(13, 133, 104, .45);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .report-back-btn:hover {
            transform: translateY(-1px);
            border-color: rgba(16, 163, 127, .35);
            box-shadow: 0 18px 32px -24px rgba(13, 133, 104, .55);
        }

.report-hero .status-badge,
        .report-hero .risk-badge {
            border: 1px solid rgba(255,255,255,.4);
            box-shadow: 0 10px 22px -18px rgba(15,23,42,.6);
        }
        @media (max-width: 640px) {
            .report-top-actions { align-items: stretch; }
            .report-top-actions .export-dropdown, .report-top-actions .btn-export-trigger, .report-back-btn { width: 100%; justify-content: center; }

}

        /* ===== BUTTONS ===== */

        /* ===== LAYOUT ===== */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media (max-width: 768px) { .two-col { grid-template-columns: 1fr; } }

        /* ===== PHOTO GRID ===== */
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
        .photo-grid img { width: 100%; height: 120px; object-fit: cover; border-radius: 0.75rem; cursor: pointer; border: 1px solid rgba(16,163,127,0.08); transition: transform 0.2s; }
        .photo-grid img:hover { transform: scale(1.02); }
        .photo-grid video { width: 100%; height: 120px; object-fit: cover; border-radius: 0.75rem; cursor: pointer; border: 1px solid rgba(16,163,127,0.08); background: #000; }
        .photo-grid video:hover { transform: scale(1.02); }
        .photo-card { position: relative; }
        .photo-grid-cell { display: flex; flex-direction: column; gap: 6px; }

/* ===== FULL-BLEED MEDIA CARDS (photos fill the whole card, no gaps) ===== */
        .card-bleed { padding: 0; overflow: hidden; }
        .card-bleed .card-header {
            margin-bottom: 0;
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .card-bleed .photo-grid { gap: 0; }
        .card-bleed .photo-grid img,
        .card-bleed .photo-grid video { border-radius: 0; border: none; }
        .card-bleed .photo-grid + p { margin-top: 0; padding: 0.8rem 1.25rem; }
        .card-bleed > .empty-state { padding: 2rem 1.25rem; }
        .card-bleed .photo-grid-cell { padding-bottom: 0; }
        /* ===== COUNT-RESPONSIVE PHOTO GRIDS (adapt to how many photos) ===== */
        .photo-grid.pg-1 { grid-template-columns: 1fr; }
        .photo-grid.pg-2 { grid-template-columns: repeat(2, 1fr); }
        .photo-grid.pg-3 { grid-template-columns: repeat(3, 1fr); }
        .photo-grid.pg-4 { grid-template-columns: repeat(4, 1fr); }
        .photo-grid.pg-1 img, .photo-grid.pg-1 video,
        .photo-grid.pg-1 .photo-card { grid-column: 1 / -1; height: auto; aspect-ratio: 16 / 9; }
        .photo-grid.pg-2 img, .photo-grid.pg-2 video { height: 230px; }
        .photo-grid.pg-3 img, .photo-grid.pg-3 video { height: 190px; }
        .photo-grid.pg-4 img, .photo-grid.pg-4 video { height: 160px; }
        .photo-grid.pg-5 { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
        @media (max-width: 768px) {
            .photo-grid.pg-2 img, .photo-grid.pg-2 video { height: 175px; }
            .photo-grid.pg-3 { grid-template-columns: repeat(2, 1fr); }
            .photo-grid.pg-4 { grid-template-columns: repeat(2, 1fr); }
            .photo-grid.pg-3 img, .photo-grid.pg-3 video { height: 150px; }
            .photo-grid.pg-4 img, .photo-grid.pg-4 video { height: 140px; }
        }
        @media (max-width: 480px) {
            .photo-grid.pg-2 { grid-template-columns: 1fr; }
            .photo-grid.pg-2 img, .photo-grid.pg-2 video { height: auto; aspect-ratio: 4 / 3; }
        }
        /* ===== MAP ===== */
        body.manage-report-page #map { height: 340px; border-radius: 0.75rem; border: 2px solid #94a39b !important; box-sizing: border-box; overflow: hidden; }

        /* ===== NOTES ===== */

.note-composer {
            display: flex;
            gap: .6rem;
            padding: .65rem;
            border-radius: 1rem;
            border: 1px solid rgba(16, 163, 127, .14);
            background: #fff;
            box-shadow: 0 12px 28px -24px rgba(13, 133, 104, .55);
        }
        .note-composer input {
            border: 0 !important;
            background: #F4F8F6;
            border-radius: 999px !important;
        }

        /* ===== ACTION PANEL ===== */
        .action-panel {
            position: fixed;
            left: 18rem;
            right: 0;
            bottom: 0;
            z-index: 1200;
            padding: .75rem 1rem;
            background: linear-gradient(to top, rgba(245, 251, 246, 1) 0%, rgba(245, 251, 246, .9) 80%, rgba(245, 251, 246, 0) 100%);
            pointer-events: none;
        }
        .action-panel-bar {
            pointer-events: auto;
            max-width: 1100px;
            max-height: min(48vh, 410px);
            margin: 0 auto;
            overflow-y: auto;
            background: #fff;
            backdrop-filter: blur(8px);
            border-radius: 1rem;
            border: 1px solid #cfe3d7;
            padding: .75rem 1rem;
            box-shadow: 0 12px 32px -18px rgba(18, 76, 60, .3);
        }
        .action-panel-bar .action-callout { margin-bottom:12px; padding:10px 12px; border-radius:10px; }
        .action-panel-bar .action-cards { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .action-panel-bar .action-card { min-width: 0; }
        .action-panel-bar .action-card:not(.action-card-btn) { background:#fff; border-color:#dce8e1; }
        .escalation-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; margin-top:auto; }
        .escalation-actions .action-btn { min-width:0; padding:10px 8px; font-size:12px; white-space:normal; }
        @media (max-width: 1023px) {
            .action-panel {
                left: 0;
                padding: .75rem .75rem max(.75rem, env(safe-area-inset-bottom));
            }
            .main-container { padding-bottom: 15rem; }
        }
        @media (max-width: 640px) {
            .action-panel-bar { max-height: min(50vh, 420px); }
            .action-panel-bar .action-cards { grid-template-columns: 1fr; }
        }

        /* ===== INFO ROWS ===== */
        .info-label { color: var(--sierra-type-muted, #63746b); font-size: 0.8rem; font-weight: 500; }
        .info-value { font-weight: 600; color: var(--sierra-type-primary, #203b31); }
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
        .status-pending { background: rgba(245, 158, 11, .14); color: #F59E0B; }
        .status-under_review { background: rgba(59, 130, 246, .14); color: #3B82F6; }
        .status-verified { background: rgba(59, 130, 246, .14); color: #3B82F6; }
        .status-in_progress { background: rgba(99, 102, 241, .14); color: #6366F1; }
        .status-escalated_pending { background: rgba(234, 88, 12, .14); color: #EA580C; }
        .status-escalated { background: rgba(234, 88, 12, .14); color: #EA580C; }
        .status-resolved { background: rgba(22, 163, 74, .14); color: #16A34A; }
        .status-rejected { background: rgba(220, 38, 38, .14); color: #DC2626; }
        .status-closed { background: #F3F4F6; color: var(--sierra-type-muted, #63746b); }
        .risk-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .risk-low { background: #D1FAE5; color: #065F46; }
        .risk-medium { background: #FEF3C7; color: #92400E; }
        .risk-high { background: #FFEDD5; color: #9A3412; }
        .risk-critical { background: #FEE2E2; color: #991B1B; }

        /* Print dropdown styles: see assets/css/export-print.css */


        @media print {
            /* Reset page */
            @page {
                size: A4;
                margin: 10mm 12mm;
            }

            /* Hide non-printable elements */
            #sidebar,
            #showSidebarBtn,
            .app-mobile-header,
            .print-dropdown,
            .action-panel,
            .action-modal-overlay,
            .no-print,
            .toast-msg,
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
                border-radius: 8px!important;
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
                border-radius: 8px!important;
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
            .photo-grid img,
            .photo-grid video {
                height: 60px !important;
                border-radius: 8px!important;
            }

            /* Map */
            #map {
                height: 120px !important;
                border-radius: 8px!important;
            }

            /* Notes */

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
                color: var(--sierra-type-muted, #63746b);
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

        /* ===== FOCUS ACCESSIBILITY ===== */
        a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible, select:focus-visible, [tabindex]:focus-visible {
            outline: 2px solid #10A37F; outline-offset: 2px; border-radius: 8px;
        }

        /* ===== PAGE ENTRANCE ===== */
        .fade-up { animation: fadeUp 0.45s ease both; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        /* ===== TOAST / FLASH MESSAGES ===== */
        .toast-msg { animation: toastIn 0.3s ease both; position: relative; }
        @keyframes toastIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        .toast-close { position: absolute; top: 10px; right: 10px; width: 22px; height: 22px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; cursor: pointer; opacity: 0.6; transition: all 0.15s ease; background: transparent; border: none; }
        .toast-close:hover { opacity: 1; background: rgba(0,0,0,0.06); }
        .toast-progress { position: absolute; bottom: 0; left: 0; height: 2px; background: currentColor; opacity: 0.35; animation: toastShrink 6s linear forwards; border-radius: 0 0 0.75rem 0.75rem; }
        @keyframes toastShrink { from { width: 100%; } to { width: 0%; } }

        /* ===== STEP BADGE ===== */
        .step-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; background: linear-gradient(135deg, #10A37F, #0D8568); color: white; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; box-shadow: 0 2px 6px rgba(16,163,127,0.3); }

        /* ===== SECTION EYEBROW ===== */
        .section-eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.05em; color: var(--sierra-type-muted, #63746b); text-transform: uppercase; }

        /* ===== EXPANDABLE ACTION FORM ===== */
        .expand-section { display: grid; grid-template-rows: 0fr; opacity: 0; transition: grid-template-rows 0.3s ease, opacity 0.25s ease, margin 0.3s ease; margin-top: 0; }
        .expand-section.open { grid-template-rows: 1fr; opacity: 1; margin-top: 0.5rem; }
        .expand-section > div { overflow: hidden; min-height: 0; }
        .expand-section-inner { padding: 1rem; }
        @media (max-width: 380px) { .expand-section-inner { padding: 0.75rem; } }

        /* ===== ACTION TRIGGER BUTTON ===== */
        .action-trigger.is-active { box-shadow: 0 0 0 3px rgba(16,163,127,0.25) inset; }

        /* ===== BUTTON LOADING STATE ===== */
        button[type="submit"].is-loading { pointer-events: none; opacity: 0.8; position: relative; color: transparent !important; transition: opacity 0.2s ease; }
        button[type="submit"].is-loading::after {
            content: ''; position: absolute; top: 50%; left: 50%; width: 16px; height: 16px; margin: -8px 0 0 -8px;
            border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%;
            animation: btn-spin 0.65s cubic-bezier(0.4,0,0.2,1) infinite, btn-pop 0.2s ease both;
        }
        button[type="submit"].btn-secondary.is-loading::after { border-color: rgba(16,163,127,0.25); border-top-color: #10A37F; }
        @keyframes btn-spin { to { transform: rotate(360deg); } }
        @keyframes btn-pop  { from { transform: scale(0.5) rotate(0deg); } to { transform: scale(1) rotate(0deg); } }

        /* ===== FILE UPLOAD (DRAG & DROP + PREVIEW) ===== */
        .file-upload-area { position: relative; overflow: hidden; }
        .file-upload-area.drag-over { border-color: #10A37F; background: #E8F5F0; }
        .file-upload-area.has-file { border-style: solid; border-color: #10A37F; background: #F5FBF6; padding: 10px; }
        .file-upload-preview { display: none; max-height: 110px; border-radius: 0.5rem; margin: 0 auto 8px; object-fit: cover; }
        video.file-upload-preview { max-height: 160px; width: 100%; background: #000; object-fit: contain; }
        .file-upload-area.has-file .file-upload-placeholder { display: none; }

        /* ===== LIGHTBOX ===== */
        .lightbox-overlay { position: fixed; inset: 0; background: rgba(15,23,20,0.92); backdrop-filter: blur(6px); z-index: 10000; display: none; align-items: center; justify-content: center; animation: fadeIn 0.2s ease; }
        .lightbox-overlay.open { display: flex; }
        .lightbox-img { max-width: 88vw; max-height: 80vh; border-radius: 0.75rem; box-shadow: 0 20px 60px rgba(0,0,0,0.5); animation: fadeUp 0.25s ease; }
        .lightbox-close, .lightbox-nav { position: absolute; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 9999px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; }
        .lightbox-close:hover, .lightbox-nav:hover { background: #10A37F; border-color: #10A37F; }
        .lightbox-close { top: 20px; right: 20px; width: 42px; height: 42px; font-size: 1.1rem; }
        .lightbox-nav { top: 50%; transform: translateY(-50%); width: 46px; height: 46px; font-size: 1.2rem; }
        .lightbox-prev { left: 16px; } .lightbox-next { right: 16px; }
        .lightbox-counter { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); color: rgba(255,255,255,0.85); font-size: 0.8rem; font-weight: 600; background: rgba(255,255,255,0.1); padding: 4px 14px; border-radius: 9999px; }

        /* ===== CONFIRM MODAL ===== */
        .confirm-modal-overlay { position: fixed; inset: 0; background: rgba(15,23,20,0.55); backdrop-filter: blur(3px); z-index: 10020; display: none; align-items: center; justify-content: center; animation: fadeIn 0.15s ease; padding: 1rem; }
        .confirm-modal-overlay.open { display: flex; }
        .confirm-modal-card { background: white; border-radius: 1rem; padding: 1.5rem; max-width: 380px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.25); animation: fadeUp 0.2s ease; }
        .confirm-modal-icon { width: 44px; height: 44px; border-radius: 9999px; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 12px; }
        .confirm-modal-card.is-positive { border:1px solid #dce8e1; box-shadow:0 24px 60px -24px #124c3c80; }
        .confirm-modal-card.is-positive .confirm-modal-icon { background:#eaf7f0; color:#0d8568; border-radius:12px; }

        /* ===== COPY BUTTON ===== */
        .copy-btn { display: inline-flex; align-items: center; gap: 4px; color: var(--sierra-type-muted, #63746b); cursor: pointer; transition: color 0.15s ease; border: none; background: none; font-size: inherit; padding: 0; }
        .copy-btn:hover { color: #10A37F; }

        /* ===== EMPTY STATE ===== */
        .empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1rem; text-align: center; color: var(--sierra-type-muted, #63746b); }
        .empty-state i { font-size: 1.75rem; margin-bottom: 8px; opacity: 0.5; }

        /* ===== NOTE AVATAR ===== */

/* ===== ENHANCED ACTION PANEL ===== */
        /* Dashboard-branded report actions. Shared button variants own CTA colors. */
        .action-cards { display:grid; grid-template-columns:1fr; gap:12px; }
        .action-card { min-width:0; padding:16px; display:flex; flex-direction:column; border:1px solid #dce8e1; border-radius:16px; background:#fff; box-shadow:0 3px 12px #173b2c04; }
        .action-card .action-icon { width:40px; height:40px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; background:#e8f5ed; color:#0d8568; font-size:18px; }
        .action-card .action-icon.text-red-600 { background:#fef2f2; color:#dc2626; }
        .action-card-title { display:block; color:var(--sierra-type-primary); font-size:14px; font-weight:800; line-height:1.4; }
        .action-card-description { display:block; margin-top:4px; color:var(--sierra-type-muted); font-size:12px; font-weight:500; line-height:1.5; white-space:normal; overflow-wrap:anywhere; }
        .action-card .action-btn { width:100%; min-height:44px; display:inline-flex; align-items:center; justify-content:center; gap:8px; touch-action:manipulation; }
        body.manage-report-page .action-card .action-btn { border-radius:var(--sierra-button-radius,10px) !important; font-size:13px; }
        .action-card .action-btn > i { margin:0; flex-shrink:0; }
        .action-card > form { display:flex; flex-direction:column; flex-grow:1; gap:12px; margin-top:4px; }
        .action-card > form .action-btn,.action-card > button.action-trigger { margin-top:auto; }
        .action-card .action-expand,.action-expand { grid-column:1 / -1; }
        .action-card .action-trigger + .expand-section { width:100%; }
        .action-card .expand-section-inner { min-width:0; }
        .expand-section-inner button { min-height:44px; touch-action:manipulation; }
        .action-card-btn { cursor:pointer; width:100%; min-height:88px; text-align:left; justify-content:center; font-family:inherit; touch-action:manipulation; transition:background-color .18s,border-color .18s,box-shadow .18s; }
        .action-card-btn > .action-card-content { display:flex; align-items:center; gap:12px; width:100%; }
        .action-card-content > .action-card-copy { flex:1; min-width:0; }
        .action-card-btn > .action-card-content > i { flex-shrink:0; font-size:16px; }
        .action-card-btn:focus-visible,.action-card .action-btn:focus-visible { outline:3px solid #80baa3; outline-offset:3px; }
        .action-card-btn--resolve { background:var(--sierra-button-accent,#0d8568); border-color:var(--sierra-button-accent,#0d8568); color:#fff; box-shadow:0 4px 10px #0d856824; }
        .action-card-btn--resolve .action-card-title { color:#fff; }
        .action-card-btn--resolve .action-card-description { color:#e1f5ec; }
        .action-card-btn--resolve .action-icon { background:#ffffff24; color:#fff; }
        .action-card-btn--resolve:hover { background:var(--sierra-button-hover,#086b54); border-color:var(--sierra-button-hover,#086b54); box-shadow:0 6px 14px #0d856833; }
        .action-card-btn--escalate { background:#f0f8f4; border-color:#cde2d7; color:#0d7057; }
        .action-card-btn--escalate:hover { background:#e3f2ea; border-color:#a4cbb5; }
        @media(max-width:640px) { .action-card { padding:12px; } .action-card-btn { min-height:80px; } }
        @media(prefers-reduced-motion:reduce) { .action-card-btn { transition:none; } }
        .risk-edit-btn { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; font-size: 0.68rem; font-weight: 700; color: #0D8568; background: rgba(16,163,127,0.10); border: 1px solid rgba(16,163,127,0.22); border-radius: 9999px; cursor: pointer; transition: all .15s ease; touch-action: manipulation; }
        .risk-edit-btn:hover { background: rgba(16,163,127,0.18); }
        .qn-note-suggestions { display: none; }
        .qn-note-suggestions.visible { display: block; }
        .action-modal-overlay { position: fixed; inset: 0; background: rgba(15,23,20,0.55); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 1rem; animation: fadeIn .15s ease; }
        .action-modal-overlay.open { display: flex; }
        .action-modal-card { background: #fff; border-radius: 1rem; width: 100%; max-width: 460px; max-height: 92vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.28); animation: fadeUp .2s ease; }
        .action-modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid #F3F4F6; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-shrink: 0; }
        .action-modal-header .action-icon { width: 42px; height: 42px; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1rem; }
        .action-modal-close { width: 34px; height: 34px; border-radius: 9999px; background: #F3F4F6; border: none; color: var(--sierra-type-muted, #63746b); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: all .15s ease; touch-action: manipulation; }
        .action-modal-close:hover { background: #FEE2E2; color: #DC2626; }
        .action-modal-body { padding: 1.25rem; overflow-y: auto; }
        .action-modal-body form { display: flex; flex-direction: column; gap: 0.9rem; }
        .action-modal-body .modal-submit { width: 100%; min-height: 46px; justify-content: center; }
        .action-modal-card > form { display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
        .action-modal-card > form .action-modal-body { min-height: 0; }
        .action-modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 1rem 1.25rem; border-top: 1px solid #eef2f0; flex-shrink: 0; }
        .action-modal-footer button { min-height: 44px; }
        @media (max-width: 480px) {
            .action-modal-card { max-width: none; }
            .action-modal-header { padding: 0.9rem 1rem; }
            .action-modal-body { padding: 1rem; }
        }

        /* Context Callouts */
        .action-callout { display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 1rem; font-size: 0.82rem; }
        .action-callout.info { background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E40AF; }
        .action-callout.warning { background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; }
        .action-callout.success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
        .action-callout.danger { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; }
        .action-callout .callout-title { font-weight: 700; }
        .action-callout .callout-sub { font-size: 0.75rem; margin-top: 2px; opacity: 0.9; }
        /* ===== EVIDENCE CAMERA MODAL (ported from submit-report) ===== */
        .file-upload-choice { display: flex; gap: 10px; margin-bottom: 10px; }
        .file-upload-choice button { flex: 1; min-height: 44px; padding: 9px 8px; background: #f3f4f6; border-radius: 0.75rem; border: none; display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 0.8rem; font-weight: 500; color: var(--sierra-type-primary, #203b31); cursor: pointer; transition: all 0.15s; touch-action: manipulation; }
        .file-upload-choice button:hover { background: #e5e7eb; }
        @media (max-width: 360px) { .file-upload-choice { flex-direction: column; } }

        .camera-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 16px; flex-direction: column; }
        .camera-modal.active { display: flex; }
        .camera-modal .camera-wrapper { position: relative; width: 100%; max-width: 500px; border-radius: 16px; overflow: hidden; background: #000; aspect-ratio: 4/3; box-shadow: 0 25px 60px rgba(0,0,0,0.8); }
        .camera-modal .camera-wrapper video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .camera-modal .viewfinder { position: absolute; inset: 0; pointer-events: none; border: 2px solid rgba(255,255,255,0.15); border-radius: 16px; box-shadow: inset 0 0 0 2px rgba(255,255,255,0.05); }
        .camera-modal .viewfinder::before { content: ''; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; height: 80%; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; }
        .camera-modal .viewfinder .crosshair { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
        .camera-modal .viewfinder .crosshair::before, .camera-modal .viewfinder .crosshair::after { content: ''; position: absolute; background: rgba(255,255,255,0.3); }
        .camera-modal .viewfinder .crosshair::before { width: 2px; height: 100%; }
        .camera-modal .viewfinder .crosshair::after { width: 100%; height: 2px; }
        .camera-modal .viewfinder .corner { position: absolute; width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.2); }
        .camera-modal .viewfinder .corner.tl { top: 12px; left: 12px; border-right: none; border-bottom: none; }
        .camera-modal .viewfinder .corner.tr { top: 12px; right: 12px; border-left: none; border-bottom: none; }
        .camera-modal .viewfinder .corner.bl { bottom: 12px; left: 12px; border-right: none; border-top: none; }
        .camera-modal .viewfinder .corner.br { bottom: 12px; right: 12px; border-left: none; border-top: none; }

        .camera-controls { display: flex; align-items: center; justify-content: center; gap: 20px; margin-top: 24px; width: 100%; max-width: 500px; padding: 0 8px; }
        .camera-controls .ctrl-btn { width: 56px; height: 56px; border-radius: 50%; border: none; background: rgba(255,255,255,0.12); backdrop-filter: blur(4px); color: white; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; touch-action: manipulation; }
        .camera-controls .ctrl-btn:hover { background: rgba(255,255,255,0.2); transform: scale(1.05); }
        .camera-controls .ctrl-btn:active { transform: scale(0.92); }
        .camera-controls .ctrl-btn.capture { width: 72px; height: 72px; background: white; color: #10A37F; font-size: 1.8rem; box-shadow: 0 0 0 4px rgba(255,255,255,0.2); }
        .camera-controls .ctrl-btn.capture:hover { background: #f0fdf4; box-shadow: 0 0 0 6px rgba(255,255,255,0.3); }
        .camera-controls .ctrl-btn.close-cam { background: rgba(239,68,68,0.3); color: #fca5a5; }
        .camera-controls .ctrl-btn.close-cam:hover { background: rgba(239,68,68,0.5); }
        .camera-controls .ctrl-btn.flash { background: rgba(255,255,255,0.08); color: #fbbf24; }
        .camera-controls .ctrl-btn.flash.active { background: #fbbf24; color: var(--sierra-type-primary, #203b31); }
        .camera-controls .ctrl-btn.switch-cam { background: rgba(255,255,255,0.08); color: #93c5fd; }
        @media (max-width: 480px) {
            .camera-controls .ctrl-btn { width: 48px; height: 48px; }
            .camera-controls .ctrl-btn.capture { width: 60px; height: 60px; }
            .camera-controls { gap: 14px; }
        }

        .camera-mode-toggle { display: flex; align-items: center; gap: 4px; background: rgba(255,255,255,0.12); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.15); border-radius: 9999px; padding: 4px; margin-bottom: 18px; }
        .camera-mode-toggle .mode-btn { border: none; background: transparent; color: rgba(255,255,255,0.7); font-size: 0.8rem; font-weight: 600; padding: 8px 18px; border-radius: 9999px; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s ease; touch-action: manipulation; }
        .camera-mode-toggle .mode-btn:hover { color: white; }
        .camera-mode-toggle .mode-btn.mode-active { background: white; color: #10A37F; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        @media (max-width: 480px) { .camera-mode-toggle .mode-btn { padding: 7px 14px; } }

        .recording-indicator { position: absolute; top: 14px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.15); color: white; padding: 8px 16px; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 8px; z-index: 20; pointer-events: none; }
        .recording-indicator .rec-dot { width: 10px; height: 10px; border-radius: 50%; background: #EF4444; animation: recBlink 1s infinite; flex-shrink: 0; }
        @keyframes recBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
        .recording-indicator .rec-max { font-size: 0.65rem; color: rgba(255,255,255,0.6); font-weight: 500; }
        .camera-controls .ctrl-btn.capture.recording { background: #EF4444; color: white; box-shadow: 0 0 0 6px rgba(239,68,68,0.3); animation: recBtnPulse 1.2s infinite; }
        @keyframes recBtnPulse { 0%, 100% { box-shadow: 0 0 0 4px rgba(239,68,68,0.3); } 50% { box-shadow: 0 0 0 10px rgba(239,68,68,0); } }

        .camera-tips-overlay { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: white; padding: 10px 18px; border-radius: 24px; font-size: 0.75rem; text-align: center; max-width: 90%; z-index: 10; border: 1px solid rgba(255,255,255,0.08); pointer-events: none; transition: opacity 0.3s ease; white-space: nowrap; }
        .camera-tips-overlay .tip-emoji { margin-right: 6px; }
        .camera-tips-overlay .tip-text strong { color: #10A37F; }
        .camera-tips-overlay .tip-dismiss { position: absolute; top: -8px; right: -6px; background: rgba(255,255,255,0.15); border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; cursor: pointer; pointer-events: auto; font-size: 10px; color: #aaa; transition: all 0.2s; }
        .camera-tips-overlay .tip-dismiss:hover { background: rgba(255,255,255,0.3); color: white; }
        @media (max-width: 480px) { .camera-tips-overlay { padding: 8px 14px; bottom: 12px; white-space: normal; } }

        /* ===== MOBILE RESPONSIVENESS ===== */
        /* Keep content clear of the floating sidebar menu button on phone/tablet. */
        @media (max-width: 1023px) {
            .main-container { padding-top: 3.75rem; }
        }
        /* Long values (GPS, addresses) wrap instead of breaking the layout. */
        .info-row .info-value { min-width: 0; overflow-wrap: anywhere; text-align: right; }
        @media (max-width: 640px) {
            .export-dropdown-menu { right: 0; transform: none; width: min(260px, calc(100vw - 2rem)); min-width: 0; }
            .card { padding: 1rem; }
            .two-col { gap: 1rem; }
            #map { height: 240px; }
            .photo-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; }
            .info-label, .info-value { font-size: 0.8rem; }
            .card form.flex.gap-2 { flex-wrap: wrap; }
            .card form.flex.gap-2 input { flex: 1 1 100%; }
            .card form.flex.gap-2 button { width: 100%; }
        }
    </style>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
</head>
<body class="manage-report-page report-detail-page">

<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<div class="lg:ml-72 min-h-screen">
    <div class="main-container">

        <!-- Flash Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="toast-msg mb-4 p-4 pr-9 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 text-sm flex items-center gap-2">
                <i class="fas fa-check-circle text-green-500"></i>
                <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                <button type="button" class="toast-close" onclick="this.closest('.toast-msg').remove()" aria-label="Dismiss message"><i class="fas fa-xmark text-xs text-green-600"></i></button>
                <div class="toast-progress text-green-500"></div>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="toast-msg mb-4 p-4 pr-9 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 text-sm flex items-center gap-2">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                <button type="button" class="toast-close" onclick="this.closest('.toast-msg').remove()" aria-label="Dismiss message"><i class="fas fa-xmark text-xs text-red-600"></i></button>
                <div class="toast-progress text-red-500"></div>
            </div>
        <?php endif; ?>

        <!-- ===== BRANDED HEADER ===== -->
        <div class="report-top-actions mb-5 md:mb-6 no-print">
            <a href="<?php echo ($user_role == 'admin') ? BASE_URL . 'index.php?page=all-reports' : BASE_URL . 'index.php?page=verify-reports'; ?>"
               class="report-back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Back to reports</span>
            </a>
            <div class="export-dropdown">
                <button onclick="togglePrintMenu()" class="btn-export-trigger" id="printDropdownTrigger">
                    <i class="fas fa-file-export"></i>
                    <span>Export</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div id="printDropdownMenu" class="export-dropdown-menu" style="width:220px;">
                    <div>
                        <button class="export-dropdown-item" onclick="window.open('<?php echo BASE_URL; ?>index.php?page=manage-report&id=<?php echo (int)$report['id']; ?>&print=1', '_blank')">
                            <i class="fas fa-file-pdf"></i>
                            <span>Export as PDF</span>
                        </button>
                        <?php if (PermissionHelper::userHasPermission('can_export_reports')): ?>
                        <button class="export-dropdown-item" onclick="handleDownloadCSV()">
                            <i class="fas fa-file-csv"></i>
                            <span>Export as CSV</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="report-detail-sheet">
        <!-- ===== REPORT HERO CARD ===== -->
        <div class="report-hero fade-up">
            <div class="report-hero-inner">
                <div>
                    <span class="report-hero-kicker">
                        <i class="fas fa-file-alt"></i>
                        Report #<?php echo str_pad($report['id'], 6, '0', STR_PAD_LEFT); ?>
                    </span>
                    <h2 class="report-hero-title"><?php echo htmlspecialchars($report['title']); ?></h2>
                    <div class="report-hero-meta">
                        <span class="report-hero-chip">
                            <i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y \a\t h:i A', strtotime($report['created_at'])); ?>
                        </span>
                        <span class="report-hero-chip">
                            <i class="far fa-clock"></i> <?php echo timeAgo($report['created_at']); ?>
                        </span>
                    </div>
                </div>
                <div class="report-hero-badges">
                    <?php echo getStatusBadge($report['status']); ?>
                    <?php echo getRiskBadge($report['risk_level']); ?>
                </div>
            </div>
        </div>

        <?php $reportProgressContext = 'manage'; $reportProgressMode = 'summary'; include BASE_PATH . 'views/shared/report_progress.php'; ?>

        <!-- Two Columns: Reporter Details + Metadata -->
        <div class="two-col fade-up" style="animation-delay:0.05s">
            <div class="card">
                <div class="card-header"><i class="fas fa-user"></i> Reporter Details</div>
                <div class="space-y-2 text-sm">
                    <div class="info-row"><span class="info-label">Full Name</span><span class="info-value"><?php echo htmlspecialchars($report['user_name'] ?? 'Unknown'); ?></span></div>
                    <div class="info-row"><span class="info-label">Account</span><span class="info-value">
                        <?php if ($report['is_resident'] ?? 0): ?>
                            <span class="badge badge-resident"><i class="fas fa-check-circle mr-1"></i> Resident</span>
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
                    <div class="info-row"><span class="info-label">Risk Level</span>
                        <span class="info-value inline-flex items-center gap-1.5 flex-wrap justify-end">
                            <?php echo ucfirst($report['risk_level']); ?>
                            <?php if ($can_reclassify): ?>
                            <button type="button" onclick="openActionModal('reclassifyModal')" class="risk-edit-btn" title="Reclassify risk level" aria-label="Reclassify risk level">
                                <i class="fas fa-pen"></i>
                            </button>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($user_role === 'admin' || $user_role === 'menro_staff'): ?>
                    <div class="info-row"><span class="info-label">Impact Modifier</span><span class="info-value">
                        <?php
                        $imp = $report['impact_modifier'] ?? 0;
                        if ($imp == 4) echo '<span class="text-red-600 font-bold">Severe (+4)</span>';
                        elseif ($imp == 2) echo '<span class="text-amber-600 font-bold">Moderate (+2)</span>';
                        else echo '<span class="text-emerald-600 font-bold">Localized (+0)</span>';
                        ?>
                    </span></div>
                    <?php
                    $mgr_score = (int)($report['severity_score'] ?? 0);
                    $mgr_level = getRiskLevelFromScore($mgr_score);
                    $mgr_color = ($mgr_level == 'critical') ? 'text-red-600' : (($mgr_level == 'high') ? 'text-orange-600' : (($mgr_level == 'medium') ? 'text-amber-600' : 'text-emerald-600'));
                    ?>
                    <div class="info-row"><span class="info-label">Severity Score</span><span class="info-value font-bold <?php echo $mgr_color; ?>"><?php echo $mgr_score; ?></span></div>
                    <?php endif; ?>
                    <div class="info-row"><span class="info-label">Classification</span><span class="info-value"><?php echo $report['decision_classification'] ?? 'Pending'; ?></span></div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="card fade-up" style="animation-delay:0.1s">
            <div class="card-header"><i class="fas fa-align-left"></i> Resident's Description</div>
            <div class="text-gray-700 leading-relaxed whitespace-pre-line"><?php echo nl2br(htmlspecialchars($report['description'])); ?></div>
        </div>

        <?php $reportProgressMode = 'history'; include BASE_PATH . 'views/shared/report_progress.php'; ?>

        <!-- Geographic Location (map solo, full width) -->
        <div class="card fade-up" style="animation-delay:0.15s">
            <div class="card-header"><i class="fas fa-map-marker-alt"></i> Geographic Location</div>
            <?php if ($report['latitude'] && $report['longitude'] && $report['latitude'] != 0 && $report['longitude'] != 0): ?>
                <div id="map"></div>
                <p class="text-xs text-gray-500 mt-2 flex flex-wrap items-center gap-x-1 gap-y-1">
                    <i class="fas fa-location-dot mr-1 text-emerald-600"></i>
                    <span id="gpsCoords">GPS: <?php echo number_format($report['latitude'], 6); ?>, <?php echo number_format($report['longitude'], 6); ?></span>
                    <button type="button" class="copy-btn ml-1" onclick="copyGps(this)" aria-label="Copy coordinates">
                        <i class="fas fa-copy"></i><span>Copy</span>
                    </button>
                    &nbsp; <a href="https://www.google.com/maps?q=<?php echo $report['latitude']; ?>,<?php echo $report['longitude']; ?>" target="_blank" class="text-emerald-600 hover:underline font-medium">Open in Google Maps <i class="fas fa-arrow-up-right-from-square text-[9px] ml-0.5"></i></a>
                </p>
                <?php if (!empty($report['location_address'])): ?>
                    <p class="text-sm text-gray-600 mt-1"><i class="fas fa-address-card mr-1 text-gray-400"></i> <?php echo htmlspecialchars($report['location_address']); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-map-location-dot"></i>
                    <p class="text-sm">No location data available.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Two Columns: Evidentiary Photo (left) + Resolution Evidence (right) -->
        <div class="two-col fade-up" style="animation-delay:0.2s">
            <div class="card card-bleed">
                <div class="card-header"><i class="fas fa-image"></i> Evidentiary Photo</div>
                <?php if (!empty($images)): ?>
                    <div class="photo-grid pg-<?php echo min(count($images), 5); ?>">
                        <?php foreach ($images as $i => $img): ?>
                            <?php if (!empty($img['is_video'])): ?>
                                <div class="photo-card relative" onclick="openLightbox(<?php echo (int)$i; ?>)" role="button" tabindex="0" onkeydown="if(event.key==='Enter')openLightbox(<?php echo (int)$i; ?>)">
                                    <video src="<?php echo BASE_URL . $img['image_path']; ?>" muted playsinline preload="metadata"></video>
                                    <div class="absolute top-2 left-2 bg-black/70 text-white text-[10px] px-2 py-0.5 rounded-full flex items-center gap-1">
                                        <i class="fas fa-video"></i>Video
                                    </div>
                                </div>
                            <?php else: ?>
                                <img src="<?php echo BASE_URL . $img['image_path']; ?>" onclick="openLightbox(<?php echo (int)$i; ?>)" alt="Evidentiary photo <?php echo (int)$i + 1; ?> for this report" loading="lazy" tabindex="0" onkeydown="if(event.key==='Enter')openLightbox(<?php echo (int)$i; ?>)">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($images) > 1): ?>
                        <p class="text-xs text-gray-400 mt-2"><i class="fas fa-expand mr-1"></i>Click any photo/video to view full size — <?php echo count($images); ?> media total.</p>
                    <?php else: ?>
                        <p class="text-xs text-gray-400 mt-2"><i class="fas fa-expand mr-1"></i>Click to view full size.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-image"></i>
                        <p class="text-sm">No photos submitted with this report.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card card-bleed">
                <div class="card-header"><i class="fas fa-check-circle" style="color:#10A37F"></i> Resolution Evidence</div>
                <?php if (!empty($resolution_evidence)): ?>
                    <div class="photo-grid pg-<?php echo min(count($resolution_evidence), 5); ?>">
                        <?php foreach ($resolution_evidence as $ev): ?>
                            <div class="photo-grid-cell">
                                <div class="photo-card relative">
                                    <?php if (!empty($ev['is_video'])): ?>
                                        <video src="<?php echo BASE_URL . $ev['image_path']; ?>" muted playsinline preload="metadata" onclick="openLightbox(<?php echo count($images) + (int)array_search($ev, $resolution_evidence, true); ?>)" role="button" tabindex="0" onkeydown="if(event.key==='Enter')openLightbox(<?php echo count($images) + (int)array_search($ev, $resolution_evidence, true); ?>)"></video>
                                        <div class="absolute top-2 left-2 bg-black/70 text-white text-[10px] px-2 py-0.5 rounded-full flex items-center gap-1">
                                            <i class="fas fa-video"></i>Video
                                        </div>
                                    <?php else: ?>
                                        <img src="<?php echo BASE_URL . $ev['image_path']; ?>" onclick="openLightbox(<?php echo count($images) + (int)array_search($ev, $resolution_evidence, true); ?>)" alt="Resolution evidence photo" loading="lazy" tabindex="0" onkeydown="if(event.key==='Enter')openLightbox(<?php echo count($images) + (int)array_search($ev, $resolution_evidence, true); ?>)">
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($ev['caption'])): ?>
                                    <div class="resolution-note-box"><span class="resolution-note-label">Resolution note</span><p><?php echo htmlspecialchars($ev['caption']); ?></p><?php if (!empty($ev['uploaded_by_name'])): ?><span class="resolution-note-author"><?php echo htmlspecialchars($ev['uploaded_by_name']); ?></span><?php endif; ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($report['status'] == 'resolved'): ?>
                        <p class="text-xs text-gray-400 mt-2"><i class="fas fa-check-circle mr-1 text-emerald-500"></i>This report has been resolved — evidence uploaded by <?php echo htmlspecialchars($resolution_evidence[0]['uploaded_by_name'] ?? 'MENRO'); ?>.</p>
                    <?php else: ?>
                        <p class="text-xs text-gray-400 mt-2"><i class="fas fa-info-circle mr-1"></i>Evidence of the actions taken to resolve this report.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p class="text-sm">No resolution evidence uploaded yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notes -->
        <?php if ($show_notes): ?>
        <div class="card fade-up" style="animation-delay:0.2s">
            <div class="card-header"><i class="fas fa-comments"></i> Investigation Notes</div>
            <div class="note-thread max-h-72 overflow-y-auto mb-4 pr-1">
                <?php if (!empty($notes)): ?>
                    <?php foreach ($notes as $note): ?>
                        <div class="note-item flex gap-3">
                            <div class="note-avatar" aria-hidden="true"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($note['user_name'], 0, 1))); ?></div>
                            <div class="note-bubble">
                                <div class="note-meta">
                                    <span class="note-author"><?php echo htmlspecialchars($note['user_name']); ?></span>
                                    <span class="note-time"><?php echo date('M d, h:i A', strtotime($note['created_at'])); ?></span>
                                </div>
                                <?php if (PermissionHelper::canDeleteInvestigationNote($report, $note['user_id'])): ?>
                                <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="note-delete-form no-print" onsubmit="return handleReportFormSubmit(event, this)" data-confirm="Delete this investigation note?">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="delete_note">
                                    <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                                    <input type="hidden" name="note_id" value="<?php echo (int)$note['id']; ?>">
                                    <button type="submit" class="note-delete-btn" title="Delete investigation note" aria-label="Delete investigation note"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
                                </form>
                                <?php endif; ?>
                                <p class="note-text"><?php echo htmlspecialchars($note['note']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-comment-dots"></i>
                        <p class="text-sm">No investigation notes yet.</p>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Quick note form -->
            <?php
            // Closed reports retain their history but cannot accept new notes.
            $note_composer_allowed = $report_verified
                && ($user_role == 'barangay_official' || $user_role == 'admin')
                && $can_manage && in_array($report['status'], ['verified', 'in_progress', 'escalated_pending', 'escalated'], true);
            ?>
            <?php if ($note_composer_allowed): ?>
            <?php if (!empty($note_templates)): ?>
            <div class="qn-wrap qn-note-suggestions" id="noteQuickSuggestions">
                <div class="qn-suggestions-header">
                    <span class="qn-suggestions-title">Suggestions</span>
                </div>
                <div class="qn-chips">
                    <?php foreach ($note_templates as $tpl_index => $tpl_text): ?>
                    <button type="button" class="note-template-chip" onclick="insertNoteTemplate('NOTE_TEMPLATES', <?php echo $tpl_index; ?>, 'noteInput')" title="<?php echo htmlspecialchars($tpl_text, ENT_QUOTES); ?>">
                        <span class="chip-text"><?php echo htmlspecialchars($tpl_text); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="note-composer" onsubmit="setLoading(this)">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                <input type="text" name="note" id="noteInput" placeholder="Add an investigation note" maxlength="500" required class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-colors">
                <button type="submit" class="btn-primary whitespace-nowrap"><i class="fas fa-plus mr-1.5"></i>Add Note</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        </div><!-- /report-detail-sheet -->

        <!-- 🛠️ ACTION & MANAGEMENT PANEL -->
        <?php if ($can_verify || $can_reject || $can_resolve || $can_escalate || $can_approve_escalation || $can_reject_escalation): ?>
        <div class="action-panel no-print">
          <div class="action-panel-bar">

            <!-- CONTEXT CALLOUTS -->
            <?php if ($report['status'] == 'escalated_pending'): ?>
                <div class="action-callout warning mb-5">
                    <i class="fas fa-hourglass-half mt-0.5"></i>
                    <div>
                        <p class="callout-title">Needs your attention</p>
                        <p class="callout-sub">This report is pending MENRO approval.<?php if (!empty($escalation['escalation_reason'])): ?> <strong>Justification:</strong> <?php echo htmlspecialchars($escalation['escalation_reason']); ?><?php endif; ?></p>
                        <?php if (!empty($escalation['evidence_path'])): $esc_is_video = preg_match('/\.(mp4|webm|mov|m4v|avi)$/i', $escalation['evidence_path']); ?>
                            <a href="<?php echo BASE_URL . $escalation['evidence_path']; ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700 mt-2 hover:underline">
                                <i class="fas <?php echo $esc_is_video ? 'fa-video' : 'fa-image'; ?>"></i> View escalation evidence
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($report['status'] == 'escalated' && !empty($escalation['evidence_path'])): ?>
                <div class="action-callout info mb-5">
                    <i class="fas fa-building-shield mt-0.5"></i>
                    <div>
                        <?php if (!empty($escalation['evidence_path'])): $esc_is_video = preg_match('/\.(mp4|webm|mov|m4v|avi)$/i', $escalation['evidence_path']); ?>
                            <a href="<?php echo BASE_URL . $escalation['evidence_path']; ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 mt-2 hover:underline">
                                <i class="fas <?php echo $esc_is_video ? 'fa-video' : 'fa-image'; ?>"></i> View escalation evidence
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($report['status'] == 'resolved'): ?>
                <div class="action-callout success mb-5">
                    <i class="fas fa-check-double mt-0.5"></i>
                    <div>
                        <p class="callout-title">Report resolved</p>
                        <p class="callout-sub"><?php if (!empty($report['resolved_at'])): ?>Closed on <?php echo date('F d, Y h:i A', strtotime($report['resolved_at'])); ?>.<?php endif; ?> Investigation notes are read-only.</p>
                    </div>
                </div>
            <?php elseif ($report['status'] == 'rejected'): ?>
                <div class="action-callout danger mb-5">
                    <i class="fas fa-circle-xmark mt-0.5"></i>
                    <div>
                        <p class="callout-title">Report rejected</p>
                        <p class="callout-sub"><?php if (!empty($report['rejection_reason'])): ?><strong>Reason:</strong> <?php echo htmlspecialchars($report['rejection_reason']); ?><?php endif; ?></p>
                    </div>
                </div>
            <?php elseif ($report['status'] == 'pending'): ?>
                <div class="action-callout info mb-5">
                    <i class="fas fa-hourglass-start mt-0.5"></i>
                    <div>
                        <p class="callout-title">Awaiting review</p>
                        <p class="callout-sub">This report is queued and waiting to be processed.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ACTION CARDS GRID -->
            <div class="action-cards">

            <?php if ($user_role == 'barangay_official'): ?>
                <?php if ($can_verify): ?>
                <div class="action-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="action-icon bg-blue-50 text-blue-600"><i class="fas fa-check"></i></div>
                        <div class="min-w-0">
                            <p class="action-card-title">Verify Report</p>
                            <p class="action-card-description">Mark this report as legitimate</p>
                        </div>
                    </div>
                    <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" data-confirm="Are you sure you want to verify this report?" onsubmit="return handleReportFormSubmit(event, this)">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="verify_report">
                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                        <button type="submit" class="action-btn btn-primary"><i class="fas fa-check mr-2"></i> Verify Report</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($can_reject): ?>
                <div class="action-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="action-icon bg-red-50 text-red-600"><i class="fas fa-ban"></i></div>
                        <div class="min-w-0">
                            <p class="action-card-title">Reject Report</p>
                            <p class="action-card-description">Refuse this report and notify resident</p>
                        </div>
                    </div>
                    <button type="button" onclick="openActionModal('rejectReportModal')" class="action-trigger action-btn btn-danger"><i class="fas fa-times-circle mr-2"></i> Reject Report</button>
                </div>
                <?php endif; ?>

                <?php if ($can_resolve): ?>
                <button type="button" class="action-card action-card-btn action-card-btn--resolve" onclick="openActionModal('resolveModal')" aria-haspopup="dialog">
                    <span class="action-card-content">
                        <span class="action-icon"><i class="fas fa-check-double"></i></span>
                        <span class="action-card-copy">
                            <span class="action-card-title">Mark as Resolved</span>
                            <span class="action-card-description">Attach photo proof and close the report</span>
                        </span>
                        <i class="fas fa-chevron-right flex-shrink-0" aria-hidden="true"></i>
                    </span>
                </button>
                <?php endif; ?>

                <?php if ($can_escalate): ?>
                <button type="button" class="action-card action-card-btn action-card-btn--escalate" onclick="openActionModal('escalateModal')" aria-haspopup="dialog">
                    <span class="action-card-content">
                        <span class="action-icon"><i class="fas fa-arrow-up-right-dots"></i></span>
                        <span class="action-card-copy">
                            <span class="action-card-title">Escalate to MENRO</span>
                            <span class="action-card-description">Request MENRO intervention</span>
                        </span>
                        <i class="fas fa-chevron-right flex-shrink-0" aria-hidden="true"></i>
                    </span>
                </button>
                <?php endif; ?>

            <?php elseif ($user_role == 'admin'): ?>
                <?php if ($can_approve_escalation): ?>
                <div class="action-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="action-icon bg-amber-50 text-amber-600"><i class="fas fa-arrow-up-right-dots"></i></div>
                        <div class="min-w-0">
                            <p class="action-card-title">Escalation Review</p>
                            <p class="action-card-description">Decide on this pending escalation</p>
                        </div>
                    </div>
                    <div class="escalation-actions">
                    <button type="button" onclick="openActionModal('approveEscalModal')" class="action-trigger action-btn btn-success">
                        <i class="fas fa-check mr-2"></i> Approve Escalation
                    </button>
                    <button type="button" onclick="openActionModal('rejectEscalModal')" class="action-trigger action-btn btn-danger">
                        <i class="fas fa-xmark mr-2"></i> Reject Escalation
                    </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($can_resolve): ?>
                <button type="button" class="action-card action-card-btn action-card-btn--resolve" onclick="openActionModal('resolveAdminModal')" aria-haspopup="dialog">
                    <span class="action-card-content">
                        <span class="action-icon"><i class="fas fa-check-double"></i></span>
                        <span class="action-card-copy">
                            <span class="action-card-title">Mark as Resolved</span>
                            <span class="action-card-description">Close this report with photo proof</span>
                        </span>
                        <i class="fas fa-chevron-right flex-shrink-0" aria-hidden="true"></i>
                    </span>
                </button>
                <?php endif; ?>
            <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Print footer (only visible when printing) -->
    <div class="print-footer">
        <strong>Sierra Environmental Reporting System</strong> — Report #<?php echo str_pad($report['id'], 6, '0', STR_PAD_LEFT); ?>
        &nbsp;|&nbsp; Generated: <?php echo date('F d, Y \a\t h:i A'); ?>
        &nbsp;|&nbsp; This document is for official use only.
    </div>
</div>

<!-- Photo Lightbox -->
<?php if (!empty($images)): ?>
<div class="lightbox-overlay no-print" id="lightboxOverlay" onclick="if(event.target===this)closeLightbox()">
    <button type="button" class="lightbox-close" onclick="closeLightbox()" aria-label="Close photo viewer"><i class="fas fa-xmark"></i></button>
    <button type="button" class="lightbox-nav lightbox-prev" onclick="navLightbox(-1)" aria-label="Previous photo"><i class="fas fa-chevron-left"></i></button>
    <img class="lightbox-img" id="lightboxImg" src="" alt="Evidentiary photo, enlarged view">
    <video class="lightbox-video" id="lightboxVideo" src="" controls playsinline preload="metadata" disablepictureinpicture style="display:none;max-width:90%;max-height:85vh;border-radius:0.75rem;"></video>
    <button type="button" class="lightbox-nav lightbox-next" onclick="navLightbox(1)" aria-label="Next photo"><i class="fas fa-chevron-right"></i></button>
    <span class="lightbox-counter" id="lightboxCounter"></span>
</div>
<?php endif; ?>

<!-- Branded Confirm Modal (destructive actions) -->
<div class="confirm-modal-overlay no-print" id="confirmModalOverlay" onclick="if(event.target===this)closeConfirmModal()">
    <div class="confirm-modal-card">
        <div class="confirm-modal-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <p class="font-bold text-gray-800 text-base mb-1">Please confirm</p>
        <p class="text-sm text-gray-500 mb-5" id="confirmModalMessage"></p>
        <div class="flex gap-3">
            <button type="button" id="confirmModalProceed" class="btn-danger flex-1" onclick="proceedConfirmModal()">Yes, continue</button>
            <button type="button" class="btn-secondary flex-1" onclick="closeConfirmModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- ===== RECLASSIFY RISK MODAL (barangay) ===== -->
<?php if ($can_reject): ?>
<div class="action-modal-overlay no-print" id="rejectReportModal" onclick="if(event.target===this)closeActionModal('rejectReportModal')" role="dialog" aria-modal="true" aria-labelledby="rejectReportModalTitle">
    <div class="action-modal-card">
        <div class="action-modal-header"><div><h3 id="rejectReportModalTitle" class="font-bold">Reject Report</h3><p class="text-sm text-gray-500">Select a suggested reason or write your own.</p></div><button type="button" class="action-modal-close" onclick="closeActionModal('rejectReportModal')" aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" onsubmit="return submitReportReason(event,this)">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="reject_report">
            <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
            <div class="action-modal-body"><?php include BASE_PATH . 'views/shared/report_reason_picker.php'; ?></div>
            <div class="action-modal-footer"><button type="button" class="btn-secondary" onclick="closeActionModal('rejectReportModal')">Back</button><button type="submit" class="btn-danger">Confirm Rejection</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<div class="action-modal-overlay no-print" id="reclassifyModal" onclick="if(event.target===this)closeActionModal('reclassifyModal')" role="dialog" aria-modal="true" aria-labelledby="reclassifyModalTitle">
    <div class="action-modal-card">
        <div class="action-modal-header">
            <div><h3 id="reclassifyModalTitle" class="font-bold">Reclassify Risk Level</h3><p class="text-sm text-gray-500">Adjust the risk level for this report</p></div>
            <button type="button" class="action-modal-close" onclick="closeActionModal('reclassifyModal')" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="action-modal-body">
            <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" data-confirm="Are you sure you want to reclassify this report's risk level?" onsubmit="return handleReportFormSubmit(event, this)">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="reclassify_impact">
                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Risk Level</label>
                    <select name="new_impact" class="w-full border-2 border-gray-300 rounded-xl px-3 py-2.5 text-sm font-semibold focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none">
                        <option value="0" <?php echo ($report['impact_modifier'] == 0) ? 'selected' : ''; ?>>Low Risk (Localized)</option>
                        <option value="2" <?php echo ($report['impact_modifier'] == 2) ? 'selected' : ''; ?>>Medium Risk (Moderate)</option>
                        <option value="4" <?php echo ($report['impact_modifier'] == 4) ? 'selected' : ''; ?>>High Risk (Severe)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Reason for reclassification</label>
                    <input type="text" name="reclassify_reason" placeholder="Why is the risk level changing?" class="w-full border-2 border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none" required>
                </div>
                <div class="action-form-footer"><button type="button" class="btn-secondary" onclick="closeActionModal('reclassifyModal')">Back</button><button type="submit" class="btn-primary modal-submit"><i class="fas fa-save mr-2"></i> Update Risk Level</button></div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MARK AS RESOLVED MODAL (barangay) ===== -->
<div class="action-modal-overlay no-print" id="resolveModal" onclick="if(event.target===this)closeActionModal('resolveModal')" role="dialog" aria-modal="true" aria-labelledby="resolveModalTitle">
    <div class="action-modal-card">
        <div class="action-modal-header">
            <div><h3 id="resolveModalTitle" class="font-bold">Mark as Resolved</h3><p class="text-sm text-gray-500">Attach photo proof and close the report</p></div>
            <button type="button" class="action-modal-close" onclick="closeActionModal('resolveModal')" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="action-modal-body">
            <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" enctype="multipart/form-data" data-confirm="Are you sure you want to mark this report as resolved?" onsubmit="return handleReportFormSubmit(event, this)">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="resolve_report">
                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Resolution photo or video</label>
                    <div class="file-upload-area" id="resImageArea" ondragover="event.preventDefault();this.classList.add('drag-over')" ondragleave="this.classList.remove('drag-over')" ondrop="handleFileDrop(event, 'resImage')">
                        <img class="file-upload-preview" id="resImagePreviewImg" alt="">
                        <video class="file-upload-preview" id="resImagePreviewVideo" controls muted playsinline></video>
                        <div class="file-upload-placeholder">
                            <i class="fas fa-cloud-arrow-up text-2xl text-gray-400 mb-1 block"></i>
                            <span class="text-xs text-gray-600 font-medium">Drag &amp; drop a photo/video here</span>
                        </div>
                        <input type="file" name="resolution_image" id="resImage" accept="image/*,video/*" style="display:none;" required onchange="handleFilePreview(this,'resImageArea','resImagePreviewImg','resImagePreviewVideo','resPreview')">
                        <span id="resPreview" class="text-xs text-gray-500 block mt-2">Photo (Max 5MB) or video (Max 10MB)</span>
                    </div>
                    <div class="file-upload-choice mt-2">
                        <button type="button" onclick="openEvidenceCamera('resImage','resImageArea','resImagePreviewImg','resImagePreviewVideo','resPreview')"><i class="fas fa-camera"></i><span>Take Photo</span></button>
                        <button type="button" onclick="triggerFileInput('resImage','gallery')"><i class="fas fa-images"></i><span>Choose from Gallery</span></button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Resolution note</label>
                    <?php if (!empty($resolve_templates)): ?>
                    <div class="qn-wrap mb-1.5">
                        <div class="qn-suggestions-header">
                            <span class="qn-suggestions-title">Resolution suggestions</span>
                        </div>
                        <div class="qn-chips">
                            <?php foreach ($resolve_templates as $tpl_index => $tpl_text): ?>
                            <button type="button" class="note-template-chip" onclick="insertNoteTemplate('RESOLVE_TEMPLATES', <?php echo $tpl_index; ?>, 'resolutionNoteBarangay')" title="<?php echo htmlspecialchars($tpl_text, ENT_QUOTES); ?>">
                                <span class="chip-text"><?php echo htmlspecialchars($tpl_text); ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <textarea name="resolution_note" id="resolutionNoteBarangay" rows="3" class="w-full border-2 border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:border-[#10A37F] focus:ring-2 focus:ring-[#10A37F]/20 outline-none" placeholder="Describe how the issue was resolved"></textarea>
                </div>
                <div class="action-form-footer"><button type="button" class="btn-secondary" onclick="closeActionModal('resolveModal')">Back</button><button type="submit" class="btn-success modal-submit"><i class="fas fa-check mr-1.5"></i> Mark as Resolved</button></div>
            </form>
        </div>
    </div>
</div>

<!-- ===== ESCALATE TO MENRO MODAL (barangay) ===== -->
<div class="action-modal-overlay no-print" id="escalateModal" onclick="if(event.target===this)closeActionModal('escalateModal')" role="dialog" aria-modal="true" aria-labelledby="escalateModalTitle">
    <div class="action-modal-card">
        <div class="action-modal-header">
            <div><h3 id="escalateModalTitle" class="font-bold">Escalate to MENRO</h3><p class="text-sm text-gray-500">Request MENRO intervention</p></div>
            <button type="button" class="action-modal-close" onclick="closeActionModal('escalateModal')" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="action-modal-body">
            <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" data-confirm="Are you sure you want to escalate this report to MENRO? The MENRO office will be notified." onsubmit="return handleReportFormSubmit(event, this)">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="escalate_report">
                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Justification for escalation</label>
                    <?php if (!empty($escalate_templates)): ?>
                    <div class="qn-wrap mb-1.5">
                        <div class="qn-suggestions-header">
                            <span class="qn-suggestions-title">Escalation suggestions</span>
                        </div>
                        <div class="qn-chips">
                            <?php foreach ($escalate_templates as $tpl_index => $tpl_text): ?>
                            <button type="button" class="note-template-chip" onclick="insertNoteTemplate('ESCALATE_TEMPLATES', <?php echo $tpl_index; ?>, 'escalationReason')" title="<?php echo htmlspecialchars($tpl_text, ENT_QUOTES); ?>">
                                <span class="chip-text"><?php echo htmlspecialchars($tpl_text); ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <textarea name="escalation_reason" id="escalationReason" rows="4" class="w-full border-2 border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:border-[#10A37F] focus:ring-2 focus:ring-[#10A37F]/20 outline-none" placeholder="Explain why MENRO assistance is needed" required></textarea>
                </div>
                <div class="action-form-footer"><button type="button" class="btn-secondary" onclick="closeActionModal('escalateModal')">Back</button><button type="submit" class="btn-primary modal-submit"><i class="fas fa-arrow-up-right-dots mr-1.5"></i> Escalate Report</button></div>
            </form>
        </div>
    </div>
</div>

<!-- ===== APPROVE ESCALATION MODAL (admin) ===== -->
<div class="action-modal-overlay no-print" id="approveEscalModal" onclick="if(event.target===this)closeActionModal('approveEscalModal')" role="dialog" aria-modal="true" aria-labelledby="approveEscalModalTitle">
    <div class="action-modal-card">
        <div class="action-modal-header">
            <div><h3 id="approveEscalModalTitle" class="font-bold">Approve Escalation</h3><p class="text-sm text-gray-500">Accept this escalation and pass it to MENRO</p></div>
            <button type="button" class="action-modal-close" onclick="closeActionModal('approveEscalModal')" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="action-modal-body">
            <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" data-confirm="Approve this escalation and pass the report to MENRO?" onsubmit="return handleReportFormSubmit(event, this)">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="approve_escalation">
                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                    <i class="fas fa-circle-info mr-1.5"></i> This will move the report to MENRO supervision and notify MENRO staff.
                    <?php if (!empty($escalation['escalation_reason'])): ?>
                        <div class="mt-2 text-xs text-amber-700"><strong>Justification:</strong> <?php echo htmlspecialchars($escalation['escalation_reason']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="action-form-footer"><button type="button" class="btn-secondary" onclick="closeActionModal('approveEscalModal')">Back</button><button type="submit" class="btn-success modal-submit"><i class="fas fa-check mr-1.5"></i> Approve Escalation</button></div>
            </form>
        </div>
    </div>
</div>

<!-- ===== REJECT ESCALATION MODAL (admin) ===== -->
<div class="action-modal-overlay no-print" id="rejectEscalModal" onclick="if(event.target===this)closeActionModal('rejectEscalModal')" role="dialog" aria-modal="true" aria-labelledby="rejectEscalModalTitle">
    <div class="action-modal-card">
        <div class="action-modal-header">
            <div><h3 id="rejectEscalModalTitle" class="font-bold">Reject Escalation</h3><p class="text-sm text-gray-500">Send the escalation back to the barangay</p></div>
            <button type="button" class="action-modal-close" onclick="closeActionModal('rejectEscalModal')" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="action-modal-body">
            <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" data-confirm="Reject this escalation and send it back?" onsubmit="return submitReportReason(event,this)">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="reject_escalation">
                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Reason for rejection <span class="text-red-500">(required)</span></label>
                    <?php $reasonSuggestions = ['The issue can be handled at barangay level.', 'More evidence or investigation is needed.', 'This issue is already covered by an existing escalation.']; include BASE_PATH . 'views/shared/report_reason_picker.php'; unset($reasonSuggestions); ?>
                </div>
                <div class="action-form-footer"><button type="button" class="btn-secondary" onclick="closeActionModal('rejectEscalModal')">Back</button><button type="submit" class="btn-danger modal-submit"><i class="fas fa-xmark mr-1.5"></i> Reject Escalation</button></div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MARK AS RESOLVED MODAL (admin / escalated) ===== -->
<div class="action-modal-overlay no-print" id="resolveAdminModal" onclick="if(event.target===this)closeActionModal('resolveAdminModal')" role="dialog" aria-modal="true" aria-labelledby="resolveAdminModalTitle">
    <div class="action-modal-card">
        <div class="action-modal-header">
            <div><h3 id="resolveAdminModalTitle" class="font-bold">Mark as Resolved</h3><p class="text-sm text-gray-500">Attach photo proof and close the report</p></div>
            <button type="button" class="action-modal-close" onclick="closeActionModal('resolveAdminModal')" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="action-modal-body">
            <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" enctype="multipart/form-data" data-confirm="Are you sure you want to mark this report as resolved?" onsubmit="return handleReportFormSubmit(event, this)">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="resolve_report">
                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Resolution photo or video</label>
                    <div class="file-upload-area" id="resImageAdminArea" ondragover="event.preventDefault();this.classList.add('drag-over')" ondragleave="this.classList.remove('drag-over')" ondrop="handleFileDrop(event, 'resImageAdmin')">
                        <img class="file-upload-preview" id="resImageAdminPreviewImg" alt="">
                        <video class="file-upload-preview" id="resImageAdminPreviewVideo" controls muted playsinline></video>
                        <div class="file-upload-placeholder">
                            <i class="fas fa-cloud-arrow-up text-2xl text-gray-400 mb-1 block"></i>
                            <span class="text-xs text-gray-600 font-medium">Drag &amp; drop a photo/video here</span>
                        </div>
                        <input type="file" name="resolution_image" id="resImageAdmin" accept="image/*,video/*" style="display:none;" required onchange="handleFilePreview(this,'resImageAdminArea','resImageAdminPreviewImg','resImageAdminPreviewVideo','resPreviewAdmin')">
                        <span id="resPreviewAdmin" class="text-xs text-gray-500 block mt-2">Photo (Max 5MB) or video (Max 10MB)</span>
                    </div>
                    <div class="file-upload-choice mt-2">
                        <button type="button" onclick="openEvidenceCamera('resImageAdmin','resImageAdminArea','resImageAdminPreviewImg','resImageAdminPreviewVideo','resPreviewAdmin')"><i class="fas fa-camera"></i><span>Take Photo</span></button>
                        <button type="button" onclick="triggerFileInput('resImageAdmin','gallery')"><i class="fas fa-images"></i><span>Choose from Gallery</span></button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Resolution note</label>
                    <?php if (!empty($resolve_templates)): ?>
                    <div class="qn-wrap mb-1.5">
                        <div class="qn-suggestions-header">
                            <span class="qn-suggestions-title">Resolution suggestions</span>
                        </div>
                        <div class="qn-chips">
                            <?php foreach ($resolve_templates as $tpl_index => $tpl_text): ?>
                            <button type="button" class="note-template-chip" onclick="insertNoteTemplate('RESOLVE_TEMPLATES', <?php echo $tpl_index; ?>, 'resolutionNoteAdmin')" title="<?php echo htmlspecialchars($tpl_text, ENT_QUOTES); ?>">
                                <span class="chip-text"><?php echo htmlspecialchars($tpl_text); ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <textarea name="resolution_note" id="resolutionNoteAdmin" rows="3" class="w-full border-2 border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:border-[#10A37F] focus:ring-2 focus:ring-[#10A37F]/20 outline-none" placeholder="Describe how the issue was resolved"></textarea>
                </div>
                <div class="action-form-footer"><button type="button" class="btn-secondary" onclick="closeActionModal('resolveAdminModal')">Back</button><button type="submit" class="btn-success modal-submit"><i class="fas fa-check mr-1.5"></i> Mark as Resolved</button></div>
            </form>
        </div>
    </div>
</div>

<!-- ===== EVIDENCE CAMERA MODAL (ported from submit-report) ===== -->
<div id="evidenceCameraModal" class="camera-modal">
    <div class="camera-mode-toggle">
        <button type="button" id="evPhotoModeBtn" class="mode-btn mode-active" data-mode="photo">
            <i class="fas fa-camera"></i> Photo
        </button>
        <button type="button" id="evVideoModeBtn" class="mode-btn" data-mode="video">
            <i class="fas fa-video"></i> Video
        </button>
    </div>

    <div class="camera-wrapper">
        <video id="evidenceVideo" autoplay playsinline muted></video>
        <canvas id="evidenceCanvas" style="display: none;"></canvas>

        <div class="viewfinder">
            <div class="corner tl"></div>
            <div class="corner tr"></div>
            <div class="corner bl"></div>
            <div class="corner br"></div>
            <div class="crosshair"></div>
        </div>

        <div id="evRecordingIndicator" class="recording-indicator" style="display:none;">
            <span class="rec-dot"></span>
            <span id="evRecTimer">00:00</span>
            <span class="rec-max">max 30s</span>
        </div>

        <div id="evCameraTips" class="camera-tips-overlay">
            <div class="tip-dismiss" onclick="dismissEvidenceCameraTips()">✕</div>
            <div id="evTipContent">
                <span class="tip-emoji">📸</span>
                <span class="tip-text"><strong>Hold steady</strong> and ensure good lighting for clear evidence.</span>
            </div>
        </div>
    </div>

    <div class="camera-controls">
        <button type="button" id="evSwitchCameraBtn" class="ctrl-btn switch-cam" title="Switch Camera">
            <i class="fas fa-sync-alt"></i>
        </button>
        <button type="button" id="evFlashToggleBtn" class="ctrl-btn flash" title="Toggle Flash">
            <i class="fas fa-bolt"></i>
        </button>
        <button type="button" id="evCaptureBtn" class="ctrl-btn capture" title="Capture Photo">
            <i class="fas fa-camera"></i>
        </button>
        <button type="button" id="evCloseCameraBtn" class="ctrl-btn close-cam" title="Close Camera">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<!-- html2canvas + jsPDF for PDF download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
// Initialize map
<?php if ($report['latitude'] && $report['longitude'] && $report['latitude'] != 0 && $report['longitude'] != 0): ?>
    var map = L.map('map').setView([<?php echo $report['latitude']; ?>, <?php echo $report['longitude']; ?>], MapLayers.getSettings().default_zoom);
    MapLayers.addControl(map);
    <?php
    // Show only the geographic outline belonging to this report.
    $reportMapFeatures = [];
    $reportMapBarangayKey = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string)($report['barangay_name'] ?? '')));
    $reportMapFiles = glob(BASE_PATH . 'geojson/barangay/*.geojson') ?: [];
    foreach ($reportMapFiles as $reportMapFile) {
        if (!is_file($reportMapFile) || basename($reportMapFile) === 'san-isidro.barangay.geojson' || strpos(basename($reportMapFile), '_with_reports') !== false) continue;
        $reportMapJson = json_decode((string)file_get_contents($reportMapFile), true);
        foreach (($reportMapJson['features'] ?? []) as $reportMapFeature) {
            $reportMapName = $reportMapFeature['properties']['barangay_name'] ?? $reportMapFeature['properties']['name'] ?? pathinfo($reportMapFile, PATHINFO_FILENAME);
            $reportMapKey = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string)$reportMapName));
            if ($reportMapBarangayKey !== '' && $reportMapKey === $reportMapBarangayKey && in_array($reportMapFeature['geometry']['type'] ?? '', ['Polygon', 'MultiPolygon'], true)) {
                $reportMapFeatures[] = $reportMapFeature;
            }
        }
    }
    ?>
    var reportMapBoundary = <?php echo json_encode(['type'=>'FeatureCollection', 'features'=>$reportMapFeatures], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    if (reportMapBoundary.features.length) {
        MapLayers.addBoundary(map, reportMapBoundary, {interactive:false});
    }

    L.marker([<?php echo $report['latitude']; ?>, <?php echo $report['longitude']; ?>], {
        icon: SierraMapClusters.icon(<?php echo (int)($report['severity_score'] ?? 0); ?>, <?php echo json_encode($report['category_name'] ?? 'Report', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)
    }).addTo(map);
<?php endif; ?>

// ===== AUTO-DISMISS TOAST FLASH MESSAGES =====
document.querySelectorAll('.toast-msg').forEach(toast => {
    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-6px)';
        setTimeout(() => toast.remove(), 300);
    }, 6000);
});

// ===== EXPANDABLE ACTION SECTIONS (escalate / reject / resolve / reclassify) =====
function toggleExpand(triggerEl) {
    const targetId = triggerEl.getAttribute('data-target');
    const section = document.getElementById(targetId);
    if (!section) return;
    const isOpen = section.classList.contains('open');
    section.classList.toggle('open', !isOpen);

    // If this element is the original opening trigger (not a Cancel button), toggle its active state
    if (triggerEl.classList.contains('action-trigger')) {
        triggerEl.classList.toggle('is-active', !isOpen);
    } else {
        // Cancel button inside the section — also reset the opening trigger's active state
        const opener = document.querySelector(`.action-trigger[data-target="${targetId}"]`);
        if (opener) opener.classList.remove('is-active');
    }

    if (!isOpen) {
        const firstField = section.querySelector('textarea, input[type="text"]');
        if (firstField) setTimeout(() => firstField.focus(), 300);
    }
}

// ===== SUBMIT BUTTON LOADING STATE =====
function setLoading(form) {
    const btn = form.querySelector('button[type="submit"]');
    if (btn && !btn.classList.contains('is-loading')) {
        btn.classList.add('is-loading');
        btn.disabled = true;
    }
    return true;
}

// ===== BRANDED CONFIRM MODAL (replaces native confirm()) =====
let pendingConfirmForm = null;
function handleReportFormSubmit(evt, form) {
    const message = form.getAttribute('data-confirm');
    if (!message) { setLoading(form); return true; }
    if (form.dataset.confirmed === 'true') { setLoading(form); return true; }
    evt.preventDefault();
    pendingConfirmForm = form;
    const positive = ['verify_report','reclassify_impact','approve_escalation','resolve_report'].includes(form.querySelector('[name="action"]')?.value);
    document.querySelector('#confirmModalOverlay .confirm-modal-card').classList.toggle('is-positive', positive);
    document.querySelector('#confirmModalOverlay .confirm-modal-icon i').className = positive ? 'fas fa-circle-check' : 'fas fa-triangle-exclamation';
    document.getElementById('confirmModalProceed').className = (positive ? 'btn-primary' : 'btn-danger') + ' flex-1';
    document.getElementById('confirmModalMessage').textContent = message;
    document.getElementById('confirmModalOverlay').classList.add('open');
    return false;
}
function closeConfirmModal() {
    document.getElementById('confirmModalOverlay').classList.remove('open');
    pendingConfirmForm = null;
}
function proceedConfirmModal() {
    const form = pendingConfirmForm;
    if (!form) return;
    pendingConfirmForm = null;
    document.getElementById('confirmModalOverlay').classList.remove('open');
    form.dataset.confirmed = 'true';
    setLoading(form);
    form.submit();
}

// ===== SMART SUGGESTION TEMPLATES (Quick Note Templates) =====
// Canned responses configured by the MENRO admin in
// Settings > Quick Note Templates, matched here by the report's category
// and status. NOTE_TEMPLATES are ongoing suggestions for the investigation-
// note box (matched to the current status); RESOLVE_TEMPLATES are closing /
// thank-you suggestions for the resolution note (matched to 'resolved');
// ESCALATE_TEMPLATES are justification suggestions for the escalation modal
// (matched to 'escalated_pending'). Clicking a chip inserts its text.
var NOTE_TEMPLATES = <?php echo json_encode($note_templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var RESOLVE_TEMPLATES = <?php echo json_encode($resolve_templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var ESCALATE_TEMPLATES = <?php echo json_encode($escalate_templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
function insertNoteTemplate(listName, index, inputId) {
    var list = window[listName];
    var el = document.getElementById(inputId);
    if (!el || !list || !list[index]) return;
    el.value = (el.value && el.value.trim()) ? el.value.trim() + ' ' + list[index] : list[index];
    var suggestions = document.getElementById('noteQuickSuggestions');
    if (suggestions && inputId === 'noteInput') suggestions.classList.add('visible');
    el.focus();
}

document.addEventListener('DOMContentLoaded', function () {
    var noteInput = document.getElementById('noteInput');
    var suggestions = document.getElementById('noteQuickSuggestions');
    if (!noteInput || !suggestions) return;
    function toggleNoteSuggestions() {
        suggestions.classList.toggle('visible', document.activeElement === noteInput || suggestions.contains(document.activeElement) || noteInput.value.trim().length > 0);
    }
    noteInput.addEventListener('input', toggleNoteSuggestions);
    noteInput.addEventListener('focus', toggleNoteSuggestions);
    noteInput.addEventListener('click', toggleNoteSuggestions);
    noteInput.addEventListener('blur', function () { setTimeout(toggleNoteSuggestions, 0); });
    suggestions.addEventListener('focusout', function () { setTimeout(toggleNoteSuggestions, 0); });
    toggleNoteSuggestions();
});

// ===== GALLERY PICKER =====
// "Take Photo" now opens the real in-page camera (see openEvidenceCamera
// below); this just opens the normal file/gallery picker for "Choose from
// Gallery". Kept generic (mode param) in case a plain capture-attribute
// trigger is needed elsewhere.
function triggerFileInput(inputId, mode) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (mode === 'camera') {
        input.setAttribute('capture', 'environment');
    } else {
        input.removeAttribute('capture');
    }
    input.click();
}

function isVideoFile(file) {
    return !!file && (file.type.indexOf('video/') === 0 || /\.(mp4|webm|mov|m4v|avi)$/i.test(file.name));
}

// ===== FILE UPLOAD: DRAG & DROP + IMAGE/VIDEO PREVIEW =====
function handleFilePreview(input, areaId, imgId, videoId, labelId) {
    const area = document.getElementById(areaId);
    const img = document.getElementById(imgId);
    const video = document.getElementById(videoId);
    const label = document.getElementById(labelId);
    const file = input.files[0];
    if (file) {
        area.classList.add('has-file');
        const url = URL.createObjectURL(file);
        if (isVideoFile(file)) {
            if (video) { video.src = url; video.style.display = 'block'; }
            if (img) { img.removeAttribute('src'); img.style.display = 'none'; }
        } else {
            if (img) { img.src = url; img.style.display = 'block'; }
            if (video) { video.pause(); video.removeAttribute('src'); video.load(); video.style.display = 'none'; }
        }
        if (label) label.textContent = file.name + ' · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
    } else {
        area.classList.remove('has-file');
        if (img) img.style.display = 'none';
        if (video) video.style.display = 'none';
        if (label) label.textContent = 'No file selected';
    }
}
function handleFileDrop(evt, inputId) {
    evt.preventDefault();
    const input = document.getElementById(inputId);
    const area = input.closest('.file-upload-area');
    area.classList.remove('drag-over');
    if (evt.dataTransfer.files && evt.dataTransfer.files.length) {
        input.files = evt.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
    }
}

// ===== COPY GPS COORDINATES =====
function copyGps(btn) {
    const text = document.getElementById('gpsCoords').textContent.replace('GPS: ', '');
    navigator.clipboard.writeText(text).then(() => {
        const span = btn.querySelector('span');
        const original = span.textContent;
        span.textContent = 'Copied!';
        setTimeout(() => { span.textContent = original; }, 1500);
    });
}

// ===== PHOTO LIGHTBOX =====
<?php
$lightbox_media = array_map(function($img) {
    return ['path' => BASE_URL . $img['image_path'], 'is_video' => !empty($img['is_video'])];
}, $images);
$lightbox_media = array_merge($lightbox_media, array_map(function($ev) {
    return ['path' => BASE_URL . $ev['image_path'], 'is_video' => !empty($ev['is_video'])];
}, $resolution_evidence));
?>
const lightboxImages = <?php echo json_encode($lightbox_media); ?>;
let lightboxIndex = 0;
function openLightbox(index) {
    lightboxIndex = index;
    renderLightbox();
    document.getElementById('lightboxOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    const vid = document.getElementById('lightboxVideo');
    if (vid) { vid.pause(); vid.removeAttribute('src'); vid.load(); }
    document.getElementById('lightboxOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
function navLightbox(delta) {
    lightboxIndex = (lightboxIndex + delta + lightboxImages.length) % lightboxImages.length;
    renderLightbox();
}
function renderLightbox() {
    const item = lightboxImages[lightboxIndex];
    const img = document.getElementById('lightboxImg');
    const vid = document.getElementById('lightboxVideo');
    const counter = document.getElementById('lightboxCounter');
    if (item.is_video) {
        img.style.display = 'none';
        vid.style.display = '';
        vid.src = item.path;
        vid.play().catch(function() {});
    } else {
        if (vid) { vid.pause(); vid.removeAttribute('src'); vid.load(); }
        vid.style.display = 'none';
        img.style.display = '';
        img.src = item.path;
    }
    counter.textContent = (lightboxIndex + 1) + ' / ' + lightboxImages.length;
    document.querySelectorAll('.lightbox-nav').forEach(el => el.style.display = lightboxImages.length > 1 ? 'flex' : 'none');
}
document.addEventListener('keydown', function(e) {
    const overlay = document.getElementById('lightboxOverlay');
    if (overlay && overlay.classList.contains('open')) {
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowRight') navLightbox(1);
        if (e.key === 'ArrowLeft') navLightbox(-1);
    }
});

// ===== ACTION MODALS (barangay popup actions) =====
function syncReportReason(field) {
    const form = field.form;
    const custom = form.querySelector('[name="custom_reason"]');
    if (field === custom && custom.value.trim()) form.querySelector('[name="reason_choice"][value="Other"]').checked = true;
    const selected = form.querySelector('[name="reason_choice"]:checked');
    if (field !== custom && selected && selected.value !== 'Other') custom.value = '';
    const isOther = !!selected && selected.value === 'Other';
    custom.closest('.report-custom-reason').hidden = !isOther;
    custom.disabled = !isOther;
    custom.required = isOther;
    if (isOther && field !== custom) custom.focus();
    form.querySelector('[name="rejection_reason"]').value = selected ? (selected.value === 'Other' ? custom.value.trim() : selected.value) : '';
}
function submitReportReason(event, form) {
    syncReportReason(form.querySelector('[name="custom_reason"]'));
    if (form.querySelector('[name="rejection_reason"]').value.trim().length < 5) {
        event.preventDefault();
        const target = form.querySelector('[name="reason_choice"]:checked')?.value === 'Other' ? form.querySelector('[name="custom_reason"]') : form.querySelector('[name="reason_choice"]');
        if (target) target.focus();
        return false;
    }
    return handleReportFormSubmit(event, form);
}
function openActionModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.add('open');
    document.body.style.overflow = 'hidden';
    const input = m.querySelector('input:not([type="hidden"]):not([type="file"]), textarea, select');
    if (input) setTimeout(() => input.focus(), 50);
}
function closeActionModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('open');
    const cam = document.getElementById('evidenceCameraModal');
    if (!document.querySelector('.action-modal-overlay.open') && !(cam && cam.classList.contains('open'))) {
        document.body.style.overflow = '';
    }
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const openModal = document.querySelector('.action-modal-overlay.open');
        if (openModal) closeActionModal(openModal.id);
    }
});

// ===== PRINT DROPDOWN =====
function togglePrintMenu() {
    const menu = document.getElementById('printDropdownMenu');
    const btn  = document.getElementById('printDropdownTrigger');
    const isOpen = menu.classList.toggle('open');
    if (btn) btn.classList.toggle('active', isOpen);
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.querySelector('.export-dropdown');
    const menu = document.getElementById('printDropdownMenu');
    const btn  = document.getElementById('printDropdownTrigger');
    if (dropdown && menu && !dropdown.contains(e.target)) {
        menu.classList.remove('open');
        if (btn) btn.classList.remove('active');
    }
});

// ===== PRINT HANDLER =====
function handlePrint() {
    document.getElementById('printDropdownMenu').classList.remove('open');
    window.print();
}

// ===== CSV EXPORT =====
function handleDownloadCSV() {
    document.getElementById('printDropdownMenu').classList.remove('open');
    window.location.href = '<?php echo BASE_URL; ?>index.php?page=manage-report&id=<?php echo (int)$report['id']; ?>&export=csv';
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
            <p style="font-weight:700;color:var(--sierra-type-primary, #203b31);font-size:0.95rem;margin-bottom:4px;">Generating PDF</p>
            <p style="color:var(--sierra-type-muted, #63746b);font-size:0.8rem;">Please wait a moment...</p>
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
        '[onclick]', '.flash-message', '.toast-msg', '.file-upload-area',
        'a.bg-gray-100', 'select', 'input', 'textarea'
    ];
    removeSelectors.forEach(sel => {
        clone.querySelectorAll(sel).forEach(el => el.remove());
    });

    // 4. Show print footer in clone
    const footer = clone.querySelector('.print-footer');
    if (footer) footer.style.cssText = 'display:block; text-align:center; font-size:6.5pt; color:var(--sierra-type-muted, #63746b); border-top:1px solid #e5e7eb; padding-top:4px; margin-top:6px;';

    // 5. Create off-screen render container with compact width
    const wrapper = document.createElement('div');
    wrapper.id = 'pdf-render-container';
    wrapper.style.cssText = 'position:fixed; left:-9999px; top:0; width:680px; background:white; z-index:-1; font-family:Manrope,sans-serif; font-size:8.5pt; color:var(--sierra-type-primary, #203b31); padding:12px;';

    // 6. Apply compact styles to cloned elements
    // Cards
    clone.querySelectorAll('.card').forEach(c => {
        c.style.cssText = 'background:white; border:1px solid #e5e7eb; border-radius:8px; padding:7px 9px; margin-bottom:5px; box-shadow:none;';
    });
    clone.querySelectorAll('.card-header').forEach(h => {
        h.style.cssText = 'font-weight:700; font-size:8pt; color:var(--sierra-type-muted, #63746b); border-bottom:1px solid #e5e7eb; padding-bottom:3px; margin-bottom:5px; display:flex; align-items:center; gap:5px;';
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
        img.style.cssText = 'width:100%; height:50px; object-fit:cover; border-radius:8px; border:1px solid #e5e7eb;';
    });
    clone.querySelectorAll('.photo-grid video').forEach(vid => {
        vid.removeAttribute('controls');
        vid.muted = true;
        vid.style.cssText = 'width:100%; height:50px; object-fit:cover; border-radius:8px; border:1px solid #e5e7eb;';
    });
    // Map - replace with static text
    const mapEl = clone.querySelector('#map');
    if (mapEl) {
        mapEl.id = 'map-pdf-placeholder';
        mapEl.style.cssText = 'height:80px; border-radius:8px; border:1px solid #e5e7eb; background:#f0f4f0; display:flex; align-items:center; justify-content:center; color:var(--sierra-type-muted, #63746b); font-size:7pt;';
        mapEl.innerHTML = `<div style="text-align:center;"><i class="fas fa-map-marker-alt" style="color:#10A37F; font-size:14px; display:block; margin-bottom:2px;"></i>GPS: <?php echo number_format($report['latitude'], 6); ?>, <?php echo number_format($report['longitude'], 6); ?><?php if (!empty($report['location_address'])): ?><br><span style="font-size:6pt;"><?php echo htmlspecialchars($report['location_address']); ?></span><?php endif; ?></div>`;
    }
    // Notes
    clone.querySelectorAll('.note-item').forEach(n => {
        n.style.cssText = 'background:#F5FBF6; padding:3px 5px; border-radius:8px; margin-bottom:2px; border-left:2px solid #10A37F; font-size:7pt;';
    });
    clone.querySelectorAll('.max-h-60').forEach(el => { el.style.maxHeight = 'none'; el.style.overflow = 'visible'; });
    // Badges
    clone.querySelectorAll('.badge, .status-badge, .risk-badge').forEach(b => {
        b.style.fontSize = '6pt'; b.style.padding = '1px 5px';
    });
    // Gradient header card
    const gradientCard = clone.querySelector('.bg-gradient-to-r');
    if (gradientCard) {
        gradientCard.style.cssText = 'background:linear-gradient(135deg,#10A37F,#0D8568); border-radius:8px; padding:7px 10px; margin-bottom:5px; color:white; overflow:hidden;';
        const h2 = gradientCard.querySelector('h2');
        if (h2) h2.style.fontSize = '11pt';
        gradientCard.querySelectorAll('.bg-white\\/20').forEach(el => {
            el.style.cssText = 'display:inline-flex; align-items:center; gap:3px; padding:1px 5px; background:rgba(255,255,255,0.2); border-radius:8px; color:white; font-size:6.5pt;';
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
            window.GB.alert({ type: 'error', title: 'PDF failed', message: 'Failed to generate PDF. Please try the Print option instead.' });
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

<!-- ===== EVIDENCE CAMERA (ported from submit-report's Take Photo / Choose from Gallery) ===== -->
<script>
(function() {
    'use strict';

    const cameraModal = document.getElementById('evidenceCameraModal');
    const video = document.getElementById('evidenceVideo');
    const canvas = document.getElementById('evidenceCanvas');
    const closeCameraBtn = document.getElementById('evCloseCameraBtn');
    const captureBtn = document.getElementById('evCaptureBtn');
    const switchCameraBtn = document.getElementById('evSwitchCameraBtn');
    const flashToggleBtn = document.getElementById('evFlashToggleBtn');
    const photoModeBtn = document.getElementById('evPhotoModeBtn');
    const videoModeBtn = document.getElementById('evVideoModeBtn');
    const recordingIndicator = document.getElementById('evRecordingIndicator');
    const recTimer = document.getElementById('evRecTimer');

    if (!cameraModal || !video) return;

    // STATE
    let mediaStream = null;
    let facingMode = 'environment';
    let flashMode = false;
    let cameraMode = 'photo';
    let mediaRecorder = null;
    let recordedChunks = [];
    let isRecording = false;
    let recTimerInterval = null;
    let recStartTime = 0;
    let cameraTipTimer = null;

    // Which form field the camera is currently capturing for
    let target = null; // { inputId, areaId, imgId, videoId, labelId }

    const MAX_VIDEO_SIZE = 10 * 1024 * 1024;   // 10MB - matches server limit
    const MAX_VIDEO_DURATION = 30000;          // 30 seconds

    function showToast(message, type) {
        if (typeof window.showToast === 'function') { window.showToast(message, type); return; }
        // Minimal fallback toast if the page doesn't already define one
        const toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:' + (type === 'error' ? '#EF4444' : type === 'success' ? '#10A37F' : '#374151') + ';color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;z-index:100000;box-shadow:0 8px 24px rgba(0,0,0,0.25);';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // ============================================================
    // CAMERA TIPS
    // ============================================================
    const cameraTips = [
        { emoji: '📸', text: '<strong>Hold steady</strong> and ensure good lighting for clear evidence.' },
        { emoji: '🎯', text: '<strong>Frame the issue</strong> clearly — show the result in context.' },
        { emoji: '🔍', text: '<strong>Get close</strong> to capture details, then step back for the bigger picture.' }
    ];
    let cameraTipIndex = 0;

    function rotateCameraTip() {
        const tipContent = document.getElementById('evTipContent');
        if (!tipContent) return;
        cameraTipIndex = (cameraTipIndex + 1) % cameraTips.length;
        const tip = cameraTips[cameraTipIndex];
        tipContent.innerHTML = '<span class="tip-emoji">' + tip.emoji + '</span><span class="tip-text">' + tip.text + '</span>';
    }

    function startCameraTips() {
        cameraTipIndex = 0;
        const tipContent = document.getElementById('evTipContent');
        if (tipContent) {
            const tip = cameraTips[0];
            tipContent.innerHTML = '<span class="tip-emoji">' + tip.emoji + '</span><span class="tip-text">' + tip.text + '</span>';
        }
        clearInterval(cameraTipTimer);
        cameraTipTimer = setInterval(rotateCameraTip, 5000);
    }

    function stopCameraTips() {
        clearInterval(cameraTipTimer);
    }

    window.dismissEvidenceCameraTips = function() {
        const overlay = document.getElementById('evCameraTips');
        if (overlay) overlay.style.opacity = '0';
        stopCameraTips();
    };

    // ============================================================
    // CAMERA LIFECYCLE
    // ============================================================
    function setCameraMode(mode) {
        cameraMode = mode;
        photoModeBtn.classList.toggle('mode-active', mode === 'photo');
        videoModeBtn.classList.toggle('mode-active', mode === 'video');
        captureBtn.classList.remove('recording');
        recordingIndicator.style.display = 'none';
        if (mode === 'video') {
            captureBtn.title = 'Start Recording';
            captureBtn.innerHTML = '<i class="fas fa-video"></i>';
        } else {
            captureBtn.title = 'Capture Photo';
            captureBtn.innerHTML = '<i class="fas fa-camera"></i>';
        }
    }

    // Called from the "Take Photo" buttons on the resolve / escalate forms
    window.openEvidenceCamera = async function(inputId, areaId, imgId, videoId, labelId) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showToast('Camera not supported on this device/browser', 'error');
            return;
        }
        target = { inputId: inputId, areaId: areaId, imgId: imgId, videoId: videoId, labelId: labelId };
        try {
            const isVideoMode = cameraMode === 'video';
            const constraints = {
                video: {
                    facingMode: facingMode,
                    width: { ideal: isVideoMode ? 1280 : 1920 },
                    height: { ideal: isVideoMode ? 720 : 1080 },
                    frameRate: { ideal: 30, max: 30 }
                },
                audio: isVideoMode
            };
            mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = mediaStream;
            await video.play();

            cameraModal.classList.add('active');
            document.body.style.overflow = 'hidden';

            const tipsOverlay = document.getElementById('evCameraTips');
            tipsOverlay.style.display = 'block';
            tipsOverlay.style.opacity = '1';
            startCameraTips();

            flashMode = false;
            flashToggleBtn.classList.remove('active');

            setCameraMode(cameraMode);
        } catch (error) {
            console.error('Camera error:', error);
            showToast('Unable to access camera. Please grant camera permission.', 'error');
        }
    };

    function closeCamera() {
        if (isRecording) stopRecording();
        if (mediaStream) {
            mediaStream.getTracks().forEach(function(track) { track.stop(); });
            mediaStream = null;
        }
        video.srcObject = null;
        cameraModal.classList.remove('active');
        document.body.style.overflow = '';
        stopCameraTips();
        captureBtn.classList.remove('recording');
        recordingIndicator.style.display = 'none';
    }

    // Push a captured File into the target's hidden <input type=file> and
    // refresh its preview, reusing the page's existing handleFilePreview().
    function applyCapturedFile(file) {
        if (!target) return;
        const input = document.getElementById(target.inputId);
        if (!input) return;
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        if (typeof window.handleFilePreview === 'function') {
            window.handleFilePreview(input, target.areaId, target.imgId, target.videoId, target.labelId);
        }
    }

    function capturePhoto() {
        const context = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function(blob) {
            if (blob) {
                const file = new File([blob], 'evidence_' + Date.now() + '.jpg', { type: 'image/jpeg' });
                applyCapturedFile(file);
                showToast('Photo captured successfully!', 'success');
            }
            closeCamera();
        }, 'image/jpeg', 0.92);
    }

    function formatRecTime(ms) {
        const totalSec = Math.floor(ms / 1000);
        const mm = String(Math.floor(totalSec / 60)).padStart(2, '0');
        const ss = String(totalSec % 60).padStart(2, '0');
        return mm + ':' + ss;
    }

    function startRecording() {
        if (!mediaStream || isRecording) return;
        if (mediaStream.getVideoTracks().length === 0) {
            showToast('No video track available', 'error');
            return;
        }
        recordedChunks = [];
        let mimeType = 'video/webm';
        if (window.MediaRecorder && MediaRecorder.isTypeSupported('video/webm;codecs=vp9')) {
            mimeType = 'video/webm;codecs=vp9';
        } else if (window.MediaRecorder && MediaRecorder.isTypeSupported('video/webm;codecs=vp8')) {
            mimeType = 'video/webm;codecs=vp8';
        } else if (window.MediaRecorder && MediaRecorder.isTypeSupported('video/mp4')) {
            mimeType = 'video/mp4';
        }
        try {
            mediaRecorder = new MediaRecorder(mediaStream, { mimeType: mimeType, videoBitsPerSecond: 1200000, audioBitsPerSecond: 128000 });
        } catch (e) {
            try {
                mediaRecorder = new MediaRecorder(mediaStream, { videoBitsPerSecond: 1200000 });
            } catch (e2) {
                try {
                    mediaRecorder = new MediaRecorder(mediaStream);
                } catch (e3) {
                    showToast('Video recording not supported on this browser', 'error');
                    return;
                }
            }
        }
        mediaRecorder.ondataavailable = function(e) {
            if (e.data && e.data.size > 0) recordedChunks.push(e.data);
        };
        mediaRecorder.onstop = onRecordingStopped;
        mediaRecorder.start(1000);
        isRecording = true;
        recStartTime = Date.now();
        captureBtn.classList.add('recording');
        captureBtn.title = 'Stop Recording';
        captureBtn.innerHTML = '<i class="fas fa-stop"></i>';
        recordingIndicator.style.display = 'flex';
        recTimer.textContent = '00:00';
        stopCameraTips();
        clearInterval(recTimerInterval);
        recTimerInterval = setInterval(function() {
            const elapsed = Date.now() - recStartTime;
            recTimer.textContent = formatRecTime(elapsed);
            if (elapsed >= MAX_VIDEO_DURATION) {
                showToast('Maximum recording time reached (30s)', 'info');
                stopRecording();
            }
        }, 250);
    }

    function stopRecording() {
        if (!isRecording) return;
        clearInterval(recTimerInterval);
        isRecording = false;
        try {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            } else {
                onRecordingStopped();
            }
        } catch (e) {
            onRecordingStopped();
        }
    }

    function onRecordingStopped() {
        captureBtn.classList.remove('recording');
        recordingIndicator.style.display = 'none';
        const rawType = recordedChunks.length > 0 && recordedChunks[0].type ? recordedChunks[0].type : 'video/webm';
        const blobType = rawType.split(';')[0].trim() || 'video/webm';
        const blob = new Blob(recordedChunks, { type: blobType });
        const ext = (blobType.indexOf('mp4') !== -1) ? 'mp4' : 'webm';
        const sizeMB = (blob.size / (1024 * 1024)).toFixed(2);
        if (blob.size < 1024) {
            showToast('Recording was too short. Please try again.', 'warning');
            startCameraTips();
            return;
        }
        if (blob.size > MAX_VIDEO_SIZE) {
            showToast('Video too large (' + sizeMB + 'MB). Max 10MB. Try a shorter clip.', 'error');
            startCameraTips();
            return;
        }
        const file = new File([blob], 'evidence_' + Date.now() + '.' + ext, { type: blobType });
        applyCapturedFile(file);
        showToast('Video recorded (' + sizeMB + 'MB)', 'success');
        closeCamera();
    }

    async function switchCamera() {
        if (isRecording) {
            showToast('Stop recording before switching camera', 'warning');
            return;
        }
        facingMode = (facingMode === 'environment') ? 'user' : 'environment';
        if (mediaStream) {
            const t = target;
            closeCamera();
            await new Promise(resolve => setTimeout(resolve, 300));
            target = t;
            await window.openEvidenceCamera(t.inputId, t.areaId, t.imgId, t.videoId, t.labelId);
        }
    }

    function toggleFlash() {
        if (cameraMode === 'video') return;
        flashMode = !flashMode;
        flashToggleBtn.classList.toggle('active', flashMode);
        if (mediaStream) {
            const track = mediaStream.getVideoTracks()[0];
            if (track) {
                const capabilities = track.getCapabilities ? track.getCapabilities() : {};
                if (capabilities.torch) {
                    track.applyConstraints({ advanced: [{ torch: flashMode }] }).catch(() => {});
                } else {
                    showToast('Flash not supported on this device', 'info');
                    flashToggleBtn.classList.remove('active');
                    flashMode = false;
                }
            }
        }
    }

    async function changeCameraMode(mode) {
        if (mode === cameraMode) return;
        if (isRecording) {
            showToast('Stop recording before switching mode', 'warning');
            return;
        }
        setCameraMode(mode);
        if (cameraMode === 'video') {
            flashMode = false;
            flashToggleBtn.classList.remove('active');
        }
        if (mediaStream) {
            const t = target;
            closeCamera();
            await new Promise(resolve => setTimeout(resolve, 300));
            target = t;
            await window.openEvidenceCamera(t.inputId, t.areaId, t.imgId, t.videoId, t.labelId);
        }
    }

    closeCameraBtn.addEventListener('click', closeCamera);
    captureBtn.addEventListener('click', function() {
        if (cameraMode === 'video') {
            if (isRecording) stopRecording(); else startRecording();
        } else {
            capturePhoto();
        }
    });
    switchCameraBtn.addEventListener('click', switchCamera);
    flashToggleBtn.addEventListener('click', toggleFlash);
    photoModeBtn.addEventListener('click', function() { changeCameraMode('photo'); });
    videoModeBtn.addEventListener('click', function() { changeCameraMode('video'); });
})();
</script>

</body>
</html>
