<?php
// views/admin/settings/partials/category_keywords.php
// Category Keywords — embedded as the "Category Keywords" tab of System
// Settings. A living dictionary linking common local terms / Tagalog
// phrases / slang to official report categories. Used by the Submit Report
// page's auto-correction engine to sniff a resident's description and auto-
// correct a mismatched (or empty) Category dropdown.
//
// POST actions (create / update / delete / toggle_status) are handled
// inline here (the settings shell routes ?tab=category_keywords POSTs to
// this partial) and redirect back to Settings > Category Keywords.

require_once BASE_PATH . 'helpers/PermissionHelper.php';
require_once BASE_PATH . 'helpers/Lang.php';

// Permission gate (super-admin bypasses via PermissionHelper).
if (!PermissionHelper::userHasPermission('can_manage_system')) {
    $_SESSION['error'] = "You are not permitted to manage category keywords.";
    header("Location: " . BASE_URL . "index.php?page=settings&tab=category_keywords");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$activityLog = new ActivityLog($db);

// Category options come straight from the categories table (only active
// categories so old/inactive entries can't be assigned).
$CATEGORY_OPTIONS = [];
try {
    $cat_stmt = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
    $CATEGORY_OPTIONS = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $CATEGORY_OPTIONS = [];
    error_log("[CategoryKeywords] categories unavailable: " . $e->getMessage());
}

$normalize_keyword = function ($raw) {
    return trim(mb_strtolower((string)$raw, 'UTF-8'));
};

// ============================================================
// HANDLE POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect_url = BASE_URL . "index.php?page=settings&tab=category_keywords";

    // CSRF protection
    if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: " . $redirect_url);
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $keyword_id = (int)($_POST['keyword_id'] ?? 0);
        $keyword = $normalize_keyword($_POST['keyword'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);

        if (mb_strlen($keyword, 'UTF-8') < 2) {
            $_SESSION['error'] = "Keyword is required (at least 2 characters).";
            header("Location: " . $redirect_url);
            exit();
        }
        if (mb_strlen($keyword, 'UTF-8') > 100) {
            $_SESSION['error'] = "Keyword must not exceed 100 characters.";
            header("Location: " . $redirect_url);
            exit();
        }
        $cat_exists = false;
        foreach ($CATEGORY_OPTIONS as $cat) {
            if ((int)$cat['id'] === $category_id) { $cat_exists = true; break; }
        }
        if (!$cat_exists) {
            $_SESSION['error'] = "Please choose a valid category.";
            header("Location: " . $redirect_url);
            exit();
        }

        // Case-insensitive uniqueness check (keywords stored lowercase).
        $dup = $db->prepare("SELECT id FROM category_keywords WHERE LOWER(keyword) = ? AND id != ?");
        $dup->execute([$keyword, $keyword_id]);
        if ($dup->fetch()) {
            $_SESSION['error'] = "That keyword already exists for a category. Edit the existing entry instead.";
            header("Location: " . $redirect_url);
            exit();
        }

        if ($action === 'create') {
            $stmt = $db->prepare("INSERT INTO category_keywords (keyword, category_id, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$keyword, $category_id, $_SESSION['user_id']]);
            $keyword_id = (int)$db->lastInsertId();
            $activityLog->log($_SESSION['user_id'], 'Create Category Keyword', "Created category keyword #$keyword_id ('$keyword')", null, 'Category Keywords');
            $_SESSION['success'] = "Keyword added successfully!";
        } else {
            if ($keyword_id <= 0) {
                $_SESSION['error'] = "Invalid keyword.";
                header("Location: " . $redirect_url);
                exit();
            }
            $stmt = $db->prepare("UPDATE category_keywords SET keyword = ?, category_id = ? WHERE id = ?");
            $stmt->execute([$keyword, $category_id, $keyword_id]);
            $activityLog->log($_SESSION['user_id'], 'Update Category Keyword', "Updated category keyword #$keyword_id ('$keyword')", null, 'Category Keywords');
            $_SESSION['success'] = "Keyword updated successfully!";
        }
        header("Location: " . $redirect_url);
        exit();
    }

    if ($action === 'delete') {
        $keyword_id = (int)($_POST['keyword_id'] ?? 0);
        if ($keyword_id > 0) {
            $stmt = $db->prepare("DELETE FROM category_keywords WHERE id = ?");
            $stmt->execute([$keyword_id]);
            $activityLog->log($_SESSION['user_id'], 'Delete Category Keyword', "Deleted category keyword #$keyword_id", null, 'Category Keywords');
            $_SESSION['success'] = "Keyword deleted successfully!";
        } else {
            $_SESSION['error'] = "Invalid keyword.";
        }
        header("Location: " . $redirect_url);
        exit();
    }

    if ($action === 'toggle_status') {
        $keyword_id = (int)($_POST['keyword_id'] ?? 0);
        if ($keyword_id > 0) {
            $stmt = $db->prepare("SELECT is_active FROM category_keywords WHERE id = ?");
            $stmt->execute([$keyword_id]);
            $current = (int)$stmt->fetchColumn();
            $new = $current ? 0 : 1;
            $stmt = $db->prepare("UPDATE category_keywords SET is_active = ? WHERE id = ?");
            $stmt->execute([$new, $keyword_id]);
            $activityLog->log($_SESSION['user_id'], 'Toggle Category Keyword', "Toggled category keyword #$keyword_id to " . ($new ? 'active' : 'inactive'), null, 'Category Keywords');
            $_SESSION['success'] = "Keyword " . ($new ? 'activated' : 'deactivated') . ".";
        } else {
            $_SESSION['error'] = "Invalid keyword.";
        }
        header("Location: " . $redirect_url);
        exit();
    }

    $_SESSION['error'] = "Invalid action.";
    header("Location: " . $redirect_url);
    exit();
}

// ============================================================
// GET — LIST KEYWORDS
// ============================================================
$keywords = [];
try {
    $stmt = $db->query("
        SELECT kw.*, c.name AS category_name
        FROM category_keywords kw
        JOIN categories c ON c.id = kw.category_id
        ORDER BY kw.is_active DESC, c.name ASC, kw.keyword ASC
    ");
    $keywords = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $keywords = [];
    error_log("[CategoryKeywords] list unavailable: " . $e->getMessage());
}

$csrf_token = InputSanitizer::generateCsrfToken();
$total = count($keywords);
$active_count = count(array_filter($keywords, fn($k) => (int)$k['is_active'] === 1));
$categories_covered = count(array_unique(array_map(fn($k) => (int)$k['category_id'], $keywords)));
?>

<style>
    .ckw-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .ckw-stats { display: flex; gap: 10px; flex-wrap: wrap; }
    .ckw-stat { background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 0.75rem; padding: 0.4rem 0.9rem; font-size: 0.8rem; color: #4B5563; }
    .ckw-stat strong { color: #1F2937; }
    .ckw-info { background: #EFF6FF; border: 1px solid #BFDBFE; border-left: 4px solid #3B82F6; color: #1E40AF; border-radius: 0.75rem; padding: 0.7rem 0.9rem; font-size: 0.78rem; margin-bottom: 1.25rem; line-height: 1.5; }
    .ckw-modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(3px); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 16px; }
    .ckw-modal-overlay.active { display: flex; }
    .ckw-modal { background: white; border-radius: 1rem; box-shadow: 0 20px 50px rgba(0,0,0,0.2); width: 100%; max-width: 520px; max-height: 92vh; overflow-y: auto; animation: ckwFade 0.2s ease-out; }
    @keyframes ckwFade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .ckw-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid #F3F4F6; }
    .ckw-modal-header h3 { font-size: 1rem; font-weight: 700; color: #1F2937; display: flex; align-items: center; gap: 0.5rem; }
    .ckw-modal-close { background: #F3F4F6; border: none; width: 30px; height: 30px; border-radius: 9999px; cursor: pointer; color: #6B7280; transition: all 0.2s; }
    .ckw-modal-close:hover { background: #E5E7EB; color: #111827; }
    .ckw-modal-body { padding: 1.25rem; }
    .ckw-sm { font-size: 0.7rem; }
    .ckw-badge { display: inline-flex; align-items: center; gap: 4px; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 700; }
    .ckw-badge.on { background: #ECFDF5; color: #047857; }
    .ckw-badge.off { background: #FEF2F2; color: #B91C1C; }
    .ckw-chip { background: #ECFDF5; color: #065F46; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.68rem; font-weight: 600; }
    .ckw-empty { text-align: center; padding: 3rem 1rem; color: #9CA3AF; }
    .ckw-empty i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; color: #D1D5DB; }
</style>

<!-- ===== EXPLAINER ===== -->
<div class="ckw-info">
    <i class="fas fa-magic mr-1"></i>
    <strong><?php echo t('How auto-correction works:'); ?></strong> <?php echo t('these keywords are the Admin Dictionary. While a resident types their description on the Submit Report page, the system listens for these words. If the description strongly matches a different category than the one selected (or none at all), the dropdown auto-switches to the correct category and flashes a notice so the resident knows.'); ?>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="ckw-toolbar">
    <div class="ckw-stats">
        <span class="ckw-stat"><strong><?php echo $total; ?></strong> <?php echo t('keywords'); ?></span>
        <span class="ckw-stat"><strong><?php echo $active_count; ?></strong> <?php echo t('active'); ?></span>
        <span class="ckw-stat"><strong><?php echo $categories_covered; ?></strong> <?php echo t('categories covered'); ?></span>
    </div>
    <button type="button" class="btn-primary" onclick="openKeywordModal()">
        <i class="fas fa-plus mr-1.5"></i> <?php echo t('Create Keyword'); ?>
    </button>
</div>

<?php if (empty($keywords)): ?>
    <div class="ckw-empty">
        <i class="fas fa-key"></i>
        <p class="text-sm font-semibold text-gray-500"><?php echo t('No category keywords yet.'); ?></p>
        <p class="text-xs text-gray-400 mt-1"><?php echo t('Add words like "basura", "baha", or "usok" so the auto-correction engine can route reports accurately.'); ?></p>
    </div>
<?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th><?php echo t('Keyword'); ?></th>
                    <th><?php echo t('Category'); ?></th>
                    <th><?php echo t('Status'); ?></th>
                    <th style="text-align:right;"><?php echo t('Actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($keywords as $kw): $kw_json = json_encode($kw['keyword'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
                <tr>
                    <td>
                        <span class="inline-flex items-center gap-1.5">
                            <i class="fas fa-key text-gray-300 text-xs"></i>
                            <?php echo htmlspecialchars($kw['keyword']); ?>
                        </span>
                    </td>
                    <td><span class="ckw-chip"><?php echo htmlspecialchars($kw['category_name'] ?? 'Unknown'); ?></span></td>
                    <td>
                        <?php if ((int)$kw['is_active'] === 1): ?>
                            <span class="ckw-badge on"><i class="fas fa-circle fa-2xs"></i> <?php echo t('Active'); ?></span>
                        <?php else: ?>
                            <span class="ckw-badge off"><i class="fas fa-circle fa-2xs"></i> <?php echo t('Inactive'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right; white-space:nowrap;">
                        <div class="flex items-center justify-end gap-1.5">
                            <form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=category_keywords" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="keyword_id" value="<?php echo (int)$kw['id']; ?>">
                                <button type="submit" class="text-xs text-gray-400 hover:text-emerald-600 transition p-1.5 hover:bg-emerald-50 rounded-lg" title="<?php echo (int)$kw['is_active'] === 1 ? t('Deactivate') : t('Activate'); ?>">
                                    <i class="fas <?php echo (int)$kw['is_active'] === 1 ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                </button>
                            </form>
                            <button type="button" onclick='openKeywordModal(<?php echo (int)$kw['id']; ?>, <?php echo $kw_json; ?>, <?php echo (int)$kw['category_id']; ?>)' class="text-xs text-gray-400 hover:text-emerald-600 transition p-1.5 hover:bg-emerald-50 rounded-lg" title="<?php echo t('Edit'); ?>">
                                <i class="fas fa-pen"></i>
                            </button>
                            <form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=category_keywords" style="display:inline-block;" onsubmit="return confirm('Delete this keyword?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="keyword_id" value="<?php echo (int)$kw['id']; ?>">
                                <button type="submit" class="text-xs text-gray-400 hover:text-red-600 transition p-1.5 hover:bg-red-50 rounded-lg" title="<?php echo t('Delete'); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- ===== CREATE / EDIT MODAL ===== -->
<div class="ckw-modal-overlay" id="keywordModal" onclick="if(event.target===this) closeKeywordModal()">
    <div class="ckw-modal" role="dialog" aria-modal="true" aria-labelledby="keywordModalTitle">
        <div class="ckw-modal-header">
            <h3 id="keywordModalTitle"><i class="fas fa-key text-[#10A37F]"></i> <span id="keywordModalTitleText"><?php echo t('Create Keyword'); ?></span></h3>
            <button type="button" class="ckw-modal-close" onclick="closeKeywordModal()" aria-label="<?php echo t('Close'); ?>"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="ckw-modal-body">
            <form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=category_keywords">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" id="keywordAction" value="create">
                <input type="hidden" name="keyword_id" id="keywordId" value="0">
                <div class="form-group">
                    <label class="form-label" for="keywordCategory"><?php echo t('Linked Category'); ?> <span class="text-red-500">*</span></label>
                    <select name="category_id" id="keywordCategory" class="form-input" required>
                        <option value=""><?php echo t('Select a category'); ?></option>
                        <?php foreach ($CATEGORY_OPTIONS as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="ckw-sm text-gray-400 mt-1"><?php echo t('The official category this keyword should point to.'); ?></div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" for="keywordText"><?php echo t('Keyword'); ?> <span class="text-red-500">*</span></label>
                    <input type="text" name="keyword" id="keywordText" class="form-input" maxlength="100" required placeholder="e.g. basura, baha, usok, clogged" autocomplete="off">
                    <div class="ckw-sm text-gray-400 mt-1"><?php echo t('Common local term, Tagalog phrase, or slang. One word or phrase per entry.'); ?></div>
                </div>
                <div class="flex gap-2 mt-5">
                    <button type="submit" class="btn-primary flex-1"><i class="fas fa-check mr-1.5"></i> <?php echo t('Save Keyword'); ?></button>
                    <button type="button" class="btn-secondary" onclick="closeKeywordModal()"><?php echo t('Cancel'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openKeywordModal(id, keyword, categoryId) {
    document.getElementById('keywordModalTitleText').textContent = id ? 'Edit Keyword' : 'Create Keyword';
    document.getElementById('keywordAction').value = id ? 'update' : 'create';
    document.getElementById('keywordId').value = id || 0;
    document.getElementById('keywordText').value = keyword || '';
    document.getElementById('keywordCategory').value = categoryId || '';
    document.getElementById('keywordModal').classList.add('active');
    document.getElementById('keywordText').focus();
}
function closeKeywordModal() {
    document.getElementById('keywordModal').classList.remove('active');
}
</script>