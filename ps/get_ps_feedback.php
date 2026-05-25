<?php
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id']) || !isset($_GET['doc_id'])) {
    exit();
}

$doc_id = intval($_GET['doc_id']);
$admin_id = $_SESSION['admin_id'];

// Get user info
$user_sql = "SELECT a.*, 
            GROUP_CONCAT(DISTINCT ar.role_name ORDER BY ara.is_primary DESC, ar.role_name SEPARATOR ', ') as roles
            FROM admins a
            LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
            LEFT JOIN admin_roles ar ON ara.role_id = ar.id
            WHERE a.id = $admin_id
            GROUP BY a.id";
$user_result = mysqli_query($conn, $user_sql);
$current_user = mysqli_fetch_assoc($user_result);
$user_fullname = $current_user['first_name'] . ' ' . $current_user['last_name'];
$user_role = $current_user['primary_role'] ?: 'Staff';

// Get feedback
$sql = "SELECT f.*, 
        a.first_name, a.last_name 
        FROM ps_document_feedback f
        LEFT JOIN admins a ON f.commenter_id = a.id
        WHERE f.document_id = ? AND f.status = 'active'
        ORDER BY f.parent_comment_id IS NULL DESC, f.created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$result = $stmt->get_result();

$feedback = [];
while ($row = $result->fetch_assoc()) {
    $feedback[] = $row;
}

// Group feedback by parent
$grouped = [];
foreach ($feedback as $item) {
    if ($item['parent_comment_id'] === null) {
        $item['replies'] = [];
        $grouped[$item['id']] = $item;
    }
}

foreach ($feedback as $item) {
    if ($item['parent_comment_id'] !== null && isset($grouped[$item['parent_comment_id']])) {
        $grouped[$item['parent_comment_id']]['replies'][] = $item;
    }
}
?>

<?php if (empty($grouped)): ?>
    <div class="text-center text-muted py-4">
        <i class="fas fa-comments fa-3x mb-3" style="color: #ddd;"></i>
        <p>No feedback yet. Be the first to comment!</p>
    </div>
<?php else: ?>
    <?php foreach ($grouped as $comment): ?>
        <div class="feedback-item">
            <div class="feedback-header">
                <div class="feedback-avatar">
                    <?php 
                    $initials = substr($comment['first_name'] ?? 'U', 0, 1) . substr($comment['last_name'] ?? 'S', 0, 1);
                    echo $initials;
                    ?>
                </div>
                <div class="feedback-info">
                    <h6><?php echo htmlspecialchars($comment['commenter_name']); ?></h6>
                    <small><?php echo htmlspecialchars($comment['commenter_role']); ?> • <?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?></small>
                </div>
            </div>
            <div class="feedback-text">
                <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
            </div>
            
            <?php if (!empty($comment['replies'])): ?>
                <?php foreach ($comment['replies'] as $reply): ?>
                    <div class="feedback-reply">
                        <div class="feedback-header">
                            <div class="feedback-avatar">
                                <?php 
                                $reply_initials = substr($reply['first_name'] ?? 'U', 0, 1) . substr($reply['last_name'] ?? 'S', 0, 1);
                                echo $reply_initials;
                                ?>
                            </div>
                            <div class="feedback-info">
                                <h6><?php echo htmlspecialchars($reply['commenter_name']); ?></h6>
                                <small><?php echo htmlspecialchars($reply['commenter_role']); ?> • <?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></small>
                            </div>
                        </div>
                        <div class="feedback-text">
                            <?php echo nl2br(htmlspecialchars($reply['comment'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <button class="btn btn-sm btn-link" onclick="replyToComment(<?php echo $comment['id']; ?>)">
                <i class="fas fa-reply"></i> Reply
            </button>
            
            <div id="replyForm_<?php echo $comment['id']; ?>" style="display: none;" class="reply-form">
                <textarea id="replyText_<?php echo $comment['id']; ?>" placeholder="Write your reply..." rows="2"></textarea>
                <button onclick="submitReply(<?php echo $comment['id']; ?>, <?php echo $doc_id; ?>)">
                    <i class="fas fa-paper-plane me-1"></i>Send Reply
                </button>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>