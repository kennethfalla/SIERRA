<?php
// views/admin/settings/partials/permissions.php
// Section 10: Permissions — Role-Based Access Control (RBAC)
// Restricts what local admins (Barangay Admins, MENRO Staff) can do.
// Included into the settings page shell when ?tab=permissions is active.
// Expects $csrf_token to already be available in scope (as with the other partials).

if (!isset($csrf_token)) {
    $csrf_token = InputSanitizer::generateCsrfToken();
}

$roles           = SettingsHelper::getManageableRoles();   // role_key => label
$permissionKeys  = SettingsHelper::getPermissionKeys();    // permission_key => label
$rolePermissions = SettingsHelper::getAllRolePermissions(); // role_key => [permission_key => bool]

$roleIcons = [
    'admin'             => 'fa-crown',
    'barangay_official' => 'fa-landmark',
];
$roleDescriptions = [
    'admin'             => 'Full MENRO office staff. Grant carefully — reducing every permission here does not remove their access to Settings itself.',
    'barangay_official' => 'Local barangay-level admins. Restrict destructive or municipality-wide actions as needed.',
];
?>
<div class="fade-in">
    <div class="stat-card bg-white rounded-xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Permissions</h2>
        <p class="text-sm text-gray-500 mb-6">
            Role-based access control (RBAC) — restrict what local admins can do.
        </p>

        <form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=permissions" id="permissionsForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="table-container mb-2">
                <table>
                    <thead>
                        <tr>
                            <th style="min-width: 200px;">Role</th>
                            <?php foreach ($permissionKeys as $permKey => $permLabel): ?>
                                <th class="text-center"><?php echo htmlspecialchars($permLabel); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $roleKey => $roleLabel): ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <i class="fas <?php echo $roleIcons[$roleKey] ?? 'fa-user-shield'; ?> text-[#10A37F]"></i>
                                        <div>
                                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($roleLabel); ?></p>
                                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($roleDescriptions[$roleKey] ?? ''); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <?php foreach ($permissionKeys as $permKey => $permLabel): ?>
                                    <td class="text-center">
                                        <label class="inline-flex items-center justify-center cursor-pointer">
                                            <input type="checkbox"
                                                   name="permissions[<?php echo htmlspecialchars($roleKey); ?>][<?php echo htmlspecialchars($permKey); ?>]"
                                                   value="1"
                                                   <?php echo !empty($rolePermissions[$roleKey][$permKey]) ? 'checked' : ''; ?>
                                                   class="rounded border-gray-300 text-[#10A37F] w-4 h-4">
                                        </label>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-400 mb-6">
                <i class="fas fa-info-circle mr-1"></i>
                Unchecked permissions are denied by default. Citizens are never granted admin permissions.
            </p>

            <div class="flex flex-wrap gap-3 justify-end pt-4 border-t border-gray-200">
                <button type="button" onclick="resetPermissionsForm()" class="btn-secondary">
                    <i class="fas fa-undo mr-2"></i> Reset
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i> Save Permissions
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    'use strict';

    const form = document.getElementById('permissionsForm');
    let formChanged = false;

    form.addEventListener('input', function() {
        formChanged = true;
    });
    form.addEventListener('submit', function() {
        formChanged = false;
    });

    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

    window.resetPermissionsForm = function() {
        if (confirm('Reset all permissions to their saved values? Unsaved changes will be lost.')) {
            location.reload();
        }
    };
})();
</script>

<style>
.btn-primary {
    background: linear-gradient(135deg, #10A37F, #0D8568);
    color: white;
    padding: 0.6rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16,163,127,0.3);
}
.btn-secondary {
    background: white;
    border: 1px solid #e2e8f0;
    padding: 0.6rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 500;
    color: #4b5563;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-secondary:hover {
    background: #f8fafc;
}
</style>