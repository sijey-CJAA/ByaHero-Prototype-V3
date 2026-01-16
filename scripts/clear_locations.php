#!/usr/bin/php
<?php
/**
 * scripts/clear_locations.php
 *
 * Windows-friendly CLI tool to clear lat/lng for buses in the SQLite DB.
 *
 * Usage (run from repo root or anywhere inside the repo):
 *   php scripts/clear_locations.php                    # interactive (default)
 *   php scripts/clear_locations.php --force            # no prompt
 *   php scripts/clear_locations.php --bus-id=1         # clear specific bus id
 *   php scripts/clear_locations.php --all              # clear all lat/lng values
 *   php scripts/clear_locations.php --stale-minutes=60 # clear buses not updated in last 60 minutes (default 60)
 *   php scripts/clear_locations.php --status=available,unavailable
 *
 * Examples:
 *   php scripts/clear_locations.php --stale-minutes=2 --force
 *   php scripts/clear_locations.php --bus-id=1
 *
 * Behavior:
 * - If --bus-id is given: clear that bus regardless of status.
 * - Else if --all is given: clear lat/lng for all rows that currently have non-NULL lat or lng.
 * - Else: clear rows that (status = 'unavailable' OR updated_at older than --stale-minutes)
 *         AND that have lat or lng set.
 */

error_reporting(E_ALL);
ini_set('display_errors', 'On');

$opts = getopt('', ['force', 'bus-id:', 'stale-minutes:', 'status:', 'all']);
$force = isset($opts['force']);
$busId = isset($opts['bus-id']) ? (int)$opts['bus-id'] : null;
$staleMinutes = isset($opts['stale-minutes']) ? max(0, (int)$opts['stale-minutes']) : 60;
$statusFilter = isset($opts['status']) ? $opts['status'] : null;
$all = isset($opts['all']);

/**
 * Try to find data/db.sqlite by walking up from current directory (__DIR__)
 * up to repo root levels.
 */
function find_db_path() {
    $dir = __DIR__;
    for ($i = 0; $i < 8; $i++) {
        $candidate = $dir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'db.sqlite';
        if (file_exists($candidate)) {
            return realpath($candidate);
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    // also try current working directory upward (if run from different cwd)
    $dir = getcwd();
    for ($i = 0; $i < 8; $i++) {
        $candidate = $dir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'db.sqlite';
        if (file_exists($candidate)) {
            return realpath($candidate);
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    return false;
}

$dbPath = find_db_path();
if (!$dbPath) {
    fwrite(STDERR, "Error: could not find data/db.sqlite in this repo. Run init_db.php first or ensure you are inside the repo.\n");
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    fwrite(STDERR, "Error opening database: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

// Build WHERE clause and params
$where = [];
$params = [];

if ($busId) {
    $where[] = 'id = ?';
    $params[] = $busId;
} elseif ($all) {
    $where[] = '(lat IS NOT NULL OR lng IS NOT NULL)';
} else {
    // default behavior: status = 'unavailable' OR updated_at older than X minutes
    $conds = [];
    $conds[] = "status = 'unavailable'";
    if ($staleMinutes > 0) {
        // Use SQLite datetime relative expression
        $conds[] = "updated_at < datetime('now', ?)";
        $params[] = "-{$staleMinutes} minutes";
    }
    $where[] = '(' . implode(' OR ', $conds) . ')';
    // Only consider rows that actually have coords
    $where[] = '(lat IS NOT NULL OR lng IS NOT NULL)';
}

// If status filter provided, include AND status IN (...)
if ($statusFilter && !$busId && !$all) {
    // allow comma separated list
    $statuses = array_filter(array_map('trim', explode(',', $statusFilter)));
    if (count($statuses) > 0) {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $where[] = "status IN ($placeholders)";
        foreach ($statuses as $s) $params[] = $s;
    }
}

$finalWhere = implode(' AND ', $where);

// Select rows to show before updating
$sqlSelect = "SELECT id, code, route, lat, lng, seats_total, seats_available, status, updated_at FROM buses WHERE $finalWhere ORDER BY id";
$stmt = $db->prepare($sqlSelect);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) === 0) {
    echo "No buses require clearing (no matching rows found)." . PHP_EOL;
    exit(0);
}

echo "Found " . count($rows) . " row(s) that would be cleared:\n";
foreach ($rows as $r) {
    $lat = ($r['lat'] === null) ? 'NULL' : $r['lat'];
    $lng = ($r['lng'] === null) ? 'NULL' : $r['lng'];
    echo sprintf(
        "  id=%d code=%s route=%s status=%s lat=%s lng=%s updated_at=%s\n",
        $r['id'],
        $r['code'],
        $r['route'] ?? 'NULL',
        $r['status'],
        $lat,
        $lng,
        $r['updated_at']
    );
}

if (!$force) {
    echo "\nProceed to clear lat/lng for the above rows? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    $confirm = trim(strtolower((string)$line));
    if ($confirm !== 'y' && $confirm !== 'yes') {
        echo "Aborted. No changes made.\n";
        exit(0);
    }
}

// Perform update
$sqlUpdate = "UPDATE buses SET lat = NULL, lng = NULL, updated_at = CURRENT_TIMESTAMP WHERE $finalWhere";
$updateStmt = $db->prepare($sqlUpdate);
$updateStmt->execute($params);
$affected = $updateStmt->rowCount();

echo "Done. Rows updated: $affected\n";
exit(0);