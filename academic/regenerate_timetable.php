<?php
// regenerate_timetable.php - Regenerate timetable with new settings
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../mhs/login.php');
    exit();
}

// Check if edit params exist
$params = [];
$session_params = [];
if (isset($_SESSION['edit_params']) && is_array($_SESSION['edit_params'])) {
    $session_params = $_SESSION['edit_params'];
}

if (!empty($_POST)) {
    $posted_timetable_id = isset($_POST['edit_timetable_id']) ? intval($_POST['edit_timetable_id']) : 0;
    $params = [
        'timetable_id' => $posted_timetable_id > 0 ? $posted_timetable_id : ($session_params['timetable_id'] ?? 0),
        'term' => isset($_POST['term']) ? trim($_POST['term']) : ($session_params['term'] ?? ''),
        'year' => isset($_POST['year']) ? intval($_POST['year']) : ($session_params['year'] ?? date('Y')),
        'start_time' => isset($_POST['start_time']) ? $_POST['start_time'] : ($session_params['start_time'] ?? '08:00'),
        'session_length' => isset($_POST['session_length']) ? intval($_POST['session_length']) : ($session_params['session_length'] ?? 40),
        'sessions_per_day' => isset($_POST['sessions_per_day']) ? intval($_POST['sessions_per_day']) : ($session_params['sessions_per_day'] ?? 6),
        'break_after' => isset($_POST['break_after']) ? intval($_POST['break_after']) : ($session_params['break_after'] ?? 0),
        'break_length' => isset($_POST['break_length']) ? intval($_POST['break_length']) : ($session_params['break_length'] ?? 30),
        'days' => isset($_POST['days']) && is_array($_POST['days']) ? $_POST['days'] : ($session_params['days'] ?? []),
        'generated_action' => isset($_POST['generated_action']) ? $_POST['generated_action'] : ($session_params['generated_action'] ?? 'keep'),
        'custom_generated_at' => isset($_POST['custom_generated_at']) ? $_POST['custom_generated_at'] : ($session_params['custom_generated_at'] ?? ''),
    ];
} elseif (!empty($session_params)) {
    $params = $session_params;
}

if (($params['timetable_id'] ?? 0) <= 0 && isset($_SESSION['active_timetable_edit_id'])) {
    $params['timetable_id'] = intval($_SESSION['active_timetable_edit_id']);
}

if (empty($params) || (($params['timetable_id'] ?? 0) <= 0 && empty($params['term']) && empty($params['year']) && empty($params['start_time']))) {
    $_SESSION['error'] = "No parameters found for regeneration.";
    header('Location: timetable.php');
    exit();
}

$timetable_id = $params['timetable_id'] ?? 0;

// Unset the session params
unset($_SESSION['edit_params']);

// Get the original timetable to delete old file
if ($timetable_id > 0) {
    $original_query = "SELECT filename, generated_by, generated_at, signature FROM generated_timetables WHERE id = ?";
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
    'generated_at_override' => $generated_at_override ?: date('Y-m-d H:i:s'),
    'last_updated_by_override' => $_SESSION['admin_id'],
    'last_updated_at_override' => date('Y-m-d H:i:s')
];

// Include the generation file - this will create the new file without breaking the redirect
ob_start();
include 'generate_session_timetable.php';
ob_end_clean();

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
    header('Location: timetable.php');
}
exit();
?>