<?php
// Turn on error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start logging
$log_file = __DIR__ . '/debug.log';
file_put_contents($log_file, "\n===== " . date('Y-m-d H:i:s') . " =====\n", FILE_APPEND);

session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

// Check database connection
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$admin_id = (int)$_SESSION['admin_id'];

// Get POST data
$input_data = [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($content_type, 'application/json') !== false) {
    $json = file_get_contents('php://input');
    $input_data = json_decode($json, true);
} else {
    $input_data = $_POST;
}

if (empty($input_data)) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

// Get required parameters
$student_id = isset($input_data['student_id']) ? (int)$input_data['student_id'] : 0;
$session_id = isset($input_data['session_id']) ? (int)$input_data['session_id'] : 0;

if (!$student_id || !$session_id) {
    echo json_encode(['success' => false, 'message' => 'Missing student_id or session_id']);
    exit();
}

// Get student combination
$student_query = mysqli_query($conn, "SELECT combination FROM students WHERE id = $student_id");
if (!$student_query || mysqli_num_rows($student_query) == 0) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit();
}
$student = mysqli_fetch_assoc($student_query);
$combination = $student['combination'];

// Get session details
$session_query = mysqli_query($conn, "SELECT exam_type_id, academic_year_id FROM exam_sessions WHERE id = $session_id");
if (!$session_query || mysqli_num_rows($session_query) == 0) {
    echo json_encode(['success' => false, 'message' => 'Session not found']);
    exit();
}
$session = mysqli_fetch_assoc($session_query);
$exam_type_id = (int)$session['exam_type_id'];
$academic_year_id = (int)$session['academic_year_id'];

// Subject combinations mapping
$combination_map = [
    'HGE' => ['history', 'geography', 'economics'],
    'HGL' => ['history', 'geography', 'english'],
    'HGK' => ['history', 'geography', 'kiswahili'],
    'HKL' => ['history', 'kiswahili', 'english'],
    'KLF' => ['kiswahili', 'english', 'french'],
    'EGM' => ['geography', 'advanced_maths', 'economics'],
    'HLF' => ['history', 'english', 'french'],
    'HGF' => ['history', 'geography', 'french']
];

// Combinations that include Basic Maths
$basic_maths_combinations = ['HGE', 'HKL', 'KLF', 'HLF', 'HGF'];

// Determine which subjects this student should have
$subjects = ['ac', 'htm'];

if (in_array($combination, $basic_maths_combinations)) {
    $subjects[] = 'basic_maths';
}

if (isset($combination_map[$combination])) {
    $subjects = array_merge($subjects, $combination_map[$combination]);
}
$subjects = array_unique($subjects);

// Get grade mapping
$grade_map = [];
$grade_result = mysqli_query($conn, "SELECT * FROM grade_mapping ORDER BY min_score DESC");
if ($grade_result) {
    while ($g = mysqli_fetch_assoc($grade_result)) {
        $grade_map[] = $g;
    }
}

// Prepare data arrays
$scores = [];
$grades = [];
$points = [];
$total_points = 0;
$subject_count = 0;
$all_scores = [];

foreach ($subjects as $subject) {
    $field = $subject . '_score';
    $score = isset($input_data[$field]) && $input_data[$field] !== '' ? floatval($input_data[$field]) : null;
    
    $scores[$subject . '_score'] = $score;
    
    if ($score !== null && $score >= 0 && $score <= 100) {
        $all_scores[] = $score;
        
        // Find grade and points
        $found_grade = null;
        $found_points = null;
        foreach ($grade_map as $g) {
            if ($score >= $g['min_score'] && $score <= $g['max_score']) {
                $found_grade = $g['grade'];
                $found_points = (int)$g['points'];
                break;
            }
        }
        
        $grades[$subject . '_grade'] = $found_grade;
        $points[$subject . '_points'] = $found_points;
        
        // Count for combination subjects only
        if (isset($combination_map[$combination]) && in_array($subject, $combination_map[$combination])) {
            $total_points += $found_points;
            $subject_count++;
        }
    } else {
        $grades[$subject . '_grade'] = null;
        $points[$subject . '_points'] = null;
    }
}

// Calculate average
$average = count($all_scores) > 0 ? round(array_sum($all_scores) / count($all_scores), 2) : null;
$final_points = ($subject_count == 3) ? $total_points : null;

// Determine division and status
$division = null;
$status = 'Pending';

if ($final_points !== null && $final_points >= 3 && $final_points <= 21) {
    $div_query = mysqli_query($conn, "SELECT division_name FROM division_rules 
                                      WHERE $final_points BETWEEN min_points AND max_points");
    if ($div_query && mysqli_num_rows($div_query) > 0) {
        $div = mysqli_fetch_assoc($div_query);
        $division = $div['division_name'];
        
        switch($division) {
            case 'Division I': $status = 'Excellent'; break;
            case 'Division II': $status = 'Good'; break;
            case 'Division III': $status = 'Satisfactory'; break;
            case 'Division IV': 
            case 'Division 0': $status = 'Fail'; break;
            default: $status = 'Pending';
        }
    }
}

// Check if record exists
$check = mysqli_query($conn, "SELECT id FROM student_results 
                              WHERE student_id = $student_id AND session_id = $session_id");
$record_exists = mysqli_num_rows($check) > 0;

// Disable foreign key checks temporarily
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");

mysqli_begin_transaction($conn);

try {
    if ($record_exists) {
        $row = mysqli_fetch_assoc($check);
        $result_id = $row['id'];
        
        $update_fields = [];
        foreach ($subjects as $subject) {
            $score_val = isset($scores[$subject . '_score']) && $scores[$subject . '_score'] !== null 
                        ? "'" . mysqli_real_escape_string($conn, $scores[$subject . '_score']) . "'" : 'NULL';
            $grade_val = isset($grades[$subject . '_grade']) && $grades[$subject . '_grade'] !== null 
                        ? "'" . mysqli_real_escape_string($conn, $grades[$subject . '_grade']) . "'" : 'NULL';
            $point_val = isset($points[$subject . '_points']) && $points[$subject . '_points'] !== null 
                        ? $points[$subject . '_points'] : 'NULL';
            
            $update_fields[] = "{$subject}_score = $score_val";
            $update_fields[] = "{$subject}_grade = $grade_val";
            $update_fields[] = "{$subject}_points = $point_val";
        }
        
        $total_points_sql = $final_points !== null ? $final_points : 'NULL';
        $average_sql = $average !== null ? "'$average'" : 'NULL';
        $division_sql = $division !== null ? "'" . mysqli_real_escape_string($conn, $division) . "'" : 'NULL';
        $status_sql = "'" . mysqli_real_escape_string($conn, $status) . "'";
        
        $update_fields[] = "total_points = $total_points_sql";
        $update_fields[] = "average_score = $average_sql";
        $update_fields[] = "division = $division_sql";
        $update_fields[] = "status = $status_sql";
        $update_fields[] = "updated_at = CURRENT_TIMESTAMP";
        
        $update_sql = "UPDATE student_results SET " . implode(", ", $update_fields) . " WHERE id = $result_id";
        
        if (!mysqli_query($conn, $update_sql)) {
            throw new Exception("Update failed: " . mysqli_error($conn));
        }
        
    } else {
        // INSERT new record
        $fields = ['student_id', 'exam_type_id', 'academic_year_id', 'session_id', 'created_by'];
        $values = [$student_id, $exam_type_id, $academic_year_id, $session_id, $admin_id];
        
        foreach ($subjects as $subject) {
            $fields[] = $subject . '_score';
            $fields[] = $subject . '_grade';
            $fields[] = $subject . '_points';
            
            $score_val = isset($scores[$subject . '_score']) && $scores[$subject . '_score'] !== null 
                        ? "'" . mysqli_real_escape_string($conn, $scores[$subject . '_score']) . "'" : 'NULL';
            $grade_val = isset($grades[$subject . '_grade']) && $grades[$subject . '_grade'] !== null 
                        ? "'" . mysqli_real_escape_string($conn, $grades[$subject . '_grade']) . "'" : 'NULL';
            $point_val = isset($points[$subject . '_points']) && $points[$subject . '_points'] !== null 
                        ? $points[$subject . '_points'] : 'NULL';
            
            $values[] = $score_val;
            $values[] = $grade_val;
            $values[] = $point_val;
        }
        
        $fields[] = 'total_points';
        $fields[] = 'average_score';
        $fields[] = 'division';
        $fields[] = 'status';
        
        $values[] = $final_points !== null ? $final_points : 'NULL';
        $values[] = $average !== null ? "'$average'" : 'NULL';
        $values[] = $division !== null ? "'" . mysqli_real_escape_string($conn, $division) . "'" : 'NULL';
        $values[] = "'$status'";
        
        $insert_sql = "INSERT INTO student_results (" . implode(", ", $fields) . ") 
                       VALUES (" . implode(", ", $values) . ")";
        
        if (!mysqli_query($conn, $insert_sql)) {
            throw new Exception("Insert failed: " . mysqli_error($conn));
        }
        $result_id = mysqli_insert_id($conn);
    }
    
    mysqli_commit($conn);
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
    
    // Update positions
    updatePositions($conn, $session_id);
    
    // Get saved data
    $result_query = mysqli_query($conn, "SELECT * FROM student_results WHERE id = $result_id");
    $result_data = mysqli_fetch_assoc($result_query);
    
    // Get all positions
    $positions = getAllPositions($conn, $session_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Saved successfully',
        'data' => $result_data,
        'positions' => $positions,
        'student_id' => $student_id
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
    echo json_encode(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
}

function updatePositions($conn, $session_id) {
    mysqli_query($conn, "UPDATE student_results SET position = NULL WHERE session_id = $session_id");
    $pos_sql = "SELECT id FROM student_results 
                WHERE session_id = $session_id AND average_score IS NOT NULL 
                ORDER BY average_score DESC";
    $pos_result = mysqli_query($conn, $pos_sql);
    $pos = 1;
    while ($row = mysqli_fetch_assoc($pos_result)) {
        mysqli_query($conn, "UPDATE student_results SET position = $pos WHERE id = {$row['id']}");
        $pos++;
    }
}

function getAllPositions($conn, $session_id) {
    $positions = [];
    $sql = "SELECT student_id, position FROM student_results 
            WHERE session_id = $session_id AND position IS NOT NULL";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $positions[$row['student_id']] = (int)$row['position'];
    }
    return $positions;
}
?>