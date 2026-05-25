<?php
// view_admin.php - AJAX endpoint to fetch specific admin details
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo '<div class="alert alert-danger">Unauthorized access.</div>';
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid request. No teacher ID provided.</div>';
    exit();
}

$admin_id = intval($_GET['id']);
$current_admin_id = $_SESSION['admin_id'];

// Get current user's roles for permission check
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $current_admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

// Check if user has permission to view (Head Master or Second Master)
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    http_response_code(403);
    echo '<div class="alert alert-danger">You don\'t have permission to view teacher details.</div>';
    exit();
}

// Fetch the specific admin details
$sql = "SELECT a.*, 
        GROUP_CONCAT(DISTINCT CONCAT(ar.role_name, IF(ara.is_primary = 1, ' (Primary)', '')) 
        ORDER BY ara.is_primary DESC, ar.role_name SEPARATOR ', ') as roles_with_type,
        MAX(ara.is_primary) as has_primary,
        GROUP_CONCAT(DISTINCT CASE WHEN ara.is_primary = 1 THEN ar.role_name END) as primary_role
        FROM admins a
        LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
        LEFT JOIN admin_roles ar ON ara.role_id = ar.id
        WHERE a.id = ?
        GROUP BY a.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows == 0) {
    echo '<div class="alert alert-danger">Teacher not found.</div>';
    exit();
}

$admin = $result->fetch_assoc();

// Get lock status
function getAdminLockInfoForView($conn, $admin_id) {
    $sql = "SELECT locked_until, failed_login_attempts FROM admins WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

$lock_info = getAdminLockInfoForView($conn, $admin_id);
$is_locked = false;
$remaining_minutes = 0;

if ($lock_info && $lock_info['locked_until'] !== null && $lock_info['locked_until'] != '') {
    $locked_until = strtotime($lock_info['locked_until']);
    $now = time();
    if ($locked_until > $now) {
        $is_locked = true;
        $remaining_minutes = ceil(($locked_until - $now) / 60);
    }
}

// Get profile image
$profile_image = '../uploads/profiles/' . ($admin['profile_image'] ?: 'default.jpg');
if (!file_exists($profile_image) || empty($admin['profile_image'])) {
    $avatar_url = 'https://ui-avatars.com/api/?name=' . urlencode($admin['first_name'] . '+' . $admin['last_name']) . '&size=150&background=3B9DB3&color=fff&bold=true';
} else {
    $avatar_url = $profile_image;
}

// Load theme colors for consistent styling
$theme_settings = [];
$query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $current_admin_id";
$result_theme = mysqli_query($conn, $query);
if ($result_theme && mysqli_num_rows($result_theme) > 0) {
    while ($row = mysqli_fetch_assoc($result_theme)) {
        if ($row !== null && isset($row['setting_key']) && isset($row['setting_value'])) {
            $theme_settings[$row['setting_key']] = $row['setting_value'];
        }
    }
}

$default_colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
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
?>

<style>
    .admin-detail-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
    }
    
    .admin-header {
        background: linear-gradient(135deg, <?php echo $colors['primary']; ?>, <?php echo $colors['primary_dark']; ?>);
        padding: 20px;
        color: white;
    }
    
    .admin-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .detail-section {
        padding: 20px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .detail-section:last-child {
        border-bottom: none;
    }
    
    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: <?php echo $colors['primary']; ?>;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid <?php echo $colors['primary']; ?>;
    }
    
    .detail-row {
        display: flex;
        margin-bottom: 12px;
    }
    
    .detail-label {
        width: 130px;
        font-weight: 500;
        color: #6c757d;
        flex-shrink: 0;
    }
    
    .detail-value {
        flex: 1;
        color: #2c3e50;
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .status-active {
        background: <?php echo $colors['success']; ?>;
        color: white;
    }
    
    .status-inactive {
        background: <?php echo $colors['danger']; ?>;
        color: white;
    }
    
    .status-locked {
        background: <?php echo $colors['danger']; ?>;
        color: white;
    }
    
    .status-unlocked {
        background: <?php echo $colors['success']; ?>;
        color: white;
    }
    
    .role-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.8rem;
        margin: 2px 4px;
        background: <?php echo $colors['primary']; ?>;
        color: white;
    }
    
    .role-badge-primary {
        background: <?php echo $colors['primary']; ?>;
    }
    
    .role-badge-secondary {
        background: #6c757d;
    }
    
    @media (max-width: 576px) {
        .detail-row {
            flex-direction: column;
        }
        
        .detail-label {
            width: 100%;
            margin-bottom: 5px;
        }
        
        .admin-avatar {
            width: 100px;
            height: 100px;
        }
    }
</style>

<div class="admin-detail-card">
    <div class="admin-header text-center">
        <img src="<?php echo $avatar_url; ?>" 
             alt="Profile" 
             class="admin-avatar mb-3"
             onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($admin['first_name'] . '+' . $admin['last_name']); ?>&size=150&background=3B9DB3&color=fff&bold=true'">
        <h4 class="mb-1"><?php echo htmlspecialchars($admin['first_name'] . ' ' . ($admin['middle_name'] ?? '') . ' ' . $admin['last_name']); ?></h4>
        <p class="mb-0">
            <span class="status-badge <?php echo $admin['status'] ? 'status-active' : 'status-inactive'; ?>">
                <i class="fas <?php echo $admin['status'] ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                <?php echo $admin['status'] ? 'Active' : 'Inactive'; ?>
            </span>
            <span class="status-badge <?php echo $is_locked ? 'status-locked' : 'status-unlocked'; ?> ms-2">
                <i class="fas <?php echo $is_locked ? 'fa-lock' : 'fa-unlock-alt'; ?> me-1"></i>
                <?php echo $is_locked ? "Locked ({$remaining_minutes} min left)" : 'Unlocked'; ?>
            </span>
        </p>
    </div>
    
    <div class="detail-section">
        <h6 class="section-title">
            <i class="fas fa-user-circle me-2"></i>Personal Information
        </h6>
        <div class="detail-row">
            <div class="detail-label">Full Name:</div>
            <div class="detail-value">
                <?php 
                $fullName = htmlspecialchars($admin['first_name']);
                if (!empty($admin['middle_name'])) {
                    $fullName .= ' ' . htmlspecialchars($admin['middle_name']);
                }
                $fullName .= ' ' . htmlspecialchars($admin['last_name']);
                echo $fullName;
                ?>
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Sex:</div>
            <div class="detail-value"><?php echo htmlspecialchars($admin['sex']); ?></div>
        </div>
        <?php if (!empty($admin['check_number'])): ?>
        <div class="detail-row">
            <div class="detail-label">Check Number:</div>
            <div class="detail-value"><?php echo htmlspecialchars($admin['check_number']); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($admin['nida'])): ?>
        <div class="detail-row">
            <div class="detail-label">NIDA Number:</div>
            <div class="detail-value"><?php echo htmlspecialchars($admin['nida']); ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="detail-section">
        <h6 class="section-title">
            <i class="fas fa-address-book me-2"></i>Contact Information
        </h6>
        <div class="detail-row">
            <div class="detail-label">Email:</div>
            <div class="detail-value">
                <i class="fas fa-envelope me-1 text-muted"></i>
                <?php echo htmlspecialchars($admin['email']); ?>
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Phone Number:</div>
            <div class="detail-value">
                <i class="fas fa-phone me-1 text-muted"></i>
                <?php echo htmlspecialchars($admin['phone_number']); ?>
            </div>
        </div>
    </div>
    
    <div class="detail-section">
        <h6 class="section-title">
            <i class="fas fa-user-tag me-2"></i>Roles & Permissions
        </h6>
        <div class="detail-row">
            <div class="detail-label">Assigned Roles:</div>
            <div class="detail-value">
                <?php 
                if (!empty($admin['roles_with_type'])) {
                    $roles = explode(', ', $admin['roles_with_type']);
                    foreach ($roles as $role) {
                        $isPrimary = strpos($role, '(Primary)') !== false;
                        echo '<span class="role-badge ' . ($isPrimary ? 'role-badge-primary' : 'role-badge-secondary') . '">';
                        echo htmlspecialchars($role);
                        if ($isPrimary) {
                            echo ' <i class="fas fa-star"></i>';
                        }
                        echo '</span>';
                    }
                } else {
                    echo '<span class="text-muted">No roles assigned</span>';
                }
                ?>
            </div>
        </div>
        <?php if (!empty($admin['primary_role'])): ?>
        <div class="detail-row">
            <div class="detail-label">Primary Role:</div>
            <div class="detail-value">
                <span class="role-badge role-badge-primary">
                    <i class="fas fa-star me-1"></i>
                    <?php echo htmlspecialchars($admin['primary_role']); ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="detail-section">
        <h6 class="section-title">
            <i class="fas fa-clock me-2"></i>Account Information
        </h6>
        <div class="detail-row">
            <div class="detail-label">Member Since:</div>
            <div class="detail-value">
                <i class="fas fa-calendar-alt me-1 text-muted"></i>
                <?php echo date('F j, Y', strtotime($admin['created_at'])); ?>
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Last Updated:</div>
            <div class="detail-value">
                <i class="fas fa-edit me-1 text-muted"></i>
                <?php echo $admin['updated_at'] ? date('F j, Y', strtotime($admin['updated_at'])) : 'Never'; ?>
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Failed Attempts:</div>
            <div class="detail-value">
                <i class="fas fa-shield-alt me-1 text-muted"></i>
                <?php echo $lock_info['failed_login_attempts'] ?? 0; ?> / 5
            </div>
        </div>
    </div>
</div>