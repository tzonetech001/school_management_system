<?php
// assign_subject.php - WITH PROPER HEADER HANDLING
// NO OUTPUT BEFORE THIS LINE - NOT EVEN SPACES OR BLANK LINES!
ob_start(); // Start output buffering at the very beginning
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

// Check if user has Head Master (1) or Second Master (2) or Academic Master (3) role
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 3) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to manage subject assignments.";
    header("Location: ../404.php");
    ob_end_flush();
    exit();
}

// Get filter parameters
$current_form = isset($_GET['form_level']) ? $_GET['form_level'] : 'Form Five';
$current_subject = isset($_GET['subject']) ? $_GET['subject'] : '';
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Valid subjects list
$subjects_list = [
    'ac' => 'Accountancy',
    'htm' => 'Hotel Management',
    'his' => 'History',
    'geo' => 'Geography',
    'kisw' => 'Kiswahili',
    'eng' => 'English Language',
    'b_math' => 'Basic Mathematics',
    'adv_m' => 'Advanced Mathematics',
    'eco' => 'Economics',
    'fren' => 'French',
    'phy' => 'Physics',
    'chem' => 'Chemistry',
    'bio' => 'Biology',
    'civ' => 'Civics',
    'lit' => 'Literature',
    'comp' => 'Computer Science'
];

// Get school_id
$school_id_query = "SELECT school_id FROM admins WHERE id = $admin_id";
$school_result = mysqli_query($conn, $school_id_query);
$school_data = mysqli_fetch_assoc($school_result);
$current_school_id = $school_data['school_id'] ?? 1;

// Get all teachers
$teachers_sql = "SELECT a.*, 
                 GROUP_CONCAT(DISTINCT ar.role_name SEPARATOR ', ') as roles
                 FROM admins a
                 LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
                 LEFT JOIN admin_roles ar ON ara.role_id = ar.id
                 WHERE a.school_id = $current_school_id
                 AND a.id != $admin_id
                 AND (ar.role_name != 'Super Admin' OR ar.role_name IS NULL)
                 GROUP BY a.id
                 ORDER BY a.first_name, a.last_name";
$teachers_result = mysqli_query($conn, $teachers_sql);
$teachers = [];
while ($row = mysqli_fetch_assoc($teachers_result)) {
    $teachers[] = $row;
}

// Handle POST requests (assign, update, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_subject'])) {
        $teacher_id = intval($_POST['teacher_id']);
        $subject = mysqli_real_escape_string($conn, $_POST['subject']);
        $form_level = mysqli_real_escape_string($conn, $_POST['form_level']);
        $academic_year = intval($_POST['academic_year']);
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        $can_enter_results = isset($_POST['can_enter_results']) ? 1 : 0;
        
        $check_sql = "SELECT id FROM subject_teacher_assignments 
                      WHERE teacher_id = $teacher_id AND subject = '$subject' 
                      AND form_level = '$form_level' AND academic_year = $academic_year
                      AND school_id = $current_school_id";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error'] = "This teacher is already assigned to this subject for the selected form and year.";
        } else {
            $insert_sql = "INSERT INTO subject_teacher_assignments 
                          (teacher_id, subject, form_level, academic_year, is_primary, can_enter_results, assigned_by, school_id) 
                          VALUES ($teacher_id, '$subject', '$form_level', $academic_year, $is_primary, $can_enter_results, $admin_id, $current_school_id)";
            
            if (mysqli_query($conn, $insert_sql)) {
                $_SESSION['success'] = "Subject assigned successfully!";
            } else {
                $_SESSION['error'] = "Error assigning subject: " . mysqli_error($conn);
            }
        }
        
        header("Location: assign_subject.php?form_level=" . urlencode($form_level) . "&year=$academic_year");
        ob_end_flush();
        exit();
    }
    
    if (isset($_POST['update_assignment'])) {
        $assignment_id = intval($_POST['assignment_id']);
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        $can_enter_results = isset($_POST['can_enter_results']) ? 1 : 0;
        
        $update_sql = "UPDATE subject_teacher_assignments 
                      SET is_primary = $is_primary, can_enter_results = $can_enter_results, updated_at = CURRENT_TIMESTAMP
                      WHERE id = $assignment_id AND school_id = $current_school_id";
        
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['success'] = "Assignment updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating assignment: " . mysqli_error($conn);
        }
        
        header("Location: assign_subject.php?form_level=" . urlencode($current_form) . "&year=$current_year&subject=" . urlencode($current_subject));
        ob_end_flush();
        exit();
    }
}

// Handle GET requests (remove, toggle)
if (isset($_GET['remove'])) {
    $assignment_id = intval($_GET['remove']);
    
    $assignment_sql = "SELECT sta.*, a.first_name, a.last_name 
                      FROM subject_teacher_assignments sta
                      JOIN admins a ON sta.teacher_id = a.id
                      WHERE sta.id = $assignment_id AND sta.school_id = $current_school_id";
    $assignment_result = mysqli_query($conn, $assignment_sql);
    $assignment = mysqli_fetch_assoc($assignment_result);
    
    if ($assignment) {
        $delete_sql = "DELETE FROM subject_teacher_assignments WHERE id = $assignment_id AND school_id = $current_school_id";
        if (mysqli_query($conn, $delete_sql)) {
            $_SESSION['success'] = "Assignment removed successfully!";
        } else {
            $_SESSION['error'] = "Error removing assignment: " . mysqli_error($conn);
        }
    }
    
    header("Location: assign_subject.php?form_level=" . urlencode($current_form) . "&year=$current_year");
    ob_end_flush();
    exit();
}

if (isset($_GET['toggle_entry'])) {
    $assignment_id = intval($_GET['toggle_entry']);
    
    $toggle_sql = "UPDATE subject_teacher_assignments 
                  SET can_enter_results = NOT can_enter_results 
                  WHERE id = $assignment_id AND school_id = $current_school_id";
    
    if (mysqli_query($conn, $toggle_sql)) {
        $_SESSION['success'] = "Result entry permission toggled successfully!";
    } else {
        $_SESSION['error'] = "Error toggling permission: " . mysqli_error($conn);
    }
    
    header("Location: assign_subject.php?form_level=" . urlencode($current_form) . "&year=$current_year");
    ob_end_flush();
    exit();
}

// Get current assignments with filters
$assignments_sql = "SELECT sta.*, a.first_name, a.last_name, a.email, a.phone_number,
                   CONCAT(a.first_name, ' ', a.last_name) as teacher_name
                   FROM subject_teacher_assignments sta
                   JOIN admins a ON sta.teacher_id = a.id
                   WHERE sta.form_level = '$current_form' 
                   AND sta.academic_year = $current_year
                   AND sta.school_id = $current_school_id";
if (!empty($current_subject)) {
    $assignments_sql .= " AND sta.subject = '$current_subject'";
}
$assignments_sql .= " ORDER BY sta.subject, a.first_name, a.last_name";
$assignments_result = mysqli_query($conn, $assignments_sql);
$assignments = [];
while ($row = mysqli_fetch_assoc($assignments_result)) {
    $assignments[] = $row;
}

// Group assignments by subject
$assignments_by_subject = [];
foreach ($assignments as $assignment) {
    $subject_key = $assignment['subject'];
    if (!isset($assignments_by_subject[$subject_key])) {
        $assignments_by_subject[$subject_key] = [];
    }
    $assignments_by_subject[$subject_key][] = $assignment;
}

// Get available years
$years_sql = "SELECT DISTINCT academic_year FROM subject_teacher_assignments WHERE school_id = $current_school_id ORDER BY academic_year DESC";
$years_result = mysqli_query($conn, $years_sql);
$available_years = [];
while ($row = mysqli_fetch_assoc($years_result)) {
    $available_years[] = $row['academic_year'];
}
if (empty($available_years)) {
    $available_years[] = date('Y');
}

// Load user's theme settings
$colors = [];
$preferences = [];

$color_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id AND school_id = $current_school_id";
$color_result = mysqli_query($conn, $color_query);
if ($color_result) {
    while ($row = mysqli_fetch_assoc($color_result)) {
        $colors[$row['setting_key']] = $row['setting_value'];
    }
}

$pref_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id AND school_id = $current_school_id";
$pref_result = mysqli_query($conn, $pref_query);
if ($pref_result) {
    while ($row = mysqli_fetch_assoc($pref_result)) {
        $preferences[$row['preference_key']] = $row['preference_value'];
    }
}

// Default theme colors
$default_colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
    'primary_light' => '#8bc5d6',
    'success' => '#28a745',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#17a2b8',
    'text' => '#333333',
    'text_light' => '#666666',
    'border' => '#e0e0e0',
    'white' => '#ffffff',
    'gray' => '#e9ecef'
];

foreach ($default_colors as $key => $value) {
    if (!isset($colors[$key])) {
        $colors[$key] = $value;
    }
}

// Default preferences
$pref_defaults = [
    'background_opacity' => '65',
    'animations' => '1',
    'font_size' => '16',
    'compact_mode' => '0',
    'sidebar_collapsed' => '0',
    'animation_speed' => 'normal'
];

foreach ($pref_defaults as $key => $default) {
    if (!isset($preferences[$key])) {
        $preferences[$key] = $default;
    }
}

$font_size_map = ['10' => '10px', '12' => '12px', '14' => '14px', '16' => '16px', '18' => '18px'];
$font_size_value = isset($font_size_map[$preferences['font_size']]) ? $font_size_map[$preferences['font_size']] : '16px';

$animation_speeds = ['slow' => '0.5s', 'normal' => '0.3s', 'fast' => '0.15s'];
$animation_duration = isset($animation_speeds[$preferences['animation_speed']]) ? $animation_speeds[$preferences['animation_speed']] : '0.3s';

$sidebarClass = ($preferences['sidebar_collapsed'] == '1') ? 'sidebar-hidden' : '';

// Clear output buffer before including header
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Teacher Assignment - Academic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        .stats-card {
            background: var(--white);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            height: 100%;
        }

        .stats-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .assignment-card {
            background: var(--white);
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .assignment-card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 12px 20px;
            font-weight: 600;
        }

        .teacher-item {
            padding: 12px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .teacher-item:last-child {
            border-bottom: none;
        }

        .badge-primary {
            background-color: var(--info-color);
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
        }

        .badge-allowed {
            background-color: var(--success-color);
            color: white;
        }

        .badge-disabled {
            background-color: #95a5a6;
            color: white;
        }

        .form-tabs {
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }

        .form-tab {
            display: inline-block;
            padding: 10px 20px;
            color: #6c757d;
            text-decoration: none;
        }

        .form-tab.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        .filter-section {
            background: var(--white);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
        }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content <?php echo $sidebarClass; ?>">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-chalkboard-teacher me-2"></i>
                    Subject Teacher Assignment
                </h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignSubjectModal">
                    <i class="fas fa-plus me-2"></i>Assign Subject
                </button>
            </div>

            <!-- Tabs -->
            <div class="form-tabs">
                <a href="?form_level=Form%20Five&year=<?php echo $current_year; ?>" 
                   class="form-tab <?php echo $current_form == 'Form Five' ? 'active' : ''; ?>">
                    <i class="fas fa-graduation-cap me-2"></i>Form Five
                </a>
                <a href="?form_level=Form%20Six&year=<?php echo $current_year; ?>" 
                   class="form-tab <?php echo $current_form == 'Form Six' ? 'active' : ''; ?>">
                    <i class="fas fa-university me-2"></i>Form Six
                </a>
            </div>

            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($teachers); ?></div>
                        <div>Total Teachers</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($assignments); ?></div>
                        <div>Total Assignments</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="stats-number">
                            <?php 
                            $pc = 0;
                            foreach ($assignments as $a) if ($a['is_primary']) $pc++;
                            echo $pc;
                            ?>
                        </div>
                        <div>Primary Teachers</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="stats-number">
                            <?php 
                            $ec = 0;
                            foreach ($assignments as $a) if ($a['can_enter_results']) $ec++;
                            echo $ec;
                            ?>
                        </div>
                        <div>Can Enter Results</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-section">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Academic Year</label>
                        <select id="yearFilter" class="form-select">
                            <?php foreach ($available_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $current_year == $year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Subject</label>
                        <select id="subjectFilter" class="form-select">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects_list as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo $current_subject == $code ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button id="applyFilters" class="btn btn-primary w-100">Apply Filters</button>
                    </div>
                </div>
            </div>

            <!-- Assignments Display -->
            <?php if (empty($assignments_by_subject)): ?>
                <div class="alert alert-info">No subject assignments found.</div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($subjects_list as $code => $subject_name): 
                        $subject_assignments = $assignments_by_subject[$code] ?? [];
                    ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="assignment-card">
                                <div class="assignment-card-header">
                                    <i class="fas fa-book me-2"></i>
                                    <?php echo $subject_name; ?>
                                    <span class="badge bg-light text-dark ms-2"><?php echo count($subject_assignments); ?></span>
                                </div>
                                <div>
                                    <?php if (empty($subject_assignments)): ?>
                                        <div class="teacher-item text-muted text-center">No teacher assigned</div>
                                    <?php else: ?>
                                        <?php foreach ($subject_assignments as $assignment): ?>
                                            <div class="teacher-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($assignment['teacher_name']); ?></strong>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($assignment['email']); ?></div>
                                                        <div class="mt-1">
                                                            <?php if ($assignment['is_primary']): ?>
                                                                <span class="badge-primary"><i class="fas fa-star me-1"></i>Primary</span>
                                                            <?php endif; ?>
                                                            <span class="badge <?php echo $assignment['can_enter_results'] ? 'badge-allowed' : 'badge-disabled'; ?> ms-1">
                                                                <?php echo $assignment['can_enter_results'] ? '✓ Can Enter Results' : '✗ Entry Disabled'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <a href="?remove=<?php echo $assignment['id']; ?>&form_level=<?php echo urlencode($current_form); ?>&year=<?php echo $current_year; ?>" 
                                                           class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this assignment?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <a href="?toggle_entry=<?php echo $assignment['id']; ?>&form_level=<?php echo urlencode($current_form); ?>&year=<?php echo $current_year; ?>" 
                                                           class="btn btn-sm btn-outline-<?php echo $assignment['can_enter_results'] ? 'warning' : 'success'; ?> ms-1">
                                                            <i class="fas fa-<?php echo $assignment['can_enter_results'] ? 'ban' : 'check'; ?>"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assign Modal -->
    <div class="modal fade" id="assignSubjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-color); color: white;">
                    <h5 class="modal-title">Assign Subject to Teacher</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Teacher</label>
                            <select name="teacher_id" class="form-select" required>
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <select name="subject" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects_list as $code => $name): ?>
                                    <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Form Level</label>
                            <select name="form_level" class="form-select" required>
                                <option value="Form Five">Form Five</option>
                                <option value="Form Six">Form Six</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Academic Year</label>
                            <input type="number" name="academic_year" class="form-control" value="<?php echo $current_year; ?>" required>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_primary" class="form-check-input" value="1" id="is_primary">
                                <label class="form-check-label" for="is_primary">Set as Primary Teacher</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="can_enter_results" class="form-check-input" value="1" id="can_enter_results" checked>
                                <label class="form-check-label" for="can_enter_results">Allow Result Entry</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_subject" class="btn btn-primary">Assign Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.getElementById('applyFilters').addEventListener('click', function() {
            const year = document.getElementById('yearFilter').value;
            const subject = document.getElementById('subjectFilter').value;
            let url = `assign_subject.php?form_level=<?php echo urlencode($current_form); ?>&year=${year}`;
            if (subject) url += `&subject=${subject}`;
            window.location.href = url;
        });

        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({title: 'Success!', text: '<?php echo addslashes($_SESSION['success']); ?>', icon: 'success', timer: 3000});
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({title: 'Error!', text: '<?php echo addslashes($_SESSION['error']); ?>', icon: 'error'});
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>