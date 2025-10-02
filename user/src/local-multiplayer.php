<?php
/**
 * Local Multiplayer API - Sistema cooperativo locale
 * Gestisce: local coop, assegnazione ruoli, controlli multipli
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config/Database.php';

// Parse della richiesta REST
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($request_uri, '/'));
$method = $_SERVER['REQUEST_METHOD'];

// Trova l'endpoint dopo 'local-multiplayer.php'
$endpoint = '';
foreach ($path_parts as $i => $part) {
    if ($part === 'local-multiplayer.php' && isset($path_parts[$i + 1])) {
        $endpoint = $path_parts[$i + 1];
        break;
    }
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    switch ($endpoint) {
        case 'create-local-match':
            if ($method === 'POST') {
                createLocalMatch($conn);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case 'join-local-match':
            if ($method === 'POST') {
                joinLocalMatch($conn);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case 'assign-roles':
            if ($method === 'POST') {
                assignPlayerRoles($conn);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case 'match-status':
            if ($method === 'GET') {
                getMatchStatus($conn);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case 'setup-tanks':
            if ($method === 'POST') {
                setupLocalTanks($conn);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case 'control-schemes':
            if ($method === 'GET') {
                getControlSchemes($conn);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case 'start-local-game':
            if ($method === 'POST') {
                startLocalGame($conn);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

// ===== FUNZIONI API =====

/**
 * Crea una nuova partita multiplayer locale
 */
function createLocalMatch($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    
    if (!$token) {
        http_response_code(400);
        echo json_encode(['error' => 'Token richiesto']);
        return;
    }
    
    $user_id = verifyToken($conn, $token);
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Token non valido']);
        return;
    }
    
    // Parametri partita
    $map_id = $data['map_id'] ?? 1;
    $max_players = $data['max_players'] ?? 4; // Massimo 4 giocatori local
    $game_mode = $data['game_mode'] ?? 'local_coop';
    $players_per_tank = $data['players_per_tank'] ?? 2; // 2 giocatori per tank (driver+gunner)
    
    // Calcola numero di tank necessari
    $max_tanks = ceil($max_players / $players_per_tank);
    if ($max_tanks > 2) $max_tanks = 2; // Massimo 2 tank
    
    try {
        $conn->beginTransaction();
        
        // Crea la partita
        $stmt = $conn->prepare("
            INSERT INTO GameMatches 
            (created_by_user_id, map_id, max_players, game_mode, is_local_multiplayer, 
             max_tanks, players_per_tank, current_players, status)
            VALUES (?, ?, ?, ?, TRUE, ?, ?, 1, 'waiting')
        ");
        
        $stmt->execute([
            $user_id, $map_id, $max_players, $game_mode, 
            $max_tanks, $players_per_tank
        ]);
        
        $match_id = $conn->lastInsertId();
        
        // Aggiungi il creatore della partita
        $stmt = $conn->prepare("
            INSERT INTO GameMatchPlayers 
            (match_id, user_id, tank_slot_number, player_role, control_scheme, status)
            VALUES (?, ?, 1, 'driver', 1, 'ready')
        ");
        $stmt->execute([$match_id, $user_id]);
        
        // Setup iniziale dei tank
        setupInitialTanks($conn, $match_id, $max_tanks);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'match_id' => $match_id,
            'max_players' => $max_players,
            'max_tanks' => $max_tanks,
            'players_per_tank' => $players_per_tank,
            'message' => 'Partita locale creata con successo'
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Errore creazione partita: ' . $e->getMessage()]);
    }
}

/**
 * Join a una partita locale
 */
function joinLocalMatch($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    $match_id = $data['match_id'] ?? '';
    
    if (!$token || !$match_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Token e match_id richiesti']);
        return;
    }
    
    $user_id = verifyToken($conn, $token);
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Token non valido']);
        return;
    }
    
    try {
        $conn->beginTransaction();
        
        // Verifica che la partita esista e sia locale
        $stmt = $conn->prepare("
            SELECT max_players, current_players, players_per_tank, max_tanks, status
            FROM GameMatches 
            WHERE id = ? AND is_local_multiplayer = TRUE AND status = 'waiting'
        ");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$match) {
            throw new Exception('Partita non trovata o non disponibile');
        }
        
        if ($match['current_players'] >= $match['max_players']) {
            throw new Exception('Partita piena');
        }
        
        // Trova il prossimo slot disponibile
        $slot_info = findNextAvailableSlot($conn, $match_id, $match['players_per_tank']);
        
        if (!$slot_info) {
            throw new Exception('Nessun slot disponibile');
        }
        
        // Aggiungi giocatore
        $stmt = $conn->prepare("
            INSERT INTO GameMatchPlayers 
            (match_id, user_id, tank_slot_number, player_role, control_scheme, status)
            VALUES (?, ?, ?, ?, ?, 'waiting')
        ");
        
        $stmt->execute([
            $match_id, $user_id, $slot_info['tank_slot'], 
            $slot_info['role'], $slot_info['control_scheme']
        ]);
        
        // Aggiorna contatore giocatori
        $stmt = $conn->prepare("
            UPDATE GameMatches 
            SET current_players = current_players + 1
            WHERE id = ?
        ");
        $stmt->execute([$match_id]);
        
        // Aggiorna assegnazioni tank
        updateTankAssignments($conn, $match_id);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'tank_slot' => $slot_info['tank_slot'],
            'role' => $slot_info['role'],
            'control_scheme' => $slot_info['control_scheme'],
            'current_players' => $match['current_players'] + 1,
            'message' => "Unito come {$slot_info['role']} al Tank {$slot_info['tank_slot']}"
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Assegna ruoli specifici ai giocatori
 */
function assignPlayerRoles($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    $match_id = $data['match_id'] ?? '';
    $assignments = $data['assignments'] ?? [];
    
    if (!$token || !$match_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Token e match_id richiesti']);
        return;
    }
    
    $user_id = verifyToken($conn, $token);
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Token non valido']);
        return;
    }
    
    // Verifica che l'utente sia il creatore della partita
    $stmt = $conn->prepare("SELECT created_by_user_id FROM GameMatches WHERE id = ?");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$match || $match['created_by_user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Solo il creatore può assegnare i ruoli']);
        return;
    }
    
    try {
        $conn->beginTransaction();
        
        foreach ($assignments as $assignment) {
            $player_id = $assignment['player_id'];
            $tank_slot = $assignment['tank_slot'];
            $role = $assignment['role'];
            $control_scheme = $assignment['control_scheme'] ?? 1;
            
            $stmt = $conn->prepare("
                UPDATE GameMatchPlayers 
                SET tank_slot_number = ?, player_role = ?, control_scheme = ?
                WHERE match_id = ? AND user_id = ?
            ");
            $stmt->execute([$tank_slot, $role, $control_scheme, $match_id, $player_id]);
        }
        
        // Aggiorna assegnazioni tank
        updateTankAssignments($conn, $match_id);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Ruoli assegnati con successo'
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Errore assegnazione ruoli: ' . $e->getMessage()]);
    }
}

/**
 * Ottieni stato completo della partita
 */
function getMatchStatus($conn) {
    $match_id = $_GET['match_id'] ?? '';
    
    if (!$match_id) {
        http_response_code(400);
        echo json_encode(['error' => 'match_id richiesto']);
        return;
    }
    
    // Info partita
    $stmt = $conn->prepare("
        SELECT m.*, u.username as creator_name
        FROM GameMatches m
        JOIN Users u ON m.created_by_user_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$match) {
        http_response_code(404);
        echo json_encode(['error' => 'Partita non trovata']);
        return;
    }
    
    // Giocatori
    $stmt = $conn->prepare("
        SELECT p.*, u.username
        FROM GameMatchPlayers p
        JOIN Users u ON p.user_id = u.id
        WHERE p.match_id = ?
        ORDER BY p.tank_slot_number, p.player_role
    ");
    $stmt->execute([$match_id]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tank assignments
    $stmt = $conn->prepare("
        SELECT t.*, 
               d.username as driver_name,
               g.username as gunner_name
        FROM LocalMultiplayerTanks t
        LEFT JOIN GameMatchPlayers dp ON t.driver_player_id = dp.id
        LEFT JOIN Users d ON dp.user_id = d.id
        LEFT JOIN GameMatchPlayers gp ON t.gunner_player_id = gp.id  
        LEFT JOIN Users g ON gp.user_id = g.id
        WHERE t.match_id = ?
        ORDER BY t.tank_slot_number
    ");
    $stmt->execute([$match_id]);
    $tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'match' => $match,
        'players' => $players,
        'tanks' => $tanks,
        'control_schemes' => getAvailableControlSchemes()
    ]);
}

/**
 * Setup configurazione tank
 */
function setupLocalTanks($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    $match_id = $data['match_id'] ?? '';
    $tank_configs = $data['tank_configs'] ?? [];
    
    if (!$token || !$match_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Token e match_id richiesti']);
        return;
    }
    
    $user_id = verifyToken($conn, $token);
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Token non valido']);
        return;
    }
    
    try {
        $conn->beginTransaction();
        
        foreach ($tank_configs as $config) {
            $stmt = $conn->prepare("
                UPDATE LocalMultiplayerTanks 
                SET tank_model_id = ?, spawn_x = ?, spawn_y = ?, team_id = ?
                WHERE match_id = ? AND tank_slot_number = ?
            ");
            
            $stmt->execute([
                $config['tank_model_id'],
                $config['spawn_x'] ?? 0,
                $config['spawn_y'] ?? 0,
                $config['team_id'] ?? 1,
                $match_id,
                $config['tank_slot']
            ]);
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Configurazione tank aggiornata'
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Errore configurazione: ' . $e->getMessage()]);
    }
}

/**
 * Ottieni schemi di controllo disponibili
 */
function getControlSchemes($conn) {
    echo json_encode([
        'success' => true,
        'schemes' => getAvailableControlSchemes()
    ]);
}

/**
 * Avvia la partita locale
 */
function startLocalGame($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    $match_id = $data['match_id'] ?? '';
    
    if (!$token || !$match_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Token e match_id richiesti']);
        return;
    }
    
    $user_id = verifyToken($conn, $token);
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Token non valido']);
        return;
    }
    
    // Verifica che l'utente sia il creatore
    $stmt = $conn->prepare("SELECT created_by_user_id, current_players FROM GameMatches WHERE id = ?");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$match || $match['created_by_user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Solo il creatore può avviare la partita']);
        return;
    }
    
    if ($match['current_players'] < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'Servono almeno 2 giocatori per iniziare']);
        return;
    }
    
    // Aggiorna stato partita
    $stmt = $conn->prepare("
        UPDATE GameMatches 
        SET status = 'in_progress', started_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$match_id]);
    
    // Genera URL di gioco con parametri per local multiplayer
    $game_url = "/game/local-multiplayer.html?match={$match_id}&mode=local_coop";
    
    echo json_encode([
        'success' => true,
        'game_url' => $game_url,
        'message' => 'Partita avviata!'
    ]);
}

// ===== FUNZIONI HELPER =====

/**
 * Verifica token di autenticazione
 */
function verifyToken($conn, $token) {
    $stmt = $conn->prepare("SELECT user_id FROM Tokens WHERE token = ? AND expiration > NOW()");
    $stmt->execute([$token]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['user_id'] : false;
}

/**
 * Setup iniziale dei tank per la partita
 */
function setupInitialTanks($conn, $match_id, $max_tanks) {
    for ($i = 1; $i <= $max_tanks; $i++) {
        $stmt = $conn->prepare("
            INSERT INTO LocalMultiplayerTanks 
            (match_id, tank_slot_number, tank_model_id, spawn_x, spawn_y, team_id)
            VALUES (?, ?, 1, ?, ?, ?)
        ");
        
        // Spawn positions distanziati
        $spawn_x = 100 + ($i - 1) * 200;
        $spawn_y = 100;
        $team_id = $i; // Ogni tank è un team separato per default
        
        $stmt->execute([$match_id, $i, $spawn_x, $spawn_y, $team_id]);
    }
}

/**
 * Trova il prossimo slot disponibile
 */
function findNextAvailableSlot($conn, $match_id, $players_per_tank) {
    // Ottieni configurazione partita
    $stmt = $conn->prepare("SELECT max_tanks FROM GameMatches WHERE id = ?");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    $max_tanks = $match['max_tanks'];
    
    // Conta giocatori per tank
    for ($tank_slot = 1; $tank_slot <= $max_tanks; $tank_slot++) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count,
                   SUM(CASE WHEN player_role = 'driver' THEN 1 ELSE 0 END) as drivers,
                   SUM(CASE WHEN player_role = 'gunner' THEN 1 ELSE 0 END) as gunners
            FROM GameMatchPlayers 
            WHERE match_id = ? AND tank_slot_number = ?
        ");
        $stmt->execute([$match_id, $tank_slot]);
        $tank_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tank_info['count'] < $players_per_tank) {
            // Determina ruolo necessario
            $role = 'driver';
            $control_scheme = ($tank_slot - 1) * 2 + 1; // Schema base per il tank
            
            if ($tank_info['drivers'] > 0) {
                $role = 'gunner';
                $control_scheme = ($tank_slot - 1) * 2 + 2; // Schema alternativo
            }
            
            return [
                'tank_slot' => $tank_slot,
                'role' => $role,
                'control_scheme' => $control_scheme
            ];
        }
    }
    
    return null; // Nessun slot disponibile
}

/**
 * Aggiorna assegnazioni tank nella tabella LocalMultiplayerTanks
 */
function updateTankAssignments($conn, $match_id) {
    // Per ogni tank slot, trova driver e gunner
    $stmt = $conn->prepare("SELECT DISTINCT tank_slot_number FROM GameMatchPlayers WHERE match_id = ?");
    $stmt->execute([$match_id]);
    $tank_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tank_slots as $tank_slot) {
        // Trova driver
        $stmt = $conn->prepare("
            SELECT id FROM GameMatchPlayers 
            WHERE match_id = ? AND tank_slot_number = ? AND player_role = 'driver'
            LIMIT 1
        ");
        $stmt->execute([$match_id, $tank_slot]);
        $driver_id = $stmt->fetchColumn();
        
        // Trova gunner
        $stmt = $conn->prepare("
            SELECT id FROM GameMatchPlayers 
            WHERE match_id = ? AND tank_slot_number = ? AND player_role = 'gunner'
            LIMIT 1
        ");
        $stmt->execute([$match_id, $tank_slot]);
        $gunner_id = $stmt->fetchColumn();
        
        // Aggiorna tank assignment
        $stmt = $conn->prepare("
            UPDATE LocalMultiplayerTanks 
            SET driver_player_id = ?, gunner_player_id = ?
            WHERE match_id = ? AND tank_slot_number = ?
        ");
        $stmt->execute([$driver_id ?: null, $gunner_id ?: null, $match_id, $tank_slot]);
    }
}

/**
 * Schemi di controllo disponibili
 */
function getAvailableControlSchemes() {
    return [
        1 => [
            'name' => 'WASD + Space',
            'movement' => ['W' => 'su', 'A' => 'sinistra', 'S' => 'giù', 'D' => 'destra'],
            'fire' => 'Space',
            'description' => 'Schema classico WASD'
        ],
        2 => [
            'name' => 'Frecce + Enter', 
            'movement' => ['↑' => 'su', '←' => 'sinistra', '↓' => 'giù', '→' => 'destra'],
            'fire' => 'Enter',
            'description' => 'Schema frecce direzionali'
        ],
        3 => [
            'name' => 'IJKL + M',
            'movement' => ['I' => 'su', 'J' => 'sinistra', 'K' => 'giù', 'L' => 'destra'],
            'fire' => 'M',
            'description' => 'Schema alternativo IJKL'
        ],
        4 => [
            'name' => 'Numpad + 0',
            'movement' => ['8' => 'su', '4' => 'sinistra', '5' => 'giù', '6' => 'destra'],
            'fire' => '0',
            'description' => 'Schema numpad'
        ]
    ];
}

?>
