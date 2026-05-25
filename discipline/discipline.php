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
    if ($role_id == 1 || $role_id == 2 || $role_id == 4) { 
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

// Handle POST actions (add, delete, bulk actions)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new discipline record
    if (isset($_POST['add_discipline_record'])) {
        $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
        $list_type = mysqli_real_escape_string($conn, $_POST['list_type']);
        $record_type = mysqli_real_escape_string($conn, $_POST['record_type']);
        $short_note = mysqli_real_escape_string($conn, $_POST['short_note']);
        $severity_level = mysqli_real_escape_string($conn, $_POST['severity_level']);
        $is_visible_to_student = isset($_POST['is_visible_to_student']) ? 1 : 0;
        $follow_up_required = isset($_POST['follow_up_required']) ? 1 : 0;
        $follow_up_due_date = !empty($_POST['follow_up_due_date']) ? $_POST['follow_up_due_date'] : NULL;
        
        // Validate student exists and is not deleted
        $check_student_sql = "SELECT id, is_leaver FROM students WHERE id = '$student_id' AND status = 1";
        $check_result = mysqli_query($conn, $check_student_sql);
        
        if (!$check_result || mysqli_num_rows($check_result) == 0) {
            $_SESSION['error'] = "Selected student not found or has been deleted.";
            header("Location: discipline.php");
            exit();
        }
        
        // Handle file upload
        $file_path = NULL;
        $file_type = NULL;
        $file_name = NULL;
        $file_size = NULL;
        
        if (isset($_FILES['discipline_file']) && $_FILES['discipline_file']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/discipline/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file = $_FILES['discipline_file'];
            $max_size = 1 * 1024 * 1024; // 1MB
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                            'video/mp4', 'audio/mpeg', 'audio/mp3', 'audio/wav'];
            
            if ($file['size'] > $max_size) {
                $_SESSION['error'] = "File size must be less than 1MB.";
                header("Location: discipline.php");
                exit();
            }
            
            if (!in_array($file['type'], $allowed_types)) {
                $_SESSION['error'] = "File type not allowed. Allowed types: JPG, PNG, GIF, PDF, MP4, MP3, WAV.";
                header("Location: discipline.php");
                exit();
            }
            
            // Determine file type category
            $mime_type = $file['type'];
            if (strpos($mime_type, 'image/') === 0) {
                $file_type = 'image';
            } elseif (strpos($mime_type, 'video/') === 0) {
                $file_type = 'video';
            } elseif (strpos($mime_type, 'audio/') === 0) {
                $file_type = 'audio';
            } elseif ($mime_type == 'application/pdf') {
                $file_type = 'document';
            } else {
                $file_type = 'other';
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $unique_filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                $file_path = $target_file;
                $file_name = $file['name'];
                $file_size = $file['size'];
            }
        }
        
        // Insert record
        $sql = "INSERT INTO discipline_records 
                (student_id, list_type, record_type, short_note, file_path, file_type, 
                file_name, file_size, recorded_by, is_visible_to_student, severity_level,
                follow_up_required, follow_up_due_date) 
                VALUES ('$student_id', '$list_type', '$record_type', '$short_note', 
                " . ($file_path ? "'$file_path'" : "NULL") . ", 
                " . ($file_type ? "'$file_type'" : "NULL") . ", 
                " . ($file_name ? "'$file_name'" : "NULL") . ", 
                " . ($file_size ? "$file_size" : "NULL") . ", 
                '$admin_id', '$is_visible_to_student', '$severity_level',
                '$follow_up_required', " . ($follow_up_due_date ? "'$follow_up_due_date'" : "NULL") . ")";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Discipline record added successfully!";
        } else {
            $_SESSION['error'] = "Error adding record: " . mysqli_error($conn);
        }
        
        header("Location: discipline.php");
        exit();
    }
    
    // Delete single record
    if (isset($_POST['delete_record'])) {
        $record_id = mysqli_real_escape_string($conn, $_POST['record_id']);
        
        // Check if record exists
        $check_sql = "SELECT dr.*, s.is_leaver 
                     FROM discipline_records dr
                     JOIN students s ON dr.student_id = s.id
                     WHERE dr.id = $record_id";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (!$check_result || mysqli_num_rows($check_result) == 0) {
            $_SESSION['error'] = "Record not found or has been deleted.";
            header("Location: discipline.php");
            exit();
        }
        
        $record = mysqli_fetch_assoc($check_result);
        
        // Only allow deletion if student is not deleted
        if ($record['is_leaver'] == 0) {
            // Get file path before deleting
            $file_sql = "SELECT file_path FROM discipline_records WHERE id = $record_id";
            $file_result = mysqli_query($conn, $file_sql);
            if ($file_row = mysqli_fetch_assoc($file_result)) {
                if ($file_row['file_path'] && file_exists($file_row['file_path'])) {
                    unlink($file_row['file_path']);
                }
            }
            
            // Delete record
            $delete_sql = "DELETE FROM discipline_records WHERE id = $record_id";
            if (mysqli_query($conn, $delete_sql)) {
                $_SESSION['success'] = "Record deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting record: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error'] = "Cannot delete record for a student who has left the school.";
        }
        
        header("Location: discipline.php");
        exit();
    }
    
    // Bulk delete
    if (isset($_POST['bulk_delete']) && isset($_POST['selected_records'])) {
        $selected_ids = implode(',', array_map('intval', $_POST['selected_records']));
        
        // Check which records belong to active students
        $check_sql = "SELECT dr.id 
                     FROM discipline_records dr
                     JOIN students s ON dr.student_id = s.id
                     WHERE dr.id IN ($selected_ids) AND s.is_leaver = 0";
        $check_result = mysqli_query($conn, $check_sql);
        
        $valid_ids = [];
        while ($row = mysqli_fetch_assoc($check_result)) {
            $valid_ids[] = $row['id'];
        }
        
        if (empty($valid_ids)) {
            $_SESSION['error'] = "No valid records selected for deletion.";
            header("Location: discipline.php");
            exit();
        }
        
        $valid_ids_str = implode(',', $valid_ids);
        
        // Get file paths before deleting
        $files_sql = "SELECT file_path FROM discipline_records WHERE id IN ($valid_ids_str)";
        $files_result = mysqli_query($conn, $files_sql);
        while ($file_row = mysqli_fetch_assoc($files_result)) {
            if ($file_row['file_path'] && file_exists($file_row['file_path'])) {
                unlink($file_row['file_path']);
            }
        }
        
        // Delete records
        $bulk_delete_sql = "DELETE FROM discipline_records WHERE id IN ($valid_ids_str)";
        if (mysqli_query($conn, $bulk_delete_sql)) {
            $_SESSION['success'] = count($valid_ids) . " records deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting records: " . mysqli_error($conn);
        }
        
        header("Location: discipline.php");
        exit();
    }
    
    // Update follow-up status
    if (isset($_POST['update_followup'])) {
        $record_id = mysqli_real_escape_string($conn, $_POST['record_id']);
        $follow_up_completed = isset($_POST['follow_up_completed']) ? 1 : 0;
        $follow_up_notes = mysqli_real_escape_string($conn, $_POST['follow_up_notes']);
        
        $update_sql = "UPDATE discipline_records SET 
                      follow_up_completed = '$follow_up_completed',
                      follow_up_notes = '$follow_up_notes',
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = $record_id";
        
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['success'] = "Follow-up updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating follow-up: " . mysqli_error($conn);
        }
        
        header("Location: discipline.php");
        exit();
    }
}

// Handle GET actions (filter, search, etc.)
$filter_list_type = $_GET['list_type'] ?? 'all';
$filter_student = $_GET['student'] ?? '';
$filter_status = $_GET['status'] ?? 'active';
$filter_severity = $_GET['severity'] ?? 'all';
$show_leavers = isset($_GET['show_leavers']) ? true : false;

// Build WHERE conditions
$where_conditions = [];
if ($filter_list_type != 'all') {
    $where_conditions[] = "dr.list_type = '$filter_list_type'";
}
if ($filter_status != 'all') {
    $where_conditions[] = "dr.status = '$filter_status'";
}
if ($filter_severity != 'all') {
    $where_conditions[] = "dr.severity_level = '$filter_severity'";
}
if (!$show_leavers) {
    $where_conditions[] = "s.is_leaver = FALSE";
}
if (!empty($filter_student)) {
    $where_conditions[] = "(s.index_number LIKE '%$filter_student%' OR 
                          CONCAT(s.first_name, ' ', s.last_name) LIKE '%$filter_student%' OR
                          s.admission_number LIKE '%$filter_student%')";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get discipline records with student info - Updated to include student status
$sql = "SELECT dr.*, 
       s.index_number, 
       CONCAT(s.first_name, ' ', s.last_name) as student_name,
       s.sex, 
       s.combination, 
       s.class,
       s.is_leaver,
       s.graduation_status,
       s.status as student_status,
       CONCAT(a.first_name, ' ', a.last_name) as recorded_by_name,
       a.profile_image as recorded_by_image
       FROM discipline_records dr
       JOIN students s ON dr.student_id = s.id
       JOIN admins a ON dr.recorded_by = a.id
       $where_clause
       ORDER BY dr.created_at DESC, dr.severity_level DESC";

$result = mysqli_query($conn, $sql);
$discipline_records = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $discipline_records[] = $row;
    }
}

// Get statistics with error handling
$stats = [
    'total_records' => 0,
    'white_count' => 0,
    'black_count' => 0,
    'critical_count' => 0,
    'pending_followups' => 0,
    'affected_students' => 0
];

$stats_sql = "SELECT 
    COUNT(*) as total_records,
    SUM(CASE WHEN list_type = 'white' THEN 1 ELSE 0 END) as white_count,
    SUM(CASE WHEN list_type = 'black' THEN 1 ELSE 0 END) as black_count,
    SUM(CASE WHEN severity_level = 'critical' AND dr.status = 'active' THEN 1 ELSE 0 END) as critical_count,
    SUM(CASE WHEN follow_up_required = 1 AND follow_up_completed = 0 THEN 1 ELSE 0 END) as pending_followups,
    COUNT(DISTINCT student_id) as affected_students
    FROM discipline_records dr
    JOIN students s ON dr.student_id = s.id
    WHERE dr.status = 'active' AND s.status = 1";
    
$stats_result = mysqli_query($conn, $stats_sql);
if ($stats_result) {
    $stats = mysqli_fetch_assoc($stats_result);
    // Ensure all keys exist even if null
    $stats = array_merge([
        'total_records' => 0,
        'white_count' => 0,
        'black_count' => 0,
        'critical_count' => 0,
        'pending_followups' => 0,
        'affected_students' => 0
    ], $stats ?: []);
} else {
    // Log error for debugging
    error_log("Statistics query failed: " . mysqli_error($conn));
    $_SESSION['error'] = "Error loading statistics. Please contact administrator.";
}

// Function to get file icon
function getDisciplineFileIcon($file_type) {
    $icons = [
        'image' => 'fa-file-image',
        'video' => 'fa-file-video',
        'audio' => 'fa-file-audio',
        'document' => 'fa-file-pdf',
        'other' => 'fa-file'
    ];
    return $icons[$file_type] ?? 'fa-file';
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Function to get student status badge
function getStudentStatusBadge($is_leaver, $graduation_status, $student_status) {
    if ($student_status == 0) {
        return '<span class="badge bg-danger">Deleted</span>';
    }
    
    if ($is_leaver) {
        if ($graduation_status == 'Graduated') {
            return '<span class="badge bg-success">Graduated</span>';
        } else {
            return '<span class="badge bg-warning">Leaver</span>';
        }
    }
    
    return '<span class="badge bg-info">Active</span>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discipline Management - Muyovozi High School</title>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>
    <!-- SweetAlert2 for confirmation dialogs -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .center-message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1050;
            min-width: 300px;
        }
        
        .student-selection-card {
            border: 2px solid #3B9DB3;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #f8fdff, #ffffff);
        }
        
        .student-info-display {
            background: #e8f4f8;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            border-left: 4px solid #3B9DB3;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-header-bg {
            background: linear-gradient(135deg, #3B9DB3, #2d7c8f);
            color: white;
        }
        
        .severity-cell {
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            text-align: center;
            min-width: 80px;
        }
        
        .severity-low { 
            background: linear-gradient(135deg, #e8f4f8, #d1e7f0);
            color: #2d7c8f;
            border: 1px solid #b8d9e6;
        }
        
        .severity-medium { 
            background: linear-gradient(135deg, #f8f4e8, #f0e9d1);
            color: #b38f00;
            border: 1px solid #e6dbb8;
        }
        
        .severity-high { 
            background: linear-gradient(135deg, #f8e8e8, #f0d1d1);
            color: #dc3545;
            border: 1px solid #e6b8b8;
        }
        
        .severity-critical { 
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: 1px solid #dc3545;
        }
        
        .list-type-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .white-list-badge {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }
        
        .black-list-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        
        .record-type-cell {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
        }
        
        .record-warning { background: #fff3cd; color: #856404; }
        .record-appreciation { background: #d1ecf1; color: #0c5460; }
        .record-suspension { background: #f8d7da; color: #721c24; }
        .record-reprimand { background: #f8d7da; color: #721c24; }
        .record-commendation { background: #d4edda; color: #155724; }
        .record-expulsion { background: #dc3545; color: white; }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .file-preview-btn {
            cursor: pointer;
            color: #3B9DB3;
            transition: all 0.3s;
        }
        
        .file-preview-btn:hover {
            color: #2d7c8f;
            transform: scale(1.1);
        }
        
        .follow-up-indicator {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .student-search-results-table {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-top: 10px;
            display: none;
        }
        
        .student-row-select {
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .student-row-select:hover {
            background: #f8f9fa;
        }
        
        .student-row-select.selected {
            background: #e8f4f8;
            border-left: 3px solid #3B9DB3;
        }
        
        .selected-student-summary {
            background: linear-gradient(135deg, #f0fff4, #ffffff);
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            display: none;
        }
        
        .modal-xl-custom {
            max-width: 900px;
        }
        
        .preview-container {
            text-align: center;
            padding: 20px;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .preview-video {
            max-width: 100%;
            max-height: 500px;
            border-radius: 8px;
        }
        
        .preview-audio {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .severity-cell, .record-type-cell {
                font-size: 0.7rem;
                padding: 3px 6px;
            }
        }
    </style>
</head>
<body>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="page-title">
                    <i class="fas fa-gavel me-2"></i>Discipline Management
                </h2>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                        <i class="fas fa-plus me-2"></i>Add New Record
                    </button>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="center-message">
                    <div class="alert alert-success alert-dismissible fade show shadow-lg" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle fa-2x me-3"></i>
                            <div>
                                <h5 class="mb-1">Success!</h5>
                                <p class="mb-0"><?php echo $_SESSION['success']; ?></p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="center-message">
                    <div class="alert alert-danger alert-dismissible fade show shadow-lg" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                            <div>
                                <h5 class="mb-1">Error!</h5>
                                <p class="mb-0"><?php echo $_SESSION['error']; ?></p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-thumbs-up fa-3x mb-3" style="color: #28a745;"></i>
                            <h2 class="mb-0"><?php echo $stats['white_count'] ?? 0; ?></h2>
                            <p class="text-muted mb-0">White List Records</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3" style="color: #dc3545;"></i>
                            <h2 class="mb-0"><?php echo $stats['black_count'] ?? 0; ?></h2>
                            <p class="text-muted mb-0">Black List Records</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-circle fa-3x mb-3" style="color: #ffc107;"></i>
                            <h2 class="mb-0"><?php echo $stats['critical_count'] ?? 0; ?></h2>
                            <p class="text-muted mb-0">Critical Issues</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-3x mb-3" style="color: #fd7e14;"></i>
                            <h2 class="mb-0"><?php echo $stats['pending_followups'] ?? 0; ?></h2>
                            <p class="text-muted mb-0">Pending Follow-ups</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">List Type</label>
                            <select name="list_type" class="form-select">
                                <option value="all" <?php echo $filter_list_type == 'all' ? 'selected' : ''; ?>>All Lists</option>
                                <option value="white" <?php echo $filter_list_type == 'white' ? 'selected' : ''; ?>>White List</option>
                                <option value="black" <?php echo $filter_list_type == 'black' ? 'selected' : ''; ?>>Black List</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Student Search</label>
                            <input type="text" name="student" class="form-control" 
                                   placeholder="Index or Name" value="<?php echo htmlspecialchars($filter_student); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Severity</label>
                            <select name="severity" class="form-select">
                                <option value="all" <?php echo $filter_severity == 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="low" <?php echo $filter_severity == 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $filter_severity == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $filter_severity == 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="critical" <?php echo $filter_severity == 'critical' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="resolved" <?php echo $filter_status == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="archived" <?php echo $filter_status == 'archived' ? 'selected' : ''; ?>>Archived</option>
                                <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="show_leavers" 
                                       id="showLeavers" <?php echo $show_leavers ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showLeavers">
                                    Include Leavers
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                            <a href="discipline.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Discipline Records Table -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-header-bg">
                            <tr>
                                <th width="5%">#</th>
                                <th width="20%">Student Information</th>
                                <th width="10%">List Type</th>
                                <th width="10%">Record Type</th>
                                <th width="10%">Severity</th>
                                <th width="20%">Short Note</th>
                                <th width="15%">Follow-up Status</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($discipline_records)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fas fa-gavel fa-3x text-muted mb-3"></i>
                                        <h4>No discipline records found</h4>
                                        <p class="text-muted mb-4">Add your first discipline record using the button above.</p>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                                            <i class="fas fa-plus me-2"></i>Add New Record
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($discipline_records as $index => $record): ?>
                                <tr>
                                    <td class="align-middle"><?php echo $index + 1; ?></td>
                                    <td class="align-middle">
                                        <div class="fw-bold"><?php echo htmlspecialchars($record['student_name']); ?></div>
                                        <div class="small text-muted">
                                            <div><i class="fas fa-hashtag me-1"></i><?php echo htmlspecialchars($record['index_number']); ?></div>
                                            <div><i class="fas fa-graduation-cap me-1"></i><?php echo $record['combination']; ?> | <?php echo $record['class']; ?></div>
                                            <div><?php echo getStudentStatusBadge($record['is_leaver'], $record['graduation_status'], $record['student_status']); ?></div>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <span class="list-type-badge <?php echo $record['list_type']; ?>-list-badge">
                                            <?php echo ucfirst($record['list_type']); ?>
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <span class="record-type-cell record-<?php echo $record['record_type']; ?>">
                                            <?php echo ucfirst($record['record_type']); ?>
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <span class="severity-cell severity-<?php echo $record['severity_level']; ?>">
                                            <?php echo ucfirst($record['severity_level']); ?>
                                        </span>
                                    </td>
                                <td class="align-middle">
    <div class="small">
        <?php echo nl2br(htmlspecialchars(substr($record['short_note'], 0, 100))); ?>
        <?php if (strlen($record['short_note']) > 100): ?>...<?php endif; ?>
        <?php if ($record['file_path'] && file_exists($record['file_path'])): ?>
            <div class="mt-2">
                <?php 
                $filename = basename($record['file_path']);
                $file_url = '../uploads/discipline/' . $filename;
                ?>
                
                <?php if ($record['file_type'] === 'image'): ?>
                    <img src="<?php echo $file_url; ?>" 
                         class="rounded" 
                         style="width: 80px; height: 80px; object-fit: cover; cursor: pointer;"
                         onclick="previewFile('<?php echo $file_url; ?>', 'image')"
                         alt="Image"
                         title="Click to view">
                <?php else: ?>
                    <a href="<?php echo $file_url; ?>" 
                       target="_blank"
                       class="text-decoration-none d-block"
                       title="View file">
                        <i class="fas <?php echo getDisciplineFileIcon($record['file_type']); ?> fa-2x 
                            <?php echo $record['file_type'] === 'document' ? 'text-danger' : 
                                    ($record['file_type'] === 'video' ? 'text-primary' : 
                                    ($record['file_type'] === 'audio' ? 'text-warning' : 'text-secondary')); ?>">
                        </i>
                        <div class="small mt-1"><?php echo htmlspecialchars($record['file_name']); ?></div>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</td>
                                    <td class="align-middle">
                                        <?php if ($record['follow_up_required']): ?>
                                            <?php if ($record['follow_up_completed']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>Completed
                                                </span>
                                                <?php if ($record['follow_up_notes']): ?>
                                                    <div class="small text-muted mt-1" title="<?php echo htmlspecialchars($record['follow_up_notes']); ?>">
                                                        <?php echo substr($record['follow_up_notes'], 0, 50); ?>...
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-warning follow-up-indicator" 
                                                      onclick="showFollowUpModal(<?php echo $record['id']; ?>)"
                                                      style="cursor: pointer;">
                                                    <i class="fas fa-clock me-1"></i>Pending
                                                </span>
                                                <?php if ($record['follow_up_due_date']): ?>
                                                    <div class="small text-muted mt-1">
                                                        Due: <?php echo date('M d, Y', strtotime($record['follow_up_due_date'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No Follow-up</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle">
                                        <div class="action-buttons">
                                            <?php if (!$record['follow_up_completed'] && $record['follow_up_required']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning"
                                                        onclick="showFollowUpModal(<?php echo $record['id']; ?>)"
                                                        title="Update Follow-up">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($record['student_status'] == 1 && $record['is_leaver'] == 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-record-btn"
                                                        data-id="<?php echo $record['id']; ?>"
                                                        data-student="<?php echo htmlspecialchars($record['student_name']); ?>"
                                                        title="Delete Record">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                                        title="Cannot delete - Student has left">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            By: <?php echo htmlspecialchars($record['recorded_by_name']); ?><br>
                                            <?php echo date('M d, Y', strtotime($record['created_at'])); ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if (count($discipline_records) > 20): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Discipline records pagination">
                        <ul class="pagination">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1">Previous</a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Record Modal -->
    <div class="modal fade" id="addRecordModal" tabindex="-1" aria-labelledby="addRecordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl-custom">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" id="addRecordForm">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addRecordModalLabel">
                            <i class="fas fa-plus-circle me-2"></i>Add New Discipline Record
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Student Selection Section -->
                        <div class="student-selection-card">
                            <h5 class="mb-3"><i class="fas fa-user-graduate me-2"></i>Step 1: Select Student</h5>
                            <div class="input-group mb-3">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" id="studentSearchInput" 
                                       placeholder="Type to search for students by name, index number, or admission number..." 
                                       autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <!-- Search Results Table -->
                            <div class="student-search-results-table" id="studentSearchResults">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="5%">Select</th>
                                            <th width="20%">Name</th>
                                            <th width="15%">Index Number</th>
                                            <th width="15%">Class</th>
                                            <th width="15%">Combination</th>
                                            <th width="15%">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="studentResultsBody">
                                        <!-- Results will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Selected Student Display -->
                            <div class="selected-student-summary" id="selectedStudentSummary">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Selected Student</h6>
                                        <div class="d-flex flex-wrap gap-3">
                                            <div>
                                                <strong>Name:</strong> 
                                                <span id="selectedStudentName">-</span>
                                            </div>
                                            <div>
                                                <strong>Index:</strong> 
                                                <span id="selectedStudentIndex">-</span>
                                            </div>
                                            <div>
                                                <strong>Class:</strong> 
                                                <span id="selectedStudentClass">-</span>
                                            </div>
                                            <div>
                                                <strong>Combination:</strong> 
                                                <span id="selectedStudentCombination">-</span>
                                            </div>
                                            <div>
                                                <strong>Status:</strong> 
                                                <span id="selectedStudentStatus">-</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearStudentSelection()">
                                        <i class="fas fa-times me-1"></i>Change
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="student_id" id="selectedStudentId" required>
                        </div>
                        
                        <!-- Record Details Section -->
                        <div class="mt-4">
                            <h5 class="mb-3"><i class="fas fa-edit me-2"></i>Step 2: Record Details</h5>
                            <div class="row g-3">
                                <!-- List Type -->
                                <div class="col-md-6">
                                    <label class="form-label">List Type *</label>
                                    <select name="list_type" class="form-select" required onchange="updateRecordTypeOptions(this.value)">
                                        <option value="">Select List Type</option>
                                        <option value="white">White List (Good Behavior)</option>
                                        <option value="black">Black List (Disciplinary Issues)</option>
                                    </select>
                                </div>
                                
                                <!-- Record Type -->
                                <div class="col-md-6">
                                    <label class="form-label">Record Type *</label>
                                    <select name="record_type" class="form-select" id="recordTypeSelect" required>
                                        <option value="">Select Record Type</option>
                                        <!-- Options will be populated by JavaScript -->
                                    </select>
                                </div>
                                
                                <!-- Severity Level -->
                                <div class="col-md-6">
                                    <label class="form-label">Severity Level *</label>
                                    <select name="severity_level" class="form-select" required>
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                        <option value="high">High</option>
                                        <option value="critical">Critical</option>
                                    </select>
                                </div>
                                
                                <!-- Visibility -->
                                <div class="col-md-6">
                                    <label class="form-label">Visibility to Student</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="is_visible_to_student" id="isVisibleToStudent" checked>
                                        <label class="form-check-label" for="isVisibleToStudent">
                                            Student can see this record
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Short Note -->
                                <div class="col-12">
                                    <label class="form-label">Short Note *</label>
                                    <textarea name="short_note" class="form-control" rows="4" 
                                              placeholder="Enter detailed description of the incident or commendation..." required></textarea>
                                    <div class="form-text">Maximum 1000 characters</div>
                                </div>
                                
                                <!-- File Upload -->
                                <div class="col-12">
                                    <label class="form-label">Upload File (Optional)</label>
                                    <div class="alert alert-info py-2">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Maximum file size: 1MB. Allowed types: Images, PDF, MP4, MP3, WAV
                                    </div>
                                    <input type="file" name="discipline_file" class="form-control" accept="image/*,.pdf,video/*,audio/*">
                                    <div id="fileSizeWarning" class="text-danger small mt-1" style="display: none;">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        File size exceeds 1MB limit
                                    </div>
                                </div>
                                
                                <!-- Follow-up -->
                                <div class="col-12">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="follow_up_required" id="followUpRequired">
                                        <label class="form-check-label fw-bold" for="followUpRequired">
                                            <i class="fas fa-clock me-1"></i>Require Follow-up
                                        </label>
                                    </div>
                                    <div id="followUpFields" style="display: none;">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Follow-up Due Date</label>
                                                <input type="date" name="follow_up_due_date" class="form-control" 
                                                       min="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_discipline_record" class="btn btn-primary" id="submitRecordBtn" disabled>
                            <i class="fas fa-save me-2"></i>Save Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Follow-up Modal -->
    <div class="modal fade" id="followUpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="record_id" id="followUpRecordId">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="fas fa-clock me-2"></i>Update Follow-up
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="follow_up_completed" id="followUpCompleted">
                                <label class="form-check-label fw-bold" for="followUpCompleted">
                                    Mark as Completed
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Follow-up Notes</label>
                            <textarea name="follow_up_notes" class="form-control" rows="4" 
                                      placeholder="Enter follow-up details..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_followup" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Update Follow-up
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- File Preview Modal -->
    <div class="modal fade" id="filePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">File Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="preview-container" id="filePreviewContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Record type options based on list type
        const recordTypeOptions = {
            white: [
                {value: 'appreciation', text: 'Appreciation'},
                {value: 'commendation', text: 'Commendation'}
            ],
            black: [
                {value: 'warning', text: 'Warning'},
                {value: 'reprimand', text: 'Reprimand'},
                {value: 'suspension', text: 'Suspension'},
                {value: 'expulsion', text: 'Expulsion'}
            ]
        };
        
        function updateRecordTypeOptions(listType) {
            const select = document.getElementById('recordTypeSelect');
            select.innerHTML = '<option value="">Select Record Type</option>';
            
            if (listType && recordTypeOptions[listType]) {
                recordTypeOptions[listType].forEach(option => {
                    const opt = document.createElement('option');
                    opt.value = option.value;
                    opt.textContent = option.text;
                    select.appendChild(opt);
                });
            }
        }
        
        // Student search functionality
        let searchTimeout;
        document.getElementById('studentSearchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                document.getElementById('studentSearchResults').style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(`search_students.php?q=${encodeURIComponent(query)}&include_leavers=1`)
                    .then(response => response.json())
                    .then(data => {
                        const tbody = document.getElementById('studentResultsBody');
                        tbody.innerHTML = '';
                        
                        if (data.length === 0) {
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="6" class="text-center py-3 text-muted">
                                        No students found
                                    </td>
                                </tr>
                            `;
                        } else {
                            data.forEach(student => {
                                const tr = document.createElement('tr');
                                tr.className = 'student-row-select';
                                tr.innerHTML = `
                                    <td class="text-center">
                                        <input type="radio" name="student_radio" value="${student.id}" 
                                               onchange="selectStudentFromTable(${student.id}, '${student.name.replace(/'/g, "\\'")}', 
                                               '${student.index_number}', '${student.class}', '${student.combination}', ${student.is_leaver})">
                                    </td>
                                    <td class="fw-bold">${student.name}</td>
                                    <td>${student.index_number}</td>
                                    <td>${student.class}</td>
                                    <td>${student.combination}</td>
                                    <td>
                                        ${student.is_leaver ? 
                                            '<span class="badge bg-warning">Leaver</span>' : 
                                            '<span class="badge bg-success">Active</span>'
                                        }
                                    </td>
                                `;
                                tbody.appendChild(tr);
                            });
                        }
                        document.getElementById('studentSearchResults').style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        document.getElementById('studentSearchResults').style.display = 'none';
                    });
            }, 300);
        });
        
        // Clear search
        document.getElementById('clearSearch').addEventListener('click', function() {
            document.getElementById('studentSearchInput').value = '';
            document.getElementById('studentSearchResults').style.display = 'none';
        });
        
        // Select student from table
        function selectStudentFromTable(studentId, studentName, indexNumber, studentClass, combination, isLeaver) {
            document.getElementById('selectedStudentId').value = studentId;
            document.getElementById('selectedStudentName').textContent = studentName;
            document.getElementById('selectedStudentIndex').textContent = indexNumber;
            document.getElementById('selectedStudentClass').textContent = studentClass;
            document.getElementById('selectedStudentCombination').textContent = combination;
            document.getElementById('selectedStudentStatus').textContent = isLeaver ? 'Leaver' : 'Active';
            document.getElementById('selectedStudentSummary').style.display = 'block';
            document.getElementById('studentSearchResults').style.display = 'none';
            document.getElementById('studentSearchInput').value = studentName;
            
            // Enable submit button
            document.getElementById('submitRecordBtn').disabled = false;
            
            // Highlight selected row
            document.querySelectorAll('.student-row-select').forEach(row => {
                row.classList.remove('selected');
            });
            event.target.closest('tr').classList.add('selected');
        }
        
        // Clear student selection
        function clearStudentSelection() {
            document.getElementById('selectedStudentId').value = '';
            document.getElementById('selectedStudentSummary').style.display = 'none';
            document.getElementById('studentSearchInput').value = '';
            document.getElementById('submitRecordBtn').disabled = true;
            
            // Clear radio buttons
            document.querySelectorAll('input[name="student_radio"]').forEach(radio => {
                radio.checked = false;
            });
            
            // Clear table selection
            document.querySelectorAll('.student-row-select').forEach(row => {
                row.classList.remove('selected');
            });
        }
        
        // File size validation
        document.querySelector('input[name="discipline_file"]').addEventListener('change', function() {
            const maxSize = 1 * 1024 * 1024; // 1MB
            const warningDiv = document.getElementById('fileSizeWarning');
            
            if (this.files[0] && this.files[0].size > maxSize) {
                warningDiv.style.display = 'block';
                this.value = ''; // Clear the file input
            } else {
                warningDiv.style.display = 'none';
            }
        });
        
        // Follow-up fields toggle
        document.getElementById('followUpRequired').addEventListener('change', function() {
            document.getElementById('followUpFields').style.display = this.checked ? 'block' : 'none';
        });
        
        // Single record delete
        document.addEventListener('click', function(e) {
            if (e.target.closest('.delete-record-btn')) {
                e.preventDefault();
                const btn = e.target.closest('.delete-record-btn');
                const recordId = btn.getAttribute('data-id');
                const studentName = btn.getAttribute('data-student');
                
                Swal.fire({
                    title: 'Delete Record?',
                    html: `Are you sure you want to delete the discipline record for <strong>${studentName}</strong>?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#d33',
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';
                        
                        const idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.name = 'record_id';
                        idInput.value = recordId;
                        form.appendChild(idInput);
                        
                        const submitInput = document.createElement('input');
                        submitInput.type = 'hidden';
                        submitInput.name = 'delete_record';
                        submitInput.value = '1';
                        form.appendChild(submitInput);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }
        });
        
        // Follow-up modal
        function showFollowUpModal(recordId) {
            document.getElementById('followUpRecordId').value = recordId;
            const modal = new bootstrap.Modal(document.getElementById('followUpModal'));
            modal.show();
        }
        
        // File preview
        function previewFile(fileUrl, fileType) {
            const contentDiv = document.getElementById('filePreviewContent');
            
            if (fileType === 'image') {
                contentDiv.innerHTML = `
                    <img src="${fileUrl}" class="preview-image">
                    <div class="mt-3">
                        <a href="${fileUrl}" download class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>Download Image
                        </a>
                    </div>
                `;
            } else if (fileType === 'video') {
                contentDiv.innerHTML = `
                    <video controls class="preview-video">
                        <source src="${fileUrl}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    <div class="mt-3">
                        <a href="${fileUrl}" download class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>Download Video
                        </a>
                    </div>
                `;
            } else if (fileType === 'audio') {
                contentDiv.innerHTML = `
                    <audio controls class="preview-audio">
                        <source src="${fileUrl}" type="audio/mpeg">
                        Your browser does not support the audio element.
                    </audio>
                    <div class="mt-3">
                        <a href="${fileUrl}" download class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>Download Audio
                        </a>
                    </div>
                `;
            } else if (fileType === 'document') {
                contentDiv.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-file-pdf fa-5x text-danger mb-3"></i>
                        <h5>PDF Document</h5>
                        <p>Preview not available for PDF files. Please download to view.</p>
                        <a href="${fileUrl}" target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt me-2"></i>Open in New Tab
                        </a>
                        <a href="${fileUrl}" download class="btn btn-success ms-2">
                            <i class="fas fa-download me-2"></i>Download PDF
                        </a>
                    </div>
                `;
            } else {
                contentDiv.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-file fa-5x text-muted mb-3"></i>
                        <h5>File Preview</h5>
                        <p>Preview not available for this file type.</p>
                        <a href="${fileUrl}" download class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>Download File
                        </a>
                    </div>
                `;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('filePreviewModal'));
            modal.show();
        }
        
        // Close student search when clicking outside
        document.addEventListener('click', function(e) {
            const searchContainer = document.getElementById('studentSearchResults');
            const searchInput = document.getElementById('studentSearchInput');
            
            if (!e.target.closest('#studentSearchResults') && 
                !e.target.closest('#studentSearchInput') && 
                !e.target.closest('#clearSearch')) {
                searchContainer.style.display = 'none';
            }
        });
        
        // Initialize record type options if list type is pre-selected
        document.addEventListener('DOMContentLoaded', function() {
            const listTypeSelect = document.querySelector('select[name="list_type"]');
            if (listTypeSelect.value) {
                updateRecordTypeOptions(listTypeSelect.value);
            }
            
            // Auto-hide messages after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.center-message');
                alerts.forEach(alert => {
                    alert.style.display = 'none';
                });
            }, 5000);
        });
        
        // Form validation
        document.getElementById('addRecordForm').addEventListener('submit', function(e) {
            const studentId = document.getElementById('selectedStudentId').value;
            const listType = document.querySelector('select[name="list_type"]').value;
            const recordType = document.getElementById('recordTypeSelect').value;
            const shortNote = document.querySelector('textarea[name="short_note"]').value;
            
            if (!studentId) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Student Required',
                    text: 'Please select a student first.'
                });
                return false;
            }
            
            if (!listType || !recordType || !shortNote.trim()) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Missing Information',
                    text: 'Please fill all required fields.'
                });
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>