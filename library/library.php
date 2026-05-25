<?php
// library.php
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
    if ($role_id == 1 || $role_id == 2 || $role_id == 3 || $role_id == 13) { // Head Master or Second Master
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view staff members.";
    header("Location: ../404.php");
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

// Load user's theme settings
$colors = [];
$preferences = [];

if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    
    // Get theme colors
    $color_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
    $color_result = mysqli_query($conn, $color_query);
    if ($color_result) {
        while ($row = mysqli_fetch_assoc($color_result)) {
            $colors[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    // Get preferences
    $pref_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id";
    $pref_result = mysqli_query($conn, $pref_query);
    if ($pref_result) {
        while ($row = mysqli_fetch_assoc($pref_result)) {
            $preferences[$row['preference_key']] = $row['preference_value'];
        }
    }
}

// Default theme colors
$default_colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
    'primary_light' => '#8bc5d6',
    'light' => '#f8f9fa',
    'white' => '#ffffff',
    'gray' => '#e9ecef',
    'text' => '#333333',
    'text_light' => '#666666',
    'border' => '#e0e0e0',
    'success' => '#28a745',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#17a2b8',
    'coral' => '#FF7F50',
    'forest_green' => '#2E7D32',
    'lime_green' => '#63E07E',
    'sky_blue' => '#66d9ff',
    'aqua_blue' => '#4dd2ff'
];

// Merge colors with defaults
foreach ($default_colors as $key => $value) {
    if (!isset($colors[$key])) {
        $colors[$key] = $value;
    }
}

// Set default preferences
$pref_defaults = [
    'background_opacity' => '65',
    'animations' => '1',
    'font_size' => '16',
    'compact_mode' => '0',
    'show_icons' => '1',
    'background_option' => 'image',
    'sidebar_collapsed' => '0',
    'animation_speed' => 'normal'
];

foreach ($pref_defaults as $key => $default) {
    if (!isset($preferences[$key])) {
        $preferences[$key] = $default;
    }
}

// Font size mapping
$font_size_map = [
    '10' => '10px',
    '12' => '12px',
    '14' => '14px',
    '16' => '16px',
    '18' => '18px'
];
$font_size_value = isset($font_size_map[$preferences['font_size']]) ? 
    $font_size_map[$preferences['font_size']] : '16px';

// Animation speed mapping
$animation_speeds = [
    'slow' => '0.5s',
    'normal' => '0.3s',
    'fast' => '0.15s'
];
$animation_duration = isset($animation_speeds[$preferences['animation_speed']]) ? 
    $animation_speeds[$preferences['animation_speed']] : '0.3s';

// Function to check if a user (staff or student) exists
function checkUserExists($conn, $type, $id) {
    if (empty($id) || !is_numeric($id)) {
        return false;
    }
    
    $id = (int)$id;
    
    if ($type == 'staff') {
        $sql = "SELECT COUNT(*) as count FROM admins WHERE id = $id AND status = 1";
    } else {
        $sql = "SELECT COUNT(*) as count FROM students WHERE id = $id AND status = 1";
    }
    
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log("SQL Error in checkUserExists: " . mysqli_error($conn));
        return false;
    }
    
    $row = mysqli_fetch_assoc($result);
    return $row && $row['count'] > 0;
}

// Function to get user details
function getUserDetails($conn, $type, $id) {
    if (empty($id) || !is_numeric($id)) {
        return null;
    }
    
    $id = (int)$id;
    
    if ($type == 'staff') {
        $sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, 
                       phone_number, email, status, 'staff' as user_type
                FROM admins WHERE id = $id";
    } else {
        $sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, 
                       index_number, combination, status, 'student' as user_type,
                       NULL as phone_number, NULL as email
                FROM students WHERE id = $id";
    }
    
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log("SQL Error in getUserDetails: " . mysqli_error($conn));
        return null;
    }
    
    return mysqli_fetch_assoc($result);
}

// Function to get user's book summary
function getUserBookSummary($conn, $type, $id) {
    $id = (int)$id;
    
    // Get total books, borrowed count, and first assignment date
    $sql = "SELECT 
                COUNT(*) as total_books,
                SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as borrowed_count,
                MIN(assigned_date) as first_assignment,
                MAX(assigned_date) as last_assignment
            FROM library_assignments 
            WHERE user_type = '$type' AND user_id = $id";
    
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return [
        'total_books' => 0,
        'borrowed_count' => 0,
        'first_assignment' => null,
        'last_assignment' => null
    ];
}

// Handle form submission for adding new book assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_book_staff'])) {
        $user_type = 'staff';
        $user_id = mysqli_real_escape_string($conn, $_POST['staff_id'] ?? '');
        $book_title = mysqli_real_escape_string($conn, $_POST['book_title'] ?? '');
        $book_number = mysqli_real_escape_string($conn, $_POST['book_number'] ?? '');
        $quantity = mysqli_real_escape_string($conn, $_POST['quantity'] ?? '');
        $short_note = mysqli_real_escape_string($conn, $_POST['short_note'] ?? '');
        $assigned_date = date('Y-m-d');
        
        // Validate required fields
        if (empty($user_id) || empty($book_title) || empty($book_number) || empty($quantity)) {
            $_SESSION['error'] = "Please fill all required fields!";
            header("Location: library.php");
            exit();
        }
        
        // Check if user exists
        if (!checkUserExists($conn, $user_type, $user_id)) {
            $_SESSION['error'] = "Selected staff does not exist!";
            header("Location: library.php");
            exit();
        }
        
        // Get user details
        $user = getUserDetails($conn, $user_type, $user_id);
        if (!$user) {
            $_SESSION['error'] = "Error getting user details!";
            header("Location: library.php");
            exit();
        }
        
        // Insert into library_assignments table
        $insert_sql = "INSERT INTO library_assignments 
                      (user_type, user_id, user_name, book_title, book_number, 
                       quantity, assigned_date, short_note, status, created_at) 
                      VALUES 
                      ('$user_type', $user_id, '" . mysqli_real_escape_string($conn, $user['full_name']) . "', 
                       '$book_title', '$book_number', '$quantity', 
                       '$assigned_date', '$short_note', 'borrowed', NOW())";
        
        if (mysqli_query($conn, $insert_sql)) {
            $_SESSION['success'] = "Book assigned to staff successfully!";
        } else {
            $_SESSION['error'] = "Error assigning book: " . mysqli_error($conn);
        }
        header("Location: library.php");
        exit();
    }
    
    if (isset($_POST['add_book_student'])) {
        $user_type = 'student';
        $user_id = mysqli_real_escape_string($conn, $_POST['student_id'] ?? '');
        $book_title = mysqli_real_escape_string($conn, $_POST['book_title'] ?? '');
        $book_number = mysqli_real_escape_string($conn, $_POST['book_number'] ?? '');
        $quantity = mysqli_real_escape_string($conn, $_POST['quantity'] ?? '');
        $short_note = mysqli_real_escape_string($conn, $_POST['short_note'] ?? '');
        $assigned_date = date('Y-m-d');
        
        // Validate required fields
        if (empty($user_id) || empty($book_title) || empty($book_number) || empty($quantity)) {
            $_SESSION['error'] = "Please fill all required fields!";
            header("Location: library.php");
            exit();
        }
        
        // Check if user exists
        if (!checkUserExists($conn, $user_type, $user_id)) {
            $_SESSION['error'] = "Selected student does not exist or is inactive!";
            header("Location: library.php");
            exit();
        }
        
        // Get user details
        $user = getUserDetails($conn, $user_type, $user_id);
        if (!$user) {
            $_SESSION['error'] = "Error getting user details!";
            header("Location: library.php");
            exit();
        }
        
        // Insert into library_assignments table
        $insert_sql = "INSERT INTO library_assignments 
                      (user_type, user_id, user_name, book_title, book_number, 
                       quantity, assigned_date, short_note, status, created_at) 
                      VALUES 
                      ('$user_type', $user_id, '" . mysqli_real_escape_string($conn, $user['full_name']) . "', 
                       '$book_title', '$book_number', '$quantity', 
                       '$assigned_date', '$short_note', 'borrowed', NOW())";
        
        if (mysqli_query($conn, $insert_sql)) {
            $_SESSION['success'] = "Book assigned to student successfully!";
        } else {
            $_SESSION['error'] = "Error assigning book: " . mysqli_error($conn);
        }
        header("Location: library.php");
        exit();
    }
}

// Handle delete book assignment
if (isset($_GET['delete_book'])) {
    $assignment_id = mysqli_real_escape_string($conn, $_GET['delete_book']);
    
    if (empty($assignment_id) || !is_numeric($assignment_id)) {
        $_SESSION['error'] = "Invalid assignment ID!";
        header("Location: library.php");
        exit();
    }
    
    $delete_sql = "DELETE FROM library_assignments WHERE id = $assignment_id";
    
    if (mysqli_query($conn, $delete_sql)) {
        $_SESSION['success'] = "Book assignment deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting book assignment: " . mysqli_error($conn);
    }
    header("Location: library.php");
    exit();
}

// Create library_assignments table if not exists
$create_table_sql = "
CREATE TABLE IF NOT EXISTS library_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('staff', 'student') NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    book_title VARCHAR(255) NOT NULL,
    book_number VARCHAR(50) NOT NULL,
    quantity VARCHAR(20) NOT NULL,
    assigned_date DATE NOT NULL,
    short_note TEXT,
    status ENUM('borrowed', 'returned') DEFAULT 'borrowed',
    return_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_type),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
)";
mysqli_query($conn, $create_table_sql);

// Get unique users with book assignments
$staff_users_sql = "SELECT DISTINCT user_id, user_name, user_type 
                   FROM library_assignments 
                   WHERE user_type = 'staff'
                   ORDER BY user_name";
$staff_users_result = mysqli_query($conn, $staff_users_sql);
$staff_users = [];
if ($staff_users_result && mysqli_num_rows($staff_users_result) > 0) {
    while ($row = mysqli_fetch_assoc($staff_users_result)) {
        $user_details = getUserDetails($conn, 'staff', $row['user_id']);
        $book_summary = getUserBookSummary($conn, 'staff', $row['user_id']);
        $staff_users[] = [
            'user_id' => $row['user_id'],
            'user_name' => $row['user_name'],
            'user_type' => 'staff',
            'details' => $user_details,
            'summary' => $book_summary
        ];
    }
}

$student_users_sql = "SELECT DISTINCT user_id, user_name, user_type 
                     FROM library_assignments 
                     WHERE user_type = 'student'
                     ORDER BY user_name";
$student_users_result = mysqli_query($conn, $student_users_sql);
$student_users = [];
if ($student_users_result && mysqli_num_rows($student_users_result) > 0) {
    while ($row = mysqli_fetch_assoc($student_users_result)) {
        $user_details = getUserDetails($conn, 'student', $row['user_id']);
        $book_summary = getUserBookSummary($conn, 'student', $row['user_id']);
        $student_users[] = [
            'user_id' => $row['user_id'],
            'user_name' => $row['user_name'],
            'user_type' => 'student',
            'details' => $user_details,
            'summary' => $book_summary
        ];
    }
}

// Combine all users
$all_users = array_merge($staff_users, $student_users);

// Get statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT CONCAT(user_type, '-', user_id)) as total_users,
    COUNT(DISTINCT CASE WHEN user_type = 'staff' THEN CONCAT(user_type, '-', user_id) END) as staff_count,
    COUNT(DISTINCT CASE WHEN user_type = 'student' THEN CONCAT(user_type, '-', user_id) END) as student_count,
    SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as borrowed_count,
    SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_count,
    COUNT(*) as total_assignments
    FROM library_assignments";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [
    'total_users' => 0,
    'staff_count' => 0,
    'student_count' => 0,
    'borrowed_count' => 0,
    'returned_count' => 0,
    'total_assignments' => 0
];

// Get all staff for dropdown
$staff_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, phone_number, email 
              FROM admins WHERE status = 1 ORDER BY first_name, last_name";
$staff_result = mysqli_query($conn, $staff_sql);
$staff_list = [];
if ($staff_result && mysqli_num_rows($staff_result) > 0) {
    while ($row = mysqli_fetch_assoc($staff_result)) {
        $staff_list[] = $row;
    }
}

// Get all students for dropdown
$students_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, 
                        index_number, combination 
                 FROM students WHERE status = 1 ORDER BY first_name, last_name";
$students_result = mysqli_query($conn, $students_sql);
$students_list = [];
if ($students_result && mysqli_num_rows($students_result) > 0) {
    while ($row = mysqli_fetch_assoc($students_result)) {
        $students_list[] = $row;
    }
}
?>

<?php include '../controller/header.php'; ?>
<?php 
// Calculate sidebar class
$sidebarClass = ($preferences['sidebar_collapsed'] == '1') ? 'sidebar-hidden' : '';
?>
<?php include '../controller/sidebar.php'; ?>

<!-- SweetAlert2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    :root {
        --primary-color: <?php echo $colors['primary']; ?>;
        --primary-dark: <?php echo $colors['primary_dark']; ?>;
        --primary-light: <?php echo $colors['primary_light']; ?>;
        --success-color: <?php echo $colors['success']; ?>;
        --danger-color: <?php echo $colors['danger']; ?>;
        --warning-color: <?php echo $colors['warning']; ?>;
        --info-color: <?php echo $colors['info']; ?>;
        --coral-color: <?php echo $colors['coral']; ?>;
        --forest-green: <?php echo $colors['forest_green']; ?>;
        --lime-green: <?php echo $colors['lime_green']; ?>;
        --text-color: <?php echo $colors['text']; ?>;
        --text-light: <?php echo $colors['text_light']; ?>;
        --border-color: <?php echo $colors['border']; ?>;
        --white: <?php echo $colors['white']; ?>;
        --gray: <?php echo $colors['gray']; ?>;
        --font-size-base: <?php echo $font_size_value; ?>;
        --animation-duration: <?php echo $animation_duration; ?>;
        --spacing-base: <?php echo $preferences['compact_mode'] === '1' ? '0.75rem' : '1rem'; ?>;
    }

    * {
        transition: <?php echo $preferences['animations'] === '1' ? 'all var(--animation-duration) ease' : 'none'; ?>;
    }

    body {
        font-size: var(--font-size-base);
        color: var(--text-color);
    }

    <?php if ($preferences['compact_mode'] === '1'): ?>
    .card-body {
        padding: 0.75rem !important;
    }
    .btn {
        padding: 0.5rem 1rem !important;
    }
    .form-control, .form-select {
        padding: 0.375rem 0.75rem !important;
    }
    .table td, .table th {
        padding: 0.5rem !important;
    }
    <?php endif; ?>

    .main-content {
        min-height: calc(100vh - 60px);
        padding: 20px;
        transition: margin-left var(--animation-duration) ease;
        margin-top: 5px;
    }

    @media (min-width: 992px) {
        .main-content {
            margin-left: 250px;
        }
        .main-content.sidebar-hidden {
            margin-left: 0;
        }
    }

    /* Stats Cards */
    .stats-card {
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        border-radius: 15px;
        padding: 20px;
        color: white;
        animation: fadeInUp 0.6s ease-out;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    }

    .stats-card:nth-child(2) {
        background: linear-gradient(135deg, var(--coral-color), #ff6b4a);
    }
    .stats-card:nth-child(3) {
        background: linear-gradient(135deg, var(--info-color), #0f8b9f);
    }
    .stats-card:nth-child(4) {
        background: linear-gradient(135deg, var(--lime-green), var(--forest-green));
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.2);
    }

    .stats-card .stats-icon {
        position: absolute;
        right: 20px;
        top: 20px;
        font-size: 2.5rem;
        opacity: 0.3;
    }

    .stats-card h3 {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .stats-card p {
        margin-bottom: 0;
        opacity: 0.9;
    }

    /* User Type Cards */
    .user-type-card {
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        border-radius: 15px;
        overflow: hidden;
    }

    .user-type-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .user-type-card .card-body {
        padding: 1.5rem;
    }

    .user-type-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        color: white;
    }

    .user-type-icon.staff {
        background: linear-gradient(135deg, var(--info-color), var(--primary-dark));
    }

    .user-type-icon.student {
        background: linear-gradient(135deg, var(--success-color), var(--forest-green));
    }

    /* Table Styles */
    .table-container {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        background: var(--white);
    }

    .table thead th {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        font-weight: 600;
        border: none;
        padding: 15px;
    }

    .table tbody tr {
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        background-color: rgba(59, 157, 179, 0.05);
        transform: scale(1.01);
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    /* Badge Styles */
    .badge-custom {
        padding: 6px 12px;
        font-weight: 500;
        border-radius: 20px;
        letter-spacing: 0.3px;
    }

    .badge-borrowed {
        background: linear-gradient(135deg, var(--warning-color), #e0a800);
        color: #000;
    }

    .badge-returned {
        background: linear-gradient(135deg, var(--success-color), #1e7e34);
        color: white;
    }

    .badge-staff {
        background: linear-gradient(135deg, var(--info-color), var(--primary-dark));
        color: white;
    }

    .badge-student {
        background: linear-gradient(135deg, var(--success-color), var(--forest-green));
        color: white;
    }

    /* Mobile Cards */
    .mobile-user-card {
        background: var(--white);
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        animation: slideIn 0.4s ease-out;
        border-left: 5px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .mobile-user-card.staff-card {
        border-left-color: var(--info-color);
    }

    .mobile-user-card.student-card {
        border-left-color: var(--success-color);
    }

    .mobile-user-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .mobile-user-card::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .mobile-user-card:hover::after {
        opacity: 1;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Button Styles */
    .btn-primary-custom {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3);
        color: white;
    }

    .btn-outline-primary-custom {
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        background: transparent;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .btn-outline-primary-custom:hover {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3);
    }

    /* Filter Section */
    .filter-section {
        background: linear-gradient(135deg, var(--white), var(--gray));
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid var(--border-color);
    }

    /* Nav Tabs */
    .nav-tabs {
        border-bottom: 2px solid var(--border-color);
    }

    .nav-tabs .nav-link {
        border: none;
        color: var(--text-light);
        font-weight: 600;
        padding: 12px 25px;
        position: relative;
        transition: all 0.3s ease;
    }

    .nav-tabs .nav-link:after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-color);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .nav-tabs .nav-link.active {
        color: var(--primary-color);
        background: transparent;
    }

    .nav-tabs .nav-link.active:after {
        transform: scaleX(1);
    }

    .nav-tabs .nav-link:hover {
        color: var(--primary-dark);
    }

    /* Modal Styles */
    .modal-content {
        border: none;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }

    .modal-header {
        padding: 20px 25px;
        border-bottom: none;
    }

    .modal-header.bg-info {
        background: linear-gradient(135deg, var(--info-color), var(--primary-dark)) !important;
    }

    .modal-header.bg-success {
        background: linear-gradient(135deg, var(--success-color), var(--forest-green)) !important;
    }

    .modal-header .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.8;
        transition: all 0.3s ease;
    }

    .modal-header .btn-close:hover {
        opacity: 1;
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 25px;
    }

    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid var(--border-color);
    }

    /* Form Controls */
    .form-control, .form-select {
        border: 2px solid var(--border-color);
        border-radius: 10px;
        padding: 10px 15px;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(59, 157, 179, 0.25);
    }

    /* Alert Styles */
    .alert-info-custom {
        background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(23, 162, 184, 0.2));
        border-left: 5px solid var(--info-color);
        border-radius: 10px;
        color: var(--text-color);
    }

    .alert-success-custom {
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.2));
        border-left: 5px solid var(--success-color);
        border-radius: 10px;
        color: var(--text-color);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-content {
            padding: 15px;
        }
        
        .page-title {
            font-size: 1.5rem;
        }
        
        .stats-card {
            padding: 15px;
        }
        
        .stats-card h3 {
            font-size: 1.5rem;
        }
        
        .stats-card .stats-icon {
            font-size: 2rem;
        }
        
        .user-type-icon {
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }
        
        .nav-tabs .nav-link {
            padding: 8px 15px;
            font-size: 0.9rem;
        }
    }

    /* Animations */
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    .btn-pulse {
        animation: pulse 2s infinite;
    }

    /* Loading Spinner */
    .spinner-custom {
        width: 3rem;
        height: 3rem;
        border: 4px solid var(--primary-light);
        border-top-color: var(--primary-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>

<div class="main-content <?php echo $sidebarClass; ?>">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <h2 class="page-title mb-2 mb-sm-0" style="color: var(--primary-color);">
                <i class="fas fa-book me-2"></i>
                Library Management
            </h2>
            <div class="btn-group">
                <button type="button" class="btn btn-primary-custom dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-plus-circle me-2"></i><span class="d-none d-sm-inline">Assign Book</span>
                    <span class="d-sm-none">Assign</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header"><i class="fas fa-chalkboard-teacher me-2" style="color: var(--info-color);"></i>Assign to Staff</h6></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addStaffBookModal">
                        <i class="fas fa-user-tie me-2" style="color: var(--info-color);"></i>Select Staff Member
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header"><i class="fas fa-user-graduate me-2" style="color: var(--success-color);"></i>Assign to Student</h6></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addStudentBookModal">
                        <i class="fas fa-user-graduate me-2" style="color: var(--success-color);"></i>Select Student
                    </a></li>
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
            <div class="col-6 col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3><?php echo $stats['total_users']; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-hand-holding"></i>
                    </div>
                    <h3><?php echo $stats['borrowed_count']; ?></h3>
                    <p>Borrowed Books</p>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3><?php echo $stats['returned_count']; ?></h3>
                    <p>Returned Books</p>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3><?php echo $stats['total_assignments']; ?></h3>
                    <p>Total Books</p>
                </div>
            </div>
        </div>

        <!-- User Type Stats -->
        <div class="row mb-4">
            <div class="col-6">
                <div class="user-type-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="user-type-icon staff me-3">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div>
                            <h3 class="mb-0" style="color: var(--info-color);"><?php echo $stats['staff_count']; ?></h3>
                            <p class="text-muted mb-0">Staff Members</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="user-type-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="user-type-icon student me-3">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div>
                            <h3 class="mb-0" style="color: var(--success-color);"><?php echo $stats['student_count']; ?></h3>
                            <p class="text-muted mb-0">Students</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="filter-section mb-4">
            <div class="row g-2">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search" style="color: var(--primary-color);"></i>
                        </span>
                        <input type="text" id="searchInput" class="form-control border-start-0" 
                               placeholder="Search by name, ID, or combination...">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <select id="userTypeFilter" class="form-select">
                        <option value="">All Users</option>
                        <option value="staff">Staff Only</option>
                        <option value="student">Students Only</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <select id="statusFilter" class="form-select">
                        <option value="">All Status</option>
                        <option value="borrowed">Has Borrowed</option>
                        <option value="returned">All Returned</option>
                        <option value="mixed">Mixed Status</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <button type="button" class="btn btn-outline-primary-custom w-100" onclick="resetFilters()">
                        <i class="fas fa-undo me-2"></i>Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- User Type Tabs -->
        <ul class="nav nav-tabs mb-4" id="userTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>All Users
                    <span class="badge" style="background: var(--primary-color); color: white; margin-left: 8px;"><?php echo count($all_users); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button" role="tab">
                    <i class="fas fa-chalkboard-teacher me-2" style="color: var(--info-color);"></i>Staff
                    <span class="badge" style="background: var(--info-color); color: white; margin-left: 8px;"><?php echo count($staff_users); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab">
                    <i class="fas fa-user-graduate me-2" style="color: var(--success-color);"></i>Students
                    <span class="badge" style="background: var(--success-color); color: white; margin-left: 8px;"><?php echo count($student_users); ?></span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="userTabContent">
            <!-- All Users Tab -->
            <div class="tab-pane fade show active" id="all" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; border-radius: 15px 15px 0 0;">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>All Library Users</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($all_users)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-book-open fa-4x mb-3" style="color: var(--primary-light);"></i>
                                <h5 class="text-muted">No users found</h5>
                                <p class="mb-3">Start by assigning books to staff or students.</p>
                                <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addStaffBookModal">
                                    <i class="fas fa-plus-circle me-2"></i>Assign First Book
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Desktop Table View -->
                            <div class="d-none d-md-block">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="allUsersTable">
                                        <thead>
                                            <tr>
                                                <th class="px-4">#</th>
                                                <th>User Type</th>
                                                <th>Full Name</th>
                                                <th>Contact/ID</th>
                                                <th>Details</th>
                                                <th>Total Books</th>
                                                <th>Borrowed</th>
                                                <th>Last Activity</th>
                                                <th class="px-4">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($all_users as $index => $user): ?>
                                            <tr class="user-row" 
                                                data-user-type="<?php echo $user['user_type']; ?>"
                                                data-borrowed="<?php echo $user['summary']['borrowed_count'] > 0 ? 'borrowed' : 'none'; ?>">
                                                <td class="px-4"><?php echo $index + 1; ?></td>
                                                <td>
                                                    <span class="badge-custom <?php echo $user['user_type'] == 'staff' ? 'badge-staff' : 'badge-student'; ?>">
                                                        <i class="fas <?php echo $user['user_type'] == 'staff' ? 'fa-chalkboard-teacher' : 'fa-user-graduate'; ?> me-1"></i>
                                                        <?php echo ucfirst($user['user_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['user_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($user['user_type'] == 'staff'): ?>
                                                        <?php if (!empty($user['details']['phone_number'])): ?>
                                                            <i class="fas fa-phone text-muted me-1"></i>
                                                            <?php echo htmlspecialchars($user['details']['phone_number']); ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if (!empty($user['details']['index_number'])): ?>
                                                            <i class="fas fa-id-card text-muted me-1"></i>
                                                            <?php echo htmlspecialchars($user['details']['index_number']); ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['user_type'] == 'staff'): ?>
                                                        <?php if (!empty($user['details']['email'])): ?>
                                                            <small><i class="fas fa-envelope text-muted me-1"></i><?php echo htmlspecialchars($user['details']['email']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if (!empty($user['details']['combination'])): ?>
                                                            <small><i class="fas fa-layer-group text-muted me-1"></i><?php echo htmlspecialchars($user['details']['combination']); ?></small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background: var(--gray); color: var(--text-color);"><?php echo $user['summary']['total_books']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($user['summary']['borrowed_count'] > 0): ?>
                                                        <span class="badge badge-borrowed"><?php echo $user['summary']['borrowed_count']; ?> active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-returned">All returned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['summary']['last_assignment']): ?>
                                                        <small><?php echo date('d/m/Y', strtotime($user['summary']['last_assignment'])); ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">N/A</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4">
                                                    <a href="book_details.php?user_type=<?php echo $user['user_type']; ?>&user_id=<?php echo $user['user_id']; ?>" 
                                                       class="btn btn-outline-primary-custom btn-sm" title="View All Books">
                                                        <i class="fas fa-book-open me-1"></i> <span class="d-none d-lg-inline">View Books</span>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Mobile Card View -->
                            <div class="d-block d-md-none p-3">
                                <?php foreach ($all_users as $index => $user): ?>
                                <div class="mobile-user-card <?php echo $user['user_type'] == 'staff' ? 'staff-card' : 'student-card'; ?> mb-3 p-3 user-item" 
                                     data-user-type="<?php echo $user['user_type']; ?>"
                                     data-borrowed="<?php echo $user['summary']['borrowed_count'] > 0 ? 'borrowed' : 'none'; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="badge-custom <?php echo $user['user_type'] == 'staff' ? 'badge-staff' : 'badge-student'; ?> mb-2">
                                                <i class="fas <?php echo $user['user_type'] == 'staff' ? 'fa-chalkboard-teacher' : 'fa-user-graduate'; ?> me-1"></i>
                                                <?php echo ucfirst($user['user_type']); ?>
                                            </span>
                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($user['user_name']); ?></h6>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge" style="background: var(--gray); color: var(--text-color);">Total: <?php echo $user['summary']['total_books']; ?></span>
                                            <?php if ($user['summary']['borrowed_count'] > 0): ?>
                                                <span class="badge badge-borrowed d-block mt-1">
                                                    <i class="fas fa-hourglass-half me-1"></i><?php echo $user['summary']['borrowed_count']; ?> active
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="small mb-3">
                                        <?php if ($user['user_type'] == 'staff'): ?>
                                            <?php if (!empty($user['details']['phone_number'])): ?>
                                                <div><i class="fas fa-phone text-muted me-2"></i><?php echo htmlspecialchars($user['details']['phone_number']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($user['details']['email'])): ?>
                                                <div><i class="fas fa-envelope text-muted me-2"></i><?php echo htmlspecialchars($user['details']['email']); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if (!empty($user['details']['index_number'])): ?>
                                                <div><i class="fas fa-id-card text-muted me-2"></i><?php echo htmlspecialchars($user['details']['index_number']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($user['details']['combination'])): ?>
                                                <div><i class="fas fa-layer-group text-muted me-2"></i><?php echo htmlspecialchars($user['details']['combination']); ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['summary']['last_assignment']): ?>
                                            <div class="mt-2 text-muted">
                                                <i class="far fa-calendar me-2"></i>Last: <?php echo date('d/m/Y', strtotime($user['summary']['last_assignment'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <a href="book_details.php?user_type=<?php echo $user['user_type']; ?>&user_id=<?php echo $user['user_id']; ?>" 
                                       class="btn btn-primary-custom w-100">
                                        <i class="fas fa-book-open me-2"></i>View All Books
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Staff Tab -->
            <div class="tab-pane fade" id="staff" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--info-color), var(--primary-dark)); color: white; border-radius: 15px 15px 0 0;">
                        <h5 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Staff Members</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($staff_users)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chalkboard-teacher fa-4x mb-3" style="color: var(--info-color);"></i>
                                <h5 class="text-muted">No staff members found</h5>
                                <p class="mb-3">Start by assigning books to staff members.</p>
                                <button type="button" class="btn btn-info text-white" data-bs-toggle="modal" data-bs-target="#addStaffBookModal" style="background: var(--info-color); border: none;">
                                    <i class="fas fa-plus-circle me-2"></i>Assign to Staff
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Desktop Table View -->
                            <div class="d-none d-md-block">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="staffTable">
                                        <thead>
                                            <tr>
                                                <th class="px-4">#</th>
                                                <th>Full Name</th>
                                                <th>Phone Number</th>
                                                <th>Email</th>
                                                <th>Total Books</th>
                                                <th>Borrowed</th>
                                                <th>Last Activity</th>
                                                <th class="px-4">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($staff_users as $index => $user): ?>
                                            <tr>
                                                <td class="px-4"><?php echo $index + 1; ?></td>
                                                <td><strong><?php echo htmlspecialchars($user['user_name']); ?></strong></td>
                                                <td>
                                                    <?php if (!empty($user['details']['phone_number'])): ?>
                                                        <i class="fas fa-phone text-muted me-1"></i>
                                                        <?php echo htmlspecialchars($user['details']['phone_number']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($user['details']['email'])): ?>
                                                        <small><?php echo htmlspecialchars($user['details']['email']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background: var(--gray); color: var(--text-color);"><?php echo $user['summary']['total_books']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($user['summary']['borrowed_count'] > 0): ?>
                                                        <span class="badge badge-borrowed"><?php echo $user['summary']['borrowed_count']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-returned">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['summary']['last_assignment']): ?>
                                                        <small><?php echo date('d/m/Y', strtotime($user['summary']['last_assignment'])); ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">N/A</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4">
                                                    <a href="book_details.php?user_type=staff&user_id=<?php echo $user['user_id']; ?>" 
                                                       class="btn btn-outline-info btn-sm" style="border-color: var(--info-color); color: var(--info-color);">
                                                        <i class="fas fa-book-open me-1"></i>View Books
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Mobile Card View -->
                            <div class="d-block d-md-none p-3">
                                <?php foreach ($staff_users as $index => $user): ?>
                                <div class="mobile-user-card staff-card mb-3 p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($user['user_name']); ?></h6>
                                        <span class="badge" style="background: var(--gray); color: var(--text-color);"><?php echo $user['summary']['total_books']; ?> books</span>
                                    </div>
                                    
                                    <div class="small mb-3">
                                        <?php if (!empty($user['details']['phone_number'])): ?>
                                            <div><i class="fas fa-phone text-muted me-2"></i><?php echo htmlspecialchars($user['details']['phone_number']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($user['details']['email'])): ?>
                                            <div><i class="fas fa-envelope text-muted me-2"></i><?php echo htmlspecialchars($user['details']['email']); ?></div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-2">
                                            <?php if ($user['summary']['borrowed_count'] > 0): ?>
                                                <span class="badge badge-borrowed">
                                                    <i class="fas fa-hourglass-half me-1"></i><?php echo $user['summary']['borrowed_count']; ?> borrowed
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-returned">
                                                    <i class="fas fa-check-circle me-1"></i>All returned
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($user['summary']['last_assignment']): ?>
                                            <div class="mt-2 text-muted">
                                                <i class="far fa-calendar me-2"></i>Last: <?php echo date('d/m/Y', strtotime($user['summary']['last_assignment'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <a href="book_details.php?user_type=staff&user_id=<?php echo $user['user_id']; ?>" 
                                       class="btn w-100" style="border: 2px solid var(--info-color); color: var(--info-color); background: transparent;">
                                        <i class="fas fa-book-open me-2"></i>View All Books
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Students Tab -->
            <div class="tab-pane fade" id="students" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--success-color), var(--forest-green)); color: white; border-radius: 15px 15px 0 0;">
                        <h5 class="mb-0"><i class="fas fa-user-graduate me-2"></i>Students</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($student_users)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-graduate fa-4x mb-3" style="color: var(--success-color);"></i>
                                <h5 class="text-muted">No students found</h5>
                                <p class="mb-3">Start by assigning books to students.</p>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStudentBookModal" style="background: var(--success-color); border: none;">
                                    <i class="fas fa-plus-circle me-2"></i>Assign to Student
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Desktop Table View -->
                            <div class="d-none d-md-block">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="studentsTable">
                                        <thead>
                                            <tr>
                                                <th class="px-4">#</th>
                                                <th>Full Name</th>
                                                <th>Index Number</th>
                                                <th>Combination</th>
                                                <th>Total Books</th>
                                                <th>Borrowed</th>
                                                <th>Last Activity</th>
                                                <th class="px-4">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($student_users as $index => $user): ?>
                                            <tr>
                                                <td class="px-4"><?php echo $index + 1; ?></td>
                                                <td><strong><?php echo htmlspecialchars($user['user_name']); ?></strong></td>
                                                <td>
                                                    <?php if (!empty($user['details']['index_number'])): ?>
                                                        <?php echo htmlspecialchars($user['details']['index_number']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($user['details']['combination'])): ?>
                                                        <?php echo htmlspecialchars($user['details']['combination']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background: var(--gray); color: var(--text-color);"><?php echo $user['summary']['total_books']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($user['summary']['borrowed_count'] > 0): ?>
                                                        <span class="badge badge-borrowed"><?php echo $user['summary']['borrowed_count']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-returned">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['summary']['last_assignment']): ?>
                                                        <small><?php echo date('d/m/Y', strtotime($user['summary']['last_assignment'])); ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">N/A</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4">
                                                    <a href="book_details.php?user_type=student&user_id=<?php echo $user['user_id']; ?>" 
                                                       class="btn btn-outline-success btn-sm" style="border-color: var(--success-color); color: var(--success-color);">
                                                        <i class="fas fa-book-open me-1"></i>View Books
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Mobile Card View -->
                            <div class="d-block d-md-none p-3">
                                <?php foreach ($student_users as $index => $user): ?>
                                <div class="mobile-user-card student-card mb-3 p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($user['user_name']); ?></h6>
                                        <span class="badge" style="background: var(--gray); color: var(--text-color);"><?php echo $user['summary']['total_books']; ?> books</span>
                                    </div>
                                    
                                    <div class="small mb-3">
                                        <?php if (!empty($user['details']['index_number'])): ?>
                                            <div><i class="fas fa-id-card text-muted me-2"></i><?php echo htmlspecialchars($user['details']['index_number']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($user['details']['combination'])): ?>
                                            <div><i class="fas fa-layer-group text-muted me-2"></i><?php echo htmlspecialchars($user['details']['combination']); ?></div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-2">
                                            <?php if ($user['summary']['borrowed_count'] > 0): ?>
                                                <span class="badge badge-borrowed">
                                                    <i class="fas fa-hourglass-half me-1"></i><?php echo $user['summary']['borrowed_count']; ?> borrowed
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-returned">
                                                    <i class="fas fa-check-circle me-1"></i>All returned
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($user['summary']['last_assignment']): ?>
                                            <div class="mt-2 text-muted">
                                                <i class="far fa-calendar me-2"></i>Last: <?php echo date('d/m/Y', strtotime($user['summary']['last_assignment'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <a href="book_details.php?user_type=student&user_id=<?php echo $user['user_id']; ?>" 
                                       class="btn w-100" style="border: 2px solid var(--success-color); color: var(--success-color); background: transparent;">
                                        <i class="fas fa-book-open me-2"></i>View All Books
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Book to Staff Modal -->
<div class="modal fade" id="addStaffBookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--info-color), var(--primary-dark));">
                <h5 class="modal-title text-white"><i class="fas fa-chalkboard-teacher me-2"></i>Assign Book to Staff</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="addStaffBookForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="staffSelect" class="form-label">Select Staff Member *</label>
                            <select class="form-select select2-staff" id="staffSelect" name="staff_id" required>
                                <option value="">-- Select Staff Member --</option>
                                <?php foreach ($staff_list as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" 
                                        data-phone="<?php echo htmlspecialchars($staff['phone_number']); ?>"
                                        data-email="<?php echo htmlspecialchars($staff['email']); ?>">
                                    <?php echo htmlspecialchars($staff['full_name']); ?> 
                                    (<?php echo htmlspecialchars($staff['phone_number']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3" id="staffSelectedInfo" style="display: none;">
                            <div class="alert alert-info-custom p-3">
                                <i class="fas fa-info-circle me-2" style="color: var(--info-color);"></i>
                                <span id="staffSelectedDetails"></span>
                            </div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="staffBookTitle" class="form-label">Title of Book *</label>
                            <input type="text" class="form-control" id="staffBookTitle" name="book_title" 
                                   placeholder="Enter book title" required autocomplete="off">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="staffBookNumber" class="form-label">Book Number *</label>
                            <input type="text" class="form-control" id="staffBookNumber" name="book_number" 
                                   placeholder="e.g., LIB-001" required autocomplete="off">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="staffQuantity" class="form-label">Quantity *</label>
                            <input type="text" class="form-control" id="staffQuantity" name="quantity" 
                                   placeholder="e.g., 1 book, 2 copies" required autocomplete="off">
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="staffShortNote" class="form-label">Short Note (Optional)</label>
                            <textarea class="form-control" id="staffShortNote" name="short_note" rows="2" 
                                      placeholder="Any additional notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" name="add_book_staff" class="btn" style="background: var(--info-color); color: white; border: none;">
                        <i class="fas fa-save me-2"></i>Assign Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Book to Student Modal -->
<div class="modal fade" id="addStudentBookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--success-color), var(--forest-green));">
                <h5 class="modal-title text-white"><i class="fas fa-user-graduate me-2"></i>Assign Book to Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="addStudentBookForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="studentSelect" class="form-label">Select Student *</label>
                            <select class="form-select select2-student" id="studentSelect" name="student_id" required>
                                <option value="">-- Select Student --</option>
                                <?php foreach ($students_list as $student): ?>
                                <option value="<?php echo $student['id']; ?>" 
                                        data-index="<?php echo htmlspecialchars($student['index_number']); ?>"
                                        data-combination="<?php echo htmlspecialchars($student['combination']); ?>">
                                    <?php echo htmlspecialchars($student['full_name']); ?> 
                                    (<?php echo htmlspecialchars($student['index_number']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3" id="studentSelectedInfo" style="display: none;">
                            <div class="alert alert-success-custom p-3">
                                <i class="fas fa-info-circle me-2" style="color: var(--success-color);"></i>
                                <span id="studentSelectedDetails"></span>
                            </div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="studentBookTitle" class="form-label">Title of Book *</label>
                            <input type="text" class="form-control" id="studentBookTitle" name="book_title" 
                                   placeholder="Enter book title" required autocomplete="off">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="studentBookNumber" class="form-label">Book Number *</label>
                            <input type="text" class="form-control" id="studentBookNumber" name="book_number" 
                                   placeholder="e.g., LIB-001" required autocomplete="off">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="studentQuantity" class="form-label">Quantity *</label>
                            <input type="text" class="form-control" id="studentQuantity" name="quantity" 
                                   placeholder="e.g., 1 book, 2 copies" required autocomplete="off">
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="studentShortNote" class="form-label">Short Note (Optional)</label>
                            <textarea class="form-control" id="studentShortNote" name="short_note" rows="2" 
                                      placeholder="Any additional notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" name="add_book_student" class="btn" style="background: var(--success-color); color: white; border: none;">
                        <i class="fas fa-save me-2"></i>Assign Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// ============================================
// DOCUMENT READY
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    initializeSweetAlerts();
    initializeSelect2();
    initializeEventListeners();
    initializeFilters();
});

// ============================================
// INITIALIZATION FUNCTIONS
// ============================================
function initializeSweetAlerts() {
    const successMessage = document.getElementById('successMessage');
    if (successMessage) {
        const message = successMessage.getAttribute('data-message');
        Swal.fire({
            title: 'Success!',
            text: message,
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '<?php echo $colors['success']; ?>',
            timer: 3000,
            timerProgressBar: true,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            customClass: {
                popup: 'colored-toast'
            }
        });
    }
    
    const errorMessage = document.getElementById('errorMessage');
    if (errorMessage) {
        const message = errorMessage.getAttribute('data-message');
        Swal.fire({
            title: 'Error!',
            text: message,
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '<?php echo $colors['danger']; ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            customClass: {
                popup: 'colored-toast'
            }
        });
    }
}

function initializeSelect2() {
    $('.select2-staff').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: '-- Select Staff Member --',
        allowClear: true,
        dropdownParent: $('#addStaffBookModal')
    });

    $('.select2-student').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: '-- Select Student --',
        allowClear: true,
        dropdownParent: $('#addStudentBookModal')
    });
}

function initializeEventListeners() {
    // Staff select change
    $('#staffSelect').on('change', function() {
        const selected = $(this).find('option:selected');
        if (selected.val()) {
            const name = selected.text().split('(')[0].trim();
            const phone = selected.data('phone');
            const email = selected.data('email');
            
            $('#staffSelectedDetails').html(`
                <strong>${name}</strong><br>
                <small><i class="fas fa-phone me-1"></i>${phone || 'N/A'}</small><br>
                <small><i class="fas fa-envelope me-1"></i>${email || 'N/A'}</small>
            `);
            $('#staffSelectedInfo').fadeIn();
        } else {
            $('#staffSelectedInfo').fadeOut();
        }
    });

    // Student select change
    $('#studentSelect').on('change', function() {
        const selected = $(this).find('option:selected');
        if (selected.val()) {
            const name = selected.text().split('(')[0].trim();
            const index = selected.data('index');
            const combination = selected.data('combination');
            
            $('#studentSelectedDetails').html(`
                <strong>${name}</strong><br>
                <small><i class="fas fa-id-card me-1"></i>Index: ${index || 'N/A'}</small><br>
                <small><i class="fas fa-layer-group me-1"></i>Combination: ${combination || 'N/A'}</small>
            `);
            $('#studentSelectedInfo').fadeIn();
        } else {
            $('#studentSelectedInfo').fadeOut();
        }
    });

    // Modal reset on close
    $('#addStaffBookModal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
        $('#staffSelect').val(null).trigger('change');
        $('#staffSelectedInfo').fadeOut();
    });

    $('#addStudentBookModal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
        $('#studentSelect').val(null).trigger('change');
        $('#studentSelectedInfo').fadeOut();
    });

    // Form validation
    const addStaffForm = document.getElementById('addStaffBookForm');
    const addStudentForm = document.getElementById('addStudentBookForm');

    if (addStaffForm) {
        addStaffForm.addEventListener('submit', validateStaffForm);
    }

    if (addStudentForm) {
        addStudentForm.addEventListener('submit', validateStudentForm);
    }
}

function initializeFilters() {
    const searchInput = document.getElementById('searchInput');
    const userTypeFilter = document.getElementById('userTypeFilter');
    const statusFilter = document.getElementById('statusFilter');

    if (searchInput) {
        searchInput.addEventListener('keyup', debounce(handleSearch, 300));
    }

    if (userTypeFilter) {
        userTypeFilter.addEventListener('change', handleFilter);
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', handleFilter);
    }
}

// ============================================
// SEARCH AND FILTER FUNCTIONS
// ============================================
function handleSearch() {
    const searchTerm = this.value.toLowerCase();
    const userType = document.getElementById('userTypeFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    filterUsers(searchTerm, userType, status);
}

function handleFilter() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const userType = document.getElementById('userTypeFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    filterUsers(searchTerm, userType, status);
}

function filterUsers(searchTerm, userType, status) {
    // Filter desktop rows
    const rows = document.querySelectorAll('#allUsersTable tbody tr.user-row, .mobile-user-card.user-item');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const rowUserType = row.getAttribute('data-user-type');
        const rowStatus = row.getAttribute('data-borrowed');
        
        let show = true;
        
        if (searchTerm && !text.includes(searchTerm)) {
            show = false;
        }
        
        if (userType && rowUserType !== userType) {
            show = false;
        }
        
        if (status) {
            if (status === 'borrowed' && rowStatus !== 'borrowed') {
                show = false;
            } else if (status === 'returned' && rowStatus !== 'none') {
                show = false;
            }
        }
        
        if (row.tagName === 'TR') {
            row.style.display = show ? '' : 'none';
        } else {
            row.style.display = show ? 'block' : 'none';
        }
    });
}

function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('userTypeFilter').value = '';
    document.getElementById('statusFilter').value = '';
    
    document.querySelectorAll('#allUsersTable tbody tr.user-row, .mobile-user-card.user-item').forEach(el => {
        if (el.tagName === 'TR') {
            el.style.display = '';
        } else {
            el.style.display = 'block';
        }
    });
}

// ============================================
// FORM VALIDATION
// ============================================
function validateStaffForm(e) {
    const staffId = document.getElementById('staffSelect').value;
    const bookTitle = document.getElementById('staffBookTitle').value.trim();
    const bookNumber = document.getElementById('staffBookNumber').value.trim();
    const quantity = document.getElementById('staffQuantity').value.trim();
    
    const errors = [];
    
    if (!staffId) errors.push('Please select a staff member.');
    if (!bookTitle) errors.push('Book title is required.');
    else if (bookTitle.length < 3) errors.push('Book title must be at least 3 characters.');
    if (!bookNumber) errors.push('Book number is required.');
    if (!quantity) errors.push('Quantity is required.');
    
    if (errors.length > 0) {
        e.preventDefault();
        Swal.fire({
            title: 'Validation Error',
            html: errors.map(err => `<i class="fas fa-times-circle text-danger me-2"></i>${err}`).join('<br>'),
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '<?php echo $colors['danger']; ?>'
        });
        return false;
    }
    
    return true;
}

function validateStudentForm(e) {
    const studentId = document.getElementById('studentSelect').value;
    const bookTitle = document.getElementById('studentBookTitle').value.trim();
    const bookNumber = document.getElementById('studentBookNumber').value.trim();
    const quantity = document.getElementById('studentQuantity').value.trim();
    
    const errors = [];
    
    if (!studentId) errors.push('Please select a student.');
    if (!bookTitle) errors.push('Book title is required.');
    else if (bookTitle.length < 3) errors.push('Book title must be at least 3 characters.');
    if (!bookNumber) errors.push('Book number is required.');
    if (!quantity) errors.push('Quantity is required.');
    
    if (errors.length > 0) {
        e.preventDefault();
        Swal.fire({
            title: 'Validation Error',
            html: errors.map(err => `<i class="fas fa-times-circle text-danger me-2"></i>${err}`).join('<br>'),
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '<?php echo $colors['danger']; ?>'
        });
        return false;
    }
    
    return true;
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>

<?php include '../controller/footer.php'; ?>