<?php
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }
    
    $id = intval($_POST['id']);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
        exit();
    }
    
    // Check if category has any productions
    $check_sql = "SELECT COUNT(*) as count FROM productions p 
                  INNER JOIN production_categories pc ON p.category = pc.category_name 
                  WHERE pc.id = $id AND pc.status = 1";
    $check_result = mysqli_query($conn, $check_sql);
    $check_data = mysqli_fetch_assoc($check_result);
    
    if ($check_data['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete category that has productions']);
        exit();
    }
    
    // Get category name before deletion for logging
    $cat_sql = "SELECT category_name FROM production_categories WHERE id = $id";
    $cat_result = mysqli_query($conn, $cat_sql);
    $cat_data = mysqli_fetch_assoc($cat_result);
    $category_name = $cat_data['category_name'];
    
    // Soft delete (update status)
    $sql = "UPDATE production_categories SET status = 0, updated_at = NOW() WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        // Log the action
        $log_sql = "INSERT INTO production_logs (action, admin_id, details) 
                   VALUES ('category_deleted', {$_SESSION['admin_id']}, 
                   'Deleted category: $category_name')";
        mysqli_query($conn, $log_sql);
        
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting category: ' . mysqli_error($conn)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>