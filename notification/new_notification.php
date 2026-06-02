<?php
// new_notification.php - WITH SCHOOL ID FILTERING
session_start();
require_once '../controller/db_connect.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../controller/login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$is_admin = false;

// ========== GET CURRENT SCHOOL ID AND SCHOOL CODE ==========
$school_query = "SELECT a.school_id, s.school_code 
                 FROM admins a 
                 JOIN schools s ON a.school_id = s.id 
                 WHERE a.id = ?";
$stmt = $conn->prepare($school_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$school_result = $stmt->get_result();
$current_admin_data = $school_result->fetch_assoc();
$current_school_id = $current_admin_data['school_id'] ?? 1;
$current_school_code = $current_admin_data['school_code'] ?? 'MVZ001';

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
    $is_admin = in_array('Head Master', $admin_roles) || in_array('Second Master', $admin_roles);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $visibility = mysqli_real_escape_string($conn, $_POST['visibility'] ?? 'public');
    $priority = mysqli_real_escape_string($conn, $_POST['priority'] ?? 'normal');
    
    // Validate required fields
    if (empty($title)) {
        $_SESSION['error'] = "Notification title is required.";
        header("Location: new_notification.php");
        exit();
    }
    
    // Initialize file variables as NULL
    $file_path = null;
    $file_name = null;
    $file_type = null;
    $file_size = null;
    
    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0 && $_FILES['attachment']['size'] > 0) {
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
            // Create uploads directory if it doesn't exist
            $upload_dir = '../uploads/notifications/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $_SESSION['error'] = "Failed to create upload directory. Please check permissions.";
                    header("Location: new_notification.php");
                    exit();
                }
            }
            
            // Check if directory is writable
            if (!is_writable($upload_dir)) {
                $_SESSION['error'] = "Upload directory is not writable. Please set permissions to 755 or 777.";
                header("Location: new_notification.php");
                exit();
            }
            
            // Generate unique filename
            $safe_filename = preg_replace('/[^a-zA-Z0-9.-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . $safe_filename . '.' . $file_ext;
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
                header("Location: new_notification.php");
                exit();
            }
        } else {
            if (!in_array($file['type'], $allowed_types)) {
                $_SESSION['error'] = "File type not allowed. Allowed types: Images, Videos, Audio, PDF, Office documents, ZIP, TXT, CSV";
            } else {
                $_SESSION['error'] = "File size must be less than 5MB";
            }
            header("Location: new_notification.php");
            exit();
        }
    }
    
    // Prepare SQL with proper NULL handling - ADDED school_id
    $file_path_sql = ($file_path !== null) ? "'" . mysqli_real_escape_string($conn, $file_path) . "'" : "NULL";
    $file_name_sql = ($file_name !== null) ? "'" . mysqli_real_escape_string($conn, $file_name) . "'" : "NULL";
    $file_type_sql = ($file_type !== null) ? "'" . mysqli_real_escape_string($conn, $file_type) . "'" : "NULL";
    $file_size_sql = ($file_size !== null && $file_size > 0) ? intval($file_size) : "NULL";
    
    // Build INSERT query with school_id
    $sql = "INSERT INTO notifications (admin_id, title, description, file_path, file_type, file_name, file_size, visibility, priority, school_id) 
            VALUES (
                $admin_id, 
                '$title', 
                '$description', 
                $file_path_sql, 
                $file_type_sql, 
                $file_name_sql, 
                $file_size_sql, 
                '$visibility', 
                '$priority',
                $current_school_id
            )";
    
    // Log the query for debugging (remove in production)
    error_log("SQL Query: " . $sql);
    
    // Execute query
    if (mysqli_query($conn, $sql)) {
        // Get the inserted notification ID
        $notification_id = mysqli_insert_id($conn);
        
        // Update last notification check for all admins in this school to mark as unread
        mysqli_query($conn, "UPDATE admins SET last_notification_check = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE school_id = $current_school_id");
        
        $_SESSION['success'] = "Notification created successfully!";
        header("Location: notifications.php");
        exit();
    } else {
        // Log the exact error
        $error = mysqli_error($conn);
        error_log("MySQL Error in new_notification.php: " . $error);
        error_log("Failed SQL: " . $sql);
        
        $_SESSION['error'] = "Error creating notification: " . $error;
        header("Location: new_notification.php");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Notification - School System</title>
    
    <!-- Include header -->
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>
    
    <!-- Additional CSS for new notification page -->
    <style>
        .create-notification-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            background: lightgrey;
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
            padding: 40px 20px;
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
            font-size: 3rem;
            color: #3B9DB3;
            margin-bottom: 15px;
        }
        
        .file-info-card {
            background: #e8f4f8;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid #3B9DB3;
        }
        
        .file-preview {
            max-width: 100%;
            margin: 15px 0;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
            padding: 10px;
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
            display: none;
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
        }
        
        @media (max-width: 768px) {
            .create-notification-container {
                padding: 20px;
                margin: 10px;
            }
            
            .upload-area {
                padding: 30px 15px;
            }
            
            .upload-icon {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
  
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="page-title">
                    <i class="fas fa-bell me-2"></i>Create New Notification
                    <small class="text-muted ms-2">School: <?php echo htmlspecialchars($current_school_code); ?></small>
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

            <!-- Notification Form -->
            <div class="create-notification-container">
                <form method="POST" enctype="multipart/form-data" id="notificationForm">
                    <!-- Title -->
                    <div class="mb-4">
                        <label for="title" class="form-label required-field">Notification Title</label>
                        <input type="text" class="form-control form-control-lg" id="title" name="title" required
                               placeholder="Enter a clear and descriptive title for your notification"
                               maxlength="200" oninput="updatePreview()">
                        <div class="form-text">Maximum 200 characters. This will be the main heading of your notification.</div>
                    </div>
                    
                    <!-- Description -->
                    <div class="mb-4">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="5" placeholder="Add more details about this notification..."
                                  maxlength="1000" oninput="updatePreview()"></textarea>
                        <div class="form-text">Maximum 1000 characters. Provide additional information or instructions.</div>
                    </div>
                    
                    <!-- File Upload -->
                    <div class="mb-4">
                        <label class="form-label">Attachment (Optional, max 5MB)</label>
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <h4>Upload File</h4>
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
                                <option value="public">
                                    <i class="fas fa-globe me-2"></i>Public (Visible to everyone)
                                </option>
                                <option value="private">
                                    <i class="fas fa-lock me-2"></i>Private (Staff only)
                                </option>
                            </select>
                            <div class="form-text">Choose who can see this notification</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="priority" class="form-label">Priority Level</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="normal">
                                    <i class="fas fa-circle me-2" style="color: #3B9DB3;"></i>Normal
                                </option>
                                <option value="important">
                                    <i class="fas fa-circle me-2" style="color: #dc3545;"></i>Important
                                </option>
                                <option value="starred">
                                    <i class="fas fa-circle me-2" style="color: #FFD700;"></i>Starred
                                </option>
                            </select>
                            <div class="form-text">Set the importance level of this notification</div>
                        </div>
                    </div>
                    
                    <!-- Preview Section -->
                    <div class="preview-section" id="previewSection">
                        <div class="preview-title">
                            <i class="fas fa-eye me-2"></i>Preview
                        </div>
                        <div class="mb-2">
                            <strong>Title:</strong> <span id="previewTitle"></span>
                        </div>
                        <div class="mb-2">
                            <strong>Description:</strong> <span id="previewDescription"></span>
                        </div>
                        <div>
                            <strong>Settings:</strong> 
                            <span id="previewVisibility" class="badge bg-info me-2"></span>
                            <span id="previewPriority" class="badge bg-warning"></span>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-5 pt-4 border-top">
                        <a href="notifications.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>Publish Notification
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
            
            // Click to upload
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
                        showFilePreview(null);
                        return;
                    }
                    
                    // Clear error
                    fileError.classList.add('d-none');
                    
                    // Get file icon and preview
                    let fileIcon = 'fa-file';
                    let previewHTML = '';
                    
                    if (file.type.startsWith('image/')) {
                        fileIcon = 'fa-file-image';
                        previewHTML = `
                            <div class="file-preview">
                                <img src="${URL.createObjectURL(file)}" alt="Preview" class="img-fluid">
                            </div>
                        `;
                    } else if (file.type.startsWith('video/')) {
                        fileIcon = 'fa-file-video';
                        previewHTML = `
                            <div class="file-preview">
                                <video controls class="w-100">
                                    <source src="${URL.createObjectURL(file)}" type="${file.type}">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        `;
                    } else if (file.type.startsWith('audio/')) {
                        fileIcon = 'fa-file-audio';
                        previewHTML = `
                            <div class="file-preview text-center">
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
                        <div class="file-info-card">
                            <div class="d-flex align-items-center">
                                <i class="fas ${fileIcon} me-3 fa-2x"></i>
                                <div class="flex-grow-1">
                                    <strong>${file.name}</strong>
                                    <div class="text-muted">${formatFileSize(file.size)} • ${file.type}</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFile()">
                                    <i class="fas fa-times"></i> Remove
                                </button>
                            </div>
                            ${previewHTML}
                        </div>
                    `;
                    
                    showFilePreview(previewHTML);
                }
            }
            
            // Form validation
            const form = document.getElementById('notificationForm');
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
                    title: 'Ready to Publish?',
                    html: `Are you sure you want to publish this notification?<br><br>
                          <strong>Title:</strong> ${title.value}<br>
                          <strong>Visibility:</strong> ${document.getElementById('visibility').options[document.getElementById('visibility').selectedIndex].text}<br>
                          <strong>Priority:</strong> ${document.getElementById('priority').options[document.getElementById('priority').selectedIndex].text}`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Publish It!',
                    cancelButtonText: 'Review Again',
                    confirmButtonColor: '#3B9DB3',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Publishing...',
                            text: 'Please wait while we publish your notification',
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
        
        function clearFile() {
            const fileInput = document.getElementById('attachment');
            const fileInfo = document.getElementById('fileInfo');
            const fileError = document.getElementById('fileError');
            
            fileInput.value = '';
            fileInfo.innerHTML = '';
            fileError.classList.add('d-none');
            showFilePreview(null);
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function showFilePreview(previewHTML) {
            // This function can be expanded to show file preview in a modal or separate section
            console.log('File preview:', previewHTML ? 'shown' : 'hidden');
        }
        
        function updatePreview() {
            const title = document.getElementById('title').value;
            const description = document.getElementById('description').value;
            const visibility = document.getElementById('visibility').value;
            const priority = document.getElementById('priority').value;
            const previewSection = document.getElementById('previewSection');
            
            // Update preview content
            document.getElementById('previewTitle').textContent = title || '[No title yet]';
            document.getElementById('previewDescription').textContent = description || '[No description]';
            
            // Update visibility badge
            const visibilityBadge = document.getElementById('previewVisibility');
            if (visibility === 'public') {
                visibilityBadge.textContent = 'Public';
                visibilityBadge.className = 'badge bg-success me-2';
            } else {
                visibilityBadge.textContent = 'Private';
                visibilityBadge.className = 'badge bg-warning me-2';
            }
            
            // Update priority badge
            const priorityBadge = document.getElementById('previewPriority');
            if (priority === 'normal') {
                priorityBadge.textContent = 'Normal';
                priorityBadge.className = 'badge bg-info';
            } else if (priority === 'important') {
                priorityBadge.textContent = 'Important';
                priorityBadge.className = 'badge bg-danger';
            } else {
                priorityBadge.textContent = 'Starred';
                priorityBadge.className = 'badge bg-warning';
            }
            
            // Show/hide preview section
            if (title || description) {
                previewSection.style.display = 'block';
            } else {
                previewSection.style.display = 'none';
            }
        }
        
        // Update preview when settings change
        document.getElementById('visibility').addEventListener('change', updatePreview);
        document.getElementById('priority').addEventListener('change', updatePreview);
        
        // Warn before leaving page with unsaved changes
        let formChanged = false;
        const formInputs = document.querySelectorAll('#notificationForm input, #notificationForm textarea, #notificationForm select');
        
        formInputs.forEach(input => {
            input.addEventListener('input', () => formChanged = true);
            input.addEventListener('change', () => formChanged = true);
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
        
        // Clear form changed flag on submit
        document.getElementById('notificationForm').addEventListener('submit', () => {
            formChanged = false;
        });
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>