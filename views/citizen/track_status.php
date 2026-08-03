<?php
// views/citizen/track_status.php - WITH CANCELLATION REASON MODAL & TIMELINE
// UPDATED: Support/Verification feature with ownership check – can only support others' reports
// ENHANCED: Unified header for both own and supported reports, consistent with my_reports.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SecurityHelper.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($report_id == 0) {
    $_SESSION['error'] = "Invalid report ID.";
    header("Location: " . BASE_URL . "index.php?page=my-reports");
    exit();
}

// Handle resolution confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_resolution'])) {
    $confirm_id = (int)$_POST['report_id'];
    
    $check_query = "SELECT id, status FROM reports WHERE id = :id AND user_id = :user_id AND status = 'resolved'";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $confirm_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->fetch()) {
        $update_query = "UPDATE reports SET resolution_confirmed = 1, resolution_confirmed_at = NOW() WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $confirm_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Thank you for confirming the resolution!";
            $_SESSION['show_confetti'] = true;
        } else {
            $_SESSION['error'] = "Failed to confirm resolution. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Unable to confirm resolution. Report may not exist, not belong to you, or not be resolved yet.";
    }
    
    header("Location: " . BASE_URL . "index.php?page=track-status&id=" . $confirm_id);
    exit();
}

// Get report data with verification info
$query = "SELECT r.*, c.name as category_name, c.icon_class, b.name as barangay_name,
                 CONCAT(u.first_name, ' ', u.last_name) as user_name, u.email as user_email,
                 r.verification_count,
                 r.user_id as owner_id,
                 (SELECT COUNT(*) FROM report_verifications WHERE report_id = r.id AND user_id = :current_user) as is_verified_by_user
          FROM reports r
          JOIN categories c ON r.category_id = c.id
          JOIN barangays b ON r.barangay_id = b.id
          JOIN users u ON r.user_id = u.id
          WHERE r.id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $report_id);
$stmt->bindParam(':current_user', $_SESSION['user_id']);
$stmt->execute();
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    $_SESSION['error'] = "Report not found.";
    header("Location: " . BASE_URL . "index.php?page=my-reports");
    exit();
}

// Permission check - allow owner, admins, barangay officials, OR users who have supported this report
$is_supporter = false;
if ($_SESSION['user_role'] === 'citizen' && $report['user_id'] != $_SESSION['user_id']) {
    $sup_check = $db->prepare("SELECT id FROM report_verifications WHERE report_id = ? AND user_id = ?");
    $sup_check->execute([$report_id, $_SESSION['user_id']]);
    $is_supporter = (bool)$sup_check->fetch();
}

if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'barangay_official' && $_SESSION['user_role'] !== 'menro' && $report['user_id'] != $_SESSION['user_id'] && !$is_supporter) {
    $_SESSION['error'] = "You don't have permission to view this report.";
    header("Location: " . BASE_URL . "index.php?page=my-reports");
    exit();
}

// If report is cancelled, only owner or admin can view
if ($report['status'] == 'cancelled' && $_SESSION['user_role'] !== 'admin' && $report['user_id'] != $_SESSION['user_id']) {
    $_SESSION['error'] = "This report has been cancelled and is not accessible.";
    header("Location: " . BASE_URL . "index.php?page=my-reports");
    exit();
}

// Get images
$img_query = "SELECT * FROM report_images WHERE report_id = :report_id ORDER BY is_primary DESC, uploaded_at ASC";
$img_stmt = $db->prepare($img_query);
$img_stmt->bindParam(':report_id', $report_id);
$img_stmt->execute();
$images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($images as &$img) {
    $img['image_path'] = BASE_URL . $img['image_path'];
}

// Get resolution evidence
$evidence_query = "SELECT * FROM resolution_evidence WHERE report_id = :report_id ORDER BY uploaded_at DESC";
$evidence_stmt = $db->prepare($evidence_query);
$evidence_stmt->bindParam(':report_id', $report_id);
$evidence_stmt->execute();
$resolution_evidence = $evidence_stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($resolution_evidence as &$ev) {
    $ev['image_path'] = BASE_URL . $ev['image_path'];
}

// Get investigation notes
$notes_query = "SELECT n.*, CONCAT(u.first_name, ' ', u.last_name) as user_name 
                FROM report_notes n 
                JOIN users u ON n.user_id = u.id 
                WHERE report_id = :report_id 
                ORDER BY n.created_at DESC";
$notes_stmt = $db->prepare($notes_query);
$notes_stmt->bindParam(':report_id', $report_id);
$notes_stmt->execute();
$notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Load San Isidro boundary for map
$geojson_file = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/geojson/sanisidro.geojson';
$boundary_data = null;
if (file_exists($geojson_file)) {
    $geojson_content = file_get_contents($geojson_file);
    $boundary_data = json_decode($geojson_content, true);
}

$risk_levels = [
    'low' => ['label' => 'Low', 'color' => 'green', 'bg' => 'bg-green-50', 'text' => 'text-green-800', 'icon' => 'fa-seedling', 'desc' => 'Minor issue, routine monitoring'],
    'medium' => ['label' => 'Medium', 'color' => 'yellow', 'bg' => 'bg-yellow-50', 'text' => 'text-yellow-800', 'icon' => 'fa-exclamation-triangle', 'desc' => 'Moderate concern, requires attention'],
    'high' => ['label' => 'High', 'color' => 'red', 'bg' => 'bg-red-50', 'text' => 'text-red-800', 'icon' => 'fa-fire', 'desc' => 'Urgent, requires immediate action'],
    'critical' => ['label' => 'Critical', 'color' => 'purple', 'bg' => 'bg-purple-50', 'text' => 'text-purple-800', 'icon' => 'fa-skull-crossbones', 'desc' => 'Emergency, immediate intervention needed']
];
$current_risk = isset($report['risk_level']) ? $report['risk_level'] : 'low';
$risk_info = $risk_levels[$current_risk];

$submitted_date = new DateTime($report['created_at']);
$now = new DateTime();
$days_ago = $submitted_date->diff($now)->days;

// ============================================================
// TIMELINE LOGIC
// ============================================================
$current_status = $report['status'];
$escalated_to_menro = isset($report['escalated_to_menro']) ? (int)$report['escalated_to_menro'] : 0;
$menro_accepted = isset($report['menro_accepted']) ? (int)$report['menro_accepted'] : 0;
$resolution_confirmed = isset($report['resolution_confirmed']) ? (int)$report['resolution_confirmed'] : 0;

$is_cancelled = ($current_status == 'cancelled');
$is_rejected = ($current_status == 'rejected');
$is_resolved = ($current_status == 'resolved');
$is_pending = ($current_status == 'pending');
$is_under_review = ($current_status == 'under_review');
$is_in_progress = ($current_status == 'in_progress' || $current_status == 'escalated_pending' || $current_status == 'escalated');

$step1_completed = true;
$step2_completed = !in_array($current_status, ['pending', 'cancelled', 'rejected']);
$step3_completed = in_array($current_status, ['in_progress', 'escalated_pending', 'escalated', 'resolved']);
$step4_completed = ($is_resolved && $resolution_confirmed == 1) || $is_rejected || $is_cancelled;
$step4_current = ($is_resolved && $resolution_confirmed == 0);

$step2_class = 'pending';
if ($is_under_review) {
    $step2_class = 'current';
} elseif ($step2_completed) {
    $step2_class = 'completed';
}

$step3_class = 'pending';
if ($is_in_progress && !$is_resolved) {
    $step3_class = 'current';
} elseif ($step3_completed) {
    $step3_class = 'completed';
}

if ($is_under_review) {
    $step2_text = 'Under Review';
} elseif ($step2_completed) {
    $step2_text = 'Completed';
} else {
    $step2_text = 'Pending Review';
}

if ($current_status == 'escalated') {
    $step3_text = 'MENRO In Progress';
} elseif ($current_status == 'escalated_pending') {
    $step3_text = 'Escalation Pending';
} elseif ($is_in_progress) {
    $step3_text = 'Action Being Taken';
} elseif ($step3_completed) {
    $step3_text = 'Completed';
} else {
    $step3_text = 'Pending';
}

if ($is_resolved) {
    $final_label = 'Resolved';
    $final_icon = 'fa-check-circle';
    if ($step4_completed) {
        $final_class = 'completed';
        $final_text = 'Confirmed ' . date('M d', strtotime($report['resolution_confirmed_at'] ?? 'now'));
    } else {
        $final_class = 'resolved-current';
        $final_text = 'Awaiting Confirmation';
    }
} elseif ($is_rejected) {
    $final_label = 'Rejected';
    $final_icon = 'fa-times-circle';
    $final_class = 'rejected-step';
    $final_text = 'Rejected';
} elseif ($is_cancelled) {
    $final_label = 'Cancelled';
    $final_icon = 'fa-ban';
    $final_class = 'cancelled-step';
    $final_text = 'Cancelled';
} else {
    $final_label = 'Final';
    $final_icon = 'fa-flag-checkered';
    $final_class = 'pending';
    $final_text = 'Pending';
}

$submitted_center = 12.5;
if ($step4_completed) {
    $progress_width = 87.5 - $submitted_center;
} elseif ($step3_completed) {
    $progress_width = 62.5 - $submitted_center;
} elseif ($step2_completed) {
    $progress_width = 37.5 - $submitted_center;
} else {
    $progress_width = 0;
}
if ($step4_current) {
    $progress_width = 62.5 - $submitted_center;
}

$display_status = $current_status;
$status_display = [
    'pending' => 'Submitted',
    'under_review' => 'Under Review',
    'verified' => 'Under Review',
    'in_progress' => 'In Progress',
    'escalated_pending' => 'Escalated Pending',
    'escalated' => 'Under MENRO',
    'resolved' => 'Resolved',
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled'
];
$display_status_label = isset($status_display[$display_status]) ? $status_display[$display_status] : ucfirst(str_replace('_', ' ', $display_status));

// A citizen may only cancel their own report while it is still 'pending'
$is_owner = ($report['user_id'] == $_SESSION['user_id']);
$can_cancel = ($is_pending && $is_owner);
$show_cancel_locked_notice = (!$is_pending && !$is_cancelled && !$is_rejected && !$is_resolved && $is_owner);
$can_confirm_resolution = ($is_resolved && $resolution_confirmed === 0 && $_SESSION['user_id'] == $report['user_id']);

// CSRF token for AJAX
$csrf_token = InputSanitizer::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Track Report - EnviroTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1"></script>
    <style>
        * { font-family: 'Manrope', sans-serif; }
        
        body { 
            background: #F5FBF6;
            overflow-x: hidden;
        }
        
        @media (max-width: 768px) {
            .ml-72 {
                margin-left: 0 !important;
                width: 100%;
                padding: 0;
            }
            .sidebar-mobile {
                position: fixed;
                left: -280px;
                transition: left 0.3s ease;
                z-index: 1000;
            }
            .sidebar-mobile.open {
                left: 0;
            }
        }
        
        .main-container {
            padding: 1rem;
            max-width: 1280px;
            margin: 0 auto;
        }
        @media (min-width: 640px) {
            .main-container {
                padding: 1.5rem;
            }
        }
        @media (min-width: 768px) {
            .main-container {
                padding: 2rem;
            }
        }
        
        .timeline-container {
            display: flex;
            flex-wrap: wrap;
            position: relative;
            padding: 0 0.5rem;
        }
        .timeline-step { 
            position: relative; 
            flex: 1; 
            text-align: center; 
            z-index: 2;
            min-width: 60px;
        }
        .timeline-container::before {
            content: '';
            position: absolute;
            top: 28px;
            left: 12.5%;
            right: 12.5%;
            height: 3px;
            background: #E5E7EB;
            z-index: 0;
            border-radius: 2px;
        }
        .timeline-progress {
            position: absolute;
            top: 28px;
            left: 12.5%;
            height: 4px;
            background: #10A37F;
            z-index: 1;
            transition: width 0.6s ease;
            border-radius: 2px;
            width: 0%;
        }
        .step-icon { 
            position: relative; 
            z-index: 2; 
            width: 56px; 
            height: 56px; 
            margin: 0 auto 12px; 
            background: white; 
            border: 2px solid #E5E7EB; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            transition: all 0.3s ease; 
        }
        .step-icon i { color: #9CA3AF; font-size: 1.25rem; }
        .timeline-step.completed .step-icon { border-color: #10A37F; background: #10A37F; }
        .timeline-step.completed .step-icon i { color: white; }
        .timeline-step.current .step-icon { border-color: #10A37F; background: white; animation: stepPulse 2s infinite; }
        .timeline-step.current .step-icon i { color: #10A37F; }
        @keyframes stepPulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 163, 127, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(16, 163, 127, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 163, 127, 0); }
        }
        .timeline-step.rejected-step .step-icon { border-color: #EF4444; background: #FEE2E2; }
        .timeline-step.rejected-step .step-icon i { color: #DC2626; }
        .timeline-step.cancelled-step .step-icon { border-color: #6B7280; background: #F3F4F6; }
        .timeline-step.cancelled-step .step-icon i { color: #6B7280; }
        .timeline-step.resolved-current .step-icon { border-color: #10A37F; background: white; animation: none !important; }
        .timeline-step.resolved-current .step-icon i { color: #10A37F; }
        
        .timeline-step .step-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #1F2937;
            line-height: 1.2;
        }
        .timeline-step .step-date {
            font-size: 0.6rem;
            color: #9CA3AF;
            margin-top: 0.2rem;
        }
        @media (max-width: 640px) {
            .timeline-step .step-icon { width: 40px; height: 40px; }
            .timeline-step .step-icon i { font-size: 1rem; }
            .timeline-container::before, .timeline-progress { top: 20px; left: 12.5%; right: 12.5%; }
            .timeline-step .step-label { font-size: 0.6rem; }
            .timeline-step .step-date { font-size: 0.5rem; }
        }
        
        .status-badge { 
            display: inline-flex; 
            align-items: center; 
            padding: 0.25rem 0.75rem; 
            border-radius: 9999px; 
            font-size: 0.7rem; 
            font-weight: 600; 
        }
        .status-pending { background: #FEF3C7; color: #D97706; }
        .status-under_review { background: #DBEAFE; color: #1E40AF; }
        .status-verified { background: #DBEAFE; color: #2563EB; }
        .status-in_progress { background: #FCE7F3; color: #DB2777; }
        .status-escalated_pending { background: #FDE68A; color: #92400E; }
        .status-escalated { background: #FED7AA; color: #9A3412; }
        .status-resolved { background: #D1FAE5; color: #10A37F; }
        .status-rejected { background: #FEE2E2; color: #DC2626; }
        .status-cancelled { background: #F3F4F6; color: #6B7280; }
        
        .risk-low { background: #D1FAE5; color: #10A37F; }
        .risk-medium { background: #FEF3C7; color: #D97706; }
        .risk-high { background: #FEE2E2; color: #DC2626; }
        .risk-critical { background: #EDE9FE; color: #7C3AED; }
        
        .severity-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .severity-Green { background: #D1FAE5; color: #065F46; }
        .severity-Yellow { background: #FEF3C7; color: #92400E; }
        .severity-Orange { background: #FED7AA; color: #9A3412; }
        .severity-Red { background: #FEE2E2; color: #991B1B; }

        /* Verification/Styling */
        .verify-btn {
            transition: all 0.2s ease;
            font-size: 0.75rem;
            padding: 0.3rem 1rem;
            border-radius: 9999px;
            border: 1px solid #10A37F;
            background: transparent;
            color: #10A37F;
            cursor: pointer;
        }
        .verify-btn:hover:not(:disabled) {
            background: #10A37F;
            color: white;
        }
        .verify-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            border-color: #D1D5DB;
            color: #9CA3AF;
        }
        .verify-btn.verified {
            background: #D1FAE5;
            border-color: #10A37F;
            color: #065F46;
        }
        .verification-count {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.8rem;
            color: #6B7280;
        }
        .verification-count i {
            color: #10A37F;
        }

        /* Own report label */
        .own-report-label {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 500;
            color: #6B7280;
            background: #F3F4F6;
        }

        /* Toast */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideInRight 0.3s ease-out;
            max-width: 90vw;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @media (max-width: 480px) {
            .toast-notification {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
        
        .btn-primary {
            background-color: #10A37F;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #0D8568;
            transform: scale(0.98);
        }
        .btn-danger {
            background-color: #EF4444;
            transition: all 0.2s ease;
        }
        .btn-danger:hover {
            background-color: #DC2626;
            transform: scale(0.98);
        }
        
        .celebration-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .celebration-card {
            background: white;
            border-radius: 2rem;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .celebration-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #10A37F, #0D8568);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: bounce 0.6s ease-out;
        }
        @keyframes bounce { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        .celebration-icon i { font-size: 3rem; color: white; }
        
        .photo-card { 
            position: relative; 
            border-radius: 0.75rem; 
            overflow: hidden; 
            cursor: pointer; 
            transition: transform 0.2s; 
        }
        .photo-card:hover { transform: scale(1.02); }
        .photo-card img { width: 100%; height: 200px; object-fit: cover; }
        
        @media (max-width: 768px) {
            .photo-card img { height: 140px; }
        }

        /* ===== ENHANCED SUPPORTED REPORT HEADER ===== */
        .supported-header {
            border: 2px solid #0A7E6B;
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 4px 20px rgba(10, 126, 107, 0.08);
        }
        .supported-header .badge-teal {
            background: rgba(10, 126, 107, 0.1);
            color: #0A7E6B;
            border: 1px solid rgba(10, 126, 107, 0.2);
        }
        .supported-header .text-teal {
            color: #0A7E6B;
        }
        .supported-header .border-teal {
            border-color: #0A7E6B;
        }

        /* ===== SUPPORTED CARD IN VERIFICATION SECTION ===== */
        .supported-verification-card {
            border-color: #0A7E6B;
        }
        .supported-verification-card .heart-bg {
            background: #FCE4EC;
        }
        .supported-verification-card .heart-text {
            color: #E91E63;
        }

        /* Timeline progress color override for supported */
        .supported-timeline .timeline-progress {
            background: #0A7E6B;
        }
        .supported-timeline .timeline-step.completed .step-icon {
            border-color: #0A7E6B;
            background: #0A7E6B;
        }
        .supported-timeline .timeline-step.current .step-icon {
            border-color: #0A7E6B;
        }
        .supported-timeline .timeline-step.current .step-icon i {
            color: #0A7E6B;
        }
        .supported-timeline .timeline-step.resolved-current .step-icon {
            border-color: #0A7E6B;
        }
        .supported-timeline .timeline-step.resolved-current .step-icon i {
            color: #0A7E6B;
        }

        /* Track button for supported */
        .btn-track-supported {
            background: #0A7E6B;
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 9999px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-track-supported:hover {
            background: #066B5A;
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(10, 126, 107, 0.3);
        }

        /* Back button style matching my_reports */
        .btn-back {
            background: #f1f5f9;
            color: #1f2937;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        .btn-back:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        @media (min-width: 640px) {
            .btn-back {
                padding: 0.5rem 1.25rem;
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body class="bg-[#F5FBF6]">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<div class="ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6">
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- ===== UNIFIED HEADER (matches my_reports.php style) ===== -->
        <div class="mb-6 md:mb-8">
            <div class="flex items-center space-x-2 mb-2">
                <div class="w-8 h-8 <?php echo $is_supporter ? 'bg-[#0A7E6B]/10' : 'bg-[#10A37F]/10'; ?> rounded-lg flex items-center justify-center">
                    <i class="fas <?php echo $is_supporter ? 'fa-heart' : 'fa-map-pin'; ?> <?php echo $is_supporter ? 'text-[#0A7E6B]' : 'text-[#10A37F]'; ?> text-sm"></i>
                </div>
                <span class="text-xs uppercase tracking-wider <?php echo $is_supporter ? 'text-[#0A7E6B]' : 'text-[#10A37F]'; ?> font-semibold">
                    <?php echo $is_supporter ? 'Supported Report Tracking' : 'Report Tracking'; ?>
                </span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                        <?php echo $is_supporter ? 'Track Supported Report' : 'Track Report'; ?>
                    </h1>
                    <p class="text-gray-500 text-sm mt-1">
                        <?php echo $is_supporter 
                            ? 'You supported this report — follow its progress below.' 
                            : 'Real-time status and details of your environmental report'; ?>
                    </p>
                </div>
                <div class="flex gap-3 flex-wrap">
                    <?php if($can_cancel): ?>
                        <button onclick="openCancelModal()" 
                                class="bg-red-500 hover:bg-red-600 text-white px-4 md:px-5 py-2 rounded-xl transition-all flex items-center gap-2 text-sm">
                            <i class="fas fa-times-circle"></i>
                            <span>Cancel Report</span>
                        </button>
                    <?php elseif($show_cancel_locked_notice): ?>
                        <button disabled title="This report is already being reviewed by the barangay and can no longer be cancelled."
                                class="bg-gray-100 text-gray-400 px-4 md:px-5 py-2 rounded-xl flex items-center gap-2 text-sm cursor-not-allowed">
                            <i class="fas fa-lock"></i>
                            <span>Cancel Unavailable</span>
                        </button>
                    <?php endif; ?>
                    <a href="<?php 
                        if ($_SESSION['user_role'] == 'admin') {
                            echo BASE_URL . 'index.php?page=all-reports';
                        } elseif ($_SESSION['user_role'] == 'barangay_official') {
                            echo BASE_URL . 'index.php?page=verify-reports';
                        } elseif ($is_supporter) {
                            echo BASE_URL . 'index.php?page=my-reports&tab=supported';
                        } else {
                            echo BASE_URL . 'index.php?page=my-reports';
                        }
                    ?>" 
                       class="btn-back">
                        <i class="fas fa-arrow-left"></i>
                        <span><?php echo $is_supporter ? 'Supported Reports' : 'Back'; ?></span>
                    </a>
                </div>
            </div>
            <?php if($show_cancel_locked_notice): ?>
            <p class="text-xs text-gray-400 mt-2 md:text-right">
                <i class="fas fa-info-circle mr-1"></i>
                A barangay official has already started reviewing this report, so it can no longer be cancelled.
            </p>
            <?php endif; ?>
        </div>
        
        <!-- ===== REPORT DETAILS CARD ===== -->
        <?php if ($is_supporter): ?>
            <!-- SUPPORTED REPORT HEADER (teal accent) -->
            <div class="supported-header rounded-2xl shadow-sm overflow-hidden mb-6 md:mb-8">
                <div class="px-4 md:px-6 py-4 md:py-6">
                    <div class="flex flex-wrap justify-between items-start gap-4">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg flex items-center justify-center" style="background:rgba(10,126,107,0.12);">
                                    <i class="fas fa-heart text-sm" style="color:#0A7E6B;"></i>
                                </div>
                                <span class="text-xs uppercase tracking-wider font-semibold" style="color:#0A7E6B;">You Supported This Report</span>
                            </div>
                            <h2 class="text-xl md:text-2xl font-bold" style="color:#0A7E6B;"><?php echo htmlspecialchars($report['title']); ?></h2>
                            <div class="flex flex-wrap gap-2 mt-1">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs" style="background:rgba(10,126,107,0.08);color:#0A7E6B;border:1px solid rgba(10,126,107,0.2);">
                                    <i class="fas fa-calendar-alt"></i> <?php echo $days_ago; ?> day<?php echo $days_ago != 1 ? 's' : ''; ?> ago
                                </span>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs" style="background:rgba(10,126,107,0.08);color:#0A7E6B;border:1px solid rgba(10,126,107,0.2);">
                                    <?php echo date('M d, Y • H:i', strtotime($report['created_at'])); ?>
                                </span>
                            </div>
                            <?php if($is_cancelled && !empty($report['cancellation_remarks'])): ?>
                                <div class="mt-2 p-2 rounded-lg text-xs" style="background:rgba(10,126,107,0.08);color:#0A7E6B;border:1px solid rgba(10,126,107,0.2);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>Cancellation reason:</strong> <?php echo htmlspecialchars($report['cancellation_remarks']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <?php
                            $status_icon = '';
                            if($display_status == 'pending') $status_icon = 'fa-clock';
                            elseif($display_status == 'under_review') $status_icon = 'fa-search';
                            elseif($display_status == 'verified') $status_icon = 'fa-search';
                            elseif($display_status == 'in_progress') $status_icon = 'fa-spinner fa-pulse';
                            elseif($display_status == 'escalated_pending') $status_icon = 'fa-hourglass-half';
                            elseif($display_status == 'escalated') $status_icon = 'fa-building';
                            elseif($display_status == 'resolved') $status_icon = 'fa-check-circle';
                            elseif($display_status == 'rejected') $status_icon = 'fa-times-circle';
                            elseif($display_status == 'cancelled') $status_icon = 'fa-ban';
                            else $status_icon = 'fa-clock';
                            ?>
                            <span class="status-badge status-<?php echo $display_status; ?>">
                                <i class="fas <?php echo $status_icon; ?> mr-1 text-xs"></i>
                                <?php echo $display_status_label; ?>
                            </span>
                            <?php if(!$is_cancelled && !$is_rejected): ?>
                            <span class="risk-<?php echo $current_risk; ?> px-2 py-0.5 text-xs rounded-full font-medium flex items-center gap-1">
                                <i class="fas <?php echo $risk_info['icon']; ?> text-xs"></i>
                                <?php echo $risk_info['label']; ?> Risk
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- OWN REPORT HEADER (default green gradient) -->
            <div class="bg-gradient-to-r from-[#10A37F] to-[#0D8568] rounded-2xl shadow-xl overflow-hidden mb-6 md:mb-8">
                <div class="px-4 md:px-6 py-4 md:py-6">
                    <div class="flex flex-wrap justify-between items-start gap-4">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 md:w-6 md:h-6 bg-white/20 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-file-alt text-white/80 text-[10px] md:text-xs"></i>
                                </div>
                                <span class="text-white/80 text-[10px] md:text-xs uppercase tracking-wider font-semibold">Report Details</span>
                            </div>
                            <h2 class="text-xl md:text-2xl font-bold text-white"><?php echo htmlspecialchars($report['title']); ?></h2>
                            <div class="flex flex-wrap gap-2 mt-1">
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-white/20 rounded-lg text-white text-[10px] md:text-xs">
                                    <i class="fas fa-calendar-alt"></i> <?php echo $days_ago; ?> day<?php echo $days_ago != 1 ? 's' : ''; ?> ago
                                </span>
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-white/20 rounded-lg text-white text-[10px] md:text-xs">
                                    <?php echo date('M d, Y • H:i', strtotime($report['created_at'])); ?>
                                </span>
                            </div>
                            <?php if($is_cancelled && !empty($report['cancellation_remarks'])): ?>
                                <div class="mt-2 p-2 bg-white/20 rounded-lg text-white text-xs">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>Cancellation reason:</strong> <?php echo htmlspecialchars($report['cancellation_remarks']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <?php
                            $status_icon = '';
                            if($display_status == 'pending') $status_icon = 'fa-clock';
                            elseif($display_status == 'under_review') $status_icon = 'fa-search';
                            elseif($display_status == 'verified') $status_icon = 'fa-search';
                            elseif($display_status == 'in_progress') $status_icon = 'fa-spinner fa-pulse';
                            elseif($display_status == 'escalated_pending') $status_icon = 'fa-hourglass-half';
                            elseif($display_status == 'escalated') $status_icon = 'fa-building';
                            elseif($display_status == 'resolved') $status_icon = 'fa-check-circle';
                            elseif($display_status == 'rejected') $status_icon = 'fa-times-circle';
                            elseif($display_status == 'cancelled') $status_icon = 'fa-ban';
                            else $status_icon = 'fa-clock';
                            ?>
                            <span class="status-badge status-<?php echo $display_status; ?>">
                                <i class="fas <?php echo $status_icon; ?> mr-1 text-xs"></i>
                                <?php echo $display_status_label; ?>
                            </span>
                            <?php if(!$is_cancelled && !$is_rejected): ?>
                            <span class="risk-<?php echo $current_risk; ?> px-2 py-0.5 text-xs rounded-full font-medium flex items-center gap-1">
                                <i class="fas <?php echo $risk_info['icon']; ?> text-xs"></i>
                                <?php echo $risk_info['label']; ?> Risk
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Status, Risk & Verification Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
            <!-- Status Card -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-emerald-50">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Current Status</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="status-badge status-<?php echo $report['status']; ?>">
                                <i class="fas <?php echo $report['status'] == 'pending' ? 'fa-clock' : ($report['status'] == 'resolved' ? 'fa-check-circle' : 'fa-check'); ?> mr-1 text-xs"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 mt-3 leading-relaxed">
                            <?php 
                                if($report['status'] == 'pending') echo "Your report is waiting for verification from barangay officials.";
                                elseif($report['status'] == 'under_review') echo "Your report is currently under review by barangay officials.";
                                elseif($report['status'] == 'in_progress') echo "Your report is being actioned by the barangay.";
                                elseif($report['status'] == 'resolved') echo "Your report has been successfully resolved. Thank you for your contribution!";
                                elseif($report['status'] == 'escalated_pending') echo "Your report has been escalated to MENRO and is awaiting approval.";
                                elseif($report['status'] == 'escalated') echo "Your report is now under MENRO supervision.";
                                elseif($report['status'] == 'rejected') echo "Your report was rejected by the barangay.";
                                elseif($report['status'] == 'cancelled') echo "This report has been cancelled.";
                                else echo "Action is being taken on your report by the concerned authorities.";
                            ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 <?php echo $report['status'] == 'pending' ? 'bg-yellow-50' : ($report['status'] == 'resolved' ? 'bg-green-50' : 'bg-blue-50'); ?> rounded-xl flex items-center justify-center">
                        <i class="fas <?php echo $report['status'] == 'pending' ? 'fa-clock' : ($report['status'] == 'resolved' ? 'fa-check-circle' : 'fa-spinner'); ?> text-xl <?php echo $report['status'] == 'pending' ? 'text-yellow-500' : ($report['status'] == 'resolved' ? 'text-green-500' : 'text-blue-500'); ?>"></i>
                    </div>
                </div>
            </div>
            
            <!-- Risk Card -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-emerald-50">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Severity Level</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="risk-<?php echo $current_risk; ?> px-2 py-0.5 text-xs rounded-full font-medium flex items-center gap-1">
                                <i class="fas <?php echo $risk_info['icon']; ?> text-xs"></i>
                                <?php echo $risk_info['label']; ?> Risk
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 mt-3 leading-relaxed"><?php echo $risk_info['desc']; ?></p>
                    </div>
                    <div class="w-12 h-12 <?php echo $risk_info['bg']; ?> rounded-xl flex items-center justify-center">
                        <i class="fas <?php echo $risk_info['icon']; ?> text-xl <?php echo $risk_info['text']; ?>"></i>
                    </div>
                </div>
            </div>

            <!-- Verification / Support Card (with ownership check) -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border <?php echo $is_supporter ? 'border-[#0A7E6B]' : 'border-emerald-50'; ?>">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Community Support</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="verification-count">
                                <i class="fas fa-thumbs-up"></i>
                                <span class="font-semibold text-gray-700" id="verifyCount"><?php echo (int)$report['verification_count']; ?></span>
                                <span class="text-gray-400">verification<?php echo $report['verification_count'] != 1 ? 's' : ''; ?></span>
                            </span>
                        </div>
                        <p class="text-sm mt-3 leading-relaxed <?php echo $is_supporter ? 'text-[#0A7E6B]' : 'text-gray-500'; ?>">
                            <?php if ($report['owner_id'] == $_SESSION['user_id']): ?>
                                <span class="text-gray-500">This is your report</span>
                            <?php elseif ($report['is_verified_by_user'] > 0): ?>
                                <span class="font-medium"><i class="fas fa-heart mr-1" style="color:#ef4444;"></i> You supported this report</span>
                            <?php else: ?>
                                Verify that you witnessed this issue too.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <?php if ($report['owner_id'] == $_SESSION['user_id']): ?>
                            <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-user text-gray-400 text-2xl"></i>
                            </div>
                            <p class="text-xs text-gray-400 mt-1 text-center">Your report</p>
                        <?php elseif ($report['is_verified_by_user'] > 0): ?>
                            <div class="w-12 h-12 bg-pink-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-heart text-pink-500 text-2xl"></i>
                            </div>
                            <p class="text-xs text-pink-500 mt-1 text-center font-medium">Supported</p>
                        <?php elseif (!in_array($report['status'], ['resolved', 'rejected', 'cancelled'])): ?>
                            <button id="supportBtn" class="verify-btn" onclick="supportReport(<?php echo $report['id']; ?>, this)">
                                <i class="fas fa-thumbs-up"></i> Support
                            </button>
                        <?php else: ?>
                            <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-ban text-gray-400 text-2xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Timeline -->
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 p-4 md:p-6 mb-6 md:mb-8 <?php echo $is_supporter ? 'supported-timeline' : ''; ?>">
            <h3 class="text-xs md:text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4 md:mb-6">Report Progress Timeline</h3>
            <div class="timeline-container">
                <div class="timeline-progress" style="width: <?php echo $progress_width; ?>%;"></div>
                
                <div class="timeline-step completed">
                    <div class="step-icon"><i class="fas fa-check"></i></div>
                    <div class="step-label">Submitted</div>
                    <div class="step-date"><?php echo date('M d', strtotime($report['created_at'])); ?></div>
                </div>
                
                <div class="timeline-step <?php echo $step2_class; ?>">
                    <div class="step-icon"><i class="fas <?php echo $step2_class == 'completed' ? 'fa-check' : 'fa-search'; ?>"></i></div>
                    <div class="step-label">Under Review</div>
                    <div class="step-date"><?php echo $step2_text; ?></div>
                </div>
                
                <div class="timeline-step <?php echo $step3_class; ?>">
                    <div class="step-icon"><i class="fas <?php echo $step3_class == 'completed' ? 'fa-check' : ($step3_class == 'current' ? 'fa-spinner fa-pulse' : 'fa-spinner'); ?>"></i></div>
                    <div class="step-label">In Progress</div>
                    <div class="step-date"><?php echo $step3_text; ?></div>
                </div>
                
                <div class="timeline-step <?php echo $final_class; ?>">
                    <div class="step-icon"><i class="fas <?php echo $final_icon; ?>"></i></div>
                    <div class="step-label"><?php echo $final_label; ?></div>
                    <div class="step-date"><?php echo $final_text; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Resolution Confirmation -->
        <?php if($can_confirm_resolution): ?>
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-2xl border-2 border-green-200 p-4 md:p-6 mb-6 md:mb-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 text-lg">Resolution Confirmation Required</h3>
                        <p class="text-gray-600 text-sm">
                            <?php if($menro_accepted): ?>
                                MENRO has marked this report as resolved. Please confirm if you agree with the resolution.
                            <?php else: ?>
                                Barangay has marked this report as resolved. Please confirm if you agree with the resolution.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <form method="POST" action="" onsubmit="return confirmResolution()">
                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                    <button type="submit" name="confirm_resolution" class="btn-primary px-6 py-3 text-white rounded-xl font-semibold flex items-center gap-2">
                        <i class="fas fa-thumbs-up"></i>
                        Confirm Resolution
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Report Information -->
        <div class="grid grid-cols-1 gap-4 md:gap-5 mb-6 md:mb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white rounded-2xl p-3 md:p-4 shadow-sm border border-emerald-50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 md:w-9 md:h-9 bg-emerald-100 rounded-lg flex items-center justify-center shadow-sm">
                            <i class="fas fa-tag text-[#10A37F] text-sm md:text-base"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] md:text-xs text-gray-400 uppercase tracking-wider font-semibold">Category</p>
                            <p class="font-semibold text-gray-800 text-sm md:text-base truncate"><?php echo htmlspecialchars($report['category_name']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl p-3 md:p-4 shadow-sm border border-emerald-50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 md:w-9 md:h-9 bg-emerald-100 rounded-lg flex items-center justify-center shadow-sm">
                            <i class="fas fa-map-marker-alt text-[#10A37F] text-sm md:text-base"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] md:text-xs text-gray-400 uppercase tracking-wider font-semibold">Barangay</p>
                            <p class="font-semibold text-gray-800 text-sm md:text-base truncate"><?php echo htmlspecialchars($report['barangay_name']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 p-4 md:p-5 lg:p-6 overflow-hidden">
                <div class="flex items-center gap-2 mb-3 flex-shrink-0">
                    <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-align-left text-[#10A37F] text-sm"></i>
                    </div>
                    <h3 class="text-xs md:text-sm font-semibold text-gray-400 uppercase tracking-wider">Description</h3>
                </div>
                <div class="bg-gradient-to-br from-[#F5FBF6] to-[#EEF8F1] rounded-xl p-4 md:p-5 lg:p-6 border border-emerald-100 min-h-[180px] md:min-h-[220px] overflow-hidden">
                    <p class="text-gray-700 text-base md:text-lg leading-relaxed break-words whitespace-pre-line overflow-wrap-anywhere max-w-full"><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Map -->
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 p-4 md:p-6 mb-6 md:mb-8">
            <h3 class="text-xs md:text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3 md:mb-4">Report Location</h3>
            <?php if($report['latitude'] && $report['longitude'] && $report['latitude'] != 0 && $report['longitude'] != 0): ?>
            <div class="rounded-xl overflow-hidden border border-emerald-100">
                <div id="reportMap" class="h-64 md:h-80"></div>
            </div>
            <p class="text-xs text-gray-400 mt-2 text-center">
                <i class="fas fa-map-pin mr-1 text-[#10A37F]"></i>
                <?php echo number_format($report['latitude'], 6); ?>, <?php echo number_format($report['longitude'], 6); ?>
            </p>
            <div class="text-center mt-1">
                <a href="https://www.openstreetmap.org/?mlat=<?php echo $report['latitude']; ?>&amp;mlon=<?php echo $report['longitude']; ?>#map=17/<?php echo $report['latitude']; ?>/<?php echo $report['longitude']; ?>" 
                   target="_blank" 
                   class="text-xs text-[#10A37F] hover:underline inline-flex items-center gap-1">
                    <i class="fas fa-external-link-alt"></i> View larger map
                </a>
            </div>
            <?php elseif($report['location_address']): ?>
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                <p class="text-sm text-gray-700"><i class="fas fa-map-marker-alt text-[#10A37F] mr-2"></i><?php echo htmlspecialchars($report['location_address']); ?></p>
            </div>
            <?php else: ?>
            <p class="text-gray-400 text-sm text-center py-8">No location data available</p>
            <?php endif; ?>
        </div>
        
        <!-- Evidence Photos -->
        <?php if(!empty($images)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 p-4 md:p-6 mb-6 md:mb-8">
            <h3 class="text-xs md:text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3 md:mb-4">
                <i class="fas fa-image mr-1"></i> Evidence Photos (<?php echo count($images); ?>)
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-4">
                <?php foreach($images as $index => $image): ?>
                <div class="photo-card group" onclick="openLightbox(<?php echo $index; ?>)">
                    <img src="<?php echo $image['image_path']; ?>" class="w-full h-32 md:h-48 object-cover" alt="Evidence photo" onerror="this.src='https://placehold.co/400x300/e2e8f0/94a3b8?text=Image+Not+Found'">
                    <?php if($image['is_primary']): ?>
                    <div class="absolute top-2 right-2 bg-[#10A37F] text-white text-[10px] md:text-xs px-1.5 md:px-2 py-0.5 md:py-1 rounded-lg">
                        <i class="fas fa-star mr-1"></i>Primary
                    </div>
                    <?php endif; ?>
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <i class="fas fa-search-plus text-white text-2xl md:text-3xl"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Resolution Evidence -->
        <?php if($report['status'] == 'resolved' && !empty($resolution_evidence)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 p-4 md:p-6 mb-6 md:mb-8">
            <h3 class="text-xs md:text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3 md:mb-4">
                <i class="fas fa-check-circle text-green-500 mr-1"></i> Resolution Evidence (<?php echo count($resolution_evidence); ?>)
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-4">
                <?php foreach($resolution_evidence as $ev): ?>
                <div class="relative group cursor-pointer" onclick="window.open('<?php echo $ev['image_path']; ?>', '_blank')">
                    <img src="<?php echo $ev['image_path']; ?>" alt="Resolution evidence" class="w-full h-32 md:h-48 object-cover rounded-xl border border-green-200 hover:border-green-500 transition">
                    <?php if($ev['caption']): ?>
                    <div class="absolute bottom-0 left-0 right-0 bg-black/60 text-white text-xs p-1 rounded-b-xl text-center">
                        <?php echo htmlspecialchars(substr($ev['caption'], 0, 50)); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Investigation Notes -->
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 p-4 md:p-6 mb-6 md:mb-8">
            <h3 class="text-xs md:text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3 md:mb-4">
                <i class="fas fa-sticky-note mr-1"></i> Investigation Notes
            </h3>
            <div class="space-y-3 max-h-64 overflow-y-auto">
                <?php if(!empty($notes)): ?>
                    <?php foreach($notes as $note): ?>
                    <div class="border-l-4 border-[#10A37F] bg-gray-50 p-4 rounded-xl">
                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($note['note']); ?></p>
                        <p class="text-xs text-gray-400 mt-2">
                            <i class="fas fa-user-circle mr-1"></i><?php echo htmlspecialchars($note['user_name']); ?> • 
                            <i class="fas fa-clock ml-2 mr-1"></i><?php echo date('M d, h:i A', strtotime($note['created_at'])); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-400 text-sm text-center py-6">No investigation notes yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        
    </div>
</div>

<!-- Lightbox Modal -->
<div id="lightboxModal" class="fixed inset-0 bg-black/90 backdrop-blur-sm z-[10000] hidden items-center justify-center p-4" onclick="closeLightbox()">
    <button onclick="closeLightbox()" class="absolute top-6 right-6 text-white hover:text-gray-300 transition z-10">
        <i class="fas fa-times text-3xl"></i>
    </button>
    <button onclick="prevImage()" class="absolute left-6 top-1/2 -translate-y-1/2 text-white hover:text-gray-300 transition z-10">
        <i class="fas fa-chevron-left text-4xl"></i>
    </button>
    <button onclick="nextImage()" class="absolute right-6 top-1/2 -translate-y-1/2 text-white hover:text-gray-300 transition z-10">
        <i class="fas fa-chevron-right text-4xl"></i>
    </button>
    <div class="max-w-5xl max-h-[85vh] w-full h-full flex items-center justify-center" onclick="event.stopPropagation()">
        <img id="lightboxImage" src="" alt="Full size image" class="max-w-full max-h-full object-contain rounded-2xl shadow-2xl">
    </div>
    <div class="absolute bottom-6 left-0 right-0 text-center text-white/70 text-sm" id="lightboxCounter">
        Image 1 of <?php echo count($images); ?>
    </div>
</div>

<!-- Celebration Modal -->
<?php if(isset($_SESSION['show_confetti']) && $_SESSION['show_confetti'] === true): ?>
<div id="celebrationModal" class="celebration-modal">
    <div class="celebration-card">
        <div class="celebration-icon">
            <i class="fas fa-leaf"></i>
        </div>
        <h2 class="text-3xl font-bold text-gray-800 mb-3">Thank You!</h2>
        <p class="text-gray-600 text-lg mb-2">for contributing to a</p>
        <p class="text-2xl font-bold text-[#10A37F] mb-4">CLEANER SAN ISIDRO</p>
        <div class="bg-green-50 rounded-xl p-4 mb-6">
            <i class="fas fa-hand-peace text-[#10A37F] text-2xl mb-2 block"></i>
            <p class="text-gray-700">Your report has been confirmed resolved. Together, we're making San Isidro a better place to live.</p>
        </div>
        <button onclick="closeCelebration()" class="btn-primary px-8 py-3 text-white rounded-xl font-semibold text-lg">
            Continue
        </button>
    </div>
</div>
<script>
    function triggerFullScreenConfetti() {
        canvasConfetti({ particleCount: 200, spread: 100, origin: { y: 0.6 }, colors: ['#10A37F', '#0D8568', '#34D399', '#6EE7B7', '#F59E0B', '#FBBF24'] });
        setTimeout(() => { canvasConfetti({ particleCount: 150, spread: 120, origin: { y: 0.5, x: 0.2 }, colors: ['#10A37F', '#34D399', '#F59E0B'] }); }, 150);
        setTimeout(() => { canvasConfetti({ particleCount: 150, spread: 120, origin: { y: 0.5, x: 0.8 }, colors: ['#10A37F', '#6EE7B7', '#FBBF24'] }); }, 300);
        setTimeout(() => { canvasConfetti({ particleCount: 100, spread: 80, origin: { y: 0.2, x: 0.5 }, colors: ['#10A37F', '#0D8568', '#34D399'] }); }, 450);
        setTimeout(() => { canvasConfetti({ particleCount: 100, spread: 80, origin: { y: 0.8, x: 0.5 }, colors: ['#F59E0B', '#FBBF24', '#10A37F'] }); }, 600);
        for(let i = 0; i < 5; i++) {
            setTimeout(() => { canvasConfetti({ particleCount: 50, spread: 60, origin: { y: 0.7, x: Math.random() }, colors: ['#10A37F', '#34D399', '#F59E0B'] }); }, 800 + (i * 200));
        }
    }
    function closeCelebration() { document.getElementById('celebrationModal').remove(); }
    window.addEventListener('load', function() { triggerFullScreenConfetti(); });
</script>
<?php unset($_SESSION['show_confetti']); ?>
<?php endif; ?>

<!-- Cancel Report Modal -->
<div id="cancelModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[50000] hidden items-center justify-center p-4" onclick="if(event.target===this) closeCancelModal()">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-800">Cancel Report</h3>
                    <p class="text-sm text-gray-500">Please tell us why you're cancelling this report.</p>
                </div>
                <button onclick="closeCancelModal()" class="ml-auto text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="cancelForm" method="POST" action="<?php echo BASE_URL; ?>controllers/ReportController.php">
                <input type="hidden" name="action" value="cancel_report">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">

                <div class="mb-4">
                    <label for="cancel_reason_select" class="block text-sm font-semibold text-gray-700 mb-2">Reason for cancellation</label>
                    <select id="cancel_reason_select" name="cancellation_remarks_select" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none transition" onchange="toggleCancelOther(this.value)">
                        <option value="">Select a reason...</option>
                        <option value="Submitted by mistake">Submitted by mistake</option>
                        <option value="Issue already resolved by the community">Issue already resolved by the community</option>
                        <option value="Duplicate report (I already reported this)">Duplicate report</option>
                        <option value="Other">Other (please specify)</option>
                    </select>
                </div>

                <div id="cancel_other_container" class="mb-4" style="display: none;">
                    <label for="cancel_remarks_other" class="block text-sm font-semibold text-gray-700 mb-2">Please specify</label>
                    <textarea id="cancel_remarks_other" name="cancellation_remarks" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none transition" placeholder="Describe why you're cancelling this report..."></textarea>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeCancelModal()" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl hover:bg-gray-50 transition font-medium">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-red-500 text-white rounded-xl hover:bg-red-600 transition font-semibold">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const sanIsidroBoundary = <?php echo json_encode($boundary_data); ?>;
const reportImages = <?php echo json_encode($images); ?>;
let currentImageIndex = 0;

function extractPolygonCoordinates(geojson) {
    if (!geojson || !geojson.features) return null;
    for (const feature of geojson.features) {
        if (feature.geometry && feature.geometry.type === 'MultiPolygon') {
            const coords = feature.geometry.coordinates[0][0];
            return coords.map(coord => [coord[1], coord[0]]);
        }
    }
    return null;
}

function confirmResolution() {
    return confirm('Have you personally verified that this environmental issue has been fully resolved? Once confirmed, this action cannot be undone.');
}

// ===== CANCEL MODAL =====
function openCancelModal() {
    document.getElementById('cancelModal').classList.remove('hidden');
    document.getElementById('cancelModal').classList.add('flex');
    document.body.style.overflow = 'hidden';
    document.getElementById('cancel_reason_select').value = '';
    document.getElementById('cancel_other_container').style.display = 'none';
    document.getElementById('cancel_remarks_other').value = '';
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
    document.getElementById('cancelModal').classList.remove('flex');
    document.body.style.overflow = '';
}

function toggleCancelOther(value) {
    const container = document.getElementById('cancel_other_container');
    if (value === 'Other') {
        container.style.display = 'block';
        document.getElementById('cancel_remarks_other').focus();
    } else {
        container.style.display = 'none';
        document.getElementById('cancel_remarks_other').value = '';
        // If a predefined reason is selected, store it in the hidden textarea
        document.getElementById('cancel_remarks_other').value = value;
    }
}

// On form submit, ensure the final cancellation_remarks field is set
document.getElementById('cancelForm').addEventListener('submit', function(e) {
    const select = document.getElementById('cancel_reason_select');
    const otherText = document.getElementById('cancel_remarks_other');
    // If a predefined reason is selected (not "Other"), set the textarea to that value
    if (select.value !== 'Other') {
        otherText.value = select.value;
    }
    // If "Other" is selected but empty, prevent submission
    if (select.value === 'Other' && otherText.value.trim().length < 3) {
        e.preventDefault();
        alert('Please specify a reason (at least 3 characters).');
        otherText.focus();
        return false;
    }
    // If no selection, prevent
    if (select.value === '') {
        e.preventDefault();
        alert('Please select a reason for cancellation.');
        select.focus();
        return false;
    }
    return true;
});

// Lightbox functions
function openLightbox(index) {
    currentImageIndex = index;
    const lightbox = document.getElementById('lightboxModal');
    const lightboxImg = document.getElementById('lightboxImage');
    const counter = document.getElementById('lightboxCounter');
    if (reportImages[currentImageIndex]) {
        lightboxImg.src = reportImages[currentImageIndex].image_path;
        counter.textContent = `Image ${currentImageIndex + 1} of ${reportImages.length}`;
    }
    lightbox.classList.remove('hidden');
    lightbox.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const lightbox = document.getElementById('lightboxModal');
    lightbox.classList.add('hidden');
    lightbox.classList.remove('flex');
    document.body.style.overflow = '';
}

function nextImage() {
    if (reportImages.length === 0) return;
    currentImageIndex = (currentImageIndex + 1) % reportImages.length;
    updateLightboxImage();
}

function prevImage() {
    if (reportImages.length === 0) return;
    currentImageIndex = (currentImageIndex - 1 + reportImages.length) % reportImages.length;
    updateLightboxImage();
}

function updateLightboxImage() {
    const lightboxImg = document.getElementById('lightboxImage');
    const counter = document.getElementById('lightboxCounter');
    if (reportImages[currentImageIndex]) {
        lightboxImg.src = reportImages[currentImageIndex].image_path;
        counter.textContent = `Image ${currentImageIndex + 1} of ${reportImages.length}`;
    }
}

document.addEventListener('keydown', function(e) {
    const lightbox = document.getElementById('lightboxModal');
    if (lightbox && lightbox.classList.contains('flex')) {
        if (e.key === 'Escape') { closeLightbox(); }
        else if (e.key === 'ArrowLeft') { prevImage(); }
        else if (e.key === 'ArrowRight') { nextImage(); }
    }
    // Close cancel modal with Escape
    if (e.key === 'Escape') {
        const cancelModal = document.getElementById('cancelModal');
        if (cancelModal && cancelModal.classList.contains('flex')) {
            closeCancelModal();
        }
    }
});

// ============================================
// SUPPORT / VERIFY REPORT (AJAX)
// ============================================
function supportReport(reportId, button) {
    if (!confirm('Do you want to support this report? This helps increase its priority.')) {
        return;
    }

    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const formData = new FormData();
    formData.append('action', 'upvote_report');
    formData.append('report_id', reportId);
    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

    fetch('<?php echo BASE_URL; ?>controllers/ReportController.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Increment verification count
            const countSpan = document.getElementById('verifyCount');
            if (countSpan) {
                countSpan.textContent = parseInt(countSpan.textContent) + 1;
            }

            // Replace button with check icon
            const parentDiv = button.parentElement;
            parentDiv.innerHTML = `
                <div class="w-12 h-12 bg-pink-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-heart text-pink-500 text-2xl"></i>
                </div>
                <p class="text-xs text-pink-500 mt-1 text-center font-medium">Supported</p>
            `;

            // Update description text
            const card = parentDiv.closest('.bg-white');
            const descP = card?.querySelector('p.text-gray-500');
            if (descP) {
                descP.innerHTML = '<span class="font-medium"><i class="fas fa-heart mr-1" style="color:#ef4444;"></i> You supported this report</span>';
            }

            showToast('Thank you for supporting!', 'success');
            // Optionally refresh the page to update the header to supported view
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            alert(data.message || 'Failed to support report.');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-thumbs-up"></i> Support';
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-thumbs-up"></i> Support';
    });
}

// ============================================
// TOAST NOTIFICATION
// ============================================
function showToast(message, type) {
    type = type || 'info';
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    const color = colors[type] || colors.info;
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 ${color} text-white px-5 py-3 rounded-xl shadow-lg flex items-center gap-3 max-w-sm`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Initialize map
document.addEventListener('DOMContentLoaded', function() {
    <?php if($report['latitude'] && $report['longitude'] && $report['latitude'] != 0 && $report['longitude'] != 0): ?>
    var pinColor = '<?php echo strtolower($report['decision_pin'] ?? 'Green'); ?>';
    var colorMap = { 'green': '#10A37F', 'yellow': '#F59E0B', 'orange': '#F97316', 'red': '#EF4444' };
    var color = colorMap[pinColor] || '#10A37F';

    var map = L.map('reportMap').setView([<?php echo $report['latitude']; ?>, <?php echo $report['longitude']; ?>], 16);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);
    
    if (sanIsidroBoundary && sanIsidroBoundary.features) {
        const polygonCoords = extractPolygonCoordinates(sanIsidroBoundary);
        if (polygonCoords) {
            L.polygon(polygonCoords, {
                color: "#10A37F",
                weight: 3,
                fillColor: "#10A37F",
                fillOpacity: 0.12,
                smoothFactor: 1
            }).addTo(map);
        }
    }

    var customIcon = L.divIcon({
        html: '<div style="background-color: ' + color + '; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border: 3px solid white;">' +
                '<i class="fas fa-map-pin" style="color: white; font-size: 20px;"></i>' +
              '</div>',
        iconSize: [45, 45],
        className: 'custom-marker'
    });

    L.marker([<?php echo $report['latitude']; ?>, <?php echo $report['longitude']; ?>], { icon: customIcon })
        .addTo(map)
        .bindPopup(`
            <div class="text-center">
                <strong><i class="fas fa-map-pin mr-1"></i> Report Location</strong><br>
                <span class="text-xs">Priority: <?php echo $report['decision_classification'] ?? 'Routine'; ?></span><br>
                <span class="text-xs">Score: <?php echo $report['severity_score'] ?? 0; ?></span><br>
                <span class="text-xs">${'<?php echo addslashes(htmlspecialchars($report['title'])); ?>'}</span>
            </div>
        `);
    <?php endif; ?>
});

// Hide prev/next if only one image
if (reportImages.length <= 1) {
    setTimeout(() => {
        const prevBtn = document.querySelector('#lightboxModal button[onclick="prevImage()"]');
        const nextBtn = document.querySelector('#lightboxModal button[onclick="nextImage()"]');
        if (prevBtn) prevBtn.classList.add('hidden');
        if (nextBtn) nextBtn.classList.add('hidden');
    }, 100);
}
</script>

</body>
</html>