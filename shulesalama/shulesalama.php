<?php 
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../controller/login.php");
    exit();
}

// Get admin details
$admin_id = $_SESSION['admin_id'];
$admin_sql = "SELECT a.*, GROUP_CONCAT(ar.role_name) as roles 
              FROM admins a 
              LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id 
              LEFT JOIN admin_roles ar ON ara.role_id = ar.id 
              WHERE a.id = $admin_id 
              GROUP BY a.id";
$admin_result = mysqli_query($conn, $admin_sql);
$admin = mysqli_fetch_assoc($admin_result);

// Function to get correct profile image path
function getProfileImagePath($image_path) {
    if (empty($image_path)) {
        return null;
    }
    
    // If path already includes uploads/, use as is
    if (strpos($image_path, 'uploads/') !== false) {
        return '../' . $image_path;
    }
    
    // Otherwise, assume it's in uploads/profiles/
    return '../uploads/profiles/' . basename($image_path);
}

// Check if user has Shule Salama or Head Master role
$roles = $admin['roles'] ? explode(',', $admin['roles']) : [];
$is_shule_salama = in_array('Shule Salama', $roles) || in_array('Head Master', $roles);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_shule_salama && isset($_POST['title'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $visibility = mysqli_real_escape_string($conn, $_POST['visibility']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    
    // Initialize file variables
    $file_path = null;
    $file_type = null;
    $file_name = null;
    $file_size = null;
    
    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $file = $_FILES['attachment'];
        $file_name = basename($file['name']);
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed file types
        $allowed_image = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $allowed_document = ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt'];
        $allowed_audio = ['mp3', 'wav', 'ogg', 'm4a'];
        $allowed_video = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'];
        $allowed_archive = ['zip', 'rar', '7z', 'tar', 'gz'];
        
        // Determine file type
        if (in_array($file_ext, $allowed_image)) {
            $file_type = 'image';
        } elseif (in_array($file_ext, $allowed_document)) {
            $file_type = 'document';
        } elseif (in_array($file_ext, $allowed_audio)) {
            $file_type = 'audio';
        } elseif (in_array($file_ext, $allowed_video)) {
            $file_type = 'video';
        } elseif (in_array($file_ext, $allowed_archive)) {
            $file_type = 'archive';
        } else {
            $file_type = 'other';
        }
        
        // Create unique filename
        $new_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
        $upload_dir = '../uploads/shule_salama/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_path = $upload_dir . $new_filename;
        
        // Move uploaded file (max 20MB)
        if ($file_size <= 20 * 1024 * 1024) { // 20MB limit
            if (move_uploaded_file($file_tmp, $file_path)) {
                $file_path = 'uploads/shule_salama/' . $new_filename;
            } else {
                $error = "Failed to upload file.";
                $file_path = null;
                $file_type = null;
                $file_name = null;
                $file_size = null;
            }
        } else {
            $error = "File size exceeds 20MB limit.";
        }
    }
    
    // Insert into shule_salama_posts table
    $insert_sql = "INSERT INTO shule_salama_posts 
                  (admin_id, title, description, file_path, file_type, file_name, file_size, visibility, priority, status) 
                  VALUES ('$admin_id', '$title', '$description', " . 
                  ($file_path ? "'$file_path'" : "NULL") . ", " .
                  ($file_type ? "'$file_type'" : "NULL") . ", " .
                  ($file_name ? "'$file_name'" : "NULL") . ", " .
                  ($file_size ? "'$file_size'" : "NULL") . ", 
                  '$visibility', '$priority', 'active')";
    
    if (mysqli_query($conn, $insert_sql)) {
        $post_id = mysqli_insert_id($conn);
        $_SESSION['success'] = "Safety announcement posted successfully!";
        
        // Log the action
        $log_sql = "INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent) 
                    VALUES ('$admin_id', 'Shule Salama Post', 'Posted: $title (ID: $post_id)', '" . $_SERVER['REMOTE_ADDR'] . "', '" . $_SERVER['HTTP_USER_AGENT'] . "')";
        mysqli_query($conn, $log_sql);
        
        header("Location: shulesalama.php?success=true");
        exit();
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Handle delete actions
if (isset($_GET['action']) && $is_shule_salama) {
    $post_id = mysqli_real_escape_string($conn, $_GET['id'] ?? 0);
    
    switch ($_GET['action']) {
        case 'delete':
            // Check if user can delete (owner or admin with appropriate role)
            $check_sql = "SELECT * FROM shule_salama_posts WHERE id = $post_id";
            $check_result = mysqli_query($conn, $check_sql);
            $post = mysqli_fetch_assoc($check_result);
            
            if ($post && ($post['admin_id'] == $admin_id || $is_shule_salama)) {
                // Delete associated file
                if ($post['file_path'] && file_exists('../' . $post['file_path'])) {
                    unlink('../' . $post['file_path']);
                }
                
                // Delete post
                $delete_sql = "DELETE FROM shule_salama_posts WHERE id = $post_id";
                if (mysqli_query($conn, $delete_sql)) {
                    $_SESSION['success'] = "Safety announcement deleted successfully!";
                    
                    // Log the action
                    $log_sql = "INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent) 
                                VALUES ('$admin_id', 'Shule Salama Delete', 'Deleted post ID: $post_id', '" . $_SERVER['REMOTE_ADDR'] . "', '" . $_SERVER['HTTP_USER_AGENT'] . "')";
                    mysqli_query($conn, $log_sql);
                } else {
                    $_SESSION['error'] = "Error deleting announcement: " . mysqli_error($conn);
                }
            } else {
                $_SESSION['error'] = "You don't have permission to delete this announcement.";
            }
            break;
            
        case 'delete_all':
            if ($is_shule_salama) {
                // Get all posts to delete their files
                $sql = "SELECT file_path FROM shule_salama_posts WHERE file_path IS NOT NULL";
                $result = mysqli_query($conn, $sql);
                while ($row = mysqli_fetch_assoc($result)) {
                    if (file_exists('../' . $row['file_path'])) {
                        unlink('../' . $row['file_path']);
                    }
                }
                
                // Delete all posts
                $sql = "DELETE FROM shule_salama_posts";
                if (mysqli_query($conn, $sql)) {
                    $_SESSION['success'] = "All safety announcements deleted successfully!";
                    
                    // Log the action
                    $log_sql = "INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent) 
                                VALUES ('$admin_id', 'Shule Salama Delete All', 'Deleted all posts', '" . $_SERVER['REMOTE_ADDR'] . "', '" . $_SERVER['HTTP_USER_AGENT'] . "')";
                    mysqli_query($conn, $log_sql);
                } else {
                    $_SESSION['error'] = "Error deleting all announcements: " . mysqli_error($conn);
                }
            }
            break;
            
        case 'archive':
            $sql = "UPDATE shule_salama_posts SET status = 'archived' WHERE id = $post_id AND admin_id = $admin_id";
            mysqli_query($conn, $sql);
            break;
    }
    
    if (isset($_SESSION['success']) || isset($_SESSION['error'])) {
        header("Location: shulesalama.php");
        exit();
    }
}

// Get all Shule Salama posts (latest first)
$posts_sql = "SELECT sp.*, CONCAT(a.first_name, ' ', a.last_name) as author_name, 
              a.profile_image as author_image 
              FROM shule_salama_posts sp 
              JOIN admins a ON sp.admin_id = a.id 
              WHERE sp.status = 'active' 
              ORDER BY 
                CASE sp.priority 
                    WHEN 'emergency' THEN 1
                    WHEN 'critical' THEN 2
                    WHEN 'important' THEN 3
                    ELSE 4
                END, 
                sp.created_at DESC";
$posts_result = mysqli_query($conn, $posts_sql);
$total_posts = mysqli_num_rows($posts_result);

// Get emergency posts count
$emergency_sql = "SELECT COUNT(*) as count FROM shule_salama_posts WHERE status = 'active' AND priority IN ('emergency', 'critical')";
$emergency_result = mysqli_query($conn, $emergency_sql);
$emergency_count = mysqli_fetch_assoc($emergency_result)['count'];

// Get recent posts (last 7 days)
$recent_sql = "SELECT COUNT(*) as count FROM shule_salama_posts WHERE status = 'active' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$recent_result = mysqli_query($conn, $recent_sql);
$recent_count = mysqli_fetch_assoc($recent_result)['count'];
?>

<?php include '../controller/header.php'; ?> 
<?php include '../controller/sidebar.php'; ?> 

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header with colored background -->
        <div class="page-header rounded-3 p-4 mb-4 shadow-sm">
            <div class="row align-items-center">
                <div class="col">
                    <div class="d-flex align-items-center">
                        <div class="icon-wrapper bg-white p-3 rounded-circle me-3 shadow">
                            <i class="fas fa-shield-alt fa-2x" style="color: #3B9DB3;"></i>
                        </div>
                        <div>
                            <h1 class="h3 mb-1 fw-bold text-white">Shule Salama</h1>
                            <p class="text-light mb-0 opacity-75">School Safety & Security Management Portal</p>
                        </div>
                    </div>
                </div>
                <div class="col-auto">
                    <?php if ($is_shule_salama): ?>
                        <button class="btn btn-light btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                            <i class="fas fa-plus-circle me-2"></i>New Announcement
                        </button>
                    <?php else: ?>
                        <span class="badge bg-light text-dark fs-6 px-4 py-2 shadow-sm">
                            <i class="fas fa-eye me-1"></i>View Only Access
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                <i class="fas fa-check-circle fa-lg me-3"></i>
                <div class="flex-grow-1">
                    <strong>Success!</strong> <?php echo $_SESSION['success']; ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                <i class="fas fa-exclamation-circle fa-lg me-3"></i>
                <div class="flex-grow-1">
                    <strong>Error!</strong> <?php echo $_SESSION['error']; ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Stats Dashboard -->
        <div class="row mb-4 g-4">
            <div class="col-xl-3 col-md-6">
                <div class="card card-stats border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-1 text-uppercase small">Total Announcements</h6>
                                <h2 class="fw-bold mb-0"><?php echo $total_posts; ?></h2>
                                <span class="text-muted small">Active safety posts</span>
                            </div>
                            <div class="icon-wrapper bg-primary bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-bullhorn fa-2x text-primary"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-primary bg-opacity-25 text-primary px-3 py-2 rounded-pill">
                                <i class="fas fa-chart-line me-1"></i>Active
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-stats border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-1 text-uppercase small">Active Alerts</h6>
                                <h2 class="fw-bold mb-0"><?php echo $emergency_count; ?></h2>
                                <span class="text-muted small">Emergency & Critical</span>
                            </div>
                            <div class="icon-wrapper bg-warning bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-warning bg-opacity-25 text-warning px-3 py-2 rounded-pill">
                                <i class="fas fa-bell me-1"></i>High Priority
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-stats border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-1 text-uppercase small">Weekly Posts</h6>
                                <h2 class="fw-bold mb-0"><?php echo $recent_count; ?></h2>
                                <span class="text-muted small">Last 7 days</span>
                            </div>
                            <div class="icon-wrapper bg-info bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-calendar-week fa-2x text-info"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-info bg-opacity-25 text-info px-3 py-2 rounded-pill">
                                <i class="fas fa-clock me-1"></i>Recent
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-stats border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-1 text-uppercase small">Your Posts</h6>
                                <h2 class="fw-bold mb-0">
                                    <?php 
                                    $user_sql = "SELECT COUNT(*) as count FROM shule_salama_posts 
                                                WHERE status = 'active' AND admin_id = $admin_id";
                                    $user_result = mysqli_query($conn, $user_sql);
                                    echo mysqli_fetch_assoc($user_result)['count'];
                                    ?>
                                </h2>
                                <span class="text-muted small">Personal contributions</span>
                            </div>
                            <div class="icon-wrapper bg-success bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-user-check fa-2x text-success"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-25 text-success px-3 py-2 rounded-pill">
                                <i class="fas fa-user me-1"></i>Your Activity
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcements Section -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h3 class="mb-2 fw-bold">
                            <i class="fas fa-list-alt me-3" style="color: #3B9DB3;"></i>Safety Announcements
                        </h3>
                        <p class="text-muted mb-0">Showing all active safety announcements (latest first)</p>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Search -->
                        <div class="search-wrapper position-relative" style="width: 250px;">
                            <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                            <input type="text" class="form-control form-control-lg ps-5" placeholder="Search announcements..." id="searchPosts">
                        </div>
                        
                        <!-- Filter -->
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-lg dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-filter me-2"></i> Filter By
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><h6 class="dropdown-header">Filter by Priority</h6></li>
                                <li><a class="dropdown-item" href="#" data-filter="all"><i class="fas fa-layer-group me-2"></i>All Posts</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-filter="emergency"><span class="badge bg-danger me-2">🔴</span>Emergency</a></li>
                                <li><a class="dropdown-item" href="#" data-filter="critical"><span class="badge bg-warning me-2">🟠</span>Critical</a></li>
                                <li><a class="dropdown-item" href="#" data-filter="important"><span class="badge bg-info me-2">🟡</span>Important</a></li>
                                <li><a class="dropdown-item" href="#" data-filter="normal"><span class="badge bg-success me-2">🟢</span>Normal</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-filter="yours"><i class="fas fa-user me-2"></i>Your Posts</a></li>
                            </ul>
                        </div>
                        
                        <?php if ($is_shule_salama && $total_posts > 0): ?>
                            <button type="button" class="btn btn-outline-danger btn-lg" data-bs-toggle="modal" data-bs-target="#deleteAllModal">
                                <i class="fas fa-trash-alt me-2"></i> Delete All
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <?php if (mysqli_num_rows($posts_result) > 0): ?>
                    <div id="announcementsList">
                        <?php while ($post = mysqli_fetch_assoc($posts_result)): 
                            // Determine styling based on priority
                            $priority_class = '';
                            $priority_icon = '';
                            $border_color = '';
                            $badge_class = '';
                            
                            switch($post['priority']) {
                                case 'emergency': 
                                    $priority_class = 'bg-danger text-white';
                                    $priority_icon = 'fas fa-exclamation-circle';
                                    $border_color = 'border-danger border-start border-4';
                                    $badge_class = 'bg-danger';
                                    break;
                                case 'critical': 
                                    $priority_class = 'bg-warning text-dark';
                                    $priority_icon = 'fas fa-exclamation-triangle';
                                    $border_color = 'border-warning border-start border-4';
                                    $badge_class = 'bg-warning';
                                    break;
                                case 'important': 
                                    $priority_class = 'bg-info text-white';
                                    $priority_icon = 'fas fa-info-circle';
                                    $border_color = 'border-info border-start border-4';
                                    $badge_class = 'bg-info';
                                    break;
                                default: 
                                    $priority_class = 'bg-secondary text-white';
                                    $priority_icon = 'fas fa-info';
                                    $border_color = 'border-secondary border-start border-4';
                                    $badge_class = 'bg-secondary';
                            }
                            
                            // Check if user can edit/delete this post
                            $can_edit_delete = ($post['admin_id'] == $admin_id || $is_shule_salama);
                            
                            // Get author profile image path
                            $author_profile_image = getProfileImagePath($post['author_image']);
                            
                            // Get file icon and color
                            $file_icon = '';
                            $file_color = '';
                            $file_bg_color = '';
                            $full_file_path = '';
                            if ($post['file_path']) {
                                $full_file_path = '../' . $post['file_path'];
                                switch($post['file_type']) {
                                    case 'image': 
                                        $file_icon = 'fas fa-image';
                                        $file_color = 'text-primary';
                                        $file_bg_color = 'bg-primary bg-opacity-10';
                                        break;
                                    case 'document': 
                                        $file_icon = 'fas fa-file-pdf';
                                        $file_color = 'text-danger';
                                        $file_bg_color = 'bg-danger bg-opacity-10';
                                        break;
                                    case 'audio': 
                                        $file_icon = 'fas fa-file-audio';
                                        $file_color = 'text-success';
                                        $file_bg_color = 'bg-success bg-opacity-10';
                                        break;
                                    case 'video': 
                                        $file_icon = 'fas fa-file-video';
                                        $file_color = 'text-warning';
                                        $file_bg_color = 'bg-warning bg-opacity-10';
                                        break;
                                    case 'archive': 
                                        $file_icon = 'fas fa-file-archive';
                                        $file_color = 'text-secondary';
                                        $file_bg_color = 'bg-secondary bg-opacity-10';
                                        break;
                                    default: 
                                        $file_icon = 'fas fa-file';
                                        $file_color = 'text-dark';
                                        $file_bg_color = 'bg-dark bg-opacity-10';
                                }
                            }
                            
                            // Format date
                            $post_date = date('M d, Y', strtotime($post['created_at']));
                            $post_time = date('h:i A', strtotime($post['created_at']));
                            
                            // Get time ago with proper format
                            $time_ago = get_time_ago($post['created_at']);
                            
                            // Get visibility badge
                            $visibility_badge = '';
                            switch($post['visibility']) {
                                case 'staff_only':
                                    $visibility_badge = '<span class="badge bg-dark"><i class="fas fa-user-tie me-1"></i>Staff Only</span>';
                                    break;
                                case 'students_only':
                                    $visibility_badge = '<span class="badge bg-dark"><i class="fas fa-graduation-cap me-1"></i>Students Only</span>';
                                    break;
                                default:
                                    $visibility_badge = '<span class="badge bg-success">Public</span>';
                            }
                        ?>
                        <div class="announcement-item <?php echo $border_color; ?> bg-white mb-4 mx-4 mt-4 rounded-3 shadow-sm" 
                             data-id="<?php echo $post['id']; ?>" 
                             data-priority="<?php echo $post['priority']; ?>"
                             data-author="<?php echo $post['admin_id'] == $admin_id ? 'yours' : 'others'; ?>">
                            <div class="p-4">
                                <!-- Header -->
                                <div class="d-flex justify-content-between align-items-start mb-4">
                                    <div class="d-flex align-items-center">
                                        <!-- Author Avatar -->
                                        <div class="position-relative me-3">
                                            <?php if ($author_profile_image && file_exists(str_replace('../', '', $author_profile_image))): ?>
                                                <img src="<?php echo $author_profile_image; ?>" 
                                                     alt="<?php echo htmlspecialchars($post['author_name']); ?>" 
                                                     class="rounded-circle border border-3 border-white shadow-sm" 
                                                     width="60" height="60" style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 60px; height: 60px; font-size: 20px; background: linear-gradient(135deg, #3B9DB3 0%, #2a7080 100%); color: white; border: 3px solid white;">
                                                    <?php echo strtoupper(substr($post['author_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($post['admin_id'] == $admin_id): ?>
                                                <span class="position-absolute bottom-0 end-0" style="background: #3B9DB3; color: white; border-radius: 50%; padding: 3px; border: 2px solid white;">
                                                    <i class="fas fa-user-check" style="font-size: 10px;"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Author Info -->
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($post['author_name']); ?></h6>
                                            <div class="text-muted small">
                                                <i class="fas fa-clock me-1"></i><?php echo $time_ago; ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-calendar me-1"></i><?php echo $post_date; ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-clock me-1"></i><?php echo $post_time; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Badges and Actions -->
                                    <div class="d-flex align-items-center gap-2">
                                        <!-- Priority Badge -->
                                        <span class="badge <?php echo $badge_class; ?> px-3 py-2 rounded-pill">
                                            <i class="<?php echo $priority_icon; ?> me-1"></i>
                                            <?php echo ucfirst($post['priority']); ?>
                                        </span>
                                        
                                        <!-- Visibility Badge -->
                                        <?php echo $visibility_badge; ?>
                                        
                                        <!-- Views -->
                                        <span class="badge bg-light text-dark px-3 py-2 border">
                                            <i class="fas fa-eye me-1"></i><?php echo $post['views_count']; ?>
                                        </span>
                                        
                                        <!-- Action Menu -->
                                        <?php if ($can_edit_delete): ?>
                                            <div class="dropdown">
                                                <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow">
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" 
                                                           onclick="confirmDelete(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars(addslashes($post['title'])); ?>')">
                                                            <i class="fas fa-trash me-2"></i> Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Title -->
                                <h4 class="mb-3 fw-bold text-dark">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </h4>
                                
                                <!-- Content -->
                                <div class="post-content mb-4">
                                    <div class="content-text">
                                        <?php echo nl2br(htmlspecialchars($post['description'])); ?>
                                    </div>
                                </div>
                                
                                <!-- File Attachment -->
                                <?php if ($post['file_path'] && file_exists($full_file_path)): ?>
                                    <div class="attachment-box bg-light bg-opacity-25 rounded-3 p-3 mb-4 border">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0 fw-bold">
                                                <i class="fas fa-paperclip me-2"></i>Attached File
                                            </h6>
                                            <span class="badge <?php echo $file_color; ?> bg-opacity-10 px-3 py-2 rounded-pill">
                                                <i class="<?php echo $file_icon; ?> me-1"></i>
                                                <?php echo strtoupper($post['file_type']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="d-flex align-items-center justify-content-between gap-3">
                                            <!-- File Info -->
                                            <div class="d-flex align-items-center flex-grow-1">
                                                <div class="me-3">
                                                    <div class="icon-wrapper <?php echo $file_bg_color; ?> p-3 rounded-circle">
                                                        <i class="<?php echo $file_icon; ?> fa-lg <?php echo $file_color; ?>"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold text-truncate"><?php echo htmlspecialchars($post['file_name']); ?></div>
                                                    <div class="text-muted small">
                                                        <?php 
                                                        // Format file size
                                                        $file_size = $post['file_size'];
                                                        if ($file_size < 1024) {
                                                            echo $file_size . ' bytes';
                                                        } elseif ($file_size < 1048576) {
                                                            echo round($file_size / 1024, 2) . ' KB';
                                                        } else {
                                                            echo round($file_size / 1048576, 2) . ' MB';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- File Actions -->
                                            <div class="d-flex gap-2">
                                                <?php if ($post['file_type'] == 'image'): ?>
                                                    <!-- Image Preview Button -->
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="openImageModal('<?php echo htmlspecialchars($full_file_path); ?>', '<?php echo htmlspecialchars($post['title']); ?>')">
                                                        <i class="fas fa-expand me-1"></i> View
                                                    </button>
                                                <?php elseif ($post['file_type'] == 'video'): ?>
                                                    <!-- Video Preview Button -->
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="openMediaModal('video', '<?php echo htmlspecialchars($full_file_path); ?>', '<?php echo htmlspecialchars($post['title']); ?>')">
                                                        <i class="fas fa-play me-1"></i> Play
                                                    </button>
                                                <?php elseif ($post['file_type'] == 'audio'): ?>
                                                    <!-- Audio Preview Button -->
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="openMediaModal('audio', '<?php echo htmlspecialchars($full_file_path); ?>', '<?php echo htmlspecialchars($post['title']); ?>')">
                                                        <i class="fas fa-play-circle me-1"></i> Play
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Document/Other File View Button -->
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="openDocumentModal('<?php echo htmlspecialchars($full_file_path); ?>', '<?php echo htmlspecialchars($post['title']); ?>', '<?php echo $post['file_type']; ?>')">
                                                        <i class="fas fa-eye me-1"></i> View
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <!-- Download Button -->
                                                <a href="<?php echo htmlspecialchars($full_file_path); ?>" 
                                                   class="btn btn-outline-success btn-sm" download>
                                                    <i class="fas fa-download me-1"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <!-- Image Thumbnail (Small Preview) -->
                                        <?php if ($post['file_type'] == 'image'): ?>
                                            <div class="mt-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">Preview:</small>
                                                    <button class="btn btn-link btn-sm p-0 text-decoration-none" 
                                                            onclick="openImageModal('<?php echo htmlspecialchars($full_file_path); ?>', '<?php echo htmlspecialchars($post['title']); ?>')">
                                                        <i class="fas fa-expand-alt me-1"></i> Full View
                                                    </button>
                                                </div>
                                                <div class="text-center">
                                                    <img src="<?php echo htmlspecialchars($full_file_path); ?>" 
                                                         alt="Image Preview" 
                                                         class="img-thumbnail cursor-pointer"
                                                         style="max-height: 150px; max-width: 200px;"
                                                         onclick="openImageModal('<?php echo htmlspecialchars($full_file_path); ?>', '<?php echo htmlspecialchars($post['title']); ?>')">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($post['file_path']): ?>
                                    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center small">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <div>Attachment file not found or has been removed.</div>
                                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Footer -->
                                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                    <div class="text-muted small">
                                        <?php if ($post['admin_id'] == $admin_id): ?>
                                            <span class="badge bg-primary bg-opacity-25 text-primary">
                                                <i class="fas fa-star me-1"></i>Your Post
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="text-center py-5">
                        <div class="empty-state">
                            <div class="mb-4">
                                <i class="fas fa-shield-alt fa-5x text-muted opacity-25"></i>
                            </div>
                            <h4 class="text-muted mb-3">No Safety Announcements Yet</h4>
                            <p class="text-muted mb-4">Be the first to create a safety announcement for the school community.</p>
                            <?php if ($is_shule_salama): ?>
                                <button class="btn btn-primary btn-lg px-5 shadow-sm" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                                    <i class="fas fa-plus-circle me-2"></i> Create First Announcement
                                </button>
                            <?php else: ?>
                                <div class="alert alert-info border-0 bg-info bg-opacity-10 w-75 mx-auto">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Only authorized personnel with appropriate roles can create announcements.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (mysqli_num_rows($posts_result) > 0): ?>
                <div class="card-footer bg-white border-0 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">
                            <i class="fas fa-info-circle me-2"></i>
                            Showing <?php echo $total_posts; ?> announcement(s)
                        </div>
                        <div>
                            <button class="btn btn-outline-primary btn-sm" onclick="scrollToTop()">
                                <i class="fas fa-arrow-up me-2"></i> Back to Top
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-labelledby="createAnnouncementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background: linear-gradient(135deg, #3B9DB3 0%, #2a7080 100%); color: white; border: 0;">
                <h5 class="modal-title" id="createAnnouncementModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Create Safety Announcement
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <form method="POST" enctype="multipart/form-data" id="announcementForm" class="needs-validation" novalidate>
                    <div class="p-4">
                        <!-- Title -->
                        <div class="form-floating mb-4">
                            <input type="text" class="form-control form-control-lg" id="title" name="title" 
                                   placeholder="Announcement title" 
                                   required maxlength="200">
                            <label for="title" class="fw-bold">
                                <i class="fas fa-heading me-2 text-muted"></i>Announcement Title <span class="text-danger">*</span>
                            </label>
                            <div class="form-text d-flex justify-content-between align-items-center mt-2">
                                <span>Clear and descriptive title</span>
                                <span class="text-muted"><span id="titleCount">0</span>/200</span>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-bold mb-3">
                                <i class="fas fa-align-left me-2 text-muted"></i>Detailed Information <span class="text-danger">*</span>
                            </label>
                            <div class="editor-wrapper border rounded-3 p-3">
                                <textarea class="form-control border-0" id="description" name="description" 
                                          rows="6" placeholder="Provide detailed safety information, instructions, and contact information..." 
                                          required maxlength="5000"></textarea>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div class="form-text">
                                    Include all relevant details, instructions, and contact information
                                </div>
                                <small class="text-muted">
                                    <span id="charCount">0</span>/5000 characters
                                </small>
                            </div>
                        </div>
                        
                        <!-- File Upload Section -->
                        <div class="mb-4">
                            <label class="form-label fw-bold mb-3">
                                <i class="fas fa-paperclip me-2 text-muted"></i>Attachment (Optional)
                            </label>
                            <div class="file-upload-wrapper border-2 border-dashed rounded-3 p-5 text-center bg-light bg-opacity-25" 
                                 id="fileUploadArea">
                                <div class="mb-3">
                                    <i class="fas fa-cloud-upload-alt fa-4x text-muted"></i>
                                </div>
                                <h5 class="mb-2">Drag & drop your files here</h5>
                                <p class="text-muted mb-4">or click to browse</p>
                                <input type="file" class="form-control d-none" id="attachment" name="attachment" 
                                       accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.pdf,.doc,.docx,.txt,.rtf,.odt,.mp3,.wav,.ogg,.m4a,.mp4,.avi,.mov,.wmv,.flv,.mkv,.zip,.rar,.7z,.tar,.gz">
                                <label for="attachment" class="btn btn-outline-primary btn-lg px-5">
                                    <i class="fas fa-folder-open me-2"></i>Choose File
                                </label>
                                <div class="form-text small mt-4">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Max file size: 20MB. Supported: Images, Documents, Audio, Video, Archives
                                </div>
                            </div>
                            <div id="filePreview" class="mt-3"></div>
                        </div>
                        
                        <div class="row g-4">
                            <!-- Visibility -->
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="visibility" class="form-label fw-bold mb-3">
                                        <i class="fas fa-eye me-2 text-muted"></i>Visibility
                                    </label>
                                    <select class="form-select form-select-lg" id="visibility" name="visibility" required>
                                        <option value="public" selected>
                                            Public (Everyone)
                                        </option>
                                        <option value="staff_only">
                                            Staff Only
                                        </option>
                                        <option value="students_only">
                                            Students Only
                                        </option>
                                    </select>
                                    <div class="form-text mt-2">
                                        <i class="fas fa-users me-1"></i>
                                        Control who can see this announcement
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Priority -->
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="priority" class="form-label fw-bold mb-3">
                                        <i class="fas fa-flag me-2 text-muted"></i>Priority Level
                                    </label>
                                    <select class="form-select form-select-lg" id="priority" name="priority" required>
                                        <option value="normal" selected>
                                            <span class="badge bg-success me-2">🟢</span> Normal - Routine information
                                        </option>
                                        <option value="important">
                                            <span class="badge bg-warning me-2">🟡</span> Important - Requires attention
                                        </option>
                                        <option value="critical">
                                            <span class="badge bg-orange me-2">🟠</span> Critical - Immediate action needed
                                        </option>
                                        <option value="emergency">
                                            <span class="badge bg-danger me-2">🔴</span> Emergency - Urgent safety concern
                                        </option>
                                    </select>
                                    <div class="form-text mt-2">
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        Set the urgency level for this announcement
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Author Info -->
                        <div class="card border bg-light bg-opacity-25 mb-4">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center">
                                    <?php 
                                    $admin_profile_image = getProfileImagePath($admin['profile_image']);
                                    if ($admin_profile_image && file_exists(str_replace('../', '', $admin_profile_image))): ?>
                                        <img src="<?php echo $admin_profile_image; ?>" 
                                             alt="<?php echo htmlspecialchars($admin['first_name']); ?>" 
                                             class="rounded-circle me-3" width="50" height="50" style="object-fit: cover;">
                                    <?php else: ?>
                                        <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center me-3" 
                                             style="width: 50px; height: 50px; font-size: 18px; background: linear-gradient(135deg, #3B9DB3 0%, #2a7080 100%); color: white;">
                                            <?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <small class="text-muted d-block">Posted by</small>
                                        <div class="fw-bold"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></div>
                                        <div class="small text-muted">
                                            <i class="fas fa-calendar-alt me-1"></i><?php echo date('M d, Y'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-outline-secondary btn-lg px-4" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm" id="publishBtn">
                            <i class="fas fa-paper-plane me-2"></i> Publish Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Success Animation Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg bg-transparent">
            <div class="modal-body text-center p-5">
                <div class="success-animation">
                    <div class="checkmark-circle bg-success rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" 
                         style="width: 100px; height: 100px;">
                        <i class="fas fa-check fa-3x text-white"></i>
                    </div>
                    <h4 class="fw-bold text-white mb-3">Success!</h4>
                    <p class="text-light mb-4" id="successMessage">Announcement published successfully!</p>
                    <div class="loading-bar mt-3">
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-white" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: 0;">
                <h5 class="modal-title" id="deleteConfirmationModalLabel">
                    <i class="fas fa-trash-alt me-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <div class="icon-wrapper bg-danger bg-opacity-10 p-4 rounded-circle d-inline-block">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger"></i>
                    </div>
                </div>
                <h5 class="mb-3 fw-bold" id="deleteTitle">Delete Announcement?</h5>
                <p class="mb-4 text-muted" id="deleteMessage">Are you sure you want to delete this safety announcement?</p>
                <div class="alert alert-warning border-0 bg-warning bg-opacity-10">
                    <div class="d-flex">
                        <i class="fas fa-exclamation-circle fa-lg mt-1 me-3 text-warning"></i>
                        <div>
                            <strong>Warning:</strong> This action cannot be undone. All associated data will be permanently removed.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-center border-0">
                <button type="button" class="btn btn-outline-secondary btn-lg px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Cancel
                </button>
                <a href="#" class="btn btn-danger btn-lg px-4" id="confirmDeleteBtn">
                    <i class="fas fa-trash-alt me-2"></i> Yes, Delete
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Delete All Confirmation Modal -->
<div class="modal fade" id="deleteAllModal" tabindex="-1" aria-labelledby="deleteAllModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: 0;">
                <h5 class="modal-title" id="deleteAllModalLabel">
                    <i class="fas fa-trash-alt me-2"></i>Delete All Announcements
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <div class="icon-wrapper bg-danger bg-opacity-10 p-4 rounded-circle d-inline-block">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger"></i>
                    </div>
                </div>
                <h5 class="mb-3 fw-bold">Delete All Announcements?</h5>
                <p class="mb-4 text-muted">Are you sure you want to delete ALL safety announcements?</p>
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10">
                    <div class="d-flex">
                        <i class="fas fa-exclamation-circle fa-lg mt-1 me-3 text-danger"></i>
                        <div>
                            <strong class="d-block mb-1">Critical Warning</strong>
                            This will permanently delete <span class="fw-bold"><?php echo $total_posts; ?></span> announcements and all associated files. This action cannot be undone!
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-center border-0">
                <button type="button" class="btn btn-outline-secondary btn-lg px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Cancel
                                </button>
                                <a href="shulesalama.php?action=delete_all" class="btn btn-danger btn-lg px-4">
                                    <i class="fas fa-trash-alt me-2"></i> Delete All <?php echo $total_posts; ?> Announcements
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

<!-- Image Preview Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-white border-0">
                <h5 class="modal-title fw-bold" id="imageModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img src="" id="modalImage" class="img-fluid" alt="Preview">
            </div>
            <div class="modal-footer bg-white border-0">
                <a href="#" id="downloadImageLink" class="btn btn-primary" download>
                    <i class="fas fa-download me-2"></i> Download
                </a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Media Preview Modal (Video/Audio) -->
<div class="modal fade" id="mediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-white border-0">
                <h5 class="modal-title fw-bold" id="mediaModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div id="mediaPlayer"></div>
            </div>
            <div class="modal-footer bg-white border-0">
                <a href="#" id="downloadMediaLink" class="btn btn-primary" download>
                    <i class="fas fa-download me-2"></i> Download
                </a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Document Preview Modal -->
<div class="modal fade" id="documentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-white border-0">
                <h5 class="modal-title fw-bold" id="documentModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="document-viewer">
                    <iframe id="documentFrame" class="w-100" style="height: 70vh; border: none;"></iframe>
                </div>
            </div>
            <div class="modal-footer bg-white border-0">
                <a href="#" id="downloadDocumentLink" class="btn btn-primary" download>
                    <i class="fas fa-download me-2"></i> Download
                </a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* Custom Styles */
.hover-lift {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}
.hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
}

.icon-wrapper {
    transition: all 0.3s ease;
}

.bg-orange {
    background-color: #ff6b35;
    color: white;
}

.cursor-pointer {
    cursor: pointer;
}

.editor-wrapper {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.file-upload-wrapper {
    border: 2px dashed #dee2e6;
    transition: all 0.3s ease;
}
.file-upload-wrapper:hover,
.file-upload-wrapper.dragover {
    border-color: #3B9DB3 !important;
    background-color: rgba(59, 157, 179, 0.05) !important;
}

.avatar-placeholder {
    background: linear-gradient(135deg, #3B9DB3 0%, #2a7080 100%);
}

.priority-badge {
    font-size: 0.8rem;
    padding: 0.25rem 0.75rem;
}

.post-content {
    line-height: 1.8;
    font-size: 1.05rem;
}

.attachment-box {
    border-left: 4px solid #3B9DB3;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
}
::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* Search wrapper */
.search-wrapper input {
    transition: all 0.3s ease;
}
.search-wrapper input:focus {
    box-shadow: 0 0 0 3px rgba(59, 157, 179, 0.1);
    border-color: #3B9DB3;
}

/* Image thumbnail */
.img-thumbnail {
    transition: all 0.3s ease;
    border: 2px solid #dee2e6;
}
.img-thumbnail:hover {
    transform: scale(1.05);
    border-color: #3B9DB3;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

/* Document viewer */
.document-viewer {
    background: #f8f9fa;
    min-height: 400px;
}

/* Success animation */
#successModal .modal-content {
    background: linear-gradient(135deg, #3B9DB3 0%, #2a7080 100%);
}
.checkmark-circle {
    animation: checkmarkAnimation 0.6s ease-in-out;
}
@keyframes checkmarkAnimation {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.2); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
}

/* File preview */
.file-preview-item {
    animation: fadeIn 0.3s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.border-dashed {
    border-style: dashed !important;
}

/* Page header gradient matching header */
.page-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
    padding: 20px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show success modal if redirected with success parameter
    <?php if (isset($_GET['success']) && $_GET['success'] == 'true'): ?>
        setTimeout(() => {
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            
            // Auto close after 3 seconds
            setTimeout(() => {
                successModal.hide();
                window.history.replaceState({}, document.title, window.location.pathname);
            }, 3000);
            
            // Animate progress bar
            const progressBar = document.querySelector('#successModal .progress-bar');
            let width = 0;
            const interval = setInterval(() => {
                if (width >= 100) {
                    clearInterval(interval);
                } else {
                    width += 10;
                    progressBar.style.width = width + '%';
                }
            }, 300);
        }, 500);
    <?php endif; ?>
    
    // Character counter
    const titleInput = document.getElementById('title');
    const titleCount = document.getElementById('titleCount');
    const description = document.getElementById('description');
    const charCount = document.getElementById('charCount');
    
    // Title character counter
    if (titleInput && titleCount) {
        function updateTitleCount() {
            titleCount.textContent = titleInput.value.length;
            if (titleInput.value.length > 180) {
                titleCount.className = 'text-danger';
            } else if (titleInput.value.length > 150) {
                titleCount.className = 'text-warning';
            } else {
                titleCount.className = 'text-muted';
            }
        }
        
        titleInput.addEventListener('input', updateTitleCount);
        updateTitleCount();
    }
    
    // Description character counter
    if (description && charCount) {
        function updateCharCount() {
            charCount.textContent = description.value.length;
            if (description.value.length > 4500) {
                charCount.className = 'text-danger';
            } else if (description.value.length > 4000) {
                charCount.className = 'text-warning';
            } else {
                charCount.className = 'text-muted';
            }
        }
        
        description.addEventListener('input', updateCharCount);
        updateCharCount();
    }
    
    // Form validation
    const form = document.getElementById('announcementForm');
    const publishBtn = document.getElementById('publishBtn');
    
    if (form && publishBtn) {
        form.addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            
            // Basic validation
            if (!title || !description) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please fill in all required fields (Title and Description).',
                    confirmButtonColor: '#3B9DB3',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Okay',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                });
                return false;
            }
            
            if (title.length > 200) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Title Too Long',
                    text: 'Title must be 200 characters or less.',
                    confirmButtonColor: '#3B9DB3',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Okay'
                });
                return false;
            }
            
            if (description.length > 5000) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Description Too Long',
                    text: 'Description must be 5000 characters or less.',
                    confirmButtonColor: '#3B9DB3',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Okay'
                });
                return false;
            }
            
            // Show loading state
            publishBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Publishing...';
            publishBtn.disabled = true;
            
            return true;
        });
    }
    
    // Enhanced file upload handling
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('attachment');
    const filePreview = document.getElementById('filePreview');
    
    if (fileUploadArea && fileInput) {
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Highlight drop area
        ['dragenter', 'dragover'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            fileUploadArea.classList.add('dragover');
        }
        
        function unhighlight() {
            fileUploadArea.classList.remove('dragover');
        }
        
        // Handle dropped files
        fileUploadArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            updateFilePreview();
        }
        
        // Handle file selection
        fileInput.addEventListener('change', updateFilePreview);
        
        function updateFilePreview() {
            filePreview.innerHTML = '';
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const fileSize = file.size > 1024 * 1024 
                    ? (file.size / (1024 * 1024)).toFixed(2) + ' MB'
                    : (file.size / 1024).toFixed(2) + ' KB';
                
                // Determine file type icon
                let fileIcon = 'fas fa-file';
                let fileColor = 'text-primary';
                
                if (file.type.startsWith('image/')) {
                    fileIcon = 'fas fa-image';
                    fileColor = 'text-success';
                } else if (file.type.startsWith('video/')) {
                    fileIcon = 'fas fa-video';
                    fileColor = 'text-danger';
                } else if (file.type.startsWith('audio/')) {
                    fileIcon = 'fas fa-music';
                    fileColor = 'text-warning';
                } else if (file.type.includes('pdf')) {
                    fileIcon = 'fas fa-file-pdf';
                    fileColor = 'text-danger';
                } else if (file.type.includes('word') || file.type.includes('document')) {
                    fileIcon = 'fas fa-file-word';
                    fileColor = 'text-primary';
                }
                
                const preview = document.createElement('div');
                preview.className = 'file-preview-item alert alert-success d-flex align-items-center justify-content-between';
                preview.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="${fileIcon} fa-2x ${fileColor}"></i>
                        </div>
                        <div>
                            <strong class="d-block">${file.name}</strong>
                            <small class="text-muted d-block">${fileSize}</small>
                            <small class="text-muted">${file.type}</small>
                        </div>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeFile()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                filePreview.appendChild(preview);
                
                // If it's an image, show preview
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const imgPreview = document.createElement('div');
                        imgPreview.className = 'mt-3 text-center';
                        imgPreview.innerHTML = `
                            <p class="small text-muted mb-2">Image Preview:</p>
                            <img src="${e.target.result}" class="img-thumbnail" style="max-height: 150px;">
                        `;
                        preview.appendChild(imgPreview);
                    };
                    reader.readAsDataURL(file);
                }
            }
        }
        
        // Click on upload area to trigger file input
        fileUploadArea.addEventListener('click', () => {
            fileInput.click();
        });
    }
    
    // Search functionality
    const searchInput = document.getElementById('searchPosts');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const searchTerm = this.value.toLowerCase();
                const items = document.querySelectorAll('.announcement-item');
                
                items.forEach(item => {
                    const title = item.querySelector('h4').textContent.toLowerCase();
                    const content = item.querySelector('.content-text').textContent.toLowerCase();
                    const author = item.querySelector('h6.fw-bold').textContent.toLowerCase();
                    
                    if (title.includes(searchTerm) || content.includes(searchTerm) || author.includes(searchTerm)) {
                        item.style.display = 'block';
                        item.classList.add('animate__animated', 'animate__fadeIn');
                    } else {
                        item.style.display = 'none';
                    }
                });
            }, 300);
        });
    }
    
    // Filter functionality
    document.querySelectorAll('[data-filter]').forEach(filter => {
        filter.addEventListener('click', function(e) {
            e.preventDefault();
            const filterValue = this.getAttribute('data-filter');
            const items = document.querySelectorAll('.announcement-item');
            
            items.forEach(item => {
                const priority = item.getAttribute('data-priority');
                const author = item.getAttribute('data-author');
                
                if (filterValue === 'all' || 
                    (filterValue === 'yours' && author === 'yours') ||
                    (filterValue !== 'yours' && priority === filterValue)) {
                    item.style.display = 'block';
                    item.classList.add('animate__animated', 'animate__fadeIn');
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Update active filter
            document.querySelectorAll('.dropdown-item').forEach(item => {
                item.classList.remove('active');
            });
            this.classList.add('active');
            
            // Close dropdown
            const dropdown = bootstrap.Dropdown.getInstance(this.closest('.dropdown').querySelector('[data-bs-toggle="dropdown"]'));
            dropdown.hide();
        });
    });
});

// Helper Functions
function removeFile() {
    const fileInput = document.getElementById('attachment');
    fileInput.value = '';
    document.getElementById('filePreview').innerHTML = '';
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Delete confirmation
function confirmDelete(postId, postTitle) {
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
    
    // Set modal content
    document.getElementById('deleteTitle').textContent = `Delete "${postTitle}"?`;
    document.getElementById('deleteMessage').textContent = `Are you sure you want to delete the announcement "${postTitle}"?`;
    document.getElementById('confirmDeleteBtn').href = `shulesalama.php?action=delete&id=${postId}`;
    
    deleteModal.show();
}

// Image preview modal
function openImageModal(imageSrc, imageTitle) {
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('imageModalLabel');
    const downloadLink = document.getElementById('downloadImageLink');
    
    modalImage.src = imageSrc;
    modalImage.alt = imageTitle;
    modalTitle.textContent = imageTitle;
    downloadLink.href = imageSrc;
    downloadLink.download = imageTitle.replace(/[^a-z0-9]/gi, '_') + '.jpg';
    
    modal.show();
}

// Media preview modal (Video/Audio)
function openMediaModal(type, mediaSrc, mediaTitle) {
    const modal = new bootstrap.Modal(document.getElementById('mediaModal'));
    const mediaPlayer = document.getElementById('mediaPlayer');
    const modalTitle = document.getElementById('mediaModalLabel');
    const downloadLink = document.getElementById('downloadMediaLink');
    
    modalTitle.textContent = mediaTitle;
    downloadLink.href = mediaSrc;
    
    if (type === 'video') {
        mediaPlayer.innerHTML = `
            <video controls class="w-100 rounded" style="max-height: 60vh;">
                <source src="${mediaSrc}" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        `;
        downloadLink.download = mediaTitle.replace(/[^a-z0-9]/gi, '_') + '.mp4';
    } else if (type === 'audio') {
        mediaPlayer.innerHTML = `
            <audio controls class="w-100">
                <source src="${mediaSrc}" type="audio/mpeg">
                Your browser does not support the audio element.
            </audio>
        `;
        downloadLink.download = mediaTitle.replace(/[^a-z0-9]/gi, '_') + '.mp3';
    }
    
    modal.show();
}

// Document preview modal
function openDocumentModal(docSrc, docTitle, docType) {
    const modal = new bootstrap.Modal(document.getElementById('documentModal'));
    const documentFrame = document.getElementById('documentFrame');
    const modalTitle = document.getElementById('documentModalLabel');
    const downloadLink = document.getElementById('downloadDocumentLink');
    
    modalTitle.textContent = docTitle;
    downloadLink.href = docSrc;
    
    // Set appropriate viewer based on file type
    if (docType === 'pdf') {
        documentFrame.src = docSrc + '#view=FitH';
        downloadLink.download = docTitle.replace(/[^a-z0-9]/gi, '_') + '.pdf';
    } else if (docType === 'document') {
        // For Word documents, use Google Docs viewer
        documentFrame.src = `https://docs.google.com/gview?url=${encodeURIComponent(window.location.origin + docSrc.substring(1))}&embedded=true`;
        downloadLink.download = docTitle.replace(/[^a-z0-9]/gi, '_') + '.docx';
    } else {
        // For other document types, try to open directly
        documentFrame.src = docSrc;
        downloadLink.download = docTitle.replace(/[^a-z0-9]/gi, '_') + '.' + docType;
    }
    
    modal.show();
}

// Reset form when modal is closed
const createModal = document.getElementById('createAnnouncementModal');
if (createModal) {
    createModal.addEventListener('hidden.bs.modal', function () {
        document.getElementById('announcementForm').reset();
        document.getElementById('filePreview').innerHTML = '';
        const publishBtn = document.getElementById('publishBtn');
        if (publishBtn) {
            publishBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Publish Announcement';
            publishBtn.disabled = false;
        }
    });
}

<?php 
// Updated time_elapsed_string function with better formatting
function get_time_ago($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Calculate total seconds
    $total_seconds = ($now->getTimestamp() - $ago->getTimestamp());
    
    if ($total_seconds < 60) {
        return "Soon posted";
    } elseif ($total_seconds < 3600) {
        $minutes = floor($total_seconds / 60);
        return $minutes . ($minutes == 1 ? " minute ago" : " minutes ago");
    } elseif ($total_seconds < 86400) {
        $hours = floor($total_seconds / 3600);
        return $hours . ($hours == 1 ? " hour ago" : " hours ago");
    } elseif ($total_seconds < 604800) {
        $days = floor($total_seconds / 86400);
        return $days . ($days == 1 ? " day ago" : " days ago");
    } elseif ($total_seconds < 2592000) {
        $weeks = floor($total_seconds / 604800);
        return $weeks . ($weeks == 1 ? " week ago" : " weeks ago");
    } elseif ($total_seconds < 31536000) {
        $months = floor($total_seconds / 2592000);
        return $months . ($months == 1 ? " month ago" : " months ago");
    } else {
        $years = floor($total_seconds / 31536000);
        return $years . ($years == 1 ? " year ago" : " years ago");
    }
}
?>
</script>

<?php include '../controller/footer.php'; ?>