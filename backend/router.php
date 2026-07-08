<?php
// Log request to CMD
error_log("Request: " . $_SERVER['REQUEST_URI']);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = strtok($requestUri, '?');
$requestUri = ltrim($requestUri, '/');

// Debug
if ($requestUri === 'debug') {
    echo json_encode([
        'message' => 'Debug working',
        'uri' => $requestUri,
        'method' => $_SERVER['REQUEST_METHOD'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// ==================== AUTH ROUTES ====================

if ($requestUri === 'api/auth/check-phone' || $requestUri === 'auth/check-phone') {
    require __DIR__ . '/api/auth/check-phone.php';
    exit;
}

if ($requestUri === 'api/auth/login' || $requestUri === 'auth/login') {
    require __DIR__ . '/api/auth/login.php';
    exit;
}

if ($requestUri === 'api/auth/register' || $requestUri === 'auth/register') {
    require __DIR__ . '/api/auth/register.php';
    exit;
}

if ($requestUri === 'api/auth/logout' || $requestUri === 'auth/logout') {
    require __DIR__ . '/api/auth/logout.php';
    exit;
}

if ($requestUri === 'api/auth/refresh' || $requestUri === 'auth/refresh') {
    require __DIR__ . '/api/auth/refresh.php';
    exit;
}

if ($requestUri === 'api/auth/request-reset' || $requestUri === 'auth/request-reset') {
    require __DIR__ . '/api/auth/request-reset.php';
    exit;
}

if ($requestUri === 'api/auth/reset-password' || $requestUri === 'auth/reset-password') {
    require __DIR__ . '/api/auth/reset-password.php';
    exit;
}

if ($requestUri === 'api/auth/change-password' || $requestUri === 'auth/change-password') {
    require __DIR__ . '/api/auth/change-password.php';
    exit;
}

if ($requestUri === 'api/auth/phones' || $requestUri === 'auth/phones') {
    require __DIR__ . '/api/auth/phones.php';
    exit;
}

// ==================== PAYMENTS ROUTES ====================

if ($requestUri === 'api/payments/get_student_payments.php' || $requestUri === 'payments/get_student_payments.php') {
    require __DIR__ . '/api/payments/get_student_payments.php';
    exit;
}

if ($requestUri === 'api/payments/get_fee_settings.php' || $requestUri === 'payments/get_fee_settings.php') {
    require __DIR__ . '/api/payments/get_fee_settings.php';
    exit;
}

if ($requestUri === 'api/payments/make_payment.php' || $requestUri === 'payments/make_payment.php') {
    require __DIR__ . '/api/payments/make_payment.php';
    exit;
}

if ($requestUri === 'api/payments/get_payment_history.php' || $requestUri === 'payments/get_payment_history.php') {
    require __DIR__ . '/api/payments/get_payment_history.php';
    exit;
}

if ($requestUri === 'api/payments/verify_payment.php' || $requestUri === 'payments/verify_payment.php') {
    require __DIR__ . '/api/payments/verify_payment.php';
    exit;
}

// ==================== LEARNING MATERIALS ROUTES ====================

if ($requestUri === 'api/learning/get_materials.php' || $requestUri === 'learning/get_materials.php') {
    require __DIR__ . '/api/learning/get_materials.php';
    exit;
}

if ($requestUri === 'api/learning/upload_material.php' || $requestUri === 'learning/upload_material.php') {
    require __DIR__ . '/api/learning/upload_material.php';
    exit;
}

if ($requestUri === 'api/learning/download_material.php' || $requestUri === 'learning/download_material.php') {
    require __DIR__ . '/api/learning/download_material.php';
    exit;
}

if ($requestUri === 'api/learning/delete_material.php' || $requestUri === 'learning/delete_material.php') {
    require __DIR__ . '/api/learning/delete_material.php';
    exit;
}

// ==================== RESULTS ROUTES ====================

if ($requestUri === 'api/results/get_results.php' || $requestUri === 'results/get_results.php') {
    require __DIR__ . '/api/results/get_results.php';
    exit;
}

if ($requestUri === 'api/results/get_exam_results.php' || $requestUri === 'results/get_exam_results.php') {
    require __DIR__ . '/api/results/get_exam_results.php';
    exit;
}

// ==================== STUDENTS ROUTES ====================

if ($requestUri === 'api/students/get_student.php' || $requestUri === 'students/get_student.php') {
    require __DIR__ . '/api/students/get_student.php';
    exit;
}

if ($requestUri === 'api/students/get_parent_students.php' || $requestUri === 'students/get_parent_students.php') {
    require __DIR__ . '/api/students/get_parent_students.php';
    exit;
}

if ($requestUri === 'api/students/update_student.php' || $requestUri === 'students/update_student.php') {
    require __DIR__ . '/api/students/update_student.php';
    exit;
}

// ==================== NOTIFICATIONS ROUTES ====================

if ($requestUri === 'api/notifications/get_notifications.php' || $requestUri === 'notifications/get_notifications.php') {
    require __DIR__ . '/api/notifications/get_notifications.php';
    exit;
}

if ($requestUri === 'api/notifications/mark_read.php' || $requestUri === 'notifications/mark_read.php') {
    require __DIR__ . '/api/notifications/mark_read.php';
    exit;
}

// ==================== SCHOOL INFO ROUTES ====================

if ($requestUri === 'api/school/get_info.php' || $requestUri === 'school/get_info.php') {
    require __DIR__ . '/api/school/get_info.php';
    exit;
}

// ==================== DEFAULT RESPONSE ====================

// If no route matches, return API info
echo json_encode([
    'success' => true,
    'message' => 'Muyovozi Secondary School API is running',
    'version' => '1.0.0',
    'uri' => $requestUri,
    'method' => $_SERVER['REQUEST_METHOD'],
    'timestamp' => date('Y-m-d H:i:s'),
    'endpoints' => [
        'auth' => [
            'login' => 'POST /api/auth/login',
            'register' => 'POST /api/auth/register',
            'logout' => 'POST /api/auth/logout',
            'refresh' => 'POST /api/auth/refresh',
            'check-phone' => 'GET /api/auth/check-phone',
            'request-reset' => 'POST /api/auth/request-reset',
            'reset-password' => 'POST /api/auth/reset-password',
            'change-password' => 'POST /api/auth/change-password',
            'phones' => 'GET /api/auth/phones'
        ],
        'payments' => [
            'get_student_payments' => 'GET /api/payments/get_student_payments.php',
            'get_fee_settings' => 'GET /api/payments/get_fee_settings.php',
            'make_payment' => 'POST /api/payments/make_payment.php',
            'get_payment_history' => 'GET /api/payments/get_payment_history.php',
            'verify_payment' => 'POST /api/payments/verify_payment.php'
        ],
        'learning' => [
            'get_materials' => 'GET /api/learning/get_materials.php',
            'upload_material' => 'POST /api/learning/upload_material.php',
            'download_material' => 'POST /api/learning/download_material.php',
            'delete_material' => 'POST /api/learning/delete_material.php'
        ],
        'results' => [
            'get_results' => 'GET /api/results/get_results.php',
            'get_exam_results' => 'GET /api/results/get_exam_results.php'
        ],
        'students' => [
            'get_student' => 'GET /api/students/get_student.php',
            'get_parent_students' => 'GET /api/students/get_parent_students.php',
            'update_student' => 'PUT /api/students/update_student.php'
        ]
    ]
]);
?>