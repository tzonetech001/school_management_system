<?php
// manage_attendance.php - Teacher Class Assignment Management
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

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

// Check if user has permission (Head Master, Second Master, or Academic Master)
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

$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 3) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to manage attendance assignments.";
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

// Get all teachers (users with class teacher role)
$teachers_sql = "SELECT DISTINCT a.id, a.first_name, a.middle_name, a.last_name, a.email 
                 FROM admins a
                 INNER JOIN admin_role_assignments ara ON a.id = ara.admin_id
                 INNER JOIN admin_roles ar ON ara.role_id = ar.id
                 WHERE a.school_id = ? AND ar.role_name = 'Class Teacher' AND a.status = 1
                 ORDER BY a.first_name, a.last_name";
$stmt = $conn->prepare($teachers_sql);
$stmt->bind_param("i", $current_school_id);
$stmt->execute();
$teachers_result = $stmt->get_result();
$teachers = [];
while ($row = $teachers_result->fetch_assoc()) {
    $teachers[] = $row;
}

// Get all combinations
$combinations = ['HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'];
$class_levels = ['Form Five', 'Form Six'];

// Get existing assignments
$assignments_sql = "SELECT tca.*, 
                    CONCAT(a.first_name, ' ', a.last_name) as teacher_name,
                    assigned.first_name as assigned_by_name
                    FROM teacher_class_assignments tca
                    JOIN admins a ON tca.teacher_id = a.id
                    JOIN admins assigned ON tca.assigned_by = assigned.id
                    WHERE tca.school_id = ?
                    ORDER BY tca.class_level, tca.combination, a.first_name";
$stmt = $conn->prepare($assignments_sql);
$stmt->bind_param("i", $current_school_id);
$stmt->execute();
$assignments_result = $stmt->get_result();
$assignments = [];
while ($row = $assignments_result->fetch_assoc()) {
    $assignments[] = $row;
}

// ==================== HANDLE AJAX REQUESTS ====================

// Handle Add Assignment (AJAX)
if (isset($_POST['ajax_add']) && $_POST['ajax_add'] == 1) {
    header('Content-Type: application/json');
    
    $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id'] ?? '');
    $class_level = mysqli_real_escape_string($conn, $_POST['class_level'] ?? '');
    $combination = mysqli_real_escape_string($conn, $_POST['combination'] ?? '');
    
    $response = ['success' => false, 'message' => ''];
    
    if (empty($teacher_id) || empty($class_level) || empty($combination)) {
        $response['message'] = 'Please select all fields.';
        echo json_encode($response);
        exit();
    }
    
    // Check if assignment already exists
    $check_sql = "SELECT id FROM teacher_class_assignments 
                  WHERE teacher_id = ? AND class_level = ? AND combination = ? AND school_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("issi", $teacher_id, $class_level, $combination, $current_school_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $response['message'] = 'This teacher is already assigned to this class and combination.';
        echo json_encode($response);
        exit();
    }
    
    $insert_sql = "INSERT INTO teacher_class_assignments (teacher_id, class_level, combination, assigned_by, school_id) 
                   VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("issii", $teacher_id, $class_level, $combination, $admin_id, $current_school_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Teacher assigned successfully!';
    } else {
        $response['message'] = 'Error assigning teacher: ' . $stmt->error;
    }
    
    echo json_encode($response);
    exit();
}

// Handle Edit Assignment (AJAX)
if (isset($_POST['ajax_edit']) && $_POST['ajax_edit'] == 1) {
    header('Content-Type: application/json');
    
    $assignment_id = intval($_POST['assignment_id'] ?? 0);
    $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id'] ?? '');
    $class_level = mysqli_real_escape_string($conn, $_POST['class_level'] ?? '');
    $combination = mysqli_real_escape_string($conn, $_POST['combination'] ?? '');
    
    $response = ['success' => false, 'message' => ''];
    
    if (empty($assignment_id) || empty($teacher_id) || empty($class_level) || empty($combination)) {
        $response['message'] = 'Please select all fields.';
        echo json_encode($response);
        exit();
    }
    
    // Check if assignment already exists for this teacher (excluding current)
    $check_sql = "SELECT id FROM teacher_class_assignments 
                  WHERE teacher_id = ? AND class_level = ? AND combination = ? 
                  AND school_id = ? AND id != ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("issii", $teacher_id, $class_level, $combination, $current_school_id, $assignment_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $response['message'] = 'This teacher is already assigned to this class and combination.';
        echo json_encode($response);
        exit();
    }
    
    $update_sql = "UPDATE teacher_class_assignments 
                   SET teacher_id = ?, class_level = ?, combination = ? 
                   WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("issii", $teacher_id, $class_level, $combination, $assignment_id, $current_school_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Assignment updated successfully!';
    } else {
        $response['message'] = 'Error updating assignment: ' . $stmt->error;
    }
    
    echo json_encode($response);
    exit();
}

// Handle Delete Assignment (AJAX)
if (isset($_POST['ajax_delete']) && $_POST['ajax_delete'] == 1) {
    header('Content-Type: application/json');
    
    $assignment_id = intval($_POST['assignment_id'] ?? 0);
    
    $response = ['success' => false, 'message' => ''];
    
    if (empty($assignment_id)) {
        $response['message'] = 'Invalid assignment ID.';
        echo json_encode($response);
        exit();
    }
    
    $delete_sql = "DELETE FROM teacher_class_assignments WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("ii", $assignment_id, $current_school_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Assignment removed successfully!';
    } else {
        $response['message'] = 'Error removing assignment: ' . $stmt->error;
    }
    
    echo json_encode($response);
    exit();
}

// Handle Get Assignment Data for Edit (AJAX)
if (isset($_GET['ajax_get_assignment']) && $_GET['ajax_get_assignment'] == 1) {
    header('Content-Type: application/json');
    
    $assignment_id = intval($_GET['assignment_id'] ?? 0);
    
    if (empty($assignment_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid assignment ID.']);
        exit();
    }
    
    $sql = "SELECT tca.*, 
                    CONCAT(a.first_name, ' ', a.last_name) as teacher_name
            FROM teacher_class_assignments tca
            JOIN admins a ON tca.teacher_id = a.id
            WHERE tca.id = ? AND tca.school_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $assignment_id, $current_school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Assignment not found.']);
    }
    exit();
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
    }
    
    .btn-primary-custom {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3);
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
    
    .btn-danger-custom {
        background: linear-gradient(135deg, var(--danger-color), #bd2130);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .btn-danger-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        color: white;
    }
    
    .btn-warning-custom {
        background: linear-gradient(135deg, var(--warning-color), #e0a800);
        color: #856404;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .btn-warning-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
        color: #856404;
    }
    
    .btn-outline-secondary-custom {
        border: 2px solid var(--border-color);
        color: var(--text-color);
        background: transparent;
        padding: 6px 12px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .btn-outline-secondary-custom:hover {
        background: var(--gray);
    }
    
    .table th {
        font-weight: 600;
        color: var(--text-color);
        background-color: rgba(59, 157, 179, 0.05);
        border-bottom: 2px solid rgba(59, 157, 179, 0.2);
    }
    
    .action-buttons .btn {
        margin: 0 2px;
        padding: 4px 10px;
        font-size: 0.8rem;
        border-radius: 6px;
    }
    
    .action-buttons .btn i {
        font-size: 0.9rem;
    }
    
    .badge-primary {
        background-color: var(--primary-color) !important;
    }
    
    /* Modal Styles */
    .modal-content {
        border: none;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    
    .modal-header {
        padding: 20px 25px;
        border-bottom: none;
    }
    
    .modal-header.bg-primary-custom {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
    }
    
    .modal-header .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.8;
        transition: all 0.3s ease;
    }
    
    .modal-header .btn-close:hover {
        opacity: 1;
        transform: rotate(90deg);
    }
    
    .modal-body {
        padding: 25px;
    }
    
    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid var(--border-color);
    }
    
    /* Animation for new rows */
    .row-added {
        animation: rowSlideIn 0.5s ease;
    }
    
    @keyframes rowSlideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @media (max-width: 768px) {
        .page-header h2 {
            font-size: 1.3rem;
        }
        .table th, .table td {
            font-size: 0.8rem;
            padding: 6px 4px;
        }
        .action-buttons .btn {
            padding: 2px 6px;
            font-size: 0.7rem;
        }
        .action-buttons .btn i {
            font-size: 0.7rem;
        }
    }
</style>

<div class="main-content <?php echo $sidebarClass; ?>">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center mb-2 mb-sm-0">
                    
                    <h2 class="mb-0">
                        <i class="fas fa-chalkboard-teacher me-2"></i>
                        Manage Attendance Assignments
                    </h2>
                </div>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-calendar-alt me-1"></i>
                    <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>
        
        <!-- Add Assignment Button -->
        <div class="mb-4">
            <button type="button" class="btn btn-primary-custom" id="addAssignmentBtn">
                <i class="fas fa-plus me-2"></i>New Assignment
            </button>
        </div>
        
        <!-- Current Assignments -->
        <div class="form-card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>
                Current Assignments
                <span class="badge bg-light text-dark ms-2" id="assignmentCount"><?php echo count($assignments); ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="assignmentsTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Teacher</th>
                                <th>Class</th>
                                <th>Combination</th>
                                <th>Assigned By</th>
                                <th>Assigned At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="assignmentsBody">
                            <?php if (empty($assignments)): ?>
                                <tr id="noDataRow">
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-info-circle fa-2x text-muted mb-2 d-block"></i>
                                        No assignments found. Click <strong>"New Assignment"</strong> to add one.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignments as $index => $assignment): ?>
                                    <tr id="assignment-row-<?php echo $assignment['id']; ?>">
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($assignment['teacher_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($assignment['class_level']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($assignment['combination']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($assignment['assigned_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons d-flex flex-wrap">
                                                <button class="btn btn-warning-custom edit-assignment" 
                                                        data-id="<?php echo $assignment['id']; ?>"
                                                        data-teacher-id="<?php echo $assignment['teacher_id']; ?>"
                                                        data-class-level="<?php echo $assignment['class_level']; ?>"
                                                        data-combination="<?php echo $assignment['combination']; ?>"
                                                        title="Edit Assignment">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger-custom delete-assignment" 
                                                        data-id="<?php echo $assignment['id']; ?>"
                                                        data-teacher="<?php echo htmlspecialchars($assignment['teacher_name']); ?>"
                                                        data-class="<?php echo htmlspecialchars($assignment['class_level']); ?>"
                                                        data-combination="<?php echo htmlspecialchars($assignment['combination']); ?>"
                                                        title="Delete Assignment">
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

<!-- ============================================ -->
<!-- ADD ASSIGNMENT MODAL -->
<!-- ============================================ -->
<div class="modal fade" id="addAssignmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary-custom">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>New Assignment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addAssignmentForm">
                    <div class="mb-3">
                        <label for="add_teacher_id" class="form-label">Select Teacher <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="add_teacher_id" class="form-select" required>
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . ($teacher['middle_name'] ?? '') . ' ' . $teacher['last_name']); ?>
                                    (<?php echo htmlspecialchars($teacher['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_class_level" class="form-label">Class Level <span class="text-danger">*</span></label>
                        <select name="class_level" id="add_class_level" class="form-select" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($class_levels as $level): ?>
                                <option value="<?php echo $level; ?>"><?php echo $level; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_combination" class="form-label">Combination <span class="text-danger">*</span></label>
                        <select name="combination" id="add_combination" class="form-select" required>
                            <option value="">-- Select Combination --</option>
                            <?php foreach ($combinations as $comb): ?>
                                <option value="<?php echo $comb; ?>"><?php echo $comb; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success-custom" id="saveAssignmentBtn">
                    <i class="fas fa-save me-2"></i>Assign Teacher
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- EDIT ASSIGNMENT MODAL -->
<!-- ============================================ -->
<div class="modal fade" id="editAssignmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--warning-color), #e0a800); color: #856404;">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit Assignment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editAssignmentForm">
                    <input type="hidden" name="assignment_id" id="edit_assignment_id">
                    
                    <div class="mb-3">
                        <label for="edit_teacher_id" class="form-label">Select Teacher <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="edit_teacher_id" class="form-select" required>
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . ($teacher['middle_name'] ?? '') . ' ' . $teacher['last_name']); ?>
                                    (<?php echo htmlspecialchars($teacher['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_class_level" class="form-label">Class Level <span class="text-danger">*</span></label>
                        <select name="class_level" id="edit_class_level" class="form-select" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($class_levels as $level): ?>
                                <option value="<?php echo $level; ?>"><?php echo $level; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_combination" class="form-label">Combination <span class="text-danger">*</span></label>
                        <select name="combination" id="edit_combination" class="form-select" required>
                            <option value="">-- Select Combination --</option>
                            <?php foreach ($combinations as $comb): ?>
                                <option value="<?php echo $comb; ?>"><?php echo $comb; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning-custom" id="updateAssignmentBtn">
                    <i class="fas fa-save me-2"></i>Update Assignment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- SweetAlert2 & Bootstrap JS -->
<!-- ============================================ -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ============================================
// MANAGE ATTENDANCE ASSIGNMENTS - FULL SCRIPT
// ============================================

// ============================================
// OPEN ADD MODAL
// ============================================
document.getElementById('addAssignmentBtn')?.addEventListener('click', function() {
    // Reset form
    document.getElementById('addAssignmentForm').reset();
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('addAssignmentModal'));
    modal.show();
});

// ============================================
// SAVE ASSIGNMENT (ADD)
// ============================================
document.getElementById('saveAssignmentBtn')?.addEventListener('click', function() {
    const teacherId = document.getElementById('add_teacher_id').value;
    const classLevel = document.getElementById('add_class_level').value;
    const combination = document.getElementById('add_combination').value;
    
    if (!teacherId || !classLevel || !combination) {
        Swal.fire({
            title: 'Validation Error',
            text: 'Please select all fields.',
            icon: 'warning',
            confirmButtonColor: '#ffc107'
        });
        return;
    }
    
    // Disable button and show loading
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    
    // Prepare data
    const formData = new FormData();
    formData.append('ajax_add', '1');
    formData.append('teacher_id', teacherId);
    formData.append('class_level', classLevel);
    formData.append('combination', combination);
    
    // Send AJAX
    fetch('manage_attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-2"></i>Assign Teacher';
        
        if (data.success) {
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('addAssignmentModal')).hide();
            
            // Show success popup
            Swal.fire({
                title: 'Success!',
                text: data.message,
                icon: 'success',
                confirmButtonColor: '#28a745',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonText: '<i class="fas fa-check me-2"></i>OK'
            }).then(() => {
                // Reload page to show new assignment
                location.reload();
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: data.message,
                icon: 'error',
                confirmButtonColor: '#d33'
            });
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-2"></i>Assign Teacher';
        
        Swal.fire({
            title: 'Error!',
            text: 'Network error. Please try again.',
            icon: 'error',
            confirmButtonColor: '#d33'
        });
        console.error('Error:', error);
    });
});

// ============================================
// OPEN EDIT MODAL
// ============================================
document.querySelectorAll('.edit-assignment').forEach(btn => {
    btn.addEventListener('click', function() {
        const assignmentId = this.getAttribute('data-id');
        const teacherId = this.getAttribute('data-teacher-id');
        const classLevel = this.getAttribute('data-class-level');
        const combination = this.getAttribute('data-combination');
        
        // Set values in edit form
        document.getElementById('edit_assignment_id').value = assignmentId;
        document.getElementById('edit_teacher_id').value = teacherId;
        document.getElementById('edit_class_level').value = classLevel;
        document.getElementById('edit_combination').value = combination;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('editAssignmentModal'));
        modal.show();
    });
});

// ============================================
// UPDATE ASSIGNMENT (EDIT)
// ============================================
document.getElementById('updateAssignmentBtn')?.addEventListener('click', function() {
    const assignmentId = document.getElementById('edit_assignment_id').value;
    const teacherId = document.getElementById('edit_teacher_id').value;
    const classLevel = document.getElementById('edit_class_level').value;
    const combination = document.getElementById('edit_combination').value;
    
    if (!assignmentId || !teacherId || !classLevel || !combination) {
        Swal.fire({
            title: 'Validation Error',
            text: 'Please select all fields.',
            icon: 'warning',
            confirmButtonColor: '#ffc107'
        });
        return;
    }
    
    // Disable button and show loading
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
    
    // Prepare data
    const formData = new FormData();
    formData.append('ajax_edit', '1');
    formData.append('assignment_id', assignmentId);
    formData.append('teacher_id', teacherId);
    formData.append('class_level', classLevel);
    formData.append('combination', combination);
    
    // Send AJAX
    fetch('manage_attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-2"></i>Update Assignment';
        
        if (data.success) {
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('editAssignmentModal')).hide();
            
            // Show success popup
            Swal.fire({
                title: 'Updated!',
                text: data.message,
                icon: 'success',
                confirmButtonColor: '#ffc107',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonText: '<i class="fas fa-check me-2"></i>OK'
            }).then(() => {
                // Reload page to show updated assignment
                location.reload();
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: data.message,
                icon: 'error',
                confirmButtonColor: '#d33'
            });
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-2"></i>Update Assignment';
        
        Swal.fire({
            title: 'Error!',
            text: 'Network error. Please try again.',
            icon: 'error',
            confirmButtonColor: '#d33'
        });
        console.error('Error:', error);
    });
});

// ============================================
// DELETE ASSIGNMENT
// ============================================
document.querySelectorAll('.delete-assignment').forEach(btn => {
    btn.addEventListener('click', function() {
        const assignmentId = this.getAttribute('data-id');
        const teacherName = this.getAttribute('data-teacher');
        const classLevel = this.getAttribute('data-class');
        const combination = this.getAttribute('data-combination');
        
        Swal.fire({
            title: 'Delete Assignment?',
            html: `
                <div class="text-center">
                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                    <p><strong>${teacherName}</strong></p>
                    <p><span class="badge bg-info">${classLevel}</span> - <span class="badge bg-primary">${combination}</span></p>
                    <div class="alert alert-danger mt-2">
                        <small>This action cannot be undone.</small>
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash me-2"></i>Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Deleting...',
                    text: 'Please wait.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Prepare data
                const formData = new FormData();
                formData.append('ajax_delete', '1');
                formData.append('assignment_id', assignmentId);
                
                // Send AJAX
                fetch('manage_attendance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Deleted!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonColor: '#28a745',
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: true,
                            confirmButtonText: '<i class="fas fa-check me-2"></i>OK'
                        }).then(() => {
                            // Reload page
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: data.message,
                            icon: 'error',
                            confirmButtonColor: '#d33'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Network error. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#d33'
                    });
                    console.error('Error:', error);
                });
            }
        });
    });
});

// ============================================
// SUCCESS/ERROR MESSAGES FROM SERVER
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Check for success message from URL (for non-AJAX operations)
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    
    if (msg) {
        Swal.fire({
            title: msg === 'success' ? 'Success!' : 'Error!',
            text: msg === 'success' ? 'Operation completed successfully.' : 'An error occurred.',
            icon: msg === 'success' ? 'success' : 'error',
            confirmButtonColor: msg === 'success' ? '#28a745' : '#d33'
        });
    }
});

// ============================================
// KEYBOARD SHORTCUTS
// ============================================
document.addEventListener('keydown', function(e) {
    // Escape key to close modals
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            bootstrap.Modal.getInstance(modal)?.hide();
        });
    }
});
</script>

<?php include '../controller/footer.php'; ?>