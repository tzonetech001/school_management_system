<?php
// Enable error reporting for debugging 502 error
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../config/database.php';

$phone = $_GET['phone'] ?? '';

if (empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    exit;
}

// Clean phone number
$phone = preg_replace('/\s+/', '', $phone);
$phoneWithoutPlus = ltrim($phone, '+');

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM students 
        WHERE (parent_phone = ? OR parent_phone = ?)
        AND status = 1 
        AND is_leaver = 0
    ");
    $stmt->execute([$phone, $phoneWithoutPlus]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'registered' => ($result['count'] > 0)
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
?>