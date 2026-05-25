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
$error = '';
$success = '';

// Get current form filter (default to Form Five)
$current_form = isset($_GET['form_level']) ? $_GET['form_level'] : 'Form Five';
if (!in_array($current_form, ['Form Five', 'Form Six'])) {
    $current_form = 'Form Five';
}

// Get current user's roles for permission check
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

// Check if user has permission
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 3 || $role_id == 4) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to manage exam types.";
    header("Location: ../404.php");
    exit();
}

// Helper function to safely execute queries with error handling
function executeSafely($conn, $sql, $error_message = "Database error occurred") {
    try {
        $result = mysqli_query($conn, $sql);
        if ($result === false) {
            // Check for duplicate entry error (1062)
            if (mysqli_errno($conn) == 1062) {
                // Extract the duplicate key information from error message
                if (preg_match("/Duplicate entry '(.+)' for key '(.+)'/", mysqli_error($conn), $matches)) {
                    $duplicate_value = $matches[1];
                    $key_name = $matches[2];
                    if ($key_name == 'exam_code') {
                        return ['error' => "Exam code '$duplicate_value' already exists for this form level and year!"];
                    } else {
                        return ['error' => "Duplicate entry error: '$duplicate_value' already exists."];
                    }
                }
                return ['error' => "Duplicate entry error. The value already exists in the database."];
            }
            return ['error' => $error_message . ": " . mysqli_error($conn)];
        }
        return ['success' => true, 'result' => $result];
    } catch (Exception $e) {
        return ['error' => $error_message . ": " . $e->getMessage()];
    }
}

// Auto-deactivate other active exams for the same form level when activating a new one
function autoDeactivateOtherExams($conn, $exam_id, $form_level, $current_status) {
    if ($current_status == 1) {
        // If activating this exam, deactivate all others for the same form level
        $update_sql = "UPDATE exam_types SET is_active = 0, updated_at = CURRENT_TIMESTAMP 
                       WHERE form_level = '$form_level' AND id != $exam_id";
        mysqli_query($conn, $update_sql);
    }
}

// Handle adding new exam type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_exam'])) {
    $exam_name = mysqli_real_escape_string($conn, $_POST['exam_name']);
    $exam_code = mysqli_real_escape_string($conn, $_POST['exam_code']);
    $form_level = mysqli_real_escape_string($conn, $_POST['form_level']);
    $term = mysqli_real_escape_string($conn, $_POST['term']);
    $year = intval($_POST['year']);
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate exam code uniqueness (per year and form level) - check first
    $check_sql = "SELECT id FROM exam_types WHERE exam_code = '$exam_code' AND year = $year AND form_level = '$form_level'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['error'] = "Exam code '$exam_code' already exists for $form_level in year $year!";
    } else {
        // If activating this exam, deactivate others first
        if ($is_active == 1) {
            autoDeactivateOtherExams($conn, 0, $form_level, 1);
        }
        
        $insert_sql = "INSERT INTO exam_types (exam_name, exam_code, form_level, term, year, description, is_active, created_by) 
                       VALUES ('$exam_name', '$exam_code', '$form_level', '$term', $year, '$description', $is_active, $admin_id)";
        
        $result = executeSafely($conn, $insert_sql, "Error adding exam type");
        
        if (isset($result['error'])) {
            $_SESSION['error'] = $result['error'];
        } else {
            $new_id = mysqli_insert_id($conn);
            $_SESSION['success'] = "Exam type '$exam_name' added successfully!";
            
            // Log the action
            $log_sql = "INSERT INTO admin_logs (admin_id, action, details) 
                       VALUES ($admin_id, 'Add Exam Type', 'Added exam type: $exam_name ($exam_code) for $form_level in year $year')";
            mysqli_query($conn, $log_sql);
        }
    }
    
    header("Location: exam_type_manager.php?form_level=" . urlencode($form_level));
    exit();
}

// Handle editing exam type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_exam'])) {
    $id = intval($_POST['exam_id']);
    $exam_name = mysqli_real_escape_string($conn, $_POST['exam_name']);
    $exam_code = mysqli_real_escape_string($conn, $_POST['exam_code']);
    $form_level = mysqli_real_escape_string($conn, $_POST['form_level']);
    $term = mysqli_real_escape_string($conn, $_POST['term']);
    $year = intval($_POST['year']);
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Check if exam code is unique (excluding current)
    $check_sql = "SELECT id FROM exam_types WHERE exam_code = '$exam_code' AND year = $year AND form_level = '$form_level' AND id != $id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['error'] = "Exam code '$exam_code' already exists for $form_level in year $year!";
    } else {
        // If activating this exam, deactivate others
        if ($is_active == 1) {
            autoDeactivateOtherExams($conn, $id, $form_level, 1);
        }
        
        $update_sql = "UPDATE exam_types SET 
                      exam_name = '$exam_name',
                      exam_code = '$exam_code',
                      form_level = '$form_level',
                      term = '$term',
                      year = $year,
                      description = '$description',
                      is_active = $is_active,
                      updated_at = CURRENT_TIMESTAMP,
                      updated_by = $admin_id
                      WHERE id = $id";
        
        $result = executeSafely($conn, $update_sql, "Error updating exam type");
        
        if (isset($result['error'])) {
            $_SESSION['error'] = $result['error'];
        } else {
            $_SESSION['success'] = "Exam type updated successfully!";
            
            // Log the action
            $log_sql = "INSERT INTO admin_logs (admin_id, action, details) 
                       VALUES ($admin_id, 'Edit Exam Type', 'Edited exam type ID $id: $exam_name')";
            mysqli_query($conn, $log_sql);
        }
    }
    
    header("Location: exam_type_manager.php?form_level=" . urlencode($form_level));
    exit();
}

// Handle toggling exam status (activate/deactivate)
if (isset($_GET['toggle_status'])) {
    $id = intval($_GET['toggle_status']);
    $form_level = isset($_GET['form_level']) ? $_GET['form_level'] : 'Form Five';
    
    // Get current status
    $status_sql = "SELECT is_active, exam_name, form_level FROM exam_types WHERE id = $id";
    $status_result = mysqli_query($conn, $status_sql);
    $exam = mysqli_fetch_assoc($status_result);
    
    if ($exam) {
        $new_status = $exam['is_active'] ? 0 : 1;
        
        // If activating, deactivate all other exams for this form level first
        if ($new_status == 1) {
            $deactivate_sql = "UPDATE exam_types SET is_active = 0, updated_at = CURRENT_TIMESTAMP 
                              WHERE form_level = '{$exam['form_level']}' AND id != $id";
            mysqli_query($conn, $deactivate_sql);
        }
        
        $update_sql = "UPDATE exam_types SET is_active = $new_status, updated_at = CURRENT_TIMESTAMP WHERE id = $id";
        
        if (mysqli_query($conn, $update_sql)) {
            $action = $new_status ? 'activated' : 'deactivated';
            $_SESSION['success'] = "Exam type '{$exam['exam_name']}' $action successfully!";
            
            // Log the action
            $log_sql = "INSERT INTO admin_logs (admin_id, action, details) 
                       VALUES ($admin_id, 'Toggle Exam Status', '{$action} exam type: {$exam['exam_name']}')";
            mysqli_query($conn, $log_sql);
        } else {
            $_SESSION['error'] = "Error toggling exam status: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "Exam type not found!";
    }
    
    header("Location: exam_type_manager.php?form_level=" . urlencode($form_level));
    exit();
}

// Handle deleting exam type with cascade deletion
if (isset($_GET['delete_exam'])) {
    $id = intval($_GET['delete_exam']);
    $form_level = isset($_GET['form_level']) ? $_GET['form_level'] : 'Form Five';
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get exam details for logging
        $exam_sql = "SELECT exam_name, exam_code, year, form_level FROM exam_types WHERE id = $id";
        $exam_result = mysqli_query($conn, $exam_sql);
        $exam = mysqli_fetch_assoc($exam_result);
        
        if (!$exam) {
            throw new Exception("Exam type not found!");
        }
        
        // Determine which results table to use
        $results_table = ($exam['form_level'] == 'Form Five') ? 'form_five_results' : 'form_six_results';
        
        // Get count of results to be deleted
        $count_sql = "SELECT COUNT(*) as result_count FROM $results_table WHERE exam_type_id = $id";
        $count_result = mysqli_query($conn, $count_sql);
        $result_count = mysqli_fetch_assoc($count_result)['result_count'];
        
        // Delete all results for this exam first
        $delete_results_sql = "DELETE FROM $results_table WHERE exam_type_id = $id";
        if (!mysqli_query($conn, $delete_results_sql)) {
            throw new Exception("Error deleting results: " . mysqli_error($conn));
        }
        
        // Delete auto-save entries for this exam
        $delete_auto_save_sql = "DELETE FROM results_auto_save WHERE exam_type_id = $id";
        mysqli_query($conn, $delete_auto_save_sql);
        
        // Delete entry sessions for this exam
        $delete_sessions_sql = "DELETE FROM results_entry_sessions WHERE exam_type_id = $id";
        mysqli_query($conn, $delete_sessions_sql);
        
        // Delete the exam type
        $delete_exam_sql = "DELETE FROM exam_types WHERE id = $id";
        if (!mysqli_query($conn, $delete_exam_sql)) {
            throw new Exception("Error deleting exam type: " . mysqli_error($conn));
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Log the action
        $log_message = "Deleted exam type: {$exam['exam_name']} ({$exam['exam_code']}) for {$exam['form_level']} in year {$exam['year']}";
        if ($result_count > 0) {
            $log_message .= " with $result_count associated results";
        }
        $log_sql = "INSERT INTO admin_logs (admin_id, action, details) 
                   VALUES ($admin_id, 'Delete Exam Type', '$log_message')";
        mysqli_query($conn, $log_sql);
        
        $_SESSION['success'] = "Exam type '{$exam['exam_name']}' deleted successfully!" . 
                              ($result_count > 0 ? " $result_count student results were also deleted." : "");
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Error deleting exam: " . $e->getMessage();
    }
    
    header("Location: exam_type_manager.php?form_level=" . urlencode($form_level));
    exit();
}

// Handle duplicating exam type (for new year)
if (isset($_GET['duplicate_exam'])) {
    $id = intval($_GET['duplicate_exam']);
    $new_year = intval($_GET['new_year'] ?? date('Y'));
    $form_level = isset($_GET['form_level']) ? $_GET['form_level'] : 'Form Five';
    
    // Get source exam
    $source_sql = "SELECT exam_name, exam_code, form_level, term, description FROM exam_types WHERE id = $id";
    $source_result = mysqli_query($conn, $source_sql);
    $source = mysqli_fetch_assoc($source_result);
    
    if ($source) {
        // Generate new exam code for new year
        $form_suffix = ($source['form_level'] == 'Form Five') ? 'F5' : 'F6';
        $base_code = preg_replace('/_(F5|F6)$/', '', $source['exam_code']);
        $new_code = $base_code . '_' . $form_suffix;
        
        // Check if already exists
        $check_sql = "SELECT id FROM exam_types WHERE exam_name = '{$source['exam_name']}' AND year = $new_year AND form_level = '{$source['form_level']}'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error'] = "Exam type already exists for year $new_year!";
        } else {
            $insert_sql = "INSERT INTO exam_types (exam_name, exam_code, form_level, term, year, description, created_by, is_active) 
                           VALUES ('{$source['exam_name']}', '$new_code', '{$source['form_level']}', '{$source['term']}', $new_year, 
                                   'Duplicated from {$source['exam_code']}', $admin_id, 0)";
            
            $result = executeSafely($conn, $insert_sql, "Error duplicating exam type");
            
            if (isset($result['error'])) {
                $_SESSION['error'] = $result['error'];
            } else {
                $_SESSION['success'] = "Exam type duplicated for year $new_year successfully! (Inactive by default)";
                
                // Log the action
                $log_sql = "INSERT INTO admin_logs (admin_id, action, details) 
                           VALUES ($admin_id, 'Duplicate Exam Type', 'Duplicated exam type from {$source['exam_code']} to $new_code for year $new_year')";
                mysqli_query($conn, $log_sql);
            }
        }
    } else {
        $_SESSION['error'] = "Source exam type not found!";
    }
    
    header("Location: exam_type_manager.php?form_level=" . urlencode($form_level));
    exit();
}

// Get all exam types with statistics for current form
$exam_types_sql = "SELECT et.*, 
                   (SELECT COUNT(*) FROM form_five_results WHERE exam_type_id = et.id) as f5_results_count,
                   (SELECT COUNT(*) FROM form_six_results WHERE exam_type_id = et.id) as f6_results_count,
                   (SELECT COUNT(DISTINCT student_id) FROM form_five_results WHERE exam_type_id = et.id AND total_points IS NOT NULL) as f5_completed_count,
                   (SELECT COUNT(DISTINCT student_id) FROM form_six_results WHERE exam_type_id = et.id AND total_points IS NOT NULL) as f6_completed_count
                   FROM exam_types et 
                   WHERE et.form_level = '$current_form'
                   ORDER BY et.year DESC, et.id DESC";
$exam_types_result = mysqli_query($conn, $exam_types_sql);
$exam_types = [];
while ($row = mysqli_fetch_assoc($exam_types_result)) {
    $results_count = ($current_form == 'Form Five') ? $row['f5_results_count'] : $row['f6_results_count'];
    $completed_count = ($current_form == 'Form Five') ? $row['f5_completed_count'] : $row['f6_completed_count'];
    $row['results_count'] = $results_count;
    $row['completed_count'] = $completed_count;
    $exam_types[] = $row;
}

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total_exams,
              SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_exams,
              SUM(CASE WHEN year = YEAR(CURDATE()) THEN 1 ELSE 0 END) as current_year_exams,
              COUNT(DISTINCT year) as years_count
              FROM exam_types
              WHERE form_level = '$current_form'";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Type Manager - Results Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --primary-dark: #1a2632;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
        }

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

        .exam-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .exam-table thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            font-weight: 600;
            padding: 12px;
            border: none;
        }

        .exam-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge-active {
            background-color: var(--success-color);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-inactive {
            background-color: #95a5a6;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .btn-group-sm .btn {
            margin: 0 2px;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .progress {
            height: 6px;
            border-radius: 3px;
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
                    <i class="fas fa-clipboard-list me-2"></i>
                    Exam Type Manager
                </h2>
                <div class="d-flex gap-2">
                    <?php if ($current_form == 'Form Five'): ?>
                        <a href="results_entry_five.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Form Five Results
                        </a>
                    <?php else: ?>
                        <a href="results_entry_six.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Form Six Results
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExamModal">
                        <i class="fas fa-plus me-2"></i>Add New Exam
                    </button>
                </div>
            </div>

            <!-- Form Level Tabs -->
            <div class="form-tabs">
                <a href="?form_level=Form%20Five" class="form-tab <?php echo $current_form == 'Form Five' ? 'active' : ''; ?>">
                    <i class="fas fa-graduation-cap me-2"></i>Form Five
                </a>
                <a href="?form_level=Form%20Six" class="form-tab <?php echo $current_form == 'Form Six' ? 'active' : ''; ?>">
                    <i class="fas fa-university me-2"></i>Form Six
                </a>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-calendar-alt" style="color: var(--primary-color);"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['total_exams']; ?></div>
                        <p class="text-muted mb-0">Total Exams (<?php echo $current_form; ?>)</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['active_exams']; ?></div>
                        <p class="text-muted mb-0">Active Exams</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-calendar-week" style="color: var(--info-color);"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['current_year_exams']; ?></div>
                        <p class="text-muted mb-0">This Year</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-chart-line" style="color: var(--warning-color);"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['years_count']; ?></div>
                        <p class="text-muted mb-0">Academic Years</p>
                    </div>
                </div>
            </div>

            <!-- Exam Types Table -->
            <div class="exam-table">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Exam Name</th>
                                <th>Code</th>
                                <th>Term</th>
                                <th>Year</th>
                                <th>Status</th>
                                <th>Results</th>
                                <th>Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($exam_types)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="fas fa-folder-open fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted">No exam types found for <?php echo $current_form; ?>. Click "Add New Exam" to create one.</p>
                                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addExamModal">
                                            <i class="fas fa-plus me-2"></i>Add First Exam
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($exam_types as $exam): 
                                    $progress = $exam['results_count'] > 0 ? round(($exam['completed_count'] / $exam['results_count']) * 100) : 0;
                                ?>
                                    <tr>
                                        <td><?php echo $exam['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($exam['exam_name']); ?></strong>
                                            <?php if ($exam['description']): ?>
                                                <div class="small text-muted"><?php echo htmlspecialchars($exam['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($exam['exam_code']); ?></code></td>
                                        <td><?php echo htmlspecialchars($exam['term']); ?></td>
                                        <td><?php echo $exam['year']; ?></td>
                                        <td>
                                            <span class="<?php echo $exam['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php echo $exam['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $exam['results_count']; ?> students
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%"></div>
                                                </div>
                                                <small class="text-muted"><?php echo $progress; ?>%</small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $exam['completed_count']; ?>/<?php echo $exam['results_count']; ?> completed
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <?php if ($current_form == 'Form Five'): ?>
                                                    <a href="results_entry_five.php?exam_id=<?php echo $exam['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Enter Results">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="results_entry_six.php?exam_id=<?php echo $exam['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Enter Results">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-outline-info" 
                                                        onclick="editExam(<?php echo htmlspecialchars(json_encode($exam)); ?>)" 
                                                        title="Edit Exam">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                                <a href="?toggle_status=<?php echo $exam['id']; ?>&form_level=<?php echo urlencode($current_form); ?>" 
                                                   class="btn btn-outline-<?php echo $exam['is_active'] ? 'warning' : 'success'; ?>"
                                                   onclick="return confirmToggle('<?php echo addslashes($exam['exam_name']); ?>', '<?php echo $exam['is_active'] ? 'deactivate' : 'activate'; ?>', <?php echo $exam['id']; ?>);"
                                                   title="<?php echo $exam['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $exam['is_active'] ? 'ban' : 'check-circle'; ?>"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-secondary" 
                                                        onclick="duplicateExam(<?php echo $exam['id']; ?>, <?php echo $exam['year']; ?>)" 
                                                        title="Duplicate for New Year">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                                <a href="?delete_exam=<?php echo $exam['id']; ?>&form_level=<?php echo urlencode($current_form); ?>" 
                                                   class="btn btn-outline-danger"
                                                   onclick="return confirmDelete('<?php echo addslashes($exam['exam_name']); ?>', <?php echo $exam['results_count']; ?>, <?php echo $exam['id']; ?>);"
                                                   title="Delete Exam">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Tips -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Quick Tips:</strong>
                        <ul class="mb-0 mt-2">
                            <li>You can have multiple exam types per academic year (Mid-Term, Terminal, Pre-NECTA, etc.)</li>
                            <li>Only <strong>Active</strong> exam types are visible in the results entry page</li>
                            <li>When you activate an exam, all other exams for the same form level are automatically deactivated</li>
                            <li>Deleting an exam type will <strong class="text-danger">permanently delete all associated student results</strong></li>
                            <li>Use <strong>Duplicate</strong> to copy exam settings for a new academic year (results are NOT copied, new exam is Inactive by default)</li>
                            <li>Progress bar shows how many students have completed results entry</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Exam Modal -->
    <div class="modal fade" id="addExamModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Add New Exam Type
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Form Level <span class="text-danger">*</span></label>
                            <select name="form_level" class="form-select" required>
                                <option value="Form Five" <?php echo $current_form == 'Form Five' ? 'selected' : ''; ?>>Form Five</option>
                                <option value="Form Six" <?php echo $current_form == 'Form Six' ? 'selected' : ''; ?>>Form Six</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Exam Name <span class="text-danger">*</span></label>
                            <input type="text" name="exam_name" class="form-control" required>
                            <small class="text-muted">e.g., Mid-Term 1, Terminal Exam 2, Pre-NECTA</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Exam Code <span class="text-danger">*</span></label>
                            <input type="text" name="exam_code" class="form-control" required>
                            <small class="text-muted">e.g., MT1_F5, TE2_F6 (Must be unique per year and form)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Term</label>
                            <select name="term" class="form-select">
                                <option value="Term 1">Term 1</option>
                                <option value="Term 2">Term 2</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year <span class="text-danger">*</span></label>
                            <input type="number" name="year" class="form-control" value="<?php echo date('Y'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Additional notes about this exam..."></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" value="1" id="is_active_add">
                                <label class="form-check-label" for="is_active_add">
                                    Activate this exam (only one active exam per form level)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_exam" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Add Exam
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Exam Modal -->
    <div class="modal fade" id="editExamModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Exam Type
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="exam_id" id="edit_exam_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Form Level <span class="text-danger">*</span></label>
                            <select name="form_level" id="edit_form_level" class="form-select" required>
                                <option value="Form Five">Form Five</option>
                                <option value="Form Six">Form Six</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Exam Name <span class="text-danger">*</span></label>
                            <input type="text" name="exam_name" id="edit_exam_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Exam Code <span class="text-danger">*</span></label>
                            <input type="text" name="exam_code" id="edit_exam_code" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Term</label>
                            <select name="term" id="edit_exam_term" class="form-select">
                                <option value="Term 1">Term 1</option>
                                <option value="Term 2">Term 2</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year <span class="text-danger">*</span></label>
                            <input type="number" name="year" id="edit_exam_year" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_exam_description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" value="1" id="edit_is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Activate this exam (only one active exam per form level)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_exam" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Exam
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Duplicate Exam Modal -->
    <div class="modal fade" id="duplicateExamModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-copy me-2"></i>Duplicate Exam for New Year
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Duplicate this exam for a new academic year:</p>
                    <div class="mb-3">
                        <label class="form-label">New Year</label>
                        <input type="number" id="duplicate_year" class="form-control" value="<?php echo date('Y') + 1; ?>">
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This will create a copy of the exam settings for the selected year. Results will NOT be copied.
                        The duplicated exam will be <strong>Inactive by default</strong>.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmDuplicateBtn">
                        <i class="fas fa-copy me-2"></i>Duplicate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <script>
        let duplicateExamId = null;
        
        function editExam(exam) {
            document.getElementById('edit_exam_id').value = exam.id;
            document.getElementById('edit_form_level').value = exam.form_level;
            document.getElementById('edit_exam_name').value = exam.exam_name;
            document.getElementById('edit_exam_code').value = exam.exam_code;
            document.getElementById('edit_exam_term').value = exam.term;
            document.getElementById('edit_exam_year').value = exam.year;
            document.getElementById('edit_exam_description').value = exam.description || '';
            document.getElementById('edit_is_active').checked = exam.is_active == 1;
            
            const modal = new bootstrap.Modal(document.getElementById('editExamModal'));
            modal.show();
        }
        
        function duplicateExam(id, year) {
            duplicateExamId = id;
            document.getElementById('duplicate_year').value = parseInt(year) + 1;
            const modal = new bootstrap.Modal(document.getElementById('duplicateExamModal'));
            modal.show();
        }
        
        document.getElementById('confirmDuplicateBtn').addEventListener('click', function() {
            const newYear = document.getElementById('duplicate_year').value;
            if (newYear) {
                window.location.href = `exam_type_manager.php?duplicate_exam=${duplicateExamId}&new_year=${newYear}&form_level=<?php echo urlencode($current_form); ?>`;
            }
        });
        
        function confirmToggle(examName, action, examId) {
            Swal.fire({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Exam?`,
                text: `Are you sure you want to ${action} "${examName}"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: action === 'activate' ? '#27ae60' : '#e74c3c',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, ${action}`,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `exam_type_manager.php?toggle_status=${examId}&form_level=<?php echo urlencode($current_form); ?>`;
                }
            });
            return false;
        }
        
        function confirmDelete(examName, resultsCount, examId) {
            let message = `Are you sure you want to delete "${examName}"?`;
            if (resultsCount > 0) {
                message = `⚠️ WARNING: This exam has ${resultsCount} student result(s)!\n\n`;
                message += `Deleting this exam will permanently delete ALL ${resultsCount} student result(s) associated with it.\n\n`;
                message += `Are you sure you want to delete "${examName}" and all its results?`;
            } else {
                message += `\n\nThis action cannot be undone.`;
            }
            
            Swal.fire({
                title: 'Delete Exam Type',
                html: message.replace(/\n/g, '<br>'),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#6c757d',
                confirmButtonText: resultsCount > 0 ? 'Yes, Delete Exam & Results' : 'Yes, Delete Exam',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `exam_type_manager.php?delete_exam=${examId}&form_level=<?php echo urlencode($current_form); ?>`;
                }
            });
            return false;
        }
        
        // Show success/error messages with SweetAlert
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