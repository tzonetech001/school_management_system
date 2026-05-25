<?php
// students.php - Student Management with Fixed Leaver & Status Functions
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'] ?? 0;

// Load theme settings
$theme_settings = [];
$query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $theme_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id";
$prefs_result = mysqli_query($conn, $prefs_query);
if ($prefs_result && mysqli_num_rows($prefs_result) > 0) {
    while ($row = mysqli_fetch_assoc($prefs_result)) {
        $preferences[$row['preference_key']] = $row['preference_value'];
    }
}

$default_colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
    'primary_light' => '#8bc5d6',
    'light' => '#f8f9fa',
    'white' => '#ffffff',
    'gray' => '#e9ecef',
    'text' => '#333333',
    'text_light' => '#666666',
    'border' => '#e0e0e0',
    'success' => '#28a745',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#17a2b8'
];

$colors = $default_colors;
foreach ($theme_settings as $key => $value) {
    if (array_key_exists($key, $colors) && $value !== null) {
        $colors[$key] = $value;
    }
}

$pref_defaults = [
    'background_opacity' => '65',
    'animations' => '1',
    'font_size' => '16',
    'compact_mode' => '0',
    'background_option' => 'image',
    'sidebar_collapsed' => '0',
    'animation_speed' => 'normal'
];

foreach ($pref_defaults as $key => $default) {
    if (!isset($preferences[$key]) || $preferences[$key] === null) {
        $preferences[$key] = $default;
    }
}

$bg_opacity = isset($preferences['background_opacity']) ? $preferences['background_opacity'] / 100 : 0.65;
$animations_enabled = $preferences['animations'];
$font_size = $preferences['font_size'];
$compact_mode = $preferences['compact_mode'];
$bg_option = $preferences['background_option'];
$sidebar_collapsed = $preferences['sidebar_collapsed'];
$animation_speed = $preferences['animation_speed'];

$animation_speeds = ['slow' => '0.5s', 'normal' => '0.3s', 'fast' => '0.15s'];
$animation_duration = isset($animation_speeds[$animation_speed]) ? $animation_speeds[$animation_speed] : '0.3s';

$font_size_map = ['10' => '10px', '12' => '12px', '14' => '14px', '16' => '16px', '18' => '18px'];
$font_size_value = isset($font_size_map[$font_size]) ? $font_size_map[$font_size] : '16px';

$background_colors = ['gray' => '#e9ecef', 'eye_care' => '#c7e9c0', 'milk' => '#fdf5e6', 'dark_light' => '#2d2d2d'];

if ($bg_option === 'image') {
    $bg_style = "linear-gradient(rgba(255,255,255,{$bg_opacity}), rgba(255,255,255,{$bg_opacity})), url('../muyovozi.png') no-repeat center center fixed";
    $bg_size = 'cover';
} else {
    $bg_color = isset($background_colors[$bg_option]) ? $background_colors[$bg_option] : '#e9ecef';
    $bg_style = $bg_color;
    $bg_size = 'auto';
}

// ==================== PERMISSION CHECK ====================
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 3) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view student members.";
    header("Location: ../404.php");
    exit();
}

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

// ==================== HELPER FUNCTIONS ====================

function isStudentAccountLocked($conn, $student_id) {
    $sql = "SELECT locked_until, failed_login_attempts FROM students WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    
    if (!$student) return false;
    
    if ($student['locked_until'] !== null) {
        $locked_until = strtotime($student['locked_until']);
        $now = time();
        
        if ($locked_until <= $now) {
            unlockStudentAccount($conn, $student_id);
            return false;
        }
        return true;
    }
    return false;
}

function getStudentLockInfo($conn, $student_id) {
    $sql = "SELECT locked_until, failed_login_attempts FROM students WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getStudentLockExpiry($conn, $student_id) {
    $sql = "SELECT locked_until FROM students WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    
    if ($student && $student['locked_until'] !== null) {
        return $student['locked_until'];
    }
    return null;
}

function unlockStudentAccount($conn, $student_id) {
    $sql = "UPDATE students SET 
            failed_login_attempts = 0, 
            locked_until = NULL,
            last_login_attempt = NULL 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    
    if ($stmt->execute()) {
        $admission_sql = "SELECT admission_number FROM students WHERE id = ?";
        $stmt = $conn->prepare($admission_sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $admission_result = $stmt->get_result();
        $student = $admission_result->fetch_assoc();
        
        if ($student) {
            $delete_sql = "DELETE FROM student_login_attempts WHERE identifier = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("s", $student['admission_number']);
            $stmt->execute();
        }
        return true;
    }
    return false;
}

function updateRoomOccupancy($conn, $room_id) {
    $update_sql = "UPDATE dormitory_rooms 
                   SET current_occupancy = (
                       SELECT COUNT(*) FROM student_dormitory 
                       WHERE room_id = $room_id AND status = 'Active'
                   )
                   WHERE id = $room_id";
    return mysqli_query($conn, $update_sql);
}

function updateDormitoryOccupancy($conn, $dormitory_id) {
    $update_sql = "UPDATE dormitories 
                   SET current_occupancy = (
                       SELECT COUNT(DISTINCT sd.id) 
                       FROM student_dormitory sd
                       JOIN dormitory_rooms dr ON sd.room_id = dr.id
                       WHERE dr.dormitory_id = $dormitory_id AND sd.status = 'Active'
                   )
                   WHERE id = $dormitory_id";
    return mysqli_query($conn, $update_sql);
}

function cleanupStudentDormitoryAssignments($conn, $student_id) {
    $cleaned_count = 0;
    $rooms_to_update = [];
    $dormitories_to_update = [];
    
    $assignments_sql = "SELECT id, room_id, dormitory_id FROM student_dormitory 
                       WHERE student_id = $student_id AND status = 'Active'";
    $assignments_result = mysqli_query($conn, $assignments_sql);
    
    if ($assignments_result && mysqli_num_rows($assignments_result) > 0) {
        while ($row = mysqli_fetch_assoc($assignments_result)) {
            $assignment_id = $row['id'];
            $room_id = $row['room_id'];
            $dormitory_id = $row['dormitory_id'];
            
            $update_sql = "UPDATE student_dormitory 
                          SET status = 'Removed',
                              removed_date = CURRENT_TIMESTAMP,
                              removal_reason = 'Auto-removed: Student marked as leaver/deactivated'
                          WHERE id = $assignment_id";
            
            if (mysqli_query($conn, $update_sql)) {
                $rooms_to_update[] = $room_id;
                $dormitories_to_update[] = $dormitory_id;
                $cleaned_count++;
            }
        }
        
        foreach (array_unique($rooms_to_update) as $room_id) {
            updateRoomOccupancy($conn, $room_id);
        }
        
        foreach (array_unique($dormitories_to_update) as $dormitory_id) {
            updateDormitoryOccupancy($conn, $dormitory_id);
        }
    }
    
    return $cleaned_count;
}

function returnStudentMaintenanceItems($conn, $student_id, $admin_id, $reason = 'Student removed') {
    $returned_count = 0;
    
    $assignments_sql = "SELECT id, item_id FROM maintenance_assignments 
                       WHERE student_id = $student_id AND status = 'active'";
    $assignments_result = mysqli_query($conn, $assignments_sql);
    
    if ($assignments_result && mysqli_num_rows($assignments_result) > 0) {
        while ($assignment = mysqli_fetch_assoc($assignments_result)) {
            $update_assignment_sql = "UPDATE maintenance_assignments SET 
                                     status = 'returned',
                                     return_date = CURDATE(),
                                     return_condition = 'good',
                                     return_notes = 'Auto-returned: $reason'
                                     WHERE id = {$assignment['id']}";
            
            if (mysqli_query($conn, $update_assignment_sql)) {
                $update_item_sql = "UPDATE maintenance_items SET status = 'available' WHERE id = {$assignment['item_id']}";
                mysqli_query($conn, $update_item_sql);
                $returned_count++;
            }
        }
    }
    
    return $returned_count;
}

function regenerateAllIndexNumbers($conn) {
    $combination_order = ['HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'];
    
    // Process Form Five
    $form_five_index = 1;
    foreach ($combination_order as $combination) {
        $form_five_sql = "SELECT id FROM students 
                         WHERE class = 'Form Five' 
                         AND combination = '$combination'
                         AND is_leaver = FALSE
                         ORDER BY 
                             CASE WHEN sex = 'Female' THEN 1 ELSE 2 END,
                             first_name, last_name";
        
        $form_five_result = mysqli_query($conn, $form_five_sql);
        while ($student = mysqli_fetch_assoc($form_five_result)) {
            $new_index = 'S5098-' . str_pad($form_five_index + 500, 4, '0', STR_PAD_LEFT);
            mysqli_query($conn, "UPDATE students SET index_number = '$new_index' WHERE id = " . $student['id']);
            $form_five_index++;
        }
    }
    
    // Process Form Six
    $form_six_index = 1;
    foreach ($combination_order as $combination) {
        $form_six_sql = "SELECT id FROM students 
                        WHERE class = 'Form Six' 
                        AND combination = '$combination'
                        AND is_leaver = FALSE
                        ORDER BY 
                            CASE WHEN sex = 'Female' THEN 1 ELSE 2 END,
                            first_name, last_name";
        
        $form_six_result = mysqli_query($conn, $form_six_sql);
        while ($student = mysqli_fetch_assoc($form_six_result)) {
            $new_index = 'S5098-' . str_pad(($form_six_index + 500), 4, '0', STR_PAD_LEFT);
            mysqli_query($conn, "UPDATE students SET index_number = '$new_index' WHERE id = " . $student['id']);
            $form_six_index++;
        }
    }
    
    return true;
}

// ==================== FIXED MARK AS LEAVER FUNCTION ====================
function markStudentAsLeaver($conn, $student_id, $admin_id) {
    // Clear any pending results
    while (mysqli_more_results($conn) && mysqli_next_result($conn));
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get student details
        $student_sql = "SELECT * FROM students WHERE id = ? AND is_leaver = FALSE";
        $stmt = $conn->prepare($student_sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $student_result = $stmt->get_result();
        
        if ($student_result->num_rows == 0) {
            throw new Exception("Student not found or already a leaver!");
        }
        
        $student = $student_result->fetch_assoc();
        $current_class = $student['class'];
        $previous_class = $student['class'];
        $current_year = date('Y');
        
        // Determine leaver type
        if ($current_class == 'Form Six') {
            $graduation_status = 'Graduated';
            $leaver_type = 'Graduated';
            $reason = "Graduated from Form Six";
        } else {
            $graduation_status = 'Left';
            $leaver_type = 'Transferred';
            $reason = "Transferred from " . $current_class;
        }
        
        // Clean up dormitory assignments
        $dorm_cleaned = cleanupStudentDormitoryAssignments($conn, $student_id);
        
        // Return maintenance items
        $maintenance_returned = returnStudentMaintenanceItems($conn, $student_id, $admin_id, $reason);
        
        // Mark as leaver
        $update_sql = "UPDATE students SET 
                      is_leaver = TRUE, 
                      status = FALSE,
                      year_left = ?,
                      previous_class = ?,
                      graduation_status = ?,
                      graduation_year = ?,
                      class = 'Leavers',
                      updated_at = CURRENT_TIMESTAMP,
                      updated_by_admin = ?
                      WHERE id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssssii", $current_year, $previous_class, $graduation_status, $current_year, $admin_id, $student_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error marking as leaver: " . $stmt->error);
        }
        
        // Add to leavers table
        $check_sql = "SELECT id FROM student_leavers WHERE student_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows == 0) {
            $insert_sql = "INSERT INTO student_leavers (student_id, index_number, first_name, last_name, 
                          combination, class_left, year_left, reason, leaver_type) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("issssssss", 
                $student_id, $student['index_number'], $student['first_name'], 
                $student['last_name'], $student['combination'], $previous_class, 
                $current_year, $reason, $leaver_type);
            $stmt->execute();
        }
        
        // Add to history
        $history_sql = "INSERT INTO student_graduation_history (
            student_id, from_class, to_class, academic_year, 
            graduation_type, graduation_date, final_index_number,
            remarks, recorded_by
        ) VALUES (?, ?, ?, ?, ?, CURRENT_DATE, ?, ?, ?)";
        
        $academic_year = ($current_year - 1) . '/' . $current_year;
        $remarks = $reason . ($maintenance_returned > 0 ? ' - ' . $maintenance_returned . ' maintenance items returned' : '');
        
        $stmt = $conn->prepare($history_sql);
        $stmt->bind_param("issssssi", 
            $student_id, $previous_class, $graduation_status, $academic_year,
            $leaver_type, $student['index_number'], $remarks, $admin_id);
        $stmt->execute();
        
        // Regenerate index numbers
        regenerateAllIndexNumbers($conn);
        
        mysqli_commit($conn);
        return ['success' => true, 'dorm_cleaned' => $dorm_cleaned, 'items_returned' => $maintenance_returned];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ==================== FIXED STATUS TOGGLE FUNCTION ====================
function toggleStudentStatus($conn, $student_id, $admin_id) {
    // Clear any pending results
    while (mysqli_more_results($conn) && mysqli_next_result($conn));
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get current status
        $status_sql = "SELECT status, is_leaver FROM students WHERE id = ?";
        $stmt = $conn->prepare($status_sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            throw new Exception("Student not found!");
        }
        
        $student = $result->fetch_assoc();
        
        if ($student['is_leaver'] == 1) {
            throw new Exception("Cannot change status of a leaver/graduate student!");
        }
        
        $new_status = $student['status'] == 1 ? 0 : 1;
        $dorm_cleaned = 0;
        $items_returned = 0;
        
        // If deactivating, clean up dormitory and maintenance items
        if ($new_status == 0) {
            $dorm_cleaned = cleanupStudentDormitoryAssignments($conn, $student_id);
            $items_returned = returnStudentMaintenanceItems($conn, $student_id, $admin_id, 'Student deactivated');
        }
        
        // Update status
        $update_sql = "UPDATE students SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ii", $new_status, $student_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating status: " . $stmt->error);
        }
        
        mysqli_commit($conn);
        return ['success' => true, 'new_status' => $new_status, 'dorm_cleaned' => $dorm_cleaned, 'items_returned' => $items_returned];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ==================== FIXED DELETE STUDENT FUNCTION ====================
function deleteStudent($conn, $student_id, $admin_id) {
    // Clear any pending results
    while (mysqli_more_results($conn) && mysqli_next_result($conn));
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get student info
        $student_sql = "SELECT admission_number, first_name, last_name, class FROM students WHERE id = ?";
        $stmt = $conn->prepare($student_sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $student_result = $stmt->get_result();
        
        if ($student_result->num_rows == 0) {
            throw new Exception("Student not found!");
        }
        
        $student = $student_result->fetch_assoc();
        
        // Clean up
        cleanupStudentDormitoryAssignments($conn, $student_id);
        returnStudentMaintenanceItems($conn, $student_id, $admin_id, 'Student deleted');
        
        // Delete login attempts
        $delete_attempts = "DELETE FROM student_login_attempts WHERE identifier = ?";
        $stmt = $conn->prepare($delete_attempts);
        $stmt->bind_param("s", $student['admission_number']);
        $stmt->execute();
        
        // Delete student
        $delete_sql = "DELETE FROM students WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $student_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error deleting student: " . $stmt->error);
        }
        
        // Regenerate index numbers
        regenerateAllIndexNumbers($conn);
        
        mysqli_commit($conn);
        return ['success' => true];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ==================== PROMOTE FUNCTION ====================
function promoteFormFiveToSix($conn, $admin_id) {
    // Clear any pending results
    while (mysqli_more_results($conn) && mysqli_next_result($conn));
    
    mysqli_begin_transaction($conn);
    
    try {
        $current_year = date('Y');
        $academic_year = ($current_year - 1) . '/' . $current_year;
        
        // Mark current Form Six as graduates
        $form_six_sql = "SELECT id, index_number, first_name, last_name, combination FROM students 
                        WHERE class = 'Form Six' AND is_leaver = FALSE";
        $form_six_result = mysqli_query($conn, $form_six_sql);
        
        while ($student = mysqli_fetch_assoc($form_six_result)) {
            cleanupStudentDormitoryAssignments($conn, $student['id']);
            returnStudentMaintenanceItems($conn, $student['id'], $admin_id, 'Graduated from Form Six');
            
            $update_sql = "UPDATE students SET 
                          is_leaver = TRUE, status = FALSE, year_left = ?,
                          previous_class = 'Form Six', graduation_status = 'Graduated',
                          graduation_year = ?, class = 'Leavers',
                          updated_at = CURRENT_TIMESTAMP, updated_by_admin = ?
                          WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssii", $current_year, $current_year, $admin_id, $student['id']);
            $stmt->execute();
            
            // Add to leavers table
            $check_sql = "SELECT id FROM student_leavers WHERE student_id = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("i", $student['id']);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows == 0) {
                $insert_sql = "INSERT INTO student_leavers (student_id, index_number, first_name, last_name, 
                              combination, class_left, year_left, reason, leaver_type) 
                              VALUES (?, ?, ?, ?, ?, 'Form Six', ?, 'Graduated from Form Six', 'Graduated')";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("isssss", $student['id'], $student['index_number'], 
                    $student['first_name'], $student['last_name'], $student['combination'], $current_year);
                $stmt->execute();
            }
        }
        
        // Promote Form Five to Form Six
        $form_five_sql = "SELECT id FROM students WHERE class = 'Form Five' AND is_leaver = FALSE";
        $form_five_result = mysqli_query($conn, $form_five_sql);
        
        while ($student = mysqli_fetch_assoc($form_five_result)) {
            $update_sql = "UPDATE students SET 
                          class = 'Form Six', previous_class = 'Form Five', 
                          promotion_status = 'Promoted to Form Six',
                          class_changed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP,
                          updated_by_admin = ?
                          WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ii", $admin_id, $student['id']);
            $stmt->execute();
        }
        
        // Regenerate index numbers
        regenerateAllIndexNumbers($conn);
        
        mysqli_commit($conn);
        return ['success' => true];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ==================== HANDLE ACTIONS (PROCESSED BEFORE ANY OUTPUT) ====================

// Handle Mark as Leaver
if (isset($_GET['mark_leaver']) && !empty($_GET['mark_leaver'])) {
    $student_id = intval($_GET['mark_leaver']);
    $result = markStudentAsLeaver($conn, $student_id, $admin_id);
    
    if ($result['success']) {
        $_SESSION['success'] = "Student marked as " . ($result['dorm_cleaned'] > 0 ? "leaver! " . $result['dorm_cleaned'] . " dormitory assignments cleaned up. " : "") . 
                              ($result['items_returned'] > 0 ? $result['items_returned'] . " maintenance items returned to inventory. " : "") . 
                              "Index numbers regenerated.";
    } else {
        $_SESSION['error'] = $result['error'];
    }
    
    $redirect_url = "students.php";
    if (isset($_GET['class']) && !empty($_GET['class'])) {
        $redirect_url .= "?class=" . urlencode($_GET['class']);
    }
    header("Location: " . $redirect_url);
    exit();
}

// Handle Status Toggle
if (isset($_GET['toggle_status']) && !empty($_GET['toggle_status'])) {
    $student_id = intval($_GET['toggle_status']);
    $result = toggleStudentStatus($conn, $student_id, $admin_id);
    
    if ($result['success']) {
        $status_text = $result['new_status'] == 1 ? "activated" : "deactivated";
        $message = "Student $status_text successfully!";
        if ($result['dorm_cleaned'] > 0) {
            $message .= " " . $result['dorm_cleaned'] . " dormitory assignments cleaned up.";
        }
        if ($result['items_returned'] > 0) {
            $message .= " " . $result['items_returned'] . " maintenance items returned to inventory.";
        }
        $_SESSION['success'] = $message;
    } else {
        $_SESSION['error'] = $result['error'];
    }
    
    $redirect_url = "students.php";
    if (isset($_GET['class']) && !empty($_GET['class'])) {
        $redirect_url .= "?class=" . urlencode($_GET['class']);
    }
    header("Location: " . $redirect_url);
    exit();
}

// Handle Delete
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $student_id = intval($_GET['delete']);
    $result = deleteStudent($conn, $student_id, $admin_id);
    
    if ($result['success']) {
        $_SESSION['success'] = "Student deleted successfully! Index numbers regenerated.";
    } else {
        $_SESSION['error'] = $result['error'];
    }
    
    $redirect_url = "students.php";
    if (isset($_GET['class']) && !empty($_GET['class'])) {
        $redirect_url .= "?class=" . urlencode($_GET['class']);
    }
    header("Location: " . $redirect_url);
    exit();
}

// Handle Unlock Account
if (isset($_GET['unlock_account']) && !empty($_GET['unlock_account'])) {
    $student_id = intval($_GET['unlock_account']);
    
    if (unlockStudentAccount($conn, $student_id)) {
        $_SESSION['success'] = "Student account unlocked successfully!";
    } else {
        $_SESSION['error'] = "Error unlocking student account.";
    }
    
    $redirect_url = "students.php";
    if (isset($_GET['class']) && !empty($_GET['class'])) {
        $redirect_url .= "?class=" . urlencode($_GET['class']);
    }
    header("Location: " . $redirect_url);
    exit();
}

// Handle Reset Password
if (isset($_GET['reset_password']) && !empty($_GET['reset_password'])) {
    $student_id = intval($_GET['reset_password']);
    
    $student_sql = "SELECT first_name, last_name, parent_phone, admission_number FROM students WHERE id = ?";
    $stmt = $conn->prepare($student_sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student_result = $stmt->get_result();
    $student = $student_result->fetch_assoc();
    
    if (!$student) {
        $_SESSION['error'] = "Student not found!";
    } elseif (empty($student['parent_phone'])) {
        $_SESSION['error'] = "Parent phone number is not set for this student!";
    } else {
        $hashed_password = password_hash($student['parent_phone'], PASSWORD_DEFAULT);
        
        $update_sql = "UPDATE students SET password = ?, updated_at = CURRENT_TIMESTAMP, updated_by_admin = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sii", $hashed_password, $admin_id, $student_id);
        
        if ($stmt->execute()) {
            // Reset failed attempts and unlock
            mysqli_query($conn, "UPDATE students SET failed_login_attempts = 0, locked_until = NULL WHERE id = $student_id");
            mysqli_query($conn, "DELETE FROM student_login_attempts WHERE identifier = '{$student['admission_number']}'");
            
            $display_phone = substr($student['parent_phone'], -4);
            $_SESSION['success'] = "Password reset successfully for {$student['first_name']} {$student['last_name']}! New password is parent phone number (ending with ...$display_phone)";
        } else {
            $_SESSION['error'] = "Error resetting password: " . $stmt->error;
        }
    }
    
    $redirect_url = "students.php";
    if (isset($_GET['class']) && !empty($_GET['class'])) {
        $redirect_url .= "?class=" . urlencode($_GET['class']);
    }
    header("Location: " . $redirect_url);
    exit();
}

// Handle Promote All
if (isset($_GET['promote_form_six']) && $_GET['promote_form_six'] == 1) {
    $result = promoteFormFiveToSix($conn, $admin_id);
    
    if ($result['success']) {
        $_SESSION['success'] = "Form Five students promoted to Form Six! Current Form Six marked as graduates.";
    } else {
        $_SESSION['error'] = "Error promoting students: " . $result['error'];
    }
    
    header("Location: students.php");
    exit();
}

// Handle Regenerate Index
if (isset($_GET['regenerate_index']) && $_GET['regenerate_index'] == 1) {
    try {
        regenerateAllIndexNumbers($conn);
        $_SESSION['success'] = "Index numbers regenerated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error regenerating index numbers: " . $e->getMessage();
    }
    header("Location: students.php");
    exit();
}

// Handle Auto-Unlock All
if (isset($_GET['auto_unlock_all']) && $_GET['auto_unlock_all'] == 1) {
    $sql = "UPDATE students SET 
            failed_login_attempts = 0, 
            locked_until = NULL 
            WHERE locked_until IS NOT NULL AND locked_until <= NOW()";
    
    if (mysqli_query($conn, $sql)) {
        $affected = mysqli_affected_rows($conn);
        $_SESSION['success'] = "$affected expired locked accounts have been auto-unlocked.";
    } else {
        $_SESSION['error'] = "Error auto-unlocking accounts.";
    }
    header("Location: students.php");
    exit();
}

// ==================== GET STUDENT DATA ====================

$current_class = $_GET['class'] ?? '';

$sql_form_five = "SELECT * FROM students 
                 WHERE class = 'Form Five' AND is_leaver = FALSE
                 ORDER BY FIELD(combination, 'HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'),
                          CASE WHEN sex = 'Female' THEN 1 ELSE 2 END,
                          first_name, last_name";
$result_form_five = mysqli_query($conn, $sql_form_five);
$students_form_five = [];
if ($result_form_five && mysqli_num_rows($result_form_five) > 0) {
    while ($row = mysqli_fetch_assoc($result_form_five)) {
        $students_form_five[] = $row;
    }
}

$sql_form_six = "SELECT * FROM students 
                WHERE class = 'Form Six' AND is_leaver = FALSE
                ORDER BY FIELD(combination, 'HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'),
                         CASE WHEN sex = 'Female' THEN 1 ELSE 2 END,
                         first_name, last_name";
$result_form_six = mysqli_query($conn, $sql_form_six);
$students_form_six = [];
if ($result_form_six && mysqli_num_rows($result_form_six) > 0) {
    while ($row = mysqli_fetch_assoc($result_form_six)) {
        $students_form_six[] = $row;
    }
}

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_students,
    SUM(CASE WHEN class = 'Form Five' AND is_leaver = FALSE THEN 1 ELSE 0 END) as form_five_count,
    SUM(CASE WHEN class = 'Form Six' AND is_leaver = FALSE THEN 1 ELSE 0 END) as form_six_count,
    SUM(CASE WHEN is_leaver = TRUE THEN 1 ELSE 0 END) as leavers_count,
    SUM(CASE WHEN sex = 'Male' AND is_leaver = FALSE THEN 1 ELSE 0 END) as male_count,
    SUM(CASE WHEN sex = 'Female' AND is_leaver = FALSE THEN 1 ELSE 0 END) as female_count,
    SUM(CASE WHEN status = 1 AND is_leaver = FALSE THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status = 0 AND is_leaver = FALSE THEN 1 ELSE 0 END) as inactive_count
    FROM students";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Include header and sidebar
include '../controller/header.php';
include '../controller/sidebar.php';
?>

<!-- ==================== CSS STYLES (LOADED EARLY) ==================== -->
<style> :root {--primary-color: <?php echo $colors['primary']; ?>; --primary-dark: <?php echo $colors['primary_dark']; ?>; --primary-light: <?php echo $colors['primary_light']; ?>; --light: <?php echo $colors['light']; ?>; --white: <?php echo $colors['white']; ?>; --gray: <?php echo $colors['gray']; ?>; --text: <?php echo $colors['text']; ?>; --text-light: <?php echo $colors['text_light']; ?>; --border: <?php echo $colors['border']; ?>; --success: <?php echo $colors['success']; ?>; --danger: <?php echo $colors['danger']; ?>; --warning: <?php echo $colors['warning']; ?>; --info: <?php echo $colors['info']; ?>; --font-size-base: <?php echo $font_size_value; ?>; --animation-duration: <?php echo $animation_duration; ?>; } body {font-size: var(--font-size-base); background: <?php echo $bg_style; ?>; background-size: <?php echo $bg_size; ?>; background-position: center; min-height: 100vh; } <?php if ($compact_mode === '1'): ?> .card-body { padding: 0.75rem !important; } .btn { padding: 0.5rem 1rem !important; } .form-control, .form-select { padding: 0.375rem 0.75rem !important; } .table td, .table th { padding: 0.5rem !important; } <?php endif; ?> /* Stats Cards Styles */ .stats-card.simple-card {border: none; border-radius: 15px; padding: 20px; text-align: center; background: var(--white); box-shadow: 0 5px 15px rgba(0,0,0,0.08); transition: all 0.3s ease; height: 100%; position: relative; overflow: hidden; } .stats-card.simple-card::before {content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: var(--primary-color); } .stats-card.simple-card:hover {transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); } .stats-card.simple-card .stats-icon {margin-bottom: 10px; } .stats-card.simple-card .stats-icon i {font-size: 2.2rem; } .stats-card.simple-card h3 {font-size: 1.8rem; font-weight: bold; color: var(--text); margin: 10px 0; } .stats-card.simple-card p {color: var(--text-light); font-size: 0.9rem; margin: 0; font-weight: 500; } /* Avatar Circle */ .avatar-circle {width: 40px; height: 40px; border-radius: 50%; background-color: rgba(59, 157, 179, 0.1); display: flex; align-items: center; justify-content: center; } /* Table Styles */ .table th {font-weight: 600; color: var(--text); background-color: rgba(59, 157, 179, 0.05); border-bottom: 2px solid rgba(59, 157, 179, 0.2); padding: 12px 8px; } /* Button Group Styles */ .btn-group-sm .btn {padding: 0.25rem 0.6rem; font-size: 0.75rem; } .btn-group {gap: 4px; } .btn-group .btn {border-radius: 8px !important; transition: all 0.2s ease; } .btn-group .btn:hover {transform: scale(1.05); } /* Navigation Tabs */ .nav-tabs .nav-link {color: var(--text); border: 1px solid transparent; border-radius: 8px 8px 0 0; font-weight: 500; padding: 10px 20px; transition: all 0.3s ease; } .nav-tabs .nav-link.active {color: var(--white); background: var(--primary-color); border-color: var(--primary-color); } .nav-tabs .nav-link:hover:not(.active) {border-color: var(--gray); background: var(--light); } /* Card Header */ .card-header {background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white); } /* Badge Styles */ .badge.bg-primary {background-color: var(--primary-color) !important; } /* Mobile Responsive */ @media (max-width: 768px) {.stats-card.simple-card {padding: 15px; margin-bottom: 15px; } .stats-card.simple-card h3 {font-size: 1.5rem; } .btn-group {flex-wrap: wrap; } .btn-group .btn {margin-bottom: 4px; font-size: 0.7rem; padding: 0.2rem 0.4rem; } .nav-tabs .nav-link {font-size: 0.85rem; padding: 8px 12px; } .table th, .table td {font-size: 0.8rem; padding: 6px 4px; } .avatar-circle {width: 30px; height: 30px; margin-right: 8px; } .avatar-circle i {font-size: 0.8rem; } } /* Animation Classes */ <?php if ($animations_enabled == '1'): ?> .fade-in {animation: fadeIn var(--animation-duration) ease-in-out; } @keyframes fadeIn {from {opacity: 0; transform: translateY(10px); } to {opacity: 1; transform: translateY(0); } } .stats-card.simple-card {animation: fadeIn 0.5s ease-out; } <?php endif; ?> </style>

<!-- ==================== MAIN CONTENT ==================== -->
<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Student Management</h2>
            <div class="dropdown d-md-block d-none">
                <button class="btn btn-primary dropdown-toggle" type="button" id="actionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-2"></i>Actions
                </button>
                <ul class="dropdown-menu" aria-labelledby="actionsDropdown">
                    <li><a class="dropdown-item" href="register"><i class="fas fa-user-plus me-2"></i>New Student</a></li>
                    <li><a class="dropdown-item" href="#" id="promoteFormSixBtn"><i class="fas fa-graduation-cap me-2"></i>Promote Form 5 to 6</a></li>
                    <li><a class="dropdown-item" href="#" id="regenerateIndexBtn"><i class="fas fa-sync-alt me-2"></i>Regenerate Index</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="students?auto_unlock_all=1" onclick="return confirm('Auto-unlock all expired student accounts?');"><i class="fas fa-lock-open me-2"></i>Auto-Unlock Expired</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="report_student"><i class="fas fa-download me-2"></i>Export List</a></li>
                    <li><a class="dropdown-item" href="leavers"><i class="fas fa-user-graduate me-2"></i>Leavers/Graduates</a></li>
                </ul>
            </div>
            <div class="dropdown d-md-none">
                <button class="btn btn-primary" type="button" id="mobileActionsBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileActionsBtn">
                    <li><a class="dropdown-item" href="register.php"><i class="fas fa-user-plus me-2"></i>New Student</a></li>
                    <li><a class="dropdown-item" href="#" id="promoteFormSixBtnMobile"><i class="fas fa-graduation-cap me-2"></i>Promote Form 5 to 6</a></li>
                    <li><a class="dropdown-item" href="#" id="regenerateIndexBtnMobile"><i class="fas fa-sync-alt me-2"></i>Regenerate Index</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="students.php?auto_unlock_all=1" onclick="return confirm('Auto-unlock all expired student accounts?');"><i class="fas fa-lock-open me-2"></i>Auto-Unlock Expired</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="report_student.php"><i class="fas fa-download me-2"></i>Export List</a></li>
                    <li><a class="dropdown-item" href="leavers.php"><i class="fas fa-user-graduate me-2"></i>Leavers/Graduates</a></li>
                </ul>
            </div>
        </div>

        <!-- SweetAlert2 Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div id="successMessage" data-message="<?php echo htmlspecialchars($_SESSION['success']); ?>"></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div id="errorMessage" data-message="<?php echo htmlspecialchars($_SESSION['error']); ?>"></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-users" style="color: var(--primary-color);"></i>
                    </div>
                    <h3><?php echo $stats['total_students'] ?? 0; ?></h3>
                    <p>Total Students</p>
                    <small class="text-muted" style="font-size: 20px; font-weight: bold;"><?php echo $stats['leavers_count'] ?? 0; ?> Total leavers</small>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-graduation-cap" style="color: var(--primary-color);"></i>
                    </div>
                    <h3>
                        <span style="color: var(--success);"><?php echo $stats['form_five_count'] ?? 0; ?></span>
                        <span class="text-muted mx-2">/</span>
                        <span style="color: var(--info);"><?php echo $stats['form_six_count'] ?? 0; ?></span>
                    </h3>
                    <p>Form V / Form VI</p>
                    <h5 style="color:blue;"><?php echo ($stats['total_students'] - $stats['leavers_count']) ?? 0; ?></h5>
                    <small class="text-muted">Active students</small>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-venus-mars" style="color: var(--primary-color);"></i>
                    </div>
                    <h3>
                        <span style="color: #007bff;"><?php echo $stats['male_count'] ?? 0; ?></span>
                        <span class="text-muted mx-2">/</span>
                        <span style="color: #e83e8c;"><?php echo $stats['female_count'] ?? 0; ?></span>
                    </h3>
                    <p>Male / Female</p>
                    <small class="text-muted">Active students</small>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-check" style="color: var(--primary-color);"></i>
                    </div>
                    <h3>
                        <span style="color: var(--success);"><?php echo $stats['active_count'] ?? 0; ?></span>
                        <span class="text-muted mx-2">/</span>
                        <span style="color: var(--danger);"><?php echo $stats['inactive_count'] ?? 0; ?></span>
                    </h3>
                    <p>Active / Inactive</p>
                    <small class="text-muted">Current status</small>
                </div>
            </div>
        </div>

        <!-- Class Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="classTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="students.php" class="nav-link <?php echo empty($current_class) ? 'active' : ''; ?>">
                    <i class="fas fa-users me-2"></i>All Students
                    <span class="badge bg-primary ms-2"><?php echo count($students_form_five) + count($students_form_six); ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="students.php?class=Form%20Five" class="nav-link <?php echo $current_class == 'Form Five' ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate me-2"></i>Form Five
                    <span class="badge bg-success ms-2"><?php echo count($students_form_five); ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="students.php?class=Form%20Six" class="nav-link <?php echo $current_class == 'Form Six' ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate me-2"></i>Form Six
                    <span class="badge bg-info ms-2"><?php echo count($students_form_six); ?></span>
                </a>
            </li>
        </ul>
         
        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search students...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select id="combinationFilter" class="form-select">
                            <option value="">All Combinations</option>
                            <option value="HGE">HGE</option>
                            <option value="HGL">HGL</option>
                            <option value="HGK">HGK</option>
                            <option value="HKL">HKL</option>
                            <option value="KLF">KLF</option>
                            <option value="EGM">EGM</option>
                            <option value="HLF">HLF</option>
                            <option value="HGF">HGF</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="sexFilter" class="form-select">
                            <option value="">All Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="lockFilter" class="form-select">
                            <option value="">All Accounts</option>
                            <option value="locked">Locked Accounts (30-min lock)</option>
                            <option value="unlocked">Unlocked Accounts</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($current_class) || $current_class == 'Form Five'): ?>
        <!-- Form Five Students Card -->
        <div class="card mb-4" id="formFiveSection">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-user-graduate me-2"></i>
                    Form Five Students
                    <span class="badge bg-light text-dark ms-2"><?php echo count($students_form_five); ?> Students</span>
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="formFiveTable">
                        <thead class="table-light">
                            <tr style="text-align: center;">
                                <th>S/N</th>
                                <th>Index No.</th>
                                <th>Full Name</th>
                                <th>Comb</th>
                                <th>Admission</th>
                                <th>Status</th>
                                <th>Account</th>
                                <th>Lock Info</th>
                                <th>Actions</th>
                             </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students_form_five)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No Form Five students found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students_form_five as $index => $student): 
                                    $is_locked = isStudentAccountLocked($conn, $student['id']);
                                    $lock_info = getStudentLockInfo($conn, $student['id']);
                                    $lock_expiry = getStudentLockExpiry($conn, $student['id']);
                                    
                                    $remaining_minutes = 0;
                                    if ($lock_expiry) {
                                        $expiry = new DateTime($lock_expiry);
                                        $now = new DateTime();
                                        if ($expiry > $now) {
                                            $interval = $now->diff($expiry);
                                            $remaining_minutes = ($interval->h * 60) + $interval->i;
                                        }
                                    }
                                ?>
                                <tr data-lock-status="<?php echo $is_locked ? 'locked' : 'unlocked'; ?>"
                                    data-status="<?php echo $student['status'] ? 'Active' : 'Inactive'; ?>"
                                    data-sex="<?php echo $student['sex']; ?>"
                                    data-combination="<?php echo $student['combination']; ?>">
                                    <td class="text-center"><?php echo $index + 1; ?></td>
                                    <td class="text-center">
                                        <strong><?php echo htmlspecialchars($student['index_number']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <?php if ($student['sex'] == 'Male'): ?>
                                                    <i class="fas fa-male text-primary"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-female" style="color: #e83e8c;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars($student['second_name'] ?? ''); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($student['combination']); ?></span>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $student['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $student['status'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_locked): ?>
                                            <span class="badge bg-danger" title="Locked until <?php echo $lock_expiry; ?>">
                                                <i class="fas fa-lock me-1"></i>Locked
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-unlock me-1"></i>Unlocked
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_locked): ?>
                                            <div class="small">
                                                <span class="text-danger">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo $remaining_minutes; ?> min left
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    Failed: <?php echo $lock_info['failed_login_attempts'] ?? 0; ?>/5
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <div class="small text-muted">
                                                <i class="fas fa-check-circle text-success me-1"></i>
                                                Attempts: <?php echo $lock_info['failed_login_attempts'] ?? 0; ?>/5
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-info view-student" 
                                                    data-bs-toggle="modal" data-bs-target="#viewStudentModal"
                                                    data-student-id="<?php echo $student['id']; ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="register.php?edit=<?php echo $student['id']; ?>" 
                                               class="btn btn-outline-warning"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($is_locked): ?>
                                                <a href="students.php?unlock_account=<?php echo $student['id']; ?>&class=<?php echo urlencode($current_class); ?>" 
                                                   class="btn btn-outline-success"
                                                   title="Unlock Account"
                                                   onclick="return confirm('Unlock this student account?');">
                                                    <i class="fas fa-unlock-alt"></i>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-secondary" disabled title="Account is unlocked">
                                                    <i class="fas fa-lock-open"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-dark reset-password-student" 
                                                    data-id="<?php echo $student['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                    title="Reset Password to Parent Phone">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-dark mark-leaver-student" 
                                                    data-id="<?php echo $student['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                    data-class="<?php echo $student['class']; ?>"
                                                    title="Mark as Leaver">
                                                <i class="fas fa-sign-out-alt"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger delete-student" 
                                                    data-id="<?php echo $student['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-<?php echo $student['status'] ? 'secondary' : 'success'; ?> toggle-status-btn" 
                                                    data-id="<?php echo $student['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                    data-status="<?php echo $student['status'] ? 'Active' : 'Inactive'; ?>"
                                                    data-class="<?php echo htmlspecialchars($current_class); ?>"
                                                    title="<?php echo $student['status'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($current_class) || $current_class == 'Form Six'): ?>
        <!-- Form Six Students Card -->
        <div class="card mb-4" id="formSixSection">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-user-graduate me-2"></i>
                    Form Six Students
                    <span class="badge bg-light text-dark ms-2"><?php echo count($students_form_six); ?> Students</span>
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="formSixTable">
                        <thead class="table-light">
                            <tr style="text-align: center;">
                                <th>S/N</th>
                                <th>Index No.</th>
                                <th>Full Name</th>
                                <th>Comb</th>
                                <th>Admission</th>
                                <th>Status</th>
                                <th>Account</th>
                                <th>Lock Info</th>
                                <th>Actions</th>
                             </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students_form_six)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No Form Six students found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students_form_six as $index => $student): 
                                    $is_locked = isStudentAccountLocked($conn, $student['id']);
                                    $lock_info = getStudentLockInfo($conn, $student['id']);
                                    $lock_expiry = getStudentLockExpiry($conn, $student['id']);
                                    
                                    $remaining_minutes = 0;
                                    if ($lock_expiry) {
                                        $expiry = new DateTime($lock_expiry);
                                        $now = new DateTime();
                                        if ($expiry > $now) {
                                            $interval = $now->diff($expiry);
                                            $remaining_minutes = ($interval->h * 60) + $interval->i;
                                        }
                                    }
                                ?>
                                <tr data-lock-status="<?php echo $is_locked ? 'locked' : 'unlocked'; ?>"
                                    data-status="<?php echo $student['status'] ? 'Active' : 'Inactive'; ?>"
                                    data-sex="<?php echo $student['sex']; ?>"
                                    data-combination="<?php echo $student['combination']; ?>">
                                    <td class="text-center"><?php echo $index + 1; ?></td>
                                    <td class="text-center">
                                        <strong><?php echo htmlspecialchars($student['index_number']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <?php if ($student['sex'] == 'Male'): ?>
                                                    <i class="fas fa-male text-primary"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-female" style="color: #e83e8c;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars($student['second_name'] ?? ''); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($student['combination']); ?></span>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $student['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $student['status'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_locked): ?>
                                            <span class="badge bg-danger" title="Locked until <?php echo $lock_expiry; ?>">
                                                <i class="fas fa-lock me-1"></i>Locked
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-unlock me-1"></i>Unlocked
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_locked): ?>
                                            <div class="small">
                                                <span class="text-danger">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo $remaining_minutes; ?> min left
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    Failed: <?php echo $lock_info['failed_login_attempts'] ?? 0; ?>/5
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <div class="small text-muted">
                                                <i class="fas fa-check-circle text-success me-1"></i>
                                                Attempts: <?php echo $lock_info['failed_login_attempts'] ?? 0; ?>/5
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-info view-student" 
                                                    data-bs-toggle="modal" data-bs-target="#viewStudentModal"
                                                    data-student-id="<?php echo $student['id']; ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="register.php?edit=<?php echo $student['id']; ?>" 
                                               class="btn btn-outline-warning"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($is_locked): ?>
                                                <a href="students.php?unlock_account=<?php echo $student['id']; ?>&class=<?php echo urlencode($current_class); ?>" 
                                                   class="btn btn-outline-success"
                                                   title="Unlock Account"
                                                   onclick="return confirm('Unlock this student account?');">
                                                    <i class="fas fa-unlock-alt"></i>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-secondary" disabled title="Account is unlocked">
                                                    <i class="fas fa-lock-open"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-dark reset-password-student" 
                                                    data-id="<?php echo $student['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                    title="Reset Password to Parent Phone">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-dark mark-leaver-student" 
                                                    data-id="<?php echo $student['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                    data-class="<?php echo $student['class']; ?>"
                                                    title="Mark as Graduate">
                                                <i class="fas fa-graduation-cap"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger delete-student" 
                                                    data-id="<?php echo $student['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <a href="students.php?toggle_status=<?php echo $student['id']; ?>&class=<?php echo urlencode($current_class); ?>" 
                                               class="btn btn-outline-<?php echo $student['status'] ? 'secondary' : 'success'; ?> toggle-status-student"
                                               title="<?php echo $student['status'] ? 'Deactivate' : 'Activate'; ?>"
                                               onclick="return confirm('<?php echo $student['status'] ? 'Deactivate' : 'Activate'; ?> this student?');">
                                                <i class="fas fa-power-off"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Student Modal -->
<div class="modal fade" id="viewStudentModal" tabindex="-1" aria-labelledby="viewStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--primary-color); color: var(--white);">
                <h5 class="modal-title" id="viewStudentModalLabel">Student Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="studentDetails">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading student details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Promote Form Six Modal -->
<div class="modal fade" id="promoteFormSixModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-graduation-cap me-2"></i>Promote Students</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-users fa-3x text-warning mb-3"></i>
                <h5 class="mb-3">Promote all Form Five students to Form Six?</h5>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> This action will:
                    <ul class="text-start mt-2 mb-0">
                        <li>Mark all current Form Six students as Graduates</li>
                        <li>Promote all current Form Five students to Form Six</li>
                        <li>Remove dormitory assignments for graduates</li>
                        <li>Return all maintenance items for graduates</li>
                        <li>Regenerate index numbers for all students</li>
                        <li>Cannot be undone automatically</li>
                    </ul>
                </div>
                <p class="text-muted"><small>Form Five students: <?php echo count($students_form_five); ?></small></p>
                <p class="text-muted"><small>Form Six students to graduate: <?php echo count($students_form_six); ?></small></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="students.php?promote_form_six=1" class="btn btn-warning">
                    <i class="fas fa-graduation-cap me-2"></i>Yes, Promote All
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Regenerate Index Modal -->
<div class="modal fade" id="regenerateIndexModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-sync-alt me-2"></i>Regenerate Index Numbers</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-list-ol fa-3x text-info mb-3"></i>
                <h5 class="mb-3">Regenerate all index numbers?</h5>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    This will reorder all students and assign new index numbers.
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="students.php?regenerate_index=1" class="btn btn-info">
                    <i class="fas fa-sync-alt me-2"></i>Yes, Regenerate
                </a>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
// Show SweetAlert2 notifications
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    
    if (successMessage) {
        Swal.fire({
            title: 'Success!',
            text: successMessage.getAttribute('data-message'),
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6',
            timer: 5000,
            timerProgressBar: true,
        });
    }
    
    if (errorMessage) {
        Swal.fire({
            title: 'Error!',
            text: errorMessage.getAttribute('data-message'),
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#d33',
        });
    }
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const formFiveRows = document.querySelectorAll('#formFiveTable tbody tr');
    const formSixRows = document.querySelectorAll('#formSixTable tbody tr');
    
    formFiveRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
    
    formSixRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
});

// Filter functionality
document.getElementById('combinationFilter').addEventListener('change', filterTables);
document.getElementById('statusFilter').addEventListener('change', filterTables);
document.getElementById('sexFilter').addEventListener('change', filterTables);
document.getElementById('lockFilter').addEventListener('change', filterTables);

function filterTables() {
    const combination = document.getElementById('combinationFilter').value;
    const status = document.getElementById('statusFilter').value;
    const sex = document.getElementById('sexFilter').value;
    const lock = document.getElementById('lockFilter').value;
    
    const formFiveRows = document.querySelectorAll('#formFiveTable tbody tr');
    formFiveRows.forEach(row => {
        if (row.cells.length < 9) return;
        
        const rowCombination = row.getAttribute('data-combination') || '';
        const rowStatus = row.getAttribute('data-status') || '';
        const rowSex = row.getAttribute('data-sex') || '';
        const rowLock = row.getAttribute('data-lock-status') || '';
        
        const showCombination = !combination || rowCombination === combination;
        const showStatus = !status || rowStatus === status;
        const showSex = !sex || rowSex === sex;
        const showLock = !lock || rowLock === lock;
        
        row.style.display = (showCombination && showStatus && showSex && showLock) ? '' : 'none';
    });
    
    const formSixRows = document.querySelectorAll('#formSixTable tbody tr');
    formSixRows.forEach(row => {
        if (row.cells.length < 9) return;
        
        const rowCombination = row.getAttribute('data-combination') || '';
        const rowStatus = row.getAttribute('data-status') || '';
        const rowSex = row.getAttribute('data-sex') || '';
        const rowLock = row.getAttribute('data-lock-status') || '';
        
        const showCombination = !combination || rowCombination === combination;
        const showStatus = !status || rowStatus === status;
        const showSex = !sex || rowSex === sex;
        const showLock = !lock || rowLock === lock;
        
        row.style.display = (showCombination && showStatus && showSex && showLock) ? '' : 'none';
    });
}

// Mark as leaver confirmation
document.querySelectorAll('.mark-leaver-student').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        const studentId = this.getAttribute('data-id');
        const studentName = this.getAttribute('data-name');
        const studentClass = this.getAttribute('data-class');
        const isGraduate = studentClass === 'Form Six';
        
        Swal.fire({
            title: isGraduate ? 'Mark as Graduate?' : 'Mark as Leaver?',
            html: `
                <div class="text-center">
                    <i class="fas fa-${isGraduate ? 'graduation-cap' : 'sign-out-alt'} fa-3x text-warning mb-3"></i>
                    <p><strong>${studentName}</strong></p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        ${isGraduate ? 
                            'This will mark the student as graduated from Form Six. The student will be moved to leavers list.' : 
                            'This will mark the student as leaver. The student will be moved to leavers list.'}
                    </div>
                    <div class="alert alert-warning">
                        <small>
                            This will also:<br>
                            1. Remove dormitory assignments<br>
                            2. Return all maintenance items to inventory<br>
                            3. Regenerate index numbers
                        </small>
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: `Yes, mark as ${isGraduate ? 'Graduate' : 'Leaver'}`,
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `students.php?mark_leaver=${studentId}&class=<?php echo urlencode($current_class); ?>`;
            }
        });
    });
});

// Delete confirmation
document.querySelectorAll('.delete-student').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        const studentId = this.getAttribute('data-id');
        const studentName = this.getAttribute('data-name');
        
        Swal.fire({
            title: 'Delete Student?',
            html: `
                <div class="text-center">
                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                    <p><strong>${studentName}</strong></p>
                    <div class="alert alert-danger">
                        <small>
                            This action cannot be undone!<br>
                            All dormitory assignments and maintenance items will be cleaned up.<br>
                            Index numbers will be regenerated.
                        </small>
                    </div>
                </div>
            `,
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete permanently!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `students.php?delete=${studentId}&class=<?php echo urlencode($current_class); ?>`;
            }
        });
    });
});

// Password reset confirmation
document.querySelectorAll('.reset-password-student').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        const studentId = this.getAttribute('data-id');
        const studentName = this.getAttribute('data-name');
        
        Swal.fire({
            title: 'Reset Password?',
            html: `
                <div class="text-center">
                    <i class="fas fa-key fa-3x text-warning mb-3"></i>
                    <p><strong>${studentName}</strong></p>
                    <div class="alert alert-info">
                        Password will be reset to the student's parent phone number.<br>
                        The account will also be unlocked if it was locked.
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, reset password',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `students.php?reset_password=${studentId}&class=<?php echo urlencode($current_class); ?>`;
            }
        });
    });
});

// View student details
document.querySelectorAll('.view-student').forEach(button => {
    button.addEventListener('click', function() {
        const studentId = this.getAttribute('data-student-id');
        
        document.getElementById('studentDetails').innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading student details...</p>
            </div>
        `;
        
        fetch(`get_student.php?id=${studentId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('studentDetails').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('studentDetails').innerHTML = 
                    '<div class="alert alert-danger">Error loading student details.</div>';
            });
    });
});

// Status Toggle with SweetAlert2 Confirmation
document.querySelectorAll('.toggle-status-btn').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        
        const studentId = this.getAttribute('data-id');
        const studentName = this.getAttribute('data-name');
        const currentStatus = this.getAttribute('data-status');
        const currentClass = this.getAttribute('data-class') || '';
        
        const isActive = currentStatus === 'Active';
        const action = isActive ? 'Deactivate' : 'Activate';
        const newStatus = isActive ? 'Inactive' : 'Active';
        
        Swal.fire({
            title: `${action} Student?`,
            html: `
                <div class="text-center">
                    <i class="fas fa-power-off fa-3x text-warning mb-3"></i>
                    <p><strong>${studentName}</strong></p>
                    
                    ${isActive ? `
                    <div class="alert alert-warning mt-2">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <small>Deactivating will also return any assigned maintenance items to inventory.</small>
                    </div>
                    ` : ''}
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: isActive ? '#d33' : '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Yes, ${action}`,
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Processing...',
                    text: `Please wait while we ${action.toLowerCase()} the student.`,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Perform the status toggle
                window.location.href = `students.php?toggle_status=${studentId}&class=${encodeURIComponent(currentClass)}`;
            }
        });
    });
});

// Promote Form Five modal
document.getElementById('promoteFormSixBtn')?.addEventListener('click', function(e) {
    e.preventDefault();
    new bootstrap.Modal(document.getElementById('promoteFormSixModal')).show();
});

document.getElementById('promoteFormSixBtnMobile')?.addEventListener('click', function(e) {
    e.preventDefault();
    new bootstrap.Modal(document.getElementById('promoteFormSixModal')).show();
});

// Regenerate index modal
document.getElementById('regenerateIndexBtn')?.addEventListener('click', function(e) {
    e.preventDefault();
    new bootstrap.Modal(document.getElementById('regenerateIndexModal')).show();
});

document.getElementById('regenerateIndexBtnMobile')?.addEventListener('click', function(e) {
    e.preventDefault();
    new bootstrap.Modal(document.getElementById('regenerateIndexModal')).show();
});

// Auto-refresh every 5 minutes
setTimeout(function() {
    location.reload();
}, 300000);
</script>

<?php include '../controller/footer.php'; ?>