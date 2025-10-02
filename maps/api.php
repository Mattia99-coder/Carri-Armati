<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration
$host = isset($_ENV['DATABASE_HOST']) ? $_ENV['DATABASE_HOST'] : 'localhost';
$port = isset($_ENV['DATABASE_PORT']) ? $_ENV['DATABASE_PORT'] : (($host === 'localhost') ? '13306' : '3306');
$dbname = 'tank-game';
$username = isset($_ENV['DATABASE_USER']) ? $_ENV['DATABASE_USER'] : 'root';
$password = isset($_ENV['DATABASE_PASSWORD']) ? $_ENV['DATABASE_PASSWORD'] : 'rootpassword';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage(), 'config' => "host=$host:$port, db=$dbname, user=$username"]);
    exit;
}

// Funzione per verificare il token utente
function verifyToken($pdo, $token) {
    if (empty($token)) return false;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.username 
            FROM Tokens t 
            JOIN Users u ON t.user_id = u.id 
            WHERE t.token = ? AND t.expiration > NOW()
        ");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

// Get the request path
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Simple routing based on URL path or query parameter
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';
if (empty($endpoint)) {
    // Check path-based routing
    if (strpos($path, '/maps/slots') !== false || strpos($path, 'maps/slots') !== false) {
        $endpoint = 'maps/slots';
    } elseif (strpos($path, '/tanks/owned') !== false || strpos($path, 'tanks/owned') !== false) {
        $endpoint = 'tanks/owned';
    } elseif (strpos($path, '/tanks/slots') !== false || strpos($path, 'tanks/slots') !== false) {
        $endpoint = 'tanks/slots';
    } elseif (strpos($path, '/maps/generate') !== false || strpos($path, 'maps/generate') !== false) {
        $endpoint = 'maps/generate';
    }
}

if ($endpoint === 'maps/slots') {
    try {
        // Get maps from database (using actual table structure)
        $stmt = $pdo->query("SELECT id, name, description, biome, seed FROM game_maps ORDER BY id");
        $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add default cover paths since they don't exist in this version of the table
        foreach ($maps as &$map) {
            $map['cover_path'] = "/assets/covers/{$map['id']}.png";
        }
        unset($map); // Break the reference
        
        echo json_encode($maps);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch maps: ' . $e->getMessage()]);
    }

} elseif ($endpoint === 'tanks/owned') {
    // Endpoint per tank posseduti dall'utente (richiede token)
    $token = $_GET['token'] ?? '';
    $user = verifyToken($pdo, $token);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or missing token']);
        exit;
    }
    
    try {
        // Check if UserOwnedTanks table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'UserOwnedTanks'");
        $result = $stmt->fetch();
        
        if ($result) {
            // Get owned tanks from database + always include free tanks (price = 0)
            $stmt = $pdo->prepare("
                SELECT DISTINCT t.id, t.name, t.cover_path, t.price, t.description
                FROM game_tanks t
                LEFT JOIN UserOwnedTanks ot ON t.id = ot.tank_id AND ot.user_id = ?
                WHERE ot.tank_id IS NOT NULL OR t.price = 0
                ORDER BY t.price ASC, t.id ASC
            ");
            $stmt->execute([$user['id']]);
            $tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If no tanks found, ensure at least the free tank is available
            if (empty($tanks)) {
                $stmt = $pdo->query("SELECT id, name, cover_path, price, description FROM game_tanks WHERE price = 0 ORDER BY id");
                $tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            // Fallback: restituisci solo il tank base (prezzo 0) se la tabella non esiste
            $stmt = $pdo->query("SELECT id, name, cover_path, price, description FROM game_tanks WHERE price = 0 ORDER BY id");
            $tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode($tanks);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch owned tanks: ' . $e->getMessage()]);
    }
    
} elseif ($endpoint === 'tanks/slots') {
    try {
        // Check if game_tanks table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'game_tanks'");
        $result = $stmt->fetch();
        
        if ($result) {
            // Get tanks from database  
            $stmt = $pdo->query("SELECT id, name, cover_path FROM game_tanks ORDER BY id");
            $tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Use default tanks since table doesn't exist
            $tanks = [
                ['id' => 1, 'name' => 'Tank Standard', 'cover_path' => '/assets/tanks/1.png'],
                ['id' => 2, 'name' => 'Tank Pesante', 'cover_path' => '/assets/tanks/2.png'],
                ['id' => 11, 'name' => 'Tank Veloce', 'cover_path' => '/assets/tanks/tank11.png'],
                ['id' => 12, 'name' => 'Tank d\'Assalto', 'cover_path' => '/assets/tanks/tank12.png'],
                ['id' => 13, 'name' => 'Tank Sniper', 'cover_path' => '/assets/tanks/tank13.png']
            ];
        }
        
        echo json_encode($tanks);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch tanks: ' . $e->getMessage()]);
    }
    
} elseif ($endpoint === 'maps/generate') {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
        
        // Get map data from database
        $stmt = $pdo->prepare("SELECT id, name, seed, biome FROM game_maps WHERE id = ?");
        $stmt->execute([$id]);
        $map = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$map) {
            http_response_code(404);
            echo json_encode(['error' => 'Map not found']);
            exit;
        }
        
        // Get biomes for this map from database
        $stmt = $pdo->prepare("
            SELECT b.label, tt.type, tt.color, tt.texture_pattern 
            FROM biomes b 
            LEFT JOIN terrain_types tt ON b.id = tt.biome_id 
            WHERE b.game_maps_id = ?
        ");
        $stmt->execute([$id]);
        $biomesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize biomes data
        $biomesGrouped = [];
        foreach ($biomesData as $row) {
            $biomeLabel = $row['label'];
            
            if (!isset($biomesGrouped[$biomeLabel])) {
                $biomesGrouped[$biomeLabel] = [
                    'label' => $biomeLabel,
                    'terrain_types' => []
                ];
            }
            
            if ($row['type']) {
                $biomesGrouped[$biomeLabel]['terrain_types'][] = [
                    'type' => $row['type'],
                    'color' => $row['color'],
                    'texture_pattern' => $row['texture_pattern']
                ];
            }
        }
        
        // Convert associative array to indexed array
        $biomes = array_values($biomesGrouped);
        
        echo json_encode([
            'id' => $map['id'],
            'name' => $map['name'],
            'seed' => (string)$map['seed'], // Ensure seed is a string
            'biome' => $map['biome'],
            'biomes' => $biomes
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate map: ' . $e->getMessage()]);
    }
    
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found', 'path' => $path, 'endpoint' => $endpoint]);
}
?>
