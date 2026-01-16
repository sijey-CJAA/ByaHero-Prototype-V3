<?php
// Returns a FeatureCollection containing:
//  - route GeoJSON features (from data/routes/*.geojson)
//  - current bus location features (from data/current_locations/*.geojson)
// Usage: GET /map_data.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$baseData = __DIR__ . '/../data';
$routesDir = $baseData . '/routes';
$locationsDir = $baseData . '/current_locations';

$features = [];

// Load route files (all .geojson in data/routes)
if (is_dir($routesDir)) {
    foreach (glob($routesDir . '/*.geojson') as $routeFile) {
        $contents = @file_get_contents($routeFile);
        if (!$contents) continue;
        $json = json_decode($contents, true);
        if (!$json) continue;
        // If it's a FeatureCollection, merge features
        if (isset($json['type']) && $json['type'] === 'FeatureCollection' && isset($json['features'])) {
            foreach ($json['features'] as $f) $features[] = $f;
        } elseif (isset($json['type']) && $json['type'] === 'Feature') {
            $features[] = $json;
        }
    }
}

// Load current locations (each should be a Feature or FeatureCollection)
if (is_dir($locationsDir)) {
    foreach (glob($locationsDir . '/bus_*.geojson') as $locFile) {
        $contents = @file_get_contents($locFile);
        if (!$contents) continue;
        $json = json_decode($contents, true);
        if (!$json) continue;
        if (isset($json['type']) && $json['type'] === 'FeatureCollection' && isset($json['features'])) {
            foreach ($json['features'] as $f) $features[] = $f;
        } elseif (isset($json['type']) && $json['type'] === 'Feature') {
            $features[] = $json;
        } else {
            // If stored as a bare geometry, wrap as feature
            if (isset($json['coordinates']) && isset($json['type'])) {
                $features[] = ['type' => 'Feature', 'geometry' => $json, 'properties' => ['source' => 'bus']];
            }
        }
    }
}

$out = ['type' => 'FeatureCollection', 'features' => $features];
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);