<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration
$db_host = getenv('DATABASE_HOST') ?: 'db';
$db_name = 'tank-game';
$db_user = getenv('DATABASE_USER') ?: 'game_user';
$db_pass = getenv('DATABASE_PASSWORD') ?: 'secret';

// Establish database connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get form data
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Validate input
$requiredFields = ['name', 'password'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Sanitize input and assign variables
$username = trim($data['name']);
$password = $data['password'];

try {
    // Find user in database
    $stmt = $pdo->prepare("SELECT id, password FROM Users WHERE username = :name");
    $stmt->execute([':name' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify username and password
    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password']);
        exit;
    }

    // Generate new token (32 characters)
    $token = bin2hex(random_bytes(16));
    $expiration = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Delete any existing tokens for this user
    $deleteStmt = $pdo->prepare("DELETE FROM Tokens WHERE user_id = :user_id");
    $deleteStmt->execute([':user_id' => $user['id']]);

    // Insert new token
    $insertStmt = $pdo->prepare("
        INSERT INTO Tokens (token, user_id, expiration) 
        VALUES (:token, :user_id, :expiration)
    ");
    $insertStmt->execute([
        ':token' => $token,
        ':user_id' => $user['id'],
        ':expiration' => $expiration
    ]);

    // Return success with token
    http_response_code(200);
    echo json_encode(['success' => true, 'token' => $token]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

?>