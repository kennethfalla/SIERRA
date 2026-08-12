<?php
// controllers/ReportController.php - COMPLETE WITH UNDER REVIEW STATUS
// FIXED: All prepared statements now use only named parameters (no mixing)

require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SecurityHelper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SettingsHelper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/PermissionHelper.php';

$database = new Database();
$db = $database->getConnection();
$report = new Report($db);
$activityLog = new ActivityLog($db);

// ============================================
// HELPER: Build obfuscated (token-based) report URLs
// ============================================
function manageReportUrl($id) {
    return BASE_URL . 'index.php?page=manage-report&id=' . IdGuard::enc((int)$id);
}

function trackStatusUrl($id) {
    return BASE_URL . 'index.php?page=track-status&id=' . IdGuard::enc((int)$id);
}

// ============================================
// HELPER: Can the current session access report details
// (used to guard the JSON/AJAX endpoints — IDOR protection)
// ============================================
function canAccessReportData($db, $report_id) {
    $stmt = $db->prepare("SELECT user_id, barangay_id FROM reports WHERE id = ?");
    $stmt->execute([(int)$report_id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return false;
    }

    $role = $_SESSION['user_role'] ?? '';
    $uid  = $_SESSION['user_id'] ?? 0;
    $bid  = $_SESSION['barangay_id'] ?? null;

    if (in_array($role, ['admin', 'menro'], true)) {
        return true;
    }
    if ($role === 'barangay_official') {
        return $bid !== null && (int)$r['barangay_id'] === (int)$bid;
    }
    if ($role === 'citizen') {
        return (int)$r['user_id'] === (int)$uid;
    }
    return false;
}

// ============================================
// HELPER: Recalculate severity for reports near a location
// ============================================
function recalcNearbyReports($db, $lat, $lng, $excludeId = null) {
    $reportModel = new Report($db);
    $reportModel->recalcReportsNearLocation($lat, $lng, $excludeId);
}

// ============================================
// MANAGE REPORT PAGE - Full page view (GET request)
// ============================================
if (isset($_GET['page']) && $_GET['page'] === 'manage-report') {
    requireLogin();
    
    $report_id = IdGuard::req($_GET['id'] ?? '');
    if ($report_id == 0) {
        $_SESSION['error'] = "Invalid report ID.";
        header('Location: ' . BASE_URL . 'index.php?page=dashboard');
        exit();
    }

    $user_role = $_SESSION['user_role'];
    $user_id = $_SESSION['user_id'];
    $barangay_id = $_SESSION['barangay_id'] ?? null;

    $report_data = $report->getReportWithDetails($report_id);
    if (!$report_data) {
        $_SESSION['error'] = "Report not found.";
        header('Location: ' . BASE_URL . 'index.php?page=dashboard');
        exit();
    }

    // Permission checks
    if ($user_role == 'citizen' && $report_data['user_id'] != $user_id) {
        $_SESSION['error'] = "You don't have permission to view this report.";
        header('Location: ' . BASE_URL . 'index.php?page=my-reports');
        exit();
    }
    if ($user_role == 'barangay_official' && $report_data['barangay_id'] != $barangay_id) {
        $_SESSION['error'] = "You don't have permission to view this report.";
        header('Location: ' . BASE_URL . 'index.php?page=verify-reports');
        exit();
    }
    if ($report_data['status'] == Report::STATUS_CANCELLED && $user_role != 'admin' && $report_data['user_id'] != $user_id) {
        $_SESSION['error'] = "This report has been cancelled and is not accessible.";
        header('Location: ' . BASE_URL . 'index.php?page=dashboard');
        exit();
    }

    // ============================================
    // AUTO-FLIP TO UNDER REVIEW FOR BARANGAY OFFICIALS
    // ============================================
    if ($user_role === 'barangay_official' && 
        $report_data['status'] === Report::STATUS_PENDING &&
        $report_data['barangay_id'] == $barangay_id) {
        
        $updateStmt = $db->prepare("UPDATE reports SET status = :status WHERE id = :id");
        $updateStmt->execute([
            ':status' => Report::STATUS_UNDER_REVIEW,
            ':id' => $report_id
        ]);
        
        $activityLog->log(
            $user_id,
            'Status Change',
            "Auto‑flipped report #$report_id to Under Review (barangay viewed the report)"
        );
        
        $report_data = $report->getReportWithDetails($report_id);
    }

    $images = $report->getImagesByReport($report_id);
    foreach ($images as &$img) {
        $img['is_video'] = preg_match('/\.(mp4|webm|mov|m4v|avi)$/i', $img['image_path']) ? 1 : 0;
    }
    unset($img);
    $notes = $report->getNotes($report_id);
    $resolution_evidence = $report->getResolutionEvidence($report_id);
    foreach ($resolution_evidence as &$ev) {
        $ev['is_video'] = preg_match('/\.(mp4|webm|mov|m4v|avi)$/i', $ev['image_path']) ? 1 : 0;
    }
    unset($ev);

    $esc_stmt = $db->prepare("SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) as escalated_by_name 
                              FROM escalations e 
                              LEFT JOIN users u ON e.escalated_by = u.id 
                              WHERE e.report_id = ? ORDER BY e.escalated_at DESC LIMIT 1");
    $esc_stmt->execute([$report_id]);
    $escalation = $esc_stmt->fetch(PDO::FETCH_ASSOC);

    $has_pending_escalation = false;
    $has_approved_escalation = false;
    $was_escalation_rejected = false;
    $check_stmt = $db->prepare("SELECT status FROM escalations WHERE report_id = ? ORDER BY escalated_at DESC LIMIT 1");
    $check_stmt->execute([$report_id]);
    $esc_status = $check_stmt->fetch(PDO::FETCH_ASSOC);
    if ($esc_status) {
        if ($esc_status['status'] == 'pending') $has_pending_escalation = true;
        elseif ($esc_status['status'] == 'approved') $has_approved_escalation = true;
        elseif ($esc_status['status'] == 'rejected') $was_escalation_rejected = true;
    }

    // RBAC-aware action flags: allowedReportActions() already applies the
    // can_manage_reports permission, the user_type scoping (barangay_personnel
    // manages own barangay's non-escalated reports; menro_staff/admin manage
    // escalated reports), and the super-admin bypass. We AND that with the
    // status/ownership checks below.
    $can_manage = PermissionHelper::canManageReport($report_data);
    $allowedActions = PermissionHelper::allowedReportActions($report_data);

    $can_verify = (
        in_array('verify', $allowedActions) &&
        $report_data['status'] == Report::STATUS_UNDER_REVIEW
    );
    $can_reject = (
        in_array('reject', $allowedActions) &&
        $report_data['status'] == Report::STATUS_UNDER_REVIEW
    );
    $can_escalate = (
        in_array('escalate', $allowedActions) &&
        $report_data['status'] == Report::STATUS_UNDER_REVIEW &&
        !$has_pending_escalation &&
        !$has_approved_escalation &&
        !$was_escalation_rejected
    );
    $can_resolve = (
        in_array('mark_resolved', $allowedActions) &&
        (($user_role == 'barangay_official' && $report_data['status'] == Report::STATUS_IN_PROGRESS) ||
         ($user_role == 'admin' && $report_data['status'] == Report::STATUS_ESCALATED))
    );
    $can_approve_escalation = ($user_role == 'admin' && $report_data['status'] == Report::STATUS_ESCALATED_PENDING) && $can_manage;
    $can_reject_escalation = in_array('reject_escalation', $allowedActions) && $report_data['status'] == Report::STATUS_ESCALATED_PENDING;
    $can_reclassify = $can_manage && (
        ($user_role == 'barangay_official' && $report_data['status'] == Report::STATUS_IN_PROGRESS) ||
        ($user_role == 'admin' && in_array($report_data['status'], [Report::STATUS_ESCALATED_PENDING, Report::STATUS_ESCALATED]))
    );
    $show_notes = in_array($report_data['status'], [Report::STATUS_IN_PROGRESS, Report::STATUS_ESCALATED_PENDING, Report::STATUS_ESCALATED]);

    $view_data = [
        'report' => $report_data,
        'images' => $images,
        'notes' => $notes,
        'resolution_evidence' => $resolution_evidence,
        'escalation' => $escalation,
        'user_role' => $user_role,
        'user_id' => $user_id,
        'barangay_id' => $barangay_id,
        'has_pending_escalation' => $has_pending_escalation,
        'has_approved_escalation' => $has_approved_escalation,
        'was_escalation_rejected' => $was_escalation_rejected,
        'can_verify' => $can_verify,
        'can_reject' => $can_reject,
        'can_resolve' => $can_resolve,
        'can_escalate' => $can_escalate,
        'can_approve_escalation' => $can_approve_escalation,
        'can_reject_escalation' => $can_reject_escalation,
        'can_reclassify' => $can_reclassify,
        'show_notes' => $show_notes
    ];

    require_once 'views/shared/manage_report.php';
    exit();
}

// ============================================
// AJAX ENDPOINTS (GET requests)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $report_id = IdGuard::req($_GET['id'] ?? '');

    if (($action === 'get_full' || $action === 'get_notes' || $action === 'get_images') && $report_id > 0) {
        // IDOR protection: these endpoints only serve data the current
        // session is authorized to access (admin = all, barangay_official =
        // own barangay, citizen = own reports).
        if (!isset($_SESSION['user_id']) || !canAccessReportData($db, $report_id)) {
            echo json_encode(['error' => 'Access denied.']);
            exit();
        }
    }

    if ($action === 'get_full' && $report_id > 0) {
        $stmt = $db->prepare("
            SELECT r.*, c.name as category_name, 
                   CONCAT(u.first_name, ' ', u.last_name) as user_name, 
                   u.email as user_email,
                   b.name as barangay_name,
                   (SELECT GROUP_CONCAT(image_path) FROM report_images WHERE report_id = r.id) as image_paths,
                   (SELECT GROUP_CONCAT(image_path) FROM resolution_evidence WHERE report_id = r.id) as resolution_evidence_paths
            FROM reports r
            JOIN categories c ON r.category_id = c.id
            JOIN users u ON r.user_id = u.id
            JOIN barangays b ON r.barangay_id = b.id
            WHERE r.id = ?
        ");
        $stmt->execute([$report_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($result);
        exit();
    }

    if ($action === 'get_notes' && $report_id > 0) {
        $stmt = $db->prepare("
            SELECT n.*, CONCAT(u.first_name, ' ', u.last_name) as user_name 
            FROM report_notes n 
            JOIN users u ON n.user_id = u.id 
            WHERE n.report_id = ? 
            ORDER BY n.created_at DESC
        ");
        $stmt->execute([$report_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
        exit();
    }

    if ($action === 'get_images' && $report_id > 0) {
        $stmt = $db->prepare("SELECT * FROM report_images WHERE report_id = ? ORDER BY is_primary DESC");
        $stmt->execute([$report_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as &$img) {
            $img['image_path'] = BASE_URL . $img['image_path'];
            $img['is_video'] = preg_match('/\.(mp4|webm|mov|m4v|avi)$/i', $img['image_path']) ? 1 : 0;
        }
        echo json_encode($result);
        exit();
    }

    // ============================================
    // DUPLICATE DETECTION - Nearby active reports
    // Powers the "Did you mean...?" modal on submit_report.php
    // ============================================
    if ($action === 'check_nearby_reports') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
            exit();
        }

        $lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        $category_id = filter_var($_GET['category_id'] ?? 0, FILTER_VALIDATE_INT);

        if ($lat === false || $lng === false || $lat === null || $lng === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid coordinates.']);
            exit();
        }

        $radius = (int)SettingsHelper::get('clustering_radius_meters', 50);

        try {
            // Exclude current user's own reports from nearby detection
            $nearby = $report->getActiveReportsNearLocation($lat, $lng, $radius, $category_id ?: 0, $_SESSION['user_id']);
            echo json_encode(['success' => true, 'reports' => $nearby, 'debug_radius' => $radius, 'debug_lat' => $lat, 'debug_lng' => $lng]);
        } catch (\PDOException $e) {
            error_log('check_nearby_reports failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lookup failed.', 'debug_error' => $e->getMessage()]);
        }
        exit();
    }

    echo json_encode(['error' => 'Invalid action']);
    exit();
}

// ============================================
// CHECK LOGIN FOR POST REQUESTS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to perform this action.";
    header("Location: " . BASE_URL . "index.php?page=login");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'citizen';

// ============================================
// POST REQUESTS - Action Handlers
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
            exit();
        }
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: " . BASE_URL . "index.php?page=dashboard");
        exit();
    }

    // ============================================
    // CANCEL REPORT (Resident only, pending status)
    // ============================================
    if ($action === 'cancel_report') {
        // KILL SWITCH: citizen cancellations disabled
        if (SettingsHelper::get('allow_citizen_cancellations', '1') != '1') {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Cancellation is currently disabled by the system administrator.']); exit(); }
            $_SESSION['error'] = "Cancellation is currently disabled by the system administrator.";
            header("Location: " . BASE_URL . "index.php?page=my-reports");
            exit();
        }
        $report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($report_id <= 0) {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Invalid report ID.']); exit(); }
            $_SESSION['error'] = "Invalid report ID.";
            header("Location: " . BASE_URL . "index.php?page=my-reports");
            exit();
        }
        if ($user_role !== 'citizen') {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Only citizens can cancel reports.']); exit(); }
            $_SESSION['error'] = "Only citizens can cancel reports.";
            header("Location: " . BASE_URL . "index.php?page=my-reports");
            exit();
        }
        $check_stmt = $db->prepare("SELECT id, user_id, status, latitude, longitude FROM reports WHERE id = ?");
        $check_stmt->execute([$report_id]);
        $report_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report_data) {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Report not found.']); exit(); }
            $_SESSION['error'] = "Report not found.";
            header("Location: " . BASE_URL . "index.php?page=my-reports");
            exit();
        }
        if ($report_data['user_id'] != $user_id) {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'You do not own this report.']); exit(); }
            $_SESSION['error'] = "You do not own this report.";
            header("Location: " . BASE_URL . "index.php?page=my-reports");
            exit();
        }
        if ($report_data['status'] !== Report::STATUS_PENDING) {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Only pending reports can be cancelled.']); exit(); }
            $_SESSION['error'] = "Only pending reports can be cancelled.";
            header("Location: " . BASE_URL . "index.php?page=my-reports");
            exit();
        }
        $update = $db->prepare("UPDATE reports SET status = :cancelled WHERE id = :id");
        $result = $update->execute([
            ':cancelled' => Report::STATUS_CANCELLED,
            ':id' => $report_id
        ]);
        if ($result) {
            if ($report_data['latitude'] && $report_data['longitude']) {
                recalcNearbyReports($db, $report_data['latitude'], $report_data['longitude'], $report_id);
            }
            $activityLog->log($user_id, 'Cancel Report', "Cancelled report #$report_id");
            if ($is_ajax) {
                echo json_encode(['success' => true, 'message' => 'Report cancelled successfully.']);
                exit();
            }
            $_SESSION['success'] = "Report cancelled successfully. You can submit a new accurate report.";
            header("Location: " . BASE_URL . "index.php?page=my-reports");
            exit();
        } else {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'Failed to cancel report.']);
                exit();
            }
            $_SESSION['error'] = "Failed to cancel report.";
            header("Location: " . BASE_URL . "index.php?page=my-reports");
            exit();
        }
    }

    // ============================================
    // UPVOTE / SUPPORT REPORT (Citizen confirms an existing report is the same issue)
    // ============================================
    if ($action === 'upvote_report') {
        // KILL SWITCH: community support/verification disabled
        if (SettingsHelper::get('enable_report_support', '1') != '1') {
            echo json_encode(['success' => false, 'message' => 'Report support is currently disabled by the system administrator.']);
            exit();
        }
        $report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($report_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid report ID.']);
            exit();
        }

        $result = $report->addVerification($report_id, $user_id);

        if ($result['success']) {
            $activityLog->log($user_id, 'Support Report', "Supported/verified report #$report_id");
        }

        echo json_encode($result);
        exit();
    }

    // ============================================
    // VERIFY REPORT (Barangay only)
    // ============================================
    if ($action === 'verify_report') {
        $report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($report_id <= 0) {
            $_SESSION['error'] = "Invalid report ID.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if ($user_role !== 'barangay_official') {
            $_SESSION['error'] = "You don't have permission to verify reports.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        $check_stmt = $db->prepare("SELECT id, barangay_id, status, latitude, longitude FROM reports WHERE id = ?");
        $check_stmt->execute([$report_id]);
        $report_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report_data) {
            $_SESSION['error'] = "Report not found.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if (!PermissionHelper::canManageReport($report_data)) {
            $_SESSION['error'] = "You are not permitted to manage this report.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if ($report_data['barangay_id'] != $_SESSION['barangay_id']) {
            $_SESSION['error'] = "You don't have permission to verify this report.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if ($report_data['status'] == Report::STATUS_CANCELLED) {
            $_SESSION['error'] = "Cannot verify a cancelled report.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if ($report_data['status'] !== Report::STATUS_UNDER_REVIEW) {
            $_SESSION['error'] = "Only reports under review can be verified.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }

        // FIXED: All named parameters
        $stmt = $db->prepare("UPDATE reports SET status = :status, verified_by = :verified_by, verified_at = NOW() WHERE id = :id");
        $stmt->execute([
            ':status' => Report::STATUS_IN_PROGRESS,
            ':verified_by' => $user_id,
            ':id' => $report_id
        ]);
        $report->calculateAndUpdateSeverity($report_id);
        if ($report_data['latitude'] && $report_data['longitude']) {
            recalcNearbyReports($db, $report_data['latitude'], $report_data['longitude'], $report_id);
        }
        $activityLog->log($user_id, 'Verify Report', "Verified report #$report_id and moved to In Progress");
        $_SESSION['success'] = "Report #$report_id verified and moved to IN PROGRESS.";
        header("Location: " . manageReportUrl($report_id));
        exit();
    }

    // ============================================
    // REJECT REPORT (Barangay only)
    // ============================================
    if ($action === 'reject_report') {
        $report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
        $reason = InputSanitizer::sanitizeString($_POST['rejection_reason'] ?? '', 1000);
        if ($report_id <= 0) {
            $_SESSION['error'] = "Invalid report ID.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if (strlen($reason) < 5) {
            $_SESSION['error'] = "Please provide a detailed rejection reason (at least 5 characters).";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
        if ($user_role !== 'barangay_official') {
            $_SESSION['error'] = "You don't have permission to reject reports.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        $check_stmt = $db->prepare("SELECT id, barangay_id, status, latitude, longitude FROM reports WHERE id = ?");
        $check_stmt->execute([$report_id]);
        $report_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report_data) {
            $_SESSION['error'] = "Report not found.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if (!PermissionHelper::canManageReport($report_data)) {
            $_SESSION['error'] = "You are not permitted to manage this report.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if ($report_data['barangay_id'] != $_SESSION['barangay_id']) {
            $_SESSION['error'] = "You don't have permission to reject this report.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if ($report_data['status'] == Report::STATUS_CANCELLED) {
            $_SESSION['error'] = "Cannot reject a cancelled report.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if ($report_data['status'] !== Report::STATUS_UNDER_REVIEW) {
            $_SESSION['error'] = "Only reports under review can be rejected.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }

        // FIXED: All named parameters
        $stmt = $db->prepare("UPDATE reports SET status = :status, rejection_reason = :rejection_reason, rejected_at = NOW() WHERE id = :id");
        $stmt->execute([
            ':status' => Report::STATUS_REJECTED,
            ':rejection_reason' => $reason,
            ':id' => $report_id
        ]);
        $report->calculateAndUpdateSeverity($report_id);
        if ($report_data['latitude'] && $report_data['longitude']) {
            recalcNearbyReports($db, $report_data['latitude'], $report_data['longitude'], $report_id);
        }
        $activityLog->log($user_id, 'Reject Report', "Rejected report #$report_id. Reason: $reason");
        $_SESSION['success'] = "Report #$report_id rejected.";
        header("Location: " . manageReportUrl($report_id));
        exit();
    }

    // ============================================
    // RESOLVE REPORT (Barangay or Admin)
    // ============================================
    if ($action === 'resolve_report') {
        $report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($report_id <= 0) {
            $_SESSION['error'] = "Invalid report ID.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        $check_stmt = $db->prepare("SELECT id, status, barangay_id, latitude, longitude FROM reports WHERE id = ?");
        $check_stmt->execute([$report_id]);
        $report_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report_data) {
            $_SESSION['error'] = "Report not found.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if (!PermissionHelper::canManageReport($report_data)) {
            $_SESSION['error'] = "You are not permitted to manage this report.";
            header("Location: " . BASE_URL . "index.php?page=" . (($user_role == 'admin') ? 'all-reports' : 'verify-reports'));
            exit();
        }
        if ($report_data['status'] == Report::STATUS_CANCELLED) {
            $_SESSION['error'] = "Cannot resolve a cancelled report.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if ($user_role == 'barangay_official') {
            if ($report_data['status'] != Report::STATUS_IN_PROGRESS) {
                $_SESSION['error'] = "Only in-progress reports can be resolved by barangay.";
                header("Location: " . manageReportUrl($report_id));
                exit();
            }
            if ($report_data['barangay_id'] != $_SESSION['barangay_id']) {
                $_SESSION['error'] = "You don't have permission to resolve this report.";
                header("Location: " . BASE_URL . "index.php?page=verify-reports");
                exit();
            }
        } elseif ($user_role == 'admin') {
            if ($report_data['status'] != Report::STATUS_ESCALATED) {
                $_SESSION['error'] = "Only escalated reports can be resolved by MENRO.";
                header("Location: " . BASE_URL . "index.php?page=all-reports");
                exit();
            }
        } else {
            $_SESSION['error'] = "You don't have permission to resolve reports.";
            header("Location: " . BASE_URL . "index.php?page=dashboard");
            exit();
        }

        if (!isset($_FILES['resolution_image']) || $_FILES['resolution_image']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Please upload a photo as proof of resolution.";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
        $file = $_FILES['resolution_image'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowed)) {
            $_SESSION['error'] = "Invalid file type. Allowed: JPG, PNG, GIF, WebP.";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
        if ($file['size'] > 5242880) {
            $_SESSION['error'] = "File size exceeds 5MB limit.";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
        $filename = 'resolution_' . uniqid() . '.' . $extension;
        $target_path = UPLOAD_DIR . $filename;
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            $_SESSION['error'] = "Failed to upload resolution image.";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
        $image_path = 'uploads/reports/' . $filename;
        $caption = trim($_POST['resolution_note'] ?? '');
        $stmt = $db->prepare("INSERT INTO resolution_evidence (report_id, image_path, uploaded_by, caption) VALUES (?, ?, ?, ?)");
        $stmt->execute([$report_id, $image_path, $user_id, $caption]);
        $activityLog->log($user_id, 'Evidence Upload', "Uploaded resolution evidence for report #$report_id", null, 'Reports');

        // FIXED: All named parameters
        $stmt = $db->prepare("UPDATE reports SET status = :status, resolved_at = NOW() WHERE id = :id");
        $stmt->execute([
            ':status' => Report::STATUS_RESOLVED,
            ':id' => $report_id
        ]);
        $report->calculateAndUpdateSeverity($report_id);
        if ($report_data['latitude'] && $report_data['longitude']) {
            recalcNearbyReports($db, $report_data['latitude'], $report_data['longitude'], $report_id);
        }
        $activityLog->log($user_id, 'Resolve Report', "Resolved report #$report_id");
        $_SESSION['success'] = "Report #$report_id marked as RESOLVED.";
        $redirect = ($user_role == 'admin') ? 'all-reports' : 'manage-report';
        header("Location: " . BASE_URL . "index.php?page=" . $redirect . "&id=" . IdGuard::enc($report_id));
        exit();
    }

    // ============================================
    // ESCALATE TO MENRO (Barangay only)
    // ============================================
    if ($action === 'escalate_report') {
        // KILL SWITCH: escalation to MENRO disabled
        if (SettingsHelper::get('enable_escalation', '1') != '1') {
            $_SESSION['error'] = "Escalation is currently disabled by the system administrator.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        $report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
        $reason = InputSanitizer::sanitizeString($_POST['escalation_reason'] ?? '', 1000);
        if ($report_id <= 0) {
            $_SESSION['error'] = "Invalid report ID.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if (strlen($reason) < 10) {
            $_SESSION['error'] = "Please provide a detailed justification (at least 10 characters).";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
        if ($user_role !== 'barangay_official') {
            $_SESSION['error'] = "You don't have permission to escalate reports.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        $check_stmt = $db->prepare("SELECT id, barangay_id, status, latitude, longitude FROM reports WHERE id = ?");
        $check_stmt->execute([$report_id]);
        $report_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report_data) {
            $_SESSION['error'] = "Report not found.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if (!PermissionHelper::canManageReport($report_data)) {
            $_SESSION['error'] = "You are not permitted to manage this report.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if ($report_data['status'] == Report::STATUS_CANCELLED) {
            $_SESSION['error'] = "Cannot escalate a cancelled report.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if ($report_data['barangay_id'] != $_SESSION['barangay_id']) {
            $_SESSION['error'] = "You don't have permission to escalate this report.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if ($report_data['status'] != Report::STATUS_UNDER_REVIEW) {
            $_SESSION['error'] = "Only reports under review can be escalated.";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
        $check_esc = $db->prepare("SELECT id, status FROM escalations WHERE report_id = ? ORDER BY escalated_at DESC LIMIT 1");
        $check_esc->execute([$report_id]);
        $existing = $check_esc->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if ($existing['status'] == 'pending') {
                $_SESSION['error'] = "This report already has a pending escalation request.";
                header("Location: " . manageReportUrl($report_id));
                exit();
            } elseif ($existing['status'] == 'approved') {
                $_SESSION['error'] = "This report is already under MENRO supervision.";
                header("Location: " . manageReportUrl($report_id));
                exit();
            }
        }
        $stmt = $db->prepare("INSERT INTO escalations (report_id, escalated_by, escalation_reason, escalated_at, status) VALUES (?, ?, ?, NOW(), 'pending')");
        $stmt->execute([$report_id, $user_id, $reason]);

        // FIXED: All named parameters
        $stmt = $db->prepare("UPDATE reports SET status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => Report::STATUS_ESCALATED_PENDING,
            ':id' => $report_id
        ]);
        $report->calculateAndUpdateSeverity($report_id);
        if ($report_data['latitude'] && $report_data['longitude']) {
            recalcNearbyReports($db, $report_data['latitude'], $report_data['longitude'], $report_id);
        }
        $activityLog->log($user_id, 'Escalate Report', "Escalated report #$report_id to MENRO. Reason: $reason");
        $_SESSION['success'] = "Report #$report_id escalated to MENRO.";
        header("Location: " . manageReportUrl($report_id));
        exit();
    }

    // ============================================
    // APPROVE ESCALATION (Admin only)
    // ============================================
    if ($action === 'approve_escalation') {
        $report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($report_id <= 0) {
            $_SESSION['error'] = "Invalid report ID.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
        }
        if ($user_role !== 'admin') {
            $_SESSION['error'] = "You don't have permission to approve escalations.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
        }
        $check_stmt = $db->prepare("SELECT id, barangay_id, status, latitude, longitude FROM reports WHERE id = ?");
        $check_stmt->execute([$report_id]);
        $report_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report_data) {
            $_SESSION['error'] = "Report not found.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
        }
        if (!PermissionHelper::canManageReport($report_data)) {
            $_SESSION['error'] = "You are not permitted to manage this report.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
        }
        if ($report_data['status'] == Report::STATUS_CANCELLED) {
            $_SESSION['error'] = "Cannot approve escalation for a cancelled report.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
        }
        if ($report_data['status'] != Report::STATUS_ESCALATED_PENDING) {
            $_SESSION['error'] = "This report is not pending escalation approval.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
        }

        // FIXED: All named parameters
        $stmt = $db->prepare("UPDATE reports SET status = :status, menro_accepted = 1, escalated_to_menro = 1 WHERE id = :id");
        $stmt->execute([
            ':status' => Report::STATUS_ESCALATED,
            ':id' => $report_id
        ]);
        $stmt = $db->prepare("UPDATE escalations SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE report_id = ? AND status = 'pending'");
        $stmt->execute([$user_id, $report_id]);
        $report->calculateAndUpdateSeverity($report_id);
        if ($report_data['latitude'] && $report_data['longitude']) {
            recalcNearbyReports($db, $report_data['latitude'], $report_data['longitude'], $report_id);
        }
        $activityLog->log($user_id, 'Approve Escalation', "Approved escalation for report #$report_id");
        $_SESSION['success'] = "Escalation approved. Report is now under MENRO supervision.";
        header("Location: " . manageReportUrl($report_id));
        exit();
    }

    // ============================================
    // REJECT ESCALATION (Admin only)
    // ============================================
    if ($action === 'reject_escalation') {
        $report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
        $reason = InputSanitizer::sanitizeString($_POST['rejection_reason'] ?? '', 1000);
        if ($report_id <= 0) {
            $_SESSION['error'] = "Invalid report ID.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
        }
        if (strlen($reason) < 5) {
            $_SESSION['error'] = "Please provide a detailed rejection reason (at least 5 characters).";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
        if ($user_role !== 'admin') {
            $_SESSION['error'] = "You don't have permission to reject escalations.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
        }
        $check_stmt = $db->prepare("SELECT id, barangay_id, status, latitude, longitude FROM reports WHERE id = ?");
        $check_stmt->execute([$report_id]);
        $report_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report_data) {
            $_SESSION['error'] = "Report not found.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
        }
        if (!PermissionHelper::canManageReport($report_data)) {
            $_SESSION['error'] = "You are not permitted to manage this report.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
        }
        if ($report_data['status'] == Report::STATUS_CANCELLED) {
            $_SESSION['error'] = "Cannot reject escalation for a cancelled report.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
        }
        if ($report_data['status'] != Report::STATUS_ESCALATED_PENDING) {
            $_SESSION['error'] = "This report is not pending escalation approval.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
        }

        // FIXED: All named parameters
        $stmt = $db->prepare("UPDATE reports SET status = :status, menro_accepted = 0 WHERE id = :id");
        $stmt->execute([
            ':status' => Report::STATUS_IN_PROGRESS,
            ':id' => $report_id
        ]);
        $stmt = $db->prepare("UPDATE escalations SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ? WHERE report_id = ? AND status = 'pending'");
        $stmt->execute([$user_id, $reason, $report_id]);
        $report->calculateAndUpdateSeverity($report_id);
        if ($report_data['latitude'] && $report_data['longitude']) {
            recalcNearbyReports($db, $report_data['latitude'], $report_data['longitude'], $report_id);
        }
        $activityLog->log($user_id, 'Reject Escalation', "Rejected escalation for report #$report_id. Reason: $reason");
        $_SESSION['success'] = "Escalation rejected. Report returned to barangay.";
        header("Location: " . manageReportUrl($report_id));
        exit();
    }

    // ============================================
    // RECLASSIFY IMPACT (Barangay or Admin)
    // ============================================
    if ($action === 'reclassify_impact') {
        $report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
        $new_impact = isset($_POST['new_impact']) ? (int)$_POST['new_impact'] : -1;
        $reason = InputSanitizer::sanitizeString($_POST['reclassify_reason'] ?? '', 500);
        if ($report_id <= 0) {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Invalid report ID.']); exit(); }
            $_SESSION['error'] = "Invalid report ID.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if (!in_array($new_impact, [0, 2, 4])) {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Invalid impact value.']); exit(); }
            $_SESSION['error'] = "Invalid impact value.";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
        if (empty($reason) || strlen($reason) < 3) {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Please provide a reason for reclassification.']); exit(); }
            $_SESSION['error'] = "Please provide a reason for reclassification.";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
        if ($user_role == 'barangay_official') {
            $check_stmt = $db->prepare("SELECT id, barangay_id, status, latitude, longitude FROM reports WHERE id = ?");
            $check_stmt->execute([$report_id]);
            $report_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$report_data) {
                if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Report not found.']); exit(); }
                $_SESSION['error'] = "Report not found.";
                header("Location: " . BASE_URL . "index.php?page=verify-reports");
                exit();
            }
            if (!PermissionHelper::canManageReport($report_data)) {
                if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'You are not permitted to manage this report.']); exit(); }
                $_SESSION['error'] = "You are not permitted to manage this report.";
                header("Location: " . BASE_URL . "index.php?page=verify-reports");
                exit();
            }
            if ($report_data['status'] == Report::STATUS_CANCELLED) {
                if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Cannot reclassify a cancelled report.']); exit(); }
                $_SESSION['error'] = "Cannot reclassify a cancelled report.";
                header("Location: " . BASE_URL . "index.php?page=verify-reports");
                exit();
            }
            if ($report_data['barangay_id'] != $_SESSION['barangay_id']) {
                if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'You don\'t have permission.']); exit(); }
                $_SESSION['error'] = "You don't have permission.";
                header("Location: " . BASE_URL . "index.php?page=verify-reports");
                exit();
            }
            if ($report_data['status'] != Report::STATUS_IN_PROGRESS) {
                if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Only in-progress reports can be reclassified.']); exit(); }
                $_SESSION['error'] = "Only in-progress reports can be reclassified.";
                header("Location: " . manageReportUrl($report_id));
                exit();
            }
        } elseif ($user_role == 'admin') {
            $check_stmt = $db->prepare("SELECT id, barangay_id, status, latitude, longitude FROM reports WHERE id = ?");
            $check_stmt->execute([$report_id]);
            $report_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$report_data) {
                if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Report not found.']); exit(); }
                $_SESSION['error'] = "Report not found.";
                header("Location: " . BASE_URL . "index.php?page=all-reports");
                exit();
            }
            if (!PermissionHelper::canManageReport($report_data)) {
                if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'You are not permitted to manage this report.']); exit(); }
                $_SESSION['error'] = "You are not permitted to manage this report.";
                header("Location: " . BASE_URL . "index.php?page=all-reports");
                exit();
            }
            if ($report_data['status'] == Report::STATUS_CANCELLED) {
                if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Cannot reclassify a cancelled report.']); exit(); }
                $_SESSION['error'] = "Cannot reclassify a cancelled report.";
                header("Location: " . BASE_URL . "index.php?page=all-reports");
                exit();
            }
            if (!in_array($report_data['status'], [Report::STATUS_ESCALATED_PENDING, Report::STATUS_ESCALATED])) {
                if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Only escalated reports can be reclassified by MENRO.']); exit(); }
                $_SESSION['error'] = "Only escalated reports can be reclassified by MENRO.";
                header("Location: " . BASE_URL . "index.php?page=all-reports");
                exit();
            }
        } else {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'You don\'t have permission.']); exit(); }
            $_SESSION['error'] = "You don't have permission.";
            header("Location: " . BASE_URL . "index.php?page=dashboard");
            exit();
        }
        if ($report->reclassifyImpact($report_id, $new_impact, $user_id)) {
            $log = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'Reclassify Impact', ?, ?, NOW())");
            $role_label = ($user_role == 'admin') ? 'Admin' : 'Barangay';
            $description = "$role_label reclassified report #$report_id impact modifier to $new_impact. Reason: $reason";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log->execute([$user_id, $description, $ip]);
            if ($report_data['latitude'] && $report_data['longitude']) {
                recalcNearbyReports($db, $report_data['latitude'], $report_data['longitude'], $report_id);
            }
            if ($is_ajax) {
                echo json_encode(['success' => true, 'message' => 'Impact modifier reclassified successfully.']);
                exit();
            }
            $_SESSION['success'] = "Impact modifier reclassified successfully.";
            header("Location: " . manageReportUrl($report_id));
            exit();
        } else {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'Failed to reclassify.']);
                exit();
            }
            $_SESSION['error'] = "Failed to reclassify.";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
    }

    // ============================================
    // ADD NOTE (Barangay or Admin)
    // ============================================
    if ($action === 'add_note' || isset($_POST['add_note'])) {
        $report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
        $note = InputSanitizer::sanitizeString($_POST['note'] ?? '', 2000);
        if ($report_id <= 0) {
            if ($is_ajax) { echo json_encode(['success' => false, 'error' => 'Invalid report ID.']); exit(); }
            $_SESSION['error'] = "Invalid report ID.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if (empty($note) || strlen($note) < 2) {
            if ($is_ajax) { echo json_encode(['success' => false, 'error' => 'Note must be at least 2 characters.']); exit(); }
            $_SESSION['error'] = "Note must be at least 2 characters.";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
        $check_stmt = $db->prepare("SELECT id, barangay_id, status FROM reports WHERE id = ?");
        $check_stmt->execute([$report_id]);
        $report_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report_data) {
            if ($is_ajax) { echo json_encode(['success' => false, 'error' => 'Report not found.']); exit(); }
            $_SESSION['error'] = "Report not found.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if (!PermissionHelper::canManageReport($report_data)) {
            if ($is_ajax) { echo json_encode(['success' => false, 'error' => 'You are not permitted to manage this report.']); exit(); }
            $_SESSION['error'] = "You are not permitted to manage this report.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if ($report_data['status'] == Report::STATUS_CANCELLED) {
            if ($is_ajax) { echo json_encode(['success' => false, 'error' => 'Cannot add notes to cancelled report.']); exit(); }
            $_SESSION['error'] = "Cannot add notes to cancelled report.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        if (!in_array($report_data['status'], [Report::STATUS_IN_PROGRESS, Report::STATUS_ESCALATED_PENDING, Report::STATUS_ESCALATED])) {
            if ($is_ajax) { echo json_encode(['success' => false, 'error' => 'Notes can only be added to active reports.']); exit(); }
            $_SESSION['error'] = "Notes can only be added to active reports.";
            header("Location: " . manageReportUrl($report_id));
            exit();
        }
        if ($user_role == 'barangay_official' && $report_data['barangay_id'] != $_SESSION['barangay_id']) {
            if ($is_ajax) { echo json_encode(['success' => false, 'error' => 'You don\'t have permission.']); exit(); }
            $_SESSION['error'] = "You don't have permission.";
            header("Location: " . BASE_URL . "index.php?page=verify-reports");
            exit();
        }
        $stmt = $db->prepare("INSERT INTO report_notes (report_id, user_id, note, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$report_id, $user_id, $note]);
        $activityLog->log($user_id, 'Add Note', "Added note to report #$report_id");
        if ($is_ajax) {
            echo json_encode(['success' => true]);
            exit();
        }
        $_SESSION['success'] = "Note added successfully.";
        header("Location: " . manageReportUrl($report_id));
        exit();
    }

    // ============================================
    // DELETE REPORT (AJAX or Non-AJAX)
    // ============================================
    if ($action === 'delete' || $action === 'ajax_delete') {
        $report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($report_id <= 0) {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Invalid report ID.']); exit(); }
            $_SESSION['error'] = "Invalid report ID.";
            header("Location: " . BASE_URL . "index.php?page=my-reports");
            exit();
        }
        $check_stmt = $db->prepare("SELECT id, user_id, status, latitude, longitude FROM reports WHERE id = ?");
        $check_stmt->execute([$report_id]);
        $report_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report_data) {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'Report not found.']); exit(); }
            $_SESSION['error'] = "Report not found.";
            header("Location: " . BASE_URL . "index.php?page=my-reports");
            exit();
        }
        $is_admin = ($user_role === 'admin' || $user_role === 'administrator');
        $is_owner = ($report_data['user_id'] == $user_id);
        $is_pending = ($report_data['status'] === Report::STATUS_PENDING);
        if (!$is_admin && !($is_owner && $is_pending)) {
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => 'You don\'t have permission to delete this report. Only pending reports can be deleted.']); exit(); }
            $_SESSION['error'] = "You don't have permission to delete this report.";
            header("Location: " . BASE_URL . "index.php?page=my-reports");
            exit();
        }
        $db->beginTransaction();
        try {
            $img_stmt = $db->prepare("SELECT image_path FROM report_images WHERE report_id = ?");
            $img_stmt->execute([$report_id]);
            $images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($images as $img) {
                $file_path = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/' . $img['image_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            $db->prepare("DELETE FROM report_images WHERE report_id = ?")->execute([$report_id]);
            $db->prepare("DELETE FROM resolution_evidence WHERE report_id = ?")->execute([$report_id]);
            $db->prepare("DELETE FROM report_notes WHERE report_id = ?")->execute([$report_id]);
            $db->prepare("DELETE FROM escalations WHERE report_id = ?")->execute([$report_id]);
            $db->prepare("DELETE FROM reports WHERE id = ?")->execute([$report_id]);
            $db->commit();
            if ($report_data['latitude'] && $report_data['longitude']) {
                recalcNearbyReports($db, $report_data['latitude'], $report_data['longitude']);
            }
            $activityLog->log($user_id, 'Delete Report', "Deleted report #$report_id");
            if ($is_ajax) {
                echo json_encode(['success' => true, 'message' => 'Report deleted successfully.']);
                exit();
            }
            $_SESSION['success'] = "Report deleted successfully.";
            $redirect_page = ($user_role === 'admin') ? 'all-reports' : 'my-reports';
            header("Location: " . BASE_URL . "index.php?page=" . $redirect_page);
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'Failed to delete report: ' . $e->getMessage()]);
                exit();
            }
            $_SESSION['error'] = "Failed to delete report.";
            header("Location: " . BASE_URL . "index.php?page=my-reports");
            exit();
        }
    }

    // ============================================
    // STORE NEW REPORT (Citizen only)
    // ============================================
    if ($action === 'store') {
        // KILL SWITCH: report submission disabled
        if (SettingsHelper::get('enable_report_submission', '1') != '1') {
            $_SESSION['error'] = "Report submission is currently disabled by the system administrator.";
            header("Location: " . BASE_URL . "index.php?page=submit-report");
            exit();
        }
        if ($user_role !== 'citizen') {
            $_SESSION['error'] = "Only citizens can submit reports.";
            header("Location: " . BASE_URL . "index.php?page=dashboard");
            exit();
        }

        $errors = [];
        $description = InputSanitizer::sanitizeRichText($_POST['description'] ?? '', 5000);
        if (empty($description) || strlen($description) < 10) {
            $errors[] = "Description must be at least 10 characters.";
        }
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($category_id <= 0) {
            $errors[] = "Please select a valid category.";
        }
        $latitude = filter_var($_POST['latitude'] ?? '', FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($_POST['longitude'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($latitude === false || $longitude === false || $latitude == 0 || $longitude == 0) {
            $errors[] = "Please click on the map to pin the exact location.";
        }
        $impact_modifier = isset($_POST['impact_modifier']) ? (int)$_POST['impact_modifier'] : -1;
        if (!in_array($impact_modifier, [0, 2, 4], true)) {
            $errors[] = "Please select a valid impact level.";
        }
        if (!empty($errors)) {
            $_SESSION['error'] = implode(" ", $errors);
            header("Location: " . BASE_URL . "index.php?page=submit-report");
            exit();
        }

        $location_address = InputSanitizer::sanitizeString($_POST['location_address'] ?? '', 500);
        $cat_check = $db->prepare("SELECT id, name FROM categories WHERE id = ? AND is_active = 1");
        $cat_check->execute([$category_id]);
        $cat_row = $cat_check->fetch(PDO::FETCH_ASSOC);
        if (!$cat_row) {
            $_SESSION['error'] = "Invalid category selected.";
            header("Location: " . BASE_URL . "index.php?page=submit-report");
            exit();
        }
        $category_name = $cat_row['name'];
        $barangay_id = filter_var($_POST['barangay_id'] ?? $_SESSION['barangay_id'] ?? 1, FILTER_VALIDATE_INT);
        if ($barangay_id <= 0) $barangay_id = 1;
        $brgy_check = $db->prepare("SELECT id, name FROM barangays WHERE id = ?");
        $brgy_check->execute([$barangay_id]);
        $brgy_row = $brgy_check->fetch(PDO::FETCH_ASSOC);
        if (!$brgy_row) {
            $barangay_id = 1;
            $brgy_row = ['name' => 'San Isidro'];
        }
        $barangay_name = $brgy_row['name'];

        $title = $category_name . ' at ' . $barangay_name;
        $risk_level = 'low';

        $image_paths = [];
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/uploads/reports/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov', 'm4v', 'avi', '3gp', 'mkv'];
        $allowed_mimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/x-matroska' => 'webm',
            'video/quicktime' => 'mov',
            'video/x-m4v' => 'm4v',
            'video/m4v' => 'm4v',
            'video/x-msvideo' => 'avi',
            'video/3gpp' => '3gp',
            'video/3gpp2' => '3g2'
        ];
        $max_photo_size = 5242880;
        $max_video_size = 26214400;
        $max_files = 3;

        $processFileField = function($files) use ($upload_dir, $allowed_extensions, $allowed_mimes, $max_photo_size, $max_video_size, $max_files) {
            $paths = [];
            $total_files = min(count($files['name']), $max_files);
            for ($i = 0; $i < $total_files; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $file_ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($file_ext, $allowed_extensions)) continue;
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $files['tmp_name'][$i]);
                finfo_close($finfo);
                if (!is_string($mime_type) || $mime_type === '' || $mime_type === false) $mime_type = '';
                $is_video = (strpos($mime_type, 'video/') === 0);
                if (!$is_video && $file_ext === 'webm' && $mime_type === 'video/x-matroska') {
                    $is_video = true;
                }
                if (!$is_video && !array_key_exists($mime_type, $allowed_mimes)) continue;
                if (!$is_video && $allowed_mimes[$mime_type] !== $file_ext) continue;
                $limit = $is_video ? $max_video_size : $max_photo_size;
                if ($files['size'][$i] > $limit) continue;
                $new_filename = bin2hex(random_bytes(16)) . '.' . $file_ext;
                if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $new_filename)) {
                    $paths[] = 'uploads/reports/' . $new_filename;
                }
            }
            return $paths;
        };

        if (isset($_FILES['report_images']) && !empty($_FILES['report_images']['name'][0])) {
            $image_paths = array_merge($image_paths, $processFileField($_FILES['report_images']));
        }
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $image_paths = array_merge($image_paths, $processFileField($_FILES['images']));
        }
        if (isset($_POST['camera_image']) && !empty($_POST['camera_image']) && empty($image_paths)) {
            $camera_image = $_POST['camera_image'];
            if (preg_match('/^data:image\/(\w+);base64,/', $camera_image, $matches)) {
                $image_type = strtolower($matches[1]);
                if (!in_array($image_type, ['jpeg', 'jpg', 'png', 'gif', 'webp'])) {
                    $image_type = 'jpeg';
                }
                $image_data = substr($camera_image, strpos($camera_image, ',') + 1);
                $image_data = base64_decode($image_data);
                if ($image_data !== false && strlen($image_data) <= $max_photo_size) {
                    $temp_file = tempnam(sys_get_temp_dir(), 'cam_');
                    file_put_contents($temp_file, $image_data);
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $actual_mime = finfo_file($finfo, $temp_file);
                    finfo_close($finfo);
                    if (array_key_exists($actual_mime, $allowed_mimes)) {
                        $new_filename = bin2hex(random_bytes(16)) . '.' . $image_type;
                        $target_path = $upload_dir . $new_filename;
                        if (file_put_contents($target_path, $image_data)) {
                            $image_paths[] = 'uploads/reports/' . $new_filename;
                        }
                    }
                    unlink($temp_file);
                }
            }
        }

        if (empty($image_paths)) {
            $_SESSION['error'] = "Please upload at least one photo or video as evidence.";
            header("Location: " . BASE_URL . "index.php?page=submit-report");
            exit();
        }

        $data = [
            'user_id' => $user_id,
            'category_id' => $category_id,
            'barangay_id' => $barangay_id,
            'title' => $title,
            'description' => $description,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location_address' => $location_address,
            'risk_level' => $risk_level,
            'impact_modifier' => $impact_modifier,
            'postal_code' => ''
        ];

        $report_id = $report->create($data);
        if ($report_id) {
            foreach ($image_paths as $index => $path) {
                $is_primary = ($index === 0) ? 1 : 0;
                $stmt = $db->prepare("INSERT INTO report_images (report_id, image_path, is_primary, uploaded_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$report_id, $path, $is_primary]);
            }
            $report->calculateAndUpdateSeverity($report_id);
            $newReport = $report->getReportById($report_id);
            if ($newReport && $newReport['latitude'] && $newReport['longitude']) {
                recalcNearbyReports($db, $newReport['latitude'], $newReport['longitude'], $report_id);
            }
            $activityLog->log($user_id, 'Create Report', "Created report #$report_id");
            $_SESSION['success'] = "Report submitted successfully with " . count($image_paths) . " photo(s)/video(s)!";
            header("Location: " . trackStatusUrl($report_id));
            exit();
        } else {
            $_SESSION['error'] = "Failed to submit report. Please try again.";
            header("Location: " . BASE_URL . "index.php?page=submit-report");
            exit();
        }
    }

    // ============================================
    // UPDATE REPORT STATUS (Barangay or Admin) - generic fallback
    // ============================================
    if ($action === 'update_status') {
        $report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
        $allowed_statuses = [Report::STATUS_PENDING, Report::STATUS_UNDER_REVIEW, Report::STATUS_IN_PROGRESS, Report::STATUS_RESOLVED, Report::STATUS_REJECTED, Report::STATUS_ESCALATED_PENDING];
        $status = isset($_POST['status']) && in_array($_POST['status'], $allowed_statuses, true) ? $_POST['status'] : '';
        if ($report_id && $status) {
            $check_stmt = $db->prepare("SELECT id, latitude, longitude FROM reports WHERE id = ?");
            $check_stmt->execute([$report_id]);
            $report_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
            if ($report_data) {
                $stmt = $db->prepare("UPDATE reports SET status = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $user_id, $report_id]);
                $report->calculateAndUpdateSeverity($report_id);
                if ($report_data['latitude'] && $report_data['longitude']) {
                    recalcNearbyReports($db, $report_data['latitude'], $report_data['longitude'], $report_id);
                }
                $activityLog->log($user_id, 'Update Status', "Updated report #$report_id status to $status");
                $_SESSION['success'] = "Status updated successfully!";
            } else {
                $_SESSION['error'] = "Report not found.";
            }
        } else {
            $_SESSION['error'] = "Invalid report or status.";
        }
        $redirect = ($user_role == 'admin') ? 'all-reports' : 'verify-reports';
        header("Location: " . BASE_URL . "index.php?page=" . $redirect);
        exit();
    }

} // End of POST handling

// ============================================
// FALLBACK - If no action matched
// ============================================
header("Location: " . BASE_URL . "index.php");
exit();
?>