<?php
// views/citizen/my_reports.php - COMPLETE VERSION WITH VERIFICATION/UPVOTE INTEGRATION
// UPDATED: Verify button only appears for reports not owned by the current user
// WITH TOOLBAR/POPOVER FILTER, PAGINATION, SORT, VIEW TOGGLE, AND AJAX UPDATES
// UPDATED: Added "Supported Reports" tab with enhanced card design matching own report cards
// UPDATED: Added stats summary cards (matching admin dashboard design)

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SecurityHelper.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// ============================================================
// ACTIVE TAB: 'my' or 'supported'
// ============================================================
$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'supported' ? 'supported' : 'my';

// Get initial filter values (from URL, if any)
$filter_status = isset($_GET['status']) && $_GET['status'] != '' ? $_GET['status'] : '';
$filter_risk = isset($_GET['risk']) && $_GET['risk'] != '' ? $_GET['risk'] : '';
$filter_category = isset($_GET['category']) && $_GET['category'] != '' ? (int)$_GET['category'] : 0;
$filter_date = isset($_GET['date_range']) && $_GET['date_range'] != '' ? (int)$_GET['date_range'] : 0;
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$view_mode = isset($_COOKIE['report_view_mode']) ? $_COOKIE['report_view_mode'] : 'grid';
if ($page < 1) $page = 1;

// Get categories for dropdowns
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Build category name map for chips
$category_name_map = [];
foreach ($categories as $cat) {
    $category_name_map[$cat['id']] = $cat['name'];
}

$limit = 10;
$offset = ($page - 1) * $limit;

// ============================================================
// MY REPORTS tab query
// ============================================================
$where_conditions = ["r.user_id = $user_id"];
$params = [];

if ($filter_status != '') {
    $where_conditions[] = "r.status = '$filter_status'";
}
if ($filter_risk != '') {
    $where_conditions[] = "r.risk_level = '$filter_risk'";
}
if ($filter_category > 0) {
    $where_conditions[] = "r.category_id = $filter_category";
}
if ($filter_date > 0) {
    $where_conditions[] = "r.created_at >= DATE_SUB(NOW(), INTERVAL $filter_date DAY)";
}
if ($search_keyword != '') {
    $search_escaped = addslashes($search_keyword);
    $where_conditions[] = "(r.title LIKE '%$search_escaped%' OR r.description LIKE '%$search_escaped%')";
}
$where_clause = implode(" AND ", $where_conditions);

// Count my reports
$count_sql = "SELECT COUNT(*) as total FROM reports r WHERE $where_clause";
$total_reports = $db->query($count_sql)->fetch(PDO::FETCH_ASSOC)['total'];

// ============================================================
// SUPPORTED REPORTS tab query
// ============================================================
$supported_where = ["rv.user_id = $user_id"];
if ($filter_status != '') {
    $supported_where[] = "r.status = '$filter_status'";
}
if ($filter_risk != '') {
    $supported_where[] = "r.risk_level = '$filter_risk'";
}
if ($filter_category > 0) {
    $supported_where[] = "r.category_id = $filter_category";
}
if ($filter_date > 0) {
    $supported_where[] = "rv.created_at >= DATE_SUB(NOW(), INTERVAL $filter_date DAY)";
}
if ($search_keyword != '') {
    $search_escaped = addslashes($search_keyword);
    $supported_where[] = "(r.title LIKE '%$search_escaped%' OR r.description LIKE '%$search_escaped%')";
}
$supported_where_clause = implode(" AND ", $supported_where);

$supported_count_sql = "SELECT COUNT(*) as total FROM report_verifications rv JOIN reports r ON rv.report_id = r.id WHERE $supported_where_clause";
$total_supported = $db->query($supported_count_sql)->fetch(PDO::FETCH_ASSOC)['total'];

// Set pagination based on active tab
$total_in_tab = ($active_tab === 'supported') ? $total_supported : $total_reports;
$total_pages = max(1, ceil($total_in_tab / $limit));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

// ============================================================
// FETCH REPORTS (active tab)
// ============================================================
if ($active_tab === 'supported') {
    $sql = "SELECT r.*, c.name as category_name, b.name as barangay_name,
                   r.user_id,
                   r.verification_count,
                   1 as is_verified_by_user,
                   rv.created_at as supported_at,
                   CONCAT(ou.first_name, ' ', ou.last_name) as owner_name
            FROM report_verifications rv
            JOIN reports r ON rv.report_id = r.id
            JOIN categories c ON r.category_id = c.id
            JOIN barangays b ON r.barangay_id = b.id
            JOIN users ou ON r.user_id = ou.id
            WHERE $supported_where_clause
            ORDER BY rv.created_at " . ($sort_order === 'oldest' ? 'ASC' : 'DESC') . "
            LIMIT $limit OFFSET $offset";
} else {
    $sql = "SELECT r.*, c.name as category_name, b.name as barangay_name,
                   r.user_id,
                   r.verification_count,
                   (SELECT COUNT(*) FROM report_verifications WHERE report_id = r.id AND user_id = $user_id) as is_verified_by_user
            FROM reports r
            JOIN categories c ON r.category_id = c.id
            JOIN barangays b ON r.barangay_id = b.id
            WHERE $where_clause
            ORDER BY r.created_at " . ($sort_order === 'oldest' ? 'ASC' : 'DESC') . "
            LIMIT $limit OFFSET $offset";
}
$reports = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get risk summary for initial load
$risk_summary = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
$risk_result = $db->query("SELECT risk_level, COUNT(*) as cnt FROM reports WHERE user_id = $user_id GROUP BY risk_level");
while ($row = $risk_result->fetch(PDO::FETCH_ASSOC)) {
    if (isset($risk_summary[$row['risk_level']])) {
        $risk_summary[$row['risk_level']] = $row['cnt'];
    }
}

// ============================================================
// STATS FOR SUMMARY CARDS (unfiltered counts for user's reports)
// ============================================================
$stats_total = $db->query("SELECT COUNT(*) FROM reports WHERE user_id = $user_id")->fetchColumn();
$stats_pending = $db->query("SELECT COUNT(*) FROM reports WHERE user_id = $user_id AND status = 'pending'")->fetchColumn();
$stats_under_review = $db->query("SELECT COUNT(*) FROM reports WHERE user_id = $user_id AND status = 'under_review'")->fetchColumn();
$stats_in_progress = $db->query("SELECT COUNT(*) FROM reports WHERE user_id = $user_id AND status = 'in_progress'")->fetchColumn();
$stats_escalated = $db->query("SELECT COUNT(*) FROM reports WHERE user_id = $user_id AND status IN ('escalated_pending','escalated')")->fetchColumn();
$stats_resolved = $db->query("SELECT COUNT(*) FROM reports WHERE user_id = $user_id AND status = 'resolved'")->fetchColumn();

// Helper labels for chips
$status_labels = [
    'pending' => 'Pending',
    'under_review' => 'Under Review',
    'in_progress' => 'In Progress',
    'escalated' => 'Escalated',
    'resolved' => 'Resolved',
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled'
];
$risk_labels = ['low' => 'Low Risk', 'medium' => 'Medium Risk', 'high' => 'High Risk', 'critical' => 'Critical Risk'];
$date_range_labels = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 3 months'];
$active_category_name = ($filter_category > 0 && isset($category_name_map[$filter_category])) ? $category_name_map[$filter_category] : '';

// Count active filters
$active_filters = 0;
if ($filter_status != '') $active_filters++;
if ($filter_risk != '') $active_filters++;
if ($filter_category > 0) $active_filters++;
if ($filter_date > 0) $active_filters++;
if ($search_keyword != '') $active_filters++;

// Generate CSRF token for AJAX
$csrf_token = InputSanitizer::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title>My Reports - EnviroTrack</title>
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
        
        /* Report Cards */
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
        .report-card-grid .header-badge i {
            color: white;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        @media (min-width: 640px) {
            .status-badge {
                padding: 4px 12px;
                font-size: 0.7rem;
            }
        }
        .status-pending { background: #FEF3C7; color: #D97706; }
        .status-under_review { background: #DBEAFE; color: #1E40AF; }
        .status-in_progress { background: #FCE7F3; color: #DB2777; }
        .status-resolved { background: #D1FAE5; color: #10A37F; }
        .status-escalated_pending { background: #FDE68A; color: #92400E; border: 1px solid #F59E0B; }
        .status-escalated { background: #FED7AA; color: #9A3412; }
        .status-rejected { background: #FEE2E2; color: #DC2626; }
        .status-cancelled { background: #F3F4F6; color: #6B7280; }
        
        /* Risk Badges */
        .risk-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        @media (min-width: 640px) {
            .risk-badge {
                padding: 3px 10px;
                font-size: 0.65rem;
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
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        @media (min-width: 640px) {
            .severity-badge {
                padding: 3px 10px;
                font-size: 0.65rem;
            }
        }
        .severity-Green { background: #D1FAE5; color: #065F46; }
        .severity-Yellow { background: #FEF3C7; color: #92400E; }
        .severity-Orange { background: #FED7AA; color: #9A3412; }
        .severity-Red { background: #FEE2E2; color: #991B1B; }
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

        /* Verification styles */
        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 600;
            background: #D1FAE5;
            color: #065F46;
        }
        .verification-count {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.7rem;
            color: #6B7280;
        }
        .verification-count i {
            color: #10A37F;
        }

        .verify-btn {
            transition: all 0.2s ease;
            font-size: 0.7rem;
            padding: 0.2rem 0.6rem;
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

        /* Toast notification */
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
        
        /* ===== TOOLBAR & FILTER STYLES ===== */
        :root {
            --lt-forest: #2D5A27;
            --lt-forest-light: #E8F0E7;
            --lt-forest-mid: #3A7332;
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
            box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.10);
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
            box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.10);
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
            box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.10);
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
            box-shadow: 0 4px 12px rgba(45, 90, 39, 0.2);
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
            background: rgba(45, 90, 39, 0.15);
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
            .view-btn {
                padding: 0.375rem 1rem;
                font-size: 0.875rem;
            }
        }
        .view-btn.active {
            background: white;
            color: #10A37F;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .view-btn:hover:not(.active) {
            color: #10A37F;
        }

        /* Grid Layout */
        .reports-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 640px) {
            .reports-grid {
                gap: 1.25rem;
            }
        }
        @media (min-width: 768px) {
            .reports-grid.grid-view { 
                grid-template-columns: repeat(2, 1fr); 
            }
        }
        @media (min-width: 1024px) {
            .reports-grid.grid-view { 
                grid-template-columns: repeat(3, 1fr); 
            }
        }

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

        /* Loading Overlay */
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
        @media (min-width: 640px) {
            .loading-spinner {
                width: 45px;
                height: 45px;
            }
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

        /* Meta Items */
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.6rem;
            color: #64748b;
        }
        @media (min-width: 640px) {
            .meta-item {
                gap: 0.5rem;
                font-size: 0.7rem;
            }
        }
        .meta-icon {
            width: 1.4rem;
            height: 1.4rem;
            background: #F5FBF6;
            border-radius: 0.4rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        @media (min-width: 640px) {
            .meta-icon {
                width: 1.75rem;
                height: 1.75rem;
                border-radius: 0.5rem;
            }
        }

        /* Container */
        .main-container {
            padding: 1rem;
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
        }

        /* Primary Button */
        .btn-primary {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.8rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        @media (min-width: 640px) {
            .btn-primary {
                padding: 0.5rem 1.25rem;
                font-size: 0.875rem;
            }
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
        }

        /* ===== TAB SWITCHER ===== */
        .tab-switcher {
            display: flex;
            gap: 0;
            background: #f1f5f4;
            border-radius: 14px;
            padding: 4px;
            width: fit-content;
            margin-bottom: 1.25rem;
        }
        .tab-btn {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #6B7280;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            white-space: nowrap;
        }
        @media (min-width: 640px) {
            .tab-btn { padding: 9px 22px; font-size: 0.875rem; }
        }
        .tab-btn.active {
            background: white;
            color: #10A37F;
            box-shadow: 0 2px 8px rgba(16, 163, 127, 0.12), 0 1px 3px rgba(0,0,0,0.08);
        }
        .tab-btn:hover:not(.active) { color: #10A37F; background: rgba(255,255,255,0.5); }
        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 700;
            background: #E5E7EB;
            color: #374151;
            transition: all 0.2s;
        }
        .tab-btn.active .tab-badge {
            background: #D1FAE5;
            color: #065F46;
        }
        /* Supported tab badge special color when active */
        .tab-btn.supported-tab.active .tab-badge {
            background: #0A7E6B;
            color: white;
        }

        /* ===== SUPPORTED CARDS (now matching own card spacing) ===== */
        .supported-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            animation: fadeInUp 0.4s ease-out both;
            cursor: pointer;
        }
        .supported-card:nth-child(2) { animation-delay: 0.05s; }
        .supported-card:nth-child(3) { animation-delay: 0.1s; }
        .supported-card:nth-child(4) { animation-delay: 0.15s; }
        .supported-card:nth-child(5) { animation-delay: 0.2s; }
        .supported-card:nth-child(6) { animation-delay: 0.25s; }

        .supported-card:hover {
            transform: translateY(-2px);
            border-color: #0A7E6B;
            box-shadow: 0 4px 12px rgba(10, 126, 107, 0.1);
        }
        .supported-card .card-header {
            background: linear-gradient(135deg, #F0F9F6 0%, #E1F0EC 100%);
            padding: 1rem 1rem 0.75rem;
            border-bottom: 2px solid rgba(10, 126, 107, 0.1);
        }
        .supported-card .card-header .supported-banner {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: white;
            border: 1px solid #0A7E6B;
            color: #0A7E6B;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.2rem 0.8rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            box-shadow: 0 2px 6px rgba(10, 126, 107, 0.08);
        }
        .supported-card .card-header .header-title {
            font-size: 1rem;
            line-height: 1.25;
            font-weight: 700;
            margin-top: 0.25rem;
            color: #0A7E6B;
            max-width: 22rem;
        }
        .supported-card .card-header .header-meta {
            font-size: 0.75rem;
            opacity: 0.8;
            color: #0A7E6B;
        }
        .supported-card .card-header .header-badge {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(10,126,107,0.15);
            color: #0A7E6B;
        }
        .supported-card .card-header .header-badge i {
            color: #0A7E6B;
        }
        .supported-card .track-report-btn {
            background: #0A7E6B;
            color: white;
            border: none;
            padding: 0.2rem 0.8rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .supported-card .track-report-btn:hover {
            background: #066B5A;
            box-shadow: 0 4px 12px rgba(10, 126, 107, 0.25);
            transform: scale(1.02);
        }
        .supported-card .track-report-btn i {
            color: white;
            font-size: 0.6rem;
        }
        .supported-card .status-badge.header-badge {
            background: rgba(255,255,255,0.6);
            border-color: rgba(10,126,107,0.15);
        }
        .supported-card .risk-badge.header-badge {
            background: rgba(255,255,255,0.6);
            border-color: rgba(10,126,107,0.15);
        }
        .supported-card .severity-badge.header-badge {
            background: rgba(255,255,255,0.6);
            border-color: rgba(10,126,107,0.15);
        }
        .supported-card .meta-item i {
            color: #0A7E6B;
        }
        .supported-card .meta-item {
            font-size: 0.6rem;
        }
        @media (min-width: 640px) {
            .supported-card .meta-item {
                font-size: 0.7rem;
            }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<div class="lg:ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">
        
        <div id="loadingOverlay" class="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
        
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center space-x-2 mb-2">
                <div class="w-8 h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-list text-[#10A37F] text-sm"></i>
                </div>
                <span class="text-xs uppercase tracking-wider text-[#10A37F] font-semibold"><?php echo $active_tab === 'supported' ? 'Supported Reports' : 'My Reports'; ?></span>
            </div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800"><?php echo $active_tab === 'supported' ? 'Reports I Supported' : 'My Reports'; ?></h1>
                    <p class="text-gray-500 text-sm mt-1"><?php echo $active_tab === 'supported' ? 'Track reports you have supported — see their progress and status updates.' : 'Track and manage all your environmental reports'; ?></p>
                </div>
                <a href="<?php echo BASE_URL; ?>index.php?page=submit-report" class="btn-primary inline-flex items-center gap-1.5 md:gap-2 w-full sm:w-auto justify-center">
                    <i class="fas fa-plus-circle text-xs md:text-sm"></i> 
                    <span class="text-xs md:text-sm">New Report</span>
                </a>
            </div>
        </div>

        <!-- ===== STATISTICS CARDS (matching all_reports.php design) ===== -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 md:gap-4 mb-6">
            <?php
            $stats_metrics = [
                ['label' => 'Total', 'value' => $stats_total, 'color' => 'text-[#10A37F]', 'icon' => 'fa-flag', 'iconBg' => 'bg-[#10A37F]/10', 'iconColor' => 'text-[#10A37F]'],
                ['label' => 'Pending', 'value' => $stats_pending, 'color' => 'text-yellow-600', 'icon' => 'fa-clock', 'iconBg' => 'bg-yellow-100', 'iconColor' => 'text-yellow-700'],
                ['label' => 'Under Review', 'value' => $stats_under_review, 'color' => 'text-blue-600', 'icon' => 'fa-search', 'iconBg' => 'bg-blue-100', 'iconColor' => 'text-blue-700'],
                ['label' => 'In Progress', 'value' => $stats_in_progress, 'color' => 'text-pink-600', 'icon' => 'fa-spinner', 'iconBg' => 'bg-pink-100', 'iconColor' => 'text-pink-700'],
                ['label' => 'Escalated', 'value' => $stats_escalated, 'color' => 'text-orange-600', 'icon' => 'fa-exclamation-triangle', 'iconBg' => 'bg-orange-100', 'iconColor' => 'text-orange-700'],
                ['label' => 'Resolved', 'value' => $stats_resolved, 'color' => 'text-[#10A37F]', 'icon' => 'fa-check-circle', 'iconBg' => 'bg-green-100', 'iconColor' => 'text-[#10A37F]'],
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

        <!-- Tab Switcher -->
        <div class="tab-switcher">
            <a href="<?php echo BASE_URL; ?>index.php?page=my-reports" class="tab-btn <?php echo $active_tab === 'my' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                My Reports
                <span class="tab-badge"><?php echo $total_reports; ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>index.php?page=my-reports&tab=supported" class="tab-btn supported-tab <?php echo $active_tab === 'supported' ? 'active' : ''; ?>">
                <i class="fas fa-heart" style="color: <?php echo $active_tab === 'supported' ? '#0A7E6B' : 'inherit'; ?>;"></i>
                Supported
                <span class="tab-badge" style="<?php echo $active_tab === 'supported' ? 'background:#0A7E6B; color:white;' : ''; ?>"><?php echo $total_supported; ?></span>
            </a>
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
                <option value="pending" <?php echo $filter_status=='pending'?'selected':''; ?>>Pending</option>
                <option value="under_review" <?php echo $filter_status=='under_review'?'selected':''; ?>>Under Review</option>
                <option value="in_progress" <?php echo $filter_status=='in_progress'?'selected':''; ?>>In Progress</option>
                <option value="escalated" <?php echo $filter_status=='escalated'?'selected':''; ?>>Escalated</option>
                <option value="resolved" <?php echo $filter_status=='resolved'?'selected':''; ?>>Resolved</option>
                <option value="rejected" <?php echo $filter_status=='rejected'?'selected':''; ?>>Rejected</option>
                <option value="cancelled" <?php echo $filter_status=='cancelled'?'selected':''; ?>>Cancelled</option>
            </select>

            <!-- Filter By Popover -->
            <div class="filter-popover-wrapper">
                <button type="button" class="toolbar-filter-btn <?php echo ($filter_risk != '' || $filter_category > 0 || $filter_date > 0) ? 'active' : ''; ?>" id="filterByBtn">
                    <i class="fas fa-sliders-h"></i> Filter By
                    <?php 
                        $popover_count = 0;
                        if ($filter_risk != '') $popover_count++;
                        if ($filter_category > 0) $popover_count++;
                        if ($filter_date > 0) $popover_count++;
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
                                <option value="low" <?php echo $filter_risk=='low'?'selected':''; ?>>Low</option>
                                <option value="medium" <?php echo $filter_risk=='medium'?'selected':''; ?>>Medium</option>
                                <option value="high" <?php echo $filter_risk=='high'?'selected':''; ?>>High</option>
                                <option value="critical" <?php echo $filter_risk=='critical'?'selected':''; ?>>Critical</option>
                            </select>
                        </div>
                        <div class="popover-field">
                            <label>Category</label>
                            <select id="popoverCategory">
                                <option value="0">All Categories</option>
                                <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $filter_category==$cat['id']?'selected':''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="popover-grid full-width" style="margin-top: 10px;">
                        <div class="popover-field">
                            <label>Date Range</label>
                            <select id="popoverDateRange">
                                <option value="0">All Time</option>
                                <option value="7" <?php echo $filter_date==7?'selected':''; ?>>Last 7 Days</option>
                                <option value="30" <?php echo $filter_date==30?'selected':''; ?>>Last 30 Days</option>
                                <option value="90" <?php echo $filter_date==90?'selected':''; ?>>Last 90 Days</option>
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

        <!-- Active Filter Chips (only when filters active) -->
        <?php if ($active_filters > 0): ?>
        <div class="active-filters-row">
            <span class="active-filters-label">Active:</span>
            <?php if (!empty($search_keyword)): ?>
                <span class="filter-chip">"<?php echo htmlspecialchars($search_keyword); ?>" <span class="chip-remove" data-filter="search"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <?php if ($filter_status != ''): ?>
                <span class="filter-chip"><?php echo $status_labels[$filter_status] ?? ucfirst($filter_status); ?> <span class="chip-remove" data-filter="status"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <?php if ($filter_risk != ''): ?>
                <span class="filter-chip"><?php echo $risk_labels[$filter_risk] ?? ucfirst($filter_risk); ?> <span class="chip-remove" data-filter="risk"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <?php if ($filter_category > 0): ?>
                <span class="filter-chip"><?php echo htmlspecialchars($active_category_name); ?> <span class="chip-remove" data-filter="category"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <?php if ($filter_date > 0): ?>
                <span class="filter-chip"><?php echo $date_range_labels[$filter_date] ?? $filter_date . ' days'; ?> <span class="chip-remove" data-filter="date"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <a href="#" class="chips-clear-all" id="clearAllFilters">Clear all</a>
        </div>
        <?php else: ?>
        <div style="margin-bottom: 1.5rem;"></div>
        <?php endif; ?>
        
        <!-- Reports Grid -->
        <div id="reportsGrid" class="reports-grid <?php echo $view_mode; ?>-view">
            <?php if(count($reports) > 0): ?>

                <?php if ($active_tab === 'supported'): ?>
                    <!-- ===== ENHANCED SUPPORTED REPORTS CARDS (matching own card spacing) ===== -->
                    <?php foreach($reports as $report): ?>
                    <div class="supported-card" data-report-id="<?php echo $report['id']; ?>" onclick="window.location.href='<?php echo BASE_URL; ?>index.php?page=track-status&id=<?php echo IdGuard::enc((int)$report['id']); ?>'">
                        <div class="card-header">
                            <div class="flex flex-col sm:flex-row justify-between items-start gap-3 mb-3">
                                <div class="space-y-2">
                                    <div class="supported-banner">
                                        <i class="fas fa-heart" style="color: #ef4444;"></i>
                                        You Supported This
                                    </div>
                                    <h3 class="header-title"><?php echo htmlspecialchars($report['title']); ?></h3>
                                    <div class="flex items-center gap-1 text-xs text-gray-500">
                                        <i class="fas fa-user-circle"></i>
                                        <span>by <?php echo htmlspecialchars($report['owner_name'] ?? 'Unknown'); ?></span>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="header-meta">#<?php echo str_pad($report['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                    <div class="header-meta mt-2"><?php echo date('M d, Y', strtotime($report['created_at'])); ?></div>
                                </div>
                            </div>
                            <!-- Badges -->
                            <div class="header-badges">
                                <?php
                                $status_icon = '';
                                if ($report['status'] == 'pending') $status_icon = 'fa-clock';
                                elseif ($report['status'] == 'under_review') $status_icon = 'fa-search';
                                elseif ($report['status'] == 'in_progress') $status_icon = 'fa-spinner fa-pulse';
                                elseif ($report['status'] == 'escalated_pending') $status_icon = 'fa-hourglass-half';
                                elseif ($report['status'] == 'escalated') $status_icon = 'fa-shield-alt';
                                elseif ($report['status'] == 'resolved') $status_icon = 'fa-check-circle';
                                elseif ($report['status'] == 'rejected') $status_icon = 'fa-times-circle';
                                elseif ($report['status'] == 'cancelled') $status_icon = 'fa-ban';
                                else $status_icon = 'fa-clock';
                                $status_label = ucfirst(str_replace('_', ' ', $report['status']));
                                ?>
                                <span class="status-badge header-badge status-<?php echo $report['status']; ?>">
                                    <i class="fas <?php echo $status_icon; ?> text-[10px] sm:text-xs"></i>
                                    <?php echo $status_label; ?>
                                </span>
                                <?php if ($report['status'] != 'cancelled' && $report['status'] != 'rejected'): ?>
                                <span class="risk-badge header-badge risk-<?php echo $report['risk_level']; ?>">
                                    <i class="fas <?php echo $report['risk_level'] == 'low' ? 'fa-seedling' : ($report['risk_level'] == 'medium' ? 'fa-exclamation-triangle' : ($report['risk_level'] == 'high' ? 'fa-fire' : 'fa-skull-crossbones')); ?> text-[10px] sm:text-xs"></i>
                                    <?php echo ucfirst($report['risk_level']); ?>
                                </span>
                                <?php endif; ?>
                                <?php if(isset($report['decision_classification']) && $report['decision_classification'] && $report['status'] != 'cancelled' && $report['status'] != 'rejected'): ?>
                                <span class="severity-badge header-badge severity-<?php echo strtolower($report['decision_pin'] ?? 'Green'); ?>">
                                    <i class="fas fa-chart-line text-[10px] sm:text-xs"></i>
                                    <?php echo $report['decision_classification']; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="p-4 sm:p-5">
                            <p class="text-gray-600 text-sm leading-relaxed line-clamp-3"><?php echo htmlspecialchars(substr($report['description'], 0, 80)); ?><?php echo strlen($report['description']) > 80 ? '...' : ''; ?></p>

                            <div class="flex flex-wrap items-center justify-between gap-3 mt-3 pt-2 border-t border-gray-100">
                                <div class="flex flex-wrap gap-3 meta-item">
                                    <span><i class="fas fa-tag mr-1"></i> <?php echo htmlspecialchars($report['category_name']); ?></span>
                                    <span><i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($report['barangay_name']); ?></span>
                                    <span><i class="fas fa-thumbs-up mr-1"></i> <?php echo (int)$report['verification_count']; ?> supporters</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-[#0A7E6B] bg-[#E8F4F0] px-2 py-1 rounded-full">
                                        <i class="fas fa-calendar-check mr-1"></i> <?php echo date('M d', strtotime($report['supported_at'])); ?>
                                    </span>
                                    <a href="<?php echo BASE_URL; ?>index.php?page=track-status&id=<?php echo IdGuard::enc((int)$report['id']); ?>" class="track-report-btn" onclick="event.stopPropagation();">
                                        <i class="fas fa-satellite-dish"></i> Track
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                <?php else: ?>
                    <!-- ===== MY REPORTS CARDS ===== -->
                    <?php foreach($reports as $report): ?>
                    <div class="report-card-grid" data-report-id="<?php echo $report['id']; ?>" onclick="window.location.href='<?php echo BASE_URL; ?>index.php?page=track-status&id=<?php echo IdGuard::enc((int)$report['id']); ?>'" style="cursor:pointer;">
                        <div class="report-card-header rounded-t-2xl">
                            <div class="flex flex-col sm:flex-row justify-between items-start gap-3 mb-3">
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 md:w-6 md:h-6 bg-white/20 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-file-alt text-white/80 text-[10px] md:text-xs"></i>
                                        </div>
                                        <span class="header-label">Report Summary</span>
                                    </div>
                                    <h3 class="header-title"><?php echo htmlspecialchars($report['title']); ?></h3>
                                </div>
                                <div class="text-right">
                                    <div class="header-meta">#<?php echo str_pad($report['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                    <div class="header-meta mt-2"><?php echo date('M d, Y', strtotime($report['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="header-badges">
                                <?php
                                $status_class = 'status-' . $report['status'];
                                $status_icon = '';
                                if ($report['status'] == 'pending') $status_icon = 'fa-clock';
                                elseif ($report['status'] == 'under_review') $status_icon = 'fa-search';
                                elseif ($report['status'] == 'in_progress') $status_icon = 'fa-spinner fa-pulse';
                                elseif ($report['status'] == 'escalated_pending') $status_icon = 'fa-hourglass-half';
                                elseif ($report['status'] == 'escalated') $status_icon = 'fa-shield-alt';
                                elseif ($report['status'] == 'resolved') $status_icon = 'fa-check-circle';
                                elseif ($report['status'] == 'rejected') $status_icon = 'fa-times-circle';
                                elseif ($report['status'] == 'cancelled') $status_icon = 'fa-ban';
                                else $status_icon = 'fa-clock';
                                $status_label = ucfirst(str_replace('_', ' ', $report['status']));
                                ?>
                                <span class="status-badge header-badge <?php echo $status_class; ?>">
                                    <i class="fas <?php echo $status_icon; ?> text-[10px] sm:text-xs"></i>
                                    <?php echo $status_label; ?>
                                </span>
                                <?php if ($report['status'] != 'cancelled' && $report['status'] != 'rejected'): ?>
                                <span class="risk-badge header-badge risk-<?php echo $report['risk_level']; ?>">
                                    <i class="fas <?php echo $report['risk_level'] == 'low' ? 'fa-seedling' : ($report['risk_level'] == 'medium' ? 'fa-exclamation-triangle' : ($report['risk_level'] == 'high' ? 'fa-fire' : 'fa-skull-crossbones')); ?> text-[10px] sm:text-xs"></i>
                                    <?php echo ucfirst($report['risk_level']); ?>
                                </span>
                                <?php endif; ?>
                                <?php if(isset($report['decision_classification']) && $report['decision_classification'] && $report['status'] != 'cancelled' && $report['status'] != 'rejected'): ?>
                                <span class="severity-badge header-badge severity-<?php echo strtolower($report['decision_pin'] ?? 'Green'); ?>">
                                    <i class="fas fa-chart-line text-[10px] sm:text-xs"></i>
                                    <?php echo $report['decision_classification']; ?>
                                    <span class="text-[8px] sm:text-[9px] font-mono opacity-75">(<?php echo $report['severity_score'] ?? 0; ?>)</span>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="p-4 sm:p-5">
                            <p class="text-gray-500 mb-3 sm:mb-4 line-clamp-3"><?php echo htmlspecialchars(substr($report['description'], 0, 80)); ?><?php echo strlen($report['description']) > 80 ? '...' : ''; ?></p>
                            
                            <div class="flex flex-wrap gap-2 sm:gap-3 pt-2 sm:pt-3 border-t border-gray-100">
                                <div class="meta-item">
                                    <div class="meta-icon"><i class="fas fa-tag text-gray-400 text-[10px] sm:text-xs"></i></div>
                                    <span><?php echo htmlspecialchars($report['category_name']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-icon"><i class="fas fa-map-marker-alt text-gray-400 text-[10px] sm:text-xs"></i></div>
                                    <span><?php echo htmlspecialchars($report['barangay_name']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-icon"><i class="far fa-calendar-alt text-gray-400 text-[10px] sm:text-xs"></i></div>
                                    <span><?php echo date('M d, Y', strtotime($report['created_at'])); ?></span>
                                </div>
                            </div>

                            <!-- ===== VERIFICATION SECTION (with ownership check) ===== -->
                            <div class="flex flex-wrap items-center gap-2 mt-3 pt-2 border-t border-gray-100">
                                <!-- Verification Count -->
                                <span class="verification-count">
                                    <i class="fas fa-thumbs-up"></i>
                                    <span class="font-medium" id="verifyCount-<?php echo $report['id']; ?>"><?php echo (int)$report['verification_count']; ?></span>
                                    <span class="text-gray-400">verification<?php echo $report['verification_count'] != 1 ? 's' : ''; ?></span>
                                </span>
                                
                                <?php if ($report['user_id'] != $user_id): ?>
                                    <!-- Only show verify options if not the owner -->
                                    <?php if ($report['is_verified_by_user'] > 0): ?>
                                        <span class="verification-badge">
                                            <i class="fas fa-check-circle"></i> You verified this
                                        </span>
                                    <?php else: ?>
                                        <?php if (!in_array($report['status'], ['resolved', 'rejected', 'cancelled'])): ?>
                                            <button class="verify-btn" onclick="event.stopPropagation(); verifyReport(<?php echo $report['id']; ?>, this)">
                                                <i class="fas fa-thumbs-up"></i> Verify
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Own report – show a label -->
                                    <span class="own-report-label">
                                        <i class="fas fa-user"></i> Your report
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                        <i class="fas <?php echo $active_tab === 'supported' ? 'fa-thumbs-up' : 'fa-inbox'; ?> text-xl sm:text-2xl text-gray-400"></i>
                    </div>
                    <?php if ($active_tab === 'supported'): ?>
                        <h3 class="font-semibold text-gray-700 mb-1 sm:mb-2 text-base sm:text-lg">No supported reports yet</h3>
                        <p class="text-gray-400 text-xs sm:text-sm mb-3 sm:mb-4">When you support a report from the community, it will appear here so you can track its progress.</p>
                        <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" class="btn-primary inline-flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm">
                            <i class="fas fa-map"></i> Explore Community Reports
                        </a>
                    <?php else: ?>
                        <h3 class="font-semibold text-gray-700 mb-1 sm:mb-2 text-base sm:text-lg">No reports found</h3>
                        <p class="text-gray-400 text-xs sm:text-sm mb-3 sm:mb-4">Try adjusting your filters</p>
                        <a href="<?php echo BASE_URL; ?>index.php?page=submit-report" class="btn-primary inline-flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm">
                            <i class="fas fa-plus-circle"></i> New Report
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <div id="paginationContainer">
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php
                $pag_params = ['page' => 'my-reports', 'tab' => $active_tab];
                if ($filter_status) $pag_params['status'] = $filter_status;
                if ($filter_risk) $pag_params['risk'] = $filter_risk;
                if ($filter_category > 0) $pag_params['category'] = $filter_category;
                if ($filter_date > 0) $pag_params['date_range'] = $filter_date;
                if ($search_keyword) $pag_params['search'] = $search_keyword;
                if ($sort_order) $pag_params['sort'] = $sort_order;
                ?>
                <?php if($page > 1): ?>
                <a href="<?php $pag_params['page_num'] = $page-1; echo BASE_URL . 'index.php?' . http_build_query(array_merge($pag_params, ['page' => 'my-reports', 'p' => $page-1])); ?>" class="page-btn"><i class="fas fa-chevron-left text-[10px] sm:text-xs"></i></a>
                <?php else: ?>
                <span class="page-btn disabled"><i class="fas fa-chevron-left text-[10px] sm:text-xs"></i></span>
                <?php endif; ?>
                
                <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <a href="<?php echo BASE_URL . 'index.php?' . http_build_query(array_merge($pag_params, ['page' => 'my-reports', 'p' => $i])); ?>" class="page-btn <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                <a href="<?php echo BASE_URL . 'index.php?' . http_build_query(array_merge($pag_params, ['page' => 'my-reports', 'p' => $page+1])); ?>" class="page-btn"><i class="fas fa-chevron-right text-[10px] sm:text-xs"></i></a>
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

// ===== APPLY FILTERS (URL-based) =====
function applyFilters() {
    const params = new URLSearchParams();
    params.append('page', 'my-reports');
    params.append('tab', '<?php echo $active_tab; ?>');

    const status = document.getElementById('toolbarStatus').value;
    const risk = document.getElementById('popoverRisk').value;
    const category = document.getElementById('popoverCategory').value;
    const dateRange = document.getElementById('popoverDateRange').value;
    const search = document.getElementById('searchInput').value;
    const sort = document.getElementById('toolbarSort').value;

    if (status) params.append('status', status);
    if (risk) params.append('risk', risk);
    if (category && category !== '0') params.append('category', category);
    if (dateRange && dateRange !== '0') params.append('date_range', dateRange);
    if (search) params.append('search', search);
    if (sort) params.append('sort', sort);

    window.location.href = '<?php echo BASE_URL; ?>index.php?' + params.toString();
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

// ===== VERIFY / UPVOTE REPORT (AJAX) =====
function verifyReport(reportId, button) {
    if (button.closest('.report-card-grid')?.querySelector('.own-report-label')) {
        showToast('You cannot verify your own report.', 'warning');
        return;
    }
    if (!confirm('Do you want to verify that you also witnessed this issue? This will increase the priority of this report.')) return;

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
            showToast('Thank you! Redirecting to Supported Reports...', 'success');
            button.parentElement.innerHTML = `<span class="verification-badge"><i class="fas fa-check-circle"></i> You verified this</span>`;
            setTimeout(() => {
                window.location.href = '<?php echo BASE_URL; ?>index.php?page=my-reports&tab=supported';
            }, 1500);
        } else {
            alert(data.message || 'Failed to verify. Please try again.');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-thumbs-up"></i> Verify';
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-thumbs-up"></i> Verify';
    });
}

// ===== TOAST NOTIFICATION =====
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

// ===== EVENT LISTENERS =====
// Search with debounce
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});

// Status dropdown
document.getElementById('toolbarStatus').addEventListener('change', applyFilters);

// Sort dropdown
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

// Popover Apply
document.getElementById('popoverApply')?.addEventListener('click', function() {
    filterPopover.classList.remove('open');
    applyFilters();
});

// Popover Reset
document.getElementById('popoverReset')?.addEventListener('click', function() {
    document.getElementById('popoverRisk').value = '';
    document.getElementById('popoverCategory').value = '0';
    document.getElementById('popoverDateRange').value = '0';
    filterPopover.classList.remove('open');
    applyFilters();
});

// Chip removal (server-rendered chips)
document.querySelectorAll('.chip-remove').forEach(function(el) {
    el.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        removeFilter(this.getAttribute('data-filter'));
    });
});

// Clear all chips
document.getElementById('clearAllFilters')?.addEventListener('click', function(e) {
    e.preventDefault();
    clearAllFilters();
});

// Escape closes popover
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && filterPopover?.classList.contains('open')) {
        filterPopover.classList.remove('open');
    }
});

// Stop verify button click from navigating card
document.querySelectorAll('.verify-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) { e.stopPropagation(); });
});
</script>

</body>
</html>