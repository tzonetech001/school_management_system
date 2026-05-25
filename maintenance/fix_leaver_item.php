<?php
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_GET['item_id']) && isset($_GET['admin_id'])) {
    $item_id = intval($_GET['item_id']);
    $admin_id = intval($_GET['admin_id']);
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get assignment details
        $assignment_sql = "SELECT ma.*, mi.item_code, s.first_name, s.last_name 
                          FROM maintenance_assignments ma
                          JOIN maintenance_items mi ON ma.item_id = mi.id
                          JOIN students s ON ma.student_id = s.id
                          WHERE ma.item_id = $item_id 
                          AND ma.status = 'active'
                          LIMIT 1";
        
        $assignment_result = mysqli_query($conn, $assignment_sql);
        
        if ($assignment_result && mysqli_num_rows($assignment_result) > 0) {
            $assignment = mysqli_fetch_assoc($assignment_result);
            
            // Update assignment
            $update_sql = "UPDATE maintenance_assignments SET 
                          status = 'returned',
                          return_date = CURDATE(),
                          return_condition = 'good',
                          return_notes = 'Auto-returned: Student is leaver/graduate'
                          WHERE id = {$assignment['id']}";
            
            if (!mysqli_query($conn, $update_sql)) {
                throw new Exception("Error updating assignment");
            }
            
            // Update item status
            $update_item_sql = "UPDATE maintenance_items SET status = 'available' WHERE id = $item_id";
            if (!mysqli_query($conn, $update_item_sql)) {
                throw new Exception("Error updating item status");
            }
            
            // Log the action
            $student_name = $assignment['first_name'] . ' ' . $assignment['last_name'];
            $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                       VALUES ($item_id, 'auto_return', 'student', {$assignment['student_id']}, $admin_id, 
                       'Auto-returned {$assignment['item_code']} from leaver/graduate: $student_name')";
            mysqli_query($conn, $log_sql);
            
            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Item auto-returned successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No active assignment found']);
        }
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
}
?>