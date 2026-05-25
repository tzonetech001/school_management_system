<?php
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Load user's theme settings
$colors = [];
$preferences = [];

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

// Default theme colors
$default_colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
    'primary_light' => '#8bc5d6',
    'success' => '#28a745',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#17a2b8',
    'coral' => '#FF7F50',
    'forest_green' => '#2E7D32',
    'lime_green' => '#63E07E',
    'sky_blue' => '#66d9ff',
    'aqua_blue' => '#4dd2ff'
];

// Merge colors with defaults
foreach ($default_colors as $key => $value) {
    if (!isset($colors[$key])) {
        $colors[$key] = $value;
    }
}

// PS Theme Colors
$ps_primary = '#9C27B0';
$ps_primary_dark = '#7B1FA2';
$ps_primary_light = '#E1BEE7';
$ps_accent = '#FF9800';

// Get current user info with roles
$user_sql = "SELECT a.*, 
            GROUP_CONCAT(DISTINCT ar.role_name ORDER BY ara.is_primary DESC, ar.role_name SEPARATOR ', ') as roles,
            GROUP_CONCAT(DISTINCT CASE WHEN ara.is_primary = 1 THEN ar.role_name END) as primary_role
            FROM admins a
            LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
            LEFT JOIN admin_roles ar ON ara.role_id = ar.id
            WHERE a.id = $admin_id
            GROUP BY a.id";
$user_result = mysqli_query($conn, $user_sql);
$current_user = mysqli_fetch_assoc($user_result);

$is_ps = strpos($current_user['roles'], 'PS') !== false;
$user_fullname = $current_user['first_name'] . ' ' . $current_user['last_name'];
$user_role = $current_user['primary_role'] ?: 'Staff';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'upload_document') {
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $short_note = mysqli_real_escape_string($conn, $_POST['short_note']);
        $visibility = mysqli_real_escape_string($conn, $_POST['visibility']);
        $allow_feedback = isset($_POST['allow_feedback']) ? 1 : 0;
        $needs_ps_review = isset($_POST['needs_ps_review']) ? 1 : 0;
        
        // Handle file upload
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
            $upload_dir = '../uploads/ps_documents/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file = $_FILES['document_file'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Generate unique filename
            $new_file_name = 'ps_' . time() . '_' . uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $new_file_name;
            
            // Determine file type
            $file_type = 'other';
            $image_ext = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
            $video_ext = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'];
            $audio_ext = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];
            $document_ext = ['doc', 'docx', 'txt', 'rtf', 'odt'];
            $spreadsheet_ext = ['xls', 'xlsx', 'csv', 'ods'];
            $presentation_ext = ['ppt', 'pptx', 'odp'];
            $pdf_ext = ['pdf'];
            $archive_ext = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'];
            
            if (in_array($file_ext, $image_ext)) $file_type = 'image';
            elseif (in_array($file_ext, $video_ext)) $file_type = 'video';
            elseif (in_array($file_ext, $audio_ext)) $file_type = 'audio';
            elseif (in_array($file_ext, $document_ext)) $file_type = 'document';
            elseif (in_array($file_ext, $spreadsheet_ext)) $file_type = 'spreadsheet';
            elseif (in_array($file_ext, $presentation_ext)) $file_type = 'presentation';
            elseif (in_array($file_ext, $pdf_ext)) $file_type = 'pdf';
            elseif (in_array($file_ext, $archive_ext)) $file_type = 'archive';
            
            // Get mime type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                // Determine initial status
                $status = 'active';
                $ps_status = 'pending';
                
                if ($needs_ps_review && !$is_ps) {
                    $ps_status = 'pending';
                } elseif ($needs_ps_review && $is_ps) {
                    $ps_status = 'approved';
                } else {
                    $ps_status = 'approved';
                }
                
                $insert_sql = "INSERT INTO ps_documents 
                              (title, short_note, file_name, file_path, file_type, file_extension, 
                               file_size, mime_type, uploaded_by, uploader_name, uploader_role, 
                               visibility, allow_feedback, needs_ps_review, ps_status, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("sssssssisssssiss", 
                    $title, $short_note, $file_name, $file_path, $file_type, $file_ext,
                    $file_size, $mime_type, $admin_id, $user_fullname, $user_role,
                    $visibility, $allow_feedback, $needs_ps_review, $ps_status, $status
                );
                
                if ($stmt->execute()) {
                    $doc_id = $conn->insert_id;
                    
                    // Create notification for PS if needed
                    if ($needs_ps_review && !$is_ps) {
                        $notification_title = "New Document Requires Review";
                        $notification_message = "$user_fullname uploaded a document '$title' that needs your review.";
                        
                        $notify_sql = "INSERT INTO ps_notifications 
                                      (title, message, type, target_role, document_id, created_by, status) 
                                      VALUES (?, ?, 'ps_review', 'PS', ?, ?, 'unread')";
                        $notify_stmt = $conn->prepare($notify_sql);
                        $notify_stmt->bind_param("ssii", $notification_title, $notification_message, $doc_id, $admin_id);
                        $notify_stmt->execute();
                    }
                    
                    $_SESSION['upload_response'] = ['success' => true, 'message' => 'Document uploaded successfully!'];
                } else {
                    $_SESSION['upload_response'] = ['success' => false, 'message' => 'Database error: ' . $conn->error];
                }
                $stmt->close();
            } else {
                $_SESSION['upload_response'] = ['success' => false, 'message' => 'Failed to upload file. Check folder permissions.'];
            }
        } else {
            $error_message = 'No file uploaded';
            if (isset($_FILES['document_file'])) {
                switch ($_FILES['document_file']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        $error_message = 'File exceeds upload_max_filesize directive in php.ini';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = 'File exceeds MAX_FILE_SIZE directive in HTML form';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = 'File was only partially uploaded';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message = 'No file was uploaded';
                        break;
                    default:
                        $error_message = 'Unknown upload error';
                }
            }
            $_SESSION['upload_response'] = ['success' => false, 'message' => $error_message];
        }
        
        header("Location: ps.php");
        exit();
    }
    
    // Handle document update
    if ($_POST['action'] == 'update_document' && isset($_POST['doc_id'])) {
        $doc_id = intval($_POST['doc_id']);
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $short_note = mysqli_real_escape_string($conn, $_POST['short_note']);
        $visibility = mysqli_real_escape_string($conn, $_POST['visibility']);
        $allow_feedback = isset($_POST['allow_feedback']) ? 1 : 0;
        
        // Check if user can update this document
        $check_sql = "SELECT uploaded_by, ps_status FROM ps_documents WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $doc_id);
        $check_stmt->execute();
        $doc_result = $check_stmt->get_result();
        $doc_data = $doc_result->fetch_assoc();
        
        if ($doc_data && ($is_ps || $doc_data['uploaded_by'] == $admin_id)) {
            $update_sql = "UPDATE ps_documents SET title = ?, short_note = ?, visibility = ?, allow_feedback = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssii", $title, $short_note, $visibility, $allow_feedback, $doc_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['upload_response'] = ['success' => true, 'message' => 'Document updated successfully!'];
            } else {
                $_SESSION['upload_response'] = ['success' => false, 'message' => 'Update failed'];
            }
            $update_stmt->close();
        } else {
            $_SESSION['upload_response'] = ['success' => false, 'message' => 'You don\'t have permission to update this document'];
        }
        $check_stmt->close();
        
        header("Location: ps.php");
        exit();
    }
    
    // Handle PS Review Response
    if ($_POST['action'] == 'ps_review' && isset($_POST['doc_id']) && $is_ps) {
        $doc_id = intval($_POST['doc_id']);
        $ps_status = mysqli_real_escape_string($conn, $_POST['ps_status']);
        $ps_comment = mysqli_real_escape_string($conn, $_POST['ps_comment']);
        
        $update_sql = "UPDATE ps_documents SET ps_status = ?, ps_reviewed_by = ?, ps_reviewed_at = NOW(), ps_comment = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sisi", $ps_status, $admin_id, $ps_comment, $doc_id);
        
        if ($update_stmt->execute()) {
            // Get document info for notification
            $doc_sql = "SELECT title, uploaded_by FROM ps_documents WHERE id = ?";
            $doc_stmt = $conn->prepare($doc_sql);
            $doc_stmt->bind_param("i", $doc_id);
            $doc_stmt->execute();
            $doc_info = $doc_stmt->get_result()->fetch_assoc();
            
            // Notify uploader
            $notification_title = "Document Review Complete";
            $notification_message = "Your document '{$doc_info['title']}' has been reviewed by PS. Status: " . ucfirst($ps_status);
            
            $notify_sql = "INSERT INTO ps_notifications 
                          (title, message, type, user_id, document_id, created_by, status) 
                          VALUES (?, ?, 'document_review', ?, ?, ?, 'unread')";
            $notify_stmt = $conn->prepare($notify_sql);
            $notify_stmt->bind_param("ssiii", $notification_title, $notification_message, $doc_info['uploaded_by'], $doc_id, $admin_id);
            $notify_stmt->execute();
            
            $_SESSION['upload_response'] = ['success' => true, 'message' => 'Review submitted successfully!'];
        } else {
            $_SESSION['upload_response'] = ['success' => false, 'message' => 'Review failed'];
        }
        
        header("Location: ps.php");
        exit();
    }
}

// Handle document deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $doc_id = intval($_GET['delete']);
    
    // Check if user can delete this document
    $check_sql = "SELECT uploaded_by, file_path FROM ps_documents WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $doc_id);
    $check_stmt->execute();
    $doc_result = $check_stmt->get_result();
    $doc_data = $doc_result->fetch_assoc();
    
    if ($doc_data && ($is_ps || $doc_data['uploaded_by'] == $admin_id)) {
        // Delete physical file
        if (file_exists($doc_data['file_path'])) {
            unlink($doc_data['file_path']);
        }
        
        // Delete from database
        $delete_sql = "DELETE FROM ps_documents WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $doc_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['upload_response'] = ['success' => true, 'message' => 'Document deleted successfully!'];
        } else {
            $_SESSION['upload_response'] = ['success' => false, 'message' => 'Delete failed'];
        }
        $delete_stmt->close();
    } else {
        $_SESSION['upload_response'] = ['success' => false, 'message' => 'You don\'t have permission to delete this document'];
    }
    $check_stmt->close();
    
    header("Location: ps.php");
    exit();
}

// Handle document download
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $doc_id = intval($_GET['download']);
    
    $sql = "SELECT * FROM ps_documents WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doc = $result->fetch_assoc();
    
    if ($doc && file_exists($doc['file_path'])) {
        // Log download
        $log_sql = "INSERT INTO ps_document_logs (document_id, user_id, user_name, user_role, action, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, 'download', ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $ip = $_SERVER['REMOTE_ADDR'];
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $log_stmt->bind_param("iissss", $doc_id, $admin_id, $user_fullname, $user_role, $ip, $ua);
        $log_stmt->execute();
        
        // Update download count
        $update_sql = "UPDATE ps_documents SET download_count = download_count + 1 WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $doc_id);
        $update_stmt->execute();
        
        // Force download
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Disposition: attachment; filename="' . basename($doc['file_name']) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($doc['file_path']));
        readfile($doc['file_path']);
        exit();
    } else {
        $_SESSION['upload_response'] = ['success' => false, 'message' => 'File not found'];
        header("Location: ps.php");
        exit();
    }
}

// Handle document view
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $doc_id = intval($_GET['view']);
    
    $sql = "SELECT * FROM ps_documents WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doc = $result->fetch_assoc();
    
    if ($doc && file_exists($doc['file_path'])) {
        // Log view
        $log_sql = "INSERT INTO ps_document_logs (document_id, user_id, user_name, user_role, action, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, 'view', ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $ip = $_SERVER['REMOTE_ADDR'];
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $log_stmt->bind_param("iissss", $doc_id, $admin_id, $user_fullname, $user_role, $ip, $ua);
        $log_stmt->execute();
        
        // Update view count
        $update_sql = "UPDATE ps_documents SET view_count = view_count + 1 WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $doc_id);
        $update_stmt->execute();
        
        // Display file in browser
        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Disposition: inline; filename="' . basename($doc['file_name']) . '"');
        readfile($doc['file_path']);
        exit();
    } else {
        $_SESSION['upload_response'] = ['success' => false, 'message' => 'File not found'];
        header("Location: ps.php");
        exit();
    }
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'add_feedback') {
    header('Content-Type: application/json');
    
    $document_id = intval($_POST['document_id']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    
    // Check if document allows feedback
    $check_sql = "SELECT allow_feedback, uploaded_by, title FROM ps_documents WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $document_id);
    $check_stmt->execute();
    $doc_result = $check_stmt->get_result();
    $doc = $doc_result->fetch_assoc();
    
    if ($doc && $doc['allow_feedback']) {
        $insert_sql = "INSERT INTO ps_document_feedback 
                      (document_id, commenter_id, commenter_name, commenter_role, comment, parent_comment_id) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iisssi", $document_id, $admin_id, $user_fullname, $user_role, $comment, $parent_id);
        
        if ($insert_stmt->execute()) {
            // Update feedback count
            $update_sql = "UPDATE ps_documents SET feedback_count = feedback_count + 1 WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $document_id);
            $update_stmt->execute();
            
            // Log feedback
            $log_sql = "INSERT INTO ps_document_logs (document_id, user_id, user_name, user_role, action, ip_address, user_agent) 
                        VALUES (?, ?, ?, ?, 'feedback', ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $ip = $_SERVER['REMOTE_ADDR'];
            $ua = $_SERVER['HTTP_USER_AGENT'];
            $log_stmt->bind_param("iissss", $document_id, $admin_id, $user_fullname, $user_role, $ip, $ua);
            $log_stmt->execute();
            
            // Notify document owner if comment is not from them
            if ($doc['uploaded_by'] != $admin_id) {
                $notify_title = "New Feedback on Your Document";
                $notify_message = "$user_fullname commented on your document '{$doc['title']}'";
                
                $notify_sql = "INSERT INTO ps_notifications 
                              (title, message, type, user_id, document_id, created_by, status) 
                              VALUES (?, ?, 'feedback', ?, ?, ?, 'unread')";
                $notify_stmt = $conn->prepare($notify_sql);
                $notify_stmt->bind_param("ssiii", $notify_title, $notify_message, $doc['uploaded_by'], $document_id, $admin_id);
                $notify_stmt->execute();
            }
            
            echo json_encode(['success' => true, 'message' => 'Feedback added successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add feedback']);
        }
        $insert_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Feedback is not allowed for this document']);
    }
    exit();
}

// Get documents based on user role
$sql = "SELECT d.*, a.first_name, a.last_name, a.profile_image 
        FROM ps_documents d
        LEFT JOIN admins a ON d.uploaded_by = a.id
        WHERE d.status = 'active' ";

if (!$is_ps) {
    $sql .= "AND (d.visibility = 'public' OR (d.visibility = 'staff_only' AND d.uploaded_by = $admin_id) OR d.uploaded_by = $admin_id)";
}

$sql .= " ORDER BY 
            CASE 
                WHEN d.needs_ps_review = 1 AND d.ps_status = 'pending' THEN 1 
                ELSE 2 
            END,
            d.created_at DESC";

$documents_result = mysqli_query($conn, $sql);
$documents = [];
$pending_reviews = [];
if ($documents_result && mysqli_num_rows($documents_result) > 0) {
    while ($row = mysqli_fetch_assoc($documents_result)) {
        $documents[] = $row;
        if ($row['needs_ps_review'] && $row['ps_status'] == 'pending') {
            $pending_reviews[] = $row;
        }
    }
}

// Get file type icons and colors
function getFileIcon($type) {
    $icons = [
        'image' => 'fa-image',
        'video' => 'fa-video',
        'audio' => 'fa-music',
        'document' => 'fa-file-word',
        'spreadsheet' => 'fa-file-excel',
        'presentation' => 'fa-file-powerpoint',
        'pdf' => 'fa-file-pdf',
        'archive' => 'fa-file-archive',
        'other' => 'fa-file'
    ];
    return $icons[$type] ?? 'fa-file';
}

function getFileColor($type) {
    $colors = [
        'image' => '#2196F3',
        'video' => '#9C27B0',
        'audio' => '#4CAF50',
        'document' => '#FF9800',
        'spreadsheet' => '#4CAF50',
        'presentation' => '#FF5722',
        'pdf' => '#F44336',
        'archive' => '#795548',
        'other' => '#757575'
    ];
    return $colors[$type] ?? '#757575';
}

function getPSStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending Review</span>';
        case 'approved':
            return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Approved</span>';
        case 'rejected':
            return '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Rejected</span>';
        case 'changes_requested':
            return '<span class="badge bg-info"><i class="fas fa-edit me-1"></i>Changes Requested</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}
?>

<?php include '../controller/header.php'; ?>
<?php 
// Calculate sidebar class
$sidebarClass = ($preferences['sidebar_collapsed'] == '1') ? 'sidebar-hidden' : '';
?>
<?php include '../controller/sidebar.php'; ?>

<!-- SweetAlert2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --ps-primary: <?php echo $ps_primary; ?>;
        --ps-primary-dark: <?php echo $ps_primary_dark; ?>;
        --ps-primary-light: <?php echo $ps_primary_light; ?>;
        --ps-accent: <?php echo $ps_accent; ?>;
        --primary-color: <?php echo $colors['primary']; ?>;
        --primary-dark: <?php echo $colors['primary_dark']; ?>;
        --primary-light: <?php echo $colors['primary_light']; ?>;
        --success-color: <?php echo $colors['success']; ?>;
        --danger-color: <?php echo $colors['danger']; ?>;
        --warning-color: <?php echo $colors['warning']; ?>;
        --info-color: <?php echo $colors['info']; ?>;
        --text-color: <?php echo $colors['text'] ?? '#333333'; ?>;
        --text-light: <?php echo $colors['text_light'] ?? '#666666'; ?>;
        --border-color: <?php echo $colors['border'] ?? '#e0e0e0'; ?>;
        --white: <?php echo $colors['white'] ?? '#ffffff'; ?>;
        --gray: <?php echo $colors['gray'] ?? '#e9ecef'; ?>;
        --font-size-base: <?php echo isset($preferences['font_size']) ? ($preferences['font_size'] . 'px') : '16px'; ?>;
        --animation-duration: <?php 
            $speed = isset($preferences['animation_speed']) ? $preferences['animation_speed'] : 'normal';
            echo $speed === 'slow' ? '0.5s' : ($speed === 'fast' ? '0.15s' : '0.3s'); 
        ?>;
        --spacing-base: <?php echo (isset($preferences['compact_mode']) && $preferences['compact_mode'] === '1') ? '0.75rem' : '1rem'; ?>;
    }

    * {
        transition: <?php echo (isset($preferences['animations']) && $preferences['animations'] === '1') ? 'all var(--animation-duration) ease' : 'none'; ?>;
    }

    body {
        font-size: var(--font-size-base);
        color: var(--text-color);
        background: <?php 
            if (isset($preferences['background_option']) && $preferences['background_option'] === 'image') {
                $opacity = isset($preferences['background_opacity']) ? $preferences['background_opacity'] / 100 : 0.65;
                echo "linear-gradient(rgba(255,255,255,{$opacity}), rgba(255,255,255,{$opacity})), url('../muyovozi.png') no-repeat center center fixed";
            } else {
                $bg_colors = [
                    'gray' => '#e9ecef',
                    'eye_care' => '#c7e9c0',
                    'milk' => '#fdf5e6',
                    'dark_light' => '#2d2d2d'
                ];
                $bg_option = isset($preferences['background_option']) ? $preferences['background_option'] : 'image';
                echo isset($bg_colors[$bg_option]) ? $bg_colors[$bg_option] : '#e9ecef';
            }
        ?>;
        background-size: <?php echo isset($preferences['background_option']) && $preferences['background_option'] === 'image' ? 'cover' : 'auto'; ?>;
        background-position: center;
        min-height: 100vh;
    }

    <?php if (isset($preferences['compact_mode']) && $preferences['compact_mode'] === '1'): ?>
    .card-body {
        padding: 0.75rem !important;
    }
    .btn {
        padding: 0.5rem 1rem !important;
    }
    .form-control, .form-select {
        padding: 0.375rem 0.75rem !important;
    }
    <?php endif; ?>

    .main-content {
        min-height: calc(100vh - 60px);
        padding: 20px;
        transition: margin-left var(--animation-duration) ease;
        margin-top: 5px;
    }

    @media (min-width: 992px) {
        .main-content {
            margin-left: 250px;
        }
        .main-content.sidebar-hidden {
            margin-left: 0;
        }
    }

    /* PS Theme Elements */
    .ps-badge {
        background: linear-gradient(135deg, var(--ps-primary), var(--ps-primary-dark));
        color: white;
        padding: 5px 15px;
        border-radius: 30px;
        font-size: 0.9rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .ps-badge i {
        font-size: 1rem;
    }

    .ps-gradient {
        background: linear-gradient(135deg, var(--ps-primary), var(--ps-primary-dark));
    }

    .ps-text {
        color: var(--ps-primary);
    }

    .ps-border {
        border-color: var(--ps-primary) !important;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: var(--white);
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
        border: 1px solid var(--border-color);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(156, 39, 176, 0.15);
        border-color: var(--ps-primary-light);
    }

    .stat-info h3 {
        font-size: 32px;
        font-weight: 700;
        color: var(--text-color);
        margin: 0 0 5px 0;
    }

    .stat-info p {
        font-size: 14px;
        color: var(--text-light);
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--ps-primary-light), var(--ps-primary));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
    }

    /* Upload Card */
    .upload-card {
        background: var(--white);
        border-radius: 24px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        border: 2px dashed var(--ps-primary-light);
        transition: all 0.3s ease;
    }

    .upload-card:hover {
        border-color: var(--ps-primary);
        box-shadow: 0 15px 50px rgba(156, 39, 176, 0.15);
    }

    .upload-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 25px;
    }

    .upload-header-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--ps-primary), var(--ps-primary-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
    }

    .upload-header h2 {
        font-size: 24px;
        font-weight: 600;
        color: var(--text-color);
        margin: 0;
    }

    /* File Input Styling */
    .file-input-wrapper {
        position: relative;
        margin-bottom: 15px;
    }

    .file-input-wrapper input[type="file"] {
        position: absolute;
        width: 0.1px;
        height: 0.1px;
        opacity: 0;
        overflow: hidden;
        z-index: -1;
    }

    .file-input-label {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 20px;
        background: var(--gray);
        border: 2px dashed var(--border-color);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .file-input-label:hover {
        border-color: var(--ps-primary);
        background: var(--ps-primary-light);
    }

    .file-input-label i {
        font-size: 24px;
        color: var(--ps-primary);
    }

    .file-input-label span {
        font-size: 16px;
        color: var(--text-light);
    }

    .file-info {
        margin-top: 10px;
        padding: 10px;
        background: var(--gray);
        border-radius: 8px;
        display: none;
    }

    .file-info.show {
        display: block;
    }

    .file-info-item {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--text-color);
    }

    .file-info-item i {
        color: var(--ps-primary);
    }

    /* Documents Header */
    .documents-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .documents-header h3 {
        font-size: 20px;
        font-weight: 600;
        color: var(--text-color);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .search-box {
        flex: 0 0 300px;
        position: relative;
    }

    .search-box i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-light);
    }

    .search-box input {
        width: 100%;
        padding: 12px 15px 12px 45px;
        border: 2px solid var(--border-color);
        border-radius: 30px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: var(--white);
        color: var(--text-color);
    }

    .search-box input:focus {
        outline: none;
        border-color: var(--ps-primary);
        box-shadow: 0 0 0 4px rgba(156, 39, 176, 0.1);
    }

    .filter-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 8px 16px;
        border: 2px solid var(--border-color);
        border-radius: 30px;
        background: var(--white);
        color: var(--text-light);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .filter-btn:hover,
    .filter-btn.active {
        background: var(--ps-primary);
        border-color: var(--ps-primary);
        color: white;
    }

    /* Document Cards */
    .documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 25px;
    }

    .document-card {
        background: var(--white);
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        border: 1px solid var(--border-color);
        position: relative;
        overflow: hidden;
    }

    .document-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(156, 39, 176, 0.15);
        border-color: var(--ps-primary-light);
    }

    .document-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--ps-primary), var(--ps-primary-light));
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .document-card:hover::before {
        opacity: 1;
    }

    .document-card.pending-review {
        border-left: 5px solid var(--warning-color);
    }

    .document-card.approved {
        border-left: 5px solid var(--success-color);
    }

    .document-card.rejected {
        border-left: 5px solid var(--danger-color);
    }

    .document-card.changes-requested {
        border-left: 5px solid var(--info-color);
    }

    .document-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }

    .document-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }

    .document-title {
        flex: 1;
    }

    .document-title h4 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-color);
        margin: 0 0 5px 0;
        word-break: break-word;
    }

    .document-meta {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 12px;
        color: var(--text-light);
        flex-wrap: wrap;
    }

    .document-meta span {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .document-description {
        color: var(--text-light);
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
        word-break: break-word;
    }

    .document-uploader {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
        padding: 10px;
        background: var(--gray);
        border-radius: 12px;
    }

    .uploader-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--ps-primary), var(--ps-primary-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 14px;
        font-weight: 600;
    }

    .uploader-info {
        flex: 1;
    }

    .uploader-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 2px;
    }

    .uploader-role {
        font-size: 12px;
        color: var(--ps-primary);
        font-weight: 500;
    }

    .ps-review-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 2;
    }

    .document-stats {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
        padding: 10px;
        background: var(--gray);
        border-radius: 12px;
    }

    .stat-item {
        flex: 1;
        text-align: center;
    }

    .stat-value {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 2px;
    }

    .stat-label {
        font-size: 11px;
        color: var(--text-light);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .document-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .action-btn {
        flex: 1;
        padding: 8px;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        min-width: 70px;
    }

    .btn-view {
        background: var(--ps-primary-light);
        color: var(--ps-primary-dark);
    }

    .btn-view:hover {
        background: var(--ps-primary);
        color: white;
    }

    .btn-download {
        background: #E3F2FD;
        color: #1976D2;
    }

    .btn-download:hover {
        background: #1976D2;
        color: white;
    }

    .btn-print {
        background: #F3E5F5;
        color: #7B1FA2;
    }

    .btn-print:hover {
        background: #7B1FA2;
        color: white;
    }

    .btn-feedback {
        background: #E8F5E9;
        color: #388E3C;
    }

    .btn-feedback:hover {
        background: #388E3C;
        color: white;
    }

    .btn-edit {
        background: #FFF3E0;
        color: #F57C00;
    }

    .btn-edit:hover {
        background: #F57C00;
        color: white;
    }

    .btn-delete {
        background: #FFEBEE;
        color: #D32F2F;
    }

    .btn-delete:hover {
        background: #D32F2F;
        color: white;
    }

    .btn-review {
        background: #E1BEE7;
        color: #7B1FA2;
    }

    .btn-review:hover {
        background: #7B1FA2;
        color: white;
    }

    /* PS Review Modal */
    .ps-review-modal .modal-content {
        border-radius: 20px;
        overflow: hidden;
    }

    .ps-review-modal .modal-header {
        background: linear-gradient(135deg, var(--ps-primary), var(--ps-primary-dark));
        color: white;
        border: none;
        padding: 20px;
    }

    .ps-review-modal .modal-body {
        padding: 25px;
    }

    .ps-review-options {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .review-option {
        flex: 1;
        padding: 15px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
    }

    .review-option:hover {
        border-color: var(--ps-primary);
        background: var(--ps-primary-light);
    }

    .review-option.selected {
        border-color: var(--ps-primary);
        background: var(--ps-primary-light);
    }

    .review-option i {
        font-size: 24px;
        margin-bottom: 10px;
    }

    .review-option.approve i { color: var(--success-color); }
    .review-option.reject i { color: var(--danger-color); }
    .review-option.changes i { color: var(--info-color); }

    /* File type badge */
    .file-type-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        background: rgba(0,0,0,0.05);
        color: var(--text-light);
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }

    .empty-state i {
        font-size: 80px;
        color: var(--border-color);
        margin-bottom: 20px;
    }

    .empty-state h4 {
        font-size: 20px;
        color: var(--text-color);
        margin-bottom: 10px;
    }

    .empty-state p {
        color: var(--text-light);
        margin-bottom: 20px;
    }

    /* Feedback Modal */
    .feedback-modal .modal-content {
        border-radius: 20px;
        overflow: hidden;
    }

    .feedback-modal .modal-header {
        background: linear-gradient(135deg, var(--ps-primary), var(--ps-primary-dark));
        color: white;
        border: none;
        padding: 20px;
    }

    .feedback-modal .modal-body {
        padding: 25px;
        max-height: 400px;
        overflow-y: auto;
        background: var(--white);
    }

    .feedback-item {
        padding: 15px;
        background: var(--gray);
        border-radius: 12px;
        margin-bottom: 15px;
        border-left: 3px solid var(--ps-primary);
    }

    .feedback-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    .feedback-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--ps-primary), var(--ps-primary-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        font-weight: 600;
    }

    .feedback-info h6 {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-color);
        margin: 0 0 2px 0;
    }

    .feedback-info small {
        font-size: 11px;
        color: var(--ps-primary);
    }

    .feedback-text {
        font-size: 13px;
        color: var(--text-light);
        line-height: 1.5;
        margin-bottom: 10px;
    }

    .feedback-reply {
        margin-left: 42px;
        padding: 10px;
        background: var(--white);
        border-radius: 8px;
        border-left: 3px solid var(--ps-primary);
        margin-top: 10px;
    }

    .reply-form {
        margin-top: 10px;
        padding: 10px;
        background: var(--white);
        border-radius: 8px;
    }

    .reply-form textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 12px;
        resize: vertical;
        margin-bottom: 10px;
        background: var(--white);
        color: var(--text-color);
    }

    .reply-form button {
        padding: 6px 12px;
        background: var(--ps-primary);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .documents-grid {
            grid-template-columns: 1fr;
        }
        
        .documents-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .search-box {
            flex: 1;
        }
        
        .filter-buttons {
            justify-content: center;
        }
    }

    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Form Controls */
    .form-control, .form-select {
        border: 2px solid var(--border-color);
        border-radius: 10px;
        padding: 10px 15px;
        transition: all 0.3s ease;
        background: var(--white);
        color: var(--text-color);
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--ps-primary);
        box-shadow: 0 0 0 0.2rem rgba(156, 39, 176, 0.25);
    }

    .form-check-input:checked {
        background-color: var(--ps-primary);
        border-color: var(--ps-primary);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--ps-primary), var(--ps-primary-dark));
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(156, 39, 176, 0.3);
    }
</style>

<div class="main-content <?php echo $sidebarClass; ?>">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                <i class="fas fa-file-alt me-2" style="color: var(--ps-primary);"></i>
                PS Document Management System
            </h2>
            <div>
                <?php if ($is_ps): ?>
                    <span class="ps-badge">
                        <i class="fas fa-user-tie"></i> PS Access
                        <?php if (!empty($pending_reviews)): ?>
                            <span class="badge bg-warning text-dark ms-2"><?php echo count($pending_reviews); ?> Pending</span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- SweetAlert2 Messages -->
        <?php if (isset($_SESSION['upload_response'])): ?>
            <div id="uploadResponse" 
                 data-success="<?php echo $_SESSION['upload_response']['success'] ? 'true' : 'false'; ?>" 
                 data-message="<?php echo htmlspecialchars($_SESSION['upload_response']['message']); ?>">
            </div>
            <?php unset($_SESSION['upload_response']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <?php
        $total_docs = count($documents);
        $my_docs = 0;
        $pending_count = count($pending_reviews);
        $approved_count = 0;
        $rejected_count = 0;
        $total_downloads = 0;
        $total_views = 0;
        $total_feedback = 0;
        
        foreach ($documents as $doc) {
            if ($doc['uploaded_by'] == $admin_id) $my_docs++;
            if ($doc['ps_status'] == 'approved') $approved_count++;
            if ($doc['ps_status'] == 'rejected') $rejected_count++;
            $total_downloads += $doc['download_count'];
            $total_views += $doc['view_count'];
            $total_feedback += $doc['feedback_count'];
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $total_docs; ?></h3>
                    <p>Total Documents</p>
                </div>
                <div class="stat-icon" style="background: linear-gradient(135deg, #9C27B0, #7B1FA2);">
                    <i class="fas fa-file-alt"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $my_docs; ?></h3>
                    <p>My Documents</p>
                </div>
                <div class="stat-icon" style="background: linear-gradient(135deg, #2196F3, #1976D2);">
                    <i class="fas fa-user-edit"></i>
                </div>
            </div>
            <?php if ($is_ps): ?>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $pending_count; ?></h3>
                    <p>Pending Review</p>
                </div>
                <div class="stat-icon" style="background: linear-gradient(135deg, #FF9800, #F57C00);">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $approved_count; ?> / <?php echo $rejected_count; ?></h3>
                    <p>Approved / Rejected</p>
                </div>
                <div class="stat-icon" style="background: linear-gradient(135deg, #4CAF50, #388E3C);">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <?php else: ?>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $total_downloads; ?></h3>
                    <p>Total Downloads</p>
                </div>
                <div class="stat-icon" style="background: linear-gradient(135deg, #4CAF50, #388E3C);">
                    <i class="fas fa-download"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $total_views; ?></h3>
                    <p>Total Views</p>
                </div>
                <div class="stat-icon" style="background: linear-gradient(135deg, #FF9800, #F57C00);">
                    <i class="fas fa-eye"></i>
                </div>
            </div>
            <?php endif; ?>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $total_feedback; ?></h3>
                    <p>Comments</p>
                </div>
                <div class="stat-icon" style="background: linear-gradient(135deg, #00BCD4, #0097A7);">
                    <i class="fas fa-comments"></i>
                </div>
            </div>
        </div>

        <!-- Upload Card -->
        <div class="upload-card">
            <div class="upload-header">
                <div class="upload-header-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <h2>Upload New Document</h2>
            </div>
            
            <form action="ps.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload_document">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="Enter document title">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Visibility</label>
                        <select name="visibility" class="form-select">
                            <option value="staff_only">Staff Only</option>
                            <option value="public">Public</option>
                            <?php if ($is_ps): ?>
                                <option value="private">Private (Only PS)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Options</label>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="allow_feedback" class="form-check-input" id="allowFeedback" checked>
                            <label class="form-check-label" for="allowFeedback">Allow feedback</label>
                        </div>
                        <?php if (!$is_ps): ?>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="needs_ps_review" class="form-check-input" id="needsPSReview">
                            <label class="form-check-label" for="needsPSReview">Needs PS Review</label>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <label class="form-label">Short Note / Description</label>
                        <textarea name="short_note" class="form-control" rows="3" placeholder="Enter a brief description..."></textarea>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <label class="form-label">Upload File <span class="text-danger">*</span></label>
                        <div class="file-input-wrapper">
                            <input type="file" name="document_file" id="documentFile" required>
                            <label for="documentFile" class="file-input-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Choose a file or drag it here</span>
                            </label>
                        </div>
                        <div id="fileInfo" class="file-info">
                            <div class="file-info-item">
                                <i class="fas fa-file"></i>
                                <span id="fileName"></span> 
                                (<span id="fileSize"></span>)
                            </div>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Supported formats: Images, Videos, Audio, Documents (PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT), Archives (ZIP, RAR, 7Z) - Max size: 50MB
                        </small>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-cloud-upload-alt me-2"></i> Upload Document
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Pending Reviews Section (for PS only) -->
        <?php if ($is_ps && !empty($pending_reviews)): ?>
        <div class="mb-4">
            <h3 class="mb-3">
                <i class="fas fa-clock text-warning me-2"></i>
                Pending Reviews
                <span class="badge bg-warning text-dark ms-2"><?php echo count($pending_reviews); ?></span>
            </h3>
            <div class="documents-grid">
                <?php foreach ($pending_reviews as $doc): ?>
                <div class="document-card pending-review">
                    <span class="file-type-badge"><?php echo strtoupper($doc['file_extension']); ?></span>
                    <div class="ps-review-badge">
                        <span class="badge bg-warning text-dark">
                            <i class="fas fa-clock me-1"></i>Pending Review
                        </span>
                    </div>
                    
                    <div class="document-header">
                        <div class="document-icon" style="background: linear-gradient(135deg, <?php echo getFileColor($doc['file_type']); ?>, <?php echo getFileColor($doc['file_type']); ?>dd);">
                            <i class="fas <?php echo getFileIcon($doc['file_type']); ?>"></i>
                        </div>
                        <div class="document-title">
                            <h4><?php echo htmlspecialchars($doc['title']); ?></h4>
                            <div class="document-meta">
                                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($doc['created_at'])); ?></span>
                                <span><i class="fas fa-file"></i> <?php echo round($doc['file_size'] / 1024, 1); ?> KB</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="document-uploader">
                        <div class="uploader-avatar">
                            <?php 
                            $uploader_initials = substr($doc['first_name'] ?? 'U', 0, 1) . substr($doc['last_name'] ?? 'S', 0, 1);
                            echo $uploader_initials;
                            ?>
                        </div>
                        <div class="uploader-info">
                            <div class="uploader-name"><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></div>
                            <div class="uploader-role"><?php echo htmlspecialchars($doc['uploader_role'] ?? 'Staff'); ?></div>
                        </div>
                    </div>
                    
                    <div class="document-actions">
                        <a href="ps.php?view=<?php echo $doc['id']; ?>" target="_blank" class="action-btn btn-view" title="View">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="ps.php?download=<?php echo $doc['id']; ?>" class="action-btn btn-download" title="Download">
                            <i class="fas fa-download"></i> Download
                        </a>
                        <button type="button" class="action-btn btn-review review-document" 
                                data-doc-id="<?php echo $doc['id']; ?>"
                                data-title="<?php echo htmlspecialchars($doc['title']); ?>"
                                title="Review">
                            <i class="fas fa-check-circle"></i> Review
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- All Documents Section -->
        <div class="documents-header">
            <h3>
                <i class="fas fa-folder-open" style="color: var(--ps-primary);"></i>
                All Documents
                <span class="badge bg-secondary"><?php echo count($documents); ?></span>
            </h3>
            
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchDocs" placeholder="Search documents...">
            </div>
            
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">
                    <i class="fas fa-th-large"></i> All
                </button>
                <button class="filter-btn" data-filter="pending">
                    <i class="fas fa-clock"></i> Pending
                </button>
                <button class="filter-btn" data-filter="approved">
                    <i class="fas fa-check-circle"></i> Approved
                </button>
                <button class="filter-btn" data-filter="rejected">
                    <i class="fas fa-times-circle"></i> Rejected
                </button>
            </div>
        </div>

        <?php if (empty($documents)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h4>No Documents Yet</h4>
                <p>Upload your first document using the form above</p>
            </div>
        <?php else: ?>
            <div class="documents-grid" id="documentsGrid">
                <?php foreach ($documents as $doc): 
                    $can_edit = $is_ps || $doc['uploaded_by'] == $admin_id;
                    $file_size_formatted = $doc['file_size'] ? round($doc['file_size'] / 1024, 1) . ' KB' : 'Unknown';
                    if ($doc['file_size'] > 1024 * 1024) {
                        $file_size_formatted = round($doc['file_size'] / (1024 * 1024), 1) . ' MB';
                    }
                    
                    // Determine card class based on PS status
                    $card_class = '';
                    if ($doc['needs_ps_review']) {
                        switch($doc['ps_status']) {
                            case 'pending':
                                $card_class = 'pending-review';
                                break;
                            case 'approved':
                                $card_class = 'approved';
                                break;
                            case 'rejected':
                                $card_class = 'rejected';
                                break;
                            case 'changes_requested':
                                $card_class = 'changes-requested';
                                break;
                        }
                    }
                    
                    // Get uploader initials
                    $uploader_name = $doc['first_name'] . ' ' . $doc['last_name'];
                    $uploader_initials = '';
                    if (!empty($doc['first_name']) && !empty($doc['last_name'])) {
                        $uploader_initials = substr($doc['first_name'], 0, 1) . substr($doc['last_name'], 0, 1);
                    } else {
                        $uploader_initials = 'PS';
                    }
                ?>
                <div class="document-card <?php echo $card_class; ?>" 
                     data-file-type="<?php echo $doc['file_type']; ?>" 
                     data-ps-status="<?php echo $doc['ps_status']; ?>"
                     data-title="<?php echo strtolower($doc['title']); ?>" 
                     data-description="<?php echo strtolower($doc['short_note'] ?? ''); ?>">
                    
                    <span class="file-type-badge"><?php echo strtoupper($doc['file_extension']); ?></span>
                    
                    <?php if ($doc['needs_ps_review']): ?>
                    <div class="ps-review-badge">
                        <?php echo getPSStatusBadge($doc['ps_status']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="document-header">
                        <div class="document-icon" style="background: linear-gradient(135deg, <?php echo getFileColor($doc['file_type']); ?>, <?php echo getFileColor($doc['file_type']); ?>dd);">
                            <i class="fas <?php echo getFileIcon($doc['file_type']); ?>"></i>
                        </div>
                        <div class="document-title">
                            <h4><?php echo htmlspecialchars($doc['title']); ?></h4>
                            <div class="document-meta">
                                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($doc['created_at'])); ?></span>
                                <span><i class="fas fa-file"></i> <?php echo $file_size_formatted; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($doc['short_note'])): ?>
                        <div class="document-description">
                            <?php echo nl2br(htmlspecialchars($doc['short_note'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="document-uploader">
                        <div class="uploader-avatar">
                            <?php echo $uploader_initials; ?>
                        </div>
                        <div class="uploader-info">
                            <div class="uploader-name"><?php echo htmlspecialchars($uploader_name); ?></div>
                            <div class="uploader-role"><?php echo htmlspecialchars($doc['uploader_role'] ?? 'Staff'); ?></div>
                        </div>
                        <?php if ($doc['uploaded_by'] == $admin_id): ?>
                            <span class="badge bg-success">You</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($doc['ps_comment'])): ?>
                    <div class="alert alert-info mt-2 mb-2 p-2 small">
                        <i class="fas fa-comment-dots me-1"></i>
                        <strong>PS Comment:</strong> <?php echo htmlspecialchars($doc['ps_comment']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="document-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $doc['view_count']; ?></div>
                            <div class="stat-label">Views</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $doc['download_count']; ?></div>
                            <div class="stat-label">Downloads</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $doc['feedback_count']; ?></div>
                            <div class="stat-label">Comments</div>
                        </div>
                    </div>
                    
                    <div class="document-actions">
                        <a href="ps.php?view=<?php echo $doc['id']; ?>" target="_blank" class="action-btn btn-view" title="View">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="ps.php?download=<?php echo $doc['id']; ?>" class="action-btn btn-download" title="Download">
                            <i class="fas fa-download"></i> Download
                        </a>
                        <a href="ps.php?print=<?php echo $doc['id']; ?>" target="_blank" class="action-btn btn-print" title="Print">
                            <i class="fas fa-print"></i> Print
                        </a>
                        <?php if ($doc['allow_feedback']): ?>
                        <button type="button" class="action-btn btn-feedback view-feedback" 
                                data-doc-id="<?php echo $doc['id']; ?>" 
                                data-doc-title="<?php echo htmlspecialchars($doc['title']); ?>"
                                title="Feedback">
                            <i class="fas fa-comments"></i> Feed
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($can_edit): ?>
                        <button type="button" class="action-btn btn-edit edit-document" 
                                data-doc-id="<?php echo $doc['id']; ?>"
                                data-title="<?php echo htmlspecialchars($doc['title']); ?>"
                                data-note="<?php echo htmlspecialchars($doc['short_note']); ?>"
                                data-visibility="<?php echo $doc['visibility']; ?>"
                                data-feedback="<?php echo $doc['allow_feedback']; ?>"
                                title="Edit">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <a href="ps.php?delete=<?php echo $doc['id']; ?>" class="action-btn btn-delete delete-document" 
                           data-title="<?php echo htmlspecialchars($doc['title']); ?>"
                           title="Delete">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($is_ps && $doc['needs_ps_review'] && $doc['ps_status'] == 'pending'): ?>
                        <button type="button" class="action-btn btn-review review-document" 
                                data-doc-id="<?php echo $doc['id']; ?>"
                                data-title="<?php echo htmlspecialchars($doc['title']); ?>"
                                title="Review">
                            <i class="fas fa-check-circle"></i> Review
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Document Modal -->
<div class="modal fade" id="editDocumentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #FF9800, #F57C00); color: white;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="ps.php" method="POST">
                <input type="hidden" name="action" value="update_document">
                <input type="hidden" name="doc_id" id="edit_doc_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Short Note</label>
                        <textarea name="short_note" id="edit_note" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Visibility</label>
                        <select name="visibility" id="edit_visibility" class="form-select">
                            <option value="staff_only">Staff Only</option>
                            <option value="public">Public</option>
                            <?php if ($is_ps): ?>
                                <option value="private">Private (Only PS)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="allow_feedback" class="form-check-input" id="edit_allow_feedback">
                            <label class="form-check-label" for="edit_allow_feedback">Allow feedback/comments</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background: #FF9800; color: white;">
                        <i class="fas fa-save me-2"></i>Update Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PS Review Modal -->
<div class="modal fade ps-review-modal" id="psReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>PS Document Review</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="ps.php" method="POST">
                <input type="hidden" name="action" value="ps_review">
                <input type="hidden" name="doc_id" id="review_doc_id">
                <div class="modal-body">
                    <h6 id="review_doc_title" class="text-center mb-4"></h6>
                    
                    <div class="ps-review-options">
                        <div class="review-option approve" onclick="selectReviewOption('approved')">
                            <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                            <h6>Approve</h6>
                            <small>Accept this document</small>
                        </div>
                        <div class="review-option reject" onclick="selectReviewOption('rejected')">
                            <i class="fas fa-times-circle" style="color: var(--danger-color);"></i>
                            <h6>Reject</h6>
                            <small>Reject this document</small>
                        </div>
                        <div class="review-option changes" onclick="selectReviewOption('changes_requested')">
                            <i class="fas fa-edit" style="color: var(--info-color);"></i>
                            <h6>Changes Requested</h6>
                            <small>Request modifications</small>
                        </div>
                    </div>
                    
                    <input type="hidden" name="ps_status" id="ps_status" value="">
                    
                    <div class="mb-3">
                        <label class="form-label">Review Comment</label>
                        <textarea name="ps_comment" class="form-control" rows="3" placeholder="Add your feedback or comments..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Feedback Modal -->
<div class="modal fade feedback-modal" id="feedbackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-comments me-2"></i>Document Feedback</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="feedbackContainer">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <form id="addFeedbackForm" class="w-100" onsubmit="return false;">
                    <input type="hidden" name="document_id" id="feedbackDocId">
                    <input type="hidden" name="ajax_action" value="add_feedback">
                    <div class="input-group">
                        <textarea name="comment" class="form-control" placeholder="Write your feedback..." rows="2" required></textarea>
                        <button type="submit" class="btn" style="background: #9C27B0; color: white;">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Show SweetAlert2 ps_notifications
document.addEventListener('DOMContentLoaded', function() {
    const response = document.getElementById('uploadResponse');
    if (response) {
        const success = response.dataset.success === 'true';
        const message = response.dataset.message;
        
        Swal.fire({
            title: success ? 'Success!' : 'Error!',
            text: message,
            icon: success ? 'success' : 'error',
            confirmButtonColor: '#9C27B0',
            timer: success ? 3000 : null,
            timerProgressBar: success
        });
    }
    
    // Initialize PS Review if user is PS
    <?php if ($is_ps): ?>
    initializePSReview();
    <?php endif; ?>
});

// File input display
document.getElementById('documentFile').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        
        fileName.textContent = file.name;
        
        // Format file size
        let size = file.size;
        if (size < 1024) {
            fileSize.textContent = size + ' B';
        } else if (size < 1024 * 1024) {
            fileSize.textContent = (size / 1024).toFixed(1) + ' KB';
        } else {
            fileSize.textContent = (size / (1024 * 1024)).toFixed(1) + ' MB';
        }
        
        fileInfo.classList.add('show');
    }
});

// Search functionality
document.getElementById('searchDocs').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const cards = document.querySelectorAll('.document-card');
    
    cards.forEach(card => {
        const title = card.dataset.title || '';
        const description = card.dataset.description || '';
        const text = title + ' ' + description;
        
        if (text.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

// Filter functionality
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        const cards = document.querySelectorAll('.document-card');
        
        cards.forEach(card => {
            if (filter === 'all') {
                card.style.display = 'block';
            } else if (filter === 'pending') {
                card.style.display = card.dataset.psStatus === 'pending' ? 'block' : 'none';
            } else if (filter === 'approved') {
                card.style.display = card.dataset.psStatus === 'approved' ? 'block' : 'none';
            } else if (filter === 'rejected') {
                card.style.display = card.dataset.psStatus === 'rejected' ? 'block' : 'none';
            } else {
                card.style.display = card.dataset.fileType === filter ? 'block' : 'none';
            }
        });
    });
});

// Edit document
document.querySelectorAll('.edit-document').forEach(btn => {
    btn.addEventListener('click', function() {
        const docId = this.dataset.docId;
        const title = this.dataset.title;
        const note = this.dataset.note;
        const visibility = this.dataset.visibility;
        const feedback = this.dataset.feedback === '1';
        
        document.getElementById('edit_doc_id').value = docId;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_note').value = note;
        document.getElementById('edit_visibility').value = visibility;
        document.getElementById('edit_allow_feedback').checked = feedback;
        
        new bootstrap.Modal(document.getElementById('editDocumentModal')).show();
    });
});

// Delete confirmation
document.querySelectorAll('.delete-document').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const href = this.href;
        const title = this.dataset.title;
        
        Swal.fire({
            title: 'Delete Document?',
            html: `Are you sure you want to delete "<strong>${title}</strong>"?<br><br>This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = href;
            }
        });
    });
});

// PS Review functionality
function initializePSReview() {
    document.querySelectorAll('.review-document').forEach(btn => {
        btn.addEventListener('click', function() {
            const docId = this.dataset.docId;
            const title = this.dataset.title;
            
            document.getElementById('review_doc_id').value = docId;
            document.getElementById('review_doc_title').textContent = title;
            
            // Reset selection
            document.querySelectorAll('.review-option').forEach(opt => opt.classList.remove('selected'));
            document.getElementById('ps_status').value = '';
            
            new bootstrap.Modal(document.getElementById('psReviewModal')).show();
        });
    });
}

function selectReviewOption(status) {
    document.querySelectorAll('.review-option').forEach(opt => opt.classList.remove('selected'));
    document.querySelector(`.review-option.${status === 'approved' ? 'approve' : status === 'rejected' ? 'reject' : 'changes'}`).classList.add('selected');
    document.getElementById('ps_status').value = status;
}

// View feedback
document.querySelectorAll('.view-feedback').forEach(btn => {
    btn.addEventListener('click', function() {
        const docId = this.dataset.docId;
        const docTitle = this.dataset.docTitle;
        
        document.getElementById('feedbackDocId').value = docId;
        
        const modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
        modal.show();
        
        loadFeedback(docId);
    });
});

function loadFeedback(docId) {
    const container = document.getElementById('feedbackContainer');
    
    fetch(`get_ps_feedback.php?doc_id=${docId}`)
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
        })
        .catch(error => {
            container.innerHTML = '<div class="alert alert-danger">Error loading feedback.</div>';
        });
}

// Add feedback
document.getElementById('addFeedbackForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('ps.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const docId = document.getElementById('feedbackDocId').value;
            loadFeedback(docId);
            document.querySelector('#addFeedbackForm textarea').value = '';
            
            Swal.fire({
                title: 'Success!',
                text: data.message,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: data.message,
                icon: 'error',
                confirmButtonColor: '#9C27B0'
            });
        }
    });
});

// Reply to comment
function replyToComment(commentId) {
    const replyForm = document.getElementById(`replyForm_${commentId}`);
    if (replyForm) {
        replyForm.style.display = replyForm.style.display === 'none' ? 'block' : 'none';
    }
}

function submitReply(commentId, docId) {
    const textarea = document.getElementById(`replyText_${commentId}`);
    const comment = textarea.value.trim();
    
    if (!comment) {
        Swal.fire({
            title: 'Error!',
            text: 'Please enter your reply',
            icon: 'error',
            confirmButtonColor: '#9C27B0'
        });
        return;
    }
    
    const formData = new FormData();
    formData.append('ajax_action', 'add_feedback');
    formData.append('document_id', docId);
    formData.append('comment', comment);
    formData.append('parent_id', commentId);
    
    fetch('ps.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadFeedback(docId);
            textarea.value = '';
            document.getElementById(`replyForm_${commentId}`).style.display = 'none';
            
            Swal.fire({
                title: 'Success!',
                text: 'Reply added successfully',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: data.message,
                icon: 'error',
                confirmButtonColor: '#9C27B0'
            });
        }
    });
}
</script>

<?php include '../controller/footer.php'; ?>