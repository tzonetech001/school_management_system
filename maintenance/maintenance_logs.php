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
// Handle item return when student/staff is deleted
function handleUserDeletionLogs($conn, $admin_id) {
    // Check for deleted students with active assignments
    $deleted_students_sql = "SELECT s.id, s.first_name, s.last_name, s.index_number 
                            FROM students s
                            WHERE s.status = 0 
                            AND EXISTS (
                                SELECT 1 FROM maintenance_assignments ma 
                                WHERE ma.student_id = s.id AND ma.status = 'active'
                            )";
    $deleted_students_result = mysqli_query($conn, $deleted_students_sql);
    
    while ($student = mysqli_fetch_assoc($deleted_students_result)) {
        // Auto-return items for deleted student
        $assignments_sql = "SELECT ma.*, mi.item_code 
                           FROM maintenance_assignments ma
                           JOIN maintenance_items mi ON ma.item_id = mi.id
                           WHERE ma.student_id = {$student['id']} AND ma.status = 'active'";
        $assignments_result = mysqli_query($conn, $assignments_sql);
        
        while ($assignment = mysqli_fetch_assoc($assignments_result)) {
            // Update assignment
            $update_sql = "UPDATE maintenance_assignments SET 
                          status = 'returned',
                          return_date = CURDATE(),
                          return_condition = 'good',
                          return_notes = 'Auto-returned: Student deleted'
                          WHERE id = {$assignment['id']}";
            mysqli_query($conn, $update_sql);
            
            // Update item
            $update_item_sql = "UPDATE maintenance_items SET status = 'available' WHERE id = {$assignment['item_id']}";
            mysqli_query($conn, $update_item_sql);
            
            // Log the action
            $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                       VALUES ({$assignment['item_id']}, 'return', 'student', {$student['id']}, $admin_id, 
                       'Auto-returned {$assignment['item_code']} from deleted student: {$student['first_name']} {$student['last_name']} ({$student['index_number']})')";
            mysqli_query($conn, $log_sql);
        }
    }
    
    // Check for deleted staff with active assignments
    $deleted_staff_sql = "SELECT a.id, a.first_name, a.last_name, a.email 
                         FROM admins a
                         WHERE a.status = 0 
                         AND EXISTS (
                             SELECT 1 FROM maintenance_staff_assignments msa 
                             WHERE msa.staff_id = a.id AND msa.status = 'active'
                         )";
    $deleted_staff_result = mysqli_query($conn, $deleted_staff_sql);
    
    while ($staff = mysqli_fetch_assoc($deleted_staff_result)) {
        // Auto-return items for deleted staff
        $assignments_sql = "SELECT msa.*, mi.item_code 
                           FROM maintenance_staff_assignments msa
                           JOIN maintenance_items mi ON msa.item_id = mi.id
                           WHERE msa.staff_id = {$staff['id']} AND msa.status = 'active'";
        $assignments_result = mysqli_query($conn, $assignments_sql);
        
        while ($assignment = mysqli_fetch_assoc($assignments_result)) {
            // Update assignment
            $update_sql = "UPDATE maintenance_staff_assignments SET 
                          status = 'returned',
                          return_date = CURDATE(),
                          return_condition = 'good',
                          return_notes = 'Auto-returned: Staff deleted'
                          WHERE id = {$assignment['id']}";
            mysqli_query($conn, $update_sql);
            
            // Update item
            $update_item_sql = "UPDATE maintenance_items SET status = 'available' WHERE id = {$assignment['item_id']}";
            mysqli_query($conn, $update_item_sql);
            
            // Log the action
            $log_sql = "INSERT INTO maintenance_logs (item_id, log_type, user_type, user_id, admin_id, description) 
                       VALUES ({$assignment['item_id']}, 'return', 'staff', {$staff['id']}, $admin_id, 
                       'Auto-returned {$assignment['item_code']} from deleted staff: {$staff['first_name']} {$staff['last_name']} ({$staff['email']})')";
            mysqli_query($conn, $log_sql);
        }
    }
}

// Run auto-return on page load
handleUserDeletionLogs($conn, $_SESSION['admin_id']);

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$log_type = isset($_GET['log_type']) ? $_GET['log_type'] : '';
$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : '';
$item_code = isset($_GET['item_code']) ? $_GET['item_code'] : '';

// Build query with filters
$logs_sql = "SELECT ml.*, mi.item_code, mi.item_type,
            CONCAT(a.first_name, ' ', a.last_name) as admin_name,
            
            -- Get user details if available
            CASE 
                WHEN ml.user_type = 'student' THEN 
                    (SELECT CONCAT(s.first_name, ' ', s.last_name, ' (', s.index_number, ')') 
                     FROM students s WHERE s.id = ml.user_id)
                WHEN ml.user_type = 'staff' THEN 
                    (SELECT CONCAT(a2.first_name, ' ', a2.last_name, ' (', a2.email, ')') 
                     FROM admins a2 WHERE a2.id = ml.user_id)
                ELSE NULL
            END as user_details
            
            FROM maintenance_logs ml
            JOIN maintenance_items mi ON ml.item_id = mi.id
            JOIN admins a ON ml.admin_id = a.id
            WHERE 1=1";

if (!empty($date_from)) {
    $date_from = mysqli_real_escape_string($conn, $date_from);
    $logs_sql .= " AND DATE(ml.created_at) >= '$date_from'";
}

if (!empty($date_to)) {
    $date_to = mysqli_real_escape_string($conn, $date_to);
    $logs_sql .= " AND DATE(ml.created_at) <= '$date_to'";
}

if (!empty($log_type)) {
    $log_type = mysqli_real_escape_string($conn, $log_type);
    $logs_sql .= " AND ml.log_type = '$log_type'";
}

if (!empty($user_type)) {
    $user_type = mysqli_real_escape_string($conn, $user_type);
    $logs_sql .= " AND ml.user_type = '$user_type'";
}

if (!empty($item_code)) {
    $item_code = mysqli_real_escape_string($conn, $item_code);
    $logs_sql .= " AND mi.item_code LIKE '%$item_code%'";
}

$logs_sql .= " ORDER BY ml.created_at DESC";
$logs_result = mysqli_query($conn, $logs_sql);

// Get statistics for filters
$types_sql = "SELECT DISTINCT log_type FROM maintenance_logs ORDER BY log_type";
$types_result = mysqli_query($conn, $types_sql);

$user_types_sql = "SELECT DISTINCT user_type FROM maintenance_logs WHERE user_type IS NOT NULL ORDER BY user_type";
$user_types_result = mysqli_query($conn, $user_types_sql);

// Get unique item codes for filter
$item_codes_sql = "SELECT DISTINCT mi.item_code FROM maintenance_logs ml 
                   JOIN maintenance_items mi ON ml.item_id = mi.id 
                   ORDER BY mi.item_code";
$item_codes_result = mysqli_query($conn, $item_codes_sql);
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Maintenance Logs</h2>
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
                         <li><a class="dropdown-item" href="staff_main.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Assign Staff
                        </a></li>
                         <li><hr class="dropdown-divider"></li>
                         <li><a class="dropdown-item" href="student_main.php">
                            <i class="fas fa-user-graduate me-2"></i>Assign Student
                        </a></li>
                         <li><a class="dropdown-item" href="inventory.php">
                            <i class="fas fa-list-alt me-2"></i>View Inventory
                        </a></li>
                        <li><a class="dropdown-item" href="report_maintenance.php"><i class="fas fa-download me-2"></i>Export List</a></li>
                       
                    </ul>
                </div>
        </div>

        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Logs</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="log_type" class="form-label">Log Type</label>
                        <select class="form-select" id="log_type" name="log_type">
                            <option value="">All Types</option>
                            <?php while ($type = mysqli_fetch_assoc($types_result)): ?>
                            <option value="<?php echo $type['log_type']; ?>" <?php echo ($log_type == $type['log_type']) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($type['log_type']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="user_type" class="form-label">User Type</label>
                        <select class="form-select" id="user_type" name="user_type">
                            <option value="">All Users</option>
                            <?php while ($u_type = mysqli_fetch_assoc($user_types_result)): ?>
                            <option value="<?php echo $u_type['user_type']; ?>" <?php echo ($user_type == $u_type['user_type']) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($u_type['user_type']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="item_code" class="form-label">Item Code</label>
                        <select class="form-select" id="item_code" name="item_code">
                            <option value="">All Items</option>
                            <?php while ($item = mysqli_fetch_assoc($item_codes_result)): ?>
                            <option value="<?php echo $item['item_code']; ?>" <?php echo ($item_code == $item['item_code']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['item_code']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Apply Filters
                            </button>
                            <a href="maintenance_logs.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-2"></i>Reset Filters
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="card">
            <div class="card-header" >
                <h4 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Maintenance Activity Logs
                    <span class="badge bg-light text-dark ms-2"><?php echo mysqli_num_rows($logs_result); ?> Logs</span>
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="logsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Item</th>
                                <th>Activity Type</th>
                                <th>Description</th>
                                <th>User</th>
                                <th>Performed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($logs_result) == 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                            <h5>No Activity Logs Found</h5>
                                            <p class="text-muted">No maintenance activities have been logged yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($log = mysqli_fetch_assoc($logs_result)): ?>
                                <tr>
                                    <td>
                                        <div class="small">
                                            <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                            <div class="text-muted">
                                                <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong class="text-primary"><?php echo htmlspecialchars($log['item_code']); ?></strong>
                                            <div class="small text-muted">
                                                <?php echo ucfirst($log['item_type']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $type_badge = '';
                                        $type_icon = '';
                                        switch ($log['log_type']) {
                                            case 'assignment':
                                                $type_badge = 'bg-success';
                                                $type_icon = 'fa-user-plus';
                                                break;
                                            case 'return':
                                                $type_badge = 'bg-warning text-dark';
                                                $type_icon = 'fa-undo';
                                                break;
                                            case 'damage':
                                                $type_badge = 'bg-danger';
                                                $type_icon = 'fa-exclamation-triangle';
                                                break;
                                            case 'repair':
                                                $type_badge = 'bg-info';
                                                $type_icon = 'fa-tools';
                                                break;
                                            case 'maintenance':
                                                $type_badge = 'bg-secondary';
                                                $type_icon = 'fa-wrench';
                                                break;
                                            default:
                                                $type_badge = 'bg-primary';
                                                $type_icon = 'fa-info-circle';
                                        }
                                        ?>
                                        <span class="badge <?php echo $type_badge; ?>">
                                            <i class="fas <?php echo $type_icon; ?> me-1"></i>
                                            <?php echo ucfirst($log['log_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($log['description']); ?>
                                        <?php if (strpos($log['description'], 'Auto-returned') !== false || 
                                                 strpos($log['description'], 'Auto-fixed') !== false || 
                                                 strpos($log['description'], 'Force returned') !== false): ?>
                                            <span class="badge bg-secondary ms-1">Auto</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['user_type']): ?>
                                            <div>
                                                <span class="badge <?php echo $log['user_type'] == 'student' ? 'bg-success' : 'bg-info'; ?>">
                                                    <?php echo ucfirst($log['user_type']); ?>
                                                </span>
                                                <?php if ($log['user_details']): ?>
                                                    <div class="small text-muted mt-1">
                                                        <?php echo htmlspecialchars($log['user_details']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($log['admin_name']); ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Export Options -->
                <div class="mt-4">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Showing all maintenance activity logs. System automatically returns items when students/staff are deleted.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for search functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default dates for filter (last 30 days)
    const dateToInput = document.getElementById('date_to');
    const dateFromInput = document.getElementById('date_from');
    
    if (!dateToInput.value) {
        const today = new Date().toISOString().split('T')[0];
        dateToInput.value = today;
    }
    
    if (!dateFromInput.value) {
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
        dateFromInput.value = thirtyDaysAgo.toISOString().split('T')[0];
    }
    
    // Add search functionality for logs table
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'form-control';
    searchInput.placeholder = 'Search in logs...';
    searchInput.id = 'logsSearch';
    
    const cardHeader = document.querySelector('.card-header');
    if (cardHeader) {
        const headerContent = cardHeader.innerHTML;
        cardHeader.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Maintenance Activity Logs
                        <span class="badge bg-light text-dark ms-2" id="logsCount">${<?php echo mysqli_num_rows($logs_result); ?>} Logs</span>
                    </h4>
                </div>
                <div style="width: 300px;">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="logsSearch" placeholder="Search in logs...">
                    </div>
                </div>
            </div>
        `;
    }
    
    // Search functionality
    const searchLogs = document.getElementById('logsSearch');
    if (searchLogs) {
        searchLogs.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#logsTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                if (row.classList.contains('empty-state-row')) return;
                
                const text = row.textContent.toLowerCase();
                if (text.includes(searchValue)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update count badge
            const countBadge = document.getElementById('logsCount');
            if (countBadge) {
                countBadge.textContent = visibleCount + ' Logs';
            }
        });
    }
});
</script>

<style>
    .card-header{
         background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
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

.table td {
    vertical-align: middle;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-header .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .card-header .input-group {
        width: 100% !important;
        margin-top: 10px;
    }
}
</style>

<?php include '../controller/footer.php'; ?>