<?php
// views/admin/reports/users_report.php - PRINTABLE USER / ACCOUNT REPORT
// Paper-agnostic printable report of all registered users with filters
// for Status (Active/Inactive) and Barangay. Role filter included so the
// report can focus on Reporters/Residents, Barangay Officials, or MENRO staff.
// Opened via:
//   index.php?page=users-report&status=all|active|inactive&barangay=ID&role=all|citizen|barangay|menro[&autoprint=1]
require_once dirname(__DIR__, 3) . '/config/config.php';
requireLogin();

// Users reports are reserved for the System Administrator.
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "You are not permitted to view user reports.";
    header("Location: " . BASE_URL . "index.php?page=dashboard");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// ------------------------------------------------------------
// INPUTS
// ------------------------------------------------------------
$status   = in_array($_GET['status'] ?? 'all', ['all', 'active', 'inactive'], true) ? $_GET['status'] : 'all';
$barangay = isset($_GET['barangay']) ? (int)$_GET['barangay'] : 0;
$role     = in_array($_GET['role'] ?? 'all', ['all', 'citizen', 'barangay', 'menro'], true) ? $_GET['role'] : 'all';
$autoprint = !empty($_GET['autoprint']);

// ------------------------------------------------------------
// QUERY USERS
// ------------------------------------------------------------
$where = ["1=1"];
$params = [];
if ($status === 'active')   { $where[] = "u.is_active = 1"; }
elseif ($status === 'inactive') { $where[] = "u.is_active = 0"; }
if ($barangay > 0) {
    $where[] = "u.barangay_id = :barangay";
    $params[':barangay'] = $barangay;
}
if ($role === 'citizen')  { $where[] = "(u.user_type IS NULL OR u.user_type = '')"; }
elseif ($role === 'barangay') { $where[] = "u.user_type = 'barangay_personnel'"; }
elseif ($role === 'menro')    { $where[] = "u.user_type IN ('menro_staff', 'admin')"; }

$sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.contact_number,
               u.user_type, u.is_active, u.is_resident, u.non_resident_address,
               u.job_title, u.created_at,
               b.name AS barangay_name
        FROM users u
        LEFT JOIN barangays b ON u.barangay_id = b.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY u.is_active DESC, u.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Barangay list for the filter dropdown.
$barangays = $db->query("SELECT id, name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$barangayName = '';
foreach ($barangays as $b) {
    if ((int)$b['id'] === $barangay) { $barangayName = $b['name']; break; }
}

// ------------------------------------------------------------
// DERIVED
// ------------------------------------------------------------
function roleLabelOf($user_type) {
    if ($user_type === 'admin') return 'System Admin';
    if ($user_type === 'menro_staff') return 'MENRO Staff';
    if ($user_type === 'barangay_personnel') return 'Barangay Official';
    return 'Reporter / Resident';
}

$totalUsers  = count($users);
$activeUsers = count(array_filter($users, fn($u) => (int)$u['is_active'] === 1));
$inactiveUsers = $totalUsers - $activeUsers;
$residents = count(array_filter($users, fn($u) => isset($u['is_resident']) && (int)$u['is_resident'] === 1));
$nonResidents = count(array_filter($users, fn($u) => isset($u['is_resident']) && (int)$u['is_resident'] === 0));
$activePct = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0;

$statusText = $status === 'active' ? 'Active Only' : ($status === 'inactive' ? 'Inactive Only' : 'All Statuses');
$roleText = $role === 'citizen' ? 'Reporters / Residents' : ($role === 'barangay' ? 'Barangay Officials' : ($role === 'menro' ? 'MENRO Staff & Admins' : 'All Roles'));
$barangayText = $barangay > 0 ? $barangayName : 'All Barangays';

// ------------------------------------------------------------
// ORGANIZATION / REPORT SETTINGS
// ------------------------------------------------------------
$lguLogo       = SettingsHelper::getLogoUrl();
$menroLogoPath = SettingsHelper::get('menro_logo', '');
$menroLogo     = $menroLogoPath ? BASE_URL . $menroLogoPath : '';
$officeName    = SettingsHelper::get('pdf_office_name', 'Municipal Environment and Natural Resources Office');
$municipality  = SettingsHelper::get('pdf_municipality_name', 'Municipality of San Isidro');
$systemName    = SettingsHelper::get('system_name', 'SIERRA');
$generatedBy   = $_SESSION['user_name'] ?? 'System Admin';
$generatedOn   = date('F j, Y \a\t h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ($lguLogo): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($lguLogo); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User / Account Report - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Manrope', Arial, sans-serif;
            background: #eef2f1;
            color: #1f2937;
            font-size: 11px;
        }

        /* ===== Screen-only toolbar ===== */
        .toolbar {
            max-width: 100%;
            margin: 16px auto 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 0 12px;
        }
        .toolbar button {
            background: #10A37F;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .toolbar button:hover { background: #0D8568; }
        .toolbar a {
            color: #374151;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
        }
        .controls {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 14px;
        }
        .controls label { font-size: 12px; font-weight: 600; color: #374151; }
        .controls input, .controls select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 12px;
            font-family: inherit;
            background: #fff;
            color: #1f2937;
        }

        /* ===== Report page (paper-agnostic) ===== */
        .report {
            width: 100%;
            min-height: 0;
            margin: 0 auto;
            background: #ffffff;
            padding: 10mm 12mm;
        }

        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding-bottom: 10px;
            border-bottom: 3px solid #10A37F;
        }
        .logo-box {
            width: 22mm;
            height: 22mm;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .logo-box img { max-width: 22mm; max-height: 22mm; object-fit: contain; }
        .logo-placeholder {
            width: 22mm;
            height: 22mm;
            border: 1px dashed #d1d5db;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 8px;
            text-align: center;
        }
        .org-block { flex: 1; text-align: center; padding: 0 6px; }
        .org-line1 { font-size: 10px; letter-spacing: 0.12em; color: #4b5563; text-transform: uppercase; }
        .org-name { font-size: 15px; font-weight: 800; color: #111827; margin-top: 2px; line-height: 1.25; }
        .org-muni { font-size: 11px; color: #374151; margin-top: 2px; font-weight: 600; }

        .report-title-block { text-align: center; margin: 12px 0 12px; }
        .report-title { font-size: 19px; font-weight: 800; letter-spacing: 0.02em; color: #0D8568; }
        .report-subtitle { font-size: 11px; color: #4b5563; margin-top: 3px; font-weight: 600; }
        .report-meta {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
            font-size: 10px;
            color: #6b7280;
        }
        .report-meta span {
            background: #f4faf7;
            border: 1px solid #dff0e9;
            border-radius: 999px;
            padding: 3px 10px;
        }

        /* ===== KPI stat cards ===== */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }
        .kpi-card {
            border: 1px solid #e5e7eb;
            border-top: 4px solid #10A37F;
            border-radius: 10px;
            padding: 10px 12px;
            background: #fafcfb;
            text-align: center;
        }
        .kpi-card .kpi-label { font-size: 8.5px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi-card .kpi-value { font-size: 22px; font-weight: 800; color: #111827; margin-top: 2px; line-height: 1.1; }
        .kpi-card .kpi-sub { font-size: 9px; color: #9ca3af; margin-top: 3px; }
        .kpi-green { border-top-color: #10B981; }
        .kpi-red   { border-top-color: #EF4444; }
        .kpi-blue  { border-top-color: #3B82F6; }
        .kpi-amber { border-top-color: #F59E0B; }

        /* ===== Table ===== */
        .user-table-wrap { margin-top: 14px; }
        .table-caption {
            font-size: 12px;
            font-weight: 800;
            color: #0D8568;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 6px 10px;
            background: #f4faf7;
            border: 1px solid #dff0e9;
            border-left: 5px solid #10A37F;
            border-radius: 6px;
            margin-bottom: 6px;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        table.data th {
            background: #f4faf7;
            color: #374151;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            text-align: left;
            padding: 5px 7px;
            border: 1px solid #dff0e9;
        }
        table.data td {
            padding: 4px 7px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
            vertical-align: top;
        }
        table.data tr:nth-child(even) td { background: #fafcfb; }
        .badge-active { color: #047857; font-weight: 700; }
        .badge-inactive { color: #b91c1c; font-weight: 700; }

        .signature-block {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 30px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
        }
        .sig-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .sig-line { border-bottom: 1px solid #374151; margin-top: 28px; }
        .sig-name { font-size: 12px; font-weight: 700; color: #111827; margin-top: 4px; text-align: center; }
        .sig-title { font-size: 10px; color: #6b7280; text-align: center; }

        .report-footer {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #6b7280;
        }
        .report-footer .brand { font-weight: 700; color: #0D8568; }

        @page {
            margin: 10mm 12mm;
        }
        @media print {
            body { background: #ffffff !important; }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .toolbar, .controls { display: none !important; }
            .report {
                width: 100%;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            table.data tr { break-inside: avoid; }
            .report-title-block, .signature-block, .kpi-row { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <!-- Screen-only toolbar (hidden on print) -->
    <div class="toolbar">
        <button type="button" onclick="window.print()"><i class="fas fa-print" style="margin-right:6px;"></i>Print / Save as PDF</button>
        <a href="<?php echo BASE_URL; ?>index.php?page=settings&tab=users">&larr; Back to User Management</a>
        <span class="hint" style="color:#6b7280; font-size:11px;">Tip: choose "Save as PDF" as the printer destination for a PDF export.</span>
    </div>

    <!-- Screen-only report controls -->
    <form class="controls" method="get" action="<?php echo BASE_URL; ?>index.php">
        <input type="hidden" name="page" value="users-report">
        <label>Status
            <select name="status">
                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </label>
        <label>Role
            <select name="role">
                <option value="all" <?php echo $role === 'all' ? 'selected' : ''; ?>>All Roles</option>
                <option value="citizen" <?php echo $role === 'citizen' ? 'selected' : ''; ?>>Reporters / Residents</option>
                <option value="barangay" <?php echo $role === 'barangay' ? 'selected' : ''; ?>>Barangay Officials</option>
                <option value="menro" <?php echo $role === 'menro' ? 'selected' : ''; ?>>MENRO Staff & Admins</option>
            </select>
        </label>
        <label>Barangay
            <select name="barangay">
                <option value="0" <?php echo $barangay === 0 ? 'selected' : ''; ?>>All Barangays</option>
                <?php foreach ($barangays as $b): ?>
                <option value="<?php echo (int)$b['id']; ?>" <?php echo $barangay === (int)$b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Generate Report</button>
    </form>

    <div class="report">
        <!-- ===== Official LGU Header ===== -->
        <header class="report-header">
            <div class="logo-box">
                <?php if ($lguLogo): ?>
                    <img src="<?php echo htmlspecialchars($lguLogo); ?>" alt="LGU Logo">
                <?php else: ?>
                    <div class="logo-placeholder">LGU<br>Logo</div>
                <?php endif; ?>
            </div>
            <div class="org-block">
                <div class="org-line1">Republic of the Philippines</div>
                <div class="org-name"><?php echo htmlspecialchars($officeName); ?></div>
                <div class="org-muni"><?php echo htmlspecialchars($municipality); ?></div>
            </div>
            <div class="logo-box">
                <?php if ($menroLogo): ?>
                    <img src="<?php echo htmlspecialchars($menroLogo); ?>" alt="MENRO Logo">
                <?php else: ?>
                    <div class="logo-placeholder">MENRO<br>Logo</div>
                <?php endif; ?>
            </div>
        </header>

        <!-- ===== Report Title & Metadata ===== -->
        <div class="report-title-block">
            <div class="report-title">USER / ACCOUNT REPORT</div>
            <div class="report-subtitle">Registered Users in the System &middot; <?php echo htmlspecialchars($municipality); ?></div>
            <div class="report-meta">
                <span><strong>Status:</strong> <?php echo htmlspecialchars($statusText); ?></span>
                <span><strong>Role:</strong> <?php echo htmlspecialchars($roleText); ?></span>
                <span><strong>Barangay:</strong> <?php echo htmlspecialchars($barangayText); ?></span>
                <span><strong>Total Users:</strong> <?php echo number_format($totalUsers); ?></span>
                <span><strong>Generated By:</strong> <?php echo htmlspecialchars($generatedBy); ?></span>
                <span><strong>Generated On:</strong> <?php echo htmlspecialchars($generatedOn); ?></span>
            </div>
        </div>

        <!-- ===== KPI Stat Cards ===== -->
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-label">Total Users</div>
                <div class="kpi-value"><?php echo number_format($totalUsers); ?></div>
                <div class="kpi-sub">Registered accounts</div>
            </div>
            <div class="kpi-card kpi-green">
                <div class="kpi-label">Active</div>
                <div class="kpi-value" style="color:#059669;"><?php echo number_format($activeUsers); ?></div>
                <div class="kpi-sub"><?php echo $activePct; ?>% of total</div>
            </div>
            <div class="kpi-card kpi-red">
                <div class="kpi-label">Inactive</div>
                <div class="kpi-value" style="color:#dc2626;"><?php echo number_format($inactiveUsers); ?></div>
                <div class="kpi-sub">Suspended accounts</div>
            </div>
            <div class="kpi-card kpi-blue">
                <div class="kpi-label">Residents</div>
                <div class="kpi-value"><?php echo number_format($residents); ?></div>
                <div class="kpi-sub">Resident accounts</div>
            </div>
            <div class="kpi-card kpi-amber">
                <div class="kpi-label">Non-Residents</div>
                <div class="kpi-value"><?php echo number_format($nonResidents); ?></div>
                <div class="kpi-sub">Non-resident accounts</div>
            </div>
        </div>

        <!-- ===== Users Table ===== -->
        <div class="user-table-wrap">
            <div class="table-caption">Registered Users</div>
            <?php if (empty($users)): ?>
            <div style="text-align:center; padding:30px 0; color:#9ca3af; font-size:12px;">No users found for the selected filters.</div>
            <?php else: ?>
            <table class="data">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Barangay</th>
                        <th>Residency</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><strong><?php echo htmlspecialchars(trim($u['first_name'] . ' ' . $u['last_name'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($u['email'] ?: '—'); ?></td>
                        <td><?php echo !empty($u['contact_number']) ? htmlspecialchars(formatPhoneNumber($u['contact_number'])) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($u['barangay_name'] ?: '—'); ?></td>
                        <td><?php
                            if (isset($u['is_resident']) && (int)$u['is_resident'] === 1) { echo 'Resident'; }
                            elseif (isset($u['is_resident']) && (int)$u['is_resident'] === 0) { echo 'Non-Resident'; }
                            else { echo '—'; }
                        ?></td>
                        <td><?php echo htmlspecialchars(roleLabelOf($u['user_type'])); ?></td>
                        <td>
                            <?php if ((int)$u['is_active'] === 1): ?>
                                <span class="badge-active">Active</span>
                            <?php else: ?>
                                <span class="badge-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ===== Formal Sign-off ===== -->
        <div class="signature-block">
            <div>
                <div class="sig-label">Prepared by:</div>
                <div class="sig-line"></div>
                <div class="sig-name"><?php echo htmlspecialchars($generatedBy); ?></div>
                <div class="sig-title">System Administrator</div>
            </div>
            <div>
                <div class="sig-label">Noted and Approved by:</div>
                <div class="sig-line"></div>
                <div class="sig-name">____________________</div>
                <div class="sig-title">Municipal Environment and Natural Resources Officer</div>
            </div>
        </div>

        <!-- ===== Audit Trail Footer ===== -->
        <footer class="report-footer">
            <span>Date Printed: <?php echo date('F j, Y'); ?></span>
            <span>Time Printed: <?php echo date('h:i A'); ?></span>
            <span><?php echo htmlspecialchars($systemName); ?> &middot; Web-Based Environmental Reporting System</span>
        </footer>
    </div>

    <?php if ($autoprint): ?>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 700);
        });
    </script>
    <?php endif; ?>
</body>
</html>