<?php
// timetable.php - Role-based timetable access
session_start();
require_once '../controller/db_connect.php';

// Check login
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

$admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;

// Get user's roles
$user_roles_sql = "SELECT ar.role_name, ara.is_primary 
                   FROM admin_role_assignments ara
                   JOIN admin_roles ar ON ara.role_id = ar.id
                   WHERE ara.admin_id = $admin_id";
$user_roles_result = mysqli_query($conn, $user_roles_sql);
$user_roles = [];
$is_academic_admin = false;

if ($user_roles_result && mysqli_num_rows($user_roles_result) > 0) {
    while ($row = mysqli_fetch_assoc($user_roles_result)) {
        $user_roles[] = $row['role_name'];
        // Check if user has academic management roles
        if (in_array($row['role_name'], ['Head Master', 'Second Master', 'Academic Master'])) {
            $is_academic_admin = true;
        }
    }
}

// If user is a regular teacher (no academic admin role), redirect to teacher view
if (!$is_academic_admin) {
    header("Location: teacher_timetable.php");
    exit();
}

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

// Get school name for display
$school_name = "School Management System";
$school_query = "SELECT s.school_name FROM admins a JOIN schools s ON a.school_id = s.id WHERE a.id = $admin_id";
$school_result = mysqli_query($conn, $school_query);
if ($school_result && $row = mysqli_fetch_assoc($school_result)) {
    $school_name = $row['school_name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Management - <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f0f2f5;
            font-size: var(--font-size-base);
            min-height: 100vh;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

        <?php if ($compact_mode): ?>
        .card-body { padding: 1rem !important; }
        <?php endif; ?>

        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 40px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(45deg);
        }

        .hero-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }

        .hero-subtitle {
            font-size: 16px;
            opacity: 0.95;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }

        .hero-icon {
            position: absolute;
            bottom: 20px;
            right: 30px;
            font-size: 120px;
            opacity: 0.15;
            z-index: 0;
        }

        .timetable-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            position: relative;
        }

        .timetable-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        .card-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            z-index: 1;
        }

        .card-icon {
            font-size: 60px;
            padding: 30px 30px 0 30px;
            text-align: center;
        }

        .card-icon i {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .card-title {
            font-size: 24px;
            font-weight: 700;
            margin: 20px 30px 10px;
            color: var(--text-color);
        }

        .card-description {
            font-size: 14px;
            color: var(--text-light);
            margin: 0 30px 20px;
            line-height: 1.6;
        }

        .card-features {
            padding: 0 30px 20px;
            border-top: 1px solid var(--border-color);
            margin-top: 10px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            font-size: 13px;
            color: var(--text-light);
        }

        .feature-item i {
            width: 20px;
            color: var(--primary-color);
            font-size: 14px;
        }

        .card-footer-btn {
            padding: 15px 30px 25px;
            text-align: center;
        }

        .btn-create {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-create:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(59, 157, 179, 0.4);
        }

        .stats-section {
            margin-top: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 10px;
        }

        .stat-icon {
            font-size: 40px;
            color: var(--primary-light);
            margin-bottom: 10px;
        }

        .info-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
        }

        .info-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 15px;
        }

        .info-text {
            font-size: 13px;
            color: var(--text-light);
            line-height: 1.6;
        }

        .quick-link {
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 10px;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 13px;
        }

        .quick-link:hover {
            text-decoration: underline;
        }

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

        .animate-in {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="hero-section animate-in">
                <div class="hero-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h1 class="hero-title">
                    <i class="fas fa-clock me-2"></i>Timetable Management
                </h1>
                <p class="hero-subtitle">
                    Create, manage and publish timetables for academic sessions.<br>
                    Streamline scheduling and ensure efficient time management for your institution.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-6 animate-in" style="animation-delay: 0.1s;">
                    <div class="timetable-card">
                        <div class="card-badge">
                            <i class="fas fa-star me-1"></i> ADMIN ACCESS
                        </div>
                        <div class="card-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h3 class="card-title">Session Timetable</h3>
                        <p class="card-description">
                            Create comprehensive daily class schedules with teacher assignments, subject allocation, and period management for Form 5 and Form 6.
                        </p>
                        <div class="card-features">
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Form 5 & Form 6 combinations</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Customizable session times and break periods</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Export to Excel, PDF, or CSV formats</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Teacher assignment and workload management</span>
                            </div>
                        </div>
                        <div class="card-footer-btn">
                            <a href="session_timetable.php" class="btn btn-create">
                                <i class="fas fa-plus-circle me-2"></i>Create Session Timetable
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-6 animate-in" style="animation-delay: 0.2s;">
                    <div class="timetable-card">
                        <div class="card-badge" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-calendar-week me-1"></i> COMING SOON
                        </div>
                        <div class="card-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3 class="card-title">Exam Timetable</h3>
                        <p class="card-description">
                            Plan and organize examination schedules with room allocation, invigilator assignments, and student seating arrangements.
                        </p>
                        <div class="card-features">
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Exam schedule planning and conflict detection</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Room and invigilator allocation system</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Student seating arrangement management</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Printable exam cards and notices</span>
                            </div>
                        </div>
                        <div class="card-footer-btn">
                            <button class="btn btn-create" disabled style="opacity: 0.6; cursor: not-allowed;">
                                <i class="fas fa-clock me-2"></i>Coming Soon
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-section">
                <div class="row g-4">
                    <div class="col-md-3 col-sm-6 animate-in" style="animation-delay: 0.3s;">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number" id="statForm5">0</div>
                            <div class="stat-label">Form 5 Combinations</div>
                            <small class="text-muted">Active classes</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 animate-in" style="animation-delay: 0.35s;">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="stat-number" id="statForm6">0</div>
                            <div class="stat-label">Form 6 Combinations</div>
                            <small class="text-muted">Active classes</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 animate-in" style="animation-delay: 0.4s;">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div class="stat-number" id="statTeachers">0</div>
                            <div class="stat-label">Total Teachers</div>
                            <small class="text-muted">Active staff</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 animate-in" style="animation-delay: 0.45s;">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-file-export"></i>
                            </div>
                            <div class="stat-number">3</div>
                            <div class="stat-label">Export Formats</div>
                            <small class="text-muted">Excel, PDF, CSV</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="info-section animate-in" style="animation-delay: 0.5s;">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="info-title">
                            <i class="fas fa-info-circle me-2" style="color: var(--primary-color);"></i>
                            About Timetable Management
                        </h5>
                        <p class="info-text">
                            The timetable management system allows you to create structured schedules for academic sessions.
                            The system includes intelligent teacher assignment to avoid scheduling conflicts and supports multiple 
                            export formats for easy distribution.
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h5 class="info-title">
                            <i class="fas fa-link me-2" style="color: var(--primary-color);"></i>
                            Quick Links
                        </h5>
                        <div>
                            <a href="session_timetable.php" class="quick-link">
                                <i class="fas fa-chevron-right me-1"></i> Create Session Timetable
                            </a>
                            <a href="teacher_timetable.php" class="quick-link">
                                <i class="fas fa-chevron-right me-1"></i> View Published Timetables
                            </a>
                            <a href="../manage_teachers/admins.php" class="quick-link">
                                <i class="fas fa-chevron-right me-1"></i> Manage Teachers
                            </a>
                            <a href="../manage_subjects/assign_subject.php" class="quick-link">
                                <i class="fas fa-chevron-right me-1"></i> Assign Subjects
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../controller/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function loadStats() {
            $.ajax({
                url: 'get_stats.php',
                method: 'GET',
                dataType: 'json',
                timeout: 5000,
                success: function(data) {
                    if (data.success) {
                        $('#statForm5').text(data.form5_count || 0);
                        $('#statForm6').text(data.form6_count || 0);
                        $('#statTeachers').text(data.teachers_count || 0);
                    } else {
                        console.log('Error loading stats:', data.error);
                        // Set default values
                        $('#statForm5').text('N/A');
                        $('#statForm6').text('N/A');
                        $('#statTeachers').text('N/A');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Could not load stats:', error);
                    $('#statForm5').text('N/A');
                    $('#statForm6').text('N/A');
                    $('#statTeachers').text('N/A');
                }
            });
        }
        
        loadStats();

        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.animate-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            observer.observe(el);
        });

        const cards = document.querySelectorAll('.timetable-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-10px)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>