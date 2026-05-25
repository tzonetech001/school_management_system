<?php
// candidates/profile.php
session_start();
require_once '../controller/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$success_message = '';
$error_message = '';

// Handle profile picture upload
if (isset($_POST['upload_profile'])) {
    $target_dir = "../uploads/student_profiles/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($_FILES["profile_image"]["name"]);
    $target_file = $target_dir . $file_name;
    $upload_ok = 1;
    $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
    if ($check !== false) {
        $upload_ok = 1;
    } else {
        $error_message = "File is not an image.";
        $upload_ok = 0;
    }
    
    // Check file size (max 5MB)
    if ($_FILES["profile_image"]["size"] > 5000000) {
        $error_message = "Sorry, your file is too large. Max size is 5MB.";
        $upload_ok = 0;
    }
    
    // Allow certain file formats
    if ($image_file_type != "jpg" && $image_file_type != "png" && $image_file_type != "jpeg" && $image_file_type != "gif") {
        $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        $upload_ok = 0;
    }
    
    if ($upload_ok == 1) {
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            // Get old profile image to delete
            $old_image_sql = "SELECT profile_image FROM students WHERE id = ?";
            $old_image_stmt = mysqli_prepare($conn, $old_image_sql);
            mysqli_stmt_bind_param($old_image_stmt, "i", $student_id);
            mysqli_stmt_execute($old_image_stmt);
            $old_image_result = mysqli_stmt_get_result($old_image_stmt);
            
            if ($old_image_result && mysqli_num_rows($old_image_result) > 0) {
                $old_image_data = mysqli_fetch_assoc($old_image_result);
                if (!empty($old_image_data['profile_image']) && file_exists($target_dir . $old_image_data['profile_image'])) {
                    unlink($target_dir . $old_image_data['profile_image']); // Delete old image
                }
            }
            mysqli_stmt_close($old_image_stmt);
            
            // Update database with new profile image
            $update_sql = "UPDATE students SET profile_image = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $file_name, $student_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success_message = "Profile picture updated successfully!";
            } else {
                $error_message = "Error updating database: " . mysqli_error($conn);
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $error_message = "Sorry, there was an error uploading your file.";
        }
    }
}

// Handle profile picture removal
if (isset($_GET['remove_image'])) {
    $target_dir = "../uploads/student_profiles/";
    
    // Get current profile image
    $image_sql = "SELECT profile_image FROM students WHERE id = ?";
    $image_stmt = mysqli_prepare($conn, $image_sql);
    mysqli_stmt_bind_param($image_stmt, "i", $student_id);
    mysqli_stmt_execute($image_stmt);
    $image_result = mysqli_stmt_get_result($image_stmt);
    
    if ($image_result && mysqli_num_rows($image_result) > 0) {
        $image_data = mysqli_fetch_assoc($image_result);
        
        if (!empty($image_data['profile_image']) && file_exists($target_dir . $image_data['profile_image'])) {
            unlink($target_dir . $image_data['profile_image']); // Delete file
        }
    }
    mysqli_stmt_close($image_stmt);
    
    // Update database
    $update_sql = "UPDATE students SET profile_image = NULL, updated_at = NOW() WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "i", $student_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        $success_message = "Profile picture removed successfully!";
    } else {
        $error_message = "Error removing profile picture: " . mysqli_error($conn);
    }
    mysqli_stmt_close($update_stmt);
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New password and confirm password do not match.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long.";
    } else {
        // Get current password hash from database
        $password_sql = "SELECT password FROM students WHERE id = ?";
        $password_stmt = mysqli_prepare($conn, $password_sql);
        mysqli_stmt_bind_param($password_stmt, "i", $student_id);
        mysqli_stmt_execute($password_stmt);
        $password_result = mysqli_stmt_get_result($password_stmt);
        $password_data = mysqli_fetch_assoc($password_result);
        mysqli_stmt_close($password_stmt);
        
        // Verify current password
        if (password_verify($current_password, $password_data['password'])) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password in database
            $update_sql = "UPDATE students SET password = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $student_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success_message = "Password changed successfully!";
                
                // Log the password change
                $log_sql = "INSERT INTO student_login_logs (student_id, action, ip_address, user_agent) 
                           VALUES (?, 'Password Changed', ?, ?)";
                $log_stmt = mysqli_prepare($conn, $log_sql);
                $ip = $_SERVER['REMOTE_ADDR'];
                $ua = $_SERVER['HTTP_USER_AGENT'];
                mysqli_stmt_bind_param($log_stmt, "iss", $student_id, $ip, $ua);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);
            } else {
                $error_message = "Error changing password: " . mysqli_error($conn);
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $error_message = "Current password is incorrect.";
        }
    }
}

// Fetch student data with error handling
$student = null;
$student_sql = "SELECT * FROM students WHERE id = ?";
$student_stmt = mysqli_prepare($conn, $student_sql);

if ($student_stmt) {
    mysqli_stmt_bind_param($student_stmt, "i", $student_id);
    mysqli_stmt_execute($student_stmt);
    $student_result = mysqli_stmt_get_result($student_stmt);
    
    if ($student_result && mysqli_num_rows($student_result) > 0) {
        $student = mysqli_fetch_assoc($student_result);
    }
    mysqli_stmt_close($student_stmt);
}

if (!$student) {
    header("Location: ../login.php");
    exit();
}

// Get profile image path
$profile_image_path = '';
if (!empty($student['profile_image']) && file_exists("../uploads/student_profiles/" . $student['profile_image'])) {
    $profile_image_path = "../uploads/student_profiles/" . $student['profile_image'];
}

// Format dates
$joined_date = !empty($student['created_at']) ? date('F j, Y', strtotime($student['created_at'])) : 'N/A';
$dob = !empty($student['date_of_birth']) ? date('F j, Y', strtotime($student['date_of_birth'])) : 'Not provided';

// Get initials for avatar
$initials = strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));
$full_name = $student['first_name'] . ' ' . $student['last_name'];
if (!empty($student['second_name'])) {
    $full_name = $student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name'];
}
?>

<?php include '../candidates/header.php'; ?>
<?php include 'sidebar_student.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title with Watermark Effect -->
        <div class="page-title-wrapper mb-4">
            <div class="watermark">PROFILE</div>
            <h2 class="page-title">
                <i class="fas fa-id-card me-2" style="color: var(--primary-color);"></i>
                My Profile
            </h2>
            <p class="text-muted">View and manage your personal information</p>
        </div>

        <!-- Alert Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Profile Header Card -->
        <div class="profile-header-card mb-4">
            <div class="watermark-small">STUDENT</div>
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="welcome-message">
                        <h1>Welcome back, <span class="highlight"><?php echo htmlspecialchars($student['first_name']); ?>!</span></h1>
                        <p class="mb-0"><i class="fas fa-map-pin me-2"></i> <?php echo htmlspecialchars($student['class'] . ' ' . $student['combination']); ?> | Admission: <?php echo htmlspecialchars($student['admission_number']); ?></p>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="profile-status">
                        <span class="badge <?php echo $student['status'] ? 'bg-success' : 'bg-danger'; ?>">
                            <i class="fas fa-circle me-1"></i>
                            <?php echo $student['status'] ? 'Active Student' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column - Profile Picture & Quick Info -->
            <div class="col-lg-4 mb-4">
                <!-- Profile Picture Card -->
                <div class="card profile-card h-100">
                    <div class="watermark-small">PHOTO</div>
                    <div class="card-body text-center">
                        <div class="profile-image-wrapper mb-4">
                            <?php if ($profile_image_path): ?>
                                <div class="profile-image-container" onclick="document.getElementById('profile_image').click();">
                                    <img src="<?php echo $profile_image_path; ?>" alt="<?php echo htmlspecialchars($full_name); ?>" class="profile-image">
                                    <div class="profile-image-overlay">
                                        <i class="fas fa-camera"></i>
                                        <span>Change Photo</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="profile-image-placeholder" onclick="document.getElementById('profile_image').click();">
                                    <span class="initials"><?php echo $initials; ?></span>
                                    <div class="placeholder-overlay">
                                        <i class="fas fa-camera"></i>
                                        <span>Add Photo</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h3 class="student-name"><?php echo htmlspecialchars($full_name); ?></h3>
                        <p class="student-class mb-3">
                            <i class="fas fa-graduation-cap me-1"></i>
                            <?php echo htmlspecialchars($student['class'] . ' ' . $student['combination']); ?>
                        </p>

                        <!-- Upload Form -->
                        <form action="" method="POST" enctype="multipart/form-data" id="profileUploadForm">
                            <div class="upload-area" onclick="document.getElementById('profile_image').click();">
                                <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                <p class="mb-1">Click to upload new photo</p>
                                <small class="text-muted">Max 5MB (JPG, PNG, GIF)</small>
                                <input type="file" class="d-none" id="profile_image" name="profile_image" accept="image/*">
                            </div>
                            <button type="submit" name="upload_profile" class="btn btn-primary w-100 mt-3" id="uploadBtn">
                                <i class="fas fa-upload me-2"></i>Upload Photo
                            </button>
                        </form>

                        <?php if ($profile_image_path): ?>
                            <a href="?remove_image=1" class="btn btn-outline-danger w-100 mt-2" onclick="return confirm('Are you sure you want to remove your profile picture?');">
                                <i class="fas fa-trash-alt me-2"></i>Remove Picture
                            </a>
                        <?php endif; ?>

                        <!-- Quick Stats -->
                        <div class="quick-stats mt-4">
                            <div class="row g-2">
                                <div class="col-4">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo date('Y') - date('Y', strtotime($student['created_at'])); ?></div>
                                        <div class="stat-label">Years</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $student['sex'] == 'Male' ? '♂' : '♀'; ?></div>
                                        <div class="stat-label">Gender</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-item">
                                        <div class="stat-value">
                                            <?php 
                                            $term = ceil(date('n')/4) + 1;
                                            echo $term > 4 ? 'Annual' : 'Term ' . $term;
                                            ?>
                                        </div>
                                        <div class="stat-label">Current</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Detailed Information -->
            <div class="col-lg-8">
                <!-- Personal Information Card -->
                <div class="card info-card mb-4">
                    <div class="watermark-small">PERSONAL</div>
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-circle me-2" style="color: var(--primary-color);"></i>
                            Personal Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">First Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['first_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Second Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['second_name'] ?? '—'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Last Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['last_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Gender</div>
                                <div class="info-value">
                                    <span class="gender-badge <?php echo strtolower($student['sex']); ?>">
                                        <i class="fas fa-<?php echo $student['sex'] == 'Male' ? 'mars' : 'venus'; ?> me-1"></i>
                                        <?php echo htmlspecialchars($student['sex']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Date of Birth</div>
                                <div class="info-value"><?php echo $dob; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Place of Birth</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['place_of_birth'] ?? 'Not provided'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information Card -->
                <div class="card info-card mb-4">
                    <div class="watermark-small">CONTACT</div>
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-address-book me-2" style="color: var(--primary-color);"></i>
                            Contact Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Email Address</div>
                                <div class="info-value">
                                    <?php if (!empty($student['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($student['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value">
                                    <?php if (!empty($student['phone_number'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($student['phone_number']); ?>">
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($student['phone_number']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['address'] ?? 'Not provided'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Postal Code</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['postal_code'] ?? 'Not provided'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Academic Information Card -->
                <div class="card info-card mb-4">
                    <div class="watermark-small">ACADEMIC</div>
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-graduation-cap me-2" style="color: var(--primary-color);"></i>
                            Academic Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Admission Number</div>
                                <div class="info-value">
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($student['admission_number']); ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Class</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['class']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Combination</div>
                                <div class="info-value">
                                    <span class="badge bg-info"><?php echo htmlspecialchars($student['combination']); ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Index Number</div>
                                <div class="info-value">
                                    <strong><?php echo htmlspecialchars($student['index_number'] ?? 'Not assigned'); ?></strong>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Year Joined</div>
                                <div class="info-value"><?php echo $joined_date; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Previous School</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['previous_school'] ?? 'Not provided'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Parent/Guardian Information Card -->
                <div class="card info-card mb-4">
                    <div class="watermark-small">PARENT</div>
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2" style="color: var(--primary-color);"></i>
                            Parent/Guardian Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Parent Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['parent_name'] ?? 'Not provided'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Relationship</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['parent_relation'] ?? 'Not provided'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Parent Phone</div>
                                <div class="info-value">
                                    <?php if (!empty($student['parent_phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($student['parent_phone']); ?>">
                                            <i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars($student['parent_phone']); ?>
                                        </a>
                                        <small class="text-muted d-block">(Default password)</small>
                                    <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Parent Email</div>
                                <div class="info-value">
                                    <?php if (!empty($student['parent_email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($student['parent_email']); ?>">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($student['parent_email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Parent Occupation</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['parent_occupation'] ?? 'Not provided'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Parent Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['parent_address'] ?? 'Not provided'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Password Card -->
                <div class="card info-card mb-4">
                    <div class="watermark-small">SECURITY</div>
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-lock me-2" style="color: var(--primary-color);"></i>
                            Change Password
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="passwordChangeForm">
                            <div class="password-fields">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength mt-2">
                                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                    </div>
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-match-feedback mt-1" id="passwordMatchFeedback"></div>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-primary w-100" id="changePasswordBtn">
                                    <i class="fas fa-sync-alt me-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                        
                        <!-- Password Tips -->
                        <div class="password-tips mt-3">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Password Tips:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Use a mix of letters, numbers, and symbols</li>
                                    <li>Don't share your password with anyone</li>
                                    <li>Change your password regularly</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact Card -->
                <?php if (!empty($student['emergency_name']) || !empty($student['emergency_phone'])): ?>
                <div class="card info-card mt-4">
                    <div class="watermark-small">EMERGENCY</div>
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-ambulance me-2" style="color: var(--primary-color);"></i>
                            Emergency Contact
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Contact Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['emergency_name'] ?? 'Not provided'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Relationship</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['emergency_relation'] ?? 'Not provided'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value">
                                    <?php if (!empty($student['emergency_phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($student['emergency_phone']); ?>">
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($student['emergency_phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer Note -->
        <div class="profile-footer-note text-center mt-4">
            <p class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                For any changes to your personal information (name, contact details, etc.), please contact the school administration.
            </p>
        </div>
    </div>
</div>

<style>
/* Profile Page Specific Styles with Watermark */

/* Watermark Effects */
.page-title-wrapper {
    position: relative;
    padding: 20px 0;
    overflow: hidden;
}

.watermark {
    position: absolute;
    top: -20px;
    right: -20px;
    font-size: 120px;
    font-weight: 900;
    color: rgba(59, 157, 179, 0.05);
    text-transform: uppercase;
    pointer-events: none;
    z-index: 0;
    transform: rotate(10deg);
    white-space: nowrap;
}

.watermark-small {
    position: absolute;
    top: 5px;
    right: 10px;
    font-size: 40px;
    font-weight: 800;
    color: rgba(59, 157, 179, 0.03);
    text-transform: uppercase;
    pointer-events: none;
    z-index: 0;
}

/* Profile Header Card */
.profile-header-card {
    background: linear-gradient(135deg, rgba(59, 157, 179, 0.1), rgba(45, 124, 143, 0.05));
    border-radius: 20px;
    padding: 30px;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(59, 157, 179, 0.2);
    backdrop-filter: blur(10px);
}

.welcome-message h1 {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 10px;
    color: #333;
}

.welcome-message .highlight {
    color: var(--primary-color);
    position: relative;
    display: inline-block;
}

.welcome-message .highlight::after {
    content: '';
    position: absolute;
    bottom: 5px;
    left: 0;
    width: 100%;
    height: 8px;
    background: rgba(59, 157, 179, 0.2);
    z-index: -1;
    border-radius: 4px;
}

/* Profile Cards */
.profile-card, .info-card {
    border: none;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    position: relative;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
}

.profile-card:hover, .info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(59, 157, 179, 0.15);
}

.card-header {
    background: linear-gradient(135deg, #fff, #f8f9fa);
    border-bottom: 2px solid rgba(59, 157, 179, 0.1);
    padding: 20px 25px;
}

.card-header h5 {
    font-weight: 600;
    color: #333;
}

/* Profile Image Styles */
.profile-image-wrapper {
    position: relative;
    display: inline-block;
    cursor: pointer;
}

.profile-image-container {
    position: relative;
    width: 200px;
    height: 200px;
    margin: 0 auto;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid var(--primary-color);
    box-shadow: 0 10px 30px rgba(59, 157, 179, 0.3);
    cursor: pointer;
}

.profile-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.profile-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(59, 157, 179, 0.8);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    color: white;
    font-size: 20px;
}

.profile-image-overlay span {
    font-size: 14px;
    margin-top: 5px;
}

.profile-image-container:hover .profile-image {
    transform: scale(1.1);
}

.profile-image-container:hover .profile-image-overlay {
    opacity: 1;
}

.profile-image-placeholder {
    width: 200px;
    height: 200px;
    margin: 0 auto;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    border: 4px solid white;
    box-shadow: 0 10px 30px rgba(59, 157, 179, 0.3);
    cursor: pointer;
}

.profile-image-placeholder .initials {
    font-size: 80px;
    font-weight: 700;
    color: white;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}

.placeholder-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: rgba(59, 157, 179, 0.8);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    color: white;
    font-size: 20px;
}

.placeholder-overlay span {
    font-size: 14px;
    margin-top: 5px;
}

.profile-image-placeholder:hover .placeholder-overlay {
    opacity: 1;
}

/* Upload Area */
.upload-area {
    border: 2px dashed var(--primary-color);
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    background: rgba(59, 157, 179, 0.05);
    cursor: pointer;
    transition: all 0.3s ease;
}

.upload-area:hover {
    background: rgba(59, 157, 179, 0.1);
    border-color: var(--primary-dark);
    transform: translateY(-2px);
}

.upload-area i {
    color: var(--primary-color);
}

/* Student Name */
.student-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 5px;
}

.student-class {
    color: var(--primary-color);
    font-size: 1rem;
    font-weight: 500;
    background: rgba(59, 157, 179, 0.1);
    padding: 5px 15px;
    border-radius: 30px;
    display: inline-block;
}

/* Quick Stats */
.quick-stats {
    background: linear-gradient(135deg, rgba(59, 157, 179, 0.05), rgba(45, 124, 143, 0.02));
    border-radius: 15px;
    padding: 15px;
}

.stat-item {
    text-align: center;
    padding: 10px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    transition: transform 0.3s ease;
}

.stat-item:hover {
    transform: scale(1.05);
}

.stat-value {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--primary-color);
    line-height: 1.2;
}

.stat-label {
    font-size: 0.8rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    padding: 12px 15px;
    background: rgba(59, 157, 179, 0.02);
    border-radius: 12px;
    border-left: 3px solid var(--primary-color);
    transition: all 0.3s ease;
}

.info-item:hover {
    background: rgba(59, 157, 179, 0.05);
    transform: translateX(5px);
}

.info-label {
    font-size: 0.8rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.info-value {
    font-size: 1rem;
    font-weight: 500;
    color: #333;
    word-break: break-word;
}

.info-value a {
    color: var(--primary-color);
    text-decoration: none;
    transition: color 0.3s ease;
}

.info-value a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Gender Badge */
.gender-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 0.9rem;
    font-weight: 500;
}

.gender-badge.male {
    background: rgba(0, 123, 255, 0.1);
    color: #0056b3;
}

.gender-badge.female {
    background: rgba(232, 62, 140, 0.1);
    color: #c8236c;
}

/* Badge Customization */
.badge.bg-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)) !important;
    padding: 8px 15px;
    font-size: 0.9rem;
    font-weight: 500;
}

.badge.bg-info {
    background: linear-gradient(135deg, #17a2b8, #138496) !important;
    padding: 8px 15px;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Password Fields */
.password-fields {
    background: rgba(59, 157, 179, 0.02);
    padding: 20px;
    border-radius: 15px;
}

.input-group {
    border-radius: 12px;
    overflow: hidden;
}

.input-group-text {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    border: none;
    padding: 0 15px;
}

.form-control {
    border: 2px solid rgba(59, 157, 179, 0.1);
    padding: 12px 15px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(59, 157, 179, 0.25);
}

.toggle-password {
    border: 2px solid rgba(59, 157, 179, 0.1);
    background: white;
    color: var(--primary-color);
    transition: all 0.3s ease;
}

.toggle-password:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

/* Password Strength Indicator */
.password-strength {
    height: 5px;
    background: #e9ecef;
    border-radius: 5px;
    overflow: hidden;
}

.password-strength-bar {
    height: 100%;
    width: 0;
    background: #dc3545;
    transition: all 0.3s ease;
}

/* Password Match Feedback */
.password-match-feedback {
    font-size: 0.85rem;
    min-height: 20px;
}

.password-match-feedback.match {
    color: #28a745;
}

.password-match-feedback.no-match {
    color: #dc3545;
}

/* Password Tips */
.password-tips {
    border-top: 1px solid rgba(59, 157, 179, 0.1);
    padding-top: 15px;
    margin-top: 15px;
}

.password-tips .alert {
    background: rgba(59, 157, 179, 0.05);
    border: 1px solid rgba(59, 157, 179, 0.1);
    color: #333;
    border-radius: 12px;
}

.password-tips ul {
    list-style-type: none;
    padding-left: 0;
}

.password-tips li {
    padding: 3px 0;
    padding-left: 20px;
    position: relative;
}

.password-tips li:before {
    content: '✓';
    position: absolute;
    left: 0;
    color: var(--primary-color);
    font-weight: bold;
}

/* Profile Footer Note */
.profile-footer-note {
    position: relative;
    padding: 20px;
    background: rgba(59, 157, 179, 0.02);
    border-radius: 15px;
    border: 1px solid rgba(59, 157, 179, 0.1);
}

.profile-footer-note p {
    margin: 0;
    font-style: italic;
}

/* Button Styles */
.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    border: none;
    padding: 12px 20px;
    font-weight: 500;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(59, 157, 179, 0.3);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-outline-danger {
    border: 2px solid #dc3545;
    color: #dc3545;
    font-weight: 500;
    border-radius: 12px;
    padding: 12px 20px;
    transition: all 0.3s ease;
}

.btn-outline-danger:hover {
    background: #dc3545;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(220, 53, 69, 0.3);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .watermark {
        font-size: 80px;
        top: -10px;
        right: -10px;
    }
    
    .watermark-small {
        font-size: 30px;
    }
    
    .profile-image-container,
    .profile-image-placeholder {
        width: 150px;
        height: 150px;
    }
    
    .profile-image-placeholder .initials {
        font-size: 60px;
    }
    
    .welcome-message h1 {
        font-size: 1.6rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-header-card {
        padding: 20px;
    }
    
    .password-fields {
        padding: 15px;
    }
}

/* Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.profile-card, .info-card {
    animation: fadeInUp 0.5s ease forwards;
}

.info-card:nth-child(2) {
    animation-delay: 0.1s;
}

.info-card:nth-child(3) {
    animation-delay: 0.2s;
}

.info-card:nth-child(4) {
    animation-delay: 0.3s;
}

/* Loading State */
.loading {
    position: relative;
    overflow: hidden;
}

.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}
</style>

<script>
// Auto-submit when file is selected
document.getElementById('profile_image')?.addEventListener('change', function() {
    if (this.files.length > 0) {
        // Show preview
        previewImage(this);
        // Enable upload button
        document.getElementById('uploadBtn').disabled = false;
    }
});

// Preview uploaded image before upload
function previewImage(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const container = document.querySelector('.profile-image-wrapper');
            
            if (document.querySelector('.profile-image-container')) {
                // Update existing image
                document.querySelector('.profile-image').src = e.target.result;
            } else {
                // Replace placeholder with image preview
                const initials = document.querySelector('.profile-image-placeholder .initials');
                if (initials) {
                    container.innerHTML = `
                        <div class="profile-image-container" onclick="document.getElementById('profile_image').click();">
                            <img src="${e.target.result}" alt="Profile Preview" class="profile-image">
                            <div class="profile-image-overlay">
                                <i class="fas fa-camera"></i>
                                <span>Change Photo</span>
                            </div>
                        </div>
                    `;
                }
            }
        };
        reader.readAsDataURL(file);
    }
}

// Add loading state to upload button
document.getElementById('uploadBtn')?.addEventListener('click', function() {
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';
    this.disabled = true;
});

// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const passwordField = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordField.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});

// Password strength checker
document.getElementById('new_password')?.addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrengthBar');
    let strength = 0;
    
    // Check length
    if (password.length >= 6) strength += 1;
    if (password.length >= 8) strength += 1;
    
    // Check for numbers
    if (/\d/.test(password)) strength += 1;
    
    // Check for special characters
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength += 1;
    
    // Check for uppercase
    if (/[A-Z]/.test(password)) strength += 1;
    
    // Update strength bar
    const percentage = (strength / 5) * 100;
    strengthBar.style.width = percentage + '%';
    
    // Update color based on strength
    if (strength <= 2) {
        strengthBar.style.background = '#dc3545'; // Weak
    } else if (strength <= 3) {
        strengthBar.style.background = '#ffc107'; // Medium
    } else {
        strengthBar.style.background = '#28a745'; // Strong
    }
});

// Password match checker
document.getElementById('confirm_password')?.addEventListener('input', checkPasswordMatch);
document.getElementById('new_password')?.addEventListener('input', checkPasswordMatch);

function checkPasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const feedback = document.getElementById('passwordMatchFeedback');
    const submitBtn = document.getElementById('changePasswordBtn');
    
    if (confirmPassword.length > 0) {
        if (newPassword === confirmPassword) {
            feedback.innerHTML = '<i class="fas fa-check-circle me-1"></i>Passwords match';
            feedback.className = 'password-match-feedback match';
            submitBtn.disabled = false;
        } else {
            feedback.innerHTML = '<i class="fas fa-times-circle me-1"></i>Passwords do not match';
            feedback.className = 'password-match-feedback no-match';
            submitBtn.disabled = true;
        }
    } else {
        feedback.innerHTML = '';
        submitBtn.disabled = false;
    }
}

// Form validation for password change
document.getElementById('passwordChangeForm')?.addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New password and confirm password do not match!');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
    
    // Add loading state
    const submitBtn = document.getElementById('changePasswordBtn');
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Changing Password...';
    submitBtn.disabled = true;
});

// Smooth scroll to top when page loads
window.scrollTo({ top: 0, behavior: 'smooth' });

// Enable upload button only when file is selected
document.getElementById('uploadBtn').disabled = true;

// Drag and drop for file upload
const uploadArea = document.querySelector('.upload-area');
if (uploadArea) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        uploadArea.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
    }
    
    function unhighlight() {
        uploadArea.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
    }
    
    uploadArea.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        document.getElementById('profile_image').files = files;
        previewImage(document.getElementById('profile_image'));
        document.getElementById('uploadBtn').disabled = false;
    }
}
</script>

<?php include '../controller/footer.php'; ?>