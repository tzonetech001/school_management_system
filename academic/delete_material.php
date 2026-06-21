<?php
// delete_material.php - Delete a learning material (AJAX)
session_start();
require_once '../controller/db_connect.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check login
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$admin_id = intval($_SESSION['admin_id']);
$material_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($material_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid material ID']);
    exit();
}

// Fetch the material to verify ownership and get file path
$select_sql = "SELECT file_path, uploaded_by, file_name FROM learning_materials WHERE id = ?";
$select_stmt = mysqli_prepare($conn, $select_sql);
mysqli_stmt_bind_param($select_stmt, "i", $material_id);
mysqli_stmt_execute($select_stmt);
$select_result = mysqli_stmt_get_result($select_stmt);
$material = mysqli_fetch_assoc($select_result);
mysqli_stmt_close($select_stmt);

if (!$material) {
    echo json_encode(['success' => false, 'message' => 'Material not found']);
    exit();
}

// Verify ownership
if ($material['uploaded_by'] != $admin_id) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this material. Owner: ' . $material['uploaded_by'] . ', You: ' . $admin_id]);
    exit();
}

// Delete the physical file
$file_path = __DIR__ . '/../' . $material['file_path']; // assume file_path is relative to root
$file_name = $material['file_name'];
$debug_msg = "Attempting to delete: ID=$material_id, File=$file_name, Path=$file_path, Exists=" . (file_exists($file_path) ? 'Yes' : 'No');

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
$delete_sql = "DELETE FROM learning_materials WHERE id = ? AND uploaded_by = ?";
$delete_stmt = mysqli_prepare($conn, $delete_sql);
mysqli_stmt_bind_param($delete_stmt, "ii", $material_id, $admin_id);
$success = mysqli_stmt_execute($delete_stmt);
mysqli_stmt_close($delete_stmt);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Material deleted successfully', 'debug' => $debug_msg]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn) . ' | ' . $debug_msg]);
}
?>