<?php
/**
 * Updated Admin UI with real-time bus indicators on the map.
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

define('DB_PATH', __DIR__ . '/../../data/db.sqlite');

$envUser = getenv('ADMIN_USER');
$envPass = getenv('ADMIN_PASS');

define('ADMIN_USER', $envUser !== false ? $envUser : 'admin');
define('ADMIN_PASS', $envPass !== false ? $envPass : 'password');

/* --- Basic Auth --- */
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])
    || $_SERVER['PHP_AUTH_USER'] !== ADMIN_USER
    || $_SERVER['PHP_AUTH_PW'] !== ADMIN_PASS) {
    header('WWW-Authenticate: Basic realm="ByaHero Admin"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required';
    exit;
}

function getDB(): PDO {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo "DB connection failed: " . htmlspecialchars($e->getMessage());
        exit;
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
session_start();

/* --- Fetch active buses and their locations --- */
$pdo = getDB();
$activeBuses = $pdo->query("SELECT id, code, route, current_location_name, seats_total, seats_available, status, updated_at FROM buses WHERE status IN ('available', 'on_stop', 'full')")->fetchAll(PDO::FETCH_ASSOC);
$locations = []; // To store live geo-coordinates for buses

foreach ($activeBuses as $bus) {
    $locationFile = __DIR__ . "/../../data/current_locations/bus_{$bus['id']}.geojson";
    if (is_file($locationFile)) {
        $geoData = json_decode(file_get_contents($locationFile), true);
        if (isset($geoData['geometry']['coordinates'])) {
            $locations[] = [
                'id' => $bus['id'],
                'code' => $bus['code'],
                'route' => $bus['route'],
                'location' => $bus['current_location_name'] ?? 'Unknown',
                'seats' => "{$bus['seats_available']} / {$bus['seats_total']}",
                'status' => $bus['status'],
                'updated_at' => $bus['updated_at'],
                'coordinates' => $geoData['geometry']['coordinates'] // [longitude, latitude]
            ];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>ByaHero — ADMIN</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.3/dist/leaflet.js"></script>
<link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.3/dist/leaflet.css" rel="stylesheet">

<style>
    body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial; background:#f6f7fb; color:#111; padding:0; margin:0; }
    .navbar { background:#2563eb; color:#fff; padding:16px; display:flex; justify-content:space-between; align-items:center; }
    .navbar a { color:#fff; text-decoration:none; font-weight:bold; padding:8px 16px; border-radius:6px; }
    .navbar a.active, .navbar a:hover { background:#1d4ed8; }
    .container { padding:20px; max-width:1100px; margin:0 auto; }
    .panel { background:#fff; padding:16px; border-radius:6px; box-shadow:0 1px 2px rgba(0,0,0,0.03); }
    .flash { padding:10px 12px; }
    #map { height:500px; border-radius:6px; margin-top:16px; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:8px 10px; border-bottom:1px solid #eee; text-align:left; }
</style>
</head>
<body>
<div class="navbar">
    <span>ByaHero — ADMIN</span>
    <div>
        <a href="#view" class="tablink active" onclick="openTab(event, 'view')">View Active Buses</a>
        <a href="#add" class="tablink" onclick="openTab(event, 'add')">Add New Bus</a>
    </div>
</div>

<div class="container">
    <div id="view" class="panel">
        <h2>Active Buses</h2>
        <table>
            <thead>
                <tr><th>ID</th><th>Code</th><th>Route</th><th>Location</th><th>Seats</th><th>Status</th><th>Updated</th></tr>
            </thead>
            <tbody>
                <?php foreach ($activeBuses as $bus): ?>
                <tr>
                    <td><?= h($bus['id']) ?></td>
                    <td><?= h($bus['code']) ?></td>
                    <td><?= h($bus['route']) ?></td>
                    <td><?= h($bus['current_location_name']) ?></td>
                    <td><?= h("{$bus['seats_available']} / {$bus['seats_total']}") ?></td>
                    <td><?= h($bus['status']) ?></td>
                    <td><?= h($bus['updated_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div id="map"></div>
    </div>
</div>

<script>
    const map = L.map('map').setView([14.5995, 120.9842], 10); // Default view (Manila)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(map);

    const busIcons = {
        'available': L.icon({ iconUrl: 'green-marker.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34] }),
        'on_stop': L.icon({ iconUrl: 'orange-marker.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34] }),
        'full': L.icon({ iconUrl: 'red-marker.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34] })
    };

    const buses = <?= json_encode($locations) ?>;

    buses.forEach(bus => {
        if (bus.coordinates) {
            const [lng, lat] = bus.coordinates;
            const marker = L.marker([lat, lng], { icon: busIcons[bus.status] || busIcons['available'] })
                .addTo(map)
                .bindPopup(`<strong>Bus Code:</strong> ${bus.code}<br><strong>Route:</strong> ${bus.route}<br><strong>Location:</strong> ${bus.location}<br><strong>Seats:</strong> ${bus.seats}<br><strong>Status:</strong> ${bus.status}`);
        }
    });
</script>
</body>
</html>