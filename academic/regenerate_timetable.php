<?php
// regenerate_timetable.php - Regenerate timetable with new settings
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../mhs/login.php');
    exit();
}

// Check if edit params exist
if (!isset($_SESSION['edit_params'])) {
    $_SESSION['error'] = "No parameters found for regeneration.";
    header('Location: timetable.php');
    exit();
}

$params = $_SESSION['edit_params'];
$timetable_id = $params['timetable_id'] ?? 0;

// Unset the session params
unset($_SESSION['edit_params']);

// Get the original timetable to delete old file
if ($timetable_id > 0) {
    $original_query = "SELECT filename, generated_by, generated_at FROM generated_timetables WHERE id = ?";
    $stmt = $conn->prepare($original_query);
    $stmt->bind_param("i", $timetable_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $original = $result->fetch_assoc();
        
        // Delete old file from server
        $old_file = '../uploads/timetables/' . $original['filename'] . '.html';
        if (file_exists($old_file)) {
            unlink($old_file);
        }
        
        // Delete old record from database
        $delete_sql = "DELETE FROM generated_timetables WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $timetable_id);
        $delete_stmt->execute();

        $original_generated_by = intval($original['generated_by']);
        $original_generated_at = $original['generated_at'];
    } else {
        $original_generated_by = 0;
        $original_generated_at = null;
    }
} else {
    $original_generated_by = 0;
    $original_generated_at = null;
}

$generated_at_override = null;
if (isset($params['generated_action'])) {
    switch ($params['generated_action']) {
        case 'now':
            $generated_at_override = date('Y-m-d H:i:s');
            break;
        case 'clear':
            $generated_at_override = null;
            break;
        case 'custom':
            if (!empty($params['custom_generated_at'])) {
                $generated_at_override = str_replace('T', ' ', $params['custom_generated_at']);
                if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?:[:\d{2}]*)?$/', $generated_at_override)) {
                    $generated_at_override = $original_generated_at;
                }
            } else {
                $generated_at_override = $original_generated_at;
            }
            break;
        default:
            $generated_at_override = $original_generated_at;
            break;
    }
} else {
    $generated_at_override = $original_generated_at;
}

// Forward to generate with the parameters
$_POST = [
    'term' => $params['term'],
    'year' => $params['year'],
    'start_time' => $params['start_time'],
    'session_length' => $params['session_length'],
    'sessions_per_day' => $params['sessions_per_day'],
    'break_after' => $params['break_after'],
    'break_length' => $params['break_length'],
    'days' => $params['days'],
    'export_format' => 'excel',
    'action' => 'save',
    'generated_by_override' => $original_generated_by > 0 ? $original_generated_by : $_SESSION['admin_id'],
    'generated_at_override' => $generated_at_override,
    'last_updated_by_override' => $_SESSION['admin_id'],
    'last_updated_at_override' => date('Y-m-d H:i:s')
];

// Include the generation file - this will create the new file
include 'generate_session_timetable.php';

// After generation, redirect back
if (!isset($_SESSION['error'])) {
    $_SESSION['success'] = "Timetable updated successfully!";
}

// Determine where to redirect
$has_admin_role = false;
$admin_roles_query = "SELECT ar.role_name FROM admin_role_assignments ara 
                      JOIN admin_roles ar ON ara.role_id = ar.id 
                      WHERE ara.admin_id = " . intval($_SESSION['admin_id']) . " 
                      AND ar.role_name IN ('Head Master', 'Second Master', 'Academic Master')";
$admin_roles_result = mysqli_query($conn, $admin_roles_query);
if ($admin_roles_result && mysqli_num_rows($admin_roles_result) > 0) {
    $has_admin_role = true;
}

if ($has_admin_role) {
    header('Location: timetable.php');
} else {
    header('Location: teacher_timetable.php');
}
exit();
?>