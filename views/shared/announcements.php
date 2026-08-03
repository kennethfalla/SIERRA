<?php
// views/shared/announcements.php - UNIFIED ANNOUNCEMENTS PAGE
// Fully responsive, matching my_reports/verify_reports design.
// Statistics are hidden for citizens (read‑only users).

require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SecurityHelper.php';

if(!isLoggedIn()) {
    header("Location: " . BASE_URL . "views/auth/login.php");
    exit();
}

$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];
$barangay_id = $_SESSION['barangay_id'] ?? null;

$database = new Database();
$db = $database->getConnection();

// Determine permissions
$is_admin = ($user_role === 'admin');
$is_barangay = ($user_role === 'barangay_official');
$is_citizen = ($user_role === 'citizen');

$can_create = ($is_admin || $is_barangay);
$can_edit = function($announcement) use ($user_id, $is_admin, $is_barangay) {
    if ($is_admin) return true;
    if ($is_barangay && $announcement['created_by'] == $user_id) return true;
    return false;
};
$can_delete = $can_edit;

// Get barangay name for display
$barangay_name = '';
if ($is_barangay || $is_citizen) {
    $stmt = $db->prepare("SELECT name FROM barangays WHERE id = ?");
    $stmt->execute([$barangay_id]);
    $barangay_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $barangay_name = $barangay_info['name'] ?? '';
}

// ========== PAGINATION & FILTERS ==========
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

if($limit < 1) $limit = 10;
if($page < 1) $page = 1;
$offset = ($page - 1) * $limit;
if($offset < 0) $offset = 0;

// Build WHERE clause based on role
$where = "1=1";
$params = [];

if ($is_admin) {
    // Admin sees everything
} elseif ($is_barangay || $is_citizen) {
    // See public OR barangay-specific for their barangay
    $where .= " AND (a.is_public = 1 OR (a.is_public = 0 AND a.barangay_id = ?))";
    $params[] = $barangay_id;
} else {
    $where .= " AND 1=0";
}

if($search_query != '') {
    $search = '%' . addslashes($search_query) . '%';
    $where .= " AND (a.title LIKE ? OR a.content LIKE ?)";
    $params[] = $search;
    $params[] = $search;
}

if($category_filter != 'all') {
    $where .= " AND a.category = ?";
    $params[] = $category_filter;
}

if($date_from != '') {
    $where .= " AND DATE(a.created_at) >= ?";
    $params[] = $date_from;
}

if($date_to != '') {
    $where .= " AND DATE(a.created_at) <= ?";
    $params[] = $date_to;
}

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT a.id) FROM announcements a WHERE $where";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_filtered = (int)$count_stmt->fetchColumn();

$total_pages = ceil($total_filtered / $limit);
if($total_pages < 1) $total_pages = 1;
if($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

// Get announcements with pagination
$sql = "
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as author_name,
           b.name as barangay_name,
           (SELECT COUNT(*) FROM announcement_images WHERE announcement_id = a.id) as image_count,
           a.created_by,
           a.created_by_role,
           a.is_public,
           a.barangay_id
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    LEFT JOIN barangays b ON a.barangay_id = b.id
    WHERE $where
    ORDER BY a.created_at DESC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($announcements as &$announcement) {
    $stmt_img = $db->prepare("SELECT id, image_path FROM announcement_images WHERE announcement_id = ? ORDER BY id ASC");
    $stmt_img->execute([$announcement['id']]);
    $announcement['images'] = $stmt_img->fetchAll(PDO::FETCH_ASSOC);
}
unset($announcement);

// Get all categories for filter dropdown
$categories = ['General', 'Environmental', 'Flood Warning', 'Clean-up Drive', 'Tree Planting', 'Waste Management', 'Emergency', 'Meeting', 'Event', 'Advisory'];

// Get stats based on what the user can see
$total_announcements = 0;
$monthly_count = 0;
$total_photos = 0;
$stats_sql = "SELECT COUNT(*) as total FROM announcements a WHERE $where";
$stats_stmt = $db->prepare($stats_sql);
$stats_stmt->execute($params);
$total_announcements = $stats_stmt->fetchColumn();

$monthly_sql = str_replace('COUNT(*)', 'COUNT(*)', $stats_sql) . " AND MONTH(a.created_at) = MONTH(CURRENT_DATE()) AND YEAR(a.created_at) = YEAR(CURRENT_DATE())";
$monthly_stmt = $db->prepare($monthly_sql);
$monthly_stmt->execute($params);
$monthly_count = $monthly_stmt->fetchColumn();

$photo_sql = "SELECT COUNT(*) FROM announcement_images ai JOIN announcements a ON ai.announcement_id = a.id WHERE $where";
$photo_stmt = $db->prepare($photo_sql);
$photo_stmt->execute($params);
$total_photos = $photo_stmt->fetchColumn();

// Helper functions for badges
function getCategoryBadge($category) {
    $category_colors = [
        'Emergency' => 'bg-red-100 text-red-700',
        'Flood Warning' => 'bg-orange-100 text-orange-700',
        'Environmental' => 'bg-emerald-100 text-emerald-700',
        'Clean-up Drive' => 'bg-blue-100 text-blue-700',
        'Tree Planting' => 'bg-green-100 text-green-700',
        'Waste Management' => 'bg-yellow-100 text-yellow-700',
        'Meeting' => 'bg-indigo-100 text-indigo-700',
        'Event' => 'bg-pink-100 text-pink-700',
        'Policy' => 'bg-gray-100 text-gray-700',
        'General' => 'bg-slate-100 text-slate-700'
    ];
    $color = $category_colors[$category] ?? 'bg-slate-100 text-slate-700';
    return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold ' . $color . '"><i class="fas fa-tag text-[9px]"></i> ' . htmlspecialchars($category) . '</span>';
}

function getAudienceBadge($is_public, $barangay_name = null) {
    if ($is_public == 1) {
        return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] bg-emerald-100 text-emerald-700 font-semibold"><i class="fas fa-globe text-[9px]"></i> Public</span>';
    } else {
        return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] bg-blue-100 text-blue-700 font-semibold"><i class="fas fa-building text-[9px]"></i> ' . htmlspecialchars($barangay_name) . ' Only</span>';
    }
}

function getSourceBadge($role, $barangay_name = null) {
    if ($role == 'menro') {
        return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] bg-purple-100 text-purple-700 font-semibold"><i class="fas fa-building text-[9px]"></i> MENRO</span>';
    } else {
        return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] bg-emerald-100 text-emerald-700 font-semibold"><i class="fas fa-map-marker-alt text-[9px]"></i> ' . htmlspecialchars($barangay_name) . '</span>';
    }
}

// Build query string for pagination – using global function from functions.php
$base_query_params = [
    'page' => 'announcements',
    'limit' => $limit,
    'search' => $search_query,
    'category' => $category_filter,
    'date_from' => $date_from,
    'date_to' => $date_to
];
$base_query_string = buildQueryString($base_query_params); // global function

// Get barangays list for admin audience selector
$barangays = [];
if ($is_admin) {
    $barangays = $db->query("SELECT id, name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

// Generate CSRF token for forms
$csrf_token = InputSanitizer::generateCsrfToken();

// Active filters count
$active_filters = 0;
if ($search_query != '') $active_filters++;
if ($category_filter != 'all') $active_filters++;
if ($date_from != '') $active_filters++;
if ($date_to != '') $active_filters++;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Announcements - EnviroTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Quill CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <!-- Quill JS -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: #F5FBF6; overflow-x: hidden; }

        /* ===== CONTAINER ===== */
        .main-container {
            padding: 1rem;
            max-width: 1280px;
            margin: 0 auto;
        }
        @media (min-width: 640px) {
            .main-container { padding: 1.5rem; }
        }
        @media (min-width: 768px) {
            .main-container { padding: 2rem; }
        }

        /* ===== SIDEBAR RESPONSIVE ===== */
        @media (max-width: 768px) {
            .ml-72 { margin-left: 0 !important; width: 100%; padding: 0; }
            .sidebar-mobile { position: fixed; left: -280px; transition: left 0.3s ease; z-index: 1000; }
            .sidebar-mobile.open { left: 0; }
        }

        /* ===== HEADER ===== */
        .page-header { margin-bottom: 1.25rem; }
        @media (min-width: 640px) { .page-header { margin-bottom: 1.5rem; } }
        .page-title { font-size: 1.5rem; }
        @media (min-width: 640px) { .page-title { font-size: 1.875rem; } }

        /* ===== STAT CARDS ===== */
        .stat-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(16, 163, 127, 0.08);
            padding: 1.25rem 1rem;
            transition: all 0.25s ease;
            opacity: 0;
            animation: slideUp 0.5s ease-out forwards;
        }
        @media (min-width: 640px) { .stat-card { padding: 1.5rem; } }
        .stat-card:hover {
            transform: translateY(-3px);
            border-color: #10A37F;
            box-shadow: 0 12px 24px -8px rgba(16, 163, 127, 0.12);
        }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 800; color: #1a2e1a; letter-spacing: -0.02em; }
        @media (min-width: 640px) { .stat-card .stat-value { font-size: 2rem; } }
        .stat-card .stat-label { font-size: 0.7rem; font-weight: 600; color: #8aa38a; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 0.15rem; }
        @media (min-width: 640px) { .stat-card .stat-label { font-size: 0.75rem; } }
        .stat-card .stat-icon { width: 2.5rem; height: 2.5rem; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        @media (min-width: 640px) { .stat-card .stat-icon { width: 3rem; height: 3rem; } }

        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.2s; }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
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
        .toolbar-search input::placeholder { color: #9CA3AF; }

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
        .toolbar-select:hover { border-color: var(--lt-forest); }

        .toolbar-divider {
            width: 1px;
            height: 28px;
            background: var(--lt-border-light);
            flex-shrink: 0;
        }

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

        .filter-popover-wrapper { position: relative; }
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
        .filter-popover.open { display: block; }
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
        .popover-grid.full-width { grid-template-columns: 1fr; }
        .popover-field label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--lt-gray-500);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .popover-field select,
        .popover-field input {
            width: 100%;
            padding: 7px 10px;
            border: 1.5px solid var(--lt-border-light);
            border-radius: 8px;
            font-size: 0.82rem;
            color: var(--lt-gray-700);
            background: var(--lt-gray-50);
            appearance: none;
            cursor: pointer;
            outline: none;
            transition: all 0.2s ease;
        }
        .popover-field select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%236B7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            padding-right: 30px;
        }
        .popover-field select:focus,
        .popover-field input:focus {
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
        .toolbar-results-text strong { color: var(--lt-gray-800); font-weight: 700; }

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
        .filter-chip:hover { background: #D4E4D2; }
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
        .filter-chip .chip-remove:hover { background: #c53030; color: white; }
        .chips-clear-all {
            font-size: 0.72rem;
            color: var(--lt-gray-500);
            text-decoration: none;
            font-weight: 500;
            margin-left: 4px;
            transition: color 0.15s ease;
        }
        .chips-clear-all:hover { color: #c53030; }

        /* ===== FEED CARDS ===== */
        .announcement-card {
            background: white;
            border: 1px solid rgba(16, 163, 127, 0.08);
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.25s ease;
        }
        .announcement-card:hover {
            transform: translateY(-2px);
            border-color: #10A37F;
            box-shadow: 0 8px 20px -8px rgba(16, 163, 127, 0.12);
        }
        .announcement-card .report-title { font-weight: 600; color: #1a2e1a; font-size: 0.95rem; }
        @media (min-width: 640px) { .announcement-card .report-title { font-size: 1rem; } }
        .announcement-card .report-description { color: #4b5a4a; font-size: 0.8rem; line-height: 1.4; }
        .announcement-card .meta-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.6rem;
            color: #64748b;
        }
        @media (min-width: 640px) { .announcement-card .meta-item { font-size: 0.7rem; gap: 0.5rem; } }
        .announcement-card .meta-icon {
            width: 1.4rem;
            height: 1.4rem;
            background: #F5FBF6;
            border-radius: 0.4rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        @media (min-width: 640px) { .announcement-card .meta-icon { width: 1.75rem; height: 1.75rem; border-radius: 0.5rem; } }

        .feed-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .feed-container { grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        }

        /* ===== FACEBOOK-STYLE PHOTO GRID ===== */
        .fb-photo-grid {
            display: grid;
            gap: 2px;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 10px;
            cursor: pointer;
            background: #e5e7eb;
        }
        .fb-photo-grid.grid-1 { grid-template-columns: 1fr; max-height: 400px; }
        .fb-photo-grid.grid-1 .fb-photo-item { aspect-ratio: auto; max-height: 400px; min-height: 200px; }
        .fb-photo-grid.grid-2 { grid-template-columns: 1fr 1fr; }
        .fb-photo-grid.grid-2 .fb-photo-item { aspect-ratio: 1; max-height: 350px; }
        .fb-photo-grid.grid-3 { grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; }
        .fb-photo-grid.grid-3 .fb-photo-item:first-child { grid-row: 1 / 3; aspect-ratio: auto; max-height: 350px; }
        .fb-photo-grid.grid-3 .fb-photo-item:not(:first-child) { aspect-ratio: 1; max-height: 175px; }
        .fb-photo-grid.grid-4 { grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; }
        .fb-photo-grid.grid-4 .fb-photo-item { aspect-ratio: 1; max-height: 200px; }
        .fb-photo-grid.grid-5 { grid-template-columns: 1fr 1fr 1fr; grid-template-rows: 1fr 1fr; }
        .fb-photo-grid.grid-5 .fb-photo-item:first-child,
        .fb-photo-grid.grid-5 .fb-photo-item:nth-child(2) { grid-row: 1 / 3; aspect-ratio: auto; max-height: 250px; }
        .fb-photo-grid.grid-5 .fb-photo-item:first-child { grid-column: 1 / 3; }
        .fb-photo-grid.grid-5 .fb-photo-item:nth-child(2) { grid-column: 3 / 4; }
        .fb-photo-grid.grid-5 .fb-photo-item:nth-child(3),
        .fb-photo-grid.grid-5 .fb-photo-item:nth-child(4),
        .fb-photo-grid.grid-5 .fb-photo-item:nth-child(5) { aspect-ratio: 1; max-height: 125px; }

        .fb-photo-item {
            position: relative;
            overflow: hidden;
            background: #d1d5db;
            width: 100%;
            height: 100%;
        }
        .fb-photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
            display: block;
            max-width: 100%;
        }
        .fb-photo-item:hover img { transform: scale(1.03); }
        .fb-photo-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .fb-photo-item:hover .fb-photo-overlay { opacity: 1; }
        .fb-photo-overlay i { color: white; font-size: 24px; background: rgba(0,0,0,0.5); padding: 10px; border-radius: 50%; }
        .fb-photo-extra {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: 700;
        }

        /* ===== LIGHTBOX ===== */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.92);
            backdrop-filter: blur(12px);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .lightbox.active { display: flex; }
        .lightbox-content { position: relative; max-width: 90vw; max-height: 90vh; display: flex; align-items: center; justify-content: center; }
        .lightbox-content img { max-width: 90vw; max-height: 85vh; object-fit: contain; border-radius: 0.75rem; box-shadow: 0 25px 60px -12px rgba(0,0,0,0.8); transition: opacity 0.3s ease; }
        .lightbox-close { position: fixed; top: 20px; right: 30px; width: 50px; height: 50px; background: rgba(255,255,255,0.15); border: 2px solid rgba(255,255,255,0.2); border-radius: 50%; color: white; font-size: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; z-index: 100000; }
        .lightbox-close:hover { background: rgba(255,255,255,0.25); transform: rotate(90deg); border-color: rgba(255,255,255,0.4); }
        .lightbox-nav { position: fixed; top: 50%; transform: translateY(-50%); width: 50px; height: 50px; background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.15); border-radius: 50%; color: white; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; z-index: 100000; }
        .lightbox-nav:hover { background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.3); }
        .lightbox-nav.prev { left: 30px; }
        .lightbox-nav.next { right: 30px; }
        .lightbox-counter { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); color: rgba(255,255,255,0.6); font-size: 0.9rem; font-weight: 500; background: rgba(0,0,0,0.5); padding: 0.5rem 1.2rem; border-radius: 2rem; z-index: 100000; font-family: 'Manrope', sans-serif; }

        /* ===== MODAL ===== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 1.5rem;
            max-width: 680px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }

        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 600; color: #1a1a2e; margin-bottom: 0.4rem; }
        .form-label .required { color: #EF4444; margin-left: 0.25rem; }
        .form-input-custom, .form-select-custom {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 2px solid #E5E7EB;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: #FAFAFA;
        }
        .form-input-custom:focus, .form-select-custom:focus {
            border-color: #10A37F;
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(16,163,127,0.2);
        }
        .upload-area {
            transition: all 0.2s;
            cursor: pointer;
            border: 2px dashed #E5E7EB;
            border-radius: 0.75rem;
            padding: 2rem;
            text-align: center;
            background: #FAFAFA;
        }
        .upload-area:hover { border-color: #10A37F; background: #F0FDF4; }
        .upload-area.dragover { border-color: #10A37F; background: #E6F7EF; }
        .photo-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
            max-height: 260px;
            overflow-y: auto;
        }
        .photo-preview-item { position: relative; aspect-ratio: 1; border-radius: 0.75rem; overflow: hidden; background: #f1f5f9; }
        .photo-preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .photo-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; background: #EF4444; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; transition: all 0.2s; }
        .photo-remove:hover { transform: scale(1.1); }
        .current-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
            max-height: 260px;
            overflow-y: auto;
        }
        .current-image-item { position: relative; aspect-ratio: 1; border-radius: 0.75rem; overflow: hidden; border: 1px solid #E5E7EB; }
        .current-image-item img { width: 100%; height: 100%; object-fit: cover; }
        .image-delete-btn { position: absolute; top: 4px; right: 4px; width: 28px; height: 28px; background: #EF4444; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; opacity: 0; transition: opacity 0.2s; }
        .current-image-item:hover .image-delete-btn { opacity: 1; }

        .modal-header-sticky { padding: 1.5rem 1.5rem 0 1.5rem; border-bottom: 1px solid #E5E7EB; background: white; border-radius: 1.5rem 1.5rem 0 0; flex-shrink: 0; }
        .modal-footer-sticky { padding: 1rem 1.5rem 1.5rem 1.5rem; background: white; border-top: 1px solid #E5E7EB; flex-shrink: 0; }
        .modal-form-scrollable { flex: 1; overflow-y: auto; padding: 1.5rem; }
        /* ===== PRIMARY BUTTON (on-brand) ===== */
        .btn-primary {
            background: linear-gradient(135deg, #10A37F, #0D8568);
            color: white;
            padding: 0.625rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(16, 163, 127, 0.18);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(16, 163, 127, 0.3);
        }
        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(16, 163, 127, 0.15);
        }

        .btn-cancel { background: white; border: 1px solid #E5E7EB; padding: 0.625rem 1.5rem; border-radius: 2rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-cancel:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn-submit { background: linear-gradient(135deg, #10A37F, #0D8568); color: white; padding: 0.625rem 1.5rem; border-radius: 2rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16,163,127,0.3); }
        .radio-group-modern { display: flex; gap: 1rem; flex-wrap: wrap; }
        .radio-card { flex: 1; position: relative; cursor: pointer; }
        .radio-card input { position: absolute; opacity: 0; }
        .radio-card .radio-label { display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1rem; border: 1.5px solid #E5E7EB; border-radius: 1rem; font-size: 0.875rem; font-weight: 600; transition: all 0.2s; background: white; cursor: pointer; }
        .radio-card input:checked + .radio-label { border-color: #10A37F; background: #ecfdf5; color: #065f46; }

        .ql-container { min-height: 150px; border-bottom-left-radius: 0.75rem; border-bottom-right-radius: 0.75rem; font-family: 'Manrope', sans-serif; font-size: 0.95rem; }
        .ql-toolbar { border-top-left-radius: 0.75rem; border-top-right-radius: 0.75rem; background: #FAFAFA; border-color: #E5E7EB !important; }
        .ql-editor { min-height: 120px; font-size: 0.95rem; font-family: 'Manrope', sans-serif; }
        .ql-editor p { margin-bottom: 0.5rem; }

        .content-preview { font-size: 0.95rem; line-height: 1.7; color: #374151; }
        .content-preview p { margin-bottom: 0.6rem; }
        .content-preview ul, .content-preview ol { padding-left: 1.5rem; margin-bottom: 0.6rem; }
        .content-preview h1, .content-preview h2, .content-preview h3 { font-weight: 700; margin-bottom: 0.4rem; }
        .content-preview h1 { font-size: 1.4rem; }
        .content-preview h2 { font-size: 1.2rem; }
        .content-preview h3 { font-size: 1.05rem; }
        .content-preview blockquote { border-left: 4px solid #10A37F; padding-left: 1rem; color: #4B5563; margin: 0.6rem 0; }
        .content-preview code { background: #F3F4F6; padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace; font-size: 0.9em; }
        .content-preview pre { background: #1F2937; color: #F9FAFB; padding: 0.8rem; border-radius: 8px; overflow-x: auto; margin: 0.6rem 0; }
        .content-preview pre code { background: transparent; padding: 0; color: inherit; }
        .content-preview a { color: #10A37F; text-decoration: underline; }
        .content-preview strong { font-weight: 700; }
        .content-preview em { font-style: italic; }

        /* ===== PAGINATION ===== */
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
        }
        @media (min-width: 640px) {
            .page-btn { min-width: 2.25rem; height: 2.25rem; font-size: 0.875rem; }
        }
        .page-btn:hover { background: #f0fdf4; border-color: #10A37F; }
        .page-btn.active { background: #10A37F; color: white; border-color: #10A37F; }
        .page-btn.disabled { opacity: 0.4; pointer-events: none; }

        /* ===== Empty State ===== */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            background: white;
            border-radius: 1rem;
            border: 1px solid #eef2f0;
        }
        @media (min-width: 640px) { .empty-state { padding: 3rem 2rem; } }

        /* ===== Responsive toolbar ===== */
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
            .active-filters-row { padding: 8px 12px; }
            .fb-photo-grid.grid-1 { max-height: 280px; }
            .fb-photo-grid.grid-1 .fb-photo-item { max-height: 280px; min-height: 160px; }
            .fb-photo-grid.grid-2 .fb-photo-item { max-height: 200px; }
            .fb-photo-grid.grid-3 .fb-photo-item:first-child { max-height: 240px; }
            .fb-photo-grid.grid-3 .fb-photo-item:not(:first-child) { max-height: 120px; }
            .fb-photo-grid.grid-4 .fb-photo-item { max-height: 140px; }
            .fb-photo-grid.grid-5 .fb-photo-item:first-child,
            .fb-photo-grid.grid-5 .fb-photo-item:nth-child(2) { max-height: 180px; }
            .fb-photo-grid.grid-5 .fb-photo-item:nth-child(3),
            .fb-photo-grid.grid-5 .fb-photo-item:nth-child(4),
            .fb-photo-grid.grid-5 .fb-photo-item:nth-child(5) { max-height: 90px; }
            .fb-photo-grid { gap: 1px; }
            .lightbox-nav { width: 40px; height: 40px; font-size: 14px; }
            .lightbox-nav.prev { left: 10px; }
            .lightbox-nav.next { right: 10px; }
            .lightbox-close { top: 10px; right: 10px; width: 40px; height: 40px; font-size: 18px; }
            .lightbox-content img { max-width: 95vw; max-height: 80vh; }
            .lightbox-counter { font-size: 0.75rem; padding: 0.3rem 1rem; bottom: 15px; }
        }
        @media (max-width: 480px) {
            .stat-card .stat-value { font-size: 1.5rem; }
            .stat-card { padding: 1rem; }
            .page-title { font-size: 1.25rem; }
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<div class="ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">

        <!-- ===== HEADER ===== -->
        <div class="page-header">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-7 h-7 md:w-8 md:h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-bullhorn text-[#10A37F] text-xs md:text-sm"></i>
                </div>
                <span class="text-[10px] md:text-xs uppercase tracking-wider text-[#10A37F] font-semibold">Announcements</span>
            </div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="page-title font-bold text-gray-800">
                        <?php
                        if ($is_admin) echo 'MENRO Announcements';
                        elseif ($is_barangay) echo htmlspecialchars($barangay_name) . ' Updates';
                        else echo 'Community Announcements';
                        ?>
                    </h1>
                    <p class="text-gray-500 text-xs md:text-sm mt-0.5 md:mt-1">
                        <?php
                        if ($is_admin) echo 'Manage all announcements for the municipality';
                        elseif ($is_barangay) echo 'View and manage announcements for your barangay';
                        else echo 'Stay updated with the latest news from your barangay and MENRO';
                        ?>
                    </p>
                </div>
                <?php if ($can_create): ?>
                    <button onclick="openCreateModal()" class="btn-primary inline-flex items-center gap-1.5 md:gap-2 w-full sm:w-auto justify-center">
                        <i class="fas fa-plus-circle text-xs md:text-sm"></i>
                        <span class="text-xs md:text-sm">Create Post</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== SUCCESS/ERROR MESSAGES ===== -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_admin || $is_barangay): ?>
        <!-- ===== STATISTICS CARDS ===== -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 md:gap-4 mb-6">
            <div class="stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="stat-value"><?php echo $total_announcements; ?></div>
                        <div class="stat-label">Total Posts</div>
                    </div>
                    <div class="stat-icon bg-emerald-100">
                        <i class="fas fa-newspaper text-[#10A37F] text-base md:text-lg"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="stat-value"><?php echo $monthly_count; ?></div>
                        <div class="stat-label">This Month</div>
                    </div>
                    <div class="stat-icon bg-blue-50">
                        <i class="fas fa-calendar-alt text-blue-500 text-base md:text-lg"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="stat-value"><?php echo $total_photos; ?></div>
                        <div class="stat-label">Photos</div>
                    </div>
                    <div class="stat-icon bg-purple-50">
                        <i class="fas fa-image text-purple-500 text-base md:text-lg"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="stat-value"><?php echo count($announcements); ?></div>
                        <div class="stat-label">Showing</div>
                    </div>
                    <div class="stat-icon bg-amber-50">
                        <i class="fas fa-eye text-amber-500 text-base md:text-lg"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== FILTER TOOLBAR ===== -->
        <div class="reports-toolbar <?php echo $active_filters > 0 ? 'style-has-chips' : ''; ?>" style="<?php echo $active_filters > 0 ? 'border-radius: 14px 14px 0 0;' : ''; ?>">
            <!-- Search -->
            <div class="toolbar-search">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search announcements...">
            </div>

            <!-- Category Dropdown -->
            <select id="toolbarCategory" class="toolbar-select">
                <option value="all">All Categories</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?php echo $cat; ?>" <?php echo $category_filter==$cat?'selected':''; ?>><?php echo $cat; ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Filter By Popover -->
            <div class="filter-popover-wrapper">
                <button type="button" class="toolbar-filter-btn <?php echo ($date_from != '' || $date_to != '') ? 'active' : ''; ?>" id="filterByBtn">
                    <i class="fas fa-sliders-h"></i> Filter By
                    <?php if($date_from != '' || $date_to != ''): ?>
                        <span class="filter-count-badge">1</span>
                    <?php endif; ?>
                </button>
                <div class="filter-popover" id="filterPopover">
                    <div class="popover-title">Refine Results</div>
                    <div class="popover-grid full-width" style="margin-top: 10px;">
                        <div class="popover-field">
                            <label>Date From</label>
                            <input type="date" id="popoverDateFrom" value="<?php echo $date_from; ?>" class="filter-input" style="padding: 7px 10px; border: 1.5px solid #E5E7EB; border-radius: 8px; width: 100%;">
                        </div>
                        <div class="popover-field">
                            <label>Date To</label>
                            <input type="date" id="popoverDateTo" value="<?php echo $date_to; ?>" class="filter-input" style="padding: 7px 10px; border: 1.5px solid #E5E7EB; border-radius: 8px; width: 100%;">
                        </div>
                    </div>
                    <div class="popover-actions">
                        <button type="button" class="popover-btn-reset" id="popoverReset"><i class="fas fa-undo" style="font-size:0.7rem"></i> Reset</button>
                        <button type="button" class="popover-btn-apply" id="popoverApply"><i class="fas fa-check" style="font-size:0.7rem; margin-right:4px"></i>Apply Filters</button>
                    </div>
                </div>
            </div>

            <div class="toolbar-divider"></div>

            <!-- Results Count + Per Page -->
            <div class="toolbar-results">
                <span class="toolbar-results-text">Showing <strong><?php echo count($announcements); ?></strong> of <strong><?php echo $total_filtered; ?></strong> posts</span>
                <select id="perPageSelect" class="toolbar-select" style="min-width: 80px;" onchange="changePerPage(this.value)">
                    <option value="5" <?php echo $limit==5?'selected':''; ?>>5</option>
                    <option value="10" <?php echo $limit==10?'selected':''; ?>>10</option>
                    <option value="25" <?php echo $limit==25?'selected':''; ?>>25</option>
                    <option value="50" <?php echo $limit==50?'selected':''; ?>>50</option>
                </select>
            </div>
        </div>

        <!-- Active Filter Chips -->
        <?php if ($active_filters > 0): ?>
        <div class="active-filters-row">
            <span class="active-filters-label">Active:</span>
            <?php if (!empty($search_query)): ?>
                <span class="filter-chip">"<?php echo htmlspecialchars($search_query); ?>" <span class="chip-remove" data-filter="search"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <?php if ($category_filter != 'all'): ?>
                <span class="filter-chip"><?php echo htmlspecialchars($category_filter); ?> <span class="chip-remove" data-filter="category"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <?php if ($date_from != ''): ?>
                <span class="filter-chip">From <?php echo date('M d', strtotime($date_from)); ?> <span class="chip-remove" data-filter="date_from"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <?php if ($date_to != ''): ?>
                <span class="filter-chip">To <?php echo date('M d', strtotime($date_to)); ?> <span class="chip-remove" data-filter="date_to"><i class="fas fa-times"></i></span></span>
            <?php endif; ?>
            <a href="#" class="chips-clear-all" id="clearAllFilters">Clear all</a>
        </div>
        <?php else: ?>
        <div style="margin-bottom: 1.5rem;"></div>
        <?php endif; ?>

        <!-- ===== FEED ===== -->
        <div id="announcementsGrid" class="feed-container">
            <?php if(count($announcements) > 0): ?>
                <?php 
                $seen_ids = [];
                foreach($announcements as $announcement): 
                    if(in_array($announcement['id'], $seen_ids)) continue;
                    $seen_ids[] = $announcement['id'];
                    $lightbox_images = [];
                    foreach($announcement['images'] as $img) {
                        $lightbox_images[] = BASE_URL . $img['image_path'];
                    }
                    $lightbox_json = json_encode($lightbox_images);
                    $imgCount = count($announcement['images']);
                    $is_owner = ($announcement['created_by'] == $user_id);
                    $can_edit_this = $can_edit($announcement);
                    $can_delete_this = $can_delete($announcement);
                ?>
                <div class="announcement-card">
                    <div class="p-4 sm:p-5">
                        <!-- Top Row: Badges + Actions -->
                        <div class="flex flex-wrap justify-between items-start gap-2 mb-2">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <?php echo getCategoryBadge($announcement['category'] ?? 'General'); ?>
                                <?php echo getSourceBadge($announcement['created_by_role'], $announcement['barangay_name']); ?>
                                <?php echo getAudienceBadge($announcement['is_public'] ?? 1, $announcement['barangay_name']); ?>
                            </div>
                            <?php if ($can_edit_this || $can_delete_this): ?>
                            <div class="flex gap-1 flex-shrink-0">
                                <?php if ($can_edit_this): ?>
                                <button onclick='openEditModal(<?php echo $announcement['id']; ?>, <?php echo json_encode(htmlspecialchars($announcement['title'])); ?>, <?php echo json_encode($announcement['content']); ?>, <?php echo json_encode($announcement['category'] ?? 'General'); ?>, <?php echo json_encode($announcement['images']); ?>)' class="text-gray-400 hover:text-emerald-600 transition p-1.5 hover:bg-emerald-50 rounded-lg" title="Edit">
                                    <i class="fas fa-edit text-sm"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($can_delete_this): ?>
                                <form method="POST" action="<?php echo BASE_URL; ?>controllers/AnnouncementController.php" onsubmit="return confirm('Delete this announcement?')" class="inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                    <button type="submit" class="text-gray-400 hover:text-red-500 transition p-1.5 hover:bg-red-50 rounded-lg" title="Delete">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-[10px] text-gray-400 font-medium flex items-center gap-1 px-2.5 py-1 bg-gray-50 rounded-full">
                                <i class="fas fa-lock text-[9px]"></i> Read Only
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Author & Date -->
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white text-xs font-bold">
                                <?php 
                                $name = $announcement['author_name'] ?? 'A';
                                echo strtoupper(substr($name, 0, 1));
                                ?>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($announcement['author_name']); ?></p>
                                <p class="text-xs text-gray-400">
                                    <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?> · <?php echo date('h:i A', strtotime($announcement['created_at'])); ?>
                                    <?php if($announcement['created_by_role'] == 'barangay' && $announcement['barangay_name']): ?> · <?php echo htmlspecialchars($announcement['barangay_name']); ?><?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Title -->
                        <h3 class="font-bold text-gray-800 text-lg mb-2 tracking-tight leading-snug">
                            <?php echo htmlspecialchars($announcement['title']); ?>
                        </h3>

                        <!-- Content Preview -->
                        <div class="content-preview mb-2" style="font-size:0.9rem;">
                            <?php 
                            $content = $announcement['content'];
                            if(empty($content) || trim($content) === '' || trim($content) === '<p><br></p>') {
                                echo '<p class="text-gray-400 italic">No content</p>';
                            } else {
                                echo $content;
                            }
                            ?>
                        </div>

                        <!-- Photo Grid -->
                        <?php if($imgCount > 0): 
                            $displayCount = min($imgCount, 5);
                            $extra = $imgCount - 5;
                            $gridClass = 'grid-' . $displayCount;
                        ?>
                        <div class="fb-photo-grid <?php echo $gridClass; ?>">
                            <?php 
                            $displayed = 0;
                            foreach($announcement['images'] as $idx => $image): 
                                if($displayed >= 5) break;
                                $isExtra = ($displayed == 4 && $imgCount > 5);
                                $displayed++;
                            ?>
                            <div class="fb-photo-item" onclick="event.stopPropagation(); openLightbox(<?php echo htmlspecialchars($lightbox_json); ?>, <?php echo $idx; ?>)">
                                <img src="<?php echo BASE_URL . $image['image_path']; ?>" alt="" loading="lazy">
                                <?php if($isExtra): ?>
                                <div class="fb-photo-extra">+<?php echo $extra; ?></div>
                                <?php else: ?>
                                <div class="fb-photo-overlay">
                                    <i class="fas fa-search-plus"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                        <i class="fas fa-inbox text-xl sm:text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="font-semibold text-gray-700 mb-1 sm:mb-2 text-base sm:text-lg">No announcements found</h3>
                    <p class="text-gray-400 text-xs sm:text-sm mb-3 sm:mb-4">Try adjusting your filters or create a new post.</p>
                    <?php if ($can_create): ?>
                        <button onclick="openCreateModal()" class="btn-primary inline-flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm">
                            <i class="fas fa-plus-circle"></i> Create Post
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== PAGINATION ===== -->
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

<!-- ===== MODALS & LIGHTBOX (same as before) ===== -->
<!-- CREATE MODAL -->
<div id="createModal" class="modal">
    <div class="modal-content scrollbar-hide">
        <div class="modal-header-sticky">
            <div class="flex items-center gap-3">
                <div class="post-avatar w-10 h-10">
                    <i class="fas fa-plus text-sm"></i>
                </div>
                <div>
                    <h3 class="font-bold text-xl text-gray-800 tracking-tight">Create Announcement</h3>
                    <p class="text-xs text-gray-400 font-medium">
                        <?php echo $is_admin ? 'Post to the whole municipality or a specific barangay' : 'Post to ' . htmlspecialchars($barangay_name); ?>
                    </p>
                </div>
                <button onclick="closeCreateModal()" class="ml-auto w-8 h-8 hover:bg-gray-100 rounded-lg transition flex items-center justify-center">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
        </div>

        <div class="modal-form-scrollable">
            <form method="POST" enctype="multipart/form-data" action="<?php echo BASE_URL; ?>controllers/AnnouncementController.php" id="postForm">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="content" id="create_content_hidden">

                <?php if ($is_admin): ?>
                <div class="form-group">
                    <label class="form-label">Audience</label>
                    <div class="radio-group-modern">
                        <label class="radio-card">
                            <input type="radio" name="audience" value="public" checked onchange="toggleBarangaySelect(false)">
                            <div class="radio-label">
                                <i class="fas fa-globe"></i>
                                <span>Public</span>
                            </div>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="audience" value="barangay" onchange="toggleBarangaySelect(true)">
                            <div class="radio-label">
                                <i class="fas fa-building"></i>
                                <span>Barangay Only</span>
                            </div>
                        </label>
                    </div>
                    <p class="text-xs text-gray-400 mt-2 font-medium">Public: Everyone can see | Barangay Only: Only residents of selected barangay</p>
                </div>

                <div class="form-group" id="barangaySelectContainer" style="display: none;">
                    <label class="form-label">Select Barangay</label>
                    <select name="barangay_id" class="form-select-custom">
                        <option value="">Select Barangay</option>
                        <?php foreach($barangays as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="audience" value="barangay">
                <input type="hidden" name="barangay_id" value="<?php echo $barangay_id; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" required class="form-select-custom">
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" required class="form-input-custom" placeholder="What's the announcement about?">
                </div>

                <div class="form-group">
                    <label class="form-label">Content</label>
                    <div style="border: 2px solid #E5E7EB; border-radius: 0.75rem; overflow: hidden;">
                        <div id="create_editor"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Add Photos <span class="text-gray-400 text-xs font-normal">(Max 10)</span></label>
                    <div class="upload-area" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2 block"></i>
                        <p class="text-sm text-gray-500 font-medium">Click or drag & drop to upload photos</p>
                        <p class="text-xs text-gray-400 mt-1 font-medium">JPG, PNG, GIF, WebP up to 5MB</p>
                        <input type="file" id="photoInput" name="images[]" accept="image/*" multiple style="display: none;">
                    </div>
                    <div id="photoPreviews" class="photo-preview-grid"></div>
                    <p class="text-xs text-gray-400 mt-2 font-medium" id="photoCount">0 / 10 photos selected</p>
                </div>
            </form>
        </div>

        <div class="modal-footer-sticky">
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeCreateModal()" class="btn-cancel">Cancel</button>
                <button type="submit" form="postForm" class="btn-submit flex items-center gap-2">
                    <i class="fas fa-paper-plane"></i> Post Announcement
                </button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="modal">
    <div class="modal-content scrollbar-hide">
        <div class="modal-header-sticky">
            <div class="flex items-center gap-3">
                <div class="post-avatar w-10 h-10">
                    <i class="fas fa-edit text-sm"></i>
                </div>
                <div>
                    <h3 class="font-bold text-xl text-gray-800 tracking-tight">Edit Announcement</h3>
                    <p class="text-xs text-gray-400 font-medium">Update your post</p>
                </div>
                <button onclick="closeEditModal()" class="ml-auto w-8 h-8 hover:bg-gray-100 rounded-lg transition flex items-center justify-center">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
        </div>

        <div class="modal-form-scrollable">
            <form method="POST" enctype="multipart/form-data" action="<?php echo BASE_URL; ?>controllers/AnnouncementController.php" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="announcement_id" id="edit_id">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="delete_images" id="delete_images" value="">
                <input type="hidden" name="content" id="edit_content_hidden">

                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" id="edit_category" required class="form-select-custom">
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" id="edit_title" required class="form-input-custom">
                </div>

                <div class="form-group">
                    <label class="form-label">Content</label>
                    <div style="border: 2px solid #E5E7EB; border-radius: 0.75rem; overflow: hidden;">
                        <div id="edit_editor"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Current Photos</label>
                    <div id="edit_image_gallery" class="current-images-grid"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Add More Photos</label>
                    <div class="upload-area" id="editUploadArea">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2 block"></i>
                        <p class="text-sm text-gray-500 font-medium">Click to upload more photos</p>
                        <p class="text-xs text-gray-400 mt-1 font-medium">JPG, PNG, GIF, WebP up to 5MB</p>
                        <input type="file" id="editPhotoInput" name="images[]" accept="image/*" multiple style="display: none;">
                    </div>
                    <div id="editPhotoPreviews" class="photo-preview-grid"></div>
                </div>
            </form>
        </div>

        <div class="modal-footer-sticky">
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeEditModal()" class="btn-cancel">Cancel</button>
                <button type="submit" form="editForm" class="btn-submit flex items-center gap-2">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- LIGHTBOX -->
<div id="lightbox" class="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="event.stopPropagation(); closeLightbox()">
        <i class="fas fa-times"></i>
    </button>
    <button class="lightbox-nav prev" onclick="event.stopPropagation(); navigateLightbox(-1)">
        <i class="fas fa-chevron-left"></i>
    </button>
    <button class="lightbox-nav next" onclick="event.stopPropagation(); navigateLightbox(1)">
        <i class="fas fa-chevron-right"></i>
    </button>
    <div class="lightbox-content" onclick="event.stopPropagation();">
        <img id="lightboxImage" src="" alt="Announcement image">
    </div>
    <div class="lightbox-counter" id="lightboxCounter">1 / 1</div>
</div>

<script>
// ===== TOGGLE BARANGAY SELECT =====
function toggleBarangaySelect(show) {
    document.getElementById('barangaySelectContainer').style.display = show ? 'block' : 'none';
}

// ===== LIGHTBOX =====
let lightboxImages = [];
let currentImageIndex = 0;

function openLightbox(images, index) {
    if(typeof images === 'string') {
        try { images = JSON.parse(images); } catch(e) { return; }
    }
    if (!images || !Array.isArray(images) || images.length === 0) return;

    lightboxImages = images;
    currentImageIndex = index || 0;
    var lightbox = document.getElementById('lightbox');
    var img = document.getElementById('lightboxImage');
    var counter = document.getElementById('lightboxCounter');

    img.src = lightboxImages[currentImageIndex];
    counter.textContent = (currentImageIndex + 1) + ' / ' + lightboxImages.length;
    lightbox.classList.add('active');
    document.body.style.overflow = 'hidden';

    var prevBtn = document.querySelector('.lightbox-nav.prev');
    var nextBtn = document.querySelector('.lightbox-nav.next');
    if (lightboxImages.length <= 1) {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'none';
    } else {
        prevBtn.style.display = 'flex';
        nextBtn.style.display = 'flex';
    }
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
    document.body.style.overflow = '';
}

function navigateLightbox(direction) {
    var newIndex = currentImageIndex + direction;
    if (newIndex < 0 || newIndex >= lightboxImages.length) return;

    currentImageIndex = newIndex;
    var img = document.getElementById('lightboxImage');
    var counter = document.getElementById('lightboxCounter');

    img.style.opacity = '0.5';
    setTimeout(function() {
        img.src = lightboxImages[currentImageIndex];
        img.style.opacity = '1';
    }, 150);

    counter.textContent = (currentImageIndex + 1) + ' / ' + lightboxImages.length;
}

document.addEventListener('keydown', function(e) {
    var lightbox = document.getElementById('lightbox');
    if (!lightbox.classList.contains('active')) return;
    if (e.key === 'Escape') closeLightbox();
    else if (e.key === 'ArrowLeft') navigateLightbox(-1);
    else if (e.key === 'ArrowRight') navigateLightbox(1);
});

// ===== FILTER FUNCTIONS =====
let searchTimeout;

function applyFilters() {
    const params = new URLSearchParams();
    const search = document.getElementById('searchInput').value;
    const category = document.getElementById('toolbarCategory').value;
    const dateFrom = document.getElementById('popoverDateFrom').value;
    const dateTo = document.getElementById('popoverDateTo').value;
    const limit = document.getElementById('perPageSelect').value;

    params.append('search', search);
    params.append('category', category);
    params.append('date_from', dateFrom);
    params.append('date_to', dateTo);
    params.append('limit', limit);
    params.append('page_num', 1);

    window.location.href = '<?php echo BASE_URL; ?>index.php?page=announcements&' + params.toString();
}

function goToPage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page_num', page);
    window.location.href = '<?php echo BASE_URL; ?>index.php?page=announcements&' + params.toString();
}

function changePerPage(limit) {
    const params = new URLSearchParams(window.location.search);
    params.set('limit', limit);
    params.set('page_num', 1);
    window.location.href = '<?php echo BASE_URL; ?>index.php?page=announcements&' + params.toString();
}

// Remove individual filter chip
document.querySelectorAll('.chip-remove').forEach(el => {
    el.addEventListener('click', function(e) {
        e.preventDefault();
        const filter = this.getAttribute('data-filter');
        if (filter === 'search') {
            document.getElementById('searchInput').value = '';
        } else if (filter === 'category') {
            document.getElementById('toolbarCategory').value = 'all';
        } else if (filter === 'date_from') {
            document.getElementById('popoverDateFrom').value = '';
        } else if (filter === 'date_to') {
            document.getElementById('popoverDateTo').value = '';
        }
        applyFilters();
    });
});

// Clear all filters
document.getElementById('clearAllFilters')?.addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('searchInput').value = '';
    document.getElementById('toolbarCategory').value = 'all';
    document.getElementById('popoverDateFrom').value = '';
    document.getElementById('popoverDateTo').value = '';
    applyFilters();
});

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
    document.getElementById('popoverDateFrom').value = '';
    document.getElementById('popoverDateTo').value = '';
    filterPopover.classList.remove('open');
    applyFilters();
});

// Search debounce
document.getElementById('searchInput')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});

// Category dropdown change
document.getElementById('toolbarCategory')?.addEventListener('change', applyFilters);

// ===== QUILL EDITORS =====
var createQuill = null;
var editQuill = null;

function initQuill() {
    var createEditorEl = document.getElementById('create_editor');
    if (createEditorEl && !createQuill) {
        createQuill = new Quill('#create_editor', {
            theme: 'snow',
            placeholder: 'Write your announcement details here...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link'],
                    ['clean']
                ]
            }
        });
    }

    var editEditorEl = document.getElementById('edit_editor');
    if (editEditorEl && !editQuill) {
        editQuill = new Quill('#edit_editor', {
            theme: 'snow',
            placeholder: 'Write your announcement details here...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link'],
                    ['clean']
                ]
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initQuill();
});

function openCreateModal() {
    document.getElementById('createModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    setTimeout(function() {
        if (!createQuill) initQuill();
        if (createQuill) createQuill.setContents([{ insert: '\n' }]);
    }, 200);
    selectedPhotos = [];
    updatePhotoPreviews();
    updateFileInput();
}

function openEditModal(id, title, content, category, images) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_category').value = category;
    document.getElementById('editModal').classList.add('active');
    document.body.style.overflow = 'hidden';

    setTimeout(function() {
        if (!editQuill) initQuill();
        if (editQuill) {
            if (content && content.trim() !== '' && content !== '<p><br></p>') {
                editQuill.root.innerHTML = content;
            } else {
                editQuill.setContents([{ insert: '\n' }]);
            }
        }
    }, 200);

    editCurrentImages = images || [];
    deleteImageIds = [];
    document.getElementById('delete_images').value = '';

    var gallery = document.getElementById('edit_image_gallery');
    gallery.innerHTML = '';
    if (editCurrentImages.length > 0) {
        editCurrentImages.forEach(function(img) {
            var div = document.createElement('div');
            div.className = 'current-image-item';
            div.innerHTML = `
                <img src="<?php echo BASE_URL; ?>${img.image_path}" class="w-full h-full object-cover rounded-xl">
                <div class="image-delete-btn" onclick="markImageForDelete(${img.id}, this)">
                    <i class="fas fa-trash-alt text-xs"></i>
                </div>
            `;
            gallery.appendChild(div);
        });
    } else {
        gallery.innerHTML = '<p class="text-gray-400 text-sm col-span-full text-center py-4 font-medium">No images yet</p>';
    }

    editSelectedPhotos = [];
    updateEditPhotoPreviews();
}

function closeCreateModal() {
    document.getElementById('createModal').classList.remove('active');
    document.body.style.overflow = '';
    selectedPhotos = [];
    updatePhotoPreviews();
    updateFileInput();
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
    document.body.style.overflow = '';
    editSelectedPhotos = [];
    deleteImageIds = [];
    updateEditPhotoPreviews();
}

document.getElementById('postForm')?.addEventListener('submit', function(e) {
    if (createQuill) document.getElementById('create_content_hidden').value = createQuill.root.innerHTML;
});

document.getElementById('editForm')?.addEventListener('submit', function(e) {
    if (editQuill) document.getElementById('edit_content_hidden').value = editQuill.root.innerHTML;
});

// ===== PHOTO HANDLING =====
var selectedPhotos = [], editSelectedPhotos = [], deleteImageIds = [], editCurrentImages = [], MAX_PHOTOS = 10;

function markImageForDelete(imageId, element) {
    if (confirm('Remove this image?')) {
        deleteImageIds.push(imageId);
        document.getElementById('delete_images').value = deleteImageIds.join(',');
        element.closest('.current-image-item').remove();
        editCurrentImages = editCurrentImages.filter(function(img) { return img.id != imageId; });
    }
}

var photoInput = document.getElementById('photoInput');
var photoPreviews = document.getElementById('photoPreviews');
var uploadArea = document.getElementById('uploadArea');
var photoCount = document.getElementById('photoCount');

function updatePhotoPreviews() {
    photoPreviews.innerHTML = '';
    selectedPhotos.forEach(function(photo, index) {
        var previewDiv = document.createElement('div');
        previewDiv.className = 'photo-preview-item';
        previewDiv.innerHTML = `
            <img src="${photo.data}" class="w-full h-full object-cover rounded-xl">
            <div class="photo-remove" onclick="removePhoto(${index})">
                <i class="fas fa-times"></i>
            </div>
        `;
        photoPreviews.appendChild(previewDiv);
    });
    photoCount.textContent = selectedPhotos.length + ' / ' + MAX_PHOTOS + ' photos selected';
}

function removePhoto(index) { selectedPhotos.splice(index, 1); updatePhotoPreviews(); updateFileInput(); }

function updateFileInput() {
    var dataTransfer = new DataTransfer();
    selectedPhotos.forEach(function(photo) { if(photo.file) dataTransfer.items.add(photo.file); });
    photoInput.files = dataTransfer.files;
}

function addPhotos(files) {
    for(var i = 0; i < files.length; i++) {
        var file = files[i];
        if(selectedPhotos.length >= MAX_PHOTOS) { alert('Maximum ' + MAX_PHOTOS + ' photos allowed'); break; }
        if(file.type && file.type.startsWith('image/')) {
            var reader = new FileReader();
            reader.onload = function(e) {
                selectedPhotos.push({ data: e.target.result, file: file });
                updatePhotoPreviews();
                updateFileInput();
            };
            reader.readAsDataURL(file);
        }
    }
}

uploadArea.addEventListener('click', function() { photoInput.click(); });
photoInput.addEventListener('change', function(e) { addPhotos(Array.from(e.target.files)); });
uploadArea.addEventListener('dragover', function(e) { e.preventDefault(); uploadArea.classList.add('border-emerald-500', 'bg-emerald-50'); });
uploadArea.addEventListener('dragleave', function(e) { uploadArea.classList.remove('border-emerald-500', 'bg-emerald-50'); });
uploadArea.addEventListener('drop', function(e) { e.preventDefault(); uploadArea.classList.remove('border-emerald-500', 'bg-emerald-50'); addPhotos(Array.from(e.dataTransfer.files)); });

var editPhotoInput = document.getElementById('editPhotoInput');
var editPhotoPreviews = document.getElementById('editPhotoPreviews');
var editUploadArea = document.getElementById('editUploadArea');

function updateEditPhotoPreviews() {
    editPhotoPreviews.innerHTML = '';
    editSelectedPhotos.forEach(function(photo, index) {
        var previewDiv = document.createElement('div');
        previewDiv.className = 'photo-preview-item';
        previewDiv.innerHTML = `
            <img src="${photo.data}" class="w-full h-full object-cover rounded-xl">
            <div class="photo-remove" onclick="removeEditPhoto(${index})">
                <i class="fas fa-times"></i>
            </div>
        `;
        editPhotoPreviews.appendChild(previewDiv);
    });
}

function removeEditPhoto(index) { editSelectedPhotos.splice(index, 1); updateEditPhotoPreviews(); updateEditFileInput(); }

function updateEditFileInput() {
    var dataTransfer = new DataTransfer();
    editSelectedPhotos.forEach(function(photo) { if(photo.file) dataTransfer.items.add(photo.file); });
    editPhotoInput.files = dataTransfer.files;
}

function addEditPhotos(files) {
    var currentTotal = editCurrentImages.length + editSelectedPhotos.length;
    for(var i = 0; i < files.length; i++) {
        var file = files[i];
        if(currentTotal + editSelectedPhotos.length >= MAX_PHOTOS) { alert('Maximum ' + MAX_PHOTOS + ' photos allowed'); break; }
        if(file.type && file.type.startsWith('image/')) {
            var reader = new FileReader();
            reader.onload = function(e) {
                editSelectedPhotos.push({ data: e.target.result, file: file });
                updateEditPhotoPreviews();
                updateEditFileInput();
            };
            reader.readAsDataURL(file);
        }
    }
}

editUploadArea.addEventListener('click', function() { editPhotoInput.click(); });
editPhotoInput.addEventListener('change', function(e) { addEditPhotos(Array.from(e.target.files)); });
editUploadArea.addEventListener('dragover', function(e) { e.preventDefault(); editUploadArea.classList.add('border-emerald-500', 'bg-emerald-50'); });
editUploadArea.addEventListener('dragleave', function(e) { editUploadArea.classList.remove('border-emerald-500', 'bg-emerald-50'); });
editUploadArea.addEventListener('drop', function(e) { e.preventDefault(); editUploadArea.classList.remove('border-emerald-500', 'bg-emerald-50'); addEditPhotos(Array.from(e.dataTransfer.files)); });

document.getElementById('createModal')?.addEventListener('click', function(e) { if(e.target === this) closeCreateModal(); });
document.getElementById('editModal')?.addEventListener('click', function(e) { if(e.target === this) closeEditModal(); });

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeCreateModal(); closeEditModal(); }
});
</script>

</body>
</html>