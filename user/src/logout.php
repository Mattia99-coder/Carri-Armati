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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$token = $data['token'] ?? '';

// Validate input
if (empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Token is required']);
    exit;
}

try {
    // Delete the token
    $stmt = $pdo->prepare("DELETE FROM Tokens WHERE token = :token");
    $stmt->execute([':token' => $token]);
    
    // Check if any row was affected
    if ($stmt->rowCount() > 0) {
        // Success - return 204 No Content as specified in the API
        http_response_code(204);
    } else {
        // Token didn't exist (but we still consider this a success)
        http_response_code(204);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Logout failed: ' . $e->getMessage()]);
}
?>