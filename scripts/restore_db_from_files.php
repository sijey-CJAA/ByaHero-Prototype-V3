<?php
// scripts/restore_db_from_files.php
// Usage: php scripts/restore_db_from_files.php
// Recreates data/db.sqlite and populates it from data/current_locations/*.geojson (if present)

define('DB_PATH', __DIR__ . '/../data/db.sqlite');
$baseDataDir = __DIR__ . '/../data';
$locationsDir = $baseDataDir . '/current_locations';

if (!is_dir($baseDataDir)) {
    echo "Creating data directory...\n";
    mkdir($baseDataDir, 0755, true);
}

if (file_exists(DB_PATH)) {
    echo "Database already exists at " . DB_PATH . "\n";
    echo "Overwrite and recreate? This will DELETE existing DB. (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    if (trim(strtolower($line)) !== 'y') {
        echo "Aborted. No changes made.\n";
        exit(0);
    }
    unlink(DB_PATH);
    echo "Removed existing DB.\n";
}

try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Creating buses table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS buses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            route TEXT,
            current_location TEXT,
            current_location_name TEXT,
            seats_total INTEGER NOT NULL DEFAULT 40,
            seats_available INTEGER NOT NULL DEFAULT 40,
            status TEXT NOT NULL DEFAULT 'available',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    echo "Seeding initial buses (BUS-001..BUS-003)...\n";
    $stmtInsert = $pdo->prepare("INSERT INTO buses (code, seats_total, seats_available, status) VALUES (?, 40, 40, 'available')");
    $buses = ['BUS-001', 'BUS-002', 'BUS-003'];
    foreach ($buses as $code) {
        $stmtInsert->execute([$code]);
    }

    $restored = 0;
    if (!is_dir($locationsDir)) {
        echo "No current_locations directory found at {$locationsDir}. Nothing else to restore.\n";
    } else {
        $files = glob($locationsDir . '/bus_*.geojson');
        if ($files === false) $files = [];

        echo "Found " . count($files) . " current location files.\n";

        // Prepare update statements
        $updateSql = "UPDATE buses SET current_location = ?, current_location_name = ?, route = COALESCE(?, route), seats_available = COALESCE(?, seats_available), status = COALESCE(?, status), updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $updateStmt = $pdo->prepare($updateSql);

        $insertSql = "INSERT OR REPLACE INTO buses (id, code, route, current_location, current_location_name, seats_total, seats_available, status, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
        $insertStmt = $pdo->prepare($insertSql);

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) continue;

            $json = json_decode($content, true);
            // Determine bus id: from filename first
            $matches = [];
            preg_match('/bus_(\d+)\.geojson$/', $file, $matches);
            $busId = isset($matches[1]) ? intval($matches[1]) : null;

            // fallback: look in properties
            if (($busId === null || $busId === 0) && is_array($json)) {
                // try Feature/FeatureCollection
                if (isset($json['type']) && $json['type'] === 'Feature' && isset($json['properties']['bus_id'])) {
                    $busId = intval($json['properties']['bus_id']);
                } elseif (isset($json['type']) && $json['type'] === 'FeatureCollection' && isset($json['features'][0]['properties']['bus_id'])) {
                    $busId = intval($json['features'][0]['properties']['bus_id']);
                }
            }

            // Determine code if present
            $code = null;
            if (is_array($json)) {
                if (isset($json['type']) && $json['type'] === 'Feature' && isset($json['properties']['code'])) $code = $json['properties']['code'];
                elseif (isset($json['type']) && $json['type'] === 'FeatureCollection' && isset($json['features'][0]['properties']['code'])) $code = $json['features'][0]['properties']['code'];
            }

            // Extract friendly name
            $locationName = null;
            if (is_array($json)) {
                $props = [];
                if (isset($json['type']) && $json['type'] === 'Feature' && isset($json['properties'])) $props = $json['properties'];
                elseif (isset($json['type']) && $json['type'] === 'FeatureCollection' && isset($json['features'][0]['properties'])) $props = $json['features'][0]['properties'];
                elseif (isset($json['properties'])) $props = $json['properties'];

                if (!empty($props['current_location_name'])) $locationName = $props['current_location_name'];
                elseif (!empty($props['Current Location'])) $locationName = $props['Current Location'];
                elseif (!empty($props['name'])) $locationName = $props['name'];
                else {
                    foreach ($props as $k => $v) {
                        if (is_string($v) && trim($v) !== '') { $locationName = trim($v); break; }
                        if ($v === '') { $locationName = $k; break; }
                    }
                }
            }

            // Extra optional fields
            $route = null;
            $seatsAvailable = null;
            $status = null;
            if (is_array($json)) {
                $props = [];
                if (isset($json['type']) && $json['type'] === 'Feature' && isset($json['properties'])) $props = $json['properties'];
                elseif (isset($json['type']) && $json['type'] === 'FeatureCollection' && isset($json['features'][0]['properties'])) $props = $json['features'][0]['properties'];

                if (!empty($props['route'])) $route = $props['route'];
                if (isset($props['seats_available'])) $seatsAvailable = intval($props['seats_available']);
                if (!empty($props['status'])) $status = $props['status'];
            }

            $jsonString = json_encode($json, JSON_UNESCAPED_SLASHES);

            if ($busId && $busId > 0) {
                // if bus exists, update; else insert with that id
                $exists = $pdo->prepare("SELECT id FROM buses WHERE id = ?");
                $exists->execute([$busId]);
                $row = $exists->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $updateStmt->execute([$jsonString, $locationName, $route, $seatsAvailable, $status, $busId]);
                    $restored++;
                    echo "Updated bus id={$busId} from file " . basename($file) . "\n";
                } else {
                    // insert with explicit id to preserve numbering
                    $busCode = $code ?: ("BUS-" . str_pad($busId, 3, "0", STR_PAD_LEFT));
                    $insertStmt->execute([$busId, $busCode, $route, $jsonString, $locationName, 40, $seatsAvailable ?? 40, $status ?? 'available']);
                    $restored++;
                    echo "Inserted bus id={$busId} (code={$busCode}) from file " . basename($file) . "\n";
                }
            } else {
                // No bus id: try to match by code if available
                if ($code) {
                    $exists = $pdo->prepare("SELECT id FROM buses WHERE code = ?");
                    $exists->execute([$code]);
                    $row = $exists->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $id = $row['id'];
                        $updateStmt->execute([$jsonString, $locationName, $route, $seatsAvailable, $status, $id]);
                        $restored++;
                        echo "Updated bus code={$code} (id={$id}) from file " . basename($file) . "\n";
                        continue;
                    } else {
                        // insert new
                        $insertStmt->execute([null, $code, $route, $jsonString, $locationName, 40, $seatsAvailable ?? 40, $status ?? 'available']);
                        $restored++;
                        echo "Inserted new bus code={$code} from file " . basename($file) . "\n";
                        continue;
                    }
                }
                echo "Skipping file " . basename($file) . " - no bus id or code found\n";
            }
        }
    }

    echo "Restore complete. Restored/updated {$restored} bus records (if any).\n";
    echo "Database ready at " . DB_PATH . "\n";
    exit(0);

} catch (PDOException $e) {
    echo "DB error: " . $e->getMessage() . "\n";
    exit(1);
}