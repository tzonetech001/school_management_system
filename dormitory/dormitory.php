<?php
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// current_school_id is provided by controller/db_connect.php (null for super-admin)
$current_school_id = isset($current_school_id) ? $current_school_id : null;

// If school_id not set, get from session or admin
if ($current_school_id === null) {
    $current_school_id = $_SESSION['school_id'] ?? 0;
    
    if ($current_school_id == 0) {
        $admin_id = $_SESSION['admin_id'] ?? 0;
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
}

// Check if user has permission (Head Master, Second Master, or Dormitory Teacher)
$admin_id = $_SESSION['admin_id'] ?? 0;
$is_super_admin = isset($_SESSION['super_admin_id']);

// Get current user's roles
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}
$stmt->close();

// Check if user has Head Master (1), Second Master (2), or Dormitory Teacher (7) role
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 7) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission && !$is_super_admin) {
    $_SESSION['error'] = "You don't have permission to view dormitory management.";
    header("Location: ../404.php");
    exit();
}

// Get all roles from database
$roles = [];
$roles_sql = "SELECT * FROM admin_roles WHERE role_name != 'Super Admin' ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_sql);
if ($roles_result && mysqli_num_rows($roles_result) > 0) {
    while ($row = mysqli_fetch_assoc($roles_result)) {
        $roles[] = $row;
    }
}

// ==================== FUNCTIONS ====================

/**
 * Update room occupancy
 */
function updateRoomOccupancy($conn, $room_id) {
    global $current_school_id;
    $school_cond = ($current_school_id !== null) ? " AND school_id = " . intval($current_school_id) : "";
    $update_sql = "UPDATE dormitory_rooms 
                   SET current_occupancy = (
                       SELECT COUNT(*) FROM student_dormitory 
                       WHERE room_id = $room_id AND status = 'Active' $school_cond
                   )
                   WHERE id = $room_id";
    return mysqli_query($conn, $update_sql);
}

/**
 * Update dormitory occupancy
 */
function updateDormitoryOccupancy($conn, $dormitory_id) {
    global $current_school_id;
    $school_cond = ($current_school_id !== null) ? " AND sd.school_id = " . intval($current_school_id) : "";
    $update_sql = "UPDATE dormitories 
                   SET current_occupancy = (
                       SELECT COUNT(DISTINCT sd.id) 
                       FROM student_dormitory sd
                       JOIN dormitory_rooms dr ON sd.room_id = dr.id
                       WHERE dr.dormitory_id = $dormitory_id AND sd.status = 'Active' $school_cond
                   )
                   WHERE id = $dormitory_id";
    return mysqli_query($conn, $update_sql);
}

/**
 * Remove leavers/graduated students from dormitories
 */
function removeLeaversFromDormitories($conn) {
    $removed_count = 0;
    global $current_school_id;
    
    $school_cond = "";
    if ($current_school_id !== null) {
        $school_cond = " AND sd.school_id = " . intval($current_school_id);
    }

    $leavers_sql = "SELECT sd.id as assignment_id, sd.room_id, sd.dormitory_id, 
                           s.first_name, s.last_name, s.index_number,
                           CONCAT(s.first_name, ' ', s.last_name) as student_name
                    FROM student_dormitory sd
                    JOIN students s ON sd.student_id = s.id
                    JOIN dormitories d ON sd.dormitory_id = d.id
                    WHERE sd.status = 'Active' $school_cond
                    AND (s.is_leaver = TRUE 
                         OR s.class IN ('Leavers', 'Graduated') 
                         OR s.graduation_status IN ('Graduated', 'Left'))";
    
    $leavers_result = mysqli_query($conn, $leavers_sql);
    
    if ($leavers_result && mysqli_num_rows($leavers_result) > 0) {
        while ($row = mysqli_fetch_assoc($leavers_result)) {
            $assignment_id = $row['assignment_id'];
            $room_id = $row['room_id'];
            $dormitory_id = $row['dormitory_id'];
            
            $update_sql = "UPDATE student_dormitory 
                           SET status = 'Left', 
                               notes = CONCAT(COALESCE(notes, ''), ' | Auto-removed: Student is leaver/graduated'),
                               updated_at = CURRENT_TIMESTAMP
                           WHERE id = $assignment_id";
            
            if (mysqli_query($conn, $update_sql)) {
                updateRoomOccupancy($conn, $room_id);
                updateDormitoryOccupancy($conn, $dormitory_id);
                $removed_count++;
            }
        }
        
        if ($removed_count > 0) {
            $_SESSION['info'] = "Automatically removed $removed_count leaver/graduated students from dormitories.";
        }
    }
    
    return $removed_count;
}

// Run automatic removal check
removeLeaversFromDormitories($conn);

// Build school filter
$school_filter = "";
if ($current_school_id !== null) {
    $school_filter = " AND s.school_id = " . intval($current_school_id);
}

// Get all active students (Form Five and Six, not leavers/graduated)
$students_sql = "SELECT s.* FROM students s 
                WHERE s.is_leaver = FALSE 
                AND s.status = 1
                AND s.class IN ('Form Five', 'Form Six')
                AND s.graduation_status NOT IN ('Graduated', 'Left') $school_filter
                ORDER BY s.sex, s.class, s.combination, s.first_name, s.last_name";

$students_result = mysqli_query($conn, $students_sql);
$all_students = [];
$male_students = [];
$female_students = [];
if ($students_result && mysqli_num_rows($students_result) > 0) {
    while ($row = mysqli_fetch_assoc($students_result)) {
        $all_students[] = $row;
        if ($row['sex'] == 'Male') {
            $male_students[] = $row;
        } else {
            $female_students[] = $row;
        }
    }
}

// Get all dormitory assignments for active students
$sd_school_filter = "";
if ($current_school_id !== null) {
    $sd_school_filter = " AND sd.school_id = " . intval($current_school_id);
}

$assignments_sql = "SELECT sd.*, s.first_name, s.last_name, s.index_number, s.class, s.combination, s.sex,
                   s.is_leaver, s.graduation_status,
                   d.dorm_name, d.dorm_type, dr.room_number, dr.room_label, 
                   dr.capacity as room_capacity,
                   dr.current_occupancy as room_occupancy,
                   dr.status as room_status
                   FROM student_dormitory sd
                   JOIN students s ON sd.student_id = s.id
                   JOIN dormitories d ON sd.dormitory_id = d.id
                   JOIN dormitory_rooms dr ON sd.room_id = dr.id
                   WHERE sd.status = 'Active'
                   AND s.is_leaver = FALSE
                   AND s.class IN ('Form Five', 'Form Six')
                   AND s.graduation_status NOT IN ('Graduated', 'Left')
                   $sd_school_filter
                   ORDER BY s.sex, d.dorm_name, dr.room_number, s.first_name";

$assignments_result = mysqli_query($conn, $assignments_sql);
$all_assignments = [];
$male_assignments = [];
$female_assignments = [];
if ($assignments_result && mysqli_num_rows($assignments_result) > 0) {
    while ($row = mysqli_fetch_assoc($assignments_result)) {
        $all_assignments[] = $row;
        if ($row['sex'] == 'Male') {
            $male_assignments[] = $row;
        } else {
            $female_assignments[] = $row;
        }
    }
}

// Get all dormitories
$dorm_school_filter = "";
if ($current_school_id !== null) {
    $dorm_school_filter = " AND school_id = " . intval($current_school_id);
}
$dormitories_sql = "SELECT * FROM dormitories WHERE status IN ('Active', 'Full') $dorm_school_filter ORDER BY dorm_type, dorm_name";
$dormitories_result = mysqli_query($conn, $dormitories_sql);
$dormitories = [];
$male_dormitories = [];
$female_dormitories = [];
if ($dormitories_result && mysqli_num_rows($dormitories_result) > 0) {
    while ($row = mysqli_fetch_assoc($dormitories_result)) {
        $dormitories[] = $row;
        if ($row['dorm_type'] == 'Male') {
            $male_dormitories[] = $row;
        } else {
            $female_dormitories[] = $row;
        }
    }
}

// Get ALL dormitories for management (including inactive)
$all_dorms_sql = "SELECT * FROM dormitories";
if ($current_school_id !== null) {
    $all_dorms_sql .= " WHERE school_id = " . intval($current_school_id);
}
$all_dorms_sql .= " ORDER BY dorm_type, dorm_name";
$all_dorms_result = mysqli_query($conn, $all_dorms_sql);
$all_dormitories = [];
while ($row = mysqli_fetch_assoc($all_dorms_result)) {
    $all_dormitories[] = $row;
}

// ==================== HANDLE ASSIGN STUDENT ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_student'])) {
    $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
    $dormitory_id = mysqli_real_escape_string($conn, $_POST['dormitory_id']);
    $room_id = mysqli_real_escape_string($conn, $_POST['room_id']);
    $bed_number = mysqli_real_escape_string($conn, $_POST['bed_number']);
    $assigned_by = $_SESSION['admin_id'];
    
    // Get student details
    $student_school_cond = "";
    if ($current_school_id !== null) {
        $student_school_cond = " AND school_id = " . intval($current_school_id);
    }
    $student_sql = "SELECT CONCAT(first_name, ' ', last_name) as student_name, sex, school_id 
                   FROM students WHERE id = $student_id $student_school_cond";
    $student_result = mysqli_query($conn, $student_sql);
    $student_data = mysqli_fetch_assoc($student_result);
    $student_name = $student_data['student_name'] ?? 'Unknown';
    $student_sex = $student_data['sex'] ?? '';
    $student_school_id = $student_data['school_id'] ?? $current_school_id;
    
    try {
        // Check if student already has active assignment
        $check_sql = "SELECT id FROM student_dormitory 
                     WHERE student_id = $student_id AND status = 'Active'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            throw new Exception("Student already has an active dormitory assignment!");
        }
        
        // Check if room has capacity
        $room_check_sql = "SELECT capacity, current_occupancy FROM dormitory_rooms WHERE id = $room_id";
        $room_check_result = mysqli_query($conn, $room_check_sql);
        $room_data = mysqli_fetch_assoc($room_check_result);
        
        if ($room_data && $room_data['current_occupancy'] >= $room_data['capacity']) {
            throw new Exception("Room is already at full capacity!");
        }
        
        // Try using stored procedure first
        $procedure_sql = "CALL assign_student_to_dormitory(
            $student_id, 
            $dormitory_id, 
            $room_id, 
            '$bed_number', 
            $assigned_by, 
            'Assigned via dormitory.php'
        )";
        
        $procedure_worked = false;
        if (mysqli_multi_query($conn, $procedure_sql)) {
            $proc_result = null;
            if ($result = mysqli_store_result($conn)) {
                $proc_result = mysqli_fetch_assoc($result);
                mysqli_free_result($result);
            }
            while (mysqli_more_results($conn) && mysqli_next_result($conn));
            
            if (isset($proc_result['status']) && $proc_result['status'] == 'SUCCESS') {
                $procedure_worked = true;
                $_SESSION['success'] = $proc_result['message'];
            }
        }
        
        // If procedure failed, use direct insert
        if (!$procedure_worked) {
            // Insert assignment directly
            $insert_sql = "INSERT INTO student_dormitory (student_id, dormitory_id, room_id, bed_number, assigned_by, status, notes, school_id) 
                           VALUES (?, ?, ?, ?, ?, 'Active', 'Assigned via dormitory.php', ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iiisii", $student_id, $dormitory_id, $room_id, $bed_number, $assigned_by, $student_school_id);
            
            if ($insert_stmt->execute()) {
                // Update occupancies
                updateRoomOccupancy($conn, $room_id);
                updateDormitoryOccupancy($conn, $dormitory_id);
                $_SESSION['success'] = "Student $student_name assigned to dormitory successfully!";
            } else {
                throw new Exception("Failed to assign: " . $insert_stmt->error);
            }
            $insert_stmt->close();
        }
        
        // Refresh data
        header("Location: dormitory.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: dormitory.php");
        exit();
    }
}

// ==================== HANDLE UPDATE ASSIGNMENT ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_assignment'])) {
    $assignment_id = mysqli_real_escape_string($conn, $_POST['assignment_id']);
    $new_dormitory_id = mysqli_real_escape_string($conn, $_POST['dormitory_id']);
    $new_room_id = mysqli_real_escape_string($conn, $_POST['room_id']);
    $new_bed_number = mysqli_real_escape_string($conn, $_POST['bed_number']);
    $updated_by = $_SESSION['admin_id'];
    
    // Get old room and dormitory info
    $old_info_sql = "SELECT room_id, dormitory_id, student_id FROM student_dormitory WHERE id = $assignment_id";
    $old_info_result = mysqli_query($conn, $old_info_sql);
    $old_info = mysqli_fetch_assoc($old_info_result);
    $old_room_id = $old_info['room_id'];
    $old_dormitory_id = $old_info['dormitory_id'];
    $student_id = $old_info['student_id'];
    
    try {
        // Check if new room has capacity
        $room_check_sql = "SELECT capacity, current_occupancy FROM dormitory_rooms WHERE id = $new_room_id";
        $room_check_result = mysqli_query($conn, $room_check_sql);
        $room_data = mysqli_fetch_assoc($room_check_result);
        
        if ($room_data && $room_data['current_occupancy'] >= $room_data['capacity']) {
            throw new Exception("New room is already at full capacity!");
        }
        
        // Try using stored procedure first
        $procedure_sql = "CALL update_student_dormitory(
            $assignment_id,
            $new_dormitory_id,
            $new_room_id,
            '$new_bed_number',
            $updated_by,
            'Updated via dormitory.php'
        )";
        
        $procedure_worked = false;
        if (mysqli_multi_query($conn, $procedure_sql)) {
            $proc_result = null;
            if ($result = mysqli_store_result($conn)) {
                $proc_result = mysqli_fetch_assoc($result);
                mysqli_free_result($result);
            }
            while (mysqli_more_results($conn) && mysqli_next_result($conn));
            
            if (isset($proc_result['status']) && $proc_result['status'] == 'SUCCESS') {
                $procedure_worked = true;
                $_SESSION['success'] = $proc_result['message'];
            }
        }
        
        // If procedure failed, use direct update
        if (!$procedure_worked) {
            // Update assignment
            $update_sql = "UPDATE student_dormitory 
                           SET dormitory_id = ?, room_id = ?, bed_number = ?, 
                               notes = CONCAT(COALESCE(notes, ''), ' | Updated via dormitory.php'), 
                               updated_at = CURRENT_TIMESTAMP
                           WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("iisi", $new_dormitory_id, $new_room_id, $new_bed_number, $assignment_id);
            
            if ($update_stmt->execute()) {
                // Update occupancies
                updateRoomOccupancy($conn, $old_room_id);
                updateDormitoryOccupancy($conn, $old_dormitory_id);
                updateRoomOccupancy($conn, $new_room_id);
                updateDormitoryOccupancy($conn, $new_dormitory_id);
                
                $_SESSION['success'] = "Assignment updated successfully!";
            } else {
                throw new Exception("Failed to update: " . $update_stmt->error);
            }
            $update_stmt->close();
        }
        
        header("Location: dormitory.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: dormitory.php");
        exit();
    }
}

// ==================== HANDLE REMOVE ASSIGNMENT ====================
if (isset($_GET['remove_assignment'])) {
    $assignment_id = mysqli_real_escape_string($conn, $_GET['remove_assignment']);
    
    try {
        // Get assignment details
        $get_sql = "SELECT room_id, dormitory_id FROM student_dormitory WHERE id = $assignment_id";
        $get_result = mysqli_query($conn, $get_sql);
        $assignment_data = mysqli_fetch_assoc($get_result);
        
        if (!$assignment_data) {
            throw new Exception("Assignment not found.");
        }
        
        $room_id = $assignment_data['room_id'];
        $dormitory_id = $assignment_data['dormitory_id'];
        
        // Try using stored procedure first
        $procedure_sql = "CALL remove_dormitory_assignment($assignment_id, 'Removed by admin via dormitory.php')";
        
        $procedure_worked = false;
        if (mysqli_multi_query($conn, $procedure_sql)) {
            $proc_result = null;
            if ($result = mysqli_store_result($conn)) {
                $proc_result = mysqli_fetch_assoc($result);
                mysqli_free_result($result);
            }
            while (mysqli_more_results($conn) && mysqli_next_result($conn));
            
            if (isset($proc_result['status']) && $proc_result['status'] == 'SUCCESS') {
                $procedure_worked = true;
                $_SESSION['success'] = $proc_result['message'];
            }
        }
        
        // If procedure failed, use direct update
        if (!$procedure_worked) {
            $update_sql = "UPDATE student_dormitory 
                           SET status = 'Left', 
                               notes = CONCAT(COALESCE(notes, ''), ' | Removed by admin via dormitory.php'),
                               updated_at = CURRENT_TIMESTAMP
                           WHERE id = $assignment_id";
            
            if (mysqli_query($conn, $update_sql)) {
                updateRoomOccupancy($conn, $room_id);
                updateDormitoryOccupancy($conn, $dormitory_id);
                $_SESSION['success'] = "Assignment removed successfully!";
            } else {
                throw new Exception("Failed to remove: " . mysqli_error($conn));
            }
        }
        
        header("Location: dormitory.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: dormitory.php");
        exit();
    }
}

// ==================== HANDLE REMOVE STUDENT DORMITORY ====================
if (isset($_GET['remove_student_dormitory'])) {
    $student_id = mysqli_real_escape_string($conn, $_GET['remove_student_dormitory']);
    
    try {
        $get_assignments_sql = "SELECT id, room_id, dormitory_id FROM student_dormitory 
                               WHERE student_id = $student_id AND status = 'Active'";
        $assignments_result = mysqli_query($conn, $get_assignments_sql);
        
        $removed_count = 0;
        
        while ($assignment = mysqli_fetch_assoc($assignments_result)) {
            $assignment_id = $assignment['id'];
            $room_id = $assignment['room_id'];
            $dormitory_id = $assignment['dormitory_id'];
            
            $update_sql = "UPDATE student_dormitory 
                           SET status = 'Left', 
                               notes = CONCAT(COALESCE(notes, ''), ' | Auto-removed: Student deleted/marked as leaver'),
                               updated_at = CURRENT_TIMESTAMP
                           WHERE id = $assignment_id";
            
            if (mysqli_query($conn, $update_sql)) {
                updateRoomOccupancy($conn, $room_id);
                updateDormitoryOccupancy($conn, $dormitory_id);
                $removed_count++;
            }
        }
        
        if ($removed_count > 0) {
            $_SESSION['info'] = "Removed $removed_count dormitory assignments for student.";
        } else {
            $_SESSION['info'] = "No dormitory assignments found for student.";
        }
        
        header("Location: dormitory.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: dormitory.php");
        exit();
    }
}

// ==================== HANDLE ADD DORMITORY (via modal) ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_dormitory_modal'])) {
    $dorm_name = mysqli_real_escape_string($conn, trim($_POST['dorm_name']));
    $dorm_type = mysqli_real_escape_string($conn, $_POST['dorm_type']);
    $rooms_count = intval($_POST['rooms_count']);
    $capacity_per_room = intval($_POST['capacity_per_room']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $school_id = $current_school_id;
    
    if (empty($dorm_name) || empty($dorm_type) || $rooms_count < 1 || $capacity_per_room < 1) {
        $_SESSION['error'] = "All fields are required.";
    } else {
        $check_sql = "SELECT id FROM dormitories WHERE dorm_name = ? AND school_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $dorm_name, $school_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error'] = "Dormitory '$dorm_name' already exists.";
        } else {
            $total_capacity = $rooms_count * $capacity_per_room;
            
            $insert_sql = "INSERT INTO dormitories (dorm_name, dorm_type, rooms_count, capacity_per_room, total_capacity, description, status, school_id) 
                           VALUES (?, ?, ?, ?, ?, ?, 'Active', ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssiiisi", $dorm_name, $dorm_type, $rooms_count, $capacity_per_room, $total_capacity, $description, $school_id);
            
            if ($insert_stmt->execute()) {
                $dormitory_id = $insert_stmt->insert_id;
                $rooms_created = 0;
                
                for ($i = 1; $i <= $rooms_count; $i++) {
                    $letter = ($i <= 10) ? 'A' : chr(ord('A') + floor(($i - 1) / 10));
                    $number = ($i <= 10) ? $i : (($i - 1) % 10) + 1;
                    $room_label = $letter . $number;
                    
                    $room_sql = "INSERT INTO dormitory_rooms (dormitory_id, room_number, room_label, capacity, school_id) 
                                 VALUES (?, ?, ?, ?, ?)";
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
        }
        $check_stmt->close();
    }
    
    header("Location: dormitory.php");
    exit();
}

// ==================== HANDLE EDIT DORMITORY (via modal) ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_dormitory_modal'])) {
    $dormitory_id = intval($_POST['dormitory_id']);
    $dorm_name = mysqli_real_escape_string($conn, trim($_POST['dorm_name']));
    $rooms_count = intval($_POST['rooms_count']);
    $capacity_per_room = intval($_POST['capacity_per_room']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Active');
    $school_id = $current_school_id;
    
    if (empty($dorm_name) || $rooms_count < 1 || $capacity_per_room < 1) {
        $_SESSION['error'] = "All fields are required.";
    } else {
        $check_sql = "SELECT id FROM dormitories WHERE dorm_name = ? AND school_id = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sii", $dorm_name, $school_id, $dormitory_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error'] = "Dormitory '$dorm_name' already exists.";
        } else {
            $total_capacity = $rooms_count * $capacity_per_room;
            
            $update_sql = "UPDATE dormitories 
                           SET dorm_name = ?, rooms_count = ?, capacity_per_room = ?,
                               total_capacity = ?, description = ?, status = ?,
                               updated_at = NOW()
                           WHERE id = ? AND school_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("siiissii", 
                $dorm_name, $rooms_count, $capacity_per_room,
                $total_capacity, $description, $status,
                $dormitory_id, $school_id
            );
            
            if ($update_stmt->execute()) {
                // Update room capacities
                $update_rooms_sql = "UPDATE dormitory_rooms 
                                     SET capacity = ?, updated_at = NOW() 
                                     WHERE dormitory_id = ? AND school_id = ?";
                $update_rooms_stmt = $conn->prepare($update_rooms_sql);
                $update_rooms_stmt->bind_param("iii", $capacity_per_room, $dormitory_id, $school_id);
                $update_rooms_stmt->execute();
                $update_rooms_stmt->close();
                
                $_SESSION['success'] = "Dormitory '$dorm_name' updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update dormitory: " . $conn->error;
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
    
    header("Location: dormitory.php");
    exit();
}

// ==================== HANDLE DELETE DORMITORY ====================
if (isset($_GET['delete_dormitory'])) {
    $dormitory_id = intval($_GET['delete_dormitory']);
    
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
    } else {
        // Delete rooms first
        $del_rooms_sql = "DELETE FROM dormitory_rooms WHERE dormitory_id = ?";
        $del_rooms_stmt = $conn->prepare($del_rooms_sql);
        $del_rooms_stmt->bind_param("i", $dormitory_id);
        $del_rooms_stmt->execute();
        $del_rooms_stmt->close();
        
        // Delete dormitory
        $del_sql = "DELETE FROM dormitories WHERE id = ? AND school_id = ?";
        $del_stmt = $conn->prepare($del_sql);
        $del_stmt->bind_param("ii", $dormitory_id, $current_school_id);
        
        if ($del_stmt->execute()) {
            $_SESSION['success'] = "Dormitory deleted successfully.";
        } else {
            $_SESSION['error'] = "Failed to delete dormitory.";
        }
        $del_stmt->close();
    }
    
    header("Location: dormitory.php");
    exit();
}

// Calculate statistics
$total_students = count($all_students);
$total_assigned = count($all_assignments);
$total_male_students = count($male_students);
$total_female_students = count($female_students);
$total_male_assigned = count($male_assignments);
$total_female_assigned = count($female_assignments);

// Calculate available beds
$male_total_beds = 0;
$male_occupied_beds = 0;
foreach ($male_dormitories as $dorm) {
    $male_total_beds += $dorm['total_capacity'];
    $male_occupied_beds += $dorm['current_occupancy'];
}
$male_available_beds = max(0, $male_total_beds - $male_occupied_beds);

$female_total_beds = 0;
$female_occupied_beds = 0;
foreach ($female_dormitories as $dorm) {
    $female_total_beds += $dorm['total_capacity'];
    $female_occupied_beds += $dorm['current_occupancy'];
}
$female_available_beds = max(0, $female_total_beds - $female_occupied_beds);

$total_beds = $male_total_beds + $female_total_beds;
$total_occupied_beds = $male_occupied_beds + $female_occupied_beds;
$total_available_beds = $male_available_beds + $female_available_beds;
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Dormitory Management System</h2>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignDormitoryModal">
                    <i class="fas fa-bed me-2"></i>Assign Dormitory
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addDormitoryModal">
                    <i class="fas fa-plus-circle me-2"></i>Add Dormitory
                </button>
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
        
        <?php if (isset($_SESSION['info'])): ?>
            <div id="infoMessage" data-message="<?php echo htmlspecialchars($_SESSION['info']); ?>"></div>
            <?php unset($_SESSION['info']); ?>
        <?php endif; ?>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-12 mb-3">
                <div class="card">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Overall Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-sm-6 text-center">
                                <h3 class="text-primary"><?php echo $total_students; ?></h3>
                                <p class="text-muted">Total Students</p>
                                <small class="text-muted">Form Five & Six only</small>
                            </div>
                            <div class="col-md-3 col-sm-6 text-center">
                                <h3 class="text-success"><?php echo $total_assigned; ?></h3>
                                <p class="text-muted">Assigned to Dormitories</p>
                                <small class="text-muted">Active assignments</small>
                            </div>
                            <div class="col-md-3 col-sm-6 text-center">
                                <h3 class="text-warning"><?php echo max(0, $total_students - $total_assigned); ?></h3>
                                <p class="text-muted">Unassigned Students</p>
                                <small class="text-muted">Eligible for dormitory</small>
                            </div>
                            <div class="col-md-3 col-sm-6 text-center">
                                <h3 class="text-info"><?php echo $total_available_beds; ?></h3>
                                <p class="text-muted">Available Beds</p>
                                <small class="text-muted">Across all dormitories</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gender Statistics -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header" style="background-color: #007bff; color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-male me-2"></i>Male Dormitories
                            <span class="badge bg-light text-dark float-end">
                                <?php echo count($male_dormitories); ?> Dormitories
                            </span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <h4 class="text-primary"><?php echo $total_male_students; ?></h4>
                                <small>Total Male Students</small>
                            </div>
                            <div class="col-md-4 text-center">
                                <h4 class="text-success"><?php echo $total_male_assigned; ?></h4>
                                <small>Assigned</small>
                            </div>
                            <div class="col-md-4 text-center">
                                <h4 class="text-info"><?php echo $male_available_beds; ?></h4>
                                <small>Available Beds</small>
                            </div>
                        </div>
                        <div class="progress mt-3">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: <?php echo $total_male_students > 0 ? ($total_male_assigned / $total_male_students * 100) : 0; ?>%">
                                <?php echo $total_male_students > 0 ? number_format($total_male_assigned / $total_male_students * 100, 1) : 0; ?>%
                            </div>
                        </div>
                        <small class="text-muted">Assignment Rate: <?php echo $total_male_students > 0 ? number_format($total_male_assigned / $total_male_students * 100, 1) : 0; ?>%</small>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header" style="background-color: #e83e8c; color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-female me-2"></i>Female Dormitories
                            <span class="badge bg-light text-dark float-end">
                                <?php echo count($female_dormitories); ?> Dormitories
                            </span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <h4 class="text-danger"><?php echo $total_female_students; ?></h4>
                                <small>Total Female Students</small>
                            </div>
                            <div class="col-md-4 text-center">
                                <h4 class="text-success"><?php echo $total_female_assigned; ?></h4>
                                <small>Assigned</small>
                            </div>
                            <div class="col-md-4 text-center">
                                <h4 class="text-info"><?php echo $female_available_beds; ?></h4>
                                <small>Available Beds</small>
                            </div>
                        </div>
                        <div class="progress mt-3">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?php echo $total_female_students > 0 ? ($total_female_assigned / $total_female_students * 100) : 0; ?>%; background-color: #e83e8c;">
                                <?php echo $total_female_students > 0 ? number_format($total_female_assigned / $total_female_students * 100, 1) : 0; ?>%
                            </div>
                        </div>
                        <small class="text-muted">Assignment Rate: <?php echo $total_female_students > 0 ? number_format($total_female_assigned / $total_female_students * 100, 1) : 0; ?>%</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dormitory Overview -->
        <div class="row mb-4">
            <?php if (empty($dormitories)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No active dormitories found. Please add a dormitory first.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($dormitories as $dorm): 
                    $available = max(0, $dorm['total_capacity'] - $dorm['current_occupancy']);
                    $occupancy_rate = $dorm['total_capacity'] > 0 ? 
                        round(($dorm['current_occupancy'] / $dorm['total_capacity']) * 100, 1) : 0;
                    $dorm_color = $dorm['dorm_type'] == 'Male' ? '#007bff' : '#e83e8c';
                ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header" style="background-color: <?php echo $dorm_color; ?>; color: white;">
                            <h5 class="mb-0">
                                <i class="fas fa-<?php echo $dorm['dorm_type'] == 'Male' ? 'male' : 'female'; ?> me-2"></i>
                                <?php echo htmlspecialchars($dorm['dorm_name']); ?> Dormitory
                                <span class="badge bg-light text-dark float-end">
                                    <?php echo $dorm['current_occupancy'] . '/' . $dorm['total_capacity']; ?> Beds
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Type:</strong> <?php echo $dorm['dorm_type']; ?></p>
                                    <p><strong>Rooms:</strong> <?php echo $dorm['rooms_count']; ?></p>
                                    <p><strong>Capacity per Room:</strong> <?php echo $dorm['capacity_per_room']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Total Capacity:</strong> <?php echo $dorm['total_capacity']; ?></p>
                                    <p><strong>Current Occupancy:</strong> <?php echo $dorm['current_occupancy']; ?></p>
                                    <p><strong>Available:</strong> 
                                        <span class="badge bg-<?php echo ($available > 0) ? 'success' : 'danger'; ?>">
                                            <?php echo $available; ?> beds
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="progress mt-2">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo min($occupancy_rate, 100); ?>%; 
                                            background-color: <?php echo $dorm_color; ?>;" 
                                     aria-valuenow="<?php echo $dorm['current_occupancy']; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="<?php echo $dorm['total_capacity']; ?>">
                                    <?php echo $occupancy_rate; ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select id="genderFilter" class="form-select">
                            <option value="">All Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="classFilter" class="form-select">
                            <option value="">All Classes</option>
                            <option value="Form Five">Form Five</option>
                            <option value="Form Six">Form Six</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="dormitoryFilter" class="form-select">
                            <option value="">All Dormitories</option>
                            <?php foreach ($dormitories as $dorm): ?>
                            <option value="<?php echo htmlspecialchars($dorm['dorm_name']); ?>"><?php echo htmlspecialchars($dorm['dorm_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-select">
                            <option value="all">All Students</option>
                            <option value="assigned">Assigned Only</option>
                            <option value="unassigned">Unassigned Only</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs for Male/Female -->
        <ul class="nav nav-tabs mb-4" id="dormitoryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-tab-pane" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>All Students (<?php echo count($all_assignments); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="male-tab" data-bs-toggle="tab" data-bs-target="#male-tab-pane" type="button" role="tab">
                    <i class="fas fa-male me-2"></i>Male (<?php echo count($male_assignments); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="female-tab" data-bs-toggle="tab" data-bs-target="#female-tab-pane" type="button" role="tab">
                    <i class="fas fa-female me-2"></i>Female (<?php echo count($female_assignments); ?>)
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="dormitoryTabsContent">
            <!-- All Students Tab -->
            <div class="tab-pane fade show active" id="all-tab-pane" role="tabpanel" tabindex="0">
                <div class="card">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                        <h4 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            All Dormitory Assignments
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="allAssignmentsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>S/N</th>
                                        <th>Student</th>
                                        <th>Gender</th>
                                        <th>Class</th>
                                        <th>Dormitory</th>
                                        <th>Room</th>
                                        <th>Bed</th>
                                        <th>Room Capacity</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($all_assignments)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4">
                                                <i class="fas fa-bed fa-2x text-muted d-block mb-2"></i>
                                                <p class="text-muted">No dormitory assignments found.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($all_assignments as $index => $assignment): 
                                            $occupancy_rate = $assignment['room_capacity'] > 0 ? 
                                                round(($assignment['room_occupancy'] / $assignment['room_capacity']) * 100, 0) : 0;
                                            $gender_color = $assignment['sex'] == 'Male' ? '#007bff' : '#e83e8c';
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-3" 
                                                         style="background-color: <?php echo $assignment['sex'] == 'Male' ? 'rgba(0, 123, 255, 0.1)' : 'rgba(232, 62, 140, 0.1)'; ?>;">
                                                        <i class="fas fa-<?php echo $assignment['sex'] == 'Male' ? 'male' : 'female'; ?>" 
                                                           style="color: <?php echo $gender_color; ?>;"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></strong>
                                                        <div class="text-muted small">
                                                            <?php echo $assignment['index_number']; ?> | <?php echo $assignment['combination']; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo $gender_color; ?>; color: white;">
                                                    <?php echo $assignment['sex']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $assignment['class']; ?></span>
                                                <br>
                                                <small class="text-muted"><?php echo $assignment['graduation_status']; ?></small>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo $assignment['dorm_type'] == 'Male' ? '#007bff' : '#e83e8c'; ?>; color: white;">
                                                    <?php echo htmlspecialchars($assignment['dorm_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark"><?php echo htmlspecialchars($assignment['room_number']); ?></span>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($assignment['room_label']); ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($assignment['bed_number'])): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-bed me-1"></i><?php echo htmlspecialchars($assignment['bed_number']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted"><i class="fas fa-bed-slash me-1"></i>Not Set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo $assignment['room_occupancy']; ?>/<?php echo $assignment['room_capacity']; ?>
                                                    <div class="progress mt-1" style="height: 5px;">
                                                        <div class="progress-bar <?php echo $occupancy_rate >= 100 ? 'bg-danger' : ($occupancy_rate >= 80 ? 'bg-warning' : 'bg-success'); ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo min($occupancy_rate, 100); ?>%;">
                                                        </div>
                                                    </div>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($assignment['is_leaver']): ?>
                                                    <span class="badge bg-danger"><i class="fas fa-user-slash me-1"></i>Leaver</span>
                                                <?php elseif (in_array($assignment['class'], ['Leavers', 'Graduated'])): ?>
                                                    <span class="badge bg-warning"><i class="fas fa-graduation-cap me-1"></i>Graduated</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><i class="fas fa-user-check me-1"></i>Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-outline-info edit-assignment" 
                                                            data-bs-toggle="modal" data-bs-target="#editAssignmentModal"
                                                            data-assignment-id="<?php echo $assignment['id']; ?>"
                                                            data-student-name="<?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>"
                                                            data-dormitory-id="<?php echo $assignment['dormitory_id']; ?>"
                                                            data-room-id="<?php echo $assignment['room_id']; ?>"
                                                            data-bed-number="<?php echo htmlspecialchars($assignment['bed_number'] ?? ''); ?>"
                                                            data-student-gender="<?php echo $assignment['sex']; ?>"
                                                            title="Edit Assignment">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger remove-assignment" 
                                                            data-assignment-id="<?php echo $assignment['id']; ?>"
                                                            data-student-name="<?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>"
                                                            title="Remove Assignment">
                                                        <i class="fas fa-trash"></i>
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
            </div>

            <!-- Male Tab -->
            <div class="tab-pane fade" id="male-tab-pane" role="tabpanel" tabindex="0">
                <div class="card">
                    <div class="card-header" style="background-color: #007bff; color: white;">
                        <h4 class="mb-0">
                            <i class="fas fa-male me-2"></i>
                            Male Dormitory Assignments
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="maleAssignmentsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>S/N</th>
                                        <th>Student</th>
                                        <th>Class</th>
                                        <th>Dormitory</th>
                                        <th>Room</th>
                                        <th>Bed</th>
                                        <th>Room Capacity</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($male_assignments)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <i class="fas fa-bed fa-2x text-muted d-block mb-2"></i>
                                                <p class="text-muted">No male dormitory assignments found.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($male_assignments as $index => $assignment): 
                                            $occupancy_rate = $assignment['room_capacity'] > 0 ? 
                                                round(($assignment['room_occupancy'] / $assignment['room_capacity']) * 100, 0) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-3" style="background-color: rgba(0, 123, 255, 0.1);">
                                                        <i class="fas fa-male" style="color: #007bff;"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></strong>
                                                        <div class="text-muted small">
                                                            <?php echo $assignment['index_number']; ?> | <?php echo $assignment['combination']; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $assignment['class']; ?></span>
                                                <br>
                                                <small class="text-muted"><?php echo $assignment['graduation_status']; ?></small>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: #007bff; color: white;">
                                                    <?php echo htmlspecialchars($assignment['dorm_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark"><?php echo htmlspecialchars($assignment['room_number']); ?></span>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($assignment['room_label']); ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($assignment['bed_number'])): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-bed me-1"></i><?php echo htmlspecialchars($assignment['bed_number']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted"><i class="fas fa-bed-slash me-1"></i>Not Set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo $assignment['room_occupancy']; ?>/<?php echo $assignment['room_capacity']; ?>
                                                    <div class="progress mt-1" style="height: 5px;">
                                                        <div class="progress-bar <?php echo $occupancy_rate >= 100 ? 'bg-danger' : ($occupancy_rate >= 80 ? 'bg-warning' : 'bg-success'); ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo min($occupancy_rate, 100); ?>%;">
                                                        </div>
                                                    </div>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($assignment['is_leaver']): ?>
                                                    <span class="badge bg-danger"><i class="fas fa-user-slash me-1"></i>Leaver</span>
                                                <?php elseif (in_array($assignment['class'], ['Leavers', 'Graduated'])): ?>
                                                    <span class="badge bg-warning"><i class="fas fa-graduation-cap me-1"></i>Graduated</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><i class="fas fa-user-check me-1"></i>Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-outline-info edit-assignment" 
                                                            data-bs-toggle="modal" data-bs-target="#editAssignmentModal"
                                                            data-assignment-id="<?php echo $assignment['id']; ?>"
                                                            data-student-name="<?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>"
                                                            data-dormitory-id="<?php echo $assignment['dormitory_id']; ?>"
                                                            data-room-id="<?php echo $assignment['room_id']; ?>"
                                                            data-bed-number="<?php echo htmlspecialchars($assignment['bed_number'] ?? ''); ?>"
                                                            data-student-gender="Male"
                                                            title="Edit Assignment">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger remove-assignment" 
                                                            data-assignment-id="<?php echo $assignment['id']; ?>"
                                                            data-student-name="<?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>"
                                                            title="Remove Assignment">
                                                        <i class="fas fa-trash"></i>
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
            </div>

            <!-- Female Tab -->
            <div class="tab-pane fade" id="female-tab-pane" role="tabpanel" tabindex="0">
                <div class="card">
                    <div class="card-header" style="background-color: #e83e8c; color: white;">
                        <h4 class="mb-0">
                            <i class="fas fa-female me-2"></i>
                            Female Dormitory Assignments
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="femaleAssignmentsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>S/N</th>
                                        <th>Student</th>
                                        <th>Class</th>
                                        <th>Dormitory</th>
                                        <th>Room</th>
                                        <th>Bed</th>
                                        <th>Room Capacity</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($female_assignments)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <i class="fas fa-bed fa-2x text-muted d-block mb-2"></i>
                                                <p class="text-muted">No female dormitory assignments found.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($female_assignments as $index => $assignment): 
                                            $occupancy_rate = $assignment['room_capacity'] > 0 ? 
                                                round(($assignment['room_occupancy'] / $assignment['room_capacity']) * 100, 0) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-3" style="background-color: rgba(232, 62, 140, 0.1);">
                                                        <i class="fas fa-female" style="color: #e83e8c;"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></strong>
                                                        <div class="text-muted small">
                                                            <?php echo $assignment['index_number']; ?> | <?php echo $assignment['combination']; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $assignment['class']; ?></span>
                                                <br>
                                                <small class="text-muted"><?php echo $assignment['graduation_status']; ?></small>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: #e83e8c; color: white;">
                                                    <?php echo htmlspecialchars($assignment['dorm_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark"><?php echo htmlspecialchars($assignment['room_number']); ?></span>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($assignment['room_label']); ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($assignment['bed_number'])): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-bed me-1"></i><?php echo htmlspecialchars($assignment['bed_number']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted"><i class="fas fa-bed-slash me-1"></i>Not Set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo $assignment['room_occupancy']; ?>/<?php echo $assignment['room_capacity']; ?>
                                                    <div class="progress mt-1" style="height: 5px;">
                                                        <div class="progress-bar <?php echo $occupancy_rate >= 100 ? 'bg-danger' : ($occupancy_rate >= 80 ? 'bg-warning' : 'bg-success'); ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo min($occupancy_rate, 100); ?>%;">
                                                        </div>
                                                    </div>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($assignment['is_leaver']): ?>
                                                    <span class="badge bg-danger"><i class="fas fa-user-slash me-1"></i>Leaver</span>
                                                <?php elseif (in_array($assignment['class'], ['Leavers', 'Graduated'])): ?>
                                                    <span class="badge bg-warning"><i class="fas fa-graduation-cap me-1"></i>Graduated</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><i class="fas fa-user-check me-1"></i>Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-outline-info edit-assignment" 
                                                            data-bs-toggle="modal" data-bs-target="#editAssignmentModal"
                                                            data-assignment-id="<?php echo $assignment['id']; ?>"
                                                            data-student-name="<?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>"
                                                            data-dormitory-id="<?php echo $assignment['dormitory_id']; ?>"
                                                            data-room-id="<?php echo $assignment['room_id']; ?>"
                                                            data-bed-number="<?php echo htmlspecialchars($assignment['bed_number'] ?? ''); ?>"
                                                            data-student-gender="Female"
                                                            title="Edit Assignment">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger remove-assignment" 
                                                            data-assignment-id="<?php echo $assignment['id']; ?>"
                                                            data-student-name="<?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>"
                                                            title="Remove Assignment">
                                                        <i class="fas fa-trash"></i>
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
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODALS -->
<!-- ============================================= -->

<!-- Add Dormitory Modal -->
<div class="modal fade" id="addDormitoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add New Dormitory</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="add_dormitory_modal" value="1">
                    <input type="hidden" name="school_id" value="<?php echo $current_school_id ?: 0; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Dormitory Name *</label>
                        <input type="text" class="form-control" name="dorm_name" placeholder="e.g., Magufuli, Safina" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type *</label>
                        <select class="form-select" name="dorm_type" required>
                            <option value="">Select Type...</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rooms Count *</label>
                            <input type="number" class="form-control" name="rooms_count" min="1" placeholder="e.g., 10" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Capacity/Room *</label>
                            <input type="number" class="form-control" name="capacity_per_room" min="1" placeholder="e.g., 6" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" placeholder="Optional description">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Add Dormitory
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Dormitory Modal -->
<div class="modal fade" id="editDormitoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Dormitory</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="edit_dormitory_modal" value="1">
                    <input type="hidden" name="dormitory_id" id="editDormId">
                    <input type="hidden" name="school_id" value="<?php echo $current_school_id ?: 0; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Dormitory Name *</label>
                        <input type="text" class="form-control" name="dorm_name" id="editDormName" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rooms Count *</label>
                            <input type="number" class="form-control" name="rooms_count" id="editDormRooms" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Capacity per Room *</label>
                            <input type="number" class="form-control" name="capacity_per_room" id="editDormCapacity" min="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" id="editDormDescription" placeholder="Optional description">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="editDormStatus">
                            <option value="Active">Active</option>
                            <option value="Full">Full</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Dormitory
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Dormitory Modal -->
<div class="modal fade" id="assignDormitoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                <h5 class="modal-title" id="assignDormitoryModalLabel">
                    <i class="fas fa-bed me-2"></i>Assign Student to Dormitory
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="studentSelect" class="form-label">Select Student *</label>
                            <select class="form-select" id="studentSelect" name="student_id" required>
                                <option value="">Choose student...</option>
                                <?php foreach ($all_students as $student): 
                                    $has_assignment = false;
                                    foreach ($all_assignments as $assignment) {
                                        if ($assignment['student_id'] == $student['id']) {
                                            $has_assignment = true;
                                            break;
                                        }
                                    }
                                    if (!$has_assignment):
                                ?>
                                <option value="<?php echo $student['id']; ?>" data-gender="<?php echo $student['sex']; ?>">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    - <?php echo htmlspecialchars($student['index_number']); ?> (<?php echo $student['class']; ?> - <?php echo $student['sex']; ?>)
                                </option>
                                <?php endif; endforeach; ?>
                            </select>
                            <div class="form-text">Only active Form Five and Form Six students are shown</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="dormitorySelect" class="form-label">Select Dormitory *</label>
                            <select class="form-select" id="dormitorySelect" name="dormitory_id" required onchange="loadRooms(this.value)">
                                <option value="">Choose dormitory...</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="roomSelect" class="form-label">Select Room *</label>
                            <select class="form-select" id="roomSelect" name="room_id" required disabled>
                                <option value="">Select dormitory first</option>
                            </select>
                            <small class="text-muted" id="roomInfo"></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="bedNumber" class="form-label">Bed Number (Optional)</label>
                            <input type="text" class="form-control" id="bedNumber" name="bed_number" placeholder="e.g., Bed 1, Bunk A">
                            <div class="form-text">Leave empty for automatic bed assignment</div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> Only active Form Five and Form Six students can be assigned to dormitories.
                        Leavers and graduated students will be automatically removed.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_student" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Assign to Dormitory
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Assignment Modal -->
<div class="modal fade" id="editAssignmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Dormitory Assignment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="editAssignmentId" name="assignment_id">
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Editing assignment for: <strong id="editStudentName"></strong>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editDormitorySelect" class="form-label">Select Dormitory *</label>
                            <select class="form-select" id="editDormitorySelect" name="dormitory_id" required onchange="loadEditRooms(this.value)">
                                <option value="">Choose dormitory...</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editRoomSelect" class="form-label">Select Room *</label>
                            <select class="form-select" id="editRoomSelect" name="room_id" required disabled>
                                <option value="">Select dormitory first</option>
                            </select>
                            <small class="text-muted" id="editRoomInfo"></small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editBedNumber" class="form-label">Bed Number (Optional)</label>
                        <input type="text" class="form-control" id="editBedNumber" name="bed_number" placeholder="e.g., Bed 1, Bunk A">
                        <div class="form-text">Leave empty to remove bed assignment</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_assignment" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Remove Assignment Confirmation Modal -->
<div class="modal fade" id="removeAssignmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Remove Assignment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-user-times fa-3x text-danger mb-3"></i>
                <h5 class="mb-3">Remove dormitory assignment?</h5>
                <p class="mb-2">Student: <strong id="removeStudentName"></strong></p>
                <p class="text-danger">
                    <small>This will free up the bed space for other students.</small>
                </p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmRemove" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Remove Assignment
                </a>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
// =============================================
// NOTIFICATIONS
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    const infoMessage = document.getElementById('infoMessage');
    
    if (successMessage) {
        const message = successMessage.getAttribute('data-message');
        Swal.fire({
            title: 'Success!',
            text: message,
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6',
            timer: 3000,
            timerProgressBar: true,
        });
    }
    
    if (errorMessage) {
        const message = errorMessage.getAttribute('data-message');
        Swal.fire({
            title: 'Error!',
            text: message,
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#d33',
        });
    }
    
    if (infoMessage) {
        const message = infoMessage.getAttribute('data-message');
        Swal.fire({
            title: 'Information',
            text: message,
            icon: 'info',
            confirmButtonText: 'OK',
            confirmButtonColor: '#17a2b8',
            timer: 4000,
            timerProgressBar: true,
        });
    }
});

// =============================================
// FILTER DORMITORIES BY GENDER
// =============================================
document.getElementById('studentSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const studentGender = selectedOption.getAttribute('data-gender');
    const dormitorySelect = document.getElementById('dormitorySelect');
    
    dormitorySelect.innerHTML = '<option value="">Choose dormitory...</option>';
    
    <?php foreach ($dormitories as $dorm): 
        $available = max(0, $dorm['total_capacity'] - $dorm['current_occupancy']);
    ?>
        if ("<?php echo $dorm['dorm_type']; ?>" === studentGender) {
            const option = document.createElement('option');
            option.value = "<?php echo $dorm['id']; ?>";
            option.textContent = "<?php echo htmlspecialchars($dorm['dorm_name']); ?> (Available: <?php echo $available; ?>/<?php echo $dorm['total_capacity']; ?> beds)";
            dormitorySelect.appendChild(option);
        }
    <?php endforeach; ?>
    
    document.getElementById('roomSelect').innerHTML = '<option value="">Select dormitory first</option>';
    document.getElementById('roomSelect').disabled = true;
    document.getElementById('roomInfo').innerHTML = '';
});

// =============================================
// LOAD ROOMS FOR DORMITORY
// =============================================
function loadRooms(dormitoryId) {
    if (!dormitoryId) {
        document.getElementById('roomSelect').innerHTML = '<option value="">Select dormitory first</option>';
        document.getElementById('roomSelect').disabled = true;
        document.getElementById('roomInfo').innerHTML = '';
        return;
    }
    
    document.getElementById('roomInfo').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading rooms...';
    
    fetch(`get_rooms.php?dormitory_id=${dormitoryId}`)
        .then(response => response.json())
        .then(data => {
            const roomSelect = document.getElementById('roomSelect');
            roomSelect.innerHTML = '';
            
            if (data.rooms && data.rooms.length > 0) {
                data.rooms.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.textContent = `${room.room_label} (Available: ${room.available_beds}/${room.capacity})`;
                    option.dataset.capacity = room.capacity;
                    option.dataset.occupancy = room.current_occupancy;
                    roomSelect.appendChild(option);
                });
                roomSelect.disabled = false;
                
                document.getElementById('roomInfo').innerHTML = 
                    `<strong>${data.dormitory.dorm_name}:</strong> ${data.statistics.active_students}/${data.statistics.total_capacity} beds occupied, ${data.statistics.real_available_beds} beds available`;
                
            } else {
                roomSelect.innerHTML = '<option value="">No available rooms</option>';
                roomSelect.disabled = true;
                document.getElementById('roomInfo').innerHTML = 
                    `<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> No available rooms in this dormitory</span>`;
            }
        })
        .catch(error => {
            console.error('Error loading rooms:', error);
            document.getElementById('roomSelect').innerHTML = '<option value="">Error loading rooms</option>';
            document.getElementById('roomSelect').disabled = true;
            document.getElementById('roomInfo').innerHTML = 
                '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading room data</span>';
        });
}

function loadEditRooms(dormitoryId) {
    if (!dormitoryId) {
        document.getElementById('editRoomSelect').innerHTML = '<option value="">Select dormitory first</option>';
        document.getElementById('editRoomSelect').disabled = true;
        document.getElementById('editRoomInfo').innerHTML = '';
        return;
    }
    
    document.getElementById('editRoomInfo').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading rooms...';
    
    fetch(`get_rooms.php?dormitory_id=${dormitoryId}`)
        .then(response => response.json())
        .then(data => {
            const roomSelect = document.getElementById('editRoomSelect');
            roomSelect.innerHTML = '';
            
            if (data.rooms && data.rooms.length > 0) {
                data.rooms.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.textContent = `${room.room_label} (Available: ${room.available_beds}/${room.capacity})`;
                    option.dataset.capacity = room.capacity;
                    option.dataset.occupancy = room.current_occupancy;
                    roomSelect.appendChild(option);
                });
                roomSelect.disabled = false;
                
                document.getElementById('editRoomInfo').innerHTML = 
                    `<strong>${data.dormitory.dorm_name}:</strong> ${data.statistics.active_students}/${data.statistics.total_capacity} beds occupied, ${data.statistics.real_available_beds} beds available`;
                
            } else {
                roomSelect.innerHTML = '<option value="">No available rooms</option>';
                roomSelect.disabled = true;
                document.getElementById('editRoomInfo').innerHTML = 
                    `<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> No available rooms in this dormitory</span>`;
            }
        })
        .catch(error => {
            console.error('Error loading rooms:', error);
            document.getElementById('editRoomSelect').innerHTML = '<option value="">Error loading rooms</option>';
            document.getElementById('editRoomSelect').disabled = true;
            document.getElementById('editRoomInfo').innerHTML = 
                '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading room data</span>';
        });
}

// =============================================
// EDIT ASSIGNMENT BUTTON
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-assignment');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const assignmentId = this.getAttribute('data-assignment-id');
            const studentName = this.getAttribute('data-student-name');
            const dormitoryId = this.getAttribute('data-dormitory-id');
            const roomId = this.getAttribute('data-room-id');
            const bedNumber = this.getAttribute('data-bed-number');
            const studentGender = this.getAttribute('data-student-gender');
            
            document.getElementById('editAssignmentId').value = assignmentId;
            document.getElementById('editStudentName').textContent = studentName;
            document.getElementById('editBedNumber').value = bedNumber || '';
            
            const editDormitorySelect = document.getElementById('editDormitorySelect');
            editDormitorySelect.innerHTML = '<option value="">Choose dormitory...</option>';
            
            <?php foreach ($dormitories as $dorm): 
                $available = max(0, $dorm['total_capacity'] - $dorm['current_occupancy']);
            ?>
                if ("<?php echo $dorm['dorm_type']; ?>" === studentGender) {
                    const option = document.createElement('option');
                    option.value = "<?php echo $dorm['id']; ?>";
                    option.textContent = "<?php echo htmlspecialchars($dorm['dorm_name']); ?> (Available: <?php echo $available; ?>/<?php echo $dorm['total_capacity']; ?> beds)";
                    if ("<?php echo $dorm['id']; ?>" === dormitoryId) {
                        option.selected = true;
                    }
                    editDormitorySelect.appendChild(option);
                }
            <?php endforeach; ?>
            
            if (dormitoryId) {
                loadEditRooms(dormitoryId);
                setTimeout(() => {
                    const editRoomSelect = document.getElementById('editRoomSelect');
                    if (editRoomSelect && roomId) {
                        editRoomSelect.value = roomId;
                    }
                }, 500);
            }
        });
    });
});

// =============================================
// EDIT DORMITORY BUTTON
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const editDormButtons = document.querySelectorAll('.edit-dorm-btn');
    editDormButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('editDormId').value = this.getAttribute('data-id');
            document.getElementById('editDormName').value = this.getAttribute('data-name');
            document.getElementById('editDormRooms').value = this.getAttribute('data-rooms');
            document.getElementById('editDormCapacity').value = this.getAttribute('data-capacity');
            document.getElementById('editDormDescription').value = this.getAttribute('data-description') || '';
            document.getElementById('editDormStatus').value = this.getAttribute('data-status') || 'Active';
        });
    });
});

// =============================================
// REMOVE ASSIGNMENT BUTTON
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const removeButtons = document.querySelectorAll('.remove-assignment');
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const assignmentId = this.getAttribute('data-assignment-id');
            const studentName = this.getAttribute('data-student-name');
            
            document.getElementById('removeStudentName').textContent = studentName;
            document.getElementById('confirmRemove').href = `dormitory.php?remove_assignment=${assignmentId}`;
            
            const removeModal = new bootstrap.Modal(document.getElementById('removeAssignmentModal'));
            removeModal.show();
        });
    });
});

// =============================================
// SEARCH AND FILTER
// =============================================
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const activeTab = document.querySelector('.tab-pane.active');
    const table = activeTab.querySelector('table');
    
    if (table) {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchValue) ? '' : 'none';
        });
    }
});

document.getElementById('genderFilter').addEventListener('change', filterTable);
document.getElementById('classFilter').addEventListener('change', filterTable);
document.getElementById('dormitoryFilter').addEventListener('change', filterTable);
document.getElementById('statusFilter').addEventListener('change', filterTable);

function filterTable() {
    const genderFilter = document.getElementById('genderFilter').value;
    const classFilter = document.getElementById('classFilter').value;
    const dormitoryFilter = document.getElementById('dormitoryFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    
    const activeTab = document.querySelector('.tab-pane.active');
    const table = activeTab ? activeTab.querySelector('table') : null;
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        if (row.cells.length < 8) return;
        
        const rowGender = row.cells[2] ? row.cells[2].textContent.trim() : '';
        const rowClass = row.cells[3] ? row.cells[3].querySelectorAll('.badge')[0]?.textContent.trim() || '' : '';
        const rowDormitory = row.cells[4] ? row.cells[4].textContent.trim() : '';
        
        const showGender = !genderFilter || rowGender === genderFilter;
        const showClass = !classFilter || rowClass === classFilter;
        const showDormitory = !dormitoryFilter || rowDormitory === dormitoryFilter;
        
        let showStatus = true;
        if (statusFilter === 'assigned') {
            showStatus = row.style.display !== 'none';
        }
        
        row.style.display = (showGender && showClass && showDormitory && showStatus) ? '' : 'none';
    });
}

// Reset filters on tab change
document.querySelectorAll('#dormitoryTabs button').forEach(tab => {
    tab.addEventListener('click', function() {
        document.getElementById('genderFilter').value = '';
        document.getElementById('classFilter').value = '';
        document.getElementById('dormitoryFilter').value = '';
        document.getElementById('statusFilter').value = 'all';
        document.getElementById('searchInput').value = '';
        
        setTimeout(() => {
            const activeTab = document.querySelector('.tab-pane.active');
            const table = activeTab ? activeTab.querySelector('table') : null;
            if (table) {
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    row.style.display = '';
                });
            }
        }, 100);
    });
});
</script>

<style>
/* DORMITORY MANAGEMENT PAGE STYLES */
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}

.nav-tabs {
    border-bottom: 2px solid #3B9DB3;
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
    padding: 12px 24px;
    transition: all 0.3s ease;
}

.nav-tabs .nav-link:hover {
    color: #3B9DB3;
    border: none;
}

.nav-tabs .nav-link.active {
    background-color: #3B9DB3;
    color: white;
    border: none;
    border-radius: 5px 5px 0 0;
}

.table th {
    font-weight: 600;
    color: #333;
    background-color: rgba(59, 157, 179, 0.05);
    border-bottom: 2px solid rgba(59, 157, 179, 0.2);
    padding: 12px 8px;
}

.btn-group .btn {
    transition: all 0.2s ease;
}

.btn-group .btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.progress {
    height: 20px;
    border-radius: 10px;
    background-color: #e9ecef;
}

.progress-bar {
    border-radius: 10px;
}

.badge {
    font-weight: 500;
    padding: 5px 10px;
}

.badge.bg-warning {
    color: #212529 !important;
}

.bg-pink {
    background-color: #e83e8c !important;
    color: white !important;
}

.stats-card.simple-card {
    border: none;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.stats-card.simple-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: #3B9DB3;
}

.stats-card.simple-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.stats-card.simple-card .stats-icon {
    margin-bottom: 10px;
}

.stats-card.simple-card .stats-icon i {
    font-size: 2.2rem;
}

.stats-card.simple-card h3 {
    font-size: 1.8rem;
    font-weight: bold;
    color: #333;
    margin: 10px 0;
}

.stats-card.simple-card p {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
    font-weight: 500;
}

@media (max-width: 768px) {
    .avatar-circle {
        width: 35px;
        height: 35px;
        margin-right: 10px;
    }
    
    .nav-tabs .nav-link {
        padding: 8px 12px;
        font-size: 0.9rem;
    }
    
    .btn-group {
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .btn-group .btn {
        margin-bottom: 5px;
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
        flex: 1;
        min-width: 40px;
    }
    
    .modal-dialog {
        margin: 10px;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .stats-card.simple-card {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
}
</style>

<?php include '../controller/footer.php'; ?>