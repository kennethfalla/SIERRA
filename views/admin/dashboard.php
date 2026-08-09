<?php
// views/admin/dashboard.php - DECISION-SUPPORT DASHBOARD
// Algorithmic KPIs, Heatmap with Clustering, Drill-Down Panel, Trend Charts
// Updated: Export Analytics (CSV/PDF), Enhanced Cluster Drill-Down with Photos

require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';
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
// 1. ALGORITHMIC KPI CALCULATIONS (back-end)
// ------------------------------------------------------------

// Total active hotspots = unique clusters (spatial density > 0) among active reports
// We'll count reports with spatial_density_count > 0 (i.e., overlapping within 50m)
$activeHotspots = $db->query("
    SELECT COUNT(DISTINCT 
        CASE 
            WHEN spatial_density_count > 0 THEN CONCAT(latitude, ',', longitude)
            ELSE id
        END
    ) as count
    FROM reports
    WHERE status NOT IN ('resolved', 'rejected', 'cancelled')
      AND latitude IS NOT NULL AND longitude IS NOT NULL
")->fetchColumn();

// Average Municipal Risk Level = average severity_score of active reports
$avgRisk = $db->query("
    SELECT AVG(severity_score) as avg_score
    FROM reports
    WHERE status NOT IN ('resolved', 'rejected', 'cancelled')
      AND severity_score IS NOT NULL
")->fetchColumn() ?: 0;
$avgRisk = round($avgRisk, 1);

// Critical Escalations = active reports in the Critical risk band (>= configured threshold)
$criticalCount = $db->query("
    SELECT COUNT(*) FROM reports
    WHERE risk_level = 'critical'
      AND status NOT IN ('resolved', 'rejected', 'cancelled')
")->fetchColumn();

// Resolved Hotspots (historical) = clusters that were resolved in the last year
// We count unique locations of resolved reports that had spatial density > 0
$resolvedHotspots = $db->query("
    SELECT COUNT(DISTINCT CONCAT(latitude, ',', longitude)) as count
    FROM reports
    WHERE status = 'resolved'
      AND resolved_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
      AND spatial_density_count > 0
      AND latitude IS NOT NULL AND longitude IS NOT NULL
")->fetchColumn();

// ------------------------------------------------------------
// 2. DATA FOR HEATMAP – Active & Historical
// ------------------------------------------------------------

// Active reports (exclude resolved/rejected/cancelled)
$activeReports = $db->query("
        SELECT 
                r.id, r.title, r.description, r.latitude, r.longitude, r.severity_score,
                r.spatial_density_count,
                r.decision_classification,
                r.category_id,
                COALESCE(c.name, '') AS category_name,
                r.location_address,
                r.barangay_name,
                r.status,
                r.risk_level,
                r.created_at,
                (SELECT GROUP_CONCAT(image_path) FROM report_images WHERE report_id = r.id LIMIT 3) as image_paths
        FROM reports r
        LEFT JOIN categories c ON r.category_id = c.id
        WHERE r.status NOT IN ('resolved', 'rejected', 'cancelled')
            AND r.latitude IS NOT NULL AND r.longitude IS NOT NULL
            AND r.latitude != 0 AND r.longitude != 0
        ORDER BY r.severity_score DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Historical (resolved) reports for the toggle
$historicalReports = $db->query("
        SELECT 
                r.id, r.title, r.description, r.latitude, r.longitude, r.severity_score,
                r.spatial_density_count,
                r.decision_classification,
                r.category_id,
                COALESCE(c.name, '') AS category_name,
                r.location_address,
                r.barangay_name,
                r.status,
                r.risk_level,
                r.created_at,
                r.resolved_at,
                (SELECT GROUP_CONCAT(image_path) FROM report_images WHERE report_id = r.id LIMIT 3) as image_paths
        FROM reports r
        LEFT JOIN categories c ON r.category_id = c.id
        WHERE r.status = 'resolved'
            AND r.latitude IS NOT NULL AND r.longitude IS NOT NULL
            AND r.latitude != 0 AND r.longitude != 0
        ORDER BY r.resolved_at DESC
        LIMIT 500
")->fetchAll(PDO::FETCH_ASSOC);

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
$tierQuery = $db->query("
    SELECT severity_score FROM reports
    WHERE status NOT IN ('resolved', 'rejected', 'cancelled')
      AND severity_score IS NOT NULL
");
while ($row = $tierQuery->fetch(PDO::FETCH_ASSOC)) {
    $level = getRiskLevelFromScore($row['severity_score']);
    $severityTiers[$level]['count']++;
}
$severityTotal = array_sum(array_column($severityTiers, 'count'));
$criticalSharePct = $severityTotal > 0 ? round(($severityTiers['critical']['count'] / $severityTotal) * 100, 1) : 0;
$criticalAlert = $severityTotal > 0 && $criticalSharePct > (float)$kpi_critical_reports_pct;

// Seasonal Hazard Analytics (monthly count of high-severity hotspots over last 12 months)
$seasonalData = [];
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[] = date('M', strtotime("-$i months"));
    $count = $db->query("
        SELECT COUNT(*) FROM reports
        WHERE severity_score >= {$severityBands['orange']}
          AND status NOT IN ('resolved', 'rejected', 'cancelled')
          AND DATE_FORMAT(created_at, '%Y-%m') = '$month'
    ")->fetchColumn();
    $seasonalData[] = (int)$count;
}

// Surge Alert: compare the most recent month vs. the previous month per category.
// If any category grew by at least the configured surge threshold (%), flag a
// recommendation to reallocate budget toward that hazard type.
$surgeAlert = null;
$currentMonth = date('Y-m');
$prevMonth = date('Y-m', strtotime('last month'));
try {
    $surgeStmt = $db->query("
        SELECT c.name AS category_name,
               SUM(CASE WHEN DATE_FORMAT(r.created_at, '%Y-%m') = '$currentMonth' THEN 1 ELSE 0 END) AS current_count,
               SUM(CASE WHEN DATE_FORMAT(r.created_at, '%Y-%m') = '$prevMonth' THEN 1 ELSE 0 END) AS previous_count
        FROM reports r
        JOIN categories c ON r.category_id = c.id
        WHERE r.severity_score >= {$severityBands['orange']}
          AND r.status NOT IN ('resolved', 'rejected', 'cancelled')
        GROUP BY c.id, c.name
    ");
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
    $stmt = $db->query("
        SELECT b.name AS barangay_name,
               COUNT(*) AS total_assigned,
               SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) AS total_resolved
        FROM reports r
        JOIN barangays b ON b.id = r.barangay_id
        WHERE r.status NOT IN ('rejected', 'cancelled')
        GROUP BY b.id, b.name
    ");
    $barangayLeaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        $stmt = $db->query("
            SELECT barangay AS barangay_name,
                   COUNT(*) AS total_assigned,
                   SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS total_resolved
            FROM reports
            WHERE status NOT IN ('rejected', 'cancelled')
              AND barangay IS NOT NULL AND barangay != ''
            GROUP BY barangay
        ");
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
    $slowStmt = $db->query("
        SELECT b.name AS barangay_name,
               AVG(TIMESTAMPDIFF(HOUR, r.created_at, r.resolved_at)) AS avg_hours
        FROM reports r
        JOIN barangays b ON b.id = r.barangay_id
        WHERE r.status = 'resolved' AND r.resolved_at IS NOT NULL AND r.created_at IS NOT NULL
        GROUP BY b.id, b.name
        HAVING avg_hours IS NOT NULL
        ORDER BY avg_hours DESC
        LIMIT 1
    ");
    $slowRow = $slowStmt->fetch(PDO::FETCH_ASSOC);
    if ($slowRow) {
        $slowestBarangay = $slowRow;
    }
} catch (Exception $e) {
    try {
        $slowStmt = $db->query("
            SELECT barangay AS barangay_name,
                   AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
            FROM reports
            WHERE status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL
              AND barangay IS NOT NULL AND barangay != ''
            GROUP BY barangay
            HAVING avg_hours IS NOT NULL
            ORDER BY avg_hours DESC
            LIMIT 1
        ");
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
$avgResolutionHoursAllTime = $db->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
    FROM reports
    WHERE status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL
")->fetchColumn() ?: 0;
$avgResolutionDaysAllTime = round($avgResolutionHoursAllTime / 24, 1);

$avgResolutionHoursThisMonth = $db->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
    FROM reports
    WHERE status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL
      AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
")->fetchColumn() ?: 0;
$avgResolutionDaysThisMonth = round($avgResolutionHoursThisMonth / 24, 1);

$avgResolutionHoursLastMonth = $db->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
    FROM reports
    WHERE status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL
      AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')
")->fetchColumn() ?: 0;
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
    $stmt = $db->query("
        SELECT u.residency_status AS status_type, COUNT(*) AS total
        FROM reports r
        JOIN users u ON u.id = r.user_id
        GROUP BY u.residency_status
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (strtolower($row['status_type']) === 'resident') ? 'resident' : 'non_resident';
        $demographics[$key] += (int)$row['total'];
    }
} catch (Exception $e) {
    try {
        $stmt = $db->query("
            SELECT u.is_resident AS status_type, COUNT(*) AS total
            FROM reports r
            JOIN users u ON u.id = r.user_id
            GROUP BY u.is_resident
        ");
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
$dayStmt = $db->query("
    SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS total
    FROM reports
    WHERE created_at IS NOT NULL
    GROUP BY DAYOFWEEK(created_at)
");
while ($row = $dayStmt->fetch(PDO::FETCH_ASSOC)) {
    $idx = (int)$row['dow'] - 1; // MySQL DAYOFWEEK: 1 = Sunday
    if ($idx >= 0 && $idx < 7) $dayCounts[$idx] = (int)$row['total'];
}

$timeBuckets = ['Morning (6AM–12PM)' => 0, 'Afternoon (12PM–6PM)' => 0, 'Night (6PM–6AM)' => 0];
$hourStmt = $db->query("
    SELECT HOUR(created_at) AS hr, COUNT(*) AS total
    FROM reports
    WHERE created_at IS NOT NULL
    GROUP BY HOUR(created_at)
");
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
    $stmt = $db->query("
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
        GROUP BY grid_lat_key, grid_lng_key
        HAVING incident_count > {$repeat_min_sql}
        ORDER BY incident_count DESC
        LIMIT 5
    ");
    $repeatOffenders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        // Fallback for schemas without a text `barangay` column: derive the
        // barangay name via the barangays lookup table on barangay_id.
        $stmt = $db->query("
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
            GROUP BY grid_lat_key, grid_lng_key
            HAVING incident_count > {$repeat_min_sql}
            ORDER BY incident_count DESC
            LIMIT 5
        ");
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
        'low'      => 'Standard Barangay-level maintenance. No MENRO intervention required.',
        'medium'   => 'Flagged for priority Barangay resolution. MENRO monitoring advised.',
        'high'     => 'Escalate to MENRO. Dispatch hazard clearing team to prevent secondary damage or flooding.',
        'critical' => 'CRITICAL. Deploy MENRO heavy equipment and initiate municipal response protocols immediately.',
    ];
    return 'System Recommendation: ' . $recs[$level];
}

// Load San Isidro boundary GeoJSON for map
$geojson_file = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/geojson/sanisidro.geojson';
$boundary_data = null;
if (file_exists($geojson_file)) {
    $boundary_data = json_decode(file_get_contents($geojson_file), true);
}

// Load every barangay boundary GeoJSON and merge them into a single
// FeatureCollection so the map can draw one polygon per barangay.
$barangays_dir = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/geojson/barangay';
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>MENRO Decision Dashboard - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet.markercluster for clustering -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- html2canvas + jsPDF for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: #F5FBF6; overflow-x: hidden; }

        @media (max-width: 768px) {
            .ml-72 { margin-left: 0 !important; width: 100%; padding: 0; }
        }

        .main-container { max-width: 1600px; margin: 0 auto; padding: 1rem; }
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

        /* ===== EXPORT DROPDOWN ===== */
        .export-dropdown {
            position: relative;
            display: inline-block;
        }
        .export-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            box-shadow: 0 12px 36px -8px rgba(0,0,0,0.12);
            min-width: 200px;
            z-index: 100;
            overflow: hidden;
            animation: dropdownIn 0.15s ease;
        }
        .export-dropdown-menu.open { display: block; }
        @keyframes dropdownIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .export-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            font-size: 0.82rem;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.15s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        .export-dropdown-item:hover {
            background: #E8F5F0;
            color: #10A37F;
        }
        .export-dropdown-item i {
            width: 18px;
            text-align: center;
            font-size: 0.9rem;
        }
        .export-dropdown-divider {
            height: 1px;
            background: #f3f4f6;
            margin: 0;
        }
        .btn-export {
            background: white;
            border: 1px solid #e2e8f0;
            color: #374151;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-export:hover {
            border-color: #10A37F;
            color: #10A37F;
            background: #E8F5F0;
        }

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

        /* Responsive tweaks */
        @media (max-width: 768px) {
            #drillPanel { width: 100%; right: -100%; }
            .kpi-card .kpi-value { font-size: 1.5rem; }
            .map-toggle button { padding: 0.3rem 0.8rem; font-size: 0.7rem; }
            .drill-photo-grid { grid-template-columns: repeat(2, 1fr); }
            .drill-photo-grid img,
            .drill-photo-grid video { height: 80px; }
        }
        .risk-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .risk-low { background: #D1FAE5; color: #065F46; }
        .risk-medium { background: #FEF3C7; color: #92400E; }
        .risk-high { background: #FFEDD5; color: #9A3412; }
        .risk-critical { background: #FEE2E2; color: #991B1B; }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<div class="lg:ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">

        <!-- Header with Export -->
        <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-8 h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-pie text-[#10A37F] text-sm"></i>
                    </div>
                    <span class="text-xs uppercase tracking-wider text-[#10A37F] font-semibold">Decision Support System</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">MENRO Decision Dashboard</h1>
                <p class="text-gray-500 text-sm">Real-time algorithm-driven hazard intelligence for San Isidro</p>
            </div>
            <div class="flex items-center gap-3 mt-2 sm:mt-0">
                <div class="text-sm text-gray-400 flex items-center gap-2 mr-2">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date('F d, Y'); ?></span>
                </div>
                <!-- Export Dropdown -->
                <div class="export-dropdown">
                    <button onclick="toggleExportMenu()" class="btn-export">
                        <i class="fas fa-file-export"></i>
                        Export Analytics
                        <i class="fas fa-chevron-down text-[10px] ml-0.5"></i>
                    </button>
                    <div id="exportDropdownMenu" class="export-dropdown-menu">
                        <button class="export-dropdown-item" onclick="exportCSV()">
                            <i class="fas fa-file-csv"></i>
                            <span>Export as CSV</span>
                        </button>
                        <button class="export-dropdown-item" onclick="exportPDF()">
                            <i class="fas fa-file-pdf"></i>
                            <span>Export as PDF</span>
                        </button>
                        <div class="export-dropdown-divider"></div>
                        <button class="export-dropdown-item" onclick="exportCharts()">
                            <i class="fas fa-chart-pie"></i>
                            <span>Export Charts as Images</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- 1. ALGORITHMIC KPI WIDGETS -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="kpi-card kpi-hotspot">
                <div class="kpi-icon"><i class="fas fa-map-pin"></i></div>
                <div class="kpi-label">Active Hotspots</div>
                <div class="kpi-value text-amber-600"><?php echo $activeHotspots; ?></div>
                <div class="kpi-sub">Unique clusters with density > 0</div>
            </div>
            <div class="kpi-card kpi-risk">
                <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
                <div class="kpi-label">Avg Municipal Risk</div>
                <div class="kpi-value text-blue-600"><?php echo $avgRisk; ?></div>
                <div class="kpi-sub">out of 20 severity score</div>
            </div>
            <div class="kpi-card kpi-critical">
                <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="kpi-label">Critical Escalations</div>
                <div class="kpi-value text-red-600"><?php echo $criticalCount; ?></div>
                <div class="kpi-sub">Score <?php echo $severityBands['critical']; ?>-20 · require immediate action</div>
            </div>
            <div class="kpi-card kpi-resolved">
                <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
                <div class="kpi-label">Resolved Hotspots</div>
                <div class="kpi-value text-emerald-600"><?php echo $resolvedHotspots; ?></div>
                <div class="kpi-sub">Clusters resolved this year</div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- 2. DECISION-SUPPORT HEATMAP WITH TOGGLE -->
        <!-- ============================================================ -->
        <div id="map-container" class="mb-6">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                        <i class="fas fa-map-marked-alt text-[#10A37F]"></i>
                        Environmental Heatmap
                    </h2>
                    <div class="map-toggle" id="mapToggle">
                        <button class="active" data-mode="active">Active Hazards</button>
                        <button data-mode="historical">Historical Trends</button>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 text-xs">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#10B981;"></span> Low (1-<?php echo $severityBands['yellow'] - 1; ?>)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#F59E0B;"></span> Medium (<?php echo $severityBands['yellow']; ?>-<?php echo $severityBands['orange'] - 1; ?>)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#F97316;"></span> High (<?php echo $severityBands['orange']; ?>-<?php echo $severityBands['critical'] - 1; ?>)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#EF4444;"></span> Critical (<?php echo $severityBands['critical']; ?>-20)</span>
                </div>
            </div>

            <!-- Category Filter + Timeframe Selector -->
            <div class="flex flex-wrap justify-between items-center gap-3 mb-3">
                <!-- Category Filter Dropdown -->
                <div class="relative" id="categoryFilterWrap">
                    <button id="categoryFilterBtn" class="flex items-center gap-2 text-sm font-semibold text-gray-700 bg-gray-50 border border-gray-200 rounded-full px-4 py-2 hover:border-[#10A37F] transition">
                        <i class="fas fa-filter text-[#10A37F]"></i>
                        <span id="categoryFilterLabel">All Categories</span>
                        <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                    </button>
                    <div id="categoryFilterMenu" class="hidden absolute z-[1100] mt-2 w-64 bg-white rounded-xl border border-gray-200 shadow-lg p-3">
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

                <!-- Timeframe Selector -->
                <div class="map-toggle" id="timeframeToggle">
                    <button data-range="week">This Week</button>
                    <button data-range="month">This Month</button>
                    <button data-range="year">This Year</button>
                    <button class="active" data-range="all">All Time</button>
                </div>
            </div>

            <div id="map"></div>
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
                <div class="chart-title"><i class="fas fa-chart-pie text-[#10A37F] mr-2"></i>Severity Distribution</div>
                <div class="chart-container">
                    <canvas id="severityChart"></canvas>
                </div>
                <?php if ($criticalAlert): ?>
                <div class="rec-box rec-critical mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>System Recommendation:</strong> Critical-severity reports make up <?php echo $criticalSharePct; ?>% of all active reports — exceeding the <?php echo (float)$kpi_critical_reports_pct; ?>% threshold. High-severity alerts suggest a concentrated hazard situation. Recommend immediate MENRO intervention and municipal response protocols.
                </div>
                <?php endif; ?>
            </div>
            <!-- Seasonal Hazard Analytics -->
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-chart-line text-[#10A37F] mr-2"></i>Seasonal Hazard Trends (High-Severity)</div>
                <div class="chart-container">
                    <canvas id="seasonalChart"></canvas>
                </div>
                <?php if ($surgeAlert): ?>
                <div class="rec-box rec-critical mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>System Recommendation:</strong> <?php echo htmlspecialchars($surgeAlert['category']); ?> incidents have surged by <?php echo $surgeAlert['pct']; ?>% compared to the previous month (<?php echo $surgeAlert['previous']; ?> → <?php echo $surgeAlert['current']; ?> this month), exceeding the <?php echo $kpi_surge_alert_threshold; ?>% surge threshold. Recommend budget reallocation to increase response capability for <?php echo htmlspecialchars($surgeAlert['category']); ?> reports.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- 4. BARANGAY PERFORMANCE LEADERBOARD -->
        <!-- ============================================================ -->
        <div class="chart-card mb-6">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
                <div class="chart-title mb-0"><i class="fas fa-trophy text-[#10A37F] mr-2"></i>Barangay Performance Leaderboard</div>
                <span class="text-xs text-gray-400">Ranked by resolution rate · accountability &amp; follow-up tool</span>
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
                $worst = end($barangayLeaderboard);
                reset($barangayLeaderboard);
            ?>
            <?php if ($worst && $worst['resolution_rate'] < $kpi_resolution_rate_target): ?>
            <div class="rec-box rec-critical mt-4">
                <i class="fas fa-lightbulb mr-2"></i>
                <strong>System Recommendation:</strong> Brgy. <?php echo htmlspecialchars($worst['barangay_name']); ?> is experiencing a backlog with only a <?php echo $worst['resolution_rate']; ?>% clearance rate (below the <?php echo $kpi_resolution_rate_target; ?>% target). Dispatch MENRO auxiliary staff and prioritize resolution.
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- ============================================================ -->
        <!-- 5. AVERAGE RESOLUTION TIME + USER DEMOGRAPHICS -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
            <!-- Average Municipal Response Time -->
            <div class="chart-card flex flex-col justify-between">
                <div class="chart-title"><i class="fas fa-stopwatch text-[#10A37F] mr-2"></i>Average Municipal Response Time</div>
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
                <div class="rec-box rec-critical">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>System Recommendation:</strong> The current average response time is <?php echo round($avgResolutionHoursThisMonth, 1); ?> hours, which exceeds the municipal KPI of <?php echo $sla_hours; ?> hours<?php if ($slowestBarangay): ?> — the delay is heavily concentrated in Brgy. <?php echo htmlspecialchars($slowestBarangay['barangay_name']); ?> (avg. <?php echo $slowestBarangay['avg_hours']; ?> hours)<?php endif; ?>. Recommend adding a maintenance crew and equipment to ease the dispatch bottleneck.
                </div>
                <?php elseif ($resolutionTrend === 'worse'): ?>
                <div class="rec-box rec-critical">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>System Recommendation:</strong> Response time is trending up. This may indicate the municipality is understaffed or under-equipped — consider justifying additional maintenance workers or equipment.
                </div>
                <?php elseif ($resolutionTrend === 'better'): ?>
                <div class="rec-box rec-low">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>System Recommendation:</strong> Response time is improving. Current staffing and equipment levels appear to be working well.
                </div>
                <?php endif; ?>
            </div>

            <!-- User Demographics -->
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-users text-[#10A37F] mr-2"></i>Reporter Demographics</div>
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
                <div class="rec-box rec-medium mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>System Recommendation:</strong> Only <?php echo $lowGroupPct; ?>% of reports come from <?php echo $lowGroup; ?> — below the <?php echo (float)$kpi_demographic_threshold; ?>% engagement target. Launch an IEC (Information, Education &amp; Communication) campaign targeted at <?php echo $lowGroup; ?> to improve participation.
                </div>
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
                <div class="chart-title"><i class="fas fa-clock text-[#10A37F] mr-2"></i>Peak Reporting Hours &amp; Days</div>
                <div class="chart-container">
                    <canvas id="peakDayChart"></canvas>
                </div>
                <div class="rec-box rec-medium mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>System Recommendation:</strong>
                    <?php if ($peakDayTotal > 0): ?>
                        Historical data indicates peak hazard reporting consistently occurs on <strong><?php echo $peakDayLabel; ?></strong>s between <strong><?php echo $peakTimeLabel; ?></strong>. Schedule maximum dispatchers and admin staff during this window to keep response times inside the municipal KPI.
                    <?php else: ?>
                        Not enough report data yet to identify a peak reporting window.
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top 5 Repeat Offender Locations -->
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-map-marker-alt text-[#10A37F] mr-2"></i>Top 5 "Repeat Offender" Locations</div>
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
                <div class="rec-box rec-critical mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>System Recommendation:</strong>
                    <?php
                        $topSpot = $repeatOffenders[0];
                        $spotCount = (int)$topSpot['incident_count'];
                        $spotBarangay = !empty($topSpot['barangay_name']) ? 'Brgy. ' . $topSpot['barangay_name'] : 'The #1 location';
                        $spotCategory = $topSpot['category_names'] ?? 'the same hazard type';
                    ?>
                    <?php echo $spotBarangay; ?> has logged <?php echo $spotCount; ?> reports within the last <?php echo (int)$kpi_repeat_window_days; ?> days (exceeding the <?php echo (int)$kpi_repeat_min_reports; ?>-report repeat threshold), the majority being <?php echo htmlspecialchars($spotCategory); ?>. This is a chronic enforcement problem, not a cleanup problem. Recommend installing CCTVs and consider permanent infrastructural changes.
                </div>
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
const severityData = <?php echo json_encode(array_values(array_column($severityTiers, 'count'))); ?>;
const severityLabels = <?php echo json_encode(array_values(array_column($severityTiers, 'label'))); ?>;
const seasonalData = <?php echo json_encode($seasonalData); ?>;
const months = <?php echo json_encode($months); ?>;
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
let selectedRange = 'all'; // 'week' | 'month' | 'year' | 'all' — starts on "All Time" so the default master-cluster view shows everything

// ------------------------------------------------------------
// MAP INITIALIZATION
// ------------------------------------------------------------
let map;
let currentLayer = null;
let currentMode = 'active'; // 'active' or 'historical'

// Barangay polygon layer state
let barangayLayer = null;
let selectedBarangay = null;
let selectedBarangayLayer = null;
const barangayDefaultStyle = {
    color: "#10A37F",
    weight: 1.5,
    fillColor: "#10A37F",
    fillOpacity: 0.06,
    smoothFactor: 1
};

function initMap() {
    const center = [15.3092, 120.9033];
    map = L.map('map').setView(center, 13);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);

    // Draw one clickable polygon per barangay (from the GeoJSON folder)
    addBarangayLayers();

    // Add the municipality outline as a subtle dashed backdrop so reports that
    // fall outside the barangay polygons still have spatial context.
    if (boundaryData && boundaryData.features) {
        try {
            const coords = extractPolygonCoords(boundaryData);
            if (coords) {
                L.polygon(coords, {
                    color: "#10A37F",
                    weight: 1.2,
                    fillColor: "#10A37F",
                    fillOpacity: 0.03,
                    dashArray: "6 4",
                    smoothFactor: 1,
                    interactive: false
                }).addTo(map);
            }
        } catch(e) {}
    }

    // Load initial data
    loadMapData('active');
}

// Render all barangay boundaries as an interactive polygon layer.
function addBarangayLayers() {
    if (!barangayData || !barangayData.features) return;

    barangayLayer = L.geoJSON(barangayData, {
        style: barangayDefaultStyle,
        onEachFeature: function(feature, layer) {
            const name = (feature.properties && feature.properties.name) ? feature.properties.name : 'Barangay';
            layer.bindTooltip(name, { sticky: true });

            layer.on({
                mouseover: function() {
                    if (!selectedBarangayLayer || layer !== selectedBarangayLayer) {
                        layer.setStyle({ fillOpacity: 0.14, weight: 2.5 });
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

    try { map.fitBounds(barangayLayer.getBounds(), { padding: [20, 20], maxZoom: 13 }); } catch(e) {}
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
    layer.setStyle({ fillColor: "#10A37F", fillOpacity: 0.22, weight: 3, color: "#0D8568" });
    layer.bringToFront();
    updateBarangayFilterChip();
    loadMapData(currentMode);
}

function clearBarangayFilter() {
    selectedBarangay = null;
    if (selectedBarangayLayer) { selectedBarangayLayer.setStyle(barangayDefaultStyle); }
    selectedBarangayLayer = null;
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
    const now = new Date();
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
        return categoryOk && rangeOk && barangayOk;
    });
}

function updateFilterSummary(mode, count) {
    const rangeLabels = { week: 'this week', month: 'this month', year: 'this year', all: 'all time' };
    const modeLabel = (mode === 'active') ? 'active' : 'resolved (historical)';
    const el = document.getElementById('filterSummary');
    if (el) {
        let summary = `Showing ${count} ${modeLabel} report(s) · ${rangeLabels[selectedRange]} · ${selectedCategories.size} of ${allCategories.length} categories selected.`;
        if (selectedBarangay) {
            summary += ` · Barangay: ${selectedBarangay}`;
        }
        el.textContent = summary;
    }
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
                <button onclick="openDrillPanel(${report.id})" style="margin-top: 6px; background: #10A37F; color: white; border: none; border-radius: 6px; padding: 4px 12px; font-size: 12px; cursor: pointer;">Analyze</button>
            </div>
        `;

        const icon = L.divIcon({
            html: `<div style="background: ${color}; width: 24px; height: 24px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);"></div>`,
            iconSize: [24, 24],
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
    if (data.length > 0) {
        const bounds = L.latLngBounds(data.map(r => [r.latitude, r.longitude]));
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
    }
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
document.getElementById('timeframeToggle').addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;
    const range = btn.dataset.range;
    if (range === selectedRange) return;
    selectedRange = range;
    this.querySelectorAll('button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadMapData(currentMode);
});

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
        low: 'Standard Barangay-level maintenance. No MENRO intervention required.',
        medium: 'Flagged for priority Barangay resolution. MENRO monitoring advised.',
        high: 'Escalate to MENRO. Dispatch hazard clearing team to prevent secondary damage or flooding.',
        critical: 'CRITICAL. Deploy MENRO heavy equipment and initiate municipal response protocols immediately.'
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
            <span class="text-sm font-mono bg-gray-100 px-2 py-1 rounded">#${String(report.id).padStart(6,'0')}</span>
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
            <strong>System Recommendation:</strong> ${recText}
        </div>

        <!-- Open Full Report -->
        <a href="<?php echo BASE_URL; ?>index.php?page=manage-report&id=${report.id}" target="_blank" class="drill-open-btn">
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
            maintainAspectRatio: true
        }
    });

    // Seasonal Hazard Trends (Line)
    const ctx2 = document.getElementById('seasonalChart').getContext('2d');
    new Chart(ctx2, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'High-Severity (Score ≥ 9)',
                data: seasonalData,
                borderColor: '#EF4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#EF4444',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#e5e7eb' }, ticks: { font: { size: 10 } } },
                x: { grid: { display: false }, ticks: { font: { size: 10 } } }
            },
            responsive: true,
            maintainAspectRatio: true
        }
    });

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
let exportMenuOpen = false;

function toggleExportMenu() {
    const menu = document.getElementById('exportDropdownMenu');
    menu.classList.toggle('open');
    exportMenuOpen = !exportMenuOpen;
}

// Close export dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.querySelector('.export-dropdown');
    const menu = document.getElementById('exportDropdownMenu');
    if (dropdown && menu && !dropdown.contains(e.target)) {
        menu.classList.remove('open');
        exportMenuOpen = false;
    }
});

// Export CSV
function exportCSV() {
    document.getElementById('exportDropdownMenu').classList.remove('open');
    exportMenuOpen = false;

    // Build CSV data from reports
    const data = currentMode === 'active' ? activeReports : historicalReports;
    if (!data || data.length === 0) {
        alert('No data to export.');
        return;
    }

    // Define headers
    const headers = ['ID', 'Title', 'Category', 'Barangay', 'Status', 'Risk Level', 'Severity Score', 'Classification', 'Created At'];
    if (currentMode === 'historical') headers.push('Resolved At');

    // Build rows
    const rows = data.map(r => {
        const row = [
            r.id,
            `"${(r.title || '').replace(/"/g, '""')}"`,
            `"${(r.category_name || '').replace(/"/g, '""')}"`,
            `"${(r.barangay_name || '').replace(/"/g, '""')}"`,
            r.status || '',
            r.risk_level || 'low',
            r.severity_score || 0,
            r.decision_classification || '',
            r.created_at || ''
        ];
        if (currentMode === 'historical') row.push(r.resolved_at || '');
        return row.join(',');
    });

    const csvContent = [headers.join(','), ...rows].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `analytics_export_${currentMode}_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
}

// Export PDF (dashboard snapshot)
function exportPDF() {
    document.getElementById('exportDropdownMenu').classList.remove('open');
    exportMenuOpen = false;

    // Show loading overlay
    const overlay = document.createElement('div');
    overlay.className = 'export-overlay';
    overlay.id = 'exportOverlay';
    overlay.innerHTML = `
        <div class="export-overlay-card">
            <div class="export-spinner"></div>
            <p style="font-weight:700;color:#1f2937;font-size:0.95rem;margin-bottom:4px;">Generating PDF Report</p>
            <p style="color:#6b7280;font-size:0.8rem;">Please wait a moment...</p>
        </div>
    `;
    document.body.appendChild(overlay);

    // Clone the dashboard content (excluding the map and interactive elements)
    const source = document.querySelector('.main-container');
    const clone = source.cloneNode(true);

    // Remove interactive elements from clone
    clone.querySelectorAll('#map-container, .export-dropdown, .map-toggle, #categoryFilterWrap, #timeframeToggle, .chart-container canvas').forEach(el => {
        if (el.parentNode) el.parentNode.removeChild(el);
    });

    // Replace map with static text
    const mapContainer = clone.querySelector('#map-container');
    if (mapContainer) {
        mapContainer.innerHTML = `
            <div style="padding:1rem; background:#f0f4f0; border-radius:0.75rem; text-align:center; color:#6b7280; border:1px solid #e5e7eb;">
                <i class="fas fa-map-marked-alt" style="color:#10A37F; font-size:24px; display:block; margin-bottom:8px;"></i>
                <p style="font-weight:600;">Environmental Heatmap - ${currentMode === 'active' ? 'Active Hazards' : 'Historical Trends'}</p>
                <p style="font-size:0.8rem;">${currentMode === 'active' ? activeReports.length : historicalReports.length} reports shown</p>
                <p style="font-size:0.7rem;color:#9ca3af;">Filtered by ${selectedCategories.size} categories</p>
            </div>
        `;
    }

    // Replace charts with static text
    clone.querySelectorAll('.chart-card').forEach(card => {
        const canvas = card.querySelector('canvas');
        if (canvas) {
            const title = card.querySelector('.chart-title')?.textContent || 'Chart';
            const parent = canvas.parentNode;
            parent.innerHTML = `
                <div style="padding:1rem; background:#f8fafc; border-radius:0.5rem; text-align:center; color:#6b7280; border:1px solid #e5e7eb;">
                    <p style="font-weight:600;">${title.trim()}</p>
                    <p style="font-size:0.8rem;">Data included in PDF export</p>
                </div>
            `;
        }
    });

    // Render clone in a hidden container
    const wrapper = document.createElement('div');
    wrapper.id = 'pdf-render-container';
    wrapper.style.cssText = 'position:fixed; left:-9999px; top:0; width:800px; background:white; z-index:-1; font-family:Manrope,sans-serif; padding:20px;';
    wrapper.appendChild(clone);
    document.body.appendChild(wrapper);

    // Capture and generate PDF
    setTimeout(() => {
        html2canvas(clone, {
            scale: 2,
            useCORS: true,
            letterRendering: true,
            backgroundColor: '#ffffff',
            width: 800,
            windowWidth: 800,
            scrollY: 0,
            scrollX: 0
        }).then(canvas => {
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('portrait', 'mm', 'a4');
            const marginX = 10, marginY = 10;
            const usableW = 210 - (marginX * 2);
            const usableH = 297 - (marginY * 2);
            const imgRatio = canvas.width / canvas.height;
            let pdfW = usableW;
            let pdfH = pdfW / imgRatio;
            if (pdfH > usableH) {
                pdfH = usableH;
                pdfW = pdfH * imgRatio;
            }
            const offsetX = marginX + (usableW - pdfW) / 2;
            const offsetY = marginY;

            const imgData = canvas.toDataURL('image/jpeg', 0.92);
            pdf.addImage(imgData, 'JPEG', offsetX, offsetY, pdfW, pdfH);
            pdf.save(`Dashboard_Report_${new Date().toISOString().slice(0,10)}.pdf`);

            cleanup();
        }).catch(err => {
            console.error('PDF capture error:', err);
            cleanup();
            alert('Failed to generate PDF. Please try again.');
        });
    }, 500);

    function cleanup() {
        const c = document.getElementById('pdf-render-container');
        if (c) c.remove();
        const o = document.getElementById('exportOverlay');
        if (o) o.remove();
    }
}

// Export Charts as Images
function exportCharts() {
    document.getElementById('exportDropdownMenu').classList.remove('open');
    exportMenuOpen = false;

    const charts = document.querySelectorAll('.chart-card canvas');
    if (charts.length === 0) {
        alert('No charts to export.');
        return;
    }

    // Create a zip-like download of all chart images (download one by one)
    charts.forEach((canvas, index) => {
        const title = canvas.closest('.chart-card')?.querySelector('.chart-title')?.textContent?.trim() || `Chart_${index + 1}`;
        const link = document.createElement('a');
        link.download = `${title.replace(/[^a-zA-Z0-9]/g, '_')}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    });

    // Also export the map as image if possible
    const mapElement = document.getElementById('map');
    if (mapElement) {
        // Use leaflet's built-in export or just notify
        setTimeout(() => {
            alert('Map image export is not supported directly. Use the PDF export for a complete dashboard snapshot.');
        }, 500);
    }
}

// ------------------------------------------------------------
// INIT
// ------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    initCharts();
});
</script>

</body>
</html>