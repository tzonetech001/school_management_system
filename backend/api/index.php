<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$uri = $_SERVER['REQUEST_URI'];
$uri = strtok($uri, '?');
$uri = str_replace('/api', '', $uri);
$uri = rtrim($uri, '/');

// Simple routing
switch ($uri) {
    case '/auth/login':
        require __DIR__ . '/auth/login.php';
        break;
    case '/auth/check-phone':
        require __DIR__ . '/auth/check-phone.php';
        break;
    case '/auth/logout':
        require __DIR__ . '/auth/logout.php';
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        break;
}
?>