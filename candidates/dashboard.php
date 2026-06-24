<?php
// candidates/dashboard.php - Student Dashboard
session_start();
require_once '../controller/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// ==================== GET STUDENT INFO ====================
$student_sql = "SELECT s.*, 
                       d.dorm_name,
                       dr.room_number,
                       dr.room_label,
                       sd.bed_number
                FROM students s
                LEFT JOIN student_dormitory sd ON s.id = sd.student_id AND sd.status = 'Active'
                LEFT JOIN dormitories d ON sd.dormitory_id = d.id
                LEFT JOIN dormitory_rooms dr ON sd.room_id = dr.id
                WHERE s.id = ?";
$stmt = $conn->prepare($student_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_result = $stmt->get_result();
$student = $student_result->fetch_assoc();
$stmt->close();

if (!$student) {
    header("Location: ../logout.php");
    exit();
}

// ==================== GET NOTIFICATIONS COUNT ====================
$notif_sql = "SELECT COUNT(*) as count 
              FROM notifications n
              LEFT JOIN notification_views nv ON n.id = nv.notification_id AND nv.viewer_id = ? AND nv.viewer_type = 'student'
              WHERE n.status = 'active' 
              AND (n.visibility = 'public' OR n.visibility = 'students_only')
              AND nv.id IS NULL";
$stmt = $conn->prepare($notif_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$notif_result = $stmt->get_result();
$notif_count = $notif_result->fetch_assoc()['count'] ?? 0;
$stmt->close();

// ==================== GET MAINTENANCE ITEMS ====================
$maintenance_sql = "SELECT COUNT(*) as count FROM maintenance_assignments WHERE student_id = ? AND status = 'active'";
$stmt = $conn->prepare($maintenance_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$maintenance_result = $stmt->get_result();
$maintenance_count = $maintenance_result->fetch_assoc()['count'] ?? 0;
$stmt->close();

// ==================== GET DISCIPLINE RECORDS ====================
$discipline_sql = "SELECT COUNT(*) as count FROM discipline_records WHERE student_id = ? AND status = 'active'";
$stmt = $conn->prepare($discipline_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$discipline_result = $stmt->get_result();
$discipline_count = $discipline_result->fetch_assoc()['count'] ?? 0;
$stmt->close();

// ==================== GET LATEST EXAM RESULTS ====================
// Check Form Five results first
$result_sql = "SELECT fr.*, et.exam_name, et.exam_code, et.term, et.year
               FROM form_five_results fr
               JOIN exam_types et ON fr.exam_type_id = et.id
               WHERE fr.student_id = ? AND et.is_active = 1
               ORDER BY fr.entered_at DESC
               LIMIT 1";
$stmt = $conn->prepare($result_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$latest_result = $result->fetch_assoc();
$stmt->close();

// If no Form Five results, check Form Six
if (!$latest_result) {
    $result_sql = "SELECT fr.*, et.exam_name, et.exam_code, et.term, et.year
                   FROM form_six_results fr
                   JOIN exam_types et ON fr.exam_type_id = et.id
                   WHERE fr.student_id = ? AND et.is_active = 1
                   ORDER BY fr.entered_at DESC
                   LIMIT 1";
    $stmt = $conn->prepare($result_sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $latest_result = $result->fetch_assoc();
    $stmt->close();
}

// ==================== GET PAYMENT STATUS ====================
// Get fee settings for this school
$total_fee = 80000; // Default
$school_id = $student['school_id'] ?? null;
if ($school_id) {
    $settings_sql = "SELECT total_fee FROM fee_settings WHERE school_id = ? ORDER BY updated_at DESC LIMIT 1";
    $settings_stmt = $conn->prepare($settings_sql);
    $settings_stmt->bind_param("i", $school_id);
    $settings_stmt->execute();
    $settings_result = $settings_stmt->get_result();
    if ($settings_row = $settings_result->fetch_assoc()) {
        $total_fee = floatval($settings_row['total_fee']);
    }
    $settings_stmt->close();
}

// Get payments
$payment = ['contribution_status' => 'Not Paid', 'contribution_paid' => 0, 'contribution_balance' => $total_fee];

$payment_sql = "SELECT 
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as contribution_paid,
                    ? as contribution_balance,
                    CASE 
                        WHEN COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) >= ? THEN 'Paid'
                        WHEN COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) > 0 THEN 'Partially Paid'
                        ELSE 'Not Paid'
                    END as contribution_status
                FROM student_payments 
                WHERE student_id = ? AND school_id = ?";
$payment_stmt = $conn->prepare($payment_sql);
$payment_stmt->bind_param("ddii", $total_fee, $total_fee, $student_id, $school_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
$payment_data = $payment_result->fetch_assoc();
$payment_stmt->close();

if ($payment_data) {
    $payment = $payment_data;
    $payment['contribution_balance'] = max(0, $total_fee - ($payment['contribution_paid'] ?? 0));
}

// ==================== GET DORMITORY INFO ====================
// Check if dormitory info exists with null safety
$has_dormitory = !empty($student['dorm_name']);
$dorm_name = $student['dorm_name'] ?? 'Not Assigned';
$room_number = $student['room_number'] ?? 'N/A';
$bed_number = $student['bed_number'] ?? 'Not assigned';

// ==================== GET PROFILE IMAGE ====================
$profile_image = '';
if (!empty($student['profile_image']) && file_exists("../uploads/student_profiles/" . $student['profile_image'])) {
    $profile_image = "../uploads/student_profiles/" . $student['profile_image'];
}

$full_name = $student['first_name'] . ' ' . $student['last_name'];
if (!empty($student['second_name'])) {
    $full_name = $student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name'];
}

$initials = strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));

// Get current date and time
$current_time = date('H:i');
$greeting = 'Good Morning';
if ($current_time >= '12:00' && $current_time < '17:00') {
    $greeting = 'Good Afternoon';
} elseif ($current_time >= '17:00' && $current_time < '21:00') {
    $greeting = 'Good Evening';
} elseif ($current_time >= '21:00' || $current_time < '05:00') {
    $greeting = 'Good Night';
}

// Get school name
$school_name = "School Management System";
if (!empty($student['school_id'])) {
    $school_sql = "SELECT school_name FROM schools WHERE id = ?";
    $stmt = $conn->prepare($school_sql);
    $stmt->bind_param("i", $student['school_id']);
    $stmt->execute();
    $school_result = $stmt->get_result();
    if ($school_row = $school_result->fetch_assoc()) {
        $school_name = $school_row['school_name'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($school_name); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #3B9DB3;
            --primary-dark: #2d7c8f;
            --primary-light: #8bc5d6;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --white: #ffffff;
            --light: #f8f9fa;
            --dark: #212529;
            --text-muted: #6c757d;
            --border-radius: 16px;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 40px rgba(0,0,0,0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            color: var(--dark);
            overflow-x: hidden;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 24px 30px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        @media (max-width: 991px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
        }

        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
        }

        .greeting-section {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .greeting-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }

        .greeting-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .greeting-text h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }

        .greeting-text .greeting {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 400;
        }

        .greeting-text .student-class {
            font-size: 13px;
            color: var(--primary-color);
            font-weight: 500;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification-btn {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--white);
            border: none;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            font-size: 18px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .notification-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: var(--primary-color);
            color: var(--white);
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--danger-color);
            color: white;
            font-size: 10px;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
            border: 2px solid var(--white);
        }

        /* Quick Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px 22px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.04);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 12px;
        }

        .stat-card .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.2;
        }

        .stat-card .stat-label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 400;
            margin-top: 2px;
        }

        .stat-card .stat-link {
            font-size: 12px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 8px;
        }

        .stat-card .stat-link:hover {
            color: var(--primary-dark);
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .card-custom {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(0,0,0,0.04);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card-custom:hover {
            box-shadow: var(--shadow-md);
        }

        .card-custom .card-header {
            padding: 18px 22px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: transparent;
        }

        .card-custom .card-header h5 {
            font-size: 15px;
            font-weight: 600;
            margin: 0;
            color: var(--dark);
        }

        .card-custom .card-header .card-action {
            font-size: 13px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .card-custom .card-header .card-action:hover {
            color: var(--primary-dark);
        }

        .card-custom .card-body {
            padding: 20px 22px;
        }

        /* Latest Result */
        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .result-item:last-child {
            border-bottom: none;
        }

        .result-item .result-info {
            display: flex;
            flex-direction: column;
        }

        .result-item .result-info .result-name {
            font-weight: 600;
            font-size: 14px;
        }

        .result-item .result-info .result-meta {
            font-size: 12px;
            color: var(--text-muted);
        }

        .result-item .result-score {
            text-align: right;
        }

        .result-item .result-score .score {
            font-weight: 700;
            font-size: 18px;
        }

        .result-item .result-score .score.good {
            color: var(--success-color);
        }

        .result-item .result-score .score.average {
            color: var(--warning-color);
        }

        .result-item .result-score .score.poor {
            color: var(--danger-color);
        }

        .result-item .result-score .score-label {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px 12px;
            border-radius: 12px;
            background: var(--light);
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--dark);
            font-size: 12px;
            font-weight: 500;
            gap: 6px;
        }

        .quick-action-btn:hover {
            background: var(--primary-color);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .quick-action-btn i {
            font-size: 22px;
        }

        .quick-action-btn .action-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: rgba(59, 157, 179, 0.1);
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .quick-action-btn:hover .action-icon {
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
        }

        /* Dormitory Info */
        .dorm-info {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            background: var(--light);
            border-radius: 12px;
            margin-top: 8px;
        }

        .dorm-info .dorm-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(59, 157, 179, 0.1);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .dorm-info .dorm-details {
            flex: 1;
        }

        .dorm-info .dorm-details .dorm-name {
            font-weight: 600;
            font-size: 14px;
        }

        .dorm-info .dorm-details .dorm-room {
            font-size: 13px;
            color: var(--text-muted);
        }

        .dorm-info .dorm-bed {
            font-size: 12px;
            background: var(--primary-color);
            color: var(--white);
            padding: 2px 10px;
            border-radius: 20px;
        }

        /* Payment Status */
        .payment-status {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            margin-top: 8px;
        }

        .payment-status.paid {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .payment-status.partial {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .payment-status.unpaid {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .payment-status .payment-icon {
            font-size: 20px;
        }

        .payment-status .payment-details {
            flex: 1;
        }

        .payment-status .payment-details .payment-label {
            font-size: 13px;
            font-weight: 500;
        }

        .payment-status .payment-details .payment-amount {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
        }

        .empty-state i {
            font-size: 40px;
            color: #ddd;
            margin-bottom: 12px;
        }

        .empty-state h6 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .empty-state p {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-in {
            animation: fadeInUp 0.6s ease forwards;
        }

        .animate-in:nth-child(2) { animation-delay: 0.1s; }
        .animate-in:nth-child(3) { animation-delay: 0.2s; }
        .animate-in:nth-child(4) { animation-delay: 0.3s; }
        .animate-in:nth-child(5) { animation-delay: 0.4s; }
        .animate-in:nth-child(6) { animation-delay: 0.5s; }

        /* Responsive */
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 14px 16px;
            }

            .stat-card .stat-number {
                font-size: 20px;
            }

            .greeting-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .greeting-avatar {
                width: 44px;
                height: 44px;
                font-size: 18px;
            }

            .greeting-text h1 {
                font-size: 18px;
            }

            .quick-actions {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .quick-action-btn {
                padding: 12px 8px;
                font-size: 11px;
            }

            .quick-action-btn .action-icon {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }
        }

        @media (max-width: 380px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>
<?php include 'sidebar_student.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div class="greeting-section">
                <div class="greeting-avatar">
                    <?php if ($profile_image): ?>
                        <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="<?php echo htmlspecialchars($full_name); ?>">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div class="greeting-text">
                    <span class="greeting"><?php echo $greeting; ?> 👋</span>
                    <h1><?php echo htmlspecialchars($student['first_name']); ?></h1>
                    <span class="student-class">
                        <?php echo htmlspecialchars($student['class'] . ' - ' . $student['combination']); ?>
                    </span>
                </div>
            </div>
            <div class="header-actions">
                <a href="notifications.php" class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <?php if ($notif_count > 0): ?>
                        <span class="notification-badge"><?php echo $notif_count > 9 ? '9+' : $notif_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card animate-in">
                <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: var(--info-color);">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-number">
                    <?php echo $latest_result ? ($latest_result['average'] ? number_format($latest_result['average'], 1) . '%' : 'N/A') : 'N/A'; ?>
                </div>
                <div class="stat-label">Latest Average</div>
                <?php if ($latest_result): ?>
                    <a href="results.php" class="stat-link">
                        View Results <i class="fas fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>

            <div class="stat-card animate-in">
                <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: var(--success-color);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number">
                    <?php echo $payment['contribution_status'] ?? 'Not Paid'; ?>
                </div>
                <div class="stat-label">Payment Status</div>
                <a href="fees.php" class="stat-link">
                    View Details <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="stat-card animate-in">
                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: var(--warning-color);">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-number"><?php echo $maintenance_count; ?></div>
                <div class="stat-label">Assigned Items</div>
                <a href="maintenance.php" class="stat-link">
                    View Items <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="stat-card animate-in">
                <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: var(--danger-color);">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <div class="stat-number"><?php echo $discipline_count; ?></div>
                <div class="stat-label">Discipline Records</div>
                <a href="discipline.php" class="stat-link">
                    View Records <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Latest Results -->
                <div class="card-custom animate-in">
                    <div class="card-header">
                        <h5><i class="fas fa-graduation-cap me-2" style="color: var(--primary-color);"></i>Latest Exam Results</h5>
                        <?php if ($latest_result): ?>
                            <a href="results.php" class="card-action">View All</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($latest_result): ?>
                            <div class="result-item">
                                <div class="result-info">
                                    <span class="result-name"><?php echo htmlspecialchars($latest_result['exam_name']); ?></span>
                                    <span class="result-meta">
                                        <?php echo htmlspecialchars($latest_result['exam_code']); ?> • 
                                        Term <?php echo htmlspecialchars($latest_result['term']); ?> • 
                                        <?php echo $latest_result['year']; ?>
                                    </span>
                                </div>
                                <div class="result-score">
                                    <div class="score <?php 
                                        $avg = $latest_result['average'] ?? 0;
                                        echo $avg >= 70 ? 'good' : ($avg >= 50 ? 'average' : 'poor');
                                    ?>">
                                        <?php echo $avg ? number_format($avg, 1) . '%' : 'N/A'; ?>
                                    </div>
                                    <div class="score-label">
                                        <?php echo $latest_result['division'] ?? 'N/A'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Subject breakdown -->
                            <?php 
                            $subjects = ['ac', 'htm', 'his', 'geo', 'kisw', 'eng', 'b_math', 'adv_m', 'eco', 'fren'];
                            $subject_labels = ['AC', 'HTM', 'HIST', 'GEO', 'KISW', 'ENG', 'B/MATH', 'ADV/M', 'ECO', 'FREN'];
                            $display_subjects = [];
                            for ($i = 0; $i < count($subjects); $i++) {
                                if (isset($latest_result[$subjects[$i]]) && $latest_result[$subjects[$i]] !== null && $latest_result[$subjects[$i]] !== '') {
                                    $display_subjects[] = ['label' => $subject_labels[$i], 'marks' => $latest_result[$subjects[$i]]];
                                }
                            }
                            if (count($display_subjects) > 0):
                            ?>
                            <div style="margin-top: 12px; display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px;">
                                <?php foreach ($display_subjects as $subj): ?>
                                    <div style="text-align: center; padding: 6px 4px; background: var(--light); border-radius: 8px;">
                                        <div style="font-size: 11px; font-weight: 600; color: var(--text-muted);"><?php echo $subj['label']; ?></div>
                                        <div style="font-size: 15px; font-weight: 700; color: <?php echo $subj['marks'] >= 70 ? 'var(--success-color)' : ($subj['marks'] >= 50 ? 'var(--warning-color)' : 'var(--danger-color)'); ?>;">
                                            <?php echo $subj['marks']; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard-list"></i>
                                <h6>No Results Yet</h6>
                                <p>Your exam results will appear here once available.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card-custom animate-in" style="margin-top: 20px;">
                    <div class="card-header">
                        <h5><i class="fas fa-rocket me-2" style="color: var(--primary-color);"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="profile.php" class="quick-action-btn">
                                <span class="action-icon"><i class="fas fa-user-circle"></i></span>
                                My Profile
                            </a>
                            <a href="my_dormitory.php" class="quick-action-btn">
                                <span class="action-icon"><i class="fas fa-bed"></i></span>
                                My Dormitory
                            </a>
                            <a href="maintenance.php" class="quick-action-btn">
                                <span class="action-icon"><i class="fas fa-tools"></i></span>
                                Maintenance
                            </a>
                            <a href="fees.php" class="quick-action-btn">
                                <span class="action-icon"><i class="fas fa-money-bill-wave"></i></span>
                                Contributions
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Dormitory Info -->
                <div class="card-custom animate-in">
                    <div class="card-header">
                        <h5><i class="fas fa-bed me-2" style="color: var(--primary-color);"></i>Dormitory Info</h5>
                        <a href="my_dormitory.php" class="card-action">View</a>
                    </div>
                    <div class="card-body">
                        <?php if ($has_dormitory): ?>
                            <div class="dorm-info">
                                <div class="dorm-icon">
                                    <i class="fas fa-<?php echo $student['sex'] == 'Male' ? 'male' : 'female'; ?>"></i>
                                </div>
                                <div class="dorm-details">
                                    <div class="dorm-name"><?php echo htmlspecialchars($dorm_name); ?></div>
                                    <div class="dorm-room">Room <?php echo htmlspecialchars($room_number); ?></div>
                                </div>
                                <?php if ($bed_number && $bed_number != 'Not assigned'): ?>
                                    <span class="dorm-bed">Bed <?php echo htmlspecialchars($bed_number); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-bed"></i>
                                <h6>Not Assigned</h6>
                                <p>You haven't been assigned a dormitory yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Status -->
                <div class="card-custom animate-in" style="margin-top: 20px;">
                    <div class="card-header">
                        <h5><i class="fas fa-money-bill-wave me-2" style="color: var(--success-color);"></i>Payment Status</h5>
                        <a href="fees.php" class="card-action">View</a>
                    </div>
                    <div class="card-body">
                        <?php 
                        $status = $payment['contribution_status'] ?? 'Not Paid';
                        $status_class = 'unpaid';
                        $status_icon = 'fa-times-circle';
                        $status_color = 'var(--danger-color)';
                        
                        if ($status == 'Paid') {
                            $status_class = 'paid';
                            $status_icon = 'fa-check-circle';
                            $status_color = 'var(--success-color)';
                        } elseif ($status == 'Partially Paid') {
                            $status_class = 'partial';
                            $status_icon = 'fa-exclamation-circle';
                            $status_color = 'var(--warning-color)';
                        }
                        ?>
                        <div class="payment-status <?php echo $status_class; ?>">
                            <span class="payment-icon" style="color: <?php echo $status_color; ?>;">
                                <i class="fas <?php echo $status_icon; ?>"></i>
                            </span>
                            <div class="payment-details">
                                <div class="payment-label"><?php echo $status; ?></div>
                                <div class="payment-amount">
                                    Paid: TZS <?php echo number_format($payment['contribution_paid'] ?? 0, 0); ?> • 
                                    Balance: TZS <?php echo number_format($payment['contribution_balance'] ?? $total_fee, 0); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Student Info -->
                <div class="card-custom animate-in" style="margin-top: 20px;">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2" style="color: var(--primary-color);"></i>Student Info</h5>
                    </div>
                    <div class="card-body" style="padding: 16px 22px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; font-size: 13px;">
                            <div><strong>Index No:</strong></div>
                            <div><?php echo htmlspecialchars($student['index_number'] ?? 'N/A'); ?></div>
                            
                            <div><strong>Admission:</strong></div>
                            <div><?php echo htmlspecialchars($student['admission_number'] ?? 'N/A'); ?></div>
                            
                            <div><strong>Class:</strong></div>
                            <div><?php echo htmlspecialchars($student['class']); ?></div>
                            
                            <div><strong>Combination:</strong></div>
                            <div><?php echo htmlspecialchars($student['combination']); ?></div>
                            
                            <div><strong>Gender:</strong></div>
                            <div><?php echo htmlspecialchars($student['sex']); ?></div>
                            
                            <div><strong>Status:</strong></div>
                            <div>
                                <span class="badge <?php echo $student['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $student['status'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 32px; font-size: 13px; color: var(--text-muted); border-top: 1px solid rgba(0,0,0,0.06); padding-top: 20px;">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_name); ?> • Student Dashboard
        </div>
    </div>
</div>

<?php include '../controller/footer.php'; ?>
</body>
</html>