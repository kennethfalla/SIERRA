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
        .photo-grid video { width: 100%; height: 120px; object-fit: cover; border-radius: 0.75rem; cursor: pointer; border: 1px solid rgba(16,163,127,0.08); background: #000; }
        .photo-grid video:hover { transform: scale(1.02); }
        .photo-card { position: relative; }

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
        .risk-high { background: #FFEDD5; color: #9A3412; }
        .risk-critical { background: #FEE2E2; color: #991B1B; }

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
            .photo-grid img,
            .photo-grid video {
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

        /* ===== FOCUS ACCESSIBILITY ===== */
        a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible, select:focus-visible, [tabindex]:focus-visible {
            outline: 2px solid #10A37F; outline-offset: 2px; border-radius: 4px;
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
        .section-eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.05em; color: #6b7280; text-transform: uppercase; }

        /* ===== EXPANDABLE ACTION FORM ===== */
        .expand-section { display: grid; grid-template-rows: 0fr; opacity: 0; transition: grid-template-rows 0.3s ease, opacity 0.25s ease, margin 0.3s ease; margin-top: 0; }
        .expand-section.open { grid-template-rows: 1fr; opacity: 1; margin-top: 0.5rem; }
        .expand-section > div { overflow: hidden; min-height: 0; }
        .expand-section-inner { padding: 1rem; }

        /* ===== ACTION TRIGGER BUTTON ===== */
        .action-trigger.is-active { box-shadow: 0 0 0 3px rgba(16,163,127,0.25) inset; }

        /* ===== BUTTON LOADING STATE ===== */
        button[type="submit"].is-loading { pointer-events: none; opacity: 0.75; position: relative; color: transparent !important; }
        button[type="submit"].is-loading::after {
            content: ''; position: absolute; top: 50%; left: 50%; width: 16px; height: 16px; margin: -8px 0 0 -8px;
            border: 2px solid rgba(255,255,255,0.5); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite;
        }
        button[type="submit"].btn-secondary.is-loading::after { border: 2px solid rgba(16,163,127,0.3); border-top-color: #10A37F; }

        /* ===== FILE UPLOAD (DRAG & DROP + PREVIEW) ===== */
        .file-upload-area { position: relative; overflow: hidden; }
        .file-upload-area.drag-over { border-color: #10A37F; background: #E8F5F0; }
        .file-upload-area.has-file { border-style: solid; border-color: #10A37F; background: #F5FBF6; padding: 10px; }
        .file-upload-preview { display: none; max-height: 110px; border-radius: 0.5rem; margin: 0 auto 8px; object-fit: cover; }
        .file-upload-area.has-file .file-upload-preview { display: block; }
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
        .confirm-modal-overlay { position: fixed; inset: 0; background: rgba(15,23,20,0.55); backdrop-filter: blur(3px); z-index: 10000; display: none; align-items: center; justify-content: center; animation: fadeIn 0.15s ease; padding: 1rem; }
        .confirm-modal-overlay.open { display: flex; }
        .confirm-modal-card { background: white; border-radius: 1rem; padding: 1.5rem; max-width: 380px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.25); animation: fadeUp 0.2s ease; }
        .confirm-modal-icon { width: 44px; height: 44px; border-radius: 9999px; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 12px; }

        /* ===== COPY BUTTON ===== */
        .copy-btn { display: inline-flex; align-items: center; gap: 4px; color: #6b7280; cursor: pointer; transition: color 0.15s ease; border: none; background: none; font-size: inherit; padding: 0; }
        .copy-btn:hover { color: #10A37F; }

        /* ===== EMPTY STATE ===== */
        .empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1rem; text-align: center; color: #9CA3AF; }
        .empty-state i { font-size: 1.75rem; margin-bottom: 8px; opacity: 0.5; }

        /* ===== NOTE AVATAR ===== */
        .note-avatar { width: 26px; height: 26px; border-radius: 9999px; background: linear-gradient(135deg,#10A37F,#0D8568); color: white; font-size: 0.65rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

        /* ===== ENHANCED ACTION PANEL ===== */
        /* Status Stepper */
        .status-stepper { display: flex; align-items: flex-start; gap: 0; padding: 0 0.25rem; overflow-x: auto; }
        .status-step { display: flex; flex-direction: column; align-items: center; flex: 1; min-width: 68px; position: relative; }
        .status-step .step-dot { width: 38px; height: 38px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; background: #E5E7EB; color: #9CA3AF; border: 3px solid #fff; box-shadow: 0 0 0 2px #E5E7EB; z-index: 2; transition: all 0.2s ease; flex-shrink: 0; }
        .status-step .step-label { margin-top: 8px; font-size: 0.66rem; font-weight: 700; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.03em; text-align: center; white-space: nowrap; }
        .status-step .step-date { font-size: 0.58rem; color: #C0C8D0; margin-top: 2px; text-align: center; font-weight: 500; }
        .status-step.done .step-dot { background: linear-gradient(135deg,#10A37F,#0D8568); color: white; box-shadow: 0 0 0 2px #10A37F; }
        .status-step.done .step-label { color: #0D8568; }
        .status-step.active .step-dot { background: linear-gradient(135deg,#10A37F,#0D8568); color: white; box-shadow: 0 0 0 3px rgba(16,163,127,0.28); animation: pulseDot 1.8s infinite; }
        .status-step.active .step-label { color: #10A37F; }
        @keyframes pulseDot { 0%,100% { box-shadow: 0 0 0 3px rgba(16,163,127,0.28); } 50% { box-shadow: 0 0 0 7px rgba(16,163,127,0.12); } }
        .status-step.danger .step-dot { background: linear-gradient(135deg,#DC2626,#B91C1C); color: white; box-shadow: 0 0 0 3px rgba(220,38,38,0.28); }
        .status-step.danger .step-label { color: #B91C1C; }
        .step-connector { flex: 1; height: 3px; background: #E5E7EB; margin-top: 18px; min-width: 12px; border-radius: 2px; }
        .step-connector.done { background: linear-gradient(90deg,#10A37F,#0D8568); }

        /* Action Cards */
        .action-cards { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 768px) { .action-cards { grid-template-columns: repeat(3, 1fr); } }
        .action-card { background: white; border: 1px solid #E5E7EB; border-radius: 1rem; padding: 1rem; transition: all 0.2s ease; }
        .action-card:hover { border-color: #10A37F; box-shadow: 0 4px 16px -6px rgba(16,163,127,0.18); }
        .action-card .action-icon { width: 42px; height: 42px; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1rem; }
        .action-card .action-btn { width: 100%; display: inline-flex; align-items: center; justify-content: center; }
        .action-card .action-expand { grid-column: 1 / -1; }
        .action-expand { grid-column: 1 / -1; }

        /* Context Callouts */
        .action-callout { display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 0.9rem; font-size: 0.82rem; }
        .action-callout.info { background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E40AF; }
        .action-callout.warning { background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; }
        .action-callout.success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
        .action-callout.danger { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; }
        .action-callout .callout-title { font-weight: 700; }
        .action-callout .callout-sub { font-size: 0.75rem; margin-top: 2px; opacity: 0.9; }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<div class="ml-72 min-h-screen">
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
                            <?php if (PermissionHelper::userHasPermission('can_export_reports')): ?>
                            <button class="print-dropdown-item" onclick="handleDownloadPDF()">
                                <i class="fas fa-file-pdf"></i>
                                <span>Download PDF</span>
                            </button>
                            <?php endif; ?>
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
        <div class="fade-up bg-gradient-to-r from-[#10A37F] to-[#0D8568] rounded-2xl shadow-xl overflow-hidden mb-6 md:mb-8 relative">
            <div class="absolute inset-0 opacity-[0.06] pointer-events-none" style="background-image: radial-gradient(circle at 90% 10%, white 0%, transparent 45%);"></div>
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
        <div class="two-col fade-up" style="animation-delay:0.05s">
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
                    <?php
                    $mgr_score = (int)($report['severity_score'] ?? 0);
                    $mgr_level = getRiskLevelFromScore($mgr_score);
                    $mgr_color = ($mgr_level == 'critical') ? 'text-red-600' : (($mgr_level == 'high') ? 'text-orange-600' : (($mgr_level == 'medium') ? 'text-amber-600' : 'text-emerald-600'));
                    ?>
                    <div class="info-row"><span class="info-label">Severity Score</span><span class="info-value font-bold <?php echo $mgr_color; ?>"><?php echo $mgr_score; ?></span></div>
                    <div class="info-row"><span class="info-label">Classification</span><span class="info-value"><?php echo $report['decision_classification'] ?? 'Pending'; ?></span></div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="card fade-up" style="animation-delay:0.1s">
            <div class="card-header"><i class="fas fa-align-left"></i> Resident's Description</div>
            <div class="text-gray-700 leading-relaxed whitespace-pre-line"><?php echo nl2br(htmlspecialchars($report['description'])); ?></div>
        </div>

        <!-- Two Columns: Photo + Map -->
        <div class="two-col fade-up" style="animation-delay:0.15s">
            <div class="card">
                <div class="card-header"><i class="fas fa-image"></i> Evidentiary Photo</div>
                <?php if (!empty($images)): ?>
                    <div class="photo-grid">
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

            <div class="card">
                <div class="card-header"><i class="fas fa-map-pin"></i> Geographic Location</div>
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
        </div>

        <!-- Resolution Evidence -->
        <div class="card fade-up" style="animation-delay:0.18s">
            <div class="card-header"><i class="fas fa-check-circle" style="color:#10A37F"></i> Resolution Evidence</div>
            <?php if (!empty($resolution_evidence)): ?>
                <div class="photo-grid">
                    <?php foreach ($resolution_evidence as $ev): ?>
                        <?php if (!empty($ev['is_video'])): ?>
                            <div class="photo-card relative" onclick="openLightbox(<?php echo count($images) + (int)array_search($ev, $resolution_evidence, true); ?>)" role="button" tabindex="0" onkeydown="if(event.key==='Enter')openLightbox(<?php echo count($images) + (int)array_search($ev, $resolution_evidence, true); ?>)">
                                <video src="<?php echo BASE_URL . $ev['image_path']; ?>" muted playsinline preload="metadata"></video>
                                <div class="absolute top-2 left-2 bg-black/70 text-white text-[10px] px-2 py-0.5 rounded-full flex items-center gap-1">
                                    <i class="fas fa-video"></i>Video
                                </div>
                            </div>
                        <?php else: ?>
                            <img src="<?php echo BASE_URL . $ev['image_path']; ?>" onclick="openLightbox(<?php echo count($images) + (int)array_search($ev, $resolution_evidence, true); ?>)" alt="Resolution evidence photo" loading="lazy" tabindex="0" onkeydown="if(event.key==='Enter')openLightbox(<?php echo count($images) + (int)array_search($ev, $resolution_evidence, true); ?>)">
                        <?php endif; ?>
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

        <!-- Notes -->
        <?php if ($show_notes): ?>
        <div class="card fade-up" style="animation-delay:0.2s">
            <div class="card-header"><i class="fas fa-sticky-note"></i> Investigation Notes</div>
            <div class="max-h-60 overflow-y-auto mb-4 space-y-2 pr-1">
                <?php if (!empty($notes)): ?>
                    <?php foreach ($notes as $note): ?>
                        <div class="note-item flex gap-2.5">
                            <div class="note-avatar" aria-hidden="true"><?php echo strtoupper(substr($note['user_name'], 0, 1)); ?></div>
                            <div class="min-w-0">
                                <p class="text-sm text-gray-700"><?php echo htmlspecialchars($note['note']); ?></p>
                                <p class="text-xs text-gray-400 mt-1"><span class="font-medium text-gray-500"><?php echo htmlspecialchars($note['user_name']); ?></span> • <?php echo date('M d, h:i A', strtotime($note['created_at'])); ?></p>
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
            <?php if ($can_manage && ($user_role == 'barangay_official' || $user_role == 'admin')): ?>
            <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="flex gap-2" onsubmit="setLoading(this)">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                <input type="text" name="note" placeholder="Add an investigation note..." maxlength="500" required class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-colors">
                <button type="submit" class="btn-primary whitespace-nowrap"><i class="fas fa-plus mr-1.5"></i>Add Note</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 🛠️ ACTION & MANAGEMENT PANEL -->
        <div class="action-panel fade-up">
            <div class="flex items-center gap-3 pb-3 mb-4 border-b border-gray-100">
                <div class="w-9 h-9 rounded-xl bg-[#10A37F]/10 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-tools text-[#10A37F] text-sm"></i>
                </div>
                <div>
                    <p class="font-bold text-gray-800 text-sm">Action &amp; Management Panel</p>
                    <p class="text-xs text-gray-400">Choose what happens next with this report</p>
                </div>
            </div>

            <?php
            // Only show the Escalation / With MENRO steps in the lifecycle when the
            // report has actually been escalated. Otherwise the stepper is a clean
            // Submitted -> Under Review -> In Progress -> Resolved flow.
            $report_escalated = in_array($report['status'], ['escalated_pending', 'escalated']);
            $flow_steps = [
                ['key' => 'pending',          'label' => 'Submitted',    'icon' => 'fa-paper-plane'],
                ['key' => 'under_review',     'label' => 'Under Review', 'icon' => 'fa-eye'],
                ['key' => 'in_progress',      'label' => 'In Progress',  'icon' => 'fa-wrench'],
            ];
            if ($report_escalated) {
                $flow_steps[] = ['key' => 'escalated_pending', 'label' => 'Escalation',   'icon' => 'fa-hourglass-half'];
                $flow_steps[] = ['key' => 'escalated',         'label' => 'With MENRO',   'icon' => 'fa-building-shield'];
            }
            $flow_steps[] = ['key' => 'resolved',              'label' => 'Resolved',     'icon' => 'fa-check-double'];

            $status_order = [];
            foreach ($flow_steps as $step_index => $step) {
                $status_order[$step['key']] = $step_index;
            }
            $status_order['verified'] = $status_order['under_review'] ?? 1;
            $current_step = $status_order[$report['status']] ?? 0;
            $is_terminal = in_array($report['status'], ['rejected', 'cancelled']);
            ?>

            <!-- STATUS LIFECYCLE STEPPER -->
            <div class="status-stepper mb-5 no-print" role="list" aria-label="Report lifecycle">
                <?php if ($is_terminal): ?>
                    <div class="status-step done">
                        <div class="step-dot"><i class="fas fa-paper-plane"></i></div>
                        <div class="step-label">Submitted</div>
                    </div>
                    <div class="step-connector done"></div>
                    <div class="status-step active danger">
                        <div class="step-dot"><i class="fas <?php echo ($report['status'] === 'cancelled') ? 'fa-ban' : 'fa-xmark'; ?>"></i></div>
                        <div class="step-label"><?php echo ($report['status'] === 'cancelled') ? 'Cancelled' : 'Rejected'; ?></div>
                    </div>
                <?php else: ?>
                    <?php foreach ($flow_steps as $i => $s):
                        $state = ($i < $current_step) ? 'done' : (($i === $current_step) ? 'active' : '');
                    ?>
                        <?php if ($i > 0): ?><div class="step-connector <?php echo ($i <= $current_step) ? 'done' : ''; ?>"></div><?php endif; ?>
                        <div class="status-step <?php echo $state; ?>">
                            <div class="step-dot"><i class="fas <?php echo $s['icon']; ?>"></i></div>
                            <div class="step-label"><?php echo $s['label']; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- CONTEXT CALLOUTS -->
            <?php if ($report['status'] == 'escalated_pending'): ?>
                <div class="action-callout warning mb-5">
                    <i class="fas fa-hourglass-half mt-0.5"></i>
                    <div>
                        <p class="callout-title">Needs your attention</p>
                        <p class="callout-sub">This report is pending MENRO approval.<?php if (!empty($escalation['escalation_reason'])): ?> <strong>Justification:</strong> <?php echo htmlspecialchars($escalation['escalation_reason']); ?><?php endif; ?></p>
                    </div>
                </div>
            <?php elseif ($report['status'] == 'escalated'): ?>
                <div class="action-callout info mb-5">
                    <i class="fas fa-building-shield mt-0.5"></i>
                    <div>
                        <p class="callout-title">Under MENRO supervision</p>
                        <p class="callout-sub">This report has been escalated and is now being managed by MENRO.</p>
                    </div>
                </div>
            <?php elseif ($report['status'] == 'resolved'): ?>
                <div class="action-callout success mb-5">
                    <i class="fas fa-check-double mt-0.5"></i>
                    <div>
                        <p class="callout-title">Report resolved</p>
                        <p class="callout-sub"><?php if (!empty($report['resolved_at'])): ?>Closed on <?php echo date('F d, Y h:i A', strtotime($report['resolved_at'])); ?>.<?php endif; ?> No further action is required.</p>
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
            <?php elseif ($report['status'] == 'in_progress'): ?>
                <div class="action-callout info mb-5">
                    <i class="fas fa-wrench mt-0.5"></i>
                    <div>
                        <p class="callout-title">Work in progress</p>
                        <p class="callout-sub">A resolution is currently being worked on for this report.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ACTION CARDS GRID -->
            <div class="action-cards">

            <?php if ($user_role == 'barangay_official'): ?>
                <?php if ($can_reclassify): ?>
                <div class="action-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="action-icon bg-indigo-50 text-indigo-600"><i class="fas fa-rotate"></i></div>
                        <div class="min-w-0">
                            <p class="font-bold text-gray-800 text-sm">Reclassify Risk Level</p>
                            <p class="text-xs text-gray-400 truncate">Adjust impact for in-progress reports</p>
                        </div>
                    </div>
                    <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="space-y-3" onsubmit="setLoading(this)">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="reclassify_impact">
                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                        <select name="new_impact" class="w-full border-2 border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none">
                            <option value="0" <?php echo ($report['impact_modifier'] == 0) ? 'selected' : ''; ?>>🟢 Low Risk (Localized)</option>
                            <option value="2" <?php echo ($report['impact_modifier'] == 2) ? 'selected' : ''; ?>>🟡 Medium Risk (Moderate)</option>
                            <option value="4" <?php echo ($report['impact_modifier'] == 4) ? 'selected' : ''; ?>>🔴 High Risk (Severe)</option>
                        </select>
                        <input type="text" name="reclassify_reason" placeholder="Reason for risk level change..." class="w-full border-2 border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none" required>
                        <button type="submit" class="action-btn btn-indigo"><i class="fas fa-save mr-2"></i> Update Risk</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($can_verify): ?>
                <div class="action-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="action-icon bg-blue-50 text-blue-600"><i class="fas fa-check"></i></div>
                        <div class="min-w-0">
                            <p class="font-bold text-gray-800 text-sm">Verify Report</p>
                            <p class="text-xs text-gray-400 truncate">Mark this report as legitimate</p>
                        </div>
                    </div>
                    <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" onsubmit="setLoading(this)">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="verify_report">
                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                        <button type="submit" class="action-btn btn-primary"><i class="fas fa-check mr-2"></i> Verify Report</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($can_escalate): ?>
                <div class="action-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="action-icon bg-amber-50 text-amber-600"><i class="fas fa-share"></i></div>
                        <div class="min-w-0">
                            <p class="font-bold text-gray-800 text-sm">Escalate to MENRO</p>
                            <p class="text-xs text-gray-400 truncate">Request MENRO intervention</p>
                        </div>
                    </div>
                    <button type="button" data-target="escalateFormSection" onclick="toggleExpand(this)" class="action-trigger action-btn btn-warning">
                        <i class="fas fa-share mr-2"></i> Escalate to MENRO
                    </button>
                    <div id="escalateFormSection" class="expand-section mt-3 bg-amber-50 border-2 border-amber-200 rounded-xl"><div>
                        <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="expand-section-inner space-y-3" onsubmit="setLoading(this)">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="escalate_report">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <label class="block text-sm font-semibold text-gray-700"><i class="fas fa-share text-amber-600 mr-1"></i> Justification for escalation</label>
                            <textarea name="escalation_reason" rows="3" class="w-full border-2 border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:border-[#10A37F] focus:ring-2 focus:ring-[#10A37F]/20 outline-none" placeholder="Explain why this report needs to be escalated to MENRO..." required></textarea>
                            <div class="flex gap-2">
                                <button type="submit" class="btn-warning flex-1 px-4 py-2 text-xs"><i class="fas fa-paper-plane mr-1.5"></i> Confirm Escalation</button>
                                <button type="button" data-target="escalateFormSection" onclick="toggleExpand(this)" class="btn-secondary px-4 py-2 text-xs">Cancel</button>
                            </div>
                        </form>
                    </div></div>
                </div>
                <?php endif; ?>

                <?php if ($can_reject): ?>
                <div class="action-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="action-icon bg-red-50 text-red-600"><i class="fas fa-ban"></i></div>
                        <div class="min-w-0">
                            <p class="font-bold text-gray-800 text-sm">Reject Report</p>
                            <p class="text-xs text-gray-400 truncate">Refuse this report and notify resident</p>
                        </div>
                    </div>
                    <button type="button" data-target="rejectFormSection" onclick="toggleExpand(this)" class="action-trigger action-btn btn-danger">
                        <i class="fas fa-times-circle mr-2"></i> Reject Report
                    </button>
                    <div id="rejectFormSection" class="expand-section mt-3 bg-red-50 border-2 border-red-200 rounded-xl"><div>
                        <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="expand-section-inner space-y-3" data-confirm="Are you sure you want to reject this report? This action will notify the resident." onsubmit="return handleReportFormSubmit(event, this)">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="reject_report">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <label class="block text-sm font-semibold text-gray-700"><i class="fas fa-ban text-red-600 mr-1"></i> Reason for rejection</label>
                            <textarea name="rejection_reason" rows="3" class="w-full border-2 border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none" placeholder="Provide a clear reason for rejecting this report..." required></textarea>
                            <div class="flex gap-2">
                                <button type="submit" class="btn-danger flex-1 px-4 py-2 text-xs"><i class="fas fa-ban mr-1.5"></i> Confirm Rejection</button>
                                <button type="button" data-target="rejectFormSection" onclick="toggleExpand(this)" class="btn-secondary px-4 py-2 text-xs">Cancel</button>
                            </div>
                        </form>
                    </div></div>
                </div>
                <?php endif; ?>

                <?php if ($can_resolve): ?>
                <div class="action-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="action-icon bg-emerald-50 text-emerald-600"><i class="fas fa-check-double"></i></div>
                        <div class="min-w-0">
                            <p class="font-bold text-gray-800 text-sm">Mark as Resolved</p>
                            <p class="text-xs text-gray-400 truncate">Attach photo proof and close the report</p>
                        </div>
                    </div>
                    <button type="button" data-target="resolveFormSection" onclick="toggleExpand(this)" class="action-trigger action-btn btn-success">
                        <i class="fas fa-check-double mr-2"></i> Mark as Resolved
                    </button>
                    <div id="resolveFormSection" class="expand-section mt-3 bg-green-50 border-2 border-green-200 rounded-xl"><div>
                        <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" enctype="multipart/form-data" class="expand-section-inner space-y-3" onsubmit="setLoading(this)">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="resolve_report">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Resolution photo <span class="text-red-500">(required)</span></label>
                            <div class="file-upload-area" id="resImageArea" onclick="document.getElementById('resImage').click()" ondragover="event.preventDefault();this.classList.add('drag-over')" ondragleave="this.classList.remove('drag-over')" ondrop="handleFileDrop(event, 'resImage')">
                                <img class="file-upload-preview" id="resImagePreviewImg" alt="">
                                <div class="file-upload-placeholder">
                                    <i class="fas fa-camera text-2xl text-gray-400 mb-1 block"></i>
                                    <span class="text-xs text-gray-600 font-medium">Click or drag a photo here</span>
                                </div>
                                <input type="file" name="resolution_image" id="resImage" accept="image/*" style="display:none;" required onchange="handleFilePreview(this,'resImageArea','resImagePreviewImg','resPreview')">
                                <span id="resPreview" class="text-xs text-gray-500 block mt-2">JPG, PNG, GIF (Max 5MB)</span>
                            </div>
                            <textarea name="resolution_note" rows="3" class="w-full border-2 border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:border-[#10A37F] focus:ring-2 focus:ring-[#10A37F]/20 outline-none" placeholder="Describe the actions taken to resolve this issue..."></textarea>
                            <div class="flex gap-2">
                                <button type="submit" class="btn-success flex-1 px-4 py-2 text-xs"><i class="fas fa-check mr-1.5"></i> Confirm Resolution</button>
                                <button type="button" data-target="resolveFormSection" onclick="toggleExpand(this)" class="btn-secondary px-4 py-2 text-xs">Cancel</button>
                            </div>
                        </form>
                    </div></div>
                </div>
                <?php endif; ?>

            <?php elseif ($user_role == 'admin'): ?>
                <?php if ($can_approve_escalation): ?>
                <div class="action-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="action-icon bg-amber-50 text-amber-600"><i class="fas fa-arrow-up-right-dots"></i></div>
                        <div class="min-w-0">
                            <p class="font-bold text-gray-800 text-sm">Escalation Review</p>
                            <p class="text-xs text-gray-400 truncate">Decide on this pending escalation</p>
                        </div>
                    </div>
                    <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" onsubmit="setLoading(this)">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="approve_escalation">
                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                        <button type="submit" class="action-btn btn-success mb-2"><i class="fas fa-check mr-2"></i> Approve Escalation</button>
                    </form>
                    <button type="button" data-target="rejectEscalationSection" onclick="toggleExpand(this)" class="action-trigger action-btn btn-danger">
                        <i class="fas fa-xmark mr-2"></i> Reject Escalation
                    </button>
                    <div id="rejectEscalationSection" class="expand-section mt-3 bg-red-50 border-2 border-red-200 rounded-xl"><div>
                        <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="expand-section-inner space-y-3" data-confirm="Reject this escalation and send it back?" onsubmit="return handleReportFormSubmit(event, this)">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="reject_escalation">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <label class="block text-sm font-semibold text-gray-700">Reason for rejection</label>
                            <input type="text" name="rejection_reason" placeholder="Reason for rejection..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none" required>
                            <div class="flex gap-2">
                                <button type="submit" class="btn-danger flex-1 px-4 py-2 text-xs">Confirm Rejection</button>
                                <button type="button" data-target="rejectEscalationSection" onclick="toggleExpand(this)" class="btn-secondary px-4 py-2 text-xs">Cancel</button>
                            </div>
                        </form>
                    </div></div>
                </div>
                <?php endif; ?>

                <?php if ($can_resolve): ?>
                <div class="action-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="action-icon bg-emerald-50 text-emerald-600"><i class="fas fa-check-double"></i></div>
                        <div class="min-w-0">
                            <p class="font-bold text-gray-800 text-sm">Mark as Resolved</p>
                            <p class="text-xs text-gray-400 truncate">Close this report with photo proof</p>
                        </div>
                    </div>
                    <button type="button" data-target="resolveAdminSection" onclick="toggleExpand(this)" class="action-trigger action-btn btn-success">
                        <i class="fas fa-check-double mr-2"></i> Mark as Resolved
                    </button>
                    <div id="resolveAdminSection" class="expand-section mt-3 bg-green-50 border-2 border-green-200 rounded-xl"><div>
                        <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" enctype="multipart/form-data" class="expand-section-inner space-y-3" onsubmit="setLoading(this)">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="resolve_report">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <div class="file-upload-area" id="resImageAdminArea" onclick="document.getElementById('resImageAdmin').click()" ondragover="event.preventDefault();this.classList.add('drag-over')" ondragleave="this.classList.remove('drag-over')" ondrop="handleFileDrop(event, 'resImageAdmin')">
                                <img class="file-upload-preview" id="resImageAdminPreviewImg" alt="">
                                <div class="file-upload-placeholder">
                                    <i class="fas fa-camera text-2xl text-gray-400 mb-1 block"></i>
                                    <span class="text-sm text-gray-500">Upload resolution photo</span>
                                </div>
                                <input type="file" name="resolution_image" id="resImageAdmin" accept="image/*" style="display:none;" onchange="handleFilePreview(this,'resImageAdminArea','resImageAdminPreviewImg','resPreviewAdmin')">
                                <span id="resPreviewAdmin" class="text-xs text-gray-400 block">JPG, PNG, GIF (Max 5MB)</span>
                            </div>
                            <input type="text" name="resolution_note" placeholder="Optional note..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-[#10A37F] focus:ring-2 focus:ring-[#10A37F]/20 outline-none">
                            <div class="flex gap-2">
                                <button type="submit" class="btn-success flex-1 px-4 py-2 text-xs"><i class="fas fa-check mr-1.5"></i> Confirm Resolution</button>
                                <button type="button" data-target="resolveAdminSection" onclick="toggleExpand(this)" class="btn-secondary px-4 py-2 text-xs">Cancel</button>
                            </div>
                        </form>
                    </div></div>
                </div>
                <?php endif; ?>

                <?php if ($can_reclassify): ?>
                <div class="action-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="action-icon bg-indigo-50 text-indigo-600"><i class="fas fa-rotate"></i></div>
                        <div class="min-w-0">
                            <p class="font-bold text-gray-800 text-sm">Reclassify Impact</p>
                            <p class="text-xs text-gray-400 truncate">Admin override of risk level</p>
                        </div>
                    </div>
                    <button type="button" data-target="reclassifyAdminForm" onclick="toggleExpand(this)" class="action-trigger action-btn btn-indigo">
                        <i class="fas fa-rotate mr-2"></i> Reclassify Impact
                    </button>
                    <div id="reclassifyAdminForm" class="expand-section mt-3 bg-gray-50 border border-gray-200 rounded-xl"><div>
                        <form method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php" class="expand-section-inner space-y-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="reclassify_impact">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <p class="text-sm text-gray-600 mb-2">Select new impact level:</p>
                            <div class="flex flex-wrap gap-3 mb-3">
                                <label class="flex items-center gap-1.5 text-sm text-gray-700"><input type="radio" name="new_impact" value="0" checked> 🟢 Localized (+0)</label>
                                <label class="flex items-center gap-1.5 text-sm text-gray-700"><input type="radio" name="new_impact" value="2"> 🟡 Moderate (+2)</label>
                                <label class="flex items-center gap-1.5 text-sm text-gray-700"><input type="radio" name="new_impact" value="4"> 🔴 Severe (+4)</label>
                            </div>
                            <input type="text" name="reclassify_reason" placeholder="Reason for reclassification..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:border-[#10A37F] focus:ring-2 focus:ring-[#10A37F]/20 outline-none" required>
                            <div class="flex gap-2">
                                <button type="submit" class="btn-indigo flex-1 px-4 py-2 text-xs">Confirm Reclassification</button>
                                <button type="button" data-target="reclassifyAdminForm" onclick="toggleExpand(this)" class="btn-secondary px-4 py-2 text-xs">Cancel</button>
                            </div>
                        </form>
                    </div></div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            </div>
        </div>

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
            <button type="button" class="btn-danger flex-1" onclick="proceedConfirmModal()">Yes, continue</button>
            <button type="button" class="btn-secondary flex-1" onclick="closeConfirmModal()">Cancel</button>
        </div>
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
    document.getElementById('confirmModalMessage').textContent = message;
    document.getElementById('confirmModalOverlay').classList.add('open');
    return false;
}
function closeConfirmModal() {
    document.getElementById('confirmModalOverlay').classList.remove('open');
    pendingConfirmForm = null;
}
function proceedConfirmModal() {
    if (pendingConfirmForm) {
        pendingConfirmForm.dataset.confirmed = 'true';
        closeConfirmModal();
        setLoading(pendingConfirmForm);
        pendingConfirmForm.requestSubmit ? pendingConfirmForm.requestSubmit() : pendingConfirmForm.submit();
    }
}

// ===== FILE UPLOAD: DRAG & DROP + IMAGE PREVIEW =====
function handleFilePreview(input, areaId, imgId, labelId) {
    const area = document.getElementById(areaId);
    const img = document.getElementById(imgId);
    const label = document.getElementById(labelId);
    const file = input.files[0];
    if (file) {
        area.classList.add('has-file');
        img.src = URL.createObjectURL(file);
        if (label) label.textContent = file.name + ' · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
    } else {
        area.classList.remove('has-file');
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
        '[onclick]', '.flash-message', '.toast-msg', '.file-upload-area',
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
    clone.querySelectorAll('.photo-grid video').forEach(vid => {
        vid.removeAttribute('controls');
        vid.muted = true;
        vid.style.cssText = 'width:100%; height:50px; object-fit:cover; border-radius:3px; border:1px solid #e5e7eb;';
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