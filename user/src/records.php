<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration
$db_host = getenv('DATABASE_HOST') ?: 'db';
$db_name = 'tank-game';
$db_user = getenv('DATABASE_USER') ?: 'game_user';
$db_pass = getenv('DATABASE_PASSWORD') ?: 'secret';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Simple routing
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Rimuove il prefisso del file PHP dal path se presente
$path = str_replace('/user/src/records.php', '', $path);
if (empty($path) || $path === '/') {
    $path = '/';
}

// Debug del path
error_log("Records API - Path: " . $path . " Method: " . $_SERVER['REQUEST_METHOD']);

if (strpos($path, '/records/leaderboard') !== false) {
    // GET /records/leaderboard - Top 10 giocatori
    $stmt = $pdo->prepare("
        SELECT u.username, us.total_points, us.total_kills, us.total_deaths, us.matches_played
        FROM UserStats us 
        JOIN Users u ON us.user_id = u.id 
        ORDER BY us.total_points DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($leaderboard);

} elseif (strpos($path, '/records/user') !== false) {
    // GET /records/user?token=xxx - Statistiche utente specifico
    $token = $_GET['token'] ?? '';
    if (!$token) {
        http_response_code(400);
        echo json_encode(['error' => 'Token required']);
        exit;
    }
    
    // Verifica token e ottieni user_id
    $stmt = $pdo->prepare("
        SELECT t.user_id, u.username 
        FROM Tokens t 
        JOIN Users u ON t.user_id = u.id 
        WHERE t.token = :token AND t.expiration > NOW()
    ");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Invece di errore, fornisci statistiche di default per evitare errori di parsing
        echo json_encode([
            'stats' => [
                'total_points' => 0,
                'total_kills' => 0,
                'total_deaths' => 0,
                'matches_played' => 0,
                'total_playtime' => 0,
                'credits' => 0
            ],
            'recent_records' => [],
            'username' => 'Guest'
        ]);
        exit;
    }
    
    // Ottieni statistiche utente
    $stmt = $pdo->prepare("
        SELECT * FROM UserStats WHERE user_id = :user_id
    ");
    $stmt->execute([':user_id' => $user['user_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ottieni ultimi 5 record
    $stmt = $pdo->prepare("
        SELECT gr.*, gm.name as map_name 
        FROM GameRecords gr 
        LEFT JOIN game_maps gm ON gr.map_id = gm.id 
        WHERE gr.user_id = :user_id 
        ORDER BY gr.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([':user_id' => $user['user_id']]);
    $recent_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'username' => $user['username'],
        'stats' => $stats,
        'recent_games' => $recent_games
    ]);

} elseif (strpos($path, '/records/save') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST /records/save - Salva risultato partita
    $data = json_decode(file_get_contents('php://input'), true);
    
    $token = $data['token'] ?? '';
    $score = (int)($data['score'] ?? 0);
    $kills = (int)($data['kills'] ?? 0);
    $deaths = (int)($data['deaths'] ?? 0);
    $duration = (int)($data['duration'] ?? 0);
    $map_id = (int)($data['map_id'] ?? 1);
    $tank_id = (int)($data['tank_id'] ?? 1);
    
    if (!$token) {
        http_response_code(400);
        echo json_encode(['error' => 'Token required']);
        exit;
    }
    
    // Verifica token
    $stmt = $pdo->prepare("
        SELECT user_id FROM Tokens 
        WHERE token = :token AND expiration > NOW()
    ");
    $stmt->execute([':token' => $token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenData) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Salva record partita
        $stmt = $pdo->prepare("
            INSERT INTO GameRecords (user_id, score, kills, deaths, duration, map_id, tank_id)
            VALUES (:user_id, :score, :kills, :deaths, :duration, :map_id, :tank_id)
        ");
        $stmt->execute([
            ':user_id' => $tokenData['user_id'],
            ':score' => $score,
            ':kills' => $kills,
            ':deaths' => $deaths,
            ':duration' => $duration,
            ':map_id' => $map_id,
            ':tank_id' => $tank_id
        ]);
        
        // Aggiorna statistiche utente
        $stmt = $pdo->prepare("
            UPDATE UserStats SET 
                total_points = total_points + :score,
                total_kills = total_kills + :kills,
                total_deaths = total_deaths + :deaths,
                matches_played = matches_played + 1
            WHERE user_id = :user_id
        ");
        
        $stmt->execute([
            ':score' => $score,
            ':kills' => $kills,
            ':deaths' => $deaths,
            ':user_id' => $tokenData['user_id']
        ]);
        
        // Per ora non gestiamo il level up (non abbiamo colonna experience e level)
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Record salvato con successo'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save record: ' . $e->getMessage()]);
    }

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}
?>
