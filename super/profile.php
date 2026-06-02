<?php
// super/profile.php - Super Admin Profile Management
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

$success_message = '';
$error_message = '';

// ==================== UPDATE PROFILE INFO ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    
    // Validate
    if (empty($first_name) || empty($last_name)) {
        $error_message = "First name and last name are required!";
    } else {
        $update_sql = "UPDATE super_admins SET 
                       first_name = ?, 
                       last_name = ?, 
                       phone = ? 
                       WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssi", $first_name, $last_name, $phone, $super_id);
        
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Refresh session data
            $_SESSION['super_admin_name'] = $first_name . ' ' . $last_name;
            // Refresh admin data
            $super_sql = "SELECT * FROM super_admins WHERE id = $super_id AND status = 1";
            $super_result = mysqli_query($conn, $super_sql);
            $super_admin = mysqli_fetch_assoc($super_result);
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
        $stmt->close();
    }
}

// ==================== UPDATE PASSWORD ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Please fill all password fields!";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Password must be at least 6 characters long!";
    } else {
        // Verify old password
        if (password_verify($old_password, $super_admin['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE super_admins SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $hashed_password, $super_id);
            
            if ($stmt->execute()) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Failed to change password. Please try again.";
            }
            $stmt->close();
        } else {
            $error_message = "Current password is incorrect!";
        }
    }
}

// ==================== UPLOAD PROFILE IMAGE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        // Validate file type
        if (!in_array($file['type'], $allowed_types)) {
            $error_message = "Only JPG, PNG, and GIF images are allowed!";
        } 
        // Validate file size
        elseif ($file['size'] > $max_size) {
            $error_message = "Image size must be less than 2MB!";
        }
        else {
            // Create upload directory if not exists
            $upload_dir = '../uploads/super_admins/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'super_' . $super_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            $db_path = 'uploads/super_admins/' . $filename;
            
            // Delete old image if exists
            if (!empty($super_admin['profile_image']) && file_exists('../' . $super_admin['profile_image'])) {
                unlink('../' . $super_admin['profile_image']);
            }
            
            // Upload new image
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $update_sql = "UPDATE super_admins SET profile_image = ? WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("si", $db_path, $super_id);
                
                if ($stmt->execute()) {
                    $success_message = "Profile image uploaded successfully!";
                    // Refresh admin data
                    $super_sql = "SELECT * FROM super_admins WHERE id = $super_id AND status = 1";
                    $super_result = mysqli_query($conn, $super_sql);
                    $super_admin = mysqli_fetch_assoc($super_result);
                } else {
                    $error_message = "Failed to save image path.";
                }
                $stmt->close();
            } else {
                $error_message = "Failed to upload image. Please try again.";
            }
        }
    } else {
        $error_message = "Please select an image to upload.";
    }
}

// ==================== REMOVE PROFILE IMAGE ====================
if (isset($_GET['remove_image'])) {
    if (!empty($super_admin['profile_image']) && file_exists('../' . $super_admin['profile_image'])) {
        unlink('../' . $super_admin['profile_image']);
    }
    $update_sql = "UPDATE super_admins SET profile_image = NULL WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $super_id);
    if ($stmt->execute()) {
        $success_message = "Profile image removed successfully!";
        // Refresh admin data
        $super_sql = "SELECT * FROM super_admins WHERE id = $super_id AND status = 1";
        $super_result = mysqli_query($conn, $super_sql);
        $super_admin = mysqli_fetch_assoc($super_result);
    }
    $stmt->close();
}

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');
$current_time = date('l, F j, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Profile - School Management System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-color: #3B9DB3;
            --primary-dark: #2d7c8f;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            color: var(--primary-color);
            margin: 0 auto 15px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
        }

        .profile-avatar.has-image {
            background-size: cover;
            background-position: center;
        }

        .avatar-upload {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: var(--primary-color);
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid white;
            transition: all 0.3s ease;
        }

        .avatar-upload:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .avatar-upload input {
            display: none;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .profile-card h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-control, .form-select {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 10px 15px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(59, 157, 179, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3);
        }

        .btn-outline-danger:hover {
            transform: translateY(-2px);
        }

        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-label {
            width: 130px;
            font-weight: 600;
            color: #555;
        }

        .info-value {
            flex: 1;
            color: #333;
        }

        .role-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        @media (max-width: 768px) {
            .info-row {
                flex-direction: column;
            }
            .info-label {
                width: 100%;
                margin-bottom: 5px;
            }
            .profile-header {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<?php include '../controller/header.php'; ?>
<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="container-fluid">
        
        <!-- Page Title -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-user-circle me-2"></i> My Profile</h2>
                    <p class="mb-0">Manage your account information and preferences</p>
                    <p class="mb-0 mt-2">
                        <small><i class="far fa-calendar-alt me-1"></i> <?php echo $current_time; ?></small>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="role-badge">
                        <i class="fas fa-crown me-1"></i> System Administrator
                    </span>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            
            <!-- Left Column - Profile Image -->
            <div class="col-md-4">
                <div class="profile-card text-center">
                    <div class="profile-avatar <?php echo $super_admin['profile_image'] ? 'has-image' : ''; ?>"
                         id="profileAvatar"
                         style="<?php echo $super_admin['profile_image'] ? 'background-image: url(\'../' . $super_admin['profile_image'] . '\')' : ''; ?>">
                        <?php if (!$super_admin['profile_image']): ?>
                            <i class="fas fa-crown"></i>
                        <?php endif; ?>
                        
                        <!-- Upload Button -->
                        <div class="avatar-upload" onclick="document.getElementById('profileImageInput').click();">
                            <i class="fas fa-camera"></i>
                            <input type="file" id="profileImageInput" accept="image/*" style="display: none;">
                        </div>
                    </div>
                    
                    <h4 class="mt-3 mb-1"><?php echo htmlspecialchars($super_admin['first_name'] . ' ' . $super_admin['last_name']); ?></h4>
                    <p class="text-muted small">
                        <i class="fas fa-crown me-1" style="color: #dc3545;"></i> 
                        <?php echo $super_admin['role']; ?>
                    </p>
                    
                    <?php if ($super_admin['profile_image']): ?>
                    <a href="?remove_image=1" class="btn btn-sm btn-outline-danger mt-2" 
                       onclick="return confirm('Remove your profile image?')">
                        <i class="fas fa-trash-alt me-1"></i> Remove Image
                    </a>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    
                    <div class="text-start">
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-envelope me-2"></i> Email:</div>
                            <div class="info-value"><?php echo htmlspecialchars($super_admin['email']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-phone me-2"></i> Phone:</div>
                            <div class="info-value">
                                <?php echo $super_admin['phone'] ? htmlspecialchars($super_admin['phone']) : '<span class="text-muted">Not set</span>'; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-calendar-plus me-2"></i> Joined:</div>
                            <div class="info-value"><?php echo date('F d, Y', strtotime($super_admin['created_at'])); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-clock me-2"></i> Last Login:</div>
                            <div class="info-value">
                                <?php echo $super_admin['last_login'] ? date('F d, Y H:i', strtotime($super_admin['last_login'])) : 'Never'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Edit Forms -->
            <div class="col-md-8">
                
                <!-- Edit Profile Form -->
                <div class="profile-card">
                    <h5><i class="fas fa-user-edit me-2"></i> Edit Profile Information</h5>
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" 
                                       value="<?php echo htmlspecialchars($super_admin['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" 
                                       value="<?php echo htmlspecialchars($super_admin['last_name']); ?>" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($super_admin['phone'] ?? ''); ?>"
                                       placeholder="e.g., 255712345678">
                                <small class="text-muted">Format: 255XXXXXXXXX</small>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($super_admin['email']); ?>" disabled>
                                <small class="text-muted">Email cannot be changed. Contact system administrator if needed.</small>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Change Password Form -->
                <div class="profile-card">
                    <h5><i class="fas fa-key me-2"></i> Change Password</h5>
                    <form method="POST" action="" onsubmit="return validatePassword()">
                        <div class="mb-3">
                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="old_password" id="old_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="new_password" id="new_password" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="update_password" class="btn btn-primary">
                                <i class="fas fa-lock me-2"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
                
            </div>
        </div>
        
    </div>
</main>

<!-- Image Upload Form (Hidden) -->
<form id="imageUploadForm" method="POST" action="" enctype="multipart/form-data" style="display: none;">
    <input type="hidden" name="upload_image" value="1">
    <input type="file" name="profile_image" id="hiddenImageInput" accept="image/*">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Handle profile image upload
document.getElementById('profileImageInput').addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const file = this.files[0];
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        // Validate file type
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid File Type',
                text: 'Only JPG, PNG, and GIF images are allowed!'
            });
            return;
        }
        
        // Validate file size
        if (file.size > maxSize) {
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'Image size must be less than 2MB!'
            });
            return;
        }
        
        // Preview image before upload
        const reader = new FileReader();
        reader.onload = function(e) {
            Swal.fire({
                title: 'Upload Profile Image?',
                text: 'Are you sure you want to change your profile picture?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3B9DB3',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, upload it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form with the selected file
                    const formData = new FormData();
                    formData.append('upload_image', '1');
                    formData.append('profile_image', file);
                    
                    // Show loading
                    Swal.fire({
                        title: 'Uploading...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Upload via fetch
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(() => {
                        window.location.reload();
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Upload Failed',
                            text: 'An error occurred while uploading. Please try again.'
                        });
                    });
                }
            });
        };
        reader.readAsDataURL(file);
    }
});

// Validate password match
function validatePassword() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        Swal.fire({
            icon: 'error',
            title: 'Password Mismatch',
            text: 'New password and confirmation do not match!'
        });
        return false;
    }
    
    if (newPassword.length < 6) {
        Swal.fire({
            icon: 'error',
            title: 'Password Too Short',
            text: 'Password must be at least 6 characters long!'
        });
        return false;
    }
    
    return true;
}

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });
}, 100);
</script>

<?php include '../controller/footer.php'; ?>
</body>
</html>