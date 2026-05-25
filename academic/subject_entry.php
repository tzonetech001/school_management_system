<?php
ob_start();
// subject_entry.php - Main page to select form and subject
session_start();
require_once '../controller/db_connect.php';

// Check login
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

$admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
$current_year = date('Y');

// Load theme settings for this admin
$theme_settings = [];
$settings_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
$settings_result = mysqli_query($conn, $settings_query);
if ($settings_result && mysqli_num_rows($settings_result) > 0) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $theme_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Load user preferences
$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id";
$prefs_result = mysqli_query($conn, $prefs_query);
if ($prefs_result && mysqli_num_rows($prefs_result) > 0) {
    while ($row = mysqli_fetch_assoc($prefs_result)) {
        $preferences[$row['preference_key']] = $row['preference_value'];
    }
}

// Default colors
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

// Merge with user settings
$colors = $default_colors;
if (!empty($theme_settings)) {
    foreach ($theme_settings as $key => $value) {
        if (array_key_exists($key, $colors)) {
            $colors[$key] = $value;
        }
    }
}

// Background options
$background_options = ['image', 'gray', 'eye_care', 'milk', 'dark_light'];
$background_colors = [
    'gray' => '#e9ecef',
    'eye_care' => '#c7e9c0',
    'milk' => '#fdf5e6',
    'dark_light' => '#2d2d2d'
];

$bg_option = isset($preferences['background_option']) ? $preferences['background_option'] : 'image';
$bg_opacity = isset($preferences['background_opacity']) ? $preferences['background_opacity'] / 100 : 0.65;

if ($bg_option === 'image') {
    $bg_style = "linear-gradient(rgba(255,255,255,{$bg_opacity}), rgba(255,255,255,{$bg_opacity})), url('../muyovozi.png') no-repeat center center fixed";
    $bg_size = 'cover';
} else {
    $bg_color = isset($background_colors[$bg_option]) ? $background_colors[$bg_option] : '#e9ecef';
    $bg_style = $bg_color;
    $bg_size = 'auto';
}

// Font size
$font_sizes = ['10' => '10px', '12' => '12px', '14' => '14px', '16' => '16px', '18' => '18px'];
$font_size = isset($preferences['font_size']) ? $font_sizes[$preferences['font_size']] : '16px';
$compact_mode = isset($preferences['compact_mode']) && $preferences['compact_mode'] === '1';
$animations = isset($preferences['animations']) && $preferences['animations'] === '1';
$animation_speed = isset($preferences['animation_speed']) ? $preferences['animation_speed'] : 'normal';
$animation_time = $animation_speed === 'slow' ? '0.5s' : ($animation_speed === 'fast' ? '0.15s' : '0.3s');

// Get teacher's name
$teacher_sql = "SELECT CONCAT(first_name, ' ', last_name) as teacher_name FROM admins WHERE id = $admin_id";
$teacher_result = mysqli_query($conn, $teacher_sql);
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_name = $teacher['teacher_name'] ?? 'Teacher';

// Get teacher's assigned subjects with permission to enter results
$assigned_sql = "SELECT sta.* 
                 FROM subject_teacher_assignments sta
                 WHERE sta.teacher_id = $admin_id 
                 AND sta.academic_year = $current_year
                 AND sta.can_enter_results = 1
                 ORDER BY sta.form_level, sta.is_primary DESC, sta.subject";
$assigned_result = mysqli_query($conn, $assigned_sql);

$form_five_subjects = [];
$form_six_subjects = [];

while ($row = mysqli_fetch_assoc($assigned_result)) {
    if ($row['form_level'] == 'Form Five') {
        $form_five_subjects[] = $row;
    } else {
        $form_six_subjects[] = $row;
    }
}

// Subject display names
$subject_display = [
    'ac' => 'AC (Accountancy)',
    'htm' => 'HTM (Hospitality)',
    'his' => 'HIST (History)',
    'geo' => 'GEO (Geography)',
    'kisw' => 'KISW (Kiswahili)',
    'eng' => 'ENG (English)',
    'b_math' => 'B/MATH (Basic Mathematics)',
    'adv_m' => 'ADV/M (Advanced Mathematics)',
    'eco' => 'ECO (Economics)',
    'fren' => 'FREN (French)'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Results Entry - Muyovozi High School</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: <?php echo $colors['primary']; ?>;
            --primary-dark: <?php echo $colors['primary_dark']; ?>;
            --primary-light: <?php echo $colors['primary_light']; ?>;
            --light-color: <?php echo $colors['light']; ?>;
            --white: <?php echo $colors['white']; ?>;
            --gray: <?php echo $colors['gray']; ?>;
            --text-color: <?php echo $colors['text']; ?>;
            --text-light: <?php echo $colors['text_light']; ?>;
            --border-color: <?php echo $colors['border']; ?>;
            --success-color: <?php echo $colors['success']; ?>;
            --danger-color: <?php echo $colors['danger']; ?>;
            --warning-color: <?php echo $colors['warning']; ?>;
            --info-color: <?php echo $colors['info']; ?>;
            --coral-color: <?php echo $colors['coral']; ?>;
            --forest-green: <?php echo $colors['forest_green']; ?>;
            --lime-green: <?php echo $colors['lime_green']; ?>;
            --sky-blue: <?php echo $colors['sky_blue']; ?>;
            --aqua-blue: <?php echo $colors['aqua_blue']; ?>;
            --font-size-base: <?php echo $font_size; ?>;
            --spacing-base: <?php echo $compact_mode ? '0.75rem' : '1rem'; ?>;
            --animation-speed: <?php echo $animation_time; ?>;
        }

        * {
            transition: <?php echo $animations ? 'all var(--animation-speed) ease' : 'none'; ?>;
        }

        body {
            background: <?php echo $bg_style; ?>;
            background-size: <?php echo $bg_size; ?>;
            background-position: center;
            min-height: 100vh;
            padding-top: 60px;
            color: var(--text-color);
            font-size: var(--font-size-base);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        <?php if ($compact_mode): ?>
        .card-body { padding: 0.75rem !important; }
        .btn { padding: 0.5rem 1rem !important; }
        .form-control, .form-select { padding: 0.375rem 0.75rem !important; }
        <?php endif; ?>

        /* Teacher Info Card */
        .teacher-info-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .teacher-info-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .teacher-info-card::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }

        /* Stats Cards */
        .stats-card {
            background: var(--white);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-color);
        }

        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 10px 0;
        }

        /* Form Cards */
        .form-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .form-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .form-card-header {
            padding: 25px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .form-card-header.form-five {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        }

        .form-card-header.form-six {
            background: linear-gradient(135deg, var(--primary-dark), #1a2632);
        }

        .form-card-header i {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        .form-card-header h3 {
            margin: 0;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .form-card-header p {
            margin: 5px 0 0;
            opacity: 0.85;
            font-size: 0.85rem;
        }

        .form-card-body {
            padding: 20px;
        }

        /* Subject List */
        .subject-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .subject-list li {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s ease;
        }

        .subject-list li:hover {
            background: rgba(59, 157, 179, 0.05);
        }

        .subject-list li:last-child {
            border-bottom: none;
        }

        .subject-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-color);
        }

        .subject-badge {
            font-size: 0.7rem;
            padding: 3px 10px;
            border-radius: 20px;
            margin-left: 8px;
        }

        .badge-primary-teacher {
            background: var(--warning-color);
            color: #333;
        }

        .btn-enter {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
            border: none;
            padding: 6px 18px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-enter:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
            color: white;
        }

        .no-subjects {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .no-subjects i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Info Alert */
        .info-alert {
            background: linear-gradient(135deg, rgba(59,157,179,0.1), rgba(45,124,143,0.05));
            border-left: 4px solid var(--primary-color);
            border-radius: 12px;
            padding: 15px 20px;
            margin-top: 20px;
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-card {
            animation: fadeInUp 0.6s ease forwards;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Teacher Info Card -->
            <div class="teacher-info-card animate-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <i class="fas fa-chalkboard-user fa-2x me-3" style="opacity: 0.9;"></i>
                        <div style="display: inline-block;">
                            <h4 class="mb-0">Welcome back, <?php echo htmlspecialchars($teacher_name); ?>!</h4>
                            <p class="mb-0 opacity-75"><i class="fas fa-calendar-alt me-1"></i> Academic Year: <?php echo $current_year; ?></p>
                        </div>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                            <i class="fas fa-graduation-cap me-1"></i> Teacher Portal
                        </span>
                    </div>
                </div>
            </div>

            <!-- Page Title -->
            <div class="mb-4 animate-card delay-1">
                <h2 class="page-title" style="color: var(--text-color);">
                    <i class="fas fa-chalkboard-teacher me-2" style="color: var(--primary-color);"></i>
                    Subject Results Entry
                </h2>
                <p class="text-muted">Select a form and subject to enter examination results</p>
            </div>

            <!-- Statistics Row -->
            <div class="row mb-4 animate-card delay-1">
                <div class="col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-graduation-cap" style="color: var(--primary-color);"></i>
                        </div>
                        <div class="stats-number"><?php echo count($form_five_subjects); ?></div>
                        <p class="text-muted mb-0">Form Five Subjects</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-university" style="color: var(--info-color);"></i>
                        </div>
                        <div class="stats-number"><?php echo count($form_six_subjects); ?></div>
                        <p class="text-muted mb-0">Form Six Subjects</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-book" style="color: var(--success-color);"></i>
                        </div>
                        <div class="stats-number"><?php echo count($form_five_subjects) + count($form_six_subjects); ?></div>
                        <p class="text-muted mb-0">Total Assigned Subjects</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Form Five Column -->
                <div class="col-lg-6 mb-4 animate-card delay-2">
                    <div class="form-card">
                        <div class="form-card-header form-five">
                            <i class="fas fa-graduation-cap"></i>
                            <h3>Form Five</h3>
                            <p>Advanced Level - Year 1</p>
                        </div>
                        <div class="form-card-body">
                            <?php if (empty($form_five_subjects)): ?>
                                <div class="no-subjects">
                                    <i class="fas fa-ban text-warning"></i>
                                    <p>No subjects assigned for Form Five</p>
                                    <small class="text-muted">Contact Academic Master to assign subjects</small>
                                </div>
                            <?php else: ?>
                                <ul class="subject-list">
                                    <?php foreach ($form_five_subjects as $subject): ?>
                                        <li>
                                            <div>
                                                <span class="subject-name">
                                                    <?php echo $subject_display[$subject['subject']] ?? strtoupper($subject['subject']); ?>
                                                </span>
                                                <?php if ($subject['is_primary']): ?>
                                                    <span class="subject-badge badge-primary-teacher">
                                                        <i class="fas fa-star me-1"></i>Primary
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="subject_entry_five.php?subject=<?php echo $subject['subject']; ?>" 
                                               class="btn-enter btn">
                                                <i class="fas fa-arrow-right me-1"></i>Enter Results
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Form Six Column -->
                <div class="col-lg-6 mb-4 animate-card delay-2">
                    <div class="form-card">
                        <div class="form-card-header form-six">
                            <i class="fas fa-university"></i>
                            <h3>Form Six</h3>
                            <p>Advanced Level - Year 2</p>
                        </div>
                        <div class="form-card-body">
                            <?php if (empty($form_six_subjects)): ?>
                                <div class="no-subjects">
                                    <i class="fas fa-ban text-warning"></i>
                                    <p>No subjects assigned for Form Six</p>
                                    <small class="text-muted">Contact Academic Master to assign subjects</small>
                                </div>
                            <?php else: ?>
                                <ul class="subject-list">
                                    <?php foreach ($form_six_subjects as $subject): ?>
                                        <li>
                                            <div>
                                                <span class="subject-name">
                                                    <?php echo $subject_display[$subject['subject']] ?? strtoupper($subject['subject']); ?>
                                                </span>
                                                <?php if ($subject['is_primary']): ?>
                                                    <span class="subject-badge badge-primary-teacher">
                                                        <i class="fas fa-star me-1"></i>Primary
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="subject_entry_six.php?subject=<?php echo $subject['subject']; ?>" 
                                               class="btn-enter btn">
                                                <i class="fas fa-arrow-right me-1"></i>Enter Results
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Note -->
            <div class="info-alert animate-card delay-2">
                <div class="d-flex gap-3">
                    <i class="fas fa-info-circle fa-2x" style="color: var(--primary-color);"></i>
                    <div>
                        <strong class="d-block mb-1">Information & Guidelines</strong>
                        <ul class="mb-0 ps-3">
                            <li>You can only enter results for subjects you have been assigned to</li>
                            <li>Only active exams will be available for result entry</li>
                            <li>Marks are automatically saved as you type (1.5 second delay)</li>
                            <li>Use arrow keys (↑ ↓ ← →) to navigate between cells while entering marks</li>
                            <li>Primary teachers have additional responsibilities but same entry rights</li>
                            <li>Contact Academic Master if you need access to additional subjects</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Add animation classes to elements
        document.addEventListener('DOMContentLoaded', function() {
            // Any additional initialization
            console.log('Subject Entry Page Loaded');
        });
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>