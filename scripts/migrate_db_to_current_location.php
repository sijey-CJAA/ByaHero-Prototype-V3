<?php
/**
 * Migration script: convert buses table from (lat, lng) -> current_location JSON text.
 *
 * Usage: php scripts/migrate_db_to_current_location.php
 *
 * This script:
 *  - Detects if `lat` or `lng` columns exist in buses
 *  - Creates a new table `buses_new` with current_location TEXT
 *  - Copies data, converting existing lat/lng into GeoJSON Point if present
 *  - Replaces the old table
 */

$dbPath = __DIR__ . '/../data/db.sqlite';
if (!file_exists($dbPath)) {
    echo "No database found at $dbPath\n";
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check columns
$cols = $pdo->query("PRAGMA table_info(buses)")->fetchAll(PDO::FETCH_ASSOC);
$hasLat = false; $hasLng = false;
foreach ($cols as $c) {
    if ($c['name'] === 'lat') $hasLat = true;
    if ($c['name'] === 'lng') $hasLng = true;
}

if (!($hasLat || $hasLng)) {
    echo "No lat/lng columns found; migration not needed.\n";
    exit(0);
}

echo "Migrating buses table to use current_location...\n";

// Create new table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS buses_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        route TEXT,
        current_location TEXT,
        seats_total INTEGER NOT NULL DEFAULT 40,
        seats_available INTEGER NOT NULL DEFAULT 40,
        status TEXT NOT NULL DEFAULT 'available',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Copy rows
$rows = $pdo->query("SELECT * FROM buses")->fetchAll(PDO::FETCH_ASSOC);
$insert = $pdo->prepare("
    INSERT INTO buses_new (id, code, route, current_location, seats_total, seats_available, status, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
foreach ($rows as $r) {
    $currentLocation = null;
    if (isset($r['lat']) && isset($r['lng']) && $r['lat'] !== null && $r['lng'] !== null) {
        $lat = floatval($r['lat']);
        $lng = floatval($r['lng']);
        $gj = [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
            'properties' => ['migrated_from' => 'lat_lng']
        ];
        $currentLocation = json_encode($gj, JSON_UNESCAPED_SLASHES);
    }
    $insert->execute([
        $r['id'],
        $r['code'],
        $r['route'] ?? null,
        $currentLocation,
        $r['seats_total'] ?? 40,
        $r['seats_available'] ?? 40,
        $r['status'] ?? 'available',
        $r['updated_at'] ?? date('c')
    ]);
}

$pdo->exec("DROP TABLE buses");
$pdo->exec("ALTER TABLE buses_new RENAME TO buses");

echo "Migration complete. Old lat/lng converted to current_location (GeoJSON Point) where present.\n";