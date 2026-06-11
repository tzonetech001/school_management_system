<?php
// generate_session_timetable.php - Save timetable for all teachers to access
session_start();
require_once '../controller/db_connect.php';

// Check login
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../mhs/login.php');
    exit();
}

$admin_id = intval($_SESSION['admin_id']);

// Get all form data
$term = isset($_POST['term']) ? $_POST['term'] : 'Term 2';
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '08:00';
$session_length = isset($_POST['session_length']) ? intval($_POST['session_length']) : 40;
$sessions_per_day = isset($_POST['sessions_per_day']) ? intval($_POST['sessions_per_day']) : 6;
$break_after = isset($_POST['break_after']) ? intval($_POST['break_after']) : 2;
$break_length = isset($_POST['break_length']) ? intval($_POST['break_length']) : 30;
$export_format = isset($_POST['export_format']) ? $_POST['export_format'] : 'excel';
$action = isset($_POST['action']) ? $_POST['action'] : 'download';

// Get selected days
$selected_days = isset($_POST['days']) ? $_POST['days'] : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Get school_id
$school_id_query = "SELECT school_id FROM admins WHERE id = $admin_id";
$school_result = mysqli_query($conn, $school_id_query);
$school_data = mysqli_fetch_assoc($school_result);
$school_id = $school_data['school_id'];

// Get combinations
$form5_combinations_query = "SELECT DISTINCT combination FROM students WHERE class = 'Form Five' AND school_id = $school_id AND (is_leaver = 0 OR is_leaver IS NULL) AND combination IS NOT NULL AND combination != '' ORDER BY combination";
$form5_result = mysqli_query($conn, $form5_combinations_query);
$form5_combinations = [];
while ($row = mysqli_fetch_assoc($form5_result)) { $form5_combinations[] = $row['combination']; }

$form6_combinations_query = "SELECT DISTINCT combination FROM students WHERE class = 'Form Six' AND school_id = $school_id AND (is_leaver = 0 OR is_leaver IS NULL) AND combination IS NOT NULL AND combination != '' ORDER BY combination";
$form6_result = mysqli_query($conn, $form6_combinations_query);
$form6_combinations = [];
while ($row = mysqli_fetch_assoc($form6_result)) { $form6_combinations[] = $row['combination']; }

// Fallbacks
if (empty($form5_combinations)) { $form5_combinations = ['HGE', 'HGL', 'HGK', 'PCM', 'CBG', 'EGM', 'HGM']; }
if (empty($form6_combinations)) { $form6_combinations = ['HGE', 'HGL', 'HGK', 'PCM', 'CBG', 'EGM', 'HGM']; }

// Subject names
$subject_names = [
    'ac' => 'Accountancy', 'htm' => 'Hotel Management', 'his' => 'History', 'geo' => 'Geography',
    'kisw' => 'Kiswahili', 'eng' => 'English', 'b_math' => 'Basic Math', 'adv_m' => 'Advanced Math',
    'eco' => 'Economics', 'fren' => 'French', 'phy' => 'Physics', 'chem' => 'Chemistry',
    'bio' => 'Biology', 'civ' => 'Civics', 'lit' => 'Literature', 'comp' => 'Computer Science'
];

// Get teacher assignments
$form5_teachers = [];
$form5_assignments = mysqli_query($conn, "SELECT sta.subject, sta.teacher_id, sta.is_primary, CONCAT(a.first_name, ' ', a.last_name) as teacher_name FROM subject_teacher_assignments sta JOIN admins a ON sta.teacher_id = a.id WHERE sta.form_level = 'Form Five' AND sta.academic_year = $year AND sta.school_id = $school_id");
while ($row = mysqli_fetch_assoc($form5_assignments)) { $form5_teachers[$row['subject']][] = $row; }

$form6_teachers = [];
$form6_assignments = mysqli_query($conn, "SELECT sta.subject, sta.teacher_id, sta.is_primary, CONCAT(a.first_name, ' ', a.last_name) as teacher_name FROM subject_teacher_assignments sta JOIN admins a ON sta.teacher_id = a.id WHERE sta.form_level = 'Form Six' AND sta.academic_year = $year AND sta.school_id = $school_id");
while ($row = mysqli_fetch_assoc($form6_assignments)) { $form6_teachers[$row['subject']][] = $row; }

// Function to add minutes to time
function addMinutesToTime($time, $minutes) { 
    return date('H:i', strtotime($time) + ($minutes * 60)); 
}

// Calculate schedule rows
function calculateScheduleRows($start_time, $session_length, $sessions_per_day, $break_after, $break_length) {
    $rows = [];
    $current_time = $start_time;
    $session_number = 1;
    
    for ($i = 1; $i <= $sessions_per_day; $i++) {
        $session_end = addMinutesToTime($current_time, $session_length);
        $rows[] = [
            'type' => 'session',
            'number' => $session_number,
            'start' => $current_time,
            'end' => $session_end,
            'label' => 'Session ' . $session_number
        ];
        
        $current_time = $session_end;
        $session_number++;
        
        if ($break_after > 0 && $i == $break_after) {
            $break_end = addMinutesToTime($current_time, $break_length);
            $rows[] = [
                'type' => 'break',
                'start' => $current_time,
                'end' => $break_end,
                'duration' => $break_length,
                'label' => 'BREAK'
            ];
            $current_time = $break_end;
        }
    }
    
    return $rows;
}

$schedule_rows = calculateScheduleRows($start_time, $session_length, $sessions_per_day, $break_after, $break_length);

// Get school name
$school_name = "School Management System";
$school_q = mysqli_query($conn, "SELECT s.school_name FROM admins a JOIN schools s ON a.school_id = s.id WHERE a.id = $admin_id");
if ($row = mysqli_fetch_assoc($school_q)) { $school_name = $row['school_name']; }

$document_name = $term . ' Timetable - ' . $year;

// Generate single class timetable
function generateClassTimetable($class_name, $selected_days, $schedule_rows, $subject_teachers, $subject_names) {
    $available_subjects = array_keys($subject_teachers);
    if (empty($available_subjects)) { $available_subjects = array_keys($subject_names); }
    
    $schedule = [];
    $teacher_schedule = [];
    
    foreach ($selected_days as $day) {
        $schedule[$day] = [];
        $session_counter = 1;
        
        foreach ($schedule_rows as $row_index => $row) {
            if ($row['type'] == 'session') {
                $available_subjects_shuffled = $available_subjects;
                shuffle($available_subjects_shuffled);
                $assigned = false;
                
                foreach ($available_subjects_shuffled as $subject) {
                    $teachers = $subject_teachers[$subject] ?? [];
                    if (empty($teachers)) continue;
                    
                    $teacher = null;
                    foreach ($teachers as $t) { 
                        if ($t['is_primary'] == 1) { $teacher = $t; break; } 
                    }
                    if (!$teacher && !empty($teachers)) { $teacher = $teachers[0]; }
                    
                    if ($teacher) {
                        $teacher_key = $teacher['teacher_id'] . '_' . $day . '_' . $session_counter;
                        if (!isset($teacher_schedule[$teacher_key])) {
                            $schedule[$day][$row_index] = [
                                'type' => 'session',
                                'subject' => $subject,
                                'subject_name' => $subject_names[$subject] ?? strtoupper($subject),
                                'teacher_name' => $teacher['teacher_name']
                            ];
                            $teacher_schedule[$teacher_key] = true;
                            $assigned = true;
                            break;
                        }
                    }
                }
                
                if (!$assigned) {
                    $schedule[$day][$row_index] = [
                        'type' => 'session',
                        'subject' => 'TBA',
                        'subject_name' => 'TBA',
                        'teacher_name' => 'Not Assigned'
                    ];
                }
                $session_counter++;
            } else {
                $schedule[$day][$row_index] = [
                    'type' => 'break',
                    'duration' => $row['duration']
                ];
            }
        }
    }
    
    $html = '<div class="timetable-section" style="margin-bottom: 30px; page-break-after: avoid;">';
    $html .= '<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
    
    $html .= '<tr style="background-color: #2c7a8f; color: white;">';
    $html .= '<td colspan="' . (count($selected_days) + 1) . '" style="text-align: center; font-size: 14px; font-weight: bold; padding: 12px;">' . htmlspecialchars($class_name) . '</td>';
    $html .= '</tr>';
    
    $html .= '<tr style="background-color: #3B9DB3; color: white;">';
    $html .= '<th style="width: 100px; padding: 10px;">Day / Time</th>';
    foreach ($schedule_rows as $row) {
        if ($row['type'] == 'session') {
            $html .= '<th>' . $row['label'] . '<br><small>' . $row['start'] . ' - ' . $row['end'] . '</small></th>';
        } else {
            $html .= '<th style="background-color: #ff9800;">' . $row['label'] . '<br><small>' . $row['start'] . ' - ' . $row['end'] . '</small></th>';
        }
    }
    $html .= '</tr>';
    
    foreach ($selected_days as $day) {
        $html .= '<tr>';
        $html .= '<td style="background-color: #e8f4f8; font-weight: bold; padding: 10px;">' . htmlspecialchars($day) . '</td>';
        
        foreach ($schedule_rows as $row_index => $row) {
            $cell = $schedule[$day][$row_index] ?? ['type' => 'session', 'subject_name' => 'TBA', 'teacher_name' => 'Not Assigned'];
            
            if ($cell['type'] == 'break') {
                $html .= '<td style="background-color: #ffffcc; text-align: center; vertical-align: middle;">';
                $html .= '<strong>BREAK</strong><br>';
                $html .= '<small>' . $cell['duration'] . ' minutes</small>';
                $html .= '</td>';
            } else {
                $html .= '<td style="padding: 8px;">';
                $html .= '<strong>' . htmlspecialchars($cell['subject_name']) . '</strong><br>';
                $html .= '<small style="color: #666;">' . htmlspecialchars($cell['teacher_name']) . '</small>';
                $html .= '</td>';
            }
        }
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    $displayed_teachers = [];
    $html .= '<div style="margin-top: 8px; margin-bottom: 15px; padding: 8px; background-color: #f8f9fa; border-left: 4px solid #3B9DB3; border-radius: 4px;">';
    $html .= '<strong><i class="fas fa-chalkboard-teacher"></i> Subject Teachers:</strong> ';
    
    foreach ($schedule as $day => $day_data) {
        foreach ($day_data as $cell) {
            if (isset($cell['type']) && $cell['type'] == 'session' && isset($cell['teacher_name']) && $cell['teacher_name'] != 'Not Assigned' && $cell['teacher_name'] != '') {
                $key = $cell['subject'] . '_' . $cell['teacher_name'];
                if (!isset($displayed_teachers[$key])) {
                    $displayed_teachers[$key] = true;
                    $html .= '<span style="display: inline-block; margin: 2px 6px 2px 0; padding: 2px 8px; background-color: #e9ecef; border-radius: 12px; font-size: 11px;">';
                    $html .= '<strong>' . htmlspecialchars($cell['subject_name']) . '</strong>: ' . htmlspecialchars($cell['teacher_name']);
                    $html .= '</span>';
                }
            }
        }
    }
    $html .= '</div></div>';
    
    return $html;
}

$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($document_name) . '</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            font-size: 12px;
        }
        .header { 
            text-align: center; 
            margin-bottom: 20px; 
        }
        .school-name { 
            font-size: 20px; 
            font-weight: bold; 
        }
        .document-name { 
            font-size: 16px; 
            font-weight: bold; 
            margin-top: 5px;
            color: #2c7a8f;
        }
        .config-info { 
            text-align: center; 
            margin-bottom: 20px; 
            padding: 8px; 
            background-color: #e8f4f8; 
            border-radius: 5px; 
            font-size: 11px; 
        }
        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin-bottom: 15px;
        }
        th { 
            background-color: #3B9DB3; 
            color: white; 
            padding: 8px; 
            border: 1px solid #000;
            text-align: center;
        }
        td { 
            border: 1px solid #000; 
            padding: 8px; 
            vertical-align: top;
        }
        .footer { 
            margin-top: 30px; 
            text-align: center; 
            font-size: 10px; 
            color: #999; 
        }
        @media print {
            th { background-color: #3B9DB3 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="school-name">' . htmlspecialchars($school_name) . '</div>
        <div class="document-name">' . htmlspecialchars($document_name) . '</div>
    </div>
    <div class="config-info">
        <strong>Schedule Configuration:</strong> Start: ' . $start_time . ' | Session Length: ' . $session_length . ' min | Sessions per Day: ' . $sessions_per_day;
if ($break_after > 0 && $break_after <= $sessions_per_day) {
    $html .= ' | Break: ' . $break_length . ' min after Session ' . $break_after;
}
$html .= '
    </div>';

foreach ($form5_combinations as $combo) {
    $html .= generateClassTimetable("Form 5 - {$combo}", $selected_days, $schedule_rows, $form5_teachers, $subject_names);
}

foreach ($form6_combinations as $combo) {
    $html .= generateClassTimetable("Form 6 - {$combo}", $selected_days, $schedule_rows, $form6_teachers, $subject_names);
}

$html .= '<div class="footer">Generated on: ' . date('F d, Y g:i A') . '<br>© ' . date('Y') . ' ' . htmlspecialchars($school_name) . '</div>';
$html .= '</body></html>';

// Ensure directory exists
$timetable_dir = '../uploads/timetables/';
if (!file_exists($timetable_dir)) {
    mkdir($timetable_dir, 0777, true);
}

// Save the timetable file for all teachers to access
$filename_base = str_replace(' ', '_', $document_name);
$filename = $timetable_dir . $filename_base . '.html';
file_put_contents($filename, $html);

// Also keep a record in database for easy retrieval
$record_sql = "INSERT INTO generated_timetables (term, year, filename, generated_by, generated_at, school_id) 
               VALUES (?, ?, ?, ?, NOW(), ?) 
               ON DUPLICATE KEY UPDATE generated_at = NOW()";
$stmt = $conn->prepare($record_sql);
$stmt->bind_param("sisii", $term, $year, $filename_base, $admin_id, $school_id);
$stmt->execute();

// Output based on action
if ($action === 'download') {
    if ($export_format === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename_base . '.xls"');
        echo $html;
    } elseif ($export_format === 'pdf') {
        $print_btn = '<div style="text-align:center;margin-bottom:20px;"><button onclick="window.print();" style="padding:10px 20px;background:#3B9DB3;color:white;border:none;border-radius:5px;cursor:pointer;">🖨️ Print / Save as PDF</button></div>';
        echo str_replace('<body>', '<body>' . $print_btn, $html);
    } elseif ($export_format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename_base . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, [$school_name]);
        fputcsv($output, [$document_name]);
        fclose($output);
    }
} elseif ($action === 'view') {
    echo $html;
} elseif ($action === 'save') {
    echo '<!DOCTYPE html><html><head><title>Saved</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><meta http-equiv="refresh" content="3;url=session_timetable.php"></head><body><div class="container mt-5"><div class="alert alert-success">Timetable saved!<br><a href="' . $filename . '" target="_blank">Download</a><br>Redirecting...</div></div></body></html>';
}
?>