<?php
// views/admin/settings/partials/quick_notes.php
// Quick Note Templates — embedded as the "Quick Note Templates" tab of
// System Settings. Full CRUD for smart-suggestion (canned response)
// templates used on the Manage Report page (Investigation Notes) and
// inside the Resolve Report and Escalate to MENRO modals.
//
// POST actions (create / update / delete / toggle_status) are handled
// inline here (the settings shell routes ?tab=quick_notes POSTs to this
// partial) and redirect back to Settings > Quick Note Templates.

require_once BASE_PATH . 'helpers/PermissionHelper.php';

// Permission gate (super-admin bypasses via PermissionHelper).
if (!PermissionHelper::userHasPermission('can_manage_system')) {
    $_SESSION['error'] = "You are not permitted to manage quick note templates.";
    header("Location: " . BASE_URL . "index.php?page=settings&tab=quick_notes");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$activityLog = new ActivityLog($db);

// ============================================================
// STATUS + CATEGORY OPTIONS
// ============================================================
$QUICK_NOTE_STATUSES = [
    ''                 => 'Investigation — Any active status',
    'pending'           => 'Investigation — Pending',
    'under_review'      => 'Investigation — Under Review',
    'verified'          => 'Investigation — Verified',
    'in_progress'       => 'Investigation — In Progress',
    'escalated_pending' => 'Escalate to MENRO',
    'escalated'         => 'Escalate to MENRO',
    'resolved'          => 'Mark as Resolved',
];

// Category options come straight from the categories table.
// target_category stores the category NAME ('' = all categories).
$CATEGORY_OPTIONS = [];
try {
    $cat_stmt = $db->query("SELECT name FROM categories ORDER BY name ASC");
    $CATEGORY_OPTIONS = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $CATEGORY_OPTIONS = [];
    error_log("[QuickNotes] categories unavailable: " . $e->getMessage());
}

// ============================================================
// HANDLE POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect_url = BASE_URL . "index.php?page=settings&tab=quick_notes";

    // CSRF protection
    if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: " . $redirect_url);
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $template_id = (int)($_POST['template_id'] ?? 0);
        $template_text = InputSanitizer::sanitizeString($_POST['template_text'] ?? '', 2000);
        $target_category = trim((string)($_POST['target_category'] ?? ''));
        $target_status = trim((string)($_POST['target_status'] ?? ''));

        if (mb_strlen($template_text) < 2) {
            $_SESSION['error'] = "Template text is required (at least 2 characters).";
            header("Location: " . $redirect_url);
            exit();
        }
        if (!array_key_exists($target_status, $QUICK_NOTE_STATUSES)) {
            $_SESSION['error'] = "Invalid target status.";
            header("Location: " . $redirect_url);
            exit();
        }
        if ($target_category !== '' && !in_array($target_category, $CATEGORY_OPTIONS, true)) {
            $_SESSION['error'] = "Invalid target category.";
            header("Location: " . $redirect_url);
            exit();
        }

        if ($action === 'create') {
            $stmt = $db->prepare("INSERT INTO quick_note_templates (template_text, target_category, target_status, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$template_text, $target_category, $target_status, $_SESSION['user_id']]);
            $template_id = (int)$db->lastInsertId();
            $activityLog->log($_SESSION['user_id'], 'Create Quick Note Template', "Created quick note template #$template_id", null, 'Quick Notes');
            $_SESSION['success'] = "Template created successfully!";
        } else {
            if ($template_id <= 0) {
                $_SESSION['error'] = "Invalid template.";
                header("Location: " . $redirect_url);
                exit();
            }
            $stmt = $db->prepare("UPDATE quick_note_templates SET template_text = ?, target_category = ?, target_status = ? WHERE id = ?");
            $stmt->execute([$template_text, $target_category, $target_status, $template_id]);
            $activityLog->log($_SESSION['user_id'], 'Update Quick Note Template', "Updated quick note template #$template_id", null, 'Quick Notes');
            $_SESSION['success'] = "Template updated successfully!";
        }
        header("Location: " . $redirect_url);
        exit();
    }

    if ($action === 'delete') {
        $template_id = (int)($_POST['template_id'] ?? 0);
        if ($template_id > 0) {
            $stmt = $db->prepare("DELETE FROM quick_note_templates WHERE id = ?");
            $stmt->execute([$template_id]);
            $activityLog->log($_SESSION['user_id'], 'Delete Quick Note Template', "Deleted quick note template #$template_id", null, 'Quick Notes');
            $_SESSION['success'] = "Template deleted successfully!";
        } else {
            $_SESSION['error'] = "Invalid template.";
        }
        header("Location: " . $redirect_url);
        exit();
    }

    if ($action === 'toggle_status') {
        $template_id = (int)($_POST['template_id'] ?? 0);
        if ($template_id > 0) {
            $stmt = $db->prepare("SELECT is_active FROM quick_note_templates WHERE id = ?");
            $stmt->execute([$template_id]);
            $current = (int)$stmt->fetchColumn();
            $new = $current ? 0 : 1;
            $stmt = $db->prepare("UPDATE quick_note_templates SET is_active = ? WHERE id = ?");
            $stmt->execute([$new, $template_id]);
            $activityLog->log($_SESSION['user_id'], 'Toggle Quick Note Template', "Toggled quick note template #$template_id to " . ($new ? 'active' : 'inactive'), null, 'Quick Notes');
            $_SESSION['success'] = "Template " . ($new ? 'activated' : 'deactivated') . ".";
        } else {
            $_SESSION['error'] = "Invalid template.";
        }
        header("Location: " . $redirect_url);
        exit();
    }

    $_SESSION['error'] = "Invalid action.";
    header("Location: " . $redirect_url);
    exit();
}

// ============================================================
// GET — LIST TEMPLATES
// ============================================================
$templates = [];
try {
    $stmt = $db->query("SELECT * FROM quick_note_templates ORDER BY is_active DESC, id DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $templates = [];
    error_log("[QuickNotes] list unavailable: " . $e->getMessage());
}

$csrf_token = InputSanitizer::generateCsrfToken();
$total = count($templates);
$active_count = count(array_filter($templates, fn($t) => (int)$t['is_active'] === 1));
?>

<style>
    .qnt-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .qnt-stats { display: flex; gap: 10px; flex-wrap: wrap; }
    .qnt-stat { background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 0.75rem; padding: 0.4rem 0.9rem; font-size: 0.8rem; color: var(--sierra-type-muted, #63746b); }
    .qnt-stat strong { color: var(--sierra-type-primary, #203b31); }
    .qnt-modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(3px); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 16px; }
    .qnt-modal-overlay.active { display: flex; }
    .qnt-modal { background: white; border-radius: 1rem; box-shadow: 0 20px 50px rgba(0,0,0,0.2); width: 100%; max-width: 520px; max-height: 92vh; overflow-y: auto; animation: qntFade 0.2s ease-out; }
    @keyframes qntFade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .qnt-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid #F3F4F6; }
    .qnt-modal-header h3 { font-size: 1rem; font-weight: 700; color: var(--sierra-type-primary, #203b31); display: flex; align-items: center; gap: 0.5rem; }
    .qnt-modal-close { background: #F3F4F6; border: none; width: 30px; height: 30px; border-radius: 9999px; cursor: pointer; color: var(--sierra-type-muted, #63746b); transition: all 0.2s; }
    .qnt-modal-close:hover { background: #E5E7EB; color: var(--sierra-type-primary, #203b31); }
    .qnt-modal-body { padding: 1.25rem; }
    .qnt-sm { font-size: 0.7rem; }
    .qnt-badge { display: inline-flex; align-items: center; gap: 4px; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 700; }
    .qnt-badge.on { background: #ECFDF5; color: #047857; }
    .qnt-badge.off { background: #FEF2F2; color: #B91C1C; }
    .qnt-chip { background: #F1F5F9; color: var(--sierra-type-primary, #203b31); padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.68rem; font-weight: 600; }
    .qnt-empty { text-align: center; padding: 3rem 1rem; color: var(--sierra-type-muted, #63746b); }
    .qnt-empty i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; color: #D1D5DB; }
</style>

<!-- ===== TOOLBAR ===== -->
<div class="qnt-toolbar">
    <div class="qnt-stats stat-cards">
        <span class="qnt-stat"><strong><?php echo $total; ?></strong> templates</span>
        <span class="qnt-stat"><strong><?php echo $active_count; ?></strong> active</span>
    </div>
    <button type="button" class="btn-primary" onclick="openTemplateModal()">
        <i class="fas fa-plus mr-1.5"></i> Create Template
    </button>
</div>

<?php if (empty($templates)): ?>
    <div class="qnt-empty">
        <i class="fas fa-bolt"></i>
        <p class="text-sm font-semibold text-gray-500">No quick note templates yet.</p>
        <p class="text-xs text-gray-400 mt-1">Create a template to show smart suggestion chips to barangay officials and MENRO staff.</p>
    </div>
<?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Template Text</th>
                    <th>Target Category</th>
                    <th>Used For</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $tpl): $tpl_json_text = json_encode($tpl['template_text'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); $tpl_json_cat = json_encode($tpl['target_category'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); $tpl_json_status = json_encode($tpl['target_status'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
                <tr>
                    <td style="max-width: 360px;"><?php echo htmlspecialchars($tpl['template_text']); ?></td>
                    <td>
                        <?php if ($tpl['target_category'] === ''): ?>
                            <span class="qnt-chip">All Categories</span>
                        <?php else: ?>
                            <span class="qnt-chip" style="background:#ECFDF5;color:#065F46;"><?php echo htmlspecialchars($tpl['target_category']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($tpl['target_status'] === ''): ?>
                            <span class="qnt-chip">Investigation — Any active status</span>
                        <?php else: ?>
                            <span class="qnt-chip" style="background:#EFF6FF;color:#1E40AF;"><?php echo htmlspecialchars($QUICK_NOTE_STATUSES[$tpl['target_status']] ?? $tpl['target_status']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$tpl['is_active'] === 1): ?>
                            <span class="qnt-badge on"><i class="fas fa-circle fa-2xs"></i> Active</span>
                        <?php else: ?>
                            <span class="qnt-badge off"><i class="fas fa-circle fa-2xs"></i> Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right; white-space:nowrap;">
                        <div class="flex items-center justify-end gap-1.5">
                            <form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=quick_notes" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="template_id" value="<?php echo (int)$tpl['id']; ?>">
                                <button type="submit" class="text-xs text-gray-400 hover:text-emerald-600 transition p-1.5 hover:bg-emerald-50 rounded-lg" title="<?php echo (int)$tpl['is_active'] === 1 ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas <?php echo (int)$tpl['is_active'] === 1 ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                </button>
                            </form>
                            <button type="button" onclick='openTemplateModal(<?php echo (int)$tpl['id']; ?>, <?php echo $tpl_json_text; ?>, <?php echo $tpl_json_cat; ?>, <?php echo $tpl_json_status; ?>)' class="text-xs text-gray-400 hover:text-emerald-600 transition p-1.5 hover:bg-emerald-50 rounded-lg" title="Edit">
                                <i class="fas fa-pen"></i>
                            </button>
                            <form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=quick_notes" style="display:inline-block;" onsubmit="return confirm('Delete this template?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="template_id" value="<?php echo (int)$tpl['id']; ?>">
                                <button type="submit" class="text-xs text-gray-400 hover:text-red-600 transition p-1.5 hover:bg-red-50 rounded-lg" title="Delete">
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
<div class="qnt-modal-overlay" id="templateModal" onclick="if(event.target===this) closeTemplateModal()">
    <div class="qnt-modal" role="dialog" aria-modal="true" aria-labelledby="templateModalTitle">
        <div class="qnt-modal-header">
            <h3 id="templateModalTitle"><i class="fas fa-bolt text-[#10A37F]"></i> <span id="templateModalTitleText">Create Template</span></h3>
            <button type="button" class="qnt-modal-close" onclick="closeTemplateModal()" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="qnt-modal-body">
            <form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=quick_notes">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" id="templateAction" value="create">
                <input type="hidden" name="template_id" id="templateId" value="0">
                <div class="form-group">
                    <label class="form-label">Target Category</label>
                    <select name="target_category" id="templateCategory" class="form-input">
                        <option value="">All Categories</option>
                        <?php foreach ($CATEGORY_OPTIONS as $cat_name): ?>
                            <option value="<?php echo htmlspecialchars($cat_name); ?>"><?php echo htmlspecialchars($cat_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="qnt-sm text-gray-400 mt-1">Choose which environmental issue this note is for.</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="templateStatus">Used For</label>
                    <select name="target_status" id="templateStatus" class="form-input">
                        <optgroup label="Investigation notes">
                            <?php foreach ($QUICK_NOTE_STATUSES as $st_key => $st_label): if (in_array($st_key, ['resolved', 'escalated_pending', 'escalated'], true)) continue; ?>
                            <option value="<?php echo $st_key; ?>"><?php echo htmlspecialchars($st_label); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Report actions">
                            <option value="escalated_pending">Escalate to MENRO</option>
                            <option value="resolved">Mark as Resolved</option>
                        </optgroup>
                    </select>
                    <div class="qnt-sm text-gray-400 mt-1">Each suggestion appears only in the selected action or investigation status.</div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Template Text</label>
                    <textarea name="template_text" id="templateText" rows="4" class="form-input" required placeholder="e.g. Awaiting MENRO garbage truck for collection."></textarea>
                    <div class="qnt-sm text-gray-400 mt-1">The canned response shown as a clickable suggestion on the manage-report page.</div>
                </div>
                <div class="flex gap-2 mt-5">
                    <button type="submit" class="btn-primary flex-1"><i class="fas fa-check mr-1.5"></i> Save Template</button>
                    <button type="button" class="btn-secondary" onclick="closeTemplateModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openTemplateModal(id, text, category, status) {
    document.getElementById('templateModalTitleText').textContent = id ? 'Edit Template' : 'Create Template';
    document.getElementById('templateAction').value = id ? 'update' : 'create';
    document.getElementById('templateId').value = id || 0;
    document.getElementById('templateText').value = text || '';
    document.getElementById('templateCategory').value = category || '';
    document.getElementById('templateStatus').value = status === 'escalated' ? 'escalated_pending' : (status || '');
    document.getElementById('templateModal').classList.add('active');
    document.getElementById('templateText').focus();
}
function closeTemplateModal() {
    document.getElementById('templateModal').classList.remove('active');
}
</script>
