<?php
// views/settings/partials/map.php
// Section 7: Map Settings — calibrates geospatial analytics and the heatmap,
// plus per-barangay GeoJSON boundary management.
// Included into the settings page shell when ?tab=map is active.
// Expects $csrf_token to already be available in scope (as with the other partials).

$mapSettings = SettingsHelper::getMapSettings();

// Barangay boundary GeoJSON management
$database = new Database();
$brgy_db = $database->getConnection();
$brgy_stmt = $brgy_db->query("SELECT id, name FROM barangays ORDER BY name ASC");
$barangays = $brgy_stmt->fetchAll(PDO::FETCH_ASSOC);

$barangay_dir = BASE_PATH . 'geojson/barangay/';

// Index existing boundary files by their declared barangay name so the file
// for "Santo Cristo" (saved as sto-cristo.geojson) is matched correctly.
$boundary_files_by_name = [];
foreach (glob($barangay_dir . '*.geojson') as $candidate) {
    $base = basename($candidate);
    if ($base === 'san-isidro.barangay.geojson') continue;
    $decoded = json_decode(file_get_contents($candidate), true);
    if (!is_array($decoded)) continue;
    foreach (($decoded['features'] ?? []) as $feat) {
        $declared = $feat['properties']['barangay_name'] ?? $feat['properties']['name'] ?? '';
        if ($declared !== '') {
            $key = strtolower(preg_replace('/[^a-z0-9]+/', '', $declared));
            $boundary_files_by_name[$key] = $candidate;
        }
    }
}

$barangay_boundaries = [];
foreach ($barangays as $b) {
    $name_key = strtolower(preg_replace('/[^a-z0-9]+/', '', trim($b['name'])));
    $existing_file = $boundary_files_by_name[$name_key] ?? null;
    $slug = strtolower(trim($b['name']));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    $file_path = $existing_file ?: ($barangay_dir . $slug . '.geojson');
    $barangay_boundaries[$b['id']] = [
        'name' => $b['name'],
        'slug' => $existing_file ? basename($existing_file, '.geojson') : $slug,
        'exists' => file_exists($file_path),
        'content' => file_exists($file_path) ? file_get_contents($file_path) : null
    ];
}
// Combined boundary data for the preview map (official names via properties)
$preview_boundaries = [];
foreach ($barangay_boundaries as $bid => $bb) {
    if (!$bb['exists']) continue;
    $decoded = json_decode($bb['content'], true);
    if (is_array($decoded)) {
        $decoded['barangay_id'] = $bid;
        $decoded['barangay_name'] = $bb['name'];
        $preview_boundaries[] = $decoded;
    }
}
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

    <!-- ===== BARANGAY BOUNDARY GeoJSON MANAGEMENT ===== -->
    <div class="stat-card bg-white rounded-xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Barangay Boundaries</h2>
        <p class="text-sm text-gray-500 mb-6">
            Upload or edit the official boundary GeoJSON for each barangay. These files power the
            accurate point-in-polygon detection on the citizen reporting map.
        </p>

        <form
            method="POST"
            action="<?php echo BASE_URL; ?>controllers/SettingsController.php?tab=map"
            enctype="multipart/form-data"
            data-validate
        >
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="sub_action" value="save_barangay_boundaries">

            <div class="space-y-4 mb-6">
                <?php foreach ($barangay_boundaries as $bid => $bb): ?>
                <div class="boundary-row" data-id="<?php echo (int)$bid; ?>">
                    <input type="hidden" name="barangay_id[<?php echo (int)$bid; ?>]" value="<?php echo (int)$bid; ?>">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-4 rounded-xl border"
                         style="border-color: <?php echo $bb['exists'] ? 'rgba(16,163,127,0.3)' : '#fca5a5'; ?>;">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($bb['name']); ?></span>
                                <?php if ($bb['exists']): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i class="fas fa-check-circle"></i>Boundary loaded
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-red-50 text-red-600 border border-red-200">
                                        <i class="fas fa-exclamation-circle"></i>No boundary file
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-400 mt-1 font-mono"><?php echo htmlspecialchars($bb['slug']); ?>.geojson</p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                            <label class="cursor-pointer">
                                <span class="btn-secondary inline-flex items-center gap-1.5 !py-2 !px-3 text-sm">
                                    <i class="fas fa-upload text-[#10A37F]"></i>Upload .geojson
                                </span>
                                <input type="file" name="barangay_geojson_file[<?php echo (int)$bid; ?>]"
                                       accept=".geojson,application/json,application/geo+json"
                                       class="boundary-file-input hidden" data-name="<?php echo htmlspecialchars($bb['name']); ?>">
                            </label>
                            <button type="button" class="btn-secondary inline-flex items-center gap-1.5 !py-2 !px-3 text-sm toggle-boundary-edit">
                                <i class="fas fa-edit text-gray-500"></i>Edit JSON
                            </button>
                        </div>
                    </div>

                    <div class="boundary-editor hidden mt-3" data-id="<?php echo (int)$bid; ?>">
                        <textarea
                            name="barangay_geojson[<?php echo (int)$bid; ?>]"
                            rows="10"
                            spellcheck="false"
                            class="boundary-textarea w-full font-mono text-xs p-3 rounded-lg border border-gray-200 focus:border-[#10A37F] focus:outline-none bg-gray-50"
                            placeholder="<?php echo htmlspecialchars('{"type":"FeatureCollection","features":[...]}'); ?>"
                        ><?php echo $bb['exists'] ? htmlspecialchars($bb['content']) : ''; ?></textarea>
                        <div class="flex items-center justify-between mt-2">
                            <p class="text-xs text-gray-400 flex items-center gap-1">
                                <i class="fas fa-info-circle"></i>
                                Must be a valid GeoJSON FeatureCollection with Polygon/MultiPolygon features.
                            </p>
                            <button type="button" class="text-xs text-[#10A37F] font-medium preview-boundary" data-id="<?php echo (int)$bid; ?>">
                                <i class="fas fa-eye mr-1"></i>Preview this boundary
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Combined preview map -->
            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="form-label !mb-0">Boundary Preview</label>
                    <button type="button" id="refreshBoundaryPreview" class="text-xs text-[#10A37F] font-medium">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh preview
                    </button>
                </div>
                <div id="barangayBoundaryMap" class="map-container" style="height: 380px;"></div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i>Save Barangay Boundaries
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

    // ============================================================
    // BARANGAY BOUNDARY GEOJSON PREVIEW
    // ============================================================
    const initialBoundaryData = <?php echo json_encode($preview_boundaries); ?>;

    const boundaryColors = ['#10A37F', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6', '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'];
    let boundaryPreviewMap = null;
    let boundaryLayers = [];

    function renderBoundaryPreview() {
        const geojsons = collectBoundaryGeojsons();
        if (boundaryLayers.length > 0) {
            boundaryLayers.forEach(function(l) {
                if (boundaryPreviewMap) boundaryPreviewMap.removeLayer(l);
            });
            boundaryLayers = [];
        }
        if (!geojsons.length) return;
        if (!boundaryPreviewMap) {
            boundaryPreviewMap = L.map('barangayBoundaryMap', { scrollWheelZoom: false });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(boundaryPreviewMap);
        }
        const bounds = [];
        geojsons.forEach(function(geo, idx) {
            const color = boundaryColors[idx % boundaryColors.length];
            const layer = L.geoJSON(geo, {
                style: { color: color, weight: 2, fillColor: color, fillOpacity: 0.18, smoothFactor: 1 },
                onEachFeature: function(feature, layer) {
                    const name = (geo.barangay_name) ? geo.barangay_name : ((feature.properties && feature.properties.name) || '');
                    if (name) layer.bindTooltip(name, { sticky: true });
                }
            }).addTo(boundaryPreviewMap);
            boundaryLayers.push(layer);
            const layerBounds = layer.getBounds();
            if (layerBounds.isValid()) bounds.push(layerBounds);
        });
        if (bounds.length > 0) {
            boundaryPreviewMap.fitBounds(L.latLngBounds(bounds).pad(0.15));
        } else {
            boundaryPreviewMap.setView([15.3092, 120.9033], 13);
        }
    }

    function collectBoundaryGeojsons() {
        const geojsons = [];
        document.querySelectorAll('.boundary-row').forEach(function(row) {
            const ta = row.querySelector('.boundary-textarea');
            if (!ta || ta.value.trim() === '') return;
            try {
                const parsed = JSON.parse(ta.value);
                if (parsed && parsed.type === 'FeatureCollection') {
                    const name = row.querySelector('.font-semibold') ? row.querySelector('.font-semibold').textContent.trim() : '';
                    if (name) parsed.barangay_name = name;
                    geojsons.push(parsed);
                }
            } catch (e) {}
        });
        return geojsons;
    }

    document.querySelectorAll('.toggle-boundary-edit').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const row = btn.closest('.boundary-row');
            const editor = row.querySelector('.boundary-editor');
            editor.classList.toggle('hidden');
            btn.querySelector('i').className = editor.classList.contains('hidden')
                ? 'fas fa-edit text-gray-500'
                : 'fas fa-chevron-up text-gray-500';
        });
    });

    document.querySelectorAll('.preview-boundary').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = btn.getAttribute('data-id');
            const ta = document.querySelector('.boundary-editor[data-id="' + id + '"] .boundary-textarea');
            if (!ta || ta.value.trim() === '') return;
            try {
                const parsed = JSON.parse(ta.value);
                if (!parsed || parsed.type !== 'FeatureCollection') return;
                renderBoundaryPreview();
                if (!boundaryPreviewMap) return;
                boundaryLayers.forEach(function(layer) { boundaryPreviewMap.removeLayer(layer); });
                boundaryLayers = [];
                const layer = L.geoJSON(parsed, {
                    style: { color: '#10A37F', weight: 2, fillColor: '#10A37F', fillOpacity: 0.18, smoothFactor: 1 }
                }).addTo(boundaryPreviewMap);
                boundaryLayers.push(layer);
                const b = layer.getBounds();
                if (b.isValid()) boundaryPreviewMap.fitBounds(b.pad(0.15));
            } catch (e) {}
        });
    });

    document.getElementById('refreshBoundaryPreview').addEventListener('click', renderBoundaryPreview);

    // File input -> load contents into the textarea for inspection.
    document.querySelectorAll('.boundary-file-input').forEach(function(input) {
        input.addEventListener('change', function() {
            const file = this.files && this.files[0];
            if (!file) return;
            const row = this.closest('.boundary-row');
            const editor = row.querySelector('.boundary-editor');
            const ta = row.querySelector('.boundary-textarea');
            const reader = new FileReader();
            reader.onload = function(e) {
                ta.value = e.target.result;
                editor.classList.remove('hidden');
                row.querySelector('.toggle-boundary-edit i').className = 'fas fa-chevron-up text-gray-500';
                renderBoundaryPreview();
            };
            reader.readAsText(file);
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('barangayBoundaryMap')) {
            renderBoundaryPreview();
        }
    });
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        renderBoundaryPreview();
    }
</script>