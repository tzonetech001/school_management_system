<?php
// timetable.php - Admin dashboard with existing timetables
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

// Non-academic users should use the teacher-facing timetable view.
if (!$is_academic_admin) {
    header('Location: teacher_timetable.php');
    exit();
}

// Get school_id
$school_query = "SELECT school_id FROM admins WHERE id = $admin_id";
$school_result = mysqli_query($conn, $school_query);
$school_data = mysqli_fetch_assoc($school_result);
$school_id = $school_data['school_id'];

// Handle delete - ADMIN CAN DELETE ANY TIMETABLE, others can delete only timetables they created
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $timetable_id = intval($_GET['id']);
    
    // Get the timetable details
    $check_sql = "SELECT filename FROM generated_timetables WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $timetable_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $timetable = $check_result->fetch_assoc();
        
        // Delete the physical file
        $file_path = '../uploads/timetables/' . $timetable['filename'] . '.html';
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database
        $delete_sql = "DELETE FROM generated_timetables WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $timetable_id);
        $delete_stmt->execute();
        
        $_SESSION['success'] = "Timetable deleted successfully!";
    } else {
        $_SESSION['error'] = "Timetable not found.";
    }
    
    header("Location: timetable.php");
    exit();
}

// Get existing timetables
$timetables_query = "SELECT gt.*, 
       CONCAT(gf.first_name, ' ', gf.last_name) AS generated_by_name, 
       CONCAT(uf.first_name, ' ', uf.last_name) AS updated_by_name 
       FROM generated_timetables gt 
       LEFT JOIN admins gf ON gt.generated_by = gf.id 
       LEFT JOIN admins uf ON gt.last_updated_by = uf.id 
       WHERE gt.school_id = $school_id 
       ORDER BY gt.year DESC, FIELD(gt.term, 'Term 02', 'Term 01')";
$timetables_result = mysqli_query($conn, $timetables_query);
$available_timetables = [];
while ($row = mysqli_fetch_assoc($timetables_result)) {
    $available_timetables[] = $row;
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

// Get teacher name
$teacher_query = "SELECT first_name, last_name FROM admins WHERE id = $admin_id";
$teacher_result = mysqli_query($conn, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_name = $teacher['first_name'] . ' ' . $teacher['last_name'];
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        /* Existing Timetables Section */
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primary-light);
        }

        .section-title i {
            color: var(--primary-color);
            margin-right: 10px;
        }

        .timetable-item-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
        }

        .timetable-item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .timetable-item-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 15px;
            text-align: center;
        }

        .timetable-item-body {
            padding: 20px;
            text-align: center;
        }

        .timetable-item-icon {
            font-size: 40px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .btn-download {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            margin: 3px;
            cursor: pointer;
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3);
        }

        .btn-view {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            margin: 3px;
            cursor: pointer;
        }

        .btn-view:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            margin: 3px;
            cursor: pointer;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-edit {
            background: #ffc107;
            color: #333;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            margin: 3px;
            cursor: pointer;
        }

        .btn-edit:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 12px;
        }

        .empty-icon {
            font-size: 60px;
            color: var(--primary-light);
            margin-bottom: 15px;
        }

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 5px;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 24px;
            }
            .hero-section {
                padding: 25px;
            }
            .action-buttons .btn {
                font-size: 11px;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Hero Section -->
            <div class="hero-section">
                <div class="hero-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h1 class="hero-title">
                    <i class="fas fa-clock me-2"></i>Timetable Management
                </h1>
                <p class="hero-subtitle">
                    Welcome, <?php echo htmlspecialchars($teacher_name); ?>! 
                    Create new timetables or manage existing ones below.
                </p>
            </div>

            <!-- Create New Section -->
            <div class="row g-4 mb-5">
                <div class="col-md-6">
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
                        <div class="card-footer-btn">
                            <a href="session_timetable.php" class="btn btn-create">
                                <i class="fas fa-plus-circle me-2"></i>Create Session Timetable
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
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
                        <div class="card-footer-btn">
                            <button class="btn btn-create" disabled style="opacity: 0.6; cursor: not-allowed;">
                                <i class="fas fa-clock me-2"></i>Coming Soon
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Existing Timetables Section -->
            <div class="section-title">
                <i class="fas fa-list"></i>
                Published Timetables
                <span class="badge bg-primary ms-2"><?php echo count($available_timetables); ?></span>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row align-items-center">
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="form-label"><i class="fas fa-filter me-1"></i>Term</label>
                        <select id="filterTerm" class="form-select">
                            <option value="all">All Terms</option>
                            <option value="Term 01">Term 01</option>
                            <option value="Term 02">Term 02</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="form-label"><i class="fas fa-calendar me-1"></i>Year</label>
                        <select id="filterYear" class="form-select">
                            <option value="all">All Years</option>
                            <?php
                            $years = array_unique(array_column($available_timetables, 'year'));
                            rsort($years);
                            foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button id="resetFilters" class="btn btn-secondary w-100">
                            <i class="fas fa-undo-alt me-2"></i>Reset Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Timetables Grid -->
            <div class="row" id="timetablesGrid">
                <?php if (empty($available_timetables)): ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-calendar-times"></i></div>
                            <h4>No Published Timetables</h4>
                            <p class="text-muted">Click "Create Session Timetable" above to generate your first timetable.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($available_timetables as $timetable): ?>
                        <div class="col-md-6 col-lg-4 mb-4 timetable-item" 
                             data-term="<?php echo htmlspecialchars($timetable['term']); ?>" 
                             data-year="<?php echo $timetable['year']; ?>">
                            <div class="timetable-item-card">
                                <div class="timetable-item-header">
                                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($timetable['term']); ?></h5>
                                    <small><?php echo $timetable['year']; ?></small>
                                    <?php if (!empty($timetable['generated_at'])): ?>
                                        <br>
                                        <small style="font-size:12px;opacity:0.95;color:#fff;">Generated: <?php echo date('l, F d, Y g:i A', strtotime($timetable['generated_at'])); ?></small>
                                    <?php endif; ?>
                                    <br>
                                    <small class="badge bg-warning text-dark mt-1">
                                        <i class="fas fa-crown me-1"></i>Admin Access
                                    </small>
                                </div>
                                <div class="timetable-item-body">
                                    <div class="timetable-item-icon"><i class="fas fa-file-alt"></i></div>
                                    <h6><?php echo htmlspecialchars($timetable['document_name'] ?: ($timetable['term'] . ' Timetable - ' . $timetable['year'])); ?></h6>
                                    <p class="text-muted small">
                                        <?php echo $timetable['sessions_per_day'] ?? '6'; ?> sessions | 
                                        Break: <?php echo ($timetable['break_after'] ?? 0) > 0 ? 'After Session ' . ($timetable['break_after'] ?? 0) : 'No break'; ?>
                                        <?php if (!empty($timetable['days'])): ?>
                                            <br>Days: <?php echo htmlspecialchars($timetable['days']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($timetable['last_updated_by'])): ?>
                                            <br>Updated by: <?php echo htmlspecialchars($timetable['last_updated_by']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-download" onclick="event.preventDefault(); downloadTimetable('<?php echo htmlspecialchars($timetable['filename']); ?>')">
                                            <i class="fas fa-download me-1"></i>Download
                                        </button>
                                        <button type="button" class="btn-view" onclick="event.preventDefault(); viewTimetable('<?php echo htmlspecialchars($timetable['filename']); ?>')">
                                            <i class="fas fa-eye me-1"></i>View
                                        </button>
                                        <button type="button" class="btn-edit" onclick="event.preventDefault(); editTimetable(<?php echo $timetable['id']; ?>)">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </button>
                                        <button type="button" class="btn-delete" onclick="event.preventDefault(); deleteTimetable(<?php echo $timetable['id']; ?>)">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Info Card -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Information:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Click <strong>View</strong> to open the timetable in your browser</li>
                            <li>Click <strong>Download</strong> to save the timetable to your device</li>
                            <li>Click <strong>Edit</strong> to modify timetable settings and regenerate</li>
                            <li>Click <strong>Delete</strong> to permanently remove the timetable from the system</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Timetable Modal -->
    <div class="modal fade" id="viewTimetableModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                    <h5 class="modal-title"><i class="fas fa-calendar-alt me-2"></i><span id="modalTitle">Timetable</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="modalBody">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-3">Loading timetable...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printModalBtn">
                        <i class="fas fa-print me-2"></i>Print / Save as PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include '../controller/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function filterTimetables() {
            const term = $('#filterTerm').val();
            const year = $('#filterYear').val();
            
            $('.timetable-item').each(function() {
                const itemTerm = $(this).data('term');
                const itemYear = $(this).data('year').toString();
                
                let show = true;
                if (term !== 'all' && itemTerm !== term) show = false;
                if (year !== 'all' && itemYear !== year) show = false;
                
                $(this).toggle(show);
            });
            
            if ($('.timetable-item:visible').length === 0 && $('.timetable-item').length > 0) {
                if ($('#noResultsMsg').length === 0) {
                    $('#timetablesGrid').append(`
                        <div id="noResultsMsg" class="col-12">
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No timetables match your filters.
                            </div>
                        </div>
                    `);
                }
            } else {
                $('#noResultsMsg').remove();
            }
        }
        
        $('#filterTerm, #filterYear').on('change', filterTimetables);
        
        $('#resetFilters').on('click', function() {
            $('#filterTerm').val('all');
            $('#filterYear').val('all');
            filterTimetables();
        });
        
        function downloadTimetable(filename) {
            window.location.href = 'download_timetable.php?file=' + encodeURIComponent(filename + '.html');
        }
        
        function viewTimetable(filename) {
            const displayName = filename.replace(/_/g, ' ');
            $('#modalTitle').text(displayName);
            $('#modalBody').html(`
                <div class="text-center p-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-3">Loading timetable...</p>
                </div>
            `);
            
            $.ajax({
                url: 'view_timetable.php?file=' + encodeURIComponent(filename + '.html'),
                success: function(response) {
                    $('#modalBody').html(response);
                },
                error: function() {
                    $('#modalBody').html(`
                        <div class="alert alert-danger m-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading timetable. Please try again.
                        </div>
                    `);
                }
            });
            
            new bootstrap.Modal(document.getElementById('viewTimetableModal')).show();
        }
        
        function deleteTimetable(id) {
            Swal.fire({
                title: 'Delete Timetable?',
                text: 'This will permanently remove this timetable from the system. All teachers will lose access to it. This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it permanently!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show processing message
                    Swal.fire({
                        title: 'Deleting...',
                        text: 'Please wait while the timetable is being removed.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Redirect to delete
                    window.location.href = 'timetable.php?delete=1&id=' + id;
                }
            });
        }
        
        function editTimetable(id) {
            window.location.href = 'session_timetable.php?edit=1&id=' + encodeURIComponent(id);
        }
        
        $('#printModalBtn').on('click', function() {
            const printContent = $('#modalBody').html();
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Timetable</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            body { padding: 20px; }
                            @media print { body { margin: 0; padding: 10px; } }
                        </style>
                    </head>
                    <body>
                        ${printContent}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        });
    </script>
</body>
</html>