<?php
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$exam_type_id = isset($_GET['exam_type_id']) ? intval($_GET['exam_type_id']) : 0;

if (!$student_id || !$exam_type_id) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

$sql = "SELECT total_points, average, division FROM form_six_results 
        WHERE student_id = $student_id AND exam_type_id = $exam_type_id";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

if ($row) {
    echo json_encode([
        'success' => true,
        'total_points' => $row['total_points'],
        'average' => floatval($row['average']),
        'division' => $row['division']
    ]);
} else {
    echo json_encode([
        'success' => true,
        'total_points' => null,
        'average' => null,
        'division' => null
    ]);
}
?>