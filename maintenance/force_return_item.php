<?php
session_start();
require_once "../controller/db_connect.php";

header("Content-Type: application/json");

if (!isset($_SESSION["admin_id"])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if (isset($_GET["assignment_id"]) && isset($_GET["admin_id"])) {
    $assignment_id = mysqli_real_escape_string($conn, $_GET["assignment_id"]);
    $admin_id = mysqli_real_escape_string($conn, $_GET["admin_id"]);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get assignment details
        $assignment_sql = "SELECT ma.*, mi.item_code, s.first_name, s.last_name, s.is_leaver 
                          FROM maintenance_assignments ma
                          JOIN maintenance_items mi ON ma.item_id = mi.id
                          JOIN students s ON ma.student_id = s.id
                          WHERE ma.id = $assignment_id";
        $assignment_result = mysqli_query($conn, $assignment_sql);
        $assignment = mysqli_fetch_assoc($assignment_result);
        
        if (!$assignment) {
            throw new Exception("Assignment not found");
        }
        
        // Update assignment status
        $update_sql = "UPDATE maintenance_assignments SET 
                      status = 'returned',
                      return_date = CURDATE(),
                      return_condition = 'good',
                      return_notes = 'Force returned: Student left/graduated'
                      WHERE id = $assignment_id";
        
        if (!mysqli_query($conn, $update_sql)) {
            throw new Exception("Error returning item: " . mysqli_error($conn));
        }
        
        // Update item status to available
        $update_item_sql = "UPDATE maintenance_items SET status = 'available' WHERE id = {$assignment['item_id']}";
        if (!mysqli_query($conn, $update_item_sql)) {
            throw new Exception("Error updating item status: " . mysqli_error($conn));
        }
        
        // Log the action
        $student_name = $assignment['first_name'] . ' ' . $assignment['last_name'];
        $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                   VALUES ({$assignment['item_id']}, 'return', 'student', {$assignment['student_id']}, $admin_id, 
                   'Force returned {$assignment['item_code']} from student: $student_name (Student left/graduated)')";
        if (!mysqli_query($conn, $log_sql)) {
            throw new Exception("Error logging return: " . mysqli_error($conn));
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        echo json_encode(['success' => true, 'message' => 'Item returned successfully']);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>