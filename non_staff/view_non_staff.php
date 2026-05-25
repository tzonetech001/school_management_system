<?php
// view_non_staff.php - AJAX endpoint for employee details
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo '<div class="alert alert-danger">Unauthorized access.</div>';
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit();
}

$employee_id = intval($_GET['id']);

$sql = "SELECT * FROM non_staff WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows == 0) {
    echo '<div class="alert alert-danger">Employee not found.</div>';
    exit();
}

$employee = $result->fetch_assoc();

// Get profile image
$profile_image = '../uploads/profiles/' . ($employee['profile_image'] ?: 'default.jpg');
if (!file_exists($profile_image) || empty($employee['profile_image'])) {
    $avatar_url = 'https://ui-avatars.com/api/?name=' . urlencode($employee['first_name'] . '+' . $employee['last_name']) . '&size=150&background=3B9DB3&color=fff&bold=true';
} else {
    $avatar_url = $profile_image;
}

// Load theme colors for styling
$admin_id = $_SESSION['admin_id'];
$theme_settings = [];
$query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
$result_theme = mysqli_query($conn, $query);
if ($result_theme && mysqli_num_rows($result_theme) > 0) {
    while ($row = mysqli_fetch_assoc($result_theme)) {
        $theme_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$primary_color = $theme_settings['primary'] ?? '#3B9DB3';
$primary_dark = $theme_settings['primary_dark'] ?? '#2d7c8f';
?>

<style>
    .employee-detail-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
    }
    
    .employee-header {
        background: linear-gradient(135deg, <?php echo $primary_color; ?>, <?php echo $primary_dark; ?>);
        padding: 20px;
        color: white;
    }
    
    .employee-avatar {
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
        color: <?php echo $primary_color; ?>;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid <?php echo $primary_color; ?>;
    }
    
    .detail-row {
        display: flex;
        margin-bottom: 12px;
    }
    
    .detail-label {
        width: 140px;
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
        background: #28a745;
        color: white;
    }
    
    .status-inactive {
        background: #dc3545;
        color: white;
    }
    
    @media (max-width: 576px) {
        .detail-row {
            flex-direction: column;
        }
        .detail-label {
            width: 100%;
            margin-bottom: 5px;
        }
        .employee-avatar {
            width: 100px;
            height: 100px;
        }
    }
</style>

<div class="employee-detail-card">
    <div class="employee-header text-center">
        <img src="<?php echo $avatar_url; ?>" alt="Profile" class="employee-avatar mb-3"
             onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($employee['first_name'] . '+' . $employee['last_name']); ?>&size=150&background=3B9DB3&color=fff&bold=true'">
        <h4 class="mb-1"><?php echo htmlspecialchars($employee['first_name'] . ' ' . ($employee['middle_name'] ?? '') . ' ' . $employee['last_name']); ?></h4>
        <p>
            <span class="status-badge <?php echo $employee['status'] ? 'status-active' : 'status-inactive'; ?>">
                <i class="fas <?php echo $employee['status'] ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                <?php echo $employee['status'] ? 'Active' : 'Inactive'; ?>
            </span>
        </p>
    </div>
    
    <div class="detail-section">
        <h6 class="section-title"><i class="fas fa-user-circle me-2"></i>Personal Information</h6>
        <div class="detail-row">
            <div class="detail-label">Full Name:</div>
            <div class="detail-value"><?php echo htmlspecialchars($employee['first_name'] . ' ' . ($employee['middle_name'] ?? '') . ' ' . $employee['last_name']); ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Sex:</div>
            <div class="detail-value"><?php echo htmlspecialchars($employee['sex']); ?></div>
        </div>
        <?php if (!empty($employee['nida'])): ?>
        <div class="detail-row">
            <div class="detail-label">NIDA Number:</div>
            <div class="detail-value"><?php echo htmlspecialchars($employee['nida']); ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="detail-section">
        <h6 class="section-title"><i class="fas fa-address-book me-2"></i>Contact Information</h6>
        <div class="detail-row">
            <div class="detail-label">Email:</div>
            <div class="detail-value"><?php echo htmlspecialchars($employee['email']); ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Phone Number:</div>
            <div class="detail-value"><?php echo htmlspecialchars($employee['phone_number']); ?></div>
        </div>
        <?php if (!empty($employee['address'])): ?>
        <div class="detail-row">
            <div class="detail-label">Address:</div>
            <div class="detail-value"><?php echo nl2br(htmlspecialchars($employee['address'])); ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="detail-section">
        <h6 class="section-title"><i class="fas fa-briefcase me-2"></i>Employment Information</h6>
        <div class="detail-row">
            <div class="detail-label">Position:</div>
            <div class="detail-value"><span class="badge bg-info"><?php echo htmlspecialchars($employee['position']); ?></span></div>
        </div>
        <?php if (!empty($employee['department'])): ?>
        <div class="detail-row">
            <div class="detail-label">Department:</div>
            <div class="detail-value"><?php echo htmlspecialchars($employee['department']); ?></div>
        </div>
        <?php endif; ?>
        <div class="detail-row">
            <div class="detail-label">Employment Date:</div>
            <div class="detail-value"><?php echo date('F j, Y', strtotime($employee['employment_date'])); ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Contract Type:</div>
            <div class="detail-value"><?php echo htmlspecialchars($employee['contract_type']); ?></div>
        </div>
        <?php if (!empty($employee['salary_scale'])): ?>
        <div class="detail-row">
            <div class="detail-label">Salary Scale:</div>
            <div class="detail-value"><?php echo htmlspecialchars($employee['salary_scale']); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($employee['work_location'])): ?>
        <div class="detail-row">
            <div class="detail-label">Work Location:</div>
            <div class="detail-value"><?php echo htmlspecialchars($employee['work_location']); ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($employee['emergency_contact_name']) || !empty($employee['emergency_contact_phone'])): ?>
    <div class="detail-section">
        <h6 class="section-title"><i class="fas fa-ambulance me-2"></i>Emergency Contact</h6>
        <?php if (!empty($employee['emergency_contact_name'])): ?>
        <div class="detail-row">
            <div class="detail-label">Contact Name:</div>
            <div class="detail-value"><?php echo htmlspecialchars($employee['emergency_contact_name']); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($employee['emergency_contact_phone'])): ?>
        <div class="detail-row">
            <div class="detail-label">Contact Phone:</div>
            <div class="detail-value"><?php echo htmlspecialchars($employee['emergency_contact_phone']); ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($employee['notes'])): ?>
    <div class="detail-section">
        <h6 class="section-title"><i class="fas fa-sticky-note me-2"></i>Additional Notes</h6>
        <div class="detail-value"><?php echo nl2br(htmlspecialchars($employee['notes'])); ?></div>
    </div>
    <?php endif; ?>
    
    <div class="detail-section">
        <h6 class="section-title"><i class="fas fa-clock me-2"></i>Record Information</h6>
        <div class="detail-row">
            <div class="detail-label">Created:</div>
            <div class="detail-value"><?php echo date('F j, Y g:i A', strtotime($employee['created_at'])); ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Last Updated:</div>
            <div class="detail-value"><?php echo date('F j, Y g:i A', strtotime($employee['updated_at'])); ?></div>
        </div>
    </div>
</div>