<?php
// views/admin/settings/partials/permissions.php
// Section 10: Permissions — Role-Based Access Control (RBAC)
// Restricts what local admins (Barangay Admins, MENRO Staff) can do.
// Included into the settings page shell when ?tab=permissions is active.
//
// Layout:
//   TOP    -> branding banner + inline "Create Role" form
//   BOTTOM -> list of roles (names only); clicking a role expands its
//            permission toggles in an accordion panel.
//
// NOTE: Every role's permission checkboxes stay in the DOM inside
// #permissionsForm (collapsed panels are only hidden with CSS). The
// controller's savePermissionToggles() re-reads ALL roles from the form,
// so hiding a panel must not remove its inputs.
// Expects $csrf_token to already be available in scope (as with the other partials).

if (!isset($csrf_token)) {
    $csrf_token = InputSanitizer::generateCsrfToken();
}

$allRoles        = SettingsHelper::getAllRoles();               // full rows incl. id/title/description/is_system
$roles           = SettingsHelper::getManageableRoles();        // id => title
$permissionKeys  = SettingsHelper::getPermissionKeys();
$rolePermissions = SettingsHelper::getAllRolePermissions();      // role_id => [perm_key => bool]
$system_name     = SettingsHelper::get('system_name', 'Sierra');

// Built-in roles (seeded by the migration) keep their curated icon/color;
// any admin-created ("Create Role") role falls back to a neutral style.
$systemRoleIconsByTitle = [
    'MENRO Staff'   => 'fa-crown',
    'Barangay Admin' => 'fa-landmark',
];
$systemRoleColorsByTitle = [
    'MENRO Staff'    => ['bg' => '#FFF7ED', 'border' => '#FDBA74', 'icon' => '#EA580C', 'badge_bg' => '#FED7AA', 'badge_text' => '#9A3412'],
    'Barangay Admin' => ['bg' => '#EFF6FF', 'border' => '#93C5FD', 'icon' => '#2563EB', 'badge_bg' => '#DBEAFE', 'badge_text' => '#1E40AF'],
];
$defaultRoleColor = ['bg' => '#F5F3FF', 'border' => '#C4B5FD', 'icon' => '#7C3AED', 'badge_bg' => '#EDE9FE', 'badge_text' => '#5B21B6'];
$defaultRoleIcon  = 'fa-user-shield';

$permissionIcons = [
    'can_view_reports'    => 'fa-eye',
    'can_manage_reports'  => 'fa-clipboard-list',
    'can_view_map'        => 'fa-map-marked-alt',
    'can_view_analytics'  => 'fa-chart-line',
    'can_manage_evidence' => 'fa-images',
    'can_manage_users'    => 'fa-user-cog',
    'can_manage_staff'    => 'fa-user-tie',
    'can_export_reports'  => 'fa-file-export',
    'can_manage_system'   => 'fa-sliders-h',
];
$permissionDescriptions = [
    'can_view_reports'    => 'View environmental reports and their full details.',
    'can_manage_reports'  => 'Verify, escalate, resolve, reject, and update reports.',
    'can_view_map'        => 'View report locations and geotagged environmental incidents on the map.',
    'can_view_analytics'  => 'View statistics, trends, and decision-support dashboards.',
    'can_manage_evidence' => 'View, upload, and manage report photos and resolution evidence.',
    'can_manage_users'    => 'Manage citizen accounts, profiles, and their activity.',
    'can_manage_staff'    => 'Manage barangay personnel and MENRO staff accounts.',
    'can_export_reports'  => 'Download and export reports as PDF documents.',
    'can_manage_system'   => 'Manage system settings, categories, announcements, and configuration.',
];
$permissionRisk = [
    'can_view_reports'    => 'low',
    'can_manage_reports'  => 'medium',
    'can_view_map'        => 'low',
    'can_view_analytics'  => 'low',
    'can_manage_evidence' => 'medium',
    'can_manage_users'    => 'high',
    'can_manage_staff'    => 'high',
    'can_export_reports'  => 'low',
    'can_manage_system'   => 'high',
];
?>

<style>
/* ================================================================
   BRANDING BANNER
   ================================================================ */
.perm-hero {
    position: relative;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.75rem 1.75rem;
    border-radius: 1.25rem;
    background: linear-gradient(135deg, #0D8568 0%, #10A37F 55%, #34C79E 100%);
    color: white;
    overflow: hidden;
    margin-bottom: 1.5rem;
    box-shadow: 0 10px 30px rgba(16, 163, 127, 0.25);
}
.perm-hero::after {
    content: '';
    position: absolute;
    right: -60px;
    top: -60px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
}
.perm-hero::before {
    content: '';
    position: absolute;
    right: 40px;
    bottom: -80px;
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
}
.perm-hero-icon {
    width: 58px;
    height: 58px;
    flex-shrink: 0;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.perm-hero-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255, 255, 255, 0.85);
    margin-bottom: 0.25rem;
}
.perm-hero-title {
    font-size: 1.45rem;
    font-weight: 800;
    line-height: 1.2;
}
.perm-hero-sub {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.9);
    margin-top: 0.3rem;
    max-width: 520px;
}
.perm-hero-stats {
    margin-left: auto;
    display: flex;
    gap: 0.75rem;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}
.perm-hero-stat {
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 0.9rem;
    padding: 0.7rem 1.1rem;
    text-align: center;
    min-width: 82px;
    backdrop-filter: blur(4px);
}
.perm-hero-stat span {
    display: block;
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1;
}
.perm-hero-stat small {
    display: block;
    font-size: 0.62rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.85);
    margin-top: 0.3rem;
}

/* ================================================================
   CREATE / EDIT ROLE CARD
   ================================================================ */
.perm-card {
    background: white;
    border-radius: 1rem;
    border: 1.5px solid #e5e7eb;
    overflow: hidden;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
    margin-bottom: 1.5rem;
}
.perm-card:hover { box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06); }
.create-role-card.editing {
    border-color: #10A37F;
    box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.12);
}
.create-role-head {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 1.1rem 1.5rem;
    background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
    border-bottom: 1px solid #f0fdf4;
}
.create-role-head h3 {
    font-size: 1rem;
    font-weight: 800;
    color: #065f46;
}
.create-role-head p {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.1rem;
}
.create-role-icon {
    width: 42px;
    height: 42px;
    border-radius: 0.75rem;
    background: #10A37F;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.perm-create-body { padding: 1.4rem 1.5rem 1.5rem; }
.create-role-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 640px) { .create-role-fields { grid-template-columns: 1fr; } }

/* Permission picker (create/edit role) */
.perm-option-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem;
    margin-top: 0.4rem;
    margin-bottom: 0.75rem;
}
@media (max-width: 640px) { .perm-option-grid { grid-template-columns: 1fr; } }
.perm-option {
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
    border: 1.5px solid #eef2ef;
    border-radius: 0.8rem;
    padding: 0.75rem 0.9rem;
    cursor: pointer;
    transition: all 0.18s;
    background: white;
}
.perm-option:hover { border-color: #c6e6dc; background: #fafefc; }
.perm-option:has(input:checked) {
    border-color: #10A37F;
    background: #f0fdf4;
    box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08);
}
.perm-option input {
    margin-top: 0.2rem;
    width: 16px;
    height: 16px;
    accent-color: #10A37F;
    flex-shrink: 0;
}
.perm-option-icon {
    width: 30px;
    height: 30px;
    flex-shrink: 0;
    border-radius: 0.5rem;
    background: #f3f4f6;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
}
.perm-option:has(input:checked) .perm-option-icon {
    background: #10A37F;
    color: white;
}
.perm-option-text { min-width: 0; }
.perm-option-title {
    display: block;
    font-size: 0.8rem;
    font-weight: 700;
    color: #1f2937;
    line-height: 1.2;
}
.perm-option-desc {
    display: block;
    font-size: 0.68rem;
    color: #9ca3af;
    margin-top: 0.15rem;
    line-height: 1.35;
}
.create-role-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
}

/* ================================================================
   ROLES LIST
   ================================================================ */
.roles-section { margin-top: 0.25rem; }
.roles-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.roles-section-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.roles-section-title i { color: #10A37F; font-size: 0.95rem; }
.roles-section-sub {
    font-size: 0.78rem;
    color: #6b7280;
    margin-top: 0.15rem;
}

/* Role row (name only) */
.role-item {
    border: 1.5px solid #e5e7eb;
    border-radius: 1rem;
    background: white;
    margin-bottom: 0.9rem;
    overflow: hidden;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.role-item:hover { border-color: #d1d5db; box-shadow: 0 3px 14px rgba(0, 0, 0, 0.05); }
.role-item.open {
    border-color: #10A37F;
    box-shadow: 0 4px 18px rgba(16, 163, 127, 0.12);
}
.role-item-head {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 0.9rem 1.25rem;
    cursor: pointer;
    user-select: none;
    transition: background 0.18s;
}
.role-item.open .role-item-head { background: #f7fdfa; }
.role-item-head:hover { background: #fafafa; }
.role-item.open .role-item-head:hover { background: #f2fbf7; }
.role-item-icon {
    width: 42px;
    height: 42px;
    border-radius: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.role-item-info { flex: 1; min-width: 0; }
.role-item-title-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.role-item-name {
    font-size: 0.92rem;
    font-weight: 800;
    color: #111827;
}
.role-item-desc {
    font-size: 0.73rem;
    color: #9ca3af;
    margin-top: 0.12rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 560px;
}
.role-item-count {
    font-size: 0.78rem;
    font-weight: 800;
    background: #f9fafb;
    border: 1px solid #f0f0f0;
    padding: 0.25rem 0.7rem;
    border-radius: 9999px;
    white-space: nowrap;
    flex-shrink: 0;
}
.role-item-actions {
    display: flex;
    gap: 0.35rem;
    flex-shrink: 0;
}
.role-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 0.55rem;
    border: none;
    background: #f3f4f6;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.18s;
}
.role-action-btn:hover { background: #e6f7f1; color: #10A37F; }
.role-action-btn.danger:hover { background: #fef2f2; color: #ef4444; }
.role-item-chevron {
    color: #cbd5e1;
    transition: transform 0.25s;
    flex-shrink: 0;
}
.role-item.open .role-item-chevron { transform: rotate(180deg); color: #10A37F; }

/* Accordion panel (permissions for the clicked role) */
.role-perm-panel {
    display: none;
    border-top: 1px solid #f0f4f1;
}
.role-perm-panel.open {
    display: block;
    animation: permFade 0.25s ease-out;
}
@keyframes permFade {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}
.role-perm-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.8rem 1.25rem;
    background: #f9fafb;
    border-bottom: 1px solid #f0f0f0;
}
.role-perm-panel-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #374151;
}
.perm-counter {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
}

/* ================================================================
   PERMISSION ROWS
   ================================================================ */
.perm-row {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid #f9fafb;
    transition: background 0.15s;
}
.perm-row:last-child { border-bottom: none; }
.perm-row:hover { background: #fafafa; }
.perm-info { display: flex; align-items: center; gap: 0.85rem; }
.perm-icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 0.6rem;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    color: #6b7280;
    flex-shrink: 0;
    transition: all 0.2s;
}
.perm-row:hover .perm-icon-wrap { background: #e9faf5; color: #10A37F; }
.perm-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.1rem;
}
.perm-desc { font-size: 0.75rem; color: #9ca3af; }

/* ===== RISK BADGES ===== */
.risk-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.55rem;
    border-radius: 9999px;
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-left: 0.4rem;
}
.risk-high   { background: #FEF2F2; color: #991B1B; }
.risk-medium { background: #FFFBEB; color: #92400E; }
.risk-low    { background: #F0FDF4; color: #14532D; }

/* ===== CUSTOM TOGGLE ===== */
.perm-toggle-wrap { display: flex; align-items: center; gap: 0.5rem; }
.perm-toggle {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
    flex-shrink: 0;
    cursor: pointer;
}
.perm-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
.perm-slider {
    position: absolute;
    inset: 0;
    background: #d1d5db;
    border-radius: 9999px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.perm-slider::before {
    content: '';
    position: absolute;
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background: white;
    border-radius: 50%;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
}
.perm-toggle input:checked + .perm-slider { background: #10A37F; }
.perm-toggle input:checked + .perm-slider::before { transform: translateX(24px); }
.perm-toggle input:focus-visible + .perm-slider {
    outline: 2px solid #10A37F;
    outline-offset: 2px;
}
.toggle-state-label {
    font-size: 0.75rem;
    font-weight: 600;
    width: 30px;
    color: #9ca3af;
    transition: color 0.2s;
}

/* ===== ROLE BADGE ===== */
.perm-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.18rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* ===== PANEL NOTE ===== */
.role-panel-note {
    padding: 0.75rem 1.25rem;
    background: #FFFBEB;
    color: #92400E;
    font-size: 0.72rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

/* ===== ACTIONS BAR ===== */
.perm-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid #f0f0f0;
    margin-top: 1.25rem;
}
.perm-info-notice {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.9rem 1.1rem;
    background: #f0f9ff;
    border: 1px solid #bfdbfe;
    border-radius: 0.9rem;
    margin-top: 1rem;
}
.perm-info-notice i { color: #3b82f6; margin-top: 0.15rem; }
.perm-info-notice p { font-size: 0.72rem; color: #1e40af; line-height: 1.5; }

/* ===== CHANGE INDICATOR ===== */
#changesIndicator {
    display: none;
    align-items: center;
    gap: 0.5rem;
    background: #FFF7ED;
    border: 1px solid #FED7AA;
    color: #C2410C;
    padding: 0.5rem 1rem;
    border-radius: 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.d-none { display: none !important; }

/* ===== RESPONSIVE ===== */
@media (max-width: 640px) {
    .perm-hero { flex-wrap: wrap; }
    .perm-hero-stats { margin-left: 0; width: 100%; }
    .perm-hero-stat { flex: 1; }
    .perm-row { grid-template-columns: 1fr; gap: 0.5rem; }
    .perm-toggle-wrap { justify-content: flex-start; }
    .role-item-head { flex-wrap: wrap; }
}
</style>

<div class="fade-in">

    <!-- ===== BRANDING BANNER ===== -->
    <div class="perm-hero">
        <div class="perm-hero-icon">
            <i class="fas fa-user-shield"></i>
        </div>
        <div class="flex-1 min-w-0">
            <span class="perm-hero-kicker">
                <i class="fas fa-key" style="font-size:0.55rem;"></i> Role-Based Access Control
            </span>
            <h2 class="perm-hero-title">Permissions &amp; Roles</h2>
            <p class="perm-hero-sub">
                Grant or restrict what each role can do in <?php echo htmlspecialchars($system_name); ?>.
            </p>
        </div>
        <div class="perm-hero-stats">
            <div class="perm-hero-stat">
                <span><?php echo count($allRoles); ?></span>
                <small>Roles</small>
            </div>
            <div class="perm-hero-stat">
                <span><?php echo count($permissionKeys); ?></span>
                <small>Permissions</small>
            </div>
        </div>
    </div>

    <!-- ===== CREATE / EDIT ROLE (TOP) ===== -->
    <div class="perm-card create-role-card" id="createRoleCard">
        <div class="create-role-head">
            <div class="create-role-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="flex-1">
                <h3 id="roleFormHeading">Create New Role</h3>
                <p id="roleFormSubtitle">Define a new role and choose which permissions it is granted.</p>
            </div>
        </div>

        <form method="POST"
              action="<?php echo BASE_URL; ?>controllers/SettingsController.php?tab=permissions"
              class="perm-create-body" id="roleForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="sub_action" id="roleSubAction" value="create_role">
            <input type="hidden" name="role_id" id="roleFormId" value="">

            <div class="create-role-fields">
                <div class="form-group">
                    <label class="form-label" for="roleFormTitle">Role Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="roleFormTitle" required maxlength="100"
                           class="form-input"
                           placeholder="e.g. MENRO Report Manager">
                </div>
                <div class="form-group">
                    <label class="form-label" for="roleFormDescription">Description (optional)</label>
                    <textarea name="description" id="roleFormDescription" rows="2" maxlength="500"
                              class="form-input"
                              placeholder="What is this role for?"></textarea>
                </div>
            </div>

            <label class="form-label">Permissions</label>
            <div class="perm-option-grid">
                <?php foreach ($permissionKeys as $permKey => $permLabel):
                    $pIcon = $permissionIcons[$permKey] ?? 'fa-check';
                    $pDesc = $permissionDescriptions[$permKey] ?? '';
                ?>
                <label class="perm-option">
                    <input type="checkbox" name="permissions[<?php echo htmlspecialchars($permKey); ?>]"
                           value="1" class="role-form-perm-checkbox" data-perm="<?php echo htmlspecialchars($permKey); ?>">
                    <span class="perm-option-icon"><i class="fas <?php echo $pIcon; ?>"></i></span>
                    <span class="perm-option-text">
                        <span class="perm-option-title"><?php echo htmlspecialchars($permLabel); ?></span>
                        <span class="perm-option-desc"><?php echo htmlspecialchars($pDesc); ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="create-role-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i><span id="roleFormSubmitLabel">Save Role</span>
                </button>
                <button type="button" id="roleFormCancelBtn" onclick="resetRoleForm()" class="btn-secondary d-none">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
            </div>
        </form>
    </div>

    <!-- ===== ROLES LIST (BOTTOM) ===== -->
    <div class="roles-section">
        <div class="roles-section-head">
            <div>
                <h3 class="roles-section-title">
                    <i class="fas fa-id-badge"></i> Roles
                </h3>
                <p class="roles-section-sub">Click a role to view and edit its permissions.</p>
            </div>
            <div id="changesIndicator">
                <i class="fas fa-exclamation-circle"></i>
                <span id="changesCount">0</span> unsaved change(s)
            </div>
        </div>

        <form method="POST"
              action="<?php echo BASE_URL; ?>controllers/SettingsController.php?tab=permissions"
              id="permissionsForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <?php foreach ($allRoles as $roleRow):
                $roleKey     = (int)$roleRow['id'];
                $roleLabel   = $roleRow['title'];
                $isSystem    = (int)$roleRow['is_system'] === 1;
                $colors      = $systemRoleColorsByTitle[$roleLabel] ?? $defaultRoleColor;
                $icon        = $systemRoleIconsByTitle[$roleLabel] ?? $defaultRoleIcon;
                $desc        = $roleRow['description'] ?? '';
                $perms       = $rolePermissions[$roleKey] ?? [];
                $grantedCount = count(array_filter($perms));
                $totalCount   = count($permissionKeys);
            ?>
            <div class="role-item" id="role-item-<?php echo $roleKey; ?>">

                <!-- Role row: name only -->
                <div class="role-item-head" onclick="toggleRolePanel(<?php echo $roleKey; ?>)">
                    <div class="role-item-icon"
                         style="background: <?php echo $colors['badge_bg']; ?>; color: <?php echo $colors['icon']; ?>;">
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="role-item-info">
                        <div class="role-item-title-row">
                            <h4 class="role-item-name"><?php echo htmlspecialchars($roleLabel); ?></h4>
                            <span class="perm-role-badge"
                                  style="background: <?php echo $colors['badge_bg']; ?>; color: <?php echo $colors['badge_text']; ?>;">
                                <?php echo $isSystem ? 'built-in' : 'custom'; ?>
                            </span>
                        </div>
                        <?php if ($desc): ?>
                        <p class="role-item-desc"><?php echo htmlspecialchars($desc); ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="role-item-count" style="color: <?php echo $colors['icon']; ?>;">
                        <span id="counter-<?php echo $roleKey; ?>"><?php echo $grantedCount; ?></span>/<?php echo $totalCount; ?>
                    </span>
                    <div class="role-item-actions" onclick="event.stopPropagation()">
                        <button type="button" title="Edit role"
                                onclick='editRole(<?php echo json_encode([
                                    "id" => $roleKey,
                                    "title" => $roleLabel,
                                    "description" => $desc,
                                    "permissions" => $perms,
                                ]); ?>)'
                                class="role-action-btn">
                            <i class="fas fa-pen"></i>
                        </button>
                        <?php if (!$isSystem): ?>
                        <button type="button" title="Delete role"
                                onclick="deleteRole(<?php echo $roleKey; ?>, '<?php echo htmlspecialchars(addslashes($roleLabel)); ?>')"
                                class="role-action-btn danger">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="role-item-chevron"><i class="fas fa-chevron-down"></i></div>
                </div>

                <!-- Permission accordion (shown only when clicked) -->
                <div class="role-perm-panel" id="panel-<?php echo $roleKey; ?>">
                    <div class="role-perm-panel-head">
                        <span class="role-perm-panel-title">
                            Permissions for
                            <strong style="color: <?php echo $colors['icon']; ?>;"><?php echo htmlspecialchars($roleLabel); ?></strong>
                        </span>
                        <span class="perm-counter">
                            <i class="fas fa-check-circle text-emerald-500"></i>
                            <span id="granted-<?php echo $roleKey; ?>"><?php echo $grantedCount; ?></span> of <?php echo $totalCount; ?> granted
                        </span>
                    </div>

                    <?php foreach ($permissionKeys as $permKey => $permLabel):
                        $isGranted = !empty($perms[$permKey]);
                        $pIcon     = $permissionIcons[$permKey] ?? 'fa-check';
                        $pDesc     = $permissionDescriptions[$permKey] ?? '';
                        $risk      = $permissionRisk[$permKey] ?? 'low';
                        $riskLabel = ucfirst($risk) . ' Risk';
                        $inputId   = 'perm_' . $roleKey . '_' . $permKey;
                    ?>
                    <div class="perm-row">
                        <div class="perm-info">
                            <div class="perm-icon-wrap">
                                <i class="fas <?php echo $pIcon; ?>"></i>
                            </div>
                            <div>
                                <div class="flex items-center flex-wrap">
                                    <span class="perm-label"><?php echo htmlspecialchars($permLabel); ?></span>
                                    <span class="risk-badge risk-<?php echo $risk; ?>">
                                        <?php if ($risk === 'high'): ?>
                                            <i class="fas fa-shield-alt" style="font-size:0.5rem;"></i>
                                        <?php elseif ($risk === 'medium'): ?>
                                            <i class="fas fa-exclamation-triangle" style="font-size:0.5rem;"></i>
                                        <?php else: ?>
                                            <i class="fas fa-check-circle" style="font-size:0.5rem;"></i>
                                        <?php endif; ?>
                                        <?php echo $riskLabel; ?>
                                    </span>
                                </div>
                                <p class="perm-desc"><?php echo htmlspecialchars($pDesc); ?></p>
                            </div>
                        </div>
                        <div class="perm-toggle-wrap">
                            <label class="perm-toggle"
                                   for="<?php echo $inputId; ?>"
                                   title="<?php echo $isGranted ? 'Granted — click to revoke' : 'Denied — click to grant'; ?>">
                                <input type="checkbox"
                                       id="<?php echo $inputId; ?>"
                                       name="permissions[<?php echo htmlspecialchars($roleKey); ?>][<?php echo htmlspecialchars($permKey); ?>]"
                                       value="1"
                                       data-role="<?php echo htmlspecialchars($roleKey); ?>"
                                       data-perm="<?php echo htmlspecialchars($permKey); ?>"
                                       data-original="<?php echo $isGranted ? '1' : '0'; ?>"
                                       class="perm-checkbox"
                                       <?php echo $isGranted ? 'checked' : ''; ?>>
                                <span class="perm-slider"></span>
                            </label>
                            <span class="toggle-state-label" id="label-<?php echo $inputId; ?>">
                                <?php echo $isGranted ? 'On' : 'Off'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($roleLabel === 'MENRO Staff'): ?>
                    <div class="role-panel-note">
                        <i class="fas fa-info-circle"></i>
                        The primary super-admin account is always unrestricted
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- ===== ACTIONS ===== -->
            <div class="perm-actions">
                <button type="button" onclick="resetPermissionsForm()" class="btn-secondary">
                    <i class="fas fa-undo mr-2"></i> Reset to Saved
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i> Save Permissions
                </button>
            </div>
        </form>

        <!-- ===== INFO NOTICE ===== -->
        <div class="perm-info-notice">
            <i class="fas fa-info-circle flex-shrink-0"></i>
            <p>
                <strong>How permissions work:</strong>
                Disabled toggles are <strong>denied</strong> by default.
                Granting <strong>Manage Reports</strong> always includes
                <strong>View Reports</strong>.
                Citizens are never granted admin permissions.
                The primary super-admin account bypasses all restrictions.
                <strong>Audit Logs</strong> are read-only and reserved for the
                System Administrator — they are not a configurable permission.
                Changes apply to every account with the matching role.
            </p>
        </div>
    </div>

    <!-- Hidden form used for role deletion (confirm() then submit) -->
    <form method="POST"
          action="<?php echo BASE_URL; ?>controllers/SettingsController.php?tab=permissions"
          id="deleteRoleForm" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="sub_action" value="delete_role">
        <input type="hidden" name="role_id" id="deleteRoleId" value="">
    </form>

</div>

<script>
(function () {
    'use strict';

    const form      = document.getElementById('permissionsForm');
    const indicator = document.getElementById('changesIndicator');
    const countEl   = document.getElementById('changesCount');

    function countChanges() {
        return [...document.querySelectorAll('.perm-checkbox')].filter(function(cb) {
            return (cb.checked ? '1' : '0') !== cb.dataset.original;
        }).length;
    }

    function updateGrantedCounter(roleKey) {
        var checked = document.querySelectorAll(
            '.perm-checkbox[data-role="' + roleKey + '"]:checked'
        ).length;

        var counterEl = document.getElementById('counter-' + roleKey);
        var grantedEl = document.getElementById('granted-' + roleKey);
        if (counterEl) counterEl.textContent = checked;
        if (grantedEl) grantedEl.textContent = checked;
    }

    function updateToggleLabel(cb) {
        var labelEl = document.getElementById('label-' + cb.id);
        if (labelEl) labelEl.textContent = cb.checked ? 'On' : 'Off';
    }

    // "Manage Reports" always implies "View Reports": turning Manage ON
    // forces View ON; turning View OFF forces Manage OFF.
    function enforceManageViewDependency(root) {
        if (!root) return;
        var viewCb   = root.querySelector('[data-perm="can_view_reports"]');
        var manageCb = root.querySelector('[data-perm="can_manage_reports"]');
        if (!viewCb || !manageCb) return;

        if (manageCb.checked && !viewCb.checked) {
            viewCb.checked = true;
            updateToggleLabel(viewCb);
        }
        if (!viewCb.checked && manageCb.checked) {
            manageCb.checked = false;
            updateToggleLabel(manageCb);
        }
    }

    // ============================================================
    // ACCORDION — clicking a role shows only that role's permissions
    // ============================================================
    window.toggleRolePanel = function (roleKey) {
        const panel = document.getElementById('panel-' + roleKey);
        if (!panel) return;

        const item    = document.getElementById('role-item-' + roleKey);
        const wasOpen = panel.classList.contains('open');

        // Close every panel first (single-open accordion).
        document.querySelectorAll('.role-perm-panel').forEach(function (p) {
            p.classList.remove('open');
        });
        document.querySelectorAll('.role-item').forEach(function (i) {
            i.classList.remove('open');
        });

        if (!wasOpen) {
            panel.classList.add('open');
            if (item) item.classList.add('open');
        }
    };

    // ============================================================
    // PERMISSION TOGGLES
    // ============================================================
    document.querySelectorAll('.perm-checkbox').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var roleScope = cb.closest('.role-item');
            enforceManageViewDependency(roleScope);
            if (roleScope) {
                roleScope.querySelectorAll('.perm-checkbox').forEach(updateToggleLabel);
            }

            updateGrantedCounter(cb.dataset.role);

            var changes = countChanges();
            if (countEl) countEl.textContent = changes;
            if (indicator) indicator.style.display = changes > 0 ? 'flex' : 'none';
        });
    });

    // Enforce the same dependency in the Create/Edit Role permission picker.
    document.querySelectorAll('.role-form-perm-checkbox').forEach(function (cb) {
        cb.addEventListener('change', function () {
            enforceManageViewDependency(cb.closest('.perm-option-grid'));
        });
    });

    form.addEventListener('submit', function () {
        if (indicator) indicator.style.display = 'none';
    });

    window.addEventListener('beforeunload', function (e) {
        if (countChanges() > 0) {
            e.preventDefault();
            e.returnValue = 'You have unsaved permission changes. Are you sure you want to leave?';
        }
    });

    window.resetPermissionsForm = function () {
        if (countChanges() === 0) {
            if (typeof showNotification === 'function') {
                showNotification('No unsaved changes to reset.', 'info');
            }
            return;
        }
        if (confirm('Reset all permissions to their last-saved values? Unsaved changes will be lost.')) {
            location.reload();
        }
    };

    // ============================================================
    // CREATE / EDIT ROLE (inline form at the top)
    // ============================================================
    window.editRole = function (role) {
        document.getElementById('roleForm').reset();

        const heading      = document.getElementById('roleFormHeading');
        const subtitle     = document.getElementById('roleFormSubtitle');
        const submitLabel  = document.getElementById('roleFormSubmitLabel');
        const subAction    = document.getElementById('roleSubAction');
        const roleFormId   = document.getElementById('roleFormId');
        const titleInput   = document.getElementById('roleFormTitle');
        const descInput    = document.getElementById('roleFormDescription');
        const cancelBtn    = document.getElementById('roleFormCancelBtn');
        const card         = document.getElementById('createRoleCard');
        const checkboxes   = document.querySelectorAll('.role-form-perm-checkbox');

        heading.textContent  = 'Edit Role';
        subtitle.textContent = 'Update "' + (role.title || '') + '" — changes apply to every account with this role.';
        submitLabel.textContent = 'Save Changes';
        subAction.value = 'update_role';
        roleFormId.value = role.id;
        titleInput.value = role.title || '';
        descInput.value = role.description || '';
        checkboxes.forEach(function (cb) {
            cb.checked = !!(role.permissions && role.permissions[cb.dataset.perm]);
        });
        enforceManageViewDependency(document.querySelector('.perm-option-grid'));

        cancelBtn.classList.remove('d-none');
        card.classList.add('editing');
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.resetRoleForm = function () {
        document.getElementById('roleForm').reset();
        document.getElementById('roleFormHeading').textContent = 'Create New Role';
        document.getElementById('roleFormSubtitle').textContent = 'Define a new role and choose which permissions it is granted.';
        document.getElementById('roleFormSubmitLabel').textContent = 'Save Role';
        document.getElementById('roleSubAction').value = 'create_role';
        document.getElementById('roleFormId').value = '';
        document.getElementById('roleFormCancelBtn').classList.add('d-none');
        document.getElementById('createRoleCard').classList.remove('editing');
    };

    window.deleteRole = function (roleId, roleTitle) {
        if (confirm('Delete the role "' + roleTitle + '"? This cannot be undone, and only works if no users are currently assigned to it.')) {
            document.getElementById('deleteRoleId').value = roleId;
            document.getElementById('deleteRoleForm').submit();
        }
    };

})();
</script>
