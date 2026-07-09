<?php
$env = parse_ini_file(__DIR__ . '/../.env');

if (!$env || !isset($env['DB_HOST'], $env['DB_USER'], $env['DB_NAME'])) {
    throw new Exception("Database configuration missing or invalid in .env file");
}

$conn = mysqli_connect(
    $env['DB_HOST'],
    $env['DB_USER'],
    $env['DB_PASS'],
    $env['DB_NAME']
);

if (!$conn) {
    throw new Exception("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

function ensureTableExists($conn, $tableName, $createSql) {
    $escapedTable = $conn->real_escape_string($tableName);
    $checkResult = $conn->query("SHOW TABLES LIKE '$escapedTable'");
    if ($checkResult && $checkResult->num_rows === 0) {
        $conn->query($createSql);
    }
}

function ensureColumnExists($conn, $tableName, $columnName, $definition) {
    $escapedTable = $conn->real_escape_string($tableName);
    $escapedColumn = $conn->real_escape_string($columnName);
    $checkResult = $conn->query("SHOW COLUMNS FROM `{$escapedTable}` LIKE '{$escapedColumn}'");
    if ($checkResult && $checkResult->num_rows === 0) {
        $conn->query("ALTER TABLE `{$escapedTable}` ADD COLUMN `{$escapedColumn}` {$definition}");
    }
}

ensureTableExists($conn, 'generated_timetables', "CREATE TABLE IF NOT EXISTS generated_timetables (
    id INT(11) NOT NULL AUTO_INCREMENT,
    term VARCHAR(20) NOT NULL,
    year YEAR(4) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    generated_by INT(11) NOT NULL,
    generated_at DATETIME DEFAULT NULL,
    last_updated_by INT(11) DEFAULT NULL,
    last_updated_at DATETIME DEFAULT NULL,
    school_id INT(11) NOT NULL DEFAULT 1,
    break_after INT(11) DEFAULT 0,
    break_length INT(11) DEFAULT 30,
    start_time VARCHAR(10) DEFAULT '08:00',
    session_length INT(11) DEFAULT 40,
    sessions_per_day INT(11) DEFAULT 6,
    days VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_school_id (school_id),
    KEY idx_term_year (term, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

ensureTableExists($conn, 'subject_teacher_assignments', "CREATE TABLE IF NOT EXISTS subject_teacher_assignments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    teacher_id INT(11) NOT NULL,
    subject VARCHAR(20) NOT NULL,
    form_level ENUM('Form Five','Form Six') NOT NULL,
    academic_year YEAR(4) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    can_enter_results TINYINT(1) DEFAULT 1,
    assigned_by INT(11) NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    school_id INT(11) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY unique_assignment (teacher_id, subject, form_level, academic_year),
    KEY idx_school_id (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

ensureTableExists($conn, 'theme_settings', "CREATE TABLE IF NOT EXISTS theme_settings (
    id INT(11) NOT NULL AUTO_INCREMENT,
    admin_id INT(11) NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    school_id INT(11) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY unique_admin_setting (admin_id, setting_key),
    KEY idx_school_id (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

ensureTableExists($conn, 'user_preferences', "CREATE TABLE IF NOT EXISTS user_preferences (
    id INT(11) NOT NULL AUTO_INCREMENT,
    admin_id INT(11) NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    school_id INT(11) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY unique_admin_preference (admin_id, preference_key),
    KEY idx_school_id (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

ensureTableExists($conn, 'fee_settings', "CREATE TABLE IF NOT EXISTS fee_settings (
    id INT(11) NOT NULL AUTO_INCREMENT,
    school_id INT(11) NOT NULL DEFAULT 1,
    academic_year VARCHAR(20) NOT NULL,
    total_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_school_id (school_id),
    KEY idx_academic_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

ensureTableExists($conn, 'student_payments', "CREATE TABLE IF NOT EXISTS student_payments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    student_id INT(11) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
    reference_number VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    recorded_by INT(11) NOT NULL,
    school_id INT(11) NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_id (student_id),
    KEY idx_school_id (school_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

ensureColumnExists($conn, 'generated_timetables', 'signature', "VARCHAR(64) DEFAULT NULL");
ensureColumnExists($conn, 'fee_settings', 'academic_year', "VARCHAR(20) NOT NULL");
ensureColumnExists($conn, 'fee_settings', 'total_fee', "DECIMAL(12,2) NOT NULL DEFAULT 0.00");
ensureColumnExists($conn, 'fee_settings', 'school_id', "INT(11) NOT NULL DEFAULT 1");
ensureColumnExists($conn, 'student_payments', 'student_id', "INT(11) NOT NULL");
ensureColumnExists($conn, 'student_payments', 'amount', "DECIMAL(12,2) NOT NULL DEFAULT 0.00");
ensureColumnExists($conn, 'student_payments', 'payment_date', "DATE NOT NULL");
ensureColumnExists($conn, 'student_payments', 'payment_method', "VARCHAR(50) NOT NULL DEFAULT 'cash'");
ensureColumnExists($conn, 'student_payments', 'reference_number', "VARCHAR(100) DEFAULT NULL");
ensureColumnExists($conn, 'student_payments', 'notes', "TEXT DEFAULT NULL");
ensureColumnExists($conn, 'student_payments', 'recorded_by', "INT(11) NOT NULL");
ensureColumnExists($conn, 'student_payments', 'school_id', "INT(11) NOT NULL DEFAULT 1");
ensureColumnExists($conn, 'student_payments', 'status', "VARCHAR(20) NOT NULL DEFAULT 'completed'");

// Older installs created student_payments with a non-auto-increment primary key,
// causing "Duplicate entry '0' for key 'PRIMARY'" on insert. Repair it once.
$pk_check = $conn->query("SHOW COLUMNS FROM `student_payments` LIKE 'id'");
if ($pk_check && ($pk_col = $pk_check->fetch_assoc()) && stripos($pk_col['Extra'], 'auto_increment') === false) {
    // Clear any stray id=0 row so the ALTER can succeed, then enable auto-increment.
    $conn->query("DELETE FROM `student_payments` WHERE id = 0");
    $conn->query("ALTER TABLE `student_payments` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
}

// Configure session to use root path so cookies work across all directories
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
// Start session if not already started (avoid "session already started" warnings)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine current school id for scoping queries. For super-admins keep null.
$current_school_id = null;
if (isset($_SESSION['admin_id'])) {
    $admin_id = (int)$_SESSION['admin_id'];
    $school_sql = "SELECT school_id FROM admins WHERE id = ?";
    if ($stmt = $conn->prepare($school_sql)) {
        $stmt->bind_param('i', $admin_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $current_school_id = isset($row['school_id']) ? (int)$row['school_id'] : null;
        $stmt->close();
    }
} elseif (isset($_SESSION['super_admin_id'])) {
    // super admin can see all schools
    $current_school_id = null;
}
