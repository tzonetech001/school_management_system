<?php
// non_staff.php - Non-Staff Employee Management
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'] ?? 0;

// ==================== LOAD THEME SETTINGS ====================
$theme_settings = [];
$query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
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

// ==================== PERMISSION CHECK ====================
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view non-staff employees.";
    header("Location: ../404.php");
    exit();
}

// ==================== HANDLE ACTIONS ====================

// Handle deletion
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    $delete_sql = "DELETE FROM non_staff WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Employee deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting employee: " . $conn->error;
    }
    header("Location: non_staff.php");
    exit();
}

// Handle status toggle
if (isset($_GET['toggle_status'])) {
    $id = mysqli_real_escape_string($conn, $_GET['toggle_status']);
    
    $status_sql = "UPDATE non_staff SET status = NOT status WHERE id = ?";
    $stmt = $conn->prepare($status_sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Employee status updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating status: " . $conn->error;
    }
    header("Location: non_staff.php");
    exit();
}

// ==================== GET ALL NON-STAFF EMPLOYEES ====================

$sql = "SELECT * FROM non_staff ORDER BY status DESC, first_name, last_name";
$result = mysqli_query($conn, $sql);
$employees = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $employees[] = $row;
    }
}

// Get statistics
$total_count = count($employees);
$active_count = 0;
$inactive_count = 0;
foreach ($employees as $emp) {
    if ($emp['status']) $active_count++;
    else $inactive_count++;
}

// Get distinct positions and departments for filters
$positions = [];
$departments = [];
$pos_result = mysqli_query($conn, "SELECT DISTINCT position FROM non_staff WHERE position IS NOT NULL ORDER BY position");
if ($pos_result) {
    while ($row = mysqli_fetch_assoc($pos_result)) {
        $positions[] = $row['position'];
    }
}
$dept_result = mysqli_query($conn, "SELECT DISTINCT department FROM non_staff WHERE department IS NOT NULL ORDER BY department");
if ($dept_result) {
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $row['department'];
    }
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="page-title mb-0">
                <i class="fas fa-users me-2" style="color: var(--primary-color, #3B9DB3);"></i> 
                Non-Staff Employee Management
            </h2>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="location.reload();">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
                <a href="register_non_staff.php" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Register New Employee
                </a>
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
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo $total_count; ?></div>
                    <div class="stats-label">Total Employees</div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stats-number"><?php echo $active_count; ?></div>
                    <div class="stats-label">Active Employees</div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="stats-number"><?php echo $inactive_count; ?></div>
                    <div class="stats-label">Inactive Employees</div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-primary"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by name, email, phone, or position...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="positionFilter" class="form-select">
                            <option value="">All Positions</option>
                            <?php foreach ($positions as $pos): ?>
                                <option value="<?php echo htmlspecialchars($pos); ?>"><?php echo htmlspecialchars($pos); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="departmentFilter" class="form-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employees Table -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color, #3B9DB3), var(--primary-dark, #2d7c8f)); color: white;">
                <h5 class="mb-0"><i class="fas fa-briefcase me-2"></i>All Non-Staff Employees</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="employeesTable">
                        <thead class="table-light">
                            <tr>
                                <th width="50">Photo</th>
                                <th>Full Name</th>
                                <th>Contact</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Employment Date</th>
                                <th width="80">Status</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fas fa-users fa-3x text-muted mb-3 d-block"></i>
                                        No employees found. <a href="register_non_staff.php">Register a new employee</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $employee): 
                                    $profile_image = '../uploads/profiles/' . ($employee['profile_image'] ?: 'default.jpg');
                                    if (!file_exists($profile_image) || empty($employee['profile_image'])) {
                                        $avatar_url = 'https://ui-avatars.com/api/?name=' . urlencode($employee['first_name'] . '+' . $employee['last_name']) . '&size=45&background=' . ltrim($colors['primary'], '#') . '&color=fff&bold=true';
                                    } else {
                                        $avatar_url = $profile_image;
                                    }
                                ?>
                                <tr data-status="<?php echo $employee['status']; ?>"
                                    data-position="<?php echo htmlspecialchars($employee['position']); ?>"
                                    data-department="<?php echo htmlspecialchars($employee['department']); ?>">
                                    <td>
                                        <img src="<?php echo $avatar_url; ?>" 
                                             alt="Profile" 
                                             class="rounded-circle"
                                             style="width: 45px; height: 45px; object-fit: cover;"
                                             onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($employee['first_name'] . '+' . $employee['last_name']); ?>&size=45&background=3B9DB3&color=fff&bold=true'">
                                    </td>
                                    <td>
                                        <strong>
                                            <?php 
                                            $fullName = htmlspecialchars($employee['first_name']);
                                            if (!empty($employee['middle_name'])) {
                                                $fullName .= ' ' . htmlspecialchars($employee['middle_name']);
                                            }
                                            $fullName .= ' ' . htmlspecialchars($employee['last_name']);
                                            echo $fullName;
                                            ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <div><i class="fas fa-envelope text-muted me-1"></i> <?php echo htmlspecialchars($employee['email']); ?></div>
                                        <div class="small text-muted"><i class="fas fa-phone text-muted me-1"></i> <?php echo htmlspecialchars($employee['phone_number']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($employee['position']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($employee['department'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($employee['department']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo date('M d, Y', strtotime($employee['employment_date'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $employee['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <i class="fas <?php echo $employee['status'] ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                                            <?php echo $employee['status'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-info view-employee" 
                                                    data-bs-toggle="modal" data-bs-target="#viewEmployeeModal"
                                                    data-employee-id="<?php echo $employee['id']; ?>"
                                                    data-employee-name="<?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="edit_non_staff.php?id=<?php echo $employee['id']; ?>" 
                                               class="btn btn-outline-warning"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger delete-employee" 
                                                    data-id="<?php echo $employee['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-<?php echo $employee['status'] ? 'secondary' : 'success'; ?> toggle-status-employee"
                                                    data-id="<?php echo $employee['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>"
                                                    data-status="<?php echo $employee['status'] ? 'Active' : 'Inactive'; ?>"
                                                    title="<?php echo $employee['status'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-power-off"></i>
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

<!-- View Employee Modal -->
<div class="modal fade" id="viewEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color, #3B9DB3), var(--primary-dark, #2d7c8f)); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-user-tie me-2"></i>
                    <span id="modalEmployeeName">Employee Details</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="employeeDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading employee details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                <h5 class="mb-3">Delete Employee?</h5>
                <p class="mb-2"><strong id="deleteEmployeeName"></strong></p>
                <p class="text-danger small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Status Toggle Modal -->
<div class="modal fade" id="statusToggleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-power-off me-2"></i>Change Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-user-cog fa-3x text-warning mb-3"></i>
                <h5 id="statusToggleTitle"></h5>
                <p class="mb-2"><strong id="statusEmployeeName"></strong></p>
                <p id="statusToggleMessage"></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmStatusToggle" class="btn btn-warning">
                    <i class="fas fa-check me-2"></i>Confirm
                </a>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

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

    .stats-card {
        background: var(--white, white);
        border-radius: 20px;
        padding: 25px 20px;
        text-align: center;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
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
        background: linear-gradient(90deg, var(--primary-color), var(--warning-color));
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(59,157,179,0.15);
    }

    .stats-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        color: white;
        font-size: 24px;
    }

    .stats-number {
        font-size: 28px;
        font-weight: 800;
        color: #2c3e50;
        line-height: 1.2;
    }

    .stats-label {
        font-size: 13px;
        color: #6c757d;
        font-weight: 500;
        margin-top: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table th {
        font-weight: 600;
        color: #2c3e50;
        background-color: #f8f9fa;
        border-bottom: 2px solid #e9ecef;
        padding: 12px 8px;
        font-size: 13px;
    }

    .table td {
        vertical-align: middle;
        padding: 12px 8px;
    }

    .btn-group-sm .btn {
        padding: 0.25rem 0.6rem;
        font-size: 0.75rem;
    }

    .btn-group {
        gap: 4px;
    }

    .btn-group .btn {
        border-radius: 8px !important;
        transition: all 0.2s ease;
    }

    .btn-group .btn:hover {
        transform: scale(1.05);
    }

    @media (max-width: 992px) {
        .stats-card { padding: 20px 15px; }
        .stats-icon { width: 50px; height: 50px; font-size: 20px; }
        .stats-number { font-size: 24px; }
    }

    @media (max-width: 768px) {
        .btn-group { flex-wrap: wrap; }
        .btn-group .btn { margin-bottom: 4px; flex: 1; }
        .table th, .table td { font-size: 12px; padding: 8px 4px; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    
    if (successMessage) {
        Swal.fire({
            title: 'Success!',
            text: successMessage.getAttribute('data-message'),
            icon: 'success',
            confirmButtonColor: '#3B9DB3',
            timer: 3000,
            timerProgressBar: true
        });
    }
    
    if (errorMessage) {
        Swal.fire({
            title: 'Error!',
            text: errorMessage.getAttribute('data-message'),
            icon: 'error',
            confirmButtonColor: '#d33'
        });
    }
});

// Search and Filter
document.getElementById('searchInput').addEventListener('keyup', filterTable);
document.getElementById('positionFilter').addEventListener('change', filterTable);
document.getElementById('departmentFilter').addEventListener('change', filterTable);
document.getElementById('statusFilter').addEventListener('change', filterTable);

function filterTable() {
    const searchValue = document.getElementById('searchInput').value.toLowerCase();
    const position = document.getElementById('positionFilter').value;
    const department = document.getElementById('departmentFilter').value;
    const status = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('#employeesTable tbody tr');
    
    rows.forEach(row => {
        if (row.cells.length < 8) return;
        
        const text = row.textContent.toLowerCase();
        const rowPosition = row.getAttribute('data-position') || '';
        const rowDepartment = row.getAttribute('data-department') || '';
        const rowStatus = row.getAttribute('data-status');
        
        const matchSearch = text.includes(searchValue);
        const matchPosition = !position || rowPosition === position;
        const matchDepartment = !department || rowDepartment === department;
        const matchStatus = !status || rowStatus === status;
        
        row.style.display = (matchSearch && matchPosition && matchDepartment && matchStatus) ? '' : 'none';
    });
}

// Delete confirmation
document.querySelectorAll('.delete-employee').forEach(button => {
    button.addEventListener('click', function() {
        const empId = this.dataset.id;
        const empName = this.dataset.name;
        
        document.getElementById('deleteEmployeeName').textContent = empName;
        document.getElementById('confirmDelete').href = `non_staff.php?delete=${empId}`;
        
        new bootstrap.Modal(document.getElementById('deleteConfirmationModal')).show();
    });
});

// Status toggle confirmation
document.querySelectorAll('.toggle-status-employee').forEach(button => {
    button.addEventListener('click', function() {
        const empId = this.dataset.id;
        const empName = this.dataset.name;
        const currentStatus = this.dataset.status;
        const action = currentStatus === 'Active' ? 'Deactivate' : 'Activate';
        
        document.getElementById('statusEmployeeName').textContent = empName;
        document.getElementById('statusToggleTitle').textContent = `${action} Employee`;
        document.getElementById('statusToggleMessage').innerHTML = 
            `Are you sure you want to ${action.toLowerCase()} <strong>${empName}</strong>?`;
        
        document.getElementById('confirmStatusToggle').href = `non_staff.php?toggle_status=${empId}`;
        
        new bootstrap.Modal(document.getElementById('statusToggleModal')).show();
    });
});

// View employee details
document.querySelectorAll('.view-employee').forEach(button => {
    button.addEventListener('click', function() {
        const empId = this.dataset.employeeId;
        const empName = this.dataset.employeeName;
        
        document.getElementById('modalEmployeeName').textContent = empName;
        
        document.getElementById('employeeDetails').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Loading employee details for ${empName}...</p>
            </div>
        `;
        
        fetch(`view_non_staff.php?id=${empId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('employeeDetails').innerHTML = data;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('employeeDetails').innerHTML = `
                    <div class="alert alert-danger m-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading employee details. Please try again.
                    </div>
                `;
            });
    });
});
</script>

<?php include '../controller/footer.php'; ?>