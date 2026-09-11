<?php
// views/admin/dashboard.php - DECISION-SUPPORT DASHBOARD
// Algorithmic KPIs, Heatmap with Clustering, Drill-Down Panel, Trend Charts
// Updated: Export Analytics (CSV/PDF), Enhanced Cluster Drill-Down with Photos

require_once dirname(__DIR__, 2) . '/config/config.php';
requireRole('admin');

$database = new Database();
$db = $database->getConnection();

// ------------------------------------------------------------
// 0. KPI & INSIGHTS TARGETS (configurable in System Settings → KPI & Insights)
// ------------------------------------------------------------
// These feed the Insight Engine so the textual recommendations track the
// targets the MENRO Chief defines in System Settings.
$kpi_resolution_rate_target = (float)SettingsHelper::get('kpi_resolution_rate_target', 60);
$kpi_sla_response_hours     = (float)SettingsHelper::get('kpi_sla_response_hours', 48);
$kpi_surge_alert_threshold  = (float)SettingsHelper::get('kpi_surge_alert_threshold', 25);
$kpi_hotspot_radius_meters  = (float)SettingsHelper::get('kpi_hotspot_radius_meters', 10);
$kpi_critical_reports_pct   = (float)SettingsHelper::get('kpi_critical_reports_pct', 30);
$kpi_demographic_threshold  = (float)SettingsHelper::get('kpi_demographic_threshold', 10);
$kpi_repeat_min_reports     = (float)SettingsHelper::get('kpi_repeat_min_reports', 3);
$kpi_repeat_window_days     = (float)SettingsHelper::get('kpi_repeat_window_days', 30);

// ------------------------------------------------------------
// 0b. ANALYTICS FILTERS — read from GET params (toolbar reloads here)
// ------------------------------------------------------------
$analytics_date_from = isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from']) ? $_GET['date_from'] : null;
$analytics_date_to   = isset($_GET['date_to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])   ? $_GET['date_to']   : null;

$f_know_status = ['pending','under_review','verified','in_progress','escalated_pending','escalated','resolved','rejected','cancelled'];
$f_know_risk   = ['low','medium','high','critical'];
$f_status   = (isset($_GET['status']) && in_array($_GET['status'], $f_know_status, true)) ? $_GET['status'] : 'all';
$f_risk     = (isset($_GET['risk'])   && in_array($_GET['risk'],   $f_know_risk,   true)) ? $_GET['risk']   : 'all';
$f_barangay = isset($_GET['barangay']) ? trim((string)$_GET['barangay']) : '';
$f_search   = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

function _build_af($alias, $date_col, $with_date, $f_status, $f_risk, $f_barangay, $f_search) {
    $clause = '';
    $params = [];
    $p = function ($col) use ($alias) { return $alias === '' ? $col : $alias . '.' . $col; };
    if ($with_date && !empty($GLOBALS['analytics_date_from'])) { $clause .= ' AND ' . $p($date_col) . ' >= ?';           $params[] = $GLOBALS['analytics_date_from'] . ' 00:00:00'; }
    if ($with_date && !empty($GLOBALS['analytics_date_to']))   { $clause .= ' AND ' . $p($date_col) . ' <= ?';           $params[] = $GLOBALS['analytics_date_to']   . ' 23:59:59'; }
    if ($f_status !== 'all')  { $clause .= ' AND ' . $p('status') . ' = ?';                                             $params[] = $f_status; }
    if ($f_risk   !== 'all')  { $clause .= ' AND ' . $p('risk_level') . ' = ?';                                         $params[] = $f_risk; }
    if ($f_barangay !== '')   { $clause .= ' AND ' . $p('barangay_id') . ' IN (SELECT id FROM barangays WHERE name = ?)'; $params[] = $f_barangay; }
    if ($f_search !== '')     { $clause .= ' AND (' . $p('title') . ' LIKE ? OR ' . $p('description') . ' LIKE ?)';     $params[] = "%$f_search%"; $params[] = "%$f_search%"; }
    return [$clause, $params];
}

// created_at-based clauses (default analytics)
list($fragC_plain, $fragC_plain_params) = _build_af('', 'created_at', true, $f_status, $f_risk, $f_barangay, $f_search);
list($fragC_r,     $fragC_r_params)     = _build_af('r', 'created_at', true, $f_status, $f_risk, $f_barangay, $f_search);
// resolved_at-based clauses (resolution metrics)
list($fragR_plain, $fragR_plain_params) = _build_af('', 'resolved_at', true, $f_status, $f_risk, $f_barangay, $f_search);
list($fragR_r,     $fragR_r_params)     = _build_af('r', 'resolved_at', true, $f_status, $f_risk, $f_barangay, $f_search);
// status+risk+barangay+search only (no date) — for inherently time-scoped cards
list($fragSR_plain, $fragSR_plain_params) = _build_af('', 'created_at', false, $f_status, $f_risk, $f_barangay, $f_search);
list($fragSR_r,     $fragSR_r_params)     = _build_af('r', 'created_at', false, $f_status, $f_risk, $f_barangay, $f_search);

// Back-compat alias (used by the $ft config below)
$date_sql_clause = $fragC_r;
$date_sql_params = $fragC_r_params;
$date_sql_simple = $fragC_plain;

// ------------------------------------------------------------
// 1. ALGORITHMIC KPI CALCULATIONS (back-end)
// ------------------------------------------------------------

// Total active hotspots = unique clusters (spatial density > 0) among active reports
// We'll count reports with spatial_density_count > 0 (i.e., overlapping within 50m)
$activeHotspotsStmt = $db->prepare("
    SELECT COUNT(DISTINCT 
        CASE 
            WHEN spatial_density_count > 0 THEN CONCAT(latitude, ',', longitude)
            ELSE id
        END
    ) as count
    FROM reports
    WHERE status NOT IN ('resolved', 'rejected', 'cancelled')
      AND latitude IS NOT NULL AND longitude IS NOT NULL
      {$fragC_plain}
");
$activeHotspotsStmt->execute($fragC_plain_params);
$activeHotspots = $activeHotspotsStmt->fetchColumn();

// Average Municipal Risk Level = average severity_score of active reports
$avgRiskStmt = $db->prepare("
    SELECT AVG(severity_score) as avg_score
    FROM reports
    WHERE status NOT IN ('resolved', 'rejected', 'cancelled')
      AND severity_score IS NOT NULL
      {$fragC_plain}
");
$avgRiskStmt->execute($fragC_plain_params);
$avgRisk = $avgRiskStmt->fetchColumn() ?: 0;
$avgRisk = round($avgRisk, 1);

// Critical Escalations = active reports in the Critical risk band (>= configured threshold)
$criticalCountStmt = $db->prepare("
    SELECT COUNT(*) FROM reports
    WHERE risk_level = 'critical'
      AND status NOT IN ('resolved', 'rejected', 'cancelled')
      {$fragC_plain}
");
$criticalCountStmt->execute($fragC_plain_params);
$criticalCount = $criticalCountStmt->fetchColumn();

// Resolved Hotspots (historical) = clusters that were resolved in the last year
// We count unique locations of resolved reports that had spatial density > 0
$resolvedHotspotsStmt = $db->prepare("
    SELECT COUNT(DISTINCT CONCAT(latitude, ',', longitude)) as count
    FROM reports
    WHERE status = 'resolved'
      AND resolved_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
      AND spatial_density_count > 0
      AND latitude IS NOT NULL AND longitude IS NOT NULL
      {$fragR_plain}
");
$resolvedHotspotsStmt->execute($fragR_plain_params);
$resolvedHotspots = $resolvedHotspotsStmt->fetchColumn();

// ------------------------------------------------------------
// 2. DATA FOR HEATMAP – Active & Historical
// ------------------------------------------------------------

// Active reports (exclude resolved/rejected/cancelled)
$activeReportsStmt = $db->prepare("
        SELECT 
                r.id, r.title, r.description, r.latitude, r.longitude, r.severity_score,
                r.spatial_density_count,
                r.decision_classification,
                r.category_id,
                COALESCE(c.name, '') AS category_name,
                r.location_address,
                b.name as barangay_name,
                r.status,
                r.risk_level,
                r.created_at,
                (SELECT GROUP_CONCAT(image_path) FROM report_images WHERE report_id = r.id LIMIT 3) as image_paths
        FROM reports r
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN barangays b ON r.barangay_id = b.id
        WHERE r.status NOT IN ('resolved', 'rejected', 'cancelled')
            AND r.latitude IS NOT NULL AND r.longitude IS NOT NULL
            AND r.latitude != 0 AND r.longitude != 0
            {$fragC_r}
        ORDER BY r.severity_score DESC
");
$activeReportsStmt->execute($fragC_r_params);
$activeReports = $activeReportsStmt->fetchAll(PDO::FETCH_ASSOC);

// Attach opaque tokens so dashboard drill links never expose raw report IDs
foreach ($activeReports as &$drill_row) {
    $drill_row['token'] = IdGuard::enc((int)$drill_row['id']);
}
unset($drill_row);

// Historical (resolved) reports for the toggle
$historicalReportsStmt = $db->prepare("
        SELECT 
                r.id, r.title, r.description, r.latitude, r.longitude, r.severity_score,
                r.spatial_density_count,
                r.decision_classification,
                r.category_id,
                COALESCE(c.name, '') AS category_name,
                r.location_address,
                b.name as barangay_name,
                r.status,
                r.risk_level,
                r.created_at,
                r.resolved_at,
                (SELECT GROUP_CONCAT(image_path) FROM report_images WHERE report_id = r.id LIMIT 3) as image_paths
        FROM reports r
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN barangays b ON r.barangay_id = b.id
        WHERE r.status = 'resolved'
            AND r.latitude IS NOT NULL AND r.longitude IS NOT NULL
            AND r.latitude != 0 AND r.longitude != 0
            {$fragR_r}
        ORDER BY r.resolved_at DESC
        LIMIT 500
");
$historicalReportsStmt->execute($fragR_r_params);
$historicalReports = $historicalReportsStmt->fetchAll(PDO::FETCH_ASSOC);

// Attach opaque tokens so dashboard drill links never expose raw report IDs
foreach ($historicalReports as &$drill_row) {
    $drill_row['token'] = IdGuard::enc((int)$drill_row['id']);
}
unset($drill_row);

// ------------------------------------------------------------
// 2b. CATEGORIES – for the Category Filter dropdown
// ------------------------------------------------------------
$categories = [];
try {
    $catStmt = $db->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback: derive distinct categories straight from reports if a categories table isn't available
        $catStmt = $db->query("
            SELECT DISTINCT category_id as id, '' as name
            FROM reports
            WHERE category_id IS NOT NULL
            ORDER BY category_id ASC
        ");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ------------------------------------------------------------
// 3. CHART DATA
// ------------------------------------------------------------

// Severity Distribution (tiers) - uses the same configurable bands as the model
$severityBands = getSeverityBands();
$severityTiers = [
    'low'      => ['label' => 'Low (' . 1 . '-' . ($severityBands['yellow'] - 1) . ')', 'count' => 0],
    'medium'   => ['label' => 'Medium (' . $severityBands['yellow'] . '-' . ($severityBands['orange'] - 1) . ')', 'count' => 0],
    'high'     => ['label' => 'High (' . $severityBands['orange'] . '-' . ($severityBands['critical'] - 1) . ')', 'count' => 0],
    'critical' => ['label' => 'Critical (' . $severityBands['critical'] . '-20)', 'count' => 0],
];
$tierQuery = $db->prepare("
    SELECT severity_score FROM reports
    WHERE status NOT IN ('resolved', 'rejected', 'cancelled')
      AND severity_score IS NOT NULL
      {$fragC_plain}
");
$tierQuery->execute($fragC_plain_params);
while ($row = $tierQuery->fetch(PDO::FETCH_ASSOC)) {
    $level = getRiskLevelFromScore($row['severity_score']);
    $severityTiers[$level]['count']++;
}
$severityTotal = array_sum(array_column($severityTiers, 'count'));
$criticalSharePct = $severityTotal > 0 ? round(($severityTiers['critical']['count'] / $severityTotal) * 100, 1) : 0;
$criticalAlert = $severityTotal > 0 && $criticalSharePct > (float)$kpi_critical_reports_pct;

// Seasonal Hazard Analytics — monthly counts per severity level (last 12 months).
// Computed per risk level so the chart can be switched between severities
// (All / Low / Medium / High / Critical) and time windows on the client side.
$seasonalMonths = [];
$seasonalByLevel = ['all' => [], 'low' => [], 'medium' => [], 'high' => [], 'critical' => []];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $seasonalMonths[] = $month;
    foreach ($seasonalByLevel as $k => $v) {
        $seasonalByLevel[$k][$month] = 0;
    }
}
$seasonalStmt = $db->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, risk_level, COUNT(*) AS total
    FROM reports
    WHERE status NOT IN ('resolved', 'rejected', 'cancelled')
      AND DATE_FORMAT(created_at, '%Y-%m') >= ?
      {$fragC_plain}
    GROUP BY ym, risk_level
");
$seasonalParams = array_merge([$seasonalMonths[0]], $fragC_plain_params);
$seasonalStmt->execute($seasonalParams);
$seasonalRows = $seasonalStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($seasonalRows as $row) {
    $ym = $row['ym'];
    $lvl = in_array($row['risk_level'], ['low', 'medium', 'high', 'critical'], true) ? $row['risk_level'] : 'low';
    if (isset($seasonalByLevel['all'][$ym])) {
        $seasonalByLevel['all'][$ym] += (int)$row['total'];
        $seasonalByLevel[$lvl][$ym] += (int)$row['total'];
    }
}
// Keep $months / $seasonalData aliases for the default chart rendering.
$months = array_map(function ($m) { return date('M', strtotime($m)); }, $seasonalMonths);
$seasonalData = array_values($seasonalByLevel['all']);

// Surge Alert: compare the most recent month vs. the previous month per category.
// If any category grew by at least the configured surge threshold (%), flag a
// recommendation to reallocate budget toward that hazard type.
$surgeAlert = null;
$currentMonth = date('Y-m');
$prevMonth = date('Y-m', strtotime('last month'));
try {
    $surgeStmt = $db->prepare("
        SELECT c.name AS category_name,
               SUM(CASE WHEN DATE_FORMAT(r.created_at, '%Y-%m') = '$currentMonth' THEN 1 ELSE 0 END) AS current_count,
               SUM(CASE WHEN DATE_FORMAT(r.created_at, '%Y-%m') = '$prevMonth' THEN 1 ELSE 0 END) AS previous_count
        FROM reports r
        JOIN categories c ON r.category_id = c.id
        WHERE r.severity_score >= {$severityBands['orange']}
          AND r.status NOT IN ('resolved', 'rejected', 'cancelled')
          {$fragC_r}
        GROUP BY c.id, c.name
    ");
    $surgeStmt->execute($fragC_r_params);
    foreach ($surgeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $current_count = (int)$row['current_count'];
        $previous_count = (int)$row['previous_count'];
        if ($previous_count > 0 && $current_count >= $previous_count) {
            $pct = (($current_count - $previous_count) / $previous_count) * 100;
            if ($pct >= $kpi_surge_alert_threshold) {
                $surgeAlert = [
                    'category' => $row['category_name'],
                    'pct' => round($pct, 1),
                    'current' => $current_count,
                    'previous' => $previous_count
                ];
                break;
            }
        }
    }
} catch (Exception $e) {
    $surgeAlert = null;
}

// ------------------------------------------------------------
// 5. BARANGAY PERFORMANCE LEADERBOARD
// ------------------------------------------------------------
// Assumes reports.barangay_id -> barangays.name; falls back to a plain
// text reports.barangay column if no barangays lookup table exists.
$barangayLeaderboard = [];
try {
    $stmt = $db->prepare("
        SELECT b.name AS barangay_name,
               COUNT(*) AS total_assigned,
               SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) AS total_resolved
        FROM reports r
        JOIN barangays b ON b.id = r.barangay_id
        WHERE r.status NOT IN ('rejected', 'cancelled')
          {$fragC_r}
        GROUP BY b.id, b.name
    ");
    $stmt->execute($fragC_r_params);
    $barangayLeaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        $stmt = $db->prepare("
            SELECT barangay AS barangay_name,
                   COUNT(*) AS total_assigned,
                   SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS total_resolved
            FROM reports
            WHERE status NOT IN ('rejected', 'cancelled')
              AND barangay IS NOT NULL AND barangay != ''
              {$fragC_plain}
            GROUP BY barangay
        ");
        $stmt->execute($fragC_plain_params);
        $barangayLeaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $barangayLeaderboard = [];
    }
}
foreach ($barangayLeaderboard as &$brgy) {
    $brgy['total_assigned'] = (int)$brgy['total_assigned'];
    $brgy['total_resolved'] = (int)$brgy['total_resolved'];
    $brgy['resolution_rate'] = $brgy['total_assigned'] > 0
        ? round(($brgy['total_resolved'] / $brgy['total_assigned']) * 100, 1)
        : 0;
}
unset($brgy);
// Rank best-to-worst by resolution rate (ties broken by total resolved)
usort($barangayLeaderboard, function($a, $b) {
    if ($a['resolution_rate'] == $b['resolution_rate']) {
        return $b['total_resolved'] <=> $a['total_resolved'];
    }
    return $b['resolution_rate'] <=> $a['resolution_rate'];
});

// Slowest barangay = highest average resolution time, used by the SLA
// recommendation to point the MENRO Chief at where the delay is concentrated.
$slowestBarangay = null;
try {
    $slowStmt = $db->prepare("
        SELECT b.name AS barangay_name,
               AVG(TIMESTAMPDIFF(HOUR, r.created_at, r.resolved_at)) AS avg_hours
        FROM reports r
        JOIN barangays b ON b.id = r.barangay_id
        WHERE r.status = 'resolved' AND r.resolved_at IS NOT NULL AND r.created_at IS NOT NULL
          {$fragR_r}
        GROUP BY b.id, b.name
        HAVING avg_hours IS NOT NULL
        ORDER BY avg_hours DESC
        LIMIT 1
    ");
    $slowStmt->execute($fragR_r_params);
    $slowRow = $slowStmt->fetch(PDO::FETCH_ASSOC);
    if ($slowRow) {
        $slowestBarangay = $slowRow;
    }
} catch (Exception $e) {
    try {
        $slowStmt = $db->prepare("
            SELECT barangay AS barangay_name,
                   AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
            FROM reports
            WHERE status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL
              AND barangay IS NOT NULL AND barangay != ''
              {$fragR_plain}
            GROUP BY barangay
            HAVING avg_hours IS NOT NULL
            ORDER BY avg_hours DESC
            LIMIT 1
        ");
        $slowStmt->execute($fragR_plain_params);
        $slowRow = $slowStmt->fetch(PDO::FETCH_ASSOC);
        if ($slowRow) {
            $slowestBarangay = $slowRow;
        }
    } catch (Exception $e2) {
        $slowestBarangay = null;
    }
}
if ($slowestBarangay) {
    $slowestBarangay['avg_hours'] = round((float)$slowestBarangay['avg_hours'], 1);
}

// ------------------------------------------------------------
// 6. AVERAGE RESOLUTION TIME (Speed Tracking)
// ------------------------------------------------------------
$avgResAllStmt = $db->prepare("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
    FROM reports
    WHERE status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL
      {$fragR_plain}
");
$avgResAllStmt->execute($fragR_plain_params);
$avgResolutionHoursAllTime = $avgResAllStmt->fetchColumn() ?: 0;
$avgResolutionDaysAllTime = round($avgResolutionHoursAllTime / 24, 1);

$avgResMonthStmt = $db->prepare("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
    FROM reports
    WHERE status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL
      AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
      {$fragR_plain}
");
$avgResMonthStmt->execute($fragR_plain_params);
$avgResolutionHoursThisMonth = $avgResMonthStmt->fetchColumn() ?: 0;
$avgResolutionDaysThisMonth = round($avgResolutionHoursThisMonth / 24, 1);

$avgResLastStmt = $db->prepare("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
    FROM reports
    WHERE status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL
      AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')
      {$fragR_plain}
");
$avgResLastStmt->execute($fragR_plain_params);
$avgResolutionHoursLastMonth = $avgResLastStmt->fetchColumn() ?: 0;
$avgResolutionDaysLastMonth = round($avgResolutionHoursLastMonth / 24, 1);

$resolutionTrend = 'stable';
$resolutionDelta = 0;
if ($avgResolutionDaysLastMonth > 0) {
    $resolutionDelta = round($avgResolutionDaysThisMonth - $avgResolutionDaysLastMonth, 1);
    if ($resolutionDelta > 0.5) $resolutionTrend = 'worse';
    elseif ($resolutionDelta < -0.5) $resolutionTrend = 'better';
}

// ------------------------------------------------------------
// 7. USER DEMOGRAPHICS (Resident vs Non-Resident)
// ------------------------------------------------------------
// Assumes users.residency_status ('resident' / 'non-resident'); falls back to
// a users.is_resident boolean column if that's how the schema is set up.
$demographics = ['resident' => 0, 'non_resident' => 0];
$demographicsAvailable = true;
try {
    $stmt = $db->prepare("
        SELECT u.residency_status AS status_type, COUNT(*) AS total
        FROM reports r
        JOIN users u ON u.id = r.user_id
        WHERE 1=1 {$fragC_r}
        GROUP BY u.residency_status
    ");
    $stmt->execute($fragC_r_params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (strtolower($row['status_type']) === 'resident') ? 'resident' : 'non_resident';
        $demographics[$key] += (int)$row['total'];
    }
} catch (Exception $e) {
    try {
        $stmt = $db->prepare("
            SELECT u.is_resident AS status_type, COUNT(*) AS total
            FROM reports r
            JOIN users u ON u.id = r.user_id
            WHERE 1=1 {$fragC_r}
            GROUP BY u.is_resident
        ");
        $stmt->execute($fragC_r_params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = ((int)$row['status_type'] === 1) ? 'resident' : 'non_resident';
            $demographics[$key] += (int)$row['total'];
        }
    } catch (Exception $e2) {
        $demographicsAvailable = false;
    }
}
$demographicsTotal = $demographics['resident'] + $demographics['non_resident'];
$residentPct = $demographicsTotal > 0 ? round(($demographics['resident'] / $demographicsTotal) * 100, 1) : 0;
$nonResidentPct = $demographicsTotal > 0 ? round(($demographics['non_resident'] / $demographicsTotal) * 100, 1) : 0;

// ------------------------------------------------------------
// 8. PEAK REPORTING HOURS & DAYS (Time Analytics)
// ------------------------------------------------------------
$dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$dayCounts = array_fill(0, 7, 0);
$dayStmt = $db->prepare("
    SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS total
    FROM reports
    WHERE created_at IS NOT NULL
      {$fragC_plain}
    GROUP BY DAYOFWEEK(created_at)
");
$dayStmt->execute($fragC_plain_params);
while ($row = $dayStmt->fetch(PDO::FETCH_ASSOC)) {
    $idx = (int)$row['dow'] - 1; // MySQL DAYOFWEEK: 1 = Sunday
    if ($idx >= 0 && $idx < 7) $dayCounts[$idx] = (int)$row['total'];
}

$timeBuckets = ['Morning (6AM–12PM)' => 0, 'Afternoon (12PM–6PM)' => 0, 'Night (6PM–6AM)' => 0];
$hourStmt = $db->prepare("
    SELECT HOUR(created_at) AS hr, COUNT(*) AS total
    FROM reports
    WHERE created_at IS NOT NULL
      {$fragC_plain}
    GROUP BY HOUR(created_at)
");
$hourStmt->execute($fragC_plain_params);
while ($row = $hourStmt->fetch(PDO::FETCH_ASSOC)) {
    $hr = (int)$row['hr'];
    $total = (int)$row['total'];
    if ($hr >= 6 && $hr < 12) $timeBuckets['Morning (6AM–12PM)'] += $total;
    elseif ($hr >= 12 && $hr < 18) $timeBuckets['Afternoon (12PM–6PM)'] += $total;
    else $timeBuckets['Night (6PM–6AM)'] += $total;
}

$peakDayTotal = max($dayCounts);
$peakDayIndex = array_search($peakDayTotal, $dayCounts);
$peakDayLabel = ($peakDayTotal > 0 && $peakDayIndex !== false) ? $dayLabels[$peakDayIndex] : 'N/A';

$peakTimeTotal = max($timeBuckets);
$peakTimeLabel = ($peakTimeTotal > 0) ? array_search($peakTimeTotal, $timeBuckets) : 'N/A';

// Plain-language variants of the peak window for staff-facing recommendations.
$peakDayPlain = ($peakDayTotal > 0 && $peakDayIndex !== false)
    ? ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$peakDayIndex]
    : 'N/A';
if (strpos($peakTimeLabel, 'Night') !== false) {
    $peakTimePlain = 'during the evening and overnight hours';
} elseif (strpos($peakTimeLabel, 'Morning') !== false) {
    $peakTimePlain = 'during the morning hours';
} else {
    $peakTimePlain = 'during the afternoon hours';
}

$dayGrandTotal = array_sum($dayCounts);
$peakDayShare = $dayGrandTotal > 0 ? round(($peakDayTotal / $dayGrandTotal) * 100, 1) : 0;

// ------------------------------------------------------------
// 9. TOP 5 "REPEAT OFFENDER" LOCATIONS
// ------------------------------------------------------------
// Behavioral hazards (illegal dumping, vandalism, littering, etc.) grouped
// into grid cells sized by the configured Hotspot Definition Radius
// (System Settings → KPI & Insights) to find chronic enforcement hotspots.
// ~0.0009 deg ≈ 100m latitude; scale linearly with the configured radius.
$repeatOffenders = [];
$hotspot_grid_deg = max(0.000001, ((float)$kpi_hotspot_radius_meters / 100.0) * 0.0009);
$repeat_window_sql = max(1, (int)$kpi_repeat_window_days);
$repeat_min_sql = max(1, (int)$kpi_repeat_min_reports);
try {
    $stmt = $db->prepare("
        SELECT 
            FLOOR(r.latitude / {$hotspot_grid_deg}) AS grid_lat_key,
            FLOOR(r.longitude / {$hotspot_grid_deg}) AS grid_lng_key,
            COUNT(*) AS incident_count,
            AVG(r.latitude) AS avg_lat,
            AVG(r.longitude) AS avg_lng,
            MAX(r.title) AS sample_title,
            MAX(r.barangay) AS barangay_name,
            GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') AS category_names
        FROM reports r
        JOIN categories c ON c.id = r.category_id
        WHERE r.status = 'resolved'
          AND (c.name LIKE '%Dump%' OR c.name LIKE '%Vandal%' OR c.name LIKE '%Litter%' OR c.name LIKE '%Illegal%')
          AND r.latitude IS NOT NULL AND r.longitude IS NOT NULL
          AND r.latitude != 0 AND r.longitude != 0
          AND r.created_at >= DATE_SUB(NOW(), INTERVAL {$repeat_window_sql} DAY)
          {$fragC_r}
        GROUP BY grid_lat_key, grid_lng_key
        HAVING incident_count > {$repeat_min_sql}
        ORDER BY incident_count DESC
        LIMIT 5
    ");
    $stmt->execute($fragC_r_params);
    $repeatOffenders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        // Fallback for schemas without a text `barangay` column: derive the
        // barangay name via the barangays lookup table on barangay_id.
        $stmt = $db->prepare("
            SELECT 
                FLOOR(r.latitude / {$hotspot_grid_deg}) AS grid_lat_key,
                FLOOR(r.longitude / {$hotspot_grid_deg}) AS grid_lng_key,
                COUNT(*) AS incident_count,
                AVG(r.latitude) AS avg_lat,
                AVG(r.longitude) AS avg_lng,
                MAX(r.title) AS sample_title,
                MAX(b.name) AS barangay_name,
                GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') AS category_names
            FROM reports r
            JOIN categories c ON c.id = r.category_id
            LEFT JOIN barangays b ON b.id = r.barangay_id
            WHERE r.status = 'resolved'
              AND (c.name LIKE '%Dump%' OR c.name LIKE '%Vandal%' OR c.name LIKE '%Litter%' OR c.name LIKE '%Illegal%')
              AND r.latitude IS NOT NULL AND r.longitude IS NOT NULL
              AND r.latitude != 0 AND r.longitude != 0
              AND r.created_at >= DATE_SUB(NOW(), INTERVAL {$repeat_window_sql} DAY)
              {$fragC_r}
            GROUP BY grid_lat_key, grid_lng_key
            HAVING incident_count > {$repeat_min_sql}
            ORDER BY incident_count DESC
            LIMIT 5
        ");
        $stmt->execute($fragC_r_params);
        $repeatOffenders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $repeatOffenders = [];
    }
}

// ------------------------------------------------------------
// 4. HELPER FUNCTIONS
// ------------------------------------------------------------

function getSeverityColor($score) {
    return getRiskColor(getRiskLevelFromScore($score));
}

function getSeverityTier($score) {
    return getRiskLevelLabel(getRiskLevelFromScore($score));
}

function getRecommendation($score) {
    $level = getRiskLevelFromScore($score);
    $recs = [
        'low'      => 'Keep as is. Barangay can handle this with regular cleanup.',
        'medium'   => 'Barangay should act soon. MENRO should keep an eye on this.',
        'high'     => 'Send to MENRO. Clear the hazard now before it spreads or causes flooding.',
        'critical' => 'Act now. Send MENRO crews and equipment to this location right away.',
    ];
    return 'Recommendation: ' . $recs[$level];
}

// Load San Isidro boundary GeoJSON for map
$geojson_file = BASE_PATH . 'geojson/sanisidro.geojson';
$boundary_data = null;
if (file_exists($geojson_file)) {
    $boundary_data = json_decode(file_get_contents($geojson_file), true);
}

// Load every barangay boundary GeoJSON and merge them into a single
// FeatureCollection so the map can draw one polygon per barangay.
$barangays_dir = BASE_PATH . 'geojson/barangay';
$barangay_data = null;
if (is_dir($barangays_dir)) {
    $barangay_features = [];
    foreach (glob($barangays_dir . '/*.geojson') as $barangay_file) {
        $barangay_base = basename($barangay_file);
        // Skip the combined "all barangays" file and any *_with_reports outputs
        if ($barangay_base === 'san-isidro.barangay.geojson' || strpos($barangay_base, '_with_reports') !== false) {
            continue;
        }
        $barangay_decoded = json_decode(file_get_contents($barangay_file), true);
        if (!is_array($barangay_decoded) || ($barangay_decoded['type'] ?? '') !== 'FeatureCollection') {
            continue;
        }
        foreach (($barangay_decoded['features'] ?? []) as $barangay_feature) {
            if (!is_array($barangay_feature) || !isset($barangay_feature['geometry'])) {
                continue;
            }
            $barangay_gtype = $barangay_feature['geometry']['type'] ?? '';
            if ($barangay_gtype !== 'Polygon' && $barangay_gtype !== 'MultiPolygon') {
                continue;
            }
            // Give every boundary a friendly name so it can be labelled / filtered on the map
            $barangay_name = $barangay_feature['properties']['barangay_name'] ?? $barangay_feature['properties']['name'] ?? '';
            if ($barangay_name === '') {
                $barangay_name = ucwords(str_replace('-', ' ', pathinfo($barangay_base, PATHINFO_FILENAME)));
            }
            $barangay_feature['properties']['name'] = $barangay_name;
            $barangay_features[] = $barangay_feature;
        }
    }
    if (count($barangay_features) > 0) {
        $barangay_data = ['type' => 'FeatureCollection', 'features' => $barangay_features];
    }
}

// Helper for decision badge (used in drill-down)
function getDecisionBadge($classification) {
    $badges = [
        'Isolated Incident' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200'],
        'Isolated Emergency' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'border' => 'border-red-200'],
        'Moderate Recurrence' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'border' => 'border-orange-200'],
        'Critical Chronic Hotspot' => ['bg' => 'bg-red-200', 'text' => 'text-red-800', 'border' => 'border-red-300'],
        'Emerging Pattern' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'border' => 'border-yellow-200'],
        'Under Review' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'border' => 'border-gray-200']
    ];
    return $badges[$classification] ?? $badges['Under Review'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <title>MENRO Analytics Dashboard - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/map-layers.js"></script>
    <!-- Leaflet.markercluster for clustering -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: #F5FBF6; overflow-x: hidden; }

        @media (max-width: 768px) {
            .ml-72 { margin-left: 0 !important; width: 100%; padding: 0; }
        }

        .main-container { max-width: 1400px; margin: 0 auto; padding: 1rem; }
        @media (min-width: 640px) { .main-container { padding: 1.5rem; } }
        @media (min-width: 768px) { .main-container { padding: 2rem; } }

        /* KPI Cards */
        .kpi-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(16, 163, 127, 0.08);
            padding: 1.25rem 1rem;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }
        .kpi-card:hover {
            transform: translateY(-4px);
            border-color: #10A37F;
            box-shadow: 0 12px 28px -8px rgba(16, 163, 127, 0.15);
        }
        .kpi-card .kpi-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #8aa38a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.2rem;
        }
        .kpi-card .kpi-value {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .kpi-card .kpi-sub {
            font-size: 0.7rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        .kpi-card .kpi-icon {
            position: absolute;
            right: 1rem;
            top: 1rem;
            font-size: 1.5rem;
            opacity: 0.2;
        }
        .kpi-critical { border-left: 4px solid #EF4444; }
        .kpi-resolved { border-left: 4px solid #10B981; }
        .kpi-hotspot { border-left: 4px solid #F59E0B; }
        .kpi-risk { border-left: 4px solid #3B82F6; }

        /* KPI widget grid - always horizontal (4-across), compacted on mobile */
        .analytics-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .analytics-kpi-box {
            min-width: 0;
            width: 100%;
            padding: 1.25rem;
        }
        @media (max-width: 560px) {
            .analytics-kpi-grid { gap: 0.5rem; margin-bottom: 1rem; }
            .analytics-kpi-box { padding: 0.5rem; }
            .analytics-kpi-box .w-10 { display: none; }
            .analytics-kpi-box .uppercase { font-size: 0.58rem; letter-spacing: 0.03em; }
            .analytics-kpi-box .text-2xl { font-size: 1.05rem; }
            .analytics-kpi-box .text-base { font-size: 0.85rem; }
            .analytics-kpi-box [class~="mt-1"], .analytics-kpi-box [class~="mt-1.5"] { font-size: 0.55rem; }
        }

        /* Map container */
        #map-container {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid rgba(16, 163, 127, 0.08);
            padding: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }
        #map {
            height: 500px;
            width: 100%;
            border-radius: 0.75rem;
            z-index: 1;
        }
        @media (max-width: 768px) { #map { height: 350px; } }

        /* Severity hotspot pin with category label (shown when zoomed in) */
        .sev-marker-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            line-height: 1;
        }
        .sev-marker-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.35);
        }
        .sev-marker-label {
            font-family: 'Manrope', sans-serif;
            font-size: 10px;
            font-weight: 700;
            color: #0F3B2E;
            background: rgba(255,255,255,0.92);
            padding: 2px 6px;
            border-radius: 6px;
            white-space: nowrap;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
            border: 1px solid rgba(16,163,127,0.25);
        }

        /* Map toggle */
        .map-toggle {
            display: flex;
            background: #f1f5f9;
            border-radius: 2rem;
            padding: 0.2rem;
            gap: 0.2rem;
        }
        .map-toggle button {
            padding: 0.4rem 1.2rem;
            border-radius: 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            background: transparent;
            color: #64748b;
            transition: all 0.2s;
        }
        .map-toggle button.active {
            background: white;
            color: #10A37F;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .map-toggle button:hover:not(.active) { color: #10A37F; }

        /* ===== ENHANCED DRILL-DOWN PANEL ===== */
        #drillPanel {
            position: fixed;
            top: 0;
            right: -520px;
            width: 520px;
            height: 100%;
            background: white;
            box-shadow: -4px 0 24px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: right 0.3s ease;
            overflow-y: auto;
            padding: 0;
        }
        #drillPanel.open { right: 0; }
        #drillPanel .close-btn {
            position: sticky;
            top: 0;
            float: right;
            margin: 1rem 1rem 0 0;
            background: #f1f5f9;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
            z-index: 10;
        }
        #drillPanel .close-btn:hover { background: #e2e8f0; }
        #drillPanel .drill-body { padding: 1rem 1.5rem 1.5rem; }

        .drill-photo-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin-top: 8px;
        }
        .drill-photo-grid img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 0.5rem;
            cursor: pointer;
            border: 1px solid #e5e7eb;
            transition: transform 0.2s;
        }
        .drill-photo-grid video {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 0.5rem;
            cursor: pointer;
            border: 1px solid #e5e7eb;
            transition: transform 0.2s;
            background: #111827;
        }
        .drill-photo-grid img:hover,
        .drill-photo-grid video:hover { transform: scale(1.02); }
        .drill-photo-grid .no-photo {
            grid-column: 1 / -1;
            text-align: center;
            color: #9ca3af;
            font-size: 0.8rem;
            padding: 1.5rem 0;
            background: #f9fafb;
            border-radius: 0.5rem;
        }

        .drill-score-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
        }
        .drill-score-row .label { color: #64748b; }
        .drill-score-row .value { font-weight: 600; }

        .drill-rec-box {
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-top: 0.75rem;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .drill-rec-low { background: #D1FAE5; color: #065F46; border-left: 4px solid #10B981; }
        .drill-rec-medium { background: #FEF3C7; color: #92400E; border-left: 4px solid #F59E0B; }
        .drill-rec-high { background: #FFEDD5; color: #9A3412; border-left: 4px solid #F97316; }
        .drill-rec-critical { background: #FEE2E2; color: #991B1B; border-left: 4px solid #EF4444; }

        .drill-open-btn {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.5rem 1.25rem;
            background: #10A37F;
            color: white;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .drill-open-btn:hover {
            background: #0D8568;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16,163,127,0.25);
        }

        /* Score breakdown */
        .score-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
        }
        .score-row .label { color: #64748b; }
        .score-row .value { font-weight: 600; }

        /* Recommendation box */
        .rec-box {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-top: 1rem;
            font-weight: 500;
        }
        .rec-low { background: #D1FAE5; color: #065F46; border-left: 4px solid #10B981; }
        .rec-medium { background: #FEF3C7; color: #92400E; border-left: 4px solid #F59E0B; }
        .rec-critical { background: #FEE2E2; color: #991B1B; border-left: 4px solid #EF4444; }

        /* Chart header + info ("i") recommendation toggle */
        .chart-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .rec-info-btn {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            background: #E8F5F0;
            color: #10A37F;
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }
        .rec-info-btn:hover { background: #10A37F; color: #fff; }
        .rec-info-btn.active { background: #10A37F; color: #fff; }
        .rec-stack {
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transform: translateY(-6px);
            transition: max-height 0.45s ease, opacity 0.4s ease, transform 0.4s ease;
        }
        .rec-stack.open {
            max-height: 700px;
            opacity: 1;
            transform: translateY(0);
        }

        /* Chart containers */
        .chart-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(16, 163, 127, 0.08);
            padding: 1.25rem;
        }
        .chart-card .chart-title {
            font-weight: 700;
            font-size: 0.9rem;
            color: #1f2937;
            margin-bottom: 0.75rem;
        }
        .chart-container {
            height: 220px;
            position: relative;
        }

        /* ===== EXPORT DROPDOWN (uses shared export-print.css) ===== */

        /* PDF/CSV overlay */
        .export-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .export-overlay-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            max-width: 320px;
            width: 90%;
        }
        .export-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e5e7eb;
            border-top-color: #10A37F;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Map card layout */
        .map-head {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.85rem 1.25rem;
            margin-bottom: 0.9rem;
        }
        .map-title-wrap {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            flex-wrap: wrap;
            min-width: 0;
        }
        .map-head-tools {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 0.85rem 1rem;
        }

        /* Floating legend + category filter overlay on the map canvas */
        .map-overlay {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.6rem;
            pointer-events: none;
        }
        .map-overlay > * { pointer-events: auto; }
        .map-legend {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 0.45rem 0.8rem;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
            font-size: 0.72rem;
            color: #4b5563;
        }
        /* Legend shown under the map on phones so it never covers the canvas */
        .map-legend-inline {
            display: none;
            justify-content: center;
            width: 100%;
        }

        /* Responsive tweaks */
        @media (max-width: 768px) {
            #drillPanel { width: 100%; right: -100%; }
            .kpi-card .kpi-value { font-size: 1.5rem; }
            .map-toggle button { padding: 0.3rem 0.8rem; font-size: 0.7rem; }
            .drill-photo-grid { grid-template-columns: repeat(2, 1fr); }
            .drill-photo-grid img,
            .drill-photo-grid video { height: 80px; }
            /* Map card compacts for tablets/phones */
            #map-container { padding: 0.85rem; }
            .map-title-wrap { width: 100%; justify-content: space-between; }
            #mapToggle { flex-wrap: nowrap; }
            #mapToggle button { flex: 1; padding: 0.35rem 0.5rem; font-size: 0.72rem; }
            .map-legend { gap: 0.45rem 0.85rem; padding: 0.35rem 0.65rem; font-size: 0.68rem; }
            /* Header tools wrap to their own line on mobile */
            .map-head-tools { width: 100%; justify-content: space-between; }
            /* Timeframe pills become a swipeable strip instead of wrapping */
            #timeframeToggle {
                display: flex;
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                max-width: 100%;
            }
            #timeframeToggle::-webkit-scrollbar { display: none; }
            #timeframeToggle button { flex-shrink: 0; white-space: nowrap; }
            #customRangeBox { width: 100%; flex-wrap: wrap; }
            #customRangeBox input { flex: 1 1 40%; min-width: 0; }
            /* Floating overlay stays compact */
            .map-overlay { top: 0.5rem; right: 0.5rem; }
            #categoryFilterWrap { max-width: 170px; }
            #categoryFilterBtn { padding: 0.35rem 0.75rem; font-size: 0.75rem; }
            #categoryFilterMenu { width: 230px; right: 0; left: auto; }
            /* Keep the map canvas unobstructed: legend moves below the map */
            .map-overlay .map-legend { display: none; }
            .map-legend-inline { display: flex; margin-top: 0.6rem; }
        }
        @media (max-width: 480px) {
            #map { height: 300px; }
            .map-title-wrap h2 { font-size: 1.05rem; }
            .map-head { gap: 0.7rem; }
            .map-legend span { font-size: 0.7rem; }
            #customRangeBox input { flex: 1 1 100%; }
        }
        .risk-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .risk-low { background: #D1FAE5; color: #065F46; }
        .risk-medium { background: #FEF3C7; color: #92400E; }
        .risk-high { background: #FFEDD5; color: #9A3412; }
        .risk-critical { background: #FEE2E2; color: #991B1B; }
        .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .status-pending { background: #FEF3C7; color: #D97706; }
        .status-under_review { background: #DBEAFE; color: #1E40AF; }
        .status-verified { background: #DBEAFE; color: #1E40AF; }
        .status-in_progress { background: #FCE7F3; color: #DB2777; }
        .status-escalated_pending { background: #FDE68A; color: #92400E; border: 1px solid #F59E0B; }
        .status-escalated { background: #FED7AA; color: #9A3412; }
        .status-resolved { background: #D1FAE5; color: #10A37F; }
        .status-rejected { background: #FEE2E2; color: #DC2626; }
        .status-cancelled { background: #F3F4F6; color: #4B5563; }
    </style>
</head>
<body>

<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<div class="lg:ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">

        <!-- Header with Export -->
        <div class="mb-4 md:mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-7 h-7 md:w-8 md:h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-pie text-[#10A37F] text-sm"></i>
                    </div>
                    <span class="text-[10px] md:text-xs uppercase tracking-wider text-[#10A37F] font-semibold">Analytics Dashboard</span>
                </div>
                <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800">MENRO Analytics Dashboard</h1>
                <p class="text-gray-500 text-xs sm:text-sm">Real-time algorithm-driven hazard intelligence for San Isidro</p>
            </div>
            <div class="flex items-center gap-3 mt-2 sm:mt-0">
                <div class="flex items-center gap-2 text-sm text-gray-600 bg-white border border-gray-200 rounded-lg px-3 py-2">
                    <i class="far fa-calendar-alt text-[#10A37F]"></i>
                    <span class="font-semibold text-gray-500">Date:</span>
                    <span class="font-semibold text-gray-800"><?php echo date('F d, Y'); ?></span>
                </div>
                <!-- Export Analytics -->
                <div class="export-dropdown" id="exportDropdownWrap">
                    <button onclick="toggleExportDropdown()" id="exportDropBtn" class="btn-export-trigger">
                        <i class="fas fa-file-export"></i>
                        <span>Export</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div id="exportDropdown" class="export-dropdown-menu" style="width:280px;">
                        <div class="export-dropdown-header">
                            <p>Export Analytics</p>
                            <p class="sub">Download the current analytics</p>
                        </div>
                        <button class="export-dropdown-item" onclick="exportAnalyticsPdf()">
                            <div class="item-icon" style="background:#E8F5F0; color:#10A37F;"><i class="fas fa-file-pdf"></i></div>
                            <div class="item-text">
                                <div class="item-title">Export as PDF</div>
                                <div class="item-desc">Preview and save as PDF</div>
                            </div>
                        </button>
                        <button class="export-dropdown-item" onclick="exportAnalyticsCsv()">
                            <div class="item-icon" style="background:#DBEAFE; color:#2563EB;"><i class="fas fa-file-csv"></i></div>
                            <div class="item-text">
                                <div class="item-title">Export as CSV</div>
                                <div class="item-desc">Download spreadsheet of analytics</div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Analytics Date Filter Banner -->
        <?php if ($analytics_date_from || $analytics_date_to): ?>
        <div class="mb-4 flex items-center gap-3 bg-[#10A37F]/8 border border-[#10A37F]/25 rounded-xl px-4 py-2.5 text-sm text-[#0D8568] font-medium">
            <i class="fas fa-calendar-check text-[#10A37F]"></i>
            <span>Analytics filtered:
                <?php if ($analytics_date_from && $analytics_date_to): ?>
                    <strong><?php echo htmlspecialchars($analytics_date_from); ?></strong> to <strong><?php echo htmlspecialchars($analytics_date_to); ?></strong>
                <?php elseif ($analytics_date_from): ?>
                    from <strong><?php echo htmlspecialchars($analytics_date_from); ?></strong>
                <?php else: ?>
                    up to <strong><?php echo htmlspecialchars($analytics_date_to); ?></strong>
                <?php endif; ?>
            </span>
            <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" class="ml-auto flex items-center gap-1 text-xs text-gray-500 hover:text-red-500 transition">
                <i class="fas fa-times"></i> Clear Filter
            </a>
        </div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- 1. ALGORITHMIC KPI WIDGETS -->
        <!-- ============================================================ -->
        <div class="analytics-kpi-grid">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm analytics-kpi-box flex items-start justify-between gap-3 hover:shadow-md hover:border-[#10A37F] transition-all duration-200">
                <div class="min-w-0">
                    <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Active Hotspots</p>
                    <p class="text-2xl font-extrabold text-amber-600 tracking-tight"><?php echo $activeHotspots; ?></p>
                    <p class="text-xs text-gray-400 mt-1">Unique clusters with density > 0</p>
                </div>
                <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-map-pin text-amber-600"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm analytics-kpi-box flex items-start justify-between gap-3 hover:shadow-md hover:border-[#10A37F] transition-all duration-200">
                <div class="min-w-0">
                    <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Avg Municipal Risk</p>
                    <p class="text-2xl font-extrabold text-blue-600 tracking-tight"><?php echo $avgRisk; ?></p>
                    <p class="text-xs text-gray-400 mt-1">out of 20 severity score</p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-chart-line text-blue-600"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm analytics-kpi-box flex items-start justify-between gap-3 hover:shadow-md hover:border-[#10A37F] transition-all duration-200">
                <div class="min-w-0">
                    <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Critical Escalations</p>
                    <p class="text-2xl font-extrabold text-red-600 tracking-tight"><?php echo $criticalCount; ?></p>
                    <p class="text-xs text-gray-400 mt-1">Score <?php echo $severityBands['critical']; ?>-20 · require immediate action</p>
                </div>
                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm analytics-kpi-box flex items-start justify-between gap-3 hover:shadow-md hover:border-[#10A37F] transition-all duration-200">
                <div class="min-w-0">
                    <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Resolved Hotspots</p>
                    <p class="text-2xl font-extrabold text-emerald-600 tracking-tight"><?php echo $resolvedHotspots; ?></p>
                    <p class="text-xs text-gray-400 mt-1">Clusters resolved this year</p>
                </div>
                <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-emerald-600"></i>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- REPORT FILTER TOOLBAR (filters the heatmap + exports) -->
        <!-- ============================================================ -->
        <?php
        $dash_status_options = [
            'all' => 'All Statuses', 'pending' => 'Pending', 'under_review' => 'Under Review',
            'verified' => 'Verified', 'in_progress' => 'In Progress',
            'escalated_pending' => 'Escalated Pending', 'escalated' => 'Escalated',
            'resolved' => 'Resolved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'
        ];
        $dash_risk_options = ['all' => 'All Risk Levels', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];
        $dash_date_preset = in_array($_GET['date_preset'] ?? '', ['today', 'week', 'month', 'year'], true) ? $_GET['date_preset'] : '';
        $dash_date_preset_options = ['' => 'All Dates', 'today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'];

        $dash_barangay_options = ['' => 'All Barangays'];
        try {
            $dash_barangay_list = $db->query("SELECT id, name FROM barangays ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($dash_barangay_list as $b) {
                $dash_barangay_options[$b['name']] = $b['name'];
            }
        } catch (Exception $e) {
            $dash_barangay_list = [];
        }

        $ft = [
            'search_id'          => 'dashSearchInput',
            'search_value'       => $f_search,
            'search_placeholder' => 'Search reports by title or description...',
            'inline_selects'     => [
                ['id' => 'dashStatusFilter', 'value' => $f_status, 'min_width' => '150px', 'options' => $dash_status_options],
                ['id' => 'dashRiskFilter', 'value' => $f_risk, 'min_width' => '150px', 'options' => $dash_risk_options],
                ['id' => 'dashBarangayFilter', 'value' => $f_barangay, 'min_width' => '170px', 'options' => $dash_barangay_options],
            ],
            'filter_by'          => ['active' => false, 'count' => 0],
            'popover_fields'     => [],
            'date_range'         => [
                'button_label' => 'Date Range',
                'active'       => (bool)($dash_date_preset !== '' || $analytics_date_from || $analytics_date_to),
                'count'        => (int)(($dash_date_preset !== '' ? 1 : 0) + ($analytics_date_from ? 1 : 0) + ($analytics_date_to ? 1 : 0)),
                'presets'      => $dash_date_preset_options,
                'preset'       => $dash_date_preset !== '' ? $dash_date_preset : (($analytics_date_from || $analytics_date_to) ? '__custom__' : ''),
                'from'         => $analytics_date_from,
                'to'           => $analytics_date_to,
            ],
            'active_filters'     => (int)(($f_status !== 'all' ? 1 : 0) + ($f_risk !== 'all' ? 1 : 0) + ($f_barangay !== '' ? 1 : 0) + ($f_search !== '' ? 1 : 0) + ($analytics_date_from ? 1 : 0) + ($analytics_date_to ? 1 : 0) + ($dash_date_preset !== '' ? 1 : 0)),
            'chips'              => [],
            'chips_clear_all'    => false,
            'callback'           => 'applyDashboardFilters',
        ];
        include BASE_PATH . 'views/shared/report_filter_toolbar.php';
        ?>

        <!-- ============================================================ -->
        <!-- 2. DECISION-SUPPORT HEATMAP WITH TOGGLE -->
        <!-- ============================================================ -->
        <div id="map-container" class="mb-6">
            <div class="map-head">
                <div class="map-title-wrap">
                    <h2 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                        <i class="fas fa-map-marked-alt text-[#10A37F]"></i>
                        Environmental Hazard Map
                    </h2>
                    <div class="map-toggle" id="mapToggle">
                        <button class="active" data-mode="active">Active Hazards</button>
                        <button data-mode="historical">Historical Trends</button>
                    </div>
                </div>

                <!-- Timeframe Segmented Control + Custom Range (right side) -->
                <div class="map-head-tools">
                    <div class="map-toggle" id="timeframeToggle">
                        <button data-range="today">Today</button>
                        <button data-range="week">This Week</button>
                        <button data-range="month">This Month</button>
                        <button data-range="year">This Year</button>
                        <button data-range="custom">Custom</button>
                        <button class="active" data-range="all">All Time</button>
                    </div>
                    <div id="customRangeBox" class="hidden items-center gap-2">
                        <input type="date" id="rangeFrom" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-700 bg-white focus:outline-none focus:border-[#10A37F]" title="Start date">
                        <span class="text-xs text-gray-400">to</span>
                        <input type="date" id="rangeTo" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-700 bg-white focus:outline-none focus:border-[#10A37F]" title="End date">
                        <button onclick="applyAnalyticsDateFilter()" class="bg-[#10A37F] text-white text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-[#0D8568] transition flex items-center gap-1" title="Reload page with selected date range to update all KPIs and charts">
                            <i class="fas fa-sync-alt"></i> Apply to Analytics
                        </button>
                    </div>
                </div>
            </div>

            <div id="map" class="relative">
                <!-- Floating legend + category filter overlay (top-right of the map canvas) -->
                <div class="map-overlay">
                    <div class="map-legend">
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#10B981;"></span> Low (1-<?php echo $severityBands['yellow'] - 1; ?>)</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#F59E0B;"></span> Medium (<?php echo $severityBands['yellow']; ?>-<?php echo $severityBands['orange'] - 1; ?>)</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#F97316;"></span> High (<?php echo $severityBands['orange']; ?>-<?php echo $severityBands['critical'] - 1; ?>)</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#EF4444;"></span> Critical (<?php echo $severityBands['critical']; ?>-20)</span>
                    </div>
                    <div class="relative" id="categoryFilterWrap">
                        <button id="categoryFilterBtn" class="flex items-center gap-2 text-sm font-semibold text-gray-700 bg-white border border-gray-200 rounded-full px-4 py-2 shadow-sm hover:border-[#10A37F] transition">
                            <i class="fas fa-filter text-[#10A37F]"></i>
                            <span id="categoryFilterLabel">All Categories</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                        </button>
                        <div id="categoryFilterMenu" class="hidden absolute z-[1100] mt-2 w-64 bg-white rounded-xl border border-gray-200 shadow-lg p-3 right-0">
                            <div class="flex justify-between items-center mb-2 pb-2 border-b border-gray-100">
                                <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">Hazard Categories</span>
                                <div class="flex gap-2">
                                    <button type="button" id="catSelectAll" class="text-xs text-[#10A37F] font-semibold hover:underline">All</button>
                                    <button type="button" id="catSelectNone" class="text-xs text-gray-400 font-semibold hover:underline">None</button>
                                </div>
                            </div>
                            <div id="categoryCheckboxList" class="max-h-56 overflow-y-auto space-y-1">
                                <?php foreach ($categories as $cat): ?>
                                <label class="flex items-center gap-2 text-sm text-gray-700 px-1 py-1 rounded hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" class="category-checkbox accent-[#10A37F]" value="<?php echo htmlspecialchars($cat['id']); ?>" checked>
                                    <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                </label>
                                <?php endforeach; ?>
                                <?php if (empty($categories)): ?>
                                <p class="text-xs text-gray-400 px-1">No categories found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="map-legend map-legend-inline text-xs text-gray-500">
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#10B981;"></span> Low (1-<?php echo $severityBands['yellow'] - 1; ?>)</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#F59E0B;"></span> Medium (<?php echo $severityBands['yellow']; ?>-<?php echo $severityBands['orange'] - 1; ?>)</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#F97316;"></span> High (<?php echo $severityBands['orange']; ?>-<?php echo $severityBands['critical'] - 1; ?>)</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#EF4444;"></span> Critical (<?php echo $severityBands['critical']; ?>-20)</span>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <p class="text-xs text-gray-400" id="filterSummary"></p>
                <span id="barangayFilterChip" class="hidden items-center gap-1 px-2 py-0.5 rounded-full bg-[#10A37F]/10 border border-[#10A37F]/30 text-xs font-semibold text-[#0D8568]">
                    <i class="fas fa-map-pin"></i>
                    <span id="barangayFilterLabel"></span>
                    <button type="button" onclick="clearBarangayFilter()" class="ml-1 hover:text-red-600" aria-label="Clear barangay filter"><i class="fas fa-times"></i></button>
                </span>
            </div>
            <p class="text-xs text-gray-400 mt-2 flex items-center gap-1">
                <i class="fas fa-info-circle"></i>
                Clusters are formed by reports within 50m radius. Color indicates severity score.
                Click a cluster or marker to view detailed analysis. Click a barangay on the map to filter its reports.
            </p>
        </div>

        <!-- ============================================================ -->
        <!-- 3. BOTTOM CHARTS -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
            <!-- Severity Distribution -->
            <div class="chart-card">
                <div class="chart-head">
                    <div class="chart-title"><i class="fas fa-chart-pie text-[#10A37F] mr-2"></i>Severity Distribution</div>
                    <button type="button" class="rec-info-btn" data-rec="rec-severity" title="Show recommendation" aria-label="Show recommendation"><i class="fas fa-info"></i></button>
                </div>
                <div class="chart-container" style="height:180px;">
                    <canvas id="severityChart"></canvas>
                </div>
                <div class="rec-stack" data-rec="rec-severity">
                <?php if ($criticalAlert): ?>
                <div class="rec-box rec-critical mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>Recommendation:</strong> Too many critical cases. Some active reports are critical and need immediate attention. Send help to the affected areas now.
                </div>
                <?php endif; ?>
                <?php
                // Build a data-driven interpretation of the severity distribution.
                $sev_counts = array_column($severityTiers, 'count', 'label');
                $sev_nonzero = array_filter($sev_counts, function ($c) { return $c > 0; });
                $dominant_label = $sev_nonzero ? array_search(max($sev_nonzero), $sev_nonzero) : null;
                $dominant_pct = ($dominant_label !== null && $severityTotal > 0) ? round(($sev_counts[$dominant_label] / $severityTotal) * 100) : 0;
                $zero_labels = array_keys(array_filter($sev_counts, function ($c) { return $c === 0; }));
                $medium_label = 'Medium (' . $severityBands['yellow'] . '-' . ($severityBands['orange'] - 1) . ')';
                ?>
                <div class="rec-box rec-low mt-4">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fas fa-chart-pie"></i>
                        <strong class="text-xs uppercase tracking-wide">Hazard Profile Analysis</strong>
                    </div>
                    <p class="text-xs leading-relaxed">
                        <?php
                        // Plain-language hazard profile: dominant risk level + practical action (no statistics).
                        if ($severityTotal === 0) {
                            echo 'No active reports are currently classified by severity. New reports will be rated automatically as they come in.';
                        } else {
                            $dom_word = 'low';
                            if ($dominant_label !== null) {
                                $dl = strtolower($dominant_label);
                                if (strpos($dl, 'critical') !== false) $dom_word = 'critical';
                                elseif (strpos($dl, 'high') !== false) $dom_word = 'high';
                                elseif (strpos($dl, 'medium') !== false) $dom_word = 'medium';
                            }
                            $critical_label = null;
                            foreach ($sev_counts as $lbl => $cnt) {
                                if (stripos($lbl, 'critical') !== false) { $critical_label = $lbl; break; }
                            }
                            $critical_active = $critical_label !== null && !empty($sev_counts[$critical_label]);

                            echo 'Most active reports are <strong>' . $dom_word . '-severity</strong> hazards';
                            if (!$critical_active) echo ', with no critical emergencies active';
                            echo '. Focus daily clearing operations on these <strong>' . $dom_word . '-priority</strong> hazards before they get worse.';
                            if ($critical_active) echo ' Some reports are critical and need immediate attention.';
                        }
                        ?>
                    </p>
                </div>
                </div><!-- /rec-stack -->
            </div>
            <!-- Seasonal Hazard Analytics -->
            <div class="chart-card">
                <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                    <div class="chart-title"><i class="fas fa-chart-line text-[#10A37F] mr-2"></i>Seasonal Hazard Trends</div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="rec-info-btn" data-rec="rec-seasonal" title="Show recommendation" aria-label="Show recommendation"><i class="fas fa-info"></i></button>
                        <select id="seasonalSeveritySelect" class="text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:border-[#10A37F]">
                            <option value="all">All Severities</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        <select id="seasonalPeriodSelect" class="text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:border-[#10A37F]">
                            <option value="3">Last 3 Months</option>
                            <option value="6">Last 6 Months</option>
                            <option value="12" selected>Last 12 Months</option>
                        </select>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="seasonalChart"></canvas>
                </div>
                <div class="rec-stack" data-rec="rec-seasonal">
                <?php if ($surgeAlert): ?>
                <div class="rec-box rec-critical mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>Recommendation:</strong> <?php echo htmlspecialchars($surgeAlert['category']); ?> reports jumped by <?php echo $surgeAlert['pct']; ?>% this month (from <?php echo $surgeAlert['previous']; ?> to <?php echo $surgeAlert['current']; ?>). Put more resources into <?php echo htmlspecialchars($surgeAlert['category']); ?>.
                </div>
                <?php endif; ?>
                </div><!-- /rec-stack -->
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- 4. BARANGAY PERFORMANCE LEADERBOARD -->
        <!-- ============================================================ -->
        <div class="chart-card mb-6">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
                <div class="chart-title mb-0"><i class="fas fa-trophy text-[#10A37F] mr-2"></i>Barangay Performance Leaderboard</div>
                <span class="flex items-center gap-2 text-xs text-gray-400">
                    <button type="button" class="rec-info-btn" data-rec="rec-leaderboard" title="Show recommendation" aria-label="Show recommendation"><i class="fas fa-info"></i></button>
                    Ranked by resolution rate · accountability &amp; follow-up tool
                </span>
            </div>
            <?php if (empty($barangayLeaderboard)): ?>
                <p class="text-sm text-gray-400 py-6 text-center">No barangay data available yet.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b border-gray-100">
                            <th class="py-2 pr-2">Rank</th>
                            <th class="py-2 pr-2">Barangay</th>
                            <th class="py-2 pr-2 text-right">Assigned</th>
                            <th class="py-2 pr-2 text-right">Resolved</th>
                            <th class="py-2 pr-2">Resolution Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($barangayLeaderboard as $i => $brgy):
                            $rank = $i + 1;
                            $rate = $brgy['resolution_rate'];
                            $barColor = $rate >= 75 ? '#10B981' : ($rate >= 50 ? '#F59E0B' : '#EF4444');
                            $rowFlag = $rate < 50 ? 'bg-red-50/50' : '';
                        ?>
                        <tr class="border-b border-gray-50 <?php echo $rowFlag; ?>">
                            <td class="py-2 pr-2 font-bold text-gray-500">
                                <?php if ($rank === 1): ?><i class="fas fa-medal text-yellow-400"></i>
                                <?php elseif ($rank === 2): ?><i class="fas fa-medal text-gray-400"></i>
                                <?php elseif ($rank === 3): ?><i class="fas fa-medal text-amber-600"></i>
                                <?php else: echo '#' . $rank; endif; ?>
                            </td>
                            <td class="py-2 pr-2 font-semibold text-gray-800"><?php echo htmlspecialchars($brgy['barangay_name']); ?></td>
                            <td class="py-2 pr-2 text-right text-gray-600"><?php echo $brgy['total_assigned']; ?></td>
                            <td class="py-2 pr-2 text-right text-gray-600"><?php echo $brgy['total_resolved']; ?></td>
                            <td class="py-2 pr-2">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-100 rounded-full h-2 min-w-[80px]">
                                        <div class="h-2 rounded-full" style="width: <?php echo min(100, $rate); ?>%; background: <?php echo $barColor; ?>;"></div>
                                    </div>
                                    <span class="font-bold text-xs" style="color: <?php echo $barColor; ?>;"><?php echo $rate; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
                $below_target = array_filter($barangayLeaderboard, function($b) use ($kpi_resolution_rate_target) {
                    return $b['resolution_rate'] < $kpi_resolution_rate_target;
                });
                if (!empty($below_target)):
                    $below_names = array_map(function($b){ return htmlspecialchars($b['barangay_name']); }, $below_target);
            ?>
            <div class="rec-stack" data-rec="rec-leaderboard">
            <div class="rec-box rec-critical mt-4">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong>Recommendation:</strong>
                <?php if (count($below_target) === 1): $b = reset($below_target); ?>
                    Brgy. <?php echo htmlspecialchars($b['barangay_name']); ?> is behind on resolving its hazard reports. Prioritize field teams there to clear the backlog.
                <?php else: ?>
                    The following barangays are behind on resolving their hazard reports:
                    <strong><?php echo implode(', ', $below_names); ?></strong>.
                    Assign cleanup operations and field teams to these areas first.
                <?php endif; ?>
            </div>
            </div><!-- /rec-stack -->
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- ============================================================ -->
        <!-- 5. AVERAGE RESOLUTION TIME + USER DEMOGRAPHICS -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
            <!-- Average Municipal Response Time -->
            <div class="chart-card flex flex-col justify-between">
                <div class="chart-head">
                    <div class="chart-title"><i class="fas fa-stopwatch text-[#10A37F] mr-2"></i>Average Municipal Response Time</div>
                    <button type="button" class="rec-info-btn" data-rec="rec-response" title="Show recommendation" aria-label="Show recommendation"><i class="fas fa-info"></i></button>
                </div>
                <div class="flex items-center gap-4 py-2">
                    <div class="text-5xl font-extrabold text-gray-800"><?php echo $avgResolutionDaysAllTime; ?> <span class="text-xl font-semibold text-gray-400">days</span></div>
                    <?php if ($resolutionTrend !== 'stable'): ?>
                        <div class="flex items-center gap-1 text-sm font-semibold <?php echo $resolutionTrend === 'worse' ? 'text-red-500' : 'text-emerald-500'; ?>">
                            <i class="fas fa-arrow-<?php echo $resolutionTrend === 'worse' ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($resolutionDelta); ?> days vs last month
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-1 text-sm font-semibold text-gray-400">
                            <i class="fas fa-equals"></i> Stable vs last month
                        </div>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-gray-400 mb-3">This month: <?php echo $avgResolutionDaysThisMonth; ?> days &nbsp;·&nbsp; Last month: <?php echo $avgResolutionDaysLastMonth; ?> days</div>
                <?php
                    // Municipal SLA check: warn when the average response time
                    // exceeds the target configured in System Settings → KPI & Insights.
                    $sla_hours = (float)$kpi_sla_response_hours;
                    $sla_breached = $avgResolutionHoursThisMonth > $sla_hours;
                ?>
                <?php if ($sla_breached): ?>
                <div class="rec-stack" data-rec="rec-response">
                <div class="rec-box rec-critical">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>Recommendation:</strong> The municipality is behind schedule on clearing hazard reports<?php if ($slowestBarangay): ?>, with the slowest response in Brgy. <?php echo htmlspecialchars($slowestBarangay['barangay_name']); ?><?php endif; ?>. Send additional cleanup crews or equipment to that area.
                </div>
                </div><!-- /rec-stack -->
                <?php elseif ($resolutionTrend === 'worse'): ?>
                <div class="rec-stack" data-rec="rec-response">
                <div class="rec-box rec-critical">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>Recommendation:</strong> Response times currently meet the target standard, but response has been slowing lately. Keep an eye on crew levels before a backlog builds up.
                </div>
                </div><!-- /rec-stack -->
                <?php else: ?>
                <div class="rec-stack" data-rec="rec-response">
                <div class="rec-box rec-low">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>Recommendation:</strong> Municipal response times comply with the target standard. Keep current crews and monitoring in place.
                </div>
                </div><!-- /rec-stack -->
                <?php endif; ?>
            </div>

            <!-- User Demographics -->
            <div class="chart-card">
                <div class="chart-head">
                    <div class="chart-title"><i class="fas fa-users text-[#10A37F] mr-2"></i>Reporter Demographics</div>
                    <button type="button" class="rec-info-btn" data-rec="rec-demographics" title="Show recommendation" aria-label="Show recommendation"><i class="fas fa-info"></i></button>
                </div>
                <?php if (!$demographicsAvailable || $demographicsTotal === 0): ?>
                    <p class="text-sm text-gray-400 py-10 text-center">Demographic data not available yet.</p>
                <?php else: ?>
                <div class="chart-container" style="height:180px;">
                    <canvas id="demographicsChart"></canvas>
                </div>
                <div class="flex justify-center gap-6 text-xs mt-2">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#10A37F;"></span> Resident (<?php echo $residentPct; ?>%)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#F59E0B;"></span> Non-Resident (<?php echo $nonResidentPct; ?>%)</span>
                </div>
                <p class="text-xs text-gray-400 mt-3 text-center">
                    <?php echo $nonResidentPct; ?>% of reports come from non-residents — a sign the app is catching hazards municipality-wide, not just within local subdivisions.
                </p>
                <?php
                    $lowGroup = null;
                    $lowGroupPct = null;
                    if ($demographicsTotal > 0) {
                        if ($residentPct < (float)$kpi_demographic_threshold) { $lowGroup = 'Residents'; $lowGroupPct = $residentPct; }
                        if ($nonResidentPct < (float)$kpi_demographic_threshold && $nonResidentPct <= $residentPct) { $lowGroup = 'Non-Residents'; $lowGroupPct = $nonResidentPct; }
                    }
                ?>
                <?php if ($lowGroup): ?>
                <div class="rec-stack" data-rec="rec-demographics">
                <div class="rec-box rec-medium mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>Recommendation:</strong> Only <?php echo $lowGroupPct; ?>% of reports come from <?php echo $lowGroup; ?>. Run an info drive to encourage them to report.
                </div>
                </div><!-- /rec-stack -->
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- 6. PEAK REPORTING HOURS/DAYS + TOP 5 REPEAT OFFENDER LOCATIONS -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
            <!-- Peak Reporting Hours & Days -->
            <div class="chart-card">
                <div class="chart-head">
                    <div class="chart-title"><i class="fas fa-clock text-[#10A37F] mr-2"></i>Peak Reporting Hours &amp; Days</div>
                    <button type="button" class="rec-info-btn" data-rec="rec-peak" title="Show recommendation" aria-label="Show recommendation"><i class="fas fa-info"></i></button>
                </div>
                <div class="chart-container">
                    <canvas id="peakDayChart"></canvas>
                </div>
                <div class="rec-stack" data-rec="rec-peak">
                <div class="rec-box rec-medium mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>Recommendation:</strong>
                    <?php if ($peakDayTotal > 0 && $peakDayPlain !== 'N/A'): ?>
                        Most reports are filed on <strong><?php echo $peakDayPlain; ?>s</strong>, mostly <?php echo $peakTimePlain; ?>. Prepare your morning response crews to process new submissions first thing the following day.
                    <?php else: ?>
                        Not enough report data yet to identify a peak reporting window.
                    <?php endif; ?>
                </div>
                </div><!-- /rec-stack -->
            </div>

            <!-- Top 5 Repeat Offender Locations -->
            <div class="chart-card">
                <div class="chart-head">
                    <div class="chart-title"><i class="fas fa-map-marker-alt text-[#10A37F] mr-2"></i>Top 5 "Repeat Offender" Locations</div>
                    <button type="button" class="rec-info-btn" data-rec="rec-repeat" title="Show recommendation" aria-label="Show recommendation"><i class="fas fa-info"></i></button>
                </div>
                <p class="text-xs text-gray-400 mb-3">Behavioral hazards (illegal dumping, vandalism, littering) clustered within a <?php echo (float)$kpi_hotspot_radius_meters; ?>m radius, ranked by resolved incident count.</p>
                <?php if (empty($repeatOffenders)): ?>
                    <p class="text-sm text-gray-400 py-6 text-center">No repeat-offender locations identified yet.</p>
                <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($repeatOffenders as $i => $spot):
                        $rank = $i + 1;
                        $lat = round((float)$spot['avg_lat'], 5);
                        $lng = round((float)$spot['avg_lng'], 5);
                    ?>
                    <div class="flex items-start gap-3 p-3 rounded-xl <?php echo $rank === 1 ? 'bg-red-50' : 'bg-gray-50'; ?>">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs text-white shrink-0" style="background: <?php echo $rank === 1 ? '#EF4444' : '#F59E0B'; ?>;">
                            <?php echo $rank; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-800 text-sm truncate"><?php echo htmlspecialchars($spot['sample_title']); ?></div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($spot['category_names']); ?> · <?php echo $lat; ?>, <?php echo $lng; ?></div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-extrabold text-gray-800"><?php echo (int)$spot['incident_count']; ?>×</div>
                            <div class="text-[10px] text-gray-400 uppercase">resolved</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="rec-stack" data-rec="rec-repeat">
                <div class="rec-box rec-critical mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>Recommendation:</strong>
                    <?php
                        $topSpot = $repeatOffenders[0];
                        $spotCount = (int)$topSpot['incident_count'];
                        $spotBarangay = !empty($topSpot['barangay_name']) ? 'Brgy. ' . $topSpot['barangay_name'] : 'This location';
                        $spotCategory = $topSpot['category_names'] ?? 'the same hazard';
                    ?>
                    <?php echo $spotBarangay; ?> has logged <?php echo $spotCount; ?> reports in <?php echo (int)$kpi_repeat_window_days; ?> days, mostly <?php echo htmlspecialchars($spotCategory); ?>. This keeps coming back. Put up CCTV and look at a permanent fix.
                </div>
                </div><!-- /rec-stack -->
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer note -->
        <div class="text-xs text-gray-400 border-t border-gray-200 pt-4 mt-2 flex justify-between">
            <span>All scores are calculated using the 20‑point algorithm (Base Weight + Impact Modifier + Spatial Density).</span>
            <span>Last updated: <?php echo date('h:i A'); ?></span>
        </div>

    </div>
</div>

<!-- ============================================================ -->
<!-- ENHANCED DRILL-DOWN PANEL -->
<!-- ============================================================ -->
<div id="drillPanel">
    <button class="close-btn" onclick="closeDrillPanel()"><i class="fas fa-times"></i></button>
    <div id="drillContent" class="drill-body">
        <!-- Dynamically populated -->
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
<script>
// ------------------------------------------------------------
// DATA FROM PHP
// ------------------------------------------------------------
const activeReports = <?php echo json_encode($activeReports); ?>;
const historicalReports = <?php echo json_encode($historicalReports); ?>;
const boundaryData = <?php echo json_encode($boundary_data); ?>;
const barangayData = <?php echo json_encode($barangay_data); ?>;
const mapDefaults = <?php echo json_encode(SettingsHelper::getMapSettings()); ?>;
const severityData = <?php echo json_encode(array_values(array_column($severityTiers, 'count'))); ?>;
const severityLabels = <?php echo json_encode(array_values(array_column($severityTiers, 'label'))); ?>;
const seasonalData = <?php echo json_encode($seasonalData); ?>;
const months = <?php echo json_encode($months); ?>;
const seasonalDataByLevel = <?php echo json_encode($seasonalByLevel); ?>;
const seasonalMonthKeys = <?php echo json_encode($seasonalMonths); ?>;
const allCategories = <?php echo json_encode($categories); ?>;
const demographicsAvailable = <?php echo $demographicsAvailable && $demographicsTotal > 0 ? 'true' : 'false'; ?>;
const demographicsData = <?php echo json_encode([$demographics['resident'], $demographics['non_resident']]); ?>;
const dayLabels = <?php echo json_encode($dayLabels); ?>;
const dayCounts = <?php echo json_encode($dayCounts); ?>;
const timeBucketLabels = <?php echo json_encode(array_keys($timeBuckets)); ?>;
const timeBucketData = <?php echo json_encode(array_values($timeBuckets)); ?>;

// ------------------------------------------------------------
// FILTER STATE (Category Filter + Timeframe Selector)
// ------------------------------------------------------------
let selectedCategories = new Set(allCategories.map(c => String(c.id))); // all checked by default
let selectedRange = 'all'; // 'today' | 'week' | 'month' | 'year' | 'custom' | 'all' — starts on "All Time" so the default master-cluster view shows everything
let selectedFrom = ''; // 'YYYY-MM-DD' — used when selectedRange === 'custom'
let selectedTo = '';   // 'YYYY-MM-DD' — used when selectedRange === 'custom'
let searchQuery = '';  // from the report filter toolbar
let selectedStatus = 'all'; // from the report filter toolbar
let selectedRisk = 'all';   // from the report filter toolbar

// ------------------------------------------------------------
// MAP INITIALIZATION
// ------------------------------------------------------------
let map;
let currentLayer = null;
let currentMode = 'active'; // 'active' or 'historical'
let initialFitApplied = false; // preserve the saved default view on first load

// Barangay polygon layer state
let barangayLayer = null;
let selectedBarangay = null;
let selectedBarangayLayer = null;
let spotlightMask = null;
const barangayDefaultStyle = MapLayers.dashedBoundaryStyle(1.5);

function initMap() {
    const center = [mapDefaults.default_lat, mapDefaults.default_lng];
    map = L.map('map').setView(center, mapDefaults.default_zoom);

    MapLayers.addControl(map);

    // Draw one clickable polygon per barangay (from the GeoJSON folder)
    addBarangayLayers();

    // Add the municipality outline as a subtle dashed backdrop so reports that
    // fall outside the barangay polygons still have spatial context.
    if (boundaryData && boundaryData.features) {
        try {
            const coords = extractPolygonCoords(boundaryData);
            if (coords) {
                L.polygon(coords, Object.assign({}, MapLayers.whiteCasingStyle(1.5), { interactive: false })).addTo(map);
                L.polygon(coords, Object.assign({}, MapLayers.dashedBoundaryStyle(1.5), { interactive: false })).addTo(map);
            }
        } catch(e) {}
    }

    // Load initial data
    loadMapData('active');
}

// Render all barangay boundaries as an interactive polygon layer.
function addBarangayLayers() {
    if (!barangayData || !barangayData.features) return;

    // White casing under the dashed green stroke so the boundary reads as
    // alternating green/white dashes.
    L.geoJSON(barangayData, { style: MapLayers.whiteCasingStyle(1.5), interactive: false }).addTo(map);

    barangayLayer = L.geoJSON(barangayData, {
        style: barangayDefaultStyle,
        onEachFeature: function(feature, layer) {
            const name = (feature.properties && feature.properties.name) ? feature.properties.name : 'Barangay';
            layer.bindTooltip(name, { sticky: true });

            layer.on({
                mouseover: function() {
                    if (!selectedBarangayLayer || layer !== selectedBarangayLayer) {
                        layer.setStyle({ fillOpacity: 0.15, weight: 2 });
                        layer.bringToFront();
                    }
                },
                mouseout: function() {
                    if (!selectedBarangayLayer || layer !== selectedBarangayLayer) {
                        layer.setStyle(barangayDefaultStyle);
                    }
                },
                click: function() {
                    toggleBarangayFilter(name, layer);
                }
            });
        }
    }).addTo(map);
}

// Clicking a barangay filters the report markers to only that barangay.
// Clicking the already-selected barangay clears the filter.
function toggleBarangayFilter(name, layer) {
    if (selectedBarangay === name) {
        clearBarangayFilter();
        return;
    }
    selectedBarangay = name;
    if (selectedBarangayLayer) { selectedBarangayLayer.setStyle(barangayDefaultStyle); }
    selectedBarangayLayer = layer;
    layer.setStyle({ fillColor: "#4ADE80", fillOpacity: 0.15, weight: 2, color: "#4ADE80" });
    layer.bringToFront();
    if (spotlightMask) { map.removeLayer(spotlightMask); spotlightMask = null; }
    spotlightMask = MapLayers.spotlight(map, layer);
    updateBarangayFilterChip();
    loadMapData(currentMode);
}

function clearBarangayFilter() {
    selectedBarangay = null;
    if (selectedBarangayLayer) { selectedBarangayLayer.setStyle(barangayDefaultStyle); }
    selectedBarangayLayer = null;
    if (spotlightMask) { map.removeLayer(spotlightMask); spotlightMask = null; }
    updateBarangayFilterChip();
    loadMapData(currentMode);
}

function updateBarangayFilterChip() {
    const chip = document.getElementById('barangayFilterChip');
    const label = document.getElementById('barangayFilterLabel');
    if (!chip || !label) return;
    if (selectedBarangay) {
        label.textContent = selectedBarangay;
        chip.classList.remove('hidden');
        chip.classList.add('inline-flex');
    } else {
        chip.classList.add('hidden');
        chip.classList.remove('inline-flex');
    }
}

function extractPolygonCoords(geojson) {
    if (!geojson || !geojson.features) return null;
    for (const feature of geojson.features) {
        if (feature.geometry && feature.geometry.type === 'MultiPolygon') {
            return feature.geometry.coordinates[0][0].map(coord => [coord[1], coord[0]]);
        }
        if (feature.geometry && feature.geometry.type === 'Polygon') {
            return feature.geometry.coordinates[0].map(coord => [coord[1], coord[0]]);
        }
    }
    return null;
}

// ------------------------------------------------------------
// LOAD MAP DATA WITH CLUSTERING
// ------------------------------------------------------------
function isWithinRange(dateStr, range) {
    if (range === 'all' || !dateStr) return true;
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return true;
    if (range === 'custom') {
        if (selectedFrom) {
            const from = new Date(selectedFrom + 'T00:00:00');
            if (!isNaN(from.getTime()) && date < from) return false;
        }
        if (selectedTo) {
            const to = new Date(selectedTo + 'T23:59:59');
            if (!isNaN(to.getTime()) && date > to) return false;
        }
        return true;
    }
    const now = new Date();
    if (range === 'today') {
        const todayStart = new Date(now);
        todayStart.setHours(0, 0, 0, 0);
        return date >= todayStart;
    }
    if (range === 'week') {
        const weekAgo = new Date(now);
        weekAgo.setDate(now.getDate() - 7);
        return date >= weekAgo;
    }
    if (range === 'month') {
        const monthAgo = new Date(now);
        monthAgo.setMonth(now.getMonth() - 1);
        return date >= monthAgo;
    }
    if (range === 'year') {
        const yearAgo = new Date(now);
        yearAgo.setFullYear(now.getFullYear() - 1);
        return date >= yearAgo;
    }
    return true;
}

function getFilteredData(mode) {
    const source = (mode === 'active') ? activeReports : historicalReports;
    if (!source) return [];
    // Timeframe: active hazards are filtered by when they were reported (created_at);
    // historical hazards are filtered by when they were resolved (resolved_at) —
    // this lets "This Year" + "Historical" surface an entire year of resolved hotspots.
    const dateField = (mode === 'active') ? 'created_at' : 'resolved_at';
    return source.filter(report => {
        const categoryOk = selectedCategories.size === 0
            ? false
            : selectedCategories.has(String(report.category_id));
        const rangeOk = isWithinRange(report[dateField], selectedRange);
        const barangayOk = !selectedBarangay
            || String(report.barangay_name || '').trim().toLowerCase() === selectedBarangay.toLowerCase();
        const statusOk = selectedStatus === 'all' || String(report.status || '') === selectedStatus;
        const riskOk = selectedRisk === 'all' || String(report.risk_level || '') === selectedRisk;
        const q = searchQuery.trim().toLowerCase();
        const searchOk = !q
            || String(report.title || '').toLowerCase().includes(q)
            || String(report.description || '').toLowerCase().includes(q);
        return categoryOk && rangeOk && barangayOk && statusOk && riskOk && searchOk;
    });
}

function updateFilterSummary(mode, count) {
    const rangeLabels = { today: 'today', week: 'this week', month: 'this month', year: 'this year', all: 'all time' };
    let rangeLabel = rangeLabels[selectedRange] || selectedRange;
    if (selectedRange === 'custom') {
        rangeLabel = 'custom (' + (selectedFrom || '?') + ' to ' + (selectedTo || '?') + ')';
    }
    const modeLabel = (mode === 'active') ? 'active' : 'resolved (historical)';
    const el = document.getElementById('filterSummary');
    if (el) {
        let summary = `Showing ${count} ${modeLabel} report(s) · ${rangeLabel} · ${selectedCategories.size} of ${allCategories.length} categories selected.`;
        if (selectedBarangay) {
            summary += ` · Barangay: ${selectedBarangay}`;
        }
        el.textContent = summary;
    }
}

// ------------------------------------------------------------
// REPORT FILTER TOOLBAR — client-side filter callback
// (shared report_filter_toolbar.php calls this via FT.callback)
// ------------------------------------------------------------
// ===== ANALYTICS DATE FILTER (reloads page with GET params for PHP chart/KPI refresh) =====
function applyAnalyticsDateFilter() {
    var from = document.getElementById('rangeFrom') ? document.getElementById('rangeFrom').value : '';
    var to   = document.getElementById('rangeTo')   ? document.getElementById('rangeTo').value   : '';
    var url = new URL(window.location.href);
    if (from) url.searchParams.set('date_from', from); else url.searchParams.delete('date_from');
    if (to)   url.searchParams.set('date_to',   to);   else url.searchParams.delete('date_to');
    url.searchParams.delete('date_preset');
    window.location.href = url.toString();
}

function applyDashboardFilters() {
    const s = document.getElementById('dashSearchInput');
    const st = document.getElementById('dashStatusFilter');
    const rk = document.getElementById('dashRiskFilter');
    const brgy = document.getElementById('dashBarangayFilter');
    const dateFrom = document.getElementById('ftRangeFrom');
    const dateTo   = document.getElementById('ftRangeTo');
    const presetEl = document.getElementById('ftRangePreset');

    const url = new URL(window.location.href);
    const set = (k, v) => {
        if (v && v !== 'all' && v !== '') url.searchParams.set(k, String(v).trim());
        else url.searchParams.delete(k);
    };
    set('search', s ? s.value : '');
    set('status', st ? st.value : 'all');
    set('risk', rk ? rk.value : 'all');
    set('barangay', brgy ? brgy.value : '');

    let df = dateFrom ? dateFrom.value : '';
    let dt = dateTo ? dateTo.value : '';
    let preset = presetEl ? presetEl.value : '';
    if (df || dt) {
        preset = '';
        if (presetEl) presetEl.value = '';
    }
    if (preset) {
        const today = new Date();
        const ymd = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        let f = today, t = today;
        if (preset === 'week') { f = new Date(today); f.setDate(today.getDate() - 6); }
        else if (preset === 'month') { f = new Date(today.getFullYear(), today.getMonth(), 1); }
        else if (preset === 'year') { f = new Date(today.getFullYear(), 0, 1); }
        df = ymd(f); dt = ymd(t);
    }
    set('date_preset', preset);
    set('date_from', df);
    set('date_to', dt);
    window.location.href = url.toString();
}

function loadMapData(mode) {
    if (currentLayer) {
        map.removeLayer(currentLayer);
        currentLayer = null;
    }

    const data = getFilteredData(mode);
    updateFilterSummary(mode, data.length);

    if (!data || data.length === 0) {
        // Show empty state
        currentLayer = L.layerGroup().addTo(map);
        return;
    }

    // Create a custom cluster group with severity-based styling
    const clusterGroup = L.markerClusterGroup({
        maxClusterRadius: 60, // 60px radius for clustering
        iconCreateFunction: function(cluster) {
            // Get all markers in the cluster
            const markers = cluster.getAllChildMarkers();
            // Compute average severity score for coloring
            let totalScore = 0;
            let count = 0;
            markers.forEach(m => {
                const score = m.options.severityScore || 0;
                totalScore += score;
                count++;
            });
            const avgScore = count > 0 ? totalScore / count : 0;
            const color = getSeverityColor(avgScore);
            const size = 40 + (count * 2); // larger cluster = more reports
            return L.divIcon({
                html: `<div style="background: ${color}; width: ${size}px; height: ${size}px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: ${size/2}px; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">${count}</div>`,
                iconSize: [size, size],
                className: 'cluster-icon'
            });
        }
    });

    // Add markers
    data.forEach(report => {
        const lat = parseFloat(report.latitude);
        const lng = parseFloat(report.longitude);
        if (isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0) return;

        const score = parseInt(report.severity_score) || 0;
        const color = getSeverityColor(score);
        const tier = getSeverityTier(score);
        const popupContent = `
            <div style="font-family: Manrope; min-width: 200px;">
                <strong style="font-size: 14px;">${escapeHtml(report.title)}</strong><br>
                <span style="font-size: 12px; color: #64748b;">Severity: ${score}/20 (${tier})</span><br>
                <span style="font-size: 12px; color: #64748b;">Reports in cluster: ${report.spatial_density_count || 0}</span><br>
            </div>
        `;

        const catName = report.category_name || 'General';
        const icon = L.divIcon({
            html: `<div class="sev-marker-wrap">
                     <div class="sev-marker-dot" style="background:${color};"></div>
                     <div class="sev-marker-label">${escapeHtml(catName)}</div>
                   </div>`,
            iconSize: [120, 40],
            iconAnchor: [60, 12],
            className: 'severity-marker'
        });

        const marker = L.marker([lat, lng], { icon: icon, severityScore: score })
            .bindPopup(popupContent);

        // On click, open drill-down with the report ID
        marker.on('click', function() {
            openDrillPanel(report.id);
        });

        clusterGroup.addLayer(marker);
    });

    currentLayer = clusterGroup;
    map.addLayer(clusterGroup);
    // Fit bounds
    if (data.length > 0 && initialFitApplied) {
        const bounds = L.latLngBounds(data.map(r => [r.latitude, r.longitude]));
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
    }
    initialFitApplied = true;
}

// ------------------------------------------------------------
// MAP TOGGLE
// ------------------------------------------------------------
document.getElementById('mapToggle').addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;
    const mode = btn.dataset.mode;
    if (mode === currentMode) return;
    currentMode = mode;
    // Toggle active class
    this.querySelectorAll('button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadMapData(mode);
});

// ------------------------------------------------------------
// CATEGORY FILTER DROPDOWN
// ------------------------------------------------------------
const categoryFilterBtn = document.getElementById('categoryFilterBtn');
const categoryFilterMenu = document.getElementById('categoryFilterMenu');
const categoryFilterLabel = document.getElementById('categoryFilterLabel');

categoryFilterBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    categoryFilterMenu.classList.toggle('hidden');
});
document.addEventListener('click', function(e) {
    if (!categoryFilterMenu.contains(e.target) && e.target !== categoryFilterBtn) {
        categoryFilterMenu.classList.add('hidden');
    }
});

function updateCategoryLabel() {
    const total = allCategories.length;
    const selected = selectedCategories.size;
    if (selected === total) categoryFilterLabel.textContent = 'All Categories';
    else if (selected === 0) categoryFilterLabel.textContent = 'No Categories';
    else if (selected === 1) {
        const only = allCategories.find(c => selectedCategories.has(String(c.id)));
        categoryFilterLabel.textContent = only ? only.name : '1 Category';
    } else categoryFilterLabel.textContent = `${selected} Categories`;
}

document.querySelectorAll('.category-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        if (this.checked) selectedCategories.add(this.value);
        else selectedCategories.delete(this.value);
        updateCategoryLabel();
        loadMapData(currentMode);
    });
});

document.getElementById('catSelectAll').addEventListener('click', function() {
    document.querySelectorAll('.category-checkbox').forEach(cb => {
        cb.checked = true;
        selectedCategories.add(cb.value);
    });
    updateCategoryLabel();
    loadMapData(currentMode);
});

document.getElementById('catSelectNone').addEventListener('click', function() {
    document.querySelectorAll('.category-checkbox').forEach(cb => {
        cb.checked = false;
    });
    selectedCategories.clear();
    updateCategoryLabel();
    loadMapData(currentMode);
});

// ------------------------------------------------------------
// TIMEFRAME SELECTOR (works alongside Active/Historical toggle)
// ------------------------------------------------------------
function selectRange(range) {
    if (range === selectedRange && range !== 'custom') return;
    selectedRange = range;
    document.querySelectorAll('[data-range]').forEach(b => {
        b.classList.toggle('active', b.dataset.range === range);
    });
    toggleCustomRangeBox();
    loadMapData(currentMode);
}

document.getElementById('timeframeToggle').addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;
    selectRange(btn.dataset.range);
});

// ------------------------------------------------------------
// CUSTOM DATE RANGE (from/to date pickers shown when "Custom" is active)
// ------------------------------------------------------------
function toYMD(d) {
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + m + '-' + day;
}

function toggleCustomRangeBox() {
    const box = document.getElementById('customRangeBox');
    if (!box) return;
    if (selectedRange === 'custom') {
        if (!selectedFrom) {
            const to = new Date();
            const from = new Date();
            from.setDate(to.getDate() - 30);
            selectedFrom = toYMD(from);
            selectedTo = toYMD(to);
        }
        document.getElementById('rangeFrom').value = selectedFrom;
        document.getElementById('rangeTo').value = selectedTo;
        box.classList.remove('hidden');
        box.classList.add('flex');
    } else {
        box.classList.add('hidden');
        box.classList.remove('flex');
    }
}

function applyCustomRange() {
    if (selectedRange !== 'custom') return;
    const from = document.getElementById('rangeFrom').value;
    const to = document.getElementById('rangeTo').value;
    if (!from || !to) {
        window.GB.alert({ type: 'error', title: 'Invalid range', message: 'Please select both a start and end date.' });
        return;
    }
    if (from > to) {
        window.GB.alert({ type: 'error', title: 'Invalid range', message: 'The start date must be on or before the end date.' });
        return;
    }
    selectedFrom = from;
    selectedTo = to;
    loadMapData(currentMode);
}

document.getElementById('rangeFrom').addEventListener('change', applyCustomRange);
document.getElementById('rangeTo').addEventListener('change', applyCustomRange);

// ------------------------------------------------------------
// SEVERITY COLOR HELPERS
// ------------------------------------------------------------
function getSeverityColor(score) {
    return getRiskColorFromScore(score);
}

function getSeverityTier(score) {
    return getRiskLevelLabelFromScore(score);
}

// Single source of truth for risk bands, mirroring PHP getSeverityBands()
const SEVERITY_BANDS = { yellow: <?php echo $severityBands['yellow']; ?>, orange: <?php echo $severityBands['orange']; ?>, critical: <?php echo $severityBands['critical']; ?> };
function getRiskLevelFromScore(score) {
    if (score < SEVERITY_BANDS.yellow) return 'low';
    if (score < SEVERITY_BANDS.orange) return 'medium';
    if (score < SEVERITY_BANDS.critical) return 'high';
    return 'critical';
}
function getRiskColorFromScore(score) {
    const colors = { low: '#10B981', medium: '#F59E0B', high: '#F97316', critical: '#EF4444' };
    return colors[getRiskLevelFromScore(score)] || '#10B981';
}
function getRiskLevelLabelFromScore(score) {
    const labels = { low: 'Low', medium: 'Medium', high: 'High', critical: 'Critical' };
    return labels[getRiskLevelFromScore(score)] || 'Low';
}
function getRiskRecommendation(score) {
    const recs = {
        low: 'Keep as is. Barangay can handle this with regular cleanup.',
        medium: 'Barangay should act soon. MENRO should keep an eye on this.',
        high: 'Send to MENRO. Clear the hazard now before it spreads or causes flooding.',
        critical: 'Act now. Send MENRO crews and equipment to this location right away.'
    };
    return recs[getRiskLevelFromScore(score)] || recs.low;
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// ------------------------------------------------------------
// ENHANCED DRILL-DOWN PANEL (AJAX)
// ------------------------------------------------------------
function openDrillPanel(reportId) {
    // Show loading
    document.getElementById('drillContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-[#10A37F]"></i><p class="mt-2 text-gray-500">Loading report details...</p></div>';
    document.getElementById('drillPanel').classList.add('open');

    // Fetch report details via AJAX (use existing endpoint)
    fetch('<?php echo BASE_URL; ?>controllers/ReportController.php?action=get_full&id=' + reportId)
        .then(response => response.json())
        .then(data => {
            if (!data || data.error) {
                document.getElementById('drillContent').innerHTML = '<p class="text-red-500">Error loading report details.</p>';
                return;
            }
            renderDrillPanel(data);
        })
        .catch(err => {
            document.getElementById('drillContent').innerHTML = '<p class="text-red-500">Failed to load data.</p>';
            console.error(err);
        });
}

function renderDrillPanel(report) {
    const score = parseInt(report.severity_score) || 0;
    const tier = getSeverityTier(score);
    const riskLevel = getRiskLevelFromScore(score);
    const recClass = 'drill-rec-' + riskLevel;
    const recText = getRiskRecommendation(score);

    // Get category name
    const categoryName = report.category_name || 'Uncategorized';

    // Build photo gallery HTML
    const baseUrl = '<?php echo BASE_URL; ?>';
    const isVideoFile = p => /\.(mp4|webm|mov|m4v|avi)$/i.test(p);
    const buildMediaHtml = p => {
        const mediaUrl = baseUrl + p.trim();
        if (isVideoFile(mediaUrl)) {
            return `<video src="${mediaUrl}" muted playsinline preload="metadata" onclick="window.open('${mediaUrl}','_blank')"></video>`;
        }
        return `<img src="${mediaUrl}" onclick="window.open('${mediaUrl}','_blank')" alt="Evidence photo" loading="lazy" onerror="this.style.display='none'">`;
    };
    let photoHtml = '';
    if (report.image_paths) {
        const paths = report.image_paths.split(',').filter(p => p && p.trim());
        const maxPhotos = 3;
        const displayPhotos = paths.slice(0, maxPhotos);
        photoHtml = displayPhotos.map(buildMediaHtml).join('');
        if (displayPhotos.length === 0) {
            photoHtml = '<div class="no-photo"><i class="fas fa-image text-2xl block mb-1"></i>No photos available</div>';
        } else if (displayPhotos.length < paths.length) {
            // Add a + indicator
            photoHtml += `<div class="flex items-center justify-center bg-gray-100 rounded-lg text-gray-500 text-sm font-bold">+${paths.length - displayPhotos.length}</div>`;
        }
    } else {
        photoHtml = '<div class="no-photo"><i class="fas fa-image text-2xl block mb-1"></i>No photos available</div>';
    }

    // Build resolution evidence gallery HTML
    let resolutionHtml = '';
    if (report.resolution_evidence_paths) {
        const resPaths = report.resolution_evidence_paths.split(',').filter(p => p && p.trim());
        resolutionHtml = resPaths.map(buildMediaHtml).join('');
        if (resPaths.length === 0) {
            resolutionHtml = '<div class="no-photo"><i class="fas fa-check-circle text-2xl block mb-1"></i>No resolution evidence</div>';
        }
    } else {
        resolutionHtml = '<div class="no-photo"><i class="fas fa-check-circle text-2xl block mb-1"></i>No resolution evidence</div>';
    }

    // Build location text
    let locationText = '';
    if (report.location_address) {
        locationText = report.location_address;
    } else if (report.latitude && report.longitude) {
        locationText = `${parseFloat(report.latitude).toFixed(6)}, ${parseFloat(report.longitude).toFixed(6)}`;
    } else {
        locationText = 'No location data';
    }

    const html = `
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">${escapeHtml(report.title)}</h3>
            <span class="text-sm bg-gray-100 px-2 py-1 rounded">#${String(report.id).padStart(6,'0')}</span>
        </div>

        <!-- Status Badges -->
        <div class="flex flex-wrap gap-2 mb-4">
            ${getStatusBadgeHTML(report.status)}
            ${getRiskBadgeHTML(report.risk_level || 'low')}
        </div>

        <!-- Quick Overview -->
        <div class="space-y-3 mb-4">
            <div class="flex items-start gap-2">
                <span class="text-gray-500 text-sm w-24 flex-shrink-0 font-medium">Category:</span>
                <span class="text-gray-800 font-semibold">${escapeHtml(categoryName)}</span>
            </div>
            <div class="flex items-start gap-2">
                <span class="text-gray-500 text-sm w-24 flex-shrink-0 font-medium">Description:</span>
                <span class="text-gray-700 text-sm">${escapeHtml(report.description ? report.description.substring(0, 150) : 'No description')}${report.description && report.description.length > 150 ? '...' : ''}</span>
            </div>
            <div class="flex items-start gap-2">
                <span class="text-gray-500 text-sm w-24 flex-shrink-0 font-medium">Location:</span>
                <span class="text-gray-700 text-sm">${escapeHtml(locationText)}</span>
            </div>
        </div>

        <!-- Photo Evidence -->
        <div class="mb-4">
            <p class="text-sm font-semibold text-gray-700 mb-2">Photo Evidence</p>
            <div class="drill-photo-grid">
                ${photoHtml}
            </div>
        </div>

        <!-- Resolution Evidence -->
        <div class="mb-4">
            <p class="text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-check-circle text-emerald-500 mr-1"></i>Resolution Evidence
            </p>
            <div class="drill-photo-grid">
                ${resolutionHtml}
            </div>
        </div>

        <!-- Severity Score (optional) -->
        <div class="bg-gray-50 rounded-xl p-4 mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-700">Severity Score</p>
                    <p class="text-2xl font-extrabold ${riskLevel === 'critical' ? 'text-red-600' : (riskLevel === 'high' ? 'text-orange-600' : (riskLevel === 'medium' ? 'text-amber-600' : 'text-emerald-600'))}">${score} / 20</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-400">Classification</p>
                    <p class="text-sm font-semibold text-gray-700">${report.decision_classification || 'Pending'}</p>
                </div>
            </div>
            <div class="mt-2 flex gap-1">
                ${Array.from({length: 20}, (_, i) => {
                    const filled = i < score;
                    return `<div class="h-2 flex-1 rounded-full ${filled ? (riskLevel === 'critical' ? 'bg-red-500' : (riskLevel === 'high' ? 'bg-orange-500' : (riskLevel === 'medium' ? 'bg-amber-500' : 'bg-emerald-500'))) : 'bg-gray-200'}"></div>`;
                }).join('')}
            </div>
        </div>

        <!-- Recommendation -->
        <div class="drill-rec-box ${recClass}">
            <i class="fas fa-lightbulb mr-2"></i>
            <strong>Recommendation:</strong> ${recText}
        </div>

        <!-- Open Full Report -->
        <a href="<?php echo BASE_URL; ?>index.php?page=manage-report&id=${report.token}" target="_blank" class="drill-open-btn">
            <i class="fas fa-external-link-alt mr-2"></i> Open Full Report
        </a>

        <div class="mt-4 text-xs text-gray-400 flex gap-4">
            <span><i class="far fa-calendar-alt mr-1"></i>Reported: ${new Date(report.created_at).toLocaleString()}</span>
            <span><i class="far fa-clock mr-1"></i>${timeAgo(report.created_at)}</span>
        </div>
    `;

    document.getElementById('drillContent').innerHTML = html;
}

// Helper: Get status badge HTML
function getStatusBadgeHTML(status) {
    const statusMap = {
        'pending': { label: 'Pending', class: 'status-pending', icon: 'fa-clock' },
        'under_review': { label: 'Under Review', class: 'status-under_review', icon: 'fa-search' },
        'verified': { label: 'Verified', class: 'status-verified', icon: 'fa-check-circle' },
        'in_progress': { label: 'In Progress', class: 'status-in_progress', icon: 'fa-spinner fa-pulse' },
        'escalated_pending': { label: 'Escalated Pending', class: 'status-escalated_pending', icon: 'fa-hourglass-half' },
        'escalated': { label: 'Escalated', class: 'status-escalated', icon: 'fa-shield-alt' },
        'resolved': { label: 'Resolved', class: 'status-resolved', icon: 'fa-check-circle' },
        'rejected': { label: 'Rejected', class: 'status-rejected', icon: 'fa-times-circle' },
        'cancelled': { label: 'Cancelled', class: 'status-cancelled', icon: 'fa-ban' }
    };
    const info = statusMap[status] || { label: status, class: 'status-pending', icon: 'fa-circle' };
    return `<span class="status-badge ${info.class}"><i class="fas ${info.icon} text-xs"></i> ${info.label}</span>`;
}

// Helper: Get risk badge HTML
function getRiskBadgeHTML(risk) {
    const riskMap = {
        'low': { label: 'Low', class: 'risk-low', icon: 'fa-seedling' },
        'medium': { label: 'Medium', class: 'risk-medium', icon: 'fa-exclamation-triangle' },
        'high': { label: 'High', class: 'risk-high', icon: 'fa-fire' },
        'critical': { label: 'Critical', class: 'risk-critical', icon: 'fa-skull-crossbones' }
    };
    const info = riskMap[risk] || { label: risk, class: 'risk-low', icon: 'fa-circle' };
    return `<span class="risk-badge ${info.class}"><i class="fas ${info.icon} text-xs"></i> ${info.label}</span>`;
}

// Time ago helper
function timeAgo(dateStr) {
    const now = new Date();
    const then = new Date(dateStr);
    const diff = Math.floor((now - then) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    return Math.floor(diff / 86400) + ' days ago';
}

function closeDrillPanel() {
    document.getElementById('drillPanel').classList.remove('open');
}

// Close panel on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDrillPanel();
});

// ------------------------------------------------------------
// SEASONAL HAZARD TRENDS (customizable severity + time window)
// ------------------------------------------------------------
let seasonalChartInstance = null;

const SEASONAL_META = {
    all:      { label: 'All Severities', color: '#10A37F', bg: 'rgba(16, 163, 127, 0.12)' },
    low:      { label: 'Low',            color: '#10B981', bg: 'rgba(16, 185, 129, 0.12)' },
    medium:   { label: 'Medium',         color: '#F59E0B', bg: 'rgba(245, 158, 11, 0.12)' },
    high:     { label: 'High',           color: '#F97316', bg: 'rgba(249, 115, 22, 0.12)' },
    critical: { label: 'Critical',       color: '#EF4444', bg: 'rgba(239, 68, 68, 0.12)' }
};

function renderSeasonalChart() {
    const canvas = document.getElementById('seasonalChart');
    if (!canvas) return;

    const severitySel = document.getElementById('seasonalSeveritySelect');
    const periodSel = document.getElementById('seasonalPeriodSelect');
    const severity = severitySel ? severitySel.value : 'all';
    const period = parseInt(periodSel ? periodSel.value : '12', 10);

    // seasonalMonthKeys are oldest -> newest; take the most recent N months.
    const keys = (seasonalMonthKeys || []).slice(-period);
    const labels = keys.map(k => {
        const d = new Date(k + '-01T00:00:00');
        return isNaN(d.getTime()) ? k : d.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
    });
    const data = keys.map(k => {
        const bucket = seasonalDataByLevel[severity] || seasonalDataByLevel.all || {};
        return bucket[k] || 0;
    });

    const meta = SEASONAL_META[severity] || SEASONAL_META.all;

    if (seasonalChartInstance) {
        seasonalChartInstance.data.labels = labels;
        seasonalChartInstance.data.datasets[0].data = data;
        seasonalChartInstance.data.datasets[0].label = meta.label;
        seasonalChartInstance.data.datasets[0].borderColor = meta.color;
        seasonalChartInstance.data.datasets[0].backgroundColor = meta.bg;
        seasonalChartInstance.data.datasets[0].pointBackgroundColor = meta.color;
        seasonalChartInstance.update();
        return;
    }

    const ctx = canvas.getContext('2d');
    seasonalChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: meta.label,
                data: data,
                borderColor: meta.color,
                backgroundColor: meta.bg,
                tension: 0.3,
                fill: true,
                pointBackgroundColor: meta.color,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#e5e7eb' }, ticks: { font: { size: 10 } } },
                x: { grid: { display: false }, ticks: { font: { size: 10 } } }
            },
            responsive: true,
            maintainAspectRatio: true
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const sev = document.getElementById('seasonalSeveritySelect');
    const period = document.getElementById('seasonalPeriodSelect');
    if (sev) sev.addEventListener('change', renderSeasonalChart);
    if (period) period.addEventListener('change', renderSeasonalChart);

    // Auto-populate date inputs from GET params (analytics date filter)
    const urlParams = new URLSearchParams(window.location.search);
    const dateFrom = urlParams.get('date_from');
    const dateTo   = urlParams.get('date_to');
    if (dateFrom || dateTo) {
        // Switch to Custom mode
        const customBtn = document.querySelector('#timeframeToggle [data-range="custom"]');
        if (customBtn) customBtn.click();
        setTimeout(function() {
            if (dateFrom) { const f = document.getElementById('rangeFrom'); if (f) f.value = dateFrom; }
            if (dateTo)   { const t = document.getElementById('rangeTo');   if (t) t.value = dateTo; }
        }, 100);
    }
});

// ------------------------------------------------------------
// CHARTS
// ------------------------------------------------------------
function initCharts() {
    // Severity Distribution (Doughnut)
    const ctx1 = document.getElementById('severityChart').getContext('2d');
    new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: severityLabels,
            datasets: [{
                data: severityData,
                backgroundColor: ['#10B981', '#F59E0B', '#F97316', '#EF4444'],
                borderWidth: 0
            }]
        },
        options: {
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, family: 'Manrope' } } }
            },
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Seasonal Hazard Trends (Line) — customizable via renderSeasonalChart()
    renderSeasonalChart();

    // Reporter Demographics (Donut)
    if (demographicsAvailable) {
        const ctx3 = document.getElementById('demographicsChart').getContext('2d');
        new Chart(ctx3, {
            type: 'doughnut',
            data: {
                labels: ['Resident', 'Non-Resident'],
                datasets: [{
                    data: demographicsData,
                    backgroundColor: ['#10A37F', '#F59E0B'],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '65%',
                plugins: { legend: { display: false } },
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    // Peak Reporting Hours & Days (grouped bars: day of week + time-of-day)
    const ctx4 = document.getElementById('peakDayChart').getContext('2d');
    new Chart(ctx4, {
        type: 'bar',
        data: {
            labels: dayLabels,
            datasets: [{
                label: 'Reports by Day',
                data: dayCounts,
                backgroundColor: '#10A37F',
                borderRadius: 4,
                maxBarThickness: 32
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#e5e7eb' }, ticks: { font: { size: 10 }, precision: 0 } },
                x: { grid: { display: false }, ticks: { font: { size: 10 } } }
            },
            responsive: true,
            maintainAspectRatio: true
        }
    });
}

// ------------------------------------------------------------
// EXPORT FUNCTIONS
// ------------------------------------------------------------
function toggleExportDropdown() {
    const dd = document.getElementById('exportDropdown');
    const btn = document.getElementById('exportDropBtn');
    if (dd) dd.classList.toggle('open');
    if (btn) btn.classList.toggle('active');
}

// Close the export dropdown when clicking outside of it.
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('exportDropdownWrap');
    const dd = document.getElementById('exportDropdown');
    const btn = document.getElementById('exportDropBtn');
    if (wrap && dd && !wrap.contains(e.target)) {
        dd.classList.remove('open');
        if (btn) btn.classList.remove('active');
    }
});

function buildDashboardReportUrl(format) {
    const cats = [...selectedCategories].join(',');
    let url = '<?php echo BASE_URL; ?>index.php?page=dashboard-report'
        + '&range=' + encodeURIComponent(selectedRange)
        + '&cats=' + encodeURIComponent(cats);
    if (selectedRange === 'custom') {
        url += '&from=' + encodeURIComponent(selectedFrom || '')
            + '&to=' + encodeURIComponent(selectedTo || '');
    }
    if (format) url += '&format=' + encodeURIComponent(format);
    return url;
}

function exportAnalyticsPdf() {
    closeExportDropdown();
    window.open(buildDashboardReportUrl('') + '&autoprint=1', '_blank');
}

function exportAnalyticsCsv() {
    closeExportDropdown();
    // Download without opening a new tab/page.
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = buildDashboardReportUrl('csv');
    document.body.appendChild(iframe);
    setTimeout(function() { iframe.remove(); }, 8000);
}

function closeExportDropdown() {
    const dd = document.getElementById('exportDropdown');
    const btn = document.getElementById('exportDropBtn');
    if (dd) dd.classList.remove('open');
    if (btn) btn.classList.remove('active');
}

// ------------------------------------------------------------
// INIT
// ------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    initCharts();
});

// ------------------------------------------------------------
// RECOMMENDATION CAROUSEL (YouTube-style "i" info)
// Auto-shows one recommendation at a time every 10s; clicking
// the chart's "i" button pins it open. Click again to close.
// ------------------------------------------------------------
(function recCarousel() {
    const stacks = Array.from(document.querySelectorAll('.rec-stack'))
        .filter(function (s) { return s.querySelector('.rec-box') !== null; });

    stacks.forEach(function (s) { s.classList.remove('open'); });

    document.querySelectorAll('.rec-info-btn').forEach(function (btn) {
        const idx = stacks.indexOf(document.querySelector('.rec-stack[data-rec="' + btn.dataset.rec + '"]'));
        if (idx === -1) { btn.style.display = 'none'; }
    });

    if (stacks.length === 0) return;

    let current = -1;
    let pinned = false;
    let timer = null;

    function show(i) {
        stacks.forEach(function (s, k) { s.classList.toggle('open', k === i); });
        document.querySelectorAll('.rec-info-btn').forEach(function (btn) {
            const idx = stacks.indexOf(document.querySelector('.rec-stack[data-rec="' + btn.dataset.rec + '"]'));
            btn.classList.toggle('active', idx === i);
        });
        current = i;
    }

    function next() { if (!pinned && stacks.length) show((current + 1) % stacks.length); }

    function start() {
        if (timer) clearInterval(timer);
        timer = setInterval(next, 10000);
    }

    function stop() { if (timer) { clearInterval(timer); timer = null; } }

    document.querySelectorAll('.rec-info-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.style.display === 'none') return;
            const idx = stacks.indexOf(document.querySelector('.rec-stack[data-rec="' + btn.dataset.rec + '"]'));
            if (idx === -1) return;
            if (current === idx && pinned) {
                pinned = false;
                start();
            } else {
                pinned = true;
                stop();
                show(idx);
            }
        });
    });

    show(0);
    start();
})();
</script>

</body>
</html>
