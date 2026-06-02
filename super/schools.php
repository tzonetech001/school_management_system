<?php
// super/schools.php - Simplified Schools Management
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

// Handle Status Toggle (Activate/Suspend)
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

// Handle Delete School
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $school_id = (int)$_GET['id'];
    
    // Check if school exists
    $check_sql = "SELECT school_name FROM schools WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $school_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $school = $check_result->fetch_assoc();
    
    if ($school) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete related data
            $delete_students = "DELETE FROM students WHERE school_id = ?";
            $stmt = $conn->prepare($delete_students);
            $stmt->bind_param("i", $school_id);
            $stmt->execute();
            $stmt->close();
            
            $delete_admins = "DELETE FROM admins WHERE school_id = ?";
            $stmt = $conn->prepare($delete_admins);
            $stmt->bind_param("i", $school_id);
            $stmt->execute();
            $stmt->close();
            
            $delete_dorms = "DELETE FROM dormitories WHERE school_id = ?";
            $stmt = $conn->prepare($delete_dorms);
            $stmt->bind_param("i", $school_id);
            $stmt->execute();
            $stmt->close();
            
            $delete_exams = "DELETE FROM exam_types WHERE school_id = ?";
            $stmt = $conn->prepare($delete_exams);
            $stmt->bind_param("i", $school_id);
            $stmt->execute();
            $stmt->close();
            
            $delete_school = "DELETE FROM schools WHERE id = ?";
            $stmt = $conn->prepare($delete_school);
            $stmt->bind_param("i", $school_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            $_SESSION['toast_message'] = "School '" . htmlspecialchars($school['school_name']) . "' and all its data has been deleted!";
            $_SESSION['toast_type'] = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['toast_message'] = "Error deleting school: " . $e->getMessage();
            $_SESSION['toast_type'] = "error";
        }
    } else {
        $_SESSION['toast_message'] = "School not found!";
        $_SESSION['toast_type'] = "error";
    }
    
    $check_stmt->close();
    header("Location: schools.php");
    exit();
}

// Handle Add School
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

// Handle Edit School
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
    
    $update_sql = "UPDATE schools SET 
                   school_code = ?, school_name = ?, school_motto = ?, 
                   address = ?, phone = ?, email = ?, status = ?
                   WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssssssi", $school_code, $school_name, $school_motto, $address, $phone, $email, $status, $school_id);
    
    if ($stmt->execute()) {
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

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schools - Super Admin</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- DataTables -->
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
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .stats-card h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .stats-card p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
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
        .status-Expired { background: #fff3cd; color: #856404; }
        .status-Inactive { background: #e2e3e5; color: #383d41; }

        .action-btns .btn {
            padding: 4px 8px;
            margin: 0 2px;
            font-size: 0.75rem;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .modal-body {
            padding: 25px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            padding: 10px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(59, 157, 179, 0.25);
        }
        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            color: white;
            font-weight: 500;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3);
        }
    </style>
</head>
<body>

<?php include '../controller/header.php'; ?>
<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="container-fluid">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-building me-2"></i> Manage Schools</h2>
                    <p class="mb-0">View, add, edit, and manage all schools in the system</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addSchoolModal">
                        <i class="fas fa-plus-circle me-2"></i> Add New School
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <h2><?php echo $total_schools; ?></h2>
                    <p><i class="fas fa-school me-1"></i> Total Schools</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <h2 class="text-success"><?php echo $active_schools; ?></h2>
                    <p><i class="fas fa-check-circle me-1 text-success"></i> Active Schools</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <h2 class="text-warning"><?php echo $suspended_schools; ?></h2>
                    <p><i class="fas fa-pause-circle me-1 text-warning"></i> Suspended Schools</p>
                </div>
            </div>
        </div>

        <!-- Schools Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="schoolsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>School Code</th>
                                <th>School Name</th>
                                <th>Motto</th>
                                <th>Address</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schools as $school): ?>
                            <tr id="school-row-<?php echo $school['id']; ?>">
                                <td><?php echo $school['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($school['school_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                                <td><?php echo htmlspecialchars($school['school_motto'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars(substr($school['address'] ?? '-', 0, 50)); ?>...</td>
                                <td><?php echo htmlspecialchars($school['phone'] ?? '-'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $school['status']; ?>">
                                        <?php echo $school['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($school['created_at'])); ?></td>
                                <td class="action-btns">
                                    <button type="button" class="btn btn-sm btn-primary" 
                                            onclick="editSchool(<?php echo $school['id']; ?>)"
                                            title="Edit School">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($school['status'] == 'Active'): ?>
                                    <button type="button" class="btn btn-sm btn-warning" 
                                            onclick="toggleStatus(<?php echo $school['id']; ?>, 'suspend')"
                                            title="Suspend School">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-success" 
                                            onclick="toggleStatus(<?php echo $school['id']; ?>, 'activate')"
                                            title="Activate School">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="deleteSchool(<?php echo $school['id']; ?>, '<?php echo addslashes($school['school_name']); ?>')"
                                            title="Delete School">
                                        <i class="fas fa-trash-alt"></i>
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

<!-- ==================== ADD SCHOOL MODAL ==================== -->
<div class="modal fade" id="addSchoolModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New School</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">School Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="school_code" required 
                               placeholder="e.g., MVZ001" maxlength="20">
                        <small class="text-muted">Unique code for the school</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">School Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="school_name" required 
                               placeholder="e.g., Muyovozi High School">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">School Motto</label>
                        <input type="text" class="form-control" name="school_motto" 
                               placeholder="e.g., Education For Life">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2" 
                                  placeholder="Full school address"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone" 
                               placeholder="e.g., 255712345678">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" 
                               placeholder="school@example.com">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_school" class="btn btn-submit">
                        <i class="fas fa-save me-2"></i> Create School
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== EDIT SCHOOL MODAL ==================== -->
<div class="modal fade" id="editSchoolModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit School</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="editSchoolForm">
                <div class="modal-body">
                    <input type="hidden" name="school_id" id="edit_school_id">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_school" class="btn btn-submit">
                        <i class="fas fa-save me-2"></i> Update School
                    </button>
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
        order: [[0, 'desc']],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });
});

// Show toast message from session
<?php if (isset($_SESSION['toast_message'])): ?>
Swal.fire({
    icon: '<?php echo $_SESSION['toast_type'] == 'success' ? 'success' : 'error'; ?>',
    title: '<?php echo $_SESSION['toast_type'] == 'success' ? 'Success!' : 'Error!'; ?>',
    text: '<?php echo htmlspecialchars($_SESSION['toast_message']); ?>',
    confirmButtonColor: '#3B9DB3',
    timer: 3000,
    showConfirmButton: true,
    position: 'center'
});
<?php 
unset($_SESSION['toast_message']);
unset($_SESSION['toast_type']);
endif; 
?>

// Edit School Function
function editSchool(id) {
    $.ajax({
        url: 'get_school.php',
        type: 'POST',
        data: { id: id },
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
            $('#editSchoolModal').modal('show');
        },
        error: function() {
            Swal.fire('Error!', 'Failed to load school data', 'error');
        }
    });
}

// Toggle Status Function
function toggleStatus(id, action) {
    let title = action == 'suspend' ? 'Suspend School' : 'Activate School';
    let text = action == 'suspend' ? 'Are you sure you want to suspend this school?' : 'Are you sure you want to activate this school?';
    let confirmText = action == 'suspend' ? 'Yes, suspend it!' : 'Yes, activate it!';
    
    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: action == 'suspend' ? '#dc3545' : '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmText
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'schools.php?toggle_status=' + action + '&id=' + id;
        }
    });
}

// Delete School Function
function deleteSchool(id, name) {
    Swal.fire({
        title: 'Delete School?',
        html: `<div style="text-align: left;">
                    <p><strong>⚠️ WARNING: This action is IRREVERSIBLE!</strong></p>
                    <p>You are about to delete <strong>"${name}"</strong>.</p>
                    <p>This will permanently delete all data related to this school including:</p>
                    <ul>
                        <li>All students and their records</li>
                        <li>All staff and administrators</li>
                        <li>All dormitories and rooms</li>
                        <li>All exam results</li>
                        <li>All notifications and logs</li>
                    </ul>
                    <p class="text-danger"><strong>This action cannot be undone!</strong></p>
                </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete everything!',
        cancelButtonText: 'Cancel'
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