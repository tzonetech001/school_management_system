<?php
// timetable.php - Display timetable coming soon page
session_start();
require_once '../controller/db_connect.php';

// Check login
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

$admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;

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
    'border' => '#e0e0e0'
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

// Font size and compact mode
$font_sizes = ['10' => '10px', '12' => '12px', '14' => '14px', '16' => '16px', '18' => '18px'];
$font_size = isset($preferences['font_size']) ? $font_sizes[$preferences['font_size']] : '16px';
$compact_mode = isset($preferences['compact_mode']) && $preferences['compact_mode'] === '1';
$animations = isset($preferences['animations']) && $preferences['animations'] === '1';
$animation_speed = isset($preferences['animation_speed']) ? $preferences['animation_speed'] : 'normal';
$animation_time = $animation_speed === 'slow' ? '0.5s' : ($animation_speed === 'fast' ? '0.15s' : '0.3s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable - School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $colors['primary']; ?>;
            --primary-dark: <?php echo $colors['primary_dark']; ?>;
            --primary-light: <?php echo $colors['primary_light']; ?>;
            --text-color: <?php echo $colors['text']; ?>;
            --text-light: <?php echo $colors['text_light']; ?>;
            --border-color: <?php echo $colors['border']; ?>;
            --font-size-base: <?php echo $font_size; ?>;
            --spacing-base: <?php echo $compact_mode ? '0.75rem' : '1rem'; ?>;
            --animation-speed: <?php echo $animation_time; ?>;
        }

        * {
            transition: <?php echo $animations ? 'all var(--animation-speed) ease' : 'none'; ?>;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            font-size: var(--font-size-base);
        }

        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
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
        <?php endif; ?>

        .coming-soon-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 60px 40px;
            text-align: center;
            margin-top: 50px;
        }

        .coming-soon-icon {
            font-size: 80px;
            color: var(--primary-color);
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .coming-soon-title {
            font-size: 48px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .coming-soon-text {
            font-size: 18px;
            color: var(--text-light);
            margin-bottom: 30px;
        }

        .progress-container {
            max-width: 400px;
            margin: 30px auto;
        }

        .progress {
            height: 10px;
            border-radius: 10px;
            background: var(--gray);
        }

        .progress-bar {
            background: var(--primary-color);
            border-radius: 10px;
            width: 65%;
            animation: loading 2s ease-in-out infinite;
        }

        @keyframes loading {
            0% { width: 45%; }
            50% { width: 75%; }
            100% { width: 45%; }
        }

        .feature-list {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 40px;
        }

        .feature-item {
            text-align: center;
            padding: 15px;
        }

        .feature-item i {
            font-size: 30px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .feature-item span {
            display: block;
            font-size: 14px;
            color: var(--text-light);
        }

        @media (max-width: 768px) {
            .coming-soon-title {
                font-size: 32px;
            }
            
            .coming-soon-card {
                padding: 40px 20px;
            }
            
            .coming-soon-icon {
                font-size: 60px;
            }
        }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="coming-soon-card">
                <div class="coming-soon-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h1 class="coming-soon-title">Time Table</h1>
                <p class="coming-soon-text">
                    We are working on creating a comprehensive timetable system.<br>
                    Class schedules and exam timetables will be available soon.
                </p>
                
                <div class="progress-container">
                    <div class="progress">
                        <div class="progress-bar"></div>
                    </div>
                    <p class="text-muted mt-2 small">In Development</p>
                </div>

                <div class="feature-list">
                    <div class="feature-item">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Class Schedules</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-clock"></i>
                        <span>Subject Timings</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-file-alt"></i>
                        <span>Exam Timetables</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-bell"></i>
                        <span>Reminders</span>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="academic.php" class="btn btn-primary" style="background: var(--primary-color); border: none; padding: 10px 30px;">
                        <i class="fas fa-home me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include '../controller/footer.php'; ?>
</body>
</html>