<?php
// notifications.php - WITH SCHOOL ID FILTERING
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['admin_id']);
$admin_id = $is_logged_in ? $_SESSION['admin_id'] : null;
$is_admin = false;
$current_school_id = null;

if ($is_logged_in) {
    // ========== GET CURRENT SCHOOL ID ==========
    $school_query = "SELECT school_id FROM admins WHERE id = ?";
    $stmt = $conn->prepare($school_query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $school_result = $stmt->get_result();
    $current_admin_data = $school_result->fetch_assoc();
    $current_school_id = $current_admin_data['school_id'] ?? 1;
    
    // Get admin info and check if they are admin (Head Master or Second Master)
    $admin_sql = "SELECT a.*, 
                 GROUP_CONCAT(DISTINCT ar.role_name) as roles
                 FROM admins a
                 LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
                 LEFT JOIN admin_roles ar ON ara.role_id = ar.id
                 WHERE a.id = ? AND a.school_id = ?
                 GROUP BY a.id";
    $stmt = $conn->prepare($admin_sql);
    $stmt->bind_param("ii", $admin_id, $current_school_id);
    $stmt->execute();
    $admin_result = $stmt->get_result();
    
    if ($admin_result && $admin_result->num_rows > 0) {
        $admin_info = $admin_result->fetch_assoc();
        $admin_roles = explode(',', $admin_info['roles']);
        $is_admin = in_array('Head Master', $admin_roles) || in_array('Second Master', $admin_roles) || in_array('Academic Master', $admin_roles);
    }
}

// Handle GET actions (star, delete, etc.)
if (isset($_GET['action']) && $is_logged_in) {
    $notification_id = mysqli_real_escape_string($conn, $_GET['id'] ?? 0);
    
    switch ($_GET['action']) {
        case 'star':
            // Verify notification belongs to current school
            $check_sql = "SELECT id FROM notifications WHERE id = ? AND school_id = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ii", $notification_id, $current_school_id);
            $stmt->execute();
            $check_result = $stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $sql = "UPDATE notifications SET is_starred = NOT is_starred WHERE id = ? AND admin_id = ? AND school_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $notification_id, $admin_id, $current_school_id);
                $stmt->execute();
            }
            break;
            
        case 'delete':
            // Check if user can delete (owner or admin) and notification belongs to current school
            $check_sql = "SELECT * FROM notifications WHERE id = ? AND school_id = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ii", $notification_id, $current_school_id);
            $stmt->execute();
            $check_result = $stmt->get_result();
            $notification = $check_result->fetch_assoc();
            
            if ($notification && ($notification['admin_id'] == $admin_id || $is_admin)) {
                // Delete associated file
                if ($notification['file_path'] && file_exists($notification['file_path'])) {
                    unlink($notification['file_path']);
                }
                
                // Delete notification views
                $delete_views_sql = "DELETE FROM notification_views WHERE notification_id = ? AND school_id = ?";
                $stmt = $conn->prepare($delete_views_sql);
                $stmt->bind_param("ii", $notification_id, $current_school_id);
                $stmt->execute();
                
                // Delete notification
                $sql = "DELETE FROM notifications WHERE id = ? AND school_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $notification_id, $current_school_id);
                $stmt->execute();
                $_SESSION['success'] = "Notification deleted successfully!";
            }
            break;
            
        case 'delete_all':
            if ($is_admin && $current_school_id) {
                // Get all notifications to delete their files
                $sql = "SELECT file_path FROM notifications WHERE file_path IS NOT NULL AND school_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $current_school_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    if (file_exists($row['file_path'])) {
                        unlink($row['file_path']);
                    }
                }
                
                // Delete all notification views for this school
                $delete_all_views_sql = "DELETE FROM notification_views WHERE school_id = ?";
                $stmt = $conn->prepare($delete_all_views_sql);
                $stmt->bind_param("i", $current_school_id);
                $stmt->execute();
                
                // Delete all notifications for this school
                $sql = "DELETE FROM notifications WHERE school_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $current_school_id);
                $stmt->execute();
                $_SESSION['success'] = "All notifications deleted successfully!";
            }
            break;
            
        case 'archive':
            $sql = "UPDATE notifications SET status = 'archived' WHERE id = ? AND admin_id = ? AND school_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $notification_id, $admin_id, $current_school_id);
            $stmt->execute();
            break;
            
        case 'mark_all_read':
            if ($is_logged_in && $current_school_id) {
                $stmt = $conn->prepare("UPDATE admins SET last_notification_check = NOW() WHERE id = ? AND school_id = ?");
                $stmt->bind_param("ii", $admin_id, $current_school_id);
                $stmt->execute();
                $_SESSION['success'] = "All notifications marked as read!";
            }
            break;
            
        case 'mark_read':
            if ($is_logged_in && isset($_GET['id']) && $current_school_id) {
                $notification_id = mysqli_real_escape_string($conn, $_GET['id']);
                $viewer_type = 'admin';
                
                // Check if notification belongs to current school
                $check_sql = "SELECT id FROM notifications WHERE id = ? AND school_id = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("ii", $notification_id, $current_school_id);
                $stmt->execute();
                $check_result = $stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Check if already viewed
                    $check_view_sql = "SELECT * FROM notification_views 
                                      WHERE notification_id = ? AND viewer_id = ? AND school_id = ?";
                    $stmt = $conn->prepare($check_view_sql);
                    $stmt->bind_param("iii", $notification_id, $admin_id, $current_school_id);
                    $stmt->execute();
                    $check_view_result = $stmt->get_result();
                    
                    if ($check_view_result->num_rows == 0) {
                        // Add view record
                        $view_sql = "INSERT INTO notification_views (notification_id, viewer_id, viewer_type, school_id) 
                                    VALUES (?, ?, ?, ?)";
                        $stmt = $conn->prepare($view_sql);
                        $stmt->bind_param("iisi", $notification_id, $admin_id, $viewer_type, $current_school_id);
                        $stmt->execute();
                        
                        // Update views count
                        $update_sql = "UPDATE notifications SET views_count = views_count + 1 WHERE id = ? AND school_id = ?";
                        $stmt = $conn->prepare($update_sql);
                        $stmt->bind_param("ii", $notification_id, $current_school_id);
                        $stmt->execute();
                    }
                }
            }
            break;
    }
    
    header("Location: notifications.php");
    exit();
}

// Get notifications based on visibility and school_id
$where_conditions = [];
$params = [];
$param_types = "";

if ($is_logged_in && $current_school_id) {
    $where_conditions[] = "n.school_id = ?";
    $params[] = $current_school_id;
    $param_types .= "i";
    
    if ($is_admin) {
        // Admins can see all active notifications for their school
        $where_conditions[] = "n.status = 'active'";
    } else {
        // Regular logged-in users see public + their own private notifications
        $where_conditions[] = "(n.visibility = 'public' OR n.admin_id = ?)";
        $params[] = $admin_id;
        $param_types .= "i";
        $where_conditions[] = "n.status = 'active'";
    }
} elseif ($is_logged_in && !$current_school_id) {
    // Should not happen, but fallback
    $where_conditions[] = "n.visibility = 'public'";
    $where_conditions[] = "n.status = 'active'";
} else {
    // Guests only see public notifications
    $where_conditions[] = "n.visibility = 'public'";
    $where_conditions[] = "n.status = 'active'";
}

// Build WHERE clause
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get notifications with author info
$sql = "SELECT n.*, 
       CONCAT(a.first_name, ' ', a.last_name) as author_name,
       a.profile_image as author_image,
       GROUP_CONCAT(DISTINCT ar.role_name) as author_roles,
       (SELECT COUNT(*) FROM notification_views nv WHERE nv.notification_id = n.id AND nv.school_id = n.school_id) as total_views,
       (CASE 
          WHEN ? = 1 THEN 
            (SELECT COUNT(*) FROM notification_views nv2 
             WHERE nv2.notification_id = n.id AND nv2.viewer_id = ? AND nv2.school_id = n.school_id)
          ELSE 0 
        END) as is_viewed
       FROM notifications n
       JOIN admins a ON n.admin_id = a.id
       LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
       LEFT JOIN admin_roles ar ON ara.role_id = ar.id
       $where_clause
       GROUP BY n.id
       ORDER BY n.is_starred DESC, 
                FIELD(n.priority, 'important', 'starred', 'normal'),
                n.created_at DESC";

// Prepare and execute
$is_logged_int = $is_logged_in ? 1 : 0;
if (!empty($params)) {
    // Add the two extra params for the CASE statement
    $all_params = array_merge([$is_logged_int, $admin_id], $params);
    $all_types = "ii" . $param_types;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($all_types, ...$all_params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // No school_id filter? Should not happen for logged in users
    $result = mysqli_query($conn, $sql);
}

$notifications = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
}

// Get unread count for logged-in users
$unread_count = 0;
if ($is_logged_in && $current_school_id) {
    // Get last check time
    $last_check_sql = "SELECT last_notification_check FROM admins WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($last_check_sql);
    $stmt->bind_param("ii", $admin_id, $current_school_id);
    $stmt->execute();
    $last_check_result = $stmt->get_result();
    $last_check_row = $last_check_result->fetch_assoc();
    $last_check = $last_check_row ? $last_check_row['last_notification_check'] : null;
    
    if ($last_check) {
        $count_sql = "SELECT COUNT(DISTINCT n.id) as count 
                     FROM notifications n
                     LEFT JOIN notification_views nv ON n.id = nv.notification_id AND nv.viewer_id = ? AND nv.school_id = n.school_id
                     WHERE n.status = 'active' 
                     AND n.school_id = ?
                     AND n.created_at > ?
                     AND nv.id IS NULL";
        
        // If not admin, filter by visibility
        if (!$is_admin) {
            $count_sql .= " AND (n.visibility = 'public' OR n.admin_id = ?)";
            $stmt = $conn->prepare($count_sql);
            $stmt->bind_param("iisi", $admin_id, $current_school_id, $last_check, $admin_id);
        } else {
            $stmt = $conn->prepare($count_sql);
            $stmt->bind_param("iis", $admin_id, $current_school_id, $last_check);
        }
        
        $stmt->execute();
        $count_result = $stmt->get_result();
        if ($count_result) {
            $count_row = $count_result->fetch_assoc();
            $unread_count = $count_row ? $count_row['count'] : 0;
        }
    }
}

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

// Function to get file extension
function getFileExtension($filename) {
    return pathinfo($filename, PATHINFO_EXTENSION);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - School System</title>
    
    <!-- Include header -->
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>
    
    <!-- SweetAlert2 for confirmation dialogs -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .notification-card {
            border-left: 4px solid #3B9DB3;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            position: relative;
        }
        
        .notification-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .notification-card.starred {
            border-left-color: #FFD700;
            background: linear-gradient(135deg, #fff9e6, #ffffff);
        }
        
        .notification-card.important {
            border-left-color: #dc3545;
        }
        
        .notification-card.private {
            border: 2px solid #dc3545;
            background: linear-gradient(135deg, #fff5f5, #ffffff);
        }
        
        .notification-card.unread {
            border-left-color: #3B9DB3;
            background: linear-gradient(135deg, #e8f4f8, #ffffff);
        }
        
        .file-preview {
            max-width: 100%;
            margin: 15px 0;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
            padding: 15px;
            border: 1px solid #e0e0e0;
        }
        
        .file-preview img {
            max-width: 100%;
            max-height: 300px;
            object-fit: contain;
            cursor: pointer;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .file-preview video, .file-preview audio {
            width: 100%;
            border-radius: 6px;
            background: #000;
        }
        
        .file-icon-large {
            font-size: 3rem;
            color: #3B9DB3;
            margin: 10px 0;
        }
        
        .author-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .author-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3B9DB3;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .author-details {
            flex: 1;
        }
        
        .author-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
            font-size: 1.05rem;
        }
        
        .author-role {
            font-size: 0.85rem;
            color: #666;
        }
        
        .notification-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            opacity: 0;
            transition: opacity 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            padding: 5px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .notification-card:hover .notification-actions {
            opacity: 1;
        }
        
        .star-btn {
            color: #ccc;
            cursor: pointer;
            transition: all 0.3s ease;
            background: none;
            border: none;
            font-size: 1.2rem;
            padding: 5px;
            border-radius: 4px;
        }
        
        .star-btn:hover {
            background: rgba(255, 215, 0, 0.1);
        }
        
        .star-btn.active {
            color: #FFD700;
            background: rgba(255, 215, 0, 0.1);
        }
        
        .upload-area {
            border: 2px dashed #3B9DB3;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(59, 157, 179, 0.05);
        }
        
        .upload-area:hover {
            background: rgba(59, 157, 179, 0.1);
            border-color: #2d7c8f;
        }
        
        .upload-area.dragover {
            background: rgba(59, 157, 179, 0.2);
            border-color: #1a5c6d;
        }
        
        .file-info-card {
            background: #e8f4f8;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            border-left: 4px solid #3B9DB3;
        }
        
        .notification-date {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .views-count {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .badge-new {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }
        
        .priority-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin-right: 5px;
        }
        
        .priority-normal { 
            background: linear-gradient(135deg, #e8f4f8, #d1e7f0);
            color: #2d7c8f;
            border: 1px solid #b8d9e6;
        }
        
        .priority-important { 
            background: linear-gradient(135deg, #f8e8e8, #f0d1d1);
            color: #dc3545;
            border: 1px solid #e6b8b8;
        }
        
        .priority-starred { 
            background: linear-gradient(135deg, #f8f4e8, #f0e9d1);
            color: #b38f00;
            border: 1px solid #e6dbb8;
        }
        
        .filter-buttons {
            margin-bottom: 25px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-buttons .btn-group {
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .filter-buttons .btn {
            border-radius: 20px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .filter-buttons .btn.active {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(59, 157, 179, 0.2);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #3B9DB3;
            margin-bottom: 20px;
            opacity: 0.7;
        }
        
        .empty-state h4 {
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .empty-state p {
            color: #666;
            max-width: 500px;
            margin: 0 auto 25px;
            line-height: 1.6;
        }
        
        .modal-xl-custom {
            max-width: 800px;
        }
        
        .file-type-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #f0f0f0;
            color: #666;
            display: inline-block;
            margin-left: 5px;
        }
        
        .notification-content {
            padding: 5px;
        }
        
        .notification-title {
            font-size: 1.25rem;
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
        
        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
        }
        
        .notification-tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .unread-dot {
            width: 10px;
            height: 10px;
            background: #3B9DB3;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .notification-actions {
                opacity: 1;
                position: relative;
                top: 0;
                right: 0;
                margin-top: 10px;
                background: transparent;
                box-shadow: none;
                padding: 0;
            }
            
            .upload-area {
                padding: 1.5rem;
            }
            
            .author-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .notification-meta {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .filter-buttons .btn {
                padding: 6px 12px;
                font-size: 0.9rem;
            }
        }
        
        .download-btn {
            transition: all 0.3s ease;
        }
        
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .card-footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="page-title">
                    <i class="fas fa-bell me-2"></i>Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger ms-2"><?php echo $unread_count; ?> new</span>
                    <?php endif; ?>
                </h2>
                
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" id="actionDropdown" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog me-2"></i>Actions
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionDropdown">
                        <?php if ($is_logged_in): ?>
                            <li>
                                <a class="dropdown-item" href="new_notification.php">
                                    <i class="fas fa-plus me-2 text-primary"></i>New Notification
                                </a>
                            </li>

                            <?php if ($is_admin && !empty($notifications)): ?>
                                <li>
                                    <button class="dropdown-item text-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteAllModal">
                                        <i class="fas fa-trash me-2"></i>Delete All
                                    </button>
                                </li>
                            <?php endif; ?>

                            <?php if ($unread_count > 0): ?>
                                <li>
                                    <a class="dropdown-item text-success" 
                                       href="notifications.php?action=mark_all_read">
                                        <i class="fas fa-check-double me-2"></i>Mark All Read
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li>
                                <a class="dropdown-item" href="../login.php">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login to Post Notifications
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Filter Buttons -->
            <div class="filter-buttons">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 text-primary"><i class="fas fa-filter me-2"></i>Filter Notifications</h6>
                    <small class="text-muted"><?php echo count($notifications); ?> total notifications</small>
                </div>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary active" onclick="filterNotifications('all')">
                        <i class="fas fa-th-large me-1"></i>All
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="filterNotifications('starred')">
                        <i class="fas fa-star me-1"></i>Starred
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="filterNotifications('important')">
                        <i class="fas fa-exclamation-circle me-1"></i>Important
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="filterNotifications('unread')">
                        <i class="fas fa-envelope me-1"></i>Unread
                    </button>
                    <?php if ($is_logged_in): ?>
                        <button type="button" class="btn btn-outline-primary" onclick="filterNotifications('private')">
                            <i class="fas fa-lock me-1"></i>Private
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notifications Grid -->
            <div class="row" id="notificationsContainer">
                <?php if (empty($notifications)): ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h4>No notifications yet</h4>
                            <p class="mb-4">When notifications are posted, they will appear here.</p>
                            <?php if (!$is_logged_in): ?>
                                <p class="mb-4">Log in to see more notifications or post new ones.</p>
                                <a href="../login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login Now
                                </a>
                            <?php else: ?>
                                <a href="new_notification.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Create Your First Notification
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                    <?php 
                        $is_new = false;
                        if ($is_logged_in) {
                            $is_new = ($notification['is_viewed'] == 0);
                        }
                        
                        // Get relative file path for display
                        $file_url = '';
                        if ($notification['file_path']) {
                            $file_url = str_replace('../../', '', $notification['file_path']);
                        }
                    ?>
                    <div class="col-lg-6 col-xl-4 mb-4 notification-item" 
                         data-priority="<?php echo $notification['priority']; ?>"
                         data-starred="<?php echo $notification['is_starred']; ?>"
                         data-visibility="<?php echo $notification['visibility']; ?>"
                         data-unread="<?php echo $is_new ? 'true' : 'false'; ?>">
                        <div class="notification-card card h-100 
                            <?php echo $notification['is_starred'] ? 'starred' : ''; ?> 
                            <?php echo $notification['priority'] == 'important' ? 'important' : ''; ?> 
                            <?php echo $notification['visibility'] == 'private' ? 'private' : ''; ?>
                            <?php echo $is_new ? 'unread' : ''; ?>"
                            data-id="<?php echo $notification['id']; ?>"
                            <?php if ($is_new && $is_logged_in): ?>
                                onclick="markAsRead(<?php echo $notification['id']; ?>, this)"
                            <?php endif; ?>>
                            
                            <div class="card-body position-relative">
                                <!-- Header with author info -->
                                <div class="author-info">
                                    <?php if ($notification['author_image'] && file_exists($notification['author_image'])): ?>
                                        <img src="<?php echo $notification['author_image']; ?>" alt="Author" class="author-avatar">
                                    <?php else: ?>
                                        <div class="author-avatar bg-primary text-white d-flex align-items-center justify-content-center" style="font-size: 1.2rem; font-weight: bold;">
                                            <?php echo substr($notification['author_name'], 0, 1); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="author-details">
                                        <div class="author-name">
                                            <?php echo htmlspecialchars($notification['author_name']); ?>
                                            <?php if ($is_new): ?>
                                                <span class="badge-new">
                                                    <span class="unread-dot"></span>
                                                    NEW
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="author-role">
                                            <?php 
                                            if ($notification['author_roles']) {
                                                $roles = explode(',', $notification['author_roles']);
                                                echo htmlspecialchars(trim($roles[0]));
                                                echo ' • ';
                                            }
                                            ?>
                                            <span class="priority-badge priority-<?php echo $notification['priority']; ?>">
                                                <?php echo $notification['priority']; ?>
                                            </span>
                                            <?php if ($notification['visibility'] == 'private'): ?>
                                                <span class="file-type-badge">
                                                    <i class="fas fa-lock me-1"></i>Private
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="notification-actions">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Star button -->
                                        <button class="btn btn-outline-warning star-btn <?php echo $notification['is_starred'] ? 'active' : ''; ?>"
                                                onclick="toggleStar(<?php echo $notification['id']; ?>, event)" 
                                                title="<?php echo $notification['is_starred'] ? 'Unstar' : 'Star'; ?>">
                                            <i class="fas fa-star"></i>
                                        </button>
                                        
                                        <?php if ($is_logged_in && ($notification['admin_id'] == $admin_id || $is_admin)): ?>
                                            <!-- Edit button -->
                                            <a href="edit_notification.php?id=<?php echo $notification['id']; ?>" 
                                               class="btn btn-outline-primary"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <!-- Delete button -->
                                            <button class="btn btn-outline-danger"
                                                    onclick="deleteNotification(<?php echo $notification['id']; ?>, '<?php echo htmlspecialchars(addslashes($notification['title'])); ?>', event)"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Notification Content -->
                                <div class="notification-content">
                                    <!-- Title -->
                                    <h5 class="notification-title">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </h5>
                                    
                                    <!-- Description -->
                                    <?php if (!empty($notification['description'])): ?>
                                        <div class="notification-description">
                                            <?php echo nl2br(htmlspecialchars($notification['description'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- File Preview -->
                                    <?php if ($notification['file_path'] && file_exists($notification['file_path'])): ?>
                                        <div class="file-preview mb-3">
                                            <?php 
                                            $file_ext = getFileExtension($notification['file_name']);
                                            
                                            if ($notification['file_type'] == 'image'): ?>
                                                <img src="<?php echo $file_url; ?>" 
                                                     alt="<?php echo htmlspecialchars($notification['title']); ?>"
                                                     class="img-fluid rounded"
                                                     onclick="previewImage('<?php echo $file_url; ?>')"
                                                     style="cursor: pointer;">
                                            
                                            <?php elseif ($notification['file_type'] == 'video'): ?>
                                                <video controls class="w-100 rounded">
                                                    <source src="<?php echo $file_url; ?>" type="video/mp4">
                                                    Your browser does not support the video tag.
                                                </video>
                                            
                                            <?php elseif ($notification['file_type'] == 'audio'): ?>
                                                <div class="text-center">
                                                    <i class="fas fa-file-audio file-icon-large"></i>
                                                    <audio controls class="w-100 mt-2">
                                                        <source src="<?php echo $file_url; ?>" type="audio/mpeg">
                                                        Your browser does not support the audio element.
                                                    </audio>
                                                </div>
                                            
                                            <?php else: ?>
                                                <div class="text-center">
                                                    <i class="fas <?php echo getFileIcon($notification['file_type']); ?> file-icon-large"></i>
                                                    <div class="mt-3">
                                                        <a href="<?php echo $file_url; ?>" 
                                                           target="_blank"
                                                           class="btn btn-sm btn-primary download-btn">
                                                            <i class="fas fa-download me-2"></i>
                                                            Download File
                                                        </a>
                                                        <div class="mt-2 text-muted">
                                                            <small>
                                                                <strong><?php echo strtoupper($file_ext); ?></strong> file • 
                                                                <?php echo formatFileSize($notification['file_size']); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Footer Meta -->
                                    <div class="notification-meta">
                                        <div class="notification-date">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo date('M d, Y', strtotime($notification['created_at'])); ?>
                                            <small class="text-muted ms-1">
                                                at <?php echo date('h:i A', strtotime($notification['created_at'])); ?>
                                            </small>
                                        </div>
                                        
                                        <div class="views-count">
                                            <i class="fas fa-eye me-1"></i>
                                            <?php echo $notification['total_views']; ?> views
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Card Footer -->
                            <div class="card-footer-actions">
                                <div class="notification-tags">
                                    <?php if ($notification['visibility'] == 'private'): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-lock me-1"></i>Private
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-globe me-1"></i>Public
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($notification['file_path']): ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-paperclip me-1"></i>Attachment
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($is_new && $is_logged_in): ?>
                                    <span class="text-primary small">
                                        <i class="fas fa-circle me-1"></i>Click to mark as read
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination (if many notifications) -->
            <?php if (count($notifications) > 12): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Notifications pagination">
                        <ul class="pagination">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1">Previous</a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete All Modal -->
    <div class="modal fade" id="deleteAllModal" tabindex="-1" aria-labelledby="deleteAllModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteAllModalLabel">
                        <i class="fas fa-trash me-2"></i>Delete All Notifications
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5 class="mb-3">Are you sure?</h5>
                    <p>This will permanently delete ALL notifications and their attachments. This action cannot be undone.</p>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Warning:</strong> This will delete <?php echo count($notifications); ?> notifications
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="notifications.php?action=delete_all" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete All Notifications
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img src="" id="previewImage" class="img-fluid rounded shadow-lg">
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle star
        function toggleStar(notificationId, event) {
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }
            
            // Optimistic UI update
            const starBtn = event.target.closest('.star-btn');
            if (starBtn) {
                starBtn.classList.toggle('active');
                starBtn.title = starBtn.classList.contains('active') ? 'Unstar' : 'Star';
                
                // Update card class
                const card = starBtn.closest('.notification-card');
                if (card) {
                    card.classList.toggle('starred');
                }
            }
            
            // Send request
            fetch(`notifications.php?action=star&id=${notificationId}`)
                .catch(error => {
                    console.error('Error starring notification:', error);
                    // Revert UI if error
                    if (starBtn) {
                        starBtn.classList.toggle('active');
                        starBtn.title = starBtn.classList.contains('active') ? 'Unstar' : 'Star';
                    }
                });
        }
        
        // Mark as read
        function markAsRead(notificationId, element) {
            // Remove unread styling immediately
            const card = element.closest('.notification-card');
            if (card) {
                card.classList.remove('unread');
                card.style.cursor = 'default';
                
                // Remove unread dot and badge
                const unreadBadge = card.querySelector('.badge-new');
                if (unreadBadge) {
                    unreadBadge.remove();
                }
                
                // Remove click to mark as read text
                const markAsReadText = card.querySelector('.text-primary.small');
                if (markAsReadText) {
                    markAsReadText.remove();
                }
                
                // Update data attribute
                const item = card.closest('.notification-item');
                if (item) {
                    item.setAttribute('data-unread', 'false');
                }
                
                // Update unread count in header
                const unreadCountBadge = document.querySelector('.page-title .badge');
                if (unreadCountBadge) {
                    let currentCount = parseInt(unreadCountBadge.textContent);
                    if (currentCount > 1) {
                        unreadCountBadge.textContent = currentCount - 1;
                    } else {
                        unreadCountBadge.remove();
                    }
                }
            }
            
            // Send request
            fetch(`notifications.php?action=mark_read&id=${notificationId}`)
                .catch(error => {
                    console.error('Error marking as read:', error);
                });
        }
        
        // Delete notification
        function deleteNotification(notificationId, title, event) {
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }
            
            Swal.fire({
                title: 'Delete Notification?',
                html: `Are you sure you want to delete "<strong>${title}</strong>"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(`notifications.php?action=delete&id=${notificationId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Request failed: ${error}`);
                        });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'Notification has been deleted.',
                        confirmButtonColor: '#3B9DB3'
                    }).then(() => {
                        location.reload();
                    });
                }
            });
        }
        
        // Filter notifications
        function filterNotifications(filter) {
            const items = document.querySelectorAll('.notification-item');
            let visibleCount = 0;
            
            items.forEach(item => {
                let show = false;
                
                switch(filter) {
                    case 'all':
                        show = true;
                        break;
                    case 'starred':
                        show = item.getAttribute('data-starred') === '1';
                        break;
                    case 'important':
                        show = item.getAttribute('data-priority') === 'important';
                        break;
                    case 'unread':
                        show = item.getAttribute('data-unread') === 'true';
                        break;
                    case 'private':
                        show = item.getAttribute('data-visibility') === 'private';
                        break;
                }
                
                if (show) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Update filter button active state
            document.querySelectorAll('.filter-buttons .btn').forEach(btn => {
                btn.classList.remove('active');
            });
            if (event && event.target) {
                event.target.classList.add('active');
            }
            
            // Show empty state if no notifications match filter
            const container = document.getElementById('notificationsContainer');
            const existingEmptyState = container.querySelector('.empty-state-filtered');
            
            if (visibleCount === 0 && filter !== 'all') {
                if (!existingEmptyState) {
                    const emptyState = document.createElement('div');
                    emptyState.className = 'col-12 empty-state empty-state-filtered';
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
        function previewImage(src) {
            document.getElementById('previewImage').src = src;
            const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
            modal.show();
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Update notification badge on sidebar
            <?php if ($is_logged_in && $unread_count > 0): ?>
            const notificationLinks = document.querySelectorAll('a[href*="notifications.php"]');
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
            
            // Auto-mark as read when notification is viewed (for non-interactive viewing)
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const notificationItem = entry.target;
                        const notificationId = notificationItem.querySelector('.notification-card')?.getAttribute('data-id');
                        const isUnread = notificationItem.getAttribute('data-unread') === 'true';
                        
                        if (isUnread && notificationId) {
                            // Small delay to ensure user is actually viewing
                            setTimeout(() => {
                                markAsRead(notificationId, notificationItem);
                            }, 2000);
                        }
                    }
                });
            }, { threshold: 0.5 });
            
            // Observe all notification items
            document.querySelectorAll('.notification-item').forEach(item => {
                observer.observe(item);
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N for new notification
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'new_notification.php';
            }
            
            // Escape to clear filters
            if (e.key === 'Escape') {
                filterNotifications('all');
            }
        });
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>