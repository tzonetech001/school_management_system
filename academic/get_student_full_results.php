<?php
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$exam_type_id = isset($_GET['exam_type_id']) ? intval($_GET['exam_type_id']) : 0;
$form = isset($_GET['form']) ? mysqli_real_escape_string($conn, $_GET['form']) : 'Form five';

if (!$student_id || !$exam_type_id) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

$results_table = ($form == 'Form five') ? 'form_five_results' : 'form_six_results';

// Get student basic info
$student_sql = "SELECT s.id, s.index_number, s.first_name, s.last_name, s.second_name, s.sex, s.combination,
                       fr.total_points, fr.average, fr.division
                FROM students s
                LEFT JOIN $results_table fr ON s.id = fr.student_id AND fr.exam_type_id = $exam_type_id
                WHERE s.id = $student_id";
$student_result = mysqli_query($conn, $student_sql);
$student = mysqli_fetch_assoc($student_result);

if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Student not found']);
    exit();
}

// Get subject results
$subjects_sql = "SELECT ac, htm, his, geo, kisw, eng, b_math, adv_m, eco, fren 
                 FROM $results_table 
                 WHERE student_id = $student_id AND exam_type_id = $exam_type_id";
$subjects_result = mysqli_query($conn, $subjects_sql);
$subjects_data = mysqli_fetch_assoc($subjects_result);

// Subject display names
$subject_display = [
    'ac' => ['name' => 'Accountancy', 'code' => 'AC'],
    'htm' => ['name' => 'Hotel Management', 'code' => 'HTM'],
    'his' => ['name' => 'History', 'code' => 'HIST'],
    'geo' => ['name' => 'Geography', 'code' => 'GEO'],
    'kisw' => ['name' => 'Kiswahili', 'code' => 'KISW'],
    'eng' => ['name' => 'English', 'code' => 'ENG'],
    'b_math' => ['name' => 'Basic Mathematics', 'code' => 'B/MATH'],
    'adv_m' => ['name' => 'Advanced Mathematics', 'code' => 'ADV/M'],
    'eco' => ['name' => 'Economics', 'code' => 'ECO'],
    'fren' => ['name' => 'French', 'code' => 'FREN']
];

function getGradeLetter($marks) {
    if ($marks >= 80) return 'A';
    if ($marks >= 70) return 'B';
    if ($marks >= 60) return 'C';
    if ($marks >= 50) return 'D';
    if ($marks >= 40) return 'E';
    if ($marks >= 35) return 'S';
    return 'F';
}

// Build subjects array
$subjects = [];
foreach ($subject_display as $key => $info) {
    $marks = $subjects_data[$key] ?? null;
    $subjects[] = [
        'code' => $info['code'],
        'name' => $info['name'],
        'marks' => $marks,
        'grade' => $marks !== null ? getGradeLetter($marks) : null
    ];
}

// Calculate position
$position_sql = "SELECT COUNT(*) + 1 as position 
                 FROM $results_table 
                 WHERE exam_type_id = $exam_type_id 
                 AND average > (SELECT average FROM $results_table WHERE student_id = $student_id AND exam_type_id = $exam_type_id)";
$position_result = mysqli_query($conn, $position_sql);
$position_row = mysqli_fetch_assoc($position_result);

echo json_encode([
    'success' => true,
    'data' => [
        'student' => [
            'id' => $student['id'],
            'index_number' => $student['index_number'],
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'second_name' => $student['second_name'],
            'sex' => $student['sex'],
            'combination' => $student['combination'],
            'total_points' => $student['total_points'],
            'average' => floatval($student['average']),
            'division' => $student['division'],
            'position' => $position_row['position'] ?? 'N/A'
        ],
        'subjects' => $subjects
    ]
]);
?>