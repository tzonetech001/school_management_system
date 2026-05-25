<?php
// otp.php
session_start();
require_once '../controller/db_connect.php';

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

// Beem Africa API Credentials
define('BEEM_API_KEY', '5e3de5075687abf8');
define('BEEM_SECRET_KEY', 'MDRhM2MxNGUxZGNmYmRjNDMzYzVmYjlkY2MyM2UxNTRmNjMyNzU2YTg2OGRjMmQ5YmMxZjdiODRkZTg2ZjQwYQ==');
define('BEEM_SOURCE_ADDR', 'MUYOVOZI HS');

$error = '';
$success = '';
$show_form = true;

// Generate OTP function
function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

// Format phone number to international format
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

// Send SMS via Beem Africa API
function sendSMS($phone_number, $message) {
    $api_key = BEEM_API_KEY;
    $secret_key = BEEM_SECRET_KEY;
    $source_addr = BEEM_SOURCE_ADDR;
    
    $phone_number = formatPhoneNumber($phone_number);
    
    $postData = array(
        'source_addr' => $source_addr,
        'encoding' => 0,
        'schedule_time' => '',
        'message' => $message,
        'recipients' => [
            array(
                'recipient_id' => '1',
                'dest_addr' => $phone_number
            )
        ]
    );

    $Url = 'https://apisms.beem.africa/v1/send';

    $ch = curl_init($Url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt_array($ch, array(
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Basic ' . base64_encode("$api_key:$secret_key"),
            'Content-Type: application/json'
        ),
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_TIMEOUT => 30
    ));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if($response === FALSE) {
        error_log("SMS Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    error_log("SMS Response: " . $response);
    error_log("HTTP Code: " . $http_code);
    
    $response_data = json_decode($response, true);
    
    if ($http_code == 200 && isset($response_data['successful']) && $response_data['successful'] === true) {
        return true;
    }
    
    return false;
}

// Ensure password_resets table exists
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
            otp VARCHAR(10) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT DEFAULT 0,
            purpose VARCHAR(50) DEFAULT 'password_reset',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_otp (otp),
            INDEX idx_user (user_type, user_id)
        )";
        $conn->query($sql);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_otp'])) {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $error = "Tafadhali weka barua pepe.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Barua pepe si sahihi.";
        } else {
            ensurePasswordResetsTable($conn);
            
            // Search for staff in admins table
            $sql = "SELECT id, first_name, last_name, email, phone_number FROM admins 
                   WHERE email = ? AND status = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $staff = $result->fetch_assoc(); // FIXED: was $result->fetch_assostaff
                
                // Generate OTP and token
                $otp = generateOTP();
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                // Delete old unused OTPs
                $clean = "DELETE FROM password_resets WHERE user_id = ? AND user_type = 'staff' AND used = 0";
                $clean_stmt = $conn->prepare($clean);
                $clean_stmt->bind_param("i", $staff['id']);
                $clean_stmt->execute();
                
                // Save to password_resets table
                $insert = "INSERT INTO password_resets (user_type, user_id, email, phone, token, otp, expires_at) 
                          VALUES ('staff', ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert);
                $insert_stmt->bind_param("isssss", $staff['id'], $staff['email'], $staff['phone_number'], $token, $otp, $expiry);
                
                if ($insert_stmt->execute()) {
                    // Check if phone number exists
                    if (!empty($staff['phone_number'])) {
                        $formatted_phone = formatPhoneNumber($staff['phone_number']);
                        
                        // Send SMS
                        $message = "MUYOVOZI HS: OTP yako ni $otp. Itaisha kwa dakika 10. Usishiriki namba hii.";
                        $sms_sent = sendSMS($formatted_phone, $message);
                        
                        if ($sms_sent) {
                            $success = "OTP imetumwa kwa namba ya simu ya staff.";
                            
                            // Store in session
                            $_SESSION['otp_token'] = $token;
                            $_SESSION['otp_staff_id'] = $staff['id'];
                            $_SESSION['otp_email'] = $staff['email'];
                            $_SESSION['otp_expiry'] = $expiry;
                            
                            $show_form = false;
                        } else {
                            $error = "OTP imeundwa lakini imeshindwa kutuma SMS. Tafadhali jaribu tena.";
                        }
                    } else {
                        $error = "Namba ya simu ya staff haipatikani. Wasiliana na Admin.";
                    }
                } else {
                    $error = "Kuna tatizo katika kuunda OTP. Jaribu tena.";
                }
            } else {
                $error = "Hakuna staff aliyepatikana kwa barua pepe hii.";
            }
        }
    } elseif (isset($_POST['verify_otp'])) {
        $otp = trim($_POST['otp']);
        $token = $_SESSION['otp_token'] ?? '';
        
        if (empty($otp)) {
            $error = "Tafadhali weka OTP.";
        } elseif (empty($token)) {
            $error = "Session imeisha. Tafadhali anza upya.";
        } else {
            // Verify OTP
            $sql = "SELECT * FROM password_resets 
                   WHERE token = ? AND otp = ? AND expires_at > NOW() AND used = 0 
                   ORDER BY created_at DESC LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $token, $otp);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $otp_data = $result->fetch_assoc();
                
                // Mark as used
                $update = "UPDATE password_resets SET used = 1 WHERE id = ?";
                $update_stmt = $conn->prepare($update);
                $update_stmt->bind_param("i", $otp_data['id']);
                $update_stmt->execute();
                
                $_SESSION['otp_verified'] = true;
                $_SESSION['verified_staff_id'] = $otp_data['user_id'];
                
                $success = "OTP imethibitishwa kikamilifu!";
                $show_form = false;
            } else {
                $error = "OTP si sahihi au imekwisha muda wake.";
            }
        }
    }
}

// Check if OTP is verified
$otp_verified = isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Manager - Muyovozi High School</title>
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
        }
        
        .otp-container {
            max-width: 450px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 25, 45, 0.2);
            overflow: hidden;
        }
        
        .otp-header {
            background: linear-gradient(135deg, #3B9DB3, #1c6c80);
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .otp-header h2 {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 1.8rem;
        }
        
        .otp-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .otp-body {
            padding: 30px 25px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: #3B9DB3;
            box-shadow: 0 0 0 0.2rem rgba(59, 157, 179, 0.25);
        }
        
        .btn-otp {
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
        
        .btn-otp:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3);
        }
        
        .btn-outline-otp {
            background: transparent;
            color: #3B9DB3;
            border: 2px solid #3B9DB3;
            border-radius: 10px;
            padding: 10px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-outline-otp:hover {
            background: #3B9DB3;
            color: white;
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
        
        .otp-input {
            letter-spacing: 8px;
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .info-box i {
            font-size: 3rem;
            color: #3B9DB3;
            margin-bottom: 10px;
        }
        
        .timer {
            font-size: 1rem;
            color: #dc3545;
            font-weight: 600;
            text-align: center;
            margin: 10px 0;
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
    </style>
</head>
<body>
    <div class="otp-container">
        <div class="otp-header">
            <h2><i class="fas fa-shield-alt me-2"></i>OTP Manager</h2>
            <p>Tuma OTP kwa staff</p>
        </div>
        
        <div class="otp-body">
            <!-- Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($otp_verified): ?>
                <!-- Success Message -->
                <div class="info-box">
                    <i class="fas fa-check-circle text-success"></i>
                    <h5 class="mt-2">OTP Imethibitishwa!</h5>
                    <p class="text-muted mb-0">Unaweza kuendelea na operesheni zako.</p>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="otp.php?restart=1" class="btn btn-outline-otp">
                        <i class="fas fa-redo-alt me-2"></i>Tuma OTP Nyingine
                    </a>
                    <a href="../dashboard.php" class="btn btn-otp">
                        <i class="fas fa-home me-2"></i>Rudi kwenye Dashboard
                    </a>
                </div>
                
            <?php elseif ($show_form && !isset($_POST['send_otp'])): ?>
                <!-- Email Form -->
                <form method="POST">
                    <input type="hidden" name="send_otp" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-envelope me-1"></i>Barua Pepe ya Staff
                        </label>
                        <input type="email" class="form-control" name="email" 
                               placeholder="staff@muyovozi.ac.tz" required>
                        <small class="text-muted">
                            Weka barua pepe ya staff kupata OTP kwenye simu yake
                        </small>
                    </div>
                    
                    <button type="submit" class="btn-otp">
                        <i class="fas fa-paper-plane me-2"></i>Tuma OTP
                    </button>
                </form>
                
            <?php elseif (!$otp_verified): ?>
                <!-- OTP Verification Form -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> 
                    OTP imetumwa kwa namba ya simu ya <?php echo htmlspecialchars($_SESSION['otp_email'] ?? ''); ?>
                </div>
                
                <div class="timer" id="timer">Dakika 10 zimesalia</div>
                
                <form method="POST">
                    <input type="hidden" name="verify_otp" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-key me-1"></i>Weka OTP
                        </label>
                        <input type="text" class="form-control otp-input" name="otp" 
                               maxlength="6" pattern="\d{6}" placeholder="______" 
                               inputmode="numeric" required>
                    </div>
                    
                    <button type="submit" class="btn-otp">
                        <i class="fas fa-check-circle me-2"></i>Thibitisha OTP
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="?resend=1" class="text-muted small">
                            <i class="fas fa-redo-alt me-1"></i>Tuma OTP tena
                        </a>
                    </div>
                </form>
            <?php endif; ?>
            
            <!-- Restart Link -->
            <?php if (!$otp_verified && isset($_POST['send_otp'])): ?>
                <div class="link-back mt-3">
                    <a href="?restart=1"><i class="fas fa-arrow-left me-1"></i>Anza upya</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Timer
        function startTimer(duration, display) {
            let timer = duration, minutes, seconds;
            const interval = setInterval(function () {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);
                
                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;
                
                display.textContent = minutes + ":" + seconds + " zimesalia";
                
                if (--timer < 0) {
                    clearInterval(interval);
                    display.textContent = "OTP imekwisha muda";
                    display.style.color = '#dc3545';
                }
            }, 1000);
        }
        
        <?php if (!$otp_verified && isset($_POST['send_otp'])): ?>
        window.onload = function() {
            const timer = document.getElementById('timer');
            if (timer) {
                <?php if (isset($_SESSION['otp_expiry'])): ?>
                const expiry = new Date("<?php echo $_SESSION['otp_expiry']; ?>").getTime();
                const now = new Date().getTime();
                const remaining = Math.max(0, Math.floor((expiry - now) / 1000));
                if (remaining > 0) {
                    startTimer(remaining, timer);
                } else {
                    timer.textContent = "OTP imekwisha muda";
                }
                <?php else: ?>
                startTimer(600, timer);
                <?php endif; ?>
            }
        };
        <?php endif; ?>
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Handle resend OTP
if (isset($_GET['resend']) && isset($_SESSION['otp_token'])) {
    $token = $_SESSION['otp_token'];
    
    $sql = "SELECT pr.*, a.first_name, a.last_name, a.phone_number, a.email 
            FROM password_resets pr
            JOIN admins a ON pr.user_id = a.id
            WHERE pr.token = ? AND pr.user_type = 'staff' AND pr.used = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        
        $new_otp = generateOTP();
        $new_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $update = "UPDATE password_resets SET otp = ?, expires_at = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update);
        $update_stmt->bind_param("ssi", $new_otp, $new_expiry, $data['id']);
        
        if ($update_stmt->execute()) {
            if (!empty($data['phone_number'])) {
                $formatted_phone = formatPhoneNumber($data['phone_number']);
                $message = "MUYOVOZI HS: OTP yako mpya ni $new_otp. Itaisha kwa dakika 10.";
                sendSMS($formatted_phone, $message);
                
                $_SESSION['otp_expiry'] = $new_expiry;
                
                header("Location: otp.php");
                exit();
            }
        }
    }
}

// Handle restart
if (isset($_GET['restart'])) {
    unset($_SESSION['otp_token']);
    unset($_SESSION['otp_staff_id']);
    unset($_SESSION['otp_email']);
    unset($_SESSION['otp_expiry']);
    unset($_SESSION['otp_verified']);
    unset($_SESSION['verified_staff_id']);
    header("Location: otp.php");
    exit();
}
?>