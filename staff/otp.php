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
    if ($role_id == 1 || $role_id == 2) { // Head Master or Second Master
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
// Get current admin info
$admin_sql = "SELECT first_name, last_name FROM admins WHERE id = ?";
$admin_stmt = $conn->prepare($admin_sql);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
$admin_data = $admin_result->fetch_assoc();

// Handle OTP deletion
if (isset($_GET['delete'])) {
    $reset_id = intval($_GET['delete']);
    
    $delete_sql = "DELETE FROM password_resets WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $reset_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success'] = "Password reset request deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting request: " . $conn->error;
    }
    header("Location: otp.php");
    exit();
}

// Handle clearing expired OTPs
if (isset($_GET['clear_expired'])) {
    $current_time = date('Y-m-d H:i:s');
    
    $clear_sql = "DELETE FROM password_resets WHERE expires_at < ?";
    $clear_stmt = $conn->prepare($clear_sql);
    $clear_stmt->bind_param("s", $current_time);
    $clear_stmt->execute();
    
    $affected = $clear_stmt->affected_rows;
    $_SESSION['success'] = "$affected expired password reset request(s) cleared successfully!";
    header("Location: otp.php");
    exit();
}

// Handle marking OTP as used
if (isset($_GET['mark_used'])) {
    $reset_id = intval($_GET['mark_used']);
    
    $update_sql = "UPDATE password_resets SET used = 1 WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $reset_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "OTP marked as used successfully!";
    } else {
        $_SESSION['error'] = "Error marking OTP as used: " . $conn->error;
    }
    header("Location: otp.php");
    exit();
}

// Get all password reset requests with staff/student details
$sql = "SELECT pr.*, 
        CASE 
            WHEN pr.user_type = 'staff' THEN 
                CONCAT(a.first_name, ' ', COALESCE(a.middle_name, ''), ' ', a.last_name)
            WHEN pr.user_type = 'student' THEN 
                CONCAT(s.first_name, ' ', COALESCE(s.second_name, ''), ' ', s.last_name)
        END as user_full_name,
        CASE 
            WHEN pr.user_type = 'staff' THEN a.email
            
        END as user_email,
        CASE 
            WHEN pr.user_type = 'staff' THEN a.phone_number
            WHEN pr.user_type = 'student' THEN s.parent_phone
        END as user_phone,
        CASE 
            WHEN pr.user_type = 'staff' THEN a.check_number
            WHEN pr.user_type = 'student' THEN s.index_number
        END as user_identifier,
        CASE 
            WHEN pr.user_type = 'staff' THEN a.profile_image
            WHEN pr.user_type = 'student' THEN NULL
        END as profile_image,
        a.status as staff_status,
        s.status as student_status,
        s.class as student_class,
        s.combination as student_combination
        FROM password_resets pr
        LEFT JOIN admins a ON pr.user_type = 'staff' AND pr.user_id = a.id
        LEFT JOIN students s ON pr.user_type = 'student' AND pr.user_id = s.id
        ORDER BY pr.created_at DESC";

$result = mysqli_query($conn, $sql);
$reset_requests = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $reset_requests[] = $row;
    }
}

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN used = 1 THEN 1 ELSE 0 END) as used_requests,
                SUM(CASE WHEN used = 0 AND expires_at > NOW() THEN 1 ELSE 0 END) as active_requests,
                SUM(CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END) as expired_requests
              FROM password_resets";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get user type statistics
$type_stats_sql = "SELECT 
                    user_type,
                    COUNT(*) as count,
                    SUM(CASE WHEN used = 1 THEN 1 ELSE 0 END) as used,
                    SUM(CASE WHEN used = 0 AND expires_at > NOW() THEN 1 ELSE 0 END) as active
                  FROM password_resets
                  GROUP BY user_type";
$type_stats_result = mysqli_query($conn, $type_stats_sql);
$type_stats = [];
while ($row = mysqli_fetch_assoc($type_stats_result)) {
    $type_stats[$row['user_type']] = $row;
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<!-- SweetAlert2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

<!-- Animate.css -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
:root {
    --expiry-warning: #ff9800;
    --expiry-critical: #f44336;
}

.main-content {
    padding: 20px;
    min-height: calc(100vh - 60px);
    background-color: #f8f9fa;
}

/* Stats Cards */
.stats-card {
    border: none;
    border-radius: 15px;
    padding: 20px;
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--primary-color);
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
}

.stats-card h3 {
    font-size: 28px;
    font-weight: bold;
    margin: 10px 0 5px;
    color: #333;
}

.stats-card p {
    color: #666;
    margin: 0;
    font-size: 14px;
}

/* User Type Badges */
.user-type-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.user-type-staff {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.user-type-student {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
    color: white;
}

/* User Info */
.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background-color: var(--primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid var(--primary-color);
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-avatar i {
    font-size: 22px;
    color: var(--primary-color);
}

.user-details {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-weight: 600;
    color: #333;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-identifier {
    font-size: 11px;
    color: #666;
    display: block;
}

/* OTP Badge */
.otp-badge {
    font-family: 'Courier New', monospace;
    font-size: 18px;
    font-weight: bold;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    padding: 8px 12px;
    border-radius: 8px;
    display: inline-block;
    letter-spacing: 2px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Status Badges */
.badge {
    padding: 6px 12px;
    font-weight: 500;
    border-radius: 20px;
    font-size: 12px;
}

.badge-active {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.badge-used {
    background: linear-gradient(135deg, #6c757d, #5a6268);
    color: white;
}

.badge-expired {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
}

/* Expiry Time Display */
.expiry-time {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 20px;
    display: inline-block;
    text-align: center;
}

.expiry-time.active {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.expiry-time.warning {
    background: linear-gradient(135deg, #ff9800, #f57c00);
    color: white;
    animation: pulse 1s infinite;
}

.expiry-time.critical {
    background: linear-gradient(135deg, #f44336, #d32f2f);
    color: white;
    animation: pulse 0.5s infinite;
}

.expiry-time.used {
    background: linear-gradient(135deg, #6c757d, #5a6268);
    color: white;
}

.expiry-time.expired {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.9; transform: scale(1.05); }
}

/* Action Buttons */
.action-btn {
    width: 35px;
    height: 35px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    margin: 0 3px;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.action-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.action-btn.view {
    background: linear-gradient(135deg, #17a2b8, #138496);
    color: white;
}

.action-btn.success {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.action-btn.danger {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
}

/* Filter Section */
.filter-section {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.table {
    margin-bottom: 0;
}

.table th {
    background: linear-gradient(135deg, var(--primary-light), #ffffff);
    color: var(--primary-dark);
    font-weight: 600;
    border-bottom: 2px solid var(--primary-color);
    padding: 15px 10px;
    white-space: nowrap;
}

.table td {
    padding: 12px 10px;
    vertical-align: middle;
}

.table tbody tr:hover {
    background-color: rgba(59, 157, 179, 0.05);
}

/* Modal Styles */
.modal-content {
    border: none;
    border-radius: 15px;
    overflow: hidden;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    border: none;
    padding: 20px;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}

.modal-body {
    padding: 25px;
}

.otp-display {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 10px;
    padding: 30px;
    text-align: center;
    margin-bottom: 20px;
}

.otp-display .otp-code {
    font-size: 48px;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    color: white;
    letter-spacing: 10px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}

.info-row {
    display: flex;
    margin-bottom: 15px;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.info-label {
    width: 120px;
    font-weight: 600;
    color: #666;
}

.info-value {
    flex: 1;
    color: #333;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-card {
        margin-bottom: 15px;
    }
    
    .table-container {
        padding: 10px;
    }
    
    .otp-badge {
        font-size: 14px;
        padding: 5px 8px;
    }
    
    .action-btn {
        width: 30px;
        height: 30px;
        margin: 2px;
    }
    
    .user-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .expiry-time {
        font-size: 12px;
        padding: 3px 6px;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="page-title mb-1">
                    <i class="fas fa-key me-2" style="color: var(--primary-color);"></i>
                    Password Reset OTP Management
                </h2>
                <p class="text-muted">Monitor and manage one-time password requests (10-minute expiry from request time)</p>
            </div>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" id="actionsDropdown" data-bs-toggle="dropdown">
                    <i class="fas fa-cog me-2"></i>Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="otp.php?clear_expired=1" onclick="return confirm('Clear all expired password reset requests?');">
                            <i class="fas fa-trash-alt me-2 text-danger"></i>Clear Expired
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#statsModal">
                            <i class="fas fa-chart-pie me-2 text-info"></i>View Statistics
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="#" onclick="location.reload();">
                            <i class="fas fa-sync-alt me-2 text-success"></i>Refresh Page
                        </a>
                    </li>
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
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-icon" style="background: #e3f2fd;">
                        <i class="fas fa-clock" style="color: #0d6efd;"></i>
                    </div>
                    <h3><?php echo number_format($stats['total_requests'] ?? 0); ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-icon" style="background: #d4edda;">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i>
                    </div>
                    <h3><?php echo number_format($stats['active_requests'] ?? 0); ?></h3>
                    <p>Active OTPs (10 min)</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-icon" style="background: #cce5ff;">
                        <i class="fas fa-check-double" style="color: #0056b3;"></i>
                    </div>
                    <h3><?php echo number_format($stats['used_requests'] ?? 0); ?></h3>
                    <p>Used OTPs</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-icon" style="background: #f8d7da;">
                        <i class="fas fa-hourglass-end" style="color: #dc3545;"></i>
                    </div>
                    <h3><?php echo number_format($stats['expired_requests'] ?? 0); ?></h3>
                    <p>Expired OTPs</p>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by name, email, OTP...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select id="userTypeFilter" class="form-select">
                        <option value="">All Users</option>
                        <option value="staff">Staff Only</option>
                        <option value="student">Students Only</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="statusFilter" class="form-select">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="used">Used</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="btn-group w-100">
                        <button class="btn btn-outline-primary" onclick="filterActiveOnly()">
                            <i class="fas fa-check-circle me-2"></i>Active
                        </button>
                        <button class="btn btn-outline-warning" onclick="filterExpiringSoon()">
                            <i class="fas fa-hourglass-half me-2"></i>Last 3 Min
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                        <i class="fas fa-undo me-2"></i>Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Password Reset Requests Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="resetTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Type</th>
                            <th>Contact</th>
                            <th>OTP Code</th>
                            <th>Status</th>
                            <th>Expires At</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reset_requests)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">No password reset requests found</h5>
                                    <p class="text-muted">All OTP requests will appear here</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reset_requests as $request): 
                                $current_time = time();
                                $created_time = strtotime($request['created_at']);
                                $expiry_time = strtotime($request['expires_at']); // This is created_at + 10 minutes
                                $time_remaining = $expiry_time - $current_time;
                                $is_expired = $time_remaining <= 0;
                                $is_used = $request['used'] == 1;
                                
                                // Determine status
                                if ($is_used) {
                                    $status_class = 'badge-used';
                                    $status_text = 'Used';
                                    $status_icon = 'fa-check-circle';
                                } elseif ($is_expired) {
                                    $status_class = 'badge-expired';
                                    $status_text = 'Expired';
                                    $status_icon = 'fa-times-circle';
                                } else {
                                    $status_class = 'badge-active';
                                    $status_text = 'Active';
                                    $status_icon = 'fa-clock';
                                }
                                
                                // Format expiry time display
                                if ($is_used) {
                                    $expiry_display = 'Used';
                                    $expiry_class = 'expiry-time used';
                                } elseif ($is_expired) {
                                    $expiry_display = 'Expired';
                                    $expiry_class = 'expiry-time expired';
                                } else {
                                    // Format as H:i:s (time only)
                                    $expiry_display = date('H:i:s', $expiry_time);
                                    
                                    // Determine expiry class based on time remaining
                                    if ($time_remaining <= 60) { // Last minute
                                        $expiry_class = 'expiry-time critical';
                                    } elseif ($time_remaining <= 180) { // Last 3 minutes
                                        $expiry_class = 'expiry-time warning';
                                    } else {
                                        $expiry_class = 'expiry-time active';
                                    }
                                }
                                
                                // Get avatar
                                if ($request['user_type'] == 'staff' && !empty($request['profile_image'])) {
                                    $avatar = '../uploads/profiles/' . $request['profile_image'];
                                    if (!file_exists($avatar)) {
                                        $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($request['user_full_name']) . '&size=45&background=3B9DB3&color=fff&bold=true';
                                    }
                                } else {
                                    $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($request['user_full_name']) . '&size=45&background=' . ($request['user_type'] == 'staff' ? '28a745' : 'ffc107') . '&color=fff&bold=true';
                                }
                            ?>
                            <tr data-user-type="<?php echo $request['user_type']; ?>"
                                data-status="<?php echo $is_used ? 'used' : ($is_expired ? 'expired' : 'active'); ?>"
                                data-expiry="<?php echo $expiry_time; ?>"
                                data-created="<?php echo $created_time; ?>">
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <img src="<?php echo $avatar; ?>" 
                                                 alt="<?php echo htmlspecialchars($request['user_full_name']); ?>"
                                                 onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($request['user_full_name']); ?>&size=45&background=<?php echo $request['user_type'] == 'staff' ? '28a745' : 'ffc107'; ?>&color=fff&bold=true'">
                                        </div>
                                        <div class="user-details">
                                            <span class="user-name"><?php echo htmlspecialchars($request['user_full_name']); ?></span>
                                            <span class="user-identifier">
                                                <?php if ($request['user_type'] == 'staff'): ?>
                                                    <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($request['user_identifier'] ?? 'No Check No'); ?>
                                                <?php else: ?>
                                                    <i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($request['user_identifier'] ?? 'No Index'); ?>
                                                    <?php if ($request['student_class']): ?>
                                                        | <?php echo $request['student_class']; ?> 
                                                        <?php if ($request['student_combination']): ?>(<?php echo $request['student_combination']; ?>)<?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="user-type-badge user-type-<?php echo $request['user_type']; ?>">
                                        <i class="fas fa-<?php echo $request['user_type'] == 'staff' ? 'user-tie' : 'user-graduate'; ?>"></i>
                                        <?php echo ucfirst($request['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($request['user_email'])): ?>
                                        <div><i class="fas fa-envelope me-1 text-muted"></i> <?php echo htmlspecialchars($request['user_email']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($request['user_phone'])): ?>
                                        <div><i class="fas fa-phone me-1 text-muted"></i> <?php echo htmlspecialchars($request['user_phone']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="otp-badge"><?php echo htmlspecialchars($request['otp']); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <i class="fas <?php echo $status_icon; ?> me-1"></i>
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $expiry_class; ?>" data-expiry="<?php echo $expiry_time; ?>">
                                        <i class="fas fa-hourglass-half me-1"></i>
                                        <?php echo $expiry_display; ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo date('M d, Y', strtotime($request['created_at'])); ?></div>
                                    <small class="text-muted"><?php echo date('H:i:s', strtotime($request['created_at'])); ?></small>
                                </td>
                                <td>
                                    <button type="button" class="action-btn view" title="View Details"
                                            onclick="viewOTPDetails(<?php echo htmlspecialchars(json_encode($request)); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if (!$is_used && !$is_expired): ?>
                                        <a href="otp.php?mark_used=<?php echo $request['id']; ?>" 
                                           class="action-btn success" 
                                           title="Mark as Used"
                                           onclick="return confirm('Mark this OTP as used? This action cannot be undone.');">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="otp.php?delete=<?php echo $request['id']; ?>" 
                                       class="action-btn danger" 
                                       title="Delete Request"
                                       onclick="return confirm('Delete this password reset request? This action cannot be undone.');">
                                        <i class="fas fa-trash"></i>
                                    </a>
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

<!-- View OTP Details Modal -->
<div class="modal fade" id="viewOTPModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-key me-2"></i>OTP Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="otpDetailsContent">
                <!-- Content will be loaded dynamically -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Modal -->
<div class="modal fade" id="statsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-chart-pie me-2"></i>Request Statistics
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <canvas id="statsChart" style="width: 100%; height: 300px;"></canvas>
                
                <hr>
                
                <div class="row mt-4">
                    <div class="col-6">
                        <h6 class="text-muted">Staff Requests</h6>
                        <h4><?php echo number_format($type_stats['staff']['count'] ?? 0); ?></h4>
                        <small class="text-success"><?php echo number_format($type_stats['staff']['active'] ?? 0); ?> active</small>
                    </div>
                    <div class="col-6">
                        <h6 class="text-muted">Student Requests</h6>
                        <h4><?php echo number_format($type_stats['student']['count'] ?? 0); ?></h4>
                        <small class="text-success"><?php echo number_format($type_stats['student']['active'] ?? 0); ?> active</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Initialize DataTable
$(document).ready(function() {
    var table = $('#resetTable').DataTable({
        pageLength: 25,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        order: [[6, 'desc']],
        columnDefs: [
            { orderable: false, targets: [7] }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search records...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)"
        }
    });

    // Custom search
    $('#searchInput').on('keyup', function() {
        table.search(this.value).draw();
    });

    // User type filter
    $('#userTypeFilter').on('change', function() {
        var type = this.value;
        if (type === '') {
            table.column(1).search('').draw();
        } else {
            table.column(1).search(type, true, false).draw();
        }
    });

    // Status filter
    $('#statusFilter').on('change', function() {
        var status = this.value;
        if (status === '') {
            table.column(4).search('').draw();
        } else {
            table.column(4).search(status, true, false).draw();
        }
    });
});

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
            showClass: {
                popup: 'animate__animated animate__fadeInDown'
            }
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
            showClass: {
                popup: 'animate__animated animate__shakeX'
            }
        });
    }

    // Update timers every second
    updateTimers();
    setInterval(updateTimers, 1000);
    
    // Initialize chart in stats modal
    initStatsChart();
});

// Update timer displays
function updateTimers() {
    const timers = document.querySelectorAll('.expiry-time[data-expiry]');
    const now = Math.floor(Date.now() / 1000);
    
    timers.forEach(timer => {
        const expiry = parseInt(timer.getAttribute('data-expiry'));
        const remaining = expiry - now;
        
        if (remaining <= 0) {
            // Timer expired, update row
            const row = timer.closest('tr');
            if (row) {
                const statusCell = row.querySelector('td:nth-child(5) .badge');
                const actionCell = row.querySelector('td:nth-child(8)');
                
                // Update status
                if (statusCell) {
                    statusCell.className = 'badge badge-expired';
                    statusCell.innerHTML = '<i class="fas fa-times-circle me-1"></i>Expired';
                }
                
                // Update expiry display
                timer.className = 'expiry-time expired';
                timer.innerHTML = '<i class="fas fa-hourglass-half me-1"></i>Expired';
                
                // Remove mark as used button if exists
                const markUsedBtn = actionCell?.querySelector('.action-btn.success');
                if (markUsedBtn) {
                    markUsedBtn.remove();
                }
                
                row.setAttribute('data-status', 'expired');
            }
        } else {
            // Update expiry time display (show time only)
            const expiryDate = new Date(expiry * 1000);
            const hours = String(expiryDate.getHours()).padStart(2, '0');
            const minutes = String(expiryDate.getMinutes()).padStart(2, '0');
            const seconds = String(expiryDate.getSeconds()).padStart(2, '0');
            const timeString = `${hours}:${minutes}:${seconds}`;
            timer.innerHTML = `<i class="fas fa-hourglass-half me-1"></i>${timeString}`;
            
            // Update timer class based on remaining time
            if (remaining <= 60) {
                timer.className = 'expiry-time critical';
            } else if (remaining <= 180) {
                timer.className = 'expiry-time warning';
            } else {
                timer.className = 'expiry-time active';
            }
        }
    });
}

// View OTP details
function viewOTPDetails(request) {
    const now = new Date();
    const created = new Date(request.created_at);
    const expiry = new Date(request.expires_at);
    const timeRemaining = Math.floor((expiry - now) / 1000);
    const totalDuration = 600; // 10 minutes in seconds
    const elapsed = Math.floor((now - created) / 1000);
    const percentageElapsed = (elapsed / totalDuration) * 100;
    const isExpired = timeRemaining <= 0;
    const isUsed = request.used == 1;
    
    let statusHtml = '';
    if (isUsed) {
        statusHtml = '<span class="badge badge-used">Used</span>';
    } else if (isExpired) {
        statusHtml = '<span class="badge badge-expired">Expired</span>';
    } else {
        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        statusHtml = `<span class="badge badge-active">Active (${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')} remaining)</span>`;
    }
    
    const userTypeIcon = request.user_type === 'staff' ? 'user-tie' : 'user-graduate';
    const userTypeColor = request.user_type === 'staff' ? '#28a745' : '#ffc107';
    
    const content = `
        <div class="otp-display">
            <div class="otp-code">${request.otp}</div>
            <p class="text-white-50 mb-0">One-Time Password (Valid for 10 minutes)</p>
        </div>
        
        <div class="info-row">
            <div class="info-label"><i class="fas fa-${userTypeIcon} me-2" style="color: ${userTypeColor};"></i>User Type</div>
            <div class="info-value">
                <span class="user-type-badge user-type-${request.user_type}">
                    ${request.user_type === 'staff' ? 'Staff Member' : 'Student'}
                </span>
            </div>
        </div>
        
        <div class="info-row">
            <div class="info-label"><i class="fas fa-user me-2"></i>Full Name</div>
            <div class="info-value"><strong>${request.user_full_name}</strong></div>
        </div>
        
        <div class="info-row">
            <div class="info-label"><i class="fas fa-id-card me-2"></i>Identifier</div>
            <div class="info-value">
                ${request.user_identifier || 'N/A'}
                ${request.student_class ? `<br><small>Class: ${request.student_class} ${request.student_combination ? '(' + request.student_combination + ')' : ''}</small>` : ''}
            </div>
        </div>
        
        <div class="info-row">
            <div class="info-label"><i class="fas fa-envelope me-2"></i>Email</div>
            <div class="info-value">${request.user_email || 'N/A'}</div>
        </div>
        
        <div class="info-row">
            <div class="info-label"><i class="fas fa-phone me-2"></i>Phone</div>
            <div class="info-value">${request.user_phone || 'N/A'}</div>
        </div>
        
      
        
        <div class="info-row">
            <div class="info-label"><i class="fas fa-info-circle me-2"></i>Status</div>
            <div class="info-value">${statusHtml}</div>
        </div>
        
        <div class="info-row">
            <div class="info-label"><i class="fas fa-calendar-plus me-2"></i>Requested At</div>
            <div class="info-value">${created.toLocaleString()}</div>
        </div>
        
        <div class="info-row">
            <div class="info-label"><i class="fas fa-hourglass-end me-2"></i>Expires At</div>
            <div class="info-value ${isExpired ? 'text-danger' : ''}">${expiry.toLocaleString()}</div>
        </div>
        
        <div class="info-row">
            <div class="info-label"><i class="fas fa-chart-line me-2"></i>Time Elapsed</div>
            <div class="info-value">
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar ${percentageElapsed >= 90 ? 'bg-danger' : (percentageElapsed >= 70 ? 'bg-warning' : 'bg-success')}" 
                         style="width: ${Math.min(100, percentageElapsed)}%">
                        ${Math.round(percentageElapsed)}%
                    </div>
                </div>
                <small class="text-muted">${Math.floor(elapsed / 60)}:${String(elapsed % 60).padStart(2, '0')} / 10:00 elapsed</small>
            </div>
        </div>
    `;
    
    document.getElementById('otpDetailsContent').innerHTML = content;
    
    const modal = new bootstrap.Modal(document.getElementById('viewOTPModal'));
    modal.show();
}

// Filter functions
function filterActiveOnly() {
    $('#userTypeFilter').val('');
    $('#statusFilter').val('active').trigger('change');
}

function filterExpiringSoon() {
    const table = $('#resetTable').DataTable();
    $('#userTypeFilter').val('');
    $('#statusFilter').val('');
    
    // Custom filtering for expiring soon (last 3 minutes)
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            const row = table.row(dataIndex).node();
            const status = row.getAttribute('data-status');
            if (status !== 'active') return false;
            
            const expiryCell = row.querySelector('td:nth-child(6) .expiry-time');
            if (!expiryCell) return false;
            
            const expiry = parseInt(expiryCell.getAttribute('data-expiry'));
            const now = Math.floor(Date.now() / 1000);
            const remaining = expiry - now;
            
            return remaining > 0 && remaining <= 180; // Last 3 minutes
        }
    );
    
    table.draw();
    $.fn.dataTable.ext.search.pop();
}

function resetFilters() {
    $('#searchInput').val('');
    $('#userTypeFilter').val('').trigger('change');
    $('#statusFilter').val('').trigger('change');
    
    const table = $('#resetTable').DataTable();
    table.search('').columns().search('').draw();
}

// Initialize statistics chart
function initStatsChart() {
    const statsModal = document.getElementById('statsModal');
    if (statsModal) {
        statsModal.addEventListener('shown.bs.modal', function() {
            const ctx = document.getElementById('statsChart').getContext('2d');
            
            // Get data from PHP
            const staffActive = <?php echo $type_stats['staff']['active'] ?? 0; ?>;
            const staffUsed = <?php echo ($type_stats['staff']['used'] ?? 0) - ($type_stats['staff']['active'] ?? 0); ?>;
            const staffExpired = <?php echo ($type_stats['staff']['count'] ?? 0) - ($type_stats['staff']['used'] ?? 0); ?>;
            
            const studentActive = <?php echo $type_stats['student']['active'] ?? 0; ?>;
            const studentUsed = <?php echo ($type_stats['student']['used'] ?? 0) - ($type_stats['student']['active'] ?? 0); ?>;
            const studentExpired = <?php echo ($type_stats['student']['count'] ?? 0) - ($type_stats['student']['used'] ?? 0); ?>;
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Staff Active', 'Staff Used', 'Staff Expired', 'Student Active', 'Student Used', 'Student Expired'],
                    datasets: [{
                        data: [staffActive, staffUsed, staffExpired, studentActive, studentUsed, studentExpired],
                        backgroundColor: [
                            '#28a745',
                            '#6c757d',
                            '#dc3545',
                            '#ffc107',
                            '#6c757d',
                            '#dc3545'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });
    }
}

// Auto-refresh page every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);
</script>

<?php include '../controller/footer.php'; ?>