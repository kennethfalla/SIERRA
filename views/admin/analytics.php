<?php
// MENRO analytics and decision support.
// Algorithmic KPIs, Heatmap with Clustering, Drill-Down Panel, Trend Charts
// Updated: Export Analytics (CSV/PDF), Enhanced Cluster Drill-Down with Photos

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/helpers/Lang.php';
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
$f_search = trim((string)($_GET['search'] ?? ''));
$f_category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0;

function _build_af($alias, $date_col, $with_date, $f_status, $f_risk, $f_barangay, $f_search, $f_category) {
    $clause = '';
    $params = [];
    $p = function ($col) use ($alias) { return $alias === '' ? $col : $alias . '.' . $col; };
    if ($with_date && !empty($GLOBALS['analytics_date_from'])) { $clause .= ' AND ' . $p($date_col) . ' >= ?';           $params[] = $GLOBALS['analytics_date_from'] . ' 00:00:00'; }
    if ($with_date && !empty($GLOBALS['analytics_date_to']))   { $clause .= ' AND ' . $p($date_col) . ' <= ?';           $params[] = $GLOBALS['analytics_date_to']   . ' 23:59:59'; }
    if ($f_status !== 'all')  { $clause .= ' AND ' . $p('status') . ' = ?';                                             $params[] = $f_status; }
    if ($f_risk   !== 'all')  { $clause .= ' AND ' . $p('risk_level') . ' = ?';                                         $params[] = $f_risk; }
    if ($f_barangay !== '')   { $clause .= ' AND ' . $p('barangay_id') . ' IN (SELECT id FROM barangays WHERE name = ?)'; $params[] = $f_barangay; }
    if ($f_category > 0) { $clause .= ' AND ' . $p('category_id') . ' = ?'; $params[] = $f_category; }
    if ($f_search !== '')     { $clause .= ' AND (' . $p('title') . ' LIKE ? OR ' . $p('description') . ' LIKE ?)';     $params[] = "%$f_search%"; $params[] = "%$f_search%"; }
    return [$clause, $params];
}

// created_at-based clauses (default analytics)
list($fragC_plain, $fragC_plain_params) = _build_af('', 'created_at', true, $f_status, $f_risk, $f_barangay, $f_search, $f_category);
list($fragC_r,     $fragC_r_params)     = _build_af('r', 'created_at', true, $f_status, $f_risk, $f_barangay, $f_search, $f_category);
// resolved_at-based clauses (resolution metrics)
list($fragR_plain, $fragR_plain_params) = _build_af('', 'resolved_at', true, $f_status, $f_risk, $f_barangay, $f_search, $f_category);
list($fragR_r,     $fragR_r_params)     = _build_af('r', 'resolved_at', true, $f_status, $f_risk, $f_barangay, $f_search, $f_category);
// status+risk+barangay+search only (no date) — for inherently time-scoped cards
list($fragSR_plain, $fragSR_plain_params) = _build_af('', 'created_at', false, $f_status, $f_risk, $f_barangay, $f_search, $f_category);
list($fragSR_r,     $fragSR_r_params)     = _build_af('r', 'created_at', false, $f_status, $f_risk, $f_barangay, $f_search, $f_category);

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
                r.created_at
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
                r.resolved_at
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
foreach ($severityTiers as &$severityTier) {
    $severityTier['percentage'] = $severityTotal > 0 ? round(($severityTier['count'] / $severityTotal) * 100, 1) : 0;
}
unset($severityTier);
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
require_once BASE_PATH . 'helpers/ReporterDemographics.php';
$demographics = ['resident' => 0, 'non_resident' => 0, 'unknown' => 0];
$demographicsAvailable = true;
try {
    $demographics = ReporterDemographics::summarize($db, "1=1 {$fragC_r}", $fragC_r_params);
} catch (Exception $e) {
    $demographicsAvailable = false;
    error_log('Reporter demographics unavailable: ' . $e->getMessage());
}
$demographicsTotal = array_sum($demographics);
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

// Summary and comparison data use the same filter scope as the existing charts.
$analyticsSummaryStmt = $db->prepare("SELECT COUNT(*) AS total,
    SUM(status = 'resolved') AS resolved,
    SUM(risk_level IN ('high', 'critical')) AS urgent
    FROM reports WHERE 1=1 {$fragC_plain}");
$analyticsSummaryStmt->execute($fragC_plain_params);
$analyticsSummary = $analyticsSummaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$analyticsTotal = (int)($analyticsSummary['total'] ?? 0);
$analyticsResolutionRate = $analyticsTotal ? round(100 * (int)($analyticsSummary['resolved'] ?? 0) / $analyticsTotal, 1) : 0;
$analyticsUrgentRate = $analyticsTotal ? round(100 * (int)($analyticsSummary['urgent'] ?? 0) / $analyticsTotal, 1) : 0;

$hazardAnalysisStmt = $db->prepare("SELECT COALESCE(c.name, 'Uncategorized') AS name, COUNT(*) AS total,
    SUM(r.status = 'resolved') AS resolved
    FROM reports r LEFT JOIN categories c ON c.id = r.category_id
    WHERE 1=1 {$fragC_r} GROUP BY r.category_id, c.name ORDER BY total DESC");
$hazardAnalysisStmt->execute($fragC_r_params);
$hazardAnalysis = $hazardAnalysisStmt->fetchAll(PDO::FETCH_ASSOC);

$resolutionMonthsStmt = $db->prepare("SELECT DATE_FORMAT(r.resolved_at, '%Y-%m') AS month, COUNT(*) AS total
    FROM reports r WHERE r.status = 'resolved' AND r.resolved_at IS NOT NULL {$fragR_r}
    GROUP BY DATE_FORMAT(r.resolved_at, '%Y-%m') ORDER BY month DESC LIMIT 12");
$resolutionMonthsStmt->execute($fragR_r_params);
$resolutionMonths = array_reverse($resolutionMonthsStmt->fetchAll(PDO::FETCH_ASSOC));
if ($resolutionMonths) {
    $resolutionLookup = array_column($resolutionMonths, 'total', 'month');
    $resolutionStart = new DateTimeImmutable($resolutionMonths[0]['month'] . '-01');
    $resolutionEnd = new DateTimeImmutable(end($resolutionMonths)['month'] . '-01');
    $resolutionMonths = [];
    for ($monthCursor = $resolutionStart; $monthCursor <= $resolutionEnd; $monthCursor = $monthCursor->modify('+1 month')) {
        $monthKey = $monthCursor->format('Y-m');
        $resolutionMonths[] = ['month'=>$monthKey, 'total'=>(int)($resolutionLookup[$monthKey] ?? 0)];
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
    <title><?php echo t('Analytics - Sierra'); ?></title>
    <link href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/material-symbols.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/material-symbols.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard-loading.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/dashboard-loading.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/export-print.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/export-print.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/leaflet/leaflet.css" />
    <script src="<?php echo BASE_URL; ?>assets/vendor/leaflet/leaflet.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/leaflet-stub.js"></script>
    <!-- Network hints for slow connections (map tiles / reverse geocoding) -->
    <link rel="dns-prefetch" href="https://tile.openstreetmap.org">
    <link rel="preconnect" href="https://tile.openstreetmap.org" crossorigin>
    <link rel="preconnect" href="https://tile.openstreetmap.appspot.com" crossorigin>
    <link rel="dns-prefetch" href="https://nominatim.openstreetmap.org">
    <link rel="dns-prefetch" href="https://photon.komoot.io">
    <script src="<?php echo BASE_URL; ?>assets/js/map-layers.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/map-layers.js'); ?>"></script>
    <!-- Chart.js -->
    <script src="<?php echo BASE_URL; ?>assets/vendor/chart/chart.umd.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/chart-stub.js"></script>
    <script>if (window.Chart && Chart.defaults && Chart.defaults.font) Chart.defaults.font.family = 'Manrope, sans-serif';</script>
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

        /* KPI widget grid - 4-across on desktop, 2 columns on tablets/phones, 1 column on tiny screens */
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
        @media (max-width: 1024px) {
            .analytics-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.85rem;
            }
        }
        @media (max-width: 560px) {
            .analytics-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.6rem;
                margin-bottom: 1rem;
            }
            .analytics-kpi-box { padding: 0.6rem 0.65rem; }
            .analytics-kpi-box .w-10 { display: none; }
            .analytics-kpi-box .uppercase { font-size: 0.58rem; letter-spacing: 0.03em; }
            .analytics-kpi-box .text-2xl { font-size: 1.05rem; }
            .analytics-kpi-box .text-base { font-size: 0.85rem; }
            .analytics-kpi-box [class~="mt-1"], .analytics-kpi-box [class~="mt-1.5"] { font-size: 0.55rem; }
        }
        @media (max-width: 400px) {
            .analytics-kpi-grid { grid-template-columns: 1fr; gap: 0.5rem; }
        }

        /* Map container */
        #map-container {
            background: white;
            border-radius: 1rem;
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
        @media (max-width: 768px) { #map { height: 300px; } }
        @media (max-width: 400px) { #map { height: 260px; } }

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
            border-radius: 8px;
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
            border-radius: 1.5rem;
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
        .rec-stack { display: none; }

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
            align-items: center;
            gap: 0.75rem 1rem;
            margin-bottom: 0.9rem;
        }
        .map-title-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.9rem;
            flex-wrap: nowrap;
            width: 100%;
            min-width: 0;
        }
        .map-head-tools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
            width: 100%;
            min-width: 0;
        }
        /* Desktop: keep the mode toggle and the timeframe picker on one row */
        @media (min-width: 769px) {
            .map-head-tools {
                flex-wrap: nowrap;
            }
            #mapToggle {
                flex-shrink: 0;
            }
            #mapToggle,
            #timeframeToggle {
                flex-wrap: nowrap;
            }
            #mapToggle button,
            #timeframeToggle button {
                white-space: nowrap;
            }
            #timeframeToggle {
                min-width: 0;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            #timeframeToggle::-webkit-scrollbar { display: none; }
            #customRangeBox {
                width: 100%;
                flex-wrap: wrap;
            }
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

        /* Fullscreen toggle button (sits beside the date pickers) */
        .map-fullscreen-btn {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }
        .map-fullscreen-btn i { font-size: 0.8rem; }
        .map-fullscreen-btn:hover {
            color: #10A37F;
            border-color: #10A37F;
            background: #f0fdf9;
        }

        /* Map card in fullscreen mode */
        #map-container.map-fullscreen {
            position: fixed;
            inset: 0;
            z-index: 9999;
            margin: 0;
            width: 100vw;
            height: 100vh;
            border-radius: 0;
            display: flex;
            flex-direction: column;
        }
        #map-container.map-fullscreen .map-head { flex-shrink: 0; }
        #map-container.map-fullscreen #map { flex: 1 1 auto; height: auto; min-height: 0; }
        #map-container.map-fullscreen .map-legend-inline,
        #map-container.map-fullscreen > .flex,
        #map-container.map-fullscreen > p { display: none; }

        /* Responsive tweaks */
        @media (max-width: 768px) {
            .kpi-card .kpi-value { font-size: 1.5rem; }
            .map-toggle button { padding: 0.3rem 0.8rem; font-size: 0.7rem; min-height: 44px; }
            /* Map card compacts for tablets/phones */
            #map-container { padding: 0.85rem; }
            .map-title-wrap { width: 100%; justify-content: space-between; }
            #mapToggle { flex-wrap: nowrap; }
            #mapToggle button { flex: 1; padding: 0.35rem 0.5rem; font-size: 0.72rem; min-width: 44px; }
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
            #timeframeToggle button { flex-shrink: 0; white-space: nowrap; min-height: 44px; }
            #customRangeBox { width: 100%; flex-wrap: wrap; }
            #customRangeBox input { flex: 1 1 40%; min-width: 0; min-height: 44px; }
            /* Floating overlay stays compact */
            .map-overlay { top: 0.5rem; right: 0.5rem; }
            #categoryFilterWrap { max-width: 170px; }
            #categoryFilterBtn { padding: 0.35rem 0.75rem; font-size: 0.75rem; min-height: 44px; }
            #categoryFilterMenu { width: 230px; right: 0; left: auto; }
            /* Keep the map canvas unobstructed: legend moves below the map */
            .map-overlay .map-legend { display: none; }
            .map-legend-inline { display: flex; margin-top: 0.6rem; }
            /* Charts: keep thumb-friendly heights on small screens */
            .chart-container { height: 200px; }
            /* Touch-friendly toolbutton sizes on mobile */
            .map-fullscreen-btn { width: 44px; height: 44px; }
            .rec-info-btn { width: 44px; height: 44px; }
            #seasonalSeveritySelect,
            #seasonalPeriodSelect { min-height: 44px; }
        }
        @media (max-width: 480px) {
            #map { height: 300px; }
            .chart-container { height: 180px; }
            .map-title-wrap h2 { font-size: 1.05rem; }
            .map-head { gap: 0.7rem; }
            .map-legend span { font-size: 0.7rem; }
            #customRangeBox input { flex: 1 1 100%; }
        }
        /* Leaderboard table -> horizontal swipe on tablet, stacked cards on phones */
        .leaderboard-scroll { -webkit-overflow-scrolling: touch; }
        /* Mobile: show only the top 3 rows until "View all" is tapped */
        .lb-view-all { display: none; }
        @media (max-width: 767px) {
            body .leaderboard-scroll:not(.lb-open) .lb-extra { display: none; }
            body .lb-view-all {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                width: 100%;
                margin-top: 10px;
                padding: 10px 12px;
                background: #F0FDF9;
                color: #0D8568;
                border: 1px solid rgba(16, 163, 127, 0.2);
                border-radius: 12px;
                font-size: 0.8rem;
                font-weight: 700;
                cursor: pointer;
            }
            body .lb-view-all i { transition: transform .2s ease; }
        }
        @media (max-width: 560px) {
            .leaderboard-scroll { overflow: visible; }
            .leaderboard-table thead { display: none; }
            .leaderboard-table,
            .leaderboard-table tbody,
            .leaderboard-table tr,
            .leaderboard-table td { display: block; width: 100%; }
            .leaderboard-table tr {
                background: #ffffff;
                border: 1px solid #eef2f0;
                border-radius: 12px;
                padding: 0.55rem 0.9rem;
                margin-bottom: 0.6rem;
            }
            .leaderboard-table td {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                padding: 0.28rem 0;
                border: none;
                text-align: right;
            }
            .leaderboard-table td::before {
                content: attr(data-label);
                font-size: 0.62rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #8aa38a;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .leaderboard-table td .flex.items-center.gap-2 { min-width: 0; }
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/dashboard.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard-hero.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/dashboard-hero.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard-analytics.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/dashboard-analytics.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/menro-dashboard.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/menro-dashboard.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/menro-pages.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/menro-pages.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/branded-dropdowns.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/branded-dropdowns.css'); ?>">
</head>
<body class="dashboard-page menro-analytics-page">
<?php $dashboard_loading_initial = true; include BASE_PATH . 'views/shared/dashboard_loading.php'; ?>

<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>

<div id="main-content" tabindex="-1" class="lg:ml-72 min-h-screen" role="main">
    <div class="main-container max-w-7xl mx-auto">

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

        $dash_category_options = ['0' => 'All Hazards'];
        foreach ($categories as $dashCategory) {
            $dash_category_options[(string)$dashCategory['id']] = $dashCategory['name'];
        }
        $ft = [
            'search_id'          => 'dashSearchInput',
            'search_value'       => $f_search,
            'search_placeholder' => 'Search reports by title or description...',
            'show_search'        => true,
            'show_active_row'    => false,
            'compact_breakpoint' => 1199,
            'more_icon'          => 'fa-sliders-h',
            'inline_selects'     => [
                ['id' => 'dashStatusFilter', 'value' => $f_status, 'min_width' => '150px', 'options' => $dash_status_options],
                ['id' => 'dashRiskFilter', 'label' => 'Severity', 'value' => $f_risk, 'min_width' => '150px', 'options' => $dash_risk_options],
                ['id' => 'dashBarangayFilter', 'value' => $f_barangay, 'min_width' => '170px', 'options' => $dash_barangay_options],
                ['id' => 'dashCategoryFilter', 'label' => 'Hazard Category', 'value' => $f_category, 'options' => $dash_category_options],
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
            'active_filters'     => (int)(($f_status !== 'all' ? 1 : 0) + ($f_risk !== 'all' ? 1 : 0) + ($f_barangay !== '' ? 1 : 0) + ($f_category > 0 ? 1 : 0) + ($f_search !== '' ? 1 : 0) + ($analytics_date_from ? 1 : 0) + ($analytics_date_to ? 1 : 0) + ($dash_date_preset !== '' ? 1 : 0)),
            'chips'              => [],
            'chips_clear_all'    => false,
            'callback'           => 'applyDashboardFilters',
        ];
        ?>
        <div id="dashHeaderExtras" class="dash-header-extras">
            <div class="dashboard-toolbar-row dash-topbar">
                <div class="dashboard-toolbar-filters">
                    <?php include BASE_PATH . 'views/shared/report_filter_toolbar.php'; ?>
                </div>
            </div>
        </div>

        <div class="menro-stat-grid analytics-summary-grid">
            <div class="menro-stat-card menro-stat-total"><i class="fas fa-file-lines" aria-hidden="true"></i><span><?php echo t('Total Reports'); ?></span><strong><?php echo number_format($analyticsTotal); ?></strong><small><?php echo t('In selected period'); ?></small></div>
            <div class="menro-stat-card menro-stat-response"><i class="fas fa-stopwatch" aria-hidden="true"></i><span><?php echo t('Average Response Time'); ?></span><strong><?php echo $avgResolutionDaysAllTime; ?> <small><?php echo t('days'); ?></small></strong><small><?php echo t('Created to resolved'); ?></small></div>
            <div class="menro-stat-card menro-stat-resolved"><i class="fas fa-circle-check" aria-hidden="true"></i><span><?php echo t('Resolution Rate'); ?></span><strong><?php echo $analyticsResolutionRate; ?>%</strong><small><?php echo t('Resolved reports'); ?></small></div>
            <div class="menro-stat-card menro-stat-urgent"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><span><?php echo t('Urgent Report Rate'); ?></span><strong><?php echo $analyticsUrgentRate; ?>%</strong><small><?php echo t('High or critical risk'); ?></small></div>
        </div>

        <div class="dash-head dash-title-row">
            <div class="dash-title-actions table-section-actions" data-filter-actions>
                <span class="dash-date"><i class="far fa-calendar" aria-hidden="true"></i><?php echo date('D, d F Y'); ?></span>
                <div class="export-dropdown" id="exportDropdownWrap">
                    <button onclick="toggleExportDropdown()" id="exportDropBtn" class="btn-export-trigger">
                        <i class="fas fa-file-export"></i>
                        <span><?php echo t('Export'); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div id="exportDropdown" class="export-dropdown-menu" style="width:280px;">
                        <div class="export-dropdown-header">
                            <p><?php echo t('Export Analytics'); ?></p>
                            <p class="sub"><?php echo t('Download the current analytics'); ?></p>
                        </div>
                        <button class="export-dropdown-item" onclick="exportAnalyticsPdf()">
                            <div class="item-icon" style="background:#E8F5F0; color:#10A37F;"><i class="fas fa-file-pdf"></i></div>
                            <div class="item-text">
                                <div class="item-title"><?php echo t('Export as PDF'); ?></div>
                                <div class="item-desc"><?php echo t('Preview and save as PDF'); ?></div>
                            </div>
                        </button>
                        <button class="export-dropdown-item" onclick="exportAnalyticsCsv()">
                            <div class="item-icon" style="background:#DBEAFE; color:#2563EB;"><i class="fas fa-file-csv"></i></div>
                            <div class="item-text">
                                <div class="item-title"><?php echo t('Export as CSV'); ?></div>
                                <div class="item-desc"><?php echo t('Download spreadsheet of analytics'); ?></div>
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
            <span><?php echo t('Analytics filtered:'); ?>
                <?php if ($analytics_date_from && $analytics_date_to): ?>
                    <strong><?php echo htmlspecialchars($analytics_date_from); ?></strong> to <strong><?php echo htmlspecialchars($analytics_date_to); ?></strong>
                <?php elseif ($analytics_date_from): ?>
                    from <strong><?php echo htmlspecialchars($analytics_date_from); ?></strong>
                <?php else: ?>
                    up to <strong><?php echo htmlspecialchars($analytics_date_to); ?></strong>
                <?php endif; ?>
            </span>
            <a href="<?php echo BASE_URL; ?>index.php?page=analytics" class="ml-auto flex items-center gap-1 text-xs text-gray-500 hover:text-red-500 transition">
                <i class="fas fa-times"></i> <?php echo t('Clear Filter'); ?>
            </a>
        </div>
        <?php endif; ?>


        <div class="bento analytics-sections">
<h2 class="analytics-section-heading b-full"><?php echo t('Environmental Situation'); ?></h2>
<div class="analytics-grid analytics-overview-grid">
<div id="map-container" class="b-wide analytics-map">
            <div class="map-head">
                <div class="map-title-wrap">
                    <h2 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                        <i class="fas fa-map-marked-alt text-[#10A37F]"></i>
                        <?php echo t('Environmental Hazard Map'); ?>
                    </h2>
                    <a href="<?php echo BASE_URL; ?>index.php?page=map" title="<?php echo t('View Full Map'); ?>" class="map-fullscreen-btn"><i class="fas fa-up-right-and-down-left-from-center"></i><span><?php echo t('View Full Map'); ?></span></a>
                </div>

                <!-- Mode toggle + Timeframe Segmented Control (same row) -->
                <div class="map-head-tools">
                    <div class="map-toggle" id="mapToggle">
                        <button class="active" data-mode="active"><i class="fas fa-map-pin" aria-hidden="true"></i><span><?php echo t('Active Hazards'); ?></span></button>
                        <button data-mode="historical"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i><span><?php echo t('Historical Trends'); ?></span></button>
                    </div>
                    <div class="map-toggle" id="timeframeToggle">
                        <button data-range="today"><?php echo t('Today'); ?></button>
                        <button data-range="week"><?php echo t('This Week'); ?></button>
                        <button data-range="month"><?php echo t('This Month'); ?></button>
                        <button data-range="year"><?php echo t('This Year'); ?></button>
                        <button data-range="custom"><?php echo t('Custom'); ?></button>
                        <button class="active" data-range="all"><?php echo t('All Time'); ?></button>
                    </div>
                </div>

                <div id="customRangeBox" class="hidden items-center gap-2">
                    <input type="date" id="rangeFrom" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-700 bg-white focus:outline-none focus:border-[#10A37F]" title="<?php echo t('Start date'); ?>">
                    <span class="text-xs text-gray-400"><?php echo t('to'); ?></span>
                    <input type="date" id="rangeTo" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-700 bg-white focus:outline-none focus:border-[#10A37F]" title="<?php echo t('End date'); ?>">
                    <button onclick="applyAnalyticsDateFilter()" class="bg-[#10A37F] text-white text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-[#0D8568] transition flex items-center gap-1" title="<?php echo t('Reload page with selected date range to update all KPIs and charts'); ?>">
                        <i class="fas fa-sync-alt"></i> <?php echo t('Apply to Analytics'); ?>
                    </button>
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
                            <span id="categoryFilterLabel"><?php echo t('All Categories'); ?></span>
                            <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                        </button>
                        <div id="categoryFilterMenu" class="hidden absolute z-[1100] mt-2 w-64 bg-white rounded-xl border border-gray-200 shadow-lg p-3 right-0">
                            <div class="flex justify-between items-center mb-2 pb-2 border-b border-gray-100">
                                <span class="text-xs font-bold text-gray-500 uppercase tracking-wide"><?php echo t('Hazard Categories'); ?></span>
                                <div class="flex gap-2">
                                    <button type="button" id="catSelectAll" class="text-xs text-[#10A37F] font-semibold hover:underline"><?php echo t('All'); ?></button>
                                    <button type="button" id="catSelectNone" class="text-xs text-gray-400 font-semibold hover:underline"><?php echo t('None'); ?></button>
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
                                <p class="text-xs text-gray-400 px-1"><?php echo t('No categories found.'); ?></p>
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
                <span id="barangayFilterChip" class="hidden items-center gap-1 px-2 py-0.5 rounded-full bg-[#10A37F]/10 border border-[#10A37F]/30 text-xs font-semibold text-[#0D8568]">
                    <i class="fas fa-map-pin"></i>
                    <span id="barangayFilterLabel"></span>
                    <button type="button" onclick="clearBarangayFilter()" class="ml-1 hover:text-red-600" aria-label="<?php echo t('Clear barangay filter'); ?>"><i class="fas fa-times"></i></button>
                </span>
            </div>
        </div>
<section class="chart-card b-hero hero-kpi analytics-hotspots">
                <div class="chart-head"><div class="chart-title"><i class="fas fa-map-pin"></i><?php echo t('Active Hotspots'); ?></div></div>
                <div class="hotspot-summary">
                    <div class="hero-num"><?php echo $activeHotspots; ?></div>
                    <p class="hero-sub"><?php echo t('Unique clusters with density > 0'); ?></p>
                </div>
                <div class="hero-list-head"><?php echo t('Key Indicators'); ?></div>
                <dl class="indicator-list">
                    <div class="indicator-row">
                        <dt><i class="fas fa-chart-line" aria-hidden="true"></i><?php echo t('Avg Municipal Risk'); ?></dt>
                        <dd><?php echo $avgRisk; ?><small><?php echo t('Out of 20 severity score'); ?></small></dd>
                    </div>
                    <div class="indicator-row<?php echo $criticalCount > 0 ? ' indicator-row--critical' : ''; ?>">
                        <dt><i class="fas fa-exclamation-triangle" aria-hidden="true"></i><?php echo t('Critical Escalations'); ?></dt>
                        <dd><?php echo $criticalCount; ?><small><?php echo t('Require immediate action'); ?></small></dd>
                    </div>
                    <div class="indicator-row">
                        <dt><i class="fas fa-check-circle" aria-hidden="true"></i><?php echo t('Resolved Hotspots'); ?></dt>
                        <dd><?php echo $resolvedHotspots; ?><small><?php echo t('Clusters resolved this year'); ?></small></dd>
                    </div>
                    <div class="indicator-row">
                        <dt><i class="fas fa-list-alt" aria-hidden="true"></i><?php echo t('Active Reports'); ?></dt>
                        <dd><?php echo count($activeReports); ?><small><?php echo t('Open reports on the map'); ?></small></dd>
                    </div>
                </dl>
            </section>
</div>
<div class="analytics-grid analytics-environment-grid">
<div class="chart-card b-narrow analytics-severity">
                <div class="chart-head">
                    <div class="chart-title"><i class="fas fa-chart-pie text-[#10A37F] mr-2"></i><?php echo t('Severity Distribution'); ?></div>
                    <button type="button" class="rec-info-btn" data-rec="rec-severity" title="<?php echo t('Show recommendation'); ?>" aria-label="<?php echo t('Show recommendation'); ?>"><i class="fas fa-info"></i></button>
                </div>
                <div class="severity-layout">
                    <div class="severity-chart-wrap">
                        <canvas id="severityChart" role="img" aria-label="<?php echo t('Active reports grouped by severity'); ?>"></canvas>
                        <div class="severity-chart-total" aria-hidden="true"><strong><?php echo $severityTotal; ?></strong><span><?php echo t('Active reports'); ?></span></div>
                    </div>
                    <div class="severity-breakdown" aria-label="<?php echo t('Severity percentages'); ?>">
                        <?php foreach ($severityTiers as $severityKey => $severityTier): ?>
                        <div class="severity-stat severity-<?php echo htmlspecialchars($severityKey, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="severity-dot" aria-hidden="true"></span>
                            <span class="severity-stat-name" title="<?php echo htmlspecialchars($severityTier['label'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($severityTier['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="severity-stat-value"><?php echo $severityTier['count']; ?> · <?php echo number_format($severityTier['percentage'], 1); ?>%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="rec-stack" data-rec="rec-severity">
                <?php if ($criticalAlert): ?>
                <div class="rec-box rec-critical mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong><?php echo t('Recommendation:'); ?></strong> <?php echo t('Too many critical cases. Some active reports are critical and need immediate attention. Send help to the affected areas now.'); ?>
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
                        <strong class="text-xs uppercase tracking-wide"><?php echo t('Hazard Profile Analysis'); ?></strong>
                    </div>
                    <p class="text-xs leading-relaxed">
                        <?php
                        // Plain-language hazard profile: dominant risk level + practical action (no statistics).
                        if ($severityTotal === 0) {
                            echo t('No active reports are currently classified by severity. New reports will be rated automatically as they come in.');
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
<div class="chart-card b-wide analytics-hazard-categories">
                <div class="chart-head"><div class="chart-title"><i class="fas fa-leaf text-[#10A37F] mr-2"></i><?php echo t('Hazard Category Analysis'); ?></div></div>
                <?php if (!$hazardAnalysis): ?><p class="text-sm text-gray-400"><?php echo t('No reports match these filters.'); ?></p><?php endif; ?>
                <?php foreach (array_slice($hazardAnalysis, 0, 8) as $hazardRow): ?>
                <div class="analytics-bar-row"><span><?php echo htmlspecialchars($hazardRow['name'], ENT_QUOTES, 'UTF-8'); ?></span><div class="analytics-bar-track"><i style="width:<?php echo $analyticsTotal ? round(100 * (int)$hazardRow['total'] / $analyticsTotal, 1) : 0; ?>%"></i></div><strong><?php echo (int)$hazardRow['total']; ?></strong></div>
                <?php endforeach; ?>
            </div>
</div>
<h2 class="analytics-section-heading b-full analytics-response-heading"><?php echo t('Response Performance'); ?></h2>
<div class="analytics-grid analytics-response-grid">
<div class="chart-card b-wide analytics-barangay-performance">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
                <div class="chart-title mb-0"><i class="fas fa-trophy text-[#10A37F] mr-2"></i><?php echo t('Barangay Performance Leaderboard'); ?></div>
                <span class="flex items-center gap-2 text-xs text-gray-400">
                    <?php echo t('Ranked by resolution rate · accountability &amp; follow-up tool'); ?>
                    <button type="button" class="rec-info-btn" data-rec="rec-leaderboard" title="<?php echo t('Show recommendation'); ?>" aria-label="<?php echo t('Show recommendation'); ?>"><i class="fas fa-info"></i></button>
                </span>
            </div>
            <?php if (empty($barangayLeaderboard)): ?>
                <p class="text-sm text-gray-400 py-6 text-center"><?php echo t('No barangay data available yet.'); ?></p>
            <?php else: ?>
            <div class="overflow-x-auto leaderboard-scroll">
                <table class="w-full text-sm leaderboard-table">
                    <thead>
                        <tr class="text-left text-xs text-gray-400 uppercase tracking-wide border-b border-gray-100">
                            <th class="py-2 pr-2"><?php echo t('Rank'); ?></th>
                            <th class="py-2 pr-2"><?php echo t('Barangay'); ?></th>
                            <th class="py-2 pr-2 text-right"><?php echo t('Assigned'); ?></th>
                            <th class="py-2 pr-2 text-right"><?php echo t('Resolved'); ?></th>
                            <th class="py-2 pr-2"><?php echo t('Resolution Rate'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($barangayLeaderboard as $i => $brgy):
                            $rank = $i + 1;
                            $rate = $brgy['resolution_rate'];
                            $barColor = $rate >= 75 ? '#10B981' : ($rate >= 50 ? '#F59E0B' : '#EF4444');
                            $rowFlag = $rate < 50 ? 'bg-red-50/50' : '';
                        ?>
                        <tr class="border-b border-gray-50 <?php echo $rowFlag; ?><?php echo $i >= 3 ? ' lb-extra' : ''; ?>">
                            <td class="py-2 pr-2 font-bold text-gray-500" data-label="Rank">
                                <?php if ($rank === 1): ?><i class="fas fa-medal text-yellow-400"></i>
                                <?php elseif ($rank === 2): ?><i class="fas fa-medal text-gray-400"></i>
                                <?php elseif ($rank === 3): ?><i class="fas fa-medal text-amber-600"></i>
                                <?php else: echo '#' . $rank; endif; ?>
                            </td>
                            <td class="py-2 pr-2 font-semibold text-gray-800" data-label="Barangay"><?php echo htmlspecialchars($brgy['barangay_name']); ?></td>
                            <td class="py-2 pr-2 text-right text-gray-600" data-label="Assigned"><?php echo $brgy['total_assigned']; ?></td>
                            <td class="py-2 pr-2 text-right text-gray-600" data-label="Resolved"><?php echo $brgy['total_resolved']; ?></td>
                            <td class="py-2 pr-2" data-label="Resolution Rate">
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
            <button type="button" class="lb-view-all" onclick="toggleLeaderboard(this)">
                <span><?php echo t('View all barangays'); ?></span> <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </button>
            <script>
            window.toggleLeaderboard = function (btn) {
                var card = btn.closest('.chart-card');
                var wrap = card ? card.querySelector('.leaderboard-scroll') : null;
                if (!wrap) return;
                var open = wrap.classList.toggle('lb-open');
                var span = btn.querySelector('span');
                if (span) span.textContent = open ? <?php echo json_encode(t('Show less')); ?> : <?php echo json_encode(t('View all barangays')); ?>;
                var icon = btn.querySelector('i');
                if (icon) icon.style.transform = open ? 'rotate(180deg)' : '';
            };
            </script>
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
                <strong><?php echo t('Recommendation:'); ?></strong>
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
<div class="analytics-card-stack">
<section class="chart-card kpi-sm analytics-response-summary">
                <div class="chart-head" style="margin-bottom:.5rem;">
                    <div class="chart-title"><i class="fas fa-stopwatch"></i><?php echo t('Avg Response Time'); ?></div>
                    <button type="button" class="rec-info-btn" data-rec="rec-response" title="<?php echo t('Show recommendation'); ?>" aria-label="<?php echo t('Show recommendation'); ?>"><i class="fas fa-info"></i></button>
                </div>
                <div>
                    <div class="kpi-num"><?php echo $avgResolutionDaysAllTime; ?><small><?php echo t('days'); ?></small></div>
                    <div class="kpi-foot">
                        <?php if ($resolutionTrend === 'stable'): ?>
                            <span class="chip chip-flat"><i class="fas fa-equals"></i><?php echo t('Stable'); ?></span>
                        <?php else: $rt_worse = ($resolutionTrend === 'worse'); ?>
                            <span class="chip <?php echo $rt_worse ? 'chip-bad' : 'chip-good'; ?>"><i class="fas fa-arrow-<?php echo $rt_worse ? 'up' : 'down'; ?>"></i><?php echo abs($resolutionDelta); ?> <?php echo t('days'); ?></span>
                        <?php endif; ?>
                        <span><?php echo t('from last month'); ?></span>
                    </div>
                </div>
                <?php
                    $sla_breached = $avgResolutionHoursThisMonth > (float)$kpi_sla_response_hours;
                    if ($sla_breached) {
                        $rc_class = 'rec-critical';
                        $rc_msg = 'The municipality is behind schedule on clearing hazard reports' . ($slowestBarangay ? ', with the slowest response in Brgy. ' . htmlspecialchars($slowestBarangay['barangay_name']) : '') . '. Send additional cleanup crews or equipment to that area.';
                    } elseif ($resolutionTrend === 'worse') {
                        $rc_class = 'rec-critical';
                        $rc_msg = t('Response times currently meet the target standard, but response has been slowing lately. Keep an eye on crew levels before a backlog builds up.');
                    } else {
                        $rc_class = 'rec-low';
                        $rc_msg = t('Municipal response times comply with the target standard. Keep current crews and monitoring in place.');
                    }
                ?>
                <div class="rec-stack" data-rec="rec-response">
                    <div class="rec-box <?php echo $rc_class; ?>"><i class="fas fa-lightbulb mr-2"></i><strong><?php echo t('Recommendation:'); ?></strong> <?php echo $rc_msg; ?></div>
                </div>
            </section>
<div class="chart-card b-narrow analytics-response-time">
                <div class="chart-head"><div class="chart-title"><i class="fas fa-stopwatch text-[#10A37F] mr-2"></i><?php echo t('Response Time Analysis'); ?></div></div>
                <p class="analytics-big-number"><?php echo $avgResolutionDaysThisMonth; ?> <small><?php echo t('days this month'); ?></small></p>
                <p class="text-sm text-gray-500"><?php echo t('Average time from report submission to resolution.'); ?></p>
                <p class="text-sm text-gray-500"><?php echo t('Previous month:'); ?> <?php echo $avgResolutionDaysLastMonth; ?> <?php echo t('days'); ?></p>
            </div>
</div>
</div>
<div class="chart-card b-wide analytics-resolution-trends">
                <div class="chart-head"><div class="chart-title"><i class="fas fa-chart-line text-[#10A37F] mr-2"></i><?php echo t('Resolution Trends'); ?></div></div>
                <?php if (!$resolutionMonths): ?><p class="text-sm text-gray-400"><?php echo t('No resolved reports in this period.'); ?></p><?php else: ?>
                <div class="resolution-trend-summary"><strong><?php echo array_sum(array_column($resolutionMonths, 'total')); ?></strong><span>resolved across the months shown</span></div>
                <div class="resolution-trend-chart"><canvas id="resolutionTrendChart" role="img" aria-label="Monthly resolved reports"></canvas></div>
                <?php endif; ?>
            </div>
<h2 class="analytics-section-heading b-full analytics-patterns-heading"><?php echo t('Reporting Patterns'); ?></h2>
<section class="chart-card kpi-sm analytics-demographics">
                <div class="chart-head" style="margin-bottom:.5rem;">
                    <div class="chart-title"><i class="fas fa-users"></i><?php echo t('Reporter Demographics'); ?></div>
                    <button type="button" class="rec-info-btn" data-rec="rec-demographics" title="<?php echo t('Show recommendation'); ?>" aria-label="<?php echo t('Show recommendation'); ?>"><i class="fas fa-info"></i></button>
                </div>
                <?php if (!$demographicsAvailable || $demographicsTotal === 0): ?>
                    <p class="text-sm text-gray-400 py-4"><?php echo t('Demographic data not available yet.'); ?></p>
                <?php else: ?>
                <?php include BASE_PATH . 'views/shared/reporter_demographics.php'; ?>
                <?php
                    $lowGroup = null; $lowGroupPct = null;
                    if ($residentPct < (float)$kpi_demographic_threshold) { $lowGroup = 'Residents'; $lowGroupPct = $residentPct; }
                    if ($nonResidentPct < (float)$kpi_demographic_threshold && $nonResidentPct <= $residentPct) { $lowGroup = 'Non-Residents'; $lowGroupPct = $nonResidentPct; }
                ?>
                <?php if ($lowGroup): ?>
                <div class="rec-stack" data-rec="rec-demographics">
                    <div class="rec-box rec-medium"><i class="fas fa-lightbulb mr-2"></i><strong><?php echo t('Recommendation:'); ?></strong> Only <?php echo $lowGroupPct; ?>% of reports come from <?php echo $lowGroup; ?>. Run an info drive to encourage them to report.</div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </section>
<div class="analytics-grid analytics-patterns-grid">
<div class="chart-card b-narrow analytics-peak">
                <div class="chart-head">
                    <div class="chart-title"><i class="fas fa-clock text-[#10A37F] mr-2"></i><?php echo t('Peak Reporting Hours &amp; Days'); ?></div>
                    <button type="button" class="rec-info-btn" data-rec="rec-peak" title="<?php echo t('Show recommendation'); ?>" aria-label="<?php echo t('Show recommendation'); ?>"><i class="fas fa-info"></i></button>
                </div>
                <div class="chart-container">
                    <canvas id="peakDayChart"></canvas>
                </div>
                <div class="rec-stack" data-rec="rec-peak">
                <div class="rec-box rec-medium mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong><?php echo t('Recommendation:'); ?></strong>
                    <?php if ($peakDayTotal > 0 && $peakDayPlain !== 'N/A'): ?>
                        Most reports are filed on <strong><?php echo $peakDayPlain; ?>s</strong>, mostly <?php echo $peakTimePlain; ?>. Prepare your morning response crews to process new submissions first thing the following day.
                    <?php else: ?>
                        Not enough report data yet to identify a peak reporting window.                    <?php endif; ?>
                </div>
                </div><!-- /rec-stack -->
            </div>
<div class="chart-card b-wide analytics-seasonal">
                <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                    <div class="chart-title"><i class="fas fa-chart-line text-[#10A37F] mr-2"></i><?php echo t('Seasonal Hazard Trends'); ?></div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="rec-info-btn" data-rec="rec-seasonal" title="<?php echo t('Show recommendation'); ?>" aria-label="<?php echo t('Show recommendation'); ?>"><i class="fas fa-info"></i></button>
                        <select id="seasonalSeveritySelect" class="text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:border-[#10A37F]">
                            <option value="all"><?php echo t('All Severities'); ?></option>
                            <option value="low"><?php echo t('Low'); ?></option>
                            <option value="medium"><?php echo t('Medium'); ?></option>
                            <option value="high"><?php echo t('High'); ?></option>
                            <option value="critical"><?php echo t('Critical'); ?></option>
                        </select>
                        <select id="seasonalPeriodSelect" class="text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:border-[#10A37F]">
                            <option value="3"><?php echo t('Last 3 Months'); ?></option>
                            <option value="6"><?php echo t('Last 6 Months'); ?></option>
                            <option value="12" selected><?php echo t('Last 12 Months'); ?></option>
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
                    <strong><?php echo t('Recommendation:'); ?></strong> <?php echo htmlspecialchars($surgeAlert['category']); ?> reports jumped by <?php echo $surgeAlert['pct']; ?>% this month (from <?php echo $surgeAlert['previous']; ?> to <?php echo $surgeAlert['current']; ?>). Put more resources into <?php echo htmlspecialchars($surgeAlert['category']); ?>.
                </div>
                <?php endif; ?>
                </div><!-- /rec-stack -->
            </div>
</div>
<h2 class="analytics-section-heading b-full analytics-recurring-heading"><?php echo t('Recurring Problems'); ?></h2>
<div class="analytics-grid analytics-recurring-grid">
<div class="chart-card b-full analytics-repeat-locations">
                <div class="chart-head">
                    <div class="chart-title"><i class="fas fa-map-marker-alt text-[#10A37F] mr-2"></i><?php echo t('Repeat-Reported Locations'); ?></div>
                    <button type="button" class="rec-info-btn" data-rec="rec-repeat" title="<?php echo t('Show recommendation'); ?>" aria-label="<?php echo t('Show recommendation'); ?>"><i class="fas fa-info"></i></button>
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
                    <strong><?php echo t('Recommendation:'); ?></strong>
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
<div class="chart-card b-full analytics-recurring-categories">
                <div class="chart-head"><div class="chart-title"><i class="fas fa-repeat text-[#10A37F] mr-2"></i><?php echo t('Recurring Hazard Categories'); ?></div></div>
                <?php $recurringCategories = array_filter($hazardAnalysis, static fn($row) => (int)$row['total'] >= 2); ?>
                <?php if (!$recurringCategories): ?><p class="text-sm text-gray-400"><?php echo t('No hazard category has multiple reports in this period.'); ?></p><?php endif; ?>
                <div class="analytics-category-chips"><?php foreach ($recurringCategories as $recurringCategory): ?><span><?php echo htmlspecialchars($recurringCategory['name'], ENT_QUOTES, 'UTF-8'); ?> <strong><?php echo (int)$recurringCategory['total']; ?></strong></span><?php endforeach; ?></div>
            </div>
</div>
</div>

    </div>
</div>

<?php include BASE_PATH . 'views/shared/decision_support_popover.php'; ?>

<!-- ============================================================ -->
<!-- ENHANCED DRILL-DOWN PANEL -->
<!-- ============================================================ -->
<?php include BASE_PATH . 'views/shared/map_report_panel.php'; ?>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
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
const demographicsData = <?php echo json_encode([$demographics['resident'], $demographics['non_resident'], $demographics['unknown']]); ?>;
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

    MapLayers.addControl(map, { position: 'bottomright' });

    // Draw one clickable polygon per barangay (from the GeoJSON folder)
    addBarangayLayers();

    if (boundaryData && boundaryData.features) MapLayers.addBoundary(map, boundaryData, {interactive:false});

    // Load initial data
    loadMapData('active');
}

// Render all barangay boundaries as an interactive polygon layer.
function addBarangayLayers() {
    if (!barangayData || !barangayData.features) return;

    // White casing under the dashed green stroke so the boundary reads as
    // alternating green/white dashes.
    barangayLayer = MapLayers.addBoundary(map, barangayData, {
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
    });
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
        const categoryOk = selectedCategories.size === allCategories.length
            || selectedCategories.has(String(report.category_id));
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

// Map custom-range control uses the same server-rendered analytics scope.
function applyAnalyticsDateFilter() {
    const from = document.getElementById('rangeFrom');
    const to = document.getElementById('rangeTo');
    const url = new URL(window.location.href);
    if (from && from.value) url.searchParams.set('date_from', from.value);
    else url.searchParams.delete('date_from');
    if (to && to.value) url.searchParams.set('date_to', to.value);
    else url.searchParams.delete('date_to');
    url.searchParams.delete('date_preset');
    window.location.href = url.toString();
}

// Shared header filter callback. Reloading keeps every server-rendered KPI,
// chart, map, and export on the same filter scope.
function applyDashboardFilters() {
    const search = document.getElementById('dashSearchInput');
    const status = document.getElementById('dashStatusFilter');
    const risk = document.getElementById('dashRiskFilter');
    const barangay = document.getElementById('dashBarangayFilter');
    const category = document.getElementById('dashCategoryFilter');
    const dateFrom = document.getElementById('ftRangeFrom');
    const dateTo = document.getElementById('ftRangeTo');
    const presetElement = document.getElementById('ftRangePreset');
    const url = new URL(window.location.href);
    const set = function (key, value) {
        if (value && value !== 'all') url.searchParams.set(key, String(value).trim());
        else url.searchParams.delete(key);
    };

    set('search', search ? search.value : '');
    set('status', status ? status.value : 'all');
    set('risk', risk ? risk.value : 'all');
    set('barangay', barangay ? barangay.value : '');
    set('category', category && category.value !== '0' ? category.value : '');

    let from = dateFrom ? dateFrom.value : '';
    let to = dateTo ? dateTo.value : '';
    let preset = presetElement ? presetElement.value : '';
    if (from || to) preset = '';
    if (preset) {
        const today = new Date();
        const formatDate = function (date) {
            return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        };
        let first = new Date(today);
        if (preset === 'week') first.setDate(today.getDate() - 6);
        else if (preset === 'month') first = new Date(today.getFullYear(), today.getMonth(), 1);
        else if (preset === 'year') first = new Date(today.getFullYear(), 0, 1);
        from = formatDate(first);
        to = formatDate(today);
    }
    set('date_preset', preset);
    set('date_from', from);
    set('date_to', to);
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
    const clusterGroup = SierraMapClusters.layer({
        radiusMeters: mapDefaults.clustering_radius_meters,
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
            const size = Math.min(64, 38 + Math.log2(count) * 6); // larger cluster = more reports
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

        const marker = L.marker([lat, lng], { icon: icon, severityScore: score, reportTitle: report.title })
            .bindPopup(popupContent);

        // On click, open drill-down with the report ID
        marker.on('click', function() {
            openDrillPanel(report.token || report.id);
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

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Shared report details panel keeps Analytics and Map behavior consistent.
function openDrillPanel(reportId) {
    SierraMapReportPanel.open(reportId);
}

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


    seasonalChartInstance = SierraCharts.create('seasonalChart', {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: meta.label,
                data: data,
                borderColor: meta.color,
                backgroundColor: meta.bg,
                tension: 0.35,
                fill: true,
                borderWidth: 2.5,
                pointBackgroundColor: meta.color,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 2.5,
                pointHoverRadius: 5,
                pointHitRadius: 12
            }]
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: { backgroundColor: '#173f2e', padding: 10, cornerRadius: 8, displayColors: false } },
            scales: {
                y: { beginAtZero: true, border: { display: false }, grid: { color: '#eaf1eb' }, ticks: { color: '#718579', font: { size: 10 }, precision: 0 } },
                x: { border: { display: false }, grid: { display: false }, ticks: { color: '#718579', font: { size: 10 } } }
            },
            responsive: true,
            maintainAspectRatio: false
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
    SierraCharts.create('severityChart', {
        type: 'doughnut',
        data: {
            labels: severityLabels,
            datasets: [{
                data: severityData,
                backgroundColor: ['#10B981', '#F59E0B', '#F97316', '#EF4444'],
                borderColor: '#fff',
                borderWidth: 3,
                hoverOffset: 6
            }]
        },
        options: {
            cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#173f2e', padding: 10, cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((sum, value) => sum + Number(value || 0), 0);
                            const pct = total > 0 ? ((Number(context.raw || 0) / total) * 100).toFixed(1) : '0.0';
                            return ' ' + context.label + ': ' + context.raw + ' (' + pct + '%)';
                        }
                    }
                }
            },
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Seasonal Hazard Trends (Line) — customizable via renderSeasonalChart()
    renderSeasonalChart();

    SierraCharts.create('resolutionTrendChart', {
        type:'line',
        data:{labels:<?php echo json_encode(array_map(static fn($r)=>date('M Y',strtotime($r['month'].'-01')),$resolutionMonths)); ?>,
            datasets:[{label:'Resolved',data:<?php echo json_encode(array_column($resolutionMonths,'total')); ?>,
                borderColor:'#0d8568',backgroundColor:'rgba(16,163,127,.09)',fill:true,tension:.3,borderWidth:2.5,pointRadius:3,pointHoverRadius:6}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:'#173f2e',padding:10}},
            scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:'#edf2ee'}},x:{grid:{display:false},ticks:{maxTicksLimit:8,maxRotation:0}}}}
    });
    // Reporter Demographics (Donut)
    if (demographicsAvailable) {
        SierraCharts.create('demographicsChart', {
            type: 'doughnut',
            data: {
                labels: ['Resident', 'Non-Resident', 'Not recorded'],
                datasets: [{
                    data: demographicsData,
                    backgroundColor: ['#10A37F', '#F59E0B', '#94a3b8'],
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverOffset: 4
                }]
            },
            options: {
                cutout: '76%',
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#173f2e', padding: 9, cornerRadius: 8 } },
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    // Peak Reporting Hours & Days (grouped bars: day of week + time-of-day)
    SierraCharts.create('peakDayChart', {
        type: 'bar',
        data: {
            labels: dayLabels,
            datasets: [{
                label: 'Reports by Day',
                data: dayCounts,
                backgroundColor: '#38ad88',
                hoverBackgroundColor: '#0d8568',
                borderRadius: 7,
                maxBarThickness: 32
            }]
        },
        options: {
            plugins: { legend: { display: false }, tooltip: { backgroundColor: '#173f2e', padding: 10, cornerRadius: 8, displayColors: false } },
            scales: {
                y: { beginAtZero: true, border: { display: false }, grid: { color: '#eaf1eb' }, ticks: { color: '#718579', font: { size: 10 }, precision: 0 } },
                x: { border: { display: false }, grid: { display: false }, ticks: { color: '#718579', font: { size: 10 } } }
            },
            responsive: true,
            maintainAspectRatio: false
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
    initCharts();
    initMap();
});

// ------------------------------------------------------------
// Decision support popover for the chart info buttons.
// It floats over the dashboard so opening it never changes the grid layout.
// ------------------------------------------------------------

</script>

<script src="<?php echo BASE_URL; ?>assets/js/fetch-timeout.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/modal-a11y.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/decision-support.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/decision-support.js'); ?>"></script>
</body>
</html>
