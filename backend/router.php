<?php
// Log request to CMD
error_log("Request: " . $_SERVER['REQUEST_URI']);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = strtok($requestUri, '?');
$requestUri = ltrim($requestUri, '/');

// Debug
if ($requestUri === 'debug') {
    echo json_encode([
        'message' => 'Debug working',
        'uri' => $requestUri
    ]);
    exit;
}

// Routing
if ($requestUri === 'api/auth/check-phone' || $requestUri === 'auth/check-phone') {
    require __DIR__ . '/api/auth/check-phone.php';
    exit;
}

if ($requestUri === 'api/auth/login' || $requestUri === 'auth/login') {
    require __DIR__ . '/api/auth/login.php';
    exit;
}

// Default
echo json_encode([
    'success' => true,
    'message' => 'API is running',
    'uri' => $requestUri
]);
?>