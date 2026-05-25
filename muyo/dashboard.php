<?php
// dashboard.php
session_start();

// ==================== SESSION VALIDATION ====================
// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    // Clean any output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header("Location: ../index.php");
    exit();
}



// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // Regenerate every 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

require_once '../controller/db_connect.php';

// Verify admin still exists and is active
$admin_id = $_SESSION['admin_id'];
$verify_sql = "SELECT id, status FROM admins WHERE id = $admin_id AND status = 1";
$verify_result = mysqli_query($conn, $verify_sql);
if (!$verify_result || mysqli_num_rows($verify_result) == 0) {
    // Admin no longer exists or is inactive
    session_destroy();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header("Location: ../index.php");
    exit();
}

// Get current admin info
$admin_name = 'Guest';
$admin_roles = [];

if ($admin_id > 0) {
    $admin_sql = "SELECT a.*, 
                  GROUP_CONCAT(DISTINCT ar.role_name) as roles
                  FROM admins a
                  LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
                  LEFT JOIN admin_roles ar ON ara.role_id = ar.id
                  WHERE a.id = $admin_id
                  GROUP BY a.id";
    $admin_result = mysqli_query($conn, $admin_sql);
    if ($admin_result && mysqli_num_rows($admin_result) > 0) {
        $admin_info = mysqli_fetch_assoc($admin_result);
        $admin_name = $admin_info['first_name'] . ' ' . $admin_info['last_name'];
        $admin_roles = explode(',', $admin_info['roles'] ?? '');
    }
}
// Load user's theme settings from session or database
$colors = [];
$preferences = [];
$bg_style = '';
$bg_size = 'cover';

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

// Calculate background style
$bg_opacity = $preferences['background_opacity'] / 100;
$bg_option = $preferences['background_option'];

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

// Get real statistics from database
$stats_sql = "SELECT 
    -- Student counts
    (SELECT COUNT(*) FROM students WHERE is_leaver = 0) as total_active_students,
    (SELECT COUNT(*) FROM students WHERE class = 'Form Five' AND is_leaver = 0) as form_five_count,
    (SELECT COUNT(*) FROM students WHERE class = 'Form Six' AND is_leaver = 0) as form_six_count,
    (SELECT COUNT(*) FROM students WHERE is_leaver = 1) as leavers_count,
    
    -- Gender distribution
    (SELECT COUNT(*) FROM students WHERE sex = 'Male' AND is_leaver = 0) as male_count,
    (SELECT COUNT(*) FROM students WHERE sex = 'Female' AND is_leaver = 0) as female_count,
    
    -- Staff counts
    (SELECT COUNT(*) FROM admins WHERE status = 1) as active_staff,
    (SELECT COUNT(*) FROM admins WHERE status = 0) as inactive_staff,
    
    -- Dormitory occupancy
    (SELECT SUM(current_occupancy) FROM dormitories) as total_dorm_occupancy,
    (SELECT SUM(total_capacity) FROM dormitories) as total_dorm_capacity,
    (SELECT COUNT(*) FROM dormitories WHERE status = 'Active') as active_dorms,
    
    -- Maintenance items
    (SELECT COUNT(*) FROM maintenance_items WHERE status = 'available') as available_items,
    (SELECT COUNT(*) FROM maintenance_items WHERE status = 'assigned') as assigned_items,
    (SELECT COUNT(*) FROM maintenance_items WHERE status = 'damaged') as damaged_items,
    
    -- Notifications
    (SELECT COUNT(*) FROM notifications WHERE status = 'active') as total_notifications,
    
    -- Recent activities (last 7 days)
    (SELECT COUNT(*) FROM admin_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_activities,
    
    -- Locked accounts
    (SELECT COUNT(*) FROM admins WHERE locked_until IS NOT NULL AND locked_until > NOW()) as locked_staff,
    (SELECT COUNT(*) FROM students WHERE locked_until IS NOT NULL AND locked_until > NOW()) as locked_students";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get academic performance data for Form Five
$academic_five_sql = "SELECT 
    AVG(fr.average) as avg_score,
    COUNT(CASE WHEN fr.division = 'Division I' THEN 1 END) as div1_count,
    COUNT(CASE WHEN fr.division = 'Division II' THEN 1 END) as div2_count,
    COUNT(CASE WHEN fr.division = 'Division III' THEN 1 END) as div3_count,
    COUNT(CASE WHEN fr.division = 'Division IV' THEN 1 END) as div4_count,
    COUNT(CASE WHEN fr.division = 'Division 0' THEN 1 END) as div0_count,
    AVG(fr.ac) as avg_ac,
    AVG(fr.htm) as avg_htm,
    AVG(fr.his) as avg_his,
    AVG(fr.geo) as avg_geo,
    AVG(fr.kisw) as avg_kisw,
    AVG(fr.eng) as avg_eng,
    AVG(fr.b_math) as avg_bmath,
    AVG(fr.adv_m) as avg_advm,
    AVG(fr.eco) as avg_eco,
    AVG(fr.fren) as avg_fren
FROM form_five_results fr
WHERE fr.total_points IS NOT NULL";
$academic_five_result = mysqli_query($conn, $academic_five_sql);
$academic_five = mysqli_fetch_assoc($academic_five_result);

// Get academic performance data for Form Six
$academic_six_sql = "SELECT 
    AVG(fr.average) as avg_score,
    COUNT(CASE WHEN fr.division = 'Division I' THEN 1 END) as div1_count,
    COUNT(CASE WHEN fr.division = 'Division II' THEN 1 END) as div2_count,
    COUNT(CASE WHEN fr.division = 'Division III' THEN 1 END) as div3_count,
    COUNT(CASE WHEN fr.division = 'Division IV' THEN 1 END) as div4_count,
    COUNT(CASE WHEN fr.division = 'Division 0' THEN 1 END) as div0_count,
    AVG(fr.ac) as avg_ac,
    AVG(fr.htm) as avg_htm,
    AVG(fr.his) as avg_his,
    AVG(fr.geo) as avg_geo,
    AVG(fr.kisw) as avg_kisw,
    AVG(fr.eng) as avg_eng,
    AVG(fr.b_math) as avg_bmath,
    AVG(fr.adv_m) as avg_advm,
    AVG(fr.eco) as avg_eco,
    AVG(fr.fren) as avg_fren
FROM form_six_results fr
WHERE fr.total_points IS NOT NULL";
$academic_six_result = mysqli_query($conn, $academic_six_sql);
$academic_six = mysqli_fetch_assoc($academic_six_result);

// Get exam trends (last 4 exam types with average scores)
$exam_trend_sql = "SELECT 
    et.exam_name,
    et.year,
    et.term,
    AVG(CASE WHEN et.form_level = 'Form Five' THEN fr5.average ELSE fr6.average END) as avg_score
FROM exam_types et
LEFT JOIN form_five_results fr5 ON et.id = fr5.exam_type_id AND et.form_level = 'Form Five'
LEFT JOIN form_six_results fr6 ON et.id = fr6.exam_type_id AND et.form_level = 'Form Six'
WHERE et.is_active = 1 OR et.id IN (SELECT DISTINCT exam_type_id FROM form_five_results UNION SELECT DISTINCT exam_type_id FROM form_six_results)
GROUP BY et.id
ORDER BY et.year DESC, et.id DESC
LIMIT 6";
$exam_trend_result = mysqli_query($conn, $exam_trend_sql);
$exam_trends = [];
while ($row = mysqli_fetch_assoc($exam_trend_result)) {
    $exam_trends[] = $row;
}
$exam_trends = array_reverse($exam_trends);

// Get top performing students across both forms
$top_students_sql = "SELECT 
    'Form Five' as form,
    s.index_number,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    s.combination,
    fr.total_points,
    fr.average,
    fr.division
FROM students s
JOIN form_five_results fr ON s.id = fr.student_id
WHERE fr.total_points IS NOT NULL
ORDER BY fr.total_points ASC, fr.average DESC
LIMIT 5";
$top_students_result = mysqli_query($conn, $top_students_sql);
$top_students = [];
while ($row = mysqli_fetch_assoc($top_students_result)) {
    $top_students[] = $row;
}

// If not enough Form Five students, get from Form Six
if (count($top_students) < 5) {
    $top_six_sql = "SELECT 
        'Form Six' as form,
        s.index_number,
        CONCAT(s.first_name, ' ', s.last_name) as student_name,
        s.combination,
        fr.total_points,
        fr.average,
        fr.division
    FROM students s
    JOIN form_six_results fr ON s.id = fr.student_id
    WHERE fr.total_points IS NOT NULL
    ORDER BY fr.total_points ASC, fr.average DESC
    LIMIT 5";
    $top_six_result = mysqli_query($conn, $top_six_sql);
    while ($row = mysqli_fetch_assoc($top_six_result)) {
        $top_students[] = $row;
    }
}
$top_students = array_slice($top_students, 0, 5);

// Get subject performance comparison between forms
$subjects = ['ac', 'htm', 'his', 'geo', 'kisw', 'eng', 'b_math', 'adv_m', 'eco', 'fren'];
$subject_names = [
    'ac' => 'AC', 'htm' => 'HTM', 'his' => 'HIST', 'geo' => 'GEO',
    'kisw' => 'KISW', 'eng' => 'ENG', 'b_math' => 'B/MATH',
    'adv_m' => 'ADV/M', 'eco' => 'ECO', 'fren' => 'FREN'
];
$subject_performance = [];
foreach ($subjects as $subject) {
    $subject_performance[$subject] = [
        'name' => $subject_names[$subject],
        'form_five' => round($academic_five['avg_' . $subject] ?? 0, 1),
        'form_six' => round($academic_six['avg_' . $subject] ?? 0, 1)
    ];
}

// Get division distribution for both forms
$division_distribution = [
    'Division I' => [
        'form_five' => $academic_five['div1_count'] ?? 0,
        'form_six' => $academic_six['div1_count'] ?? 0
    ],
    'Division II' => [
        'form_five' => $academic_five['div2_count'] ?? 0,
        'form_six' => $academic_six['div2_count'] ?? 0
    ],
    'Division III' => [
        'form_five' => $academic_five['div3_count'] ?? 0,
        'form_six' => $academic_six['div3_count'] ?? 0
    ],
    'Division IV' => [
        'form_five' => $academic_five['div4_count'] ?? 0,
        'form_six' => $academic_six['div4_count'] ?? 0
    ],
    'Division 0' => [
        'form_five' => $academic_five['div0_count'] ?? 0,
        'form_six' => $academic_six['div0_count'] ?? 0
    ]
];

// Get grade distribution (A-F) based on average scores
$grade_distribution = [
    'A (80-100)' => 0, 'B (70-79)' => 0, 'C (60-69)' => 0,
    'D (50-59)' => 0, 'E (40-49)' => 0, 'S (35-39)' => 0, 'F (0-34)' => 0
];

$grade_sql = "SELECT average FROM form_five_results WHERE average IS NOT NULL 
              UNION ALL 
              SELECT average FROM form_six_results WHERE average IS NOT NULL";
$grade_result = mysqli_query($conn, $grade_sql);
while ($row = mysqli_fetch_assoc($grade_result)) {
    $avg = $row['average'];
    if ($avg >= 80) $grade_distribution['A (80-100)']++;
    elseif ($avg >= 70) $grade_distribution['B (70-79)']++;
    elseif ($avg >= 60) $grade_distribution['C (60-69)']++;
    elseif ($avg >= 50) $grade_distribution['D (50-59)']++;
    elseif ($avg >= 40) $grade_distribution['E (40-49)']++;
    elseif ($avg >= 35) $grade_distribution['S (35-39)']++;
    else $grade_distribution['F (0-34)']++;
}

// Calculate percentages
$total_students = ($stats['form_five_count'] ?? 0) + ($stats['form_six_count'] ?? 0);
$total_staff = ($stats['active_staff'] ?? 0) + ($stats['inactive_staff'] ?? 0);
$dorm_occupancy_percent = $stats['total_dorm_capacity'] > 0 
    ? round(($stats['total_dorm_occupancy'] * 100 / $stats['total_dorm_capacity']), 1) 
    : 0;

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

// Get current date and time
$current_time = date('l, F j, Y');
$greeting = '';
$hour = date('H');

if ($hour >= 5 && $hour < 12) {
    $greeting = 'Good Morning 🌄';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = 'Good Afternoon ☀️';
} elseif ($hour >= 17 && $hour < 21) {
    $greeting = 'Good Evening 🌆';
} else {
    $greeting = 'Good Night 🌃';
}

// Get combination distribution for chart
$combo_stats_sql = "SELECT combination, COUNT(*) as student_count
                    FROM students 
                    WHERE is_leaver = 0
                    GROUP BY combination
                    ORDER BY student_count DESC";
$combo_stats = mysqli_query($conn, $combo_stats_sql);
$combo_labels = [];
$combo_data = [];
$combo_colors = [$colors['lime_green'], $colors['forest_green'], $colors['primary'], 
                 $colors['coral'], $colors['sky_blue'], $colors['aqua_blue']];

if ($combo_stats && mysqli_num_rows($combo_stats) > 0) {
    mysqli_data_seek($combo_stats, 0);
    while ($combo = mysqli_fetch_assoc($combo_stats)) {
        $combo_labels[] = $combo['combination'] . ' (' . $combo['student_count'] . ')';
        $combo_data[] = (int)$combo['student_count'];
    }
}

// Get dormitory data for chart
$dorm_summary_sql = "SELECT dorm_name, dorm_type, current_occupancy, total_capacity,
                     (current_occupancy * 100 / total_capacity) as occupancy_percentage
                     FROM dormitories 
                     WHERE status = 'Active'
                     ORDER BY dorm_type, dorm_name";
$dorm_summary = mysqli_query($conn, $dorm_summary_sql);
$dorm_labels = [];
$dorm_occupied = [];
$dorm_capacity = [];

if ($dorm_summary && mysqli_num_rows($dorm_summary) > 0) {
    mysqli_data_seek($dorm_summary, 0);
    while ($dorm = mysqli_fetch_assoc($dorm_summary)) {
        $dorm_labels[] = $dorm['dorm_name'];
        $dorm_occupied[] = (int)$dorm['current_occupancy'];
        $dorm_capacity[] = (int)$dorm['total_capacity'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Muyovozi High School</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
            --font-size-base: <?php echo $font_size_value; ?>;
            --spacing-base: <?php echo $preferences['compact_mode'] === '1' ? '0.75rem' : '1rem'; ?>;
            --animation-duration: <?php echo $animation_duration; ?>;
        }

        * {
            transition: <?php echo $preferences['animations'] === '1' ? 'all var(--animation-duration) ease' : 'none'; ?>;
        }

        body {
            background: <?php echo $bg_style; ?>;
            background-size: <?php echo $bg_size; ?>;
            background-position: center;
            min-height: 100vh;
            color: var(--text-color);
            line-height: 1.6;
            font-size: var(--font-size-base);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

        /* Main Content */
        .main-content {
            min-height: calc(100vh - 60px);
            padding: 20px;
            transition: margin-left var(--animation-duration) ease;
            border-radius: 8px;
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

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 20px;
            padding: 35px;
            margin-bottom: 30px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.25);
            animation: slideDown 1s ease-out;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            animation: rotate 25s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .welcome-card h2 {
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 2.2rem;
            position: relative;
            z-index: 1;
        }

        .welcome-card p {
            opacity: 0.9;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 22px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
            border-left: 5px solid var(--primary-color);
            animation: fadeInUp 0.8s ease-out;
            animation-fill-mode: both;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.15s; }
        .stat-card:nth-child(3) { animation-delay: 0.2s; }
        .stat-card:nth-child(4) { animation-delay: 0.25s; }
        .stat-card:nth-child(5) { animation-delay: 0.3s; }
        .stat-card:nth-child(6) { animation-delay: 0.35s; }
        .stat-card:nth-child(7) { animation-delay: 0.4s; }
        .stat-card:nth-child(8) { animation-delay: 0.45s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 35px rgba(0,0,0,0.15);
            border-left-color: var(--primary-dark);
        }

        .stat-card .stat-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 4rem;
            opacity: 0.12;
            transition: all 0.8s ease;
            color: var(--primary-color);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.3) rotate(8deg);
            opacity: 0.2;
            color: var(--primary-dark);
        }

        .stat-card .stat-label {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
        }

        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 5px 0 8px;
            color: var(--text-color);
            line-height: 1.2;
            transition: all 0.4s ease;
        }

        .stat-card:hover h3 {
            color: var(--primary-dark);
        }

        .stat-card p {
            color: var(--text-light);
            margin: 0;
            font-size: 0.95rem;
        }

        .stat-card .stat-footer {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid rgba(0,0,0,0.1);
            font-size: 0.9rem;
            color: var(--text-light);
        }

        /* Progress Bars */
        .progress {
            height: 10px;
            border-radius: 8px;
            background: var(--gray);
            margin: 12px 0 8px;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            transition: width 2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            border-radius: 8px;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255,255,255,0.3), 
                transparent);
            animation: shimmer 2.5s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Chart Containers */
        .chart-container {
            background: var(--white);
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            transition: all 0.6s ease;
            animation: fadeIn 1s ease-out;
            border: 1px solid rgba(0,0,0,0.1);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .chart-container:hover {
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            transform: translateY(-5px);
            border-color: var(--primary-light);
        }

        .chart-container h5 {
            color: var(--primary-dark);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid rgba(0,0,0,0.1);
            font-size: 1.2rem;
        }

        .chart-container h5 i {
            color: var(--primary-color);
            margin-right: 8px;
        }

        canvas {
            transition: all 0.8s ease;
            animation: chartPop 1.2s ease-out;
            max-height: 300px;
            width: 100% !important;
        }

        @keyframes chartPop {
            0% {
                opacity: 0;
                transform: scale(0.9);
            }
            50% {
                opacity: 0.8;
                transform: scale(1.02);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Top Students Table */
        .top-students-table {
            width: 100%;
            font-size: 0.9rem;
        }
        .top-students-table thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 12px;
            font-weight: 600;
        }
        .top-students-table tbody tr:hover {
            background: rgba(0,0,0,0.05);
        }
        .rank-badge {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            font-weight: bold;
            font-size: 0.85rem;
        }
        .rank-1 { background: #FFD700; color: #333; }
        .rank-2 { background: #C0C0C0; color: #333; }
        .rank-3 { background: #CD7F32; color: white; }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card h3 {
                font-size: 2rem;
            }
            
            .welcome-card h2 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    
<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<main class="main-content <?php echo (isset($preferences['sidebar_collapsed']) && $preferences['sidebar_collapsed'] === '1') ? 'sidebar-collapsed' : 'sidebar-open'; ?>">
    <div class="container-fluid">
        
        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><?php echo $greeting; ?>, <?php echo htmlspecialchars($admin_name); ?>!</h2>
                    <p class="mb-2">Today is: <?php echo $current_time; ?></p>
                    <?php if (!empty($admin_roles)): ?>
                        <div class="mt-3">
                            <?php foreach ($admin_roles as $role): ?>
                                <?php if (!empty(trim($role))): ?>
                                    <span class="badge bg-white text-dark me-2 px-3 py-2" style="opacity: 0.9;">
                                        <i class="fas fa-circle me-1" style="color: var(--primary-color); font-size: 8px;"></i>
                                        <?php echo htmlspecialchars(trim($role)); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-md-end">
                    <i class="fas fa-school fa-5x opacity-50"></i>
                </div>
            </div>
        </div>
        
        <!-- Key Statistics Cards -->
        <div class="stats-grid">
            <!-- Total Students -->
            <div class="stat-card">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-label">Total Students</div>
                <h3><?php echo number_format($total_students); ?></h3>
                <p>
                    <span class="fw-bold" style="color: var(--primary-color);"><?php echo $stats['form_five_count'] ?? 0; ?> Form V</span> • 
                    <span class="fw-bold" style="color: var(--primary-dark);"><?php echo $stats['form_six_count'] ?? 0; ?> Form VI</span>
                </p>
                <div class="stat-footer">
                    <i class="fas fa-venus-mars me-1" style="color: var(--primary-color);"></i>
                    Male: <?php echo $stats['male_count'] ?? 0; ?> | Female: <?php echo $stats['female_count'] ?? 0; ?>
                </div>
            </div>
            
            <!-- Staff -->
            <div class="stat-card">
                <i class="fas fa-chalkboard-teacher stat-icon"></i>
                <div class="stat-label">Staff</div>
                <h3><?php echo number_format($total_staff); ?></h3>
                <p>
                    <span class="text-success fw-bold"><?php echo $stats['active_staff'] ?? 0; ?> Active</span> • 
                    <span class="text-danger fw-bold"><?php echo $stats['inactive_staff'] ?? 0; ?> Inactive</span>
                </p>
                <div class="stat-footer">
                    <i class="fas fa-lock me-1 text-warning"></i>
                    <?php echo $stats['locked_staff'] ?? 0; ?> Locked Accounts
                </div>
            </div>
            
            <!-- Overall Average Score -->
            <div class="stat-card">
                <i class="fas fa-chart-line stat-icon"></i>
                <div class="stat-label">Overall Average Score</div>
                <h3><?php echo round((($academic_five['avg_score'] ?? 0) + ($academic_six['avg_score'] ?? 0)) / 2, 1); ?>%</h3>
                <p>
                    <span class="fw-bold text-info">Form V: <?php echo round($academic_five['avg_score'] ?? 0, 1); ?>%</span> • 
                    <span class="fw-bold text-success">Form VI: <?php echo round($academic_six['avg_score'] ?? 0, 1); ?>%</span>
                </p>
                <div class="stat-footer">
                    <i class="fas fa-trophy me-1 text-warning"></i>
                    Top Division: <?php echo ($academic_five['div1_count'] ?? 0) + ($academic_six['div1_count'] ?? 0); ?> Division I
                </div>
            </div>
            
            <!-- Dormitory Occupancy -->
            <div class="stat-card">
                <i class="fas fa-bed stat-icon"></i>
                <div class="stat-label">Dormitory Occupancy</div>
                <h3><?php echo $stats['total_dorm_occupancy'] ?? 0; ?> / <?php echo $stats['total_dorm_capacity'] ?? 0; ?></h3>
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo $dorm_occupancy_percent; ?>%"></div>
                </div>
                <div class="stat-footer d-flex justify-content-between">
                    <span><i class="fas fa-building me-1"></i><?php echo $stats['active_dorms'] ?? 0; ?> Active Dorms</span>
                    <span class="fw-bold"><?php echo $dorm_occupancy_percent; ?>% Full</span>
                </div>
            </div>
            
            <!-- Division I Students -->
            <div class="stat-card">
                <i class="fas fa-award stat-icon"></i>
                <div class="stat-label">Division I Students</div>
                <h3><?php echo ($academic_five['div1_count'] ?? 0) + ($academic_six['div1_count'] ?? 0); ?></h3>
                <p>
                    <span class="fw-bold text-success">Form V: <?php echo $academic_five['div1_count'] ?? 0; ?></span> • 
                    <span class="fw-bold text-info">Form VI: <?php echo $academic_six['div1_count'] ?? 0; ?></span>
                </p>
                <div class="stat-footer">
                    <i class="fas fa-star me-1 text-warning"></i>
                    Top Performers
                </div>
            </div>
            
            <!-- Exams Completed -->
            <div class="stat-card">
                <i class="fas fa-clipboard-list stat-icon"></i>
                <div class="stat-label">Exams Completed</div>
                <h3><?php echo count($exam_trends); ?></h3>
                <p>Records with results</p>
                <div class="stat-footer">
                    <i class="fas fa-calendar me-1"></i>
                    Latest: <?php echo !empty($exam_trends) ? $exam_trends[count($exam_trends)-1]['exam_name'] . ' (' . $exam_trends[count($exam_trends)-1]['year'] . ')' : 'N/A'; ?>
                </div>
            </div>
        </div>
        
        <!-- Academic Performance Charts Row 1 -->
        <div class="row">
            <!-- Subject Performance Comparison -->
            <div class="col-lg-7">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-bar"></i>Subject Performance Comparison (Form V vs Form VI)</h5>
                    <div style="height: 350px; position: relative;">
                        <canvas id="subjectComparisonChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Division Distribution -->
            <div class="col-lg-5">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-pie"></i>Division Distribution by Form</h5>
                    <div style="height: 350px; position: relative;">
                        <canvas id="divisionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Academic Performance Charts Row 2 -->
        <div class="row">
            <!-- Exam Trend Chart -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-line"></i>Exam Performance Trend</h5>
                    <div style="height: 300px; position: relative;">
                        <canvas id="examTrendChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Grade Distribution -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-line"></i>Grade Distribution (All Students)</h5>
                    <div style="height: 300px; position: relative;">
                        <canvas id="gradeDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Performing Students Table -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <h5><i class="fas fa-medal"></i>Top Performing Students</h5>
                    <div class="table-responsive">
                        <table class="table top-students-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Index No</th>
                                    <th>Student Name</th>
                                    <th>Form</th>
                                    <th>Combination</th>
                                    <th>Total Points</th>
                                    <th>Average</th>
                                    <th>Division</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_students)): ?>
                                    <tr><td colspan="8" class="text-center">No result data available</td></tr>
                                <?php else: ?>
                                    <?php $rank = 1; foreach ($top_students as $student): ?>
                                        <tr>
                                            <td>
                                                <span class="rank-badge <?php echo $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : '')); ?>">
                                                    <?php echo $rank++; ?>
                                                </span>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($student['index_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $student['form']; ?></span></td>
                                            <td><span class="badge bg-secondary"><?php echo $student['combination']; ?></span></td>
                                            <td><strong><?php echo $student['total_points']; ?></strong></td>
                                            <td><?php echo number_format($student['average'], 1); ?>%</td>
                                            <td>
                                                <?php if ($student['division']): ?>
                                                    <span class="badge <?php echo str_replace(' ', '-', strtolower($student['division'])); ?>">
                                                        <?php echo $student['division']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dormitory Occupancy Chart -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <h5><i class="fas fa-bed"></i>Dormitory Occupancy Overview</h5>
                    <div style="height: 300px; position: relative;">
                        <canvas id="dormChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</main>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Initialize charts with smooth animations
document.addEventListener('DOMContentLoaded', function() {
    
    // Subject Performance Comparison Chart (Grouped Bar)
    const subjectCtx = document.getElementById('subjectComparisonChart').getContext('2d');
    const subjectLabels = <?php echo json_encode(array_column($subject_performance, 'name')); ?>;
    const formFiveData = <?php echo json_encode(array_column($subject_performance, 'form_five')); ?>;
    const formSixData = <?php echo json_encode(array_column($subject_performance, 'form_six')); ?>;
    
    new Chart(subjectCtx, {
        type: 'bar',
        data: {
            labels: subjectLabels,
            datasets: [
                {
                    label: 'Form Five',
                    data: formFiveData,
                    backgroundColor: '<?php echo $colors['primary']; ?>',
                    borderRadius: 8,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                },
                {
                    label: 'Form Six',
                    data: formSixData,
                    backgroundColor: '<?php echo $colors['lime_green']; ?>',
                    borderRadius: 8,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    title: { display: true, text: 'Average Score (%)' }
                },
                x: {
                    grid: { display: false },
                    title: { display: true, text: 'Subjects' }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.raw}%`;
                        }
                    }
                }
            }
        }
    });

    // Division Distribution Chart (Grouped Bar)
    const divisionCtx = document.getElementById('divisionChart').getContext('2d');
    const divisionLabels = <?php echo json_encode(array_keys($division_distribution)); ?>;
    const divisionFiveData = <?php echo json_encode(array_column($division_distribution, 'form_five')); ?>;
    const divisionSixData = <?php echo json_encode(array_column($division_distribution, 'form_six')); ?>;
    
    new Chart(divisionCtx, {
        type: 'bar',
        data: {
            labels: divisionLabels,
            datasets: [
                {
                    label: 'Form Five',
                    data: divisionFiveData,
                    backgroundColor: '<?php echo $colors['primary']; ?>',
                    borderRadius: 8,
                    barPercentage: 0.7
                },
                {
                    label: 'Form Six',
                    data: divisionSixData,
                    backgroundColor: '<?php echo $colors['coral']; ?>',
                    borderRadius: 8,
                    barPercentage: 0.7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    title: { display: true, text: 'Number of Students' }
                },
                x: {
                    grid: { display: false }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.raw} students`;
                        }
                    }
                }
            }
        }
    });

    // Exam Trend Chart (Line)
    const trendCtx = document.getElementById('examTrendChart').getContext('2d');
    const trendLabels = <?php 
        $labels = [];
        foreach ($exam_trends as $trend) {
            $labels[] = $trend['exam_name'] . ' (' . $trend['year'] . ')';
        }
        echo json_encode($labels);
    ?>;
    const trendData = <?php echo json_encode(array_column($exam_trends, 'avg_score')); ?>;
    
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Average Score (%)',
                data: trendData,
                borderColor: '<?php echo $colors['primary']; ?>',
                backgroundColor: 'rgba(59, 157, 179, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '<?php echo $colors['primary_dark']; ?>',
                pointBorderColor: 'white',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    title: { display: true, text: 'Average Score (%)' }
                },
                x: {
                    grid: { display: false }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.raw ? `${context.raw.toFixed(1)}%` : 'No data';
                        }
                    }
                }
            }
        }
    });

    // Grade Distribution Chart (Bar)
    const gradeCtx = document.getElementById('gradeDistributionChart').getContext('2d');
    const gradeLabels = <?php echo json_encode(array_keys($grade_distribution)); ?>;
    const gradeData = <?php echo json_encode(array_values($grade_distribution)); ?>;
    
    new Chart(gradeCtx, {
        type: 'bar',
        data: {
            labels: gradeLabels,
            datasets: [{
                label: 'Number of Students',
                data: gradeData,
                backgroundColor: [
                    '#27ae60', '#2ecc71', '#f39c12', 
                    '#e67e22', '#95a5a6', '#7f8c8d', '#e74c3c'
                ],
                borderRadius: 8,
                barPercentage: 0.7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    title: { display: true, text: 'Number of Students' }
                },
                x: {
                    grid: { display: false },
                    title: { display: true, text: 'Grade Range' }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.raw} students`;
                        }
                    }
                }
            }
        }
    });

    // Dormitory Occupancy Chart (Stacked Bar)
    const dormCtx = document.getElementById('dormChart').getContext('2d');
    new Chart(dormCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($dorm_labels); ?>,
            datasets: [
                {
                    label: 'Occupied Beds',
                    data: <?php echo json_encode($dorm_occupied); ?>,
                    backgroundColor: '<?php echo $colors['lime_green']; ?>',
                    borderRadius: 8,
                    barPercentage: 0.7,
                    stack: 'Stack 0'
                },
                {
                    label: 'Available Beds',
                    data: <?php echo json_encode(array_map(function($cap, $occ) { 
                        return $cap - $occ; 
                    }, $dorm_capacity, $dorm_occupied)); ?>,
                    backgroundColor: '<?php echo $colors['gray']; ?>',
                    borderRadius: 8,
                    barPercentage: 0.7,
                    stack: 'Stack 0'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    title: { display: true, text: 'Number of Beds' }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true, pointStyle: 'rectRounded' }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.raw} beds`;
                        }
                    }
                }
            }
        }
    });

    // Animate progress bars on page load
    setTimeout(() => {
        document.querySelectorAll('.progress-bar').forEach(bar => {
            let width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 500);
        });
    }, 500);

    // Add hover effect to stat cards
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1)';
        });
    });
});

// Auto-refresh page every 5 minutes
setTimeout(function() {
    location.reload();
}, 300000);
</script>

<?php include '../controller/footer.php'; ?>
</body>
</html>