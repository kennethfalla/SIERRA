<?php if (!empty($sierraMapReportPanelIncluded)) return; $sierraMapReportPanelIncluded = true; ?>
<div id="mapReportBackdrop" class="map-report-backdrop" hidden></div>
<aside id="drillPanel" class="map-report-panel" role="dialog" aria-modal="true" aria-labelledby="mapReportPanelTitle" aria-hidden="true" inert
       data-detail-url="<?php echo htmlspecialchars(BASE_URL . 'controllers/ReportController.php?action=get_full&id=', ENT_QUOTES, 'UTF-8'); ?>"
       data-asset-url="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>"
       data-report-url="<?php echo htmlspecialchars(BASE_URL . 'index.php?page=manage-report&id=', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="map-report-panel-head"><h2 id="mapReportPanelTitle"><?php echo t('Report Details'); ?></h2><button type="button" id="mapReportClose" aria-label="Close report details"><span aria-hidden="true">&times;</span></button></div>
    <div id="drillContent" class="drill-body" aria-live="polite"></div>
</aside>
<script src="<?php echo BASE_URL; ?>assets/js/map-report-panel.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/map-report-panel.js'); ?>"></script>
