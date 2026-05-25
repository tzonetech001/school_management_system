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
    if ($role_id == 1 || $role_id == 2 || $role_id == 3 || $role_id == 16) { // Head Master or Second Master
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view page you need.";
    header("Location: ../404.php");
    exit();
}
// Auto-return items for students who have left but still have active assignments
$check_leavers_sql = "SELECT ma.id as assignment_id, ma.student_id, mi.id as item_id 
                     FROM maintenance_assignments ma
                     JOIN students s ON ma.student_id = s.id
                     JOIN maintenance_items mi ON ma.item_id = mi.id
                     WHERE ma.status = 'active' 
                     AND s.is_leaver = 1";
$check_leavers_result = mysqli_query($conn, $check_leavers_sql);

if (mysqli_num_rows($check_leavers_result) > 0) {
    mysqli_begin_transaction($conn);
    try {
        while ($leaver = mysqli_fetch_assoc($check_leavers_result)) {
            // Update assignment as returned due to student leaving
            $update_assignment_sql = "UPDATE maintenance_assignments SET 
                                     status = 'returned',
                                     return_date = CURDATE(),
                                     return_condition = 'good',
                                     return_notes = 'Auto-returned: Student left/graduated'
                                     WHERE id = {$leaver['assignment_id']}";
            mysqli_query($conn, $update_assignment_sql);
            
            // Update item status back to available
            $update_item_sql = "UPDATE maintenance_items SET status = 'available' WHERE id = {$leaver['item_id']}";
            mysqli_query($conn, $update_item_sql);
            
            // Log the auto-return
            $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                       VALUES ({$leaver['item_id']}, 'auto_return', 'student', {$leaver['student_id']}, $admin_id, 
                       'Auto-returned item due to student leaving/graduating')";
            mysqli_query($conn, $log_sql);
        }
        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error auto-returning items for leavers: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['assign_item'])) {
        $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
        $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
        
        // Get selected items
        $selected_items = [];
        
        if (isset($_POST['table_id']) && !empty($_POST['table_id'])) {
            $selected_items[] = [
                'id' => mysqli_real_escape_string($conn, $_POST['table_id']),
                'type' => 'table'
            ];
        }
        
        if (isset($_POST['chair_id']) && !empty($_POST['chair_id'])) {
            $selected_items[] = [
                'id' => mysqli_real_escape_string($conn, $_POST['chair_id']),
                'type' => 'chair'
            ];
        }
        
        // Check if no items selected
        if (empty($selected_items)) {
            $_SESSION['error'] = "Please select at least one item (table or chair) to assign.";
            header("Location: student_main.php");
            exit();
        }
        
        // Check for existing assignments
        $errors = [];
        foreach ($selected_items as $item) {
            $check_sql = "SELECT id FROM maintenance_assignments 
                         WHERE student_id = $student_id 
                         AND assignment_type = '{$item['type']}'
                         AND status = 'active'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                $errors[] = "This student already has an active {$item['type']} assignment.";
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode(" ", $errors);
        } else {
            // Start transaction
            mysqli_begin_transaction($conn);
            $success_count = 0;
            
            try {
                // Get student info for logging
                $student_sql = "SELECT first_name, last_name, is_leaver FROM students WHERE id = $student_id";
                $student_result = mysqli_query($conn, $student_sql);
                $student = mysqli_fetch_assoc($student_result);
                $student_name = $student['first_name'] . ' ' . $student['last_name'];
                $student_status = $student['is_leaver'] ? ' (LEFT)' : '';
                
                foreach ($selected_items as $item) {
                    // Check if item is available
                    $item_sql = "SELECT status, item_code FROM maintenance_items WHERE id = {$item['id']}";
                    $item_result = mysqli_query($conn, $item_sql);
                    $item_data = mysqli_fetch_assoc($item_result);
                    
                    if ($item_data['status'] != 'available') {
                        throw new Exception("Selected {$item['type']} ({$item_data['item_code']}) is not available for assignment.");
                    }
                    
                    // Insert assignment
                    $insert_sql = "INSERT INTO maintenance_assignments 
                                  (student_id, item_id, assignment_type, assigned_by, assigned_date, due_date, notes) 
                                  VALUES ($student_id, {$item['id']}, '{$item['type']}', $admin_id, CURDATE(), '$due_date', '$notes')";
                    
                    if (!mysqli_query($conn, $insert_sql)) {
                        throw new Exception("Error assigning {$item['type']}: " . mysqli_error($conn));
                    }
                    
                    // Update item status
                    $update_item_sql = "UPDATE maintenance_items SET status = 'assigned' WHERE id = {$item['id']}";
                    if (!mysqli_query($conn, $update_item_sql)) {
                        throw new Exception("Error updating {$item['type']} status: " . mysqli_error($conn));
                    }
                    
                    // Log the action
                    $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                               VALUES ({$item['id']}, 'assignment', 'student', $student_id, $admin_id, 
                               'Assigned {$item_data['item_code']} ({$item['type']}) to student: $student_name$student_status')";
                    if (!mysqli_query($conn, $log_sql)) {
                        throw new Exception("Error logging assignment: " . mysqli_error($conn));
                    }
                    
                    $success_count++;
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                if ($success_count == 1) {
                    $_SESSION['success'] = "Item assigned successfully to student!";
                } else {
                    $_SESSION['success'] = "$success_count items assigned successfully to student!";
                }
                
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                $_SESSION['error'] = $e->getMessage();
            }
        }
        
        header("Location: student_main.php");
        exit();
        
    } elseif (isset($_POST['return_item'])) {
        $assignment_id = mysqli_real_escape_string($conn, $_POST['assignment_id']);
        $return_condition = mysqli_real_escape_string($conn, $_POST['return_condition']);
        $return_notes = mysqli_real_escape_string($conn, $_POST['return_notes']);
        
        // Set default return condition to 'good' if not provided
        if (empty($return_condition)) {
            $return_condition = 'good';
        }
        
        // Get assignment details
        $assignment_sql = "SELECT ma.*, mi.item_code, s.first_name, s.last_name, s.is_leaver
                          FROM maintenance_assignments ma
                          JOIN maintenance_items mi ON ma.item_id = mi.id
                          JOIN students s ON ma.student_id = s.id
                          WHERE ma.id = $assignment_id";
        $assignment_result = mysqli_query($conn, $assignment_sql);
        $assignment = mysqli_fetch_assoc($assignment_result);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update assignment status
            $update_sql = "UPDATE maintenance_assignments SET 
                          status = 'returned',
                          return_date = CURDATE(),
                          return_condition = '$return_condition',
                          return_notes = '$return_notes'
                          WHERE id = $assignment_id";
            
            if (!mysqli_query($conn, $update_sql)) {
                throw new Exception("Error returning item: " . mysqli_error($conn));
            }
            
            // Update item status based on return condition
            $new_status = ($return_condition == 'good') ? 'available' : 'damaged';
            $update_item_sql = "UPDATE maintenance_items SET status = '$new_status' WHERE id = {$assignment['item_id']}";
            if (!mysqli_query($conn, $update_item_sql)) {
                throw new Exception("Error updating item status: " . mysqli_error($conn));
            }
            
            // Log the action
            $student_name = $assignment['first_name'] . ' ' . $assignment['last_name'];
            $student_status = $assignment['is_leaver'] ? ' (LEFT)' : '';
            $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                       VALUES ({$assignment['item_id']}, 'return', 'student', {$assignment['student_id']}, $admin_id, 
                       'Returned {$assignment['item_code']} from student: $student_name$student_status. Condition: $return_condition')";
            if (!mysqli_query($conn, $log_sql)) {
                throw new Exception("Error logging return: " . mysqli_error($conn));
            }
            
            // Commit transaction
            mysqli_commit($conn);
            $_SESSION['success'] = "Item returned successfully!";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: student_main.php");
        exit();
    }
}

// Get all active student assignments - SORTED BY INDEX NUMBER ASCENDING
$assignments_sql = "SELECT ma.*, 
                   mi.item_code, mi.item_type, mi.description as item_description,
                   s.index_number, s.first_name, s.last_name, s.class, s.combination, s.sex, s.is_leaver,
                   CONCAT(s.first_name, ' ', s.last_name) as student_name,
                   a.first_name as assigned_by_fname, a.last_name as assigned_by_lname
                   FROM maintenance_assignments ma
                   JOIN maintenance_items mi ON ma.item_id = mi.id
                   JOIN students s ON ma.student_id = s.id
                   LEFT JOIN admins a ON ma.assigned_by = a.id
                   WHERE ma.status = 'active'
                   ORDER BY s.index_number ASC, ma.assigned_date DESC";
$assignments_result = mysqli_query($conn, $assignments_sql);
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Assign Iteims to Students</h2>
             
             <!-- Action Button with Dropdown -->
                <div class="dropdown">
                    <!-- <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#assignModal">
                                                <i class="fas fa-plus me-2"></i>Assign Student
                                            </button> -->
                    <button class="btn btn-primary dropdown-toggle" type="button" id="actionDropdown" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog me-2"></i>Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionDropdown">
                        <li><a class="dropdown-item" href="maintenance.php">
                            <i class="fas fa-tools me-2"></i>Dashboard
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="fas fa-plus me-2"></i>Assign Student</a></li>
                         <li><hr class="dropdown-divider"></li>
                         <li><a class="dropdown-item" href="staff_main.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Assign Staff
                        </a></li>
                         <li><a class="dropdown-item" href="inventory.php">
                            <i class="fas fa-list-alt me-2"></i>View Inventory
                        </a></li>
                        
                        <li><a class="dropdown-item" href="maintenance_logs.php">
                            <i class="fas fa-history me-2"></i>View Logs
                        </a></li>
                        <li><a class="dropdown-item" href="report_maintenance.php"><i class="fas fa-download me-2"></i>Export List</a></li>
                    </ul>
                </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Active Assignments with Search -->
        <div class="card mb-4">
            <div class="card-header" style="background-color: #3B9DB3; color: white;">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-list-check me-2"></i>
                        Active Student Assignments
                        <span class="badge bg-light text-dark ms-2" id="assignmentCount"><?php echo mysqli_num_rows($assignments_result); ?> Active</span>
                    </h4>
                    <div class="d-flex align-items-center">
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="tableSearchInput" class="form-control" placeholder="Search any field...">
                            <button class="btn btn-outline-light" type="button" id="clearSearch">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <button class="btn btn-outline-light btn-sm ms-2" id="toggleSort" title="Toggle Sort Order">
                            <i class="fas fa-sort-alpha-down" id="sortIcon"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="assignmentsTable">
                        <thead class="table-light">
                            <tr>
                                <th data-sort="index_number">Student 
                                    <i class="fas fa-sort-up float-end" data-column="index_number"></i>
                                </th>
                                <th data-sort="item_code">Item Details 
                                    <i class="fas fa-sort float-end" data-column="item_code"></i>
                                </th>
                                <th data-sort="assigned_date">Assignment Date 
                                    <i class="fas fa-sort float-end" data-column="assigned_date"></i>
                                </th>
                                <th data-sort="due_date">Due Date 
                                    <i class="fas fa-sort float-end" data-column="due_date"></i>
                                </th>
                                <th data-sort="is_leaver">Student Status 
                                    <i class="fas fa-sort float-end" data-column="is_leaver"></i>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="assignmentsTableBody">
                            <?php if (mysqli_num_rows($assignments_result) == 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                                            <h5>No Active Assignments</h5>
                                            <p class="text-muted">No maintenance items are currently assigned to students.</p>
                                            <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#assignModal">
                                                <i class="fas fa-plus me-2"></i>Assign First Item
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($assignment = mysqli_fetch_assoc($assignments_result)): ?>
                                <tr class="<?php echo $assignment['is_leaver'] ? 'table-warning' : ''; ?>" 
                                    data-index="<?php echo htmlspecialchars($assignment['index_number']); ?>"
                                    data-name="<?php echo htmlspecialchars($assignment['student_name']); ?>"
                                    data-item="<?php echo htmlspecialchars($assignment['item_code']); ?>"
                                    data-type="<?php echo $assignment['assignment_type']; ?>"
                                    data-class="<?php echo htmlspecialchars($assignment['class']); ?>"
                                    data-date="<?php echo $assignment['assigned_date']; ?>"
                                    data-due="<?php echo $assignment['due_date']; ?>"
                                    data-status="<?php echo $assignment['is_leaver'] ? 'Left' : 'Active'; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <?php if ($assignment['sex'] == 'Male'): ?>
                                                    <i class="fas fa-male text-primary"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-female" style="color: #e83e8c;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($assignment['student_name']); ?></strong>
                                                <div class="small text-muted">
                                                    <span class="index-number"><?php echo htmlspecialchars($assignment['index_number']); ?></span> • 
                                                    <?php echo htmlspecialchars($assignment['class']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong class="text-primary item-code"><?php echo htmlspecialchars($assignment['item_code']); ?></strong>
                                            <div class="small text-muted">
                                                <?php echo ucfirst($assignment['assignment_type']); ?> • 
                                                <?php echo htmlspecialchars($assignment['item_description'] ?: 'No description'); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="assigned-date">
                                        <?php echo date('M d, Y', strtotime($assignment['assigned_date'])); ?>
                                    </td>
                                    <td class="due-date">
                                        <?php if ($assignment['due_date']): ?>
                                            <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                            <?php if (strtotime($assignment['due_date']) < time()): ?>
                                                <span class="badge bg-danger ms-1">Overdue</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No due date</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="student-status">
                                        <?php if ($assignment['is_leaver']): ?>
                                            <span class="badge bg-warning">Left/Graduated</span>
                                            <button type="button" class="btn btn-sm btn-outline-warning ms-1" 
                                                    onclick="forceReturnForLeaver(<?php echo $assignment['id']; ?>)"
                                                    title="Force return item">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-warning return-item" 
                                                    data-bs-toggle="modal" data-bs-target="#returnModal"
                                                    data-id="<?php echo $assignment['id']; ?>"
                                                    data-item-code="<?php echo htmlspecialchars($assignment['item_code']); ?>"
                                                    data-student-name="<?php echo htmlspecialchars($assignment['student_name']); ?>"
                                                    title="Return Item">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-info view-assignment" 
                                                    data-bs-toggle="modal" data-bs-target="#viewAssignmentModal"
                                                    data-id="<?php echo $assignment['id']; ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="assignForm">
                <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                    <h5 class="modal-title" id="assignModalLabel">Assign Items to Student</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Student Search -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Student <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="studentSearch" class="form-control" 
                                   placeholder="Start typing student name or index number..." 
                                   autocomplete="off">
                            <input type="hidden" id="student_id" name="student_id" required>
                        </div>
                        <div id="studentSearchResults" class="mt-2 border rounded shadow-lg" 
                             style="display: none; max-height: 300px; overflow-y: auto; z-index: 9999; position: absolute; width: 93%; background: white;">
                            <!-- Results will appear here -->
                        </div>
                        <div id="selectedStudent" class="mt-3 d-none">
                            <div class="alert alert-success d-flex align-items-center">
                                <i class="fas fa-check-circle me-3 fs-4"></i>
                                <div>
                                    <strong id="selectedStudentName"></strong>
                                    <div class="small">
                                        <span id="selectedStudentIndex" class="me-3"></span>
                                        <span id="selectedStudentClass"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Item Selection - BOTH Table and Chair can be selected -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-table me-2"></i>Assign Table</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Search Tables</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                            <input type="text" id="tableSearch" class="form-control" 
                                                   placeholder="Type table code or description..." 
                                                   autocomplete="off" disabled>
                                        </div>
                                        <div id="tableSearchResults" class="mt-2 border rounded shadow-sm" 
                                             style="display: none; max-height: 200px; overflow-y: auto; z-index: 9998; position: absolute; width: 90%; background: white;">
                                            <!-- Table results will appear here -->
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <button type="button" class="btn btn-outline-primary w-100" onclick="openTableListModal()">
                                            <i class="fas fa-list me-2"></i>View All Available Tables
                                        </button>
                                    </div>
                                    <div id="selectedTable" class="mt-3 d-none">
                                        <div class="alert alert-info d-flex align-items-center">
                                            <i class="fas fa-table me-3"></i>
                                            <div>
                                                <strong id="selectedTableCode"></strong>
                                                <div class="small" id="selectedTableDescription"></div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="clearTableSelection()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-chair me-2"></i>Assign Chair</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Search Chairs</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                            <input type="text" id="chairSearch" class="form-control" 
                                                   placeholder="Type chair code or description..." 
                                                   autocomplete="off" disabled>
                                        </div>
                                        <div id="chairSearchResults" class="mt-2 border rounded shadow-sm" 
                                             style="display: none; max-height: 200px; overflow-y: auto; z-index: 9998; position: absolute; width: 90%; background: white;">
                                            <!-- Chair results will appear here -->
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <button type="button" class="btn btn-outline-info w-100" onclick="openChairListModal()">
                                            <i class="fas fa-list me-2"></i>View All Available Chairs
                                        </button>
                                    </div>
                                    <div id="selectedChair" class="mt-3 d-none">
                                        <div class="alert alert-info d-flex align-items-center">
                                            <i class="fas fa-chair me-3"></i>
                                            <div>
                                                <strong id="selectedChairCode"></strong>
                                                <div class="small" id="selectedChairDescription"></div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="clearChairSelection()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Summary of selected items -->
                    <div id="selectedItemsSummary" class="mt-3 d-none">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Items to Assign</h6>
                            </div>
                            <div class="card-body">
                                <div class="row" id="selectedItemsList">
                                    <!-- Selected items will appear here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Current Assignments for Selected Student -->
                    <div id="currentAssignments" class="mt-4 d-none">
                        <h6>Current Assignments for <span id="currentStudentName"></span>:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm" id="currentAssignmentsTable">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Assigned Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="row mt-4">
                        <div class="col-md-6 mb-3">
                            <label for="due_date" class="form-label fw-bold">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" 
                                   value="<?php echo date('Y-m-d'); ?>">
                            <small class="text-muted">Set when this assignment should be returned</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="notes" class="form-label fw-bold">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Enter any notes about this assignment"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_item" class="btn btn-primary" id="assignButton" disabled>
                        <i class="fas fa-save me-2"></i>Assign Selected Items
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Table List Modal -->
<div class="modal fade" id="tableListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-table me-2"></i>Available Tables</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="tableListSearch" class="form-control" placeholder="Search tables...">
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>Location</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableListBody">
                            <!-- Tables will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Chair List Modal -->
<div class="modal fade" id="chairListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-chair me-2"></i>Available Chairs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="chairListSearch" class="form-control" placeholder="Search chairs...">
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>Location</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="chairListBody">
                            <!-- Chairs will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1" aria-labelledby="returnModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" id="return_assignment_id" name="assignment_id">
                <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                    <h5 class="modal-title" id="returnModalLabel">Return Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-undo fa-3x text-primary mb-3"></i>
                        <h5>Return Item</h5>
                        <p>Returning: <strong id="returnItemCode"></strong></p>
                        <p>From: <strong id="returnStudentName"></strong></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="return_condition" class="form-label fw-bold">Return Condition</label>
                        <select class="form-select" id="return_condition" name="return_condition">
                            <option value="good" selected>Good - Ready for reassignment</option>
                            <option value="damaged">Damaged - Needs repair</option>
                            <option value="lost">Lost</option>
                        </select>
                        <div class="form-text">Default is 'Good' - item will be available for another student</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="return_notes" class="form-label fw-bold">Return Notes</label>
                        <textarea class="form-control" id="return_notes" name="return_notes" rows="3" placeholder="Enter notes about the return condition"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="return_item" class="btn btn-primary">
                        <i class="fas fa-undo me-2"></i>Confirm Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Assignment Modal -->
<div class="modal fade" id="viewAssignmentModal" tabindex="-1" aria-labelledby="viewAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                <h5 class="modal-title" id="viewAssignmentModalLabel">Assignment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="assignmentDetails">
                <!-- Assignment details will be loaded here via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Table search and sort functionality
    const tableSearchInput = document.getElementById('tableSearchInput');
    const clearSearchBtn = document.getElementById('clearSearch');
    const toggleSortBtn = document.getElementById('toggleSort');
    const sortIcon = document.getElementById('sortIcon');
    const assignmentCount = document.getElementById('assignmentCount');
    const assignmentsTableBody = document.getElementById('assignmentsTableBody');
    const tableHeaders = document.querySelectorAll('th[data-sort]');
    
    let currentSortColumn = 'index_number';
    let currentSortDirection = 'asc'; // 'asc' or 'desc'
    let allAssignments = [];
    
    // Initialize all assignments data
    function initializeAssignmentsData() {
        allAssignments = [];
        const rows = assignmentsTableBody.querySelectorAll('tr');
        rows.forEach(row => {
            if (row.cells.length >= 5) {
                const assignment = {
                    row: row,
                    indexNumber: row.getAttribute('data-index') || '',
                    studentName: row.getAttribute('data-name') || '',
                    itemCode: row.getAttribute('data-item') || '',
                    itemType: row.getAttribute('data-type') || '',
                    studentClass: row.getAttribute('data-class') || '',
                    assignedDate: row.getAttribute('data-date') || '',
                    dueDate: row.getAttribute('data-due') || '',
                    status: row.getAttribute('data-status') || ''
                };
                allAssignments.push(assignment);
            }
        });
    }
    
    // Initialize data
    if (assignmentsTableBody.querySelector('tr').cells.length > 1) {
        initializeAssignmentsData();
    }
    
    // Search functionality
    tableSearchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        filterTable(searchTerm);
    });
    
    // Clear search
    clearSearchBtn.addEventListener('click', function() {
        tableSearchInput.value = '';
        filterTable('');
        tableSearchInput.focus();
    });
    
    // Toggle sort order
    toggleSortBtn.addEventListener('click', function() {
        // Toggle between index_number and name sorting
        if (currentSortColumn === 'index_number') {
            currentSortColumn = 'student_name';
            sortIcon.className = 'fas fa-sort-alpha-down';
        } else {
            currentSortColumn = 'index_number';
            sortIcon.className = 'fas fa-sort-numeric-down';
        }
        sortTable();
    });
    
    // Column header sorting
    tableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            setSortColumn(column);
        });
    });
    
    // Set sort column and direction
    function setSortColumn(column) {
        if (currentSortColumn === column) {
            // Toggle direction if same column
            currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            // New column, default to asc
            currentSortColumn = column;
            currentSortDirection = 'asc';
        }
        
        // Update sort icons
        updateSortIcons();
        sortTable();
    }
    
    // Update sort icons
    function updateSortIcons() {
        tableHeaders.forEach(header => {
            const icon = header.querySelector('i');
            const column = header.getAttribute('data-sort');
            
            if (column === currentSortColumn) {
                icon.className = currentSortDirection === 'asc' ? 'fas fa-sort-up float-end' : 'fas fa-sort-down float-end';
            } else {
                icon.className = 'fas fa-sort float-end';
            }
        });
    }
    
    // Filter table based on search term
    function filterTable(searchTerm) {
        if (allAssignments.length === 0) return;
        
        let visibleCount = 0;
        
        allAssignments.forEach(assignment => {
            const searchableText = `
                ${assignment.indexNumber.toLowerCase()}
                ${assignment.studentName.toLowerCase()}
                ${assignment.itemCode.toLowerCase()}
                ${assignment.itemType.toLowerCase()}
                ${assignment.studentClass.toLowerCase()}
                ${assignment.status.toLowerCase()}
            `.replace(/\s+/g, ' ');
            
            const isVisible = searchTerm === '' || searchableText.includes(searchTerm);
            assignment.row.style.display = isVisible ? '' : 'none';
            
            if (isVisible) {
                visibleCount++;
            }
        });
        
        // Update count
        assignmentCount.textContent = `${visibleCount} Active`;
        
        // Show no results message
        if (visibleCount === 0 && searchTerm !== '') {
            showNoResultsMessage(searchTerm);
        }
    }
    
    // Show no results message
    function showNoResultsMessage(searchTerm) {
        const noResultsRow = document.createElement('tr');
        noResultsRow.innerHTML = `
            <td colspan="6" class="text-center py-4">
                <div class="empty-state">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5>No Matching Assignments</h5>
                    <p class="text-muted">No assignments found for "<strong>${searchTerm}</strong>"</p>
                    <button class="btn btn-outline-primary mt-2" onclick="document.getElementById('tableSearchInput').value=''; filterTable('');">
                        <i class="fas fa-times me-2"></i>Clear Search
                    </button>
                </div>
            </td>
        `;
        
        // Remove any existing no results message
        const existingNoResults = assignmentsTableBody.querySelector('.no-results-message');
        if (existingNoResults) {
            existingNoResults.remove();
        }
        
        noResultsRow.classList.add('no-results-message');
        assignmentsTableBody.appendChild(noResultsRow);
    }
    
    // Sort table
    function sortTable() {
        if (allAssignments.length === 0) return;
        
        allAssignments.sort((a, b) => {
            let aValue, bValue;
            
            switch (currentSortColumn) {
                case 'index_number':
                    aValue = a.indexNumber;
                    bValue = b.indexNumber;
                    // Handle alphanumeric sorting
                    return compareAlphanumeric(aValue, bValue);
                    
                case 'student_name':
                    aValue = a.studentName;
                    bValue = b.studentName;
                    break;
                    
                case 'item_code':
                    aValue = a.itemCode;
                    bValue = b.itemCode;
                    break;
                    
                case 'assigned_date':
                    aValue = new Date(a.assignedDate);
                    bValue = new Date(b.assignedDate);
                    break;
                    
                case 'due_date':
                    aValue = a.dueDate ? new Date(a.dueDate) : new Date(0);
                    bValue = b.dueDate ? new Date(b.dueDate) : new Date(0);
                    break;
                    
                case 'is_leaver':
                    aValue = a.status === 'Left' ? 1 : 0;
                    bValue = b.status === 'Left' ? 1 : 0;
                    break;
                    
                default:
                    aValue = a.indexNumber;
                    bValue = b.indexNumber;
            }
            
            if (aValue < bValue) return currentSortDirection === 'asc' ? -1 : 1;
            if (aValue > bValue) return currentSortDirection === 'asc' ? 1 : -1;
            return 0;
        });
        
        // Reorder rows in DOM
        const fragment = document.createDocumentFragment();
        allAssignments.forEach(assignment => {
            if (assignment.row.style.display !== 'none') {
                fragment.appendChild(assignment.row);
            }
        });
        
        assignmentsTableBody.innerHTML = '';
        assignmentsTableBody.appendChild(fragment);
        
        // Append any no results message that might exist
        const noResultsMessage = document.querySelector('.no-results-message');
        if (noResultsMessage) {
            assignmentsTableBody.appendChild(noResultsMessage);
        }
    }
    
    // Compare alphanumeric strings (A-Z, 0-9)
    function compareAlphanumeric(a, b) {
        // Extract numeric and non-numeric parts
        const regex = /(\d+)|(\D+)/g;
        const aParts = a.match(regex) || [];
        const bParts = b.match(regex) || [];
        
        for (let i = 0; i < Math.min(aParts.length, bParts.length); i++) {
            const aPart = aParts[i];
            const bPart = bParts[i];
            
            // Check if both parts are numbers
            const aNum = parseInt(aPart, 10);
            const bNum = parseInt(bPart, 10);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                // Both are numbers
                if (aNum !== bNum) {
                    return currentSortDirection === 'asc' ? aNum - bNum : bNum - aNum;
                }
            } else {
                // At least one is not a number, compare as strings
                const comparison = aPart.localeCompare(bPart);
                if (comparison !== 0) {
                    return currentSortDirection === 'asc' ? comparison : -comparison;
                }
            }
        }
        
        // If we get here, the common parts are equal
        return currentSortDirection === 'asc' ? aParts.length - bParts.length : bParts.length - aParts.length;
    }
    
    // ========== ASSIGN MODAL FUNCTIONALITY ==========
    // Elements for assign modal
    const studentSearch = document.getElementById('studentSearch');
    const studentResults = document.getElementById('studentSearchResults');
    const studentIdInput = document.getElementById('student_id');
    const selectedStudentDiv = document.getElementById('selectedStudent');
    const selectedStudentName = document.getElementById('selectedStudentName');
    const selectedStudentIndex = document.getElementById('selectedStudentIndex');
    const selectedStudentClass = document.getElementById('selectedStudentClass');
    
    const tableSearch = document.getElementById('tableSearch');
    const tableResults = document.getElementById('tableSearchResults');
    const selectedTableDiv = document.getElementById('selectedTable');
    const selectedTableCode = document.getElementById('selectedTableCode');
    const selectedTableDescription = document.getElementById('selectedTableDescription');
    
    const chairSearch = document.getElementById('chairSearch');
    const chairResults = document.getElementById('chairSearchResults');
    const selectedChairDiv = document.getElementById('selectedChair');
    const selectedChairCode = document.getElementById('selectedChairCode');
    const selectedChairDescription = document.getElementById('selectedChairDescription');
    
    const selectedItemsSummary = document.getElementById('selectedItemsSummary');
    const selectedItemsList = document.getElementById('selectedItemsList');
    
    const currentAssignmentsDiv = document.getElementById('currentAssignments');
    const currentStudentName = document.getElementById('currentStudentName');
    const currentAssignmentsTable = document.querySelector('#currentAssignmentsTable tbody');
    
    const assignButton = document.getElementById('assignButton');
    
    // Variables for selected items
    let searchTimeout;
    let currentStudentId = null;
    let selectedTable = null; // {id: '', code: '', description: ''}
    let selectedChair = null; // {id: '', code: '', description: ''}
    
    // Selected items array
    let selectedItems = [];
    
    // Get the correct base path for API calls
    function getBasePath() {
        const currentPath = window.location.pathname;
        const pathParts = currentPath.split('/');
        pathParts.pop(); // Remove current filename
        return pathParts.join('/') + '/';
    }

    // Student search functionality
    studentSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            studentResults.style.display = 'none';
            studentResults.innerHTML = '<div class="p-3 text-muted">Type at least 2 characters...</div>';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            // Show loading indicator
            studentResults.innerHTML = '<div class="p-3 text-center"><i class="fas fa-spinner fa-spin me-2"></i>Searching students...</div>';
            studentResults.style.display = 'block';
            
            const basePath = getBasePath();
            fetch(`${basePath}search_students.php?q=${encodeURIComponent(query)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Search results:', data);
                    
                    if (data.error) {
                        studentResults.innerHTML = `<div class="p-3 text-danger">Error: ${data.error}</div>`;
                        return;
                    }
                    
                    if (!Array.isArray(data)) {
                        studentResults.innerHTML = '<div class="p-3 text-danger">Invalid response format from server</div>';
                        return;
                    }
                    
                    if (data.length === 0) {
                        studentResults.innerHTML = '<div class="p-3 text-muted">No students found matching your search.</div>';
                        return;
                    }
                    
                    // Filter to show only active students (status = 1 or no status field)
                    const activeStudents = data.filter(student => {
                        return student.status === undefined || student.status == 1;
                    });
                    
                    if (activeStudents.length === 0) {
                        let message = 'No active students found. ';
                        if (data.length > 0) {
                            message += 'Students may be marked as inactive.';
                        }
                        studentResults.innerHTML = `<div class="p-3 text-warning">${message}</div>`;
                        return;
                    }
                    
                    displayStudentResults(activeStudents);
                })
                .catch(error => {
                    console.error('Error searching students:', error);
                    studentResults.innerHTML = `
                        <div class="p-3 text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error searching students. Please check console for details.
                        </div>
                    `;
                });
        }, 300);
    });
    
    // Display student search results
    function displayStudentResults(students) {
        let html = '<div class="list-group list-group-flush">';
        
        // Sort by index number ascending by default
        students.sort((a, b) => {
            if (a.index_number && b.index_number) {
                return a.index_number.localeCompare(b.index_number);
            }
            return 0;
        });
        
        students.forEach(student => {
            const fullName = student.full_name || `${student.first_name || ''} ${student.second_name || ''} ${student.last_name || ''}`.trim().replace(/\s+/g, ' ');
            const isLeaver = student.is_leaver == 1;
            const indexNumber = student.index_number || 'No index';
            const studentClass = student.class || 'Not specified';
            const combination = student.combination || 'Not specified';
            const sex = student.sex || 'Unknown';
            
            html += `
            <a href="#" class="list-group-item list-group-item-action student-option ${isLeaver ? 'list-group-item-warning' : ''}" 
               data-id="${student.id}" 
               data-name="${fullName}"
               data-index="${indexNumber}"
               data-class="${studentClass}"
               data-combination="${combination}"
               data-is-leaver="${isLeaver}">
                <div class="d-flex w-100 justify-content-between">
                    <strong>${fullName}</strong>
                    <div>
                        ${isLeaver ? '<span class="badge bg-warning text-dark me-2">Left</span>' : ''}
                        <small class="text-muted">${indexNumber}</small>
                    </div>
                </div>
                <div class="small text-muted">
                    ${studentClass} • ${combination} • ${sex}
                </div>
                ${isLeaver ? '<div class="small text-warning mt-1"><i class="fas fa-exclamation-triangle me-1"></i>This student has left/graduated</div>' : ''}
            </a>
            `;
        });
        html += '</div>';
        
        studentResults.innerHTML = html;
        studentResults.style.display = 'block';
        
        // Add click event listeners to student options
        document.querySelectorAll('.student-option').forEach(option => {
            option.addEventListener('click', function(e) {
                e.preventDefault();
                const isLeaver = this.getAttribute('data-is-leaver') === 'true';
                
                if (isLeaver) {
                    if (!confirm('This student has left/graduated. Do you still want to assign items to them?')) {
                        return;
                    }
                }
                
                selectStudent(
                    this.getAttribute('data-id'),
                    this.getAttribute('data-name'),
                    this.getAttribute('data-index'),
                    this.getAttribute('data-class'),
                    this.getAttribute('data-combination'),
                    isLeaver
                );
            });
        });
    }
    
    // Function to select a student
    function selectStudent(id, name, index, studentClass, combination, isLeaver = false) {
        console.log('Selecting student:', {id, name, index, studentClass, combination, isLeaver});
        
        currentStudentId = id;
        studentIdInput.value = id;
        selectedStudentName.textContent = name;
        selectedStudentIndex.textContent = `Index: ${index}`;
        
        if (isLeaver) {
            selectedStudentClass.innerHTML = `${studentClass} (${combination}) <span class="badge bg-warning ms-2">Left/Graduated</span>`;
        } else {
            selectedStudentClass.textContent = `${studentClass} (${combination})`;
        }
        
        selectedStudentDiv.classList.remove('d-none');
        studentSearch.value = name;
        studentResults.style.display = 'none';
        
        // Enable item searches
        tableSearch.disabled = false;
        chairSearch.disabled = false;
        
        // Load current assignments for this student
        loadCurrentAssignments(id, name);
        
        // Update assign button state
        updateAssignButtonState();
        
        // Auto-focus on table search for better UX
        setTimeout(() => {
            tableSearch.focus();
        }, 100);
    }
    
    // Table search functionality
    tableSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 1) {
            tableResults.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            // Show loading
            tableResults.innerHTML = '<div class="p-3 text-center"><i class="fas fa-spinner fa-spin me-2"></i>Searching tables...</div>';
            tableResults.style.display = 'block';
            
            const basePath = getBasePath();
            fetch(`${basePath}search_items.php?q=${encodeURIComponent(query)}&type=table`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Table results:', data);
                    
                    if (data.length === 0) {
                        tableResults.innerHTML = '<div class="p-3 text-muted">No tables found</div>';
                        tableResults.style.display = 'block';
                        return;
                    }
                    
                    let html = '<div class="list-group list-group-flush">';
                    data.forEach(item => {
                        html += `
                        <a href="#" class="list-group-item list-group-item-action table-option" 
                           data-id="${item.id}" 
                           data-code="${item.item_code}"
                           data-description="${item.description || 'No description'}">
                            <div class="d-flex w-100 justify-content-between">
                                <strong>${item.item_code}</strong>
                                <span class="badge bg-primary">Table</span>
                            </div>
                            <div class="small text-muted">
                                ${item.description || 'No description'}
                            </div>
                        </a>
                        `;
                    });
                    html += '</div>';
                    
                    tableResults.innerHTML = html;
                    tableResults.style.display = 'block';
                    
                    // Add click event listeners to table options
                    document.querySelectorAll('.table-option').forEach(option => {
                        option.addEventListener('click', function(e) {
                            e.preventDefault();
                            selectTable(
                                this.getAttribute('data-id'),
                                this.getAttribute('data-code'),
                                this.getAttribute('data-description')
                            );
                        });
                    });
                })
                .catch(error => {
                    console.error('Error searching tables:', error);
                    tableResults.innerHTML = '<div class="p-3 text-danger">Error searching tables</div>';
                    tableResults.style.display = 'block';
                });
        }, 300);
    });
    
    // Function to select a table
    function selectTable(id, code, description) {
        console.log('Selecting table:', {id, code, description});
        
        selectedTable = {
            id: id,
            code: code,
            description: description || 'No description',
            type: 'table'
        };
        
        tableSearch.value = code;
        selectedTableCode.textContent = code;
        selectedTableDescription.textContent = description || 'No description';
        
        selectedTableDiv.classList.remove('d-none');
        tableResults.style.display = 'none';
        
        // Add to selected items if not already there
        addSelectedItem(selectedTable);
        
        updateAssignButtonState();
        updateSelectedItemsSummary();
    }
    
    // Function to clear table selection
    window.clearTableSelection = function() {
        selectedTable = null;
        tableSearch.value = '';
        selectedTableDiv.classList.add('d-none');
        
        // Remove from selected items
        removeSelectedItem('table');
        
        updateAssignButtonState();
        updateSelectedItemsSummary();
        tableSearch.focus();
    }
    
    // Chair search functionality
    chairSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 1) {
            chairResults.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            // Show loading
            chairResults.innerHTML = '<div class="p-3 text-center"><i class="fas fa-spinner fa-spin me-2"></i>Searching chairs...</div>';
            chairResults.style.display = 'block';
            
            const basePath = getBasePath();
            fetch(`${basePath}search_items.php?q=${encodeURIComponent(query)}&type=chair`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Chair results:', data);
                    
                    if (data.length === 0) {
                        chairResults.innerHTML = '<div class="p-3 text-muted">No chairs found</div>';
                        chairResults.style.display = 'block';
                        return;
                    }
                    
                    let html = '<div class="list-group list-group-flush">';
                    data.forEach(item => {
                        html += `
                        <a href="#" class="list-group-item list-group-item-action chair-option" 
                           data-id="${item.id}" 
                           data-code="${item.item_code}"
                           data-description="${item.description || 'No description'}">
                            <div class="d-flex w-100 justify-content-between">
                                <strong>${item.item_code}</strong>
                                <span class="badge bg-info">Chair</span>
                            </div>
                            <div class="small text-muted">
                                ${item.description || 'No description'}
                            </div>
                        </a>
                        `;
                    });
                    html += '</div>';
                    
                    chairResults.innerHTML = html;
                    chairResults.style.display = 'block';
                    
                    // Add click event listeners to chair options
                    document.querySelectorAll('.chair-option').forEach(option => {
                        option.addEventListener('click', function(e) {
                            e.preventDefault();
                            selectChair(
                                this.getAttribute('data-id'),
                                this.getAttribute('data-code'),
                                this.getAttribute('data-description')
                            );
                        });
                    });
                })
                .catch(error => {
                    console.error('Error searching chairs:', error);
                    chairResults.innerHTML = '<div class="p-3 text-danger">Error searching chairs</div>';
                    chairResults.style.display = 'block';
                });
        }, 300);
    });
    
    // Function to select a chair
    function selectChair(id, code, description) {
        console.log('Selecting chair:', {id, code, description});
        
        selectedChair = {
            id: id,
            code: code,
            description: description || 'No description',
            type: 'chair'
        };
        
        chairSearch.value = code;
        selectedChairCode.textContent = code;
        selectedChairDescription.textContent = description || 'No description';
        
        selectedChairDiv.classList.remove('d-none');
        chairResults.style.display = 'none';
        
        // Add to selected items if not already there
        addSelectedItem(selectedChair);
        
        updateAssignButtonState();
        updateSelectedItemsSummary();
    }
    
    // Function to clear chair selection
    window.clearChairSelection = function() {
        selectedChair = null;
        chairSearch.value = '';
        selectedChairDiv.classList.add('d-none');
        
        // Remove from selected items
        removeSelectedItem('chair');
        
        updateAssignButtonState();
        updateSelectedItemsSummary();
        chairSearch.focus();
    }
    
    // Add selected item to array
    function addSelectedItem(item) {
        // Check if item of same type already exists
        const existingIndex = selectedItems.findIndex(i => i.type === item.type);
        
        if (existingIndex !== -1) {
            // Replace existing item of same type
            selectedItems[existingIndex] = item;
        } else {
            // Add new item
            selectedItems.push(item);
        }
    }
    
    // Remove selected item by type
    function removeSelectedItem(type) {
        selectedItems = selectedItems.filter(item => item.type !== type);
    }
    
    // Update selected items summary
    function updateSelectedItemsSummary() {
        selectedItemsList.innerHTML = '';
        
        if (selectedItems.length === 0) {
            selectedItemsSummary.classList.add('d-none');
            return;
        }
        
        selectedItemsSummary.classList.remove('d-none');
        
        selectedItems.forEach((item, index) => {
            const badgeClass = item.type === 'table' ? 'bg-primary' : 'bg-info';
            const iconClass = item.type === 'table' ? 'fa-table' : 'fa-chair';
            
            const col = document.createElement('div');
            col.className = 'col-md-6 mb-2';
            col.innerHTML = `
                <div class="card">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge ${badgeClass} me-2"><i class="fas ${iconClass}"></i></span>
                                <strong>${item.code}</strong>
                                <div class="small text-muted">${item.description}</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clear${item.type.charAt(0).toUpperCase() + item.type.slice(1)}Selection()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <input type="hidden" name="${item.type}_id" value="${item.id}">
                    </div>
                </div>
            `;
            selectedItemsList.appendChild(col);
        });
        
        // Update button text
        if (selectedItems.length > 0) {
            assignButton.innerHTML = `<i class="fas fa-save me-2"></i>Assign ${selectedItems.length} Item${selectedItems.length > 1 ? 's' : ''}`;
        }
    }
    
    // Update assign button state
    function updateAssignButtonState() {
        const hasStudent = currentStudentId !== null;
        const hasItems = selectedItems.length > 0;
        
        assignButton.disabled = !(hasStudent && hasItems);
        
        if (hasStudent && hasItems) {
            assignButton.innerHTML = `<i class="fas fa-save me-2"></i>Assign ${selectedItems.length} Item${selectedItems.length > 1 ? 's' : ''}`;
        } else {
            assignButton.innerHTML = `<i class="fas fa-save me-2"></i>Assign Items`;
        }
    }
    
    // Load current assignments for selected student
    function loadCurrentAssignments(studentId, studentName) {
        console.log('Loading assignments for student:', studentId);
        
        const basePath = getBasePath();
        fetch(`${basePath}get_current_assignments.php?student_id=${studentId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Current assignments:', data);
                
                if (data.length > 0) {
                    currentStudentName.textContent = studentName;
                    currentAssignmentsTable.innerHTML = '';
                    
                    data.forEach(assignment => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${assignment.item_code || 'Unknown'}</td>
                            <td><span class="badge ${assignment.assignment_type === 'table' ? 'bg-primary' : 'bg-info'}">${assignment.assignment_type || 'Unknown'}</span></td>
                            <td>${assignment.assigned_date || 'Unknown'}</td>
                            <td><span class="badge bg-success">Active</span></td>
                        `;
                        currentAssignmentsTable.appendChild(row);
                    });
                    
                    currentAssignmentsDiv.classList.remove('d-none');
                    
                    // Disable search for already assigned item types
                    data.forEach(assignment => {
                        if (assignment.assignment_type === 'table') {
                            if (selectedTable) {
                                clearTableSelection();
                            }
                            tableSearch.value = `${assignment.item_code || 'Item'} (Already assigned)`;
                            tableSearch.disabled = true;
                            document.querySelector('.card-header.bg-primary').innerHTML += ' <span class="badge bg-warning">Already assigned</span>';
                        } else if (assignment.assignment_type === 'chair') {
                            if (selectedChair) {
                                clearChairSelection();
                            }
                            chairSearch.value = `${assignment.item_code || 'Item'} (Already assigned)`;
                            chairSearch.disabled = true;
                            document.querySelector('.card-header.bg-info').innerHTML += ' <span class="badge bg-warning">Already assigned</span>';
                        }
                    });
                    
                    updateAssignButtonState();
                } else {
                    currentAssignmentsDiv.classList.add('d-none');
                }
            })
            .catch(error => {
                console.error('Error loading assignments:', error);
                currentAssignmentsDiv.classList.add('d-none');
            });
    }
    
    // Hide dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!studentSearch.contains(e.target) && !studentResults.contains(e.target)) {
            studentResults.style.display = 'none';
        }
        if (!tableSearch.contains(e.target) && !tableResults.contains(e.target)) {
            tableResults.style.display = 'none';
        }
        if (!chairSearch.contains(e.target) && !chairResults.contains(e.target)) {
            chairResults.style.display = 'none';
        }
    });
    
    // Clear form when assign modal is closed
    document.getElementById('assignModal').addEventListener('hidden.bs.modal', function() {
        resetAssignForm();
    });
    
    // Reset assign form
    function resetAssignForm() {
        console.log('Resetting assign form');
        
        studentSearch.value = '';
        studentIdInput.value = '';
        selectedStudentDiv.classList.add('d-none');
        studentResults.style.display = 'none';
        studentResults.innerHTML = '';
        
        tableSearch.value = '';
        tableSearch.disabled = true;
        tableResults.style.display = 'none';
        tableResults.innerHTML = '';
        selectedTableDiv.classList.add('d-none');
        
        chairSearch.value = '';
        chairSearch.disabled = true;
        chairResults.style.display = 'none';
        chairResults.innerHTML = '';
        selectedChairDiv.classList.add('d-none');
        
        selectedItemsSummary.classList.add('d-none');
        selectedItemsList.innerHTML = '';
        
        currentAssignmentsDiv.classList.add('d-none');
        currentAssignmentsTable.innerHTML = '';
        
        document.getElementById('due_date').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('notes').value = '';
        
        assignButton.disabled = true;
        assignButton.innerHTML = `<i class="fas fa-save me-2"></i>Assign Items`;
        
        currentStudentId = null;
        selectedTable = null;
        selectedChair = null;
        selectedItems = [];
        
        // Reset card headers
        const tableCardHeader = document.querySelector('.card-header.bg-primary');
        const chairCardHeader = document.querySelector('.card-header.bg-info');
        
        if (tableCardHeader) {
            tableCardHeader.innerHTML = tableCardHeader.innerHTML.replace(' <span class="badge bg-warning">Already assigned</span>', '');
        }
        if (chairCardHeader) {
            chairCardHeader.innerHTML = chairCardHeader.innerHTML.replace(' <span class="badge bg-warning">Already assigned</span>', '');
        }
    }
    
    // Return item modal
    document.querySelectorAll('.return-item').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const itemCode = this.getAttribute('data-item-code');
            const studentName = this.getAttribute('data-student-name');
            
            document.getElementById('return_assignment_id').value = id;
            document.getElementById('returnItemCode').textContent = itemCode;
            document.getElementById('returnStudentName').textContent = studentName;
        });
    });
    
    // View assignment details
    document.querySelectorAll('.view-assignment').forEach(button => {
        button.addEventListener('click', function() {
            const assignmentId = this.getAttribute('data-id');
            const basePath = getBasePath();
            
            // Show loading
            document.getElementById('assignmentDetails').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading assignment details...</p>
                </div>
            `;
            
            // Fetch assignment details via AJAX
            fetch(`${basePath}get_assignment.php?id=${assignmentId}&type=student`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('assignmentDetails').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('assignmentDetails').innerHTML = 
                        '<div class="alert alert-danger">Error loading assignment details.</div>';
                });
        });
    });
    
    // Force return for leaver
    window.forceReturnForLeaver = function(assignmentId) {
        if (confirm('This student has left/graduated. Do you want to force return this item?')) {
            const basePath = getBasePath();
            fetch(`${basePath}force_return_item.php?assignment_id=${assignmentId}&admin_id=<?php echo $admin_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error returning item: ' + error);
                });
        }
    };
    
    // Set today's date as default for due date
    const today = new Date();
    const dueDateInput = document.getElementById('due_date');
    if (dueDateInput) {
        // Set due date to 30 days from today
        const dueDate = new Date();
        dueDate.setDate(today.getDate() + 30);
        dueDateInput.min = today.toISOString().split('T')[0];
        dueDateInput.value = dueDate.toISOString().split('T')[0];
    }
    
    // Open table list modal
    window.openTableListModal = function() {
        console.log('Opening table list modal');
        
        const basePath = getBasePath();
        fetch(`${basePath}search_items.php?q=&type=table`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('All tables:', data);
                
                const tableListBody = document.getElementById('tableListBody');
                tableListBody.innerHTML = '';
                
                if (data.length === 0) {
                    tableListBody.innerHTML = '<tr><td colspan="4" class="text-center py-3">No tables available</td></tr>';
                } else {
                    // Sort by item code
                    data.sort((a, b) => {
                        if (a.item_code && b.item_code) {
                            return a.item_code.localeCompare(b.item_code);
                        }
                        return 0;
                    });
                    
                    data.forEach(item => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td><strong>${item.item_code || 'Unknown'}</strong></td>
                            <td>${item.description || 'No description'}</td>
                            <td>${item.location || 'Not specified'}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" onclick="selectTableFromList('${item.id}', '${item.item_code}', '${item.description || 'No description'}')">
                                    Select
                                </button>
                            </td>
                        `;
                        tableListBody.appendChild(row);
                    });
                }
                
                const tableListModal = new bootstrap.Modal(document.getElementById('tableListModal'));
                tableListModal.show();
                
                // Auto-focus on search in modal
                setTimeout(() => {
                    const searchInput = document.getElementById('tableListSearch');
                    if (searchInput) searchInput.focus();
                }, 500);
                
                // Add search functionality
                const tableListSearch = document.getElementById('tableListSearch');
                if (tableListSearch) {
                    tableListSearch.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase();
                        const rows = tableListBody.querySelectorAll('tr');
                        
                        rows.forEach(row => {
                            const text = row.textContent.toLowerCase();
                            if (text.includes(searchTerm)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    });
                }
            })
            .catch(error => {
                console.error('Error loading tables:', error);
                const tableListBody = document.getElementById('tableListBody');
                tableListBody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger">Error loading tables</td></tr>';
            });
    };
    
    // Select table from list
    window.selectTableFromList = function(id, code, description) {
        console.log('Selecting table from list:', {id, code, description});
        selectTable(id, code, description);
        const tableListModal = bootstrap.Modal.getInstance(document.getElementById('tableListModal'));
        if (tableListModal) tableListModal.hide();
    };
    
    // Open chair list modal
    window.openChairListModal = function() {
        console.log('Opening chair list modal');
        
        const basePath = getBasePath();
        fetch(`${basePath}search_items.php?q=&type=chair`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('All chairs:', data);
                
                const chairListBody = document.getElementById('chairListBody');
                chairListBody.innerHTML = '';
                
                if (data.length === 0) {
                    chairListBody.innerHTML = '<tr><td colspan="4" class="text-center py-3">No chairs available</td></tr>';
                } else {
                    // Sort by item code
                    data.sort((a, b) => {
                        if (a.item_code && b.item_code) {
                            return a.item_code.localeCompare(b.item_code);
                        }
                        return 0;
                    });
                    
                    data.forEach(item => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td><strong>${item.item_code || 'Unknown'}</strong></td>
                            <td>${item.description || 'No description'}</td>
                            <td>${item.location || 'Not specified'}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" onclick="selectChairFromList('${item.id}', '${item.item_code}', '${item.description || 'No description'}')">
                                    Select
                                </button>
                            </td>
                        `;
                        chairListBody.appendChild(row);
                    });
                }
                
                const chairListModal = new bootstrap.Modal(document.getElementById('chairListModal'));
                chairListModal.show();
                
                // Auto-focus on search in modal
                setTimeout(() => {
                    const searchInput = document.getElementById('chairListSearch');
                    if (searchInput) searchInput.focus();
                }, 500);
                
                // Add search functionality
                const chairListSearch = document.getElementById('chairListSearch');
                if (chairListSearch) {
                    chairListSearch.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase();
                        const rows = chairListBody.querySelectorAll('tr');
                        
                        rows.forEach(row => {
                            const text = row.textContent.toLowerCase();
                            if (text.includes(searchTerm)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    });
                }
            })
            .catch(error => {
                console.error('Error loading chairs:', error);
                const chairListBody = document.getElementById('chairListBody');
                chairListBody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger">Error loading chairs</td></tr>';
            });
    };
    
    // Select chair from list
    window.selectChairFromList = function(id, code, description) {
        console.log('Selecting chair from list:', {id, code, description});
        selectChair(id, code, description);
        const chairListModal = bootstrap.Modal.getInstance(document.getElementById('chairListModal'));
        if (chairListModal) chairListModal.hide();
    };
    
    // Focus on search inputs when clicked
    tableSearch.addEventListener('focus', function() {
        if (!selectedTable && this.disabled === false) {
            this.value = '';
            this.dispatchEvent(new Event('input'));
        }
    });
    
    chairSearch.addEventListener('focus', function() {
        if (!selectedChair && this.disabled === false) {
            this.value = '';
            this.dispatchEvent(new Event('input'));
        }
    });
    
    // Log the current base path for debugging
    console.log('Base path:', getBasePath());
    
    // Initialize sort icons
    updateSortIcons();
});
</script>

<style>
    .card-header{
         background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
    }
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: rgba(59, 157, 179, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state i {
    opacity: 0.5;
}

.empty-state h5 {
    color: #666;
    margin: 15px 0 10px 0;
}

.empty-state p {
    color: #999;
    max-width: 400px;
    margin: 0 auto;
}

.table th {
    font-weight: 600;
    color: #333;
    background-color: rgba(59, 157, 179, 0.05);
    border-bottom: 2px solid rgba(59, 157, 179, 0.2);
    padding: 12px 8px;
    cursor: pointer;
    position: relative;
}

.table th:hover {
    background-color: rgba(59, 157, 179, 0.1);
}

.table th i {
    opacity: 0.5;
    transition: opacity 0.2s;
}

.table th:hover i {
    opacity: 1;
}

.btn-group .btn {
    border-radius: 6px !important;
    margin: 0 2px;
}

/* Enhanced search results styling */
#studentSearchResults {
    background-color: white;
    z-index: 9999;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    border: 2px solid #3B9DB3;
    margin-top: 5px;
}

#tableSearchResults, 
#chairSearchResults {
    background-color: white;
    z-index: 9998;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border: 1px solid #dee2e6;
}

#studentSearchResults .student-option:hover,
#tableSearchResults .table-option:hover,
#chairSearchResults .chair-option:hover {
    background-color: #f0f7ff;
    cursor: pointer;
    border-left: 4px solid #3B9DB3;
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Search bar styling */
#tableSearchInput {
    transition: all 0.3s ease;
}

#tableSearchInput:focus {
    border-color: #3B9DB3;
    box-shadow: 0 0 0 0.25rem rgba(59, 157, 179, 0.25);
}

#clearSearch:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

#toggleSort {
    transition: all 0.3s ease;
}

#toggleSort:hover {
    background-color: rgba(255, 255, 255, 0.2);
    transform: scale(1.05);
}

/* Selected items styling */
#selectedItemsSummary .card {
    border: 2px solid #28a745;
}

#selectedItemsSummary .card-header {
    background-color: #28a745 !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
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
    
    .avatar-circle {
        width: 35px;
        height: 35px;
        margin-right: 10px;
    }
    
    /* Adjust search results for mobile */
    #studentSearchResults {
        width: 100% !important;
        position: relative !important;
    }
    
    /* Adjust header for mobile */
    .card-header .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .card-header .input-group {
        margin-top: 10px;
        width: 100% !important;
    }
    
    #toggleSort {
        margin-left: 0 !important;
        margin-top: 10px;
    }
    
    /* Adjust selected items for mobile */
    #selectedItemsList .col-md-6 {
        width: 100%;
    }
}

/* Table sorting indicators */
.fa-sort-up, .fa-sort-down {
    margin-left: 5px;
}

/* Loading animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

#studentSearchResults,
#tableSearchResults,
#chairSearchResults {
    animation: fadeIn 0.2s ease-out;
}

/* Focus styles for accessibility */
#studentSearch:focus,
#tableSearch:focus,
#chairSearch:focus {
    border-color: #3B9DB3;
    box-shadow: 0 0 0 0.25rem rgba(59, 157, 179, 0.25);
}

/* Highlight search matches */
.highlight {
    background-color: #fff3cd;
    border-radius: 3px;
    padding: 1px 3px;
}

/* Badge adjustments */
.badge {
    font-weight: 500;
    letter-spacing: 0.3px;
}

/* Card headers for assign modal */
.card-header.bg-primary, .card-header.bg-info {
    position: relative;
}
</style>

<?php include '../controller/footer.php'; ?>
