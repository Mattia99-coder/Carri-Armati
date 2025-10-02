<?php
// Check for biomes and terrain_types tables
$host = 'db';
$port = '3306';
$dbname = 'tank-game';
$username = 'game_user';
$password = 'secret';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 Checking biomes table:<br>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'biomes'");
    $biomesExists = $stmt->fetch();
    
    if ($biomesExists) {
        echo "✅ biomes table exists<br>";
        $stmt = $pdo->query("DESCRIBE biomes");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  - {$col['Field']}: {$col['Type']}<br>";
        }
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM biomes");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "📊 Records in biomes: {$count['count']}<br>";
        
        if ($count['count'] > 0) {
            $stmt = $pdo->query("SELECT * FROM biomes LIMIT 5");
            $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "📄 Sample biomes data:<br>";
            foreach ($samples as $sample) {
                echo "  " . json_encode($sample) . "<br>";
            }
        }
    } else {
        echo "❌ biomes table does not exist<br>";
    }
    
    echo "<br>🔍 Checking terrain_types table:<br>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'terrain_types'");
    $terrainExists = $stmt->fetch();
    
    if ($terrainExists) {
        echo "✅ terrain_types table exists<br>";
        $stmt = $pdo->query("DESCRIBE terrain_types");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  - {$col['Field']}: {$col['Type']}<br>";
        }
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM terrain_types");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "📊 Records in terrain_types: {$count['count']}<br>";
        
        if ($count['count'] > 0) {
            $stmt = $pdo->query("SELECT * FROM terrain_types LIMIT 5");
            $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "📄 Sample terrain_types data:<br>";
            foreach ($samples as $sample) {
                echo "  " . json_encode($sample) . "<br>";
            }
        }
    } else {
        echo "❌ terrain_types table does not exist<br>";
    }
    
    // Test the actual query used in API
    echo "<br>🧪 Testing API query for map ID 3:<br>";
    $stmt = $pdo->prepare("
        SELECT b.label, tt.type, tt.color, tt.texture_pattern 
        FROM biomes b 
        LEFT JOIN terrain_types tt ON b.id = tt.biome_id 
        WHERE b.game_maps_id = ?
    ");
    $stmt->execute([3]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Query results: " . count($results) . " rows<br>";
    foreach ($results as $result) {
        echo "  " . json_encode($result) . "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
