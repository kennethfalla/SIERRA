<?php
// views/settings/partials/map.php
// Section 7: Map Settings — calibrates geospatial analytics and the heatmap.
// Included into the settings page shell when ?tab=map is active.
// Expects $csrf_token to already be available in scope (as with the other partials).

$mapSettings = SettingsHelper::getMapSettings();
?>
<div class="fade-in">
    <div class="stat-card bg-white rounded-xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Map Settings</h2>
        <p class="text-sm text-gray-500 mb-6">Calibrates the geospatial analytics and heatmap.</p>

        <form method="POST" action="<?php echo BASE_URL; ?>controllers/SettingsController.php?tab=map" data-validate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <!-- Clustering Radius -->
            <div class="mb-8">
                <label class="form-label" for="clustering_radius_meters">
                    Clustering Radius
                    <span class="text-gray-400 font-normal">— group hazards within this distance of each other</span>
                </label>
                <div class="flex items-center gap-4">
                    <input
                        type="range"
                        id="clustering_radius_meters"
                        name="clustering_radius_meters"
                        min="10"
                        max="200"
                        step="5"
                        value="<?php echo (int)$mapSettings['clustering_radius_meters']; ?>"
                        class="w-full accent-emerald-500"
                        oninput="document.getElementById('clusteringRadiusValue').textContent = this.value + ' m'"
                    >
                    <span id="clusteringRadiusValue" class="text-sm font-semibold text-gray-700 w-16 text-right">
                        <?php echo (int)$mapSettings['clustering_radius_meters']; ?> m
                    </span>
                </div>
                <div class="flex justify-between text-xs text-gray-400 mt-1">
                    <span>10 m</span>
                    <span>200 m</span>
                </div>
            </div>

            <!-- Default Map Center -->
            <div class="mb-8">
                <label class="form-label">
                    Default Map Center
                    <span class="text-gray-400 font-normal">— the map always loads centered here (San Isidro)</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label text-xs" for="map_default_lat">Latitude</label>
                        <input
                            type="text"
                            id="map_default_lat"
                            name="map_default_lat"
                            inputmode="decimal"
                            pattern="-?[0-9]*\.?[0-9]+"
                            required
                            class="form-input"
                            value="<?php echo htmlspecialchars($mapSettings['default_lat']); ?>"
                            placeholder="e.g. 15.3092"
                        >
                    </div>
                    <div>
                        <label class="form-label text-xs" for="map_default_lng">Longitude</label>
                        <input
                            type="text"
                            id="map_default_lng"
                            name="map_default_lng"
                            inputmode="decimal"
                            pattern="-?[0-9]*\.?[0-9]+"
                            required
                            class="form-input"
                            value="<?php echo htmlspecialchars($mapSettings['default_lng']); ?>"
                            placeholder="e.g. 120.9033"
                        >
                    </div>
                </div>
                <button
                    type="button"
                    id="pickOnMapBtn"
                    class="mt-3 text-sm text-emerald-600 hover:text-emerald-700 font-medium"
                    onclick="enablePickMode()"
                >
                    <i class="fas fa-map-marker-alt mr-1"></i> Click the preview map to set coordinates
                </button>
            </div>

            <!-- Default Zoom Level -->
            <div class="mb-8">
                <label class="form-label" for="map_default_zoom">
                    Default Zoom Level
                    <span class="text-gray-400 font-normal">— how zoomed-in the map starts (1 = whole world, 19 = street level)</span>
                </label>
                <input
                    type="number"
                    id="map_default_zoom"
                    name="map_default_zoom"
                    min="1"
                    max="19"
                    step="1"
                    required
                    class="form-input max-w-[150px]"
                    value="<?php echo (int)$mapSettings['default_zoom']; ?>"
                >
            </div>

            <!-- Live Preview -->
            <div class="mb-6">
                <label class="form-label">Preview</label>
                <div id="mapSettingsPreview" class="map-container" style="height: 320px;"></div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i>Save Map Settings
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let settingsPreviewMap = null;
    let settingsPreviewMarker = null;
    let pickModeActive = false;

    function renderSettingsPreview() {
        const latInput = document.getElementById('map_default_lat');
        const lngInput = document.getElementById('map_default_lng');
        const zoomInput = document.getElementById('map_default_zoom');

        const lat = parseFloat(latInput.value) || <?php echo (float)$mapSettings['default_lat']; ?>;
        const lng = parseFloat(lngInput.value) || <?php echo (float)$mapSettings['default_lng']; ?>;
        const zoom = parseInt(zoomInput.value, 10) || <?php echo (int)$mapSettings['default_zoom']; ?>;

        if (!settingsPreviewMap) {
            settingsPreviewMap = L.map('mapSettingsPreview').setView([lat, lng], zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(settingsPreviewMap);

            settingsPreviewMap.on('click', function (e) {
                if (!pickModeActive) return;
                latInput.value = e.latlng.lat.toFixed(6);
                lngInput.value = e.latlng.lng.toFixed(6);
                placeSettingsPreviewMarker(e.latlng.lat, e.latlng.lng);
                pickModeActive = false;
                document.getElementById('pickOnMapBtn').classList.remove('text-red-600');
            });
        } else {
            settingsPreviewMap.setView([lat, lng], zoom);
        }

        placeSettingsPreviewMarker(lat, lng);
    }

    function placeSettingsPreviewMarker(lat, lng) {
        if (settingsPreviewMarker) {
            settingsPreviewMap.removeLayer(settingsPreviewMarker);
        }
        settingsPreviewMarker = L.marker([lat, lng]).addTo(settingsPreviewMap);
        settingsPreviewMarker.bindPopup('Default map center');
    }

    function enablePickMode() {
        pickModeActive = true;
        document.getElementById('pickOnMapBtn').classList.add('text-red-600');
        showNotification('Click anywhere on the preview map to set the default center', 'info');
    }

    document.getElementById('map_default_lat').addEventListener('change', renderSettingsPreview);
    document.getElementById('map_default_lng').addEventListener('change', renderSettingsPreview);
    document.getElementById('map_default_zoom').addEventListener('change', renderSettingsPreview);

    document.addEventListener('DOMContentLoaded', renderSettingsPreview);
    // In case this tab is loaded via AJAX after DOMContentLoaded already fired:
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        renderSettingsPreview();
    }
</script>