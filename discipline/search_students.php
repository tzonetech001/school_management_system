<?php
// search_students.php - AJAX endpoint for student search WITH SCHOOL ID FILTERING
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

// Check if user has permission
$is_logged_in = isset($_SESSION['admin_id']);
if (!$is_logged_in) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$admin_id = $_SESSION['admin_id'];

// ========== GET CURRENT SCHOOL ID ==========
$school_query = "SELECT school_id FROM admins WHERE id = ?";
$stmt = $conn->prepare($school_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$school_result = $stmt->get_result();
$current_admin_data = $school_result->fetch_assoc();
$current_school_id = $current_admin_data['school_id'] ?? 1;

$query = $_GET['q'] ?? '';
$include_leavers = isset($_GET['include_leavers']) ? true : false;

if (strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

// Build search query WITH school_id filtering
$search_sql = "SELECT id, index_number, first_name, second_name, last_name, 
               CONCAT(first_name, ' ', COALESCE(second_name, ''), ' ', last_name) as name,
               class, combination, is_leaver, admission_number, status
               FROM students 
               WHERE school_id = ?
               AND (index_number LIKE ? OR 
                    first_name LIKE ? OR 
                    last_name LIKE ? OR 
                    CONCAT(first_name, ' ', COALESCE(second_name, ''), ' ', last_name) LIKE ? OR
                    admission_number LIKE ?)";

if (!$include_leavers) {
    $search_sql .= " AND is_leaver = 0";
}

$search_sql .= " AND status = 1 ORDER BY first_name, last_name LIMIT 20";

$search_term = "%{$query}%";
$stmt = mysqli_prepare($conn, $search_sql);
mysqli_stmt_bind_param($stmt, 'isssss', $current_school_id, $search_term, $search_term, $search_term, $search_term, $search_term);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$students = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Clean up the name (remove extra spaces if middle name is empty)
    $row['name'] = trim(preg_replace('/\s+/', ' ', $row['name']));
    $students[] = $row;
}

echo json_encode($students);
?>