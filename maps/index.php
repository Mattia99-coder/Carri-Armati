<?php
require_once '../user/src/config/Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Initialize database connection
Database::init('game_user', 'secret');
$db = Database::getInstance();

// Get the request path
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Skip the "maps" and "index.php" parts to get the actual endpoint
$mapsIndex = array_search('maps', $pathParts);
if ($mapsIndex !== false) {
    $pathParts = array_slice($pathParts, $mapsIndex);
}

// Route handling
try {
    if ($pathParts[0] === 'maps') {
        if ($pathParts[1] === 'slots') {
            handleMapsSlots($db);
        } elseif ($pathParts[1] === 'generate') {
            handleMapGenerate($db);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
        }
    } elseif ($pathParts[0] === 'tanks') {
        if ($pathParts[1] === 'slots') {
            handleTanksSlots($db);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleMapsSlots($db) {
    error_log('→ GET /maps/slots ricevuta');
    
    $stmt = $db->getPdo()->prepare('SELECT id, name, cover_path FROM game_maps ORDER BY id');
    $stmt->execute();
    $rows = $stmt->fetchAll();
    
    echo json_encode($rows);
}

function handleTanksSlots($db) {
    error_log('→ GET /tanks/slots ricevuta');
    
    $stmt = $db->getPdo()->prepare('SELECT id, name, cover_path FROM game_tanks ORDER BY id');
    $stmt->execute();
    $rows = $stmt->fetchAll();
    
    echo json_encode($rows);
}

function handleMapGenerate($db) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id']);
        return;
    }
    
    error_log("→ GET /maps/generate?id={$id} ricevuta");
    
    $stmt = $db->getPdo()->prepare(
        'SELECT t.type, t.color, t.texture_pattern 
         FROM biomes b
         JOIN terrain_types t ON b.id = t.biome_id
         WHERE b.game_maps_id = ?
         ORDER BY t.id'
    );
    $stmt->execute([$id]);
    $biomes = $stmt->fetchAll();
    
    $seed = mt_rand(0, 1000000000);
    $seedString = (string)$seed;
    
    error_log("Seed: {$seedString}");
    
    echo json_encode([
        'id' => $id,
        'seed' => $seedString,
        'biomes' => $biomes
    ]);
}
?>