<?php
/**
 * Get student payments and fee summary
 * GET: /api/payments/get_student_payments.php?school_id=1&student_id=1&academic_year=2026
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
    
    // Get parameters
    $school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    $academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : date('Y');
    
    if ($school_id === 0 || $student_id === 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'School ID and Student ID are required'
        ]);
        exit;
    }
    
    // Get fee summary
    $stmt = $pdo->prepare("
        SELECT 
            fs.total_fee,
            COALESCE(SUM(sp.amount), 0) as total_paid,
            fs.academic_year,
            fs.school_id
        FROM fee_settings fs
        LEFT JOIN student_payments sp ON fs.school_id = sp.school_id 
            AND fs.academic_year = sp.academic_year 
            AND sp.student_id = ?
            AND sp.status = 'completed'
        WHERE fs.school_id = ? AND fs.academic_year = ?
        GROUP BY fs.id
    ");
    $stmt->execute([$student_id, $school_id, $academic_year]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no fee settings found, create default
    if (!$summary) {
        $summary = [
            'total_fee' => 0,
            'total_paid' => 0,
            'academic_year' => $academic_year,
            'school_id' => $school_id
        ];
    }
    
    $total_fee = floatval($summary['total_fee']);
    $total_paid = floatval($summary['total_paid']);
    $balance = $total_fee - $total_paid;
    
    // Get payment history - UPDATED to match your table structure
    $stmt = $pdo->prepare("
        SELECT 
            sp.*,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            s.index_number,
            s.class,
            s.admission_number
        FROM student_payments sp
        JOIN students s ON sp.student_id = s.id
        WHERE sp.student_id = ? 
            AND sp.school_id = ?
            AND sp.status = 'completed'
        ORDER BY sp.payment_date DESC, sp.created_at DESC
    ");
    $stmt->execute([$student_id, $school_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format payments
    $formatted_payments = [];
    foreach ($payments as $payment) {
        $formatted_payments[] = [
            'id' => $payment['id'],
            'student_id' => $payment['student_id'],
            'student_name' => $payment['student_name'],
            'index_number' => $payment['index_number'],
            'class' => $payment['class'],
            'admission_number' => $payment['admission_number'],
            'amount' => floatval($payment['amount']),
            'payment_date' => $payment['payment_date'],
            'payment_method' => $payment['payment_method'],
            'reference_number' => $payment['reference_number'] ?? '',
            'status' => $payment['status'],
            'notes' => $payment['notes'] ?? '',
            'recorded_by' => $payment['recorded_by'],
            'created_at' => $payment['created_at'],
            'school_id' => $payment['school_id']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'summary' => [
                'total_fee' => $total_fee,
                'total_paid' => $total_paid,
                'balance' => $balance,
                'academic_year' => $academic_year,
                'school_id' => $school_id,
                'percentage_paid' => $total_fee > 0 ? round(($total_paid / $total_fee) * 100, 2) : 0
            ],
            'payments' => $formatted_payments,
            'count' => count($formatted_payments)
        ]
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>