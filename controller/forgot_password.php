<?php
// forgot_password.php - Updated with Multi-School Support & No SMS
session_start();
require_once '../controller/db_connect.php';

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

$error = '';
$success = '';
$step = 1;
$user_type = '';
$identifier = '';

// ==================== HELPER FUNCTIONS ====================

// Format phone number for validation only (no SMS sending)
function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (substr($phone, 0, 1) === '0') {
        $phone = '255' . substr($phone, 1);
    }
    else if (substr($phone, 0, 1) === '7' || substr($phone, 0, 1) === '6') {
        $phone = '255' . $phone;
    }
    else if (substr($phone, 0, 3) !== '255') {
        $phone = '255' . $phone;
    }
    
    if (strlen($phone) > 12) {
        $phone = substr($phone, 0, 12);
    }
    
    return $phone;
}

// Validate phone number format
function validatePhoneNumber($phone) {
    $phone = formatPhoneNumber($phone);
    return preg_match('/^255[67][0-9]{8}$/', $phone);
}

// Create password_resets table if not exists
function ensurePasswordResetsTable($conn) {
    $check = "SHOW TABLES LIKE 'password_resets'";
    $result = $conn->query($check);
    
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_type VARCHAR(20) NOT NULL,
            user_id INT NOT NULL,
            email VARCHAR(100),
            phone VARCHAR(20),
            token VARCHAR(100) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_user (user_type, user_id)
        )";
        $conn->query($sql);
    }
}

// ==================== STEP 1: Request Reset ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $identifier = trim($_POST['identifier']);
    
    if (empty($identifier)) {
        $error = "Please enter your email or admission number.";
    } else {
        ensurePasswordResetsTable($conn);
        
        // Check if identifier is email (admin or super admin)
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            // FIRST: Check Super Admin
            $super_sql = "SELECT id, first_name, last_name, email, phone FROM super_admins 
                         WHERE email = ? AND status = 1";
            $stmt = $conn->prepare($super_sql);
            $stmt->bind_param("s", $identifier);
            $stmt->execute();
            $super_result = $stmt->get_result();
            
            if ($super_result && $super_result->num_rows > 0) {
                $user = $super_result->fetch_assoc();
                $user_type = 'super_admin';
                
                // Store in session for step 2 (phone verification)
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Delete old unused tokens
                $clean = "DELETE FROM password_resets WHERE user_id = ? AND user_type = 'super_admin' AND used = 0";
                $clean_stmt = $conn->prepare($clean);
                $clean_stmt->bind_param("i", $user['id']);
                $clean_stmt->execute();
                
                $insert = "INSERT INTO password_resets (user_type, user_id, email, phone, token, expires_at) 
                          VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert);
                $insert_stmt->bind_param("sissss", $user_type, $user['id'], $user['email'], $user['phone'], $token, $expiry);
                
                if ($insert_stmt->execute()) {
                    $_SESSION['reset_token'] = $token;
                    $_SESSION['reset_user_type'] = 'super_admin';
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['reset_phone'] = $user['phone'];
                    
                    $step = 2;
                } else {
                    $error = "Error processing request. Please try again.";
                }
            } 
            // SECOND: Check Admin (Staff)
            else {
                $admin_sql = "SELECT a.id, a.first_name, a.last_name, a.email, a.phone_number, a.school_id, s.school_name
                             FROM admins a
                             JOIN schools s ON a.school_id = s.id
                             WHERE a.email = ? AND a.status = 1 AND s.status = 'Active'";
                $stmt = $conn->prepare($admin_sql);
                $stmt->bind_param("s", $identifier);
                $stmt->execute();
                $admin_result = $stmt->get_result();
                
                if ($admin_result && $admin_result->num_rows > 0) {
                    $user = $admin_result->fetch_assoc();
                    $user_type = 'admin';
                    
                    if (empty($user['phone_number'])) {
                        $error = "No phone number found for this account. Please contact school administrator.";
                    } else {
                        $token = bin2hex(random_bytes(32));
                        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        
                        // Delete old unused tokens
                        $clean = "DELETE FROM password_resets WHERE user_id = ? AND user_type = 'admin' AND used = 0";
                        $clean_stmt = $conn->prepare($clean);
                        $clean_stmt->bind_param("i", $user['id']);
                        $clean_stmt->execute();
                        
                        $insert = "INSERT INTO password_resets (user_type, user_id, email, phone, token, expires_at) 
                                  VALUES (?, ?, ?, ?, ?, ?)";
                        $insert_stmt = $conn->prepare($insert);
                        $insert_stmt->bind_param("sissss", $user_type, $user['id'], $user['email'], $user['phone_number'], $token, $expiry);
                        
                        if ($insert_stmt->execute()) {
                            $_SESSION['reset_token'] = $token;
                            $_SESSION['reset_user_type'] = 'admin';
                            $_SESSION['reset_user_id'] = $user['id'];
                            $_SESSION['reset_user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                            $_SESSION['reset_phone'] = $user['phone_number'];
                            $_SESSION['reset_school_id'] = $user['school_id'];
                            $_SESSION['reset_school_name'] = $user['school_name'];
                            
                            $step = 2;
                        } else {
                            $error = "Error processing request. Please try again.";
                        }
                    }
                } else {
                    $error = "Email not found in our records.";
                }
            }
        } 
        // ELSE: Student by admission number
        else {
            $student_sql = "SELECT s.id, s.first_name, s.last_name, s.admission_number, s.parent_phone, s.date_of_birth, s.school_id, sc.school_name
                          FROM students s
                          JOIN schools sc ON s.school_id = sc.id
                          WHERE s.admission_number = ? 
                          AND (s.is_leaver = FALSE OR s.is_leaver IS NULL) 
                          AND s.status = 1
                          AND sc.status = 'Active'";
            $stmt = $conn->prepare($student_sql);
            $stmt->bind_param("s", $identifier);
            $stmt->execute();
            $student_result = $stmt->get_result();
            
            if ($student_result && $student_result->num_rows > 0) {
                $user = $student_result->fetch_assoc();
                $user_type = 'student';
                
                $token = bin2hex(random_bytes(32));
                
                // Store student info in session
                $_SESSION['reset_token'] = $token;
                $_SESSION['reset_user_type'] = 'student';
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_student_dob'] = $user['date_of_birth'];
                $_SESSION['reset_student_phone'] = $user['parent_phone'];
                $_SESSION['reset_student_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['reset_school_id'] = $user['school_id'];
                $_SESSION['reset_school_name'] = $user['school_name'];
                
                $step = 2;
            } else {
                $error = "Admission number not found.";
            }
        }
    }
}

// ==================== STEP 2: Verify ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $user_type = $_SESSION['reset_user_type'] ?? '';
    $token = $_SESSION['reset_token'] ?? '';
    
    if ($user_type === 'super_admin' || $user_type === 'admin') {
        // Staff/Super Admin Phone Verification
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
        $stored_phone = $_SESSION['reset_phone'] ?? '';
        
        $formatted_input = formatPhoneNumber($phone);
        $formatted_stored = formatPhoneNumber($stored_phone);
        
        if (empty($phone)) {
            $error = "Please enter your phone number.";
        } elseif ($formatted_input === $formatted_stored) {
            // Mark token as verified
            if (!empty($token)) {
                $update = "UPDATE password_resets SET used = 1 WHERE token = ? AND user_type = ?";
                $update_stmt = $conn->prepare($update);
                $update_stmt->bind_param("ss", $token, $user_type);
                $update_stmt->execute();
            }
            
            $_SESSION['reset_authorized'] = true;
            $step = 3;
        } else {
            $error = "Phone number does not match our records.";
        }
    } 
    elseif ($user_type === 'student') {
        // Student verification using DOB and parent phone
        $dob = $_POST['dob'] ?? '';
        $parent_phone = preg_replace('/\D/', '', $_POST['parent_phone'] ?? '');
        
        $stored_dob = $_SESSION['reset_student_dob'] ?? '';
        $stored_phone = $_SESSION['reset_student_phone'] ?? '';
        
        $formatted_parent = formatPhoneNumber($parent_phone);
        $formatted_stored = formatPhoneNumber($stored_phone);
        
        if (empty($dob) || empty($parent_phone)) {
            $error = "Please enter both Date of Birth and Parent Phone number.";
        } elseif ($dob == $stored_dob && $formatted_parent == $formatted_stored) {
            $_SESSION['reset_authorized'] = true;
            $step = 3;
        } else {
            $error = "Verification failed. Date of Birth or Parent Phone number is incorrect.";
        }
    }
}

// ==================== STEP 3: Set New Password ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_password'])) {
    if (!isset($_SESSION['reset_authorized']) || $_SESSION['reset_authorized'] !== true) {
        header("Location: forgot_password.php");
        exit();
    }
    
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_id = $_SESSION['reset_user_id'];
    $user_type = $_SESSION['reset_user_type'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill all fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        if ($user_type === 'super_admin') {
            $update = "UPDATE super_admins SET password = ? WHERE id = ?";
        } elseif ($user_type === 'admin') {
            $update = "UPDATE admins SET password = ? WHERE id = ?";
        } else {
            $update = "UPDATE students SET password = ? WHERE id = ?";
        }
        
        $stmt = $conn->prepare($update);
        $stmt->bind_param("si", $hashed, $user_id);
        
        if ($stmt->execute()) {
            // Clean up all tokens for this user
            $clean = "DELETE FROM password_resets WHERE user_id = ? AND user_type = ?";
            $clean_stmt = $conn->prepare($clean);
            $clean_stmt->bind_param("is", $user_id, $user_type);
            $clean_stmt->execute();
            
            // Clear reset session
            unset($_SESSION['reset_token']);
            unset($_SESSION['reset_user_type']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_authorized']);
            unset($_SESSION['reset_phone']);
            unset($_SESSION['reset_student_dob']);
            unset($_SESSION['reset_student_phone']);
            unset($_SESSION['reset_student_name']);
            unset($_SESSION['reset_school_id']);
            unset($_SESSION['reset_school_name']);
            unset($_SESSION['reset_user_name']);
            
            $_SESSION['reset_success'] = "Password reset successful. You can now login.";
            header("Location: ../mhs/login.php");
            exit();
        } else {
            $error = "Error updating password. Please try again.";
        }
    }
}

// Restart
if (isset($_GET['restart'])) {
    unset($_SESSION['reset_token']);
    unset($_SESSION['reset_user_type']);
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_authorized']);
    unset($_SESSION['reset_phone']);
    unset($_SESSION['reset_student_dob']);
    unset($_SESSION['reset_student_phone']);
    unset($_SESSION['reset_student_name']);
    unset($_SESSION['reset_school_id']);
    unset($_SESSION['reset_school_name']);
    unset($_SESSION['reset_user_name']);
    $step = 1;
}

// Get step from session if available
if (isset($_SESSION['reset_token']) && !isset($_GET['restart'])) {
    if (isset($_SESSION['reset_authorized']) && $_SESSION['reset_authorized'] === true) {
        $step = 3;
    } elseif (isset($_SESSION['reset_user_type'])) {
        $step = 2;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(145deg, #d4e0ec 1%, #b6cddf 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
        }
        
        .reset-container {
            max-width: 450px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 25, 45, 0.2);
            overflow: hidden;
        }
        
        .reset-header {
            background: linear-gradient(135deg, #3B9DB3, #1c6c80);
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .reset-header h2 {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 1.8rem;
        }
        
        .reset-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .reset-body {
            padding: 30px 25px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }
        
        .step-item {
            position: relative;
            z-index: 2;
            background: white;
            padding: 0 10px;
            text-align: center;
        }
        
        .step-circle {
            width: 32px;
            height: 32px;
            background: #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 5px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .step-item.active .step-circle {
            background: #3B9DB3;
            color: white;
        }
        
        .step-item.completed .step-circle {
            background: #28a745;
            color: white;
        }
        
        .step-label {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: #3B9DB3;
            box-shadow: 0 0 0 0.2rem rgba(59, 157, 179, 0.25);
        }
        
        .btn-reset {
            background: linear-gradient(135deg, #3B9DB3, #1c6c80);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
            margin-top: 15px;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #3B9DB3; }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }
        
        .info-box i {
            font-size: 2rem;
            color: #3B9DB3;
            margin-bottom: 10px;
        }
        
        .link-back {
            text-align: center;
            margin-top: 20px;
        }
        
        .link-back a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .link-back a:hover {
            color: #3B9DB3;
        }
        
        /* School badge */
        .school-badge {
            background: #e9ecef;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 0.75rem;
            color: #3B9DB3;
            display: inline-block;
            margin-top: 5px;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loader {
            display: flex;
            gap: 8px;
            align-items: flex-end;
            margin-bottom: 20px;
        }

        .loader div {
            width: 12px;
            background: linear-gradient(145deg, #3b9db3, #1c6c80);
            border-radius: 4px;
            animation: load 1s infinite ease-in-out;
        }

        .loader div:nth-child(1) { height: 12px; animation-delay: 0s; }
        .loader div:nth-child(2) { height: 24px; animation-delay: 0.1s; }
        .loader div:nth-child(3) { height: 36px; animation-delay: 0.2s; }
        .loader div:nth-child(4) { height: 48px; animation-delay: 0.3s; }
        .loader div:nth-child(5) { height: 36px; animation-delay: 0.4s; }
        .loader div:nth-child(6) { height: 24px; animation-delay: 0.5s; }

        @keyframes load {
            0%, 100% { transform: scaleY(1); opacity: 0.6; }
            50% { transform: scaleY(1.5); opacity: 1; background: #ffc107; }
        }

        .loading-text {
            font-family: 'Poppins', sans-serif;
            color: #1f4b5a;
            font-size: 1.1rem;
            font-weight: 500;
            margin-top: 10px;
            letter-spacing: 1px;
        }

        .loading-text span {
            color: #3b9db3;
            font-weight: 700;
        }
        
        .input-group-text {
            background: #f0f6fa;
            border: 2px solid #e9ecef;
            border-right: none;
        }
        
        .input-group .form-control {
            border-left: none;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader">
            <div></div><div></div><div></div><div></div><div></div><div></div>
        </div>
        <div class="loading-text">
            <span>School Management System</span>
        </div>
    </div>

    <div class="reset-container">
        <div class="reset-header">
            <h2><i class="fas fa-key me-2"></i>Forgot Password?</h2>
            <p>Reset your password securely</p>
        </div>
        
        <div class="reset-body">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step-item <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    <div class="step-circle">1</div>
                    <div class="step-label">Identify</div>
                </div>
                <div class="step-item <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                    <div class="step-circle">2</div>
                    <div class="step-label">Verify</div>
                </div>
                <div class="step-item <?php echo $step >= 3 ? 'active' : ''; ?>">
                    <div class="step-circle">3</div>
                    <div class="step-label">Reset</div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Step 1: Request Reset -->
            <?php if ($step == 1): ?>
                <form method="POST" id="step1Form">
                    <input type="hidden" name="request_reset" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-user me-1"></i>Username
                        </label>
                        <input type="text" class="form-control" name="identifier" 
                               placeholder="Enter your email or admission number" 
                               value="<?php echo htmlspecialchars($identifier); ?>" required>
                        <small class="text-muted">
                            Staff/Super Admin: Use your email address<br>
                            Student: Use your admission number
                        </small>
                    </div>
                    
                    <button type="submit" class="btn-reset">
                        <i class="fas fa-arrow-right me-2"></i>Continue
                    </button>
                </form>
            <?php endif; ?>
            
            <!-- Step 2: Verify -->
            <?php if ($step == 2): ?>
                <?php if (isset($_SESSION['reset_user_type']) && ($_SESSION['reset_user_type'] == 'admin' || $_SESSION['reset_user_type'] == 'super_admin')): ?>
                    <!-- Staff/Super Admin Phone Verification -->
                    <div class="info-box">
                        <i class="fas fa-user-check"></i>
                        <h6>Verify Your Identity</h6>
                        <p class="small text-muted mb-0">
                            Please enter your registered phone number
                        </p>
                        <?php if (isset($_SESSION['reset_user_name'])): ?>
                            <p class="small fw-bold mt-2 mb-0">
                                Account: <?php echo htmlspecialchars($_SESSION['reset_user_name']); ?>
                            </p>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['reset_school_name'])): ?>
                            <div class="school-badge">
                                <i class="fas fa-building me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['reset_school_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" id="step2Form">
                        <input type="hidden" name="verify" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text">+255</span>
                                <input type="tel" class="form-control" name="phone" 
                                       placeholder="712345678" maxlength="9" 
                                       pattern="[67][0-9]{8}" required>
                            </div>
                            <small class="text-muted">Format: 712345678 (9 digits starting with 6 or 7)</small>
                        </div>
                        
                        <button type="submit" class="btn-reset">
                            <i class="fas fa-check-circle me-2"></i>Verify Phone
                        </button>
                        
                        <div class="text-center mt-2">
                            <a href="?restart=1" class="text-muted small">
                                <i class="fas fa-arrow-left me-1"></i>Start over
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Student Verification (DOB + Parent Phone) -->
                    <div class="info-box">
                        <i class="fas fa-user-graduate"></i>
                        <h6>Student Verification</h6>
                        <p class="small text-muted mb-0">
                            Please enter your Date of Birth and Parent's Phone number
                        </p>
                        <?php if (isset($_SESSION['reset_student_name'])): ?>
                            <p class="small fw-bold mt-2 mb-0">
                                Student: <?php echo htmlspecialchars($_SESSION['reset_student_name']); ?>
                            </p>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['reset_school_name'])): ?>
                            <div class="school-badge">
                                <i class="fas fa-building me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['reset_school_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" id="step2Form">
                        <input type="hidden" name="verify" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input type="date" class="form-control" name="dob" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Parent Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text">+255</span>
                                <input type="tel" class="form-control" name="parent_phone" 
                                       placeholder="712345678" maxlength="9" 
                                       pattern="[67][0-9]{8}" required>
                            </div>
                            <small class="text-muted">Format: 712345678 (9 digits starting with 6 or 7)</small>
                        </div>
                        
                        <button type="submit" class="btn-reset">
                            <i class="fas fa-check-circle me-2"></i>Verify
                        </button>
                        
                        <div class="text-center mt-2">
                            <a href="?restart=1" class="text-muted small">
                                <i class="fas fa-arrow-left me-1"></i>Start over
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Step 3: Set New Password -->
            <?php if ($step == 3 && isset($_SESSION['reset_authorized'])): ?>
                <div class="info-box">
                    <i class="fas fa-check-circle text-success"></i>
                    <h6 class="text-success">Verification Successful</h6>
                    <p class="small text-muted mb-0">Please set your new password</p>
                    <?php if (isset($_SESSION['reset_school_name'])): ?>
                        <div class="school-badge mt-2">
                            <i class="fas fa-building me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['reset_school_name']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" id="step3Form">
                    <input type="hidden" name="set_password" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" class="form-control" name="new_password" 
                               minlength="6" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm Password</label>
                        <input type="password" class="form-control" name="confirm_password" 
                               minlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn-reset">
                        <i class="fas fa-save me-2"></i>Reset Password
                    </button>
                </form>
            <?php endif; ?>
            
            <!-- Back to Login -->
            <div class="link-back">
                <a href="../mhs/login.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Login
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Loading overlay functionality
        const loadingOverlay = document.getElementById('loadingOverlay');
        
        function showLoading(form) {
            if (form && form.checkValidity()) {
                loadingOverlay.classList.add('active');
                
                const btn = form.querySelector('button[type="submit"]');
                if(btn) {
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
                    btn.disabled = true;
                }
            } else if (form) {
                form.reportValidity();
            }
        }

        // Add event listeners to all forms
        document.addEventListener('DOMContentLoaded', function() {
            const step1Form = document.getElementById('step1Form');
            if (step1Form) {
                step1Form.addEventListener('submit', function(e) {
                    showLoading(this);
                });
            }

            const step2Form = document.getElementById('step2Form');
            if (step2Form) {
                step2Form.addEventListener('submit', function(e) {
                    showLoading(this);
                });
            }

            const step3Form = document.getElementById('step3Form');
            if (step3Form) {
                step3Form.addEventListener('submit', function(e) {
                    showLoading(this);
                });
            }
        });

        window.addEventListener('load', function() {
            setTimeout(() => {
                loadingOverlay.classList.remove('active');
            }, 3000);
        });

        // Phone number formatting for students
        <?php if ($step == 2 && isset($_SESSION['reset_user_type']) && $_SESSION['reset_user_type'] == 'student'): ?>
        document.querySelector('input[name="parent_phone"]')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 9);
        });
        <?php endif; ?>
        
        // Phone number formatting for admin/super admin
        <?php if ($step == 2 && isset($_SESSION['reset_user_type']) && ($_SESSION['reset_user_type'] == 'admin' || $_SESSION['reset_user_type'] == 'super_admin')): ?>
        document.querySelector('input[name="phone"]')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 9);
        });
        <?php endif; ?>

        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) alert.remove();
                }, 500);
            }, 5000);
        });
    </script>
</body>
</html>