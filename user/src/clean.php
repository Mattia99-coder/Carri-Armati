<?php
header('Content-Type: application/json');

// Database configuration
$db_host = 'db-docker';
$db_name = 'tank-game';
$db_user = $_ENV['MYSQL_USER'];
$db_pass = $_ENV['MYSQL_PASSWORD'];

try {
    // Establish database connection
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

try {
    // Disable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    
    // Truncate tables (order matters due to foreign keys)
    $pdo->exec('TRUNCATE TABLE Tokens');
    $pdo->exec('TRUNCATE TABLE Users');
    
    // Re-enable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    // Return 204 No Content
    http_response_code(204);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database cleaning failed: ' . $e->getMessage()]);
}
?>