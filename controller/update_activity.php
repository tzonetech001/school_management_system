<?php
// update_activity.php - AJAX endpoint to update activity timestamp
header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

echo json_encode(['success' => true]);
?>