<?php
/**
 * ByaHero Bus Tracking API (update: persist current_location_name)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

define('DB_PATH', __DIR__ . '/../data/db.sqlite');

function getDB() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}

function initDB() {
    $db = getDB();

    // Create buses table with current_location and current_location_name
    $db->exec("
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

    $count = $db->query("SELECT COUNT(*) FROM buses")->fetchColumn();
    if ($count == 0) {
        $stmt = $db->prepare("
            INSERT INTO buses (code, seats_total, seats_available, status) 
            VALUES (?, 40, 40, 'available')
        ");
        $buses = ['BUS-001', 'BUS-002', 'BUS-003'];
        foreach ($buses as $busCode) $stmt->execute([$busCode]);
    }

    return ['success' => true, 'message' => 'Database initialized successfully'];
}

function getBuses() {
    $db = getDB();
    $stmt = $db->query("
        SELECT id, code, route, current_location, current_location_name, seats_total, seats_available, status, updated_at 
        FROM buses 
        ORDER BY code
    ");
    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['success' => true, 'buses' => $buses];
}

/**
 * Update bus location and details
 * Accepts geojson (preferred) or lat/lng. Stores current_location (JSON string) and current_location_name when provided.
 */
function updateLocation() {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null) {
        http_response_code(400);
        return ['success' => false, 'error' => 'Invalid JSON'];
    }
    if (!isset($data['bus_id'])) {
        http_response_code(400);
        return ['success' => false, 'error' => 'Missing required field: bus_id'];
    }

    $busId = intval($data['bus_id']);

    // Build GeoJSON if provided; if only lat/lng given, convert to a Point GeoJSON
    $geojson = null;
    if (isset($data['geojson'])) {
        $geojson = $data['geojson'];
    } elseif (isset($data['lat']) && isset($data['lng'])) {
        $lat = filter_var($data['lat'], FILTER_VALIDATE_FLOAT);
        $lng = filter_var($data['lng'], FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            http_response_code(400);
            return ['success' => false, 'error' => 'Invalid lat/lng values'];
        }
        $geojson = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$lng, $lat]
            ],
            'properties' => [
                'timestamp' => gmdate('c')
            ]
        ];
    } else {
        http_response_code(400);
        return ['success' => false, 'error' => 'Provide geojson or lat & lng'];
    }

    // Try to extract friendly name from geojson properties
    $locationName = null;
    if (isset($geojson['properties'])) {
        if (!empty($geojson['properties']['current_location_name'])) $locationName = $geojson['properties']['current_location_name'];
        elseif (!empty($geojson['properties']['Current Location'])) $locationName = $geojson['properties']['Current Location'];
        elseif (!empty($geojson['properties']['name'])) $locationName = $geojson['properties']['name'];
        else {
            // if properties like {"Bugaan East":"Laurel"} pick the first non-empty value or the key
            foreach ($geojson['properties'] as $k => $v) {
                if (is_string($v) && trim($v) !== '') { $locationName = trim($v); break; }
                if ($v === '') { $locationName = $k; break; }
            }
        }
    }

    $params = [];
    $fields = ['current_location = ?', 'updated_at = CURRENT_TIMESTAMP'];
    $params[] = json_encode($geojson, JSON_UNESCAPED_SLASHES);

    if (!empty($locationName)) {
        $fields[] = 'current_location_name = ?';
        $params[] = $locationName;
    }

    if (isset($data['route'])) {
        $fields[] = 'route = ?';
        $params[] = $data['route'];
    }
    if (isset($data['seats_available'])) {
        $sa = filter_var($data['seats_available'], FILTER_VALIDATE_INT);
        if ($sa === false || $sa < 0) {
            http_response_code(400);
            return ['success' => false, 'error' => 'Invalid seats_available value'];
        }
        $fields[] = 'seats_available = ?';
        $params[] = $sa;
    }
    if (isset($data['status'])) {
        $allowed = ['available','on_stop','full','unavailable'];
        if (!in_array($data['status'], $allowed)) {
            http_response_code(400);
            return ['success' => false, 'error' => 'Invalid status value'];
        }
        $fields[] = 'status = ?';
        $params[] = $data['status'];
    }

    $params[] = $busId;
    $sql = "UPDATE buses SET " . implode(', ', $fields) . " WHERE id = ?";
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['success' => true, 'message' => 'Location updated successfully', 'current_location_name' => $locationName];
}

function stopTracking() {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null) {
        http_response_code(400);
        return ['success' => false, 'error' => 'Invalid JSON'];
    }
    if (!isset($data['bus_id'])) {
        return ['success' => false, 'error' => 'Missing bus_id'];
    }

    $db = getDB();
    $stmt = $db->prepare("
        UPDATE buses
        SET current_location = NULL, current_location_name = NULL, status = 'unavailable', updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$data['bus_id']]);

    return ['success' => true, 'message' => 'Stopped tracking for bus'];
}

// Dispatch remains the same...
$action = $_GET['action'] ?? $_POST['action'] ?? 'get_buses';
try {
    switch ($action) {
        case 'init_db':
            $response = initDB();
            break;
        case 'get_buses':
            $response = getBuses();
            break;
        case 'update_location':
            $response = updateLocation();
            break;
        case 'stop_tracking':
            $response = stopTracking();
            break;
        default:
            http_response_code(400);
            $response = ['success' => false, 'error' => 'Invalid action'];
    }
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}