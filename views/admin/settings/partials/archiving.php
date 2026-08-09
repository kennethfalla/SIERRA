<?php
// views/admin/settings/partials/archiving.php
// Data Archiving & Retention - Three sections:
//   A. Automated Retention Rules (resolved / rejected / announcements)
//   B. Manual Archive Action Triggers (run now / export backup)
//   C. Archive Management Table (view details / restore)

// Load current archiving settings
$archive_after_days    = (int)SettingsHelper::get('archive_after_days', 30);
$archive_rejected_days = (int)SettingsHelper::get('archive_rejected_days', 60);
$archive_cron_enabled  = (bool)SettingsHelper::get('archive_cron_enabled', 0);
$archive_cron_secret   = (string)SettingsHelper::get('archive_cron_secret', '');

$csrf_token = InputSanitizer::generateCsrfToken();

// Build the archive management dataset (archived reports + announcements)
$database = new Database();
$db = $database->getConnection();

$archive_rows = [];

$stmt = $db->query("
    SELECT r.id, r.title, r.status, r.barangay_name, r.resolved_at, r.rejected_at,
           r.archived_at, r.archived_reason, c.name AS category_name
    FROM reports r
    LEFT JOIN categories c ON c.id = r.category_id
    WHERE r.is_archived = 1
    ORDER BY r.archived_at DESC, r.id DESC
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $archive_rows[] = [
        'archive_id'     => 'ARC-' . str_pad((string)$r['id'], 5, '0', STR_PAD_LEFT),
        'source_type'    => 'report',
        'original_id'    => (int)$r['id'],
        'title'          => $r['title'],
        'category'       => $r['category_name'] ?? 'Uncategorized',
        'barangay'       => $r['barangay_name'] ?? '',
        'status'         => $r['status'],
        'closed_at'      => $r['resolved_at'] ?: $r['rejected_at'],
        'archived_at'    => $r['archived_at'],
        'archived_reason'=> $r['archived_reason'],
    ];
}

$stmt = $db->query("
    SELECT a.id, a.title, a.category, a.expires_at, a.archived_at, b.name AS barangay_name
    FROM announcements a
    LEFT JOIN barangays b ON b.id = a.barangay_id
    WHERE a.is_archived = 1
    ORDER BY a.archived_at DESC, a.id DESC
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $archive_rows[] = [
        'archive_id'     => 'ANN-' . str_pad((string)$a['id'], 5, '0', STR_PAD_LEFT),
        'source_type'    => 'announcement',
        'original_id'    => (int)$a['id'],
        'title'          => $a['title'],
        'category'       => $a['category'] ?? 'General',
        'barangay'       => $a['barangay_name'] ?? 'All Barangays',
        'status'         => 'expired',
        'closed_at'      => $a['expires_at'],
        'archived_at'    => $a['archived_at'],
        'archived_reason'=> 'expired',
    ];
}

// Sort by archived_at descending (newest first)
usort($archive_rows, function ($a, $b) {
    return strcmp((string)($b['archived_at'] ?? ''), (string)($a['archived_at'] ?? ''));
});

$settings_url = BASE_URL . 'controllers/SettingsController.php?tab=archiving';
?>

<style>
    .form-group { margin-bottom: 0.75rem; }
    .form-group label { display: block; font-weight: 600; color: #374151; font-size: 0.8rem; margin-bottom: 0.2rem; }
    .form-group .form-input {
        width: 100%; padding: 0.5rem 0.75rem; border: 1.5px solid #E5E7EB; border-radius: 0.5rem;
        font-size: 0.85rem; transition: all 0.2s; background: white; color: #1F2937;
    }
    .form-group .form-input:focus { border-color: #10A37F; outline: none; box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08); }
    .form-group .help-text { font-size: 0.7rem; color: #6B7280; margin-top: 0.2rem; }
    .btn-primary {
        background: linear-gradient(135deg, #10A37F, #0D8568); color: white; border: none; transition: all 0.3s ease;
        cursor: pointer; padding: 0.6rem 1.5rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.9rem;
    }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3); }
    .btn-secondary {
        background: white; border: 1px solid #e2e8f0; padding: 0.6rem 1.5rem; border-radius: 0.75rem;
        font-weight: 500; color: #4b5563; cursor: pointer; transition: all 0.2s;
    }
    .btn-secondary:hover { background: #f8fafc; }
    .card-info {
        background: #f0fdf4; border-left: 4px solid #10A37F; padding: 0.75rem 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem;
    }
    .card-info .title { font-weight: 600; color: #065f46; font-size: 0.9rem; }
    .card-info .desc { font-size: 0.8rem; color: #065f46; opacity: 0.9; }
    .toggle-switch { position: relative; width: 48px; height: 28px; flex-shrink: 0; cursor: pointer; display: inline-block; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider { position: absolute; inset: 0; background: #D1D5DB; border-radius: 9999px; transition: all 0.3s; }
    .toggle-slider::before {
        content: ''; position: absolute; height: 20px; width: 20px; left: 4px; bottom: 4px;
        background: white; border-radius: 50%; transition: all 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .toggle-switch input:checked + .toggle-slider { background: #10A37F; }
    .toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
    .setting-row {
        display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
        padding: 1rem; background: white; border: 1px solid #E5E7EB; border-radius: 0.75rem; margin-bottom: 1rem;
    }
    .setting-row .setting-title { font-weight: 600; color: #1F2937; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; }
    .setting-row .setting-desc { font-size: 0.8rem; color: #6B7280; margin-top: 0.2rem; line-height: 1.5; }
    .badge-active { background: #D1FAE5; color: #065F46; font-size: 0.65rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 0.25rem; }
    .badge-inactive { background: #F3F4F6; color: #6B7280; font-size: 0.65rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 0.25rem; }
    .code-block {
        font-family: monospace; font-size: 0.75rem; background: #1F2937; color: #A7F3D0;
        padding: 0.75rem 1rem; border-radius: 0.5rem; overflow-x: auto; margin-top: 0.5rem; word-break: break-all;
    }

    /* ===== SECTION HEADERS ===== */
    .archive-section-title {
        display: flex; align-items: center; gap: 0.6rem; font-weight: 700; font-size: 0.95rem;
        color: #065f46; margin: 1.75rem 0 0.85rem;
    }
    .archive-section-title .num {
        width: 1.6rem; height: 1.6rem; background: linear-gradient(135deg, #10A37F, #0D8568); color: white;
        border-radius: 0.5rem; display: flex; align-items: center; justify-content: center;
        font-size: 0.8rem; font-weight: 700; flex-shrink: 0;
    }

    /* ===== ARCHIVE TABLE ===== */
    .archive-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .archive-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .archive-table thead { background: #f9fafb; border-bottom: 2px solid #e5e7eb; }
    .archive-table thead th {
        padding: 0.75rem 1rem; text-align: left; font-weight: 600; font-size: 0.7rem;
        text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; white-space: nowrap;
    }
    .archive-table tbody tr { border-bottom: 1px solid #f3f4f6; transition: background 0.15s ease; }
    .archive-table tbody tr:hover { background: #fafafa; }
    .archive-table tbody tr:last-child { border-bottom: none; }
    .archive-table tbody td { padding: 0.6rem 1rem; vertical-align: middle; }
    .type-badge { font-size: 0.65rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 0.25rem; white-space: nowrap; }
    .type-report { background: #E0F2FE; color: #075985; }
    .type-announcement { background: #FEF3C7; color: #92400E; }
    .archive-search {
        width: 100%; max-width: 340px; padding: 0.5rem 0.85rem; border: 1.5px solid #E5E7EB;
        border-radius: 0.5rem; font-size: 0.85rem; transition: all 0.2s; background: white; color: #1F2937;
    }
    .archive-search:focus { border-color: #10A37F; outline: none; box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08); }
    .btn-restore {
        display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.3rem 0.7rem; border-radius: 0.5rem;
        font-size: 0.72rem; font-weight: 600; border: 1px solid #10A37F; background: #f0fdf4; color: #065f46;
        cursor: pointer; transition: all 0.2s ease; white-space: nowrap;
    }
    .btn-restore:hover { background: #10A37F; color: white; }
    .btn-view {
        display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.3rem 0.7rem; border-radius: 0.5rem;
        font-size: 0.72rem; font-weight: 600; border: 1px solid #e5e7eb; background: white; color: #4b5563;
        cursor: pointer; transition: all 0.2s ease; white-space: nowrap;
    }
    .btn-view:hover { background: #f8fafc; border-color: #cbd5e1; }
    .empty-archive { text-align: center; padding: 2.5rem 1rem; color: #9ca3af; }

    /* ===== DETAIL MODAL ===== */
    .archive-modal-backdrop {
        position: fixed; inset: 0; background: rgba(17, 24, 39, 0.55); z-index: 9999;
        display: none; align-items: center; justify-content: center; padding: 1rem;
    }
    .archive-modal-backdrop.open { display: flex; }
    .archive-modal {
        background: white; border-radius: 1rem; max-width: 560px; width: 100%; max-height: 85vh;
        overflow-y: auto; box-shadow: 0 20px 50px rgba(0,0,0,0.25);
    }
    .archive-modal-header {
        display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem;
        border-bottom: 1px solid #e5e7eb;
    }
    .archive-modal-header h3 { font-weight: 700; font-size: 1rem; color: #1F2937; }
    .archive-modal-close {
        width: 2rem; height: 2rem; border: none; background: #f3f4f6; border-radius: 0.5rem;
        cursor: pointer; color: #4b5563; font-size: 0.9rem; transition: all 0.2s;
    }
    .archive-modal-close:hover { background: #e5e7eb; }
    .archive-modal-body { padding: 1.25rem; }
    .detail-row { margin-bottom: 0.9rem; }
    .detail-row .k { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; margin-bottom: 0.15rem; }
    .detail-row .v { font-size: 0.9rem; color: #1F2937; line-height: 1.5; word-break: break-word; }
</style>

<div class="card-info">
    <div class="title"><i class="fas fa-archive mr-1"></i> Data Archiving & Retention</div>
    <div class="desc">
        Keeps the database fast and compliant. Old resolved reports, rejected/spam reports, and
        expired announcements are moved out of the active lists into the archive automatically.
        Use the manual triggers to run an archive now or export an audit backup.
    </div>
</div>

<!-- ============================================ -->
<!-- SECTION A: AUTOMATED RETENTION RULES -->
<!-- ============================================ -->
<div class="archive-section-title"><span class="num">A</span> Automated Retention Rules</div>

<form method="POST" action="<?php echo $settings_url; ?>" id="archivingForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="sub_action" value="save_rules">

    <!-- Resolved Report Retention Period -->
    <div class="setting-row">
        <div style="flex:1;">
            <div class="setting-title">
                <i class="fas fa-check-circle text-[#10A37F]"></i>
                Resolved Report Retention Period
            </div>
            <p class="setting-desc">
                Move <strong>Resolved</strong> tickets to the archive after this many days of being
                closed. Resolved reports are archived forever (never permanently deleted).
            </p>
            <div class="form-group" style="max-width: 240px; margin-top: 0.75rem;">
                <label for="archive_after_days">Retention period</label>
                <select name="archive_after_days" id="archive_after_days" class="form-input">
                    <?php
                    $options = [30 => '30 Days', 90 => '90 Days', 365 => '1 Year'];
                    foreach ($options as $val => $label):
                    ?>
                        <option value="<?php echo $val; ?>" <?php echo $archive_after_days == $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="help-text">After this period, resolved reports leave the active lists.</p>
            </div>
        </div>
    </div>

    <!-- Spam / Rejected Retention Period -->
    <div class="setting-row">
        <div style="flex:1;">
            <div class="setting-title">
                <i class="fas fa-ban text-amber-600"></i>
                Spam / Rejected Retention Period
            </div>
            <p class="setting-desc">
                Move <strong>Rejected / Spam</strong> reports to the archive after this many days,
                and <strong>permanently delete</strong> them once the full window has elapsed.
                Export an archive backup before purging if you need an audit copy.
            </p>
            <div class="form-group" style="max-width: 240px; margin-top: 0.75rem;">
                <label for="archive_rejected_days">Retention before permanent deletion</label>
                <select name="archive_rejected_days" id="archive_rejected_days" class="form-input">
                    <?php
                    $rej_options = [30 => '30 Days', 60 => '60 Days', 90 => '90 Days'];
                    foreach ($rej_options as $val => $label):
                    ?>
                        <option value="<?php echo $val; ?>" <?php echo $archive_rejected_days == $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="help-text">After archiving, rejected reports are kept for this window, then purged from the database.</p>
            </div>
        </div>
    </div>

    <!-- Expired Announcements (read-only note) -->
    <div class="setting-row">
        <div style="flex:1;">
            <div class="setting-title">
                <i class="fas fa-bullhorn text-indigo-600"></i>
                Expired Municipal Broadcasts &amp; Announcements
            </div>
            <p class="setting-desc">
                Announcements whose <strong>expiry date has passed</strong> are automatically moved
                to the archive on the next archive run. They remain restorable.
            </p>
        </div>
        <span class="badge-active">Automatic</span>
    </div>

    <!-- Master Toggle -->
    <div class="setting-row">
        <div style="flex:1;">
            <div class="setting-title">
                <i class="fas fa-toggle-on text-[#10A37F]"></i>
                Enable Auto-Archiving (Master Switch)
                <span class="badge-<?php echo $archive_cron_enabled ? 'active' : 'inactive'; ?>">
                    <?php echo $archive_cron_enabled ? 'Active' : 'Disabled'; ?>
                </span>
            </div>
            <p class="setting-desc">
                When enabled, a scheduled job (Windows Task Scheduler, Linux cron, or a hosted
                scheduler) runs all three retention rules above automatically. The job is
                triggered via a private, secret-protected URL.
            </p>

            <?php if ($archive_cron_enabled): ?>
                <?php if ($archive_cron_secret !== ''): ?>
                <p class="setting-desc" style="margin-top: 0.75rem;">
                    <strong>Schedule this URL</strong> (e.g. daily):
                </p>
                <div class="code-block">
                    <?php echo htmlspecialchars(BASE_URL . 'cron/archive_reports.php?key=' . $archive_cron_secret); ?>
                </div>
                <p class="help-text" style="margin-top: 0.5rem;">
                    Or run from the command line (no key needed):
                    <code>php <?php echo htmlspecialchars(BASE_PATH); ?>cron/archive_reports.php</code>
                </p>
                <?php else: ?>
                <p class="help-text" style="margin-top: 0.75rem;">
                    A secret URL will be generated when you save with this toggle ON.
                </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="toggle-switch">
            <input type="checkbox" name="archive_cron_enabled" id="archive_cron_enabled"
                   value="1" <?php echo $archive_cron_enabled ? 'checked' : ''; ?>>
            <label class="toggle-slider" for="archive_cron_enabled"></label>
        </div>
    </div>

    <!-- Save Rules -->
    <div class="flex flex-wrap gap-3 justify-end pt-4 border-t border-gray-200">
        <button type="reset" onclick="resetArchivingForm()" class="btn-secondary">
            <i class="fas fa-undo mr-2"></i>Reset
        </button>
        <button type="submit" class="btn-primary">
            <i class="fas fa-save mr-2"></i>Save Retention Rules
        </button>
    </div>
</form>

<!-- ============================================ -->
<!-- SECTION B: MANUAL ARCHIVE ACTION TRIGGERS -->
<!-- ============================================ -->
<div class="archive-section-title"><span class="num">B</span> Manual Archive Action Triggers</div>

<div class="setting-row" style="align-items:center;">
    <div style="flex:1;">
        <div class="setting-title">
            <i class="fas fa-play text-[#10A37F]"></i>
            Run Manual Archive Now
        </div>
        <p class="setting-desc">
            Execute all retention rules immediately, without waiting for the scheduled job.
            Rejected reports that have passed their retention window will be permanently purged.
        </p>
    </div>
    <form method="POST" action="<?php echo $settings_url; ?>" style="flex-shrink:0;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="sub_action" value="run_archive">
        <button type="submit" class="btn-primary" onclick="return confirm('Run the archiving job now? Rejected reports past their retention window will be permanently deleted.');">
            <i class="fas fa-play mr-2"></i>Run Manual Archive Now
        </button>
    </form>
</div>

<div class="setting-row" style="align-items:center;">
    <div style="flex:1;">
        <div class="setting-title">
            <i class="fas fa-download text-indigo-600"></i>
            Export Archive Backup
        </div>
        <p class="setting-desc">
            Download all archived data as a CSV spreadsheet or a portable SQL file for
            off-site audit storage.
        </p>
    </div>
    <form method="POST" action="<?php echo $settings_url; ?>" style="flex-shrink:0; display:flex; gap:0.5rem; align-items:center;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="sub_action" value="export">
        <select name="export_format" class="form-input" style="width:auto; padding:0.5rem 0.75rem;">
            <option value="csv">CSV</option>
            <option value="sql">SQL</option>
        </select>
        <button type="submit" class="btn-secondary">
            <i class="fas fa-download mr-2"></i>Export
        </button>
    </form>
</div>

<!-- ============================================ -->
<!-- SECTION C: ARCHIVE MANAGEMENT TABLE -->
<!-- ============================================ -->
<div class="archive-section-title"><span class="num">C</span> Archive Management</div>

<div class="bg-white rounded-2xl border border-[#10A37F]/10 overflow-hidden">
    <div class="p-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h4 class="font-bold text-gray-800 text-sm"><i class="fas fa-box-archive text-[#10A37F] mr-2"></i>Archived Items</h4>
            <p class="text-xs text-gray-500 mt-0.5"><?php echo count($archive_rows); ?> item(s) currently archived · search to filter</p>
        </div>
        <input type="text" id="archiveSearch" class="archive-search" placeholder="Search by title, ID, category, barangay...">
    </div>

    <div class="archive-table-wrap">
        <table class="archive-table" id="archiveTable">
            <thead>
                <tr>
                    <th>Archive ID</th>
                    <th>Original ID</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Barangay Location</th>
                    <th>Date Resolved / Rejected</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($archive_rows) === 0): ?>
                    <tr>
                        <td colspan="7" class="empty-archive">
                            <i class="fas fa-inbox text-3xl text-gray-300 block mb-2"></i>
                            The archive is empty. Archived reports and announcements will appear here.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($archive_rows as $item):
                        $closed_display = $item['closed_at'] ? date('M d, Y H:i', strtotime($item['closed_at'])) : '—';
                    ?>
                    <tr class="archive-row"
                        data-title="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-id="<?php echo (int)$item['original_id']; ?>"
                        data-type="<?php echo $item['source_type']; ?>"
                        data-category="<?php echo htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-barangay="<?php echo htmlspecialchars($item['barangay'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-status="<?php echo htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-closed="<?php echo $closed_display; ?>"
                        data-archived="<?php echo $item['archived_at'] ? date('M d, Y H:i', strtotime($item['archived_at'])) : '—'; ?>">
                        <td><span class="font-mono text-xs text-gray-500"><?php echo htmlspecialchars($item['archive_id']); ?></span></td>
                        <td><span class="font-mono text-xs text-gray-700">#<?php echo (int)$item['original_id']; ?></span></td>
                        <td class="font-medium text-gray-800 max-w-[220px] truncate"><?php echo htmlspecialchars($item['title']); ?></td>
                        <td><span class="type-badge <?php echo $item['source_type'] === 'report' ? 'type-report' : 'type-announcement'; ?>"><?php echo htmlspecialchars($item['source_type']); ?></span> <?php echo htmlspecialchars($item['category']); ?></td>
                        <td class="text-gray-600"><?php echo htmlspecialchars($item['barangay']); ?></td>
                        <td class="text-gray-600 text-xs whitespace-nowrap"><?php echo $closed_display; ?></td>
                        <td>
                            <div class="flex items-center gap-1.5">
                                <button type="button" class="btn-view" onclick="viewArchiveItem(this)">
                                    <i class="fas fa-eye text-[10px]"></i>View
                                </button>
                                <form method="POST" action="<?php echo $settings_url; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="sub_action" value="restore">
                                    <input type="hidden" name="archive_type" value="<?php echo $item['source_type']; ?>">
                                    <input type="hidden" name="archive_id" value="<?php echo (int)$item['original_id']; ?>">
                                    <button type="submit" class="btn-restore" onclick="return confirm('Restore this <?php echo $item['source_type']; ?> back to the active system?');">
                                        <i class="fas fa-rotate-left text-[10px]"></i>Restore
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================ -->
<!-- DETAIL MODAL -->
<!-- ============================================ -->
<div class="archive-modal-backdrop" id="archiveModal">
    <div class="archive-modal">
        <div class="archive-modal-header">
            <h3><i class="fas fa-box-archive text-[#10A37F] mr-2"></i>Archived Item Details</h3>
            <button type="button" class="archive-modal-close" onclick="closeArchiveModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="archive-modal-body">
            <div class="detail-row"><div class="k">Archive ID</div><div class="v" id="mdlArchiveId">—</div></div>
            <div class="detail-row"><div class="k">Original Report / Announcement ID</div><div class="v" id="mdlOriginalId">—</div></div>
            <div class="detail-row"><div class="k">Title</div><div class="v" id="mdlTitle">—</div></div>
            <div class="detail-row"><div class="k">Source Type</div><div class="v" id="mdlType">—</div></div>
            <div class="detail-row"><div class="k">Category</div><div class="v" id="mdlCategory">—</div></div>
            <div class="detail-row"><div class="k">Barangay Location</div><div class="v" id="mdlBarangay">—</div></div>
            <div class="detail-row"><div class="k">Status</div><div class="v" id="mdlStatus">—</div></div>
            <div class="detail-row"><div class="k">Date Resolved / Rejected</div><div class="v" id="mdlClosed">—</div></div>
            <div class="detail-row"><div class="k">Archived At</div><div class="v" id="mdlArchived">—</div></div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================ -->
<script>
(function() {
    'use strict';

    const form = document.getElementById('archivingForm');

    // Reset form – reload page to discard changes
    window.resetArchivingForm = function() {
        if (confirm('Reset all fields to their saved values? Unsaved changes will be lost.')) {
            location.reload();
        }
    };

    // Warn about unsaved changes (rules form only; manual triggers are separate forms)
    let formChanged = false;
    form.addEventListener('input', function() { formChanged = true; });
    form.addEventListener('change', function() { formChanged = true; });
    form.addEventListener('submit', function() { formChanged = false; });
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

    // ===== ARCHIVE TABLE SEARCH =====
    const searchInput = document.getElementById('archiveSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('#archiveTable tbody tr.archive-row').forEach(function(row) {
                row.style.display = (q === '' || row.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    // ===== DETAIL MODAL =====
    window.viewArchiveItem = function(btn) {
        const row = btn.closest('tr');
        const set = (id, val) => { document.getElementById(id).textContent = val; };
        set('mdlArchiveId', row.dataset.type === 'announcement' ? 'ANN-' + String(row.dataset.id).padStart(5, '0') : 'ARC-' + String(row.dataset.id).padStart(5, '0'));
        set('mdlOriginalId', '#' + row.dataset.id);
        set('mdlTitle', row.dataset.title);
        set('mdlType', row.dataset.type.charAt(0).toUpperCase() + row.dataset.type.slice(1));
        set('mdlCategory', row.dataset.category);
        set('mdlBarangay', row.dataset.barangay);
        set('mdlStatus', row.dataset.status);
        set('mdlClosed', row.dataset.closed);
        set('mdlArchived', row.dataset.archived);
        document.getElementById('archiveModal').classList.add('open');
    };

    window.closeArchiveModal = function() {
        document.getElementById('archiveModal').classList.remove('open');
    };

    // Close modal on backdrop click
    document.getElementById('archiveModal').addEventListener('click', function(e) {
        if (e.target === this) closeArchiveModal();
    });

})();
</script>
