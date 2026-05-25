<?php
// edit_non_staff.php - Edit Non-Staff Employee
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

// Load theme settings (same as register_non_staff.php)
// ... [Include theme loading code same as register_non_staff.php] ...

// Get employee data
$sql = "SELECT * FROM non_staff WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $edit_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows == 0) {
    $_SESSION['error'] = "Employee not found.";
    header("Location: non_staff.php");
    exit();
}

$employee = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs (same as register)
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
    
    // Validation (same as register)
    // ... [validation code] ...
    
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
        
        // Update database
        if (empty($nida)) {
            $sql = "UPDATE non_staff SET first_name=?, middle_name=?, last_name=?, sex=?, email=?, phone_number=?, nida=NULL,
                    position=?, department=?, employment_date=?, contract_type=?, salary_scale=?, work_location=?,
                    emergency_contact_name=?, emergency_contact_phone=?, address=?, profile_image=?, status=?, notes=?
                    WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssssssssssssi", 
                $first_name, $middle_name, $last_name, $sex, $email, $phone_number,
                $position, $department, $employment_date, $contract_type, $salary_scale, $work_location,
                $emergency_contact_name, $emergency_contact_phone, $address, $profile_image, $status, $notes, $edit_id);
        } else {
            $sql = "UPDATE non_staff SET first_name=?, middle_name=?, last_name=?, sex=?, email=?, phone_number=?, nida=?,
                    position=?, department=?, employment_date=?, contract_type=?, salary_scale=?, work_location=?,
                    emergency_contact_name=?, emergency_contact_phone=?, address=?, profile_image=?, status=?, notes=?
                    WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssssssssssssi", 
                $first_name, $middle_name, $last_name, $sex, $email, $phone_number, $nida,
                $position, $department, $employment_date, $contract_type, $salary_scale, $work_location,
                $emergency_contact_name, $emergency_contact_phone, $address, $profile_image, $status, $notes, $edit_id);
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
       ;
    }
</style>

<?php include '../controller/footer.php'; ?>