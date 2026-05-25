<?php
// profile.php
session_start();
require_once '../controller/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../controller/login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Fetch admin details
$sql = "SELECT a.*, 
        GROUP_CONCAT(DISTINCT ar.role_name ORDER BY ara.is_primary DESC, ar.role_name SEPARATOR ', ') as roles,
        GROUP_CONCAT(DISTINCT CASE WHEN ara.is_primary = 1 THEN ar.role_name END) as primary_role
        FROM admins a
        LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
        LEFT JOIN admin_roles ar ON ara.role_id = ar.id
        WHERE a.id = $admin_id
        GROUP BY a.id";
        
$result = mysqli_query($conn, $sql);
$admin = mysqli_fetch_assoc($result);

if (!$admin) {
    header("Location: ../controller/login.php");
    exit();
}

// Handle profile update
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name'] ?? '');
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $sex = mysqli_real_escape_string($conn, $_POST['sex']);
    $nida = mysqli_real_escape_string($conn, $_POST['nida'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    
    // Handle image upload
    $profile_image = $admin['profile_image'];
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 300 * 1024; // 300KB
        $file = $_FILES['profile_image'];
        
        // Check file type
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Only JPG, JPEG, PNG & GIF files are allowed.';
        }
        // Check file size
        elseif ($file['size'] > $max_size) {
            $error = 'File size must be less than 300KB.';
        }
        // Upload file
        else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'admin_' . $admin_id . '_' . time() . '.' . $ext;
            $upload_path = '../uploads/profiles/' . $filename;
            
            // Create directory if it doesn't exist
            if (!file_exists('../uploads/profiles')) {
                mkdir('../uploads/profiles', 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old image if exists and not default
                if ($profile_image && $profile_image !== 'default.jpg' && file_exists('../uploads/profiles/' . $profile_image)) {
                    unlink('../uploads/profiles/' . $profile_image);
                }
                $profile_image = $filename;
            } else {
                $error = 'Failed to upload image.';
            }
        }
    }
    
    // Handle password change
    $password_changed = false;
    if (!empty($_POST['current_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        // Verify current password
        if (password_verify($_POST['current_password'], $admin['password'])) {
            if ($_POST['new_password'] === $_POST['confirm_password']) {
                $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $password_changed = true;
            } else {
                $error = 'New passwords do not match.';
            }
        } else {
            $error = 'Current password is incorrect.';
        }
    }
    
    // Update database if no errors
    if (empty($error)) {
        if ($password_changed) {
            $update_sql = "UPDATE admins SET 
                          first_name = '$first_name',
                          middle_name = '$middle_name',
                          last_name = '$last_name',
                          email = '$email',
                          phone_number = '$phone_number',
                          sex = '$sex',
                          nida = '$nida',
                          address = '$address',
                          profile_image = '$profile_image',
                          password = '$hashed_password',
                          updated_at = NOW()
                          WHERE id = $admin_id";
        } else {
            $update_sql = "UPDATE admins SET 
                          first_name = '$first_name',
                          middle_name = '$middle_name',
                          last_name = '$last_name',
                          email = '$email',
                          phone_number = '$phone_number',
                          sex = '$sex',
                          nida = '$nida',
                          address = '$address',
                          profile_image = '$profile_image',
                          updated_at = NOW()
                          WHERE id = $admin_id";
        }
        
        if (mysqli_query($conn, $update_sql)) {
            $success = 'Profile updated successfully!';
            
            // Refresh admin data
            $result = mysqli_query($conn, $sql);
            $admin = mysqli_fetch_assoc($result);
            
            // Update session
            $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
            $_SESSION['admin_email'] = $admin['email'];
        } else {
            $error = 'Error updating profile: ' . mysqli_error($conn);
        }
    }
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>


<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">My Profile</h2>
            <div class="text-end">
                <span class="badge bg-info fs-6">
                    <?php echo htmlspecialchars($admin['primary_role'] ?? 'Teacher'); ?>
                </span>
            </div>
        </div>

        <!-- Display Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Left Column: Profile Card -->
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header" style="background-color: #3B9DB3; color: white;">
                        <h5 class="mb-0">Profile Picture</h5>
                    </div>
                    <div class="card-body text-center">
                        <!-- Profile Image -->
                        <?php
                        $profile_image_path = '../uploads/profiles/' . ($admin['profile_image'] ?: 'default.jpg');
                        if (!file_exists($profile_image_path) || empty($admin['profile_image'])) {
                            $profile_image_path = 'https://ui-avatars.com/api/?name=' . urlencode($admin['first_name'] . '+' . $admin['last_name']) . '&size=200&background=3B9DB3&color=fff&bold=true';
                        }
                        ?>
                        <div class="mb-4">
                            <img src="<?php echo $profile_image_path; ?>" 
                                 alt="Profile Picture" 
                                 class="rounded-circle border border-3 border-primary"
                                 style="width: 200px; height: 200px; object-fit: cover;"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($admin['first_name'] . '+' . $admin['last_name']); ?>&size=200&background=3B9DB3&color=fff&bold=true'">
                        </div>
                        
                        <!-- Status Badge -->
                        <div class="mb-3">
                            <span class="badge <?php echo $admin['status'] ? 'bg-success' : 'bg-danger'; ?> fs-6">
                                <?php echo $admin['status'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="row text-start mt-4">
                            <div class="col-6">
                                <small class="text-muted">Member Since</small>
                                <p class="mb-0 fw-bold">
                                    <?php echo date('M d, Y', strtotime($admin['created_at'])); ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Last Updated</small>
                                <p class="mb-0 fw-bold">
                                    <?php echo $admin['updated_at'] ? date('M d, Y', strtotime($admin['updated_at'])) : 'Never'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Profile Form -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" id="profileForm">
                            <!-- Image Upload -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <label class="form-label">Upload New Profile Picture (Max 300KB)</label>
                                    <div class="input-group">
                                        <input type="file" class="form-control" name="profile_image" 
                                               accept=".jpg,.jpeg,.png,.gif" id="profileImageInput">
                                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('profileImageInput').value=''">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Allowed: JPG, JPEG, PNG, GIF | Max: 300KB</small>
                                    <div class="mt-2">
                                        <img id="imagePreview" src="#" alt="Preview" 
                                             class="img-thumbnail d-none" style="max-width: 150px;">
                                    </div>
                                </div>
                            </div>

                            <!-- Personal Info -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label required-field">First Name</label>
                                    <input type="text" class="form-control" name="first_name" 
                                           value="<?php echo htmlspecialchars($admin['first_name']); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" name="middle_name" 
                                           value="<?php echo htmlspecialchars($admin['middle_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required-field">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" 
                                           value="<?php echo htmlspecialchars($admin['last_name']); ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label required-field">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone_number" 
                                           value="<?php echo htmlspecialchars($admin['phone_number']); ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label required-field">Gender</label>
                                    <select class="form-select" name="sex" required>
                                        <option value="Male" <?php echo $admin['sex'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $admin['sex'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">NIDA Number</label>
                                    <input type="text" class="form-control" name="nida" 
                                           value="<?php echo htmlspecialchars($admin['nida'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($admin['address'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Roles Section -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card border-info">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-user-tag me-2"></i>My Roles & Permissions</h6>
                                        </div>
                                        <div class="card-body">
                                            <?php 
                                            $roles = explode(', ', $admin['roles']);
                                            $primary_role = $admin['primary_role'];
                                            foreach ($roles as $role): 
                                                if (!empty($role)):
                                            ?>
                                                <span class="badge <?php echo $role === $primary_role ? 'bg-primary' : 'bg-secondary'; ?> me-2 mb-2">
                                                    <?php echo htmlspecialchars($role); ?>
                                                    <?php if ($role === $primary_role): ?>
                                                        <i class="fas fa-star ms-1" title="Primary Role"></i>
                                                    <?php endif; ?>
                                                </span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <!-- Password Change Section -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card border-warning">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Leave password fields empty if you don't want to change password.
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Current Password</label>
                                                    <input type="password" class="form-control" name="current_password" 
                                                           id="currentPassword">
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="checkbox" 
                                                               id="showCurrentPassword">
                                                        <label class="form-check-label" for="showCurrentPassword">
                                                            Show Password
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">New Password</label>
                                                    <input type="password" class="form-control" name="new_password" 
                                                           id="newPassword">
                                                    <div class="progress mt-2" style="height: 5px;">
                                                        <div class="progress-bar" id="passwordStrength" 
                                                             role="progressbar" style="width: 0%"></div>
                                                    </div>
                                                    <small class="text-muted" id="passwordHint"></small>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Confirm New Password</label>
                                                    <input type="password" class="form-control" name="confirm_password" 
                                                           id="confirmPassword">
                                                    <div id="passwordMatch" class="mt-2 small"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <a href="../dashboard.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                        </a>
                                        <div>
                                            <button type="reset" class="btn btn-outline-danger me-2">
                                                <i class="fas fa-undo me-2"></i>Reset
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Save Changes
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Profile Management -->
<script>
// Image Preview
document.getElementById('profileImageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('imagePreview');
    const maxSize = 300 * 1024; // 300KB
    
    if (file) {
        // Check file size
        if (file.size > maxSize) {
            alert('File size must be less than 300KB');
            this.value = '';
            preview.classList.add('d-none');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
        }
        reader.readAsDataURL(file);
    } else {
        preview.classList.add('d-none');
    }
});

// Show/Hide Password
document.getElementById('showCurrentPassword').addEventListener('change', function() {
    const passwordField = document.getElementById('currentPassword');
    passwordField.type = this.checked ? 'text' : 'password';
});

// Password Strength Checker
document.getElementById('newPassword').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    const hint = document.getElementById('passwordHint');
    
    // Reset
    strengthBar.style.width = '0%';
    strengthBar.className = 'progress-bar';
    hint.textContent = '';
    
    if (password.length === 0) return;
    
    let strength = 0;
    let hintText = '';
    
    // Length check
    if (password.length >= 8) strength += 25;
    
    // Contains lowercase
    if (/[a-z]/.test(password)) strength += 25;
    
    // Contains uppercase
    if (/[A-Z]/.test(password)) strength += 25;
    
    // Contains numbers or special chars
    if (/[0-9!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) strength += 25;
    
    // Update progress bar
    strengthBar.style.width = strength + '%';
    
    // Set color based on strength
    if (strength < 50) {
        strengthBar.classList.add('bg-danger');
        hintText = 'Weak password';
    } else if (strength < 75) {
        strengthBar.classList.add('bg-warning');
        hintText = 'Moderate password';
    } else {
        strengthBar.classList.add('bg-success');
        hintText = 'Strong password';
    }
    
    // Give hints
    if (password.length < 8) {
        hintText += ' - At least 8 characters';
    }
    if (!/[a-z]/.test(password)) {
        hintText += ' - Add lowercase letters';
    }
    if (!/[A-Z]/.test(password)) {
        hintText += ' - Add uppercase letters';
    }
    if (!/[0-9!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
        hintText += ' - Add numbers or special characters';
    }
    
    hint.textContent = hintText;
});

// Password Match Checker
document.getElementById('confirmPassword').addEventListener('input', function() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = this.value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirmPassword.length === 0) {
        matchDiv.textContent = '';
        matchDiv.className = 'mt-2 small';
        return;
    }
    
    if (newPassword === confirmPassword) {
        matchDiv.innerHTML = '<i class="fas fa-check text-success me-1"></i>Passwords match';
        matchDiv.className = 'mt-2 small text-success';
    } else {
        matchDiv.innerHTML = '<i class="fas fa-times text-danger me-1"></i>Passwords do not match';
        matchDiv.className = 'mt-2 small text-danger';
    }
});

// Form validation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    // If any password field is filled, all must be filled
    if (currentPassword || newPassword || confirmPassword) {
        if (!currentPassword || !newPassword || !confirmPassword) {
            e.preventDefault();
            alert('Please fill all password fields if you want to change password.');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New passwords do not match.');
            return;
        }
        
        if (newPassword.length < 8) {
            e.preventDefault();
            alert('New password must be at least 8 characters long.');
            return;
        }
    }
    
    // Image size check (client-side)
    const fileInput = document.getElementById('profileImageInput');
    if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        const maxSize = 300 * 1024; // 300KB
        
        if (file.size > maxSize) {
            e.preventDefault();
            alert('Profile image must be less than 300KB.');
            return;
        }
    }
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<style>
.required-field::after {
    content: " *";
    color: #dc3545;
}

.card {
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}
.card-header{
     background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
}

.form-control:focus, .form-select:focus {
    border-color: #3B9DB3;
    box-shadow: 0 0 0 0.25rem rgba(59, 157, 179, 0.25);
}

.badge {
    font-size: 0.85rem;
    padding: 0.5em 1em;
    border-radius: 20px;
}

.img-thumbnail {
    border: 2px solid #3B9DB3;
}

.progress {
    background-color: #e9ecef;
}

@media (max-width: 768px) {
    .row .col-md-4, .row .col-md-6 {
        margin-bottom: 1rem;
    }
    
    .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-group .btn {
        margin-bottom: 0.5rem;
        width: 100%;
    }
}
</style>

<?php include '../controller/footer.php'; ?>