<?php
// super/super_admins.php - Super Admins Management with Filters & Self-Contained AJAX
session_start();

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if Super Admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    while (ob_get_level() > 0) ob_end_clean();
    header("Location: ../mhs/login.php");
    exit();
}

require_once '../controller/db_connect.php';

// Get current logged-in super admin info
$current_super_id = (int)$_SESSION['super_admin_id'];
$current_super_sql = "SELECT * FROM super_admins WHERE id = $current_super_id AND status = 1";
$current_super_result = mysqli_query($conn, $current_super_sql);
$current_super_admin = mysqli_fetch_assoc($current_super_result);

if (!$current_super_admin) {
    session_destroy();
    header("Location: ../mhs/login.php");
    exit();
}

// ==================== HELPER FUNCTIONS ====================
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}
function validatePhone($phone) {
    return preg_match('/^[0-9]{10,15}$/', $phone);
}
function logSuperAdminAction($conn, $admin_id, $action, $description, $target_admin_id = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $log_sql = "INSERT INTO super_admin_logs (admin_id, action, description, target_admin_id, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($log_sql);
    $stmt->bind_param("ississ", $admin_id, $action, $description, $target_admin_id, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
}

// ==================== AJAX: Get Super Admin Details (Self-contained) ====================
if (isset($_GET['ajax_get_super_admin']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $admin_id = (int)$_GET['id'];
    $query = "SELECT id, first_name, last_name, email, phone, role, profile_image, status FROM super_admins WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Super Admin not found']);
    }
    $stmt->close();
    exit();
}

// ==================== ADD SUPER ADMIN ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_super_admin'])) {
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $password = $_POST['password'];
    
    $errors = [];
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!validateEmail($email)) $errors[] = "Invalid email format";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (!validatePhone($phone)) $errors[] = "Phone number must be 10-15 digits";
    if (empty($password)) $errors[] = "Password is required";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    
    // Check duplicates
    $check_stmt = $conn->prepare("SELECT id FROM super_admins WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) $errors[] = "Email already exists!";
    $check_stmt->close();
    
    $check_stmt = $conn->prepare("SELECT id FROM super_admins WHERE phone = ?");
    $check_stmt->bind_param("s", $phone);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) $errors[] = "Phone number already exists!";
    $check_stmt->close();
    
    if (empty($errors)) {
        $hashed_password = hashPassword($password);
        $profile_image = null;
        
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/super_admin_images/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['profile_image']['tmp_name']);
            finfo_close($finfo);
            if (in_array($mime_type, $allowed_types) && $_FILES['profile_image']['size'] <= 2 * 1024 * 1024) {
                $extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $filename = 'super_admin_' . time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $email) . '.' . $extension;
                $filepath = $upload_dir . $filename;
                $db_path = 'uploads/super_admin_images/' . $filename;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filepath)) $profile_image = $db_path;
            }
        }
        
        $insert_sql = "INSERT INTO super_admins (first_name, last_name, email, phone, role, password, profile_image, status, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sssssss", $first_name, $last_name, $email, $phone, $role, $hashed_password, $profile_image);
        if ($stmt->execute()) {
            $new_admin_id = $conn->insert_id;
            logSuperAdminAction($conn, $current_super_id, 'CREATE', "Created new super admin: $first_name $last_name ($email)", $new_admin_id);
            $_SESSION['toast_message'] = "Super Admin '$first_name $last_name' created successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error creating super admin: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $stmt->close();
    } else {
        $_SESSION['toast_message'] = implode(", ", $errors);
        $_SESSION['toast_type'] = "error";
    }
    header("Location: super_admins.php");
    exit();
}

// ==================== EDIT SUPER ADMIN ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_super_admin'])) {
    $admin_id = (int)$_POST['admin_id'];
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $status = (int)$_POST['status'];
    
    $errors = [];
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!validateEmail($email)) $errors[] = "Invalid email format";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (!validatePhone($phone)) $errors[] = "Phone number must be 10-15 digits";
    
    // Check uniqueness for other admins
    $check_stmt = $conn->prepare("SELECT id FROM super_admins WHERE email = ? AND id != ?");
    $check_stmt->bind_param("si", $email, $admin_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) $errors[] = "Email already exists for another admin!";
    $check_stmt->close();
    
    $check_stmt = $conn->prepare("SELECT id FROM super_admins WHERE phone = ? AND id != ?");
    $check_stmt->bind_param("si", $phone, $admin_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) $errors[] = "Phone number already exists for another admin!";
    $check_stmt->close();
    
    // Get current profile image
    $current_image_stmt = $conn->prepare("SELECT profile_image FROM super_admins WHERE id = ?");
    $current_image_stmt->bind_param("i", $admin_id);
    $current_image_stmt->execute();
    $current_image = $current_image_stmt->get_result()->fetch_assoc();
    $current_image_path = $current_image['profile_image'] ?? null;
    $current_image_stmt->close();
    
    if (empty($errors)) {
        $profile_image = $current_image_path;
        // Handle new image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/super_admin_images/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['profile_image']['tmp_name']);
            finfo_close($finfo);
            if (in_array($mime_type, $allowed_types) && $_FILES['profile_image']['size'] <= 2 * 1024 * 1024) {
                if ($current_image_path && file_exists('../' . $current_image_path)) unlink('../' . $current_image_path);
                $extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $filename = 'super_admin_' . time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $email) . '.' . $extension;
                $filepath = $upload_dir . $filename;
                $db_path = 'uploads/super_admin_images/' . $filename;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filepath)) $profile_image = $db_path;
            }
        }
        
        $update_sql = "UPDATE super_admins SET first_name=?, last_name=?, email=?, phone=?, role=?, profile_image=?, status=? WHERE id=?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssssssi", $first_name, $last_name, $email, $phone, $role, $profile_image, $status, $admin_id);
        if ($stmt->execute()) {
            logSuperAdminAction($conn, $current_super_id, 'EDIT', "Edited super admin: $first_name $last_name ($email)", $admin_id);
            $_SESSION['toast_message'] = "Super Admin updated successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error updating super admin: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $stmt->close();
    } else {
        $_SESSION['toast_message'] = implode(", ", $errors);
        $_SESSION['toast_type'] = "error";
    }
    header("Location: super_admins.php");
    exit();
}

// ==================== CHANGE PASSWORD ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $admin_id = (int)$_POST['admin_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $errors = [];
    if (empty($new_password)) $errors[] = "New password is required";
    if (strlen($new_password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($new_password !== $confirm_password) $errors[] = "Passwords do not match";
    
    if (empty($errors)) {
        $hashed_password = hashPassword($new_password);
        $stmt = $conn->prepare("UPDATE super_admins SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $admin_id);
        if ($stmt->execute()) {
            logSuperAdminAction($conn, $current_super_id, 'CHANGE_PASSWORD', "Changed password for admin ID: $admin_id", $admin_id);
            $_SESSION['toast_message'] = "Password changed successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error changing password: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $stmt->close();
    } else {
        $_SESSION['toast_message'] = implode(", ", $errors);
        $_SESSION['toast_type'] = "error";
    }
    header("Location: super_admins.php");
    exit();
}

// ==================== TOGGLE STATUS (Activate/Deactivate) ====================
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $admin_id = (int)$_GET['id'];
    $action = $_GET['toggle_status'];
    $new_status = ($action == 'activate') ? 1 : 0;
    
    // Prevent self deactivation
    if ($admin_id == $current_super_id) {
        $_SESSION['toast_message'] = "You cannot deactivate your own account!";
        $_SESSION['toast_type'] = "error";
        header("Location: super_admins.php");
        exit();
    }
    
    // Prevent deactivation of the first super admin (id = 1)
    if ($admin_id == 1 && $new_status == 0) {
        $_SESSION['toast_message'] = "The first super admin (ID: 1) cannot be deactivated!";
        $_SESSION['toast_type'] = "error";
        header("Location: super_admins.php");
        exit();
    }
    
    $status_text = ($new_status == 1) ? 'activated' : 'deactivated';
    $stmt = $conn->prepare("UPDATE super_admins SET status = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $admin_id);
    if ($stmt->execute()) {
        logSuperAdminAction($conn, $current_super_id, strtoupper($status_text), ucfirst($status_text) . " super admin ID: $admin_id", $admin_id);
        $_SESSION['toast_message'] = "Super Admin " . ucfirst($status_text) . " successfully!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Failed to update status!";
        $_SESSION['toast_type'] = "error";
    }
    $stmt->close();
    header("Location: super_admins.php");
    exit();
}

// ==================== DELETE SUPER ADMIN ====================
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $admin_id = (int)$_GET['id'];
    
    // Prevent self deletion
    if ($admin_id == $current_super_id) {
        $_SESSION['toast_message'] = "You cannot delete your own account!";
        $_SESSION['toast_type'] = "error";
        header("Location: super_admins.php");
        exit();
    }
    
    // Prevent deletion of the first super admin (id = 1)
    if ($admin_id == 1) {
        $_SESSION['toast_message'] = "The first super admin (ID: 1) cannot be deleted!";
        $_SESSION['toast_type'] = "error";
        header("Location: super_admins.php");
        exit();
    }
    
    // Get admin info for logging & image deletion
    $info_stmt = $conn->prepare("SELECT first_name, last_name, email, profile_image FROM super_admins WHERE id = ?");
    $info_stmt->bind_param("i", $admin_id);
    $info_stmt->execute();
    $admin_info = $info_stmt->get_result()->fetch_assoc();
    
    if ($admin_info) {
        if ($admin_info['profile_image'] && file_exists('../' . $admin_info['profile_image'])) {
            unlink('../' . $admin_info['profile_image']);
        }
        $delete_stmt = $conn->prepare("DELETE FROM super_admins WHERE id = ?");
        $delete_stmt->bind_param("i", $admin_id);
        if ($delete_stmt->execute()) {
            logSuperAdminAction($conn, $current_super_id, 'DELETE', "Deleted super admin: {$admin_info['first_name']} {$admin_info['last_name']} ({$admin_info['email']})", $admin_id);
            $_SESSION['toast_message'] = "Super Admin deleted successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error deleting super admin!";
            $_SESSION['toast_type'] = "error";
        }
        $delete_stmt->close();
    }
    $info_stmt->close();
    header("Location: super_admins.php");
    exit();
}

// ==================== REMOVE PROFILE IMAGE ====================
if (isset($_GET['remove_image']) && isset($_GET['id'])) {
    $admin_id = (int)$_GET['id'];
    $image_stmt = $conn->prepare("SELECT profile_image FROM super_admins WHERE id = ?");
    $image_stmt->bind_param("i", $admin_id);
    $image_stmt->execute();
    $image_data = $image_stmt->get_result()->fetch_assoc();
    if ($image_data && $image_data['profile_image'] && file_exists('../' . $image_data['profile_image'])) {
        unlink('../' . $image_data['profile_image']);
    }
    $image_stmt->close();
    
    $update_stmt = $conn->prepare("UPDATE super_admins SET profile_image = NULL WHERE id = ?");
    $update_stmt->bind_param("i", $admin_id);
    if ($update_stmt->execute()) {
        $_SESSION['toast_message'] = "Profile image removed successfully!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error removing profile image!";
        $_SESSION['toast_type'] = "error";
    }
    $update_stmt->close();
    header("Location: super_admins.php");
    exit();
}

// Get all super admins
$super_admins = [];
$result = mysqli_query($conn, "SELECT * FROM super_admins ORDER BY created_at DESC");
if ($result) while ($row = mysqli_fetch_assoc($result)) $super_admins[] = $row;

$total_admins = count($super_admins);
$active_admins = count(array_filter($super_admins, fn($a) => $a['status'] == 1));
$inactive_admins = $total_admins - $active_admins;

date_default_timezone_set('Africa/Dar_es_Salaam');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Super Admins - Super Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <style>
        :root { --primary-color: #3B9DB3; --primary-dark: #2d7c8f; }
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s;
        }
        .stats-card:hover { transform: translateY(-3px); }
        .stats-card h2 { font-size: 2rem; font-weight: 700; margin-bottom: 5px; color: var(--primary-color); }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .admin-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; background: #f0f0f0; display: flex; align-items: center; justify-content: center; }
        .modal-content { border-radius: 15px; }
        .modal-header { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; border-radius: 15px 15px 0 0; }
        .modal-header .btn-close { filter: brightness(0) invert(1); }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #e0e0e0; padding: 10px 15px; }
        .btn-submit { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); border: none; border-radius: 10px; padding: 10px 25px; color: white; }
        .avatar-preview { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-color); background: #f8f9fa; }
        .role-badge { padding: 4px 10px; border-radius: 15px; font-size: 0.7rem; font-weight: 600; }
        .role-Super\ Admin { background: #cce5ff; color: #004085; }
        .role-Account\ Manager { background: #d4edda; color: #155724; }
        .role-Support { background: #fff3cd; color: #856404; }
        .role-Developer { background: #d1ecf1; color: #0c5460; }
        .filter-bar {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group label { font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; color: #495057; }
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
                    <h2><i class="fas fa-user-shield me-2"></i> Manage Super Administrators</h2>
                    <p class="mb-0">Manage all super admin accounts - Create, Edit, Change Password, Activate/Deactivate</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addSuperAdminModal">
                        <i class="fas fa-plus-circle me-2"></i> Add New Super Admin
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3"><div class="stats-card"><h2><?php echo $total_admins; ?></h2><p><i class="fas fa-users me-1"></i> Total Admins</p></div></div>
            <div class="col-md-4 mb-3"><div class="stats-card"><h2 class="text-success"><?php echo $active_admins; ?></h2><p><i class="fas fa-check-circle text-success me-1"></i> Active Admins</p></div></div>
            <div class="col-md-4 mb-3"><div class="stats-card"><h2 class="text-danger"><?php echo $inactive_admins; ?></h2><p><i class="fas fa-ban text-danger me-1"></i> Inactive Admins</p></div></div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label><i class="fas fa-tag me-1"></i> Filter by Role</label>
                <select id="filterRole" class="form-select form-select-sm">
                    <option value="">All Roles</option>
                    <option value="Super Admin">Super Admin</option>
                    <option value="Account Manager">Account Manager</option>
                    <option value="Support">Support</option>
                    <option value="Developer">Developer</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-circle-info me-1"></i> Filter by Status</label>
                <select id="filterStatus" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button id="clearFilters" class="btn btn-secondary btn-sm w-100"><i class="fas fa-eraser me-1"></i> Clear Filters</button>
            </div>
        </div>

        <!-- Super Admins Table -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3"><h5 class="mb-0"><i class="fas fa-table me-2"></i> All Super Administrators</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="superAdminsTable" class="table table-hover">
                        <thead class="table-light">
                            <tr><th>ID</th><th>Avatar</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($super_admins as $admin): ?>
                            <tr id="admin-row-<?php echo $admin['id']; ?>">
                                <td><?php echo $admin['id']; if ($admin['id'] == $current_super_id) echo ' <span class="badge bg-info">You</span>'; ?></td>
                                <td>
                                    <?php if (!empty($admin['profile_image']) && file_exists('../' . $admin['profile_image'])): ?>
                                        <img src="../<?php echo htmlspecialchars($admin['profile_image']); ?>" class="admin-avatar" style="width:45px;height:45px;border-radius:50%;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="admin-avatar d-flex align-items-center justify-content-center bg-light" style="width:45px;height:45px;border-radius:50%;"><i class="fas fa-user-circle fa-2x text-secondary"></i></div>
                                    <?php endif; ?>
                                 </td>
                                <td><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?> </td>
                                <td><?php echo htmlspecialchars($admin['email']); ?> </td>
                                <td><?php echo htmlspecialchars($admin['phone']); ?> </td>
                                <td><span class="role-badge role-<?php echo str_replace(' ', '-', $admin['role']); ?>"><?php echo htmlspecialchars($admin['role']); ?></span></td>
                                <td><span class="status-badge status-<?php echo $admin['status'] == 1 ? 'active' : 'inactive'; ?>"><?php echo $admin['status'] == 1 ? 'Active' : 'Inactive'; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?> </td>
                                <td>
                                    <button class="btn btn-sm btn-primary btn-action" onclick="editSuperAdmin(<?php echo $admin['id']; ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-warning btn-action" onclick="changePassword(<?php echo $admin['id']; ?>, '<?php echo addslashes($admin['first_name'] . ' ' . $admin['last_name']); ?>')" title="Change Password"><i class="fas fa-key"></i></button>
                                    <?php if ($admin['id'] != $current_super_id && $admin['id'] != 1): ?>
                                        <?php if ($admin['status'] == 1): ?>
                                        <button class="btn btn-sm btn-danger btn-action" onclick="toggleStatus(<?php echo $admin['id']; ?>, 'deactivate', '<?php echo addslashes($admin['first_name'] . ' ' . $admin['last_name']); ?>')" title="Deactivate"><i class="fas fa-ban"></i></button>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-success btn-action" onclick="toggleStatus(<?php echo $admin['id']; ?>, 'activate', '<?php echo addslashes($admin['first_name'] . ' ' . $admin['last_name']); ?>')" title="Activate"><i class="fas fa-check-circle"></i></button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-dark btn-action" onclick="deleteSuperAdmin(<?php echo $admin['id']; ?>, '<?php echo addslashes($admin['first_name'] . ' ' . $admin['last_name']); ?>')" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                    <?php endif; ?>
                                    <?php if ($admin['id'] == 1 && $admin['id'] != $current_super_id): ?>
                                        <span class="badge bg-secondary ms-1">Protected</span>
                                    <?php endif; ?>
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

<!-- ADD MODAL -->
<div class="modal fade" id="addSuperAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Super Admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">First Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="first_name" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Last Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="last_name" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="email" required></div>
                    <div class="mb-3"><label class="form-label">Phone Number <span class="text-danger">*</span></label><input type="tel" class="form-control" name="phone" required placeholder="e.g., 255712345678"></div>
                    <div class="mb-3"><label class="form-label">Role <span class="text-danger">*</span></label><select class="form-select" name="role" required><option value="Super Admin">Super Admin</option><option value="Account Manager">Account Manager</option><option value="Support">Support</option><option value="Developer">Developer</option></select></div>
                    <div class="mb-3"><label class="form-label">Profile Image</label><input type="file" class="form-control" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp"><small class="text-muted">Max 2MB. JPG, PNG, GIF, WEBP</small></div>
                    <div class="mb-3"><label class="form-label">Password <span class="text-danger">*</span></label><input type="password" class="form-control" name="password" required minlength="6"><small class="text-muted">Minimum 6 characters</small></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_super_admin" class="btn btn-submit">Create Admin</button></div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editSuperAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Super Admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" enctype="multipart/form-data" id="editSuperAdminForm">
                <div class="modal-body">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    <div class="text-center mb-3">
                        <img id="avatarPreview" class="avatar-preview" src="" alt="Avatar" style="display: none;">
                        <div id="noAvatarPreview" class="avatar-preview d-flex align-items-center justify-content-center bg-light"><i class="fas fa-user-circle fa-3x text-secondary"></i></div>
                        <button type="button" class="btn btn-sm btn-danger mt-2" id="removeImageBtn" style="display: none;" onclick="removeProfileImage()"><i class="fas fa-trash me-1"></i> Remove Image</button>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">First Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="first_name" id="edit_first_name" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Last Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="last_name" id="edit_last_name" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="email" id="edit_email" required></div>
                    <div class="mb-3"><label class="form-label">Phone Number <span class="text-danger">*</span></label><input type="tel" class="form-control" name="phone" id="edit_phone" required></div>
                    <div class="mb-3"><label class="form-label">Role <span class="text-danger">*</span></label><select class="form-select" name="role" id="edit_role"><option value="Super Admin">Super Admin</option><option value="Account Manager">Account Manager</option><option value="Support">Support</option><option value="Developer">Developer</option></select></div>
                    <div class="mb-3"><label class="form-label">Change Profile Image (Optional)</label><input type="file" class="form-control" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp"><small class="text-muted">Leave empty to keep current image. Max 2MB.</small></div>
                    <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status" id="edit_status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="edit_super_admin" class="btn btn-submit">Update Admin</button></div>
            </form>
        </div>
    </div>
</div>

<!-- CHANGE PASSWORD MODAL -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-key me-2"></i> Change Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="admin_id" id="password_admin_id">
                    <div class="mb-3 text-center"><i class="fas fa-user-shield fa-3x text-primary mb-2"></i><h5 id="password_admin_name"></h5></div>
                    <div class="mb-3"><label class="form-label">New Password <span class="text-danger">*</span></label><input type="password" class="form-control" name="new_password" id="new_password" required minlength="6"><small class="text-muted">Minimum 6 characters</small></div>
                    <div class="mb-3"><label class="form-label">Confirm Password <span class="text-danger">*</span></label><input type="password" class="form-control" name="confirm_password" id="confirm_password" required></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="change_password" class="btn btn-submit">Change Password</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    var table = $('#superAdminsTable').DataTable({
        pageLength: 10,
        order: [[0, 'desc']],
        language: { search: "Search:", lengthMenu: "Show _MENU_ admins", info: "Showing _START_ to _END_ of _TOTAL_ admins", emptyTable: "No super admins found" }
    });

    // Custom filters
    $('#filterRole').on('change', function() {
        var val = $.fn.dataTable.util.escapeRegex($(this).val());
        table.column(5).search(val ? '^' + val + '$' : '', true, false).draw();
    });
    $('#filterStatus').on('change', function() {
        var val = $(this).val();
        table.column(6).search(val).draw();
    });
    $('#clearFilters').on('click', function() {
        $('#filterRole').val('');
        $('#filterStatus').val('');
        table.search('').columns().search('').draw();
    });
});

<?php if (isset($_SESSION['toast_message'])): ?>
Swal.fire({ icon: '<?php echo $_SESSION['toast_type'] == 'success' ? 'success' : 'error'; ?>', title: '<?php echo $_SESSION['toast_type'] == 'success' ? 'Success!' : 'Error!'; ?>', text: '<?php echo htmlspecialchars($_SESSION['toast_message']); ?>', confirmButtonColor: '#3B9DB3', timer: 3000 });
<?php unset($_SESSION['toast_message']); unset($_SESSION['toast_type']); endif; ?>

function editSuperAdmin(id) {
    Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    $.ajax({
        url: 'super_admins.php?ajax_get_super_admin=1&id=' + id,
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            Swal.close();
            if (data.error) { Swal.fire('Error!', data.error, 'error'); return; }
            $('#edit_admin_id').val(data.id);
            $('#edit_first_name').val(data.first_name);
            $('#edit_last_name').val(data.last_name);
            $('#edit_email').val(data.email);
            $('#edit_phone').val(data.phone);
            $('#edit_role').val(data.role);
            $('#edit_status').val(data.status);
            if (data.profile_image && data.profile_image !== '') {
                $('#avatarPreview').attr('src', '../' + data.profile_image).show();
                $('#noAvatarPreview').hide();
                $('#removeImageBtn').show();
            } else {
                $('#avatarPreview').hide();
                $('#noAvatarPreview').show();
                $('#removeImageBtn').hide();
            }
            $('#editSuperAdminModal').modal('show');
        },
        error: function() { Swal.close(); Swal.fire('Error!', 'Failed to load admin data', 'error'); }
    });
}

function changePassword(id, name) {
    $('#password_admin_id').val(id);
    $('#password_admin_name').text(name);
    $('#new_password, #confirm_password').val('');
    $('#changePasswordModal').modal('show');
}

function toggleStatus(id, action, name) {
    let title = action == 'activate' ? 'Activate Admin?' : 'Deactivate Admin?';
    let text = action == 'activate' ? `Activate ${name}?` : `Deactivate ${name}?`;
    Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonColor: action == 'activate' ? '#28a745' : '#dc3545', confirmButtonText: action == 'activate' ? 'Yes, activate!' : 'Yes, deactivate!' }).then((result) => { if (result.isConfirmed) window.location.href = 'super_admins.php?toggle_status=' + action + '&id=' + id; });
}

function deleteSuperAdmin(id, name) {
    if (id == 1) { Swal.fire('Cannot Delete', 'The first super admin (ID: 1) cannot be deleted.', 'warning'); return; }
    Swal.fire({ title: 'Delete Admin?', html: `<strong>"${escapeHtml(name)}"</strong> will be permanently deleted!`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Yes, delete!' }).then((result) => { if (result.isConfirmed) window.location.href = 'super_admins.php?delete=1&id=' + id; });
}

function removeProfileImage() {
    let adminId = $('#edit_admin_id').val();
    if (!adminId) { Swal.fire('Error!', 'Admin ID not found', 'error'); return; }
    Swal.fire({ title: 'Remove Image?', text: 'Are you sure you want to remove this profile image?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Yes, remove!' }).then((result) => { if (result.isConfirmed) window.location.href = 'super_admins.php?remove_image=1&id=' + adminId; });
}

function escapeHtml(str) { if (!str) return ''; return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
</script>

<?php include '../controller/footer.php'; ?>
</body>
</html>