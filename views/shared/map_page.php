<?php
require_once BASE_PATH . 'config/config.php';
require_once BASE_PATH . 'helpers/SettingsHelper.php';
$mapPageRole = $_SESSION['user_role'] ?? '';
requireRole($mapPageRole === 'admin' ? 'admin' : 'barangay_official');
$db = (new Database())->getConnection();
$hazardMapFull = true;
require BASE_PATH . 'views/shared/hazard_map_data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="<?php echo BASE_URL; ?>assets/js/theme.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/theme.js'); ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo t('Environmental Map - Sierra'); ?></title>
    <link rel="icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl() ?: BASE_URL . 'assets/images/sierra-favicon.svg', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/manrope/manrope.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/vendor/leaflet/leaflet.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/hazard-map.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/hazard-map.css'); ?>">
    <script src="<?php echo BASE_URL; ?>assets/vendor/leaflet/leaflet.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/map-layers.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/map-layers.js'); ?>"></script>
</head>
<body class="map-page" style="background:#f4f8f5;font-family:Manrope,sans-serif">
<?php include BASE_PATH . 'views/layouts/sidebar.php'; ?>
<main id="main-content" tabindex="-1" class="lg:ml-72 min-h-screen" role="main">
    <div class="main-container max-w-7xl mx-auto" style="padding:clamp(1rem,2vw,1.8rem)">
        <?php include BASE_PATH . 'views/shared/hazard_map.php'; ?>
    </div>
</main>
</body>
</html>
