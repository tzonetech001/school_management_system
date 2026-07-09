<?php
/**
 * Make a payment
 * POST: /api/payments/make_payment.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST'
    ]);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON: ' . json_last_error_msg()
        ]);
        exit;
    }
    
    // Validate required fields
    $required = ['student_id', 'school_id', 'amount'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Missing required field: $field"
            ]);
            exit;
        }
    }
    
    $student_id = intval($input['student_id']);
    $school_id = intval($input['school_id']);
    $amount = floatval($input['amount']);
    $payment_method = $input['payment_method'] ?? 'mobile_money';
    $reference_number = $input['reference_number'] ?? '';
    $notes = $input['notes'] ?? 'Payment via M-Pesa';
    $recorded_by = isset($input['recorded_by']) ? intval($input['recorded_by']) : null;
    $payment_date = $input['payment_date'] ?? date('Y-m-d');
    $academic_year = $input['academic_year'] ?? date('Y');
    
    // Validate payment method
    $allowed_methods = ['cash', 'bank_transfer', 'mobile_money'];
    if (!in_array($payment_method, $allowed_methods)) {
        $payment_method = 'mobile_money';
    }
    
    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Amount must be greater than 0'
        ]);
        exit;
    }
    
    // Generate reference number if not provided
    if (empty($reference_number)) {
        $reference_number = 'REF' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert payment
    $stmt = $pdo->prepare("
        INSERT INTO student_payments (
            student_id,
            school_id,
            amount,
            payment_date,
            payment_method,
            reference_number,
            status,
            notes,
            recorded_by,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, ?, NOW())
    ");
    
    $stmt->execute([
        $student_id,
        $school_id,
        $amount,
        $payment_date,
        $payment_method,
        $reference_number,
        $notes,
        $recorded_by
    ]);
    
    $payment_id = $pdo->lastInsertId();
    
    // Update student's total paid
    $stmt = $pdo->prepare("
        UPDATE students 
        SET total_paid = COALESCE(total_paid, 0) + ?
        WHERE id = ?
    ");
    $stmt->execute([$amount, $student_id]);
    
    // Commit transaction
    $pdo->commit();
    
    // Get student details
    $stmt = $pdo->prepare("
        SELECT CONCAT(first_name, ' ', last_name) as student_name, class, index_number
        FROM students 
        WHERE id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment recorded successfully',
        'data' => [
            'payment_id' => $payment_id,
            'student_id' => $student_id,
            'student_name' => $student['student_name'] ?? '',
            'class' => $student['class'] ?? '',
            'index_number' => $student['index_number'] ?? '',
            'amount' => $amount,
            'payment_date' => $payment_date,
            'payment_method' => $payment_method,
            'reference_number' => $reference_number,
            'status' => 'completed',
            'notes' => $notes,
            'recorded_by' => $recorded_by,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch(PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>