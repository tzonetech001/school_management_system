<?php
// delete_holiday.php - Delete a holiday package (AJAX)
session_start();
require_once '../controller/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$admin_id = intval($_SESSION['admin_id']);
$package_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($package_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid package ID']);
    exit();
}

// Fetch the package to verify ownership and file path
$select_sql = "SELECT file_path, uploaded_by, file_name FROM holiday_packages WHERE id = ?";
$select_stmt = mysqli_prepare($conn, $select_sql);
mysqli_stmt_bind_param($select_stmt, "i", $package_id);
mysqli_stmt_execute($select_stmt);
$select_result = mysqli_stmt_get_result($select_stmt);
$package = mysqli_fetch_assoc($select_result);
mysqli_stmt_close($select_stmt);

if (!$package) {
    echo json_encode(['success' => false, 'message' => 'Package not found']);
    exit();
}

if ($package['uploaded_by'] != $admin_id) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this package. Owner: ' . $package['uploaded_by'] . ', You: ' . $admin_id]);
    exit();
}

// Delete the physical file
$file_path = __DIR__ . '/../' . $package['file_path'];
$file_name = $package['file_name'];
$debug_msg = "Attempting to delete: ID=$package_id, File=$file_name, Path=$file_path, Exists=" . (file_exists($file_path) ? 'Yes' : 'No');

if (file_exists($file_path)) {
    if (unlink($file_path)) {
        $debug_msg .= " - File deleted successfully";
    } else {
        $debug_msg .= " - Failed to delete file (permission issue?)";
    }
} else {
    $debug_msg .= " - File not found on disk";
}

// Delete from database
$delete_sql = "DELETE FROM holiday_packages WHERE id = ? AND uploaded_by = ?";
$delete_stmt = mysqli_prepare($conn, $delete_sql);
mysqli_stmt_bind_param($delete_stmt, "ii", $package_id, $admin_id);
$success = mysqli_stmt_execute($delete_stmt);
mysqli_stmt_close($delete_stmt);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Package deleted successfully', 'debug' => $debug_msg]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn) . ' | ' . $debug_msg]);
}
?>