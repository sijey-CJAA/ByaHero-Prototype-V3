<?php
/**
 * Debug: resolve a lat,lng against data/routes/*.geojson and public/routes/*.geojson and return match info.
 * Usage examples:
 *   http://localhost:8000/debug_resolve.php?lat=14.093046&lng=121.023338
 */

header('Content-Type: application/json; charset=utf-8');

$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
if ($lat === null || $lng === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Provide lat and lng query params, e.g. ?lat=14.0931&lng=121.0233']);
    exit;
}

$baseDataDir = __DIR__ . '/../data';
$dirsToSearch = [$baseDataDir . '/routes', __DIR__ . '/routes'];

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
                foreach ($j['features'] as $feat) { $feat['_src_file'] = basename($f); $features[] = $feat; }
            } elseif (isset($j['type']) && $j['type'] === 'Feature') {
                $j['_src_file'] = basename($f);
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

function polygonCentroid($rings) {
    $ring = $rings[0] ?? [];
    $sumX = 0; $sumY = 0; $n = count($ring);
    if ($n === 0) return null;
    foreach ($ring as $pt) { $sumX += $pt[0]; $sumY += $pt[1]; }
    return [$sumX / $n, $sumY / $n];
}

function haversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000.0;
    $φ1 = deg2rad($lat1); $φ2 = deg2rad($lat2);
    $Δφ = deg2rad($lat2 - $lat1); $Δλ = deg2rad($lon2 - $lon1);
    $a = sin($Δφ/2) * sin($Δφ/2) + cos($φ1) * cos($φ2) * sin($Δλ/2) * sin($Δλ/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
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

$features = loadRouteFeatures($dirsToSearch);

$matched = null;
$checked = 0;
foreach ($features as $idx => $feat) {
    $checked++;
    if (!isset($feat['geometry']) || !isset($feat['geometry']['type'])) continue;
    $g = $feat['geometry'];
    if ($g['type'] === 'Polygon' && isset($g['coordinates'])) {
        if (pointInPolygon($lng, $lat, $g['coordinates'])) { $matched = ['index' => $idx, 'feature' => $feat]; break; }
    } elseif ($g['type'] === 'MultiPolygon' && isset($g['coordinates'])) {
        foreach ($g['coordinates'] as $poly) {
            if (pointInPolygon($lng, $lat, $poly)) { $matched = ['index' => $idx, 'feature' => $feat]; break 2; }
        }
    }
}

$result = ['success' => true, 'input' => ['lat' => $lat, 'lng' => $lng], 'checked_features' => $checked];

if ($matched) {
    $f = $matched['feature'];
    $result['matched'] = [
        'index' => $matched['index'],
        'src_file' => $f['_src_file'] ?? null,
        'name' => featureName($f),
        'properties' => $f['properties'] ?? null
    ];
    echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    exit;
}

$distances = [];
foreach ($features as $idx => $feat) {
    if (!isset($feat['geometry']) || !isset($feat['geometry']['coordinates'])) continue;
    $coords = $feat['geometry']['coordinates'];
    $centroid = null;
    if ($feat['geometry']['type'] === 'Polygon') $centroid = polygonCentroid($coords);
    elseif ($feat['geometry']['type'] === 'MultiPolygon') $centroid = polygonCentroid($coords[0] ?? []);
    if (!$centroid) continue;
    $cLng = $centroid[0]; $cLat = $centroid[1];
    $d = haversine($lat, $lng, $cLat, $cLng);
    $distances[] = ['index'=>$idx, 'name'=>featureName($feat), 'src_file'=>($feat['_src_file'] ?? null), 'centroid'=>['lat'=>$cLat,'lng'=>$cLng], 'distance_m'=>$d];
}
usort($distances, function($a,$b){ return $a['distance_m'] <=> $b['distance_m']; });
$result['nearest_features'] = array_slice($distances, 0, 6);
echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);