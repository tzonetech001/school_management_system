<?php
/**
 * Get fee settings for a school
 * GET: /api/payments/get_fee_settings.php?school_id=1&academic_year=2026
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = getDBConnection();
    
    $school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
    $academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : date('Y');
    
    if ($school_id === 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'School ID is required'
        ]);
        exit;
    }
    
    // Get fee settings
    $stmt = $pdo->prepare("
        SELECT 
            id,
            school_id,
            academic_year,
            total_fee,
            term_fee,
            registration_fee,
            exam_fee,
            other_fees,
            description,
            created_at,
            updated_at
        FROM fee_settings 
        WHERE school_id = ? AND academic_year = ?
        LIMIT 1
    ");
    $stmt->execute([$school_id, $academic_year]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        // Try to get the latest settings
        $stmt = $pdo->prepare("
            SELECT 
                id,
                school_id,
                academic_year,
                total_fee,
                term_fee,
                registration_fee,
                exam_fee,
                other_fees,
                description,
                created_at,
                updated_at
            FROM fee_settings 
            WHERE school_id = ?
            ORDER BY updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$school_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($settings) {
        $settings['total_fee'] = floatval($settings['total_fee']);
        $settings['term_fee'] = floatval($settings['term_fee'] ?? 0);
        $settings['registration_fee'] = floatval($settings['registration_fee'] ?? 0);
        $settings['exam_fee'] = floatval($settings['exam_fee'] ?? 0);
        $settings['other_fees'] = floatval($settings['other_fees'] ?? 0);
        
        echo json_encode([
            'success' => true,
            'data' => $settings
        ]);
    } else {
        // Return default settings if none found
        echo json_encode([
            'success' => true,
            'data' => [
                'school_id' => $school_id,
                'academic_year' => $academic_year,
                'total_fee' => 0,
                'term_fee' => 0,
                'registration_fee' => 0,
                'exam_fee' => 0,
                'other_fees' => 0,
                'description' => 'No fee settings configured for this academic year'
            ],
            'message' => 'No fee settings found, using defaults'
        ]);
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>