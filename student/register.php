<?php
// edit_admin.php
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user has permission (Head Master or Second Master only)
$admin_id = $_SESSION['admin_id'] ?? 0;

// Get current user's roles
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

// Check if user has Head Master (1) or Second Master (2) role
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 3) { // Head Master or Second Master
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view page you need.";
    header("Location:  ../404.php");
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

$student = [];
$edit_mode = false;

// Check if we're editing an existing student
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $id = mysqli_real_escape_string($conn, $_GET['edit']);
    $sql = "SELECT * FROM students WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $student = mysqli_fetch_assoc($result);
    } else {
        $_SESSION['error'] = "Student not found!";
        header("Location: students.php");
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect all form data
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $second_name = mysqli_real_escape_string($conn, $_POST['second_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $sex = mysqli_real_escape_string($conn, $_POST['sex']);
    $combination = mysqli_real_escape_string($conn, $_POST['combination']);
    $date_of_birth = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    
    $date_of_admission = mysqli_real_escape_string($conn, $_POST['date_of_admission']);
    $admission_number = mysqli_real_escape_string($conn, $_POST['admission_number']);
    $class = mysqli_real_escape_string($conn, $_POST['class']);
    $citizenship = mysqli_real_escape_string($conn, $_POST['citizenship']);
    $place_of_birth = mysqli_real_escape_string($conn, $_POST['place_of_birth']);
    
    $parent_name = mysqli_real_escape_string($conn, $_POST['parent_name']);
    $parent_phone = mysqli_real_escape_string($conn, $_POST['parent_phone']);
    $parent_occupation = mysqli_real_escape_string($conn, $_POST['parent_occupation']);
    $parent_residence = mysqli_real_escape_string($conn, $_POST['parent_residence']);
    
    $former_school = mysqli_real_escape_string($conn, $_POST['former_school']);
    $school_transferred_to = mysqli_real_escape_string($conn, $_POST['school_transferred_to']);
    $date_leaving_school = !empty($_POST['date_leaving_school']) ? mysqli_real_escape_string($conn, $_POST['date_leaving_school']) : 'NULL';
    $school_transferred_from = mysqli_real_escape_string($conn, $_POST['school_transferred_from']);
    
    // Validate and format phone number
    if (!empty($parent_phone)) {
        // Remove any non-digit characters
        $parent_phone = preg_replace('/\D/', '', $parent_phone);
        
        // Ensure it starts with 255 and has exactly 12 digits total
        if (strlen($parent_phone) == 9) {
            // If user entered 9 digits, prepend 255
            $parent_phone = '255' . $parent_phone;
        }
        
        // Final validation: must be 12 digits starting with 255
        if (!preg_match('/^255\d{9}$/', $parent_phone)) {
            $_SESSION['error'] = "Phone number must start with 255 followed by 9 digits (12 digits total)";
            header("Location: register.php" . ($edit_mode ? "?edit=" . $student['id'] : ""));
            exit();
        }
    }
    
    // Check if admission number already exists for the SAME class
    $check_admission_sql = "SELECT id FROM students WHERE admission_number = '$admission_number' AND class = '$class'";
    if ($edit_mode) {
        $check_admission_sql .= " AND id != " . $student['id'];
    }
    $check_result = mysqli_query($conn, $check_admission_sql);
    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['error'] = "Admission number already exists for this class!";
        header("Location: register.php" . ($edit_mode ? "?edit=" . $student['id'] : ""));
        exit();
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        if ($edit_mode) {
            // Update existing student
            $sql = "UPDATE students SET 
                    first_name = '$first_name',
                    second_name = '$second_name',
                    last_name = '$last_name',
                    sex = '$sex',
                    combination = '$combination',
                    date_of_birth = '$date_of_birth',
                    date_of_admission = '$date_of_admission',
                    admission_number = '$admission_number',
                    class = '$class',
                    citizenship = '$citizenship',
                    place_of_birth = '$place_of_birth',
                    parent_name = '$parent_name',
                    parent_phone = '$parent_phone',
                    parent_occupation = '$parent_occupation',
                    parent_residence = '$parent_residence',
                    former_school = '$former_school',
                    school_transferred_to = '$school_transferred_to',
                    date_leaving_school = $date_leaving_school,
                    school_transferred_from = '$school_transferred_from'
                    WHERE id = " . $student['id'];
            
            if (!mysqli_query($conn, $sql)) {
                throw new Exception("Error updating student: " . mysqli_error($conn));
            }
        } else {
            // Hash the parent phone number as initial password
            $hashed_password = password_hash($parent_phone, PASSWORD_DEFAULT);
            
            // Insert new student with password
            $sql = "INSERT INTO students (
                    first_name, second_name, last_name, sex, combination, 
                    date_of_birth, date_of_admission, admission_number, class, 
                    citizenship, place_of_birth, parent_name, parent_phone, 
                    parent_occupation, parent_residence, former_school, 
                    school_transferred_to, date_leaving_school, school_transferred_from,
                    password
                    ) VALUES (
                    '$first_name', '$second_name', '$last_name', '$sex', '$combination',
                    '$date_of_birth', '$date_of_admission', '$admission_number', '$class',
                    '$citizenship', '$place_of_birth', '$parent_name', '$parent_phone',
                    '$parent_occupation', '$parent_residence', '$former_school',
                    '$school_transferred_to', $date_leaving_school, '$school_transferred_from',
                    '$hashed_password'
                    )";
            
            if (!mysqli_query($conn, $sql)) {
                throw new Exception("Error registering student: " . mysqli_error($conn));
            }
            
            // Get the newly inserted student ID
            $new_student_id = mysqli_insert_id($conn);
            
            // Log the student creation with password info
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $log_sql = "INSERT INTO admin_logs (admin_id, action, description, ip_address) 
                        VALUES ($admin_id, 'Student Registered', 
                        'Registered student: $first_name $last_name (Admission: $admission_number) with default password as parent phone', 
                        '" . $_SERVER['REMOTE_ADDR'] . "')";
            mysqli_query($conn, $log_sql);
        }
        
        // REGENERATE ALL INDEX NUMBERS for entire database with female first ordering
        regenerateAllIndexNumbers($conn);
        
        // Commit transaction
        mysqli_commit($conn);
        
        $message = $edit_mode ? "Student updated successfully! Index numbers regenerated." : 
                               "Student registered successfully! Default password is parent's phone number. Index numbers regenerated.";
        
        $_SESSION['success'] = $message;
        header("Location: students.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
        header("Location: register.php" . ($edit_mode ? "?edit=" . $student['id'] : ""));
        exit();
    }
}

function regenerateAllIndexNumbers($conn) {
    // Define combination order
    $combination_order = ['HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'];
    
    // Process Form Five - continuous numbering across all combinations with female first
    $form_five_index = 1; // Start from 0501 for Form Five
    
    foreach ($combination_order as $combination) {
        // UPDATED: Get female students first, then male, within each combination
        $form_five_sql = "SELECT * FROM students 
                         WHERE class = 'Form Five' 
                         AND combination = '$combination'
                         AND is_leaver = FALSE
                         ORDER BY 
                             CASE WHEN sex = 'Female' THEN 1 ELSE 2 END,  -- Female first
                             first_name, 
                             last_name";
        
        $form_five_result = mysqli_query($conn, $form_five_sql);
        
        if (!$form_five_result) {
            throw new Exception("Error fetching Form Five $combination students: " . mysqli_error($conn));
        }
        
        while ($student = mysqli_fetch_assoc($form_five_result)) {
            $new_index = 'S5098-' . str_pad(($form_five_index + 500), 4, '0', STR_PAD_LEFT);
           
            // Update the student's index number
            $update_sql = "UPDATE students SET index_number = '$new_index' WHERE id = " . $student['id'];
            
            if (!mysqli_query($conn, $update_sql)) {
                throw new Exception("Error updating index number for student ID " . $student['id'] . ": " . mysqli_error($conn));
            }
            
            $form_five_index++;
        }
    }
    
    // Process Form Six - continuous numbering across all combinations with female first
    $form_six_index = 1; // Start from 0501 for Form Six
    
    foreach ($combination_order as $combination) {
        // UPDATED: Get female students first, then male, within each combination
        $form_six_sql = "SELECT * FROM students 
                        WHERE class = 'Form Six' 
                        AND combination = '$combination'
                        AND is_leaver = FALSE
                        ORDER BY 
                            CASE WHEN sex = 'Female' THEN 1 ELSE 2 END,  -- Female first
                            first_name, 
                            last_name";
        
        $form_six_result = mysqli_query($conn, $form_six_sql);
        
        if (!$form_six_result) {
            throw new Exception("Error fetching Form Six $combination students: " . mysqli_error($conn));
        }
        
        while ($student = mysqli_fetch_assoc($form_six_result)) {
            $new_index = 'S5098-' . str_pad(($form_six_index + 500), 4, '0', STR_PAD_LEFT);
            
            // Update the student's index number
            $update_sql = "UPDATE students SET index_number = '$new_index' WHERE id = " . $student['id'];
            
            if (!mysqli_query($conn, $update_sql)) {
                throw new Exception("Error updating index number for student ID " . $student['id'] . ": " . mysqli_error($conn));
            }
            
            $form_six_index++;
        }
    }
    
    return true;
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>


<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title"><?php echo $edit_mode ? 'Edit Student' : 'Student Registration'; ?></h2>
             <div class="dropdown d-md-block d-none">
                <button class="btn btn-primary dropdown-toggle" type="button" id="actionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-2"></i>Actions
                </button>
                <ul class="dropdown-menu" aria-labelledby="actionsDropdown">
                    <li><a class="dropdown-item" href="register.php"><i class="fas fa-user-plus me-2"></i>New Student</a></li>
                    <li><a class="dropdown-item" href="students.php"><i  class="fas fa-users me-2"></i>Manage students</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="leavers.php"><i class="fas fa-user-graduate me-2"></i>View Leavers/Graduates</a></li>
                </ul>
            </div>
            <!-- Mobile Actions Button -->
            <div class="dropdown d-md-none">
                <button class="btn btn-primary" type="button" id="mobileActionsBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileActionsBtn">
                    <li><a class="dropdown-item" href="register.php"><i class="fas fa-user-plus me-2"></i>New Student</a></li>
                    <li><a class="dropdown-item" href="students.php"><i  class="fas fa-users me-2"></i>Manage students</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="leavers.php"><i class="fas fa-user-graduate me-2"></i>View Leavers/Graduates</a></li>
                </ul>
            </div>
        </div>

        <!-- Display Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Info Alert for New Registrations -->
        <?php if (!$edit_mode): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Note:</strong> New students will have their parent's phone number as the default password. 
            Students can change their password after first login.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Registration Form with Steps -->
        <form id="registrationForm" method="POST" action="">
            <div class="card watermark-card">
                <div class="card-header" style=" background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);">
                    <ul class="nav nav-pills card-header-pills" id="formTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="step1-tab" data-bs-toggle="pill" data-bs-target="#step1" type="button" role="tab">
                                <i class="fas fa-user-circle me-2"></i>Step 1: Personal Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="step2-tab" data-bs-toggle="pill" data-bs-target="#step2" type="button" role="tab">
                                <i class="fas fa-school me-2"></i>Step 2: Admission Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="step3-tab" data-bs-toggle="pill" data-bs-target="#step3" type="button" role="tab">
                                <i class="fas fa-users me-2"></i>Step 3: Parent/Guardian
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="step4-tab" data-bs-toggle="pill" data-bs-target="#step4" type="button" role="tab">
                                <i class="fas fa-graduation-cap me-2"></i>Step 4: Previous School
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body">
                    <div class="tab-content" id="formTabsContent">
                        <!-- Step 1: Personal Information -->
                        <div class="tab-pane fade show active" id="step1" role="tabpanel">
                            <div class="watermark-icon text-center mb-4">
                                <i class="fas fa-user-graduate watermark-large-icon"></i>
                                <h4 class="mt-3 text-watermark">Personal Information</h4>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo isset($student['first_name']) ? htmlspecialchars($student['first_name']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter first name</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="second_name" class="form-label">Second Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="second_name" name="second_name"
                                           value="<?php echo isset($student['second_name']) ? htmlspecialchars($student['second_name']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter second name</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name"
                                           value="<?php echo isset($student['last_name']) ? htmlspecialchars($student['last_name']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter last name</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sex <span class="text-danger">*</span></label><br>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="sex" id="male" value="Male" 
                                               <?php echo (isset($student['sex']) && $student['sex'] == 'Male') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="male">Male</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="sex" id="female" value="Female"
                                               <?php echo (isset($student['sex']) && $student['sex'] == 'Female') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="female">Female</label>
                                    </div>
                                    <div class="invalid-feedback d-block">Please select sex</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                           value="<?php echo isset($student['date_of_birth']) ? htmlspecialchars($student['date_of_birth']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please select date of birth</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="combination" class="form-label">Combination <span class="text-danger">*</span></label>
                                    <select class="form-select" id="combination" name="combination" required>
                                        <option value="">Select Combination</option>
                                        <option value="HGE" <?php echo (isset($student['combination']) && $student['combination'] == 'HGE') ? 'selected' : ''; ?>>HGE (History, Geography, Economics)</option>
                                        <option value="HGL" <?php echo (isset($student['combination']) && $student['combination'] == 'HGL') ? 'selected' : ''; ?>>HGL (History, Geography, Language)</option>
                                        <option value="HGK" <?php echo (isset($student['combination']) && $student['combination'] == 'HGK') ? 'selected' : ''; ?>>HGK (History, Geography, Kiswahili)</option>
                                        <option value="HKL" <?php echo (isset($student['combination']) && $student['combination'] == 'HKL') ? 'selected' : ''; ?>>HKL (History, Kiswahili, Language)</option>
                                        <option value="KLF" <?php echo (isset($student['combination']) && $student['combination'] == 'KLF') ? 'selected' : ''; ?>>KLF (Kiswahili, Language, French)</option>
                                        <option value="EGM" <?php echo (isset($student['combination']) && $student['combination'] == 'EGM') ? 'selected' : ''; ?>>EGM (Economics, Geography, Mathematics)</option>
                                        <option value="HLF" <?php echo (isset($student['combination']) && $student['combination'] == 'HLF') ? 'selected' : ''; ?>>HLF (History, Language, French)</option>
                                        <option value="HGF" <?php echo (isset($student['combination']) && $student['combination'] == 'HGF') ? 'selected' : ''; ?>>HGF (History, Geography, French)</option>
                                    </select>
                                    <small class="text-muted">H = History, G = Geography, F = French, L = Language, K = Kiswahili, M = Mathematics, E = Economics</small>
                                    <div class="invalid-feedback">Please select a combination</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary" disabled>Previous</button>
                                <button type="button" class="btn btn-primary next-step" data-next="step2">
                                    Continue <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 2: Admission Details -->
                        <div class="tab-pane fade" id="step2" role="tabpanel">
                            <div class="watermark-icon text-center mb-4">
                                <i class="fas fa-school watermark-large-icon"></i>
                                <h4 class="mt-3 text-watermark">Admission Details</h4>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_admission" class="form-label">Date of Admission <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_of_admission" name="date_of_admission"
                                           value="<?php echo isset($student['date_of_admission']) ? htmlspecialchars($student['date_of_admission']) : date('Y-m-d'); ?>" 
                                           required>
                                    <div class="invalid-feedback">Please select admission date</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="admission_number" class="form-label">Admission Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="admission_number" name="admission_number"
                                           value="<?php echo isset($student['admission_number']) ? htmlspecialchars($student['admission_number']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter admission number</div>
                                    <small class="text-muted">Admission numbers can be the same for Form Five and Form Six as they are different classes</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="class" class="form-label">Class Admitted <span class="text-danger">*</span></label>
                                    <select class="form-select" id="class" name="class" required>
                                        <option value="">Select Class</option>
                                        <option value="Form Five" <?php echo (isset($student['class']) && $student['class'] == 'Form Five') ? 'selected' : ''; ?>>Form Five</option>
                                        <option value="Form Six" <?php echo (isset($student['class']) && $student['class'] == 'Form Six') ? 'selected' : ''; ?>>Form Six</option>
                                    </select>
                                    <div class="invalid-feedback">Please select class</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="citizenship" class="form-label">Citizenship <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="citizenship" name="citizenship" 
                                           value="<?php echo isset($student['citizenship']) ? htmlspecialchars($student['citizenship']) : 'Tanzania'; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter citizenship</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="place_of_birth" class="form-label">Place of Birth <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="place_of_birth" name="place_of_birth"
                                           value="<?php echo isset($student['place_of_birth']) ? htmlspecialchars($student['place_of_birth']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter place of birth</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary prev-step" data-prev="step1">
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </button>
                                <button type="button" class="btn btn-primary next-step" data-next="step3">
                                    Continue <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 3: Parent/Guardian Information -->
                        <div class="tab-pane fade" id="step3" role="tabpanel">
                            <div class="watermark-icon text-center mb-4">
                                <i class="fas fa-users watermark-large-icon"></i>
                                <h4 class="mt-3 text-watermark">Parent/Guardian Information</h4>
                                <?php if (!$edit_mode): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-key me-2"></i>
                                    <strong>Password Note:</strong> The parent's phone number will be used as the student's default password.
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="parent_name" class="form-label">Parent/Guardian Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="parent_name" name="parent_name"
                                           value="<?php echo isset($student['parent_name']) ? htmlspecialchars($student['parent_name']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter parent/guardian name</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="parent_phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">255</span>
                                        <input type="tel" class="form-control" id="parent_phone" name="parent_phone"
                                               value="<?php 
                                               if (isset($student['parent_phone'])) {
                                                   // Display only the 9 digits after 255
                                                   $phone = htmlspecialchars($student['parent_phone']);
                                                   if (substr($phone, 0, 3) === '255') {
                                                       echo substr($phone, 3);
                                                   } else {
                                                       echo $phone;
                                                   }
                                               }
                                               ?>" 
                                               required 
                                               pattern="[0-9]{9}"
                                               placeholder="712345678"
                                               maxlength="9"
                                               oninput="this.value = this.value.replace(/\D/g, '').slice(0, 9)">
                                    </div>
                                    <div class="invalid-feedback">Please enter 9 digits after 255 (e.g., 712345678)</div>
                                    <small class="text-muted">Format: 255 followed by 9 digits (total 12 digits) - This will be the student's default password</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="parent_occupation" class="form-label">Occupation</label>
                                    <input type="text" class="form-control" id="parent_occupation" name="parent_occupation"
                                           value="<?php echo isset($student['parent_occupation']) ? htmlspecialchars($student['parent_occupation']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="parent_residence" class="form-label">Residence (Street/Village) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="parent_residence" name="parent_residence"
                                           value="<?php echo isset($student['parent_residence']) ? htmlspecialchars($student['parent_residence']) : ''; ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter residence</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary prev-step" data-prev="step2">
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </button>
                                <button type="button" class="btn btn-primary next-step" data-next="step4">
                                    Continue <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 4: Previous School (Optional) -->
                        <div class="tab-pane fade" id="step4" role="tabpanel">
                            <div class="watermark-icon text-center mb-4">
                                <i class="fas fa-graduation-cap watermark-large-icon"></i>
                                <h4 class="mt-3 text-watermark">Previous School Information (Optional)</h4>
                            </div>
                            
                            <div class="alert alert-info watermark-alert mb-4">
                                <i class="fas fa-info-circle me-2"></i>
                                This section is optional. Fill only if applicable.
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="former_school" class="form-label">Former School</label>
                                    <input type="text" class="form-control" id="former_school" name="former_school"
                                           value="<?php echo isset($student['former_school']) ? htmlspecialchars($student['former_school']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="school_transferred_to" class="form-label">School Transferred To</label>
                                    <input type="text" class="form-control" id="school_transferred_to" name="school_transferred_to"
                                           value="<?php echo isset($student['school_transferred_to']) ? htmlspecialchars($student['school_transferred_to']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date_leaving_school" class="form-label">Date of Leaving School</label>
                                    <input type="date" class="form-control" id="date_leaving_school" name="date_leaving_school"
                                           value="<?php echo isset($student['date_leaving_school']) ? htmlspecialchars($student['date_leaving_school']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="school_transferred_from" class="form-label">School Transferred From</label>
                                    <input type="text" class="form-control" id="school_transferred_from" name="school_transferred_from"
                                           value="<?php echo isset($student['school_transferred_from']) ? htmlspecialchars($student['school_transferred_from']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary prev-step" data-prev="step3">
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i><?php echo $edit_mode ? 'Update Student' : 'Register Student'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Validation Error Modal -->
<div class="modal fade" id="validationErrorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-exclamation-circle me-2"></i>Validation Error</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <h5 class="mb-3" id="validationErrorTitle">Please fill in all required fields</h5>
                <p id="validationErrorMessage"></p>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Please fill in all required fields marked with <span class="text-danger">*</span> before continuing.
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-warning" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>OK
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Tab navigation for form steps
document.addEventListener('DOMContentLoaded', function() {
    const nextButtons = document.querySelectorAll('.next-step');
    const prevButtons = document.querySelectorAll('.prev-step');
    
    nextButtons.forEach(button => {
        button.addEventListener('click', function() {
            const nextStep = this.getAttribute('data-next');
            const currentTab = this.closest('.tab-pane').id;
            
            // Validate current step before proceeding
            if (validateStep(currentTab)) {
                const nextTab = new bootstrap.Tab(document.querySelector(`#${nextStep}-tab`));
                nextTab.show();
            }
        });
    });
    
    prevButtons.forEach(button => {
        button.addEventListener('click', function() {
            const prevStep = this.getAttribute('data-prev');
            const prevTab = new bootstrap.Tab(document.querySelector(`#${prevStep}-tab`));
            prevTab.show();
        });
    });
    
    // Phone number validation
    const phoneInput = document.getElementById('parent_phone');
    if (phoneInput) {
        // Auto-format phone number on blur
        phoneInput.addEventListener('blur', function(e) {
            // Remove any non-digit characters
            let phone = this.value.replace(/\D/g, '');
            
            // Ensure exactly 9 digits
            if (phone.length === 9) {
                // Combine with 255 prefix for display
                this.value = phone;
                this.classList.remove('is-invalid');
            } else if (phone.length > 0) {
                this.classList.add('is-invalid');
            }
        });
        
        // Restrict input to digits only and limit to 9 digits
        phoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 9);
            
            // Validate format
            if (this.value.length === 9) {
                this.classList.remove('is-invalid');
            } else if (this.value.length > 0) {
                this.classList.add('is-invalid');
            }
        });
    }
    
    // Form validation for each step
    function validateStep(stepId) {
        const step = document.getElementById(stepId);
        const requiredFields = step.querySelectorAll('[required]');
        let isValid = true;
        let firstInvalidField = null;
        let errorMessage = '';
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                
                if (!firstInvalidField) {
                    firstInvalidField = field;
                    const fieldLabel = field.closest('.mb-3')?.querySelector('.form-label')?.textContent || 'Field';
                    errorMessage = `${fieldLabel.trim().replace('*', '')} is required`;
                }
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        // Special validation for phone number
        if (stepId === 'step3' && phoneInput) {
            if (phoneInput.value && phoneInput.value.length !== 9) {
                isValid = false;
                phoneInput.classList.add('is-invalid');
                if (!firstInvalidField) {
                    firstInvalidField = phoneInput;
                    errorMessage = 'Phone number must be exactly 9 digits after 255';
                }
            }
        }
        
        if (!isValid) {
            // Show validation error modal
            document.getElementById('validationErrorTitle').textContent = 'Form Validation Error';
            document.getElementById('validationErrorMessage').textContent = errorMessage;
            
            const errorModal = new bootstrap.Modal(document.getElementById('validationErrorModal'));
            errorModal.show();
            
            // Scroll to first error after modal is shown
            setTimeout(() => {
                if (firstInvalidField) {
                    firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalidField.focus();
                }
            }, 500);
        }
        
        return isValid;
    }
    
    // Remove validation classes when user starts typing
    document.querySelectorAll('input, select').forEach(field => {
        field.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
    
    // Form submission validation
    const registrationForm = document.getElementById('registrationForm');
    if (registrationForm) {
        registrationForm.addEventListener('submit', function(e) {
            // Validate all steps before submission
            const steps = ['step1', 'step2', 'step3'];
            let isValid = true;
            let firstInvalidStep = null;
            
            steps.forEach(stepId => {
                if (!validateStep(stepId)) {
                    isValid = false;
                    if (!firstInvalidStep) {
                        firstInvalidStep = stepId;
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                // Show the tab with errors
                if (firstInvalidStep) {
                    const tab = new bootstrap.Tab(document.querySelector(`#${firstInvalidStep}-tab`));
                    tab.show();
                }
                return false;
            }
            
            // Format phone number before submission (add 255 prefix)
            if (phoneInput && phoneInput.value.length === 9) {
                // Create a hidden field with the full phone number
                const fullPhoneInput = document.createElement('input');
                fullPhoneInput.type = 'hidden';
                fullPhoneInput.name = 'parent_phone';
                fullPhoneInput.value = '255' + phoneInput.value;
                
                // Remove the original phone input (just the 9 digits)
                phoneInput.disabled = true;
                
                // Append the hidden field
                this.appendChild(fullPhoneInput);
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                submitBtn.disabled = true;
                
                // Re-enable button after 5 seconds if still on page
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 5000);
            }
            
            return true;
        });
    }
});
</script>

<style>
:root {
    --watermark-color: #3B9DB3;
}

.text-watermark {
    color: var(--watermark-color) !important;
    opacity: 0.9;
}

.watermark-large-icon {
    font-size: 4rem;
    color: var(--watermark-color);
    opacity: 0.3;
}

.watermark-card {
    border: 1px solid rgba(59, 157, 179, 0.2);
}

.watermark-alert {
    background-color: rgba(59, 157, 179, 0.1);
    border-color: rgba(59, 157, 179, 0.3);
    color: #333;
}

.nav-pills .nav-link.active {
    background-color: var(--watermark-color);
    border-color: var(--watermark-color);
}

.btn-primary {
    background-color: var(--watermark-color);
    border-color: var(--watermark-color);
}

.btn-primary:hover {
    background-color: #2d8b9e;
    border-color: #2d8b9e;
}

.form-control:focus, .form-select:focus {
    border-color: var(--watermark-color);
    box-shadow: 0 0 0 0.25rem rgba(59, 157, 179, 0.25);
}

.btn-success {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-outline-secondary {
    color: #6c757d;
    border-color: #6c757d;
}

.btn-outline-secondary:hover {
    background-color: #6c757d;
    border-color: #6c757d;
}

.modal-dialog-centered {
    min-height: calc(100% - 1rem);
}

.modal.fade .modal-dialog {
    transform: scale(0.9);
    opacity: 0;
    transition: all 0.3s ease;
}

.modal.show .modal-dialog {
    transform: scale(1);
    opacity: 1;
}

.modal-header {
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.modal-footer {
    border-top: 1px solid #dee2e6;
}

.input-group-text {
    background-color: #e9ecef;
    color: #495057;
    font-weight: 500;
}

@media (max-width: 768px) {
    .watermark-large-icon {
        font-size: 3rem;
    }
    
    .nav-pills .nav-link {
        font-size: 0.8rem;
        padding: 0.5rem 0.75rem;
    }
    
    .modal-dialog {
        margin: 0.5rem;
    }
}
</style>

<?php include '../controller/footer.php'; ?>