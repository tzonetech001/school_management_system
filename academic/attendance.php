<?php
// attendance.php - Class Teacher Attendance Management with Autosave
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'] ?? 0;

// Get current school ID
$school_query = "SELECT school_id FROM admins WHERE id = ?";
$stmt = $conn->prepare($school_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$school_result = $stmt->get_result();
$current_admin_data = $school_result->fetch_assoc();
$current_school_id = $current_admin_data['school_id'] ?? 1;

// Check if user has Class Teacher role
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
$is_class_teacher = false;
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
    if ($row['role_name'] == 'Class Teacher') {
        $is_class_teacher = true;
    }
}

// Check if user has Head Master, Second Master, or Academic Master permission
$has_admin_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 3) {
        $has_admin_permission = true;
        break;
    }
}

if (!$is_class_teacher && !$has_admin_permission) {
    $_SESSION['error'] = "You don't have permission to access attendance.";
    header("Location: ../404.php");
    exit();
}

// Load theme settings
$theme_settings = [];
$query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = ? AND school_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $admin_id, $current_school_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $theme_settings[$row['setting_key']] = $row['setting_value'];
}

$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = ? AND school_id = ?";
$stmt = $conn->prepare($prefs_query);
$stmt->bind_param("ii", $admin_id, $current_school_id);
$stmt->execute();
$prefs_result = $stmt->get_result();
while ($row = $prefs_result->fetch_assoc()) {
    $preferences[$row['preference_key']] = $row['preference_value'];
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
    'info' => '#17a2b8'
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

$animation_speeds = ['slow' => '0.5s', 'normal' => '0.3s', 'fast' => '0.15s'];
$animation_duration = isset($animation_speeds[$animation_speed]) ? $animation_speeds[$animation_speed] : '0.3s';

$font_size_map = ['10' => '10px', '12' => '12px', '14' => '14px', '16' => '16px', '18' => '18px'];
$font_size_value = isset($font_size_map[$font_size]) ? $font_size_map[$font_size] : '16px';

$background_colors = ['gray' => '#e9ecef', 'eye_care' => '#c7e9c0', 'milk' => '#fdf5e6', 'dark_light' => '#2d2d2d'];

if ($bg_option === 'image') {
    $bg_style = "linear-gradient(rgba(255,255,255,{$bg_opacity}), rgba(255,255,255,{$bg_opacity})), url('../muyovozi.png') no-repeat center center fixed";
    $bg_size = 'cover';
} else {
    $bg_color = isset($background_colors[$bg_option]) ? $background_colors[$bg_option] : '#e9ecef';
    $bg_style = $bg_color;
    $bg_size = 'auto';
}

// Get teacher's assigned classes
$assigned_classes_sql = "SELECT tca.* 
                         FROM teacher_class_assignments tca
                         WHERE tca.teacher_id = ? AND tca.school_id = ?
                         ORDER BY tca.class_level, tca.combination";
$stmt = $conn->prepare($assigned_classes_sql);
$stmt->bind_param("ii", $admin_id, $current_school_id);
$stmt->execute();
$assigned_classes_result = $stmt->get_result();
$assigned_classes = [];
while ($row = $assigned_classes_result->fetch_assoc()) {
    $assigned_classes[] = $row;
}

// If user has admin permission, get all classes
if ($has_admin_permission && empty($assigned_classes)) {
    $all_classes_sql = "SELECT DISTINCT class_level, combination 
                        FROM teacher_class_assignments 
                        WHERE school_id = ?
                        ORDER BY class_level, combination";
    $stmt = $conn->prepare($all_classes_sql);
    $stmt->bind_param("i", $current_school_id);
    $stmt->execute();
    $all_classes_result = $stmt->get_result();
    while ($row = $all_classes_result->fetch_assoc()) {
        $assigned_classes[] = [
            'class_level' => $row['class_level'],
            'combination' => $row['combination']
        ];
    }
}

// Get selected class and combination from URL
$selected_class = isset($_GET['class']) ? $_GET['class'] : '';
$selected_combination = isset($_GET['combination']) ? $_GET['combination'] : '';
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// If no class selected and there are assigned classes, use the first one
if (empty($selected_class) && !empty($assigned_classes)) {
    $selected_class = $assigned_classes[0]['class_level'];
    $selected_combination = $assigned_classes[0]['combination'];
}

// Get students for the selected class and combination
$students = [];
if (!empty($selected_class) && !empty($selected_combination)) {
    $students_sql = "SELECT s.id, s.first_name, s.second_name, s.last_name, s.sex, s.index_number, s.admission_number
                     FROM students s
                     WHERE s.class = ? AND s.combination = ? AND s.is_leaver = FALSE AND s.status = 1 AND s.school_id = ?
                     ORDER BY s.sex DESC, s.first_name, s.last_name";
    $stmt = $conn->prepare($students_sql);
    $stmt->bind_param("ssi", $selected_class, $selected_combination, $current_school_id);
    $stmt->execute();
    $students_result = $stmt->get_result();
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
}

// Handle AJAX autosave
if (isset($_POST['ajax_attendance']) && $_POST['ajax_attendance'] == 1) {
    header('Content-Type: application/json');
    
    $attendance_data = json_decode($_POST['attendance_data'], true);
    $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
    $class_level = $_POST['class_level'] ?? '';
    $combination = $_POST['combination'] ?? '';
    
    $response = ['success' => false, 'message' => ''];
    
    $today = date('Y-m-d');
    if ($attendance_date > $today) {
        $response['message'] = 'Cannot mark attendance for future dates!';
        echo json_encode($response);
        exit();
    }
    
    if (empty($attendance_data)) {
        $response['message'] = 'No attendance data received.';
        echo json_encode($response);
        exit();
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        // Delete existing attendance for this date, class, and combination
        $delete_sql = "DELETE FROM attendance_records 
                       WHERE attendance_date = ? AND class_level = ? AND combination = ? AND school_id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("sssi", $attendance_date, $class_level, $combination, $current_school_id);
        $stmt->execute();
        
        // Insert new attendance records
        $day_of_week = date('D', strtotime($attendance_date));
        $day_map = [
            'Mon' => 'MO', 'Tue' => 'TU', 'Wed' => 'WE',
            'Thu' => 'TH', 'Fri' => 'FR', 'Sat' => 'SA', 'Sun' => 'SU'
        ];
        $day_of_week_short = $day_map[$day_of_week] ?? $day_of_week;
        
        $inserted_count = 0;
        foreach ($attendance_data as $student_id => $status) {
            if ($status !== '' && $status !== '0' && $status !== 'none') {
                $insert_sql = "INSERT INTO attendance_records 
                               (student_id, teacher_id, class_level, combination, attendance_date, day_of_week, status, school_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("iisssssi", 
                    $student_id, $admin_id, $class_level, $combination, 
                    $attendance_date, $day_of_week_short, $status, $current_school_id
                );
                $stmt->execute();
                $inserted_count++;
            }
        }
        
        mysqli_commit($conn);
        $response['success'] = true;
        $response['message'] = "Attendance saved successfully! ($inserted_count records)";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $response['message'] = "Error saving attendance: " . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Handle form submission (fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance']) && !isset($_POST['ajax_attendance'])) {
    $attendance_data = $_POST['attendance'];
    $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
    $class_level = $_POST['class_level'] ?? '';
    $combination = $_POST['combination'] ?? '';
    
    $today = date('Y-m-d');
    if ($attendance_date > $today) {
        $error = "Cannot mark attendance for future dates!";
    } else {
        mysqli_begin_transaction($conn);
        
        try {
            $delete_sql = "DELETE FROM attendance_records 
                           WHERE attendance_date = ? AND class_level = ? AND combination = ? AND school_id = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("sssi", $attendance_date, $class_level, $combination, $current_school_id);
            $stmt->execute();
            
            $day_of_week = date('D', strtotime($attendance_date));
            $day_map = [
                'Mon' => 'MO', 'Tue' => 'TU', 'Wed' => 'WE',
                'Thu' => 'TH', 'Fri' => 'FR', 'Sat' => 'SA', 'Sun' => 'SU'
            ];
            $day_of_week_short = $day_map[$day_of_week] ?? $day_of_week;
            
            foreach ($attendance_data as $student_id => $status) {
                if ($status !== '' && $status !== '0') {
                    $insert_sql = "INSERT INTO attendance_records 
                                   (student_id, teacher_id, class_level, combination, attendance_date, day_of_week, status, school_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($insert_sql);
                    $stmt->bind_param("iisssssi", 
                        $student_id, $admin_id, $class_level, $combination, 
                        $attendance_date, $day_of_week_short, $status, $current_school_id
                    );
                    $stmt->execute();
                }
            }
            
            mysqli_commit($conn);
            $success = "Attendance saved successfully for " . date('F j, Y', strtotime($attendance_date)) . "!";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error saving attendance: " . $e->getMessage();
        }
    }
}

// Get existing attendance for the selected date
$existing_attendance = [];
if (!empty($selected_class) && !empty($selected_combination) && !empty($selected_date)) {
    $attendance_sql = "SELECT student_id, status FROM attendance_records 
                       WHERE attendance_date = ? AND class_level = ? AND combination = ? AND school_id = ?";
    $stmt = $conn->prepare($attendance_sql);
    $stmt->bind_param("sssi", $selected_date, $selected_class, $selected_combination, $current_school_id);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
    while ($row = $attendance_result->fetch_assoc()) {
        $existing_attendance[$row['student_id']] = $row['status'];
    }
}

// Generate day headers for the week
$days_of_week = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
$day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Get current date and dates for the week
$current_date = new DateTime();
$current_day_index = $current_date->format('N') - 1;

$start_of_week = clone $current_date;
$start_of_week->modify('-' . $current_day_index . ' days');

$week_dates = [];
for ($i = 0; $i < 7; $i++) {
    $date = clone $start_of_week;
    $date->modify('+' . $i . ' days');
    $week_dates[] = $date->format('Y-m-d');
}

// Check if selected date is in the week
$is_in_week = in_array($selected_date, $week_dates);
if (!$is_in_week) {
    $selected_date_obj = new DateTime($selected_date);
    $selected_day_index = $selected_date_obj->format('N') - 1;
    $selected_start_of_week = clone $selected_date_obj;
    $selected_start_of_week->modify('-' . $selected_day_index . ' days');
    
    $week_dates = [];
    for ($i = 0; $i < 7; $i++) {
        $date = clone $selected_start_of_week;
        $date->modify('+' . $i . ' days');
        $week_dates[] = $date->format('Y-m-d');
    }
}

$is_today = ($selected_date === date('Y-m-d'));
$is_past = ($selected_date < date('Y-m-d'));
$is_future = ($selected_date > date('Y-m-d'));

$sidebarClass = ($sidebar_collapsed == '1') ? 'sidebar-hidden' : '';
include '../controller/header.php';
include '../controller/sidebar.php';
?>

<style>
    :root {
        --primary-color: <?php echo $colors['primary']; ?>;
        --primary-dark: <?php echo $colors['primary_dark']; ?>;
        --primary-light: <?php echo $colors['primary_light']; ?>;
        --text-color: <?php echo $colors['text']; ?>;
        --text-light: <?php echo $colors['text_light']; ?>;
        --border-color: <?php echo $colors['border']; ?>;
        --success-color: <?php echo $colors['success']; ?>;
        --danger-color: <?php echo $colors['danger']; ?>;
        --warning-color: <?php echo $colors['warning']; ?>;
        --info-color: <?php echo $colors['info']; ?>;
        --font-size-base: <?php echo $font_size_value; ?>;
        --animation-duration: <?php echo $animation_duration; ?>;
    }
    
    * {
        transition: <?php echo $animations_enabled === '1' ? 'all var(--animation-duration) ease' : 'none'; ?>;
    }
    
    body {
        font-size: var(--font-size-base);
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
    }
    
    .page-header h2 {
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
        text-decoration: none;
    }
    
    .page-header .btn-back:hover {
        background: white;
        color: var(--primary-color);
        transform: translateX(-5px);
    }
    
    .form-card {
        background: var(--white, white);
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        margin-bottom: 25px;
        overflow: hidden;
        border: 1px solid var(--border-color);
    }
    
    .form-card .card-header {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        padding: 15px 25px;
        border-bottom: none;
        font-weight: 600;
    }
    
    .form-card .card-body {
        padding: 25px;
        overflow-x: auto;
    }
    
    .alert-custom {
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 20px;
        border-left: 5px solid transparent;
    }
    
    .alert-danger-custom {
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.2));
        border-left-color: var(--danger-color);
    }
    
    .alert-success-custom {
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.2));
        border-left-color: var(--success-color);
    }
    
    .alert-warning-custom {
        background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.2));
        border-left-color: var(--warning-color);
    }
    
    .alert-info-custom {
        background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(23, 162, 184, 0.2));
        border-left-color: var(--info-color);
    }
    
    .table th {
        font-weight: 600;
        color: var(--text-color);
        background-color: rgba(59, 157, 179, 0.05);
        border-bottom: 2px solid rgba(59, 157, 179, 0.2);
    }
    
    .table td {
        vertical-align: middle;
    }
    
    .attendance-btn {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        border: 2px solid var(--border-color);
        background: var(--white);
        cursor: pointer;
        font-size: 1.1rem;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
    
    .attendance-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    }
    
    .attendance-btn.status-present {
        background: var(--success-color);
        color: white;
        border-color: var(--success-color);
    }
    
    .attendance-btn.status-absent {
        background: var(--danger-color);
        color: white;
        border-color: var(--danger-color);
    }
    
    .attendance-btn.status-permission {
        background: var(--warning-color);
        color: white;
        border-color: var(--warning-color);
    }
    
    .attendance-btn.status-none {
        background: var(--gray);
        color: var(--text-light);
        border-color: var(--gray);
    }
    
    .attendance-btn.saving {
        opacity: 0.6;
        cursor: wait;
    }
    
    .attendance-btn.saved {
        animation: savedPulse 0.5s ease;
    }
    
    @keyframes savedPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }
    
    .student-name {
        font-weight: 500;
    }
    
    .student-second-name {
        color: var(--text-light);
        font-size: 0.85rem;
    }
    
    .day-label {
        font-weight: 600;
        text-align: center;
        padding: 8px 0;
    }
    
    .day-label.today {
        color: var(--primary-color);
        border-bottom: 3px solid var(--primary-color);
    }
    
    .day-label.past {
        color: var(--text-light);
        opacity: 0.7;
    }
    
    .day-label.future {
        color: var(--text-light);
        opacity: 0.5;
    }
    
    .day-date {
        display: block;
        font-size: 0.8rem;
        font-weight: normal;
    }
    
    .btn-primary-custom {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 10px 25px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3);
        color: white;
    }
    
    .btn-success-custom {
        background: linear-gradient(135deg, var(--success-color), #1e7e34);
        color: white;
        border: none;
        padding: 10px 25px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .btn-success-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        color: white;
    }
    
    .legend-item {
        display: inline-flex;
        align-items: center;
        margin-right: 20px;
        font-size: 0.9rem;
    }
    
    .legend-color {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        margin-right: 8px;
    }
    
    /* Autosave indicator */
    .autosave-indicator {
        display: inline-flex;
        align-items: center;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .autosave-indicator.saving {
        background: var(--warning-color);
        color: #856404;
    }
    
    .autosave-indicator.saved {
        background: var(--success-color);
        color: white;
    }
    
    .autosave-indicator.error {
        background: var(--danger-color);
        color: white;
    }
    
    .autosave-indicator .spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
        margin-right: 8px;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    @media (max-width: 768px) {
        .page-header h2 {
            font-size: 1.3rem;
        }
        .table th, .table td {
            font-size: 0.8rem;
            padding: 4px 2px;
        }
        .attendance-btn {
            width: 35px;
            height: 35px;
            font-size: 0.8rem;
        }
        .student-name {
            font-size: 0.8rem;
        }
        .day-label {
            font-size: 0.8rem;
        }
        .day-date {
            font-size: 0.7rem;
        }
        .autosave-indicator {
            font-size: 0.7rem;
            padding: 3px 10px;
        }
    }
    
    .table-fixed {
        table-layout: fixed;
    }
    
    .col-sn { width: 5%; }
    .col-name { width: 25%; }
    .col-sex { width: 8%; }
    .col-day { width: 10%; }
    
    @media (max-width: 768px) {
        .col-sn { width: 8%; }
        .col-name { width: 22%; }
        .col-sex { width: 10%; }
        .col-day { width: 10%; }
    }
</style>

<div class="main-content <?php echo $sidebarClass; ?>">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center mb-2 mb-sm-0">
                
                    <h2 class="mb-0">
                        <i class="fas fa-clipboard-check me-2"></i>
                        Attendance Management
                    </h2>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div id="autosaveIndicator" class="autosave-indicator saved">
                        <i class="fas fa-check-circle me-1"></i> Auto-saved
                    </div>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-calendar-alt me-1"></i>
                        <?php echo date('F j, Y'); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert-custom alert-danger-custom">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert-custom alert-success-custom">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div id="successMessage" data-message="<?php echo htmlspecialchars($_SESSION['success']); ?>"></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div id="errorMessage" data-message="<?php echo htmlspecialchars($_SESSION['error']); ?>"></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (empty($assigned_classes)): ?>
            <div class="alert-custom alert-warning-custom">
                <i class="fas fa-info-circle me-2"></i>
                You have not been assigned any classes yet. Please contact the Head Master or Academic Master.
            </div>
        <?php else: ?>
        
        <!-- Class Selector - NO AUTO SUBMIT, uses AJAX -->
        <div class="form-card">
            <div class="card-header">
                <i class="fas fa-filter me-2"></i>
                Select Class
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="class_level" class="form-label">Class</label>
                        <select name="class" id="class_level" class="form-select">
                            <option value="">-- Select Class --</option>
                            <?php 
                            $unique_classes = [];
                            foreach ($assigned_classes as $class) {
                                $key = $class['class_level'] . '|' . $class['combination'];
                                if (!in_array($key, $unique_classes)) {
                                    $unique_classes[] = $key;
                                    $selected = ($class['class_level'] == $selected_class && $class['combination'] == $selected_combination) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $class['class_level']; ?>" data-combination="<?php echo $class['combination']; ?>" <?php echo $selected; ?>>
                                    <?php echo $class['class_level']; ?> - <?php echo $class['combination']; ?>
                                </option>
                            <?php 
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="combination" class="form-label">Combination</label>
                        <select name="combination" id="combination" class="form-select">
                            <option value="">-- Select Combination --</option>
                            <?php 
                            foreach ($assigned_classes as $class) {
                                if ($class['class_level'] == $selected_class) {
                                    $selected = ($class['combination'] == $selected_combination) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $class['combination']; ?>" <?php echo $selected; ?>>
                                    <?php echo $class['combination']; ?>
                                </option>
                            <?php 
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" name="date" id="date" class="form-control" 
                               value="<?php echo $selected_date; ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <button type="button" id="viewAttendanceBtn" class="btn btn-primary-custom w-100">
                            <i class="fas fa-search me-2"></i>View
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($selected_class) && !empty($selected_combination) && !empty($students)): ?>
        
        <!-- Attendance Table -->
        <div class="form-card" id="attendanceCard">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <i class="fas fa-users me-2"></i>
                        <?php echo $selected_class; ?> - <?php echo $selected_combination; ?>
                        <span class="badge bg-light text-dark ms-2"><?php echo count($students); ?> Students</span>
                        <span class="badge bg-<?php echo $is_today ? 'success' : ($is_past ? 'secondary' : 'warning'); ?> ms-2">
                            <?php echo $is_today ? 'Today' : ($is_past ? 'Past' : 'Future'); ?>
                        </span>
                    </div>
                    <div>
                        <?php if (!$is_future): ?>
                            <span class="text-light small">
                                <i class="fas fa-info-circle me-1"></i>
                                Click circles to mark attendance (auto-saves)
                            </span>
                        <?php else: ?>
                            <span class="text-warning small">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Cannot mark attendance for future dates
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Legend -->
                <div class="mb-3">
                    <span class="legend-item">
                        <span class="legend-color" style="background: var(--success-color);"></span>
                        Present (✓)
                    </span>
                    <span class="legend-item">
                        <span class="legend-color" style="background: var(--danger-color);"></span>
                        Absent (✗)
                    </span>
                    <span class="legend-item">
                        <span class="legend-color" style="background: var(--warning-color);"></span>
                        Permission (P)
                    </span>
                    <span class="legend-item">
                        <span class="legend-color" style="background: var(--gray);"></span>
                        Not Marked (-)
                    </span>
                    <span class="legend-item text-muted">
                        <i class="fas fa-sync-alt fa-spin me-1"></i> Auto-saves on click
                    </span>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-fixed" id="attendanceTable">
                        <thead>
                            <tr>
                                <th class="col-sn text-center">#</th>
                                <th class="col-name">Student Name</th>
                                <th class="col-sex text-center">Sex</th>
                                <?php foreach ($week_dates as $index => $date): 
                                    $date_obj = new DateTime($date);
                                    $day_label = $days_of_week[$index];
                                    $is_today_date = ($date === date('Y-m-d'));
                                    $is_past_date = ($date < date('Y-m-d'));
                                    $is_future_date = ($date > date('Y-m-d'));
                                ?>
                                <th class="col-day text-center <?php echo $is_today_date ? 'today' : ($is_past_date ? 'past' : 'future'); ?>">
                                    <div class="day-label">
                                        <?php echo $day_label; ?>
                                        <span class="day-date"><?php echo date('M j', strtotime($date)); ?></span>
                                    </div>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="attendanceBody">
                            <?php foreach ($students as $index => $student): 
                                // Get existing attendance for each day of the week
                                $attendance_statuses = [];
                                foreach ($week_dates as $date) {
                                    if (isset($existing_attendance[$student['id']]) && $date === $selected_date) {
                                        $attendance_statuses[$date] = $existing_attendance[$student['id']];
                                    } else {
                                        $attendance_statuses[$date] = '';
                                    }
                                }
                                
                                $selected_status = $attendance_statuses[$selected_date] ?? '';
                            ?>
                            <tr data-student-id="<?php echo $student['id']; ?>">
                                <td class="text-center"><?php echo $index + 1; ?></td>
                                <td>
                                    <div>
                                        <span class="student-name">
                                            <?php echo htmlspecialchars($student['first_name']); ?>
                                        </span>
                                        <?php if (!empty($student['second_name'])): ?>
                                            <span class="student-second-name">
                                                <?php echo substr(htmlspecialchars($student['second_name']), 0, 1) . '.'; ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="student-name">
                                            <?php echo htmlspecialchars($student['last_name']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($student['sex'] == 'Male'): ?>
                                        <span class="badge bg-primary">M</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">F</span>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($week_dates as $date): 
                                    $status = $attendance_statuses[$date] ?? '';
                                    $is_editable = ($date === $selected_date && !$is_future);
                                ?>
                                <td class="text-center">
                                    <?php if ($is_editable): ?>
                                        <button type="button" 
                                                class="attendance-btn status-<?php echo $status ?: 'none'; ?>"
                                                data-student-id="<?php echo $student['id']; ?>"
                                                data-date="<?php echo $date; ?>"
                                                data-current-status="<?php echo $status ?: 'none'; ?>"
                                                onclick="cycleAttendanceWithAutosave(this)"
                                                <?php echo $is_future ? 'disabled' : ''; ?>>
                                            <?php 
                                                if ($status === 'present') echo '✓';
                                                elseif ($status === 'absent') echo '✗';
                                                elseif ($status === 'permission') echo 'P';
                                                else echo '-';
                                            ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" 
                                                class="attendance-btn status-<?php echo $status ?: 'none'; ?>"
                                                disabled
                                                style="cursor: default; opacity: <?php echo $status ? '1' : '0.5'; ?>;">
                                            <?php 
                                                if ($status === 'present') echo '✓';
                                                elseif ($status === 'absent') echo '✗';
                                                elseif ($status === 'permission') echo 'P';
                                                else echo '-';
                                            ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">
                    <div class="text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        <?php echo $is_future ? 'Future date - view only' : 'Click circles to change attendance (auto-saves)'; ?>
                    </div>
                    <div>
                        <button type="button" id="manualSaveBtn" class="btn btn-success-custom" 
                                <?php echo $is_future ? 'disabled' : ''; ?>>
                            <i class="fas fa-save me-2"></i>Manual Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php elseif (!empty($selected_class) && !empty($selected_combination) && empty($students)): ?>
            <div class="alert-custom alert-warning-custom">
                <i class="fas fa-info-circle me-2"></i>
                No students found for <?php echo $selected_class; ?> - <?php echo $selected_combination; ?>.
            </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<script>
// ============================================
// ATTENDANCE MANAGEMENT WITH AUTOSAVE
// ============================================

let saveTimeout = null;
let isSaving = false;
let pendingSaveData = {};
let initialAutosaveDone = false;

// Get current data
const selectedClass = '<?php echo $selected_class; ?>';
const selectedCombination = '<?php echo $selected_combination; ?>';
const selectedDate = '<?php echo $selected_date; ?>';
const isFuture = <?php echo $is_future ? 'true' : 'false'; ?>;

// ============================================
// CYCLE ATTENDANCE WITH AUTOSAVE
// ============================================
function cycleAttendanceWithAutosave(button) {
    if (isFuture) return;
    if (button.disabled) return;
    
    const currentStatus = button.getAttribute('data-current-status') || 'none';
    
    let nextStatus = '';
    let displayChar = '-';
    let statusClass = 'none';
    
    // Cycle: none -> present -> absent -> permission -> none
    if (currentStatus === 'none' || currentStatus === '') {
        nextStatus = 'present';
        displayChar = '✓';
        statusClass = 'present';
    } else if (currentStatus === 'present') {
        nextStatus = 'absent';
        displayChar = '✗';
        statusClass = 'absent';
    } else if (currentStatus === 'absent') {
        nextStatus = 'permission';
        displayChar = 'P';
        statusClass = 'permission';
    } else if (currentStatus === 'permission') {
        nextStatus = 'none';
        displayChar = '-';
        statusClass = 'none';
    }
    
    // Update button
    button.className = 'attendance-btn status-' + statusClass;
    button.textContent = displayChar;
    button.setAttribute('data-current-status', nextStatus);
    
    // Trigger autosave
    triggerAutosave();
}

// ============================================
// COLLECT ATTENDANCE DATA
// ============================================
function collectAttendanceData() {
    const buttons = document.querySelectorAll('#attendanceBody .attendance-btn:not([disabled])');
    const data = {};
    
    buttons.forEach(btn => {
        const studentId = btn.getAttribute('data-student-id');
        const status = btn.getAttribute('data-current-status');
        if (studentId && status && status !== 'none') {
            data[studentId] = status;
        } else if (studentId) {
            // Send '0' for none/unmarked (will be deleted from DB)
            data[studentId] = '0';
        }
    });
    
    return data;
}

// ============================================
// TRIGGER AUTOSAVE
// ============================================
function triggerAutosave() {
    if (isFuture) return;
    
    // Clear any pending timeout
    if (saveTimeout) {
        clearTimeout(saveTimeout);
    }
    
    // Update indicator to "saving"
    updateAutosaveIndicator('saving');
    
    // Debounce: wait 1 second before saving
    saveTimeout = setTimeout(function() {
        performAutosave();
    }, 1000);
}

// ============================================
// PERFORM AUTOSAVE
// ============================================
function performAutosave() {
    if (isSaving) return;
    if (isFuture) return;
    
    const attendanceData = collectAttendanceData();
    
    // Check if there's any data to save (including '0' values to clear records)
    let hasData = false;
    for (let key in attendanceData) {
        if (attendanceData[key] !== '') {
            hasData = true;
            break;
        }
    }
    
    if (!hasData) {
        updateAutosaveIndicator('saved', 'No changes');
        return;
    }
    
    isSaving = true;
    updateAutosaveIndicator('saving');
    
    // Prepare data
    const formData = new FormData();
    formData.append('ajax_attendance', '1');
    formData.append('attendance_data', JSON.stringify(attendanceData));
    formData.append('attendance_date', selectedDate);
    formData.append('class_level', selectedClass);
    formData.append('combination', selectedCombination);
    
    // Send AJAX request
    fetch('attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        isSaving = false;
        
        if (data.success) {
            updateAutosaveIndicator('saved', 'Auto-saved');
            
            // Add visual feedback on buttons
            document.querySelectorAll('#attendanceBody .attendance-btn:not([disabled])').forEach(btn => {
                btn.classList.add('saved');
                setTimeout(() => btn.classList.remove('saved'), 500);
            });
        } else {
            updateAutosaveIndicator('error', data.message || 'Save failed');
            console.error('Autosave error:', data.message);
        }
    })
    .catch(error => {
        isSaving = false;
        updateAutosaveIndicator('error', 'Network error');
        console.error('Autosave error:', error);
    });
}

// ============================================
// UPDATE AUTOSAVE INDICATOR
// ============================================
function updateAutosaveIndicator(status, message) {
    const indicator = document.getElementById('autosaveIndicator');
    if (!indicator) return;
    
    // Remove all classes
    indicator.className = 'autosave-indicator';
    
    switch(status) {
        case 'saving':
            indicator.classList.add('saving');
            indicator.innerHTML = '<span class="spinner"></span> Saving...';
            break;
        case 'saved':
            indicator.classList.add('saved');
            indicator.innerHTML = '<i class="fas fa-check-circle me-1"></i> ' + (message || 'Auto-saved');
            break;
        case 'error':
            indicator.classList.add('error');
            indicator.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> ' + (message || 'Error');
            break;
        default:
            indicator.classList.add('saved');
            indicator.innerHTML = '<i class="fas fa-check-circle me-1"></i> Auto-saved';
    }
}

// ============================================
// MANUAL SAVE
// ============================================
document.getElementById('manualSaveBtn')?.addEventListener('click', function() {
    if (isFuture) {
        Swal.fire({
            title: 'Error',
            text: 'Cannot save attendance for future dates!',
            icon: 'error',
            confirmButtonColor: '#d33'
        });
        return;
    }
    
    // Cancel any pending autosave
    if (saveTimeout) {
        clearTimeout(saveTimeout);
        saveTimeout = null;
    }
    
    // Perform immediate save
    updateAutosaveIndicator('saving');
    performAutosave();
    
    // Show confirmation
    Swal.fire({
        title: 'Saving...',
        text: 'Please wait while attendance is saved.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Check save status after 2 seconds
    setTimeout(() => {
        const indicator = document.getElementById('autosaveIndicator');
        if (indicator.classList.contains('saved')) {
            Swal.fire({
                title: 'Success!',
                text: 'Attendance saved successfully!',
                icon: 'success',
                confirmButtonColor: '#28a745',
                timer: 2000,
                timerProgressBar: true
            });
        } else if (indicator.classList.contains('error')) {
            Swal.fire({
                title: 'Error!',
                text: 'Failed to save attendance. Please try again.',
                icon: 'error',
                confirmButtonColor: '#d33'
            });
        }
    }, 2000);
});

// ============================================
// VIEW ATTENDANCE - LOAD NEW CLASS
// ============================================
document.getElementById('viewAttendanceBtn')?.addEventListener('click', function() {
    const classLevel = document.getElementById('class_level').value;
    const combination = document.getElementById('combination').value;
    const date = document.getElementById('date').value;
    
    if (!classLevel || !combination || !date) {
        Swal.fire({
            title: 'Warning',
            text: 'Please select class, combination, and date.',
            icon: 'warning',
            confirmButtonColor: '#ffc107'
        });
        return;
    }
    
    // Build URL
    const url = `attendance.php?class=${encodeURIComponent(classLevel)}&combination=${encodeURIComponent(combination)}&date=${encodeURIComponent(date)}`;
    window.location.href = url;
});

// ============================================
// POPULATE COMBINATIONS BASED ON CLASS
// ============================================
document.getElementById('class_level')?.addEventListener('change', function() {
    const selectedClass = this.value;
    const combinationSelect = document.getElementById('combination');
    const classOptions = this.options;
    let combos = [];
    
    for (let i = 0; i < classOptions.length; i++) {
        if (classOptions[i].value === selectedClass) {
            const combo = classOptions[i].getAttribute('data-combination');
            if (combo) combos.push(combo);
        }
    }
    
    combinationSelect.innerHTML = '<option value="">-- Select Combination --</option>';
    if (combos.length > 0) {
        combos.forEach(combo => {
            const opt = document.createElement('option');
            opt.value = combo;
            opt.textContent = combo;
            combinationSelect.appendChild(opt);
        });
    }
    
    // Auto-select the first combination if available
    if (combos.length === 1) {
        combinationSelect.value = combos[0];
    }
});

// ============================================
// AUTO-SAVE ON PAGE UNLOAD
// ============================================
window.addEventListener('beforeunload', function() {
    if (saveTimeout) {
        clearTimeout(saveTimeout);
        // Try to save immediately before leaving
        performAutosave();
    }
});

// ============================================
// KEYBOARD SHORTCUTS
// ============================================
document.addEventListener('keydown', function(e) {
    // Ctrl+S to manually save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('manualSaveBtn')?.click();
    }
});

// ============================================
// INITIALIZATION ON PAGE LOAD
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // SweetAlert2 notifications
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    
    if (successMessage) {
        Swal.fire({
            title: 'Success!',
            text: successMessage.getAttribute('data-message'),
            icon: 'success',
            confirmButtonColor: '#28a745',
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
    
    // Trigger change event to populate combinations
    const classSelect = document.getElementById('class_level');
    if (classSelect && classSelect.value) {
        classSelect.dispatchEvent(new Event('change'));
    }
    
    // REMOVED: Automatic default absent marking
    // Students will start with null/unmarked status
    // Only existing attendance from database will be shown
    
    console.log('Attendance page loaded. Students start with null/unmarked status.');
});

// ============================================
// AUTO-REFRESH PREVENTION
// ============================================
// Remove any auto-refresh that might be causing instability
// The page will only refresh when user clicks "View" button
</script>

<?php include '../controller/footer.php'; ?>