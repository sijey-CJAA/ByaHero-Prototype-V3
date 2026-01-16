<?php
/**
 * Remove `current_location` column from buses table in data/db.sqlite
 *
 * Usage:
 *   php scripts/remove_current_location_column.php
 *
 * IMPORTANT:
 * - Back up data/db.sqlite before running (the script will not overwrite your backup).
 * - After running, update server code to stop writing the 'current_location' column (see updated public/update_geo_location.php included below).
 */

$base = __DIR__ . '/../data';
$dbPath = $base . '/db.sqlite';
$bakPath = $base . '/db.sqlite.bak';

if (!file_exists($dbPath)) {
    echo "Database not found at {$dbPath}\n";
    exit(1);
}

echo "Backing up DB to {$bakPath} ...\n";
if (!copy($dbPath, $bakPath)) {
    echo "Warning: failed to create backup. Aborting for safety.\n";
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // check if current_location exists
    $cols = $pdo->query("PRAGMA table_info(buses)")->fetchAll(PDO::FETCH_ASSOC);
    $has_current_location = false;
    $has_current_location_name = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'current_location') $has_current_location = true;
        if ($c['name'] === 'current_location_name') $has_current_location_name = true;
    }

    if (!$has_current_location) {
        echo "No 'current_location' column found — nothing to do.\n";
        exit(0);
    }

    echo "Creating new table buses_new without current_location column...\n";
    // New table does include current_location_name (keep it)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS buses_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            route TEXT,
            current_location_name TEXT,
            seats_total INTEGER NOT NULL DEFAULT 40,
            seats_available INTEGER NOT NULL DEFAULT 40,
            status TEXT NOT NULL DEFAULT 'available',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    echo "Copying rows (preserving current_location_name if present)...\n";

    // Build select list: try to read current_location_name if present, otherwise NULL
    $selectCols = "id, code, route";
    if ($has_current_location_name) $selectCols .= ", current_location_name";
    else $selectCols .= ", NULL AS current_location_name";
    $selectCols .= ", seats_total, seats_available, status, updated_at";

    $rows = $pdo->query("SELECT {$selectCols} FROM buses")->fetchAll(PDO::FETCH_ASSOC);

    $insert = $pdo->prepare("
        INSERT INTO buses_new (id, code, route, current_location_name, seats_total, seats_available, status, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($rows as $r) {
        $insert->execute([
            $r['id'],
            $r['code'],
            $r['route'] ?? null,
            $r['current_location_name'] ?? null,
            $r['seats_total'] ?? 40,
            $r['seats_available'] ?? 40,
            $r['status'] ?? 'available',
            $r['updated_at'] ?? date('c')
        ]);
    }

    echo "Dropping old table and renaming...\n";
    $pdo->exec("DROP TABLE buses");
    $pdo->exec("ALTER TABLE buses_new RENAME TO buses");

    echo "Migration complete. The DB now omits the 'current_location' column.\n";
    echo "Backup is at: {$bakPath}\n";
    exit(0);

} catch (PDOException $e) {
    echo "DB error: " . $e->getMessage() . "\n";
    echo "Restoring backup...\n";
    if (file_exists($bakPath)) copy($bakPath, $dbPath);
    exit(1);
}