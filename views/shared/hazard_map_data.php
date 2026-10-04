<?php
// Data shared by the MENRO dashboard and the two full map pages.
require_once BASE_PATH . 'helpers/IdGuard.php';
$hazardMapRole = $_SESSION['user_role'] ?? '';
$hazardMapBarangayId = (int)($_SESSION['barangay_id'] ?? 0);
$hazardMapWhere = "r.latitude IS NOT NULL AND r.longitude IS NOT NULL AND r.latitude <> 0 AND r.longitude <> 0";
$hazardMapParams = [];
if (empty($hazardMapFull)) $hazardMapWhere .= " AND r.status NOT IN ('resolved', 'rejected', 'cancelled')";
if ($hazardMapRole === 'barangay_official') {
    $hazardMapWhere .= ' AND r.barangay_id = ?';
    $hazardMapParams[] = $hazardMapBarangayId;
}
$hazardMapLimit = !empty($hazardMapFull) ? '' : ' LIMIT 1500';
$hazardMapStmt = $db->prepare("SELECT r.id, r.title, r.latitude, r.longitude, r.created_at, r.resolved_at,
    r.status, r.risk_level, r.severity_score, r.location_address, r.category_id, r.barangay_id,
    COALESCE(c.name, 'Uncategorized') AS category_name,
    COALESCE(b.name, '') AS barangay_name
    FROM reports r
    LEFT JOIN categories c ON c.id = r.category_id
    LEFT JOIN barangays b ON b.id = r.barangay_id
    WHERE {$hazardMapWhere}
    ORDER BY r.created_at DESC{$hazardMapLimit}");
$hazardMapStmt->execute($hazardMapParams);
$hazardMapReports = $hazardMapStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($hazardMapReports as &$hazardMapRow) {
    $hazardMapRow['url'] = BASE_URL . 'index.php?page=manage-report&id=' . rawurlencode(IdGuard::enc((int)$hazardMapRow['id']));
}
unset($hazardMapRow);
$hazardMapCategories = $db->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$hazardMapBarangays = $hazardMapRole === 'admin'
    ? $db->query('SELECT id, name FROM barangays ORDER BY name')->fetchAll(PDO::FETCH_ASSOC)
    : [];
$hazardMapBoundary = null;
$hazardMapBoundaryPath = BASE_PATH . 'geojson/sanisidro.geojson';
if (is_file($hazardMapBoundaryPath)) {
    $hazardMapBoundary = json_decode((string)file_get_contents($hazardMapBoundaryPath), true);
}
if ($hazardMapRole === 'barangay_official' && $hazardMapBarangayId > 0) {
    $nameStmt = $db->prepare('SELECT name FROM barangays WHERE id = ?');
    $nameStmt->execute([$hazardMapBarangayId]);
    $nameKey = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string)$nameStmt->fetchColumn()));
    if ($nameKey !== '') {
        foreach (glob(BASE_PATH . 'geojson/barangay/*.geojson') ?: [] as $boundaryFile) {
            if (strpos(basename($boundaryFile), '_with_reports') !== false) continue;
            $boundaryJson = json_decode((string)file_get_contents($boundaryFile), true);
            foreach (($boundaryJson['features'] ?? []) as $feature) {
                $featureName = $feature['properties']['barangay_name'] ?? $feature['properties']['name'] ?? '';
                $featureKey = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string)$featureName));
                $fileKey = strtolower(preg_replace('/[^a-z0-9]+/i', '', pathinfo($boundaryFile, PATHINFO_FILENAME)));
                if ($featureKey === $nameKey || $fileKey === $nameKey) {
                    $hazardMapBoundary = ['type' => 'FeatureCollection', 'features' => [$feature]];
                    break 2;
                }
            }
        }
    }
}
$hazardMapDefaults = SettingsHelper::getMapSettings();
$hazardMapBarangayBoundaries = null;
if ($hazardMapRole === 'admin') {
    $mapBoundaryFeatures = [];
    foreach (glob(BASE_PATH . 'geojson/barangay/*.geojson') ?: [] as $boundaryFile) {
        if (basename($boundaryFile) === 'san-isidro.barangay.geojson' || strpos(basename($boundaryFile), '_with_reports') !== false) continue;
        $boundaryJson = json_decode((string)file_get_contents($boundaryFile), true);
        foreach (($boundaryJson['features'] ?? []) as $feature) {
            if (!in_array($feature['geometry']['type'] ?? '', ['Polygon', 'MultiPolygon'], true)) continue;
            $feature['properties']['name'] = $feature['properties']['barangay_name'] ?? $feature['properties']['name'] ?? ucwords(str_replace('-', ' ', pathinfo($boundaryFile, PATHINFO_FILENAME)));
            $mapBoundaryFeatures[] = $feature;
        }
    }
    if ($mapBoundaryFeatures) $hazardMapBarangayBoundaries = ['type'=>'FeatureCollection', 'features'=>$mapBoundaryFeatures];
}
