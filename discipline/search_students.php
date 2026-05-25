<?php
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

// Check if user has permission
$is_logged_in = isset($_SESSION['admin_id']);
if (!$is_logged_in) {
    echo json_encode([]);
    exit();
}

$query = $_GET['q'] ?? '';
$include_leavers = isset($_GET['include_leavers']) ? true : false;

if (strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

// Build search query
$search_sql = "SELECT id, index_number, first_name, last_name, 
               CONCAT(first_name, ' ', last_name) as name,
               class, combination, is_leaver
               FROM students 
               WHERE (index_number LIKE ? OR 
                     first_name LIKE ? OR 
                     last_name LIKE ? OR 
                     CONCAT(first_name, ' ', last_name) LIKE ? OR
                     admission_number LIKE ?)";
               
if (!$include_leavers) {
    $search_sql .= " AND is_leaver = FALSE";
}

$search_sql .= " AND status = 1 ORDER BY first_name, last_name LIMIT 20";

$search_term = "%{$query}%";
$stmt = mysqli_prepare($conn, $search_sql);
mysqli_stmt_bind_param($stmt, 'sssss', $search_term, $search_term, $search_term, $search_term, $search_term);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$students = [];
while ($row = mysqli_fetch_assoc($result)) {
    $students[] = $row;
}

echo json_encode($students);
?>