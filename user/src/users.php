<?php
header('Content-Type: application/json');

// Database configuration
$db_host = 'db-docker';
$db_name = 'tank-game';
$db_user = $_ENV['MYSQL_USER'];
$db_pass = $_ENV['MYSQL_PASSWORD'];
$db_port = $_ENV['MYSQL_PORT'] ?? 3306;

try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Optional: Add authentication (e.g., check a token)
// $authToken = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
// if (!$this->isValidToken($authToken)) {  // Implement token validation
//     http_response_code(401);
//     echo json_encode(['error' => 'Unauthorized']);
//     exit;
// }

try {
    // Fetch only non-sensitive data (exclude passwords!)
    $stmt = $pdo->query("SELECT id, name FROM Users");  // Removed 'password'
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Optional: Pagination
    // $limit = min(100, $_GET['limit'] ?? 20);  // Default 20, max 100
    // $users = array_slice($users, 0, $limit);

    http_response_code(200);
    echo json_encode(['data' => $users]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>