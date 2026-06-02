<?php
// super/get_school.php - Get School Data with Logo
session_start();

if (!isset($_SESSION['super_admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../controller/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $school_id = (int)$_POST['id'];
    
    $sql = "SELECT id, school_code, school_name, school_motto, address, phone, email, status, logo_path, created_at FROM schools WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $school = $result->fetch_assoc();
    
    if ($school) {
        echo json_encode($school);
    } else {
        echo json_encode(['error' => 'School not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>