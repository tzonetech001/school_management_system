<?php
// edit_admin.php
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

// Check if user has Head Master (1) or Second Master (2) role
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 3) { // Head Master or Second Master
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view staff members.";
    header("Location:  ../404.php");
    exit();
}


// Get all roles from database (excluding Super Admin if it exists)
$roles = [];
$roles_sql = "SELECT * FROM admin_roles WHERE role_name != 'Super Admin' ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_sql);
if ($roles_result && mysqli_num_rows($roles_result) > 0) {
    while ($row = mysqli_fetch_assoc($roles_result)) {
        $roles[] = $row;
    }
}

$admin_id = $_SESSION['admin_id'];

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

// Check if user has Academic Master (3), Head Master (1), or Second Master (2) role
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
    exit();
}

// Get filter parameters
$current_form = isset($_GET['form_level']) ? $_GET['form_level'] : 'Form Five';
$current_subject = isset($_GET['subject']) ? $_GET['subject'] : '';
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Valid subjects list
$subjects_list = [
    'ac' => 'AC (Academic Communication)',
    'htm' => 'HTM (Historia ya Tanzania na Maadili)',
    'his' => 'HIST (History)',
    'geo' => 'GEO (Geography)',
    'kisw' => 'KISW (Kiswahili)',
    'eng' => 'ENG (English)',
    'b_math' => 'B/MATH (Basic Mathematics)',
    'adv_m' => 'ADV/M (Advanced Mathematics)',
    'eco' => 'ECO (Economics)',
    'fren' => 'FREN (French)'
];

// Get all teachers (admins) who are not super admin
$teachers_sql = "SELECT a.*, 
                 GROUP_CONCAT(DISTINCT ar.role_name SEPARATOR ', ') as roles
                 FROM admins a
                 LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
                 LEFT JOIN admin_roles ar ON ara.role_id = ar.id
                 WHERE ar.role_name != 'Super Admin' OR ar.role_name IS NULL
                 GROUP BY a.id
                 ORDER BY a.first_name, a.last_name";
$teachers_result = mysqli_query($conn, $teachers_sql);
$teachers = [];
while ($row = mysqli_fetch_assoc($teachers_result)) {
    $teachers[] = $row;
}

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_subject'])) {
        $teacher_id = intval($_POST['teacher_id']);
        $subject = mysqli_real_escape_string($conn, $_POST['subject']);
        $form_level = mysqli_real_escape_string($conn, $_POST['form_level']);
        $academic_year = intval($_POST['academic_year']);
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        $can_enter_results = isset($_POST['can_enter_results']) ? 1 : 0;
        
        // Check if assignment already exists
        $check_sql = "SELECT id FROM subject_teacher_assignments 
                      WHERE teacher_id = $teacher_id AND subject = '$subject' 
                      AND form_level = '$form_level' AND academic_year = $academic_year";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error'] = "This teacher is already assigned to this subject for the selected form and year.";
        } else {
            $insert_sql = "INSERT INTO subject_teacher_assignments 
                          (teacher_id, subject, form_level, academic_year, is_primary, can_enter_results, assigned_by) 
                          VALUES ($teacher_id, '$subject', '$form_level', $academic_year, $is_primary, $can_enter_results, $admin_id)";
            
            if (mysqli_query($conn, $insert_sql)) {
                $_SESSION['success'] = "Subject assigned successfully!";
                // Log the action
                $log_sql = "INSERT INTO admin_logs (admin_id, action, details) 
                           VALUES ($admin_id, 'Assign Subject', 'Assigned $subject to teacher ID $teacher_id for $form_level ($academic_year)')";
                mysqli_query($conn, $log_sql);
            } else {
                $_SESSION['error'] = "Error assigning subject: " . mysqli_error($conn);
            }
        }
        
        header("Location: assign_subject.php?form_level=" . urlencode($form_level) . "&year=$academic_year");
        exit();
    }
    
    // Handle update assignment
    if (isset($_POST['update_assignment'])) {
        $assignment_id = intval($_POST['assignment_id']);
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        $can_enter_results = isset($_POST['can_enter_results']) ? 1 : 0;
        
        $update_sql = "UPDATE subject_teacher_assignments 
                      SET is_primary = $is_primary, can_enter_results = $can_enter_results, updated_at = CURRENT_TIMESTAMP
                      WHERE id = $assignment_id";
        
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['success'] = "Assignment updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating assignment: " . mysqli_error($conn);
        }
        
        header("Location: assign_subject.php?form_level=" . urlencode($current_form) . "&year=$current_year&subject=" . urlencode($current_subject));
        exit();
    }
}

// Handle removal
if (isset($_GET['remove'])) {
    $assignment_id = intval($_GET['remove']);
    
    // Get assignment details for logging
    $assignment_sql = "SELECT sta.*, a.first_name, a.last_name 
                      FROM subject_teacher_assignments sta
                      JOIN admins a ON sta.teacher_id = a.id
                      WHERE sta.id = $assignment_id";
    $assignment_result = mysqli_query($conn, $assignment_sql);
    $assignment = mysqli_fetch_assoc($assignment_result);
    
    if ($assignment) {
        $delete_sql = "DELETE FROM subject_teacher_assignments WHERE id = $assignment_id";
        if (mysqli_query($conn, $delete_sql)) {
            $_SESSION['success'] = "Assignment removed successfully!";
            $log_sql = "INSERT INTO admin_logs (admin_id, action, details) 
                       VALUES ($admin_id, 'Remove Subject Assignment', 
                       'Removed {$assignment['subject']} from {$assignment['first_name']} {$assignment['last_name']} for {$assignment['form_level']}')";
            mysqli_query($conn, $log_sql);
        } else {
            $_SESSION['error'] = "Error removing assignment: " . mysqli_error($conn);
        }
    }
    
    header("Location: assign_subject.php?form_level=" . urlencode($current_form) . "&year=$current_year");
    exit();
}

// Handle toggle result entry permission
if (isset($_GET['toggle_entry'])) {
    $assignment_id = intval($_GET['toggle_entry']);
    
    $toggle_sql = "UPDATE subject_teacher_assignments 
                  SET can_enter_results = NOT can_enter_results 
                  WHERE id = $assignment_id";
    
    if (mysqli_query($conn, $toggle_sql)) {
        $_SESSION['success'] = "Result entry permission toggled successfully!";
    } else {
        $_SESSION['error'] = "Error toggling permission: " . mysqli_error($conn);
    }
    
    header("Location: assign_subject.php?form_level=" . urlencode($current_form) . "&year=$current_year");
    exit();
}

// Get current assignments with filters
$assignments_sql = "SELECT sta.*, a.first_name, a.last_name, a.email, a.phone_number,
                   CONCAT(a.first_name, ' ', a.last_name) as teacher_name
                   FROM subject_teacher_assignments sta
                   JOIN admins a ON sta.teacher_id = a.id
                   WHERE sta.form_level = '$current_form' AND sta.academic_year = $current_year";
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

// Get available years for filtering
$years_sql = "SELECT DISTINCT academic_year FROM subject_teacher_assignments ORDER BY academic_year DESC";
$years_result = mysqli_query($conn, $years_sql);
$available_years = [];
while ($row = mysqli_fetch_assoc($years_result)) {
    $available_years[] = $row['academic_year'];
}
if (empty($available_years)) {
    $available_years[] = date('Y');
}

// Get all subjects that have assignments
$subjects_with_assignments = array_keys($assignments_by_subject);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Teacher Assignment - Academic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
     

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
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

        .stats-card {
            border: none;
            border-radius: 15px;
            padding: 20px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
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

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .stats-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stats-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }

        .assignment-card {
            background: white;
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

        .assignment-card-header i {
            margin-right: 10px;
        }

        .teacher-item {
            padding: 12px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.2s;
        }

        .teacher-item:hover {
            background-color: #f8f9fa;
        }

        .teacher-item:last-child {
            border-bottom: none;
        }

        .badge-primary-teacher {
            background-color: var(--info-color);
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-entry-allowed {
            background-color: var(--success-color);
            color: white;
        }

        .badge-entry-disabled {
            background-color: #95a5a6;
            color: white;
        }

        .form-tabs {
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-tab {
            display: inline-block;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: 500;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            text-decoration: none;
        }

        .form-tab:hover {
            color: var(--primary-color);
        }

        .form-tab.active {
            color: var(--primary-color);
        }

        .form-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-color);
        }

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 15px;
            }
            
            .stats-number {
                font-size: 1.5rem;
            }
            
            .btn-group-sm {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }
            
            .btn-group-sm .btn {
                flex: 1;
                min-width: 35px;
            }
        }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h2 class="page-title">
                    <i class="fas fa-chalkboard-teacher me-2"></i>
                    Subject Teacher Assignment
                </h2>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignSubjectModal">
                        <i class="fas fa-plus me-2"></i>Assign Subject to Teacher
                    </button>
                </div>
            </div>

            <!-- Form Level Tabs -->
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

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-users" style="color: var(--primary-color);"></i>
                        </div>
                        <div class="stats-number"><?php echo count($teachers); ?></div>
                        <p class="text-muted mb-0">Total Teachers</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-book" style="color: var(--success-color);"></i>
                        </div>
                        <div class="stats-number"><?php echo count($assignments); ?></div>
                        <p class="text-muted mb-0">Total Assignments</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-star" style="color: var(--warning-color);"></i>
                        </div>
                        <div class="stats-number">
                            <?php 
                            $primary_count = 0;
                            foreach ($assignments as $a) {
                                if ($a['is_primary']) $primary_count++;
                            }
                            echo $primary_count;
                            ?>
                        </div>
                        <p class="text-muted mb-0">Primary Teachers</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-pen" style="color: var(--info-color);"></i>
                        </div>
                        <div class="stats-number">
                            <?php 
                            $entry_allowed = 0;
                            foreach ($assignments as $a) {
                                if ($a['can_enter_results']) $entry_allowed++;
                            }
                            echo $entry_allowed;
                            ?>
                        </div>
                        <p class="text-muted mb-0">Can Enter Results</p>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row align-items-center">
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
                        <label class="form-label">Filter by Subject</label>
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
                        <button id="applyFilters" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Assignments by Subject -->
            <?php if (empty($assignments_by_subject)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    No subject assignments found for <?php echo $current_form; ?> in year <?php echo $current_year; ?>.
                    Click "Assign Subject to Teacher" to get started.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($subjects_list as $code => $subject_name): 
                        $subject_assignments = $assignments_by_subject[$code] ?? [];
                    ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="assignment-card">
                                <div class="assignment-card-header">
                                    <i class="fas fa-book"></i>
                                    <?php echo $subject_name; ?>
                                    <span class="badge bg-light text-dark ms-2">
                                        <?php echo count($subject_assignments); ?> teacher(s)
                                    </span>
                                </div>
                                <div class="assignment-card-body">
                                    <?php if (empty($subject_assignments)): ?>
                                        <div class="teacher-item text-muted text-center">
                                            <i class="fas fa-user-slash me-2"></i>No teacher assigned
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($subject_assignments as $assignment): ?>
                                            <div class="teacher-item">
                                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($assignment['teacher_name']); ?></strong>
                                                        <div class="small text-muted">
                                                            <?php echo htmlspecialchars($assignment['email']); ?>
                                                        </div>
                                                        <div class="mt-1">
                                                            <?php if ($assignment['is_primary']): ?>
                                                                <span class="badge-primary-teacher">
                                                                    <i class="fas fa-star me-1"></i>Primary Teacher
                                                                </span>
                                                            <?php endif; ?>
                                                            <span class="badge <?php echo $assignment['can_enter_results'] ? 'badge-entry-allowed' : 'badge-entry-disabled'; ?> ms-1">
                                                                <i class="fas fa-<?php echo $assignment['can_enter_results'] ? 'check' : 'times'; ?> me-1"></i>
                                                                <?php echo $assignment['can_enter_results'] ? 'Can Enter Results' : 'Entry Disabled'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-info" 
                                                                onclick="editAssignment(<?php echo htmlspecialchars(json_encode($assignment)); ?>)"
                                                                title="Edit Assignment">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <a href="?remove=<?php echo $assignment['id']; ?>&form_level=<?php echo urlencode($current_form); ?>&year=<?php echo $current_year; ?>" 
                                                           class="btn btn-outline-danger"
                                                           onclick="return confirm('Remove <?php echo htmlspecialchars($assignment['teacher_name']); ?> from <?php echo $subject_name; ?>?')"
                                                           title="Remove Assignment">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <a href="?toggle_entry=<?php echo $assignment['id']; ?>&form_level=<?php echo urlencode($current_form); ?>&year=<?php echo $current_year; ?>" 
                                                           class="btn btn-outline-<?php echo $assignment['can_enter_results'] ? 'warning' : 'success'; ?>"
                                                           title="<?php echo $assignment['can_enter_results'] ? 'Disable Result Entry' : 'Enable Result Entry'; ?>">
                                                            <i class="fas fa-<?php echo $assignment['can_enter_results'] ? 'ban' : 'check-circle'; ?>"></i>
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

            <!-- Info Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Information:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>Primary Teacher:</strong> The main teacher responsible for the subject</li>
                            <li><strong>Can Enter Results:</strong> Controls whether the teacher can enter results for this subject</li>
                            <li>Teachers will only see subjects they are assigned to in the results entry page</li>
                            <li>When a teacher is denied result entry permission, they cannot enter or modify marks for that subject</li>
                            <li>Assignments are year-specific - you need to create new assignments for each academic year</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Subject Modal -->
    <div class="modal fade" id="assignSubjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Assign Subject to Teacher
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Teacher <span class="text-danger">*</span></label>
                            <select name="teacher_id" class="form-select" required>
                                <option value="">-- Select Teacher --</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                        (<?php echo htmlspecialchars($teacher['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Subject <span class="text-danger">*</span></label>
                            <select name="subject" class="form-select" required>
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($subjects_list as $code => $name): ?>
                                    <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Form Level <span class="text-danger">*</span></label>
                            <select name="form_level" class="form-select" required>
                                <option value="Form Five" <?php echo $current_form == 'Form Five' ? 'selected' : ''; ?>>Form Five</option>
                                <option value="Form Six" <?php echo $current_form == 'Form Six' ? 'selected' : ''; ?>>Form Six</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <input type="number" name="academic_year" class="form-control" value="<?php echo $current_year; ?>" required>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_primary" class="form-check-input" value="1" id="is_primary">
                                <label class="form-check-label" for="is_primary">
                                    <i class="fas fa-star text-warning me-1"></i>Set as Primary Teacher
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="can_enter_results" class="form-check-input" value="1" id="can_enter_results" checked>
                                <label class="form-check-label" for="can_enter_results">
                                    <i class="fas fa-pen me-1"></i>Allow Result Entry
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_subject" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Assign Subject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Assignment Modal -->
    <div class="modal fade" id="editAssignmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Subject Assignment
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="assignment_id" id="edit_assignment_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Teacher</label>
                            <input type="text" id="edit_teacher_name" class="form-control" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" id="edit_subject_name" class="form-control" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Form Level</label>
                            <input type="text" id="edit_form_level" class="form-control" disabled>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_primary" class="form-check-input" value="1" id="edit_is_primary">
                                <label class="form-check-label" for="edit_is_primary">
                                    <i class="fas fa-star text-warning me-1"></i>Set as Primary Teacher
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="can_enter_results" class="form-check-input" value="1" id="edit_can_enter_results">
                                <label class="form-check-label" for="edit_can_enter_results">
                                    <i class="fas fa-pen me-1"></i>Allow Result Entry
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_assignment" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <script>
        const subjectsList = <?php echo json_encode($subjects_list); ?>;
        
        function editAssignment(assignment) {
            document.getElementById('edit_assignment_id').value = assignment.id;
            document.getElementById('edit_teacher_name').value = assignment.teacher_name;
            document.getElementById('edit_subject_name').value = subjectsList[assignment.subject] || assignment.subject;
            document.getElementById('edit_form_level').value = assignment.form_level;
            document.getElementById('edit_is_primary').checked = assignment.is_primary == 1;
            document.getElementById('edit_can_enter_results').checked = assignment.can_enter_results == 1;
            
            const modal = new bootstrap.Modal(document.getElementById('editAssignmentModal'));
            modal.show();
        }
        
        // Apply filters
        document.getElementById('applyFilters').addEventListener('click', function() {
            const year = document.getElementById('yearFilter').value;
            const subject = document.getElementById('subjectFilter').value;
            let url = `assign_subject.php?form_level=<?php echo urlencode($current_form); ?>&year=${year}`;
            if (subject) {
                url += `&subject=${subject}`;
            }
            window.location.href = url;
        });
        
        // Year filter change
        document.getElementById('yearFilter').addEventListener('change', function() {
            document.getElementById('applyFilters').click();
        });
        
        // Subject filter change
        document.getElementById('subjectFilter').addEventListener('change', function() {
            document.getElementById('applyFilters').click();
        });
        
        // Show success/error messages
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                title: 'Success!',
                text: '<?php echo addslashes($_SESSION['success']); ?>',
                icon: 'success',
                confirmButtonText: 'OK',
                timer: 3000,
                timerProgressBar: true
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                title: 'Error!',
                text: '<?php echo addslashes($_SESSION['error']); ?>',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>