<?php
// Debug file per testare se PHP funziona
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Log della richiesta
$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');
$post_data = $_POST;

$debug_info = [
    'method' => $method,
    'raw_input' => $input,
    'post_data' => $post_data,
    'parsed_json' => json_decode($input, true),
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>
