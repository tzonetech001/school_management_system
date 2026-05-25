<?php
// register_admin.php
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user has permission (Head Master only)
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

// Check if user has Head Master (1) role only
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1) { // Head Master only
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to register new staff members.";
    header("Location: ../404.php");
    exit();
}

// Load user's theme settings
$colors = [];
$preferences = [];

if (isset($_SESSION['admin_id'])) {
    $current_admin_id = $_SESSION['admin_id'];
    
    // Get theme colors
    $color_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = ?";
    $stmt = $conn->prepare($color_query);
    $stmt->bind_param("i", $current_admin_id);
    $stmt->execute();
    $color_result = $stmt->get_result();
    if ($color_result) {
        while ($row = $color_result->fetch_assoc()) {
            $colors[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    // Get preferences
    $pref_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = ?";
    $stmt = $conn->prepare($pref_query);
    $stmt->bind_param("i", $current_admin_id);
    $stmt->execute();
    $pref_result = $stmt->get_result();
    if ($pref_result) {
        while ($row = $pref_result->fetch_assoc()) {
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
    $phone_number = '255' . $phone_input;
    
    $nida = mysqli_real_escape_string($conn, trim($_POST['nida'] ?? ''));
    $selected_roles = $_POST['roles'] ?? [];
    $primary_role = $_POST['primary_role'] ?? null;
    
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
    
    // Check if email already exists
    if (empty($error)) {
        $check_email_sql = "SELECT id FROM admins WHERE email = ?";
        $stmt = $conn->prepare($check_email_sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $check_result = $stmt->get_result();
        if ($check_result->num_rows > 0) {
            $error = "Email already exists. Please use a different email.";
        }
    }
    
    // Check if phone already exists
    if (empty($error)) {
        $check_phone_sql = "SELECT id FROM admins WHERE phone_number = ?";
        $stmt = $conn->prepare($check_phone_sql);
        $stmt->bind_param("s", $phone_number);
        $stmt->execute();
        $check_result = $stmt->get_result();
        if ($check_result->num_rows > 0) {
            $error = "Phone number already exists. Please use a different phone number.";
        }
    }
    
    // Check if NIDA already exists (only if NIDA is provided)
    if (empty($error) && !empty($nida)) {
        $check_nida_sql = "SELECT id FROM admins WHERE nida = ?";
        $stmt = $conn->prepare($check_nida_sql);
        $stmt->bind_param("s", $nida);
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
            // Generate temporary password (first 4 chars of last name + 4 random digits)
            $temp_password = substr($last_name, 0, 4) . rand(1000, 9999);
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // Handle NIDA - set to NULL if empty
            if (empty($nida) || trim($nida) === '') {
                $sql = "INSERT INTO admins (first_name, middle_name, last_name, sex, email, check_number, phone_number, password) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssss", $first_name, $middle_name, $last_name, $sex, $email, $check_number, $phone_number, $hashed_password);
            } else {
                $sql = "INSERT INTO admins (first_name, middle_name, last_name, sex, email, check_number, phone_number, nida, password) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssss", $first_name, $middle_name, $last_name, $sex, $email, $check_number, $phone_number, $nida, $hashed_password);
            }
            
            if ($stmt->execute()) {
                $new_admin_id = $conn->insert_id;
                
                // Assign roles
                foreach ($selected_roles as $role_id) {
                    $is_primary = ($role_id == $primary_role) ? 1 : 0;
                    $role_sql = "INSERT INTO admin_role_assignments (admin_id, role_id, is_primary) VALUES (?, ?, ?)";
                    $role_stmt = $conn->prepare($role_sql);
                    $role_stmt->bind_param("iii", $new_admin_id, $role_id, $is_primary);
                    
                    if (!$role_stmt->execute()) {
                        throw new Exception("Error assigning role");
                    }
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Log the registration
                $log_sql = "INSERT INTO admin_logs (admin_id, action, description, ip_address) 
                           VALUES (?, 'register_teacher', ?, ?)";
                $log_stmt = $conn->prepare($log_sql);
                $description = "Registered new teacher: $first_name $last_name (ID: $new_admin_id)";
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iss", $admin_id, $description, $ip_address);
                $log_stmt->execute();
                
                $_SESSION['success'] = "Teacher registered successfully! Temporary password: " . $temp_password;
                header("Location: admins.php");
                exit();
                
            } else {
                throw new Exception("Error saving teacher: " . $conn->error);
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<?php 
// Calculate sidebar class
$sidebarClass = ($preferences['sidebar_collapsed'] == '1') ? 'sidebar-hidden' : '';
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

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
        --watermark-color: <?php echo $colors['primary']; ?>;
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
        background-attachment: fixed;
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
        padding: 20px 25px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
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

    .page-header h2 {
        position: relative;
        z-index: 1;
        margin: 0;
        font-weight: 600;
    }

    .page-header .btn-back {
        background: rgba(255,255,255,0.2);
        color: white;
        border: 2px solid white;
        transition: all 0.3s ease;
        border-radius: 8px;
        padding: 8px 16px;
    }

    .page-header .btn-back:hover {
        background: white;
        color: var(--primary-color);
        transform: translateX(-5px);
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
        padding: 15px 25px;
        border-bottom: none;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .form-card .card-header i {
        margin-right: 8px;
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

    /* Progress Indicator */
    .progress-indicator {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        position: relative;
    }

    .progress-indicator::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 0;
        width: 100%;
        height: 2px;
        background: var(--border-color);
        z-index: 1;
    }

    .step {
        position: relative;
        z-index: 2;
        background: var(--white);
        padding: 0 15px;
        text-align: center;
        flex: 1;
    }

    .step .step-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--white);
        border: 2px solid var(--border-color);
        color: var(--text-light);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .step.active .step-circle {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        transform: scale(1.1);
        box-shadow: 0 0 15px rgba(59, 157, 179, 0.3);
    }

    .step.completed .step-circle {
        background: var(--success-color);
        border-color: var(--success-color);
        color: white;
    }

    .step .step-label {
        font-size: 0.9rem;
        color: var(--text-light);
        transition: color 0.3s ease;
    }

    .step.active .step-label {
        color: var(--primary-color);
        font-weight: 600;
    }

    /* Form Section Title */
    .form-section-title {
        color: var(--primary-color);
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary-light);
        margin-bottom: 25px;
        font-size: 1.2rem;
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
        font-size: 0.95rem;
    }

    .form-control, .form-select {
        border: 2px solid var(--border-color);
        border-radius: 10px;
        padding: 10px 15px;
        transition: all 0.3s ease;
        font-size: 0.95rem;
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

    .input-group-text {
        background-color: var(--gray);
        border: 2px solid var(--border-color);
        border-right: none;
        border-radius: 10px 0 0 10px;
        color: var(--text-color);
        font-weight: 500;
    }

    .input-group .form-control {
        border-left: none;
        border-radius: 0 10px 10px 0;
    }

    /* Role Cards */
    .role-card {
        position: relative;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 20px 15px;
        transition: all 0.3s ease;
        cursor: pointer;
        background: var(--white);
        height: 100%;
        animation: fadeIn 0.5s ease-out;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .role-card:hover {
        border-color: var(--primary-color);
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }

    .role-card.selected {
        border-color: var(--primary-color);
        background: linear-gradient(135deg, rgba(59, 157, 179, 0.05), rgba(59, 157, 179, 0.1));
    }

    .role-checkbox {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 20px;
        height: 20px;
        cursor: pointer;
        accent-color: var(--primary-color);
    }

    .role-name {
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 8px;
        padding-right: 30px;
        font-size: 1rem;
    }

    .role-description {
        color: var(--text-light);
        font-size: 0.85rem;
        margin-bottom: 15px;
        line-height: 1.4;
    }

    .primary-role-container {
        margin-top: 15px;
        padding-top: 12px;
        border-top: 1px dashed var(--border-color);
        display: flex;
        align-items: center;
    }

    .primary-radio {
        margin-right: 8px;
        accent-color: var(--success-color);
        width: 16px;
        height: 16px;
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
        font-size: 0.9rem;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    /* Alert Styles */
    .alert-custom {
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 20px;
        border-left: 5px solid transparent;
        animation: slideInRight 0.5s ease-out;
        position: relative;
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

    /* Button Styles */
    .btn-primary-custom {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 1rem;
        box-shadow: 0 4px 10px rgba(59, 157, 179, 0.2);
    }

    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(59, 157, 179, 0.3);
        color: white;
    }

    .btn-outline-primary-custom {
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        background: transparent;
        padding: 10px 25px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 1rem;
    }

    .btn-outline-primary-custom:hover {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3);
    }

    .btn-success-custom {
        background: linear-gradient(135deg, var(--success-color), #1e7e34);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 1rem;
        box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2);
    }

    .btn-success-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(40, 167, 69, 0.3);
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

    .modal-header.bg-warning {
        background: linear-gradient(135deg, var(--warning-color), #e0a800) !important;
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
        
        .page-header {
            padding: 15px;
        }
        
        .page-header h2 {
            font-size: 1.3rem;
        }
        
        .form-card .card-body {
            padding: 20px;
        }
        
        .role-card {
            padding: 15px;
        }
        
        .btn-primary-custom, .btn-outline-primary-custom, .btn-success-custom {
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        
        .modal-dialog {
            margin: 10px;
        }
        
        .progress-indicator {
            flex-wrap: wrap;
        }
        
        .step {
            flex: 1 1 50%;
            margin-bottom: 15px;
        }
        
        .progress-indicator::before {
            display: none;
        }
    }

    @media (max-width: 576px) {
        .step {
            flex: 1 1 100%;
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

    /* Watermark Elements */
    .watermark-icon {
        color: var(--primary-color);
        opacity: 0.1;
        font-size: 6rem;
        position: absolute;
        bottom: 10px;
        right: 10px;
        pointer-events: none;
        z-index: 0;
    }

    .card {
        position: relative;
        overflow: hidden;
    }

    /* Tooltip Styles */
    .tooltip-custom {
        position: relative;
        display: inline-block;
    }

    .tooltip-custom:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: var(--text-color);
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 0.8rem;
        white-space: nowrap;
        z-index: 10;
        margin-bottom: 5px;
    }
</style>

<div class="main-content <?php echo $sidebarClass; ?>">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center mb-2 mb-sm-0">
                    <a href="admins.php" class="btn-back me-3">
                        <i class="fas fa-arrow-left me-2"></i>
                        <span class="d-none d-sm-inline">Back to Staff List</span>
                    </a>
                    <h2 class="mb-0">
                        <i class="fas fa-user-plus me-2"></i>
                        Register New Teacher
                    </h2>
                </div>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-calendar-alt me-1"></i>
                    <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <!-- Display Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert-custom alert-danger-custom">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                    <div>
                        <strong>Error!</strong> <?php echo $error; ?>
                    </div>
                </div>
                <button type="button" class="btn-close position-absolute top-50 end-0 translate-middle-y me-3" 
                        data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-custom alert-danger-custom">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                    <div>
                        <strong>Error!</strong> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                </div>
                <button type="button" class="btn-close position-absolute top-50 end-0 translate-middle-y me-3" 
                        data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Registration Form Card -->
        <div class="form-card">
            <div class="card-header">
                <i class="fas fa-user-graduate"></i>
                New Teacher Registration Form
            </div>
            <div class="card-body position-relative">
                <!-- Watermark Icon -->
                <div class="watermark-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                
                <!-- Progress Indicator -->
                <div class="progress-indicator">
                    <div class="step active" id="step1Indicator">
                        <div class="step-circle">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step" id="step2Indicator">
                        <div class="step-circle">2</div>
                        <div class="step-label">Role Assignment</div>
                    </div>
                </div>
                
                <form method="POST" action="" id="adminForm" novalidate>
                    <!-- Step 1: Personal Information -->
                    <div id="step1">
                        <h5 class="form-section-title">
                            <i class="fas fa-user-circle"></i>
                            Personal Information
                        </h5>
                        <p class="text-muted mb-4">
                            <i class="fas fa-info-circle me-2 text-info"></i>
                            Fields marked with <span class="text-danger">*</span> are required
                        </p>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="first_name" class="form-label">
                                    First Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" 
                                       required placeholder="Enter first name">
                                <div class="invalid-feedback">Please enter first name</div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name"
                                       value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>"
                                       placeholder="Enter middle name (optional)">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="last_name" class="form-label">
                                    Last Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" 
                                       required placeholder="Enter last name">
                                <div class="invalid-feedback">Please enter last name</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Sex <span class="text-danger">*</span>
                                </label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sex" id="male" 
                                               value="Male" <?php echo ($_POST['sex'] ?? '') == 'Male' ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="male">
                                            <i class="fas fa-mars me-1 text-primary"></i>Male
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sex" id="female" 
                                               value="Female" <?php echo ($_POST['sex'] ?? '') == 'Female' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="female">
                                            <i class="fas fa-venus me-1 text-danger"></i>Female
                                        </label>
                                    </div>
                                </div>
                                <div class="invalid-feedback d-block">Please select sex</div>
                            </div>
                        </div>
                        
                        <h5 class="form-section-title mt-4">
                            <i class="fas fa-address-book"></i>
                            Contact Information
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           required placeholder="teacher@example.com">
                                </div>
                                <div class="invalid-feedback">Please enter a valid email</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="phone_number" class="form-label">
                                    Phone Number <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">+255</span>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number"
                                           value="<?php 
                                               $post_phone = $_POST['phone_number'] ?? '';
                                               $post_phone = preg_replace('/^255/', '', $post_phone);
                                               echo htmlspecialchars($post_phone);
                                           ?>" 
                                           placeholder="712345678" 
                                           maxlength="9" minlength="9" required>
                                </div>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Format: 255 followed by 9 digits (e.g., 255712345678)
                                </small>
                                <div class="invalid-feedback">Please enter a valid phone number</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="check_number" class="form-label">Check Number (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                    <input type="text" class="form-control" id="check_number" name="check_number"
                                           value="<?php echo htmlspecialchars($_POST['check_number'] ?? ''); ?>"
                                           placeholder="Enter check number">
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="nida" class="form-label">NIDA Number (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" class="form-control" id="nida" name="nida"
                                           value="<?php echo htmlspecialchars($_POST['nida'] ?? ''); ?>"
                                           maxlength="20" placeholder="Enter 20-digit NIDA number">
                                </div>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Leave blank or enter exactly 20 digits
                                </small>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <button type="button" class="btn btn-primary-custom next-step-btn" id="nextStepBtn">
                                Next Step <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Role Assignment -->
                    <div id="step2" style="display: none;">
                        <h5 class="form-section-title">
                            <i class="fas fa-user-tag"></i>
                            Role Assignment
                        </h5>
                        <p class="text-muted mb-4">
                            <i class="fas fa-info-circle me-2 text-info"></i>
                            Select one or more roles for this teacher. Choose one as primary role.
                        </p>
                        
                        <div class="row" id="rolesContainer">
                            <?php foreach ($roles as $role): ?>
                            <div class="col-md-4 mb-3">
                                <div class="role-card" onclick="toggleRole(<?php echo $role['id']; ?>)">
                                    <input type="checkbox" class="role-checkbox" 
                                           name="roles[]" value="<?php echo $role['id']; ?>" 
                                           id="role_<?php echo $role['id']; ?>"
                                           <?php echo in_array($role['id'], $_POST['roles'] ?? []) ? 'checked' : ''; ?>
                                           onchange="toggleRoleCard(this, <?php echo $role['id']; ?>)">
                                    <div class="role-name">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </div>
                                    <?php if (!empty($role['description'])): ?>
                                        <div class="role-description">
                                            <?php echo htmlspecialchars($role['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="primary-role-container" onclick="event.stopPropagation()">
                                        <input type="radio" class="primary-radio" 
                                               name="primary_role" value="<?php echo $role['id']; ?>"
                                               id="primary_<?php echo $role['id']; ?>" 
                                               <?php echo (isset($_POST['primary_role']) && $_POST['primary_role'] == $role['id']) ? 'checked' : ''; ?>
                                               <?php echo in_array($role['id'], $_POST['roles'] ?? []) ? '' : 'disabled'; ?>>
                                        <label class="primary-label" for="primary_<?php echo $role['id']; ?>">
                                            <i class="fas fa-star me-1"></i>Set as Primary
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="alert alert-info-custom mt-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle fa-2x me-3"></i>
                                <div>
                                    <strong>Note:</strong> Primary role will be used as the main designation for this teacher 
                                    and will be displayed prominently in the staff list.
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-primary-custom prev-step-btn" id="prevStepBtn">
                                <i class="fas fa-arrow-left me-2"></i>Previous Step
                            </button>
                            <button type="submit" class="btn btn-success-custom" id="submitBtn">
                                <i class="fas fa-user-plus me-2"></i>Register Teacher
                            </button>
                        </div>
                    </div>
                </form>
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
                <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                <h5 class="mb-3" id="validationErrorTitle">Please fill in all required fields</h5>
                <p id="validationErrorMessage" class="text-muted"></p>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Please fill in all required fields marked with <span class="text-danger">*</span> before continuing.
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

<!-- SweetAlert2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
let currentStep = 1;

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

// Validation function for step 1
function validateStep1() {
    // Get all required fields
    const firstName = document.getElementById('first_name');
    const lastName = document.getElementById('last_name');
    const email = document.getElementById('email');
    const phoneInput = document.getElementById('phone_number');
    const phoneNumber = '255' + phoneInput.value;
    const sexChecked = document.querySelector('input[name="sex"]:checked');
    const nida = document.getElementById('nida');
    
    let isValid = true;
    let errorMessage = '';
    
    // Reset validation styles
    [firstName, lastName, email].forEach(field => {
        field.classList.remove('is-invalid');
    });
    phoneInput.classList.remove('is-invalid');
    nida.classList.remove('is-invalid');
    
    // Validate each field
    if (!firstName.value.trim()) {
        firstName.classList.add('is-invalid');
        errorMessage = 'First name is required';
        isValid = false;
    }
    
    if (!lastName.value.trim()) {
        lastName.classList.add('is-invalid');
        if (!errorMessage) errorMessage = 'Last name is required';
        isValid = false;
    }
    
    if (!sexChecked) {
        if (!errorMessage) errorMessage = 'Please select sex';
        isValid = false;
    }
    
    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email.value.trim() || !emailRegex.test(email.value)) {
        email.classList.add('is-invalid');
        if (!errorMessage) errorMessage = 'Please enter a valid email';
        isValid = false;
    }
    
    // Phone validation (255 followed by exactly 9 digits)
    const phoneRegex = /^255\d{9}$/;
    if (!phoneInput.value.trim() || !phoneRegex.test(phoneNumber)) {
        phoneInput.classList.add('is-invalid');
        if (!errorMessage) errorMessage = 'Please enter a valid phone number (9 digits after 255)';
        isValid = false;
    }
    
    // NIDA validation (if provided)
    if (nida.value.trim() && nida.value.length !== 20) {
        nida.classList.add('is-invalid');
        if (!errorMessage) errorMessage = 'NIDA number must be exactly 20 digits or left blank';
        isValid = false;
    }
    
    if (!isValid) {
        // Show validation error modal
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
    
    return true;
}

function goToStep2() {
    if (validateStep1()) {
        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
        
        // Update progress indicators
        document.getElementById('step1Indicator').classList.remove('active');
        document.getElementById('step1Indicator').classList.add('completed');
        document.getElementById('step2Indicator').classList.add('active');
        
        currentStep = 2;
        
        // Enable/disable radio buttons based on checked state
        updatePrimaryRadios();
        
        // Scroll to top of form
        document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth' });
    }
}

function goToStep1() {
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step1').style.display = 'block';
    
    // Update progress indicators
    document.getElementById('step1Indicator').classList.remove('completed');
    document.getElementById('step1Indicator').classList.add('active');
    document.getElementById('step2Indicator').classList.remove('active');
    
    currentStep = 1;
    
    // Scroll to top of form
    document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize role cards
    updatePrimaryRadios();
    
    // Initialize selected role cards
    document.querySelectorAll('.role-checkbox:checked').forEach(checkbox => {
        const card = checkbox.closest('.role-card');
        if (card) {
            card.classList.add('selected');
        }
    });
    
    // Event listeners for navigation buttons
    document.getElementById('nextStepBtn').addEventListener('click', goToStep2);
    document.getElementById('prevStepBtn').addEventListener('click', goToStep1);
    
    // Enable/disable primary role radio based on checkbox selection
    const checkboxes = document.querySelectorAll('.role-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updatePrimaryRadios();
        });
    });
    
    // Form validation before submission
    document.getElementById('adminForm').addEventListener('submit', function(e) {
        // Validate step 1 first
        if (!validateStep1()) {
            e.preventDefault();
            goToStep1();
            return false;
        }
        
        // Validate roles
        const checkboxes = document.querySelectorAll('.role-checkbox:checked');
        const primaryRadio = document.querySelector('.primary-radio:checked');
        
        if (checkboxes.length === 0) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Role Selection Error',
                text: 'Please select at least one role',
                icon: 'warning',
                confirmButtonColor: '#ffc107',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        if (!primaryRadio) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Primary Role Error',
                text: 'Please select a primary role',
                icon: 'warning',
                confirmButtonColor: '#ffc107',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        const originalContent = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Registering...';
        submitBtn.disabled = true;
        
        // Re-enable after 10 seconds if still on page (fallback)
        setTimeout(() => {
            submitBtn.innerHTML = originalContent;
            submitBtn.disabled = false;
        }, 10000);
        
        return true;
    });
    
    // Remove validation classes when user starts typing
    document.querySelectorAll('#step1 input').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
    
    // Phone number formatting - only allow digits and limit to 9 characters
    document.getElementById('phone_number').addEventListener('input', function(e) {
        // Remove non-numeric characters
        this.value = this.value.replace(/\D/g, '');
        
        // Limit to 9 digits
        if (this.value.length > 9) {
            this.value = this.value.slice(0, 9);
        }
        
        // Auto-format with dashes for better readability
        if (this.value.length > 3 && this.value.length <= 6) {
            const prefix = this.value.substring(0, 3);
            const suffix = this.value.substring(3);
            this.placeholder = prefix + '-' + suffix;
        } else if (this.value.length > 6) {
            const prefix = this.value.substring(0, 3);
            const middle = this.value.substring(3, 6);
            const suffix = this.value.substring(6);
            this.placeholder = prefix + '-' + middle + '-' + suffix;
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
        
        // Group digits for better readability
        if (this.value.length > 0) {
            let formatted = '';
            for (let i = 0; i < this.value.length; i += 4) {
                if (i > 0) formatted += '-';
                formatted += this.value.substring(i, i + 4);
            }
            this.placeholder = formatted;
        }
        
        // Show validation
        if (this.value.length > 0 && this.value.length !== 20) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
    
    // Email validation on blur
    document.getElementById('email').addEventListener('blur', function() {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (this.value.trim() && !emailRegex.test(this.value)) {
            this.classList.add('is-invalid');
        }
    });
    
    // Auto-hide error messages after 10 seconds
    setTimeout(() => {
        const errorAlerts = document.querySelectorAll('.alert-danger-custom');
        errorAlerts.forEach(alert => {
            alert.style.display = 'none';
        });
    }, 10000);
    
    // Show success message if exists in session
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({
        title: 'Success!',
        text: '<?php echo $_SESSION['success']; unset($_SESSION['success']); ?>',
        icon: 'success',
        confirmButtonColor: '#28a745',
        confirmButtonText: 'OK'
    });
    <?php endif; ?>
});
</script>

<?php include '../controller/footer.php'; ?>