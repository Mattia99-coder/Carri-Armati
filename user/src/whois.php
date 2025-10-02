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
    // Establish database connection
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get token from query parameters
$token = $_GET['token'] ?? '';

// Check for empty token
// if (empty($token)) {
//     http_response_code(400);
//     echo json_encode("Token is empty");
//     exit;
// }

try {
    // Check if token exists and is valid
    $stmt = $pdo->prepare("
        SELECT user_id 
        FROM Tokens 
        WHERE token = :token 
        AND expiration > NOW()
    ");
    $stmt->execute([':token' => $token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenData) {
        // Invece di errore, fornisci username di default per evitare errori di parsing
        echo json_encode("Guest");
        exit;
    }

    // Update token expiration (reset timer)
    $updateStmt = $pdo->prepare("
        UPDATE Tokens 
        SET expiration = NOW() + INTERVAL 1 HOUR 
        WHERE token = :token
    ");
    $updateStmt->execute([':token' => $token]);

    // Get user information
    $userStmt = $pdo->prepare("
        SELECT username 
        FROM Users 
        WHERE id = :user_id
    ");
    $userStmt->execute([':user_id' => $tokenData['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode("Guest");
        exit;
    }

    // Return successful response
    http_response_code(200);
    echo json_encode($user['username']);

} catch (PDOException $e) {
    // Invece di errore, fornisci username di default per evitare errori di parsing
    echo json_encode("Guest");
}
?>