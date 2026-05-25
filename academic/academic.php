<?php
session_start();
require_once '../controller/db_connect.php';

// Check login
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

// Get current academic year
$current_year = date('Y');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;
$selected_form = isset($_GET['form']) ? mysqli_real_escape_string($conn, $_GET['form']) : 'Form five';

// Get available years for filter
$years_sql = "SELECT DISTINCT YEAR(created_at) as year FROM exam_types ORDER BY year DESC";
$years_result = mysqli_query($conn, $years_sql);
$available_years = [];
while ($row = mysqli_fetch_assoc($years_result)) {
    $available_years[] = $row['year'];
}

// Get exam types for selected form and year
$exam_types_sql = "SELECT id, exam_name, year, is_active FROM exam_types 
                   WHERE form_level = '$selected_form' AND year = $selected_year 
                   ORDER BY id DESC";
$exam_types_result = mysqli_query($conn, $exam_types_sql);
$exam_types = [];
while ($row = mysqli_fetch_assoc($exam_types_result)) {
    $exam_types[] = $row;
}

// Get statistics for selected form and year
$stats = [];

// Division distribution
$division_stats = [
    'Division I' => 0,
    'Division II' => 0,
    'Division III' => 0,
    'Division IV' => 0,
    'Division 0' => 0,
    'Not Assigned' => 0
];

// Gender distribution
$gender_stats = ['Male' => 0, 'Female' => 0];

// Subject performance (average marks per subject)
$subject_performance = [
    'ac' => ['total' => 0, 'count' => 0, 'name' => 'AC'],
    'htm' => ['total' => 0, 'count' => 0, 'name' => 'HTM'],
    'his' => ['total' => 0, 'count' => 0, 'name' => 'HISTORY'],
    'geo' => ['total' => 0, 'count' => 0, 'name' => 'GEOGRAPHY'],
    'kisw' => ['total' => 0, 'count' => 0, 'name' => 'KISWAHILI'],
    'eng' => ['total' => 0, 'count' => 0, 'name' => 'ENGLISH'],
    'b_math' => ['total' => 0, 'count' => 0, 'name' => 'BASIC MATH'],
    'adv_m' => ['total' => 0, 'count' => 0, 'name' => 'ADVANCED MATH'],
    'eco' => ['total' => 0, 'count' => 0, 'name' => 'ECONOMICS'],
    'fren' => ['total' => 0, 'count' => 0, 'name' => 'FRENCH']
];

// Combination performance
$combination_stats = [
    'HGE' => ['total_points' => 0, 'count' => 0, 'students' => 0],
    'HGL' => ['total_points' => 0, 'count' => 0, 'students' => 0],
    'HGK' => ['total_points' => 0, 'count' => 0, 'students' => 0],
    'HKL' => ['total_points' => 0, 'count' => 0, 'students' => 0],
    'KLF' => ['total_points' => 0, 'count' => 0, 'students' => 0],
    'EGM' => ['total_points' => 0, 'count' => 0, 'students' => 0],
    'HLF' => ['total_points' => 0, 'count' => 0, 'students' => 0],
    'HGF' => ['total_points' => 0, 'count' => 0, 'students' => 0]
];

// Get results table name based on form
$results_table = ($selected_form == 'Form five') ? 'form_five_results' : 'form_six_results';

// Process each exam type to get statistics
foreach ($exam_types as $exam) {
    $exam_id = $exam['id'];
    
    // Get students with results
    $sql = "SELECT s.id, s.sex, s.combination, 
                   fr.ac, fr.htm, fr.his, fr.geo, fr.kisw, fr.eng, 
                   fr.b_math, fr.adv_m, fr.eco, fr.fren,
                   fr.total_points, fr.average, fr.division
            FROM students s
            LEFT JOIN $results_table fr ON s.id = fr.student_id AND fr.exam_type_id = $exam_id
            WHERE s.class = '$selected_form' AND (s.is_leaver IS NULL OR s.is_leaver = FALSE)";
    
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Division distribution
        $division = $row['division'] ?? 'Not Assigned';
        if (isset($division_stats[$division])) {
            $division_stats[$division]++;
        }
        
        // Gender distribution
        $gender = $row['sex'];
        if (isset($gender_stats[$gender])) {
            $gender_stats[$gender]++;
        }
        
        // Subject performance
        foreach ($subject_performance as $key => $subject) {
            if ($row[$key] !== null && $row[$key] > 0) {
                $subject_performance[$key]['total'] += $row[$key];
                $subject_performance[$key]['count']++;
            }
        }
        
        // Combination performance (points based)
        $combination = $row['combination'];
        if (isset($combination_stats[$combination]) && $row['total_points'] !== null) {
            $combination_stats[$combination]['total_points'] += $row['total_points'];
            $combination_stats[$combination]['count']++;
            $combination_stats[$combination]['students']++;
        } elseif (isset($combination_stats[$combination])) {
            $combination_stats[$combination]['students']++;
        }
    }
}

// Calculate averages
foreach ($subject_performance as $key => $subject) {
    if ($subject['count'] > 0) {
        $subject_performance[$key]['average'] = round($subject['total'] / $subject['count'], 1);
    } else {
        $subject_performance[$key]['average'] = 0;
    }
}

foreach ($combination_stats as $key => $combo) {
    if ($combo['count'] > 0) {
        $combination_stats[$key]['avg_points'] = round($combo['total_points'] / $combo['count'], 1);
    } else {
        $combination_stats[$key]['avg_points'] = 0;
    }
}

// Get top performing students
$top_students_sql = "SELECT s.id, s.index_number, s.first_name, s.last_name, s.sex, s.combination,
                            fr.total_points, fr.average, fr.division
                     FROM students s
                     LEFT JOIN $results_table fr ON s.id = fr.student_id
                     WHERE s.class = '$selected_form' 
                     AND (s.is_leaver IS NULL OR s.is_leaver = FALSE)
                     AND fr.total_points IS NOT NULL
                     ORDER BY fr.total_points ASC, fr.average DESC
                     LIMIT 10";
$top_students_result = mysqli_query($conn, $top_students_sql);
$top_students = [];
while ($row = mysqli_fetch_assoc($top_students_result)) {
    $top_students[] = $row;
}

// Get monthly performance trend (last 6 months)
$trend_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('F', strtotime("-$i months"));
    $trend_data[$month] = [
        'average' => rand(55, 75), // Replace with actual data from database
        'students' => rand(40, 60)
    ];
}

// Get grade distribution
$grade_distribution = [
    'A (80-100)' => 0,
    'B (70-79)' => 0,
    'C (60-69)' => 0,
    'D (50-59)' => 0,
    'E (40-49)' => 0,
    'S (35-39)' => 0,
    'F (0-34)' => 0
];

// Calculate grade distribution from subject averages
$grade_sql = "SELECT average FROM $results_table WHERE average IS NOT NULL";
$grade_result = mysqli_query($conn, $grade_sql);
while ($row = mysqli_fetch_assoc($grade_result)) {
    $avg = $row['average'];
    if ($avg >= 80) $grade_distribution['A (80-100)']++;
    elseif ($avg >= 70) $grade_distribution['B (70-79)']++;
    elseif ($avg >= 60) $grade_distribution['C (60-69)']++;
    elseif ($avg >= 50) $grade_distribution['D (50-59)']++;
    elseif ($avg >= 40) $grade_distribution['E (40-49)']++;
    elseif ($avg >= 35) $grade_distribution['S (35-39)']++;
    else $grade_distribution['F (0-34)']++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Analytics Dashboard - Form Five & Six</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --primary-dark: #1a2632;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --purple: #9b59b6;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
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

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary-color);
            border-left: 4px solid var(--primary-color);
            padding-left: 15px;
        }

        .filter-bar {
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .table-performance {
            font-size: 13px;
        }

        .table-performance thead th {
            background: var(--primary-color);
            color: white;
            padding: 10px;
        }

        .badge-division {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .division-i { background: #27ae60; color: white; }
        .division-ii { background: #2ecc71; color: white; }
        .division-iii { background: #f39c12; color: white; }
        .division-iv { background: #e67e22; color: white; }
        .division-0 { background: #e74c3c; color: white; }

        .progress-custom {
            height: 8px;
            border-radius: 4px;
            margin-top: 8px;
        }

        .subject-score {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .score-high { background: #27ae60; color: white; }
        .score-medium { background: #f39c12; color: white; }
        .score-low { background: #e74c3c; color: white; }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-chart-line me-2"></i>
                    Academic Analytics Dashboard
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
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Academic Year</label>
                        <select id="yearSelector" class="form-select">
                            <?php foreach ($available_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $selected_year == $year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                </div>
            </div>

            <!-- Statistics Cards - Row 1 -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e8f4fd; color: var(--info);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo array_sum($gender_stats); ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e8f5e9; color: var(--success);">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-value"><?php echo $division_stats['Division I']; ?></div>
                        <div class="stat-label">Division I Students</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fff3e0; color: var(--warning);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-value">
                            <?php 
                            $total_with_avg = 0;
                            $sum_avg = 0;
                            foreach ($subject_performance as $subj) {
                                if ($subj['average'] > 0) {
                                    $sum_avg += $subj['average'];
                                    $total_with_avg++;
                                }
                            }
                            $overall_avg = $total_with_avg > 0 ? round($sum_avg / $total_with_avg, 1) : 0;
                            echo $overall_avg . '%';
                            ?>
                        </div>
                        <div class="stat-label">Overall Average</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fce4ec; color: var(--danger);">
                            <i class="fas fa-flag-checkered"></i>
                        </div>
                        <div class="stat-value"><?php echo count($exam_types); ?></div>
                        <div class="stat-label">Exams Conducted</div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1: Division Distribution & Gender Distribution -->
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-pie me-2"></i>Division Distribution
                        </div>
                        <canvas id="divisionChart" style="height: 300px;"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-venus-mars me-2"></i>Gender Distribution
                        </div>
                        <canvas id="genderChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2: Subject Performance Bar Chart -->
            <div class="row">
                <div class="col-12">
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-bar me-2"></i>Subject Performance Analysis
                        </div>
                        <canvas id="subjectChart" style="height: 400px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 3: Combination Performance & Grade Distribution -->
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-layer-group me-2"></i>Combination Performance (Average Points)
                        </div>
                        <div id="combinationChart" style="height: 350px;"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-line me-2"></i>Grade Distribution
                        </div>
                        <canvas id="gradeChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 4: Trend Line & Radar Chart -->
            <div class="row">
                <div class="col-md-7">
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-line me-2"></i>Performance Trend (Last 6 Months)
                        </div>
                        <canvas id="trendChart" style="height: 350px;"></canvas>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-radar me-2"></i>Subject Performance Radar
                        </div>
                        <canvas id="radarChart" style="height: 350px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Students Table -->
            <div class="chart-container">
                <div class="chart-title">
                    <i class="fas fa-medal me-2"></i>Top 10 Performing Students
                </div>
                <div class="table-responsive">
                    <table class="table table-performance">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Index No</th>
                                <th>Student Name</th>
                                <th>Gender</th>
                                <th>Combination</th>
                                <th>Total Points</th>
                                <th>Average</th>
                                <th>Division</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_students)): ?>
                                <tr><td colspan="8" class="text-center">No data available</td></tr>
                            <?php else: ?>
                                <?php $rank = 1; foreach ($top_students as $student): ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['index_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <td><?php echo $student['sex']; ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $student['combination']; ?></span></td>
                                        <td><strong><?php echo $student['total_points']; ?></strong></td>
                                        <td><?php echo $student['average'] ? number_format($student['average'], 1) . '%' : '-'; ?></td>
                                        <td>
                                            <?php if ($student['division']): ?>
                                                <span class="badge-division <?php echo str_replace(' ', '-', strtolower($student['division'])); ?>">
                                                    <?php echo $student['division']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Subject Performance Details -->
            <div class="chart-container">
                <div class="chart-title">
                    <i class="fas fa-book-open me-2"></i>Subject Performance Details
                </div>
                <div class="row">
                    <?php 
                    $subject_colors = ['primary', 'success', 'info', 'warning', 'danger', 'purple', 'secondary', 'dark'];
                    $color_index = 0;
                    foreach ($subject_performance as $key => $subject):
                        if ($subject['count'] > 0):
                            $percentage = $subject['average'];
                            $color = $subject_colors[$color_index % count($subject_colors)];
                            $score_class = $percentage >= 70 ? 'score-high' : ($percentage >= 50 ? 'score-medium' : 'score-low');
                    ?>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <strong><?php echo $subject['name']; ?></strong>
                                    <small class="text-muted">(<?php echo $subject['count']; ?> students)</small>
                                </span>
                                <span class="subject-score <?php echo $score_class; ?>">
                                    <?php echo $percentage; ?>%
                                </span>
                            </div>
                            <div class="progress progress-custom">
                                <div class="progress-bar bg-<?php echo $color; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo $percentage; ?>%"
                                     aria-valuenow="<?php echo $percentage; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                    <?php 
                        $color_index++;
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Division Chart (Pie)
        const divisionCtx = document.getElementById('divisionChart').getContext('2d');
        new Chart(divisionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Division I', 'Division II', 'Division III', 'Division IV', 'Division 0', 'Not Assigned'],
                datasets: [{
                    data: <?php echo json_encode(array_values($division_stats)); ?>,
                    backgroundColor: ['#27ae60', '#2ecc71', '#f39c12', '#e67e22', '#e74c3c', '#95a5a6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gender Chart (Pie)
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: ['Male', 'Female'],
                datasets: [{
                    data: <?php echo json_encode(array_values($gender_stats)); ?>,
                    backgroundColor: ['#3498db', '#e74c3c'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Subject Performance Chart (Bar)
        const subjectCtx = document.getElementById('subjectChart').getContext('2d');
        const subjectLabels = <?php 
            $labels = [];
            $averages = [];
            foreach ($subject_performance as $subject) {
                if ($subject['count'] > 0) {
                    $labels[] = $subject['name'];
                    $averages[] = $subject['average'];
                }
            }
            echo json_encode($labels);
        ?>;
        const subjectAverages = <?php echo json_encode($averages); ?>;
        
        new Chart(subjectCtx, {
            type: 'bar',
            data: {
                labels: subjectLabels,
                datasets: [{
                    label: 'Average Score (%)',
                    data: subjectAverages,
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: '#3498db',
                    borderWidth: 1,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Percentage (%)'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + '%';
                            }
                        }
                    }
                }
            }
        });

        // Combination Chart (Horizontal Bar using ApexCharts)
        const combinationData = <?php 
            $comboLabels = [];
            $comboPoints = [];
            foreach ($combination_stats as $combo => $data) {
                if ($data['students'] > 0) {
                    $comboLabels[] = $combo;
                    $comboPoints[] = $data['avg_points'];
                }
            }
            echo json_encode(['labels' => $comboLabels, 'points' => $comboPoints]);
        ?>;
        
        const comboOptions = {
            series: [{
                name: 'Average Points',
                data: combinationData.points
            }],
            chart: {
                type: 'bar',
                height: 350,
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 8,
                    dataLabels: { position: 'top' }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(val) {
                    return val.toFixed(1);
                },
                offsetX: 20
            },
            colors: ['#3498db'],
            xaxis: {
                categories: combinationData.labels,
                title: { text: 'Average Points' }
            },
            title: { text: undefined }
        };
        
        const combinationChart = new ApexCharts(document.querySelector("#combinationChart"), comboOptions);
        combinationChart.render();

        // Grade Distribution Chart (Line/Bar)
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        new Chart(gradeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($grade_distribution)); ?>,
                datasets: [{
                    label: 'Number of Students',
                    data: <?php echo json_encode(array_values($grade_distribution)); ?>,
                    backgroundColor: 'rgba(155, 89, 182, 0.7)',
                    borderColor: '#9b59b6',
                    borderWidth: 1,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Number of Students' }
                    },
                    x: {
                        title: { display: true, text: 'Grade Range' }
                    }
                }
            }
        });

        // Trend Chart (Line)
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendMonths = <?php echo json_encode(array_keys($trend_data)); ?>;
        const trendAverages = <?php echo json_encode(array_column($trend_data, 'average')); ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendMonths,
                datasets: [
                    {
                        label: 'Average Performance (%)',
                        data: trendAverages,
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#27ae60'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: { display: true, text: 'Percentage (%)' }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + '%';
                            }
                        }
                    }
                }
            }
        });

        // Radar Chart for Subject Performance
        const radarCtx = document.getElementById('radarChart').getContext('2d');
        new Chart(radarCtx, {
            type: 'radar',
            data: {
                labels: subjectLabels,
                datasets: [{
                    label: 'Subject Performance',
                    data: subjectAverages,
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: '#3498db',
                    borderWidth: 2,
                    pointBackgroundColor: '#3498db',
                    pointBorderColor: '#fff',
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { stepSize: 20 }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });

        // Filter change handlers
        $('#formSelector, #yearSelector').on('change', function() {
            const form = $('#formSelector').val();
            const year = $('#yearSelector').val();
            window.location.href = `academic.php?form=${encodeURIComponent(form)}&year=${year}`;
        });
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>