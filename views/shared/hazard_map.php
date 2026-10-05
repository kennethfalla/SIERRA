<?php
if (!isset($hazardMapReports)) require BASE_PATH . 'views/shared/hazard_map_data.php';
$hazardMapFull = $hazardMapFull ?? false;
?>
<section class="sierra-map-panel <?php echo $hazardMapFull ? 'sierra-map-panel-full' : ''; ?>" aria-label="Environmental hazard map">
    <div class="sierra-map-heading">
        <div><h2><i class="fas fa-map-marked-alt" aria-hidden="true"></i><?php echo t('Environmental Hazard Map'); ?></h2></div>
        <div class="sierra-map-heading-actions">
            <?php if ($hazardMapFull): ?>
            <?php
            $mapCategoryOptions = ['' => t('All Hazards')];
            foreach ($hazardMapCategories as $mapCat) { $mapCategoryOptions[(string)$mapCat['id']] = $mapCat['name']; }
            $mapBarangayOptions = ['' => t('All Barangays')];
            foreach (($hazardMapBarangays ?: []) as $mapBarangay) { $mapBarangayOptions[(string)$mapBarangay['id']] = $mapBarangay['name']; }
            $ft = [
                'search_id'          => 'searchInput',
                'search_value'       => '',
                'show_search'        => false,
                'in_header'          => false,
                'results_text'       => '',
                'inline_selects'     => [],
                'filter_by'          => ['active' => false, 'count' => 0],
                'popover_fields'     => [
                    ['kind' => 'select', 'id' => 'popoverCategory', 'label' => t('Hazard Category'), 'value' => '', 'default' => '', 'multi' => true, 'options' => $mapCategoryOptions],
                    ['kind' => 'select', 'id' => 'popoverRisk', 'label' => t('Severity'), 'value' => '', 'default' => '', 'multi' => true, 'options' => ['' => t('All Severities'), 'low' => t('Low'), 'medium' => t('Medium'), 'high' => t('High'), 'critical' => t('Critical')]],
                ],
                'active_filters'     => 0,
                'chips'              => [],
                'callback'           => 'applyMapFilters',
                'compact_breakpoint' => 1199,
                'more_icon'          => 'fa-sliders-h',
            ];
            if ($hazardMapBarangays) {
                $ft['popover_fields'][] = ['kind' => 'select', 'id' => 'popoverBarangay', 'label' => t('Barangay'), 'value' => '', 'default' => '', 'multi' => true, 'options' => $mapBarangayOptions];
            }
            ?>
            <div class="map-filter-toolbar"><?php include __DIR__ . '/report_filter_toolbar.php'; ?></div>
            <?php endif; ?>
            <?php if (!$hazardMapFull): ?><a class="sierra-map-full-link" href="<?php echo BASE_URL; ?>index.php?page=map"><i class="fas fa-expand" aria-hidden="true"></i> <?php echo t('View Full Map'); ?></a><?php endif; ?>
        </div>
    </div>
    <?php if ($hazardMapFull): ?>
    <div class="sierra-map-controls">
        <div class="sierra-map-segment" role="group" aria-label="Map mode">
            <button type="button" data-map-mode="active" class="active"><?php echo t('Active Hotspots'); ?></button>
            <button type="button" data-map-mode="historical"><?php echo t('Historical Trends'); ?></button>
        </div>
        <div class="sierra-map-filter-row">
            <select id="sierraMapPeriod" hidden aria-label="Date range"><option value="all">All dates</option><option value="today">Today</option><option value="week">This week</option><option value="month">This month</option><option value="year">This year</option><option value="custom">Custom</option></select>
            <div class="sierra-map-periods" role="group" aria-label="Date range"><?php foreach (['all'=>'All Dates','today'=>'Today','week'=>'Week','month'=>'Month','year'=>'Year','custom'=>'Custom'] as $mapPeriod=>$mapPeriodLabel): ?><button type="button" data-map-period="<?php echo $mapPeriod; ?>" class="<?php echo $mapPeriod === 'all' ? 'active' : ''; ?>"><?php echo t($mapPeriodLabel); ?></button><?php endforeach; ?></div>
        </div>
    </div>
    <div class="sierra-map-dates" id="sierraMapDates" hidden><label><?php echo t('From'); ?> <input type="date" id="sierraMapFrom"></label><label><?php echo t('To'); ?> <input type="date" id="sierraMapTo"></label></div>
    <?php endif; ?>
    <div id="sierraHazardMap" class="sierra-hazard-map" role="application" aria-label="Interactive environmental hazard map"></div>
    <div class="sierra-map-foot"><span><span class="sierra-risk-dot low"></span><?php echo t('Low'); ?></span><span><span class="sierra-risk-dot medium"></span><?php echo t('Medium'); ?></span><span><span class="sierra-risk-dot high"></span><?php echo t('High'); ?></span><span><span class="sierra-risk-dot critical"></span><?php echo t('Critical'); ?></span><strong id="sierraMapCount" aria-live="polite"></strong></div>
</section>
<?php include BASE_PATH . 'views/shared/map_report_panel.php'; ?>
<script>window.sierraHazardMapData = <?php echo json_encode([
    'reports' => $hazardMapReports,
    'boundary' => $hazardMapBoundary,
    'defaults' => $hazardMapDefaults,
    'severityBands' => getSeverityBands(),
    'focusBoundary' => $hazardMapRole === 'barangay_official',
    'fullPage' => $hazardMapFull,
    'barangayBoundaries' => $hazardMapBarangayBoundaries ?? null,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;</script>
<script src="<?php echo BASE_URL; ?>assets/js/hazard-map.js?v=<?php echo filemtime(BASE_PATH . 'assets/js/hazard-map.js'); ?>" defer></script>
