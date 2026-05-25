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
    $_SESSION['error'] = "You don't have permission to edit staff members.";
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

// Get all roles from database (excluding Super Admin if it exists)
$roles = [];
$roles_sql = "SELECT * FROM admin_roles WHERE role_name != 'Super Admin' ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_sql);
if ($roles_result && mysqli_num_rows($roles_result) > 0) {
    while ($row = mysqli_fetch_assoc($roles_result)) {
        $roles[] = $row;
    }
}

// Get admin ID from URL
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($edit_id <= 0) {
    $_SESSION['error'] = "Invalid teacher ID.";
    header("Location: admins.php");
    exit();
}

// Check if trying to edit self
if ($edit_id == $admin_id) {
    $_SESSION['error'] = "You cannot edit your own profile here. Please use the Profile page.";
    header("Location: admins.php");
    exit();
}

// Get admin details with current roles
$sql = "SELECT a.*, 
        GROUP_CONCAT(DISTINCT ara.role_id) as role_ids,
        ara.is_primary as primary_role_id
        FROM admins a
        LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
        WHERE a.id = ?
        GROUP BY a.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $edit_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    
    // Get current role assignments
    $current_roles = [];
    $primary_role_id = null;
    
    $role_sql = "SELECT role_id, is_primary FROM admin_role_assignments WHERE admin_id = ?";
    $role_stmt = $conn->prepare($role_sql);
    $role_stmt->bind_param("i", $edit_id);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
    
    while ($role_row = $role_result->fetch_assoc()) {
        $current_roles[] = $role_row['role_id'];
        if ($role_row['is_primary'] == 1) {
            $primary_role_id = $role_row['role_id'];
        }
    }
    
    // Check if account is locked
    function isAdminAccountLocked($conn, $admin_id) {
        $email_sql = "SELECT email FROM admins WHERE id = ?";
        $stmt = $conn->prepare($email_sql);
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $email_result = $stmt->get_result();
        $admin = $email_result->fetch_assoc();
        
        if (!$admin) return false;
        
        $sql = "SELECT * FROM admin_login_attempts 
                WHERE identifier = ?
                AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND success = 0";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $admin['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows >= 5;
    }
    
    $is_locked = isAdminAccountLocked($conn, $edit_id);
} else {
    $_SESSION['error'] = "Teacher not found.";
    header("Location: admins.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name'] ?? ''));
    $middle_name = mysqli_real_escape_string($conn, trim($_POST['middle_name'] ?? ''));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name'] ?? ''));
    $sex = mysqli_real_escape_string($conn, $_POST['sex'] ?? '');
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $check_number = mysqli_real_escape_string($conn, trim($_POST['check_number'] ?? ''));
    
    // Handle phone number - add 255 prefix
    $phone_input = mysqli_real_escape_string($conn, trim($_POST['phone_number'] ?? ''));
    $phone_number = '255' . $phone_input; // Add prefix to store full number
    
    $nida = mysqli_real_escape_string($conn, trim($_POST['nida'] ?? ''));
    $selected_roles = $_POST['roles'] ?? [];
    $primary_role = $_POST['primary_role'] ?? null;
    $status = isset($_POST['status']) ? 1 : 0;
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($sex) || empty($email) || empty($phone_input)) {
        $error = "Please fill in all required fields.";
    }
    
    // Validate NIDA (if provided)
    if (!empty($nida) && strlen($nida) !== 20) {
        $error = "NIDA number must be exactly 20 digits.";
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    }
    
    // Validate phone number format (255 followed by 9 digits = 12 characters total)
    $phone_regex = '/^255\d{9}$/';
    if (!preg_match($phone_regex, $phone_number)) {
        $error = "Invalid phone number format. Must be 255 followed by 9 digits (e.g., 255712345678).";
    }
    
    // Validate roles
    if (empty($selected_roles)) {
        $error = "Please select at least one role.";
    }
    
    if (empty($primary_role)) {
        $error = "Please select a primary role.";
    }
    
    // Check if email already exists for ANOTHER admin
    if (empty($error)) {
        $check_email_sql = "SELECT id FROM admins WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($check_email_sql);
        $stmt->bind_param("si", $email, $edit_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        if ($check_result->num_rows > 0) {
            $error = "Email already exists. Please use a different email.";
        }
    }
    
    // Check if phone already exists for ANOTHER admin
    if (empty($error)) {
        $check_phone_sql = "SELECT id FROM admins WHERE phone_number = ? AND id != ?";
        $stmt = $conn->prepare($check_phone_sql);
        $stmt->bind_param("si", $phone_number, $edit_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        if ($check_result->num_rows > 0) {
            $error = "Phone number already exists. Please use a different phone number.";
        }
    }
    
    // Check if NIDA already exists for ANOTHER admin (only if NIDA is provided)
    if (empty($error) && !empty($nida)) {
        $check_nida_sql = "SELECT id FROM admins WHERE nida = ? AND id != ?";
        $stmt = $conn->prepare($check_nida_sql);
        $stmt->bind_param("si", $nida, $edit_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        if ($check_result->num_rows > 0) {
            $error = "NIDA number already exists. Please use a different NIDA number.";
        }
    }
    
    if (empty($error)) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Handle NIDA - set to NULL if empty
            if (empty($nida) || trim($nida) === '') {
                $sql = "UPDATE admins SET 
                        first_name = ?, 
                        middle_name = ?, 
                        last_name = ?, 
                        sex = ?, 
                        email = ?, 
                        check_number = ?, 
                        phone_number = ?, 
                        nida = NULL,
                        status = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssii", $first_name, $middle_name, $last_name, $sex, $email, $check_number, $phone_number, $status, $edit_id);
            } else {
                $sql = "UPDATE admins SET 
                        first_name = ?, 
                        middle_name = ?, 
                        last_name = ?, 
                        sex = ?, 
                        email = ?, 
                        check_number = ?, 
                        phone_number = ?, 
                        nida = ?,
                        status = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssii", $first_name, $middle_name, $last_name, $sex, $email, $check_number, $phone_number, $nida, $status, $edit_id);
            }
            
            if ($stmt->execute()) {
                // Delete existing role assignments
                $delete_roles_sql = "DELETE FROM admin_role_assignments WHERE admin_id = ?";
                $delete_stmt = $conn->prepare($delete_roles_sql);
                $delete_stmt->bind_param("i", $edit_id);
                
                if (!$delete_stmt->execute()) {
                    throw new Exception("Error removing old role assignments");
                }
                
                // Assign new roles
                foreach ($selected_roles as $role_id) {
                    $is_primary = ($role_id == $primary_role) ? 1 : 0;
                    $role_sql = "INSERT INTO admin_role_assignments (admin_id, role_id, is_primary) VALUES (?, ?, ?)";
                    $role_stmt = $conn->prepare($role_sql);
                    $role_stmt->bind_param("iii", $edit_id, $role_id, $is_primary);
                    
                    if (!$role_stmt->execute()) {
                        throw new Exception("Error assigning role");
                    }
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                $_SESSION['success'] = "Teacher details updated successfully!";
                header("Location: admins.php");
                exit();
                
            } else {
                throw new Exception("Error updating teacher: " . $conn->error);
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Update failed: " . $e->getMessage();
        }
    }
}

// Get profile image
$profile_image = '../uploads/profiles/' . ($admin['profile_image'] ?: 'default.jpg');
if (!file_exists($profile_image) || empty($admin['profile_image'])) {
    $avatar_url = 'https://ui-avatars.com/api/?name=' . urlencode($admin['first_name'] . '+' . $admin['last_name']) . '&size=150&background=3B9DB3&color=fff&bold=true';
} else {
    $avatar_url = $profile_image;
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

<!-- Font Awesome -->
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

    /* Form Card */
    .form-card {
        background: var(--white);
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        margin-bottom: 25px;
        overflow: hidden;
        animation: fadeInUp 0.6s ease-out;
        border: 1px solid var(--border-color);
    }

    .form-card .card-header {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        padding: 15px 20px;
        border-bottom: none;
    }

    .form-card .card-header h5 {
        margin: 0;
        font-weight: 600;
    }

    .form-card .card-body {
        padding: 30px;
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

    /* Profile Image */
    .profile-image-container {
        position: relative;
        display: inline-block;
    }

    .profile-image {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--primary-color);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    }

    .profile-image:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    }

    .profile-info {
        background: rgba(59, 157, 179, 0.05);
        border-radius: 15px;
        padding: 20px;
        margin-top: 20px;
    }

    .info-item {
        display: flex;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 1.1rem;
    }

    .info-label {
        font-weight: 500;
        color: var(--text-light);
        margin-bottom: 2px;
    }

    .info-value {
        font-weight: 600;
        color: var(--text-color);
    }

    /* Form Section Title */
    .form-section-title {
        color: var(--primary-color);
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary-light);
        margin-bottom: 20px;
    }

    .form-section-title i {
        margin-right: 10px;
        color: var(--primary-color);
    }

    /* Form Controls */
    .form-label {
        font-weight: 500;
        color: var(--text-color);
        margin-bottom: 8px;
    }

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

    .form-control.is-invalid, .form-select.is-invalid {
        border-color: var(--danger-color);
        background-image: none;
    }

    .form-control.is-invalid:focus, .form-select.is-invalid:focus {
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }

    .invalid-feedback {
        color: var(--danger-color);
        font-size: 0.85rem;
        margin-top: 5px;
        text-align: left;
    }

    /* Role Cards */
    .role-card {
        position: relative;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 15px;
        transition: all 0.3s ease;
        cursor: pointer;
        background: var(--white);
        height: 100%;
        animation: fadeIn 0.5s ease-out;
    }

    .role-card:hover {
        border-color: var(--primary-color);
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }

    .role-card.selected {
        border-color: var(--primary-color);
        background: rgba(59, 157, 179, 0.05);
    }

    .role-checkbox {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 20px;
        height: 20px;
        cursor: pointer;
    }

    .role-name {
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 5px;
        padding-right: 30px;
    }

    .role-description {
        color: var(--text-light);
        font-size: 0.9rem;
        margin-bottom: 10px;
    }

    .primary-role-container {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed var(--border-color);
    }

    .primary-radio {
        margin-right: 8px;
    }

    .primary-radio:checked {
        background-color: var(--success-color);
        border-color: var(--success-color);
    }

    .primary-radio:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .primary-label {
        color: var(--success-color);
        font-weight: 500;
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

    .alert-danger-custom {
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.2));
        border-left-color: var(--danger-color);
        color: var(--text-color);
    }

    .alert-success-custom {
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.2));
        border-left-color: var(--success-color);
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

    /* Button Styles */
    .btn-primary-custom {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-weight: 500;
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
        padding: 10px 20px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .btn-outline-primary-custom:hover {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3);
    }

    /* Badge Styles */
    .badge-custom {
        padding: 8px 12px;
        font-weight: 500;
        border-radius: 20px;
        letter-spacing: 0.3px;
        display: inline-block;
    }

    .badge-active {
        background: linear-gradient(135deg, var(--success-color), #1e7e34);
        color: white;
    }

    .badge-inactive {
        background: linear-gradient(135deg, #6c757d, #5a6268);
        color: white;
    }

    .badge-locked {
        background: linear-gradient(135deg, var(--danger-color), #bd2130);
        color: white;
    }

    .badge-unlocked {
        background: linear-gradient(135deg, var(--success-color), #1e7e34);
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

    .modal-header.bg-danger {
        background: linear-gradient(135deg, var(--danger-color), #bd2130) !important;
    }

    .modal-header.bg-warning {
        background: linear-gradient(135deg, var(--warning-color), #e0a800) !important;
    }

    .modal-header.bg-info {
        background: linear-gradient(135deg, var(--info-color), var(--primary-dark)) !important;
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

    /* Responsive */
    @media (max-width: 768px) {
        .main-content {
            padding: 15px;
        }
        
        .page-header h2 {
            font-size: 1.3rem;
        }
        
        .form-card .card-body {
            padding: 20px;
        }
        
        .profile-image {
            width: 120px;
            height: 120px;
        }
        
        .info-icon {
            width: 35px;
            height: 35px;
            font-size: 1rem;
        }
        
        .role-card {
            padding: 12px;
        }
        
        .btn-primary-custom, .btn-outline-primary-custom {
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        
        .modal-dialog {
            margin: 10px;
        }
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
    }
</style>

<div class="main-content <?php echo $sidebarClass; ?>">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center mb-2 mb-sm-0">
                    <a href="admins.php" class="btn btn-back me-3">
                        <i class="fas fa-arrow-left"></i> <span class="d-none d-sm-inline">Back to Staff List</span>
                    </a>
                    <h2 class="mb-0">
                        <i class="fas fa-user-edit me-2"></i>
                        Edit Teacher
                    </h2>
                </div>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-id-card me-1"></i> ID: #<?php echo str_pad($admin['id'], 4, '0', STR_PAD_LEFT); ?>
                </span>
            </div>
        </div>

        <!-- Display Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert-custom alert-danger-custom">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close float-end" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-custom alert-danger-custom">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close float-end" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert-custom alert-success-custom">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close float-end" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="form-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-edit me-2"></i>
                    Edit Teacher Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Profile Summary -->
                    <div class="col-lg-4 mb-4">
                        <div class="text-center">
                            <div class="profile-image-container">
                                <img src="<?php echo $avatar_url; ?>" 
                                     alt="Profile" 
                                     class="profile-image"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($admin['first_name'] . '+' . $admin['last_name']); ?>&size=150&background=3B9DB3&color=fff&bold=true'">
                            </div>
                            
                            <h4 class="mt-3 mb-1">
                                <?php 
                                $fullName = htmlspecialchars($admin['first_name']);
                                if (!empty($admin['middle_name'])) {
                                    $fullName .= ' ' . htmlspecialchars($admin['middle_name']);
                                }
                                $fullName .= ' ' . htmlspecialchars($admin['last_name']);
                                echo $fullName;
                                ?>
                            </h4>
                            
                            <div class="mb-3">
                                <span class="badge-custom <?php echo $admin['status'] ? 'badge-active' : 'badge-inactive'; ?>">
                                    <i class="fas <?php echo $admin['status'] ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                                    <?php echo $admin['status'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                
                                <?php if ($is_locked): ?>
                                    <span class="badge-custom badge-locked ms-2">
                                        <i class="fas fa-lock me-1"></i>Locked
                                    </span>
                                <?php else: ?>
                                    <span class="badge-custom badge-unlocked ms-2">
                                        <i class="fas fa-unlock me-1"></i>Unlocked
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="profile-info">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div>
                                        <div class="info-label">Email</div>
                                        <div class="info-value"><?php echo htmlspecialchars($admin['email']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div>
                                        <div class="info-label">Phone</div>
                                        <div class="info-value"><?php echo htmlspecialchars($admin['phone_number']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div>
                                        <div class="info-label">Member Since</div>
                                        <div class="info-value"><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></div>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div>
                                        <div class="info-label">Last Updated</div>
                                        <div class="info-value"><?php echo $admin['updated_at'] ? date('M d, Y', strtotime($admin['updated_at'])) : 'Never'; ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning-custom mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Editing information for this teacher. All changes will be logged.</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Edit Form -->
                    <div class="col-lg-8">
                        <form method="POST" action="" id="editAdminForm">
                            <h5 class="form-section-title">
                                <i class="fas fa-user-circle"></i>
                                Personal Information
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($admin['first_name']); ?>" required>
                                    <div class="invalid-feedback">Please enter first name</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name"
                                           value="<?php echo htmlspecialchars($admin['middle_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name"
                                           value="<?php echo htmlspecialchars($admin['last_name']); ?>" required>
                                    <div class="invalid-feedback">Please enter last name</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sex <span class="text-danger">*</span></label><br>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="sex" id="male" 
                                               value="Male" <?php echo ($admin['sex'] == 'Male') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="male">Male</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="sex" id="female" 
                                               value="Female" <?php echo ($admin['sex'] == 'Female') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="female">Female</label>
                                    </div>
                                    <div class="invalid-feedback">Please select sex</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Account Status</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="status" id="status" 
                                               value="1" <?php echo $admin['status'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="status">
                                            <?php echo $admin['status'] ? 'Active Account' : 'Inactive Account'; ?>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Inactive users cannot log in</small>
                                </div>
                            </div>
                            
                            <h5 class="form-section-title mt-4">
                                <i class="fas fa-address-book"></i>
                                Contact Information
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                    <div class="invalid-feedback">Please enter a valid email</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">255</span>
                                        <input type="tel" class="form-control" id="phone_number" name="phone_number"
                                               value="<?php 
                                                   $phone = preg_replace('/^255/', '', $admin['phone_number']);
                                                   echo htmlspecialchars($phone);
                                               ?>" 
                                               placeholder="712345678" 
                                               maxlength="9" minlength="9" required>
                                    </div>
                                    <small class="form-text text-muted">Format: 255 followed by 9 digits (e.g., 255712345678)</small>
                                    <div class="invalid-feedback">Please enter a valid phone number (9 digits after 255)</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="check_number" class="form-label">Check Number (Optional)</label>
                                    <input type="text" class="form-control" id="check_number" name="check_number"
                                           value="<?php echo htmlspecialchars($admin['check_number'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="nida" class="form-label">NIDA Number (Optional)</label>
                                    <input type="text" class="form-control" id="nida" name="nida"
                                           value="<?php echo htmlspecialchars($admin['nida'] ?? ''); ?>"
                                           maxlength="20" minlength="0" pattern=".{0}|.{20}">
                                    <small class="form-text text-muted">Leave blank or enter exactly 20 digits</small>
                                </div>
                            </div>
                            
                            <h5 class="form-section-title mt-4">
                                <i class="fas fa-user-tag"></i>
                                Role Assignment
                            </h5>
                            <p class="text-muted mb-3">Select one or more roles for this teacher. Choose one as primary role.</p>
                            
                            <div class="row" id="rolesContainer">
                                <?php foreach ($roles as $role): 
                                    $is_selected = in_array($role['id'], $current_roles);
                                ?>
                                <div class="col-md-4 mb-3">
                                    <div class="role-card <?php echo $is_selected ? 'selected' : ''; ?>" onclick="toggleRole(<?php echo $role['id']; ?>)">
                                        <input type="checkbox" class="role-checkbox" 
                                               name="roles[]" value="<?php echo $role['id']; ?>" 
                                               id="role_<?php echo $role['id']; ?>"
                                               <?php echo $is_selected ? 'checked' : ''; ?>
                                               onchange="toggleRoleCard(this, <?php echo $role['id']; ?>)">
                                        <div class="role-name">
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </div>
                                        <?php if (!empty($role['description'])): ?>
                                            <div class="role-description">
                                                <?php echo htmlspecialchars($role['description']); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="primary-role-container">
                                            <input type="radio" class="primary-radio" 
                                                   name="primary_role" value="<?php echo $role['id']; ?>"
                                                   id="primary_<?php echo $role['id']; ?>" 
                                                   <?php echo ($primary_role_id == $role['id']) ? 'checked' : ''; ?>
                                                   <?php echo $is_selected ? '' : 'disabled'; ?>
                                                   onclick="event.stopPropagation()">
                                            <label class="primary-label" for="primary_<?php echo $role['id']; ?>" onclick="event.stopPropagation()">
                                                <small>Set as Primary Role</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="alert alert-info-custom mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Primary role will be used as the main designation for this teacher.
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="admins.php" class="btn btn-outline-primary-custom">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary-custom" id="submitBtn">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Validation Error Modal -->
<div class="modal fade" id="validationErrorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title text-center w-100">
                    <i class="fas fa-exclamation-circle me-2"></i>Validation Error
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <h5 class="mb-3" id="validationErrorTitle">Please fill in all required fields</h5>
                <p id="validationErrorMessage" class="text-center"></p>
                <div class="alert alert-info mt-3 text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    Please fill in all required fields marked with <span class="text-danger">*</span> before saving.
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-warning" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--success-color), #1e7e34); color: white;">
                <h5 class="modal-title text-center w-100">
                    <i class="fas fa-check-circle me-2"></i>Success
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5 class="mb-3">Teacher Updated Successfully!</h5>
                <p>The teacher information has been updated.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <a href="admins.php" class="btn btn-success">
                    <i class="fas fa-arrow-left me-2"></i>Back to Staff List
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle role card selection
function toggleRole(roleId) {
    const checkbox = document.getElementById('role_' + roleId);
    checkbox.checked = !checkbox.checked;
    toggleRoleCard(checkbox, roleId);
}

function toggleRoleCard(checkbox, roleId) {
    const card = checkbox.closest('.role-card');
    const radio = document.getElementById('primary_' + roleId);
    
    if (checkbox.checked) {
        card.classList.add('selected');
        radio.disabled = false;
    } else {
        card.classList.remove('selected');
        radio.disabled = true;
        if (radio.checked) {
            radio.checked = false;
        }
    }
    
    // Update primary role options
    updatePrimaryRadios();
}

// Update primary role radios based on selected checkboxes
function updatePrimaryRadios() {
    const checkboxes = document.querySelectorAll('.role-checkbox');
    let hasChecked = false;
    
    checkboxes.forEach(checkbox => {
        const roleId = checkbox.value;
        const radio = document.getElementById('primary_' + roleId);
        
        if (checkbox.checked) {
            hasChecked = true;
            radio.disabled = false;
        } else {
            radio.disabled = true;
            if (radio.checked) {
                radio.checked = false;
            }
        }
    });
    
    // If no roles selected, disable all radios
    if (!hasChecked) {
        document.querySelectorAll('.primary-radio').forEach(radio => {
            radio.disabled = true;
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize role cards
    updatePrimaryRadios();
    
    // Form validation
    const form = document.getElementById('editAdminForm');
    
    form.addEventListener('submit', function(e) {
        // Get form values
        const firstName = document.getElementById('first_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const email = document.getElementById('email').value.trim();
        const phoneInput = document.getElementById('phone_number').value.trim();
        const sexChecked = document.querySelector('input[name="sex"]:checked');
        const nida = document.getElementById('nida').value.trim();
        const selectedRoles = document.querySelectorAll('.role-checkbox:checked');
        const primaryRadio = document.querySelector('.primary-radio:checked');
        
        let isValid = true;
        let errorMessage = '';
        
        // Reset validation styles
        document.querySelectorAll('.form-control').forEach(field => {
            field.classList.remove('is-invalid');
        });
        
        // Validate first name
        if (!firstName) {
            document.getElementById('first_name').classList.add('is-invalid');
            errorMessage = 'First name is required';
            isValid = false;
        }
        
        // Validate last name
        if (!lastName) {
            document.getElementById('last_name').classList.add('is-invalid');
            if (!errorMessage) errorMessage = 'Last name is required';
            isValid = false;
        }
        
        // Validate sex
        if (!sexChecked) {
            if (!errorMessage) errorMessage = 'Please select sex';
            isValid = false;
        }
        
        // Validate email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email || !emailRegex.test(email)) {
            document.getElementById('email').classList.add('is-invalid');
            if (!errorMessage) errorMessage = 'Please enter a valid email';
            isValid = false;
        }
        
        // Validate phone
        if (!phoneInput || phoneInput.length !== 9 || !/^\d+$/.test(phoneInput)) {
            document.getElementById('phone_number').classList.add('is-invalid');
            if (!errorMessage) errorMessage = 'Please enter a valid phone number (9 digits after 255)';
            isValid = false;
        }
        
        // Validate NIDA
        if (nida && nida.length !== 20) {
            document.getElementById('nida').classList.add('is-invalid');
            if (!errorMessage) errorMessage = 'NIDA number must be exactly 20 digits or left blank';
            isValid = false;
        }
        
        // Validate roles
        if (selectedRoles.length === 0) {
            if (!errorMessage) errorMessage = 'Please select at least one role';
            isValid = false;
        }
        
        // Validate primary role
        if (!primaryRadio) {
            if (!errorMessage) errorMessage = 'Please select a primary role';
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            
            document.getElementById('validationErrorTitle').textContent = 'Form Validation Error';
            document.getElementById('validationErrorMessage').textContent = errorMessage;
            
            const errorModal = new bootstrap.Modal(document.getElementById('validationErrorModal'));
            errorModal.show();
            
            // Scroll to first error after modal is shown
            setTimeout(() => {
                const firstInvalid = document.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
            }, 500);
            
            return false;
        }
        
        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        const originalContent = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        submitBtn.disabled = true;
        
        return true;
    });
    
    // Remove validation classes when user starts typing
    document.querySelectorAll('.form-control').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
    
    // Phone number formatting
    document.getElementById('phone_number').addEventListener('input', function(e) {
        // Remove non-numeric characters
        this.value = this.value.replace(/\D/g, '');
        
        // Limit to 9 digits
        if (this.value.length > 9) {
            this.value = this.value.slice(0, 9);
        }
        
        // Show validation
        if (this.value.length === 9) {
            this.classList.remove('is-invalid');
        } else if (this.value.length > 0) {
            this.classList.add('is-invalid');
        }
    });
    
    // NIDA validation
    document.getElementById('nida').addEventListener('input', function(e) {
        // Allow only digits
        this.value = this.value.replace(/\D/g, '');
        
        // Limit to 20 digits
        if (this.value.length > 20) {
            this.value = this.value.slice(0, 20);
        }
        
        // Show validation
        if (this.value.length > 0 && this.value.length !== 20) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
    
    // Check for success message in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
    }
    
    // Auto-hide error messages after 10 seconds
    setTimeout(() => {
        const errorAlerts = document.querySelectorAll('.alert-danger-custom');
        errorAlerts.forEach(alert => {
            alert.style.display = 'none';
        });
    }, 10000);
});
</script>

<?php include '../controller/footer.php'; ?>