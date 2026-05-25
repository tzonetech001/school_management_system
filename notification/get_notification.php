<?php
// get_notification.php
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit();
}

$notification_id = mysqli_real_escape_string($conn, $_GET['id']);
$admin_id = $_SESSION['admin_id'];

// Get notification details
$sql = "SELECT n.*, 
        CONCAT(a.first_name, ' ', a.last_name) as author_name,
        GROUP_CONCAT(DISTINCT ar.role_name) as author_roles
        FROM notifications n
        JOIN admins a ON n.admin_id = a.id
        LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
        LEFT JOIN admin_roles ar ON ara.role_id = ar.id
        WHERE n.id = $notification_id
        GROUP BY n.id";

$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $notification = mysqli_fetch_assoc($result);
    echo json_encode(['success' => true, 'notification' => $notification]);
} else {
    echo json_encode(['success' => false, 'message' => 'Notification not found']);
}
?>