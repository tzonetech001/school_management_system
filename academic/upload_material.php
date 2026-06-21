<?php
// upload_material.php - Upload learning materials for a subject
session_start();
require_once '../controller/db_connect.php';

// Check login
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

$admin_id = intval($_SESSION['admin_id']);
$current_year = date('Y');

// Get subject and form from URL
$subject = isset($_GET['subject']) ? trim($_GET['subject']) : '';
$form_level = isset($_GET['form']) ? trim($_GET['form']) : '';

if (empty($subject) || empty($form_level)) {
    header("Location: subject_entry.php?error=missing_params");
    exit();
}

// Verify that the teacher is assigned to this subject and form with permission
$check_sql = "SELECT id FROM subject_teacher_assignments 
              WHERE teacher_id = ? AND subject = ? AND form_level = ? 
              AND academic_year = ? AND can_enter_results = 1";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "issi", $admin_id, $subject, $form_level, $current_year);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
if (mysqli_num_rows($check_result) == 0) {
    // Not authorized
    header("Location: subject_entry.php?error=unauthorized");
    exit();
}
mysqli_stmt_close($check_stmt);

// Initialize upload_debug array
$upload_debug = [];

// Load teacher name for display
$teacher_sql = "SELECT CONCAT(first_name, ' ', last_name) as teacher_name FROM admins WHERE id = $admin_id";
$teacher_result = mysqli_query($conn, $teacher_sql);
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_name = $teacher['teacher_name'] ?? 'Teacher';

// Subject display name
$subject_display = [
    'ac' => 'AC (Accountancy)',
    'htm' => 'HTM (Hospitality)',
    'his' => 'HIST (History)',
    'geo' => 'GEO (Geography)',
    'kisw' => 'KISW (Kiswahili)',
    'eng' => 'ENG (English)',
    'b_math' => 'B/MATH (Basic Mathematics)',
    'adv_m' => 'ADV/M (Advanced Mathematics)',
    'eco' => 'ECO (Economics)',
    'fren' => 'FREN (French)'
];
$subject_name = $subject_display[$subject] ?? strtoupper($subject);

// Handle file upload
$upload_message = '';
$upload_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['material_file'])) {
    $file = $_FILES['material_file'];
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // File validation
    $allowed_types = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'video/mp4',
        'video/quicktime',
        'image/jpeg',
        'image/png',
        'image/gif'
    ];
    $max_size = 50 * 1024 * 1024; // 50MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_status = 'error';
        $upload_message = 'Upload failed with error code: ' . $file['error'];
    } elseif (!in_array($file['type'], $allowed_types)) {
        $upload_status = 'error';
        $upload_message = 'File type not allowed. Please upload PDF, Word, PowerPoint, MP4, or common image formats.';
    } elseif ($file['size'] > $max_size) {
        $upload_status = 'error';
        $upload_message = 'File too large. Maximum size is 50MB.';
    } else {
        // Generate a unique filename to avoid collisions
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
        $upload_dir = __DIR__ . '/../uploads/materials/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Insert into database
            $insert_sql = "INSERT INTO learning_materials 
                          (subject, form_level, file_name, file_path, file_type, uploaded_by, description) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            $file_path = 'uploads/materials/' . $new_filename; // relative path for web access
            $file_type = $file['type'];
            $file_name = $file['name'];
            mysqli_stmt_bind_param($insert_stmt, "sssssis", 
                $subject, $form_level, $file_name, $file_path, $file_type, $admin_id, $description);
            if (mysqli_stmt_execute($insert_stmt)) {
                $upload_status = 'success';
                $upload_message = 'Material uploaded successfully!';
            } else {
                $upload_status = 'error';
                $upload_message = 'Database error: ' . mysqli_error($conn);
                // Delete the file if DB insert fails
                unlink($destination);
            }
            mysqli_stmt_close($insert_stmt);
        } else {
            $upload_status = 'error';
            $upload_message = 'Failed to move uploaded file. Check directory permissions.';
        }
    }
}

// Fetch existing materials for this subject/form
$materials = [];
$debug_info = [];

// First, check what's in the database for this teacher
$debug_sql = "SELECT subject, form_level, COUNT(*) as count, GROUP_CONCAT(file_name SEPARATOR ' | ') as files
              FROM learning_materials 
              WHERE uploaded_by = ?
              GROUP BY subject, form_level";
$debug_stmt = mysqli_prepare($conn, $debug_sql);
mysqli_stmt_bind_param($debug_stmt, "i", $admin_id);
mysqli_stmt_execute($debug_stmt);
$debug_result = mysqli_stmt_get_result($debug_stmt);
while ($row = mysqli_fetch_assoc($debug_result)) {
    $debug_info[] = $row;
}
mysqli_stmt_close($debug_stmt);

// Now fetch materials for current subject/form
$select_sql = "SELECT id, file_name, file_path, file_type, uploaded_at, description, uploaded_by 
               FROM learning_materials 
               WHERE subject = ? AND form_level = ? 
               ORDER BY uploaded_at DESC";
$select_stmt = mysqli_prepare($conn, $select_sql);
mysqli_stmt_bind_param($select_stmt, "ss", $subject, $form_level);
mysqli_stmt_execute($select_stmt);
$select_result = mysqli_stmt_get_result($select_stmt);
while ($row = mysqli_fetch_assoc($select_result)) {
    $materials[] = $row;
}
mysqli_stmt_close($select_stmt);

// Load theme settings and preferences (similar to subject_entry.php)
// ... (copy the same theme loading code from subject_entry.php) 
// For brevity, we'll include a minimal version here.
$theme_settings = [];
$settings_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
$settings_result = mysqli_query($conn, $settings_query);
if ($settings_result && mysqli_num_rows($settings_result) > 0) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $theme_settings[$row['setting_key']] = $row['setting_value'];
    }
}
$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id";
$prefs_result = mysqli_query($conn, $prefs_query);
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
if (!empty($theme_settings)) {
    foreach ($theme_settings as $key => $value) {
        if (array_key_exists($key, $colors)) {
            $colors[$key] = $value;
        }
    }
}
$bg_option = isset($preferences['background_option']) ? $preferences['background_option'] : 'image';
$bg_opacity = isset($preferences['background_opacity']) ? $preferences['background_opacity'] / 100 : 0.65;
if ($bg_option === 'image') {
    $bg_style = "linear-gradient(rgba(255,255,255,{$bg_opacity}), rgba(255,255,255,{$bg_opacity})), url('../muyovozi.png') no-repeat center center fixed";
    $bg_size = 'cover';
} else {
    $bg_colors = ['gray'=>'#e9ecef','eye_care'=>'#c7e9c0','milk'=>'#fdf5e6','dark_light'=>'#2d2d2d'];
    $bg_color = isset($bg_colors[$bg_option]) ? $bg_colors[$bg_option] : '#e9ecef';
    $bg_style = $bg_color;
    $bg_size = 'auto';
}
$font_sizes = ['10'=>'10px','12'=>'12px','14'=>'14px','16'=>'16px','18'=>'18px'];
$font_size = '16px';
if (isset($preferences['font_size']) && isset($font_sizes[$preferences['font_size']])) {
    $font_size = $font_sizes[$preferences['font_size']];
}
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
    <title>Upload Learning Material - Muyovozi High School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --font-size-base: <?php echo $font_size; ?>;
            --spacing-base: <?php echo $compact_mode ? '0.75rem' : '1rem'; ?>;
            --animation-speed: <?php echo $animation_time; ?>;
        }
        * { transition: <?php echo $animations ? 'all var(--animation-speed) ease' : 'none'; ?>; }
        body {
            background: <?php echo $bg_style; ?>;
            background-size: <?php echo $bg_size; ?>;
            background-position: center;
            min-height: 100vh;
            padding-top: 60px;
            color: var(--text-color);
            font-size: var(--font-size-base);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-content {
            margin-left: 260px;
            padding: 20px;
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
        }
        <?php if ($compact_mode): ?>
        .card-body { padding: 0.75rem !important; }
        .btn { padding: 0.5rem 1rem !important; }
        <?php endif; ?>
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .page-header h3 { margin: 0; font-weight: 600; }
        .page-header small { opacity: 0.8; }
        .upload-card {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 30px;
        }
        .upload-card .card-title {
            font-weight: 600;
            color: var(--text-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }
        .file-drop-zone {
            border: 2px dashed var(--border-color);
            border-radius: 16px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--light-color);
        }
        .file-drop-zone:hover {
            border-color: var(--primary-color);
            background: rgba(59,157,179,0.05);
        }
        .file-drop-zone i { font-size: 3rem; color: var(--primary-color); }
        .table-container {
            background: var(--white);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow-x: auto;
        }
        .table th { background: var(--primary-color); color: white; }
        .btn-delete { color: var(--danger-color); border: none; background: none; }
        .btn-delete:hover { color: #a71d2a; }
        .file-icon { font-size: 1.8rem; margin-right: 10px; }
        .badge-file-type { font-size: 0.7rem; padding: 4px 8px; }
        .back-link { color: var(--primary-color); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3><i class="fas fa-cloud-upload-alt me-2"></i> Upload Learning Material</h3>
                        <p class="mb-0">
                            <i class="fas fa-book me-1"></i> <?php echo htmlspecialchars($subject_name, ENT_QUOTES, 'UTF-8'); ?> 
                            | <i class="fas fa-users me-1"></i> <?php echo htmlspecialchars($form_level, ENT_QUOTES, 'UTF-8'); ?>
                            | <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($teacher_name, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>
                    <div>
                        <a href="subject_entry.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Subjects
                        </a>
                    </div>
                </div>
            </div>

            <!-- Upload Form -->
            <div class="upload-card">
                <h5 class="card-title"><i class="fas fa-upload me-2"></i> Upload New Material</h5>
                <form action="" method="post" enctype="multipart/form-data" id="uploadForm">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="material_file" class="form-label">Select File:</label>
                                <input type="file" name="material_file" id="material_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.mov,.jpg,.jpeg,.png,.gif" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="description" class="form-label">Description (optional)</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Brief description of the material"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100" id="uploadBtn">
                                <i class="fas fa-cloud-upload-alt me-1"></i> Upload
                            </button>
                        </div>
                    </div>
                </form>
                <div id="uploadStatus" class="mt-3"></div>
            </div>

            <!-- Existing Materials Table -->
            <div class="table-container">
                <h5 class="card-title"><i class="fas fa-list me-2"></i> Uploaded Materials</h5>
                <?php if (count($materials) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>File Name</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Uploaded</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materials as $index => $mat): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($mat['file_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                                                <i class="fas <?php 
                                                    $ext = pathinfo($mat['file_name'], PATHINFO_EXTENSION);
                                                    if (in_array($ext, ['pdf'])) echo 'fa-file-pdf text-danger';
                                                    elseif (in_array($ext, ['doc','docx'])) echo 'fa-file-word text-primary';
                                                    elseif (in_array($ext, ['ppt','pptx'])) echo 'fa-file-powerpoint text-warning';
                                                    elseif (in_array($ext, ['mp4','mov'])) echo 'fa-file-video text-info';
                                                    elseif (in_array($ext, ['jpg','jpeg','png','gif'])) echo 'fa-file-image text-success';
                                                    else echo 'fa-file';
                                                ?> me-1"></i>
                                                <?php echo htmlspecialchars($mat['file_name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </td>
                                        <td><span class="badge bg-secondary badge-file-type"><?php echo htmlspecialchars($mat['file_type'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php echo htmlspecialchars($mat['description'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo date('d M Y H:i', strtotime($mat['uploaded_at'])); ?></td>
                                        <td>
                                            <!-- Delete button (only for own uploads) -->
                                            <?php if ($mat['uploaded_by'] == $admin_id): ?>
                                                <button class="btn-delete btn-sm" data-id="<?php echo $mat['id']; ?>" onclick="deleteMaterial(<?php echo $mat['id']; ?>)">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small"><i class="fas fa-lock"></i> locked</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4"><i class="fas fa-info-circle me-1"></i> No materials uploaded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Handle upload status via session or query param
        <?php if ($upload_status): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const statusDiv = document.getElementById('uploadStatus');
                if ('<?php echo $upload_status; ?>' === 'success') {
                    statusDiv.innerHTML = `<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo addslashes($upload_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`;
                } else if ('<?php echo $upload_status; ?>' === 'error') {
                    statusDiv.innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo addslashes($upload_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`;
                }
            });
        <?php endif; ?>

        // Delete function
        function deleteMaterial(id) {
            Swal.fire({
                title: 'Delete Material?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    // AJAX delete
                    $.ajax({
                        url: 'delete_material.php',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                let msg = response.message;
                                if (response.debug) {
                                    msg += '\n\n' + response.debug;
                                }
                                Swal.fire('Deleted!', msg, 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                let errorMsg = response.message;
                                if (response.debug) {
                                    errorMsg += '\n\n' + response.debug;
                                }
                                Swal.fire('Error!', errorMsg, 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            Swal.fire('Error!', 'Server error: ' + error, 'error');
                        }
                    });
                }
            });
        }
    </script>
    <?php include '../controller/footer.php'; ?>
</body>
</html>