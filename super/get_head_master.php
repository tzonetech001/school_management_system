<?php
// super/get_head_master.php - Get Head Master Data for AJAX
session_start();

if (!isset($_SESSION['super_admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../controller/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $admin_id = (int)$_POST['id'];
    
    $sql = "SELECT * FROM admins WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if ($admin) {
        echo json_encode($admin);
    } else {
        echo json_encode(['error' => 'Head Master not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>