<?php
// get_stats.php - Get statistics for dashboard
// No whitespace or output before this line!
ob_start();
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    ob_end_flush();
    exit();
}

$admin_id = intval($_SESSION['admin_id']);

// Get school_id
$school_query = "SELECT school_id FROM admins WHERE id = $admin_id";
$school_result = mysqli_query($conn, $school_query);
if (!$school_result) {
    echo json_encode(['error' => 'Database error: ' . mysqli_error($conn)]);
    ob_end_flush();
    exit();
}

$school_data = mysqli_fetch_assoc($school_result);
$school_id = $school_data['school_id'] ?? 1;

// Get Form 5 combinations count
$form5_query = "SELECT COUNT(DISTINCT combination) as count 
                FROM students 
                WHERE class = 'Form Five' 
                AND school_id = $school_id 
                AND (is_leaver = 0 OR is_leaver IS NULL)
                AND combination IS NOT NULL 
                AND combination != ''";
$form5_result = mysqli_query($conn, $form5_query);
$form5_count = 0;
if ($form5_result && $row = mysqli_fetch_assoc($form5_result)) {
    $form5_count = intval($row['count']);
}

// Get Form 6 combinations count
$form6_query = "SELECT COUNT(DISTINCT combination) as count 
                FROM students 
                WHERE class = 'Form Six' 
                AND school_id = $school_id 
                AND (is_leaver = 0 OR is_leaver IS NULL)
                AND combination IS NOT NULL 
                AND combination != ''";
$form6_result = mysqli_query($conn, $form6_query);
$form6_count = 0;
if ($form6_result && $row = mysqli_fetch_assoc($form6_result)) {
    $form6_count = intval($row['count']);
}

// Get teachers count
$teachers_query = "SELECT COUNT(*) as count 
                   FROM admins 
                   WHERE school_id = $school_id 
                   AND status = 1";
$teachers_result = mysqli_query($conn, $teachers_query);
$teachers_count = 0;
if ($teachers_result && $row = mysqli_fetch_assoc($teachers_result)) {
    $teachers_count = intval($row['count']);
}

// Clear any output buffers and send JSON
ob_clean();
echo json_encode([
    'success' => true,
    'form5_count' => $form5_count,
    'form6_count' => $form6_count,
    'teachers_count' => $teachers_count
]);
ob_end_flush();
exit();
?>