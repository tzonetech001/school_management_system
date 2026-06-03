<?php
// super/schools.php - Schools Management with Logo Upload
session_start();

// Check if Super Admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header("Location: ../mhs/login.php");
    exit();
}

require_once '../controller/db_connect.php';

// Get Super Admin info
$super_id = $_SESSION['super_admin_id'];
$super_sql = "SELECT * FROM super_admins WHERE id = $super_id AND status = 1";
$super_result = mysqli_query($conn, $super_sql);
$super_admin = mysqli_fetch_assoc($super_result);

if (!$super_admin) {
    session_destroy();
    header("Location: ../mhs/login.php");
    exit();
}

// ==================== HELPER FUNCTION: Upload Logo ====================
function uploadSchoolLogo($file, $school_id, $school_code) {
    $upload_dir = '../uploads/school_logos/';
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Only JPG, PNG, GIF, and WEBP images are allowed!'];
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Image size must be less than 2MB!'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'school_' . $school_id . '_' . $school_code . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    $db_path = 'uploads/school_logos/' . $filename;
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $db_path];
    } else {
        return ['success' => false, 'message' => 'Failed to upload image'];
    }
}

// ==================== AJAX: Get School Details ====================
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_school' && isset($_GET['id'])) {
    $school_id = (int)$_GET['id'];
    $sql = "SELECT id, school_code, school_name, school_motto, address, phone, email, status, logo_path FROM schools WHERE id = $school_id";
    $result = mysqli_query($conn, $sql);
    if ($row = mysqli_fetch_assoc($result)) {
        header('Content-Type: application/json');
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'School not found']);
    }
    exit();
}

// ==================== ADD SCHOOL ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_school'])) {
    $school_code = mysqli_real_escape_string($conn, trim($_POST['school_code']));
    $school_name = mysqli_real_escape_string($conn, trim($_POST['school_name']));
    $school_motto = mysqli_real_escape_string($conn, trim($_POST['school_motto'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    
    $errors = [];
    if (empty($school_code)) $errors[] = "School code is required";
    if (empty($school_name)) $errors[] = "School name is required";
    
    // Check if school code already exists
    $check_sql = "SELECT id FROM schools WHERE school_code = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $school_code);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $errors[] = "School code already exists!";
    }
    $check_stmt->close();
    
    if (empty($errors)) {
        $insert_sql = "INSERT INTO schools (school_code, school_name, school_motto, address, phone, email, status) 
                       VALUES (?, ?, ?, ?, ?, ?, 'Active')";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssssss", $school_code, $school_name, $school_motto, $address, $phone, $email);
        
        if ($stmt->execute()) {
            $school_id = $conn->insert_id;
            
            // Handle logo upload
            if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadSchoolLogo($_FILES['school_logo'], $school_id, $school_code);
                if ($upload_result['success']) {
                    $update_logo = "UPDATE schools SET logo_path = ? WHERE id = ?";
                    $logo_stmt = $conn->prepare($update_logo);
                    $logo_stmt->bind_param("si", $upload_result['path'], $school_id);
                    $logo_stmt->execute();
                    $logo_stmt->close();
                }
            }
            
            $_SESSION['toast_message'] = "School '" . $school_name . "' created successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error creating school: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $stmt->close();
    } else {
        $_SESSION['toast_message'] = implode(", ", $errors);
        $_SESSION['toast_type'] = "error";
    }
    header("Location: schools.php");
    exit();
}

// ==================== EDIT SCHOOL ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_school'])) {
    $school_id = (int)$_POST['school_id'];
    $school_code = mysqli_real_escape_string($conn, trim($_POST['school_code']));
    $school_name = mysqli_real_escape_string($conn, trim($_POST['school_name']));
    $school_motto = mysqli_real_escape_string($conn, trim($_POST['school_motto'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Active');
    
    // Check if school code exists for other schools
    $check_sql = "SELECT id FROM schools WHERE school_code = ? AND id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $school_code, $school_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $_SESSION['toast_message'] = "School code already exists for another school!";
        $_SESSION['toast_type'] = "error";
        header("Location: schools.php");
        exit();
    }
    $check_stmt->close();
    
    // Get current logo path
    $current_logo_sql = "SELECT logo_path FROM schools WHERE id = ?";
    $current_logo_stmt = $conn->prepare($current_logo_sql);
    $current_logo_stmt->bind_param("i", $school_id);
    $current_logo_stmt->execute();
    $current_logo_result = $current_logo_stmt->get_result();
    $current_logo = $current_logo_result->fetch_assoc();
    $current_logo_path = $current_logo['logo_path'] ?? null;
    $current_logo_stmt->close();
    
    $update_sql = "UPDATE schools SET 
                   school_code = ?, school_name = ?, school_motto = ?, 
                   address = ?, phone = ?, email = ?, status = ?
                   WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssssssi", $school_code, $school_name, $school_motto, $address, $phone, $email, $status, $school_id);
    
    if ($stmt->execute()) {
        // Handle logo upload (replace if new file provided)
        if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
            // Delete old logo if exists
            if ($current_logo_path && file_exists('../' . $current_logo_path)) {
                unlink('../' . $current_logo_path);
            }
            
            $upload_result = uploadSchoolLogo($_FILES['school_logo'], $school_id, $school_code);
            if ($upload_result['success']) {
                $update_logo = "UPDATE schools SET logo_path = ? WHERE id = ?";
                $logo_stmt = $conn->prepare($update_logo);
                $logo_stmt->bind_param("si", $upload_result['path'], $school_id);
                $logo_stmt->execute();
                $logo_stmt->close();
            }
        }
        
        $_SESSION['toast_message'] = "School '" . $school_name . "' updated successfully!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error updating school: " . $conn->error;
        $_SESSION['toast_type'] = "error";
    }
    $stmt->close();
    header("Location: schools.php");
    exit();
}

// ==================== REMOVE SCHOOL LOGO ====================
if (isset($_GET['remove_logo']) && isset($_GET['id'])) {
    $school_id = (int)$_GET['id'];
    
    // Get logo path
    $logo_sql = "SELECT logo_path FROM schools WHERE id = ?";
    $logo_stmt = $conn->prepare($logo_sql);
    $logo_stmt->bind_param("i", $school_id);
    $logo_stmt->execute();
    $logo_result = $logo_stmt->get_result();
    $logo_data = $logo_result->fetch_assoc();
    
    if ($logo_data['logo_path'] && file_exists('../' . $logo_data['logo_path'])) {
        unlink('../' . $logo_data['logo_path']);
    }
    $logo_stmt->close();
    
    $update_sql = "UPDATE schools SET logo_path = NULL WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $school_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['toast_message'] = "School logo removed successfully!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error removing logo!";
        $_SESSION['toast_type'] = "error";
    }
    $update_stmt->close();
    header("Location: schools.php");
    exit();
}

// ==================== STATUS TOGGLE ====================
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $school_id = (int)$_GET['id'];
    $new_status = $_GET['toggle_status'] == 'activate' ? 'Active' : 'Suspended';
    
    $update_sql = "UPDATE schools SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $new_status, $school_id);
    
    if ($stmt->execute()) {
        $_SESSION['toast_message'] = "School status updated to " . $new_status . "!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Failed to update school status!";
        $_SESSION['toast_type'] = "error";
    }
    $stmt->close();
    header("Location: schools.php");
    exit();
}

// ==================== DELETE SCHOOL ====================
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $school_id = (int)$_GET['id'];
    
    // Get school info
    $info_sql = "SELECT school_name, logo_path FROM schools WHERE id = ?";
    $info_stmt = $conn->prepare($info_sql);
    $info_stmt->bind_param("i", $school_id);
    $info_stmt->execute();
    $info_result = $info_stmt->get_result();
    $school_info = $info_result->fetch_assoc();
    
    if ($school_info) {
        // Delete logo file if exists
        if ($school_info['logo_path'] && file_exists('../' . $school_info['logo_path'])) {
            unlink('../' . $school_info['logo_path']);
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete related data
            $conn->query("DELETE FROM students WHERE school_id = $school_id");
            $conn->query("DELETE FROM admins WHERE school_id = $school_id");
            $conn->query("DELETE FROM dormitories WHERE school_id = $school_id");
            $conn->query("DELETE FROM exam_types WHERE school_id = $school_id");
            $conn->query("DELETE FROM notifications WHERE school_id = $school_id");
            $conn->query("DELETE FROM maintenance_items WHERE school_id = $school_id");
            $conn->query("DELETE FROM store_tools WHERE school_id = $school_id");
            $conn->query("DELETE FROM schools WHERE id = $school_id");
            
            $conn->commit();
            $_SESSION['toast_message'] = "School '" . $school_info['school_name'] . "' and all its data deleted!";
            $_SESSION['toast_type'] = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['toast_message'] = "Error deleting school!";
            $_SESSION['toast_type'] = "error";
        }
    }
    $info_stmt->close();
    header("Location: schools.php");
    exit();
}

// Get all schools
$schools_sql = "SELECT * FROM schools ORDER BY created_at DESC";
$schools_result = mysqli_query($conn, $schools_sql);
$schools = [];
while ($row = mysqli_fetch_assoc($schools_result)) {
    $schools[] = $row;
}

// Get total counts
$total_schools = count($schools);
$active_schools = count(array_filter($schools, function($s) { return $s['status'] == 'Active'; }));
$suspended_schools = count(array_filter($schools, function($s) { return $s['status'] == 'Suspended'; }));

date_default_timezone_set('Africa/Dar_es_Salaam');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schools - Super Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <style>
        :root {
            --primary-color: #3B9DB3;
            --primary-dark: #2d7c8f;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 20px;
            padding: 25px 30px;
            color: white;
            margin-bottom: 30px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--primary-color);
        }

        .stats-card h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-Active { background: #d4edda; color: #155724; }
        .status-Suspended { background: #f8d7da; color: #721c24; }

        .school-logo {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            object-fit: cover;
            background: #f0f0f0;
        }

        .modal-content {
            border-radius: 15px;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            padding: 10px 15px;
        }
        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            color: white;
        }
        .logo-preview {
            width: 100px;
            height: 100px;
            border-radius: 15px;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            background: #f8f9fa;
        }
    </style>
</head>
<body>

<?php include '../controller/header.php'; ?>
<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="container-fluid">
        
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-building me-2"></i> Manage Schools</h2>
                    <p class="mb-0">Manage all schools in the system</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addSchoolModal">
                        <i class="fas fa-plus-circle me-2"></i> Add New School
                    </button>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <h2><?php echo $total_schools; ?></h2>
                    <p>Total Schools</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <h2 class="text-success"><?php echo $active_schools; ?></h2>
                    <p>Active Schools</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <h2 class="text-warning"><?php echo $suspended_schools; ?></h2>
                    <p>Suspended Schools</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="schoolsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Logo</th>
                                <th>School Code</th>
                                <th>School Name</th>
                                <th>Motto</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schools as $school): ?>
                            <tr>
                                <td><?php echo $school['id']; ?></td>
                                <td>
                                    <?php if ($school['logo_path'] && file_exists('../' . $school['logo_path'])): ?>
                                        <img src="../<?php echo $school['logo_path']; ?>" class="school-logo" alt="Logo">
                                    <?php else: ?>
                                        <div class="school-logo d-flex align-items-center justify-content-center bg-light">
                                            <i class="fas fa-school text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($school['school_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                                <td><?php echo htmlspecialchars($school['school_motto'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($school['phone'] ?? '-'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $school['status']; ?>">
                                        <?php echo $school['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($school['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editSchool(<?php echo $school['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($school['status'] == 'Active'): ?>
                                    <button class="btn btn-sm btn-warning" onclick="toggleStatus(<?php echo $school['id']; ?>, 'suspend')">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-success" onclick="toggleStatus(<?php echo $school['id']; ?>, 'activate')">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-danger" onclick="deleteSchool(<?php echo $school['id']; ?>, '<?php echo addslashes($school['school_name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                 </td>
                              </tr>
                            <?php endforeach; ?>
                        </tbody>
                     </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- ADD SCHOOL MODAL - Two Columns -->
<div class="modal fade" id="addSchoolModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New School</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">School Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="school_code" required placeholder="e.g., MVZ001">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">School Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="school_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">School Motto</label>
                                <input type="text" class="form-control" name="school_motto">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">School Logo</label>
                                <input type="file" class="form-control" name="school_logo" accept="image/*">
                                <small class="text-muted">Max 2MB. JPG, PNG, GIF, WEBP</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_school" class="btn btn-submit">Create School</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT SCHOOL MODAL - Two Columns -->
<div class="modal fade" id="editSchoolModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit School</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editSchoolForm">
                <div class="modal-body">
                    <input type="hidden" name="school_id" id="edit_school_id">
                    <div class="text-center mb-3" id="logoPreviewContainer">
                        <img id="logoPreview" class="logo-preview" src="" alt="School Logo" style="display: none;">
                        <div id="noLogoPreview" class="logo-preview d-flex align-items-center justify-content-center bg-light">
                            <i class="fas fa-school fa-3x text-secondary"></i>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger mt-2" id="removeLogoBtn" style="display: none;" onclick="removeLogo()">
                            <i class="fas fa-trash me-1"></i> Remove Logo
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">School Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="school_code" id="edit_school_code" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">School Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="school_name" id="edit_school_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">School Motto</label>
                                <input type="text" class="form-control" name="school_motto" id="edit_school_motto">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Change Logo</label>
                                <input type="file" class="form-control" name="school_logo" id="edit_school_logo" accept="image/*">
                                <small class="text-muted">Leave empty to keep current logo</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" id="edit_phone">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" id="edit_email">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="edit_status">
                                    <option value="Active">Active</option>
                                    <option value="Suspended">Suspended</option>
                                    <option value="Expired">Expired</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_school" class="btn btn-submit">Update School</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('#schoolsTable').DataTable({
        pageLength: 10,
        order: [[0, 'desc']]
    });
});

<?php if (isset($_SESSION['toast_message'])): ?>
Swal.fire({
    icon: '<?php echo $_SESSION['toast_type'] == 'success' ? 'success' : 'error'; ?>',
    title: '<?php echo $_SESSION['toast_type'] == 'success' ? 'Success!' : 'Error!'; ?>',
    text: '<?php echo htmlspecialchars($_SESSION['toast_message']); ?>',
    confirmButtonColor: '#3B9DB3',
    timer: 3000
});
<?php unset($_SESSION['toast_message']); unset($_SESSION['toast_type']); endif; ?>

function editSchool(id) {
    $.ajax({
        url: 'schools.php?ajax=get_school&id=' + id,
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            $('#edit_school_id').val(data.id);
            $('#edit_school_code').val(data.school_code);
            $('#edit_school_name').val(data.school_name);
            $('#edit_school_motto').val(data.school_motto);
            $('#edit_address').val(data.address);
            $('#edit_phone').val(data.phone);
            $('#edit_email').val(data.email);
            $('#edit_status').val(data.status);
            
            // Handle logo preview
            if (data.logo_path && data.logo_path !== '') {
                $('#logoPreview').attr('src', '../' + data.logo_path).show();
                $('#noLogoPreview').hide();
                $('#removeLogoBtn').show();
            } else {
                $('#logoPreview').hide();
                $('#noLogoPreview').show();
                $('#removeLogoBtn').hide();
            }
            // Clear file input
            $('#edit_school_logo').val('');
            
            $('#editSchoolModal').modal('show');
        },
        error: function() {
            Swal.fire('Error!', 'Failed to load school data', 'error');
        }
    });
}

function removeLogo() {
    let schoolId = $('#edit_school_id').val();
    Swal.fire({
        title: 'Remove Logo?',
        text: 'Are you sure you want to remove this school logo?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'schools.php?remove_logo=1&id=' + schoolId;
        }
    });
}

function toggleStatus(id, action) {
    Swal.fire({
        title: action == 'suspend' ? 'Suspend School?' : 'Activate School?',
        text: action == 'suspend' ? 'Are you sure you want to suspend this school?' : 'Are you sure you want to activate this school?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: action == 'suspend' ? '#dc3545' : '#28a745',
        confirmButtonText: action == 'suspend' ? 'Yes, suspend!' : 'Yes, activate!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'schools.php?toggle_status=' + action + '&id=' + id;
        }
    });
}

function deleteSchool(id, name) {
    Swal.fire({
        title: 'Delete School?',
        html: `<strong>"${name}"</strong> will be permanently deleted!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'schools.php?delete=1&id=' + id;
        }
    });
}
</script>

<?php include '../controller/footer.php'; ?>
</body>
</html>