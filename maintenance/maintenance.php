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

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

// Get detailed maintenance statistics
$stats_sql = "SELECT 
    -- Total Items and Available Items
    (SELECT COUNT(*) FROM maintenance_items) as total_items,
    (SELECT COUNT(*) FROM maintenance_items WHERE status = 'available') as available_items,
    
    -- Tables Statistics
    (SELECT COUNT(*) FROM maintenance_items WHERE item_type = 'table') as total_tables,
    (SELECT COUNT(*) FROM maintenance_items WHERE item_type = 'table' AND status = 'available') as available_tables,
    
    -- Chairs Statistics
    (SELECT COUNT(*) FROM maintenance_items WHERE item_type = 'chair') as total_chairs,
    (SELECT COUNT(*) FROM maintenance_items WHERE item_type = 'chair' AND status = 'available') as available_chairs,
    
    -- Student Assignments
    (SELECT COUNT(DISTINCT student_id) FROM maintenance_assignments WHERE status = 'active') as assigned_students_count,
    (SELECT COUNT(DISTINCT s.id) FROM students s WHERE s.status = 1) as total_active_students,
    
    -- Staff Assignments
    (SELECT COUNT(DISTINCT staff_id) FROM maintenance_staff_assignments WHERE status = 'active') as assigned_staff_count,
    (SELECT COUNT(DISTINCT a.id) FROM admins a WHERE a.status = 1 AND a.id != 1) as total_active_staff,
    
    -- Overall Assigned Items
    (SELECT COUNT(*) FROM maintenance_items WHERE status = 'assigned') as assigned_items,
    
    -- Today's logs
    (SELECT COUNT(*) FROM maintenance_logs WHERE DATE(created_at) = CURDATE()) as today_logs,
    
    -- Items by status
    (SELECT COUNT(*) FROM maintenance_items WHERE status = 'damaged') as damaged_items,
    (SELECT COUNT(*) FROM maintenance_items WHERE status = 'under_maintenance') as under_maintenance_items,
    (SELECT COUNT(*) FROM maintenance_items WHERE status = 'lost') as lost_items";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Calculate percentages
$available_percentage = $stats['total_items'] > 0 ? round(($stats['available_items'] / $stats['total_items']) * 100) : 0;
$assigned_percentage = $stats['total_items'] > 0 ? round(($stats['assigned_items'] / $stats['total_items']) * 100) : 0;
$available_tables_percentage = $stats['total_tables'] > 0 ? round(($stats['available_tables'] / $stats['total_tables']) * 100) : 0;
$available_chairs_percentage = $stats['total_chairs'] > 0 ? round(($stats['available_chairs'] / $stats['total_chairs']) * 100) : 0;

// Get recent assignments (combined student and staff)
$recent_assignments_sql = "(SELECT ma.id, ma.assigned_date, mi.item_code, mi.item_type, 
           CONCAT(s.first_name, ' ', s.last_name) as user_name,
           'student' as user_type, s.index_number as identifier, s.is_leaver,
           CONCAT(s.first_name, ' ', s.last_name, ' (', s.index_number, ')') as full_info
    FROM maintenance_assignments ma
    JOIN maintenance_items mi ON ma.item_id = mi.id
    JOIN students s ON ma.student_id = s.id
    WHERE ma.status = 'active'
    ORDER BY ma.assigned_date DESC
    LIMIT 5
) UNION ALL (
    SELECT msa.id, msa.assigned_date, mi.item_code, mi.item_type,
           CONCAT(a.first_name, ' ', a.last_name) as user_name,
           'staff' as user_type, a.email as identifier, 0 as is_leaver,
           CONCAT(a.first_name, ' ', a.last_name, ' (', a.email, ')') as full_info
    FROM maintenance_staff_assignments msa
    JOIN maintenance_items mi ON msa.item_id = mi.id
    JOIN admins a ON msa.staff_id = a.id
    WHERE msa.status = 'active'
    ORDER BY msa.assigned_date DESC
    LIMIT 5
) ORDER BY assigned_date DESC LIMIT 10";

$recent_assignments_result = mysqli_query($conn, $recent_assignments_sql);

// Get recent maintenance logs
$recent_logs_sql = "SELECT ml.*, mi.item_code, 
                   CONCAT(a.first_name, ' ', a.last_name) as admin_name
                   FROM maintenance_logs ml
                   JOIN maintenance_items mi ON ml.item_id = mi.id
                   JOIN admins a ON ml.admin_id = a.id
                   ORDER BY ml.created_at DESC
                   LIMIT 10";
$recent_logs_result = mysqli_query($conn, $recent_logs_sql);
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title with Action Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="page-title">Maintenance Dashboard</h2>
            
            </div>
            <div>
                <!-- Action Button with Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" id="actionDropdown" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog me-2"></i>Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionDropdown">
                        <li><a class="dropdown-item" href="student_main.php">
                            <i class="fas fa-user-graduate me-2"></i>Assign Student
                        </a></li>
                        <li><a class="dropdown-item" href="staff_main.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Assign Staff
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
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
        </div>

        <!-- Statistics Cards - First Row -->
        <div class="row mb-4">
            <!-- Total Items / Available Items -->
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-cube" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $stats['total_items'] ?? 0; ?> / <?php echo $stats['available_items'] ?? 0; ?></h3>
                    <p>Total Items / Available Items</p>
                    <div class="progress mt-2" style="height: 8px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $available_percentage; ?>%;" 
                             aria-valuenow="<?php echo $available_percentage; ?>" 
                             aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">Available: <?php echo $available_percentage; ?>%</small>
                        <small class="text-muted">Assigned: <?php echo $assigned_percentage; ?>%</small>
                    </div>
                </div>
            </div>
            
            <!-- Total Tables / Available Tables -->
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-table" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $stats['total_tables'] ?? 0; ?> / <?php echo $stats['available_tables'] ?? 0; ?></h3>
                    <p>Total Tables / Available Tables</p>
                    <div class="progress mt-2" style="height: 8px;">
                        <div class="progress-bar bg-info" role="progressbar" 
                             style="width: <?php echo $available_tables_percentage; ?>%;" 
                             aria-valuenow="<?php echo $available_tables_percentage; ?>" 
                             aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">Available: <?php echo $available_tables_percentage; ?>%</small>
                        <small class="text-muted">Assigned: <?php echo $stats['total_tables'] > 0 ? round((($stats['total_tables'] - $stats['available_tables']) / $stats['total_tables']) * 100) : 0; ?>%</small>
                    </div>
                </div>
            </div>
            
            <!-- Total Chairs / Available Chairs -->
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-chair" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $stats['total_chairs'] ?? 0; ?> / <?php echo $stats['available_chairs'] ?? 0; ?></h3>
                    <p>Total Chairs / Available Chairs</p>
                    <div class="progress mt-2" style="height: 8px;">
                        <div class="progress-bar bg-warning" role="progressbar" 
                             style="width: <?php echo $available_chairs_percentage; ?>%;" 
                             aria-valuenow="<?php echo $available_chairs_percentage; ?>" 
                             aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">Available: <?php echo $available_chairs_percentage; ?>%</small>
                        <small class="text-muted">Assigned: <?php echo $stats['total_chairs'] > 0 ? round((($stats['total_chairs'] - $stats['available_chairs']) / $stats['total_chairs']) * 100) : 0; ?>%</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards - Second Row -->
        <div class="row mb-4">
            <!-- Total Students / Assigned Students -->
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-graduate" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $stats['total_active_students'] ?? 0; ?> / <?php echo $stats['assigned_students_count'] ?? 0; ?></h3>
                    <p>Total Students / Assigned Students</p>
                    <div class="mt-2">
                        <?php 
                        $student_assignment_percentage = $stats['total_active_students'] > 0 ? round(($stats['assigned_students_count'] / $stats['total_active_students']) * 100) : 0;
                        ?>
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $student_assignment_percentage; ?>%;" 
                                         aria-valuenow="<?php echo $student_assignment_percentage; ?>" 
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <small class="ms-2 text-muted"><?php echo $student_assignment_percentage; ?>%</small>
                        </div>
                        <small class="text-muted d-block mt-1">
                            <?php echo ($stats['total_active_students'] - $stats['assigned_students_count']); ?> students without items
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Total Staff / Assigned Staff -->
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-chalkboard-teacher" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $stats['total_active_staff'] ?? 0; ?> / <?php echo $stats['assigned_staff_count'] ?? 0; ?></h3>
                    <p>Total Staff / Assigned Staff</p>
                    <div class="mt-2">
                        <?php 
                        $staff_assignment_percentage = $stats['total_active_staff'] > 0 ? round(($stats['assigned_staff_count'] / $stats['total_active_staff']) * 100) : 0;
                        ?>
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo $staff_assignment_percentage; ?>%;" 
                                         aria-valuenow="<?php echo $staff_assignment_percentage; ?>" 
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <small class="ms-2 text-muted"><?php echo $staff_assignment_percentage; ?>%</small>
                        </div>
                        <small class="text-muted d-block mt-1">
                            <?php echo ($stats['total_active_staff'] - $stats['assigned_staff_count']); ?> staff without items
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Today's Activities -->
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-history" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $stats['today_logs'] ?? 0; ?></h3>
                    <p>Today's Activities</p>
                    <div class="mt-2">
                        <span class="badge bg-primary">Logs: <?php echo $stats['today_logs'] ?? 0; ?></span>
                        <span class="badge bg-success ms-1">New: <?php echo $stats['today_logs'] ?? 0; ?></span>
                    </div>
                    <small class="text-muted d-block mt-1">Last updated: <?php echo date('H:i:s'); ?></small>
                </div>
            </div>
        </div>

        <!-- Status Summary Cards -->
        <div class="row mb-4">
            <!-- Items by Status -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Items by Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="status-item d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                                    <span><i class="fas fa-circle text-success me-2"></i>Available</span>
                                    <span class="badge bg-success fs-6"><?php echo $stats['available_items'] ?? 0; ?></span>
                                </div>
                                <div class="status-item d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                                    <span><i class="fas fa-circle text-warning me-2"></i>Assigned</span>
                                    <span class="badge bg-warning fs-6"><?php echo $stats['assigned_items'] ?? 0; ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="status-item d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                                    <span><i class="fas fa-circle text-danger me-2"></i>Damaged</span>
                                    <span class="badge bg-danger fs-6"><?php echo $stats['damaged_items'] ?? 0; ?></span>
                                </div>
                                <div class="status-item d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                                    <span><i class="fas fa-circle text-secondary me-2"></i>Under Maintenance</span>
                                    <span class="badge bg-secondary fs-6"><?php echo $stats['under_maintenance_items'] ?? 0; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="inventory.php?add=new" class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center">
                                    <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                    <span>Add New Item</span>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="student_main.php" class="btn btn-outline-success w-100 py-3 d-flex flex-column align-items-center">
                                    <i class="fas fa-user-graduate fa-2x mb-2"></i>
                                    <span>Assign to Student</span>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="staff_main.php" class="btn btn-outline-info w-100 py-3 d-flex flex-column align-items-center">
                                    <i class="fas fa-chalkboard-teacher fa-2x mb-2"></i>
                                    <span>Assign to Staff</span>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="maintenance_logs.php" class="btn btn-outline-warning w-100 py-3 d-flex flex-column align-items-center">
                                    <i class="fas fa-history fa-2x mb-2"></i>
                                    <span>View Logs</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <!-- Recent Assignments -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list-check me-2"></i>
                                Recent Assignments
                            </h5>
                            <span class="badge bg-light text-dark">Last 10</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Item</th>
                                        <th>Assigned To</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($recent_assignments_result) == 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">No recent assignments</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($assignment = mysqli_fetch_assoc($recent_assignments_result)): ?>
                                        <tr>
                                            <td>
                                                <small><?php echo date('M d', strtotime($assignment['assigned_date'])); ?></small>
                                                <div class="text-muted">
                                                    <small><?php echo date('H:i', strtotime($assignment['assigned_date'])); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $assignment['item_type'] == 'table' ? 'bg-primary' : 'bg-info'; ?>">
                                                    <?php echo htmlspecialchars($assignment['item_code']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <strong><?php echo htmlspecialchars($assignment['user_name']); ?></strong>
                                                    <div class="text-muted">
                                                        <?php echo htmlspecialchars($assignment['identifier']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $assignment['user_type'] == 'student' ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo ucfirst($assignment['user_type']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <div class="btn-group" role="group">
                                <a href="student_main.php" class="btn btn-sm btn-outline-primary">View Students</a>
                                <a href="staff_main.php" class="btn btn-sm btn-outline-info">View Staff</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Logs -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Recent Activity Logs
                            </h5>
                            <span class="badge bg-light text-dark">Last 10</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="activity-timeline">
                            <?php if (mysqli_num_rows($recent_logs_result) == 0): ?>
                                <div class="text-center text-muted py-3">No recent activity</div>
                            <?php else: ?>
                                <?php while ($log = mysqli_fetch_assoc($recent_logs_result)): ?>
                                <div class="activity-item mb-3">
                                    <div class="d-flex">
                                        <div class="activity-icon me-3">
                                            <?php
                                            $log_icon = '';
                                            $log_color = '';
                                            switch ($log['log_type']) {
                                                case 'assignment':
                                                    $log_icon = 'fa-user-plus';
                                                    $log_color = 'text-success';
                                                    break;
                                                case 'return':
                                                    $log_icon = 'fa-undo';
                                                    $log_color = 'text-warning';
                                                    break;
                                                case 'damage':
                                                    $log_icon = 'fa-exclamation-triangle';
                                                    $log_color = 'text-danger';
                                                    break;
                                                case 'repair':
                                                    $log_icon = 'fa-tools';
                                                    $log_color = 'text-info';
                                                    break;
                                                case 'maintenance':
                                                    $log_icon = 'fa-wrench';
                                                    $log_color = 'text-secondary';
                                                    break;
                                                default:
                                                    $log_icon = 'fa-info-circle';
                                                    $log_color = 'text-primary';
                                            }
                                            ?>
                                            <i class="fas <?php echo $log_icon; ?> fa-lg <?php echo $log_color; ?>"></i>
                                        </div>
                                        <div class="activity-content flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <strong class="text-primary"><?php echo htmlspecialchars($log['item_code']); ?></strong>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($log['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($log['description']); ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($log['admin_name']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="maintenance_logs.php" class="btn btn-sm btn-outline-primary">View All Logs</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

/* Progress bar styling */
.progress {
    border-radius: 10px;
    background-color: #f0f0f0;
}

.progress-bar {
    border-radius: 10px;
}

/* Activity timeline styling */
.activity-timeline {
    max-height: 300px;
    overflow-y: auto;
    padding-right: 10px;
}

.activity-item {
    padding: 10px;
    border-left: 3px solid #3B9DB3;
    background-color: rgba(59, 157, 179, 0.05);
    border-radius: 0 8px 8px 0;
    transition: all 0.3s ease;
}

.activity-item:hover {
    background-color: rgba(59, 157, 179, 0.1);
    transform: translateX(5px);
}

.activity-icon {
    flex-shrink: 0;
}

/* Status items styling */
.status-item {
    transition: all 0.3s ease;
}

.status-item:hover {
    background-color: #e9ecef !important;
    transform: translateY(-2px);
}

/* Quick actions buttons */
.btn-outline-primary, .btn-outline-success, .btn-outline-info, .btn-outline-warning {
    transition: all 0.3s ease;
}

.btn-outline-primary:hover, .btn-outline-success:hover, .btn-outline-info:hover, .btn-outline-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

/* Badge styling */
.badge {
    font-weight: 500;
    padding: 0.5em 0.8em;
}

/* Custom scrollbar for activity timeline */
.activity-timeline::-webkit-scrollbar {
    width: 6px;
}

.activity-timeline::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.activity-timeline::-webkit-scrollbar-thumb {
    background: #3B9DB3;
    border-radius: 3px;
}

.activity-timeline::-webkit-scrollbar-thumb:hover {
    background: #2d8b9e;
}

/* Action button dropdown */
#actionDropdown {
    padding: 10px 20px;
    border-radius: 8px;
}

.dropdown-menu {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 8px;
}

.dropdown-item {
    padding: 10px 15px;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background-color: rgba(59, 157, 179, 0.1);
    color: #3B9DB3;
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
    
    .activity-timeline {
        max-height: 250px;
    }
    
    /* Make cards full width on mobile */
    .col-md-4, .col-md-6 {
        width: 100%;
    }
    
    /* Adjust action button */
    #actionDropdown {
        width: 100%;
        margin-bottom: 10px;
    }
    
    /* Stack buttons in button groups */
    .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-group .btn {
        margin-bottom: 5px;
        width: 100%;
    }
}

@media (max-width: 576px) {
    .stats-card.simple-card h3 {
        font-size: 1.3rem;
    }
    
    .stats-card.simple-card .stats-icon i {
        font-size: 1.8rem;
    }
    
    .activity-item {
        padding: 8px;
    }
    
    .dropdown-menu {
        width: 100%;
        margin-top: 5px;
    }
}

/* Animation for page load */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.stats-card.simple-card {
    animation: fadeIn 0.5s ease-out;
}

.card {
    animation: fadeIn 0.6s ease-out;
}

/* Tooltip styling */
[data-bs-toggle="tooltip"] {
    cursor: help;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
    
    // Auto-refresh dashboard every 60 seconds
    setInterval(function() {
        // Refresh only the statistics cards
        fetch(window.location.href)
            .then(response => response.text())
            .then(data => {
                // You could implement partial page refresh here
                // For now, we'll just reload the page after 5 minutes
            })
            .catch(error => console.error('Error refreshing dashboard:', error));
    }, 60000); // 60 seconds
    
    // Add click animations to cards
    document.querySelectorAll('.stats-card.simple-card').forEach(card => {
        card.addEventListener('click', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
            setTimeout(() => {
                this.style.transform = 'translateY(-5px) scale(1)';
            }, 150);
        });
    });
    
    // Update time display every minute
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour12: true, 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
        });
        
        // Update all time elements
        document.querySelectorAll('.current-time').forEach(element => {
            element.textContent = timeString;
        });
    }
    
    // Update time every second
    setInterval(updateTime, 1000);
    updateTime(); // Initial call
});
</script>

<?php include '../controller/footer.php'; ?>