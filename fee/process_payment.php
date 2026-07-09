<?php
// Standalone payment processor - BYPASSES .htaccess rewrite rules
// No includes, pure JSON, guaranteed to work

// Set headers FIRST
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// Clean buffers
while (ob_get_level()) { ob_end_clean(); }
ob_start();

try {
    // Debug: log what we receive
    $debug = [
        'method' => $_SERVER['REQUEST_METHOD'],
        'post' => $_POST,
        'get' => $_GET,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
    ];
    error_log("PAYMENT DEBUG: " . json_encode($debug));
    
    // Check if it's POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit();
    }
    
    // Get action from POST or GET (fallback)
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (empty($action)) {
        echo json_encode(['success' => false, 'message' => 'No action specified', 'post' => $_POST]);
        exit();
    }
    
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check auth
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit();
    }
    
    // Database connection
    require_once __DIR__ . '/../controller/db_connect.php';
    
    $admin_id = $_SESSION['admin_id'];
    
    // Get school_id
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
    
    // Handle get_student_summary
    if ($action === 'get_student_summary') {
        $student_id = intval($_POST['student_id'] ?? $_GET['student_id'] ?? 0);
        
        if ($student_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid student ID', 'received' => $_POST['student_id'] ?? 'none']);
            exit();
        }
        
        // Get fee settings
        $stmt = mysqli_prepare($conn, "SELECT total_fee FROM fee_settings WHERE school_id = ? ORDER BY updated_at DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $school_id);
        mysqli_stmt_execute($stmt);
        $fee = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        
        $total_fee = $fee ? floatval($fee['total_fee']) : 0;
        
        // Get paid amount
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
    
    // Handle record_payment
    if ($action === 'record_payment') {
        // Accept from both POST and GET for testing
        $student_id = intval($_POST['student_id'] ?? $_GET['student_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? $_GET['amount'] ?? 0);
        $payment_date = $_POST['payment_date'] ?? $_GET['payment_date'] ?? date('Y-m-d');
        $payment_method = $_POST['payment_method'] ?? $_GET['payment_method'] ?? 'cash';
        $reference = $_POST['reference'] ?? $_GET['reference'] ?? '';
        $notes = $_POST['notes'] ?? $_GET['notes'] ?? '';
        
        // Log what we received
        error_log("RECORD PAYMENT - Student: $student_id, Amount: $amount, Date: $payment_date");
        
        if ($student_id <= 0 || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => "Invalid data - Student: $student_id, Amount: $amount"]);
            exit();
        }
        
        // Verify student exists
        $stmt = mysqli_prepare($conn, "SELECT id FROM students WHERE id = ? AND school_id = ? AND status = 1");
        mysqli_stmt_bind_param($stmt, "ii", $student_id, $school_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 0) {
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit();
        }
        mysqli_stmt_close($stmt);
        
        // Insert payment
        $stmt = mysqli_prepare($conn, "INSERT INTO student_payments (student_id, amount, payment_date, payment_method, reference_number, notes, recorded_by, school_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
        mysqli_stmt_bind_param($stmt, "idssssii", $student_id, $amount, $payment_date, $payment_method, $reference, $notes, $admin_id, $school_id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            
            echo json_encode(['success' => true, 'message' => 'Payment recorded successfully!']);
            exit();
        } else {
            $error = mysqli_error($conn);
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            
            error_log("DB ERROR: $error");
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $error]);
            exit();
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    exit();
    
} catch (Exception $e) {
    error_log("EXCEPTION: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit();
}
?>