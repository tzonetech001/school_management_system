<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get the path from PATH_INFO or REQUEST_URI
if (isset($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
    $path = ltrim($path, '/');
} else {
    $requestUri = $_SERVER['REQUEST_URI'];
    $requestUri = strtok($requestUri, '?');
    $path = ltrim($requestUri, '/');
}

// Debug
if ($path === 'debug') {
    echo json_encode([
        'path_info' => $_SERVER['PATH_INFO'] ?? 'Not set',
        'request_uri' => $_SERVER['REQUEST_URI'],
        'path' => $path
    ]);
    exit;
}

// Remove api/ prefix
$path = str_replace('api/', '', $path);

// Share endpoints
if (strpos($path, 'share/') === 0) {
    $filePath = __DIR__ . '/' . $path . '.php';
    if (file_exists($filePath)) {
        require $filePath;
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Share endpoint not found',
            'path' => $path,
            'file_path' => $filePath
        ]);
    }
    exit;
}

if (empty($path)) {
    echo json_encode([
        'success' => true,
        'message' => 'Muyovozi High School API',
        'endpoints' => [
            'POST /api/auth/login' => 'Login with phone',
            'GET /api/auth/check-phone?phone=...' => 'Check if phone is registered'
        ]
    ]);
    exit;
}

$filePath = __DIR__ . '/api/' . $path . '.php';

if (file_exists($filePath)) {
    require $filePath;
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Endpoint not found',
        'path' => $path,
        'file_path' => $filePath
    ]);
}
?>