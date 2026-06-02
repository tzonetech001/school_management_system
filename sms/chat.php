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
    $_SESSION['error'] = "You don't have permission to view staff members.";
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

// Beem Africa API Credentials
define('BEEM_API_KEY', '5e3de');
define('BEEM_SECRET_KEY', 'MDRhM2MxNGUxZGNmYmRjND');
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
    
    // Default: add 255
    return '255' . $phone;
}

// Function to validate phone number
function validatePhoneNumber($phone) {
    $phone = formatPhoneNumber($phone);
    return preg_match('/^255[67][0-9]{8}$/', $phone);
}

// Function to send SMS via Beem Africa
function sendSMS($phone_number, $message) {
    $api_key = BEEM_API_KEY;
    $secret_key = BEEM_SECRET_KEY;
    
    $phone_number = formatPhoneNumber($phone_number);
    
    if (!preg_match('/^255[67][0-9]{8}$/', $phone_number)) {
        return [
            'success' => false,
            'message' => "Invalid phone number format: $phone_number"
        ];
    }
    
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
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($response === FALSE) {
        return [
            'success' => false,
            'message' => "CURL Error: " . $curl_error
        ];
    }
    
    $response_data = json_decode($response, true);
    
    if ($http_code == 200 && isset($response_data['successful']) && $response_data['successful'] === true) {
        return [
            'success' => true,
            'message' => "SMS sent successfully"
        ];
    } else {
        $error_msg = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
        return [
            'success' => false,
            'message' => "Failed: $error_msg"
        ];
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

// Create SMS logs table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    recipient_type VARCHAR(50) NOT NULL,
    recipient_count INT NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    notes TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin (admin_id),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Function to log SMS
function logSMS($conn, $admin_id, $recipient_type, $recipient_count, $message, $status, $notes = '') {
    $sql = "INSERT INTO sms_logs (admin_id, recipient_type, recipient_count, message, status, notes) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isiss", $admin_id, $recipient_type, $recipient_count, $message, $status, $notes);
    return $stmt->execute();
}

// Get all staff with phone numbers
$staff_sql = "SELECT id, first_name, last_name, email, phone_number, sex 
              FROM admins 
              WHERE phone_number IS NOT NULL 
              AND phone_number != '' 
              AND status = 1
              ORDER BY first_name, last_name";
$staff_result = mysqli_query($conn, $staff_sql);
$staff_list = [];
if ($staff_result && mysqli_num_rows($staff_result) > 0) {
    while ($row = mysqli_fetch_assoc($staff_result)) {
        $staff_list[] = $row;
    }
}

// Get all students with parent phone numbers
$parent_sql = "SELECT id, first_name, last_name, admission_number, parent_phone, class, combination, sex 
               FROM students 
               WHERE parent_phone IS NOT NULL 
               AND parent_phone != '' 
               AND is_leaver = FALSE
               AND status = 1
               ORDER BY class, combination, first_name, last_name";
$parent_result = mysqli_query($conn, $parent_sql);
$parent_list = [];
if ($parent_result && mysqli_num_rows($parent_result) > 0) {
    while ($row = mysqli_fetch_assoc($parent_result)) {
        $parent_list[] = $row;
    }
}

// Get recent SMS logs
$logs_sql = "SELECT sl.*, a.first_name as admin_first, a.last_name as admin_last 
             FROM sms_logs sl
             LEFT JOIN admins a ON sl.admin_id = a.id
             ORDER BY sl.sent_at DESC
             LIMIT 30";
$logs_result = mysqli_query($conn, $logs_sql);
$sms_logs = [];
if ($logs_result && mysqli_num_rows($logs_result) > 0) {
    while ($row = mysqli_fetch_assoc($logs_result)) {
        $sms_logs[] = $row;
    }
}

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_messages,
    SUM(CASE WHEN status = 'Success' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) as failed
    FROM sms_logs";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$staff_count = count($staff_list);
$parent_count = count($parent_list);
$total_contacts = $staff_count + $parent_count;

// Get class and combination stats for parents
$class_stats = [];
$combination_stats = [];

foreach ($parent_list as $student) {
    $class = $student['class'];
    $combination = $student['combination'];
    
    if (!isset($class_stats[$class])) {
        $class_stats[$class] = 0;
    }
    $class_stats[$class]++;
    
    if (!isset($combination_stats[$combination])) {
        $combination_stats[$combination] = 0;
    }
    $combination_stats[$combination]++;
}

// Handle SMS sending
$sms_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $sms_message = trim($_POST['message'] ?? '');
    $selected = $_POST['selected_recipients'] ?? [];
    
    if (empty($sms_message)) {
        $sms_result = ['success' => false, 'message' => 'Please enter a message'];
    } elseif (strlen($sms_message) > SMS_MAX_CHARS) {
        $sms_result = ['success' => false, 'message' => 'Message too long. Maximum ' . SMS_MAX_CHARS . ' characters.'];
    } elseif (empty($selected)) {
        $sms_result = ['success' => false, 'message' => 'Please select at least one recipient'];
    } else {
        $success_count = 0;
        $fail_count = 0;
        $details = [];
        
        foreach ($selected as $item) {
            $parts = explode('_', $item);
            $type = $parts[0];
            $id = $parts[1];
            
            $phone = '';
            $name = '';
            
            if ($type === 'staff') {
                foreach ($staff_list as $staff) {
                    if ($staff['id'] == $id) {
                        $phone = $staff['phone_number'];
                        $name = $staff['first_name'] . ' ' . $staff['last_name'];
                        break;
                    }
                }
            } elseif ($type === 'parent') {
                foreach ($parent_list as $student) {
                    if ($student['id'] == $id) {
                        $phone = $student['parent_phone'];
                        $name = $student['first_name'] . ' ' . $student['last_name'] . ' (Parent)';
                        break;
                    }
                }
            }
            
            if (!empty($phone)) {
                $result = sendSMS($phone, $sms_message);
                
                if ($result['success']) {
                    $success_count++;
                } else {
                    $fail_count++;
                    $details[] = "$name: " . $result['message'];
                }
            }
        }
        
        // Log the batch
        $status = ($fail_count == 0) ? 'Success' : (($success_count > 0) ? 'Partial' : 'Failed');
        $notes = ($fail_count > 0) ? "Failed: " . implode("; ", $details) : "All sent successfully";
        
        logSMS($conn, $admin_id, 'batch', count($selected), $sms_message, $status, $notes);
        
        if ($success_count > 0) {
            $sms_result = [
                'success' => true,
                'message' => "✅ SMS sent to $success_count recipients" . 
                            ($fail_count > 0 ? ", $fail_count failed" : "")
            ];
            
            if (!empty($details)) {
                $_SESSION['sms_details'] = $details;
            }
        } else {
            $sms_result = [
                'success' => false,
                'message' => "❌ Failed to send any SMS. Check logs for details."
            ];
        }
        
        // Refresh logs
        $logs_result = mysqli_query($conn, $logs_sql);
        $sms_logs = [];
        if ($logs_result && mysqli_num_rows($logs_result) > 0) {
            while ($row = mysqli_fetch_assoc($logs_result)) {
                $sms_logs[] = $row;
            }
        }
    }
}

$balance = checkBalance();
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<!-- SweetAlert2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<style>
:root {
    --primary-color: #3B9DB3;
    --primary-dark: #2d7c8f;
    --primary-light: #8bc5d6;
    --secondary-color: #1c6c80;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --light-bg: #f8f9fa;
    --border-color: #e0e0e0;
    --text-color: #333333;
    --text-light: #666666;
    --white: #ffffff;
}

.main-content {
    padding: 20px;
    min-height: 100vh;
    padding-bottom: 100px;
}

.page-title {
    color: var(--text-color);
    font-weight: 600;
    margin-bottom: 0;
}

/* Stats Cards */
.stats-card {
    border: none;
    border-radius: 15px;
    padding: 20px;
    background: var(--white);
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--primary-color);
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(59, 157, 179, 0.15);
}

.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    background: rgba(59, 157, 179, 0.1);
    color: var(--primary-color);
    font-size: 24px;
}

.stats-card h3 {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-color);
    margin-bottom: 5px;
}

.stats-card p {
    color: var(--text-light);
    margin-bottom: 0;
    font-size: 14px;
    font-weight: 500;
}

/* Main Card */
.main-card {
    border: none;
    border-radius: 15px;
    background: var(--white);
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 20px;
}

.card-header-custom {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
    padding: 20px;
}

.card-header-custom h4 {
    margin: 0;
    font-weight: 600;
}

.card-body-custom {
    padding: 25px;
}

/* Tabs */
.recipient-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 10px;
    flex-wrap: wrap;
}

.recipient-tab {
    padding: 8px 20px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    color: var(--text-light);
    background: var(--light-bg);
    border: none;
}

.recipient-tab:hover {
    background: var(--border-color);
    color: var(--text-color);
}

.recipient-tab.active {
    background: var(--primary-color);
    color: var(--white);
}

/* Action Bar */
.action-bar {
    background: var(--light-bg);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    border: 1px solid var(--border-color);
}

.selection-info {
    background: var(--primary-color);
    color: var(--white);
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
}

/* Search Box */
.search-box {
    position: relative;
    margin-bottom: 15px;
}

.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.search-box input {
    padding-left: 35px;
    border-radius: 20px;
    border: 2px solid var(--border-color);
    height: 40px;
    font-size: 14px;
    width: 100%;
}

.search-box input:focus {
    border-color: var(--primary-color);
    box-shadow: none;
    outline: none;
}

/* Filter Buttons */
.filter-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.filter-btn {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 12px;
    background: var(--light-bg);
    color: var(--text-light);
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-btn:hover {
    background: var(--border-color);
    color: var(--text-color);
}

.filter-btn.active {
    background: var(--primary-color);
    color: var(--white);
    border-color: var(--primary-color);
}

/* Contact Cards */
.contact-section {
    margin-top: 20px;
}

.contact-section-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-color);
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
}

.contact-section-title .badge {
    margin-left: 10px;
    background: var(--primary-color);
    color: var(--white);
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
}

.select-all-bar {
    background: rgba(59, 157, 179, 0.05);
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    border: 1px solid var(--border-color);
}

.select-all-bar label {
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    margin: 0;
}

.select-all-bar input[type="checkbox"] {
    accent-color: var(--primary-color);
}

.contact-card {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 10px;
    transition: all 0.2s ease;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
}

.contact-card:hover {
    background: var(--border-color);
    border-color: var(--primary-color);
    transform: translateX(5px);
}

.contact-card.selected {
    background: rgba(59, 157, 179, 0.1);
    border-color: var(--primary-color);
    border-left: 4px solid var(--primary-color);
}

.contact-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--primary-color);
}

.contact-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-color);
    color: var(--white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    flex-shrink: 0;
}

.contact-info {
    flex: 1;
}

.contact-name {
    font-weight: 600;
    color: var(--text-color);
    margin-bottom: 3px;
}

.contact-details {
    font-size: 12px;
    color: var(--text-light);
}

.contact-details i {
    width: 16px;
    color: var(--primary-color);
}

.contact-phone {
    font-size: 13px;
    color: var(--primary-color);
    font-weight: 500;
}

/* Logs Card */
.logs-card {
    border: none;
    border-radius: 15px;
    background: var(--white);
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    overflow: hidden;
}

.logs-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
    padding: 15px 20px;
}

.logs-header h5 {
    margin: 0;
    font-weight: 600;
}

.logs-table {
    width: 100%;
}

.logs-table th {
    background: rgba(59, 157, 179, 0.05);
    font-weight: 600;
    color: var(--text-color);
    font-size: 13px;
    padding: 12px;
    border-bottom: 2px solid rgba(59, 157, 179, 0.2);
}

.logs-table td {
    padding: 12px;
    font-size: 13px;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
}

.status-badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.status-success {
    background: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
}

.status-failed {
    background: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
}

.status-partial {
    background: rgba(255, 193, 7, 0.1);
    color: #856404;
}

/* FAB */
.fab-container {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
}

.fab {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    box-shadow: 0 4px 15px rgba(59, 157, 179, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    color: var(--white);
    font-size: 24px;
    position: relative;
}

.fab:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(59, 157, 179, 0.6);
}

.fab.hidden {
    transform: scale(0);
    opacity: 0;
    pointer-events: none;
}

.fab-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--danger-color);
    color: var(--white);
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Message Modal */
.message-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    backdrop-filter: blur(5px);
}

.message-modal.show {
    display: flex;
    opacity: 1;
}

.modal-content-modern {
    background: var(--white);
    border-radius: 25px;
    width: 90%;
    max-width: 550px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
}

.modal-header-modern {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
    padding: 20px 25px;
    border-radius: 25px 25px 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header-modern h3 {
    margin: 0;
    font-weight: 600;
    font-size: 1.4rem;
}

.modal-header-modern h3 i {
    margin-right: 10px;
}

.modal-close {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 18px;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-body-modern {
    padding: 25px;
}

.recipient-summary {
    background: rgba(59, 157, 179, 0.05);
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 20px;
    border-left: 4px solid var(--primary-color);
}

.recipient-summary-title {
    font-size: 13px;
    color: var(--text-light);
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.recipient-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
    max-height: 100px;
    overflow-y: auto;
}

.recipient-chip {
    background: var(--white);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 5px 12px;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.recipient-chip i {
    color: var(--primary-color);
}

.recipient-count-badge {
    background: var(--primary-color);
    color: var(--white);
    border-radius: 15px;
    padding: 5px 12px;
    font-size: 12px;
    display: inline-block;
}

.message-input-modern {
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 15px;
    width: 100%;
    resize: none;
    font-size: 14px;
    transition: all 0.3s ease;
    margin-bottom: 10px;
}

.message-input-modern:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(59, 157, 179, 0.25);
    outline: none;
}

.character-count-modern {
    text-align: right;
    font-size: 13px;
    color: var(--text-light);
    margin-bottom: 20px;
}

.character-count-modern.warning {
    color: var(--warning-color);
    font-weight: 600;
}

.character-count-modern.danger {
    color: var(--danger-color);
    font-weight: 600;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn-modern {
    padding: 12px 25px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-modern-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
    box-shadow: 0 4px 10px rgba(59, 157, 179, 0.3);
}

.btn-modern-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(59, 157, 179, 0.4);
}

.btn-modern-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-modern-secondary {
    background: var(--light-bg);
    color: var(--text-light);
    border: 1px solid var(--border-color);
}

.btn-modern-secondary:hover {
    background: var(--border-color);
    color: var(--text-color);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .main-content {
        padding: 10px;
        padding-bottom: 90px;
    }
    
    .fab-container {
        bottom: 20px;
        right: 20px;
    }
    
    .fab {
        width: 55px;
        height: 55px;
        font-size: 20px;
    }
    
    .modal-content-modern {
        width: 95%;
    }
    
    .modal-actions {
        flex-direction: column;
    }
    
    .btn-modern {
        width: 100%;
        justify-content: center;
    }
    
    .stats-card {
        padding: 15px;
    }
    
    .stats-icon {
        width: 40px;
        height: 40px;
        font-size: 20px;
    }
    
    .stats-card h3 {
        font-size: 22px;
    }
    
    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                <i class="fas fa-comment-dots me-2" style="color: var(--primary-color);"></i>
                SMS Messaging Center
            </h2>
            <div>
                <span class="badge" style="background: var(--primary-color); color: var(--white); padding: 8px 15px;">
                    <i class="fas fa-wallet me-1"></i> Balance: <?php echo $balance; ?> TZS
                </span>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3><?php echo $staff_count; ?></h3>
                    <p>Staff Members</p>
                    <small class="text-muted">With phone numbers</small>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <h3><?php echo $parent_count; ?></h3>
                    <p>Parents</p>
                    <small class="text-muted">With valid phone numbers</small>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3><?php echo number_format($stats['total_messages'] ?? 0); ?></h3>
                    <p>Total Messages</p>
                    <small class="text-muted">
                        <span class="text-success"><?php echo number_format($stats['successful'] ?? 0); ?> Success</span> / 
                        <span class="text-danger"><?php echo number_format($stats['failed'] ?? 0); ?> Failed</span>
                    </small>
                </div>
            </div>
            
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
        </div>

        <!-- Main Content Row -->
        <div class="row">
            <!-- Left Column - Recipients -->
            <div class="col-lg-8">
                <div class="main-card">
                    <div class="card-header-custom">
                        <h4><i class="fas fa-address-book me-2"></i>Select Recipients</h4>
                        <p class="mb-0 small opacity-75">Choose staff and parents to send SMS</p>
                    </div>
                    
                    <div class="card-body-custom">
                        <!-- Recipient Tabs -->
                        <div class="recipient-tabs">
                            <button class="recipient-tab active" data-target="all">
                                <i class="fas fa-users me-1"></i> All Contacts (<?php echo $total_contacts; ?>)
                            </button>
                            <button class="recipient-tab" data-target="staff">
                                <i class="fas fa-chalkboard-teacher me-1"></i> Staff (<?php echo $staff_count; ?>)
                            </button>
                            <button class="recipient-tab" data-target="parents">
                                <i class="fas fa-user-friends me-1"></i> Parents (<?php echo $parent_count; ?>)
                            </button>
                        </div>

                        <!-- Action Bar -->
                        <div class="action-bar">
                            <div class="d-flex align-items-center gap-3">
                                <button class="btn btn-sm" style="background: var(--primary-color); color: var(--white); border: none;" onclick="selectAll()">
                                    <i class="fas fa-check-double me-1"></i> Select All
                                </button>
                                <button class="btn btn-sm" style="background: var(--light-bg); color: var(--text-color); border: 1px solid var(--border-color);" onclick="deselectAll()">
                                    <i class="fas fa-times me-1"></i> Deselect All
                                </button>
                            </div>
                            <div class="selection-info" id="selectionInfo">
                                <i class="fas fa-users"></i> <span id="selectedCount">0</span> selected
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" id="searchContacts" 
                                   placeholder="Search by name, phone, class, combination...">
                        </div>

                        <!-- Filter Buttons -->
                        <div class="filter-buttons" id="filterButtons">
                            <span class="filter-btn active" data-filter="all">All</span>
                            <?php foreach ($class_stats as $class => $count): ?>
                                <span class="filter-btn" data-filter="<?php echo $class; ?>">
                                    <?php echo $class; ?> (<?php echo $count; ?>)
                                </span>
                            <?php endforeach; ?>
                            <?php foreach ($combination_stats as $comb => $count): ?>
                                <span class="filter-btn" data-filter="<?php echo $comb; ?>">
                                    <?php echo $comb; ?> (<?php echo $count; ?>)
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <!-- Hidden form for recipients -->
                        <form id="smsForm" method="POST">
                            <!-- Staff Section -->
                            <div class="contact-section" id="staffSection">
                                <div class="contact-section-title">
                                    <i class="fas fa-chalkboard-teacher me-2" style="color: var(--primary-color);"></i>
                                    Staff Members
                                    <span class="badge"><?php echo $staff_count; ?> contacts</span>
                                </div>
                                
                                <div class="select-all-bar">
                                    <label>
                                        <input type="checkbox" class="select-all-staff" onchange="toggleAllStaff(this)">
                                        <strong>Select All Staff</strong>
                                    </label>
                                </div>
                                
                                <div class="staff-contacts-list">
                                    <?php if (empty($staff_list)): ?>
                                        <div class="alert alert-info">No staff with phone numbers found.</div>
                                    <?php else: ?>
                                        <?php foreach ($staff_list as $staff): ?>
                                            <div class="contact-card" data-type="staff" 
                                                 data-id="<?php echo $staff['id']; ?>"
                                                 data-name="<?php echo strtolower($staff['first_name'] . ' ' . $staff['last_name']); ?>"
                                                 data-phone="<?php echo $staff['phone_number']; ?>">
                                                <input type="checkbox" class="contact-checkbox" 
                                                       name="selected_recipients[]" 
                                                       value="staff_<?php echo $staff['id']; ?>"
                                                       onchange="updateSelection()">
                                                <div class="contact-avatar">
                                                    <?php echo strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)); ?>
                                                </div>
                                                <div class="contact-info">
                                                    <div class="contact-name">
                                                        <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                                    </div>
                                                    <div class="contact-details">
                                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staff['email']); ?>
                                                    </div>
                                                    <div class="contact-phone">
                                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($staff['phone_number']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Parents Section -->
                            <div class="contact-section" id="parentsSection">
                                <div class="contact-section-title">
                                    <i class="fas fa-user-friends me-2" style="color: var(--primary-color);"></i>
                                    Parents/Guardians
                                    <span class="badge"><?php echo $parent_count; ?> contacts</span>
                                </div>
                                
                                <div class="select-all-bar">
                                    <label>
                                        <input type="checkbox" class="select-all-parents" onchange="toggleAllParents(this)">
                                        <strong>Select All Parents</strong>
                                    </label>
                                </div>
                                
                                <div class="parents-contacts-list">
                                    <?php if (empty($parent_list)): ?>
                                        <div class="alert alert-info">No parents with phone numbers found.</div>
                                    <?php else: ?>
                                        <?php foreach ($parent_list as $student): ?>
                                            <div class="contact-card" data-type="parent" 
                                                 data-id="<?php echo $student['id']; ?>"
                                                 data-name="<?php echo strtolower($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                 data-phone="<?php echo $student['parent_phone']; ?>"
                                                 data-class="<?php echo $student['class']; ?>"
                                                 data-combination="<?php echo $student['combination']; ?>">
                                                <input type="checkbox" class="contact-checkbox" 
                                                       name="selected_recipients[]" 
                                                       value="parent_<?php echo $student['id']; ?>"
                                                       onchange="updateSelection()">
                                                <div class="contact-avatar">
                                                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                </div>
                                                <div class="contact-info">
                                                    <div class="contact-name">
                                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                    </div>
                                                    <div class="contact-details">
                                                        <i class="fas fa-graduation-cap"></i> 
                                                        <?php echo $student['class']; ?> - <?php echo $student['combination']; ?>
                                                    </div>
                                                    <div class="contact-phone">
                                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($student['parent_phone']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column - SMS Logs -->
            <div class="col-lg-4">
                <div class="logs-card">
                    <div class="logs-header">
                        <h5><i class="fas fa-history me-2"></i>Recent SMS Logs</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="logs-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sms_logs)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            No SMS history yet
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sms_logs as $log): ?>
                                        <tr>
                                            <td>
                                                <small><?php echo date('H:i', strtotime($log['sent_at'])); ?></small>
                                                <br>
                                                <small class="text-muted"><?php echo date('d/m', strtotime($log['sent_at'])); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo ucfirst($log['recipient_type']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo $log['recipient_count']; ?> rec</small>
                                            </td>
                                            <td>
                                                <small title="<?php echo htmlspecialchars($log['message']); ?>">
                                                    <?php echo substr($log['message'], 0, 20); ?>...
                                                </small>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($log['admin_first'] . ' ' . $log['admin_last']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($log['status']); ?>">
                                                    <?php echo $log['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FLOATING ACTION BUTTON -->
<div class="fab-container">
    <div class="fab <?php echo ($total_contacts == 0) ? 'hidden' : ''; ?>" id="fab" onclick="openMessageModal()">
        <i class="fas fa-pen"></i>
        <span class="fab-badge" id="fabBadge">0</span>
    </div>
</div>

<!-- MESSAGE COMPOSER MODAL -->
<div class="message-modal" id="messageModal">
    <div class="modal-content-modern">
        <div class="modal-header-modern">
            <h3>
                <i class="fas fa-comment-dots"></i>
                Compose Message
            </h3>
            <div class="modal-close" onclick="closeMessageModal()">
                <i class="fas fa-times"></i>
            </div>
        </div>
        
        <div class="modal-body-modern">
            <!-- Recipient Summary -->
            <div class="recipient-summary">
                <div class="recipient-summary-title">
                    <i class="fas fa-users me-1"></i> Selected Recipients
                </div>
                <div class="recipient-chips" id="recipientChips">
                    <!-- Will be populated by JavaScript -->
                </div>
                <div class="recipient-count-badge" id="modalSelectedCount">
                    0 recipients selected
                </div>
            </div>
            
            <!-- Message Input -->
            <textarea id="modalMessage" class="message-input-modern" 
                      rows="5" maxlength="<?php echo SMS_MAX_CHARS; ?>" 
                      placeholder="Type your message here... (max <?php echo SMS_MAX_CHARS; ?> characters)"
                      oninput="updateModalCharCount()"></textarea>
            
            <div class="character-count-modern" id="modalCharCount">
                <span id="modalCurrentChars">0</span>/<?php echo SMS_MAX_CHARS; ?> characters
            </div>
            
            <!-- Action Buttons -->
            <div class="modal-actions">
                <button class="btn-modern btn-modern-secondary" onclick="closeMessageModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-modern btn-modern-primary" onclick="sendMessage()" id="sendMessageBtn">
                    <i class="fas fa-paper-plane"></i> Send SMS
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ========== GLOBAL VARIABLES ==========
let selectedCount = 0;
let selectedRecipients = [];

// ========== SELECTION FUNCTIONS ==========
function updateSelection() {
    const checkboxes = document.querySelectorAll('input[name="selected_recipients[]"]:checked');
    selectedCount = checkboxes.length;
    
    document.getElementById('selectedCount').textContent = selectedCount;
    
    const fab = document.getElementById('fab');
    const fabBadge = document.getElementById('fabBadge');
    
    if (selectedCount > 0) {
        fab.classList.remove('hidden');
        fabBadge.textContent = selectedCount;
        fabBadge.style.display = 'flex';
    } else {
        fab.classList.add('hidden');
        fabBadge.style.display = 'none';
    }
    
    // Update contact card selected state
    checkboxes.forEach(cb => {
        cb.closest('.contact-card').classList.add('selected');
    });
    
    document.querySelectorAll('input[name="selected_recipients[]"]:not(:checked)').forEach(cb => {
        cb.closest('.contact-card').classList.remove('selected');
    });
    
    updateSelectAllCheckboxes();
}

function updateSelectAllCheckboxes() {
    const staffCheckboxes = document.querySelectorAll('#staffSection .contact-checkbox');
    const parentCheckboxes = document.querySelectorAll('#parentsSection .contact-checkbox');
    
    const selectAllStaff = document.querySelector('.select-all-staff');
    const selectAllParents = document.querySelector('.select-all-parents');
    
    if (selectAllStaff) {
        const allStaffChecked = Array.from(staffCheckboxes).every(cb => cb.checked);
        selectAllStaff.checked = allStaffChecked;
    }
    
    if (selectAllParents) {
        const allParentsChecked = Array.from(parentCheckboxes).every(cb => cb.checked);
        selectAllParents.checked = allParentsChecked;
    }
}

function toggleAllStaff(checkbox) {
    const staffCheckboxes = document.querySelectorAll('#staffSection .contact-checkbox');
    staffCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelection();
}

function toggleAllParents(checkbox) {
    const parentCheckboxes = document.querySelectorAll('#parentsSection .contact-checkbox');
    parentCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelection();
}

function selectAll() {
    document.querySelectorAll('.contact-checkbox').forEach(cb => cb.checked = true);
    document.querySelectorAll('.select-all-staff, .select-all-parents').forEach(cb => cb.checked = true);
    updateSelection();
}

function deselectAll() {
    document.querySelectorAll('.contact-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll('.select-all-staff, .select-all-parents').forEach(cb => cb.checked = false);
    updateSelection();
}

// ========== SEARCH FUNCTION ==========
document.getElementById('searchContacts').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase().trim();
    
    document.querySelectorAll('.contact-card').forEach(card => {
        const name = card.getAttribute('data-name') || '';
        const phone = card.getAttribute('data-phone') || '';
        const text = name + ' ' + phone;
        
        if (searchTerm === '' || text.includes(searchTerm)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
});

// ========== FILTER FUNCTION ==========
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.getAttribute('data-filter');
        
        document.querySelectorAll('.contact-card').forEach(card => {
            if (filter === 'all') {
                card.style.display = 'flex';
            } else if (card.getAttribute('data-class') === filter || 
                       card.getAttribute('data-combination') === filter) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

// ========== TAB FUNCTION ==========
document.querySelectorAll('.recipient-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.recipient-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const target = this.getAttribute('data-target');
        
        const staffSection = document.getElementById('staffSection');
        const parentsSection = document.getElementById('parentsSection');
        
        if (target === 'all') {
            staffSection.style.display = 'block';
            parentsSection.style.display = 'block';
        } else if (target === 'staff') {
            staffSection.style.display = 'block';
            parentsSection.style.display = 'none';
        } else if (target === 'parents') {
            staffSection.style.display = 'none';
            parentsSection.style.display = 'block';
        }
    });
});

// ========== CLICK CARD TO TOGGLE ==========
document.querySelectorAll('.contact-card').forEach(card => {
    card.addEventListener('click', function(e) {
        if (e.target.type !== 'checkbox') {
            const checkbox = this.querySelector('.contact-checkbox');
            checkbox.checked = !checkbox.checked;
            updateSelection();
        }
    });
});

// ========== MODAL FUNCTIONS ==========
function openMessageModal() {
    if (selectedCount === 0) {
        Swal.fire({
            title: 'No Recipients',
            text: 'Please select at least one recipient first',
            icon: 'warning',
            confirmButtonColor: '#3B9DB3'
        });
        return;
    }
    
    updateSelectedRecipientsList();
    
    const modal = document.getElementById('messageModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
        document.getElementById('modalMessage').focus();
    }, 300);
    
    document.getElementById('modalMessage').value = '';
    updateModalCharCount();
}

function closeMessageModal() {
    const modal = document.getElementById('messageModal');
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

function updateSelectedRecipientsList() {
    const checkboxes = document.querySelectorAll('input[name="selected_recipients[]"]:checked');
    const chipsContainer = document.getElementById('recipientChips');
    const modalCount = document.getElementById('modalSelectedCount');
    
    chipsContainer.innerHTML = '';
    selectedRecipients = [];
    
    checkboxes.forEach(cb => {
        const card = cb.closest('.contact-card');
        const type = card.getAttribute('data-type');
        const name = card.querySelector('.contact-name').textContent.trim();
        const phone = card.querySelector('.contact-phone').textContent.trim().replace('📞', '').trim();
        
        selectedRecipients.push({
            type: type,
            name: name,
            phone: phone,
            value: cb.value
        });
        
        const chip = document.createElement('span');
        chip.className = `recipient-chip ${type}`;
        chip.innerHTML = `
            <i class="fas fa-${type === 'staff' ? 'chalkboard-teacher' : 'user-friends'}"></i>
            ${name}
            <small>${phone}</small>
        `;
        chipsContainer.appendChild(chip);
    });
    
    modalCount.textContent = `${selectedRecipients.length} recipient(s) selected`;
}

function updateModalCharCount() {
    const message = document.getElementById('modalMessage');
    const currentChars = message.value.length;
    const maxChars = <?php echo SMS_MAX_CHARS; ?>;
    const charCount = document.getElementById('modalCurrentChars');
    const charDiv = document.getElementById('modalCharCount');
    
    charCount.textContent = currentChars;
    
    charDiv.classList.remove('warning', 'danger');
    if (currentChars > <?php echo SMS_WARNING_LIMIT; ?>) {
        charDiv.classList.add('warning');
    }
    if (currentChars >= maxChars) {
        charDiv.classList.add('danger');
    }
}

// ========== SEND MESSAGE ==========
function sendMessage() {
    const message = document.getElementById('modalMessage').value.trim();
    const sendBtn = document.getElementById('sendMessageBtn');
    
    if (message === '') {
        Swal.fire({
            title: 'Empty Message',
            text: 'Please enter a message',
            icon: 'warning',
            confirmButtonColor: '#3B9DB3'
        });
        document.getElementById('modalMessage').focus();
        return;
    }
    
    if (message.length > <?php echo SMS_MAX_CHARS; ?>) {
        Swal.fire({
            title: 'Message Too Long',
            text: 'Maximum <?php echo SMS_MAX_CHARS; ?> characters allowed',
            icon: 'error',
            confirmButtonColor: '#3B9DB3'
        });
        return;
    }
    
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    const formData = new FormData();
    formData.append('send_sms', '1');
    formData.append('message', message);
    
    selectedRecipients.forEach(recipient => {
        formData.append('selected_recipients[]', recipient.value);
    });
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        closeMessageModal();
        
        Swal.fire({
            title: 'Success!',
            text: `SMS sent to ${selectedRecipients.length} recipient(s)`,
            icon: 'success',
            confirmButtonColor: '#3B9DB3',
            timer: 3000
        });
        
        setTimeout(() => {
            location.reload();
        }, 2000);
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'Failed to send SMS. Please try again.',
            icon: 'error',
            confirmButtonColor: '#3B9DB3'
        });
        
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send SMS';
    });
}

// ========== MODAL CLOSE ON OUTSIDE CLICK ==========
document.getElementById('messageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeMessageModal();
    }
});

// ========== KEYBOARD SHORTCUTS ==========
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMessageModal();
    }
});

document.getElementById('modalMessage').addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        sendMessage();
    }
});

// ========== SWEETALERT FOR SMS RESULT ==========
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($sms_result): ?>
        Swal.fire({
            title: '<?php echo $sms_result['success'] ? 'Success!' : 'Failed!'; ?>',
            text: '<?php echo addslashes($sms_result['message']); ?>',
            icon: '<?php echo $sms_result['success'] ? 'success' : 'error'; ?>',
            confirmButtonColor: '#3B9DB3'
        });
    <?php endif; ?>
    
    <?php if (isset($_SESSION['sms_details'])): ?>
        let details = <?php echo json_encode($_SESSION['sms_details']); ?>;
        if (details && details.length > 0) {
            let html = '<ul style="text-align: left;">';
            details.forEach(d => html += '<li>' + d + '</li>');
            html += '</ul>';
            
            Swal.fire({
                title: 'Sending Details',
                html: html,
                icon: 'info',
                confirmButtonColor: '#3B9DB3'
            });
        }
        <?php unset($_SESSION['sms_details']); ?>
    <?php endif; ?>
    
    updateSelection();
});

// ========== AUTO REFRESH LOGS (OPTIONAL) ==========
// Refresh every 5 minutes
setInterval(function() {
    if (!document.getElementById('messageModal').classList.contains('show')) {
        location.reload();
    }
}, 300000); // 5 minutes
</script>

<?php include '../controller/footer.php'; ?>
