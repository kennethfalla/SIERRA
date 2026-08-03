<?php
// views/admin/settings/partials/general.php - General Settings
// System Name, Contact Email, Emergency Hotline, LGU Logo

// Load current settings
$system_name = SettingsHelper::get('system_name', 'Sierra');
$contact_email = SettingsHelper::get('contact_email', '');
$emergency_hotline = SettingsHelper::get('emergency_hotline', '');
$lgu_logo = SettingsHelper::get('lgu_logo', '');
$logo_url = $lgu_logo ? BASE_URL . $lgu_logo : '';

// Generate CSRF token
$csrf_token = InputSanitizer::generateCsrfToken();
?>

<form method="POST" enctype="multipart/form-data" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=general" id="generalSettingsForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    
    <!-- ============================================ -->
    <!-- SYSTEM NAME -->
    <!-- ============================================ -->
    <div class="form-group">
        <label class="form-label" for="system_name">
            System Name <span class="text-red-500">*</span>
            <span class="text-xs font-normal text-gray-400 ml-1">(Appears in header, sidebar, and page titles)</span>
        </label>
        <input type="text" name="system_name" id="system_name" 
               value="<?php echo htmlspecialchars($system_name); ?>" 
               class="form-input" required 
               placeholder="e.g., Sierra">
        <p class="text-xs text-gray-400 mt-1">This name appears throughout the system and in email notifications.</p>
    </div>
    
    <!-- ============================================ -->
    <!-- CONTACT EMAIL -->
    <!-- ============================================ -->
    <div class="form-group">
        <label class="form-label" for="contact_email">
            Contact Email <span class="text-red-500">*</span>
            <span class="text-xs font-normal text-gray-400 ml-1">(Displayed in footer and notifications)</span>
        </label>
        <input type="email" name="contact_email" id="contact_email" 
               value="<?php echo htmlspecialchars($contact_email); ?>" 
               class="form-input" required 
               placeholder="menro@sanisidro.gov.ph">
        <p class="text-xs text-gray-400 mt-1">Citizens use this email to contact the LGU for support or inquiries.</p>
    </div>
    
    <!-- ============================================ -->
    <!-- EMERGENCY HOTLINE -->
    <!-- ============================================ -->
    <div class="form-group">
        <label class="form-label" for="emergency_hotline">
            Emergency Hotline
            <span class="text-xs font-normal text-gray-400 ml-1">(Displayed in footer)</span>
        </label>
        <input type="text" name="emergency_hotline" id="emergency_hotline" 
               value="<?php echo htmlspecialchars($emergency_hotline); ?>" 
               class="form-input" 
               placeholder="e.g., 0917-123-4567">
        <p class="text-xs text-gray-400 mt-1">This number is shown in the footer for emergency contact purposes.</p>
    </div>
    
    <!-- ============================================ -->
    <!-- LGU LOGO -->
    <!-- ============================================ -->
    <div class="form-group">
        <label class="form-label">
            LGU Logo
            <span class="text-xs font-normal text-gray-400 ml-1">(Appears in sidebar, header, and login pages)</span>
        </label>
        
        <div class="flex flex-col sm:flex-row items-start gap-4">
            <!-- Current Logo Preview -->
            <div class="flex-shrink-0">
                <div class="logo-preview flex items-center justify-center bg-gray-50 border border-gray-200">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Current Logo" class="w-full h-full object-contain" id="logoPreviewImg">
                    <?php else: ?>
                        <span class="text-gray-400 text-sm text-center px-2" id="logoPreviewFallback">No logo uploaded</span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-400 mt-1 text-center">Recommended: Square image, PNG or JPG</p>
            </div>
            
            <!-- Upload Area -->
            <div class="flex-1 w-full">
                <div class="upload-area" id="logoUploadArea">
                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2 block"></i>
                    <p class="text-sm text-gray-500 font-medium">Click or drag & drop to upload</p>
                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, GIF, WebP (Max 5MB)</p>
                    <input type="file" name="lgu_logo" id="logoInput" accept="image/*" style="display: none;">
                    <p class="file-label text-xs text-gray-400 mt-2">
                        <?php if ($logo_url): ?>
                            Current: <?php echo basename($lgu_logo); ?>
                        <?php else: ?>
                            No file chosen
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- PREVIEW SECTION -->
    <!-- ============================================ -->
    <div class="bg-gray-50 rounded-xl p-4 mb-6 border border-gray-200">
        <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-eye text-[#10A37F]"></i>
            Live Preview
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div class="bg-white rounded-lg p-3 border border-gray-200">
                <p class="text-xs text-gray-400 mb-1">System Name</p>
                <p class="font-bold text-gray-800" id="previewSystemName"><?php echo htmlspecialchars($system_name); ?></p>
            </div>
            <div class="bg-white rounded-lg p-3 border border-gray-200">
                <p class="text-xs text-gray-400 mb-1">Contact Email</p>
                <p class="text-gray-700" id="previewContactEmail"><?php echo htmlspecialchars($contact_email); ?></p>
            </div>
            <div class="bg-white rounded-lg p-3 border border-gray-200">
                <p class="text-xs text-gray-400 mb-1">Emergency Hotline</p>
                <p class="text-gray-700" id="previewHotline"><?php echo htmlspecialchars($emergency_hotline ?: 'Not set'); ?></p>
            </div>
            <div class="bg-white rounded-lg p-3 border border-gray-200">
                <p class="text-xs text-gray-400 mb-1">Logo Status</p>
                <p class="text-gray-700">
                    <?php if ($logo_url): ?>
                        <span class="text-green-600"><i class="fas fa-check-circle mr-1"></i> Uploaded</span>
                    <?php else: ?>
                        <span class="text-gray-400"><i class="fas fa-info-circle mr-1"></i> Not uploaded</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3 text-center">
            <i class="fas fa-info-circle mr-1"></i>
            Changes take effect immediately after saving. Preview updates in real-time.
        </p>
    </div>
    
    <!-- ============================================ -->
    <!-- FORM ACTIONS -->
    <!-- ============================================ -->
    <div class="flex flex-wrap gap-3 justify-end pt-2 border-t border-gray-100">
        <button type="button" onclick="resetForm()" class="btn-secondary flex items-center gap-2">
            <i class="fas fa-undo"></i> Reset
        </button>
        <button type="submit" class="btn-primary flex items-center gap-2">
            <i class="fas fa-save"></i> Save Changes
        </button>
    </div>
</form>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
(function() {
    'use strict';
    
    // ===== DOM REFERENCES =====
    const form = document.getElementById('generalSettingsForm');
    const systemNameInput = document.getElementById('system_name');
    const contactEmailInput = document.getElementById('contact_email');
    const hotlineInput = document.getElementById('emergency_hotline');
    const logoInput = document.getElementById('logoInput');
    const uploadArea = document.getElementById('logoUploadArea');
    const previewSystemName = document.getElementById('previewSystemName');
    const previewContactEmail = document.getElementById('previewContactEmail');
    const previewHotline = document.getElementById('previewHotline');
    const fileLabel = uploadArea.querySelector('.file-label');
    
    // ===== REAL-TIME PREVIEW =====
    systemNameInput.addEventListener('input', function() {
        previewSystemName.textContent = this.value || '(empty)';
        previewSystemName.style.color = this.value ? '#1f2937' : '#ef4444';
    });
    
    contactEmailInput.addEventListener('input', function() {
        previewContactEmail.textContent = this.value || '(empty)';
        previewContactEmail.style.color = this.value ? '#1f2937' : '#ef4444';
    });
    
    hotlineInput.addEventListener('input', function() {
        previewHotline.textContent = this.value || 'Not set';
        previewHotline.style.color = this.value ? '#1f2937' : '#9ca3af';
    });
    
    // ===== LOGO UPLOAD =====
    // Click to upload
    uploadArea.addEventListener('click', function(e) {
        // Prevent triggering when clicking on file label or image preview
        if (e.target.closest('.file-label') || e.target.closest('img')) {
            return;
        }
        logoInput.click();
    });
    
    // Drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            logoInput.files = e.dataTransfer.files;
            updateFileLabel(e.dataTransfer.files[0]);
            previewLogo(e.dataTransfer.files[0]);
        }
    });
    
    // File input change
    logoInput.addEventListener('change', function() {
        if (this.files.length) {
            updateFileLabel(this.files[0]);
            previewLogo(this.files[0]);
        } else {
            fileLabel.textContent = 'No file chosen';
        }
    });
    
    function updateFileLabel(file) {
        const size = (file.size / 1024).toFixed(1);
        fileLabel.textContent = file.name + ' (' + size + ' KB)';
        fileLabel.style.color = '#10A37F';
    }
    
    function previewLogo(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('logoPreviewImg');
            const fallback = document.getElementById('logoPreviewFallback');
            if (previewImg) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
            }
            if (fallback) {
                fallback.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);
    }
    
    // ===== RESET FORM =====
    window.resetForm = function() {
        if (confirm('Reset all fields to their saved values? Unsaved changes will be lost.')) {
            location.reload();
        }
    };
    
    // ===== UNSAVED CHANGES WARNING =====
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
    
    // ===== INITIAL VALIDATION STATE =====
    // Show placeholder styling for empty previews
    if (!systemNameInput.value) {
        previewSystemName.textContent = '(empty)';
        previewSystemName.style.color = '#ef4444';
    }
    if (!contactEmailInput.value) {
        previewContactEmail.textContent = '(empty)';
        previewContactEmail.style.color = '#ef4444';
    }
    
    // ===== VALIDATION ON SUBMIT =====
    form.addEventListener('submit', function(e) {
        // Ensure email is valid
        const email = contactEmailInput.value.trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address.');
            contactEmailInput.focus();
            return;
        }
        
        // Ensure system name is not empty
        if (!systemNameInput.value.trim()) {
            e.preventDefault();
            alert('System name is required.');
            systemNameInput.focus();
            return;
        }
    });
    
})();
</script>

<!-- ===== ADDITIONAL STYLES FOR THIS PARTIAL ===== -->
<style>
    .upload-area.dragover {
        border-color: #10A37F;
        background: #d1fae5;
    }
    .logo-preview {
        width: 120px;
        height: 120px;
        object-fit: contain;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .logo-preview img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    .upload-area {
        border: 2px dashed #d1d5db;
        border-radius: 0.75rem;
        padding: 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
        background: #fafafa;
    }
    .upload-area:hover {
        border-color: #10A37F;
        background: #f0fdf4;
    }
    .file-label {
        font-size: 0.75rem;
        color: #9ca3af;
        margin-top: 0.5rem;
        transition: color 0.2s;
        word-break: break-all;
    }
</style>