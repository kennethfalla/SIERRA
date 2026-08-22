<?php
// views/barangay/reporters_directory.php - BARANGAY REPORTERS DIRECTORY
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/helpers/SettingsHelper.php';
requireRole('barangay_official');

$database = new Database();
$db = $database->getConnection();
$barangay_id = (int)($_SESSION['barangay_id'] ?? 0);

$brgyStmt = $db->prepare("SELECT name FROM barangays WHERE id = ?");
$brgyStmt->execute([$barangay_id]);
$barangay_name = $brgyStmt->fetchColumn() ?: 'Your Barangay';

$active_tab = (($_GET['tab'] ?? 'residents') === 'non_residents') ? 'non_residents' : 'residents';
$search = trim($_GET['search'] ?? '');

// ============================================================
// CSV EXPORT HANDLER (runs BEFORE any HTML output)
// ============================================================
if (isset($_GET['export']) && in_array($_GET['export'], ['residents', 'non_residents'], true)) {
    $export_type = $_GET['export'];

    if ($export_type === 'residents') {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.contact_number,
                       u.purok_street, u.created_at,
                       (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id AND r.barangay_id = ?) AS report_count
                FROM users u
                WHERE u.user_type IS NULL AND u.barangay_id = ? AND u.is_resident = 1 AND u.is_active = 1
                ORDER BY report_count DESC, u.first_name ASC";
        $labels = ['ID', 'First Name', 'Last Name', 'Email', 'Contact Number', 'Purok / Street', 'Reports Submitted', 'Registered'];
    } else {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.contact_number,
                       u.is_resident, u.province, u.municipality, u.non_resident_address,
                       b.name AS home_barangay, u.created_at,
                       (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id AND r.barangay_id = ?) AS report_count
                FROM users u
                LEFT JOIN barangays b ON u.barangay_id = b.id
                WHERE u.user_type IS NULL
                  AND u.id IN (SELECT DISTINCT r2.user_id FROM reports r2 WHERE r2.barangay_id = ?)
                  AND (u.is_resident = 0 OR u.barangay_id IS NULL OR u.barangay_id != ?)
                  AND u.is_active = 1
                ORDER BY report_count DESC, u.first_name ASC";
        $labels = ['ID', 'First Name', 'Last Name', 'Email', 'Contact Number', 'Residency', 'Province / Municipality', 'Home Barangay', 'Reports Submitted', 'Registered'];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute([$barangay_id, $barangay_id, $barangay_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporters_' . $export_type . '_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $labels);

    foreach ($rows as $r) {
        if ($export_type === 'residents') {
            fputcsv($out, [
                str_pad($r['id'], 5, '0', STR_PAD_LEFT),
                $r['first_name'], $r['last_name'], $r['email'] ?? '',
                $r['contact_number'] ?? '', $r['purok_street'] ?? '',
                $r['report_count'] ?? 0,
                date('M d, Y', strtotime($r['created_at']))
            ]);
        } else {
            $residency = (($r['is_resident'] ?? 0) == 1) ? 'Resident (other barangay)' : 'Non-Resident';
            $loc = trim(implode(', ', array_filter([$r['municipality'] ?? '', $r['province'] ?? ''])));
            if ($loc === '' && !empty($r['non_resident_address'])) $loc = $r['non_resident_address'];
            fputcsv($out, [
                str_pad($r['id'], 5, '0', STR_PAD_LEFT),
                $r['first_name'], $r['last_name'], $r['email'] ?? '',
                $r['contact_number'] ?? '', $residency, $loc,
                $r['home_barangay'] ?? '-',
                $r['report_count'] ?? 0,
                date('M d, Y', strtotime($r['created_at']))
            ]);
        }
    }
    fclose($out);
    exit();
}

// ============================================================
// QUERY THE TWO DIRECTORIES
// ============================================================
$searchSql = '';
$searchParams = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $searchSql = " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR u.contact_number LIKE ?)";
    $searchParams = [$like, $like, $like];
}

$residentsSql = "SELECT u.id, u.first_name, u.last_name, u.email, u.contact_number,
                        u.purok_street, u.created_at,
                        (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id AND r.barangay_id = ?) AS report_count
                 FROM users u
                 WHERE u.user_type IS NULL AND u.barangay_id = ? AND u.is_resident = 1 AND u.is_active = 1
                 $searchSql
                 ORDER BY report_count DESC, u.first_name ASC";
$resStmt = $db->prepare($residentsSql);
$resStmt->execute(array_merge([$barangay_id, $barangay_id], $searchParams));
$residents = $resStmt->fetchAll(PDO::FETCH_ASSOC);

$nonResSql = "SELECT u.id, u.first_name, u.last_name, u.email, u.contact_number,
                     u.is_resident, u.province, u.municipality, u.non_resident_address,
                     b.name AS home_barangay, u.created_at,
                     (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id AND r.barangay_id = ?) AS report_count
              FROM users u
              LEFT JOIN barangays b ON u.barangay_id = b.id
              WHERE u.user_type IS NULL
                AND u.id IN (SELECT DISTINCT r2.user_id FROM reports r2 WHERE r2.barangay_id = ?)
                AND (u.is_resident = 0 OR u.barangay_id IS NULL OR u.barangay_id != ?)
                AND u.is_active = 1
                $searchSql
              ORDER BY report_count DESC, u.first_name ASC";
$nonResStmt = $db->prepare($nonResSql);
$nonResStmt->execute(array_merge([$barangay_id, $barangay_id, $barangay_id], $searchParams));
$non_residents = $nonResStmt->fetchAll(PDO::FETCH_ASSOC);

$display_rows = ($active_tab === 'residents') ? $residents : $non_residents;
$total_residents = count($residents);
$total_non_residents = count($non_residents);

function residencyLabelOf($r) {
    return (($r['is_resident'] ?? 0) == 1) ? 'Resident (other barangay)' : 'Non-Resident';
}
function reporterLocationOf($r) {
    $loc = trim(implode(', ', array_filter([$r['municipality'] ?? '', $r['province'] ?? ''])));
    if ($loc !== '') return $loc;
    if (!empty($r['non_resident_address'])) return $r['non_resident_address'];
    return '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Reporters Directory - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: #F5FBF6; overflow-x: hidden; }
        @media (max-width: 768px) { .ml-72 { margin-left: 0 !important; } }
        .tab-active { border-bottom: 3px solid #10A37F; color: #10A37F; font-weight: 700; }
        .tab-inactive { color: #6B7280; border-bottom: 3px solid transparent; font-weight: 500; }
        .tab-inactive:hover { color: #10A37F; border-bottom-color: #10A37F; }
        .tab-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 9999px; font-size: 0.6rem; font-weight: 700; background: #e5e7eb; color: #4b5563; }
        .tab-active .tab-badge { background: #10A37F; color: white; }
        .reporter-card { background: white; border: 1px solid rgba(16,163,127,0.08); border-radius: 14px; transition: all 0.2s ease; cursor: pointer; }
        .reporter-card:hover { transform: translateY(-2px); border-color: #10A37F; box-shadow: 0 8px 20px -8px rgba(16,163,127,0.15); }
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(8px); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 16px; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: white; border-radius: 16px; max-width: 640px; width: 100%; max-height: 88vh; overflow-y: auto; box-shadow: 0 20px 60px -12px rgba(0,0,0,0.25); }
        .modal-header { background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); padding: 1rem 1.5rem; border-radius: 16px 16px 0 0; position: sticky; top: 0; z-index: 10; }
        .modal-header h2 { color: white; font-size: 1.2rem; font-weight: 700; }
        .modal-header .close-btn { color: rgba(255,255,255,0.7); background: none; border: none; font-size: 1.5rem; cursor: pointer; }
        .modal-header .close-btn:hover { color: white; }
        .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-under_review { background: #DBEAFE; color: #1E40AF; }
        .status-in_progress { background: #FCE7F3; color: #9D174D; }
        .status-escalated_pending { background: #FDE68A; color: #92400E; }
        .status-escalated { background: #FED7AA; color: #9A3412; }
        .status-resolved { background: #D1FAE5; color: #065F46; }
        .status-rejected { background: #FEE2E2; color: #991B1B; }
        .status-cancelled { background: #F3F4F6; color: #4B5563; }
        .risk-low { background: #D1FAE5; color: #065F46; }
        .risk-medium { background: #FEF3C7; color: #92400E; }
        .risk-high { background: #FFEDD5; color: #9A3412; }
        .risk-critical { background: #FEE2E2; color: #991B1B; }
    </style>
</head>
<body class="bg-[#F5FBF6]">

<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<div class="lg:ml-72 min-h-screen">
    <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        <div class="mb-6">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-address-book text-[#10A37F] text-sm"></i>
                </div>
                <span class="text-xs uppercase tracking-wider text-[#10A37F] font-semibold">Reporters Directory</span>
            </div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Reporters Directory</h1>
                    <p class="text-gray-500 text-sm mt-1">Reporter directory for <span class="font-semibold"><?php echo htmlspecialchars($barangay_name); ?></span></p>
                </div>
                <div class="export-dropdown">
                    <button onclick="toggleExportMenu()" class="btn-export-trigger">
                        <i class="fas fa-download"></i> Export
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div id="exportMenu" class="export-dropdown-menu">
                        <div class="export-dropdown-header"><p><i class="fas fa-file-csv"></i> Export Directory</p></div>
                        <button class="export-dropdown-item" onclick="downloadExport('residents')">
                            <i class="fas fa-home"></i><span>Residents List</span>
                        </button>
                        <button class="export-dropdown-item" onclick="downloadExport('non_residents')">
                            <i class="fas fa-road"></i><span>Non-Residents &amp; Other Barangay</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-emerald-100">
            <nav class="flex gap-1 sm:space-x-8 overflow-x-auto whitespace-nowrap">
                <a href="?page=reporters-directory&tab=residents<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="px-3 sm:px-1 py-4 text-sm flex items-center gap-2 <?php echo $active_tab === 'residents' ? 'tab-active' : 'tab-inactive'; ?>">
                    <i class="fas fa-home"></i> Residents
                    <span class="tab-badge"><?php echo $total_residents; ?></span>
                </a>
                <a href="?page=reporters-directory&tab=non_residents<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="px-3 sm:px-1 py-4 text-sm flex items-center gap-2 <?php echo $active_tab === 'non_residents' ? 'tab-active' : 'tab-inactive'; ?>">
                    <i class="fas fa-road"></i> Non-Residents &amp; Other Barangay
                    <span class="tab-badge"><?php echo $total_non_residents; ?></span>
                </a>
            </nav>
            <form method="get" action="index.php" class="flex items-center gap-2">
                <input type="hidden" name="page" value="reporters-directory">
                <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search reporters..." class="border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-sm focus:outline-none focus:border-[#10A37F] w-48 sm:w-64">
                </div>
            </form>
        </div>

        <?php if (empty($display_rows)): ?>
            <div class="text-center py-16 bg-white rounded-xl border border-gray-100">
                <i class="fas fa-users-slash text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500 font-semibold">No reporters found</p>
                <p class="text-gray-400 text-sm mt-1">Try adjusting your search</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($display_rows as $r): ?>
                <div class="reporter-card p-4" onclick="viewReporter(<?php echo (int)$r['id']; ?>, '<?php echo htmlspecialchars(addslashes(trim($r['first_name'] . ' ' . $r['last_name']))); ?>')">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-100 to-emerald-200 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user text-[#10A37F]"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800 text-sm truncate"><?php echo htmlspecialchars(trim($r['first_name'] . ' ' . $r['last_name'])); ?></p>
                            <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($r['email'] ?: '—'); ?></p>
                        </div>
                    </div>
                    <div class="space-y-1.5 text-xs text-gray-500">
                        <div class="flex items-center gap-2"><i class="fas fa-phone-alt text-gray-300 w-4"></i><?php echo htmlspecialchars($r['contact_number'] ?? '—'); ?></div>
                        <?php if ($active_tab === 'residents'): ?>
                        <div class="flex items-center gap-2"><i class="fas fa-map-pin text-gray-300 w-4"></i><?php echo htmlspecialchars($r['purok_street'] ?: '—'); ?></div>
                        <?php else: ?>
                        <div class="flex items-center gap-2"><i class="fas fa-tag text-gray-300 w-4"></i><?php echo htmlspecialchars(residencyLabelOf($r)); ?></div>
                        <div class="flex items-center gap-2"><i class="fas fa-map-marker-alt text-gray-300 w-4"></i><?php echo htmlspecialchars(reporterLocationOf($r)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-xs font-semibold text-[#10A37F]"><?php echo (int)$r['report_count']; ?> report(s)</span>
                        <span class="text-xs text-gray-400">View <i class="fas fa-chevron-right text-[10px]"></i></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reporter Reports Modal -->
<div class="modal-overlay" id="reporterModal" onclick="if(event.target===this)closeReporterModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header flex justify-between items-center">
            <h2><i class="fas fa-user"></i> <span id="reporterModalName">Reporter</span></h2>
            <button class="close-btn" onclick="closeReporterModal()">&times;</button>
        </div>
        <div id="reporterModalBody" class="p-5">
            <div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-[#10A37F]"></i></div>
        </div>
    </div>
</div>

<script>
function toggleExportMenu() {
    document.getElementById('exportMenu').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    var dd = document.querySelector('.export-dropdown');
    var menu = document.getElementById('exportMenu');
    if (dd && menu && !dd.contains(e.target)) menu.classList.remove('open');
});
function downloadExport(type) {
    document.getElementById('exportMenu').classList.remove('open');
    window.location.href = '<?php echo BASE_URL; ?>index.php?page=reporters-directory&tab=<?php echo $active_tab; ?>&export=' + type;
}
function closeReporterModal() {
    document.getElementById('reporterModal').classList.remove('active');
}
function viewReporter(id, name) {
    var modal = document.getElementById('reporterModal');
    document.getElementById('reporterModalName').textContent = name;
    document.getElementById('reporterModalBody').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-[#10A37F]"></i></div>';
    modal.classList.add('active');
    fetch('<?php echo BASE_URL; ?>ajax/get_reporter_reports.php?reporter_id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                document.getElementById('reporterModalBody').innerHTML = '<p class="text-red-500 text-center py-4">' + data.error + '</p>';
                return;
            }
            var html = '<div class="mb-4 pb-3 border-b border-gray-100 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">'
                + '<span><i class="fas fa-envelope text-gray-300 mr-1"></i>' + (data.reporter.email || '—') + '</span>'
                + '<span><i class="fas fa-phone-alt text-gray-300 mr-1"></i>' + (data.reporter.contact || '—') + '</span>'
                + '<span><i class="fas fa-file-alt text-gray-300 mr-1"></i>' + data.count + ' report(s)</span>'
                + '</div>';
            if (!data.reports || data.reports.length === 0) {
                html += '<p class="text-gray-400 text-center py-6">No reports submitted in this barangay.</p>';
            } else {
                data.reports.forEach(function(r) {
                    var st = r.status.replace(/_/g, ' ');
                    var risk = r.risk_level || 'low';
                    html += '<div class="border border-gray-100 rounded-xl p-3 mb-2">'
                        + '<div class="flex items-start justify-between gap-2">'
                        + '<p class="font-semibold text-gray-800 text-sm">' + escapeHtml(r.title) + '</p>'
                        + '<span class="text-xs text-gray-400 whitespace-nowrap">#' + String(r.id).padStart(6, '0') + '</span>'
                        + '</div>'
                        + '<div class="flex flex-wrap gap-2 mt-2">'
                        + '<span class="status-badge status-' + r.status + '">' + ucfirst(st) + '</span>'
                        + '<span class="status-badge risk-' + risk + '">' + ucfirst(risk) + '</span>'
                        + '<span class="text-xs text-gray-400">' + (r.category_name || '') + '</span>'
                        + '</div>'
                        + '<p class="text-xs text-gray-500 mt-2">' + escapeHtml(r.description || '').substring(0, 140) + '</p>'
                        + '<p class="text-xs text-gray-400 mt-2"><i class="far fa-clock mr-1"></i>' + (r.created_at || '') + '</p>'
                        + '</div>';
                });
            }
            document.getElementById('reporterModalBody').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('reporterModalBody').innerHTML = '<p class="text-red-500 text-center py-4">Failed to load reports.</p>';
        });
}
function ucfirst(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
function escapeHtml(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeReporterModal();
});
</script>
</body>
</html>
