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
// AJAX handler for staff search
if (isset($_GET['ajax_staff_search']) && isset($_GET['q'])) {
    $search = mysqli_real_escape_string($conn, $_GET['q']);
    
    $sql = "SELECT a.id, a.first_name, a.middle_name, a.last_name, a.email, a.sex,
                   CONCAT(a.first_name, ' ', COALESCE(a.middle_name, ''), ' ', a.last_name) as full_name,
                   ar.role_name
            FROM admins a
            LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
            LEFT JOIN admin_roles ar ON ara.role_id = ar.id
            WHERE a.status = 1
            AND (a.first_name LIKE '%$search%' 
                 OR a.last_name LIKE '%$search%' 
                 OR a.email LIKE '%$search%'
                 OR CONCAT(a.first_name, ' ', a.last_name) LIKE '%$search%')
            GROUP BY a.id
            ORDER BY a.first_name
            LIMIT 10";
    
    $result = mysqli_query($conn, $sql);
    $staff = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $staff[] = $row;
    }
    
    echo json_encode($staff);
    exit();
}

// AJAX handler for item search
if (isset($_GET['ajax_item_search']) && isset($_GET['q']) && isset($_GET['type'])) {
    $search = mysqli_real_escape_string($conn, $_GET['q']);
    $type = mysqli_real_escape_string($conn, $_GET['type']);
    
    $sql = "SELECT id, item_code, item_type, description, status
            FROM maintenance_items 
            WHERE item_type = '$type'
            AND status = 'available'
            AND (item_code LIKE '%$search%' 
                 OR description LIKE '%$search%')
            ORDER BY item_code
            LIMIT 10";
    
    $result = mysqli_query($conn, $sql);
    $items = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    
    echo json_encode($items);
    exit();
}

// AJAX handler for current assignments
if (isset($_GET['ajax_current_assignments']) && isset($_GET['staff_id'])) {
    $staff_id = mysqli_real_escape_string($conn, $_GET['staff_id']);
    
    $sql = "SELECT msa.*, mi.item_code, mi.item_type
            FROM maintenance_staff_assignments msa
            JOIN maintenance_items mi ON msa.item_id = mi.id
            WHERE msa.staff_id = $staff_id
            AND msa.status = 'active'
            ORDER BY msa.assigned_date DESC";
    
    $result = mysqli_query($conn, $sql);
    $assignments = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $row['assigned_date'] = date('M d, Y', strtotime($row['assigned_date']));
        $assignments[] = $row;
    }
    
    echo json_encode($assignments);
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['assign_items'])) {
        $staff_id = mysqli_real_escape_string($conn, $_POST['staff_id']);
        $table_id = isset($_POST['table_id']) ? mysqli_real_escape_string($conn, $_POST['table_id']) : null;
        $chair_id = isset($_POST['chair_id']) ? mysqli_real_escape_string($conn, $_POST['chair_id']) : null;
        $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
        
        // Validate at least one item is selected
        if (!$table_id && !$chair_id) {
            $_SESSION['error'] = "Please select at least one item (table or chair) to assign.";
        } else {
            // Start transaction
            mysqli_begin_transaction($conn);
            
            $assignments_made = 0;
            $errors = [];
            
            try {
                // Get staff info for logging
                $staff_sql = "SELECT first_name, last_name FROM admins WHERE id = $staff_id";
                $staff_result = mysqli_query($conn, $staff_sql);
                $staff = mysqli_fetch_assoc($staff_result);
                $staff_name = $staff['first_name'] . ' ' . $staff['last_name'];
                
                // Assign table if selected
                if ($table_id) {
                    // Check if staff already has an active table assignment
                    $check_table_sql = "SELECT id FROM maintenance_staff_assignments 
                                       WHERE staff_id = $staff_id 
                                       AND assignment_type = 'table'
                                       AND status = 'active'";
                    $check_table_result = mysqli_query($conn, $check_table_sql);
                    
                    if (mysqli_num_rows($check_table_result) > 0) {
                        $errors[] = "This staff already has an active table assignment.";
                    } else {
                        // Check if table is available
                        $table_sql = "SELECT status, item_code FROM maintenance_items WHERE id = $table_id";
                        $table_result = mysqli_query($conn, $table_sql);
                        $table = mysqli_fetch_assoc($table_result);
                        
                        if ($table['status'] != 'available') {
                            $errors[] = "Selected table is not available.";
                        } else {
                            // Insert table assignment
                            $insert_table_sql = "INSERT INTO maintenance_staff_assignments 
                                              (staff_id, item_id, assignment_type, assigned_by, assigned_date, due_date, notes) 
                                              VALUES ($staff_id, $table_id, 'table', $admin_id, CURDATE(), '$due_date', '$notes')";
                            
                            if (!mysqli_query($conn, $insert_table_sql)) {
                                throw new Exception("Error assigning table: " . mysqli_error($conn));
                            }
                            
                            // Update table status
                            $update_table_sql = "UPDATE maintenance_items SET status = 'assigned' WHERE id = $table_id";
                            if (!mysqli_query($conn, $update_table_sql)) {
                                throw new Exception("Error updating table status: " . mysqli_error($conn));
                            }
                            
                            // Log the action
                            $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                                       VALUES ($table_id, 'assignment', 'staff', $staff_id, $admin_id, 
                                       'Assigned {$table['item_code']} (table) to staff: $staff_name')";
                            if (!mysqli_query($conn, $log_sql)) {
                                throw new Exception("Error logging table assignment: " . mysqli_error($conn));
                            }
                            
                            $assignments_made++;
                        }
                    }
                }
                
                // Assign chair if selected
                if ($chair_id) {
                    // Check if staff already has an active chair assignment
                    $check_chair_sql = "SELECT id FROM maintenance_staff_assignments 
                                       WHERE staff_id = $staff_id 
                                       AND assignment_type = 'chair'
                                       AND status = 'active'";
                    $check_chair_result = mysqli_query($conn, $check_chair_sql);
                    
                    if (mysqli_num_rows($check_chair_result) > 0) {
                        $errors[] = "This staff already has an active chair assignment.";
                    } else {
                        // Check if chair is available
                        $chair_sql = "SELECT status, item_code FROM maintenance_items WHERE id = $chair_id";
                        $chair_result = mysqli_query($conn, $chair_sql);
                        $chair = mysqli_fetch_assoc($chair_result);
                        
                        if ($chair['status'] != 'available') {
                            $errors[] = "Selected chair is not available.";
                        } else {
                            // Insert chair assignment
                            $insert_chair_sql = "INSERT INTO maintenance_staff_assignments 
                                              (staff_id, item_id, assignment_type, assigned_by, assigned_date, due_date, notes) 
                                              VALUES ($staff_id, $chair_id, 'chair', $admin_id, CURDATE(), '$due_date', '$notes')";
                            
                            if (!mysqli_query($conn, $insert_chair_sql)) {
                                throw new Exception("Error assigning chair: " . mysqli_error($conn));
                            }
                            
                            // Update chair status
                            $update_chair_sql = "UPDATE maintenance_items SET status = 'assigned' WHERE id = $chair_id";
                            if (!mysqli_query($conn, $update_chair_sql)) {
                                throw new Exception("Error updating chair status: " . mysqli_error($conn));
                            }
                            
                            // Log the action
                            $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                                       VALUES ($chair_id, 'assignment', 'staff', $staff_id, $admin_id, 
                                       'Assigned {$chair['item_code']} (chair) to staff: $staff_name')";
                            if (!mysqli_query($conn, $log_sql)) {
                                throw new Exception("Error logging chair assignment: " . mysqli_error($conn));
                            }
                            
                            $assignments_made++;
                        }
                    }
                }
                
                if (!empty($errors)) {
                    $_SESSION['error'] = implode(" ", $errors);
                    mysqli_rollback($conn);
                } else {
                    // Commit transaction
                    mysqli_commit($conn);
                    if ($assignments_made > 0) {
                        $_SESSION['success'] = "$assignments_made item(s) assigned successfully to staff!";
                    }
                }
                
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                $_SESSION['error'] = $e->getMessage();
            }
        }
        
        header("Location: staff_main.php");
        exit();
        
    } elseif (isset($_POST['return_item'])) {
        $assignment_id = mysqli_real_escape_string($conn, $_POST['assignment_id']);
        $return_condition = mysqli_real_escape_string($conn, $_POST['return_condition']);
        $return_notes = mysqli_real_escape_string($conn, $_POST['return_notes']);
        
        // Get assignment details
        $assignment_sql = "SELECT msa.*, mi.item_code, a.first_name, a.last_name 
                          FROM maintenance_staff_assignments msa
                          JOIN maintenance_items mi ON msa.item_id = mi.id
                          JOIN admins a ON msa.staff_id = a.id
                          WHERE msa.id = $assignment_id";
        $assignment_result = mysqli_query($conn, $assignment_sql);
        $assignment = mysqli_fetch_assoc($assignment_result);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update assignment status
            $update_sql = "UPDATE maintenance_staff_assignments SET 
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
            $staff_name = $assignment['first_name'] . ' ' . $assignment['last_name'];
            $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                       VALUES ({$assignment['item_id']}, 'return', 'staff', {$assignment['staff_id']}, $admin_id, 
                       'Returned {$assignment['item_code']} from staff: $staff_name. Condition: $return_condition')";
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
        
        header("Location: staff_main.php");
        exit();
    }
}

// Get all available tables and chairs for the list modals
$available_tables_sql = "SELECT id, item_code, item_type, description, status 
                         FROM maintenance_items 
                         WHERE item_type = 'table' 
                         AND status = 'available' 
                         ORDER BY item_code ASC";
$available_tables_result = mysqli_query($conn, $available_tables_sql);

$available_chairs_sql = "SELECT id, item_code, item_type, description, status 
                         FROM maintenance_items 
                         WHERE item_type = 'chair' 
                         AND status = 'available' 
                         ORDER BY item_code ASC";
$available_chairs_result = mysqli_query($conn, $available_chairs_sql);

// Get all active staff assignments with search functionality
$search_query = "";
$order_by = "a.first_name ASC, a.last_name ASC"; // Default sort by staff name ascending

if (isset($_GET['search'])) {
    $search_term = mysqli_real_escape_string($conn, $_GET['search']);
    if (!empty($search_term)) {
        $search_query = "AND (a.first_name LIKE '%$search_term%' 
                            OR a.last_name LIKE '%$search_term%' 
                            OR a.email LIKE '%$search_term%'
                            OR mi.item_code LIKE '%$search_term%'
                            OR mi.description LIKE '%$search_term%')";
    }
}

// Get all active staff assignments with search and sort
$assignments_sql = "SELECT msa.*, 
                   mi.item_code, mi.item_type, mi.description as item_description,
                   a.first_name, a.last_name, a.sex,
                   CONCAT(a.first_name, ' ', a.last_name) as staff_name,
                   ab.first_name as assigned_by_fname, ab.last_name as assigned_by_lname
                   FROM maintenance_staff_assignments msa
                   JOIN maintenance_items mi ON msa.item_id = mi.id
                   JOIN admins a ON msa.staff_id = a.id
                   LEFT JOIN admins ab ON msa.assigned_by = ab.id
                   WHERE msa.status = 'active'
                   $search_query
                   ORDER BY $order_by";
$assignments_result = mysqli_query($conn, $assignments_sql);
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Assign Item to Staff</h2>
              <!-- Action Button with Dropdown -->
                <div class="dropdown">
                    
                    <button class="btn btn-primary dropdown-toggle" type="button" id="actionDropdown" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog me-2"></i>Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionDropdown">
                        <li><a class="dropdown-item" href="maintenance.php">
                            <i class="fas fa-tools me-2"></i>Dashboard
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#assignModal">
                                                <i class="fas fa-plus me-2"></i>Assign staff Item</a></li>
                         <li><hr class="dropdown-divider"></li>
                         <li><a class="dropdown-item" href="student_main.php">
                            <i class="fas fa-user-graduate me-2"></i>Assign Student
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

        <!-- Search Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search Assignments</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="d-flex">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by staff name, email, item code..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                        <a href="staff_main.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Active Filters Display -->
                <?php if (isset($_GET['search'])): ?>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-filter me-1"></i>Active filter: 
                        <?php if (!empty($_GET['search'])): ?>
                        <span class="badge bg-info">Search: "<?php echo htmlspecialchars($_GET['search']); ?>"</span>
                        <?php endif; ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active Assignments -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-list-check me-2"></i>
                    Active Staff Assignments
                    <span class="badge bg-light text-dark ms-2"><?php echo mysqli_num_rows($assignments_result); ?> Active</span>
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="assignmentsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Staff Member</th>
                                <th>Item Details</th>
                                <th>Assigned Date</th>
                                <th>Due Date</th>
                                <th>Assigned By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($assignments_result) == 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                                            <h5>No Active Assignments</h5>
                                            <p class="text-muted">
                                                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                                No assignments found for "<?php echo htmlspecialchars($_GET['search']); ?>"
                                                <?php else: ?>
                                                No maintenance items are currently assigned to staff.
                                                <?php endif; ?>
                                            </p>
                                            <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#assignModal">
                                                <i class="fas fa-plus me-2"></i>Assign First Item
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($assignment = mysqli_fetch_assoc($assignments_result)): ?>
                                <tr>
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
                                                <strong><?php echo htmlspecialchars($assignment['staff_name']); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong class="text-primary"><?php echo htmlspecialchars($assignment['item_code']); ?></strong>
                                            <div class="small text-muted">
                                                <?php echo ucfirst($assignment['item_type']); ?> • 
                                                <?php echo htmlspecialchars($assignment['item_description'] ?: 'No description'); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($assignment['assigned_date'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($assignment['due_date']): ?>
                                            <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No due date</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($assignment['assigned_by_fname']): ?>
                                            <?php echo htmlspecialchars($assignment['assigned_by_fname'] . ' ' . $assignment['assigned_by_lname']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-warning return-item" 
                                                    data-bs-toggle="modal" data-bs-target="#returnModal"
                                                    data-id="<?php echo $assignment['id']; ?>"
                                                    data-item-code="<?php echo htmlspecialchars($assignment['item_code']); ?>"
                                                    data-staff-name="<?php echo htmlspecialchars($assignment['staff_name']); ?>"
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
                    <h5 class="modal-title" id="assignModalLabel">Assign Items to Staff</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Staff Search -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Staff <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="staffSearch" class="form-control" 
                                   placeholder="Start typing staff name or email..." 
                                   autocomplete="off">
                            <input type="hidden" id="staff_id" name="staff_id" required>
                        </div>
                        <div id="staffSearchResults" class="mt-2 border rounded" style="display: none; max-height: 300px; overflow-y: auto;">
                            <!-- Results will appear here -->
                        </div>
                        <div id="selectedStaff" class="mt-3 d-none">
                            <div class="alert alert-success d-flex align-items-center">
                                <i class="fas fa-check-circle me-3 fs-4"></i>
                                <div>
                                    <strong id="selectedStaffName"></strong>
                                    <div class="small">
                                        <span id="selectedStaffEmail"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dual Column Item Selection -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fas fa-table me-2"></i>Assign Table</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Search & Select Available Tables</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                            <input type="text" id="tableSearch" class="form-control" 
                                                   placeholder="Type table code or description..." 
                                                   autocomplete="off" disabled>
                                            <button type="button" class="btn btn-outline-primary" id="showTablesBtn" disabled>
                                                <i class="fas fa-list"></i>
                                            </button>
                                            <input type="hidden" id="table_id" name="table_id">
                                        </div>
                                        <div id="tableSearchResults" class="mt-2 border rounded" style="display: none; max-height: 200px; overflow-y: auto;">
                                            <!-- Table results will appear here -->
                                        </div>
                                        <!-- Tables List Modal Trigger -->
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="viewAllTablesBtn" disabled>
                                                <i class="fas fa-table-list me-1"></i>View All Available Tables
                                            </button>
                                        </div>
                                    </div>
                                    <div id="selectedTable" class="mt-3 d-none">
                                        <div class="alert alert-info d-flex align-items-center">
                                            <i class="fas fa-table me-3"></i>
                                            <div>
                                                <strong id="selectedTableCode"></strong>
                                                <div class="small">
                                                    <span id="selectedTableDescription"></span>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="clearTableSelection()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div id="noTableSelected" class="text-center text-muted p-3 border rounded">
                                        <i class="fas fa-table fa-2x mb-2"></i>
                                        <p class="mb-0">No table selected</p>
                                        <small>Optional - Staff may not need a table</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="fas fa-chair me-2"></i>Assign Chair</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Search & Select Available Chairs</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                            <input type="text" id="chairSearch" class="form-control" 
                                                   placeholder="Type chair code or description..." 
                                                   autocomplete="off" disabled>
                                            <button type="button" class="btn btn-outline-info" id="showChairsBtn" disabled>
                                                <i class="fas fa-list"></i>
                                            </button>
                                            <input type="hidden" id="chair_id" name="chair_id">
                                        </div>
                                        <div id="chairSearchResults" class="mt-2 border rounded" style="display: none; max-height: 200px; overflow-y: auto;">
                                            <!-- Chair results will appear here -->
                                        </div>
                                        <!-- Chairs List Modal Trigger -->
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="viewAllChairsBtn" disabled>
                                                <i class="fas fa-chair me-1"></i>View All Available Chairs
                                            </button>
                                        </div>
                                    </div>
                                    <div id="selectedChair" class="mt-3 d-none">
                                        <div class="alert alert-info d-flex align-items-center">
                                            <i class="fas fa-chair me-3"></i>
                                            <div>
                                                <strong id="selectedChairCode"></strong>
                                                <div class="small">
                                                    <span id="selectedChairDescription"></span>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="clearChairSelection()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div id="noChairSelected" class="text-center text-muted p-3 border rounded">
                                        <i class="fas fa-chair fa-2x mb-2"></i>
                                        <p class="mb-0">No chair selected</p>
                                        <small>Optional - Staff may not need a chair</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Current Assignments -->
                    <div id="currentAssignments" class="mt-4 d-none">
                        <h6>Current Assignments for <span id="currentStaffName"></span>:</h6>
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
                            <small class="text-muted">Set when these assignments should be returned</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="notes" class="form-label fw-bold">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Enter any notes about these assignments"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_items" class="btn btn-primary" id="assignButton" disabled>
                        <i class="fas fa-save me-2"></i>Assign Selected Items
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tables List Modal -->
<div class="modal fade" id="tablesListModal" tabindex="-1" aria-labelledby="tablesListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                <h5 class="modal-title" id="tablesListModalLabel">
                    <i class="fas fa-table me-2"></i>Available Tables
                    <span class="badge bg-light text-dark ms-2"><?php echo mysqli_num_rows($available_tables_result); ?> Available</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($available_tables_result) == 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">No tables available</td>
                                </tr>
                            <?php else: ?>
                                <?php mysqli_data_seek($available_tables_result, 0); ?>
                                <?php while ($table = mysqli_fetch_assoc($available_tables_result)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($table['item_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($table['description'] ?: 'No description'); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary select-table-btn" 
                                                data-id="<?php echo $table['id']; ?>"
                                                data-code="<?php echo htmlspecialchars($table['item_code']); ?>"
                                                data-description="<?php echo htmlspecialchars($table['description']); ?>">
                                            <i class="fas fa-check me-1"></i>Select
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
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

<!-- Chairs List Modal -->
<div class="modal fade" id="chairsListModal" tabindex="-1" aria-labelledby="chairsListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                <h5 class="modal-title" id="chairsListModalLabel">
                    <i class="fas fa-chair me-2"></i>Available Chairs
                    <span class="badge bg-light text-dark ms-2"><?php echo mysqli_num_rows($available_chairs_result); ?> Available</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($available_chairs_result) == 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">No chairs available</td>
                                </tr>
                            <?php else: ?>
                                <?php mysqli_data_seek($available_chairs_result, 0); ?>
                                <?php while ($chair = mysqli_fetch_assoc($available_chairs_result)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($chair['item_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($chair['description'] ?: 'No description'); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-info select-chair-btn" 
                                                data-id="<?php echo $chair['id']; ?>"
                                                data-code="<?php echo htmlspecialchars($chair['item_code']); ?>"
                                                data-description="<?php echo htmlspecialchars($chair['description']); ?>">
                                            <i class="fas fa-check me-1"></i>Select
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
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
                        <p>From: <strong id="returnStaffName"></strong></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="return_condition" class="form-label fw-bold">Return Condition <span class="text-danger">*</span></label>
                        <select class="form-select" id="return_condition" name="return_condition" required>
                            <option value="">Select Condition</option>
                            <option value="good" selected>Good - Ready for reassignment</option>
                            <option value="damaged">Damaged - Needs repair</option>
                            <option value="lost">Lost</option>
                        </select>
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
    // Elements
    const staffSearch = document.getElementById('staffSearch');
    const staffResults = document.getElementById('staffSearchResults');
    const staffIdInput = document.getElementById('staff_id');
    const selectedStaffDiv = document.getElementById('selectedStaff');
    const selectedStaffName = document.getElementById('selectedStaffName');
    const selectedStaffEmail = document.getElementById('selectedStaffEmail');
    
    const tableSearch = document.getElementById('tableSearch');
    const tableResults = document.getElementById('tableSearchResults');
    const tableIdInput = document.getElementById('table_id');
    const selectedTableDiv = document.getElementById('selectedTable');
    const selectedTableCode = document.getElementById('selectedTableCode');
    const selectedTableDescription = document.getElementById('selectedTableDescription');
    const noTableSelected = document.getElementById('noTableSelected');
    const showTablesBtn = document.getElementById('showTablesBtn');
    const viewAllTablesBtn = document.getElementById('viewAllTablesBtn');
    
    const chairSearch = document.getElementById('chairSearch');
    const chairResults = document.getElementById('chairSearchResults');
    const chairIdInput = document.getElementById('chair_id');
    const selectedChairDiv = document.getElementById('selectedChair');
    const selectedChairCode = document.getElementById('selectedChairCode');
    const selectedChairDescription = document.getElementById('selectedChairDescription');
    const noChairSelected = document.getElementById('noChairSelected');
    const showChairsBtn = document.getElementById('showChairsBtn');
    const viewAllChairsBtn = document.getElementById('viewAllChairsBtn');
    
    const currentAssignmentsDiv = document.getElementById('currentAssignments');
    const currentStaffName = document.getElementById('currentStaffName');
    const currentAssignmentsTable = document.querySelector('#currentAssignmentsTable tbody');
    
    const assignButton = document.getElementById('assignButton');
    
    // Modals
    const tablesListModal = new bootstrap.Modal(document.getElementById('tablesListModal'));
    const chairsListModal = new bootstrap.Modal(document.getElementById('chairsListModal'));
    
    // Variables
    let searchTimeout;
    let currentStaffId = null;
    let hasTableSelected = false;
    let hasChairSelected = false;

    // Staff search functionality
    staffSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            staffResults.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            fetch(`staff_main.php?ajax_staff_search=true&q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        staffResults.innerHTML = '<div class="p-3 text-muted">No staff found</div>';
                        staffResults.style.display = 'block';
                        return;
                    }
                    
                    let html = '<div class="list-group list-group-flush">';
                    data.forEach(staff => {
                        const fullName = staff.full_name || `${staff.first_name} ${staff.middle_name || ''} ${staff.last_name}`.trim();
                        html += `
                        <a href="#" class="list-group-item list-group-item-action staff-option" 
                           data-id="${staff.id}" 
                           data-name="${fullName}"
                           data-email="${staff.email}"
                           data-role="${staff.role_name || 'Staff'}">
                            <div class="d-flex w-100 justify-content-between">
                                <strong>${fullName}</strong>
                                <small class="text-muted">${staff.role_name || 'Staff'}</small>
                            </div>
                            <div class="small text-muted">
                                ${staff.email}
                            </div>
                        </a>
                        `;
                    });
                    html += '</div>';
                    
                    staffResults.innerHTML = html;
                    staffResults.style.display = 'block';
                    
                    // Add click event listeners to staff options
                    document.querySelectorAll('.staff-option').forEach(option => {
                        option.addEventListener('click', function(e) {
                            e.preventDefault();
                            selectStaff(
                                this.getAttribute('data-id'),
                                this.getAttribute('data-name'),
                                this.getAttribute('data-email'),
                                this.getAttribute('data-role')
                            );
                        });
                    });
                })
                .catch(error => {
                    console.error('Error searching staff:', error);
                    staffResults.innerHTML = '<div class="p-3 text-danger">Error searching staff</div>';
                    staffResults.style.display = 'block';
                });
        }, 300);
    });
    
    // Function to select a staff
    function selectStaff(id, name, email, role) {
        currentStaffId = id;
        staffIdInput.value = id;
        selectedStaffName.textContent = name;
        selectedStaffEmail.textContent = email;
        
        selectedStaffDiv.classList.remove('d-none');
        staffSearch.value = name;
        staffResults.style.display = 'none';
        
        // Enable item searches and buttons
        tableSearch.disabled = false;
        chairSearch.disabled = false;
        showTablesBtn.disabled = false;
        showChairsBtn.disabled = false;
        viewAllTablesBtn.disabled = false;
        viewAllChairsBtn.disabled = false;
        
        // Load current assignments for this staff
        loadCurrentAssignments(id, name);
        
        // Update assign button state
        updateAssignButtonState();
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
            fetch(`staff_main.php?ajax_item_search=true&q=${encodeURIComponent(query)}&type=table`)
                .then(response => response.json())
                .then(data => {
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
    
    // Show tables list button
    showTablesBtn.addEventListener('click', function() {
        tableSearch.dispatchEvent(new Event('input'));
    });
    
    // View all tables modal button
    viewAllTablesBtn.addEventListener('click', function() {
        tablesListModal.show();
    });
    
    // Function to select a table
    function selectTable(id, code, description) {
        tableIdInput.value = id;
        tableSearch.value = code;
        selectedTableCode.textContent = code;
        selectedTableDescription.textContent = description;
        
        selectedTableDiv.classList.remove('d-none');
        noTableSelected.style.display = 'none';
        tableResults.style.display = 'none';
        
        hasTableSelected = true;
        updateAssignButtonState();
        
        // Close tables list modal if open
        tablesListModal.hide();
    }
    
    // Function to clear table selection
    window.clearTableSelection = function() {
        tableIdInput.value = '';
        tableSearch.value = '';
        selectedTableDiv.classList.add('d-none');
        noTableSelected.style.display = 'block';
        
        hasTableSelected = false;
        updateAssignButtonState();
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
            fetch(`staff_main.php?ajax_item_search=true&q=${encodeURIComponent(query)}&type=chair`)
                .then(response => response.json())
                .then(data => {
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
    
    // Show chairs list button
    showChairsBtn.addEventListener('click', function() {
        chairSearch.dispatchEvent(new Event('input'));
    });
    
    // View all chairs modal button
    viewAllChairsBtn.addEventListener('click', function() {
        chairsListModal.show();
    });
    
    // Function to select a chair
    function selectChair(id, code, description) {
        chairIdInput.value = id;
        chairSearch.value = code;
        selectedChairCode.textContent = code;
        selectedChairDescription.textContent = description;
        
        selectedChairDiv.classList.remove('d-none');
        noChairSelected.style.display = 'none';
        chairResults.style.display = 'none';
        
        hasChairSelected = true;
        updateAssignButtonState();
        
        // Close chairs list modal if open
        chairsListModal.hide();
    }
    
    // Function to clear chair selection
    window.clearChairSelection = function() {
        chairIdInput.value = '';
        chairSearch.value = '';
        selectedChairDiv.classList.add('d-none');
        noChairSelected.style.display = 'block';
        
        hasChairSelected = false;
        updateAssignButtonState();
    }
    
    // Update assign button state
    function updateAssignButtonState() {
        const hasStaff = currentStaffId !== null;
        const hasItemSelected = hasTableSelected || hasChairSelected;
        
        assignButton.disabled = !(hasStaff && hasItemSelected);
    }
    
    // Load current assignments for selected staff
    function loadCurrentAssignments(staffId, staffName) {
        fetch(`staff_main.php?ajax_current_assignments=true&staff_id=${staffId}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    currentStaffName.textContent = staffName;
                    currentAssignmentsTable.innerHTML = '';
                    
                    data.forEach(assignment => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${assignment.item_code}</td>
                            <td><span class="badge ${assignment.assignment_type === 'table' ? 'bg-primary' : 'bg-info'}">${assignment.assignment_type}</span></td>
                            <td>${assignment.assigned_date}</td>
                            <td><span class="badge bg-success">Active</span></td>
                        `;
                        currentAssignmentsTable.appendChild(row);
                    });
                    
                    currentAssignmentsDiv.classList.remove('d-none');
                    
                    // Pre-fill search fields with existing assignments
                    data.forEach(assignment => {
                        if (assignment.assignment_type === 'table') {
                            tableSearch.value = `${assignment.item_code} (Already assigned)`;
                            tableSearch.disabled = true;
                            showTablesBtn.disabled = true;
                            viewAllTablesBtn.disabled = true;
                            hasTableSelected = true;
                        } else if (assignment.assignment_type === 'chair') {
                            chairSearch.value = `${assignment.item_code} (Already assigned)`;
                            chairSearch.disabled = true;
                            showChairsBtn.disabled = true;
                            viewAllChairsBtn.disabled = true;
                            hasChairSelected = true;
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
        if (!staffSearch.contains(e.target) && !staffResults.contains(e.target)) {
            staffResults.style.display = 'none';
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
        staffSearch.value = '';
        staffIdInput.value = '';
        selectedStaffDiv.classList.add('d-none');
        staffResults.style.display = 'none';
        staffResults.innerHTML = '';
        
        tableSearch.value = '';
        tableSearch.disabled = true;
        tableIdInput.value = '';
        tableResults.style.display = 'none';
        tableResults.innerHTML = '';
        selectedTableDiv.classList.add('d-none');
        noTableSelected.style.display = 'block';
        showTablesBtn.disabled = true;
        viewAllTablesBtn.disabled = true;
        
        chairSearch.value = '';
        chairSearch.disabled = true;
        chairIdInput.value = '';
        chairResults.style.display = 'none';
        chairResults.innerHTML = '';
        selectedChairDiv.classList.add('d-none');
        noChairSelected.style.display = 'block';
        showChairsBtn.disabled = true;
        viewAllChairsBtn.disabled = true;
        
        currentAssignmentsDiv.classList.add('d-none');
        currentAssignmentsTable.innerHTML = '';
        
        document.getElementById('due_date').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('notes').value = '';
        
        assignButton.disabled = true;
        currentStaffId = null;
        hasTableSelected = false;
        hasChairSelected = false;
    }
    
    // Return item modal
    document.querySelectorAll('.return-item').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const itemCode = this.getAttribute('data-item-code');
            const staffName = this.getAttribute('data-staff-name');
            
            document.getElementById('return_assignment_id').value = id;
            document.getElementById('returnItemCode').textContent = itemCode;
            document.getElementById('returnStaffName').textContent = staffName;
        });
    });
    
    // View assignment details
    document.querySelectorAll('.view-assignment').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            fetch(`get_assignment.php?id=${id}&type=staff`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('assignmentDetails').innerHTML = data;
                })
                .catch(error => {
                    console.error('Error loading assignment details:', error);
                    document.getElementById('assignmentDetails').innerHTML = 
                        '<div class="alert alert-danger">Error loading assignment details</div>';
                });
        });
    });
    
    // Set today's date as default for due date
    const today = new Date();
    const dueDateInput = document.getElementById('due_date');
    if (dueDateInput) {
        // Set due date to 30 days from today
        const dueDate = new Date();
        dueDate.setDate(today.getDate() + 30);
        dueDateInput.min = today.toISOString().split('T')[0];
    }
    
    // Focus on search inputs when clicked
    tableSearch.addEventListener('focus', function() {
        if (!hasTableSelected) {
            this.value = '';
            this.dispatchEvent(new Event('input'));
        }
    });
    
    chairSearch.addEventListener('focus', function() {
        if (!hasChairSelected) {
            this.value = '';
            this.dispatchEvent(new Event('input'));
        }
    });
    
    // Add event listeners for table selection buttons in the tables list modal
    document.querySelectorAll('.select-table-btn').forEach(button => {
        button.addEventListener('click', function() {
            selectTable(
                this.getAttribute('data-id'),
                this.getAttribute('data-code'),
                this.getAttribute('data-description')
            );
        });
    });
    
    // Add event listeners for chair selection buttons in the chairs list modal
    document.querySelectorAll('.select-chair-btn').forEach(button => {
        button.addEventListener('click', function() {
            selectChair(
                this.getAttribute('data-id'),
                this.getAttribute('data-code'),
                this.getAttribute('data-description')
            );
        });
    });
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
}

.btn-group .btn {
    border-radius: 6px !important;
    margin: 0 2px;
}

#staffSearchResults, 
#tableSearchResults, 
#chairSearchResults {
    background-color: white;
    z-index: 1050;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

#staffSearchResults .staff-option:hover,
#tableSearchResults .table-option:hover,
#chairSearchResults .chair-option:hover {
    background-color: #f8f9fa;
    cursor: pointer;
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
    
    .input-group > button {
        padding: 0.375rem 0.75rem;
    }
}

/* Custom styles for the dual column layout */
.modal-body .card {
    border: 1px solid #dee2e6;
}

.modal-body .card-header {
    font-weight: 600;
    padding: 0.75rem 1rem;
}

#noTableSelected, #noChairSelected {
    background-color: #f8f9fa;
    border-style: dashed !important;
    border-width: 2px !important;
    border-color: #dee2e6 !important;
}
</style>

<?php include '../controller/footer.php'; ?>