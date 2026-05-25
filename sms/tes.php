<?php
// sms.php - Full SMS System in One Page
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

// Beem Africa API Credentials - MOVED TO CONSTANTS AT TOP
define('BEEM_API_KEY', '5e3de5075687abf8');
define('BEEM_SECRET_KEY', 'MDRhM2MxNGUxZGNmYmRjNDMzYzVmYjlkY2MyM2UxNTRmNjMyNzU2YTg2OGRjMmQ5YmMxZjdiODRkZTg2ZjQwYQ==');
define('BEEM_SOURCE_ADDR', 'MUYOVOZI HS');
define('SMS_MAX_CHARS', 160);
define('SMS_WARNING_LIMIT', 140);

$admin_id = $_SESSION['admin_id'];
$message = '';
$message_type = '';

// Function to format phone number
function formatPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If already starts with 255, return as is (max 12 digits)
    if (substr($phone, 0, 3) === '255') {
        return substr($phone, 0, 12);
    }
    
    // If starts with 0, replace with 255
    if (substr($phone, 0, 1) === '0') {
        return '255' . substr($phone, 1, 9);
    }
    
    // If starts with 7 or 6, add 255
    if (substr($phone, 0, 1) === '7' || substr($phone, 0, 1) === '6') {
        return '255' . $phone;
    }
    
    return $phone;
}

// Function to send SMS
function sendSMS($phone_number, $message) {
    $api_key = BEEM_API_KEY;
    $secret_key = BEEM_SECRET_KEY;
    
    // Format phone number
    $phone_number = formatPhoneNumber($phone_number);
    
    // Prepare request data
    $postData = [
        "source_addr" => BEEM_SOURCE_ADDR,
        "encoding" => 0,
        "message" => $message,
        "recipients" => [
            [
                "recipient_id" => "1",
                "dest_addr" => $phone_number
            ]
        ]
    ];

    $Url = 'https://apisms.beem.africa/v1/send';

    $ch = curl_init($Url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt_array($ch, [
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode("$api_key:$secret_key"),
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    if ($http_code == 200 && isset($response_data['successful']) && $response_data['successful'] === true) {
        return ['success' => true, 'message' => 'SMS sent successfully'];
    } else {
        $error = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
        return ['success' => false, 'message' => "Failed: $error"];
    }
}

// Function to check balance
function checkBalance() {
    $api_key = BEEM_API_KEY;
    $secret_key = BEEM_SECRET_KEY;
    
    $Url = 'https://apisms.beem.africa/public/v1/vendors/balance';

    $ch = curl_init($Url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode("$api_key:$secret_key"),
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        if (isset($data['data']['credit_balance'])) {
            return number_format($data['data']['credit_balance'], 0);
        }
    }
    return '0';
}

// Check if sms_logs table exists, if not create it - REMOVED response and request_id fields
$table_check = $conn->query("SHOW TABLES LIKE 'sms_logs'");
if ($table_check->num_rows == 0) {
    $conn->query("CREATE TABLE sms_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        recipient VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_id (admin_id),
        INDEX idx_status (status),
        INDEX idx_sent_at (sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Get all staff with phone numbers
$staff = [];
$staff_result = $conn->query("SELECT id, first_name, last_name, phone_number, email, sex FROM admins WHERE phone_number IS NOT NULL AND phone_number != '' AND status = 1 ORDER BY first_name");
while ($row = $staff_result->fetch_assoc()) {
    $staff[] = $row;
}

// Get all parents with phone numbers
$parents = [];
$parent_result = $conn->query("SELECT id, first_name, last_name, parent_phone, class, combination, sex FROM students WHERE parent_phone IS NOT NULL AND parent_phone != '' AND is_leaver = FALSE AND status = 1 ORDER BY first_name");
while ($row = $parent_result->fetch_assoc()) {
    $parents[] = $row;
}

// Get recent logs with admin names
$logs = [];
$logs_result = $conn->query("SELECT l.*, a.first_name as admin_first, a.last_name as admin_last 
                            FROM sms_logs l 
                            LEFT JOIN admins a ON l.admin_id = a.id 
                            ORDER BY l.sent_at DESC 
                            LIMIT 50");
while ($row = $logs_result->fetch_assoc()) {
    $logs[] = $row;
}

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_messages,
    SUM(CASE WHEN status = 'Success' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) as failed,
    COUNT(DISTINCT DATE(sent_at)) as active_days,
    COUNT(DISTINCT recipient) as unique_recipients
    FROM sms_logs";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get today's stats
$today_stats_sql = "SELECT 
    COUNT(*) as today_messages,
    SUM(CASE WHEN status = 'Success' THEN 1 ELSE 0 END) as today_success
    FROM sms_logs 
    WHERE DATE(sent_at) = CURDATE()";
$today_stats_result = $conn->query($today_stats_sql);
$today_stats = $today_stats_result->fetch_assoc();

// Handle SMS sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $selected = $_POST['selected'] ?? [];
    $sms_message = trim($_POST['message'] ?? '');
    $message_type_send = $_POST['message_type'] ?? 'general'; // general, exam, fee, holiday, emergency
    
    if (empty($sms_message)) {
        $message = "Please enter a message";
        $message_type = "error";
    } elseif (empty($selected)) {
        $message = "Please select at least one recipient";
        $message_type = "error";
    } else {
        $success_count = 0;
        $fail_count = 0;
        $details = [];
        
        foreach ($selected as $item) {
            list($type, $id) = explode('_', $item);
            $phone = '';
            $name = '';
            
            if ($type == 'staff') {
                foreach ($staff as $s) {
                    if ($s['id'] == $id) {
                        $phone = $s['phone_number'];
                        $name = $s['first_name'] . ' ' . $s['last_name'];
                        break;
                    }
                }
            } else {
                foreach ($parents as $p) {
                    if ($p['id'] == $id) {
                        $phone = $p['parent_phone'];
                        $name = $p['first_name'] . ' ' . $p['last_name'] . ' (Parent of ' . $p['class'] . ' ' . $p['combination'] . ')';
                        break;
                    }
                }
            }
            
            if (!empty($phone)) {
                $result = sendSMS($phone, $sms_message);
                
                // Log to database - REMOVED response and request_id
                $status = $result['success'] ? 'Success' : 'Failed';
                
                $stmt = $conn->prepare("INSERT INTO sms_logs (admin_id, recipient, phone, message, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $admin_id, $name, $phone, $sms_message, $status);
                $stmt->execute();
                
                if ($result['success']) {
                    $success_count++;
                } else {
                    $fail_count++;
                    $details[] = "$name: " . $result['message'];
                }
            }
        }
        
        if ($success_count > 0) {
            $message = "✅ SMS sent successfully to $success_count recipients";
            if ($fail_count > 0) {
                $message .= ", $fail_count failed";
            }
            $message_type = "success";
            
            // Store details for display
            if (!empty($details)) {
                $_SESSION['sms_details'] = $details;
            }
        } else {
            $message = "❌ Failed to send any SMS. Check logs for details.";
            $message_type = "error";
        }
    }
}

$balance = checkBalance();
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header with Actions -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-comment-dots me-2" style="color: #3B9DB3;"></i>
                    SMS Messaging Center
                </h2>
                <p class="text-muted">
                    <i class="fas fa-clock me-1"></i><?php echo date('l, d F Y H:i'); ?> |
                    <i class="fas fa-globe me-1 ms-2"></i>Africa/Dar_es_Salaam
                </p>
            </div>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" id="actionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-2"></i>SMS Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionsDropdown">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#templatesModal"><i class="fas fa-file-alt me-2"></i>Message Templates</a></li>
                    <li><a class="dropdown-item" href="#" onclick="location.reload()"><i class="fas fa-sync-alt me-2"></i>Refresh Balance</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logsModal"><i class="fas fa-history me-2"></i>View All Logs</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportLogs()"><i class="fas fa-download me-2"></i>Export Logs</a></li>
                </ul>
            </div>
        </div>

        <!-- SweetAlert2 Messages -->
        <?php if (!empty($message)): ?>
            <div id="smsMessage" data-message="<?php echo htmlspecialchars($message); ?>" data-type="<?php echo $message_type; ?>"></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['sms_details'])): ?>
            <div id="smsDetails" data-details='<?php echo json_encode($_SESSION['sms_details']); ?>'></div>
            <?php unset($_SESSION['sms_details']); ?>
        <?php endif; ?>

        <!-- Statistics Cards (like students.php) -->
        <div class="row mb-4">
            <!-- Balance Card -->
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-wallet" style="color: #28a745;"></i>
                    </div>
                    <h3 style="color: #28a745;"><?php echo number_format($balance); ?> TZS</h3>
                    <p>SMS Balance</p>
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>Cost per SMS: ~50 TZS
                    </small>
                </div>
            </div>
            
            <!-- Total Messages Card -->
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-envelope" style="color: #3B9DB3;"></i>
                    </div>
                    <h3>
                        <span style="color: #28a745;"><?php echo number_format($stats['successful'] ?? 0); ?></span>
                        <span class="text-muted mx-1">/</span>
                        <span style="color: #dc3545;"><?php echo number_format($stats['failed'] ?? 0); ?></span>
                    </h3>
                    <p>Success / Failed</p>
                    <small class="text-muted">Total: <?php echo number_format($stats['total_messages'] ?? 0); ?> messages</small>
                </div>
            </div>
            
            <!-- Today's Messages Card -->
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-calendar-day" style="color: #ffc107;"></i>
                    </div>
                    <h3>
                        <span style="color: #28a745;"><?php echo number_format($today_stats['today_success'] ?? 0); ?></span>
                        <span class="text-muted mx-1">/</span>
                        <span><?php echo number_format($today_stats['today_messages'] ?? 0); ?></span>
                    </h3>
                    <p>Today's Messages</p>
                    <small class="text-muted"><?php echo date('d M Y'); ?></small>
                </div>
            </div>
            
            <!-- Recipients Card -->
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-users" style="color: #6f42c1;"></i>
                    </div>
                    <h3>
                        <span style="color: #3B9DB3;"><?php echo count($staff); ?></span>
                        <span class="text-muted mx-1">/</span>
                        <span style="color: #e83e8c;"><?php echo count($parents); ?></span>
                    </h3>
                    <p>Staff / Parents</p>
                    <small class="text-muted">Total: <?php echo count($staff) + count($parents); ?> recipients</small>
                </div>
            </div>
        </div>

        <!-- Main Row: Compose + Logs -->
        <div class="row">
            <!-- Left Column - Compose Message -->
            <div class="col-lg-7 mb-4">
                <div class="card">
                    <div class="card-header" style="background-color: #3B9DB3; color: white;">
                        <h4 class="mb-0">
                            <i class="fas fa-pen-alt me-2"></i>
                            Compose New Message
                        </h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="smsForm">
                            <!-- Message Type Selection -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Message Type</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="message_type" id="typeGeneral" value="general" checked>
                                        <label class="form-check-label" for="typeGeneral">
                                            <i class="fas fa-comment text-info me-1"></i>General
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="message_type" id="typeExam" value="exam">
                                        <label class="form-check-label" for="typeExam">
                                            <i class="fas fa-graduation-cap text-success me-1"></i>Exam
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="message_type" id="typeFee" value="fee">
                                        <label class="form-check-label" for="typeFee">
                                            <i class="fas fa-money-bill text-warning me-1"></i>Fee
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="message_type" id="typeHoliday" value="holiday">
                                        <label class="form-check-label" for="typeHoliday">
                                            <i class="fas fa-umbrella-beach text-primary me-1"></i>Holiday
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="message_type" id="typeEmergency" value="emergency">
                                        <label class="form-check-label" for="typeEmergency">
                                            <i class="fas fa-exclamation-triangle text-danger me-1"></i>Emergency
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Message Input Area -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-comment me-2"></i>Your Message
                                    <button type="button" class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#templatesModal">
                                        <i class="fas fa-file-alt me-1"></i>Use Template
                                    </button>
                                </label>
                                <textarea name="message" id="message" class="form-control message-input" rows="5" maxlength="<?php echo SMS_MAX_CHARS; ?>" 
                                          placeholder="Type your message here... (max <?php echo SMS_MAX_CHARS; ?> characters)" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                                
                                <!-- Character Counter with Progress -->
                                <div class="mt-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="char-counter" id="charCounter">
                                            <i class="fas fa-text-height me-1"></i>
                                            <span id="charCount">0</span>/<?php echo SMS_MAX_CHARS; ?>
                                        </span>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <span id="smsCount">1 SMS</span>
                                        </small>
                                    </div>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" id="charProgress" role="progressbar" style="width: 0%; background-color: #3B9DB3;"></div>
                                    </div>
                                </div>
                            </div>
                             <!-- Send Button -->
                            <button type="submit" name="send_sms" class="btn btn-primary btn-send w-100">
                                <i class="fas fa-paper-plane me-2"></i>
                                Send Message
                                <span class="badge bg-light text-dark ms-2" id="selectedCountPreview">0 selected</span>
                            </button>

                            <!-- Quick Templates Row -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Quick Templates</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="insertTemplate('exam')">
                                        <i class="fas fa-graduation-cap me-1"></i>Exam Results
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="insertTemplate('fee')">
                                        <i class="fas fa-money-bill me-1"></i>Fee Reminder
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="insertTemplate('meeting')">
                                        <i class="fas fa-users me-1"></i>Meeting
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="insertTemplate('holiday')">
                                        <i class="fas fa-umbrella-beach me-1"></i>Holiday
                                    </button>
                                </div>
                            </div>

                            <!-- Recipients Selection Area -->
                            <div class="mb-4">
                                <h5 class="section-title">
                                    <i class="fas fa-users me-2"></i>Select Recipients
                                </h5>

                                <!-- Search and Filter -->
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                            <input type="text" id="searchRecipients" class="form-control" placeholder="Search by name or phone...">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <select id="typeFilter" class="form-select">
                                            <option value="all">All Recipients</option>
                                            <option value="staff">Staff Only</option>
                                            <option value="parents">Parents Only</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Select All Controls -->
                                <div class="select-all-card mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <input type="checkbox" id="selectAllStaff" class="form-check-input me-2" onchange="toggleStaffAll()">
                                            <label for="selectAllStaff" class="fw-bold">Select All Staff (<?php echo count($staff); ?>)</label>
                                        </div>
                                        <div>
                                            <input type="checkbox" id="selectAllParents" class="form-check-input me-2" onchange="toggleParentsAll()">
                                            <label for="selectAllParents" class="fw-bold">Select All Parents (<?php echo count($parents); ?>)</label>
                                        </div>
                                        <span class="badge-count" id="selectedCount">0 selected</span>
                                    </div>
                                </div>

                                <!-- Staff List -->
                                <div class="recipient-section mb-3">
                                    <h6 class="fw-bold mb-3" style="color: #3B9DB3;">
                                        <i class="fas fa-chalkboard-teacher me-2"></i>
                                        Staff Members
                                        <span class="badge bg-secondary ms-2" id="staffCount"><?php echo count($staff); ?></span>
                                    </h6>
                                    <div class="recipient-list" id="staffList">
                                        <?php if (empty($staff)): ?>
                                            <p class="text-muted text-center py-3">No staff members with phone numbers found.</p>
                                        <?php else: ?>
                                            <?php foreach ($staff as $s): ?>
                                            <div class="recipient-item" data-type="staff" data-name="<?php echo strtolower($s['first_name'] . ' ' . $s['last_name']); ?>" data-phone="<?php echo $s['phone_number']; ?>">
                                                <input type="checkbox" name="selected[]" value="staff_<?php echo $s['id']; ?>" class="recipient-checkbox staff-checkbox form-check-input" onchange="updateSelectedCount()">
                                                <div class="avatar-circle">
                                                    <?php if ($s['sex'] == 'Female'): ?>
                                                        <i class="fas fa-female" style="color: #e83e8c;"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-male" style="color: #007bff;"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-phone me-1"></i><?php echo $s['phone_number']; ?>
                                                        <?php if (!empty($s['email'])): ?>
                                                            <span class="ms-2"><i class="fas fa-envelope"></i> <?php echo $s['email']; ?></span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Parents List -->
                                <div class="recipient-section">
                                    <h6 class="fw-bold mb-3" style="color: #e83e8c;">
                                        <i class="fas fa-user-friends me-2"></i>
                                        Parents
                                        <span class="badge bg-secondary ms-2" id="parentsCount"><?php echo count($parents); ?></span>
                                    </h6>
                                    <div class="recipient-list" id="parentsList">
                                        <?php if (empty($parents)): ?>
                                            <p class="text-muted text-center py-3">No parents with phone numbers found.</p>
                                        <?php else: ?>
                                            <?php foreach ($parents as $p): ?>
                                            <div class="recipient-item" data-type="parent" data-name="<?php echo strtolower($p['first_name'] . ' ' . $p['last_name']); ?>" data-phone="<?php echo $p['parent_phone']; ?>" data-class="<?php echo strtolower($p['class']); ?>" data-combination="<?php echo strtolower($p['combination']); ?>">
                                                <input type="checkbox" name="selected[]" value="parent_<?php echo $p['id']; ?>" class="recipient-checkbox parents-checkbox form-check-input" onchange="updateSelectedCount()">
                                                <div class="avatar-circle">
                                                    <?php if ($p['sex'] == 'Female'): ?>
                                                        <i class="fas fa-female" style="color: #e83e8c;"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-male" style="color: #007bff;"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-graduation-cap me-1"></i><?php echo $p['class'] . ' - ' . $p['combination']; ?><br>
                                                        <i class="fas fa-phone me-1"></i><?php echo $p['parent_phone']; ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                           
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column - SMS Logs -->
            <div class="col-lg-5 mb-4">
                <div class="card">
                    <div class="card-header" style="background-color: #3B9DB3; color: white;">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Recent SMS Logs
                            </h4>
                            <span class="badge bg-light text-dark">Last 50 Messages</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($logs)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Messages Yet</h5>
                                <p class="text-muted small">Send your first SMS to see logs here</p>
                            </div>
                        <?php else: ?>
                            <div class="logs-container" style="max-height: 600px; overflow-y: auto;">
                                <?php foreach ($logs as $log): ?>
                                <div class="log-item <?php echo strtolower($log['status']); ?>">
                                    <div class="log-header">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <span class="fw-bold"><?php echo htmlspecialchars($log['recipient']); ?></span>
                                                <span class="log-time">
                                                    <i class="far fa-clock ms-2 me-1"></i>
                                                    <?php echo date('H:i', strtotime($log['sent_at'])); ?>
                                                    <span class="text-muted"><?php echo date('d/m/Y', strtotime($log['sent_at'])); ?></span>
                                                </span>
                                            </div>
                                            <span class="status-badge status-<?php echo strtolower($log['status']); ?>">
                                                <?php if ($log['status'] == 'Success'): ?>
                                                    <i class="fas fa-check-circle me-1"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-exclamation-circle me-1"></i>
                                                <?php endif; ?>
                                                <?php echo $log['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="log-body">
                                        <div class="message-preview">
                                            <i class="fas fa-comment me-2 text-muted"></i>
                                            <?php echo htmlspecialchars(substr($log['message'], 0, 100)); ?>
                                            <?php if (strlen($log['message']) > 100): ?>
                                                <span class="text-primary" style="cursor: pointer;" onclick="showFullMessage(<?php echo $log['id']; ?>, '<?php echo htmlspecialchars(addslashes($log['message'])); ?>')">...more</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="log-details mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-phone me-1"></i><?php echo $log['phone']; ?>
                                                <?php if (!empty($log['admin_first'])): ?>
                                                    <span class="ms-3">
                                                        <i class="fas fa-user me-1"></i>By: <?php echo htmlspecialchars($log['admin_first'] . ' ' . $log['admin_last']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($logs)): ?>
                    <div class="card-footer bg-light text-center">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Showing recent <?php echo count($logs); ?> messages
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Templates Modal -->
<div class="modal fade" id="templatesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>Message Templates</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <button type="button" class="list-group-item list-group-item-action" onclick="useTemplate('exam')">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-graduation-cap text-success me-2"></i>Exam Results</h6>
                            <small>Click to use</small>
                        </div>
                        <p class="mb-1 text-muted">Dear Parent, your child's exam results are now available. Please check the school portal or visit school office.</p>
                    </button>
                    
                    <button type="button" class="list-group-item list-group-item-action" onclick="useTemplate('fee')">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-money-bill text-warning me-2"></i>Fee Reminder</h6>
                            <small>Click to use</small>
                        </div>
                        <p class="mb-1 text-muted">Dear Parent, this is a reminder that school fees are due by 25th of this month. Please make payments on time to avoid penalties.</p>
                    </button>
                    
                    <button type="button" class="list-group-item list-group-item-action" onclick="useTemplate('meeting')">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-users text-info me-2"></i>Parents Meeting</h6>
                            <small>Click to use</small>
                        </div>
                        <p class="mb-1 text-muted">Dear Parent, there will be a parents meeting on Saturday at 9:00 AM in the school hall. Your attendance is highly appreciated.</p>
                    </button>
                    
                    <button type="button" class="list-group-item list-group-item-action" onclick="useTemplate('holiday')">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-umbrella-beach text-primary me-2"></i>Holiday Notice</h6>
                            <small>Click to use</small>
                        </div>
                        <p class="mb-1 text-muted">Dear Parent, school will close for holidays on Friday at 1:00 PM. Students should be picked up on time. School reopens on January 15th.</p>
                    </button>
                    
                    <button type="button" class="list-group-item list-group-item-action" onclick="useTemplate('emergency')">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Emergency</h6>
                            <small>Click to use</small>
                        </div>
                        <p class="mb-1 text-muted">URGENT: Due to unforeseen circumstances, school will close early today at 12:00 PM. Please arrange to pick up your child immediately.</p>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Full Message Modal -->
<div class="modal fade" id="fullMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                <h5 class="modal-title"><i class="fas fa-comment me-2"></i>Full Message</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="fullMessageText" class="lead"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View All Logs Modal -->
<div class="modal fade" id="logsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i>All SMS Logs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="logsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Recipient</th>
                                <th>Phone</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Sent By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $all_logs = $conn->query("SELECT l.*, a.first_name as admin_first, a.last_name as admin_last 
                                                     FROM sms_logs l 
                                                     LEFT JOIN admins a ON l.admin_id = a.id 
                                                     ORDER BY l.sent_at DESC 
                                                     LIMIT 500");
                            while ($log = $all_logs->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($log['sent_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['recipient']); ?></td>
                                <td><?php echo $log['phone']; ?></td>
                                <td><?php echo htmlspecialchars(substr($log['message'], 0, 50)) . '...'; ?></td>
                                <td>
                                    <span class="status-<?php echo strtolower($log['status']); ?>">
                                        <?php echo $log['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['admin_first'] . ' ' . $log['admin_last']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="exportAllLogs()">
                    <i class="fas fa-download me-2"></i>Export CSV
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<style>
/* SMS Page Specific Styles - Matching students.php */

.stats-card.simple-card {
    border: none;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.stats-card.simple-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: #3B9DB3;
}

.stats-card.simple-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.stats-card.simple-card .stats-icon {
    margin-bottom: 10px;
}

.stats-card.simple-card .stats-icon i {
    font-size: 2.2rem;
}

.stats-card.simple-card h3 {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 10px 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.card-header {
    background: linear-gradient(135deg, #3B9DB3, #2d7c8f);
    color: white;
    padding: 1.25rem 1.5rem;
    border-bottom: none;
    border-radius: 12px 12px 0 0 !important;
    font-weight: 500;
}

.message-input {
    border: 2px solid #e0e7ff;
    border-radius: 10px;
    padding: 15px;
    font-size: 16px;
    transition: all 0.3s;
}

.message-input:focus {
    border-color: #3B9DB3;
    box-shadow: 0 0 0 3px rgba(59, 157, 179, 0.1);
}

.char-counter {
    background: #f0f3ff;
    padding: 5px 12px;
    border-radius: 50px;
    color: #3B9DB3;
    font-weight: 600;
    font-size: 14px;
}

.progress {
    background-color: #e9ecef;
    border-radius: 10px;
}

.progress-bar {
    border-radius: 10px;
    transition: width 0.3s ease;
}

.section-title {
    color: #3B9DB3;
    font-weight: 700;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(59, 157, 179, 0.2);
}

.select-all-card {
    background: linear-gradient(135deg, rgba(59, 157, 179, 0.05), rgba(45, 124, 143, 0.05));
    border: 2px solid rgba(59, 157, 179, 0.2);
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 20px;
}

.badge-count {
    background: #3B9DB3;
    color: white;
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 14px;
}

.recipient-section {
    background: #f8faff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid rgba(59, 157, 179, 0.1);
}

.recipient-list {
    max-height: 300px;
    overflow-y: auto;
    border-radius: 8px;
}

.recipient-item {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.2s;
    cursor: pointer;
}

.recipient-item:hover {
    border-color: #3B9DB3;
    background: #e9f2f5;
    transform: translateX(5px);
}

.recipient-item.selected {
    background: linear-gradient(135deg, rgba(59, 157, 179, 0.1), rgba(45, 124, 143, 0.1));
    border-color: #3B9DB3;
    border-left-width: 4px;
}

.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: rgba(59, 157, 179, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-circle i {
    font-size: 1.2rem;
}

.form-check-input:checked {
    background-color: #3B9DB3;
    border-color: #3B9DB3;
}

.btn-primary {
    background-color: #3B9DB3;
    border-color: #3B9DB3;
}

.btn-primary:hover {
    background-color: #2d7c8f;
    border-color: #2d7c8f;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 157, 179, 0.3);
}

.btn-send {
    padding: 15px;
    font-size: 18px;
    font-weight: 600;
    border-radius: 10px;
}

/* Logs Styling */
.logs-container {
    padding: 10px;
}

.log-item {
    border-left: 4px solid;
    margin-bottom: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    transition: all 0.2s;
}

.log-item:hover {
    background: white;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

.log-item.success {
    border-left-color: #28a745;
}

.log-item.failed {
    border-left-color: #dc3545;
}

.log-header {
    margin-bottom: 10px;
}

.log-time {
    font-size: 12px;
    color: #6c757d;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

.status-success {
    background-color: #d4edda;
    color: #155724;
}

.status-failed {
    background-color: #f8d7da;
    color: #721c24;
}

.log-body {
    font-size: 14px;
}

.message-preview {
    background: white;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

/* Table Styling */
.table th {
    font-weight: 600;
    color: #333;
    background-color: rgba(59, 157, 179, 0.05);
    border-bottom: 2px solid rgba(59, 157, 179, 0.2);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .stats-card.simple-card {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
    
    .recipient-item {
        flex-wrap: wrap;
    }
    
    .select-all-card .d-flex {
        flex-direction: column;
        gap: 10px;
    }
    
    .modal-dialog {
        margin: 10px;
    }
}
</style>

<script>
// Character counter and progress
document.getElementById('message').addEventListener('input', function() {
    let count = this.value.length;
    let max = <?php echo SMS_MAX_CHARS; ?>;
    let percent = (count / max) * 100;
    
    document.getElementById('charCount').textContent = count;
    document.getElementById('charProgress').style.width = percent + '%';
    
    // Calculate SMS count
    let smsCount = Math.ceil(count / 160);
    document.getElementById('smsCount').textContent = smsCount + ' SMS' + (smsCount > 1 ? 'es' : '');
    
    // Warning colors
    let counter = document.getElementById('charCounter');
    let progress = document.getElementById('charProgress');
    
    if (count > <?php echo SMS_WARNING_LIMIT; ?>) {
        counter.style.background = '#fff3cd';
        counter.style.color = '#856404';
        progress.style.backgroundColor = '#ffc107';
    } else {
        counter.style.background = '#f0f3ff';
        counter.style.color = '#3B9DB3';
        progress.style.backgroundColor = '#3B9DB3';
    }
    
    if (count >= max) {
        counter.style.background = '#f8d7da';
        counter.style.color = '#721c24';
        progress.style.backgroundColor = '#dc3545';
    }
});

// Update selected count
function updateSelectedCount() {
    let count = document.querySelectorAll('.recipient-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count + ' selected';
    document.getElementById('selectedCountPreview').textContent = count + ' selected';
    
    // Update visual selection
    document.querySelectorAll('.recipient-item').forEach(item => {
        let cb = item.querySelector('.recipient-checkbox');
        if (cb && cb.checked) {
            item.classList.add('selected');
        } else {
            item.classList.remove('selected');
        }
    });
    
    // Update select all checkboxes
    let staffCheckboxes = document.querySelectorAll('.staff-checkbox');
    let staffChecked = document.querySelectorAll('.staff-checkbox:checked').length;
    document.getElementById('selectAllStaff').checked = staffCheckboxes.length > 0 && staffChecked === staffCheckboxes.length;
    
    let parentsCheckboxes = document.querySelectorAll('.parents-checkbox');
    let parentsChecked = document.querySelectorAll('.parents-checkbox:checked').length;
    document.getElementById('selectAllParents').checked = parentsCheckboxes.length > 0 && parentsChecked === parentsCheckboxes.length;
}

// Toggle staff all
function toggleStaffAll() {
    let selectAll = document.getElementById('selectAllStaff');
    let checkboxes = document.querySelectorAll('.staff-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateSelectedCount();
}

// Toggle parents all
function toggleParentsAll() {
    let selectAll = document.getElementById('selectAllParents');
    let checkboxes = document.querySelectorAll('.parents-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateSelectedCount();
}

// Search and filter
document.getElementById('searchRecipients').addEventListener('input', filterRecipients);
document.getElementById('typeFilter').addEventListener('change', filterRecipients);

function filterRecipients() {
    let search = document.getElementById('searchRecipients').value.toLowerCase().trim();
    let type = document.getElementById('typeFilter').value;
    
    document.querySelectorAll('.recipient-item').forEach(item => {
        let itemType = item.getAttribute('data-type');
        let name = item.getAttribute('data-name') || '';
        let phone = item.getAttribute('data-phone') || '';
        let itemClass = item.getAttribute('data-class') || '';
        let combination = item.getAttribute('data-combination') || '';
        
        let matchesSearch = search === '' || 
                           name.includes(search) || 
                           phone.includes(search) || 
                           itemClass.includes(search) || 
                           combination.includes(search);
        
        let matchesType = type === 'all' || itemType === type;
        
        item.style.display = (matchesSearch && matchesType) ? 'flex' : 'none';
    });
}

// Click on item to toggle checkbox
document.querySelectorAll('.recipient-item').forEach(item => {
    item.addEventListener('click', function(e) {
        if (e.target.type !== 'checkbox') {
            let cb = this.querySelector('.recipient-checkbox');
            if (cb) {
                cb.checked = !cb.checked;
                updateSelectedCount();
            }
        }
    });
});

// Insert quick template
function insertTemplate(type) {
    let messageField = document.getElementById('message');
    let templates = {
        'exam': 'Dear Parent, exam results for this term are now available. Please check the school portal or visit the academic office for more details.',
        'fee': 'Dear Parent, this is a friendly reminder that school fees for this term are due. Please make payments to avoid any disruptions.',
        'meeting': 'Dear Parent, there will be a parents-teachers meeting on Saturday at 9:00 AM in the school hall. Your attendance is important.',
        'holiday': 'Dear Parent, school will close for holidays on Friday at 1:00 PM. Students should be picked up on time. School reopens on January 15th.',
        'emergency': 'URGENT: Due to emergency situation, school will close early today at 12:00 PM. Please arrange to pick up your child immediately.'
    };
    
    if (templates[type]) {
        messageField.value = templates[type];
        messageField.dispatchEvent(new Event('input'));
    }
}

// Use template from modal
function useTemplate(type) {
    insertTemplate(type);
    bootstrap.Modal.getInstance(document.getElementById('templatesModal')).hide();
}

// Show full message
function showFullMessage(id, message) {
    document.getElementById('fullMessageText').textContent = message;
    new bootstrap.Modal(document.getElementById('fullMessageModal')).show();
}

// Export logs
function exportLogs() {
    window.location.href = 'export_sms_logs.php';
}

function exportAllLogs() {
    window.location.href = 'export_sms_logs.php?all=1';
}

// Show SweetAlert2 messages
document.addEventListener('DOMContentLoaded', function() {
    const smsMessage = document.getElementById('smsMessage');
    const smsDetails = document.getElementById('smsDetails');
    
    if (smsMessage) {
        let message = smsMessage.getAttribute('data-message');
        let type = smsMessage.getAttribute('data-type');
        
        Swal.fire({
            title: type === 'success' ? 'Success!' : 'Error!',
            text: message,
            icon: type,
            confirmButtonText: 'OK',
            confirmButtonColor: '#3B9DB3',
            timer: type === 'success' ? 5000 : undefined,
            timerProgressBar: type === 'success'
        });
    }
    
    if (smsDetails) {
        let details = JSON.parse(smsDetails.getAttribute('data-details'));
        if (details && details.length > 0) {
            let html = '<ul style="text-align: left;">';
            details.forEach(d => html += '<li>' + d + '</li>');
            html += '</ul>';
            
            Swal.fire({
                title: 'Sending Details',
                html: html,
                icon: 'info',
                confirmButtonText: 'OK',
                confirmButtonColor: '#3B9DB3'
            });
        }
    }
});

// Form validation
document.getElementById('smsForm').addEventListener('submit', function(e) {
    let message = document.getElementById('message').value.trim();
    let selected = document.querySelectorAll('.recipient-checkbox:checked').length;
    
    if (!message) {
        e.preventDefault();
        Swal.fire({
            title: 'Error!',
            text: 'Please enter a message',
            icon: 'error',
            confirmButtonColor: '#3B9DB3'
        });
    } else if (selected === 0) {
        e.preventDefault();
        Swal.fire({
            title: 'Error!',
            text: 'Please select at least one recipient',
            icon: 'error',
            confirmButtonColor: '#3B9DB3'
        });
    }
});

// Initialize
updateSelectedCount();
</script>

<?php include '../controller/footer.php'; ?>