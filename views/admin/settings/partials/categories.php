<?php
// views/admin/settings/partials/categories.php
// Manage Categories — embedded as the "Categories" tab of System Settings.
// Full CRUD with weight assignments (1-10) and a threat-level rubric.
//
// POST actions (create / update / delete / toggle_status) are handled inline
// here (the settings shell routes ?tab=categories POSTs to this partial) and
// redirect back to Settings > Categories.

require_once BASE_PATH . 'helpers/SettingsHelper.php';
require_once BASE_PATH . 'helpers/PermissionHelper.php';

// Permission gate (super-admin bypasses via PermissionHelper).
if (!PermissionHelper::userHasPermission('can_manage_system')) {
    $_SESSION['error'] = "You are not permitted to manage categories.";
    header("Location: " . BASE_URL . "index.php?page=settings&tab=categories");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$categoryModel = new Category($db);
$activityLog = new ActivityLog($db);

// ============================================================
// HANDLE POST ACTIONS
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect_url = BASE_URL . "index.php?page=settings&tab=categories";

    // CSRF protection
    if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: " . $redirect_url);
        exit();
    }

    $action = $_POST['action'] ?? '';

    if($action === 'create') {
        $name = InputSanitizer::sanitizeString($_POST['name'] ?? '');
        $description = InputSanitizer::sanitizeString($_POST['description'] ?? '');
        $icon_class = InputSanitizer::sanitizeString($_POST['icon_class'] ?? 'fa-tag');
        $base_weight = isset($_POST['base_weight']) ? (int)$_POST['base_weight'] : 1;
        if ($base_weight < 1 || $base_weight > 10) { $base_weight = 1; }

        if (empty($name)) {
            $_SESSION['error'] = "Category name is required.";
        } elseif ($categoryModel->create($name, $description, $icon_class, $base_weight)) {
            $activityLog->log($_SESSION['user_id'], 'Create Category', "Created category: $name", null, 'Categories');
            $_SESSION['success'] = "Category created!";
        } else {
            $_SESSION['error'] = "Failed to create category.";
        }
        header("Location: " . $redirect_url);
        exit();
    }

    if($action === 'update') {
        $id = (int)($_POST['category_id'] ?? 0);
        $name = InputSanitizer::sanitizeString($_POST['name'] ?? '');
        $description = InputSanitizer::sanitizeString($_POST['description'] ?? '');
        $icon_class = InputSanitizer::sanitizeString($_POST['icon_class'] ?? 'fa-tag');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $base_weight = isset($_POST['base_weight']) ? (int)$_POST['base_weight'] : 1;
        if ($base_weight < 1 || $base_weight > 10) { $base_weight = 1; }

        if ($id <= 0 || empty($name)) {
            $_SESSION['error'] = "Invalid category data.";
        } elseif ($categoryModel->update($id, $name, $description, $icon_class, $is_active, $base_weight)) {
            $activityLog->log($_SESSION['user_id'], 'Update Category', "Updated category: $name", null, 'Categories');
            $_SESSION['success'] = "Category updated!";
        } else {
            $_SESSION['error'] = "Failed to update category.";
        }
        header("Location: " . $redirect_url);
        exit();
    }

    if($action === 'delete') {
        $id = (int)($_POST['category_id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = "Invalid category ID.";
        } elseif ($categoryModel->isUsed($id)) {
            $_SESSION['error'] = "Cannot delete category that is already used in reports. Deactivate it instead.";
        } elseif ($categoryModel->delete($id)) {
            $activityLog->log($_SESSION['user_id'], 'Delete Category', "Deleted category #$id", null, 'Categories');
            $_SESSION['success'] = "Category deleted!";
        } else {
            $_SESSION['error'] = "Failed to delete category.";
        }
        header("Location: " . $redirect_url);
        exit();
    }

    if($action === 'toggle_status') {
        $id = (int)($_POST['category_id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = "Invalid category ID.";
        } elseif ($categoryModel->toggleStatus($id)) {
            $activityLog->log($_SESSION['user_id'], 'Toggle Category Status', "Toggled category #$id status", null, 'Categories');
            $_SESSION['success'] = "Category status updated!";
        } else {
            $_SESSION['error'] = "Failed to update category status.";
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
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// ============================================================
// FETCH ALL CATEGORIES WITH USAGE COUNT
// ============================================================
$categories = $categoryModel->getAllWithUsageCount();
$all_categories = [];
while($cat = $categories->fetch(PDO::FETCH_ASSOC)) {
    $all_categories[] = $cat;
}

// ============================================================
// APPLY FILTERS
// ============================================================
$filtered_categories = $all_categories;

if (!empty($search_query)) {
    $filtered_categories = array_filter($filtered_categories, function($cat) use ($search_query) {
        return stripos($cat['name'], $search_query) !== false ||
               stripos($cat['description'], $search_query) !== false ||
               stripos($cat['icon_class'], $search_query) !== false;
    });
}

if ($status_filter !== 'all') {
    $filtered_categories = array_filter($filtered_categories, function($cat) use ($status_filter) {
        return ($status_filter == 'active' && $cat['is_active'] == 1) ||
               ($status_filter == 'inactive' && $cat['is_active'] == 0);
    });
}

// ============================================================
// STATISTICS
// ============================================================
$total_categories = count($all_categories);
$active_categories = count(array_filter($all_categories, fn($cat) => $cat['is_active'] == 1));
$inactive_categories = count(array_filter($all_categories, fn($cat) => $cat['is_active'] == 0));

// ============================================================
// GENERATE CSRF TOKEN
// ============================================================
$csrf_token = InputSanitizer::generateCsrfToken();

// ============================================================
// WEIGHT LEVEL HELPER (color class per threat level)
// ============================================================
function categoryWeightLevelClass($weight) {
    if ($weight >= 9) return 'bg-red-500';
    if ($weight >= 7) return 'bg-orange-500';
    if ($weight >= 4) return 'bg-yellow-500';
    return 'bg-green-500';
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

    /* ===== WEIGHT BADGES ===== */
    .weight-badge {
        display: inline-flex; align-items: center; justify-content: center;
        width: 2.5rem; height: 2.5rem; border-radius: 10px;
        color: white; font-weight: 800; font-size: 1.05rem;
        cursor: pointer; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .weight-badge:hover { transform: scale(1.08); box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3); }

    /* ===== STATUS BADGES ===== */
    .status-badge {
        display: inline-flex; align-items: center; padding: 4px 14px;
        border-radius: 9999px; font-size: 0.7rem; font-weight: 600;
        white-space: nowrap; letter-spacing: 0.01em;
    }
    .status-active { background: #D1FAE5; color: #065F46; }
    .status-inactive { background: #F3F4F6; color: #6B7280; }

    /* ===== TABLE ===== */
    .table-container { background: white; border-radius: 12px; border: 1px solid rgba(16, 163, 127, 0.08); overflow: hidden; }
    .table-container thead th { background: #F5FBF6; font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: #8aa38a; padding: 0.75rem 1.25rem; }
    .table-container tbody td { padding: 0.75rem 1.25rem; font-size: 0.875rem; border-bottom: 1px solid #f0f4f2; }
    .table-container tbody tr:hover { background: #f9fcfb; }

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

    /* ===== RUBRIC TABLE ===== */
    .rubric-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .rubric-table th, .rubric-table td { border: 1px solid #e5e7eb; padding: 0.75rem; text-align: left; }
    .rubric-table th { background: #f9fafb; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; }

    /* ===== ACTION BUTTONS ===== */
    .action-btn {
        padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 600;
        transition: all 0.15s ease; border: none; cursor: pointer;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .action-btn:hover { transform: scale(1.05); }
    .action-btn-edit { background: #D1FAE5; color: #065F46; }
    .action-btn-edit:hover { background: #A7F3D0; }
    .action-btn-deactivate { background: #FEF3C7; color: #92400E; }
    .action-btn-deactivate:hover { background: #FDE68A; }
    .action-btn-activate { background: #D1FAE5; color: #065F46; }
    .action-btn-activate:hover { background: #A7F3D0; }
    .action-btn-delete { background: #FEE2E2; color: #991B1B; }
    .action-btn-delete:hover { background: #FECACA; }
    .action-btn-disabled { background: #F3F4F6; color: #9CA3AF; cursor: not-allowed; }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
        .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-container table { min-width: 760px; }
        .modal-overlay { padding: 12px; align-items: flex-end; }
        .modal-content { border-radius: 16px 16px 0 0; max-height: 88vh; }
        .stat-card { padding: 1rem 0.9rem; }
    }
    @media (max-width: 480px) {
        .action-btn { padding: 5px 9px; }
        .action-btn-disabled { padding: 5px 9px; font-size: 0.65rem; }
        .status-badge { padding: 3px 10px; font-size: 0.65rem; }
        .weight-badge { width: 2.25rem; height: 2.25rem; font-size: 0.95rem; }
    }
</style>

<div class="fade-in">

    <!-- ===== TOOLBAR ===== -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-3 flex-wrap">
            <p class="text-sm text-gray-500 font-medium">
                <i class="fas fa-tags mr-1.5 text-[#10A37F]"></i>
                Manage report categories and severity weights.
            </p>
            <button onclick="openRubricModal()" class="text-[#10A37F] hover:text-[#0D8568] transition text-sm flex items-center gap-1.5 font-semibold px-2.5 py-1.5 rounded-lg hover:bg-emerald-50">
                <i class="fas fa-chart-line"></i> Weight Rubric
            </button>
        </div>
        <button onclick="openAddCategoryModal()"
                class="btn-primary px-5 py-2.5 text-white font-semibold flex items-center justify-center gap-2 shadow-sm text-sm w-full sm:w-auto">
            <i class="fas fa-plus-circle"></i>
            Add Category
        </button>
    </div>

    <!-- ===== STATISTICS CARDS ===== -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <p class="stat-label">Total Categories</p>
                    <p class="stat-value"><?php echo $total_categories; ?></p>
                </div>
                <div class="stat-icon bg-purple-50">
                    <i class="fas fa-tags text-purple-500 text-base"></i>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <p class="stat-label">Active Categories</p>
                    <p class="stat-value"><?php echo $active_categories; ?></p>
                    <p class="text-xs text-gray-400 mt-1 font-medium">
                        <span class="text-emerald-600"><?php echo $active_categories; ?> active</span>
                    </p>
                </div>
                <div class="stat-icon bg-emerald-50">
                    <i class="fas fa-check-circle text-emerald-500 text-base"></i>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <p class="stat-label">Inactive Categories</p>
                    <p class="stat-value"><?php echo $inactive_categories; ?></p>
                </div>
                <div class="stat-icon bg-gray-100">
                    <i class="fas fa-times-circle text-gray-400 text-base"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== FILTER TOOLBAR (shared report toolbar design) ===== -->
    <?php
    $ft_chips = [];
    if (!empty($search_query)) $ft_chips[] = '<span class="filter-chip">"' . htmlspecialchars($search_query) . '" <span class="chip-remove" data-filter="search"><i class="fas fa-times"></i></span></span>';
    if ($status_filter !== 'all') $ft_chips[] = '<span class="filter-chip">' . ucfirst($status_filter) . ' <span class="chip-remove" data-filter="status"><i class="fas fa-times"></i></span></span>';

    $ft = [
        'search_id'          => 'searchInput',
        'search_value'       => $search_query,
        'search_placeholder' => 'Search by name, description, or icon...',
        'results_text'       => 'Showing <strong>' . count($filtered_categories) . '</strong> of <strong>' . $total_categories . '</strong> categories',
        'inline_selects'     => [
            [
                'id'        => 'toolbarStatus',
                'value'     => $status_filter,
                'min_width' => '130px',
                'options'   => ['all' => 'All Status', 'active' => 'Active', 'inactive' => 'Inactive'],
            ],
        ],
        'filter_by'          => ['active' => false, 'count' => 0],
        'popover_fields'     => [],
        'trailing_select'    => null,
        'view_toggle'        => null,
        'active_filters'     => (int)((!empty($search_query) ? 1 : 0) + ($status_filter !== 'all' ? 1 : 0)),
        'chips'              => $ft_chips,
        'chips_clear_all'    => true,
        'chip_clear_map'     => [
            'search' => ['el' => 'searchInput', 'clear' => ''],
            'status' => ['el' => 'toolbarStatus', 'clear' => 'all'],
        ],
        'callback'           => 'applyFilters',
    ];
    include __DIR__ . '/../../../shared/report_filter_toolbar.php';
    ?>

    <!-- ===== CATEGORIES TABLE ===== -->
    <div class="table-container">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left">Icon</th>
                        <th class="text-left">Name</th>
                        <th class="text-left">Description</th>
                        <th class="text-left">Weight</th>
                        <th class="text-left">Status</th>
                        <th class="text-left">Usage</th>
                        <th class="text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($filtered_categories) > 0): ?>
                        <?php foreach($filtered_categories as $cat):
                            $usage_count = isset($cat['usage_count']) ? $cat['usage_count'] : 0;
                            $can_delete = $usage_count == 0;
                            $weight = isset($cat['base_weight']) ? $cat['base_weight'] : 1;
                        ?>
                        <tr>
                            <td>
                                <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                                    <i class="fas <?php echo htmlspecialchars($cat['icon_class']); ?> text-[#10A37F]"></i>
                                </div>
                            </td>
                            <td class="font-semibold text-gray-800"><?php echo htmlspecialchars($cat['name']); ?></td>
                            <td class="text-gray-500 text-sm font-medium">
                                <?php echo htmlspecialchars(substr($cat['description'], 0, 50)) . (strlen($cat['description']) > 50 ? '...' : ''); ?>
                            </td>
                            <td>
                                <div onclick="showWeightInfo(<?php echo $weight; ?>)"
                                     class="weight-badge <?php echo categoryWeightLevelClass($weight); ?>" title="Click to view threat level">
                                    <?php echo $weight; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $cat['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-sm <?php echo $usage_count > 0 ? 'text-blue-600 font-bold' : 'text-gray-400 font-medium'; ?>">
                                    <?php echo $usage_count; ?> <?php echo $usage_count == 1 ? 'report' : 'reports'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <button onclick='editCategory(<?php echo $cat['id']; ?>, "<?php echo addslashes($cat['name']); ?>", "<?php echo addslashes($cat['description']); ?>", "<?php echo $cat['icon_class']; ?>", <?php echo $cat['is_active']; ?>, <?php echo $weight; ?>)'
                                            class="action-btn action-btn-edit" title="Edit Category">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>

                                    <form method="POST" class="inline" onsubmit="return confirm('<?php echo $cat['is_active'] ? 'Deactivate' : 'Activate'; ?> this category?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                        <button type="submit" class="action-btn <?php echo $cat['is_active'] ? 'action-btn-deactivate' : 'action-btn-activate'; ?>" title="<?php echo $cat['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas <?php echo $cat['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on'; ?> text-xs"></i>
                                        </button>
                                    </form>

                                    <?php if($can_delete): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Permanently delete this category? This action cannot be undone.')">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                        <button type="submit" class="action-btn action-btn-delete" title="Delete Category">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="action-btn action-btn-disabled" title="Cannot delete - used in <?php echo $usage_count; ?> report(s)">
                                        <i class="fas fa-trash-alt text-xs"></i>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-12">
                                <div class="text-center py-6">
                                    <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                                        <i class="fas fa-tags text-xl sm:text-2xl text-gray-400"></i>
                                    </div>
                                    <h3 class="font-semibold text-gray-700 mb-1 text-base">No categories found</h3>
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
    <!-- ADD / EDIT CATEGORY MODAL -->
    <!-- ============================================================ -->
    <div id="categoryModal" class="modal-overlay" onclick="if(event.target===this) closeCategoryModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="flex justify-between items-center">
                    <h2>
                        <i class="fas fa-layer-group"></i>
                        <span id="modalTitle">Add Category</span>
                    </h2>
                    <button onclick="closeCategoryModal()" class="close-btn">&times;</button>
                </div>
            </div>

            <form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=categories" class="p-6" onsubmit="return validateWeight()">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="category_id" id="categoryId">

                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Category Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="catName" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition text-sm">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="catDescription" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition text-sm"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Icon Class (Font Awesome)</label>
                    <input type="text" name="icon_class" id="catIcon" placeholder="fa-trash"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition text-sm">
                    <p class="text-xs text-gray-400 mt-1 font-medium">Use Font Awesome 6: fa-trash, fa-water, fa-smog, fa-tree, fa-recycle, etc.</p>
                </div>

                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-bold text-gray-700">Base Weight Point <span class="text-red-500">*</span></label>
                        <button type="button" onclick="openRubricModal()" class="text-[#10A37F] hover:text-[#0D8568] text-xs flex items-center gap-1 font-semibold">
                            <i class="fas fa-info-circle"></i> View Rubric
                        </button>
                    </div>
                    <input type="number" name="base_weight" id="baseWeight" min="1" max="10" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition text-sm"
                           oninput="validateWeightInput(this)">
                    <p class="text-xs text-gray-400 mt-1 font-medium">Weight must be between 1 and 10 (1 = lowest threat, 10 = highest threat)</p>
                    <div id="weightWarning" class="hidden mt-2 text-xs text-red-600 bg-red-50 p-2 rounded-lg font-medium"></div>
                </div>

                <div class="mb-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" id="catActive" value="1" class="w-4 h-4 text-[#10A37F] rounded">
                        <span class="text-sm font-bold text-gray-700">Active</span>
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeCategoryModal()"
                            class="px-5 py-2.5 border border-gray-300 rounded-xl hover:bg-gray-50 transition font-semibold text-sm">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2.5 btn-primary text-white font-semibold text-sm">
                        Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- WEIGHT INFO POPUP MODAL -->
    <!-- ============================================================ -->
    <div id="weightInfoModal" class="modal-overlay" onclick="if(event.target===this) closeWeightInfoModal()">
        <div class="modal-content" onclick="event.stopPropagation()" style="max-width: 420px;">
            <div class="modal-header">
                <div class="flex justify-between items-center">
                    <h2>
                        <i class="fas fa-info-circle"></i>
                        Weight Information
                    </h2>
                    <button onclick="closeWeightInfoModal()" class="close-btn">&times;</button>
                </div>
            </div>
            <div id="weightInfoContent" class="p-6">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin text-2xl text-[#10A37F]"></i>
                </div>
            </div>
            <div class="p-6 pt-0 flex justify-end">
                <button onclick="closeWeightInfoModal()" class="px-5 py-2.5 btn-primary text-white font-semibold text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- WEIGHT ASSIGNMENT RUBRIC MODAL -->
    <!-- ============================================================ -->
    <div id="rubricModal" class="modal-overlay" onclick="if(event.target===this) closeRubricModal()">
        <div class="modal-content" onclick="event.stopPropagation()" style="max-width: 820px;">
            <div class="modal-header">
                <div class="flex justify-between items-center">
                    <h2>
                        <i class="fas fa-chart-line"></i>
                        Weight Assignment Rubric
                    </h2>
                    <button onclick="closeRubricModal()" class="close-btn">&times;</button>
                </div>
            </div>
            <div class="p-6">
                <div class="mb-4 p-4 bg-blue-50 rounded-xl border border-blue-100">
                    <p class="text-sm text-gray-700 font-medium"><span class="font-extrabold">How to use:</span> Assign a base weight point (1-10) to each category based on its potential threat level to the community and environment.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="rubric-table">
                        <thead>
                            <tr>
                                <th>Threat Level</th>
                                <th>Recommended Weight</th>
                                <th>Defining Criteria for MENRO Admin</th>
                                <th>Example Scenarios</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="hover:bg-gray-50">
                                <td class="font-semibold text-blue-700">Level 1: Operational Nuisance</td>
                                <td class="text-center font-bold">1 to 3</td>
                                <td>Causes aesthetic decay or foul odor, but poses no immediate danger. Can be resolved by standard daily LGU operations.</td>
                                <td>Uncollected garbage, overgrown weeds on public sidewalks, scattered dry leaves.</td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="font-semibold text-yellow-700">Level 2: Ordinance Violation</td>
                                <td class="text-center font-bold">4 to 6</td>
                                <td>Requires active policy enforcement or Barangay Tanod intervention. Shows a behavioral hazard from the community.</td>
                                <td>Illegal dumping of solid waste, noise pollution, open burning of small garbage piles.</td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="font-semibold text-orange-700">Level 3: Infrastructure Threat</td>
                                <td class="text-center font-bold">7 to 8</td>
                                <td>High probability of causing secondary physical damage to municipal property or restricting public mobility.</td>
                                <td>Drainage blockage (flood risk), fallen trees blocking roadways, damaged retaining walls.</td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="font-semibold text-red-700">Level 4: Critical Biohazard</td>
                                <td class="text-center font-bold">9 to 10</td>
                                <td>Immediate, catastrophic threat to human life, agriculture, or the municipality's water supply. Requires emergency multi-agency response.</td>
                                <td>Toxic chemical spills, raw sewage in waterways, massive medical waste dumping.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 p-4 bg-amber-50 rounded-xl border border-amber-100">
                    <p class="text-xs text-gray-700 font-medium"><i class="fas fa-lightbulb text-amber-500 mr-1"></i> <span class="font-extrabold">Pro Tip:</span> Categories with higher weights will be prioritized in reports and alerts. Choose weights carefully based on real-world impact assessment.</p>
                </div>

                <div class="flex justify-end mt-5">
                    <button onclick="closeRubricModal()" class="px-5 py-2.5 btn-primary text-white font-semibold">
                        Got it
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
let currentWeightValue = 1;

function openAddCategoryModal() {
    document.getElementById('modalTitle').textContent = 'Add Category';
    document.getElementById('formAction').value = 'create';
    document.getElementById('categoryId').value = '';
    document.getElementById('catName').value = '';
    document.getElementById('catDescription').value = '';
    document.getElementById('catIcon').value = 'fa-tag';
    document.getElementById('catActive').checked = true;
    document.getElementById('baseWeight').value = '1';
    document.getElementById('weightWarning').classList.add('hidden');
    currentWeightValue = 1;

    document.getElementById('categoryModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function editCategory(id, name, description, icon, isActive, baseWeight) {
    document.getElementById('modalTitle').textContent = 'Edit Category';
    document.getElementById('formAction').value = 'update';
    document.getElementById('categoryId').value = id;
    document.getElementById('catName').value = name;
    document.getElementById('catDescription').value = description;
    document.getElementById('catIcon').value = icon;
    document.getElementById('catActive').checked = isActive == 1;
    document.getElementById('baseWeight').value = baseWeight || 1;
    document.getElementById('weightWarning').classList.add('hidden');
    currentWeightValue = baseWeight || 1;

    document.getElementById('categoryModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.remove('active');
    document.body.style.overflow = '';
}

function openRubricModal() {
    document.getElementById('rubricModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeRubricModal() {
    document.getElementById('rubricModal').classList.remove('active');
    document.body.style.overflow = '';
}

function showWeightInfo(weight) {
    let threatLevel = '', recommendedRange = '', criteria = '', examples = '', colorClass = '', badgeClass = '';

    if (weight >= 1 && weight <= 3) {
        threatLevel = 'Level 1: Operational Nuisance';
        recommendedRange = '1 - 3';
        criteria = 'Causes aesthetic decay or foul odor, but poses no immediate danger. Can be resolved by standard daily LGU operations.';
        examples = 'Uncollected garbage, overgrown weeds on public sidewalks, scattered dry leaves.';
        colorClass = 'text-blue-700';
        badgeClass = 'bg-green-500';
    } else if (weight >= 4 && weight <= 6) {
        threatLevel = 'Level 2: Ordinance Violation';
        recommendedRange = '4 - 6';
        criteria = 'Requires active policy enforcement or Barangay Tanod intervention. Shows a behavioral hazard from the community.';
        examples = 'Illegal dumping of solid waste, noise pollution, open burning of small garbage piles.';
        colorClass = 'text-yellow-700';
        badgeClass = 'bg-yellow-500';
    } else if (weight >= 7 && weight <= 8) {
        threatLevel = 'Level 3: Infrastructure Threat';
        recommendedRange = '7 - 8';
        criteria = 'High probability of causing secondary physical damage to municipal property or restricting public mobility.';
        examples = 'Drainage blockage (flood risk), fallen trees blocking roadways, damaged retaining walls.';
        colorClass = 'text-orange-700';
        badgeClass = 'bg-orange-500';
    } else if (weight >= 9 && weight <= 10) {
        threatLevel = 'Level 4: Critical Biohazard';
        recommendedRange = '9 - 10';
        criteria = 'Immediate, catastrophic threat to human life, agriculture, or the municipality\'s water supply. Requires emergency multi-agency response.';
        examples = 'Toxic chemical spills, raw sewage in waterways, massive medical waste dumping.';
        colorClass = 'text-red-700';
        badgeClass = 'bg-red-500';
    }

    document.getElementById('weightInfoContent').innerHTML = `
        <div class="space-y-3">
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                <span class="font-bold text-gray-700">Weight Value:</span>
                <span class="inline-flex items-center justify-center w-10 h-10 ${badgeClass} text-white font-extrabold rounded-lg text-lg">${weight}</span>
            </div>
            <div>
                <p class="font-bold text-gray-700 mb-1">Threat Level:</p>
                <p class="${colorClass} font-semibold">${threatLevel}</p>
            </div>
            <div>
                <p class="font-bold text-gray-700 mb-1">Recommended Weight Range:</p>
                <p class="text-gray-600 font-medium">${recommendedRange}</p>
            </div>
            <div>
                <p class="font-bold text-gray-700 mb-1">Criteria:</p>
                <p class="text-gray-600 text-sm font-medium">${criteria}</p>
            </div>
            <div>
                <p class="font-bold text-gray-700 mb-1">Examples:</p>
                <p class="text-gray-600 text-sm font-medium">${examples}</p>
            </div>
        </div>
    `;

    document.getElementById('weightInfoModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeWeightInfoModal() {
    document.getElementById('weightInfoModal').classList.remove('active');
    document.body.style.overflow = '';
}

function validateWeightInput(input) {
    let value = parseInt(input.value);
    const warningDiv = document.getElementById('weightWarning');

    if (isNaN(value)) {
        warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Please enter a valid number between 1 and 10.';
        warningDiv.classList.remove('hidden');
        return false;
    }

    if (value < 1) {
        input.value = 1;
        warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Weight cannot be less than 1. Setting to minimum value.';
        warningDiv.classList.remove('hidden');
        setTimeout(() => warningDiv.classList.add('hidden'), 3000);
    } else if (value > 10) {
        input.value = 10;
        warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Weight cannot exceed 10. Setting to maximum value.';
        warningDiv.classList.remove('hidden');
        setTimeout(() => warningDiv.classList.add('hidden'), 3000);
    } else {
        warningDiv.classList.add('hidden');
    }

    currentWeightValue = input.value;
    return true;
}

function validateWeight() {
    const weightInput = document.getElementById('baseWeight');
    const weight = parseInt(weightInput.value);

    if (isNaN(weight) || weight < 1 || weight > 10) {
        alert('Please enter a valid base weight point between 1 and 10.');
        return false;
    }
    return true;
}

// ============================================================
// FILTER FUNCTIONALITY
// The shared report_filter_toolbar partial handles search
// debounce, inline selects, and filter chips. This callback is
// invoked on every change and builds the redirect URL.
// ============================================================
function applyFilters() {
    const params = new URLSearchParams({ page: 'settings', tab: 'categories' });
    const search = document.getElementById('searchInput')?.value || '';
    const status = document.getElementById('toolbarStatus')?.value || 'all';
    if (search) params.set('search', search);
    if (status !== 'all') params.set('status', status);
    window.location.href = '<?php echo BASE_URL; ?>index.php?' + params.toString();
}

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCategoryModal();
        closeRubricModal();
        closeWeightInfoModal();
    }
});
</script>
