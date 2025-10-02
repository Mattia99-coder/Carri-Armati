<?php
// Check users in database
$host = 'db';
$port = '3306';
$dbname = 'tank-game';
$username = 'game_user';
$password = 'secret';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 Checking Users table:<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM Users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📊 Total users: {$count['count']}<br><br>";
    
    if ($count['count'] > 0) {
        echo "📄 All users in database:<br>";
        $stmt = $pdo->query("SELECT id, username, password FROM Users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            echo "  - ID: {$user['id']}, Username: '{$user['username']}', Password Hash: " . substr($user['password'], 0, 20) . "...<br>";
        }
        
        echo "<br>🔍 Looking specifically for 'mattia':<br>";
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = ?");
        $stmt->execute(['mattia']);
        $mattia = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mattia) {
            echo "✅ Found 'mattia': " . json_encode($mattia) . "<br>";
        } else {
            echo "❌ User 'mattia' not found<br>";
        }
        
        // Test password verification for mattia if exists
        if ($mattia) {
            echo "<br>🧪 Testing password verification:<br>";
            $testPasswords = ['mattia', 'password', '123456', 'test'];
            foreach ($testPasswords as $testPwd) {
                $result = password_verify($testPwd, $mattia['password']);
                echo "  - Password '$testPwd': " . ($result ? "✅ MATCH" : "❌ No match") . "<br>";
            }
        }
    } else {
        echo "❌ No users found in database<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
