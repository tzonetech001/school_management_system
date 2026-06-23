<?php
// student_report.php - Student Report Generator
// Multi-school support with dynamic school info from database
// Supports individual and bulk student reports with exam results and discipline

session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user has permission
$admin_id = $_SESSION['admin_id'] ?? 0;

// ==================== GET CURRENT USER'S SCHOOL ID ====================
$school_id = $_SESSION['school_id'] ?? 0;

if ($school_id == 0 && $admin_id > 0) {
    $school_sql = "SELECT school_id FROM admins WHERE id = ?";
    $school_stmt = $conn->prepare($school_sql);
    $school_stmt->bind_param("i", $admin_id);
    $school_stmt->execute();
    $school_result = $school_stmt->get_result();
    if ($school_row = $school_result->fetch_assoc()) {
        $school_id = $school_row['school_id'];
        $_SESSION['school_id'] = $school_id;
    }
    $school_stmt->close();
}

// Check for System Registrar
$is_super_admin = isset($_SESSION['super_admin_id']);

if ($is_super_admin) {
    $selected_school_id = $_GET['school_id'] ?? 0;
    if ($selected_school_id > 0) {
        $school_id = $selected_school_id;
    }
}

// ==================== GET SCHOOL INFO ====================
$school_name = "School Management System";
$school_motto = "Education For Life";
$school_logo_path = null;
$school_code = "";

if ($school_id > 0) {
    $school_sql = "SELECT school_name, school_motto, logo_path, school_code FROM schools WHERE id = ? AND status = 'Active'";
    $school_stmt = $conn->prepare($school_sql);
    $school_stmt->bind_param("i", $school_id);
    $school_stmt->execute();
    $school_result = $school_stmt->get_result();
    if ($school_row = $school_result->fetch_assoc()) {
        $school_name = !empty($school_row['school_name']) ? $school_row['school_name'] : "School Management System";
        $school_motto = !empty($school_row['school_motto']) ? $school_row['school_motto'] : "Education For Life";
        $school_logo_path = $school_row['logo_path'] ?? null;
        $school_code = $school_row['school_code'] ?? "";
    }
    $school_stmt->close();
}

// ==================== GET USER ROLES ====================
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
    if ($role_id == 1 || $role_id == 2 || $role_id == 3 || $role_id == 5) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission && !$is_super_admin) {
    $_SESSION['error'] = "You don't have permission to view student reports.";
    header("Location: ../404.php");
    exit();
}

// ==================== GET ALL SCHOOLS FOR SYSTEM REGISTRAR ====================
$all_schools = [];
if ($is_super_admin) {
    $schools_sql = "SELECT id, school_name, school_code, logo_path FROM schools WHERE status = 'Active' ORDER BY school_name";
    $schools_result = mysqli_query($conn, $schools_sql);
    while ($row = mysqli_fetch_assoc($schools_result)) {
        $all_schools[] = $row;
    }
}

// ==================== GET PARAMETERS ====================
$action = $_GET['action'] ?? 'list'; // list, view, bulk_pdf, bulk_excel
$student_id = $_GET['id'] ?? 0;
$filter_class = $_GET['class'] ?? '';
$filter_combination = $_GET['combination'] ?? '';
$filter_gender = $_GET['gender'] ?? '';
$filter_status = $_GET['status'] ?? 'Active';
$search_term = $_GET['search'] ?? '';

// Get all classes for dropdown
$classes_sql = "SELECT DISTINCT class FROM students WHERE school_id = ? AND class IN ('Form Five', 'Form Six') ORDER BY class";
$classes_stmt = $conn->prepare($classes_sql);
$classes_stmt->bind_param("i", $school_id);
$classes_stmt->execute();
$classes_result = $classes_stmt->get_result();
$classes = [];
while ($row = $classes_result->fetch_assoc()) {
    $classes[] = $row['class'];
}
$classes_stmt->close();

// Get all combinations for dropdown
$combinations_sql = "SELECT DISTINCT combination FROM students WHERE school_id = ? AND combination IS NOT NULL AND combination != '' ORDER BY combination";
$combinations_stmt = $conn->prepare($combinations_sql);
$combinations_stmt->bind_param("i", $school_id);
$combinations_stmt->execute();
$combinations_result = $combinations_stmt->get_result();
$combinations = [];
while ($row = $combinations_result->fetch_assoc()) {
    $combinations[] = $row['combination'];
}
$combinations_stmt->close();

// ==================== FUNCTIONS ====================

function getStudentDiscipline($student_id, $conn) {
    $sql = "SELECT 
                d.list_type,
                d.record_type,
                d.short_note,
                d.created_at,
                CONCAT(a.first_name, ' ', a.last_name) as recorded_by_name
            FROM discipline_records d
            LEFT JOIN admins a ON d.recorded_by = a.id
            WHERE d.student_id = ? AND d.status = 'active'
            ORDER BY d.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    $stmt->close();
    return $records;
}

function getStudentExamResults($student_id, $conn, $limit = 1) {
    // Get latest exam results for the student
    $sql = "SELECT 
                fr.id,
                fr.exam_type_id,
                et.exam_name,
                et.exam_code,
                et.term,
                et.year,
                fr.ac, fr.htm, fr.his, fr.geo, fr.kisw, fr.eng, 
                fr.b_math, fr.adv_m, fr.eco, fr.fren,
                fr.total_points,
                fr.average,
                fr.division,
                fr.entered_at
            FROM form_five_results fr
            JOIN exam_types et ON fr.exam_type_id = et.id
            WHERE fr.student_id = ? AND et.is_active = 1
            ORDER BY fr.entered_at DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
    
    // If no results in form_five, check form_six
    if (empty($results)) {
        $sql = "SELECT 
                    fr.id,
                    fr.exam_type_id,
                    et.exam_name,
                    et.exam_code,
                    et.term,
                    et.year,
                    fr.ac, fr.htm, fr.b_math, fr.his, fr.geo, fr.kisw, fr.eng, 
                    fr.adv_m, fr.eco, fr.fren,
                    fr.total_points,
                    fr.average,
                    fr.division,
                    fr.entered_at
                FROM form_six_results fr
                JOIN exam_types et ON fr.exam_type_id = et.id
                WHERE fr.student_id = ? AND et.is_active = 1
                ORDER BY fr.entered_at DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $student_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    }
    
    return $results;
}

function getStudentData($student_id, $conn) {
    $sql = "SELECT 
                s.*,
                d.id as dormitory_id,
                d.dorm_name,
                dr.room_number,
                dr.room_label,
                sd.bed_number
            FROM students s
            LEFT JOIN student_dormitory sd ON s.id = sd.student_id AND sd.status = 'Active'
            LEFT JOIN dormitories d ON sd.dormitory_id = d.id
            LEFT JOIN dormitory_rooms dr ON sd.room_id = dr.id
            WHERE s.id = ? AND s.school_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $GLOBALS['school_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    return $student;
}

function getStudentShortNote($student_id, $conn) {
    $sql = "SELECT teacher_short_note FROM students WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['teacher_short_note'] ?? '';
}

function updateStudentShortNote($student_id, $note, $conn) {
    $sql = "UPDATE students SET teacher_short_note = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $note, $student_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// ==================== HANDLE SHORT NOTE UPDATE ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_note']) && isset($_POST['student_id'])) {
    $student_id = (int)$_POST['student_id'];
    $note = $_POST['short_note'] ?? '';
    
    if (updateStudentShortNote($student_id, $note, $conn)) {
        $success = "Short note updated successfully!";
    } else {
        $error = "Failed to update short note.";
    }
}

// ==================== VIEW INDIVIDUAL STUDENT ====================
if ($action == 'view' && $student_id > 0) {
    $student = getStudentData($student_id, $conn);
    if (!$student) {
        header("Location: student_report.php?error=Student not found");
        exit();
    }
    
    $discipline_records = getStudentDiscipline($student_id, $conn);
    $exam_results = getStudentExamResults($student_id, $conn);
    $short_note = getStudentShortNote($student_id, $conn);
    
    // Calculate subject points for display
    $subjects = [];
    $subject_names = ['ac', 'htm', 'his', 'geo', 'kisw', 'eng', 'b_math', 'adv_m', 'eco', 'fren'];
    $subject_labels = ['AC', 'HTM', 'HIS', 'GEO', 'KISW', 'ENG', 'B/MATH', 'ADV/M', 'ECO', 'FREN'];
    
    if (!empty($exam_results)) {
        $result = $exam_results[0];
        for ($i = 0; $i < count($subject_names); $i++) {
            $subject = $subject_names[$i];
            if (isset($result[$subject]) && $result[$subject] !== null && $result[$subject] !== '') {
                $subjects[] = [
                    'name' => $subject_labels[$i],
                    'code' => $subject,
                    'marks' => $result[$subject]
                ];
            }
        }
    }
}

// ==================== GET STUDENT LIST FOR BULK ====================
if ($action == 'list' || $action == 'bulk_pdf' || $action == 'bulk_excel') {
    $where_conditions = ["s.school_id = ?", "s.is_leaver = 0"];
    $params = [$school_id];
    $param_types = "i";
    
    if (!empty($filter_class)) {
        $where_conditions[] = "s.class = ?";
        $params[] = $filter_class;
        $param_types .= "s";
    }
    
    if (!empty($filter_combination)) {
        $where_conditions[] = "s.combination = ?";
        $params[] = $filter_combination;
        $param_types .= "s";
    }
    
    if (!empty($filter_gender)) {
        $where_conditions[] = "s.sex = ?";
        $params[] = $filter_gender;
        $param_types .= "s";
    }
    
    if ($filter_status == 'Active') {
        $where_conditions[] = "s.status = 1";
    } elseif ($filter_status == 'Inactive') {
        $where_conditions[] = "s.status = 0";
    }
    
    if (!empty($search_term)) {
        $where_conditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.index_number LIKE ? OR s.admission_number LIKE ?)";
        $search = "%$search_term%";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $param_types .= "ssss";
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    $sql = "SELECT 
                s.id,
                s.index_number,
                s.first_name,
                s.second_name,
                s.last_name,
                s.sex,
                s.class,
                s.combination,
                s.status,
                s.admission_number,
                s.parent_name,
                s.parent_phone,
                s.date_of_birth,
                s.date_of_admission,
                s.teacher_short_note
            FROM students s
            $where_clause
            ORDER BY s.class, s.last_name, s.first_name";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    $total_students = count($student_list);
}

// ==================== BULK PDF EXPORT - INDIVIDUAL PAGES ====================
if ($action == 'bulk_pdf') {
    require_once('../tcpdf/tcpdf.php');
    
    class StudentBulkPDF extends TCPDF {
        public $school_name = '';
        public $school_motto = '';
        public $logo_path = '';
        public $students_data = [];
        public $all_discipline = [];
        public $all_exams = [];
        public $all_notes = [];
        
        public function Header() {
            // No header for individual student pages - we'll add custom header per student
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' | Generated: ' . date('d/m/Y H:i:s'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
        
        public function generateStudentPage($student, $discipline, $exam_results, $short_note) {
            $this->AddPage();
            
            // School Logo and Header
            $logo_used = false;
            $temp_logo_path = null;
            
            if (!empty($this->logo_path)) {
                $full_logo_path = '../' . $this->logo_path;
                if (file_exists($full_logo_path)) {
                    $file_info = pathinfo($full_logo_path);
                    $extension = strtolower($file_info['extension'] ?? '');
                    if ($extension == 'png' && function_exists('imagecreatefrompng') && function_exists('imagejpeg')) {
                        $png = @imagecreatefrompng($full_logo_path);
                        if ($png !== false) {
                            $temp_jpg = sys_get_temp_dir() . '/logo_' . md5($full_logo_path) . '.jpg';
                            imagejpeg($png, $temp_jpg, 90);
                            imagedestroy($png);
                            if (file_exists($temp_jpg)) {
                                $temp_logo_path = $temp_jpg;
                                $full_logo_path = $temp_logo_path;
                            }
                        }
                    }
                    if (file_exists($full_logo_path)) {
                        $this->Image($full_logo_path, 15, 8, 25, 25, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                        $logo_used = true;
                    }
                }
            }
            
            // School Name - Top Center
            $this->SetY(10);
            $this->SetFont('helvetica', 'B', 16);
            $this->Cell(0, 0, strtoupper($this->school_name), 0, 1, 'C');
            $this->SetFont('helvetica', 'I', 10);
            $this->Cell(0, 0, $this->school_motto, 0, 1, 'C');
            
            // Student Name - Large and Bold
            $this->SetY(30);
            $this->SetFont('helvetica', 'B', 18);
            $full_name = $student['first_name'];
            if (!empty($student['second_name'])) {
                $full_name .= ' ' . $student['second_name'];
            }
            $full_name .= ' ' . $student['last_name'];
            $this->Cell(0, 10, strtoupper($full_name), 0, 1, 'C');
            
            // Index Number
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 8, 'Index Number: ' . ($student['index_number'] ?: 'N/A'), 0, 1, 'C');
            
            // Divider Line
            $this->SetY(50);
            $this->Line(15, $this->GetY(), 195, $this->GetY());
            $this->SetY($this->GetY() + 5);
            
            // ==================== STUDENT DETAILS ====================
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 10, 'STUDENT INFORMATION', 0, 1, 'L');
            $this->Ln(3);
            
            // Create student info table
            $html = '
            <table border="1" cellpadding="4" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 10pt;">
                <tr>
                    <td width="15%" style="font-weight: bold; background-color: #f0f0f0;">Full Name</td>
                    <td width="35%">' . htmlspecialchars($full_name) . '</td>
                    <td width="15%" style="font-weight: bold; background-color: #f0f0f0;">Index Number</td>
                    <td width="35%">' . htmlspecialchars($student['index_number'] ?: 'N/A') . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; background-color: #f0f0f0;">Gender</td>
                    <td>' . htmlspecialchars($student['sex']) . '</td>
                    <td style="font-weight: bold; background-color: #f0f0f0;">Date of Birth</td>
                    <td>' . date('d/m/Y', strtotime($student['date_of_birth'])) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; background-color: #f0f0f0;">Class</td>
                    <td>' . htmlspecialchars($student['class']) . '</td>
                    <td style="font-weight: bold; background-color: #f0f0f0;">Combination</td>
                    <td>' . htmlspecialchars($student['combination']) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; background-color: #f0f0f0;">Admission No.</td>
                    <td>' . htmlspecialchars($student['admission_number'] ?: 'N/A') . '</td>
                    <td style="font-weight: bold; background-color: #f0f0f0;">Date of Admission</td>
                    <td>' . date('d/m/Y', strtotime($student['date_of_admission'])) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; background-color: #f0f0f0;">Parent Name</td>
                    <td>' . htmlspecialchars($student['parent_name'] ?: 'N/A') . '</td>
                    <td style="font-weight: bold; background-color: #f0f0f0;">Parent Phone</td>
                    <td>' . htmlspecialchars($student['parent_phone'] ?: 'N/A') . '</td>
                </tr>';
            
            if (!empty($student['dorm_name'])) {
                $html .= '
                <tr>
                    <td style="font-weight: bold; background-color: #f0f0f0;">Dormitory</td>
                    <td>' . htmlspecialchars($student['dorm_name']) . '</td>
                    <td style="font-weight: bold; background-color: #f0f0f0;">Room</td>
                    <td>' . htmlspecialchars($student['room_number']) . ' - ' . htmlspecialchars($student['room_label'] ?? '') . '</td>
                </tr>';
            }
            
            $html .= '
            </table>';
            
            $this->writeHTML($html, true, false, true, false, '');
            $this->Ln(5);
            
            // ==================== EXAM RESULTS ====================
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 10, 'EXAM RESULTS', 0, 1, 'L');
            $this->Ln(3);
            
            if (!empty($exam_results)) {
                foreach ($exam_results as $result) {
                    $this->SetFont('helvetica', 'B', 10);
                    $this->Cell(0, 8, 'Exam: ' . htmlspecialchars($result['exam_name']) . ' (' . htmlspecialchars($result['exam_code']) . ') - Term ' . htmlspecialchars($result['term']) . ', ' . htmlspecialchars($result['year']), 0, 1);
                    $this->Ln(2);
                    
                    // Subject results table
                    $subject_names = ['ac' => 'AC', 'htm' => 'HTM', 'his' => 'HIS', 'geo' => 'GEO', 
                                     'kisw' => 'KISW', 'eng' => 'ENG', 'b_math' => 'B/MATH', 
                                     'adv_m' => 'ADV/M', 'eco' => 'ECO', 'fren' => 'FREN'];
                    
                    $html = '<table border="1" cellpadding="3" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 9pt;">
                        <tr style="background-color: #3B9DB3; color: white; font-weight: bold;">
                            <th style="text-align: center;">Subject</th>
                            <th style="text-align: center;">Marks</th>
                            <th style="text-align: center;">Subject</th>
                            <th style="text-align: center;">Marks</th>
                        </tr>';
                    
                    $subjects_display = [];
                    foreach ($subject_names as $code => $label) {
                        if (isset($result[$code]) && $result[$code] !== null && $result[$code] !== '') {
                            $subjects_display[] = ['label' => $label, 'marks' => $result[$code]];
                        }
                    }
                    
                    $col_count = count($subjects_display);
                    $rows = ceil($col_count / 2);
                    
                    for ($i = 0; $i < $rows; $i++) {
                        $html .= '<tr>';
                        $idx1 = $i * 2;
                        if ($idx1 < $col_count) {
                            $html .= '<td style="text-align: center;">' . $subjects_display[$idx1]['label'] . '</td>
                                      <td style="text-align: center;">' . $subjects_display[$idx1]['marks'] . '</td>';
                        } else {
                            $html .= '<td></td><td></td>';
                        }
                        
                        $idx2 = $i * 2 + 1;
                        if ($idx2 < $col_count) {
                            $html .= '<td style="text-align: center;">' . $subjects_display[$idx2]['label'] . '</td>
                                      <td style="text-align: center;">' . $subjects_display[$idx2]['marks'] . '</td>';
                        } else {
                            $html .= '<td></td><td></td>';
                        }
                        $html .= '</tr>';
                    }
                    
                    $html .= '<tr style="background-color: #f0f0f0;">
                        <td colspan="4" style="text-align: center; font-weight: bold;">
                            Total Points: ' . ($result['total_points'] ?? 'N/A') . ' | 
                            Average: ' . ($result['average'] ?? 'N/A') . ' | 
                            Division: ' . ($result['division'] ?? 'N/A') . '
                        </td>
                    </tr>';
                    $html .= '</table>';
                    
                    $this->writeHTML($html, true, false, true, false, '');
                    $this->Ln(5);
                }
            } else {
                $this->SetFont('helvetica', '', 10);
                $this->SetTextColor(150);
                $this->Cell(0, 10, 'No exam results found.', 0, 1, 'L');
                $this->SetTextColor(0);
            }
            
            // ==================== TEACHER SHORT NOTE ====================
            $this->Ln(5);
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 10, 'TEACHER\'S SHORT NOTE', 0, 1, 'L');
            $this->SetFont('helvetica', '', 10);
            $this->SetFillColor(255, 255, 200);
            $note_text = !empty($short_note) ? htmlspecialchars($short_note) : 'No short note available.';
            $this->MultiCell(0, 10, $note_text, 1, 'L', true);
            $this->Ln(5);
            
            // ==================== DISCIPLINE RECORDS ====================
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 10, 'DISCIPLINE RECORDS', 0, 1, 'L');
            $this->Ln(3);
            
            if (!empty($discipline)) {
                $html = '<table border="1" cellpadding="3" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 9pt;">
                    <tr style="background-color: #3B9DB3; color: white; font-weight: bold;">
                        <th style="text-align: center;">List Type</th>
                        <th style="text-align: center;">Record Type</th>
                        <th style="text-align: center;">Short Note</th>
                        <th style="text-align: center;">Recorded By</th>
                        <th style="text-align: center;">Date</th>
                    </tr>';
                
                foreach ($discipline as $record) {
                    $html .= '<tr>
                        <td style="text-align: center; font-weight: bold; color: ' . ($record['list_type'] == 'white' ? '#28a745' : '#dc3545') . ';">' . ucfirst($record['list_type']) . ' List</td>
                        <td style="text-align: center;">' . ucfirst($record['record_type']) . '</td>
                        <td>' . htmlspecialchars($record['short_note']) . '</td>
                        <td style="text-align: center;">' . htmlspecialchars($record['recorded_by_name'] ?: 'N/A') . '</td>
                        <td style="text-align: center;">' . date('d/m/Y', strtotime($record['created_at'])) . '</td>
                    </tr>';
                }
                $html .= '</table>';
                $this->writeHTML($html, true, false, true, false, '');
            } else {
                $this->SetFont('helvetica', '', 10);
                $this->SetTextColor(150);
                $this->Cell(0, 10, 'No discipline records found.', 0, 1, 'L');
                $this->SetTextColor(0);
            }
            
            // Clean up temp logo if used
            if (isset($temp_logo_path) && file_exists($temp_logo_path)) {
                @unlink($temp_logo_path);
            }
        }
    }
    
    // Create PDF
    $pdf = new StudentBulkPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->school_name = $school_name;
    $pdf->school_motto = $school_motto;
    $pdf->logo_path = $school_logo_path;
    
    $pdf->SetCreator($school_name);
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Student Reports - ' . $school_name);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(15);
    $pdf->SetAutoPageBreak(TRUE, 20);
    $pdf->setPrintHeader(false);
    
    // Generate page for each student
    $counter = 0;
    foreach ($student_list as $student) {
        $counter++;
        // Get discipline for this student
        $discipline = getStudentDiscipline($student['id'], $conn);
        // Get exam results for this student
        $exam_results = getStudentExamResults($student['id'], $conn);
        // Get short note
        $short_note = $student['teacher_short_note'] ?? '';
        
        // Generate individual page
        $pdf->generateStudentPage($student, $discipline, $exam_results, $short_note);
    }
    
    // Output PDF
    $pdf->Output('all_student_reports_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}

// ==================== BULK EXCEL EXPORT ====================
if ($action == 'bulk_excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="students_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #3B9DB3; color: white; font-weight: bold; padding: 8px; border: 1px solid #ddd; }
            td { padding: 6px; border: 1px solid #ddd; }
            .header { text-align: center; font-size: 16px; font-weight: bold; margin-bottom: 20px; }
            .sub-header { text-align: center; font-size: 12px; margin-bottom: 20px; color: #666; }
        </style>
    </head>
    <body>';
    
    echo '<div class="header">' . strtoupper(htmlspecialchars($school_name)) . '</div>';
    echo '<div class="sub-header">' . htmlspecialchars($school_motto) . '</div>';
    echo '<div class="header" style="font-size:14px;">STUDENTS REPORT</div>';
    echo '<div style="margin-bottom:20px;">Generated: ' . date('d/m/Y H:i:s') . ' | Total: ' . $total_students . ' students</div>';
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>S/N</th>';
    echo '<th>Index No.</th>';
    echo '<th>Full Name</th>';
    echo '<th>Class</th>';
    echo '<th>Combination</th>';
    echo '<th>Gender</th>';
    echo '<th>Admission No.</th>';
    echo '<th>Parent Name</th>';
    echo '<th>Parent Phone</th>';
    echo '<th>Status</th>';
    echo '<th>Short Note</th>';
    echo '</tr>';
    
    $sn = 1;
    foreach($student_list as $student) {
        $full_name = $student['first_name'];
        if (!empty($student['second_name'])) {
            $full_name .= ' ' . $student['second_name'];
        }
        $full_name .= ' ' . $student['last_name'];
        
        echo '<tr>';
        echo '<td>' . $sn . '</td>';
        echo '<td>' . htmlspecialchars($student['index_number'] ?: 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($full_name) . '</td>';
        echo '<td>' . htmlspecialchars($student['class']) . '</td>';
        echo '<td>' . htmlspecialchars($student['combination']) . '</td>';
        echo '<td>' . htmlspecialchars($student['sex']) . '</td>';
        echo '<td>' . htmlspecialchars($student['admission_number'] ?: 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($student['parent_name'] ?: 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($student['parent_phone'] ?: 'N/A') . '</td>';
        echo '<td>' . ($student['status'] ? 'Active' : 'Inactive') . '</td>';
        echo '<td>' . htmlspecialchars($student['teacher_short_note'] ?: '') . '</td>';
        echo '</tr>';
        $sn++;
    }
    
    echo '</table>';
    echo '</body></html>';
    exit();
}

// ==================== INDIVIDUAL PDF EXPORT ====================
if ($action == 'view' && $student_id > 0 && isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once('../tcpdf/tcpdf.php');
    
    class IndividualStudentPDF extends TCPDF {
        public $school_name = '';
        public $school_motto = '';
        public $logo_path = '';
        public $student = null;
        public $discipline = [];
        public $results = [];
        public $subjects = [];
        public $short_note = '';
        
        public function Header() {
            // No header - we'll add custom header in content
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' | Generated: ' . date('d/m/Y H:i:s'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }
    
    $pdf = new IndividualStudentPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->school_name = $school_name;
    $pdf->school_motto = $school_motto;
    $pdf->logo_path = $school_logo_path;
    $pdf->student = $student;
    $pdf->discipline = $discipline_records;
    $pdf->results = $exam_results;
    $pdf->subjects = $subjects;
    $pdf->short_note = $short_note;
    
    $pdf->SetCreator($school_name);
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Student Report - ' . $student['first_name'] . ' ' . $student['last_name']);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(15);
    $pdf->SetAutoPageBreak(TRUE, 20);
    $pdf->setPrintHeader(false);
    $pdf->AddPage();
    
    // School Logo and Header
    $logo_used = false;
    $temp_logo_path = null;
    
    if (!empty($school_logo_path)) {
        $full_logo_path = '../' . $school_logo_path;
        if (file_exists($full_logo_path)) {
            $file_info = pathinfo($full_logo_path);
            $extension = strtolower($file_info['extension'] ?? '');
            if ($extension == 'png' && function_exists('imagecreatefrompng') && function_exists('imagejpeg')) {
                $png = @imagecreatefrompng($full_logo_path);
                if ($png !== false) {
                    $temp_jpg = sys_get_temp_dir() . '/logo_' . md5($full_logo_path) . '.jpg';
                    imagejpeg($png, $temp_jpg, 90);
                    imagedestroy($png);
                    if (file_exists($temp_jpg)) {
                        $temp_logo_path = $temp_jpg;
                        $full_logo_path = $temp_logo_path;
                    }
                }
            }
            if (file_exists($full_logo_path)) {
                $pdf->Image($full_logo_path, 15, 8, 25, 25, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $logo_used = true;
            }
        }
    }
    
    // School Name - Top Center
    $pdf->SetY(10);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 0, strtoupper($school_name), 0, 1, 'C');
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(0, 0, $school_motto, 0, 1, 'C');
    
    // Student Name - Large and Bold
    $pdf->SetY(30);
    $pdf->SetFont('helvetica', 'B', 18);
    $full_name = $student['first_name'];
    if (!empty($student['second_name'])) {
        $full_name .= ' ' . $student['second_name'];
    }
    $full_name .= ' ' . $student['last_name'];
    $pdf->Cell(0, 10, strtoupper($full_name), 0, 1, 'C');
    
    // Index Number
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Index Number: ' . ($student['index_number'] ?: 'N/A'), 0, 1, 'C');
    
    // Divider Line
    $pdf->SetY(50);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->SetY($pdf->GetY() + 5);
    
    // ==================== STUDENT DETAILS ====================
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'STUDENT INFORMATION', 0, 1, 'L');
    $pdf->Ln(3);
    
    // Create student info table
    $html = '
    <table border="1" cellpadding="4" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 10pt;">
        <tr>
            <td width="15%" style="font-weight: bold; background-color: #f0f0f0;">Full Name</td>
            <td width="35%">' . htmlspecialchars($full_name) . '</td>
            <td width="15%" style="font-weight: bold; background-color: #f0f0f0;">Index Number</td>
            <td width="35%">' . htmlspecialchars($student['index_number'] ?: 'N/A') . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f0f0f0;">Gender</td>
            <td>' . htmlspecialchars($student['sex']) . '</td>
            <td style="font-weight: bold; background-color: #f0f0f0;">Date of Birth</td>
            <td>' . date('d/m/Y', strtotime($student['date_of_birth'])) . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f0f0f0;">Class</td>
            <td>' . htmlspecialchars($student['class']) . '</td>
            <td style="font-weight: bold; background-color: #f0f0f0;">Combination</td>
            <td>' . htmlspecialchars($student['combination']) . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f0f0f0;">Admission No.</td>
            <td>' . htmlspecialchars($student['admission_number'] ?: 'N/A') . '</td>
            <td style="font-weight: bold; background-color: #f0f0f0;">Date of Admission</td>
            <td>' . date('d/m/Y', strtotime($student['date_of_admission'])) . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f0f0f0;">Parent Name</td>
            <td>' . htmlspecialchars($student['parent_name'] ?: 'N/A') . '</td>
            <td style="font-weight: bold; background-color: #f0f0f0;">Parent Phone</td>
            <td>' . htmlspecialchars($student['parent_phone'] ?: 'N/A') . '</td>
        </tr>';
    
    if (!empty($student['dorm_name'])) {
        $html .= '
        <tr>
            <td style="font-weight: bold; background-color: #f0f0f0;">Dormitory</td>
            <td>' . htmlspecialchars($student['dorm_name']) . '</td>
            <td style="font-weight: bold; background-color: #f0f0f0;">Room</td>
            <td>' . htmlspecialchars($student['room_number']) . ' - ' . htmlspecialchars($student['room_label'] ?? '') . '</td>
        </tr>';
    }
    
    $html .= '
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(5);
    
    // ==================== EXAM RESULTS ====================
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'EXAM RESULTS', 0, 1, 'L');
    $pdf->Ln(3);
    
    if (!empty($exam_results)) {
        foreach ($exam_results as $result) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 8, 'Exam: ' . htmlspecialchars($result['exam_name']) . ' (' . htmlspecialchars($result['exam_code']) . ') - Term ' . htmlspecialchars($result['term']) . ', ' . htmlspecialchars($result['year']), 0, 1);
            $pdf->Ln(2);
            
            // Subject results table
            $subject_names = ['ac' => 'AC', 'htm' => 'HTM', 'his' => 'HIS', 'geo' => 'GEO', 
                             'kisw' => 'KISW', 'eng' => 'ENG', 'b_math' => 'B/MATH', 
                             'adv_m' => 'ADV/M', 'eco' => 'ECO', 'fren' => 'FREN'];
            
            $html = '<table border="1" cellpadding="3" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 9pt;">
                <tr style="background-color: #3B9DB3; color: white; font-weight: bold;">
                    <th style="text-align: center;">Subject</th>
                    <th style="text-align: center;">Marks</th>
                    <th style="text-align: center;">Subject</th>
                    <th style="text-align: center;">Marks</th>
                </tr>';
            
            $subjects_display = [];
            foreach ($subject_names as $code => $label) {
                if (isset($result[$code]) && $result[$code] !== null && $result[$code] !== '') {
                    $subjects_display[] = ['label' => $label, 'marks' => $result[$code]];
                }
            }
            
            $col_count = count($subjects_display);
            $rows = ceil($col_count / 2);
            
            for ($i = 0; $i < $rows; $i++) {
                $html .= '<tr>';
                $idx1 = $i * 2;
                if ($idx1 < $col_count) {
                    $html .= '<td style="text-align: center;">' . $subjects_display[$idx1]['label'] . '</td>
                              <td style="text-align: center;">' . $subjects_display[$idx1]['marks'] . '</td>';
                } else {
                    $html .= '<td></td><td></td>';
                }
                
                $idx2 = $i * 2 + 1;
                if ($idx2 < $col_count) {
                    $html .= '<td style="text-align: center;">' . $subjects_display[$idx2]['label'] . '</td>
                              <td style="text-align: center;">' . $subjects_display[$idx2]['marks'] . '</td>';
                } else {
                    $html .= '<td></td><td></td>';
                }
                $html .= '</tr>';
            }
            
            $html .= '<tr style="background-color: #f0f0f0;">
                <td colspan="4" style="text-align: center; font-weight: bold;">
                    Total Points: ' . ($result['total_points'] ?? 'N/A') . ' | 
                    Average: ' . ($result['average'] ?? 'N/A') . ' | 
                    Division: ' . ($result['division'] ?? 'N/A') . '
                </td>
            </tr>';
            $html .= '</table>';
            
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Ln(5);
        }
    } else {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(150);
        $pdf->Cell(0, 10, 'No exam results found.', 0, 1, 'L');
        $pdf->SetTextColor(0);
    }
    
    // ==================== TEACHER SHORT NOTE ====================
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'TEACHER\'S SHORT NOTE', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(255, 255, 200);
    $note_text = !empty($short_note) ? htmlspecialchars($short_note) : 'No short note available.';
    $pdf->MultiCell(0, 10, $note_text, 1, 'L', true);
    $pdf->Ln(5);
    
    // ==================== DISCIPLINE RECORDS ====================
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'DISCIPLINE RECORDS', 0, 1, 'L');
    $pdf->Ln(3);
    
    if (!empty($discipline_records)) {
        $html = '<table border="1" cellpadding="3" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 9pt;">
            <tr style="background-color: #3B9DB3; color: white; font-weight: bold;">
                <th style="text-align: center;">List Type</th>
                <th style="text-align: center;">Record Type</th>
                <th style="text-align: center;">Short Note</th>
                <th style="text-align: center;">Recorded By</th>
                <th style="text-align: center;">Date</th>
            </tr>';
        
        foreach ($discipline_records as $record) {
            $html .= '<tr>
                <td style="text-align: center; font-weight: bold; color: ' . ($record['list_type'] == 'white' ? '#28a745' : '#dc3545') . ';">' . ucfirst($record['list_type']) . ' List</td>
                <td style="text-align: center;">' . ucfirst($record['record_type']) . '</td>
                <td>' . htmlspecialchars($record['short_note']) . '</td>
                <td style="text-align: center;">' . htmlspecialchars($record['recorded_by_name'] ?: 'N/A') . '</td>
                <td style="text-align: center;">' . date('d/m/Y', strtotime($record['created_at'])) . '</td>
            </tr>';
        }
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');
    } else {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(150);
        $pdf->Cell(0, 10, 'No discipline records found.', 0, 1, 'L');
        $pdf->SetTextColor(0);
    }
    
    if (isset($temp_logo_path) && file_exists($temp_logo_path)) {
        @unlink($temp_logo_path);
    }
    
    $pdf->Output('student_report_' . $student['index_number'] . '_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}

// ==================== PAGE ====================
include '../controller/header.php';
include '../controller/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                <i class="fas fa-file-alt me-2"></i>Student Report Generator
                <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($school_name); ?></span>
            </h2>
            <div>
                <a href="students.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Students
                </a>
            </div>
        </div>

        <!-- School Info Card -->
        <div class="card mb-4 bg-light">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <?php if (!empty($school_logo_path)): ?>
                            <img src="../<?php echo htmlspecialchars($school_logo_path); ?>" 
                                 alt="<?php echo htmlspecialchars($school_name); ?> Logo" 
                                 class="img-fluid" style="max-height: 80px; border-radius: 8px;">
                        <?php else: ?>
                            <div class="logo-placeholder" style="width: 80px; height: 80px; background: #3B9DB3; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; margin: 0 auto;">
                                <?php echo substr($school_name, 0, 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h3 class="mb-1"><?php echo htmlspecialchars($school_name); ?></h3>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($school_motto); ?></p>
                        <?php if (!empty($school_code)): ?>
                            <span class="badge bg-secondary">Code: <?php echo htmlspecialchars($school_code); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <span class="badge bg-info fs-6">Total Students: <?php echo isset($student_list) ? count($student_list) : 0; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_super_admin && count($all_schools) > 1): ?>
        <!-- School Selector for System Registrar -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h5 class="mb-0"><i class="fas fa-building me-2"></i>Select School</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="student_report.php" class="row g-3">
                    <div class="col-md-4">
                        <select name="school_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">-- Select School --</option>
                            <?php foreach ($all_schools as $school): ?>
                                <option value="<?php echo $school['id']; ?>" 
                                    <?php echo ($school_id == $school['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($school['school_name']); ?> (<?php echo htmlspecialchars($school['school_code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <a href="student_report.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($action == 'view' && isset($student)): ?>
        <!-- Individual Student Report -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-user-graduate me-2"></i>
                        Student Report: <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                        <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($student['index_number']); ?></span>
                    </h4>
                    <div>
                        <a href="student_report.php?action=view&id=<?php echo $student_id; ?>&export=pdf<?php echo ($is_super_admin && $school_id > 0) ? '&school_id=' . $school_id : ''; ?>" 
                           class="btn btn-danger btn-sm">
                            <i class="fas fa-file-pdf me-1"></i>Download PDF
                        </a>
                        <a href="student_report.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to List
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Student Info -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <table class="table table-bordered table-sm">
                            <tr>
                                <th width="35%">Full Name</th>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . ($student['second_name'] ?? '') . ' ' . $student['last_name']); ?></strong>
                                </td>
                            </tr>
                            <tr>
                                <th>Index Number</th>
                                <td><strong><?php echo htmlspecialchars($student['index_number'] ?: 'N/A'); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Admission Number</th>
                                <td><?php echo htmlspecialchars($student['admission_number'] ?: 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Gender</th>
                                <td>
                                    <span class="badge <?php echo $student['sex'] == 'Male' ? 'bg-info' : 'bg-pink'; ?>">
                                        <?php echo htmlspecialchars($student['sex']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Date of Birth</th>
                                <td><?php echo date('d/m/Y', strtotime($student['date_of_birth'])); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered table-sm">
                            <tr>
                                <th width="35%">Class</th>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($student['class']); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Combination</th>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($student['combination']); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Date of Admission</th>
                                <td><?php echo date('d/m/Y', strtotime($student['date_of_admission'])); ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="badge <?php echo $student['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $student['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php if (!empty($student['dorm_name'])): ?>
                            <tr>
                                <th>Dormitory</th>
                                <td><?php echo htmlspecialchars($student['dorm_name']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- Parent Info -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h6 class="mb-2">Parent/Guardian Information</h6>
                        <table class="table table-bordered table-sm">
                            <tr>
                                <th width="15%">Parent Name</th>
                                <td width="35%"><?php echo htmlspecialchars($student['parent_name'] ?: 'N/A'); ?></td>
                                <th width="15%">Parent Phone</th>
                                <td width="35%"><?php echo htmlspecialchars($student['parent_phone'] ?: 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Parent Occupation</th>
                                <td><?php echo htmlspecialchars($student['parent_occupation'] ?: 'N/A'); ?></td>
                                <th>Parent Residence</th>
                                <td><?php echo htmlspecialchars($student['parent_residence'] ?: 'N/A'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Teacher Short Note - Editable -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-warning">
                                <h6 class="mb-0"><i class="fas fa-pencil-alt me-2"></i>Teacher's Short Note</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <?php echo $success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php endif; ?>
                                <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php endif; ?>
                                <form method="POST" action="student_report.php?action=view&id=<?php echo $student_id; ?>" class="row g-3">
                                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                    <div class="col-md-10">
                                        <textarea name="short_note" class="form-control" rows="3" placeholder="Enter short note about this student..."><?php echo htmlspecialchars($short_note); ?></textarea>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" name="update_note" class="btn btn-primary w-100" style="height: 100%;">
                                            <i class="fas fa-save me-1"></i>Update Note
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exam Results -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h6 class="mb-2"><i class="fas fa-graduation-cap me-2"></i>Exam Results</h6>
                        <?php if (!empty($exam_results)): ?>
                            <?php foreach ($exam_results as $result): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <strong><?php echo htmlspecialchars($result['exam_name']); ?></strong>
                                    <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($result['exam_code']); ?></span>
                                    <span class="badge bg-info ms-2">Term <?php echo htmlspecialchars($result['term']); ?></span>
                                    <span class="badge bg-dark ms-2"><?php echo htmlspecialchars($result['year']); ?></span>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>Marks</th>
                                                    <th>Subject</th>
                                                    <th>Marks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $subject_names = ['ac' => 'AC', 'htm' => 'HTM', 'his' => 'HIS', 'geo' => 'GEO', 
                                                                 'kisw' => 'KISW', 'eng' => 'ENG', 'b_math' => 'B/MATH', 
                                                                 'adv_m' => 'ADV/M', 'eco' => 'ECO', 'fren' => 'FREN'];
                                                $subjects_display = [];
                                                foreach ($subject_names as $code => $label) {
                                                    if (isset($result[$code]) && $result[$code] !== null && $result[$code] !== '') {
                                                        $subjects_display[] = ['label' => $label, 'marks' => $result[$code]];
                                                    }
                                                }
                                                $col_count = count($subjects_display);
                                                $rows = ceil($col_count / 2);
                                                for ($i = 0; $i < $rows; $i++):
                                                ?>
                                                <tr>
                                                    <?php
                                                    $idx1 = $i * 2;
                                                    if ($idx1 < $col_count):
                                                    ?>
                                                    <td><?php echo $subjects_display[$idx1]['label']; ?></td>
                                                    <td><strong><?php echo $subjects_display[$idx1]['marks']; ?></strong></td>
                                                    <?php else: ?>
                                                    <td></td><td></td>
                                                    <?php endif; ?>
                                                    <?php
                                                    $idx2 = $i * 2 + 1;
                                                    if ($idx2 < $col_count):
                                                    ?>
                                                    <td><?php echo $subjects_display[$idx2]['label']; ?></td>
                                                    <td><strong><?php echo $subjects_display[$idx2]['marks']; ?></strong></td>
                                                    <?php else: ?>
                                                    <td></td><td></td>
                                                    <?php endif; ?>
                                                </tr>
                                                <?php endfor; ?>
                                                <tr class="table-secondary">
                                                    <td colspan="4" class="text-center">
                                                        <strong>Total Points: <?php echo $result['total_points'] ?? 'N/A'; ?></strong> | 
                                                        <strong>Average: <?php echo $result['average'] ?? 'N/A'; ?></strong> | 
                                                        <strong>Division: <?php echo $result['division'] ?? 'N/A'; ?></strong>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No exam results found for this student.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Discipline Records -->
                <div class="row">
                    <div class="col-md-12">
                        <h6 class="mb-2"><i class="fas fa-gavel me-2"></i>Discipline Records</h6>
                        <?php if (!empty($discipline_records)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>List Type</th>
                                        <th>Record Type</th>
                                        <th>Short Note</th>
                                        <th>Recorded By</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($discipline_records as $record): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php echo $record['list_type'] == 'white' ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo ucfirst($record['list_type']); ?> List
                                            </span>
                                        </td>
                                        <td><?php echo ucfirst($record['record_type']); ?></td>
                                        <td><?php echo htmlspecialchars($record['short_note']); ?></td>
                                        <td><?php echo htmlspecialchars($record['recorded_by_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">No discipline records found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Student List for Bulk Reports -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-list me-2"></i>Student List
                    </h4>
                    <div>
                        <span class="badge bg-light text-dark">
                            <?php echo isset($student_list) ? count($student_list) : 0; ?> students found
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" action="student_report.php" id="filterForm" class="mb-4">
                    <?php if ($is_super_admin && $school_id > 0): ?>
                        <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-2 mb-2">
                            <label class="form-label">Class</label>
                            <select name="class" class="form-select">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class); ?>" <?php echo $filter_class == $class ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="form-label">Combination</label>
                            <select name="combination" class="form-select">
                                <option value="">All Combinations</option>
                                <?php foreach ($combinations as $combo): ?>
                                <option value="<?php echo htmlspecialchars($combo); ?>" <?php echo $filter_combination == $combo ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($combo); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">All Genders</option>
                                <option value="Male" <?php echo $filter_gender == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $filter_gender == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?php echo $filter_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $filter_status == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Name, Index, Admission..." value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                        <div class="col-md-1 mb-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="student_report.php<?php echo ($is_super_admin && $school_id > 0) ? '?school_id=' . $school_id : ''; ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-redo me-1"></i>Reset
                                    </a>
                                </div>
                                <div>
                                    <!-- Bulk PDF Button - Downloads all students in one PDF with individual pages -->
                                    <a href="student_report.php?action=bulk_pdf<?php echo ($is_super_admin && $school_id > 0) ? '&school_id=' . $school_id : ''; ?>&<?php echo http_build_query($_GET); ?>" 
                                       class="btn btn-danger" style="padding: 8px 20px;">
                                        <i class="fas fa-file-pdf me-2"></i>Download All Reports (PDF)
                                    </a>
                                    <a href="student_report.php?action=bulk_excel<?php echo ($is_super_admin && $school_id > 0) ? '&school_id=' . $school_id : ''; ?>&<?php echo http_build_query($_GET); ?>" 
                                       class="btn btn-success" style="padding: 8px 20px;">
                                        <i class="fas fa-file-excel me-2"></i>Export to Excel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Student Table -->
                <?php if (!empty($student_list)): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="studentTable">
                        <thead class="table-light">
                            <tr>
                                <th>S/N</th>
                                <th>Index No.</th>
                                <th>Full Name</th>
                                <th>Class</th>
                                <th>Combination</th>
                                <th>Gender</th>
                                <th>Parent</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_list as $index => $student): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($student['index_number'] ?: 'N/A'); ?></strong></td>
                                <td>
                                    <?php 
                                    $full_name = $student['first_name'];
                                    if (!empty($student['second_name'])) {
                                        $full_name .= ' ' . $student['second_name'];
                                    }
                                    $full_name .= ' ' . $student['last_name'];
                                    echo htmlspecialchars($full_name);
                                    ?>
                                    <?php if (!empty($student['teacher_short_note'])): ?>
                                    <i class="fas fa-pencil-alt text-warning ms-1" title="<?php echo htmlspecialchars($student['teacher_short_note']); ?>"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($student['class']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($student['combination']); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $student['sex'] == 'Male' ? 'bg-info' : 'bg-pink'; ?>">
                                        <?php echo htmlspecialchars($student['sex']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($student['parent_name'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['parent_phone'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="badge <?php echo $student['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $student['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="student_report.php?action=view&id=<?php echo $student['id']; ?><?php echo ($is_super_admin && $school_id > 0) ? '&school_id=' . $school_id : ''; ?>" 
                                       class="btn btn-sm btn-info" title="View Full Report">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                    <a href="student_report.php?action=view&id=<?php echo $student['id']; ?>&export=pdf<?php echo ($is_super_admin && $school_id > 0) ? '&school_id=' . $school_id : ''; ?>" 
                                       class="btn btn-sm btn-danger" title="Download PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Bulk Download Info -->
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Bulk Download:</strong> Click the <strong>"Download All Reports (PDF)"</strong> button above to generate a single PDF containing individual report pages for all <?php echo count($student_list); ?> students. Each student will have their own page with full details.
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4>No students found</h4>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.bg-pink {
    background-color: #e83e8c !important;
    color: white;
}

.logo-placeholder {
    background: #3B9DB3 !important;
}

.table th {
    background-color: rgba(59, 157, 179, 0.1);
    border-bottom: 2px solid #3B9DB3;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(59, 157, 179, 0.02);
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.85rem;
    }
    .btn-sm {
        padding: 0.2rem 0.4rem;
    }
}
</style>

<?php include '../controller/footer.php'; ?>