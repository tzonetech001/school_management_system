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

// Function to automatically return items when student leaves/graduates
function returnItemsForLeaver($conn, $student_id, $admin_id, $reason = 'Student left/graduated') {
    mysqli_begin_transaction($conn);
    $returned_count = 0;
    
    try {
        // Get all active assignments for the student
        $assignments_sql = "SELECT ma.*, mi.item_code, s.first_name, s.last_name 
                           FROM maintenance_assignments ma
                           JOIN maintenance_items mi ON ma.item_id = mi.id
                           JOIN students s ON ma.student_id = s.id
                           WHERE ma.student_id = $student_id 
                           AND ma.status = 'active'";
        
        $assignments_result = mysqli_query($conn, $assignments_sql);
        
        while ($assignment = mysqli_fetch_assoc($assignments_result)) {
            // Update assignment status
            $update_sql = "UPDATE maintenance_assignments SET 
                          status = 'returned',
                          return_date = CURDATE(),
                          return_condition = 'good',
                          return_notes = 'Auto-returned: $reason'
                          WHERE id = {$assignment['id']}";
            
            if (!mysqli_query($conn, $update_sql)) {
                throw new Exception("Error updating assignment: " . mysqli_error($conn));
            }
            
            // Update item status to available
            $update_item_sql = "UPDATE maintenance_items SET status = 'available' WHERE id = {$assignment['item_id']}";
            if (!mysqli_query($conn, $update_item_sql)) {
                throw new Exception("Error updating item status: " . mysqli_error($conn));
            }
            
            // Log the action
            $student_name = $assignment['first_name'] . ' ' . $assignment['last_name'];
            $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                       VALUES ({$assignment['item_id']}, 'return', 'student', $student_id, $admin_id, 
                       'Auto-returned {$assignment['item_code']} from student: $student_name ($reason)')";
            if (!mysqli_query($conn, $log_sql)) {
                throw new Exception("Error logging return: " . mysqli_error($conn));
            }
            
            $returned_count++;
        }
        
        mysqli_commit($conn);
        return $returned_count;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error returning items for leaver: " . $e->getMessage());
        return 0;
    }
}

// Function to automatically return items when staff is deleted
function returnItemsForDeletedStaff($conn, $staff_id, $admin_id) {
    mysqli_begin_transaction($conn);
    $returned_count = 0;
    
    try {
        // Get all active assignments for the staff
        $assignments_sql = "SELECT msa.*, mi.item_code, a.first_name, a.last_name 
                           FROM maintenance_staff_assignments msa
                           JOIN maintenance_items mi ON msa.item_id = mi.id
                           JOIN admins a ON msa.staff_id = a.id
                           WHERE msa.staff_id = $staff_id 
                           AND msa.status = 'active'";
        
        $assignments_result = mysqli_query($conn, $assignments_sql);
        
        while ($assignment = mysqli_fetch_assoc($assignments_result)) {
            // Update assignment status
            $update_sql = "UPDATE maintenance_staff_assignments SET 
                          status = 'returned',
                          return_date = CURDATE(),
                          return_condition = 'good',
                          return_notes = 'Auto-returned: Staff deleted'
                          WHERE id = {$assignment['id']}";
            
            if (!mysqli_query($conn, $update_sql)) {
                throw new Exception("Error updating assignment: " . mysqli_error($conn));
            }
            
            // Update item status to available
            $update_item_sql = "UPDATE maintenance_items SET status = 'available' WHERE id = {$assignment['item_id']}";
            if (!mysqli_query($conn, $update_item_sql)) {
                throw new Exception("Error updating item status: " . mysqli_error($conn));
            }
            
            // Log the action
            $staff_name = $assignment['first_name'] . ' ' . $assignment['last_name'];
            $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                       VALUES ({$assignment['item_id']}, 'return', 'staff', $staff_id, $admin_id, 
                       'Auto-returned {$assignment['item_code']} from staff: $staff_name (Staff deleted)')";
            if (!mysqli_query($conn, $log_sql)) {
                throw new Exception("Error logging return: " . mysqli_error($conn));
            }
            
            $returned_count++;
        }
        
        mysqli_commit($conn);
        return $returned_count;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error returning items for deleted staff: " . $e->getMessage());
        return 0;
    }
}

// Function to cleanup all maintenance data for a deleted student
function cleanupStudentMaintenanceData($conn, $student_id, $admin_id) {
    $cleaned_count = 0;
    
    try {
        // First return all items
        $cleaned_count += returnItemsForLeaver($conn, $student_id, $admin_id, 'Student deleted');
        
        // Delete maintenance assignment history (optional - keep for audit)
        // $delete_history_sql = "DELETE FROM maintenance_assignments WHERE student_id = $student_id";
        // if (mysqli_query($conn, $delete_history_sql)) {
        //     $cleaned_count += mysqli_affected_rows($conn);
        // }
        
        // Delete maintenance logs for this student
        $delete_logs_sql = "DELETE FROM maintenance_logs WHERE user_id = $student_id AND user_type = 'student'";
        if (mysqli_query($conn, $delete_logs_sql)) {
            $cleaned_count += mysqli_affected_rows($conn);
        }
        
        return $cleaned_count;
        
    } catch (Exception $e) {
        error_log("Error cleaning up maintenance data: " . $e->getMessage());
        return $cleaned_count;
    }
}

// Check for orphaned assignments (status = assigned but no active assignments)
function fixOrphanedAssignments($conn, $admin_id) {
    mysqli_begin_transaction($conn);
    $fixed_count = 0;
    
    try {
        // Find items with status 'assigned' but no active assignments
        $orphaned_sql = "SELECT mi.id, mi.item_code, mi.status
                        FROM maintenance_items mi
                        WHERE mi.status = 'assigned'
                        AND NOT EXISTS (
                            SELECT 1 FROM maintenance_assignments ma 
                            WHERE ma.item_id = mi.id AND ma.status = 'active'
                        )
                        AND NOT EXISTS (
                            SELECT 1 FROM maintenance_staff_assignments msa 
                            WHERE msa.item_id = mi.id AND msa.status = 'active'
                        )";
        
        $orphaned_result = mysqli_query($conn, $orphaned_sql);
        
        while ($item = mysqli_fetch_assoc($orphaned_result)) {
            // Update item status to available
            $update_sql = "UPDATE maintenance_items SET status = 'available' WHERE id = {$item['id']}";
            if (!mysqli_query($conn, $update_sql)) {
                throw new Exception("Error updating orphaned item: " . mysqli_error($conn));
            }
            
            // Log the action
            $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, admin_id, description) 
                       VALUES ({$item['id']}, 'repair', $admin_id, 
                       'Auto-fixed orphaned item: {$item['item_code']}. Status changed from assigned to available.')";
            if (!mysqli_query($conn, $log_sql)) {
                throw new Exception("Error logging orphaned item fix: " . mysqli_error($conn));
            }
            
            $fixed_count++;
        }
        
        // Also check for students who are leavers but still have active assignments
        $leaver_assignments_sql = "SELECT ma.id as assignment_id, ma.student_id, ma.item_id, mi.item_code
                                  FROM maintenance_assignments ma
                                  JOIN maintenance_items mi ON ma.item_id = mi.id
                                  JOIN students s ON ma.student_id = s.id
                                  WHERE ma.status = 'active' 
                                  AND s.is_leaver = 1";
        
        $leaver_result = mysqli_query($conn, $leaver_assignments_sql);
        
        while ($leaver = mysqli_fetch_assoc($leaver_result)) {
            // Auto-return items for leavers
            $update_sql = "UPDATE maintenance_assignments SET 
                          status = 'returned',
                          return_date = CURDATE(),
                          return_condition = 'good',
                          return_notes = 'Auto-returned: Student is leaver/graduate'
                          WHERE id = {$leaver['assignment_id']}";
            
            if (!mysqli_query($conn, $update_sql)) {
                throw new Exception("Error updating leaver assignment: " . mysqli_error($conn));
            }
            
            $update_item_sql = "UPDATE maintenance_items SET status = 'available' WHERE id = {$leaver['item_id']}";
            if (!mysqli_query($conn, $update_item_sql)) {
                throw new Exception("Error updating leaver item: " . mysqli_error($conn));
            }
            
            $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                       VALUES ({$leaver['item_id']}, 'auto_return', 'student', {$leaver['student_id']}, $admin_id, 
                       'Auto-returned {$leaver['item_code']} - Student is leaver/graduate')";
            mysqli_query($conn, $log_sql);
            
            $fixed_count++;
        }
        
        mysqli_commit($conn);
        return $fixed_count;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error fixing orphaned assignments: " . $e->getMessage());
        return 0;
    }
}

// Auto-fix orphaned assignments and leaver items on page load
$fixed_orphaned = fixOrphanedAssignments($conn, $_SESSION['admin_id']);
if ($fixed_orphaned > 0) {
    $_SESSION['info'] = "Auto-fixed $fixed_orphaned orphaned/leaver items (status updated to available).";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_item'])) {
        $item_code = mysqli_real_escape_string($conn, $_POST['item_code']);
        $item_type = mysqli_real_escape_string($conn, $_POST['item_type']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $location = mysqli_real_escape_string($conn, $_POST['location']);
        $signed_at = mysqli_real_escape_string($conn, $_POST['signed_at']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
        
        // Check if item code already exists
        $check_sql = "SELECT id FROM maintenance_items WHERE item_code = '$item_code'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error'] = "Item code already exists!";
        } else {
            $insert_sql = "INSERT INTO maintenance_items (item_code, item_type, description, location, signed_at, notes) 
                          VALUES ('$item_code', '$item_type', '$description', '$location', '$signed_at', '$notes')";
            
            if (mysqli_query($conn, $insert_sql)) {
                $_SESSION['success'] = "Item added successfully!";
            } else {
                $_SESSION['error'] = "Error adding item: " . mysqli_error($conn);
            }
        }
        
        header("Location: inventory.php");
        exit();
        
    } elseif (isset($_POST['update_item'])) {
        $item_id = mysqli_real_escape_string($conn, $_POST['item_id']);
        $item_code = mysqli_real_escape_string($conn, $_POST['item_code']);
        $item_type = mysqli_real_escape_string($conn, $_POST['item_type']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $location = mysqli_real_escape_string($conn, $_POST['location']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $signed_at = mysqli_real_escape_string($conn, $_POST['signed_at']);
        $last_maintenance = mysqli_real_escape_string($conn, $_POST['last_maintenance']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
        
        $update_sql = "UPDATE maintenance_items SET 
                      item_code = '$item_code',
                      item_type = '$item_type',
                      description = '$description',
                      location = '$location',
                      status = '$status',
                      signed_at = '$signed_at',
                      last_maintenance = '$last_maintenance',
                      notes = '$notes'
                      WHERE id = $item_id";
        
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['success'] = "Item updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating item: " . mysqli_error($conn);
        }
        
        header("Location: inventory.php");
        exit();
        
    } elseif (isset($_POST['delete_item'])) {
        $item_id = mysqli_real_escape_string($conn, $_POST['item_id']);
        
        // Check if item is assigned
        $check_assigned_sql = "SELECT id FROM maintenance_assignments WHERE item_id = $item_id AND status = 'active'
                              UNION
                              SELECT id FROM maintenance_staff_assignments WHERE item_id = $item_id AND status = 'active'";
        $check_result = mysqli_query($conn, $check_assigned_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error'] = "Cannot delete item. It is currently assigned to a user.";
        } else {
            // Check if there are any historical assignments before deleting
            $check_history_sql = "SELECT id FROM maintenance_assignments WHERE item_id = $item_id
                                 UNION
                                 SELECT id FROM maintenance_staff_assignments WHERE item_id = $item_id";
            $history_result = mysqli_query($conn, $check_history_sql);
            
            if (mysqli_num_rows($history_result) > 0) {
                $_SESSION['error'] = "Cannot delete item. It has historical assignment records.";
            } else {
                // First delete related logs
                $delete_logs_sql = "DELETE FROM maintenance_logs WHERE item_id = $item_id";
                mysqli_query($conn, $delete_logs_sql);
                
                // Then delete the item
                $delete_sql = "DELETE FROM maintenance_items WHERE id = $item_id";
                
                if (mysqli_query($conn, $delete_sql)) {
                    $_SESSION['success'] = "Item deleted successfully!";
                } else {
                    $_SESSION['error'] = "Error deleting item: " . mysqli_error($conn);
                }
            }
        }
        
        header("Location: inventory.php");
        exit();
        
    } elseif (isset($_POST['force_return_item'])) {
        $item_id = mysqli_real_escape_string($conn, $_POST['item_id']);
        $admin_id = $_SESSION['admin_id'];
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Get item details
            $item_sql = "SELECT item_code FROM maintenance_items WHERE id = $item_id";
            $item_result = mysqli_query($conn, $item_sql);
            $item = mysqli_fetch_assoc($item_result);
            
            // Return from student assignments
            $student_assignments_sql = "SELECT ma.*, s.first_name, s.last_name 
                                       FROM maintenance_assignments ma
                                       JOIN students s ON ma.student_id = s.id
                                       WHERE ma.item_id = $item_id AND ma.status = 'active'";
            $student_result = mysqli_query($conn, $student_assignments_sql);
            
            while ($assignment = mysqli_fetch_assoc($student_result)) {
                $update_sql = "UPDATE maintenance_assignments SET 
                              status = 'returned',
                              return_date = CURDATE(),
                              return_condition = 'good',
                              return_notes = 'Force returned by admin'
                              WHERE id = {$assignment['id']}";
                
                if (!mysqli_query($conn, $update_sql)) {
                    throw new Exception("Error returning student assignment: " . mysqli_error($conn));
                }
                
                $student_name = $assignment['first_name'] . ' ' . $assignment['last_name'];
                $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                           VALUES ($item_id, 'return', 'student', {$assignment['student_id']}, $admin_id, 
                           'Force returned {$item['item_code']} from student: $student_name by admin')";
                mysqli_query($conn, $log_sql);
            }
            
            // Return from staff assignments
            $staff_assignments_sql = "SELECT msa.*, a.first_name, a.last_name 
                                     FROM maintenance_staff_assignments msa
                                     JOIN admins a ON msa.staff_id = a.id
                                     WHERE msa.item_id = $item_id AND msa.status = 'active'";
            $staff_result = mysqli_query($conn, $staff_assignments_sql);
            
            while ($assignment = mysqli_fetch_assoc($staff_result)) {
                $update_sql = "UPDATE maintenance_staff_assignments SET 
                              status = 'returned',
                              return_date = CURDATE(),
                              return_condition = 'good',
                              return_notes = 'Force returned by admin'
                              WHERE id = {$assignment['id']}";
                
                if (!mysqli_query($conn, $update_sql)) {
                    throw new Exception("Error returning staff assignment: " . mysqli_error($conn));
                }
                
                $staff_name = $assignment['first_name'] . ' ' . $assignment['last_name'];
                $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                           VALUES ($item_id, 'return', 'staff', {$assignment['staff_id']}, $admin_id, 
                           'Force returned {$item['item_code']} from staff: $staff_name by admin')";
                mysqli_query($conn, $log_sql);
            }
            
            // Update item status to available
            $update_item_sql = "UPDATE maintenance_items SET status = 'available' WHERE id = $item_id";
            if (!mysqli_query($conn, $update_item_sql)) {
                throw new Exception("Error updating item status: " . mysqli_error($conn));
            }
            
            mysqli_commit($conn);
            $_SESSION['success'] = "Item force returned successfully!";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: inventory.php");
        exit();
    }
}

// Get all maintenance items with accurate assignment status
$items_sql = "SELECT mi.*, 
             (SELECT COUNT(*) FROM maintenance_assignments WHERE item_id = mi.id AND status = 'active') as student_assignments,
             (SELECT COUNT(*) FROM maintenance_staff_assignments WHERE item_id = mi.id AND status = 'active') as staff_assignments,
             
             -- Get student assignment details if exists
             (SELECT CONCAT(s.first_name, ' ', s.last_name, ' (', s.index_number, ')') 
              FROM maintenance_assignments ma 
              JOIN students s ON ma.student_id = s.id 
              WHERE ma.item_id = mi.id AND ma.status = 'active' LIMIT 1) as assigned_to_student,
             
             -- Get staff assignment details if exists
             (SELECT CONCAT(a.first_name, ' ', a.last_name) 
              FROM maintenance_staff_assignments msa 
              JOIN admins a ON msa.staff_id = a.id 
              WHERE msa.item_id = mi.id AND msa.status = 'active' LIMIT 1) as assigned_to_staff,
             
             -- Check if assigned student is leaver
             (SELECT s.is_leaver 
              FROM maintenance_assignments ma 
              JOIN students s ON ma.student_id = s.id 
              WHERE ma.item_id = mi.id AND ma.status = 'active' LIMIT 1) as student_is_leaver
             
             FROM maintenance_items mi
             ORDER BY 
                 CASE WHEN mi.status = 'assigned' THEN 0 ELSE 1 END,
                 mi.item_type, 
                 mi.item_code";
$items_result = mysqli_query($conn, $items_sql);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_items,
    COUNT(CASE WHEN status = 'available' THEN 1 END) as available_items,
    COUNT(CASE WHEN status = 'assigned' THEN 1 END) as assigned_items,
    COUNT(CASE WHEN status = 'damaged' THEN 1 END) as damaged_items,
    COUNT(CASE WHEN status = 'under_maintenance' THEN 1 END) as under_maintenance_items,
    COUNT(CASE WHEN status = 'lost' THEN 1 END) as lost_items,
    COUNT(CASE WHEN item_type = 'table' THEN 1 END) as total_tables,
    COUNT(CASE WHEN item_type = 'chair' THEN 1 END) as total_chairs
    FROM maintenance_items";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Maintenance Inventory</h2>
                   <div>
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
                       <li><a class="dropdown-item" href="#"  class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addItemModal"><i class="fas fa-plus me-2"></i>Add First Item</a></li>
                         <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="student_main.php">
                            <i class="fas fa-user-graduate me-2"></i>Assign Student
                        </a></li>
                        <li><a class="dropdown-item" href="staff_main.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Assign Staff
                        </a></li>
                        <li><a class="dropdown-item" href="maintenance_logs.php">
                            <i class="fas fa-history me-2"></i>View Logs
                        </a></li>
                        <li><a class="dropdown-item" href="report_maintenance.php"><i class="fas fa-download me-2"></i>Export List</a></li>
                    </ul>
                </div>
            </div>
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
        
        <?php if (isset($_SESSION['info'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i><?php echo $_SESSION['info']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['info']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-cube" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $stats['total_items'] ?? 0; ?></h3>
                    <p>Total Items</p>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-info" role="progressbar" 
                             style="width: 100%;" 
                             aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-table" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $stats['total_tables'] ?? 0; ?></h3>
                    <p>Total Tables</p>
                    <div class="mt-2">
                        <span class="badge bg-info">Available: <?php echo $stats['available_items'] ?? 0; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-chair" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $stats['total_chairs'] ?? 0; ?></h3>
                    <p>Total Chairs</p>
                    <div class="mt-2">
                        <span class="badge bg-info">Available: <?php echo $stats['available_items'] ?? 0; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-check-circle" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $stats['available_items'] ?? 0; ?></h3>
                    <p>Available Items</p>
                    <div class="mt-2">
                        <span class="badge bg-success">Ready to Assign</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by item code, description, or location...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="typeFilter" class="form-select">
                            <option value="">All Types</option>
                            <option value="table">Table</option>
                            <option value="chair">Chair</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="available">Available</option>
                            <option value="assigned">Assigned</option>
                            <option value="damaged">Damaged</option>
                            <option value="under_maintenance">Under Maintenance</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" id="resetFilters" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-list-alt me-2"></i>
                    Maintenance Inventory Items
                    <span class="badge bg-light text-dark ms-2"><?php echo mysqli_num_rows($items_result); ?> Items</span>
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="inventoryTable">
                        <thead class="table-light">
                            <tr>
                                <th>Item Code</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Student Status</th>
                                <th>Signed At</th>
                                <th>Last Maintenance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($items_result) == 0): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-cube fa-3x text-muted mb-3"></i>
                                            <h5>No Inventory Items Found</h5>
                                            <p class="text-muted">No maintenance items have been added yet.</p>
                                            <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                                <i class="fas fa-plus me-2"></i>Add First Item
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($item = mysqli_fetch_assoc($items_result)): 
                                    $total_assignments = $item['student_assignments'] + $item['staff_assignments'];
                                    $is_orphaned = ($item['status'] == 'assigned' && $total_assignments == 0);
                                    $student_is_leaver = $item['student_is_leaver'] == 1;
                                ?>
                                <tr class="<?php echo $is_orphaned ? 'table-warning' : ''; ?> <?php echo $student_is_leaver ? 'table-danger' : ''; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['item_code']); ?></strong>
                                        <?php if ($item['signed_at']): ?>
                                            <div class="small text-muted">
                                                Signed: <?php echo date('M Y', strtotime($item['signed_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $item['item_type'] == 'table' ? 'bg-primary' : ($item['item_type'] == 'chair' ? 'bg-info' : 'bg-secondary'); ?>">
                                            <?php echo ucfirst($item['item_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['description'] ?: 'No description'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['location'] ?: 'Not specified'); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badge = '';
                                        switch ($item['status']) {
                                            case 'available':
                                                $status_badge = 'bg-success';
                                                break;
                                            case 'assigned':
                                                $status_badge = 'bg-warning text-dark';
                                                break;
                                            case 'damaged':
                                                $status_badge = 'bg-danger';
                                                break;
                                            case 'under_maintenance':
                                                $status_badge = 'bg-secondary';
                                                break;
                                            case 'lost':
                                                $status_badge = 'bg-dark';
                                                break;
                                            default:
                                                $status_badge = 'bg-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_badge; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                            <?php if ($is_orphaned): ?>
                                                <i class="fas fa-exclamation-triangle ms-1" title="Orphaned - needs fixing"></i>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($item['assigned_to_student']): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-graduate <?php echo $student_is_leaver ? 'text-danger' : 'text-primary'; ?> me-2"></i>
                                                <div>
                                                    <span class="small <?php echo $student_is_leaver ? 'text-danger' : ''; ?>"><?php echo htmlspecialchars($item['assigned_to_student']); ?></span>
                                                    <div class="text-muted">Student</div>
                                                </div>
                                            </div>
                                        <?php elseif ($item['assigned_to_staff']): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-chalkboard-teacher text-info me-2"></i>
                                                <div>
                                                    <span class="small"><?php echo htmlspecialchars($item['assigned_to_staff']); ?></span>
                                                    <div class="text-muted">Staff</div>
                                                </div>
                                            </div>
                                        <?php elseif ($is_orphaned): ?>
                                            <div class="text-warning">
                                                <i class="fas fa-exclamation-circle"></i>
                                                <span class="small">Orphaned - No active assignment</span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['assigned_to_student']): ?>
                                            <?php if ($student_is_leaver): ?>
                                                <span class="badge bg-danger">Left/Graduated</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['signed_at']): ?>
                                            <?php echo date('M d, Y', strtotime($item['signed_at'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['last_maintenance']): ?>
                                            <?php echo date('M d, Y', strtotime($item['last_maintenance'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-info edit-item" 
                                                    data-bs-toggle="modal" data-bs-target="#editItemModal"
                                                    data-id="<?php echo $item['id']; ?>"
                                                    data-item-code="<?php echo htmlspecialchars($item['item_code']); ?>"
                                                    data-item-type="<?php echo $item['item_type']; ?>"
                                                    data-description="<?php echo htmlspecialchars($item['description']); ?>"
                                                    data-location="<?php echo htmlspecialchars($item['location']); ?>"
                                                    data-status="<?php echo $item['status']; ?>"
                                                    data-signed-at="<?php echo htmlspecialchars($item['signed_at']); ?>"
                                                    data-last-maintenance="<?php echo htmlspecialchars($item['last_maintenance']); ?>"
                                                    data-notes="<?php echo htmlspecialchars($item['notes']); ?>"
                                                    title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger delete-item" 
                                                    data-id="<?php echo $item['id']; ?>"
                                                    data-item-code="<?php echo htmlspecialchars($item['item_code']); ?>"
                                                    title="Delete Item">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php if ($item['status'] == 'available'): ?>
                                                <a href="student_main.php?assign=<?php echo $item['id']; ?>&type=<?php echo $item['item_type']; ?>" 
                                                   class="btn btn-outline-success"
                                                   title="Assign to Student">
                                                    <i class="fas fa-user-graduate"></i>
                                                </a>
                                                <a href="staff_main.php?assign=<?php echo $item['id']; ?>&type=<?php echo $item['item_type']; ?>" 
                                                   class="btn btn-outline-primary"
                                                   title="Assign to Staff">
                                                    <i class="fas fa-chalkboard-teacher"></i>
                                                </a>
                                            <?php elseif ($item['status'] == 'assigned'): ?>
                                                <button type="button" class="btn btn-outline-warning force-return-item" 
                                                        data-bs-toggle="modal" data-bs-target="#forceReturnModal"
                                                        data-id="<?php echo $item['id']; ?>"
                                                        data-item-code="<?php echo htmlspecialchars($item['item_code']); ?>"
                                                        title="Force Return Item">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <?php if ($student_is_leaver): ?>
                                                    <button type="button" class="btn btn-outline-danger fix-leaver-item" 
                                                            data-id="<?php echo $item['id']; ?>"
                                                            data-item-code="<?php echo htmlspecialchars($item['item_code']); ?>"
                                                            title="Auto-return from leaver">
                                                        <i class="fas fa-user-slash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
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

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                    <h5 class="modal-title" id="addItemModalLabel">Add New Maintenance Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="item_code" class="form-label fw-bold">Item Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="item_code" name="item_code" 
                                   placeholder="Enter unique item code (e.g., TBL-001, CHR-001)" required>
                            <div class="form-text">Enter a unique code for this item</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="item_type" class="form-label fw-bold">Item Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="item_type" name="item_type" required>
                                <option value="">Select Type</option>
                                <option value="table">Table</option>
                                <option value="chair">Chair</option>
                               
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label fw-bold">Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="2" placeholder="Enter item description"></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label fw-bold">Location</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   placeholder="Enter location (e.g., Class A, Office, etc.)">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="signed_at" class="form-label fw-bold">Signed At Date</label>
                            <input type="date" class="form-control" id="signed_at" name="signed_at">
                            <div class="form-text">Date when item was added to inventory</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="notes" class="form-label fw-bold">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" 
                                      rows="3" placeholder="Enter any additional notes about this item"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_item" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" id="edit_item_id" name="item_id">
                <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                    <h5 class="modal-title" id="editItemModalLabel">Edit Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_item_code" class="form-label fw-bold">Item Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_item_code" name="item_code" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_item_type" class="form-label fw-bold">Item Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_item_type" name="item_type" required>
                                <option value="">Select Type</option>
                                <option value="table">Table</option>
                                <option value="chair">Chair</option>
                                
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="edit_description" class="form-label fw-bold">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_location" class="form-label fw-bold">Location</label>
                            <input type="text" class="form-control" id="edit_location" name="location">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_status" class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="available">Available</option>
                                <option value="assigned">Assigned</option>
                                <option value="damaged">Damaged</option>
                                <option value="under_maintenance">Under Maintenance</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_signed_at" class="form-label fw-bold">Signed At Date</label>
                            <input type="date" class="form-control" id="edit_signed_at" name="signed_at">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_last_maintenance" class="form-label fw-bold">Last Maintenance</label>
                            <input type="date" class="form-control" id="edit_last_maintenance" name="last_maintenance">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="edit_notes" class="form-label fw-bold">Notes</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_item" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" id="delete_item_id" name="item_id">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                    <h5 class="mb-3">Are you sure you want to delete this item?</h5>
                    <p class="mb-2"><strong id="deleteItemCode"></strong></p>
                    <p class="text-danger"><small>This action cannot be undone. The item will only be deleted if it has no assignment history.</small></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_item" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Force Return Modal -->
<div class="modal fade" id="forceReturnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" id="force_return_item_id" name="item_id">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Force Return Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-undo fa-3x text-warning mb-3"></i>
                    <h5 class="mb-3">Force Return Item</h5>
                    <p class="mb-2">Item: <strong id="forceReturnItemCode"></strong></p>
                    <p class="text-danger"><small>This will force return the item from any active assignments and mark it as available.</small></p>
                    <p class="text-danger"><small>Use this when a student/staff has been deleted but items still show as assigned.</small></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="force_return_item" class="btn btn-warning">
                        <i class="fas fa-undo me-2"></i>Force Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const typeFilter = document.getElementById('typeFilter');
    const statusFilter = document.getElementById('statusFilter');
    const resetFilters = document.getElementById('resetFilters');
    
    function filterTable() {
        const searchValue = searchInput.value.toLowerCase();
        const typeValue = typeFilter.value;
        const statusValue = statusFilter.value;
        
        const rows = document.querySelectorAll('#inventoryTable tbody tr');
        
        rows.forEach(row => {
            if (row.classList.contains('empty-state-row')) return;
            
            const text = row.textContent.toLowerCase();
            const type = row.cells[1]?.textContent.trim().toLowerCase() || '';
            const status = row.cells[4]?.textContent.trim().toLowerCase() || '';
            
            const matchesSearch = text.includes(searchValue);
            const matchesType = !typeValue || type.includes(typeValue);
            const matchesStatus = !statusValue || status.includes(statusValue);
            
            row.style.display = (matchesSearch && matchesType && matchesStatus) ? '' : 'none';
        });
    }
    
    searchInput.addEventListener('keyup', filterTable);
    typeFilter.addEventListener('change', filterTable);
    statusFilter.addEventListener('change', filterTable);
    
    resetFilters.addEventListener('click', function() {
        searchInput.value = '';
        typeFilter.value = '';
        statusFilter.value = '';
        filterTable();
    });
    
    // Edit item modal
    document.querySelectorAll('.edit-item').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const itemCode = this.getAttribute('data-item-code');
            const itemType = this.getAttribute('data-item-type');
            const description = this.getAttribute('data-description');
            const location = this.getAttribute('data-location');
            const status = this.getAttribute('data-status');
            const signedAt = this.getAttribute('data-signed-at');
            const lastMaintenance = this.getAttribute('data-last-maintenance');
            const notes = this.getAttribute('data-notes');
            
            document.getElementById('edit_item_id').value = id;
            document.getElementById('edit_item_code').value = itemCode;
            document.getElementById('edit_item_type').value = itemType;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_signed_at').value = signedAt;
            document.getElementById('edit_last_maintenance').value = lastMaintenance;
            document.getElementById('edit_notes').value = notes;
        });
    });
    
    // Delete item modal
    document.querySelectorAll('.delete-item').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const itemCode = this.getAttribute('data-item-code');
            
            document.getElementById('delete_item_id').value = id;
            document.getElementById('deleteItemCode').textContent = itemCode;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
            deleteModal.show();
        });
    });
    
    // Force return item modal
    document.querySelectorAll('.force-return-item').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const itemCode = this.getAttribute('data-item-code');
            
            document.getElementById('force_return_item_id').value = id;
            document.getElementById('forceReturnItemCode').textContent = itemCode;
            
            const forceReturnModal = new bootstrap.Modal(document.getElementById('forceReturnModal'));
            forceReturnModal.show();
        });
    });
    
    // Fix leaver item (auto-return)
    document.querySelectorAll('.fix-leaver-item').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const itemCode = this.getAttribute('data-item-code');
            
            if (confirm(`Auto-return item ${itemCode} from leaver/graduate? This will mark the item as available.`)) {
                // Send AJAX request to fix leaver item
                fetch(`fix_leaver_item.php?item_id=${id}&admin_id=<?php echo $_SESSION['admin_id']; ?>`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error fixing leaver item: ' + error);
                    });
            }
        });
    });
    
    // Auto-generate item code suggestion based on type
    const itemTypeSelect = document.getElementById('item_type');
    const itemCodeInput = document.getElementById('item_code');
    
    if (itemTypeSelect && itemCodeInput) {
        itemTypeSelect.addEventListener('change', function() {
            if (this.value && !itemCodeInput.value) {
                const prefix = this.value === 'table' ? 'TBL-' : this.value === 'chair' ? 'CHR-' : 'ITM-';
                itemCodeInput.placeholder = prefix + "001";
            }
        });
    }
    
    // Set today's date as default for signed at date
    const today = new Date().toISOString().split('T')[0];
    const signedAtInput = document.getElementById('signed_at');
    if (signedAtInput) {
        signedAtInput.value = today;
    }
});
</script>

<style>
    .card-header{
         background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
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
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.stats-card.simple-card p {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
    font-weight: 500;
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

.table-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
}

.table-danger {
    background-color: rgba(220, 53, 69, 0.1) !important;
}

/* Responsive adjustments */
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
}
</style>

<?php include '../controller/footer.php'; ?>
