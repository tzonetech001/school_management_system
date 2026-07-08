<?php
// fee/ajax_record_payment.php - Standalone AJAX handler
// NO INCLUDES, NO HTML, PURE JSON ONLY

// Disable caching and clean buffers
header('Cache-Control: no-cache, must-revalidate');
header('Content-Type: application/json; charset=utf-8');

// Clean ALL output buffers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

try {
    // Session management
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'samesite' => 'Lax']);
        session_start();
    }
    
    // Auth check
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired']);
        exit();
    }
    
    // Load database config
    $env = parse_ini_file(__DIR__ . '/../.env');
    if (!$env) {
        echo json_encode(['success' => false, 'message' => 'Config error']);
        exit();
    }
    
    $conn = mysqli_connect($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit();
    }
    mysqli_set_charset($conn, 'utf8mb4');
    
    // Get admin and school
    $admin_id = $_SESSION['admin_id'];
    $stmt = mysqli_prepare($conn, "SELECT school_id FROM admins WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $admin_id);
    mysqli_stmt_execute($stmt);
    $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    
    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'Invalid admin']);
        exit();
    }
    $school_id = $admin['school_id'];
    
    // Get action
    $action = $_POST['action'] ?? '';
    
    if ($action === 'get_student_summary') {
        $student_id = intval($_POST['student_id'] ?? 0);
        
        if ($student_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid student']);
            exit();
        }
        
        // Get total fee
        $stmt = mysqli_prepare($conn, "SELECT total_fee FROM fee_settings WHERE school_id = ? ORDER BY updated_at DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $school_id);
        mysqli_stmt_execute($stmt);
        $fee = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $total_fee = $fee ? floatval($fee['total_fee']) : 0;
        
        // Get total paid
        $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount), 0) as total_paid FROM student_payments WHERE student_id = ? AND status = 'completed'");
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        mysqli_stmt_execute($stmt);
        $paid = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $total_paid = floatval($paid['total_paid']);
        
        $balance = $total_fee - $total_paid;
        $status = $balance <= 0 ? 'Paid in Full' : ($total_paid > 0 ? 'Partial' : 'Not Paid');
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_fee' => $total_fee,
                'total_paid' => $total_paid,
                'balance' => $balance,
                'status' => $status
            ]
        ]);
        exit();
    }
    
    if ($action === 'record_payment') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date'] ?? date('Y-m-d'));
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? 'cash');
        $reference = mysqli_real_escape_string($conn, $_POST['reference'] ?? '');
        $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
        
        if ($student_id <= 0 || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit();
        }
        
        // Insert payment
        $stmt = mysqli_prepare($conn, "INSERT INTO student_payments (student_id, amount, payment_date, payment_method, reference_number, notes, recorded_by, school_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
        mysqli_stmt_bind_param($stmt, "idssssii", $student_id, $amount, $payment_date, $payment_method, $reference, $notes, $admin_id, $school_id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => true, 'message' => 'Payment recorded successfully!']);
            exit();
        } else {
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
            exit();
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit();
}
?>