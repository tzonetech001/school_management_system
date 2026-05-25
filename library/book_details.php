<?php
// book_details.php
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
    if ($role_id == 1 || $role_id == 2 || $role_id == 3 || $role_id == 13) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view staff members.";
    header("Location: ../404.php");
    exit();
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

// Function to check if user has any books
function getUserBooks($conn, $user_type, $user_id) {
    $user_id = (int)$user_id;
    $user_type = mysqli_real_escape_string($conn, $user_type);
    
    $sql = "SELECT * FROM library_assignments 
            WHERE user_type = '$user_type' AND user_id = $user_id 
            ORDER BY 
                CASE WHEN status = 'borrowed' THEN 0 ELSE 1 END,
                assigned_date DESC";
    $result = mysqli_query($conn, $sql);
    $books = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $books[] = $row;
        }
    }
    return $books;
}

// Function to get user details with guaranteed name field
function getUserDetails($conn, $user_type, $user_id) {
    $user_id = (int)$user_id;
    $user_type = mysqli_real_escape_string($conn, $user_type);
    
    // Initialize user array with defaults
    $user = [
        'id' => $user_id,
        'first_name' => '',
        'last_name' => '',
        'display_name' => "User #$user_id",
        'user_type' => $user_type,
        'status' => 0,
        'phone_number' => null,
        'email' => null,
        'index_number' => null,
        'combination' => null,
        'class' => null,
        'note' => null
    ];
    
    // First try to get from main tables (admins/students)
    if ($user_type == 'staff') {
        $sql = "SELECT id, 
                       first_name,
                       last_name,
                       phone_number, 
                       email, 
                       status
                FROM admins 
                WHERE id = $user_id";
    } else {
        $sql = "SELECT id, 
                       first_name,
                       last_name,
                       index_number, 
                       combination, 
                       class,
                       status
                FROM students 
                WHERE id = $user_id";
    }
    
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $db_user = mysqli_fetch_assoc($result);
        
        // Merge database values with defaults
        foreach ($db_user as $key => $value) {
            if ($value !== null) {
                $user[$key] = $value;
            }
        }
        
        // Construct display name from first and last name
        if (!empty($user['first_name']) || !empty($user['last_name'])) {
            $user['display_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        } else {
            $user['display_name'] = "User #$user_id";
        }
        
        $user['status'] = $db_user['status'] ?? 0;
        
        return $user;
    }
    
    // If not found in main tables, get from library_assignments
    $fallback_sql = "SELECT user_name, 
                            MAX(assigned_date) as last_activity,
                            COUNT(*) as total_assignments
                     FROM library_assignments 
                     WHERE user_type = '$user_type' AND user_id = $user_id 
                     GROUP BY user_name
                     ORDER BY id DESC LIMIT 1";
    $fallback_result = mysqli_query($conn, $fallback_sql);
    
    if ($fallback_result && mysqli_num_rows($fallback_result) > 0) {
        $fallback = mysqli_fetch_assoc($fallback_result);
        
        $user['display_name'] = $fallback['user_name'];
        $user['first_name'] = $fallback['user_name'];
        $user['note'] = 'User not found in main database (may have been deleted)';
        $user['status'] = 0;
        
        return $user;
    }
    
    $user['note'] = 'No records found for this user';
    return $user;
}

// Get parameters
$user_type = isset($_GET['user_type']) ? mysqli_real_escape_string($conn, $_GET['user_type']) : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Validate parameters
if (empty($user_type) || $user_id <= 0 || !in_array($user_type, ['staff', 'student'])) {
    $_SESSION['error'] = "Invalid user information provided!";
    header("Location: library.php");
    exit();
}

// Get user details
$user = getUserDetails($conn, $user_type, $user_id);

// Final safety check - ensure display_name is always set
if (!isset($user['display_name']) || empty($user['display_name'])) {
    $name_sql = "SELECT user_name FROM library_assignments 
                WHERE user_type = '$user_type' AND user_id = $user_id 
                ORDER BY id DESC LIMIT 1";
    $name_result = mysqli_query($conn, $name_sql);
    if ($name_result && mysqli_num_rows($name_result) > 0) {
        $name_row = mysqli_fetch_assoc($name_result);
        $user['display_name'] = $name_row['user_name'];
    } else {
        $user['display_name'] = $user_type . " #" . str_pad($user_id, 5, '0', STR_PAD_LEFT);
    }
}

// Get user's books
$user_books = getUserBooks($conn, $user_type, $user_id);

// Separate borrowed and returned books
$borrowed_books = array_filter($user_books, function($book) {
    return $book['status'] == 'borrowed';
});

$returned_books = array_filter($user_books, function($book) {
    return $book['status'] == 'returned';
});

// Handle form submission for adding new book
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_book'])) {
        $book_title = mysqli_real_escape_string($conn, $_POST['book_title'] ?? '');
        $book_number = mysqli_real_escape_string($conn, $_POST['book_number'] ?? '');
        $quantity = mysqli_real_escape_string($conn, $_POST['quantity'] ?? '');
        $short_note = mysqli_real_escape_string($conn, $_POST['short_note'] ?? '');
        $assigned_date = date('Y-m-d');
        
        // Validate required fields
        if (empty($book_title) || empty($book_number) || empty($quantity)) {
            $_SESSION['error'] = "Please fill all required fields!";
            header("Location: book_details.php?user_type=$user_type&user_id=$user_id");
            exit();
        }
        
        // Insert new assignment
        $insert_sql = "INSERT INTO library_assignments 
                      (user_type, user_id, user_name, book_title, book_number, 
                       quantity, assigned_date, short_note, status, created_at) 
                      VALUES 
                      ('$user_type', $user_id, '" . mysqli_real_escape_string($conn, $user['display_name']) . "', 
                       '$book_title', '$book_number', '$quantity', 
                       '$assigned_date', '$short_note', 'borrowed', NOW())";
        
        if (mysqli_query($conn, $insert_sql)) {
            $_SESSION['success'] = "Book assigned successfully!";
        } else {
            $_SESSION['error'] = "Error assigning book: " . mysqli_error($conn);
        }
        header("Location: book_details.php?user_type=$user_type&user_id=$user_id");
        exit();
    }
    
    if (isset($_POST['return_book'])) {
        $assignment_id = (int)($_POST['assignment_id'] ?? 0);
        $return_date = date('Y-m-d');
        
        if (empty($assignment_id)) {
            $_SESSION['error'] = "Invalid assignment ID!";
            header("Location: book_details.php?user_type=$user_type&user_id=$user_id");
            exit();
        }
        
        $return_sql = "UPDATE library_assignments 
                      SET status = 'returned',
                          return_date = '$return_date',
                          updated_at = NOW()
                      WHERE id = $assignment_id AND user_id = $user_id AND user_type = '$user_type'";
        
        if (mysqli_query($conn, $return_sql)) {
            $_SESSION['success'] = "Book returned successfully!";
        } else {
            $_SESSION['error'] = "Error returning book: " . mysqli_error($conn);
        }
        header("Location: book_details.php?user_type=$user_type&user_id=$user_id");
        exit();
    }
}

// Handle delete
if (isset($_GET['delete_book'])) {
    $assignment_id = (int)$_GET['delete_book'];
    
    // Check if book is returned before allowing delete
    $check_sql = "SELECT status FROM library_assignments 
                  WHERE id = $assignment_id AND user_id = $user_id AND user_type = '$user_type'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $book = mysqli_fetch_assoc($check_result);
        
        if ($book['status'] == 'returned') {
            $delete_sql = "DELETE FROM library_assignments WHERE id = $assignment_id";
            if (mysqli_query($conn, $delete_sql)) {
                $_SESSION['success'] = "Book assignment deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting book: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error'] = "Cannot delete a book that hasn't been returned! Please return the book first.";
        }
    } else {
        $_SESSION['error'] = "Book assignment not found!";
    }
    
    header("Location: book_details.php?user_type=$user_type&user_id=$user_id");
    exit();
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

<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

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
        background: <?php 
            if (isset($preferences['background_option']) && $preferences['background_option'] === 'image') {
                $opacity = isset($preferences['background_opacity']) ? $preferences['background_opacity'] / 100 : 0.65;
                echo "linear-gradient(rgba(255,255,255,{$opacity}), rgba(255,255,255,{$opacity})), url('../muyovozi.png') no-repeat center center fixed";
            } else {
                $bg_colors = [
                    'gray' => '#e9ecef',
                    'eye_care' => '#c7e9c0',
                    'milk' => '#fdf5e6',
                    'dark_light' => '#2d2d2d'
                ];
                $bg_option = isset($preferences['background_option']) ? $preferences['background_option'] : 'image';
                echo isset($bg_colors[$bg_option]) ? $bg_colors[$bg_option] : '#e9ecef';
            }
        ?>;
        background-size: <?php echo isset($preferences['background_option']) && $preferences['background_option'] === 'image' ? 'cover' : 'auto'; ?>;
        background-position: center;
        min-height: 100vh;
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

    /* Page Header */
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        animation: slideDown 0.6s ease-out;
        position: relative;
        overflow: hidden;
    }

    .page-header::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
        animation: rotate 20s linear infinite;
    }

    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .page-header h2 {
        position: relative;
        z-index: 1;
    }

    .page-header .btn-back {
        background: rgba(255,255,255,0.2);
        color: white;
        border: 2px solid white;
        transition: all 0.3s ease;
    }

    .page-header .btn-back:hover {
        background: white;
        color: var(--primary-color);
        transform: translateX(-5px);
    }

    /* User Info Card */
    .user-info-card {
        background: var(--white);
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        margin-bottom: 25px;
        overflow: hidden;
        animation: fadeInUp 0.6s ease-out;
        border: 1px solid var(--border-color);
    }

    .user-info-card .card-header {
        background: <?php echo $user_type == 'staff' ? 'linear-gradient(135deg, var(--info-color), var(--primary-dark))' : 'linear-gradient(135deg, var(--success-color), var(--forest-green))'; ?>;
        color: white;
        padding: 15px 20px;
        border-bottom: none;
    }

    .user-info-card .card-header h5 {
        margin: 0;
        font-weight: 600;
    }

    .user-info-card .card-body {
        padding: 25px;
    }

    .user-info-table {
        width: 100%;
    }

    .user-info-table th {
        width: 30%;
        color: var(--text-light);
        font-weight: 500;
        padding: 10px 0;
    }

    .user-info-table td {
        padding: 10px 0;
        color: var(--text-color);
    }

    .user-info-table td strong {
        color: <?php echo $user_type == 'staff' ? 'var(--info-color)' : 'var(--success-color)'; ?>;
        font-size: 1.1rem;
    }

    /* Stats Cards */
    .stats-card {
        background: var(--white);
        border-radius: 15px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        animation: fadeInUp 0.6s ease-out;
        border: 1px solid var(--border-color);
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stats-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 1.8rem;
    }

    .stats-icon.borrowed {
        background: linear-gradient(135deg, var(--warning-color), #e0a800);
        color: white;
    }

    .stats-icon.returned {
        background: linear-gradient(135deg, var(--success-color), #1e7e34);
        color: white;
    }

    .stats-card h3 {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 5px;
        color: var(--text-color);
    }

    .stats-card p {
        color: var(--text-light);
        margin-bottom: 0;
    }

    /* Alert Styles */
    .alert-custom {
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 20px;
        border-left: 5px solid transparent;
        animation: slideInRight 0.5s ease-out;
    }

    .alert-warning-custom {
        background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.2));
        border-left-color: var(--warning-color);
        color: var(--text-color);
    }

    .alert-info-custom {
        background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(23, 162, 184, 0.2));
        border-left-color: var(--info-color);
        color: var(--text-color);
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Filter Section */
    .filter-section {
        background: var(--white);
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        margin-bottom: 25px;
        border: 1px solid var(--border-color);
    }

    /* Table Styles */
    .table-container {
        background: var(--white);
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        margin-bottom: 25px;
    }

    .table thead th {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        font-weight: 600;
        border: none;
        padding: 15px;
        white-space: nowrap;
    }

    .table tbody tr {
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        background-color: rgba(59, 157, 179, 0.05);
        transform: scale(1.01);
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .table tbody td {
        padding: 15px;
        vertical-align: middle;
        border-bottom: 1px solid var(--border-color);
    }

    /* Badge Styles */
    .badge-custom {
        padding: 8px 12px;
        font-weight: 500;
        border-radius: 20px;
        letter-spacing: 0.3px;
        display: inline-block;
    }

    .badge-borrowed {
        background: linear-gradient(135deg, var(--warning-color), #e0a800);
        color: #000;
    }

    .badge-returned {
        background: linear-gradient(135deg, var(--success-color), #1e7e34);
        color: white;
    }

    .badge-status {
        padding: 6px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
    }

    .badge-active {
        background: linear-gradient(135deg, var(--success-color), #1e7e34);
        color: white;
    }

    .badge-inactive {
        background: linear-gradient(135deg, #6c757d, #5a6268);
        color: white;
    }

    /* Mobile Cards */
    .mobile-book-card {
        background: var(--white);
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        margin-bottom: 15px;
        padding: 15px;
        animation: slideInUp 0.5s ease-out;
        border-left: 5px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .mobile-book-card.borrowed-card {
        border-left-color: var(--warning-color);
    }

    .mobile-book-card.returned-card {
        border-left-color: var(--success-color);
    }

    .mobile-book-card::after {
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

    .mobile-book-card:hover::after {
        opacity: 1;
    }

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .mobile-book-card .book-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: <?php echo $user_type == 'staff' ? 'var(--info-color)' : 'var(--success-color)'; ?>;
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

    .btn-outline-info {
        border: 2px solid var(--info-color);
        color: var(--info-color);
    }

    .btn-outline-info:hover {
        background: var(--info-color);
        color: white;
    }

    .btn-outline-success {
        border: 2px solid var(--success-color);
        color: var(--success-color);
    }

    .btn-outline-success:hover {
        background: var(--success-color);
        color: white;
    }

    .btn-outline-danger {
        border: 2px solid var(--danger-color);
        color: var(--danger-color);
    }

    .btn-outline-danger:hover {
        background: var(--danger-color);
        color: white;
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

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 50px 20px;
    }

    .empty-state i {
        font-size: 4rem;
        color: var(--primary-light);
        margin-bottom: 20px;
    }

    .empty-state h5 {
        color: var(--text-light);
        margin-bottom: 10px;
    }

    .empty-state p {
        color: var(--text-light);
        margin-bottom: 20px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-content {
            padding: 15px;
        }
        
        .page-header h2 {
            font-size: 1.3rem;
        }
        
        .user-info-table th {
            width: 40%;
        }
        
        .stats-card {
            padding: 15px;
        }
        
        .stats-card h3 {
            font-size: 1.5rem;
        }
        
        .stats-icon {
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }
    }

    /* Print Styles */
    @media print {
        .btn-group, .btn, .modal, .no-print {
            display: none !important;
        }
        
        .main-content {
            margin-left: 0 !important;
            padding: 0;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        
        .table {
            border-collapse: collapse !important;
        }
        
        .table td, .table th {
            background-color: white !important;
            color: black !important;
        }
    }
</style>

<div class="main-content <?php echo $sidebarClass; ?>">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center mb-2 mb-sm-0">
                    <a href="library.php" class="btn btn-back me-3">
                        <i class="fas fa-arrow-left"></i> <span class="d-none d-sm-inline">Back to Library</span>
                    </a>
                    <h2 class="mb-0">
                        <i class="fas <?php echo $user_type == 'staff' ? 'fa-chalkboard-teacher' : 'fa-user-graduate'; ?> me-2"></i>
                        Book Details
                    </h2>
                </div>
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addBookModal">
                    <i class="fas fa-plus-circle me-2"></i><span class="d-none d-sm-inline">Assign New Book</span>
                    <span class="d-sm-none">Assign</span>
                </button>
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

        <!-- User Information Card -->
        <div class="user-info-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    User Information: <?php echo htmlspecialchars($user['display_name']); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="user-info-table">
                            <tr>
                                <th>Full Name:</th>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['display_name']); ?></strong>
                                    <?php if (isset($user['status']) && $user['status'] == 0): ?>
                                        <span class="badge-custom badge-inactive ms-2">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>User Type:</th>
                                <td>
                                    <span class="badge-custom <?php echo $user_type == 'staff' ? 'badge-staff' : 'badge-student'; ?>" 
                                          style="background: <?php echo $user_type == 'staff' ? 'var(--info-color)' : 'var(--success-color)'; ?>; color: white;">
                                        <i class="fas <?php echo $user_type == 'staff' ? 'fa-chalkboard-teacher' : 'fa-user-graduate'; ?> me-1"></i>
                                        <?php echo ucfirst($user_type); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php if ($user_type == 'staff'): ?>
                                <?php if (!empty($user['phone_number'])): ?>
                                <tr>
                                    <th>Phone Number:</th>
                                    <td>
                                        <i class="fas fa-phone text-muted me-2"></i>
                                        <a href="tel:<?php echo htmlspecialchars($user['phone_number']); ?>" style="color: var(--info-color);">
                                            <?php echo htmlspecialchars($user['phone_number']); ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($user['email'])): ?>
                                <tr>
                                    <th>Email:</th>
                                    <td>
                                        <i class="fas fa-envelope text-muted me-2"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" style="color: var(--info-color);">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($user['index_number'])): ?>
                                <tr>
                                    <th>Index Number:</th>
                                    <td>
                                        <i class="fas fa-id-card text-muted me-2"></i>
                                        <?php echo htmlspecialchars($user['index_number']); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($user['class'])): ?>
                                <tr>
                                    <th>Class:</th>
                                    <td>
                                        <i class="fas fa-graduation-cap text-muted me-2"></i>
                                        <?php echo htmlspecialchars($user['class']); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($user['combination'])): ?>
                                <tr>
                                    <th>Combination:</th>
                                    <td>
                                        <i class="fas fa-layer-group text-muted me-2"></i>
                                        <?php echo htmlspecialchars($user['combination']); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="stats-icon borrowed">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <h3><?php echo count($borrowed_books); ?></h3>
                                    <p>Borrowed Books</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="stats-icon returned">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <h3><?php echo count($returned_books); ?></h3>
                                    <p>Returned Books</p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (isset($user['note']) && !empty($user['note'])): ?>
                        <div class="alert-custom alert-warning-custom mt-3">
                            <i class="fas fa-exclamation-triangle me-2" style="color: var(--warning-color);"></i>
                            <small><?php echo htmlspecialchars($user['note']); ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="filter-section">
            <div class="row g-2">
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search" style="color: var(--primary-color);"></i>
                        </span>
                        <input type="text" id="searchBooks" class="form-control border-start-0" placeholder="Search books...">
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4">
                    <select id="statusFilter" class="form-select">
                        <option value="all">All Books</option>
                        <option value="borrowed">Borrowed Only</option>
                        <option value="returned">Returned Only</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <button type="button" class="btn btn-outline-primary-custom w-100" onclick="resetFilters()">
                        <i class="fas fa-undo me-2"></i>Reset Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Books List -->
        <div class="table-container">
            <div class="card-header" style="background: linear-gradient(135deg, <?php echo $user_type == 'staff' ? 'var(--info-color)' : 'var(--success-color)'; ?>, var(--primary-dark)); color: white; padding: 15px 20px;">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>
                    Books Assigned to <?php echo htmlspecialchars($user['display_name']); ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($user_books)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h5>No books assigned yet</h5>
                        <p>Click the "Assign New Book" button to assign a book to this user.</p>
                        <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addBookModal">
                            <i class="fas fa-plus-circle me-2"></i>Assign First Book
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Desktop Table View -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="booksTable">
                                <thead>
                                    <tr>
                                        <th>S/N</th>
                                        <th>Book Title</th>
                                        <th>Book No.</th>
                                        <th>Quantity</th>
                                        <th>Status</th>
                                        <th>Assigned Date</th>
                                        <th>Return Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_books as $index => $book): ?>
                                    <tr class="book-row" data-status="<?php echo $book['status']; ?>">
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <strong style="color: <?php echo $user_type == 'staff' ? 'var(--info-color)' : 'var(--success-color)'; ?>;">
                                                <?php echo htmlspecialchars($book['book_title']); ?>
                                            </strong>
                                            <?php if (!empty($book['short_note'])): ?>
                                                <i class="fas fa-sticky-note text-muted ms-2" 
                                                   title="<?php echo htmlspecialchars($book['short_note']); ?>"
                                                   style="cursor: help;"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($book['book_number']); ?></td>
                                        <td><?php echo htmlspecialchars($book['quantity']); ?></td>
                                        <td>
                                            <span class="badge-custom <?php echo $book['status'] == 'borrowed' ? 'badge-borrowed' : 'badge-returned'; ?>">
                                                <?php echo ucfirst($book['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($book['assigned_date'])); ?></td>
                                        <td>
                                            <?php echo $book['return_date'] ? date('d/m/Y', strtotime($book['return_date'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-info view-book" 
                                                        data-book-id="<?php echo $book['id']; ?>"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($book['status'] == 'borrowed'): ?>
                                                    <button type="button" class="btn btn-outline-success return-book" 
                                                            data-book-id="<?php echo $book['id']; ?>"
                                                            data-book-title="<?php echo htmlspecialchars($book['book_title']); ?>"
                                                            title="Return Book">
                                                        <i class="fas fa-undo-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($book['status'] == 'returned'): ?>
                                                    <button type="button" class="btn btn-outline-danger delete-book" 
                                                            data-book-id="<?php echo $book['id']; ?>"
                                                            data-book-title="<?php echo htmlspecialchars($book['book_title']); ?>"
                                                            title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="d-block d-md-none p-3">
                        <div id="mobileBookList">
                            <?php foreach ($user_books as $index => $book): ?>
                            <div class="mobile-book-card <?php echo $book['status'] == 'borrowed' ? 'borrowed-card' : 'returned-card'; ?> book-item" 
                                 data-status="<?php echo $book['status']; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="book-title mb-0">
                                        <?php echo htmlspecialchars($book['book_title']); ?>
                                    </h6>
                                    <span class="badge-custom <?php echo $book['status'] == 'borrowed' ? 'badge-borrowed' : 'badge-returned'; ?>">
                                        <?php echo ucfirst($book['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="small mb-3">
                                    <div class="mb-1">
                                        <i class="fas fa-hashtag text-muted me-2"></i>
                                        <strong>Book No:</strong> <?php echo htmlspecialchars($book['book_number']); ?>
                                    </div>
                                    <div class="mb-1">
                                        <i class="fas fa-cubes text-muted me-2"></i>
                                        <strong>Quantity:</strong> <?php echo htmlspecialchars($book['quantity']); ?>
                                    </div>
                                    <div class="mb-1">
                                        <i class="far fa-calendar text-muted me-2"></i>
                                        <strong>Assigned:</strong> <?php echo date('d/m/Y', strtotime($book['assigned_date'])); ?>
                                    </div>
                                    <?php if ($book['return_date']): ?>
                                    <div class="mb-1">
                                        <i class="far fa-calendar-check text-muted me-2"></i>
                                        <strong>Returned:</strong> <?php echo date('d/m/Y', strtotime($book['return_date'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($book['short_note'])): ?>
                                    <div class="mt-2 p-2 rounded" style="background: var(--gray);">
                                        <i class="fas fa-sticky-note text-muted me-2"></i>
                                        <em><?php echo htmlspecialchars($book['short_note']); ?></em>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="btn-group w-100" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-info view-book flex-fill" 
                                            data-book-id="<?php echo $book['id']; ?>">
                                        <i class="fas fa-eye"></i> <span class="d-none d-sm-inline">View</span>
                                    </button>
                                    
                                    <?php if ($book['status'] == 'borrowed'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success return-book flex-fill" 
                                                data-book-id="<?php echo $book['id']; ?>"
                                                data-book-title="<?php echo htmlspecialchars($book['book_title']); ?>">
                                            <i class="fas fa-undo-alt"></i> <span class="d-none d-sm-inline">Return</span>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($book['status'] == 'returned'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-book flex-fill" 
                                                data-book-id="<?php echo $book['id']; ?>"
                                                data-book-title="<?php echo htmlspecialchars($book['book_title']); ?>">
                                            <i class="fas fa-trash"></i> <span class="d-none d-sm-inline">Delete</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header <?php echo $user_type == 'staff' ? 'bg-info' : 'bg-success'; ?>">
                <h5 class="modal-title text-white">
                    <i class="fas fa-plus-circle me-2"></i>
                    Assign New Book to <?php echo htmlspecialchars($user['display_name']); ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="addBookForm">
                <div class="modal-body">
                    <div class="alert alert-info-custom mb-3">
                        <i class="fas fa-info-circle me-2" style="color: var(--info-color);"></i>
                        Assigning book to: <strong><?php echo htmlspecialchars($user['display_name']); ?></strong>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="bookTitle" class="form-label">Title of Book *</label>
                            <input type="text" class="form-control" id="bookTitle" name="book_title" 
                                   placeholder="Enter book title" required autocomplete="off">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bookNumber" class="form-label">Book Number *</label>
                            <input type="text" class="form-control" id="bookNumber" name="book_number" 
                                   placeholder="e.g., LIB-001" required autocomplete="off">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="quantity" class="form-label">Quantity *</label>
                            <input type="text" class="form-control" id="quantity" name="quantity" 
                                   placeholder="e.g., 1 book, 2 copies" required autocomplete="off">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="shortNote" class="form-label">Short Note (Optional)</label>
                            <textarea class="form-control" id="shortNote" name="short_note" rows="3" 
                                      placeholder="Any additional notes about this assignment..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" name="add_book" class="btn <?php echo $user_type == 'staff' ? 'btn-info' : 'btn-success'; ?> text-white">
                        <i class="fas fa-save me-2"></i>Assign Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Return Book Modal -->
<div class="modal fade" id="returnBookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--success-color), #1e7e34);">
                <h5 class="modal-title text-white"><i class="fas fa-undo-alt me-2"></i>Return Book</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="returnBookForm">
                <input type="hidden" name="assignment_id" id="returnAssignmentId">
                <div class="modal-body text-center">
                    <i class="fas fa-book fa-3x mb-3" style="color: var(--success-color);"></i>
                    <h5 class="mb-3">Confirm book return?</h5>
                    <p class="mb-2">Book: <strong id="returnBookTitle" style="color: var(--success-color);"></strong></p>
                    <div class="alert alert-success-custom mt-3">
                        <i class="fas fa-info-circle me-2" style="color: var(--success-color);"></i>
                        This will mark the book as returned.
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" name="return_book" class="btn" style="background: var(--success-color); color: white; border: none;">
                        <i class="fas fa-check me-2"></i>Confirm Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Book Modal -->
<div class="modal fade" id="viewBookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--info-color), var(--primary-dark));">
                <h5 class="modal-title text-white"><i class="fas fa-eye me-2"></i>Book Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewBookDetails">
                <div class="text-center py-4">
                    <div class="spinner-border" style="color: var(--primary-color);" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading book details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Book Modal -->
<div class="modal fade" id="deleteBookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--danger-color), #bd2130);">
                <h5 class="modal-title text-white"><i class="fas fa-trash me-2"></i>Delete Assignment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-exclamation-triangle fa-3x mb-3" style="color: var(--danger-color);"></i>
                <h5 class="mb-3">Delete book assignment?</h5>
                <p class="mb-2">Book: <strong id="deleteBookTitle" style="color: var(--danger-color);"></strong></p>
                <div class="alert alert-danger mt-3" style="background: rgba(220, 53, 69, 0.1); border-left: 5px solid var(--danger-color);">
                    <i class="fas fa-exclamation-circle me-2" style="color: var(--danger-color);"></i>
                    This action cannot be undone!
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <a href="#" id="confirmDeleteBook" class="btn" style="background: var(--danger-color); color: white; border: none;">
                    <i class="fas fa-trash me-2"></i>Delete
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// DOCUMENT READY
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    initializeSweetAlerts();
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

function initializeEventListeners() {
    // View book details
    document.querySelectorAll('.view-book').forEach(btn => {
        btn.addEventListener('click', function() {
            const bookId = this.getAttribute('data-book-id');
            viewBookDetails(bookId);
        });
    });
    
    // Return book
    document.querySelectorAll('.return-book').forEach(btn => {
        btn.addEventListener('click', function() {
            const bookId = this.getAttribute('data-book-id');
            const bookTitle = this.getAttribute('data-book-title');
            prepareReturnModal(bookId, bookTitle);
        });
    });
    
    // Delete book
    document.querySelectorAll('.delete-book').forEach(btn => {
        btn.addEventListener('click', function() {
            const bookId = this.getAttribute('data-book-id');
            const bookTitle = this.getAttribute('data-book-title');
            prepareDeleteModal(bookId, bookTitle);
        });
    });
    
    // Search functionality
    const searchInput = document.getElementById('searchBooks');
    if (searchInput) {
        searchInput.addEventListener('keyup', debounce(handleBookSearch, 300));
    }
    
    // Status filter
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', handleStatusFilter);
    }
    
    // Form validation
    const addBookForm = document.getElementById('addBookForm');
    if (addBookForm) {
        addBookForm.addEventListener('submit', validateAddBookForm);
    }
}

function initializeFilters() {
    const urlParams = new URLSearchParams(window.location.search);
    const filter = urlParams.get('filter');
    if (filter && document.getElementById('statusFilter')) {
        document.getElementById('statusFilter').value = filter;
        handleStatusFilter();
    }
}

// ============================================
// VIEW BOOK DETAILS
// ============================================
function viewBookDetails(bookId) {
    const detailsDiv = document.getElementById('viewBookDetails');
    detailsDiv.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" style="color: var(--primary-color);" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading book details...</p>
        </div>
    `;
    
    // Simulate loading (replace with actual AJAX call)
    setTimeout(() => {
        // This should be replaced with actual AJAX call to get_assignment.php
        const viewModal = new bootstrap.Modal(document.getElementById('viewBookModal'));
        viewModal.show();
    }, 500);
}

// ============================================
// RETURN BOOK FUNCTIONS
// ============================================
function prepareReturnModal(bookId, bookTitle) {
    document.getElementById('returnAssignmentId').value = bookId;
    document.getElementById('returnBookTitle').textContent = bookTitle;
    
    const returnModal = new bootstrap.Modal(document.getElementById('returnBookModal'));
    returnModal.show();
}

// ============================================
// DELETE BOOK FUNCTIONS
// ============================================
function prepareDeleteModal(bookId, bookTitle) {
    document.getElementById('deleteBookTitle').textContent = bookTitle;
    document.getElementById('confirmDeleteBook').href = `book_details.php?user_type=<?php echo $user_type; ?>&user_id=<?php echo $user_id; ?>&delete_book=${bookId}`;
    
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteBookModal'));
    deleteModal.show();
}

// ============================================
// SEARCH AND FILTER FUNCTIONS
// ============================================
function handleBookSearch() {
    const searchTerm = this.value.toLowerCase();
    const filterStatus = document.getElementById('statusFilter').value;
    
    // Filter desktop table
    const tableRows = document.querySelectorAll('#booksTable tbody tr');
    tableRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const status = row.getAttribute('data-status');
        
        const matchesSearch = text.includes(searchTerm);
        const matchesFilter = filterStatus === 'all' || status === filterStatus;
        
        row.style.display = matchesSearch && matchesFilter ? '' : 'none';
    });
    
    // Filter mobile cards
    const mobileCards = document.querySelectorAll('.mobile-book-card');
    mobileCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        const status = card.getAttribute('data-status');
        
        const matchesSearch = text.includes(searchTerm);
        const matchesFilter = filterStatus === 'all' || status === filterStatus;
        
        card.style.display = matchesSearch && matchesFilter ? 'block' : 'none';
    });
}

function handleStatusFilter() {
    const filterStatus = this.value;
    const searchTerm = document.getElementById('searchBooks')?.value.toLowerCase() || '';
    
    // Filter desktop table
    const tableRows = document.querySelectorAll('#booksTable tbody tr');
    tableRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const status = row.getAttribute('data-status');
        
        const matchesSearch = text.includes(searchTerm);
        const matchesFilter = filterStatus === 'all' || status === filterStatus;
        
        row.style.display = matchesSearch && matchesFilter ? '' : 'none';
    });
    
    // Filter mobile cards
    const mobileCards = document.querySelectorAll('.mobile-book-card');
    mobileCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        const status = card.getAttribute('data-status');
        
        const matchesSearch = text.includes(searchTerm);
        const matchesFilter = filterStatus === 'all' || status === filterStatus;
        
        card.style.display = matchesSearch && matchesFilter ? 'block' : 'none';
    });
}

function resetFilters() {
    document.getElementById('searchBooks').value = '';
    document.getElementById('statusFilter').value = 'all';
    
    // Show all rows
    document.querySelectorAll('#booksTable tbody tr, .mobile-book-card').forEach(el => {
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
function validateAddBookForm(e) {
    const bookTitle = document.getElementById('bookTitle').value.trim();
    const bookNumber = document.getElementById('bookNumber').value.trim();
    const quantity = document.getElementById('quantity').value.trim();
    
    const errors = [];
    
    if (!bookTitle) {
        errors.push('Book title is required.');
    } else if (bookTitle.length < 3) {
        errors.push('Book title must be at least 3 characters.');
    }
    
    if (!bookNumber) {
        errors.push('Book number is required.');
    }
    
    if (!quantity) {
        errors.push('Quantity is required.');
    }
    
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

// ============================================
// EXPORT FUNCTIONS
// ============================================
function printBooks() {
    window.print();
}

function exportBooksToCSV() {
    const table = document.getElementById('booksTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            let text = col.textContent.trim().replace(/\s+/g, ' ');
            rowData.push('"' + text + '"');
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'books_export.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<?php include '../controller/footer.php'; ?>