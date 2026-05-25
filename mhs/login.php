<?php
// login.php - Simple Email Notification using PHP mail() function
session_start();
require_once '../controller/db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['admin_id']) || isset($_SESSION['student_id'])) {
    if (isset($_SESSION['admin_id'])) {
        if (file_exists('../muyo/dashboard.php')) {
            header("Location: ../muyo/dashboard.php");
        } else {
            header("Location: muyovozi_home.php");
        }
        exit();
    } else {
        if (file_exists('../candidates/dashboard.php')) {
            header("Location: ../candidates/dashboard.php");
        } else {
            header("Location: muyovozi_home.php");
        }
        exit();
    }
}

$error = '';
$success = '';

// ==================== SIMPLE EMAIL NOTIFICATION FUNCTION ====================
function sendLoginNotification($name, $role, $email_to = 'muyovozimuyovozi1@gmail.com') {
    // Set timezone to Tanzania
    date_default_timezone_set('Africa/Dar_es_Salaam');
    $login_time = date('Y-m-d H:i:s');
    $login_date = date('l, F j, Y');
    
    // Get IP address
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // Email subject
    $subject = "Login Alert - School Management System";
    
    // Email body (plain text for better compatibility)
    $message = "
===========================================
    School Management System LOGIN ALERT
===========================================

User Information:
-----------------
Full Name: {$name}
Role: {$role}
Login Time: {$login_time} (Tanzania Time - EAT)
Login Date: {$login_date}
IP Address: {$ip}

===========================================
he enter at this time
===========================================
    ";
    
    // Email headers
    $headers = "From: noreply@muyovozi.sc.tz\r\n";
    $headers .= "Reply-To: admin@muyovozi.sc.tz\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Send email
    if(mail($email_to, $subject, $message, $headers)) {
        error_log("Login notification sent to {$email_to} for user: {$name}");
        return true;
    } else {
        error_log("Failed to send login notification for user: {$name}");
        return false;
    }
}

// ==================== ACCOUNT LOCK FUNCTIONS ====================
function isAccountLocked($conn, $identifier, $type = 'admin') {
    $table = ($type == 'admin') ? 'admins' : 'students';
    $identifier_field = ($type == 'admin') ? 'email' : 'admission_number';
    
    cleanupExpiredLocks($conn, $type);
    
    $sql = "SELECT locked_until FROM $table WHERE $identifier_field = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("s", $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['locked_until'] !== null && $row['locked_until'] != '') {
            $locked_until = strtotime($row['locked_until']);
            $now = time();
            
            if ($locked_until <= $now) {
                unlockAccount($conn, $identifier, $type);
                return false;
            }
            return true;
        }
    }
    return false;
}

function cleanupExpiredLocks($conn, $type = 'admin') {
    $table = ($type == 'admin') ? 'admins' : 'students';
    $sql = "UPDATE $table SET 
            failed_login_attempts = 0, 
            locked_until = NULL 
            WHERE locked_until IS NOT NULL 
            AND locked_until <= NOW()";
    return mysqli_query($conn, $sql);
}

function unlockAccount($conn, $identifier, $type = 'admin') {
    $table = ($type == 'admin') ? 'admins' : 'students';
    $identifier_field = ($type == 'admin') ? 'email' : 'admission_number';
    
    $sql = "UPDATE $table SET 
            failed_login_attempts = 0, 
            locked_until = NULL,
            last_login_attempt = NULL 
            WHERE $identifier_field = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("s", $identifier);
    return $stmt->execute();
}

function logFailedAttempt($conn, $identifier, $type = 'admin') {
    $table = ($type == 'admin') ? 'admins' : 'students';
    $identifier_field = ($type == 'admin') ? 'email' : 'admission_number';
    
    $update_sql = "UPDATE $table SET 
                   failed_login_attempts = failed_login_attempts + 1,
                   last_login_attempt = NOW() 
                   WHERE $identifier_field = ?";
    
    $stmt = $conn->prepare($update_sql);
    if ($stmt) {
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
    }
    
    $select_sql = "SELECT failed_login_attempts FROM $table WHERE $identifier_field = ?";
    $stmt = $conn->prepare($select_sql);
    if (!$stmt) return 1;
    $stmt->bind_param("s", $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $attempts = $row['failed_login_attempts'] ?? 1;
    
    if ($attempts >= 5) {
        $lock_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $lock_sql = "UPDATE $table SET locked_until = ? WHERE $identifier_field = ?";
        $stmt = $conn->prepare($lock_sql);
        if ($stmt) {
            $stmt->bind_param("ss", $lock_until, $identifier);
            $stmt->execute();
        }
    }
    
    return $attempts;
}

function logSuccessfulLogin($conn, $identifier, $type = 'admin') {
    $table = ($type == 'admin') ? 'admins' : 'students';
    $identifier_field = ($type == 'admin') ? 'email' : 'admission_number';
    
    $update_sql = "UPDATE $table SET 
                   failed_login_attempts = 0, 
                   locked_until = NULL,
                   last_login_attempt = NOW() 
                   WHERE $identifier_field = ?";
    
    $stmt = $conn->prepare($update_sql);
    if ($stmt) {
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
    }
}

function getLockInfo($conn, $identifier, $type = 'admin') {
    $table = ($type == 'admin') ? 'admins' : 'students';
    $identifier_field = ($type == 'admin') ? 'email' : 'admission_number';
    
    $sql = "SELECT failed_login_attempts, locked_until, last_login_attempt 
            FROM $table WHERE $identifier_field = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("s", $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

// ==================== HANDLE LOGIN POST ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_successful = false;
    
    // Check if account is locked
    if (isAccountLocked($conn, $username, 'admin') || isAccountLocked($conn, $username, 'student')) {
        $lock_info = getLockInfo($conn, $username, 'admin');
        if (!$lock_info) {
            $lock_info = getLockInfo($conn, $username, 'student');
        }
        if ($lock_info && $lock_info['locked_until']) {
            $locked_until = new DateTime($lock_info['locked_until']);
            $now = new DateTime();
            $interval = $now->diff($locked_until);
            $minutes_remaining = ($interval->h * 60) + $interval->i;
            
            $error = "Account is locked due to multiple failed attempts. Please try again after $minutes_remaining minutes.";
        } else {
            $error = "Account is temporarily locked. Please try again after 30 minutes.";
        }
    } else {
        // Try admin login first
        $admin_sql = "SELECT * FROM admins WHERE (email = ? OR phone_number = ?) AND status = 1";
        $stmt = $conn->prepare($admin_sql);
        if ($stmt) {
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $admin_result = $stmt->get_result();
            
            if ($admin_result && $admin_result->num_rows > 0) {
                $admin = $admin_result->fetch_assoc();
                if (password_verify($password, $admin['password'])) {
                    logSuccessfulLogin($conn, $username, 'admin');
                    
                    $user_full_name = trim($admin['first_name'] . ' ' . ($admin['middle_name'] ?? '') . ' ' . $admin['last_name']);
                    $user_role = 'Admin';
                    
                    // Get admin role
                    $role_sql = "SELECT ar.role_name FROM admin_role_assignments ara 
                                 JOIN admin_roles ar ON ara.role_id = ar.id 
                                 WHERE ara.admin_id = ? LIMIT 1";
                    $role_stmt = $conn->prepare($role_sql);
                    if ($role_stmt) {
                        $role_stmt->bind_param("i", $admin['id']);
                        $role_stmt->execute();
                        $role_result = $role_stmt->get_result();
                        if ($role_row = $role_result->fetch_assoc()) {
                            $user_role = $role_row['role_name'];
                        }
                        $role_stmt->close();
                    }
                    
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['user_type'] = 'admin';
                    
                    // SEND SIMPLE EMAIL NOTIFICATION
                    sendLoginNotification($user_full_name, $user_role);
                    
                    if (file_exists('../muyo/dashboard.php')) {
                        header("Location: ../muyo/dashboard.php");
                    } else {
                        header("Location: home.php");
                    }
                    exit();
                } else {
                    $attempts = logFailedAttempt($conn, $username, 'admin');
                    $remaining_attempts = 5 - $attempts;
                    
                    if ($remaining_attempts > 0) {
                        $error = "Invalid password! $remaining_attempts attempts remaining before lockout.";
                    } else {
                        $error = "Account locked for 30 minutes due to multiple failed attempts.";
                    }
                    $login_successful = false;
                }
            }
        }
        
        // If admin login failed, try student login
        if (!$login_successful && empty($error)) {
            $student_sql = "SELECT * FROM students WHERE admission_number = ? AND (is_leaver = FALSE OR is_leaver IS NULL)";
            $stmt = $conn->prepare($student_sql);
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $student_result = $stmt->get_result();
                
                if ($student_result && $student_result->num_rows > 0) {
                    $student = $student_result->fetch_assoc();
                    
                    if (isset($student['status']) && $student['status'] == 0) {
                        logFailedAttempt($conn, $username, 'student');
                        $error = "Your account is inactive. Please contact Head Master.";
                    } elseif (password_verify($password, $student['password'])) {
                        logSuccessfulLogin($conn, $username, 'student');
                        
                        $student_full_name = trim($student['first_name'] . ' ' . ($student['second_name'] ?? '') . ' ' . $student['last_name']);
                        $student_class = $student['class'] ?? 'Form Six';
                        $student_combination = $student['combination'] ?? '';
                        $user_role = "Student - $student_class" . ($student_combination ? " ($student_combination)" : "");
                        
                        $_SESSION['student_id'] = $student['id'];
                        $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                        $_SESSION['student_admission'] = $student['admission_number'];
                        $_SESSION['student_class'] = $student['class'] ?? 'Form Six';
                        $_SESSION['user_type'] = 'student';
                        
                        // SEND SIMPLE EMAIL NOTIFICATION
                        sendLoginNotification($student_full_name, $user_role);
                        
                        if (file_exists('../candidates/dashboard.php')) {
                            header("Location: ../candidates/dashboard.php");
                        } else {
                            header("Location: home.php");
                        }
                        exit();
                    } else {
                        $attempts = logFailedAttempt($conn, $username, 'student');
                        $remaining_attempts = 5 - $attempts;
                        
                        if ($remaining_attempts > 0) {
                            $error = "Invalid password! $remaining_attempts attempts remaining before lockout.";
                        } else {
                            $error = "Account locked for 30 minutes due to multiple failed attempts.";
                        }
                    }
                } else {
                    logFailedAttempt($conn, $username, 'admin');
                    $error = "Invalid username or password!";
                }
            } else {
                $error = "System error. Please try again later.";
            }
        }
    }
}
?>
<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Login · School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style> * { margin: 0; padding: 0; box-sizing: border-box; } body {font-family: 'Poppins', sans-serif; background: linear-gradient(145deg, #d4e0ec 1%, #b6cddf 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; position: relative; } .portal-container {max-width: 1200px; width: 100%; background: rgba(255,255,255,0.7); backdrop-filter: blur(16px); border-radius: 2.2rem; box-shadow: 0 30px 55px rgba(0, 25, 45, 0.35); overflow: hidden; border: 1px solid rgba(255,255,255,0.5); } .split-row {display: flex; flex-wrap: wrap; min-height: 550px; } .carousel-side {flex: 1 1 50%; background: #0b2d3b; position: relative; overflow: hidden; border-radius: 2rem 0 0 2rem; } @media (max-width: 991.98px) {.carousel-side { display: none; } .login-side { flex: 1 1 100%; border-radius: 2rem; } .portal-container { max-width: 95%; } } .login-side {flex: 1 1 50%; background: white; display: flex; align-items: center; justify-content: center; padding: 2rem; border-radius: 0 2rem 2rem 0; } @media (max-width: 576px) {.login-side { padding: 1.5rem; } .login-form-container { padding: 0; } .welcome-title { font-size: 1.5rem !important; } .btn-signin { padding: 0.8rem !important; font-size: 1rem !important; } .input-wrapper { padding: 0.1rem 1rem !important; } .input-wrapper input { padding: 0.7rem 0.5rem !important; } } .login-form-container {width: 100%; max-width: 450px; } .welcome-title {font-size: 2rem; font-weight: 800; color: #0d2d3a; margin-bottom: 0.1rem; } .welcome-sub {color: #3b9db3; font-weight: 500; margin-bottom: 1.5rem; letter-spacing: 0.5px; } .input-group-custom {margin-bottom: 1.5rem; } .input-group-custom label {display: block; font-weight: 600; font-size: 0.82rem; color: #1f4b5a; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.3rem; } .input-wrapper {display: flex; align-items: center; background: #f0f6fa; border-radius: 60px; padding: 0.1rem 1.2rem; border: 2px solid transparent; transition: 0.2s; } .input-wrapper:focus-within {border-color: #3b9db3; background: white; box-shadow: 0 0 0 4px rgba(59,157,179,0.15); } .input-wrapper i {color: #3b9db3; font-size: 1.1rem; width: 24px; } .input-wrapper input {border: none; background: transparent; padding: 0.85rem 0.5rem; width: 100%; outline: none; font-size: 1rem; } .btn-signin {background: linear-gradient(125deg, #3b9db3, #1c6c80); color: white; border: none; border-radius: 60px; padding: 0.9rem; font-weight: 700; font-size: 1.1rem; width: 100%; margin: 1.2rem 0 1rem; transition: 0.2s; box-shadow: 0 12px 22px -8px #1c6c80; } .btn-signin:hover {background: #2d7c8f; transform: translateY(-2px); box-shadow: 0 18px 28px -8px #1c6c80; } .btn-signin:disabled {opacity: 0.7; transform: none; } .login-footer-links {display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 10px; } .login-footer-links a {text-decoration: none; color: #1d5b6b; font-weight: 500; transition: 0.2s; } .login-footer-links a:hover {color: #ffc107; } .lock-alert, .success-alert {padding: 12px 16px; border-radius: 30px; margin-bottom: 1.2rem; font-size: 0.85rem; display: flex; align-items: center; gap: 10px; animation: slideDown 0.3s ease; } @keyframes slideDown {from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } } .lock-alert {background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; } .success-alert {background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; } .lock-timer {font-weight: bold; color: #dc3545; margin-top: 8px; } .password-toggle-icon {cursor: pointer; color: #3b9db3; margin-left: 6px; } .loading-overlay {position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(8px); display: none; justify-content: center; align-items: center; z-index: 9999; flex-direction: column; } .loading-overlay.active { display: flex; } .loader {display: flex; gap: 8px; align-items: flex-end; margin-bottom: 20px; } .loader div {width: 12px; background: linear-gradient(145deg, #3b9db3, #1c6c80); border-radius: 4px; animation: load 1s infinite ease-in-out; } .loader div:nth-child(1) { height: 12px; animation-delay: 0s; } .loader div:nth-child(2) { height: 24px; animation-delay: 0.1s; } .loader div:nth-child(3) { height: 36px; animation-delay: 0.2s; } .loader div:nth-child(4) { height: 48px; animation-delay: 0.3s; } .loader div:nth-child(5) { height: 36px; animation-delay: 0.4s; } .loader div:nth-child(6) { height: 24px; animation-delay: 0.5s; } @keyframes load {0%, 100% { transform: scaleY(1); opacity: 0.6; } 50% { transform: scaleY(1.5); opacity: 1; background: #ffc107; } } .loading-text {font-family: 'Poppins', sans-serif; color: #1f4b5a; font-size: 1.1rem; font-weight: 500; margin-top: 10px; letter-spacing: 1px; } .loading-text span {color: #3b9db3; font-weight: 700; } .carousel-inner-custom {position: relative; width: 100%; height: 100%; min-height: 550px; } .carousel-slide {position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-size: cover; background-position: center; opacity: 0; transition: opacity 1.2s ease-in-out; display: flex; align-items: flex-end; padding: 2.5rem; color: white; } .carousel-slide.active {opacity: 1; z-index: 10; } .carousel-slide::after {content: ''; position: absolute; inset: 0; background: linear-gradient(0deg, rgba(0,30,40,0.7) 0%, rgba(0,10,20,0.2) 70%); z-index: 1; } .slide-caption {position: relative; z-index: 20; max-width: 85%; text-shadow: 0 4px 12px rgba(0,0,0,0.5); } .slide-caption h2 {font-weight: 700; font-size: 1.8rem; margin-bottom: 0.4rem; } .slide-caption p {font-size: 0.9rem; opacity: 0.9; } .carousel-indicators-custom {position: absolute; bottom: 2rem; left: 2.5rem; z-index: 25; display: flex; gap: 0.8rem; } .indicator-dot {width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.5); cursor: pointer; transition: 0.2s; } .indicator-dot.active {background: #ffc107; transform: scale(1.3); box-shadow: 0 0 14px #ffc107; } .password-strength {height: 3px; margin-top: 5px; border-radius: 2px; background: #e9ecef; overflow: hidden; } .password-strength-bar {height: 100%; width: 0%; transition: width 0.3s ease; } .strength-weak { background: #dc3545; width: 33.33%; } .strength-medium { background: #ffc107; width: 66.66%; } .strength-strong { background: #28a745; width: 100%; } .user-type-hint {text-align: center; margin-top: 10px; font-size: 0.8rem; color: #6c757d; } .user-type-hint i {color: #ffc107; margin: 0 4px; } .modal-content {border-radius: 20px; } .modal-header.bg-info {background: linear-gradient(135deg, #3b9db3, #1c6c80) !important; border-radius: 20px 20px 0 0; } </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader">
            <div></div><div></div><div></div><div></div><div></div><div></div>
        </div>
        <div class="loading-text">
            <span>School Management System</span>
        </div>
        <div class="loading-text" style="font-size: 0.8rem; margin-top: 5px;">
            Redirecting...
        </div>
    </div>

    <div class="portal-container">
        <div class="split-row">
            <div class="carousel-side">
                <div class="carousel-inner-custom" id="slidesContainer">
                    <div class="carousel-slide active" style="background-image: url('../images/image1.png');">
                        <div class="slide-caption">
                            <h2>School Management System</h2>
                            <p>Education For Life</p>
                        </div>
                    </div>
                    <div class="carousel-slide" style="background-image: url('../images/image5.png');">
                        <div class="slide-caption">
                            <h2>ADMINSTRATION BLOCK</h2>
                            <p>Student Records & Admissions Management.</p>
                        </div>
                    </div>
                    <div class="carousel-slide" style="background-image: url('../images/image4.png');">
                        <div class="slide-caption">
                            <h2>NATIONAL SYMBOL TZ</h2>
                            <p>Tanzania's official coat of arms.</p>
                        </div>
                    </div>
                    <div class="carousel-slide" style="background-image: url('../images/image3.png');">
                        <div class="slide-caption">
                            <h2>NECTA</h2>
                            <p>Excellence since 2014</p>
                        </div>
                    </div>
                    <div class="carousel-slide" style="background-image: url('../images/image2.png');">
                        <div class="slide-caption">
                            <h2>MODERN CLASSROOM</h2>
                            <p>New classrooms ready for students</p>
                        </div>
                    </div>
                    <div class="carousel-indicators-custom">
                        <span class="indicator-dot active" data-index="0"></span>
                        <span class="indicator-dot" data-index="1"></span>
                        <span class="indicator-dot" data-index="2"></span>
                        <span class="indicator-dot" data-index="3"></span>
                        <span class="indicator-dot" data-index="4"></span>
                    </div>
                </div>
            </div>

            <div class="login-side">
                <div class="login-form-container">
                    <div class="welcome-title">Welcome Back</div>
                    <div class="welcome-sub">Login to School management System</div>

                    <?php if (!empty($error)): ?>
                        <div class="lock-alert" id="errorAlert">
                            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                            <?php if (strpos($error, 'minutes') !== false): ?>
                                <div class="lock-timer" id="lockTimer"></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="success-alert" id="successAlert">
                            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="unifiedLoginForm">
                        <div class="input-group-custom">
                            <label><i class="fas fa-user me-1"></i> Username</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" name="username" id="username" 
                                       placeholder="Email or Admission Number" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       required autofocus>
                            </div>
                        </div>

                        <div class="input-group-custom">
                            <label><i class="fas fa-lock me-1"></i> Password</label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" id="loginPassword" placeholder="Enter your password" required>
                                <span class="password-toggle-icon" onclick="togglePassword()">
                                    <i class="far fa-eye" id="toggleIcon"></i>
                                </span>
                            </div>
                            <div class="password-strength" id="passwordStrength">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                        </div>
                        
                        <button class="btn-signin" type="submit" id="submitBtn">
                            <i class="fas fa-sign-in-alt me-2"></i> Sign In
                        </button>
                        
                        <div class="login-footer-links">
                            <a href="../controller/forgot_password.php">
                                <i class="far fa-question-circle me-1"></i>Forgot Password?
                            </a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#helpModal">
                                <i class="far fa-question-circle me-1"></i>Need Help?
                            </a>
                        </div>
                        
                        <div class="user-type-hint" id="userTypeHint">
                            <i class="fas fa-shield-alt"></i> System auto-detects your user type <i class="fas fa-graduation-cap"></i>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-question-circle me-2"></i>Login Help</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="text-primary"><i class="fas fa-user-shield me-2"></i>Staff Login:</h6>
                    <ul>
                        <li><strong>Username:</strong> Your email address or phone number</li>
                        <li><strong>Password:</strong> Your account password</li>
                    </ul>
                    
                    <h6 class="text-success"><i class="fas fa-user-graduate me-2"></i>Student Login:</h6>
                    <ul>
                        <li><strong>Username:</strong> Admission number (e.g., ADM001)</li>
                        <li><strong>Password:</strong> Your student account password</li>
                    </ul>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Security Notice:</strong> Do not allow to save password on device.
                    </div>
                    <p class="mb-0">Contact school Head Master for manual assistance or password reset.</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Carousel functionality
        (function() {
            const slides = document.querySelectorAll('.carousel-slide');
            const dots = document.querySelectorAll('.indicator-dot');
            if (!slides.length) return;
            let currentIndex = 0, intervalId;
            
            function showSlide(index) {
                if (index < 0) index = slides.length-1; 
                if (index >= slides.length) index = 0;
                slides.forEach(s => s.classList.remove('active'));
                dots.forEach(d => d.classList.remove('active'));
                slides[index].classList.add('active');
                if(dots[index]) dots[index].classList.add('active');
                currentIndex = index;
            }
            
            function nextSlide() { showSlide(currentIndex + 1); }
            
            function startAutoSlide() { 
                if(intervalId) clearInterval(intervalId); 
                intervalId = setInterval(nextSlide, 5000); 
            }
            
            dots.forEach((dot, idx) => { 
                dot.addEventListener('click', (e) => { 
                    e.stopPropagation(); 
                    showSlide(idx); 
                    startAutoSlide(); 
                }); 
            });
            
            showSlide(0); 
            startAutoSlide();
            
            const carouselSide = document.querySelector('.carousel-side');
            if(carouselSide) {
                carouselSide.addEventListener('mouseenter', () => clearInterval(intervalId));
                carouselSide.addEventListener('mouseleave', startAutoSlide);
            }
        })();

        function togglePassword() {
            const passwordField = document.getElementById('loginPassword');
            const toggleIcon = document.getElementById('toggleIcon');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        const errorAlert = document.getElementById('errorAlert');
        if (errorAlert) {
            setTimeout(() => { 
                errorAlert.style.transition = 'opacity 0.5s'; 
                errorAlert.style.opacity = '0'; 
                setTimeout(() => errorAlert.remove(), 600); 
            }, 5000);
        }

        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(() => { 
                successAlert.style.transition = 'opacity 0.5s'; 
                successAlert.style.opacity = '0'; 
                setTimeout(() => successAlert.remove(), 600); 
            }, 5000);
        }

        const loadingOverlay = document.getElementById('loadingOverlay');
        const loginForm = document.getElementById('unifiedLoginForm');

        function hideLoadingAfterDelay() {
            setTimeout(() => {
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('active');
                }
            }, 10000);
        }

        loginForm?.addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const username = document.querySelector('input[name="username"]')?.value;
            const password = document.querySelector('input[name="password"]')?.value;
            
            if (!username || !password) {
                e.preventDefault();
                return;
            }
            
            if (loadingOverlay) {
                loadingOverlay.classList.add('active');
            }
            
            if(btn) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Signing in...';
                btn.disabled = true;
            }
            
            hideLoadingAfterDelay();
        });

        window.addEventListener('load', function() {
            hideLoadingAfterDelay();
        });

        function updateLockTimer() {
            const timerElement = document.getElementById('lockTimer');
            if (!timerElement) return;
            
            const errorText = document.querySelector('.lock-alert')?.innerText || '';
            const match = errorText.match(/(\d+)\s*minutes?/);
            
            if (match) {
                let minutes = parseInt(match[1]);
                let seconds = 0;
                
                const interval = setInterval(() => {
                    if (seconds === 0) {
                        if (minutes === 0) {
                            clearInterval(interval);
                            location.reload();
                            return;
                        }
                        minutes--;
                        seconds = 59;
                    } else {
                        seconds--;
                    }
                    
                    timerElement.innerHTML = `⏱️ Time remaining: ${minutes}:${seconds.toString().padStart(2, '0')}`;
                }, 1000);
            }
        }
        
        if (document.getElementById('lockTimer')) {
            updateLockTimer();
        }

        document.getElementById('username')?.addEventListener('input', function(e) {
            const username = e.target.value;
            const hint = document.getElementById('userTypeHint');
            
            if (username.includes('@')) {
                hint.innerHTML = '<i class="fas fa-shield-alt text-primary"></i> Staff login detected <i class="fas fa-user-shield"></i>';
            } else if (username.toUpperCase().startsWith('ADM') || username.match(/^[0-9]+$/)) {
                hint.innerHTML = '<i class="fas fa-graduation-cap text-success"></i> Student login detected <i class="fas fa-user-graduate"></i>';
            } else {
                hint.innerHTML = '<i class="fas fa-shield-alt"></i> System auto-detects your user type <i class="fas fa-graduation-cap"></i>';
            }
        });

        document.getElementById('loginPassword')?.addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthBar = document.getElementById('strengthBar');
            if (!strengthBar) return;
            
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                strengthBar.className = 'password-strength-bar';
            } else if (password.length < 6) {
                strengthBar.className = 'password-strength-bar strength-weak';
            } else if (password.length < 10) {
                strengthBar.className = 'password-strength-bar strength-medium';
            } else {
                strengthBar.className = 'password-strength-bar strength-strong';
            }
        });

        let formSubmitted = false;
        loginForm?.addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return;
            }
            formSubmitted = true;
            setTimeout(() => { formSubmitted = false; }, 5000);
        });
    </script>
</body>
</html>