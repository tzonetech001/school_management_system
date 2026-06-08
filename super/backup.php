<?php
// super/backup.php - Database Backup Management (Works with any db_connect.php)
session_start();

// Check if Super Admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    while (ob_get_level() > 0) ob_end_clean();
    header("Location: ../mhs/login.php");
    exit();
}

require_once '../controller/db_connect.php';

// Get super admin info
$super_id = (int)$_SESSION['super_admin_id'];
$super_sql = "SELECT * FROM super_admins WHERE id = $super_id AND status = 1";
$super_result = mysqli_query($conn, $super_sql);
$super_admin = mysqli_fetch_assoc($super_result);
if (!$super_admin) {
    session_destroy();
    header("Location: ../mhs/login.php");
    exit();
}

// Configuration
$backup_dir = '../backups/';
$auto_backup_interval = 24; // hours
$max_backups = 10; // keep last 10 backups

// Create backup directory if not exists
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// ==================== HELPER FUNCTIONS ====================
function logBackupAction($conn, $admin_id, $action, $description, $filename = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $log_sql = "INSERT INTO super_admin_logs (admin_id, action, description, target_admin_id, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, NULL, ?, ?, NOW())";
    $stmt = $conn->prepare($log_sql);
    $stmt->bind_param("issss", $admin_id, $action, $description, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
}

function getLastBackupTime() {
    $time_file = '../backups/last_backup.txt';
    if (file_exists($time_file)) {
        return (int)file_get_contents($time_file);
    }
    return 0;
}

function updateLastBackupTime($timestamp = null) {
    $time_file = '../backups/last_backup.txt';
    $time = $timestamp ?? time();
    file_put_contents($time_file, $time);
}

function cleanupOldBackups($max_backups) {
    $backup_dir = '../backups/';
    $files = glob($backup_dir . 'muyovozi_backup_*.sql.gz');
    if (count($files) > $max_backups) {
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        $to_delete = array_slice($files, 0, count($files) - $max_backups);
        foreach ($to_delete as $file) {
            unlink($file);
        }
    }
}

// ==================== BACKUP CREATION (PHP ONLY, no mysqldump) ====================
function createDatabaseBackup($conn, $backup_dir, $compress = true) {
    $filename = 'muyovozi_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . $filename;
    
    // Use PHP method (works with any MySQL connection)
    $sql_dump = "-- Muyovozi High School Database Backup\n";
    $sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql_dump .= "-- PHP Backup Method\n\n";
    $sql_dump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql_dump .= "START TRANSACTION;\n";
    $sql_dump .= "SET time_zone = '+00:00';\n\n";
    
    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    foreach ($tables as $table) {
        // Create table structure
        $create_result = $conn->query("SHOW CREATE TABLE `$table`");
        $create_row = $create_result->fetch_assoc();
        $sql_dump .= "-- Table structure for table `$table`\n";
        $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql_dump .= $create_row['Create Table'] . ";\n\n";
        
        // Dump data
        $data_result = $conn->query("SELECT * FROM `$table`");
        if ($data_result->num_rows > 0) {
            $sql_dump .= "-- Dumping data for table `$table`\n";
            while ($row = $data_result->fetch_assoc()) {
                $columns = array_keys($row);
                $escaped_values = array_map(function($value) use ($conn) {
                    if ($value === null) return 'NULL';
                    return "'" . mysqli_real_escape_string($conn, $value) . "'";
                }, array_values($row));
                $sql_dump .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $escaped_values) . ");\n";
            }
            $sql_dump .= "\n";
        }
        $data_result->free();
    }
    
    $sql_dump .= "COMMIT;\n";
    
    // Write to file
    file_put_contents($filepath, $sql_dump);
    
    // Compress if enabled
    if ($compress && function_exists('gzencode')) {
        $gz_filename = $filename . '.gz';
        $gz_filepath = $backup_dir . $gz_filename;
        $gz_content = gzencode($sql_dump, 9);
        if (file_put_contents($gz_filepath, $gz_content)) {
            unlink($filepath);
            return $gz_filename;
        }
    }
    return $filename;
}

// ==================== HANDLE ACTIONS ====================
// Manual backup
if (isset($_GET['action']) && $_GET['action'] === 'manual_backup') {
    $backup_file = createDatabaseBackup($conn, $backup_dir, true);
    if ($backup_file) {
        cleanupOldBackups($max_backups);
        updateLastBackupTime();
        logBackupAction($conn, $super_id, 'MANUAL_BACKUP', "Manual database backup created: $backup_file");
        $_SESSION['toast_message'] = "Database backup created successfully! File: $backup_file";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Failed to create database backup!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: backup.php");
    exit();
}

// Delete backup file
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $filepath = $backup_dir . $file;
    if (file_exists($filepath) && strpos($file, 'muyovozi_backup_') === 0) {
        unlink($filepath);
        logBackupAction($conn, $super_id, 'DELETE_BACKUP', "Deleted backup file: $file");
        $_SESSION['toast_message'] = "Backup file deleted!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "File not found or invalid!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: backup.php");
    exit();
}

// Download backup
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $filepath = $backup_dir . $file;
    if (file_exists($filepath) && strpos($file, 'muyovozi_backup_') === 0) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    } else {
        $_SESSION['toast_message'] = "File not found!";
        $_SESSION['toast_type'] = "error";
        header("Location: backup.php");
        exit();
    }
}

// ==================== AUTO BACKUP CHECK ====================
$last_backup = getLastBackupTime();
$auto_backup_triggered = false;
if (time() - $last_backup > $auto_backup_interval * 3600) {
    $backup_file = createDatabaseBackup($conn, $backup_dir, true);
    if ($backup_file) {
        cleanupOldBackups($max_backups);
        updateLastBackupTime();
        logBackupAction($conn, $super_id, 'AUTO_BACKUP', "Automatic 24-hour database backup created: $backup_file");
        $auto_backup_triggered = true;
        $_SESSION['toast_message'] = "Automatic backup completed: $backup_file";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Automatic backup failed!";
        $_SESSION['toast_type'] = "error";
    }
    // Refresh to avoid showing old stats
    if (!isset($_GET['no_redirect'])) {
        header("Location: backup.php?no_redirect=1");
        exit();
    }
}

// ==================== PREPARE DATA FOR DISPLAY ====================
$backup_files = glob($backup_dir . 'muyovozi_backup_*');
$backup_files = array_map('basename', $backup_files);
rsort($backup_files); // newest first

$last_backup_time = $last_backup ? date('Y-m-d H:i:s', $last_backup) : 'Never';
$next_backup_time = $last_backup ? date('Y-m-d H:i:s', $last_backup + ($auto_backup_interval * 3600)) : 'Immediately';
$time_remaining = $last_backup ? ($last_backup + ($auto_backup_interval * 3600)) - time() : 0;
$hours_remaining = floor($time_remaining / 3600);
$minutes_remaining = floor(($time_remaining % 3600) / 60);

date_default_timezone_set('Africa/Dar_es_Salaam');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <style>
        :root { --primary-color: #3B9DB3; --primary-dark: #2d7c8f; }
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 20px;
            padding: 25px 30px;
            color: white;
            margin-bottom: 30px;
        }
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
            margin-bottom: 20px;
        }
        .btn-backup {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            color: white;
            font-weight: 500;
        }
        .btn-backup:hover { opacity: 0.9; color: white; }
        .table-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
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
                    <h2><i class="fas fa-database me-2"></i> Database Backup Manager</h2>
                    <p class="mb-0">Create, download, and manage automatic database backups (every 24 hours)</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="?action=manual_backup" class="btn btn-light" id="manualBackupBtn">
                        <i class="fas fa-plus-circle me-2"></i> Manual Backup Now
                    </a>
                </div>
            </div>
        </div>

        <?php if ($auto_backup_triggered): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> Automatic backup completed successfully! (24-hour interval)
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Info Cards -->
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="info-card">
                    <h5><i class="fas fa-clock me-2 text-primary"></i> Last Backup</h5>
                    <p class="mb-1"><?php echo $last_backup_time; ?></p>
                    <?php if ($last_backup_time != 'Never'): ?>
                    <small class="text-muted"><?php echo round((time() - $last_backup) / 3600, 1); ?> hours ago</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="info-card">
                    <h5><i class="fas fa-calendar-alt me-2 text-primary"></i> Next Auto Backup</h5>
                    <p class="mb-1"><?php echo $next_backup_time; ?></p>
                    <?php if ($time_remaining > 0): ?>
                    <small class="text-muted">in <?php echo $hours_remaining; ?>h <?php echo $minutes_remaining; ?>m</small>
                    <?php else: ?>
                    <small class="text-warning">Overdue – will run on next page load</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="info-card">
                    <h5><i class="fas fa-archive me-2 text-primary"></i> Backup Settings</h5>
                    <p class="mb-0">Interval: <strong><?php echo $auto_backup_interval; ?> hours</strong></p>
                    <p class="mb-0">Keep last: <strong><?php echo $max_backups; ?> backups</strong></p>
                </div>
            </div>
        </div>

        <!-- Backup Files Table -->
        <div class="card table-card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-file-archive me-2"></i> Available Backups</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="backupTable" class="table table-hover">
                        <thead>
                            <tr><th>#</th><th>Filename</th><th>Size</th><th>Created</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($backup_files)): ?>
                            <tr><td colspan="5" class="text-center">No backups found. Click "Manual Backup Now" to create one.</td></tr>
                            <?php else: ?>
                                <?php foreach ($backup_files as $index => $file): 
                                    $filepath = $backup_dir . $file;
                                    $size = filesize($filepath);
                                    $size_mb = round($size / 1024 / 1024, 2);
                                    $date = date('Y-m-d H:i:s', filemtime($filepath));
                                    $is_gz = strpos($file, '.gz') !== false;
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><i class="fas fa-file-<?php echo $is_gz ? 'archive' : 'code'; ?> me-2 text-primary"></i> <?php echo htmlspecialchars($file); ?></td>
                                    <td><?php echo $size_mb; ?> MB</td>
                                    <td><?php echo $date; ?></td>
                                    <td>
                                        <a href="?action=download&file=<?php echo urlencode($file); ?>" class="btn btn-sm btn-success" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDelete('<?php echo htmlspecialchars($file); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle me-2"></i> Auto backup triggers automatically when you visit this page and the last backup is older than <strong><?php echo $auto_backup_interval; ?> hours</strong>. For reliable scheduled backups, set up a cron job or web cron service.
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('#backupTable').DataTable({
        pageLength: 10,
        order: [[3, 'desc']],
        language: { search: "Search:", lengthMenu: "Show _MENU_ backups", info: "Showing _START_ to _END_ of _TOTAL_ backups" }
    });
});

function confirmDelete(filename) {
    Swal.fire({
        title: 'Delete Backup?',
        text: `Are you sure you want to delete "${filename}"? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'backup.php?action=delete&file=' + encodeURIComponent(filename);
        }
    });
}

document.getElementById('manualBackupBtn')?.addEventListener('click', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Create Manual Backup?',
        text: 'This will create a full database backup. It may take a few seconds.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3B9DB3',
        confirmButtonText: 'Yes, create backup!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'backup.php?action=manual_backup';
        }
    });
});

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