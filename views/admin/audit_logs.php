<?php
// views/admin/audit_logs.php - SIERRA AUDIT LOGS PAGE (READ-ONLY)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/helpers/Lang.php';
requireLogin();

// Audit Logs are read-only and reserved for the System Administrator.
// MENRO Staff, Barangay Officials, and citizens are never allowed to view them.
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "You are not permitted to view audit logs.";
    header("Location: " . BASE_URL . "index.php?page=dashboard");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// ------------------------------------------------------------
// HELPER: parse a User-Agent into a friendly device label
// e.g. "iPhone · Safari", "Windows 11 · Chrome", "Android Phone 14 · Chrome"
// ------------------------------------------------------------
function friendlyDeviceName($ua) {
    if (empty(trim($ua))) return null;
    $ua = ' ' . $ua . ' ';

    if (stripos($ua, 'iPhone') !== false) {
        $device = 'iPhone' . iosVersion($ua);
    } elseif (stripos($ua, 'iPad') !== false) {
        $device = 'iPad' . iosVersion($ua);
    } elseif (stripos($ua, 'Android') !== false) {
        $androidVer = '';
        if (preg_match('/Android\s([0-9.]+)/i', $ua, $m)) $androidVer = ' ' . $m[1];
        $device = (stripos($ua, 'Mobile') !== false ? 'Android Phone' : 'Android Tablet') . $androidVer;
    } elseif (stripos($ua, 'CrOS') !== false) {
        $device = 'Chromebook';
    } elseif (preg_match('/Windows NT 11/i', $ua)) {
        $device = 'Windows 11';
    } elseif (preg_match('/Windows NT 10\.0/i', $ua)) {
        $device = 'Windows 10/11';
    } elseif (preg_match('/Windows NT 6\.3/i', $ua)) {
        $device = 'Windows 8.1';
    } elseif (preg_match('/Windows NT 6\.1/i', $ua)) {
        $device = 'Windows 7';
    } elseif (stripos($ua, 'Windows') !== false) {
        $device = 'Windows PC';
    } elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) {
        $device = 'Mac';
    } elseif (stripos($ua, 'Linux') !== false) {
        $device = 'Linux Device';
    } else {
        $device = 'Unknown Device';
    }

    if (stripos($ua, 'SamsungBrowser') !== false) {
        $browser = 'Samsung Internet';
    } elseif (stripos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) {
        $browser = 'Opera';
    } elseif (stripos($ua, 'Chrome/') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (preg_match('/Version\/[\d.]+.*Safari/i', $ua)) {
        $browser = 'Safari';
    } elseif (stripos($ua, 'Trident/') !== false || stripos($ua, 'MSIE') !== false) {
        $browser = 'Internet Explorer';
    } else {
        $browser = null;
    }

    return $browser ? $device . ' · ' . $browser : $device;
}

function iosVersion($ua) {
    if (preg_match('/OS (\d+)[_\.](\d+)/', $ua, $m)) {
        return ' ' . $m[1] . '.' . $m[2];
    }
    return '';
}

// Get filter parameters
$action_filter = $_GET['action'] ?? 'all';
$user_filter = $_GET['user'] ?? '';
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$limit = 50;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = ["1=1"];
$params = [];

if($action_filter !== 'all') {
    $where[] = "a.action = :action";
    $params[':action'] = $action_filter;
}

if($status_filter !== 'all') {
    $where[] = "a.status = :status";
    $params[':status'] = $status_filter;
}

if(!empty($user_filter)) {
    $where[] = "a.user_id = :user_id";
    $params[':user_id'] = (int)$user_filter;
}

if(!empty($date_from)) {
    $where[] = "DATE(a.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if(!empty($date_to)) {
    $where[] = "DATE(a.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if(!empty($search)) {
    $where[] = "(a.description LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR a.status LIKE :search OR a.user_agent LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(' AND ', $where);

// Get total count
$count_sql = "SELECT COUNT(*) FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id WHERE $where_clause";
$stmt = $db->prepare($count_sql);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_logs = $stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Get logs
$sql = "SELECT a.*, 
               CONCAT(u.first_name, ' ', u.last_name) as user_name,
               u.email as user_email,
               u.user_type as user_role
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE $where_clause
        ORDER BY a.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($sql);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique actions for filter dropdown
$actions = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter dropdown
$users = $db->query("SELECT id, first_name, last_name, email FROM users ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$total_activities = $db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$today_activities = $db->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$unique_users = $db->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE user_id IS NOT NULL")->fetchColumn();

// Most common actions
$top_actions = $db->query("
    SELECT action, COUNT(*) as count 
    FROM activity_logs 
    GROUP BY action 
    ORDER BY count DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo t('Audit Logs - Sierra'); ?></title>
    <link href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/material-symbols.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/material-symbols.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/export-print.css'); ?>">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: #F7FBF9; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; background: transparent; }
        ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 16px; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #10A37F, #0D8568); border-radius: 16px; }
        * { scrollbar-width: thin; scrollbar-color: #10A37F #f1f5f9; }
        
        h1, h2, h3, h4, h5, h6 { font-weight: 700; letter-spacing: -0.02em; }

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

        .page-header { margin-bottom: 1.25rem; }
        @media (min-width: 640px) { .page-header { margin-bottom: 1.5rem; } }
        .page-title { font-size: 1.5rem; }
        @media (min-width: 640px) { .page-title { font-size: 1.875rem; } }
        
        .stat-card { 
            border-radius: 12px; 
            transition: all 0.2s ease; 
            border: 1px solid rgba(16, 163, 127, 0.08); 
            opacity: 0; 
            animation: slideUp 0.5s ease-out forwards; 
        }
        .stat-card:hover { 
            transform: translateY(-2px); 
            border-color: #10A37F; 
            box-shadow: 0 8px 20px -12px rgba(16, 163, 127, 0.15); 
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.2s; }
        
        .btn-primary {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
        }

        .table-container {
            background: white;
            border-radius: 12px;
            border: 1px solid #eef2f0;
            overflow: hidden;
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
        
        .action-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .action-Login { background: #D1FAE5; color: #065F46; }
        .action-Logout { background: #FEE2E2; color: #991B1B; }
        .action-UserRegistration { background: #DBEAFE; color: #1E40AF; }
        .action-ReportStatusChange { background: #FEF3C7; color: #92400E; }
        .action-Create { background: #E0E7FF; color: #3730A3; }
        .action-Update { background: #FCE7F3; color: #9D174D; }
        .action-Delete { background: #FEE2E2; color: #DC2626; }
        .action-default { background: #F3F4F6; color: #6B7280; }
        
        .role-badge-admin { background: #8B5CF6; color: white; }
        .role-badge-barangay { background: #10A37F; color: white; }
        .role-badge-citizen { background: #3B82F6; color: white; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 24px 64px -12px rgba(0,0,0,0.25);
        }
        
        .pagination-btn {
            padding: 8px 16px;
            border: 1px solid #E5E7EB;
            border-radius: 9999px;
            font-size: 0.875rem;
            color: #1F2937;
            text-decoration: none;
            transition: all 0.2s;
        }
        .pagination-btn:hover { background: #F0FDF4; border-color: #10A37F; }
        .pagination-active { background: #10A37F; color: white; border-color: #10A37F; }
        
        @media (max-width: 768px) {
            .ml-72 { margin-left: 0; }
        }
    </style>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
</head>
<body class="bg-[#F7FBF9] admin-data-page">

<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<div id="main-content" tabindex="-1" class="lg:ml-72 min-h-screen" role="main">
    <div class="main-container max-w-7xl mx-auto">
        
        <!-- Success/Error Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="mb-5 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 flex items-center gap-3 animate-slide-up">
                <i class="fas fa-check-circle text-green-500"></i>
                <span class="font-medium"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="mb-5 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 flex items-center gap-3 animate-slide-up">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <span class="font-medium"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 stat-cards">
            <div class="stat-card bg-white p-5">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1 font-semibold"><?php echo t('Total Activities'); ?></p>
                        <p class="text-2xl font-extrabold text-gray-800 tracking-tight"><?php echo number_format($total_activities); ?></p>
                    </div>
                    <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-chart-bar text-purple-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card bg-white p-5">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1 font-semibold"><?php echo t("Today's Activities"); ?></p>
                        <p class="text-2xl font-extrabold text-blue-600 tracking-tight"><?php echo number_format($today_activities); ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-calendar-day text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card bg-white p-5">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1 font-semibold"><?php echo t('Unique Users'); ?></p>
                        <p class="text-2xl font-extrabold text-emerald-600 tracking-tight"><?php echo number_format($unique_users); ?></p>
                    </div>
                    <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-users text-emerald-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card bg-white p-5">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1 font-semibold"><?php echo t('Logs Shown'); ?></p>
                        <p class="text-2xl font-extrabold text-amber-600 tracking-tight"><?php echo count($logs); ?></p>
                    </div>
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-list text-amber-600"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Actions Summary -->
        <?php if(!empty($top_actions)): ?>
        <div class="bg-white rounded-xl p-4 mb-6 border border-emerald-50 animate-slide-up">
            <h3 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                <i class="fas fa-chart-pie text-[#10A37F]"></i> <?php echo t('Most Common Actions'); ?>
            </h3>
            <div class="flex flex-wrap gap-3">
                <?php foreach($top_actions as $action): ?>
                <span class="action-badge action-<?php echo str_replace(' ', '', $action['action']); ?> action-default">
                    <?php echo htmlspecialchars($action['action']); ?> (<?php echo $action['count']; ?>)
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Filter Toolbar (Shared Design) -->
        <?php
        $ft_popover_count = (($date_from != '') ? 1 : 0) + (($date_to != '') ? 1 : 0) + ((!empty($user_filter)) ? 1 : 0);

        $active_filters = 0;
        if ($search != '') $active_filters++;
        if ($action_filter != 'all') $active_filters++;
        if ($status_filter != 'all') $active_filters++;
        if (!empty($user_filter)) $active_filters++;
        if ($date_from != '') $active_filters++;
        if ($date_to != '') $active_filters++;

        $ft = [
            'search_id'          => 'searchInput',
            'search_value'       => htmlspecialchars($search),
            'search_placeholder' => 'Search logs by description or user...',
            'results_text'       => 'Showing <strong>' . count($logs) . '</strong> of <strong>' . number_format($total_logs) . '</strong> log entries',
            'inline_selects'     => [
                [
                    'id'        => 'toolbarAction',
                    'value'     => $action_filter,
                    'min_width' => '140px',
                    'options'   => array_merge(['all' => 'All Actions'], array_combine($actions, $actions)),
                ],
                [
                    'id'        => 'toolbarStatus',
                    'value'     => $status_filter,
                    'min_width' => '140px',
                    'options'   => [
                        'all' => 'All Statuses',
                        'SUCCESS' => 'Success',
                        'FAILED' => 'Failed',
                        'UNAUTHORIZED_ATTEMPT' => 'Unauthorized'
                    ],
                ],
            ],
            'filter_by'          => [
                'active' => ($date_from != '' || $date_to != '' || !empty($user_filter)),
                'count'  => $ft_popover_count,
            ],
            'popover_fields'     => [
                ['kind' => 'select', 'id' => 'popoverUser', 'label' => 'User', 'value' => $user_filter, 'default' => '',
                 'options' => array_merge(['' => 'All Users'], array_reduce($users, function($carry, $u) {
                     $carry[$u['id']] = $u['first_name'] . ' ' . $u['last_name'];
                     return $carry;
                 }, []))
                ],
                ['kind' => 'date', 'id' => 'popoverDateFrom', 'label' => 'Date From', 'value' => $date_from, 'default' => ''],
                ['kind' => 'date', 'id' => 'popoverDateTo', 'label' => 'Date To', 'value' => $date_to, 'default' => ''],
            ],
            'active_filters'     => (int)$active_filters,
            'chips'              => array_filter([
                !empty($search) ? '<span class="filter-chip">"' . htmlspecialchars($search) . '" <span class="chip-remove" data-filter="search"><i class="fas fa-times"></i></span></span>' : null,
                ($action_filter != 'all') ? '<span class="filter-chip">' . htmlspecialchars($action_filter) . ' <span class="chip-remove" data-filter="action"><i class="fas fa-times"></i></span></span>' : null,
                ($status_filter != 'all') ? '<span class="filter-chip">' . ($status_filter == 'SUCCESS' ? 'Success' : ($status_filter == 'FAILED' ? 'Failed' : 'Unauthorized')) . ' <span class="chip-remove" data-filter="status"><i class="fas fa-times"></i></span></span>' : null,
                (!empty($user_filter)) ? '<span class="filter-chip">User: ' . htmlspecialchars(array_reduce($users, function($carry, $u) use ($user_filter) {
                    return $u['id'] == $user_filter ? ($u['first_name'] . ' ' . $u['last_name']) : $carry;
                }, 'Unknown')) . ' <span class="chip-remove" data-filter="user"><i class="fas fa-times"></i></span></span>' : null,
                ($date_from != '') ? '<span class="filter-chip">From ' . date('M d, Y', strtotime($date_from)) . ' <span class="chip-remove" data-filter="date_from"><i class="fas fa-times"></i></span></span>' : null,
                ($date_to != '') ? '<span class="filter-chip">To ' . date('M d, Y', strtotime($date_to)) . ' <span class="chip-remove" data-filter="date_to"><i class="fas fa-times"></i></span></span>' : null,
            ], fn($v) => $v !== null),
            'chips_clear_all'    => true,
            'chip_clear_map'     => [
                'search'    => ['el' => 'searchInput',      'clear' => ''],
                'action'    => ['el' => 'toolbarAction',    'clear' => 'all'],
                'status'    => ['el' => 'toolbarStatus',    'clear' => 'all'],
                'user'      => ['el' => 'popoverUser',      'clear' => ''],
                'date_from' => ['el' => 'popoverDateFrom',  'clear' => ''],
                'date_to'   => ['el' => 'popoverDateTo',    'clear' => ''],
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
        
        <!-- Logs Table -->
        <div class="table-container mb-6 animate-slide-up">
            <div class="table-section-header">
                <div class="table-section-title">
                    <h2><?php echo t('Audit Logs List'); ?></h2>
                    <p><?php echo t('System activities matching the current filters.'); ?></p>
                </div>
                <a href="?page=audit-logs-report<?php echo $date_from ? '&from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&to=' . urlencode($date_to) : ''; ?><?php echo $action_filter !== 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo !empty($user_filter) ? '&user=' . urlencode($user_filter) : ''; ?><?php echo $status_filter !== 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn-export-trigger">
                    <i class="fas fa-file-export"></i>
                    <span><?php echo t('Export'); ?></span>
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full audit-logs-table app-data-table">
                    <thead>
                        <tr class="border-b" style="background: linear-gradient(90deg,#F0FBF6 0%, #F7FFF9 100%);">
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase"><?php echo t('Timestamp'); ?></th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase"><?php echo t('User'); ?></th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase"><?php echo t('Role'); ?></th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase"><?php echo t('Action'); ?></th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase"><?php echo t('Module'); ?></th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase"><?php echo t('Status'); ?></th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase"><?php echo t('Details'); ?></th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase"><?php echo t('IP Address'); ?></th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase"><?php echo t('Device'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($logs) > 0): ?>
                            <?php foreach($logs as $log): 
                                $action_class = 'action-' . str_replace(' ', '', $log['action']);
                                if(!in_array($log['action'], ['Login', 'Logout', 'User Registration', 'Report Status Change', 'Create', 'Update', 'Delete'])) {
                                    $action_class = 'action-default';
                                }
                                
                                // Fall back to the snapshot columns if the user
                                // account has since been deleted.
                                $log_user_name  = $log['user_name']  ?: ($log['actor_name'] ?? null);
                                $log_user_email = $log['user_email'] ?: '';
                                $log_user_role  = $log['user_role'] ?: ($log['actor_role'] ?? null);

                                // Citizens are stored with a NULL/empty user_type
                                // (see roleFromUserType) — so any real user that
                                // isn't staff/admin/barangay is a citizen.
                                if (empty($log_user_role) && ($log['user_id'] || $log['actor_name'] || $log['user_name'])) {
                                    $log_user_role = 'citizen';
                                }
                                
                                // Affected module: prefer the stored value, then
                                // derive a sensible module from the action name.
                                $actionModuleMap = [
                                    'Login' => 'Auth', 'Logout' => 'Auth', 'User Registration' => 'Auth',
                                    'Password Reset' => 'Auth',
                                    'Create Report' => 'Reports', 'Update Report' => 'Reports',
                                    'Delete Report' => 'Reports', 'Cancel Report' => 'Reports',
                                    'Support Report' => 'Reports', 'Verify Report' => 'Reports',
                                    'Reject Report' => 'Reports', 'Resolve Report' => 'Reports',
                                    'Escalate Report' => 'Reports', 'Approve Escalation' => 'Reports',
                                    'Reject Escalation' => 'Reports', 'Status Change' => 'Reports',
                                    'Update Status' => 'Reports', 'Add Note' => 'Reports',
                                    'Evidence Upload' => 'Reports', 'Reclassify Impact' => 'Reports',
                                    'Create Staff Account' => 'Staff', 'Toggle User Status' => 'Users',
                                    'Update User Role' => 'Users', 'Delete User' => 'Users',
                                    'Create Category' => 'Categories', 'Update Category' => 'Categories',
                                    'Delete Category' => 'Categories', 'Toggle Category Status' => 'Categories',
                                    'Create Announcement' => 'Announcements',
                                    'Create Role' => 'Permissions', 'Update Role' => 'Permissions',
                                    'Delete Role' => 'Permissions', 'Update Permissions' => 'Permissions',
                                    'Update System Settings' => 'Settings',
                                    'Create Barangay' => 'Barangays', 'Update Barangay' => 'Barangays',
                                    'Delete Barangay' => 'Barangays',
                                    'Create Tag' => 'Tags', 'Update Tag' => 'Tags', 'Delete Tag' => 'Tags',
                                ];
                                $module = $log['target_module'] ?? ($actionModuleMap[$log['action']] ?? 'General');
                                
                                $role_class = '';
                                if(in_array($log_user_role, ['admin', 'menro_staff'], true)) $role_class = 'role-badge-admin';
                                elseif($log_user_role == 'barangay_personnel') $role_class = 'role-badge-barangay';
                                else $role_class = 'role-badge-citizen';

                                // Display names: 'citizen' is shown to end users as "Reporter".
                                $roleLabelMap = [
                                    'citizen'            => 'Reporter',
                                    'admin'              => 'Admin',
                                    'menro_staff'        => 'MENRO Staff',
                                    'barangay_personnel' => 'Barangay Official',
                                ];
                                $log_user_role_label = $roleLabelMap[$log_user_role]
                                    ?? ucfirst(str_replace('_', ' ', $log_user_role));
                            ?>
                            <tr class="border-b hover:bg-emerald-50/30 transition">
                                <td data-label="<?php echo t('Timestamp'); ?>" class="px-4 py-3 text-sm text-gray-600 font-medium whitespace-nowrap">
                                    <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                </td>
                                <td data-label="<?php echo t('User'); ?>" class="px-4 py-3">
                                    <?php if($log_user_name): ?>
                                    <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($log_user_name); ?></p>
                                    <?php if($log_user_email): ?><p class="text-xs text-gray-400"><?php echo htmlspecialchars($log_user_email); ?></p><?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-gray-400 text-sm"><?php echo t('System'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php echo t('Role'); ?>" class="px-4 py-3">
                                    <?php if($log_user_role): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold text-white <?php echo $role_class; ?>">
                                        <?php echo htmlspecialchars($log_user_role_label); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-gray-400 text-xs">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php echo t('Action'); ?>" class="px-4 py-3">
                                    <span class="action-badge <?php echo $action_class; ?>">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td data-label="<?php echo t('Module'); ?>" class="px-4 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-[10px] font-bold uppercase tracking-wide">
                                        <?php echo htmlspecialchars($module); ?>
                                    </span>
                                </td>
                                <td data-label="<?php echo t('Status'); ?>" class="px-4 py-3">
                                    <?php $log_status = $log['status'] ?? 'SUCCESS'; ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide
                                        <?php
                                        if ($log_status === 'FAILED') { echo 'bg-red-100 text-red-700'; }
                                        elseif ($log_status === 'UNAUTHORIZED_ATTEMPT') { echo 'bg-orange-100 text-orange-700'; }
                                        else { echo 'bg-emerald-100 text-emerald-700'; }
                                        ?>">
                                        <?php echo htmlspecialchars($log_status); ?>
                                    </span>
                                </td>
                                <td data-label="<?php echo t('Details'); ?>" class="px-4 py-3 text-sm text-gray-600 max-w-xs">
                                    <?php echo htmlspecialchars($log['description'] ?: '—'); ?>
                                </td>
                                <td data-label="<?php echo t('IP Address'); ?>" class="px-4 py-3 text-sm text-gray-500">
                                    <?php echo htmlspecialchars($log['ip_address'] ?: '—'); ?>
                                </td>
                                <td data-label="<?php echo t('Device'); ?>" class="px-4 py-3 text-sm text-gray-500">
                                    <?php
                                        $ua = trim($log['user_agent'] ?? '');
                                        echo $ua ? htmlspecialchars(friendlyDeviceName($ua)) : '—';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center">
                                    <div class="empty-state">
                                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                                            <i class="fas fa-history text-xl sm:text-2xl text-gray-400"></i>
                                        </div>
                                        <h3 class="font-semibold text-gray-700 mb-1 sm:mb-2 text-base sm:text-lg"><?php echo t('No audit logs found'); ?></h3>
                                        <p class="text-gray-400 text-xs sm:text-sm"><?php echo t('Try adjusting your filters'); ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="flex justify-center gap-2 animate-slide-up">
            <?php if($page > 1): ?>
            <a href="?page=audit-logs&page_num=<?php echo $page-1; ?>&action=<?php echo $action_filter; ?>&user=<?php echo $user_filter; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn"><i class="fas fa-chevron-left mr-1"></i><?php echo t('Prev'); ?></a>
            <?php endif; ?>
            
            <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="?page=audit-logs&page_num=<?php echo $i; ?>&action=<?php echo $action_filter; ?>&user=<?php echo $user_filter; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn <?php echo $page == $i ? 'pagination-active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
            <a href="?page=audit-logs&page_num=<?php echo $page+1; ?>&action=<?php echo $action_filter; ?>&user=<?php echo $user_filter; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn"><?php echo t('Next'); ?><i class="fas fa-chevron-right ml-1"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
// Audit Logs Filter Functions
function applyFilters() {
    const search = document.getElementById('searchInput')?.value || '';
    const action = document.getElementById('toolbarAction')?.value || 'all';
    const status = document.getElementById('toolbarStatus')?.value || 'all';
    const user = document.getElementById('popoverUser')?.value || '';
    const dateFrom = document.getElementById('popoverDateFrom')?.value || '';
    const dateTo = document.getElementById('popoverDateTo')?.value || '';
    
    const params = new URLSearchParams({
        page: 'audit-logs',
        search: search,
        action: action,
        status: status,
        user: user,
        date_from: dateFrom,
        date_to: dateTo
    });
    
    window.location.href = 'index.php?' + params.toString();
}
</script>

<script src="<?php echo BASE_URL; ?>assets/js/modal-a11y.js"></script>
</body>
</html>
