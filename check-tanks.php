<?php
$host = 'db';
$port = '3306';
$dbname = 'tank-game';
$username = 'game_user';
$password = 'secret';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if game_tanks table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'game_tanks'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "✅ game_tanks table exists<br>";
        $stmt = $pdo->query("DESCRIBE game_tanks");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Structure:<br>";
        foreach ($columns as $col) {
            echo "  - {$col['Field']}: {$col['Type']}<br>";
        }
        
        $stmt = $pdo->query("SELECT * FROM game_tanks LIMIT 3");
        $tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<br>Sample data:<br>";
        foreach ($tanks as $tank) {
            echo json_encode($tank) . "<br>";
        }
    } else {
        echo "❌ game_tanks table does not exist<br>";
        
        echo "Available tables:<br>";
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            echo "  - $table<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
