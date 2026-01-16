<?php
// php scripts/db_inspect.php
$path = __DIR__ . '/../data/db.sqlite';
echo "DB path: $path\n";
if (!file_exists($path)) {
    echo "ERROR: DB not found at $path\n";
    exit(1);
}
try {
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to DB.\n\n";

    echo "Tables:\n";
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) echo " - $t\n";

    if (!in_array('buses', $tables)) {
        echo "\nNo 'buses' table found.\n";
        exit(0);
    }

    echo "\nSchema for 'buses':\n";
    $cols = $pdo->query("PRAGMA table_info(buses)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo sprintf(" %s %s (pk=%s, dflt=%s)\n", $c['name'], $c['type'], $c['pk'], $c['dflt_value']);
    }

    echo "\nFirst 10 rows (if any):\n";
    $rows = $pdo->query("SELECT * FROM buses LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo " (no rows)\n";
    } else {
        foreach ($rows as $r) {
            echo json_encode($r, JSON_UNESCAPED_SLASHES) . "\n";
        }
    }

} catch (PDOException $e) {
    echo "DB error: " . $e->getMessage() . "\n";
    exit(1);
}