<?php
// leavers.php - Leavers & Graduates Management with Dynamic School Code
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user has permission (Head Master or Second Master only)
$admin_id = $_SESSION['admin_id'] ?? 0;

// ========== GET CURRENT SCHOOL ID AND SCHOOL CODE ==========
$school_query = "SELECT school_id, school_code FROM admins a JOIN schools s ON a.school_id = s.id WHERE a.id = ?";
$stmt = $conn->prepare($school_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$school_result = $stmt->get_result();
$current_admin_data = $school_result->fetch_assoc();
$current_school_id = $current_admin_data['school_id'] ?? 1;
$current_school_code = $current_admin_data['school_code'] ?? 'MVZ001';

// Get current user's roles (with school_id)
$user_roles_sql = "SELECT ara.role_id 
                   FROM admin_role_assignments ara
                   JOIN admins a ON ara.admin_id = a.id
                   WHERE ara.admin_id = ? AND a.school_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("ii", $admin_id, $current_school_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

// Check if user has Head Master (1), Second Master (2), or Academic Master (3) role
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 3) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view page you need.";
    header("Location: ../404.php");
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

// ==================== REGENERATE INDEX NUMBERS WITH DYNAMIC SCHOOL CODE ====================
function regenerateAllIndexNumbers($conn, $school_code, $school_id) {
    $combination_order = ['HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'];
    
    // Process Form Five - continuous numbering across all combinations with female first
    $form_five_index = 1; // Start from 0501 for Form Five
    
    foreach ($combination_order as $combination) {
        $form_five_sql = "SELECT id FROM students 
                         WHERE class = 'Form Five' 
                         AND combination = ?
                         AND is_leaver = FALSE
                         AND school_id = ?
                         ORDER BY 
                             CASE WHEN sex = 'Female' THEN 1 ELSE 2 END,
                             first_name, last_name";
        $stmt = $conn->prepare($form_five_sql);
        $stmt->bind_param("si", $combination, $school_id);
        $stmt->execute();
        $form_five_result = $stmt->get_result();
        
        while ($student = $form_five_result->fetch_assoc()) {
            $new_index = $school_code . '-' . str_pad(($form_five_index + 500), 4, '0', STR_PAD_LEFT);
            $update_sql = "UPDATE students SET index_number = ? WHERE id = ? AND school_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sii", $new_index, $student['id'], $school_id);
            $update_stmt->execute();
            $form_five_index++;
        }
    }
    
    // Process Form Six - continuous numbering across all combinations with female first
    $form_six_index = 1; // Start from 0501 for Form Six
    
    foreach ($combination_order as $combination) {
        $form_six_sql = "SELECT id FROM students 
                        WHERE class = 'Form Six' 
                        AND combination = ?
                        AND is_leaver = FALSE
                        AND school_id = ?
                        ORDER BY 
                            CASE WHEN sex = 'Female' THEN 1 ELSE 2 END,
                            first_name, last_name";
        $stmt = $conn->prepare($form_six_sql);
        $stmt->bind_param("si", $combination, $school_id);
        $stmt->execute();
        $form_six_result = $stmt->get_result();
        
        while ($student = $form_six_result->fetch_assoc()) {
            $new_index = $school_code . '-' . str_pad(($form_six_index + 500), 4, '0', STR_PAD_LEFT);
            $update_sql = "UPDATE students SET index_number = ? WHERE id = ? AND school_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sii", $new_index, $student['id'], $school_id);
            $update_stmt->execute();
            $form_six_index++;
        }
    }
    
    return true;
}

// Function to update room occupancy
function updateRoomOccupancy($conn, $room_id, $school_id) {
    $update_sql = "UPDATE dormitory_rooms 
                   SET current_occupancy = (
                       SELECT COUNT(*) FROM student_dormitory 
                       WHERE room_id = ? AND status = 'Active' AND school_id = ?
                   )
                   WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("iiii", $room_id, $school_id, $room_id, $school_id);
    return $stmt->execute();
}

// Function to update dormitory occupancy
function updateDormitoryOccupancy($conn, $dormitory_id, $school_id) {
    $update_sql = "UPDATE dormitories 
                   SET current_occupancy = (
                       SELECT COUNT(DISTINCT sd.id) 
                       FROM student_dormitory sd
                       JOIN dormitory_rooms dr ON sd.room_id = dr.id
                       WHERE dr.dormitory_id = ? AND sd.status = 'Active' AND sd.school_id = ?
                   )
                   WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("iiii", $dormitory_id, $school_id, $dormitory_id, $school_id);
    return $stmt->execute();
}

// Function to cleanup dormitory assignments for student
function cleanupStudentDormitoryAssignments($conn, $student_id, $school_id) {
    $cleaned_count = 0;
    $rooms_to_update = [];
    $dormitories_to_update = [];
    
    // Get all active assignments for this student
    $assignments_sql = "SELECT id, room_id, dormitory_id FROM student_dormitory 
                       WHERE student_id = ? AND status = 'Active' AND school_id = ?";
    $stmt = $conn->prepare($assignments_sql);
    $stmt->bind_param("ii", $student_id, $school_id);
    $stmt->execute();
    $assignments_result = $stmt->get_result();
    
    if ($assignments_result && $assignments_result->num_rows > 0) {
        while ($row = $assignments_result->fetch_assoc()) {
            $assignment_id = $row['id'];
            $room_id = $row['room_id'];
            $dormitory_id = $row['dormitory_id'];
            
            // Update assignment status
            $update_sql = "UPDATE student_dormitory 
                          SET status = 'Left', 
                              notes = CONCAT(COALESCE(notes, ''), ' | Removed: Auto-removed from leaver management'),
                              updated_at = CURRENT_TIMESTAMP
                          WHERE id = ? AND school_id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ii", $assignment_id, $school_id);
            $stmt->execute();
            
            $rooms_to_update[] = $room_id;
            $dormitories_to_update[] = $dormitory_id;
            $cleaned_count++;
        }
        
        // Update occupancies
        foreach (array_unique($rooms_to_update) as $room_id) {
            updateRoomOccupancy($conn, $room_id, $school_id);
        }
        
        foreach (array_unique($dormitories_to_update) as $dormitory_id) {
            updateDormitoryOccupancy($conn, $dormitory_id, $school_id);
        }
    }
    
    return $cleaned_count;
}

// Get all leavers (both graduates and leavers) for current school
$sql_leavers = "SELECT s.*, sl.reason, sl.leaver_type, sl.left_at, sl.returned
                FROM students s
                LEFT JOIN student_leavers sl ON s.id = sl.student_id AND sl.school_id = s.school_id
                WHERE s.is_leaver = TRUE AND s.school_id = ?
                ORDER BY s.graduation_status DESC, sl.left_at DESC";
$stmt = $conn->prepare($sql_leavers);
$stmt->bind_param("i", $current_school_id);
$stmt->execute();
$result_leavers = $stmt->get_result();
$leavers = [];
if ($result_leavers && $result_leavers->num_rows > 0) {
    while ($row = $result_leavers->fetch_assoc()) {
        $leavers[] = $row;
    }
}

// Handle bulk actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Bulk delete leavers - UPDATED with dormitory cleanup
    if (isset($_POST['bulk_delete']) && isset($_POST['selected_leavers'])) {
        $selected_ids = $_POST['selected_leavers'];
        
        if (empty($selected_ids)) {
            $_SESSION['error'] = "Please select at least one leaver to delete.";
            header("Location: leavers.php");
            exit();
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            $deleted_count = 0;
            $total_cleaned_assignments = 0;
            
            foreach ($selected_ids as $id) {
                $id = intval($id);
                
                // STEP 1: Clean up dormitory assignments first
                $cleaned_count = cleanupStudentDormitoryAssignments($conn, $id, $current_school_id);
                $total_cleaned_assignments += $cleaned_count;
                
                // STEP 2: Delete from student_leavers table
                $delete_leaver_sql = "DELETE FROM student_leavers WHERE student_id = ? AND school_id = ?";
                $stmt = $conn->prepare($delete_leaver_sql);
                $stmt->bind_param("ii", $id, $current_school_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error deleting from leavers table for student ID $id: " . $stmt->error);
                }
                
                // STEP 3: Delete from students table
                $delete_student_sql = "DELETE FROM students WHERE id = ? AND school_id = ?";
                $stmt = $conn->prepare($delete_student_sql);
                $stmt->bind_param("ii", $id, $current_school_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error deleting student ID $id: " . $stmt->error);
                }
                
                $deleted_count++;
            }
            
            // Regenerate index numbers after deletion
            regenerateAllIndexNumbers($conn, $current_school_code, $current_school_id);
            
            // Commit transaction
            mysqli_commit($conn);
            
            $message = "Successfully deleted $deleted_count leaver(s) permanently!";
            if ($total_cleaned_assignments > 0) {
                $message .= " $total_cleaned_assignments dormitory assignments cleaned up.";
            }
            $message .= " Index numbers regenerated with format: {$current_school_code}-XXXX.";
            
            $_SESSION['success'] = $message;
            header("Location: leavers.php");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = $e->getMessage();
            header("Location: leavers.php");
            exit();
        }
    }
    
    // Bulk return leavers
    if (isset($_POST['bulk_return']) && isset($_POST['selected_leavers']) && isset($_POST['return_class'])) {
        $selected_ids = $_POST['selected_leavers'];
        $return_class = mysqli_real_escape_string($conn, $_POST['return_class']);
        
        if (empty($selected_ids)) {
            $_SESSION['error'] = "Please select at least one leaver to return.";
            header("Location: leavers.php");
            exit();
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            $returned_count = 0;
            
            foreach ($selected_ids as $id) {
                $id = intval($id);
                
                // Update student to return
                $update_sql = "UPDATE students 
                              SET is_leaver = FALSE, 
                                  class = ?,
                                  status = TRUE,
                                  year_left = NULL,
                                  graduation_status = ?,
                                  graduation_year = NULL,
                                  previous_class = NULL,
                                  updated_at = CURRENT_TIMESTAMP,
                                  updated_by_admin = ?
                              WHERE id = ? AND school_id = ?";
                
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ssiii", $return_class, $return_class, $admin_id, $id, $current_school_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error returning student ID $id: " . $stmt->error);
                }
                
                // Update leavers table if record exists
                $update_leaver_sql = "UPDATE student_leavers 
                                     SET returned = TRUE, 
                                         returned_at = CURRENT_TIMESTAMP 
                                     WHERE student_id = ? AND school_id = ? AND returned = FALSE";
                $stmt = $conn->prepare($update_leaver_sql);
                $stmt->bind_param("ii", $id, $current_school_id);
                $stmt->execute();
                
                $returned_count++;
            }
            
            // Regenerate index numbers after all returns
            regenerateAllIndexNumbers($conn, $current_school_code, $current_school_id);
            
            mysqli_commit($conn);
            
            $_SESSION['success'] = "Successfully returned $returned_count leaver(s) to $return_class! Index numbers regenerated with format: {$current_school_code}-XXXX.";
            header("Location: leavers.php");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = $e->getMessage();
            header("Location: leavers.php");
            exit();
        }
    }
}

// Handle single return student (GET method)
if (isset($_GET['return_student'])) {
    $id = intval($_GET['return_student']);
    $return_class = mysqli_real_escape_string($conn, $_GET['class'] ?? 'Form Six');
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update student to return
        $update_sql = "UPDATE students 
                      SET is_leaver = FALSE, 
                          class = ?,
                          status = TRUE,
                          year_left = NULL,
                          graduation_status = ?,
                          graduation_year = NULL,
                          previous_class = NULL,
                          updated_at = CURRENT_TIMESTAMP,
                          updated_by_admin = ?
                      WHERE id = ? AND school_id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssiii", $return_class, $return_class, $admin_id, $id, $current_school_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error returning student: " . $stmt->error);
        }
        
        // Update leavers table if record exists
        $update_leaver_sql = "UPDATE student_leavers 
                             SET returned = TRUE, 
                                 returned_at = CURRENT_TIMESTAMP 
                             WHERE student_id = ? AND school_id = ? AND returned = FALSE";
        $stmt = $conn->prepare($update_leaver_sql);
        $stmt->bind_param("ii", $id, $current_school_id);
        $stmt->execute();
        
        // Regenerate index numbers
        regenerateAllIndexNumbers($conn, $current_school_code, $current_school_id);
        
        mysqli_commit($conn);
        
        $_SESSION['success'] = "Student returned to $return_class successfully! Index numbers regenerated with format: {$current_school_code}-XXXX.";
        header("Location: leavers.php");
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
        header("Location: leavers.php");
        exit();
    }
}

// Handle single delete leaver (GET method)
if (isset($_GET['delete_leaver'])) {
    $id = intval($_GET['delete_leaver']);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // STEP 1: Clean up dormitory assignments first
        $cleaned_count = cleanupStudentDormitoryAssignments($conn, $id, $current_school_id);
        
        // STEP 2: Delete from student_leavers table
        $delete_leaver_sql = "DELETE FROM student_leavers WHERE student_id = ? AND school_id = ?";
        $stmt = $conn->prepare($delete_leaver_sql);
        $stmt->bind_param("ii", $id, $current_school_id);
        if (!$stmt->execute()) {
            throw new Exception("Error deleting from leavers table: " . $stmt->error);
        }
        
        // STEP 3: Delete from students table
        $delete_student_sql = "DELETE FROM students WHERE id = ? AND school_id = ?";
        $stmt = $conn->prepare($delete_student_sql);
        $stmt->bind_param("ii", $id, $current_school_id);
        if (!$stmt->execute()) {
            throw new Exception("Error deleting student: " . $stmt->error);
        }
        
        // Regenerate index numbers after deletion
        regenerateAllIndexNumbers($conn, $current_school_code, $current_school_id);
        
        // Commit transaction
        mysqli_commit($conn);
        
        $message = "Leaver deleted permanently!";
        if ($cleaned_count > 0) {
            $message .= " $cleaned_count dormitory assignments cleaned up.";
        }
        $message .= " Index numbers regenerated with format: {$current_school_code}-XXXX.";
        
        $_SESSION['success'] = $message;
        header("Location: leavers.php");
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
        header("Location: leavers.php");
        exit();
    }
}

// Load theme settings for styling
$theme_settings = [];
$query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = ? AND school_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $admin_id, $current_school_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $theme_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = ? AND school_id = ?";
$stmt = $conn->prepare($prefs_query);
$stmt->bind_param("ii", $admin_id, $current_school_id);
$stmt->execute();
$prefs_result = $stmt->get_result();
if ($prefs_result && mysqli_num_rows($prefs_result) > 0) {
    while ($row = mysqli_fetch_assoc($prefs_result)) {
        $preferences[$row['preference_key']] = $row['preference_value'];
    }
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
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                Leavers & Graduates
                <small class="text-muted ms-2">School: <?php echo htmlspecialchars($current_school_code); ?></small>
            </h2>
            <div class="dropdown d-md-block d-none">
                <button class="btn btn-primary dropdown-toggle" type="button" id="actionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-2"></i>Actions
                </button>
                <ul class="dropdown-menu" aria-labelledby="actionsDropdown">
                    <li><a class="dropdown-item" href="students.php"><i class="fas fa-users me-2"></i>Back to Students</a></li>
                    <li><a class="dropdown-item" href="register.php"><i class="fas fa-user-plus me-2"></i>New Student</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="report_leaver.php"><i class="fas fa-download me-2"></i>Export List</a></li>
                </ul>
            </div>
            <!-- Mobile Actions Button -->
            <div class="dropdown d-md-none">
                <button class="btn btn-primary" type="button" id="mobileActionsBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileActionsBtn">
                    <li><a class="dropdown-item" href="students.php"><i class="fas fa-users me-2"></i>Back to Students</a></li>
                    <li><a class="dropdown-item" href="register.php"><i class="fas fa-user-plus me-2"></i>New Student</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="report_leaver.php"><i class="fas fa-download me-2"></i>Export List</a></li>
                </ul>
            </div>
        </div>

        <!-- SweetAlert2 Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div id="successMessage" data-message="<?php echo htmlspecialchars($_SESSION['success']); ?>"></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div id="errorMessage" data-message="<?php echo htmlspecialchars($_SESSION['error']); ?>"></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <!-- Total Leavers -->
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-slash" style="color: #dc3545;"></i>
                    </div>
                    <h3><?php echo count($leavers); ?></h3>
                    <p>Total Leavers</p>
                </div>
            </div>
            
            <!-- Graduates -->
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-graduation-cap" style="color: #28a745;"></i>
                    </div>
                    <h3>
                        <?php 
                        $graduates_count = 0;
                        foreach ($leavers as $leaver) {
                            if ($leaver['graduation_status'] == 'Graduated') {
                                $graduates_count++;
                            }
                        }
                        echo $graduates_count;
                        ?>
                    </h3>
                    <p>Graduates</p>
                </div>
            </div>
            
            <!-- Regular Leavers -->
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-sign-out-alt" style="color: #ffc107;"></i>
                    </div>
                    <h3>
                        <?php 
                        $leavers_count = count($leavers) - $graduates_count;
                        echo $leavers_count;
                        ?>
                    </h3>
                    <p>Regular Leavers</p>
                </div>
            </div>
            
            <!-- This Year -->
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-calendar" style="color: #17a2b8;"></i>
                    </div>
                    <h3>
                        <?php 
                        $current_year = date('Y');
                        $this_year_count = 0;
                        foreach ($leavers as $leaver) {
                            if ($leaver['year_left'] == $current_year) {
                                $this_year_count++;
                            }
                        }
                        echo $this_year_count;
                        ?>
                    </h3>
                    <p>This Year</p>
                </div>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="card mb-4" id="bulkActionsBar" style="display: none;">
            <div class="card-body py-2">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span id="selectedCount" class="badge bg-primary me-3">0 selected</span>
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-success" id="bulkReturnBtn">
                                <i class="fas fa-undo me-1"></i> Return Selected
                            </button>
                            <button type="button" class="btn btn-outline-danger" id="bulkDeleteBtn">
                                <i class="fas fa-trash me-1"></i> Delete Selected
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" id="clearSelectionBtn">
                        <i class="fas fa-times me-1"></i> Clear Selection
                    </button>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search leavers...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select id="typeFilter" class="form-select">
                            <option value="">All Types</option>
                            <option value="Graduated">Graduates</option>
                            <option value="Left">Regular Leavers</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="yearFilter" class="form-select">
                            <option value="">All Years</option>
                            <?php
                            $years = [];
                            foreach ($leavers as $leaver) {
                                if ($leaver['year_left']) {
                                    $years[$leaver['year_left']] = $leaver['year_left'];
                                }
                            }
                            krsort($years);
                            foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="classFilter" class="form-select">
                            <option value="">All Classes</option>
                            <option value="Form Five">Form Five</option>
                            <option value="Form Six">Form Six</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary" id="selectAllBtn">
                                <i class="fas fa-check-square me-1"></i> Select All
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="selectFilteredBtn">
                                <i class="fas fa-filter me-1"></i> Select Filtered
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leavers Table -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h4 class="mb-0">
                        <i class="fas fa-user-graduate me-2"></i>
                        Leavers & Graduates List
                        <span class="badge bg-light text-dark ms-2"><?php echo count($leavers); ?> Records</span>
                        <small class="text-white-50 ms-2">Index format: <?php echo htmlspecialchars($current_school_code); ?>-XXXX</small>
                    </h4>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAllCheckbox">
                        <label class="form-check-label text-white" for="selectAllCheckbox">
                            Select All
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="leaversTable">
                        <thead class="table-light">
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="checkAll" class="form-check-input">
                                </th>
                                <th>S/N</th>
                                <th>Index No.</th>
                                <th>Full Name</th>
                                <th>Combination</th>
                                <th>Type</th>
                                <th>Class Left</th>
                                <th>Year Left</th>
                                <th>Reason</th>
                                <th>Left Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leavers)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-5">
                                        <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                        <h5>No leavers or graduates found</h5>
                                        <p class="text-muted">Students marked as leavers or graduates will appear here.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leavers as $index => $student): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_leavers[]" value="<?php echo $student['id']; ?>" class="row-checkbox form-check-input">
                                    </td>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($student['index_number']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <?php if ($student['sex'] == 'Male'): ?>
                                                    <i class="fas fa-male text-primary"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-female" style="color: #e83e8c;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars($student['second_name'] ?? ''); ?>
                                                </div>
                                            </div>
                                        </div>
                                     </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($student['combination']); ?></span>
                                     </td>
                                    <td>
                                        <?php if ($student['graduation_status'] == 'Graduated'): ?>
                                            <span class="badge bg-success">Graduate</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Leaver</span>
                                        <?php endif; ?>
                                     </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($student['previous_class'] ?: $student['class']); ?></span>
                                     </td>
                                    <td>
                                        <span class="badge bg-dark"><?php echo htmlspecialchars($student['year_left']); ?></span>
                                      </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($student['reason'] ?: 'Not specified'); ?></small>
                                      </td>
                                    <td>
                                        <small><?php echo date('d/m/Y', strtotime($student['left_at'])); ?></small>
                                      </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-info view-leaver" 
                                                    data-bs-toggle="modal" data-bs-target="#viewLeaverModal"
                                                    data-student-id="<?php echo $student['id']; ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($student['graduation_status'] == 'Graduated'): ?>
                                                <button type="button" class="btn btn-outline-success return-graduate" 
                                                        data-id="<?php echo $student['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                        title="Return to Form Six">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-success return-leaver" 
                                                        data-id="<?php echo $student['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                        data-previous-class="<?php echo htmlspecialchars($student['previous_class']); ?>"
                                                        title="Return to Students">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-danger delete-leaver" 
                                                    data-id="<?php echo $student['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                    title="Delete Permanently">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
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
</div>

<!-- Bulk Actions Forms (Hidden) -->
<form method="POST" id="bulkReturnForm" style="display: none;">
    <input type="hidden" name="bulk_return" value="1">
    <input type="hidden" name="return_class" id="bulkReturnClassInput" value="Form Five">
    <div id="bulkReturnCheckboxes"></div>
</form>

<form method="POST" id="bulkDeleteForm" style="display: none;">
    <input type="hidden" name="bulk_delete" value="1">
    <div id="bulkDeleteCheckboxes"></div>
</form>

<!-- View Leaver Modal -->
<div class="modal fade" id="viewLeaverModal" tabindex="-1" aria-labelledby="viewLeaverModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="viewLeaverModalLabel">Leaver Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="leaverDetails">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading leaver details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Return Graduate Modal -->
<div class="modal fade" id="returnGraduateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-undo me-2"></i>Return Graduate</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-user-graduate fa-3x text-success mb-3"></i>
                <h5 class="mb-3">Return graduate to Form Six?</h5>
                <p class="mb-2"><strong id="returnGraduateName"></strong></p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Graduates can only be returned to Form Six. This will:
                    <ul class="text-start mt-2 mb-0">
                        <li>Return student to Form Six class</li>
                        <li>Reactivate student status</li>
                        <li>Regenerate index numbers with format: <strong><?php echo htmlspecialchars($current_school_code); ?>-XXXX</strong></li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmReturnGraduate" class="btn btn-success">
                    <i class="fas fa-undo me-2"></i>Return to Form Six
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Return Leaver Modal -->
<div class="modal fade" id="returnLeaverModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-undo me-2"></i>Return Leaver</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-user-check fa-3x text-warning mb-3"></i>
                <h5 class="mb-3">Return leaver to which class?</h5>
                <p class="mb-2"><strong id="returnLeaverName"></strong></p>
                <div class="form-group mt-3">
                    <label for="returnLeaverClass" class="form-label">Return to class:</label>
                    <select id="returnLeaverClass" class="form-select">
                        <option value="Form Five">Form Five</option>
                        <option value="Form Six">Form Six</option>
                    </select>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    This will return the student to active students list and regenerate index numbers with format: <strong><?php echo htmlspecialchars($current_school_code); ?>-XXXX</strong>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmReturnLeaver" class="btn btn-warning">
                    <i class="fas fa-undo me-2"></i>Return Student
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Leaver Confirmation Modal -->
<div class="modal fade" id="deleteLeaverModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Permanently</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                <h5 class="mb-3">Delete leaver permanently?</h5>
                <p class="mb-2"><strong id="deleteLeaverName"></strong></p>
                <p class="text-danger">
                    <small>
                        <i class="fas fa-exclamation-circle me-1"></i>
                        This will permanently delete the student from both students and leavers tables.<br>
                        This action cannot be undone!<br>
                        Index numbers will be regenerated after deletion.
                    </small>
                </p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteLeaver" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete Permanently
                </a>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<style>
:root {
    --primary-color: <?php echo $colors['primary']; ?>;
    --primary-dark: <?php echo $colors['primary_dark']; ?>;
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

/* LEAVERS PAGE SPECIFIC STYLES */
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: rgba(59, 157, 179, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.stats-card.simple-card {
    border: none;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    background: var(--white, white);
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.stats-card.simple-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
}

.stats-card.simple-card:nth-child(1)::before { background: #dc3545; }
.stats-card.simple-card:nth-child(2)::before { background: #28a745; }
.stats-card.simple-card:nth-child(3)::before { background: #ffc107; }
.stats-card.simple-card:nth-child(4)::before { background: #17a2b8; }

.stats-card.simple-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.stats-card.simple-card .stats-icon {
    margin-bottom: 10px;
}

.stats-card.simple-card .stats-icon i {
    font-size: 2.2rem;
}

.stats-card.simple-card h3 {
    font-size: 1.8rem;
    font-weight: bold;
    color: #333;
    margin: 10px 0;
}

.stats-card.simple-card p {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
    font-weight: 500;
}

.table th {
    font-weight: 600;
    color: #333;
    background-color: rgba(59, 157, 179, 0.05);
    border-bottom: 2px solid rgba(59, 157, 179, 0.2);
    padding: 12px 8px;
}

.btn-outline-info, .btn-outline-warning, .btn-outline-secondary, 
.btn-outline-danger, .btn-outline-dark, .btn-outline-success {
    border-width: 1px;
    transition: all 0.2s ease;
}

.btn-outline-info:hover, .btn-outline-warning:hover, .btn-outline-secondary:hover,
.btn-outline-danger:hover, .btn-outline-dark:hover, .btn-outline-success:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Bulk Actions Bar */
#bulkActionsBar {
    background-color: #f8f9fa;
    border-left: 4px solid #007bff;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .stats-card.simple-card {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
    
    .btn-group {
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .btn-group .btn {
        margin-bottom: 5px;
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
        flex: 1;
        min-width: 40px;
    }
    
    .modal-dialog {
        margin: 10px;
    }
    
    .avatar-circle {
        width: 35px;
        height: 35px;
        margin-right: 10px;
    }
    
    .dropdown-menu {
        position: absolute;
        right: 0;
        left: auto;
    }
    
    /* Adjust table for mobile */
    #leaversTable {
        font-size: 0.85rem;
    }
    
    #leaversTable th,
    #leaversTable td {
        padding: 6px 4px;
    }
    
    /* Bulk actions responsive */
    #bulkActionsBar .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    #bulkActionsBar .btn {
        width: 100%;
        margin-bottom: 5px;
    }
}

/* Desktop Actions */
@media (min-width: 769px) {
    .btn-group {
        display: flex;
        flex-wrap: nowrap;
    }
    
    .btn-group .btn {
        margin: 0 2px;
    }
}

/* Checkbox styling */
.form-check-input:checked {
    background-color: #007bff;
    border-color: #007bff;
}

.table th:first-child,
.table td:first-child {
    width: 50px;
    text-align: center;
}

/* Highlight selected rows */
.row-checkbox:checked + td {
    background-color: rgba(0, 123, 255, 0.05);
}
</style>

<script>
// Show SweetAlert2 notifications
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    
    if (successMessage) {
        Swal.fire({
            title: 'Success!',
            text: successMessage.getAttribute('data-message'),
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6',
            timer: 5000,
            timerProgressBar: true,
        });
    }
    
    if (errorMessage) {
        Swal.fire({
            title: 'Error!',
            text: errorMessage.getAttribute('data-message'),
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#d33',
        });
    }
});

// Bulk selection functionality
let selectedCount = 0;
const bulkActionsBar = document.getElementById('bulkActionsBar');
const selectedCountSpan = document.getElementById('selectedCount');
const checkAllCheckbox = document.getElementById('checkAll');
const rowCheckboxes = document.querySelectorAll('.row-checkbox');
const bulkReturnForm = document.getElementById('bulkReturnForm');
const bulkDeleteForm = document.getElementById('bulkDeleteForm');
const bulkReturnClassInput = document.getElementById('bulkReturnClassInput');

// Update selection count
function updateSelectionCount() {
    selectedCount = document.querySelectorAll('.row-checkbox:checked').length;
    selectedCountSpan.textContent = `${selectedCount} selected`;
    
    if (selectedCount > 0) {
        bulkActionsBar.style.display = 'block';
    } else {
        bulkActionsBar.style.display = 'none';
    }
    
    const totalRows = rowCheckboxes.length;
    checkAllCheckbox.checked = selectedCount === totalRows;
    document.getElementById('selectAllCheckbox').checked = selectedCount === totalRows;
}

// Select all checkboxes
checkAllCheckbox.addEventListener('change', function() {
    rowCheckboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelectionCount();
});

document.getElementById('selectAllCheckbox').addEventListener('change', function() {
    rowCheckboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    checkAllCheckbox.checked = this.checked;
    updateSelectionCount();
});

rowCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', updateSelectionCount);
});

document.getElementById('clearSelectionBtn').addEventListener('click', function() {
    rowCheckboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    checkAllCheckbox.checked = false;
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectionCount();
});

document.getElementById('selectAllBtn').addEventListener('click', function() {
    const visibleRows = document.querySelectorAll('#leaversTable tbody tr:not([style*="display: none"])');
    visibleRows.forEach(row => {
        const checkbox = row.querySelector('.row-checkbox');
        if (checkbox) checkbox.checked = true;
    });
    updateSelectionCount();
});

document.getElementById('selectFilteredBtn').addEventListener('click', function() {
    const visibleRows = document.querySelectorAll('#leaversTable tbody tr:not([style*="display: none"])');
    
    rowCheckboxes.forEach(checkbox => { checkbox.checked = false; });
    
    visibleRows.forEach(row => {
        const checkbox = row.querySelector('.row-checkbox');
        if (checkbox) checkbox.checked = true;
    });
    updateSelectionCount();
});

// Bulk Return Button
document.getElementById('bulkReturnBtn').addEventListener('click', function() {
    const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        Swal.fire({ title: 'Error!', text: 'Please select at least one leaver to return.', icon: 'error', confirmButtonText: 'OK' });
        return;
    }
    
    const selectedNames = [];
    selectedCheckboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const name = row.querySelector('td:nth-child(4) strong')?.textContent || 'Unknown';
        selectedNames.push(name);
    });
    
    Swal.fire({
        title: 'Return Selected Leavers',
        html: `
            <div class="text-start">
                <p>You are about to return <strong>${selectedCheckboxes.length}</strong> leaver(s).</p>
                <p>Please select the class to return them to:</p>
                <select id="swalReturnClass" class="form-select mt-2">
                    <option value="Form Five">Form Five</option>
                    <option value="Form Six">Form Six</option>
                </select>
                <div class="mt-3">
                    <strong>Selected leavers:</strong>
                    <div class="mt-1 small text-muted" style="max-height: 100px; overflow-y: auto;">
                        ${selectedNames.map(name => `<div>• ${name}</div>`).join('')}
                    </div>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Index numbers will be regenerated with format: <?php echo htmlspecialchars($current_school_code); ?>-XXXX
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Return Selected',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const returnClass = document.getElementById('swalReturnClass').value;
            if (!returnClass) {
                Swal.showValidationMessage('Please select a class');
                return false;
            }
            return returnClass;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            bulkReturnClassInput.value = result.value;
            document.getElementById('bulkReturnCheckboxes').innerHTML = '';
            selectedCheckboxes.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'selected_leavers[]';
                hiddenInput.value = checkbox.value;
                document.getElementById('bulkReturnCheckboxes').appendChild(hiddenInput);
            });
            bulkReturnForm.submit();
        }
    });
});

// Bulk Delete Button
document.getElementById('bulkDeleteBtn').addEventListener('click', function() {
    const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        Swal.fire({ title: 'Error!', text: 'Please select at least one leaver to delete.', icon: 'error', confirmButtonText: 'OK' });
        return;
    }
    
    const selectedNames = [];
    selectedCheckboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const name = row.querySelector('td:nth-child(4) strong')?.textContent || 'Unknown';
        selectedNames.push(name);
    });
    
    Swal.fire({
        title: 'Delete Selected Leavers',
        html: `
            <div class="text-start">
                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone!</p>
                <p>You are about to permanently delete <strong>${selectedCheckboxes.length}</strong> leaver(s).</p>
                <div class="mt-3">
                    <strong>Selected leavers for deletion:</strong>
                    <div class="mt-1 small text-danger" style="max-height: 100px; overflow-y: auto;">
                        ${selectedNames.map(name => `<div>• ${name}</div>`).join('')}
                    </div>
                </div>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Index numbers will be regenerated after deletion with format: <?php echo htmlspecialchars($current_school_code); ?>-XXXX
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete Permanently',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('bulkDeleteCheckboxes').innerHTML = '';
            selectedCheckboxes.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'selected_leavers[]';
                hiddenInput.value = checkbox.value;
                document.getElementById('bulkDeleteCheckboxes').appendChild(hiddenInput);
            });
            bulkDeleteForm.submit();
        }
    });
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const leaverRows = document.querySelectorAll('#leaversTable tbody tr');
    leaverRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
    updateSelectionCount();
});

// Filter functionality
document.getElementById('typeFilter').addEventListener('change', filterLeavers);
document.getElementById('yearFilter').addEventListener('change', filterLeavers);
document.getElementById('classFilter').addEventListener('change', filterLeavers);

function filterLeavers() {
    const type = document.getElementById('typeFilter').value;
    const year = document.getElementById('yearFilter').value;
    const classFilter = document.getElementById('classFilter').value;
    const leaverRows = document.querySelectorAll('#leaversTable tbody tr');
    
    leaverRows.forEach(row => {
        if (row.cells.length < 11) return;
        const rowType = row.cells[5]?.querySelector('.badge')?.textContent.trim() || '';
        const rowYear = row.cells[7]?.querySelector('.badge')?.textContent.trim() || '';
        const rowClass = row.cells[6]?.querySelector('.badge')?.textContent.trim() || '';
        
        const showType = !type || (type === 'Graduated' && rowType === 'Graduate') || (type === 'Left' && rowType === 'Leaver');
        const showYear = !year || rowYear === year;
        const showClass = !classFilter || rowClass === classFilter;
        
        row.style.display = (showType && showYear && showClass) ? '' : 'none';
    });
    updateSelectionCount();
}

// View leaver details
document.querySelectorAll('.view-leaver').forEach(button => {
    button.addEventListener('click', function() {
        const studentId = this.getAttribute('data-student-id');
        document.getElementById('leaverDetails').innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                <p class="mt-2">Loading leaver details...</p>
            </div>
        `;
        fetch(`get_student.php?id=${studentId}&leaver=1&school_id=<?php echo $current_school_id; ?>`)
            .then(response => response.text())
            .then(data => { document.getElementById('leaverDetails').innerHTML = data; })
            .catch(error => { document.getElementById('leaverDetails').innerHTML = '<div class="alert alert-danger">Error loading leaver details.</div>'; });
    });
});

// Return graduate confirmation
document.querySelectorAll('.return-graduate').forEach(button => {
    button.addEventListener('click', function() {
        const studentId = this.getAttribute('data-id');
        const studentName = this.getAttribute('data-name');
        document.getElementById('returnGraduateName').textContent = studentName;
        document.getElementById('confirmReturnGraduate').href = `leavers.php?return_student=${studentId}&class=Form Six`;
        new bootstrap.Modal(document.getElementById('returnGraduateModal')).show();
    });
});

// Return leaver confirmation
document.querySelectorAll('.return-leaver').forEach(button => {
    button.addEventListener('click', function() {
        const studentId = this.getAttribute('data-id');
        const studentName = this.getAttribute('data-name');
        const previousClass = this.getAttribute('data-previous-class') || 'Form Five';
        document.getElementById('returnLeaverName').textContent = studentName;
        document.getElementById('returnLeaverClass').value = previousClass;
        document.getElementById('confirmReturnLeaver').href = `leavers.php?return_student=${studentId}&class=${previousClass}`;
        new bootstrap.Modal(document.getElementById('returnLeaverModal')).show();
    });
});

document.getElementById('returnLeaverClass')?.addEventListener('change', function() {
    const studentId = document.querySelector('#returnLeaverModal .return-leaver')?.getAttribute('data-id');
    if (studentId) {
        document.getElementById('confirmReturnLeaver').href = `leavers.php?return_student=${studentId}&class=${this.value}`;
    }
});

// Delete leaver confirmation
document.querySelectorAll('.delete-leaver').forEach(button => {
    button.addEventListener('click', function() {
        const studentId = this.getAttribute('data-id');
        const studentName = this.getAttribute('data-name');
        document.getElementById('deleteLeaverName').textContent = studentName;
        document.getElementById('confirmDeleteLeaver').href = `leavers.php?delete_leaver=${studentId}`;
        new bootstrap.Modal(document.getElementById('deleteLeaverModal')).show();
    });
});

// Initialize selection count
updateSelectionCount();
</script>

<?php include '../controller/footer.php'; ?>