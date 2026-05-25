<?php
// view_results.php - Student Results Report Viewer (READ ONLY)
session_start();
require_once '../controller/db_connect.php';

// Check login
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

// Get user info for theme
$admin_id = $_SESSION['admin_id'];

// Load theme settings
$theme_settings = [];
$settings_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
$settings_result = mysqli_query($conn, $settings_query);
if ($settings_result && mysqli_num_rows($settings_result) > 0) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $theme_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Default colors
$default_colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
    'primary_light' => '#8bc5d6'
];
$colors = $default_colors;
foreach ($theme_settings as $key => $value) {
    if (array_key_exists($key, $colors)) {
        $colors[$key] = $value;
    }
}

// Get parameters
$selected_form = isset($_GET['form']) ? mysqli_real_escape_string($conn, $_GET['form']) : 'Form five';
$selected_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Normalize form name
$db_form = ($selected_form == 'Form five') ? 'Form five' : 'Form six';
$display_form = ($selected_form == 'Form five') ? 'Form Five' : 'Form Six';

// Get available exam types for selected form
$exam_types_sql = "SELECT id, exam_name, year, is_active FROM exam_types 
                   WHERE form_level = '$db_form' 
                   ORDER BY year DESC, id DESC";
$exam_types_result = mysqli_query($conn, $exam_types_sql);
$exam_types = [];
while ($row = mysqli_fetch_assoc($exam_types_result)) {
    $exam_types[] = $row;
}

// If no exam selected, get the most recent active one
if ($selected_exam == 0 && count($exam_types) > 0) {
    foreach ($exam_types as $exam) {
        if ($exam['is_active']) {
            $selected_exam = $exam['id'];
            break;
        }
    }
    if ($selected_exam == 0) {
        $selected_exam = $exam_types[0]['id'];
    }
}

// Get current exam details
$current_exam = null;
if ($selected_exam > 0) {
    $exam_sql = "SELECT * FROM exam_types WHERE id = $selected_exam";
    $exam_result = mysqli_query($conn, $exam_sql);
    $current_exam = mysqli_fetch_assoc($exam_result);
}

// Determine results table
$results_table = ($db_form == 'Form five') ? 'form_five_results' : 'form_six_results';

// Subject display names (CORRECTED)
$subject_display = [
    'ac' => 'ACADEMIC COMMUNICATION',
    'htm' => 'HISTORIA YA TANZANIA NA MAADILI',
    'his' => 'HISTORY',
    'geo' => 'GEOGRAPHY',
    'kisw' => 'KISWAHILI',
    'eng' => 'ENGLISH',
    'b_math' => 'BASIC MATHEMATICS',
    'adv_m' => 'ADVANCED MATHEMATICS',
    'eco' => 'ECONOMICS',
    'fren' => 'FRENCH'
];

// Subject short names for table headers
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

// Combination subjects mapping
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

// Get all students with results - ORDER BY INDEX NUMBER for main table
$sql = "SELECT s.id, s.index_number, s.first_name, s.last_name, s.second_name, s.sex, s.combination,
               fr.ac, fr.htm, fr.his, fr.geo, fr.kisw, fr.eng, fr.b_math, fr.adv_m, fr.eco, fr.fren,
               fr.total_points, fr.average, fr.division, fr.updated_at
        FROM students s
        LEFT JOIN $results_table fr ON s.id = fr.student_id AND fr.exam_type_id = $selected_exam
        WHERE s.class = '$db_form' AND (s.is_leaver IS NULL OR s.is_leaver = FALSE)";

if ($search_query) {
    $sql .= " AND (s.index_number LIKE '%$search_query%' 
               OR s.first_name LIKE '%$search_query%' 
               OR s.last_name LIKE '%$search_query%')";
}

// ORDER BY INDEX NUMBER ASCENDING for main table
$sql .= " ORDER BY s.index_number ASC";

$students_result = mysqli_query($conn, $sql);
$all_students = [];
while ($row = mysqli_fetch_assoc($students_result)) {
    $all_students[] = $row;
}

// Calculate positions based on average (highest average = position 1)
// First, get students with average for ranking
$students_with_avg = array_filter($all_students, function($s) {
    return $s['average'] !== null;
});

// Sort by average DESC for position calculation
usort($students_with_avg, function($a, $b) {
    if ($a['average'] == $b['average']) {
        // If same average, sort alphabetically by name
        return strcmp($a['first_name'] . ' ' . $a['last_name'], $b['first_name'] . ' ' . $b['last_name']);
    }
    return ($b['average'] <=> $a['average']);
});

// Assign positions (sequential - no ties)
$position_map = [];
foreach ($students_with_avg as $index => $student) {
    $position_map[$student['id']] = $index + 1;
}
$total_students_with_avg = count($students_with_avg);

// Add position to all students
$students_with_position = [];
foreach ($all_students as $student) {
    $student['position'] = $position_map[$student['id']] ?? 'N/A';
    $students_with_position[] = $student;
}

// Get top 10 students (by average, highest first)
$top_10 = array_slice($students_with_avg, 0, 10);

// Get bottom 10 students (by average, lowest first)
$bottom_10 = array_slice(array_reverse($students_with_avg), 0, 10);

// Function to get grade letter
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

// Calculate statistics
$div1_count = count(array_filter($students_with_position, function($s) {
    return $s['division'] === 'Division I';
}));
$div2_count = count(array_filter($students_with_position, function($s) {
    return $s['division'] === 'Division II';
}));
$div3_count = count(array_filter($students_with_position, function($s) {
    return $s['division'] === 'Division III';
}));
$div4_count = count(array_filter($students_with_position, function($s) {
    return $s['division'] === 'Division IV';
}));
$div0_count = count(array_filter($students_with_position, function($s) {
    return $s['division'] === 'Division 0';
}));
$passed = $div1_count + $div2_count + $div3_count;
$pass_rate = count($students_with_position) > 0 ? round(($passed / count($students_with_position)) * 100, 1) : 0;

// Calculate overall average
$total_avg = 0;
$avg_count = 0;
foreach ($students_with_position as $s) {
    if ($s['average']) {
        $total_avg += $s['average'];
        $avg_count++;
    }
}
$overall_avg = $avg_count > 0 ? round($total_avg / $avg_count, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Results - <?php echo $display_form; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style> :root {--primary-color: <?php echo $colors['primary']; ?>; --primary-dark: <?php echo $colors['primary_dark']; ?>; --primary-light: <?php echo $colors['primary_light']; ?>; --success: #27ae60; --warning: #f39c12; --danger: #e74c3c; --info: #3498db; } body {font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; } .main-content {margin-left: 260px; padding: 20px; transition: all 0.3s; } @media (max-width: 768px) {.main-content {margin-left: 0; padding: 15px; } } .filter-bar {background: white; border-radius: 15px; padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); } .stat-card {background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; transition: transform 0.3s; } .stat-card:hover {transform: translateY(-5px); } .stat-icon {width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 15px; } .stat-value {font-size: 28px; font-weight: bold; margin-bottom: 5px; } .stat-label {color: #666; font-size: 14px; } .student-card {background: white; border-radius: 12px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: all 0.3s; cursor: pointer; } .student-card:hover {transform: translateX(5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); background: #f8f9fa; } .student-rank {font-size: 24px; font-weight: bold; color: var(--primary-color); } .student-name {font-size: 16px; font-weight: 600; } .badge-division {padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; } .division-i { background: #27ae60; color: white; } .division-ii { background: #2ecc71; color: white; } .division-iii { background: #f39c12; color: white; } .division-iv { background: #e67e22; color: white; } .division-0 { background: #e74c3c; color: white; } .results-table-container {background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow-x: auto; } .results-table {width: 100%; font-size: 13px; } .results-table thead th {background: var(--primary-color); color: white; padding: 12px; text-align: center; position: sticky; top: 0; } .results-table tbody td {padding: 10px; text-align: center; vertical-align: middle; } .results-table tbody tr:hover {background: #f8f9fa; } .view-details-btn {background: var(--info); color: white; border: none; padding: 5px 12px; border-radius: 20px; font-size: 11px; cursor: pointer; transition: all 0.3s; } .view-details-btn:hover {background: #2980b9; transform: scale(1.05); } .modal-subject-item {padding: 10px; margin-bottom: 10px; border-radius: 8px; background: #f8f9fa; } .subject-grade {font-size: 18px; font-weight: bold; padding: 5px 12px; border-radius: 25px; display: inline-block; } .position-badge {background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; } .position-1 { background: linear-gradient(135deg, #f39c12, #e67e22); } .position-2 { background: linear-gradient(135deg, #bdc3c7, #95a5a6); } .position-3 { background: linear-gradient(135deg, #cd6133, #b33939); } .section-title {font-size: 20px; font-weight: 600; margin-bottom: 20px; color: var(--primary-color); border-left: 4px solid var(--primary-color); padding-left: 15px; } .search-box {position: relative; } .search-box input {padding-right: 40px; } .search-box button {position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #999; } .rank-badge {background: #6c5ce7; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; } .rank-badge.top-1 { background: linear-gradient(135deg, #f39c12, #e67e22); } .rank-badge.top-2 { background: linear-gradient(135deg, #bdc3c7, #95a5a6); } .rank-badge.top-3 { background: linear-gradient(135deg, #cd6133, #b33939); } .top-student-card {background: linear-gradient(135deg, #fff9e6, #fff3cd); border-left: 4px solid #f39c12; } .bottom-student-card {background: linear-gradient(135deg, #ffe6e6, #ffd6d6); border-left: 4px solid #e74c3c; } </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-chart-simple me-2"></i>
                    Student Results Dashboard - <?php echo $display_form; ?>
                </h2>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Select Form</label>
                        <select id="formSelector" class="form-select">
                            <option value="Form five" <?php echo $selected_form == 'Form five' ? 'selected' : ''; ?>>Form Five</option>
                            <option value="Form six" <?php echo $selected_form == 'Form six' ? 'selected' : ''; ?>>Form Six</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Select Exam</label>
                        <select id="examSelector" class="form-select">
                            <option value="0">-- Select Exam --</option>
                            <?php foreach ($exam_types as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>" <?php echo $selected_exam == $exam['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['exam_name']); ?> (<?php echo $exam['year']; ?>)
                                    <?php echo $exam['is_active'] ? '[Active]' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Search Student</label>
                        <div class="search-box">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by name or index number..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button id="searchBtn"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
                
                <?php if ($current_exam): ?>
                    <div class="mt-3 pt-2 border-top">
                        <div class="d-flex gap-3 flex-wrap">
                            <span><i class="fas fa-calendar-alt me-1 text-muted"></i> <strong><?php echo htmlspecialchars($current_exam['exam_name']); ?></strong> - <?php echo $current_exam['year']; ?></span>
                            <span><i class="fas fa-users me-1 text-muted"></i> Total Students: <?php echo count($students_with_position); ?></span>
                            <span><i class="fas fa-chart-line me-1 text-muted"></i> Overall Average: <?php echo $overall_avg; ?>%</span>
                            <span><i class="fas fa-arrow-up me-1 text-muted"></i> Pass Rate: <?php echo $pass_rate; ?>%</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e8f4fd; color: var(--info);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo count($students_with_position); ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e8f5e9; color: var(--success);">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-value"><?php echo $div1_count; ?></div>
                        <div class="stat-label">Division I Students</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fff3e0; color: var(--warning);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-value"><?php echo $pass_rate; ?>%</div>
                        <div class="stat-label">Pass Rate</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fce4ec; color: var(--danger);">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-value">
                            <?php 
                            $best_avg = 0;
                            foreach ($students_with_position as $s) {
                                if ($s['average'] && $s['average'] > $best_avg) $best_avg = $s['average'];
                            }
                            echo $best_avg > 0 ? $best_avg . '%' : 'N/A';
                            ?>
                        </div>
                        <div class="stat-label">Highest Average</div>
                    </div>
                </div>
            </div>

            <!-- Top 10 & Bottom 10 Students -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="results-table-container">
                        <div class="section-title">
                            <i class="fas fa-medal me-2"></i>🏆 TOP 10 STUDENTS 
                        </div>
                        <?php if (empty($top_10)): ?>
                            <p class="text-muted text-center">No data available</p>
                        <?php else: ?>
                            <?php foreach ($top_10 as $index => $student): 
                                $rank_class = '';
                                if ($index == 0) $rank_class = 'top-1';
                                elseif ($index == 1) $rank_class = 'top-2';
                                elseif ($index == 2) $rank_class = 'top-3';
                            ?>
                                <div class="student-card top-student-card" onclick="viewStudentDetails(<?php echo $student['id']; ?>)">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <div class="student-rank">
                                                <?php 
                                                if ($index == 0) echo '🥇';
                                                elseif ($index == 1) echo '🥈';
                                                elseif ($index == 2) echo '🥉';
                                                else echo '<span class="rank-badge ' . $rank_class . '">#' . ($index + 1) . '</span>';
                                                ?>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="student-name">
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                <small class="text-muted">(<?php echo $student['index_number']; ?>)</small>
                                            </div>
                                            <div>
                                                <span class="badge bg-secondary"><?php echo $student['combination']; ?></span>
                                                <span class="badge bg-info"><?php echo $student['sex']; ?></span>
                                            </div>
                                        </div>
                                        <div class="col-auto text-end">
                                            <div class="badge-division <?php echo str_replace(' ', '-', strtolower($student['division'])); ?>">
                                                <?php echo $student['division']; ?>
                                            </div>
                                            <div><strong><?php echo $student['average'] ? number_format($student['average'], 1) : 'N/A'; ?>%</strong></div>
                                            <small>Points: <?php echo $student['total_points'] ?? 'N/A'; ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="results-table-container">
                        <div class="section-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>⚠️ BOTTOM 10 STUDENTS 
                        </div>
                        <?php if (empty($bottom_10)): ?>
                            <p class="text-muted text-center">No data available</p>
                        <?php else: ?>
                            <?php foreach ($bottom_10 as $index => $student): ?>
                                <div class="student-card bottom-student-card" onclick="viewStudentDetails(<?php echo $student['id']; ?>)">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <div class="student-rank" style="color: #e74c3c;">
                                                #<?php echo $total_students_with_avg - $index; ?>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="student-name">
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                <small class="text-muted">(<?php echo $student['index_number']; ?>)</small>
                                            </div>
                                            <div>
                                                <span class="badge bg-secondary"><?php echo $student['combination']; ?></span>
                                                <span class="badge bg-info"><?php echo $student['sex']; ?></span>
                                            </div>
                                        </div>
                                        <div class="col-auto text-end">
                                            <div class="badge-division <?php echo $student['division'] ? str_replace(' ', '-', strtolower($student['division'])) : 'bg-secondary'; ?>">
                                                <?php echo $student['division'] ?? 'N/A'; ?>
                                            </div>
                                            <div><strong><?php echo $student['average'] ? number_format($student['average'], 1) : 'N/A'; ?>%</strong></div>
                                            <small>Points: <?php echo $student['total_points'] ?? 'N/A'; ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Full Results Table (Sorted by Index Number) -->
            <div class="results-table-container">
                <div class="section-title">
                    <i class="fas fa-table-list me-2"></i>Complete Student Results (Sorted by Index Number)
                </div>
                <div class="table-responsive">
                    <table class="results-table" id="resultsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Index No</th>
                                <th>Student Name</th>
                                <th>Gender</th>
                                <th>Combination</th>
                                <th>Total Points</th>
                                <th>Average</th>
                                <th>Position</th>
                                <th>Division</th>
                                <th>View</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students_with_position)): ?>
                                <tr><td colspan="10" class="text-center py-5">No results found</td></tr>
                            <?php else: ?>
                                <?php $counter = 1; foreach ($students_with_position as $student): 
                                    $position_display = $student['position'] !== 'N/A' ? $student['position'] : '-';
                                    $position_class = '';
                                    if ($student['position'] == 1) $position_class = 'position-1';
                                    elseif ($student['position'] == 2) $position_class = 'position-2';
                                    elseif ($student['position'] == 3) $position_class = 'position-3';
                                ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        
                                        <td><strong><?php echo htmlspecialchars($student['index_number']); ?></strong></td>
                                        <td class="text-start">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            <?php if ($student['second_name']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($student['second_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $student['sex'] == 'Male' ? 'bg-primary' : 'bg-danger'; ?>">
                                                <?php echo $student['sex']; ?>
                                            </span>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo $student['combination']; ?></span></td>
                                        <td><strong><?php echo $student['total_points'] ?? '-'; ?></strong></td>
                                        <td>
                                            <strong><?php echo $student['average'] ? number_format($student['average'], 1) . '%' : '-'; ?></strong>
                                            <?php if ($student['average']): ?>
                                                <div class="progress" style="height: 4px; margin-top: 5px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $student['average']; ?>%"></div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($student['position'] !== 'N/A'): ?>
                                                <span class="position-badge <?php echo $position_class; ?>">
                                                    <?php echo $position_display; ?>/<?php echo $total_students_with_avg; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($student['division'] && $student['division'] != 'Not Assigned'): ?>
                                                <span class="badge-division <?php echo str_replace(' ', '-', strtolower($student['division'])); ?>">
                                                    <?php echo $student['division']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="view-details-btn" onclick="viewStudentDetails(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Details Modal -->
    <div class="modal fade" id="studentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-color); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-user-graduate me-2"></i>
                        Student Academic Report
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#resultsTable').DataTable({
                pageLength: 25,
                order: [[2, 'asc']], // Sort by Index Number column
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries"
                }
            });
        });

        // Filter handlers
        $('#formSelector, #examSelector').on('change', function() {
            const form = $('#formSelector').val();
            const examId = $('#examSelector').val();
            let url = `view_results.php?form=${encodeURIComponent(form)}&exam_id=${examId}`;
            window.location.href = url;
        });

        $('#searchBtn').click(function() {
            const search = $('#searchInput').val();
            const form = $('#formSelector').val();
            const examId = $('#examSelector').val();
            let url = `view_results.php?form=${encodeURIComponent(form)}&exam_id=${examId}&search=${encodeURIComponent(search)}`;
            window.location.href = url;
        });

        $('#searchInput').keypress(function(e) {
            if (e.which === 13) {
                $('#searchBtn').click();
            }
        });

        // View student details
        function viewStudentDetails(studentId) {
            const form = $('#formSelector').val();
            const examId = $('#examSelector').val();
            
            $('#studentDetailsModal').modal('show');
            
            $.ajax({
                url: 'get_student_full_results.php',
                method: 'GET',
                data: {
                    student_id: studentId,
                    exam_type_id: examId,
                    form: form
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayStudentDetails(response.data);
                    } else {
                        $('#modalBody').html(`
                            <div class="alert alert-danger text-center">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>
                                ${response.error || 'Failed to load student details'}
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#modalBody').html(`
                        <div class="alert alert-danger text-center">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>
                            An error occurred while loading data
                        </div>
                    `);
                }
            });
        }

        function displayStudentDetails(data) {
            const subjects = data.subjects || [];
            const student = data.student;
            
            // Subject display names mapping for modal
            const subjectDisplayMap = {
                'ac': 'ACADEMIC COMMUNICATION',
                'htm': 'HISTORIA YA TANZANIA NA MAADILI',
                'his': 'HISTORY',
                'geo': 'GEOGRAPHY',
                'kisw': 'KISWAHILI',
                'eng': 'ENGLISH',
                'b_math': 'BASIC MATHEMATICS',
                'adv_m': 'ADVANCED MATHEMATICS',
                'eco': 'ECONOMICS',
                'fren': 'FRENCH'
            };
            
            let subjectsHtml = '';
            subjects.forEach(subject => {
                const gradeColor = getGradeColor(subject.marks);
                const subjectFullName = subjectDisplayMap[subject.code] || subject.name;
                subjectsHtml += `
                    <div class="modal-subject-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${subjectFullName}</strong>
                            <br>
                            <small class="text-muted">${subject.short || subject.code.toUpperCase()}</small>
                        </div>
                        <div class="text-end">
                            <div class="subject-grade" style="background: ${gradeColor}20; color: ${gradeColor};">
                                ${subject.marks || '-'}%
                            </div>
                            <div class="badge" style="background: ${gradeColor}; color: white;">
                                Grade: ${subject.grade || 'N/A'}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            const positionDisplay = student.position ? `${student.position}/${student.total_students || '?'}` : 'N/A';
            const divisionClass = student.division ? student.division.toLowerCase().replace(' ', '-') : 'bg-secondary';
            
            const modalHtml = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title text-muted mb-2">Student Information</h6>
                                <p class="mb-1"><strong>Name:</strong> ${student.first_name} ${student.last_name}</p>
                                <p class="mb-1"><strong>Index Number:</strong> ${student.index_number}</p>
                                <p class="mb-1"><strong>Gender:</strong> ${student.sex}</p>
                                <p class="mb-1"><strong>Combination:</strong> ${student.combination}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title text-muted mb-2">Performance Summary</h6>
                                <p class="mb-1"><strong>Position:</strong> ${positionDisplay}</p>
                                <p class="mb-1"><strong>Total Points:</strong> ${student.total_points || 'N/A'}</p>
                                <p class="mb-1"><strong>Average:</strong> ${student.average ? student.average.toFixed(1) + '%' : 'N/A'}</p>
                                <p class="mb-1"><strong>Division:</strong> 
                                    <span class="badge-division ${divisionClass}">
                                        ${student.division || 'Not Assigned'}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <h6 class="mb-3"><i class="fas fa-book-open me-2"></i>Subject Performance</h6>
                ${subjectsHtml}
            `;
            
            $('#modalBody').html(modalHtml);
        }

        function getGradeColor(marks) {
            if (marks >= 80) return '#27ae60';
            if (marks >= 70) return '#2ecc71';
            if (marks >= 60) return '#f39c12';
            if (marks >= 50) return '#e67e22';
            if (marks >= 40) return '#3498db';
            if (marks >= 35) return '#95a5a6';
            return '#e74c3c';
        }
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>