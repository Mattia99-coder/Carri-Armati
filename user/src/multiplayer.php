<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Rimuove il prefisso del file PHP dal path se presente
$path = str_replace('/user/src/multiplayer.php', '', $path);
if (empty($path) || $path === '/') {
    $path = '/';
}

// Debug del path
error_log("Multiplayer API - Path: " . $path . " Method: " . $_SERVER['REQUEST_METHOD']);

// Funzione per verificare token
function getUserFromToken($pdo, $token) {
    $stmt = $pdo->prepare("
        SELECT t.user_id, u.username 
        FROM Tokens t 
        JOIN Users u ON t.user_id = u.id 
        WHERE t.token = :token AND t.expiration > NOW()
    ");
    $stmt->execute([':token' => $token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

if (strpos($path, '/multiplayer/create') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST /multiplayer/create - Crea nuova partita
    $data = json_decode(file_get_contents('php://input'), true);
    
    $token = $data['token'] ?? '';
    $map_id = (int)($data['map_id'] ?? 1);
    $max_players = min(4, max(2, (int)($data['max_players'] ?? 2))); // Min 2, Max 4
    
    $user = getUserFromToken($pdo, $token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    $match_id = 'match_' . time() . '_' . $user['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Crea partita
        $stmt = $pdo->prepare("
            INSERT INTO GameMatches (created_by_user_id, map_id, max_players, status)
            VALUES (:created_by_user_id, :map_id, :max_players, 'waiting')
        ");
        $stmt->execute([
            ':created_by_user_id' => $user['user_id'],
            ':map_id' => $map_id,
            ':max_players' => $max_players
        ]);
        
        $match_id = $pdo->lastInsertId();
        
        // Aggiungi creatore come primo giocatore
        $stmt = $pdo->prepare("
            INSERT INTO GameMatchPlayers (match_id, user_id, status)
            VALUES (:match_id, :user_id, 'ready')
        ");
        $stmt->execute([
            ':match_id' => $match_id,
            ':user_id' => $user['user_id']
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'match_id' => $match_id,
            'join_url' => "/multiplayer/join/{$match_id}"
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create match']);
    }

} elseif (strpos($path, '/multiplayer/list') !== false) {
    // GET /multiplayer/list - Lista partite disponibili
    try {
        $stmt = $pdo->prepare("
            SELECT gm.*, u.username as created_by_name,
                   (SELECT COUNT(*) FROM GameMatchPlayers gmp WHERE gmp.match_id = gm.id) as current_players,
                   (SELECT name FROM game_maps WHERE id = gm.map_id) as map_name
            FROM GameMatches gm 
            JOIN Users u ON gm.created_by_user_id = u.id 
            WHERE gm.status = 'waiting'
            ORDER BY gm.created_at DESC
        ");
        $stmt->execute();
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($matches);
    } catch (PDOException $e) {
        // Se fallisce, restituisci array vuoto per evitare errori di parsing
        echo json_encode([]);
    }

} elseif (strpos($path, '/multiplayer/join') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST /multiplayer/join - Unisciti a partita
    $data = json_decode(file_get_contents('php://input'), true);
    
    $token = $data['token'] ?? '';
    $match_id = $data['match_id'] ?? '';
    $tank_id = (int)($data['tank_id'] ?? 1);
    
    $user = getUserFromToken($pdo, $token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Verifica se la partita esiste ed è disponibile
        $stmt = $pdo->prepare("
            SELECT * FROM GameMatches 
            WHERE id = :match_id AND status = 'waiting'
        ");
        $stmt->execute([':match_id' => $match_id]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$match) {
            http_response_code(404);
            echo json_encode(['error' => 'Match not found or not available']);
            exit;
        }
        
        // Conta giocatori attuali
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM GameMatchPlayers WHERE match_id = :match_id
        ");
        $stmt->execute([':match_id' => $match_id]);
        $player_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($player_count >= $match['max_players']) {
            http_response_code(400);
            echo json_encode(['error' => 'Match is full']);
            exit;
        }
        
        // Verifica se l'utente è già nella partita
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM GameMatchPlayers 
            WHERE match_id = :match_id AND user_id = :user_id
        ");
        $stmt->execute([':match_id' => $match_id, ':user_id' => $user['user_id']]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Already in this match']);
            exit;
        }
        
        // Aggiungi giocatore
        $stmt = $pdo->prepare("
            INSERT INTO GameMatchPlayers (match_id, user_id, status)
            VALUES (:match_id, :user_id, 'waiting')
        ");
        $stmt->execute([
            ':match_id' => $match_id,
            ':user_id' => $user['user_id']
        ]);
        
        // Calcola nuovo conteggio giocatori
        $new_count = $player_count + 1;
        
        // Se raggiunto il minimo (2 giocatori), la partita può iniziare
        $can_start = $new_count >= 2;
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'current_players' => $new_count,
            'max_players' => $match['max_players'],
            'can_start' => $can_start
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to join match']);
    }

} elseif (strpos($path, '/multiplayer/invite') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST /multiplayer/invite - Invita amico
    $data = json_decode(file_get_contents('php://input'), true);
    
    $token = $data['token'] ?? '';
    $friend_username = $data['friend_username'] ?? '';
    $match_id = $data['match_id'] ?? '';
    
    $user = getUserFromToken($pdo, $token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    // Trova ID dell'amico
    $stmt = $pdo->prepare("SELECT id FROM Users WHERE username = :username");
    $stmt->execute([':username' => $friend_username]);
    $friend = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$friend) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Crea invito
    $stmt = $pdo->prepare("
        INSERT INTO FriendInvites (from_user_id, to_user_id, match_id)
        VALUES (:from_user_id, :to_user_id, :match_id)
    ");
    $stmt->execute([
        ':from_user_id' => $user['user_id'],
        ':to_user_id' => $friend['id'],
        ':match_id' => $match_id
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Invite sent']);

} elseif (strpos($path, '/multiplayer/invites') !== false) {
    // GET /multiplayer/invites?token=xxx - Lista inviti ricevuti
    $token = $_GET['token'] ?? '';
    
    $user = getUserFromToken($pdo, $token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT fi.*, u.username as from_username, gm.map_id,
               (SELECT name FROM game_maps WHERE id = gm.map_id) as map_name
        FROM FriendInvites fi 
        JOIN Users u ON fi.from_user_id = u.id 
        JOIN GameMatches gm ON fi.match_id = gm.id 
        WHERE fi.to_user_id = :user_id AND fi.status = 'pending'
        ORDER BY fi.created_at DESC
    ");
    $stmt->execute([':user_id' => $user['user_id']]);
    $invites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($invites);

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}
?>
