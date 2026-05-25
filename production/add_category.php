<?php
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check admin authentication
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }
    
    // Validate required fields
    if (empty($_POST['name'])) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        exit();
    }
    
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $description = !empty($_POST['description']) ? mysqli_real_escape_string($conn, trim($_POST['description'])) : '';
    $unit = !empty($_POST['unit']) ? mysqli_real_escape_string($conn, trim($_POST['unit'])) : '';
    
    // Check if category exists
    $check_sql = "SELECT id FROM production_categories WHERE category_name = '$name'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'Category already exists']);
    } else {
        // Insert with all fields
        $sql = "INSERT INTO production_categories (category_name, description, unit) 
                VALUES ('$name', '$description', '$unit')";
        
        if (mysqli_query($conn, $sql)) {
            // Log the action
            $log_sql = "INSERT INTO production_logs (action, admin_id, details) 
                       VALUES ('category_added', {$_SESSION['admin_id']}, 
                       'Added new category: $name')";
            mysqli_query($conn, $log_sql);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Category added successfully',
                'id' => mysqli_insert_id($conn)
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Error adding category: ' . mysqli_error($conn)
            ]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>