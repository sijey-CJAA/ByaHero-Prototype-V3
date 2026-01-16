#!/usr/bin/env php
<?php
/**
 * ByaHero Database Initialization Script (updated to use current_location)
 *
 * Run: php init_db.php
 */

define('DB_PATH', __DIR__ . '/data/db.sqlite');

echo "ByaHero Database Initialization\n";
echo "================================\n\n";

// Ensure data directory exists
if (!is_dir(__DIR__ . '/data')) {
    echo "Creating data directory...\n";
    mkdir(__DIR__ . '/data', 0750, true);
}

// If DB exists, confirm reinitialize
if (file_exists(DB_PATH)) {
    echo "Warning: Database already exists at " . DB_PATH . "\n";
    echo "Do you want to reinitialize it? This will clear all data. (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);

    if (trim(strtolower($line)) !== 'y') {
        echo "Initialization cancelled.\n";
        exit(0);
    }

    echo "Removing existing database...\n";
    unlink(DB_PATH);
}

try {
    echo "Creating database connection...\n";
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Creating buses table (with current_location)...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS buses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            route TEXT,
            current_location TEXT, -- stores GeoJSON (string)
            seats_total INTEGER NOT NULL DEFAULT 40,
            seats_available INTEGER NOT NULL DEFAULT 40,
            status TEXT NOT NULL DEFAULT 'available',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    echo "Seeding initial bus data...\n";
    $stmt = $db->prepare("
        INSERT INTO buses (code, seats_total, seats_available, status) 
        VALUES (?, 40, 40, 'available')
    ");

    $buses = ['BUS-001', 'BUS-002', 'BUS-003'];
    foreach ($buses as $busCode) {
        $stmt->execute([$busCode]);
        echo "  - Added: $busCode\n";
    }

    echo "\n✅ Database initialized successfully!\n";

    // Helper: attempt to determine a LAN-accessible IPv4 address for this machine
    function getLocalIp()
    {
        // Try sockets extension (preferred)
        if (function_exists('socket_create')) {
            try {
                $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                if ($sock !== false) {
                    // connect to a public IP; no data is actually sent
                    @socket_connect($sock, '8.8.8.8', 53);
                    $name = '';
                    if (@socket_getsockname($sock, $name)) {
                        @socket_close($sock);
                        if (filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $name !== '127.0.0.1') {
                            return $name;
                        }
                    } else {
                        @socket_close($sock);
                    }
                }
            } catch (Throwable $e) {
                // ignore and fall back
            }
        }

        // Try stream socket method
        try {
            $sock = @stream_socket_client("udp://8.8.8.8:53", $errno, $errstr, 1);
            if ($sock) {
                $name = stream_socket_get_name($sock, false); // returns "ip:port"
                fclose($sock);
                if ($name) {
                    $parts = explode(':', $name);
                    if (!empty($parts[0]) && filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $parts[0] !== '127.0.0.1') {
                        return $parts[0];
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        // Fallback: try resolving hostname
        try {
            $h = gethostbyname(gethostname());
            if ($h && $h !== '127.0.0.1') {
                return $h;
            }
        } catch (Throwable $e) {
            // ignore
        }

        return null;
    }

    $localIp = getLocalIp();

    echo "\nNext steps:\n";
    echo "1. Start the PHP server (bind to localhost for local-only access):\n";
    echo "   php -S localhost:8000 -t public\n";
    echo "\n   Or bind to all interfaces so other devices on the same Wi‑Fi can reach it:\n";
    echo "   php -S 0.0.0.0:8000 -t public\n";

    echo "\n2. Open in your browser:\n";
    echo "   - Passenger view (on this machine): http://localhost:8000/index.php\n";
    echo "   - Conductor view (on this machine): http://localhost:8000/conductor.php\n";

    if ($localIp) {
        echo "\n👉 Accessible on your phone (same Wi‑Fi network):\n";
        echo "   - Passenger view: http://{$localIp}:8000/index.php\n";
        echo "   - Conductor view: http://{$localIp}:8000/conductor.php\n";
        echo "\nNotes: Make sure your firewall allows port 8000 and that your phone is on the same network as this machine.\n";
    } else {
        echo "\nNote: Could not automatically detect a LAN IP. To access from your phone:\n";
        echo "  - Find your computer's local IP (e.g., 192.168.x.x) and then open:\n";
        echo "      http://<your-computer-ip>:8000/index.php\n";
        echo "  - Or run the server bound to all interfaces as shown above (php -S 0.0.0.0:8000 -t public).\n";
    }

    echo "\nHappy tracking! 🚌\n";
} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
