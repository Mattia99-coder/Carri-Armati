<?php
// Test database connection
$host = 'db';  // Nome del container nel docker-compose
$port = '3306';
$dbname = 'tank-game';
$username = 'game_user';
$password = 'secret';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connected successfully!<br>";
    
    // Test query
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Tables found: " . implode(", ", $tables) . "<br>";
    
    // Test game_maps table
    if (in_array('game_maps', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM game_maps");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "🗺️  Maps in database: " . $result['count'] . "<br>";
        
        // Show first few maps
        $stmt = $pdo->query("SELECT id, name, cover_path FROM game_maps LIMIT 3");
        $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "📍 Sample maps:<br>";
        foreach ($maps as $map) {
            echo "  - ID {$map['id']}: {$map['name']} ({$map['cover_path']})<br>";
        }
    }
    
    // Test game_tanks table
    if (in_array('game_tanks', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM game_tanks");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "🚗 Tanks in database: " . $result['count'] . "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage();
}
?>
