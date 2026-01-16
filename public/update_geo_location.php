<?php
/**
 * update_geo_location.php
 *
 * Writes incoming per-bus GeoJSON to the database (new column: current_location),
 * updates friendly name + metadata, and optionally writes out per-bus geojson files
 * for backward compatibility (disabled by default).
 *
 * To fully remove file usage: set $WRITE_FILES = false and add the current_location
 * column to your buses table (see migrate_add_current_location.sql).
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$baseDataDir = __DIR__ . '/../data';
$dbPath = $baseDataDir . '/db.sqlite';

// Toggle whether we still write files to data/current_locations (set to false to stop writing files)
$WRITE_FILES = false;

// read input
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if ($input === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

if (!isset($input['bus_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing bus_id']);
    exit;
}

$busId = intval($input['bus_id']);

// build geojson object (same as before)
$geojson = null;
if (!empty($input['geojson']) && is_array($input['geojson'])) {
    $geojson = $input['geojson'];
} elseif (isset($input['lat']) && isset($input['lng'])) {
    $lat = filter_var($input['lat'], FILTER_VALIDATE_FLOAT);
    $lng = filter_var($input['lng'], FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lng === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid lat/lng']);
        exit;
    }
    $geojson = [
        'type' => 'Feature',
        'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
        'properties' => [
            'bus_id' => $busId,
            'timestamp' => gmdate('c')
        ]
    ];
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No geojson or lat/lng provided']);
    exit;
}

// helper: prefer friendly name in properties
function extractNameFromProps($props) {
    if (!is_array($props)) return null;
    if (!empty($props['current_location_name']) && is_string($props['current_location_name'])) return $props['current_location_name'];
    if (!empty($props['Current Location']) && is_string($props['Current Location'])) return $props['Current Location'];
    if (!empty($props['name']) && is_string($props['name'])) return $props['name'];
    foreach ($props as $k => $v) {
        if (is_string($v) && trim($v) !== '') return trim($v);
        if ($v === '') return $k;
    }
    return null;
}

// server-side resolution (attempt if needed) - minimal lookup across data/routes or public/routes
function loadRouteFeatures($dirs) {
    $features = [];
    foreach ($dirs as $routesDir) {
        if (!is_dir($routesDir)) continue;
        foreach (glob($routesDir . '/*.geojson') as $f) {
            $c = @file_get_contents($f);
            if ($c === false) continue;
            $j = json_decode($c, true);
            if (!is_array($j)) continue;
            if (isset($j['type']) && $j['type'] === 'FeatureCollection' && !empty($j['features'])) {
                foreach ($j['features'] as $feat) $features[] = $feat;
            } elseif (isset($j['type']) && $j['type'] === 'Feature') {
                $features[] = $j;
            }
        }
    }
    return $features;
}

function pointInRing($x, $y, $ring) {
    $inside = false;
    $j = count($ring) - 1;
    for ($i = 0; $i < count($ring); $i++) {
        $xi = $ring[$i][0]; $yi = $ring[$i][1];
        $xj = $ring[$j][0]; $yj = $ring[$j][1];
        $intersect = ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1) + $xi));
        if ($intersect) $inside = !$inside;
        $j = $i;
    }
    return $inside;
}

function pointInPolygon($x, $y, $rings) {
    if (!is_array($rings) || count($rings) === 0) return false;
    if (!pointInRing($x, $y, $rings[0])) return false;
    for ($i = 1; $i < count($rings); $i++) {
        if (pointInRing($x, $y, $rings[$i])) return false;
    }
    return true;
}

function featureName($feat) {
    $p = isset($feat['properties']) ? $feat['properties'] : [];
    if (!empty($p['current_location_name'])) return $p['current_location_name'];
    if (!empty($p['Current Location'])) return $p['Current Location'];
    if (!empty($p['name'])) return $p['name'];
    foreach ($p as $k => $v) {
        if (is_string($v) && trim($v) !== '') return trim($v);
        if ($v === '') return $k;
    }
    return null;
}

// extract provided name (could be a coordinate fallback)
$providedName = null;
if (isset($geojson['properties']) && is_array($geojson['properties'])) {
    $providedName = extractNameFromProps($geojson['properties']);
}

// try server resolution (routes dir)
$serverResolvedName = null;
$lat = $lng = null;
if (isset($geojson['type']) && $geojson['type'] === 'Feature' && isset($geojson['geometry']['type']) && $geojson['geometry']['type'] === 'Point') {
    $lng = floatval($geojson['geometry']['coordinates'][0]);
    $lat = floatval($geojson['geometry']['coordinates'][1]);
}

if ($lat !== null && $lng !== null) {
    $dirsToSearch = [$baseDataDir . '/routes', __DIR__ . '/routes'];
    $features = loadRouteFeatures($dirsToSearch);
    foreach ($features as $feat) {
        if (!isset($feat['geometry']) || !isset($feat['geometry']['type'])) continue;
        $g = $feat['geometry'];
        if ($g['type'] === 'Polygon' && isset($g['coordinates'])) {
            if (pointInPolygon($lng, $lat, $g['coordinates'])) {
                $name = featureName($feat);
                if ($name) { $serverResolvedName = $name; break; }
            }
        } elseif ($g['type'] === 'MultiPolygon' && isset($g['coordinates'])) {
            foreach ($g['coordinates'] as $poly) {
                if (pointInPolygon($lng, $lat, $poly)) {
                    $name = featureName($feat);
                    if ($name) { $serverResolvedName = $name; break 2; }
                }
            }
        }
    }
}

// prefer server-resolved name if present, else provided name
$locationName = $serverResolvedName !== null ? $serverResolvedName : $providedName;

// ensure geojson properties contain friendly name for compatibility
if (!isset($geojson['properties']) || !is_array($geojson['properties'])) $geojson['properties'] = [];
if (!empty($locationName)) {
    $geojson['properties']['current_location_name'] = $locationName;
    $geojson['properties']['Current Location'] = $locationName;
} else {
    // fallback to coordinate string if no name found
    if (!isset($geojson['properties']['current_location_name']) && $lat !== null && $lng !== null) {
        $coordStr = number_format($lat, 6, '.', '') . ', ' . number_format($lng, 6, '.', '');
        $geojson['properties']['current_location_name'] = $coordStr;
        $geojson['properties']['Current Location'] = $coordStr;
    }
}

// Optional: keep writing files for compatibility (disabled by default)
$locationsDir = $baseDataDir . '/current_locations';
$writtenFile = null;
if ($WRITE_FILES) {
    if (!is_dir($locationsDir) && !mkdir($locationsDir, 0775, true) && !is_dir($locationsDir)) {
        // non-fatal: continue and still write to DB
        error_log('Warning: failed to create current_locations dir');
    } else {
        $busFile = $locationsDir . "/bus_{$busId}.geojson";
        @file_put_contents($busFile, json_encode($geojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $writtenFile = 'data/current_locations/bus_' . $busId . '.geojson';
    }
}

// Prepare db write: store raw GeoJSON in buses.current_location (TEXT) and update other fields
try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Build update pieces
    $fields = ['updated_at = CURRENT_TIMESTAMP'];
    $params = [];

    // store raw geojson string in a column named 'current_location'
    $geojson_str = json_encode($geojson, JSON_UNESCAPED_SLASHES);

    $fields[] = 'current_location = ?';
    $params[] = $geojson_str;

    // also update lat/lng columns if they exist in schema (try to detect)
    $hasLat = false; $hasLng = false;
    $cols = $pdo->query("PRAGMA table_info('buses')")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if ($c['name'] === 'lat') $hasLat = true;
        if ($c['name'] === 'lng') $hasLng = true;
    }
    if ($hasLat && $hasLng && $lat !== null && $lng !== null) {
        $fields[] = 'lat = ?'; $params[] = $lat;
        $fields[] = 'lng = ?'; $params[] = $lng;
    }

    if (!empty($geojson['properties']['current_location_name'])) {
        $fields[] = 'current_location_name = ?';
        $params[] = $geojson['properties']['current_location_name'];
    }

    if (isset($input['route'])) {
        $fields[] = 'route = ?';
        $params[] = $input['route'];
    }
    if (isset($input['seats_available'])) {
        $sa = intval($input['seats_available']);
        if ($sa < 0) $sa = 0;
        $fields[] = 'seats_available = ?';
        $params[] = $sa;
    }
    if (isset($input['status'])) {
        $allowed = ['available','on_stop','full','unavailable'];
        if (in_array($input['status'], $allowed)) {
            $fields[] = 'status = ?';
            $params[] = $input['status'];
        }
    }

    $params[] = $busId;
    $sql = "UPDATE buses SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Location saved to database' . ($WRITE_FILES ? ' (and file)' : ''),
        'saved_file' => $writtenFile,
        'current_location_name' => $geojson['properties']['current_location_name'] ?? null,
        'server_resolved_name' => $serverResolvedName
    ]);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    exit;
}