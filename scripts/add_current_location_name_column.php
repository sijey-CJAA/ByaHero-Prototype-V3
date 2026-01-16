<?php
/**
 * Add `current_location` TEXT column to buses table (if missing) and populate
 * from data/current_locations/bus_{id}.geojson files when available.
 *
 * Usage:
 *   php scripts/add_current_location_column.php
 *
 * IMPORTANT:
 * - This script will create a backup at data/db.sqlite.bak before modifying the DB.
 */

$base = __DIR__ . '/../data';
$dbPath = $base . '/db.sqlite';
$bakPath = $base . '/db.sqlite.bak';

if (!file_exists($dbPath)) {
    echo "Database not found at: {$dbPath}\n";
    exit(1);
}

// Create backup if it doesn't already exist
if (!file_exists($bakPath)) {
    echo "Creating backup: {$bakPath}\n";
    if (!copy($dbPath, $bakPath)) {
        echo "Failed to create backup. Aborting.\n";
        exit(1);
    }
} else {
    echo "Backup already exists at {$bakPath}\n";
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check existing columns
    $cols = $pdo->query("PRAGMA table_info(buses)")->fetchAll(PDO::FETCH_ASSOC);
    $has_current_location = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'current_location') { $has_current_location = true; break; }
    }

    if ($has_current_location) {
        echo "Column 'current_location' already exists. Nothing to do.\n";
        exit(0);
    }

    // Add column
    echo "Adding 'current_location' column to buses table...\n";
    $pdo->exec("ALTER TABLE buses ADD COLUMN current_location TEXT");

    // Prepare update statement
    $update = $pdo->prepare("UPDATE buses SET current_location = ? WHERE id = ?");

    // For each bus id, try to load corresponding file
    $stmt = $pdo->query("SELECT id FROM buses");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $populated = 0;
    $skipped = 0;

    foreach ($ids as $id) {
        $file = $base . "/current_locations/bus_{$id}.geojson";
        if (is_file($file) && is_readable($file)) {
            $content = @file_get_contents($file);
            if ($content !== false && trim($content) !== '') {
                // Keep original content as JSON string
                // Validate JSON; if invalid, skip
                $decoded = json_decode($content, true);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    // invalid JSON; skip
                    echo "bus {$id}: geojson file exists but contains invalid JSON, skipping\n";
                    $skipped++;
                    continue;
                }
                // store pretty-compact JSON (unescaped slashes)
                $jsonStr = json_encode($decoded, JSON_UNESCAPED_SLASHES);
                $update->execute([$jsonStr, $id]);
                echo "bus {$id}: populated current_location from file\n";
                $populated++;
                continue;
            }
        }
        // No file: optional — leave NULL
        $skipped++;
    }

    echo "Done. Populated: {$populated}, Skipped: {$skipped}\n";
    echo "If anything looks wrong you can restore the backup:\n";
    echo "  cp \"{$bakPath}\" \"{$dbPath}\"\n";
    exit(0);

} catch (PDOException $e) {
    echo "DB error: " . $e->getMessage() . "\n";
    // Attempt restore
    if (file_exists($bakPath)) {
        echo "Restoring backup...\n";
        copy($bakPath, $dbPath);
    }
    exit(1);
}