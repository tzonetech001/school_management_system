<?php
// register_non_staff.php - Register New Non-Staff Employee
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

// Permission check
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
    $_SESSION['error'] = "You don't have permission to register employees.";
    header("Location: ../404.php");
    exit();
}

// Load theme settings
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
    
    // Check if email already exists
    if (empty($error)) {
        $check_email_sql = "SELECT id FROM non_staff WHERE email = ?";
        $stmt = $conn->prepare($check_email_sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Email already exists. Please use a different email.";
        }
    }
    
    // Check if phone already exists
    if (empty($error)) {
        $check_phone_sql = "SELECT id FROM non_staff WHERE phone_number = ?";
        $stmt = $conn->prepare($check_phone_sql);
        $stmt->bind_param("s", $phone_number);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Phone number already exists. Please use a different phone number.";
        }
    }
    
    // Check if NIDA already exists
    if (empty($error) && !empty($nida)) {
        $check_nida_sql = "SELECT id FROM non_staff WHERE nida = ?";
        $stmt = $conn->prepare($check_nida_sql);
        $stmt->bind_param("s", $nida);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "NIDA number already exists. Please use a different NIDA number.";
        }
    }
    
    if (empty($error)) {
        // Handle profile image upload
        $profile_image = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = 'non_staff_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                $profile_image = $filename;
            }
        }
        
        // Insert into database
        if (empty($nida)) {
            $sql = "INSERT INTO non_staff (first_name, middle_name, last_name, sex, email, phone_number, nida, 
                    position, department, employment_date, contract_type, salary_scale, work_location,
                    emergency_contact_name, emergency_contact_phone, address, profile_image, status, notes)
                    VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssssssssssi", 
                $first_name, $middle_name, $last_name, $sex, $email, $phone_number,
                $position, $department, $employment_date, $contract_type, $salary_scale, $work_location,
                $emergency_contact_name, $emergency_contact_phone, $address, $profile_image, $status, $notes);
        } else {
            $sql = "INSERT INTO non_staff (first_name, middle_name, last_name, sex, email, phone_number, nida, 
                    position, department, employment_date, contract_type, salary_scale, work_location,
                    emergency_contact_name, emergency_contact_phone, address, profile_image, status, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssssssssssssi", 
                $first_name, $middle_name, $last_name, $sex, $email, $phone_number, $nida,
                $position, $department, $employment_date, $contract_type, $salary_scale, $work_location,
                $emergency_contact_name, $emergency_contact_phone, $address, $profile_image, $status, $notes);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Employee registered successfully!";
            header("Location: non_staff.php");
            exit();
        } else {
            $error = "Error registering employee: " . $conn->error;
        }
    }
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="page-title mb-0">
                <i class="fas fa-user-plus me-2" style="color: var(--primary-color, #3B9DB3);"></i> 
                Register Non-Staff Employee
            </h2>
            <a href="non_staff.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
        </div>

        <!-- Error/Success Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color, #3B9DB3), var(--primary-dark, #2d7c8f)); color: white;">
                <h5 class="mb-0"><i class="fas fa-address-card me-2"></i>Employee Registration Form</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <h6 class="mb-3" style="color: var(--primary-color);">
                                <i class="fas fa-user-circle me-2"></i>Personal Information
                            </h6>
                            
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name">
                            </div>
                            
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Sex <span class="text-danger">*</span></label><br>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="sex" id="male" value="Male" required>
                                    <label class="form-check-label" for="male">Male</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="sex" id="female" value="Female">
                                    <label class="form-check-label" for="female">Female</label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Profile Image</label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                                <small class="form-text text-muted">Accepted formats: JPG, PNG, GIF (Max 2MB)</small>
                            </div>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="col-md-6">
                            <h6 class="mb-3" style="color: var(--primary-color);">
                                <i class="fas fa-address-book me-2"></i>Contact Information
                            </h6>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">+255</span>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                           placeholder="712345678" maxlength="9" required>
                                </div>
                                <small class="form-text text-muted">Enter 9 digits after 255</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="nida" class="form-label">NIDA Number (Optional)</label>
                                <input type="text" class="form-control" id="nida" name="nida" maxlength="20" pattern="\d*">
                                <small class="form-text text-muted">Enter exactly 20 digits</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Physical Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Employment Information -->
                    <h6 class="mb-3" style="color: var(--primary-color);">
                        <i class="fas fa-briefcase me-2"></i>Employment Information
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="position" class="form-label">Position <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="position" name="position" required>
                            <small class="form-text text-muted">e.g., Cleaner, Security Guard, Cook, Driver</small>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department">
                            <small class="form-text text-muted">e.g., Maintenance, Kitchen, Security</small>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="employment_date" class="form-label">Employment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="employment_date" name="employment_date" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="contract_type" class="form-label">Contract Type</label>
                            <select class="form-select" id="contract_type" name="contract_type">
                                <option value="Permanent">Permanent</option>
                                <option value="Contract">Contract</option>
                                <option value="Temporary">Temporary</option>
                                <option value="Volunteer">Volunteer</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="salary_scale" class="form-label">Salary Scale</label>
                            <input type="text" class="form-control" id="salary_scale" name="salary_scale">
                            <small class="form-text text-muted">e.g., TGS C, Grade 3, etc.</small>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="work_location" class="form-label">Work Location</label>
                            <input type="text" class="form-control" id="work_location" name="work_location">
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Emergency Contact -->
                    <h6 class="mb-3" style="color: var(--primary-color);">
                        <i class="fas fa-ambulance me-2"></i>Emergency Contact
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                            <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="status" id="status" value="1" checked>
                                <label class="form-check-label" for="status">Active Account</label>
                            </div>
                            <small class="form-text text-muted">Inactive employees cannot be assigned tasks</small>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="non_staff.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Register Employee
                        </button>
                    </div>
                </form>
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
    <?php endif; ?>

    .form-label {
        font-weight: 500;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(59, 157, 179, 0.25);
    }
</style>

<?php include '../controller/footer.php'; ?>