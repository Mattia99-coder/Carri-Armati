<?php
// Check actual table structure
$host = 'db';
$port = '3306';
$dbname = 'tank-game';
$username = 'game_user';
$password = 'secret';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 Actual structure of game_maps table:<br>";
    $stmt = $pdo->query("DESCRIBE game_maps");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "  - {$col['Field']}: {$col['Type']}<br>";
    }
    
    echo "<br>📄 Sample data from game_maps:<br>";
    $stmt = $pdo->query("SELECT * FROM game_maps LIMIT 2");
    $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($maps as $map) {
        echo "Map ID {$map['id']}: " . json_encode($map) . "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
