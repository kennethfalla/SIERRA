<?php
// views/shared/profile/pdf_export.php - BARANGAY PDF EXPORT SETTINGS (per account)
// "Prepared by" is always the account's name (auto). Only "Approved by" and the
// barangay logo are editable here. Mirrors the MENRO PDF Export settings, which
// live under Settings instead of Profile.

$b_approved_by    = SettingsHelper::get('brgy_pdf_approved_by_name_' . $user_id, '');
$b_approved_title = SettingsHelper::get('brgy_pdf_approved_by_title_' . $user_id, 'Punong Barangay');
$b_logo_path      = SettingsHelper::get('brgy_pdf_logo_' . $user_id, '');
$b_logo_url       = $b_logo_path ? BASE_URL . $b_logo_path : '';
$b_csrf           = InputSanitizer::generateCsrfToken();
?>

<form method="POST" enctype="multipart/form-data" action="<?php echo BASE_URL; ?>index.php?page=profile&section=pdf-export-settings" id="barangayPdfExportForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($b_csrf); ?>">
    <input type="hidden" name="save_pdf_export" value="1">

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-800 flex items-start gap-3">
        <i class="fas fa-file-pdf text-blue-500 mt-0.5"></i>
        <div>
            <p class="font-semibold"><?php echo t('PDF Export Settings'); ?></p>
            <p class="text-blue-700 text-xs mt-1">These settings control the official document header and signatory block used when you export analytics and lists from your barangay dashboard.</p>
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 text-sm text-amber-800 flex items-start gap-3">
        <i class="fas fa-user-check text-amber-500 mt-0.5"></i>
        <div>
            <p class="font-semibold">Prepared by is automatic</p>
            <p class="text-amber-700 text-xs mt-1">The "Prepared by" name on your exported PDFs is always your account name: <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'You'); ?></strong>. Only the "Approved by" details and logo below can be edited.</p>
        </div>
    </div>

    <!-- Barangay Logo -->
    <div class="form-group mb-5">
        <label class="form-label">Barangay Logo</label>
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0">
                <div class="w-20 h-20 flex items-center justify-center bg-gray-50 border border-gray-200 rounded-xl overflow-hidden">
                    <?php if ($b_logo_url): ?>
                        <img src="<?php echo htmlspecialchars($b_logo_url); ?>" alt="Barangay Logo" class="w-full h-full object-contain" id="brgyLogoPreviewImg">
                    <?php else: ?>
                        <span class="text-gray-400 text-sm text-center px-2" id="brgyLogoPreviewFallback">No logo</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex-1 w-full">
                <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-[#10A37F] transition" id="brgyLogoUploadArea">
                    <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-1 block"></i>
                    <p class="text-sm text-gray-500 font-medium">Click or drag &amp; drop to upload</p>
                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, GIF, WebP (Max 5MB)</p>
                    <input type="file" name="brgy_pdf_logo" id="brgyLogoInput" accept="image/*" style="display: none;">
                    <p class="file-label text-xs text-gray-400 mt-2">
                        <?php if ($b_logo_url): ?>
                            Current: <?php echo htmlspecialchars(basename($b_logo_path)); ?>
                        <?php else: ?>
                            No file chosen
                        <?php endif; ?>
                    </p>
                </div>
                <p class="text-xs text-gray-400 mt-1">Appears on the top-right of your exported PDF documents.</p>
            </div>
        </div>
    </div>

    <!-- Approved by -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="form-group">
            <label class="form-label" for="brgy_pdf_approved_by_name">Noted and Approved by — Name</label>
            <input type="text" name="brgy_pdf_approved_by_name" id="brgy_pdf_approved_by_name"
                   value="<?php echo htmlspecialchars($b_approved_by); ?>"
                   class="form-input"
                   placeholder="e.g., Punong Barangay Name">
        </div>
        <div class="form-group">
            <label class="form-label" for="brgy_pdf_approved_by_title">Noted and Approved by — Title</label>
            <input type="text" name="brgy_pdf_approved_by_title" id="brgy_pdf_approved_by_title"
                   value="<?php echo htmlspecialchars($b_approved_title); ?>"
                   class="form-input"
                   placeholder="Punong Barangay">
        </div>
    </div>

    <div class="flex items-center gap-3 mt-5">
        <button type="submit" class="btn-primary">
            <i class="fas fa-save mr-1"></i> Save PDF Export Settings
        </button>
    </div>
</form>

<script>
(function() {
    var input = document.getElementById('brgyLogoInput');
    var area = document.getElementById('brgyLogoUploadArea');
    var previewImg = document.getElementById('brgyLogoPreviewImg');
    var fallback = document.getElementById('brgyLogoPreviewFallback');

    if (area && input) {
        area.addEventListener('click', function() { input.click(); });
        area.addEventListener('dragover', function(e) { e.preventDefault(); area.classList.add('border-[#10A37F]'); });
        area.addEventListener('dragleave', function() { area.classList.remove('border-[#10A37F]'); });
        area.addEventListener('drop', function(e) {
            e.preventDefault();
            area.classList.remove('border-[#10A37F]');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                var label = area.querySelector('.file-label');
                if (label) label.textContent = e.dataTransfer.files[0].name;
            }
        });
        input.addEventListener('change', function() {
            var label = area.querySelector('.file-label');
            if (label && this.files.length) label.textContent = this.files[0].name;
            if (this.files.length) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) { previewImg.src = e.target.result; previewImg.style.display = 'block'; }
                    if (fallback) fallback.style.display = 'none';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
})();
</script>
