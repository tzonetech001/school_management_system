<?php
// academics.php - Complete Academic Analytics Dashboard
session_start();
require_once '../controller/db_connect.php';

// Get theme colors if available (for dynamic styling)
$colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
    'primary_light' => '#8bc5d6',
    'accent' => '#ffc107',
    'success' => '#28a745',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#17a2b8',
    'dark' => '#2c3e50',
    'light' => '#f8f9fa'
];

// Get user preferences if logged in
$compact_mode = false;
$font_size = '14px';
if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    $pref_sql = "SELECT preference_value FROM user_preferences WHERE admin_id = $admin_id AND preference_key = 'compact_mode'";
    $pref_result = mysqli_query($conn, $pref_sql);
    if ($pref_result && $row = mysqli_fetch_assoc($pref_result)) {
        $compact_mode = $row['preference_value'] == '1';
    }
    
    $font_sql = "SELECT preference_value FROM user_preferences WHERE admin_id = $admin_id AND preference_key = 'font_size'";
    $font_result = mysqli_query($conn, $font_sql);
    if ($font_result && $row = mysqli_fetch_assoc($font_result)) {
        $font_size = $row['preference_value'] . 'px';
    }
}

// Get filter parameters
$selected_form = isset($_GET['form_level']) ? mysqli_real_escape_string($conn, $_GET['form_level']) : 'Form Five';
$selected_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get ALL exam types for filtering
$exam_types_sql = "SELECT id, exam_name, exam_code, year, is_active, term, description 
                   FROM exam_types 
                   WHERE form_level = '$selected_form'
                   ORDER BY year DESC, id DESC";
$exam_types_result = mysqli_query($conn, $exam_types_sql);
$exam_types = [];
while ($row = mysqli_fetch_assoc($exam_types_result)) {
    $exam_types[] = $row;
}

// If no exam selected and there are exams, get the most recent active one
if ($selected_exam == 0 && count($exam_types) > 0) {
    foreach ($exam_types as $exam) {
        if ($exam['is_active'] == 1) {
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
$exam_name = '';
if ($selected_exam > 0) {
    $exam_sql = "SELECT * FROM exam_types WHERE id = $selected_exam";
    $exam_result = mysqli_query($conn, $exam_sql);
    $current_exam = mysqli_fetch_assoc($exam_result);
    if ($current_exam) {
        $exam_name = $current_exam['exam_name'] . ' (' . $current_exam['year'] . ')';
    }
}

// Determine results table
$results_table = ($selected_form == 'Form Five') ? 'form_five_results' : 'form_six_results';

// Get available years from exam_types
$years_sql = "SELECT DISTINCT year FROM exam_types WHERE form_level = '$selected_form' ORDER BY year DESC";
$years_result = mysqli_query($conn, $years_sql);
$available_years = [];
while ($row = mysqli_fetch_assoc($years_result)) {
    $available_years[] = $row['year'];
}
if (empty($available_years)) {
    $available_years[] = date('Y');
}

// ==================== STATISTICS QUERIES ====================

// 1. Division Distribution
$division_stats = [
    'Division I' => 0,
    'Division II' => 0,
    'Division III' => 0,
    'Division IV' => 0,
    'Division 0' => 0,
    'Not Assigned' => 0
];

if ($selected_exam > 0) {
    $division_sql = "SELECT division, COUNT(*) as count 
                     FROM $results_table 
                     WHERE exam_type_id = $selected_exam 
                     GROUP BY division";
    $division_result = mysqli_query($conn, $division_sql);
    if ($division_result) {
        while ($row = mysqli_fetch_assoc($division_result)) {
            $div = $row['division'] ?: 'Not Assigned';
            if (isset($division_stats[$div])) {
                $division_stats[$div] = (int)$row['count'];
            } else {
                $division_stats['Not Assigned'] += (int)$row['count'];
            }
        }
    }
}

// 2. Gender Performance
$gender_stats = [
    'Male' => ['count' => 0, 'total_points' => 0, 'students_with_points' => 0, 'avg_score' => 0],
    'Female' => ['count' => 0, 'total_points' => 0, 'students_with_points' => 0, 'avg_score' => 0]
];

if ($selected_exam > 0) {
    $gender_sql = "SELECT s.sex, fr.total_points, fr.average
                   FROM $results_table fr
                   JOIN students s ON fr.student_id = s.id
                   WHERE fr.exam_type_id = $selected_exam";
    $gender_result = mysqli_query($conn, $gender_sql);
    if ($gender_result) {
        while ($row = mysqli_fetch_assoc($gender_result)) {
            $sex = $row['sex'];
            if (isset($gender_stats[$sex])) {
                $gender_stats[$sex]['count']++;
                if ($row['average'] !== null && $row['average'] > 0) {
                    $gender_stats[$sex]['total_points'] += $row['average'];
                    $gender_stats[$sex]['students_with_points']++;
                }
            }
        }
    }
    
    // Calculate average score per gender (as percentage)
    foreach ($gender_stats as $sex => $data) {
        if ($data['students_with_points'] > 0) {
            $gender_stats[$sex]['avg_score'] = round($data['total_points'] / $data['students_with_points'], 1);
        }
    }
}

// 3. Subject Performance (average scores per subject)
$subjects = ['ac', 'htm', 'his', 'geo', 'kisw', 'eng', 'b_math', 'adv_m', 'eco', 'fren'];
$subject_names = [
    'ac' => ' Academic Communication', 'htm' => 'Historia ya Tanzania na maadili', 'his' => 'History', 'geo' => 'Geography',
    'kisw' => 'Kiswahili', 'eng' => 'English', 'b_math' => 'Basic Math',
    'adv_m' => 'Adv Math', 'eco' => 'Economics', 'fren' => 'French'
];
$subject_performance = [];

if ($selected_exam > 0) {
    foreach ($subjects as $subject) {
        $subject_sql = "SELECT AVG($subject) as avg_score, 
                               COUNT($subject) as student_count,
                               SUM(CASE WHEN $subject >= 80 THEN 1 ELSE 0 END) as grade_a,
                               SUM(CASE WHEN $subject >= 70 AND $subject < 80 THEN 1 ELSE 0 END) as grade_b,
                               SUM(CASE WHEN $subject >= 60 AND $subject < 70 THEN 1 ELSE 0 END) as grade_c,
                               SUM(CASE WHEN $subject >= 50 AND $subject < 60 THEN 1 ELSE 0 END) as grade_d,
                               SUM(CASE WHEN $subject >= 40 AND $subject < 50 THEN 1 ELSE 0 END) as grade_e,
                               SUM(CASE WHEN $subject >= 35 AND $subject < 40 THEN 1 ELSE 0 END) as grade_s,
                               SUM(CASE WHEN $subject < 35 AND $subject IS NOT NULL THEN 1 ELSE 0 END) as grade_f
                        FROM $results_table 
                        WHERE exam_type_id = $selected_exam AND $subject IS NOT NULL";
        $subject_result = mysqli_query($conn, $subject_sql);
        if ($subject_result) {
            $data = mysqli_fetch_assoc($subject_result);
            $subject_performance[$subject] = [
                'name' => $subject_names[$subject],
                'avg_score' => round($data['avg_score'] ?? 0, 1),
                'student_count' => (int)$data['student_count'],
                'grade_a' => (int)$data['grade_a'],
                'grade_b' => (int)$data['grade_b'],
                'grade_c' => (int)$data['grade_c'],
                'grade_d' => (int)$data['grade_d'],
                'grade_e' => (int)$data['grade_e'],
                'grade_s' => (int)$data['grade_s'],
                'grade_f' => (int)$data['grade_f']
            ];
        } else {
            $subject_performance[$subject] = [
                'name' => $subject_names[$subject],
                'avg_score' => 0,
                'student_count' => 0,
                'grade_a' => 0, 'grade_b' => 0, 'grade_c' => 0,
                'grade_d' => 0, 'grade_e' => 0, 'grade_s' => 0, 'grade_f' => 0
            ];
        }
    }
}

// 4. Top 10 Students
$top_students = [];
if ($selected_exam > 0) {
    $top_students_sql = "SELECT s.id, s.index_number, s.first_name, s.last_name, s.sex, s.combination,
                                fr.total_points, fr.average, fr.division
                         FROM $results_table fr
                         JOIN students s ON fr.student_id = s.id
                         WHERE fr.exam_type_id = $selected_exam AND fr.average IS NOT NULL
                         ORDER BY fr.average DESC
                         LIMIT 10";
    $top_students_result = mysqli_query($conn, $top_students_sql);
    while ($row = mysqli_fetch_assoc($top_students_result)) {
        $top_students[] = $row;
    }
}

// 5. Bottom 10 Students (for intervention)
$bottom_students = [];
if ($selected_exam > 0) {
    $bottom_students_sql = "SELECT s.id, s.index_number, s.first_name, s.last_name, s.sex, s.combination,
                                   fr.total_points, fr.average, fr.division
                            FROM $results_table fr
                            JOIN students s ON fr.student_id = s.id
                            WHERE fr.exam_type_id = $selected_exam AND fr.average IS NOT NULL
                            ORDER BY fr.average ASC
                            LIMIT 10";
    $bottom_students_result = mysqli_query($conn, $bottom_students_sql);
    while ($row = mysqli_fetch_assoc($bottom_students_result)) {
        $bottom_students[] = $row;
    }
}

// 6. Combination Performance
$combinations = ['HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'];
$combination_performance = [];

if ($selected_exam > 0) {
    foreach ($combinations as $combo) {
        $combo_sql = "SELECT AVG(average) as avg_avg, 
                             AVG(total_points) as avg_points,
                             COUNT(*) as student_count,
                             SUM(CASE WHEN division = 'Division I' THEN 1 ELSE 0 END) as div1,
                             SUM(CASE WHEN division = 'Division II' THEN 1 ELSE 0 END) as div2,
                             SUM(CASE WHEN division = 'Division III' THEN 1 ELSE 0 END) as div3,
                             SUM(CASE WHEN division = 'Division IV' THEN 1 ELSE 0 END) as div4
                      FROM $results_table fr
                      JOIN students s ON fr.student_id = s.id
                      WHERE fr.exam_type_id = $selected_exam AND s.combination = '$combo'";
        $combo_result = mysqli_query($conn, $combo_sql);
        if ($combo_result) {
            $data = mysqli_fetch_assoc($combo_result);
            $combination_performance[$combo] = [
                'student_count' => (int)$data['student_count'],
                'avg_average' => round($data['avg_avg'] ?? 0, 1),
                'avg_points' => round($data['avg_points'] ?? 0, 1),
                'div1' => (int)$data['div1'],
                'div2' => (int)$data['div2'],
                'div3' => (int)$data['div3'],
                'div4' => (int)$data['div4']
            ];
        } else {
            $combination_performance[$combo] = [
                'student_count' => 0, 'avg_average' => 0, 'avg_points' => 0,
                'div1' => 0, 'div2' => 0, 'div3' => 0, 'div4' => 0
            ];
        }
    }
}

// 7. Overall Statistics
$overall_stats = ['total_students' => 0, 'school_avg' => 0, 'pass_rate' => 0, 'highest_score' => 0, 'lowest_score' => 0];
$total_students = 0;
$school_avg = 0;
$pass_rate = 0;

if ($selected_exam > 0) {
    $overall_sql = "SELECT 
                       COUNT(*) as total_students,
                       AVG(average) as school_avg,
                       AVG(total_points) as avg_points,
                       SUM(CASE WHEN division IN ('Division I', 'Division II', 'Division III') THEN 1 ELSE 0 END) as passed,
                       MAX(average) as highest_score,
                       MIN(average) as lowest_score
                    FROM $results_table 
                    WHERE exam_type_id = $selected_exam";
    $overall_result = mysqli_query($conn, $overall_sql);
    if ($overall_result) {
        $overall_stats = mysqli_fetch_assoc($overall_result);
        $total_students = (int)($overall_stats['total_students'] ?? 0);
        $school_avg = round($overall_stats['school_avg'] ?? 0, 1);
        $passed = (int)($overall_stats['passed'] ?? 0);
        $pass_rate = $total_students > 0 ? round(($passed / $total_students) * 100, 1) : 0;
    }
}

// 8. Performance Trend (last 5 exams for this form)
$trend_data = [];
if ($selected_exam > 0) {
    $trend_sql = "SELECT et.exam_name, et.year, 
                         AVG(fr.average) as avg_score
                  FROM exam_types et
                  LEFT JOIN $results_table fr ON et.id = fr.exam_type_id
                  WHERE et.form_level = '$selected_form'
                  GROUP BY et.id
                  ORDER BY et.year ASC, et.id ASC
                  LIMIT 5";
    $trend_result = mysqli_query($conn, $trend_sql);
    while ($row = mysqli_fetch_assoc($trend_result)) {
        $trend_data[] = [
            'label' => $row['exam_name'] . ' (' . substr($row['year'], -2) . ')',
            'score' => round($row['avg_score'] ?? 0, 1)
        ];
    }
}

// 9. Grade Distribution for all subjects combined
$grade_distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'S' => 0, 'F' => 0];
foreach ($subjects as $subject) {
    if (isset($subject_performance[$subject])) {
        $grade_distribution['A'] += $subject_performance[$subject]['grade_a'];
        $grade_distribution['B'] += $subject_performance[$subject]['grade_b'];
        $grade_distribution['C'] += $subject_performance[$subject]['grade_c'];
        $grade_distribution['D'] += $subject_performance[$subject]['grade_d'];
        $grade_distribution['E'] += $subject_performance[$subject]['grade_e'];
        $grade_distribution['S'] += $subject_performance[$subject]['grade_s'];
        $grade_distribution['F'] += $subject_performance[$subject]['grade_f'];
    }
}

// 10. Subject Pass Rates (pass = grade D or better)
$subject_pass_rates = [];
foreach ($subjects as $subject) {
    if (isset($subject_performance[$subject])) {
        $total = $subject_performance[$subject]['student_count'];
        $passed = $subject_performance[$subject]['grade_a'] + 
                  $subject_performance[$subject]['grade_b'] + 
                  $subject_performance[$subject]['grade_c'] + 
                  $subject_performance[$subject]['grade_d'];
        $subject_pass_rates[$subject] = [
            'name' => $subject_names[$subject],
            'pass_rate' => $total > 0 ? round(($passed / $total) * 100, 1) : 0,
            'total' => $total
        ];
    } else {
        $subject_pass_rates[$subject] = [
            'name' => $subject_names[$subject],
            'pass_rate' => 0,
            'total' => 0
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Analytics - Muyovozi High School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: <?php echo $colors['primary']; ?>;
            --primary-dark: <?php echo $colors['primary_dark']; ?>;
            --primary-light: <?php echo $colors['primary_light']; ?>;
            --accent-color: <?php echo $colors['accent']; ?>;
            --success-color: <?php echo $colors['success']; ?>;
            --danger-color: <?php echo $colors['danger']; ?>;
            --warning-color: <?php echo $colors['warning']; ?>;
            --info-color: <?php echo $colors['info']; ?>;
            --dark-color: <?php echo $colors['dark']; ?>;
            --light-color: <?php echo $colors['light']; ?>;
            --font-size-base: <?php echo $font_size; ?>;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            font-size: var(--font-size-base);
            color: var(--dark-color);
            line-height: 1.5;
        }

        /* Main Content Area - Accounts for fixed header */
        .main-content {
            margin-left: 0;
            padding: 100px 20px 40px;
            transition: all 0.3s;
            min-height: 100vh;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 90px 15px 30px;
            }
        }

        /* Filter Bar Styles */
        .filter-bar {
            background: white;
            border-radius: 20px;
            padding: 20px 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .filter-label {
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary-color);
            margin-bottom: 8px;
            display: block;
        }

        .form-select, .form-control {
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            padding: 10px 15px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59,157,179,0.1);
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 12px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59,157,179,0.3);
        }

        /* Stat Cards */
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid rgba(0,0,0,0.03);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(59,157,179,0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
            color: var(--dark-color);
        }

        .stat-label {
            color: #6c757d;
            font-size: 13px;
            font-weight: 500;
        }

        /* Chart Cards */
        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            height: 100%;
            transition: all 0.3s ease;
        }

        .chart-card:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        .chart-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--dark-color);
            border-left: 4px solid var(--primary-color);
            padding-left: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-title i {
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        canvas {
            max-height: 280px;
            width: 100% !important;
        }

        /* Table Styles */
        .table-custom {
            border-radius: 16px;
            overflow: hidden;
        }

        .table-custom thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            padding: 12px 15px;
        }

        .table-custom tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            font-size: 0.85rem;
        }

        .table-custom tbody tr:hover {
            background: rgba(59,157,179,0.05);
        }

        /* Badge Styles */
        .badge-division-i {
            background: #27ae60;
            color: white;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-division-ii {
            background: #2ecc71;
            color: white;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-division-iii {
            background: #f39c12;
            color: white;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-division-iv {
            background: #e67e22;
            color: white;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-division-0 {
            background: #e74c3c;
            color: white;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Rank Styles */
        .rank-1 { background: linear-gradient(135deg, #ffd70020, #ffd70010); font-weight: 700; }
        .rank-2 { background: linear-gradient(135deg, #c0c0c020, #c0c0c010); }
        .rank-3 { background: linear-gradient(135deg, #cd7f3220, #cd7f3210); }

        /* Alert Styles */
        .alert-custom {
            border-radius: 20px;
            border: none;
            padding: 30px;
            text-align: center;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stat-value { font-size: 22px; }
            canvas { max-height: 220px; }
            .chart-card { padding: 15px; }
            .filter-bar { padding: 15px; }
        }

        @media (max-width: 576px) {
            .main-content { padding: 80px 12px 20px; }
            .stat-card { padding: 15px; }
            .stat-value { font-size: 20px; }
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(5px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
        }
        .loading-overlay.active { display: flex; }
        .spinner-custom {
            width: 50px;
            height: 50px;
            border: 4px solid var(--primary-light);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-custom"></div>
        <p class="mt-3 text-primary fw-bold">Loading analytics...</p>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--dark-color);">
                        <i class="fas fa-chart-line me-2" style="color: var(--primary-color);"></i>
                        Academic Analytics Dashboard
                    </h2>
                    <p class="text-muted mb-0">Comprehensive performance analysis and insights</p>
                </div>
                <?php if ($selected_exam > 0 && $total_students > 0 && isset($_SESSION['admin_id'])): ?>
                <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fas fa-print me-2"></i>Export Report
                </button>
                <?php endif; ?>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="row align-items-end g-3">
                    <div class="col-md-3">
                        <label class="filter-label"><i class="fas fa-layer-group me-1"></i> Form Level</label>
                        <select id="formSelector" class="form-select">
                            <option value="Form Five" <?php echo $selected_form == 'Form Five' ? 'selected' : ''; ?>>Form Five</option>
                            <option value="Form Six" <?php echo $selected_form == 'Form Six' ? 'selected' : ''; ?>>Form Six</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="filter-label"><i class="fas fa-file-alt me-1"></i> Exam Type</label>
                        <select id="examSelector" class="form-select">
                            <option value="0">-- Select Exam --</option>
                            <?php foreach ($exam_types as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>" <?php echo $selected_exam == $exam['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['exam_name']); ?> (<?php echo $exam['year']; ?>)
                                    <?php echo $exam['is_active'] ? '✓' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label"><i class="fas fa-calendar me-1"></i> Year</label>
                        <select id="yearSelector" class="form-select">
                            <?php foreach ($available_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $selected_year == $year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button id="applyFilters" class="btn btn-primary w-100">
                            <i class="fas fa-chart-simple me-2"></i>Update
                        </button>
                    </div>
                </div>
                
                <?php if ($current_exam && $total_students > 0): ?>
                    <div class="mt-3 pt-2 border-top d-flex flex-wrap gap-3">
                        <span><i class="fas fa-calendar-alt me-1 text-muted"></i> <strong><?php echo htmlspecialchars($current_exam['exam_name']); ?></strong> - <?php echo $current_exam['year']; ?></span>
                        <span><i class="fas fa-users me-1 text-muted"></i> Total Students: <?php echo $total_students; ?></span>
                        <span><i class="fas fa-chart-line me-1 text-muted"></i> School Average: <?php echo $school_avg; ?>%</span>
                        <span><i class="fas fa-trophy me-1 text-muted"></i> Pass Rate: <?php echo $pass_rate; ?>%</span>
                    </div>
                <?php elseif ($selected_exam > 0): ?>
                    <div class="mt-3 pt-2 border-top">
                        <div class="alert alert-info mb-0 py-2 rounded-pill">
                            <i class="fas fa-info-circle me-2"></i> No results found for this exam. Please enter results first.
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($selected_exam > 0 && $total_students > 0): ?>
                
                <!-- Key Metrics Row -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo $colors['primary']; ?>20; color: var(--primary-color);">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_students; ?></div>
                            <div class="stat-label">Total Students Assessed</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo $colors['success']; ?>20; color: var(--success-color);">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-value"><?php echo $school_avg; ?>%</div>
                            <div class="stat-label">School Average Score</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo $colors['warning']; ?>20; color: var(--warning-color);">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="stat-value"><?php echo $pass_rate; ?>%</div>
                            <div class="stat-label">Pass Rate (Div I-III)</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo $colors['info']; ?>20; color: var(--info-color);">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="stat-value"><?php echo round($overall_stats['highest_score'] ?? 0, 1); ?>%</div>
                            <div class="stat-label">Highest Score</div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-chart-pie"></i> Division Distribution
                            </div>
                            <canvas id="divisionChart" style="height: 280px;"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-venus-mars"></i> Gender Performance Comparison
                            </div>
                            <canvas id="genderChart" style="height: 280px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="row g-4 mt-2">
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-chart-simple"></i> Subject Average Scores
                            </div>
                            <canvas id="subjectChart" style="height: 300px;"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-chart-line"></i> Performance Trend
                            </div>
                            <canvas id="trendChart" style="height: 280px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 3 -->
                <div class="row g-4 mt-2">
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-chart-bar"></i> Grade Distribution (All Subjects)
                            </div>
                            <canvas id="gradeChart" style="height: 280px;"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-chart-simple"></i> Subject Pass Rates
                            </div>
                            <canvas id="passRateChart" style="height: 280px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Combination Performance Table -->
                <div class="chart-card mt-3">
                    <div class="chart-title">
                        <i class="fas fa-layer-group"></i> Combination Performance Analysis
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Combination</th>
                                    <th>Students</th>
                                    <th>Avg Score (%)</th>
                                    <th>Avg Points</th>
                                    <th>Div I</th>
                                    <th>Div II</th>
                                    <th>Div III</th>
                                    <th>Div IV+</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $has_data = false;
                                foreach ($combination_performance as $combo => $data): 
                                    if ($data['student_count'] > 0): 
                                        $has_data = true;
                                ?>
                                    <tr>
                                        <td><strong><?php echo $combo; ?></strong></td>
                                        <td><?php echo $data['student_count']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span><?php echo $data['avg_average']; ?>%</span>
                                                <div class="progress flex-grow-1" style="height: 5px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $data['avg_average']; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $data['avg_points']; ?></td>
                                        <td><span class="badge-division-i"><?php echo $data['div1']; ?></span></td>
                                        <td><span class="badge-division-ii"><?php echo $data['div2']; ?></span></td>
                                        <td><span class="badge-division-iii"><?php echo $data['div3']; ?></span></td>
                                        <td><span class="badge-division-iv"><?php echo $data['div4']; ?></span></td>
                                    </tr>
                                <?php 
                                    endif;
                                endforeach; 
                                if (!$has_data):
                                ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No combination data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top and Bottom Students -->
                <div class="row g-4 mt-2">
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-crown" style="color: #ffd700;"></i> Top 10 Students
                            </div>
                            <div class="table-responsive">
                                <table class="table table-custom table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Index No</th>
                                            <th>Name</th>
                                            <th>Comb</th>
                                            <th>Avg (%)</th>
                                            <th>Division</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($top_students) > 0): ?>
                                            <?php $rank = 1; foreach ($top_students as $student): ?>
                                                <tr class="<?php echo $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : '')); ?>">
                                                    <td><strong><?php echo $rank++; ?></strong></td>
                                                    <td><?php echo htmlspecialchars($student['index_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                    <td><?php echo $student['combination']; ?></td>
                                                    <td><strong><?php echo round($student['average'], 1); ?>%</strong></td>
                                                    <td><span class="badge-division-<?php echo strtolower(str_replace(' ', '', $student['division'])); ?>"><?php echo $student['division']; ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="text-center text-muted py-3">No data available</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-chart-simple" style="color: var(--danger-color);"></i> Bottom 10 Students (Need Support)
                            </div>
                            <div class="table-responsive">
                                <table class="table table-custom table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Index No</th>
                                            <th>Name</th>
                                            <th>Comb</th>
                                            <th>Avg (%)</th>
                                            <th>Division</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($bottom_students) > 0): ?>
                                            <?php $rank = 1; foreach ($bottom_students as $student): ?>
                                                <tr>
                                                    <td><?php echo $rank++; ?></td>
                                                    <td><?php echo htmlspecialchars($student['index_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                    <td><?php echo $student['combination']; ?></td>
                                                    <td><strong class="text-danger"><?php echo round($student['average'], 1); ?>%</strong></td>
                                                    <td><span class="badge-division-<?php echo strtolower(str_replace(' ', '', $student['division'])); ?>"><?php echo $student['division']; ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="text-center text-muted py-3">No data available</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($selected_exam > 0): ?>
                <!-- No Results Found -->
                <div class="alert-custom bg-white shadow-sm">
                    <i class="fas fa-info-circle fa-4x mb-3" style="color: var(--primary-color);"></i>
                    <h4 class="fw-bold mb-2">No Results Found</h4>
                    <p class="text-muted mb-4">No student results have been entered for this exam yet.</p>
                    <?php if (isset($_SESSION['admin_id'])): ?>
                        <a href="<?php echo $selected_form == 'Form Five' ? 'results_entry_five.php' : 'results_entry_six.php'; ?>?exam_id=<?php echo $selected_exam; ?>" class="btn btn-primary px-4 py-2 rounded-pill">
                            <i class="fas fa-edit me-2"></i>Enter Results Now
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- No Exam Selected -->
                <div class="alert-custom bg-white shadow-sm">
                    <i class="fas fa-exclamation-triangle fa-4x mb-3" style="color: var(--warning-color);"></i>
                    <h4 class="fw-bold mb-2">No Exam Selected</h4>
                    <p class="text-muted mb-0">Please select an exam type from the filter above to view analytics.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        <?php if ($selected_exam > 0 && $total_students > 0): ?>
        
        // Division Distribution Chart
        const divisionCtx = document.getElementById('divisionChart').getContext('2d');
        new Chart(divisionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Division I', 'Division II', 'Division III', 'Division IV', 'Division 0', 'Not Assigned'],
                datasets: [{
                    data: [
                        <?php echo $division_stats['Division I']; ?>,
                        <?php echo $division_stats['Division II']; ?>,
                        <?php echo $division_stats['Division III']; ?>,
                        <?php echo $division_stats['Division IV']; ?>,
                        <?php echo $division_stats['Division 0']; ?>,
                        <?php echo $division_stats['Not Assigned']; ?>
                    ],
                    backgroundColor: ['#27ae60', '#2ecc71', '#f39c12', '#e67e22', '#e74c3c', '#95a5a6'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 } } },
                    tooltip: { callbacks: { label: function(context) { return context.label + ': ' + context.raw + ' students'; } } }
                }
            }
        });

        // Gender Performance Chart
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'bar',
            data: {
                labels: ['Male', 'Female'],
                datasets: [{
                    label: 'Average Score (%)',
                    data: [<?php echo $gender_stats['Male']['avg_score']; ?>, <?php echo $gender_stats['Female']['avg_score']; ?>],
                    backgroundColor: ['<?php echo $colors['primary']; ?>', '<?php echo $colors['danger']; ?>'],
                    borderRadius: 8,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Average Score (%)' } } },
                plugins: { tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ' + context.raw.toFixed(1) + '%'; } } } }
            }
        });

        // Subject Performance Chart
        const subjectCtx = document.getElementById('subjectChart').getContext('2d');
        const subjectLabels = [<?php 
            $labels = []; $scores = [];
            foreach ($subject_performance as $subject => $data) {
                if ($data['student_count'] > 0) {
                    $labels[] = "'" . addslashes($data['name']) . "'";
                    $scores[] = $data['avg_score'];
                }
            }
            echo implode(', ', $labels);
        ?>];
        const subjectScores = [<?php echo implode(', ', $scores); ?>];
        new Chart(subjectCtx, {
            type: 'bar',
            data: {
                labels: subjectLabels,
                datasets: [{
                    label: 'Average Score (%)',
                    data: subjectScores,
                    backgroundColor: '<?php echo $colors['primary']; ?>',
                    borderRadius: 8,
                    barPercentage: 0.7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Average Score (%)' } } },
                plugins: { tooltip: { callbacks: { label: function(context) { return 'Average: ' + context.raw.toFixed(1) + '%'; } } } }
            }
        });

        // Performance Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: [<?php foreach ($trend_data as $t) { echo "'" . addslashes($t['label']) . "', "; } ?>],
                datasets: [{
                    label: 'Average Score (%)',
                    data: [<?php foreach ($trend_data as $t) { echo $t['score'] . ', '; } ?>],
                    borderColor: '<?php echo $colors['primary']; ?>',
                    backgroundColor: '<?php echo $colors['primary']; ?>20',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '<?php echo $colors['primary']; ?>',
                    pointBorderColor: 'white',
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Average Score (%)' } } }
            }
        });

        // Grade Distribution Chart
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        new Chart(gradeCtx, {
            type: 'bar',
            data: {
                labels: ['A', 'B', 'C', 'D', 'E', 'S', 'F'],
                datasets: [{
                    label: 'Number of Grades',
                    data: [
                        <?php echo $grade_distribution['A']; ?>,
                        <?php echo $grade_distribution['B']; ?>,
                        <?php echo $grade_distribution['C']; ?>,
                        <?php echo $grade_distribution['D']; ?>,
                        <?php echo $grade_distribution['E']; ?>,
                        <?php echo $grade_distribution['S']; ?>,
                        <?php echo $grade_distribution['F']; ?>
                    ],
                    backgroundColor: ['#27ae60', '#2ecc71', '#f39c12', '#3498db', '#95a5a6', '#e67e22', '#e74c3c'],
                    borderRadius: 8,
                    barPercentage: 0.7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Grades' } } }
            }
        });

        // Subject Pass Rates Chart
        const passRateCtx = document.getElementById('passRateChart').getContext('2d');
        const passLabels = [<?php 
            $passLabels = []; $passRates = [];
            foreach ($subject_pass_rates as $subject => $data) {
                if ($data['total'] > 0) {
                    $passLabels[] = "'" . addslashes($data['name']) . "'";
                    $passRates[] = $data['pass_rate'];
                }
            }
            echo implode(', ', $passLabels);
        ?>];
        const passRates = [<?php echo implode(', ', $passRates); ?>];
        new Chart(passRateCtx, {
            type: 'bar',
            data: {
                labels: passLabels,
                datasets: [{
                    label: 'Pass Rate (%)',
                    data: passRates,
                    backgroundColor: function(context) {
                        const value = context.raw;
                        if (value >= 70) return '#27ae60';
                        if (value >= 50) return '#f39c12';
                        return '#e74c3c';
                    },
                    borderRadius: 8,
                    barPercentage: 0.7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'Pass Rate (%)' } } },
                plugins: { tooltip: { callbacks: { label: function(context) { return 'Pass Rate: ' + context.raw.toFixed(1) + '%'; } } } }
            }
        });

        <?php endif; ?>

        // Filter handlers
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        $('#formSelector, #examSelector, #yearSelector').on('change', function() {
            showLoading();
            const form = $('#formSelector').val();
            const examId = $('#examSelector').val();
            const year = $('#yearSelector').val();
            let url = `academics.php?form_level=${encodeURIComponent(form)}&year=${year}`;
            if (examId && examId !== '0') {
                url += `&exam_id=${examId}`;
            }
            window.location.href = url;
        });

        $('#applyFilters').click(function() {
            showLoading();
            const form = $('#formSelector').val();
            const examId = $('#examSelector').val();
            const year = $('#yearSelector').val();
            let url = `academics.php?form_level=${encodeURIComponent(form)}&year=${year}`;
            if (examId && examId !== '0') {
                url += `&exam_id=${examId}`;
            }
            window.location.href = url;
        });

        // Hide loading overlay after page load
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('loadingOverlay').classList.remove('active');
            }, 500);
        });
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>