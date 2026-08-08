<?php
// views/barangay/verify_reports.php - COMPLETE VERSION WITH UNDER REVIEW STATUS
// WITH TOOLBAR/POPOVER FILTER, PAGINATION, SORT, AND AJAX UPDATES
// STATS DESIGN UPDATED TO MATCH ADMIN DASHBOARD (with icons)

require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SecurityHelper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SettingsHelper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/PermissionHelper.php';
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
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$view_mode = isset($_COOKIE['report_view_mode']) && $_COOKIE['report_view_mode'] === 'list' ? 'list' : 'grid';

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
if ($search_keyword != '') {
    $search = "%$search_keyword%";
    $where .= " AND (r.title LIKE :search OR r.description LIKE :search OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search)";
    $params[':search'] = $search;
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
$sql = "SELECT r.*, c.name as category_name, CONCAT(u.first_name, ' ', u.last_name) as user_name
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

// Active filters count
$active_filters = 0;
if ($status_filter != '') $active_filters++;
if ($risk_filter != '') $active_filters++;
if ($category_filter > 0) $active_filters++;
if ($date_range > 0) $active_filters++;
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
$active_category_name = ($category_filter > 0 && isset($category_name_map[$category_filter])) ? $category_name_map[$category_filter] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Manage Reports - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .risk-high { background: #FEE2E2; color: #991B1B; }
        .risk-critical { background: #EDE9FE; color: #5B21B6; }
        
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
            border-radius: 14px;
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
            border-radius: 10px;
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
            border-radius: 10px;
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
            border-radius: 10px;
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
            border-radius: 9px;
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
            border-radius: 0 0 14px 14px;
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
            border-radius: 20px;
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
            border-radius: 0.4rem;
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
            border-radius: 0.4rem;
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
            border-radius: 2rem;
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
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(3px);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .loading-overlay.active { display: flex; }
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #10A37F;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            background: white;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

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
            .stat-card .stat-value {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body class="bg-[#F5FBF6]">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<div class="ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">
        
        <div id="loadingOverlay" class="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
        
        <!-- Header -->
        <div class="page-header">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-7 h-7 md:w-8 md:h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-double text-[#10A37F] text-xs md:text-sm"></i>
                </div>
                <span class="text-[10px] md:text-xs uppercase tracking-wider text-[#10A37F] font-semibold">Manage Reports</span>
            </div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="page-title font-bold text-gray-800">Manage Reports</h1>
                    <p class="text-gray-500 text-xs md:text-sm mt-0.5 md:mt-1">Review and manage environmental reports from your barangay</p>
                </div>
                <span class="inline-flex items-center px-3 py-1.5 bg-emerald-100 rounded-full text-xs text-[#10A37F] font-semibold">
                    <i class="fas fa-map-marker-alt mr-1.5"></i>San Isidro, Nueva Ecija
                </span>
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
        
        <!-- ===== STATISTICS CARDS (updated to match all_reports.php design) ===== -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 md:gap-4 mb-6">
            <?php
            // Define stats array (matches all_reports.php style)
            $stats_metrics = [
                ['label' => 'Total', 'value' => $total, 'color' => 'text-[#10A37F]', 'icon' => 'fa-flag', 'iconBg' => 'bg-[#10A37F]/10', 'iconColor' => 'text-[#10A37F]'],
                ['label' => 'Pending', 'value' => $pending, 'color' => 'text-yellow-600', 'icon' => 'fa-clock', 'iconBg' => 'bg-yellow-100', 'iconColor' => 'text-yellow-700'],
                ['label' => 'Under Review', 'value' => $under_review, 'color' => 'text-blue-600', 'icon' => 'fa-search', 'iconBg' => 'bg-blue-100', 'iconColor' => 'text-blue-700'],
                ['label' => 'In Progress', 'value' => $progress, 'color' => 'text-pink-600', 'icon' => 'fa-spinner', 'iconBg' => 'bg-pink-100', 'iconColor' => 'text-pink-700'],
                ['label' => 'Escalated', 'value' => $escalated, 'color' => 'text-orange-600', 'icon' => 'fa-exclamation-triangle', 'iconBg' => 'bg-orange-100', 'iconColor' => 'text-orange-700'],
                ['label' => 'Resolved', 'value' => $resolved, 'color' => 'text-[#10A37F]', 'icon' => 'fa-check-circle', 'iconBg' => 'bg-green-100', 'iconColor' => 'text-[#10A37F]'],
            ];
            foreach($stats_metrics as $m): ?>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 md:p-4 flex items-center gap-3 hover:shadow-md hover:border-[#10A37F] transition-all duration-200">
                <div class="w-9 h-9 md:w-10 md:h-10 rounded-full <?php echo $m['iconBg']; ?> flex items-center justify-center <?php echo $m['iconColor']; ?> flex-shrink-0">
                    <i class="fas <?php echo $m['icon']; ?> text-sm md:text-base"></i>
                </div>
                <div>
                    <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $m['value']; ?></div>
                    <div class="text-[10px] md:text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $m['label']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Risk Summary -->
        <div class="risk-summary-container" id="riskSummaryContainer">
            <span class="text-xs text-gray-500 font-medium mr-1">Risk Summary:</span>
            <?php foreach($risk_summary as $risk => $count): if($count > 0): ?>
            <span class="risk-badge risk-<?php echo $risk; ?>"><?php echo ucfirst($risk); ?>: <?php echo $count; ?></span>
            <?php endif; endforeach; ?>
        </div>
        
        <!-- ===== FILTER TOOLBAR ===== -->
        <div class="reports-toolbar <?php echo $active_filters > 0 ? 'style-has-chips' : ''; ?>" style="<?php echo $active_filters > 0 ? 'border-radius: 14px 14px 0 0;' : ''; ?>">
            <!-- Search -->
            <div class="toolbar-search">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" value="<?php echo htmlspecialchars($search_keyword); ?>" placeholder="Search reports...">
            </div>

            <!-- Status Dropdown -->
            <select id="toolbarStatus" class="toolbar-select">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $status_filter=='pending'?'selected':''; ?>>Pending</option>
                <option value="under_review" <?php echo $status_filter=='under_review'?'selected':''; ?>>Under Review</option>
                <option value="in_progress" <?php echo $status_filter=='in_progress'?'selected':''; ?>>In Progress</option>
                <option value="escalated" <?php echo $status_filter=='escalated'?'selected':''; ?>>Escalated</option>
                <option value="resolved" <?php echo $status_filter=='resolved'?'selected':''; ?>>Resolved</option>
                <option value="rejected" <?php echo $status_filter=='rejected'?'selected':''; ?>>Rejected</option>
                <option value="cancelled" <?php echo $status_filter=='cancelled'?'selected':''; ?>>Cancelled</option>
            </select>

            <!-- Filter By Popover -->
            <div class="filter-popover-wrapper">
                <button type="button" class="toolbar-filter-btn <?php echo ($risk_filter != '' || $category_filter > 0 || $date_range > 0) ? 'active' : ''; ?>" id="filterByBtn">
                    <i class="fas fa-sliders-h"></i> Filter By
                    <?php 
                        $popover_count = 0;
                        if ($risk_filter != '') $popover_count++;
                        if ($category_filter > 0) $popover_count++;
                        if ($date_range > 0) $popover_count++;
                        if ($popover_count > 0): 
                    ?>
                    <span class="filter-count-badge"><?php echo $popover_count; ?></span>
                    <?php endif; ?>
                </button>
                <div class="filter-popover" id="filterPopover">
                    <div class="popover-title">Refine Results</div>
                    <div class="popover-grid">
                        <div class="popover-field">
                            <label>Risk Level</label>
                            <select id="popoverRisk">
                                <option value="">All Levels</option>
                                <option value="low" <?php echo $risk_filter=='low'?'selected':''; ?>>Low</option>
                                <option value="medium" <?php echo $risk_filter=='medium'?'selected':''; ?>>Medium</option>
                                <option value="high" <?php echo $risk_filter=='high'?'selected':''; ?>>High</option>
                                <option value="critical" <?php echo $risk_filter=='critical'?'selected':''; ?>>Critical</option>
                            </select>
                        </div>
                        <div class="popover-field">
                            <label>Category</label>
                            <select id="popoverCategory">
                                <option value="0">All Categories</option>
                                <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter==$cat['id']?'selected':''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="popover-grid full-width" style="margin-top: 10px;">
                        <div class="popover-field">
                            <label>Date Range</label>
                            <select id="popoverDateRange">
                                <option value="0">All Time</option>
                                <option value="7" <?php echo $date_range==7?'selected':''; ?>>Last 7 Days</option>
                                <option value="30" <?php echo $date_range==30?'selected':''; ?>>Last 30 Days</option>
                                <option value="90" <?php echo $date_range==90?'selected':''; ?>>Last 90 Days</option>
                            </select>
                        </div>
                    </div>
                    <div class="popover-actions">
                        <button type="button" class="popover-btn-reset" id="popoverReset"><i class="fas fa-undo" style="font-size:0.7rem"></i> Reset</button>
                        <button type="button" class="popover-btn-apply" id="popoverApply"><i class="fas fa-check" style="font-size:0.7rem; margin-right:4px"></i>Apply Filters</button>
                    </div>
                </div>
            </div>

            <div class="toolbar-divider"></div>

            <!-- Results Count + View Toggle + Sort -->
            <div class="toolbar-results">
                <span class="toolbar-results-text">Showing <strong id="resultsCountDisplay"><?php echo count($reports); ?></strong> of <strong><?php echo $total_reports; ?></strong> reports</span>

                <!-- View Toggle -->
                <div class="view-toggle">
                    <button onclick="setViewMode('grid')" id="gridViewBtn" class="view-btn <?php echo $view_mode == 'grid' ? 'active' : ''; ?>">
                        <i class="fas fa-th"></i>
                    </button>
                    <button onclick="setViewMode('list')" id="listViewBtn" class="view-btn <?php echo $view_mode == 'list' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i>
                    </button>
                </div>

                <select id="toolbarSort" class="toolbar-select" style="min-width: 140px;">
                    <option value="newest" <?php echo $sort_order=='newest'?'selected':''; ?>>Recent to Older</option>
                    <option value="oldest" <?php echo $sort_order=='oldest'?'selected':''; ?>>Older to Recent</option>
                </select>
            </div>
        </div>

        <!-- Active Filter Chips -->
        <?php if ($active_filters > 0): ?>
        <div class="active-filters-row">
            <span class="active-filters-label">Active:</span>
            <?php if (!empty($search_keyword)): ?>
                <span class="filter-chip">"<?php echo htmlspecialchars($search_keyword); ?>" <span class="chip-remove" data-filter="search"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <?php if ($status_filter != ''): ?>
                <span class="filter-chip"><?php echo $status_labels[$status_filter] ?? ucfirst($status_filter); ?> <span class="chip-remove" data-filter="status"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <?php if ($risk_filter != ''): ?>
                <span class="filter-chip"><?php echo $risk_labels[$risk_filter] ?? ucfirst($risk_filter); ?> <span class="chip-remove" data-filter="risk"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <?php if ($category_filter > 0): ?>
                <span class="filter-chip"><?php echo htmlspecialchars($active_category_name); ?> <span class="chip-remove" data-filter="category"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <?php if ($date_range > 0): ?>
                <span class="filter-chip"><?php echo $date_range_labels[$date_range] ?? $date_range . ' days'; ?> <span class="chip-remove" data-filter="date"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <a href="#" class="chips-clear-all" id="clearAllFilters">Clear all</a>
        </div>
        <?php else: ?>
        <div style="margin-bottom: 1.5rem;"></div>
        <?php endif; ?>
        
        <!-- Reports Grid -->
        <div id="reportsGrid" class="reports-grid <?php echo $view_mode; ?>-view">
            <?php if(count($reports) > 0): ?>
                <?php foreach($reports as $r): 
                    $isEscalatedPending = ($r['status'] == 'escalated_pending');
                    $status_class = 'status-' . $r['status'];
                    $status_icon = '';
                    if ($r['status'] == 'pending') $status_icon = 'fa-clock';
                    elseif ($r['status'] == 'under_review') $status_icon = 'fa-search';
                    elseif ($r['status'] == 'in_progress') $status_icon = 'fa-spinner fa-pulse';
                    elseif ($r['status'] == 'escalated_pending') $status_icon = 'fa-hourglass-half';
                    elseif ($r['status'] == 'escalated') $status_icon = 'fa-shield-alt';
                    elseif ($r['status'] == 'resolved') $status_icon = 'fa-check-circle';
                    elseif ($r['status'] == 'rejected') $status_icon = 'fa-times-circle';
                    elseif ($r['status'] == 'cancelled') $status_icon = 'fa-ban';
                    $status_label = ucfirst(str_replace('_', ' ', $r['status']));
                    $needs_attention = $isEscalatedPending || in_array($r['status'], ['pending', 'under_review']);
                ?>
                <div class="report-card-grid <?php echo $isEscalatedPending ? 'border-2 border-orange-300' : ''; ?>" data-report-id="<?php echo $r['id']; ?>">
                    <div class="report-card-header rounded-t-2xl">
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
                                <span class="text-[8px] sm:text-[9px] font-mono opacity-75">(<?php echo $r['severity_score'] ?? 0; ?>)</span>
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
                            <?php if (isset($r['impact_modifier'])): ?>
                            <div class="meta-item">
                                <div class="meta-icon"><i class="fas fa-exclamation-triangle text-gray-400 text-[10px] sm:text-xs"></i></div>
                                <span>Impact: <?php echo $r['impact_modifier']==4?'Severe':($r['impact_modifier']==2?'Moderate':'Localized'); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-wrap justify-between items-center gap-3 pt-3 border-t border-gray-100 mt-3">
                            <div>
                                <?php if ($needs_attention): ?>
                                <span class="text-[10px] text-amber-600 font-medium"><i class="fas fa-exclamation-triangle mr-1"></i>Needs your attention</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="<?php echo BASE_URL; ?>index.php?page=track-status&id=<?php echo $r['id']; ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#10A37F] hover:text-[#0D8568] transition">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if (PermissionHelper::canManageReport($r)): ?>
                                <a href="<?php echo BASE_URL; ?>index.php?page=manage-report&id=<?php echo $r['id']; ?>" class="btn-manage">
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

<script>
let searchTimeout;
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

// ===== APPLY FILTERS =====
function applyFilters() {
    showLoading();
    const params = new URLSearchParams();
    params.append('status', document.getElementById('toolbarStatus').value);
    params.append('risk', document.getElementById('popoverRisk').value);
    params.append('category', document.getElementById('popoverCategory').value);
    params.append('date_range', document.getElementById('popoverDateRange').value);
    params.append('search', document.getElementById('searchInput').value);
    params.append('sort', document.getElementById('toolbarSort').value);
    params.append('scope', 'barangay');
    params.append('page', '1');

    fetch('<?php echo BASE_URL; ?>ajax/filter_reports.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('reportsGrid').innerHTML = data.html;
                document.getElementById('resultsCountDisplay').textContent = data.total_count;
                document.getElementById('paginationContainer').innerHTML = data.pagination;
                
                // Update risk summary
                let riskHtml = '<span class="text-xs text-gray-500 font-medium mr-1">Risk Summary:</span>';
                const risks = { low: 'Low', medium: 'Medium', high: 'High', critical: 'Critical' };
                for (let r in data.risk_summary) {
                    if (data.risk_summary[r] > 0) {
                        riskHtml += `<span class="risk-badge risk-${r}">${risks[r]}: ${data.risk_summary[r]}</span>`;
                    }
                }
                document.getElementById('riskSummaryContainer').innerHTML = riskHtml;
                updateActiveFilters();
            }
            hideLoading();
        })
        .catch(() => { hideLoading(); alert('Error loading reports'); });
}

// ===== UPDATE ACTIVE FILTER CHIPS =====
function updateActiveFilters() {
    const status = document.getElementById('toolbarStatus').value;
    const risk = document.getElementById('popoverRisk').value;
    const category = document.getElementById('popoverCategory').value;
    const dateRange = document.getElementById('popoverDateRange').value;
    const search = document.getElementById('searchInput').value;
    const container = document.querySelector('.active-filters-row');
    const toolbar = document.querySelector('.reports-toolbar');
    
    let activeCount = 0;
    if (status) activeCount++;
    if (risk) activeCount++;
    if (parseInt(category) > 0) activeCount++;
    if (parseInt(dateRange) > 0) activeCount++;
    if (search) activeCount++;
    
    if (activeCount === 0) {
        if (container) container.style.display = 'none';
        if (toolbar) toolbar.style.borderRadius = '14px';
        return;
    }
    
    if (container) {
        container.style.display = 'flex';
        let html = '<span class="active-filters-label">Active:</span>';
        if (search) {
            const searchDisplay = search.length > 20 ? search.substring(0,20)+'...' : search;
            html += `<span class="filter-chip">"${searchDisplay}" <span class="chip-remove" data-filter="search"><i class="fas fa-times"></i></span></span>`;
        }
        if (status) {
            const statusLabels = { 'pending': 'Pending', 'under_review': 'Under Review', 'in_progress': 'In Progress', 'escalated': 'Escalated', 'resolved': 'Resolved', 'rejected': 'Rejected', 'cancelled': 'Cancelled' };
            html += `<span class="filter-chip">${statusLabels[status] || status} <span class="chip-remove" data-filter="status"><i class="fas fa-times"></i></span></span>`;
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
        html += `<a href="#" class="chips-clear-all" id="clearAllFilters">Clear all</a>`;
        container.innerHTML = html;
        
        container.querySelectorAll('.chip-remove').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                const filter = this.getAttribute('data-filter');
                removeFilter(filter);
            });
        });
        const clearAll = container.querySelector('#clearAllFilters');
        if (clearAll) {
            clearAll.addEventListener('click', function(e) {
                e.preventDefault();
                clearAllFilters();
            });
        }
        if (toolbar) toolbar.style.borderRadius = '14px 14px 0 0';
    }
}

// ===== REMOVE INDIVIDUAL FILTER =====
function removeFilter(type) {
    if (type === 'search') document.getElementById('searchInput').value = '';
    else if (type === 'status') document.getElementById('toolbarStatus').value = '';
    else if (type === 'risk') document.getElementById('popoverRisk').value = '';
    else if (type === 'category') document.getElementById('popoverCategory').value = '0';
    else if (type === 'date') document.getElementById('popoverDateRange').value = '0';
    applyFilters();
}

// ===== CLEAR ALL FILTERS =====
function clearAllFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('toolbarStatus').value = '';
    document.getElementById('popoverRisk').value = '';
    document.getElementById('popoverCategory').value = '0';
    document.getElementById('popoverDateRange').value = '0';
    applyFilters();
}

// ===== GO TO PAGE =====
function goToPage(page) {
    showLoading();
    const params = new URLSearchParams();
    params.append('status', document.getElementById('toolbarStatus').value);
    params.append('risk', document.getElementById('popoverRisk').value);
    params.append('category', document.getElementById('popoverCategory').value);
    params.append('date_range', document.getElementById('popoverDateRange').value);
    params.append('search', document.getElementById('searchInput').value);
    params.append('sort', document.getElementById('toolbarSort').value);
    params.append('scope', 'barangay');
    params.append('page', page);

    fetch('<?php echo BASE_URL; ?>ajax/filter_reports.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('reportsGrid').innerHTML = data.html;
                document.getElementById('resultsCountDisplay').textContent = data.total_count;
                document.getElementById('paginationContainer').innerHTML = data.pagination;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            hideLoading();
        })
        .catch(() => hideLoading());
}

// ===== EVENT LISTENERS =====
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});

document.getElementById('toolbarStatus').addEventListener('change', applyFilters);
document.getElementById('toolbarSort').addEventListener('change', applyFilters);

// Popover toggle
const filterBtn = document.getElementById('filterByBtn');
const filterPopover = document.getElementById('filterPopover');

filterBtn?.addEventListener('click', function(e) {
    e.stopPropagation();
    filterPopover.classList.toggle('open');
});

document.addEventListener('click', function(e) {
    if (filterPopover && !filterPopover.contains(e.target) && e.target !== filterBtn) {
        filterPopover.classList.remove('open');
    }
});

filterPopover?.addEventListener('click', function(e) {
    e.stopPropagation();
});

document.getElementById('popoverApply')?.addEventListener('click', function() {
    filterPopover.classList.remove('open');
    applyFilters();
});

document.getElementById('popoverReset')?.addEventListener('click', function() {
    document.getElementById('popoverRisk').value = '';
    document.getElementById('popoverCategory').value = '0';
    document.getElementById('popoverDateRange').value = '0';
    filterPopover.classList.remove('open');
    applyFilters();
});

// ===== INITIAL UPDATE =====
updateActiveFilters();
</script>

</body>
</html>