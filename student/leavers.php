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
    if ($role_id == 1 || $role_id == 2 || $role_id == 3) { // Head Master or Second Master
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view page you need.";
    header("Location: ../404.php.php");
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

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

// Function to cleanup dormitory assignments for student
function cleanupStudentDormitoryAssignments($conn, $student_id) {
    $cleaned_count = 0;
    $rooms_to_update = [];
    $dormitories_to_update = [];
    
    // Get all active assignments for this student
    $assignments_sql = "SELECT id, room_id, dormitory_id FROM student_dormitory 
                       WHERE student_id = $student_id AND status = 'Active'";
    $assignments_result = mysqli_query($conn, $assignments_sql);
    
    if ($assignments_result && mysqli_num_rows($assignments_result) > 0) {
        while ($row = mysqli_fetch_assoc($assignments_result)) {
            $assignment_id = $row['id'];
            $room_id = $row['room_id'];
            $dormitory_id = $row['dormitory_id'];
            
            // Use stored procedure to remove assignment
            $procedure_sql = "CALL remove_dormitory_assignment($assignment_id, 'Auto-removed: Leaver deleted from system')";
            
            if (mysqli_multi_query($conn, $procedure_sql)) {
                // Consume all results
                while (mysqli_more_results($conn) && mysqli_next_result($conn));
                
                $rooms_to_update[] = $room_id;
                $dormitories_to_update[] = $dormitory_id;
                $cleaned_count++;
            }
        }
        
        // Update occupancies
        foreach (array_unique($rooms_to_update) as $room_id) {
            updateRoomOccupancy($conn, $room_id);
        }
        
        foreach (array_unique($dormitories_to_update) as $dormitory_id) {
            updateDormitoryOccupancy($conn, $dormitory_id);
        }
    }
    
    return $cleaned_count;
}

// Function to regenerate index numbers
function regenerateAllIndexNumbers($conn) {
    $combination_order = ['HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'];
    
    // Process Form Five
    $form_five_index = 1;
    foreach ($combination_order as $combination) {
        $form_five_sql = "SELECT * FROM students 
                         WHERE class = 'Form Five' 
                         AND combination = '$combination'
                         AND is_leaver = FALSE
                         ORDER BY first_name, last_name";
        
        $form_five_result = mysqli_query($conn, $form_five_sql);
        
        if (!$form_five_result) {
            throw new Exception("Error fetching Form Five $combination students: " . mysqli_error($conn));
        }
        
        while ($student = mysqli_fetch_assoc($form_five_result)) {
            $new_index = 'S5098-' . str_pad(($form_five_index + 500), 4, '0', STR_PAD_LEFT);
            $update_sql = "UPDATE students SET index_number = '$new_index' WHERE id = " . $student['id'];
            mysqli_query($conn, $update_sql);
            $form_five_index++;
        }
    }
    
    // Process Form Six
    $form_six_index = 1;
    foreach ($combination_order as $combination) {
        $form_six_sql = "SELECT * FROM students 
                        WHERE class = 'Form Six' 
                        AND combination = '$combination'
                        AND is_leaver = FALSE
                        ORDER BY first_name, last_name";
        
        $form_six_result = mysqli_query($conn, $form_six_sql);
        
        if (!$form_six_result) {
            throw new Exception("Error fetching Form Six $combination students: " . mysqli_error($conn));
        }
        
        while ($student = mysqli_fetch_assoc($form_six_result)) {
            $new_index = 'S5098-' . str_pad(($form_six_index + 500), 4, '0', STR_PAD_LEFT);
            $update_sql = "UPDATE students SET index_number = '$new_index' WHERE id = " . $student['id'];
            mysqli_query($conn, $update_sql);
            $form_six_index++;
        }
    }
    
    return true;
}

// Get all leavers (both graduates and leavers)
$sql_leavers = "SELECT s.*, sl.reason, sl.leaver_type, sl.left_at, sl.returned
                FROM students s
                LEFT JOIN student_leavers sl ON s.id = sl.student_id
                WHERE s.is_leaver = TRUE
                ORDER BY s.graduation_status DESC, sl.left_at DESC";
$result_leavers = mysqli_query($conn, $sql_leavers);
$leavers = [];
if ($result_leavers && mysqli_num_rows($result_leavers) > 0) {
    while ($row = mysqli_fetch_assoc($result_leavers)) {
        $leavers[] = $row;
    }
}

// Handle bulk actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Bulk delete leavers - UPDATED with dormitory cleanup
    if (isset($_POST['bulk_delete']) && isset($_POST['selected_leavers'])) {
        $selected_ids = $_POST['selected_leavers'];
        
        if (empty($selected_ids)) {
            $_SESSION['error'] = "Please select at least one leaver to delete.";
            header("Location: leavers.php");
            exit();
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            $deleted_count = 0;
            $total_cleaned_assignments = 0;
            
            foreach ($selected_ids as $id) {
                $id = mysqli_real_escape_string($conn, $id);
                
                // STEP 1: Clean up dormitory assignments first
                $cleaned_count = cleanupStudentDormitoryAssignments($conn, $id);
                $total_cleaned_assignments += $cleaned_count;
                
                // STEP 2: Delete from student_leavers table
                $delete_leaver_sql = "DELETE FROM student_leavers WHERE student_id = $id";
                if (!mysqli_query($conn, $delete_leaver_sql)) {
                    throw new Exception("Error deleting from leavers table for student ID $id: " . mysqli_error($conn));
                }
                
                // STEP 3: Delete from students table
                $delete_student_sql = "DELETE FROM students WHERE id = $id";
                if (!mysqli_query($conn, $delete_student_sql)) {
                    throw new Exception("Error deleting student ID $id: " . mysqli_error($conn));
                }
                
                $deleted_count++;
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            $message = "Successfully deleted $deleted_count leaver(s) permanently!";
            if ($total_cleaned_assignments > 0) {
                $message .= " $total_cleaned_assignments dormitory assignments cleaned up.";
            }
            
            $_SESSION['success'] = $message;
            header("Location: leavers.php");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = $e->getMessage();
            header("Location: leavers.php");
            exit();
        }
    }
    
    // Bulk return leavers - UPDATED: No dormitory cleanup needed when returning
    if (isset($_POST['bulk_return']) && isset($_POST['selected_leavers']) && isset($_POST['return_class'])) {
        $selected_ids = $_POST['selected_leavers'];
        $return_class = mysqli_real_escape_string($conn, $_POST['return_class']);
        
        if (empty($selected_ids)) {
            $_SESSION['error'] = "Please select at least one leaver to return.";
            header("Location: leavers.php");
            exit();
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            $returned_count = 0;
            
            foreach ($selected_ids as $id) {
                $id = mysqli_real_escape_string($conn, $id);
                
                // Update student to return
                $update_sql = "UPDATE students 
                              SET is_leaver = FALSE, 
                                  class = '$return_class',
                                  status = TRUE,
                                  year_left = NULL,
                                  graduation_status = '$return_class',
                                  graduation_year = NULL,
                                  previous_class = NULL,
                                  updated_at = CURRENT_TIMESTAMP,
                                  updated_by_admin = $admin_id
                              WHERE id = $id";
                
                if (!mysqli_query($conn, $update_sql)) {
                    throw new Exception("Error returning student ID $id: " . mysqli_error($conn));
                }
                
                // Update leavers table if record exists
                $update_leaver_sql = "UPDATE student_leavers 
                                     SET returned = TRUE, 
                                         returned_at = CURRENT_TIMESTAMP 
                                     WHERE student_id = $id AND returned = FALSE";
                
                mysqli_query($conn, $update_leaver_sql);
                
                $returned_count++;
            }
            
            // Regenerate index numbers after all returns
            regenerateAllIndexNumbers($conn);
            
            mysqli_commit($conn);
            
            $_SESSION['success'] = "Successfully returned $returned_count leaver(s) to $return_class! Index numbers regenerated.";
            header("Location: leavers.php");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = $e->getMessage();
            header("Location: leavers.php");
            exit();
        }
    }
}

// Handle single return student (GET method) - UPDATED: No dormitory cleanup needed
if (isset($_GET['return_student'])) {
    $id = mysqli_real_escape_string($conn, $_GET['return_student']);
    $return_class = mysqli_real_escape_string($conn, $_GET['class'] ?? 'Form Six');
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update student to return
        $update_sql = "UPDATE students 
                      SET is_leaver = FALSE, 
                          class = '$return_class',
                          status = TRUE,
                          year_left = NULL,
                          graduation_status = '$return_class',
                          graduation_year = NULL,
                          previous_class = NULL,
                          updated_at = CURRENT_TIMESTAMP,
                          updated_by_admin = $admin_id
                      WHERE id = $id";
        
        if (!mysqli_query($conn, $update_sql)) {
            throw new Exception("Error returning student: " . mysqli_error($conn));
        }
        
        // Update leavers table if record exists
        $update_leaver_sql = "UPDATE student_leavers 
                             SET returned = TRUE, 
                                 returned_at = CURRENT_TIMESTAMP 
                             WHERE student_id = $id AND returned = FALSE";
        
        mysqli_query($conn, $update_leaver_sql);
        
        // Regenerate index numbers
        regenerateAllIndexNumbers($conn);
        
        mysqli_commit($conn);
        
        $_SESSION['success'] = "Student returned to $return_class successfully! Index numbers regenerated.";
        header("Location: leavers.php");
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
        header("Location: leavers.php");
        exit();
    }
}

// Handle single delete leaver (GET method) - UPDATED with dormitory cleanup
if (isset($_GET['delete_leaver'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete_leaver']);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // STEP 1: Clean up dormitory assignments first
        $cleaned_count = cleanupStudentDormitoryAssignments($conn, $id);
        
        // STEP 2: Delete from student_leavers table
        $delete_leaver_sql = "DELETE FROM student_leavers WHERE student_id = $id";
        if (!mysqli_query($conn, $delete_leaver_sql)) {
            throw new Exception("Error deleting from leavers table: " . mysqli_error($conn));
        }
        
        // STEP 3: Delete from students table
        $delete_student_sql = "DELETE FROM students WHERE id = $id";
        if (!mysqli_query($conn, $delete_student_sql)) {
            throw new Exception("Error deleting student: " . mysqli_error($conn));
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        $message = "Leaver deleted permanently!";
        if ($cleaned_count > 0) {
            $message .= " $cleaned_count dormitory assignments cleaned up.";
        }
        
        $_SESSION['success'] = $message;
        header("Location: leavers.php");
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
        header("Location: leavers.php");
        exit();
    }
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Leavers & Graduates</h2>
            <div class="dropdown d-md-block d-none">
                <button class="btn btn-primary dropdown-toggle" type="button" id="actionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-2"></i>Actions
                </button>
                <ul class="dropdown-menu" aria-labelledby="actionsDropdown">
                    <li><a class="dropdown-item" href="students"><i class="fas fa-users me-2"></i>Back to Students</a></li>
                    <li><a class="dropdown-item" href="register"><i class="fas fa-user-plus me-2"></i>New Student</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="report_leaver"><i class="fas fa-download me-2"></i>Export List</a></li>
                </ul>
            </div>
            <!-- Mobile Actions Button -->
            <div class="dropdown d-md-none">
                <button class="btn btn-primary" type="button" id="mobileActionsBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileActionsBtn">
                    <li><a class="dropdown-item" href="students"><i class="fas fa-users me-2"></i>Back to Students</a></li>
                    <li><a class="dropdown-item" href="register"><i class="fas fa-user-plus me-2"></i>New Student</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="report_leaver"><i class="fas fa-download me-2"></i>Export List</a></li>
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
            <!-- Total Leavers -->
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-slash" style="color: #dc3545;"></i>
                    </div>
                    <h3><?php echo count($leavers); ?></h3>
                    <p>Total Leavers</p>
                </div>
            </div>
            
            <!-- Graduates -->
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-graduation-cap" style="color: #28a745;"></i>
                    </div>
                    <h3>
                        <?php 
                        $graduates_count = 0;
                        foreach ($leavers as $leaver) {
                            if ($leaver['graduation_status'] == 'Graduated') {
                                $graduates_count++;
                            }
                        }
                        echo $graduates_count;
                        ?>
                    </h3>
                    <p>Graduates</p>
                </div>
            </div>
            
            <!-- Regular Leavers -->
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-sign-out-alt" style="color: #ffc107;"></i>
                    </div>
                    <h3>
                        <?php 
                        $leavers_count = count($leavers) - $graduates_count;
                        echo $leavers_count;
                        ?>
                    </h3>
                    <p>Regular Leavers</p>
                </div>
            </div>
            
            <!-- This Year -->
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-calendar" style="color: #17a2b8;"></i>
                    </div>
                    <h3>
                        <?php 
                        $current_year = date('Y');
                        $this_year_count = 0;
                        foreach ($leavers as $leaver) {
                            if ($leaver['year_left'] == $current_year) {
                                $this_year_count++;
                            }
                        }
                        echo $this_year_count;
                        ?>
                    </h3>
                    <p>This Year</p>
                </div>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="card mb-4" id="bulkActionsBar" style="display: none;">
            <div class="card-body py-2">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <span id="selectedCount" class="badge bg-primary me-3">0 selected</span>
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-success" id="bulkReturnBtn">
                                <i class="fas fa-undo me-1"></i> Return Selected
                            </button>
                            <button type="button" class="btn btn-outline-danger" id="bulkDeleteBtn">
                                <i class="fas fa-trash me-1"></i> Delete Selected
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" id="clearSelectionBtn">
                        <i class="fas fa-times me-1"></i> Clear Selection
                    </button>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search leavers...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select id="typeFilter" class="form-select">
                            <option value="">All Types</option>
                            <option value="Graduated">Graduates</option>
                            <option value="Left">Regular Leavers</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="yearFilter" class="form-select">
                            <option value="">All Years</option>
                            <?php
                            $years = [];
                            foreach ($leavers as $leaver) {
                                if ($leaver['year_left']) {
                                    $years[$leaver['year_left']] = $leaver['year_left'];
                                }
                            }
                            krsort($years);
                            foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
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
                        <div class="d-flex">
                            <button type="button" class="btn btn-outline-primary me-2" id="selectAllBtn">
                                <i class="fas fa-check-square me-1"></i> Select All
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="selectFilteredBtn">
                                <i class="fas fa-filter me-1"></i> Select Filtered
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leavers Table -->
        <div class="card mb-4">
            <div class="card-header" style=" background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-user-graduate me-2"></i>
                        Leavers & Graduates List
                        <span class="badge bg-light text-dark ms-2"><?php echo count($leavers); ?> Records</span>
                    </h4>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAllCheckbox">
                        <label class="form-check-label text-white" for="selectAllCheckbox">
                            Select All
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="leaversTable">
                        <thead class="table-light">
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="checkAll" class="form-check-input">
                                </th>
                                <th>S/N</th>
                                <th>Index No.</th>
                                <th>Full Name</th>
                                <th>Combination</th>
                                <th>Type</th>
                                <th>Class Left</th>
                                <th>Year Left</th>
                                <th>Reason</th>
                                <th>Left Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leavers)): ?>
                                <tr>
                                    <td colspan="11" class="text-center">No leavers or graduates found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leavers as $index => $student): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_leavers[]" value="<?php echo $student['id']; ?>" class="row-checkbox form-check-input">
                                    </td>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
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
                                                    <?php echo htmlspecialchars($student['second_name'] ?: ''); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($student['combination']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($student['graduation_status'] == 'Graduated'): ?>
                                            <span class="badge bg-success">Graduate</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Leaver</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($student['previous_class'] ?: $student['class']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark"><?php echo htmlspecialchars($student['year_left']); ?></span>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($student['reason'] ?: 'Not specified'); ?></small>
                                    </td>
                                    <td>
                                        <small><?php echo date('d/m/Y', strtotime($student['left_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-info view-leaver" 
                                                    data-bs-toggle="modal" data-bs-target="#viewLeaverModal"
                                                    data-student-id="<?php echo $student['id']; ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($student['graduation_status'] == 'Graduated'): ?>
                                                <button type="button" class="btn btn-outline-success return-graduate" 
                                                        data-id="<?php echo $student['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                        title="Return to Form Six">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-success return-leaver" 
                                                        data-id="<?php echo $student['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                        data-previous-class="<?php echo htmlspecialchars($student['previous_class']); ?>"
                                                        title="Return to Students">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-danger delete-leaver" 
                                                    data-id="<?php echo $student['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                    title="Delete Permanently">
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

<!-- Bulk Actions Forms (Hidden) -->
<form method="POST" id="bulkReturnForm" style="display: none;">
    <input type="hidden" name="bulk_return" value="1">
    <input type="hidden" name="return_class" id="bulkReturnClassInput" value="Form Five">
    <div id="bulkReturnCheckboxes"></div>
</form>

<form method="POST" id="bulkDeleteForm" style="display: none;">
    <input type="hidden" name="bulk_delete" value="1">
    <div id="bulkDeleteCheckboxes"></div>
</form>

<!-- View Leaver Modal -->
<div class="modal fade" id="viewLeaverModal" tabindex="-1" aria-labelledby="viewLeaverModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="viewLeaverModalLabel">Leaver Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="leaverDetails">
                <!-- Leaver details will be loaded here via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Return Graduate Modal -->
<div class="modal fade" id="returnGraduateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-undo me-2"></i>Return Graduate</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-user-graduate fa-3x text-success mb-3"></i>
                <h5 class="mb-3">Return graduate to Form Six?</h5>
                <p class="mb-2"><strong id="returnGraduateName"></strong></p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Graduates can only be returned to Form Six. This will:
                    <ul class="text-start mt-2 mb-0">
                        <li>Return student to Form Six class</li>
                        <li>Reactivate student status</li>
                        <li>Regenerate index numbers</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmReturnGraduate" class="btn btn-success">
                    <i class="fas fa-undo me-2"></i>Return to Form Six
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Return Leaver Modal -->
<div class="modal fade" id="returnLeaverModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-undo me-2"></i>Return Leaver</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-user-check fa-3x text-warning mb-3"></i>
                <h5 class="mb-3">Return leaver to which class?</h5>
                <p class="mb-2"><strong id="returnLeaverName"></strong></p>
                <div class="form-group mt-3">
                    <label for="returnLeaverClass" class="form-label">Return to class:</label>
                    <select id="returnLeaverClass" class="form-select">
                        <option value="Form Five">Form Five</option>
                        <option value="Form Six">Form Six</option>
                    </select>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    This will return the student to active students list and regenerate index numbers.
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmReturnLeaver" class="btn btn-warning">
                    <i class="fas fa-undo me-2"></i>Return Student
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Leaver Confirmation Modal -->
<div class="modal fade" id="deleteLeaverModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Permanently</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                <h5 class="mb-3">Delete leaver permanently?</h5>
                <p class="mb-2"><strong id="deleteLeaverName"></strong></p>
                <p class="text-danger">
                    <small>
                        <i class="fas fa-exclamation-circle me-1"></i>
                        This will permanently delete the student from both students and leavers tables.<br>
                        This action cannot be undone!
                    </small>
                </p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteLeaver" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete Permanently
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
});

// Bulk selection functionality
let selectedCount = 0;
const bulkActionsBar = document.getElementById('bulkActionsBar');
const selectedCountSpan = document.getElementById('selectedCount');
const checkAllCheckbox = document.getElementById('checkAll');
const rowCheckboxes = document.querySelectorAll('.row-checkbox');
const bulkReturnForm = document.getElementById('bulkReturnForm');
const bulkDeleteForm = document.getElementById('bulkDeleteForm');
const bulkReturnClassInput = document.getElementById('bulkReturnClassInput');

// Update selection count
function updateSelectionCount() {
    selectedCount = document.querySelectorAll('.row-checkbox:checked').length;
    selectedCountSpan.textContent = `${selectedCount} selected`;
    
    // Show/hide bulk actions bar
    if (selectedCount > 0) {
        bulkActionsBar.style.display = 'block';
    } else {
        bulkActionsBar.style.display = 'none';
    }
    
    // Update check all checkbox state
    const totalRows = rowCheckboxes.length;
    checkAllCheckbox.checked = selectedCount === totalRows;
    document.getElementById('selectAllCheckbox').checked = selectedCount === totalRows;
}

// Select all checkboxes
checkAllCheckbox.addEventListener('change', function() {
    rowCheckboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelectionCount();
});

// Select all checkbox in header
document.getElementById('selectAllCheckbox').addEventListener('change', function() {
    rowCheckboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    checkAllCheckbox.checked = this.checked;
    updateSelectionCount();
});

// Individual checkbox change
rowCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', updateSelectionCount);
});

// Clear selection button
document.getElementById('clearSelectionBtn').addEventListener('click', function() {
    rowCheckboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    checkAllCheckbox.checked = false;
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectionCount();
});

// Select all visible rows
document.getElementById('selectAllBtn').addEventListener('click', function() {
    const visibleRows = document.querySelectorAll('#leaversTable tbody tr:not([style*="display: none"])');
    visibleRows.forEach(row => {
        const checkbox = row.querySelector('.row-checkbox');
        if (checkbox) {
            checkbox.checked = true;
        }
    });
    updateSelectionCount();
});

// Select filtered rows
document.getElementById('selectFilteredBtn').addEventListener('click', function() {
    const visibleRows = document.querySelectorAll('#leaversTable tbody tr:not([style*="display: none"])');
    
    // Uncheck all first
    rowCheckboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Check only visible rows
    visibleRows.forEach(row => {
        const checkbox = row.querySelector('.row-checkbox');
        if (checkbox) {
            checkbox.checked = true;
        }
    });
    updateSelectionCount();
});

// Bulk Return Button
document.getElementById('bulkReturnBtn').addEventListener('click', function() {
    const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        Swal.fire({
            title: 'Error!',
            text: 'Please select at least one leaver to return.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    // Get selected student names for confirmation
    const selectedNames = [];
    selectedCheckboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const name = row.querySelector('td:nth-child(4) strong').textContent;
        selectedNames.push(name);
    });
    
    Swal.fire({
        title: 'Return Selected Leavers',
        html: `
            <div class="text-start">
                <p>You are about to return <strong>${selectedCheckboxes.length}</strong> leaver(s).</p>
                <p>Please select the class to return them to:</p>
                <select id="swalReturnClass" class="form-select mt-2">
                    <option value="Form Five">Form Five</option>
                    <option value="Form Six">Form Six</option>
                </select>
                <div class="mt-3">
                    <strong>Selected leavers:</strong>
                    <div class="mt-1 small text-muted" style="max-height: 100px; overflow-y: auto;">
                        ${selectedNames.map(name => `<div>• ${name}</div>`).join('')}
                    </div>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Return Selected',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const returnClass = document.getElementById('swalReturnClass').value;
            if (!returnClass) {
                Swal.showValidationMessage('Please select a class');
                return false;
            }
            return returnClass;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Prepare form data
            const returnClass = result.value;
            bulkReturnClassInput.value = returnClass;
            
            // Clear previous checkboxes
            document.getElementById('bulkReturnCheckboxes').innerHTML = '';
            
            // Add selected checkboxes to form
            selectedCheckboxes.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'selected_leavers[]';
                hiddenInput.value = checkbox.value;
                document.getElementById('bulkReturnCheckboxes').appendChild(hiddenInput);
            });
            
            // Submit form
            bulkReturnForm.submit();
        }
    });
});

// Bulk Delete Button
document.getElementById('bulkDeleteBtn').addEventListener('click', function() {
    const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        Swal.fire({
            title: 'Error!',
            text: 'Please select at least one leaver to delete.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    // Get selected student names for confirmation
    const selectedNames = [];
    selectedCheckboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const name = row.querySelector('td:nth-child(4) strong').textContent;
        selectedNames.push(name);
    });
    
    Swal.fire({
        title: 'Delete Selected Leavers',
        html: `
            <div class="text-start">
                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone!</p>
                <p>You are about to permanently delete <strong>${selectedCheckboxes.length}</strong> leaver(s).</p>
                <div class="mt-3">
                    <strong>Selected leavers for deletion:</strong>
                    <div class="mt-1 small text-danger" style="max-height: 100px; overflow-y: auto;">
                        ${selectedNames.map(name => `<div>• ${name}</div>`).join('')}
                    </div>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete Permanently',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Clear previous checkboxes
            document.getElementById('bulkDeleteCheckboxes').innerHTML = '';
            
            // Add selected checkboxes to form
            selectedCheckboxes.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'selected_leavers[]';
                hiddenInput.value = checkbox.value;
                document.getElementById('bulkDeleteCheckboxes').appendChild(hiddenInput);
            });
            
            // Submit form
            bulkDeleteForm.submit();
        }
    });
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const leaverRows = document.querySelectorAll('#leaversTable tbody tr');
    
    leaverRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
    
    updateSelectionCount();
});

// Filter functionality
document.getElementById('typeFilter').addEventListener('change', filterLeavers);
document.getElementById('yearFilter').addEventListener('change', filterLeavers);
document.getElementById('classFilter').addEventListener('change', filterLeavers);

function filterLeavers() {
    const type = document.getElementById('typeFilter').value;
    const year = document.getElementById('yearFilter').value;
    const classFilter = document.getElementById('classFilter').value;
    
    const leaverRows = document.querySelectorAll('#leaversTable tbody tr');
    leaverRows.forEach(row => {
        if (row.cells.length < 11) return;
        
        const rowType = row.cells[5].querySelector('.badge')?.textContent.trim() || '';
        const rowYear = row.cells[7].querySelector('.badge')?.textContent.trim() || '';
        const rowClass = row.cells[6].querySelector('.badge')?.textContent.trim() || '';
        
        const showType = !type || 
            (type === 'Graduated' && rowType === 'Graduate') ||
            (type === 'Left' && rowType === 'Leaver');
        const showYear = !year || rowYear === year;
        const showClass = !classFilter || rowClass === classFilter;
        
        row.style.display = (showType && showYear && showClass) ? '' : 'none';
    });
    
    updateSelectionCount();
}

// View leaver details
document.addEventListener('DOMContentLoaded', function() {
    const viewButtons = document.querySelectorAll('.view-leaver');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.getAttribute('data-student-id');
            
            document.getElementById('leaverDetails').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading leaver details...</p>
                </div>
            `;
            
            fetch(`get_student.php?id=${studentId}&leaver=1`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('leaverDetails').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('leaverDetails').innerHTML = 
                        '<div class="alert alert-danger">Error loading leaver details.</div>';
                });
        });
    });
});

// Return graduate confirmation
document.addEventListener('DOMContentLoaded', function() {
    const returnGraduateButtons = document.querySelectorAll('.return-graduate');
    returnGraduateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.getAttribute('data-id');
            const studentName = this.getAttribute('data-name');
            
            document.getElementById('returnGraduateName').textContent = studentName;
            document.getElementById('confirmReturnGraduate').href = `leavers.php?return_student=${studentId}&class=Form Six`;
            
            const returnModal = new bootstrap.Modal(document.getElementById('returnGraduateModal'));
            returnModal.show();
        });
    });
});

// Return leaver confirmation
document.addEventListener('DOMContentLoaded', function() {
    const returnLeaverButtons = document.querySelectorAll('.return-leaver');
    returnLeaverButtons.forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.getAttribute('data-id');
            const studentName = this.getAttribute('data-name');
            const previousClass = this.getAttribute('data-previous-class') || 'Form Five';
            
            document.getElementById('returnLeaverName').textContent = studentName;
            document.getElementById('returnLeaverClass').value = previousClass;
            document.getElementById('confirmReturnLeaver').href = `leavers.php?return_student=${studentId}&class=${previousClass}`;
            
            const returnModal = new bootstrap.Modal(document.getElementById('returnLeaverModal'));
            returnModal.show();
        });
    });
    
    // Update return URL when class selection changes
    document.getElementById('returnLeaverClass').addEventListener('change', function() {
        const studentId = document.querySelector('#returnLeaverModal .return-leaver')?.getAttribute('data-id');
        if (studentId) {
            document.getElementById('confirmReturnLeaver').href = `leavers.php?return_student=${studentId}&class=${this.value}`;
        }
    });
});

// Delete leaver confirmation
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-leaver');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.getAttribute('data-id');
            const studentName = this.getAttribute('data-name');
            
            document.getElementById('deleteLeaverName').textContent = studentName;
            document.getElementById('confirmDeleteLeaver').href = `leavers.php?delete_leaver=${studentId}`;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteLeaverModal'));
            deleteModal.show();
        });
    });
});

// Export leavers list
document.addEventListener('DOMContentLoaded', function() {
    const exportBtn = document.getElementById('exportLeaversBtn');
    const exportBtnMobile = document.getElementById('exportLeaversBtnMobile');
    
    if (exportBtn) {
        exportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'export_leavers.php';
        });
    }
    
    if (exportBtnMobile) {
        exportBtnMobile.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'export_leavers.php';
        });
    }
});

// Initialize selection count
updateSelectionCount();
</script>

<style>
/* LEAVERS PAGE SPECIFIC STYLES */
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: rgba(59, 157, 179, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
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
}

.stats-card.simple-card:nth-child(1)::before { background: #dc3545; }
.stats-card.simple-card:nth-child(2)::before { background: #28a745; }
.stats-card.simple-card:nth-child(3)::before { background: #ffc107; }
.stats-card.simple-card:nth-child(4)::before { background: #17a2b8; }

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
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.stats-card.simple-card p {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
    font-weight: 500;
}

.table th {
    font-weight: 600;
    color: #333;
    background-color: rgba(59, 157, 179, 0.05);
    border-bottom: 2px solid rgba(59, 157, 179, 0.2);
    padding: 12px 8px;
}

.btn-outline-info, .btn-outline-warning, .btn-outline-secondary, 
.btn-outline-danger, .btn-outline-dark, .btn-outline-success {
    border-width: 1px;
    transition: all 0.2s ease;
}

.btn-outline-info:hover, .btn-outline-warning:hover, .btn-outline-secondary:hover,
.btn-outline-danger:hover, .btn-outline-dark:hover, .btn-outline-success:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Bulk Actions Bar */
#bulkActionsBar {
    background-color: #f8f9fa;
    border-left: 4px solid #007bff;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .stats-card.simple-card {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
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
    
    .avatar-circle {
        width: 35px;
        height: 35px;
        margin-right: 10px;
    }
    
    .dropdown-menu {
        position: absolute;
        right: 0;
        left: auto;
    }
    
    /* Adjust table for mobile */
    #leaversTable {
        font-size: 0.85rem;
    }
    
    #leaversTable th,
    #leaversTable td {
        padding: 6px 4px;
    }
    
    /* Bulk actions responsive */
    #bulkActionsBar .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    #bulkActionsBar .btn {
        width: 100%;
        margin-bottom: 5px;
    }
}

/* Desktop Actions */
@media (min-width: 769px) {
    .btn-group {
        display: flex;
        flex-wrap: nowrap;
    }
    
    .btn-group .btn {
        margin: 0 2px;
    }
}

/* Checkbox styling */
.form-check-input:checked {
    background-color: #007bff;
    border-color: #007bff;
}

.table th:first-child,
.table td:first-child {
    width: 50px;
    text-align: center;
}

/* Highlight selected rows */
.row-checkbox:checked + td {
    background-color: rgba(0, 123, 255, 0.05);
}

/* SweetAlert2 custom styles */
.swal2-popup .form-select {
    width: 100%;
    padding: 0.375rem 2.25rem 0.375rem 0.75rem;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.swal2-popup .form-select:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
</style>

<?php include '../controller/footer.php'; ?>