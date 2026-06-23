<?php
// auditory_logs.php - Complete Audit Logs Viewer
// Shows ALL system activities: login, logout, maintenance, dormitory, finance, etc.
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'] ?? 0;

// ========== GET CURRENT SCHOOL ID ==========
$school_query = "SELECT school_id FROM admins WHERE id = ?";
$stmt = $conn->prepare($school_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$school_result = $stmt->get_result();
$current_admin_data = $school_result->fetch_assoc();
$current_school_id = $current_admin_data['school_id'] ?? 1;

// ========== GET CURRENT ADMIN DETAILS ==========
$admin_sql = "SELECT a.*, 
              GROUP_CONCAT(DISTINCT ar.role_name) as roles
              FROM admins a
              LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
              LEFT JOIN admin_roles ar ON ara.role_id = ar.id
              WHERE a.id = ?
              GROUP BY a.id";
$stmt = $conn->prepare($admin_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_result = $stmt->get_result();
$current_admin = $admin_result->fetch_assoc();
$current_admin_name = ($current_admin['first_name'] ?? '') . ' ' . ($current_admin['last_name'] ?? '');
$current_admin_roles = $current_admin['roles'] ?? '';

// ========== GET USER ROLES ==========
$user_roles_sql = "SELECT ara.role_id, ar.role_name
                   FROM admin_role_assignments ara
                   JOIN admin_roles ar ON ara.role_id = ar.id
                   JOIN admins a ON ara.admin_id = a.id
                   WHERE ara.admin_id = ? AND a.school_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("ii", $admin_id, $current_school_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
$user_role_names = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
    $user_role_names[] = $row['role_name'];
}

// Check if user is Head Master (role_id = 1)
$is_head_master = in_array(1, $user_role_ids);
$is_second_master = in_array(2, $user_role_ids);

// Check if user has permission
$has_permission = $is_head_master || $is_second_master || !empty($user_role_ids);
if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view audit logs.";
    header("Location: ../404.php");
    exit();
}

// ========== LOAD THEME SETTINGS ==========
$theme_settings = [];
$query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = ? AND school_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $admin_id, $current_school_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row !== null && isset($row['setting_key']) && isset($row['setting_value'])) {
            $theme_settings[$row['setting_key']] = $row['setting_value'];
        }
    }
}

$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = ? AND school_id = ?";
$stmt = $conn->prepare($prefs_query);
$stmt->bind_param("ii", $admin_id, $current_school_id);
$stmt->execute();
$prefs_result = $stmt->get_result();
if ($prefs_result && mysqli_num_rows($prefs_result) > 0) {
    while ($row = mysqli_fetch_assoc($prefs_result)) {
        if ($row !== null && isset($row['preference_key']) && isset($row['preference_value'])) {
            $preferences[$row['preference_key']] = $row['preference_value'];
        }
    }
}

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

$colors = $default_colors;
foreach ($theme_settings as $key => $value) {
    if (array_key_exists($key, $colors) && $value !== null) {
        $colors[$key] = $value;
    }
}

$pref_defaults = [
    'background_opacity' => '65',
    'animations' => '1',
    'font_size' => '16',
    'compact_mode' => '0',
    'background_option' => 'image',
    'sidebar_collapsed' => '0',
    'animation_speed' => 'normal'
];

foreach ($pref_defaults as $key => $default) {
    if (!isset($preferences[$key]) || $preferences[$key] === null) {
        $preferences[$key] = $default;
    }
}

$bg_opacity = isset($preferences['background_opacity']) ? $preferences['background_opacity'] / 100 : 0.65;
$animations_enabled = $preferences['animations'];
$font_size = $preferences['font_size'];
$compact_mode = $preferences['compact_mode'];
$bg_option = $preferences['background_option'];
$sidebar_collapsed = $preferences['sidebar_collapsed'];
$animation_speed = $preferences['animation_speed'];

$animation_speeds = [
    'slow' => '0.5s',
    'normal' => '0.3s',
    'fast' => '0.15s'
];
$animation_duration = isset($animation_speeds[$animation_speed]) ? $animation_speeds[$animation_speed] : '0.3s';

$font_size_map = [
    '10' => '10px',
    '12' => '12px',
    '14' => '14px',
    '16' => '16px',
    '18' => '18px'
];
$font_size_value = isset($font_size_map[$font_size]) ? $font_size_map[$font_size] : '16px';

$background_colors = [
    'gray' => '#e9ecef',
    'eye_care' => '#c7e9c0',
    'milk' => '#fdf5e6',
    'dark_light' => '#2d2d2d'
];

if ($bg_option === 'image') {
    $bg_style = "linear-gradient(rgba(255,255,255,{$bg_opacity}), rgba(255,255,255,{$bg_opacity})), url('../muyovozi.png') no-repeat center center fixed";
    $bg_size = 'cover';
} else {
    $bg_color = isset($background_colors[$bg_option]) ? $background_colors[$bg_option] : '#e9ecef';
    $bg_style = $bg_color;
    $bg_size = 'auto';
}

// ========== HANDLE CLEAR LOGS ==========
// Only Head Master can clear all logs
if (isset($_GET['clear_all']) && $is_head_master) {
    mysqli_begin_transaction($conn);
    
    try {
        // Log this action first
        $log_sql = "INSERT INTO admin_logs (admin_id, action, description, ip_address, school_id) 
                   VALUES (?, 'clear_audit_logs', ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $description = "Cleared ALL audit logs by Head Master";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("issi", $admin_id, $description, $ip_address, $current_school_id);
        $log_stmt->execute();
        
        // Clear admin login attempts
        $delete_admin = "DELETE FROM admin_login_attempts WHERE school_id = ?";
        $stmt = $conn->prepare($delete_admin);
        $stmt->bind_param("i", $current_school_id);
        $stmt->execute();
        
        // Clear student login attempts
        $delete_student = "DELETE FROM student_login_attempts WHERE school_id = ?";
        $stmt = $conn->prepare($delete_student);
        $stmt->bind_param("i", $current_school_id);
        $stmt->execute();
        
        // Clear student login logs
        $delete_student_logs = "DELETE FROM student_login_logs WHERE school_id = ?";
        $stmt = $conn->prepare($delete_student_logs);
        $stmt->bind_param("i", $current_school_id);
        $stmt->execute();
        
        // Clear login notifications
        $delete_notifications = "DELETE FROM login_notifications WHERE school_id = ?";
        $stmt = $conn->prepare($delete_notifications);
        $stmt->bind_param("i", $current_school_id);
        $stmt->execute();
        
        // Clear maintenance logs
        $delete_maintenance = "DELETE FROM maintenance_logs WHERE school_id = ?";
        $stmt = $conn->prepare($delete_maintenance);
        $stmt->bind_param("i", $current_school_id);
        $stmt->execute();
        
        // Clear admin logs
        $delete_admin_logs = "DELETE FROM admin_logs WHERE school_id = ?";
        $stmt = $conn->prepare($delete_admin_logs);
        $stmt->bind_param("i", $current_school_id);
        $stmt->execute();
        
        mysqli_commit($conn);
        $_SESSION['success'] = "All audit logs have been cleared successfully.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Error clearing logs: " . $e->getMessage();
    }
    
    header("Location: auditory_logs.php");
    exit();
}

// ========== FETCH ALL LOGS ==========
$all_logs = [];

// ------------------------------------------------------------------------
// 1. ADMIN LOGIN ATTEMPTS (admin_login_attempts)
// ------------------------------------------------------------------------
$admin_logs_query = "SELECT 
                        ala.id,
                        'admin_login' as log_type,
                        ala.identifier as username,
                        ala.success,
                        ala.ip_address,
                        ala.user_agent,
                        ala.attempt_time as log_time,
                        CONCAT(a.first_name, ' ', a.last_name) as full_name,
                        a.id as user_id,
                        'Login Attempt' as action
                    FROM admin_login_attempts ala
                    LEFT JOIN admins a ON ala.identifier = a.email
                    WHERE ala.school_id = ?";

if (!$is_head_master) {
    $admin_logs_query .= " AND ala.identifier = (SELECT email FROM admins WHERE id = ?)";
}

$admin_logs_query .= " ORDER BY ala.attempt_time DESC";

$stmt = $conn->prepare($admin_logs_query);
if (!$is_head_master) {
    $stmt->bind_param("ii", $current_school_id, $admin_id);
} else {
    $stmt->bind_param("i", $current_school_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['user_type'] = 'Teacher';
    $row['icon'] = 'fa-user-tie';
    $row['badge_class'] = 'badge-user-teacher';
    $all_logs[] = $row;
}

// ------------------------------------------------------------------------
// 2. ADMIN LOGS (admin_logs) - Actions like register, edit, delete, etc.
// ------------------------------------------------------------------------
$admin_actions_query = "SELECT 
                            al.id,
                            'admin_action' as log_type,
                            al.action as action,
                            al.description,
                            al.ip_address,
                            al.user_agent,
                            al.created_at as log_time,
                            CONCAT(a.first_name, ' ', a.last_name) as full_name,
                            a.id as user_id,
                            al.action as username
                        FROM admin_logs al
                        LEFT JOIN admins a ON al.admin_id = a.id
                        WHERE al.school_id = ?";

if (!$is_head_master) {
    $admin_actions_query .= " AND al.admin_id = ?";
}

$admin_actions_query .= " ORDER BY al.created_at DESC";

$stmt = $conn->prepare($admin_actions_query);
if (!$is_head_master) {
    $stmt->bind_param("ii", $current_school_id, $admin_id);
} else {
    $stmt->bind_param("i", $current_school_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['success'] = 1;
    $row['user_type'] = 'Teacher';
    $row['icon'] = 'fa-user-cog';
    $row['badge_class'] = 'badge-user-teacher';
    $all_logs[] = $row;
}

// ------------------------------------------------------------------------
// 3. STUDENT LOGIN ATTEMPTS (student_login_attempts) - Only Head Master
// ------------------------------------------------------------------------
if ($is_head_master) {
    $student_logs_query = "SELECT 
                               sla.id,
                               'student_login' as log_type,
                               sla.identifier as username,
                               sla.success,
                               sla.ip_address,
                               sla.user_agent,
                               sla.attempt_time as log_time,
                               CONCAT(s.first_name, ' ', s.last_name) as full_name,
                               s.id as user_id,
                               'Login Attempt' as action
                           FROM student_login_attempts sla
                           LEFT JOIN students s ON sla.identifier = s.index_number OR sla.identifier = s.admission_number
                           WHERE sla.school_id = ?
                           ORDER BY sla.attempt_time DESC";
    
    $stmt = $conn->prepare($student_logs_query);
    $stmt->bind_param("i", $current_school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['user_type'] = 'Student';
        $row['icon'] = 'fa-user-graduate';
        $row['badge_class'] = 'badge-user-student';
        $all_logs[] = $row;
    }
}

// ------------------------------------------------------------------------
// 4. STUDENT LOGIN LOGS (student_login_logs) - Only Head Master
// ------------------------------------------------------------------------
if ($is_head_master) {
    $student_login_logs_query = "SELECT 
                                      sll.id,
                                      'student_login_log' as log_type,
                                      sll.student_id as user_id,
                                      sll.ip_address,
                                      sll.user_agent,
                                      sll.login_time as log_time,
                                      CONCAT(s.first_name, ' ', s.last_name) as full_name,
                                      s.index_number as username,
                                      'Login' as action,
                                      1 as success
                                  FROM student_login_logs sll
                                  LEFT JOIN students s ON sll.student_id = s.id
                                  WHERE sll.school_id = ?
                                  ORDER BY sll.login_time DESC";
    
    $stmt = $conn->prepare($student_login_logs_query);
    $stmt->bind_param("i", $current_school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['user_type'] = 'Student';
        $row['icon'] = 'fa-user-graduate';
        $row['badge_class'] = 'badge-user-student';
        $all_logs[] = $row;
    }
}

// ------------------------------------------------------------------------
// 5. MAINTENANCE LOGS (maintenance_logs)
// ------------------------------------------------------------------------
$maintenance_logs_query = "SELECT 
                               ml.id,
                               'maintenance' as log_type,
                               ml.log_type as action,
                               ml.description,
                               ml.created_at as log_time,
                               CONCAT(a.first_name, ' ', a.last_name) as full_name,
                               a.id as user_id,
                               'Maintenance' as username,
                               1 as success,
                               ml.ip_address
                           FROM maintenance_logs ml
                           LEFT JOIN admins a ON ml.admin_id = a.id
                           WHERE ml.school_id = ?";

if (!$is_head_master) {
    $maintenance_logs_query .= " AND ml.admin_id = ?";
}

$maintenance_logs_query .= " ORDER BY ml.created_at DESC";

$stmt = $conn->prepare($maintenance_logs_query);
if (!$is_head_master) {
    $stmt->bind_param("ii", $current_school_id, $admin_id);
} else {
    $stmt->bind_param("i", $current_school_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['user_type'] = 'Staff';
    $row['icon'] = 'fa-tools';
    $row['badge_class'] = 'badge-user-staff';
    $all_logs[] = $row;
}

// ------------------------------------------------------------------------
// 6. DORMITORY ROOM STATUS LOGS (room_status_logs)
// ------------------------------------------------------------------------
$room_logs_query = "SELECT 
                        rsl.id,
                        'room_status' as log_type,
                        'Room Status Change' as action,
                        CONCAT('Room ', dr.room_label, ' status changed from ', rsl.old_status, ' to ', rsl.new_status) as description,
                        rsl.changed_at as log_time,
                        CONCAT(a.first_name, ' ', a.last_name) as full_name,
                        a.id as user_id,
                        'Room Change' as username,
                        1 as success,
                        rsl.ip_address
                    FROM room_status_logs rsl
                    LEFT JOIN dormitory_rooms dr ON rsl.room_id = dr.id
                    LEFT JOIN admins a ON rsl.changed_by = a.id
                    WHERE rsl.school_id = ?";

if (!$is_head_master) {
    $room_logs_query .= " AND rsl.changed_by = ?";
}

$room_logs_query .= " ORDER BY rsl.changed_at DESC";

$stmt = $conn->prepare($room_logs_query);
if (!$is_head_master) {
    $stmt->bind_param("ii", $current_school_id, $admin_id);
} else {
    $stmt->bind_param("i", $current_school_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['user_type'] = 'Staff';
    $row['icon'] = 'fa-bed';
    $row['badge_class'] = 'badge-user-staff';
    $all_logs[] = $row;
}

// ------------------------------------------------------------------------
// 7. LOGIN NOTIFICATIONS (login_notifications)
// ------------------------------------------------------------------------
$notification_logs_query = "SELECT 
                                 ln.id,
                                 'notification' as log_type,
                                 'Login Notification' as action,
                                 CONCAT('Login notification sent to ', ln.full_name) as description,
                                 ln.created_at as log_time,
                                 ln.full_name as full_name,
                                 NULL as user_id,
                                 ln.identifier as username,
                                 1 as success,
                                 NULL as ip_address
                             FROM login_notifications ln
                             WHERE ln.school_id = ?";

if (!$is_head_master) {
    $notification_logs_query .= " AND ln.full_name LIKE ?";
}

$notification_logs_query .= " ORDER BY ln.created_at DESC";

if (!$is_head_master) {
    $search_name = '%' . $current_admin_name . '%';
    $stmt = $conn->prepare($notification_logs_query);
    $stmt->bind_param("is", $current_school_id, $search_name);
} else {
    $stmt = $conn->prepare($notification_logs_query);
    $stmt->bind_param("i", $current_school_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['user_type'] = 'System';
    $row['icon'] = 'fa-bell';
    $row['badge_class'] = 'badge-user-system';
    $all_logs[] = $row;
}

// Sort all logs by time (newest first)
usort($all_logs, function($a, $b) {
    $time_a = strtotime($a['log_time'] ?? '1970-01-01');
    $time_b = strtotime($b['log_time'] ?? '1970-01-01');
    return $time_b - $time_a;
});

// ========== STATISTICS ==========
$total_logs = count($all_logs);
$successful_logs = 0;
$failed_logs = 0;
$teacher_logs = 0;
$student_logs = 0;
$staff_logs = 0;
$system_logs = 0;

foreach ($all_logs as $log) {
    if (isset($log['success'])) {
        if ($log['success'] == 1) $successful_logs++;
        else $failed_logs++;
    }
    
    $user_type = $log['user_type'] ?? '';
    if ($user_type == 'Teacher') $teacher_logs++;
    elseif ($user_type == 'Student') $student_logs++;
    elseif ($user_type == 'Staff') $staff_logs++;
    elseif ($user_type == 'System') $system_logs++;
}

$sidebarClass = ($preferences['sidebar_collapsed'] == '1') ? 'sidebar-hidden' : '';
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<style>
    :root {
        --primary-color: <?php echo $colors['primary']; ?>;
        --primary-dark: <?php echo $colors['primary_dark']; ?>;
        --primary-light: <?php echo $colors['primary_light']; ?>;
        --success-color: <?php echo $colors['success']; ?>;
        --danger-color: <?php echo $colors['danger']; ?>;
        --warning-color: <?php echo $colors['warning']; ?>;
        --info-color: <?php echo $colors['info']; ?>;
        --text-color: <?php echo $colors['text']; ?>;
        --text-light: <?php echo $colors['text_light']; ?>;
        --border-color: <?php echo $colors['border']; ?>;
        --white: <?php echo $colors['white']; ?>;
        --gray: <?php echo $colors['gray']; ?>;
        --font-size-base: <?php echo $font_size_value; ?>;
        --animation-duration: <?php echo $animation_duration; ?>;
        --spacing-base: <?php echo $compact_mode === '1' ? '0.75rem' : '1rem'; ?>;
    }

    * {
        transition: <?php echo $animations_enabled === '1' ? 'all var(--animation-duration) ease' : 'none'; ?>;
    }

    body {
        font-size: var(--font-size-base);
        color: var(--text-color);
        background: <?php echo $bg_style; ?>;
        background-size: <?php echo $bg_size; ?>;
        background-position: center;
        min-height: 100vh;
    }

    <?php if ($compact_mode === '1'): ?>
    .card-body { padding: 0.75rem !important; }
    .btn { padding: 0.5rem 1rem !important; }
    .form-control, .form-select { padding: 0.375rem 0.75rem !important; }
    .table td, .table th { padding: 0.5rem !important; }
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

    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        padding: 20px 25px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
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

    .stats-card {
        background: var(--white);
        border-radius: 20px;
        padding: 25px 20px;
        text-align: center;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        height: 100%;
    }

    .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--warning-color));
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(59,157,179,0.15);
    }

    .stats-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        color: white;
        font-size: 24px;
    }

    .stats-number {
        font-size: 28px;
        font-weight: 800;
        color: #2c3e50;
        line-height: 1.2;
    }

    .stats-label {
        font-size: 13px;
        color: #6c757d;
        font-weight: 500;
        margin-top: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-card {
        background: var(--white);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }

    .filter-card .form-control,
    .filter-card .form-select {
        border-radius: 10px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .filter-card .form-control:focus,
    .filter-card .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(59, 157, 179, 0.25);
    }

    .card-table {
        background: var(--white);
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }

    .card-table .card-header {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        padding: 15px 20px;
        border-bottom: none;
    }

    .card-table .card-header h5 {
        margin: 0;
        font-weight: 600;
    }

    .table th {
        font-weight: 600;
        color: #2c3e50;
        background-color: #f8f9fa;
        border-bottom: 2px solid #e9ecef;
        padding: 12px 8px;
        font-size: 13px;
    }

    .table td {
        vertical-align: middle;
        padding: 12px 8px;
        font-size: 0.9rem;
    }

    .table tbody tr:hover {
        background-color: rgba(59, 157, 179, 0.05);
    }

    .badge-login {
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.75rem;
    }

    .badge-login-success {
        background: var(--success-color);
        color: white;
    }

    .badge-login-failed {
        background: var(--danger-color);
        color: white;
    }

    .badge-user-type {
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.75rem;
    }

    .badge-user-teacher {
        background: var(--primary-color);
        color: white;
    }

    .badge-user-student {
        background: var(--warning-color);
        color: #2c3e50;
    }

    .badge-user-staff {
        background: var(--info-color);
        color: white;
    }

    .badge-user-system {
        background: #6c757d;
        color: white;
    }

    .btn-clear-all {
        background: var(--danger-color);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .btn-clear-all:hover {
        transform: scale(1.05);
        box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        color: white;
    }

    .btn-clear-all:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }

    .empty-state i {
        font-size: 4rem;
        color: #d1d5db;
        margin-bottom: 20px;
    }

    .empty-state h4 {
        color: #2c3e50;
        margin-bottom: 10px;
    }

    .empty-state p {
        color: #6c757d;
    }

    .action-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
        background: var(--primary-light);
        color: var(--primary-dark);
    }

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

        .stats-card {
            padding: 20px 15px;
            margin-bottom: 15px;
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            font-size: 20px;
        }

        .stats-number {
            font-size: 24px;
        }

        .table th,
        .table td {
            font-size: 12px;
            padding: 8px 4px;
        }
    }

    @media (max-width: 576px) {
        .stats-card {
            margin-bottom: 10px;
        }

        .stats-number {
            font-size: 20px;
        }
    }
</style>

<div class="main-content <?php echo $sidebarClass; ?>">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center mb-2 mb-sm-0">
                    <a href="dashboard.php" class="btn-back me-3">
                        <i class="fas fa-arrow-left me-2"></i>
                        <span class="d-none d-sm-inline">Back to Dashboard</span>
                    </a>
                    <h2 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Audit Logs
                    </h2>
                </div>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-user me-1"></i>
                    <?php echo htmlspecialchars($current_admin_name); ?>
                    <span class="mx-1">|</span>
                    <i class="fas fa-<?php echo $is_head_master ? 'crown' : 'user'; ?> me-1"></i>
                    <?php echo $is_head_master ? 'Head Master' : (explode(',', $current_admin_roles)[0] ?? 'Staff'); ?>
                </span>
            </div>
        </div>

        <!-- SweetAlert Messages -->
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
            <div class="col-md-2 col-sm-4 mb-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($total_logs); ?></div>
                    <div class="stats-label">Total Activities</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 mb-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--success-color), #1e7e34);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number" style="color: var(--success-color);"><?php echo number_format($successful_logs); ?></div>
                    <div class="stats-label">Successful</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 mb-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--danger-color), #bd2130);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stats-number" style="color: var(--danger-color);"><?php echo number_format($failed_logs); ?></div>
                    <div class="stats-label">Failed</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 mb-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($teacher_logs); ?></div>
                    <div class="stats-label">Teacher Logs</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 mb-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--warning-color), #e0a800);">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stats-number" style="color: var(--warning-color);"><?php echo number_format($student_logs); ?></div>
                    <div class="stats-label">Student Logs</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 mb-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, var(--info-color), #117a8b);">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stats-number" style="color: var(--info-color);"><?php echo number_format($staff_logs + $system_logs); ?></div>
                    <div class="stats-label">Staff/System Logs</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card">
            <div class="row align-items-end">
                <div class="col-md-3 mb-3 mb-md-0">
                    <label class="form-label">Search</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by name, action, IP...">
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <label class="form-label">User Type</label>
                    <select id="userTypeFilter" class="form-select">
                        <option value="">All Users</option>
                        <option value="Teacher">👨‍🏫 Teacher</option>
                        <option value="Student">🎓 Student</option>
                        <option value="Staff">🔧 Staff</option>
                        <option value="System">⚙️ System</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <label class="form-label">Status</label>
                    <select id="statusFilter" class="form-select">
                        <option value="">All Status</option>
                        <option value="1">✅ Success</option>
                        <option value="0">❌ Failed</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <label class="form-label">Date Range</label>
                    <select id="dateFilter" class="form-select">
                        <option value="">All Time</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="week">Last 7 Days</option>
                        <option value="month">Last 30 Days</option>
                    </select>
                </div>
                <div class="col-md-3 text-md-end">
                    <?php if ($is_head_master): ?>
                        <button class="btn btn-clear-all" onclick="confirmClearAll()">
                            <i class="fas fa-trash-alt me-2"></i>Clear All Logs
                        </button>
                    <?php else: ?>
                        <button class="btn btn-clear-all" disabled style="background: #6c757d;">
                            <i class="fas fa-lock me-2"></i>Clear Logs (Head Master Only)
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="card-table">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h5>
                        <i class="fas fa-history me-2"></i>
                        Activity Logs
                        <span class="badge bg-light text-dark ms-2"><?php echo number_format($total_logs); ?> entries</span>
                    </h5>
                    <div>
                        <button class="btn btn-sm btn-outline-light" onclick="refreshTable()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                        <button class="btn btn-sm btn-outline-light ms-2" onclick="exportLogs()">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="logsTable">
                    <thead class="table-light">
                        <tr>
                            <th width="40">#</th>
                            <th width="80">Type</th>
                            <th width="140">User</th>
                            <th>Action</th>
                            <th width="80">Status</th>
                            <th>IP Address</th>
                            <th width="160">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_logs)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h4>No Logs Found</h4>
                                        <p>There are no activity logs to display at this time.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $sn = 1; ?>
                            <?php foreach ($all_logs as $log): 
                                $user_type = $log['user_type'] ?? 'Unknown';
                                $full_name = $log['full_name'] ?? $log['username'] ?? 'System';
                                $action = $log['action'] ?? $log['log_type'] ?? 'Unknown Action';
                                $description = $log['description'] ?? '';
                                $success = isset($log['success']) ? (int)$log['success'] : 1;
                                $ip = $log['ip_address'] ?? 'N/A';
                                $log_time = $log['log_time'] ?? date('Y-m-d H:i:s');
                                $icon = $log['icon'] ?? 'fa-circle';
                                $badge_class = $log['badge_class'] ?? 'badge-user-system';
                                
                                // Time display
                                $time_display = date('Y-m-d H:i:s', strtotime($log_time));
                                
                                // User type label
                                $type_label = $user_type;
                                $type_icon = $icon;
                                
                                // Action display
                                $action_display = $action;
                                if (!empty($description)) {
                                    $action_display .= ' <span class="text-muted small">' . htmlspecialchars(substr($description, 0, 60)) . '</span>';
                                }
                                
                                // Status display
                                $status_display = $success ? 'Success' : 'Failed';
                                $status_class = $success ? 'badge-login-success' : 'badge-login-failed';
                                $status_icon = $success ? 'fa-check-circle' : 'fa-times-circle';
                            ?>
                            <tr data-user-type="<?php echo $user_type; ?>" 
                                data-status="<?php echo $success; ?>"
                                data-date="<?php echo date('Y-m-d', strtotime($log_time)); ?>"
                                data-search="<?php echo strtolower($full_name . ' ' . $action . ' ' . $description . ' ' . $ip); ?>">
                                <td><?php echo $sn++; ?></td>
                                <td>
                                    <span class="badge-user-type <?php echo $badge_class; ?>">
                                        <i class="fas <?php echo $type_icon; ?> me-1"></i>
                                        <?php echo $type_label; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($full_name); ?></strong>
                                    <?php if (!empty($log['username']) && $log['username'] != $full_name): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($log['username']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="action-badge"><?php echo htmlspecialchars($action); ?></span>
                                    <?php if (!empty($description)): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($description, 0, 80)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-login <?php echo $status_class; ?>">
                                        <i class="fas <?php echo $status_icon; ?> me-1"></i>
                                        <?php echo $status_display; ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($ip); ?></code>
                                </td>
                                <td>
                                    <div class="small">
                                        <i class="far fa-clock me-1 text-muted"></i>
                                        <?php echo $time_display; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Showing <?php echo number_format(count($all_logs)); ?> records
                        <?php if (!$is_head_master): ?>
                            <span class="ms-2 text-warning">
                                <i class="fas fa-eye me-1"></i>Viewing your own logs only
                            </span>
                        <?php endif; ?>
                    </small>
                    <small class="text-muted">
                        <i class="fas fa-database me-1"></i>
                        Tables: admin_login_attempts, admin_logs, student_login_attempts, student_login_logs, maintenance_logs, room_status_logs, login_notifications
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Clear All Confirmation Modal -->
<div class="modal fade" id="clearAllModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--danger-color), #bd2130); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Clear All Logs
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                <h5 class="mb-3">Are you sure you want to clear all logs?</h5>
                <p class="text-muted">This action will permanently delete all activity logs from:</p>
                <ul class="text-start text-muted">
                    <li><i class="fas fa-user-tie me-2"></i>Teacher login attempts & actions</li>
                    <li><i class="fas fa-user-graduate me-2"></i>Student login attempts</li>
                    <li><i class="fas fa-tools me-2"></i>Maintenance logs</li>
                    <li><i class="fas fa-bed me-2"></i>Dormitory room status logs</li>
                    <li><i class="fas fa-bell me-2"></i>Login notifications</li>
                </ul>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone!
                </div>
                <p class="text-muted small">Only Head Master can clear all logs.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="auditory_logs.php?clear_all=1" class="btn btn-danger">
                    <i class="fas fa-trash-alt me-2"></i>Yes, Clear All
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // SweetAlert for messages
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    
    if (successMessage) {
        Swal.fire({
            title: 'Success!',
            text: successMessage.getAttribute('data-message'),
            icon: 'success',
            confirmButtonColor: '#3B9DB3',
            timer: 3000,
            timerProgressBar: true
        });
    }
    
    if (errorMessage) {
        Swal.fire({
            title: 'Error!',
            text: errorMessage.getAttribute('data-message'),
            icon: 'error',
            confirmButtonColor: '#d33'
        });
    }
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        filterTable();
    });
    
    // Filter functionality
    document.getElementById('userTypeFilter').addEventListener('change', filterTable);
    document.getElementById('statusFilter').addEventListener('change', filterTable);
    document.getElementById('dateFilter').addEventListener('change', filterTable);
});

function filterTable() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const userType = document.getElementById('userTypeFilter').value;
    const status = document.getElementById('statusFilter').value;
    const dateRange = document.getElementById('dateFilter').value;
    
    const rows = document.querySelectorAll('#logsTable tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        if (row.cells.length === 1) return; // Skip empty state row
        
        const text = row.getAttribute('data-search') || row.textContent.toLowerCase();
        const rowUserType = row.getAttribute('data-user-type') || '';
        const rowStatus = row.getAttribute('data-status') || '';
        const rowDate = row.getAttribute('data-date') || '';
        
        // Search filter
        let matchSearch = !search || text.includes(search);
        
        // User type filter
        let matchUserType = !userType || rowUserType === userType;
        
        // Status filter
        let matchStatus = !status || rowStatus === status;
        
        // Date filter
        let matchDate = true;
        if (dateRange && rowDate) {
            const today = new Date();
            const rowDateObj = new Date(rowDate + 'T00:00:00');
            
            switch(dateRange) {
                case 'today':
                    const todayStr = today.toISOString().split('T')[0];
                    matchDate = rowDate === todayStr;
                    break;
                case 'yesterday':
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    matchDate = rowDate === yesterday.toISOString().split('T')[0];
                    break;
                case 'week':
                    const weekAgo = new Date(today);
                    weekAgo.setDate(weekAgo.getDate() - 7);
                    matchDate = rowDateObj >= weekAgo;
                    break;
                case 'month':
                    const monthAgo = new Date(today);
                    monthAgo.setDate(monthAgo.getDate() - 30);
                    matchDate = rowDateObj >= monthAgo;
                    break;
                default:
                    matchDate = true;
            }
        }
        
        const isVisible = matchSearch && matchUserType && matchStatus && matchDate;
        row.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount++;
    });
    
    // Update visible count
    const footer = document.querySelector('.card-footer small:first-child');
    if (footer) {
        footer.innerHTML = `<i class="fas fa-info-circle me-1"></i>Showing ${visibleCount} records (filtered)`;
    }
}

function refreshTable() {
    location.reload();
}

function exportLogs() {
    // Simple CSV export
    const table = document.getElementById('logsTable');
    let csv = 'S/N,Type,User,Action,Status,IP,Time\n';
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        if (row.cells.length === 1) return;
        const cols = row.querySelectorAll('td');
        const rowData = [];
        cols.forEach(col => {
            let text = col.textContent.trim().replace(/,/g, ';');
            text = text.replace(/\s+/g, ' ');
            rowData.push(text);
        });
        csv += rowData.join(',') + '\n';
    });
    
    // Download CSV
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'audit_logs_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function confirmClearAll() {
    <?php if ($is_head_master): ?>
        new bootstrap.Modal(document.getElementById('clearAllModal')).show();
    <?php else: ?>
        Swal.fire({
            title: 'Access Denied',
            text: 'Only Head Master can clear all logs.',
            icon: 'error',
            confirmButtonColor: '#d33'
        });
    <?php endif; ?>
}
</script>

<?php include '../controller/footer.php'; ?>