<?php
$env = parse_ini_file(__DIR__ . '/../.env');

$conn = mysqli_connect(
    $env['DB_HOST'],
    $env['DB_USER'],
    $env['DB_PASS'],
    $env['DB_NAME']
);

if (!$conn) {
    die("Database connection failed");
}

// Ensure session is started so we can detect current admin's school
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
