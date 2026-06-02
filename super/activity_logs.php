<?php


require_once '../controller/db_connect.php';

// Get current super admin info
$current_super_id = (int)$_SESSION['super_admin_id'];
$current_super_sql = "SELECT * FROM super_admins WHERE id = $current_super_id AND status = 1";
$current_super_result = mysqli_query($conn, $current_super_sql);
$current_super_admin = mysqli_fetch_assoc($current_super_result);

if (!$current_super_admin) {
    session_destroy();
    header("Location: ../mhs/login.php");
    exit();
}

// ==================== CREATE TABLES IF NOT EXISTS ====================

// Create super_admin_logs table
$create_super_logs = "CREATE TABLE IF NOT EXISTS `super_admin_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL,
    `admin_name` varchar(200) DEFAULT NULL,
    `admin_role` varchar(50) DEFAULT NULL,
    `action` varchar(50) NOT NULL,
    `description` text NOT NULL,
    `target_admin_id` int(11) DEFAULT NULL,
    `target_admin_name` varchar(200) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`admin_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
mysqli_query($conn, $create_super_logs);

// Create admin_activity_logs table for school admins
$create_admin_logs = "CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL,
    `admin_name` varchar(200) DEFAULT NULL,
    `admin_role` varchar(100) DEFAULT NULL,
    `school_id` int(11) NOT NULL,
    `school_name` varchar(200) DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `description` text NOT NULL,
    `target_type` varchar(50) DEFAULT NULL,
    `target_id` int(11) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`admin_id`),
    KEY `idx_school_id` (`school_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
mysqli_query($conn, $create_admin_logs);

// ==================== HELPER FUNCTIONS ====================

function logSuperAdminAction($conn, $admin_id, $action, $description, $target_admin_id = null) {
    // Get admin info
    $admin_sql = "SELECT first_name, last_name, role FROM super_admins WHERE id = ?";
    $admin_stmt = $conn->prepare($admin_sql);
    $admin_stmt->bind_param("i", $admin_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    $admin = $admin_result->fetch_assoc();
    $admin_name = $admin ? $admin['first_name'] . ' ' . $admin['last_name'] : 'Unknown';
    $admin_role = $admin ? $admin['role'] : 'Unknown';
    $admin_stmt->close();
    
    $target_admin_name = null;
    if ($target_admin_id) {
        $target_sql = "SELECT first_name, last_name FROM super_admins WHERE id = ?";
        $target_stmt = $conn->prepare($target_sql);
        $target_stmt->bind_param("i", $target_admin_id);
        $target_stmt->execute();
        $target_result = $target_stmt->get_result();
        $target = $target_result->fetch_assoc();
        $target_admin_name = $target ? $target['first_name'] . ' ' . $target['last_name'] : null;
        $target_stmt->close();
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $log_sql = "INSERT INTO super_admin_logs (admin_id, admin_name, admin_role, action, description, target_admin_id, target_admin_name, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($log_sql);
    $stmt->bind_param("issssisss", $admin_id, $admin_name, $admin_role, $action, $description, $target_admin_id, $target_admin_name, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
}

// ==================== DELETE SINGLE LOG ====================
if (isset($_GET['delete_log']) && isset($_GET['id'])) {
    $log_id = (int)$_GET['id'];
    $log_type = $_GET['log_type'] ?? 'admin';
    
    if ($log_type == 'super') {
        $log_sql = "SELECT id, action, description FROM super_admin_logs WHERE id = ?";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("i", $log_id);
        $log_stmt->execute();
        $log_result = $log_stmt->get_result();
        $log = $log_result->fetch_assoc();
        
        if ($log) {
            $delete_sql = "DELETE FROM super_admin_logs WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $log_id);
            if ($delete_stmt->execute()) {
                logSuperAdminAction($conn, $current_super_id, 'DELETE_LOG', "Deleted super admin log ID: {$log_id} - Action: {$log['action']}", null);
                $_SESSION['toast_message'] = "Log entry deleted successfully!";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_message'] = "Failed to delete log entry!";
                $_SESSION['toast_type'] = "error";
            }
            $delete_stmt->close();
        }
        $log_stmt->close();
    } else {
        // Delete from admin_logs or admin_activity_logs
        $delete_sql = "DELETE FROM admin_logs WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $log_id);
        if ($delete_stmt->execute()) {
            $_SESSION['toast_message'] = "Log entry deleted successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Failed to delete log entry!";
            $_SESSION['toast_type'] = "error";
        }
        $delete_stmt->close();
    }
    
    header("Location: activity_logs.php");
    exit();
}

// ==================== BULK DELETE LOGS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $log_ids = $_POST['log_ids'] ?? [];
    $log_type = $_POST['log_type'] ?? 'admin';
    
    if (!empty($log_ids)) {
        $ids = implode(',', array_map('intval', $log_ids));
        $count = count($log_ids);
        
        if ($log_type == 'super') {
            $logs_sql = "SELECT GROUP_CONCAT(DISTINCT action SEPARATOR ', ') as actions FROM super_admin_logs WHERE id IN ($ids)";
            $logs_result = mysqli_query($conn, $logs_sql);
            $logs_info = mysqli_fetch_assoc($logs_result);
            $actions = $logs_info['actions'] ?? 'multiple';
            
            $delete_sql = "DELETE FROM super_admin_logs WHERE id IN ($ids)";
            if (mysqli_query($conn, $delete_sql)) {
                logSuperAdminAction($conn, $current_super_id, 'BULK_DELETE', "Bulk deleted {$count} super admin logs (Actions: {$actions})", null);
                $_SESSION['toast_message'] = "{$count} log entries deleted successfully!";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_message'] = "Failed to delete logs!";
                $_SESSION['toast_type'] = "error";
            }
        } else {
            $delete_sql = "DELETE FROM admin_logs WHERE id IN ($ids)";
            if (mysqli_query($conn, $delete_sql)) {
                $_SESSION['toast_message'] = "{$count} log entries deleted successfully!";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_message'] = "Failed to delete logs!";
                $_SESSION['toast_type'] = "error";
            }
        }
    } else {
        $_SESSION['toast_message'] = "No logs selected for deletion!";
        $_SESSION['toast_type'] = "error";
    }
    
    header("Location: activity_logs.php");
    exit();
}

// ==================== DELETE ALL LOGS ====================
if (isset($_GET['delete_all'])) {
    $log_type = $_GET['log_type'] ?? 'admin';
    $confirm = $_GET['confirm'] ?? '';
    
    if ($confirm == 'yes') {
        if ($log_type == 'super') {
            $count_sql = "SELECT COUNT(*) as total FROM super_admin_logs";
            $count_result = mysqli_query($conn, $count_sql);
            $count_row = mysqli_fetch_assoc($count_result);
            $total_logs = $count_row['total'];
            
            $delete_sql = "DELETE FROM super_admin_logs";
            if (mysqli_query($conn, $delete_sql)) {
                logSuperAdminAction($conn, $current_super_id, 'DELETE_ALL_LOGS', "Deleted all {$total_logs} super admin logs", null);
                $_SESSION['toast_message'] = "All {$total_logs} log entries deleted successfully!";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_message'] = "Failed to delete logs!";
                $_SESSION['toast_type'] = "error";
            }
        } else {
            $count_sql = "SELECT COUNT(*) as total FROM admin_logs";
            $count_result = mysqli_query($conn, $count_sql);
            $count_row = mysqli_fetch_assoc($count_result);
            $total_logs = $count_row['total'];
            
            $delete_sql = "DELETE FROM admin_logs";
            if (mysqli_query($conn, $delete_sql)) {
                $_SESSION['toast_message'] = "All {$total_logs} log entries deleted successfully!";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_message'] = "Failed to delete logs!";
                $_SESSION['toast_type'] = "error";
            }
        }
    } else {
        $_SESSION['toast_message'] = "Invalid confirmation!";
        $_SESSION['toast_type'] = "error";
    }
    
    header("Location: activity_logs.php");
    exit();
}

// ==================== GET PAGINATION DATA ====================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;
$log_type = isset($_GET['log_type']) ? $_GET['log_type'] : 'admin';

// Filters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$action_filter = isset($_GET['action']) ? mysqli_real_escape_string($conn, $_GET['action']) : '';
$date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';

// Build WHERE clause
$where_clauses = [];
if (!empty($search)) {
    $where_clauses[] = "(action LIKE '%$search%' OR description LIKE '%$search%' OR details LIKE '%$search%' OR ip_address LIKE '%$search%')";
}
if (!empty($action_filter)) {
    $where_clauses[] = "action = '$action_filter'";
}
if (!empty($date_from)) {
    $where_clauses[] = "DATE(created_at) >= '$date_from'";
}
if (!empty($date_to)) {
    $where_clauses[] = "DATE(created_at) <= '$date_to'";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get logs based on type
if ($log_type == 'super') {
    // Get from super_admin_logs
    $count_sql = "SELECT COUNT(*) as total FROM super_admin_logs $where_sql";
    $count_result = mysqli_query($conn, $count_sql);
    $count_row = mysqli_fetch_assoc($count_result);
    $total_logs = $count_row['total'];
    $total_pages = ceil($total_logs / $limit);
    
    $logs_sql = "SELECT *, 'super' as source FROM super_admin_logs $where_sql ORDER BY created_at DESC LIMIT $offset, $limit";
    $logs_result = mysqli_query($conn, $logs_sql);
    $logs = [];
    if ($logs_result) {
        while ($row = mysqli_fetch_assoc($logs_result)) {
            $logs[] = $row;
        }
    }
    
    // Get unique actions
    $actions_sql = "SELECT DISTINCT action FROM super_admin_logs ORDER BY action";
    $actions_result = mysqli_query($conn, $actions_sql);
    $actions = [];
    if ($actions_result) {
        while ($row = mysqli_fetch_assoc($actions_result)) {
            $actions[] = $row['action'];
        }
    }
    
    // Get statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT admin_id) as unique_admins,
                    COUNT(DISTINCT action) as unique_actions,
                    DATE(MAX(created_at)) as last_activity
                  FROM super_admin_logs";
    $stats_result = mysqli_query($conn, $stats_sql);
    $stats = mysqli_fetch_assoc($stats_result);
} else {
    // Get from admin_logs
    $count_sql = "SELECT COUNT(*) as total FROM admin_logs $where_sql";
    $count_result = mysqli_query($conn, $count_sql);
    $count_row = mysqli_fetch_assoc($count_result);
    $total_logs = $count_row['total'];
    $total_pages = ceil($total_logs / $limit);
    
    $logs_sql = "SELECT *, 'admin' as source FROM admin_logs $where_sql ORDER BY created_at DESC LIMIT $offset, $limit";
    $logs_result = mysqli_query($conn, $logs_sql);
    $logs = [];
    if ($logs_result) {
        while ($row = mysqli_fetch_assoc($logs_result)) {
            $logs[] = $row;
        }
    }
    
    // Get unique actions
    $actions_sql = "SELECT DISTINCT action FROM admin_logs ORDER BY action";
    $actions_result = mysqli_query($conn, $actions_sql);
    $actions = [];
    if ($actions_result) {
        while ($row = mysqli_fetch_assoc($actions_result)) {
            $actions[] = $row['action'];
        }
    }
    
    // Get statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT admin_id) as unique_admins,
                    COUNT(DISTINCT action) as unique_actions,
                    DATE(MAX(created_at)) as last_activity
                  FROM admin_logs";
    $stats_result = mysqli_query($conn, $stats_sql);
    $stats = mysqli_fetch_assoc($stats_result);
}

date_default_timezone_set('Africa/Dar_es_Salaam');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Super Admin Panel</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        :root {
            --primary-color: #3B9DB3;
            --primary-dark: #2d7c8f;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 20px;
            padding: 25px 30px;
            color: white;
            margin-bottom: 30px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-3px);
        }

        .stats-card h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .log-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .log-table th {
            background: #f8f9fa;
            padding: 15px;
            font-weight: 600;
        }

        .log-table td {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .action-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .action-Login, .action-login { background: #d4edda; color: #155724; }
        .action-Logout, .action-logout { background: #f8d7da; color: #721c24; }
        .action-Student\ Registered, .action-student_registered { background: #cce5ff; color: #004085; }
        .action-register_teacher { background: #d1ecf1; color: #0c5460; }
        .action-Add\ Exam\ Type, .action-add_exam_type { background: #fff3cd; color: #856404; }
        .action-Delete\ Exam\ Type, .action-delete_exam_type { background: #f8d7da; color: #721c24; }
        .action-Assign\ Subject, .action-assign_subject { background: #d4edda; color: #155724; }
        .action-Edit\ Exam\ Type, .action-edit_exam_type { background: #d1ecf1; color: #0c5460; }
        .action-Toggle\ Exam\ Status, .action-toggle_exam_status { background: #e2e3e5; color: #383d41; }
        .action-Shule\ Salama\ Post { background: #cce5ff; color: #004085; }
        .action-Shule\ Salama\ Delete { background: #f8d7da; color: #721c24; }
        .default-action { background: #e2e3e5; color: #383d41; }

        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .page-link {
            border-radius: 8px;
            margin: 0 3px;
            color: var(--primary-color);
        }
        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .select-all-checkbox {
            cursor: pointer;
        }

        .btn-bulk-delete {
            background: #dc3545;
            color: white;
            border-radius: 8px;
            padding: 8px 20px;
        }
        .btn-bulk-delete:hover {
            background: #c82333;
            color: white;
        }

        .log-details {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .checkbox-col {
            width: 40px;
        }
        
        .action-col {
            width: 150px;
        }
        
        .admin-col {
            width: 100px;
        }
        
        .date-col {
            width: 160px;
        }
        
        .nav-pills .nav-link {
            border-radius: 25px;
            padding: 8px 20px;
        }
        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
        }
        
        .log-source-badge {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-block;
        }
        .source-super {
            background: #cce5ff;
            color: #004085;
        }
        .source-admin {
            background: #e2e3e5;
            color: #383d41;
        }
    </style>
</head>
<body>

<?php include '../controller/header.php'; ?>
<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="container-fluid">
        
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-history me-2"></i> Activity Logs</h2>
                    <p class="mb-0">Monitor all system activities - Login, Registrations, Exam Management, and more</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if ($total_logs > 0): ?>
                    <button type="button" class="btn btn-light" onclick="deleteAllLogs()">
                        <i class="fas fa-trash-alt me-2"></i> Delete All
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Log Type Tabs -->
        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $log_type == 'admin' ? 'active' : ''; ?>" href="?log_type=admin&page=1&limit=<?php echo $limit; ?>">
                    <i class="fas fa-school me-2"></i> School Admin Logs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $log_type == 'super' ? 'active' : ''; ?>" href="?log_type=super&page=1&limit=<?php echo $limit; ?>">
                    <i class="fas fa-user-shield me-2"></i> Super Admin Logs
                </a>
            </li>
        </ul>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <h2><?php echo number_format($stats['total_logs'] ?? 0); ?></h2>
                    <p><i class="fas fa-list me-1"></i> Total Activities</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <h2><?php echo $stats['unique_admins'] ?? 0; ?></h2>
                    <p><i class="fas fa-users me-1"></i> Active Users</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <h2><?php echo $stats['unique_actions'] ?? 0; ?></h2>
                    <p><i class="fas fa-tasks me-1"></i> Action Types</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <h2><?php echo ($stats['last_activity'] ?? '') ? date('M d, Y', strtotime($stats['last_activity'])) : 'N/A'; ?></h2>
                    <p><i class="fas fa-calendar me-1"></i> Last Activity</p>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card">
            <form method="GET" action="" id="filterForm">
                <input type="hidden" name="log_type" value="<?php echo $log_type; ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" placeholder="Action, description, IP..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Action Type</label>
                        <select class="form-select" name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $act): ?>
                            <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $action_filter == $act ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($act); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Items Per Page</label>
                        <select class="form-select" name="limit" onchange="this.form.submit()">
                            <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100" style="background: var(--primary-color); border: none;">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <a href="activity_logs.php?log_type=<?php echo $log_type; ?>" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Delete Bar -->
        <?php if ($total_logs > 0): ?>
        <div class="mb-3" id="bulkDeleteBar" style="display: none;">
            <div class="alert alert-warning d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="selectedCount">0</span> log(s) selected
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmBulkDelete()">
                        <i class="fas fa-trash me-1"></i> Delete Selected
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="clearSelection()">
                        <i class="fas fa-times me-1"></i> Clear
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Logs Table -->
        <div class="log-table">
            <form method="POST" id="bulkDeleteForm">
                <input type="hidden" name="bulk_delete" value="1">
                <input type="hidden" name="log_type" value="<?php echo $log_type; ?>">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="checkbox-col">
                                <input type="checkbox" id="selectAllCheckbox" class="select-all-checkbox">
                            </th>
                            <th>ID</th>
                            <?php if ($log_type == 'super'): ?>
                            <th>Admin Name</th>
                            <th>Role</th>
                            <?php else: ?>
                            <th>Admin ID</th>
                            <?php endif; ?>
                            <th>Action</th>
                            <th>Description</th>
                            <?php if ($log_type == 'admin'): ?>
                            <th>Details</th>
                            <?php endif; ?>
                            <th>IP Address</th>
                            <th class="date-col">Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="<?php echo $log_type == 'super' ? '9' : '9'; ?>" class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                <p class="text-muted">No activity logs found</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="log_ids[]" value="<?php echo $log['id']; ?>" class="log-checkbox">
                            </td>
                            <td><?php echo $log['id']; ?></td>
                            <?php if ($log_type == 'super'): ?>
                            <td><?php echo htmlspecialchars($log['admin_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['admin_role'] ?? 'N/A'); ?></td>
                            <?php else: ?>
                            <td><?php echo $log['admin_id'] ?? 'N/A'; ?></td>
                            <?php endif; ?>
                            <td>
                                <span class="action-badge action-<?php echo str_replace(' ', '-', $log['action']); ?> default-action">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td class="log-details" title="<?php echo htmlspecialchars($log['description'] ?? ''); ?>">
                                <?php echo htmlspecialchars(substr($log['description'] ?? '', 0, 60)); ?>
                                <?php echo strlen($log['description'] ?? '') > 60 ? '...' : ''; ?>
                            </td>
                            <?php if ($log_type == 'admin'): ?>
                            <td class="log-details" title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>">
                                <?php echo htmlspecialchars(substr($log['details'] ?? '', 0, 50)); ?>
                                <?php echo strlen($log['details'] ?? '') > 50 ? '...' : ''; ?>
                            </td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                            <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="deleteLog(<?php echo $log['id']; ?>, '<?php echo $log_type; ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?log_type=<?php echo $log_type; ?>&page=<?php echo $page-1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?log_type=' . $log_type . '&page=1&limit=' . $limit . '&search=' . urlencode($search) . '&action=' . urlencode($action_filter) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">1</a></li>';
                    if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active = $i == $page ? 'active' : '';
                    echo '<li class="page-item ' . $active . '"><a class="page-link" href="?log_type=' . $log_type . '&page=' . $i . '&limit=' . $limit . '&search=' . urlencode($search) . '&action=' . urlencode($action_filter) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">' . $i . '</a></li>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    echo '<li class="page-item"><a class="page-link" href="?log_type=' . $log_type . '&page=' . $total_pages . '&limit=' . $limit . '&search=' . urlencode($search) . '&action=' . urlencode($action_filter) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">' . $total_pages . '</a></li>';
                }
                ?>
                
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?log_type=<?php echo $log_type; ?>&page=<?php echo $page+1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        
        <div class="text-muted text-center mt-3">
            <small>Showing <?php echo count($logs); ?> of <?php echo number_format($total_logs); ?> log entries</small>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toast Messages
<?php if (isset($_SESSION['toast_message'])): ?>
Swal.fire({
    icon: '<?php echo $_SESSION['toast_type'] == 'success' ? 'success' : 'error'; ?>',
    title: '<?php echo $_SESSION['toast_type'] == 'success' ? 'Success!' : 'Error!'; ?>',
    text: '<?php echo htmlspecialchars($_SESSION['toast_message']); ?>',
    confirmButtonColor: '#3B9DB3',
    timer: 3000,
    showConfirmButton: true
});
<?php unset($_SESSION['toast_message']); unset($_SESSION['toast_type']); endif; ?>

// Select All functionality
$('#selectAllCheckbox').on('change', function() {
    $('.log-checkbox').prop('checked', $(this).prop('checked'));
    updateBulkDeleteBar();
});

$('.log-checkbox').on('change', function() {
    updateBulkDeleteBar();
    $('#selectAllCheckbox').prop('checked', $('.log-checkbox:checked').length === $('.log-checkbox').length);
});

function updateBulkDeleteBar() {
    var selected = $('.log-checkbox:checked').length;
    if (selected > 0) {
        $('#bulkDeleteBar').show();
        $('#selectedCount').text(selected);
    } else {
        $('#bulkDeleteBar').hide();
    }
}

function clearSelection() {
    $('.log-checkbox').prop('checked', false);
    $('#selectAllCheckbox').prop('checked', false);
    updateBulkDeleteBar();
}

function confirmBulkDelete() {
    var selected = $('.log-checkbox:checked').length;
    if (selected === 0) {
        Swal.fire('Info', 'Please select at least one log to delete', 'info');
        return;
    }
    
    Swal.fire({
        title: 'Delete Logs?',
        html: `Are you sure you want to delete <strong>${selected}</strong> log entry(s)?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $('#bulkDeleteForm').submit();
        }
    });
}

function deleteLog(id, logType) {
    Swal.fire({
        title: 'Delete Log Entry?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'activity_logs.php?delete_log=1&id=' + id + '&log_type=' + logType;
        }
    });
}

function deleteAllLogs() {
    var logType = '<?php echo $log_type; ?>';
    var title = logType == 'super' ? 'Delete All Super Admin Logs?' : 'Delete All School Admin Logs?';
    
    Swal.fire({
        title: title,
        html: 'Are you sure you want to delete <strong>ALL</strong> activity logs?<br><br>This action cannot be undone!',
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete everything!',
        cancelButtonText: 'Cancel',
        input: 'text',
        inputPlaceholder: 'Type "DELETE ALL" to confirm',
        inputValidator: (value) => {
            if (!value) return 'You need to type DELETE ALL to confirm!';
            if (value !== 'DELETE ALL') return 'Please type DELETE ALL exactly to confirm';
        }
    }).then((result) => {
        if (result.isConfirmed && result.value === 'DELETE ALL') {
            window.location.href = 'activity_logs.php?delete_all=1&log_type=' + logType + '&confirm=yes';
        }
    });
}

// Auto-submit filter on enter key
$('#filterForm input, #filterForm select').on('keypress', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        $('#filterForm').submit();
    }
});
</script>

<?php include '../controller/footer.php'; ?>
</body>
</html>