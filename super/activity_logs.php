<?php
// super/activity_logs.php - View and Manage System Activity Logs
session_start();

// Check if Super Admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    while (ob_get_level() > 0) ob_end_clean();
    header("Location: ../mhs/login.php");
    exit();
}

require_once '../controller/db_connect.php';

// Get current super admin info
$super_id = (int)$_SESSION['super_admin_id'];
$super_sql = "SELECT * FROM super_admins WHERE id = $super_id AND status = 1";
$super_result = mysqli_query($conn, $super_sql);
$super_admin = mysqli_fetch_assoc($super_result);
if (!$super_admin) {
    session_destroy();
    header("Location: ../mhs/login.php");
    exit();
}

// ==================== DELETE SINGLE LOG ====================
if (isset($_GET['delete_single']) && isset($_GET['id']) && isset($_GET['table'])) {
    $log_id = (int)$_GET['id'];
    $table = $_GET['table'];
    $allowed_tables = ['admin_logs', 'super_admin_logs', 'student_login_logs', 'admin_login_attempts', 'student_login_attempts'];
    if (!in_array($table, $allowed_tables)) {
        $_SESSION['toast_message'] = "Invalid table specified!";
        $_SESSION['toast_type'] = "error";
        header("Location: activity_logs.php");
        exit();
    }
    
    // Delete based on table
    if ($table === 'admin_logs') {
        $stmt = $conn->prepare("DELETE FROM admin_logs WHERE id = ?");
    } elseif ($table === 'super_admin_logs') {
        $stmt = $conn->prepare("DELETE FROM super_admin_logs WHERE id = ?");
    } elseif ($table === 'student_login_logs') {
        $stmt = $conn->prepare("DELETE FROM student_login_logs WHERE id = ?");
    } elseif ($table === 'admin_login_attempts') {
        $stmt = $conn->prepare("DELETE FROM admin_login_attempts WHERE id = ?");
    } else {
        $stmt = $conn->prepare("DELETE FROM student_login_attempts WHERE id = ?");
    }
    
    $stmt->bind_param("i", $log_id);
    if ($stmt->execute()) {
        $_SESSION['toast_message'] = "Log entry deleted successfully!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Failed to delete log entry!";
        $_SESSION['toast_type'] = "error";
    }
    $stmt->close();
    header("Location: activity_logs.php");
    exit();
}

// ==================== BULK DELETE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $table = $_POST['table'] ?? '';
    $ids = $_POST['ids'] ?? [];
    $allowed_tables = ['admin_logs', 'super_admin_logs', 'student_login_logs', 'admin_login_attempts', 'student_login_attempts'];
    if (!in_array($table, $allowed_tables) || empty($ids)) {
        $_SESSION['toast_message'] = "Invalid request!";
        $_SESSION['toast_type'] = "error";
        header("Location: activity_logs.php");
        exit();
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    if ($table === 'admin_logs') {
        $stmt = $conn->prepare("DELETE FROM admin_logs WHERE id IN ($placeholders)");
    } elseif ($table === 'super_admin_logs') {
        $stmt = $conn->prepare("DELETE FROM super_admin_logs WHERE id IN ($placeholders)");
    } elseif ($table === 'student_login_logs') {
        $stmt = $conn->prepare("DELETE FROM student_login_logs WHERE id IN ($placeholders)");
    } elseif ($table === 'admin_login_attempts') {
        $stmt = $conn->prepare("DELETE FROM admin_login_attempts WHERE id IN ($placeholders)");
    } else {
        $stmt = $conn->prepare("DELETE FROM student_login_attempts WHERE id IN ($placeholders)");
    }
    
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    if ($stmt->execute()) {
        $_SESSION['toast_message'] = count($ids) . " log entries deleted successfully!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Failed to delete logs!";
        $_SESSION['toast_type'] = "error";
    }
    $stmt->close();
    header("Location: activity_logs.php");
    exit();
}

// ==================== CLEAR ALL LOGS ====================
if (isset($_GET['clear_all']) && isset($_GET['table'])) {
    $table = $_GET['table'];
    $allowed_tables = ['admin_logs', 'super_admin_logs', 'student_login_logs', 'admin_login_attempts', 'student_login_attempts'];
    if (!in_array($table, $allowed_tables)) {
        $_SESSION['toast_message'] = "Invalid table!";
        $_SESSION['toast_type'] = "error";
        header("Location: activity_logs.php");
        exit();
    }
    
    if ($table === 'admin_logs') {
        $conn->query("TRUNCATE TABLE admin_logs");
    } elseif ($table === 'super_admin_logs') {
        $conn->query("TRUNCATE TABLE super_admin_logs");
    } elseif ($table === 'student_login_logs') {
        $conn->query("TRUNCATE TABLE student_login_logs");
    } elseif ($table === 'admin_login_attempts') {
        $conn->query("TRUNCATE TABLE admin_login_attempts");
    } else {
        $conn->query("TRUNCATE TABLE student_login_attempts");
    }
    
    $_SESSION['toast_message'] = "All logs from " . str_replace('_', ' ', $table) . " have been cleared!";
    $_SESSION['toast_type'] = "success";
    header("Location: activity_logs.php");
    exit();
}

// Fetch counts
$admin_logs_count = $conn->query("SELECT COUNT(*) as c FROM admin_logs")->fetch_assoc()['c'];
$super_logs_count = $conn->query("SELECT COUNT(*) as c FROM super_admin_logs")->fetch_assoc()['c'];
$student_logins_count = $conn->query("SELECT COUNT(*) as c FROM student_login_logs")->fetch_assoc()['c'];
$admin_attempts_count = $conn->query("SELECT COUNT(*) as c FROM admin_login_attempts")->fetch_assoc()['c'];
$student_attempts_count = $conn->query("SELECT COUNT(*) as c FROM student_login_attempts")->fetch_assoc()['c'];

date_default_timezone_set('Africa/Dar_es_Salaam');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <style>
        :root { --primary-color: #3B9DB3; --primary-dark: #2d7c8f; }
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s;
            cursor: pointer;
        }
        .stats-card:hover { transform: translateY(-3px); }
        .stats-card h2 { font-size: 1.8rem; font-weight: 700; margin-bottom: 5px; color: var(--primary-color); }
        .log-tabs .nav-link {
            border-radius: 30px;
            margin: 0 5px;
            padding: 10px 20px;
            color: #495057;
            font-weight: 500;
        }
        .log-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }
        .table-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .btn-bulk-delete {
            background: #dc3545;
            border: none;
            border-radius: 10px;
            padding: 8px 20px;
            color: white;
        }
        .btn-clear-all {
            background: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 8px 20px;
            color: white;
        }
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 20px;
            padding: 5px 15px;
            border: 1px solid #ddd;
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
                    <h2><i class="fas fa-history me-2"></i> System Activity Logs</h2>
                    <p class="mb-0">Monitor all system activities, login attempts, and admin actions</p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards (clickable to switch tabs) -->
        <div class="row mb-4">
            <div class="col-md-2 col-6 mb-3">
                <div class="stats-card" data-tab="admin_logs">
                    <h2><?php echo number_format($admin_logs_count); ?></h2>
                    <p><i class="fas fa-user-shield me-1"></i> Admin Actions</p>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="stats-card" data-tab="super_logs">
                    <h2><?php echo number_format($super_logs_count); ?></h2>
                    <p><i class="fas fa-crown me-1"></i> Super Admin Actions</p>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="stats-card" data-tab="student_logins">
                    <h2><?php echo number_format($student_logins_count); ?></h2>
                    <p><i class="fas fa-user-graduate me-1"></i> Student Logins</p>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="stats-card" data-tab="admin_attempts">
                    <h2><?php echo number_format($admin_attempts_count); ?></h2>
                    <p><i class="fas fa-sign-in-alt me-1"></i> Admin Login Attempts</p>
                </div>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <div class="stats-card" data-tab="student_attempts">
                    <h2><?php echo number_format($student_attempts_count); ?></h2>
                    <p><i class="fas fa-key me-1"></i> Student Login Attempts</p>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs log-tabs mb-3" id="logTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="admin-logs-tab" data-bs-toggle="tab" data-bs-target="#admin_logs" type="button" role="tab">Admin Actions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="super-logs-tab" data-bs-toggle="tab" data-bs-target="#super_logs" type="button" role="tab">Super Admin Actions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="student-logins-tab" data-bs-toggle="tab" data-bs-target="#student_logins" type="button" role="tab">Student Logins</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="admin-attempts-tab" data-bs-toggle="tab" data-bs-target="#admin_attempts" type="button" role="tab">Admin Login Attempts</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="student-attempts-tab" data-bs-toggle="tab" data-bs-target="#student_attempts" type="button" role="tab">Student Login Attempts</button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="logTabsContent">
            <!-- Admin Logs -->
            <div class="tab-pane fade show active" id="admin_logs" role="tabpanel">
                <div class="card table-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i> Admin Actions Log</h5>
                        <div>
                            <button class="btn btn-sm btn-danger btn-bulk-delete me-2" data-table="admin_logs" onclick="confirmBulkDelete('admin_logs')">
                                <i class="fas fa-trash-alt me-1"></i> Bulk Delete
                            </button>
                            <button class="btn btn-sm btn-secondary btn-clear-all" data-table="admin_logs" onclick="confirmClearAll('admin_logs')">
                                <i class="fas fa-trash me-1"></i> Clear All
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="table_admin_logs">
                                <thead>
                                    <tr><th><input type="checkbox" class="select-all-checkbox" data-table="admin_logs"></th><th>ID</th><th>Admin</th><th>Action</th><th>Description</th><th>IP Address</th><th>Time</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $logs = $conn->query("SELECT al.*, CONCAT(a.first_name, ' ', a.last_name) as admin_name 
                                                           FROM admin_logs al 
                                                           LEFT JOIN admins a ON al.admin_id = a.id 
                                                           ORDER BY al.created_at DESC LIMIT 1000");
                                    while ($row = $logs->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="log-checkbox" data-id="<?php echo $row['id']; ?>" data-table="admin_logs"></td>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['admin_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($row['action']); ?></td>
                                        <td><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['ip_address'] ?? '-'); ?></td>
                                        <td><?php echo date('M d, Y H:i:s', strtotime($row['created_at'])); ?></td>
                                        <td><button class="btn btn-sm btn-danger" onclick="deleteSingle('admin_logs', <?php echo $row['id']; ?>)"><i class="fas fa-trash"></i></button></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Super Admin Logs -->
            <div class="tab-pane fade" id="super_logs" role="tabpanel">
                <div class="card table-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-crown me-2"></i> Super Admin Actions Log</h5>
                        <div>
                            <button class="btn btn-sm btn-danger btn-bulk-delete me-2" data-table="super_admin_logs" onclick="confirmBulkDelete('super_admin_logs')">
                                <i class="fas fa-trash-alt me-1"></i> Bulk Delete
                            </button>
                            <button class="btn btn-sm btn-secondary btn-clear-all" data-table="super_admin_logs" onclick="confirmClearAll('super_admin_logs')">
                                <i class="fas fa-trash me-1"></i> Clear All
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="table_super_admin_logs">
                                <thead>
                                    <tr><th><input type="checkbox" class="select-all-checkbox" data-table="super_admin_logs"></th><th>ID</th><th>Admin</th><th>Action</th><th>Description</th><th>Target</th><th>IP</th><th>Time</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $super_logs = $conn->query("SELECT sl.*, CONCAT(sa.first_name, ' ', sa.last_name) as admin_name 
                                                                 FROM super_admin_logs sl 
                                                                 LEFT JOIN super_admins sa ON sl.admin_id = sa.id 
                                                                 ORDER BY sl.created_at DESC LIMIT 1000");
                                    while ($row = $super_logs->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="log-checkbox" data-id="<?php echo $row['id']; ?>" data-table="super_admin_logs"></td>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['admin_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($row['action']); ?></td>
                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td><?php echo $row['target_admin_id'] ? 'Admin ID: ' . $row['target_admin_id'] : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($row['ip_address'] ?? '-'); ?></td>
                                        <td><?php echo date('M d, Y H:i:s', strtotime($row['created_at'])); ?></td>
                                        <td><button class="btn btn-sm btn-danger" onclick="deleteSingle('super_admin_logs', <?php echo $row['id']; ?>)"><i class="fas fa-trash"></i></button></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Login Logs -->
            <div class="tab-pane fade" id="student_logins" role="tabpanel">
                <div class="card table-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-user-graduate me-2"></i> Student Login History</h5>
                        <div>
                            <button class="btn btn-sm btn-danger btn-bulk-delete me-2" data-table="student_login_logs" onclick="confirmBulkDelete('student_login_logs')">
                                <i class="fas fa-trash-alt me-1"></i> Bulk Delete
                            </button>
                            <button class="btn btn-sm btn-secondary btn-clear-all" data-table="student_login_logs" onclick="confirmClearAll('student_login_logs')">
                                <i class="fas fa-trash me-1"></i> Clear All
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="table_student_login_logs">
                                <thead>
                                    <tr><th><input type="checkbox" class="select-all-checkbox" data-table="student_login_logs"></th><th>ID</th><th>Student</th><th>Action</th><th>IP Address</th><th>Time</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $student_logs = $conn->query("SELECT sll.*, CONCAT(st.first_name, ' ', st.last_name) as student_name 
                                                                   FROM student_login_logs sll 
                                                                   LEFT JOIN students st ON sll.student_id = st.id 
                                                                   ORDER BY sll.login_time DESC LIMIT 1000");
                                    while ($row = $student_logs->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="log-checkbox" data-id="<?php echo $row['id']; ?>" data-table="student_login_logs"></td>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['student_name'] ?? 'Unknown Student (ID: '.$row['student_id'].')'); ?></td>
                                        <td><?php echo htmlspecialchars($row['action'] ?: 'Login'); ?></td>
                                        <td><?php echo htmlspecialchars($row['ip_address'] ?? '-'); ?></td>
                                        <td><?php echo date('M d, Y H:i:s', strtotime($row['login_time'])); ?></td>
                                        <td><button class="btn btn-sm btn-danger" onclick="deleteSingle('student_login_logs', <?php echo $row['id']; ?>)"><i class="fas fa-trash"></i></button></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Login Attempts -->
            <div class="tab-pane fade" id="admin_attempts" role="tabpanel">
                <div class="card table-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i> Admin Login Attempts</h5>
                        <div>
                            <button class="btn btn-sm btn-danger btn-bulk-delete me-2" data-table="admin_login_attempts" onclick="confirmBulkDelete('admin_login_attempts')">
                                <i class="fas fa-trash-alt me-1"></i> Bulk Delete
                            </button>
                            <button class="btn btn-sm btn-secondary btn-clear-all" data-table="admin_login_attempts" onclick="confirmClearAll('admin_login_attempts')">
                                <i class="fas fa-trash me-1"></i> Clear All
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="table_admin_login_attempts">
                                <thead>
                                    <tr><th><input type="checkbox" class="select-all-checkbox" data-table="admin_login_attempts"></th><th>ID</th><th>Identifier</th><th>Status</th><th>IP</th><th>Time</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $attempts = $conn->query("SELECT * FROM admin_login_attempts ORDER BY attempt_time DESC LIMIT 1000");
                                    while ($row = $attempts->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="log-checkbox" data-id="<?php echo $row['id']; ?>" data-table="admin_login_attempts"></td>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['identifier']); ?></td>
                                        <td><?php echo $row['success'] ? '<span class="badge bg-success">Success</span>' : '<span class="badge bg-danger">Failed</span>'; ?></td>
                                        <td><?php echo htmlspecialchars($row['ip_address'] ?? '-'); ?></td>
                                        <td><?php echo date('M d, Y H:i:s', strtotime($row['attempt_time'])); ?></td>
                                        <td><button class="btn btn-sm btn-danger" onclick="deleteSingle('admin_login_attempts', <?php echo $row['id']; ?>)"><i class="fas fa-trash"></i></button></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Login Attempts -->
            <div class="tab-pane fade" id="student_attempts" role="tabpanel">
                <div class="card table-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-key me-2"></i> Student Login Attempts</h5>
                        <div>
                            <button class="btn btn-sm btn-danger btn-bulk-delete me-2" data-table="student_login_attempts" onclick="confirmBulkDelete('student_login_attempts')">
                                <i class="fas fa-trash-alt me-1"></i> Bulk Delete
                            </button>
                            <button class="btn btn-sm btn-secondary btn-clear-all" data-table="student_login_attempts" onclick="confirmClearAll('student_login_attempts')">
                                <i class="fas fa-trash me-1"></i> Clear All
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="table_student_login_attempts">
                                <thead>
                                    <tr><th><input type="checkbox" class="select-all-checkbox" data-table="student_login_attempts"></th><th>ID</th><th>Identifier</th><th>Status</th><th>IP</th><th>Time</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $student_attempts = $conn->query("SELECT * FROM student_login_attempts ORDER BY attempt_time DESC LIMIT 1000");
                                    while ($row = $student_attempts->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="log-checkbox" data-id="<?php echo $row['id']; ?>" data-table="student_login_attempts"></td>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['identifier']); ?></td>
                                        <td><?php echo $row['success'] ? '<span class="badge bg-success">Success</span>' : '<span class="badge bg-danger">Failed</span>'; ?></td>
                                        <td><?php echo htmlspecialchars($row['ip_address'] ?? '-'); ?></td>
                                        <td><?php echo date('M d, Y H:i:s', strtotime($row['attempt_time'])); ?></td>
                                        <td><button class="btn btn-sm btn-danger" onclick="deleteSingle('student_login_attempts', <?php echo $row['id']; ?>)"><i class="fas fa-trash"></i></button></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Bulk Delete Form (hidden) -->
<form id="bulkDeleteForm" method="POST" style="display: none;">
    <input type="hidden" name="bulk_delete" value="1">
    <input type="hidden" name="table" id="bulk_table">
    <input type="hidden" name="ids" id="bulk_ids">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize DataTables for each table
    $('#table_admin_logs, #table_super_admin_logs, #table_student_login_logs, #table_admin_login_attempts, #table_student_login_attempts').each(function() {
        $(this).DataTable({
            pageLength: 25,
            order: [[1, 'desc']],
            language: { search: "Search:", lengthMenu: "Show _MENU_ entries", info: "Showing _START_ to _END_ of _TOTAL_ entries" },
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'print']
        });
    });

    // Click on stats card to switch tab
    $('.stats-card').click(function() {
        var tabId = $(this).data('tab');
        $('.nav-link[data-bs-target="#' + tabId + '"]').tab('show');
    });

    // Select/Deselect all checkboxes for a table
    $('.select-all-checkbox').change(function() {
        var table = $(this).data('table');
        var isChecked = $(this).is(':checked');
        $('.log-checkbox[data-table="' + table + '"]').prop('checked', isChecked);
    });

    // Update select-all checkbox when individual checkboxes change
    $(document).on('change', '.log-checkbox', function() {
        var table = $(this).data('table');
        var total = $('.log-checkbox[data-table="' + table + '"]').length;
        var checked = $('.log-checkbox[data-table="' + table + '"]:checked').length;
        $('.select-all-checkbox[data-table="' + table + '"]').prop('checked', total === checked && total > 0);
    });
});

// Delete single log entry
function deleteSingle(table, id) {
    Swal.fire({
        title: 'Delete Log Entry?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'activity_logs.php?delete_single=1&table=' + table + '&id=' + id;
        }
    });
}

// Confirm bulk delete
function confirmBulkDelete(table) {
    var selected = [];
    $('.log-checkbox[data-table="' + table + '"]:checked').each(function() {
        selected.push($(this).data('id'));
    });
    if (selected.length === 0) {
        Swal.fire('No Selection', 'Please select at least one log entry to delete.', 'warning');
        return;
    }
    Swal.fire({
        title: 'Bulk Delete',
        html: `You are about to delete <strong>${selected.length}</strong> log entries.<br>This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete them!'
    }).then((result) => {
        if (result.isConfirmed) {
            $('#bulk_table').val(table);
            $('#bulk_ids').val(JSON.stringify(selected));
            $('#bulkDeleteForm').submit();
        }
    });
}

// Confirm clear all logs from a table
function confirmClearAll(table) {
    let tableName = '';
    if (table === 'admin_logs') tableName = 'Admin Actions';
    else if (table === 'super_admin_logs') tableName = 'Super Admin Actions';
    else if (table === 'student_login_logs') tableName = 'Student Login History';
    else if (table === 'admin_login_attempts') tableName = 'Admin Login Attempts';
    else if (table === 'student_login_attempts') tableName = 'Student Login Attempts';
    
    Swal.fire({
        title: 'Clear All Logs?',
        html: `You are about to delete <strong>ALL</strong> entries from <strong>${tableName}</strong>.<br>This action is permanent and cannot be undone!`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, clear all!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'activity_logs.php?clear_all=1&table=' + table;
        }
    });
}

<?php if (isset($_SESSION['toast_message'])): ?>
Swal.fire({
    icon: '<?php echo $_SESSION['toast_type'] == 'success' ? 'success' : 'error'; ?>',
    title: '<?php echo $_SESSION['toast_type'] == 'success' ? 'Success!' : 'Error!'; ?>',
    text: '<?php echo htmlspecialchars($_SESSION['toast_message']); ?>',
    confirmButtonColor: '#3B9DB3',
    timer: 3000
});
<?php unset($_SESSION['toast_message']); unset($_SESSION['toast_type']); endif; ?>
</script>

<?php include '../controller/footer.php'; ?>
</body>
</html>