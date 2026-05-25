<?php
// results_report.php - Student Results Report Generator
session_start();
require_once '../controller/db_connect.php';

// Check login
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Check permission
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
    if ($role_id == 1 || $role_id == 2 || $role_id == 3 || $role_id == 12) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view this page.";
    header("Location: ../404.php");
    exit();
}

// Load theme settings
$theme_settings = [];
$settings_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
$settings_result = mysqli_query($conn, $settings_query);
if ($settings_result && mysqli_num_rows($settings_result) > 0) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $theme_settings[$row['setting_key']] = $row['setting_value'];
    }
}

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

// Get filter parameters
$selected_form = isset($_GET['form']) ? mysqli_real_escape_string($conn, $_GET['form']) : 'Form Six';
$selected_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'index';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'all';

// Normalize form name
$selected_form = ($selected_form == 'Form five' || $selected_form == 'Form Five') ? 'Form Five' : 'Form Six';
$db_form = ($selected_form == 'Form Five') ? 'Form five' : 'Form six';

// Get available exam types
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
$results_table = ($selected_form == 'Form Five') ? 'form_five_results' : 'form_six_results';

// Subject display names (CORRECTED)
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

$subject_full = [
    'ac' => 'Academic Communication',
    'htm' => 'Historia ya Tanzania na Maadili',
    'his' => 'HISTORY',
    'geo' => 'GEOGRAPHY',
    'kisw' => 'KISWAHILI',
    'eng' => 'ENGLISH',
    'b_math' => 'BASIC MATHEMATICS',
    'adv_m' => 'ADVANCED MATHS',
    'eco' => 'ECONOMICS',
    'fren' => 'FRENCH'
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

// Get all students with results
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

// Apply sorting
switch ($sort_by) {
    case 'index':
        $sql .= " ORDER BY s.index_number $sort_order";
        break;
    case 'name':
        $sql .= " ORDER BY s.first_name $sort_order, s.last_name $sort_order";
        break;
    case 'division':
        $sql .= " ORDER BY 
                    CASE fr.division
                        WHEN 'Division I' THEN 1
                        WHEN 'Division II' THEN 2
                        WHEN 'Division III' THEN 3
                        WHEN 'Division IV' THEN 4
                        WHEN 'Division 0' THEN 5
                        ELSE 6
                    END $sort_order,
                    fr.total_points ASC";
        break;
    case 'points':
        $sql .= " ORDER BY fr.total_points $sort_order";
        break;
    case 'average':
        $sql .= " ORDER BY fr.average $sort_order";
        break;
    case 'position':
        $sql .= " ORDER BY fr.average DESC";
        break;
    default:
        $sql .= " ORDER BY s.index_number ASC";
}

$students_result = mysqli_query($conn, $sql);
$all_students = [];
while ($row = mysqli_fetch_assoc($students_result)) {
    $all_students[] = $row;
}

// ============================================
// CALCULATE POSITIONS BASED ON AVERAGE
// HIGHEST AVERAGE = POSITION 1
// IF SAME AVERAGE, ORDER BY NAME ALPHABETICALLY
// NO TIES - SEQUENTIAL POSITIONS
// ============================================
$students_with_position = [];

// First, filter students with average
$students_with_avg = array_filter($all_students, function($s) {
    return $s['average'] !== null;
});

// Sort by average DESC, then by name ASC
usort($students_with_avg, function($a, $b) {
    if ($a['average'] == $b['average']) {
        $cmp = strcmp($a['first_name'], $b['first_name']);
        if ($cmp == 0) {
            $cmp = strcmp($a['last_name'], $b['last_name']);
        }
        return $cmp;
    }
    return ($b['average'] <=> $a['average']);
});

// Assign sequential positions (no ties)
$position_map = [];
foreach ($students_with_avg as $index => $student) {
    $position_map[$student['id']] = $index + 1;
}
$total_students_with_avg = count($students_with_avg);

// Add position to all students
foreach ($all_students as $student) {
    $student['position'] = $position_map[$student['id']] ?? null;
    $students_with_position[] = $student;
}

// Get Top 10 and Bottom 10 (based on average)
$top_10 = array_slice($students_with_avg, 0, 10);
$bottom_10 = array_slice(array_reverse($students_with_avg), 0, 10);

// Filter by report type
if ($report_type == 'top10') {
    $filtered_students = $top_10;
} elseif ($report_type == 'bottom10') {
    $filtered_students = $bottom_10;
} else {
    $filtered_students = $students_with_position;
}

$total_students = count($filtered_students);

// Get grade letter and points
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

// Calculate GPA
$total_points_sum = 0;
$points_count = 0;
foreach ($students_with_position as $s) {
    if ($s['total_points']) {
        $total_points_sum += $s['total_points'];
        $points_count++;
    }
}
$school_gpa = $points_count > 0 ? round($total_points_sum / $points_count, 4) : 0;

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="results_report_' . $selected_form . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<table border="1">';
    echo '<tr><th colspan="18" style="background: #3B9DB3; color: white; font-size: 16px;">';
    echo 'MUYOVOZI SECONDARY SCHOOL - ' . $selected_form . ' RESULTS REPORT';
    echo '</th></tr>';
    echo '<tr><th colspan="18" style="background: #f0f0f0;">';
    echo 'Exam: ' . ($current_exam ? $current_exam['exam_name'] . ' (' . $current_exam['year'] . ')' : 'N/A');
    echo ' | Date: ' . date('Y-m-d H:i:s');
    echo '</th></tr>';
    echo '<tr>';
    echo '<th>C.NO</th><th>NAMES</th><th>SEX</th><th>COMBS</th>';
    echo '<th>AC</th><th>HTM</th><th>HIST</th><th>GEO</th><th>KISW</th><th>ENG</th>';
    echo '<th>B/MATH</th><th>ADV/M</th><th>ECO</th><th>FREN</th>';
    echo '<th>AVG</th><th>POS</th><th>PTS</th><th>DIV</th>';
    echo '</tr>';
    
    foreach ($filtered_students as $student) {
        $combination = $student['combination'];
        $subjects = $combination_subjects[$combination] ?? [];
        $full_name = trim($student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name']);
        
        echo '<tr>';
        echo '<td>' . $student['index_number'] . '</td>';
        echo '<td>' . $full_name . '</td>';
        echo '<td>' . $student['sex'] . '</td>';
        echo '<td>' . $student['combination'] . '</td>';
        
        $subject_list = ['ac', 'htm', 'his', 'geo', 'kisw', 'eng', 'b_math', 'adv_m', 'eco', 'fren'];
        foreach ($subject_list as $subj) {
            if (in_array($subj, $subjects)) {
                $marks = $student[$subj] !== null ? $student[$subj] : '-';
                echo '<td>' . $marks . '</td>';
            } else {
                echo '<td>-</td>';
            }
        }
        
        echo '<td>' . ($student['average'] ? number_format($student['average'], 1) . '%' : '-') . '</td>';
        echo '<td>' . ($student['position'] ?? '-') . '</td>';
        echo '<td>' . ($student['total_points'] ?? '-') . '</td>';
        echo '<td>' . ($student['division'] ?? '-') . '</td>';
        echo '</tr>';
    }
    
    // Summary row
    echo '<tr><td colspan="18" style="background: #f0f0f0; font-weight: bold;">';
    echo 'Total Students: ' . count($students_with_position) . ' | Division I: ' . $div1_count . ' | Pass Rate: ' . $pass_rate . '% | School GPA: ' . $school_gpa;
    echo '</td></tr>';
    
    echo '</table>';
    exit();
}

// Handle PDF export with TCPDF
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once('../tcpdf/tcpdf.php');
    
    class ResultsPDF extends TCPDF {
        public function Header() {
            $logo_path = '../muyovozi.png';
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 12, 7, 22, 22, 'PNG');
            }
            $this->SetY(12);
            $this->SetFont('helvetica', 'B', 36);
            $this->Cell(0, 6, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
            $this->SetY(38);
            $this->Line(10, $this->GetY(), 280, $this->GetY());
            $this->Ln(6);
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 7);
            $this->Cell(0, 8, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' | Generated: ' . date('Y-m-d H:i:s'), 0, false, 'C');
        }
    }
    
    $pdf = new ResultsPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Muyovozi High School');
    $pdf->SetTitle($selected_form . ' Results Report');
    $pdf->SetSubject('Examination Results');
    
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(10, 48, 10);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->AddPage();
    
    // School info
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'MUYOVOZI HIGH SCHOOL - S5098 ', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, $selected_form . ' ' . ($current_exam ? $current_exam['exam_name'] : 'EXAMINATION') . ' RESULTS: ' . ($current_exam ? $current_exam['year'] : date('Y')), 0, 1, 'C');
    $pdf->Ln(4);
    
    // TOP 10 AND BOTTOM 10 SIDE BY SIDE
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(59, 157, 179);
    $pdf->SetTextColor(255);
    $pdf->Cell(133, 7, 'OVERALL BEST 10 STUDENTS', 1, 0, 'C', 1);
    $pdf->Cell(133, 7, 'LAST TEN STUDENTS', 1, 1, 'C', 1);
    $pdf->SetTextColor(0);
    
    // Table headers for both columns
    $pdf->SetFont('helvetica', 'B', 8);
    $headers = ['INDEX NO', 'NAME', 'SEX', 'COMB', 'AVG', 'PNT', 'DIV'];
    $widths = [22, 49, 14, 12, 12, 12, 12];
    
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 7, $headers[$i], 1, 0, 'C', 1);
    }
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 7, $headers[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Data rows
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetTextColor(0);
    $fill = false;
    
    $max_rows = max(count($top_10), count($bottom_10));
    for ($i = 0; $i < $max_rows; $i++) {
        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 248 : 255, $fill ? 250 : 255);
        
        // Top 10 student
        if (isset($top_10[$i])) {
            $student = $top_10[$i];
            $full_name = substr(trim($student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name']), 0, 18);
            $percentage_display = $student['average'] ? number_format($student['average'], 1) . '%' : '-';
            
            $pdf->Cell($widths[0], 6, $student['index_number'], 1, 0, 'C', $fill);
            $pdf->Cell($widths[1], 6, $full_name, 1, 0, 'L', $fill);
            $pdf->Cell($widths[2], 6, $student['sex'], 1, 0, 'C', $fill);
            $pdf->Cell($widths[3], 6, $student['combination'], 1, 0, 'C', $fill);
            $pdf->Cell($widths[4], 6, $percentage_display, 1, 0, 'C', $fill);
            $pdf->Cell($widths[5], 6, $student['total_points'] ?? '-', 1, 0, 'C', $fill);
            $division_display = $student['division'] ? substr($student['division'], -1) : '-';
            $pdf->Cell($widths[6], 6, $division_display, 1, 0, 'C', $fill);
        } else {
            for ($j = 0; $j < count($widths); $j++) {
                $pdf->Cell($widths[$j], 6, '', 1, 0, 'C', $fill);
            }
        }
        
        // Bottom 10 student
        if (isset($bottom_10[$i])) {
            $student = $bottom_10[$i];
            $full_name = substr(trim($student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name']), 0, 18);
            $percentage_display = $student['average'] ? number_format($student['average'], 1) . '%' : '-';
            
            $pdf->Cell($widths[0], 6, $student['index_number'], 1, 0, 'C', $fill);
            $pdf->Cell($widths[1], 6, $full_name, 1, 0, 'L', $fill);
            $pdf->Cell($widths[2], 6, $student['sex'], 1, 0, 'C', $fill);
            $pdf->Cell($widths[3], 6, $student['combination'], 1, 0, 'C', $fill);
            $pdf->Cell($widths[4], 6, $percentage_display, 1, 0, 'C', $fill);
            $pdf->Cell($widths[5], 6, $student['total_points'] ?? '-', 1, 0, 'C', $fill);
            $division_display = $student['division'] ? substr($student['division'], -1) : '-';
            $pdf->Cell($widths[6], 6, $division_display, 1, 0, 'C', $fill);
        } else {
            for ($j = 0; $j < count($widths); $j++) {
                $pdf->Cell($widths[$j], 6, '', 1, 0, 'C', $fill);
            }
        }
        $pdf->Ln();
        $fill = !$fill;
    }
    $pdf->Ln(4);
    
    // SUMMARY STATISTICS
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, 'SUMMARY STATISTICS', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 5, 'TOTAL STUDENTS: ' . count($students_with_position), 0, 1);
    $pdf->Cell(0, 5, 'DIVISION I: ' . $div1_count . ' | DIVISION II: ' . $div2_count . ' | DIVISION III: ' . $div3_count . ' | DIVISION IV: ' . $div4_count . ' | DIVISION 0: ' . $div0_count, 0, 1);
    $pdf->Cell(0, 5, 'PASS RATE: ' . $pass_rate . '% | SCHOOL GPA: ' . $school_gpa, 0, 1);
    $pdf->Ln(3);
    
    // FULL RESULTS TABLE
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, 'STUDENTS DETAILS', 0, 1, 'C');
    $pdf->Ln(2);
    
    // Full table header
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetFillColor(59, 157, 179);
    $pdf->SetTextColor(255);
    
    $full_headers = ['C.NO', 'NAMES', 'SEX', 'COMBS', 'AC', 'HTM', 'HIST', 'GEO', 'KISW', 'ENG', 'B/MATH', 'ADV/M', 'ECO', 'FREN', 'AVG', 'POS', 'PTS', 'DIV'];
    $full_widths = [20, 45, 12, 12, 12, 12, 12, 12, 12, 12, 16, 16, 12, 12, 16, 12, 12, 18];
    
    for ($i = 0; $i < count($full_headers); $i++) {
        $pdf->Cell($full_widths[$i], 6, $full_headers[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Table content
    $pdf->SetFont('helvetica', '', 6.5);
    $pdf->SetTextColor(0);
    $fill = false;
    
    foreach ($students_with_position as $student) {
        $combination = $student['combination'];
        $subjects = $combination_subjects[$combination] ?? [];
        $full_name = substr(trim($student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name']), 0, 22);
        
        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 248 : 255, $fill ? 250 : 255);
        
        $pdf->Cell($full_widths[0], 5.5, $student['index_number'], 1, 0, 'C', $fill);
        $pdf->Cell($full_widths[1], 5.5, $full_name, 1, 0, 'L', $fill);
        $pdf->Cell($full_widths[2], 5.5, $student['sex'], 1, 0, 'C', $fill);
        $pdf->Cell($full_widths[3], 5.5, $student['combination'], 1, 0, 'C', $fill);
        
        $subject_list = ['ac', 'htm', 'his', 'geo', 'kisw', 'eng', 'b_math', 'adv_m', 'eco', 'fren'];
        $col_idx = 4;
        foreach ($subject_list as $subj) {
            if (in_array($subj, $subjects)) {
                $marks = $student[$subj] !== null ? $student[$subj] : '-';
                $pdf->Cell($full_widths[$col_idx], 5.5, $marks, 1, 0, 'C', $fill);
            } else {
                $pdf->Cell($full_widths[$col_idx], 5.5, '-', 1, 0, 'C', $fill);
            }
            $col_idx++;
        }
        
        $avg_value = $student['average'] ? number_format($student['average'], 1) . '%' : '-';
        $pdf->Cell($full_widths[14], 5.5, $avg_value, 1, 0, 'C', $fill);
        $pdf->Cell($full_widths[15], 5.5, $student['position'] ?? '-', 1, 0, 'C', $fill);
        $pdf->Cell($full_widths[16], 5.5, $student['total_points'] ?? '-', 1, 0, 'C', $fill);
        $pdf->Cell($full_widths[17], 5.5, $student['division'] ?? '-', 1, 0, 'C', $fill);
        
        $pdf->Ln();
        $fill = !$fill;
        
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetFillColor(59, 157, 179);
            $pdf->SetTextColor(255);
            for ($i = 0; $i < count($full_headers); $i++) {
                $pdf->Cell($full_widths[$i], 6, $full_headers[$i], 1, 0, 'C', 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 6.5);
            $pdf->SetTextColor(0);
            $fill = false;
        }
    }
    
    $pdf->Output('results_report_' . $selected_form . '_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Results Report - <?php echo $selected_form; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style> * {margin: 0; padding: 0; box-sizing: border-box; } :root {--primary-color: <?php echo $colors['primary']; ?>; --primary-dark: <?php echo $colors['primary_dark']; ?>; --primary-light: <?php echo $colors['primary_light']; ?>; } body {font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #e9ecef; min-height: 100vh; } /* Main Content - Full Width */ .main-content {margin-left: 260px; padding: 20px; transition: all 0.3s; min-height: 100vh; width: calc(100% - 260px); } /* Full Width Container */ .full-width-container {width: 100%; max-width: 100%; margin: 0; padding: 0; background: transparent; } @media (max-width: 992px) {.main-content {margin-left: 0; padding: 15px; width: 100%; } } /* School Header */ .school-header {text-align: center; margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 12px; border-left: 5px solid var(--primary-color); border-right: 5px solid var(--primary-color); } .school-header h3 {margin: 0; color: #2c3e50; font-size: 1rem; } .school-header h4 {margin: 5px 0 0; color: #555; font-size: 0.9rem; } .school-header h2 {margin: 8px 0 0; color: var(--primary-color); font-size: 1.2rem; font-weight: bold; } /* Filter Card */ .filter-card, .export-card, .results-card, .top-table-container, .bottom-table-container {background: white; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; } .card-header-custom {background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; padding: 12px 20px; font-weight: 600; font-size: 1rem; } /* Stat Card */ .stat-card {background: white; border-radius: 10px; padding: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); } .stat-value {font-size: 22px; font-weight: bold; color: var(--primary-color); } /* Tables */ .table-responsive-custom {overflow-x: auto; -webkit-overflow-scrolling: touch; } .results-table {width: 100%; font-size: 12px; border-collapse: collapse; } .results-table thead th {background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; padding: 10px 8px; text-align: center; white-space: nowrap; font-weight: 600; font-size: 11px; position: sticky; top: 0; } .results-table tbody td {padding: 8px 6px; text-align: center; border-bottom: 1px solid #e9ecef; font-size: 11px; } .results-table tbody tr:hover {background: rgba(59, 157, 179, 0.05); } /* Top/Bottom Tables */ .top-table-container .table, .bottom-table-container .table {font-size: 12px; margin-bottom: 0; } .top-table-container .table th, .bottom-table-container .table th {background: linear-gradient(135deg, #2c3e50, #1a2632); color: white; padding: 10px; font-size: 12px; } .table-title {font-size: 16px; font-weight: bold; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 3px solid var(--primary-color); display: inline-block; } /* Badges */ .badge-division {padding: 3px 8px; border-radius: 20px; font-size: 10px; font-weight: 600; white-space: nowrap; } .division-i { background: #27ae60; color: white; } .division-ii { background: #2ecc71; color: white; } .division-iii { background: #f39c12; color: white; } .division-iv { background: #e67e22; color: white; } .division-0 { background: #e74c3c; color: white; } .position-badge {background: var(--primary-color); color: white; padding: 3px 8px; border-radius: 20px; font-size: 10px; font-weight: 600; display: inline-block; } .position-1 { background: linear-gradient(135deg, #f39c12, #e67e22); } .position-2 { background: linear-gradient(135deg, #95a5a6, #7f8c8d); } .position-3 { background: linear-gradient(135deg, #cd6133, #b33939); } /* Progress Bar */ .progress {height: 3px; margin-top: 3px; border-radius: 2px; } /* Export Buttons */ .btn-export-pdf { background: #dc3545; color: white; border: none; } .btn-export-excel { background: #28a745; color: white; border: none; } .btn-export-pdf:hover, .btn-export-excel:hover { opacity: 0.9; color: white; } /* DataTables Custom */ .dataTables_wrapper .dataTables_filter input, .dataTables_wrapper .dataTables_length select {padding: 5px 10px; border-radius: 8px; border: 1px solid #ddd; } .dataTables_wrapper .dataTables_paginate .paginate_button {padding: 5px 10px; border-radius: 8px; } .dataTables_wrapper .dataTables_info {font-size: 12px; } /* Print Styles */ @media print {.no-print, .filter-card, .export-card, .sidebar, .header, .btn, .dropdown, .nav-tabs, .dataTables_filter, .dataTables_length, .dataTables_paginate {display: none !important; } .main-content {margin-left: 0 !important; padding: 0 !important; width: 100% !important; } .full-width-container {width: 100%; margin: 0; padding: 0; } .results-table thead th {background: #3B9DB3 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .badge-division, .position-badge {-webkit-print-color-adjust: exact; print-color-adjust: exact; } } </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="full-width-container">
            <!-- School Header -->
            <div class="school-header">
                <h2>MUYOVOZI SECONDARY SCHOOL</h2>
                <h4>S5098</h4>
                <h4><?php echo $selected_form; ?> <?php echo ($current_exam ? $current_exam['exam_name'] : 'EXAMINATION'); ?> RESULTS: <?php echo ($current_exam ? $current_exam['year'] : date('Y')); ?></h4>
            </div>

            <!-- Filter Card -->
            <div class="filter-card no-print">
                <div class="card-header-custom">
                    <i class="fas fa-filter me-2"></i>Filter & Sort Options
                </div>
                <div class="card-body p-3">
                    <form method="GET" action="results_report.php" id="reportForm" class="row g-2">
                        <div class="col-md-2">
                            <label class="form-label fw-bold small">Form</label>
                            <select name="form" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="Form Five" <?php echo $selected_form == 'Form Five' ? 'selected' : ''; ?>>Form Five</option>
                                <option value="Form Six" <?php echo $selected_form == 'Form Six' ? 'selected' : ''; ?>>Form Six</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Exam</label>
                            <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="0">-- Select Exam --</option>
                                <?php foreach ($exam_types as $exam): ?>
                                    <option value="<?php echo $exam['id']; ?>" <?php echo $selected_exam == $exam['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($exam['exam_name']); ?> (<?php echo $exam['year']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small">Report Type</label>
                            <select name="report_type" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="all" <?php echo $report_type == 'all' ? 'selected' : ''; ?>>All Students</option>
                                <option value="top10" <?php echo $report_type == 'top10' ? 'selected' : ''; ?>>Top 10 Students</option>
                                <option value="bottom10" <?php echo $report_type == 'bottom10' ? 'selected' : ''; ?>>Bottom 10 Students</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small">Sort By</label>
                            <select name="sort_by" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="index" <?php echo $sort_by == 'index' ? 'selected' : ''; ?>>Index Number</option>
                                <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Alphabetical</option>
                                <option value="division" <?php echo $sort_by == 'division' ? 'selected' : ''; ?>>Division</option>
                                <option value="points" <?php echo $sort_by == 'points' ? 'selected' : ''; ?>>Total Points</option>
                                <option value="average" <?php echo $sort_by == 'average' ? 'selected' : ''; ?>>Average</option>
                                <option value="position" <?php echo $sort_by == 'position' ? 'selected' : ''; ?>>Position</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small">Order</label>
                            <select name="sort_order" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            </select>
                        </div>
                        <div class="col-md-12 mt-2">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small">Search Student</label>
                                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Name or Index..." value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-sm w-100">
                                        <i class="fas fa-search me-1"></i>Search
                                    </button>
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="results_report.php?form=<?php echo urlencode($selected_form); ?>&exam_id=<?php echo $selected_exam; ?>" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-redo me-1"></i>Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TOP 10 STUDENTS -->
            <div class="top-table-container">
                <div class="table-title">
                    <i class="fas fa-trophy me-2" style="color: #f39c12;"></i>OVERALL BEST 10 STUDENTS
                </div>
                <div class="table-responsive-custom">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>S/NO</th>
                                <th>INDEX NO.</th>
                                <th>NAME</th>
                                <th>SEX</th>
                                <th>COMB</th>
                                <th>AVERAGE</th>
                                <th>PNT</th>
                                <th>DIV</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($top_10 as $student): ?>
                            <tr>
                                <td class="text-center"><?php echo $rank++; ?></td>
                                <td><strong><?php echo $student['index_number']; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name'])); ?></strong></td>
                                <td class="text-center"><?php echo $student['sex']; ?></td>
                                <td class="text-center"><?php echo $student['combination']; ?></td>
                                <td class="text-center"><strong><?php echo $student['average'] ? number_format($student['average'], 1) . '%' : '-'; ?></strong></td>
                                
                                <td class="text-center"><?php echo $student['total_points'] ?? '-'; ?></td>
                                <td class="text-center"><span class="badge-division <?php echo str_replace(' ', '-', strtolower($student['division'])); ?>"><?php echo $student['division']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- BOTTOM 10 STUDENTS -->
            <div class="bottom-table-container">
                <div class="table-title">
                    <i class="fas fa-exclamation-triangle me-2" style="color: #e74c3c;"></i>LAST TEN STUDENTS
                </div>
                <div class="table-responsive-custom">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>S/NO</th>
                                <th>INDEX NO</th>
                                <th>NAME</th>
                                <th>SEX</th>
                                <th>COMB</th>
                                <th>AVERAGE</th>
                                <th>PNT</th>
                                <th>DIV</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($bottom_10 as $student): ?>
                            <tr>
                                <td class="text-center"><?php echo $rank++; ?></td>
                                <td><strong><?php echo $student['index_number']; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name'])); ?></strong></td>
                                <td class="text-center"><?php echo $student['sex']; ?></td>
                                <td class="text-center"><?php echo $student['combination']; ?></td>
                                <td class="text-center"><strong><?php echo $student['average'] ? number_format($student['average'], 1) . '%' : '-'; ?></strong></td>
                                <td class="text-center"><?php echo $student['total_points'] ?? '-'; ?></td>
                                <td class="text-center"><span class="badge-division <?php echo str_replace(' ', '-', strtolower($student['division'])); ?>"><?php echo $student['division']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Export Card -->
            <div class="export-card no-print">
                <div class="card-header-custom">
                    <i class="fas fa-download me-2"></i>Export Options
                </div>
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-md-6 text-center">
                            <?php
                            $export_params = [
                                'form' => $selected_form,
                                'exam_id' => $selected_exam,
                                'report_type' => $report_type,
                                'sort_by' => $sort_by,
                                'sort_order' => $sort_order,
                                'search' => $search_query,
                                'export' => 'pdf'
                            ];
                            ?>
                            <a href="results_report.php?<?php echo http_build_query($export_params); ?>" class="btn btn-export-pdf btn-sm px-4">
                                <i class="fas fa-file-pdf me-2"></i>Export as PDF (Landscape A4)
                            </a>
                            <p class="text-muted mt-1 small mb-0">A4 Landscape format with school logo</p>
                        </div>
                        <div class="col-md-6 text-center">
                            <?php
                            $export_params['export'] = 'excel';
                            ?>
                            <a href="results_report.php?<?php echo http_build_query($export_params); ?>" class="btn btn-export-excel btn-sm px-4">
                                <i class="fas fa-file-excel me-2"></i>Export as Excel
                            </a>
                            <p class="text-muted mt-1 small mb-0">Excel spreadsheet for data analysis</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Summary -->
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="stat-card">
                        <strong>TOTAL: <?php echo count($students_with_position); ?> | DIV I: <?php echo $div1_count; ?> | DIV II: <?php echo $div2_count; ?> | DIV III: <?php echo $div3_count; ?> | DIV IV: <?php echo $div4_count; ?> | DIV 0: <?php echo $div0_count; ?></strong>
                        <br>
                        <strong>PASS RATE: <?php echo $pass_rate; ?>% | SCHOOL GPA: <?php echo $school_gpa; ?></strong>
                    </div>
                </div>
            </div>

            <!-- Full Results Table with DataTables -->
            <div class="results-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-table me-2"></i>STUDENTS DETAILS</span>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-sort me-1"></i>Sorted by: <?php echo ucfirst(str_replace('_', ' ', $sort_by)); ?> (<?php echo $sort_order; ?>)
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive-custom">
                        <table class="results-table" id="resultsTable">
                            <thead>
                                <tr>
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
                                    <th>POS</th>
                                    <th>PTS</th>
                                    <th>DIV</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students_with_position)): ?>
                                    <tr><td colspan="18" class="text-center py-4">No results found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($students_with_position as $student): 
                                        $combination = $student['combination'];
                                        $subjects = $combination_subjects[$combination] ?? [];
                                        $full_name = htmlspecialchars(trim($student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name']));
                                    ?>
                                    <tr>
                                        <td><strong><?php echo $student['index_number']; ?></strong></td>
                                        <td class="text-start"><?php echo $full_name; ?></td>
                                        <td><span class="badge <?php echo $student['sex'] == 'Male' ? 'bg-primary' : 'bg-danger'; ?>"><?php echo $student['sex']; ?></span></td>
                                        <td><span class="badge bg-secondary"><?php echo $student['combination']; ?></span></td>
                                        
                                        <?php
                                        $subject_list = ['ac', 'htm', 'his', 'geo', 'kisw', 'eng', 'b_math', 'adv_m', 'eco', 'fren'];
                                        foreach ($subject_list as $subj):
                                            if (in_array($subj, $subjects)):
                                                $marks = $student[$subj];
                                        ?>
                                            <td>
                                                <?php if ($marks !== null): ?>
                                                    <strong><?php echo $marks; ?></strong>
                                                    <div class="small text-muted"><?php echo getGradeLetter($marks); ?></div>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        <?php else: ?>
                                            <td>-</td>
                                        <?php endif; endforeach; ?>
                                        
                                        <td>
                                            <strong><?php echo $student['average'] ? number_format($student['average'], 1) . '%' : '-'; ?></strong>
                                            <?php if ($student['average']): ?>
                                                <div class="progress">
                                                    <div class="progress-bar" style="width: <?php echo $student['average']; ?>%; background: var(--primary-color);"></div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="position-badge <?php 
                                                echo $student['position'] == 1 ? 'position-1' : ($student['position'] == 2 ? 'position-2' : ($student['position'] == 3 ? 'position-3' : '')); 
                                            ?>">
                                                <?php echo $student['position'] ?? '-'; ?>/<?php echo $total_students_with_avg; ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo $student['total_points'] ?? '-'; ?></strong></td>
                                        <td>
                                            <?php if ($student['division'] && $student['division'] != 'Not Assigned'): ?>
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
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script> $(document).ready(function() {$('#resultsTable').DataTable({pageLength: 25, order: [[0, 'asc']], responsive: true, language: {search: "Search:", lengthMenu: "Show _MENU_ entries", info: "Showing _START_ to _END_ of _TOTAL_ entries", paginate: {first: "First", last: "Last", next: "Next", previous: "Previous" } }, columnDefs: [{ orderable: false, targets: [4,5,6,7,8,9,10,11,12,13] } ] }); }); </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>