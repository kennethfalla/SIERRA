<?php
// views/admin/settings/partials/barangays.php
// Barangays Management - Master Roster for San Isidro

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Fetch all barangays from database
$query = "SELECT * FROM barangays ORDER BY name ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get CSRF token (already generated in parent, or generate if needed)
if (!isset($csrf_token)) {
    $csrf_token = InputSanitizer::generateCsrfToken();
}
?>

<style>
    /* ===== TABLE STYLES ===== */
    .barangay-section {
        background: white;
        border-radius: 1rem;
        border: 1px solid rgba(16, 163, 127, 0.08);
        overflow: hidden;
    }
    
    .barangay-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .barangay-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }
    
    .barangay-table thead {
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .barangay-table thead th {
        padding: 0.75rem 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
        white-space: nowrap;
    }
    
    .barangay-table tbody tr {
        border-bottom: 1px solid #f3f4f6;
        transition: background 0.15s ease;
    }
    
    .barangay-table tbody tr:hover {
        background: #fafafa;
    }
    
    .barangay-table tbody tr:last-child {
        border-bottom: none;
    }
    
    .barangay-table tbody td {
        padding: 0.6rem 1rem;
        vertical-align: middle;
    }
    
    /* ===== FORM INPUTS ===== */
    .barangay-input {
        width: 100%;
        padding: 0.4rem 0.6rem;
        border: 1.5px solid #e5e7eb;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        background: #f9fafb;
        color: #1f2937;
        transition: all 0.2s ease;
    }
    
    .barangay-input:focus {
        border-color: #10A37F;
        outline: none;
        box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08);
        background: white;
    }
    
    .barangay-input:disabled {
        background: #f9fafb;
        cursor: not-allowed;
        opacity: 0.7;
    }
    
    .barangay-input.editing {
        background: white;
        border-color: #10A37F;
    }
    
    /* ===== BUTTONS ===== */
    .barangay-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        font-weight: 500;
        border: 1px solid #e5e7eb;
        background: white;
        color: #4b5563;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    
    .barangay-btn:hover {
        background: #f0fdf4;
        border-color: #10A37F;
        color: #10A37F;
    }
    
    .barangay-btn-edit.active {
        background: #10A37F;
        color: white;
        border-color: #10A37F;
    }
    
    .barangay-btn-edit.active:hover {
        background: #0d8568;
        border-color: #0d8568;
    }
    
    .barangay-btn-save {
        background: linear-gradient(135deg, #10A37F, #0D8568);
        color: white;
        border: none;
        display: none;
    }
    
    .barangay-btn-save:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(16, 163, 127, 0.3);
    }
    
    .barangay-btn-save.show {
        display: inline-flex;
    }
    
    /* ===== ADD BARANGAY FORM ===== */
    .add-barangay-area {
        background: #f9fafb;
        border: 2px dashed #d1d5db;
        border-radius: 0.75rem;
        padding: 1rem 1.25rem;
        margin-bottom: 1.25rem;
        transition: all 0.2s ease;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem;
    }
    
    .add-barangay-area:hover {
        border-color: #10A37F;
        background: #f0fdf4;
    }
    
    .add-barangay-area .add-input {
        flex: 1;
        min-width: 150px;
        padding: 0.5rem 0.75rem;
        border: 1.5px solid #e5e7eb;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        background: white;
        transition: all 0.2s ease;
    }
    
    .add-barangay-area .add-input:focus {
        border-color: #10A37F;
        outline: none;
        box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08);
    }
    
    .btn-add-barangay {
        background: linear-gradient(135deg, #10A37F, #0D8568);
        color: white;
        border: none;
        padding: 0.5rem 1.25rem;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    
    .btn-add-barangay:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
    }
    
    .btn-add-barangay:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
    }
    
    /* ===== DELETE BUTTON ===== */
    .barangay-btn-delete {
        background: white;
        border: 1px solid #fee2e2;
        color: #ef4444;
        padding: 0.3rem 0.5rem;
        border-radius: 0.5rem;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-left: 0.3rem;
    }
    
    .barangay-btn-delete:hover {
        background: #fee2e2;
        border-color: #ef4444;
    }
    
    /* ===== RESPONSIVE ===== */
    @media (max-width: 640px) {
        .barangay-table thead th,
        .barangay-table tbody td {
            padding: 0.4rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .barangay-input {
            font-size: 0.75rem;
            padding: 0.3rem 0.4rem;
        }
        
        .barangay-btn {
            font-size: 0.65rem;
            padding: 0.2rem 0.5rem;
        }
        
        .add-barangay-area {
            flex-direction: column;
            padding: 0.75rem;
        }
        .add-barangay-area .add-input {
            width: 100%;
            min-width: unset;
        }
        .btn-add-barangay {
            width: 100%;
            text-align: center;
        }
    }
    
    /* ===== NO DATA ===== */
    .barangay-empty {
        text-align: center;
        padding: 2rem 1rem;
        color: #9ca3af;
    }
    
    .barangay-empty i {
        font-size: 2.5rem;
        display: block;
        margin-bottom: 0.5rem;
        color: #d1d5db;
    }
    
    /* ===== TOAST ===== */
    .barangay-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
        width: 100%;
        padding: 12px 16px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        font-size: 14px;
        color: #1f2937;
        background: white;
        border-left: 4px solid #10A37F;
        animation: slideIn 0.3s ease;
        display: none;
    }
    
    .barangay-toast.error {
        border-left-color: #ef4444;
    }
    
    .barangay-toast.warning {
        border-left-color: #f59e0b;
    }
    
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
</style>

<!-- ================================================================ -->
<!-- TOAST NOTIFICATION -->
<!-- ================================================================ -->
<div id="barangayToast" class="barangay-toast">
    <span id="barangayToastMessage"></span>
</div>

<!-- ================================================================ -->
<!-- ADD BARANGAY FORM -->
<!-- ================================================================ -->
<div class="add-barangay-area" id="addBarangayArea">
    <i class="fas fa-plus-circle text-[#10A37F] text-xl"></i>
    <input type="text" 
           id="newBarangayName" 
           class="add-input" 
           placeholder="Enter new barangay name..."
           onkeydown="if(event.key === 'Enter') addBarangay()">
    <button type="button" class="btn-add-barangay" id="addBarangayBtn" onclick="addBarangay()">
        <i class="fas fa-plus mr-1"></i> Add Barangay
    </button>
</div>

<!-- ================================================================ -->
<!-- BARANGAY TABLE -->
<!-- ================================================================ -->
<div class="barangay-section">
    <div class="barangay-table-wrap">
        <table class="barangay-table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th style="min-width: 160px;">Barangay Name</th>
                    <th style="min-width: 160px;">Barangay Captain</th>
                    <th style="min-width: 150px;">Office Number</th>
                    <th style="width: 150px; text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody id="barangayTableBody">
                <?php if (empty($barangays)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="barangay-empty">
                                <i class="fas fa-building"></i>
                                <p>No barangays found in the system.</p>
                                <p class="text-xs mt-1">Use the form above to add your first barangay.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $counter = 1; ?>
                    <?php foreach ($barangays as $barangay): ?>
                        <tr id="barangay-row-<?php echo $barangay['id']; ?>">
                            <td class="text-gray-500 font-medium text-center">
                                <?php echo $counter++; ?>
                            </td>
                            <td>
                                <input type="text"
                                       id="barangay_name_<?php echo $barangay['id']; ?>"
                                       class="barangay-input"
                                       value="<?php echo htmlspecialchars($barangay['name']); ?>"
                                       placeholder="Enter barangay name"
                                       disabled>
                            </td>
                            <td>
                                <input type="text"
                                       id="captain_name_<?php echo $barangay['id']; ?>"
                                       class="barangay-input"
                                       value="<?php echo htmlspecialchars($barangay['captain_name'] ?? ''); ?>"
                                       placeholder="Enter captain name"
                                       disabled>
                            </td>
                            <td>
                                <input type="text"
                                       id="captain_contact_<?php echo $barangay['id']; ?>"
                                       class="barangay-input"
                                       value="<?php echo htmlspecialchars($barangay['captain_contact'] ?? ''); ?>"
                                       placeholder="Enter office number"
                                       disabled>
                            </td>
                            <td style="text-align: center;">
                                <button type="button"
                                        class="barangay-btn barangay-btn-edit"
                                        id="edit-btn-<?php echo $barangay['id']; ?>"
                                        onclick="enableEdit(<?php echo $barangay['id']; ?>)">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                                <button type="button"
                                        class="barangay-btn barangay-btn-save"
                                        id="save-btn-<?php echo $barangay['id']; ?>"
                                        onclick="saveBarangay(<?php echo $barangay['id']; ?>)">
                                    <i class="fas fa-check"></i> Save
                                </button>
                                <button type="button"
                                        class="barangay-btn-delete"
                                        id="delete-btn-<?php echo $barangay['id']; ?>"
                                        onclick="deleteBarangay(<?php echo $barangay['id']; ?>, '<?php echo htmlspecialchars(addslashes($barangay['name'])); ?>')"
                                        title="Delete this barangay">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================================================================ -->
<!-- STATISTICS FOOTER -->
<!-- ================================================================ -->
<?php if (!empty($barangays)): ?>
    <div class="mt-4 text-xs text-gray-400 flex items-center gap-4 flex-wrap">
        <span><i class="fas fa-list-ul mr-1"></i> Total: <strong><?php echo count($barangays); ?></strong> barangays</span>
        <span><i class="fas fa-users mr-1"></i> Click <strong>Edit</strong> to update barangay info</span>
        <span><i class="fas fa-keyboard mr-1"></i> Press <kbd class="px-1 py-0.5 bg-gray-100 rounded border border-gray-300 text-xs">Enter</kbd> to save, <kbd class="px-1 py-0.5 bg-gray-100 rounded border border-gray-300 text-xs">Esc</kbd> to cancel</span>
    </div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- HIDDEN FORMS -->
<!-- ================================================================ -->

<!-- Edit Form -->
<form method="POST"
      action="<?php echo BASE_URL; ?>index.php?page=settings&tab=barangays"
      id="barangayForm"
      style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="sub_action" value="edit">
    <input type="hidden" name="barangay_id" id="form_barangay_id" value="">
    <input type="hidden" name="barangay_name" id="form_barangay_name" value="">
    <input type="hidden" name="captain_name" id="form_captain_name" value="">
    <input type="hidden" name="captain_contact" id="form_captain_contact" value="">
</form>

<!-- Add Form -->
<form method="POST"
      action="<?php echo BASE_URL; ?>index.php?page=settings&tab=barangays"
      id="addBarangayForm"
      style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="sub_action" value="add">
    <input type="hidden" name="barangay_name" id="form_new_barangay_name" value="">
    <input type="hidden" name="captain_name" id="form_new_captain_name" value="">
    <input type="hidden" name="captain_contact" id="form_new_captain_contact" value="">
</form>

<!-- Delete Form -->
<form method="POST"
      action="<?php echo BASE_URL; ?>index.php?page=settings&tab=barangays"
      id="deleteBarangayForm"
      style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="sub_action" value="delete">
    <input type="hidden" name="barangay_id" id="form_delete_barangay_id" value="">
</form>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
(function() {
    'use strict';

    // ================================================================
    // STATE
    // ================================================================
    let activeEditId = null;

    // ================================================================
    // DOM REFS
    // ================================================================
    const toast = document.getElementById('barangayToast');
    const toastMessage = document.getElementById('barangayToastMessage');
    const addInput = document.getElementById('newBarangayName');
    const addBtn = document.getElementById('addBarangayBtn');

    // ================================================================
    // TOAST HELPERS
    // ================================================================
    function showToast(message, type) {
        toast.className = 'barangay-toast';
        if (type === 'error') {
            toast.classList.add('error');
        } else if (type === 'warning') {
            toast.classList.add('warning');
        }
        toastMessage.textContent = message;
        toast.style.display = 'block';
        toast.style.animation = 'slideIn 0.3s ease';

        clearTimeout(toast._timeout);
        toast._timeout = setTimeout(function() {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(function() {
                toast.style.display = 'none';
                toast.style.animation = 'slideIn 0.3s ease';
            }, 300);
        }, 4000);
    }

    // ================================================================
    // ADD BARANGAY
    // ================================================================
    window.addBarangay = function() {
        const name = addInput.value.trim();
        
        if (!name) {
            showToast('Please enter a barangay name.', 'error');
            addInput.focus();
            return;
        }
        
        // Check if name already exists
        const existingNames = document.querySelectorAll('[id^="barangay_name_"]');
        for (let input of existingNames) {
            if (input.value.toLowerCase() === name.toLowerCase()) {
                showToast('A barangay with this name already exists.', 'error');
                addInput.focus();
                return;
            }
        }
        
        // Set form values
        document.getElementById('form_new_barangay_name').value = name;
        document.getElementById('form_new_captain_name').value = '';
        document.getElementById('form_new_captain_contact').value = '';
        
        // Disable button and show loading
        addBtn.disabled = true;
        addBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Adding...';
        
        document.getElementById('addBarangayForm').submit();
    };

    // ================================================================
    // ENABLE EDIT MODE
    // ================================================================
    window.enableEdit = function(barangayId) {
        // Cancel any existing edit
        if (activeEditId !== null && activeEditId !== barangayId) {
            cancelEdit(activeEditId);
        }

        const nameInput = document.getElementById('barangay_name_' + barangayId);
        const captainInput = document.getElementById('captain_name_' + barangayId);
        const contactInput = document.getElementById('captain_contact_' + barangayId);
        const editBtn = document.getElementById('edit-btn-' + barangayId);
        const saveBtn = document.getElementById('save-btn-' + barangayId);

        if (!nameInput || !captainInput || !contactInput || !editBtn || !saveBtn) {
            return;
        }

        // Enable inputs
        nameInput.disabled = false;
        captainInput.disabled = false;
        contactInput.disabled = false;
        nameInput.classList.add('editing');
        captainInput.classList.add('editing');
        contactInput.classList.add('editing');

        // Toggle buttons
        editBtn.classList.add('active');
        editBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
        editBtn.onclick = function() { cancelEdit(barangayId); };
        saveBtn.classList.add('show');

        // Focus on name input
        nameInput.focus();
        nameInput.select();

        activeEditId = barangayId;

        showToast('Editing barangay — Press Enter to save, Esc to cancel.', 'info');
    };

    // ================================================================
    // CANCEL EDIT MODE
    // ================================================================
    function cancelEdit(barangayId) {
        const nameInput = document.getElementById('barangay_name_' + barangayId);
        const captainInput = document.getElementById('captain_name_' + barangayId);
        const contactInput = document.getElementById('captain_contact_' + barangayId);
        const editBtn = document.getElementById('edit-btn-' + barangayId);
        const saveBtn = document.getElementById('save-btn-' + barangayId);

        if (nameInput) {
            nameInput.disabled = true;
            nameInput.classList.remove('editing');
            const originalName = nameInput.getAttribute('data-original') || nameInput.value;
            nameInput.value = originalName;
        }

        if (captainInput) {
            captainInput.disabled = true;
            captainInput.classList.remove('editing');
            const originalCaptain = captainInput.getAttribute('data-original') || captainInput.value;
            captainInput.value = originalCaptain;
        }

        if (contactInput) {
            contactInput.disabled = true;
            contactInput.classList.remove('editing');
            const originalContact = contactInput.getAttribute('data-original') || contactInput.value;
            contactInput.value = originalContact;
        }

        if (editBtn) {
            editBtn.classList.remove('active');
            editBtn.innerHTML = '<i class="fas fa-pen"></i> Edit';
            editBtn.onclick = function() { enableEdit(barangayId); };
        }

        if (saveBtn) {
            saveBtn.classList.remove('show');
        }

        activeEditId = null;
    }

    // ================================================================
    // SAVE BARANGAY
    // ================================================================
    window.saveBarangay = function(barangayId) {
        const nameInput = document.getElementById('barangay_name_' + barangayId);
        const captainInput = document.getElementById('captain_name_' + barangayId);
        const contactInput = document.getElementById('captain_contact_' + barangayId);

        if (!nameInput || !captainInput || !contactInput) return;

        const name = nameInput.value.trim();
        const captain = captainInput.value.trim();
        const contact = contactInput.value.trim();

        // Validation - barangay name is required
        if (!name) {
            showToast('Please enter the Barangay name.', 'error');
            nameInput.focus();
            return;
        }

        // Check if name conflicts with another barangay
        const existingNames = document.querySelectorAll('[id^="barangay_name_"]');
        for (let input of existingNames) {
            if (input.id !== 'barangay_name_' + barangayId) {
                if (input.value.toLowerCase() === name.toLowerCase()) {
                    showToast('Another barangay with this name already exists.', 'error');
                    nameInput.focus();
                    return;
                }
            }
        }

        // Set form values
        document.getElementById('form_barangay_id').value = barangayId;
        document.getElementById('form_barangay_name').value = name;
        document.getElementById('form_captain_name').value = captain;
        document.getElementById('form_captain_contact').value = contact;

        // Store original values for cancel
        nameInput.setAttribute('data-original', name);
        captainInput.setAttribute('data-original', captain);
        contactInput.setAttribute('data-original', contact);

        // Show saving state
        const saveBtn = document.getElementById('save-btn-' + barangayId);
        const originalHtml = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        saveBtn.disabled = true;

        // Submit form
        document.getElementById('barangayForm').submit();
    };

    // ================================================================
    // DELETE BARANGAY
    // ================================================================
    window.deleteBarangay = function(barangayId, barangayName) {
        if (!confirm('Are you sure you want to delete "' + barangayName + '"?\n\nThis action cannot be undone.')) {
            return;
        }
        
        // Set form value and submit
        document.getElementById('form_delete_barangay_id').value = barangayId;
        document.getElementById('deleteBarangayForm').submit();
    };

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        // Enter key - save if editing
        if (e.key === 'Enter' && activeEditId !== null) {
            const activeElement = document.activeElement;
            if (activeElement && activeElement.id && activeElement.id.startsWith('barangay_name_')) {
                e.preventDefault();
                saveBarangay(activeEditId);
            }
        }

        // Escape key - cancel edit
        if (e.key === 'Escape' && activeEditId !== null) {
            e.preventDefault();
            cancelEdit(activeEditId);
            showToast('Edit cancelled.', 'info');
        }
    });

    // ================================================================
    // UNSAVED CHANGES WARNING
    // ================================================================
    let formChanged = false;

    document.addEventListener('input', function(e) {
        if (e.target.classList && e.target.classList.contains('barangay-input') && !e.target.disabled) {
            formChanged = true;
        }
    });

    window.addEventListener('beforeunload', function(e) {
        if (formChanged && activeEditId !== null) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

    // Reset form changed flag on submit
    document.getElementById('barangayForm').addEventListener('submit', function() {
        formChanged = false;
    });

    // ================================================================
    // AUTO-CLOSE TOAST ON PAGE NAVIGATION
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            toast.style.display = 'none';
        }
    });

    console.log('Barangay Settings: Loaded successfully.');
    console.log('Total barangays: <?php echo count($barangays); ?>');

})();
</script>