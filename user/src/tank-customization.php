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
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Verifica token utente
function verifyToken($pdo, $token) {
    $stmt = $pdo->prepare("SELECT user_id FROM Tokens WHERE token = ? AND expiration > NOW()");
    $stmt->execute([$token]);
    return $stmt->fetchColumn();
}

// Routing delle API
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Rimuove il prefisso del file PHP dal path se presente
$path = str_replace('/user/src/tank-customization.php', '', $path);
if (empty($path) || $path === '/') {
    $path = '/';
}

// Debug del path
error_log("Tank Customization API - Path: " . $path . " Method: " . $method);

// GET /weapons - Lista tutte le armi disponibili
if ($path === '/weapons' && $method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM TankWeapons ORDER BY price ASC");
    $stmt->execute();
    $weapons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'weapons' => $weapons]);

// GET /user-weapons?token=xxx - Armi possedute dall'utente
} elseif ($path === '/user-weapons' && $method === 'GET') {
    $token = $_GET['token'] ?? '';
    $user_id = verifyToken($pdo, $token);
    
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT tw.*, 
               utc.tank_id, 
               utc.slot_position,
               CASE 
                   WHEN tw.price = 0 THEN 1  -- Armi gratis sono sempre possedute
                   WHEN utc.weapon_id IS NOT NULL THEN 1  -- Armi acquistate nell'inventario
                   ELSE 0 
               END as owned
        FROM TankWeapons tw
        LEFT JOIN UserTankCustomizations utc ON tw.id = utc.weapon_id 
                                              AND utc.user_id = ? 
                                              AND utc.tank_id = 0 
                                              AND utc.slot_position = 0
        ORDER BY tw.type, tw.price
    ");
    $stmt->execute([$user_id]);
    $weapons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'weapons' => $weapons]);

// POST /customize - Personalizza tank con armi
} elseif ($path === '/customize' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';
    $tank_id = $input['tank_id'] ?? 0;
    $weapon_id = $input['weapon_id'] ?? 0;
    $slot_position = $input['slot_position'] ?? 1;
    
    $user_id = verifyToken($pdo, $token);
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    // Verifica che l'utente possieda l'arma (gratis o acquistata nell'inventario)
    $stmt = $pdo->prepare("
        SELECT tw.* FROM TankWeapons tw
        LEFT JOIN UserTankCustomizations utc ON tw.id = utc.weapon_id 
                                              AND utc.user_id = ? 
                                              AND utc.tank_id = 0 
                                              AND utc.slot_position = 0
        WHERE tw.id = ? AND (tw.price = 0 OR utc.weapon_id IS NOT NULL)
    ");
    $stmt->execute([$user_id, $weapon_id]);
    $weapon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$weapon) {
        echo json_encode(['error' => 'Weapon not owned or not found']);
        exit;
    }
    
    // Installa l'arma sul tank
    $stmt = $pdo->prepare("
        INSERT INTO UserTankCustomizations (user_id, tank_id, weapon_id, slot_position)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE weapon_id = VALUES(weapon_id)
    ");
    $stmt->execute([$user_id, $tank_id, $weapon_id, $slot_position]);
    
    echo json_encode(['success' => true, 'message' => 'Tank customized successfully']);

// POST /buy-weapon - Acquista arma
} elseif ($path === '/buy-weapon' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';
    $weapon_id = $input['weapon_id'] ?? 0;
    
    $user_id = verifyToken($pdo, $token);
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // Verifica arma e prezzo
        $stmt = $pdo->prepare("SELECT * FROM TankWeapons WHERE id = ?");
        $stmt->execute([$weapon_id]);
        $weapon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$weapon) {
            throw new Exception('Weapon not found');
        }
        
        // Verifica crediti utente
        $stmt = $pdo->prepare("SELECT credits FROM UserStats WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $credits = $stmt->fetchColumn() ?? 0;
        
        if ($credits < $weapon['price']) {
            throw new Exception('Insufficient credits');
        }
        
        // Sottrai crediti
        $stmt = $pdo->prepare("UPDATE UserStats SET credits = credits - ? WHERE user_id = ?");
        $stmt->execute([$weapon['price'], $user_id]);
        
        // Aggiungi arma all'inventario (slot 0 = inventario)
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO UserTankCustomizations (user_id, tank_id, weapon_id, slot_position)
            VALUES (?, 0, ?, 0)
        ");
        $stmt->execute([$user_id, $weapon_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Weapon purchased successfully']);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['error' => $e->getMessage()]);
    }

// GET /tank-loadout?token=xxx&tank_id=1 - Configurazione armi di un tank
} elseif ($path === '/tank-loadout' && $method === 'GET') {
    $token = $_GET['token'] ?? '';
    $tank_id = $_GET['tank_id'] ?? 1;
    
    $user_id = verifyToken($pdo, $token);
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT utc.slot_position, tw.*
        FROM UserTankCustomizations utc
        JOIN TankWeapons tw ON utc.weapon_id = tw.id
        WHERE utc.user_id = ? AND utc.tank_id = ? AND utc.slot_position > 0
        ORDER BY utc.slot_position
    ");
    $stmt->execute([$user_id, $tank_id]);
    $loadout = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'loadout' => $loadout]);

// GET /customizations - Ottieni tutte le personalizzazioni dell'utente
} elseif ($path === '/customizations' && $method === 'GET') {
    $token = $_GET['token'] ?? '';
    $user_id = verifyToken($pdo, $token);
    
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    try {
        // Carica tutte le personalizzazioni dell'utente organizzate per tank
        $stmt = $pdo->prepare("
            SELECT utc.tank_id, utc.weapon_id, utc.slot_position, tw.name as weapon_name, tw.type
            FROM UserTankCustomizations utc
            JOIN TankWeapons tw ON utc.weapon_id = tw.id
            WHERE utc.user_id = ?
            ORDER BY utc.tank_id, utc.slot_position
        ");
        $stmt->execute([$user_id]);
        $customizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organizza per tank
        $organized = [];
        foreach ($customizations as $custom) {
            $tank_id = $custom['tank_id'];
            if (!isset($organized[$tank_id])) {
                $organized[$tank_id] = ['weapons' => []];
            }
            $organized[$tank_id]['weapons'][] = [
                'weapon_id' => $custom['weapon_id'],
                'slot_position' => $custom['slot_position'],
                'name' => $custom['weapon_name'],
                'type' => $custom['type']
            ];
        }
        
        echo json_encode(['success' => true, 'customizations' => $organized]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }

// GET /enemies/types - Lista tipi di nemici
} elseif ($path === '/enemies/types' && $method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM EnemyTypes ORDER BY type, health");
    $stmt->execute();
    $enemies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'enemies' => $enemies]);

// GET /map-obstacles/types - Lista ostacoli mappa
} elseif ($path === '/map-obstacles/types' && $method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM MapObstacles ORDER BY type, name");
    $stmt->execute();
    $obstacles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'obstacles' => $obstacles]);

// GET /user-stats?token=xxx - Statistiche e crediti utente
} elseif ($path === '/user-stats' && $method === 'GET') {
    $token = $_GET['token'] ?? '';
    $user_id = verifyToken($pdo, $token);
    
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT us.*, u.username 
        FROM UserStats us 
        JOIN Users u ON us.user_id = u.id 
        WHERE us.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        echo json_encode(['success' => true, 'stats' => $stats]);
    } else {
        // Crea statistiche di default se non esistono
        $stmt = $pdo->prepare("INSERT IGNORE INTO UserStats (user_id, credits) VALUES (?, 500)");
        $stmt->execute([$user_id]);
        
        // Riprova a recuperare
        $stmt = $pdo->prepare("
            SELECT us.*, u.username 
            FROM UserStats us 
            JOIN Users u ON us.user_id = u.id 
            WHERE us.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    }

// GET /user-tanks?token=xxx - Tank posseduti dall'utente
} elseif ($path === '/user-tanks' && $method === 'GET') {
    $token = $_GET['token'] ?? '';
    $user_id = verifyToken($pdo, $token);
    
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    try {
        // Check if UserOwnedTanks table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'UserOwnedTanks'");
        $result = $stmt->fetch();
        
        if ($result) {
            // Get owned tanks from database with full details
            $stmt = $pdo->prepare("
                SELECT t.id, t.name, t.cover_path, t.price, t.description, ot.purchased_at
                FROM game_tanks t
                INNER JOIN UserOwnedTanks ot ON t.id = ot.tank_id
                WHERE ot.user_id = ?
                ORDER BY t.price ASC, t.id ASC
            ");
            $stmt->execute([$user_id]);
            $tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Fallback: assume user owns only the basic tank (id=1) if table doesn't exist
            $stmt = $pdo->prepare("SELECT id, name, cover_path, 0 as price, 'Tank base gratuito' as description, NOW() as purchased_at FROM game_tanks WHERE id = 1");
            $stmt->execute();
            $tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'tanks' => $tanks]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch owned tanks: ' . $e->getMessage()]);
    }

// GET /tanks - Lista carri armati disponibili
} elseif ($path === '/tanks' && $method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM game_tanks ORDER BY id ASC");
    $stmt->execute();
    $tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'tanks' => $tanks]);

// POST /purchase - Gestisce gli acquisti
} elseif ($path === '/purchase' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';
    $itemType = $input['type'] ?? '';
    $itemId = $input['id'] ?? 0;
    
    $user_id = verifyToken($pdo, $token);
    
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Recupera crediti utente attuali (rimuoviamo le colonne inesistenti)
        $stmt = $pdo->prepare("SELECT credits FROM UserStats WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userStats) {
            throw new Exception('Statistiche utente non trovate');
        }
        
        $price = 0;
        
        if ($itemType === 'weapon') {
            // Controlla se l'arma è già posseduta controllando se è in inventario
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM UserTankCustomizations WHERE user_id = ? AND weapon_id = ? AND tank_id = 0 AND slot_position = 0");
            $stmt->execute([$user_id, $itemId]);
            $owned = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($owned['count'] > 0) {
                throw new Exception('Arma già posseduta');
            }
            
            // Recupera il prezzo dell'arma
            $stmt = $pdo->prepare("SELECT price FROM TankWeapons WHERE id = ?");
            $stmt->execute([$itemId]);
            $weapon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$weapon) {
                throw new Exception('Arma non trovata');
            }
            
            $price = $weapon['price'];
            
            // Controlla se l'utente ha crediti sufficienti
            if ($userStats['credits'] < $price) {
                throw new Exception('Crediti insufficienti');
            }
            
            // Scala i crediti dall'utente
            $stmt = $pdo->prepare("UPDATE UserStats SET credits = credits - ? WHERE user_id = ?");
            $stmt->execute([$price, $user_id]);
            
            // Aggiunge l'arma all'inventario dell'utente (tank_id=0, slot_position=0 = inventario)
            $stmt = $pdo->prepare("INSERT INTO UserTankCustomizations (user_id, tank_id, weapon_id, slot_position) VALUES (?, 0, ?, 0)");
            $stmt->execute([$user_id, $itemId]);
            
        } elseif ($itemType === 'tank') {
            // Controlla se il tank è già posseduto
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM UserOwnedTanks WHERE user_id = ? AND tank_id = ?");
            $stmt->execute([$user_id, $itemId]);
            $owned = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($owned['count'] > 0) {
                throw new Exception('Tank già posseduto');
            }
            
            // Recupera il prezzo del tank
            $stmt = $pdo->prepare("SELECT price FROM game_tanks WHERE id = ?");
            $stmt->execute([$itemId]);
            $tank = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tank) {
                throw new Exception('Tank non trovato');
            }
            
            $price = $tank['price'];
            
            // Controlla se l'utente ha crediti sufficienti
            if ($userStats['credits'] < $price) {
                throw new Exception('Crediti insufficienti');
            }
            
            // Scala i crediti dall'utente
            $stmt = $pdo->prepare("UPDATE UserStats SET credits = credits - ? WHERE user_id = ?");
            $stmt->execute([$price, $user_id]);
            
            // Aggiunge il tank agli owned tanks dell'utente
            $stmt = $pdo->prepare("INSERT INTO UserOwnedTanks (user_id, tank_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $itemId]);
            
        } else {
            throw new Exception('Tipo oggetto non supportato');
        }
        
        $pdo->commit();
        
        // Recupera i nuovi crediti
        $stmt = $pdo->prepare("SELECT credits FROM UserStats WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $newCredits = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Acquisto completato con successo',
            'newCredits' => $newCredits,
            'price' => $price
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}
?>
