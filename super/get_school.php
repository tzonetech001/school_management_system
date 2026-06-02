<?php
// super/get_school.php - Get School Data for Editing
session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if Super Admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    echo json_encode(['error' => 'Unauthorized - Please login again']);
    exit();
}

require_once '../controller/db_connect.php';

// Get school ID from POST or GET
$school_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $school_id = (int)$_POST['id'];
} elseif (isset($_GET['id'])) {
    $school_id = (int)$_GET['id'];
}

if ($school_id <= 0) {
    echo json_encode(['error' => 'Invalid school ID']);
    exit();
}

// Query to get school data
$sql = "SELECT id, school_code, school_name, school_motto, address, phone, email, status, logo_path, created_at 
        FROM schools WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
$school = $result->fetch_assoc();

if ($school) {
    // Return school data as JSON
    echo json_encode($school);
} else {
    echo json_encode(['error' => 'School not found']);
}

$stmt->close();
$conn->close();
?>