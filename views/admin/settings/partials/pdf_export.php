<?php
// views/admin/settings/partials/pdf_export.php - MENRO PDF Analytics Export Settings
// Official LGU header (logos), office name, and signatory block for exported PDFs.

$menro_logo          = SettingsHelper::get('menro_logo', '');
$menro_logo_url      = $menro_logo ? BASE_URL . $menro_logo : '';
$pdf_office_name     = SettingsHelper::get('pdf_office_name', 'Municipal Environment and Natural Resources Office');
$pdf_municipality    = SettingsHelper::get('pdf_municipality_name', 'Municipality of San Isidro');
$pdf_prepared_by     = SettingsHelper::get('pdf_prepared_by_name', '');
$pdf_prepared_title  = SettingsHelper::get('pdf_prepared_by_title', 'MENRO Data Analyst / Administrator');
$pdf_approved_by     = SettingsHelper::get('pdf_approved_by_name', '');
$pdf_approved_title  = SettingsHelper::get('pdf_approved_by_title', 'Municipal Environment and Natural Resources Officer');
$pdf_footer_note     = SettingsHelper::get('pdf_footer_note', 'System Generated via SIERRA (Web-Based Environmental Reporting Application) | Page 1 of 1');

$csrf_token = InputSanitizer::generateCsrfToken();
?>

<form method="POST" enctype="multipart/form-data" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=pdf_export" id="pdfExportForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-800 flex items-start gap-3">
        <i class="fas fa-file-pdf text-blue-500 mt-0.5"></i>
        <div>
            <p class="font-semibold">MENRO PDF Analytics Export</p>
            <p class="text-blue-700 text-xs mt-1">These settings control the official document header and signatory block used when exporting analytics from the MENRO Decision Dashboard (Dashboard &rarr; Export Analytics &rarr; Export as PDF).</p>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- OFFICIAL HEADER - LOGOS & OFFICE NAME -->
    <!-- ============================================ -->
    <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
        <i class="fas fa-landmark text-[#10A37F]"></i> Official LGU Header
    </h4>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="form-group">
            <label class="form-label">MENRO Logo</label>
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <div class="logo-preview flex items-center justify-center bg-gray-50 border border-gray-200">
                        <?php if ($menro_logo_url): ?>
                            <img src="<?php echo htmlspecialchars($menro_logo_url); ?>" alt="MENRO Logo" class="w-full h-full object-contain" id="menroLogoPreviewImg">
                        <?php else: ?>
                            <span class="text-gray-400 text-sm text-center px-2" id="menroLogoPreviewFallback">No MENRO logo</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex-1 w-full">
                    <div class="upload-area" id="menroLogoUploadArea">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2 block"></i>
                        <p class="text-sm text-gray-500 font-medium">Click or drag &amp; drop to upload</p>
                        <p class="text-xs text-gray-400 mt-1">JPG, PNG, GIF, WebP (Max 5MB)</p>
                        <input type="file" name="menro_logo" id="menroLogoInput" accept="image/*" style="display: none;">
                        <p class="file-label text-xs text-gray-400 mt-2">
                            <?php if ($menro_logo_url): ?>
                                Current: <?php echo htmlspecialchars(basename($menro_logo)); ?>
                            <?php else: ?>
                                No file chosen
                            <?php endif; ?>
                        </p>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Appears on the top-right of exported PDF documents.</p>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="form-group">
                <label class="form-label" for="pdf_office_name">
                    Office / Department Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="pdf_office_name" id="pdf_office_name"
                       value="<?php echo htmlspecialchars($pdf_office_name); ?>"
                       class="form-input" required
                       placeholder="Municipal Environment and Natural Resources Office">
            </div>
            <div class="form-group">
                <label class="form-label" for="pdf_municipality_name">
                    Municipality / LGU Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="pdf_municipality_name" id="pdf_municipality_name"
                       value="<?php echo htmlspecialchars($pdf_municipality); ?>"
                       class="form-input" required
                       placeholder="Municipality of San Isidro">
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- SIGNATORY BLOCK -->
    <!-- ============================================ -->
    <h4 class="text-sm font-bold text-gray-700 mb-3 mt-6 flex items-center gap-2">
        <i class="fas fa-signature text-[#10A37F]"></i> Signatory Block (Footer)
    </h4>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="form-group">
            <label class="form-label" for="pdf_prepared_by_name">
                Prepared by — Name
                <span class="text-xs font-normal text-gray-400 ml-1">(System Admin / MENRO Staff)</span>
            </label>
            <input type="text" name="pdf_prepared_by_name" id="pdf_prepared_by_name"
                   value="<?php echo htmlspecialchars($pdf_prepared_by); ?>"
                   class="form-input"
                   placeholder="e.g., Juan Dela Cruz">
        </div>
        <div class="form-group">
            <label class="form-label" for="pdf_prepared_by_title">Prepared by — Title</label>
            <input type="text" name="pdf_prepared_by_title" id="pdf_prepared_by_title"
                   value="<?php echo htmlspecialchars($pdf_prepared_title); ?>"
                   class="form-input"
                   placeholder="MENRO Data Analyst / Administrator">
        </div>
        <div class="form-group">
            <label class="form-label" for="pdf_approved_by_name">
                Noted and Approved by — Name
                <span class="text-xs font-normal text-gray-400 ml-1">(MENRO Head)</span>
            </label>
            <input type="text" name="pdf_approved_by_name" id="pdf_approved_by_name"
                   value="<?php echo htmlspecialchars($pdf_approved_by); ?>"
                   class="form-input"
                   placeholder="e.g., Maria Santos">
        </div>
        <div class="form-group">
            <label class="form-label" for="pdf_approved_by_title">Noted and Approved by — Title</label>
            <input type="text" name="pdf_approved_by_title" id="pdf_approved_by_title"
                   value="<?php echo htmlspecialchars($pdf_approved_title); ?>"
                   class="form-input"
                   placeholder="Municipal Environment and Natural Resources Officer">
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FOOTER NOTE -->
    <!-- ============================================ -->
    <div class="form-group">
        <label class="form-label" for="pdf_footer_note">Footer Note</label>
        <input type="text" name="pdf_footer_note" id="pdf_footer_note"
               value="<?php echo htmlspecialchars($pdf_footer_note); ?>"
               class="form-input">
        <p class="text-xs text-gray-400 mt-1">Shown at the bottom of exported PDF documents.</p>
    </div>

    <!-- ============================================ -->
    <!-- LIVE PREVIEW -->
    <!-- ============================================ -->
    <div class="bg-gray-50 rounded-xl p-4 mb-6 border border-gray-200">
        <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-eye text-[#10A37F]"></i>
            Document Preview
        </h4>

        <!-- Official header preview -->
        <div class="bg-white rounded-lg border border-gray-200 p-4 mb-3">
            <div class="flex items-center justify-between gap-4 pb-3 mb-3" style="border-bottom: 3px solid #10A37F;">
                <div class="w-16 h-16 flex items-center justify-center">
                    <img src="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl() ?: ''); ?>" alt="LGU" class="max-w-full max-h-full object-contain" id="previewLguLogo" onerror="this.style.display='none';">
                </div>
                <div class="flex-1 text-center">
                    <div class="font-bold text-gray-800 text-sm" id="previewOfficeName"><?php echo htmlspecialchars($pdf_office_name); ?></div>
                    <div class="text-gray-500 text-xs mt-0.5" id="previewMunicipality"><?php echo htmlspecialchars($pdf_municipality); ?></div>
                    <div class="text-[#10A37F] font-bold text-[10px] tracking-wider uppercase mt-1">Environmental Hazard Analysis Report</div>
                </div>
                <div class="w-16 h-16 flex items-center justify-center">
                    <img src="<?php echo htmlspecialchars($menro_logo_url); ?>" alt="MENRO" class="max-w-full max-h-full object-contain" id="previewMenroLogo" onerror="this.style.display='none';">
                </div>
            </div>
            <div class="bg-gray-50 rounded-md p-2 text-xs text-gray-600 space-y-0.5">
                <div><span class="font-semibold">Reporting Period:</span> <span id="previewPeriod">All Time (through <?php echo date('F Y'); ?>)</span></div>
                <div><span class="font-semibold">Date Generated:</span> <?php echo date('F j, Y'); ?></div>
                <div><span class="font-semibold">Generated By:</span> <span id="previewGeneratedBy"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'System Admin'); ?></span></div>
            </div>
        </div>

        <!-- Signatory preview -->
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex justify-between gap-8 mb-3">
                <div class="flex-1">
                    <div class="text-xs font-bold text-gray-600">Prepared by:</div>
                    <div class="h-11"></div>
                    <div class="text-center">
                        <div class="font-bold text-gray-800 text-sm" id="previewPreparedName"><?php echo htmlspecialchars($pdf_prepared_by ?: '____________________'); ?></div>
                        <div class="text-xs text-gray-500" id="previewPreparedTitle"><?php echo htmlspecialchars($pdf_prepared_title); ?></div>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-bold text-gray-600">Noted and Approved by:</div>
                    <div class="h-11"></div>
                    <div class="text-center">
                        <div class="font-bold text-gray-800 text-sm" id="previewApprovedName"><?php echo htmlspecialchars($pdf_approved_by ?: '____________________'); ?></div>
                        <div class="text-xs text-gray-500" id="previewApprovedTitle"><?php echo htmlspecialchars($pdf_approved_title); ?></div>
                    </div>
                </div>
            </div>
            <div class="text-center text-[10px] text-gray-400" id="previewFooterNote"><?php echo htmlspecialchars($pdf_footer_note); ?></div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary">
            <i class="fas fa-save mr-1"></i> Save PDF Export Settings
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ---- MENRO logo upload preview (mirrors general.php pattern) ----
    const menroInput = document.getElementById('menroLogoInput');
    const menroArea = document.getElementById('menroLogoUploadArea');
    const menroPreviewImg = document.getElementById('menroLogoPreviewImg');
    const menroPreviewFallback = document.getElementById('menroLogoPreviewFallback');
    const previewMenroLogo = document.getElementById('previewMenroLogo');

    if (menroArea && menroInput) {
        menroArea.addEventListener('click', () => menroInput.click());
        menroArea.addEventListener('dragover', (e) => { e.preventDefault(); menroArea.classList.add('dragover'); });
        menroArea.addEventListener('dragleave', () => menroArea.classList.remove('dragover'));
        menroArea.addEventListener('drop', (e) => {
            e.preventDefault();
            menroArea.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                menroInput.files = e.dataTransfer.files;
                const label = menroArea.querySelector('.file-label');
                if (label) label.textContent = e.dataTransfer.files[0].name;
            }
        });
        menroInput.addEventListener('change', function() {
            const label = menroArea.querySelector('.file-label');
            if (label && this.files.length) label.textContent = this.files[0].name;
            if (this.files.length) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (menroPreviewImg) { menroPreviewImg.src = e.target.result; menroPreviewImg.style.display = 'block'; }
                    if (menroPreviewFallback) menroPreviewFallback.style.display = 'none';
                    if (previewMenroLogo) { previewMenroLogo.src = e.target.result; previewMenroLogo.style.display = 'block'; }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    // ---- Live preview text updates ----
    const bindPreview = function(inputId, previewId, fallback) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        if (!input || !preview) return;
        input.addEventListener('input', function() {
            preview.textContent = this.value.trim() || (fallback !== undefined ? fallback : '');
        });
    };
    bindPreview('pdf_office_name', 'previewOfficeName');
    bindPreview('pdf_municipality_name', 'previewMunicipality');
    bindPreview('pdf_prepared_by_name', 'previewPreparedName', '____________________');
    bindPreview('pdf_prepared_by_title', 'previewPreparedTitle');
    bindPreview('pdf_approved_by_name', 'previewApprovedName', '____________________');
    bindPreview('pdf_approved_by_title', 'previewApprovedTitle');
    bindPreview('pdf_footer_note', 'previewFooterNote');
});
</script>
