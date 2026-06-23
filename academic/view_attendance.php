<?php
// view_attendance.php - View Attendance (Weekly View)
session_start();
require_once '../controller/db_connect.php';

$error = '';

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

// Check if user has permission (any logged in admin can view)
// But we check if they have any role
$user_roles_sql = "SELECT ara.role_id, ar.role_name
                   FROM admin_role_assignments ara
                   JOIN admin_roles ar ON ara.role_id = ar.id
                   JOIN admins a ON ara.admin_id = a.id
                   WHERE ara.admin_id = ? AND a.school_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("ii", $admin_id, $current_school_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$has_any_role = $user_roles_result->num_rows > 0;

if (!$has_any_role) {
    $_SESSION['error'] = "You don't have permission to view attendance.";
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

// Get all teachers with class assignments (for dropdown)
$teachers_sql = "SELECT DISTINCT a.id, a.first_name, a.middle_name, a.last_name 
                 FROM admins a
                 INNER JOIN teacher_class_assignments tca ON a.id = tca.teacher_id
                 WHERE a.school_id = ? AND a.status = 1
                 ORDER BY a.first_name, a.last_name";
$stmt = $conn->prepare($teachers_sql);
$stmt->bind_param("i", $current_school_id);
$stmt->execute();
$teachers_result = $stmt->get_result();
$teachers = [];
while ($row = $teachers_result->fetch_assoc()) {
    $teachers[] = $row;
}

// Get all class assignments for dropdown
$classes_sql = "SELECT DISTINCT class_level, combination 
                FROM teacher_class_assignments 
                WHERE school_id = ?
                ORDER BY class_level, combination";
$stmt = $conn->prepare($classes_sql);
$stmt->bind_param("i", $current_school_id);
$stmt->execute();
$classes_result = $stmt->get_result();
$available_classes = [];
while ($row = $classes_result->fetch_assoc()) {
    $available_classes[] = $row;
}

// Get selected filters
$selected_class = isset($_GET['class']) ? $_GET['class'] : '';
$selected_combination = isset($_GET['combination']) ? $_GET['combination'] : '';
$selected_teacher = isset($_GET['teacher']) ? intval($_GET['teacher']) : 0;

// Get week offset (0 = current week, -1 = previous week, 1 = next week)
$week_offset = isset($_GET['week']) ? intval($_GET['week']) : 0;

// Calculate week dates based on offset
$today = new DateTime();
$today->modify('+' . ($week_offset * 7) . ' days');

// Get Monday of the week
$current_day_index = $today->format('N') - 1;
$start_of_week = clone $today;
$start_of_week->modify('-' . $current_day_index . ' days');

$week_dates = [];
$week_labels = [];
$days_of_week = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
$day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

for ($i = 0; $i < 7; $i++) {
    $date = clone $start_of_week;
    $date->modify('+' . $i . ' days');
    $week_dates[$days_of_week[$i]] = $date->format('Y-m-d');
    $week_labels[$days_of_week[$i]] = $date->format('M j');
}

// Get week start and end for display
$week_start = $start_of_week->format('M d, Y');
$week_end = clone $start_of_week;
$week_end->modify('+6 days');
$week_end_display = $week_end->format('M d, Y');

// Determine if current week
$is_current_week = ($week_offset == 0);
$is_previous_week = ($week_offset < 0);
$is_next_week = ($week_offset > 0);

// Get students based on filters
$students = [];
if (!empty($selected_class) && !empty($selected_combination)) {
    $students_sql = "SELECT s.id, s.first_name, s.second_name, s.last_name, s.sex, s.index_number
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

// Get attendance data for the week
$attendance_data = [];
if (!empty($students)) {
    $student_ids = array_column($students, 'id');
    $student_ids_str = implode(',', $student_ids);
    
    if (!empty($student_ids_str)) {
        // Get all attendance for these students for the week
        $week_start_date = reset($week_dates);
        $week_end_date = end($week_dates);
        
        $attendance_sql = "SELECT student_id, attendance_date, status 
                           FROM attendance_records 
                           WHERE student_id IN ($student_ids_str) 
                           AND attendance_date BETWEEN ? AND ?
                           AND school_id = ?
                           AND class_level = ?
                           AND combination = ?";
        $stmt = $conn->prepare($attendance_sql);
        $stmt->bind_param("ssiss", $week_start_date, $week_end_date, $current_school_id, $selected_class, $selected_combination);
        $stmt->execute();
        $attendance_result = $stmt->get_result();
        
        while ($row = $attendance_result->fetch_assoc()) {
            $student_id = $row['student_id'];
            $date = $row['attendance_date'];
            $status = $row['status'];
            
            if (!isset($attendance_data[$student_id])) {
                $attendance_data[$student_id] = [];
            }
            $attendance_data[$student_id][$date] = $status;
        }
    }
}

// Get summary statistics
$summary = [
    'total_students' => count($students),
    'present' => 0,
    'absent' => 0,
    'permission' => 0,
    'not_marked' => 0
];

// Calculate summary for the selected week
$today_date = date('Y-m-d');
foreach ($students as $student) {
    $student_id = $student['id'];
    $has_attendance = false;
    
    foreach ($week_dates as $day => $date) {
        // Only count for days up to today (if current week)
        if ($is_current_week && $date > $today_date) {
            continue;
        }
        // For previous weeks, count all days
        if (isset($attendance_data[$student_id][$date])) {
            $status = $attendance_data[$student_id][$date];
            if ($status == 'present') {
                $summary['present']++;
            } elseif ($status == 'absent') {
                $summary['absent']++;
            } elseif ($status == 'permission') {
                $summary['permission']++;
            }
            $has_attendance = true;
        }
    }
    
    if (!$has_attendance) {
        $summary['not_marked']++;
    }
}

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
    
    .btn-outline-secondary-custom {
        border: 2px solid var(--border-color);
        color: var(--text-color);
        background: transparent;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .btn-outline-secondary-custom:hover {
        background: var(--gray);
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
        background: var(--primary-color);
        color: white;
    }
    
    .btn-success-custom {
        background: linear-gradient(135deg, var(--success-color), #1e7e34);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .btn-success-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        color: white;
    }
    
    .table th {
        font-weight: 600;
        color: var(--text-color);
        background-color: rgba(59, 157, 179, 0.05);
        border-bottom: 2px solid rgba(59, 157, 179, 0.2);
        text-align: center;
    }
    
    .table td {
        vertical-align: middle;
        text-align: center;
    }
    
    .attendance-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        min-width: 70px;
    }
    
    .attendance-badge.present {
        background: var(--success-color);
        color: white;
    }
    
    .attendance-badge.absent {
        background: var(--danger-color);
        color: white;
    }
    
    .attendance-badge.permission {
        background: var(--warning-color);
        color: #856404;
    }
    
    .attendance-badge.not-marked {
        background: var(--gray);
        color: var(--text-light);
    }
    
    .student-name {
        font-weight: 500;
        text-align: left;
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
        font-size: 0.75rem;
        font-weight: normal;
    }
    
    .week-navigation {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .week-navigation .week-label {
        font-weight: 600;
        padding: 0 15px;
        min-width: 200px;
        text-align: center;
    }
    
    .badge-primary {
        background-color: var(--primary-color) !important;
    }
    
    .stats-summary {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .stats-summary .stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 15px;
        border-radius: 20px;
        background: var(--light);
    }
    
    .stats-summary .stat-item .stat-number {
        font-weight: bold;
        font-size: 1.1rem;
    }
    
    .stats-summary .stat-item .stat-label {
        color: var(--text-light);
        font-size: 0.85rem;
    }
    
    @media (max-width: 768px) {
        .page-header h2 {
            font-size: 1.3rem;
        }
        .table th, .table td {
            font-size: 0.75rem;
            padding: 4px 2px;
        }
        .attendance-badge {
            font-size: 0.7rem;
            padding: 2px 8px;
            min-width: 50px;
        }
        .student-name {
            font-size: 0.75rem;
        }
        .day-label {
            font-size: 0.7rem;
        }
        .day-date {
            font-size: 0.6rem;
        }
        .week-navigation .week-label {
            font-size: 0.8rem;
            min-width: 120px;
        }
        .stats-summary .stat-item {
            padding: 3px 10px;
            font-size: 0.75rem;
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
        .col-name { width: 20%; }
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
                        <i class="fas fa-clipboard-list me-2"></i>
                        View Attendance
                    </h2>
                </div>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-calendar-alt me-1"></i>
                    <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="form-card">
            <div class="card-header">
                <i class="fas fa-filter me-2"></i>
                Select Class &amp; Combination
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="class_level" class="form-label">Class <span class="text-danger">*</span></label>
                            <select name="class" id="class_level" class="form-select" required>
                                <option value="">-- Select Class --</option>
                                <?php 
                                $unique_classes = [];
                                foreach ($available_classes as $class) {
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
                        
                        <div class="col-md-4 mb-3">
                            <label for="combination" class="form-label">Combination <span class="text-danger">*</span></label>
                            <select name="combination" id="combination" class="form-select" required>
                                <option value="">-- Select Combination --</option>
                                <?php 
                                foreach ($available_classes as $class) {
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
                        
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary-custom w-100">
                                <i class="fas fa-search me-2"></i>View Attendance
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!empty($selected_class) && !empty($selected_combination)): ?>
                    <!-- Week Navigation -->
                    <div class="row mt-3 pt-3 border-top">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div class="week-navigation">
                                    <a href="?class=<?php echo urlencode($selected_class); ?>&combination=<?php echo urlencode($selected_combination); ?>&week=<?php echo $week_offset - 1; ?>" 
                                       class="btn btn-outline-secondary-custom" title="Previous Week">
                                        <i class="fas fa-chevron-left me-1"></i> Prev
                                    </a>
                                    <span class="week-label">
                                        <i class="fas fa-calendar-week me-2"></i>
                                        <?php echo $week_start; ?> - <?php echo $week_end_display; ?>
                                        <?php if ($is_current_week): ?>
                                            <span class="badge bg-success ms-2">Current</span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if (!$is_current_week): ?>
                                        <a href="?class=<?php echo urlencode($selected_class); ?>&combination=<?php echo urlencode($selected_combination); ?>&week=<?php echo $week_offset + 1; ?>" 
                                           class="btn btn-outline-secondary-custom" title="Next Week">
                                            Next <i class="fas fa-chevron-right ms-1"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary-custom" disabled>
                                            <i class="fas fa-chevron-right me-1"></i> Next
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="?class=<?php echo urlencode($selected_class); ?>&combination=<?php echo urlencode($selected_combination); ?>&week=0" 
                                   class="btn btn-outline-primary-custom <?php echo $is_current_week ? 'disabled' : ''; ?>">
                                    <i class="fas fa-calendar-day me-2"></i>Current Week
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <?php if (!empty($selected_class) && !empty($selected_combination) && !empty($students)): ?>
        
        <!-- Summary Statistics -->
        <div class="form-card">
            <div class="card-header">
                <i class="fas fa-chart-bar me-2"></i>
                Attendance Summary
                <span class="badge bg-light text-dark ms-2"><?php echo $week_start; ?> - <?php echo $week_end_display; ?></span>
            </div>
            <div class="card-body">
                <div class="stats-summary">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $summary['total_students']; ?></span>
                        <span class="stat-label">Total Students</span>
                    </div>
                    <div class="stat-item" style="background: rgba(40, 167, 69, 0.1);">
                        <span class="stat-number" style="color: var(--success-color);"><?php echo $summary['present']; ?></span>
                        <span class="stat-label">Present</span>
                    </div>
                    <div class="stat-item" style="background: rgba(220, 53, 69, 0.1);">
                        <span class="stat-number" style="color: var(--danger-color);"><?php echo $summary['absent']; ?></span>
                        <span class="stat-label">Absent</span>
                    </div>
                    <div class="stat-item" style="background: rgba(255, 193, 7, 0.1);">
                        <span class="stat-number" style="color: var(--warning-color);"><?php echo $summary['permission']; ?></span>
                        <span class="stat-label">Permission</span>
                    </div>
                    <div class="stat-item" style="background: rgba(108, 117, 125, 0.1);">
                        <span class="stat-number" style="color: var(--text-light);"><?php echo $summary['not_marked']; ?></span>
                        <span class="stat-label">Not Marked</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Attendance Table -->
        <div class="form-card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <i class="fas fa-users me-2"></i>
                        <?php echo $selected_class; ?> - <?php echo $selected_combination; ?>
                        <span class="badge bg-light text-dark ms-2"><?php echo count($students); ?> Students</span>
                    </div>
                    <div>
                        <span class="text-light small">
                            <i class="fas fa-info-circle me-1"></i>
                            View only - Attendance for <?php echo $week_start; ?> to <?php echo $week_end_display; ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Legend -->
                <div class="mb-3">
                    <span class="legend-item">
                        <span class="attendance-badge present">Present</span>
                    </span>
                    <span class="legend-item">
                        <span class="attendance-badge absent">Absent</span>
                    </span>
                    <span class="legend-item">
                        <span class="attendance-badge permission">Permission</span>
                    </span>
                    <span class="legend-item">
                        <span class="attendance-badge not-marked">Not Marked</span>
                    </span>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-fixed" id="attendanceTable">
                        <thead>
                            <tr>
                                <th class="col-sn">#</th>
                                <th class="col-name text-start">Student Name</th>
                                <th class="col-sex">Sex</th>
                                <?php foreach ($days_of_week as $day): 
                                    $date = $week_dates[$day];
                                    $date_obj = new DateTime($date);
                                    $is_today_date = ($date === date('Y-m-d'));
                                    $is_past_date = ($date < date('Y-m-d'));
                                    $is_future_date = ($date > date('Y-m-d'));
                                    $is_weekend = ($day == 'SA' || $day == 'SU');
                                ?>
                                <th class="col-day <?php echo $is_today_date ? 'today' : ($is_past_date ? 'past' : 'future'); ?>">
                                    <div class="day-label">
                                        <?php echo $day; ?>
                                        <span class="day-date"><?php echo $week_labels[$day]; ?></span>
                                        <?php if ($is_weekend): ?>
                                            <span class="badge bg-secondary" style="font-size: 0.6rem;">Weekend</span>
                                        <?php endif; ?>
                                    </div>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td class="text-start">
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
                                </td>
                                <td>
                                    <?php if ($student['sex'] == 'Male'): ?>
                                        <span class="badge bg-primary">M</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">F</span>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($days_of_week as $day): 
                                    $date = $week_dates[$day];
                                    $status = isset($attendance_data[$student['id']][$date]) ? $attendance_data[$student['id']][$date] : '';
                                    $is_weekend = ($day == 'SA' || $day == 'SU');
                                    $is_today_date = ($date === date('Y-m-d'));
                                    $is_future_date = ($date > date('Y-m-d'));
                                    
                                    // Skip weekend days or future days in current week
                                    if ($is_weekend || ($is_current_week && $is_future_date)) {
                                        $display_status = 'weekend';
                                    } else {
                                        $display_status = $status;
                                    }
                                ?>
                                <td>
                                    <?php if ($display_status == 'weekend'): ?>
                                        <span class="text-muted" style="font-size: 0.7rem;">—</span>
                                    <?php elseif ($display_status == 'present'): ?>
                                        <span class="attendance-badge present">✓ Present</span>
                                    <?php elseif ($display_status == 'absent'): ?>
                                        <span class="attendance-badge absent">✗ Absent</span>
                                    <?php elseif ($display_status == 'permission'): ?>
                                        <span class="attendance-badge permission">P Permission</span>
                                    <?php elseif ($is_future_date): ?>
                                        <span class="attendance-badge not-marked">—</span>
                                    <?php else: ?>
                                        <span class="attendance-badge not-marked">Not Marked</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php elseif (!empty($selected_class) && !empty($selected_combination) && empty($students)): ?>
            <div class="alert-custom alert-warning-custom">
                <i class="fas fa-info-circle me-2"></i>
                No students found for <?php echo $selected_class; ?> - <?php echo $selected_combination; ?>.
            </div>
        <?php elseif (empty($selected_class) || empty($selected_combination)): ?>
            <div class="alert-custom alert-info-custom">
                <i class="fas fa-info-circle me-2"></i>
                Please select a class and combination to view attendance.
            </div>
        <?php endif; ?>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<script>
// ============================================
// VIEW ATTENDANCE - FULL SCRIPT
// ============================================

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
// AUTO-SUBMIT FORM ON LOAD
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Trigger change event to populate combinations
    const classSelect = document.getElementById('class_level');
    if (classSelect && classSelect.value) {
        classSelect.dispatchEvent(new Event('change'));
    }
    
    // If class and combination are already selected, show attendance
    const classLevel = '<?php echo $selected_class; ?>';
    const combination = '<?php echo $selected_combination; ?>';
    if (classLevel && combination) {
        // Already showing
    }
});

// ============================================
// KEYBOARD SHORTCUTS
// ============================================
document.addEventListener('keydown', function(e) {
    // Left arrow: previous week
    if (e.key === 'ArrowLeft' && !e.ctrlKey && !e.metaKey) {
        const prevLink = document.querySelector('.week-navigation a:first-child');
        if (prevLink && !prevLink.disabled) {
            e.preventDefault();
            window.location.href = prevLink.href;
        }
    }
    
    // Right arrow: next week
    if (e.key === 'ArrowRight' && !e.ctrlKey && !e.metaKey) {
        const nextLink = document.querySelector('.week-navigation a:last-child');
        if (nextLink && !nextLink.disabled && nextLink.href) {
            e.preventDefault();
            window.location.href = nextLink.href;
        }
    }
    
    // Ctrl+Home: current week
    if ((e.ctrlKey || e.metaKey) && e.key === 'Home') {
        e.preventDefault();
        const currentLink = document.querySelector('a[href*="week=0"]');
        if (currentLink && !currentLink.classList.contains('disabled')) {
            window.location.href = currentLink.href;
        }
    }
});

// ============================================
// AUTO-REFRESH PREVENTION
// ============================================
// No auto-refresh - user controls navigation
console.log('View Attendance loaded. Use navigation buttons to browse weeks.');
</script>

<style>
    /* Additional styles for alerts */
    .alert-custom {
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 20px;
        border-left: 5px solid transparent;
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
    
    .legend-item {
        display: inline-flex;
        align-items: center;
        margin-right: 15px;
        font-size: 0.9rem;
    }
    
    .legend-item .attendance-badge {
        margin-right: 5px;
    }
    
    /* Disabled button styles */
    .btn-outline-secondary-custom.disabled,
    .btn-outline-secondary-custom:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .btn-outline-primary-custom.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
</style>

<?php include '../controller/footer.php'; ?>