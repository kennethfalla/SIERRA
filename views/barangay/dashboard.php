<?php
// views/barangay/dashboard.php - WITH CONSISTENT DESIGN SYSTEM + ALGORITHMIC DECISION-SUPPORT WIDGETS
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';

// Check if user is logged in
if(!isLoggedIn()) {
    header("Location: " . BASE_URL . "views/auth/login.php");
    exit();
}

// Check if user has barangay_official role
if($_SESSION['user_role'] !== 'barangay_official') {
    header("Location: " . BASE_URL . "index.php?page=dashboard");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$reportModel = new Report($db);
$barangayModel = new Barangay($db);

// Check if barangay_id is set in session
if (!isset($_SESSION['barangay_id']) || empty($_SESSION['barangay_id'])) {
    $_SESSION['error'] = "Your account is not associated with a barangay. Please contact the administrator.";
    error_log("Barangay Dashboard Error: barangay_id not set for user " . $_SESSION['user_id']);
    header("Location: " . BASE_URL . "index.php?page=profile");
    exit();
}

$barangay_id = $_SESSION['barangay_id'];
$barangay_info = $barangayModel->getById($barangay_id);
$user_name = $_SESSION['user_name'] ?? 'Barangay Official';

// Load THIS barangay's own boundary from the GeoJSON folder so the map shows
// exactly their jurisdiction, with the citizen reports pinned on top of it.
// The boundary file is matched against the barangay name stored in the
// session (case-insensitive, ignoring hyphens/spaces, e.g. "San Roque").
$barangay_boundary = null;
$barangay_name_key = strtolower(preg_replace('/[^a-z0-9]+/', '', $barangay_info['name'] ?? ''));
if ($barangay_name_key !== '') {
    $barangays_dir = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/geojson/barangay';
    if (is_dir($barangays_dir)) {
        foreach (glob($barangays_dir . '/*.geojson') as $barangay_file) {
            $barangay_base = basename($barangay_file);
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
                $prop_name = $barangay_feature['properties']['barangay_name'] ?? $barangay_feature['properties']['name'] ?? '';
                $prop_key = strtolower(preg_replace('/[^a-z0-9]+/', '', $prop_name));
                $file_key = strtolower(preg_replace('/[^a-z0-9]+/', '', pathinfo($barangay_base, PATHINFO_FILENAME)));
                if ($prop_key === $barangay_name_key || $file_key === $barangay_name_key) {
                    $barangay_feature['properties']['name'] = $barangay_info['name'];
                    $barangay_boundary = ['type' => 'FeatureCollection', 'features' => [$barangay_feature]];
                    break 2;
                }
            }
        }
    }
}

// Get current time for real-time greeting
date_default_timezone_set('Asia/Manila');
$current_hour = date('H');
$current_time = date('g:i A');
$current_date = date('F j, Y');

// Determine greeting based on current hour
if ($current_hour < 12) {
    $greeting = "Good Morning";
    $greeting_icon = "fa-sun";
    $greeting_color = "text-amber-500";
} elseif ($current_hour < 18) {
    $greeting = "Good Afternoon";
    $greeting_icon = "fa-cloud-sun";
    $greeting_color = "text-orange-500";
} else {
    $greeting = "Good Evening";
    $greeting_icon = "fa-moon";
    $greeting_color = "text-indigo-500";
}

// ========== NOTIFICATIONS SECTION ==========
$notifications = array();

// 1. Welcome notification
$notifications[] = array(
    'id' => 'welcome',
    'type' => 'welcome',
    'title' => 'Welcome to Sierra',
    'message' => "Welcome back, $user_name! You're managing reports for " . ($barangay_info['name'] ?? 'your barangay'),
    'time' => date('Y-m-d H:i:s'),
    'icon' => 'fa-leaf',
    'color' => '#059669',
    'link' => '',
    'read' => false
);

// 2. New pending reports notification
$pending_reports = $reportModel->getReportsByStatus('pending', $barangay_id);
if($pending_reports > 0) {
    $notifications[] = array(
        'id' => 'pending_reports',
        'type' => 'pending',
        'title' => 'New Reports Pending Verification',
        'message' => "You have $pending_reports pending report(s) waiting for your review and verification.",
        'time' => date('Y-m-d H:i:s'),
        'icon' => 'fa-clock',
        'color' => '#F59E0B',
        'link' => BASE_URL . "index.php?page=verify-reports",
        'read' => false
    );
}

// 3. In progress reports notification
$in_progress_reports = $reportModel->getReportsByStatus('in_progress', $barangay_id);
if($in_progress_reports > 0) {
    $notifications[] = array(
        'id' => 'in_progress_reports',
        'type' => 'in_progress',
        'title' => 'Reports Being Addressed',
        'message' => "$in_progress_reports report(s) are currently in progress and being worked on.",
        'time' => date('Y-m-d H:i:s'),
        'icon' => 'fa-spinner',
        'color' => '#DB2777',
        'link' => BASE_URL . "index.php?page=verify-reports&status=in_progress",
        'read' => false
    );
}

// 4. Recently resolved reports notification
$resolved_7_days = $db->prepare("
    SELECT COUNT(*) as count FROM reports 
    WHERE barangay_id = ? AND status = 'resolved' 
    AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$resolved_7_days->execute([$barangay_id]);
$recently_resolved = $resolved_7_days->fetch(PDO::FETCH_ASSOC)['count'];

if($recently_resolved > 0) {
    $notifications[] = array(
        'id' => 'resolved_reports',
        'type' => 'resolved',
        'title' => 'Reports Resolved',
        'message' => "$recently_resolved report(s) have been resolved in the last 7 days. Great work!",
        'time' => date('Y-m-d H:i:s'),
        'icon' => 'fa-check-circle',
        'color' => '#059669',
        'link' => BASE_URL . "index.php?page=verify-reports&status=resolved",
        'read' => false
    );
}

// 5. High risk reports notification
$high_risk_count = $db->prepare("
    SELECT COUNT(*) as count FROM reports 
    WHERE barangay_id = ? AND risk_level IN ('high', 'critical')
");
$high_risk_count->execute([$barangay_id]);
$high_risk = $high_risk_count->fetch(PDO::FETCH_ASSOC)['count'];

if($high_risk > 0) {
    $notifications[] = array(
        'id' => 'high_risk',
        'type' => 'risk',
        'title' => 'High Risk Reports Need Attention',
        'message' => "$high_risk report(s) with high or critical risk level require immediate attention.",
        'time' => date('Y-m-d H:i:s'),
        'icon' => 'fa-exclamation-triangle',
        'color' => '#EF4444',
        'link' => BASE_URL . "index.php?page=verify-reports&risk=high",
        'read' => false
    );
}

// 6. Weekly report summary notification
$weekly_total = $db->prepare("
    SELECT COUNT(*) as count FROM reports 
    WHERE barangay_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$weekly_total->execute([$barangay_id]);
$new_this_week = $weekly_total->fetch(PDO::FETCH_ASSOC)['count'];

if($new_this_week > 0) {
    $notifications[] = array(
        'id' => 'weekly_summary',
        'type' => 'summary',
        'title' => 'Weekly Report Summary',
        'message' => "$new_this_week new report(s) submitted this week in your barangay.",
        'time' => date('Y-m-d H:i:s'),
        'icon' => 'fa-chart-line',
        'color' => '#8B5CF6',
        'link' => BASE_URL . "index.php?page=verify-reports",
        'read' => false
    );
}

// Sort by time (newest first)
usort($notifications, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

$notifications = array_slice($notifications, 0, 10);
$unread_count = count($notifications);

// ========== BASIC STATUS-BREAKDOWN STATISTICS (kept for the secondary row) ==========
$total_reports = $reportModel->getTotalCount($barangay_id);
$pending_count = $reportModel->getReportsByStatus('pending', $barangay_id);
$in_progress_count = $reportModel->getReportsByStatus('in_progress', $barangay_id);
$resolved_count = $reportModel->getReportsByStatus('resolved', $barangay_id);
$rejected_count = $reportModel->getReportsByStatus('rejected', $barangay_id);

$escalated_to_menro_query = $db->prepare("
    SELECT COUNT(*) as count FROM reports 
    WHERE barangay_id = ? AND (status = 'escalated' OR status = 'escalated_pending' OR escalated_to_menro = 1)
");
$escalated_to_menro_query->execute([$barangay_id]);
$escalated_to_menro_count = $escalated_to_menro_query->fetch(PDO::FETCH_ASSOC)['count'];

$resolution_rate = $total_reports > 0 ? round(($resolved_count / $total_reports) * 100) : 0;

// ============================================================
// ========== ALGORITHMIC KPI WIDGETS (barangay-scoped) ==========
// Mirrors the MENRO decision-support algorithm exactly, but every
// query is strictly filtered WHERE barangay_id = $barangay_id so an
// official only ever sees their own jurisdiction.
// ============================================================

// 1. Pending Acknowledgment = [Submitted]/pending reports demanding review
//    (reuses $pending_count calculated above)

// 2. Critical Local Hotspots = unique ~50m-grid clusters hitting the
//    algorithm's "Critical" tier (severity_score >= critical threshold)
//    among this barangay's active (non-resolved/rejected/cancelled) reports.
$criticalBands = getSeverityBands();
$criticalHotspotsCount = $db->prepare("
    SELECT COUNT(DISTINCT CONCAT(FLOOR(latitude / 0.00045), ',', FLOOR(longitude / 0.00045))) AS count
    FROM reports
    WHERE barangay_id = ?
      AND risk_level = 'critical'
      AND status NOT IN ('resolved', 'rejected', 'cancelled')
      AND latitude IS NOT NULL AND longitude IS NOT NULL
      AND latitude != 0 AND longitude != 0
");
$criticalHotspotsCount->execute([$barangay_id]);
$criticalHotspotsCount = (int)$criticalHotspotsCount->fetch(PDO::FETCH_ASSOC)['count'];

// 3. Average Resolution Speed (all-time, this month, last month) - barangay scoped
$avgResHoursAllTime = $db->prepare("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
    FROM reports
    WHERE barangay_id = ? AND status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL
");
$avgResHoursAllTime->execute([$barangay_id]);
$avgResolutionDaysAllTime = round(($avgResHoursAllTime->fetch(PDO::FETCH_ASSOC)['avg_hours'] ?: 0) / 24, 1);

$avgResHoursThisMonth = $db->prepare("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
    FROM reports
    WHERE barangay_id = ? AND status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL
      AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
");
$avgResHoursThisMonth->execute([$barangay_id]);
$avgResolutionDaysThisMonth = round(($avgResHoursThisMonth->fetch(PDO::FETCH_ASSOC)['avg_hours'] ?: 0) / 24, 1);

$avgResHoursLastMonth = $db->prepare("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
    FROM reports
    WHERE barangay_id = ? AND status = 'resolved' AND resolved_at IS NOT NULL AND created_at IS NOT NULL
      AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')
");
$avgResHoursLastMonth->execute([$barangay_id]);
$avgResolutionDaysLastMonth = round(($avgResHoursLastMonth->fetch(PDO::FETCH_ASSOC)['avg_hours'] ?: 0) / 24, 1);

$resolutionSpeedTrend = 'stable';
$resolutionSpeedDelta = 0;
if ($avgResolutionDaysLastMonth > 0) {
    $resolutionSpeedDelta = round($avgResolutionDaysThisMonth - $avgResolutionDaysLastMonth, 1);
    if ($resolutionSpeedDelta > 0.5) $resolutionSpeedTrend = 'worse';
    elseif ($resolutionSpeedDelta < -0.5) $resolutionSpeedTrend = 'better';
}

// 4. Resolution Rate (THIS MONTH) = % of hazards assigned this month that this
//    barangay has already marked [Resolved]. "Assigned this month" = created
//    this month OR resolved this month (covers carry-over backlog too).
$assignedThisMonthQ = $db->prepare("
    SELECT COUNT(*) AS count FROM reports
    WHERE barangay_id = ?
      AND (DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
           OR DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m'))
");
$assignedThisMonthQ->execute([$barangay_id]);
$assignedThisMonth = (int)$assignedThisMonthQ->fetch(PDO::FETCH_ASSOC)['count'];

$resolvedThisMonthQ = $db->prepare("
    SELECT COUNT(*) AS count FROM reports
    WHERE barangay_id = ? AND status = 'resolved'
      AND DATE_FORMAT(resolved_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
");
$resolvedThisMonthQ->execute([$barangay_id]);
$resolvedThisMonth = (int)$resolvedThisMonthQ->fetch(PDO::FETCH_ASSOC)['count'];

$resolutionRateThisMonth = $assignedThisMonth > 0 ? round(($resolvedThisMonth / $assignedThisMonth) * 100) : 0;

// ========== WEEKLY RESOLUTION STATS (kept - powers the secondary Weekly Trends card) ==========
$weekly_stats = $db->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%W') as day,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
    FROM reports 
    WHERE barangay_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DAYOFWEEK(created_at)
    ORDER BY DAYOFWEEK(created_at)
");
$weekly_stats->execute([$barangay_id]);
$weekly_data = $weekly_stats->fetchAll(PDO::FETCH_ASSOC);

// ========== CATEGORY DISTRIBUTION (kept - powers the secondary Category card) ==========
$category_stats = $db->prepare("
    SELECT c.name, COUNT(r.id) as count
    FROM categories c
    LEFT JOIN reports r ON c.id = r.category_id AND r.barangay_id = ?
    GROUP BY c.id
    HAVING COUNT(r.id) > 0
    ORDER BY count DESC
");
$category_stats->execute([$barangay_id]);
$category_data = $category_stats->fetchAll(PDO::FETCH_ASSOC);

// ========== RISK LEVEL DISTRIBUTION (kept - powers the secondary Risk card) ==========
$risk_stats = $db->prepare("
    SELECT 
        COALESCE(risk_level, 'low') as risk_level,
        COUNT(*) as count
    FROM reports 
    WHERE barangay_id = ?
    GROUP BY risk_level
    ORDER BY 
        CASE risk_level 
            WHEN 'critical' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
        END
");
$risk_stats->execute([$barangay_id]);
$risk_data = $risk_stats->fetchAll(PDO::FETCH_ASSOC);
$risk_total = array_sum(array_column($risk_data, 'count'));

// ============================================================
// ========== CENTERPIECE MAP DATA (algorithm-driven, barangay-scoped) ==========
// Same "Active" vs "Historical" split and severity-score fields as the
// MENRO map so the clustering/coloring algorithm is identical.
// ============================================================
$activeMapQuery = $db->prepare("
    SELECT id, title, latitude, longitude, severity_score, spatial_density_count,
           decision_classification, category_id, status, risk_level, created_at
    FROM reports
    WHERE barangay_id = ?
      AND status NOT IN ('resolved', 'rejected', 'cancelled')
      AND latitude IS NOT NULL AND longitude IS NOT NULL
      AND latitude != 0 AND longitude != 0
    ORDER BY severity_score DESC
");
$activeMapQuery->execute([$barangay_id]);
$activeReports = $activeMapQuery->fetchAll(PDO::FETCH_ASSOC);

$historicalMapQuery = $db->prepare("
    SELECT id, title, latitude, longitude, severity_score, spatial_density_count,
           decision_classification, category_id, status, risk_level, created_at, resolved_at
    FROM reports
    WHERE barangay_id = ?
      AND status = 'resolved'
      AND latitude IS NOT NULL AND longitude IS NOT NULL
      AND latitude != 0 AND longitude != 0
    ORDER BY resolved_at DESC
    LIMIT 500
");
$historicalMapQuery->execute([$barangay_id]);
$historicalReports = $historicalMapQuery->fetchAll(PDO::FETCH_ASSOC);

// Category checkboxes for the map's Category Filter toggle
$categories = [];
try {
    $catStmt = $db->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// ============================================================
// ========== LOCAL DEMOGRAPHICS (Resident vs Non-Resident) ==========
// Scoped strictly to reports submitted within this barangay.
// ============================================================
$demographics = ['resident' => 0, 'non_resident' => 0];
$demographicsAvailable = true;
try {
    $stmt = $db->prepare("
        SELECT u.residency_status AS status_type, COUNT(*) AS total
        FROM reports r
        JOIN users u ON u.id = r.user_id
        WHERE r.barangay_id = ?
        GROUP BY u.residency_status
    ");
    $stmt->execute([$barangay_id]);
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
            WHERE r.barangay_id = ?
            GROUP BY u.is_resident
        ");
        $stmt->execute([$barangay_id]);
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

// ============================================================
// ========== PEAK REPORTING TIMES (day-of-week, barangay-scoped) ==========
// ============================================================
$dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$dayCounts = array_fill(0, 7, 0);
$dayStmt = $db->prepare("
    SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS total
    FROM reports
    WHERE barangay_id = ? AND created_at IS NOT NULL
    GROUP BY DAYOFWEEK(created_at)
");
$dayStmt->execute([$barangay_id]);
while ($row = $dayStmt->fetch(PDO::FETCH_ASSOC)) {
    $idx = (int)$row['dow'] - 1; // MySQL DAYOFWEEK: 1 = Sunday
    if ($idx >= 0 && $idx < 7) $dayCounts[$idx] = (int)$row['total'];
}
$peakDayTotal = max($dayCounts);
$peakDayIndex = array_search($peakDayTotal, $dayCounts);
$peakDayLabel = ($peakDayTotal > 0 && $peakDayIndex !== false) ? $dayLabels[$peakDayIndex] : 'N/A';
$dayGrandTotal = array_sum($dayCounts);
$peakDayShare = $dayGrandTotal > 0 ? round(($peakDayTotal / $dayGrandTotal) * 100, 1) : 0;

// ========== SEVERITY HELPER FUNCTIONS (same 20-point algorithm as MENRO) ==========
function getSeverityColorPHP($score) {
    return getRiskColor(getRiskLevelFromScore($score));
}
function getSeverityTierPHP($score) {
    return getRiskLevelLabel(getRiskLevelFromScore($score));
}

// ========== GET RECENT REPORTS (kept - powers the Recent Reports table) ==========
$recent_reports = $reportModel->getAllReports($barangay_id);
$recent_reports->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Barangay Dashboard - Sierra</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet.markercluster for algorithm-driven clustering (same as MENRO map) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { font-family: 'Manrope', sans-serif; }
        
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
            background: transparent;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 20px;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #059669, #047857);
            border-radius: 20px;
        }
        * {
            scrollbar-width: thin;
            scrollbar-color: #059669 #f1f5f9;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Manrope', sans-serif;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        
        /* ========== RADIUS SCALE SYSTEM ========== */
        .radius-4 { border-radius: 4px; }
        .radius-8 { border-radius: 8px; }
        button:not(.radius-10):not(.radius-12):not(.radius-16):not(.radius-full) { border-radius: 8px; }
        input, select, textarea { border-radius: 8px !important; }
        .radius-10 { border-radius: 10px; }
        .stat-icon, .icon-container { border-radius: 10px; }
        .btn-primary { border-radius: 10px; }
        .radius-12 { border-radius: 12px; }
        .stat-card, .resolution-card, .table-container { border-radius: 12px; }
        .radius-16 { border-radius: 16px; }
        .modal-content { border-radius: 16px; }
        .radius-24 { border-radius: 24px; }
        .greeting-badge { border-radius: 24px; }
        .radius-full { border-radius: 9999px; }
        .status-badge, .notification-badge, .notification-dropdown { border-radius: 9999px; }
        
        .stat-card { 
            transition: all 0.2s ease; 
            border: 1px solid rgba(5, 150, 105, 0.08);
            border-radius: 12px;
            opacity: 0;
            animation: slideUp 0.5s ease-out forwards;
        }
        .stat-card:hover { 
            transform: translateY(-2px); 
            border-color: #059669; 
            box-shadow: 0 8px 20px -12px rgba(5, 150, 105, 0.15); 
        }
        
        .status-badge { 
            display: inline-flex; 
            align-items: center; 
            padding: 0.25rem 0.75rem; 
            border-radius: 9999px; 
            font-size: 0.7rem; 
            font-weight: 600; 
        }
        .status-pending { background: #FEF3C7; color: #D97706; }
        .status-verified { background: #DBEAFE; color: #2563EB; }
        .status-in_progress { background: #FCE7F3; color: #DB2777; }
        .status-resolved { background: #D1FAE5; color: #059669; }
        .status-rejected { background: #FEE2E2; color: #DC2626; }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-up { animation: slideUp 0.5s ease-out forwards; }
        
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.2s; }
        .stat-card:nth-child(5) { animation-delay: 0.25s; }
        .stat-card:nth-child(6) { animation-delay: 0.3s; }
        
        .greeting-badge {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            opacity: 0;
            animation: slideUp 0.5s ease-out forwards;
        }
        
        .notification-dropdown {
            position: fixed !important;
            top: 80px;
            right: 32px;
            width: 420px;
            max-height: 500px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.25);
            z-index: 999999 !important;
            display: none;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #E5E7EB;
        }
        
        .notification-dropdown.show { 
            display: flex; 
            animation: slideDown 0.2s ease; 
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px) translateZ(0); }
            to { opacity: 1; transform: translateY(0) translateZ(0); }
        }
        
        .notification-bell { 
            cursor: pointer; 
            transition: all 0.2s; 
            position: relative; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            z-index: 100;
        }
        .notification-bell:hover { transform: scale(1.05); }
        
        .notification-badge {
            position: absolute; 
            top: -5px; 
            right: -5px; 
            background: #EF4444; 
            color: white;
            font-size: 10px; 
            font-weight: bold; 
            padding: 2px 6px; 
            border-radius: 20px;
            min-width: 18px; 
            text-align: center;
        }
        
        .notification-header { 
            padding: 16px 20px; 
            border-bottom: 1px solid #F3F4F6; 
            background: #FAFAFA; 
        }
        
        .notification-list { 
            overflow-y: auto; 
            max-height: 380px; 
            flex: 1; 
        }
        
        .notification-item { 
            display: flex; 
            gap: 14px; 
            padding: 14px 20px; 
            border-bottom: 1px solid #F3F4F6; 
            transition: background 0.2s; 
            cursor: pointer; 
        }
        .notification-item:hover { background: #F0FDF4; }
        
        .notification-icon { 
            width: 44px; 
            height: 44px; 
            border-radius: 14px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            flex-shrink: 0; 
        }
        
        .notification-title { 
            font-weight: 700; 
            font-size: 0.9rem; 
            color: #1F2937; 
            margin-bottom: 4px; 
        }
        
        .notification-message { 
            font-size: 0.75rem; 
            color: #6B7280; 
            line-height: 1.4; 
            margin-bottom: 6px; 
        }
        
        .notification-time { 
            font-size: 0.65rem; 
            color: #9CA3AF; 
            display: flex; 
            align-items: center; 
            gap: 4px; 
        }
        
        .notification-dot { 
            width: 8px; 
            height: 8px; 
            background: #059669; 
            border-radius: 50%; 
            flex-shrink: 0; 
            margin-top: 2px; 
        }
        
        .mark-all-read { 
            text-align: center; 
            padding: 12px 20px; 
            border-top: 1px solid #F3F4F6; 
            background: #FAFAFA; 
            font-size: 0.75rem; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.2s; 
            color: #059669; 
        }
        .mark-all-read:hover { background: #F0FDF4; color: #047857; }
        
        .resolution-card {
            background: white;
            border: 1px solid rgba(5, 150, 105, 0.08);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .resolution-progress {
            height: 8px;
            background: #eef2f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .resolution-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #059669, #34D399);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .chart-canvas-container {
            position: relative;
            height: 220px;
            width: 100%;
        }
        
        .reports-table::-webkit-scrollbar { height: 6px; }
        .reports-table::-webkit-scrollbar-track { background: #F3F4F6; border-radius: 10px; }
        .reports-table::-webkit-scrollbar-thumb { background: #059669; border-radius: 10px; }
        
        /* ========== ALGORITHMIC KPI WIDGETS (top row) ========== */
        .kpi-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(5, 150, 105, 0.08);
            padding: 1.25rem 1rem;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }
        .kpi-card:hover {
            transform: translateY(-4px);
            border-color: #059669;
            box-shadow: 0 12px 28px -8px rgba(5, 150, 105, 0.15);
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
        .kpi-pending { border-left: 4px solid #EF4444; }
        .kpi-hotspot { border-left: 4px solid #F59E0B; }
        .kpi-speed { border-left: 4px solid #3B82F6; }
        .kpi-rate { border-left: 4px solid #10B981; }

        /* ========== CENTERPIECE DECISION-SUPPORT MAP ========== */
        #map-container {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid rgba(5, 150, 105, 0.08);
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
            color: #059669;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .map-toggle button:hover:not(.active) { color: #059669; }

        /* Drill-down panel */
        #drillPanel {
            position: fixed;
            top: 0;
            right: -480px;
            width: 480px;
            height: 100%;
            background: white;
            box-shadow: -4px 0 24px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: right 0.3s ease;
            overflow-y: auto;
            padding: 1.5rem;
        }
        #drillPanel.open { right: 0; }
        #drillPanel .close-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
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
        }
        #drillPanel .close-btn:hover { background: #e2e8f0; }

        .score-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
        }
        .score-row .label { color: #64748b; }
        .score-row .value { font-weight: 600; }

        .rec-box {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-top: 1rem;
            font-weight: 500;
        }
        .rec-low { background: #D1FAE5; color: #065F46; border-left: 4px solid #10B981; }
        .rec-medium { background: #FEF3C7; color: #92400E; border-left: 4px solid #F59E0B; }
        .rec-high { background: #FFEDD5; color: #9A3412; border-left: 4px solid #F97316; }
        .rec-critical { background: #FEE2E2; color: #991B1B; border-left: 4px solid #EF4444; }

        .chart-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(5, 150, 105, 0.08);
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
        
        .time-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border-radius: 12px;
            padding: 0.5rem 1rem;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .ml-72 { margin-left: 0; }
            .notification-dropdown { right: 16px; left: 16px; width: auto; }
            #drillPanel { width: 100%; right: -100%; }
            .kpi-card .kpi-value { font-size: 1.5rem; }
            .map-toggle button { padding: 0.3rem 0.8rem; font-size: 0.7rem; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-[#F5FBF6] to-[#EAF7F2]">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<div class="lg:ml-72 min-h-screen">
    <div class="p-4 md:p-8 max-w-[1600px] mx-auto">
        
        <!-- PROFESSIONAL GREETING BADGE -->
        <div class="greeting-badge p-6 text-white mb-8">
            <div class="relative z-10">
                <div class="flex justify-between items-start flex-wrap gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <i class="fas <?php echo $greeting_icon; ?> <?php echo $greeting_color; ?> text-xl"></i>
                            <span class="text-sm font-medium opacity-90"><?php echo $greeting; ?></span>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-bold tracking-tight"><?php echo htmlspecialchars($user_name); ?></h1>
                        <p class="text-emerald-100 text-sm mt-1 font-medium">Manage reports for <span class="font-bold"><?php echo htmlspecialchars($barangay_info['name'] ?? 'Your Barangay'); ?></span></p>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <!-- Notification Bell -->
                        <div class="relative">
                            <div class="notification-bell bg-white/20 rounded-xl w-12 h-12 flex items-center justify-center" onclick="toggleNotifications()">
                                <i class="fas fa-bell text-white text-xl"></i>
                                <?php if($unread_count > 0): ?>
                                <span class="notification-badge" id="notificationBadge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Time Card -->
                        <div class="time-card">
                            <div class="text-2xl font-bold tracking-tight" id="currentTime"><?php echo date('h:i'); ?></div>
                            <div class="text-xs uppercase tracking-wide font-semibold" id="currentPeriod"><?php echo date('A'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- NOTIFICATION DROPDOWN -->
        <div id="notificationDropdown" class="notification-dropdown" style="display: none;">
            <div class="notification-header">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="font-extrabold text-gray-800 tracking-tight">Notifications</h3>
                        <p class="text-xs text-gray-400 mt-0.5 font-medium">Stay updated on barangay reports</p>
                    </div>
                    <span class="text-xs bg-emerald-50 text-[#059669] px-2.5 py-1 rounded-full font-bold">
                        <?php echo count($notifications); ?> updates
                    </span>
                </div>
            </div>
            
            <div class="notification-list">
                <?php if(count($notifications) > 0): ?>
                    <?php foreach($notifications as $notif): ?>
                    <div class="notification-item" data-link="<?php echo isset($notif['link']) ? $notif['link'] : ''; ?>">
                        <div class="notification-icon" style="background: <?php echo $notif['color']; ?>20;">
                            <i class="fas <?php echo $notif['icon']; ?>" style="color: <?php echo $notif['color']; ?>; font-size: 1.25rem;"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                            <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                            <div class="notification-time">
                                <i class="far fa-clock"></i>
                                <?php
                                    $time_diff = time() - strtotime($notif['time']);
                                    if($time_diff < 60) echo "Just now";
                                    elseif($time_diff < 3600) echo floor($time_diff / 60) . " min ago";
                                    elseif($time_diff < 86400) echo floor($time_diff / 3600) . " hours ago";
                                    else echo date('M d', strtotime($notif['time']));
                                ?>
                            </div>
                        </div>
                        <?php if(!$notif['read']): ?>
                        <div class="notification-dot"></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-bell-slash text-2xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-400 text-sm font-medium">No notifications yet</p>
                        <p class="text-xs text-gray-300 mt-1 font-medium">We'll notify you when something arrives</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if(count($notifications) > 0): ?>
            <div class="mark-all-read" onclick="markAllAsRead()">
                <i class="fas fa-check-double mr-2"></i>Mark all as read
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="mb-5 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 flex items-center gap-3 animate-slide-up">
                <i class="fas fa-check-circle text-green-500"></i>
                <span class="font-medium"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="mb-5 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 flex items-center gap-3 animate-slide-up">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <span class="font-medium"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- 1. TOP ROW: ALGORITHMIC KPI WIDGETS (Local Health) -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="kpi-card kpi-pending animate-slide-up" style="animation-delay: 0.05s;">
                <div class="kpi-icon"><i class="fas fa-inbox"></i></div>
                <div class="kpi-label">Pending Acknowledgment</div>
                <div class="kpi-value text-red-600"><?php echo $pending_count; ?></div>
                <div class="kpi-sub">Submitted reports awaiting review</div>
            </div>
            <div class="kpi-card kpi-hotspot animate-slide-up" style="animation-delay: 0.1s;">
                <div class="kpi-icon"><i class="fas fa-map-pin"></i></div>
                <div class="kpi-label">Critical Local Hotspots</div>
                <div class="kpi-value text-amber-600"><?php echo $criticalHotspotsCount; ?></div>
                <div class="kpi-sub">Clusters scoring 16+ / 20</div>
            </div>
            <div class="kpi-card kpi-speed animate-slide-up" style="animation-delay: 0.15s;">
                <div class="kpi-icon"><i class="fas fa-stopwatch"></i></div>
                <div class="kpi-label">Avg Resolution Speed</div>
                <div class="kpi-value text-blue-600"><?php echo $avgResolutionDaysAllTime; ?><span class="text-base font-bold">d</span></div>
                <div class="kpi-sub">
                    <?php if ($resolutionSpeedTrend === 'worse'): ?>
                        <span class="text-red-500 font-semibold"><i class="fas fa-arrow-up"></i> <?php echo abs($resolutionSpeedDelta); ?>d slower vs last month</span>
                    <?php elseif ($resolutionSpeedTrend === 'better'): ?>
                        <span class="text-emerald-500 font-semibold"><i class="fas fa-arrow-down"></i> <?php echo abs($resolutionSpeedDelta); ?>d faster vs last month</span>
                    <?php else: ?>
                        Stable vs last month
                    <?php endif; ?>
                </div>
            </div>
            <div class="kpi-card kpi-rate animate-slide-up" style="animation-delay: 0.2s;">
                <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
                <div class="kpi-label">Resolution Rate (This Month)</div>
                <div class="kpi-value text-emerald-600"><?php echo $resolutionRateThisMonth; ?>%</div>
                <div class="kpi-sub"><?php echo $resolvedThisMonth; ?> of <?php echo $assignedThisMonth; ?> assigned this month</div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- 2. CENTERPIECE: DECISION-SUPPORT LOCAL MAP -->
        <!-- Strictly WHERE barangay_id = ? · same 50m clustering + -->
        <!-- 20-point severity algorithm as the MENRO map, scaled to the LGU -->
        <!-- ============================================================ -->
        <div id="map-container" class="mb-6 animate-slide-up" style="animation-delay: 0.25s;">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                        <i class="fas fa-map-marked-alt text-[#059669]"></i>
                        Local Incident Map
                    </h2>
                    <div class="map-toggle" id="mapToggle">
                        <button class="active" data-mode="active">Active Hazards</button>
                        <button data-mode="historical">Historical / Resolved</button>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 text-xs">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#10B981;"></span> Low (1-<?php echo $criticalBands['yellow'] - 1; ?>)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#F59E0B;"></span> Medium (<?php echo $criticalBands['yellow']; ?>-<?php echo $criticalBands['orange'] - 1; ?>)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#F97316;"></span> High (<?php echo $criticalBands['orange']; ?>-<?php echo $criticalBands['critical'] - 1; ?>)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#EF4444;"></span> Critical (<?php echo $criticalBands['critical']; ?>-20)</span>
                </div>
            </div>

            <!-- Category Filter -->
            <div class="flex flex-wrap justify-between items-center gap-3 mb-3">
                <div class="relative" id="categoryFilterWrap">
                    <button id="categoryFilterBtn" class="flex items-center gap-2 text-sm font-semibold text-gray-700 bg-gray-50 border border-gray-200 rounded-full px-4 py-2 hover:border-[#059669] transition">
                        <i class="fas fa-filter text-[#059669]"></i>
                        <span id="categoryFilterLabel">All Categories</span>
                        <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                    </button>
                    <div id="categoryFilterMenu" class="hidden absolute z-[1100] mt-2 w-64 bg-white rounded-xl border border-gray-200 shadow-lg p-3">
                        <div class="flex justify-between items-center mb-2 pb-2 border-b border-gray-100">
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">Hazard Categories</span>
                            <div class="flex gap-2">
                                <button type="button" id="catSelectAll" class="text-xs text-[#059669] font-semibold hover:underline">All</button>
                                <button type="button" id="catSelectNone" class="text-xs text-gray-400 font-semibold hover:underline">None</button>
                            </div>
                        </div>
                        <div id="categoryCheckboxList" class="max-h-56 overflow-y-auto space-y-1">
                            <?php foreach ($categories as $cat): ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700 px-1 py-1 rounded hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" class="category-checkbox accent-[#059669]" value="<?php echo htmlspecialchars($cat['id']); ?>" checked>
                                <span><?php echo htmlspecialchars($cat['name']); ?></span>
                            </label>
                            <?php endforeach; ?>
                            <?php if (empty($categories)): ?>
                            <p class="text-xs text-gray-400 px-1">No categories found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-gray-400 font-medium">📍 <?php echo count($activeReports); ?> active reports in your barangay</p>
            </div>

            <div id="map" class="overflow-hidden border border-emerald-100"></div>
            <p class="text-xs text-gray-400 mt-2" id="filterSummary"></p>
            <p class="text-xs text-gray-400 mt-2 flex items-center gap-1">
                <i class="fas fa-info-circle"></i>
                Overlapping reports within 50m merge into clusters, colored by the 20-point Severity Score. Click a marker or cluster to analyze.
            </p>
        </div>

        <!-- Drill-down panel -->
        <div id="drillPanel">
            <button class="close-btn" onclick="closeDrillPanel()"><i class="fas fa-times"></i></button>
            <div id="drillContent"></div>
        </div>

        <!-- ============================================================ -->
        <!-- 3. BOTTOM LEFT: LOCAL DEMOGRAPHICS & TRENDS -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
            <!-- User Demographics -->
            <div class="chart-card animate-slide-up" style="animation-delay: 0.3s;">
                <div class="chart-title"><i class="fas fa-users text-[#059669] mr-2"></i>User Demographics</div>
                <?php if (!$demographicsAvailable || $demographicsTotal === 0): ?>
                    <p class="text-sm text-gray-400 py-10 text-center">Demographic data not available yet.</p>
                <?php else: ?>
                <div class="chart-container" style="height:180px;">
                    <canvas id="demographicsChart"></canvas>
                </div>
                <div class="flex justify-center gap-6 text-xs mt-2">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#059669;"></span> Resident (<?php echo $residentPct; ?>%)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background:#F59E0B;"></span> Non-Resident (<?php echo $nonResidentPct; ?>%)</span>
                </div>
                <p class="text-xs text-gray-400 mt-3 text-center">
                    Split of reports submitted in your barangay by residents vs. non-residents.
                </p>
                <?php endif; ?>
            </div>

            <!-- Peak Reporting Times -->
            <div class="chart-card animate-slide-up" style="animation-delay: 0.35s;">
                <div class="chart-title"><i class="fas fa-clock text-[#059669] mr-2"></i>Peak Reporting Times</div>
                <div class="chart-container">
                    <canvas id="peakDayChart"></canvas>
                </div>
                <div class="rec-box <?php echo $peakDayTotal > 0 ? 'rec-medium' : 'rec-low'; ?> mt-4">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>Tanod Scheduling Tip:</strong>
                    <?php if ($peakDayTotal > 0): ?>
                        <?php echo $peakDayShare; ?>% of reports in your barangay arrive on <strong><?php echo $peakDayLabel; ?></strong>. Schedule the most tanods on duty that day.
                    <?php else: ?>
                        Not enough report data yet to identify a peak day.
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Risk Distribution + Issues by Category + Weekly Trends (kept from previous version) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
            <!-- Risk Level Distribution -->
            <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 p-5 animate-slide-up" style="animation-delay: 0.4s;">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-extrabold text-gray-800 flex items-center gap-2 tracking-tight">
                        <i class="fas fa-exclamation-triangle text-[#059669]"></i> 
                        Risk Distribution
                    </h3>
                    <p class="text-xs text-gray-400 font-medium"><?php echo $risk_total; ?> total</p>
                </div>
                <?php if(!empty($risk_data) && $risk_total > 0): ?>
                    <div class="chart-canvas-container">
                        <canvas id="riskChart" style="max-height: 200px;"></canvas>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-4">
                        <?php 
                        $risk_colors = [
                            'critical' => '#EF4444',
                            'high' => '#F97316',
                            'medium' => '#F59E0B',
                            'low' => '#059669'
                        ];
                        foreach($risk_data as $risk): 
                            $percentage = round(($risk['count'] / $risk_total) * 100);
                        ?>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" style="background-color: <?php echo $risk_colors[$risk['risk_level']] ?? '#059669'; ?>"></div>
                                <span class="text-xs font-extrabold text-gray-700"><?php echo ucfirst($risk['risk_level']); ?></span>
                            </div>
                            <p class="text-xl font-extrabold mt-1 tracking-tight"><?php echo $risk['count']; ?></p>
                            <p class="text-xs text-gray-500 font-medium"><?php echo $percentage; ?>% of total</p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="h-48 flex items-center justify-center">
                        <p class="text-gray-400 text-center text-sm font-medium">No risk data available</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Issues by Category -->
            <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 p-5 animate-slide-up" style="animation-delay: 0.45s;">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-extrabold text-gray-800 flex items-center gap-2 tracking-tight">
                        <i class="fas fa-chart-pie text-[#059669]"></i> 
                        Issues by Category
                    </h3>
                    <p class="text-xs text-gray-400 font-medium"><?php echo count($category_data); ?> categories</p>
                </div>
                <?php if(!empty($category_data)): ?>
                    <div class="chart-canvas-container" style="height:160px;">
                        <canvas id="categoryPieChart"></canvas>
                    </div>
                    <div class="space-y-1 mt-2">
                        <?php 
                        $colors = ['#059669', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4'];
                        $total_category = array_sum(array_column($category_data, 'count'));
                        foreach(array_slice($category_data, 0, 4) as $index => $cat): 
                            $percentage = round(($cat['count'] / $total_category) * 100);
                        ?>
                        <div class="flex items-center justify-between px-1 py-1">
                            <div class="flex items-center gap-2">
                                <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo $colors[$index % count($colors)]; ?>"></div>
                                <span class="text-xs font-semibold text-gray-700 truncate max-w-[100px]"><?php echo htmlspecialchars($cat['name']); ?></span>
                            </div>
                            <span class="text-xs font-bold text-gray-800"><?php echo $percentage; ?>%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="h-48 flex items-center justify-center">
                        <p class="text-gray-400 text-center text-sm font-medium">No category data available</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Weekly Resolution Statistics -->
            <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 p-5 animate-slide-up" style="animation-delay: 0.5s;">
                <h3 class="text-lg font-extrabold text-gray-800 mb-3 flex items-center gap-2 tracking-tight">
                    <i class="fas fa-chart-bar text-[#059669]"></i> 
                    Weekly Resolution Trends
                </h3>
                <div class="chart-canvas-container" style="height:160px;">
                    <canvas id="weeklyChart"></canvas>
                </div>
                <div class="grid grid-cols-2 gap-3 mt-4">
                    <div class="bg-emerald-50 rounded-xl p-3 text-center">
                        <p class="text-xs text-gray-500 font-bold">New Reports (7d)</p>
                        <p class="text-xl font-extrabold text-[#059669] tracking-tight"><?php echo array_sum(array_column($weekly_data, 'total')); ?></p>
                    </div>
                    <div class="bg-amber-50 rounded-xl p-3 text-center">
                        <p class="text-xs text-gray-500 font-bold">Resolved (7d)</p>
                        <p class="text-xl font-extrabold text-amber-600 tracking-tight"><?php echo array_sum(array_column($weekly_data, 'resolved')); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Reports Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-50 overflow-hidden animate-slide-up" style="animation-delay: 0.55s;">
            <div class="px-5 py-4 border-b border-emerald-50 bg-[#F5FBF6] flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-extrabold text-gray-800 tracking-tight">Recent Reports</h3>
                    <p class="text-xs text-gray-400 mt-0.5 font-medium">Latest reports submitted in your barangay</p>
                </div>
                <a href="<?php echo BASE_URL; ?>index.php?page=verify-reports" class="text-sm text-[#059669] hover:text-[#047857] font-bold">
                    View All <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="reports-table overflow-x-auto">
                <table class="w-full min-w-[800px]">
                    <thead>
                        <tr class="border-b border-emerald-50 bg-[#F5FBF6]">
                            <th class="px-5 py-3 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-5 py-3 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-5 py-3 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Reporter</th>
                            <th class="px-5 py-3 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-5 py-3 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Risk</th>
                            <th class="px-5 py-3 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-5 py-3 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 0;
                        while($row = $recent_reports->fetch(PDO::FETCH_ASSOC)): 
                            if($counter++ >= 10) break;
                            $risk_level = $row['risk_level'] ?? 'low';
                            $risk_badge = '';
                            if($risk_level == 'low') $risk_badge = 'bg-green-100 text-green-700';
                            elseif($risk_level == 'medium') $risk_badge = 'bg-yellow-100 text-yellow-700';
                            elseif($risk_level == 'high') $risk_badge = 'bg-orange-100 text-orange-700';
                            else $risk_badge = 'bg-red-100 text-red-700';
                            
                            $display_status = $row['status'];
                            if($display_status == 'escalated_pending' || $display_status == 'escalated') {
                                $display_status = 'escalated';
                            }
                            if($display_status == 'rejected') {
                                $display_status = 'declined';
                            }
                        ?>
                        <tr class="border-b border-emerald-50 hover:bg-emerald-50/30 transition">
                            <td class="px-5 py-4 text-sm font-mono text-gray-500 font-medium">#<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($row['title']); ?></p>
                                <p class="text-xs text-gray-400 font-medium"><?php echo htmlspecialchars(substr($row['description'], 0, 50)); ?>...</p>
                            </td>
                            <td class="px-5 py-4 text-sm font-semibold text-gray-600"><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td class="px-5 py-4 text-sm font-medium text-gray-600"><?php echo htmlspecialchars($row['category_name']); ?></td>
                            <td class="px-5 py-4">
                                <span class="px-2.5 py-1 text-xs rounded-full font-extrabold <?php echo $risk_badge; ?>">
                                    <?php echo ucfirst($risk_level); ?>
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="status-badge status-<?php echo $row['status'] == 'rejected' ? 'rejected' : $row['status']; ?>">
                                    <i class="fas <?php echo $row['status'] == 'pending' ? 'fa-clock' : ($row['status'] == 'in_progress' ? 'fa-spinner fa-pulse' : ($row['status'] == 'resolved' ? 'fa-check-circle' : ($row['status'] == 'rejected' ? 'fa-times' : 'fa-share'))); ?> mr-1 text-xs"></i>
                                    <?php echo $display_status == 'escalated' ? 'Escalated' : ($display_status == 'declined' ? 'Declined' : ($display_status == 'in_progress' ? 'In Progress' : ucfirst($display_status))); ?>
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm font-medium text-gray-500"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                            <td class="px-5 py-4">
                                <a href="<?php echo BASE_URL; ?>index.php?page=verify-reports&id=<?php echo $row['id']; ?>" 
                                   class="text-[#059669] hover:text-[#047857] transition text-sm font-bold">
                                    Manage
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($counter == 0): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center">
                                <i class="fas fa-inbox text-5xl text-gray-300 mb-3 block"></i>
                                <p class="text-gray-400 font-medium">No reports found in your barangay</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</div>

<script>
// ========== NOTIFICATION FUNCTIONS ==========
let isDropdownOpen = false;

function toggleNotifications() {
    var dropdown = document.getElementById('notificationDropdown');
    if (!dropdown) return;
    
    if (isDropdownOpen) {
        dropdown.style.display = 'none';
        isDropdownOpen = false;
    } else {
        dropdown.style.display = 'flex';
        isDropdownOpen = true;
    }
}

function handleNotificationClick(link) {
    if (link && link !== '') {
        window.location.href = link;
    }
    document.getElementById('notificationDropdown').style.display = 'none';
    isDropdownOpen = false;
}

function markAllAsRead() {
    document.querySelectorAll('.notification-item .notification-dot').forEach(dot => dot.remove());
    var badge = document.getElementById('notificationBadge');
    if (badge) badge.style.display = 'none';
    setTimeout(() => { document.getElementById('notificationDropdown').style.display = 'none'; isDropdownOpen = false; }, 1500);
}

document.addEventListener('click', function(event) {
    var dropdown = document.getElementById('notificationDropdown');
    var bell = document.querySelector('.notification-bell');
    if (dropdown && bell && isDropdownOpen) {
        if (!dropdown.contains(event.target) && !bell.contains(event.target)) {
            dropdown.style.display = 'none';
            isDropdownOpen = false;
        }
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && isDropdownOpen) {
        document.getElementById('notificationDropdown').style.display = 'none';
        isDropdownOpen = false;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            handleNotificationClick(this.getAttribute('data-link'));
        });
    });
});

// ========== DATA FROM PHP (barangay-scoped only — same fields as MENRO) ==========
const activeReports = <?php echo json_encode($activeReports); ?>;
const historicalReports = <?php echo json_encode($historicalReports); ?>;
const allCategories = <?php echo json_encode($categories); ?>;
const barangayCenter = [
    <?php echo $barangay_info['latitude'] ?? 15.3092; ?>,
    <?php echo $barangay_info['longitude'] ?? 120.9033; ?>
];
const barangayBoundary = <?php echo json_encode($barangay_boundary); ?>;

// ========== FILTER STATE (Category Filter + Active/Historical toggle) ==========
let selectedCategories = new Set(allCategories.map(c => String(c.id)));
let currentMode = 'active';

// ========== SEVERITY COLOR HELPERS (same 20-point algorithm as MENRO) ==========
// Single source of truth for risk bands, mirroring PHP getSeverityBands()
const SEVERITY_BANDS = { yellow: <?php echo $criticalBands['yellow']; ?>, orange: <?php echo $criticalBands['orange']; ?>, critical: <?php echo $criticalBands['critical']; ?> };
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
function getSeverityColor(score) {
    return getRiskColorFromScore(score);
}
function getSeverityTier(score) {
    return getRiskLevelLabelFromScore(score);
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

// ========== LOCAL DECISION-SUPPORT MAP ==========
let map;
let currentLayer = null;

function initMap() {
    // The map loads standard San Isidro tiles, but every marker below is
    // already strictly filtered server-side WHERE barangay_id = ? — the
    // PHP only ever sent this barangay's reports, so Auto-Fit Bounds
    // naturally zooms to just their local pins.
    map = L.map('map').setView(barangayCenter, 15);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);

    // Draw THIS barangay's own boundary (its GeoJSON) so the map shows only
    // their jurisdiction, with the citizen report pins sitting on top of it.
    if (barangayBoundary && barangayBoundary.features) {
        const brgyLayer = L.geoJSON(barangayBoundary, {
            style: {
                color: "#059669",
                weight: 2.5,
                fillColor: "#059669",
                fillOpacity: 0.07,
                smoothFactor: 1
            },
            onEachFeature: function(feature, layer) {
                const name = (feature.properties && feature.properties.name) ? feature.properties.name : 'Barangay';
                layer.bindTooltip(name, { sticky: true });
            }
        }).addTo(map);

        // Frame the map on the barangay polygon so the whole jurisdiction is visible.
        try { map.fitBounds(brgyLayer.getBounds(), { padding: [30, 30], maxZoom: 16 }); } catch(e) {}
    }

    loadMapData('active');
}

function getFilteredData(mode) {
    const source = (mode === 'active') ? activeReports : historicalReports;
    if (!source) return [];
    return source.filter(report => {
        return selectedCategories.size === 0 ? false : selectedCategories.has(String(report.category_id));
    });
}

function updateFilterSummary(mode, count) {
    const modeLabel = (mode === 'active') ? 'active' : 'resolved (historical)';
    const el = document.getElementById('filterSummary');
    if (el) {
        el.textContent = `Showing ${count} ${modeLabel} report(s) in your barangay · ${selectedCategories.size} of ${allCategories.length} categories selected.`;
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
        currentLayer = L.layerGroup().addTo(map);
        // Keep the barangay boundary in view; only recenter to the saved map
        // center when no boundary polygon was drawn.
        if (!barangayBoundary || !barangayBoundary.features) {
            map.setView(barangayCenter, 15);
        }
        return;
    }

    // Algorithm-driven clustering: reports within ~50m merge into a single
    // cluster, colored by average severity score (not just pin volume).
    const clusterGroup = L.markerClusterGroup({
        maxClusterRadius: 60,
        iconCreateFunction: function(cluster) {
            const markers = cluster.getAllChildMarkers();
            let totalScore = 0, count = 0;
            markers.forEach(m => {
                totalScore += (m.options.severityScore || 0);
                count++;
            });
            const avgScore = count > 0 ? totalScore / count : 0;
            const color = getSeverityColor(avgScore);
            const size = 40 + (count * 2);
            return L.divIcon({
                html: `<div style="background: ${color}; width: ${size}px; height: ${size}px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: ${size/2}px; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">${count}</div>`,
                iconSize: [size, size],
                className: 'cluster-icon'
            });
        }
    });

    data.forEach(report => {
        const lat = parseFloat(report.latitude);
        const lng = parseFloat(report.longitude);
        if (isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0) return;

        const score = parseInt(report.severity_score) || 0;
        const color = getSeverityColor(score);
        const tier = getSeverityTier(score);
        const popupContent = `
            <div style="font-family: Manrope, sans-serif; min-width: 200px;">
                <strong style="font-size: 14px; color:#1f2937;">#${String(report.id).padStart(5,'0')} — ${escapeHtml(report.title)}</strong><br>
                <span style="font-size: 12px; color: #64748b;">Severity: ${score}/20 (${tier})</span><br>
                <span style="font-size: 12px; color: #64748b;">Reports in cluster: ${report.spatial_density_count || 0}</span><br>
                <button onclick="openDrillPanel(${report.id})" style="margin-top: 6px; background: #059669; color: white; border: none; border-radius: 6px; padding: 4px 12px; font-size: 12px; cursor: pointer;">Analyze</button>
            </div>
        `;

        const icon = L.divIcon({
            html: `<div style="background: ${color}; width: 24px; height: 24px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);"></div>`,
            iconSize: [24, 24],
            className: 'severity-marker'
        });

        const marker = L.marker([lat, lng], { icon: icon, severityScore: score }).bindPopup(popupContent);
        marker.on('click', function() { openDrillPanel(report.id); });
        clusterGroup.addLayer(marker);
    });

    currentLayer = clusterGroup;
    map.addLayer(clusterGroup);

    // Auto-fit bounds to just this barangay's pins
    const bounds = L.latLngBounds(data.map(r => [r.latitude, r.longitude]));
    map.fitBounds(bounds, { padding: [30, 30], maxZoom: 17 });
}

// ========== TIME MACHINE TOGGLE (Active Hazards vs Historical/Resolved) ==========
document.getElementById('mapToggle').addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;
    const mode = btn.dataset.mode;
    if (mode === currentMode) return;
    currentMode = mode;
    this.querySelectorAll('button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadMapData(mode);
});

// ========== CATEGORY FILTER DROPDOWN ==========
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
    document.querySelectorAll('.category-checkbox').forEach(cb => { cb.checked = false; });
    selectedCategories.clear();
    updateCategoryLabel();
    loadMapData(currentMode);
});

// ========== DRILL-DOWN PANEL (reuses the existing ReportController endpoint) ==========
function openDrillPanel(reportId) {
    document.getElementById('drillContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-[#059669]"></i><p class="mt-2 text-gray-500">Loading analysis...</p></div>';
    document.getElementById('drillPanel').classList.add('open');

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
    const recClass = 'rec-' + riskLevel;
    const recText = getRiskRecommendation(score);

    const baseWeight = report.base_weight || 5;
    const impactModifier = report.impact_modifier || 0;
    const densityPoints = report.spatial_density_factor || 0;

    const html = `
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">${escapeHtml(report.title)}</h3>
            <span class="text-sm font-mono bg-gray-100 px-2 py-1 rounded">#${String(report.id).padStart(5,'0')}</span>
        </div>

        <div class="mb-4">
            <div class="flex items-center justify-between p-3 rounded-xl ${riskLevel === 'low' ? 'bg-green-50 text-green-800' : riskLevel === 'medium' ? 'bg-yellow-50 text-yellow-800' : riskLevel === 'high' ? 'bg-orange-50 text-orange-800' : 'bg-red-50 text-red-800'}">
                <span class="font-bold">Severity Score</span>
                <span class="text-2xl font-extrabold">${score} / 20</span>
            </div>
        </div>

        <div class="bg-gray-50 rounded-xl p-4 mb-4">
            <h4 class="font-semibold text-gray-700 mb-2">Score Breakdown</h4>
            <div class="score-row"><span class="label">Base Weight (Category)</span><span class="value">${baseWeight} pts</span></div>
            <div class="score-row"><span class="label">Impact Modifier</span><span class="value">${impactModifier} pts</span></div>
            <div class="score-row"><span class="label">Spatial Density (${report.spatial_density_count || 0} nearby)</span><span class="value">${densityPoints} pts</span></div>
            <div class="score-row border-t border-gray-300 pt-2 mt-2 font-bold"><span>Total</span><span>${score} / 20</span></div>
        </div>

        <div class="rec-box ${recClass}">
            <i class="fas fa-lightbulb mr-2"></i>
            <strong>System Recommendation:</strong> ${recText}
        </div>

        <div class="mt-4">
            <h4 class="font-semibold text-gray-700 mb-2">Citizen Evidence (${report.spatial_density_count || 0} reports)</h4>
            <p class="text-sm text-gray-500">View full report for complete evidence list.</p>
            <div class="mt-2">
                <a href="<?php echo BASE_URL; ?>index.php?page=verify-reports&id=${report.id}" target="_blank" class="text-[#059669] hover:underline text-sm">
                    <i class="fas fa-external-link-alt mr-1"></i>Open in Verify Reports
                </a>
            </div>
        </div>

        <div class="mt-4 text-xs text-gray-400">
            <i class="far fa-calendar-alt mr-1"></i>Reported: ${new Date(report.created_at).toLocaleString()}
        </div>
    `;
    document.getElementById('drillContent').innerHTML = html;
}

function closeDrillPanel() {
    document.getElementById('drillPanel').classList.remove('open');
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDrillPanel();
});

// ========== CHARTS ==========
<?php if(!empty($risk_data) && $risk_total > 0): ?>
const riskCtx = document.getElementById('riskChart').getContext('2d');
new Chart(riskCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php foreach($risk_data as $r) echo "'" . ucfirst($r['risk_level']) . "',"; ?>],
        datasets: [{
            data: [<?php foreach($risk_data as $r) echo $r['count'] . ","; ?>],
            backgroundColor: ['#EF4444', '#F97316', '#F59E0B', '#059669'],
            borderWidth: 0
        }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, cutout: '60%' }
});
<?php endif; ?>

<?php if(!empty($category_data)): ?>
const categoryCtx = document.getElementById('categoryPieChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php foreach($category_data as $cat) echo "'" . addslashes($cat['name']) . "',"; ?>],
        datasets: [{
            data: [<?php foreach($category_data as $cat) echo $cat['count'] . ","; ?>],
            backgroundColor: ['#059669', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4'],
            borderWidth: 0
        }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, cutout: '60%' }
});
<?php endif; ?>

const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
new Chart(weeklyCtx, {
    type: 'bar',
    data: {
        labels: [<?php foreach($weekly_data as $w) echo "'" . $w['day'] . "',"; ?>],
        datasets: [
            { label: 'Total', data: [<?php foreach($weekly_data as $w) echo $w['total'] . ","; ?>], backgroundColor: '#059669', borderRadius: 6, barPercentage: 0.7 },
            { label: 'Resolved', data: [<?php foreach($weekly_data as $w) echo $w['resolved'] . ","; ?>], backgroundColor: '#F59E0B', borderRadius: 6, barPercentage: 0.7 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        scales: { y: { beginAtZero: true, grid: { color: '#E5E7EB' }, ticks: { font: { size: 9 } } }, x: { grid: { display: false }, ticks: { font: { size: 9 } } } },
        plugins: { legend: { position: 'top', labels: { font: { size: 10, family: 'Manrope', weight: '600' }, boxWidth: 10 } } }
    }
});

<?php if ($demographicsAvailable && $demographicsTotal > 0): ?>
const demographicsCtx = document.getElementById('demographicsChart').getContext('2d');
new Chart(demographicsCtx, {
    type: 'doughnut',
    data: {
        labels: ['Resident', 'Non-Resident'],
        datasets: [{
            data: [<?php echo $demographics['resident']; ?>, <?php echo $demographics['non_resident']; ?>],
            backgroundColor: ['#059669', '#F59E0B'],
            borderWidth: 0
        }]
    },
    options: { cutout: '65%', plugins: { legend: { display: false } }, responsive: true, maintainAspectRatio: false }
});
<?php endif; ?>

const peakDayCtx = document.getElementById('peakDayChart').getContext('2d');
new Chart(peakDayCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($dayLabels); ?>,
        datasets: [{
            label: 'Reports by Day',
            data: <?php echo json_encode($dayCounts); ?>,
            backgroundColor: '#059669',
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
        responsive: true, maintainAspectRatio: true
    }
});

// ========== REAL-TIME CLOCK ==========
function updateClock() {
    var now = new Date();
    var hours = now.getHours();
    var minutes = now.getMinutes();
    var ampm = hours >= 12 ? 'PM' : 'AM';
    var displayHours = hours % 12 || 12;
    document.getElementById('currentTime').textContent = displayHours.toString().padStart(2, '0') + ':' + minutes.toString().padStart(2, '0');
    document.getElementById('currentPeriod').textContent = ampm;
}
setInterval(updateClock, 1000);
updateClock();

document.addEventListener('DOMContentLoaded', initMap);
</script>
</body>
</html>