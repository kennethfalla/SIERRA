<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . 'helpers/SettingsHelper.php';
require_once BASE_PATH . 'helpers/Lang.php';
requireRole('admin');
$db = (new Database())->getConnection();

$summary = $db->query("SELECT COUNT(*) AS total,
    SUM(status = 'pending') AS pending,
    SUM(status IN ('under_review', 'verified', 'in_progress', 'escalated_pending', 'escalated')) AS ongoing,
    SUM(status = 'resolved') AS resolved,
    SUM(risk_level IN ('high', 'critical') AND status NOT IN ('resolved', 'rejected', 'cancelled')) AS urgent
    FROM reports")->fetch(PDO::FETCH_ASSOC) ?: [];
$responseDays = $db->query("SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) / 24, 1)
    FROM reports WHERE status = 'resolved' AND resolved_at IS NOT NULL")->fetchColumn();
$urgentReports = $db->query("SELECT r.id, r.title, r.risk_level, r.created_at, b.name AS barangay_name
    FROM reports r LEFT JOIN barangays b ON b.id = r.barangay_id
    WHERE r.risk_level IN ('high', 'critical') AND r.status NOT IN ('resolved', 'rejected', 'cancelled')
    ORDER BY FIELD(r.risk_level, 'critical', 'high'), r.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$barangayReports = $db->query("SELECT b.name, COUNT(*) AS total,
    SUM(r.status NOT IN ('resolved', 'rejected', 'cancelled')) AS active
    FROM reports r JOIN barangays b ON b.id = r.barangay_id
    GROUP BY b.id, b.name ORDER BY active DESC, total DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
$recentReports = $db->query("SELECT r.id, r.title, r.status, r.created_at, b.name AS barangay_name
    FROM reports r LEFT JOIN barangays b ON b.id = r.barangay_id
    ORDER BY r.created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
try {
    $recentActivity = $db->query("SELECT action, description, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    $recentActivity = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="<?php echo BASE_URL; ?>assets/js/theme.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/theme.js'); ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo t('MENRO Dashboard - Sierra'); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard-loading.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard-hero.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/dashboard-hero.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/hazard-map.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/hazard-map.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/menro-dashboard.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/menro-dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/leaflet/leaflet.css">
    <script src="<?php echo BASE_URL; ?>assets/vendor/leaflet/leaflet.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/map-layers.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/map-layers.js'); ?>"></script>
</head>
<body class="dashboard-page menro-dashboard-page">
<?php $dashboard_loading_initial = true; include BASE_PATH . 'views/shared/dashboard_loading.php'; ?>
<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>
<main id="main-content" tabindex="-1" class="lg:ml-72 min-h-screen" role="main">
    <div class="main-container max-w-7xl mx-auto">
        <?php include BASE_PATH . 'views/shared/dashboard_hero.php'; ?>
        <div class="menro-dash-heading"><div><span><?php echo t('Current situation'); ?></span><h1><?php echo t('Environmental Overview'); ?></h1><p><?php echo t('What needs attention across San Isidro today.'); ?></p></div><?php if (PermissionHelper::userHasPermission('can_view_analytics')): ?><a href="<?php echo BASE_URL; ?>index.php?page=analytics"><?php echo t('Explore Analytics'); ?> <i class="fas fa-arrow-right"></i></a><?php endif; ?></div>
        <section class="menro-stat-grid" aria-label="Current report statistics">
            <?php foreach ([
                ['Total Reports', 'total', 'fa-file-lines'],
                ['Pending', 'pending', 'fa-clock'],
                ['In Progress', 'ongoing', 'fa-spinner'],
                ['Resolved', 'resolved', 'fa-circle-check'],
                ['Urgent', 'urgent', 'fa-triangle-exclamation']
            ] as $stat): ?>
            <div class="menro-stat-card menro-stat-<?php echo $stat[1]; ?>"><i class="fas <?php echo $stat[2]; ?>" aria-hidden="true"></i><span><?php echo t($stat[0]); ?></span><strong><?php echo number_format((int)($summary[$stat[1]] ?? 0)); ?></strong></div>
            <?php endforeach; ?>
        </section>
        <section class="menro-dash-middle" aria-label="Current environmental situation">
            <div class="menro-map-span"><?php include BASE_PATH . 'views/shared/hazard_map.php'; ?></div>
            <article class="menro-dash-card menro-response-card"><div class="menro-card-title"><i class="fas fa-stopwatch"></i><h2><?php echo t('Average Response Time'); ?></h2></div><div class="menro-response-value"><?php echo $responseDays !== false && $responseDays !== null ? htmlspecialchars((string)$responseDays) : '—'; ?><small><?php echo t('days'); ?></small></div><p><?php echo t('Average time from submission to resolution.'); ?></p><a href="<?php echo BASE_URL; ?>index.php?page=analytics"><?php echo t('View response analysis'); ?> <i class="fas fa-arrow-right"></i></a></article>
            <article class="menro-dash-card menro-urgent-card"><div class="menro-card-title"><i class="fas fa-triangle-exclamation"></i><h2><?php echo t('Recent Urgent Reports'); ?></h2></div><?php if (!$urgentReports): ?><p class="menro-empty"><?php echo t('No urgent reports need attention.'); ?></p><?php endif; ?><?php foreach ($urgentReports as $report): ?><a class="menro-list-row" href="<?php echo BASE_URL; ?>index.php?page=manage-report&amp;id=<?php echo rawurlencode(IdGuard::enc((int)$report['id'])); ?>"><span><strong><?php echo htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8'); ?></strong><small><?php echo htmlspecialchars($report['barangay_name'] ?: 'San Isidro', ENT_QUOTES, 'UTF-8'); ?></small></span><em class="risk-badge risk-<?php echo htmlspecialchars($report['risk_level'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($report['risk_level']), ENT_QUOTES, 'UTF-8'); ?></em></a><?php endforeach; ?></article>
            <article class="menro-dash-card menro-barangay-card"><div class="menro-card-title"><i class="fas fa-map-location-dot"></i><h2><?php echo t('Reports by Barangay'); ?></h2></div><div class="menro-barangay-grid"><?php if (!$barangayReports): ?><p class="menro-empty"><?php echo t('No barangay reports yet.'); ?></p><?php endif; ?><?php $maxBarangayReports = $barangayReports ? max(array_column($barangayReports, 'total')) : 1; foreach ($barangayReports as $barangay): ?><div class="menro-barangay-row"><span><?php echo htmlspecialchars($barangay['name'], ENT_QUOTES, 'UTF-8'); ?></span><div><i style="width:<?php echo round(100 * (int)$barangay['total'] / max(1, (int)$maxBarangayReports)); ?>%"></i></div><strong><?php echo (int)$barangay['total']; ?></strong></div><?php endforeach; ?></div></article>

            <article class="menro-dash-card"><div class="menro-card-title"><i class="fas fa-list"></i><h2><?php echo t('Recent Reports'); ?></h2><a href="<?php echo BASE_URL; ?>index.php?page=all-reports"><?php echo t('View all'); ?></a></div><?php if (!$recentReports): ?><p class="menro-empty"><?php echo t('No reports yet.'); ?></p><?php endif; ?><?php foreach ($recentReports as $report): ?><a class="menro-list-row" href="<?php echo BASE_URL; ?>index.php?page=manage-report&amp;id=<?php echo rawurlencode(IdGuard::enc((int)$report['id'])); ?>"><span><strong><?php echo htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8'); ?></strong><small><?php echo htmlspecialchars($report['barangay_name'] ?: 'San Isidro', ENT_QUOTES, 'UTF-8'); ?> · <?php echo date('M d, Y', strtotime($report['created_at'])); ?></small></span><em class="status-badge status-<?php echo htmlspecialchars($report['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $report['status'])), ENT_QUOTES, 'UTF-8'); ?></em></a><?php endforeach; ?></article>
            <div class="menro-announcements"><?php $dashAnnHeading = 'Recent Announcements'; include BASE_PATH . 'views/shared/dashboard_announcements.php'; ?></div>
            <article class="menro-dash-card"><div class="menro-card-title"><i class="fas fa-clock-rotate-left"></i><h2><?php echo t('Recent Activity'); ?></h2></div><?php if (!$recentActivity): ?><p class="menro-empty"><?php echo t('No recent activity.'); ?></p><?php endif; ?><?php foreach ($recentActivity as $activity): ?><div class="menro-list-row"><span><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $activity['action'] ?: 'Activity')), ENT_QUOTES, 'UTF-8'); ?></strong><small><?php echo htmlspecialchars($activity['description'] ?: '', ENT_QUOTES, 'UTF-8'); ?></small></span><time><?php echo date('M d · g:i A', strtotime($activity['created_at'])); ?></time></div><?php endforeach; ?></article>
        </section>
    </div>
</main>
</body>
</html>
