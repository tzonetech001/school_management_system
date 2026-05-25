<?php
// parent_sms_results.php
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user has permission
$admin_id = $_SESSION['admin_id'] ?? 0;

$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 3) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view this page.";
    header("Location: ../404.php");
    exit();
}

$admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
$current_year = date('Y');

// Load theme settings
$theme_settings = [];
$settings_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
$settings_result = mysqli_query($conn, $settings_query);
if ($settings_result && mysqli_num_rows($settings_result) > 0) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $theme_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Load user preferences
$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id";
$prefs_result = mysqli_query($conn, $prefs_query);
if ($prefs_result && mysqli_num_rows($prefs_result) > 0) {
    while ($row = mysqli_fetch_assoc($prefs_result)) {
        $preferences[$row['preference_key']] = $row['preference_value'];
    }
}

// Default colors
$default_colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
    'primary_light' => '#8bc5d6',
    'light' => '#f8f9fa',
    'white' => '#ffffff',
    'gray' => '#e9ecef',
    'text' => '#333333',
    'text_light' => '#666666',
    'border' => '#e0e0e0',
    'success' => '#28a745',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#17a2b8'
];

$colors = $default_colors;
foreach ($theme_settings as $key => $value) {
    if (array_key_exists($key, $colors)) {
        $colors[$key] = $value;
    }
}

// Font size and compact mode
$font_sizes = ['10' => '10px', '12' => '12px', '14' => '14px', '16' => '16px', '18' => '18px'];
$font_size = isset($preferences['font_size']) ? $font_sizes[$preferences['font_size']] : '16px';
$compact_mode = isset($preferences['compact_mode']) && $preferences['compact_mode'] === '1';
$animations = isset($preferences['animations']) && $preferences['animations'] === '1';
$animation_speed = isset($preferences['animation_speed']) ? $preferences['animation_speed'] : 'normal';
$animation_time = $animation_speed === 'slow' ? '0.5s' : ($animation_speed === 'fast' ? '0.15s' : '0.3s');

// Beem Africa API Credentials
define('BEEM_API_KEY', '5e3de5075687abf8');
define('BEEM_SECRET_KEY', 'MDRhM2MxNGUxZGNmYmRjNDMzYzVmYjlkY2MyM2UxNTRmNjMyNzU2YTg2OGRjMmQ5YmMxZjdiODRkZTg2ZjQwYQ==');
define('BEEM_SOURCE_ADDR', 'MUYOVOZI HS');
define('SMS_MAX_CHARS', 160);

// Get filters
$selected_form = isset($_GET['form']) ? mysqli_real_escape_string($conn, $_GET['form']) : 'Form five';
$selected_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$db_form = ($selected_form == 'Form five') ? 'Form five' : 'Form six';

// Get ONLY ACTIVE exam types
$exam_types_sql = "SELECT id, exam_name, year, is_active FROM exam_types 
                   WHERE form_level = '$db_form' AND is_active = 1
                   ORDER BY year DESC, id DESC";
$exam_types_result = mysqli_query($conn, $exam_types_sql);
$exam_types = [];
while ($row = mysqli_fetch_assoc($exam_types_result)) {
    $exam_types[] = $row;
}

if ($selected_exam == 0 && count($exam_types) > 0) {
    $selected_exam = $exam_types[0]['id'];
}

$current_exam = null;
if ($selected_exam > 0) {
    $exam_sql = "SELECT * FROM exam_types WHERE id = $selected_exam AND is_active = 1";
    $exam_result = mysqli_query($conn, $exam_sql);
    $current_exam = mysqli_fetch_assoc($exam_result);
}

$results_table = ($db_form == 'Form five') ? 'form_five_results' : 'form_six_results';

$subject_display = [
    'ac' => 'AC', 'htm' => 'HTM', 'his' => 'HIST', 'geo' => 'GEO',
    'kisw' => 'KISW', 'eng' => 'ENG', 'b_math' => 'B/MATH',
    'adv_m' => 'ADV/M', 'eco' => 'ECO', 'fren' => 'FREN'
];

$subject_full_names = [
    'ac' => 'Academic Communication', 'htm' => 'Historia ya Tanzania na Maadili',
    'his' => 'History', 'geo' => 'Geography', 'kisw' => 'Kiswahili',
    'eng' => 'English', 'b_math' => 'Basic Mathematics',
    'adv_m' => 'Advanced Mathematics', 'eco' => 'Economics', 'fren' => 'French'
];

function formatPhoneNumberDisplay($phone) {
    if (empty($phone)) return '';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 12 && substr($phone, 0, 3) == '255') {
        return '0' . substr($phone, 3);
    }
    return $phone;
}

function formatPhoneNumberForAPI($phone) {
    if (empty($phone)) return '';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 3) === '255') return substr($phone, 0, 12);
    if (substr($phone, 0, 1) === '0') return '255' . substr($phone, 1, 9);
    if (substr($phone, 0, 1) === '7' || substr($phone, 0, 1) === '6') return '255' . $phone;
    return '255' . $phone;
}

function checkSMSBalance() {
    $api_key = BEEM_API_KEY;
    $secret_key = BEEM_SECRET_KEY;
    $Url = 'https://apisms.beem.africa/public/v1/vendors/balance';
    $ch = curl_init($Url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode("$api_key:$secret_key"), 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['data']['credit_balance'])) return number_format($data['data']['credit_balance'], 0);
        if (isset($data['credit_balance'])) return number_format($data['credit_balance'], 0);
    }
    return 'N/A';
}

function sendSMS($phone_number, $message) {
    $api_key = BEEM_API_KEY;
    $secret_key = BEEM_SECRET_KEY;
    $phone_number = formatPhoneNumberForAPI($phone_number);
    if (!preg_match('/^255[67][0-9]{8}$/', $phone_number)) {
        return ['success' => false, 'message' => "Invalid phone number format"];
    }
    $postData = ["source_addr" => BEEM_SOURCE_ADDR, "encoding" => 0, "message" => $message, "recipients" => [["recipient_id" => "1", "dest_addr" => $phone_number]]];
    $Url = 'https://apisms.beem.africa/v1/send';
    $ch = curl_init($Url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt_array($ch, [CURLOPT_POST => TRUE, CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode("$api_key:$secret_key"), 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($postData), CURLOPT_TIMEOUT => 30]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === FALSE) return ['success' => false, 'message' => "CURL Error"];
    $response_data = json_decode($response, true);
    if ($http_code == 200 && isset($response_data['successful']) && $response_data['successful'] === true) {
        return ['success' => true, 'message' => "SMS sent successfully"];
    }
    return ['success' => false, 'message' => "Failed: " . ($response_data['message'] ?? 'Unknown error')];
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

function getGradePoint($marks) {
    if ($marks >= 80) return 1;
    if ($marks >= 70) return 2;
    if ($marks >= 60) return 3;
    if ($marks >= 50) return 4;
    if ($marks >= 40) return 5;
    if ($marks >= 35) return 6;
    return 7;
}

// ============================================
// UPDATE PARENT PHONE ONLY (No subject edit)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_parent_phone'])) {
    header('Content-Type: application/json');
    
    $student_id = intval($_POST['student_id']);
    $parent_phone = trim($_POST['parent_phone']);
    $parent_name = mysqli_real_escape_string($conn, trim($_POST['parent_name']));
    $parent_occupation = mysqli_real_escape_string($conn, trim($_POST['parent_occupation']));
    $parent_residence = mysqli_real_escape_string($conn, trim($_POST['parent_residence']));
    
    if (empty($student_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
        exit();
    }
    
    $formatted_phone = !empty($parent_phone) ? formatPhoneNumberForAPI($parent_phone) : null;
    
    $update_sql = "UPDATE students SET 
                   parent_phone = " . ($formatted_phone ? "'$formatted_phone'" : "NULL") . ",
                   parent_name = " . ($parent_name ? "'$parent_name'" : "NULL") . ",
                   parent_occupation = " . ($parent_occupation ? "'$parent_occupation'" : "NULL") . ",
                   parent_residence = " . ($parent_residence ? "'$parent_residence'" : "NULL") . "
                   WHERE id = $student_id";
    
    if (mysqli_query($conn, $update_sql)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Parent information updated successfully!',
            'display_phone' => formatPhoneNumberDisplay($formatted_phone)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit();
}

// ============================================
// GET STUDENT DETAILS FOR VIEW MODAL (READ ONLY)
// ============================================
if (isset($_GET['get_student_details'])) {
    header('Content-Type: application/json');
    
    $student_id = intval($_GET['student_id']);
    $exam_type_id = intval($_GET['exam_type_id']);
    $form = mysqli_real_escape_string($conn, $_GET['form']);
    
    $results_table = ($form == 'Form five') ? 'form_five_results' : 'form_six_results';
    $db_form = ($form == 'Form five') ? 'Form five' : 'Form six';
    
    $sql = "SELECT s.*, 
                   fr.ac, fr.htm, fr.his, fr.geo, fr.kisw, fr.eng, fr.b_math, fr.adv_m, fr.eco, fr.fren,
                   fr.total_points, fr.average, fr.division
            FROM students s
            LEFT JOIN $results_table fr ON s.id = fr.student_id AND fr.exam_type_id = $exam_type_id
            WHERE s.id = $student_id";
    
    $result = mysqli_query($conn, $sql);
    $student = mysqli_fetch_assoc($result);
    
    if ($student) {
        $student['parent_phone_formatted'] = formatPhoneNumberDisplay($student['parent_phone']);
        
        // Get rank - sequential, no ties, alphabetical order for same average
        $rank_sql = "SELECT s.id, s.first_name, s.last_name, fr.average
                FROM students s
                LEFT JOIN $results_table fr ON s.id = fr.student_id AND fr.exam_type_id = $exam_type_id
                WHERE s.class = '{$student['class']}' 
                AND fr.average IS NOT NULL
                ORDER BY fr.average DESC, s.first_name ASC, s.last_name ASC";
        $rank_result = mysqli_query($conn, $rank_sql);
        $temp = [];
        while ($row = mysqli_fetch_assoc($rank_result)) $temp[] = $row;
        
        $student_rank = null;
        foreach ($temp as $index => $st) {
            if ($st['id'] == $student_id) {
                $student_rank = $index + 1;
                break;
            }
        }
        $student['rank'] = $student_rank ?? 'N/A';
        $student['total_students'] = count($temp);
        
        // Build subjects array
        $subjects = [];
        $subject_list = ['ac', 'htm', 'his', 'geo', 'kisw', 'eng', 'b_math', 'adv_m', 'eco', 'fren'];
        
        for ($i = 0; $i < count($subject_list); $i++) {
            $subject_code = $subject_list[$i];
            $marks = $student[$subject_code];
            $subjects[] = [
                'code' => $subject_code,
                'name' => $subject_full_names[$subject_code],
                'short' => $subject_display[$subject_code],
                'marks' => $marks,
                'grade' => $marks !== null ? getGradeLetter($marks) : null,
                'grade_point' => $marks !== null ? getGradePoint($marks) : null
            ];
        }
        
        echo json_encode([
            'success' => true,
            'student' => $student,
            'subjects' => $subjects,
            'exam_id' => $exam_type_id,
            'form' => $form
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
    }
    exit();
}

// ============================================
// GET STUDENTS WITH RANKS (sequential, no ties, alphabetical for same average)
// ============================================
$students = [];
$total_students_with_results = 0;

if ($selected_exam > 0 && $current_exam) {
    // Get students sorted by average DESC, then by name ASC - SEQUENTIAL RANK
    $rank_sql = "SELECT s.id, s.first_name, s.last_name, fr.average
            FROM students s
            LEFT JOIN $results_table fr ON s.id = fr.student_id AND fr.exam_type_id = $selected_exam
            WHERE s.class = '$db_form' 
            AND (s.is_leaver IS NULL OR s.is_leaver = FALSE)
            AND fr.average IS NOT NULL
            ORDER BY fr.average DESC, s.first_name ASC, s.last_name ASC";
    
    $rank_result = mysqli_query($conn, $rank_sql);
    $temp_students = [];
    while ($row = mysqli_fetch_assoc($rank_result)) {
        $temp_students[] = $row;
    }
    
    // Assign ranks - SEQUENTIAL, NO TIES
    $ranked_students = [];
    foreach ($temp_students as $index => $student) {
        $ranked_students[$student['id']] = $index + 1;
    }
    $total_students_with_results = count($temp_students);
    
    // Get detailed student data - ORDER BY INDEX NUMBER
    $sql = "SELECT s.id, s.index_number, s.first_name, s.last_name, s.second_name, s.sex, s.combination, s.parent_phone,
                   s.parent_name, s.parent_occupation, s.parent_residence,
                   fr.ac, fr.htm, fr.his, fr.geo, fr.kisw, fr.eng, fr.b_math, fr.adv_m, fr.eco, fr.fren,
                   fr.total_points, fr.average, fr.division, fr.updated_at
            FROM students s
            LEFT JOIN $results_table fr ON s.id = fr.student_id AND fr.exam_type_id = $selected_exam
            WHERE s.class = '$db_form' AND (s.is_leaver IS NULL OR s.is_leaver = FALSE)
            AND s.parent_phone IS NOT NULL AND s.parent_phone != ''";

    if ($search_query) {
        $sql .= " AND (s.index_number LIKE '%$search_query%' 
                   OR s.first_name LIKE '%$search_query%' 
                   OR s.last_name LIKE '%$search_query%')";
    }

    $sql .= " ORDER BY s.index_number ASC";

    $students_result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($students_result)) {
        $row['parent_phone_formatted'] = formatPhoneNumberDisplay($row['parent_phone']);
        $row['rank'] = $ranked_students[$row['id']] ?? 'N/A';
        $students[] = $row;
    }
}

// ============================================
// AJAX: Handle single SMS send
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_send_sms'])) {
    header('Content-Type: application/json');
    
    $student_id = intval($_POST['student_id']);
    $exam_type_id = intval($_POST['exam_type_id']);
    $form = mysqli_real_escape_string($conn, $_POST['form']);
    
    $results_table = ($form == 'Form five') ? 'form_five_results' : 'form_six_results';
    $form_level = ($form == 'Form five') ? 'Form five' : 'Form six';
    
    $student_sql = "SELECT s.id, s.first_name, s.last_name, s.parent_phone, s.combination,
                           fr.average, fr.total_points, fr.division,
                           fr.ac, fr.htm, fr.his, fr.geo, fr.kisw, fr.eng, fr.b_math, fr.adv_m, fr.eco, fr.fren
                    FROM students s
                    LEFT JOIN $results_table fr ON s.id = fr.student_id AND fr.exam_type_id = $exam_type_id
                    WHERE s.id = $student_id";
    $student_result = mysqli_query($conn, $student_sql);
    $student = mysqli_fetch_assoc($student_result);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }
    
    if (empty($student['parent_phone'])) {
        echo json_encode(['success' => false, 'message' => 'Parent phone number not available']);
        exit();
    }
    
    // Get rank - sequential, no ties
    $rank_sql = "SELECT s.id, s.first_name, s.last_name, fr.average
            FROM students s
            LEFT JOIN $results_table fr ON s.id = fr.student_id AND fr.exam_type_id = $exam_type_id
            WHERE s.class = '$form_level' AND fr.average IS NOT NULL
            ORDER BY fr.average DESC, s.first_name ASC, s.last_name ASC";
    $rank_result = mysqli_query($conn, $rank_sql);
    $temp = [];
    while ($row = mysqli_fetch_assoc($rank_result)) $temp[] = $row;
    
    $student_rank = null;
    foreach ($temp as $index => $st) {
        if ($st['id'] == $student_id) {
            $student_rank = $index + 1;
            break;
        }
    }
    $total_students = count($temp);
    
    $student_name = $student['first_name'] . ' ' . $student['last_name'];
    $average = $student['average'] ? number_format($student['average'], 1) : 'N/A';
    $division = $student['division'] ?: 'Not Assigned';
    $points = $student['total_points'] ?: 'N/A';
    
    $exam_sql = "SELECT exam_name FROM exam_types WHERE id = $exam_type_id";
    $exam_result = mysqli_query($conn, $exam_sql);
    $exam = mysqli_fetch_assoc($exam_result);
    $exam_name = $exam ? $exam['exam_name'] : 'Examination';
    
    $subjects_performance = [];
    $subject_list = ['ac', 'htm', 'his', 'geo', 'kisw', 'eng', 'b_math', 'adv_m', 'eco', 'fren'];
    $subject_names = ['AC', 'HTM', 'HIST', 'GEO', 'KIS', 'ENG', 'B/MAT', 'ADV/M', 'ECO', 'FRE'];
    
    for ($i = 0; $i < count($subject_list); $i++) {
        $subject = $subject_list[$i];
        $marks = $student[$subject];
        if ($marks !== null && $marks > 0) {
            $grade = getGradeLetter($marks);
            $subjects_performance[] = $subject_names[$i] . ' ' . $grade;
        }
    }
    $subjects_str = implode(', ', $subjects_performance);
    
    $message = "Habari mzazi wa $student_name, Mtihani wa $exam_name Amekuwa nafasi $student_rank/$total_students Wastan $average% $division point $points. $subjects_str";
    
    if (strlen($message) > SMS_MAX_CHARS) {
        $message = substr($message, 0, SMS_MAX_CHARS - 2) . '..';
    }
    
    $result = sendSMS($student['parent_phone'], $message);
    
    echo json_encode([
        'success' => $result['success'],
        'message' => $result['message'],
        'sms_content' => $message
    ]);
    exit();
}

// ============================================
// AJAX: Handle bulk SMS send
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_send_sms'])) {
    header('Content-Type: application/json');
    
    $student_ids = isset($_POST['student_ids']) ? $_POST['student_ids'] : [];
    $exam_type_id = intval($_POST['exam_type_id']);
    $form = mysqli_real_escape_string($conn, $_POST['form']);
    
    if (empty($student_ids)) {
        echo json_encode(['success' => false, 'message' => 'No students selected']);
        exit();
    }
    
    $results_table = ($form == 'Form five') ? 'form_five_results' : 'form_six_results';
    $form_level = ($form == 'Form five') ? 'Form five' : 'Form six';
    $success_count = 0;
    $fail_count = 0;
    $failed_students = [];
    
    // Get all ranks - sequential, no ties
    $rank_sql = "SELECT s.id, s.first_name, s.last_name, fr.average
            FROM students s
            LEFT JOIN $results_table fr ON s.id = fr.student_id AND fr.exam_type_id = $exam_type_id
            WHERE s.class = '$form_level' AND fr.average IS NOT NULL
            ORDER BY fr.average DESC, s.first_name ASC, s.last_name ASC";
    $rank_result = mysqli_query($conn, $rank_sql);
    $temp = [];
    while ($row = mysqli_fetch_assoc($rank_result)) $temp[] = $row;
    
    $rank_map = [];
    foreach ($temp as $index => $st) {
        $rank_map[$st['id']] = $index + 1;
    }
    $total_students = count($temp);
    
    foreach ($student_ids as $student_id) {
        $student_id = intval($student_id);
        
        $student_sql = "SELECT s.id, s.first_name, s.last_name, s.parent_phone, s.combination,
                               fr.average, fr.total_points, fr.division,
                               fr.ac, fr.htm, fr.his, fr.geo, fr.kisw, fr.eng, fr.b_math, fr.adv_m, fr.eco, fr.fren
                        FROM students s
                        LEFT JOIN $results_table fr ON s.id = fr.student_id AND fr.exam_type_id = $exam_type_id
                        WHERE s.id = $student_id";
        $student_result = mysqli_query($conn, $student_sql);
        $student = mysqli_fetch_assoc($student_result);
        
        if (!$student || empty($student['parent_phone'])) {
            $fail_count++;
            $failed_students[] = $student ? $student['first_name'] . ' ' . $student['last_name'] . ' (No phone number)' : 'Unknown';
            continue;
        }
        
        $student_rank = $rank_map[$student_id] ?? 'N/A';
        $student_name = $student['first_name'] . ' ' . $student['last_name'];
        $average = $student['average'] ? number_format($student['average'], 1) : 'N/A';
        $division = $student['division'] ?: 'Not Assigned';
        $points = $student['total_points'] ?: 'N/A';
        
        $exam_sql = "SELECT exam_name FROM exam_types WHERE id = $exam_type_id";
        $exam_result = mysqli_query($conn, $exam_sql);
        $exam = mysqli_fetch_assoc($exam_result);
        $exam_name = $exam ? $exam['exam_name'] : 'Examination';
        
        $subjects_performance = [];
        $subject_list = ['ac', 'htm', 'his', 'geo', 'kisw', 'eng', 'b_math', 'adv_m', 'eco', 'fren'];
        $subject_names = ['AC', 'HTM', 'HIS', 'GEO', 'KIS', 'ENG', 'B/MAT', 'ADV/M', 'ECO', 'FRE'];
        
        for ($i = 0; $i < count($subject_list); $i++) {
            $subject = $subject_list[$i];
            $marks = $student[$subject];
            if ($marks !== null && $marks > 0) {
                $grade = getGradeLetter($marks);
                $subjects_performance[] = $subject_names[$i] . ' ' . $grade;
            }
        }
        $subjects_str = implode(', ', $subjects_performance);
        
        $message = "Habari mzazi wa $student_name, Mtihani wa $exam_name Amekuwa nafasi $student_rank/$total_students Wastan $average% $division point $points. $subjects_str";
    
        if (strlen($message) > SMS_MAX_CHARS) {
            $message = substr($message, 0, SMS_MAX_CHARS - 2) . '..';
        }
        
        $result = sendSMS($student['parent_phone'], $message);
        
        if ($result['success']) {
            $success_count++;
        } else {
            $fail_count++;
            $failed_students[] = $student_name . ': ' . $result['message'];
        }
        
        usleep(200000);
    }
    
    $response_message = "✅ $success_count SMS sent successfully";
    if ($fail_count > 0) $response_message .= ", ❌ $fail_count failed";
    
    echo json_encode([
        'success' => true,
        'message' => $response_message,
        'success_count' => $success_count,
        'fail_count' => $fail_count,
        'failed_list' => $failed_students
    ]);
    exit();
}

$sms_balance = checkSMSBalance();

$div1_count = count(array_filter($students, function($s) { return $s['division'] === 'Division I'; }));
$div2_count = count(array_filter($students, function($s) { return $s['division'] === 'Division II'; }));
$div3_count = count(array_filter($students, function($s) { return $s['division'] === 'Division III'; }));
$passed = $div1_count + $div2_count + $div3_count;
$pass_rate = count($students) > 0 ? round(($passed / count($students)) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent SMS Results - Send Results to Parents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style> :root {--primary-color: <?php echo $colors['primary']; ?>; --primary-dark: <?php echo $colors['primary_dark']; ?>; --primary-light: <?php echo $colors['primary_light']; ?>; --text-color: <?php echo $colors['text']; ?>; --text-light: <?php echo $colors['text_light']; ?>; --border-color: <?php echo $colors['border']; ?>; --success: <?php echo $colors['success']; ?>; --danger: <?php echo $colors['danger']; ?>; --warning: <?php echo $colors['warning']; ?>; --info: <?php echo $colors['info']; ?>; --font-size-base: <?php echo $font_size; ?>; --spacing-base: <?php echo $compact_mode ? '0.75rem' : '1rem'; ?>; --animation-speed: <?php echo $animation_time; ?>; } * { transition: <?php echo $animations ? 'all var(--animation-speed) ease' : 'none'; ?>; } body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; font-size: var(--font-size-base); } .main-content { margin-left: 260px; padding: 20px; transition: all 0.3s; } @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } } <?php if ($compact_mode): ?> .card-body { padding: 0.75rem !important; } .btn { padding: 0.5rem 1rem !important; } .form-control, .form-select { padding: 0.375rem 0.75rem !important; } <?php endif; ?> .filter-bar { background: white; border-radius: 15px; padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); } .stat-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; transition: transform 0.3s; } .stat-card:hover { transform: translateY(-5px); } .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 15px; } .stat-value { font-size: 28px; font-weight: bold; margin-bottom: 5px; } .stat-label { color: #666; font-size: 14px; } .results-table-container { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow-x: auto; } .results-table { width: 100%; font-size: 13px; } .results-table thead th { background: var(--primary-color); color: white; padding: 12px; text-align: center; position: sticky; top: 0; } .results-table tbody td { padding: 10px; text-align: center; vertical-align: middle; } .results-table tbody tr:hover { background: #f8f9fa; } .badge-division { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; } .division-i { background: #27ae60; color: white; } .division-ii { background: #2ecc71; color: white; } .division-iii { background: #f39c12; color: white; } .division-iv { background: #e67e22; color: white; } .division-0 { background: #e74c3c; color: white; } .action-buttons { display: flex; gap: 5px; justify-content: center; flex-wrap: wrap; } .action-btn { padding: 5px 10px; border-radius: 6px; font-size: 11px; transition: all 0.2s; border: none; cursor: pointer; } .btn-view { background: var(--info); color: white; } .btn-view:hover { background: #2980b9; transform: scale(1.05); } .btn-edit-phone { background: var(--warning); color: white; } .btn-edit-phone:hover { background: #e67e22; transform: scale(1.05); } .btn-send { background: var(--success); color: white; } .btn-send:hover { background: #1e7e34; transform: scale(1.05); } .section-title { font-size: 18px; font-weight: 600; margin-bottom: 20px; color: var(--primary-color); border-left: 4px solid var(--primary-color); padding-left: 15px; } .search-box { position: relative; } .search-box input { padding-right: 40px; } .search-box button { position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #999; } .loading-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(5px); } .loading-overlay.show { display: flex; } .loading-container { background: white; padding: 40px; border-radius: 20px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.3); animation: slideIn 0.3s ease; } @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } } .loader { display: flex; gap: 8px; align-items: flex-end; justify-content: center; margin-bottom: 20px; } .loader div { width: 10px; background: var(--primary-color); animation: load 1s infinite ease-in-out; border-radius: 6px; } .loader div:nth-child(1) { height: 10px; animation-delay: 0s; } .loader div:nth-child(2) { height: 20px; animation-delay: 0.1s; } .loader div:nth-child(3) { height: 30px; animation-delay: 0.2s; } .loader div:nth-child(4) { height: 40px; animation-delay: 0.3s; } .loader div:nth-child(5) { height: 30px; animation-delay: 0.4s; } .loader div:nth-child(6) { height: 20px; animation-delay: 0.5s; } .loader div:nth-child(7) { height: 10px; animation-delay: 0.6s; } @keyframes load { 0%, 100% { transform: scaleY(1); opacity: 0.5; } 50% { transform: scaleY(2); opacity: 1; background: var(--primary-dark); } } .loading-text { color: var(--text-color); font-size: 18px; font-weight: 600; margin: 15px 0 5px; } .loading-subtext { color: var(--text-light); font-size: 14px; } .bulk-bar { background: #e8f4fd; border-radius: 10px; padding: 12px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; } .rank-badge { background: #6c5ce7; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; } .rank-badge.top-1 { background: linear-gradient(135deg, #f39c12, #e67e22); } .rank-badge.top-2 { background: linear-gradient(135deg, #bdc3c7, #95a5a6); } .rank-badge.top-3 { background: linear-gradient(135deg, #cd6133, #b33939); } .custom-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 10000; justify-content: center; align-items: center; } .custom-modal.show { display: flex; } .custom-modal-content { background: white; border-radius: 20px; width: 90%; max-width: 550px; animation: modalSlideIn 0.3s ease; box-shadow: 0 20px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; } .custom-modal-header { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; padding: 15px 20px; border-radius: 20px 20px 0 0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; } .custom-modal-header h5 { margin: 0; font-size: 1.1rem; } .custom-modal-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; transition: transform 0.2s; } .custom-modal-close:hover { transform: scale(1.1); } .custom-modal-body { padding: 20px; } .custom-modal-footer { padding: 15px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; } .form-group { margin-bottom: 15px; } .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: var(--text-color); } .form-group input { width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 10px; font-size: 14px; transition: all 0.2s; } .form-group input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(59, 157, 179, 0.1); } .phone-preview { font-size: 12px; color: #666; margin-top: 5px; } .btn-save { background: var(--success); color: white; border: none; padding: 8px 20px; border-radius: 25px; cursor: pointer; transition: all 0.3s; } .btn-save:hover { background: #1e7e34; transform: scale(1.02); } .btn-cancel-modal { background: var(--danger); color: white; border: none; padding: 8px 20px; border-radius: 25px; cursor: pointer; transition: all 0.3s; } .btn-cancel-modal:hover { background: #c82333; } .student-info-modal { background: #f8f9fa; padding: 12px; border-radius: 12px; margin-bottom: 20px; } .student-info-modal p { margin: 5px 0; font-size: 13px; } .student-info-modal strong { color: var(--primary-color); } .subject-view-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; margin-bottom: 8px; background: #f8f9fa; border-radius: 10px; } .subject-view-item .subject-name { font-weight: 600; width: 140px; } .subject-view-item .subject-marks-display { width: 80px; text-align: center; font-weight: bold; } .subject-view-item .subject-grade { width: 60px; text-align: center; } .subject-view-item .subject-point { width: 50px; text-align: center; } .sms-stat-card { background: white !important; } @media (max-width: 768px) {.action-buttons { flex-direction: column; align-items: center; } .action-btn { width: 100%; margin-bottom: 3px; } .bulk-bar { flex-direction: column; align-items: stretch; } .subject-view-item { flex-wrap: wrap; gap: 8px; } } </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h2 style="color: var(--text-color);">
                    <i class="fas fa-envelope me-2" style="color: var(--primary-color);"></i>
                    Send Results to Parents via SMS
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
                        <label class="form-label fw-bold">Select Active Exam</label>
                        <select id="examSelector" class="form-select">
                            <option value="0">-- Select Exam --</option>
                            <?php foreach ($exam_types as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>" <?php echo $selected_exam == $exam['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['exam_name']); ?> (<?php echo $exam['year']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($exam_types)): ?>
                            <small class="text-danger">No active exam found. Please activate an exam in Exam Type Manager.</small>
                        <?php endif; ?>
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
                            <span><i class="fas fa-users me-1 text-muted"></i> Students with phone: <?php echo count($students); ?></span>
                            <span><i class="fas fa-phone me-1 text-muted"></i> SMS limit: <?php echo SMS_MAX_CHARS; ?> chars</span>
                        </div>
                    </div>
                <?php elseif (!empty($exam_types)): ?>
                    <div class="mt-3 pt-2 border-top">
                        <div class="alert alert-info mb-0 py-2">
                            <i class="fas fa-info-circle me-2"></i> Select an exam to view student results
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: <?php echo $colors['primary']; ?>20; color: var(--info);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo count($students); ?></div>
                        <div class="stat-label">Students with Parent Phone</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: <?php echo $colors['primary']; ?>20; color: var(--success);">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-value"><?php echo $div1_count; ?></div>
                        <div class="stat-label">Division I Students</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: <?php echo $colors['primary']; ?>20; color: var(--warning);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-value"><?php echo $pass_rate; ?>%</div>
                        <div class="stat-label">Pass Rate</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card sms-stat-card">
                        <div class="stat-icon" style="background: <?php echo $colors['primary']; ?>20; color: var(--primary-color);">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="stat-value"><?php echo $sms_balance; ?></div>
                        <div class="stat-label">SMS Balance Remaining</div>
                    </div>
                </div>
            </div>

            <!-- Bulk SMS Bar -->
            <div class="bulk-bar" id="bulkBar" style="display: none;">
                <div>
                    <i class="fas fa-check-circle text-success me-2"></i>
                    <span id="selectedCount">0</span> students selected
                </div>
                <div>
                    <button class="btn btn-sm btn-success me-2" onclick="sendBulkSMS()">
                        <i class="fas fa-paper-plane me-1"></i> Send SMS to Selected
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="clearSelection()">
                        <i class="fas fa-times me-1"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Results Table -->
            <div class="results-table-container">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="section-title mb-0">
                        <i class="fas fa-table-list me-2"></i>Student Results
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" onclick="selectAllVisible()">
                            <i class="fas fa-check-double me-1"></i> Select All Visible
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="results-table" id="resultsTable">
                        <thead>
                            <tr>
                                <th style="width: 30px;"><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)"></th>
                                <th>#</th>
                                <th>Index No</th>
                                <th>Student Name</th>
                                <th>Sex</th>
                                <th>Combination</th>
                                <th>Rank</th>
                                <th>Total Points</th>
                                <th>Average</th>
                                <th>Division</th>
                                <th>Parent Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr><td colspan="12" class="text-center py-5">
                                    <?php if (empty($exam_types)): ?>
                                        <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block text-warning"></i>
                                        No active exam found. Please activate an exam in Exam Type Manager.
                                    <?php elseif ($selected_exam == 0): ?>
                                        <i class="fas fa-info-circle fa-2x mb-2 d-block text-info"></i>
                                        Please select an exam to view results.
                                    <?php else: ?>
                                        <i class="fas fa-inbox fa-2x mb-2 d-block text-muted"></i>
                                        No results found with parent phone numbers
                                    <?php endif; ?>
                                </td></tr>
                            <?php else: ?>
                                <?php $counter = 1; foreach ($students as $student): 
                                    $rank_class = '';
                                    if ($student['rank'] == 1) $rank_class = 'top-1';
                                    elseif ($student['rank'] == 2) $rank_class = 'top-2';
                                    elseif ($student['rank'] == 3) $rank_class = 'top-3';
                                ?>
                                    <tr data-student-id="<?php echo $student['id']; ?>">
                                        <td><input type="checkbox" class="student-checkbox" value="<?php echo $student['id']; ?>"></td>
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
                                        <td>
                                            <span class="rank-badge <?php echo $rank_class; ?>">
                                                <?php echo $student['rank']; ?>/<?php echo $total_students_with_results; ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo $student['total_points'] ?? '-'; ?></strong></td>
                                        <td>
                                            <strong><?php echo $student['average'] ? number_format($student['average'], 1) . '%' : '-'; ?></strong>
                                            <?php if ($student['average']): ?>
                                                <div class="progress" style="height: 3px; margin-top: 5px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $student['average']; ?>%"></div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($student['division'] && $student['division'] != 'Not Assigned'): ?>
                                                <span class="badge-division <?php echo strtolower(str_replace(' ', '-', $student['division'])); ?>">
                                                    <?php echo $student['division']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($student['parent_phone'])): ?>
                                                <i class="fas fa-phone text-success me-1"></i>
                                                <?php echo htmlspecialchars($student['parent_phone_formatted']); ?>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="fas fa-ban"></i> No phone</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn btn-view" onclick="viewStudent(<?php echo $student['id']; ?>, <?php echo $selected_exam; ?>, '<?php echo $selected_form; ?>')" title="View Results">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="action-btn btn-edit-phone" onclick="openChangePhoneModal(<?php echo $student['id']; ?>, '<?php echo addslashes($student['first_name']); ?>', '<?php echo addslashes($student['last_name']); ?>', '<?php echo addslashes($student['second_name']); ?>', '<?php echo $student['index_number']; ?>', '<?php echo $student['sex']; ?>', '<?php echo $student['combination']; ?>', '<?php echo addslashes($student['parent_phone_formatted']); ?>', '<?php echo addslashes($student['parent_name']); ?>', '<?php echo addslashes($student['parent_occupation']); ?>', '<?php echo addslashes($student['parent_residence']); ?>')" title="Change Parent Phone">
                                                    <i class="fas fa-phone-alt"></i> Change Phone
                                                </button>
                                                <button class="action-btn btn-send" onclick="sendSingleSMS(<?php echo $student['id']; ?>, <?php echo $selected_exam; ?>, '<?php echo $selected_form; ?>', '<?php echo addslashes($student['first_name'] . ' ' . $student['last_name']); ?>')" title="Send SMS to Parent">
                                                    <i class="fas fa-paper-plane"></i> Send
                                                </button>
                                            </div>
                                         </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>SMS Information:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Messages are limited to <strong><?php echo SMS_MAX_CHARS; ?> characters</strong></li>
                            <li>SMS format: Habari mzazi wa [Student Name], [Exam Name]: amekuwa nafasi [Rank]/[Total], wastani [Average]%, [Division], point [Points]. [Subject: Grade]</li>
                            <li><strong>Rank is calculated based on AVERAGE score (highest average = Rank 1). If same average, alphabetical order by name determines rank.</strong></li>
                            <li>Click <strong>View</strong> to see full results (read-only - cannot edit marks)</li>
                            <li>Click <strong>Change Phone</strong> to update parent contact information</li>
                            <li>Click <strong>Send</strong> to send SMS to individual parent</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-container">
            <div class="loader"><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>
            <div class="loading-text" id="loadingText">Processing...</div>
            <div class="loading-subtext" id="loadingSubtext">Please wait...</div>
        </div>
    </div>

    <!-- Custom Modal for Changing Phone Number -->
    <div id="phoneModal" class="custom-modal">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <h5><i class="fas fa-phone-alt me-2"></i>Change Parent Contact Information</h5>
                <button class="custom-modal-close" onclick="closePhoneModal()">&times;</button>
            </div>
            <div class="custom-modal-body">
                <div id="modalStudentInfo" class="student-info-modal"><p><strong>Loading...</strong></p></div>
                <form id="phoneForm">
                    <input type="hidden" id="student_id" name="student_id">
                    <div class="form-group">
                        <label><i class="fas fa-user me-1"></i> Parent/Guardian Name</label>
                        <input type="text" id="parent_name" class="form-control" placeholder="e.g., John Michael">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone me-1"></i> Phone Number</label>
                        <input type="tel" id="parent_phone" class="form-control" placeholder="e.g., 0712345678 or 255712345678">
                        <div class="phone-preview" id="phonePreview"></div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-briefcase me-1"></i> Occupation (Optional)</label>
                        <input type="text" id="parent_occupation" class="form-control" placeholder="e.g., Farmer, Teacher">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-home me-1"></i> Residence (Optional)</label>
                        <input type="text" id="parent_residence" class="form-control" placeholder="e.g., Muyovozi">
                    </div>
                </form>
            </div>
            <div class="custom-modal-footer">
                <button class="btn-cancel-modal" onclick="closePhoneModal()"><i class="fas fa-times me-1"></i> Cancel</button>
                <button class="btn-save" onclick="savePhoneNumber()"><i class="fas fa-save me-1"></i> Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Custom Modal for Viewing Student Results (READ ONLY) -->
    <div id="viewModal" class="custom-modal">
        <div class="custom-modal-content" style="max-width: 800px;">
            <div class="custom-modal-header">
                <h5><i class="fas fa-user-graduate me-2"></i>Student Academic Report - <span id="viewStudentName"></span></h5>
                <button class="custom-modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="custom-modal-body" id="viewModalBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading...</p></div>
            </div>
            <div class="custom-modal-footer">
                <button class="btn-cancel-modal" onclick="closeViewModal()"><i class="fas fa-times me-1"></i> Close</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let selectedStudents = new Set();
        let currentExamId = <?php echo $selected_exam; ?>;
        let currentForm = '<?php echo $selected_form; ?>';

        $(document).ready(function() {
            if ($('#resultsTable tbody tr').length > 0) {
                $('#resultsTable').DataTable({ pageLength: 25, order: [[2, 'asc']], language: { search: "Search:", lengthMenu: "Show _MENU_ entries", info: "Showing _START_ to _END_ of _TOTAL_ entries" } });
            }
        });

        $('#formSelector, #examSelector').on('change', function() {
            const form = $('#formSelector').val();
            const examId = $('#examSelector').val();
            window.location.href = `parent_sms_results.php?form=${encodeURIComponent(form)}&exam_id=${examId}`;
        });

        $('#searchBtn').click(function() {
            const search = $('#searchInput').val();
            const form = $('#formSelector').val();
            const examId = $('#examSelector').val();
            window.location.href = `parent_sms_results.php?form=${encodeURIComponent(form)}&exam_id=${examId}&search=${encodeURIComponent(search)}`;
        });

        $('#searchInput').keypress(function(e) { if (e.which === 13) $('#searchBtn').click(); });

        function toggleSelectAll(checkbox) {
            const visibleRows = $('#resultsTable tbody tr:visible');
            visibleRows.find('.student-checkbox').each(function() {
                this.checked = checkbox.checked;
                const studentId = parseInt($(this).val());
                checkbox.checked ? selectedStudents.add(studentId) : selectedStudents.delete(studentId);
            });
            updateBulkBar();
        }

        $(document).on('change', '.student-checkbox', function() {
            const studentId = parseInt($(this).val());
            this.checked ? selectedStudents.add(studentId) : selectedStudents.delete(studentId);
            updateBulkBar();
            const totalVisible = $('#resultsTable tbody tr:visible').length;
            const checkedVisible = $('#resultsTable tbody tr:visible .student-checkbox:checked').length;
            $('#selectAllCheckbox').prop('checked', totalVisible > 0 && checkedVisible === totalVisible);
            $('#selectAllCheckbox').prop('indeterminate', checkedVisible > 0 && checkedVisible < totalVisible);
        });

        function updateBulkBar() { selectedStudents.size > 0 ? $('#bulkBar').show() : $('#bulkBar').hide(); $('#selectedCount').text(selectedStudents.size); }
        function selectAllVisible() { $('#resultsTable tbody tr:visible .student-checkbox').each(function() { this.checked = true; selectedStudents.add(parseInt($(this).val())); }); updateBulkBar(); $('#selectAllCheckbox').prop('checked', true); $('#selectAllCheckbox').prop('indeterminate', false); }
        function clearSelection() { $('.student-checkbox').prop('checked', false); selectedStudents.clear(); updateBulkBar(); $('#selectAllCheckbox').prop('checked', false); $('#selectAllCheckbox').prop('indeterminate', false); }
        function showLoading(msg, sub) { $('#loadingText').text(msg); $('#loadingSubtext').text(sub); $('#loadingOverlay').addClass('show'); }
        function hideLoading() { $('#loadingOverlay').removeClass('show'); }

        // ============================================
        // VIEW STUDENT - READ ONLY (No edit)
        // ============================================
        function viewStudent(studentId, examTypeId, form) {
            $('#viewModalBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading student details...</p></div>');
            $('#viewStudentName').text('Loading...');
            $('#viewModal').addClass('show');
            
            $.ajax({
                url: window.location.href,
                method: 'GET',
                data: { get_student_details: 1, student_id: studentId, exam_type_id: examTypeId, form: form },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayViewModal(response);
                    } else {
                        $('#viewModalBody').html(`<div class="alert alert-danger">${response.error || 'Failed to load'}</div>`);
                    }
                },
                error: function() { $('#viewModalBody').html('<div class="alert alert-danger">Error loading student details</div>'); }
            });
        }

        function displayViewModal(data) {
            const student = data.student;
            const subjects = data.subjects || [];
            
            $('#viewStudentName').text(student.first_name + ' ' + student.last_name);
            
            const divisionClass = student.division ? student.division.toLowerCase().replace(' ', '-') : 'bg-secondary';
            const rankDisplay = student.rank && student.total_students ? `${student.rank}/${student.total_students}` : 'N/A';
            
            let subjectsHtml = '<h6 class="mb-3"><i class="fas fa-book-open me-2"></i>Subject Performance</h6>';
            subjectsHtml += '<div id="subjectsList">';
            
            subjects.forEach(subject => {
                let gradeColor = '';
                if (subject.marks >= 80) gradeColor = '#27ae60';
                else if (subject.marks >= 70) gradeColor = '#2ecc71';
                else if (subject.marks >= 60) gradeColor = '#f39c12';
                else if (subject.marks >= 50) gradeColor = '#e67e22';
                else if (subject.marks >= 40) gradeColor = '#3498db';
                else if (subject.marks >= 35) gradeColor = '#95a5a6';
                else if (subject.marks !== null) gradeColor = '#e74c3c';
                else gradeColor = '#6c757d';
                
                subjectsHtml += `
                    <div class="subject-view-item">
                        <div class="subject-name"><strong>${subject.name}</strong><br><small class="text-muted">${subject.short}</small></div>
                        <div class="subject-marks-display"><strong>${subject.marks !== null ? subject.marks : '-'}%</strong></div>
                        <div class="subject-grade"><span class="badge" style="background: ${gradeColor};">${subject.grade || '-'}</span></div>
                        <div class="subject-point"><small>Points: ${subject.grade_point !== null ? subject.grade_point : '-'}</small></div>
                    </div>
                `;
            });
            subjectsHtml += '</div>';
            
            const html = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card"><div class="card-body">
                            <h6 class="card-title text-muted mb-2">Student Information</h6>
                            <p class="mb-1"><strong>Name:</strong> ${student.first_name} ${student.last_name}</p>
                            <p class="mb-1"><strong>Index Number:</strong> ${student.index_number}</p>
                            <p class="mb-1"><strong>Gender:</strong> ${student.sex}</p>
                            <p class="mb-1"><strong>Combination:</strong> ${student.combination}</p>
                            <p class="mb-1"><strong>Rank:</strong> ${rankDisplay}</p>
                        </div></div>
                    </div>
                    <div class="col-md-6">
                        <div class="card"><div class="card-body">
                            <h6 class="card-title text-muted mb-2">Parent Contact</h6>
                            <p class="mb-1"><strong>Parent Name:</strong> ${student.parent_name || 'N/A'}</p>
                            <p class="mb-1"><strong>Phone:</strong> ${student.parent_phone_formatted || 'N/A'}</p>
                            <p class="mb-1"><strong>Occupation:</strong> ${student.parent_occupation || 'N/A'}</p>
                            <p class="mb-1"><strong>Residence:</strong> ${student.parent_residence || 'N/A'}</p>
                        </div></div>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card"><div class="card-body">
                            <h6 class="card-title text-muted mb-2">Performance Summary</h6>
                            <p class="mb-1"><strong>Total Points:</strong> ${student.total_points || 'N/A'}</p>
                            <p class="mb-1"><strong>Average:</strong> ${student.average ? parseFloat(student.average).toFixed(1) + '%' : 'N/A'}</p>
                            <p class="mb-1"><strong>Division:</strong> <span class="badge ${divisionClass}">${student.division || 'Not Assigned'}</span></p>
                        </div></div>
                    </div>
                </div>
                ${subjectsHtml}
            `;
            
            $('#viewModalBody').html(html);
        }

        function closeViewModal() { $('#viewModal').removeClass('show'); }

        // ============================================
        // CHANGE PHONE MODAL FUNCTIONS
        // ============================================
        function openChangePhoneModal(studentId, firstName, lastName, secondName, indexNumber, sex, combination, parentPhone, parentName, parentOccupation, parentResidence) {
            const fullName = firstName + ' ' + lastName + (secondName ? ' ' + secondName : '');
            $('#modalStudentInfo').html(`<p><strong><i class="fas fa-user-graduate me-1"></i> Student:</strong> ${fullName}</p><p><strong><i class="fas fa-id-card me-1"></i> Index:</strong> ${indexNumber}</p><p><strong><i class="fas fa-venus-mars me-1"></i> Gender:</strong> ${sex}</p><p><strong><i class="fas fa-layer-group me-1"></i> Combination:</strong> ${combination}</p>`);
            $('#student_id').val(studentId);
            $('#parent_name').val(parentName || '');
            $('#parent_phone').val(parentPhone || '');
            $('#parent_occupation').val(parentOccupation || '');
            $('#parent_residence').val(parentResidence || '');
            $('#phonePreview').html(parentPhone ? `Current: <strong>${parentPhone}</strong>` : '');
            $('#phoneModal').addClass('show');
        }

        function closePhoneModal() { $('#phoneModal').removeClass('show'); }

        $('#parent_phone').on('input', function() {
            let phone = $(this).val().replace(/[^0-9]/g, '');
            if (phone.length > 0) {
                if (phone.substring(0, 1) === '0') $('#phonePreview').html(`Will be saved as: <strong>255${phone.substring(1)}</strong>`);
                else if (phone.substring(0, 3) === '255') $('#phonePreview').html(`Will be saved as: <strong>${phone}</strong>`);
                else if (phone.substring(0, 1) === '7' || phone.substring(0, 1) === '6') $('#phonePreview').html(`Will be saved as: <strong>255${phone}</strong>`);
                else $('#phonePreview').html(`<span class="text-warning">Please enter a valid Tanzanian phone number</span>`);
            } else { $('#phonePreview').html(''); }
        });

        function savePhoneNumber() {
            const studentId = $('#student_id').val();
            const parentPhone = $('#parent_phone').val();
            const parentName = $('#parent_name').val();
            const parentOccupation = $('#parent_occupation').val();
            const parentResidence = $('#parent_residence').val();
            
            if (!studentId) { Swal.fire('Error', 'Invalid student ID', 'error'); return; }
            
            Swal.fire({ title: 'Saving...', text: 'Please wait...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            
            $.ajax({
                url: window.location.href, method: 'POST', data: { update_parent_phone: 1, student_id: studentId, parent_phone: parentPhone, parent_name: parentName, parent_occupation: parentOccupation, parent_residence: parentResidence }, dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        closePhoneModal();
                        const row = $(`tr[data-student-id="${studentId}"]`);
                        row.find('td:eq(10)').html(`<i class="fas fa-phone text-success me-1"></i> ${response.display_phone || parentPhone}`);
                        Swal.fire({ title: 'Updated!', text: response.message, icon: 'success', timer: 1500, showConfirmButton: false });
                    } else { Swal.fire('Error!', response.message, 'error'); }
                },
                error: function() { Swal.close(); Swal.fire('Error!', 'Failed to save. Please try again.', 'error'); }
            });
        }

        // ============================================
        // SMS FUNCTIONS
        // ============================================
        function sendSingleSMS(studentId, examTypeId, form, studentName) {
            if (!examTypeId || examTypeId === 0) { Swal.fire({ title: 'No Exam Selected', text: 'Please select an exam first', icon: 'warning' }); return; }
            Swal.fire({ title: 'Send SMS to Parent', text: `Send result notification to parent of ${studentName}?`, icon: 'question', showCancelButton: true, confirmButtonColor: '#27ae60', cancelButtonColor: '#6c757d', confirmButtonText: 'Yes, Send SMS' }).then((result) => {
                if (result.isConfirmed) {
                    showLoading('Sending SMS...', `Sending to parent of ${studentName}`);
                    $.ajax({ url: window.location.href, method: 'POST', data: { ajax_send_sms: 1, student_id: studentId, exam_type_id: examTypeId, form: form }, dataType: 'json',
                        success: function(response) {
                            hideLoading();
                            if (response.success) { Swal.fire({ title: 'SMS Sent!', text: response.message, icon: 'success', timer: 3000, showConfirmButton: false }); }
                            else { Swal.fire({ title: 'Failed', text: response.message, icon: 'error' }); }
                        },
                        error: function() { hideLoading(); Swal.fire({ title: 'Error', text: 'Failed to send SMS. Please try again.', icon: 'error' }); }
                    });
                }
            });
        }

        function sendBulkSMS() {
            const studentIds = Array.from(selectedStudents);
            const examId = $('#examSelector').val();
            if (studentIds.length === 0) { Swal.fire({ title: 'No Selection', text: 'Please select at least one student', icon: 'warning' }); return; }
            if (!examId || examId === '0') { Swal.fire({ title: 'No Exam Selected', text: 'Please select an exam first', icon: 'warning' }); return; }
            Swal.fire({ title: 'Send Bulk SMS', text: `Send result notifications to ${studentIds.length} parent(s)?`, icon: 'question', showCancelButton: true, confirmButtonColor: '#27ae60', confirmButtonText: 'Yes, Send All' }).then((result) => {
                if (result.isConfirmed) {
                    showLoading('Sending Bulk SMS...', `Sending to ${studentIds.length} recipients`);
                    const formData = new FormData();
                    formData.append('bulk_send_sms', '1');
                    formData.append('form', $('#formSelector').val());
                    formData.append('exam_type_id', examId);
                    studentIds.forEach(id => formData.append('student_ids[]', id));
                    $.ajax({ url: window.location.href, method: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
                        success: function(response) {
                            hideLoading();
                            if (response.success) {
                                let html = `<div><p><strong>✅ Successful: ${response.success_count || 0}</strong></p><p><strong>❌ Failed: ${response.fail_count || 0}</strong></p>`;
                                if (response.failed_list && response.failed_list.length > 0) {
                                    html += `<hr><p><strong>Failed:</strong></p><ul>`;
                                    response.failed_list.slice(0, 10).forEach(fail => html += `<li class="text-danger">${fail}</li>`);
                                    if (response.failed_list.length > 10) html += `<li>... and ${response.failed_list.length - 10} more</li>`;
                                    html += `</ul>`;
                                }
                                html += `</div>`;
                                Swal.fire({ title: 'Bulk SMS Complete', html: html, icon: response.fail_count > 0 ? 'warning' : 'success', width: '500px' });
                                clearSelection();
                            } else { Swal.fire({ title: 'Error', text: response.message || 'Failed to send bulk SMS', icon: 'error' }); }
                        },
                        error: function() { hideLoading(); Swal.fire({ title: 'Error', text: 'Failed to send bulk SMS. Please try again.', icon: 'error' }); }
                    });
                }
            });
        }

        $(document).on('click', '#phoneModal', function(e) { if (e.target === this) closePhoneModal(); });
        $(document).on('click', '#viewModal', function(e) { if (e.target === this) closeViewModal(); });
        $(document).on('keydown', function(e) { if (e.key === 'Escape') { closePhoneModal(); closeViewModal(); } });
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>