<?php
// views/admin/reports/dashboard_report.php - DEDICATED PRINT/EXPORT REPORT PAGE
// A4-portrait printable "MENRO Analytics Report" generated from the MENRO
// Decision Dashboard. Opened in a new tab via:
//   index.php?page=dashboard-report&range=week|month|year|all|custom&cats=1,2,3
//           &sections=kpi,map,severity,...[&format=csv][&autoprint=1]
//   For custom ranges also pass from=YYYY-MM-DD&to=YYYY-MM-DD (inclusive).
// The `sections` parameter controls which analytics blocks are rendered /
// exported (both PDF and CSV). `format=csv` returns a CSV download instead of HTML.

require_once dirname(__DIR__, 3) . '/config/config.php';
if (!isLoggedIn() || !in_array($_SESSION['user_role'] ?? '', ['admin', 'barangay_official'], true)) {
    http_response_code(403);
    die('Access Denied');
}

$database = new Database();
$db = $database->getConnection();

// Barangay officials are strictly scoped to their own barangay.
$isBarangay  = (($_SESSION['user_role'] ?? '') === 'barangay_official');
$barangayId  = $isBarangay ? (int)($_SESSION['barangay_id'] ?? 0) : 0;
$scopeBarangaySql = $isBarangay ? ' AND r.barangay_id = :scope_barangay' : '';
$reportBrand = $isBarangay ? 'BARANGAY ANALYTICS REPORT' : 'MENRO ANALYTICS REPORT';

// ------------------------------------------------------------
// INPUTS
// ------------------------------------------------------------
$range = isset($_GET['range']) ? $_GET['range'] : 'all';
if (!in_array($range, ['today', 'week', 'month', 'year', 'all', 'custom'], true)) $range = 'all';

$from = isset($_GET['from']) ? preg_replace('/[^0-9-]/', '', $_GET['from']) : '';
$to   = isset($_GET['to'])   ? preg_replace('/[^0-9-]/', '', $_GET['to'])   : '';

$format = isset($_GET['format']) && $_GET['format'] === 'csv' ? 'csv' : 'html';

function isValidDateStr($s) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
    [$y, $m, $d] = array_map('intval', explode('-', $s));
    return checkdate($m, $d, $y);
}

$cats = [];
if (!empty($_GET['cats'])) {
    foreach (explode(',', $_GET['cats']) as $cid) {
        if (ctype_digit((string)$cid)) $cats[] = (int)$cid;
    }
}
$autoprint = !empty($_GET['autoprint']);

// ------------------------------------------------------------
// ADDITIONAL FILTERS (Status / Risk / Barangay)
// ------------------------------------------------------------
$validStatuses = ['all','pending','under_review','verified','in_progress','escalated_pending','escalated','resolved','rejected','cancelled'];
$statusFilter = in_array($_GET['status'] ?? 'all', $validStatuses, true) ? ($_GET['status']) : 'all';

$validRisks = ['all','low','medium','high','critical'];
$riskFilter = in_array($_GET['risk'] ?? 'all', $validRisks, true) ? ($_GET['risk']) : 'all';

$barangayFilter = isset($_GET['barangay']) ? (int)$_GET['barangay'] : 0;
if ($isBarangay) $barangayFilter = 0;

// ------------------------------------------------------------
// SECTION SELECTION
// ------------------------------------------------------------
$sectionLabels = [
    'kpi'          => 'KPI Summary',
    'map'          => 'Environmental Hazard Map',
    'severity'     => 'Severity Distribution',
    'seasonal'     => 'Seasonal Hazard Trends',
    'leaderboard'  => 'Barangay Performance Leaderboard',
    'response'     => 'Average Municipal Response Time',
    'demographics' => 'Reporter Demographics',
    'peak'         => 'Peak Reporting Hours & Days',
    'repeat'       => 'Top 5 Repeat Offender Locations',
];

$sections = [];
$rawSections = $_GET['sections'] ?? null;
if (is_array($rawSections)) {
    foreach ($rawSections as $sec) {
        $sec = trim((string)$sec);
        if (isset($sectionLabels[$sec])) $sections[] = $sec;
    }
} elseif (is_string($rawSections) && $rawSections !== '') {
    foreach (explode(',', $rawSections) as $sec) {
        $sec = trim($sec);
        if (isset($sectionLabels[$sec])) $sections[] = $sec;
    }
}
if (empty($sections)) $sections = array_keys($sectionLabels);

// If the on-page filter form supplies an explicit from/to date, treat the
// report as a custom date range (even if `range` was not set to "custom").
if (($from !== '' || $to !== '') && $range !== 'custom') {
    $range = 'custom';
}

// ------------------------------------------------------------
// DATE WINDOW + PERIOD LABELS
// ------------------------------------------------------------
switch ($range) {
    case 'today':
        $startDate   = date('Y-m-d 00:00:00');
        $periodStart = $startDate;
        $periodLabel = 'Today';
        $rangeLabel  = 'Daily';
        $endDate     = date('Y-m-d 23:59:59');
        break;
    case 'week':
        $startDate   = date('Y-m-d H:i:s', strtotime('-7 days'));
        $periodStart = date('Y-m-d H:i:s', strtotime('-7 days'));
        $periodLabel = 'This Week';
        $rangeLabel  = 'Weekly';
        $endDate     = date('Y-m-d H:i:s');
        break;
    case 'month':
        $startDate   = date('Y-m-d H:i:s', strtotime('-1 month'));
        $periodStart = date('Y-m-d H:i:s', strtotime('-1 month'));
        $periodLabel = 'This Month';
        $rangeLabel  = 'Monthly';
        $endDate     = date('Y-m-d H:i:s');
        break;
    case 'year':
        $startDate   = date('Y-m-d H:i:s', strtotime('-1 year'));
        $periodStart = date('Y-m-d H:i:s', strtotime(date('Y-01-01')));
        $periodLabel = 'This Year';
        $rangeLabel  = 'Annual';
        $endDate     = date('Y-m-d H:i:s');
        break;
    case 'custom':
        if (isValidDateStr($from) && isValidDateStr($to)) {
            $startDate   = $from . ' 00:00:00';
            $endDate     = $to . ' 23:59:59';
            $periodStart = $startDate;
            $periodLabel = 'Custom Range';
            $rangeLabel  = 'Custom';
        } elseif (isValidDateStr($from)) {
            $startDate   = $from . ' 00:00:00';
            $endDate     = date('Y-m-d H:i:s');
            $periodStart = $startDate;
            $periodLabel = 'Custom Range';
            $rangeLabel  = 'Custom';
        } elseif (isValidDateStr($to)) {
            $startDate   = '1970-01-01 00:00:00';
            $endDate     = $to . ' 23:59:59';
            $periodStart = $startDate;
            $periodLabel = 'Custom Range';
            $rangeLabel  = 'Custom';
        } else {
            $range       = 'all';
            $startDate   = '1970-01-01 00:00:00';
            $periodStart = date('Y-m-d H:i:s', strtotime(date('Y-m-01')));
            $periodLabel = 'This Month';
            $rangeLabel  = 'All-Time';
            $endDate     = date('Y-m-d H:i:s');
        }
        break;
    default:
        $startDate   = '1970-01-01 00:00:00';
        $periodStart = date('Y-m-d H:i:s', strtotime(date('Y-m-01')));
        $periodLabel = 'All Time';
        $rangeLabel  = 'All-Time';
        $endDate     = date('Y-m-d H:i:s');
        break;
}

$rangeText = 'All Time';
if ($range === 'today') {
    $rangeText = date('M j, Y', strtotime($from ?: date('Y-m-d')));
} elseif ($from && $to && $from === $to) {
    $rangeText = date('M j, Y', strtotime($from));
} elseif ($from && $to) {
    $rangeText = date('M j, Y', strtotime($from)) . ' to ' . date('M j, Y', strtotime($to));
} elseif ($from) $rangeText = 'From ' . date('M j, Y', strtotime($from));
elseif ($to) $rangeText = 'Up to ' . date('M j, Y', strtotime($to));

// Upper-bound clause only for explicit custom ranges; presets end "now".
$endSql = ($range === 'custom') ? ' AND r.created_at <= :end' : '';

// Category filter clause — ids are int-cast, safe to inline.
$catSql = '';
if (!empty($cats)) {
    $catSql = ' AND r.category_id IN (' . implode(',', array_map('intval', $cats)) . ')';
}

// Status / Risk / Barangay filter clause (bound params).
$filterSql = '';
$filterParams = [];
if ($statusFilter !== 'all') {
    $filterSql .= ' AND r.status = :status';
    $filterParams[':status'] = $statusFilter;
}
if ($riskFilter !== 'all') {
    $filterSql .= ' AND r.risk_level = :risk';
    $filterParams[':risk'] = $riskFilter;
}
if ($barangayFilter > 0) {
    $filterSql .= ' AND r.barangay_id = :barangay';
    $filterParams[':barangay'] = $barangayFilter;
}

// Shared WHERE scope + bound params for every report query.
$scopeWhere = "r.created_at >= :start $scopeBarangaySql$catSql$filterSql$endSql";
$scopeParams = [':start' => $startDate] + ($isBarangay ? [':scope_barangay' => $barangayId] : []) + $filterParams + ($endSql ? [':end' => $endDate] : []);

// Load barangay list for the on-page filter dropdown.
$barangayList = [];
try {
    $barangayList = $db->query("SELECT id, name FROM barangays ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $barangayList = [];
}

$statusLabels = [
    'all' => 'All Statuses', 'pending' => 'Pending', 'under_review' => 'Under Review',
    'verified' => 'Verified', 'in_progress' => 'In Progress',
    'escalated_pending' => 'Escalated Pending', 'escalated' => 'Escalated',
    'resolved' => 'Resolved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'
];
$riskLabels = ['all' => 'All Risk Levels', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];

// Report title reflects the applied filters — period + status/risk/barangay scope.
$analyticsScopeParts = [];
if ($statusFilter !== 'all')   $analyticsScopeParts[] = $statusLabels[$statusFilter] ?? ucwords(str_replace('_', ' ', $statusFilter));
if ($riskFilter !== 'all')     $analyticsScopeParts[] = $riskLabels[$riskFilter] ?? ucfirst($riskFilter);
if ($barangayFilter > 0) {
    $scopeBrgyName = '';
    foreach ($barangayList as $brgy) {
        if ((int)$brgy['id'] === $barangayFilter) { $scopeBrgyName = $brgy['name']; break; }
    }
    if ($scopeBrgyName !== '') $analyticsScopeParts[] = 'Barangay ' . $scopeBrgyName;
}
if ($from !== '' || $to !== '') {
    if ($from === date('Y-m-d') && $to === date('Y-m-d')) {
        $analyticsPeriodLabel = 'Today';
    } elseif ($from === date('Y-m-d', strtotime('-6 days')) && $to === date('Y-m-d')) {
        $analyticsPeriodLabel = 'This Week';
    } elseif ($from === date('Y-m-01') && $to === date('Y-m-d')) {
        $analyticsPeriodLabel = 'This Month';
    } elseif ($from === date('Y-01-01') && $to === date('Y-m-d')) {
        $analyticsPeriodLabel = 'This Year';
    } else {
        $analyticsPeriodLabel = $rangeText;
    }
} else {
    $analyticsPeriodLabel = ($range === 'all') ? 'All Time' : $periodLabel;
}
$analyticsTitle = $reportBrand;
if (!empty($analyticsScopeParts)) $analyticsTitle .= ' - ' . implode(' - ', $analyticsScopeParts);
$analyticsTitle .= ' - ' . $analyticsPeriodLabel;

// KPI / Insights targets (configurable in System Settings → KPI & Insights)
$kpi_resolution_rate_target = (float)SettingsHelper::get('kpi_resolution_rate_target', 60);
$kpi_sla_response_hours     = (float)SettingsHelper::get('kpi_sla_response_hours', 48);
$kpi_hotspot_radius_meters  = (float)SettingsHelper::get('kpi_hotspot_radius_meters', 10);
$kpi_critical_reports_pct   = (float)SettingsHelper::get('kpi_critical_reports_pct', 30);
$kpi_repeat_min_reports     = (float)SettingsHelper::get('kpi_repeat_min_reports', 3);
$kpi_repeat_window_days     = (float)SettingsHelper::get('kpi_repeat_window_days', 30);

// ------------------------------------------------------------
// 1. KPI METRICS
// ------------------------------------------------------------
// Active status constraint is only applied when no explicit status filter is set.
$activeStatusSql = ($statusFilter === 'all') ? " AND r.status NOT IN ('resolved','rejected','cancelled')" : '';

$activeHotspots = 0;
$avgRisk = 0;
$criticalCount = 0;
$resolvedHotspots = 0;

try {
    $kpiStmt = $db->prepare("SELECT COUNT(DISTINCT CASE WHEN spatial_density_count > 0 THEN CONCAT(latitude, ',', longitude) ELSE id END) AS count
        FROM reports r WHERE $scopeWhere $activeStatusSql AND latitude IS NOT NULL AND longitude IS NOT NULL");
    $kpiStmt->execute($scopeParams);
    $activeHotspots = (int)$kpiStmt->fetchColumn();
} catch (Exception $e) {}

try {
    $kpiStmt = $db->prepare("SELECT AVG(severity_score) AS avg_score FROM reports r WHERE $scopeWhere $activeStatusSql AND severity_score IS NOT NULL");
    $kpiStmt->execute($scopeParams);
    $avgRisk = round((float)$kpiStmt->fetchColumn(), 1);
} catch (Exception $e) {}

try {
    $kpiStmt = $db->prepare("SELECT COUNT(*) FROM reports r WHERE risk_level = 'critical' AND $scopeWhere $activeStatusSql");
    $kpiStmt->execute($scopeParams);
    $criticalCount = (int)$kpiStmt->fetchColumn();
} catch (Exception $e) {}

try {
    $kpiStmt = $db->prepare("SELECT COUNT(DISTINCT CONCAT(latitude, ',', longitude)) AS count FROM reports r
        WHERE status = 'resolved' AND spatial_density_count > 0 AND latitude IS NOT NULL AND longitude IS NOT NULL AND $scopeWhere");
    $kpiStmt->execute($scopeParams);
    $resolvedHotspots = (int)$kpiStmt->fetchColumn();
} catch (Exception $e) {}

// ------------------------------------------------------------
// 2. ACTIVE HAZARDS (map section)
// ------------------------------------------------------------
$activeReports = [];
try {
    $mapStmt = $db->prepare("SELECT r.id, r.title, r.severity_score, r.risk_level, r.status, r.location_address,
            COALESCE(c.name, '') AS category_name, COALESCE(b.name, '') AS barangay_name
        FROM reports r
        LEFT JOIN categories c ON c.id = r.category_id
        LEFT JOIN barangays b ON b.id = r.barangay_id
        WHERE $scopeWhere $activeStatusSql
          AND r.latitude IS NOT NULL AND r.longitude IS NOT NULL AND r.latitude != 0 AND r.longitude != 0
        ORDER BY r.severity_score DESC
        LIMIT 500");
    $mapStmt->execute($scopeParams);
    $activeReports = $mapStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ------------------------------------------------------------
// 3. SEVERITY DISTRIBUTION
// ------------------------------------------------------------
$severityBands = getSeverityBands();
$severityTiers = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
try {
    $tierStmt = $db->prepare("SELECT severity_score FROM reports r WHERE $scopeWhere $activeStatusSql AND severity_score IS NOT NULL");
    $tierStmt->execute($scopeParams);
    while ($row = $tierStmt->fetch(PDO::FETCH_ASSOC)) {
        $severityTiers[getRiskLevelFromScore($row['severity_score'])]++;
    }
} catch (Exception $e) {}
$severityTotal = array_sum($severityTiers);

// ------------------------------------------------------------
// 4. SEASONAL / MONTHLY TRENDS
// ------------------------------------------------------------
$monthlyCounts = [];
try {
    $monthStmt = $db->prepare("SELECT DATE_FORMAT(r.created_at, '%Y-%m') AS ym, COUNT(*) AS total
        FROM reports r WHERE $scopeWhere GROUP BY ym ORDER BY ym ASC");
    $monthStmt->execute($scopeParams);
    foreach ($monthStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $monthlyCounts[$row['ym']] = (int)$row['total'];
    }
} catch (Exception $e) {}

if ($range === 'custom') {
    $spanMonths  = max(0, (strtotime($to) - strtotime($from)) / (30.4 * 86400));
    $monthWindow = max(3, min(12, (int)ceil($spanMonths)));
} else {
    $monthWindow = ($range === 'year' || $range === 'all') ? 12 : 3;
}
$monthKeys   = [];
$monthLabels = [];
for ($i = $monthWindow - 1; $i >= 0; $i--) {
    $ts            = strtotime("first day of -$i month");
    $monthKeys[]   = date('Y-m', $ts);
    $monthLabels[] = date('M y', $ts);
}
$seriesData = [];
foreach ($monthKeys as $k) {
    $seriesData[] = $monthlyCounts[$k] ?? 0;
}
$totalCount = array_sum($seriesData);

// ------------------------------------------------------------
// 5. BARANGAY PERFORMANCE LEADERBOARD
// ------------------------------------------------------------
$barangayLeaderboard = [];
try {
    $lbStmt = $db->prepare("SELECT b.name AS barangay_name,
            COUNT(*) AS total_assigned,
            SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) AS total_resolved
        FROM reports r JOIN barangays b ON b.id = r.barangay_id
        WHERE $scopeWhere AND r.status NOT IN ('rejected','cancelled')
        GROUP BY b.id, b.name");
    $lbStmt->execute($scopeParams);
    $barangayLeaderboard = $lbStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
foreach ($barangayLeaderboard as &$brgy) {
    $brgy['total_assigned'] = (int)$brgy['total_assigned'];
    $brgy['total_resolved'] = (int)$brgy['total_resolved'];
    $brgy['resolution_rate'] = $brgy['total_assigned'] > 0
        ? round(($brgy['total_resolved'] / $brgy['total_assigned']) * 100, 1) : 0;
}
unset($brgy);
usort($barangayLeaderboard, function($a, $b) {
    if ($a['resolution_rate'] == $b['resolution_rate']) return $b['total_resolved'] <=> $a['total_resolved'];
    return $b['resolution_rate'] <=> $a['resolution_rate'];
});

// ------------------------------------------------------------
// 6. AVERAGE RESOLUTION TIME
// ------------------------------------------------------------
$avgResolutionHoursAllTime = 0;
$avgResolutionHoursThisMonth = 0;
$avgResolutionHoursLastMonth = 0;
try {
    $respScope = $isBarangay ? " AND barangay_id = " . (int)$barangayId : '';
    $avgResolutionHoursAllTime = (float)$db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM reports WHERE status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL$respScope")->fetchColumn();
    $avgResolutionHoursThisMonth = (float)$db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM reports WHERE status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')$respScope")->fetchColumn();
    $avgResolutionHoursLastMonth = (float)$db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM reports WHERE status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')$respScope")->fetchColumn();
} catch (Exception $e) {}
$avgResolutionDaysAllTime = round($avgResolutionHoursAllTime / 24, 1);
$avgResolutionDaysThisMonth = round($avgResolutionHoursThisMonth / 24, 1);
$avgResolutionDaysLastMonth = round($avgResolutionHoursLastMonth / 24, 1);
$resolutionTrend = 'stable';
if ($avgResolutionDaysLastMonth > 0) {
    $delta = round($avgResolutionDaysThisMonth - $avgResolutionDaysLastMonth, 1);
    if ($delta > 0.5) $resolutionTrend = 'worse';
    elseif ($delta < -0.5) $resolutionTrend = 'better';
}

// ------------------------------------------------------------
// 7. REPORTER DEMOGRAPHICS
// ------------------------------------------------------------
$demographics = ['resident' => 0, 'non_resident' => 0];
$demographicsAvailable = true;
try {
    $demStmt = $db->prepare("SELECT u.residency_status AS status_type, COUNT(*) AS total
        FROM reports r JOIN users u ON u.id = r.user_id WHERE $scopeWhere GROUP BY u.residency_status");
    $demStmt->execute($scopeParams);
    foreach ($demStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (strtolower($row['status_type']) === 'resident') ? 'resident' : 'non_resident';
        $demographics[$key] += (int)$row['total'];
    }
} catch (Exception $e) {
    try {
        $demStmt = $db->prepare("SELECT u.is_resident AS status_type, COUNT(*) AS total
            FROM reports r JOIN users u ON u.id = r.user_id WHERE $scopeWhere GROUP BY u.is_resident");
        $demStmt->execute($scopeParams);
        foreach ($demStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = ((int)$row['status_type'] === 1) ? 'resident' : 'non_resident';
            $demographics[$key] += (int)$row['total'];
        }
    } catch (Exception $e2) {
        $demographicsAvailable = false;
    }
}
$demographicsTotal = $demographics['resident'] + $demographics['non_resident'];
$residentPct    = $demographicsTotal > 0 ? round(($demographics['resident'] / $demographicsTotal) * 100, 1) : 0;
$nonResidentPct = $demographicsTotal > 0 ? round(($demographics['non_resident'] / $demographicsTotal) * 100, 1) : 0;

// ------------------------------------------------------------
// 8. PEAK REPORTING HOURS & DAYS
// ------------------------------------------------------------
$dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$dayCounts = array_fill(0, 7, 0);
$timeBuckets = ['Morning (6AM–12PM)' => 0, 'Afternoon (12PM–6PM)' => 0, 'Night (6PM–6AM)' => 0];
try {
    $dayStmt = $db->prepare("SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS total FROM reports r WHERE $scopeWhere GROUP BY DAYOFWEEK(created_at)");
    $dayStmt->execute($scopeParams);
    while ($row = $dayStmt->fetch(PDO::FETCH_ASSOC)) {
        $idx = (int)$row['dow'] - 1;
        if ($idx >= 0 && $idx < 7) $dayCounts[$idx] = (int)$row['total'];
    }

    $hourStmt = $db->prepare("SELECT HOUR(created_at) AS hr, COUNT(*) AS total FROM reports r WHERE $scopeWhere GROUP BY HOUR(created_at)");
    $hourStmt->execute($scopeParams);
    while ($row = $hourStmt->fetch(PDO::FETCH_ASSOC)) {
        $hr = (int)$row['hr'];
        $total = (int)$row['total'];
        if ($hr >= 6 && $hr < 12) $timeBuckets['Morning (6AM–12PM)'] += $total;
        elseif ($hr >= 12 && $hr < 18) $timeBuckets['Afternoon (12PM–6PM)'] += $total;
        else $timeBuckets['Night (6PM–6AM)'] += $total;
    }
} catch (Exception $e) {}

$peakDayTotal = max($dayCounts);
$peakDayIndex = array_search($peakDayTotal, $dayCounts);
$peakDayLabel = ($peakDayTotal > 0 && $peakDayIndex !== false) ? $dayLabels[$peakDayIndex] : 'N/A';
$peakTimeTotal = max($timeBuckets);
$peakTimeLabel = ($peakTimeTotal > 0) ? array_search($peakTimeTotal, $timeBuckets) : 'N/A';

// ------------------------------------------------------------
// 9. TOP 5 REPEAT OFFENDER LOCATIONS
// ------------------------------------------------------------
$repeatOffenders = [];
$hotspot_grid_deg = max(0.000001, ((float)$kpi_hotspot_radius_meters / 100.0) * 0.0009);
$repeat_min_sql = max(1, (int)$kpi_repeat_min_reports);
try {
    $stmt = $db->prepare("
        SELECT FLOOR(r.latitude / :grid) AS grid_lat_key, FLOOR(r.longitude / :grid) AS grid_lng_key,
            COUNT(*) AS incident_count, AVG(r.latitude) AS avg_lat, AVG(r.longitude) AS avg_lng,
            MAX(r.title) AS sample_title, MAX(b.name) AS barangay_name,
            GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') AS category_names
        FROM reports r
        JOIN categories c ON c.id = r.category_id
        LEFT JOIN barangays b ON b.id = r.barangay_id
        WHERE r.status = 'resolved'
          AND (c.name LIKE '%Dump%' OR c.name LIKE '%Vandal%' OR c.name LIKE '%Litter%' OR c.name LIKE '%Illegal%')
          AND r.latitude IS NOT NULL AND r.longitude IS NOT NULL AND r.latitude != 0 AND r.longitude != 0
          AND r.created_at >= :start $scopeBarangaySql$catSql$filterSql$endSql
        GROUP BY grid_lat_key, grid_lng_key
        HAVING incident_count > :min
        ORDER BY incident_count DESC
        LIMIT 5
    ");
    $stmt->bindValue(':grid', $hotspot_grid_deg);
    $stmt->bindValue(':min', $repeat_min_sql, PDO::PARAM_INT);
    foreach ($scopeParams as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $repeatOffenders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $repeatOffenders = [];
}

// ------------------------------------------------------------
// ORGANIZATION / PDF EXPORT SETTINGS ("Prepared by" = auto account name)
// ------------------------------------------------------------
$pdfCfg        = getPdfExportConfig($db);
$lguLogo       = $pdfCfg['lgu_logo'];
$menroLogo     = $pdfCfg['right_logo'];
$systemName    = $pdfCfg['system_name'];
$generatedBy   = $pdfCfg['generated_by'];
$generatedOn   = $pdfCfg['generated_on'];
$officeName    = $pdfCfg['office_name'];
$municipality  = $pdfCfg['municipality'];
$preparedBy    = $pdfCfg['prepared_by'];
$preparedTitle = $pdfCfg['prepared_title'];
$approvedBy    = $pdfCfg['approved_by'];
$approvedTitle = $pdfCfg['approved_title'];
$footerNote    = $pdfCfg['footer_note'];

$headerLine1 = $pdfCfg['header_lines'][0];
$headerLine2 = $pdfCfg['header_lines'][1];
$headerLine3 = $pdfCfg['header_lines'][2];
$headerLine4 = $pdfCfg['header_lines'][3];

// ------------------------------------------------------------
// CSV EXPORT
// ------------------------------------------------------------
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . ($isBarangay ? 'barangay' : 'menro') . '_analytics_report_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [$analyticsTitle]);
    fputcsv($out, ['System', $systemName]);
    fputcsv($out, ['Generated On', $generatedOn]);
    fputcsv($out, ['Date Range', $rangeText]);
    fputcsv($out, ['Period', $periodLabel]);
    if (!empty($cats)) fputcsv($out, ['Categories', count($cats) . ' selected']);
    fputcsv($out, []);

    foreach ($sections as $sec) {
        fputcsv($out, [strtoupper($sectionLabels[$sec])]);
        switch ($sec) {
            case 'kpi':
                fputcsv($out, ['Active Hotspots', $activeHotspots]);
                fputcsv($out, ['Avg Municipal Risk', $avgRisk]);
                fputcsv($out, ['Critical Escalations', $criticalCount]);
                fputcsv($out, ['Resolved Hotspots', $resolvedHotspots]);
                break;
            case 'map':
                fputcsv($out, ['ID', 'Title', 'Category', 'Barangay', 'Severity', 'Risk Level', 'Status', 'Address']);
                foreach ($activeReports as $r) {
                    fputcsv($out, [
                        '#' . str_pad($r['id'], 5, '0', STR_PAD_LEFT),
                        $r['title'], $r['category_name'], $r['barangay_name'],
                        $r['severity_score'] ?? 0,
                        $riskLabels[$r['risk_level']] ?? $r['risk_level'],
                        $statusLabels[$r['status']] ?? $r['status'],
                        $r['location_address'] ?? ''
                    ]);
                }
                break;
            case 'severity':
                fputcsv($out, ['Risk Level', 'Count', 'Share']);
                foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $lvl => $lbl) {
                    $share = $severityTotal > 0 ? round(($severityTiers[$lvl] / $severityTotal) * 100, 1) . '%' : '0%';
                    fputcsv($out, [$lbl, $severityTiers[$lvl], $share]);
                }
                break;
            case 'seasonal':
                fputcsv($out, ['Month', 'Total Reports']);
                foreach ($monthKeys as $i => $k) {
                    fputcsv($out, [date('F Y', strtotime($k . '-01')), $seriesData[$i]]);
                }
                break;
            case 'leaderboard':
                fputcsv($out, ['Rank', 'Barangay', 'Assigned', 'Resolved', 'Resolution Rate']);
                foreach ($barangayLeaderboard as $i => $b) {
                    fputcsv($out, [$i + 1, $b['barangay_name'], $b['total_assigned'], $b['total_resolved'], $b['resolution_rate'] . '%']);
                }
                break;
            case 'response':
                fputcsv($out, ['Average Resolution Time (All Time, Days)', $avgResolutionDaysAllTime]);
                fputcsv($out, ['This Month (Days)', $avgResolutionDaysThisMonth]);
                fputcsv($out, ['Last Month (Days)', $avgResolutionDaysLastMonth]);
                fputcsv($out, ['Trend', ucfirst($resolutionTrend)]);
                break;
            case 'demographics':
                fputcsv($out, ['Group', 'Count', 'Share']);
                fputcsv($out, ['Resident', $demographics['resident'], $residentPct . '%']);
                fputcsv($out, ['Non-Resident', $demographics['non_resident'], $nonResidentPct . '%']);
                break;
            case 'peak':
                fputcsv($out, ['Day of Week', 'Reports']);
                foreach ($dayLabels as $i => $d) fputcsv($out, [$d, $dayCounts[$i]]);
                fputcsv($out, []);
                fputcsv($out, ['Time of Day', 'Reports']);
                foreach ($timeBuckets as $tb => $c) fputcsv($out, [$tb, $c]);
                fputcsv($out, ['Peak Day', $peakDayLabel]);
                fputcsv($out, ['Peak Time', $peakTimeLabel]);
                break;
            case 'repeat':
                fputcsv($out, ['Rank', 'Sample Title', 'Categories', 'Barangay', 'Incidents', 'Latitude', 'Longitude']);
                foreach ($repeatOffenders as $i => $spot) {
                    fputcsv($out, [
                        $i + 1,
                        $spot['sample_title'] ?? '',
                        $spot['category_names'] ?? '',
                        $spot['barangay_name'] ?? '',
                        (int)($spot['incident_count'] ?? 0),
                        round((float)($spot['avg_lat'] ?? 0), 5),
                        round((float)($spot['avg_lng'] ?? 0), 5)
                    ]);
                }
                break;
        }
        fputcsv($out, []);
    }

    fputcsv($out, ['End of report. Generated by SIERRA Environmental Reporting System.']);
    fclose($out);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ($lguLogo): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($lguLogo); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isBarangay ? 'Barangay' : 'MENRO'; ?> Analytics Report - Sierra</title>
    <link href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css">
    <script src="<?php echo BASE_URL; ?>assets/vendor/chart/chart.umd.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/chart-stub.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Manrope', Arial, sans-serif; background: #eef2f1; color: #1f2937; font-size: 12px; }

        .toolbar { max-width: 210mm; margin: 16px auto 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .toolbar button { background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); color: #fff; border: none; border-radius: 8px; padding: 8px 16px; font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
        .toolbar button:hover { box-shadow: 0 4px 12px rgba(16,163,127,0.3); }
        .toolbar a { color: #374151; font-size: 12px; font-weight: 600; text-decoration: none; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; }
        .toolbar .hint { color: #6b7280; font-size: 11px; }

        .controls { max-width: 210mm; margin: 0 auto 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 14px; }
        .controls label { font-size: 12px; font-weight: 600; color: #374151; }
        .controls input[type="date"], .controls select { border: 1px solid #d1d5db; border-radius: 8px; padding: 6px 8px; font-size: 12px; font-family: inherit; background: #fff; color: #1f2937; }
        .controls .btn-generate { background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); color: #fff; border: none; border-radius: 8px; padding: 8px 16px; font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
        .controls .btn-generate:hover { box-shadow: 0 4px 12px rgba(16,163,127,0.3); }
        .filter-summary-bar { max-width: 210mm; margin: 0 auto 10px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; background: #f4faf7; border: 1px solid #dff0e9; border-radius: 8px; padding: 6px 12px; }
        .filter-summary-bar .fs-label { font-size: 10px; font-weight: 700; color: #0D8568; text-transform: uppercase; letter-spacing: 0.05em; }
        .filter-summary-bar .fs-chip { background: #d1fae5; color: #065f46; border-radius: 999px; padding: 2px 10px; font-size: 10px; font-weight: 600; }

        .report { width: 210mm; min-height: 297mm; margin: 0 auto; background: #ffffff; padding: 12mm 14mm; display: flex; flex-direction: column; }

        .report-header { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding-bottom: 10px; border-bottom: 3px solid #10A37F; }
        .logo-box { width: 24mm; height: 24mm; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .logo-box img { max-width: 24mm; max-height: 24mm; object-fit: contain; }
        .logo-placeholder { width: 24mm; height: 24mm; border: 1px dashed #d1d5db; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 8px; text-align: center; }
        .org-block { flex: 1; text-align: center; padding: 0 6px; }
        .org-line1 { font-size: 10px; letter-spacing: 0.12em; color: #4b5563; text-transform: uppercase; }
        .org-name { font-size: 15px; font-weight: 800; color: #111827; margin-top: 2px; line-height: 1.25; }
        .org-muni { font-size: 11px; color: #374151; margin-top: 2px; font-weight: 600; }

        .report-title-block { text-align: center; margin: 12px 0 14px; }
        .report-title { font-size: 19px; font-weight: 800; letter-spacing: 0.02em; color: #0D8568; }
        .report-subtitle { font-size: 11px; color: #4b5563; margin-top: 3px; font-weight: 600; }
        .report-meta { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-top: 8px; font-size: 10px; color: #6b7280; }
        .report-meta span { background: #f4faf7; border: 1px solid #dff0e9; border-radius: 999px; padding: 3px 10px; }

        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .kpi-card { border: 1px solid #e5e7eb; border-top: 4px solid #10A37F; border-radius: 8px; padding: 10px 12px; background: #fafcfb; text-align: center; }
        .kpi-card .kpi-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi-card .kpi-value { font-size: 24px; font-weight: 800; color: #111827; margin-top: 2px; line-height: 1.1; }

        .section { margin-top: 12px; break-inside: avoid; }
        .section-heading { font-size: 13px; font-weight: 800; color: #0D8568; text-transform: uppercase; letter-spacing: 0.03em; padding: 6px 10px; background: #f4faf7; border: 1px solid #dff0e9; border-left: 5px solid #10A37F; border-radius: 6px; margin-bottom: 8px; }
        .charts-row { display: grid; grid-template-columns: 1.25fr 1fr; gap: 10px; margin-top: 10px; }
        .chart-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; }
        .chart-heading { font-size: 11px; font-weight: 700; color: #1f2937; margin-bottom: 6px; }
        .chart-wrap { height: 210px; position: relative; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th { background: #f4faf7; color: #374151; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; text-align: left; padding: 5px 7px; border: 1px solid #dff0e9; }
        table.data td { padding: 4px 7px; border: 1px solid #e5e7eb; font-size: 10px; vertical-align: top; }
        table.data tr:nth-child(even) td { background: #fafcfb; }
        .mono { font-family: 'Manrope', sans-serif; font-size: 9.5px; }

        .badge { display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: 8px; font-weight: 700; }
        .badge-low { background: #d1fae5; color: #065f46; }
        .badge-medium { background: #fef3c7; color: #92400e; }
        .badge-high { background: #ffedd5; color: #9a3412; }
        .badge-critical { background: #fee2e2; color: #991b1b; }

        .empty-note { text-align: center; color: #9ca3af; font-size: 11px; padding: 12px 0; }

        .signature-block { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: auto; padding-top: 12px; border-top: 1px solid #e5e7eb; }
        .sig-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .sig-line { border-bottom: 1px solid #374151; margin-top: 30px; }
        .sig-name { font-size: 12px; font-weight: 700; color: #111827; margin-top: 4px; text-align: center; }
        .sig-title { font-size: 10px; color: #6b7280; text-align: center; }

        .report-footer { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px; margin-top: 22px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #6b7280; }
        .report-footer .brand { font-weight: 700; color: #0D8568; }
        .report-footer-note { margin-top: 6px; text-align: center; font-size: 8px; color: #9ca3af; }

        /* ===== Screen-only filter sidebar (main-sidebar style) ===== */
        .page-wrap { display: flex; align-items: flex-start; min-height: 100vh; }
        .filter-sidebar { width: 300px; flex-shrink: 0; background: #fff; border-right: 1px solid rgba(16,163,127,0.12); box-shadow: 2px 0 20px -8px rgba(16,163,127,0.18); position: sticky; top: 0; height: 100vh; display: flex; flex-direction: column; }
        .sidebar-head { padding: 16px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .sidebar-head .head-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 15px; box-shadow: 0 4px 10px rgba(16,163,127,0.3); flex-shrink: 0; }
        .sidebar-head .head-title { font-size: 14px; font-weight: 800; color: #111827; }
        .sidebar-head .head-sub { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 1px; }
        .sidebar-form { flex: 1; display: flex; flex-direction: column; min-height: 0; }
        .sidebar-body { flex: 1; overflow-y: auto; padding: 14px 16px; display: flex; flex-direction: column; gap: 16px; }
        .sidebar-group { display: flex; flex-direction: column; gap: 10px; }
        .quick-range { display: flex; flex-wrap: wrap; gap: 5px; }
        .quick-range a {
            padding: 4px 10px;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            background: #fff;
            font-size: 11px;
            font-weight: 600;
            color: #374151;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .quick-range a:hover { border-color: #10A37F; color: #10A37F; background: #F0FBF6; }
        .sidebar-group-label { font-size: 10px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.06em; }
        .filter-field { display: flex; flex-direction: column; gap: 4px; }
        .filter-field label { font-size: 11px; font-weight: 600; color: #374151; }
        .filter-field select, .filter-field input { width: 100%; padding: 9px 10px; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 12.5px; font-family: inherit; background: #fff; color: #1f2937; transition: all 0.15s ease; }
        .filter-field select:hover, .filter-field input:hover { border-color: #d1d5db; }
        .filter-field select:focus, .filter-field input:focus { border-color: #10A37F; outline: none; box-shadow: 0 0 0 3px rgba(16,163,127,0.12); }
        .section-check-list { display: flex; flex-direction: column; gap: 2px; }
        .section-check { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; cursor: pointer; transition: background 0.12s ease; font-size: 12px; color: #374151; font-weight: 500; }
        .section-check:hover { background: #F0FBF6; }
        .section-check input { accent-color: #10A37F; width: 15px; height: 15px; cursor: pointer; }
        .sidebar-footer { padding: 14px 16px; border-top: 1px solid #f3f4f6; background: #fff; display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }
        .btn-apply { width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%); color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.2s ease; }
        .btn-apply:hover { box-shadow: 0 6px 16px rgba(16,163,127,0.35); transform: translateY(-1px); }
        .btn-reset { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 12px; border: 1.5px solid #e5e7eb; border-radius: 10px; background: #fff; color: #6b7280; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.15s ease; }
        .btn-reset:hover { color: #EF4444; border-color: #EF4444; background: #FEF2F2; }
        .page-main { flex: 1; min-width: 0; padding: 16px; }
        @media (max-width: 900px) { .page-wrap { flex-direction: column; } .filter-sidebar { width: 100%; position: static; height: auto; border-right: none; border-bottom: 1px solid rgba(16,163,127,0.12); } }

        @page { size: A4 portrait; margin: 14mm 14mm 20mm; }
        @media print {
            body { background: #ffffff !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .filter-sidebar { display: none !important; }
            .page-wrap { display: block; padding: 0; }
            .page-main { padding: 0; }
            .toolbar { display: none !important; }
            .filter-summary-bar { display: none !important; }
            .report { width: 100%; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .kpi-card, .chart-card, .section, .signature-block, .report-header, .report-title-block { break-inside: avoid; }
            .chart-wrap { height: 200px; }
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <aside class="filter-sidebar">
            <div class="sidebar-head">
                <div class="head-icon"><i class="fas fa-sliders-h"></i></div>
                <div>
                    <div class="head-title">Filters</div>
                    <div class="head-sub">Refine report</div>
                </div>
            </div>
            <form class="sidebar-form" method="get" action="<?php echo BASE_URL; ?>index.php" onsubmit="return validateSections()">
                <input type="hidden" name="page" value="<?php echo $isBarangay ? 'barangay-dashboard-report' : 'dashboard-report'; ?>">
                <input type="hidden" name="range" value="<?php echo htmlspecialchars($range); ?>">
                <input type="hidden" name="cats" value="<?php echo htmlspecialchars(implode(',', $cats)); ?>">
                <div class="sidebar-body">
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Analytics Checklist</div>
                        <label class="section-check">
                            <input type="checkbox" id="sectionAll" <?php echo count($sections) === count($sectionLabels) ? 'checked' : ''; ?>>
                            <strong>Select All</strong>
                        </label>
                        <div class="section-check-list">
                            <?php foreach ($sectionLabels as $secId => $secLabel): ?>
                            <label class="section-check">
                                <input type="checkbox" class="section-cb" name="sections[]" value="<?php echo htmlspecialchars($secId); ?>" <?php echo in_array($secId, $sections, true) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($secLabel); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Filters</div>
                        <div class="filter-field">
                            <label for="sideStatus">Status</label>
                            <select name="status" id="sideStatus">
                                <?php foreach ($statusLabels as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $statusFilter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sideRisk">Risk</label>
                            <select name="risk" id="sideRisk">
                                <?php foreach ($riskLabels as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $riskFilter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (!$isBarangay): ?>
                        <div class="filter-field">
                            <label for="sideBarangay">Barangay</label>
                            <select name="barangay" id="sideBarangay">
                                <option value="0" <?php echo $barangayFilter === 0 ? 'selected' : ''; ?>>All Barangays</option>
                                <?php foreach ($barangayList as $brgy): ?>
                                <option value="<?php echo (int)$brgy['id']; ?>" <?php echo $barangayFilter === (int)$brgy['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($brgy['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Date</div>
                        <div class="quick-range">
                            <a href="#" onclick="setQuickReportRange('today'); return false;">Today</a>
                            <a href="#" onclick="setQuickReportRange('week'); return false;">This Week</a>
                            <a href="#" onclick="setQuickReportRange('month'); return false;">This Month</a>
                            <a href="#" onclick="setQuickReportRange('year'); return false;">This Year</a>
                            <a href="#" onclick="setQuickRange('all'); return false;">All Reports</a>
                        </div>
                    </div>
                    <div class="sidebar-group">
                        <div class="sidebar-group-label">Date Range</div>
                        <div class="filter-field">
                            <label for="sideFrom">From</label>
                            <input type="date" name="from" id="sideFrom" value="<?php echo htmlspecialchars($from); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="sideTo">To</label>
                            <input type="date" name="to" id="sideTo" value="<?php echo htmlspecialchars($to); ?>">
                        </div>
                    </div>
                </div>
                <div class="sidebar-footer">
                    <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="<?php echo BASE_URL; ?>index.php?page=<?php echo $isBarangay ? 'barangay-dashboard-report' : 'dashboard-report'; ?>&range=<?php echo htmlspecialchars($range); ?>&cats=<?php echo htmlspecialchars(implode(',', $cats)); ?>" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </aside>

        <div class="page-main">
            <div class="toolbar">
                <a href="<?php echo BASE_URL; ?>index.php?page=dashboard"><i class="fas fa-arrow-left" style="margin-right:6px;"></i>Back</a>
                <button type="button" onclick="window.print()"><i class="fas fa-print" style="margin-right:6px;"></i>Print</button>
                <button type="button" onclick="window.print()"><i class="fas fa-file-pdf" style="margin-right:6px;"></i>Save as PDF</button>
                <span class="hint">Tip: choose "Save as PDF" as the printer destination for an A4 PDF export.</span>
            </div>

    <?php
    $filterChips = [];
    if ($from || $to) {
        $fr = $from ? date('M j, Y', strtotime($from)) : '&hellip;';
        $t  = $to ? date('M j, Y', strtotime($to)) : '&hellip;';
        if ($from && $to && $from === $to) {
            $filterChips[] = 'Date: ' . $fr;
        } else {
            $filterChips[] = 'Date: ' . $fr . ' &ndash; ' . $t;
        }
    }
    if ($statusFilter !== 'all') $filterChips[] = 'Status: ' . $statusLabels[$statusFilter];
    if ($riskFilter !== 'all')   $filterChips[] = 'Risk: ' . $riskLabels[$riskFilter];
    if ($barangayFilter > 0) {
        $brgyName = '';
        foreach ($barangayList as $brgy) { if ((int)$brgy['id'] === $barangayFilter) { $brgyName = $brgy['name']; break; } }
        $filterChips[] = 'Barangay: ' . $brgyName;
    }
    if (!empty($filterChips)): ?>
    <div class="filter-summary-bar">
        <span class="fs-label"><i class="fas fa-filter" style="margin-right:4px;"></i>Active Filters:</span>
        <?php foreach ($filterChips as $chip): ?>
        <span class="fs-chip"><?php echo $chip; ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="report">
        <header class="report-header">
            <div class="logo-box">
                <?php if ($lguLogo): ?><img src="<?php echo htmlspecialchars($lguLogo); ?>" alt="LGU Logo"><?php else: ?><div class="logo-placeholder">LGU<br>Logo</div><?php endif; ?>
            </div>
            <div class="org-block">
                <div class="org-line1"><?php echo $headerLine1; ?></div>
                <div class="org-name"><?php echo $headerLine2; ?></div>
                <div class="org-muni"><?php echo $headerLine3; ?></div>
                <div class="org-muni"><?php echo $headerLine4; ?></div>
            </div>
            <div class="logo-box">
                <?php if ($menroLogo): ?><img src="<?php echo htmlspecialchars($menroLogo); ?>" alt="<?php echo htmlspecialchars($pdfCfg['right_logo_alt']); ?> Logo"><?php else: ?><div class="logo-placeholder"><?php echo htmlspecialchars($pdfCfg['right_logo_alt']); ?><br>Logo</div><?php endif; ?>
            </div>
        </header>

        <div class="report-title-block">
            <div class="report-title"><?php echo htmlspecialchars($analyticsTitle); ?></div>
            <div class="report-meta">
                <span><strong>Date Range:</strong> <?php echo htmlspecialchars($rangeText); ?></span>
                <span><strong>Generated On:</strong> <?php echo htmlspecialchars($generatedOn); ?></span>
            </div>
        </div>

        <?php if (in_array('kpi', $sections, true)): ?>
        <div class="section">
            <div class="section-heading"><?php echo htmlspecialchars($sectionLabels['kpi']); ?></div>
            <div class="kpi-row">
                <div class="kpi-card"><div class="kpi-label">Active Hotspots</div><div class="kpi-value"><?php echo $activeHotspots; ?></div></div>
                <div class="kpi-card"><div class="kpi-label">Avg Municipal Risk</div><div class="kpi-value"><?php echo $avgRisk; ?></div></div>
                <div class="kpi-card"><div class="kpi-label">Critical Escalations</div><div class="kpi-value"><?php echo $criticalCount; ?></div></div>
                <div class="kpi-card"><div class="kpi-label">Resolved Hotspots</div><div class="kpi-value"><?php echo $resolvedHotspots; ?></div></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (in_array('map', $sections, true)): ?>
        <div class="section">
            <div class="section-heading"><?php echo htmlspecialchars($sectionLabels['map']); ?></div>
            <?php if (empty($activeReports)): ?>
                <div class="empty-note">No active hazards match the selected filters.</div>
            <?php else: ?>
            <table class="data">
                <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Barangay</th><th>Severity</th><th>Risk</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($activeReports as $r): ?>
                    <tr>
                        <td class="mono">#<?php echo str_pad($r['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($r['title']); ?></td>
                        <td><?php echo htmlspecialchars($r['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($r['barangay_name']); ?></td>
                        <td><?php echo $r['severity_score'] ?? 0; ?></td>
                        <td><span class="badge badge-<?php echo $r['risk_level']; ?>"><?php echo $riskLabels[$r['risk_level']] ?? ucfirst($r['risk_level']); ?></span></td>
                        <td><?php echo htmlspecialchars($statusLabels[$r['status']] ?? $r['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (in_array('severity', $sections, true) || in_array('seasonal', $sections, true)): ?>
        <div class="charts-row">
            <?php if (in_array('severity', $sections, true)): ?>
            <div class="chart-card">
                <div class="chart-heading"><i class="fas fa-chart-pie" style="color:#10A37F; margin-right:6px;"></i><?php echo htmlspecialchars($sectionLabels['severity']); ?></div>
                <div class="chart-wrap"><canvas id="severityChart"></canvas></div>
            </div>
            <?php endif; ?>
            <?php if (in_array('seasonal', $sections, true)): ?>
            <div class="chart-card">
                <div class="chart-heading"><i class="fas fa-chart-line" style="color:#10A37F; margin-right:6px;"></i><?php echo htmlspecialchars($sectionLabels['seasonal']); ?></div>
                <div class="chart-wrap"><canvas id="seasonalChart"></canvas></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (in_array('leaderboard', $sections, true)): ?>
        <div class="section">
            <div class="section-heading"><?php echo htmlspecialchars($sectionLabels['leaderboard']); ?></div>
            <?php if (empty($barangayLeaderboard)): ?>
                <div class="empty-note">No barangay data available.</div>
            <?php else: ?>
            <table class="data">
                <thead><tr><th>Rank</th><th>Barangay</th><th>Assigned</th><th>Resolved</th><th>Resolution Rate</th></tr></thead>
                <tbody>
                    <?php foreach ($barangayLeaderboard as $i => $b): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($b['barangay_name']); ?></td>
                        <td><?php echo $b['total_assigned']; ?></td>
                        <td><?php echo $b['total_resolved']; ?></td>
                        <td><?php echo $b['resolution_rate']; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (in_array('response', $sections, true)): ?>
        <div class="section">
            <div class="section-heading"><?php echo htmlspecialchars($sectionLabels['response']); ?></div>
            <table class="data">
                <tbody>
                    <tr><td>All-Time Average</td><td><?php echo $avgResolutionDaysAllTime; ?> days</td></tr>
                    <tr><td>This Month</td><td><?php echo $avgResolutionDaysThisMonth; ?> days</td></tr>
                    <tr><td>Last Month</td><td><?php echo $avgResolutionDaysLastMonth; ?> days</td></tr>
                    <tr><td>Trend</td><td><?php echo ucfirst($resolutionTrend); ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (in_array('demographics', $sections, true)): ?>
        <div class="section">
            <div class="section-heading"><?php echo htmlspecialchars($sectionLabels['demographics']); ?></div>
            <?php if (!$demographicsAvailable || $demographicsTotal === 0): ?>
                <div class="empty-note">Demographic data not available.</div>
            <?php else: ?>
            <div class="charts-row" style="grid-template-columns: 1fr 1fr;">
                <div class="chart-card">
                    <div class="chart-wrap"><canvas id="demographicsChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <table class="data">
                        <tbody>
                            <tr><td>Resident</td><td><?php echo $demographics['resident']; ?> (<?php echo $residentPct; ?>%)</td></tr>
                            <tr><td>Non-Resident</td><td><?php echo $demographics['non_resident']; ?> (<?php echo $nonResidentPct; ?>%)</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (in_array('peak', $sections, true)): ?>
        <div class="section">
            <div class="section-heading"><?php echo htmlspecialchars($sectionLabels['peak']); ?></div>
            <div class="chart-card">
                <div class="chart-wrap"><canvas id="peakChart"></canvas></div>
            </div>
            <table class="data">
                <tbody>
                    <tr><td>Peak Day</td><td><?php echo htmlspecialchars($peakDayLabel); ?></td></tr>
                    <tr><td>Peak Time</td><td><?php echo htmlspecialchars($peakTimeLabel); ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (in_array('repeat', $sections, true)): ?>
        <div class="section">
            <div class="section-heading"><?php echo htmlspecialchars($sectionLabels['repeat']); ?></div>
            <?php if (empty($repeatOffenders)): ?>
                <div class="empty-note">No repeat-offender locations identified.</div>
            <?php else: ?>
            <table class="data">
                <thead><tr><th>Rank</th><th>Sample Title</th><th>Categories</th><th>Barangay</th><th>Incidents</th></tr></thead>
                <tbody>
                    <?php foreach ($repeatOffenders as $i => $spot): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($spot['sample_title'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($spot['category_names'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($spot['barangay_name'] ?? ''); ?></td>
                        <td><?php echo (int)($spot['incident_count'] ?? 0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="signature-block">
            <div>
                <div class="sig-label">Prepared by:</div>
                <div class="sig-line"></div>
                <div class="sig-name"><?php echo htmlspecialchars($preparedBy ?: $generatedBy); ?></div>
                <div class="sig-title"><?php echo htmlspecialchars($preparedTitle); ?></div>
            </div>
            <div>
                <div class="sig-label">Noted and Approved by:</div>
                <div class="sig-line"></div>
                <div class="sig-name"><?php echo htmlspecialchars($approvedBy ?: '____________________'); ?></div>
                <div class="sig-title"><?php echo htmlspecialchars($approvedTitle); ?></div>
            </div>
        </div>

        <footer class="report-footer">
            <span>Date Printed: <?php echo date('F j, Y'); ?></span>
            <span>Time Printed: <?php echo date('h:i A'); ?></span>
            <span class="brand"><?php echo htmlspecialchars($systemName); ?> &middot; Web-Based Environmental Reporting System</span>
        </footer>
        <div class="report-footer-note"><?php echo htmlspecialchars($footerNote); ?></div>
            </div>
        </div>
    </div>

    <script>
        // ---- Section selection (sidebar checkboxes) ----
        const sectionAll = document.getElementById('sectionAll');
        const sectionCbs = document.querySelectorAll('.section-cb');
        function syncSelectAll() {
            if (!sectionAll) return;
            sectionAll.checked = Array.from(sectionCbs).every(cb => cb.checked);
        }
        if (sectionAll) {
            sectionAll.addEventListener('change', function() {
                sectionCbs.forEach(cb => { cb.checked = this.checked; });
            });
            sectionCbs.forEach(cb => cb.addEventListener('change', syncSelectAll));
        }
        window.validateSections = function() {
            const anyChecked = Array.from(sectionCbs).some(cb => cb.checked);
            if (!anyChecked) {
                window.GB.alert({ type: 'error', title: 'Nothing selected', message: 'Please select at least one analytics section to include.' });
                return false;
            }
            return true;
        };

        const severityData = <?php echo json_encode(array_values($severityTiers)); ?>;
        const severityLabels = <?php echo json_encode(['Low', 'Medium', 'High', 'Critical']); ?>;
        const monthlyLabels = <?php echo json_encode($monthLabels); ?>;
        const monthlyData = <?php echo json_encode($seriesData); ?>;
        const demographicsData = <?php echo json_encode([$demographics['resident'], $demographics['non_resident']]); ?>;
        const dayLabels = <?php echo json_encode($dayLabels); ?>;
        const dayCounts = <?php echo json_encode($dayCounts); ?>;

        <?php if (in_array('severity', $sections, true)): ?>
        new Chart(document.getElementById('severityChart'), {
            type: 'doughnut',
            data: { labels: severityLabels, datasets: [{ data: severityData, backgroundColor: ['#10B981', '#F59E0B', '#F97316', '#EF4444'], borderWidth: 2, borderColor: '#ffffff' }] },
            options: { cutout: '62%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } }, responsive: true, maintainAspectRatio: false }
        });
        <?php endif; ?>

        <?php if (in_array('seasonal', $sections, true)): ?>
        new Chart(document.getElementById('seasonalChart'), {
            type: 'line',
            data: { labels: monthlyLabels, datasets: [{ label: 'Reports', data: monthlyData, borderColor: '#10A37F', backgroundColor: 'rgba(16,163,127,0.12)', tension: 0.3, fill: true, pointBackgroundColor: '#10A37F' }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#e5e7eb' }, ticks: { precision: 0, font: { size: 10 } } }, x: { grid: { display: false }, ticks: { font: { size: 10 } } } }, responsive: true, maintainAspectRatio: false }
        });
        <?php endif; ?>

        <?php if (in_array('demographics', $sections, true) && $demographicsAvailable && $demographicsTotal > 0): ?>
        new Chart(document.getElementById('demographicsChart'), {
            type: 'doughnut',
            data: { labels: ['Resident', 'Non-Resident'], datasets: [{ data: demographicsData, backgroundColor: ['#10A37F', '#F59E0B'], borderWidth: 2, borderColor: '#ffffff' }] },
            options: { cutout: '62%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } }, responsive: true, maintainAspectRatio: false }
        });
        <?php endif; ?>

        <?php if (in_array('peak', $sections, true)): ?>
        new Chart(document.getElementById('peakChart'), {
            type: 'bar',
            data: { labels: dayLabels, datasets: [{ label: 'Reports by Day', data: dayCounts, backgroundColor: '#10A37F', borderRadius: 4, maxBarThickness: 32 }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#e5e7eb' }, ticks: { font: { size: 10 }, precision: 0 } }, x: { grid: { display: false }, ticks: { font: { size: 10 } } } }, responsive: true, maintainAspectRatio: false }
        });
        <?php endif; ?>

        <?php if ($autoprint): ?>
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 700);
        });
        <?php endif; ?>

        window.setQuickReportRange = function(range) {
            var f = document.getElementById('sideFrom');
            var t = document.getElementById('sideTo');
            if (!f || !t) return;
            var r = document.getElementById('sideDateRange');
            if (r) r.value = range === 'all' ? 'all' : 'custom';
            var today = new Date();
            var ymd = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
            var from = today, to = today;
            if (range === 'today') { from = today; }
            else if (range === 'week') { from = new Date(today); from.setDate(today.getDate() - 6); }
            else if (range === 'month') { from = new Date(today.getFullYear(), today.getMonth(), 1); }
            else if (range === 'year') { from = new Date(today.getFullYear(), 0, 1); }
            else if (range === 'all') { f.value = ''; t.value = ''; var form = f.closest('form'); if (form) { if (form.requestSubmit) form.requestSubmit(); else form.submit(); } return; }
            f.value = ymd(from);
            t.value = ymd(to);
            var form = f.closest('form');
            if (form) { if (form.requestSubmit) form.requestSubmit(); else form.submit(); }
        };
        window.setQuickRange = window.setQuickReportRange;
    </script>
    <?php include BASE_PATH . 'views/shared/global_modals.php'; ?>
<script src="<?php echo BASE_URL; ?>assets/js/fetch-timeout.js"></script>
</body>
</html>
