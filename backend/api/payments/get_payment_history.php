<?php
/**
 * Get payment history for a student
 * GET: /api/payments/get_payment_history.php?student_id=1&school_id=1
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
    
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    $school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : 'all'; // all, completed, pending, cancelled
    
    if ($student_id === 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Student ID is required'
        ]);
        exit;
    }
    
    // Build query - UPDATED to match your table structure
    $query = "
        SELECT 
            sp.*,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            s.index_number,
            s.class,
            s.admission_number,
            CONCAT(rec.first_name, ' ', rec.last_name) as recorded_by_name
        FROM student_payments sp
        JOIN students s ON sp.student_id = s.id
        LEFT JOIN admins rec ON sp.recorded_by = rec.id
        WHERE sp.student_id = ?
    ";
    
    $params = [$student_id];
    
    if ($school_id > 0) {
        $query .= " AND sp.school_id = ?";
        $params[] = $school_id;
    }
    
    if ($status !== 'all') {
        $query .= " AND sp.status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY sp.payment_date DESC, sp.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM student_payments sp
        WHERE sp.student_id = ?
    ";
    $countParams = [$student_id];
    
    if ($school_id > 0) {
        $countQuery .= " AND sp.school_id = ?";
        $countParams[] = $school_id;
    }
    
    if ($status !== 'all') {
        $countQuery .= " AND sp.status = ?";
        $countParams[] = $status;
    }
    
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($countParams);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
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
            'recorded_by_name' => $payment['recorded_by_name'] ?? '',
            'created_at' => $payment['created_at'],
            'school_id' => $payment['school_id']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'payments' => $formatted_payments,
            'pagination' => [
                'total' => intval($total),
                'limit' => $limit,
                'offset' => $offset,
                'pages' => ceil($total / $limit)
            ]
        ]
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>