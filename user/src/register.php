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

// Validate password length
if (strlen($password) < 6) {
    http_response_code(422);
    echo json_encode(['error' => 'Password too short (minimum 6 characters)']);
    exit;
}
try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM Users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Username already exists']);
        exit;
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Create user
    $stmt = $pdo->prepare("
        INSERT INTO Users (username, password) 
        VALUES (:username, :password)
    ");
    $stmt->execute([
        ':username' => $username,
        ':password' => $passwordHash
    ]);
    
    $userId = $pdo->lastInsertId();

    // Generate token
    $token = bin2hex(random_bytes(32));
    $expiration = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store token
    $stmt = $pdo->prepare("
        INSERT INTO Tokens (token, user_id, expiration)
        VALUES (:token, :user_id, :expiration)
    ");
    $stmt->execute([
        ':token' => $token,
        ':user_id' => $userId,
        ':expiration' => $expiration
    ]);

    // Give user the default free tank (tank_id = 1, price = 0)
    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO UserOwnedTanks (user_id, tank_id)
            VALUES (:user_id, 1)
        ");
        $stmt->execute([':user_id' => $userId]);
    } catch (PDOException $e) {
        // Log the error but don't fail registration if tank assignment fails
        error_log("Failed to assign default tank to user $userId: " . $e->getMessage());
    }

    // Return success with token
    http_response_code(201);
    echo json_encode(['success' => true, 'token' => $token, 'user_id' => $userId]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>