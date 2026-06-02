<?php
// admins.php - Staff Management with School ID Filtering
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

// Get current admin's school_id - HII NI MUHIMU SANA
$school_query = "SELECT school_id FROM admins WHERE id = ?";
$stmt = $conn->prepare($school_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$school_result = $stmt->get_result();
$current_admin = $school_result->fetch_assoc();
$current_school_id = $current_admin['school_id'] ?? 1;

// ==================== LOAD THEME SETTINGS ====================
// Load user's theme settings (filter by school_id as well)
$theme_settings = [];
$query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id AND school_id = $current_school_id";
$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row !== null && isset($row['setting_key']) && isset($row['setting_value'])) {
            $theme_settings[$row['setting_key']] = $row['setting_value'];
        }
    }
}

// Load preferences (filter by school_id)
$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id AND school_id = $current_school_id";
$prefs_result = mysqli_query($conn, $prefs_query);
if ($prefs_result && mysqli_num_rows($prefs_result) > 0) {
    while ($row = mysqli_fetch_assoc($prefs_result)) {
        if ($row !== null && isset($row['preference_key']) && isset($row['preference_value'])) {
            $preferences[$row['preference_key']] = $row['preference_value'];
        }
    }
}

// Default theme colors (same as before)
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
    'info' => '#17a2b8',
    'coral' => '#FF7F50',
    'forest_green' => '#2E7D32',
    'lime_green' => '#63E07E',
    'sky_blue' => '#66d9ff',
    'aqua_blue' => '#4dd2ff'
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

$animation_speeds = [
    'slow' => '0.5s',
    'normal' => '0.3s',
    'fast' => '0.15s'
];
$animation_duration = isset($animation_speeds[$animation_speed]) ? $animation_speeds[$animation_speed] : '0.3s';

$font_size_map = [
    '10' => '10px',
    '12' => '12px',
    '14' => '14px',
    '16' => '16px',
    '18' => '18px'
];
$font_size_value = isset($font_size_map[$font_size]) ? $font_size_map[$font_size] : '16px';

$background_colors = [
    'gray' => '#e9ecef',
    'eye_care' => '#c7e9c0',
    'milk' => '#fdf5e6',
    'dark_light' => '#2d2d2d'
];

if ($bg_option === 'image') {
    $bg_style = "linear-gradient(rgba(255,255,255,{$bg_opacity}), rgba(255,255,255,{$bg_opacity})), url('../muyovozi.png') no-repeat center center fixed";
    $bg_size = 'cover';
} else {
    $bg_color = isset($background_colors[$bg_option]) ? $background_colors[$bg_option] : '#e9ecef';
    $bg_style = $bg_color;
    $bg_size = 'auto';
}

// ==================== PERMISSION CHECK ====================
// Get current user's roles (filter by school_id)
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

// Check if user has Head Master (1) or Second Master (2) role
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view staff members.";
    header("Location: ../404.php");
    exit();
}

// ==================== ACCOUNT LOCK FUNCTIONS (with school_id check) ====================

function isAdminAccountLocked($conn, $admin_id, $school_id) {
    $sql = "SELECT locked_until, failed_login_attempts FROM admins WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $admin_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if (!$admin) return false;
    
    if ($admin['locked_until'] !== null && $admin['locked_until'] != '') {
        $locked_until = strtotime($admin['locked_until']);
        $now = time();
        
        if ($locked_until <= $now) {
            unlockAdminAccount($conn, $admin_id, $school_id);
            return false;
        }
        return true;
    }
    return false;
}

function getAdminLockInfo($conn, $admin_id, $school_id) {
    $sql = "SELECT locked_until, failed_login_attempts, last_login_attempt FROM admins WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $admin_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getRemainingLockTime($conn, $admin_id, $school_id) {
    $sql = "SELECT locked_until FROM admins WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $admin_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if ($admin && $admin['locked_until'] !== null) {
        $expiry = new DateTime($admin['locked_until']);
        $now = new DateTime();
        if ($expiry > $now) {
            $interval = $now->diff($expiry);
            return ($interval->h * 60) + $interval->i;
        }
    }
    return 0;
}

function unlockAdminAccount($conn, $admin_id, $school_id) {
    $sql = "UPDATE admins SET 
            failed_login_attempts = 0, 
            locked_until = NULL,
            last_login_attempt = NULL 
            WHERE id = ? AND school_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $admin_id, $school_id);
    
    if ($stmt->execute()) {
        $email_sql = "SELECT email FROM admins WHERE id = ? AND school_id = ?";
        $stmt = $conn->prepare($email_sql);
        $stmt->bind_param("ii", $admin_id, $school_id);
        $stmt->execute();
        $email_result = $stmt->get_result();
        $admin = $email_result->fetch_assoc();
        
        if ($admin) {
            $delete_sql = "DELETE FROM admin_login_attempts WHERE identifier = ? AND school_id = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("si", $admin['email'], $school_id);
            $stmt->execute();
        }
        return true;
    }
    return false;
}

function unlockAllExpiredAccounts($conn, $school_id) {
    $sql = "UPDATE admins SET 
            failed_login_attempts = 0, 
            locked_until = NULL 
            WHERE locked_until IS NOT NULL 
            AND locked_until <= NOW()
            AND school_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    return $stmt->affected_rows;
}

// ==================== MAINTENANCE FUNCTIONS (with school_id) ====================

function returnItemsForStaff($conn, $staff_id, $admin_id, $school_id) {
    mysqli_begin_transaction($conn);
    $returned_count = 0;
    
    try {
        $assignments_sql = "SELECT msa.*, mi.item_code, a.first_name, a.last_name 
                           FROM maintenance_staff_assignments msa
                           JOIN maintenance_items mi ON msa.item_id = mi.id
                           JOIN admins a ON msa.staff_id = a.id
                           WHERE msa.staff_id = ? AND msa.status = 'active' AND msa.school_id = ?";
        
        $stmt = $conn->prepare($assignments_sql);
        $stmt->bind_param("ii", $staff_id, $school_id);
        $stmt->execute();
        $assignments_result = $stmt->get_result();
        
        while ($assignment = $assignments_result->fetch_assoc()) {
            $update_sql = "UPDATE maintenance_staff_assignments SET 
                          status = 'returned',
                          return_date = CURDATE(),
                          return_condition = 'good',
                          return_notes = 'Auto-returned: Staff deleted'
                          WHERE id = ? AND school_id = ?";
            
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $assignment['id'], $school_id);
            $update_stmt->execute();
            
            $update_item_sql = "UPDATE maintenance_items SET status = 'available' WHERE id = ? AND school_id = ?";
            $update_item_stmt = $conn->prepare($update_item_sql);
            $update_item_stmt->bind_param("ii", $assignment['item_id'], $school_id);
            $update_item_stmt->execute();
            
            $returned_count++;
        }
        
        mysqli_commit($conn);
        return $returned_count;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return 0;
    }
}

// ==================== GET ALL ADMINS FOR CURRENT SCHOOL ====================
// MUHIMU SANA: Filter by current school_id
$sql = "SELECT a.*, 
        GROUP_CONCAT(DISTINCT ar.role_name ORDER BY ara.is_primary DESC, ar.role_name SEPARATOR ', ') as roles,
        MAX(ara.is_primary) as has_primary,
        GROUP_CONCAT(DISTINCT CASE WHEN ara.is_primary = 1 THEN ar.role_name END) as primary_role
        FROM admins a
        LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
        LEFT JOIN admin_roles ar ON ara.role_id = ar.id
        WHERE a.school_id = ?
        GROUP BY a.id
        ORDER BY a.status DESC, a.first_name, a.last_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_school_id);
$stmt->execute();
$result = $stmt->get_result();

$admins = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $admins[] = $row;
    }
}

// ==================== HANDLE ACTIONS (with school_id check) ====================

// Handle admin deletion
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    $current_admin_id = $_SESSION['admin_id'] ?? 0;
    
    // First verify this admin belongs to current school
    $verify_sql = "SELECT id FROM admins WHERE id = ? AND school_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $id, $current_school_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows == 0) {
        $_SESSION['error'] = "Unauthorized: Cannot delete admin from another school.";
        header("Location: admins.php");
        exit();
    }
    
    if ($id == $current_admin_id) {
        $_SESSION['error'] = "You cannot delete your own account!";
        header("Location: admins.php");
        exit();
    }
    
    $items_returned = returnItemsForStaff($conn, $id, $current_admin_id, $current_school_id);
    
    $delete_roles_sql = "DELETE FROM admin_role_assignments WHERE admin_id = ?";
    $stmt = $conn->prepare($delete_roles_sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    $delete_sql = "DELETE FROM admins WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("ii", $id, $current_school_id);
    
    if ($stmt->execute()) {
        $message = "Teacher deleted successfully!";
        if ($items_returned > 0) {
            $message .= " $items_returned assigned item(s) were returned to inventory.";
        }
        $_SESSION['success'] = $message;
    } else {
        $_SESSION['error'] = "Error deleting teacher: " . $conn->error;
    }
    header("Location: admins.php");
    exit();
}

// Handle status toggle (with school_id verification)
if (isset($_GET['toggle_status'])) {
    $id = mysqli_real_escape_string($conn, $_GET['toggle_status']);
    
    // Verify admin belongs to current school
    $verify_sql = "SELECT id FROM admins WHERE id = ? AND school_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $id, $current_school_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows == 0) {
        $_SESSION['error'] = "Unauthorized: Cannot modify admin from another school.";
        header("Location: admins.php");
        exit();
    }
    
    $status_sql = "UPDATE admins SET status = NOT status WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($status_sql);
    $stmt->bind_param("ii", $id, $current_school_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Teacher status updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating status: " . $conn->error;
    }
    header("Location: admins.php");
    exit();
}

// Handle password reset (with school_id verification)
if (isset($_GET['reset_password'])) {
    $id = intval($_GET['reset_password']);
    
    if ($id <= 0) {
        $_SESSION['error'] = "Invalid admin ID";
        header("Location: admins.php");
        exit();
    }
    
    // Verify admin belongs to current school
    $verify_sql = "SELECT id FROM admins WHERE id = ? AND school_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $id, $current_school_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows == 0) {
        $_SESSION['error'] = "Unauthorized: Cannot reset password for admin from another school.";
        header("Location: admins.php");
        exit();
    }
    
    $current_year = date('Y');
    $temp_password = "school@" . $current_year;
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE id = ? AND school_id = ?");
    $stmt->bind_param("sii", $hashed_password, $id, $current_school_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Password reset successfully! New password: " . $temp_password;
    } else {
        $_SESSION['error'] = "Error resetting password: " . $stmt->error;
    }
    header("Location: admins.php");
    exit();
}

// Handle unlock admin account (with school_id verification)
if (isset($_GET['unlock_account'])) {
    $id = mysqli_real_escape_string($conn, $_GET['unlock_account']);
    $current_admin_id = $_SESSION['admin_id'] ?? 0;
    
    // Verify admin belongs to current school
    $verify_sql = "SELECT id FROM admins WHERE id = ? AND school_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $id, $current_school_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows == 0) {
        $_SESSION['error'] = "Unauthorized: Cannot unlock admin from another school.";
        header("Location: admins.php");
        exit();
    }
    
    if ($id == $current_admin_id) {
        $_SESSION['error'] = "You cannot unlock your own account! Please contact another administrator.";
        header("Location: admins.php");
        exit();
    }
    
    if (unlockAdminAccount($conn, $id, $current_school_id)) {
        $_SESSION['success'] = "Teacher account unlocked successfully!";
    } else {
        $_SESSION['error'] = "Error unlocking teacher account.";
    }
    header("Location: admins.php");
    exit();
}

// Handle auto-unlock all expired accounts (only for current school)
if (isset($_GET['auto_unlock_all'])) {
    $affected = unlockAllExpiredAccounts($conn, $current_school_id);
    $_SESSION['success'] = "$affected expired locked accounts have been auto-unlocked.";
    header("Location: admins.php");
    exit();
}

// Helper function to check lock status with school_id
function isAdminAccountLockedWithSchool($conn, $admin_id, $school_id) {
    $sql = "SELECT locked_until FROM admins WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $admin_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if (!$admin) return false;
    
    if ($admin['locked_until'] !== null && $admin['locked_until'] != '') {
        $locked_until = strtotime($admin['locked_until']);
        $now = time();
        if ($locked_until > $now) {
            return true;
        }
    }
    return false;
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<!-- REST OF THE HTML REMAINS THE SAME, but update the PHP loops to use the new function -->
<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="page-title mb-0">
                <i class="fas fa-users me-2" style="color: var(--primary-color, #3B9DB3);"></i> 
                Teacher Management
            </h2>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info" onclick="location.reload();">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cog me-2"></i>Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="register_admin.php"><i class="fas fa-user-plus me-2"></i>New Staff</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="report_admin.php"><i class="fas fa-download me-2"></i>Export List</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="admins.php?auto_unlock_all=1" onclick="return confirm('Auto-unlock all expired locked accounts?');">
                            <i class="fas fa-lock-open me-2"></i>Auto-Unlock Expired
                        </a></li>
                    </ul>
                </div>
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
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo count($admins); ?></div>
                    <div class="stats-label">Total Teachers</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stats-number">
                        <?php 
                        $active_count = 0;
                        foreach ($admins as $a) {
                            if ($a['status']) $active_count++;
                        }
                        echo $active_count;
                        ?>
                    </div>
                    <div class="stats-label">Active Teachers</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="stats-number">
                        <?php 
                        $inactive_count = 0;
                        foreach ($admins as $a) {
                            if (!$a['status']) $inactive_count++;
                        }
                        echo $inactive_count;
                        ?>
                    </div>
                    <div class="stats-label">Inactive Teachers</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="stats-number">
                        <?php 
                        $locked_count = 0;
                        foreach ($admins as $a) {
                            if (isAdminAccountLockedWithSchool($conn, $a['id'], $current_school_id)) $locked_count++;
                        }
                        echo $locked_count;
                        ?>
                    </div>
                    <div class="stats-label">Locked Accounts</div>
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
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by name, email, phone, or roles...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="Active">✅ Active</option>
                            <option value="Inactive">❌ Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="lockFilter" class="form-select">
                            <option value="">All Accounts</option>
                            <option value="locked">🔒 Locked Accounts</option>
                            <option value="unlocked">🔓 Unlocked Accounts</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-warning w-100" onclick="filterLockedOnly()">
                            <i class="fas fa-clock me-2"></i>Show Locked
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Teachers Table -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color, #3B9DB3), var(--primary-dark, #2d7c8f)); color: white;">
                <h5 class="mb-0"><i class="fas fa-chalkboard-user me-2"></i>All Staff Members</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="adminsTable">
                        <thead class="table-light">
                            <tr>
                                <th width="50">Photo</th>
                                <th>Full Name</th>
                                <th>Contact</th>
                                <th>Roles</th>
                                <th width="80">Status</th>
                                <th width="100">Account</th>
                                <th width="120">Lock Info</th>
                                <th width="180">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($admins)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fas fa-users fa-3x text-muted mb-3 d-block"></i>
                                        No teachers found. <a href="register_admin.php">Add a new teacher</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($admins as $admin): 
                                    $is_locked = isAdminAccountLockedWithSchool($conn, $admin['id'], $current_school_id);
                                    $lock_info = getAdminLockInfo($conn, $admin['id'], $current_school_id);
                                    $remaining_minutes = getRemainingLockTime($conn, $admin['id'], $current_school_id);
                                    
                                    $profile_image = '../uploads/profiles/' . ($admin['profile_image'] ?: 'default.jpg');
                                    if (!file_exists($profile_image) || empty($admin['profile_image'])) {
                                        $avatar_url = 'https://ui-avatars.com/api/?name=' . urlencode($admin['first_name'] . '+' . $admin['last_name']) . '&size=45&background=' . ltrim($colors['primary'], '#') . '&color=fff&bold=true';
                                    } else {
                                        $avatar_url = $profile_image;
                                    }
                                ?>
                                <tr data-lock-status="<?php echo $is_locked ? 'locked' : 'unlocked'; ?>"
                                    data-status="<?php echo $admin['status'] ? 'Active' : 'Inactive'; ?>"
                                    data-admin-id="<?php echo $admin['id']; ?>">
                                    <td>
                                        <img src="<?php echo $avatar_url; ?>" 
                                             alt="Profile" 
                                             class="rounded-circle"
                                             style="width: 45px; height: 45px; object-fit: cover;"
                                             onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($admin['first_name'] . '+' . $admin['last_name']); ?>&size=45&background=3B9DB3&color=fff&bold=true'">
                                    </td>
                                    <td>
                                        <strong>
                                            <?php 
                                            $fullName = htmlspecialchars($admin['first_name']);
                                            if (!empty($admin['middle_name'])) {
                                                $fullName .= ' ' . htmlspecialchars($admin['middle_name']);
                                            }
                                            $fullName .= ' ' . htmlspecialchars($admin['last_name']);
                                            echo $fullName;
                                            ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <div><i class="fas fa-envelope text-muted me-1"></i> <?php echo htmlspecialchars($admin['email']); ?></div>
                                        <div class="small text-muted"><i class="fas fa-phone text-muted me-1"></i> <?php echo htmlspecialchars($admin['phone_number']); ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                        $roles = explode(', ', $admin['roles']);
                                        foreach ($roles as $role):
                                            if (!empty($role)):
                                                $isPrimary = ($admin['primary_role'] == $role);
                                        ?>
                                            <span class="badge <?php echo $isPrimary ? 'bg-primary' : 'bg-secondary'; ?> me-1 mb-1">
                                                <?php echo htmlspecialchars($role); ?>
                                                <?php if ($isPrimary): ?>
                                                    <i class="fas fa-star ms-1"></i>
                                                <?php endif; ?>
                                            </span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $admin['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <i class="fas <?php echo $admin['status'] ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                                            <?php echo $admin['status'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($is_locked): ?>
                                            <span class="badge bg-danger" title="Locked for 30 minutes">
                                                <i class="fas fa-lock me-1"></i>Locked
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-unlock-alt me-1"></i>Unlocked
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_locked): ?>
                                            <div class="small">
                                                <span class="text-danger">
                                                    <i class="fas fa-hourglass-half me-1"></i>
                                                    <?php echo $remaining_minutes; ?> min left
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    Attempts: <?php echo $lock_info['failed_login_attempts'] ?? 0; ?>/5
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <div class="small text-muted">
                                                <i class="fas fa-shield-alt text-success me-1"></i>
                                                Attempts: <?php echo $lock_info['failed_login_attempts'] ?? 0; ?>/5
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-info view-admin" 
                                                    data-bs-toggle="modal" data-bs-target="#viewAdminModal"
                                                    data-admin-id="<?php echo $admin['id']; ?>"
                                                    data-admin-name="<?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="edit_admin.php?id=<?php echo $admin['id']; ?>" 
                                               class="btn btn-outline-warning"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($is_locked): ?>
                                                <a href="admins.php?unlock_account=<?php echo $admin['id']; ?>" 
                                                   class="btn btn-outline-success"
                                                   title="Unlock Account (30-min lock)"
                                                   onclick="return confirm('Are you sure you want to unlock this teacher account?');">
                                                    <i class="fas fa-unlock-alt"></i>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-secondary" disabled title="Account is unlocked">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-dark reset-password-admin" 
                                                    data-id="<?php echo $admin['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>"
                                                    title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger delete-admin" 
                                                    data-id="<?php echo $admin['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-<?php echo $admin['status'] ? 'secondary' : 'success'; ?> toggle-status-admin"
                                                    data-id="<?php echo $admin['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>"
                                                    data-status="<?php echo $admin['status'] ? 'Active' : 'Inactive'; ?>"
                                                    title="<?php echo $admin['status'] ? 'Deactivate' : 'Activate'; ?>">
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

<!-- View Admin Modal -->
<div class="modal fade" id="viewAdminModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color, #3B9DB3), var(--primary-dark, #2d7c8f)); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-user-tie me-2"></i>
                    <span id="modalAdminName">Teacher Details</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="adminDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading teacher details...</p>
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
                <h5 class="mb-3">Delete Teacher?</h5>
                <p class="mb-2"><strong id="deleteAdminName"></strong></p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> All assigned items will be returned to inventory.
                </div>
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
                <p class="mb-2"><strong id="statusAdminName"></strong></p>
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

<!-- Password Reset Modal -->
<div class="modal fade" id="passwordResetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-lock fa-3x text-dark mb-3"></i>
                <h5 class="mb-3">Reset password for?</h5>
                <p class="mb-2"><strong id="resetAdminName"></strong></p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    New temporary password: <strong>school@<?php echo date('Y'); ?></strong>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmPasswordReset" class="btn btn-dark">
                    <i class="fas fa-key me-2"></i>Reset Password
                </a>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<style>
    /* Same styles as before - keep unchanged */
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

    /* Statistics Cards */
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

    /* Table Styles */
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

    /* Button Group */
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

    /* Responsive */
    @media (max-width: 992px) {
        .stats-card {
            padding: 20px 15px;
        }
        
        .stats-icon {
            width: 50px;
            height: 50px;
            font-size: 20px;
        }
        
        .stats-number {
            font-size: 24px;
        }
    }

    @media (max-width: 768px) {
        .btn-group {
            flex-wrap: wrap;
        }
        
        .btn-group .btn {
            margin-bottom: 4px;
            flex: 1;
        }
        
        .table th, .table td {
            font-size: 12px;
            padding: 8px 4px;
        }
    }

    @media (max-width: 576px) {
        .stats-card {
            margin-bottom: 15px;
        }
        
        .stats-number {
            font-size: 20px;
        }
    }
</style>

<script>
// Same JavaScript as before - keep unchanged
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

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const rows = document.querySelectorAll('#adminsTable tbody tr');
    
    rows.forEach(row => {
        if (row.cells.length < 8) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
});

// Filter functionality
document.getElementById('statusFilter').addEventListener('change', filterTable);
document.getElementById('lockFilter').addEventListener('change', filterTable);

function filterTable() {
    const status = document.getElementById('statusFilter').value;
    const lock = document.getElementById('lockFilter').value;
    const rows = document.querySelectorAll('#adminsTable tbody tr');
    
    rows.forEach(row => {
        if (row.cells.length < 8) return;
        
        const rowStatus = row.getAttribute('data-status');
        const rowLock = row.getAttribute('data-lock-status');
        
        const matchStatus = !status || rowStatus === status;
        const matchLock = !lock || rowLock === lock;
        
        row.style.display = (matchStatus && matchLock) ? '' : 'none';
    });
}

function filterLockedOnly() {
    document.getElementById('lockFilter').value = 'locked';
    filterTable();
}

// Delete confirmation
const deleteButtons = document.querySelectorAll('.delete-admin');
deleteButtons.forEach(button => {
    button.addEventListener('click', function() {
        const adminId = this.dataset.id;
        const adminName = this.dataset.name;
        
        document.getElementById('deleteAdminName').textContent = adminName;
        document.getElementById('confirmDelete').href = `admins.php?delete=${adminId}`;
        
        new bootstrap.Modal(document.getElementById('deleteConfirmationModal')).show();
    });
});

// Status toggle confirmation
const toggleButtons = document.querySelectorAll('.toggle-status-admin');
toggleButtons.forEach(button => {
    button.addEventListener('click', function() {
        const adminId = this.dataset.id;
        const adminName = this.dataset.name;
        const currentStatus = this.dataset.status;
        const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
        const action = currentStatus === 'Active' ? 'Deactivate' : 'Activate';
        
        document.getElementById('statusAdminName').textContent = adminName;
        document.getElementById('statusToggleTitle').textContent = `${action} Teacher`;
        document.getElementById('statusToggleMessage').innerHTML = 
            `Are you sure you want to ${action.toLowerCase()} <strong>${adminName}</strong>?<br>
            <small class="text-muted">Current status: <span class="badge ${currentStatus === 'Active' ? 'bg-success' : 'bg-danger'}">${currentStatus}</span></small>`;
        
        document.getElementById('confirmStatusToggle').href = `admins.php?toggle_status=${adminId}`;
        
        new bootstrap.Modal(document.getElementById('statusToggleModal')).show();
    });
});

// Password reset confirmation
const resetButtons = document.querySelectorAll('.reset-password-admin');
resetButtons.forEach(button => {
    button.addEventListener('click', function() {
        const adminId = this.dataset.id;
        const adminName = this.dataset.name;
        
        document.getElementById('resetAdminName').textContent = adminName;
        document.getElementById('confirmPasswordReset').href = `admins.php?reset_password=${adminId}`;
        
        new bootstrap.Modal(document.getElementById('passwordResetModal')).show();
    });
});

// Load admin details for view modal - FIXED to fetch specific admin
const viewButtons = document.querySelectorAll('.view-admin');
viewButtons.forEach(button => {
    button.addEventListener('click', function() {
        const adminId = this.dataset.adminId;
        const adminName = this.dataset.adminName;
        
        document.getElementById('modalAdminName').textContent = adminName;
        
        document.getElementById('adminDetails').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading teacher details for ${adminName}...</p>
            </div>
        `;
        
        fetch(`view_admin.php?id=${adminId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                document.getElementById('adminDetails').innerHTML = data;
            })
            .catch(error => {
                console.error('Error fetching admin details:', error);
                document.getElementById('adminDetails').innerHTML = `
                    <div class="alert alert-danger m-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading teacher details. Please try again.
                    </div>
                `;
            });
    });
});
</script>

<?php include '../controller/footer.php'; ?>