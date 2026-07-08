<?php
/**
 * Verify/Update payment status
 * POST: /api/payments/verify_payment.php
 * Body: {
 *   "payment_id": 1,
 *   "status": "completed",
 *   "reference_number": "REF123456",
 *   "notes": "Verified payment"
 * }
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
    
    $payment_id = isset($input['payment_id']) ? intval($input['payment_id']) : 0;
    $reference_number = $input['reference_number'] ?? '';
    $notes = $input['notes'] ?? '';
    $new_status = $input['status'] ?? 'completed';
    
    if ($payment_id === 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Payment ID is required'
        ]);
        exit;
    }
    
    // Validate status
    $allowed_statuses = ['pending', 'completed', 'cancelled'];
    if (!in_array($new_status, $allowed_statuses)) {
        $new_status = 'completed';
    }
    
    // Get current payment
    $stmt = $pdo->prepare("SELECT * FROM student_payments WHERE id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        echo json_encode([
            'success' => false,
            'message' => 'Payment not found'
        ]);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update payment - UPDATED to match your table structure
    $updateQuery = "UPDATE student_payments SET ";
    $updateParams = [];
    
    if (!empty($reference_number)) {
        $updateQuery .= "reference_number = ?, ";
        $updateParams[] = $reference_number;
    }
    
    if (!empty($notes)) {
        $updateQuery .= "notes = ?, ";
        $updateParams[] = $notes;
    }
    
    $updateQuery .= "status = ? WHERE id = ?";
    $updateParams[] = $new_status;
    $updateParams[] = $payment_id;
    
    $stmt = $pdo->prepare($updateQuery);
    $stmt->execute($updateParams);
    
    // If status changed to completed, update student total_paid
    if ($new_status === 'completed' && $payment['status'] !== 'completed') {
        $stmt = $pdo->prepare("
            UPDATE students 
            SET total_paid = COALESCE(total_paid, 0) + ?
            WHERE id = ?
        ");
        $stmt->execute([$payment['amount'], $payment['student_id']]);
    }
    
    // If status changed from completed to something else, deduct from total_paid
    if ($new_status !== 'completed' && $payment['status'] === 'completed') {
        $stmt = $pdo->prepare("
            UPDATE students 
            SET total_paid = COALESCE(total_paid, 0) - ?
            WHERE id = ?
        ");
        $stmt->execute([$payment['amount'], $payment['student_id']]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Get updated payment
    $stmt = $pdo->prepare("
        SELECT 
            sp.*,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            s.class,
            s.index_number
        FROM student_payments sp
        JOIN students s ON sp.student_id = s.id
        WHERE sp.id = ?
    ");
    $stmt->execute([$payment_id]);
    $updated_payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment updated successfully',
        'data' => [
            'payment_id' => $updated_payment['id'],
            'student_id' => $updated_payment['student_id'],
            'student_name' => $updated_payment['student_name'],
            'class' => $updated_payment['class'],
            'index_number' => $updated_payment['index_number'],
            'amount' => floatval($updated_payment['amount']),
            'payment_date' => $updated_payment['payment_date'],
            'payment_method' => $updated_payment['payment_method'],
            'reference_number' => $updated_payment['reference_number'] ?? '',
            'status' => $updated_payment['status'],
            'notes' => $updated_payment['notes'] ?? '',
            'recorded_by' => $updated_payment['recorded_by'],
            'created_at' => $updated_payment['created_at'],
            'school_id' => $updated_payment['school_id']
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