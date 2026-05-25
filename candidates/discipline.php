<?php
// candidates/discipline.php
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

// Fetch student discipline records
$discipline_query = "SELECT dr.*, 
                     CONCAT(a.first_name, ' ', a.last_name) as recorded_by_name
                     FROM discipline_records dr
                     LEFT JOIN admins a ON dr.recorded_by = a.id
                     WHERE dr.student_id = ? 
                     AND dr.is_visible_to_student = 1
                     AND dr.status = 'active'
                     ORDER BY dr.created_at DESC";

$discipline_stmt = mysqli_prepare($conn, $discipline_query);
mysqli_stmt_bind_param($discipline_stmt, "i", $student_id);
mysqli_stmt_execute($discipline_stmt);
$discipline_result = mysqli_stmt_get_result($discipline_stmt);

// Fetch discipline statistics
$stats_query = "SELECT 
                    COUNT(CASE WHEN list_type = 'black' THEN 1 END) as blacklist_count,
                    COUNT(CASE WHEN list_type = 'white' THEN 1 END) as whitelist_count,
                    COUNT(CASE WHEN severity_level = 'critical' THEN 1 END) as critical_issues,
                    COUNT(CASE WHEN follow_up_required = 1 AND follow_up_completed = 0 THEN 1 END) as pending_followups
                FROM discipline_records 
                WHERE student_id = ? AND status = 'active'";

$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $student_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Handle follow-up confirmation (if student wants to acknowledge)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acknowledge_record'])) {
    $record_id = intval($_POST['record_id']);
    
    $update_query = "UPDATE discipline_records 
                     SET follow_up_completed = 1,
                         follow_up_notes = CONCAT(COALESCE(follow_up_notes, ''), ' | Acknowledged by student on ', NOW())
                     WHERE id = ? AND student_id = ?";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "ii", $record_id, $student_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        $success_message = "Thank you for acknowledging this matter.";
    } else {
        $error_message = "Failed to update. Please try again.";
    }
    mysqli_stmt_close($update_stmt);
}

// Get student info
$student_query = "SELECT first_name, last_name, class, combination FROM students WHERE id = ?";
$student_stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($student_stmt, "i", $student_id);
mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);
$student = mysqli_fetch_assoc($student_result);

// Determine if student has any active cases
$has_active_cases = ($stats['pending_followups'] > 0 || $stats['critical_issues'] > 0);

// Get total records count
$total_records = mysqli_num_rows($discipline_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discipline Records - Muyovozi High School</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --primary-color: #1e3c72;
            --secondary-color: #2a5298;
            --accent-color: #4CAF50;
            --danger-color: #f44336;
            --warning-color: #ff9800;
            --info-color: #2196F3;
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

        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card.black-card {
            border-left: 5px solid var(--danger-color);
        }

        .stats-card.white-card {
            border-left: 5px solid var(--accent-color);
        }

        .stats-card.critical-card {
            border-left: 5px solid #ff6b6b;
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .stats-icon.black-icon {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }

        .stats-icon.white-icon {
            background: rgba(76, 175, 80, 0.1);
            color: var(--accent-color);
        }

        .stats-icon.critical-icon {
            background: rgba(255, 107, 107, 0.1);
            color: #ff6b6b;
        }

        .stats-number {
            font-size: 28px;
            font-weight: bold;
            margin: 5px 0;
        }

        .stats-label {
            color: #6c757d;
            font-size: 14px;
        }

        /* Record Cards */
        .record-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .record-card.black-list::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--danger-color);
        }

        .record-card.white-list::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--accent-color);
        }

        .record-type-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .badge-warning { background: rgba(255, 152, 0, 0.1); color: #f57c00; }
        .badge-appreciation { background: rgba(76, 175, 80, 0.1); color: #388e3c; }
        .badge-suspension { background: rgba(244, 67, 54, 0.1); color: #d32f2f; }
        .badge-commendation { background: rgba(33, 150, 243, 0.1); color: #1976d2; }
        .badge-reprimand { background: rgba(255, 193, 7, 0.1); color: #ffa000; }
        .badge-expulsion { background: rgba(0, 0, 0, 0.1); color: #000; }

        .severity-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .severity-low { background: #e8f5e8; color: #2e7d32; }
        .severity-medium { background: #fff3e0; color: #ef6c00; }
        .severity-high { background: #ffebee; color: #c62828; }
        .severity-critical { background: #ffebee; color: #b71c1c; font-weight: bold; }

        .record-meta {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 12px;
            color: #6c757d;
        }

        .follow-up-section {
            background: #fff8e1;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .btn-acknowledge {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .btn-acknowledge:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .empty-state i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            color: #333;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #6c757d;
            max-width: 400px;
            margin: 0 auto;
        }

        /* Alert Banner */
        .alert-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        /* Info Cards */
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .info-card h5 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-list li:last-child {
            border-bottom: none;
        }

        .info-list i {
            width: 20px;
            color: var(--primary-color);
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
                    <h2 class="mb-1">Discipline Records</h2>
                    <p class="text-muted">Track your conduct and achievements at school</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-primary p-2">
                        <i class="fas fa-calendar me-1"></i> <?php echo date('F j, Y'); ?>
                    </span>
                </div>
            </div>

            <?php if ($has_active_cases): ?>
            <!-- Active Cases Alert -->
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Attention Required!</strong> You have pending matters that need your attention.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

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

            <!-- Stats Cards Row -->
            <div class="row">
                <div class="col-md-4">
                    <div class="stats-card black-card">
                        <div class="stats-icon black-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['blacklist_count'] ?? 0; ?></div>
                        <div class="stats-label">Black List Records</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card white-card">
                        <div class="stats-icon white-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['whitelist_count'] ?? 0; ?></div>
                        <div class="stats-label">White List Records (Achievements)</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card critical-card">
                        <div class="stats-icon critical-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['pending_followups'] ?? 0; ?></div>
                        <div class="stats-label">Pending Follow-ups</div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row">
                <!-- Discipline Records Column -->
                <div class="col-lg-8">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2" style="color: var(--primary-color);"></i>
                            Your Discipline Records
                        </h5>
                        <span class="badge bg-secondary">Total: <?php echo $total_records; ?></span>
                    </div>

                    <?php if (mysqli_num_rows($discipline_result) > 0): ?>
                        <?php while ($record = mysqli_fetch_assoc($discipline_result)): 
                            $record_class = $record['list_type'] == 'black' ? 'black-list' : 'white-list';
                            $severity_class = 'severity-' . ($record['severity_level'] ?? 'medium');
                        ?>
                        <div class="record-card <?php echo $record_class; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="record-type-badge badge-<?php echo $record['record_type']; ?>">
                                        <i class="fas fa-<?php echo $record['record_type'] == 'appreciation' || $record['record_type'] == 'commendation' ? 'star' : 'exclamation'; ?> me-1"></i>
                                        <?php echo ucfirst($record['record_type']); ?>
                                    </span>
                                    <?php if ($record['severity_level']): ?>
                                    <span class="severity-badge <?php echo $severity_class; ?> ms-2">
                                        <?php echo ucfirst($record['severity_level']); ?> Priority
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <i class="far fa-clock me-1"></i> 
                                    <?php echo date('M d, Y', strtotime($record['created_at'])); ?>
                                </small>
                            </div>

                            <div class="mt-3">
                                <p class="mb-2"><?php echo nl2br(htmlspecialchars($record['short_note'])); ?></p>
                                
                                <?php if ($record['file_path']): ?>
                                <div class="mt-2">
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-paperclip me-1"></i>
                                        Attachment: 
                                        <a href="<?php echo htmlspecialchars($record['file_path']); ?>" target="_blank" class="text-primary">
                                            <?php echo htmlspecialchars($record['file_name'] ?? 'View Attachment'); ?>
                                        </a>
                                    </span>
                                </div>
                                <?php endif; ?>

                                <div class="record-meta">
                                    <span>
                                        <i class="fas fa-user me-1"></i>
                                        Recorded by: <?php echo htmlspecialchars($record['recorded_by_name'] ?? 'School Admin'); ?>
                                    </span>
                                    <?php if ($record['follow_up_due_date']): ?>
                                    <span class="<?php echo strtotime($record['follow_up_due_date']) < time() ? 'text-danger' : ''; ?>">
                                        <i class="fas fa-calendar-check me-1"></i>
                                        Due: <?php echo date('M d, Y', strtotime($record['follow_up_due_date'])); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($record['follow_up_required'] && !$record['follow_up_completed']): ?>
                            <div class="follow-up-section">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><i class="fas fa-hand-pointer me-2"></i>Action Required</strong>
                                        <p class="mb-0 small text-muted mt-1">Please acknowledge this record to confirm you've read it.</p>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to acknowledge this record?');">
                                        <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                        <button type="submit" name="acknowledge_record" class="btn btn-acknowledge btn-sm">
                                            <i class="fas fa-check me-1"></i> Acknowledge
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($record['follow_up_notes']): ?>
                            <div class="mt-2 small text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Note: <?php echo htmlspecialchars($record['follow_up_notes']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="empty-state">
                            <i class="fas fa-clipboard-check"></i>
                            <h4>No Discipline Records</h4>
                            <p>You don't have any discipline records at the moment. Keep up the good work!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Column (Guidelines and Info) -->
                <div class="col-lg-4">
                    <!-- Student Info Card -->
                    <div class="info-card">
                        <h5><i class="fas fa-user-graduate me-2"></i>Student Information</h5>
                        <ul class="info-list">
                            <li><i class="fas fa-user"></i> <strong>Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></li>
                            <li><i class="fas fa-graduation-cap"></i> <strong>Class:</strong> <?php echo htmlspecialchars($student['class']); ?></li>
                            <li><i class="fas fa-book"></i> <strong>Combination:</strong> <?php echo htmlspecialchars($student['combination']); ?></li>
                        </ul>
                    </div>

                    <!-- Guidelines Card -->
                    <div class="info-card">
                        <h5><i class="fas fa-gavel me-2"></i>Discipline Guidelines</h5>
                        <div class="accordion" id="guidelinesAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                        Black List (Conduct Issues)
                                    </button>
                                </h2>
                                <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#guidelinesAccordion">
                                    <div class="accordion-body">
                                        <ul class="mb-0">
                                            <li>Warnings for minor infractions</li>
                                            <li>Reprimands for repeated issues</li>
                                            <li>Suspensions for serious violations</li>
                                            <li>Expulsion for critical offenses</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                        White List (Achievements)
                                    </button>
                                </h2>
                                <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#guidelinesAccordion">
                                    <div class="accordion-body">
                                        <ul class="mb-0">
                                            <li>Academic excellence</li>
                                            <li>Sports achievements</li>
                                            <li>Leadership roles</li>
                                            <li>Good conduct recognition</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                        Severity Levels
                                    </button>
                                </h2>
                                <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#guidelinesAccordion">
                                    <div class="accordion-body">
                                        <ul class="mb-0">
                                            <li><span class="badge severity-low">Low</span> - Minor issues</li>
                                            <li><span class="badge severity-medium">Medium</span> - Moderate concerns</li>
                                            <li><span class="badge severity-high">High</span> - Serious matters</li>
                                            <li><span class="badge severity-critical">Critical</span> - Urgent attention</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Need Help Card -->
                    <div class="info-card">
                        <h5><i class="fas fa-question-circle me-2"></i>Need Help?</h5>
                        <p class="small text-muted">If you have questions about your discipline records, please contact:</p>
                        <div class="d-flex align-items-center mb-2">
                            <div class="bg-primary text-white rounded-circle p-2 me-2" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div>
                                <strong>Discipline Master</strong><br>
                                <small class="text-muted">Office Hours: All the time.</small>
                            </div>
                        </div>
                     
                    </div>

                    <!-- Important Dates -->
                    <?php if ($stats['pending_followups'] > 0): ?>
                    <div class="info-card border-warning">
                        <h5 class="text-warning"><i class="fas fa-clock me-2"></i>Upcoming Deadlines</h5>
                        <?php
                        // Reset pointer to fetch pending follow-ups
                        mysqli_data_seek($discipline_result, 0);
                        while ($record = mysqli_fetch_assoc($discipline_result)):
                            if ($record['follow_up_required'] && !$record['follow_up_completed'] && $record['follow_up_due_date']):
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <div>
                                <small class="d-block"><?php echo htmlspecialchars($record['record_type']); ?></small>
                                <small class="text-muted">Due: <?php echo date('M d, Y', strtotime($record['follow_up_due_date'])); ?></small>
                            </div>
                            <span class="badge bg-<?php echo strtotime($record['follow_up_due_date']) < time() ? 'danger' : 'warning'; ?>">
                                <?php echo strtotime($record['follow_up_due_date']) < time() ? 'Overdue' : 'Pending'; ?>
                            </span>
                        </div>
                        <?php 
                            endif;
                        endwhile; 
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../controller/footer.php'; ?>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>
