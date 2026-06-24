<?php
// candidates/results.php - Student Results Viewer
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
                       dr.room_number
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

$full_name = $student['first_name'] . ' ' . $student['last_name'];
if (!empty($student['second_name'])) {
    $full_name = $student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name'];
}

// ==================== GET ALL EXAM TYPES FOR STUDENT ====================
// Get Form Five exams
$form_five_exams_sql = "SELECT DISTINCT et.id, et.exam_name, et.exam_code, et.term, et.year, et.is_active,
                               fr.average, fr.total_points, fr.division, fr.entered_at
                        FROM exam_types et
                        JOIN form_five_results fr ON et.id = fr.exam_type_id
                        WHERE fr.student_id = ? 
                        ORDER BY et.year DESC, et.id DESC";
$stmt = $conn->prepare($form_five_exams_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$form_five_exams_result = $stmt->get_result();
$form_five_exams = [];
while ($row = $form_five_exams_result->fetch_assoc()) {
    $row['form_level'] = 'Form Five';
    $form_five_exams[] = $row;
}
$stmt->close();

// Get Form Six exams
$form_six_exams_sql = "SELECT DISTINCT et.id, et.exam_name, et.exam_code, et.term, et.year, et.is_active,
                               fr.average, fr.total_points, fr.division, fr.entered_at
                        FROM exam_types et
                        JOIN form_six_results fr ON et.id = fr.exam_type_id
                        WHERE fr.student_id = ? 
                        ORDER BY et.year DESC, et.id DESC";
$stmt = $conn->prepare($form_six_exams_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$form_six_exams_result = $stmt->get_result();
$form_six_exams = [];
while ($row = $form_six_exams_result->fetch_assoc()) {
    $row['form_level'] = 'Form Six';
    $form_six_exams[] = $row;
}
$stmt->close();

// Merge and sort all exams by year (newest first)
$all_exams = array_merge($form_five_exams, $form_six_exams);
usort($all_exams, function($a, $b) {
    if ($a['year'] != $b['year']) {
        return $b['year'] - $a['year'];
    }
    return $b['id'] - $a['id'];
});

// ==================== GET SELECTED EXAM ====================
$selected_exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// If no exam selected, use the most recent one
if ($selected_exam_id == 0 && !empty($all_exams)) {
    $selected_exam_id = $all_exams[0]['id'];
}

// Get selected exam details
$selected_exam = null;
foreach ($all_exams as $exam) {
    if ($exam['id'] == $selected_exam_id) {
        $selected_exam = $exam;
        break;
    }
}

// ==================== GET SUBJECT RESULTS FOR SELECTED EXAM ====================
$subject_results = [];
$subject_names = [
    'ac' => 'Academic Communication',
    'htm' => 'Historia ya Tanzania na Maadili',
    'his' => 'History',
    'geo' => 'Geography',
    'kisw' => 'Kiswahili',
    'eng' => 'English',
    'b_math' => 'Basic Mathematics',
    'adv_m' => 'Advanced Mathematics',
    'eco' => 'Economics',
    'fren' => 'French'
];
$subject_short = [
    'ac' => 'AC',
    'htm' => 'HTM',
    'his' => 'HIST',
    'geo' => 'GEO',
    'kisw' => 'KISW',
    'eng' => 'ENG',
    'b_math' => 'B/MATH',
    'adv_m' => 'ADV/M',
    'eco' => 'ECO',
    'fren' => 'FREN'
];

if ($selected_exam) {
    $form_level = $selected_exam['form_level'];
    $table = ($form_level == 'Form Five') ? 'form_five_results' : 'form_six_results';
    
    $subjects_sql = "SELECT ac, htm, his, geo, kisw, eng, b_math, adv_m, eco, fren,
                            total_points, average, division
                     FROM $table
                     WHERE student_id = ? AND exam_type_id = ?";
    $stmt = $conn->prepare($subjects_sql);
    $stmt->bind_param("ii", $student_id, $selected_exam_id);
    $stmt->execute();
    $subjects_result = $stmt->get_result();
    $subject_data = $subjects_result->fetch_assoc();
    $stmt->close();
    
    if ($subject_data) {
        foreach ($subject_names as $key => $name) {
            if (isset($subject_data[$key]) && $subject_data[$key] !== null && $subject_data[$key] !== '') {
                $subject_results[] = [
                    'code' => $key,
                    'short' => $subject_short[$key],
                    'name' => $name,
                    'marks' => intval($subject_data[$key]),
                    'grade' => getGradeLetter($subject_data[$key])
                ];
            }
        }
        $selected_exam['total_points'] = $subject_data['total_points'];
        $selected_exam['average'] = $subject_data['average'];
        $selected_exam['division'] = $subject_data['division'];
    }
}

// ==================== HELPER FUNCTIONS ====================
function getGradeLetter($marks) {
    if ($marks >= 80) return 'A';
    if ($marks >= 70) return 'B';
    if ($marks >= 60) return 'C';
    if ($marks >= 50) return 'D';
    if ($marks >= 40) return 'E';
    if ($marks >= 35) return 'S';
    return 'F';
}

function getGradeColor($marks) {
    if ($marks >= 80) return '#27ae60';
    if ($marks >= 70) return '#2ecc71';
    if ($marks >= 60) return '#f39c12';
    if ($marks >= 50) return '#e67e22';
    if ($marks >= 40) return '#3498db';
    if ($marks >= 35) return '#95a5a6';
    return '#e74c3c';
}

function getGradeClass($marks) {
    if ($marks >= 80) return 'grade-a';
    if ($marks >= 70) return 'grade-b';
    if ($marks >= 60) return 'grade-c';
    if ($marks >= 50) return 'grade-d';
    if ($marks >= 40) return 'grade-e';
    if ($marks >= 35) return 'grade-s';
    return 'grade-f';
}

function getDivisionColor($division) {
    if (strpos($division, 'I') !== false) return '#27ae60';
    if (strpos($division, 'II') !== false) return '#2ecc71';
    if (strpos($division, 'III') !== false) return '#f39c12';
    if (strpos($division, 'IV') !== false) return '#e67e22';
    return '#e74c3c';
}

// ==================== CALCULATE POSITION ====================
function getStudentPosition($student_id, $exam_id, $conn) {
    // Get the form level for this exam
    $exam_sql = "SELECT form_level FROM exam_types WHERE id = ?";
    $stmt = $conn->prepare($exam_sql);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $exam_result = $stmt->get_result();
    $exam = $exam_result->fetch_assoc();
    $stmt->close();
    
    if (!$exam) return 'N/A';
    
    $table = ($exam['form_level'] == 'Form Five') ? 'form_five_results' : 'form_six_results';
    
    // Get student's average
    $avg_sql = "SELECT average FROM $table WHERE student_id = ? AND exam_type_id = ?";
    $stmt = $conn->prepare($avg_sql);
    $stmt->bind_param("ii", $student_id, $exam_id);
    $stmt->execute();
    $avg_result = $stmt->get_result();
    $avg_data = $avg_result->fetch_assoc();
    $stmt->close();
    
    if (!$avg_data || $avg_data['average'] === null) return 'N/A';
    
    $student_avg = $avg_data['average'];
    
    // Count students with higher average
    $pos_sql = "SELECT COUNT(*) + 1 as position 
                FROM $table 
                WHERE exam_type_id = ? AND average > ? AND average IS NOT NULL";
    $stmt = $conn->prepare($pos_sql);
    $stmt->bind_param("id", $exam_id, $student_avg);
    $stmt->execute();
    $pos_result = $stmt->get_result();
    $pos_data = $pos_result->fetch_assoc();
    $stmt->close();
    
    return $pos_data['position'] ?? 'N/A';
}

// Get position for selected exam
$position = 'N/A';
$total_students = 0;
if ($selected_exam) {
    $position = getStudentPosition($student_id, $selected_exam_id, $conn);
    
    // Get total students for this exam
    $table = ($selected_exam['form_level'] == 'Form Five') ? 'form_five_results' : 'form_six_results';
    $total_sql = "SELECT COUNT(DISTINCT student_id) as total FROM $table WHERE exam_type_id = ? AND average IS NOT NULL";
    $stmt = $conn->prepare($total_sql);
    $stmt->bind_param("i", $selected_exam_id);
    $stmt->execute();
    $total_result = $stmt->get_result();
    $total_data = $total_result->fetch_assoc();
    $total_students = $total_data['total'] ?? 0;
    $stmt->close();
}

// ==================== GET SCHOOL NAME ====================
$school_name = "Muyovozi High School";
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

// ==================== GET PROFILE IMAGE ====================
$profile_image = '';
if (!empty($student['profile_image']) && file_exists("../uploads/student_profiles/" . $student['profile_image'])) {
    $profile_image = "../uploads/student_profiles/" . $student['profile_image'];
}
$initials = strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));

// Calculate summary statistics
$total_exams = count($all_exams);
$best_average = 0;
$best_division = 'N/A';
$passed_exams = 0;

foreach ($all_exams as $exam) {
    if ($exam['average'] !== null) {
        if ($exam['average'] > $best_average) {
            $best_average = $exam['average'];
            $best_division = $exam['division'] ?? 'N/A';
        }
        if ($exam['average'] >= 50) {
            $passed_exams++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results - <?php echo htmlspecialchars($school_name); ?></title>
    
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
            --success-color: #27ae60;
            --danger-color: #dc3545;
            --warning-color: #f39c12;
            --info-color: #3498db;
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

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
        }

        .page-header h2 {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
        }

        .page-header .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 10px;
            background: var(--white);
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.04);
        }

        .back-btn:hover {
            transform: translateX(-4px);
            box-shadow: var(--shadow-md);
            color: var(--primary-color);
        }

        /* Student Info Bar */
        .student-info-bar {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 16px 22px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .student-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }

        .student-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .student-info-text {
            flex: 1;
        }

        .student-info-text .name {
            font-weight: 600;
            font-size: 16px;
            margin: 0;
        }

        .student-info-text .details {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
        }

        .student-info-text .details span {
            display: inline-block;
            margin-right: 16px;
        }

        /* Exam Selector */
        .exam-selector {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 16px 22px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .exam-selector label {
            font-weight: 600;
            font-size: 14px;
            margin: 0;
            color: var(--dark);
        }

        .exam-selector select {
            padding: 8px 16px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            font-size: 14px;
            font-weight: 500;
            background: var(--white);
            min-width: 250px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .exam-selector select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 157, 179, 0.1);
        }

        .exam-selector .exam-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .exam-selector .exam-badge.active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .exam-selector .exam-badge.inactive {
            background: rgba(108, 117, 125, 0.1);
            color: var(--text-muted);
        }

        /* Results Grid */
        .results-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        @media (max-width: 768px) {
            .results-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Summary Card */
        .summary-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px 22px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(0,0,0,0.04);
        }

        .summary-card .card-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-card .summary-value {
            font-size: 28px;
            font-weight: 700;
        }

        .summary-card .summary-label {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .summary-card .summary-division {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 16px;
            margin-top: 4px;
        }

        /* Subject Results */
        .subject-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(0,0,0,0.04);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .subject-card .card-header {
            padding: 16px 22px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: transparent;
        }

        .subject-card .card-header h5 {
            font-size: 15px;
            font-weight: 600;
            margin: 0;
        }

        .subject-card .card-body {
            padding: 16px 22px 22px;
        }

        /* Subject Grid */
        .subject-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }

        .subject-item {
            padding: 14px 16px;
            border-radius: 12px;
            background: var(--light);
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .subject-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
            border-color: rgba(59, 157, 179, 0.1);
        }

        .subject-item .subject-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--dark);
        }

        .subject-item .subject-code {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 400;
        }

        .subject-item .subject-marks {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .subject-item .subject-grade {
            font-size: 13px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            color: white;
        }

        .grade-a { background: #27ae60; color: white; }
        .grade-b { background: #2ecc71; color: white; }
        .grade-c { background: #f39c12; color: white; }
        .grade-d { background: #e67e22; color: white; }
        .grade-e { background: #3498db; color: white; }
        .grade-s { background: #95a5a6; color: white; }
        .grade-f { background: #e74c3c; color: white; }

        .division-i { background: #27ae60; color: white; }
        .division-ii { background: #2ecc71; color: white; }
        .division-iii { background: #f39c12; color: white; }
        .division-iv { background: #e67e22; color: white; }
        .division-0 { background: #e74c3c; color: white; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state i {
            font-size: 56px;
            color: #ddd;
            margin-bottom: 16px;
        }

        .empty-state h4 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--text-muted);
            max-width: 400px;
            margin: 0 auto;
        }

        /* Exam History */
        .exam-history {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(0,0,0,0.04);
            overflow: hidden;
            margin-top: 24px;
        }

        .exam-history .card-header {
            padding: 16px 22px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: transparent;
        }

        .exam-history .card-header h5 {
            font-size: 15px;
            font-weight: 600;
            margin: 0;
        }

        .exam-history .table-wrap {
            padding: 0 22px 20px;
            overflow-x: auto;
        }

        .exam-history table {
            width: 100%;
            font-size: 13px;
        }

        .exam-history table th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 12px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }

        .exam-history table td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }

        .exam-history table tr:last-child td {
            border-bottom: none;
        }

        .exam-history table tr:hover {
            background: rgba(0,0,0,0.02);
        }

        .exam-history .exam-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .exam-history .exam-link:hover {
            text-decoration: underline;
        }

        .exam-history .badge-avg {
            font-weight: 600;
        }

        /* Summary Stats */
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .summary-stat {
            background: var(--white);
            border-radius: 12px;
            padding: 14px 18px;
            box-shadow: var(--shadow-sm);
            text-align: center;
            border: 1px solid rgba(0,0,0,0.04);
        }

        .summary-stat .stat-number {
            font-size: 22px;
            font-weight: 700;
        }

        .summary-stat .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
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
            animation: fadeInUp 0.5s ease forwards;
        }

        .animate-in:nth-child(2) { animation-delay: 0.1s; }
        .animate-in:nth-child(3) { animation-delay: 0.2s; }
        .animate-in:nth-child(4) { animation-delay: 0.3s; }

        /* Responsive */
        @media (max-width: 576px) {
            .subject-grid {
                grid-template-columns: 1fr;
            }

            .exam-selector select {
                min-width: 100%;
            }

            .summary-stats {
                grid-template-columns: 1fr 1fr;
            }

            .student-info-bar {
                flex-direction: column;
                text-align: center;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
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
        <div class="page-header">
            <div>
                <h2><i class="fas fa-graduation-cap me-2" style="color: var(--primary-color);"></i>My Results</h2>
                <p style="font-size: 14px; color: var(--text-muted); margin: 4px 0 0 0;">View your academic performance across all exams</p>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Student Info Bar -->
        <div class="student-info-bar">
            <div class="student-avatar">
                <?php if ($profile_image): ?>
                    <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="<?php echo htmlspecialchars($full_name); ?>">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div class="student-info-text">
                <div class="name"><?php echo htmlspecialchars($full_name); ?></div>
                <div class="details">
                    <span><i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($student['index_number'] ?? 'N/A'); ?></span>
                    <span><i class="fas fa-graduation-cap me-1"></i> <?php echo htmlspecialchars($student['class']); ?></span>
                    <span><i class="fas fa-layer-group me-1"></i> <?php echo htmlspecialchars($student['combination']); ?></span>
                </div>
            </div>
        </div>

        <?php if (empty($all_exams)): ?>
            <!-- No Results -->
            <div class="empty-state" style="background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm); padding: 60px 20px;">
                <i class="fas fa-clipboard-list"></i>
                <h4>No Results Available</h4>
                <p>You don't have any exam results yet. Results will appear here once they are published by your teachers.</p>
            </div>
        <?php else: ?>

        <!-- Summary Stats -->
        <div class="summary-stats">
            <div class="summary-stat animate-in">
                <div class="stat-number" style="color: var(--primary-color);"><?php echo $total_exams; ?></div>
                <div class="stat-label">Total Exams</div>
            </div>
            <div class="summary-stat animate-in">
                <div class="stat-number" style="color: var(--success-color);"><?php echo $passed_exams; ?></div>
                <div class="stat-label">Exams Passed</div>
            </div>
            <div class="summary-stat animate-in">
                <div class="stat-number" style="color: var(--warning-color);"><?php echo $best_average > 0 ? number_format($best_average, 1) . '%' : 'N/A'; ?></div>
                <div class="stat-label">Best Average</div>
            </div>
            <div class="summary-stat animate-in">
                <div class="stat-number" style="color: var(--info-color);"><?php echo $best_division != 'N/A' ? $best_division : 'N/A'; ?></div>
                <div class="stat-label">Best Division</div>
            </div>
        </div>

        <!-- Exam Selector -->
        <div class="exam-selector">
            <label for="examSelect"><i class="fas fa-calendar-alt me-2" style="color: var(--primary-color);"></i>Select Exam:</label>
            <select id="examSelect" onchange="window.location.href='results.php?exam_id=' + this.value">
                <?php foreach ($all_exams as $exam): ?>
                    <option value="<?php echo $exam['id']; ?>" <?php echo $selected_exam_id == $exam['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($exam['exam_name']); ?> (<?php echo $exam['year']; ?>) - <?php echo $exam['form_level']; ?>
                        <?php if ($exam['is_active']): ?>[Active]<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($selected_exam): ?>
                <span class="exam-badge <?php echo $selected_exam['is_active'] ? 'active' : 'inactive'; ?>">
                    <i class="fas fa-<?php echo $selected_exam['is_active'] ? 'check-circle' : 'clock'; ?> me-1"></i>
                    <?php echo $selected_exam['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($selected_exam): ?>
            
        <!-- Results Grid -->
        <div class="results-grid">
            <!-- Summary Card -->
            <div class="summary-card animate-in">
                <div class="card-title">
                    <i class="fas fa-chart-simple" style="color: var(--primary-color);"></i>
                    Exam Summary
                </div>
                <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
                    <?php echo htmlspecialchars($selected_exam['exam_name']); ?> • 
                    <?php echo $selected_exam['term']; ?> • 
                    <?php echo $selected_exam['year']; ?>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <div class="summary-value" style="color: var(--primary-color);">
                            <?php echo $selected_exam['average'] !== null ? number_format($selected_exam['average'], 1) . '%' : 'N/A'; ?>
                        </div>
                        <div class="summary-label">Average</div>
                    </div>
                    <div>
                        <div class="summary-value" style="color: var(--info-color);">
                            <?php echo $selected_exam['total_points'] !== null ? $selected_exam['total_points'] : 'N/A'; ?>
                        </div>
                        <div class="summary-label">Total Points</div>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div>
                        <span class="summary-division <?php 
                            $div = $selected_exam['division'] ?? 'N/A';
                            if (strpos($div, 'I') !== false) echo 'division-i';
                            elseif (strpos($div, 'II') !== false) echo 'division-ii';
                            elseif (strpos($div, 'III') !== false) echo 'division-iii';
                            elseif (strpos($div, 'IV') !== false) echo 'division-iv';
                            elseif ($div == 'Division 0') echo 'division-0';
                            else echo 'bg-secondary text-white';
                        ?>">
                            <?php echo $selected_exam['division'] ?? 'Not Assigned'; ?>
                        </span>
                    </div>
                    <div style="font-size: 14px; color: var(--text-muted);">
                        <i class="fas fa-trophy me-1"></i>
                        Position: <strong><?php echo $position; ?></strong>
                        <?php if ($total_students > 0): ?>
                            / <?php echo $total_students; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pass/Fail Status -->
            <div class="summary-card animate-in">
                <div class="card-title">
                    <i class="fas fa-flag-checkered" style="color: var(--primary-color);"></i>
                    Performance Status
                </div>
                <?php 
                $avg = $selected_exam['average'] ?? 0;
                $status_text = 'N/A';
                $status_color = 'var(--text-muted)';
                $status_icon = 'fa-minus-circle';
                
                if ($avg > 0) {
                    if ($avg >= 70) {
                        $status_text = 'Excellent Performance! 🎉';
                        $status_color = 'var(--success-color)';
                        $status_icon = 'fa-trophy';
                    } elseif ($avg >= 60) {
                        $status_text = 'Very Good Performance! ⭐';
                        $status_color = 'var(--success-color)';
                        $status_icon = 'fa-star';
                    } elseif ($avg >= 50) {
                        $status_text = 'Good Performance 👍';
                        $status_color = 'var(--warning-color)';
                        $status_icon = 'fa-thumbs-up';
                    } elseif ($avg >= 40) {
                        $status_text = 'Satisfactory 📚';
                        $status_color = 'var(--warning-color)';
                        $status_icon = 'fa-book';
                    } elseif ($avg >= 35) {
                        $status_text = 'Need Improvement 💪';
                        $status_color = 'var(--danger-color)';
                        $status_icon = 'fa-exclamation-triangle';
                    } else {
                        $status_text = 'Requires Attention 🔴';
                        $status_color = 'var(--danger-color)';
                        $status_icon = 'fa-times-circle';
                    }
                }
                ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 8px 0;">
                    <div style="font-size: 40px; color: <?php echo $status_color; ?>;">
                        <i class="fas <?php echo $status_icon; ?>"></i>
                    </div>
                    <div>
                        <div style="font-size: 20px; font-weight: 700; color: <?php echo $status_color; ?>;">
                            <?php echo $status_text; ?>
                        </div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <?php if ($avg > 0): ?>
                                Average: <strong><?php echo number_format($avg, 1); ?>%</strong>
                            <?php else: ?>
                                No marks entered yet
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subject Results -->
        <div class="subject-card animate-in">
            <div class="card-header">
                <h5><i class="fas fa-book-open me-2" style="color: var(--primary-color);"></i>Subject Performance</h5>
                <span style="font-size: 13px; color: var(--text-muted);">
                    <?php echo count($subject_results); ?> subjects
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($subject_results)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-info-circle fa-2x text-muted mb-2 d-block"></i>
                        <p style="color: var(--text-muted); margin: 0;">No subject results available for this exam.</p>
                    </div>
                <?php else: ?>
                    <div class="subject-grid">
                        <?php foreach ($subject_results as $subject): ?>
                            <div class="subject-item">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div>
                                        <div class="subject-name"><?php echo htmlspecialchars($subject['name']); ?></div>
                                        <div class="subject-code"><?php echo $subject['short']; ?></div>
                                    </div>
                                    <span class="subject-grade grade-<?php echo strtolower($subject['grade']); ?>">
                                        <?php echo $subject['grade']; ?>
                                    </span>
                                </div>
                                <div class="subject-marks">
                                    <?php echo $subject['marks']; ?>
                                    <span style="font-size: 14px; font-weight: 400; color: var(--text-muted);">/ 100</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>

        <!-- Exam History -->
        <div class="exam-history animate-in">
            <div class="card-header">
                <h5><i class="fas fa-history me-2" style="color: var(--primary-color);"></i>Exam History</h5>
                <span style="font-size: 13px; color: var(--text-muted);">
                    <?php echo count($all_exams); ?> exams
                </span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Exam Name</th>
                            <th>Form</th>
                            <th>Term</th>
                            <th>Year</th>
                            <th>Average</th>
                            <th>Points</th>
                            <th>Division</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_exams as $exam): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($exam['exam_name']); ?></strong>
                                    <?php if ($exam['is_active']): ?>
                                        <span class="badge bg-success ms-1" style="font-size: 9px;">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($exam['form_level']); ?></td>
                                <td><?php echo htmlspecialchars($exam['term']); ?></td>
                                <td><?php echo $exam['year']; ?></td>
                                <td>
                                    <span class="badge-avg" style="color: <?php 
                                        $avg = $exam['average'] ?? 0;
                                        echo $avg >= 70 ? 'var(--success-color)' : ($avg >= 50 ? 'var(--warning-color)' : 'var(--danger-color)');
                                    ?>;">
                                        <?php echo $exam['average'] !== null ? number_format($exam['average'], 1) . '%' : 'N/A'; ?>
                                    </span>
                                </td>
                                <td><?php echo $exam['total_points'] !== null ? $exam['total_points'] : 'N/A'; ?></td>
                                <td>
                                    <?php if ($exam['division']): ?>
                                        <span class="badge <?php 
                                            $div = $exam['division'];
                                            if (strpos($div, 'I') !== false) echo 'division-i';
                                            elseif (strpos($div, 'II') !== false) echo 'division-ii';
                                            elseif (strpos($div, 'III') !== false) echo 'division-iii';
                                            elseif (strpos($div, 'IV') !== false) echo 'division-iv';
                                            elseif ($div == 'Division 0') echo 'division-0';
                                            else echo 'bg-secondary text-white';
                                        ?>" style="font-size: 11px; padding: 3px 10px;">
                                            <?php echo $exam['division']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" style="font-size: 11px;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="results.php?exam_id=<?php echo $exam['id']; ?>" class="exam-link">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 32px; font-size: 13px; color: var(--text-muted); border-top: 1px solid rgba(0,0,0,0.06); padding-top: 20px;">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_name); ?> • Student Results
        </div>
    </div>
</div>

<?php include '../controller/footer.php'; ?>
</body>
</html>