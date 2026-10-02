<?php
// views/barangay/verify_reports.php - COMPLETE VERSION WITH UNDER REVIEW STATUS
// WITH TOOLBAR/POPOVER FILTER, PAGINATION, SORT, AND AJAX UPDATES
// STATS DESIGN UPDATED TO MATCH ADMIN DASHBOARD (with icons)

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/helpers/SecurityHelper.php';
require_once dirname(__DIR__, 2) . '/helpers/SettingsHelper.php';
require_once dirname(__DIR__, 2) . '/helpers/PermissionHelper.php';
requireRole('barangay_official');

$database = new Database();
$db = $database->getConnection();
$barangay_id = $_SESSION['barangay_id'];

// Ensure columns exist
try {
    $db->exec("ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT NULL DEFAULT NULL AFTER `rejected_at`");
    $db->exec("ALTER TABLE `reports` ADD COLUMN IF NOT EXISTS `rejected_at` TIMESTAMP NULL DEFAULT NULL AFTER `rejection_reason`");
} catch (Exception $e) { /* continue */ }

// Handle POST requests (Quick notes only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_quick_note']) && isset($_POST['report_id']) && isset($_POST['note'])) {
        $report_id = (int)$_POST['report_id'];
        $note = trim($_POST['note']);
        if (!empty($note)) {
            $db->prepare("INSERT INTO report_notes (report_id, user_id, note, created_at) VALUES (?, ?, ?, NOW())")
                ->execute([$report_id, $_SESSION['user_id'], $note]);
            $_SESSION['success'] = "Note added to report #$report_id";
        } else {
            $_SESSION['error'] = "Note cannot be empty.";
        }
        header("Location: " . BASE_URL . "index.php?page=verify-reports");
        exit();
    }
}

// ============================================================
// FILTER PARAMETERS
// ============================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$risk_filter = isset($_GET['risk']) ? $_GET['risk'] : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$date_range = isset($_GET['date_range']) ? (int)$_GET['date_range'] : 0;
$residency_filter = in_array($_GET['residency'] ?? '', ['resident', 'non_resident'], true) ? $_GET['residency'] : '';
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$view_mode = isset($_COOKIE['report_view_mode']) && $_COOKIE['report_view_mode'] === 'list' ? 'list' : 'grid';

// ============================================================
// AUTO-OPEN UNDER REVIEW CONFIRMATION (arriving from barangay
// map's "Manage Report" link, which passes the token in ?id=)
// ============================================================
$autoUnderReviewReport = null;
if (isset($_GET['id']) && $_GET['id'] !== '') {
    $autoUid = IdGuard::req($_GET['id']);
    if ($autoUid > 0) {
        $autoStmt = $db->prepare("SELECT id, title, status FROM reports WHERE id = ? AND barangay_id = ?");
        $autoStmt->execute([$autoUid, $barangay_id]);
        $autoRow = $autoStmt->fetch(PDO::FETCH_ASSOC);
        if ($autoRow && $autoRow['status'] === 'pending') {
            $autoUnderReviewReport = $autoRow;
        }
    }
}

// Get categories for dropdown
$categories = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$category_name_map = [];
foreach ($categories as $cat) {
    $category_name_map[$cat['id']] = $cat['name'];
}

// ============================================================
// BUILD WHERE CLAUSE
// ============================================================
$where = "r.barangay_id = " . (int)$barangay_id;
$params = [];

if ($status_filter != '') {
    if ($status_filter == 'escalated') {
        $where .= " AND r.status IN ('escalated_pending', 'escalated')";
    } else {
        $where .= " AND r.status = :status";
        $params[':status'] = $status_filter;
    }
}
if ($risk_filter != '') {
    $where .= " AND r.risk_level = :risk";
    $params[':risk'] = $risk_filter;
}
if ($category_filter > 0) {
    $where .= " AND r.category_id = :category";
    $params[':category'] = $category_filter;
}
if ($date_range > 0) {
    $where .= " AND r.created_at >= DATE_SUB(NOW(), INTERVAL :date_range DAY)";
    $params[':date_range'] = $date_range;
}
if ($residency_filter === 'resident') {
    $where .= " AND u.is_resident = 1";
} elseif ($residency_filter === 'non_resident') {
    $where .= " AND u.is_resident = 0";
}
if ($search_keyword != '') {
    $search = "%$search_keyword%";
    $where .= " AND (r.title LIKE :search OR r.description LIKE :search OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search)";
    $params[':search'] = $search;
}

// ============================================================
// CSV EXPORT HANDLER (barangay-scoped, honors current filters)
// ============================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $status_labels_export = [
        'pending' => 'Pending', 'under_review' => 'Under Review', 'verified' => 'Verified',
        'in_progress' => 'In Progress', 'escalated_pending' => 'Escalated (Pending)',
        'escalated' => 'Escalated', 'resolved' => 'Resolved', 'rejected' => 'Rejected',
        'cancelled' => 'Cancelled'
    ];
    $risk_labels_export = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];

    $export_sql = "SELECT r.id, r.title, r.description, r.risk_level, r.severity_score,
                          r.status, r.created_at, r.resolved_at,
                          c.name AS category_name,
                          CONCAT(u.first_name, ' ', u.last_name) AS reporter_name
                   FROM reports r
                   JOIN categories c ON r.category_id = c.id
                   JOIN users u ON r.user_id = u.id
                   WHERE $where
                   ORDER BY r.created_at DESC";
    $export_stmt = $db->prepare($export_sql);
    foreach ($params as $k => $v) $export_stmt->bindValue($k, $v);
    $export_stmt->execute();
    $export_rows = $export_stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="barangay_reports_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Report ID', 'Title', 'Description', 'Category', 'Reporter', 'Risk Level', 'Severity Score', 'Status', 'Date Submitted', 'Resolved At']);
    foreach ($export_rows as $row) {
        fputcsv($out, [
            '#' . str_pad($row['id'], 5, '0', STR_PAD_LEFT),
            $row['title'],
            $row['description'] ?? '',
            $row['category_name'],
            $row['reporter_name'],
            $risk_labels_export[$row['risk_level']] ?? $row['risk_level'],
            $row['severity_score'] ?? 0,
            $status_labels_export[$row['status']] ?? $row['status'],
            $row['created_at'],
            $row['resolved_at'] ?? ''
        ]);
    }
    fclose($out);
    exit();
}

// ============================================================
// PAGINATION
// ============================================================
$limit = 10;
$offset = ($page - 1) * $limit;

// Total count
$count_sql = "SELECT COUNT(*) FROM reports r JOIN users u ON r.user_id = u.id WHERE $where";
$count_stmt = $db->prepare($count_sql);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_reports = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_reports / $limit));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

// Fetch reports
$sql = "SELECT r.*, c.name as category_name, CONCAT(u.first_name, ' ', u.last_name) as user_name,
               (SELECT ri.image_path FROM report_images ri WHERE ri.report_id = r.id AND LOWER(ri.image_path) REGEXP '\\.(jpg|jpeg|png|gif|webp)$' ORDER BY ri.is_primary DESC, ri.id ASC LIMIT 1) as cover_image
        FROM reports r
        JOIN categories c ON r.category_id = c.id
        JOIN users u ON r.user_id = u.id
        WHERE $where
        ORDER BY 
            CASE WHEN r.status = 'escalated_pending' THEN 0 
                 WHEN r.status = 'pending' THEN 1 
                 WHEN r.status = 'under_review' THEN 2
                 WHEN r.status = 'in_progress' THEN 3 
                 ELSE 4 END,
            r.created_at " . ($sort_order === 'oldest' ? 'ASC' : 'DESC') . "
        LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// STATISTICS
// ============================================================
$total = $db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id")->fetchColumn();
$pending = $db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id AND status = 'pending'")->fetchColumn();
$under_review = $db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id AND status = 'under_review'")->fetchColumn();
$progress = $db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id AND status = 'in_progress'")->fetchColumn();
$escalated = $db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id AND status IN ('escalated_pending', 'escalated')")->fetchColumn();
$resolved = $db->query("SELECT COUNT(*) FROM reports WHERE barangay_id = $barangay_id AND status = 'resolved'")->fetchColumn();
// (Removed rejected & cancelled to keep 6 cards matching all_reports.php)

// Risk summary for this barangay
$risk_summary = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
$risk_stmt = $db->prepare("SELECT risk_level, COUNT(*) as cnt FROM reports WHERE barangay_id = ? GROUP BY risk_level");
$risk_stmt->execute([$barangay_id]);
while ($row = $risk_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($risk_summary[$row['risk_level']])) {
        $risk_summary[$row['risk_level']] = $row['cnt'];
    }
}

// Status summary for the status filter chips (barangay scope)
$status_summary = [];
$status_summary_stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM reports WHERE barangay_id = ? GROUP BY status");
$status_summary_stmt->execute([$barangay_id]);
while ($srow = $status_summary_stmt->fetch(PDO::FETCH_ASSOC)) {
    $status_summary[$srow['status']] = $srow['cnt'];
}

// Status chip order/labels for the notification-style filter chips
$status_chip_keys = ['', 'pending', 'under_review', 'in_progress', 'escalated_pending', 'escalated', 'resolved', 'rejected', 'cancelled'];
$status_chip_labels = [
    '' => 'All',
    'pending' => 'Pending',
    'under_review' => 'Under Review',
    'in_progress' => 'In Progress',
    'escalated_pending' => 'Escalation Pending',
    'escalated' => 'Escalated',
    'resolved' => 'Resolved',
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled',
];

// Active filters count
$active_filters = 0;
// Status is NOT counted: the status chip bar below already shows the active selection.
if ($risk_filter != '') $active_filters++;
if ($category_filter > 0) $active_filters++;
if ($date_range > 0) $active_filters++;
if ($residency_filter != '') $active_filters++;
if (!empty($search_keyword)) $active_filters++;

// Helper labels
$status_labels = [
    'pending' => 'Pending', 
    'under_review' => 'Under Review',
    'in_progress' => 'In Progress', 
    'escalated' => 'Escalated',
    'escalated_pending' => 'Escalated Pending',
    'resolved' => 'Resolved', 
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled'
];
$risk_labels = ['low' => 'Low Risk', 'medium' => 'Medium Risk', 'high' => 'High Risk', 'critical' => 'Critical Risk'];
$date_range_labels = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 3 months'];
$residency_labels = ['resident' => 'Reported by Resident', 'non_resident' => 'Reported by Non-Resident'];
$active_category_name = ($category_filter > 0 && isset($category_name_map[$category_filter])) ? $category_name_map[$category_filter] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Manage Reports - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/material-symbols.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/material-symbols.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/export-print.css'); ?>">
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
        
        /* ===== CONTAINER ===== */
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
        
        /* ===== STAT CARDS (updated to match all_reports.php) ===== */
        /* No custom classes needed – we use Tailwind utilities directly in the HTML */
        
        /* ===== STATUS BADGES ===== */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 600;
            white-space: nowrap;
            letter-spacing: 0.01em;
            line-height: 1.4;
        }
        @media (min-width: 480px) {
            .status-badge {
                padding: 4px 12px;
                font-size: 0.65rem;
                gap: 5px;
            }
        }
        @media (min-width: 640px) {
            .status-badge {
                padding: 4px 14px;
                font-size: 0.7rem;
                gap: 6px;
            }
        }
        .status-badge i {
            font-size: 0.5rem;
        }
        @media (min-width: 640px) {
            .status-badge i {
                font-size: 0.6rem;
            }
        }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-under_review { background: #DBEAFE; color: #1E40AF; }
        .status-verified { background: #DBEAFE; color: #1E40AF; }
        .status-in_progress { background: #FCE7F3; color: #9D174D; }
        .status-escalated_pending { background: #FDE68A; color: #92400E; border: 1px solid #F59E0B; }
        .status-escalated { background: #FED7AA; color: #9A3412; }
        .status-resolved { background: #D1FAE5; color: #065F46; }
        .status-rejected { background: #FEE2E2; color: #991B1B; }
        .status-cancelled { background: #F3F4F6; color: #4B5563; }
        
        /* Risk Badges */
        .risk-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        @media (min-width: 480px) {
            .risk-badge {
                padding: 3px 10px;
                font-size: 0.65rem;
            }
        }
        @media (min-width: 640px) {
            .risk-badge {
                padding: 3px 12px;
                font-size: 0.7rem;
            }
        }
        .risk-low { background: #D1FAE5; color: #065F46; }
        .risk-medium { background: #FEF3C7; color: #92400E; }
        .risk-high { background: #FFEDD5; color: #9A3412; }
        .risk-critical { background: #FEE2E2; color: #991B1B; }
        
        /* Severity Badges */
        .severity-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        @media (min-width: 480px) {
            .severity-badge {
                padding: 3px 10px;
                font-size: 0.65rem;
            }
        }
        @media (min-width: 640px) {
            .severity-badge {
                padding: 3px 12px;
                font-size: 0.7rem;
            }
        }
        .severity-Green { background: #D1FAE5; color: #065F46; }
        .severity-Yellow { background: #FEF3C7; color: #92400E; }
        .severity-Orange { background: #FED7AA; color: #9A3412; }
        .severity-Red { background: #FEE2E2; color: #991B1B; }

        /* ===== STATUS FILTER CHIPS (notification-style pills) ===== */
        .status-chip-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            background: var(--lt-white);
            border: 1px solid var(--lt-border);
            border-radius: 12px;
            margin: 0 0 1.5rem;
        }
        .status-chip-label {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--lt-gray-500);
            margin-right: 2px;
        }
        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 14px;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 600;
            line-height: 1;
            border: 1px solid #E5E7EB;
            background: #F3F4F6;
            color: #6B7280;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .status-chip:hover {
            border-color: var(--lt-forest);
            color: var(--lt-forest);
            background: var(--lt-forest-light);
        }
        .status-chip.active {
            background: var(--lt-forest);
            border-color: var(--lt-forest);
            color: #FFFFFF;
            box-shadow: 0 2px 8px rgba(16, 163, 127, 0.25);
        }
        .status-chip-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.25);
            font-size: 0.58rem;
            font-weight: 700;
        }
        .status-chip:not(.active) .status-chip-count {
            background: #E5E7EB;
            color: #6B7280;
        }
        
        /* ===== TOOLBAR ===== */
        :root {
            --lt-forest: #10A37F;
            --lt-forest-light: #E8F5F0;
            --lt-forest-mid: #0D8568;
            --lt-border: #D1D5DB;
            --lt-border-light: #E5E7EB;
            --lt-white: #FFFFFF;
            --lt-gray-50: #F9FAFB;
            --lt-gray-500: #6B7280;
            --lt-gray-700: #374151;
            --lt-gray-800: #1F2937;
        }

        .reports-toolbar {
            background: var(--lt-white);
            border: 1px solid var(--lt-border);
            border-radius: 12px;
            padding: 10px 16px;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
        }

        .toolbar-search {
            position: relative;
            flex: 1 1 220px;
            min-width: 180px;
        }
        .toolbar-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.8rem;
            pointer-events: none;
        }
        .toolbar-search input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border: 1.5px solid var(--lt-border-light);
            border-radius: 8px;
            font-size: 0.85rem;
            color: var(--lt-gray-800);
            background: var(--lt-gray-50);
            transition: all 0.2s ease;
            outline: none;
        }
        .toolbar-search input:focus {
            border-color: var(--lt-forest);
            background: var(--lt-white);
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.10);
        }
        .toolbar-search input::placeholder {
            color: #9CA3AF;
        }

        .toolbar-select {
            appearance: none;
            padding: 8px 32px 8px 12px;
            border: 1.5px solid var(--lt-border-light);
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--lt-gray-700);
            background: var(--lt-gray-50);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%236B7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
            white-space: nowrap;
        }
        .toolbar-select:focus {
            border-color: var(--lt-forest);
            background-color: var(--lt-white);
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.10);
        }
        .toolbar-select:hover {
            border-color: var(--lt-forest);
        }

        .toolbar-divider {
            width: 1px;
            height: 28px;
            background: var(--lt-border-light);
            flex-shrink: 0;
        }

        /* Filter By Button */
        .toolbar-filter-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border: 1.5px solid var(--lt-border-light);
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--lt-gray-700);
            background: var(--lt-gray-50);
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            white-space: nowrap;
        }
        .toolbar-filter-btn:hover {
            border-color: var(--lt-forest);
            color: var(--lt-forest);
            background: var(--lt-forest-light);
        }
        .toolbar-filter-btn.active {
            border-color: var(--lt-forest);
            color: var(--lt-forest);
            background: var(--lt-forest-light);
        }
        .toolbar-filter-btn .filter-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 8px;
            background: var(--lt-forest);
            color: var(--lt-white);
            font-size: 0.65rem;
            font-weight: 700;
            line-height: 1;
        }

        /* Filter Popover */
        .filter-popover-wrapper {
            position: relative;
        }
        .filter-popover {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            z-index: 50;
            background: var(--lt-white);
            border: 1px solid var(--lt-border);
            border-radius: 12px;
            box-shadow: 0 12px 36px -8px rgba(0, 0, 0, 0.12), 0 4px 12px -4px rgba(0, 0, 0, 0.06);
            padding: 16px;
            min-width: 320px;
            display: none;
            animation: popoverIn 0.2s ease;
        }
        .filter-popover.open {
            display: block;
        }
        @keyframes popoverIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .popover-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--lt-gray-500);
            margin-bottom: 12px;
        }
        .popover-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .popover-grid.full-width {
            grid-template-columns: 1fr;
        }
        .popover-field label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--lt-gray-500);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .popover-field select {
            width: 100%;
            padding: 7px 30px 7px 10px;
            border: 1.5px solid var(--lt-border-light);
            border-radius: 8px;
            font-size: 0.82rem;
            color: var(--lt-gray-700);
            background: var(--lt-gray-50);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%236B7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            cursor: pointer;
            outline: none;
            transition: all 0.2s ease;
        }
        .popover-field select:focus {
            border-color: var(--lt-forest);
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.10);
        }
        .popover-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid var(--lt-border-light);
        }
        .popover-btn-apply {
            padding: 7px 18px;
            background: var(--lt-forest);
            color: var(--lt-white);
            border: none;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .popover-btn-apply:hover {
            background: var(--lt-forest-mid);
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.2);
        }
        .popover-btn-reset {
            padding: 7px 14px;
            background: var(--lt-white);
            color: var(--lt-gray-500);
            border: 1.5px solid var(--lt-border-light);
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .popover-btn-reset:hover {
            border-color: #EF4444;
            color: #EF4444;
            background: #FEF2F2;
        }

        /* Results & Sort */
        .toolbar-results {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-shrink: 0;
            white-space: nowrap;
        }
        .toolbar-results-text {
            font-size: 0.8rem;
            color: var(--lt-gray-500);
            font-weight: 500;
        }
        .toolbar-results-text strong {
            color: var(--lt-gray-800);
            font-weight: 700;
        }

        /* Active Filter Chips */
        .active-filters-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--lt-white);
            border: 1px solid var(--lt-border);
            border-top: none;
            border-radius: 0 0 12px 12px;
            margin-top: -1px;
            margin-bottom: 1.5rem;
        }
        .active-filters-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--lt-gray-500);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-right: 2px;
        }
        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px 4px 12px;
            background: var(--lt-forest-light);
            color: var(--lt-forest);
            border-radius: 16px;
            font-size: 0.72rem;
            font-weight: 600;
            transition: all 0.15s ease;
        }
        .filter-chip:hover {
            background: #D4E4D2;
        }
        .filter-chip .chip-remove {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: rgba(16, 163, 127, 0.15);
            color: var(--lt-forest);
            font-size: 0.55rem;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            line-height: 1;
        }
        .filter-chip .chip-remove:hover {
            background: #c53030;
            color: white;
        }
        .chips-clear-all {
            font-size: 0.72rem;
            color: var(--lt-gray-500);
            text-decoration: none;
            font-weight: 500;
            margin-left: 4px;
            transition: color 0.15s ease;
        }
        .chips-clear-all:hover {
            color: #c53030;
        }

        /* Report Cards */
        .report-card {
            background: white;
            border: 1px solid rgba(16, 163, 127, 0.08);
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .report-card:hover {
            transform: translateY(-2px);
            border-color: #10A37F;
            box-shadow: 0 8px 20px -8px rgba(16, 163, 127, 0.12);
        }
        .report-card .report-title {
            font-weight: 600;
            color: #1a2e1a;
            font-size: 0.95rem;
        }
        @media (min-width: 640px) {
            .report-card .report-title {
                font-size: 1rem;
            }
        }
        .report-card .report-description {
            color: #4b5a4a;
            font-size: 0.8rem;
            line-height: 1.4;
        }
        .report-card .meta-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.6rem;
            color: #64748b;
        }
        @media (min-width: 640px) {
            .report-card .meta-item {
                font-size: 0.7rem;
                gap: 0.5rem;
            }
        }
        .report-card .meta-icon {
            width: 1.4rem;
            height: 1.4rem;
            background: #F5FBF6;
            border-radius: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        @media (min-width: 640px) {
            .report-card .meta-icon {
                width: 1.75rem;
                height: 1.75rem;
                border-radius: 0.5rem;
            }
        }

        .btn-manage {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        @media (min-width: 640px) {
            .btn-manage {
                padding: 0.5rem 1.25rem;
                font-size: 0.875rem;
            }
        }
        .btn-manage:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
        }

        /* ===== REPORT CARDS (grid design from my_reports.php) ===== */
        .report-card-grid {
            background: white;
            border-radius: 1rem;
            border: 1px solid #eef2f0;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .report-card-grid:hover {
            transform: translateY(-2px);
            border-color: #10A37F;
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.1);
        }
        .report-card-grid .report-card-header {
            background: linear-gradient(90deg, #10A37F 0%, #0D8568 100%);
            padding: 1rem 1rem 0.75rem;
            color: white;
        }
        .report-card-grid .report-card-header.has-cover {
            min-height: 9rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .report-card-grid .report-card-header .header-label {
            font-size: 0.7rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            opacity: 0.9;
        }
        .report-card-grid .report-card-header .header-title {
            font-size: 1rem;
            line-height: 1.25;
            font-weight: 700;
            margin-top: 0.25rem;
            max-width: 22rem;
        }
        .report-card-grid .report-card-header .header-meta {
            font-size: 0.75rem;
            opacity: 0.8;
        }
        .report-card-grid .header-badges {
            gap: 0.65rem;
            margin-top: 1rem;
            display: flex;
            flex-wrap: wrap;
        }
        .report-card-grid .header-badge {
            background: rgba(255,255,255,0.16);
            color: white;
            border: 1px solid rgba(255,255,255,0.18);
        }
        .report-card-grid .status-badge.header-badge,
        .report-card-grid .risk-badge.header-badge,
        .report-card-grid .severity-badge.header-badge {
            background: rgba(255,255,255,0.18);
            color: white;
            border: 1px solid rgba(255,255,255,0.22);
        }
        .report-card-grid .header-badge i {
            color: white;
        }
        .report-card-grid .meta-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.6rem;
            color: #64748b;
        }
        @media (min-width: 640px) {
            .report-card-grid .meta-item {
                gap: 0.5rem;
                font-size: 0.7rem;
            }
        }
        .report-card-grid .meta-icon {
            width: 1.4rem;
            height: 1.4rem;
            background: #F5FBF6;
            border-radius: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        @media (min-width: 640px) {
            .report-card-grid .meta-icon {
                width: 1.75rem;
                height: 1.75rem;
                border-radius: 0.5rem;
            }
        }
        .report-card-grid .btn-manage {
            padding: 0.35rem 0.8rem;
            font-size: 0.7rem;
        }
        @media (min-width: 640px) {
            .report-card-grid .btn-manage {
                padding: 0.45rem 1rem;
                font-size: 0.75rem;
            }
        }

        /* Grid Layout */
        .reports-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 640px) {
            .reports-grid { gap: 1.25rem; }
        }
        @media (min-width: 768px) {
            .reports-grid.grid-view { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .reports-grid.grid-view { grid-template-columns: repeat(3, 1fr); }
        }

        /* View Toggle */
        .view-toggle {
            background: #f1f5f9;
            border-radius: 1.5rem;
            padding: 0.2rem;
            display: inline-flex;
            gap: 0.2rem;
        }
        .view-btn {
            padding: 0.25rem 0.7rem;
            border-radius: 1.5rem;
            font-size: 0.7rem;
            font-weight: 500;
            cursor: pointer;
            background: transparent;
            color: #64748b;
            transition: all 0.2s;
        }
        @media (min-width: 640px) {
            .view-btn { padding: 0.375rem 1rem; font-size: 0.875rem; }
        }
        .view-btn.active {
            background: white;
            color: #10A37F;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .view-btn:hover:not(.active) { color: #10A37F; }

        /* Pagination */
        .pagination {
            display: flex;
            gap: 0.3rem;
            justify-content: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        @media (min-width: 640px) {
            .pagination {
                gap: 0.5rem;
                margin-top: 2rem;
            }
        }
        .page-btn {
            min-width: 2rem;
            height: 2rem;
            font-size: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background: white;
            color: #1f2937;
            cursor: pointer;
            transition: all 0.2s;
        }
        @media (min-width: 640px) {
            .page-btn {
                min-width: 2.25rem;
                height: 2.25rem;
                font-size: 0.875rem;
            }
        }
        .page-btn:hover {
            background: #f0fdf4;
            border-color: #10A37F;
        }
        .page-btn.active {
            background: #10A37F;
            color: white;
            border-color: #10A37F;
        }
        .page-btn.disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(245, 251, 246, 0.75);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .loading-spinner {
            width: 52px;
            height: 52px;
            position: relative;
            background: white;
            border-radius: 50%;
            box-shadow: 0 8px 32px rgba(16,163,127,0.18);
        }
        .loading-spinner::before,
        .loading-spinner::after {
            content: '';
            position: absolute;
            inset: 4px;
            border-radius: 50%;
            border: 3px solid transparent;
        }
        .loading-spinner::before {
            border-top-color: #10A37F;
            border-right-color: #10A37F;
            animation: spin-cw 0.9s cubic-bezier(0.4,0,0.2,1) infinite;
        }
        .loading-spinner::after {
            border-bottom-color: #34d399;
            border-left-color: #34d399;
            animation: spin-ccw 1.2s cubic-bezier(0.4,0,0.2,1) infinite;
        }
        @keyframes spin-cw  { to { transform: rotate(360deg);  } }
        @keyframes spin-ccw { to { transform: rotate(-360deg); } }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            background: white;
            border-radius: 1rem;
            border: 1px solid #eef2f0;
        }
        @media (min-width: 640px) {
            .empty-state {
                padding: 3rem 2rem;
            }
        }

        /* Risk Summary */
        .risk-summary-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }
        @media (min-width: 640px) {
            .risk-summary-container {
                gap: 0.75rem;
                margin-bottom: 1.5rem;
            }
        }

        /* Header */
        .page-header {
            margin-bottom: 1.25rem;
        }
        @media (min-width: 640px) {
            .page-header {
                margin-bottom: 1.5rem;
            }
        }
        .page-title {
            font-size: 1.5rem;
        }
        @media (min-width: 640px) {
            .page-title {
                font-size: 1.875rem;
            }
        }

        /* Quick Note Form */
        .quick-note-form input {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.4rem 0.75rem;
            font-size: 0.75rem;
            flex: 1;
            min-width: 100px;
        }
        .quick-note-form input:focus {
            border-color: #10A37F;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08);
        }
        .quick-note-form button {
            background: #10A37F;
            color: white;
            border: none;
            border-radius: 0.75rem;
            padding: 0.4rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .quick-note-form button:hover {
            background: #0D8568;
        }

        @media (max-width: 768px) {
            .reports-toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
                padding: 12px;
            }
            .toolbar-search { min-width: 100%; }
            .toolbar-divider { display: none; }
            .toolbar-results {
                margin-left: 0;
                flex-wrap: wrap;
                justify-content: space-between;
            }
            .toolbar-select { width: 100%; }
            .filter-popover {
                left: -16px;
                right: -16px;
                min-width: auto;
            }
            .active-filters-row {
                padding: 8px 12px;
            }
            .status-chip-bar {
                padding: 8px 12px;
                flex-wrap: nowrap;
                justify-content: flex-start;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                scroll-snap-type: x proximity;
            }
            .status-chip-bar::-webkit-scrollbar { display: none; }
            .status-chip-label { flex-shrink: 0; }
            .status-chip { flex: 0 0 auto; scroll-snap-align: start; }
            /* page-header tighter on mobile */
            .page-header { padding: 0.75rem 0 0.5rem; }
            .page-title { font-size: 1.25rem !important; }
            /* hide barangay location badge on mobile (saves space) */
            .location-badge { display: none; }
            /* notification dropdown full-width on mobile */
            .notification-dropdown { left: 8px; right: 8px; width: auto; }
            /* risk summary wraps cleanly */
            .risk-summary-container { flex-wrap: wrap; gap: 4px; }
        }
    </style>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
</head>
<body class="bg-[#F5FBF6]">

<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<div class="lg:ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">
        
        <div id="loadingOverlay" class="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
        
        <!-- Header -->
        <div class="page-header">
            
            <div class="flex flex-col sm:flex-row justify-end items-start sm:items-center gap-3">
                
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="export-dropdown">
                        <button onclick="toggleExportMenu()" class="btn-export-trigger">
                            <i class="fas fa-file-export"></i>
                            <span>Export</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div id="exportMenu" class="export-dropdown-menu">
                            <button class="export-dropdown-item" onclick="exportReportsPdf()">
                                <i class="fas fa-file-pdf"></i>
                                <span>Export as PDF</span>
                            </button>
                            <button class="export-dropdown-item" onclick="exportCSV()">
                                <i class="fas fa-file-csv"></i>
                                <span>Export as CSV</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- ===== STATISTICS CARDS (matches all_reports.php design) ===== -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 md:gap-4 mb-6 stat-cards">
            <?php
            // Define stats array (stat-card style like admin all_reports)
            $stats_metrics = [
                ['label' => 'Total',          'value' => $total,        'color' => 'text-emerald-600', 'chip' => 'bg-emerald-100',   'icon' => 'fa-flag'],
                ['label' => 'Pending',        'value' => $pending,      'color' => 'text-yellow-600',  'chip' => 'bg-yellow-100',    'icon' => 'fa-clock'],
                ['label' => 'Under Review',   'value' => $under_review, 'color' => 'text-blue-600',    'chip' => 'bg-blue-100',      'icon' => 'fa-search'],
                ['label' => 'In Progress',    'value' => $progress,     'color' => 'text-pink-600',    'chip' => 'bg-pink-100',      'icon' => 'fa-spinner'],
                ['label' => 'Escalated',      'value' => $escalated,    'color' => 'text-orange-600',  'chip' => 'bg-orange-100',    'icon' => 'fa-exclamation-triangle'],
                ['label' => 'Resolved',       'value' => $resolved,     'color' => 'text-emerald-600', 'chip' => 'bg-green-100',     'icon' => 'fa-check-circle'],
            ];
            foreach($stats_metrics as $m): ?>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-start justify-between gap-3 hover:shadow-md hover:border-[#10A37F] transition-all duration-200">
                <div>
                    <p class="text-[10px] md:text-xs text-gray-400 uppercase tracking-wider mb-1.5 font-semibold"><?php echo $m['label']; ?></p>
                    <p class="text-xl md:text-2xl font-extrabold <?php echo $m['color']; ?> tracking-tight"><?php echo $m['value']; ?></p>
                </div>
                <div class="w-10 h-10 <?php echo $m['chip']; ?> rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas <?php echo $m['icon']; ?> <?php echo $m['color']; ?>"></i>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
       
        
        <!-- ===== FILTER TOOLBAR (shared partial) ===== -->
        <?php
        $ft_popover_count = 0;
        if ($risk_filter != '') $ft_popover_count++;
        if ($category_filter > 0) $ft_popover_count++;
        if ($date_range > 0) $ft_popover_count++;
        if ($residency_filter != '') $ft_popover_count++;

        $ft_chips = [];
        if (!empty($search_keyword)) $ft_chips[] = '<span class="filter-chip">"' . htmlspecialchars($search_keyword) . '" <span class="chip-remove" data-filter="search"><i class="fas fa-times"></i></span></span>';
        // Status is NOT repeated here: the status chip bar below already shows the active selection.
        if ($risk_filter != '') $ft_chips[] = '<span class="filter-chip">' . htmlspecialchars($risk_labels[$risk_filter] ?? ucfirst($risk_filter)) . ' <span class="chip-remove" data-filter="risk"><i class="fas fa-times"></i></span></span>';
        if ($category_filter > 0) $ft_chips[] = '<span class="filter-chip">' . htmlspecialchars($active_category_name) . ' <span class="chip-remove" data-filter="category"><i class="fas fa-times"></i></span></span>';
        if ($date_range > 0) $ft_chips[] = '<span class="filter-chip">' . htmlspecialchars($date_range_labels[$date_range] ?? $date_range . ' days') . ' <span class="chip-remove" data-filter="date"><i class="fas fa-times"></i></span></span>';
        if ($residency_filter != '') $ft_chips[] = '<span class="filter-chip">' . htmlspecialchars($residency_labels[$residency_filter] ?? $residency_filter) . ' <span class="chip-remove" data-filter="residency"><i class="fas fa-times"></i></span></span>';

        $ft_cat_options = ['0' => 'All Categories'];
        foreach ($categories as $cat) { $ft_cat_options[(string)$cat['id']] = $cat['name']; }

        $ft = [
            'search_id'          => 'searchInput',
            'search_value'       => $search_keyword,
            'search_placeholder' => 'Search reports...',
            'results_text'       => 'Showing <strong id="resultsCountDisplay">' . count($reports) . '</strong> of <strong>' . $total_reports . '</strong> reports',
            'inline_selects'     => [],
            'filter_by'          => [
                'active' => ($risk_filter != '' || $category_filter > 0 || $date_range > 0 || $residency_filter != ''),
                'count'  => $ft_popover_count,
            ],
            'popover_fields'     => [
                ['kind' => 'select', 'id' => 'popoverRisk', 'label' => 'Risk Level', 'value' => $risk_filter, 'default' => '',
                 'options' => ['' => 'All Levels', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical']],
                ['kind' => 'select', 'id' => 'popoverCategory', 'label' => 'Category', 'value' => $category_filter, 'default' => '0', 'options' => $ft_cat_options],
                ['kind' => 'select', 'id' => 'popoverDateRange', 'label' => 'Date Range', 'value' => $date_range, 'default' => '0',
                 'options' => ['0' => 'All Time', '7' => 'Last 7 Days', '30' => 'Last 30 Days', '90' => 'Last 90 Days']],
                ['kind' => 'select', 'id' => 'popoverResidency', 'label' => 'Reported By', 'value' => $residency_filter, 'default' => '',
                 'options' => ['' => 'All Reporters', 'resident' => 'Resident', 'non_resident' => 'Non-Resident']],
            ],
            'view_toggle'        => [
                'active' => $view_mode,
                'grid'   => "setViewMode('grid')",
                'list'   => "setViewMode('list')",
            ],
            'trailing_select'    => null,
            'sort_select'        => [
                'id'        => 'toolbarSort',
                'value'     => $sort_order,
                'default'   => 'newest',
                'label'     => 'Sort By',
                'options'   => ['newest' => 'Recent to Older', 'oldest' => 'Older to Recent'],
            ],
            'active_filters'     => (int)$active_filters,
            'chips'              => $ft_chips,
            'chips_clear_all'    => true,
            'chip_clear_map'     => [
                'search'   => ['el' => 'searchInput', 'clear' => ''],
                'status'   => ['el' => 'toolbarStatus', 'clear' => ''],
                'risk'     => ['el' => 'popoverRisk', 'clear' => ''],
                'category' => ['el' => 'popoverCategory', 'clear' => '0'],
                'date'     => ['el' => 'popoverDateRange', 'clear' => '0'],
                'residency'=> ['el' => 'popoverResidency', 'clear' => ''],
            ],
            'callback'           => 'applyFilters',
            'compact_breakpoint' => 1199,
            'more_icon'          => 'fa-sliders-h',
        ];
        ?>
        <div id="dashHeaderExtras" class="dash-header-extras">
            <div class="dashboard-toolbar-row dash-topbar">
                <div class="dashboard-toolbar-filters">
                    <?php include __DIR__ . '/../shared/report_filter_toolbar.php'; ?>
                </div>
            </div>
        </div>

        <!-- Status filter chips (notification-style) -->
        <?php $status_all_count = array_sum($status_summary); ?>
        <div class="status-chip-bar" id="statusChipBar">
            <span class="status-chip-label">Status</span>
            <input type="hidden" id="toolbarStatus" value="<?php echo htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8'); ?>">
            <?php foreach ($status_chip_keys as $sc_key):
                $sc_active = ($sc_key === $status_filter);
                if ($sc_key === 'escalated') {
                    $sc_count = (int)($status_summary['escalated'] ?? 0) + (int)($status_summary['escalated_pending'] ?? 0);
                } else {
                    $sc_count = ($sc_key === '') ? $status_all_count : (int)($status_summary[$sc_key] ?? 0);
                }
                if (!$sc_active && $sc_count <= 0) continue;
            ?>
            <button type="button" class="status-chip<?php echo $sc_active ? ' active' : ''; ?>" data-status="<?php echo htmlspecialchars($sc_key, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($status_chip_labels[$sc_key]); ?>
                <span class="status-chip-count"><?php echo (int)$sc_count; ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Reports Grid -->
        <div id="reportsGrid" class="reports-grid <?php echo $view_mode; ?>-view">
            <?php if(count($reports) > 0): ?>
                <?php foreach($reports as $r): 
                    $isEscalatedPending = ($r['status'] == 'escalated_pending');
                    $status_class = 'status-' . $r['status'];
                    $status_icon = '';
                    if ($r['status'] == 'pending') $status_icon = 'fa-clock';
                    elseif ($r['status'] == 'under_review') $status_icon = 'fa-search';
                    elseif ($r['status'] == 'in_progress') $status_icon = 'fa-spinner';
                    elseif ($r['status'] == 'escalated_pending') $status_icon = 'fa-hourglass-half';
                    elseif ($r['status'] == 'escalated') $status_icon = 'fa-shield-alt';
                    elseif ($r['status'] == 'resolved') $status_icon = 'fa-check-circle';
                    elseif ($r['status'] == 'rejected') $status_icon = 'fa-times-circle';
                    elseif ($r['status'] == 'cancelled') $status_icon = 'fa-ban';
                    $status_label = ucfirst(str_replace('_', ' ', $r['status']));
                    $needs_attention = $isEscalatedPending || in_array($r['status'], ['pending', 'under_review']);
                ?>
                <div class="report-card-grid <?php echo $isEscalatedPending ? 'border-2 border-orange-300' : ''; ?>" data-report-id="<?php echo $r['id']; ?>">
                    <?php
                    $cover_src = !empty($r['cover_image']) ? BASE_URL . htmlspecialchars($r['cover_image'], ENT_QUOTES, 'UTF-8') : '';
                    $cover_inline = $cover_src ? " style=\"background-image:linear-gradient(to bottom, rgba(15,23,42,0.28) 0%, rgba(15,23,42,0.88) 100%), url('" . $cover_src . "'); background-size:cover; background-position:center;\"" : '';
                    ?>
                    <div class="report-card-header rounded-t-2xl<?php echo $cover_src ? ' has-cover' : ''; ?>"<?php echo $cover_inline; ?>>
                        <div class="flex flex-col sm:flex-row justify-between items-start gap-3 mb-3">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-5 h-5 md:w-6 md:h-6 bg-white/20 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-file-alt text-white/80 text-[10px] md:text-xs"></i>
                                    </div>
                                    <span class="header-label">Report Summary</span>
                                </div>
                                <h3 class="header-title"><?php echo htmlspecialchars($r['title']); ?></h3>
                            </div>
                            <div class="text-right">
                                <div class="header-meta">#<?php echo str_pad($r['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                <div class="header-meta mt-2"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></div>
                            </div>
                        </div>
                        <div class="header-badges">
                            <span class="status-badge header-badge <?php echo $status_class; ?>">
                                <i class="fas <?php echo $status_icon; ?> text-[10px] sm:text-xs"></i>
                                <?php echo $status_label; ?>
                            </span>
                            <?php if ($r['status'] != 'cancelled' && $r['status'] != 'rejected'): ?>
                            <span class="risk-badge header-badge risk-<?php echo $r['risk_level']; ?>">
                                <i class="fas <?php echo $r['risk_level'] == 'low' ? 'fa-seedling' : ($r['risk_level'] == 'medium' ? 'fa-exclamation-triangle' : ($r['risk_level'] == 'high' ? 'fa-fire' : 'fa-skull-crossbones')); ?> text-[10px] sm:text-xs"></i>
                                <?php echo ucfirst($r['risk_level']); ?>
                            </span>
                            <?php endif; ?>
                            <?php if(isset($r['decision_classification']) && $r['decision_classification'] && $r['status'] != 'cancelled' && $r['status'] != 'rejected'): ?>
                            <span class="severity-badge header-badge severity-<?php echo strtolower($r['decision_pin'] ?? 'Green'); ?>">
                                <i class="fas fa-chart-line text-[10px] sm:text-xs"></i>
                                <?php echo $r['decision_classification']; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-4 sm:p-5">
                        <p class="text-gray-500 mb-3 sm:mb-4 line-clamp-3"><?php echo htmlspecialchars(substr($r['description'], 0, 80)); ?><?php echo strlen($r['description']) > 80 ? '...' : ''; ?></p>

                        <div class="flex flex-wrap gap-2 sm:gap-3 pt-2 sm:pt-3 border-t border-gray-100">
                            <div class="meta-item">
                                <div class="meta-icon"><i class="fas fa-user text-gray-400 text-[10px] sm:text-xs"></i></div>
                                <span><?php echo htmlspecialchars($r['user_name'] ?? 'Unknown'); ?></span>
                            </div>
                            <div class="meta-item">
                                <div class="meta-icon"><i class="fas fa-tag text-gray-400 text-[10px] sm:text-xs"></i></div>
                                <span><?php echo htmlspecialchars($r['category_name']); ?></span>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-between items-center gap-3 pt-3 border-t border-gray-100 mt-3">
                            <div>
                                <?php if ($needs_attention): ?>
                                <span class="text-[10px] text-amber-600 font-medium"><i class="fas fa-exclamation-triangle mr-1"></i>Needs your attention</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if ($r['status'] === 'resolved'): ?>
                                <a href="<?php echo BASE_URL; ?>index.php?page=manage-report&id=<?php echo IdGuard::enc((int)$r['id']); ?>" class="btn-manage">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php elseif (PermissionHelper::canManageReport($r)): ?>
                                <a href="<?php echo BASE_URL; ?>index.php?page=manage-report&id=<?php echo IdGuard::enc((int)$r['id']); ?>" class="btn-manage" data-report-status="<?php echo htmlspecialchars($r['status']); ?>" data-report-id="<?php echo str_pad((int)$r['id'], 6, '0', STR_PAD_LEFT); ?>" data-report-title="<?php echo htmlspecialchars($r['title']); ?>" onclick="return confirmUnderReview(event, this)">
                                    <i class="fas fa-edit"></i> Manage
                                </a>
                                <?php else: ?>
                                <span class="btn-manage opacity-50 cursor-not-allowed" title="You are not permitted to manage this report">
                                    <i class="fas fa-lock"></i> Manage
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                        <i class="fas fa-inbox text-xl sm:text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="font-semibold text-gray-700 mb-1 sm:mb-2 text-base sm:text-lg">No reports found</h3>
                    <p class="text-gray-400 text-xs sm:text-sm mb-3 sm:mb-4">Try adjusting your filters</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <div id="paginationContainer">
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                <button onclick="goToPage(<?php echo $page-1; ?>)" class="page-btn"><i class="fas fa-chevron-left text-[10px] sm:text-xs"></i></button>
                <?php else: ?>
                <span class="page-btn disabled"><i class="fas fa-chevron-left text-[10px] sm:text-xs"></i></span>
                <?php endif; ?>
                
                <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <button onclick="goToPage(<?php echo $i; ?>)" class="page-btn <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></button>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                <button onclick="goToPage(<?php echo $page+1; ?>)" class="page-btn"><i class="fas fa-chevron-right text-[10px] sm:text-xs"></i></button>
                <?php else: ?>
                <span class="page-btn disabled"><i class="fas fa-chevron-right text-[10px] sm:text-xs"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- Under Review Confirmation Modal -->
<div id="underReviewModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm items-center justify-center z-[9999] p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl" onclick="event.stopPropagation()">
        <div class="w-14 h-14 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-search text-blue-600 text-2xl"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-800 text-center mb-2">Proceed with Under Review?</h3>
        <p class="text-sm text-gray-500 text-center mb-6">Do you want to proceed to place this report under review?</p>
        <div id="underReviewReportInfo" class="bg-gray-50 rounded-xl border border-gray-200 px-4 py-3 mb-6 <?php echo $autoUnderReviewReport ? '' : 'hidden'; ?>">
            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-1">Report <span id="underReviewReportNo"><?php echo $autoUnderReviewReport ? '#' . str_pad((int)$autoUnderReviewReport['id'], 6, '0', STR_PAD_LEFT) : ''; ?></span></div>
            <div id="underReviewReportTitle" class="text-sm font-semibold text-gray-800 leading-snug"><?php echo $autoUnderReviewReport ? htmlspecialchars($autoUnderReviewReport['title']) : ''; ?></div>
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="closeUnderReviewModal()" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-gray-600 font-medium hover:bg-gray-50 transition">Cancel</button>
            <button type="button" onclick="proceedUnderReview()" class="flex-1 px-4 py-2.5 bg-[#10A37F] text-white rounded-xl font-medium hover:bg-[#0D8568] transition">Proceed</button>
        </div>
    </div>
</div>

<script>
let currentViewMode = '<?php echo $view_mode; ?>';

// ===== VIEW MODE =====
function setViewMode(mode) {
    currentViewMode = mode;
    const container = document.getElementById('reportsGrid');
    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');

    container.classList.remove('grid-view', 'list-view');
    container.classList.add(mode + '-view');

    if (mode === 'grid') {
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
    } else {
        listBtn.classList.add('active');
        gridBtn.classList.remove('active');
    }

    document.cookie = "report_view_mode=" + mode + "; path=/; max-age=" + (365 * 24 * 60 * 60);
}

// ===== LOADING =====
function showLoading() { document.getElementById('loadingOverlay').classList.add('active'); }
function hideLoading() { document.getElementById('loadingOverlay').classList.remove('active'); }

// ===== UNDER REVIEW CONFIRMATION =====
let underReviewTargetUrl = '';
function confirmUnderReview(e, el) {
    if (el.getAttribute('data-report-status') !== 'pending') return true;
    e.preventDefault();
    underReviewTargetUrl = el.href;
    const title = el.getAttribute('data-report-title');
    if (title) {
        document.getElementById('underReviewReportTitle').textContent = title;
        document.getElementById('underReviewReportNo').textContent = el.getAttribute('data-report-id') ? '#' + el.getAttribute('data-report-id') : '';
        document.getElementById('underReviewReportInfo').classList.remove('hidden');
    }
    const modal = document.getElementById('underReviewModal');
    if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    return false;
}
function closeUnderReviewModal() {
    const modal = document.getElementById('underReviewModal');
    if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
}
function proceedUnderReview() {
    const url = underReviewTargetUrl;
    closeUnderReviewModal();
    if (url) window.location.href = url;
}
document.addEventListener('click', function(e) {
    const modal = document.getElementById('underReviewModal');
    if (modal && !modal.classList.contains('hidden') && e.target === modal) {
        closeUnderReviewModal();
    }
});

<?php if ($autoUnderReviewReport): ?>
// Auto-open the Under Review confirmation when arriving from the
// barangay map's "Manage Report" link, pointing back at manage-report.
document.addEventListener('DOMContentLoaded', function() {
    underReviewTargetUrl = '<?php echo BASE_URL; ?>index.php?page=manage-report&id=<?php echo IdGuard::enc((int)$autoUnderReviewReport['id']); ?>';
    const modal = document.getElementById('underReviewModal');
    if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
});
<?php endif; ?>

// ===== EXPORT CSV / PDF (open the dedicated print/export view, honoring filters) =====
function buildPrintUrl(format) {
    const params = new URLSearchParams();
    params.append('page', 'barangay-manage-reports-print');
    const status = document.getElementById('toolbarStatus')?.value || '';
    const risk = document.getElementById('popoverRisk')?.value || '';
    const category = document.getElementById('popoverCategory')?.value || '0';
    const dateRange = document.getElementById('popoverDateRange')?.value || '0';
    const residency = document.getElementById('popoverResidency')?.value || '';
    const search = document.getElementById('searchInput')?.value || '';
    if (status) params.append('status', status);
    if (risk) params.append('risk', risk);
    if (parseInt(category) > 0) params.append('category', category);
    if (parseInt(dateRange) > 0) params.append('date_range', dateRange);
    if (residency) params.append('residency', residency);
    if (search) params.append('search', search);
    if (format) params.append('format', format);
    return params.toString();
}

function exportReportsPdf() {
    document.getElementById('exportMenu').classList.remove('open');
    window.open('<?php echo BASE_URL; ?>index.php?' + buildPrintUrl('') + '&autoprint=1', '_blank');
}

function exportCSV() {
    document.getElementById('exportMenu').classList.remove('open');
    var iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = '<?php echo BASE_URL; ?>index.php?' + buildPrintUrl('csv');
    document.body.appendChild(iframe);
    setTimeout(function() { iframe.remove(); }, 8000);
}

// ===== EXPORT DROPDOWN =====
function toggleExportMenu() {
    document.getElementById('exportMenu').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    const dd = document.querySelector('.export-dropdown');
    const menu = document.getElementById('exportMenu');
    if (dd && menu && !dd.contains(e.target)) menu.classList.remove('open');
});

// ===== STATUS FILTER CHIPS (notification-style) =====
document.querySelectorAll('#statusChipBar .status-chip').forEach(function(chip) {
    chip.addEventListener('click', function() {
        var statusInput = document.getElementById('toolbarStatus');
        if (statusInput) statusInput.value = chip.getAttribute('data-status') || '';
        applyFilters();
    });
});

// ===== APPLY FILTERS =====
function applyFilters() {
    showLoading();
    const params = new URLSearchParams();
    params.append('page', 'verify-reports');

    const status = document.getElementById('toolbarStatus').value;
    const risk = document.getElementById('popoverRisk').value;
    const category = document.getElementById('popoverCategory').value;
    const dateRange = document.getElementById('popoverDateRange').value;
    const residency = document.getElementById('popoverResidency').value;
    const search = document.getElementById('searchInput').value;
    const sort = document.getElementById('toolbarSort').value;

    if (status) params.append('status', status);
    if (risk) params.append('risk', risk);
    if (category && category !== '0' && category !== '') params.append('category', category);
    if (dateRange && dateRange !== '0' && dateRange !== '') params.append('date_range', dateRange);
    if (residency) params.append('residency', residency);
    if (search) params.append('search', search);
    if (sort) params.append('sort', sort);

    window.location.href = '<?php echo BASE_URL; ?>index.php?' + params.toString();
}

// ===== UPDATE ACTIVE FILTER CHIPS =====
function updateActiveFilters() {
    const risk = document.getElementById('popoverRisk').value;
    const category = document.getElementById('popoverCategory').value;
    const dateRange = document.getElementById('popoverDateRange').value;
    const residency = document.getElementById('popoverResidency').value;
    const search = document.getElementById('searchInput').value;
    const container = document.querySelector('.active-filters-row');
    const toolbar = document.querySelector('.reports-toolbar');
    
    let activeCount = 0;
    if (risk) activeCount++;
    if (parseInt(category) > 0) activeCount++;
    if (parseInt(dateRange) > 0) activeCount++;
    if (residency) activeCount++;
    if (search) activeCount++;
    
    if (activeCount === 0) {
        if (container) container.style.display = 'none';
        if (toolbar) {
            toolbar.style.borderRadius = '14px';
            toolbar.classList.remove('style-has-chips');
            toolbar.style.marginBottom = '1.5rem';
        }
        return;
    }
    
    if (container) {
        container.style.display = 'flex';
        if (toolbar) {
            toolbar.classList.add('style-has-chips');
            toolbar.style.marginBottom = '0';
        }
        let html = '<span class="active-filters-label">Active:</span>';
        if (search) {
            const searchDisplay = search.length > 20 ? search.substring(0,20)+'...' : search;
            html += `<span class="filter-chip">"${searchDisplay}" <span class="chip-remove" data-filter="search"><i class="fas fa-times"></i></span></span>`;
        }
        if (risk) {
            const riskLabels = { 'low': 'Low Risk', 'medium': 'Medium Risk', 'high': 'High Risk', 'critical': 'Critical Risk' };
            html += `<span class="filter-chip">${riskLabels[risk] || risk} <span class="chip-remove" data-filter="risk"><i class="fas fa-times"></i></span></span>`;
        }
        if (parseInt(category) > 0) {
            const catSelect = document.getElementById('popoverCategory');
            const catName = catSelect.options[catSelect.selectedIndex]?.text || 'Category';
            html += `<span class="filter-chip">${catName} <span class="chip-remove" data-filter="category"><i class="fas fa-times"></i></span></span>`;
        }
        if (parseInt(dateRange) > 0) {
            const dateLabels = { '7': 'Last 7 Days', '30': 'Last 30 Days', '90': 'Last 90 Days' };
            html += `<span class="filter-chip">${dateLabels[dateRange] || dateRange+' days'} <span class="chip-remove" data-filter="date"><i class="fas fa-times"></i></span></span>`;
        }
        if (residency) {
            const residencyLabels = { 'resident': 'Reported by Resident', 'non_resident': 'Reported by Non-Resident' };
            html += `<span class="filter-chip">${residencyLabels[residency] || residency} <span class="chip-remove" data-filter="residency"><i class="fas fa-times"></i></span></span>`;
        }
        html += `<a href="#" class="chips-clear-all" id="clearAllFilters">Clear all</a>`;
        container.innerHTML = html;

        if (toolbar) toolbar.style.borderRadius = '14px 14px 0 0';
    }
}

// (Chip removal + Clear all are handled by the delegated listener in the shared
//  report_filter_toolbar partial, so they also work after the AJAX re-render.)

// ===== GO TO PAGE =====
function goToPage(page) {
    showLoading();
    const params = new URLSearchParams();
    params.append('page', 'verify-reports');

    const status = document.getElementById('toolbarStatus').value;
    const risk = document.getElementById('popoverRisk').value;
    const category = document.getElementById('popoverCategory').value;
    const dateRange = document.getElementById('popoverDateRange').value;
    const residency = document.getElementById('popoverResidency').value;
    const search = document.getElementById('searchInput').value;
    const sort = document.getElementById('toolbarSort').value;

    if (status) params.append('status', status);
    if (risk) params.append('risk', risk);
    if (category && category !== '0' && category !== '') params.append('category', category);
    if (dateRange && dateRange !== '0' && dateRange !== '') params.append('date_range', dateRange);
    if (residency) params.append('residency', residency);
    if (search) params.append('search', search);
    if (sort) params.append('sort', sort);
    params.append('page', page);

    window.location.href = '<?php echo BASE_URL; ?>index.php?' + params.toString();
}

// Event listeners for search/status/sort/popover/chips are provided by the shared
// report_filter_toolbar partial (search debounce, onchange, popover toggle/apply/reset,
// delegated chip removal + clear all, Escape close). Only page-specific handlers remain.

// ===== INITIAL UPDATE =====
updateActiveFilters();
</script>

</body>
</html>
