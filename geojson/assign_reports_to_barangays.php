<?php
/**
 * ============================================================================
 *  ASSIGN REPORTS TO BARANGAYS
 * ============================================================================
 *  Reads a single "reports" GeoJSON file (mostly Point features), loops over
 *  every barangay boundary GeoJSON in a folder, and uses point-in-polygon to
 *  decide which reports belong to which barangay.
 *
 *  For every barangay it writes a new file, e.g.:
 *      Calaba_with_reports.geojson
 *      Tabon_with_reports.geojson
 *  ...containing the ORIGINAL barangay boundary feature(s) PLUS all the
 *  citizen reports that fall inside that boundary. All original properties
 *  of both the boundaries and the reports are preserved untouched.
 *
 *  Requires nothing but plain PHP (no geoPHP / no Composer).
 *  Supports Polygon and MultiPolygon (including holes), and will also walk
 *  GeometryCollections.
 *
 *  Run from the command line:
 *      php assign_reports_to_barangays.php
 *
 *  Output is printed to the console (counts per barangay + a summary).
 *
 *  NOTE ON COORDINATE ORDER: GeoJSON stores coordinates as [longitude, latitude].
 *  The point-in-polygon math is symmetric, so as long as ALL your files use the
 *  same order (GeoJSON standard), it just works. You do not need to configure it.
 * ============================================================================
 */

// ---------------------------------------------------------------------------
// 1. CONFIGURATION — edit these four lines to match your machine
// ---------------------------------------------------------------------------

/** Full path to the GeoJSON that holds ALL citizen reports (mostly Points). */
const REPORTS_FILE = __DIR__ . '/sanisidro.geojson';

/** Folder containing one boundary GeoJSON per barangay (Polygon / MultiPolygon). */
const BARANGAYS_DIR = __DIR__ . '/barangay';

/** Folder where the new "<barangay>_with_reports.geojson" files are saved. */
const OUTPUT_DIR = __DIR__ . '/barangay_with_reports';

/**
 * Only Point / MultiPoint features are treated as reports.
 * Leave this true (recommended) for the described workflow.
 * Set to false if your report file also contains polygon / line features that
 * must be tested — their centroid will be used as the test point instead.
 */
const REPORTS_ARE_POINTS_ONLY = true;

/**
 * Barangay boundary files that should be skipped (by exact filename).
 * "san-isidro.barangay.geojson" is a combined file of every barangay in one,
 * so it is skipped by default to avoid double-processing / a redundant output.
 */
const SKIP_BASENAMES = ['san-isidro.barangay.geojson'];

// ---------------------------------------------------------------------------
// 2. GEOMETRY HELPERS (pure PHP point-in-polygon)
// ---------------------------------------------------------------------------

/**
 * True when point ($lon, $lat) lies exactly on the segment A->B
 * (within a tiny numeric tolerance). Used so reports sitting right on a
 * barangay border still count as belonging to that barangay.
 */
function pointOnSegment(float $lon, float $lat, array $a, array $b): bool
{
    $x1 = (float)$a[0]; $y1 = (float)$a[1];
    $x2 = (float)$b[0]; $y2 = (float)$b[1];

    $cross = ($lat - $y1) * ($x2 - $x1) - ($lon - $x1) * ($y2 - $y1);
    if (abs($cross) > 1e-9) {
        return false; // not collinear
    }
    return min($x1, $x2) - 1e-9 <= $lon && $lon <= max($x1, $x2) + 1e-9
        && min($y1, $y2) - 1e-9 <= $lat && $lat <= max($y1, $y2) + 1e-9;
}

/**
 * Classic ray-casting test: is the point inside a single ring
 * (a ring is a closed list of [lon, lat] vertices)?
 * A point on the ring's boundary counts as inside.
 */
function pointInRing(float $lon, float $lat, array $ring): bool
{
    $n = count($ring);
    if ($n < 3) {
        return false;
    }

    $inside = false;
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $a = $ring[$j];
        $b = $ring[$i];

        if (pointOnSegment($lon, $lat, $a, $b)) {
            return true;
        }

        $ay = (float)$a[1];
        $by = (float)$b[1];

        // Cast a horizontal ray to the right and flip parity on each crossing.
        if (($ay > $lat) !== ($by > $lat)) {
            $xIntersect = (float)$a[0]
                + ($lat - $ay) * ((float)$b[0] - (float)$a[0]) / ($by - $ay);
            if ($lon < $xIntersect) {
                $inside = !$inside;
            }
        }
    }
    return $inside;
}

/**
 * Test against one Polygon = [outerRing, holeRing1, holeRing2, ...].
 * The point must be inside the outer ring AND not inside any hole.
 */
function pointInPolygon(float $lon, float $lat, array $polygon): bool
{
    if (count($polygon) === 0 || count($polygon[0]) < 3) {
        return false;
    }
    if (!pointInRing($lon, $lat, $polygon[0])) {
        return false;
    }
    for ($i = 1, $n = count($polygon); $i < $n; $i++) {
        if (pointInRing($lon, $lat, $polygon[$i])) {
            return false; // inside a hole -> not part of the barangay
        }
    }
    return true;
}

/**
 * True if the point is inside any polygon in a list of polygons.
 * Handles MultiPolygon (and, via collectPolygons(), GeometryCollections).
 */
function pointInAnyPolygon(float $lon, float $lat, array $polygons): bool
{
    foreach ($polygons as $polygon) {
        if (pointInPolygon($lon, $lat, $polygon)) {
            return true;
        }
    }
    return false;
}

/**
 * Flatten a geometry's Polygon / MultiPolygon / GeometryCollection into a
 * flat list of polygons, where each polygon is [outerRing, holes...].
 * Anything else (Point, LineString, ...) yields no polygons.
 */
function collectPolygons(array $geometry): array
{
    $type = $geometry['type'] ?? '';
    switch ($type) {
        case 'Polygon':
            return [$geometry['coordinates']];

        case 'MultiPolygon':
            return $geometry['coordinates'];

        case 'GeometryCollection':
            $out = [];
            foreach (($geometry['geometries'] ?? []) as $child) {
                $out = array_merge($out, collectPolygons($child));
            }
            return $out;

        default:
            return [];
    }
}

/**
 * Extract a [lon, lat] test point from a report feature, or null if the
 * feature has no usable geometry.
 *  - Point:  uses its coordinate.
 *  - MultiPoint: uses the first coordinate.
 *  - Other types: only when REPORTS_ARE_POINTS_ONLY is false — the centroid
 *    (average of every vertex) is used as an approximation.
 */
function featureTestPoint(array $feature): ?array
{
    $geometry = $feature['geometry'] ?? null;
    if (!is_array($geometry) || empty($geometry['type'])) {
        return null;
    }

    $coords = $geometry['coordinates'] ?? null;
    $type   = $geometry['type'];

    if ($type === 'Point') {
        return (is_array($coords) && count($coords) >= 2
            && is_numeric($coords[0]) && is_numeric($coords[1]))
            ? [(float)$coords[0], (float)$coords[1]]
            : null;
    }

    if ($type === 'MultiPoint') {
        foreach ((array)$coords as $pt) {
            if (is_array($pt) && count($pt) >= 2
                && is_numeric($pt[0]) && is_numeric($pt[1])) {
                return [(float)$pt[0], (float)$pt[1]];
            }
        }
        return null;
    }

    // Non-point geometries (polygons / lines) in the reports file.
    if (REPORTS_ARE_POINTS_ONLY) {
        return null;
    }

    $vertices = [];
    collectVertices($geometry, $vertices);
    if (count($vertices) === 0) {
        return null;
    }
    $sumX = 0.0; $sumY = 0.0;
    foreach ($vertices as $v) {
        $sumX += $v[0];
        $sumY += $v[1];
    }
    return [$sumX / count($vertices), $sumY / count($vertices)];
}

/**
 * Append every vertex of a geometry into $out as [lon, lat] pairs.
 * Recurses through GeometryCollections.
 */
function collectVertices(array $geometry, array &$out): void
{
    $type = $geometry['type'] ?? '';
    $c    = $geometry['coordinates'] ?? [];

    switch ($type) {
        case 'Point':
            if (is_array($c) && count($c) >= 2) {
                $out[] = [(float)$c[0], (float)$c[1]];
            }
            break;

        case 'MultiPoint':
        case 'LineString':
            foreach ($c as $v) {
                if (is_array($v) && count($v) >= 2) {
                    $out[] = [(float)$v[0], (float)$v[1]];
                }
            }
            break;

        case 'Polygon':
            foreach ($c as $ring) {
                foreach ($ring as $v) {
                    if (is_array($v) && count($v) >= 2) {
                        $out[] = [(float)$v[0], (float)$v[1]];
                    }
                }
            }
            break;

        case 'MultiPolygon':
        case 'MultiLineString':
            foreach ($c as $part) {
                foreach ($part as $ring) {
                    foreach ($ring as $v) {
                        if (is_array($v) && count($v) >= 2) {
                            $out[] = [(float)$v[0], (float)$v[1]];
                        }
                    }
                }
            }
            break;

        case 'GeometryCollection':
            foreach (($geometry['geometries'] ?? []) as $child) {
                collectVertices($child, $out);
            }
            break;
    }
}

// ---------------------------------------------------------------------------
// 3. FILE HELPERS
// ---------------------------------------------------------------------------

/**
 * Read + decode a GeoJSON file into an array, or null on any error.
 * Prints the specific problem to the console so failures are easy to see.
 */
function readGeoJSON(string $path): ?array
{
    if (!is_file($path)) {
        echo "  !! File not found: {$path}\n";
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        echo "  !! Could not read file: {$path}\n";
        return null;
    }

    // Guard against a UTF-8 BOM that some GIS exports prepend.
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "  !! Invalid JSON in {$path}: " . json_last_error_msg() . "\n";
        return null;
    }

    if (!is_array($data) || ($data['type'] ?? '') !== 'FeatureCollection') {
        echo "  !! {$path} is not a FeatureCollection\n";
        return null;
    }

    return $data;
}

// ---------------------------------------------------------------------------
// 4. MAIN
// ---------------------------------------------------------------------------

echo "============================================================\n";
echo " ASSIGN REPORTS TO BARANGAYS\n";
echo "============================================================\n";

// --- 4a. Load the reports file -------------------------------------------
echo "\n[1/4] Loading reports file...\n";
$reportsData = readGeoJSON(REPORTS_FILE);
if ($reportsData === null) {
    exit(1);
}
$reportFeatures = $reportsData['features'] ?? [];
echo "      Found " . count($reportFeatures) . " feature(s) in " . basename(REPORTS_FILE) . "\n";

// Build a usable list of reports: [lon, lat, originalFeature].
$reports   = [];
$skipped   = ['no_geometry' => 0, 'not_a_point' => 0, 'bad_coords' => 0];
foreach ($reportFeatures as $feat) {
    if (!is_array($feat) || !isset($feat['geometry'])) {
        $skipped['no_geometry']++;
        continue;
    }
    $pt = featureTestPoint($feat);
    if ($pt === null) {
        $type = $feat['geometry']['type'] ?? 'unknown';
        if (REPORTS_ARE_POINTS_ONLY && !in_array($type, ['Point', 'MultiPoint'], true)) {
            $skipped['not_a_point']++;
        } else {
            $skipped['bad_coords']++;
        }
        continue;
    }
    $reports[] = ['lon' => $pt[0], 'lat' => $pt[1], 'feature' => $feat];
}

echo "      " . count($reports) . " usable report(s) after filtering\n";
if (count($reports) === 0) {
    echo "\n  !! WARNING: No Point-like report features were found in " . basename(REPORTS_FILE) . ".\n";
    echo "     The file currently looks like a boundary (MultiPolygon), not a collection of citizen\n";
    echo "     report points. Point the REPORTS_FILE constant at your real reports GeoJSON.\n";
    exit(1);
}

// --- 4b. Load all barangay boundaries -------------------------------------
echo "\n[2/4] Loading barangay boundaries from " . basename(BARANGAYS_DIR) . "...\n";

$barangays = []; // name => ['polygons' => [...], 'features' => [...]]
foreach (glob(BARANGAYS_DIR . '/*.geojson') as $file) {
    $file = realpath($file);
    $base = basename($file);

    if (in_array($base, SKIP_BASENAMES, true)) {
        echo "      - skipped {$base} (in SKIP_BASENAMES)\n";
        continue;
    }
    if (strpos($base, '_with_reports') !== false) {
        echo "      - skipped {$base} (looks like a generated output file)\n";
        continue;
    }

    $name  = pathinfo($base, PATHINFO_FILENAME); // 'calaba' from 'calaba.geojson'
    $data  = readGeoJSON($file);
    if ($data === null) {
        continue;
    }

    $polygons = [];
    foreach (($data['features'] ?? []) as $feat) {
        $geom = $feat['geometry'] ?? null;
        if (!is_array($geom)) {
            continue;
        }
        $polygons = array_merge($polygons, collectPolygons($geom));
    }

    if (count($polygons) === 0) {
        echo "      - skipped {$base} (no Polygon / MultiPolygon boundary found)\n";
        continue;
    }

    $barangays[$name] = ['polygons' => $polygons, 'features' => $data['features']];
    echo "      - loaded {$base}  ({$name}, " . count($polygons) . " polygon(s))\n";
}

if (count($barangays) === 0) {
    echo "\n  !! No barangay boundary files were loaded.\n";
    exit(1);
}

// --- 4c. Point-in-polygon matching ----------------------------------------
echo "\n[3/4] Matching " . count($reports) . " report(s) against " . count($barangays) . " barangay(s)...\n";

// name => matched report features (originals preserved)
$assigned = [];
foreach (array_keys($barangays) as $name) {
    $assigned[$name] = [];
}

// report-feature-index => how many barangays claimed it (overlap detection)
$claimedCount = [];

$unassigned = 0;
foreach ($reports as $idx => $report) {
    $found = false;
    foreach ($barangays as $name => $barangay) {
        if (pointInAnyPolygon($report['lon'], $report['lat'], $barangay['polygons'])) {
            $assigned[$name][] = $report['feature'];
            $claimedCount[$idx] = ($claimedCount[$idx] ?? 0) + 1;
            $found = true;
        }
    }
    if (!$found) {
        $unassigned++;
    }
}

// --- 4d. Write one output file per barangay --------------------------------
echo "\n[4/4] Writing output files to " . basename(OUTPUT_DIR) . "...\n";

if (!is_dir(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0777, true);
}

$totalAssigned = 0;
$totalMatchedMultiple = 0;

foreach ($assigned as $name => $matchedReports) {
    $outName = $name . '_with_reports.geojson';
    $outPath = OUTPUT_DIR . '/' . $outName;

    $featureCollection = [
        'type'     => 'FeatureCollection',
        'features' => array_merge($barangays[$name]['features'], $matchedReports),
    ];

    $json = json_encode(
        $featureCollection,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($json === false) {
        echo "  !! Failed to encode {$outName}: " . json_last_error_msg() . "\n";
        continue;
    }

    file_put_contents($outPath, $json);

    echo "  ✓ {$name}: " . count($matchedReports) . " report(s) -> {$outName}\n";
    $totalAssigned += count($matchedReports);
}

foreach ($claimedCount as $count) {
    if ($count > 1) {
        $totalMatchedMultiple++;
    }
}

// --- Final summary ----------------------------------------------------------
echo "\n============================================================\n";
echo " SUMMARY\n";
echo "============================================================\n";
echo "  Barangays processed          : " . count($barangays) . "\n";
echo "  Reports loaded               : " . count($reports) . "\n";
echo "  Reports assigned (in total)  : {$totalAssigned}\n";
echo "  Reports matched by 2+ brgys  : {$totalMatchedMultiple} (overlapping boundaries)\n";
echo "  Reports in no barangay       : {$unassigned}\n";
if (array_sum($skipped) > 0) {
    echo "  Reports skipped              : " . array_sum($skipped)
        . " (no geometry: {$skipped['no_geometry']}, non-point: {$skipped['not_a_point']}, bad coords: {$skipped['bad_coords']})\n";
}
echo "\n  Output saved in: " . realpath(OUTPUT_DIR) . "\n";
echo "============================================================\n";
