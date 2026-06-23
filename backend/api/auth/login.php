<?php
// Enable error reporting for debugging 502 error
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$phone = $input['phone'] ?? '';

// Validate input
if (empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    exit;
}

    // Clean phone number (remove spaces)
    $phone = preg_replace('/\s+/', '', $phone);
    
    try {
        $pdo = getDBConnection();
        
        // Normalize phone number - handle both +255 and 0 prefixes
        $phoneNormalized = $phone;
        $phoneWithoutPlus = ltrim($phone, '+');
        
        // If starts with 0, replace with 255
        if (strpos($phone, '0') === 0) {
            $phoneNormalized = '+255' . substr($phone, 1);
            $phoneWithoutPlus = '255' . substr($phone, 1);
        } elseif (strpos($phone, '+255') === 0) {
            $phoneWithoutPlus = '255' . substr($phone, 4);
        }
        
        // Check if parent exists in students table - search with all variations
        $stmt = $pdo->prepare("
            SELECT 
                id,
                CONCAT(first_name, ' ', last_name) AS name,
                parent_name,
                parent_phone AS phone,
                class,
                combination,
                index_number,
                admission_number
            FROM students 
            WHERE (parent_phone = ? OR parent_phone = ? OR parent_phone = ? OR parent_phone = ?)
            AND status = 1 
            AND is_leaver = 0
            LIMIT 1
        ");
        $stmt->execute([$phone, $phoneNormalized, $phoneWithoutPlus, ltrim($phoneWithoutPlus, '255')]);
    $student = $stmt->fetch();
    
    if (!$student) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Namba ya simu haijasajiliwa. Wasiliana na shule.'
        ]);
        exit;
    }
    
    // Get all children for this parent
    $stmt = $pdo->prepare("
        SELECT 
            id,
            CONCAT(first_name, ' ', COALESCE(second_name, ''), ' ', last_name) AS name,
            class,
            combination,
            index_number,
            admission_number,
            sex,
            parent_name
        FROM students 
        WHERE (parent_phone = ? OR parent_phone = ?)
        AND status = 1 
        AND is_leaver = 0
    ");
    $stmt->execute([$phone, $phoneWithoutPlus]);
    $children = $stmt->fetchAll();
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    $refreshToken = bin2hex(random_bytes(32));
    
    // Get parent name - FIXED: Check if parent_name exists
    $parentName = $student['parent_name'] ?? 'Mzazi';
    if (empty($parentName) && !empty($children)) {
        $parentName = $children[0]['parent_name'] ?? 'Mzazi';
    }
    
    $response = [
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => 86400,
            'user' => [
                'id' => $student['id'],
                'name' => $parentName,
                'phone' => $phone,
                'email' => '',
                'role' => 'parent',
                'children' => $children
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>