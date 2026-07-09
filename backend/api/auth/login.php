<?php
/**
 * Parent Login API Endpoint
 * Handles authentication for parents using phone number
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in response
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/php-error.log'); // Set your error log path

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Include database configuration
require_once __DIR__ . '/../../config/database.php';

// Get and decode JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload: ' . json_last_error_msg()
    ]);
    exit;
}

// Get phone number
$phone = isset($input['phone']) ? trim($input['phone']) : '';

// Validate phone number
if (empty($phone)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Phone number is required'
    ]);
    exit;
}

// Clean phone number - remove all non-digit characters except '+'
$phone = preg_replace('/[^0-9+]/', '', $phone);

// Log login attempt (for debugging)
error_log("Login attempt for phone: " . $phone);

try {
    // Get database connection
    $pdo = getDBConnection();
    
    // Normalize phone number for database search
    // Try multiple formats: with +, without +, with 0, with 255
    $phoneVariations = getPhoneVariations($phone);
    
    // Find parent by phone number
    $student = findParentByPhone($pdo, $phoneVariations);
    
    if (!$student) {
        error_log("Login failed: Phone not found - " . $phone);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Namba ya simu haijasajiliwa. Wasiliana na shule.'
        ]);
        exit;
    }
    
    // Get all children for this parent
    $children = getChildrenByParentPhone($pdo, $phoneVariations);
    
    // Generate tokens
    $token = bin2hex(random_bytes(32));
    $refreshToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours
    
    // Store token in database
    storeToken($pdo, $token, $refreshToken, $phone, $expiresAt);
    
    // Get parent name
    $parentName = getParentName($student, $children);
    
    // Prepare response
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
                'children' => array_map('formatChildData', $children)
            ]
        ]
    ];
    
    echo json_encode($response);
    error_log("Login successful for phone: " . $phone);
    
} catch (PDOException $e) {
    error_log("Database error in login.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Unexpected error in login.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again.'
    ]);
}

/**
 * Generate phone number variations for search
 */
function getPhoneVariations($phone) {
    $variations = [];
    $phone = ltrim($phone, '+');
    
    // Original number
    $variations[] = $phone;
    $variations[] = '+' . $phone;
    
    // If starts with 0, try with 255
    if (strpos($phone, '0') === 0) {
        $with255 = '255' . substr($phone, 1);
        $variations[] = $with255;
        $variations[] = '+' . $with255;
    }
    
    // If starts with 255, try with 0
    if (strpos($phone, '255') === 0) {
        $with0 = '0' . substr($phone, 3);
        $variations[] = $with0;
        $variations[] = '+' . $with0;
    }
    
    // Remove duplicates
    $variations = array_unique($variations);
    
    // Log variations for debugging
    error_log("Phone variations: " . implode(', ', $variations));
    
    return $variations;
}

/**
 * Find parent by phone number
 */
function findParentByPhone($pdo, $phoneVariations) {
    // Build placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($phoneVariations), '?'));
    
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
        WHERE parent_phone IN ($placeholders)
        AND status = 1 
        AND is_leaver = 0
        LIMIT 1
    ");
    
    $stmt->execute($phoneVariations);
    return $stmt->fetch();
}

/**
 * Get all children for a parent
 */
function getChildrenByParentPhone($pdo, $phoneVariations) {
    $placeholders = implode(',', array_fill(0, count($phoneVariations), '?'));
    
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
        WHERE parent_phone IN ($placeholders)
        AND status = 1 
        AND is_leaver = 0
        ORDER BY class ASC
    ");
    
    $stmt->execute($phoneVariations);
    return $stmt->fetchAll();
}

/**
 * Get parent name from student data
 */
function getParentName($student, $children) {
    if (!empty($student['parent_name'])) {
        return $student['parent_name'];
    }
    
    if (!empty($children) && !empty($children[0]['parent_name'])) {
        return $children[0]['parent_name'];
    }
    
    return 'Mzazi';
}

/**
 * Format child data for response
 */
function formatChildData($child) {
    return [
        'id' => $child['id'],
        'name' => trim($child['name']),
        'class' => $child['class'],
        'combination' => $child['combination'],
        'index_number' => $child['index_number'],
        'admission_number' => $child['admission_number'],
        'sex' => $child['sex'],
        'parent_name' => $child['parent_name']
    ];
}

/**
 * Store authentication tokens in database
 */
function storeToken($pdo, $token, $refreshToken, $phone, $expiresAt) {
    try {
        // Create table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS api_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(128) NOT NULL UNIQUE,
                refresh_token VARCHAR(128) NOT NULL,
                phone VARCHAR(64) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME,
                INDEX idx_token (token),
                INDEX idx_phone (phone)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Delete old tokens for this phone
        $delete = $pdo->prepare("DELETE FROM api_tokens WHERE phone = ?");
        $delete->execute([$phone]);
        
        // Insert new token
        $insert = $pdo->prepare("
            INSERT INTO api_tokens (token, refresh_token, phone, expires_at) 
            VALUES (?, ?, ?, ?)
        ");
        $insert->execute([$token, $refreshToken, $phone, $expiresAt]);
        
    } catch (PDOException $e) {
        // Log error but don't stop login process
        error_log('Token storage error: ' . $e->getMessage());
    }
}