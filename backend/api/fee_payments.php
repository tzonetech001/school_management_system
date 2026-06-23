<?php
// API endpoint to get student fee payments
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = getDBConnection();
    
    // Get parameters
    $school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    $academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';
    
    if (empty($school_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'School ID is required']);
        exit;
    }
    
    // Get fee settings for the academic year
    $fee_settings = null;
    if (!empty($academic_year)) {
        $stmt = $pdo->prepare("SELECT * FROM fee_settings WHERE school_id = ? AND academic_year = ?");
        $stmt->execute([$school_id, $academic_year]);
        $fee_settings = $stmt->fetch();
    }
    
    // If no academic year specified or not found, get the latest
    if (!$fee_settings) {
        $stmt = $pdo->prepare("SELECT * FROM fee_settings WHERE school_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$school_id]);
        $fee_settings = $stmt->fetch();
    }
    
    $total_fee = $fee_settings ? floatval($fee_settings['total_fee']) : 0;
    $academic_year = $fee_settings ? $fee_settings['academic_year'] : '';
    
    // Build query for students
    $query = "SELECT 
                s.id,
                s.index_number,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.class,
                COALESCE(SUM(p.amount), 0) as total_paid
              FROM students s
              LEFT JOIN student_payments p ON s.id = p.student_id AND p.status = 'completed'
              WHERE s.school_id = ? AND s.status = 1 AND s.is_leaver = 0";
    
    $params = [$school_id];
    
    if ($student_id > 0) {
        $query .= " AND s.id = ?";
        $params[] = $student_id;
    }
    
    $query .= " GROUP BY s.id ORDER BY s.last_name, s.first_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
    // Format response
    $students_data = [];
    foreach ($students as $student) {
        $balance = $total_fee - $student['total_paid'];
        $status = 'Not Paid';
        $status_class = 'danger';
        
        if ($balance <= 0) {
            $status = 'Paid in Full';
            $status_class = 'success';
        } elseif ($student['total_paid'] > 0) {
            $status = 'Partial';
            $status_class = 'warning';
        }
        
        $students_data[] = [
            'id' => $student['id'],
            'index_number' => $student['index_number'],
            'student_name' => $student['student_name'],
            'class' => $student['class'],
            'total_fee' => $total_fee,
            'total_paid' => $student['total_paid'],
            'balance' => $balance,
            'status' => $status,
            'status_class' => $status_class
        ];
    }
    
    // Calculate totals
    $grand_total_fee = array_sum(array_column($students_data, 'total_fee'));
    $grand_total_paid = array_sum(array_column($students_data, 'total_paid'));
    $grand_balance = array_sum(array_column($students_data, 'balance'));
    
    echo json_encode([
        'success' => true,
        'data' => [
            'academic_year' => $academic_year,
            'summary' => [
                'total_fee' => $grand_total_fee,
                'total_paid' => $grand_total_paid,
                'total_balance' => $grand_balance
            ],
            'students' => $students_data,
            'count' => count($students_data)
        ]
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>