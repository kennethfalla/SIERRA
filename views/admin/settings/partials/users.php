<?php
// views/admin/settings/partials/users.php
// Manage Users — embedded as the "Users" tab of System Settings.
// Full CRUD with role-based access control. Three sub-tabs:
// Citizens, Barangay Personnel, MENRO Staff.
//
// POST actions (deactivate / activate / delete) are handled inline here
// (the settings shell routes ?tab=users POSTs to this partial) and redirect
// back to Settings > Users. Account creation posts to AdminController.
// NOTE: `subtab` (not `tab`) holds the inner Citizens/Barangay/MENRO tab,
// because `tab` is the settings tab itself.

require_once BASE_PATH . 'helpers/SettingsHelper.php';
require_once BASE_PATH . 'helpers/PermissionHelper.php';

$database = new Database();
$db = $database->getConnection();
$userModel = new User($db);
$barangayModel = new Barangay($db);
$activityLog = new ActivityLog($db);

// ============================================================
// HANDLE POST ACTIONS
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect_subtab = $_GET['subtab'] ?? 'citizens';
    $redirect_url = BASE_URL . "index.php?page=settings&tab=users&subtab=" . $redirect_subtab;

    // CSRF protection
    if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: " . $redirect_url);
        exit();
    }

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
        header("Location: " . $redirect_url);
        exit();
    }

    // Delete user (only for non-admin users)
    if($action === 'delete') {
        $check = $db->prepare("SELECT user_type, CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
        $check->execute([$user_id]);
        $user = $check->fetch(PDO::FETCH_ASSOC);

        // Delete permission depends on the target account type:
        // citizens are managed under User Management, staff under Staff Management.
        $required_key = ($user && empty($user['user_type'])) ? 'can_manage_users' : 'can_manage_staff';
        if (!PermissionHelper::userHasPermission($required_key)) {
            $_SESSION['error'] = "You are not permitted to delete users.";
            header("Location: " . $redirect_url);
            exit();
        }

        // Prevent deleting own account
        if($user_id == $_SESSION['user_id']) {
            $_SESSION['error'] = "You cannot delete your own account.";
            header("Location: " . $redirect_url);
            exit();
        }

        if($user && $user['user_type'] !== 'admin') {
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
        header("Location: " . $redirect_url);
        exit();
    }

    // Unknown action — redirect back safely
    header("Location: " . $redirect_url);
    exit();
}

// ============================================================
// GET FILTER PARAMETERS
// ============================================================
$users_tab = isset($_GET['subtab']) ? $_GET['subtab'] : 'citizens';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$barangay_filter = isset($_GET['barangay']) ? (int)$_GET['barangay'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$residency_filter = isset($_GET['residency']) ? $_GET['residency'] : '';

// ============================================================
// FETCH USERS FROM DATABASE
// ============================================================
$all_users_data = [];
$query = "SELECT u.id, u.email, u.first_name, u.last_name, u.user_type, u.barangay_id,
                 u.contact_number, u.is_active, u.created_at, u.job_title,
                 u.is_resident, u.non_resident_address, u.province, u.municipality, u.profile_picture,
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
function applyFilters($users, $search_query, $barangay_filter, $status_filter, $residency_filter) {
    $filtered = $users;

    if (!empty($search_query)) {
        $filtered = array_filter($filtered, function($user) use ($search_query) {
            return stripos($user['full_name'] ?? '', $search_query) !== false ||
                   stripos($user['email'] ?? '', $search_query) !== false ||
                   stripos($user['contact_number'] ?? '', $search_query) !== false ||
                   stripos($user['barangay_name'] ?? '', $search_query) !== false ||
                   stripos($user['province'] ?? '', $search_query) !== false ||
                   stripos($user['municipality'] ?? '', $search_query) !== false ||
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

    if ($residency_filter === 'resident') {
        $filtered = array_filter($filtered, function($user) {
            return isset($user['is_resident']) && (int)$user['is_resident'] === 1;
        });
    } elseif ($residency_filter === 'non_resident') {
        $filtered = array_filter($filtered, function($user) {
            return isset($user['is_resident']) && (int)$user['is_resident'] === 0;
        });
    }

    return $filtered;
}

// ============================================================
// CATEGORIZE USERS BY ROLE
// ============================================================
$citizens = array_filter($all_users_data, function($user) {
    return empty($user['user_type']);
});

$barangay_personnel = array_filter($all_users_data, function($user) {
    return ($user['user_type'] ?? '') === 'barangay_personnel';
});

$menro_staff = array_filter($all_users_data, function($user) {
    return in_array($user['user_type'] ?? '', ['admin', 'menro_staff'], true);
});

// Apply filters to each group
$filtered_citizens = applyFilters($citizens, $search_query, $barangay_filter, $status_filter, $residency_filter);
$filtered_barangay = applyFilters($barangay_personnel, $search_query, $barangay_filter, $status_filter, $residency_filter);
$filtered_menro = applyFilters($menro_staff, $search_query, $barangay_filter, $status_filter, $residency_filter);

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
// DETERMINE DISPLAY DATA BASED ON ACTIVE SUB-TAB
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

if ($users_tab === 'citizens') {
    $display_users = $filtered_citizens;
    $display_total = $total_citizens;
    $display_count = count($display_users);
    $tab_label = 'Citizens';
    $show_barangay_filter = true;
    $show_status_filter = true;
    $show_create_btn = false;
    $create_role = '';
    $create_label = '';
} elseif ($users_tab === 'barangay') {
    $display_users = $filtered_barangay;
    $display_total = $total_barangay;
    $display_count = count($display_users);
    $tab_label = 'Barangay Personnel';
    $show_barangay_filter = true;
    $show_status_filter = true;
    $show_create_btn = true;
    $create_role = 'barangay_personnel'; // default User Type when opening the modal from this tab
    $create_label = 'New Barangay Personnel';
} elseif ($users_tab === 'menro') {
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
?>

<style>
    ::-webkit-scrollbar { width: 6px; height: 6px; background: transparent; }
    ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 20px; }
    ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #059669, #047857); border-radius: 20px; }
    * { scrollbar-width: thin; scrollbar-color: #059669 #f1f5f9; }

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
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

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
    .stat-card:hover { transform: translateY(-3px); border-color: #10A37F; box-shadow: 0 12px 24px -8px rgba(16, 163, 127, 0.12); }
    .stat-card .stat-value { font-size: 1.75rem; font-weight: 800; color: #1a2e1a; letter-spacing: -0.02em; }
    @media (min-width: 640px) { .stat-card .stat-value { font-size: 2rem; } }
    .stat-card .stat-label { font-size: 0.7rem; font-weight: 600; color: #8aa38a; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 0.15rem; }
    @media (min-width: 640px) { .stat-card .stat-label { font-size: 0.75rem; } }
    .stat-card .stat-icon { width: 2.5rem; height: 2.5rem; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    @media (min-width: 640px) { .stat-card .stat-icon { width: 3rem; height: 3rem; } }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .stat-card:nth-child(1) { animation-delay: 0.05s; }
    .stat-card:nth-child(2) { animation-delay: 0.1s; }
    .stat-card:nth-child(3) { animation-delay: 0.15s; }

    /* ===== SUB-TABS ===== */
    .tab-active { border-bottom: 3px solid #10A37F; color: #10A37F; font-weight: 700; }
    .tab-inactive { color: #6B7280; border-bottom: 3px solid transparent; font-weight: 500; }
    .tab-inactive:hover { color: #10A37F; border-bottom-color: #10A37F; }
    .tab-badge {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 20px; height: 20px; padding: 0 6px; border-radius: 9999px;
        font-size: 0.6rem; font-weight: 700; background: #e5e7eb; color: #4b5563;
    }
    .tab-active .tab-badge { background: #10A37F; color: white; }

    /* ===== ROLE BADGES ===== */
    .role-badge {
        display: inline-flex; align-items: center; padding: 4px 14px;
        border-radius: 9999px; font-size: 0.7rem; font-weight: 600;
        white-space: nowrap; letter-spacing: 0.01em;
    }
    .role-badge .fa, .role-badge .fas { font-size: 0.6rem; }
    .role-admin { background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%); color: white; }
    .role-barangay { background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); color: white; }
    .role-citizen { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: white; }

    /* ===== STATUS BADGES ===== */
    .status-badge {
        display: inline-flex; align-items: center; padding: 4px 14px;
        border-radius: 9999px; font-size: 0.7rem; font-weight: 600;
        white-space: nowrap; letter-spacing: 0.01em;
    }
    .status-badge .fa, .status-badge .fas { font-size: 0.5rem; }
    .status-active { background: #D1FAE5; color: #065F46; }
    .status-active .fa-circle { color: #10B981; }
    .status-inactive { background: #FEE2E2; color: #991B1B; }
    .status-inactive .fa-circle { color: #EF4444; }

    /* ===== TABLE ===== */
    .table-container { background: white; border-radius: 12px; border: 1px solid rgba(16, 163, 127, 0.08); overflow: hidden; }
    .table-container thead th { background: #F5FBF6; font-size: 0.58rem; text-transform: uppercase; letter-spacing: 0.05em; color: #8aa38a; padding: 0.6rem 0.85rem; }
    .table-container tbody td { padding: 0.55rem 0.85rem; font-size: 0.82rem; border-bottom: 1px solid #f0f4f2; }
    .table-container tbody tr:hover { background: #f9fcfb; }
    .table-container tbody td .action-btn { padding: 3px 8px; }

    /* ===== MODAL ===== */
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(8px); z-index: 1000; display: none;
        align-items: center; justify-content: center; padding: 16px;
    }
    .modal-overlay.active { display: flex; }
    .modal-content {
        background: white; border-radius: 16px; max-width: 600px; width: 100%;
        max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px -12px rgba(0,0,0,0.25);
    }
    .modal-header {
        background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
        padding: 1rem 1.5rem; border-radius: 16px 16px 0 0; position: sticky; top: 0; z-index: 10;
    }
    .modal-header h2 { color: white; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
    .modal-header .close-btn { color: rgba(255,255,255,0.7); background: none; border: none; font-size: 1.5rem; cursor: pointer; transition: color 0.2s; }
    .modal-header .close-btn:hover { color: white; }

    /* ===== EMPTY STATE ===== */
    .empty-state { text-align: center; padding: 2rem 1rem; }
    @media (min-width: 640px) { .empty-state { padding: 3rem 2rem; } }

    /* ===== ACTION BUTTONS ===== */
    .action-btn {
        padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 600;
        transition: all 0.15s ease; border: none; cursor: pointer;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .action-btn:hover { transform: scale(1.05); }
    .action-btn-view { background: #E0E7FF; color: #3730A3; }
    .action-btn-view:hover { background: #C7D2FE; }
    .action-btn-suspend { background: #FEF3C7; color: #92400E; }
    .action-btn-suspend:hover { background: #FDE68A; }
    .action-btn-activate { background: #D1FAE5; color: #065F46; }
    .action-btn-activate:hover { background: #A7F3D0; }
    .action-btn-delete { background: #FEE2E2; color: #991B1B; }
    .action-btn-delete:hover { background: #FECACA; }
    .action-btn-disabled { background: #F3F4F6; color: #9CA3AF; cursor: not-allowed; }

    /* ===== SUB-TABS NAV ===== */
    .sub-tabs-nav { -webkit-overflow-scrolling: touch; scrollbar-width: none; }
    .sub-tabs-nav::-webkit-scrollbar { display: none; }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1279px) {
        /* Convert table rows into stacked cards */
        .table-container { overflow: visible; background: transparent; border: none; }
        .table-container thead { display: none; }
        .table-container tbody { display: block; }
        .table-container tbody tr {
            display: block;
            background: #fff;
            border: 1px solid rgba(16, 163, 127, 0.08);
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(16, 163, 127, 0.05);
            margin-bottom: 12px;
            overflow: hidden;
        }
        .table-container tbody tr:hover { background: #fff; }
        .table-container tbody td {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 10px 16px;
            border-bottom: 1px dashed #eef2ef;
        }
        .table-container tbody td::before {
            content: attr(data-label);
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #8aa38a;
            margin-bottom: 3px;
        }
        .table-container tbody tr td:last-child { border-bottom: none; }
        .table-container tbody td.mobile-user {
            display: block;
            padding: 16px 16px 12px;
            border-bottom: 1px solid #f0f4f2;
        }
        .table-container tbody td.mobile-user::before { display: none; }
        .table-container tbody td.mobile-actions {
            flex-direction: row;
            flex-wrap: wrap;
            gap: 8px;
            padding: 12px 16px;
            background: #FBFDFC;
        }
        .table-container tbody td.mobile-actions::before { display: none; }
        .table-container tbody td.mobile-actions form { margin: 0; }
        .table-container tbody td.mobile-actions .action-btn {
            flex: 1 1 auto;
            justify-content: center;
            padding: 9px 12px;
            border-radius: 10px;
        }
        .table-container tbody td.mobile-actions .action-btn-disabled { flex: 0 0 auto; }
        .table-container tbody tr.empty-row {
            display: block;
            background: #fff;
            border: 1px solid rgba(16, 163, 127, 0.08);
            box-shadow: none;
            margin-bottom: 0;
        }
        .table-container tbody tr.empty-row td { display: block; padding: 0; border: none; }
        .table-container tbody tr.empty-row td::before { display: none; }
        .stat-card { padding: 1rem 0.9rem; }
    }
    @media (max-width: 768px) {
        .modal-overlay { padding: 12px; align-items: flex-end; }
        .modal-content { border-radius: 16px 16px 0 0; max-height: 88vh; }
    }
    @media (max-width: 480px) {
        .action-btn { padding: 5px 9px; }
        .action-btn-disabled { padding: 5px 9px; font-size: 0.65rem; }
        .role-badge, .status-badge { padding: 3px 10px; font-size: 0.65rem; }
    }
</style>

<div class="fade-in">

    <!-- ===== TOOLBAR ===== -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-5">
        <p class="text-sm text-gray-500 font-medium order-2 sm:order-1">
            <i class="fas fa-user-cog mr-1.5 text-[#10A37F]"></i>
            Manage <?php echo strtolower($tab_label); ?> accounts, roles, and access.
        </p>
        <?php if($show_create_btn): ?>
        <button onclick="openCreateModal('<?php echo $create_role; ?>')"
                class="btn-primary px-5 py-2.5 text-white font-semibold flex items-center justify-center gap-2 shadow-sm text-sm w-full sm:w-auto order-1 sm:order-2">
            <i class="fas fa-plus-circle"></i>
            <?php echo $create_label; ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- ===== VALIDATION ERRORS (create account) ===== -->
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

    <!-- ===== SUB-TABS ===== -->
    <div class="border-b border-emerald-100 mb-5 flex flex-wrap items-center justify-between gap-3">
        <nav class="sub-tabs-nav flex gap-1 sm:gap-0 sm:space-x-8 sm:flex-wrap overflow-x-auto sm:overflow-visible whitespace-nowrap sm:whitespace-normal flex-1 min-w-0" aria-label="User tabs">
            <a href="<?php echo BASE_URL; ?>index.php?page=settings&tab=users&subtab=citizens<?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo $barangay_filter > 0 ? '&barangay='.$barangay_filter : ''; ?><?php echo $status_filter !== '' ? '&status='.$status_filter : ''; ?><?php echo $residency_filter !== '' ? '&residency='.$residency_filter : ''; ?>"
               class="px-3 sm:px-1 py-4 text-sm transition-all duration-200 flex items-center gap-2 <?php echo $users_tab == 'citizens' ? 'tab-active' : 'tab-inactive'; ?>">
                <i class="fas fa-users"></i>
                Citizens
                <span class="tab-badge"><?php echo $total_citizens; ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>index.php?page=settings&tab=users&subtab=barangay<?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo $barangay_filter > 0 ? '&barangay='.$barangay_filter : ''; ?><?php echo $status_filter !== '' ? '&status='.$status_filter : ''; ?><?php echo $residency_filter !== '' ? '&residency='.$residency_filter : ''; ?>"
               class="px-3 sm:px-1 py-4 text-sm transition-all duration-200 flex items-center gap-2 <?php echo $users_tab == 'barangay' ? 'tab-active' : 'tab-inactive'; ?>">
                <i class="fas fa-landmark"></i>
                Barangay Personnel
                <span class="tab-badge"><?php echo $total_barangay; ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>index.php?page=settings&tab=users&subtab=menro<?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo $barangay_filter > 0 ? '&barangay='.$barangay_filter : ''; ?><?php echo $status_filter !== '' ? '&status='.$status_filter : ''; ?><?php echo $residency_filter !== '' ? '&residency='.$residency_filter : ''; ?>"
               class="px-3 sm:px-1 py-4 text-sm transition-all duration-200 flex items-center gap-2 <?php echo $users_tab == 'menro' ? 'tab-active' : 'tab-inactive'; ?>">
                <i class="fas fa-crown"></i>
                MENRO Staff
                <span class="tab-badge"><?php echo $total_menro; ?></span>
            </a>
        </nav>
        <?php
        $report_role    = $users_tab === 'citizens' ? 'citizen' : ($users_tab === 'barangay' ? 'barangay' : 'menro');
        $report_status  = $status_filter !== '' ? urlencode($status_filter) : '';
        $report_brgy    = $barangay_filter > 0 ? (int)$barangay_filter : 0;
        $report_url     = '?page=users-report&role=' . $report_role
                        . ($report_status ? '&status=' . $report_status : '')
                        . ($report_brgy ? '&barangay=' . $report_brgy : '');
        ?>
        <?php if (PermissionHelper::userHasPermission('can_export_reports')): ?>
        <div class="export-dropdown" id="usersExportWrap">
            <button onclick="toggleUsersExport()" id="usersExportBtn" class="btn-export-trigger">
                <i class="fas fa-download"></i> Export
                <i class="fas fa-chevron-down"></i>
            </button>
            <div id="usersExportDropdown" class="export-dropdown-menu" style="width:300px;">
                <button class="export-dropdown-item" onclick="window.open('<?php echo BASE_URL; ?>index.php<?php echo $report_url; ?>', '_blank')">
                    <div class="item-icon" style="background:#E8F5F0; color:#10A37F;"><i class="fas fa-file-pdf"></i></div>
                    <div class="item-text"><div class="item-title">Export as PDF</div><div class="item-desc">Preview and save as PDF</div></div>
                </button>
                <div class="export-dropdown-divider"></div>
                <div class="export-dropdown-header">
                    <p><i class="fas fa-file-csv"></i> Export Users as CSV</p>
                    <div class="sub">Download accounts by category</div>
                </div>
                <button class="export-dropdown-item" onclick="downloadUsersExport('all')">
                    <div class="item-icon" style="background:#E8F5F0; color:#10A37F;"><i class="fas fa-users"></i></div>
                    <div class="item-text"><div class="item-title">All Users</div></div>
                </button>
                <button class="export-dropdown-item" onclick="downloadUsersExport('menro')">
                    <div class="item-icon" style="background:#EDE9FE; color:#7C3AED;"><i class="fas fa-crown"></i></div>
                    <div class="item-text"><div class="item-title">All MENRO Users</div></div>
                </button>
                <button class="export-dropdown-item" onclick="downloadUsersExport('barangay')">
                    <div class="item-icon" style="background:#E8F5F0; color:#10A37F;"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="item-text"><div class="item-title">All Barangay Users</div></div>
                </button>
                <button class="export-dropdown-item" onclick="downloadUsersExport('reporters')">
                    <div class="item-icon" style="background:#DBEAFE; color:#2563EB;"><i class="fas fa-bullhorn"></i></div>
                    <div class="item-text"><div class="item-title">All Reporters</div><div class="item-desc">Resident &amp; Non-Resident</div></div>
                </button>
                <button class="export-dropdown-item" onclick="downloadUsersExport('residents')">
                    <div class="item-icon" style="background:#D1FAE5; color:#059669;"><i class="fas fa-home"></i></div>
                    <div class="item-text"><div class="item-title">Residents Only</div></div>
                </button>
                <button class="export-dropdown-item" onclick="downloadUsersExport('non_residents')">
                    <div class="item-icon" style="background:#FEF3C7; color:#B45309;"><i class="fas fa-road"></i></div>
                    <div class="item-text"><div class="item-title">Non-Residents Only</div></div>
                </button>
                <div class="export-dropdown-divider"></div>
                <div class="export-dropdown-footer">
                    <div class="footer-label"><i class="fas fa-calendar"></i> New Accounts</div>
                    <div style="display:flex; gap:8px; margin-bottom:8px;">
                        <input type="date" id="exportNewFrom" class="flex-1 border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-700 focus:outline-none focus:border-[#10A37F]" placeholder="From">
                        <input type="date" id="exportNewTo" class="flex-1 border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-700 focus:outline-none focus:border-[#10A37F]" placeholder="To">
                    </div>
                    <button class="btn-export-primary" onclick="downloadUsersExport('new_accounts')">
                        <i class="fas fa-download"></i> Export New Accounts
                    </button>
                </div>
                <div class="export-dropdown-divider"></div>
                <div class="export-dropdown-footer">
                    <div class="footer-label"><i class="fas fa-toggle-on"></i> Account Status</div>
                    <button class="btn-export-primary" onclick="downloadUsersExport('status')">
                        <i class="fas fa-file-csv"></i> Export Status Report
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== FILTER TOOLBAR (shared report toolbar design) ===== -->
    <?php
    $ft_popover_count = 0;
    if ($barangay_filter > 0) $ft_popover_count++;

    $ft_barangay_options = ['0' => 'All Barangays'];
    if ($show_barangay_filter) {
        foreach ($barangay_list as $brgy) { $ft_barangay_options[(string)$brgy['id']] = $brgy['name']; }
    }

    $ft_chips = [];
    if (!empty($search_query)) $ft_chips[] = '<span class="filter-chip">"' . htmlspecialchars($search_query) . '" <span class="chip-remove" data-filter="search"><i class="fas fa-times"></i></span></span>';
    if ($barangay_filter > 0) {
        $active_barangay_name = '';
        foreach ($barangay_list as $brgy) {
            if ($brgy['id'] == $barangay_filter) { $active_barangay_name = $brgy['name']; break; }
        }
        $ft_chips[] = '<span class="filter-chip">' . htmlspecialchars($active_barangay_name) . ' <span class="chip-remove" data-filter="barangay"><i class="fas fa-times"></i></span></span>';
    }
    if ($status_filter !== '') $ft_chips[] = '<span class="filter-chip">' . ($status_filter === 'active' ? 'Active' : 'Suspended') . ' <span class="chip-remove" data-filter="status"><i class="fas fa-times"></i></span></span>';
    if ($residency_filter !== '') $ft_chips[] = '<span class="filter-chip">' . ($residency_filter === 'resident' ? 'Resident' : 'Non-Resident') . ' <span class="chip-remove" data-filter="residency"><i class="fas fa-times"></i></span></span>';

    $ft_inline_selects = [];
    if ($show_status_filter) {
        $ft_inline_selects[] = [
            'id'        => 'toolbarStatus',
            'value'     => $status_filter,
            'min_width' => '130px',
            'options'   => ['' => 'All Status', 'active' => 'Active', 'inactive' => 'Suspended'],
        ];
    }
    if ($show_status_filter) {
        $ft_inline_selects[] = [
            'id'        => 'toolbarResidency',
            'value'     => $residency_filter,
            'min_width' => '160px',
            'options'   => ['' => 'All Residents', 'resident' => 'Resident', 'non_resident' => 'Non-Resident'],
        ];
    }

    $ft_popover_fields = [];
    if ($show_barangay_filter) {
        $ft_popover_fields[] = [
            'kind' => 'select', 'id' => 'popoverBarangay', 'label' => 'Barangay',
            'value' => $barangay_filter, 'default' => '0', 'options' => $ft_barangay_options,
        ];
    }

    $ft = [
        'search_id'          => 'searchInput',
        'search_value'       => $search_query,
        'search_placeholder' => 'Search by name, email, phone...',
        'results_text'       => 'Showing <strong>' . $display_count . '</strong> of <strong>' . $display_total . '</strong> ' . strtolower($tab_label),
        'inline_selects'     => $ft_inline_selects,
        'filter_by'          => [
            'active' => ($barangay_filter > 0),
            'count'  => $ft_popover_count,
        ],
        'popover_fields'     => $ft_popover_fields,
        'trailing_select'    => null,
        'view_toggle'        => null,
        'active_filters'     => (int)((!empty($search_query) ? 1 : 0) + ($barangay_filter > 0 ? 1 : 0) + ($status_filter !== '' ? 1 : 0) + ($residency_filter !== '' ? 1 : 0)),
        'chips'              => array_values(array_filter($ft_chips)),
        'chips_clear_all'    => true,
        'chip_clear_map'     => [
            'search'   => ['el' => 'searchInput', 'clear' => ''],
            'status'   => ['el' => 'toolbarStatus', 'clear' => ''],
            'residency' => ['el' => 'toolbarResidency', 'clear' => ''],
            'barangay' => ['el' => 'popoverBarangay', 'clear' => '0'],
        ],
        'callback'           => 'applyFilters',
    ];
    include __DIR__ . '/../../../shared/report_filter_toolbar.php';
    ?>

    <!-- ===== USERS TABLE ===== -->
    <div class="table-container">
        <div class="overflow-x-visible xl:overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left">User</th>
                        <th class="text-left">Contact</th>
                        <?php if($users_tab === 'barangay' || $users_tab === 'citizens'): ?>
                        <th class="text-left"><?php echo $residency_filter === 'non_resident' ? 'Province / Municipality' : 'Barangay'; ?></th>
                        <?php endif; ?>
                        <?php if($users_tab === 'menro'): ?>
                        <th class="text-left">Job Title</th>
                        <?php endif; ?>
                        <th class="text-left">Role</th>
                        <th class="text-left">Status</th>
                        <?php if($users_tab === 'barangay' || $users_tab === 'citizens'): ?>
                        <th class="text-left">Residency</th>
                        <?php endif; ?>
                        <th class="text-left">Registered</th>
                        <th class="text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($display_users) > 0): ?>
                        <?php foreach($display_users as $user): ?>
                        <tr>
                            <td class="mobile-user">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-100 to-emerald-200 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                        <?php if(!empty($user['profile_picture'])): ?>
                                            <img src="<?php echo BASE_URL . $user['profile_picture']; ?>" alt="Profile" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i class="fas <?php echo ($user['user_type'] ?? '') === 'admin' || ($user['user_type'] ?? '') === 'menro_staff' ? 'fa-crown' : (($user['user_type'] ?? '') === 'barangay_personnel' ? 'fa-landmark' : 'fa-user'); ?> text-[#10A37F] text-sm"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($user['full_name']); ?></p>
                                        <p class="text-xs text-gray-400 font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Contact">
                                <div class="flex items-center gap-1.5">
                                    <i class="fas fa-phone-alt text-gray-400 text-xs"></i>
                                    <span class="text-sm text-gray-600 font-medium"><?php echo formatPhoneNumber($user['contact_number']); ?></span>
                                </div>
                            </td>
                            <?php if($users_tab === 'barangay' || $users_tab === 'citizens'): ?>
                            <td data-label="<?php echo $residency_filter === 'non_resident' ? 'Province / Municipality' : 'Barangay'; ?>">
                                <?php if ($residency_filter === 'non_resident'): ?>
                                    <?php $non_res_loc = trim(implode(', ', array_filter([$user['municipality'] ?? '', $user['province'] ?? '']))); ?>
                                    <?php if ($non_res_loc !== ''): ?>
                                        <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($non_res_loc); ?></span>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-400 font-medium">—</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($user['barangay_name'] ?? '—'); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <?php if($users_tab === 'menro'): ?>
                            <td data-label="Job Title">
                                <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($user['job_title'] ?? '—'); ?></span>
                            </td>
                            <?php endif; ?>
                            <td data-label="Role">
                                <?php echo getRoleBadge($user['user_type'] ?? null, $user['job_title'] ?? ''); ?>
                            </td>
                            <td data-label="Status">
                                <span class="status-badge <?php echo $user['is_active'] == 1 ? 'status-active' : 'status-inactive'; ?>">
                                    <i class="fas fa-circle text-[6px] mr-1.5"></i>
                                    <?php echo $user['is_active'] == 1 ? 'Active' : 'Suspended'; ?>
                                </span>
                            </td>
                            <?php if($users_tab === 'barangay' || $users_tab === 'citizens'): ?>
                            <td data-label="Residency">
                                <?php if(isset($user['is_resident']) && (int)$user['is_resident'] === 1): ?>
                                    <span class="status-badge status-active"><i class="fas fa-home text-[8px] mr-1.5"></i>Resident</span>
                                <?php elseif(isset($user['is_resident']) && (int)$user['is_resident'] === 0): ?>
                                    <span class="status-badge status-inactive"><i class="fas fa-map-marker-alt text-[8px] mr-1.5"></i>Non-Resident</span>
                                <?php else: ?>
                                    <span class="text-sm text-gray-400 font-medium">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td data-label="Registered">
                                <span class="text-sm text-gray-500 font-medium"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                            </td>
                            <td class="mobile-actions">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <!-- View Profile -->
                                    <button onclick="viewProfile(<?php echo $user['id']; ?>)"
                                            class="action-btn action-btn-view" title="View Profile">
                                        <i class="fas fa-eye text-xs"></i>
                                    </button>

                                    <?php if(($user['user_type'] ?? '') !== 'admin'): ?>
                                        <?php if($user['is_active'] == 1): ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Deactivate this account? The user will lose access.')">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="action-btn action-btn-suspend" title="Suspend Account">
                                                <i class="fas fa-user-slash text-xs"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Activate this account? The user will regain access.')">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="action-btn action-btn-activate" title="Activate Account">
                                                <i class="fas fa-user-check text-xs"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
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
                        <tr class="empty-row">
                            <td colspan="<?php echo ($users_tab === 'barangay' || $users_tab === 'citizens') ? 9 : 8; ?>" class="text-center py-12">
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
                    ${data.is_resident == 0 && data.non_resident_address ? `
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Non-Resident Address</p>
                        <p class="font-semibold text-gray-800 mt-1">${data.non_resident_address}</p>
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
// FILTER FUNCTIONALITY
// The shared report_filter_toolbar partial handles search
// debounce, inline selects, the "Filter By" popover, and filter
// chips. This callback is invoked on every change and builds the
// redirect URL (preserving the active subtab).
// ============================================================
function applyFilters() {
    const params = new URLSearchParams({
        page: 'settings',
        tab: 'users',
        subtab: '<?php echo htmlspecialchars($users_tab); ?>'
    });
    const search = document.getElementById('searchInput')?.value || '';
    const status = document.getElementById('toolbarStatus')?.value || '';
    const residency = document.getElementById('toolbarResidency')?.value || '';
    const barangay = document.getElementById('popoverBarangay')?.value || '0';

    if (search) params.set('search', search);
    if (status) params.set('status', status);
    if (residency) params.set('residency', residency);
    if (parseInt(barangay, 10) > 0) params.set('barangay', barangay);

    window.location.href = '<?php echo BASE_URL; ?>index.php?' + params.toString();
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

// ============================================================
// USERS EXPORT DROPDOWN
// ============================================================
function toggleUsersExport() {
    var dd = document.getElementById('usersExportDropdown');
    var btn = document.getElementById('usersExportBtn');
    dd.classList.toggle('open');
    if (btn) btn.classList.toggle('active');
}

document.addEventListener('click', function(e) {
    var wrap = document.getElementById('usersExportWrap');
    var dd = document.getElementById('usersExportDropdown');
    var btn = document.getElementById('usersExportBtn');
    if (wrap && dd && !wrap.contains(e.target)) {
        dd.classList.remove('open');
        if (btn) btn.classList.remove('active');
    }
});

function downloadUsersExport(type) {
    var params = new URLSearchParams();
    params.set('page', 'settings');
    params.set('tab', 'users');
    params.set('export_users', type);
    if (type === 'new_accounts') {
        var from = document.getElementById('exportNewFrom').value;
        var to = document.getElementById('exportNewTo').value;
        if (from) params.set('export_from', from);
        if (to) params.set('export_to', to);
    }
    window.location.href = '<?php echo BASE_URL; ?>index.php?' + params.toString();
    var dd = document.getElementById('usersExportDropdown');
    var btn = document.getElementById('usersExportBtn');
    if (dd) dd.classList.remove('open');
    if (btn) btn.classList.remove('active');
}
</script>
