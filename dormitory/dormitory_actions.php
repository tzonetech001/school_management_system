<?php
// dormitory_actions.php - Handle all dormitory CRUD operations
session_start();
require_once '../controller/db_connect.php';

$admin_id = $_SESSION['admin_id'] ?? 0;
$current_school_id = $_SESSION['school_id'] ?? 0;

// If school_id not in session, get it from admin record
if ($current_school_id == 0 && $admin_id > 0) {
    $school_sql = "SELECT school_id FROM admins WHERE id = ?";
    $school_stmt = $conn->prepare($school_sql);
    $school_stmt->bind_param("i", $admin_id);
    $school_stmt->execute();
    $school_result = $school_stmt->get_result();
    if ($school_row = $school_result->fetch_assoc()) {
        $current_school_id = $school_row['school_id'];
        $_SESSION['school_id'] = $current_school_id;
    }
    $school_stmt->close();
}

// Check if user has permission
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
    if ($role_id == 1 || $role_id == 2 || $role_id == 7) { // Head Master, Second Master, Dormitory Teacher
        $has_permission = true;
        break;
    }
}

// Also check if user is super admin
$is_super_admin = isset($_SESSION['super_admin_id']);

if (!$has_permission && !$is_super_admin) {
    $_SESSION['error'] = "You don't have permission to perform this action.";
    header("Location: ../404.php");
    exit();
}

// Get school_id from POST or use session
$school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : $current_school_id;

// ==================== ADD DORMITORY ====================
if (isset($_POST['add_dormitory'])) {
    $dorm_name = mysqli_real_escape_string($conn, trim($_POST['dorm_name']));
    $dorm_type = mysqli_real_escape_string($conn, $_POST['dorm_type']);
    $rooms_count = intval($_POST['rooms_count']);
    $capacity_per_room = intval($_POST['capacity_per_room']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    
    // Validate
    if (empty($dorm_name) || empty($dorm_type) || $rooms_count < 1 || $capacity_per_room < 1) {
        $_SESSION['error'] = "All fields are required. Please fill all fields.";
        header("Location: dormitory.php");
        exit();
    }
    
    // Check if dormitory name exists in this school
    $check_sql = "SELECT id FROM dormitories WHERE dorm_name = ? AND school_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $dorm_name, $school_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error'] = "Dormitory '$dorm_name' already exists in this school.";
        header("Location: dormitory.php");
        exit();
    }
    $check_stmt->close();
    
    // Calculate total capacity
    $total_capacity = $rooms_count * $capacity_per_room;
    
    // Insert dormitory
    $insert_sql = "INSERT INTO dormitories (dorm_name, dorm_type, rooms_count, capacity_per_room, total_capacity, description, status, school_id) 
                   VALUES (?, ?, ?, ?, ?, ?, 'Active', ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ssiiisi", $dorm_name, $dorm_type, $rooms_count, $capacity_per_room, $total_capacity, $description, $school_id);
    
    if ($insert_stmt->execute()) {
        $dormitory_id = $insert_stmt->insert_id;
        
        // Create rooms for this dormitory
        $rooms_created = 0;
        $prefix = 'A';
        $room_counter = 1;
        
        for ($i = 1; $i <= $rooms_count; $i++) {
            // Generate room label (A1, A2, ... A10, B1, B2, ...)
            if ($i > 10) {
                $current_prefix = chr(ord($prefix) + floor(($i - 1) / 10));
                $room_number = ($i - 1) % 10 + 1;
                $room_label = $current_prefix . $room_number;
            } else {
                $room_label = $prefix . $i;
            }
            
            $room_sql = "INSERT INTO dormitory_rooms (dormitory_id, room_number, room_label, capacity, current_occupancy, status, school_id) 
                         VALUES (?, ?, ?, ?, 0, 'Available', ?)";
            $room_stmt = $conn->prepare($room_sql);
            $room_stmt->bind_param("issii", $dormitory_id, $room_label, $room_label, $capacity_per_room, $school_id);
            
            if ($room_stmt->execute()) {
                $rooms_created++;
            }
            $room_stmt->close();
        }
        
        $_SESSION['success'] = "Dormitory '$dorm_name' added successfully with $rooms_created rooms.";
    } else {
        $_SESSION['error'] = "Failed to add dormitory: " . $conn->error;
    }
    $insert_stmt->close();
    
    header("Location: dormitory.php");
    exit();
}

// ==================== EDIT DORMITORY ====================
if (isset($_POST['edit_dormitory'])) {
    $dormitory_id = intval($_POST['dormitory_id']);
    $dorm_name = mysqli_real_escape_string($conn, trim($_POST['dorm_name']));
    $rooms_count = intval($_POST['rooms_count']);
    $capacity_per_room = intval($_POST['capacity_per_room']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Active');
    $school_id = intval($_POST['school_id'] ?? $current_school_id);
    
    // Validate
    if (empty($dorm_name) || $rooms_count < 1 || $capacity_per_room < 1) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: dormitory.php");
        exit();
    }
    
    // Check if dormitory name exists (excluding current)
    $check_sql = "SELECT id FROM dormitories WHERE dorm_name = ? AND school_id = ? AND id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("sii", $dorm_name, $school_id, $dormitory_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error'] = "Dormitory '$dorm_name' already exists in this school.";
        header("Location: dormitory.php");
        exit();
    }
    $check_stmt->close();
    
    // Calculate total capacity
    $total_capacity = $rooms_count * $capacity_per_room;
    
    // Update dormitory
    $update_sql = "UPDATE dormitories 
                   SET dorm_name = ?, rooms_count = ?, capacity_per_room = ?, 
                       total_capacity = ?, description = ?, status = ?
                   WHERE id = ? AND school_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("siiissii", $dorm_name, $rooms_count, $capacity_per_room, 
                             $total_capacity, $description, $status, $dormitory_id, $school_id);
    
    if ($update_stmt->execute()) {
        // Update room capacities
        $update_rooms_sql = "UPDATE dormitory_rooms SET capacity = ? WHERE dormitory_id = ? AND school_id = ?";
        $update_rooms_stmt = $conn->prepare($update_rooms_sql);
        $update_rooms_stmt->bind_param("iii", $capacity_per_room, $dormitory_id, $school_id);
        $update_rooms_stmt->execute();
        $update_rooms_stmt->close();
        
        // Ensure room statuses are correct
        $update_status_sql = "UPDATE dormitory_rooms 
                              SET status = CASE 
                                  WHEN current_occupancy >= capacity THEN 'Full'
                                  ELSE 'Available'
                              END
                              WHERE dormitory_id = ? AND school_id = ?";
        $update_status_stmt = $conn->prepare($update_status_sql);
        $update_status_stmt->bind_param("ii", $dormitory_id, $school_id);
        $update_status_stmt->execute();
        $update_status_stmt->close();
        
        $_SESSION['success'] = "Dormitory '$dorm_name' updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update dormitory: " . $conn->error;
    }
    $update_stmt->close();
    
    header("Location: dormitory.php");
    exit();
}

// ==================== DELETE DORMITORY ====================
if (isset($_GET['delete_dormitory'])) {
    $dormitory_id = intval($_GET['delete_dormitory']);
    $school_id = intval($_GET['school_id'] ?? $current_school_id);
    
    // Check if dormitory has active students
    $check_sql = "SELECT COUNT(*) as count FROM student_dormitory 
                  WHERE dormitory_id = ? AND status = 'Active'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $dormitory_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if ($check_row['count'] > 0) {
        $_SESSION['error'] = "Cannot delete dormitory. It has " . $check_row['count'] . " active students assigned.";
        header("Location: dormitory.php");
        exit();
    }
    
    // Delete rooms first
    $delete_rooms_sql = "DELETE FROM dormitory_rooms WHERE dormitory_id = ? AND school_id = ?";
    $delete_rooms_stmt = $conn->prepare($delete_rooms_sql);
    $delete_rooms_stmt->bind_param("ii", $dormitory_id, $school_id);
    $delete_rooms_stmt->execute();
    $delete_rooms_stmt->close();
    
    // Delete dormitory
    $delete_sql = "DELETE FROM dormitories WHERE id = ? AND school_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $dormitory_id, $school_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success'] = "Dormitory deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete dormitory: " . $conn->error;
    }
    $delete_stmt->close();
    
    header("Location: dormitory.php");
    exit();
}

// ==================== UPDATE ROOM STATUS ====================
if (isset($_POST['update_room_status'])) {
    $room_id = intval($_POST['room_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $school_id = intval($_POST['school_id'] ?? $current_school_id);
    
    $update_sql = "UPDATE dormitory_rooms SET status = ? WHERE id = ? AND school_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sii", $status, $room_id, $school_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Room status updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update room status: " . $conn->error;
    }
    $update_stmt->close();
    
    header("Location: dormitory.php");
    exit();
}

// If no action specified, redirect back
header("Location: dormitory.php");
exit();
?>