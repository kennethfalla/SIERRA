<?php
// views/admin/manage_users.php - COMPLETE USER MANAGEMENT SYSTEM
// Three tabs: Citizens, Barangay Personnel, MENRO Staff
// Full CRUD operations with role-based access control
// Features: Create Staff with Temp Password, View Profile, Activate/Suspend, Delete

require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SecurityHelper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SettingsHelper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/PermissionHelper.php';
requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$userModel = new User($db);
$barangayModel = new Barangay($db);
$activityLog = new ActivityLog($db);

// ============================================================
// HANDLE POST ACTIONS
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    // Deactivate / Activate
    if($action === 'deactivate' || $action === 'activate') {
        $status = ($action === 'activate') ? 1 : 0;
        $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        if($stmt->execute([$status, $user_id])) {
            $status_text = $status ? 'Activated' : 'Deactivated';
            $name_stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
            $name_stmt->execute([$user_id]);
            $target_name = $name_stmt->fetchColumn() ?: '#' . $user_id;
            $activityLog->log($_SESSION['user_id'], $status ? 'Activate User' : 'Deactivate User', "$status_text account of $target_name", null, 'Users');
            $_SESSION['success'] = "User account has been " . ($status ? 'activated' : 'deactivated') . ".";
        } else {
            $_SESSION['error'] = "Failed to update user status.";
        }
        header("Location: " . BASE_URL . "index.php?page=manage-users&tab=" . ($_GET['tab'] ?? 'citizens'));
        exit();
    }
    
    // Delete user (only for non-admin users)
    if($action === 'delete') {
        $check = $db->prepare("SELECT role, CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
        $check->execute([$user_id]);
        $user = $check->fetch(PDO::FETCH_ASSOC);

        // Delete permission depends on the target account type:
        // citizens are managed under User Management, staff under Staff Management.
        $required_key = ($user && $user['role'] === 'citizen') ? 'can_manage_users' : 'can_manage_staff';
        if (!PermissionHelper::userHasPermission($required_key)) {
            $_SESSION['error'] = "You are not permitted to delete users.";
            header("Location: " . BASE_URL . "index.php?page=manage-users&tab=" . ($_GET['tab'] ?? 'citizens'));
            exit();
        }

        // Prevent deleting own account
        if($user_id == $_SESSION['user_id']) {
            $_SESSION['error'] = "You cannot delete your own account.";
            header("Location: " . BASE_URL . "index.php?page=manage-users&tab=" . ($_GET['tab'] ?? 'citizens'));
            exit();
        }
        
        if($user && $user['role'] !== 'admin') {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            if($stmt->execute([$user_id])) {
                $target_name = $user['full_name'] ?: '#' . $user_id;
                $activityLog->log($_SESSION['user_id'], 'Delete User', "Permanently deleted account of $target_name", null, 'Users');
                $_SESSION['success'] = "User account has been permanently deleted.";
            } else {
                $_SESSION['error'] = "Failed to delete user.";
            }
        } else {
            $_SESSION['error'] = "Cannot delete admin accounts.";
        }
        header("Location: " . BASE_URL . "index.php?page=manage-users&tab=" . ($_GET['tab'] ?? 'citizens'));
        exit();
    }
}

// ============================================================
// GET FILTER PARAMETERS
// ============================================================
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'citizens';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$barangay_filter = isset($_GET['barangay']) ? (int)$_GET['barangay'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// ============================================================
// FETCH USERS FROM DATABASE
// ============================================================
$all_users_data = [];
$query = "SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.user_type, u.barangay_id, 
                 u.contact_number, u.is_active, u.created_at, u.job_title,
                 u.is_resident, u.non_resident_address, u.profile_picture,
                 b.name as barangay_name 
          FROM users u 
          LEFT JOIN barangays b ON u.barangay_id = b.id 
          ORDER BY u.created_at DESC";
$all_users = $db->query($query);
while($user = $all_users->fetch(PDO::FETCH_ASSOC)) {
    $user['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $all_users_data[] = $user;
}

// ============================================================
// FILTER FUNCTION
// ============================================================
function applyFilters($users, $search_query, $barangay_filter, $status_filter) {
    $filtered = $users;
    
    if (!empty($search_query)) {
        $filtered = array_filter($filtered, function($user) use ($search_query) {
            return stripos($user['full_name'] ?? '', $search_query) !== false || 
                   stripos($user['email'] ?? '', $search_query) !== false ||
                   stripos($user['contact_number'] ?? '', $search_query) !== false ||
                   stripos($user['barangay_name'] ?? '', $search_query) !== false ||
                   stripos($user['job_title'] ?? '', $search_query) !== false;
        });
    }
    
    if ($barangay_filter > 0) {
        $filtered = array_filter($filtered, function($user) use ($barangay_filter) {
            return ($user['barangay_id'] ?? 0) == $barangay_filter;
        });
    }
    
    if ($status_filter !== '') {
        $is_active = ($status_filter === 'active') ? 1 : 0;
        $filtered = array_filter($filtered, function($user) use ($is_active) {
            return $user['is_active'] == $is_active;
        });
    }
    
    return $filtered;
}

// ============================================================
// CATEGORIZE USERS BY ROLE
// ============================================================
$citizens = array_filter($all_users_data, function($user) {
    return $user['role'] == 'citizen';
});

$barangay_personnel = array_filter($all_users_data, function($user) {
    return $user['role'] == 'barangay_official';
});

$menro_staff = array_filter($all_users_data, function($user) {
    return $user['role'] == 'admin';
});

// Apply filters to each group
$filtered_citizens = applyFilters($citizens, $search_query, $barangay_filter, $status_filter);
$filtered_barangay = applyFilters($barangay_personnel, $search_query, $barangay_filter, $status_filter);
$filtered_menro = applyFilters($menro_staff, $search_query, $barangay_filter, $status_filter);

// ============================================================
// STATISTICS
// ============================================================
$total_citizens = count($citizens);
$total_barangay = count($barangay_personnel);
$total_menro = count($menro_staff);

$active_citizens = count(array_filter($citizens, fn($u) => $u['is_active'] == 1));
$active_barangay = count(array_filter($barangay_personnel, fn($u) => $u['is_active'] == 1));
$active_menro = count(array_filter($menro_staff, fn($u) => $u['is_active'] == 1));

// ============================================================
// DETERMINE DISPLAY DATA BASED ON ACTIVE TAB
// ============================================================
$display_users = [];
$display_count = 0;
$display_total = 0;
$tab_label = '';
$show_barangay_filter = true;
$show_status_filter = true;
$show_create_btn = false;
$create_role = '';
$create_label = '';

if ($active_tab === 'citizens') {
    $display_users = $filtered_citizens;
    $display_total = $total_citizens;
    $display_count = count($display_users);
    $tab_label = 'Citizens';
    $show_barangay_filter = true;
    $show_status_filter = true;
    $show_create_btn = false;
    $create_role = '';
    $create_label = '';
} elseif ($active_tab === 'barangay') {
    $display_users = $filtered_barangay;
    $display_total = $total_barangay;
    $display_count = count($display_users);
    $tab_label = 'Barangay Personnel';
    $show_barangay_filter = true;
    $show_status_filter = true;
    $show_create_btn = true;
    $create_role = 'barangay_personnel'; // default User Type when opening the modal from this tab
    $create_label = 'New Barangay Personnel';
} elseif ($active_tab === 'menro') {
    $display_users = $filtered_menro;
    $display_total = $total_menro;
    $display_count = count($display_users);
    $tab_label = 'MENRO Staff';
    $show_barangay_filter = false;
    $show_status_filter = true;
    $show_create_btn = true;
    $create_role = 'menro_staff'; // default User Type when opening the modal from this tab
    $create_label = 'New MENRO Staff';
}

// ============================================================
// GET BARANGAY LIST FOR FILTERS
// ============================================================
$barangays = $barangayModel->getAll();
$barangay_list = [];
while($brgy = $barangays->fetch(PDO::FETCH_ASSOC)) {
    $barangay_list[] = $brgy;
}

// ============================================================
// GET ROLES FOR THE "Role" DROPDOWN (Create Role feature)
// ============================================================
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SettingsHelper.php';
$role_list = SettingsHelper::getAllRoles(); // [{id, title, description, is_system}, ...]

// ============================================================
// GENERATE CSRF TOKEN
// ============================================================
$csrf_token = InputSanitizer::generateCsrfToken();

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function getRoleBadge($user_type, $job_title = '') {
    if ($user_type === 'admin') {
        return '<span class="role-badge role-admin"><i class="fas fa-crown mr-1.5"></i>Admin' . ($job_title ? ' · ' . htmlspecialchars($job_title) : '') . '</span>';
    } elseif ($user_type === 'menro_staff') {
        return '<span class="role-badge role-admin"><i class="fas fa-crown mr-1.5"></i>MENRO Staff' . ($job_title ? ' · ' . htmlspecialchars($job_title) : '') . '</span>';
    } elseif ($user_type === 'barangay_personnel') {
        return '<span class="role-badge role-barangay"><i class="fas fa-landmark mr-1.5"></i>Barangay Personnel</span>';
    } else {
        return '<span class="role-badge role-citizen"><i class="fas fa-user mr-1.5"></i>Citizen</span>';
    }
}
// Use canonical helper functions from includes/functions.php for status badges and phone formatting
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Users - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
            background: transparent;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 20px;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #059669, #047857);
            border-radius: 20px;
        }
        * {
            scrollbar-width: thin;
            scrollbar-color: #059669 #f1f5f9;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Manrope', sans-serif;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        
        body {
            background: #F5FBF6;
        }
        
        @media (max-width: 768px) {
            .ml-72 {
                margin-left: 0 !important;
                width: 100%;
                padding: 0;
            }
        }
        
        /* ===== BRANDING BUTTONS ===== */
        .btn-primary {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            transition: all 0.2s ease;
            border-radius: 10px;
            color: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            transition: all 0.2s ease;
            border-radius: 10px;
            color: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 1.5px solid #e5e7eb;
            transition: all 0.2s ease;
            border-radius: 10px;
            color: #4b5563;
            font-weight: 500;
            cursor: pointer;
        }
        .btn-outline:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }
        
        /* ===== STAT CARDS ===== */
        .stat-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(16, 163, 127, 0.08);
            padding: 1.25rem 1rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0;
            animation: slideUp 0.5s ease-out forwards;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            border-color: #10A37F;
            box-shadow: 0 12px 24px -8px rgba(16, 163, 127, 0.12);
        }
        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: #1a2e1a;
            letter-spacing: -0.02em;
        }
        @media (min-width: 640px) {
            .stat-card .stat-value {
                font-size: 2rem;
            }
        }
        .stat-card .stat-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #8aa38a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 0.15rem;
        }
        @media (min-width: 640px) {
            .stat-card .stat-label {
                font-size: 0.75rem;
            }
        }
        .stat-card .stat-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        @media (min-width: 640px) {
            .stat-card .stat-icon {
                width: 3rem;
                height: 3rem;
            }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        
        /* ===== TABS ===== */
        .tab-active {
            border-bottom: 3px solid #10A37F;
            color: #10A37F;
            font-weight: 700;
        }
        .tab-inactive {
            color: #6B7280;
            border-bottom: 3px solid transparent;
            font-weight: 500;
        }
        .tab-inactive:hover {
            color: #10A37F;
            border-bottom-color: #10A37F;
        }
        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 700;
            background: #e5e7eb;
            color: #4b5563;
        }
        .tab-active .tab-badge {
            background: #10A37F;
            color: white;
        }
        
        /* ===== ROLE BADGES ===== */
        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 14px;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
            letter-spacing: 0.01em;
        }
        .role-badge .fa, .role-badge .fas { font-size: 0.6rem; }
        .role-admin {
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
            color: white;
        }
        .role-barangay {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            color: white;
        }
        .role-citizen {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: white;
        }
        
        /* ===== STATUS BADGES ===== */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 14px;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
            letter-spacing: 0.01em;
        }
        .status-badge .fa, .status-badge .fas { font-size: 0.5rem; }
        .status-active {
            background: #D1FAE5;
            color: #065F46;
        }
        .status-active .fa-circle { color: #10B981; }
        .status-inactive {
            background: #FEE2E2;
            color: #991B1B;
        }
        .status-inactive .fa-circle { color: #EF4444; }
        
        /* ===== FILTER CARD ===== */
        .filter-card {
            background: white;
            border-radius: 12px;
            border: 1px solid rgba(16, 163, 127, 0.08);
            padding: 1.25rem;
        }
        
        /* ===== TABLE ===== */
        .table-container {
            background: white;
            border-radius: 12px;
            border: 1px solid rgba(16, 163, 127, 0.08);
            overflow: hidden;
        }
        .table-container thead th {
            background: #F5FBF6;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #8aa38a;
            padding: 0.75rem 1.25rem;
        }
        .table-container tbody td {
            padding: 0.75rem 1.25rem;
            font-size: 0.875rem;
            border-bottom: 1px solid #f0f4f2;
        }
        .table-container tbody tr:hover {
            background: #f9fcfb;
        }
        
        /* ===== MODAL ===== */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px -12px rgba(0,0,0,0.25);
        }
        .modal-header {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            padding: 1rem 1.5rem;
            border-radius: 16px 16px 0 0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .modal-header h2 {
            color: white;
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .modal-header .close-btn {
            color: rgba(255,255,255,0.7);
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s;
        }
        .modal-header .close-btn:hover {
            color: white;
        }
        
        /* ===== MAIN CONTAINER ===== */
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
        
        /* ===== PAGE HEADER ===== */
        .page-header {
            margin-bottom: 1.25rem;
        }
        @media (min-width: 640px) {
            .page-header { margin-bottom: 1.5rem; }
        }
        .page-header .header-icon {
            width: 2rem;
            height: 2rem;
            background: rgba(16, 163, 127, 0.1);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .page-header .header-icon i {
            color: #10A37F;
            font-size: 0.875rem;
        }
        .page-header .header-label {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #10A37F;
        }
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a2e1a;
        }
        @media (min-width: 640px) {
            .page-title { font-size: 1.875rem; }
        }
        .page-subtitle {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
        }
        @media (min-width: 640px) {
            .empty-state { padding: 3rem 2rem; }
        }
        
        /* ===== ACTION BUTTONS ===== */
        .action-btn {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.15s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .action-btn:hover {
            transform: scale(1.05);
        }
        .action-btn-view {
            background: #E0E7FF;
            color: #3730A3;
        }
        .action-btn-view:hover {
            background: #C7D2FE;
        }
        .action-btn-suspend {
            background: #FEF3C7;
            color: #92400E;
        }
        .action-btn-suspend:hover {
            background: #FDE68A;
        }
        .action-btn-activate {
            background: #D1FAE5;
            color: #065F46;
        }
        .action-btn-activate:hover {
            background: #A7F3D0;
        }
        .action-btn-delete {
            background: #FEE2E2;
            color: #991B1B;
        }
        .action-btn-delete:hover {
            background: #FECACA;
        }
        .action-btn-disabled {
            background: #F3F4F6;
            color: #9CA3AF;
            cursor: not-allowed;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .filter-card .flex-wrap {
                flex-direction: column;
                gap: 10px;
            }
            .filter-card .flex-wrap > div {
                width: 100%;
            }
            .table-container {
                overflow-x: auto;
            }
            table {
                min-width: 700px;
            }
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<div class="ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">
        
        <!-- ===== PAGE HEADER ===== -->
        <div class="page-header">
            <div class="flex items-center gap-2 mb-2">
                <div class="header-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <span class="header-label">User Management</span>
            </div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="page-title">Manage Users</h1>
                    <p class="page-subtitle">Manage citizens, barangay personnel, and MENRO staff accounts</p>
                </div>
                <?php if($show_create_btn): ?>
                <button onclick="openCreateModal('<?php echo $create_role; ?>')" 
                        class="btn-primary px-5 py-2.5 text-white font-semibold flex items-center gap-2 shadow-sm text-sm">
                    <i class="fas fa-plus-circle"></i>
                    <?php echo $create_label; ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ===== FLASH MESSAGES ===== -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 flex items-center gap-2 text-sm">
                <i class="fas fa-check-circle text-green-500"></i>
                <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 flex items-center gap-2 text-sm">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['errors']) && is_array($_SESSION['errors'])): ?>
            <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach($_SESSION['errors'] as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['errors']); ?>
        <?php endif; ?>
        
        <!-- ===== STATISTICS CARDS ===== -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="stat-label">Citizens</p>
                        <p class="stat-value"><?php echo $total_citizens; ?></p>
                        <p class="text-xs text-gray-400 mt-1 font-medium">
                            <span class="text-emerald-600"><?php echo $active_citizens; ?> active</span>
                        </p>
                    </div>
                    <div class="stat-icon bg-blue-50">
                        <i class="fas fa-user-friends text-blue-500 text-base"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="stat-label">Barangay Personnel</p>
                        <p class="stat-value"><?php echo $total_barangay; ?></p>
                        <p class="text-xs text-gray-400 mt-1 font-medium">
                            <span class="text-emerald-600"><?php echo $active_barangay; ?> active</span>
                        </p>
                    </div>
                    <div class="stat-icon bg-emerald-50">
                        <i class="fas fa-landmark text-emerald-500 text-base"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="stat-label">MENRO Staff</p>
                        <p class="stat-value"><?php echo $total_menro; ?></p>
                        <p class="text-xs text-gray-400 mt-1 font-medium">
                            <span class="text-emerald-600"><?php echo $active_menro; ?> active</span>
                        </p>
                    </div>
                    <div class="stat-icon bg-purple-50">
                        <i class="fas fa-crown text-purple-500 text-base"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ===== TABS ===== -->
        <div class="border-b border-emerald-100 mb-5">
            <nav class="flex flex-wrap gap-1 sm:gap-0 sm:space-x-8" aria-label="User tabs">
                <a href="<?php echo BASE_URL; ?>index.php?page=manage-users&tab=citizens<?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo $barangay_filter > 0 ? '&barangay='.$barangay_filter : ''; ?><?php echo $status_filter !== '' ? '&status='.$status_filter : ''; ?>" 
                   class="px-3 sm:px-1 py-4 text-sm transition-all duration-200 flex items-center gap-2 <?php echo $active_tab == 'citizens' ? 'tab-active' : 'tab-inactive'; ?>">
                    <i class="fas fa-users"></i>
                    Citizens
                    <span class="tab-badge"><?php echo $total_citizens; ?></span>
                </a>
                <a href="<?php echo BASE_URL; ?>index.php?page=manage-users&tab=barangay<?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo $barangay_filter > 0 ? '&barangay='.$barangay_filter : ''; ?><?php echo $status_filter !== '' ? '&status='.$status_filter : ''; ?>" 
                   class="px-3 sm:px-1 py-4 text-sm transition-all duration-200 flex items-center gap-2 <?php echo $active_tab == 'barangay' ? 'tab-active' : 'tab-inactive'; ?>">
                    <i class="fas fa-landmark"></i>
                    Barangay Personnel
                    <span class="tab-badge"><?php echo $total_barangay; ?></span>
                </a>
                <a href="<?php echo BASE_URL; ?>index.php?page=manage-users&tab=menro<?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo $barangay_filter > 0 ? '&barangay='.$barangay_filter : ''; ?><?php echo $status_filter !== '' ? '&status='.$status_filter : ''; ?>" 
                   class="px-3 sm:px-1 py-4 text-sm transition-all duration-200 flex items-center gap-2 <?php echo $active_tab == 'menro' ? 'tab-active' : 'tab-inactive'; ?>">
                    <i class="fas fa-crown"></i>
                    MENRO Staff
                    <span class="tab-badge"><?php echo $total_menro; ?></span>
                </a>
            </nav>
        </div>
        
        <!-- ===== FILTERS ===== -->
        <div class="filter-card mb-6">
            <form method="GET" action="<?php echo BASE_URL; ?>index.php" id="filterForm" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="page" value="manage-users">
                <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
                
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs text-gray-500 mb-1 font-semibold">Search</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Search by name, email, phone..." 
                               class="w-full pl-11 pr-4 py-2.5 border border-gray-200 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition text-sm">
                    </div>
                </div>
                
                <?php if($show_barangay_filter): ?>
                <div class="w-44">
                    <label class="block text-xs text-gray-500 mb-1 font-semibold">Barangay</label>
                    <select name="barangay" id="barangaySelect" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none bg-white text-sm">
                        <option value="0">All Barangays</option>
                        <?php foreach($barangay_list as $brgy): ?>
                        <option value="<?php echo $brgy['id']; ?>" <?php echo $barangay_filter == $brgy['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($brgy['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if($show_status_filter): ?>
                <div class="w-36">
                    <label class="block text-xs text-gray-500 mb-1 font-semibold">Status</label>
                    <select name="status" id="statusSelect" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none bg-white text-sm">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="flex gap-2">
                    <a href="<?php echo BASE_URL; ?>index.php?page=manage-users&tab=<?php echo $active_tab; ?>" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition text-sm">
                        <i class="fas fa-times mr-2"></i>Reset
                    </a>
                </div>
            </form>
            
            <div class="mt-4 pt-3 border-t border-emerald-50">
                <p class="text-sm text-gray-500 font-medium">
                    <i class="fas fa-chart-line mr-1 text-[#10A37F]"></i>
                    Showing <span class="font-bold text-gray-700"><?php echo $display_count; ?></span> of 
                    <span class="font-bold text-gray-700"><?php echo $display_total; ?></span> <?php echo strtolower($tab_label); ?>
                </p>
            </div>
        </div>
        
        <!-- ===== USERS TABLE ===== -->
        <div class="table-container">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left">User</th>
                            <th class="text-left">Contact</th>
                            <?php if($active_tab === 'barangay' || $active_tab === 'citizens'): ?>
                            <th class="text-left">Barangay</th>
                            <?php endif; ?>
                            <?php if($active_tab === 'menro'): ?>
                            <th class="text-left">Job Title</th>
                            <?php endif; ?>
                            <th class="text-left">Role</th>
                            <th class="text-left">Status</th>
                            <th class="text-left">Registered</th>
                            <th class="text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($display_users) > 0): ?>
                            <?php foreach($display_users as $user): ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-100 to-emerald-200 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            <?php if(!empty($user['profile_picture'])): ?>
                                                <img src="<?php echo BASE_URL . $user['profile_picture']; ?>" alt="Profile" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i class="fas <?php echo in_array($user['user_type'] ?? '', ['admin', 'menro_staff']) ? 'fa-crown' : (($user['user_type'] ?? '') == 'barangay_personnel' ? 'fa-landmark' : 'fa-user'); ?> text-[#10A37F] text-sm"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($user['full_name']); ?></p>
                                            <p class="text-xs text-gray-400 font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-1.5">
                                        <i class="fas fa-phone-alt text-gray-400 text-xs"></i>
                                        <span class="text-sm text-gray-600 font-medium"><?php echo formatPhoneNumber($user['contact_number']); ?></span>
                                    </div>
                                </td>
                                <?php if($active_tab === 'barangay' || $active_tab === 'citizens'): ?>
                                <td>
                                    <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($user['barangay_name'] ?? '—'); ?></span>
                                </td>
                                <?php endif; ?>
                                <?php if($active_tab === 'menro'): ?>
                                <td>
                                    <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($user['job_title'] ?? '—'); ?></span>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <?php echo getRoleBadge($user['user_type'] ?? null, $user['job_title'] ?? ''); ?>
                                </td>
                                <td>
                                    <?php echo getStatusBadge($user['is_active']); ?>
                                </td>
                                <td>
                                    <span class="text-sm text-gray-500 font-medium"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <!-- View Profile -->
                                        <button onclick="viewProfile(<?php echo $user['id']; ?>)" 
                                                class="action-btn action-btn-view" title="View Profile">
                                            <i class="fas fa-eye text-xs"></i>
                                        </button>
                                        
                                        <?php if($user['role'] !== 'admin'): ?>
                                            <?php if($user['is_active'] == 1): ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Deactivate this account? The user will lose access.')">
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="action-btn action-btn-suspend" title="Suspend Account">
                                                    <i class="fas fa-user-slash text-xs"></i>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Activate this account? The user will regain access.')">
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="action-btn action-btn-activate" title="Activate Account">
                                                    <i class="fas fa-user-check text-xs"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="inline" onsubmit="return confirm('Permanently delete this account? This cannot be undone.')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="action-btn action-btn-delete" title="Delete Account">
                                                    <i class="fas fa-trash-alt text-xs"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="action-btn action-btn-disabled" title="Admin accounts are protected">
                                                <i class="fas fa-lock text-xs"></i> Protected
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo ($active_tab === 'barangay' || $active_tab === 'citizens') ? 8 : 7; ?>" class="text-center py-12">
                                    <div class="empty-state">
                                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                                            <i class="fas fa-users-slash text-xl sm:text-2xl text-gray-400"></i>
                                        </div>
                                        <h3 class="font-semibold text-gray-700 mb-1 text-base">No <?php echo strtolower($tab_label); ?> found</h3>
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
</div>

<!-- ============================================================ -->
<!-- CREATE STAFF MODAL -->
<!-- ============================================================ -->
<div id="createModal" class="modal-overlay" onclick="if(event.target===this) closeCreateModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="flex justify-between items-center">
                <h2>
                    <i class="fas fa-user-plus"></i>
                    <span id="createModalTitle">Create Staff Account</span>
                </h2>
                <button onclick="closeCreateModal()" class="close-btn">&times;</button>
            </div>
        </div>
        
        <form method="POST" action="<?php echo BASE_URL; ?>controllers/AdminController.php" class="p-6" id="createStaffForm">
            <input type="hidden" name="action" value="create_user">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" required 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition text-sm"
                           placeholder="Juan">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" required 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition text-sm"
                           placeholder="Dela Cruz">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition text-sm"
                           placeholder="user@example.com">
                    <p class="text-xs text-gray-400 mt-1 font-medium">Email will be used for login</p>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Contact Number <span class="text-red-500">*</span></label>
                    <input type="tel" name="contact_number" required 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition text-sm"
                           placeholder="09123456789" pattern="09[0-9]{9}">
                    <p class="text-xs text-gray-400 mt-1 font-medium">11-digit number starting with 09</p>
                </div>

                <!-- User Type -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">User Type <span class="text-red-500">*</span></label>
                    <select name="user_type" id="createUserType" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none bg-white text-sm"
                            onchange="onUserTypeChange()">
                        <option value="barangay_personnel">Barangay Personnel</option>
                        <option value="menro_staff">MENRO Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <!-- Role (dynamic, from Permission Settings -> Create Role) -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Role <span class="text-red-500">*</span></label>
                    <select name="role_id" id="createRoleId" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none bg-white text-sm">
                        <option value="">Select Role</option>
                        <?php foreach($role_list as $r): ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1 font-medium">
                        Controls what this account can do. Manage roles in Settings &rarr; Permissions.
                    </p>
                </div>

                <!-- Job Title (for MENRO Staff / Admin) -->
                <div id="jobTitleField" class="md:col-span-2" style="display: none;">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Job Title</label>
                    <input type="text" name="job_title" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition text-sm"
                           placeholder="e.g., Environmental Officer">
                </div>
                
                <!-- Barangay selection (only for User Type = Barangay Personnel) -->
                <div id="barangayField" class="md:col-span-2" style="display: none;">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Assigned Barangay <span class="text-red-500">*</span></label>
                    <select name="barangay_id" id="createBarangayId" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none bg-white text-sm">
                        <option value="">Select Barangay</option>
                        <?php foreach($barangay_list as $brgy): ?>
                        <option value="<?php echo $brgy['id']; ?>"><?php echo htmlspecialchars($brgy['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mt-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                    <div class="text-sm text-blue-800">
                        <p class="font-extrabold mb-1">Account Setup:</p>
                        <ul class="list-disc list-inside space-y-1 text-xs font-medium">
                            <li>A <strong>temporary password</strong> will be generated automatically</li>
                            <li>The user will receive their credentials via <strong>SMS</strong></li>
                            <li>They will be required to <strong>change their password</strong> on first login</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 btn-primary py-3 rounded-xl font-semibold">
                    <i class="fas fa-check-circle mr-2"></i> Create Account
                </button>
                <button type="button" onclick="closeCreateModal()" class="px-6 py-3 border border-gray-300 rounded-xl hover:bg-gray-50 transition font-medium text-sm">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- VIEW PROFILE MODAL -->
<!-- ============================================================ -->
<div id="profileModal" class="modal-overlay" onclick="if(event.target===this) closeProfileModal()">
    <div class="modal-content" onclick="event.stopPropagation()" style="max-width: 500px;">
        <div class="modal-header">
            <div class="flex justify-between items-center">
                <h2>
                    <i class="fas fa-user-circle"></i>
                    User Profile
                </h2>
                <button onclick="closeProfileModal()" class="close-btn">&times;</button>
            </div>
        </div>
        <div id="profileContent" class="p-6">
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-[#10A37F]"></i>
                <p class="text-gray-400 mt-2 text-sm">Loading profile...</p>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================================
// CREATE MODAL
// ============================================================
function openCreateModal(defaultUserType) {
    const modal = document.getElementById('createModal');

    // Reset form, then apply the default User Type for the active tab
    document.getElementById('createStaffForm').reset();
    document.getElementById('createUserType').value = defaultUserType || 'barangay_personnel';

    onUserTypeChange();

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function onUserTypeChange() {
    const userType = document.getElementById('createUserType').value;
    const title = document.getElementById('createModalTitle');
    const barangayField = document.getElementById('barangayField');
    const jobTitleField = document.getElementById('jobTitleField');
    const barangaySelect = document.getElementById('createBarangayId');
    const jobTitleInput = document.querySelector('input[name="job_title"]');

    if (userType === 'barangay_personnel') {
        title.textContent = 'Create Barangay Personnel Account';
        barangayField.style.display = 'block';
        jobTitleField.style.display = 'none';
        barangaySelect.required = true;
        jobTitleInput.required = false;
    } else if (userType === 'menro_staff') {
        title.textContent = 'Create MENRO Staff Account';
        barangayField.style.display = 'none';
        jobTitleField.style.display = 'block';
        barangaySelect.required = false;
        jobTitleInput.required = false;
    } else if (userType === 'admin') {
        title.textContent = 'Create Admin Account';
        barangayField.style.display = 'none';
        jobTitleField.style.display = 'block';
        barangaySelect.required = false;
        jobTitleInput.required = false;
    }
}

function closeCreateModal() {
    document.getElementById('createModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ============================================================
// PROFILE MODAL
// ============================================================
function viewProfile(userId) {
    const modal = document.getElementById('profileModal');
    const content = document.getElementById('profileContent');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    content.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl text-[#10A37F]"></i>
            <p class="text-gray-400 mt-2 text-sm">Loading profile...</p>
        </div>
    `;
    
    fetch('<?php echo BASE_URL; ?>ajax/get_user_profile.php?id=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = `<p class="text-red-500 text-center py-4">${data.error}</p>`;
                return;
            }
            
            const statusBadge = data.is_active ? 
                '<span class="status-badge status-active"><i class="fas fa-circle text-[6px] mr-1.5"></i>Active</span>' :
                '<span class="status-badge status-inactive"><i class="fas fa-circle text-[6px] mr-1.5"></i>Suspended</span>';
            
            const roleBadge = data.user_type === 'admin' ? 
                '<span class="role-badge role-admin"><i class="fas fa-crown mr-1.5"></i>Admin</span>' :
                data.user_type === 'menro_staff' ?
                '<span class="role-badge role-admin"><i class="fas fa-crown mr-1.5"></i>MENRO Staff</span>' :
                data.user_type === 'barangay_personnel' ?
                '<span class="role-badge role-barangay"><i class="fas fa-landmark mr-1.5"></i>Barangay Personnel</span>' :
                '<span class="role-badge role-citizen"><i class="fas fa-user mr-1.5"></i>Citizen</span>';
            
            const residencyBadge = data.is_resident == 0 ?
                '<span class="status-badge status-inactive"><i class="fas fa-map-marker-alt mr-1.5"></i>Non-Resident</span>' :
                '<span class="status-badge status-active"><i class="fas fa-home mr-1.5"></i>Resident</span>';
            
            content.innerHTML = `
                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100">
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-emerald-100 to-emerald-200 flex items-center justify-center overflow-hidden">
                        ${data.profile_picture ? 
                            `<img src="${data.profile_picture}" class="w-full h-full object-cover">` :
                            `<i class="fas fa-user text-3xl text-[#10A37F]"></i>`
                        }
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">${data.full_name}</h3>
                        <p class="text-sm text-gray-500">${data.email}</p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            ${roleBadge}
                            ${statusBadge}
                            ${residencyBadge}
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 gap-4">
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Full Name</p>
                        <p class="font-semibold text-gray-800 mt-1">${data.full_name}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Email</p>
                        <p class="font-semibold text-gray-800 mt-1">${data.email}</p>
                    </div>
                    ${data.contact_number ? `
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Contact Number</p>
                        <p class="font-semibold text-gray-800 mt-1">${data.contact_number}</p>
                    </div>
                    ` : ''}
                    ${data.barangay_name ? `
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Barangay</p>
                        <p class="font-semibold text-gray-800 mt-1">${data.barangay_name}</p>
                    </div>
                    ` : ''}
                    ${data.job_title ? `
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Job Title</p>
                        <p class="font-semibold text-gray-800 mt-1">${data.job_title}</p>
                    </div>
                    ` : ''}
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Registered</p>
                        <p class="font-semibold text-gray-800 mt-1">${new Date(data.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button onclick="closeProfileModal()" class="btn-primary px-6 py-2.5 text-white rounded-xl font-semibold text-sm">
                        Close
                    </button>
                </div>
            `;
        })
        .catch(() => {
            content.innerHTML = `<p class="text-red-500 text-center py-4">Failed to load profile data.</p>`;
        });
}

function closeProfileModal() {
    document.getElementById('profileModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ============================================================
// FILTER AUTO-SUBMIT
// ============================================================
let searchTimeout;
const searchInput = document.getElementById('searchInput');
const barangaySelect = document.getElementById('barangaySelect');
const statusSelect = document.getElementById('statusSelect');
const filterForm = document.getElementById('filterForm');

if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => filterForm.submit(), 400);
    });
}

if (barangaySelect) {
    barangaySelect.addEventListener('change', () => filterForm.submit());
}

if (statusSelect) {
    statusSelect.addEventListener('change', () => filterForm.submit());
}

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeCreateModal();
        closeProfileModal();
    }
});

// ============================================================
// FORM VALIDATION FOR CREATE STAFF
// ============================================================
document.getElementById('createStaffForm').addEventListener('submit', function(e) {
    const userType = document.getElementById('createUserType').value;
    const barangaySelect = document.getElementById('createBarangayId');
    const roleSelect = document.getElementById('createRoleId');

    if (userType === 'barangay_personnel' && !barangaySelect.value) {
        e.preventDefault();
        alert('Please select an assigned barangay for this Barangay Personnel account.');
        barangaySelect.focus();
        return false;
    }
    if (!roleSelect.value) {
        e.preventDefault();
        alert('Please select a Role for this account.');
        roleSelect.focus();
        return false;
    }
    return true;
});
</script>

</body>
</html>