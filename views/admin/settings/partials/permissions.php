<?php
// views/admin/settings/partials/permissions.php
// Section 10: Permissions — Role-Based Access Control (RBAC)
// Restricts what local admins (Barangay Admins, MENRO Staff) can do.
// Included into the settings page shell when ?tab=permissions is active.
// Expects $csrf_token to already be available in scope (as with the other partials).

if (!isset($csrf_token)) {
    $csrf_token = InputSanitizer::generateCsrfToken();
}

$allRoles        = SettingsHelper::getAllRoles();               // full rows incl. id/title/description/is_system
$roles           = SettingsHelper::getManageableRoles();        // id => title
$permissionKeys  = SettingsHelper::getPermissionKeys();
$rolePermissions = SettingsHelper::getAllRolePermissions();      // role_id => [perm_key => bool]

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
    'can_manage_reports'          => 'fa-clipboard-list',
    'can_edit_settings'           => 'fa-sliders-h',
    'can_manage_categories'       => 'fa-tags',
    'can_delete_users'            => 'fa-user-minus',
    'can_broadcast_announcements' => 'fa-bullhorn',
    'can_export_reports'          => 'fa-file-export',
];
$permissionDescriptions = [
    'can_manage_reports'          => 'Verify, escalate, resolve, or reject environmental reports.',
    'can_edit_settings'           => 'Modify system-wide configuration and settings panels.',
    'can_manage_categories'       => 'Create, edit, or deactivate report categories.',
    'can_delete_users'            => 'Permanently remove user accounts from the system.',
    'can_broadcast_announcements' => 'Send announcements visible across all barangays.',
    'can_export_reports'          => 'Download reports as PDF documents.',
];
$permissionRisk = [
    'can_manage_reports'          => 'medium',
    'can_edit_settings'           => 'high',
    'can_manage_categories'       => 'medium',
    'can_delete_users'            => 'high',
    'can_broadcast_announcements' => 'medium',
    'can_export_reports'          => 'low',
];
?>

<style>
/* ===== PERMISSION CARD ===== */
.perm-card {
    background: white;
    border-radius: 1rem;
    border: 1.5px solid #e5e7eb;
    overflow: hidden;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
    margin-bottom: 1.25rem;
}
.perm-card:hover {
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    border-color: #d1d5db;
}
.perm-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f3f4f6;
}
.perm-role-icon {
    width: 48px;
    height: 48px;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.perm-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* ===== PERMISSION ROWS ===== */
.perm-row {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 1rem;
    padding: 0.9rem 1.5rem;
    border-bottom: 1px solid #f9fafb;
    transition: background 0.15s;
}
.perm-row:last-child { border-bottom: none; }
.perm-row:hover { background: #fafafa; }
.perm-info {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
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
.perm-row:hover .perm-icon-wrap {
    background: #e9faf5;
    color: #10A37F;
}
.perm-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.1rem;
}
.perm-desc {
    font-size: 0.75rem;
    color: #9ca3af;
}

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
.perm-toggle-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.perm-toggle {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
    flex-shrink: 0;
    cursor: pointer;
}
.perm-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}
.perm-slider {
    position: absolute;
    inset: 0;
    background: #d1d5db;
    border-radius: 9999px;
    transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
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
    transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
    box-shadow: 0 1px 4px rgba(0,0,0,0.15);
}
.perm-toggle input:checked + .perm-slider {
    background: #10A37F;
}
.perm-toggle input:checked + .perm-slider::before {
    transform: translateX(24px);
}
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

/* ===== CARD FOOTER ===== */
.perm-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.85rem 1.5rem;
    background: #f9fafb;
    border-top: 1px solid #f0f0f0;
}
.perm-counter {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: #6b7280;
}

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

/* ===== RESPONSIVE ===== */
@media (max-width: 640px) {
    .perm-row { grid-template-columns: 1fr; gap: 0.5rem; }
    .perm-toggle-wrap { justify-content: flex-start; }
    .perm-card-header { flex-wrap: wrap; }
}
</style>

<div class="fade-in">

    <!-- Subtitle + Create Role + unsaved indicator -->
    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
        <p class="text-sm text-gray-500">
            Configure which actions each role can perform system-wide.
        </p>
        <div class="flex items-center gap-3">
            <div id="changesIndicator">
                <i class="fas fa-exclamation-circle"></i>
                <span id="changesCount">0</span> unsaved change(s)
            </div>
            <button type="button" onclick="openRoleModal()" class="btn-primary">
                <i class="fas fa-plus mr-2"></i> Create Role
            </button>
        </div>
    </div>

    <form method="POST"
          action="<?php echo BASE_URL; ?>controllers/SettingsController.php?tab=permissions"
          id="permissionsForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <!-- ===== ROLE CARDS ===== -->
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
        <div class="perm-card">

            <!-- Card Header -->
            <div class="perm-card-header"
                 style="background: <?php echo $colors['bg']; ?>;">
                <div class="perm-role-icon"
                     style="background: <?php echo $colors['badge_bg']; ?>; color: <?php echo $colors['icon']; ?>;">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-base font-bold text-gray-900"><?php echo htmlspecialchars($roleLabel); ?></h3>
                        <span class="perm-role-badge"
                              style="background: <?php echo $colors['badge_bg']; ?>; color: <?php echo $colors['badge_text']; ?>;">
                            <i class="fas <?php echo $icon; ?>" style="font-size:0.5rem;"></i>
                            <?php echo $isSystem ? 'built-in' : 'custom'; ?>
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5 leading-snug">
                        <?php echo htmlspecialchars($desc); ?>
                    </p>
                </div>
                <div class="flex items-start gap-3 flex-shrink-0">
                    <div class="text-right">
                        <span class="text-xs font-semibold text-gray-400">Permissions</span><br>
                        <span class="text-xl font-bold" style="color: <?php echo $colors['icon']; ?>;"
                              id="counter-<?php echo $roleKey; ?>">
                            <?php echo $grantedCount; ?>
                        </span>
                        <span class="text-sm text-gray-400">/ <?php echo $totalCount; ?></span>
                    </div>
                    <button type="button"
                            title="Edit role"
                            onclick='openRoleModal(<?php echo json_encode([
                                "id" => $roleKey,
                                "title" => $roleLabel,
                                "description" => $desc,
                                "permissions" => $perms,
                            ]); ?>)'
                            class="text-gray-400 hover:text-[#10A37F] transition p-1">
                        <i class="fas fa-pen"></i>
                    </button>
                    <?php if (!$isSystem): ?>
                    <button type="button"
                            title="Delete role"
                            onclick="deleteRole(<?php echo $roleKey; ?>, '<?php echo htmlspecialchars(addslashes($roleLabel)); ?>')"
                            class="text-gray-400 hover:text-red-500 transition p-1">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Permission Rows -->
            <?php foreach ($permissionKeys as $permKey => $permLabel):
                $isGranted = !empty($perms[$permKey]);
                $pIcon     = $permissionIcons[$permKey] ?? 'fa-check';
                $pDesc     = $permissionDescriptions[$permKey] ?? '';
                $risk      = $permissionRisk[$permKey] ?? 'low';
                $riskLabel = ucfirst($risk) . ' Risk';
                $inputId   = 'perm_' . $roleKey . '_' . $permKey; // $roleKey is now the numeric role id
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
                               data-original="<?php echo $isGranted ? '1' : '0'; ?>"
                               class="perm-checkbox"
                               <?php echo $isGranted ? 'checked' : ''; ?>>
                        <span class="perm-slider"></span>
                    </label>
                    <span class="toggle-state-label"
                          id="label-<?php echo $inputId; ?>">
                        <?php echo $isGranted ? 'On' : 'Off'; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Card footer -->
            <div class="perm-card-footer">
                <div class="perm-counter">
                    <i class="fas fa-check-circle text-emerald-500"></i>
                    <span>
                        <span id="granted-<?php echo htmlspecialchars($roleKey); ?>"><?php echo $grantedCount; ?></span>
                        of <?php echo $totalCount; ?> permissions granted
                    </span>
                </div>
                <?php if ($roleLabel === 'MENRO Staff'): ?>
                <span class="text-xs text-amber-600 font-medium">
                    <i class="fas fa-info-circle mr-1"></i>
                    The primary super-admin account is always unrestricted
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ===== INFO NOTICE ===== -->
        <div class="flex items-start gap-3 p-4 bg-blue-50 border border-blue-100 rounded-xl mb-6">
            <i class="fas fa-info-circle text-blue-400 mt-0.5 flex-shrink-0"></i>
            <p class="text-xs text-blue-700 leading-relaxed">
                <strong>How permissions work:</strong>
                Disabled toggles are <strong>denied</strong> by default.
                Citizens are never granted admin permissions.
                The primary super-admin account bypasses all restrictions.
                Changes apply to every account with the matching role.
            </p>
        </div>

        <!-- ===== ACTIONS ===== -->
        <div class="flex flex-wrap items-center gap-3 justify-between pt-4 border-t border-gray-100">
            <button type="button" onclick="resetPermissionsForm()" class="btn-secondary">
                <i class="fas fa-undo mr-2"></i> Reset to Saved
            </button>
            <button type="submit" class="btn-primary">
                <i class="fas fa-save mr-2"></i> Save Permissions
            </button>
        </div>
    </form>

    <!-- ===== CREATE / EDIT ROLE MODAL ===== -->
    <div id="roleModal" class="modal-overlay" onclick="if(event.target===this) closeRoleModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="flex justify-between items-center">
                    <h2>
                        <i class="fas fa-user-shield"></i>
                        <span id="roleModalTitle">Create Role</span>
                    </h2>
                    <button type="button" onclick="closeRoleModal()" class="close-btn">&times;</button>
                </div>
            </div>

            <form method="POST"
                  action="<?php echo BASE_URL; ?>controllers/SettingsController.php?tab=permissions"
                  class="p-6" id="roleForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="sub_action" id="roleSubAction" value="create_role">
                <input type="hidden" name="role_id" id="roleFormId" value="">

                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        Role Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="title" id="roleFormTitle" required maxlength="100"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition text-sm"
                           placeholder="e.g. MENRO Report Manager">
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Description (optional)</label>
                    <textarea name="description" id="roleFormDescription" rows="2" maxlength="500"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-[#10A37F] focus:ring-2 focus:ring-emerald-100 outline-none transition text-sm"
                              placeholder="What is this role for?"></textarea>
                </div>

                <label class="block text-sm font-bold text-gray-700 mb-2">Permissions</label>
                <div class="space-y-1 border border-gray-200 rounded-xl p-2 mb-2" id="roleFormPermissions">
                    <?php foreach ($permissionKeys as $permKey => $permLabel): ?>
                    <label class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 cursor-pointer text-sm">
                        <input type="checkbox" name="permissions[<?php echo htmlspecialchars($permKey); ?>]"
                               value="1" class="role-form-perm-checkbox" data-perm="<?php echo htmlspecialchars($permKey); ?>">
                        <span class="font-medium text-gray-700"><?php echo htmlspecialchars($permLabel); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 btn-primary py-3 rounded-xl font-semibold">
                        <i class="fas fa-save mr-2"></i> <span id="roleFormSubmitLabel">Save Role</span>
                    </button>
                    <button type="button" onclick="closeRoleModal()" class="px-6 py-3 border border-gray-300 rounded-xl hover:bg-gray-50 transition font-medium text-sm">
                        Cancel
                    </button>
                </div>
            </form>
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

    document.querySelectorAll('.perm-checkbox').forEach(function (cb) {
        cb.addEventListener('change', function () {
            // Update On / Off label
            var labelEl = document.getElementById('label-' + cb.id);
            if (labelEl) labelEl.textContent = cb.checked ? 'On' : 'Off';

            // Update per-role granted counter
            updateGrantedCounter(cb.dataset.role);

            // Show / hide unsaved-changes indicator
            var changes = countChanges();
            if (countEl) countEl.textContent = changes;
            if (indicator) indicator.style.display = changes > 0 ? 'flex' : 'none';
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
    // CREATE / EDIT ROLE MODAL
    // ============================================================
    window.openRoleModal = function (role) {
        const modal        = document.getElementById('roleModal');
        const title        = document.getElementById('roleModalTitle');
        const submitLabel  = document.getElementById('roleFormSubmitLabel');
        const subAction     = document.getElementById('roleSubAction');
        const roleFormId    = document.getElementById('roleFormId');
        const titleInput    = document.getElementById('roleFormTitle');
        const descInput     = document.getElementById('roleFormDescription');
        const checkboxes    = document.querySelectorAll('.role-form-perm-checkbox');

        document.getElementById('roleForm').reset();

        if (role && role.id) {
            // Edit mode
            title.textContent = 'Edit Role';
            submitLabel.textContent = 'Save Changes';
            subAction.value = 'update_role';
            roleFormId.value = role.id;
            titleInput.value = role.title || '';
            descInput.value = role.description || '';
            checkboxes.forEach(function (cb) {
                cb.checked = !!(role.permissions && role.permissions[cb.dataset.perm]);
            });
        } else {
            // Create mode
            title.textContent = 'Create Role';
            submitLabel.textContent = 'Save Role';
            subAction.value = 'create_role';
            roleFormId.value = '';
            checkboxes.forEach(function (cb) { cb.checked = false; });
        }

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.closeRoleModal = function () {
        document.getElementById('roleModal').classList.remove('active');
        document.body.style.overflow = '';
    };

    window.deleteRole = function (roleId, roleTitle) {
        if (confirm('Delete the role "' + roleTitle + '"? This cannot be undone, and only works if no users are currently assigned to it.')) {
            document.getElementById('deleteRoleId').value = roleId;
            document.getElementById('deleteRoleForm').submit();
        }
    };

})();
</script>