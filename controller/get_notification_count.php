<?php
// get_notification_count.php - AJAX endpoint for notification count
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

require_once 'db_connect.php';

$admin_id = $_SESSION['admin_id'];

// Get last notification check time
$last_check_sql = "SELECT last_notification_check FROM admins WHERE id = $admin_id";
$last_check_result = mysqli_query($conn, $last_check_sql);
$last_check_row = mysqli_fetch_assoc($last_check_result);
$last_check = $last_check_row ? $last_check_row['last_notification_check'] : null;

// Get admin roles
$admin_roles_sql = "SELECT GROUP_CONCAT(DISTINCT ar.role_name) as roles
                    FROM admin_role_assignments ara
                    JOIN admin_roles ar ON ara.role_id = ar.id
                    WHERE ara.admin_id = $admin_id";
$admin_roles_result = mysqli_query($conn, $admin_roles_sql);
$admin_roles_row = mysqli_fetch_assoc($admin_roles_result);
$admin_roles = $admin_roles_row ? explode(',', $admin_roles_row['roles']) : [];
$is_admin_user = in_array('Head Master', $admin_roles) || in_array('Second Master', $admin_roles) || in_array('Academic Master', $admin_roles);

$count = 0;

if ($last_check) {
    $unread_sql = "SELECT COUNT(DISTINCT n.id) as count 
                   FROM notifications n
                   LEFT JOIN notification_views nv ON n.id = nv.notification_id AND nv.viewer_id = $admin_id
                   WHERE n.status = 'active' 
                   AND n.created_at > '$last_check'
                   AND nv.id IS NULL";
    
    if (!$is_admin_user) {
        $unread_sql .= " AND (n.visibility = 'public' OR n.admin_id = $admin_id)";
    }
    
    $unread_result = mysqli_query($conn, $unread_sql);
    if ($unread_result) {
        $unread_row = mysqli_fetch_assoc($unread_result);
        $count = $unread_row ? (int)$unread_row['count'] : 0;
    }
}

echo json_encode(['count' => $count]);
?>