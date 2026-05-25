<?php
// edit_admin.php
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user has permission (Head Master or Second Master only)
$admin_id = $_SESSION['admin_id'] ?? 0;

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

// Check if user has Head Master (1) or Second Master (2) role
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 7) { // Head Master or Second Master
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view staff members.";
    header("Location:  ../404.php");
    exit();
}


// Get all roles from database (excluding Super Admin if it exists)
$roles = [];
$roles_sql = "SELECT * FROM admin_roles WHERE role_name != 'Super Admin' ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_sql);
if ($roles_result && mysqli_num_rows($roles_result) > 0) {
    while ($row = mysqli_fetch_assoc($roles_result)) {
        $roles[] = $row;
    }
}

// Function to update room occupancy
function updateRoomOccupancy($conn, $room_id) {
    $update_sql = "UPDATE dormitory_rooms 
                   SET current_occupancy = (
                       SELECT COUNT(*) FROM student_dormitory 
                       WHERE room_id = $room_id AND status = 'Active'
                   )
                   WHERE id = $room_id";
    return mysqli_query($conn, $update_sql);
}

// Function to update dormitory occupancy
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

// Function to remove leavers/graduated students from dormitories
function removeLeaversFromDormitories($conn) {
    $removed_count = 0;
    
    // Find students who are leavers or graduated but still have active assignments
    $leavers_sql = "SELECT sd.id as assignment_id, sd.room_id, sd.dormitory_id, 
                           s.first_name, s.last_name, s.index_number,
                           CONCAT(s.first_name, ' ', s.last_name) as student_name
                    FROM student_dormitory sd
                    JOIN students s ON sd.student_id = s.id
                    JOIN dormitories d ON sd.dormitory_id = d.id
                    WHERE sd.status = 'Active'
                    AND (s.is_leaver = TRUE 
                         OR s.class IN ('Leavers', 'Graduated') 
                         OR s.graduation_status IN ('Graduated', 'Left'))";
    
    $leavers_result = mysqli_query($conn, $leavers_sql);
    
    if ($leavers_result && mysqli_num_rows($leavers_result) > 0) {
        while ($row = mysqli_fetch_assoc($leavers_result)) {
            $assignment_id = $row['assignment_id'];
            $student_name = $row['student_name'];
            $room_id = $row['room_id'];
            $dormitory_id = $row['dormitory_id'];
            
            // Use the stored procedure to properly remove the assignment
            $remove_sql = "CALL remove_dormitory_assignment($assignment_id, 'Auto-removed: Student is leaver/graduated')";
            if (mysqli_multi_query($conn, $remove_sql)) {
                // Consume all results
                while (mysqli_more_results($conn) && mysqli_next_result($conn));
                
                // Update occupancies
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

// Run automatic removal check at the beginning
removeLeaversFromDormitories($conn);

// Get all active students (Form Five and Six, not leavers/graduated)
$students_sql = "SELECT s.* FROM students s 
                WHERE s.is_leaver = FALSE 
                AND s.status = 1
                AND s.class IN ('Form Five', 'Form Six')
                AND s.graduation_status NOT IN ('Graduated', 'Left')
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
$assignments_sql = "SELECT sd.*, s.first_name, s.last_name, s.index_number, s.class, s.combination, s.sex,
                   s.is_leaver, s.graduation_status,
                   d.dorm_name, d.dorm_type, dr.room_number, dr.room_label, 
                   dr.capacity as room_capacity,
                   dr.current_occupancy as room_occupancy,
                   dr.status as room_status,
                   (SELECT COUNT(*) FROM student_dormitory sd2 
                    WHERE sd2.room_id = dr.id AND sd2.status = 'Active') as active_in_room
                   FROM student_dormitory sd
                   JOIN students s ON sd.student_id = s.id
                   JOIN dormitories d ON sd.dormitory_id = d.id
                   JOIN dormitory_rooms dr ON sd.room_id = dr.id
                   WHERE sd.status = 'Active'
                   AND s.is_leaver = FALSE
                   AND s.class IN ('Form Five', 'Form Six')
                   AND s.graduation_status NOT IN ('Graduated', 'Left')
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
$dormitories_sql = "SELECT * FROM dormitories WHERE status IN ('Active', 'Full') ORDER BY dorm_type, dorm_name";
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

// Handle dormitory assignment using stored procedure
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_student'])) {
    $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
    $dormitory_id = mysqli_real_escape_string($conn, $_POST['dormitory_id']);
    $room_id = mysqli_real_escape_string($conn, $_POST['room_id']);
    $bed_number = mysqli_real_escape_string($conn, $_POST['bed_number']);
    $assigned_by = $_SESSION['admin_id'];
    
    // Get student details
    $student_sql = "SELECT CONCAT(first_name, ' ', last_name) as student_name, sex 
                   FROM students WHERE id = $student_id";
    $student_result = mysqli_query($conn, $student_sql);
    $student_data = mysqli_fetch_assoc($student_result);
    $student_name = $student_data['student_name'] ?? 'Unknown';
    $student_sex = $student_data['sex'] ?? '';
    
    try {
        // Check if student already has active assignment
        $check_sql = "SELECT id FROM student_dormitory 
                     WHERE student_id = $student_id AND status = 'Active'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            throw new Exception("Student already has an active dormitory assignment!");
        }
        
        // Use the stored procedure for assignment
        $procedure_sql = "CALL assign_student_to_dormitory(
            $student_id, 
            $dormitory_id, 
            $room_id, 
            '$bed_number', 
            $assigned_by, 
            'Assigned via dormitory.php'
        )";
        
        if (mysqli_multi_query($conn, $procedure_sql)) {
            // Get the result
            if ($result = mysqli_store_result($conn)) {
                $proc_result = mysqli_fetch_assoc($result);
                mysqli_free_result($result);
            }
            
            // Consume all results
            while (mysqli_more_results($conn) && mysqli_next_result($conn));
            
            if (isset($proc_result['status']) && $proc_result['status'] == 'SUCCESS') {
                // Update occupancies
                updateRoomOccupancy($conn, $room_id);
                updateDormitoryOccupancy($conn, $dormitory_id);
                
                $_SESSION['success'] = $proc_result['message'];
            } else {
                throw new Exception("Failed to assign dormitory.");
            }
        } else {
            throw new Exception("Error calling assignment procedure: " . mysqli_error($conn));
        }
        
        header("Location: dormitory.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: dormitory.php");
        exit();
    }
}

// Handle update dormitory assignment using stored procedure
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_assignment'])) {
    $assignment_id = mysqli_real_escape_string($conn, $_POST['assignment_id']);
    $new_dormitory_id = mysqli_real_escape_string($conn, $_POST['dormitory_id']);
    $new_room_id = mysqli_real_escape_string($conn, $_POST['room_id']);
    $new_bed_number = mysqli_real_escape_string($conn, $_POST['bed_number']);
    $updated_by = $_SESSION['admin_id'];
    
    // Get old room and dormitory info for occupancy updates
    $old_info_sql = "SELECT room_id, dormitory_id FROM student_dormitory WHERE id = $assignment_id";
    $old_info_result = mysqli_query($conn, $old_info_sql);
    $old_info = mysqli_fetch_assoc($old_info_result);
    $old_room_id = $old_info['room_id'];
    $old_dormitory_id = $old_info['dormitory_id'];
    
    try {
        // Use the stored procedure for update
        $procedure_sql = "CALL update_student_dormitory(
            $assignment_id,
            $new_dormitory_id,
            $new_room_id,
            '$new_bed_number',
            $updated_by,
            'Updated via dormitory.php'
        )";
        
        if (mysqli_multi_query($conn, $procedure_sql)) {
            // Get the result
            if ($result = mysqli_store_result($conn)) {
                $proc_result = mysqli_fetch_assoc($result);
                mysqli_free_result($result);
            }
            
            // Consume all results
            while (mysqli_more_results($conn) && mysqli_next_result($conn));
            
            if (isset($proc_result['status']) && $proc_result['status'] == 'SUCCESS') {
                // Update occupancies for old room/dormitory
                updateRoomOccupancy($conn, $old_room_id);
                updateDormitoryOccupancy($conn, $old_dormitory_id);
                
                // Update occupancies for new room/dormitory
                updateRoomOccupancy($conn, $new_room_id);
                updateDormitoryOccupancy($conn, $new_dormitory_id);
                
                $_SESSION['success'] = $proc_result['message'];
            } else {
                throw new Exception("Failed to update assignment.");
            }
        } else {
            throw new Exception("Error calling update procedure: " . mysqli_error($conn));
        }
        
        header("Location: dormitory.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: dormitory.php");
        exit();
    }
}

// Handle remove assignment using stored procedure
if (isset($_GET['remove_assignment'])) {
    $assignment_id = mysqli_real_escape_string($conn, $_GET['remove_assignment']);
    
    try {
        // Get assignment details first
        $get_sql = "SELECT sd.*, s.first_name, s.last_name, s.sex 
                   FROM student_dormitory sd
                   JOIN students s ON sd.student_id = s.id
                   WHERE sd.id = $assignment_id";
        $get_result = mysqli_query($conn, $get_sql);
        $assignment_data = mysqli_fetch_assoc($get_result);
        
        if (!$assignment_data) {
            throw new Exception("Assignment not found.");
        }
        
        $student_name = $assignment_data['first_name'] . ' ' . $assignment_data['last_name'];
        $room_id = $assignment_data['room_id'];
        $dormitory_id = $assignment_data['dormitory_id'];
        $student_sex = $assignment_data['sex'];
        
        // Use the stored procedure for removal
        $procedure_sql = "CALL remove_dormitory_assignment($assignment_id, 'Removed by admin via dormitory.php')";
        
        if (mysqli_multi_query($conn, $procedure_sql)) {
            // Get the result
            if ($result = mysqli_store_result($conn)) {
                $proc_result = mysqli_fetch_assoc($result);
                mysqli_free_result($result);
            }
            
            // Consume all results
            while (mysqli_more_results($conn) && mysqli_next_result($conn));
            
            if (isset($proc_result['status']) && $proc_result['status'] == 'SUCCESS') {
                // Update occupancies
                updateRoomOccupancy($conn, $room_id);
                updateDormitoryOccupancy($conn, $dormitory_id);
                
                $_SESSION['success'] = $proc_result['message'];
            } else {
                throw new Exception("Failed to remove assignment.");
            }
        } else {
            throw new Exception("Error calling removal procedure: " . mysqli_error($conn));
        }
        
        header("Location: dormitory.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: dormitory.php");
        exit();
    }
}

// Handle remove student from dormitory (when student is deleted/marked as leaver from other pages)
if (isset($_GET['remove_student_dormitory'])) {
    $student_id = mysqli_real_escape_string($conn, $_GET['remove_student_dormitory']);
    
    try {
        // Get all assignments for this student
        $get_assignments_sql = "SELECT id, room_id, dormitory_id FROM student_dormitory 
                               WHERE student_id = $student_id AND status = 'Active'";
        $assignments_result = mysqli_query($conn, $get_assignments_sql);
        
        $removed_count = 0;
        $rooms_to_update = [];
        $dormitories_to_update = [];
        
        while ($assignment = mysqli_fetch_assoc($assignments_result)) {
            $assignment_id = $assignment['id'];
            $room_id = $assignment['room_id'];
            $dormitory_id = $assignment['dormitory_id'];
            
            // Use stored procedure
            $procedure_sql = "CALL remove_dormitory_assignment($assignment_id, 'Auto-removed: Student deleted/marked as leaver')";
            
            if (mysqli_multi_query($conn, $procedure_sql)) {
                // Consume all results
                while (mysqli_more_results($conn) && mysqli_next_result($conn));
                
                $rooms_to_update[] = $room_id;
                $dormitories_to_update[] = $dormitory_id;
                $removed_count++;
            }
        }
        
        // Update occupancies
        foreach (array_unique($rooms_to_update) as $room_id) {
            updateRoomOccupancy($conn, $room_id);
        }
        
        foreach (array_unique($dormitories_to_update) as $dormitory_id) {
            updateDormitoryOccupancy($conn, $dormitory_id);
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

// Function to get available rooms (for JavaScript)
function getAvailableRooms($conn, $dormitory_id) {
    $rooms_sql = "SELECT dr.*, (dr.capacity - dr.current_occupancy) as available_beds
                 FROM dormitory_rooms dr
                 WHERE dr.dormitory_id = $dormitory_id
                 AND dr.status = 'Available'
                 AND dr.current_occupancy < dr.capacity
                 ORDER BY dr.room_number";
    
    $result = mysqli_query($conn, $rooms_sql);
    $rooms = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rooms[] = $row;
        }
    }
    return $rooms;
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Dormitory Management System</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignDormitoryModal">
                <i class="fas fa-bed me-2"></i>Assign Dormitory
            </button>
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
                    <div class="card-header" style=" background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);">
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
            <!-- Male Statistics -->
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

            <!-- Female Statistics -->
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
                    <div class="card-header" style=" background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);">
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
                                            <td colspan="10" class="text-center">No dormitory assignments found.</td>
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
                                            <td colspan="9" class="text-center">No male dormitory assignments found.</td>
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
                                            <td colspan="9" class="text-center">No female dormitory assignments found.</td>
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

<!-- Assign Dormitory Modal -->
<div class="modal fade" id="assignDormitoryModal" tabindex="-1" aria-labelledby="assignDormitoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                <h5 class="modal-title" id="assignDormitoryModalLabel">
                    <i class="fas fa-bed me-2"></i>Assign Student to Dormitory
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="studentSelect" class="form-label">Select Student *</label>
                            <select class="form-select" id="studentSelect" name="student_id" required>
                                <option value="">Choose student...</option>
                                <?php foreach ($all_students as $student): 
                                    // Check if student already has assignment
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
                                <!-- Dormitories will be filtered by gender -->
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
                            <input type="text" class="form-control" id="bedNumber" name="bed_number" 
                                   placeholder="e.g., Bed 1, Bunk A, etc.">
                            <div class="form-text">Leave empty for automatic bed assignment</div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Dormitory Capacities:</strong>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <h6>Male Dormitories:</h6>
                                <ul class="mb-2">
                                    <li><strong>Magufuli:</strong> 20 rooms (A1-B10), 6 students per room</li>
                                    <li><strong>Sokoine:</strong> 20 rooms (A1-B10), 6 students per room</li>
                                    <li><strong>Mwandu:</strong> 20 rooms (A1-B10), 6 students per room</li>
                                    <li><strong>Nyerere:</strong> 10 rooms (A1-A10), 12 students per room</li>
                                    <li><strong>Kisutu Juu:</strong> 5 rooms (A1-A5), 6 students per room</li>
                                    <li><strong>Kisutu Bombani:</strong> 2 rooms (A1, B1), 12 students per room</li>
                                    <li><strong>Kisutu Chini:</strong> 2 rooms (A1, B1), 6 students per room</li>
                                    <li><strong>Kisutu Prison:</strong> 7 rooms (A1-A7), 2 students per room</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Female Dormitories:</h6>
                                <ul class="mb-2">
                                    <li><strong>Safina:</strong> 16 rooms (A1-B8), 10 students per room</li>
                                    <li><strong>Samia:</strong> 20 rooms (A1-B10), 6 students per room</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> Only active Form Five and Form Six students can be assigned to dormitories.
                        Leavers and graduated students will be automatically removed from dormitories.
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
<div class="modal fade" id="editAssignmentModal" tabindex="-1" aria-labelledby="editAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                <h5 class="modal-title" id="editAssignmentModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit Dormitory Assignment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="editAssignmentId" name="assignment_id">
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        You are editing assignment for: <strong id="editStudentName"></strong>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editDormitorySelect" class="form-label">Select Dormitory *</label>
                            <select class="form-select" id="editDormitorySelect" name="dormitory_id" required onchange="loadEditRooms(this.value)">
                                <option value="">Choose dormitory...</option>
                                <!-- Dormitories will be filtered by gender -->
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
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="editBedNumber" class="form-label">Bed Number (Optional)</label>
                            <input type="text" class="form-control" id="editBedNumber" name="bed_number" 
                                   placeholder="e.g., Bed 1, Bunk A, etc.">
                            <div class="form-text">Leave empty to remove bed assignment</div>
                        </div>
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
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-user-times fa-3x text-danger mb-3"></i>
                <h5 class="mb-3">Remove dormitory assignment?</h5>
                <p class="mb-2">Student: <strong id="removeStudentName"></strong></p>
                <p class="text-danger">
                    <small>This will free up the bed space for other students.<br>
                    The bed will become available immediately.</small>
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
// Show SweetAlert2 notifications
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
    
    // Auto-refresh page every 5 minutes to update statistics
    setTimeout(() => {
        location.reload();
    }, 300000); // 5 minutes
});

// Filter dormitories by gender when student is selected
document.getElementById('studentSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const studentGender = selectedOption.getAttribute('data-gender');
    const dormitorySelect = document.getElementById('dormitorySelect');
    
    // Clear existing options
    dormitorySelect.innerHTML = '<option value="">Choose dormitory...</option>';
    
    // Add dormitories matching the student's gender
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
    
    // Reset room selection
    document.getElementById('roomSelect').innerHTML = '<option value="">Select dormitory first</option>';
    document.getElementById('roomSelect').disabled = true;
    document.getElementById('roomInfo').innerHTML = '';
});

// Load available rooms for dormitory
function loadRooms(dormitoryId) {
    if (!dormitoryId) {
        document.getElementById('roomSelect').innerHTML = '<option value="">Select dormitory first</option>';
        document.getElementById('roomSelect').disabled = true;
        document.getElementById('roomInfo').innerHTML = '';
        return;
    }
    
    // Show loading
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
                
                // Update dormitory info
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

// Load available rooms for edit modal
function loadEditRooms(dormitoryId) {
    if (!dormitoryId) {
        document.getElementById('editRoomSelect').innerHTML = '<option value="">Select dormitory first</option>';
        document.getElementById('editRoomSelect').disabled = true;
        document.getElementById('editRoomInfo').innerHTML = '';
        return;
    }
    
    // Show loading
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
                
                // Update dormitory info
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

// Edit assignment button click
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
            
            // Filter dormitories by student gender
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
            
            // Load rooms for the current dormitory
            if (dormitoryId) {
                loadEditRooms(dormitoryId);
                
                // Set the current room as selected after rooms are loaded
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

// Remove assignment button click
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

// Search functionality
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

// Filter functionality
document.getElementById('genderFilter').addEventListener('change', filterTable);
document.getElementById('classFilter').addEventListener('change', filterTable);
document.getElementById('dormitoryFilter').addEventListener('change', filterTable);
document.getElementById('statusFilter').addEventListener('change', filterTable);

function filterTable() {
    const genderFilter = document.getElementById('genderFilter').value;
    const classFilter = document.getElementById('classFilter').value;
    const dormitoryFilter = document.getElementById('dormitoryFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    
    // Get the active tab's table
    const activeTab = document.querySelector('.tab-pane.active');
    const table = activeTab ? activeTab.querySelector('table') : null;
    
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        if (row.cells.length < 8) return; // Skip empty rows
        
        const rowGender = row.cells[2] ? row.cells[2].textContent.trim() : '';
        const rowClass = row.cells[3] ? row.cells[3].querySelectorAll('.badge')[0]?.textContent.trim() || '' : '';
        const rowDormitory = row.cells[4] ? row.cells[4].textContent.trim() : '';
        const rowStatus = row.cells[8] ? row.cells[8].textContent.trim() : '';
        
        const showGender = !genderFilter || rowGender === genderFilter;
        const showClass = !classFilter || rowClass === classFilter;
        const showDormitory = !dormitoryFilter || rowDormitory === dormitoryFilter;
        
        // For status filter
        let showStatus = true;
        if (statusFilter === 'assigned') {
            showStatus = row.style.display !== 'none';
        } else if (statusFilter === 'unassigned') {
            // This would need additional logic for unassigned students table
            showStatus = true;
        }
        
        row.style.display = (showGender && showClass && showDormitory && showStatus) ? '' : 'none';
    });
}

// Reset filters on tab change
document.querySelectorAll('#dormitoryTabs button').forEach(tab => {
    tab.addEventListener('click', function() {
        // Clear filters
        document.getElementById('genderFilter').value = '';
        document.getElementById('classFilter').value = '';
        document.getElementById('dormitoryFilter').value = '';
        document.getElementById('statusFilter').value = 'all';
        document.getElementById('searchInput').value = '';
        
        // Show all rows in the new tab
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
/* DORMITORY MANAGEMENT PAGE SPECIFIC STYLES */
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
}
</style>

<?php include '../controller/footer.php'; ?>