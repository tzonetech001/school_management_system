<?php
// super/get_super_admin.php - Get Super Admin Data for Editing
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['super_admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../controller/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $admin_id = (int)$_POST['id'];
    
    $sql = "SELECT id, first_name, last_name, email, phone, role, status, profile_image, created_at 
            FROM super_admins WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if ($admin) {
        echo json_encode($admin);
    } else {
        echo json_encode(['error' => 'Admin not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>