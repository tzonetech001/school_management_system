<?php
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['student_id'])) {
    header("Location: ../index.php");
    exit();
}

$is_admin = isset($_SESSION['admin_id']);
$user_id = $is_admin ? $_SESSION['admin_id'] : $_SESSION['student_id'];
$user_type = $is_admin ? 'Teacher' : 'student';

// Load user's theme settings (for admins)
$colors = [];
$preferences = [];

if ($is_admin) {
    $admin_id = $user_id;
    
    // Get theme colors
    $color_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
    $color_result = mysqli_query($conn, $color_query);
    if ($color_result) {
        while ($row = mysqli_fetch_assoc($color_result)) {
            $colors[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    // Get preferences
    $pref_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id";
    $pref_result = mysqli_query($conn, $pref_query);
    if ($pref_result) {
        while ($row = mysqli_fetch_assoc($pref_result)) {
            $preferences[$row['preference_key']] = $row['preference_value'];
        }
    }
}

// Default theme colors
$default_colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
    'primary_light' => '#8bc5d6',
    'success' => '#28a745',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#17a2b8'
];

foreach ($default_colors as $key => $value) {
    if (!isset($colors[$key])) {
        $colors[$key] = $value;
    }
}

// Get current user info
if ($is_admin) {
    // Get admin info with roles
    $user_sql = "SELECT a.*, 
                GROUP_CONCAT(DISTINCT ar.role_name ORDER BY ara.is_primary DESC, ar.role_name SEPARATOR ', ') as roles,
                GROUP_CONCAT(DISTINCT CASE WHEN ara.is_primary = 1 THEN ar.role_name END) as primary_role
                FROM admins a
                LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
                LEFT JOIN admin_roles ar ON ara.role_id = ar.id
                WHERE a.id = $user_id
                GROUP BY a.id";
    $user_result = mysqli_query($conn, $user_sql);
    $current_user = mysqli_fetch_assoc($user_result);
    $user_name = $current_user['first_name'] . ' ' . $current_user['last_name'];
    $user_role = $current_user['primary_role'] ?: 'Staff';
    
    // Check user permissions
    $is_super_admin = strpos($current_user['roles'], 'Super Admin') !== false;
    $is_head_master = strpos($current_user['roles'], 'Head Master') !== false;
    $is_academic_master = strpos($current_user['roles'], 'Academic Master') !== false;
    $is_second_master = strpos($current_user['roles'], 'Second Master') !== false;
    
    // Users who can see all messages
    $can_see_all = $is_super_admin || $is_head_master || $is_academic_master || $is_second_master;
    
} else {
    // Get student info
    $student_sql = "SELECT * FROM students WHERE id = $user_id";
    $student_result = mysqli_query($conn, $student_sql);
    $current_user = mysqli_fetch_assoc($student_result);
    $user_name = $current_user['first_name'] . ' ' . $current_user['last_name'];
    $user_role = 'Student';
    $can_see_all = false;
}

// Handle form submission for new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority'] ?? 'normal');
    
    if (!empty($subject) && !empty($message)) {
        $insert_sql = "INSERT INTO support_messages 
                      (user_id, user_type, user_name, user_role, subject, message, priority, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("issssss", $user_id, $user_type, $user_name, $user_role, $subject, $message, $priority);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Your message has been sent successfully! Support team will respond shortly.";
        } else {
            $_SESSION['error'] = "Failed to send message. Please try again.";
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Please fill in all required fields.";
    }
    
    header("Location: help.php");
    exit();
}

// Handle reply submission (for admins only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $message_id = intval($_POST['message_id']);
    $reply_message = mysqli_real_escape_string($conn, $_POST['reply_message']);
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    
    if (!empty($reply_message)) {
        // Insert reply
        $insert_sql = "INSERT INTO support_replies 
                      (message_id, reply_by, reply_by_name, reply_by_role, reply_message, is_private) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iisssi", $message_id, $user_id, $user_name, $user_role, $reply_message, $is_private);
        
        if ($stmt->execute()) {
            // Update message status
            $update_sql = "UPDATE support_messages SET status = 'replied', updated_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $message_id);
            $update_stmt->execute();
            
            $_SESSION['success'] = "Reply sent successfully!";
        } else {
            $_SESSION['error'] = "Failed to send reply.";
        }
        $stmt->close();
    }
    
    header("Location: help.php");
    exit();
}

// Handle status update (for admins only)
if ($is_admin && isset($_GET['update_status']) && isset($_GET['id'])) {
    $message_id = intval($_GET['id']);
    $new_status = mysqli_real_escape_string($conn, $_GET['update_status']);
    
    if (in_array($new_status, ['pending', 'replied', 'closed'])) {
        $update_sql = "UPDATE support_messages SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $new_status, $message_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Message status updated successfully!";
        }
        $stmt->close();
    }
    
    header("Location: help.php");
    exit();
}

// Handle assignment (for admins only)
if ($is_admin && isset($_GET['assign_to']) && isset($_GET['id'])) {
    $message_id = intval($_GET['id']);
    $assigned_to = intval($_GET['assign_to']);
    
    $update_sql = "UPDATE support_messages SET assigned_to = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ii", $assigned_to, $message_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Message assigned successfully!";
    }
    $stmt->close();
    
    header("Location: help.php");
    exit();
}

// Get user's messages
if ($is_admin && $can_see_all) {
    // Admins with permission can see all messages
    $messages_sql = "SELECT m.*, 
                    (SELECT COUNT(*) FROM support_replies WHERE message_id = m.id) as reply_count
                    FROM support_messages m 
                    ORDER BY 
                        CASE 
                            WHEN m.status = 'pending' THEN 1 
                            WHEN m.status = 'replied' THEN 2 
                            ELSE 3 
                        END,
                        m.created_at DESC";
} else if ($is_admin) {
    // Regular admins can only see messages they created or are assigned to
    $messages_sql = "SELECT m.*, 
                    (SELECT COUNT(*) FROM support_replies WHERE message_id = m.id) as reply_count
                    FROM support_messages m 
                    WHERE m.user_id = $user_id OR m.assigned_to = $user_id
                    ORDER BY m.created_at DESC";
} else {
    // Students can only see their own messages
    $messages_sql = "SELECT m.*, 
                    (SELECT COUNT(*) FROM support_replies WHERE message_id = m.id) as reply_count
                    FROM support_messages m 
                    WHERE m.user_id = $user_id AND m.user_type = 'student'
                    ORDER BY m.created_at DESC";
}

$messages_result = mysqli_query($conn, $messages_sql);
$messages = [];
if ($messages_result && mysqli_num_rows($messages_result) > 0) {
    while ($row = mysqli_fetch_assoc($messages_result)) {
        $messages[] = $row;
    }
}

// Get all admins for assignment (for super admin)
$admins_list = [];
if ($is_super_admin) {
    $admins_sql = "SELECT a.id, a.first_name, a.last_name, 
                  GROUP_CONCAT(DISTINCT ar.role_name SEPARATOR ', ') as roles
                  FROM admins a
                  LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
                  LEFT JOIN admin_roles ar ON ara.role_id = ar.id
                  GROUP BY a.id
                  ORDER BY a.first_name";
    $admins_result = mysqli_query($conn, $admins_sql);
    while ($row = mysqli_fetch_assoc($admins_result)) {
        $admins_list[] = $row;
    }
}

// Get statistics
$stats_sql = "";
if ($is_admin && $can_see_all) {
    $stats_sql = "SELECT 
                  COUNT(*) as total,
                  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                  SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied,
                  SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
                  FROM support_messages";
} else if ($is_admin) {
    $stats_sql = "SELECT 
                  COUNT(*) as total,
                  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                  SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied,
                  SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
                  FROM support_messages 
                  WHERE user_id = $user_id OR assigned_to = $user_id";
} else {
    $stats_sql = "SELECT 
                  COUNT(*) as total,
                  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                  SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied,
                  SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
                  FROM support_messages 
                  WHERE user_id = $user_id AND user_type = 'student'";
}

$stats_result = mysqli_query($conn, $stats_sql);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : ['total' => 0, 'pending' => 0, 'replied' => 0, 'closed' => 0];
?>

<?php 
if ($is_admin) {
    include '../controller/header.php';
    include '../controller/sidebar.php';
} else {
    include 'header.php';
    include 'sidebar_student.php';
}
?>

<!-- SweetAlert2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<style>
    :root {
        --primary-color: <?php echo $colors['primary']; ?>;
        --primary-dark: <?php echo $colors['primary_dark']; ?>;
        --primary-light: <?php echo $colors['primary_light']; ?>;
        --success-color: <?php echo $colors['success']; ?>;
        --danger-color: <?php echo $colors['danger']; ?>;
        --warning-color: <?php echo $colors['warning']; ?>;
        --info-color: <?php echo $colors['info']; ?>;
    }

    .main-content {
        min-height: calc(100vh - 60px);
        padding: 20px;
        margin-top: 5px;
    }

    @media (min-width: 992px) {
        .main-content {
            margin-left: 250px;
        }
    }
    /* Contact Card Modern Styles */
.contact-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 24px;
    padding: 30px;
    color: white;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    margin-top: 40px;
    position: relative;
    overflow: hidden;
}

.contact-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.contact-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 30px;
    position: relative;
    z-index: 1;
}

.contact-header i {
    font-size: 32px;
    background: rgba(255,255,255,0.2);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.contact-header h5 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

.contact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
    position: relative;
    z-index: 1;
}

.contact-group {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 20px;
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.3s ease;
}

.contact-group:hover {
    transform: translateY(-5px);
    background: rgba(255,255,255,0.15);
    border-color: rgba(255,255,255,0.3);
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.group-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(255,255,255,0.2);
}

.group-header i {
    font-size: 20px;
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.group-header h6 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.group-content {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.contact-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.contact-icon {
    width: 32px;
    height: 32px;
    background: rgba(255,255,255,0.15);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.contact-details {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.contact-label {
    font-size: 11px;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.contact-number,
.contact-email,
.contact-text {
    font-size: 14px;
    color: white;
    text-decoration: none;
    transition: all 0.2s ease;
}

.contact-number:hover,
.contact-email:hover {
    color: #ffd700;
    transform: translateX(5px);
}

/* Quick Actions */
.contact-actions {
    margin: 30px 0 20px;
    position: relative;
    z-index: 1;
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    justify-content: center;
}

.action-btn {
    flex: 1;
    min-width: 160px;
    padding: 15px 20px;
    border-radius: 12px;
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.action-btn i {
    font-size: 20px;
}

.action-btn.emergency {
    background: linear-gradient(135deg, #dc3545, #c82333);
}

.action-btn.emergency:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
    border-color: white;
}

.action-btn.email {
    background: linear-gradient(135deg, #17a2b8, #138496);
}

.action-btn.email:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(23, 162, 184, 0.3);
    border-color: white;
}

.action-btn.whatsapp {
    background: linear-gradient(135deg, #25D366, #128C7E);
}

.action-btn.whatsapp:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(37, 211, 102, 0.3);
    border-color: white;
}

/* Contact Footer */
.contact-footer {
    background: rgba(0,0,0,0.2);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    position: relative;
    z-index: 1;
    border: 1px solid rgba(255,255,255,0.1);
}

.info-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
}

.info-item i {
    width: 24px;
    color: #ffd700;
}

/* Responsive */
@media (max-width: 768px) {
    .contact-card {
        padding: 20px;
    }
    
    .contact-header h5 {
        font-size: 20px;
    }
    
    .contact-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
    }
    
    .group-header i {
        width: 32px;
        height: 32px;
        font-size: 16px;
    }
}

@media (max-width: 576px) {
    .contact-header i {
        width: 50px;
        height: 50px;
        font-size: 24px;
    }
    
    .contact-header h5 {
        font-size: 18px;
    }
    
    .contact-row {
        flex-wrap: wrap;
    }
    
    .contact-icon {
        width: 28px;
        height: 28px;
        font-size: 12px;
    }
    
    .contact-number,
    .contact-email,
    .contact-text {
        font-size: 13px;
    }
}

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
        border: 1px solid #eee;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(59, 157, 179, 0.15);
        border-color: var(--primary-light);
    }

    .stat-info h3 {
        font-size: 28px;
        font-weight: 700;
        color: #333;
        margin: 0 0 5px 0;
    }

    .stat-info p {
        font-size: 13px;
        color: #666;
        margin: 0;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary-light), var(--primary-color));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }

    /* Message Cards */
    .message-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }

    .message-card.pending {
        border-left-color: var(--warning-color);
    }

    .message-card.replied {
        border-left-color: var(--success-color);
    }

    .message-card.closed {
        border-left-color: #6c757d;
        opacity: 0.8;
    }

    .message-card:hover {
        transform: translateX(5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }

    .message-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 16px;
    }

    .user-details h5 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
    }

    .user-details small {
        color: #666;
    }

    .message-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .badge-custom {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .badge-pending {
        background: #fff3cd;
        color: #856404;
    }

    .badge-replied {
        background: #d4edda;
        color: #155724;
    }

    .badge-closed {
        background: #e2e3e5;
        color: #383d41;
    }

    .badge-low {
        background: #d1ecf1;
        color: #0c5460;
    }

    .badge-normal {
        background: #d4edda;
        color: #155724;
    }

    .badge-high {
        background: #fff3cd;
        color: #856404;
    }

    .badge-urgent {
        background: #f8d7da;
        color: #721c24;
    }

    .message-subject {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--primary-dark);
    }

    .message-content {
        color: #555;
        line-height: 1.6;
        margin-bottom: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
    }

    .message-meta {
        display: flex;
        gap: 20px;
        color: #666;
        font-size: 12px;
        margin-bottom: 15px;
    }

    .message-meta i {
        margin-right: 5px;
        color: var(--primary-color);
    }

    /* Replies Section */
    .replies-section {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }

    .reply-item {
        margin-left: 50px;
        margin-bottom: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
        border-left: 3px solid var(--primary-color);
    }

    .reply-item.private {
        border-left-color: var(--danger-color);
        background: #fff3cd;
    }

    .reply-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .reply-author {
        font-weight: 600;
        color: var(--primary-dark);
    }

    .reply-time {
        font-size: 11px;
        color: #666;
    }

    .reply-content {
        color: #333;
        line-height: 1.5;
        font-size: 13px;
    }

    .private-badge {
        background: var(--danger-color);
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        margin-left: 10px;
    }

    /* Form Styles */
    .form-card {
        background: white;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        border: 2px dashed var(--primary-light);
    }

    .form-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
    }

    .form-title i {
        font-size: 24px;
        color: var(--primary-color);
    }

    .form-title h4 {
        margin: 0;
        color: #333;
        font-weight: 600;
    }

    /* FAQ Section */
    .faq-section {
        background: white;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }

    .faq-item {
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }

    .faq-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .faq-question {
        font-weight: 600;
        color: var(--primary-dark);
        margin-bottom: 8px;
    }

    .faq-answer {
        color: #555;
        line-height: 1.6;
        padding-left: 20px;
    }

    /* Contact Card */
    .contact-card {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(59, 157, 179, 0.3);
    }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }

    .contact-item i {
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    /* Admin Controls */
    .admin-controls {
        display: flex;
        gap: 8px;
        margin-top: 10px;
        flex-wrap: wrap;
    }

    .admin-btn {
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-assign {
        background: #e3f2fd;
        color: #1976d2;
    }

    .btn-assign:hover {
        background: #1976d2;
        color: white;
    }

    .btn-close-ticket {
        background: #e2e3e5;
        color: #383d41;
    }

    .btn-close-ticket:hover {
        background: #6c757d;
        color: white;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .message-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .reply-item {
            margin-left: 20px;
        }
    }

    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                <i class="fas fa-life-ring me-2" style="color: var(--primary-color);"></i>
                Help & Support Center
            </h2>
            <?php if ($is_admin && $can_see_all): ?>
                <span class="badge bg-primary">Staff Access: <?php echo $user_role; ?></span>
            <?php endif; ?>
        </div>

        <!-- SweetAlert2 Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div id="successMessage" data-message="<?php echo htmlspecialchars($_SESSION['success']); ?>"></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div id="errorMessage" data-message="<?php echo htmlspecialchars($_SESSION['error']); ?>"></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistics Cards (for staff) -->
        <?php if ($is_admin): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Messages</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $stats['replied']; ?></h3>
                    <p>Replied</p>
                </div>
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $stats['closed']; ?></h3>
                    <p>Closed</p>
                </div>
                <div class="stat-icon" style="background: linear-gradient(135deg, #6c757d, #5a6268);">
                    <i class="fas fa-archive"></i>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- New Message Form -->
        <div class="form-card">
            <div class="form-title">
                <i class="fas fa-paper-plane"></i>
                <h4>Send a Message to Support Team</h4>
            </div>
            
            <form method="POST" action="" id="supportForm">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" required placeholder="Brief summary of your issue">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <label class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control" rows="5" required placeholder="Describe your issue in detail..."></textarea>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" name="send_message" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- FAQ Section -->
        <div class="faq-section">
            <h5 class="mb-4"><i class="fas fa-question-circle me-2" style="color: var(--primary-color);"></i>Frequently Asked Questions</h5>
            
            <div class="faq-item">
                <div class="faq-question">1. How can I view my results?</div>
                <div class="faq-answer">Go to the <strong>Results</strong> section in your dashboard. You can view all your academic results, download report cards, and track your progress over time.</div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">2. How can I check my fees balance?</div>
                <div class="faq-answer">Open the <strong>Fees</strong> page from the main menu. You'll see your current balance, payment history, and can generate payment receipts.</div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">3. I forgot my password. What should I do?</div>
                <div class="faq-answer">Contact the school administration or send a message through this support form. They will help you reset your password securely.</div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">4. How do I update my personal information?</div>
                <div class="faq-answer">Go to your <strong>Profile</strong> section where you can update your contact details, phone number, and other personal information.</div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">5. Who can see my support messages?</div>
                <div class="faq-answer">Your messages are visible to school administrators including Head Master, Academic Master, and Second Master. It can see all messages and assign them to specific staff members.</div>
            </div>
        </div>

        <!-- Messages History -->
        <h5 class="mb-3"><i class="fas fa-history me-2" style="color: var(--primary-color);"></i>Your Messages</h5>
        
        <?php if (empty($messages)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-4x mb-3" style="color: #ddd;"></i>
                <h5 class="text-muted">No messages yet</h5>
                <p>Send your first message using the form above</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <?php
                // Get replies for this message
                $replies_sql = "SELECT r.*, a.first_name, a.last_name 
                               FROM support_replies r
                               LEFT JOIN admins a ON r.reply_by = a.id
                               WHERE r.message_id = {$msg['id']}
                               ORDER BY r.created_at ASC";
                $replies_result = mysqli_query($conn, $replies_sql);
                $replies = [];
                while ($reply = mysqli_fetch_assoc($replies_result)) {
                    $replies[] = $reply;
                }
                ?>
                
                <div class="message-card <?php echo $msg['status']; ?>" id="message-<?php echo $msg['id']; ?>">
                    <div class="message-header">
                        <div class="user-info">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($msg['user_name'], 0, 1)); ?>
                            </div>
                            <div class="user-details">
                                <h5><?php echo htmlspecialchars($msg['user_name']); ?></h5>
                                <small>
                                    <i class="fas fa-user-tag me-1"></i><?php echo htmlspecialchars($msg['user_role']); ?>
                                    
                                </small>
                            </div>
                        </div>
                        
                        <div class="message-badges">
                            <span class="badge-custom badge-<?php echo $msg['status']; ?>">
                                <i class="fas <?php echo $msg['status'] == 'pending' ? 'fa-clock' : ($msg['status'] == 'replied' ? 'fa-check' : 'fa-archive'); ?> me-1"></i>
                                <?php echo ucfirst($msg['status']); ?>
                            </span>
                            <span class="badge-custom badge-<?php echo $msg['priority']; ?>">
                                <i class="fas fa-flag me-1"></i>
                                <?php echo ucfirst($msg['priority']); ?>
                            </span>
                            <?php if ($msg['assigned_to']): ?>
                                <span class="badge-custom" style="background: #e3f2fd; color: #1976d2;">
                                    <i class="fas fa-user-check me-1"></i>Assigned
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="message-subject">
                        <?php echo htmlspecialchars($msg['subject']); ?>
                    </div>
                    
                    <div class="message-content">
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    </div>
                    
                    <div class="message-meta">
                        <span><i class="far fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></span>
                        <span><i class="far fa-comments"></i> <?php echo count($replies); ?> replies</span>
                        <?php if ($msg['assigned_to']): ?>
                            <span><i class="fas fa-user-tie"></i> Assigned to Staff #<?php echo $msg['assigned_to']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Replies Section -->
                    <?php if (!empty($replies)): ?>
                    <div class="replies-section">
                        <h6 class="mb-3"><i class="fas fa-reply-all me-2" style="color: var(--primary-color);"></i>Replies</h6>
                        
                        <?php foreach ($replies as $reply): ?>
                        <div class="reply-item <?php echo $reply['is_private'] ? 'private' : ''; ?>">
                            <div class="reply-header">
                                <div>
                                    <span class="reply-author">
                                        <?php echo htmlspecialchars($reply['reply_by_name']); ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars($reply['reply_by_role']); ?>)</small>
                                    </span>
                                    <?php if ($reply['is_private']): ?>
                                        <span class="private-badge"><i class="fas fa-lock me-1"></i>Private Note</span>
                                    <?php endif; ?>
                                </div>
                                <span class="reply-time">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?>
                                </span>
                            </div>
                            <div class="reply-content">
                                <?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Reply Form (for admins) -->
                    <?php if ($is_admin && $msg['status'] != 'closed'): ?>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#replyForm-<?php echo $msg['id']; ?>">
                            <i class="fas fa-reply me-1"></i>Reply to this message
                        </button>
                        
                        <?php if ($is_super_admin || $is_head_master): ?>
                        <div class="admin-controls">
                            <?php if (!$msg['assigned_to'] && $is_super_admin): ?>
                            <div class="dropdown d-inline-block">
                                <button class="admin-btn btn-assign dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-plus me-1"></i>Assign
                                </button>
                                <ul class="dropdown-menu">
                                    <?php foreach ($admins_list as $admin): ?>
                                    <li>
                                        <a class="dropdown-item" href="?assign_to=<?php echo $admin['id']; ?>&id=<?php echo $msg['id']; ?>">
                                            <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                                            <br><small><?php echo htmlspecialchars($admin['roles']); ?></small>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <a href="?update_status=closed&id=<?php echo $msg['id']; ?>" class="admin-btn btn-close-ticket" onclick="return confirm('Close this ticket?')">
                                <i class="fas fa-check-circle me-1"></i>Close Ticket
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="collapse mt-3" id="replyForm-<?php echo $msg['id']; ?>">
                            <form method="POST" action="">
                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                <div class="card card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Your Reply</label>
                                        <textarea name="reply_message" class="form-control" rows="3" required></textarea>
                                    </div>
                                    <?php if ($is_super_admin || $is_head_master): ?>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_private" class="form-check-input" id="private-<?php echo $msg['id']; ?>">
                                            <label class="form-check-label" for="private-<?php echo $msg['id']; ?>">
                                                <i class="fas fa-lock me-1 text-danger"></i>
                                                Private note (only visible to admins)
                                            </label>
                                        </div>
                                        <small class="text-muted">Private notes are not visible to the user</small>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <button type="submit" name="send_reply" class="btn btn-primary btn-sm">
                                            <i class="fas fa-paper-plane me-1"></i>Send Reply
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <!-- School Contact Card -->
<div class="contact-card mt-4">
    <div class="contact-header">
        <i class="fas fa-phone-alt"></i>
        <h5>School Contact Information</h5>
    </div>
    
    <div class="contact-grid">
        <!-- IT Support -->
        <div class="contact-group">
            <div class="group-header">
                <i class="fas fa-laptop-code"></i>
                <h6>IT Support (Tzonetech)</h6>
            </div>
            <div class="group-content">
                <div class="contact-row">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="contact-details">
                        <span class="contact-label">Phone 1</span>
                        <a href="tel:+255714343162" class="contact-number">
                            <strong>+255 714 343 162</strong>
                        </a>
                    </div>
                </div>
                <div class="contact-row">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="contact-details">
                        <span class="contact-label">Phone 2</span>
                        <a href="tel:+255619844080" class="contact-number">
                            <strong>+255 619 844 080</strong>
                        </a>
                    </div>
                </div>
                <div class="contact-row">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-details">
                        <span class="contact-label">Email</span>
                        <a href="mailto:it@muyovozi.sch.tz" class="contact-email">
                            <strong>it@muyovozi.sch.tz</strong>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Head Master -->
        <div class="contact-group">
            <div class="group-header">
                <i class="fas fa-crown"></i>
                <h6>Head Master</h6>
            </div>
            <div class="group-content">
                <div class="contact-row">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="contact-details">
                        <span class="contact-label">Direct Line</span>
                        <a href="tel:+255653022775" class="contact-number">
                            <strong>+255 653 022 775</strong>
                        </a>
                    </div>
                </div>
                <div class="contact-row">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-details">
                        <span class="contact-label">Email</span>
                        <a href="mailto:headmaster@muyovozi.sch.tz" class="contact-email">
                            <strong>headmaster@muyovozi.sch.tz</strong>
                        </a>
                    </div>
                </div>
                <div class="contact-row">
                    <div class="contact-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="contact-details">
                        <span class="contact-label">Office Hours</span>
                        <span class="contact-text">Mon-Fri: 8:00 AM - 3:00 PM</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Master -->
        <div class="contact-group">
            <div class="group-header">
                <i class="fas fa-graduation-cap"></i>
                <h6>Academic Master</h6>
            </div>
            <div class="group-content">
                <div class="contact-row">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="contact-details">
                        <span class="contact-label">Direct Line</span>
                        <a href="tel:+255627058362" class="contact-number">
                            <strong>+255 627 058 362</strong>
                        </a>
                    </div>
                </div>
                <div class="contact-row">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-details">
                        <span class="contact-label">Email</span>
                        <a href="mailto:academic@muyovozi.sch.tz" class="contact-email">
                            <strong>academic@muyovozi.sch.tz</strong>
                        </a>
                    </div>
                </div>
                <div class="contact-row">
                    <div class="contact-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="contact-details">
                        <span class="contact-label">Office Hours</span>
                        <span class="contact-text">Mon-Fri: 8:00 AM - 4:00 PM</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Master -->
        <div class="contact-group">
            <div class="group-header">
                <i class="fas fa-user-tie"></i>
                <h6>Second Master</h6>
            </div>
            <div class="group-content">
                <div class="contact-row">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="contact-details">
                        <span class="contact-label">Direct Line</span>
                        <a href="tel:+255620744903" class="contact-number">
                            <strong>+255 620 744 903</strong>
                        </a>
                    </div>
                </div>
                <div class="contact-row">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-details">
                        <span class="contact-label">Email</span>
                        <a href="mailto:secondmaster@muyovozi.sch.tz" class="contact-email">
                            <strong>secondmaster@muyovozi.sch.tz</strong>
                        </a>
                    </div>
                </div>
                <div class="contact-row">
                    <div class="contact-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="contact-details">
                        <span class="contact-label">Office Hours</span>
                        <span class="contact-text">Mon-Fri: 8:00 AM - 4:00 PM</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="contact-actions">
        <div class="action-buttons">
            <a href="tel:+255714343162" class="action-btn emergency">
                <i class="fas fa-phone-alt"></i>
                <span>Emergency IT Support</span>
            </a>
            <a href="mailto:support@muyovozi.sch.tz" class="action-btn email">
                <i class="fas fa-envelope"></i>
                <span>General Support</span>
            </a>
            <a href="#" class="action-btn whatsapp" onclick="window.open('https://wa.me/255714343162', '_blank')">
                <i class="fab fa-whatsapp"></i>
                <span>WhatsApp</span>
            </a>
        </div>
    </div>

    <!-- Additional Info -->
    <div class="contact-footer">
        <div class="info-item">
            <i class="fas fa-building"></i>
            <span>Muyovozi High School, P.O. Box 123, Tanzania</span>
        </div>
        <div class="info-item">
            <i class="fas fa-clock"></i>
            <span>Support Hours: Monday - Friday: 8:00 AM - 4:00 PM</span>
        </div>
        <div class="info-item">
            <i class="fas fa-exclamation-circle"></i>
            <span>For urgent matters, please call IT Support directly</span>
        </div>
    </div>
</div>

<script>
// SweetAlert2 notifications
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    
    if (successMessage) {
        const message = successMessage.getAttribute('data-message');
        Swal.fire({
            title: 'Success!',
            text: message,
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#3B9DB3',
            timer: 3000,
            timerProgressBar: true,
            toast: true,
            position: 'top-end',
            showConfirmButton: false
        });
    }
    
    if (errorMessage) {
        const message = errorMessage.getAttribute('data-message');
        Swal.fire({
            title: 'Error!',
            text: message,
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#d33',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    }
});

// Form validation
document.getElementById('supportForm')?.addEventListener('submit', function(e) {
    const subject = document.querySelector('input[name="subject"]').value.trim();
    const message = document.querySelector('textarea[name="message"]').value.trim();
    
    if (!subject || !message) {
        e.preventDefault();
        Swal.fire({
            title: 'Error!',
            text: 'Please fill in all required fields',
            icon: 'error',
            confirmButtonColor: '#d33'
        });
    }
});
</script>

<?php
if ($is_admin) {
    include '../controller/footer.php';
} else {
    include '../controller/footer.php';
}
?>