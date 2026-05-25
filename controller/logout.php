<?php
// logout.php - Complete logout script
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Clear any output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Check if timeout parameter exists
$timeout = isset($_GET['timeout']) ? '?timeout=1' : '';

// Redirect to login page
header("Location: ../mhs/login.php" . $timeout);
exit();
?>