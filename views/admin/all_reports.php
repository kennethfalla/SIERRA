<?php
// views/admin/all_reports.php - MENRO ADMIN: View all reports with filters, pagination, and manage links
// UPDATED: Added "Under Review" status support
// UPDATED: Filter design adapted from my_reports.php with toolbar/popover style
// UPDATED: Page header design matches my_reports.php branding
// UPDATED: Added stats summary cards

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/helpers/SettingsHelper.php';
require_once dirname(__DIR__, 2) . '/helpers/PermissionHelper.php';
requireRole('admin');

$database = new Database();
$db = $database->getConnection();

// Get filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$barangay_filter = isset($_GET['barangay']) ? (int)$_GET['barangay'] : 0;
$risk_filter = isset($_GET['risk']) ? $_GET['risk'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
if ($limit < 1) $limit = 20;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = "1=1";
$params = [];

if ($status_filter != '') {
    // For escalated, we need to handle 'escalated_pending' and 'escalated'
    if ($status_filter == 'escalated') {
        $where .= " AND r.status IN ('escalated_pending', 'escalated')";
    } else {
        $where .= " AND r.status = :status";
        $params[':status'] = $status_filter;
    }
}
if ($category_filter > 0) {
    $where .= " AND r.category_id = :category";
    $params[':category'] = $category_filter;
}
if ($barangay_filter > 0) {
    $where .= " AND r.barangay_id = :barangay";
    $params[':barangay'] = $barangay_filter;
}
if ($risk_filter != '') {
    $where .= " AND r.risk_level = :risk";
    $params[':risk'] = $risk_filter;
}
if ($search != '') {
    $search_like = "%$search%";
    $where .= " AND (r.title LIKE :search OR r.description LIKE :search OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search)";
    $params[':search'] = $search_like;
}
if ($date_from != '') {
    $where .= " AND DATE(r.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to != '') {
    $where .= " AND DATE(r.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

// ============================================
// EXPORT CSV HANDLER
// ============================================
if (isset($_GET['export_type']) && $_GET['export_type'] !== '') {
    if (!PermissionHelper::userHasPermission('can_export_reports')) {
        $_SESSION['error'] = "You do not have permission to export reports.";
        header("Location: " . BASE_URL . "index.php?page=all-reports");
        exit();
    }
    $export_type = $_GET['export_type'];
    $export_where = $where;
    $export_params = $params;

    $status_labels_export = [
        'pending' => 'Pending', 'under_review' => 'Under Review', 'verified' => 'Verified',
        'in_progress' => 'In Progress', 'escalated_pending' => 'Escalated (Pending)',
        'escalated' => 'Escalated', 'resolved' => 'Resolved', 'rejected' => 'Rejected',
        'cancelled' => 'Cancelled'
    ];
    $risk_labels_export = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];

    $export_join = "FROM reports r
        JOIN categories c ON r.category_id = c.id
        JOIN barangays b ON r.barangay_id = b.id
        JOIN users u ON r.user_id = u.id";

    $export_rows = [];
    $export_headers = [];
    $export_filename = '';

    switch ($export_type) {
        case 'master':
            $export_filename = 'master_report_list_' . date('Y-m-d_His') . '.csv';
            $export_headers = ['Report ID','Title','Description','Category','Barangay','Reporter','Risk Level','Severity Score','Decision Classification','Status','Impact Modifier','Verifications','Density Count','Latitude','Longitude','Address','Date Submitted','Last Updated','Resolved At','Escalated At'];
            $sql = "SELECT r.id, r.title, r.description, r.latitude, r.longitude, r.location_address,
                    r.risk_level, r.severity_score, r.decision_classification, r.status,
                    r.impact_modifier, r.verification_count, r.spatial_density_count,
                    c.name AS category_name, b.name AS barangay_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS reporter_name,
                    r.created_at, r.updated_at, r.resolved_at, r.escalated_at
                    $export_join WHERE $export_where ORDER BY r.created_at DESC";
            $stmt = $db->prepare($sql);
            foreach ($export_params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $export_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'date_filtered':
            $export_filename = 'date_filtered_reports_' . date('Y-m-d_His') . '.csv';
            $export_headers = ['Report ID','Title','Category','Barangay','Reporter','Risk Level','Status','Date Submitted'];
            $sql = "SELECT r.id, r.title, c.name AS category_name, b.name AS barangay_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS reporter_name,
                    r.risk_level, r.status, r.created_at
                    $export_join WHERE $export_where ORDER BY r.created_at DESC";
            $stmt = $db->prepare($sql);
            foreach ($export_params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $export_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'by_category':
            $export_filename = 'category_grouped_' . date('Y-m-d_His') . '.csv';
            $export_headers = ['Category','Total Reports','High Risk','Critical Risk','Pending','In Progress','Resolved'];
            $sql = "SELECT c.name AS category_name, COUNT(*) AS total_reports,
                    SUM(CASE WHEN r.risk_level='high' THEN 1 ELSE 0 END) AS high_risk,
                    SUM(CASE WHEN r.risk_level='critical' THEN 1 ELSE 0 END) AS critical_risk,
                    SUM(CASE WHEN r.status='pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN r.status='in_progress' THEN 1 ELSE 0 END) AS in_progress,
                    SUM(CASE WHEN r.status='resolved' THEN 1 ELSE 0 END) AS resolved
                    $export_join WHERE $export_where GROUP BY c.id, c.name ORDER BY total_reports DESC";
            $stmt = $db->prepare($sql);
            foreach ($export_params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $export_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'by_barangay':
            $export_filename = 'barangay_aggregated_' . date('Y-m-d_His') . '.csv';
            $export_headers = ['Barangay','Total Reports','High Risk','Critical Risk','Pending','In Progress','Resolved','Escalated'];
            $sql = "SELECT b.name AS barangay_name, COUNT(*) AS total_reports,
                    SUM(CASE WHEN r.risk_level='high' THEN 1 ELSE 0 END) AS high_risk,
                    SUM(CASE WHEN r.risk_level='critical' THEN 1 ELSE 0 END) AS critical_risk,
                    SUM(CASE WHEN r.status='pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN r.status='in_progress' THEN 1 ELSE 0 END) AS in_progress,
                    SUM(CASE WHEN r.status='resolved' THEN 1 ELSE 0 END) AS resolved,
                    SUM(CASE WHEN r.status IN ('escalated','escalated_pending') THEN 1 ELSE 0 END) AS escalated
                    $export_join WHERE $export_where GROUP BY b.id, b.name ORDER BY total_reports DESC";
            $stmt = $db->prepare($sql);
            foreach ($export_params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $export_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'by_risk':
            $export_filename = 'risk_segmented_' . date('Y-m-d_His') . '.csv';
            $export_headers = ['Risk Level','Total Reports','Categories Affected','Pending','In Progress','Resolved','Escalated'];
            $sql = "SELECT r.risk_level, COUNT(*) AS total_reports,
                    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS categories_affected,
                    SUM(CASE WHEN r.status='pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN r.status='in_progress' THEN 1 ELSE 0 END) AS in_progress,
                    SUM(CASE WHEN r.status='resolved' THEN 1 ELSE 0 END) AS resolved,
                    SUM(CASE WHEN r.status IN ('escalated','escalated_pending') THEN 1 ELSE 0 END) AS escalated
                    $export_join WHERE $export_where GROUP BY r.risk_level ORDER BY FIELD(r.risk_level,'critical','high','medium','low')";
            $stmt = $db->prepare($sql);
            foreach ($export_params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $export_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'by_status':
            $export_filename = 'status_tracking_' . date('Y-m-d_His') . '.csv';
            $export_headers = ['Status','Total Reports','Categories','Barangays','Avg Severity Score'];
            $sql = "SELECT r.status AS status_name, COUNT(*) AS total_reports,
                    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS categories,
                    GROUP_CONCAT(DISTINCT b.name ORDER BY b.name SEPARATOR ', ') AS barangays,
                    AVG(r.severity_score) AS avg_severity
                    $export_join WHERE $export_where GROUP BY r.status ORDER BY FIELD(r.status,'pending','under_review','in_progress','escalated_pending','escalated','resolved','rejected')";
            $stmt = $db->prepare($sql);
            foreach ($export_params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $export_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        default:
            $_SESSION['error'] = "Invalid export type.";
            header("Location: " . BASE_URL . "index.php?page=all-reports");
            exit();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $export_filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $export_headers);

    foreach ($export_rows as $row) {
        switch ($export_type) {
            case 'master':
                fputcsv($output, [
                    '#' . str_pad($row['id'], 5, '0', STR_PAD_LEFT),
                    $row['title'], $row['description'] ?? '', $row['category_name'],
                    $row['barangay_name'], $row['reporter_name'],
                    $risk_labels_export[$row['risk_level']] ?? $row['risk_level'],
                    $row['severity_score'] ?? 0, $row['decision_classification'] ?? '',
                    $status_labels_export[$row['status']] ?? $row['status'],
                    $row['impact_modifier'] == 4 ? 'Severe' : ($row['impact_modifier'] == 2 ? 'Moderate' : 'Minor'),
                    $row['verification_count'] ?? 0, $row['spatial_density_count'] ?? 0,
                    $row['latitude'], $row['longitude'], $row['location_address'] ?? '',
                    $row['created_at'], $row['updated_at'], $row['resolved_at'] ?? '', $row['escalated_at'] ?? ''
                ]);
                break;
            case 'date_filtered':
                fputcsv($output, [
                    '#' . str_pad($row['id'], 5, '0', STR_PAD_LEFT),
                    $row['title'], $row['category_name'], $row['barangay_name'],
                    $row['reporter_name'],
                    $risk_labels_export[$row['risk_level']] ?? $row['risk_level'],
                    $status_labels_export[$row['status']] ?? $row['status'],
                    $row['created_at']
                ]);
                break;
            case 'by_category':
                fputcsv($output, [$row['category_name'], $row['total_reports'], $row['high_risk'], $row['critical_risk'], $row['pending'], $row['in_progress'], $row['resolved']]);
                break;
            case 'by_barangay':
                fputcsv($output, [$row['barangay_name'], $row['total_reports'], $row['high_risk'], $row['critical_risk'], $row['pending'], $row['in_progress'], $row['resolved'], $row['escalated']]);
                break;
            case 'by_risk':
                fputcsv($output, [$risk_labels_export[$row['risk_level']] ?? $row['risk_level'], $row['total_reports'], $row['categories_affected'], $row['pending'], $row['in_progress'], $row['resolved'], $row['escalated']]);
                break;
            case 'by_status':
                fputcsv($output, [$status_labels_export[$row['status_name']] ?? $row['status_name'], $row['total_reports'], $row['categories'], $row['barangays'], round($row['avg_severity'] ?? 0, 2)]);
                break;
        }
    }
    fclose($output);

    require_once BASE_PATH . 'models/ActivityLog.php';
    $actLog = new ActivityLog($db);
    $actLog->log($_SESSION['user_id'], 'Export Reports', "Exported $export_type report from All Reports page", $_SERVER['REMOTE_ADDR'] ?? 'unknown', null, 'SUCCESS');
    exit();
}

// Total count
$count_sql = "SELECT COUNT(*) FROM reports r JOIN users u ON r.user_id = u.id WHERE $where";
$count_stmt = $db->prepare($count_sql);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $limit));

// Get reports
$sql = "SELECT r.*, c.name as category_name, b.name as barangay_name,
               CONCAT(u.first_name, ' ', u.last_name) as user_name
        FROM reports r
        JOIN categories c ON r.category_id = c.id
        JOIN barangays b ON r.barangay_id = b.id
        JOIN users u ON r.user_id = u.id
        WHERE $where
        ORDER BY 
            CASE 
                WHEN r.status = 'escalated_pending' THEN 0 
                WHEN r.status = 'pending' THEN 1 
                WHEN r.status = 'under_review' THEN 2
                WHEN r.status = 'in_progress' THEN 3 
                ELSE 4 
            END,
            r.created_at DESC
        LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories and barangays for filters
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$barangays = $db->query("SELECT id, name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Escalated reports summary for MENRO admin visibility
$escalatedCount = $db->query("SELECT COUNT(*) FROM reports WHERE status IN ('escalated_pending','escalated')")->fetchColumn();
$escalatedReports = $db->query("SELECT r.id, r.title, r.created_at, b.name as barangay_name, r.risk_level FROM reports r LEFT JOIN barangays b ON r.barangay_id = b.id WHERE r.status IN ('escalated_pending','escalated') ORDER BY r.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// ===== STATS FOR SUMMARY CARDS =====
$totalReports = $db->query("SELECT COUNT(*) FROM reports")->fetchColumn();
$pendingCount = $db->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn();
$underReviewCount = $db->query("SELECT COUNT(*) FROM reports WHERE status='under_review'")->fetchColumn();
$inProgressCount = $db->query("SELECT COUNT(*) FROM reports WHERE status='in_progress'")->fetchColumn();
// $escalatedCount already defined above
$highRiskCount = $db->query("SELECT COUNT(*) FROM reports WHERE risk_level IN ('high','critical')")->fetchColumn();

// Active filters count for chips
$active_filters = 0;
if ($status_filter != '') $active_filters++;
if ($category_filter > 0) $active_filters++;
if ($barangay_filter > 0) $active_filters++;
if ($risk_filter != '') $active_filters++;
if (!empty($search)) $active_filters++;
if ($date_from != '') $active_filters++;
if ($date_to != '') $active_filters++;

// Helper labels for chips
$status_labels = [
    'pending' => 'Pending',
    'under_review' => 'Under Review',
    'in_progress' => 'In Progress',
    'escalated' => 'Escalated',
    'resolved' => 'Resolved',
    'rejected' => 'Rejected'
];
$risk_labels = ['low' => 'Low Risk', 'medium' => 'Medium Risk', 'high' => 'High Risk', 'critical' => 'Critical Risk'];
$active_category_name = ($category_filter > 0) ? (array_column($categories, 'name', 'id')[$category_filter] ?? '') : '';
$active_barangay_name = ($barangay_filter > 0) ? (array_column($barangays, 'name', 'id')[$barangay_filter] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Reports - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: #F7FBF9; }
        
        @media (max-width: 768px) {
            .ml-72 { margin-left: 0 !important; width: 100%; padding: 0; }
        }
        
        .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .status-pending { background: #FEF3C7; color: #D97706; }
        .status-under_review { background: #DBEAFE; color: #1E40AF; }
        .status-verified { background: #DBEAFE; color: #1E40AF; }
        .status-in_progress { background: #FCE7F3; color: #DB2777; }
        .status-escalated_pending { background: #FDE68A; color: #92400E; border: 1px solid #F59E0B; }
        .status-escalated { background: #FED7AA; color: #9A3412; }
        .status-resolved { background: #D1FAE5; color: #10A37F; }
        .status-rejected { background: #FEE2E2; color: #DC2626; }
        
        .risk-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .risk-low { background: #D1FAE5; color: #065F46; }
        .risk-medium { background: #FEF3C7; color: #92400E; }
        .risk-high { background: #FFEDD5; color: #9A3412; }
        .risk-critical { background: #FEE2E2; color: #991B1B; }
        
        /* ===== TOOLBAR & FILTER STYLES (adapted from my_reports.php) ===== */
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
            min-width: 340px;
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
            border: none;
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

        .btn-primary {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16,163,127,0.3);
        }

        .table-container {
            background: white;
            border-radius: 12px;
            border: 1px solid #eef2f0;
            overflow: hidden;
        }

        .escalated-panel {
            background: linear-gradient(90deg, #FFFBEB, #FFF7ED);
            border: 1px solid #FCD34D;
            padding: 0.75rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        .escalated-item {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 6px 8px;
            border-radius: 8px;
            align-items: center;
        }
        .escalated-item + .escalated-item { margin-top: 6px; }

        .pagination {
            display: flex;
            gap: 0.3rem;
            justify-content: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        @media (min-width: 640px) {
            .pagination { gap: 0.5rem; margin-top: 2rem; }
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
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        .main-container {
            padding: 1rem;
            max-width: 1600px;
            margin: 0 auto;
        }
        @media (min-width: 640px) {
            .main-container { padding: 1.5rem; }
        }
        @media (min-width: 768px) {
            .main-container { padding: 2rem; }
        }

        .page-header {
            margin-bottom: 1.25rem;
        }
        @media (min-width: 640px) {
            .page-header { margin-bottom: 1.5rem; }
        }
        .page-title {
            font-size: 1.5rem;
        }
        @media (min-width: 640px) {
            .page-title { font-size: 1.875rem; }
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            background: white;
            border-radius: 1rem;
            border: 1px solid #eef2f0;
        }
        @media (min-width: 640px) {
            .empty-state { padding: 3rem 2rem; }
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
    </style>
</head>
<body>

<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<div class="lg:ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">

        <!-- Header (adapted from my_reports.php branding style) -->
        <div class="page-header">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-7 h-7 md:w-8 md:h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-flag text-[#10A37F] text-xs md:text-sm"></i>
                </div>
                <span class="text-[10px] md:text-xs uppercase tracking-wider text-[#10A37F] font-semibold">All Reports</span>
            </div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="page-title font-bold text-gray-800">All Reports</h1>
                    <p class="text-gray-500 text-xs md:text-sm mt-0.5 md:mt-1">View and manage all environmental reports across San Isidro</p>
                </div>
                <div class="flex items-center gap-3">
                    <?php if (PermissionHelper::userHasPermission('can_export_reports')): ?>
                    <div class="export-dropdown" id="exportDropdownWrap">
                        <button onclick="toggleExportDropdown()" id="exportDropBtn" class="btn-export-trigger">
                            <i class="fas fa-download"></i> Export
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div id="exportDropdown" class="export-dropdown-menu" style="width:280px;">
                            <button class="export-dropdown-item" onclick="printReports()">
                                <div class="item-icon" style="background:#E8F5F0; color:#10A37F;"><i class="fas fa-file-pdf"></i></div>
                                <div class="item-text">
                                    <div class="item-title">Export as PDF</div>
                                    <div class="item-desc">Preview and save as PDF</div>
                                </div>
                            </button>
                            <button class="export-dropdown-item" onclick="downloadExport('master')">
                                <div class="item-icon" style="background:#DBEAFE; color:#2563EB;"><i class="fas fa-file-csv"></i></div>
                                <div class="item-text">
                                    <div class="item-title">Export as CSV</div>
                                    <div class="item-desc">All reports with current filters</div>
                                </div>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===== STATS SUMMARY CARDS ===== -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 md:gap-4 mb-6">
            <!-- Total -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 md:p-4 flex items-center gap-3 hover:shadow-md hover:border-[#10A37F] transition-all duration-200">
                <div class="w-9 h-9 md:w-10 md:h-10 rounded-full bg-[#10A37F]/10 flex items-center justify-center text-[#10A37F] flex-shrink-0">
                    <i class="fas fa-flag text-sm md:text-base"></i>
                </div>
                <div>
                    <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $totalReports; ?></div>
                    <div class="text-[10px] md:text-xs font-medium text-gray-500 uppercase tracking-wider">Total</div>
                </div>
            </div>
            <!-- Pending -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 md:p-4 flex items-center gap-3 hover:shadow-md hover:border-yellow-400 transition-all duration-200">
                <div class="w-9 h-9 md:w-10 md:h-10 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-700 flex-shrink-0">
                    <i class="fas fa-clock text-sm md:text-base"></i>
                </div>
                <div>
                    <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $pendingCount; ?></div>
                    <div class="text-[10px] md:text-xs font-medium text-gray-500 uppercase tracking-wider">Pending</div>
                </div>
            </div>
            <!-- Under Review -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 md:p-4 flex items-center gap-3 hover:shadow-md hover:border-blue-400 transition-all duration-200">
                <div class="w-9 h-9 md:w-10 md:h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 flex-shrink-0">
                    <i class="fas fa-search text-sm md:text-base"></i>
                </div>
                <div>
                    <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $underReviewCount; ?></div>
                    <div class="text-[10px] md:text-xs font-medium text-gray-500 uppercase tracking-wider">Under Review</div>
                </div>
            </div>
            <!-- In Progress -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 md:p-4 flex items-center gap-3 hover:shadow-md hover:border-pink-400 transition-all duration-200">
                <div class="w-9 h-9 md:w-10 md:h-10 rounded-full bg-pink-100 flex items-center justify-center text-pink-700 flex-shrink-0">
                    <i class="fas fa-spinner text-sm md:text-base"></i>
                </div>
                <div>
                    <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $inProgressCount; ?></div>
                    <div class="text-[10px] md:text-xs font-medium text-gray-500 uppercase tracking-wider">In Progress</div>
                </div>
            </div>
            <!-- Escalated -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 md:p-4 flex items-center gap-3 hover:shadow-md hover:border-orange-400 transition-all duration-200">
                <div class="w-9 h-9 md:w-10 md:h-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-700 flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-sm md:text-base"></i>
                </div>
                <div>
                    <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $escalatedCount; ?></div>
                    <div class="text-[10px] md:text-xs font-medium text-gray-500 uppercase tracking-wider">Escalated</div>
                </div>
            </div>
            <!-- High Risk -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 md:p-4 flex items-center gap-3 hover:shadow-md hover:border-red-400 transition-all duration-200">
                <div class="w-9 h-9 md:w-10 md:h-10 rounded-full bg-red-100 flex items-center justify-center text-red-700 flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-sm md:text-base"></i>
                </div>
                <div>
                    <div class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $highRiskCount; ?></div>
                    <div class="text-[10px] md:text-xs font-medium text-gray-500 uppercase tracking-wider">High Risk</div>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 flex items-center gap-2 text-sm">
                <i class="fas fa-check-circle text-green-500"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 flex items-center gap-2 text-sm">
                <i class="fas fa-exclamation-circle text-red-500"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Escalated Reports (highlight for MENRO admin) -->
        <?php if($escalatedCount > 0): ?>
        <div class="escalated-panel">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-yellow-100 text-yellow-800 rounded-full w-10 h-10 flex items-center justify-center text-lg"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <div class="text-sm font-semibold">Escalated Reports</div>
                        <div class="text-xs text-gray-600"><?php echo (int)$escalatedCount; ?> reports require attention</div>
                    </div>
                </div>
                <div>
                    <a href="?page=all-reports&status=escalated" class="px-3 py-1 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600 transition">View All Escalated</a>
                </div>
            </div>
            <div class="mt-3">
                <?php foreach($escalatedReports as $e): ?>
                <div class="escalated-item hover:bg-white/50 transition rounded-lg">
                    <div class="truncate"><strong>#<?php echo str_pad($e['id'],5,'0',STR_PAD_LEFT); ?></strong> &nbsp; <?php echo htmlspecialchars(substr($e['title'],0,60)); ?></div>
                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($e['barangay_name']); ?> · <?php echo date('M d', strtotime($e['created_at'])); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== FILTER TOOLBAR (shared partial) ===== -->
        <?php
        $ft_popover_count = 0;
        if ($category_filter > 0) $ft_popover_count++;
        if ($barangay_filter > 0) $ft_popover_count++;
        if ($risk_filter != '') $ft_popover_count++;
        if ($date_from != '') $ft_popover_count++;
        if ($date_to != '') $ft_popover_count++;

        $ft_chips = [];
        if (!empty($search)) $ft_chips[] = '<span class="filter-chip">"' . htmlspecialchars($search) . '" <span class="chip-remove" data-filter="search"><i class="fas fa-times"></i></span></span>';
        if ($status_filter != '') $ft_chips[] = '<span class="filter-chip">' . htmlspecialchars($status_labels[$status_filter] ?? ucfirst($status_filter)) . ' <span class="chip-remove" data-filter="status"><i class="fas fa-times"></i></span></span>';
        if ($category_filter > 0) $ft_chips[] = '<span class="filter-chip">' . htmlspecialchars($active_category_name) . ' <span class="chip-remove" data-filter="category"><i class="fas fa-times"></i></span></span>';
        if ($barangay_filter > 0) $ft_chips[] = '<span class="filter-chip">' . htmlspecialchars($active_barangay_name) . ' <span class="chip-remove" data-filter="barangay"><i class="fas fa-times"></i></span></span>';
        if ($risk_filter != '') $ft_chips[] = '<span class="filter-chip">' . htmlspecialchars($risk_labels[$risk_filter] ?? ucfirst($risk_filter)) . ' <span class="chip-remove" data-filter="risk"><i class="fas fa-times"></i></span></span>';
        if ($date_from != '') $ft_chips[] = '<span class="filter-chip">From: ' . date('M d, Y', strtotime($date_from)) . ' <span class="chip-remove" data-filter="date_from"><i class="fas fa-times"></i></span></span>';
        if ($date_to != '') $ft_chips[] = '<span class="filter-chip">To: ' . date('M d, Y', strtotime($date_to)) . ' <span class="chip-remove" data-filter="date_to"><i class="fas fa-times"></i></span></span>';

        $ft_cat_options = ['0' => 'All Categories'];
        foreach ($categories as $cat) { $ft_cat_options[(string)$cat['id']] = $cat['name']; }
        $ft_barangay_options = ['0' => 'All Barangays'];
        foreach ($barangays as $b) { $ft_barangay_options[(string)$b['id']] = $b['name']; }

        $ft = [
            'search_id'          => 'searchInput',
            'search_value'       => $search,
            'search_placeholder' => 'Search reports...',
            'results_text'       => 'Showing <strong id="resultsCountDisplay">' . count($reports) . '</strong> of <strong>' . $total . '</strong> reports',
            'inline_selects'     => [
                [
                    'id'        => 'toolbarStatus',
                    'value'     => $status_filter,
                    'min_width' => null,
                    'options'   => array_merge(['' => 'All Statuses'], $status_labels),
                ],
            ],
            'filter_by'          => [
                'active' => ($category_filter > 0 || $barangay_filter > 0 || $risk_filter != '' || $date_from != '' || $date_to != ''),
                'count'  => $ft_popover_count,
            ],
            'popover_fields'     => [
                ['kind' => 'select', 'id' => 'popoverCategory', 'label' => 'Category', 'value' => $category_filter, 'default' => '0', 'options' => $ft_cat_options],
                ['kind' => 'select', 'id' => 'popoverBarangay', 'label' => 'Barangay', 'value' => $barangay_filter, 'default' => '0', 'options' => $ft_barangay_options],
                ['kind' => 'select', 'id' => 'popoverRisk', 'label' => 'Risk Level', 'value' => $risk_filter, 'default' => '',
                 'options' => ['' => 'All Levels', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical']],
                ['kind' => 'date', 'id' => 'popoverDateFrom', 'label' => 'Date From', 'value' => $date_from, 'default' => ''],
                ['kind' => 'date', 'id' => 'popoverDateTo', 'label' => 'Date To', 'value' => $date_to, 'default' => ''],
            ],
            'trailing_select'    => [
                'id'        => 'toolbarLimit',
                'value'     => $limit,
                'min_width' => '80px',
                'options'   => ['10' => '10', '20' => '20', '50' => '50'],
            ],
            'active_filters'     => (int)$active_filters,
            'chips'              => $ft_chips,
            'chips_clear_all'    => true,
            'chip_clear_map'     => [
                'search'     => ['el' => 'searchInput', 'clear' => ''],
                'status'     => ['el' => 'toolbarStatus', 'clear' => ''],
                'category'   => ['el' => 'popoverCategory', 'clear' => '0'],
                'barangay'   => ['el' => 'popoverBarangay', 'clear' => '0'],
                'risk'       => ['el' => 'popoverRisk', 'clear' => ''],
                'date_from'  => ['el' => 'popoverDateFrom', 'clear' => ''],
                'date_to'    => ['el' => 'popoverDateTo', 'clear' => ''],
            ],
            'callback'           => 'applyFilters',
        ];
        include __DIR__ . '/../shared/report_filter_toolbar.php';
        ?>

        <!-- Results Table -->
        <div id="reportsGrid">
            <div class="table-container">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b" style="background: linear-gradient(90deg,#F0FBF6 0%, #F7FFF9 100%);">
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Title</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Reporter</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Barangay</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Risk</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($reports) > 0): ?>
                                <?php foreach($reports as $row): ?>
                                <tr class="border-b hover:bg-emerald-50/30 transition">
                                    <td class="px-4 py-3 text-sm font-mono text-gray-500">#<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-800"><?php echo htmlspecialchars(substr($row['title'], 0, 40)); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($row['user_name']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($row['barangay_name']); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="risk-badge risk-<?php echo $row['risk_level']; ?>">
                                            <?php echo ucfirst($row['risk_level']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php 
                                            $status_class = 'status-' . $row['status'];
                                            $status_icon = '';
                                            if ($row['status'] == 'pending') $status_icon = 'fa-clock';
                                            elseif ($row['status'] == 'under_review') $status_icon = 'fa-search';
                                            elseif ($row['status'] == 'in_progress') $status_icon = 'fa-spinner fa-pulse';
                                            elseif ($row['status'] == 'escalated_pending') $status_icon = 'fa-hourglass-half';
                                            elseif ($row['status'] == 'escalated') $status_icon = 'fa-shield-alt';
                                            elseif ($row['status'] == 'resolved') $status_icon = 'fa-check-circle';
                                            elseif ($row['status'] == 'rejected') $status_icon = 'fa-times-circle';
                                            $status_label = ucfirst(str_replace('_', ' ', $row['status']));
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <i class="fas <?php echo $status_icon; ?> text-xs"></i>
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td class="px-4 py-3">
                                        <?php if (PermissionHelper::canManageReport($row)): ?>
                                        <a href="<?php echo BASE_URL; ?>index.php?page=manage-report&id=<?php echo IdGuard::enc((int)$row['id']); ?>" class="btn-primary px-4 py-1.5 text-white text-sm rounded-lg inline-block">
                                            <i class="fas fa-edit mr-1"></i> Manage
                                        </a>
                                        <?php else: ?>
                                        <span class="px-4 py-1.5 text-gray-400 text-sm inline-block" title="You are not permitted to manage this report">
                                            <i class="fas fa-lock mr-1"></i> Manage
                                        </span>
                                        <?php endif; ?>
                                        <?php /* NOTE: This link-level gate is a UI convenience only. The actual
                                                 status-change logic lives in the manage-report page/controller,
                                                 which was not provided. That endpoint MUST also call
                                                 PermissionHelper::canManageReport($report) server-side before
                                                 applying any status change — see PermissionHelper.php integration
                                                 notes. */ ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="px-4 py-12 text-center">
                                        <div class="empty-state">
                                            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                                                <i class="fas fa-inbox text-xl sm:text-2xl text-gray-400"></i>
                                            </div>
                                            <h3 class="font-semibold text-gray-700 mb-1 sm:mb-2 text-base sm:text-lg">No reports found</h3>
                                            <p class="text-gray-400 text-xs sm:text-sm">Try adjusting your filters</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div id="paginationContainer">
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                <a href="?page=all-reports&page_num=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&barangay=<?php echo $barangay_filter; ?>&risk=<?php echo $risk_filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&limit=<?php echo $limit; ?>" class="page-btn"><i class="fas fa-chevron-left text-[10px] sm:text-xs"></i></a>
                <?php else: ?>
                <span class="page-btn disabled"><i class="fas fa-chevron-left text-[10px] sm:text-xs"></i></span>
                <?php endif; ?>
                
                <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <a href="?page=all-reports&page_num=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&barangay=<?php echo $barangay_filter; ?>&risk=<?php echo $risk_filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&limit=<?php echo $limit; ?>" class="page-btn <?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                <a href="?page=all-reports&page_num=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&barangay=<?php echo $barangay_filter; ?>&risk=<?php echo $risk_filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&limit=<?php echo $limit; ?>" class="page-btn"><i class="fas fa-chevron-right text-[10px] sm:text-xs"></i></a>
                <?php else: ?>
                <span class="page-btn disabled"><i class="fas fa-chevron-right text-[10px] sm:text-xs"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
// ===== FILTER FUNCTIONALITY (shared partial handles search/status/limit/popover/chips) =====

// Apply filters - redirect with all parameters
function applyFilters() {
    const params = new URLSearchParams();
    params.append('page', 'all-reports');
    
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('toolbarStatus').value;
    const category = document.getElementById('popoverCategory').value;
    const barangay = document.getElementById('popoverBarangay').value;
    const risk = document.getElementById('popoverRisk').value;
    const dateFrom = document.getElementById('popoverDateFrom').value;
    const dateTo = document.getElementById('popoverDateTo').value;
    const limit = document.getElementById('toolbarLimit').value;
    
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (parseInt(category) > 0) params.append('category', category);
    if (parseInt(barangay) > 0) params.append('barangay', barangay);
    if (risk) params.append('risk', risk);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    if (limit) params.append('limit', limit);
    
    window.location.href = '?' + params.toString();
}

// ===== EXPORT FUNCTIONALITY =====
function toggleExportDropdown() {
    const dd = document.getElementById('exportDropdown');
    const btn = document.getElementById('exportDropBtn');
    dd.classList.toggle('open');
    btn.classList.toggle('active');
}

function downloadExport(type) {
    const params = new URLSearchParams();
    params.append('page', 'all-reports');
    params.append('export_type', type);

    const status = document.getElementById('toolbarStatus').value;
    const category = document.getElementById('popoverCategory').value;
    const barangay = document.getElementById('popoverBarangay').value;
    const risk = document.getElementById('popoverRisk').value;
    const dateFrom = document.getElementById('popoverDateFrom').value;
    const dateTo = document.getElementById('popoverDateTo').value;
    const search = document.getElementById('searchInput').value;

    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (parseInt(category) > 0) params.append('category', category);
    if (parseInt(barangay) > 0) params.append('barangay', barangay);
    if (risk) params.append('risk', risk);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);

    window.location.href = '?' + params.toString();
    document.getElementById('exportDropdown').classList.remove('open');
    document.getElementById('exportDropBtn').classList.remove('active');
}

// Close dropdown on outside click
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('exportDropdownWrap');
    const dd = document.getElementById('exportDropdown');
    const btn = document.getElementById('exportDropBtn');
    if (wrap && dd && !wrap.contains(e.target)) {
        dd.classList.remove('open');
        if (btn) btn.classList.remove('active');
    }
});

function printReports() {
    const params = new URLSearchParams();
    params.append('page', 'all-reports-print');

    const status = document.getElementById('toolbarStatus').value;
    const category = document.getElementById('popoverCategory').value;
    const barangay = document.getElementById('popoverBarangay').value;
    const risk = document.getElementById('popoverRisk').value;
    const dateFrom = document.getElementById('popoverDateFrom').value;
    const dateTo = document.getElementById('popoverDateTo').value;
    const search = document.getElementById('searchInput').value;

    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (parseInt(category) > 0) params.append('category', category);
    if (parseInt(barangay) > 0) params.append('barangay', barangay);
    if (risk) params.append('risk', risk);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);

    window.open('<?php echo BASE_URL; ?>index.php?' + params.toString(), '_blank');
    document.getElementById('exportDropdown').classList.remove('open');
    document.getElementById('exportDropBtn').classList.remove('active');
}
</script>

</body>
</html>