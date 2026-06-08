<?php
// super/cron_backup.php - Cron job entry point for automated backups
// For localhost development, authentication is bypassed.

// Allow execution from command line OR localhost without key
$is_cli = php_sapi_name() === 'cli';
$is_localhost = isset($_SERVER['REMOTE_ADDR']) && ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1');

if (!$is_cli && !$is_localhost) {
    // Optional: still allow if secret key is provided
    define('CRON_SECRET', 'muyovozi_backup_2026');
    if (!isset($_GET['key']) || $_GET['key'] !== CRON_SECRET) {
        die("Unauthorized access");
    }
}

// Load database connection
require_once '../controller/db_connect.php';

$backup_dir = '../backups/';
if (!file_exists($backup_dir)) mkdir($backup_dir, 0755, true);

// Functions (same as before)
function createDatabaseBackup($conn, $backup_dir, $compress = true) {
    $dbname = DB_NAME;
    $dbuser = DB_USER;
    $dbpass = DB_PASS;
    $dbhost = DB_HOST;
    
    $filename = 'muyovozi_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . $filename;
    
    // Try mysqldump (adjust path if needed, e.g., 'C:\xampp\mysql\bin\mysqldump' on Windows)
    $mysqldump_path = 'mysqldump';
    $cmd = sprintf(
        '%s --host=%s --user=%s %s --no-tablespaces --skip-lock-tables --skip-add-locks --complete-insert --extended-insert=FALSE 2>&1 > %s',
        escapeshellcmd($mysqldump_path),
        escapeshellarg($dbhost),
        escapeshellarg($dbuser),
        escapeshellarg($dbname),
        escapeshellarg($filepath)
    );
    // For no password, remove --password=... part
    if (!empty($dbpass)) {
        $cmd = str_replace('--user=' . escapeshellarg($dbuser), '--user=' . escapeshellarg($dbuser) . ' --password=' . escapeshellarg($dbpass), $cmd);
    }
    
    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);
    
    if ($return_var !== 0 || !file_exists($filepath) || filesize($filepath) < 100) {
        if (file_exists($filepath)) unlink($filepath);
        return phpBackup($conn, $backup_dir, $filename);
    }
    
    if ($compress && function_exists('gzencode')) {
        $gz_filename = $filename . '.gz';
        $gz_filepath = $backup_dir . $gz_filename;
        $sql_content = file_get_contents($filepath);
        $gz_content = gzencode($sql_content, 9);
        if (file_put_contents($gz_filepath, $gz_content)) {
            unlink($filepath);
            return $gz_filename;
        }
    }
    return $filename;
}

function phpBackup($conn, $backup_dir, $filename) {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    $sql_dump = "-- Muyovozi High School Database Backup (PHP Fallback)\n";
    $sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sql_dump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql_dump .= "START TRANSACTION;\n";
    $sql_dump .= "SET time_zone = '+00:00';\n\n";
    
    foreach ($tables as $table) {
        $create_result = $conn->query("SHOW CREATE TABLE `$table`");
        $create_row = $create_result->fetch_assoc();
        $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql_dump .= $create_row['Create Table'] . ";\n\n";
        
        $data_result = $conn->query("SELECT * FROM `$table`");
        if ($data_result->num_rows > 0) {
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
    
    $filepath = $backup_dir . $filename;
    file_put_contents($filepath, $sql_dump);
    
    if (function_exists('gzencode')) {
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

function cleanupOldBackups($max_backups = 10) {
    $backup_dir = '../backups/';
    $files = glob($backup_dir . 'muyovozi_backup_*.sql.gz');
    if (count($files) > $max_backups) {
        usort($files, function($a, $b) { return filemtime($a) - filemtime($b); });
        $to_delete = array_slice($files, 0, count($files) - $max_backups);
        foreach ($to_delete as $file) unlink($file);
    }
}

function updateLastBackupTime() {
    $backup_dir = '../backups/';
    $time_file = $backup_dir . 'last_backup.txt';
    file_put_contents($time_file, time());
}

// Execute backup
$backup_file = createDatabaseBackup($conn, $backup_dir, true);
if ($backup_file) {
    cleanupOldBackups(10);
    updateLastBackupTime();
    echo "SUCCESS: Backup created: $backup_file\n";
} else {
    echo "ERROR: Failed to create backup\n";
    exit(1);
}
?>