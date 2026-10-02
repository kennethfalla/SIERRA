<?php
if (!empty($sierra_loading_included)) return;
$sierra_loading_included = true;
$initial_page_loading = !empty($_SESSION['initial_page_loading']);
unset($_SESSION['initial_page_loading']);
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard-loading.css?v=<?php echo filemtime(BASE_PATH . 'assets/css/dashboard-loading.css'); ?>">
<div id="dashboardLoading" class="dashboard-loading" role="status" aria-live="polite" data-initial-load="<?php echo $initial_page_loading ? '1' : '0'; ?>" hidden>
    <div class="dashboard-loading-sidebar" aria-hidden="true">
        <div class="dashboard-loading-line medium"></div>
        <div class="dashboard-loading-line long"></div>
        <div class="dashboard-loading-line medium"></div>
        <div class="dashboard-loading-line long"></div>
        <div class="dashboard-loading-line short"></div>
    </div>
    <div class="dashboard-loading-main" aria-hidden="true">
        <div class="dashboard-loading-top">
            <div class="dashboard-loading-line medium"></div>
            <div class="dashboard-loading-line"></div>
        </div>
        <div class="dashboard-loading-hero">
            <div class="dashboard-loading-line short"></div>
            <div class="dashboard-loading-line long tall"></div>
            <div class="dashboard-loading-line medium"></div>
        </div>
        <div class="dashboard-loading-kpis">
            <?php for ($loading_card = 0; $loading_card < 4; $loading_card++): ?>
                <div class="dashboard-loading-card">
                    <div class="dashboard-loading-line medium"></div>
                    <div class="dashboard-loading-line short tall"></div>
                </div>
            <?php endfor; ?>
        </div>
        <div class="dashboard-loading-panels">
            <div class="dashboard-loading-card"><div class="dashboard-loading-line medium"></div><div class="dashboard-loading-line long"></div></div>
            <div class="dashboard-loading-card"><div class="dashboard-loading-line medium"></div><div class="dashboard-loading-line long"></div></div>
        </div>
    </div>
    <span class="sr-only">Loading your workspace</span>
</div>
<script src="<?php echo BASE_URL; ?>assets/js/dashboard-loading.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/dashboard-loading.js'); ?>"></script>
