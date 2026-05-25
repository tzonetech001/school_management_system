<?php
// candidates/shulesalama.php
session_start();
require_once '../controller/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$success_message = '';
$error_message = '';

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_comment'])) {
    $post_id = intval($_POST['post_id']);
    $comment = mysqli_real_escape_string($conn, trim($_POST['comment']));
    
    if (!empty($comment)) {
        $insert_query = "INSERT INTO shule_salama_comments (post_id, commenter_id, commenter_type, comment, status) 
                         VALUES (?, ?, 'student', ?, 'pending')";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "iis", $post_id, $student_id, $comment);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $success_message = "Your comment has been submitted and is awaiting approval.";
        } else {
            $error_message = "Failed to submit comment. Please try again.";
        }
        mysqli_stmt_close($insert_stmt);
    } else {
        $error_message = "Comment cannot be empty.";
    }
}

// Fetch Shule Salama posts
$posts_query = "SELECT p.*, 
                CONCAT(a.first_name, ' ', a.last_name) as author_name,
                (SELECT COUNT(*) FROM shule_salama_comments WHERE post_id = p.id AND status = 'approved') as comment_count
                FROM shule_salama_posts p
                LEFT JOIN admins a ON p.admin_id = a.id
                WHERE p.status = 'active' 
                AND p.visibility IN ('public', 'students_only')
                ORDER BY p.created_at DESC";

$posts_result = mysqli_query($conn, $posts_query);

// Fetch safety alerts count
$alerts_query = "SELECT COUNT(*) as alert_count FROM shule_salama_posts 
                 WHERE status = 'active' 
                 AND priority IN ('critical', 'emergency')
                 AND visibility IN ('public', 'students_only')";
$alerts_result = mysqli_query($conn, $alerts_query);
$alerts_data = mysqli_fetch_assoc($alerts_result);

// Fetch recent posts for sidebar
$recent_query = "SELECT id, title, created_at FROM shule_salama_posts 
                 WHERE status = 'active' AND visibility IN ('public', 'students_only')
                 ORDER BY created_at DESC LIMIT 5";
$recent_result = mysqli_query($conn, $recent_query);

// Get student info
$student_query = "SELECT first_name, last_name, class, combination FROM students WHERE id = ?";
$student_stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($student_stmt, "i", $student_id);
mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);
$student = mysqli_fetch_assoc($student_result);

// Record view for a post
if (isset($_GET['view_post']) && is_numeric($_GET['view_post'])) {
    $view_post_id = intval($_GET['view_post']);
    
    // Check if already viewed in this session
    if (!isset($_SESSION['viewed_posts'])) {
        $_SESSION['viewed_posts'] = array();
    }
    
    if (!in_array($view_post_id, $_SESSION['viewed_posts'])) {
        $update_view_query = "UPDATE shule_salama_posts SET views_count = views_count + 1 WHERE id = ?";
        $update_view_stmt = mysqli_prepare($conn, $update_view_query);
        mysqli_stmt_bind_param($update_view_stmt, "i", $view_post_id);
        mysqli_stmt_execute($update_view_stmt);
        mysqli_stmt_close($update_view_stmt);
        
        // Log the view
        $log_view_query = "INSERT INTO shule_salama_views (post_id, viewer_id, viewer_type) VALUES (?, ?, 'student')";
        $log_view_stmt = mysqli_prepare($conn, $log_view_query);
        mysqli_stmt_bind_param($log_view_stmt, "ii", $view_post_id, $student_id);
        mysqli_stmt_execute($log_view_stmt);
        mysqli_stmt_close($log_view_stmt);
        
        $_SESSION['viewed_posts'][] = $view_post_id;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shule Salama - Safety & Security - Muyovozi High School</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --primary-color: #1e3c72;
            --secondary-color: #2a5298;
            --safety-color: #28a745;
            --emergency-color: #dc3545;
            --warning-color: #ffc107;
        }

        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

        /* Emergency Banner */
        .emergency-banner {
            background: linear-gradient(135deg, var(--emergency-color), #c82333);
            color: white;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.9; }
            100% { opacity: 1; }
        }

        .emergency-banner i {
            font-size: 24px;
            margin-right: 10px;
        }

        .emergency-contacts {
            display: flex;
            gap: 20px;
        }

        .emergency-contact {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }

        /* Post Cards */
        .post-card {
            background: white;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }

        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .post-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .post-priority {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .priority-normal { background: #e9ecef; color: #495057; }
        .priority-important { background: #cce5ff; color: #004085; }
        .priority-critical { background: #f8d7da; color: #721c24; animation: blink 1.5s infinite; }
        .priority-emergency { background: #dc3545; color: white; animation: blink 1s infinite; }

        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .post-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .post-meta {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #6c757d;
            margin-top: 10px;
        }

        .post-content {
            padding: 20px;
        }

        .post-description {
            color: #333;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        /* File Attachment */
        .file-attachment {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .file-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        /* Comments Section */
        .comments-section {
            background: #f8f9fa;
            padding: 20px;
            border-top: 1px solid #dee2e6;
        }

        .comment {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 3px solid var(--primary-color);
        }

        .comment-meta {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .comment-avatar {
            width: 32px;
            height: 32px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            margin-right: 10px;
        }

        .comment-author {
            font-weight: 600;
            color: var(--primary-color);
        }

        .comment-date {
            font-size: 11px;
            color: #6c757d;
            margin-left: 10px;
        }

        .comment-text {
            margin-left: 42px;
            color: #333;
        }

        .comment-form {
            margin-top: 20px;
        }

        /* Sidebar Cards */
        .sidebar-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .sidebar-card h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .contact-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .contact-item:last-child {
            border-bottom: none;
        }

        .contact-icon {
            width: 40px;
            height: 40px;
            background: rgba(30, 60, 114, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: var(--primary-color);
        }

        .contact-info {
            flex: 1;
        }

        .contact-info strong {
            display: block;
            color: #333;
        }

        .contact-info small {
            color: #6c757d;
        }

        .hotline-number {
            font-size: 20px;
            font-weight: bold;
            color: var(--emergency-color);
        }

        /* Safety Tips */
        .tip-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .tip-item i {
            color: var(--safety-color);
            margin-right: 8px;
        }

        /* Post Stats */
        .post-stats {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #6c757d;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .emergency-banner {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .emergency-contacts {
                flex-direction: column;
                width: 100%;
            }
            
            .post-meta {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <?php include 'sidebar_student.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Emergency Banner - Always visible when there are emergency posts -->
            <?php if ($alerts_data['alert_count'] > 0): ?>
            <div class="emergency-banner">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>ACTIVE EMERGENCY ALERTS: <?php echo $alerts_data['alert_count']; ?></strong>
                </div>
                <div class="emergency-contacts">
                    <div class="emergency-contact">
                        <i class="fas fa-phone-alt me-1"></i> School Security: 0800-123-456
                    </div>
                    <div class="emergency-contact">
                        <i class="fas fa-ambulance me-1"></i> Emergency: 112
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Shule Salama</h2>
                    <p class="text-muted">School Safety and Security Information</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-success p-2">
                        <i class="fas fa-shield-alt me-1"></i> Safe School
                    </span>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-times-circle me-2"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Main Content - Posts -->
                <div class="col-lg-8">
                    <?php if (mysqli_num_rows($posts_result) > 0): ?>
                        <?php while ($post = mysqli_fetch_assoc($posts_result)): 
                            $priority_class = 'priority-' . $post['priority'];
                        ?>
                        <div class="post-card" id="post-<?php echo $post['id']; ?>">
                            <div class="post-header">
                                <span class="post-priority <?php echo $priority_class; ?>">
                                    <i class="fas fa-<?php 
                                        echo $post['priority'] == 'emergency' ? 'exclamation-circle' : 
                                            ($post['priority'] == 'critical' ? 'exclamation-triangle' : 
                                            ($post['priority'] == 'important' ? 'star' : 'info-circle')); 
                                    ?> me-1"></i>
                                    <?php echo ucfirst($post['priority']); ?>
                                </span>
                                
                                <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                
                                <div class="post-meta">
                                    <span>
                                        <i class="far fa-user-circle me-1"></i>
                                        <?php echo htmlspecialchars($post['author_name'] ?? 'School Admin'); ?>
                                    </span>
                                    <span>
                                        <i class="far fa-calendar-alt me-1"></i>
                                        <?php echo date('F j, Y', strtotime($post['created_at'])); ?>
                                    </span>
                                    <span>
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo date('h:i A', strtotime($post['created_at'])); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="post-content">
                                <?php if (!empty($post['description'])): ?>
                                <div class="post-description">
                                    <?php echo nl2br(htmlspecialchars($post['description'])); ?>
                                </div>
                                <?php endif; ?>

                                <?php if ($post['file_path']): ?>
                                <div class="file-attachment d-flex align-items-center">
                                    <div class="file-icon me-3">
                                        <i class="fas fa-<?php 
                                            echo $post['file_type'] == 'image' ? 'image' : 
                                                ($post['file_type'] == 'video' ? 'video' : 
                                                ($post['file_type'] == 'document' ? 'file-alt' : 'paperclip')); 
                                        ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <strong><?php echo htmlspecialchars($post['file_name'] ?? 'Attachment'); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php 
                                            if ($post['file_size']) {
                                                echo round($post['file_size'] / 1024, 2) . ' KB';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                    <a href="<?php echo htmlspecialchars($post['file_path']); ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                                        <i class="fas fa-download me-1"></i> View
                                    </a>
                                </div>
                                <?php endif; ?>

                                <div class="post-stats">
                                    <span class="stat-item">
                                        <i class="far fa-eye"></i> <?php echo $post['views_count']; ?> views
                                    </span>
                                    <span class="stat-item">
                                        <i class="far fa-comment"></i> <?php echo $post['comment_count']; ?> comments
                                    </span>
                                </div>
                            </div>

                            <!-- Comments Section -->
                            <div class="comments-section">
                                <?php
                                // Fetch approved comments for this post
                                $comments_query = "SELECT c.*, s.first_name, s.last_name 
                                                  FROM shule_salama_comments c
                                                  LEFT JOIN students s ON c.commenter_id = s.id AND c.commenter_type = 'student'
                                                  WHERE c.post_id = ? AND c.status = 'approved'
                                                  ORDER BY c.created_at ASC";
                                $comments_stmt = mysqli_prepare($conn, $comments_query);
                                mysqli_stmt_bind_param($comments_stmt, "i", $post['id']);
                                mysqli_stmt_execute($comments_stmt);
                                $comments_result = mysqli_stmt_get_result($comments_stmt);
                                ?>

                                <?php if (mysqli_num_rows($comments_result) > 0): ?>
                                <h6 class="mb-3">
                                    <i class="far fa-comments me-2"></i> Comments (<?php echo mysqli_num_rows($comments_result); ?>)
                                </h6>
                                
                                <?php while ($comment = mysqli_fetch_assoc($comments_result)): ?>
                                <div class="comment">
                                    <div class="comment-meta">
                                        <div class="comment-avatar">
                                            <?php 
                                            if ($comment['commenter_type'] == 'student') {
                                                echo strtoupper(substr($comment['first_name'] ?? 'S', 0, 1) . substr($comment['last_name'] ?? 'T', 0, 1));
                                            } else {
                                                echo 'AD';
                                            }
                                            ?>
                                        </div>
                                        <div>
                                            <span class="comment-author">
                                                <?php 
                                                if ($comment['commenter_type'] == 'student') {
                                                    echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']);
                                                } else {
                                                    echo 'School Administrator';
                                                }
                                                ?>
                                            </span>
                                            <span class="comment-date">
                                                <?php echo date('M d, Y h:i A', strtotime($comment['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="comment-text">
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                                <?php endif; ?>
                                <?php mysqli_stmt_close($comments_stmt); ?>

                                <!-- Comment Form -->
                                <form method="POST" class="comment-form">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <div class="form-group mb-3">
                                        <label for="comment-<?php echo $post['id']; ?>" class="form-label">
                                            <i class="far fa-comment-dots me-1"></i> Leave a comment
                                        </label>
                                        <textarea class="form-control" id="comment-<?php echo $post['id']; ?>" 
                                                  name="comment" rows="2" placeholder="Your comment will be reviewed before posting..."></textarea>
                                    </div>
                                    <button type="submit" name="submit_comment" class="btn btn-primary btn-sm">
                                        <i class="fas fa-paper-plane me-1"></i> Post Comment
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5 bg-white rounded-3">
                            <i class="fas fa-shield-alt fa-4x text-muted mb-3"></i>
                            <h4>No Announcements</h4>
                            <p class="text-muted">There are no safety announcements at this time.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Emergency Contacts -->
                    <div class="sidebar-card">
                        <h5><i class="fas fa-phone-alt me-2 text-danger"></i>In Case For Emergency</h5>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="contact-info">
                                <strong>School Security</strong>
                                <small>24/7 Security Desk</small>
                                <div class="hotline-number">Ask Watchman</div>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-ambulance"></i>
                            </div>
                            <div class="contact-info">
                                <strong>Health Center</strong>
                                <small>School Infirmary</small>
                                <div>Meet with matron or patron</div>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-fire-extinguisher"></i>
                            </div>
                            <div class="contact-info">
                                <strong>Fire & Rescue</strong>
                                <small>Emergency Services</small>
                                <div>112 / 114</div>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="contact-info">
                                <strong>Discipline Master</strong>
                                <small>Office Hours: All the Time</small>
                                <div></div>
                            </div>
                        </div>
                    </div>

                    <!-- Safety Tips -->
                    <div class="sidebar-card">
                        <h5><i class="fas fa-lightbulb me-2 text-warning"></i>Safety Tips</h5>
                        <div class="tip-item">
                            <i class="fas fa-check-circle"></i>
                            Always walk in well-lit areas at night
                        </div>
                        <div class="tip-item">
                            <i class="fas fa-check-circle"></i>
                            Report suspicious activities immediately
                        </div>
                        <div class="tip-item">
                            <i class="fas fa-check-circle"></i>
                            Keep emergency contacts saved in your phone
                        </div>
                        <div class="tip-item">
                            <i class="fas fa-check-circle"></i>
                            Lock your dormitory room when leaving
                        </div>
                        <div class="tip-item">
                            <i class="fas fa-check-circle"></i>
                            Don't share personal information with strangers
                        </div>
                        <div class="tip-item">
                            <i class="fas fa-check-circle"></i>
                            Know the location of emergency exits
                        </div>
                    </div>

                    <!-- Recent Posts -->
                    <?php if (mysqli_num_rows($recent_result) > 0): ?>
                    <div class="sidebar-card">
                        <h5><i class="fas fa-history me-2"></i>Recent Updates</h5>
                        <?php while ($recent = mysqli_fetch_assoc($recent_result)): ?>
                        <a href="#post-<?php echo $recent['id']; ?>" class="text-decoration-none">
                            <div class="d-flex align-items-center mb-2 p-2 border-bottom">
                                <div class="flex-grow-1">
                                    <strong class="text-dark"><?php echo htmlspecialchars($recent['title']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($recent['created_at'])); ?>
                                    </small>
                                </div>
                                <i class="fas fa-chevron-right text-muted"></i>
                            </div>
                        </a>
                        <?php endwhile; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Report an Issue -->
                    <div class="sidebar-card">
                        <h5><i class="fas fa-flag me-2 text-danger"></i>Report an Issue</h5>
                        <p class="small text-muted">If you notice a safety concern, report it immediately:</p>
                        <a href="#" class="btn btn-danger w-100">
                            <i class="fas fa-exclamation-triangle me-2"></i> Report Safety Concern
                        </a>
                        <hr>
                        <p class="small text-muted mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Anonymous reporting is available. Your identity will be protected.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../controller/footer.php'; ?>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scroll to post when clicking from recent posts
        document.querySelectorAll('a[href^="#post-"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Highlight new/emergency posts
        const emergencyPosts = document.querySelectorAll('.priority-emergency, .priority-critical');
        emergencyPosts.forEach(post => {
            post.closest('.post-card').style.border = '2px solid #dc3545';
        });
    </script>
</body>
</html>
<?php 
mysqli_stmt_close($student_stmt);
mysqli_close($conn);
?>