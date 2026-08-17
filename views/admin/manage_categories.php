<?php
// views/admin/manage_categories.php - WITH CONSISTENT DESIGN & RADIUS SCALE SYSTEM
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/helpers/SettingsHelper.php';
require_once dirname(__DIR__, 2) . '/helpers/PermissionHelper.php';
requireRole('admin');

if (!PermissionHelper::userHasPermission('can_manage_system')) {
    $_SESSION['error'] = "You are not permitted to manage categories.";
    header("Location: " . BASE_URL . "index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$categoryModel = new Category($db);

// Handle category actions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if($action === 'create') {
        $baseWeight = isset($_POST['base_weight']) ? intval($_POST['base_weight']) : 1;
        $categoryModel->create($_POST['name'], $_POST['description'], $_POST['icon_class'], $baseWeight);
        $_SESSION['success'] = "Category created!";
    } elseif($action === 'update') {
        $baseWeight = isset($_POST['base_weight']) ? intval($_POST['base_weight']) : 1;
        $categoryModel->update($_POST['category_id'], $_POST['name'], $_POST['description'], $_POST['icon_class'], $_POST['is_active'] ?? 0, $baseWeight);
        $_SESSION['success'] = "Category updated!";
    } elseif($action === 'delete') {
        if($categoryModel->isUsed($_POST['category_id'])) {
            $_SESSION['error'] = "Cannot delete category that is already used in reports. Deactivate it instead.";
        } else {
            $categoryModel->delete($_POST['category_id']);
            $_SESSION['success'] = "Category deleted!";
        }
    } elseif($action === 'toggle_status') {
        $categoryModel->toggleStatus($_POST['category_id']);
        $_SESSION['success'] = "Category status updated!";
    }
    header("Location: " . BASE_URL . "index.php?page=manage-categories");
    exit();
}

// Get search query
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get all categories with usage count
$categories = $categoryModel->getAllWithUsageCount();
$all_categories = [];
while($cat = $categories->fetch(PDO::FETCH_ASSOC)) {
    $all_categories[] = $cat;
}

// Apply filters
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

// Count statistics
$total_categories = count($all_categories);
$active_categories = count(array_filter($all_categories, fn($cat) => $cat['is_active'] == 1));
$inactive_categories = count(array_filter($all_categories, fn($cat) => $cat['is_active'] == 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Categories - EnviroTrack</title>
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
        
        /* ========== RADIUS SCALE SYSTEM ========== */
        /* 4px - Ultra-tight (data tables, dense UI) */
        .radius-4 { border-radius: 4px; }
        
        /* 8px - Standard (inputs, buttons - default) */
        .radius-8 { border-radius: 8px; }
        button:not(.radius-10):not(.radius-12):not(.radius-16):not(.radius-full) { border-radius: 8px; }
        input, select, textarea { border-radius: 8px !important; }
        
        /* 10px - Icon containers, primary buttons */
        .radius-10 { border-radius: 10px; }
        .stat-icon, .icon-container { border-radius: 10px; }
        .btn-primary { border-radius: 10px; }
        .weight-badge { border-radius: 10px; }
        
        /* 12px - Cards, containers (most common) */
        .radius-12 { border-radius: 12px; }
        .stat-card, .filter-card, .table-container { border-radius: 12px; }
        
        /* 16px - Prominent cards / modals */
        .radius-16 { border-radius: 16px; }
        .modal-content, .rubric-modal-content { border-radius: 16px; }
        
        /* 20px - Weight info popup */
        .radius-20 { border-radius: 20px; }
        .weight-info-modal { border-radius: 20px; }
        
        /* 24px+ - Hero elements */
        .radius-24 { border-radius: 24px; }
        .category-modal-content { border-radius: 24px; }
        
        /* 9999px - Pills (filters/tags) - fully rounded */
        .radius-full { border-radius: 9999px; }
        .status-badge { border-radius: 9999px; }
        
        /* Apply to specific components */
        .stat-card { border-radius: 12px; }
        .stat-icon { border-radius: 10px; }
        .filter-card { border-radius: 12px; }
        .table-container { border-radius: 12px; }
        .btn-primary { border-radius: 10px; }
        .weight-badge { border-radius: 10px; }
        .status-badge { border-radius: 9999px; }
        .modal-content { border-radius: 16px; }
        .category-modal-content { border-radius: 24px; }
        .weight-info-modal { border-radius: 20px; }
        
        .btn-primary {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #0D8568;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
        }
        
        .stat-card {
            transition: all 0.3s ease;
            border: 1px solid rgba(5, 150, 105, 0.08);
            opacity: 0;
            animation: slideUp 0.5s ease-out forwards;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            border-color: #059669;
            box-shadow: 0 8px 20px -5px rgba(5, 150, 105, 0.15);
        }
        
        .weight-badge { 
            background: linear-gradient(135deg, #10A37F, #0D8568);
            cursor: pointer;
            transition: all 0.2s;
        }
        .weight-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
        }
        
        .action-icon {
            transition: all 0.2s;
        }
        .action-icon:hover {
            transform: scale(1.1);
        }
        
        .rubric-table th, .rubric-table td { 
            border: 1px solid #e5e7eb; 
            padding: 12px; 
            text-align: left; 
        }
        .rubric-table th { 
            background-color: #f9fafb; 
            font-weight: 600; 
        }
        .rubric-table tr:first-child th:first-child { border-top-left-radius: 12px; }
        .rubric-table tr:first-child th:last-child { border-top-right-radius: 12px; }
        .rubric-table tr:last-child td:first-child { border-bottom-left-radius: 12px; }
        .rubric-table tr:last-child td:last-child { border-bottom-right-radius: 12px; }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-slide-up { animation: slideUp 0.5s ease-out forwards; }
        
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .filter-card { animation-delay: 0.2s; }
        .table-container { animation-delay: 0.25s; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gradient-to-br from-[#F5FBF6] to-[#EAF7F2]">

<!-- Sidebar -->
<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<!-- MAIN CONTENT WITH CONSISTENT PADDING STRUCTURE -->
<!-- ml-72 = 288px margin-left (sidebar is 256px, so 32px gap between sidebar and content) -->
<div class="lg:ml-72 min-h-screen">
    <!-- p-4 = 16px padding mobile | md:p-8 = 32px padding desktop -->
    <div class="p-4 md:p-8 max-w-[1600px] mx-auto">
        
        <!-- Header -->
        <div class="mb-8 animate-slide-up" style="animation-delay: 0s;">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="icon-container w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-layer-group text-[#10A37F] text-xl"></i>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-800 tracking-tight">Manage Categories</h1>
                    </div>
                    <p class="text-gray-500 text-sm ml-14 font-medium">Manage environmental report categories with weight assignments</p>
                </div>
                <button onclick="openAddCategoryModal()" class="btn-primary px-5 py-2.5 text-white font-semibold flex items-center gap-2 shadow-sm whitespace-nowrap">
                    <i class="fas fa-plus"></i>Add Category
                </button>
            </div>
        </div>
        
        <!-- Statistics Cards - 12px radius -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="stat-card bg-white p-5">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1 font-semibold">Total Categories</p>
                        <p class="text-2xl font-extrabold text-gray-800 tracking-tight"><?php echo $total_categories; ?></p>
                    </div>
                    <div class="stat-icon w-10 h-10 bg-purple-50 flex items-center justify-center">
                        <i class="fas fa-tags text-purple-500"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card bg-white p-5">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1 font-semibold">Active Categories</p>
                        <p class="text-2xl font-extrabold text-[#10A37F] tracking-tight"><?php echo $active_categories; ?></p>
                    </div>
                    <div class="stat-icon w-10 h-10 bg-emerald-50 flex items-center justify-center">
                        <i class="fas fa-check-circle text-[#10A37F]"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card bg-white p-5">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1 font-semibold">Inactive Categories</p>
                        <p class="text-2xl font-extrabold text-gray-500 tracking-tight"><?php echo $inactive_categories; ?></p>
                    </div>
                    <div class="stat-icon w-10 h-10 bg-gray-100 flex items-center justify-center">
                        <i class="fas fa-times-circle text-gray-400"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Error/Success Messages -->
        <?php if(isset($_SESSION['error'])): ?>
            <div class="mb-5 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 flex items-center gap-3 animate-slide-up">
                <i class="fas fa-exclamation-triangle text-red-500"></i>
                <span class="font-medium"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="mb-5 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 flex items-center gap-3 animate-slide-up">
                <i class="fas fa-check-circle text-green-500"></i>
                <span class="font-medium"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Search Filters - 12px radius -->
        <div class="filter-card bg-white p-5 mb-6 animate-slide-up">
            <form method="GET" action="<?php echo BASE_URL; ?>index.php" id="filterForm">
                <input type="hidden" name="page" value="manage-categories">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-xs text-gray-500 mb-1 font-semibold">Search</label>
                        <div class="relative">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by name, description, or icon..." class="w-full pl-11 pr-4 py-2.5 border border-gray-200 focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition">
                        </div>
                    </div>
                    <div class="w-40">
                        <label class="block text-xs text-gray-500 mb-1 font-semibold">Status</label>
                        <select name="status" id="statusSelect" class="w-full px-4 py-2.5 border border-gray-200 focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none bg-white">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <a href="<?php echo BASE_URL; ?>index.php?page=manage-categories" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mt-4 pt-3 border-t border-emerald-50">
                <div class="text-sm text-gray-500 font-medium">
                    <i class="fas fa-info-circle mr-1 text-[#10A37F]"></i>
                    Showing <span class="font-bold text-gray-700"><?php echo count($filtered_categories); ?></span> of <span class="font-bold text-gray-700"><?php echo $total_categories; ?></span> categories
                </div>
                <button onclick="openRubricModal()" class="text-[#10A37F] hover:text-[#0D8568] transition text-sm flex items-center gap-1 font-semibold">
                    <i class="fas fa-chart-line"></i>
                    <span>Weight Rubric</span>
                </button>
            </div>
        </div>
        
        <!-- Categories Table - 12px container -->
        <div class="table-container bg-white overflow-hidden animate-slide-up">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-emerald-50 bg-[#F5FBF6]">
                            <th class="px-5 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Icon</th>
                            <th class="px-5 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-5 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-5 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Weight</th>
                            <th class="px-5 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Usage</th>
                            <th class="px-5 py-4 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($filtered_categories) > 0): ?>
                            <?php foreach($filtered_categories as $cat): 
                                $usage_count = isset($cat['usage_count']) ? $cat['usage_count'] : 0;
                                $can_delete = $usage_count == 0;
                            ?>
                            <tr class="border-b border-emerald-50 hover:bg-emerald-50/30 transition">
                                <td class="px-5 py-4">
                                    <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                                        <i class="fas <?php echo htmlspecialchars($cat['icon_class']); ?> text-[#10A37F]"></i>
                                    </div>
                                </td>
                                <td class="px-5 py-4 font-semibold text-gray-800"><?php echo htmlspecialchars($cat['name']); ?></td>
                                <td class="px-5 py-4 text-gray-500 text-sm font-medium"><?php echo htmlspecialchars(substr($cat['description'], 0, 50)) . (strlen($cat['description']) > 50 ? '...' : ''); ?></td>
                                <td class="px-5 py-4">
                                    <div onclick="showWeightInfo(<?php echo isset($cat['base_weight']) ? $cat['base_weight'] : '1'; ?>)" 
                                         class="weight-badge inline-flex items-center justify-center w-12 h-12 text-white font-extrabold text-lg shadow-sm cursor-pointer">
                                        <?php echo isset($cat['base_weight']) ? $cat['base_weight'] : '1'; ?>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="status-badge px-3 py-1 text-xs font-semibold <?php echo $cat['is_active'] ? 'bg-emerald-100 text-[#10A37F]' : 'bg-gray-100 text-gray-500'; ?>">
                                        <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="text-sm <?php echo $usage_count > 0 ? 'text-blue-600 font-bold' : 'text-gray-400 font-medium'; ?>">
                                        <?php echo $usage_count; ?> <?php echo $usage_count == 1 ? 'report' : 'reports'; ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-1">
                                        <button onclick='editCategory(<?php echo $cat['id']; ?>, "<?php echo addslashes($cat['name']); ?>", "<?php echo addslashes($cat['description']); ?>", "<?php echo $cat['icon_class']; ?>", <?php echo $cat['is_active']; ?>, <?php echo isset($cat['base_weight']) ? $cat['base_weight'] : '1'; ?>)' 
                                                class="action-icon text-[#10A37F] hover:text-[#0D8568] p-2 rounded-lg hover:bg-emerald-50 transition">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('<?php echo $cat['is_active'] ? 'Deactivate' : 'Activate'; ?> this category?')">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                            <button type="submit" class="action-icon <?php echo $cat['is_active'] ? 'text-orange-500 hover:text-orange-700' : 'text-green-500 hover:text-green-700'; ?> p-2 rounded-lg hover:bg-gray-50 transition">
                                                <i class="fas <?php echo $cat['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <?php if($can_delete): ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Permanently delete this category? This action cannot be undone.')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                            <button type="submit" class="action-icon text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <div class="inline-block p-2 rounded-lg cursor-not-allowed opacity-40" title="Cannot delete - used in <?php echo $usage_count; ?> report(s)">
                                            <i class="fas fa-trash-alt text-gray-400"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-5 py-16 text-center text-gray-400">
                                    <i class="fas fa-inbox text-5xl mb-3 block"></i>
                                    <p class="font-medium">No categories found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</div>

<!-- Weight Info Popup Modal - 20px radius -->
<div id="weightInfoModal" class="modal" onclick="if(event.target===this) closeWeightInfoModal()">
    <div class="weight-info-modal bg-white max-w-sm w-full shadow-2xl" onclick="event.stopPropagation()">
        <div class="p-5">
            <div class="flex justify-between items-center mb-3">
                <h3 id="weightInfoTitle" class="text-lg font-extrabold text-gray-800 tracking-tight">Weight Information</h3>
                <button onclick="closeWeightInfoModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="weightInfoContent" class="text-sm text-gray-600"></div>
            <div class="mt-4 flex justify-end">
                <button onclick="closeWeightInfoModal()" class="px-4 py-2 btn-primary text-white text-sm font-semibold">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Category Modal - 24px radius -->
<div id="categoryModal" class="modal" onclick="if(event.target===this) closeCategoryModal()">
    <div class="category-modal-content bg-white max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl" onclick="event.stopPropagation()">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 id="modalTitle" class="text-xl font-extrabold text-gray-800 tracking-tight">Add Category</h3>
                <button onclick="closeCategoryModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <form method="POST" onsubmit="return validateWeight()">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="category_id" id="categoryId">
                
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Category Name</label>
                    <input type="text" name="name" id="catName" required 
                           class="w-full px-4 py-2.5 border border-gray-200 focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="catDescription" rows="3" 
                              class="w-full px-4 py-2.5 border border-gray-200 focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Icon Class (Font Awesome)</label>
                    <input type="text" name="icon_class" id="catIcon" placeholder="fa-trash" 
                           class="w-full px-4 py-2.5 border border-gray-200 focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition">
                    <p class="text-xs text-gray-400 mt-1 font-medium">Use Font Awesome 6: fa-trash, fa-water, fa-smog, fa-tree, fa-recycle, etc.</p>
                </div>
                
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-bold text-gray-700">Base Weight Point</label>
                        <button type="button" onclick="openRubricModal()" class="text-[#10A37F] hover:text-[#0D8568] text-xs flex items-center gap-1 font-semibold">
                            <i class="fas fa-info-circle"></i> View Rubric
                        </button>
                    </div>
                    <input type="number" name="base_weight" id="baseWeight" min="1" max="10" required 
                           class="w-full px-4 py-2.5 border border-gray-200 focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition"
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
                            class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 transition font-semibold">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2.5 btn-primary text-white font-semibold">
                        Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Weight Assignment Rubric Modal - 16px radius -->
<div id="rubricModal" class="modal" onclick="if(event.target===this) closeRubricModal()">
    <div class="modal-content bg-white max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl" onclick="event.stopPropagation()">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-extrabold text-gray-800 tracking-tight flex items-center gap-2">
                    <i class="fas fa-chart-line text-[#10A37F]"></i>
                    Weight Assignment Rubric
                </h3>
                <button onclick="closeRubricModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            
            <div class="mb-4 p-4 bg-blue-50 rounded-xl border border-blue-100">
                <p class="text-sm text-gray-700 font-medium"><span class="font-extrabold">How to use:</span> Assign a base weight point (1-10) to each category based on its potential threat level to the community and environment.</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="rubric-table w-full text-sm">
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
    let threatLevel = '', recommendedRange = '', criteria = '', examples = '', colorClass = '';
    
    if (weight >= 1 && weight <= 3) {
        threatLevel = 'Level 1: Operational Nuisance';
        recommendedRange = '1 - 3';
        criteria = 'Causes aesthetic decay or foul odor, but poses no immediate danger. Can be resolved by standard daily LGU operations.';
        examples = 'Uncollected garbage, overgrown weeds on public sidewalks, scattered dry leaves.';
        colorClass = 'text-blue-700';
    } else if (weight >= 4 && weight <= 6) {
        threatLevel = 'Level 2: Ordinance Violation';
        recommendedRange = '4 - 6';
        criteria = 'Requires active policy enforcement or Barangay Tanod intervention. Shows a behavioral hazard from the community.';
        examples = 'Illegal dumping of solid waste, noise pollution, open burning of small garbage piles.';
        colorClass = 'text-yellow-700';
    } else if (weight >= 7 && weight <= 8) {
        threatLevel = 'Level 3: Infrastructure Threat';
        recommendedRange = '7 - 8';
        criteria = 'High probability of causing secondary physical damage to municipal property or restricting public mobility.';
        examples = 'Drainage blockage (flood risk), fallen trees blocking roadways, damaged retaining walls.';
        colorClass = 'text-orange-700';
    } else if (weight >= 9 && weight <= 10) {
        threatLevel = 'Level 4: Critical Biohazard';
        recommendedRange = '9 - 10';
        criteria = 'Immediate, catastrophic threat to human life, agriculture, or the municipality\'s water supply. Requires emergency multi-agency response.';
        examples = 'Toxic chemical spills, raw sewage in waterways, massive medical waste dumping.';
        colorClass = 'text-red-700';
    }
    
    const content = `
        <div class="space-y-3">
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                <span class="font-bold text-gray-700">Weight Value:</span>
                <span class="inline-flex items-center justify-center w-10 h-10 ${weight >= 9 ? 'bg-red-500' : (weight >= 7 ? 'bg-orange-500' : (weight >= 4 ? 'bg-yellow-500' : 'bg-green-500'))} text-white font-extrabold rounded-lg text-lg">${weight}</span>
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
    
    document.getElementById('weightInfoContent').innerHTML = content;
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

// Real-time search
let searchTimeout;
const searchInput = document.getElementById('searchInput');
const statusSelect = document.getElementById('statusSelect');
const filterForm = document.getElementById('filterForm');

if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => filterForm.submit(), 300);
    });
}

if (statusSelect) {
    statusSelect.addEventListener('change', () => filterForm.submit());
}

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCategoryModal();
        closeRubricModal();
        closeWeightInfoModal();
    }
});
</script>
</body>
</html>