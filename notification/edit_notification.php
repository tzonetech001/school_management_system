<?php
// edit_notification.php
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../controller/login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$is_admin = false;

// Get admin info and check if they are admin (Head Master or Second Master)
$admin_sql = "SELECT a.*, 
             GROUP_CONCAT(DISTINCT ar.role_name) as roles
             FROM admins a
             LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
             LEFT JOIN admin_roles ar ON ara.role_id = ar.id
             WHERE a.id = $admin_id
             GROUP BY a.id";
$admin_result = mysqli_query($conn, $admin_sql);

if ($admin_result && mysqli_num_rows($admin_result) > 0) {
    $admin_info = mysqli_fetch_assoc($admin_result);
    $admin_roles = explode(',', $admin_info['roles']);
    $is_admin = in_array('Head Master', $admin_roles) || in_array('Second Master', $admin_roles);
}

// Get notification ID
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No notification selected";
    header("Location: notifications.php");
    exit();
}

$notification_id = mysqli_real_escape_string($conn, $_GET['id']);

// Get notification details
$sql = "SELECT n.*, 
       CONCAT(a.first_name, ' ', a.last_name) as author_name,
       a.profile_image as author_image
       FROM notifications n
       JOIN admins a ON n.admin_id = a.id
       WHERE n.id = $notification_id AND n.status = 'active'";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Notification not found";
    header("Location: notifications.php");
    exit();
}

$notification = mysqli_fetch_assoc($result);

// Check if user can edit this notification (owner or admin)
if ($notification['admin_id'] != $admin_id && !$is_admin) {
    $_SESSION['error'] = "You don't have permission to edit this notification";
    header("Location: notifications.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $visibility = mysqli_real_escape_string($conn, $_POST['visibility'] ?? 'public');
    $priority = mysqli_real_escape_string($conn, $_POST['priority'] ?? 'normal');
    $remove_attachment = isset($_POST['remove_attachment']) ? 1 : 0;
    
    // Handle file upload
    $file_path = $notification['file_path'];
    $file_name = $notification['file_name'];
    $file_type = $notification['file_type'];
    $file_size = $notification['file_size'];
    
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'video/mp4', 'video/mpeg', 'video/quicktime', 'video/webm', 'video/avi',
            'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/aac',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed',
            'text/plain', 'text/csv'
        ];
        
        $max_size = 5 * 1024 * 1024; // 5MB
        $file = $_FILES['attachment'];
        
        if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            // Delete old file if exists
            if ($notification['file_path'] && file_exists($notification['file_path'])) {
                unlink($notification['file_path']);
            }
            
            // Create uploads directory if it doesn't exist
            $upload_dir = '../uploads/notifications/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safe_filename = preg_replace('/[^a-zA-Z0-9.-]/', '_', $file['name']);
            $file_name = uniqid() . '_' . $safe_filename;
            $file_path = $upload_dir . $file_name;
            
            // Determine file type category
            $mime_type = $file['type'];
            if (strpos($mime_type, 'image/') === 0) {
                $file_type = 'image';
            } elseif (strpos($mime_type, 'video/') === 0) {
                $file_type = 'video';
            } elseif (strpos($mime_type, 'audio/') === 0) {
                $file_type = 'audio';
            } elseif ($mime_type == 'application/pdf') {
                $file_type = 'document';
            } elseif (in_array($mime_type, ['application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed'])) {
                $file_type = 'archive';
            } elseif (strpos($mime_type, 'application/vnd.ms-') === 0 || 
                      strpos($mime_type, 'application/vnd.openxmlformats') === 0) {
                $file_type = 'document';
            } else {
                $file_type = 'other';
            }
            
            $file_size = $file['size'];
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                $_SESSION['error'] = "Failed to upload file. Please try again.";
                header("Location: edit_notification.php?id=$notification_id");
                exit();
            }
        } else {
            if (!in_array($file['type'], $allowed_types)) {
                $_SESSION['error'] = "File type not allowed. Allowed types: Images, Videos, Audio, PDF, Office documents, ZIP, TXT, CSV";
            } else {
                $_SESSION['error'] = "File size must be less than 5MB";
            }
            header("Location: edit_notification.php?id=$notification_id");
            exit();
        }
    } elseif ($remove_attachment && $notification['file_path']) {
        // Remove existing attachment
        if (file_exists($notification['file_path'])) {
            unlink($notification['file_path']);
        }
        $file_path = null;
        $file_name = null;
        $file_type = null;
        $file_size = null;
    }
    
    // Update notification
    $sql = "UPDATE notifications SET 
            title = '$title',
            description = '$description',
            visibility = '$visibility',
            priority = '$priority',
            file_path = " . ($file_path ? "'$file_path'" : "NULL") . ",
            file_name = " . ($file_name ? "'$file_name'" : "NULL") . ",
            file_type = " . ($file_type ? "'$file_type'" : "NULL") . ",
            file_size = " . ($file_size ? "$file_size" : "NULL") . ",
            updated_at = NOW()
            WHERE id = $notification_id";
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Notification updated successfully!";
        header("Location: notifications.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating notification: " . mysqli_error($conn);
        header("Location: edit_notification.php?id=$notification_id");
        exit();
    }
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
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

// Get relative file path for display
$file_url = '';
if ($notification['file_path']) {
    $file_url = str_replace('../../', '', $notification['file_path']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Notification - Muyovozi High School</title>
    
    <!-- Include header -->
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>
    
    <!-- Additional CSS for edit notification page -->
    <style>
        .edit-notification-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            background: lightgray;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .page-title {
            color: var(--primary-dark);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 15px;
            margin-bottom: 30px;
            font-weight: 700;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        
        .upload-area {
            border: 2px dashed #3B9DB3;
            border-radius: 10px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(59, 157, 179, 0.05);
            margin-bottom: 20px;
        }
        
        .upload-area:hover {
            background: rgba(59, 157, 179, 0.1);
            border-color: #2d7c8f;
        }
        
        .upload-area.dragover {
            background: rgba(59, 157, 179, 0.2);
            border-color: #1a5c6d;
        }
        
        .upload-icon {
            font-size: 2.5rem;
            color: #3B9DB3;
            margin-bottom: 15px;
        }
        
        .current-file-card {
            background: #e8f4f8;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #3B9DB3;
        }
        
        .file-preview {
            max-width: 100%;
            margin: 15px 0;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
            padding: 10px;
            border: 1px solid #e0e0e0;
        }
        
        .file-preview img {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
            border-radius: 5px;
        }
        
        .file-preview video {
            width: 100%;
            border-radius: 5px;
        }
        
        .supported-formats {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .supported-formats h6 {
            color: #3B9DB3;
            margin-bottom: 10px;
        }
        
        .format-badge {
            display: inline-block;
            background: #e8f4f8;
            color: #3B9DB3;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin: 3px;
            font-weight: 500;
        }
        
        .back-button {
            margin-right: 15px;
        }
        
        .form-text {
            font-size: 0.85rem;
            color: #666;
        }
        
        .file-size-limit {
            display: block;
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        .preview-section {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .preview-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .notification-meta {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .notification-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .notification-info i {
            color: #3B9DB3;
        }
        
        .original-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #856404;
        }
        
        .original-notice i {
            color: #ffc107;
            margin-right: 8px;
        }
        
        .remove-file-btn {
            transition: all 0.3s ease;
        }
        
        .remove-file-btn:hover {
            transform: scale(1.1);
            color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .edit-notification-container {
                padding: 20px;
                margin: 10px;
            }
            
            .upload-area {
                padding: 25px 15px;
            }
            
            .upload-icon {
                font-size: 2rem;
            }
        }
        
        .author-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #3B9DB3;
        }
        
        .author-info-small {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .author-details small {
            display: block;
            color: #666;
        }
    </style>
</head>
<body>
   
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="page-title">
                    <i class="fas fa-edit me-2"></i>Edit Notification
                </h2>
                <div>
                    <a href="notifications.php" class="btn btn-outline-secondary back-button">
                        <i class="fas fa-arrow-left me-2"></i>Back to Notifications
                    </a>
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

            <!-- Edit Notification Form -->
            <div class="edit-notification-container">
                <!-- Original Notification Info -->
                <div class="notification-info">
                    <?php if ($notification['author_image'] && file_exists($notification['author_image'])): ?>
                        <img src="<?php echo $notification['author_image']; ?>" alt="Author" class="author-avatar">
                    <?php else: ?>
                        <div class="author-avatar bg-primary text-white d-flex align-items-center justify-content-center">
                            <?php echo substr($notification['author_name'], 0, 1); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <strong>Original Author:</strong> <?php echo htmlspecialchars($notification['author_name']); ?><br>
                        <small>Created: <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></small>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="editNotificationForm">
                    <!-- Title -->
                    <div class="mb-4">
                        <label for="title" class="form-label required-field">Notification Title</label>
                        <input type="text" class="form-control form-control-lg" id="title" name="title" required
                               value="<?php echo htmlspecialchars($notification['title']); ?>"
                               placeholder="Enter a clear and descriptive title for your notification"
                               maxlength="200" oninput="updatePreview()">
                        <div class="form-text">Maximum 200 characters. This will be the main heading of your notification.</div>
                    </div>
                    
                    <!-- Description -->
                    <div class="mb-4">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="5" placeholder="Add more details about this notification..."
                                  maxlength="1000" oninput="updatePreview()"><?php echo htmlspecialchars($notification['description']); ?></textarea>
                        <div class="form-text">Maximum 1000 characters. Provide additional information or instructions.</div>
                    </div>
                    
                    <!-- Current File -->
                    <?php if ($notification['file_path'] && file_exists($notification['file_path'])): ?>
                        <div class="mb-4">
                            <label class="form-label">Current Attachment</label>
                            <div class="current-file-card">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas <?php echo getFileIcon($notification['file_type']); ?> me-3 fa-2x text-primary"></i>
                                        <div>
                                            <strong><?php echo htmlspecialchars($notification['file_name']); ?></strong>
                                            <div class="text-muted">
                                                <?php echo formatFileSize($notification['file_size']); ?> • 
                                                Uploaded: <?php echo date('M d, Y', strtotime($notification['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <a href="<?php echo $file_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary me-2">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                        <a href="<?php echo $file_url; ?>" download class="btn btn-sm btn-outline-success me-2">
                                            <i class="fas fa-download me-1"></i>Download
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- File Preview -->
                                <?php if ($notification['file_type'] == 'image'): ?>
                                    <div class="file-preview">
                                        <img src="<?php echo $file_url; ?>" 
                                             alt="<?php echo htmlspecialchars($notification['title']); ?>"
                                             class="img-fluid rounded">
                                    </div>
                                <?php elseif ($notification['file_type'] == 'video'): ?>
                                    <div class="file-preview">
                                        <video controls class="w-100 rounded">
                                            <source src="<?php echo $file_url; ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    </div>
                                <?php elseif ($notification['file_type'] == 'audio'): ?>
                                    <div class="file-preview text-center">
                                        <i class="fas fa-file-audio fa-3x text-primary mb-2"></i>
                                        <audio controls class="w-100">
                                            <source src="<?php echo $file_url; ?>" type="audio/mpeg">
                                            Your browser does not support the audio element.
                                        </audio>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Remove attachment option -->
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="remove_attachment" value="1" id="removeAttachment">
                                <label class="form-check-label" for="removeAttachment">
                                    <i class="fas fa-trash-alt me-1 text-danger"></i>
                                    Remove current attachment
                                </label>
                                <div class="form-text">Check this box to delete the current file from this notification.</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- New File Upload -->
                    <div class="mb-4">
                        <label class="form-label"><?php echo $notification['file_path'] ? 'Replace Attachment' : 'Add Attachment'; ?> (Optional, max 5MB)</label>
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <h5>Upload New File</h5>
                            <p class="text-muted">Drag & drop your file here or click to browse</p>
                            <span class="file-size-limit">Maximum file size: 5MB</span>
                            <input type="file" name="attachment" id="attachment" 
                                   class="d-none" 
                                   accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.mp4,.mpeg,.mpg,.webm,.avi,.mp3,.wav,.ogg,.aac,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt,.csv">
                        </div>
                        <div id="fileInfo"></div>
                        <div id="fileError" class="invalid-feedback d-none"></div>
                        
                        <!-- Supported Formats -->
                        <div class="supported-formats">
                            <h6><i class="fas fa-check-circle me-2"></i>Supported File Formats</h6>
                            <div>
                                <span class="format-badge">JPG/JPEG</span>
                                <span class="format-badge">PNG</span>
                                <span class="format-badge">GIF</span>
                                <span class="format-badge">WebP</span>
                                <span class="format-badge">SVG</span>
                                <span class="format-badge">MP4</span>
                                <span class="format-badge">MPEG</span>
                                <span class="format-badge">WebM</span>
                                <span class="format-badge">MP3</span>
                                <span class="format-badge">WAV</span>
                                <span class="format-badge">PDF</span>
                                <span class="format-badge">DOC/DOCX</span>
                                <span class="format-badge">XLS/XLSX</span>
                                <span class="format-badge">PPT/PPTX</span>
                                <span class="format-badge">ZIP</span>
                                <span class="format-badge">RAR</span>
                                <span class="format-badge">TXT</span>
                                <span class="format-badge">CSV</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Settings -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="visibility" class="form-label">Visibility</label>
                            <select class="form-select" id="visibility" name="visibility">
                                <option value="public" <?php echo $notification['visibility'] == 'public' ? 'selected' : ''; ?>>
                                    <i class="fas fa-globe me-2"></i>Public (Visible to everyone)
                                </option>
                                <option value="private" <?php echo $notification['visibility'] == 'private' ? 'selected' : ''; ?>>
                                    <i class="fas fa-lock me-2"></i>Private (Staff only)
                                </option>
                            </select>
                            <div class="form-text">Choose who can see this notification</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="priority" class="form-label">Priority Level</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="normal" <?php echo $notification['priority'] == 'normal' ? 'selected' : ''; ?>>
                                    <i class="fas fa-circle me-2" style="color: #3B9DB3;"></i>Normal
                                </option>
                                <option value="important" <?php echo $notification['priority'] == 'important' ? 'selected' : ''; ?>>
                                    <i class="fas fa-circle me-2" style="color: #dc3545;"></i>Important
                                </option>
                                <option value="starred" <?php echo $notification['priority'] == 'starred' ? 'selected' : ''; ?>>
                                    <i class="fas fa-circle me-2" style="color: #FFD700;"></i>Starred
                                </option>
                            </select>
                            <div class="form-text">Set the importance level of this notification</div>
                        </div>
                    </div>
                    
                    <!-- Preview Section -->
                    <div class="preview-section">
                        <div class="preview-title">
                            <span><i class="fas fa-eye me-2"></i>Preview</span>
                            <small class="text-muted">How your changes will look</small>
                        </div>
                        <div class="notification-meta">
                            <div><strong>Last Updated:</strong> <?php echo date('M d, Y h:i A', strtotime($notification['updated_at'])); ?></div>
                            <div><strong>Original Created:</strong> <?php echo date('M d, Y', strtotime($notification['created_at'])); ?></div>
                        </div>
                        <div class="mb-3">
                            <strong>Title:</strong> <span id="previewTitle"><?php echo htmlspecialchars($notification['title']); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Description:</strong> <span id="previewDescription"><?php echo nl2br(htmlspecialchars($notification['description'])); ?></span>
                        </div>
                        <div>
                            <strong>Settings:</strong> 
                            <span id="previewVisibility" class="badge <?php echo $notification['visibility'] == 'public' ? 'bg-success' : 'bg-warning'; ?> me-2">
                                <?php echo ucfirst($notification['visibility']); ?>
                            </span>
                            <span id="previewPriority" class="badge <?php echo $notification['priority'] == 'normal' ? 'bg-info' : ($notification['priority'] == 'important' ? 'bg-danger' : 'bg-warning'); ?>">
                                <?php echo ucfirst($notification['priority']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-5 pt-4 border-top">
                        <div>
                            <a href="notifications.php" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <a href="notifications.php?action=delete&id=<?php echo $notification['id']; ?>" 
                               class="btn btn-outline-danger"
                               onclick="return confirmDelete(event, '<?php echo htmlspecialchars(addslashes($notification['title'])); ?>')">
                                <i class="fas fa-trash-alt me-2"></i>Delete Notification
                            </a>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 for confirmation dialogs -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // File upload handling
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('attachment');
            const fileInfo = document.getElementById('fileInfo');
            const fileError = document.getElementById('fileError');
            const removeAttachmentCheckbox = document.getElementById('removeAttachment');
            
            // Click to upload
            if (uploadArea) {
                uploadArea.addEventListener('click', () => fileInput.click());
                
                // Drag and drop
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, preventDefaults, false);
                });
                
                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                ['dragenter', 'dragover'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, highlight, false);
                });
                
                ['dragleave', 'drop'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, unhighlight, false);
                });
                
                function highlight() {
                    uploadArea.classList.add('dragover');
                }
                
                function unhighlight() {
                    uploadArea.classList.remove('dragover');
                }
                
                // Handle file drop
                uploadArea.addEventListener('drop', handleDrop, false);
                
                function handleDrop(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    fileInput.files = files;
                    handleFileChange(files);
                }
                
                // Handle file selection
                fileInput.addEventListener('change', function() {
                    handleFileChange(this.files);
                });
                
                function handleFileChange(files) {
                    if (files.length > 0) {
                        const file = files[0];
                        const maxSize = 5 * 1024 * 1024; // 5MB
                        
                        if (file.size > maxSize) {
                            fileError.textContent = 'File size must be less than 5MB';
                            fileError.classList.remove('d-none');
                            fileInput.value = '';
                            fileInfo.innerHTML = '';
                            return;
                        }
                        
                        // Clear error
                        fileError.classList.add('d-none');
                        
                        // Uncheck remove attachment if new file is uploaded
                        if (removeAttachmentCheckbox) {
                            removeAttachmentCheckbox.checked = false;
                        }
                        
                        // Get file icon and preview
                        let fileIcon = 'fa-file';
                        let previewHTML = '';
                        
                        if (file.type.startsWith('image/')) {
                            fileIcon = 'fa-file-image';
                            previewHTML = `
                                <div class="file-preview mt-3">
                                    <img src="${URL.createObjectURL(file)}" alt="Preview" class="img-fluid">
                                </div>
                            `;
                        } else if (file.type.startsWith('video/')) {
                            fileIcon = 'fa-file-video';
                            previewHTML = `
                                <div class="file-preview mt-3">
                                    <video controls class="w-100">
                                        <source src="${URL.createObjectURL(file)}" type="${file.type}">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                            `;
                        } else if (file.type.startsWith('audio/')) {
                            fileIcon = 'fa-file-audio';
                            previewHTML = `
                                <div class="file-preview text-center mt-3">
                                    <i class="fas ${fileIcon} fa-3x text-primary mb-2"></i>
                                    <audio controls class="w-100">
                                        <source src="${URL.createObjectURL(file)}" type="${file.type}">
                                        Your browser does not support the audio element.
                                    </audio>
                                </div>
                            `;
                        } else if (file.type.includes('pdf')) {
                            fileIcon = 'fa-file-pdf';
                        } else if (file.type.includes('zip') || file.type.includes('rar')) {
                            fileIcon = 'fa-file-archive';
                        } else if (file.type.includes('word') || file.type.includes('document')) {
                            fileIcon = 'fa-file-word';
                        } else if (file.type.includes('excel') || file.type.includes('spreadsheet')) {
                            fileIcon = 'fa-file-excel';
                        } else if (file.type.includes('powerpoint') || file.type.includes('presentation')) {
                            fileIcon = 'fa-file-powerpoint';
                        }
                        
                        fileInfo.innerHTML = `
                            <div class="current-file-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <i class="fas ${fileIcon} me-3 fa-2x text-success"></i>
                                        <div>
                                            <strong>New file: ${file.name}</strong>
                                            <div class="text-muted">${formatFileSize(file.size)} • ${file.type}</div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-file-btn" onclick="clearNewFile()">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                </div>
                                ${previewHTML}
                            </div>
                        `;
                    }
                }
            }
            
            // Handle remove attachment checkbox
            if (removeAttachmentCheckbox) {
                removeAttachmentCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        // Clear new file upload if remove is checked
                        clearNewFile();
                        // Show warning
                        Swal.fire({
                            icon: 'warning',
                            title: 'Attachment will be removed',
                            text: 'The current file will be deleted when you save changes.',
                            confirmButtonColor: '#ffc107',
                            confirmButtonText: 'I understand'
                        });
                    }
                });
            }
            
            // Form validation
            const form = document.getElementById('editNotificationForm');
            form.addEventListener('submit', function(e) {
                const title = document.getElementById('title');
                
                if (!title.value.trim()) {
                    e.preventDefault();
                    title.focus();
                    Swal.fire({
                        icon: 'error',
                        title: 'Title Required',
                        text: 'Please enter a title for your notification',
                        confirmButtonColor: '#d33'
                    });
                    return;
                }
                
                // Show confirmation dialog
                e.preventDefault();
                
                Swal.fire({
                    title: 'Save Changes?',
                    html: `Are you sure you want to update this notification?<br><br>
                          <strong>Title:</strong> ${title.value}<br>
                          <strong>Visibility:</strong> ${document.getElementById('visibility').options[document.getElementById('visibility').selectedIndex].text}<br>
                          <strong>Priority:</strong> ${document.getElementById('priority').options[document.getElementById('priority').selectedIndex].text}`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Save Changes',
                    cancelButtonText: 'Review Again',
                    confirmButtonColor: '#3B9DB3',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Saving Changes...',
                            text: 'Please wait while we update your notification',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Submit the form
                        form.submit();
                    }
                });
            });
            
            // Initialize preview
            updatePreview();
        });
        
        function clearNewFile() {
            const fileInput = document.getElementById('attachment');
            const fileInfo = document.getElementById('fileInfo');
            const fileError = document.getElementById('fileError');
            
            fileInput.value = '';
            fileInfo.innerHTML = '';
            fileError.classList.add('d-none');
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function updatePreview() {
            const title = document.getElementById('title').value;
            const description = document.getElementById('description').value;
            const visibility = document.getElementById('visibility').value;
            const priority = document.getElementById('priority').value;
            
            // Update preview content
            document.getElementById('previewTitle').textContent = title || '[No title yet]';
            document.getElementById('previewDescription').innerHTML = description ? description.replace(/\n/g, '<br>') : '[No description]';
            
            // Update visibility badge
            const visibilityBadge = document.getElementById('previewVisibility');
            visibilityBadge.textContent = visibility.charAt(0).toUpperCase() + visibility.slice(1);
            visibilityBadge.className = visibility === 'public' ? 'badge bg-success me-2' : 'badge bg-warning me-2';
            
            // Update priority badge
            const priorityBadge = document.getElementById('previewPriority');
            priorityBadge.textContent = priority.charAt(0).toUpperCase() + priority.slice(1);
            if (priority === 'normal') {
                priorityBadge.className = 'badge bg-info';
            } else if (priority === 'important') {
                priorityBadge.className = 'badge bg-danger';
            } else {
                priorityBadge.className = 'badge bg-warning';
            }
        }
        
        // Update preview when settings change
        document.getElementById('visibility').addEventListener('change', updatePreview);
        document.getElementById('priority').addEventListener('change', updatePreview);
        
        // Confirm delete
        function confirmDelete(event, title) {
            event.preventDefault();
            
            Swal.fire({
                title: 'Delete Notification?',
                html: `Are you sure you want to delete "<strong>${title}</strong>"?<br><br>
                      <span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>This action cannot be undone.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Delete It',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = event.target.href;
                }
            });
            
            return false;
        }
        
        // Warn before leaving page with unsaved changes
        let formChanged = false;
        const originalTitle = "<?php echo htmlspecialchars($notification['title']); ?>";
        const originalDescription = "<?php echo htmlspecialchars($notification['description']); ?>";
        const originalVisibility = "<?php echo $notification['visibility']; ?>";
        const originalPriority = "<?php echo $notification['priority']; ?>";
        
        const formInputs = document.querySelectorAll('#editNotificationForm input, #editNotificationForm textarea, #editNotificationForm select');
        
        function checkForChanges() {
            const currentTitle = document.getElementById('title').value;
            const currentDescription = document.getElementById('description').value;
            const currentVisibility = document.getElementById('visibility').value;
            const currentPriority = document.getElementById('priority').value;
            const hasFile = document.getElementById('attachment').files.length > 0;
            const removeChecked = document.getElementById('removeAttachment') ? document.getElementById('removeAttachment').checked : false;
            
            formChanged = (
                currentTitle !== originalTitle ||
                currentDescription !== originalDescription ||
                currentVisibility !== originalVisibility ||
                currentPriority !== originalPriority ||
                hasFile ||
                removeChecked
            );
        }
        
        formInputs.forEach(input => {
            input.addEventListener('input', checkForChanges);
            input.addEventListener('change', checkForChanges);
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
        
        // Clear form changed flag on submit
        document.getElementById('editNotificationForm').addEventListener('submit', () => {
            formChanged = false;
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.getElementById('editNotificationForm').dispatchEvent(new Event('submit'));
            }
            
            // Escape to go back
            if (e.key === 'Escape') {
                if (formChanged) {
                    if (confirm('You have unsaved changes. Are you sure you want to leave?')) {
                        window.location.href = 'notifications.php';
                    }
                } else {
                    window.location.href = 'notifications.php';
                }
            }
        });
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>