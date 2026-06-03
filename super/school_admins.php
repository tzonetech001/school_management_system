<?php
// super/school_admins.php - Manage School Head Masters (with Filters & Auto-Search)
session_start();

// Check if Super Admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    while (ob_get_level() > 0) ob_end_clean();
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

$current_year = date('Y');
$default_password = "School@" . $current_year;
$hashed_default_password = password_hash($default_password, PASSWORD_DEFAULT);

// ==================== AJAX: Get Head Master Details (Self‑contained) ====================
if (isset($_GET['ajax_get_head_master']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $admin_id = (int)$_GET['id'];
    $query = "SELECT id, first_name, middle_name, last_name, sex, email, phone_number, nida, address, status, school_id 
              FROM admins WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Head Master not found']);
    }
    $stmt->close();
    exit();
}

// ==================== ADD HEAD MASTER ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_head_master'])) {
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $middle_name = mysqli_real_escape_string($conn, trim($_POST['middle_name'] ?? ''));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $sex = mysqli_real_escape_string($conn, $_POST['sex']);
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone_number = mysqli_real_escape_string($conn, trim($_POST['phone_number']));
    $school_id = (int)$_POST['school_id'];
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $nida = mysqli_real_escape_string($conn, trim($_POST['nida'] ?? ''));
    
    $errors = [];
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($phone_number)) $errors[] = "Phone number is required";
    if ($school_id <= 0) $errors[] = "Please select a school";
    
    // Check unique email/phone
    $check_stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) $errors[] = "Email already exists!";
    $check_stmt->close();
    
    $check_stmt = $conn->prepare("SELECT id FROM admins WHERE phone_number = ?");
    $check_stmt->bind_param("s", $phone_number);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) $errors[] = "Phone number already exists!";
    $check_stmt->close();
    
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $insert_sql = "INSERT INTO admins (first_name, middle_name, last_name, sex, email, phone_number, nida, address, password, status, school_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sssssssssi", $first_name, $middle_name, $last_name, $sex, $email, $phone_number, $nida, $address, $hashed_default_password, $school_id);
            if ($stmt->execute()) {
                $admin_id = $conn->insert_id;
                // Assign Head Master role (role_id = 1)
                $role_stmt = $conn->prepare("INSERT INTO admin_role_assignments (admin_id, role_id, is_primary) VALUES (?, 1, 1)");
                $role_stmt->bind_param("i", $admin_id);
                $role_stmt->execute();
                $role_stmt->close();
                $conn->commit();
                $_SESSION['toast_message'] = "Head Master added successfully! Default password: " . $default_password;
                $_SESSION['toast_type'] = "success";
            } else throw new Exception("Failed to add Head Master");
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['toast_message'] = "Error: " . $e->getMessage();
            $_SESSION['toast_type'] = "error";
        }
    } else {
        $_SESSION['toast_message'] = implode(", ", $errors);
        $_SESSION['toast_type'] = "error";
    }
    header("Location: school_admins.php");
    exit();
}

// ==================== EDIT HEAD MASTER ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_head_master'])) {
    $admin_id = (int)$_POST['admin_id'];
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $middle_name = mysqli_real_escape_string($conn, trim($_POST['middle_name'] ?? ''));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $sex = mysqli_real_escape_string($conn, $_POST['sex']);
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone_number = mysqli_real_escape_string($conn, trim($_POST['phone_number']));
    $school_id = (int)$_POST['school_id'];
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $nida = mysqli_real_escape_string($conn, trim($_POST['nida'] ?? ''));
    $status = (int)$_POST['status'];
    
    $errors = [];
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($phone_number)) $errors[] = "Phone number is required";
    if ($school_id <= 0) $errors[] = "Please select a school";
    
    // Check unique email/phone for other admins
    $check_stmt = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
    $check_stmt->bind_param("si", $email, $admin_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) $errors[] = "Email already exists for another admin!";
    $check_stmt->close();
    
    $check_stmt = $conn->prepare("SELECT id FROM admins WHERE phone_number = ? AND id != ?");
    $check_stmt->bind_param("si", $phone_number, $admin_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) $errors[] = "Phone number already exists for another admin!";
    $check_stmt->close();
    
    if (empty($errors)) {
        $update_sql = "UPDATE admins SET first_name=?, middle_name=?, last_name=?, sex=?, email=?, phone_number=?, nida=?, address=?, status=?, school_id=? WHERE id=?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssssssssii", $first_name, $middle_name, $last_name, $sex, $email, $phone_number, $nida, $address, $status, $school_id, $admin_id);
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "Head Master updated successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Error updating Head Master!";
            $_SESSION['toast_type'] = "error";
        }
        $stmt->close();
    } else {
        $_SESSION['toast_message'] = implode(", ", $errors);
        $_SESSION['toast_type'] = "error";
    }
    header("Location: school_admins.php");
    exit();
}

// ==================== RESET PASSWORD ====================
if (isset($_GET['reset_password']) && isset($_GET['id'])) {
    $admin_id = (int)$_GET['id'];
    $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_default_password, $admin_id);
    if ($stmt->execute()) {
        $_SESSION['toast_message'] = "Password reset to default: " . $default_password;
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error resetting password!";
        $_SESSION['toast_type'] = "error";
    }
    $stmt->close();
    header("Location: school_admins.php");
    exit();
}

// ==================== DELETE HEAD MASTER ====================
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $admin_id = (int)$_GET['id'];
    $name_stmt = $conn->prepare("SELECT first_name, last_name FROM admins WHERE id = ?");
    $name_stmt->bind_param("i", $admin_id);
    $name_stmt->execute();
    $admin_data = $name_stmt->get_result()->fetch_assoc();
    $admin_name = $admin_data['first_name'] . ' ' . $admin_data['last_name'];
    $name_stmt->close();
    
    $conn->query("DELETE FROM admin_role_assignments WHERE admin_id = $admin_id");
    $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    if ($stmt->execute()) {
        $_SESSION['toast_message'] = "Head Master '$admin_name' deleted successfully!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error deleting Head Master!";
        $_SESSION['toast_type'] = "error";
    }
    $stmt->close();
    header("Location: school_admins.php");
    exit();
}

// ==================== TOGGLE STATUS ====================
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $admin_id = (int)$_GET['id'];
    $new_status = $_GET['toggle_status'] == 'activate' ? 1 : 0;
    $stmt = $conn->prepare("UPDATE admins SET status = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $admin_id);
    if ($stmt->execute()) {
        $_SESSION['toast_message'] = "Head Master " . ($new_status ? 'activated' : 'deactivated') . " successfully!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error updating status!";
        $_SESSION['toast_type'] = "error";
    }
    $stmt->close();
    header("Location: school_admins.php");
    exit();
}

// ==================== FETCH DATA FOR DISPLAY ====================
// All Head Masters (role_id = 1) with school details, student count, teacher count
$admins_sql = "SELECT a.*, s.school_name, s.school_code,
               (SELECT COUNT(*) FROM students st WHERE st.school_id = a.school_id AND st.is_leaver = 0 AND st.status = 1) as total_students,
               (SELECT COUNT(*) FROM admins sub_a WHERE sub_a.school_id = a.school_id AND sub_a.id IN (SELECT admin_id FROM admin_role_assignments)) as total_teachers
               FROM admins a
               JOIN schools s ON a.school_id = s.id
               WHERE a.id IN (SELECT admin_id FROM admin_role_assignments WHERE role_id = 1)
               ORDER BY a.created_at DESC";
$admins_result = mysqli_query($conn, $admins_sql);
$head_masters = [];
while ($row = mysqli_fetch_assoc($admins_result)) {
    $head_masters[] = $row;
}

// All active schools for dropdowns
$schools_sql = "SELECT id, school_code, school_name FROM schools WHERE status = 'Active' ORDER BY school_name";
$schools_result = mysqli_query($conn, $schools_sql);
$schools = [];
while ($row = mysqli_fetch_assoc($schools_result)) {
    $schools[] = $row;
}

// Statistics
$total_heads = count($head_masters);
$active_heads = count(array_filter($head_masters, fn($h) => $h['status'] == 1));
$inactive_heads = $total_heads - $active_heads;

date_default_timezone_set('Africa/Dar_es_Salaam');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Head Masters - Super Admin</title>
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
            transition: all 0.3s ease;
        }
        .stats-card:hover { transform: translateY(-5px); }
        .stats-card h2 { font-size: 2rem; font-weight: 700; margin-bottom: 5px; color: var(--primary-color); }
        .stats-card p { margin: 0; color: #666; font-size: 0.9rem; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .action-btns .btn { padding: 4px 8px; margin: 0 2px; font-size: 0.75rem; }
        .modal-content { border-radius: 15px; }
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .modal-header .btn-close { filter: brightness(0) invert(1); }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #e0e0e0; padding: 10px 15px; }
        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            color: white;
        }
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
        .badge-teachers { background: #17a2b8; color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.7rem; }
        .dataTables_filter { margin-bottom: 15px; }
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
                    <h2><i class="fas fa-user-graduate me-2"></i> School Head Masters</h2>
                    <p class="mb-0">Manage head masters for all schools in the system</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addHeadMasterModal">
                        <i class="fas fa-plus-circle me-2"></i> Add Head Master
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3"><div class="stats-card"><h2><?php echo $total_heads; ?></h2><p><i class="fas fa-users me-1"></i> Total Head Masters</p></div></div>
            <div class="col-md-4 mb-3"><div class="stats-card"><h2 class="text-success"><?php echo $active_heads; ?></h2><p><i class="fas fa-check-circle me-1"></i> Active Head Masters</p></div></div>
            <div class="col-md-4 mb-3"><div class="stats-card"><h2 class="text-danger"><?php echo $inactive_heads; ?></h2><p><i class="fas fa-ban me-1"></i> Inactive Head Masters</p></div></div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label><i class="fas fa-school me-1"></i> Filter by School</label>
                <select id="filterSchool" class="form-select form-select-sm">
                    <option value="">All Schools</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?php echo htmlspecialchars($school['school_name']); ?>"><?php echo htmlspecialchars($school['school_name']); ?></option>
                    <?php endforeach; ?>
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

        <!-- Head Masters Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="headMastersTable" class="table table-hover">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>School Code</th><th>School Name</th><th>Students</th><th>Teachers</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($head_masters as $hm): ?>
                            <tr>
                                <td><?php echo $hm['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($hm['first_name'] . ' ' . $hm['last_name']); ?></strong><?php if(!empty($hm['middle_name'])) echo '<br><small class="text-muted">'.htmlspecialchars($hm['middle_name']).'</small>'; ?></td>
                                <td><?php echo htmlspecialchars($hm['email']); ?></td>
                                <td><?php echo htmlspecialchars($hm['phone_number']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($hm['school_code']); ?></span></td>
                                <td><?php echo htmlspecialchars($hm['school_name']); ?></td>
                                <td><span class="badge bg-info"><?php echo number_format($hm['total_students'] ?? 0); ?></span></td>
                                <td><span class="badge-teachers"><i class="fas fa-chalkboard-user me-1"></i> <?php echo number_format($hm['total_teachers'] ?? 0); ?></span></td>
                                <td><span class="status-badge status-<?php echo $hm['status'] == 1 ? 'active' : 'inactive'; ?>"><?php echo $hm['status'] == 1 ? 'Active' : 'Inactive'; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($hm['created_at'])); ?></td>
                                <td class="action-btns">
                                    <button class="btn btn-sm btn-primary" onclick="editHeadMaster(<?php echo $hm['id']; ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-warning" onclick="resetPassword(<?php echo $hm['id']; ?>, '<?php echo addslashes($hm['first_name'] . ' ' . $hm['last_name']); ?>')" title="Reset Password"><i class="fas fa-key"></i></button>
                                    <?php if ($hm['status'] == 1): ?>
                                        <button class="btn btn-sm btn-secondary" onclick="toggleStatus(<?php echo $hm['id']; ?>, 'deactivate')" title="Deactivate"><i class="fas fa-ban"></i></button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-success" onclick="toggleStatus(<?php echo $hm['id']; ?>, 'activate')" title="Activate"><i class="fas fa-check-circle"></i></button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-danger" onclick="deleteHeadMaster(<?php echo $hm['id']; ?>, '<?php echo addslashes($hm['first_name'] . ' ' . $hm['last_name']); ?>')" title="Delete"><i class="fas fa-trash-alt"></i></button>
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

<!-- ADD HEAD MASTER MODAL -->
<div class="modal fade" id="addHeadMasterModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Head Master</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">First Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="first_name" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Middle Name</label><input type="text" class="form-control" name="middle_name"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Last Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="last_name" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Gender <span class="text-danger">*</span></label><select class="form-select" name="sex" required><option value="Male">Male</option><option value="Female">Female</option></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="email" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Phone Number <span class="text-danger">*</span></label><input type="tel" class="form-control" name="phone_number" required placeholder="e.g., 255712345678"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">NIDA Number</label><input type="text" class="form-control" name="nida"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">School <span class="text-danger">*</span></label><select class="form-select" name="school_id" required><option value="">-- Select School --</option><?php foreach ($schools as $school): ?><option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['school_code'] . ' - ' . $school['school_name']); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-12 mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"></textarea></div>
                    </div>
                    <div class="info-text alert alert-info"><i class="fas fa-info-circle me-2"></i>Default password: <strong><?php echo $default_password; ?></strong><br><small>The head master can change their password after first login.</small></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_head_master" class="btn btn-submit"><i class="fas fa-save me-2"></i> Add Head Master</button></div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT HEAD MASTER MODAL -->
<div class="modal fade" id="editHeadMasterModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Head Master</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" id="editHeadMasterForm">
                <div class="modal-body">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">First Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="first_name" id="edit_first_name" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Middle Name</label><input type="text" class="form-control" name="middle_name" id="edit_middle_name"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Last Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="last_name" id="edit_last_name" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Gender <span class="text-danger">*</span></label><select class="form-select" name="sex" id="edit_sex"><option value="Male">Male</option><option value="Female">Female</option></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="email" id="edit_email" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Phone Number <span class="text-danger">*</span></label><input type="tel" class="form-control" name="phone_number" id="edit_phone_number" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">NIDA Number</label><input type="text" class="form-control" name="nida" id="edit_nida"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Status</label><select class="form-select" name="status" id="edit_status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">School <span class="text-danger">*</span></label><select class="form-select" name="school_id" id="edit_school_id"><option value="">-- Select School --</option><?php foreach ($schools as $school): ?><option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['school_code'] . ' - ' . $school['school_name']); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-12 mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address" id="edit_address" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="edit_head_master" class="btn btn-submit"><i class="fas fa-save me-2"></i> Update Head Master</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    var table = $('#headMastersTable').DataTable({
        pageLength: 10,
        order: [[0, 'desc']],
        language: { search: "Auto Search:", lengthMenu: "Show _MENU_ entries", info: "Showing _START_ to _END_ of _TOTAL_ entries" }
    });

    // Custom filter: School (column 5 = School Name)
    $('#filterSchool').on('change', function() {
        var val = $.fn.dataTable.util.escapeRegex($(this).val());
        table.column(5).search(val ? '^' + val + '$' : '', true, false).draw();
    });
    // Custom filter: Status (column 8 = Status text)
    $('#filterStatus').on('change', function() {
        var val = $(this).val();
        table.column(8).search(val).draw();
    });
    // Clear filters
    $('#clearFilters').on('click', function() {
        $('#filterSchool').val('');
        $('#filterStatus').val('');
        table.search('').columns().search('').draw();
    });
});

// Toast message display
<?php if (isset($_SESSION['toast_message'])): ?>
Swal.fire({ icon: '<?php echo $_SESSION['toast_type'] == 'success' ? 'success' : 'error'; ?>', title: '<?php echo $_SESSION['toast_type'] == 'success' ? 'Success!' : 'Error!'; ?>', html: '<?php echo htmlspecialchars($_SESSION['toast_message']); ?>', confirmButtonColor: '#3B9DB3', timer: 5000 });
<?php unset($_SESSION['toast_message']); unset($_SESSION['toast_type']); endif; ?>

// Edit Head Master using self-contained AJAX
function editHeadMaster(id) {
    $.ajax({
        url: 'school_admins.php?ajax_get_head_master=1&id=' + id,
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.error) { Swal.fire('Error!', data.error, 'error'); return; }
            $('#edit_admin_id').val(data.id);
            $('#edit_first_name').val(data.first_name);
            $('#edit_middle_name').val(data.middle_name);
            $('#edit_last_name').val(data.last_name);
            $('#edit_sex').val(data.sex);
            $('#edit_email').val(data.email);
            $('#edit_phone_number').val(data.phone_number);
            $('#edit_nida').val(data.nida);
            $('#edit_status').val(data.status);
            $('#edit_school_id').val(data.school_id);
            $('#edit_address').val(data.address);
            $('#editHeadMasterModal').modal('show');
        },
        error: function() { Swal.fire('Error!', 'Failed to load head master data', 'error'); }
    });
}

function resetPassword(id, name) {
    Swal.fire({ title: 'Reset Password?', html: `Are you sure you want to reset password for <strong>${name}</strong>?<br><br>Default password: <strong>School@<?php echo $current_year; ?></strong>`, icon: 'question', showCancelButton: true, confirmButtonColor: '#ffc107', confirmButtonText: 'Yes, reset it!' }).then((result) => { if (result.isConfirmed) window.location.href = 'school_admins.php?reset_password=1&id=' + id; });
}

function toggleStatus(id, action) {
    let title = action == 'activate' ? 'Activate Account' : 'Deactivate Account';
    let text = action == 'activate' ? 'Are you sure you want to activate this account?' : 'Are you sure you want to deactivate this account?';
    Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonColor: action == 'activate' ? '#28a745' : '#dc3545', confirmButtonText: action == 'activate' ? 'Yes, activate!' : 'Yes, deactivate!' }).then((result) => { if (result.isConfirmed) window.location.href = 'school_admins.php?toggle_status=' + action + '&id=' + id; });
}

function deleteHeadMaster(id, name) {
    Swal.fire({ title: 'Delete Head Master?', html: `<div style="text-align:left;"><p><strong>⚠️ WARNING: This action is IRREVERSIBLE!</strong></p><p>You are about to delete <strong>"${name}"</strong> permanently.</p><p class="text-danger">This cannot be undone!</p></div>`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Yes, delete!' }).then((result) => { if (result.isConfirmed) window.location.href = 'school_admins.php?delete=1&id=' + id; });
}
</script>

<?php include '../controller/footer.php'; ?>
</body>
</html>