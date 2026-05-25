<?php
// candidates/notifications.php
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in (student or public)
$is_student_logged_in = isset($_SESSION['student_id']);
$student_id = $is_student_logged_in ? $_SESSION['student_id'] : null;
$student_info = null;

if ($is_student_logged_in) {
    // Get student info
    $student_sql = "SELECT first_name, last_name, class, combination FROM students WHERE id = ?";
    $student_stmt = mysqli_prepare($conn, $student_sql);
    mysqli_stmt_bind_param($student_stmt, "i", $student_id);
    mysqli_stmt_execute($student_stmt);
    $student_result = mysqli_stmt_get_result($student_stmt);
    $student_info = mysqli_fetch_assoc($student_result);
    mysqli_stmt_close($student_stmt);
}

// Handle marking notification as read (for logged-in students)
if (isset($_GET['action']) && $is_student_logged_in && $_GET['action'] == 'mark_read' && isset($_GET['id'])) {
    $notification_id = intval($_GET['id']);
    
    // Check if already viewed
    $check_sql = "SELECT * FROM notification_views 
                  WHERE notification_id = ? AND viewer_id = ? AND viewer_type = 'student'";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $notification_id, $student_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) == 0) {
        // Add view record
        $view_sql = "INSERT INTO notification_views (notification_id, viewer_id, viewer_type) 
                     VALUES (?, ?, 'student')";
        $view_stmt = mysqli_prepare($conn, $view_sql);
        mysqli_stmt_bind_param($view_stmt, "ii", $notification_id, $student_id);
        mysqli_stmt_execute($view_stmt);
        mysqli_stmt_close($view_stmt);
        
        // Update views count
        $update_sql = "UPDATE notifications SET views_count = views_count + 1 WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $notification_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
    mysqli_stmt_close($check_stmt);
    
    // Return to the same page or redirect to a specific notification
    if (isset($_GET['redirect'])) {
        header("Location: " . $_GET['redirect']);
        exit();
    } else {
        header("Location: notifications.php");
        exit();
    }
}

// Handle mark all as read
if (isset($_GET['action']) && $is_student_logged_in && $_GET['action'] == 'mark_all_read') {
    // Get all unread notifications for this student
    $unread_sql = "SELECT n.id FROM notifications n
                   WHERE n.status = 'active' 
                   AND (n.visibility = 'public' OR n.visibility = 'students_only')
                   AND n.id NOT IN (
                       SELECT notification_id FROM notification_views 
                       WHERE viewer_id = ? AND viewer_type = 'student'
                   )";
    $unread_stmt = mysqli_prepare($conn, $unread_sql);
    mysqli_stmt_bind_param($unread_stmt, "i", $student_id);
    mysqli_stmt_execute($unread_stmt);
    $unread_result = mysqli_stmt_get_result($unread_stmt);
    
    while ($row = mysqli_fetch_assoc($unread_result)) {
        // Insert view record for each unread notification
        $insert_sql = "INSERT INTO notification_views (notification_id, viewer_id, viewer_type) 
                       VALUES (?, ?, 'student')";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "ii", $row['id'], $student_id);
        mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);
        
        // Update views count
        $update_sql = "UPDATE notifications SET views_count = views_count + 1 WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $row['id']);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
    mysqli_stmt_close($unread_stmt);
    
    $_SESSION['success'] = "All notifications marked as read!";
    header("Location: notifications.php");
    exit();
}

// Get notifications based on visibility
$where_conditions = ["n.status = 'active'"];
$params = [];

if ($is_student_logged_in) {
    // Students see public and students_only notifications
    $where_conditions[] = "(n.visibility = 'public' OR n.visibility = 'students_only')";
} else {
    // Public/guests only see public notifications
    $where_conditions[] = "n.visibility = 'public'";
}

// Build WHERE clause
$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get notifications with author info and view status
$sql = "SELECT n.*, 
       CONCAT(a.first_name, ' ', a.last_name) as author_name,
       a.profile_image as author_image,
       GROUP_CONCAT(DISTINCT ar.role_name SEPARATOR ', ') as author_roles,
       (SELECT COUNT(*) FROM notification_views nv WHERE nv.notification_id = n.id) as total_views,
       " . ($is_student_logged_in ? 
          "(SELECT COUNT(*) FROM notification_views nv2 
            WHERE nv2.notification_id = n.id AND nv2.viewer_id = ? AND nv2.viewer_type = 'student')" : 
          "0") . " as is_viewed
       FROM notifications n
       JOIN admins a ON n.admin_id = a.id
       LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
       LEFT JOIN admin_roles ar ON ara.role_id = ar.id
       $where_clause
       GROUP BY n.id
       ORDER BY n.is_starred DESC, n.priority = 'important' DESC, n.created_at DESC";

// Prepare and execute
$stmt = mysqli_prepare($conn, $sql);
if ($is_student_logged_in) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$notifications = [];
$unread_count = 0;

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
        if ($is_student_logged_in && $row['is_viewed'] == 0) {
            $unread_count++;
        }
    }
}
mysqli_stmt_close($stmt);

// Get latest notification timestamp for "new" badge
$latest_sql = "SELECT MAX(created_at) as latest FROM notifications WHERE status = 'active'";
$latest_result = mysqli_query($conn, $latest_sql);
$latest_row = mysqli_fetch_assoc($latest_result);
$latest_notification_time = $latest_row['latest'] ?? null;

// Function to get file icon
function getFileIcon($file_type) {
    $icons = [
        'image' => 'fa-file-image',
        'video' => 'fa-file-video',
        'audio' => 'fa-file-audio',
        'document' => 'fa-file-pdf',
        'archive' => 'fa-file-archive',
        'other' => 'fa-file'
    ];
    return $icons[$file_type] ?? 'fa-file';
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Function to get time ago
function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}

// Get file extension
function getFileExtension($filename) {
    return pathinfo($filename, PATHINFO_EXTENSION);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Muyovozi High School</title>
    
    <!-- Include header and sidebar -->
    <?php include 'header.php'; ?>
    <?php include 'sidebar_student.php'; ?>
    
    <!-- SweetAlert2 for better alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-color: #1e3c72;
            --secondary-color: #2a5298;
            --accent-color: #4CAF50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196F3;
        }

        .main-content {
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s;
        }

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
            }
        }

        /* Notification Cards */
        .notification-card {
            background: white;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-left: 5px solid var(--primary-color);
        }

        .notification-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .notification-card.important {
            border-left-color: var(--danger-color);
            background: linear-gradient(135deg, #fff5f5, #ffffff);
        }

        .notification-card.important::before {
            content: 'IMPORTANT';
            position: absolute;
            top: 10px;
            right: -30px;
            background: var(--danger-color);
            color: white;
            padding: 5px 30px;
            font-size: 12px;
            font-weight: bold;
            transform: rotate(45deg);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .notification-card.starred {
            border-left-color: #FFD700;
            background: linear-gradient(135deg, #fff9e6, #ffffff);
        }

        .notification-card.unread {
            border-left-color: var(--info-color);
            background: linear-gradient(135deg, #e3f2fd, #ffffff);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 4px 15px rgba(33, 150, 243, 0.1); }
            50% { box-shadow: 0 8px 25px rgba(33, 150, 243, 0.3); }
            100% { box-shadow: 0 4px 15px rgba(33, 150, 243, 0.1); }
        }

        .notification-header {
            padding: 20px 20px 10px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            border: 3px solid rgba(255,255,255,0.2);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

        .author-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .author-info {
            flex: 1;
        }

        .author-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 3px;
            font-size: 1.1rem;
        }

        .author-role {
            font-size: 0.8rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .priority-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .priority-normal {
            background: #e9ecef;
            color: #495057;
        }

        .priority-important {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-starred {
            background: #fff3cd;
            color: #856404;
        }

        .notification-time {
            font-size: 0.8rem;
            color: #999;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .notification-content {
            padding: 0 20px 15px 85px;
        }

        .notification-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .notification-description {
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        /* File Attachment */
        .file-attachment {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .file-attachment:hover {
            background: #e9ecef;
            border-color: var(--primary-color);
        }

        .file-icon-large {
            font-size: 2.5rem;
            color: var(--primary-color);
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
            word-break: break-word;
        }

        .file-meta {
            font-size: 0.8rem;
            color: #666;
        }

        .btn-download {
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .btn-download:hover {
            background: var(--secondary-color);
            transform: scale(1.1);
        }

        /* Image Preview */
        .image-preview {
            margin-top: 15px;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            position: relative;
            max-height: 300px;
        }

        .image-preview img {
            width: 100%;
            height: auto;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .image-preview:hover img {
            transform: scale(1.02);
        }

        .image-preview:hover::after {
            content: '🔍 Click to enlarge';
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        /* Video/Audio Player */
        .media-player {
            margin-top: 15px;
            border-radius: 10px;
            overflow: hidden;
            background: #f8f9fa;
            padding: 10px;
        }

        .media-player video,
        .media-player audio {
            width: 100%;
            border-radius: 5px;
        }

        /* Notification Footer */
        .notification-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .stats {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
            font-size: 0.85rem;
        }

        .visibility-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #e9ecef;
            color: #495057;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .visibility-badge.public {
            background: #d4edda;
            color: #155724;
        }

        .visibility-badge.students {
            background: #cce5ff;
            color: #004085;
        }

        /* Read More Button */
        .read-more-btn {
            color: var(--primary-color);
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .read-more-btn:hover {
            color: var(--secondary-color);
            gap: 8px;
        }

        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border-radius: 30px;
            border: 1px solid #dee2e6;
            background: white;
            color: #495057;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .filter-btn.active i {
            color: white;
        }

        .mark-read-btn {
            background: var(--info-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .mark-read-btn:hover:not(:disabled) {
            background: #1976d2;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(33, 150, 243, 0.3);
        }

        .mark-read-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .empty-state i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            color: #495057;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .empty-state p {
            color: #6c757d;
            max-width: 400px;
            margin: 0 auto;
        }

        /* New Badge */
        .new-badge {
            background: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 12px;
            margin-left: 10px;
            animation: pulse 2s infinite;
        }

        /* Load More Button */
        .load-more-btn {
            background: white;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 30px auto 0;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .load-more-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .notification-header {
                flex-direction: column;
                text-align: center;
            }

            .notification-content {
                padding: 0 20px 15px 20px;
            }

            .author-info {
                text-align: center;
            }

            .notification-footer {
                flex-direction: column;
                text-align: center;
            }

            .stats {
                justify-content: center;
            }

            .filter-bar {
                flex-direction: column;
                text-align: center;
            }

            .filter-buttons {
                justify-content: center;
            }

            .notification-card.important::before {
                font-size: 10px;
                padding: 3px 20px;
                right: -25px;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <?php include 'sidebar_student.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-bell me-2" style="color: var(--primary-color);"></i>
                        Notifications
                    </h2>
                    <p class="text-muted">
                        <?php if ($is_student_logged_in): ?>
                            Stay updated with school announcements and news
                        <?php else: ?>
                            Public announcements from Muyovozi High School
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-primary p-2">
                        <i class="fas fa-bell me-1"></i>
                        <?php echo count($notifications); ?> Total
                    </span>
                    <?php if ($is_student_logged_in && $unread_count > 0): ?>
                        <span class="badge bg-danger p-2">
                            <i class="fas fa-circle me-1"></i>
                            <?php echo $unread_count; ?> New
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Session Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filterNotifications('all')">
                        <i class="fas fa-th-large"></i> All
                    </button>
                    <button class="filter-btn" onclick="filterNotifications('important')">
                        <i class="fas fa-exclamation-circle"></i> Important
                    </button>
                    <button class="filter-btn" onclick="filterNotifications('starred')">
                        <i class="fas fa-star"></i> Featured
                    </button>
                    <?php if ($is_student_logged_in): ?>
                        <button class="filter-btn" onclick="filterNotifications('unread')">
                            <i class="fas fa-envelope"></i> Unread
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($is_student_logged_in && $unread_count > 0): ?>
                    <button class="mark-read-btn" onclick="markAllAsRead()">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                <?php endif; ?>
            </div>

            <!-- Notifications Container -->
            <div id="notificationsContainer">
                <?php if (empty($notifications)): ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h4>No Notifications</h4>
                        <p class="mb-4">
                            <?php if ($is_student_logged_in): ?>
                                There are no notifications for you at the moment. Check back later!
                            <?php else: ?>
                                No public announcements at this time. Please check back later.
                            <?php endif; ?>
                        </p>
                        <?php if (!$is_student_logged_in): ?>
                            <a href="../login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Login for More
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Notifications List -->
                    <div class="row">
                        <?php foreach ($notifications as $index => $notification): 
                            $is_new = $is_student_logged_in && $notification['is_viewed'] == 0;
                            $is_important = $notification['priority'] == 'important';
                            $is_starred = $notification['is_starred'] == 1;
                            $time_ago = timeAgo($notification['created_at']);
                            
                            // Get relative file path for display
                            $file_url = '';
                            if ($notification['file_path']) {
                                $file_url = str_replace('../../', '', $notification['file_path']);
                                $file_url = str_replace('../', '', $file_url);
                            }
                            
                            // Truncate long descriptions
                            $description = $notification['description'] ?? '';
                            $short_description = strlen($description) > 200 ? 
                                substr($description, 0, 200) . '...' : $description;
                            $has_long_description = strlen($description) > 200;
                        ?>
                        <div class="col-lg-6 mb-4 notification-item" 
                             data-priority="<?php echo $notification['priority']; ?>"
                             data-starred="<?php echo $notification['is_starred']; ?>"
                             data-unread="<?php echo $is_new ? 'true' : 'false'; ?>"
                             data-viewed="<?php echo $notification['is_viewed'] ?? 0; ?>">
                            
                            <div class="notification-card 
                                <?php echo $is_important ? 'important' : ''; ?>
                                <?php echo $is_starred ? 'starred' : ''; ?>
                                <?php echo $is_new ? 'unread' : ''; ?>"
                                <?php if ($is_new && $is_student_logged_in): ?>
                                    onclick="markAsRead(<?php echo $notification['id']; ?>, this)"
                                <?php endif; ?>>
                                
                                <!-- Notification Header -->
                                <div class="notification-header">
                                    <div class="author-avatar">
                                        <?php if ($notification['author_image'] && file_exists($notification['author_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($notification['author_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($notification['author_name']); ?>">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($notification['author_name'] ?? 'SA', 0, 2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="author-info">
                                        <div class="author-name">
                                            <?php echo htmlspecialchars($notification['author_name'] ?? 'School Admin'); ?>
                                            <?php if ($is_new && $is_student_logged_in): ?>
                                                <span class="new-badge">NEW</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="author-role">
                                            <span class="priority-badge priority-<?php echo $notification['priority']; ?>">
                                                <i class="fas fa-<?php echo $notification['priority'] == 'important' ? 'exclamation-circle' : 'info-circle'; ?>"></i>
                                                <?php echo ucfirst($notification['priority']); ?>
                                            </span>
                                            
                                            <?php if ($notification['author_roles']): ?>
                                                <span>• <?php echo htmlspecialchars(explode(',', $notification['author_roles'])[0]); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="notification-time" title="<?php echo date('F j, Y h:i A', strtotime($notification['created_at'])); ?>">
                                        <i class="far fa-clock"></i>
                                        <?php echo $time_ago; ?>
                                    </div>
                                </div>

                                <!-- Notification Content -->
                                <div class="notification-content">
                                    <h5 class="notification-title">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </h5>
                                    
                                    <?php if (!empty($description)): ?>
                                        <div class="notification-description" id="desc-<?php echo $notification['id']; ?>">
                                            <span class="short-desc"><?php echo nl2br(htmlspecialchars($short_description)); ?></span>
                                            <?php if ($has_long_description): ?>
                                                <span class="full-desc" style="display: none;"><?php echo nl2br(htmlspecialchars($description)); ?></span>
                                                <span class="read-more-btn" onclick="toggleDescription(<?php echo $notification['id']; ?>, event)">
                                                    <span class="read-more-text">Read More</span>
                                                    <i class="fas fa-chevron-down read-more-icon"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- File Attachment Preview -->
                                    <?php if ($notification['file_path'] && file_exists($notification['file_path'])): 
                                        $file_ext = strtolower(getFileExtension($notification['file_name']));
                                        $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                                        $is_video = in_array($file_ext, ['mp4', 'webm', 'ogg', 'mov']);
                                        $is_audio = in_array($file_ext, ['mp3', 'wav', 'ogg', 'm4a']);
                                    ?>
                                        
                                        <?php if ($is_image): ?>
                                            <!-- Image Preview -->
                                            <div class="image-preview" onclick="previewImage('<?php echo $file_url; ?>', '<?php echo htmlspecialchars($notification['title']); ?>')">
                                                <img src="<?php echo $file_url; ?>" alt="<?php echo htmlspecialchars($notification['title']); ?>">
                                            </div>
                                        <?php elseif ($is_video): ?>
                                            <!-- Video Player -->
                                            <div class="media-player">
                                                <video controls>
                                                    <source src="<?php echo $file_url; ?>" type="video/<?php echo $file_ext; ?>">
                                                    Your browser does not support the video tag.
                                                </video>
                                            </div>
                                        <?php elseif ($is_audio): ?>
                                            <!-- Audio Player -->
                                            <div class="media-player">
                                                <audio controls>
                                                    <source src="<?php echo $file_url; ?>" type="audio/<?php echo $file_ext; ?>">
                                                    Your browser does not support the audio tag.
                                                </audio>
                                            </div>
                                        <?php else: ?>
                                            <!-- File Attachment -->
                                            <div class="file-attachment d-flex align-items-center">
                                                <div class="file-icon-large me-3">
                                                    <i class="fas <?php echo getFileIcon($notification['file_type']); ?>"></i>
                                                </div>
                                                <div class="file-info">
                                                    <div class="file-name">
                                                        <?php echo htmlspecialchars($notification['file_name'] ?? 'Attachment'); ?>
                                                    </div>
                                                    <div class="file-meta">
                                                        <?php echo strtoupper($file_ext); ?> file • 
                                                        <?php echo formatFileSize($notification['file_size']); ?>
                                                    </div>
                                                </div>
                                                <a href="<?php echo $file_url; ?>" class="btn-download ms-3" target="_blank" title="Download" onclick="event.stopPropagation();">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <!-- Notification Footer -->
                                <div class="notification-footer">
                                    <div class="stats">
                                        <span class="stat-item" title="<?php echo $notification['total_views']; ?> views">
                                            <i class="fas fa-eye"></i>
                                            <?php echo $notification['total_views']; ?>
                                        </span>
                                        
                                        <?php if ($notification['file_path']): ?>
                                            <span class="stat-item" title="Has attachment">
                                                <i class="fas fa-paperclip"></i>
                                                1
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="visibility-info">
                                        <?php if ($notification['visibility'] == 'public'): ?>
                                            <span class="visibility-badge public">
                                                <i class="fas fa-globe"></i> Public
                                            </span>
                                        <?php else: ?>
                                            <span class="visibility-badge students">
                                                <i class="fas fa-user-graduate"></i> Students Only
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Load More Button (if many notifications) -->
                    <?php if (count($notifications) > 10): ?>
                        <div class="text-center">
                            <button class="load-more-btn" onclick="loadMoreNotifications()">
                                <i class="fas fa-spinner"></i> Load More
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white" id="previewModalTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img src="" id="previewImage" class="img-fluid rounded shadow-lg" style="max-height: 80vh;">
                </div>
            </div>
        </div>
    </div>

   
    <script>
        // Initialize variables
        let currentFilter = 'all';
        let displayedCount = <?php echo min(10, count($notifications)); ?>;
        const totalNotifications = <?php echo count($notifications); ?>;
        
        // Toggle description expand/collapse
        function toggleDescription(notificationId, event) {
            event.preventDefault();
            event.stopPropagation();
            
            const container = document.getElementById(`desc-${notificationId}`);
            const shortDesc = container.querySelector('.short-desc');
            const fullDesc = container.querySelector('.full-desc');
            const readMoreBtn = container.querySelector('.read-more-btn');
            const readMoreText = readMoreBtn.querySelector('.read-more-text');
            const readMoreIcon = readMoreBtn.querySelector('.read-more-icon');
            
            if (fullDesc.style.display === 'none') {
                shortDesc.style.display = 'none';
                fullDesc.style.display = 'inline';
                readMoreText.textContent = 'Show Less';
                readMoreIcon.className = 'fas fa-chevron-up read-more-icon';
            } else {
                shortDesc.style.display = 'inline';
                fullDesc.style.display = 'none';
                readMoreText.textContent = 'Read More';
                readMoreIcon.className = 'fas fa-chevron-down read-more-icon';
            }
        }
        
        // Mark notification as read
        function markAsRead(notificationId, element) {
            // Prevent if already clicked
            if (element.classList.contains('processing')) return;
            
            // Add processing class
            element.classList.add('processing');
            
            // Update UI optimistically
            const card = element.closest('.notification-card');
            if (card) {
                card.classList.remove('unread');
                
                // Remove new badge
                const newBadge = card.querySelector('.new-badge');
                if (newBadge) newBadge.remove();
                
                // Update data attribute
                const item = card.closest('.notification-item');
                if (item) {
                    item.setAttribute('data-unread', 'false');
                }
            }
            
            // Update unread count in header
            const unreadBadge = document.querySelector('.badge.bg-danger.p-2');
            const filterUnreadBtn = document.querySelector('.filter-btn[onclick="filterNotifications(\'unread\')"] .badge');
            
            if (unreadBadge) {
                let currentCount = parseInt(unreadBadge.textContent);
                if (currentCount > 1) {
                    unreadBadge.textContent = currentCount - 1;
                } else {
                    unreadBadge.remove();
                }
            }
            
            if (filterUnreadBtn) {
                let currentCount = parseInt(filterUnreadBtn.textContent);
                if (currentCount > 1) {
                    filterUnreadBtn.textContent = currentCount - 1;
                } else {
                    filterUnreadBtn.remove();
                }
            }
            
            // Send AJAX request
            fetch(`notifications.php?action=mark_read&id=${notificationId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                })
                .catch(error => {
                    console.error('Error marking as read:', error);
                    // Revert UI if error
                    if (card) {
                        card.classList.add('unread');
                    }
                })
                .finally(() => {
                    element.classList.remove('processing');
                });
        }
        
        // Mark all as read
        function markAllAsRead() {
            Swal.fire({
                title: 'Mark All as Read?',
                text: 'This will mark all notifications as read.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2196F3',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, mark all',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'notifications.php?action=mark_all_read';
                }
            });
        }
        
        // Filter notifications
        function filterNotifications(filter) {
            const items = document.querySelectorAll('.notification-item');
            const buttons = document.querySelectorAll('.filter-btn');
            let visibleCount = 0;
            
            // Update active button
            buttons.forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.closest('.filter-btn').classList.add('active');
            
            // Apply filter
            items.forEach(item => {
                let show = false;
                
                switch(filter) {
                    case 'all':
                        show = true;
                        break;
                    case 'important':
                        show = item.getAttribute('data-priority') === 'important';
                        break;
                    case 'starred':
                        show = item.getAttribute('data-starred') === '1';
                        break;
                    case 'unread':
                        show = item.getAttribute('data-unread') === 'true';
                        break;
                }
                
                if (show) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            currentFilter = filter;
            
            // Show empty state if no notifications match filter
            const container = document.getElementById('notificationsContainer');
            const existingEmptyState = container.querySelector('.empty-state-filtered');
            
            if (visibleCount === 0 && filter !== 'all') {
                if (!existingEmptyState) {
                    const emptyState = document.createElement('div');
                    emptyState.className = 'empty-state empty-state-filtered mt-4';
                    emptyState.innerHTML = `
                        <i class="fas fa-search"></i>
                        <h4>No ${filter} notifications</h4>
                        <p class="mb-4">No notifications match the "${filter}" filter.</p>
                        <button class="btn btn-outline-primary" onclick="filterNotifications('all')">
                            <i class="fas fa-th-large me-2"></i>Show All Notifications
                        </button>
                    `;
                    container.appendChild(emptyState);
                }
            } else if (existingEmptyState) {
                existingEmptyState.remove();
            }
        }
        
        // Preview image in modal
        function previewImage(src, title) {
            document.getElementById('previewImage').src = src;
            document.getElementById('previewModalTitle').textContent = title || 'Image Preview';
            const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
            modal.show();
        }
        
        // Load more notifications (pagination)
        function loadMoreNotifications() {
            const items = document.querySelectorAll('.notification-item');
            const start = displayedCount;
            const end = Math.min(start + 6, totalNotifications);
            
            for (let i = start; i < end; i++) {
                if (items[i]) {
                    items[i].style.display = 'block';
                }
            }
            
            displayedCount = end;
            
            // Hide load more button if all loaded
            if (displayedCount >= totalNotifications) {
                const loadMoreBtn = document.querySelector('.load-more-btn');
                if (loadMoreBtn) {
                    loadMoreBtn.style.display = 'none';
                }
            }
            
            // Re-apply current filter to new items
            if (currentFilter !== 'all') {
                filterNotifications(currentFilter);
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // 'M' key to mark all as read
            if ((e.key === 'm' || e.key === 'M') && (e.ctrlKey || e.metaKey) && <?php echo $is_student_logged_in ? 'true' : 'false'; ?>) {
                e.preventDefault();
                markAllAsRead();
            }
            
            // '1' key for all filter
            if (e.key === '1' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                const allBtn = document.querySelector('.filter-btn[onclick="filterNotifications(\'all\')"]');
                if (allBtn) allBtn.click();
            }
            
            // '2' key for important filter
            if (e.key === '2' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                const importantBtn = document.querySelector('.filter-btn[onclick="filterNotifications(\'important\')"]');
                if (importantBtn) importantBtn.click();
            }
            
            // '3' key for unread filter
            if (e.key === '3' && (e.ctrlKey || e.metaKey) && <?php echo $is_student_logged_in ? 'true' : 'false'; ?>) {
                e.preventDefault();
                const unreadBtn = document.querySelector('.filter-btn[onclick="filterNotifications(\'unread\')"]');
                if (unreadBtn) unreadBtn.click();
            }
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Hide notifications beyond initial display
            if (totalNotifications > 10) {
                const items = document.querySelectorAll('.notification-item');
                for (let i = 10; i < items.length; i++) {
                    items[i].style.display = 'none';
                }
            }
            
            // Update unread count in sidebar
            <?php if ($is_student_logged_in && $unread_count > 0): ?>
            const notificationLinks = document.querySelectorAll('a[href="notifications.php"]');
            notificationLinks.forEach(link => {
                // Remove existing badges
                const existingBadges = link.querySelectorAll('.badge');
                existingBadges.forEach(badge => badge.remove());
                
                // Add new badge
                const badge = document.createElement('span');
                badge.className = 'badge bg-danger ms-2';
                badge.textContent = '<?php echo $unread_count; ?>';
                link.appendChild(badge);
            });
            <?php endif; ?>
            
            // Auto-mark as read when scrolled into view (for a longer time)
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const item = entry.target;
                        const isUnread = item.getAttribute('data-unread') === 'true';
                        const viewed = item.getAttribute('data-viewed') === '0';
                        
                        if (isUnread && viewed) {
                            const card = item.querySelector('.notification-card');
                            const notificationId = card.getAttribute('data-id');
                            
                            // Wait 3 seconds before marking as read (user is viewing)
                            setTimeout(() => {
                                if (item.getAttribute('data-unread') === 'true') {
                                    const card = item.querySelector('.notification-card');
                                    if (card && !card.classList.contains('processing')) {
                                        markAsRead(<?php echo $notification['id'] ?? 0; ?>, card);
                                    }
                                }
                            }, 3000);
                        }
                    }
                });
            }, { threshold: 0.7 });
            
            // Observe unread notifications
            document.querySelectorAll('.notification-item[data-unread="true"]').forEach(item => {
                observer.observe(item);
            });
        });
    </script>
</body>
</html>
 <?php include '../controller/footer.php'; ?>
