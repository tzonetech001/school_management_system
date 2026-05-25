<?php
// results_entry_five.php
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user has permission (Head Master or Second Master only)
$admin_id = $_SESSION['admin_id'] ?? 0;

// Get current user's roles
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

// Check if user has Head Master (1) or Second Master (2) role
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 3) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view staff members.";
    header("Location: ../404.php");
    exit();
}

// Get all roles from database (excluding Super Admin if it exists)
$roles = [];
$roles_sql = "SELECT * FROM admin_roles WHERE role_name != 'Super Admin' ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_sql);
if ($roles_result && mysqli_num_rows($roles_result) > 0) {
    while ($row = mysqli_fetch_assoc($roles_result)) {
        $roles[] = $row;
    }
}

// Get admin ID for audit fields
$admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;

// Get current active exam type
$current_exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Get ONLY ACTIVE exam types for Form Five
$exam_types_sql = "SELECT * FROM exam_types WHERE form_level = 'Form Five' AND is_active = 1 ORDER BY year DESC, id DESC";
$exam_types_result = mysqli_query($conn, $exam_types_sql);
$exam_types = [];
while ($row = mysqli_fetch_assoc($exam_types_result)) {
    $exam_types[] = $row;
}

// If no exam selected, get the most recent ACTIVE one
if ($current_exam_id == 0 && count($exam_types) > 0) {
    $current_exam_id = $exam_types[0]['id'];
}

// Get current exam details
$current_exam = null;
if ($current_exam_id > 0) {
    $exam_sql = "SELECT * FROM exam_types WHERE id = $current_exam_id AND is_active = 1";
    $exam_result = mysqli_query($conn, $exam_sql);
    $current_exam = mysqli_fetch_assoc($exam_result);
}

// If no active exam exists, show message
if (empty($exam_types)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Form Five Results Entry - No Active Exam</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
            .main-content { margin-left: 260px; padding: 20px; }
            @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }
        </style>
    </head>
    <body>
        <?php include '../controller/header.php'; ?>
        <?php include '../controller/sidebar.php'; ?>
        <div class="main-content">
            <div class="container-fluid">
                <div class="alert alert-warning text-center py-5">
                    <i class="fas fa-calendar-times fa-3x mb-3 d-block"></i>
                    <h4>No Active Exam Available</h4>
                    <p>There is no active exam for Form Five at the moment.</p>
                    <p>Please go to <strong>Exam Type Manager</strong> and activate an exam first.</p>
                    <hr>
                    <a href="exam_type_manager.php?form_level=Form%20Five" class="btn btn-primary">Go to Exam Type Manager</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    include '../controller/footer.php';
    exit();
}

// Combination subjects mapping for Form Five - EACH COMBINATION HAS ITS OWN SUBJECTS
$combination_subjects = [
    'HGE' => ['ac', 'htm', 'his', 'geo', 'b_math', 'eco'],
    'HGL' => ['ac', 'htm', 'his', 'geo', 'eng'],
    'HGK' => ['ac', 'htm', 'his', 'geo', 'kisw'],
    'HKL' => ['ac', 'htm', 'his', 'kisw', 'eng'],
    'KLF' => ['ac', 'htm', 'kisw', 'eng', 'fren'],
    'EGM' => ['ac', 'htm', 'geo', 'adv_m', 'eco'],
    'HLF' => ['ac', 'htm', 'his', 'eng', 'fren'],
    'HGF' => ['ac', 'htm', 'his', 'geo', 'fren']
];

// Subjects that count for points calculation (exclude AC and HTM)
$points_subjects = ['his', 'geo', 'kisw', 'eng', 'adv_m', 'eco', 'fren'];

// For each combination, define which subjects are the 3 core subjects for division
$combination_core_subjects = [
    'HGE' => ['his', 'geo', 'eco'],
    'HGL' => ['his', 'geo', 'eng'],
    'HGK' => ['his', 'geo', 'kisw'],
    'HKL' => ['his', 'kisw', 'eng'],
    'KLF' => ['kisw', 'eng', 'fren'],
    'EGM' => ['geo', 'adv_m', 'eco'],
    'HLF' => ['his', 'eng', 'fren'],
    'HGF' => ['his', 'geo', 'fren']
];

// Subject display names
$subject_display = [
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

// All subjects list (for table headers)
$all_subjects = ['ac', 'htm', 'his', 'geo', 'kisw', 'eng', 'b_math', 'adv_m', 'eco', 'fren'];

// Get all Form Five students
$students_sql = "SELECT s.id, s.index_number, s.first_name, s.last_name, s.second_name, s.sex, s.combination,
                    fr.ac, fr.htm, fr.his, fr.geo, fr.kisw, fr.eng, fr.b_math, fr.adv_m, fr.eco, fr.fren,
                    fr.total_points, fr.average, fr.division, fr.updated_at
                FROM students s
                LEFT JOIN form_five_results fr ON s.id = fr.student_id AND fr.exam_type_id = $current_exam_id
                WHERE s.class = 'Form Five' AND (s.is_leaver IS NULL OR s.is_leaver = FALSE)
                ORDER BY 
                    FIELD(s.combination, 'HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'),
                    CASE WHEN s.sex = 'Female' THEN 1 ELSE 2 END,
                    s.first_name, s.last_name";

$students_result = mysqli_query($conn, $students_sql);
$students = [];
while ($row = mysqli_fetch_assoc($students_result)) {
    $students[] = $row;
}

// ========== AJAX HANDLERS ==========

// Handle single subject auto-save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    
    $student_id = intval($_POST['student_id']);
    $exam_type_id = intval($_POST['exam_type_id']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $marks = ($_POST['marks'] !== '' && $_POST['marks'] !== null) ? intval($_POST['marks']) : null;
    
    // Validate marks
    if ($marks !== null && ($marks < 0 || $marks > 100)) {
        echo json_encode(['success' => false, 'error' => 'Marks must be between 0 and 100']);
        exit();
    }
    
    // Check if record exists
    $check_sql = "SELECT id FROM form_five_results WHERE student_id = $student_id AND exam_type_id = $exam_type_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Update existing record
        if ($marks !== null) {
            $update_sql = "UPDATE form_five_results SET `$subject` = $marks, updated_at = CURRENT_TIMESTAMP 
                          WHERE student_id = $student_id AND exam_type_id = $exam_type_id";
        } else {
            $update_sql = "UPDATE form_five_results SET `$subject` = NULL, updated_at = CURRENT_TIMESTAMP 
                          WHERE student_id = $student_id AND exam_type_id = $exam_type_id";
        }
        
        if (!mysqli_query($conn, $update_sql)) {
            echo json_encode(['success' => false, 'error' => 'Update failed: ' . mysqli_error($conn)]);
            exit();
        }
    } else {
        // Insert new record
        if ($marks !== null) {
            $insert_sql = "INSERT INTO form_five_results (student_id, exam_type_id, `$subject`, entered_by, entered_at) 
                          VALUES ($student_id, $exam_type_id, $marks, $admin_id, NOW())";
        } else {
            $insert_sql = "INSERT INTO form_five_results (student_id, exam_type_id, entered_by, entered_at) 
                          VALUES ($student_id, $exam_type_id, $admin_id, NOW())";
        }
        
        if (!mysqli_query($conn, $insert_sql)) {
            echo json_encode(['success' => false, 'error' => 'Insert failed: ' . mysqli_error($conn)]);
            exit();
        }
    }
    
    // Recalculate totals
    recalculateStudentResults($conn, $student_id, $exam_type_id);
    
    // Get updated data
    $updated_data = getStudentResults($conn, $student_id, $exam_type_id);
    
    echo json_encode(['success' => true, 'data' => $updated_data]);
    exit();
}

// ========== HELPER FUNCTIONS ==========

function getStudentResults($conn, $student_id, $exam_type_id) {
    $sql = "SELECT total_points, average, division FROM form_five_results 
            WHERE student_id = $student_id AND exam_type_id = $exam_type_id";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result);
}

function getCombinationCoreSubjects($combination) {
    $combination_core = [
        'HGE' => ['his', 'geo', 'eco'],
        'HGL' => ['his', 'geo', 'eng'],
        'HGK' => ['his', 'geo', 'kisw'],
        'HKL' => ['his', 'kisw', 'eng'],
        'KLF' => ['kisw', 'eng', 'fren'],
        'EGM' => ['geo', 'adv_m', 'eco'],
        'HLF' => ['his', 'eng', 'fren'],
        'HGF' => ['his', 'geo', 'fren']
    ];
    return $combination_core[$combination] ?? [];
}

function getCombinationSubjectsList($combination) {
    $combination_subjects = [
        'HGE' => ['ac', 'htm', 'his', 'geo', 'b_math', 'eco'],
        'HGL' => ['ac', 'htm', 'his', 'geo', 'eng'],
        'HGK' => ['ac', 'htm', 'his', 'geo', 'kisw'],
        'HKL' => ['ac', 'htm', 'his', 'kisw', 'eng'],
        'KLF' => ['ac', 'htm', 'kisw', 'eng', 'fren'],
        'EGM' => ['ac', 'htm', 'geo', 'adv_m', 'eco'],
        'HLF' => ['ac', 'htm', 'his', 'eng', 'fren'],
        'HGF' => ['ac', 'htm', 'his', 'geo', 'fren']
    ];
    return $combination_subjects[$combination] ?? [];
}

function recalculateStudentResults($conn, $student_id, $exam_type_id) {
    // Get student combination
    $comb_sql = "SELECT combination FROM students WHERE id = $student_id";
    $comb_result = mysqli_query($conn, $comb_sql);
    $student = mysqli_fetch_assoc($comb_result);
    
    if (!$student) return;
    
    $combination = $student['combination'];
    
    // Get subjects for this specific combination
    $combination_subjects = getCombinationSubjectsList($combination);
    $core_subjects = getCombinationCoreSubjects($combination);
    
    if (empty($combination_subjects)) return;
    
    // Build select query for combination subjects only
    $select_fields = [];
    foreach ($combination_subjects as $subject) {
        $select_fields[] = "IFNULL(`$subject`, 0) as `$subject`";
    }
    
    $sql = "SELECT " . implode(', ', $select_fields) . " 
            FROM form_five_results 
            WHERE student_id = $student_id AND exam_type_id = $exam_type_id";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    if (!$row) return;
    
    // Calculate points ONLY for eligible subjects (exclude AC, b_math and HTM)
    $points_array = [];
    $total_marks = 0;
    $subjects_count = 0;
    
    // Also track which core subjects have marks
    $core_subjects_entered = 0;
    $core_subjects_data = [];
    
    foreach ($combination_subjects as $subject) {
        $marks = intval($row[$subject]);
        if ($marks > 0) {
            // Only add to total marks and count for average (ALL subjects in combination)
            $total_marks += $marks;
            $subjects_count++;
            
            // Track core subjects
            if (in_array($subject, $core_subjects)) {
                $core_subjects_entered++;
                $core_subjects_data[] = $marks;
            }
            
            // Calculate points only if subject is in points_subjects (exclude AC, b_math and HTM)
            if (in_array($subject, ['his', 'geo', 'kisw', 'eng', 'adv_m', 'eco', 'fren'])) {
                $points = getGradePoints($marks);
                $points_array[] = $points;
            }
        }
    }
    
    // Sort points (1 is best, 7 is worst)
    sort($points_array);
    
    // Take best 3 subjects for total points (Division calculation)
    $best_points = array_slice($points_array, 0, min(3, count($points_array)));
    $total_points = array_sum($best_points);
    
    // Average is for ALL subjects in combination (including AC, HTM, b_math)
    $average = $subjects_count > 0 ? round($total_marks / $subjects_count, 2) : null;
    
    // Calculate division based on total points AND core subjects entered
    // Division should only be displayed if ALL 3 core subjects for the combination have marks
    $division = 'Not Assigned';
    
    // Get the core subjects for this combination
    $required_core_count = count($core_subjects);
    
    if ($required_core_count > 0 && $core_subjects_entered >= $required_core_count) {
        // All core subjects have marks, calculate division
        $division = calculateDivisionFromPoints($total_points);
    } else {
        // Not enough core subjects entered
        $division = 'Not Assigned';
    }
    
    // Update totals
    $update_sql = "UPDATE form_five_results 
                   SET total_points = " . ($total_points > 0 ? $total_points : 'NULL') . ", 
                       average = " . ($average !== null ? $average : 'NULL') . ", 
                       division = " . ($division !== 'Not Assigned' ? "'$division'" : 'NULL') . ",
                       updated_at = CURRENT_TIMESTAMP
                   WHERE student_id = $student_id AND exam_type_id = $exam_type_id";
    
    mysqli_query($conn, $update_sql);
}

function calculateDivisionFromPoints($points) {
    // Division I: 3 - 9 points
    if ($points >= 3 && $points <= 9) return 'Division I';
    // Division II: 10 - 12 points
    if ($points >= 10 && $points <= 12) return 'Division II';
    // Division III: 13 - 17 points
    if ($points >= 13 && $points <= 17) return 'Division III';
    // Division IV: 18 - 19 points
    if ($points >= 18 && $points <= 19) return 'Division IV';
    // Division 0: 20 - 21 points
    if ($points >= 20 && $points <= 21) return 'Division 0';
    return 'Not Assigned';
}

function getGradePoints($marks) {
    if ($marks >= 80) return 1;
    if ($marks >= 70) return 2;
    if ($marks >= 60) return 3;
    if ($marks >= 50) return 4;
    if ($marks >= 40) return 5;
    if ($marks >= 35) return 6;
    return 7;
}

function getGradeLetter($marks) {
    if ($marks >= 80) return 'A';
    if ($marks >= 70) return 'B';
    if ($marks >= 60) return 'C';
    if ($marks >= 50) return 'D';
    if ($marks >= 40) return 'E';
    if ($marks >= 35) return 'S';
    return 'F';
}

// Check if subject is in student's combination
function isSubjectInCombination($combination, $subject) {
    $combination_subjects = getCombinationSubjectsList($combination);
    return in_array($subject, $combination_subjects);
}

// Get core subjects for a combination (for display purposes)
function getCombinationCoreSubjectsDisplay($combination) {
    $core_subjects = getCombinationCoreSubjects($combination);
    $subject_names = [
        'his' => 'HIST',
        'geo' => 'GEO',
        'kisw' => 'KISW',
        'eng' => 'ENG',
        'adv_m' => 'ADV/M',
        'eco' => 'ECO',
        'fren' => 'FREN'
    ];
    $display = [];
    foreach ($core_subjects as $subj) {
        $display[] = $subject_names[$subj] ?? strtoupper($subj);
    }
    return implode(', ', $display);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Five Results Entry - Auto Save</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --primary-dark: #1a2632;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --max-score-border: #dc3545;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            overflow-x: auto;
        }

        .main-content {
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        .results-table-container {
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: relative;
        }

        .results-table {
            min-width: 1300px;
            font-size: 13px;
        }

        .results-table thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            font-weight: 600;
            padding: 12px 8px;
            text-align: center;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .results-table tbody td {
            padding: 10px 8px;
            text-align: center;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }

        .results-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .results-table tbody tr.active-row {
            background-color: #fff3cd;
        }

        .subject-input {
            width: 70px;
            text-align: center;
            padding: 6px 4px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s;
        }

        .subject-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(44, 62, 80, 0.1);
        }

        .subject-input:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .subject-input.saving {
            background-color: #fff3cd;
            border-color: var(--warning-color);
        }

        .subject-input.saved {
            background-color: #d4edda;
            border-color: var(--success-color);
        }

        .subject-input.max-score {
            border: 2px solid var(--max-score-border);
            background-color: #fff5f5;
        }

        .badge-grade {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            margin-top: 4px;
        }

        .grade-A { background: #27ae60; color: white; }
        .grade-B { background: #2ecc71; color: white; }
        .grade-C { background: #f39c12; color: white; }
        .grade-D { background: #e67e22; color: white; }
        .grade-E { background: #95a5a6; color: white; }
        .grade-S { background: #7f8c8d; color: white; }
        .grade-F { background: #e74c3c; color: white; }

        .division-i { background: #27ae60; color: white; }
        .division-ii { background: #2ecc71; color: white; }
        .division-iii { background: #f39c12; color: white; }
        .division-iv { background: #e67e22; color: white; }
        .division-0 { background: #e74c3c; color: white; }

        .exam-selector {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .auto-save-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--success-color);
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 12px;
            z-index: 1000;
            display: none;
            align-items: center;
            gap: 8px;
        }

        .auto-save-indicator.show {
            display: flex;
        }

        .auto-save-indicator.error {
            background: var(--danger-color);
        }

        .loading-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .nav-arrows {
            display: flex;
            gap: 10px;
            margin-left: 15px;
        }

        .nav-arrow {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .nav-arrow:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .nav-arrow:active {
            transform: scale(0.95);
        }

        .combination-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .combination-HGE { background: #3498db; color: white; }
        .combination-HGL { background: #2ecc71; color: white; }
        .combination-HGK { background: #f39c12; color: white; }
        .combination-HKL { background: #e74c3c; color: white; }
        .combination-KLF { background: #9b59b6; color: white; }
        .combination-EGM { background: #1abc9c; color: white; }
        .combination-HLF { background: #e67e22; color: white; }
        .combination-HGF { background: #34495e; color: white; }

        .info-note {
            background: #e8f4fd;
            border-left: 4px solid var(--info-color);
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 12px;
            margin-top: 10px;
        }

        .pending-subjects {
            font-size: 10px;
            color: #e74c3c;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h2 class="page-title">
                    <i class="fas fa-table-list me-2"></i>
                    Form Five Results Entry
                </h2>
                <div class="d-flex gap-2">
                    <a href="exam_type_manager.php?form_level=Form%20Five" class="btn btn-outline-secondary">
                        <i class="fas fa-cog me-2"></i>Manage Exams
                    </a>
                </div>
            </div>

            <!-- Exam Selector - Only Active Exams -->
            <div class="exam-selector">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <label class="fw-bold">Select Active Exam:</label>
                            <select id="examSelector" class="form-select w-auto">
                                <option value="">-- Select Exam --</option>
                                <?php foreach ($exam_types as $exam): ?>
                                    <option value="<?php echo $exam['id']; ?>" <?php echo $current_exam_id == $exam['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($exam['exam_name']); ?> (<?php echo $exam['year']; ?>)
                                        <?php echo $exam['is_active'] ? '[Active]' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="nav-arrows">
                                <div class="nav-arrow" id="arrowUp" title="Move Up (↑)">
                                    <i class="fas fa-arrow-up"></i>
                                </div>
                                <div class="nav-arrow" id="arrowDown" title="Move Down (↓)">
                                    <i class="fas fa-arrow-down"></i>
                                </div>
                                <div class="nav-arrow" id="arrowLeft" title="Move Left (←)">
                                    <i class="fas fa-arrow-left"></i>
                                </div>
                                <div class="nav-arrow" id="arrowRight" title="Move Right (→)">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-md-end gap-2 mt-3 mt-md-0">
                            <div class="input-group w-auto">
                                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search...">
                                <button class="btn btn-outline-secondary btn-sm" type="button" id="clearSearchBtn">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <select id="combinationFilter" class="form-select form-select-sm w-auto">
                                <option value="">All Combinations</option>
                                <option value="HGE">HGE</option>
                                <option value="HGL">HGL</option>
                                <option value="HGK">HGK</option>
                                <option value="HKL">HKL</option>
                                <option value="KLF">KLF</option>
                                <option value="EGM">EGM</option>
                                <option value="HLF">HLF</option>
                                <option value="HGF">HGF</option>
                            </select>
                            <a href="?exam_id=<?php echo $current_exam_id; ?>" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-sync me-1"></i>Refresh
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if ($current_exam): ?>
                    <div class="mt-3 pt-2 border-top">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span><i class="fas fa-calendar-alt me-1 text-muted"></i> <strong><?php echo htmlspecialchars($current_exam['exam_name']); ?></strong> - <?php echo $current_exam['year']; ?></span>
                            <span><i class="fas fa-users me-1 text-muted"></i> Total Students: <?php echo count($students); ?></span>
                            <span class="text-success"><i class="fas fa-save me-1"></i> Auto-save to database (1.5s delay)</span>
                            <span><i class="fas fa-arrow-pointer me-1 text-muted"></i> Use arrow keys to navigate</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Results Entry Table -->
            <div class="results-table-container">
                <table class="table results-table" id="resultsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>C.NO</th>
                            <th>NAMES</th>
                            <th>SEX</th>
                            <th>COMBS</th>
                            <th>AC</th>
                            <th>HTM</th>
                            <th>HIST</th>
                            <th>GEO</th>
                            <th>KISW</th>
                            <th>ENG</th>
                            <th>B/MATH</th>
                            <th>ADV/M</th>
                            <th>ECO</th>
                            <th>FREN</th>
                            <th>AVG</th>
                            <th>PTS</th>
                            <th>DIV</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="18" class="text-center py-5">
                                    <i class="fas fa-info-circle fa-2x mb-2 d-block text-muted"></i>
                                    No Form Five students found.
                                    <a href="../student/register.php" class="btn btn-sm btn-primary mt-3">Register Students</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $sn = 1;
                            foreach ($students as $student): 
                                $combination = $student['combination'];
                                $combination_subject_list = getCombinationSubjectsList($combination);
                                $core_subjects_list = getCombinationCoreSubjects($combination);
                                $core_subjects_entered_count = 0;
                                
                                // Count how many core subjects have marks
                                foreach ($core_subjects_list as $core_subj) {
                                    if ($student[$core_subj] !== null && $student[$core_subj] > 0) {
                                        $core_subjects_entered_count++;
                                    }
                                }
                                $core_subjects_required = count($core_subjects_list);
                                $has_all_core_subjects = ($core_subjects_required > 0 && $core_subjects_entered_count >= $core_subjects_required);
                            ?>
                                <tr data-student-id="<?php echo $student['id']; ?>" 
                                    data-combination="<?php echo $combination; ?>" 
                                    data-sex="<?php echo $student['sex']; ?>"
                                    data-core-required="<?php echo $core_subjects_required; ?>"
                                    data-core-entered="<?php echo $core_subjects_entered_count; ?>">
                                    <td><?php echo $sn++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($student['index_number']); ?></strong></td>
                                    <td class="text-start">
                                        <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                        <?php if ($student['second_name']): ?>
                                            <div class="small text-muted"><?php echo htmlspecialchars($student['second_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $student['sex'] == 'Male' ? 'bg-primary' : 'bg-danger'; ?>">
                                            <?php echo $student['sex']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="combination-badge combination-<?php echo $combination; ?>">
                                            <?php echo htmlspecialchars($combination); ?>
                                        </span>
                                        <?php if (!$has_all_core_subjects && $core_subjects_required > 0): ?>
                                            <div class="pending-subjects">
                                               
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- AC -->
                                    <td class="subject-cell" data-subject="ac">
                                        <?php if (in_array('ac', $combination_subject_list)): ?>
                                            <input type="number" 
                                                   class="subject-input subject-ac"
                                                   data-student-id="<?php echo $student['id']; ?>"
                                                   data-subject="ac"
                                                   value="<?php echo $student['ac'] !== null ? $student['ac'] : ''; ?>"
                                                   min="0" max="100"
                                                   step="1"
                                                   placeholder="-">
                                            <?php if ($student['ac'] !== null): ?>
                                                <div class="badge-grade grade-<?php echo getGradeLetter($student['ac']); ?>">
                                                    <?php echo getGradeLetter($student['ac']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- HTM -->
                                    <td class="subject-cell" data-subject="htm">
                                        <?php if (in_array('htm', $combination_subject_list)): ?>
                                            <input type="number" 
                                                   class="subject-input subject-htm"
                                                   data-student-id="<?php echo $student['id']; ?>"
                                                   data-subject="htm"
                                                   value="<?php echo $student['htm'] !== null ? $student['htm'] : ''; ?>"
                                                   min="0" max="100"
                                                   step="1"
                                                   placeholder="-">
                                            <?php if ($student['htm'] !== null): ?>
                                                <div class="badge-grade grade-<?php echo getGradeLetter($student['htm']); ?>">
                                                    <?php echo getGradeLetter($student['htm']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- HIST -->
                                    <td class="subject-cell" data-subject="his">
                                        <?php if (in_array('his', $combination_subject_list)): ?>
                                            <input type="number" 
                                                   class="subject-input subject-his"
                                                   data-student-id="<?php echo $student['id']; ?>"
                                                   data-subject="his"
                                                   value="<?php echo $student['his'] !== null ? $student['his'] : ''; ?>"
                                                   min="0" max="100"
                                                   step="1"
                                                   placeholder="-">
                                            <?php if ($student['his'] !== null): ?>
                                                <div class="badge-grade grade-<?php echo getGradeLetter($student['his']); ?>">
                                                    <?php echo getGradeLetter($student['his']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- GEO -->
                                    <td class="subject-cell" data-subject="geo">
                                        <?php if (in_array('geo', $combination_subject_list)): ?>
                                            <input type="number" 
                                                   class="subject-input subject-geo"
                                                   data-student-id="<?php echo $student['id']; ?>"
                                                   data-subject="geo"
                                                   value="<?php echo $student['geo'] !== null ? $student['geo'] : ''; ?>"
                                                   min="0" max="100"
                                                   step="1"
                                                   placeholder="-">
                                            <?php if ($student['geo'] !== null): ?>
                                                <div class="badge-grade grade-<?php echo getGradeLetter($student['geo']); ?>">
                                                    <?php echo getGradeLetter($student['geo']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- KISW -->
                                    <td class="subject-cell" data-subject="kisw">
                                        <?php if (in_array('kisw', $combination_subject_list)): ?>
                                            <input type="number" 
                                                   class="subject-input subject-kisw"
                                                   data-student-id="<?php echo $student['id']; ?>"
                                                   data-subject="kisw"
                                                   value="<?php echo $student['kisw'] !== null ? $student['kisw'] : ''; ?>"
                                                   min="0" max="100"
                                                   step="1"
                                                   placeholder="-">
                                            <?php if ($student['kisw'] !== null): ?>
                                                <div class="badge-grade grade-<?php echo getGradeLetter($student['kisw']); ?>">
                                                    <?php echo getGradeLetter($student['kisw']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- ENG -->
                                    <td class="subject-cell" data-subject="eng">
                                        <?php if (in_array('eng', $combination_subject_list)): ?>
                                            <input type="number" 
                                                   class="subject-input subject-eng"
                                                   data-student-id="<?php echo $student['id']; ?>"
                                                   data-subject="eng"
                                                   value="<?php echo $student['eng'] !== null ? $student['eng'] : ''; ?>"
                                                   min="0" max="100"
                                                   step="1"
                                                   placeholder="-">
                                            <?php if ($student['eng'] !== null): ?>
                                                <div class="badge-grade grade-<?php echo getGradeLetter($student['eng']); ?>">
                                                    <?php echo getGradeLetter($student['eng']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- B/MATH -->
                                    <td class="subject-cell" data-subject="b_math">
                                        <?php if (in_array('b_math', $combination_subject_list)): ?>
                                            <input type="number" 
                                                   class="subject-input subject-b_math"
                                                   data-student-id="<?php echo $student['id']; ?>"
                                                   data-subject="b_math"
                                                   value="<?php echo $student['b_math'] !== null ? $student['b_math'] : ''; ?>"
                                                   min="0" max="100"
                                                   step="1"
                                                   placeholder="-">
                                            <?php if ($student['b_math'] !== null): ?>
                                                <div class="badge-grade grade-<?php echo getGradeLetter($student['b_math']); ?>">
                                                    <?php echo getGradeLetter($student['b_math']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- ADV/M -->
                                    <td class="subject-cell" data-subject="adv_m">
                                        <?php if (in_array('adv_m', $combination_subject_list)): ?>
                                            <input type="number" 
                                                   class="subject-input subject-adv_m"
                                                   data-student-id="<?php echo $student['id']; ?>"
                                                   data-subject="adv_m"
                                                   value="<?php echo $student['adv_m'] !== null ? $student['adv_m'] : ''; ?>"
                                                   min="0" max="100"
                                                   step="1"
                                                   placeholder="-">
                                            <?php if ($student['adv_m'] !== null): ?>
                                                <div class="badge-grade grade-<?php echo getGradeLetter($student['adv_m']); ?>">
                                                    <?php echo getGradeLetter($student['adv_m']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- ECO -->
                                    <td class="subject-cell" data-subject="eco">
                                        <?php if (in_array('eco', $combination_subject_list)): ?>
                                            <input type="number" 
                                                   class="subject-input subject-eco"
                                                   data-student-id="<?php echo $student['id']; ?>"
                                                   data-subject="eco"
                                                   value="<?php echo $student['eco'] !== null ? $student['eco'] : ''; ?>"
                                                   min="0" max="100"
                                                   step="1"
                                                   placeholder="-">
                                            <?php if ($student['eco'] !== null): ?>
                                                <div class="badge-grade grade-<?php echo getGradeLetter($student['eco']); ?>">
                                                    <?php echo getGradeLetter($student['eco']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- FREN -->
                                    <td class="subject-cell" data-subject="fren">
                                        <?php if (in_array('fren', $combination_subject_list)): ?>
                                            <input type="number" 
                                                   class="subject-input subject-fren"
                                                   data-student-id="<?php echo $student['id']; ?>"
                                                   data-subject="fren"
                                                   value="<?php echo $student['fren'] !== null ? $student['fren'] : ''; ?>"
                                                   min="0" max="100"
                                                   step="1"
                                                   placeholder="-">
                                            <?php if ($student['fren'] !== null): ?>
                                                <div class="badge-grade grade-<?php echo getGradeLetter($student['fren']); ?>">
                                                    <?php echo getGradeLetter($student['fren']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Average -->
                                    <td class="average-cell" data-student-id="<?php echo $student['id']; ?>">
                                        <strong><?php echo $student['average'] !== null ? number_format($student['average'], 1) : '-'; ?></strong>
                                    </td>
                                    
                                    <!-- Points -->
                                    <td class="points-cell" data-student-id="<?php echo $student['id']; ?>">
                                        <strong><?php echo $student['total_points'] !== null ? $student['total_points'] : '-'; ?></strong>
                                    </td>
                                    
                                    <!-- Division -->
                                    <td class="division-cell" data-student-id="<?php echo $student['id']; ?>">
                                        <?php if ($student['division'] && $student['division'] != 'Not Assigned' && $has_all_core_subjects): ?>
                                            <span class="badge <?php echo str_replace(' ', '-', strtolower($student['division'])); ?>">
                                                <?php echo $student['division']; ?>
                                            </span>
                                        <?php elseif (!$has_all_core_subjects && $core_subjects_required > 0): ?>
                                            <span class="badge bg-warning" title="Need all core subjects (<?php echo getCombinationCoreSubjectsDisplay($combination); ?>)">
                                                Pending (<?php echo $core_subjects_entered_count; ?>/<?php echo $core_subjects_required; ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                 </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                 </table>
            </div>
        </div>
    </div>

    <!-- Auto-save Indicator -->
    <div id="autoSaveIndicator" class="auto-save-indicator">
        <div class="loading-spinner"></div>
        <span id="autoSaveText">Saving...</span>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <script>
        let saveTimeouts = {};
        let currentRow = 0;
        let currentCol = 0;
        let allInputs = [];

        // Core subjects mapping for each combination
        const combinationCoreSubjects = {
            'HGE': ['his', 'geo', 'eco'],
            'HGL': ['his', 'geo', 'eng'],
            'HGK': ['his', 'geo', 'kisw'],
            'HKL': ['his', 'kisw', 'eng'],
            'KLF': ['kisw', 'eng', 'fren'],
            'EGM': ['geo', 'adv_m', 'eco'],
            'HLF': ['his', 'eng', 'fren'],
            'HGF': ['his', 'geo', 'fren']
        };

        // Check if all core subjects have marks for a student
        function hasAllCoreSubjects(studentId) {
            const row = $(`tr[data-student-id="${studentId}"]`);
            const combination = row.data('combination');
            const coreSubjects = combinationCoreSubjects[combination] || [];
            
            if (coreSubjects.length === 0) return true;
            
            let allHaveMarks = true;
            coreSubjects.forEach(subject => {
                const input = row.find(`.subject-${subject}`);
                const val = input.val();
                if (!val || val === '') {
                    allHaveMarks = false;
                }
            });
            return allHaveMarks;
        }

        // Count how many core subjects have marks
        function getCoreSubjectsEnteredCount(studentId) {
            const row = $(`tr[data-student-id="${studentId}"]`);
            const combination = row.data('combination');
            const coreSubjects = combinationCoreSubjects[combination] || [];
            
            let count = 0;
            coreSubjects.forEach(subject => {
                const input = row.find(`.subject-${subject}`);
                const val = input.val();
                if (val && val !== '') {
                    count++;
                }
            });
            return count;
        }

        function getAllInputs() {
            const inputs = [];
            $('.results-table tbody tr').each(function(rowIndex) {
                $(this).find('.subject-input:not(:disabled)').each(function(colIndex) {
                    inputs.push({
                        element: this,
                        row: rowIndex,
                        col: colIndex,
                        studentId: $(this).data('student-id'),
                        subject: $(this).data('subject')
                    });
                });
            });
            return inputs;
        }

        function showAutoSaveIndicator(message, isError = false) {
            const indicator = $('#autoSaveIndicator');
            $('#autoSaveText').text(message);
            if (isError) {
                indicator.addClass('error');
            } else {
                indicator.removeClass('error');
            }
            indicator.addClass('show');
            setTimeout(() => indicator.removeClass('show'), 2000);
        }

        function updateGradeDisplay(input, marks) {
            const cell = input.closest('td');
            const marksNum = parseInt(marks);
            let grade = '';
            let gradeClass = '';
            
            if (marksNum >= 80) { grade = 'A'; gradeClass = 'grade-A'; }
            else if (marksNum >= 70) { grade = 'B'; gradeClass = 'grade-B'; }
            else if (marksNum >= 60) { grade = 'C'; gradeClass = 'grade-C'; }
            else if (marksNum >= 50) { grade = 'D'; gradeClass = 'grade-D'; }
            else if (marksNum >= 40) { grade = 'E'; gradeClass = 'grade-E'; }
            else if (marksNum >= 35) { grade = 'S'; gradeClass = 'grade-S'; }
            else { grade = 'F'; gradeClass = 'grade-F'; }
            
            cell.find('.badge-grade').remove();
            if (marks !== '') {
                cell.append(`<div class="badge-grade ${gradeClass}">${grade}</div>`);
            }
        }

        function updateMaxScoreBorder(input, marks) {
            if (marks === 100 || marks === '100') {
                input.addClass('max-score');
            } else {
                input.removeClass('max-score');
            }
        }

        function updateStudentStats(studentId, data) {
            if (data.average !== undefined) {
                $(`.average-cell[data-student-id="${studentId}"]`).html(`<strong>${data.average !== null ? parseFloat(data.average).toFixed(1) : '-'}</strong>`);
            }
            if (data.total_points !== undefined) {
                $(`.points-cell[data-student-id="${studentId}"]`).html(`<strong>${data.total_points !== null ? data.total_points : '-'}</strong>`);
            }
            if (data.division !== undefined) {
                const divisionCell = $(`.division-cell[data-student-id="${studentId}"]`);
                if (data.division && data.division !== 'Not Assigned') {
                    const divClass = data.division.toLowerCase().replace(' ', '-');
                    divisionCell.html(`<span class="badge ${divClass}">${data.division}</span>`);
                } else {
                    // Check if we have all core subjects but still no division
                    if (hasAllCoreSubjects(studentId)) {
                        divisionCell.html(`<span class="badge bg-secondary">Not Assigned</span>`);
                    } else {
                        const coreCount = getCoreSubjectsEnteredCount(studentId);
                        const row = $(`tr[data-student-id="${studentId}"]`);
                        const combination = row.data('combination');
                        const coreSubjects = combinationCoreSubjects[combination] || [];
                        const totalNeeded = coreSubjects.length;
                        divisionCell.html(`<span class="badge bg-warning">Pending (${coreCount}/${totalNeeded})</span>`);
                    }
                }
            }
        }

        function performDatabaseSave(studentId, subject, marks) {
            const input = $(`input[data-student-id="${studentId}"][data-subject="${subject}"]`);
            input.addClass('saving');
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    ajax_save: 1,
                    student_id: studentId,
                    exam_type_id: <?php echo $current_exam_id; ?>,
                    subject: subject,
                    marks: marks === '' ? '' : marks
                },
                dataType: 'json',
                success: function(response) {
                    input.removeClass('saving');
                    if (response.success) {
                        input.addClass('saved');
                        setTimeout(() => input.removeClass('saved'), 1000);
                        showAutoSaveIndicator('Saved!', false);
                        if (response.data) {
                            updateStudentStats(studentId, response.data);
                        }
                        if (marks !== '') {
                            updateGradeDisplay(input, marks);
                        }
                    } else {
                        showAutoSaveIndicator('Error: ' + (response.error || 'Unknown error'), true);
                    }
                },
                error: function(xhr, status, error) {
                    input.removeClass('saving');
                    showAutoSaveIndicator('Error saving!', true);
                    console.error('AJAX Error:', error, xhr.responseText);
                }
            });
        }

        function autoSaveToDatabase(studentId, subject, marks) {
            const key = `${studentId}_${subject}`;
            
            if (saveTimeouts[key]) {
                clearTimeout(saveTimeouts[key]);
            }
            
            saveTimeouts[key] = setTimeout(function() {
                performDatabaseSave(studentId, subject, marks);
                delete saveTimeouts[key];
            }, 1500);
        }

        // Navigation functions
        function focusCurrentCell() {
            allInputs = getAllInputs();
            const currentInput = allInputs.find(inp => inp.row === currentRow && inp.col === currentCol);
            if (currentInput) {
                currentInput.element.focus();
                $(currentInput.element).closest('tr').addClass('active-row');
                currentInput.element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        function clearActiveRow() {
            $('.results-table tbody tr').removeClass('active-row');
        }

        function moveRight() {
            allInputs = getAllInputs();
            const currentIndex = allInputs.findIndex(inp => inp.row === currentRow && inp.col === currentCol);
            if (currentIndex !== -1 && currentIndex + 1 < allInputs.length) {
                clearActiveRow();
                currentRow = allInputs[currentIndex + 1].row;
                currentCol = allInputs[currentIndex + 1].col;
                focusCurrentCell();
            }
        }

        function moveLeft() {
            allInputs = getAllInputs();
            const currentIndex = allInputs.findIndex(inp => inp.row === currentRow && inp.col === currentCol);
            if (currentIndex > 0) {
                clearActiveRow();
                currentRow = allInputs[currentIndex - 1].row;
                currentCol = allInputs[currentIndex - 1].col;
                focusCurrentCell();
            }
        }

        function moveDown() {
            allInputs = getAllInputs();
            const nextInput = allInputs.find(inp => inp.row > currentRow && inp.col === currentCol);
            if (nextInput) {
                clearActiveRow();
                currentRow = nextInput.row;
                currentCol = nextInput.col;
                focusCurrentCell();
            }
        }

        function moveUp() {
            allInputs = getAllInputs();
            const prevInput = [...allInputs].reverse().find(inp => inp.row < currentRow && inp.col === currentCol);
            if (prevInput) {
                clearActiveRow();
                currentRow = prevInput.row;
                currentCol = prevInput.col;
                focusCurrentCell();
            }
        }

        $(document).ready(function() {
            allInputs = getAllInputs();
            
            // Apply max-score border to existing 100 values on page load
            $('.subject-input').each(function() {
                const val = $(this).val();
                if (val === '100') {
                    $(this).addClass('max-score');
                }
            });
            
            // Auto-save on input
            $(document).on('input', '.subject-input', function() {
                const studentId = $(this).data('student-id');
                const subject = $(this).data('subject');
                let marks = $(this).val();
                
                if (marks !== '') {
                    marks = parseInt(marks);
                    if (marks < 0) marks = 0;
                    if (marks > 100) marks = 100;
                    $(this).val(marks);
                }
                
                // Update red border for max score (100)
                updateMaxScoreBorder($(this), marks);
                
                autoSaveToDatabase(studentId, subject, marks);
            });
            
            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if ($(':focus').is('.subject-input')) {
                    if (e.key === 'ArrowRight') { e.preventDefault(); moveRight(); }
                    else if (e.key === 'ArrowLeft') { e.preventDefault(); moveLeft(); }
                    else if (e.key === 'ArrowDown') { e.preventDefault(); moveDown(); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); moveUp(); }
                }
            });
            
            // Navigation buttons
            $('#arrowUp').click(moveUp);
            $('#arrowDown').click(moveDown);
            $('#arrowLeft').click(moveLeft);
            $('#arrowRight').click(moveRight);
            
            // Exam selector - only shows active exams
            $('#examSelector').on('change', function() {
                const examId = $(this).val();
                if (examId) {
                    window.location.href = 'results_entry_five.php?exam_id=' + examId;
                }
            });
            
            // Search filter
            $('#searchInput').on('keyup', function() {
                const searchTerm = $(this).val().toLowerCase();
                $('#resultsTable tbody tr').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.includes(searchTerm));
                });
            });
            
            $('#clearSearchBtn').click(function() {
                $('#searchInput').val('').trigger('keyup');
            });
            
            // Combination filter
            $('#combinationFilter').on('change', function() {
                const combination = $(this).val();
                $('#resultsTable tbody tr').each(function() {
                    const rowCombination = $(this).data('combination');
                    const isVisible = combination === '' || rowCombination === combination;
                    $(this).toggle(isVisible);
                    if (isVisible && $('#searchInput').val() !== '') {
                        const text = $(this).text().toLowerCase();
                        $(this).toggle(text.includes($('#searchInput').val().toLowerCase()));
                    }
                });
            });
            
            // Focus first enabled input
            setTimeout(function() {
                const firstInput = $('.subject-input:not(:disabled)').first();
                if (firstInput.length) {
                    firstInput.focus();
                    currentRow = firstInput.closest('tr').index();
                    currentCol = 0;
                    focusCurrentCell();
                }
            }, 500);
        });
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>