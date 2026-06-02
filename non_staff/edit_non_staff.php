<?php
// edit_non_staff.php - Edit Non-Staff Employee WITH SCHOOL ID FILTERING
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

// ========== GET CURRENT SCHOOL ID ==========
$school_query = "SELECT school_id FROM admins WHERE id = ?";
$stmt = $conn->prepare($school_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$school_result = $stmt->get_result();
$current_admin_data = $school_result->fetch_assoc();
$current_school_id = $current_admin_data['school_id'] ?? 1;

// Permission check (with school_id)
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

$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to edit employees.";
    header("Location: ../404.php");
    exit();
}

// Get employee ID
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($edit_id <= 0) {
    $_SESSION['error'] = "Invalid employee ID.";
    header("Location: non_staff.php");
    exit();
}

// Load theme settings (with school_id)
$theme_settings = [];
$query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = ? AND school_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $admin_id, $current_school_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $theme_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = ? AND school_id = ?";
$stmt = $conn->prepare($prefs_query);
$stmt->bind_param("ii", $admin_id, $current_school_id);
$stmt->execute();
$prefs_result = $stmt->get_result();
if ($prefs_result && mysqli_num_rows($prefs_result) > 0) {
    while ($row = mysqli_fetch_assoc($prefs_result)) {
        $preferences[$row['preference_key']] = $row['preference_value'];
    }
}

$default_colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
    'primary_light' => '#8bc5d6',
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

// Get employee data (with school_id verification)
$sql = "SELECT * FROM non_staff WHERE id = ? AND school_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $edit_id, $current_school_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows == 0) {
    $_SESSION['error'] = "Employee not found or you don't have permission to edit this employee.";
    header("Location: non_staff.php");
    exit();
}

$employee = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name'] ?? ''));
    $middle_name = mysqli_real_escape_string($conn, trim($_POST['middle_name'] ?? ''));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name'] ?? ''));
    $sex = mysqli_real_escape_string($conn, $_POST['sex'] ?? '');
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $phone_input = mysqli_real_escape_string($conn, trim($_POST['phone_number'] ?? ''));
    $phone_number = '255' . $phone_input;
    $nida = mysqli_real_escape_string($conn, trim($_POST['nida'] ?? ''));
    $position = mysqli_real_escape_string($conn, trim($_POST['position'] ?? ''));
    $department = mysqli_real_escape_string($conn, trim($_POST['department'] ?? ''));
    $employment_date = mysqli_real_escape_string($conn, $_POST['employment_date'] ?? '');
    $contract_type = mysqli_real_escape_string($conn, $_POST['contract_type'] ?? 'Permanent');
    $salary_scale = mysqli_real_escape_string($conn, trim($_POST['salary_scale'] ?? ''));
    $work_location = mysqli_real_escape_string($conn, trim($_POST['work_location'] ?? ''));
    $emergency_contact_name = mysqli_real_escape_string($conn, trim($_POST['emergency_contact_name'] ?? ''));
    $emergency_contact_phone = mysqli_real_escape_string($conn, trim($_POST['emergency_contact_phone'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $notes = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
    $status = isset($_POST['status']) ? 1 : 0;
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($sex) || empty($email) || empty($phone_input) || empty($position) || empty($employment_date)) {
        $error = "Please fill in all required fields.";
    }
    
    // Validate email
    if (empty($error) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    }
    
    // Validate phone number
    $phone_regex = '/^255\d{9}$/';
    if (empty($error) && !preg_match($phone_regex, $phone_number)) {
        $error = "Invalid phone number format. Must be 255 followed by 9 digits.";
    }
    
    // Validate NIDA if provided
    if (empty($error) && !empty($nida) && strlen($nida) !== 20) {
        $error = "NIDA number must be exactly 20 digits.";
    }
    
    // Check if email already exists for ANOTHER employee in SAME SCHOOL
    if (empty($error)) {
        $check_email_sql = "SELECT id FROM non_staff WHERE email = ? AND id != ? AND school_id = ?";
        $stmt = $conn->prepare($check_email_sql);
        $stmt->bind_param("sii", $email, $edit_id, $current_school_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Email already exists. Please use a different email.";
        }
    }
    
    // Check if phone already exists for ANOTHER employee in SAME SCHOOL
    if (empty($error)) {
        $check_phone_sql = "SELECT id FROM non_staff WHERE phone_number = ? AND id != ? AND school_id = ?";
        $stmt = $conn->prepare($check_phone_sql);
        $stmt->bind_param("sii", $phone_number, $edit_id, $current_school_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Phone number already exists. Please use a different phone number.";
        }
    }
    
    // Check if NIDA already exists for ANOTHER employee in SAME SCHOOL
    if (empty($error) && !empty($nida)) {
        $check_nida_sql = "SELECT id FROM non_staff WHERE nida = ? AND id != ? AND school_id = ?";
        $stmt = $conn->prepare($check_nida_sql);
        $stmt->bind_param("sii", $nida, $edit_id, $current_school_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "NIDA number already exists. Please use a different NIDA number.";
        }
    }
    
    if (empty($error)) {
        // Handle profile image upload
        $profile_image = $employee['profile_image'];
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Delete old image if exists
            if ($profile_image && file_exists($upload_dir . $profile_image)) {
                unlink($upload_dir . $profile_image);
            }
            
            $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = 'non_staff_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                $profile_image = $filename;
            }
        }
        
        // Update database (with school_id verification)
        if (empty($nida)) {
            $sql = "UPDATE non_staff SET first_name=?, middle_name=?, last_name=?, sex=?, email=?, phone_number=?, nida=NULL,
                    position=?, department=?, employment_date=?, contract_type=?, salary_scale=?, work_location=?,
                    emergency_contact_name=?, emergency_contact_phone=?, address=?, profile_image=?, status=?, notes=?
                    WHERE id=? AND school_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssssssssssssii", 
                $first_name, $middle_name, $last_name, $sex, $email, $phone_number,
                $position, $department, $employment_date, $contract_type, $salary_scale, $work_location,
                $emergency_contact_name, $emergency_contact_phone, $address, $profile_image, $status, $notes, $edit_id, $current_school_id);
        } else {
            $sql = "UPDATE non_staff SET first_name=?, middle_name=?, last_name=?, sex=?, email=?, phone_number=?, nida=?,
                    position=?, department=?, employment_date=?, contract_type=?, salary_scale=?, work_location=?,
                    emergency_contact_name=?, emergency_contact_phone=?, address=?, profile_image=?, status=?, notes=?
                    WHERE id=? AND school_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssssssssssssii", 
                $first_name, $middle_name, $last_name, $sex, $email, $phone_number, $nida,
                $position, $department, $employment_date, $contract_type, $salary_scale, $work_location,
                $emergency_contact_name, $emergency_contact_phone, $address, $profile_image, $status, $notes, $edit_id, $current_school_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Employee updated successfully!";
            header("Location: non_staff.php");
            exit();
        } else {
            $error = "Error updating employee: " . $conn->error;
        }
    }
}

// Get profile image URL
$profile_image_url = '../uploads/profiles/' . ($employee['profile_image'] ?: 'default.jpg');
if (!file_exists($profile_image_url) || empty($employee['profile_image'])) {
    $avatar_url = 'https://ui-avatars.com/api/?name=' . urlencode($employee['first_name'] . '+' . $employee['last_name']) . '&size=150&background=3B9DB3&color=fff&bold=true';
} else {
    $avatar_url = $profile_image_url;
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="page-title mb-0">
                <i class="fas fa-edit me-2" style="color: var(--primary-color, #3B9DB3);"></i> 
                Edit Employee: <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
            </h2>
            <a href="non_staff.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color, #3B9DB3), var(--primary-dark, #2d7c8f)); color: white;">
                <h5 class="mb-0"><i class="fas fa-address-card me-2"></i>Edit Employee Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center mb-4">
                        <img src="<?php echo $avatar_url; ?>" 
                             alt="Profile" 
                             class="rounded-circle mb-3"
                             style="width: 150px; height: 150px; object-fit: cover; border: 3px solid var(--primary-color);"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($employee['first_name'] . '+' . $employee['last_name']); ?>&size=150&background=3B9DB3&color=fff&bold=true'">
                        <div class="small text-muted">Employee ID: #<?php echo str_pad($employee['id'], 5, '0', STR_PAD_LEFT); ?></div>
                    </div>
                    
                    <div class="col-md-9">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name"
                                           value="<?php echo htmlspecialchars($employee['middle_name'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name"
                                           value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sex <span class="text-danger">*</span></label><br>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="sex" id="male" value="Male" 
                                               <?php echo ($employee['sex'] == 'Male') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="male">Male</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="sex" id="female" value="Female"
                                               <?php echo ($employee['sex'] == 'Female') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="female">Female</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">+255</span>
                                        <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                               value="<?php echo preg_replace('/^255/', '', $employee['phone_number']); ?>"
                                               maxlength="9" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="position" class="form-label">Position <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="position" name="position"
                                           value="<?php echo htmlspecialchars($employee['position']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department" name="department"
                                           value="<?php echo htmlspecialchars($employee['department'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="employment_date" class="form-label">Employment Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="employment_date" name="employment_date"
                                           value="<?php echo $employee['employment_date']; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="contract_type" class="form-label">Contract Type</label>
                                    <select class="form-select" id="contract_type" name="contract_type">
                                        <option value="Permanent" <?php echo ($employee['contract_type'] == 'Permanent') ? 'selected' : ''; ?>>Permanent</option>
                                        <option value="Contract" <?php echo ($employee['contract_type'] == 'Contract') ? 'selected' : ''; ?>>Contract</option>
                                        <option value="Temporary" <?php echo ($employee['contract_type'] == 'Temporary') ? 'selected' : ''; ?>>Temporary</option>
                                        <option value="Volunteer" <?php echo ($employee['contract_type'] == 'Volunteer') ? 'selected' : ''; ?>>Volunteer</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="status" id="status" value="1" 
                                               <?php echo $employee['status'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="status">Active Account</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="non_staff.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --primary-color: <?php echo $colors['primary']; ?>;
        --primary-dark: <?php echo $colors['primary_dark']; ?>;
        --font-size-base: <?php echo $font_size_value; ?>;
        --animation-duration: <?php echo $animation_duration; ?>;
    }
    
    body {
        font-size: var(--font-size-base);
        background: <?php echo $bg_style; ?>;
        background-size: <?php echo $bg_size; ?>;
        min-height: 100vh;
    }
</style>

<?php include '../controller/footer.php'; ?>