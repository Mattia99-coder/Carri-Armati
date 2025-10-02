<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

// Function to verify token
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

// Function to validate map data structure
function validateMapData($map_data) {
    if (!is_array($map_data)) return false;
    
    $required_fields = ['width', 'height', 'biome', 'terrain_type', 'tiles', 'enemies', 'obstacles'];
    foreach ($required_fields as $field) {
        if (!isset($map_data[$field])) return false;
    }
    
    // Validate dimensions
    $width = (int)$map_data['width'];
    $height = (int)$map_data['height'];
    if ($width < 10 || $width > 100 || $height < 10 || $height > 100) return false;
    
    // Validate biome
    $valid_biomes = ['forest', 'desert', 'arctic', 'grassland', 'mountain', 'swamp', 'urban', 'beach'];
    if (!in_array($map_data['biome'], $valid_biomes)) return false;
    
    // Validate terrain types
    $valid_terrains = ['flat', 'hilly', 'mixed'];
    if (!in_array($map_data['terrain_type'], $valid_terrains)) return false;
    
    // Validate tiles array
    if (!is_array($map_data['tiles']) || count($map_data['tiles']) !== $height) return false;
    foreach ($map_data['tiles'] as $row) {
        if (!is_array($row) || count($row) !== $width) return false;
    }
    
    return true;
}

// Function to increment play count
function incrementPlayCount($pdo, $map_id) {
    $stmt = $pdo->prepare("
        INSERT INTO MapPlayStats (map_id, play_count, last_played) 
        VALUES (?, 1, NOW()) 
        ON DUPLICATE KEY UPDATE 
        play_count = play_count + 1, 
        last_played = NOW()
    ");
    $stmt->execute([$map_id]);
}

// POST /usermaps/create - Create new custom map
if (strpos($path, '/usermaps/create') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $token = $data['token'] ?? '';
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $map_data = $data['map_data'] ?? [];
    $is_public = (bool)($data['is_public'] ?? false);
    
    if (!$name || empty($map_data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and map_data required']);
        exit;
    }
    
    $user = getUserFromToken($pdo, $token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    if (!validateMapData($map_data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid map data format']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO UserCreatedMaps (
                user_id, name, description, width, height, biome, terrain_type, 
                map_data, is_public
            ) VALUES (
                :user_id, :name, :description, :width, :height, :biome, :terrain_type, 
                :map_data, :is_public
            )
        ");
        
        $stmt->execute([
            ':user_id' => $user['user_id'],
            ':name' => $name,
            ':description' => $description,
            ':width' => $map_data['width'],
            ':height' => $map_data['height'],
            ':biome' => $map_data['biome'],
            ':terrain_type' => $map_data['terrain_type'],
            ':map_data' => json_encode($map_data),
            ':is_public' => $is_public
        ]);
        
        $map_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'map_id' => $map_id,
            'message' => 'Map created successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create map: ' . $e->getMessage()]);
    }

// PUT /usermaps/update - Update existing map
} elseif (strpos($path, '/usermaps/update') !== false && $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $token = $data['token'] ?? '';
    $map_id = (int)($data['map_id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $map_data = $data['map_data'] ?? [];
    $is_public = (bool)($data['is_public'] ?? false);
    
    $user = getUserFromToken($pdo, $token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    if (!$map_id || !$name || empty($map_data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Map ID, name and map_data required']);
        exit;
    }
    
    if (!validateMapData($map_data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid map data format']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE UserCreatedMaps SET 
                name = :name, description = :description, width = :width, height = :height,
                biome = :biome, terrain_type = :terrain_type, map_data = :map_data, 
                is_public = :is_public, updated_at = NOW()
            WHERE id = :map_id AND user_id = :user_id
        ");
        
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':width' => $map_data['width'],
            ':height' => $map_data['height'],
            ':biome' => $map_data['biome'],
            ':terrain_type' => $map_data['terrain_type'],
            ':map_data' => json_encode($map_data),
            ':is_public' => $is_public,
            ':map_id' => $map_id,
            ':user_id' => $user['user_id']
        ]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Map not found or access denied']);
            exit;
        }
        
        echo json_encode(['success' => true, 'message' => 'Map updated successfully']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update map: ' . $e->getMessage()]);
    }

// GET /usermaps/list - List maps with filtering options
} elseif (strpos($path, '/usermaps/list') !== false) {
    $token = $_GET['token'] ?? '';
    $public_only = isset($_GET['public']) && $_GET['public'] === 'true';
    $biome = $_GET['biome'] ?? '';
    $terrain_type = $_GET['terrain_type'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = max((int)($_GET['offset'] ?? 0), 0);
    $sort_by = $_GET['sort'] ?? 'created_at';
    $order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    
    // Valid sort columns
    $valid_sorts = ['created_at', 'updated_at', 'name', 'avg_rating', 'play_count'];
    if (!in_array($sort_by, $valid_sorts)) {
        $sort_by = 'created_at';
    }
    
    $where_conditions = [];
    $params = [];
    
    if ($public_only) {
        $where_conditions[] = "ucm.is_public = TRUE";
    } else {
        $user = getUserFromToken($pdo, $token);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }
        $where_conditions[] = "ucm.user_id = :user_id";
        $params[':user_id'] = $user['user_id'];
    }
    
    if ($biome) {
        $where_conditions[] = "ucm.biome = :biome";
        $params[':biome'] = $biome;
    }
    
    if ($terrain_type) {
        $where_conditions[] = "ucm.terrain_type = :terrain_type";
        $params[':terrain_type'] = $terrain_type;
    }
    
    $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
    
    $sql = "
        SELECT 
            ucm.id, ucm.name, ucm.description, ucm.width, ucm.height, 
            ucm.biome, ucm.terrain_type, ucm.is_public, ucm.created_at, ucm.updated_at,
            u.username as created_by,
            COALESCE(AVG(mr.rating), 0) as avg_rating,
            COUNT(DISTINCT mr.id) as rating_count,
            COALESCE(mps.play_count, 0) as play_count
        FROM UserCreatedMaps ucm
        JOIN Users u ON ucm.user_id = u.id
        LEFT JOIN MapRatings mr ON ucm.id = mr.map_id
        LEFT JOIN MapPlayStats mps ON ucm.id = mps.map_id
        $where_clause
        GROUP BY ucm.id
        ORDER BY $sort_by $order
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;
    $stmt->execute($params);
    
    $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert numeric strings to proper numbers
    foreach ($maps as &$map) {
        $map['avg_rating'] = (float)$map['avg_rating'];
        $map['rating_count'] = (int)$map['rating_count'];
        $map['play_count'] = (int)$map['play_count'];
    }
    
    echo json_encode($maps);

// GET /usermaps/load - Load specific map data
} elseif (strpos($path, '/usermaps/load') !== false) {
    $map_id = (int)($_GET['id'] ?? 0);
    $token = $_GET['token'] ?? '';
    $increment_play = isset($_GET['play']) && $_GET['play'] === 'true';
    
    if (!$map_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Map ID required']);
        exit;
    }
    
    // Determine user access level
    $user_id = 0;
    if ($token) {
        $user = getUserFromToken($pdo, $token);
        if ($user) {
            $user_id = $user['user_id'];
        }
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            ucm.*, u.username as created_by,
            COALESCE(AVG(mr.rating), 0) as avg_rating,
            COUNT(DISTINCT mr.id) as rating_count,
            COALESCE(mps.play_count, 0) as play_count,
            umf.id IS NOT NULL as is_favorite
        FROM UserCreatedMaps ucm 
        JOIN Users u ON ucm.user_id = u.id 
        LEFT JOIN MapRatings mr ON ucm.id = mr.map_id
        LEFT JOIN MapPlayStats mps ON ucm.id = mps.map_id
        LEFT JOIN UserMapFavorites umf ON (ucm.id = umf.map_id AND umf.user_id = :user_id)
        WHERE ucm.id = :id AND (ucm.is_public = TRUE OR ucm.user_id = :user_id)
        GROUP BY ucm.id
    ");
    
    $stmt->execute([':id' => $map_id, ':user_id' => $user_id]);
    $map = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$map) {
        http_response_code(404);
        echo json_encode(['error' => 'Map not found or access denied']);
        exit;
    }
    
    // Decode map data
    $map['map_data'] = json_decode($map['map_data'], true);
    $map['avg_rating'] = (float)$map['avg_rating'];
    $map['rating_count'] = (int)$map['rating_count'];
    $map['play_count'] = (int)$map['play_count'];
    $map['is_favorite'] = (bool)$map['is_favorite'];
    
    // Increment play count if requested
    if ($increment_play && $map['is_public']) {
        incrementPlayCount($pdo, $map_id);
        $map['play_count']++;
    }
    
    echo json_encode($map);

// POST /usermaps/rate - Rate a map
} elseif (strpos($path, '/usermaps/rate') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $token = $data['token'] ?? '';
    $map_id = (int)($data['map_id'] ?? 0);
    $rating = (int)($data['rating'] ?? 0);
    
    if (!$map_id || $rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid map_id and rating (1-5) required']);
        exit;
    }
    
    $user = getUserFromToken($pdo, $token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    try {
        // Check if map exists and is public
        $stmt = $pdo->prepare("SELECT id FROM UserCreatedMaps WHERE id = ? AND is_public = TRUE");
        $stmt->execute([$map_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Map not found or not public']);
            exit;
        }
        
        // Insert or update rating
        $stmt = $pdo->prepare("
            INSERT INTO MapRatings (map_id, user_id, rating) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = NOW()
        ");
        $stmt->execute([$map_id, $user['user_id'], $rating]);
        
        echo json_encode(['success' => true, 'message' => 'Rating saved successfully']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save rating']);
    }

// POST /usermaps/favorite - Toggle favorite status
} elseif (strpos($path, '/usermaps/favorite') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $token = $data['token'] ?? '';
    $map_id = (int)($data['map_id'] ?? 0);
    
    if (!$map_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Map ID required']);
        exit;
    }
    
    $user = getUserFromToken($pdo, $token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    try {
        // Check if map exists and is public
        $stmt = $pdo->prepare("SELECT id FROM UserCreatedMaps WHERE id = ? AND is_public = TRUE");
        $stmt->execute([$map_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Map not found or not public']);
            exit;
        }
        
        // Check if already favorited
        $stmt = $pdo->prepare("SELECT id FROM UserMapFavorites WHERE map_id = ? AND user_id = ?");
        $stmt->execute([$map_id, $user['user_id']]);
        $is_favorite = $stmt->fetch() !== false;
        
        if ($is_favorite) {
            // Remove from favorites
            $stmt = $pdo->prepare("DELETE FROM UserMapFavorites WHERE map_id = ? AND user_id = ?");
            $stmt->execute([$map_id, $user['user_id']]);
            echo json_encode(['success' => true, 'is_favorite' => false, 'message' => 'Removed from favorites']);
        } else {
            // Add to favorites
            $stmt = $pdo->prepare("INSERT INTO UserMapFavorites (map_id, user_id) VALUES (?, ?)");
            $stmt->execute([$map_id, $user['user_id']]);
            echo json_encode(['success' => true, 'is_favorite' => true, 'message' => 'Added to favorites']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update favorite status']);
    }

// GET /usermaps/favorites - Get user's favorite maps
} elseif (strpos($path, '/usermaps/favorites') !== false) {
    $token = $_GET['token'] ?? '';
    
    $user = getUserFromToken($pdo, $token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            ucm.id, ucm.name, ucm.description, ucm.width, ucm.height, 
            ucm.biome, ucm.terrain_type, ucm.created_at,
            u.username as created_by,
            COALESCE(AVG(mr.rating), 0) as avg_rating,
            COUNT(DISTINCT mr.id) as rating_count,
            COALESCE(mps.play_count, 0) as play_count,
            umf.created_at as favorited_at
        FROM UserMapFavorites umf
        JOIN UserCreatedMaps ucm ON umf.map_id = ucm.id
        JOIN Users u ON ucm.user_id = u.id
        LEFT JOIN MapRatings mr ON ucm.id = mr.map_id
        LEFT JOIN MapPlayStats mps ON ucm.id = mps.map_id
        WHERE umf.user_id = ? AND ucm.is_public = TRUE
        GROUP BY ucm.id
        ORDER BY umf.created_at DESC
    ");
    
    $stmt->execute([$user['user_id']]);
    $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($maps as &$map) {
        $map['avg_rating'] = (float)$map['avg_rating'];
        $map['rating_count'] = (int)$map['rating_count'];
        $map['play_count'] = (int)$map['play_count'];
    }
    
    echo json_encode($maps);

// GET /usermaps/stats - Get map statistics
} elseif (strpos($path, '/usermaps/stats') !== false) {
    $map_id = (int)($_GET['map_id'] ?? 0);
    $token = $_GET['token'] ?? '';
    
    if (!$map_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Map ID required']);
        exit;
    }
    
    // Verify access to map
    $user_id = 0;
    if ($token) {
        $user = getUserFromToken($pdo, $token);
        if ($user) {
            $user_id = $user['user_id'];
        }
    }
    
    $stmt = $pdo->prepare("
        SELECT id FROM UserCreatedMaps 
        WHERE id = ? AND (is_public = TRUE OR user_id = ?)
    ");
    $stmt->execute([$map_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Map not found or access denied']);
        exit;
    }
    
    // Get comprehensive statistics
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(mps.play_count, 0) as play_count,
            mps.last_played,
            COALESCE(AVG(mr.rating), 0) as avg_rating,
            COUNT(DISTINCT mr.user_id) as total_ratings,
            COUNT(DISTINCT umf.user_id) as total_favorites,
            SUM(CASE WHEN mr.rating = 5 THEN 1 ELSE 0 END) as five_star_count,
            SUM(CASE WHEN mr.rating = 4 THEN 1 ELSE 0 END) as four_star_count,
            SUM(CASE WHEN mr.rating = 3 THEN 1 ELSE 0 END) as three_star_count,
            SUM(CASE WHEN mr.rating = 2 THEN 1 ELSE 0 END) as two_star_count,
            SUM(CASE WHEN mr.rating = 1 THEN 1 ELSE 0 END) as one_star_count
        FROM UserCreatedMaps ucm
        LEFT JOIN MapPlayStats mps ON ucm.id = mps.map_id
        LEFT JOIN MapRatings mr ON ucm.id = mr.map_id
        LEFT JOIN UserMapFavorites umf ON ucm.id = umf.map_id
        WHERE ucm.id = ?
        GROUP BY ucm.id
    ");
    
    $stmt->execute([$map_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        $stats['avg_rating'] = (float)$stats['avg_rating'];
        $stats['play_count'] = (int)$stats['play_count'];
        $stats['total_ratings'] = (int)$stats['total_ratings'];
        $stats['total_favorites'] = (int)$stats['total_favorites'];
        $stats['five_star_count'] = (int)$stats['five_star_count'];
        $stats['four_star_count'] = (int)$stats['four_star_count'];
        $stats['three_star_count'] = (int)$stats['three_star_count'];
        $stats['two_star_count'] = (int)$stats['two_star_count'];
        $stats['one_star_count'] = (int)$stats['one_star_count'];
    }
    
    echo json_encode($stats ?: []);

// DELETE /usermaps/delete - Delete map
} elseif (strpos($path, '/usermaps/delete') !== false && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $token = $data['token'] ?? '';
    $map_id = (int)($data['map_id'] ?? 0);
    
    $user = getUserFromToken($pdo, $token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Delete related data first (foreign key constraints)
        $pdo->prepare("DELETE FROM MapRatings WHERE map_id = ?")->execute([$map_id]);
        $pdo->prepare("DELETE FROM MapPlayStats WHERE map_id = ?")->execute([$map_id]);
        $pdo->prepare("DELETE FROM UserMapFavorites WHERE map_id = ?")->execute([$map_id]);
        
        // Delete the map
        $stmt = $pdo->prepare("DELETE FROM UserCreatedMaps WHERE id = ? AND user_id = ?");
        $stmt->execute([$map_id, $user['user_id']]);
        
        if ($stmt->rowCount() === 0) {
            $pdo->rollback();
            http_response_code(404);
            echo json_encode(['error' => 'Map not found or access denied']);
            exit;
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Map deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete map']);
    }

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}
?>
